/* Driver session — heartbeat + tab-close detection */
(function () {
    'use strict';

    var HEARTBEAT_MS  = 15000;
    var TAB_STALE_MS  = 25000;
    var STORAGE_KEY   = 'rr_driver_tabs';
    var TAB_ID        = 'tab_' + Math.random().toString(36).substr(2, 9);
    var _timer        = null;
    var _routes       = null;

    // ── Navigation guard ─────────────────────────────────────────────────────
    // pagehide fires on BOTH navigation and actual tab/window close.
    // We only want to fire the offline beacon on a real close, not navigation.
    var _navigating = false;

    function _markNavigating() {
        _navigating = true;
        // Reset after 3 s in case the navigation was cancelled (e.g. same-page anchor)
        setTimeout(function () { _navigating = false; }, 3000);
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href') || '';
        // Ignore blank/javascript/hash-only links
        if (!href || href === '#' || href.startsWith('javascript')) return;
        _markNavigating();
    }, true);

    document.addEventListener('submit', function () {
        _markNavigating();
    }, true);

    // ── localStorage tab registry ─────────────────────────────────────────────

    function _getTabs() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
        catch (e) { return {}; }
    }

    function _setTabs(t) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(t)); }
        catch (e) {}
    }

    function _registerTab() {
        var t = _getTabs();
        t[TAB_ID] = Date.now();
        _setTabs(t);
    }

    function _unregisterTab() {
        var t = _getTabs();
        delete t[TAB_ID];
        _setTabs(t);
    }

    function _activeTabs() {
        var t   = _getTabs();
        var now = Date.now();
        var active = {};
        Object.keys(t).forEach(function (id) {
            if (id !== TAB_ID && now - t[id] < TAB_STALE_MS) {
                active[id] = t[id];
            }
        });
        return active;
    }

    // ── Heartbeat ─────────────────────────────────────────────────────────────

    function _beat() {
        if (!_routes || !_routes.heartbeat) return;

        var tabs = _getTabs();
        tabs[TAB_ID] = Date.now();
        _setTabs(tabs);

        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) return;

        fetch(_routes.heartbeat, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrf.content,
            },
            body: JSON.stringify({}),
            keepalive: true,
        }).catch(function () {});
    }

    function _startBeat() {
        clearInterval(_timer);
        _beat();
        _timer = setInterval(_beat, HEARTBEAT_MS);
    }

    function _stopBeat() {
        clearInterval(_timer);
        _timer = null;
    }

    // ── Tab-close handler ─────────────────────────────────────────────────────

    function _onClose(persisted) {
        // persisted = true means page went into bfcache (navigation), not a real close
        if (persisted || _navigating) return;

        _stopBeat();
        _unregisterTab();

        var remaining = _activeTabs();
        if (Object.keys(remaining).length > 0) return;

        if (!_routes || !_routes.tabClose) return;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) return;

        var fd = new FormData();
        fd.append('_token', csrf.content);
        navigator.sendBeacon(_routes.tabClose, fd);
    }

    // ── Visibility change: pause/resume heartbeat on tab switch ──────────────

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            _navigating = false;
            _registerTab();
            _startBeat();
        }
    });

    // ── Page unload: fire tab-close beacon only on real close ─────────────────

    window.addEventListener('pagehide', function (e) {
        _onClose(e.persisted);
    });

    // ── Init ──────────────────────────────────────────────────────────────────

    function _init() {
        _routes = window._driRoutes || null;
        if (!_routes || !_routes.heartbeat) return;

        _registerTab();
        _startBeat();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _init);
    } else {
        _init();
    }
})();
