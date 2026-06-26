<?php

namespace App\Http\Controllers\User;

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
    private function user()
    {
        return Auth::guard('users')->user();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function timeAgo($dt): string
    {
        return \Carbon\Carbon::parse($dt)->diffForHumans();
    }

    private function userChatMessage(RideChatNotification $n): string
    {
        return match ($n->sender_type) {
            'admin'  => 'Admin ' . $n->sender_name . ' replied to your ride chat',
            'driver' => 'Driver ' . $n->sender_name . ' sent you a message',
            default  => 'New message from ' . $n->sender_name,
        };
    }

    private function userStatusMessage(RideStatusNotification $n): string
    {
        $driver = $n->driver_name ?: 'Your driver';
        return match ($n->status) {
            '2'     => 'Your ride has been dispatched — ' . $driver . ' is on the way',
            '3'     => $driver . ' is now En Route',
            '4'     => $driver . ' has arrived at your location',
            '5'     => 'Your transport has started',
            '6'     => 'Your ride ' . ($n->rreb_id ?: '') . ' has been completed',
            '7'     => 'Your ride has been cancelled',
            default => 'Ride status updated: ' . $n->status_label,
        };
    }

    private function formatUserChatNotif(RideChatNotification $n): array
    {
        return [
            'id'                   => 'chat_' . $n->id,
            'source'               => 'chat',
            'source_id'            => $n->id,
            'message'              => $this->userChatMessage($n),
            'preview'              => $n->message_preview,
            'rreb_id'              => $n->rreb_id,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => '/tracking/' . $n->emergency_request_id,
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    private function formatUserStatusNotif(RideStatusNotification $n): array
    {
        return [
            'id'                   => 'status_' . $n->id,
            'source'               => 'status',
            'source_id'            => $n->id,
            'message'              => $this->userStatusMessage($n),
            'preview'              => null,
            'rreb_id'              => $n->rreb_id,
            'emergency_request_id' => $n->emergency_request_id,
            'action_url'           => '/tracking/' . $n->emergency_request_id,
            'is_read'              => (bool) $n->is_read,
            'time_ago'             => $this->timeAgo($n->created_at),
            'time'                 => $n->created_at->format('d M Y, h:i A'),
            'ts'                   => $n->created_at->timestamp,
        ];
    }

    // ── Existing methods ──────────────────────────────────────────────────────

    public function thread(Request $request, $requestId): JsonResponse
    {
        $user = $this->user();
        $req  = EmergencyRequest::where('id', $requestId)
            ->where('user_id', $user->id)
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
            ->where('is_read_user', false)
            ->where('sender_type', '!=', 'user')
            ->update(['is_read_user' => true]);

        $notifsCleared = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        if ($notifsCleared > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'user')
                ->where('recipient_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('user', $user->id, $req->id, $req->rreb_id ?? '#' . $req->id, $notifsCleared));
            } catch (\Throwable $ignored) {}
        }

        return response()->json([
            'messages'                 => $messages,
            'status'                   => $req->status,
            'chat_status'              => RideChatMessage::chatStatus($req->status),
            'rreb_id'                  => $req->rreb_id,
            'request_id'               => $req->id,
            'ride_chat_notifs_cleared' => $notifsCleared,
        ]);
    }

    public function send(Request $request, $requestId): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $user = $this->user();
        $req  = EmergencyRequest::where('id', $requestId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($req->status, ['3', '4', '5'])) {
            return response()->json(['success' => false, 'message' => 'Chat is not available at this stage.'], 403);
        }

        $msg = RideChatMessage::create([
            'emergency_request_id' => $req->id,
            'sender_type'          => 'user',
            'sender_id'            => $user->id,
            'sender_name'          => $user->name ?: $user->username,
            'message'              => $request->message,
            'is_read_user'         => true,
            'is_read_driver'       => false,
            'is_read_admin'        => false,
        ]);

        $preview       = Str::limit($request->message, 100);
        $adminNotifId  = null;
        $driverNotifId = null;

        $allAdmins = Admin::pluck('id');
        foreach ($allAdmins as $adminId) {
            $an = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'admin',
                'recipient_id'         => $adminId,
                'sender_name'          => $user->name ?: $user->username,
                'sender_type'          => 'user',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            if (!$adminNotifId) $adminNotifId = $an->id;
        }

        if ($req->driver_id) {
            $dn = RideChatNotification::create([
                'emergency_request_id' => $req->id,
                'ride_chat_message_id' => $msg->id,
                'rreb_id'              => $req->rreb_id,
                'recipient_type'       => 'driver',
                'recipient_id'         => $req->driver_id,
                'sender_name'          => $user->name ?: $user->username,
                'sender_type'          => 'user',
                'message_preview'      => $preview,
                'is_read'              => false,
            ]);
            $driverNotifId = $dn->id;
        }

        try {
            broadcast(new RideChatMessageSent($msg, $req, $req->driver_id, $user->id, $adminNotifId, $driverNotifId, null));
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
        $user = $this->user();
        $req  = EmergencyRequest::where('id', $requestId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            broadcast(new RideChatTyping($req->id, 'user', $user->name ?: $user->username, $req->driver_id, $user->id));
        } catch (\Throwable $ignored) {}

        return response()->json(['ok' => true]);
    }

    public function unreadCount(): JsonResponse
    {
        $user         = $this->user();
        $chatUnread   = RideChatNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();

        return response()->json(['unread' => $chatUnread + $statusUnread]);
    }

    // ── Notification bell (last 10, combined chat + status) ───────────────────
    public function rideChatNotifBell(): JsonResponse
    {
        $user = $this->user();

        $chatNotifs = RideChatNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatUserChatNotif($n));

        $statusNotifs = RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => $this->formatUserStatusNotif($n));

        $combined = $chatNotifs->concat($statusNotifs)
            ->sortByDesc('ts')
            ->values()
            ->take(10);

        $chatUnread   = RideChatNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();

        return response()->json([
            'notifications' => $combined,
            'unread'        => $chatUnread + $statusUnread,
        ]);
    }

    // ── Mark single chat notification read ────────────────────────────────────
    public function markNotifRead(Request $request, $id): JsonResponse
    {
        $user  = $this->user();
        $notif = RideChatNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->findOrFail($id);

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }

        return response()->json(['is_read' => true]);
    }

    // ── Mark single status notification read ─────────────────────────────────
    public function markStatusNotifRead(Request $request, $id): JsonResponse
    {
        $user  = $this->user();
        $notif = RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->findOrFail($id);

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }

        return response()->json(['is_read' => true]);
    }

    // ── Mark all chat notifs for a ride read ──────────────────────────────────
    public function markNotifsReadByRequest(Request $request, $requestId): JsonResponse
    {
        $user = $this->user();
        $req  = EmergencyRequest::where('id', $requestId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $count = RideChatNotification::where('emergency_request_id', $requestId)
            ->where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        if ($count > 0) {
            RideChatNotification::where('emergency_request_id', $requestId)
                ->where('recipient_type', 'user')
                ->where('recipient_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            try {
                broadcast(new RideChatNotificationRead('user', $user->id, $req->id, $req->rreb_id ?? '#' . $req->id, $count));
            } catch (\Throwable $ignored) {}
        }

        return response()->json(['cleared' => $count]);
    }

    // ── Mark ALL notifications read for this user ─────────────────────────────
    public function markAllNotifsRead(): JsonResponse
    {
        $user = $this->user();

        RideChatNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // ── Notification history page ─────────────────────────────────────────────
    public function notifHistory()
    {
        $user = $this->user();

        $chatNotifs = RideChatNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatUserChatNotif($n));

        $statusNotifs = RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($n) => $this->formatUserStatusNotif($n));

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

        $chatUnread   = RideChatNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();
        $statusUnread = RideStatusNotification::where('recipient_type', 'user')->where('recipient_id', $user->id)->where('is_read', false)->count();

        return view('user.pages.notif_history', [
            'notifications' => $paginator,
            'unread'        => $chatUnread + $statusUnread,
        ]);
    }
}
