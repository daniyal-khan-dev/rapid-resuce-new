<?php

namespace App\Http\Controllers\Admin;

use App\Events\RideChatMessageSent;
use App\Events\RideChatTyping;
use App\Http\Controllers\Controller;
use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RideChatController extends Controller
{
    private function admin()
    {
        return Auth::guard('admin')->user();
    }

    public function index()
    {
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
                'unread'         => RideChatMessage::where('emergency_request_id', $r->id)
                    ->where('is_read_admin', false)
                    ->where('sender_type', '!=', 'admin')
                    ->count(),
            ]);

        $unread = $requests->sum('unread');

        return view('admin.pages.ride_chats', compact('requests', 'unread'));
    }

    public function thread(Request $request, $requestId): JsonResponse
    {
        $req = EmergencyRequest::with(['driver', 'ambulance'])->findOrFail($requestId);

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

        return response()->json([
            'messages'    => $messages,
            'status'      => $req->status,
            'chat_status' => RideChatMessage::chatStatus($req->status),
            'rreb_id'     => $req->rreb_id ?? '#' . $req->id,
            'request_id'  => $req->id,
            'driver_id'   => $req->driver_id,
            'user_id'     => $req->user_id,
            'driver_name' => $req->driver?->name ?? '—',
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

        try {
            broadcast(new RideChatMessageSent($msg, $req, $req->driver_id, $req->user_id));
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
}
