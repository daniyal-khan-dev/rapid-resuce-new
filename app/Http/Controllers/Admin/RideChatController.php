<?php

namespace App\Http\Controllers\Admin;

use App\Events\RideChatMessageSent;
use App\Events\RideChatNotificationRead;
use App\Events\RideChatTyping;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use App\Models\RideChatNotification;
use App\Models\RideStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RideChatController extends Controller
{
    private function admin()
    {
        return Auth::guard('admin')->user();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function timeAgo($dt): string
    {
        return \Carbon\Carbon::parse($dt)->diffForHumans();
    }

    private function adminChatMessage(RideChatNotification $n): string
    {
        return match ($n->sender_type) {
            'driver' => 'Driver ' . $n->sender_name . ' sent a message',
            'user'   => 'User ' . $n->sender_name . ' sent a message',
            default  => 'New message from ' . $n->sender_name,
        };
    }

    private function adminStatusMessage(RideStatusNotification $n): string
    {
        $rreb   = $n->rreb_id ? ' (' . $n->rreb_id . ')' : '';
        $driver = $n->driver_name ?: 'Driver';
        return match ($n->status) {
            '1'     => $driver . ' rejected the dispatch' . $rreb,
            '2'     => 'Ride ' . ($n->rreb_id ?: '') . ' dispatched by ' . $driver,
            '3'     => $driver . ' is now En Route' . $rreb,
            '4'     => $driver . ' has arrived on scene' . $rreb,
            '5'     => 'Transport started by ' . $driver . $rreb,
            '6'     => 'Ride ' . ($n->rreb_id ?: '') . ' completed',
            '7'     => 'Ride ' . ($n->rreb_id ?: '') . ' cancelled',
            default => $n->status_label . $rreb,
        };
    }

    private function formatAdminChatNotif(RideChatNotification $n): array
    {
        return [
            'id'                   => 'chat_' . $n->id,
            'source'               => 'chat',
            'source_id'            => $n->id,
            'message'              => $this->adminChatMessage($n),
            'preview'              => $n->message_preview,
            'rreb_id'              => $n->rreb_id,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => '/admin/ride-chats?open=' . $n->emergency_request_id,
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    private function formatAdminStatusNotif(RideStatusNotification $n): array
    {
        // Chat-related statuses stay in the Ride Chats panel; all driver status
        // changes (dispatched, en-route, arrived, transport, completed, rejected,
        // cancelled) belong to the Requests/Emergency management pages.
        $actionUrl = in_array($n->status, ['6', '7'])
            ? route('admin.emergency.past-rides')   // completed / cancelled → Past Rides
            : route('admin.emergency.grid');         // all others → Active Requests

        return [
            'id'                   => 'status_' . $n->id,
            'source'               => 'status',
            'source_id'            => $n->id,
            'message'              => $this->adminStatusMessage($n),
            'preview'              => null,
            'rreb_id'              => $n->rreb_id,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => $actionUrl,
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    // ── Existing methods ──────────────────────────────────────────────────────

    public function index()
    {
        $admin    = $this->admin();
        $requests = EmergencyRequest::with(['driver', 'ambulance'])
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
                'driver_name'    => $r->driver?->name ?? '—',
                'driver_id'      => $r->driver_id,
                'user_id'        => $r->user_id,
                'unread'         => RideChatNotification::where('emergency_request_id', $r->id)
                    ->where('recipient_type', 'admin')
                    ->where('recipient_id', $admin->id)
                    ->where('is_read', false)
                    ->count(),
            ]);

        $unread = $requests->sum('unread');

        return view('admin.pages.ride_chats', compact('requests', 'unread'));
    }

    public function thread(Request $request, $requestId): JsonResponse
    {
        $admin = $this->admin();
        $req   = EmergencyRequest::with(['driver', 'ambulance'])->findOrFail($requestId);

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
            ->where('is_read_admin', false)
            ->where('sender_type', '!=', 'admin')
            ->update(['is_read_admin' => true]);

        $notifsCleared = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->where('is_read', false)
            ->count();

        if ($notifsCleared > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'admin')
                ->where('recipient_id', $admin->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('admin', $admin->id, $req->id, $req->rreb_id ?? '#' . $req->id));
            } catch (\Throwable $ignored) {}
        }

        return response()->json([
            'messages'                 => $messages,
            'status'                   => $req->status,
            'chat_status'              => RideChatMessage::chatStatus($req->status),
            'rreb_id'                  => $req->rreb_id ?? '#' . $req->id,
            'request_id'               => $req->id,
            'driver_id'                => $req->driver_id,
            'user_id'                  => $req->user_id,
            'driver_name'              => $req->driver?->name ?? '—',
            'ride_chat_notifs_cleared' => $notifsCleared,
        ]);
    }

    public function send(Request $request, $requestId): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $admin = $this->admin();
        $req   = EmergencyRequest::findOrFail($requestId);

        if (in_array($req->status, ['6', '7'])) {
            return response()->json(['success' => false, 'message' => 'This ride has ended.'], 403);
        }

        $msg = RideChatMessage::create([
            'emergency_request_id' => $req->id,
            'sender_type'          => 'admin',
            'sender_id'            => $admin->id,
            'sender_name'          => $admin->name,
            'message'              => $request->message,
            'is_read_admin'        => true,
            'is_read_driver'       => false,
            'is_read_user'         => false,
        ]);

        $preview       = Str::limit($request->message, 100);
        $driverNotifId = null;
        $userNotifId   = null;
        $adminNotifId  = null;

        if ($req->driver_id) {
            $dn = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'driver',
                'recipient_id'         => $req->driver_id,
                'sender_name'          => $admin->name,
                'sender_type'          => 'admin',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            $driverNotifId = $dn->id;
        }

        if ($req->user_id) {
            $un = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'user',
                'recipient_id'         => $req->user_id,
                'sender_name'          => $admin->name,
                'sender_type'          => 'admin',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            $userNotifId = $un->id;
        }

        $allAdmins = Admin::pluck('id');
        foreach ($allAdmins as $adminId) {
            $an = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'admin',
                'recipient_id'         => $adminId,
                'sender_name'          => $admin->name,
                'sender_type'          => 'admin',
                'message_preview'      => $preview,
                'is_read'              => ($adminId === $admin->id),
            ]);
            if (!$adminNotifId) $adminNotifId = $an->id;
        }

        try {
            broadcast(new RideChatMessageSent($msg, $req, $req->driver_id, $req->user_id, $adminNotifId, $driverNotifId, $userNotifId));
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
        $admin = $this->admin();
        $req   = EmergencyRequest::findOrFail($requestId);

        try {
            broadcast(new RideChatTyping($req->id, 'admin', $admin->name, $req->driver_id, $req->user_id));
        } catch (\Throwable $ignored) {}

        return response()->json(['ok' => true]);
    }

    public function unreadCount(): JsonResponse
    {
        $admin        = $this->admin();
        $chatUnread   = RideChatNotification::where('recipient_type', 'admin')->where('recipient_id', $admin->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'admin')->where('is_read', false)->count();

        return response()->json(['unread' => $chatUnread + $statusUnread]);
    }

    // ── Notification bell (last 10, combined chat + status) ───────────────────
    public function rideChatNotifBell(): JsonResponse
    {
        $admin = $this->admin();

        $chatNotifs = RideChatNotification::where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatAdminChatNotif($n));

        $statusNotifs = RideStatusNotification::where('recipient_type', 'admin')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatAdminStatusNotif($n));

        $combined = $chatNotifs->concat($statusNotifs)
            ->sortByDesc('ts')
            ->values()
            ->take(10);

        $chatUnread   = RideChatNotification::where('recipient_type', 'admin')->where('recipient_id', $admin->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'admin')->where('is_read', false)->count();

        return response()->json([
            'notifications' => $combined,
            'unread'        => $chatUnread + $statusUnread,
        ]);
    }

    // ── Mark single chat notification read ────────────────────────────────────
    public function markNotifRead(Request $request, $id): JsonResponse
    {
        $admin = $this->admin();
        $notif = RideChatNotification::where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->findOrFail($id);

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }

        return response()->json(['is_read' => true]);
    }

    // ── Mark single ride-status notification read ─────────────────────────────
    public function markStatusNotifRead(Request $request, $id): JsonResponse
    {
        $notif = RideStatusNotification::where('recipient_type', 'admin')->findOrFail($id);
        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }
        return response()->json(['is_read' => true]);
    }

    // ── Mark all chat notifs for a ride read (THIS admin) ────────────────────
    public function markNotifsReadByRequest(Request $request, $requestId): JsonResponse
    {
        $admin = $this->admin();
        $req   = EmergencyRequest::findOrFail($requestId);

        $count = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->where('is_read', false)
            ->count();

        if ($count > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'admin')
                ->where('recipient_id', $admin->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('admin', $admin->id, $req->id, $req->rreb_id ?? '#' . $req->id));
            } catch (\Throwable $ignored) {}
        }

        return response()->json(['cleared' => $count]);
    }

    // ── Mark ALL notifications read for this admin ────────────────────────────
    public function markAllNotifsRead(): JsonResponse
    {
        $admin = $this->admin();

        RideChatNotification::where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        RideStatusNotification::where('recipient_type', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // ── Notification history page (all notifications, paginated) ─────────────
    public function notifHistory()
    {
        $admin = $this->admin();

        $chatNotifs = RideChatNotification::where('recipient_type', 'admin')
            ->where('recipient_id', $admin->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatAdminChatNotif($n));

        $statusNotifs = RideStatusNotification::where('recipient_type', 'admin')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatAdminStatusNotif($n));

        $allNotifs = $chatNotifs->concat($statusNotifs)
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

        $chatUnread   = RideChatNotification::where('recipient_type', 'admin')->where('recipient_id', $admin->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'admin')->where('is_read', false)->count();

        return view('admin.pages.notif_history', [
            'notifications' => $paginator,
            'unread'        => $chatUnread + $statusUnread,
        ]);
    }
}
