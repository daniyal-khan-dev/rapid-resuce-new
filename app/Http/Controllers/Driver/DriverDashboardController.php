<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DriverDashboardController extends Controller
{
    public function index()
    {
        $driver = Auth::guard('driver')->user();

        $total     = EmergencyRequest::where('driver_id', $driver->id)->count();
        $completed = EmergencyRequest::where('driver_id', $driver->id)->where('status', '6')->count();
        $cancelled = EmergencyRequest::where('driver_id', $driver->id)->where('status', '7')->count();
        $active    = EmergencyRequest::where('driver_id', $driver->id)->whereNotIn('status', ['6', '7'])->where('status', '!=', '1')->count();
        $pending   = EmergencyRequest::where('driver_id', $driver->id)->where('status', '2')->count();
        $today     = EmergencyRequest::where('driver_id', $driver->id)->whereDate('created_at', today())->count();

        $history = EmergencyRequest::with(['ambulance'])
            ->where('driver_id', $driver->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('driver.pages.dashboard', compact(
            'driver', 'total', 'completed', 'cancelled', 'active', 'pending', 'today', 'history'
        ));
    }

    public function notificationsPage()
    {
        $driver = Auth::guard('driver')->user();
        return view('driver.pages.notifications', compact('driver'));
    }

    public function requests(Request $request)
    {
        $driver = Auth::guard('driver')->user();

        $query = EmergencyRequest::with(['ambulance'])
            ->where('driver_id', $driver->id);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', ['6', '7']);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('rreb_id', 'like', "%$s%")
                  ->orWhere('hospital_name', 'like', "%$s%")
                  ->orWhere('pickup_address', 'like', "%$s%")
                  ->orWhere('mobile_no', 'like', "%$s%");
            });
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total'     => EmergencyRequest::where('driver_id', $driver->id)->count(),
            'active'    => EmergencyRequest::where('driver_id', $driver->id)->whereNotIn('status', ['6', '7'])->count(),
            'completed' => EmergencyRequest::where('driver_id', $driver->id)->where('status', '6')->count(),
            'cancelled' => EmergencyRequest::where('driver_id', $driver->id)->where('status', '7')->count(),
        ];

        return view('driver.pages.requests', compact('driver', 'requests', 'stats'));
    }

    public function pastRides(Request $request)
    {
        $driver = Auth::guard('driver')->user();

        $query = EmergencyRequest::with(['ambulance'])
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['6', '7']);

        if ($request->filled('status') && in_array($request->status, ['6', '7'])) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('rreb_id', 'like', "%$s%")
                  ->orWhere('hospital_name', 'like', "%$s%")
                  ->orWhere('pickup_address', 'like', "%$s%")
                  ->orWhere('mobile_no', 'like', "%$s%");
            });
        }

        $dateFilter = $request->get('date_filter', 'all');
        if ($dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rides = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'completed' => EmergencyRequest::where('driver_id', $driver->id)->where('status', '6')->count(),
            'cancelled' => EmergencyRequest::where('driver_id', $driver->id)->where('status', '7')->count(),
        ];

        return view('driver.pages.past_rides', compact('driver', 'rides', 'stats'));
    }

    public function profile()
    {
        $driver = Auth::guard('driver')->user();
        return view('driver.pages.profile', compact('driver'));
    }

    public function updateProfile(Request $request)
    {
        $driver = Auth::guard('driver')->user();

        $request->validate([
            'name'  => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $driver->name  = $request->name;
        $driver->phone = $request->phone;
        $driver->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $driver = Auth::guard('driver')->user();

        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $driver->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        $driver->password = Hash::make($request->password);
        $driver->save();

        return back()->with('success', 'Password changed successfully.');
    }
}
