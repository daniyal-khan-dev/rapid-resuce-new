/* ── Admin Real-Time Event Hub ─────────────────────────────────────────────
 * Single Pusher connection that subscribes to 'contact.admin' and routes
 * every broadcast event to the matching page-level handler (if it exists).
 * Each admin page defines its handlers as global window.admXxx functions;
 * this file wires them up without coupling pages to each other.
 * ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var cfg = window._rrReverb;
    if (!cfg || typeof Pusher === 'undefined') return;

    /* ── BroadcastChannel: sync driver location across multiple admin tabs ─── */
    var _driverLocBC = null;
    try { _driverLocBC = new BroadcastChannel('rr-driver-location'); } catch (e) {}

    if (_driverLocBC) {
        _driverLocBC.onmessage = function (ev) {
            if (!ev.data) return;
            if (typeof window.admDriverLocationUpdated === 'function') {
                window.admDriverLocationUpdated(ev.data);
            }
        };
    }

    var pusher = new Pusher(cfg.key, {
        wsHost:            cfg.wsHost,
        wsPort:            cfg.wsPort,
        wssPort:           cfg.wssPort,
        forceTLS:          cfg.forceTLS,
        enabledTransports: cfg.enabledTransports,
        cluster:           'mt1',
        disableStats:      true,
    });

    var ch = pusher.subscribe('contact.admin');

    /* ── Emergency Request Badge ─────────────────────────────────────────── */

    // BroadcastChannel syncs badge across all open admin tabs
    var _badgeBC = null;
    try { _badgeBC = new BroadcastChannel('rr-emergency-badge'); } catch (e) {}

    function _renderBadge(count) {
        var badge = document.getElementById('emergencyReqBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Set badge to an absolute authoritative count (idempotent — safe to call
    // from multiple tabs with the same value without accumulating drift).
    function _setBadge(count) {
        window._emergencyBadgeCount = Math.max(0, count);
        _renderBadge(window._emergencyBadgeCount);
    }

    // Kept for cases where no server count is available (legacy fallback only).
    function _updateBadge(delta) {
        window._emergencyBadgeCount = Math.max(0, (window._emergencyBadgeCount || 0) + delta);
        _renderBadge(window._emergencyBadgeCount);
    }

    // Broadcast the absolute count to all sibling admin tabs.
    // Using 'set' (not 'delta') so receiving tabs call _setBadge — idempotent
    // regardless of how many tabs are open or how many WS events each got.
    function _broadcastBadge(count) {
        if (_badgeBC) {
            try { _badgeBC.postMessage({ type: 'set', count: count }); } catch (e) {}
        }
    }

    if (_badgeBC) {
        _badgeBC.onmessage = function (e) {
            if (!e.data) return;
            if (e.data.type === 'set') {
                _setBadge(e.data.count);
            } else if (e.data.type === 'delta') {
                // Legacy fallback (should not be sent anymore, but handle gracefully)
                _updateBadge(e.data.delta);
            }
        };
    }

    /* ── Helper: apply authoritative badge_count from any broadcast payload ─ */
    // When the server includes badge_count, use it directly (no drift possible).
    // Falls back to a delta if badge_count is missing (backward compat).
    function _applyBadge(data, fallbackDelta) {
        if (typeof data.badge_count === 'number') {
            _setBadge(data.badge_count);
            _broadcastBadge(data.badge_count);
        } else {
            _updateBadge(fallbackDelta);
        }
    }

    /* ── Global helpers: driver dropdown in ambulance modal ─────────────── */

    function _rtSyncAmbulanceDriverDropdown(data) {
        var sel = document.getElementById('amb_driver_id');
        if (!sel) return;

        if (data.module !== 'driver') return;

        var d   = data.data;
        var did = String(d.id);

        if (data.action === 'deleted') {
            var opt = sel.querySelector('option[value="' + did + '"]');
            if (opt) opt.remove();
            return;
        }

        // Status 5 = inactive — remove from dropdown
        if (String(d.status) === '5') {
            var opt2 = sel.querySelector('option[value="' + did + '"]');
            if (opt2) opt2.remove();
            return;
        }

        // Add or update the option
        var label   = (d.name || '') + ' \u2014 ' + (d.phone || '');
        var existing = sel.querySelector('option[value="' + did + '"]');
        if (existing) {
            existing.textContent = label;
        } else {
            var newOpt = document.createElement('option');
            newOpt.value       = did;
            newOpt.textContent = label;
            sel.appendChild(newOpt);
        }
    }

    /* ── Global helpers: dispatch pool sync (emergency page) ─────────────── */

    function _rtSyncDispatchPools(data) {
        if (data.module === 'ambulance') {
            var a   = data.data;
            var aid = String(a.id || a.ambulance_id || '');
            if (!aid) return;

            if (data.action === 'deleted') {
                window.reqAmbulances = (window.reqAmbulances || []).filter(function (x) { return String(x.id) !== aid; });
            } else {
                // added or updated
                var typeLabels = { '1': 'BLS', '2': 'ALS', '3': 'CCT', '4': 'Neonatal', '5': 'AIR' };
                var typeLabel  = typeLabels[String(a.type)] || a.type;
                var label      = (a.vehicle_number || '') + ' \u2014 ' + typeLabel;

                window.reqAmbulances = (window.reqAmbulances || []).filter(function (x) { return String(x.id) !== aid; });

                if (String(a.status) === '1') {
                    window.reqAmbulances = (window.reqAmbulances || []).concat([{ id: a.id, label: label }]);
                }
            }

            if (typeof window._refreshDispatchAmbulanceDropdown === 'function') {
                window._refreshDispatchAmbulanceDropdown();
            }
        }

        if (data.module === 'driver') {
            var dr   = data.data;
            var drid = String(dr.id || '');
            if (!drid) return;

            if (data.action === 'deleted') {
                window.reqDrivers = (window.reqDrivers || []).filter(function (x) { return String(x.id) !== drid; });
            } else {
                var dLabel = (dr.name || '') + ' \u2014 ' + (dr.phone || '');
                window.reqDrivers = (window.reqDrivers || []).filter(function (x) { return String(x.id) !== drid; });

                if (String(dr.status) === '1') {
                    window.reqDrivers = (window.reqDrivers || []).concat([{ id: dr.id, label: dLabel }]);
                }
            }

            if (typeof window._refreshDispatchDriverDropdown === 'function') {
                window._refreshDispatchDriverDropdown();
            }
        }
    }

    /* ── New emergency request submitted by a user ──────────────────────── */
    ch.bind('emergency.submitted', function (data) {
        // Use server-authoritative count — prevents double-increment when
        // multiple tabs are open (each tab receives this WS event independently).
        _applyBadge(data, +1);

        if (typeof window.admAddEmergencyRow === 'function') {
            window.admAddEmergencyRow(data);
        }
    });

    /* ── Request status changed (dispatch, en-route, completed, etc.) ───── */
    ch.bind('emergency.status.updated', function (data) {
        if (typeof window.admRequestStatusUpdated === 'function') {
            window.admRequestStatusUpdated(data);
        }
    });

    ch.bind('request.status.updated', function (data) {
        if (typeof window.admRequestStatusUpdated === 'function') {
            window.admRequestStatusUpdated(data);
        }
        // Completed (6) or cancelled (7) → request leaves the active list.
        // Use server-authoritative count to avoid drift from multi-tab delivery.
        if (data.status === '6' || data.status === '7') {
            _applyBadge(data, -1);

            if (typeof window.admAddPastRideRow === 'function') {
                window.admAddPastRideRow(data);
            }
        }
        // Notify the bell — a RideStatusNotification record is created for admin
        // on every status change so the bell count stays accurate in real time.
        if (typeof window.admNotifBell !== 'undefined' && window.admNotifBell) {
            window.admNotifBell.onNewStatusNotif();
        }
    });

    /* ── Admin deleted an active emergency request ───────────────────────── */
    ch.bind('emergency.request.deleted', function (data) {
        // Use server-authoritative count — request is already deleted server-side
        // before this event fires, so badge_count reflects the post-delete total.
        _applyBadge(data, -1);

        // Remove the row from the emergency table on any admin tab
        var rid = data.request_id;
        if (rid) {
            var row = document.querySelector('tr[data-req-id="' + rid + '"]');
            if (row) row.remove();
        }
    });

    /* ── Driver GPS ping ─────────────────────────────────────────────────── */
    ch.bind('driver.location.updated', function (data) {
        if (typeof window.admDriverLocationUpdated === 'function') {
            window.admDriverLocationUpdated(data);
        }
        /* Relay to every other open admin tab (e.g. live-monitoring in parallel tabs) */
        if (_driverLocBC) {
            try { _driverLocBC.postMessage(data); } catch (e) {}
        }
    });

    /* ── Driver came online / went offline ──────────────────────────────── */
    ch.bind('driver.availability.updated', function (data) {
        if (typeof window.admDriverAvailabilityUpdated === 'function') {
            window.admDriverAvailabilityUpdated(data);
        }
    });

    /* ── Ambulance became available or went on-job ───────────────────────── */
    ch.bind('ambulance.availability.updated', function (data) {
        if (typeof window.admAmbulanceAvailabilityUpdated === 'function') {
            window.admAmbulanceAvailabilityUpdated(data);
        }
    });

    /* ── Content updated (ambulance / driver add · edit · delete) ────────── */
    ch.bind('content.updated', function (data) {
        // Route to ambulance page handler (table row updates)
        if (typeof window.admAmbulanceContentUpdated === 'function') {
            window.admAmbulanceContentUpdated(data);
        }

        // Route to driver page handler (table row updates)
        if (typeof window.admDriverContentUpdated === 'function') {
            window.admDriverContentUpdated(data);
        }

        // Global: keep ambulance modal driver dropdown in sync
        _rtSyncAmbulanceDriverDropdown(data);

        // Global: keep emergency dispatch pools in sync
        _rtSyncDispatchPools(data);
    });

    /* ── Ride Chat: new message ──────────────────────────────────────────── */
    ch.bind('ride.chat.message', function (data) {
        if (data.sender_type === 'admin') return;

        if (typeof window.admRideChatMessageReceived === 'function') {
            // Ride Chats page is open — it handles per-ride badge AND nav badge internally.
            // Do NOT also call admIncrementChatBadge here or the nav badge double-counts.
            window.admRideChatMessageReceived(data);
        } else {
            // On any other admin page — no per-ride sidebar to update, just bump nav badge.
            if (typeof window.admIncrementChatBadge === 'function') {
                window.admIncrementChatBadge();
            }
        }

        // ── Notification bell ─────────────────────────────────────────────
        // Always increment the badge so the admin gets immediate visual
        // feedback regardless of which page they are on.
        if (typeof window.admNotifBell !== 'undefined' && window.admNotifBell) {
            window.admNotifBell.onNewMessage();
        }

        // When the admin has this exact conversation open, also auto-mark
        // the notification as read server-side so the notification history
        // stays clean.  The server will then broadcast ride.chat.notif.read
        // which triggers onNotifsRead() → re-fetches the bell → badge returns
        // to the correct (lower) count automatically.
        var _chatIsOpen = window._rcCurrentRequestId &&
            String(window._rcCurrentRequestId) === String(data.request_id);
        if (_chatIsOpen) {
            var _nrUrl = ((window._admRoutes || {}).rideChatNotifsRead || '')
                .replace(':requestId', data.request_id);
            if (_nrUrl) {
                var _csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch(_nrUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': _csrf, 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function () {
                    // Sync to other open admin tabs so they know this
                    // notification has been read (BroadcastChannel does NOT
                    // deliver to the sender tab, so this is safe here).
                    try {
                        var _bc = new BroadcastChannel('rr_admin_notif_sync');
                        _bc.postMessage({ type: 'notif_read', request_id: data.request_id });
                        _bc.close();
                    } catch (_ex) {}
                })
                .catch(function () {});
            }
        }
    });

    /* ── Ride Chat: typing indicator ─────────────────────────────────────── */
    ch.bind('ride.chat.typing', function (data) {
        if (typeof window.admRideChatTypingReceived === 'function') {
            window.admRideChatTypingReceived(data);
        }
    });

    /* ── Ride Chat notifications cleared ────────────────────────────────── */
    // Fires when THIS admin (or another admin tab of the same user) marks
    // notifications as read. We use recipient_id to confirm it's our admin.
    ch.bind('ride.chat.notif.read', function (data) {
        var myId = cfg.adminId;

        // Only process if this event is for the current admin (or unscoped)
        if (myId && data.recipient_id && String(data.recipient_id) !== String(myId)) return;
        if (data.recipient_type !== 'admin') return;

        if (typeof window.admNotifBell !== 'undefined' && window.admNotifBell) {
            window.admNotifBell.onNotifsRead();
        }

        // Update the Ride Chats sidebar badge for this request (if that page is open).
        if (typeof window.admRideChatNotifsRead === 'function') {
            window.admRideChatNotifsRead(data.request_id);
        }

        // Sync to other admin tabs via BroadcastChannel
        if (typeof window.admBroadcastTabSync === 'function') {
            window.admBroadcastTabSync({ type: 'ride_chat_notif_read', request_id: data.request_id, admin_id: data.recipient_id });
        }
    });

    pusher.connection.bind('connected', function () {
        console.log('[Reverb] Admin real-time hub connected.');
    });

    pusher.connection.bind('error', function (err) {
        console.warn('[Reverb] Connection error:', err);
    });
})();
