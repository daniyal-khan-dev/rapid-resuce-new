<?php

namespace App\Http\Controllers\User;

use App\Events\ContactMessageSubmitted;
use App\Events\UserReplySubmitted;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Mail\GuestContactConfirmationMail;
use App\Models\ContactReply;
use App\Models\RideChatNotification;
use App\Models\RideStatusNotification;
use App\Models\User\ContactMessage;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $token = $request->input('g-recaptcha-response', '');
        if (! app(RecaptchaService::class)->verify($token)) {
            return response()->json([
                'errors' => ['recaptcha' => ['Please complete the human verification (reCAPTCHA).']],
            ], 422);
        }

        $request->validate([
            'contact_name'    => 'required|string|max:30',
            'contact_email'   => 'required|email|max:30',
            'contact_phone'   => 'required|string|max:11',
            'contact_subject' => 'required|string|max:50',
            'contact_message' => 'required|string',
        ], [
            'contact_name.required'    => 'Please enter your name.',
            'contact_email.required'   => 'Please enter your email.',
            'contact_email.email'      => 'Please enter a valid Email Address.',
            'contact_phone.required'   => 'Please enter your phone no.',
            'contact_subject.required' => 'Please enter subject.',
            'contact_message.required' => 'Please enter message.',
        ]);

        DB::beginTransaction();
        try {
            $userId = Auth::guard('users')->id();
            $msg    = ContactMessage::create([
                'user_id' => $userId,
                'name'    => $request->contact_name,
                'email'   => $request->contact_email,
                'phone'   => $request->contact_phone,
                'subject' => $request->contact_subject,
                'message' => $request->contact_message,
            ]);

            $welcome = ContactReply::create([
                'contact_message_id' => $msg->id,
                'sender_type'        => 'admin',
                'message'            => 'Thank you for contacting us. An operator will connect with you shortly.',
                'is_read'            => false,
            ]);

            DB::commit();

            try {
                broadcast(new ContactMessageSubmitted($msg));
            } catch (\Throwable $ignored) {}

            if (!$userId) {
                try {
                    Mail::to($msg->email)->send(new GuestContactConfirmationMail($msg));
                } catch (\Throwable $ignored) {}
            }

            return response()->json([
                'success'        => true,
                'message'        => 'Your message has been sent. We will get back to you shortly!',
                'msg_id'         => $msg->id,
                'msg_subject'    => $msg->subject,
                'msg_text'       => $msg->message,
                'msg_time'       => $msg->created_at->format('d M Y, h:i A'),
                'msg_date_short' => $msg->created_at->format('d M'),
                'welcome_reply'  => [
                    'message' => $welcome->message,
                    'time'    => $welcome->created_at->format('d M Y, h:i A'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function sendUserReply(Request $request, $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);
        $userId = Auth::guard('users')->id();
        $msg    = ContactMessage::where('id', $id)->where('user_id', $userId)->firstOrFail();

        if ($msg->is_resolved) {
            return response()->json(['success' => false, 'message' => 'This conversation is resolved.'], 403);
        }

        $reply = ContactReply::create([
            'contact_message_id' => $msg->id,
            'sender_type'        => 'user',
            'message'            => $request->message,
        ]);

        try {
            broadcast(new UserReplySubmitted($reply));
        } catch (\Throwable $ignored) {}

        return response()->json([
            'success' => true,
            'reply'   => [
                'id'      => $reply->id,
                'message' => $reply->message,
                'time'    => $reply->created_at->format('d M Y, h:i A'),
            ],
        ]);
    }

    public function bellNotifications(): JsonResponse
    {
        $userId = Auth::guard('users')->id();

        $replies = ContactReply::with('contactMessage')
            ->whereHas('contactMessage', fn($q) => $q->where('user_id', $userId))
            ->where('sender_type', 'admin')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'                 => $r->id,
                'type'               => 'reply',
                'contact_message_id' => $r->contact_message_id,
                'subject'            => $r->contactMessage?->subject ?? '',
                'preview'            => Str::limit($r->message, 60),
                'is_read'            => (bool) $r->is_read,
                'time'               => $r->created_at->format('d M Y, h:i A'),
                'date_short'         => $r->created_at->format('d M'),
                'ts'                 => $r->created_at->timestamp,
            ]);

        $rideStatuses = RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => [
                'id'           => $n->id,
                'type'         => 'ride_status',
                'status'       => $n->status,
                'status_label' => $n->status_label,
                'rreb_id'      => $n->rreb_id,
                'driver_name'  => $n->driver_name,
                'is_read'      => (bool) $n->is_read,
                'time'         => $n->created_at->format('d M Y, h:i A'),
                'date_short'   => $n->created_at->format('d M'),
                'ts'           => $n->created_at->timestamp,
            ]);

        $rideChatNotifs = RideChatNotification::where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($n) => [
                'id'                   => $n->id,
                'type'                 => 'ride_chat',
                'sender_name'          => $n->sender_name,
                'sender_type'          => $n->sender_type,
                'rreb_id'              => $n->rreb_id,
                'emergency_request_id' => $n->emergency_request_id,
                'subject'              => 'Chat: ' . $n->sender_name . ($n->rreb_id ? ' — ' . $n->rreb_id : ''),
                'preview'              => $n->message_preview,
                'is_read'              => (bool) $n->is_read,
                'time'                 => $n->created_at->format('d M Y, h:i A'),
                'date_short'           => $n->created_at->format('d M'),
                'ts'                   => $n->created_at->timestamp,
            ]);

        $all = collect($replies)->merge($rideStatuses)->merge($rideChatNotifs)
            ->sortByDesc('ts')
            ->take(10)
            ->values();

        $unread = $all->filter(fn($n) => !$n['is_read'])->count();
        return response()->json(['notifications' => $all, 'unread' => $unread]);
    }

    public function toggleReplyRead(Request $request, $id): JsonResponse
    {
        $userId = Auth::guard('users')->id();
        $reply = ContactReply::whereHas('contactMessage', fn($q) => $q->where('user_id', $userId))
            ->where('sender_type', 'admin')
            ->findOrFail($id);
        $reply->update(['is_read' => !$reply->is_read]);
        return response()->json(['is_read' => (bool) $reply->is_read]);
    }

    public function markAllRead(): JsonResponse
    {
        $userId = Auth::guard('users')->id();
        ContactReply::whereHas('contactMessage', fn($q) => $q->where('user_id', $userId))
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        RideStatusNotification::where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function toggleRideStatusNotifRead(Request $request, $id): JsonResponse
    {
        $userId = Auth::guard('users')->id();
        $notif = RideStatusNotification::where('id', $id)
            ->where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->firstOrFail();
        $notif->update(['is_read' => !$notif->is_read]);
        return response()->json(['is_read' => (bool) $notif->is_read]);
    }

    public function userTyping(Request $request, $id): JsonResponse
    {
        $userId = Auth::guard('users')->id();
        $msg    = ContactMessage::where('id', $id)->where('user_id', $userId)->firstOrFail();
        try {
            broadcast(new UserTyping($msg->id));
        } catch (\Throwable $ignored) {}
        return response()->json(['ok' => true]);
    }

    public function loadUserThread(Request $request, $id): JsonResponse
    {
        $userId = Auth::guard('users')->id();
        $msg    = ContactMessage::with('replies')->where('id', $id)->where('user_id', $userId)->firstOrFail();

        $newlyRead = $msg->replies()->where('sender_type', 'admin')->where('is_read', false)->count();
        $msg->replies()->where('sender_type', 'admin')->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'id'          => $msg->id,
            'subject'     => $msg->subject,
            'message'     => $msg->message,
            'is_resolved' => (bool) $msg->is_resolved,
            'time'        => $msg->created_at->format('d M Y, h:i A'),
            'newly_read'  => $newlyRead,
            'replies'     => $msg->replies->map(fn($r) => [
                'id'          => $r->id,
                'sender_type' => $r->sender_type,
                'message'     => $r->message,
                'time'        => $r->created_at->format('d M Y, h:i A'),
            ]),
        ]);
    }
}
