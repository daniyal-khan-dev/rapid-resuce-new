<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Ambulance;
use App\Models\Driver\Driver;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;

class LiveMonitoringController extends Controller
{
    public function index()
    {
        $drivers = Driver::whereNotIn('status', ['5'])
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'photo', 'status', 'lat', 'lng']);

        /* Load active-ride details for on-duty drivers so the admin map can
           show pickup/hospital markers and the live route immediately on page load. */
        $onDutyIds = $drivers->where('status', '3')->pluck('id');
        $activeRequests = $onDutyIds->isNotEmpty()
            ? EmergencyRequest::whereIn('driver_id', $onDutyIds)
                ->whereIn('status', ['2', '3', '4', '5', '8'])
                ->get(['id', 'driver_id', 'status', 'pickup_lat', 'pickup_lng', 'hospital_lat', 'hospital_lng'])
                ->keyBy('driver_id')
            : collect();

        $ambulances = Ambulance::with('driver:id,name,status')
            ->whereNotNull('driver_id')
            ->get(['id', 'vehicle_number', 'type', 'status', 'driver_id']);

        $stats = [
            'online'  => $drivers->where('status', '1')->count(),
            'on_duty' => $drivers->where('status', '3')->count(),
            'offline' => $drivers->whereIn('status', ['2', '4'])->count(),
            'total'   => $drivers->count(),
        ];

        return view('admin.pages.live-monitoring', compact('drivers', 'activeRequests', 'ambulances', 'stats'));
    }

    public function driversJson(): JsonResponse
    {
        $drivers = Driver::whereNotIn('status', ['5'])
            ->get(['id', 'name', 'phone', 'photo', 'status', 'lat', 'lng']);

        return response()->json($drivers);
    }
}
