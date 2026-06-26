@extends('user.layouts.user')

@section('title', 'Notification History — Rapid Rescue')

@push('styles')
<style>
.rr-nh-page { min-height:80vh; padding:40px 0; }
.rr-nh-card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); padding:28px; }
.rr-nh-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.rr-nh-title { font-size:1.2rem; font-weight:700; color:#111; }
.rr-nh-subtitle { font-size:.84rem; color:#6b7280; margin-top:2px; }
.rr-nh-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.rr-nh-search { display:block; width:100%; max-width:300px; padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:.84rem; color:#374151; margin-bottom:16px; }
.rr-nh-search:focus { outline:none; border-color:var(--rr-primary,#D72C42); box-shadow:0 0 0 3px rgba(215,44,66,.1); }
.rr-nh-item { display:flex; align-items:flex-start; gap:14px; padding:14px 8px; border-bottom:1px solid #f3f4f6; cursor:pointer; transition:background .15s; border-radius:8px; }
.rr-nh-item:hover { background:#fafafa; }
.rr-nh-item--unread { background:#eff6ff; }
.rr-nh-item--unread:hover { background:#dbeafe; }
.rr-nh-icon-wrap { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:12px; font-size:.95rem; flex-shrink:0; }
.rr-nh-icon-wrap--chat   { background:#fee2e2; color:#D72C42; }
.rr-nh-icon-wrap--status { background:#d1fae5; color:#059669; }
.rr-nh-body { flex:1; min-width:0; }
.rr-nh-message { font-size:.88rem; font-weight:600; color:#111827; line-height:1.4; }
.rr-nh-preview { font-size:.80rem; color:#6b7280; margin-top:3px; font-style:italic; }
.rr-nh-meta { display:flex; align-items:center; gap:6px; margin-top:5px; }
.rr-nh-time { font-size:.75rem; color:#9ca3af; }
.rr-nh-rreb { font-size:.75rem; color:#9ca3af; }
.rr-nh-dot { width:9px; height:9px; border-radius:50%; background:var(--rr-primary,#D72C42); flex-shrink:0; margin-top:4px; }
.rr-nh-empty { text-align:center; padding:56px 16px; color:#9ca3af; }
.rr-nh-pagination { display:flex; align-items:center; justify-content:space-between; margin-top:20px; flex-wrap:wrap; gap:10px; }
.rr-nh-pagination-info { font-size:.80rem; color:#9ca3af; }
.rr-nh-pg-btn { display:inline-block; padding:6px 16px; border:1px solid #e5e7eb; border-radius:8px; font-size:.82rem; font-weight:500; color:#374151; text-decoration:none; transition:background .15s,border-color .15s; }
.rr-nh-pg-btn:hover { background:#f3f4f6; border-color:#d1d5db; color:#111; }
.rr-nh-pg-btn:disabled, .rr-nh-pg-btn[disabled] { opacity:.4; pointer-events:none; }
.rr-nh-pg-label { font-size:.80rem; color:#6b7280; min-width:90px; text-align:center; }
.rr-nh-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:.74rem; font-weight:600; }
.rr-nh-badge--unread { background:#fee2e2; color:#D72C42; }
.rr-nh-badge--total  { background:#f3f4f6; color:#6b7280; }
.btn-rr-outline { background:transparent; border:1.5px solid var(--rr-primary,#D72C42); color:var(--rr-primary,#D72C42); padding:6px 16px; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; transition:background .15s,color .15s; }
.btn-rr-outline:hover { background:var(--rr-primary,#D72C42); color:#fff; }
</style>
@endpush

@section('content')
<section class="rr-nh-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="rr-nh-card">
                    <div class="rr-nh-header">
                        <div>
                            <div class="rr-nh-title"><i class="fa fa-bell me-2" style="color:var(--rr-primary,#D72C42);"></i>Notification History</div>
                            <div class="rr-nh-subtitle">All your ride &amp; chat notifications</div>
                        </div>
                        <div class="rr-nh-actions">
                            <span class="rr-nh-badge rr-nh-badge--total">{{ $notifications->total() }} total</span>
                            @if($unread > 0)
                            <span class="rr-nh-badge rr-nh-badge--unread">{{ $unread }} unread</span>
                            <button id="nhMarkAllBtn" class="btn-rr-outline">
                                <i class="fa fa-check-double me-1"></i>Mark all read
                            </button>
                            @endif
                        </div>
                    </div>

                    <input type="text" id="nhSearch" class="rr-nh-search" placeholder="Search notifications…">

                    <div id="nhList">
                        @forelse($notifications as $notif)
                        <div class="rr-nh-item {{ $notif['is_read'] ? '' : 'rr-nh-item--unread' }}"
                             data-id="{{ $notif['id'] }}"
                             data-src="{{ $notif['source'] }}"
                             data-src-id="{{ $notif['source_id'] }}"
                             data-request-id="{{ $notif['emergency_request_id'] }}"
                             data-action="{{ $notif['action_url'] }}">
                            <div class="rr-nh-icon-wrap {{ $notif['source'] === 'status' ? 'rr-nh-icon-wrap--status' : 'rr-nh-icon-wrap--chat' }}">
                                <i class="fa {{ $notif['source'] === 'status' ? 'fa-truck-medical' : 'fa-comment-dots' }}"></i>
                            </div>
                            <div class="rr-nh-body">
                                <div class="rr-nh-message">{{ $notif['message'] }}</div>
                                @if($notif['preview'])
                                <div class="rr-nh-preview">"{{ $notif['preview'] }}"</div>
                                @endif
                                <div class="rr-nh-meta">
                                    <span class="rr-nh-time" title="{{ $notif['time'] }}">{{ $notif['time_ago'] }}</span>
                                    @if($notif['rreb_id'])
                                    <span class="rr-nh-rreb">&middot; {{ $notif['rreb_id'] }}</span>
                                    @endif
                                </div>
                            </div>
                            @if(!$notif['is_read'])
                            <span class="rr-nh-dot"></span>
                            @endif
                        </div>
                        @empty
                        <div class="rr-nh-empty">
                            <i class="fa fa-bell-slash fa-2x mb-3 d-block"></i>
                            <p class="mb-0">No notifications yet</p>
                            <small class="text-muted">We'll notify you when something important happens.</small>
                        </div>
                        @endforelse
                    </div>

                    @if($notifications->lastPage() > 1)
                    <div class="rr-nh-pagination">
                        <div class="rr-nh-pagination-info">
                            Showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ $notifications->total() }}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($notifications->onFirstPage())
                                <span class="rr-nh-pg-btn" disabled>← Prev</span>
                            @else
                                <a href="{{ $notifications->previousPageUrl() }}" class="rr-nh-pg-btn">← Prev</a>
                            @endif
                            <span class="rr-nh-pg-label">{{ $notifications->currentPage() }} / {{ $notifications->lastPage() }}</span>
                            @if($notifications->hasMorePages())
                                <a href="{{ $notifications->nextPageUrl() }}" class="rr-nh-pg-btn">Next →</a>
                            @else
                                <span class="rr-nh-pg-btn" disabled>Next →</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var csrf         = '{{ csrf_token() }}';
    var chatReadUrl  = '/ride-chat/notif/:id/read';
    var statReadUrl  = '/ride-chat/status-notif/:id/read';
    var markAllUrl   = '/ride-chat/notifications/mark-all-read';

    document.querySelectorAll('.rr-nh-item').forEach(function (el) {
        el.addEventListener('click', function () {
            var src    = el.dataset.src;
            var srcId  = el.dataset.srcId;
            var action = el.dataset.action;
            var url    = src === 'chat'   ? chatReadUrl.replace(':id', srcId)
                       : src === 'status' ? statReadUrl.replace(':id', srcId) : '';
            if (url) fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' } }).catch(function(){});
            if (action) window.location.href = action;
            el.classList.remove('rr-nh-item--unread');
            var dot = el.querySelector('.rr-nh-dot');
            if (dot) dot.remove();
        });
    });

    var markAllBtn = document.getElementById('nhMarkAllBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(markAllUrl, { method:'POST', headers:{ 'X-CSRF-TOKEN':csrf, 'X-Requested-With':'XMLHttpRequest' } })
                .then(function () {
                    document.querySelectorAll('.rr-nh-item--unread').forEach(function (el) {
                        el.classList.remove('rr-nh-item--unread');
                        var dot = el.querySelector('.rr-nh-dot'); if (dot) dot.remove();
                    });
                    markAllBtn.style.display = 'none';
                    document.querySelectorAll('.rr-nh-badge--unread').forEach(function (b) { b.style.display='none'; });
                    if (window.rrNotifBell) window.rrNotifBell.refresh();
                }).catch(function(){});
        });
    }

    var searchInput = document.getElementById('nhSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.rr-nh-item').forEach(function (el) {
                var msg = (el.querySelector('.rr-nh-message') || {}).textContent || '';
                el.style.display = (!q || msg.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // Real-time via rrNotifBell — patch onNewMessage and onNewStatusNotif to
    // also refresh the history list when new notifications arrive.
    var _origOnNew    = window.rrNotifBell && window.rrNotifBell.onNewMessage;
    var _origOnStatus = window.rrNotifBell && window.rrNotifBell.onNewStatusNotif;
    var _origOnRead   = window.rrNotifBell && window.rrNotifBell.onNotifsRead;
    if (window.rrNotifBell) {
        window.rrNotifBell.onNewMessage = function () {
            if (_origOnNew) _origOnNew.call(window.rrNotifBell);
            _ajaxRefresh();
        };
        window.rrNotifBell.onNewStatusNotif = function () {
            if (_origOnStatus) _origOnStatus.call(window.rrNotifBell);
            _ajaxRefresh();
        };
        // When notifications are read (from this tab, another tab, or via WS),
        // refresh the history list so unread styling is removed immediately.
        window.rrNotifBell.onNotifsRead = function (count) {
            if (_origOnRead) _origOnRead.call(window.rrNotifBell, count);
            _ajaxRefresh();
        };
    }

    // Also listen for BroadcastChannel notif_read events from other tabs
    // (e.g. user opens chat in Tab 1 — Tab 2's history refreshes immediately).
    var _nhBc;
    try { _nhBc = new BroadcastChannel('rr_user_notif_sync'); } catch (bcErr) {}
    if (_nhBc) {
        _nhBc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'notif_read' || d.type === 'mark_all_read' || d.type === 'notif_auto_read') {
                _ajaxRefresh();
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
                    div.className = 'rr-nh-item' + (n.is_read ? '' : ' rr-nh-item--unread');
                    div.dataset.src = n.source; div.dataset.srcId = n.source_id;
                    div.dataset.requestId = n.emergency_request_id; div.dataset.action = n.action_url;
                    var iconClass = n.source === 'status' ? 'rr-nh-icon-wrap--status fa-truck-medical' : 'rr-nh-icon-wrap--chat fa-comment-dots';
                    div.innerHTML = '<div class="rr-nh-icon-wrap ' + iconClass.split(' ')[0] + '"><i class="fa ' + iconClass.split(' ')[1] + '"></i></div>' +
                        '<div class="rr-nh-body"><div class="rr-nh-message">' + _esc(n.message) + '</div>' +
                        (n.preview ? '<div class="rr-nh-preview">&ldquo;' + _esc(n.preview) + '&rdquo;</div>' : '') +
                        '<div class="rr-nh-meta"><span class="rr-nh-time">' + _esc(n.time_ago) + '</span>' +
                        (n.rreb_id ? '<span class="rr-nh-rreb">&middot; ' + _esc(n.rreb_id) + '</span>' : '') + '</div></div>' +
                        (!n.is_read ? '<span class="rr-nh-dot"></span>' : '');
                    div.addEventListener('click', function () {
                        var u = n.source === 'chat' ? chatReadUrl.replace(':id',n.source_id) : statReadUrl.replace(':id',n.source_id);
                        fetch(u,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}}).catch(function(){});
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
