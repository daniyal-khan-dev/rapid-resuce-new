@extends('driver.layouts.driver')

@section('title', 'Notification History')
@section('page_title', 'Notification History')

@section('content')
<div class="dri-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-700">Notification History</h5>
            <small class="text-muted">All chat &amp; dispatch notifications</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($unread > 0)
            <button id="nhMarkAllBtn" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-check-double me-1"></i> Mark all read
            </button>
            @endif
            <span class="badge bg-secondary">{{ $notifications->total() }} total</span>
            @if($unread > 0)
            <span class="badge bg-danger">{{ $unread }} unread</span>
            @endif
        </div>
    </div>

    {{-- Search filter --}}
    <div class="mb-3">
        <input type="text" id="nhSearch" class="form-control form-control-sm" placeholder="Search notifications…" style="max-width:320px;">
    </div>

    {{-- List --}}
    <div id="nhList">
        @forelse($notifications as $notif)
        <div class="nh-item {{ $notif['is_read'] ? '' : 'nh-item--unread' }}"
             data-id="{{ $notif['id'] }}"
             data-src="{{ $notif['source'] }}"
             data-src-id="{{ $notif['source_id'] }}"
             data-request-id="{{ $notif['emergency_request_id'] }}"
             data-action="{{ $notif['action_url'] }}"
             style="cursor:pointer;">
            <div class="nh-item-inner">
                <div class="nh-icon">
                    @if($notif['source'] === 'assignment')
                        <span class="nh-icon-wrap nh-icon-wrap--assign"><i class="fa fa-truck-medical"></i></span>
                    @else
                        <span class="nh-icon-wrap nh-icon-wrap--chat"><i class="fa fa-comment-dots"></i></span>
                    @endif
                </div>
                <div class="nh-body">
                    <div class="nh-message">{{ $notif['message'] }}</div>
                    @if($notif['preview'])
                    <div class="nh-preview">"{{ $notif['preview'] }}"</div>
                    @endif
                    <div class="nh-meta">
                        <span class="nh-time" title="{{ $notif['time'] }}">{{ $notif['time_ago'] }}</span>
                        @if($notif['rreb_id'])
                        <span class="nh-rreb">&middot; {{ $notif['rreb_id'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="nh-status">
                    @if(!$notif['is_read'])
                    <span class="nh-dot" title="Unread"></span>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="nh-empty">
            <i class="fa fa-bell-slash fa-2x mb-2 opacity-50"></i>
            <p class="mb-0">No notifications yet</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($notifications->lastPage() > 1)
    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
        <small class="text-muted">
            Showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ $notifications->total() }}
        </small>
        <div class="d-flex gap-1">
            @if($notifications->onFirstPage())
                <button class="pgd-btn" disabled>← Prev</button>
            @else
                <a href="{{ $notifications->previousPageUrl() }}" class="pgd-btn">← Prev</a>
            @endif
            <span class="pgd-pages">Page {{ $notifications->currentPage() }} / {{ $notifications->lastPage() }}</span>
            @if($notifications->hasMorePages())
                <a href="{{ $notifications->nextPageUrl() }}" class="pgd-btn">Next →</a>
            @else
                <button class="pgd-btn" disabled>Next →</button>
            @endif
        </div>
    </div>
    @endif
</div>

<style>
.dri-card { background:var(--dri-card,#131e35); border-radius:14px; padding:24px; }
.nh-item { border-bottom:1px solid rgba(255,255,255,.05); transition:background .15s; }
.nh-item:hover { background:rgba(255,255,255,.04); }
.nh-item--unread { background:rgba(59,130,246,.06); }
.nh-item--unread:hover { background:rgba(59,130,246,.10); }
.nh-item-inner { display:flex; align-items:flex-start; gap:14px; padding:14px 8px; }
.nh-icon-wrap { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; font-size:.9rem; }
.nh-icon-wrap--chat   { background:rgba(59,130,246,.15); color:#60a5fa; }
.nh-icon-wrap--assign { background:rgba(52,211,153,.15); color:#34d399; }
.nh-body { flex:1; min-width:0; }
.nh-message { font-size:.84rem; font-weight:600; color:#e2e8f0; line-height:1.4; }
.nh-preview { font-size:.78rem; color:rgba(255,255,255,.50); margin-top:2px; font-style:italic; }
.nh-meta { display:flex; align-items:center; gap:6px; margin-top:4px; }
.nh-time { font-size:.73rem; color:rgba(255,255,255,.35); }
.nh-rreb { font-size:.73rem; color:rgba(255,255,255,.35); }
.nh-status { display:flex; align-items:center; }
.nh-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#3b82f6; flex-shrink:0; }
.nh-empty { text-align:center; padding:48px 16px; color:rgba(255,255,255,.35); }
</style>
@endsection

@push('scripts')
<script>
(function () {
    var csrf            = '{{ csrf_token() }}';
    var chatReadUrl     = '{{ url("/driver/ride-chats/notif") }}/:id/read';
    var assignReadUrl   = '{{ url("/driver/ride-chats/assignment-notif") }}/:id/read';
    var markAllUrl      = '{{ route("driver.ride-chats.markAllNotifsRead") }}';

    document.querySelectorAll('.nh-item').forEach(function (el) {
        el.addEventListener('click', function () {
            var src    = el.dataset.src;
            var srcId  = el.dataset.srcId;
            var action = el.dataset.action;
            var reqId  = el.dataset.requestId;
            var url    = src === 'chat'       ? chatReadUrl.replace(':id', srcId)
                       : src === 'assignment' ? assignReadUrl.replace(':id', srcId) : '';
            if (url) fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' } }).catch(function(){});
            // Broadcast to other open driver tabs (Ride Chats sidebar etc.)
            if (reqId) {
                try {
                    var _bc = new BroadcastChannel('rr_driver_notif_sync');
                    _bc.postMessage({ type: 'notif_read', request_id: reqId });
                    _bc.close();
                } catch (ex) {}
            }
            if (action) window.location.href = action;
            el.classList.remove('nh-item--unread');
            var dot = el.querySelector('.nh-dot');
            if (dot) dot.remove();
        });
    });

    var markAllBtn = document.getElementById('nhMarkAllBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(markAllUrl, { method:'POST', headers:{ 'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' } })
                .then(function () {
                    document.querySelectorAll('.nh-item--unread').forEach(function (el) {
                        el.classList.remove('nh-item--unread');
                        var dot = el.querySelector('.nh-dot');
                        if (dot) dot.remove();
                    });
                    markAllBtn.style.display = 'none';
                    document.querySelectorAll('.badge.bg-danger').forEach(function (b) { b.style.display = 'none'; });
                    if (window.driNotifBell) window.driNotifBell.refresh();
                }).catch(function () {});
        });
    }

    var searchInput = document.getElementById('nhSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.nh-item').forEach(function (el) {
                var text = (el.querySelector('.nh-message') || {}).textContent || '';
                el.style.display = (!q || text.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // ── Real-time: hook into bell signals ─────────────────────────────────────
    var _origOnNewMsg = window.driNotifBell && window.driNotifBell.onNewMessage;
    if (window.driNotifBell) {
        window.driNotifBell.onNewMessage = function () {
            if (_origOnNewMsg) _origOnNewMsg.call(window.driNotifBell);
            _ajaxRefresh();
        };
    }

    var _origOnNotifsRead = window.driNotifBell && window.driNotifBell.onNotifsRead;
    if (window.driNotifBell) {
        window.driNotifBell.onNotifsRead = function () {
            if (_origOnNotifsRead) _origOnNotifsRead.call(window.driNotifBell);
            _ajaxRefresh();
        };
    }

    // ── BroadcastChannel: sync unread state from other driver tabs ─────────────
    var _nhBc;
    try { _nhBc = new BroadcastChannel('rr_driver_notif_sync'); } catch (ex) {}
    if (_nhBc) {
        _nhBc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'notif_read' && d.request_id) {
                document.querySelectorAll('.nh-item[data-request-id="' + d.request_id + '"]').forEach(function (el) {
                    el.classList.remove('nh-item--unread');
                    var dot = el.querySelector('.nh-dot');
                    if (dot) dot.remove();
                });
            }
            if (d.type === 'mark_all_read') {
                document.querySelectorAll('.nh-item--unread').forEach(function (el) {
                    el.classList.remove('nh-item--unread');
                    var dot = el.querySelector('.nh-dot');
                    if (dot) dot.remove();
                });
            }
        };
    }

    function _ajaxRefresh() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.notifications) return;
                var list = document.getElementById('nhList');
                if (!list) return;
                list.innerHTML = '';
                data.notifications.forEach(function (n) {
                    var div = document.createElement('div');
                    div.className = 'nh-item' + (n.is_read ? '' : ' nh-item--unread');
                    div.dataset.src = n.source; div.dataset.srcId = n.source_id;
                    div.dataset.requestId = n.emergency_request_id; div.dataset.action = n.action_url;
                    div.style.cursor = 'pointer';
                    var icon = n.source === 'assignment'
                        ? '<span class="nh-icon-wrap nh-icon-wrap--assign"><i class="fa fa-truck-medical"></i></span>'
                        : '<span class="nh-icon-wrap nh-icon-wrap--chat"><i class="fa fa-comment-dots"></i></span>';
                    div.innerHTML = '<div class="nh-item-inner"><div class="nh-icon">' + icon + '</div>' +
                        '<div class="nh-body"><div class="nh-message">' + _esc(n.message) + '</div>' +
                        (n.preview ? '<div class="nh-preview">&ldquo;' + _esc(n.preview) + '&rdquo;</div>' : '') +
                        '<div class="nh-meta"><span class="nh-time">' + _esc(n.time_ago) + '</span>' +
                        (n.rreb_id ? '<span class="nh-rreb">&middot; ' + _esc(n.rreb_id) + '</span>' : '') +
                        '</div></div>' + (!n.is_read ? '<div class="nh-status"><span class="nh-dot"></span></div>' : '') + '</div>';
                    div.addEventListener('click', function () {
                        var u = n.source === 'chat' ? chatReadUrl.replace(':id',n.source_id) : assignReadUrl.replace(':id',n.source_id);
                        fetch(u, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}}).catch(function(){});
                        if (n.emergency_request_id) {
                            try {
                                var _dbc = new BroadcastChannel('rr_driver_notif_sync');
                                _dbc.postMessage({ type: 'notif_read', request_id: n.emergency_request_id });
                                _dbc.close();
                            } catch (ex) {}
                        }
                        if (n.action_url) window.location.href = n.action_url;
                    });
                    list.appendChild(div);
                });
            }).catch(function () {});
    }

    function _esc(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
@endpush
