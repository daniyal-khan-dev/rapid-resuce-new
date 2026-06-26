@extends('admin.layouts.admin')
@section('title', 'Ride Chats')
@section('page_title', 'Ride Chats')

@push('styles')
<style>
/* ── Two-pane wrapper ─────────────────────────────────────────────────────── */
.rc-wrap {
    display: flex;
    height: calc(100vh - 80px);
    background: var(--adm-card-bg, #1a1a2e);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 16px;
    overflow: hidden;
}

/* ── Left list pane ──────────────────────────────────────────────────────── */
.rc-list {
    width: 300px;
    min-width: 260px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,.07);
    background: rgba(255,255,255,.02);
}
.rc-list__head {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    font-size: .82rem;
    font-weight: 700;
    color: rgba(255,255,255,.6);
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
.rc-item__id {
    font-size: .78rem;
    font-weight: 700;
    color: #e2e8f0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-transform: uppercase;
}
.rc-item__sub {
    font-size: .72rem;
    color: rgba(255,255,255,.35);
    margin-top: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rc-item__badge {
    min-width: 18px;
    height: 18px;
    background: #ef4444;
    border-radius: 9px;
    color: #fff;
    font-size: .66rem;
    font-weight: 700;
    text-align: center;
    line-height: 18px;
    padding: 0 5px;
    flex-shrink: 0;
}
.rc-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 5px;
}

/* ── Right chat pane ─────────────────────────────────────────────────────── */
.rc-chat { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.rc-chat__head {
    padding: 12px 18px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255,255,255,.015);
}
.rc-chat__title { font-size: .88rem; font-weight: 700; color: #e2e8f0; }
.rc-chat__sub   { font-size: .74rem; color: rgba(255,255,255,.35); }
.rc-chat__status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}
.rc-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
}
.rc-messages::-webkit-scrollbar { width: 4px; }
.rc-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
.rc-bubble-wrap { display: flex; flex-direction: column; margin-bottom: 12px; }
.rc-bubble-wrap--right { align-items: flex-end; }
.rc-bubble-wrap--left  { align-items: flex-start; }
.rc-sender-name {
    font-size: .7rem;
    color: rgba(255,255,255,.38);
    margin-bottom: 3px;
}
.rc-bubble {
    max-width: 72%;
    padding: 8px 13px;
    border-radius: 14px;
    font-size: .84rem;
    line-height: 1.48;
    word-break: break-word;
    color: #fff;
}
.rc-bubble--user   { background: rgba(59,130,246,.22);  border-bottom-left-radius: 3px; color: #bfdbfe; }
.rc-bubble--driver { background: rgba(16,185,129,.22);  border-bottom-left-radius: 3px; color: #a7f3d0; }
.rc-bubble--admin  { background: #3b82f6; border-bottom-right-radius: 3px; }
.rc-bubble-time {
    font-size: .67rem;
    color: rgba(255,255,255,.25);
    margin-top: 3px;
}

/* input */
.rc-input-wrap {
    padding: 12px 18px;
    border-top: 1px solid rgba(255,255,255,.07);
    display: flex;
    gap: 8px;
    background: rgba(255,255,255,.015);
}
.rc-input {
    flex: 1;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 10px;
    color: #e2e8f0;
    font-size: .84rem;
    padding: 9px 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .15s;
}
.rc-input:focus { border-color: rgba(59,130,246,.5); }
.rc-input::placeholder { color: rgba(255,255,255,.25); }
.rc-send-btn {
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 18px;
    font-size: .83rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    font-family: inherit;
}
.rc-send-btn:hover { background: #2563eb; }
.rc-send-btn:disabled { opacity: .5; cursor: not-allowed; }
.rc-typing-bar {
    padding: 4px 20px;
    font-size: .72rem;
    color: rgba(255,255,255,.35);
    min-height: 22px;
}
.rc-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,.2);
}
.rc-locked-msg, .rc-closed-msg {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: rgba(255,255,255,.3);
    font-size: .84rem;
}
.rc-empty-list {
    padding: 24px 16px;
    text-align: center;
    color: rgba(255,255,255,.25);
    font-size: .8rem;
}

@media (max-width: 700px) {
    .rc-list { width: 100%; }
    .rc-wrap  { flex-direction: column; height: auto; min-height: 600px; }
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

    {{-- Left pane: list of rides --}}
    <div class="rc-list">
        <div class="rc-list__head">
            <i class="fa fa-comments" style="font-size:.85rem;"></i> Ride Chats
            @if($unread > 0)
                <span style="background:#ef4444;color:#fff;border-radius:9px;font-size:.68rem;padding:0 7px;line-height:18px;height:18px;display:inline-block;">
                    {{ $unread }}
                </span>
            @endif
        </div>
        <div class="rc-list__items" id="rcListItems">
            @forelse($requests as $r)
            <div class="rc-item {{ $r['unread'] > 0 ? '' : '' }}"
                 id="rcListItem{{ $r['id'] }}"
                 onclick="rcOpenChat({{ $r['id'] }})">
                <span class="rc-status-dot" style="background:{{ $statusColors[$r['status']] ?? '#94a3b8' }};"></span>
                <div class="rc-item__body">
                    <div class="rc-item__id">
                        {{ strtoupper($r['rreb_id']) }}
                        @if(!empty($r['driver_name']) && $r['driver_name'] !== '—')
                            <span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;font-size:.7rem;">{{ $r['driver_name'] }}</span>
                        @endif
                    </div>
                    <div class="rc-item__sub">
                        {{ $statusLabels[$r['status']] ?? $r['status'] }} &middot;
                        {{ \Illuminate\Support\Str::limit($r['pickup_address'] ?? '', 28) }}
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
                <i class="fa fa-comments" style="font-size:1.6rem;margin-bottom:8px;opacity:.3;display:block;"></i>
                No conversations yet.<br>
                Rides with messages will appear here.
            </div>
            @endforelse
        </div>
    </div>

    {{-- Right pane: chat window --}}
    <div class="rc-chat" id="rcChatPane">

        {{-- Empty state --}}
        <div class="rc-empty-state" id="rcEmptyState">
            <i class="fa fa-comments" style="font-size:2.5rem;margin-bottom:14px;"></i>
            <div style="font-size:.9rem;font-weight:600;">Select a ride to view the chat</div>
            <div style="font-size:.78rem;margin-top:6px;opacity:.7;">Conversations between drivers, users and dispatch</div>
        </div>

        {{-- Active chat header (hidden until a ride is selected) --}}
        <div id="rcChatHead" class="rc-chat__head" style="display:none;">
            <span class="rc-chat__status-dot" id="rcChatStatusDot" style="background:#94a3b8;"></span>
            <div>
                <div class="rc-chat__title" id="rcChatTitle">—</div>
                <div class="rc-chat__sub" id="rcChatSub">—</div>
            </div>
            <div style="margin-left:auto;">
                <span id="rcChatStatusBadge" style="font-size:.72rem;padding:3px 10px;border-radius:20px;font-weight:600;"></span>
            </div>
        </div>

        {{-- Messages --}}
        <div id="rcMessagesList" class="rc-messages" style="display:none;"></div>

        {{-- Typing indicator --}}
        <div class="rc-typing-bar" id="rcTypingBar" style="display:none;"></div>

        {{-- Locked notice --}}
        <div class="rc-locked-msg" id="rcLockedMsg" style="display:none;">
            <i class="fa fa-lock" style="font-size:1.6rem;"></i>
            <div>Chat unlocks when the driver is En Route</div>
        </div>

        {{-- Closed notice --}}
        <div class="rc-closed-msg" id="rcClosedMsg" style="display:none;">
            <i class="fa fa-flag-checkered" style="font-size:1.6rem;color:rgba(255,255,255,.3);"></i>
            <div>This ride has ended — chat is read-only</div>
        </div>

        {{-- Input --}}
        <div class="rc-input-wrap" id="rcInputWrap" style="display:none;">
            <input class="rc-input" id="rcInput" type="text" placeholder="Type a message to user and driver…" maxlength="2000" autocomplete="off">
            <button class="rc-send-btn" id="rcSendBtn" type="button">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
var _rcCurrentRequestId = null;
var _rcCurrentChatStatus = null;
var _rcTypingTimer = null;
var _rcPageCfg = {
    threadUrl: "{{ url('/admin/ride-chats') }}/:id/thread",
    sendUrl:   "{{ url('/admin/ride-chats') }}/:id/send",
    typingUrl: "{{ url('/admin/ride-chats') }}/:id/typing",
    csrf:      "{{ csrf_token() }}",
};
var _statusLabels = {!! json_encode(['1'=>'Pending','2'=>'Dispatched','3'=>'En Route','4'=>'Arrived','5'=>'Transporting','6'=>'Completed','7'=>'Cancelled']) !!};
var _statusColors = {!! json_encode(['1'=>'#94a3b8','2'=>'#3b82f6','3'=>'#22c55e','4'=>'#f59e0b','5'=>'#f97316','6'=>'#06b6d4','7'=>'#ef4444']) !!};

function rcEsc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function rcBubbleHtml(m) {
    var mine  = m.sender_type === 'admin';
    var align = mine ? 'right' : 'left';
    var cls   = 'rc-bubble--' + m.sender_type;
    var label = '';
    if (!mine) {
        var typeTag = m.sender_type === 'user'
            ? '<span style="font-size:.65rem;opacity:.6;">(User)</span>'
            : '<span style="font-size:.65rem;opacity:.6;">(Driver)</span>';
        label = '<div class="rc-sender-name">' + rcEsc(m.sender_name) + ' ' + typeTag + '</div>';
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

function rcAppendMessage(m) {
    var el = document.getElementById('rcMessagesList');
    if (!el) return;
    el.insertAdjacentHTML('beforeend', rcBubbleHtml(m));
    rcScrollBottom();
}

function rcRenderMessages(messages) {
    var el = document.getElementById('rcMessagesList');
    if (!el) return;
    if (!messages.length) {
        el.innerHTML = '<div style="text-align:center;color:rgba(255,255,255,.25);font-size:.8rem;padding:32px 0;"><i class="fa fa-comments" style="font-size:1.5rem;margin-bottom:8px;display:block;opacity:.3;"></i>No messages yet</div>';
    } else {
        el.innerHTML = messages.map(rcBubbleHtml).join('');
    }
    rcScrollBottom();
}

function rcSetChatUI(chatStatus, data) {
    var empty   = document.getElementById('rcEmptyState');
    var head    = document.getElementById('rcChatHead');
    var msgEl   = document.getElementById('rcMessagesList');
    var typingEl= document.getElementById('rcTypingBar');
    var lockedEl= document.getElementById('rcLockedMsg');
    var closedEl= document.getElementById('rcClosedMsg');
    var inputEl = document.getElementById('rcInputWrap');
    var dotEl   = document.getElementById('rcChatStatusDot');
    var titleEl = document.getElementById('rcChatTitle');
    var subEl   = document.getElementById('rcChatSub');
    var badgeEl = document.getElementById('rcChatStatusBadge');

    if (empty)    empty.style.display   = 'none';
    if (head)     head.style.display    = 'flex';
    if (msgEl)    msgEl.style.display   = 'flex';
    if (typingEl) typingEl.style.display= 'block';
    if (lockedEl) lockedEl.style.display= chatStatus === 'locked' ? 'flex' : 'none';
    if (closedEl) closedEl.style.display= chatStatus === 'closed' ? 'flex' : 'none';
    if (inputEl)  inputEl.style.display = chatStatus === 'active' ? 'flex' : 'none';
    if (msgEl)    msgEl.style.flex      = chatStatus === 'active' || chatStatus === 'closed' ? '1' : '0';

    var statusLabel = _statusLabels[data.status] || data.status;
    var statusColor = _statusColors[data.status] || '#94a3b8';

    if (dotEl)   dotEl.style.background  = chatStatus === 'active' ? '#22c55e' : chatStatus === 'closed' ? '#ef4444' : '#94a3b8';
    if (titleEl) titleEl.textContent      = (data.rreb_id || '').toUpperCase();
    if (subEl)   subEl.textContent        = (data.driver_name ? 'Driver: ' + data.driver_name + '  ·  ' : '') + (data.status ? statusLabel : '');
    if (badgeEl) {
        badgeEl.textContent     = statusLabel;
        badgeEl.style.background = statusColor + '22';
        badgeEl.style.color      = statusColor;
        badgeEl.style.border     = '1px solid ' + statusColor + '44';
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

    var item = document.getElementById('rcListItem' + requestId);
    if (item) item.classList.add('rc-item--active');

    // clear unread badge for this item
    var badge = document.getElementById('rcItemBadge' + requestId);
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }

    // recalc sidebar badge
    var totalUnread = 0;
    document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
        var n = parseInt(b.textContent, 10) || 0;
        if (b.style.display !== 'none') totalUnread += n;
    });
    if (typeof window.admSetChatBadge === 'function') window.admSetChatBadge(totalUnread);
    if (typeof admBroadcastTabSync === 'function') admBroadcastTabSync({ type: 'ride_chat_badge_set', value: totalUnread });

    // load thread
    var url = _rcPageCfg.threadUrl.replace(':id', requestId);
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            rcSetChatUI(data.chat_status, data);
            rcRenderMessages(data.messages || []);
            if (typeof window.admRideChatNotifsCleared === 'function' && data.ride_chat_notifs_cleared) {
                window.admRideChatNotifsCleared(data.ride_chat_notifs_cleared, requestId);
            }
            try {
                var _syncBc = new BroadcastChannel('rr_admin_notif_sync');
                _syncBc.postMessage({ type: 'notif_read', request_id: requestId });
                _syncBc.close();
            } catch (ex) {}
        })
        .catch(function () {});
}

// Called by: realtime.js ride.chat.notif.read WS handler + BC listener in DOMContentLoaded.
// Clears the per-ride sidebar badge and re-syncs the nav badge.
window.admRideChatNotifsRead = function (requestId) {
    if (!requestId) return;
    var badge = document.getElementById('rcItemBadge' + requestId);
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
    var total = 0;
    document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
        if (b.style.display !== 'none') total += parseInt(b.textContent, 10) || 0;
    });
    if (typeof window.admSetChatBadge === 'function') window.admSetChatBadge(total);
    if (typeof admBroadcastTabSync === 'function') admBroadcastTabSync({ type: 'ride_chat_badge_set', value: total });
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

    var url = _rcPageCfg.sendUrl.replace(':id', _rcCurrentRequestId);
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': _rcPageCfg.csrf,
        },
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
        var url = _rcPageCfg.typingUrl.replace(':id', _rcCurrentRequestId);
        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _rcPageCfg.csrf },
        }).catch(function () {});
    }, 400);
}

// Insert a ride into the sidebar list in real time when its first message arrives.
// Uses data embedded in the ride.chat.message WS event (status, pickup_address,
// driver_name) so no extra HTTP request is needed.
function rcInsertListItem(e) {
    var list = document.getElementById('rcListItems');
    if (!list) return;

    // Remove the "no conversations yet" placeholder if present
    var empty = list.querySelector('.rc-empty-list');
    if (empty) empty.remove();

    var statusColor = _statusColors[e.status] || '#94a3b8';
    var statusLabel = _statusLabels[e.status] || '';
    var rrebId      = (e.rreb_id || '#' + e.request_id).toUpperCase();
    var pickup      = (e.pickup_address || '').substring(0, 28);
    var driverPart  = (e.driver_name && e.driver_name !== '—')
        ? '<span style="font-weight:400;color:rgba(255,255,255,.35);margin-left:6px;font-size:.7rem;">' + rcEsc(e.driver_name) + '</span>'
        : '';

    var html = '<div class="rc-item" id="rcListItem' + e.request_id + '" onclick="rcOpenChat(' + e.request_id + ')">' +
        '<span class="rc-status-dot" style="background:' + rcEsc(statusColor) + ';margin-top:5px;flex-shrink:0;width:8px;height:8px;border-radius:50%;"></span>' +
        '<div class="rc-item__body">' +
            '<div class="rc-item__id">' + rcEsc(rrebId) + driverPart + '</div>' +
            '<div class="rc-item__sub">' + rcEsc(statusLabel) + ' &middot; ' + rcEsc(pickup) + '</div>' +
        '</div>' +
        '<span class="rc-item__badge" id="rcItemBadge' + e.request_id + '" style="display:none;">0</span>' +
        '</div>';

    // Active rides (status 3-5) go to the very top; others go after active rides
    var activeStatuses = ['3', '4', '5'];
    if (activeStatuses.indexOf(String(e.status)) !== -1) {
        list.insertAdjacentHTML('afterbegin', html);
    } else {
        // Insert after the last active ride item, or at the top if none
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

window.admRideChatMessageReceived = function (e) {
    if (e.sender_type === 'admin') return;

    // If this ride isn't in the list yet (first-ever message), insert it now
    if (!document.getElementById('rcListItem' + e.request_id)) {
        rcInsertListItem(e);
    }

    // Update unread badge (only if this chat isn't currently open)
    var badge = document.getElementById('rcItemBadge' + e.request_id);
    if (badge) {
        var cur = parseInt(badge.textContent, 10) || 0;
        if (String(e.request_id) !== String(_rcCurrentRequestId)) {
            badge.textContent   = cur + 1;
            badge.style.display = 'inline-block';
            // Mirror the per-ride increment to the nav-level badge
            if (typeof window.admIncrementChatBadge === 'function') window.admIncrementChatBadge();
        }
    }
    // Append to messages if this request is open
    if (String(e.request_id) === String(_rcCurrentRequestId)) {
        rcAppendMessage(e);
        var typing = document.getElementById('rcTypingBar');
        if (typing) typing.textContent = '';
    }
};

window.admRideChatTypingReceived = function (e) {
    if (e.sender_type === 'admin') return;
    if (String(e.request_id) !== String(_rcCurrentRequestId)) return;
    var typing = document.getElementById('rcTypingBar');
    if (!typing) return;
    typing.textContent = rcEsc(e.sender_name) + ' is typing…';
    clearTimeout(window._rcTypingHideTimer);
    window._rcTypingHideTimer = setTimeout(function () { typing.textContent = ''; }, 3000);
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

    // Auto-open a specific conversation when the page is reached from a
    // notification link (?open=requestId) or from the bell dropdown.
    var urlParams = new URLSearchParams(window.location.search);
    var openId    = urlParams.get('open') || urlParams.get('view');
    if (openId) {
        var rid = parseInt(openId, 10);
        if (rid > 0) rcOpenChat(rid);
    }

    // Cross-tab sync: when another admin tab marks a notification as read
    // (e.g. from Notification History), clear the per-ride unread badge here.
    var _notifSyncBc;
    try { _notifSyncBc = new BroadcastChannel('rr_admin_notif_sync'); } catch (ex) {}
    if (_notifSyncBc) {
        _notifSyncBc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'notif_read' && d.request_id) {
                window.admRideChatNotifsRead(d.request_id);
            }
            if (d.type === 'mark_all_read') {
                document.querySelectorAll('[id^="rcItemBadge"]').forEach(function (b) {
                    b.textContent = '0'; b.style.display = 'none';
                });
                if (typeof window.admSetChatBadge === 'function') window.admSetChatBadge(0);
            }
        };
    }
});
</script>
@endpush
