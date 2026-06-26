@extends('driver.layouts.driver')
@section('title', 'Ride Chats')
@section('page_title', 'Ride Chats')

@push('styles')
<style>
.rc-wrap {
    display: flex;
    height: calc(100vh - 80px);
    background: rgba(255,255,255,.02);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 16px;
    overflow: hidden;
}
.rc-list {
    width: 280px;
    min-width: 240px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,.07);
}
.rc-list__head {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    font-size: .8rem;
    font-weight: 700;
    color: rgba(255,255,255,.55);
    text-transform: uppercase;
    letter-spacing: .06em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rc-list__items { flex: 1; overflow-y: auto; }
.rc-list__items::-webkit-scrollbar { width: 4px; }
.rc-list__items::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
.rc-item {
    padding: 11px 14px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,.05);
    transition: background .15s;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.rc-item:hover { background: rgba(255,255,255,.04); }
.rc-item.rc-item--active { background: rgba(59,130,246,.1); border-left: 3px solid #3b82f6; }
.rc-item__body { flex: 1; min-width: 0; }
.rc-item__id { font-size: .78rem; font-weight: 700; color: #e2e8f0; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rc-item__sub { font-size: .71rem; color: rgba(255,255,255,.35); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rc-item__badge { min-width: 18px; height: 18px; background: #ef4444; border-radius: 9px; color: #fff; font-size: .66rem; font-weight: 700; text-align: center; line-height: 18px; padding: 0 5px; flex-shrink: 0; }
.rc-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }

.rc-chat { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.rc-chat__head { padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,.07); display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.015); }
.rc-chat__title { font-size: .88rem; font-weight: 700; color: #e2e8f0; }
.rc-chat__sub   { font-size: .74rem; color: rgba(255,255,255,.35); }
.rc-chat__status-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.rc-messages { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; }
.rc-messages::-webkit-scrollbar { width: 4px; }
.rc-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
.rc-bubble-wrap { display: flex; flex-direction: column; margin-bottom: 12px; }
.rc-bubble-wrap--right { align-items: flex-end; }
.rc-bubble-wrap--left  { align-items: flex-start; }
.rc-sender-name { font-size: .7rem; color: rgba(255,255,255,.38); margin-bottom: 3px; }
.rc-bubble { max-width: 72%; padding: 8px 13px; border-radius: 14px; font-size: .84rem; line-height: 1.48; word-break: break-word; }
.rc-bubble--user   { background: rgba(59,130,246,.22); border-bottom-left-radius: 3px; color: #bfdbfe; }
.rc-bubble--admin  { background: rgba(139,92,246,.22); border-bottom-left-radius: 3px; color: #ddd6fe; }
.rc-bubble--driver { background: #10b981; border-bottom-right-radius: 3px; color: #fff; }
.rc-bubble-time { font-size: .67rem; color: rgba(255,255,255,.25); margin-top: 3px; }
.rc-input-wrap { padding: 12px 18px; border-top: 1px solid rgba(255,255,255,.07); display: flex; gap: 8px; background: rgba(255,255,255,.015); }
.rc-input { flex: 1; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 10px; color: #e2e8f0; font-size: .84rem; padding: 9px 14px; font-family: inherit; outline: none; transition: border-color .15s; }
.rc-input:focus { border-color: rgba(16,185,129,.5); }
.rc-input::placeholder { color: rgba(255,255,255,.25); }
.rc-send-btn { background: #10b981; color: #fff; border: none; border-radius: 10px; padding: 9px 18px; font-size: .83rem; font-weight: 600; cursor: pointer; transition: background .15s; font-family: inherit; }
.rc-send-btn:hover { background: #059669; }
.rc-send-btn:disabled { opacity: .5; cursor: not-allowed; }
.rc-typing-bar { padding: 4px 20px; font-size: .72rem; color: rgba(255,255,255,.35); min-height: 22px; }
.rc-empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,.2); }
.rc-locked-msg, .rc-closed-msg { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 8px; color: rgba(255,255,255,.3); font-size: .84rem; }
.rc-empty-list { padding: 24px 16px; text-align: center; color: rgba(255,255,255,.25); font-size: .8rem; }
@media (max-width: 640px) {
    .rc-list { width: 100%; }
    .rc-wrap  { flex-direction: column; height: auto; min-height: 500px; }
}
</style>
@endpush

@section('content')

@php
    $statusLabels = [
        '1' => 'Pending', '2' => 'Dispatched', '3' => 'En Route',
        '4' => 'Arrived', '5' => 'Transporting', '6' => 'Completed', '7' => 'Cancelled',
    ];
    $statusColors = [
        '1' => '#94a3b8', '2' => '#3b82f6', '3' => '#22c55e',
        '4' => '#f59e0b', '5' => '#f97316', '6' => '#06b6d4', '7' => '#ef4444',
    ];
@endphp

<div class="rc-wrap">

    {{-- Left pane --}}
    <div class="rc-list">
        <div class="rc-list__head">
            <i class="fa fa-comments" style="font-size:.82rem;"></i> My Ride Chats
            @if($unread > 0)
                <span style="background:#ef4444;color:#fff;border-radius:9px;font-size:.68rem;padding:0 7px;line-height:18px;height:18px;display:inline-block;">{{ $unread }}</span>
            @endif
        </div>
        <div class="rc-list__items" id="rcListItems">
            @forelse($requests as $r)
            <div class="rc-item" id="rcListItem{{ $r['id'] }}" onclick="rcOpenChat({{ $r['id'] }})">
                <span class="rc-status-dot" style="background:{{ $statusColors[$r['status']] ?? '#94a3b8' }};"></span>
                <div class="rc-item__body">
                    <div class="rc-item__id">{{ strtoupper($r['rreb_id']) }}</div>
                    <div class="rc-item__sub">
                        {{ $statusLabels[$r['status']] ?? $r['status'] }} &middot;
                        {{ \Illuminate\Support\Str::limit($r['pickup_address'] ?? '', 26) }}
                    </div>
                </div>
                @if($r['unread'] > 0)
                    <span class="rc-item__badge" id="rcItemBadge{{ $r['id'] }}">{{ $r['unread'] }}</span>
                @else
                    <span class="rc-item__badge" id="rcItemBadge{{ $r['id'] }}" style="display:none;">0</span>
                @endif
            </div>
            @empty
            <div class="rc-empty-list">
                <i class="fa fa-comments" style="font-size:1.5rem;margin-bottom:8px;opacity:.3;display:block;"></i>
                No conversations yet.<br>
                Rides with messages will appear here.
            </div>
            @endforelse
        </div>
    </div>

    {{-- Right chat pane --}}
    <div class="rc-chat" id="rcChatPane">

        <div class="rc-empty-state" id="rcEmptyState">
            <i class="fa fa-comments" style="font-size:2.5rem;margin-bottom:14px;"></i>
            <div style="font-size:.9rem;font-weight:600;">Select a ride to chat</div>
            <div style="font-size:.78rem;margin-top:6px;opacity:.7;">Stay in touch with users and dispatch</div>
        </div>

        <div id="rcChatHead" class="rc-chat__head" style="display:none;">
            <span class="rc-chat__status-dot" id="rcChatStatusDot"></span>
            <div>
                <div class="rc-chat__title" id="rcChatTitle">—</div>
                <div class="rc-chat__sub" id="rcChatSub">—</div>
            </div>
            <span id="rcChatStatusBadge" style="margin-left:auto;font-size:.72rem;padding:3px 10px;border-radius:20px;font-weight:600;"></span>
        </div>

        <div id="rcMessagesList" class="rc-messages" style="display:none;"></div>
        <div class="rc-typing-bar" id="rcTypingBar" style="display:none;"></div>

        <div class="rc-locked-msg" id="rcLockedMsg" style="display:none;">
            <i class="fa fa-lock" style="font-size:1.6rem;"></i>
            <div>Chat is available once you update status to En Route</div>
        </div>

        <div class="rc-closed-msg" id="rcClosedMsg" style="display:none;">
            <i class="fa fa-flag-checkered" style="font-size:1.6rem;color:rgba(255,255,255,.3);"></i>
            <div>This ride has ended — chat is read-only</div>
        </div>

        <div class="rc-input-wrap" id="rcInputWrap" style="display:none;">
            <input class="rc-input" id="rcInput" type="text" placeholder="Message user or dispatch…" maxlength="2000" autocomplete="off">
            <button class="rc-send-btn" id="rcSendBtn" type="button">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
var _rcCurrentRequestId  = null;
var _rcCurrentChatStatus = null;
var _rcTypingTimer       = null;
var _rcChatLoading       = false;   // true while thread fetch is in-flight
var _rcPendingMessages   = [];      // WS messages buffered during the fetch
var _rcPageCfg = {
    threadUrl: "{{ url('/driver/ride-chats') }}/:id/thread",
    sendUrl:   "{{ url('/driver/ride-chats') }}/:id/send",
    typingUrl: "{{ url('/driver/ride-chats') }}/:id/typing",
    csrf:      "{{ csrf_token() }}",
};
var _statusLabels = {!! json_encode(['1'=>'Pending','2'=>'Dispatched','3'=>'En Route','4'=>'Arrived','5'=>'Transporting','6'=>'Completed','7'=>'Cancelled']) !!};
var _statusColors = {!! json_encode(['1'=>'#94a3b8','2'=>'#3b82f6','3'=>'#22c55e','4'=>'#f59e0b','5'=>'#f97316','6'=>'#06b6d4','7'=>'#ef4444']) !!};

function rcEsc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function rcBubbleHtml(m) {
    var mine  = m.sender_type === 'driver';
    var align = mine ? 'right' : 'left';
    var cls   = 'rc-bubble--' + m.sender_type;
    var label = '';
    if (!mine) {
        var tag = m.sender_type === 'user'
            ? '<span style="font-size:.65rem;opacity:.6;">(User)</span>'
            : '<span style="font-size:.65rem;opacity:.6;">(Admin)</span>';
        label = '<div class="rc-sender-name">' + rcEsc(m.sender_name) + ' ' + tag + '</div>';
    }
    return '<div class="rc-bubble-wrap rc-bubble-wrap--' + align + '">' +
        label +
        '<div class="rc-bubble ' + cls + '">' + rcEsc(m.message) + '</div>' +
        '<div class="rc-bubble-time">' + rcEsc(m.time) + '</div>' +
        '</div>';
}

function rcScrollBottom() {
    var el = document.getElementById('rcMessagesList');
    if (el) el.scrollTop = el.scrollHeight;
}

function rcRenderMessages(messages) {
    var el = document.getElementById('rcMessagesList');
    if (!el) return;
    el.innerHTML = messages.length
        ? messages.map(rcBubbleHtml).join('')
        : '<div style="text-align:center;color:rgba(255,255,255,.25);font-size:.8rem;padding:32px 0;"><i class="fa fa-comments" style="font-size:1.5rem;margin-bottom:8px;display:block;opacity:.3;"></i>No messages yet</div>';
    rcScrollBottom();
}

function rcAppendMessage(m) {
    var el = document.getElementById('rcMessagesList');
    if (!el) return;
    var empty = el.querySelector('[style*="No messages"]');
    if (empty) empty.remove();
    el.insertAdjacentHTML('beforeend', rcBubbleHtml(m));
    rcScrollBottom();
}

function rcSetChatUI(chatStatus, data) {
    var els = {
        empty:  document.getElementById('rcEmptyState'),
        head:   document.getElementById('rcChatHead'),
        msgs:   document.getElementById('rcMessagesList'),
        typing: document.getElementById('rcTypingBar'),
        locked: document.getElementById('rcLockedMsg'),
        closed: document.getElementById('rcClosedMsg'),
        input:  document.getElementById('rcInputWrap'),
        dot:    document.getElementById('rcChatStatusDot'),
        title:  document.getElementById('rcChatTitle'),
        sub:    document.getElementById('rcChatSub'),
        badge:  document.getElementById('rcChatStatusBadge'),
    };
    if (els.empty)  els.empty.style.display  = 'none';
    if (els.head)   els.head.style.display   = 'flex';
    if (els.msgs)   els.msgs.style.display   = 'flex';
    if (els.typing) els.typing.style.display = 'block';
    if (els.locked) els.locked.style.display = chatStatus === 'locked' ? 'flex' : 'none';
    if (els.closed) els.closed.style.display = chatStatus === 'closed' ? 'flex' : 'none';
    if (els.input)  els.input.style.display  = chatStatus === 'active' ? 'flex' : 'none';
    if (els.msgs)   els.msgs.style.flex      = '1';

    var sl = _statusLabels[data.status] || data.status;
    var sc = _statusColors[data.status] || '#94a3b8';
    if (els.dot)   els.dot.style.background = chatStatus === 'active' ? '#22c55e' : chatStatus === 'closed' ? '#ef4444' : '#94a3b8';
    if (els.title) els.title.textContent    = (data.rreb_id || '').toUpperCase();
    if (els.sub)   els.sub.textContent      = sl;
    if (els.badge) {
        els.badge.textContent    = sl;
        els.badge.style.background = sc + '22';
        els.badge.style.color      = sc;
        els.badge.style.border     = '1px solid ' + sc + '44';
    }
    _rcCurrentChatStatus = chatStatus;
}

function rcOpenChat(requestId) {
    if (_rcCurrentRequestId) {
        var prev = document.getElementById('rcListItem' + _rcCurrentRequestId);
        if (prev) prev.classList.remove('rc-item--active');
    }
    _rcCurrentRequestId = requestId;
    window._rcCurrentRequestId = requestId;
    _rcPendingMessages  = [];   // reset queue for the new chat
    _rcChatLoading      = true; // flag: thread fetch in-flight

    var item = document.getElementById('rcListItem' + requestId);
    if (item) item.classList.add('rc-item--active');

    var badge = document.getElementById('rcItemBadge' + requestId);
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }

    var totalUnread = 0;
    document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
        if (b.style.display !== 'none') totalUnread += parseInt(b.textContent, 10) || 0;
    });
    if (typeof window.driSetChatBadge === 'function') window.driSetChatBadge(totalUnread);
    if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'ride_chat_badge_set', value: totalUnread });

    fetch(_rcPageCfg.threadUrl.replace(':id', requestId), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            rcSetChatUI(data.chat_status, data);
            rcRenderMessages(data.messages || []);

            // Flush any WS messages that arrived while the fetch was in-flight.
            // Skip messages already included in data.messages (matched by message_id).
            var historyIds = (data.messages || []).map(function (m) { return m.id; });
            _rcPendingMessages.forEach(function (m) {
                if (historyIds.indexOf(m.message_id) === -1) rcAppendMessage(m);
            });
            _rcPendingMessages = [];
            _rcChatLoading     = false;

            // Refresh the notification bell so it reflects the now-read messages.
            if (window.driNotifBell) window.driNotifBell.onNotifsRead();
            // Broadcast to other driver tabs (Notification History, etc.) so they
            // can update their unread styling without requiring a page reload.
            try {
                var _syncBc = new BroadcastChannel('rr_driver_notif_sync');
                _syncBc.postMessage({ type: 'notif_read', request_id: requestId });
                _syncBc.close();
            } catch (ex) {}
        })
        .catch(function () { _rcChatLoading = false; });
}

// Called by: driver.blade.php ride.chat.notif.read WS handler + BC listener in DOMContentLoaded.
// Clears the per-ride sidebar badge and re-syncs the nav badge.
window.driRideChatNotifsRead = function (requestId) {
    if (!requestId) return;
    var badge = document.getElementById('rcItemBadge' + requestId);
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
    var total = 0;
    document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
        if (b.style.display !== 'none') total += parseInt(b.textContent, 10) || 0;
    });
    if (typeof window.driSetChatBadge === 'function') window.driSetChatBadge(total);
    if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'ride_chat_badge_set', value: total });
};

function rcSend() {
    if (_rcCurrentChatStatus !== 'active' || !_rcCurrentRequestId) return;
    var input = document.getElementById('rcInput');
    var btn   = document.getElementById('rcSendBtn');
    if (!input) return;
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    if (btn) btn.disabled = true;

    fetch(_rcPageCfg.sendUrl.replace(':id', _rcCurrentRequestId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': _rcPageCfg.csrf },
        body: JSON.stringify({ message: msg }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (btn) btn.disabled = false;
        if (data.success) rcAppendMessage(data.message);
    })
    .catch(function () { if (btn) btn.disabled = false; });
}

function rcSendTyping() {
    if (_rcCurrentChatStatus !== 'active' || !_rcCurrentRequestId) return;
    clearTimeout(_rcTypingTimer);
    _rcTypingTimer = setTimeout(function () {
        fetch(_rcPageCfg.typingUrl.replace(':id', _rcCurrentRequestId), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _rcPageCfg.csrf },
        }).catch(function () {});
    }, 400);
}

// Insert a ride into the sidebar list in real time when its first message arrives.
function rcInsertListItem(e) {
    var list = document.getElementById('rcListItems');
    if (!list) return;

    var empty = list.querySelector('.rc-empty-list');
    if (empty) empty.remove();

    var statusColor = _statusColors[e.status] || '#94a3b8';
    var statusLabel = _statusLabels[e.status] || '';
    var rrebId      = (e.rreb_id || '#' + e.request_id).toUpperCase();
    var pickup      = (e.pickup_address || '').substring(0, 26);

    var html = '<div class="rc-item" id="rcListItem' + e.request_id + '" onclick="rcOpenChat(' + e.request_id + ')">' +
        '<span class="rc-status-dot" style="background:' + rcEsc(statusColor) + ';margin-top:5px;flex-shrink:0;width:8px;height:8px;border-radius:50%;"></span>' +
        '<div class="rc-item__body">' +
            '<div class="rc-item__id">' + rcEsc(rrebId) + '</div>' +
            '<div class="rc-item__sub">' + rcEsc(statusLabel) + ' &middot; ' + rcEsc(pickup) + '</div>' +
        '</div>' +
        '<span class="rc-item__badge" id="rcItemBadge' + e.request_id + '" style="display:none;">0</span>' +
        '</div>';

    var activeStatuses = ['3', '4', '5'];
    if (activeStatuses.indexOf(String(e.status)) !== -1) {
        list.insertAdjacentHTML('afterbegin', html);
    } else {
        var activeItems = list.querySelectorAll('.rc-item[id^="rcListItem"]');
        var insertAfter = null;
        activeItems.forEach(function (item) {
            var dot = item.querySelector('.rc-status-dot');
            if (dot) {
                var bg = dot.style.background;
                if (bg === '#22c55e' || bg === '#f59e0b' || bg === '#f97316') {
                    insertAfter = item;
                }
            }
        });
        if (insertAfter) {
            insertAfter.insertAdjacentHTML('afterend', html);
        } else {
            list.insertAdjacentHTML('afterbegin', html);
        }
    }
}

window.driRideChatMessageReceived = function (e) {
    if (e.sender_type === 'driver') return;

    // If this ride isn't in the list yet (first-ever message), insert it now
    if (!document.getElementById('rcListItem' + e.request_id)) {
        rcInsertListItem(e);
    }

    var badge = document.getElementById('rcItemBadge' + e.request_id);
    if (badge && String(e.request_id) !== String(_rcCurrentRequestId)) {
        var cur = parseInt(badge.textContent, 10) || 0;
        badge.textContent   = cur + 1;
        badge.style.display = 'inline-block';
        // Mirror the per-ride increment to the nav-level badge
        if (typeof window.driIncrementChatBadge === 'function') window.driIncrementChatBadge();
    }
    if (String(e.request_id) === String(_rcCurrentRequestId)) {
        if (_rcChatLoading) {
            _rcPendingMessages.push(e);
        } else {
            rcAppendMessage(e);
        }
        var tb = document.getElementById('rcTypingBar');
        if (tb) tb.textContent = '';
    }
};

window.driRideChatTypingReceived = function (e) {
    if (e.sender_type === 'driver') return;
    if (String(e.request_id) !== String(_rcCurrentRequestId)) return;
    var tb = document.getElementById('rcTypingBar');
    if (!tb) return;
    tb.textContent = rcEsc(e.sender_name) + ' is typing…';
    clearTimeout(window._rcTypingHideTimer);
    window._rcTypingHideTimer = setTimeout(function () { tb.textContent = ''; }, 3000);
};

document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('rcInput');
    var btn   = document.getElementById('rcSendBtn');
    if (btn)   btn.addEventListener('click', rcSend);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); rcSend(); }
        });
        input.addEventListener('input', rcSendTyping);
    }

    // Auto-open a specific chat from URL ?open= or ?view= parameter
    // (?open= is set by notification bell action_url; ?view= is the legacy alias).
    var urlParams = new URLSearchParams(window.location.search);
    var viewId    = urlParams.get('open') || urlParams.get('view');
    if (viewId) {
        var rid = parseInt(viewId, 10);
        if (rid > 0) rcOpenChat(rid);
    }

    // Cross-tab sync: when another driver tab marks a notification as read
    // (e.g. from Notification History), clear the per-ride unread badge here.
    var _notifSyncBc;
    try { _notifSyncBc = new BroadcastChannel('rr_driver_notif_sync'); } catch (ex) {}
    if (_notifSyncBc) {
        _notifSyncBc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'notif_read' && d.request_id) {
                window.driRideChatNotifsRead(d.request_id);
            }
            if (d.type === 'mark_all_read') {
                document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
                    b.textContent = '0'; b.style.display = 'none';
                });
                if (typeof window.driSetChatBadge === 'function') window.driSetChatBadge(0);
            }
        };
    }
});
</script>
@endpush
