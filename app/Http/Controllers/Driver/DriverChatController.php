<?php

namespace App\Http\Controllers\Driver;

use App\Events\RideChatMessageSent;
use App\Events\RideChatTyping;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverChatController extends Controller
{
    private function driver()
    {
        return Auth::guard('driver')->user();
    }

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

        return response()->json([
            'messages'    => $messages,
            'status'      => $req->status,
            'chat_status' => RideChatMessage::chatStatus($req->status),
            'rreb_id'     => $req->rreb_id ?? '#' . $req->id,
            'request_id'  => $req->id,
            'user_id'     => $req->user_id,
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

        try {
            broadcast(new RideChatMessageSent($msg, $req, $driver->id, $req->user_id));
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
}
