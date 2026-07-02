@extends('driver.layouts.driver')
@section('title', 'My Requests')
@section('page_title', 'My Requests')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .req-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
        }

        .req-table th {
            padding: 10px 16px;
            text-align: left;
            font-size: .71rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .35);
            text-transform: uppercase;
            letter-spacing: .05em;
            background: rgba(255, 255, 255, .03);
            border-bottom: 1px solid rgba(255, 255, 255, .06);
            white-space: nowrap;
        }

        .req-table td {
            padding: 12px 16px;
            color: rgba(255, 255, 255, .72);
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            vertical-align: middle;
        }

        .req-table tbody tr:last-child td {
            border-bottom: none;
        }

        .req-table tbody tr:hover td {
            background: rgba(255, 255, 255, .03);
        }

        .req-table .mono {
            font-family: monospace;
            font-size: .78rem;
            background: rgba(255, 255, 255, .06);
            padding: 2px 7px;
            border-radius: 5px;
            color: rgba(255, 255, 255, .65);
        }

        .req-empty td {
            text-align: center;
            padding: 50px;
            color: rgba(255, 255, 255, .25);
            font-size: .85rem;
        }

        /* Detail modal */
        .req-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 22px;
        }

        @media (max-width:580px) {
            .req-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        .req-detail-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .req-detail-item span {
            font-size: .69rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .35);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .req-detail-item strong {
            font-size: .88rem;
            color: #e2e8f0;
            font-weight: 600;
        }

        /* Spinner */
        .dri-spinner {
            width: 22px;
            height: 22px;
            border: 2px solid rgba(255, 255, 255, .12);
            border-top-color: #60a5fa;
            border-radius: 50%;
            animation: driSpin .6s linear infinite;
            display: inline-block;
        }

        @keyframes driSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Leaflet route panel override */
        .leaflet-routing-container {
            display: none !important;
        }

        /* ── Status Action Panel ────────────────────────────────────── */
        .req-action-panel {
            margin-top: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, .03);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
        }

        .req-action-panel__title {
            font-size: .68rem;
            font-weight: 700;
            color: rgba(255, 255, 255, .3);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .req-action-panel__title i {
            color: rgba(255, 255, 255, .22);
        }

        .req-action-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .req-act-btn {
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: opacity .15s, transform .1s, box-shadow .15s;
            line-height: 1.3;
        }

        .req-act-btn:hover:not(:disabled) {
            opacity: .88;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .35);
        }

        .req-act-btn:active:not(:disabled) {
            transform: scale(.97);
        }

        .req-act-btn:disabled {
            opacity: .38;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .req-act-btn.enroute {
            background: #2563eb;
            color: #fff;
        }

        .req-act-btn.arrived {
            background: #7c3aed;
            color: #fff;
        }

        .req-act-btn.transport {
            background: #d97706;
            color: #fff;
        }

        .req-act-btn.complete {
            background: #16a34a;
            color: #fff;
        }

        .req-act-btn.cancel {
            background: rgba(239, 68, 68, .12);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, .25);
        }

        .req-act-btn .btn-spin {
            width: 13px;
            height: 13px;
            border: 2px solid rgba(255, 255, 255, .25);
            border-top-color: #fff;
            border-radius: 50%;
            animation: driSpin .55s linear infinite;
            display: inline-block;
            flex-shrink: 0;
        }

        /* ── Transport Live Panel ────────────────────────────────────────────── */
        .transport-panel {
            margin-bottom: 14px;
            padding: 14px 16px;
            background: rgba(217, 119, 6, .07);
            border: 1px solid rgba(217, 119, 6, .22);
            border-radius: 12px;
        }

        .transport-panel__header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .transport-panel__dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #f59e0b;
            flex-shrink: 0;
            box-shadow: 0 0 0 0 rgba(245, 158, 11, .4);
            animation: driLmPulse 1.8s infinite;
        }

        .transport-panel__title {
            font-size: .72rem;
            font-weight: 700;
            color: #f59e0b;
            letter-spacing: .05em;
        }

        .transport-panel__passenger {
            font-size: .77rem;
            color: rgba(255, 255, 255, .55);
            margin-left: auto;
            white-space: nowrap;
        }

        .transport-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .transport-stat {
            flex: 1;
            min-width: 90px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 10px 12px;
        }

        .transport-stat__lbl {
            font-size: .62rem;
            color: rgba(255, 255, 255, .28);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .transport-stat__val {
            font-size: .95rem;
            font-weight: 700;
            color: #e2e8f0;
        }

        .transport-stat__val span {
            font-size: .65rem;
            font-weight: 400;
            color: rgba(255, 255, 255, .35);
        }

        /* ── Custom divIcon wrapper — suppresses Leaflet default white box ─────── */
        .rm-div-icon {
            background: none !important;
            border: none !important;
        }
    </style>
@endpush

@section('content')

    {{-- Page header --}}
    <div class="dri-page-header">
        <div>
            <h2>My Requests</h2>
            <p>Active requests assigned to you. Completed &amp; cancelled rides are in <a
                    href="{{ route('driver.past-rides') }}" style="color:#60a5fa;text-decoration:none;font-weight:600;">Past
                    Rides <i class="fa fa-arrow-right" style="font-size:.75em;"></i></a></p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="status-pill s6"><i class="fa fa-circle-check me-1"></i>{{ $stats['completed'] }} Completed</span>
            <span class="status-pill s2"><i class="fa fa-spinner me-1"></i>{{ $stats['active'] }} Active</span>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="dri-stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:20px;">
        <div class="dri-stat-card">
            <div class="dri-stat-icon blue"><i class="fa fa-ambulance"></i></div>
            <div class="dri-stat-val">{{ $stats['total'] }}</div>
            <div class="dri-stat-lbl">Total Rides</div>
        </div>
        <div class="dri-stat-card">
            <div class="dri-stat-icon amber"><i class="fa fa-spinner"></i></div>
            <div class="dri-stat-val">{{ $stats['active'] }}</div>
            <div class="dri-stat-lbl">Active</div>
        </div>
        <div class="dri-stat-card">
            <div class="dri-stat-icon green"><i class="fa fa-circle-check"></i></div>
            <div class="dri-stat-val">{{ $stats['completed'] }}</div>
            <div class="dri-stat-lbl">Completed</div>
        </div>
        <div class="dri-stat-card">
            <div class="dri-stat-icon red"><i class="fa fa-ban"></i></div>
            <div class="dri-stat-val">{{ $stats['cancelled'] }}</div>
            <div class="dri-stat-lbl">Cancelled</div>
        </div>
    </div>

    {{-- Table card --}}
    <div class="card" style="overflow:visible;">
        {{-- Toolbar --}}
        <form method="GET" action="{{ route('driver.requests') }}" id="reqFilterForm">
            <div class="dri-toolbar">
                <div class="dri-search-wrap">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}" class="dri-search-input"
                        placeholder="Search by ID, hospital, pickup…" autocomplete="off">
                </div>
                <select name="status" class="dri-filter-select"
                    onchange="document.getElementById('reqFilterForm').submit()">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Active</option>
                    <option value="2" {{ request('status') === '2' ? 'selected' : '' }}>Dispatched</option>
                    <option value="3" {{ request('status') === '3' ? 'selected' : '' }}>En Route</option>
                    <option value="4" {{ request('status') === '4' ? 'selected' : '' }}>Arrived</option>
                    <option value="5" {{ request('status') === '5' ? 'selected' : '' }}>Transporting</option>
                </select>
                <button type="submit" class="btn-dri-primary" style="padding:7px 16px;font-size:.8rem;">
                    <i class="fa fa-magnifying-glass"></i> Search
                </button>
                @if (request('search') || (request('status') && request('status') !== 'all'))
                    <a href="{{ route('driver.requests') }}" class="btn-dri-secondary"
                        style="padding:7px 14px;font-size:.8rem;text-decoration:none;">
                        <i class="fa fa-xmark me-1"></i> Clear
                    </a>
                @endif
            </div>
        </form>

        {{-- Table --}}
        <div class="pgd-scroll pgd-scroll--list">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Type</th>
                        <th>Pickup Address</th>
                        <th>Hospital</th>
                        <th>Mobile</th>
                        <th>Ambulance</th>
                        <th>Status</th>
                        <th>Dispatched</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="driReqTableBody">
                    @forelse($requests as $r)
                        @php
                            $smap = [
                                '1' => ['Pending', 's1'],
                                '2' => ['Dispatched', 's2'],
                                '3' => ['En Route', 's3'],
                                '4' => ['Arrived', 's4'],
                                '5' => ['Transporting', 's5'],
                                '6' => ['Completed', 's6'],
                                '7' => ['Cancelled', 's7'],
                                '8' => ['Awaiting Acceptance', 's8'],
                            ];
                            [$slabel, $sclass] = $smap[$r->status] ?? [ucfirst($r->status), 's1'];
                        @endphp
                        <tr id="reqRow_{{ $r->id }}">
                            <td><span class="mono">{{ $r->rreb_id ?? '#' . $r->id }}</span></td>
                            <td>
                                @if ($r->type === '1')
                                    <span class="status-pill emergency">Emergency</span>
                                @else
                                    <span class="status-pill non-emergency">Non-Emergency</span>
                                @endif
                            </td>
                            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ Str::limit($r->pickup_address, 32) }}
                            </td>
                            <td>{{ Str::limit($r->hospital_name, 22) }}</td>
                            <td style="white-space:nowrap;">{{ $r->mobile_no }}</td>
                            <td>{{ $r->ambulance?->vehicle_number ?? '—' }}</td>
                            <td><span class="status-pill {{ $sclass }}"
                                    id="reqStatusBadge_{{ $r->id }}">{{ $slabel }}</span></td>
                            <td style="white-space:nowrap;color:rgba(255,255,255,.38);font-size:.77rem;">
                                {{ $r->dispatched_at?->format('d M, H:i') ?? $r->created_at->format('d M, H:i') }}
                            </td>
                            <td>
                                <button class="btn-dri-icon btn-dri-icon--primary" title="View Details"
                                    onclick="viewRequestDetail({{ $r->id }})">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="req-empty">
                            <td colspan="9">
                                <i class="fa fa-inbox"
                                    style="display:block;font-size:1.6rem;margin-bottom:10px;opacity:.2;"></i>
                                No requests found.
                                @if (request('search') || request('status'))
                                    <br><a href="{{ route('driver.requests') }}"
                                        style="color:rgba(255,255,255,.35);font-size:.78rem;">Clear filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($requests->hasPages())
            <div class="pgd-footer">
                <div class="pgd-info">
                    Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }} results
                </div>
                <div class="pgd-controls">
                    @if ($requests->onFirstPage())
                        <button class="pgd-btn" disabled>← Prev</button>
                    @else
                        <a href="{{ $requests->previousPageUrl() }}" class="pgd-btn">← Prev</a>
                    @endif
                    <span class="pgd-pages">Page {{ $requests->currentPage() }} / {{ $requests->lastPage() }}</span>
                    @if ($requests->hasMorePages())
                        <a href="{{ $requests->nextPageUrl() }}" class="pgd-btn">Next →</a>
                    @else
                        <button class="pgd-btn" disabled>Next →</button>
                    @endif
                </div>
            </div>
        @endif
    </div>


@endsection

@php
    $_reqDataJson = $requests
        ->map(function ($r) use ($driver) {
            return [
                'id' => $r->id,
                'rreb_id' => $r->rreb_id,
                'type' => $r->type,
                'status' => $r->status,
                'mobile_no' => $r->mobile_no,
                'email' => $r->email,
                'pickup_address' => $r->pickup_address,
                'pickup_lat' => $r->pickup_lat ? (float) $r->pickup_lat : null,
                'pickup_lng' => $r->pickup_lng ? (float) $r->pickup_lng : null,
                'hospital_name' => $r->hospital_name,
                'hospital_lat' => $r->hospital_lat ? (float) $r->hospital_lat : null,
                'hospital_lng' => $r->hospital_lng ? (float) $r->hospital_lng : null,
                'accepted_lat' => $r->accepted_lat ? (float) $r->accepted_lat : null,
                'accepted_lng' => $r->accepted_lng ? (float) $r->accepted_lng : null,
                'driver_lat' => $driver->lat ? (float) $driver->lat : null,
                'driver_lng' => $driver->lng ? (float) $driver->lng : null,
                'notes' => $r->notes,
                'ambulance_no' => optional($r->ambulance)->vehicle_number,
                'ambulance_type' => optional($r->ambulance)->type,
                'dispatched_at' => $r->dispatched_at ? $r->dispatched_at->format('d M Y, h:i A') : null,
                'completed_at' => $r->completed_at ? $r->completed_at->format('d M Y, h:i A') : null,
                'created_at' => $r->created_at->format('d M Y, h:i A'),
                'allowed_next' => (function ($status) {
                    return match ($status) {
                        '8' => [],
                        '2' => ['3'],
                        '3' => ['4'],
                        '4' => ['5'],
                        '5' => ['6'],
                        default => [],
                    };
                })($r->status),
            ];
        })
        ->values()
        ->all();

@endphp

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
    <script>
        var _reqData = @json($_reqDataJson);

        var _statusLabels = {
            '1': 'Pending',
            '2': 'Dispatched',
            '3': 'En Route',
            '4': 'Arrived',
            '5': 'Transporting',
            '6': 'Completed',
            '7': 'Cancelled',
            '8': 'Awaiting Acceptance'
        };
        var _statusClass = {
            '1': 's1',
            '2': 's2',
            '3': 's3',
            '4': 's4',
            '5': 's5',
            '6': 's6',
            '7': 's7',
            '8': 's8'
        };

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                '&quot;');
        }

        /* ── Map state ─────────────────────────────────────────────────────────────── */
        var _rm = null; // Leaflet map instance
        var _rmDriverMarker = null; // ambulance marker
        var _rmPickupMarker = null; // person marker
        var _rmHospitalMarker = null; // hospital marker
        var _rmRouting = null; // legacy routing control ref (kept for cleanup)
        var _rmCurrentReq = null; // currently open request object
        var _rmGeoWatch = null; // geolocation watch ID
        var _rmAnimFrames = {}; // rAF keys for smooth movement
        var _rmAdminChannel = null; // Reverb contact.admin channel subscription
        var _rmEtaInFlight = false;
        var _rmEtaLastFetch = 0;
        var ETA_INTERVAL_MS = 20000;

        /* ── Route polyline (replaces L.Routing.control) ────────────────────────── */
        var _rmRouteLine = null; // L.polyline — remaining (blue) route ahead
        var _rmRouteCoords = []; // [[lat,lng], ...] full route from OSRM
        var _rmRouteProgress = 0; // index: how far along route driver has traveled
        var _rmRouteFetching = false; // guard against concurrent OSRM fetches

        /* ── Traveled path tracking ─────────────────────────────────────────────── */
        var _rmTraveledPoints = []; // [[lat,lng], ...] — positions visited
        var _rmTraveledLine = null; // L.polyline — grey traveled path
        var _rmDeviationTimer = null; // setInterval handle
        var _rmPrevPos = null; // {lat,lng} previous position for speed calc
        var _rmPrevPosTs = 0; // timestamp (ms) of previous position
        var _rmCurrentSpeed = 0; // km/h derived from consecutive positions
        var _rmMarkerIconLive = true; // track current icon state to avoid unnecessary setIcon calls
        var _rmLastRecalcTs = 0; // timestamp of last route recalculation (cooldown guard)
        var _rmLastGpsFix = 0; // timestamp of last real GPS fix (used to deprioritize Reverb)
        var _rmAcceptedMarker = null; // marker showing where driver was when they accepted the request

        /* ── Icon builders ─────────────────────────────────────────────────────────── */
        function _rmPersonIcon() {
            return L.divIcon({
                className: 'rm-div-icon',
                html: '<div style="background:#D72C42;border:3px solid #fff;border-radius:50%;width:38px;height:38px;' +
                    'box-shadow:0 2px 14px rgba(215,44,66,0.55);display:flex;align-items:center;justify-content:center;">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">' +
                    '<circle cx="12" cy="7" r="4"/><path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>' +
                    '</svg></div>',
                iconSize: [38, 38],
                iconAnchor: [19, 19],
                popupAnchor: [0, -22],
            });
        }

        function _rmAmbulanceIcon(isLive) {
            var glow = isLive ? 'rgba(34,197,94,0.6)' : 'rgba(29,78,216,0.55)';
            var bg = isLive ? '#16a34a' : '#1d4ed8';
            var anim = isLive ? 'animation:driLmPulse 1.8s infinite;' : '';
            return L.divIcon({
                className: 'rm-div-icon',
                html: '<div style="background:' + bg +
                    ';border:3px solid #fff;border-radius:50%;width:38px;height:38px;' +
                    'box-shadow:0 2px 14px ' + glow + ';display:flex;align-items:center;justify-content:center;' +
                    anim + '">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">' +
                    '<path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1' +
                    'c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5' +
                    'S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5' +
                    ' 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>' +
                    '</svg></div>',
                iconSize: [38, 38],
                iconAnchor: [19, 19],
                popupAnchor: [0, -22],
            });
        }

        function _rmHospitalIcon() {
            return L.divIcon({
                className: 'rm-div-icon',
                html: '<div style="background:#16a34a;border:3px solid #fff;border-radius:50%;width:34px;height:34px;' +
                    'box-shadow:0 2px 12px rgba(22,163,74,0.5);display:flex;align-items:center;justify-content:center;">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="18" height="18">' +
                    '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 0 1' +
                    ' 1 1v3h3a1 1 0 0 1 0 2h-3v3a1 1 0 0 1-2 0v-3H8a1 1 0 0 1 0-2h3V7a1 1 0 0 1 1-1z"/>' +
                    '</svg></div>',
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -20],
            });
        }

        function _rmAcceptedIcon() {
            return L.divIcon({
                className: 'rm-div-icon',
                html: '<div style="background:#f59e0b;border:3px solid #fff;border-radius:50%;width:30px;height:30px;' +
                    'box-shadow:0 2px 10px rgba(245,158,11,0.5);display:flex;align-items:center;justify-content:center;">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="14" height="14">' +
                    '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>' +
                    '</svg></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
                popupAnchor: [0, -18],
            });
        }

        /* ── Haversine distance (metres) ───────────────────────────────────────────── */
        function _rmHaversine(lat1, lng1, lat2, lng2) {
            var R = 6371000;
            var p1 = lat1 * Math.PI / 180,
                p2 = lat2 * Math.PI / 180;
            var dp = (lat2 - lat1) * Math.PI / 180,
                dl = (lng2 - lng1) * Math.PI / 180;
            var a = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) * Math.sin(dl / 2);
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        /* ── Smooth marker movement ────────────────────────────────────────────────── */
        function _rmAnimateTo(key, marker, toLat, toLng, ms) {
            ms = ms || 700;
            if (_rmAnimFrames[key]) {
                cancelAnimationFrame(_rmAnimFrames[key]);
                delete _rmAnimFrames[key];
            }
            var from = marker.getLatLng();
            if (Math.abs(toLat - from.lat) < 1e-7 && Math.abs(toLng - from.lng) < 1e-7) {
                marker.setLatLng([toLat, toLng]);
                return;
            }
            var startTs = performance.now();

            function step(ts) {
                var t = Math.min((ts - startTs) / ms, 1);
                var ease = 1 - Math.pow(1 - t, 3);
                marker.setLatLng([from.lat + (toLat - from.lat) * ease, from.lng + (toLng - from.lng) * ease]);
                if (t < 1) {
                    _rmAnimFrames[key] = requestAnimationFrame(step);
                } else {
                    delete _rmAnimFrames[key];
                }
            }
            _rmAnimFrames[key] = requestAnimationFrame(step);
        }

        /* ── ETA helpers ───────────────────────────────────────────────────────────── */
        function _rmFmtDuration(secs) {
            if (secs < 60) return '< 1 min';
            var m = Math.round(secs / 60);
            if (m < 60) return m + ' min';
            var h = Math.floor(m / 60),
                rm = m % 60;
            return h + ' hr' + (rm ? ' ' + rm + ' min' : '');
        }

        function _rmFmtDistance(m) {
            return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(1) + ' km';
        }

        function _rmFetchETA(driverLat, driverLng) {
            if (!_rmCurrentReq || _rmEtaInFlight) return;
            var now = Date.now();
            if (now - _rmEtaLastFetch < ETA_INTERVAL_MS) return;

            var status = _rmCurrentReq.status;
            var destLat, destLng, destLabel;

            if (status === '4') {
                var etaEl = document.getElementById('driReqETA');
                if (etaEl) etaEl.innerHTML = '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>' +
                    ' <strong style="color:#22c55e;">At pickup location</strong>';
                return;
            }
            if ((status === '8' || status === '2' || status === '3') && _rmCurrentReq.pickup_lat) {
                destLat = _rmCurrentReq.pickup_lat;
                destLng = _rmCurrentReq.pickup_lng;
                destLabel = 'pickup';
            } else if (status === '5' && _rmCurrentReq.hospital_lat) {
                destLat = _rmCurrentReq.hospital_lat;
                destLng = _rmCurrentReq.hospital_lng;
                destLabel = 'hospital';
            } else {
                return;
            }

            _rmEtaInFlight = true;
            _rmEtaLastFetch = now;

            fetch('https://router.project-osrm.org/route/v1/driving/' +
                    driverLng + ',' + driverLat + ';' +
                    destLng + ',' + destLat + '?overview=false')
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    _rmEtaInFlight = false;
                    if (!data.routes || !data.routes[0]) return;
                    var route = data.routes[0];
                    var etaEl = document.getElementById('driReqETA');
                    if (etaEl) etaEl.innerHTML =
                        '<i class="fa fa-route" style="color:#f59e0b;width:13px;"></i>' +
                        ' <strong style="color:#f59e0b;">' + _rmFmtDuration(route.duration) + '</strong>' +
                        ' <span style="color:rgba(255,255,255,.3);">·</span>' +
                        ' <span style="color:rgba(255,255,255,.4);">' + _rmFmtDistance(route.distance) + ' to ' +
                        destLabel + '</span>';
                })
                .catch(function() {
                    _rmEtaInFlight = false;
                });
        }

        /* ── Route builder — fetches OSRM directly, draws as plain L.polyline ───────── */
        function _rmBuildRoute(status, driverLat, driverLng) {
            if (!_rm) return;
            if (_rmRouteFetching) return; // don't overlap requests

            // Remove any old routing control (legacy cleanup)
            if (_rmRouting) {
                try {
                    _rmRouting.remove();
                } catch (e) {}
                _rmRouting = null;
            }

            var r = _rmCurrentReq;
            if (!r) return;
            var fromLat, fromLng, toLat, toLng;

            if ((status === '8' || status === '2' || status === '3' || status === '4') && driverLat && r.pickup_lat) {
                fromLat = driverLat;
                fromLng = driverLng;
                toLat = r.pickup_lat;
                toLng = r.pickup_lng;
            } else if (status === '5' && driverLat && r.hospital_lat) {
                fromLat = driverLat;
                fromLng = driverLng;
                toLat = r.hospital_lat;
                toLng = r.hospital_lng;
            } else if (r.pickup_lat && r.hospital_lat) {
                fromLat = r.pickup_lat;
                fromLng = r.pickup_lng;
                toLat = r.hospital_lat;
                toLng = r.hospital_lng;
            } else {
                return;
            }

            _rmRouteFetching = true;

            fetch('https://router.project-osrm.org/route/v1/driving/' +
                    fromLng + ',' + fromLat + ';' +
                    toLng + ',' + toLat +
                    '?overview=full&geometries=geojson')
                .then(function(res) {
                    return res.json();
                })
                .then(function(data) {
                    _rmRouteFetching = false;
                    if (!_rm || !data.routes || !data.routes[0]) return;
                    // Convert OSRM [lng,lat] → Leaflet [lat,lng]
                    _rmRouteCoords = data.routes[0].geometry.coordinates.map(function(c) {
                        return [c[1], c[0]];
                    });
                    _rmRouteProgress = 0;
                    // Fast-forward progress to closest point and redraw — _rmAdvanceProgress
                    // calls _rmRedrawRoute(driverLat, driverLng) internally so the blue line
                    // is anchored to the driver's GPS position from the very first draw.
                    if (driverLat) {
                        _rmAdvanceProgress(driverLat, driverLng, true);
                    } else {
                        _rmRedrawRoute();
                    }
                    console.log('[ReqMap] Route built (' + _rmRouteCoords.length + ' pts, status=' + status + ')');
                })
                .catch(function(e) {
                    _rmRouteFetching = false;
                    console.warn('[ReqMap] OSRM fetch failed:', e);
                });
        }

        /* ── Redraw only the remaining (ahead) portion of the route in blue ────────── */
        // driverLat/driverLng: when provided, the blue line is anchored to start at the
        // driver's exact GPS position so it never diverges from the marker visually.
        function _rmRedrawRoute(driverLat, driverLng) {
            if (!_rm || !_rmRouteCoords.length) return;
            var remaining = _rmRouteCoords.slice(Math.max(0, _rmRouteProgress));
            if (remaining.length < 2) {
                remaining = _rmRouteCoords.slice();
            }
            // Anchor the start of the blue line to the driver's live GPS coordinate.
            // This eliminates the visual gap between the marker and the route polyline
            // that otherwise appears because OSRM nodes never sit at the exact GPS pos.
            if (driverLat !== undefined && driverLng !== undefined) {
                remaining = [
                    [driverLat, driverLng]
                ].concat(remaining);
            }
            if (_rmRouteLine) {
                _rmRouteLine.setLatLngs(remaining);
            } else {
                _rmRouteLine = L.polyline(remaining, {
                    color: '#1d4ed8',
                    weight: 5,
                    opacity: 0.85,
                    lineJoin: 'round',
                    lineCap: 'round',
                }).addTo(_rm);
            }
        }

        /* ── Advance the progress pointer to the closest route point to driver ──────── */
        // lat/lng are always the driver's actual GPS position and are forwarded to
        // _rmRedrawRoute so the blue line is anchored at that exact point.
        function _rmAdvanceProgress(lat, lng, fullSearch) {
            if (!_rmRouteCoords.length) return;
            var searchStart = fullSearch ? 0 : Math.max(0, _rmRouteProgress);
            var searchEnd = Math.min(_rmRouteCoords.length, searchStart + (fullSearch ? _rmRouteCoords.length : 60));
            var best = _rmRouteProgress,
                bestDist = Infinity;
            for (var i = searchStart; i < searchEnd; i++) {
                var d = _rmHaversine(lat, lng, _rmRouteCoords[i][0], _rmRouteCoords[i][1]);
                if (d < bestDist) {
                    bestDist = d;
                    best = i;
                }
            }
            if (best > _rmRouteProgress || fullSearch) {
                _rmRouteProgress = best;
                _rmRedrawRoute(lat, lng);
            }
        }

        /* ── Check route deviation every 5 s and recalculate if needed ─────────────── */
        function _rmCheckAndRecalculate() {
            if (!_rmCurrentReq) return;
            var status = _rmCurrentReq.status;
            if (!['8', '2', '3', '4', '5'].includes(status)) return;

            // Resolve current driver position.
            // Prefer window._driCurrentPos (raw GPS broadcast) over the animated marker
            // position — the marker may be mid-tween and not yet at the real coordinate.
            var posLat, posLng;
            if (window._driCurrentPos) {
                posLat = window._driCurrentPos.lat;
                posLng = window._driCurrentPos.lng;
            } else if (_rmDriverMarker) {
                var mp = _rmDriverMarker.getLatLng();
                posLat = mp.lat;
                posLng = mp.lng;
            } else {
                return; // No position available yet
            }

            // No route yet — build one now
            if (!_rmRouteCoords.length) {
                _rmBuildRoute(status, posLat, posLng);
                return;
            }

            // Cooldown: never recalculate more than once every 8 seconds
            var now = Date.now();
            if (now - _rmLastRecalcTs < 8000) return;

            // Search a window around current progress for the closest route point
            var searchStart = Math.max(0, _rmRouteProgress - 5);
            var searchEnd = Math.min(_rmRouteCoords.length, _rmRouteProgress + 80);
            var minDist = Infinity;
            for (var i = searchStart; i < searchEnd; i++) {
                var d = _rmHaversine(posLat, posLng, _rmRouteCoords[i][0], _rmRouteCoords[i][1]);
                if (d < minDist) minDist = d;
            }

            if (minDist > 120) {
                // Driver is off-route — recalculate immediately from current position
                console.log('[ReqMap] Deviation ' + Math.round(minDist) + 'm — recalculating route');
                _rmLastRecalcTs = now;
                _rmRouteCoords = [];
                _rmRouteProgress = 0;
                if (_rmRouteLine) {
                    _rmRouteLine.remove();
                    _rmRouteLine = null;
                }
                _rmBuildRoute(status, posLat, posLng);
            }
        }

        /* ── Transport live panel ───────────────────────────────────────────────────── */
        function _rmBuildTransportPanel() {
            return '<div class="transport-panel" id="driTransportPanel">' +
                '<div class="transport-panel__header">' +
                '<span class="transport-panel__dot"></span>' +
                '<span class="transport-panel__title">TRANSPORTING</span>' +
                '<span class="transport-panel__passenger">' +
                '<i class="fa fa-person" style="margin-right:4px;color:#f59e0b;"></i>Passenger is in Ambulance' +
                '</span>' +
                '</div>' +
                '<div class="transport-stats">' +
                '<div class="transport-stat"><div class="transport-stat__lbl">Speed</div>' +
                '<div class="transport-stat__val" id="driTsSpeed">—<span> km/h</span></div></div>' +
                '<div class="transport-stat"><div class="transport-stat__lbl">To Hospital</div>' +
                '<div class="transport-stat__val" id="driTsDist">—<span></span></div></div>' +
                '<div class="transport-stat"><div class="transport-stat__lbl">ETA</div>' +
                '<div class="transport-stat__val" id="driTsEta">—<span></span></div></div>' +
                '<div class="transport-stat"><div class="transport-stat__lbl">Traveled</div>' +
                '<div class="transport-stat__val" id="driTsTraveled">0<span> pts</span></div></div>' +
                '</div>' +
                '</div>';
        }

        function _rmUpdateTransportPanel(lat, lng) {
            var spdEl = document.getElementById('driTsSpeed');
            var distEl = document.getElementById('driTsDist');
            var etaEl = document.getElementById('driTsEta');
            var trvEl = document.getElementById('driTsTraveled');

            if (spdEl) spdEl.innerHTML = Math.round(_rmCurrentSpeed) + '<span> km/h</span>';

            if (distEl && _rmCurrentReq && _rmCurrentReq.hospital_lat) {
                var distM = _rmHaversine(lat, lng, _rmCurrentReq.hospital_lat, _rmCurrentReq.hospital_lng);
                distEl.innerHTML = _rmFmtDistance(distM) + '<span></span>';
            }

            if (trvEl) trvEl.innerHTML = _rmTraveledPoints.length + '<span> pts</span>';

            // ETA — throttled via OSRM (reuse _rmFetchETA)
            if (_rmCurrentReq && _rmCurrentReq.hospital_lat) {
                var now2 = Date.now();
                if (!_rmEtaInFlight && now2 - _rmEtaLastFetch >= ETA_INTERVAL_MS) {
                    _rmEtaInFlight = true;
                    _rmEtaLastFetch = now2;
                    fetch('https://router.project-osrm.org/route/v1/driving/' +
                            lng + ',' + lat + ';' +
                            _rmCurrentReq.hospital_lng + ',' + _rmCurrentReq.hospital_lat + '?overview=false')
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            _rmEtaInFlight = false;
                            if (!data.routes || !data.routes[0]) return;
                            var etaEl2 = document.getElementById('driTsEta');
                            if (etaEl2) etaEl2.innerHTML = _rmFmtDuration(data.routes[0].duration) + '<span></span>';
                        })
                        .catch(function() {
                            _rmEtaInFlight = false;
                        });
                }
            }
        }

        /* ── Live tracking badge builder ───────────────────────────────────────────── */
        function _rmBuildTrackingBadge(status) {
            var isActive = ['8', '2', '3', '4', '5'].includes(status);
            if (!isActive) return '';

            var etaInit;
            if (status === '4') {
                etaInit = '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>' +
                    ' <strong style="color:#22c55e;">At pickup location</strong>';
            } else {
                etaInit = '<i class="fa fa-spinner fa-spin" style="width:13px;color:rgba(255,255,255,.3);"></i>' +
                    ' <span style="color:rgba(255,255,255,.3);">Calculating ETA…</span>';
            }

            return '<div id="driReqTrackBadge" style="background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.22);' +
                'border-radius:12px;padding:12px 14px;margin-bottom:14px;">' +
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
                '<span id="driReqTrackDot" style="width:9px;height:9px;border-radius:50%;background:#22c55e;flex-shrink:0;' +
                'box-shadow:0 0 0 0 rgba(34,197,94,.4);animation:driLmPulse 1.8s infinite;display:inline-block;"></span>' +
                '<span style="font-size:.73rem;font-weight:700;color:#22c55e;letter-spacing:.05em;">LIVE TRACKING</span>' +
                '<span style="font-size:.67rem;color:rgba(255,255,255,.28);margin-left:auto;white-space:nowrap;" id="driReqTrackTime">' +
                '<i class="fa fa-clock" style="width:12px;"></i> Waiting…' +
                '</span>' +
                '</div>' +
                '<div style="font-size:.71rem;color:rgba(255,255,255,.38);margin-bottom:6px;" id="driReqTrackCoords">' +
                '<i class="fa fa-location-dot" style="color:#22c55e;width:13px;"></i> Acquiring location…' +
                '</div>' +
                '<div style="font-size:.73rem;" id="driReqETA">' + etaInit + '</div>' +
                '</div>';
        }

        /* ── Update live badge coords/time ─────────────────────────────────────────── */
        function _rmUpdateBadge(lat, lng) {
            var coordEl = document.getElementById('driReqTrackCoords');
            var timeEl = document.getElementById('driReqTrackTime');
            if (coordEl) coordEl.innerHTML =
                '<i class="fa fa-location-dot" style="color:#22c55e;width:13px;"></i> ' +
                parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5);
            if (timeEl) timeEl.innerHTML =
                '<i class="fa fa-clock" style="width:12px;"></i> ' + new Date().toLocaleTimeString();
        }

        /* ── Move driver marker smoothly ───────────────────────────────────────────── */
        function _rmMoveDriver(lat, lng) {
            if (!_rm) return;

            // Calculate speed from previous position
            var now = Date.now();
            if (_rmPrevPos && _rmPrevPosTs) {
                var timeDelta = (now - _rmPrevPosTs) / 1000;
                if (timeDelta > 0.5) {
                    var dist = _rmHaversine(lat, lng, _rmPrevPos.lat, _rmPrevPos.lng);
                    if (dist > 0.5) {
                        _rmCurrentSpeed = (dist / timeDelta) * 3.6;
                    }
                }
            }
            _rmPrevPos = {
                lat: lat,
                lng: lng
            };
            _rmPrevPosTs = now;

            // Update traveled path — only show grey trail when actively En Route or Transporting
            // (not during Dispatched or Arrived states where a trailing line would be misleading)
            var _curStatus = _rmCurrentReq ? _rmCurrentReq.status : null;
            if (_curStatus === '3' || _curStatus === '5') {
                if (_rmTraveledPoints.length === 0 ||
                    _rmHaversine(lat, lng,
                        _rmTraveledPoints[_rmTraveledPoints.length - 1][0],
                        _rmTraveledPoints[_rmTraveledPoints.length - 1][1]) > 8) {
                    _rmTraveledPoints.push([lat, lng]);
                    if (_rmTraveledLine) {
                        _rmTraveledLine.setLatLngs(_rmTraveledPoints);
                    } else if (_rmTraveledPoints.length >= 2) {
                        _rmTraveledLine = L.polyline(_rmTraveledPoints, {
                            color: '#64748b',
                            weight: 4,
                            opacity: 0.65,
                            dashArray: '6 4',
                        }).addTo(_rm);
                    }
                }
            }

            // Advance route progress — hides the traveled blue section
            _rmAdvanceProgress(lat, lng, false);

            // Move ambulance marker — animate position but DO NOT recreate the icon
            // (setIcon tears down/rebuilds the DOM element, causing flicker on zoom)
            if (_rmDriverMarker) {
                _rmAnimateTo('driver', _rmDriverMarker, lat, lng);
            } else {
                _rmDriverMarker = L.marker([lat, lng], {
                    icon: _rmAmbulanceIcon(true),
                    zIndexOffset: 1000,
                }).bindPopup('<b>🚑 Your Location (Live)</b>').addTo(_rm);
                _rmMarkerIconLive = true;
            }

            _rmUpdateBadge(lat, lng);

            // Update the appropriate panel based on status
            if (_rmCurrentReq && _rmCurrentReq.status === '5') {
                _rmUpdateTransportPanel(lat, lng);
            } else {
                _rmFetchETA(lat, lng);
            }
        }

        /* ── Handle Reverb location update ────────────────────────────────────────── */
        function _rmOnLocationUpdated(e) {
            if (!_rmCurrentReq) return;
            var driverId = String(e.driver_id);
            var myId = String(window._rrReverb ? window._rrReverb.driverId : '');
            if (driverId !== myId) return;

            var lat = parseFloat(e.lat),
                lng = parseFloat(e.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            // If the local GPS fired recently (< 10 s ago), the Reverb broadcast is a
            // round-trip-delayed echo of an older position. Using it would move the
            // marker backward in time and pollute the grey trail with a stale point.
            // Defer to the GPS in that case; use Reverb only when GPS is unavailable.
            var gpsAge = Date.now() - _rmLastGpsFix;
            if (_rmLastGpsFix > 0 && gpsAge < 10000) {
                console.log('[ReqMap] Reverb loc skipped — GPS fresh (' + Math.round(gpsAge / 1000) + 's)');
                return;
            }

            window._driCurrentPos = {
                lat: lat,
                lng: lng,
                ts: Date.now()
            };
            _rmMoveDriver(lat, lng);
            console.log('[ReqMap] Location update from Reverb (GPS age ' + Math.round(gpsAge / 1000) + 's):', lat, lng);
        }

        /* ── Handle Reverb status update ───────────────────────────────────────────── */
        function _rmOnStatusUpdated(e) {
            var newStatus = String(e.status);

            // ── Always update the table row badge ─────────────────────────────────────
            // This runs regardless of whether a modal is open, so table badges stay
            // live even when the driver is browsing the requests list without a modal.
            var badge = document.getElementById('reqStatusBadge_' + e.request_id);
            if (badge) {
                badge.className = 'status-pill ' + (_statusClass[newStatus] || 's1');
                badge.textContent = _statusLabels[newStatus] || newStatus;
            }

            // ── Modal-specific updates — only if THIS request is currently open ───────
            if (!_rmCurrentReq || e.request_id !== _rmCurrentReq.id) return;

            _rmCurrentReq.status = newStatus;

            // Update status badge in modal header
            var modalBadge = document.getElementById('reqModalStatusBadge');
            if (modalBadge) {
                modalBadge.className = 'status-pill ' + (_statusClass[newStatus] || 's1');
                modalBadge.textContent = _statusLabels[newStatus] || newStatus;
            }

            // Rebuild route for new status
            var driverLat = null,
                driverLng = null;
            if (_rmDriverMarker) {
                var pos = _rmDriverMarker.getLatLng();
                driverLat = pos.lat;
                driverLng = pos.lng;
            } else if (window._driCurrentPos) {
                driverLat = window._driCurrentPos.lat;
                driverLng = window._driCurrentPos.lng;
            }
            // On transition to En Route: clear traveled state + reset route for a clean start
            if (newStatus === '3') {
                if (_rmTraveledLine) {
                    _rmTraveledLine.remove();
                    _rmTraveledLine = null;
                }
                _rmTraveledPoints = [];
                if (_rmRouteLine) {
                    _rmRouteLine.remove();
                    _rmRouteLine = null;
                }
                _rmRouteCoords = [];
                _rmRouteProgress = 0;
                _rmLastRecalcTs = 0;
            }

            _rmBuildRoute(newStatus, driverLat, driverLng);

            // Status 5 (Transporting): hide pickup marker, show transport panel
            if (newStatus === '5') {
                if (_rmPickupMarker) {
                    _rmPickupMarker.remove();
                    _rmPickupMarker = null;
                }
                var trackBadge5 = document.getElementById('driReqTrackBadge');
                if (trackBadge5) {
                    trackBadge5.outerHTML = _rmBuildTransportPanel();
                }
                if (driverLat) _rmUpdateTransportPanel(driverLat, driverLng);
            }

            // Update ETA badge for status 4 (arrived)
            if (newStatus === '4') {
                var etaEl = document.getElementById('driReqETA');
                if (etaEl) etaEl.innerHTML = '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>' +
                    ' <strong style="color:#22c55e;">At pickup location</strong>';
            }

            // Rebuild action panel buttons for new status (status 2 cannot cancel after acceptance)
            var newAllowed = (function(s) {
                return {
                    '2': ['3'],
                    '3': ['4'],
                    '4': ['5'],
                    '5': ['6']
                } [s] || [];
            })(newStatus);
            _rmCurrentReq.allowed_next = newAllowed;
            _rmRebuildActionPanel(newStatus, newAllowed);

            // Update details-grid Status field
            var detailStatusEl = document.getElementById('reqDetailStatus');
            if (detailStatusEl) detailStatusEl.textContent = _statusLabels[newStatus] || newStatus;

            // Update marker icon on status change (only here — NOT on every position tick)
            if (_rmDriverMarker) {
                var wantLive = (newStatus !== '6' && newStatus !== '7');
                if (wantLive !== _rmMarkerIconLive) {
                    _rmMarkerIconLive = wantLive;
                    _rmDriverMarker.setIcon(_rmAmbulanceIcon(wantLive));
                }
            }

            // Stop deviation timer + fade badge on complete/cancel
            if (newStatus === '6' || newStatus === '7') {
                if (_rmDeviationTimer) {
                    clearInterval(_rmDeviationTimer);
                    _rmDeviationTimer = null;
                }
                // Clear the route line — ride is done
                if (_rmRouteLine) {
                    _rmRouteLine.remove();
                    _rmRouteLine = null;
                }
                _rmRouteCoords = [];
                _rmRouteProgress = 0;
                var trackBadge = document.getElementById('driReqTrackBadge');
                var tpanel = document.getElementById('driTransportPanel');
                var activePanel = trackBadge || tpanel;
                if (activePanel) {
                    activePanel.style.background = 'rgba(100,116,139,.06)';
                    activePanel.style.borderColor = 'rgba(100,116,139,.15)';
                }
                var dot = document.getElementById('driReqTrackDot');
                if (dot) {
                    dot.style.background = '#64748b';
                    dot.style.animation = 'none';
                }
                var tpdot = document.querySelector('.transport-panel__dot');
                if (tpdot) {
                    tpdot.style.background = '#64748b';
                    tpdot.style.animation = 'none';
                }
            }

            console.log('[ReqMap] Status updated to', newStatus, 'for request', e.request_id);
        }

        /* ── Subscribe to Reverb contact.admin channel ─────────────────────────────── */
        // Called both on DOMContentLoaded (for persistent table-badge updates) and
        // on modal open (to also attach driver.location.updated for the map).
        // withLocation=true when a map modal is open; false for background badge-only mode.
        function _rmSubscribeReverb(withLocation) {
            var pusher = window._driPusher;
            if (!pusher) {
                // Pusher not ready yet — retry in 1s
                setTimeout(function() { _rmSubscribeReverb(withLocation); }, 1000);
                return;
            }

            if (!_rmAdminChannel) {
                // First subscription — bind both handlers
                _rmAdminChannel = pusher.subscribe('contact.admin');
                _rmAdminChannel.bind('request.status.updated', _rmOnStatusUpdated);
                console.log('[ReqMap] Subscribed to contact.admin for live badge/status updates');
            }

            // (Re-)attach location updates whenever a map modal opens
            if (withLocation) {
                _rmAdminChannel.unbind('driver.location.updated', _rmOnLocationUpdated);
                _rmAdminChannel.bind('driver.location.updated', _rmOnLocationUpdated);
                console.log('[ReqMap] driver.location.updated bound for modal map');
            }
        }

        /* ── Geolocation watch for driver's own position ───────────────────────────── */
        function _rmStartGeoWatch() {
            // Always start the deviation/recalculation timer — works via Reverb even without GPS
            if (_rmDeviationTimer) clearInterval(_rmDeviationTimer);
            _rmDeviationTimer = setInterval(_rmCheckAndRecalculate, 5000);

            if (!navigator.geolocation) return;
            if (_rmGeoWatch !== null) return;

            // Seed the marker from the last cached position (if recent enough) so the
            // map isn't empty while waiting for the first GPS fix.
            // IMPORTANT: we do NOT call _rmMoveDriver here — that would push a potentially
            // stale coordinate into _rmTraveledPoints, causing a false "curve" in the grey
            // trail when the first real GPS fix arrives at the actual current location.
            if (window._driCurrentPos && (Date.now() - (window._driCurrentPos.ts || 0)) < 30000) {
                var seedLat = window._driCurrentPos.lat,
                    seedLng = window._driCurrentPos.lng;
                if (!_rmDriverMarker) {
                    _rmDriverMarker = L.marker([seedLat, seedLng], {
                        icon: _rmAmbulanceIcon(true),
                        zIndexOffset: 1000,
                    }).bindPopup('<b>🚑 Your Location (Live)</b>').addTo(_rm);
                    _rmMarkerIconLive = true;
                } else {
                    _rmDriverMarker.setLatLng([seedLat, seedLng]);
                }
                _rmUpdateBadge(seedLat, seedLng);
                // Build the initial route from the cached position; the first GPS fix
                // will trigger a recalculation if the driver has moved significantly.
                _rmBuildRoute(_rmCurrentReq.status, seedLat, seedLng);
            }

            _rmGeoWatch = navigator.geolocation.watchPosition(
                function(pos) {
                    if (!_rm) return;
                    var lat = pos.coords.latitude,
                        lng = pos.coords.longitude;
                    _rmLastGpsFix = Date.now();
                    window._driCurrentPos = {
                        lat: lat,
                        lng: lng,
                        ts: _rmLastGpsFix
                    };
                    _rmMoveDriver(lat, lng);

                    // If no route has been drawn yet, build one now from the real GPS fix
                    if (!_rmRouteCoords.length && _rmCurrentReq) {
                        _rmBuildRoute(_rmCurrentReq.status, lat, lng);
                    }
                },
                function(err) {
                    console.info('[ReqMap] Geolocation error:', err.message);
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 3000
                }
            );
        }

        function _rmStopGeoWatch() {
            if (_rmGeoWatch !== null && navigator.geolocation) {
                navigator.geolocation.clearWatch(_rmGeoWatch);
                _rmGeoWatch = null;
            }
            if (_rmDeviationTimer) {
                clearInterval(_rmDeviationTimer);
                _rmDeviationTimer = null;
            }
        }

        /* ── Status action button config ────────────────────────────────────────────── */
        var _actCfg = {
            '3': {
                cls: 'enroute',
                icon: 'fa-truck-fast',
                label: 'En Route'
            },
            '4': {
                cls: 'arrived',
                icon: 'fa-location-dot',
                label: 'Mark Arrived'
            },
            '5': {
                cls: 'transport',
                icon: 'fa-person-running',
                label: 'Begin Transport'
            },
            '6': {
                cls: 'complete',
                icon: 'fa-circle-check',
                label: 'Complete'
            },
            '7': {
                cls: 'cancel',
                icon: 'fa-xmark',
                label: 'Cancel Request'
            },
        };

        /* ── Build the action panel HTML ─────────────────────────────────────────────── */
        function _rmBuildActionPanel(status, allowedNext, reqId) {
            // Special case: Awaiting Acceptance — show Accept / Reject buttons
            if (status === '8') {
                return '<div class="req-action-panel" id="reqActionPanel">' +
                    '<div class="req-action-panel__title">' +
                    '<i class="fa fa-clock"></i> Driver Response Required' +
                    '</div>' +
                    '<div class="req-action-btns">' +
                    '<button class="req-act-btn enroute" id="reqActBtn_accept" ' +
                    'onclick="driAcceptDispatch(' + reqId + ')">' +
                    '<i class="fa fa-check"></i> Accept Request' +
                    '</button>' +
                    '<button class="req-act-btn cancel" id="reqActBtn_reject" ' +
                    'style="margin-left:auto;" ' +
                    'onclick="driRejectDispatch(' + reqId + ')">' +
                    '<i class="fa fa-times"></i> Reject' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            }

            if (!allowedNext || !allowedNext.length) return '';

            var btns = allowedNext.map(function(next) {
                var cfg = _actCfg[next];
                if (!cfg) return '';
                return '<button class="req-act-btn ' + cfg.cls + '" ' +
                    'id="reqActBtn_' + next + '" ' +
                    'onclick="updateRequestStatus(' + reqId + ',\'' + next + '\')">' +
                    '<i class="fa ' + cfg.icon + '"></i> ' + cfg.label +
                    '</button>';
            }).join('');

            return '<div class="req-action-panel" id="reqActionPanel">' +
                '<div class="req-action-panel__title">' +
                '<i class="fa fa-sliders"></i> Update Status' +
                '</div>' +
                '<div class="req-action-btns">' + btns + '</div>' +
                '</div>';
        }

        /* ── Accept dispatch from requests page ──────────────────────────────────────── */
        function driAcceptDispatch(id) {
            var acceptBtn = document.getElementById('reqActBtn_accept');
            var rejectBtn = document.getElementById('reqActBtn_reject');
            if (acceptBtn) {
                acceptBtn.disabled = true;
                acceptBtn.innerHTML = '<span class="btn-spin"></span> Accepting…';
            }
            if (rejectBtn) {
                rejectBtn.disabled = true;
            }

            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            var url = window._driRoutes.requestAccept.replace(':id', id);

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({}),
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (!data.success) {
                        if (acceptBtn) {
                            acceptBtn.disabled = false;
                            acceptBtn.innerHTML = '<i class="fa fa-check"></i> Accept Request';
                        }
                        if (rejectBtn) {
                            rejectBtn.disabled = false;
                        }
                        alert(data.message || 'Failed to accept.');
                        return;
                    }
                    // Update local cache
                    var idx = _reqData.findIndex(function(r) {
                        return r.id === id;
                    });
                    if (idx !== -1 && data.request) {
                        _reqData[idx] = data.request;
                        _rmCurrentReq = data.request;
                    }
                    // Rebuild action panel and status badge
                    var newStatus = data.status || '2';
                    var newLabel = data.status_label || 'Dispatched';
                    var newAllowed = (data.request && data.request.allowed_next) ? data.request.allowed_next : ['3'];
                    _rmRebuildActionPanel(newStatus, newAllowed);
                    var badge = document.getElementById('reqStatusBadge_' + id);
                    if (badge) {
                        badge.className = 'status-pill ' + (_statusClass[newStatus] || 's2');
                        badge.textContent = newLabel;
                    }
                    var detBadge = document.getElementById('reqDetailStatus');
                    if (detBadge) detBadge.textContent = newLabel;
                    // Sync dashboard tab + stats
                    if (typeof driLoadActive === 'function') driLoadActive();
                    if (typeof driRefreshStats === 'function') driRefreshStats();
                    if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({
                        type: 'active_refresh'
                    });
                    // Badge must NOT be decremented on accept — ride is still active.
                    // Badge will only decrease when the ride is Completed or Cancelled.
                })
                .catch(function() {
                    if (acceptBtn) {
                        acceptBtn.disabled = false;
                        acceptBtn.innerHTML = '<i class="fa fa-check"></i> Accept Request';
                    }
                    if (rejectBtn) {
                        rejectBtn.disabled = false;
                    }
                    alert('Server error.');
                });
        }

        /* ── Reject dispatch — show confirmation modal first ─────────────────────────── */
        var _driPendingRejectId = null;

        function driRejectDispatch(id) {
            _driPendingRejectId = id;
            var el = document.getElementById('driRejectConfirmModal');
            if (!el) return;

            // Use shown.bs.modal (fires after Bootstrap finishes inserting the backdrop)
            // so the z-index bump is guaranteed to hit the correct elements.
            el.addEventListener('shown.bs.modal', function onShown() {
                el.removeEventListener('shown.bs.modal', onShown);
                el.style.zIndex = '1080';
                // The last backdrop in the DOM belongs to this modal
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 0) {
                    backdrops[backdrops.length - 1].style.zIndex = '1075';
                }
            });

            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        /* ── Confirmed reject — runs after driver taps "Confirm Reject" ──────────────── */
        function driConfirmReject() {
            var id = _driPendingRejectId;
            if (!id) return;

            // Close confirmation modal
            var confirmEl = document.getElementById('driRejectConfirmModal');
            if (confirmEl) {
                var cm = bootstrap.Modal.getInstance(confirmEl);
                if (cm) cm.hide();
            }

            // Disable action buttons in the open detail view
            var rejectBtn = document.getElementById('reqActBtn_reject');
            var acceptBtn = document.getElementById('reqActBtn_accept');
            if (rejectBtn) {
                rejectBtn.disabled = true;
                rejectBtn.innerHTML = '<span class="btn-spin"></span> Rejecting…';
            }
            if (acceptBtn) {
                acceptBtn.disabled = true;
            }

            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            var url = window._driRoutes.requestReject.replace(':id', id);

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({}),
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    _driPendingRejectId = null;

                    if (!data.success) {
                        if (rejectBtn) {
                            rejectBtn.disabled = false;
                            rejectBtn.innerHTML = '<i class="fa fa-times"></i> Reject';
                        }
                        if (acceptBtn) {
                            acceptBtn.disabled = false;
                        }
                        alert(data.message || 'Failed to reject.');
                        return;
                    }

                    // ── Close detail modal ────────────────────────────────────────────────
                    var detailEl = document.getElementById('reqDetailModal');
                    if (detailEl) {
                        var dm = bootstrap.Modal.getInstance(detailEl);
                        if (dm) dm.hide();
                    }

                    // ── Remove from local cache ───────────────────────────────────────────
                    _reqData = _reqData.filter(function(r) {
                        return r.id !== id && r.id !== parseInt(id, 10);
                    });

                    // ── Animate-remove grid row ───────────────────────────────────────────
                    _driAnimateRemoveRow(id);

                    // ── Refresh active/dashboard panel ────────────────────────────────────
                    if (typeof driLoadActive === 'function') driLoadActive();
                    // Decrement nav badge — request acknowledged via reject
                    if (typeof window.driDecrementRequestBadge === 'function') window.driDecrementRequestBadge();
                })
                .catch(function() {
                    _driPendingRejectId = null;
                    if (rejectBtn) {
                        rejectBtn.disabled = false;
                        rejectBtn.innerHTML = '<i class="fa fa-times"></i> Reject';
                    }
                    if (acceptBtn) {
                        acceptBtn.disabled = false;
                    }
                    alert('Server error. Please try again.');
                });
        }

        /* ── Rebuild panel in-place after status change ──────────────────────────────── */
        function _rmRebuildActionPanel(newStatus, newAllowed) {
            var panel = document.getElementById('reqActionPanel');
            if (!panel || !_rmCurrentReq) return;
            var newHtml = _rmBuildActionPanel(newStatus, newAllowed, _rmCurrentReq.id);
            if (newHtml) {
                panel.outerHTML = newHtml;
            } else {
                panel.outerHTML = '<div class="req-action-panel" id="reqActionPanel" style="text-align:center;">' +
                    '<span style="font-size:.78rem;color:rgba(255,255,255,.28);">' +
                    '<i class="fa fa-circle-check me-1" style="color:rgba(34,197,94,.4);"></i>' +
                    (_statusLabels[newStatus] === 'Completed' ? 'Request completed successfully.' : 'This request is ' + (
                        _statusLabels[newStatus] || 'closed') + '.') +
                    '</span></div>';
            }
        }

        /* ── API call: update request status ─────────────────────────────────────────── */
        function updateRequestStatus(id, newStatus) {
            var btn = document.getElementById('reqActBtn_' + newStatus);
            var cfg = _actCfg[newStatus];
            if (!cfg) return;

            // Disable all action buttons to prevent double-tap
            document.querySelectorAll('.req-act-btn').forEach(function(b) {
                b.disabled = true;
            });
            if (btn) {
                btn.innerHTML = '<span class="btn-spin"></span> Updating…';
            }

            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            var url = window._driRoutes.requestStatus.replace(':id', id);

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        status: newStatus
                    }),
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (!data.success) {
                        // Re-enable on failure
                        document.querySelectorAll('.req-act-btn').forEach(function(b) {
                            b.disabled = false;
                        });
                        if (btn && cfg) btn.innerHTML = '<i class="fa ' + cfg.icon + '"></i> ' + cfg.label;
                        alert(data.message || 'Status update failed.');
                        return;
                    }

                    var ns = String(data.status);
                    var nsLbl = data.status_label || _statusLabels[ns] || ns;
                    var allowed = (data.request && data.request.allowed_next) ? data.request.allowed_next : [];

                    // Update in-memory data
                    var idx = _reqData.findIndex(function(x) {
                        return x.id === id;
                    });
                    if (idx !== -1) {
                        _reqData[idx].status = ns;
                        _reqData[idx].allowed_next = allowed;
                        if (data.request && data.request.completed_at) _reqData[idx].completed_at = data.request
                            .completed_at;
                    }
                    if (_rmCurrentReq && _rmCurrentReq.id === id) {
                        _rmCurrentReq.status = ns;
                        _rmCurrentReq.allowed_next = allowed;
                    }

                    // Update modal status badge
                    var modalBadge = document.getElementById('reqModalStatusBadge');
                    if (modalBadge) {
                        modalBadge.className = 'status-pill ' + (_statusClass[ns] || 's1');
                        modalBadge.textContent = nsLbl;
                    }

                    // Update table row badge
                    var rowBadge = document.getElementById('reqStatusBadge_' + id);
                    if (rowBadge) {
                        rowBadge.className = 'status-pill ' + (_statusClass[ns] || 's1');
                        rowBadge.textContent = nsLbl;
                    }

                    // Update details row 'Status' field
                    var detailStatus = document.getElementById('reqDetailStatus');
                    if (detailStatus) detailStatus.textContent = nsLbl;

                    // Rebuild action panel
                    _rmRebuildActionPanel(ns, allowed);

                    // Rebuild map route
                    var dLat = null,
                        dLng = null;
                    if (_rm) {
                        if (_rmDriverMarker) {
                            var p = _rmDriverMarker.getLatLng();
                            dLat = p.lat;
                            dLng = p.lng;
                        } else if (window._driCurrentPos) {
                            dLat = window._driCurrentPos.lat;
                            dLng = window._driCurrentPos.lng;
                        }
                        // On transition to En Route: clear traveled state + reset route for a clean start
                        if (ns === '3') {
                            if (_rmTraveledLine) {
                                _rmTraveledLine.remove();
                                _rmTraveledLine = null;
                            }
                            _rmTraveledPoints = [];
                            if (_rmRouteLine) {
                                _rmRouteLine.remove();
                                _rmRouteLine = null;
                            }
                            _rmRouteCoords = [];
                            _rmRouteProgress = 0;
                            _rmLastRecalcTs = 0;
                        }
                        _rmBuildRoute(ns, dLat, dLng);
                    }

                    // Status 5 (Transporting): hide pickup marker, show transport panel
                    if (ns === '5') {
                        if (_rmPickupMarker) {
                            _rmPickupMarker.remove();
                            _rmPickupMarker = null;
                        }
                        var trackBadge5b = document.getElementById('driReqTrackBadge');
                        if (trackBadge5b) {
                            trackBadge5b.outerHTML = _rmBuildTransportPanel();
                        }
                        if (dLat) _rmUpdateTransportPanel(dLat, dLng);
                    }

                    // ETA for status 4 (arrived)
                    if (ns === '4') {
                        var etaEl2 = document.getElementById('driReqETA');
                        if (etaEl2) etaEl2.innerHTML =
                            '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>' +
                            ' <strong style="color:#22c55e;">At pickup location</strong>';
                    }

                    // Stop deviation timer + fade panels on complete/cancel
                    if (ns === '6' || ns === '7') {
                        if (_rmDeviationTimer) {
                            clearInterval(_rmDeviationTimer);
                            _rmDeviationTimer = null;
                        }
                        var tb = document.getElementById('driReqTrackBadge');
                        var tpb = document.getElementById('driTransportPanel');
                        var act = tb || tpb;
                        if (act) {
                            act.style.background = 'rgba(100,116,139,.06)';
                            act.style.borderColor = 'rgba(100,116,139,.15)';
                        }
                        var dotEl = document.getElementById('driReqTrackDot');
                        if (dotEl) {
                            dotEl.style.background = '#64748b';
                            dotEl.style.animation = 'none';
                        }
                        var tpdot2 = document.querySelector('.transport-panel__dot');
                        if (tpdot2) {
                            tpdot2.style.background = '#64748b';
                            tpdot2.style.animation = 'none';
                        }
                    }

                    // Move completed/cancelled ride to Past Rides in real-time
                    if (ns === '6' || ns === '7') {
                        // Build full object for past rides page
                        var movedReq = Object.assign({}, (_reqData.find(function(x) {
                            return x.id === id;
                        }) || {}), {
                            id: id,
                            status: ns,
                            status_label: nsLbl,
                            completed_at: (data.request && data.request.completed_at) ? data.request
                                .completed_at : null,
                            time: (data.request && data.request.dispatched_at) ? data.request.dispatched_at :
                                null,
                        });
                        // Broadcast to other tabs (requests page removes row, past rides page adds row)
                        if (typeof driBroadcastTabSync === 'function') {
                            driBroadcastTabSync({
                                type: 'ride_moved_to_past',
                                req: movedReq
                            });
                        }
                        // Remove from in-memory list
                        _reqData = _reqData.filter(function(x) {
                            return x.id !== id;
                        });
                        // Close modal after letting user see final state
                        setTimeout(function() {
                            var modalEl = document.getElementById('reqDetailModal');
                            if (modalEl) {
                                var bsModal = bootstrap.Modal.getInstance(modalEl);
                                if (bsModal) bsModal.hide();
                            }
                        }, 1400);
                        // Animate row out of table
                        setTimeout(function() {
                            _driAnimateRemoveRow(id);
                        }, 1600);
                    }

                    console.log('[ReqMap] Status updated to', ns, '— allowed next:', allowed);
                })
                .catch(function(err) {
                    document.querySelectorAll('.req-act-btn').forEach(function(b) {
                        b.disabled = false;
                    });
                    if (btn && cfg) btn.innerHTML = '<i class="fa ' + cfg.icon + '"></i> ' + cfg.label;
                    console.error('[ReqMap] Status update error:', err);
                });
        }

        /* ── Main viewRequestDetail ─────────────────────────────────────────────────── */
        function viewRequestDetail(id) {
            // Auto-mark the related notification as read the moment this request is opened
            if (typeof window.driMarkReadByRequest === 'function') window.driMarkReadByRequest(id);

            var r = _reqData.find(function(x) {
                return x.id === id;
            });
            // Completed/cancelled — show read-only details without live tracking
            if (r && (r.status === '6' || r.status === '7')) {
                var modal2 = new bootstrap.Modal(document.getElementById('reqDetailModal'));
                var body2 = document.getElementById('reqDetailBody');
                var sLabel2 = _statusLabels[r.status] || r.status;
                var sCls2 = _statusClass[r.status] || 's1';
                var typeLbl2 = r.type === '1' ? 'Emergency' : 'Non-Emergency';
                var typeCls2 = r.type === '1' ? 'emergency' : 'non-emergency';
                body2.innerHTML =
                    '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.07);">' +
                    '<span class="status-pill ' + typeCls2 + '">' + esc(typeLbl2) + '</span>' +
                    '<span class="status-pill ' + sCls2 + '">' + esc(sLabel2) + '</span>' +
                    '<span style="margin-left:auto;font-family:monospace;font-size:.8rem;background:rgba(255,255,255,.06);padding:3px 10px;border-radius:6px;color:rgba(255,255,255,.55);">' +
                    esc(r.rreb_id || '#' + r.id) + '</span>' +
                    '</div>' +
                    '<div class="req-detail-grid">' +
                    detailItem('Patient Mobile', r.mobile_no) +
                    detailItem('Ambulance', r.ambulance_no || '—') +
                    detailItem('Pickup Address', r.pickup_address, true) +
                    detailItem('Hospital / Destination', r.hospital_name, true) +
                    detailItem('Dispatched At', r.dispatched_at || '—') +
                    detailItem('Completed At', r.completed_at || '—') +
                    detailItem('Requested At', r.created_at) +
                    detailItem('Status', sLabel2) +
                    (r.notes ? detailItem('Notes', r.notes, true) : '') +
                    '</div>';
                modal2.show();
                return;
            }
            var modal = new bootstrap.Modal(document.getElementById('reqDetailModal'));
            var body = document.getElementById('reqDetailBody');

            if (!r) {
                body.innerHTML = '<p style="color:rgba(255,255,255,.4);text-align:center;">Details not available.</p>';
                modal.show();
                return;
            }

            _rmCurrentReq = r;

            var typeLabel = r.type === '1' ? 'Emergency' : 'Non-Emergency';
            var typeCls = r.type === '1' ? 'emergency' : 'non-emergency';
            var sLabel = _statusLabels[r.status] || r.status;
            var sCls = _statusClass[r.status] || 's1';

            body.innerHTML =
                // Header row: type + status badges + ID
                '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.07);">' +
                '<span class="status-pill ' + typeCls + '">' + esc(typeLabel) + '</span>' +
                '<span class="status-pill ' + sCls + '" id="reqModalStatusBadge">' + esc(sLabel) + '</span>' +
                '<span style="margin-left:auto;font-family:monospace;font-size:.8rem;background:rgba(255,255,255,.06);' +
                'padding:3px 10px;border-radius:6px;color:rgba(255,255,255,.55);">' +
                esc(r.rreb_id || '#' + r.id) +
                '</span>' +
                '</div>'

                // Map container — must have an explicit height for Leaflet to render
                +
                (r.pickup_lat && r.pickup_lng ? '<div id="driReqDetailMap" style="width:100%;height:360px;border-radius:12px;' + 'margin-top:14px;overflow:hidden;border:1px solid rgba(255,255,255,.08);"></div>' : '')

                // Details grid
                +
                '<div class="req-detail-grid">' +
                detailItem('Patient Mobile', r.mobile_no) +
                detailItem('Ambulance', (r.ambulance_no || '—') + (r.ambulance_type ? ' (' + r.ambulance_type + ')' : '')) +
                detailItem('Pickup Address', r.pickup_address, true) +
                detailItem('Hospital / Destination', r.hospital_name, true) +
                detailItem('Dispatched At', r.dispatched_at || '—') +
                detailItem('Completed At', r.completed_at || '—', false, 'reqDetailCompletedAt') +
                detailItem('Requested At', r.created_at) +
                detailItem('Status', sLabel, false, 'reqDetailStatus') +
                (r.notes ? detailItem('Notes', r.notes, true) : '') +
                '</div>'

                // Status action panel (active requests only)
                +
                _rmBuildActionPanel(r.status, r.allowed_next || [], r.id)

                // Live tracking / transport panel (status-dependent)
                +
                (r.status === '5' ? _rmBuildTransportPanel() : (['8', '2', '3', '4'].includes(r.status) ? _rmBuildTrackingBadge(r.status) : ''));
                
                // Initialise the map only once the modal is fully visible —
                // Leaflet needs a rendered container with non-zero dimensions.
                if (r.pickup_lat && r.pickup_lng) {
                    document.getElementById('reqDetailModal').addEventListener(
                        'shown.bs.modal',
                        function _onMapInit() {
                            document.getElementById('reqDetailModal').removeEventListener('shown.bs.modal', _onMapInit);
                            _rmInitMap(r);
                        }
                    );
                }

            modal.show();
        }

        /* ── Map initialiser ────────────────────────────────────────────────────────── */
        function _rmInitMap(r) {
            if (_rm) {
                _rm.remove();
                _rm = null;
            }
            _rmDriverMarker = null;
            _rmPickupMarker = null;
            _rmHospitalMarker = null;
            _rmAcceptedMarker = null;
            _rmRouting = null;
            _rmRouteLine = null;
            _rmRouteCoords = [];
            _rmRouteProgress = 0;
            _rmRouteFetching = false;
            _rmTraveledLine = null;
            _rmTraveledPoints = [];
            _rmPrevPos = null;
            _rmPrevPosTs = 0;
            _rmCurrentSpeed = 0;
            _rmEtaInFlight = false;
            _rmEtaLastFetch = 0;
            _rmMarkerIconLive = true;
            _rmLastRecalcTs = 0;
            _rmLastGpsFix = 0;

            var mapEl = document.getElementById('driReqDetailMap');
            if (!mapEl) return;

            _rm = L.map('driReqDetailMap').setView([r.pickup_lat, r.pickup_lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(_rm);

            var bounds = [];

            // Pickup marker
            _rmPickupMarker = L.marker([r.pickup_lat, r.pickup_lng], {
                    icon: _rmPersonIcon()
                })
                .bindPopup('<b>📍 Pickup Location</b><br>' + esc(r.pickup_address || ''))
                .addTo(_rm);
            bounds.push([r.pickup_lat, r.pickup_lng]);

            // Hospital marker
            if (r.hospital_lat && r.hospital_lng) {
                _rmHospitalMarker = L.marker([r.hospital_lat, r.hospital_lng], {
                        icon: _rmHospitalIcon()
                    })
                    .bindPopup('<b>🏥 ' + esc(r.hospital_name || 'Hospital') + '</b>')
                    .addTo(_rm);
                bounds.push([r.hospital_lat, r.hospital_lng]);
            }

            // Accepted location marker — shows where driver was when they accepted the request
            // Only show for En Route and beyond (status 3+) and when coordinates are available
            if (r.accepted_lat && r.accepted_lng && ['3', '4', '5', '6'].includes(r.status)) {
                _rmAcceptedMarker = L.marker([r.accepted_lat, r.accepted_lng], {
                        icon: _rmAcceptedIcon()
                    })
                    .bindPopup(
                        '<b>📍 Accepted Location</b><br><small style="color:#666;">Where driver accepted this request</small>'
                        )
                    .addTo(_rm);
                bounds.push([r.accepted_lat, r.accepted_lng]);
            }

            // Driver marker — seed from geolocation cache or DB coords
            var initLat = null,
                initLng = null;
            if (window._driCurrentPos) {
                initLat = window._driCurrentPos.lat;
                initLng = window._driCurrentPos.lng;
            } else if (r.driver_lat && r.driver_lng) {
                initLat = r.driver_lat;
                initLng = r.driver_lng;
            }

            if (initLat && initLng) {
                _rmDriverMarker = L.marker([initLat, initLng], {
                    icon: _rmAmbulanceIcon(true),
                    zIndexOffset: 1000,
                }).bindPopup('<b>🚑 Your Location (Live)</b>').addTo(_rm);
                bounds.push([initLat, initLng]);
                _rmUpdateBadge(initLat, initLng);
                // Set initial traveled point
                _rmTraveledPoints = [
                    [initLat, initLng]
                ];
                _rmPrevPos = {
                    lat: initLat,
                    lng: initLng
                };
                _rmPrevPosTs = Date.now();

                if (r.status === '5') {
                    _rmUpdateTransportPanel(initLat, initLng);
                } else {
                    _rmFetchETA(initLat, initLng);
                }
                _rmBuildRoute(r.status, initLat, initLng);
            } else {
                // No position yet — build route without driver
                _rmBuildRoute(r.status, null, null);
            }

            // If status=5 (already transporting), hide pickup marker
            if (r.status === '5' && _rmPickupMarker) {
                _rmPickupMarker.remove();
                _rmPickupMarker = null;
            }

            if (bounds.length > 1) {
                _rm.fitBounds(bounds, {
                    padding: [40, 40]
                });
            }
            _rm.invalidateSize();

            // Cancel smooth-movement rAF frames during zoom to prevent marker DOM confusion
            _rm.on('zoomstart', function() {
                Object.keys(_rmAnimFrames).forEach(function(k) {
                    cancelAnimationFrame(_rmAnimFrames[k]);
                    delete _rmAnimFrames[k];
                });
            });

            // Start live tracking
            _rmStartGeoWatch();
            _rmSubscribeReverb(true); // true = also bind driver.location.updated for the map
        }

        /* ── Modal cleanup ──────────────────────────────────────────────────────────── */
        // Modal is injected via @stack('modals') which renders after @stack('scripts'),
        // so we must wait for DOMContentLoaded before the element exists.
        document.addEventListener('DOMContentLoaded', function() {
            // Tell the layout's dispatch.request.sent handler that the driver is
            // already viewing this page — it will auto-mark notifications as read
            // instead of incrementing the bell badge.
            window._driOnRequestsPage = true;

            var _reqDetailModal = document.getElementById('reqDetailModal');
            if (_reqDetailModal) _reqDetailModal.addEventListener('hidden.bs.modal', function() {
                Object.keys(_rmAnimFrames).forEach(function(k) {
                    cancelAnimationFrame(_rmAnimFrames[k]);
                });
                _rmAnimFrames = {};
                _rmStopGeoWatch(); // also clears deviation timer

                if (_rm) {
                    _rm.remove();
                    _rm = null;
                }
                _rmDriverMarker = null;
                _rmPickupMarker = null;
                _rmHospitalMarker = null;
                _rmAcceptedMarker = null;
                _rmRouting = null;
                _rmRouteLine = null;
                _rmRouteCoords = [];
                _rmRouteProgress = 0;
                _rmRouteFetching = false;
                _rmTraveledLine = null;
                _rmTraveledPoints = [];
                _rmPrevPos = null;
                _rmPrevPosTs = 0;
                _rmCurrentSpeed = 0;
                _rmCurrentReq = null;
                _rmEtaInFlight = false;
                _rmEtaLastFetch = 0;
                _rmMarkerIconLive = true;
                _rmLastRecalcTs = 0;
                _rmLastGpsFix = 0;

                // Unbind map-only listeners — driver.location.updated is only
                // useful while the map modal is open (it moves the marker on
                // the modal map). We keep the channel itself alive and keep
                // request.status.updated bound so table-row badges continue
                // updating even after the modal closes.
                if (_rmAdminChannel && window._driPusher) {
                    _rmAdminChannel.unbind('driver.location.updated', _rmOnLocationUpdated);
                    // DO NOT unbind request.status.updated — keeps table badges live
                    // DO NOT null _rmAdminChannel — channel persists for badge updates
                }
            }); // end hidden.bs.modal
        }); // end DOMContentLoaded


        /* ── Detail item builder ────────────────────────────────────────────────────── */
        function detailItem(label, value, full, strongId) {
            var idAttr = strongId ? ' id="' + strongId + '"' : '';
            return '<div class="req-detail-item">' +
                '<span>' + esc(label) + '</span>' +
                '<strong' + idAttr + '>' + esc(value || '—') + '</strong>' +
                '</div>';
        }

        /* ── Search on Enter ────────────────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', function() {
            // Subscribe to contact.admin immediately on page load (no modal needed)
            // so table-row status badges update in real time even without opening a modal.
            _rmSubscribeReverb(false);

            // Badge is NOT cleared on page visit — it persists until ride completion.
            // driRenderRequestBadge() already re-renders the saved count from localStorage.

            var inp = document.querySelector('.dri-search-input');
            if (inp) {
                inp.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') document.getElementById('reqFilterForm').submit();
                });
            }

            // ── Auto-open modal when ?view=ID is in URL (from notification click) ─────
            var urlParams = new URLSearchParams(window.location.search);
            var autoViewId = parseInt(urlParams.get('view'), 10);
            if (autoViewId) {
                var found = _reqData.find(function(r) {
                    return r.id === autoViewId;
                });
                if (found) {
                    viewRequestDetail(autoViewId);
                } else {
                    // Not in current page data — fetch from server and open
                    fetch(window._driRoutes.requestActive, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            if (data.active && data.active.id === autoViewId) {
                                _reqData.unshift(data.active);
                                viewRequestDetail(autoViewId);
                            }
                        })
                        .catch(function() {});
                }
                // Clean URL without reload
                try {
                    var cleanUrl = window.location.pathname + (urlParams.toString().replace('view=' + autoViewId,
                        '').replace(/^&|&$/, '') ? '?' + urlParams.toString().replace('view=' + autoViewId,
                        '').replace(/^&|&$/, '') : '');
                    history.replaceState(null, '', cleanUrl);
                } catch (e) {}
            }
        });

        /* ── Animate a row out and remove it from the table ─────────────────────────── */
        function _driAnimateRemoveRow(id) {
            var row = document.getElementById('reqRow_' + id);
            if (!row) return;
            row.style.transition = 'opacity .4s ease, transform .4s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(28px)';
            setTimeout(function() {
                if (row.parentNode) row.remove();
                var tbody = document.querySelector('.req-table tbody');
                if (tbody && !tbody.querySelector('tr:not(.req-empty)')) {
                    tbody.innerHTML = '<tr class="req-empty"><td colspan="9">' +
                        '<i class="fa fa-inbox" style="display:block;font-size:1.6rem;margin-bottom:10px;opacity:.2;"></i>' +
                        'No active requests. <a href="' + (window._driRoutes && window._driRoutes.pastRidesPage ?
                            window._driRoutes.pastRidesPage : '#') +
                        '" style="color:#60a5fa;text-decoration:none;">View Past Rides →</a>' +
                        '</td></tr>';
                }
            }, 420);
        }

        /* ── Called from BroadcastChannel/Reverb when another tab completes a ride ─── */
        window.driRemoveRequestRow = function(req) {
            if (!req) return;
            var reqId = parseInt(req.id, 10) || parseInt(req.request_id, 10);
            if (!reqId) return;
            _reqData = _reqData.filter(function(x) {
                return x.id !== reqId;
            });
            _driAnimateRemoveRow(reqId);
        };

        /* ── Real-time: prepend newly dispatched request row ────────────────────────── */
        window.driPrependRequestRow = function(e) {
            if (!e || !e.request_id) return;

            var existingIdx = _reqData.findIndex(function(r) {
                return r.id === e.request_id;
            });

            if (existingIdx !== -1) {
                // Request already in list — update its status to Awaiting Acceptance in _reqData and DOM
                _reqData[existingIdx].status = '8';
                _reqData[existingIdx].ambulance_no = e.ambulance_no || _reqData[existingIdx].ambulance_no;
                _reqData[existingIdx].dispatched_at = e.time || _reqData[existingIdx].dispatched_at;
                _reqData[existingIdx].allowed_next = [];

                // Update status badge in existing row
                var badge = document.getElementById('reqStatusBadge_' + e.request_id);
                if (badge) {
                    badge.className = 'status-pill s8';
                    badge.textContent = 'Awaiting Acceptance';
                    var row = document.getElementById('reqRow_' + e.request_id);
                    if (row) row.style.animation = 'driNewRowFadeIn .45s ease both';
                }
                return;
            }

            var newReq = {
                id: e.request_id,
                rreb_id: e.rreb_id || '',
                type: e.type || '1',
                status: '8',
                mobile_no: e.mobile_no || '—',
                pickup_address: e.pickup_address || '—',
                hospital_name: e.hospital_name || '—',
                ambulance_no: e.ambulance_no || '—',
                ambulance_type: e.ambulance_type || '',
                notes: e.notes || '',
                dispatched_at: e.time || '',
                created_at: e.time || '',
                pickup_lat: e.pickup_lat || null,
                pickup_lng: e.pickup_lng || null,
                hospital_lat: e.hospital_lat || null,
                hospital_lng: e.hospital_lng || null,
                accepted_lat: null,
                accepted_lng: null,
                driver_lat: null,
                driver_lng: null,
                allowed_next: [],
            };

            _reqData.unshift(newReq);

            var typeLabel = newReq.type === '1' ? 'Emergency' : 'Non-Emergency';
            var typeCls = newReq.type === '1' ? 'emergency' : 'non-emergency';
            var sLabel = 'Awaiting Acceptance';

            var tbody = document.querySelector('.req-table tbody');
            if (!tbody) return;

            var emptyRow = tbody.querySelector('.req-empty');
            if (emptyRow) emptyRow.remove();

            var tr = document.createElement('tr');
            tr.id = 'reqRow_' + newReq.id;
            tr.style.cssText = 'animation:driNewRowFadeIn .45s ease both;';
            tr.innerHTML =
                '<td><span class="mono">' + esc(newReq.rreb_id || '#' + newReq.id) + '</span></td>' +
                '<td><span class="status-pill ' + typeCls + '">' + typeLabel + '</span></td>' +
                '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                esc(newReq.pickup_address.length > 32 ? newReq.pickup_address.substring(0, 32) + '…' : newReq.pickup_address) +
                '</td>' +
                '<td>' + esc(newReq.hospital_name.length > 22 ? newReq.hospital_name.substring(0, 22) + '…' : newReq.hospital_name) + '</td>' +
                '<td style="white-space:nowrap;">' + esc(newReq.mobile_no) + '</td>' +
                '<td>' + esc(newReq.ambulance_no || '—') + '</td>' +
                '<td><span class="status-pill s8" id="reqStatusBadge_' + newReq.id + '">' + sLabel + '</span></td>' +
                '<td style="white-space:nowrap;color:rgba(255,255,255,.38);font-size:.77rem;">' + esc(newReq.dispatched_at || '—') + '</td>' +
                '<td><button class="btn-dri-icon btn-dri-icon--primary" title="View Details" onclick="viewRequestDetail(' +
                newReq.id + ')">' +
                '<i class="fa fa-eye"></i></button></td>';

            tbody.insertBefore(tr, tbody.firstChild);
        };

        // Register the alias the layout's dispatch.request.sent handler calls.
        // The layout uses driInsertDispatchRequestRow; driPrependRequestRow is the
        // actual implementation defined on this page.
        window.driInsertDispatchRequestRow = window.driPrependRequestRow;

        // Inject animation keyframe once
        (function() {
            if (!document.getElementById('driReqRowStyle')) {
                var st = document.createElement('style');
                st.id = 'driReqRowStyle';
                st.textContent =
                    '@keyframes driNewRowFadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}';
                document.head.appendChild(st);
            }
        })();
    </script>
@endpush

@push('modals')
    {{-- Request Detail Modal — rendered at body level to avoid stacking-context conflicts --}}
    <div class="modal fade" id="reqDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reqModalTitle">
                        <i class="fa fa-truck-medical me-2" style="color:#f87171;"></i>Request Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reqDetailBody">
                    <div style="text-align:center;padding:30px;">
                        <div class="dri-spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Reject Confirmation Modal ───────────────────────────────────────────── --}}
    <div class="modal fade" id="driRejectConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
        data-bs-keyboard="false" style="z-index: 1080 !important;background: #00000052;">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content"
                style="background:#1a2235;border:1px solid rgba(239,68,68,.3);border-radius:16px;overflow:hidden;">
                <div class="modal-body" style="padding:28px 24px 22px;">
                    <div style="text-align:center;margin-bottom:22px;">
                        <div
                            style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                            <i class="fa fa-triangle-exclamation" style="color:#ef4444;font-size:1.4rem;"></i>
                        </div>
                        <h6 style="color:#f1f5f9;font-weight:700;margin:0 0 10px;font-size:1rem;">Reject Dispatch Request?
                        </h6>
                        <p style="color:rgba(255,255,255,.45);font-size:.83rem;margin:0;line-height:1.55;">
                            Are you sure you want to reject this request?<br>
                            The admin will be notified and the request will return to <strong
                                style="color:rgba(255,255,255,.6);">Pending</strong>.
                        </p>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn w-50"
                            style="background:rgba(255,255,255,.05);color:#94a3b8;border:1px solid rgba(255,255,255,.1);border-radius:10px;font-size:.85rem;padding:10px 0;transition:background .2s;"
                            data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="button" onclick="driConfirmReject()" class="btn w-50"
                            style="background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.35);border-radius:10px;font-size:.85rem;padding:10px 0;font-weight:700;transition:background .2s;">
                            <i class="fa fa-xmark me-1"></i>Confirm Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush
