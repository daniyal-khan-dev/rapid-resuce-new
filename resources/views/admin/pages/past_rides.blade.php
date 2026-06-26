@extends('admin.layouts.admin')
@section('title', 'Past Rides')
@section('page_title', 'Past Rides')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        /* ── Trip map ────────────────────────────────────────────────────── */
        .apr-trip-map-wrap {
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255, 255, 255, .07);
        }

        .apr-trip-map {
            height: 340px;
            width: 100%;
            background: #1e293b;
        }

        .apr-trip-map-loader {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, .75);
            z-index: 999;
            border-radius: 10px;
        }

        .apr-trip-map-info {
            margin-top: 10px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            font-size: .75rem;
            color: rgba(255, 255, 255, .4);
        }

        .apr-trip-legend {
            margin-top: 10px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: .72rem;
            color: rgba(255, 255, 255, .45);
        }

        .apr-trip-legend span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ── Stats strip ─────────────────────────────────────────────────── */
        .apr-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .apr-stat-card {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 160px;
            flex: 1;
        }

        .apr-stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .apr-stat-icon--green {
            background: rgba(34, 197, 94, .12);
            color: #22c55e;
        }

        .apr-stat-icon--red {
            background: rgba(239, 68, 68, .12);
            color: #ef4444;
        }

        .apr-stat-icon--blue {
            background: rgba(59, 130, 246, .12);
            color: #60a5fa;
        }

        .apr-stat-val {
            font-size: 1.35rem;
            font-weight: 700;
            color: #f1f5f9;
            line-height: 1;
        }

        .apr-stat-lbl {
            font-size: .72rem;
            color: rgba(255, 255, 255, .38);
            margin-top: 3px;
        }

        /* ── Filter toolbar ──────────────────────────────────────────────── */
        .apr-toolbar {
            background: rgba(255, 255, 255, .03);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .apr-search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .apr-search-wrap i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, .3);
            font-size: .8rem;
            pointer-events: none;
        }

        .apr-search-input {
            width: 100%;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 8px;
            padding: 8px 12px 8px 32px;
            color: #e2e8f0;
            font-size: .82rem;
            outline: none;
            transition: border-color .15s;
        }

        .apr-search-input::placeholder {
            color: rgba(255, 255, 255, .25);
        }

        .apr-search-input:focus {
            border-color: rgba(99, 102, 241, .5);
        }

        .apr-select {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 8px;
            padding: 8px 12px;
            color: #e2e8f0;
            font-size: .82rem;
            outline: none;
            cursor: pointer;
            min-width: 140px;
        }

        .apr-select option {
            background: #1e293b;
        }

        .apr-date-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .apr-date-btn {
            padding: 7px 13px;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            color: rgba(255, 255, 255, .5);
            font-size: .78rem;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }

        .apr-date-btn:hover {
            background: rgba(255, 255, 255, .08);
            color: #e2e8f0;
        }

        .apr-date-btn.active {
            background: rgba(99, 102, 241, .18);
            border-color: rgba(99, 102, 241, .4);
            color: #a5b4fc;
            font-weight: 600;
        }

        .apr-date-range {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            width: 100%;
            margin-top: 4px;
        }

        .apr-date-range input[type="date"] {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 8px;
            padding: 7px 10px;
            color: #e2e8f0;
            font-size: .8rem;
            outline: none;
            color-scheme: dark;
        }

        .apr-date-range label {
            font-size: .78rem;
            color: rgba(255, 255, 255, .35);
        }

        /* ── Live dot ────────────────────────────────────────────────────── */
        .apr-live-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            animation: aprPulse 1.6s infinite;
            margin-right: 4px;
            vertical-align: middle;
        }

        @keyframes aprPulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .3
            }
        }

        /* ── New row animation ───────────────────────────────────────────── */
        @keyframes aprNewRow {
            from {
                opacity: 0;
                transform: translateY(-8px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .apr-new-row {
            animation: aprNewRow .4s ease both;
        }

        /* ── Detail modal grid ───────────────────────────────────────────── */
        .apr-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .apr-detail-field {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .apr-detail-field label {
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: rgba(255, 255, 255, .35);
        }

        .apr-detail-field span {
            font-size: .85rem;
            color: #e2e8f0;
            word-break: break-word;
        }

        .apr-divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, .06);
            margin: 14px 0;
        }

        /* ── Status pills ────────────────────────────────────────────────── */
        .apr-pill-completed {
            background: rgba(34, 197, 94, .1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
        }

        .apr-pill-cancelled {
            background: rgba(239, 68, 68, .1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, .2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .apr-search-wrap {
                min-width: unset;
            }
        }

        .table td{
            padding: 5px 7px;
        }
    </style>
@endpush

@php
    $_aprPageData = collect($rides->items())
        ->map(function ($r) {
            return [
                'id' => $r->id,
                'rreb_id' => $r->rreb_id,
                'type' => $r->type,
                'status' => $r->status,
                'pickup_address' => $r->pickup_address,
                'hospital_name' => $r->hospital_name,
                'mobile_no' => $r->mobile_no,
                'user_name' => $r->user?->details?->first_name ?? 'Guest',
                'ambulance_no' => $r->ambulance?->vehicle_number,
                'ambulance_type' => match((string)($r->ambulance?->type ?? '')) {
                    '1' => 'BLS — Basic Life Support',
                    '2' => 'ALS — Advanced Life Support',
                    '3' => 'CCT — Critical Care Transport',
                    '4' => 'Neonatal',
                    '5' => 'AIR Ambulance',
                    default => $r->ambulance?->type ?? null,
                },
                'driver_name' => $r->driver?->name,
                'driver_phone' => $r->driver?->phone,
                'notes' => $r->notes,
                'completed_at' => $r->completed_at?->format('l d M Y \a\t h:i A'),
                'dispatched_at' => $r->dispatched_at?->format('l d M Y \a\t h:i A'),
                'created_at' => $r->created_at->format('l d M Y \a\t h:i A'),
                'accepted_lat' => $r->accepted_lat ? (float) $r->accepted_lat : null,
                'accepted_lng' => $r->accepted_lng ? (float) $r->accepted_lng : null,
                'pickup_lat' => $r->pickup_lat ? (float) $r->pickup_lat : null,
                'pickup_lng' => $r->pickup_lng ? (float) $r->pickup_lng : null,
                'hospital_lat' => $r->hospital_lat ? (float) $r->hospital_lat : null,
                'hospital_lng' => $r->hospital_lng ? (float) $r->hospital_lng : null,
            ];
        })
        ->values()
        ->all();
@endphp

@section('content')

    {{-- Page header --}}
    <div class="adm-page-header">
        <div>
            <h2><i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;font-size:1.1rem;"></i>Past Rides</h2>
            <p>Completed and cancelled rides — full history with search and filters.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:.75rem;color:rgba(255,255,255,.35);">
                <span class="apr-live-dot"></span>Real-time sync active
            </span>
            <a href="{{ route('admin.emergency.grid') }}"
                style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;font-size:.8rem;text-decoration:none;transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.1)'"
                onmouseout="this.style.background='rgba(255,255,255,.06)'">
                <i class="fa fa-truck-medical"></i> Active Requests
            </a>
        </div>
    </div>

    {{-- Stats strip --}}
    <div class="apr-stats" id="aprStatsSection">
        <div class="apr-stat-card">
            <div class="apr-stat-icon apr-stat-icon--green"><i class="fa fa-circle-check"></i></div>
            <div>
                <div class="apr-stat-val" id="aprStatCompleted">{{ $stats['completed'] }}</div>
                <div class="apr-stat-lbl">Completed Rides</div>
            </div>
        </div>
        <div class="apr-stat-card">
            <div class="apr-stat-icon apr-stat-icon--red"><i class="fa fa-ban"></i></div>
            <div>
                <div class="apr-stat-val" id="aprStatCancelled">{{ $stats['cancelled'] }}</div>
                <div class="apr-stat-lbl">Cancelled Rides</div>
            </div>
        </div>
        <div class="apr-stat-card">
            <div class="apr-stat-icon apr-stat-icon--blue"><i class="fa fa-list-check"></i></div>
            <div>
                <div class="apr-stat-val">{{ $rides->total() }}</div>
                <div class="apr-stat-lbl">Matching Results</div>
            </div>
        </div>
    </div>

    {{-- Filter toolbar --}}
    <form id="aprFilterForm" method="GET" action="{{ route('admin.emergency.past-rides') }}">
        <div class="apr-toolbar">

            {{-- Search --}}
            <div class="apr-search-wrap">
                <i class="fa fa-magnifying-glass"></i>
                <input type="text" name="search" value="{{ request('search') }}" class="apr-search-input"
                    placeholder="Search RREB ID, hospital, pickup, mobile…" autocomplete="off">
            </div>

            {{-- Status --}}
            <select name="status" class="apr-select" onchange="aprNavigate()">
                <option value="" {{ !request('status') ? 'selected' : '' }}>All Past Rides</option>
                <option value="6" {{ request('status') === '6' ? 'selected' : '' }}>Completed</option>
                <option value="7" {{ request('status') === '7' ? 'selected' : '' }}>Cancelled</option>
            </select>

            {{-- Driver --}}
            <select name="driver_id" class="apr-select" onchange="aprNavigate()">
                <option value="">All Drivers</option>
                @foreach ($allDrivers as $d)
                    <option value="{{ $d->id }}" {{ request('driver_id') == $d->id ? 'selected' : '' }}>
                        {{ $d->name }}
                    </option>
                @endforeach
            </select>

            {{-- Ambulance --}}
            <select name="ambulance_id" class="apr-select" onchange="aprNavigate()">
                <option value="">All Ambulances</option>
                @foreach ($allAmbulances as $a)
                    <option value="{{ $a->id }}" {{ request('ambulance_id') == $a->id ? 'selected' : '' }}>
                        {{ $a->vehicle_number }}
                    </option>
                @endforeach
            </select>

            {{-- Date quick filters --}}
            <div class="apr-date-btns">
                <button type="button" class="apr-date-btn {{ request('date_filter', 'all') === 'all' ? 'active' : '' }}"
                    onclick="aprSetDateFilter('all')">All Time</button>
                <button type="button" class="apr-date-btn {{ request('date_filter') === 'today' ? 'active' : '' }}"
                    onclick="aprSetDateFilter('today')">Today</button>
                <button type="button" class="apr-date-btn {{ request('date_filter') === 'week' ? 'active' : '' }}"
                    onclick="aprSetDateFilter('week')">This Week</button>
                <button type="button" class="apr-date-btn {{ request('date_filter') === 'month' ? 'active' : '' }}"
                    onclick="aprSetDateFilter('month')">This Month</button>
            </div>
            <input type="hidden" name="date_filter" id="aprDateFilterHidden" value="{{ request('date_filter', 'all') }}">

            {{-- Search button --}}
            <button type="submit" class="btn btn-primary btn-sm px-3" style="font-size:.8rem;">
                <i class="fa fa-magnifying-glass"></i> Search
            </button>


            {{-- Toggle custom range --}}
            <button type="button" id="aprToggleRange"
                style="padding:7px 12px;font-size:.75rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;color:rgba(255,255,255,.4);cursor:pointer;"
                onclick="aprToggleCustomRange()">
                <i class="fa fa-calendar"></i> Custom Range
            </button>

            {{-- Custom date range --}}
            <div class="apr-date-range" id="aprCustomRange"
                style="{{ request('date_from') || request('date_to') ? '' : 'display:none;' }}">
                <label>From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}">
                <label>To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}">
                <button type="submit" class="btn btn-primary btn-sm" style="font-size:.78rem;">Apply</button>
            </div>

        </div>
    </form>

    {{-- Table --}}
    <div class="card" id="aprTableSection" data-rides='@json($_aprPageData)'>
        @if ($rides->isEmpty())
            <div class="adm-empty">
                <i class="fa fa-inbox"></i>
                <p>No past rides found.
                    @if (request()->anyFilled(['search', 'status', 'driver_id', 'ambulance_id', 'date_from', 'date_to']) ||
                            (request('date_filter') && request('date_filter') !== 'all'))
                        <br><small>Try adjusting your filters.</small>
                    @else
                        <br><small>Completed and cancelled rides will appear here.</small>
                    @endif
                </p>
            </div>
        @else
            <div class="pgd-scroll">
                <table class="table table-hover mb-0" id="aprTable">
                    <thead>
                        <tr>
                            <th>RREB ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Hospital</th>
                            <th>Pickup</th>
                            <th>Driver</th>
                            <th>Ambulance</th>
                            <th>Status</th>
                            <th>Completed At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="aprTbody">
                        @foreach ($rides as $r)
                            <tr data-ride-id="{{ $r->id }}">
                                <td class="fs-xs">
                                    <span
                                        style="font-family:monospace;font-size:.8rem;background:rgba(129,140,248,.12);padding:3px 8px;border-radius:6px;color:#a5b4fc;white-space:nowrap;">
                                        {{ $r->rreb_id ?? '—' }}
                                    </span>
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    <div>{{ $r->user?->details?->first_name ?? 'Guest' }}</div>
                                </td>
                                <td>
                                    @if ($r->type === '1')
                                        <span class="status-pill status-4">Emergency</span>
                                    @else
                                        <span class="status-pill status-3">Non-Emergency</span>
                                    @endif
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    {{ Str::limit($r->hospital_name, 22) }}</td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    {{ Str::limit($r->pickup_address, 22) }}</td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    <div>{{ $r->driver?->name ?? '—' }}</div>
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    {{ $r->ambulance?->vehicle_number ?? '—' }}</td>
                                <td class="fs-xs">
                                    @if ($r->status === '6')
                                        <span class="apr-pill-completed"><i
                                                class="fa fa-circle-check me-1"></i>Completed</span>
                                    @else
                                        <span class="apr-pill-cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>
                                    @endif
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);white-space:nowrap;">
                                    {{ $r->completed_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td>
                                    <button class="btn-adm-icon" title="View Details"
                                        onclick="aprViewDetail({{ $r->id }})">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($rides->hasPages())
                <div class="pgd-footer">
                    <div class="pgd-info">
                        Showing {{ $rides->firstItem() }}–{{ $rides->lastItem() }} of {{ $rides->total() }} rides
                    </div>
                    <div class="pgd-controls">
                        @if ($rides->onFirstPage())
                            <button class="pgd-btn" disabled>← Prev</button>
                        @else
                            <a href="{{ $rides->previousPageUrl() }}" class="pgd-btn" style="text-decoration:none;">←
                                Prev</a>
                        @endif

                        <span class="pgd-pages">Page {{ $rides->currentPage() }} / {{ $rides->lastPage() }}</span>

                        @if ($rides->hasMorePages())
                            <a href="{{ $rides->nextPageUrl() }}" class="pgd-btn" style="text-decoration:none;">Next
                                →</a>
                        @else
                            <button class="pgd-btn" disabled>Next →</button>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Detail Modal --}}
    <div class="modal fade" id="aprDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="aprModalTitle">
                        <i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;"></i>Ride Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="aprModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-secondary"></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.06);">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        /* ── Page data for detail modal ─────────────────────────────────────────────── */
        var _aprData = @json($_aprPageData);

        /* ── Hash-based navigation with AJAX result loading ─────────────────────────── */
        function aprBuildParams() {
            var form = document.getElementById('aprFilterForm');
            var filters = {};
            ['search','status','driver_id','ambulance_id','date_filter','date_from','date_to'].forEach(function(n) {
                var el = form.elements[n];
                if (!el) return;
                var v = el.value;
                if (!v || v === '') return;
                if (n === 'date_filter' && v === 'all') return;
                filters[n] = v;
            });
            var params = new URLSearchParams();
            if (Object.keys(filters).length > 0) {
                params.set('q', btoa(JSON.stringify(filters)));
            }
            return params;
        }

        function aprNavigate() {
            var qs = aprBuildParams().toString();
            history.replaceState(null, '', location.pathname + (qs ? '#' + qs : ''));
            aprFetch(qs);
        }

        function aprFetch(qs) {
            var url = document.getElementById('aprFilterForm').action + (qs ? '?' + qs : '');
            var tableEl = document.getElementById('aprTableSection');
            if (tableEl) tableEl.style.opacity = '0.45';
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var ns = doc.getElementById('aprStatsSection');
                    var nt = doc.getElementById('aprTableSection');
                    if (ns && document.getElementById('aprStatsSection'))
                        document.getElementById('aprStatsSection').outerHTML = ns.outerHTML;
                    if (nt) {
                        if (document.getElementById('aprTableSection'))
                            document.getElementById('aprTableSection').outerHTML = nt.outerHTML;
                        try {
                            var d = document.getElementById('aprTableSection').getAttribute('data-rides');
                            if (d) _aprData = JSON.parse(d);
                        } catch(e) {}
                    }
                })
                .catch(function() {
                    var t = document.getElementById('aprTableSection');
                    if (t) t.style.opacity = '1';
                });
        }

        document.getElementById('aprFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            aprNavigate();
        });

        /* Restore filters from URL hash on page load */
        (function() {
            var hash = location.hash;
            if (hash && hash.length > 1) {
                var qs = hash.substring(1);
                var params = new URLSearchParams(qs);
                var q = params.get('q');
                if (q) {
                    try {
                        var decoded = JSON.parse(atob(q));
                        var form = document.getElementById('aprFilterForm');
                        Object.keys(decoded).forEach(function(k) {
                            var el = form.elements[k];
                            if (el) el.value = decoded[k];
                        });
                    } catch(e) {}
                }
                aprFetch(qs);
            }
        })();

        /* ── Date filter helpers ─────────────────────────────────────────────────────── */
        function aprSetDateFilter(val) {
            document.getElementById('aprDateFilterHidden').value = val;
            if (val !== 'custom') {
                document.querySelectorAll('.apr-date-range input[type="date"]').forEach(function(i) {
                    i.value = '';
                });
            }
            aprNavigate();
        }

        function aprToggleCustomRange() {
            var r = document.getElementById('aprCustomRange');
            r.style.display = (r.style.display === 'none') ? 'contents' : 'none';
        }

        /* ── HTML escape ─────────────────────────────────────────────────────────────── */
        function aprEsc(s) {
            return String(s || '—').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                '&quot;');
        }

        /* ── View detail modal ───────────────────────────────────────────────────────── */
        function aprViewDetail(id) {
            var d = _aprData.find(function(r) {
                return r.id === id;
            });
            if (!d) return;

            var statusHtml = d.status == '6' ?
                '<span class="apr-pill-completed"><i class="fa fa-circle-check me-1"></i>Completed</span>' :
                '<span class="apr-pill-cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>';
            var typeHtml = (d.type == '1' || d.type === '1') ?
                '<span class="status-pill status-4">Emergency</span>' :
                '<span class="status-pill status-3">Non-Emergency</span>';

            var html = '<div>' +
                '<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;">' +
                typeHtml + ' ' + statusHtml +
                '<span style="font-family:monospace;font-size:.9rem;color:#a5b4fc;margin-left:4px;">' + aprEsc(d.rreb_id) +
                '</span>' +
                '</div>' +
                '<hr class="apr-divider">' +
                '<div style="font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);margin-bottom:12px;">' +
                '<i class="fa fa-map-location-dot me-1" style="color:#818cf8;"></i> Trip Route' +
                '</div>' +
                '<div class="apr-trip-map-wrap">' +
                '<div id="aprTripMap" class="apr-trip-map"></div>' +
                '<div id="aprTripMapLoader" class="apr-trip-map-loader">' +
                '<div style="color:rgba(255,255,255,.35);font-size:.82rem;text-align:center;">' +
                '<i class="fa fa-spinner fa-spin" style="font-size:1.2rem;margin-bottom:8px;display:block;opacity:.5;"></i>Loading route…' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="apr-trip-legend">' +
                '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#6366f1;border:2px solid #fff;"></span>Driver start</span>' +
                '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #fff;"></span>Pickup</span>' +
                '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff;"></span>Hospital</span>' +
                '<span><span style="display:inline-block;width:26px;height:3px;background:#94a3b8;border-radius:2px;vertical-align:middle;"></span>Completed route</span>' +
                '</div>' +
                '<div id="aprTripMapInfo" class="apr-trip-map-info"></div>'+
                '<hr class="apr-divider">' +
                '<div style="margin-bottom:14px;font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);">Patient Details</div>' +
                '<div class="apr-detail-grid">' +
                '<div class="apr-detail-field"><label>Name</label><span>' + aprEsc(d.user_name) + '</span></div>' +
                '<div class="apr-detail-field"><label>Mobile No.</label><span>' + aprEsc(d.mobile_no) + '</span></div>' +
                '<div class="apr-detail-field"><label>Pickup Address</label><span>' + aprEsc(d
                    .pickup_address) + '</span></div>' +
                '<div class="apr-detail-field"><label>Destination Hospital</label><span>' + aprEsc(
                    d.hospital_name) + '</span></div>' +
                '</div>'+
                '<hr class="apr-divider">' +
                '<div style="margin-bottom:14px;font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);">Driver Details</div>' +
                '<div class="apr-detail-grid">' +
                '<div class="apr-detail-field"><label>Name</label><span>' + aprEsc(d.driver_name) + '</span></div>' +
                '<div class="apr-detail-field"><label>Phone</label><span>' + aprEsc(d.driver_phone) +
                '</span></div>' +
                '<div class="apr-detail-field"><label>Ambulance</label><span>' + aprEsc(d.ambulance_no) + '</span></div>' +
                '<div class="apr-detail-field"><label>Ambulance Type</label><span>' + aprEsc(d.ambulance_type) +
                '</span></div>' +
                '</div>'

                +
                '<hr class="apr-divider">' +
                '<div style="margin-bottom:14px;font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);">Timeline</div>' +
                '<div class="apr-detail-grid">' +
                '<div class="apr-detail-field"><label>Requested At</label><span>' + aprEsc(d.created_at) + '</span></div>' +
                '<div class="apr-detail-field"><label>Dispatched At</label><span>' + aprEsc(d.dispatched_at) +
                '</span></div>' +
                '<div class="apr-detail-field"><label>' + (d.status == '6' ? 'Completed At' : 'Cancelled At') +
                '</label><span>' + aprEsc(d.completed_at) + '</span></div>' +
                '</div>';

            if (d.notes) {
                html += '<hr class="apr-divider">' +
                    '<div class="apr-detail-field"><label>Notes</label>' +
                    '<span style="white-space:pre-wrap;">' + aprEsc(d.notes) + '</span></div>';
            }

            html += '</div>';


            document.getElementById('aprModalTitle').innerHTML = '<i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;"></i>Ride — ' + aprEsc(d.rreb_id);
            document.getElementById('aprModalBody').innerHTML = html;

            var aprModalEl = document.getElementById('aprDetailModal');

            // Destroy any previous Leaflet instance before opening new modal
            if (_aprTripMapInst) {
                try {
                    _aprTripMapInst.remove();
                } catch (ex) {}
                _aprTripMapInst = null;
            }

            function _onAprShown() {
                aprModalEl.removeEventListener('shown.bs.modal', _onAprShown);
                _aprTripMapInst = _aprInitTripMap('aprTripMap', 'aprTripMapLoader', 'aprTripMapInfo', d);
            }
            aprModalEl.addEventListener('shown.bs.modal', _onAprShown);

            bootstrap.Modal.getOrCreateInstance(aprModalEl).show();
        }

        /* ── Static trip route map ───────────────────────────────────────────────────── */
        var _aprTripMapInst = null;

        function _aprInitTripMap(mapId, loaderId, infoId, ride) {
            var mapEl = document.getElementById(mapId);
            var loader = document.getElementById(loaderId);
            var infoEl = document.getElementById(infoId);
            if (!mapEl || typeof L === 'undefined') return null;

            var accLat = parseFloat(ride.accepted_lat);
            var accLng = parseFloat(ride.accepted_lng);
            var pickLat = parseFloat(ride.pickup_lat);
            var pickLng = parseFloat(ride.pickup_lng);
            var hospLat = parseFloat(ride.hospital_lat);
            var hospLng = parseFloat(ride.hospital_lng);

            var hasAcc = !isNaN(accLat) && !isNaN(accLng);
            var hasPick = !isNaN(pickLat) && !isNaN(pickLng);
            var hasHosp = !isNaN(hospLat) && !isNaN(hospLng);

            if (!hasAcc && !hasPick && !hasHosp) {
                mapEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;' +
                    'color:rgba(255,255,255,.2);font-size:.82rem;">' +
                    '<span><i class="fa fa-map-location-dot" style="margin-right:6px;opacity:.3;"></i>No location data saved for this ride.</span></div>';
                if (loader) loader.style.display = 'none';
                return null;
            }

            var map = L.map(mapEl, {
                zoomControl: true,
                attributionControl: false
            });
            // OpenStreetMap (standard bright map)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            
            function _mkIcon(bg, svgPath) {
                return L.divIcon({
                    className: '',
                    html: '<div style="background:' + bg + ';border:3px solid #fff;border-radius:50%;' +
                        'width:34px;height:34px;box-shadow:0 2px 12px rgba(0,0,0,.5);' +
                        'display:flex;align-items:center;justify-content:center;">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="15" height="15">' +
                        svgPath + '</svg></div>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 17],
                    popupAnchor: [0, -20],
                });
            }

            var _svgAmb =
                '<path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>';
            var _svgPrsn = '<circle cx="12" cy="7" r="4"/><path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>';
            var _svgHosp =
                '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 0 1 1 1v3h3a1 1 0 0 1 0 2h-3v3a1 1 0 0 1-2 0v-3H8a1 1 0 0 1 0-2h3V7a1 1 0 0 1 1-1z"/>';

            var bounds = [];
            if (hasAcc) {
                L.marker([accLat, accLng], {
                        icon: _mkIcon('#6366f1', _svgAmb)
                    })
                    .bindPopup(
                        '<div style="font-size:.82rem;min-width:140px;"><b style="color:#6366f1;">🚑 Driver Start</b><br><small style="color:#9ca3af;">Accepted ride from here</small></div>'
                        )
                    .addTo(map);
                bounds.push([accLat, accLng]);
            }
            if (hasPick) {
                L.marker([pickLat, pickLng], {
                        icon: _mkIcon('#ef4444', _svgPrsn)
                    })
                    .bindPopup(
                        '<div style="font-size:.82rem;min-width:140px;"><b style="color:#ef4444;">📍 Pickup</b><br><small style="color:#9ca3af;">' +
                        aprEsc(ride.pickup_address || '') + '</small></div>')
                    .addTo(map);
                bounds.push([pickLat, pickLng]);
            }
            if (hasHosp) {
                L.marker([hospLat, hospLng], {
                        icon: _mkIcon('#22c55e', _svgHosp)
                    })
                    .bindPopup(
                        '<div style="font-size:.82rem;min-width:140px;"><b style="color:#22c55e;">🏥 Hospital</b><br><small style="color:#9ca3af;">' +
                        aprEsc(ride.hospital_name || '') + '</small></div>')
                    .addTo(map);
                bounds.push([hospLat, hospLng]);
            }

            if (bounds.length === 1) {
                map.setView(bounds[0], 15);
            } else if (bounds.length > 1) {
                map.fitBounds(bounds, {
                    padding: [50, 50]
                });
            }

            if (loader) loader.style.display = 'none';

            // Fetch grey completed route from OSRM (static — no live tracking)
            var wps = [];
            if (hasAcc) wps.push(accLng + ',' + accLat);
            if (hasPick) wps.push(pickLng + ',' + pickLat);
            if (hasHosp) wps.push(hospLng + ',' + hospLat);

            if (wps.length >= 2) {
                fetch('https://router.project-osrm.org/route/v1/driving/' + wps.join(';') +
                        '?overview=full&geometries=geojson')
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (!data.routes || !data.routes[0]) return;
                        var route = data.routes[0];
                        var coords = route.geometry.coordinates.map(function(c) {
                            return [c[1], c[0]];
                        });
                        L.polyline(coords, {
                            color: '#94a3b8',
                            weight: 4,
                            opacity: 0.85,
                            lineJoin: 'round'
                        }).addTo(map);
                        map.fitBounds(coords, {
                            padding: [40, 40]
                        });
                        if (infoEl) {
                            var km = (route.distance / 1000).toFixed(1);
                            var mins = Math.round(route.duration / 60);
                            infoEl.innerHTML =
                                '<span><i class="fa fa-route" style="color:#818cf8;margin-right:4px;"></i>' + km +
                                ' km total route</span>' +
                                (mins > 0 ?
                                    '<span><i class="fa fa-clock" style="color:#60a5fa;margin-right:4px;"></i>~' +
                                    mins + ' min estimated drive</span>' : '');
                        }
                    })
                    .catch(function() {});
            }

            return map;
        }

        /* ── Build a table row from event/object data ────────────────────────────────── */
        function _aprBuildRow(e) {
            var id = e.request_id || e.id;
            var rrebId = e.rreb_id || '';
            var type = String(e.type || '1');
            var status = String(e.status);
            var pickup = e.pickup_address || '';
            var hosp = e.hospital_name || '';
            var mob = e.mobile_no || '';
            var drvName = e.driver_name || '—';
            var amb = e.ambulance_no || '—';
            var dt = e.completed_at || e.time || e.date_short || '';

            var typePill = type === '1' ?
                '<span class="status-pill status-4">Emergency</span>' :
                '<span class="status-pill status-3">Non-Emergency</span>';

            var statusPill = status === '6' ?
                '<span class="apr-pill-completed"><i class="fa fa-circle-check me-1"></i>Completed</span>' :
                '<span class="apr-pill-cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>';

            return '<tr data-ride-id="' + id + '" class="apr-new-row">' +
                '<td class="ps-4"><span style="font-family:monospace;font-size:.8rem;background:rgba(129,140,248,.12);padding:3px 8px;border-radius:6px;color:#a5b4fc;white-space:nowrap;">' +
                aprEsc(rrebId) + '</span></td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);"><div>Guest</div><small>' + aprEsc(mob) +
                '</small></td>' +
                '<td>' + typePill + '</td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);">' + aprEsc(hosp.length > 22 ? hosp.substring(0, 22) +
                    '…' : hosp) + '</td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);">' + aprEsc(pickup.length > 22 ? pickup.substring(0, 22) +
                    '…' : pickup) + '</td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);">' + aprEsc(drvName) + '</td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);">' + aprEsc(amb) + '</td>' +
                '<td>' + statusPill + '</td>' +
                '<td class="fs-xs" style="color:var(--adm-muted);white-space:nowrap;">' + aprEsc(dt) + '</td>' +
                '<td><button class="btn-adm-icon" title="View Details" onclick="aprViewDetail(' + id +
                ')"><i class="fa fa-eye"></i></button></td>' +
                '</tr>';
        }

        /* ── Real-time: prepend a newly completed/cancelled ride ─────────────────────── */
        window.admAddPastRideRow = function(e) {
            if (!e) return;
            var id = e.request_id || e.id;
            var status = String(e.status);
            if (!id || (status !== '6' && status !== '7')) return;
            // Dedup
            if (document.querySelector('#aprTbody tr[data-ride-id="' + id + '"]')) return;

            // Add to in-memory data for detail modal
            _aprData.unshift({
                id: id,
                rreb_id: e.rreb_id || '',
                type: e.type || '1',
                status: status,
                pickup_address: e.pickup_address || '',
                hospital_name: e.hospital_name || '',
                mobile_no: e.mobile_no || '',
                user_name: 'Guest',
                ambulance_no: e.ambulance_no || null,
                ambulance_type: null,
                driver_name: e.driver_name || null,
                driver_phone: null,
                notes: e.notes || null,
                completed_at: e.completed_at || null,
                dispatched_at: e.dispatched_at || null,
                created_at: e.created_at || e.time || e.date_short || '',
            });

            var tbody = document.getElementById('aprTbody');
            if (!tbody) return;

            // Remove empty state if present
            var card = tbody.closest('.card');
            if (!card) return;
            var emptyEl = card.querySelector('.adm-empty');
            if (emptyEl) {
                // Replace empty state with table
                card.innerHTML = '';
                var wrap = document.createElement('div');
                wrap.className = 'pgd-scroll';
                wrap.innerHTML = '<table class="table table-hover mb-0" id="aprTable">' +
                    '<thead><tr>' +
                    '<th class="ps-4">RREB ID</th><th>User / Mobile</th><th>Type</th>' +
                    '<th>Hospital</th><th>Pickup</th><th>Driver</th><th>Ambulance</th>' +
                    '<th>Status</th><th>Completed At</th><th>Actions</th>' +
                    '</tr></thead>' +
                    '<tbody id="aprTbody"></tbody>' +
                    '</table>';
                card.appendChild(wrap);
                tbody = document.getElementById('aprTbody');
            }

            tbody.insertAdjacentHTML('afterbegin', _aprBuildRow(e));

            // Update counters
            if (status === '6') {
                var cEl = document.getElementById('aprStatCompleted');
                if (cEl) cEl.textContent = String((parseInt(cEl.textContent, 10) || 0) + 1);
            } else {
                var xEl = document.getElementById('aprStatCancelled');
                if (xEl) xEl.textContent = String((parseInt(xEl.textContent, 10) || 0) + 1);
            }
        };
    </script>
@endpush
