/* ── Driver Notification Bell ───────────────────────────────────────────────
 * Combines ride-chat notifications + dispatch/assignment notifications.
 * Fetches from /driver/ride-chats/notif-bell (last 10, combined).
 *
 * DUPLICATE-PREVENTION NOTES:
 *  - onNewMessage / onNewDispatch call _fetch(true) for the authoritative
 *    unread count.  The optimistic _unread+1 gives immediate visual feedback.
 *  - 'new_notif' is NOT broadcast via BroadcastChannel.  Every tab has its
 *    own WS connection and receives the event directly; a BC delivery would
 *    cause a race-condition double-count.  Only mark_all_read is BC-synced.
 * ─────────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var _unread     = 0;
    var _open       = false;
    var _fetching   = false;
    var _lastFetch  = 0;
    var MIN_REFETCH = 3000;

    var routes = window._driRoutes || {};

    function bellBadgeEl()  { return document.getElementById('driNotifBellBadge'); }
    function bellDropEl()   { return document.getElementById('driNotifBellDropdown'); }
    function bellListEl()   { return document.getElementById('driNotifBellList'); }
    function bellBtnEl()    { return document.getElementById('driNotifBellBtn'); }
    function markAllBtnEl() { return document.getElementById('driNotifBellMarkAll'); }

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
        // NOTE: driSetChatBadge is intentionally NOT called here.
        // The bell tracks combined (chat + assignment) unread for the topbar icon.
        // The sidebar "Ride Chats" nav badge is a chat-only count managed
        // exclusively by the ride.chat.message WS handler and the server-side
        // $driChatNavCount initialised in the layout on every page load.
        // Coupling them would inflate the chat badge on every dispatch event.
    }

    // ── Item icon ─────────────────────────────────────────────────────────────
    function _iconHtml(n) {
        if (n.source === 'assignment') {
            return '<i class="fa fa-truck-medical notif-bell-icon" style="color:#a78bfa;"></i>';
        }
        if (n.message && n.message.indexOf('Admin') === 0) {
            return '<i class="fa fa-user-shield notif-bell-icon notif-bell-icon--admin"></i>';
        }
        return '<i class="fa fa-user notif-bell-icon notif-bell-icon--user"></i>';
    }

    // ── Build item ───────────────────────────────────────────────────────────
    function _buildItem(n) {
        var li = document.createElement('li');
        li.className     = 'notif-bell-item' + (n.is_read ? '' : ' notif-bell-item--unread');
        li.dataset.id    = String(n.id);
        li.dataset.src   = String(n.source || 'chat');
        li.dataset.srcId = String(n.source_id || '');
        li.dataset.requestId = String(n.emergency_request_id || '');

        li.innerHTML =
            '<div class="notif-bell-item-inner">' +
                _iconHtml(n) +
                '<div class="notif-bell-item-body">' +
                    '<div class="notif-bell-item-title">' + _esc(n.message || '') + '</div>' +
                    (n.preview ? '<div class="notif-bell-item-preview">' + _esc(n.preview) + '</div>' : '') +
                    '<div class="notif-bell-item-time">' + _esc(n.time_ago || n.time || '') +
                        (n.rreb_id ? ' &middot; ' + _esc(n.rreb_id) : '') +
                    '</div>' +
                '</div>' +
                (!n.is_read ? '<span class="notif-bell-dot"></span>' : '') +
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

        var url = routes.rideChatNotifBell;
        if (!url) return;

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
            list.innerHTML = '<div class="notif-bell-empty"><i class="fa fa-bell-slash"></i><span>No notifications</span></div>';
            return;
        }

        notifs.forEach(function (n) { list.appendChild(_buildItem(n)); });
    }

    // ── Mark read ────────────────────────────────────────────────────────────
    function _markRead(n, liEl) {
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var url  = '';
        if (n.source === 'chat') {
            url = (routes.rideChatNotifRead || '').replace(':id', n.source_id);
        } else if (n.source === 'assignment') {
            url = (routes.assignmentNotifRead || '').replace(':id', n.source_id);
        }

        // Broadcast before navigating so other open driver tabs sync immediately.
        if (_bc) try { _bc.postMessage({ type: 'notif_read', request_id: n.emergency_request_id }); } catch (ex) {}

        window.location.href = n.action_url || routes.rideChatPage || '/driver/ride-chats';

        if (url) {
            fetch(url, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function () {});
        }
    }

    // ── Mark all read ────────────────────────────────────────────────────────
    function _markAllRead() {
        var url  = routes.markAllNotifsRead;
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        if (!url) return;

        fetch(url, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function () {
            _setUnread(0);
            var items = (bellListEl() || { querySelectorAll: function () { return []; } })
                .querySelectorAll('.notif-bell-item--unread');
            Array.prototype.forEach.call(items, function (li) {
                li.classList.remove('notif-bell-item--unread');
                var dot = li.querySelector('.notif-bell-dot');
                if (dot) dot.remove();
            });
            // Sync mark-all-read to other driver tabs only.
            if (_bc) try { _bc.postMessage({ type: 'mark_all_read' }); } catch (ex) {}
        })
        .catch(function () {});
    }

    // ── BroadcastChannel: cross-tab read sync ────────────────────────────────
    // mark_all_read → reset badge to 0 on all tabs.
    // notif_read    → decrement badge + refresh list when another tab marks a
    //                 single notification as read (e.g. Notification History).
    // NOTE: 'new_notif' is intentionally NOT handled — each driver tab receives
    // the WS event on its own Pusher connection. BC for new events would cause a
    // race-condition double-count.
    var _bc;
    try { _bc = new BroadcastChannel('rr_driver_notif_sync'); } catch (e) {}

    if (_bc) {
        _bc.onmessage = function (ev) {
            var d = ev.data || {};
            if (d.type === 'mark_all_read') {
                _setUnread(0);
            }
            if (d.type === 'notif_read') {
                _setUnread(Math.max(0, _unread - 1));
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
        var wrap = document.getElementById('driNotifBellWrap');
        if (wrap && wrap.contains(ev.target)) return;
        _closeDropdown();
    }

    // ── Public API ───────────────────────────────────────────────────────────
    window.driNotifBell = {
        onNewMessage: function () {
            // Optimistic immediate increment + authoritative server sync.
            // No BC broadcast — each tab receives the WS event independently.
            _setUnread(_unread + 1);
            _fetch(true);
        },
        onNotifsRead: function () { _fetch(true); },
        onNewDispatch: function () {
            _setUnread(_unread + 1);
            _fetch(true);
        },
        refresh:   function () { _fetch(true); },
        getUnread: function () { return _unread; },
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
