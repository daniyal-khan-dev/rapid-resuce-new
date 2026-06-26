@extends('admin.layouts.admin')
@section('title', 'Live Monitoring')
@section('page_title', 'Live Monitoring')

@push('styles')
    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <style>
        /* ── Full-height layout override ───────────────────────────────────── */
        .adm-main { padding: 0 !important; display: flex; flex-direction: column; }

        /* ── Monitoring shell ──────────────────────────────────────────────── */
        .lm-shell {
            display: flex;
            flex: 1;
            height: calc(100vh - 62px);
            overflow: hidden;
        }

        /* ── Sidebar ───────────────────────────────────────────────────────── */
        .lm-sidebar {
            width: 310px;
            min-width: 280px;
            max-width: 320px;
            background: var(--adm-card-bg, #141428);
            border-right: 1px solid rgba(255,255,255,.07);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .lm-sidebar-hd {
            padding: 18px 18px 14px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            flex-shrink: 0;
        }

        .lm-sidebar-hd h4 {
            margin: 0 0 14px;
            font-size: .95rem;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lm-sidebar-hd h4 .lm-live-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34,197,94,.4);
            animation: lmPulse 1.8s infinite;
            flex-shrink: 0;
        }

        @keyframes lmPulse {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
            70%  { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }

        .lm-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }

        .lm-stat {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 8px;
            padding: 8px 6px;
            text-align: center;
        }

        .lm-stat__val {
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 3px;
        }

        .lm-stat__lbl {
            font-size: .64rem;
            color: rgba(255,255,255,.4);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .lm-stat--online  .lm-stat__val { color: #22c55e; }
        .lm-stat--busy    .lm-stat__val { color: #f59e0b; }
        .lm-stat--offline .lm-stat__val { color: #64748b; }
        .lm-stat--total   .lm-stat__val { color: #818cf8; }

        /* ── Driver list ────────────────────────────────────────────────────── */
        .lm-driver-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .lm-driver-list::-webkit-scrollbar { width: 4px; }
        .lm-driver-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }

        .lm-driver-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.05);
            background: rgba(255,255,255,.03);
            cursor: pointer;
            transition: background .15s, border-color .15s, box-shadow .15s;
            margin-bottom: 7px;
            position: relative;
        }

        .lm-driver-card:hover {
            background: rgba(99,102,241,.1);
            border-color: rgba(99,102,241,.2);
        }

        .lm-driver-card.lm-card-active {
            background: rgba(99,102,241,.15);
            border-color: rgba(99,102,241,.4);
            box-shadow: 0 0 0 1px rgba(99,102,241,.25);
        }

        @keyframes lmCardPulse {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
            60%  { box-shadow: 0 0 0 10px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .lm-card-pulse { animation: lmCardPulse .8s ease-out; }

        .lm-card-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,.1);
            flex-shrink: 0;
        }

        .lm-card-avatar--icon {
            display: flex; align-items: center; justify-content: center;
            background: rgba(215,44,66,.12);
            color: #D72C42;
            font-size: .85rem;
        }

        .lm-card-info { flex: 1; min-width: 0; }

        .lm-card-name {
            font-size: .83rem;
            font-weight: 700;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lm-card-meta {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 2px;
        }

        .lm-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .lm-dot--online  { background: #22c55e; box-shadow: 0 0 5px rgba(34,197,94,.6); }
        .lm-dot--busy    { background: #f59e0b; box-shadow: 0 0 5px rgba(245,158,11,.6); }
        .lm-dot--offline { background: #475569; }
        .lm-dot--ride    { background: #3b82f6; box-shadow: 0 0 5px rgba(59,130,246,.6); animation: lmPulse 1.8s infinite; }

        .lm-card-status {
            font-size: .72rem;
            color: rgba(255,255,255,.5);
            font-weight: 600;
        }

        .lm-card-coords {
            font-size: .68rem;
            color: rgba(255,255,255,.3);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lm-card-coords i { color: #D72C42; }

        .lm-card-time {
            font-size: .64rem;
            color: rgba(255,255,255,.2);
            margin-top: 1px;
        }
        .lm-card-time i { opacity: .6; }

        .lm-card-btn {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 7px;
            color: rgba(255,255,255,.6);
            padding: 5px 7px;
            font-size: .72rem;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .15s;
        }
        .lm-card-btn:hover { background: rgba(99,102,241,.3); color: #c7d2fe; }

        .lm-empty {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255,255,255,.2);
        }
        .lm-empty i { font-size: 2rem; display: block; margin-bottom: 8px; }
        .lm-empty p  { font-size: .82rem; margin: 0; }

        /* ── Map panel ──────────────────────────────────────────────────────── */
        .lm-map-wrap {
            flex: 1;
            position: relative;
            min-width: 0;
        }

        #liveMap {
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* Leaflet popup dark theme */
        .leaflet-popup-content-wrapper {
            background: #1e1e38 !important;
            color: #f1f5f9 !important;
            border: 1px solid rgba(255,255,255,.1) !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0,0,0,.5) !important;
        }
        .leaflet-popup-tip { background: #1e1e38 !important; }
        .leaflet-popup-close-button { color: rgba(255,255,255,.5) !important; }
        .leaflet-popup-close-button:hover { color: #fff !important; }
        .leaflet-control-zoom a {
            background: #1e1e38 !important;
            color: #f1f5f9 !important;
            border-color: rgba(255,255,255,.1) !important;
        }
        .leaflet-control-zoom a:hover { background: rgba(99,102,241,.3) !important; }
        .leaflet-control-attribution { background: rgba(0,0,0,.55) !important; color: rgba(255,255,255,.3) !important; }
        .leaflet-control-attribution a { color: rgba(255,255,255,.4) !important; }

        /* ── Map overlay buttons ────────────────────────────────────────────── */
        .lm-map-controls {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .lm-map-btn {
            background: rgba(14,23,40,.88);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            color: rgba(255,255,255,.7);
            padding: 7px 12px;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(6px);
            transition: background .15s, color .15s;
            white-space: nowrap;
        }
        .lm-map-btn:hover { background: rgba(99,102,241,.35); color: #c7d2fe; border-color: rgba(99,102,241,.4); }

        /* ── Last-updated bar ───────────────────────────────────────────────── */
        .lm-map-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 10;
            background: linear-gradient(to top, rgba(10,14,26,.85), transparent);
            padding: 20px 16px 8px;
            pointer-events: none;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
        }

        .lm-last-update {
            font-size: .7rem;
            color: rgba(255,255,255,.25);
        }

        /* ── Search ─────────────────────────────────────────────────────────── */
        .lm-search {
            padding: 10px 10px 4px;
            flex-shrink: 0;
        }

        .lm-search input {
            width: 100%;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 8px;
            color: #f1f5f9;
            padding: 7px 12px 7px 32px;
            font-size: .78rem;
            outline: none;
        }
        .lm-search input::placeholder { color: rgba(255,255,255,.25); }
        .lm-search input:focus { border-color: rgba(99,102,241,.4); background: rgba(99,102,241,.06); }
        .lm-search { position: relative; }
        .lm-search i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,.25);
            font-size: .74rem;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .lm-shell { flex-direction: column; height: auto; }
            .lm-sidebar { width: 100%; max-width: 100%; height: 300px; border-right: none; border-bottom: 1px solid rgba(255,255,255,.07); }
            .lm-map-wrap { height: 50vh; }
        }
    </style>
@endpush

@section('content')

{{-- Sweep URL for stale-driver detection ────────────────────────────────────── --}}
<input type="hidden" id="lmSweepUrl" value="{{ route('admin.drivers.sweepStale') }}">

{{-- Driver JSON for JS ─────────────────────────────────────────────────────── --}}
<script id="lmDriverData" type="application/json">
    {!! json_encode($drivers->map(function($d) use ($activeRequests) {
        $req = $activeRequests[$d->id] ?? null;
        return [
            'id'          => $d->id,
            'name'        => $d->name,
            'phone'       => $d->phone,
            'photo'       => $d->photo,
            'status'      => (string) $d->status,
            'lat'         => $d->lat ? (float) $d->lat : null,
            'lng'         => $d->lng ? (float) $d->lng : null,
            /* Active-ride details — populated for status-3 drivers only */
            'req_status'   => $req ? (string) $req->status    : null,
            'pickup_lat'   => $req && $req->pickup_lat   ? (float) $req->pickup_lat   : null,
            'pickup_lng'   => $req && $req->pickup_lng   ? (float) $req->pickup_lng   : null,
            'hospital_lat' => $req && $req->hospital_lat ? (float) $req->hospital_lat : null,
            'hospital_lng' => $req && $req->hospital_lng ? (float) $req->hospital_lng : null,
        ];
    })) !!}
</script>

<div class="lm-shell">

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    <aside class="lm-sidebar">

        <div class="lm-sidebar-hd">
            <h4>
                <span class="lm-live-dot"></span>
                Live Driver Map
            </h4>

            <div class="lm-stats-row">
                <div class="lm-stat lm-stat--online">
                    <div class="lm-stat__val" id="lmStatOnline">{{ $stats['online'] }}</div>
                    <div class="lm-stat__lbl">Online</div>
                </div>
                <div class="lm-stat lm-stat--busy">
                    <div class="lm-stat__val" id="lmStatBusy">{{ $stats['on_duty'] }}</div>
                    <div class="lm-stat__lbl">On Duty</div>
                </div>
                <div class="lm-stat lm-stat--offline">
                    <div class="lm-stat__val" id="lmStatOffline">{{ $stats['offline'] }}</div>
                    <div class="lm-stat__lbl">Offline</div>
                </div>
                <div class="lm-stat lm-stat--total">
                    <div class="lm-stat__val" id="lmStatTotal">{{ $stats['total'] }}</div>
                    <div class="lm-stat__lbl">Total</div>
                </div>
            </div>
        </div>

        <div class="lm-search">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" id="lmSearch" placeholder="Search driver…" oninput="lmFilterCards(this.value)">
        </div>

        <div class="lm-driver-list" id="lmDriverList">
            {{-- Populated by live-monitoring.js --}}
        </div>

    </aside>

    {{-- ── Map panel ────────────────────────────────────────────────────── --}}
    <div class="lm-map-wrap">
        <div id="liveMap"></div>

        {{-- Overlay controls --}}
        <div class="lm-map-controls">
            <button class="lm-map-btn" onclick="lmFitAll()">
                <i class="fa fa-expand"></i> Fit All
            </button>
            <button class="lm-map-btn" onclick="lmReload()">
                <i class="fa fa-rotate-right"></i> Refresh
            </button>
        </div>

        {{-- Footer timestamp --}}
        <div class="lm-map-footer">
            <span class="lm-last-update" id="lmLastUpdate">Live — updates in real-time</span>
            <span id="lmWsStatus" style="margin-left:12px;font-size:.7rem;padding:2px 8px;border-radius:12px;background:rgba(100,116,139,.25);color:rgba(255,255,255,.35);">
                <i class="fa fa-circle" style="font-size:.5rem;vertical-align:middle;"></i> Connecting…
            </span>
        </div>
    </div>

</div>

@endsection

@push('scripts')
    {{-- Leaflet JS --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    {{-- Live monitoring JS --}}
    <script src="{{ asset('assets/admin/js/live-monitoring.js') }}"></script>

    <script>
        // ── Extra helper functions ─────────────────────────────────────────

        // Fit all drivers button
        function lmFitAll() {
            var pts = [];
            document.querySelectorAll('.lm-driver-card').forEach(function (card) {
                var id = card.dataset.driverId;
                if (window._drvLocations && window._drvLocations[id]) {
                    pts.push([window._drvLocations[id].lat, window._drvLocations[id].lng]);
                }
            });
            if (pts.length === 1 && window._admMap) window._admMap.setView(pts[0], 14);
            else if (pts.length > 1 && window._admMap) window._admMap.fitBounds(pts, { padding: [60,60], maxZoom: 14 });
        }

        // Reload page data
        function lmReload() {
            window.location.reload();
        }

        // Card filter / search
        function lmFilterCards(q) {
            q = (q || '').toLowerCase().trim();
            document.querySelectorAll('.lm-driver-card').forEach(function (card) {
                var name = (card.querySelector('.lm-card-name') || {}).textContent || '';
                card.style.display = (!q || name.toLowerCase().includes(q)) ? '' : 'none';
            });
        }

        // ── WebSocket status indicator ─────────────────────────────────────
        function lmSetWsStatus(state) {
            var el = document.getElementById('lmWsStatus');
            if (!el) return;
            var configs = {
                connecting: { bg: 'rgba(100,116,139,.25)', color: 'rgba(255,255,255,.35)', icon: '#94a3b8', label: 'Connecting…' },
                live:       { bg: 'rgba(34,197,94,.15)',   color: '#22c55e',               icon: '#22c55e', label: 'Live' },
                error:      { bg: 'rgba(239,68,68,.15)',   color: '#f87171',               icon: '#f87171', label: 'Disconnected' },
            };
            var cfg = configs[state] || configs.connecting;
            el.style.background = cfg.bg;
            el.style.color      = cfg.color;
            var dot = el.querySelector('i');
            if (dot) dot.style.color = cfg.icon;
            el.childNodes[el.childNodes.length - 1].textContent = ' ' + cfg.label;
        }

        // Hook into the Pusher connection events once Pusher is ready
        document.addEventListener('DOMContentLoaded', function () {
            // Poll until _admPusher is set
            var _wsCheckAttempts = 0;
            var _wsCheckTimer = setInterval(function () {
                _wsCheckAttempts++;
                if (window._admPusher) {
                    clearInterval(_wsCheckTimer);
                    var state = window._admPusher.connection.state;
                    lmSetWsStatus(state === 'connected' ? 'live' : 'connecting');

                    window._admPusher.connection.bind('connected',    function () { lmSetWsStatus('live'); });
                    window._admPusher.connection.bind('disconnected', function () { lmSetWsStatus('error'); });
                    window._admPusher.connection.bind('failed',       function () { lmSetWsStatus('error'); });
                    window._admPusher.connection.bind('connecting',   function () { lmSetWsStatus('connecting'); });
                } else if (_wsCheckAttempts > 30) {
                    clearInterval(_wsCheckTimer);
                    lmSetWsStatus('error');
                }
            }, 200);
        });

        // ── Wrap admDriverLocationUpdated to also update the timestamp ─────
        var _origLocUpdated = window.admDriverLocationUpdated;
        window.admDriverLocationUpdated = function (e) {
            if (typeof _origLocUpdated === 'function') _origLocUpdated(e);
            var el = document.getElementById('lmLastUpdate');
            if (el) el.textContent = 'Last update: ' + (e.time || new Date().toLocaleTimeString());
        };

        // Mark page module for auto-reload banner suppression
        window._rrPageModule = 'live-monitoring';
    </script>
@endpush
