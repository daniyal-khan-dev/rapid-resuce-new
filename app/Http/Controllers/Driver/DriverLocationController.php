<?php

namespace App\Http\Controllers\Driver;

use App\Events\DriverLocationUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $driver = Auth::guard('driver')->user();
        $driver->lat = round($request->lat, 7);
        $driver->lng = round($request->lng, 7);
        $driver->save();

        try {
            broadcast(new DriverLocationUpdated($driver));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DriverLocationUpdated broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }
}
