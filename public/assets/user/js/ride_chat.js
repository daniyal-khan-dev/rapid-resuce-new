/* RIDE CHAT — USER SIDE */
(function () {
    var _cfg        = window._rideChatCfg || {};
    var _requestId  = _cfg.requestId;
    var _userId     = _cfg.userId;
    var _chatStatus = _cfg.chatStatus;   // 'locked' | 'active' | 'closed'
    var _typing     = null;
    var _typingTimer = null;
    var _pusher     = null;

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function scrollBottom() {
        var box = document.getElementById('rcMsgList');
        if (box) box.scrollTop = box.scrollHeight;
    }

    function senderColor(type) {
        if (type === 'user')   return '#3b82f6';
        if (type === 'driver') return '#10b981';
        return '#8b5cf6';
    }

    function buildBubble(m, mine) {
        var align = mine ? 'flex-end' : 'flex-start';
        var bg    = mine ? senderColor('user') : (m.sender_type === 'driver' ? 'rgba(16,185,129,.18)' : 'rgba(139,92,246,.18)');
        var col   = mine ? '#fff' : '#e2e8f0';
        return '<div style="display:flex;flex-direction:column;align-items:' + align + ';margin-bottom:10px;">' +
            (!mine ? '<span style="font-size:.7rem;color:rgba(255,255,255,.40);margin-bottom:2px;">' + esc(m.sender_name) +
                (m.sender_type === 'admin' ? ' <span style="font-size:.65rem;opacity:.6;">(Admin)</span>' : ' <span style="font-size:.65rem;opacity:.6;">(Driver)</span>') +
                '</span>' : '') +
            '<div style="max-width:80%;background:' + bg + ';color:' + col + ';border-radius:12px;' +
                (mine ? 'border-bottom-right-radius:3px;' : 'border-bottom-left-radius:3px;') +
                'padding:8px 12px;font-size:.84rem;line-height:1.45;word-break:break-word;">' + esc(m.message) + '</div>' +
            '<span style="font-size:.68rem;color:rgba(255,255,255,.28);margin-top:3px;">' + esc(m.time) + '</span>' +
        '</div>';
    }

    function renderMessages(messages) {
        var box = document.getElementById('rcMsgList');
        if (!box) return;
        if (!messages.length) {
            box.innerHTML = '<div style="text-align:center;color:rgba(255,255,255,.3);font-size:.8rem;padding:24px 0;">No messages yet. Say hello!</div>';
            return;
        }
        box.innerHTML = messages.map(function (m) {
            return buildBubble(m, m.sender_type === 'user');
        }).join('');
        scrollBottom();
    }

    function appendBubble(m, mine) {
        var box = document.getElementById('rcMsgList');
        if (!box) return;
        var empty = box.querySelector('[style*="No messages"]');
        if (empty) empty.remove();
        box.insertAdjacentHTML('beforeend', buildBubble(m, mine));
        scrollBottom();
    }

    function loadThread() {
        if (!_requestId) return;
        fetch(_cfg.threadUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _chatStatus = data.chat_status;
                updateChatStatus(data.chat_status, data.status);
                renderMessages(data.messages || []);
                if (typeof window.rrRideChatNotifsCleared === 'function' && data.ride_chat_notifs_cleared) {
                    window.rrRideChatNotifsCleared(data.ride_chat_notifs_cleared, _requestId);
                }
            })
            .catch(function () {});
    }

    function updateChatStatus(cs, status) {
        var lockedEl  = document.getElementById('rcLocked');
        var closedEl  = document.getElementById('rcClosed');
        var inputWrap = document.getElementById('rcInputWrap');
        var header    = document.getElementById('rcStatusDot');

        if (lockedEl)  lockedEl.style.display  = cs === 'locked'  ? 'flex' : 'none';
        if (closedEl)  closedEl.style.display  = cs === 'closed'  ? 'flex' : 'none';
        if (inputWrap) inputWrap.style.display = cs === 'active'  ? 'flex' : 'none';
        if (header) {
            header.style.background = cs === 'active' ? '#22c55e' : cs === 'closed' ? '#ef4444' : '#94a3b8';
        }
        _chatStatus = cs;
    }

    function sendMessage() {
        if (_chatStatus !== 'active') return;
        var input = document.getElementById('rcInput');
        if (!input) return;
        var msg = input.value.trim();
        if (!msg) return;
        input.value = '';
        input.disabled = true;

        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(_cfg.sendUrl, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  csrf ? csrf.content : '',
            },
            body: JSON.stringify({ message: msg }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            input.disabled = false;
            if (data.success) {
                appendBubble(data.message, true);
            }
        })
        .catch(function () { input.disabled = false; });
    }

    function sendTyping() {
        if (_chatStatus !== 'active') return;
        clearTimeout(_typingTimer);
        _typingTimer = setTimeout(function () {
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch(_cfg.typingUrl, {
                method:  'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
            }).catch(function () {});
        }, 400);
    }

    function showTypingIndicator(name) {
        var el = document.getElementById('rcTyping');
        if (!el) return;
        el.textContent = esc(name) + ' is typing…';
        el.style.display = 'block';
        clearTimeout(window._rcTypingHide);
        window._rcTypingHide = setTimeout(function () { el.style.display = 'none'; }, 3000);
    }

    // ── Auto-read helper ─────────────────────────────────────────────────────
    // Called when a new message arrives and the user is actively viewing this
    // ride's tracking page.  Marks the notification as read on the server,
    // then — AFTER the server confirms — refreshes the badge and tells all
    // other open tabs to sync.  The refresh is intentionally deferred until
    // the POST resolves so the bell never fetches a stale unread count while
    // the notification is still being written as read on the server.
    function _autoMarkRead(e) {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.content : '';

        // Build the mark-as-read promise (resolves immediately when no notif id).
        var postDone;
        if (e.user_notif_id) {
            postDone = fetch('/ride-chat/notif/' + e.user_notif_id + '/read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            });
        } else {
            postDone = Promise.resolve();
        }

        postDone
            .then(function () {
                // Server has committed the read — now it is safe to re-fetch
                // the badge count.  This avoids getting unread: 1 back because
                // the fetch raced ahead of the write.
                if (window.rrNotifBell && typeof window.rrNotifBell.refresh === 'function') {
                    window.rrNotifBell.refresh();
                }

                // Tell all other open tabs to refresh without an optimistic
                // decrement (they never incremented for this suppressed message).
                var bc;
                try { bc = new BroadcastChannel('rr_user_notif_sync'); } catch (ex) {}
                if (bc) {
                    try { bc.postMessage({ type: 'notif_auto_read', request_id: e.request_id }); bc.close(); } catch (ex) {}
                }
            })
            .catch(function () {
                // On network error still refresh so the badge stays consistent.
                if (window.rrNotifBell && typeof window.rrNotifBell.refresh === 'function') {
                    window.rrNotifBell.refresh();
                }
            });
    }

    function setupWS() {
        if (typeof Pusher === 'undefined') return;

        try {
            // Reuse the notification-bell Pusher connection created by user_header.blade.php
            // instead of opening a second WebSocket to the same private channel.
            // Two separate subscriptions to private-contact.user.{id} on different Pusher
            // instances cause duplicate WS event deliveries and double-count the bell badge.
            var ch;
            if (window._rrUserNotifChannel) {
                ch      = window._rrUserNotifChannel;
                _pusher = window._rrUserNotifPusher;
            } else {
                var reverbCfg = window._rrReverb || {};
                if (!reverbCfg.key) return;
                _pusher = new Pusher(reverbCfg.key, {
                    wsHost:            reverbCfg.wsHost,
                    wsPort:            reverbCfg.wsPort,
                    wssPort:           reverbCfg.wssPort,
                    forceTLS:          reverbCfg.forceTLS,
                    enabledTransports: reverbCfg.enabledTransports || ['ws'],
                    cluster:           'mt1',
                    disableStats:      true,
                    authEndpoint:      '/broadcasting/auth',
                    auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } }
                });
                ch = _pusher.subscribe('private-contact.user.' + _userId);
            }

            ch.bind('ride.chat.message', function (e) {
                if (String(e.request_id) !== String(_requestId)) return;
                if (e.sender_type === 'user') return;
                appendBubble(e, false);
                var typingEl = document.getElementById('rcTyping');
                if (typingEl) typingEl.style.display = 'none';

                // Auto-read: this tab is actively viewing this ride's tracking page.
                // Mark the notification as read immediately; suppress ALL badge bumps.
                if (typeof window.rrIsActiveChatFor === 'function' && window.rrIsActiveChatFor(e.request_id)) {
                    _autoMarkRead(e);
                    return;
                }

                // Normal path: bump the chat FAB badge (bumpBadge skips if panel open).
                if (typeof window.rrChatNotifyNewMessage === 'function') {
                    window.rrChatNotifyNewMessage();
                }
            });

            ch.bind('ride.chat.typing', function (e) {
                if (String(e.request_id) !== String(_requestId)) return;
                if (e.sender_type === 'user') return;
                showTypingIndicator(e.sender_name);
            });

            ch.bind('ride.status.changed', function (e) {
                if (String(e.request_id) !== String(_requestId)) return;
                var cs = e.chat_status || (_chatStatus);
                updateChatStatus(cs, e.status);
            });

            // ── Real-time ride status sync ──────────────────────────────────
            // Fires whenever the driver updates the ride status. Updates the
            // tracking page UI (status label, timeline, map route) and keeps
            // the chat panel in sync with the new ride state.
            ch.bind('request.status.updated', function (e) {
                if (String(e.request_id) !== String(_requestId)) return;

                var s = String(e.status);

                // Update the tracking page if it is loaded
                if (typeof window.handleStatusUpdate === 'function') {
                    window.handleStatusUpdate({
                        status:     s,
                        driver_lat: e.driver_lat || null,
                        driver_lng: e.driver_lng || null,
                    });
                }

                // Sync chat panel status with new ride state
                if (s === '6' || s === '7') {
                    updateChatStatus('closed', s);
                } else if ((s === '3' || s === '4' || s === '5') && _chatStatus === 'locked') {
                    updateChatStatus('active', s);
                }

                // ── Auto-read status notification ────────────────────────────
                // The user is actively viewing this ride's tracking page — the
                // status change is already visible in the timeline, so there is
                // no need for the bell badge to increment.  Mark the server-side
                // notification as read immediately and sync to other open tabs.
                if (e.user_notif_id &&
                    typeof window.rrIsActiveChatFor === 'function' &&
                    window.rrIsActiveChatFor(e.request_id)) {
                    var _snCsrf = document.querySelector('meta[name="csrf-token"]');
                    fetch('/ride-chat/status-notif/' + e.user_notif_id + '/read', {
                        method:  'POST',
                        headers: {
                            'X-CSRF-TOKEN':     _snCsrf ? _snCsrf.content : '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    .then(function () {
                        if (window.rrNotifBell && typeof window.rrNotifBell.refresh === 'function') {
                            window.rrNotifBell.refresh();
                        }
                        var _snBc;
                        try { _snBc = new BroadcastChannel('rr_user_notif_sync'); } catch (ex) {}
                        if (_snBc) {
                            try { _snBc.postMessage({ type: 'notif_auto_read', request_id: e.request_id }); _snBc.close(); } catch (ex) {}
                        }
                    })
                    .catch(function () {});
                }

                console.log('[Reverb] request.status.updated → status', s, 'req', e.request_id);
            });

            // Expose the Pusher instance so other scripts (e.g. tracking page)
            // can subscribe to additional channels on the same connection.
            window._userPusher = _pusher;

            _pusher.connection.bind('connected', function () {
                console.log('[Reverb] User ride-chat connected');
            });
        } catch (err) {
            console.warn('[Reverb] Ride chat WS failed:', err.message || err);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var input    = document.getElementById('rcInput');
        var sendBtn  = document.getElementById('rcSendBtn');

        if (sendBtn) sendBtn.addEventListener('click', sendMessage);

        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
            });
            input.addEventListener('input', sendTyping);
        }

        loadThread();
        setupWS();
    });
})();
