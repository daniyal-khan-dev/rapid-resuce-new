@extends('admin.layouts.admin')

@section('title', 'Notification History')
@section('page_title', 'Notification History')

@section('content')
<div class="adm-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-700">Notification History</h5>
            <small class="text-muted">All notifications — chat messages &amp; ride status updates</small>
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
                    @if($notif['source'] === 'status')
                        <span class="nh-icon-wrap nh-icon-wrap--status"><i class="fa fa-truck-medical"></i></span>
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
.nh-item { border-bottom: 1px solid rgba(255,255,255,.05); transition: background .15s; }
.nh-item:hover { background: rgba(255,255,255,.04); }
.nh-item--unread { background: rgba(59,130,246,.06); }
.nh-item--unread:hover { background: rgba(59,130,246,.10); }
.nh-item-inner { display:flex; align-items:flex-start; gap:14px; padding:14px 8px; }
.nh-icon-wrap { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; font-size:.9rem; }
.nh-icon-wrap--chat   { background:rgba(59,130,246,.15); color:#60a5fa; }
.nh-icon-wrap--status { background:rgba(52,211,153,.15); color:#34d399; }
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
    var csrf = '{{ csrf_token() }}';
    var notifReadUrl     = '{{ url("admin/ride-chats/notif") }}/:id/read';
    var statusReadUrl    = '{{ url("admin/ride-chats/status-notif") }}/:id/read';
    var markAllUrl       = '{{ route("admin.ride-chats.markAllNotifsRead") }}';

    // ── Click to mark read + navigate ────────────────────────────────────────
    document.querySelectorAll('.nh-item').forEach(function (el) {
        el.addEventListener('click', function () {
            var src    = el.dataset.src;
            var srcId  = el.dataset.srcId;
            var action = el.dataset.action;
            var reqId  = el.dataset.requestId;
            var url    = '';

            if (src === 'chat') {
                url = notifReadUrl.replace(':id', srcId);
            } else if (src === 'status') {
                url = statusReadUrl.replace(':id', srcId);
            }

            if (url) {
                fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
                    .catch(function () {});
            }

            // Broadcast to other open admin tabs (Ride Chats sidebar etc.)
            if (reqId) {
                try {
                    var _bc = new BroadcastChannel('rr_admin_notif_sync');
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

    // ── Mark all read ────────────────────────────────────────────────────────
    var markAllBtn = document.getElementById('nhMarkAllBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(markAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function () {
                    document.querySelectorAll('.nh-item--unread').forEach(function (el) {
                        el.classList.remove('nh-item--unread');
                        var dot = el.querySelector('.nh-dot');
                        if (dot) dot.remove();
                    });
                    markAllBtn.style.display = 'none';
                    document.querySelectorAll('.badge.bg-danger').forEach(function (b) { b.style.display = 'none'; });
                    if (window.admNotifBell) window.admNotifBell.refresh();
                })
                .catch(function () {});
        });
    }

    // ── Client-side search filter ─────────────────────────────────────────────
    var searchInput = document.getElementById('nhSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.nh-item').forEach(function (el) {
                var text = el.querySelector('.nh-message') ? el.querySelector('.nh-message').textContent.toLowerCase() : '';
                var rreb = (el.dataset.requestId || '').toLowerCase();
                el.style.display = (!q || text.indexOf(q) !== -1 || rreb.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // ── Real-time: hook into bell signals ─────────────────────────────────────
    // When a new notification arrives, or when notifications are marked read,
    // refresh this page's list via AJAX so unread/read state stays accurate.
    var _origOnNewMsg = window.admNotifBell && window.admNotifBell.onNewMessage;
    if (window.admNotifBell) {
        window.admNotifBell.onNewMessage = function () {
            if (_origOnNewMsg) _origOnNewMsg.call(window.admNotifBell);
            _ajaxRefresh();
        };
    }

    var _origOnNotifsRead = window.admNotifBell && window.admNotifBell.onNotifsRead;
    if (window.admNotifBell) {
        window.admNotifBell.onNotifsRead = function () {
            if (_origOnNotifsRead) _origOnNotifsRead.call(window.admNotifBell);
            _ajaxRefresh();
        };
    }

    // ── BroadcastChannel: sync unread state from other admin tabs ─────────────
    // When the Ride Chats page (or Notification History in another tab) marks
    // notifications as read, update the DOM here directly without a full reload.
    var _nhBc;
    try { _nhBc = new BroadcastChannel('rr_admin_notif_sync'); } catch (ex) {}
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
                    div.dataset.id        = n.id;
                    div.dataset.src       = n.source;
                    div.dataset.srcId     = n.source_id;
                    div.dataset.requestId = n.emergency_request_id;
                    div.dataset.action    = n.action_url;
                    div.style.cursor      = 'pointer';
                    var icon = n.source === 'status'
                        ? '<span class="nh-icon-wrap nh-icon-wrap--status"><i class="fa fa-truck-medical"></i></span>'
                        : '<span class="nh-icon-wrap nh-icon-wrap--chat"><i class="fa fa-comment-dots"></i></span>';
                    div.innerHTML = '<div class="nh-item-inner"><div class="nh-icon">' + icon + '</div>' +
                        '<div class="nh-body"><div class="nh-message">' + _esc(n.message) + '</div>' +
                        (n.preview ? '<div class="nh-preview">&ldquo;' + _esc(n.preview) + '&rdquo;</div>' : '') +
                        '<div class="nh-meta"><span class="nh-time">' + _esc(n.time_ago) + '</span>' +
                        (n.rreb_id ? '<span class="nh-rreb">&middot; ' + _esc(n.rreb_id) + '</span>' : '') +
                        '</div></div>' +
                        (!n.is_read ? '<div class="nh-status"><span class="nh-dot"></span></div>' : '') +
                        '</div>';
                    div.addEventListener('click', function () { _clickItem(div); });
                    list.appendChild(div);
                });
            })
            .catch(function () {});
    }

    function _clickItem(el) {
        var src    = el.dataset.src;
        var srcId  = el.dataset.srcId;
        var action = el.dataset.action;
        var reqId  = el.dataset.requestId;
        var url    = src === 'chat'
            ? notifReadUrl.replace(':id', srcId)
            : src === 'status' ? statusReadUrl.replace(':id', srcId) : '';
        if (url) fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } }).catch(function(){});
        if (reqId) {
            try {
                var _bc2 = new BroadcastChannel('rr_admin_notif_sync');
                _bc2.postMessage({ type: 'notif_read', request_id: reqId });
                _bc2.close();
            } catch (ex) {}
        }
        if (action) window.location.href = action;
    }

    function _esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
@endpush
