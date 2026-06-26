@extends('driver.layouts.driver')

@section('title', 'Past Rides')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
/* ── Trip map ─────────────────────────────────────────────────── */
.pr-trip-map-wrap {
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255,255,255,.07);
}
.pr-trip-map { height: 320px; width: 100%; background: #1e293b; }
.pr-trip-map-loader {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(15,23,42,.75); z-index: 999;
    border-radius: 10px;
}
.pr-trip-map-info {
    margin-top: 10px;
    display: flex; gap: 14px; flex-wrap: wrap;
    font-size: .75rem; color: rgba(255,255,255,.4);
}
.pr-trip-legend {
    margin-top: 10px;
    display: flex; gap: 16px; flex-wrap: wrap;
    font-size: .72rem; color: rgba(255,255,255,.45);
}
.pr-trip-legend span { display: flex; align-items: center; gap: 5px; }

/* ── Page header ─────────────────────────────────────────────── */
.pr-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 24px;
}
.pr-header h2 {
    margin: 0 0 4px;
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    letter-spacing: .01em;
}
.pr-header p {
    margin: 0;
    font-size: .82rem;
    color: rgba(255,255,255,.4);
}

/* ── Stats strip ─────────────────────────────────────────────── */
.pr-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}
.pr-stat-card {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 160px;
    flex: 1;
}
.pr-stat-card__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.pr-stat-card__icon--green  { background: rgba(34,197,94,.12);  color: #22c55e; }
.pr-stat-card__icon--red    { background: rgba(239,68,68,.12);   color: #ef4444; }
.pr-stat-card__icon--blue   { background: rgba(59,130,246,.12);  color: #60a5fa; }
.pr-stat-card__val { font-size: 1.35rem; font-weight: 700; color: #f1f5f9; line-height: 1; }
.pr-stat-card__lbl { font-size: .73rem; color: rgba(255,255,255,.38); margin-top: 3px; }

/* ── Filter toolbar ──────────────────────────────────────────── */
.pr-toolbar {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.pr-search-wrap {
    position: relative;
    flex: 1;
    min-width: 200px;
}
.pr-search-wrap i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,.3);
    font-size: .8rem;
    pointer-events: none;
}
.pr-search-input {
    width: 100%;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px;
    padding: 8px 12px 8px 32px;
    color: #e2e8f0;
    font-size: .82rem;
    outline: none;
    transition: border-color .15s;
}
.pr-search-input::placeholder { color: rgba(255,255,255,.25); }
.pr-search-input:focus { border-color: rgba(99,102,241,.5); }

.pr-filter-select {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px;
    padding: 8px 12px;
    color: #e2e8f0;
    font-size: .82rem;
    outline: none;
    cursor: pointer;
    min-width: 140px;
}
.pr-filter-select option { background: #1e293b; }

/* Date filter pill buttons */
.pr-date-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.pr-date-btn {
    padding: 7px 14px;
    border-radius: 7px;
    border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.04);
    color: rgba(255,255,255,.5);
    font-size: .78rem;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
}
.pr-date-btn:hover   { background: rgba(255,255,255,.08); color: #e2e8f0; }
.pr-date-btn.active  { background: rgba(99,102,241,.18); border-color: rgba(99,102,241,.4); color: #a5b4fc; font-weight: 600; }

/* Custom date range row */
.pr-date-range {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
    margin-top: 4px;
}
.pr-date-range input[type="date"] {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px;
    padding: 7px 10px;
    color: #e2e8f0;
    font-size: .8rem;
    outline: none;
    color-scheme: dark;
}
.pr-date-range label { font-size: .78rem; color: rgba(255,255,255,.35); }

/* ── Table ───────────────────────────────────────────────────── */
.pr-table-wrap {
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 12px;
    overflow: auto;
}
.pr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
}
.pr-table th {
    background: rgba(255,255,255,.04);
    color: rgba(255,255,255,.38);
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 11px 14px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,.06);
    white-space: nowrap;
}
.pr-table td {
    padding: 12px 14px;
    color: #cbd5e1;
    border-bottom: 1px solid rgba(255,255,255,.04);
    vertical-align: middle;
}
.pr-table tbody tr:last-child td { border-bottom: none; }
.pr-table tbody tr:hover td { background: rgba(255,255,255,.025); }
.pr-table tbody tr.pr-new-row td { background: rgba(99,102,241,.05); }

.pr-table td.pr-empty {
    text-align: center;
    padding: 48px 20px;
    color: rgba(255,255,255,.2);
    font-size: .85rem;
}

/* Status pills */
.pr-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 600;
    white-space: nowrap;
}
.pr-pill--completed { background: rgba(34,197,94,.12);  color: #22c55e;  border: 1px solid rgba(34,197,94,.2);  }
.pr-pill--cancelled { background: rgba(239,68,68,.12);   color: #f87171;  border: 1px solid rgba(239,68,68,.2);  }

/* Type badge */
.pr-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 600;
}
.pr-type-badge--emergency    { background: rgba(239,68,68,.12);  color: #f87171; }
.pr-type-badge--non-emergency { background: rgba(59,130,246,.12); color: #60a5fa; }

/* ── Pagination ──────────────────────────────────────────────── */
.pr-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    border-top: 1px solid rgba(255,255,255,.05);
    flex-wrap: wrap;
    gap: 10px;
}
.pr-pag-info { font-size: .78rem; color: rgba(255,255,255,.3); }
.pr-pag-links { display: flex; gap: 4px; align-items: center; }
.pr-pag-links .page-link {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    color: rgba(255,255,255,.5);
    border-radius: 7px;
    padding: 5px 11px;
    font-size: .78rem;
    text-decoration: none;
    transition: all .15s;
}
.pr-pag-links .page-link:hover { background: rgba(255,255,255,.1); color: #e2e8f0; }
.pr-pag-links .page-link.active { background: rgba(99,102,241,.25); border-color: rgba(99,102,241,.4); color: #a5b4fc; font-weight: 600; }
.pr-pag-links .page-link.disabled { opacity: .3; pointer-events: none; }

/* ── Live badge ──────────────────────────────────────────────── */
.pr-live-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    animation: prPulse 1.6s infinite;
    margin-right: 5px;
    vertical-align: middle;
}
@keyframes prPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.8)} }

/* ── New row animation ───────────────────────────────────────── */
@keyframes prNewRowSlide { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:none} }
.pr-new-row { animation: prNewRowSlide .45s ease both; }

/* ── Detail modal ────────────────────────────────────────────── */
.pr-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
.pr-modal-field { display: flex; flex-direction: column; gap: 4px; }
.pr-modal-field label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: rgba(255,255,255,.35);
}
.pr-modal-field span {
    font-size: .85rem;
    color: #e2e8f0;
    word-break: break-word;
}
.pr-modal-divider { border: none; border-top: 1px solid rgba(255,255,255,.06); margin: 14px 0; }

/* Resp table */
@media (max-width: 768px) {
    .pr-table th:nth-child(3),
    .pr-table td:nth-child(3),
    .pr-table th:nth-child(4),
    .pr-table td:nth-child(4) { display: none; }
}
</style>
@endpush

@php
$_prPageData = collect($rides->items())->map(function($r) {
    return [
        'id'             => $r->id,
        'rreb_id'        => $r->rreb_id,
        'type'           => $r->type,
        'status'         => $r->status,
        'pickup_address' => $r->pickup_address,
        'hospital_name'  => $r->hospital_name,
        'mobile_no'      => $r->mobile_no,
        'ambulance_no'   => $r->ambulance?->vehicle_number,
        'notes'          => $r->notes,
        'completed_at'   => $r->completed_at?->format('d M Y, h:i A'),
        'dispatched_at'  => $r->dispatched_at?->format('d M Y, h:i A'),
        'created_at'     => $r->created_at->format('d M Y, h:i A'),
        'accepted_lat'   => $r->accepted_lat   ? (float) $r->accepted_lat  : null,
        'accepted_lng'   => $r->accepted_lng   ? (float) $r->accepted_lng  : null,
        'pickup_lat'     => $r->pickup_lat     ? (float) $r->pickup_lat    : null,
        'pickup_lng'     => $r->pickup_lng     ? (float) $r->pickup_lng    : null,
        'hospital_lat'   => $r->hospital_lat   ? (float) $r->hospital_lat  : null,
        'hospital_lng'   => $r->hospital_lng   ? (float) $r->hospital_lng  : null,
    ];
})->values()->all();
@endphp

@section('content')

{{-- Page Header --}}
<div class="pr-header">
    <div>
        <h2><i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;"></i>Past Rides</h2>
        <p>Completed and cancelled rides — full history with search and filters.</p>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:.75rem;color:rgba(255,255,255,.35);">
            <span class="pr-live-dot"></span>Real-time sync active
        </span>
        <a href="{{ route('driver.requests') }}" class="btn-dri-secondary" style="padding:7px 16px;font-size:.8rem;text-decoration:none;">
            <i class="fa fa-truck-medical me-1"></i>Active Requests
        </a>
    </div>
</div>

{{-- Stats strip --}}
<div class="pr-stats" id="prStatsSection">
    <div class="pr-stat-card">
        <div class="pr-stat-card__icon pr-stat-card__icon--green">
            <i class="fa fa-circle-check"></i>
        </div>
        <div>
            <div class="pr-stat-card__val" id="prStatCompleted">{{ $stats['completed'] }}</div>
            <div class="pr-stat-card__lbl">Completed Rides</div>
        </div>
    </div>
    <div class="pr-stat-card">
        <div class="pr-stat-card__icon pr-stat-card__icon--red">
            <i class="fa fa-ban"></i>
        </div>
        <div>
            <div class="pr-stat-card__val" id="prStatCancelled">{{ $stats['cancelled'] }}</div>
            <div class="pr-stat-card__lbl">Cancelled Rides</div>
        </div>
    </div>
    <div class="pr-stat-card">
        <div class="pr-stat-card__icon pr-stat-card__icon--blue">
            <i class="fa fa-list"></i>
        </div>
        <div>
            <div class="pr-stat-card__val">{{ $rides->total() }}</div>
            <div class="pr-stat-card__lbl">Matching Results</div>
        </div>
    </div>
</div>

{{-- Filter toolbar --}}
<form id="prFilterForm" method="GET" action="{{ route('driver.past-rides') }}">
    <div class="pr-toolbar">
        {{-- Search --}}
        <div class="pr-search-wrap">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}"
                   class="pr-search-input" placeholder="Search by ID, hospital, pickup, mobile…"
                   autocomplete="off">
        </div>

        {{-- Status --}}
        <select name="status" class="pr-filter-select" onchange="prNavigate()">
            <option value=""  {{ !request('status') ? 'selected' : '' }}>All Past Rides</option>
            <option value="6" {{ request('status') === '6' ? 'selected' : '' }}>Completed Only</option>
            <option value="7" {{ request('status') === '7' ? 'selected' : '' }}>Cancelled Only</option>
        </select>

        {{-- Date quick filters --}}
        <div class="pr-date-btns">
            <button type="button" class="pr-date-btn {{ request('date_filter','all') === 'all' ? 'active' : '' }}"
                    onclick="prSetDateFilter('all')">All Time</button>
            <button type="button" class="pr-date-btn {{ request('date_filter') === 'today' ? 'active' : '' }}"
                    onclick="prSetDateFilter('today')">Today</button>
            <button type="button" class="pr-date-btn {{ request('date_filter') === 'week' ? 'active' : '' }}"
                    onclick="prSetDateFilter('week')">This Week</button>
            <button type="button" class="pr-date-btn {{ request('date_filter') === 'month' ? 'active' : '' }}"
                    onclick="prSetDateFilter('month')">This Month</button>
        </div>
        <input type="hidden" name="date_filter" id="prDateFilterHidden" value="{{ request('date_filter','all') }}">

        {{-- Search button --}}
        <button type="submit" class="btn-dri-primary" style="padding:8px 18px;font-size:.8rem;">
            <i class="fa fa-magnifying-glass"></i> Search
        </button>

        <a href="{{ route('driver.past-rides') }}" id="prClearBtn" class="btn-dri-secondary"
           style="padding:8px 14px;font-size:.8rem;text-decoration:none;{{ (request('search') || request('status') || (request('date_filter') && request('date_filter') !== 'all') || request('date_from') || request('date_to')) ? '' : 'display:none;' }}">
            <i class="fa fa-xmark"></i> Clear
        </a>

        {{-- Custom date range --}}
        <div class="pr-date-range" id="prCustomRange"
             style="{{ (request('date_from') || request('date_to')) ? '' : 'display:none;' }}">
            <label>From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
            <label>To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}">
            <button type="submit" class="btn-dri-primary" style="padding:6px 14px;font-size:.78rem;">Apply</button>
        </div>

        {{-- Toggle custom range --}}
        <button type="button" id="prToggleRange"
                style="padding:7px 12px;font-size:.75rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;color:rgba(255,255,255,.4);cursor:pointer;"
                onclick="prToggleCustomRange()">
            <i class="fa fa-calendar"></i> Custom Range
        </button>
    </div>
</form>

{{-- Table --}}
<div class="pr-table-wrap" id="prTableSection" data-rides='@json($_prPageData)'>
    <table class="pr-table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Type</th>
                <th>Pickup</th>
                <th>Hospital</th>
                <th>Ambulance</th>
                <th>Status</th>
                <th>Date</th>
                <th style="width:56px;"></th>
            </tr>
        </thead>
        <tbody id="prTbody">
            @forelse($rides as $ride)
            <tr id="prRow_{{ $ride->id }}">
                <td>
                    <span style="font-family:monospace;font-size:.78rem;color:#a5b4fc;font-weight:600;">
                        {{ $ride->rreb_id }}
                    </span>
                </td>
                <td>
                    @if($ride->type == 1)
                        <span class="pr-type-badge pr-type-badge--emergency">Emergency</span>
                    @else
                        <span class="pr-type-badge pr-type-badge--non-emergency">Non-Emergency</span>
                    @endif
                </td>
                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="{{ $ride->pickup_address }}">
                    {{ Str::limit($ride->pickup_address, 32) ?: '—' }}
                </td>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="{{ $ride->hospital_name }}">
                    {{ Str::limit($ride->hospital_name, 28) ?: '—' }}
                </td>
                <td style="font-size:.78rem;color:rgba(255,255,255,.5);">
                    {{ $ride->ambulance?->vehicle_number ?? '—' }}
                </td>
                <td>
                    @if($ride->status == 6)
                        <span class="pr-pill pr-pill--completed"><i class="fa fa-circle-check me-1"></i>Completed</span>
                    @else
                        <span class="pr-pill pr-pill--cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>
                    @endif
                </td>
                <td style="white-space:nowrap;color:rgba(255,255,255,.38);font-size:.77rem;">
                    {{ $ride->created_at->format('d M Y') }}
                </td>
                <td>
                    <button class="btn-dri-icon btn-dri-icon--primary"
                            title="View Details"
                            onclick="prViewDetail({{ $ride->id }})">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
            @empty
            <tr id="prEmptyRow">
                <td colspan="8" class="pr-empty">
                    <i class="fa fa-inbox" style="display:block;font-size:1.6rem;margin-bottom:10px;opacity:.2;"></i>
                    No past rides found.
                    @if(request('search') || request('status') || (request('date_filter') && request('date_filter') !== 'all'))
                        <br><small style="opacity:.6;">Try adjusting your filters.</small>
                    @else
                        <br><small style="opacity:.6;">Completed and cancelled rides will appear here.</small>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination --}}
    @if($rides->hasPages())
    <div class="pr-pagination">
        <div class="pr-pag-info">
            Showing {{ $rides->firstItem() }}–{{ $rides->lastItem() }} of {{ $rides->total() }} rides
        </div>
        <nav class="pr-pag-links">
            {{-- Previous --}}
            @if($rides->onFirstPage())
                <span class="page-link disabled"><i class="fa fa-chevron-left"></i></span>
            @else
                <a href="{{ $rides->previousPageUrl() }}" class="page-link"><i class="fa fa-chevron-left"></i></a>
            @endif

            {{-- Pages --}}
            @foreach($rides->getUrlRange(max(1, $rides->currentPage() - 2), min($rides->lastPage(), $rides->currentPage() + 2)) as $page => $url)
                <a href="{{ $url }}" class="page-link {{ $page == $rides->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endforeach

            {{-- Next --}}
            @if($rides->hasMorePages())
                <a href="{{ $rides->nextPageUrl() }}" class="page-link"><i class="fa fa-chevron-right"></i></a>
            @else
                <span class="page-link disabled"><i class="fa fa-chevron-right"></i></span>
            @endif
        </nav>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ── Page data ───────────────────────────────────────────────────────────────── */
var _prData = @json($_prPageData);

/* ── Hash-based navigation with AJAX result loading ─────────────────────────── */
function prBuildParams() {
    var form = document.getElementById('prFilterForm');
    var params = new URLSearchParams();
    ['search','status','date_filter','date_from','date_to'].forEach(function(n) {
        var el = form.elements[n];
        if (!el) return;
        var v = el.value;
        if (!v || v === '') return;
        if (n === 'date_filter' && v === 'all') return;
        params.set(n, v);
    });
    return params;
}

function prNavigate() {
    var qs = prBuildParams().toString();
    history.replaceState(null, '', location.pathname + (qs ? '#' + qs : ''));
    prFetch(qs);
}

function prFetch(qs) {
    var url = document.getElementById('prFilterForm').action + (qs ? '?' + qs : '');
    var tableEl = document.getElementById('prTableSection');
    if (tableEl) tableEl.style.opacity = '0.45';
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var ns = doc.getElementById('prStatsSection');
            var nt = doc.getElementById('prTableSection');
            if (ns && document.getElementById('prStatsSection'))
                document.getElementById('prStatsSection').outerHTML = ns.outerHTML;
            if (nt) {
                if (document.getElementById('prTableSection'))
                    document.getElementById('prTableSection').outerHTML = nt.outerHTML;
                try {
                    var d = document.getElementById('prTableSection').getAttribute('data-rides');
                    if (d) _prData = JSON.parse(d);
                } catch(e) {}
                var cb = document.getElementById('prClearBtn');
                if (cb) cb.style.display = qs ? '' : 'none';
            }
        })
        .catch(function() {
            var t = document.getElementById('prTableSection');
            if (t) t.style.opacity = '1';
        });
}

document.getElementById('prFilterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    prNavigate();
});

/* Restore filters from URL hash on page load */
(function() {
    var hash = location.hash;
    if (hash && hash.length > 1) {
        var qs = hash.substring(1);
        var params = new URLSearchParams(qs);
        var form = document.getElementById('prFilterForm');
        params.forEach(function(v, k) {
            var el = form.elements[k];
            if (el) el.value = v;
        });
        prFetch(qs);
    }
})();

/* ── Date filter helper ──────────────────────────────────────────────────────── */
function prSetDateFilter(val) {
    document.getElementById('prDateFilterHidden').value = val;
    if (val !== 'custom') {
        document.querySelectorAll('.pr-date-range input[type="date"]').forEach(function(i) { i.value = ''; });
    }
    prNavigate();
}

function prToggleCustomRange() {
    var r = document.getElementById('prCustomRange');
    if (r.style.display === 'none') {
        r.style.display = 'flex';
        r.querySelector('input[type="date"]').focus();
    } else {
        r.style.display = 'none';
    }
}

/* ── HTML escape ─────────────────────────────────────────────────────────────── */
function prEsc(s) {
    return String(s || '—').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── View detail modal ───────────────────────────────────────────────────────── */
function prViewDetail(id) {
    var req = _prData.find(function(r) { return r.id === id; });
    if (!req) return;

    var statusLabel = req.status == '6' ? 'Completed' : 'Cancelled';
    var statusPill  = req.status == '6'
        ? '<span class="pr-pill pr-pill--completed"><i class="fa fa-circle-check me-1"></i>Completed</span>'
        : '<span class="pr-pill pr-pill--cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>';
    var typePill = (req.type == '1' || req.type === 1)
        ? '<span class="pr-type-badge pr-type-badge--emergency">Emergency</span>'
        : '<span class="pr-type-badge pr-type-badge--non-emergency">Non-Emergency</span>';

    var html = '<div style="padding:4px 0;">'
        + '<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;">'
        +   typePill + ' ' + statusPill
        +   '<span style="font-family:monospace;font-size:.9rem;color:#a5b4fc;margin-left:4px;">' + prEsc(req.rreb_id) + '</span>'
        + '</div>'
        + '<hr class="pr-modal-divider">'
        + '<div style="font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);margin-bottom:12px;">'
        +   '<i class="fa fa-map-location-dot me-1" style="color:#818cf8;"></i> Trip Route'
        + '</div>'
        + '<div class="pr-trip-map-wrap">'
        +   '<div id="prTripMap" class="pr-trip-map"></div>'
        +   '<div id="prTripMapLoader" class="pr-trip-map-loader">'
        +     '<div style="color:rgba(255,255,255,.35);font-size:.82rem;text-align:center;">'
        +       '<i class="fa fa-spinner fa-spin" style="font-size:1.2rem;margin-bottom:8px;display:block;opacity:.5;"></i>Loading route…'
        +     '</div>'
        +   '</div>'
        + '</div>'
        + '<div class="pr-trip-legend">'
        +   '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#6366f1;border:2px solid #fff;"></span>Driver start</span>'
        +   '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #fff;"></span>Pickup</span>'
        +   '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff;"></span>Hospital</span>'
        +   '<span><span style="display:inline-block;width:26px;height:3px;background:#94a3b8;border-radius:2px;vertical-align:middle;"></span>Completed route</span>'
        + '</div>'
        + '<div id="prTripMapInfo" class="pr-trip-map-info"></div>'
        + '<hr class="pr-modal-divider">'
        + '<div class="pr-modal-grid">'
        +   '<div class="pr-modal-field"><label>Mobile No.</label><span>' + prEsc(req.mobile_no) + '</span></div>'
        +   '<div class="pr-modal-field"><label>Ambulance</label><span>' + prEsc(req.ambulance_no) + '</span></div>'
        +   '<div class="pr-modal-field" style="grid-column:1/-1"><label>Pickup Address</label><span>' + prEsc(req.pickup_address) + '</span></div>'
        +   '<div class="pr-modal-field" style="grid-column:1/-1"><label>Destination Hospital</label><span>' + prEsc(req.hospital_name) + '</span></div>'
        + '</div>'
        + '<hr class="pr-modal-divider">'
        + '<div class="pr-modal-grid">'
        +   '<div class="pr-modal-field"><label>Created At</label><span>' + prEsc(req.created_at) + '</span></div>'
        +   '<div class="pr-modal-field"><label>Dispatched At</label><span>' + prEsc(req.dispatched_at) + '</span></div>'
        +   '<div class="pr-modal-field"><label>' + statusLabel + ' At</label><span>' + prEsc(req.completed_at) + '</span></div>'
        + '</div>';

    if (req.notes) {
        html += '<hr class="pr-modal-divider">'
            +   '<div class="pr-modal-field"><label>Notes</label>'
            +   '<span style="white-space:pre-wrap;">' + prEsc(req.notes) + '</span></div>';
    }

    html += '</div>';


    document.getElementById('prModalTitle').innerHTML =
        '<i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;"></i>Ride Details — ' + prEsc(req.rreb_id);
    document.getElementById('prModalBody').innerHTML = html;

    var modalEl = document.getElementById('prDetailModal');
    var modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

    // Destroy any previous Leaflet instance before opening a new modal
    if (_prTripMapInst) { try { _prTripMapInst.remove(); } catch(ex) {} _prTripMapInst = null; }

    function _onPrShown() {
        modalEl.removeEventListener('shown.bs.modal', _onPrShown);
        _prTripMapInst = _prInitTripMap('prTripMap', 'prTripMapLoader', 'prTripMapInfo', req);
    }
    modalEl.addEventListener('shown.bs.modal', _onPrShown);

    modal.show();
}

/* ── Static trip route map ───────────────────────────────────────────────────── */
var _prTripMapInst = null;

function _prInitTripMap(mapId, loaderId, infoId, ride) {
    var mapEl   = document.getElementById(mapId);
    var loader  = document.getElementById(loaderId);
    var infoEl  = document.getElementById(infoId);
    if (!mapEl || typeof L === 'undefined') return null;

    var accLat  = parseFloat(ride.accepted_lat);
    var accLng  = parseFloat(ride.accepted_lng);
    var pickLat = parseFloat(ride.pickup_lat);
    var pickLng = parseFloat(ride.pickup_lng);
    var hospLat = parseFloat(ride.hospital_lat);
    var hospLng = parseFloat(ride.hospital_lng);

    var hasAcc  = !isNaN(accLat)  && !isNaN(accLng);
    var hasPick = !isNaN(pickLat) && !isNaN(pickLng);
    var hasHosp = !isNaN(hospLat) && !isNaN(hospLng);

    if (!hasAcc && !hasPick && !hasHosp) {
        mapEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;'
            + 'color:rgba(255,255,255,.2);font-size:.82rem;">'
            + '<span><i class="fa fa-map-location-dot" style="margin-right:6px;opacity:.3;"></i>No location data saved for this ride.</span></div>';
        if (loader) loader.style.display = 'none';
        return null;
    }

    var map = L.map(mapEl, { zoomControl: true, attributionControl: false });
    // OpenStreetMap (standard bright map)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    
    function _mkIcon(bg, svgPath) {
        return L.divIcon({
            className: '',
            html: '<div style="background:' + bg + ';border:3px solid #fff;border-radius:50%;'
                + 'width:34px;height:34px;box-shadow:0 2px 12px rgba(0,0,0,.5);'
                + 'display:flex;align-items:center;justify-content:center;">'
                + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="15" height="15">' + svgPath + '</svg></div>',
            iconSize: [34,34], iconAnchor: [17,17], popupAnchor: [0,-20],
        });
    }

    var _svgAmb  = '<path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>';
    var _svgPrsn = '<circle cx="12" cy="7" r="4"/><path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>';
    var _svgHosp = '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 0 1 1 1v3h3a1 1 0 0 1 0 2h-3v3a1 1 0 0 1-2 0v-3H8a1 1 0 0 1 0-2h3V7a1 1 0 0 1 1-1z"/>';

    var bounds = [];
    if (hasAcc) {
        L.marker([accLat, accLng], { icon: _mkIcon('#6366f1', _svgAmb) })
            .bindPopup('<div style="font-size:.82rem;min-width:130px;"><b style="color:#6366f1;">🚑 Driver Start</b><br><small style="color:#9ca3af;">Accepted ride from here</small></div>')
            .addTo(map);
        bounds.push([accLat, accLng]);
    }
    if (hasPick) {
        L.marker([pickLat, pickLng], { icon: _mkIcon('#ef4444', _svgPrsn) })
            .bindPopup('<div style="font-size:.82rem;min-width:130px;"><b style="color:#ef4444;">📍 Pickup</b><br><small style="color:#9ca3af;">' + (ride.pickup_address || '') + '</small></div>')
            .addTo(map);
        bounds.push([pickLat, pickLng]);
    }
    if (hasHosp) {
        L.marker([hospLat, hospLng], { icon: _mkIcon('#22c55e', _svgHosp) })
            .bindPopup('<div style="font-size:.82rem;min-width:130px;"><b style="color:#22c55e;">🏥 Hospital</b><br><small style="color:#9ca3af;">' + (ride.hospital_name || '') + '</small></div>')
            .addTo(map);
        bounds.push([hospLat, hospLng]);
    }

    if (bounds.length === 1) { map.setView(bounds[0], 15); }
    else if (bounds.length > 1) { map.fitBounds(bounds, { padding: [50, 50] }); }

    if (loader) loader.style.display = 'none';

    // Fetch grey completed route from OSRM (static — no live tracking)
    var wps = [];
    if (hasAcc)  wps.push(accLng  + ',' + accLat);
    if (hasPick) wps.push(pickLng + ',' + pickLat);
    if (hasHosp) wps.push(hospLng + ',' + hospLat);

    if (wps.length >= 2) {
        fetch('https://router.project-osrm.org/route/v1/driving/' + wps.join(';') + '?overview=full&geometries=geojson')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.routes || !data.routes[0]) return;
                var route  = data.routes[0];
                var coords = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
                L.polyline(coords, { color: '#94a3b8', weight: 4, opacity: 0.85, lineJoin: 'round' }).addTo(map);
                map.fitBounds(coords, { padding: [40, 40] });
                if (infoEl) {
                    var km   = (route.distance / 1000).toFixed(1);
                    var mins = Math.round(route.duration / 60);
                    infoEl.innerHTML =
                        '<span><i class="fa fa-route" style="color:#818cf8;margin-right:4px;"></i>' + km + ' km total route</span>'
                        + (mins > 0 ? '<span><i class="fa fa-clock" style="color:#60a5fa;margin-right:4px;"></i>~' + mins + ' min estimated drive</span>' : '');
                }
            })
            .catch(function() {});
    }

    return map;
}

/* ── Build a table row from a ride object ────────────────────────────────────── */
function _prBuildRow(req) {
    var id      = req.id || req.request_id;
    var status  = String(req.status);
    var type    = String(req.type);
    var rrebId  = req.rreb_id || '';
    var pickup  = req.pickup_address || '';
    var hosp    = req.hospital_name  || '';
    var amb     = req.ambulance_no   || '—';
    var dt      = req.created_at     || req.time || req.date_short || '';

    var typePill = type === '1'
        ? '<span class="pr-type-badge pr-type-badge--emergency">Emergency</span>'
        : '<span class="pr-type-badge pr-type-badge--non-emergency">Non-Emergency</span>';

    var statusPill = status === '6'
        ? '<span class="pr-pill pr-pill--completed"><i class="fa fa-circle-check me-1"></i>Completed</span>'
        : '<span class="pr-pill pr-pill--cancelled"><i class="fa fa-ban me-1"></i>Cancelled</span>';

    return '<tr id="prRow_' + id + '" class="pr-new-row">'
        + '<td><span style="font-family:monospace;font-size:.78rem;color:#a5b4fc;font-weight:600;">' + prEsc(rrebId) + '</span></td>'
        + '<td>' + typePill + '</td>'
        + '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + prEsc(pickup) + '">'
        +   prEsc(pickup.length > 32 ? pickup.substring(0, 32) + '…' : pickup) + '</td>'
        + '<td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + prEsc(hosp) + '">'
        +   prEsc(hosp.length > 28 ? hosp.substring(0, 28) + '…' : hosp) + '</td>'
        + '<td style="font-size:.78rem;color:rgba(255,255,255,.5);">' + prEsc(amb) + '</td>'
        + '<td>' + statusPill + '</td>'
        + '<td style="white-space:nowrap;color:rgba(255,255,255,.38);font-size:.77rem;">' + prEsc(dt) + '</td>'
        + '<td><button class="btn-dri-icon btn-dri-icon--primary" title="View Details" onclick="prViewDetail(' + id + ')">'
        +   '<i class="fa fa-eye"></i></button></td>'
        + '</tr>';
}

/* ── Real-time: prepend a newly completed/cancelled ride ─────────────────────── */
window.driAddPastRideRow = function(req) {
    if (!req) return;
    var id = req.id || req.request_id;
    if (!id) return;
    // Only accept completed or cancelled
    var status = String(req.status);
    if (status !== '6' && status !== '7') return;
    // Dedup: skip if row already present
    if (document.getElementById('prRow_' + id)) return;

    // Add to in-memory data
    _prData.unshift({
        id:             id,
        rreb_id:        req.rreb_id        || '',
        type:           req.type           || '1',
        status:         status,
        pickup_address: req.pickup_address || '',
        hospital_name:  req.hospital_name  || '',
        mobile_no:      req.mobile_no      || '',
        ambulance_no:   req.ambulance_no   || null,
        notes:          req.notes          || null,
        completed_at:   req.completed_at   || null,
        dispatched_at:  req.dispatched_at  || null,
        created_at:     req.created_at     || req.time || req.date_short || '',
    });

    var tbody = document.getElementById('prTbody');
    if (!tbody) return;

    // Remove empty state row if present
    var emptyRow = document.getElementById('prEmptyRow');
    if (emptyRow) emptyRow.remove();

    // Prepend new row
    tbody.insertAdjacentHTML('afterbegin', _prBuildRow(req));

    // Update stats counters
    if (status === '6') {
        var el = document.getElementById('prStatCompleted');
        if (el) el.textContent = String((parseInt(el.textContent, 10) || 0) + 1);
    } else {
        var el2 = document.getElementById('prStatCancelled');
        if (el2) el2.textContent = String((parseInt(el2.textContent, 10) || 0) + 1);
    }
};
</script>
@endpush

@push('modals')
{{-- Past Ride Detail Modal --}}
<div class="modal fade" id="prDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prModalTitle">
                    <i class="fa fa-clock-rotate-left me-2" style="color:#818cf8;"></i>Ride Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="prModalBody">
                <div style="text-align:center;padding:30px;">
                    <div class="dri-spinner"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.06);">
                <button type="button" class="btn-dri-secondary" data-bs-dismiss="modal"
                        style="padding:8px 20px;font-size:.82rem;">
                    <i class="fa fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
@endpush
