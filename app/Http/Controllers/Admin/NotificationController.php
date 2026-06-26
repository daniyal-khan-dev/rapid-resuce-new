<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\EmergencyNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = EmergencyNotification::with('emergencyRequest')
            ->orderByDesc('created_at')
            ->paginate(25);

        $unreadCount = EmergencyNotification::where('is_read', false)->count();

        return view('admin.pages.notifications', compact('notifications', 'unreadCount'));
    }

    public function unreadCount()
    {
        $count = EmergencyNotification::where('is_read', false)->count();
        return response()->json(['count' => $count]);
    }

    public function markAllRead()
    {
        EmergencyNotification::where('is_read', false)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function markRead($id)
    {
        EmergencyNotification::where('id', $id)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }
}
