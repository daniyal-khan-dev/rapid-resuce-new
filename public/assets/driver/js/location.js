/* Driver Location Tracker — Rapid Rescue */
(function () {
    'use strict';

    var _locUrl       = null;
    var _csrf         = null;
    var _watchId      = null;
    var _lastLat      = null;
    var _lastLng      = null;
    var _lastSentTs   = 0;
    var _minInterval  = 5000;   // ms — minimum time between sends
    var _minDistance  = 10;     // metres — minimum movement to trigger send
    var _enabled      = false;

    // Haversine distance in metres
    function _dist(lat1, lng1, lat2, lng2) {
        var R  = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function _send(lat, lng) {
        // Expose current position globally so map overlays can read it
        window._driCurrentPos = { lat: lat, lng: lng, ts: Date.now() };
        // Notify page components (e.g. nearby pending rides filter) of position update
        try {
            document.dispatchEvent(new CustomEvent('driLocationUpdated', { detail: { lat: lat, lng: lng } }));
        } catch (e) {}

        // Relay position to other open driver tabs so their self-view maps stay in sync
        try {
            if (typeof window.driBroadcastTabSync === 'function') {
                window.driBroadcastTabSync({ type: 'location_updated', lat: lat, lng: lng });
            }
        } catch (e) {}

        if (!_locUrl || !_csrf) return;
        var now = Date.now();

        // Throttle: skip if too soon AND movement below threshold
        if (_lastLat !== null && _lastLng !== null) {
            var moved = _dist(_lastLat, _lastLng, lat, lng);
            if (moved < _minDistance && (now - _lastSentTs) < _minInterval) return;
        }

        _lastLat    = lat;
        _lastLng    = lng;
        _lastSentTs = now;

        fetch(_locUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': _csrf,
            },
            body: JSON.stringify({ lat: lat, lng: lng }),
        }).catch(function () {}); // silent fail — don't disrupt the driver
    }

    function _onPosition(pos) {
        if (!_enabled) return;
        _send(pos.coords.latitude, pos.coords.longitude);
    }

    function _onError(err) {
        // Silently ignore permission denied / unavailable — driver can still work
        if (err.code === err.PERMISSION_DENIED) {
            console.info('[Location] Geolocation permission denied — tracking disabled.');
            _stop();
        }
    }

    function _start() {
        if (!navigator.geolocation) return;
        _enabled = true;

        // Get an immediate fix
        navigator.geolocation.getCurrentPosition(_onPosition, _onError, {
            enableHighAccuracy: true,
            timeout:            10000,
            maximumAge:         0,
        });

        // Then watch continuously
        _watchId = navigator.geolocation.watchPosition(_onPosition, _onError, {
            enableHighAccuracy: true,
            timeout:            15000,
            maximumAge:         5000,
        });
    }

    function _stop() {
        _enabled = false;
        if (_watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(_watchId);
            _watchId = null;
        }
    }

    function init() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) return;
        _csrf = meta.getAttribute('content');

        // Route is injected via window._driRoutes in the layout
        if (window._driRoutes && window._driRoutes.locationUpdate) {
            _locUrl = window._driRoutes.locationUpdate;
        } else {
            // fallback derivation
            var base = window.location.origin;
            _locUrl  = base + '/driver/location';
        }

        // Only track when driver is Online (status 1)
        var driverStatus = window._rrReverb ? String(window._rrReverb.driverStatus) : '1';
        if (driverStatus === '1' || driverStatus === '3') {
            _start();
        }

        // Re-start / stop when driver toggles availability
        document.addEventListener('driAvailabilityChanged', function (e) {
            if (e.detail && (e.detail.status === '1' || e.detail.status === '3')) {
                // Reset throttle so the very first getCurrentPosition fires immediately,
                // regardless of whether the driver moved or how recently they last sent.
                _lastLat    = null;
                _lastLng    = null;
                _lastSentTs = 0;
                _start();
            } else {
                _stop();
            }
        });
    }

    // Expose for manual control from dashboard.js
    window.driLocation = {
        start: _start,
        stop:  _stop,
    };

    document.addEventListener('DOMContentLoaded', init);
})();
