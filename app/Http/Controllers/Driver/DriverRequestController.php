<?php

namespace App\Http\Controllers\Driver;

use App\Events\AmbulanceAvailabilityUpdated;
use App\Events\DriverAvailabilityUpdated;
use App\Events\EmergencyDispatched;
use App\Events\PendingRideAvailable;
use App\Events\RequestStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\RideCompletionMail;
use App\Models\Admin\Ambulance;
use App\Models\Driver\Driver;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class DriverRequestController extends Controller
{
    private function driver(): Driver
    {
        return Auth::guard('driver')->user();
    }

    private static array $statusLabels = [
        '1' => 'Pending',
        '2' => 'Dispatched',
        '3' => 'En Route',
        '4' => 'Arrived',
        '5' => 'Transporting',
        '6' => 'Completed',
        '7' => 'Cancelled',
        '8' => 'Awaiting Acceptance',
    ];

    public function active(): JsonResponse
    {
        $driver = $this->driver();

        $req = EmergencyRequest::with(['ambulance'])
            ->where('driver_id', $driver->id)
            ->whereNotIn('status', ['6', '7'])
            ->latest('updated_at')
            ->first();

        if (!$req) {
            return response()->json(['active' => null]);
        }

        return response()->json(['active' => $this->formatRequest($req)]);
    }

    public function acceptDispatch(Request $request, $id): JsonResponse
    {
        $driver = $this->driver();

        DB::beginTransaction();
        try {
            $req = EmergencyRequest::where('id', $id)
                ->where('driver_id', $driver->id)
                ->where('status', '8')
                ->firstOrFail();

            $req->status       = '2';
            $req->dispatched_at = now();
            $req->save();

            DB::commit();

            $loaded = $req->load(['ambulance', 'driver']);

            try {
                broadcast(new EmergencyDispatched($loaded, 0));
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new RequestStatusUpdated($loaded, $driver->name, $driver->lat, $driver->lng));
            } catch (\Throwable $ignored) {}

            return response()->json([
                'success'      => true,
                'message'      => 'Request accepted. You are now dispatched.',
                'status'       => '2',
                'status_label' => 'Dispatched',
                'request'      => $this->formatRequest($req->fresh(['ambulance'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function rejectDispatch(Request $request, $id): JsonResponse
    {
        $driver = $this->driver();

        DB::beginTransaction();
        try {
            $req = EmergencyRequest::where('id', $id)
                ->where('driver_id', $driver->id)
                ->where('status', '8')
                ->firstOrFail();

            $ambulanceId = $req->ambulance_id;

            $req->status       = '1';
            $req->driver_id    = null;
            $req->ambulance_id = null;
            $req->notes        = null;
            $req->save();

            if ($ambulanceId) {
                Ambulance::where('id', $ambulanceId)->update(['status' => '1']);
            }
            Driver::where('id', $driver->id)->update(['status' => '1']);

            $freedAmbulance = $ambulanceId ? Ambulance::find($ambulanceId) : null;
            $freedDriver    = Driver::find($driver->id);

            DB::commit();

            $fresh = $req->fresh();

            try {
                broadcast(new RequestStatusUpdated($fresh, $driver->name, null, null));
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new PendingRideAvailable($fresh));
            } catch (\Throwable $ignored) {}

            if ($freedDriver) {
                try {
                    broadcast(new DriverAvailabilityUpdated($freedDriver));
                } catch (\Throwable $ignored) {}
            }

            if ($freedAmbulance) {
                try {
                    broadcast(new AmbulanceAvailabilityUpdated($freedAmbulance));
                } catch (\Throwable $ignored) {}
            }

            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:3,4,5,6,7',
        ]);

        $driver   = $this->driver();
        $newStatus = (string) $request->status;

        DB::beginTransaction();
        try {
            $req = EmergencyRequest::where('id', $id)
                ->where('driver_id', $driver->id)
                ->whereNotIn('status', ['6', '7'])
                ->firstOrFail();

            if ($newStatus === '7' && in_array($req->status, ['2', '3', '4', '5', '6'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ride can no longer be cancelled after it has been accepted.',
                ], 422);
            }

            $allowed = $this->allowedTransitions($req->status);
            if (!in_array($newStatus, $allowed)) {
                return response()->json(['success' => false, 'message' => 'Invalid status transition.'], 422);
            }

            $req->status = $newStatus;

            if ($newStatus === '3' && !$req->accepted_lat && $driver->lat) {
                $req->accepted_lat = $driver->lat;
                $req->accepted_lng = $driver->lng;
            }

            $freesResources = false;
            if ($newStatus === '6') {
                $req->completed_at = now();
                Ambulance::where('id', $req->ambulance_id)->update(['status' => '1']);
                Driver::where('id', $driver->id)->update(['status' => '1']);
                $freesResources = true;
            }

            if ($newStatus === '7') {
                Ambulance::where('id', $req->ambulance_id)->update(['status' => '1']);
                Driver::where('id', $driver->id)->update(['status' => '1']);
                $freesResources = true;
            }

            $freedAmbulanceId = $freesResources ? $req->ambulance_id : null;
            $freedDriverId    = $freesResources ? $driver->id : null;

            $req->save();

            DB::commit();

            if ($newStatus === '6') {
                try {
                    $freshForMail = $req->fresh(['driver', 'ambulance']);
                    Mail::to($freshForMail->email)->send(new RideCompletionMail($freshForMail));
                } catch (\Throwable $e) {
                    Log::error('Ride completion email failed', [
                        'rreb_id' => $req->rreb_id,
                        'email'   => $req->email,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            try {
                broadcast(new RequestStatusUpdated($req, $driver->name, $driver->lat, $driver->lng));
            } catch (\Throwable $ignored) {}

            if ($freedDriverId) {
                $freedDriver = Driver::find($freedDriverId);
                if ($freedDriver) {
                    try {
                        broadcast(new DriverAvailabilityUpdated($freedDriver));
                    } catch (\Throwable $ignored) {}
                }
            }

            if ($freedAmbulanceId) {
                $freedAmbulance = Ambulance::find($freedAmbulanceId);
                if ($freedAmbulance) {
                    try {
                        broadcast(new AmbulanceAvailabilityUpdated($freedAmbulance));
                    } catch (\Throwable $ignored) {}
                }
            }

            return response()->json([
                'success'      => true,
                'status'       => $newStatus,
                'status_label' => self::$statusLabels[$newStatus] ?? $newStatus,
                'request'      => $this->formatRequest($req->fresh(['ambulance'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        $request->validate(['status' => 'required|in:1,2']);

        $driver = $this->driver();

        if ($driver->status === '3') {
            return response()->json(['success' => false, 'message' => 'Cannot change availability while on an active assignment.'], 422);
        }

        $driver->status = (string) $request->status;
        $driver->save();

        try {
            broadcast(new DriverAvailabilityUpdated($driver));
        } catch (\Throwable $ignored) {}

        return response()->json([
            'success'      => true,
            'status'       => $driver->status,
            'status_label' => ['1' => 'Online', '2' => 'Offline'][$driver->status] ?? 'Unknown',
        ]);
    }

    public function stats(): JsonResponse
    {
        $driver = $this->driver();

        $total     = EmergencyRequest::where('driver_id', $driver->id)->count();
        $completed = EmergencyRequest::where('driver_id', $driver->id)->where('status', '6')->count();
        $cancelled = EmergencyRequest::where('driver_id', $driver->id)->where('status', '7')->count();
        $active    = EmergencyRequest::where('driver_id', $driver->id)->whereNotIn('status', ['6', '7'])->where('status', '!=', '1')->count();
        $pending   = EmergencyRequest::where('driver_id', $driver->id)->where('status', '2')->count();
        $today     = EmergencyRequest::where('driver_id', $driver->id)->whereDate('created_at', today())->count();

        return response()->json(compact('total', 'completed', 'cancelled', 'active', 'pending', 'today'));
    }

    public function pendingNearby(): JsonResponse
    {
        $rides = EmergencyRequest::where('status', '1')
            ->whereNull('driver_id')
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'rides' => $rides->map(fn($r) => [
                'request_id'     => $r->id,
                'rreb_id'        => $r->rreb_id,
                'type'           => $r->type,
                'type_label'     => $r->type === '1' ? 'Emergency' : 'Non-Emergency',
                'pickup_address' => $r->pickup_address,
                'pickup_lat'     => (float) $r->pickup_lat,
                'pickup_lng'     => (float) $r->pickup_lng,
                'hospital_name'  => $r->hospital_name,
                'mobile_no'      => $r->mobile_no,
                'notes'          => $r->notes,
                'created_at'     => $r->created_at?->format('d M Y, h:i A'),
            ])->values(),
        ]);
    }

    private function allowedTransitions(string $current): array
    {
        return match ($current) {
            '2'     => ['3'],
            '3'     => ['4'],
            '4'     => ['5'],
            '5'     => ['6'],
            default => [],
        };
    }

    private function formatRequest(EmergencyRequest $req): array
    {
        return [
            'id'             => $req->id,
            'rreb_id'        => $req->rreb_id,
            'type'           => $req->type,
            'type_label'     => $req->type === '1' ? 'Emergency' : 'Non-Emergency',
            'status'         => $req->status,
            'status_label'   => self::$statusLabels[$req->status] ?? $req->status,
            'pickup_address' => $req->pickup_address,
            'pickup_lat'     => $req->pickup_lat  ? (float) $req->pickup_lat  : null,
            'pickup_lng'     => $req->pickup_lng  ? (float) $req->pickup_lng  : null,
            'hospital_name'  => $req->hospital_name,
            'hospital_lat'   => $req->hospital_lat ? (float) $req->hospital_lat : null,
            'hospital_lng'   => $req->hospital_lng ? (float) $req->hospital_lng : null,
            'mobile_no'      => $req->mobile_no,
            'notes'          => $req->notes,
            'ambulance_no'   => $req->ambulance?->vehicle_number ?? null,
            'ambulance_type' => $req->ambulance?->type ?? null,
            'dispatched_at'  => $req->dispatched_at?->format('d M Y, h:i A'),
            'completed_at'   => $req->completed_at?->format('d M Y, h:i A'),
            'created_at'     => $req->created_at?->format('d M Y, h:i A'),
        ];
    }
}
