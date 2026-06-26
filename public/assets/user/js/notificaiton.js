/* ── User Notification Bell ─────────────────────────────────────────────────
 * Combines ride-chat + ride-status notifications.
 * Fetches from /ride-chat/notif-bell (last 10, combined, human-readable).
 *
 * DUPLICATE-PREVENTION NOTES:
 *  - onNewMessage / onNewStatusNotif call _fetch(true) for the authoritative
 *    unread count from the server.  The optimistic _unread+1 gives immediate
 *    visual feedback while the fetch is in flight.
 *  - 'new_notif' is NOT broadcast via BroadcastChannel.  Every user tab has
 *    its own WS Pusher connection and receives events directly — a BC delivery
 *    would cause a race-condition double-count.  Only mark_all_read and
 *    notif_read (single-read) are synced via BroadcastChannel.
 *  - The user Pusher connection in user_header.blade.php is the SINGLE source
 *    of WS events for this bell.  ride_chat.js reuses that same connection
 *    instead of creating a duplicate subscription.
 * ─────────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var _unread     = 0;
    var _open       = false;
    var _fetching   = false;
    var _lastFetch  = 0;
    var MIN_REFETCH = 3000;

    var _routes = window._rrNotifRoutes || {};

    function bellBadgeEl()  { return document.getElementById('rrNotifBellBadge'); }
    function bellDropEl()   { return document.getElementById('rrNotifBellDropdown'); }
    function bellListEl()   { return document.getElementById('rrNotifBellList'); }
    function bellBtnEl()    { return document.getElementById('rrNotifBellBtn'); }
    function markAllBtnEl() { return document.getElementById('rrNotifBellMarkAll'); }

    // ── Badge ────────────────────────────────────────────────────────────────
    function _renderBellBadge(n) {
        var el = bellBadgeEl();
        if (!el) return;
        if (n > 0) {
            el.textContent   = n > 99 ? '99+' : String(n);
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    }

    function _setUnread(n) {
        _unread = Math.max(0, n);
        _renderBellBadge(_unread);
    }

    // ── Item icon ─────────────────────────────────────────────────────────────
    function _iconHtml(n) {
        if (n.source === 'status') {
            return '<i class="fa fa-truck-medical rr-notif-item-icon rr-notif-icon--status"></i>';
        }
        if (n.message && n.message.indexOf('Admin') === 0) {
            return '<i class="fa fa-user-shield rr-notif-item-icon rr-notif-icon--admin"></i>';
        }
        if (n.message && n.message.indexOf('Driver') === 0) {
            return '<i class="fa fa-id-card rr-notif-item-icon rr-notif-icon--driver"></i>';
        }
        return '<i class="fa fa-bell rr-notif-item-icon rr-notif-icon--default"></i>';
    }

    // ── Build item ───────────────────────────────────────────────────────────
    function _buildItem(n) {
        var li = document.createElement('li');
        li.className     = 'rr-notif-item' + (n.is_read ? '' : ' rr-notif-item--unread');
        li.dataset.id    = String(n.id);
        li.dataset.src   = String(n.source || 'chat');
        li.dataset.srcId = String(n.source_id || '');
        li.dataset.requestId = String(n.emergency_request_id || '');

        li.innerHTML =
            '<div class="rr-notif-item-inner">' +
                _iconHtml(n) +
                '<div class="rr-notif-item-body">' +
                    '<div class="rr-notif-item-title">' + _esc(n.message || '') + '</div>' +
                    (n.preview ? '<div class="rr-notif-item-preview">' + _esc(n.preview) + '</div>' : '') +
                    '<div class="rr-notif-item-time">' + _esc(n.time_ago || n.time || '') +
                        (n.rreb_id ? ' &middot; ' + _esc(n.rreb_id) : '') +
                    '</div>' +
                '</div>' +
                (!n.is_read ? '<span class="rr-notif-dot"></span>' : '') +
            '</div>';

        li.addEventListener('click', function () { _markRead(n, li); });
        return li;
    }

    function _esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Fetch & render ───────────────────────────────────────────────────────
    function _fetch(force) {
        var now = Date.now();
        if (!force && now - _lastFetch < MIN_REFETCH) return;
        if (_fetching) return;

        var url = _routes.notifBell || '/ride-chat/notif-bell';
        _fetching  = true;
        _lastFetch = now;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                _fetching = false;
                if (!data) return;
                _setUnread(data.unread || 0);
                _renderList(data.notifications || []);
            })
            .catch(function () { _fetching = false; });
    }

    function _renderList(notifs) {
        var list = bellListEl();
        if (!list) return;
        list.innerHTML = '';

        if (!notifs.length) {
            list.innerHTML = '<li class="rr-notif-empty"><i class="fa fa-bell-slash"></i><span>No notifications</span></li>';
            return;
        }

        notifs.forEach(function (n) { list.appendChild(_buildItem(n)); });
    }

    // ── Mark read (bell dropdown click) ──────────────────────────────────────
    function _markRead(n, liEl) {
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var url  = '';
        if (n.source === 'chat') {
            url = (_routes.notifRead || '/ride-chat/notif/:id/read').replace(':id', n.source_id);
        } else if (n.source === 'status') {
            url = (_routes.statusNotifRead || '/ride-chat/status-notif/:id/read').replace(':id', n.source_id);
        }

        // Optimistically update this tab's badge immediately.
        if (!n.is_read) {
            _setUnread(Math.max(0, _unread - 1));
            liEl.classList.remove('rr-notif-item--unread');
            var dot = liEl.querySelector('.rr-notif-dot');
            if (dot) dot.remove();
            n.is_read = true;
        }

        // Fire mark-as-read to server (fire-and-forget, before navigate).
        if (url) {
            fetch(url, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function () {});
        }

        // Broadcast to other user tabs (bell + FAB) so they sync immediately.
        if (_bc) try { _bc.postMessage({ type: 'notif_read', request_id: n.emergency_request_id, count: 1 }); } catch (ex) {}
        // Also clear the tracking-page FAB on any sibling tracking tab.
        var _chatBc;
        try { _chatBc = new BroadcastChannel('rr_user_chat_sync'); } catch (ex2) {}
        if (_chatBc) try { _chatBc.postMessage({ type: 'user_chat_clear', request_id: n.emergency_request_id }); _chatBc.close(); } catch (ex3) {}

        window.location.href = n.action_url || ('/tracking/' + n.emergency_request_id);
    }

    // ── Mark all read ────────────────────────────────────────────────────────
    function _markAllRead() {
        var url  = _routes.markAllNotifsRead || '/ride-chat/notifications/mark-all-read';
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        fetch(url, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function () {
            _setUnread(0);
            var items = (bellListEl() || { querySelectorAll: function () { return []; } })
                .querySelectorAll('.rr-notif-item--unread');
            Array.prototype.forEach.call(items, function (li) {
                li.classList.remove('rr-notif-item--unread');
                var dot = li.querySelector('.rr-notif-dot');
                if (dot) dot.remove();
            });
            // Sync mark-all-read to other user tabs only.
            if (_bc) try { _bc.postMessage({ type: 'mark_all_read' }); } catch (ex) {}
        })
        .catch(function () {});
    }

    // ── BroadcastChannel: cross-tab read sync ────────────────────────────────
    // mark_all_read → reset badge to 0 on all tabs.
    // notif_read    → decrement badge by count + refresh list when another tab
    //                 marks notification(s) as read.
    // 'new_notif' is intentionally NOT handled here.  Every user tab has its
    // own Pusher WS connection and receives events directly — forwarding via BC
    // would race with the tab's own WS handler and cause double-increment.
    var _bc;
    try { _bc = new BroadcastChannel('rr_user_notif_sync'); } catch (e) {}

    if (_bc) {
        _bc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'mark_all_read') {
                _setUnread(0);
                var items = (bellListEl() || { querySelectorAll: function () { return []; } })
                    .querySelectorAll('.rr-notif-item--unread');
                Array.prototype.forEach.call(items, function (li) {
                    li.classList.remove('rr-notif-item--unread');
                    var dot = li.querySelector('.rr-notif-dot');
                    if (dot) dot.remove();
                });
            }
            if (d.type === 'notif_read') {
                var decrement = (d.count && d.count > 0) ? d.count : 1;
                _setUnread(Math.max(0, _unread - decrement));
                _fetch(true);
            }
            // notif_auto_read: a tracking-page tab silently read a message for
            // a ride it was actively viewing.  This tab never incremented for
            // that message (header handler suppressed it via localStorage), so
            // just re-fetch the authoritative count to stay in sync.
            if (d.type === 'notif_auto_read') {
                _fetch(true);
            }
        };
    }

    // ── Dropdown toggle ──────────────────────────────────────────────────────
    function _openDropdown() {
        var drop = bellDropEl();
        if (!drop) return;
        _open              = true;
        drop.style.display = 'block';
        _fetch(true);
        setTimeout(function () {
            document.addEventListener('click', _outsideClick, { once: true });
        }, 0);
    }

    function _closeDropdown() {
        var drop = bellDropEl();
        if (!drop) return;
        _open              = false;
        drop.style.display = 'none';
    }

    function _outsideClick(ev) {
        var wrap = document.getElementById('rrNotifBellWrap');
        if (wrap && wrap.contains(ev.target)) return;
        _closeDropdown();
    }

    // ── Public API ───────────────────────────────────────────────────────────
    window.rrNotifBell = {
        onNewMessage: function () {
            // Optimistic immediate increment + authoritative server sync.
            // No BC broadcast — each tab receives the WS event independently.
            _setUnread(_unread + 1);
            _fetch(true);
        },
        // Called when the server confirms notification(s) were read.
        // count: number of notifications that were cleared (from WS payload).
        //        When provided, do an immediate optimistic decrement before
        //        the authoritative fetch completes.
        onNotifsRead: function (count) {
            if (count && count > 0) {
                _setUnread(Math.max(0, _unread - count));
            }
            _fetch(true);
        },
        onNewStatusNotif: function () {
            _setUnread(_unread + 1);
            _fetch(true);
        },
        refresh:   function () { _fetch(true); },
        getUnread: function () { return _unread; },
    };

    // ── rrRideChatNotifsCleared ───────────────────────────────────────────────
    // Called by ride_chat.js when loadThread() returns and the server has
    // already marked notifications as read (ride_chat_notifs_cleared: N).
    // Immediately decrements the bell badge on this tab and syncs all other
    // open tabs via BroadcastChannel without waiting for a WS round-trip.
    window.rrRideChatNotifsCleared = function (count, requestId) {
        if (!count || count <= 0) return;

        // Immediate optimistic decrement on this tab.
        _setUnread(Math.max(0, _unread - count));
        // Authoritative re-fetch to confirm correct server state.
        _fetch(true);

        // Sync other tabs: bell badge + notification history.
        if (_bc) {
            try { _bc.postMessage({ type: 'notif_read', request_id: requestId, count: count }); } catch (ex) {}
        }
        // Sync tracking-page FAB badge on sibling tabs.
        var chatBc;
        try { chatBc = new BroadcastChannel('rr_user_chat_sync'); } catch (ex2) {}
        if (chatBc) {
            try { chatBc.postMessage({ type: 'user_chat_clear', request_id: requestId }); chatBc.close(); } catch (ex3) {}
        }
    };

    // ── Init ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        _fetch(true);

        var btn = bellBtnEl();
        if (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                if (_open) { _closeDropdown(); } else { _openDropdown(); }
            });
        }

        var markAll = markAllBtnEl();
        if (markAll) {
            markAll.addEventListener('click', function (ev) {
                ev.stopPropagation();
                _markAllRead();
            });
        }
    });
})();
