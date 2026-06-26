<?php

namespace App\Http\Controllers\Driver;

use App\Events\RideChatMessageSent;
use App\Events\RideChatNotificationRead;
use App\Events\RideChatTyping;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Driver\DriverNotification;
use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use App\Models\RideChatNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DriverChatController extends Controller
{
    private function driver()
    {
        return Auth::guard('driver')->user();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function timeAgo($dt): string
    {
        return \Carbon\Carbon::parse($dt)->diffForHumans();
    }

    private function driverChatMessage(RideChatNotification $n): string
    {
        return match ($n->sender_type) {
            'admin' => 'Admin ' . $n->sender_name . ' sent you a message',
            'user'  => 'Patient ' . $n->sender_name . ' sent you a message',
            default => 'New message from ' . $n->sender_name,
        };
    }

    private function formatDriverChatNotif(RideChatNotification $n): array
    {
        return [
            'id'                   => 'chat_' . $n->id,
            'source'               => 'chat',
            'source_id'            => $n->id,
            'message'              => $this->driverChatMessage($n),
            'preview'              => $n->message_preview,
            'rreb_id'              => $n->rreb_id,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => '/driver/ride-chats?open=' . $n->emergency_request_id,
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    private function formatDriverAssignmentNotif(DriverNotification $n): array
    {
        return [
            'id'                   => 'assignment_' . $n->id,
            'source'               => 'assignment',
            'source_id'            => $n->id,
            'message'              => $n->title ?: 'New dispatch request',
            'preview'              => $n->body,
            'rreb_id'              => null,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => '/driver/requests',
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    // ── Existing methods ──────────────────────────────────────────────────────

    public function index()
    {
        $driver   = $this->driver();
        $requests = EmergencyRequest::with(['ambulance'])
            ->where('driver_id', $driver->id)
            ->whereHas('rideChatMessages')
            ->orderByRaw("CASE WHEN status IN ('3','4','5') THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'rreb_id'        => $r->rreb_id ?? '#' . $r->id,
                'status'         => $r->status,
                'chat_status'    => RideChatMessage::chatStatus($r->status),
                'pickup_address' => $r->pickup_address,
                'hospital_name'  => $r->hospital_name,
                'user_id'        => $r->user_id,
                'unread'         => RideChatMessage::where('emergency_request_id', $r->id)
                    ->where('is_read_driver', false)
                    ->where('sender_type', '!=', 'driver')
                    ->count(),
            ]);

        $unread = $requests->sum('unread');

        return view('driver.pages.ride_chats', compact('requests', 'unread'));
    }

    public function thread(Request $request, $requestId): JsonResponse
    {
        $driver = $this->driver();
        $req    = EmergencyRequest::where('id', $requestId)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        $messages = RideChatMessage::where('emergency_request_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id,
                'sender_type' => $m->sender_type,
                'sender_name' => $m->sender_name,
                'message'     => $m->message,
                'time'        => $m->created_at->format('d M Y, h:i A'),
            ]);

        RideChatMessage::where('emergency_request_id', $requestId)
            ->where('is_read_driver', false)
            ->where('sender_type', '!=', 'driver')
            ->update(['is_read_driver' => true]);

        $notifsCleared = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('is_read', false)
            ->count();

        if ($notifsCleared > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'driver')
                ->where('recipient_id', $driver->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('driver', $driver->id, $req->id, $req->rreb_id ?? '#' . $req->id));
            } catch (\Throwable $ignored) {}
        }

        return response()->json([
            'messages'                 => $messages,
            'status'                   => $req->status,
            'chat_status'              => RideChatMessage::chatStatus($req->status),
            'rreb_id'                  => $req->rreb_id ?? '#' . $req->id,
            'request_id'               => $req->id,
            'user_id'                  => $req->user_id,
            'ride_chat_notifs_cleared' => $notifsCleared,
        ]);
    }

    public function send(Request $request, $requestId): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $driver = $this->driver();
        $req    = EmergencyRequest::where('id', $requestId)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        if (!in_array($req->status, ['3', '4', '5'])) {
            return response()->json(['success' => false, 'message' => 'Chat is not available at this stage.'], 403);
        }

        $msg = RideChatMessage::create([
            'emergency_request_id' => $req->id,
            'sender_type'          => 'driver',
            'sender_id'            => $driver->id,
            'sender_name'          => $driver->name,
            'message'              => $request->message,
            'is_read_driver'       => true,
            'is_read_admin'        => false,
            'is_read_user'         => false,
        ]);

        $preview      = Str::limit($request->message, 100);
        $adminNotifId = null;
        $userNotifId  = null;

        $allAdmins = Admin::pluck('id');
        foreach ($allAdmins as $adminId) {
            $an = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'admin',
                'recipient_id'         => $adminId,
                'sender_name'          => $driver->name,
                'sender_type'          => 'driver',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            if (!$adminNotifId) $adminNotifId = $an->id;
        }

        if ($req->user_id) {
            $un = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'user',
                'recipient_id'         => $req->user_id,
                'sender_name'          => $driver->name,
                'sender_type'          => 'driver',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            $userNotifId = $un->id;
        }

        try {
            broadcast(new RideChatMessageSent($msg, $req, $driver->id, $req->user_id, $adminNotifId, null, $userNotifId));
        } catch (\Throwable $ignored) {}

        return response()->json([
            'success' => true,
            'message' => [
                'id'          => $msg->id,
                'sender_type' => $msg->sender_type,
                'sender_name' => $msg->sender_name,
                'message'     => $msg->message,
                'time'        => $msg->created_at->format('d M Y, h:i A'),
            ],
        ]);
    }

    public function typing(Request $request, $requestId): JsonResponse
    {
        $driver = $this->driver();
        $req    = EmergencyRequest::where('id', $requestId)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        try {
            broadcast(new RideChatTyping($req->id, 'driver', $driver->name, $driver->id, $req->user_id));
        } catch (\Throwable $ignored) {}

        return response()->json(['ok' => true]);
    }

    public function unreadCount(): JsonResponse
    {
        $driver     = $this->driver();
        $chatUnread = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('is_read', false)
            ->count();
        $assignUnread = DriverNotification::where('driver_id', $driver->id)->where('is_read', false)->count();

        return response()->json(['unread' => $chatUnread + $assignUnread]);
    }

    // ── Notification bell (last 10, combined chat + assignments) ─────────────
    public function rideChatNotifBell(): JsonResponse
    {
        $driver = $this->driver();

        $chatNotifs = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatDriverChatNotif($n));

        $assignNotifs = DriverNotification::where('driver_id', $driver->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatDriverAssignmentNotif($n));

        $combined = $chatNotifs->concat($assignNotifs)
            ->sortByDesc('ts')
            ->values()
            ->take(10);

        $chatUnread   = RideChatNotification::where('recipient_type', 'driver')->where('recipient_id', $driver->id)->where('is_read', false)->count();
        $assignUnread = DriverNotification::where('driver_id', $driver->id)->where('is_read', false)->count();

        return response()->json([
            'notifications' => $combined,
            'unread'        => $chatUnread + $assignUnread,
        ]);
    }

    // ── Mark single chat notification read ────────────────────────────────────
    public function markNotifRead(Request $request, $id): JsonResponse
    {
        $driver = $this->driver();
        $notif  = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->findOrFail($id);

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }

        return response()->json(['is_read' => true]);
    }

    // ── Mark all unread assignment notifications for a given request read ────
    // Called when the driver is already viewing the requests page and a new
    // dispatch notification arrives — auto-marks without the driver needing to
    // open the notification bell.
    public function markAssignmentNotifByRequestRead(Request $request, $requestId): JsonResponse
    {
        $driver = $this->driver();

        $count = DriverNotification::where('driver_id', $driver->id)
            ->where('emergency_request_id', $requestId)
            ->where('is_read', false)
            ->count();

        if ($count > 0) {
            DriverNotification::where('driver_id', $driver->id)
                ->where('emergency_request_id', $requestId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json(['success' => true, 'marked' => $count]);
    }

    // ── Mark single driver/assignment notification read ───────────────────────
    public function markAssignmentNotifRead(Request $request, $id): JsonResponse
    {
        $driver = $this->driver();
        $notif  = DriverNotification::where('driver_id', $driver->id)->findOrFail($id);
        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }
        return response()->json(['is_read' => true]);
    }

    // ── Mark all chat notifs for a ride read ──────────────────────────────────
    public function markNotifsReadByRequest(Request $request, $requestId): JsonResponse
    {
        $driver = $this->driver();
        $req    = EmergencyRequest::findOrFail($requestId);

        $count = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('is_read', false)
            ->count();

        if ($count > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'driver')
                ->where('recipient_id', $driver->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('driver', $driver->id, $req->id, $req->rreb_id ?? '#' . $req->id));
            } catch (\Throwable $ignored) {}
        }

        return response()->json(['cleared' => $count]);
    }

    // ── Mark ALL notifications read for this driver ───────────────────────────
    public function markAllNotifsRead(): JsonResponse
    {
        $driver = $this->driver();

        RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        DriverNotification::where('driver_id', $driver->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // ── Notification history page ─────────────────────────────────────────────
    public function notifHistory()
    {
        $driver = $this->driver();

        $chatNotifs = RideChatNotification::where('recipient_type', 'driver')
            ->where('recipient_id', $driver->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatDriverChatNotif($n));

        $assignNotifs = DriverNotification::where('driver_id', $driver->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatDriverAssignmentNotif($n));

        $allNotifs = $chatNotifs->concat($assignNotifs)
            ->sortByDesc('ts')
            ->values();

        if (request()->ajax()) {
            return response()->json(['notifications' => $allNotifs->take(50)->values()]);
        }

        $perPage   = 20;
        $page      = max(1, (int) request()->get('page', 1));
        $total     = $allNotifs->count();
        $items     = $allNotifs->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
        ]);

        $chatUnread   = RideChatNotification::where('recipient_type', 'driver')->where('recipient_id', $driver->id)->where('is_read', false)->count();
        $assignUnread = DriverNotification::where('driver_id', $driver->id)->where('is_read', false)->count();

        return view('driver.pages.notif_history', [
            'notifications' => $paginator,
            'unread'        => $chatUnread + $assignUnread,
        ]);
    }
}
