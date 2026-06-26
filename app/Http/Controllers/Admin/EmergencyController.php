<?php

namespace App\Http\Controllers\Admin;

use App\Events\AmbulanceAvailabilityUpdated;
use App\Events\DispatchRequestSent;
use App\Events\DriverAvailabilityUpdated;
use App\Events\EmergencyStatusUpdated;
use App\Events\PendingRideTaken;
use App\Events\RequestStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Driver\DriverNotification;
use App\Models\Admin\Ambulance;
use App\Models\Driver\Driver;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmergencyController extends Controller
{
    public function index()
    {
        $requests   = EmergencyRequest::with(['user.details', 'ambulance', 'driver'])
            ->whereNotIn('status', ['6', '7'])
            ->latest()
            ->paginate(20);
        $ambulances = Ambulance::where('status', '1')->get();
        $drivers    = Driver::where('status', '1')->get();
        return view('admin.pages.emergency', compact('requests', 'ambulances', 'drivers'));
    }

    public function pastRides(Request $request)
    {
        if ($request->filled('q')) {
            try {
                $decoded = json_decode(base64_decode((string) $request->q), true);
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            } catch (\Throwable $e) {}
        }

        $query = EmergencyRequest::with(['user.details', 'ambulance', 'driver'])
            ->whereIn('status', ['6', '7']);

        if ($request->filled('status') && in_array($request->status, ['6', '7'])) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('rreb_id',         'like', "%$s%")
                  ->orWhere('hospital_name',  'like', "%$s%")
                  ->orWhere('pickup_address', 'like', "%$s%")
                  ->orWhere('mobile_no',      'like', "%$s%");
            });
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }
        if ($request->filled('ambulance_id')) {
            $query->where('ambulance_id', $request->ambulance_id);
        }
        $dateFilter = $request->get('date_filter', 'all');
        if ($dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rides = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'completed' => EmergencyRequest::where('status', '6')->count(),
            'cancelled' => EmergencyRequest::where('status', '7')->count(),
        ];
        $allDrivers    = Driver::orderBy('name')->get(['id', 'name', 'phone']);
        $allAmbulances = Ambulance::orderBy('vehicle_number')->get(['id', 'vehicle_number', 'type']);

        return view('admin.pages.past_rides', compact('rides', 'stats', 'allDrivers', 'allAmbulances'));
    }

    public function show($id): JsonResponse
    {
        $req = EmergencyRequest::with(['user.details', 'ambulance', 'driver'])->findOrFail($id);

        $availableAmbulances = Ambulance::where('status', '1')
            ->get(['id', 'vehicle_number', 'type'])
            ->map(fn($a) => ['id' => $a->id, 'label' => $a->vehicle_number . ' — ' . $a->type]);

        $availableDrivers = Driver::where('status', '1')
            ->get(['id', 'name', 'phone'])
            ->map(fn($d) => ['id' => $d->id, 'label' => $d->name . ' — ' . $d->phone]);

        return response()->json([
            'success'              => true,
            'request'              => $req,
            'available_ambulances' => $availableAmbulances,
            'available_drivers'    => $availableDrivers,
        ]);
    }

    public function dispatch(Request $request, $id): JsonResponse
    {
        $request->validate([
            'ambulance_id' => 'required|exists:ambulances,id',
            'driver_id'    => 'required|exists:drivers,id',
            'notes'        => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $req = EmergencyRequest::findOrFail($id);

            if (!in_array($req->status, ['1'])) {
                return response()->json(['success' => false, 'message' => 'Only Pending requests can be dispatched.'], 422);
            }

            $req->ambulance_id = $request->ambulance_id;
            $req->driver_id    = $request->driver_id;
            $req->notes        = $request->notes;
            $req->status       = '8';
            $req->save();

            Ambulance::where('id', $request->ambulance_id)->update(['status' => '2']);
            Driver::where('id', $request->driver_id)->update(['status' => '3']);

            $dispatchedAmbulance = Ambulance::find($request->ambulance_id);
            $dispatchedDriver    = Driver::find($request->driver_id);

            $admin = Auth::guard('admin')->user();
            logHistory($admin->username, $request->ip(), "Sent dispatch request for request #{$req->id} — ambulance ID: {$request->ambulance_id}, driver ID: {$request->driver_id}");

            DriverNotification::create([
                'driver_id'            => $request->driver_id,
                'emergency_request_id' => $req->id,
                'type'                 => 'assignment',
                'title'                => 'New Dispatch Request',
                'body'                 => ($req->type === '1' ? 'Emergency' : 'Non-Emergency') . ' request ' . $req->rreb_id . ' — please Accept or Reject.',
                'is_read'              => false,
            ]);

            DB::commit();

            $loaded = $req->load(['ambulance', 'driver']);

            try {
                broadcast(new EmergencyStatusUpdated($loaded));
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new DispatchRequestSent($loaded));
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new PendingRideTaken((int) $req->id));
            } catch (\Throwable $ignored) {}

            if ($dispatchedDriver) {
                try {
                    broadcast(new DriverAvailabilityUpdated($dispatchedDriver));
                } catch (\Throwable $ignored) {}
            }

            if ($dispatchedAmbulance) {
                try {
                    broadcast(new AmbulanceAvailabilityUpdated($dispatchedAmbulance));
                } catch (\Throwable $ignored) {}
            }

            return response()->json([
                'success' => true,
                'message' => 'Dispatch request sent. Awaiting driver acceptance.',
                'request' => $loaded,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function nearbyDrivers(Request $request): JsonResponse
    {
        $lat    = (float) $request->get('lat', 0);
        $lng    = (float) $request->get('lng', 0);
        $radius = (float) $request->get('radius', 30);

        $drivers = Driver::where('status', '1')
            ->whereNotNull('lat')->whereNotNull('lng')
            ->where('lat', '!=', 0)->where('lng', '!=', 0)
            ->get(['id', 'name', 'phone', 'lat', 'lng']);

        $result = $drivers->map(function ($d) use ($lat, $lng) {
            $d->distance_km = round($this->haversine($lat, $lng, (float)$d->lat, (float)$d->lng), 2);
            return $d;
        })
        ->filter(fn($d) => $d->distance_km <= $radius)
        ->sortBy('distance_km')
        ->values();

        return response()->json(['success' => true, 'drivers' => $result]);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function delete($id): JsonResponse
    {
        $req = EmergencyRequest::findOrFail($id);

        // Only active (non-completed/cancelled) requests count towards the badge
        $wasActive = !in_array($req->status, ['6', '7']);

        if ($req->ambulance_id) {
            Ambulance::where('id', $req->ambulance_id)->update(['status' => '1']);
            $amb = Ambulance::find($req->ambulance_id);
            if ($amb) {
                try { broadcast(new AmbulanceAvailabilityUpdated($amb)); } catch (\Throwable $ignored) {}
            }
        }
        if ($req->driver_id) {
            Driver::where('id', $req->driver_id)->update(['status' => '1']);
            $drv = Driver::find($req->driver_id);
            if ($drv) {
                try { broadcast(new DriverAvailabilityUpdated($drv)); } catch (\Throwable $ignored) {}
            }
        }
        $admin = Auth::guard('admin')->user();
        logHistory($admin->username, request()->ip(), "Deleted emergency request #{$req->id}");
        $req->delete();

        if ($wasActive) {
            try {
                broadcast(new \App\Events\EmergencyRequestDeleted((int) $id));
            } catch (\Throwable $ignored) {}
        }

        return response()->json(['success' => true, 'message' => 'Request deleted successfully.']);
    }
}
