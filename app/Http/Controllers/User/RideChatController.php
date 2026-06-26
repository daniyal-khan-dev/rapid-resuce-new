<?php

namespace App\Http\Controllers\User;

use App\Events\RideChatMessageSent;
use App\Events\RideChatTyping;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RideChatController extends Controller
{
    private function user()
    {
        return Auth::guard('users')->user();
    }

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

        return response()->json([
            'messages'    => $messages,
            'status'      => $req->status,
            'chat_status' => RideChatMessage::chatStatus($req->status),
            'rreb_id'     => $req->rreb_id,
            'request_id'  => $req->id,
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

        try {
            broadcast(new RideChatMessageSent($msg, $req, $req->driver_id, $user->id));
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
}
