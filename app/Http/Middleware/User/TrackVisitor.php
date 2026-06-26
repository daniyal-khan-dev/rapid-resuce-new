<?php

namespace App\Http\Middleware\User;

use Closure;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use App\Models\VisitorLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TrackVisitor
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Skip logging if user is logged in (any role)
        if (Auth::check()) {
            return $next($request);
        }

        $ip = $request->ip();
        $now = Carbon::now();

        // ✅ Check if IP was logged in the last 24 hours
        $alreadyLogged = VisitorLog::where('ip_address', $ip)
            ->where('created_at', '>=', $now->subDay()) // 24 hours window
            ->exists();

        if (!$alreadyLogged) {
            $agent = new Agent();

            VisitorLog::create([
                'ip_address' => $ip,
                'browser'    => $agent->browser(),
                'platform'   => $agent->platform(),
                'device'     => $agent->device(),
                'is_mobile'  => $agent->isMobile(),
                'created_at' => $now,
            ]);
        }

        return $next($request);
    }
}
