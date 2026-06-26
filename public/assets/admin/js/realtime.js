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

    function _setBadge(count) {
        window._emergencyBadgeCount = Math.max(0, count);
        _renderBadge(window._emergencyBadgeCount);
    }

    function _updateBadge(delta) {
        window._emergencyBadgeCount = Math.max(0, (window._emergencyBadgeCount || 0) + delta);
        _renderBadge(window._emergencyBadgeCount);
    }

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
                _updateBadge(e.data.delta);
            }
        };
    }

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

        if (String(d.status) === '5') {
            var opt2 = sel.querySelector('option[value="' + did + '"]');
            if (opt2) opt2.remove();
            return;
        }

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
        if (data.status === '6' || data.status === '7') {
            _applyBadge(data, -1);

            if (typeof window.admAddPastRideRow === 'function') {
                window.admAddPastRideRow(data);
            }
        }
    });

    /* ── Admin deleted an active emergency request ───────────────────────── */
    ch.bind('emergency.request.deleted', function (data) {
        _applyBadge(data, -1);

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
        if (typeof window.admAmbulanceContentUpdated === 'function') {
            window.admAmbulanceContentUpdated(data);
        }

        _rtSyncAmbulanceDriverDropdown(data);
        _rtSyncDispatchPools(data);
    });

    /* ── Ride Chat: new message ──────────────────────────────────────────── */
    ch.bind('ride.chat.message', function (data) {
        if (data.sender_type === 'admin') return;

        if (typeof window.admRideChatMessageReceived === 'function') {
            window.admRideChatMessageReceived(data);
        } else {
            if (typeof window.admIncrementChatBadge === 'function') {
                window.admIncrementChatBadge();
            }
        }
    });

    /* ── Ride Chat: typing indicator ─────────────────────────────────────── */
    ch.bind('ride.chat.typing', function (data) {
        if (typeof window.admRideChatTypingReceived === 'function') {
            window.admRideChatTypingReceived(data);
        }
    });

    pusher.connection.bind('connected', function () {
        console.log('[Reverb] Admin real-time hub connected.');
    });

    pusher.connection.bind('error', function (err) {
        console.warn('[Reverb] Connection error:', err);
    });
})();
