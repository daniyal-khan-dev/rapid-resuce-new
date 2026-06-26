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
use App\Models\Driver\DriverNotification;
use App\Models\EmergencyRequest;
use App\Models\RideChatNotification;
use App\Models\RideStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


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

            $userRideNotifId = null;
            if ($req->user_id) {
                $uNotif = RideStatusNotification::create([
                    'emergency_request_id' => $req->id,
                    'rreb_id'              => $req->rreb_id,
                    'status'               => '2',
                    'status_label'         => 'Dispatched',
                    'driver_name'          => $driver->name,
                    'recipient_type'       => 'user',
                    'recipient_id'         => $req->user_id,
                    'is_read'              => false,
                ]);
                $userRideNotifId = $uNotif->id;
            }

            DriverNotification::where('driver_id', $driver->id)
                ->where('emergency_request_id', $req->id)
                ->latest()
                ->first()
                ?->update(['is_read' => true]);

            DB::commit();

            $loaded = $req->load(['ambulance', 'driver']);

            try {
                broadcast(new EmergencyDispatched($loaded, 0));
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new RequestStatusUpdated($loaded, $driver->name, $driver->lat, $driver->lng, null, $userRideNotifId));
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

            // Notify admin in real time that the driver rejected the dispatch.
            // Without this record the bell endpoint returns the same unread count
            // and the optimistic +1 in admNotifBell.onNewStatusNotif() reverts.
            $adminNotifId = null;
            try {
                $aN = RideStatusNotification::create([
                    'emergency_request_id' => $req->id,
                    'rreb_id'              => $req->rreb_id,
                    'status'               => '1',
                    'status_label'         => 'Rejected',
                    'driver_name'          => $driver->name,
                    'recipient_type'       => 'admin',
                    'recipient_id'         => null,
                    'is_read'              => false,
                ]);
                $adminNotifId = $aN->id;
            } catch (\Throwable $ignored) {}

            try {
                broadcast(new RequestStatusUpdated($fresh, $driver->name, null, null, $adminNotifId));
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

            $adminNotifId = null;
            $userNotifId  = null;

            if ($req->user_id && in_array($newStatus, ['3', '4', '5', '6'])) {
                $uNotif = RideStatusNotification::create([
                    'emergency_request_id' => $req->id,
                    'rreb_id'              => $req->rreb_id,
                    'status'               => $newStatus,
                    'status_label'         => self::$statusLabels[$newStatus] ?? $newStatus,
                    'driver_name'          => $driver->name,
                    'recipient_type'       => 'user',
                    'recipient_id'         => $req->user_id,
                    'is_read'              => false,
                ]);
                $userNotifId = $uNotif->id;
            }

            if (in_array($newStatus, ['3', '6', '7'])) {
                $aNotif = RideStatusNotification::create([
                    'emergency_request_id' => $req->id,
                    'rreb_id'              => $req->rreb_id,
                    'status'               => $newStatus,
                    'status_label'         => self::$statusLabels[$newStatus] ?? $newStatus,
                    'driver_name'          => $driver->name,
                    'recipient_type'       => 'admin',
                    'recipient_id'         => null,
                    'is_read'              => false,
                ]);
                $adminNotifId = $aNotif->id;
            }

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
                broadcast(new RequestStatusUpdated($req, $driver->name, $driver->lat, $driver->lng, $adminNotifId, $userNotifId));
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

    public function bellNotifications(): JsonResponse
    {
        $driver = $this->driver();

        $notifs = DriverNotification::where('driver_id', $driver->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => [
                'id'                   => $n->id,
                'type'                 => $n->type,
                'title'                => $n->title,
                'body'                 => $n->body,
                'emergency_request_id' => $n->emergency_request_id,
                'is_read'              => (bool) $n->is_read,
                'time'                 => $n->created_at->format('d M Y, h:i A'),
                'date_short'           => $n->created_at->format('d M'),
                'ts'                   => $n->created_at->timestamp,
            ]);

        $rideChatNotifs = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => [
                'id'                   => $n->id,
                'type'                 => 'ride_chat',
                'title'                => 'Chat: ' . $n->sender_name . ($n->rreb_id ? ' — ' . $n->rreb_id : ''),
                'body'                 => $n->message_preview,
                'emergency_request_id' => $n->emergency_request_id,
                'rreb_id'              => $n->rreb_id,
                'sender_name'          => $n->sender_name,
                'sender_type'          => $n->sender_type,
                'preview'              => $n->message_preview,
                'is_read'              => (bool) $n->is_read,
                'time'                 => $n->created_at->format('d M Y, h:i A'),
                'date_short'           => $n->created_at->format('d M'),
                'ts'                   => $n->created_at->timestamp,
            ]);

        $combined = $notifs->merge($rideChatNotifs)->sortByDesc('ts')->take(10)->values();

        $unread = DriverNotification::where('driver_id', $driver->id)
            ->where('is_read', false)
            ->count()
            + RideChatNotification::where('recipient_type', 'driver')
                ->where('recipient_id', $driver->id)
                ->where('is_read', false)
                ->count();

        return response()->json(['notifications' => $combined, 'unread' => $unread]);
    }

    public function notificationsData(): JsonResponse
    {
        $driver = $this->driver();

        $driverNotifs = DriverNotification::where('driver_id', $driver->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => [
                'id'                   => $n->id,
                'source'               => 'driver_notif',
                'type'                 => $n->type,
                'title'                => $n->title,
                'body'                 => $n->body,
                'preview'              => Str::limit($n->body ?? '', 80),
                'emergency_request_id' => $n->emergency_request_id,
                'read'                 => (bool) $n->is_read,
                'time'                 => $n->created_at->format('d M Y, h:i A'),
                'date_short'           => $n->created_at->format('d M'),
                'ts'                   => $n->created_at->timestamp,
            ]);

        $rideChatNotifs = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => [
                'id'                   => $n->id,
                'source'               => 'ride_chat',
                'type'                 => 'ride_chat',
                'title'                => 'Chat: ' . $n->sender_name . ($n->rreb_id ? ' — ' . $n->rreb_id : ''),
                'body'                 => $n->message_preview,
                'preview'              => Str::limit($n->message_preview ?? '', 80),
                'emergency_request_id' => $n->emergency_request_id,
                'read'                 => (bool) $n->is_read,
                'time'                 => $n->created_at->format('d M Y, h:i A'),
                'date_short'           => $n->created_at->format('d M'),
                'ts'                   => $n->created_at->timestamp,
            ]);

        $combined = $driverNotifs->merge($rideChatNotifs)->sortByDesc('ts')->values();

        $stats = [
            'total'      => $combined->count(),
            'assignment' => $driverNotifs->where('type', 'assignment')->count(),
            'cancelled'  => $driverNotifs->where('type', 'cancelled')->count(),
            'ride_chat'  => $rideChatNotifs->count(),
            'unread'     => $combined->where('read', false)->count(),
        ];

        return response()->json(['notifications' => $combined, 'stats' => $stats]);
    }

    public function markAllRead(): JsonResponse
    {
        $driver = $this->driver();
        DriverNotification::where('driver_id', $driver->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $driver = $this->driver();
        $notif  = DriverNotification::where('driver_id', $driver->id)->findOrFail($id);

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);

            try {
                broadcast(new \App\Events\DriverNotificationRead(
                    $driver->id,
                    $notif->id,
                    $notif->emergency_request_id
                ));
            } catch (\Throwable $ignored) {}
        }

        return response()->json([
            'is_read'              => true,
            'notif_id'             => $notif->id,
            'emergency_request_id' => $notif->emergency_request_id,
        ]);
    }

    public function markReadByRequest(Request $request, $requestId): JsonResponse
    {
        $driver = $this->driver();

        $notif = DriverNotification::where('driver_id', $driver->id)
            ->where('emergency_request_id', $requestId)
            ->where('is_read', false)
            ->first();

        if (!$notif) {
            return response()->json(['success' => true, 'already_read' => true]);
        }

        $notif->update(['is_read' => true]);

        try {
            broadcast(new \App\Events\DriverNotificationRead(
                $driver->id,
                $notif->id,
                $notif->emergency_request_id
            ));
        } catch (\Throwable $ignored) {}

        return response()->json([
            'success'              => true,
            'notif_id'             => $notif->id,
            'emergency_request_id' => $notif->emergency_request_id,
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
            '2'     => ['3'],        // Dispatched → En Route only; cancel not allowed after acceptance
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
            'mobile_no'      => $req->mobile_no,
            'pickup_address' => $req->pickup_address,
            'pickup_lat'     => $req->pickup_lat,
            'pickup_lng'     => $req->pickup_lng,
            'hospital_name'  => $req->hospital_name,
            'hospital_lat'   => $req->hospital_lat,
            'hospital_lng'   => $req->hospital_lng,
            'accepted_lat'   => $req->accepted_lat ? (float) $req->accepted_lat : null,
            'accepted_lng'   => $req->accepted_lng ? (float) $req->accepted_lng : null,
            'notes'          => $req->notes,
            'dispatched_at'  => $req->dispatched_at?->format('d M Y, h:i A'),
            'ambulance_no'   => $req->ambulance?->vehicle_number ?? '—',
            'ambulance_type' => $req->ambulance?->type ?? '—',
            'allowed_next'   => $this->allowedTransitions($req->status),
        ];
    }
}
