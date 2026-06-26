<?php

namespace App\Http\Controllers\Driver;

use App\Events\DriverAvailabilityUpdated;
use App\Http\Controllers\Controller;
use App\Models\Driver\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverSessionController extends Controller
{
    public function heartbeat(): JsonResponse
    {
        $driver = Auth::guard('driver')->user();
        if (!$driver) {
            return response()->json(['ok' => false], 401);
        }

        $driver->last_seen_at = now();
        $driver->save();

        $this->sweepStaleDrivers();

        return response()->json(['ok' => true]);
    }

    public function tabClose(Request $request): JsonResponse
    {
        $driver = Auth::guard('driver')->user();
        if (!$driver) {
            return response()->json(['ok' => false]);
        }

        // Mark driver offline but do NOT invalidate the session.
        // Session destruction happens only via the explicit logout button.
        $driver->status       = '2';
        $driver->last_seen_at = null;
        $driver->save();

        try {
            broadcast(new DriverAvailabilityUpdated($driver));
        } catch (\Throwable $ignored) {}

        return response()->json(['ok' => true]);
    }

    public function sweepStaleEndpoint(): JsonResponse
    {
        $this->sweepStaleDrivers();
        return response()->json(['ok' => true]);
    }

    private function sweepStaleDrivers(): void
    {
        $cutoff = now()->subSeconds(35);

        $stale = Driver::whereIn('status', ['1', '3'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->get();

        foreach ($stale as $d) {
            $d->status       = '2';
            $d->last_seen_at = null;
            $d->save();
            try {
                broadcast(new DriverAvailabilityUpdated($d));
            } catch (\Throwable $ignored) {}
        }
    }
}
