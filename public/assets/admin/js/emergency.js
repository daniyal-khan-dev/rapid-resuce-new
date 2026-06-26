/* ── Emergency Requests JS ───────────────────────────────────────────────── */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

let reqDetailMap          = null;
let reqDetailModalInstance = null;

// ── Live-tracking state ───────────────────────────────────────────────────────
let _activeReqDriverId  = null;   // assigned driver ID (string)
let _driverMarker       = null;   // Leaflet marker for assigned driver
let _activeReqStatus    = null;   // status of the open request
let _nearbyMarkers      = {};     // { driverId: L.marker } for nearby drivers
let _nearbyDriverData   = {};     // { driverId: {name,phone,distKm} } cached data
let _excludedDriverId   = null;   // assigned driver – skip from nearby list

// ── Global driver location cache (persists while admin page is open) ──────────
// Populated by every driver.location.updated event, even when modal is closed.
// Keyed by String(driver_id) → { lat, lng, time, ts }
window._drvLocations = window._drvLocations || {};

// ── Smooth-movement animation tracking ───────────────────────────────────────
// Keyed by a marker key string → rAF id; used to cancel in-flight animations.
let _markerAnimFrames = {};

// ── Per nearby-driver speed & OSRM ETA ───────────────────────────────────────
// _nearbyDriverPrev: last recorded position + computed speed for each nearby driver
// _nearbyDriverEta:  OSRM result cache (text, dist, inFlight, fetchTs)
let _nearbyDriverPrev = {};
let _nearbyDriverEta  = {};
const NEARBY_ETA_INTERVAL_MS = 30000;  // re-query OSRM at most once per 30 s

// ETA state
let _activePickupLat    = null;
let _activePickupLng    = null;
let _activeHospitalLat  = null;
let _activeHospitalLng  = null;
let _etaLastFetch       = 0;
let _etaInFlight        = false;
const ETA_INTERVAL_MS   = 20000;

// ── Admin modal live-tracking state (mirrors user tracking.js) ────────────────
let _admTrackingData    = null;  // { status, pickupLat/Lng, hospitalLat/Lng, driverLat/Lng, acceptedLat/Lng }
let _admDrivenPath      = [];    // [[lat,lng], ...] actual GPS breadcrumb
let _admDrivenPolyline  = null;  // grey Leaflet Polyline for breadcrumb
let _admPlannedCoords   = [];    // OSRM planned coords for off-route detection
let _admTrackingStopped = false;
let _admLastRouteLat    = null;
let _admLastRouteLng    = null;
let _admLrmRoute        = null;  // live LRM control / polyline fallback
let _admPickupMarker    = null;  // stored red pickup marker reference
let _admHospitalMarker  = null;  // stored green hospital marker reference
const ADM_ROUTE_REDRAW_M = 50;
const ADM_DEVIATION_M    = 180;

function getReqDetailModal() {
    if (!reqDetailModalInstance) {
        reqDetailModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('reqDetailModal'));
    }
    return reqDetailModalInstance;
}

function filterReqs() {
    const search = document.getElementById('searchReq').value.toLowerCase();
    const status = document.getElementById('filterReqStatus').value;
    const type   = document.getElementById('filterReqType').value;

    document.querySelectorAll('#reqTable tbody tr').forEach(row => {
        const matchSearch = !search || row.dataset.search.includes(search);
        const matchStatus = !status || row.dataset.status === status;
        const matchType   = !type   || row.dataset.type === type;
        row.style.display = matchSearch && matchStatus && matchType ? '' : 'none';
    });
}

function closeReqModal(id) {
    if (id === 'reqDetailModal') getReqDetailModal().hide();
}

/* ── Map icon builders ─────────────────────────────────────────────────────── */
function buildPersonIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#D72C42;border:3px solid #fff;border-radius:50%;width:38px;height:38px;
                    box-shadow:0 2px 14px rgba(215,44,66,0.55);display:flex;align-items:center;justify-content:center;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                   <circle cx="12" cy="7" r="4"/>
                   <path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>
                 </svg>
               </div>`,
        iconSize: [38, 38], iconAnchor: [19, 19], popupAnchor: [0, -22],
    });
}

function buildAmbulanceIcon(isLive) {
    const glow = isLive ? 'rgba(34,197,94,0.6)' : 'rgba(29,78,216,0.55)';
    const bg   = isLive ? '#16a34a'              : '#1d4ed8';
    return L.divIcon({
        className: '',
        html: `<div style="background:${bg};border:3px solid #fff;border-radius:50%;width:38px;height:38px;
                    box-shadow:0 2px 14px ${glow};display:flex;align-items:center;justify-content:center;
                    ${isLive ? 'animation:lmPulse 1.8s infinite;' : ''}">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                   <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.01L3 12v8c0 .55.45 1 1 1h1
                     c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5
                     S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5
                     1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                 </svg>
               </div>`,
        iconSize: [38, 38], iconAnchor: [19, 19], popupAnchor: [0, -22],
    });
}

function buildNearbyDriverIcon(rank, distKm) {
    const label = distKm < 1
        ? (distKm * 1000).toFixed(0) + 'm'
        : distKm.toFixed(1) + 'km';
    return L.divIcon({
        className: '',
        html: `<div style="position:relative;display:flex;flex-direction:column;align-items:center;">
            <div style="background:#f59e0b;border:3px solid #fff;border-radius:50%;width:34px;height:34px;
                box-shadow:0 2px 12px rgba(245,158,11,0.55);display:flex;align-items:center;justify-content:center;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="17" height="17">
                  <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8
                    c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z
                    M6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0
                    c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                </svg>
            </div>
            <div style="background:rgba(245,158,11,0.92);color:#fff;font-size:10px;font-weight:700;
                padding:1px 5px;border-radius:4px;margin-top:2px;white-space:nowrap;
                box-shadow:0 1px 4px rgba(0,0,0,.3);">#${rank} · ${label}</div>
        </div>`,
        iconSize: [40, 52], iconAnchor: [20, 10], popupAnchor: [0, -12],
    });
}

function buildHospitalIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#16a34a;border:3px solid #fff;border-radius:50%;width:34px;height:34px;
            box-shadow:0 2px 12px rgba(22,163,74,0.5);display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="18" height="18">
              <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 0 1
                1 1v3h3a1 1 0 0 1 0 2h-3v3a1 1 0 0 1-2 0v-3H8a1 1 0 0 1 0-2h3V7a1 1 0 0 1 1-1z"/>
            </svg></div>`,
        iconSize: [34, 34], iconAnchor: [17, 17], popupAnchor: [0, -20],
    });
}

/* ── Haversine distance (km) ───────────────────────────────────────────────── */
function _haversineKm(lat1, lng1, lat2, lng2) {
    const R = 6371, toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/* ── Smooth marker animation ───────────────────────────────────────────────── */
/**
 * Animate a Leaflet marker from its current position to [toLat, toLng] over
 * `durationMs` milliseconds using an ease-out cubic curve.
 *
 * @param {string}   key        Unique key per marker (cancel previous animation)
 * @param {L.Marker} marker     The Leaflet marker to move
 * @param {number}   toLat
 * @param {number}   toLng
 * @param {number}   [durationMs=700]
 */
function _animateMarkerTo(key, marker, toLat, toLng, durationMs) {
    durationMs = durationMs || 700;

    // Cancel any in-flight animation for this marker
    if (_markerAnimFrames[key]) {
        cancelAnimationFrame(_markerAnimFrames[key]);
        delete _markerAnimFrames[key];
    }

    const from = marker.getLatLng();
    const fromLat = from.lat, fromLng = from.lng;

    // Snap immediately for negligible movement (avoids jitter on duplicate events)
    if (Math.abs(toLat - fromLat) < 1e-7 && Math.abs(toLng - fromLng) < 1e-7) {
        marker.setLatLng([toLat, toLng]);
        return;
    }

    const startTs = performance.now();

    function step(ts) {
        const elapsed = ts - startTs;
        const raw = Math.min(elapsed / durationMs, 1);
        const ease = 1 - Math.pow(1 - raw, 3); // ease-out cubic

        marker.setLatLng([
            fromLat + (toLat - fromLat) * ease,
            fromLng + (toLng - fromLng) * ease,
        ]);

        if (raw < 1) {
            _markerAnimFrames[key] = requestAnimationFrame(step);
        } else {
            delete _markerAnimFrames[key];
        }
    }

    _markerAnimFrames[key] = requestAnimationFrame(step);
}

/* ── Nearby driver popup content builder ──────────────────────────────────── */
/**
 * Builds the full popup HTML for a nearby driver marker, including
 * straight-line distance, live speed, and OSRM driving ETA to pickup.
 */
function _buildNearbyPopupContent(driverId) {
    const data = _nearbyDriverData[driverId];
    if (!data) return '';

    const rank    = data.rank || 1;
    const distStr = data.distKm < 1
        ? (data.distKm * 1000).toFixed(0) + ' m'
        : data.distKm.toFixed(1) + ' km';

    // ── Speed row ─────────────────────────────────────────────────────────────
    const prev = _nearbyDriverPrev[driverId];
    let speedLine = '';
    if (prev && prev.speedKmh != null) {
        const spd      = Math.round(prev.speedKmh);
        const spdColor = spd > 80 ? '#ef4444' : spd > 30 ? '#f59e0b' : '#22c55e';
        const spdIcon  = spd > 80 ? '🚨' : spd > 30 ? '🚗' : '🐢';
        speedLine = `<div style="margin-top:5px;font-size:.82em;display:flex;align-items:center;gap:4px;">
            <span style="color:#6b7280;">${spdIcon} Speed:</span>
            <strong style="color:${spdColor};">${spd} km/h</strong>
        </div>`;
    }

    // ── OSRM ETA row ──────────────────────────────────────────────────────────
    const etaCache = _nearbyDriverEta[driverId];
    let etaLine = '';
    if (etaCache && etaCache.text) {
        etaLine = `<div style="margin-top:4px;font-size:.82em;font-weight:600;color:#1d4ed8;">
            ⏱ ${etaCache.text}
            <span style="color:#6b7280;font-weight:400;"> · ${etaCache.dist} to pickup</span>
        </div>`;
    } else if (etaCache && etaCache.inFlight) {
        etaLine = `<div style="margin-top:4px;font-size:.82em;color:#9ca3af;">
            <i class="fa fa-spinner fa-spin" style="width:12px;"></i> Calculating ETA…
        </div>`;
    }

    return `<div style="min-width:165px;">
        <div style="font-weight:700;margin-bottom:4px;">🟡 Available Driver #${rank}</div>
        <div style="font-size:.9em;"><b>${data.name}</b></div>
        <div style="color:#6b7280;font-size:.82em;">${data.phone || ''}</div>
        <div style="margin-top:6px;font-size:.82em;color:#f59e0b;font-weight:600;">
            📍 ${distStr} from pickup
        </div>
        ${speedLine}
        ${etaLine}
    </div>`;
}

/* ── Per nearby-driver OSRM ETA fetch (debounced per driver) ───────────────── */
function _fetchNearbyETA(driverId, lat, lng) {
    if (!_activePickupLat || !_activePickupLng) return;

    if (!_nearbyDriverEta[driverId]) {
        _nearbyDriverEta[driverId] = { inFlight: false, fetchTs: 0, text: null, dist: null };
    }
    const eta = _nearbyDriverEta[driverId];
    const now = Date.now();

    // Debounce: skip if a request is already running or result is fresh enough
    if (eta.inFlight || (now - eta.fetchTs) < NEARBY_ETA_INTERVAL_MS) return;

    eta.inFlight = true;
    eta.fetchTs  = now;

    // Show "calculating" in the popup immediately
    const m = _nearbyMarkers[driverId];
    if (m) m.setPopupContent(_buildNearbyPopupContent(driverId));

    const url = 'https://router.project-osrm.org/route/v1/driving/'
        + lng + ',' + lat + ';'
        + _activePickupLng + ',' + _activePickupLat
        + '?overview=false';

    fetch(url)
        .then(r => r.json())
        .then(osrm => {
            eta.inFlight = false;
            if (!osrm.routes || !osrm.routes[0]) return;
            eta.text = _fmtDuration(osrm.routes[0].duration);
            eta.dist = _fmtDistance(osrm.routes[0].distance);
            const m2 = _nearbyMarkers[driverId];
            if (m2) m2.setPopupContent(_buildNearbyPopupContent(driverId));
            console.log('[NearbyETA] Driver', driverId, '→', eta.text, '/', eta.dist, 'to pickup');
        })
        .catch(() => { eta.inFlight = false; });
}

/* ── Nearby count badge ────────────────────────────────────────────────────── */
function _updateNearbyCountBadge() {
    const el = document.getElementById('nearbyCountBadge');
    if (!el) return;
    const n = Object.keys(_nearbyMarkers).length;
    el.innerHTML = n > 0
        ? `<i class="fa fa-circle" style="color:#f59e0b;font-size:.55rem;vertical-align:middle;margin-right:4px;"></i>`
          + `<strong style="color:#f59e0b;">${n}</strong>`
          + ` available driver${n !== 1 ? 's' : ''} nearby`
        : `<i class="fa fa-circle" style="color:rgba(255,255,255,.2);font-size:.55rem;vertical-align:middle;margin-right:4px;"></i>`
          + `<span style="color:rgba(255,255,255,.3);">No available drivers within 30 km</span>`;
}

/* ── Add / remove a single nearby marker ──────────────────────────────────── */
function _addSingleNearbyMarker(dId, lat, lng, name, phone, distKm) {
    if (!reqDetailMap) return;
    if (_nearbyMarkers[dId]) {          // already on map — smooth-move it
        _animateMarkerTo('nearby_' + dId, _nearbyMarkers[dId], lat, lng);
        return;
    }
    const rank = Object.keys(_nearbyMarkers).length + 1;

    // Store data (including rank) so _buildNearbyPopupContent can use it
    _nearbyDriverData[dId] = { name, phone, distKm, rank };
    // Seed the speed tracker — speed is null until we have two data points
    _nearbyDriverPrev[dId] = { lat, lng, ts: Date.now(), speedKmh: null };

    const marker = L.marker([lat, lng], {
        icon: buildNearbyDriverIcon(rank, distKm),
        zIndexOffset: -10,
    })
    .bindPopup(_buildNearbyPopupContent(dId))
    .addTo(reqDetailMap);

    _nearbyMarkers[dId] = marker;
    _updateNearbyCountBadge();

    // Kick off the first OSRM ETA fetch for this driver
    _fetchNearbyETA(dId, lat, lng);

    const distStr = distKm < 1 ? (distKm * 1000).toFixed(0) + ' m' : distKm.toFixed(1) + ' km';
    console.log('[NearbyDrivers] Added driver', name, 'at', distStr);
}

function _removeNearbyMarker(driverId) {
    if (_nearbyMarkers[driverId]) {
        // Cancel any in-flight smooth-move animation for this marker
        const animKey = 'nearby_' + driverId;
        if (_markerAnimFrames[animKey]) {
            cancelAnimationFrame(_markerAnimFrames[animKey]);
            delete _markerAnimFrames[animKey];
        }
        _nearbyMarkers[driverId].remove();
        delete _nearbyMarkers[driverId];
        delete _nearbyDriverData[driverId];
        // Clean up speed + ETA state for this driver
        delete _nearbyDriverPrev[driverId];
        delete _nearbyDriverEta[driverId];
        // Evict from global cache so a stale position isn't used on re-add
        delete window._drvLocations[driverId];
        _updateNearbyCountBadge();
        console.log('[NearbyDrivers] Removed driver', driverId, '(offline/busy)');
    }
}

/* ── Nearby drivers loader ─────────────────────────────────────────────────── */
function _loadNearbyDrivers(pickupLat, pickupLng, excludeDriverId) {
    if (!reqDetailMap || !window.adminRoutes.requestsNearbyDrivers) return;

    _excludedDriverId = excludeDriverId ? String(excludeDriverId) : null;

    const url = window.adminRoutes.requestsNearbyDrivers
        + '?lat=' + pickupLat + '&lng=' + pickupLng + '&radius=30';

    fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() } })
    .then(r => r.json())
    .then(data => {
        // Clear old nearby markers
        Object.values(_nearbyMarkers).forEach(m => m.remove());
        _nearbyMarkers    = {};
        _nearbyDriverData = {};

        if (!data.success || !data.drivers.length) {
            _updateNearbyCountBadge();
            return;
        }

        let rank = 0;
        data.drivers.forEach(d => {
            const dId = String(d.id);
            if (_excludedDriverId && dId === _excludedDriverId) return;

            rank++;
            const distKm = d.distance_km;

            // Store driver data with rank so popup builder can reference it
            _nearbyDriverData[dId] = { name: d.name, phone: d.phone, distKm, rank };
            // Seed speed tracker — speed unknown until a second GPS update arrives
            _nearbyDriverPrev[dId] = { lat: d.lat, lng: d.lng, ts: Date.now(), speedKmh: null };

            const marker = L.marker([d.lat, d.lng], {
                icon: buildNearbyDriverIcon(rank, distKm),
                zIndexOffset: -10,
            })
            .bindPopup(_buildNearbyPopupContent(dId))
            .addTo(reqDetailMap);

            _nearbyMarkers[dId] = marker;

            // Kick off initial OSRM ETA fetch for this driver
            _fetchNearbyETA(dId, d.lat, d.lng);
        });

        _updateNearbyCountBadge();
        console.log('[NearbyDrivers] Plotted', Object.keys(_nearbyMarkers).length, 'drivers within 30 km');
    })
    .catch(err => console.warn('[NearbyDrivers] fetch failed', err));
}

/* ── Select option builders ────────────────────────────────────────────────── */
function buildAmbulanceOptions() {
    return (window.reqAmbulances || []).map(a =>
        `<option value="${a.id}">${a.label}</option>`
    ).join('');
}
function buildDriverOptions() {
    return (window.reqDrivers || []).map(d =>
        `<option value="${d.id}">${d.label}</option>`
    ).join('');
}

/* ── Real-time dispatch dropdown refresh helpers ────────────────────────────── */

function _refreshDispatchDriverDropdown() {
    const sel = document.getElementById('inline_driver_id');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Choose driver\u2026</option>' + buildDriverOptions();
    if (current && sel.querySelector('option[value="' + current + '"]')) {
        sel.value = current;
    }
}

function _refreshDispatchAmbulanceDropdown() {
    const sel = document.getElementById('inline_ambulance_id');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Choose ambulance\u2026</option>' + buildAmbulanceOptions();
    if (current && sel.querySelector('option[value="' + current + '"]')) {
        sel.value = current;
    }
}

/* ── Real-time: ambulance became available or went on-job ───────────────────── */
window.admAmbulanceAvailabilityUpdated = function(e) {
    const ambulanceId = String(e.ambulance_id);
    const isAvailable = String(e.status) === '1';

    console.log('[Reverb] Ambulance', e.vehicle_number, 'is now', e.status_label, '(status=' + e.status + ')');

    if (isAvailable) {
        const existing = (window.reqAmbulances || []).findIndex(function(a) {
            return String(a.id) === ambulanceId;
        });
        if (existing === -1) {
            window.reqAmbulances = (window.reqAmbulances || []).concat([{
                id:    e.ambulance_id,
                label: e.label || (e.vehicle_number + ' \u2014 ' + e.type),
            }]);
            console.log('[Dispatch] Ambulance', e.vehicle_number, 'added to dispatch pool');
        }
    } else {
        const before = (window.reqAmbulances || []).length;
        window.reqAmbulances = (window.reqAmbulances || []).filter(function(a) {
            return String(a.id) !== ambulanceId;
        });
        if ((window.reqAmbulances || []).length < before) {
            console.log('[Dispatch] Ambulance', e.vehicle_number, 'removed from dispatch pool (', e.status_label, ')');
        }
    }

    _refreshDispatchAmbulanceDropdown();
};

/* ── Status helpers ────────────────────────────────────────────────────────── */
const statusLabelMap = {
    '1': 'Pending', '2': 'Dispatched', '3': 'En Route',
    '4': 'Arrived', '5': 'Transporting', '6': 'Completed', '7': 'Cancelled',
    '8': 'Awaiting Acceptance',
};
function humanStatus(s) {
    return statusLabelMap[s] || (s ? s.replace(/_/g, ' ') : '—');
}

/* ── ETA helpers ───────────────────────────────────────────────────────────── */
function _fmtDuration(secs) {
    if (secs < 60)   return '< 1 min';
    const m = Math.round(secs / 60);
    if (m < 60)      return m + ' min';
    const h = Math.floor(m / 60), rm = m % 60;
    return h + ' hr' + (rm ? ' ' + rm + ' min' : '');
}

function _fmtDistance(metres) {
    if (metres < 1000) return Math.round(metres) + ' m';
    return (metres / 1000).toFixed(1) + ' km';
}

/**
 * Determine what the ETA destination is based on the request status.
 * Returns { lat, lng, label } or null when not applicable.
 */
function _etaDest(status) {
    // En Route / Dispatched → heading to pickup
    if (status === '2' || status === '3') {
        if (_activePickupLat && _activePickupLng)
            return { lat: _activePickupLat, lng: _activePickupLng, label: 'pickup' };
    }
    // Arrived → driver is at pickup
    if (status === '4') return null; // already there — show a special message
    // Transporting → heading to hospital
    if (status === '5') {
        if (_activeHospitalLat && _activeHospitalLng)
            return { lat: _activeHospitalLat, lng: _activeHospitalLng, label: 'hospital' };
    }
    return null;
}

/**
 * Query OSRM for driving duration & distance from driver to dest.
 * Updates the ETA slot in the badge. Debounced to ETA_INTERVAL_MS.
 */
function _fetchETA(driverLat, driverLng) {
    if (_etaInFlight) return;
    const now = Date.now();
    if (now - _etaLastFetch < ETA_INTERVAL_MS) return;

    const dest = _etaDest(_activeReqStatus);

    // Status 4 (Arrived) — show a fixed label instead of querying OSRM
    if (_activeReqStatus === '4') {
        const etaEl = document.getElementById('liveTrackETA');
        if (etaEl) etaEl.innerHTML =
            '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>'
            + ' <strong style="color:#22c55e;">At pickup</strong>';
        return;
    }

    if (!dest) return;

    _etaInFlight  = true;
    _etaLastFetch = now;

    const url = `https://router.project-osrm.org/route/v1/driving/`
        + `${driverLng},${driverLat};${dest.lng},${dest.lat}`
        + `?overview=false`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            _etaInFlight = false;
            if (!data.routes || !data.routes[0]) return;
            const route = data.routes[0];
            const eta   = _fmtDuration(route.duration);
            const dist  = _fmtDistance(route.distance);
            const etaEl = document.getElementById('liveTrackETA');
            if (etaEl) {
                etaEl.innerHTML =
                    `<i class="fa fa-route" style="color:#f59e0b;width:13px;"></i>`
                    + ` <strong style="color:#f59e0b;">${eta}</strong>`
                    + ` <span style="color:rgba(255,255,255,.3);">·</span>`
                    + ` <span style="color:rgba(255,255,255,.4);">${dist} to ${dest.label}</span>`;
            }
            console.log('[ETA]', eta, '/', dist, 'to', dest.label);
        })
        .catch(() => { _etaInFlight = false; });
}

/* ── Live tracking badge ───────────────────────────────────────────────────── */
function buildLiveTrackingBadge(driver, lat, lng, status) {
    const hasCoords = lat && lng;
    const coordStr  = hasCoords
        ? parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)
        : 'Awaiting signal…';

    // Initial ETA text depends on status
    let etaInit = '<i class="fa fa-spinner fa-spin" style="width:13px;color:rgba(255,255,255,.3);"></i>'
        + ' <span style="color:rgba(255,255,255,.3);">Calculating ETA…</span>';
    if (status === '4') {
        etaInit = '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>'
            + ' <strong style="color:#22c55e;">At pickup</strong>';
    } else if (!hasCoords) {
        etaInit = '<span style="color:rgba(255,255,255,.3);">ETA available once driver sends location</span>';
    }

    return `<div id="liveTrackBadge" style="
            background:rgba(34,197,94,0.07);
            border:1px solid rgba(34,197,94,0.22);
            border-radius:12px;padding:12px 14px;margin-bottom:14px;">

        <!-- Row 1: pulse + label + updated-at -->
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <span id="liveTrackDot" style="width:9px;height:9px;border-radius:50%;background:#22c55e;flex-shrink:0;
                box-shadow:0 0 0 0 rgba(34,197,94,.4);animation:lmPulse 1.8s infinite;display:inline-block;"></span>
            <span id="liveTrackLabel" style="font-size:.73rem;font-weight:700;color:#22c55e;letter-spacing:.05em;flex-shrink:0;">LIVE</span>
            <span style="font-size:.73rem;color:rgba(255,255,255,.55);font-weight:600;flex:1;white-space:nowrap;
                overflow:hidden;text-overflow:ellipsis;">
                <i class="fa fa-ambulance" style="color:#22c55e;margin-right:3px;"></i>${driver}
            </span>
            <span style="font-size:.67rem;color:rgba(255,255,255,.28);flex-shrink:0;white-space:nowrap;" id="liveTrackTime">
                <i class="fa fa-clock" style="width:12px;"></i> Waiting…
            </span>
        </div>

        <!-- Row 2: coords -->
        <div style="font-size:.71rem;color:rgba(255,255,255,.38);margin-bottom:6px;" id="liveTrackCoords">
            <i class="fa fa-location-dot" style="color:#22c55e;width:13px;"></i>
            ${coordStr}
        </div>

        <!-- Row 3: ETA -->
        <div style="font-size:.73rem;" id="liveTrackETA">
            ${etaInit}
        </div>
    </div>`;
}

function _updateLiveBadge(lat, lng, time) {
    const coordEl = document.getElementById('liveTrackCoords');
    const timeEl  = document.getElementById('liveTrackTime');
    if (coordEl) coordEl.innerHTML =
        '<i class="fa fa-location-dot" style="color:#22c55e;width:13px;"></i> '
        + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5);
    if (timeEl)  timeEl.innerHTML =
        '<i class="fa fa-clock" style="width:12px;"></i> '
        + (time || new Date().toLocaleTimeString());
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ADMIN MODAL — LIVE TRACKING MODULE  (mirrors user tracking.js exactly)
 * ═════════════════════════════════════════════════════════════════════════ */

/* ── Haversine (metres) ──────────────────────────────────────────────────── */
function _admHavM(lat1, lng1, lat2, lng2) {
    const R = 6371000, r = x => x * Math.PI / 180;
    const dLat = r(lat2 - lat1), dLng = r(lng2 - lng1);
    const a = Math.sin(dLat/2)**2 + Math.cos(r(lat1)) * Math.cos(r(lat2)) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function _admPt2Seg(pLat, pLng, aLat, aLng, bLat, bLng) {
    const dLat = bLat - aLat, dLng = bLng - aLng;
    const lenSq = dLat*dLat + dLng*dLng;
    if (lenSq === 0) return _admHavM(pLat, pLng, aLat, aLng);
    const t = Math.max(0, Math.min(1, ((pLat-aLat)*dLat + (pLng-aLng)*dLng) / lenSq));
    return _admHavM(pLat, pLng, aLat + t*dLat, aLng + t*dLng);
}

function _admIsOffRoute(lat, lng) {
    if (_admPlannedCoords.length < 2) return false;
    for (let i = 0; i < _admPlannedCoords.length - 1; i++) {
        if (_admPt2Seg(lat, lng,
            _admPlannedCoords[i][0],   _admPlannedCoords[i][1],
            _admPlannedCoords[i+1][0], _admPlannedCoords[i+1][1]
        ) < ADM_DEVIATION_M) return false;
    }
    return true;
}

/* ── Grey breadcrumb trail ───────────────────────────────────────────────── */
function _admAppendDrivenPath(lat, lng) {
    if (!reqDetailMap) return;
    _admDrivenPath.push([lat, lng]);
    if (_admDrivenPolyline) {
        _admDrivenPolyline.setLatLngs(_admDrivenPath);
    } else if (_admDrivenPath.length >= 2) {
        _admDrivenPolyline = L.polyline(_admDrivenPath, {
            color: '#9ca3af', weight: 4, opacity: 0.65,
        }).addTo(reqDetailMap);
    }
}

/* ── Smart route point selection (same logic as user tracking.js) ────────── */
function _admGetSmartRoutePoints(td) {
    const s           = String(td.status);
    const hasDriver   = !!(td.driverLat && td.driverLng);
    const hasHospital = !!(td.hospitalLat && td.hospitalLng);

    if (s === '1') {
        if (hasHospital) return { from: [td.pickupLat, td.pickupLng], to: [td.hospitalLat, td.hospitalLng] };
        return null;
    }
    if (s === '2' || s === '8') {
        if (hasDriver)   return { from: [td.driverLat, td.driverLng],  to: [td.pickupLat, td.pickupLng] };
        if (hasHospital) return { from: [td.pickupLat, td.pickupLng],  to: [td.hospitalLat, td.hospitalLng] };
        return null;
    }
    if (s === '3' || s === '4') {
        if (hasDriver) return { from: [td.driverLat, td.driverLng], to: [td.pickupLat, td.pickupLng] };
        return null;
    }
    if (s === '5') {
        if (hasDriver && hasHospital) return { from: [td.driverLat, td.driverLng], to: [td.hospitalLat, td.hospitalLng] };
        if (hasHospital)              return { from: [td.pickupLat, td.pickupLng], to: [td.hospitalLat, td.hospitalLng] };
        return null;
    }
    return null;
}

/* ── Draw / update live LRM route on the modal map ──────────────────────── */
function _admDrawLiveRoute(fromLat, fromLng, toLat, toLng) {
    if (!reqDetailMap) return;
    if (_admLrmRoute) {
        try {
            if (typeof _admLrmRoute.remove === 'function') reqDetailMap.removeControl(_admLrmRoute);
            else reqDetailMap.removeLayer(_admLrmRoute);
        } catch(e) {}
        _admLrmRoute = null;
    }
    if (typeof L.Routing !== 'undefined') {
        _admLrmRoute = L.Routing.control({
            waypoints: [L.latLng(fromLat, fromLng), L.latLng(toLat, toLng)],
            router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
            lineOptions: { styles: [{ color: '#1d4ed8', weight: 5, opacity: 0.80 }] },
            addWaypoints: false, draggableWaypoints: false,
            show: false, createMarker: () => null,
        }).addTo(reqDetailMap);
        _admLrmRoute.on('routesfound', function(e) {
            if (e.routes && e.routes[0] && e.routes[0].coordinates) {
                _admPlannedCoords = e.routes[0].coordinates.map(c => [c.lat, c.lng]);
            }
        });
    } else {
        _admLrmRoute = L.polyline([[fromLat, fromLng], [toLat, toLng]], {
            color: '#1d4ed8', weight: 4, opacity: 0.75, dashArray: '10,8',
        }).addTo(reqDetailMap);
        _admPlannedCoords = [[fromLat, fromLng], [toLat, toLng]];
    }
}

/* ── Apply smart route (same throttle + deviation logic as user side) ────── */
function _admApplySmartRoute(td, opts) {
    if (_admTrackingStopped || !reqDetailMap) return;
    opts = opts || {};
    const pts = _admGetSmartRoutePoints(td);
    if (!pts) {
        if (_admLrmRoute) {
            try {
                if (typeof _admLrmRoute.remove === 'function') reqDetailMap.removeControl(_admLrmRoute);
                else reqDetailMap.removeLayer(_admLrmRoute);
            } catch(e) {}
            _admLrmRoute = null;
        }
        _admLastRouteLat = null; _admLastRouteLng = null;
        return;
    }
    let shouldRedraw = opts.forceRedraw;
    if (!shouldRedraw) {
        if (_admLastRouteLat === null) {
            shouldRedraw = true;
        } else {
            const moved = _admHavM(_admLastRouteLat, _admLastRouteLng, pts.from[0], pts.from[1]);
            if (moved > ADM_ROUTE_REDRAW_M) {
                shouldRedraw = true;
            } else if (opts.checkDeviation && _admIsOffRoute(pts.from[0], pts.from[1])) {
                shouldRedraw = true;
                console.log('[AdminTracking] Off-route — recalculating.');
            }
        }
    }
    if (shouldRedraw) {
        _admLastRouteLat = pts.from[0];
        _admLastRouteLng = pts.from[1];
        _admDrawLiveRoute(pts.from[0], pts.from[1], pts.to[0], pts.to[1]);
    }
    if (!opts.noFit) {
        const b = L.latLngBounds([pts.from, pts.to]);
        if (td.driverLat && td.driverLng) b.extend([td.driverLat, td.driverLng]);
        if (td.pickupLat  && td.pickupLng) b.extend([td.pickupLat,  td.pickupLng]);
        reqDetailMap.fitBounds(b, { padding: [50, 50] });
    }
}

/* ── Fetch static grey OSRM route for completion summary ─────────────────── */
function _admFetchGreyRoute(fromLat, fromLng, toLat, toLng) {
    if (!reqDetailMap) return;
    const url = 'https://router.project-osrm.org/route/v1/driving/'
        + fromLng + ',' + fromLat + ';' + toLng + ',' + toLat
        + '?overview=full&geometries=geojson';
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!reqDetailMap) return;
            if (data.routes && data.routes[0] && data.routes[0].geometry) {
                const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                L.polyline(coords, { color: '#6b7280', weight: 5, opacity: 0.70 }).addTo(reqDetailMap);
            } else { throw new Error('no route'); }
        })
        .catch(() => {
            if (!reqDetailMap) return;
            L.polyline([[fromLat, fromLng], [toLat, toLng]], {
                color: '#6b7280', weight: 4, opacity: 0.60, dashArray: '8,6',
            }).addTo(reqDetailMap);
        });
}

/* ── Draw completion summary (mirrors _drawCompletionSummary in tracking.js) */
function _admDrawCompletionSummary() {
    if (!reqDetailMap || !_admTrackingData) return;
    const td = _admTrackingData;
    const bounds = L.latLngBounds();

    // 1. Driver start marker (purple flag)
    if (td.acceptedLat && td.acceptedLng) {
        L.marker([td.acceptedLat, td.acceptedLng], {
            icon: L.divIcon({
                className: '',
                html: `<div style="background:#7c3aed;border:3px solid #fff;border-radius:50%;width:38px;height:38px;
                        box-shadow:0 2px 14px rgba(124,58,237,0.55);display:flex;align-items:center;justify-content:center;">
                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                           <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/>
                         </svg>
                       </div>`,
                iconSize: [38, 38], iconAnchor: [19, 19], popupAnchor: [0, -22],
            })
        }).bindPopup(
            '<div style="text-align:center;min-width:130px;">'
            + '<b style="color:#7c3aed;">Driver Started Here</b><br>'
            + '<small style="color:#6b7280;">' + Number(td.acceptedLat).toFixed(5) + ', ' + Number(td.acceptedLng).toFixed(5) + '</small>'
            + '</div>'
        ).addTo(reqDetailMap);
        bounds.extend([td.acceptedLat, td.acceptedLng]);
    }

    // 2. Pickup marker (re-add if removed during Transporting)
    if (td.pickupLat && td.pickupLng) {
        if (!_admPickupMarker) {
            _admPickupMarker = L.marker([td.pickupLat, td.pickupLng], { icon: buildPersonIcon() })
                .bindPopup('<div style="text-align:center;min-width:130px;"><b style="color:#D72C42;">Pickup Location</b></div>')
                .addTo(reqDetailMap);
        }
        bounds.extend([td.pickupLat, td.pickupLng]);
    }

    // 3. Hospital marker
    if (td.hospitalLat && td.hospitalLng) {
        if (!_admHospitalMarker) {
            _admHospitalMarker = L.marker([td.hospitalLat, td.hospitalLng], { icon: buildHospitalIcon() })
                .bindPopup('<div style="text-align:center;min-width:130px;"><b style="color:#16a34a;">Hospital Location</b></div>')
                .addTo(reqDetailMap);
        }
        bounds.extend([td.hospitalLat, td.hospitalLng]);
    }

    // 4. Grey routes
    if (td.acceptedLat && td.acceptedLng && td.pickupLat && td.pickupLng)
        _admFetchGreyRoute(td.acceptedLat, td.acceptedLng, td.pickupLat, td.pickupLng);
    if (td.pickupLat && td.pickupLng && td.hospitalLat && td.hospitalLng)
        _admFetchGreyRoute(td.pickupLat, td.pickupLng, td.hospitalLat, td.hospitalLng);

    // 5. Fit map
    if (bounds.isValid()) reqDetailMap.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
}

/* ── Stop live tracking + draw static completion summary ─────────────────── */
function _admStopTracking() {
    _admTrackingStopped = true;
    if (_driverMarker && reqDetailMap)   { reqDetailMap.removeLayer(_driverMarker);   _driverMarker = null; }
    if (_admDrivenPolyline && reqDetailMap) { reqDetailMap.removeLayer(_admDrivenPolyline); _admDrivenPolyline = null; }
    if (_admLrmRoute) {
        try {
            if (typeof _admLrmRoute.remove === 'function') reqDetailMap.removeControl(_admLrmRoute);
            else if (reqDetailMap) reqDetailMap.removeLayer(_admLrmRoute);
        } catch(e) {}
        _admLrmRoute = null;
    }
    _admPlannedCoords = []; _admLastRouteLat = null; _admLastRouteLng = null;
    _admDrawCompletionSummary();
}

/* ── Full tracking state reset (called on viewRequest + modal close) ─────── */
function _admResetTrackingState() {
    // Remove live route layer before nulling the reference
    if (_admLrmRoute && reqDetailMap) {
        try {
            if (typeof _admLrmRoute.remove === 'function') reqDetailMap.removeControl(_admLrmRoute);
            else reqDetailMap.removeLayer(_admLrmRoute);
        } catch(e) {}
    }
    _admTrackingData    = null;
    _admDrivenPath      = [];
    _admDrivenPolyline  = null;
    _admPlannedCoords   = [];
    _admTrackingStopped = false;
    _admLastRouteLat    = null;
    _admLastRouteLng    = null;
    _admLrmRoute        = null;
    _admPickupMarker    = null;
    _admHospitalMarker  = null;
}

/* ── viewRequest ───────────────────────────────────────────────────────────── */
function viewRequest(id) {
    document.getElementById('reqDetailBody').innerHTML =
        '<div class="adm-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';
    getReqDetailModal().show();

    // Reset all live-tracking state
    _activeReqDriverId = null;
    _driverMarker      = null;
    _activeReqStatus   = null;
    _activePickupLat   = null;
    _activePickupLng   = null;
    _activeHospitalLat = null;
    _activeHospitalLng = null;
    _etaLastFetch      = 0;
    _etaInFlight       = false;
    _nearbyMarkers     = {};
    _nearbyDriverData  = {};
    _nearbyDriverPrev  = {};
    _nearbyDriverEta   = {};
    _excludedDriverId  = null;

    fetch(`${window.adminRoutes.requestsShow}/${id}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            document.getElementById('reqDetailBody').innerHTML =
                '<p style="padding:20px;color:#f87171">Failed to load.</p>';
            return;
        }

        // Refresh available-resource arrays so dispatch dropdowns always show
        // the current pool — critical after a driver reject (resources freed).
        if (Array.isArray(data.available_ambulances)) {
            window.reqAmbulances = data.available_ambulances;
        }
        if (Array.isArray(data.available_drivers)) {
            window.reqDrivers = data.available_drivers;
        }

        const r = data.request;
        const hasCoords            = r.pickup_lat && r.pickup_lng;
        const isPending            = r.status === '1';
        const isAwaitingAcceptance = r.status === '8';
        const isActive             = ['2','3','4','5'].includes(r.status);
        const hasDriver            = r.driver && r.driver.id;
        const hasDriverCoords      = hasDriver && r.driver.lat && r.driver.lng;

        _activeReqStatus = r.status;

        // Store destination coords for ETA routing
        if (hasCoords) {
            _activePickupLat = parseFloat(r.pickup_lat);
            _activePickupLng = parseFloat(r.pickup_lng);
        }
        if (r.hospital_lat && r.hospital_lng) {
            _activeHospitalLat = parseFloat(r.hospital_lat);
            _activeHospitalLng = parseFloat(r.hospital_lng);
        }

        const liveTrackingBadge = (isActive && hasDriver)
            ? buildLiveTrackingBadge(
                r.driver.name,
                hasDriverCoords ? r.driver.lat : null,
                hasDriverCoords ? r.driver.lng : null,
                r.status
              )
            : '';

        const dispatchSection = isPending ? `
            <div class="adm-dispatch-panel">
                <div class="adm-dispatch-panel__title">
                    <i class="fa fa-paper-plane"></i> Dispatch Ambulance
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="adm-dispatch-label">Select Ambulance</label>
                        <select id="inline_ambulance_id" class="adm-input">
                            <option value="">Choose ambulance…</option>
                            ${buildAmbulanceOptions()}
                        </select>
                        <small class="adm-field-error" id="inline_err_ambulance_id"></small>
                    </div>
                    <div>
                        <label class="adm-dispatch-label">Assign Driver</label>
                        <select id="inline_driver_id" class="adm-input">
                            <option value="">Choose driver…</option>
                            ${buildDriverOptions()}
                        </select>
                        <small class="adm-field-error" id="inline_err_driver_id"></small>
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="adm-dispatch-label">Notes (optional)</label>
                        <textarea id="inline_notes" class="adm-input" rows="2"
                            placeholder="Any instructions for the driver…"></textarea>
                    </div>
                </div>
                <div id="inline_dispatch_msg" class="adm-form-msg"></div>
                <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                    <button class="adm-btn adm-btn--primary" onclick="submitInlineDispatch(${r.id})">
                        <i class="fa fa-paper-plane"></i> Send Request
                    </button>
                </div>
            </div>`
            : isAwaitingAcceptance ? `
            <div class="adm-dispatch-panel" style="border-color:rgba(251,191,36,.2);background:rgba(251,191,36,.04);">
                <div class="adm-dispatch-panel__title" style="color:#fbbf24;">
                    <i class="fa fa-clock"></i> Awaiting Driver Acceptance
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0 4px;">
                    <span style="font-size:1.4rem;animation:lmPulse 1.8s infinite;">⏳</span>
                    <div>
                        <div style="font-size:.85rem;color:#e2e8f0;font-weight:600;">
                            ${r.driver ? r.driver.name : 'Driver'} has been asked to accept this request.
                        </div>
                        <div style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:3px;">
                            The driver will Accept or Reject. Status updates automatically.
                        </div>
                    </div>
                </div>
            </div>`
            : '';

        document.getElementById('reqDetailBody').innerHTML = `
            <div class="adm-detail-body">
                ${liveTrackingBadge}
                ${hasCoords ? `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <div id="nearbyCountBadge" style="font-size:.72rem;color:rgba(255,255,255,.35);">
                        <i class="fa fa-spinner fa-spin" style="width:12px;"></i> Loading nearby drivers…
                    </div>
                </div>
                <div id="reqDetailMap"></div>` : ''}
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>RREB ID</span><strong>${r.rreb_id || '—'}</strong></div>
                    <div class="adm-detail-cell"><span>Mobile</span><strong>${r.mobile_no}</strong></div>
                </div>
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>Type</span>
                        <strong>${r.type === '1' ? 'Emergency' : r.type === '2' ? 'Non-Emergency' : r.type}</strong>
                    </div>
                    <div class="adm-detail-cell"><span>Status</span><strong id="admModalReqStatus" data-req-id="${r.id}">${humanStatus(r.status)}</strong></div>
                </div>
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>Hospital</span><strong>${r.hospital_name || '—'}</strong></div>
                    <div class="adm-detail-cell"><span>Pickup Address</span><strong>${r.pickup_address || '—'}</strong></div>
                </div>
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>Ambulance</span>
                        <strong>${r.ambulance ? r.ambulance.vehicle_number + ' — ' + r.ambulance.type : '—'}</strong>
                    </div>
                    <div class="adm-detail-cell"><span>Driver</span>
                        <strong>${r.driver ? r.driver.name + ' (' + r.driver.phone + ')' : '—'}</strong>
                        ${isActive && hasDriver ? `
                        <div style="margin-top:6px;">
                            <span id="driverOnlineStatus" style="display:inline-flex;align-items:center;gap:5px;
                                font-size:.72rem;font-weight:600;padding:2px 9px 2px 7px;border-radius:20px;
                                background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);">
                                <span id="driverStatusDot" style="width:7px;height:7px;border-radius:50%;
                                    background:#22c55e;display:inline-block;flex-shrink:0;
                                    animation:lmPulse 1.8s infinite;"></span>
                                <span id="driverStatusText">Online</span>
                            </span>
                        </div>` : ''}
                    </div>
                </div>
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>Coordinates</span>
                        <strong>${hasCoords ? r.pickup_lat + ', ' + r.pickup_lng : '—'}</strong>
                    </div>
                    <div class="adm-detail-cell"><span>Dispatched At</span><strong>${r.dispatched_at || '—'}</strong></div>
                </div>
                <div class="adm-detail-pair">
                    <div class="adm-detail-cell"><span>Notes</span><strong>${r.notes || '—'}</strong></div>
                    <div class="adm-detail-cell"><span>Submitted</span><strong>${r.created_at}</strong></div>
                </div>
                ${dispatchSection}
            </div>`;

        // Register driver for live tracking
        if (isActive && hasDriver) {
            _activeReqDriverId = String(r.driver.id);
            _excludedDriverId  = _activeReqDriverId;

            // Seed from live-monitoring cache if modal has no coords
            if (!hasDriverCoords && window._drvLocations && window._drvLocations[_activeReqDriverId]) {
                const cached = window._drvLocations[_activeReqDriverId];
                r.driver.lat = cached.lat;
                r.driver.lng = cached.lng;
            }
        }

        if (hasCoords && typeof L !== 'undefined') {
            setTimeout(() => {
                if (reqDetailMap) { reqDetailMap.remove(); reqDetailMap = null; }
                _driverMarker = null;
                _admResetTrackingState();

                reqDetailMap = L.map('reqDetailMap').setView([r.pickup_lat, r.pickup_lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19,
                }).addTo(reqDetailMap);

                const hasHospital     = r.hospital_lat && r.hospital_lng;
                const hasDriverCoords = r.driver && r.driver.lat && r.driver.lng;
                const bounds          = [[r.pickup_lat, r.pickup_lng]];

                // ── Pickup marker (stored for smart-route hide/restore) ───────
                _admPickupMarker = L.marker([r.pickup_lat, r.pickup_lng], { icon: buildPersonIcon() })
                    .bindPopup('<b>User Pickup Location</b><br>' + r.pickup_address)
                    .addTo(reqDetailMap).openPopup();
                // Hide pickup when transporting (status 5) — but keep it for completion display
                if (r.status === '5') {
                    reqDetailMap.removeLayer(_admPickupMarker);
                    _admPickupMarker = null;
                }

                // ── Hospital marker ──────────────────────────────────────────
                if (hasHospital) {
                    _admHospitalMarker = L.marker([r.hospital_lat, r.hospital_lng], { icon: buildHospitalIcon() })
                        .bindPopup('<b>Destination Hospital</b><br>' + (r.hospital_name || ''))
                        .addTo(reqDetailMap);
                    bounds.push([r.hospital_lat, r.hospital_lng]);
                }

                // ── Driver (ambulance) marker ────────────────────────────────
                if (hasDriverCoords) {
                    _driverMarker = L.marker([r.driver.lat, r.driver.lng], {
                        icon: buildAmbulanceIcon(isActive)
                    })
                    .bindPopup(
                        '<b>🚑 Ambulance — Live</b><br>'
                        + (r.ambulance ? r.ambulance.vehicle_number : 'N/A')
                        + '<br><small>Driver: ' + r.driver.name + '</small>'
                    )
                    .addTo(reqDetailMap);
                    bounds.push([r.driver.lat, r.driver.lng]);

                    _updateLiveBadge(r.driver.lat, r.driver.lng, null);

                    // Kick off initial ETA fetch
                    if (isActive) _fetchETA(parseFloat(r.driver.lat), parseFloat(r.driver.lng));
                }

                // ── Init admin tracking data object ──────────────────────────
                _admTrackingData = {
                    status:      r.status,
                    pickupLat:   parseFloat(r.pickup_lat),
                    pickupLng:   parseFloat(r.pickup_lng),
                    hospitalLat: hasHospital     ? parseFloat(r.hospital_lat) : null,
                    hospitalLng: hasHospital     ? parseFloat(r.hospital_lng) : null,
                    driverLat:   hasDriverCoords ? parseFloat(r.driver.lat)   : null,
                    driverLng:   hasDriverCoords ? parseFloat(r.driver.lng)   : null,
                    acceptedLat: r.accepted_lat  ? parseFloat(r.accepted_lat) : null,
                    acceptedLng: r.accepted_lng  ? parseFloat(r.accepted_lng) : null,
                };

                // ── Draw route or completion summary ─────────────────────────
                if (r.status === '6') {
                    // Already completed — show static summary, no live route
                    _admTrackingStopped = true;
                    _admDrawCompletionSummary();
                } else {
                    _admApplySmartRoute(_admTrackingData, { forceRedraw: true });
                }

                if (bounds.length > 1) reqDetailMap.fitBounds(bounds, { padding: [40, 40] });
                reqDetailMap.invalidateSize();

                // Load nearby available drivers (exclude already-assigned driver)
                _loadNearbyDrivers(
                    r.pickup_lat, r.pickup_lng,
                    hasDriver ? r.driver.id : null
                );
            }, 250);
        }
    })
    .catch(() => {
        document.getElementById('reqDetailBody').innerHTML =
            '<p style="padding:20px;color:#f87171">Server error.</p>';
    });
}

/* ── Dispatch ──────────────────────────────────────────────────────────────── */
var _dispatchInFlight = false;
function submitInlineDispatch(id) {
    if (_dispatchInFlight) return;
    const ambId = document.getElementById('inline_ambulance_id').value;
    const drvId = document.getElementById('inline_driver_id').value;
    const notes = document.getElementById('inline_notes').value;
    const msg   = document.getElementById('inline_dispatch_msg');

    document.getElementById('inline_err_ambulance_id').textContent = '';
    document.getElementById('inline_err_driver_id').textContent = '';

    if (!ambId) { document.getElementById('inline_err_ambulance_id').textContent = 'Please select an ambulance.'; return; }
    if (!drvId) { document.getElementById('inline_err_driver_id').textContent = 'Please select a driver.'; return; }

    const fd = new FormData();
    fd.append('ambulance_id', ambId);
    fd.append('driver_id', drvId);
    fd.append('notes', notes);

    _dispatchInFlight = true;
    const dispatchBtn = document.querySelector('#reqDetailModal .adm-btn--primary[onclick^="submitInlineDispatch"]');
    if (dispatchBtn) { dispatchBtn.disabled = true; dispatchBtn.textContent = 'Sending…'; }
    if (msg) { msg.textContent = ''; msg.className = 'adm-form-msg'; }

    fetch(`${window.adminRoutes.requestsDispatch}/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msg.textContent = data.message;
            msg.className = 'adm-form-msg success';
            _dispatchInFlight = false;

            const r = data.request;
            const driverName   = r.driver ? r.driver.name : '—';
            const ambulanceNo  = r.ambulance ? r.ambulance.vehicle_number : '—';

            // ── Update the table row live (no reload) ───────────────────────
            admUpdateTableRowMeta(id, '8', 'Awaiting Acceptance', driverName, ambulanceNo);

            // ── Swap the modal dispatch form → "Awaiting Acceptance" panel ──
            const dispatchPanel = document.querySelector('#reqDetailModal .adm-dispatch-panel');
            if (dispatchPanel) {
                dispatchPanel.style.borderColor = 'rgba(251,191,36,.2)';
                dispatchPanel.style.background  = 'rgba(251,191,36,.04)';
                dispatchPanel.innerHTML = `
                    <div class="adm-dispatch-panel__title" style="color:#fbbf24;">
                        <i class="fa fa-clock"></i> Awaiting Driver Acceptance
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0 4px;">
                        <span style="font-size:1.4rem;animation:lmPulse 1.8s infinite;">⏳</span>
                        <div>
                            <div style="font-size:.85rem;color:#e2e8f0;font-weight:600;">
                                ${driverName} has been asked to accept this request.
                            </div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:3px;">
                                The driver will Accept or Reject. Status updates automatically.
                            </div>
                        </div>
                    </div>`;
            }

            // Update modal status text if open for this request
            const modalStatus = document.getElementById('admModalReqStatus');
            if (modalStatus && String(modalStatus.dataset.reqId) === String(id)) {
                modalStatus.textContent = 'Awaiting Acceptance';
            }
        } else {
            _dispatchInFlight = false;
            if (dispatchBtn) { dispatchBtn.disabled = false; dispatchBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Request'; }
            if (data.errors) {
                if (data.errors.ambulance_id)
                    document.getElementById('inline_err_ambulance_id').textContent = data.errors.ambulance_id[0];
                if (data.errors.driver_id)
                    document.getElementById('inline_err_driver_id').textContent = data.errors.driver_id[0];
            } else {
                msg.textContent = data.message || 'Dispatch failed.';
                msg.className = 'adm-form-msg error';
            }
        }
    })
    .catch(() => {
        _dispatchInFlight = false;
        if (dispatchBtn) { dispatchBtn.disabled = false; dispatchBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Request'; }
        msg.textContent = 'Server error.'; msg.className = 'adm-form-msg error';
    });
}

/* ── Delete ────────────────────────────────────────────────────────────────── */
function deleteRequest(id) {
    if (!confirm('Permanently delete this emergency request? This cannot be undone.')) return;
    fetch(`${window.adminRoutes.requestsDelete}/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Failed to delete.');
    })
    .catch(() => alert('Server error.'));
}

/* ── Modal close — clean up ────────────────────────────────────────────────── */
document.getElementById('reqDetailModal').addEventListener('hidden.bs.modal', function () {
    // Cancel all in-flight marker animations before destroying the map
    Object.keys(_markerAnimFrames).forEach(k => {
        cancelAnimationFrame(_markerAnimFrames[k]);
    });
    _markerAnimFrames = {};

    _admResetTrackingState();  // clean up live-tracking layers before map is destroyed
    if (reqDetailMap) { reqDetailMap.remove(); reqDetailMap = null; }
    _activeReqDriverId = null;
    _driverMarker      = null;
    _activeReqStatus   = null;
    _activePickupLat   = null;
    _activePickupLng   = null;
    _activeHospitalLat = null;
    _activeHospitalLng = null;
    _etaLastFetch      = 0;
    _etaInFlight       = false;
    _nearbyMarkers     = {};
    _nearbyDriverData  = {};
    _nearbyDriverPrev  = {};
    _nearbyDriverEta   = {};
    _excludedDriverId  = null;
});

/* ── Real-time: driver moved ───────────────────────────────────────────────── */
window.admDriverLocationUpdated = function(e) {
    const driverId = String(e.driver_id);
    const lat = parseFloat(e.lat);
    const lng = parseFloat(e.lng);
    if (isNaN(lat) || isNaN(lng)) return;

    // ── Always update the global location cache, even when modal is closed ────
    // This lets viewRequest() seed marker positions from cache when the modal
    // opens after drivers have already been moving.
    window._drvLocations[driverId] = { lat, lng, time: e.time, ts: e.ts };

    // Nothing to render if the modal map isn't open
    if (!reqDetailMap) return;

    // ── Assigned driver: smooth-move the live tracking marker + badge + ETA ───
    if (_activeReqDriverId && _activeReqDriverId === driverId) {
        if (_driverMarker) {
            _animateMarkerTo('assigned', _driverMarker, lat, lng);
            _driverMarker.setIcon(buildAmbulanceIcon(true));
            _driverMarker.getPopup() && _driverMarker.setPopupContent(
                '<b>🚑 Ambulance — Live</b><br>Updated: ' + (e.time || new Date().toLocaleTimeString())
            );
        } else {
            _driverMarker = L.marker([lat, lng], { icon: buildAmbulanceIcon(true) })
                .bindPopup('<b>🚑 Ambulance — Live</b><br>Updated: ' + (e.time || new Date().toLocaleTimeString()))
                .addTo(reqDetailMap);
        }
        _updateLiveBadge(lat, lng, e.time);
        _fetchETA(lat, lng);

        // ── Breadcrumb trail + live route update ─────────────────────────────
        _admAppendDrivenPath(lat, lng);
        if (_admTrackingData && !_admTrackingStopped) {
            _admTrackingData.driverLat = lat;
            _admTrackingData.driverLng = lng;
            _admApplySmartRoute(_admTrackingData, { noFit: true, checkDeviation: true });
        }

        console.log('[EmergencyMap] Assigned driver', driverId, 'moved to', lat, lng);
    }

    // ── Nearby driver: smooth-slide + live speed + OSRM ETA popup update ────
    if (_nearbyMarkers[driverId]) {
        _animateMarkerTo('nearby_' + driverId, _nearbyMarkers[driverId], lat, lng);

        // ── Speed calculation ────────────────────────────────────────────────
        const prev = _nearbyDriverPrev[driverId];
        const now  = Date.now();
        let computedSpeed = prev ? (prev.speedKmh ?? null) : null;
        if (prev) {
            const movedKm   = _haversineKm(prev.lat, prev.lng, lat, lng);
            const elapsedHr = (now - prev.ts) / 3_600_000;
            // Trust the reading only if sample window is ≤ 5 minutes
            if (elapsedHr > 0 && elapsedHr < 0.0833) {
                computedSpeed = movedKm / elapsedHr;
            }
        }
        _nearbyDriverPrev[driverId] = { lat, lng, ts: now, speedKmh: computedSpeed };

        // ── Update straight-line distance readout ────────────────────────────
        if (_nearbyDriverData[driverId] && _activePickupLat) {
            _nearbyDriverData[driverId].distKm =
                _haversineKm(_activePickupLat, _activePickupLng, lat, lng);
        }

        // ── Refresh popup + throttled OSRM driving ETA ───────────────────────
        _nearbyMarkers[driverId].setPopupContent(_buildNearbyPopupContent(driverId));
        _fetchNearbyETA(driverId, lat, lng);

        console.log('[EmergencyMap] Nearby driver', driverId, 'moved to', lat, lng,
            computedSpeed != null ? '@ ' + Math.round(computedSpeed) + ' km/h' : '');
        return;
    }

    // ── Brand-new driver appeared mid-session (came online / entered range) ───
    if (_activePickupLat && driverId !== _excludedDriverId) {
        const dist = _haversineKm(_activePickupLat, _activePickupLng, lat, lng);
        if (dist <= 30) {
            const name  = e.driver_name || ('Driver ' + driverId);
            const phone = (window.reqDrivers || []).find(d => String(d.id) === driverId)?.label?.split('—')[1]?.trim() || '';
            _addSingleNearbyMarker(driverId, lat, lng, name, phone, dist);
        }
    }
};

/* ── Helper: update table row status pill + driver/ambulance cells ──────────── */
function admUpdateTableRowMeta(reqId, status, statusLabel, driverName, ambulanceNo) {
    const row = document.querySelector('tr[data-req-id="' + reqId + '"]');
    if (!row) return;

    const statusTd = row.querySelector('[data-status-cell]');
    const pill     = statusTd ? statusTd.querySelector('.status-pill') : null;
    if (pill) {
        pill.className   = 'status-pill ' + (_admStatusClassMap[status] || 'status-3');
        pill.textContent = statusLabel || humanStatus(status);
        pill.style.animation = 'admRowStatusFlash .6s ease both';
        setTimeout(function() { pill.style.animation = ''; }, 650);
    }

    const driverTd    = row.querySelector('[data-driver-cell]');
    const ambulanceTd = row.querySelector('[data-ambulance-cell]');
    if (driverTd    && driverName   !== undefined) driverTd.textContent    = driverName    || '—';
    if (ambulanceTd && ambulanceNo  !== undefined) ambulanceTd.textContent = ambulanceNo   || '—';
}

/* ── Helper: reload modal content if it's showing a specific request ─────────── */
function admRefreshModalIfOpen(reqId) {
    const modalStatus = document.getElementById('admModalReqStatus');
    if (modalStatus && String(modalStatus.dataset.reqId) === String(reqId)) {
        viewRequest(parseInt(reqId, 10));
    }
}

/* ── Real-time: request status changed ─────────────────────────────────────── */
var _admStatusClassMap = {
    '1': 'status-3', '2': 'status-2', '3': 'status-2',
    '4': 'status-1', '5': 'status-2', '6': 'status-1', '7': 'status-4',
    '8': 'status-3',
};

window.admRequestStatusUpdated = function(e) {
    // ── Table row: status pill + driver/ambulance cells ───────────────────────
    const row = document.querySelector('tr[data-req-id="' + e.request_id + '"]');
    if (row) {
        const statusTd = row.querySelector('[data-status-cell]');
        const pill = statusTd ? statusTd.querySelector('.status-pill') : null;
        if (pill) {
            pill.className = 'status-pill ' + (_admStatusClassMap[e.status] || 'status-3');
            pill.textContent = e.status_label || humanStatus(e.status);
        }

        // Update driver column
        const driverTd = row.querySelector('[data-driver-cell]');
        if (driverTd) {
            if (e.status === '1') {
                // Driver rejected — clear assignment
                driverTd.textContent = '—';
            } else if (e.driver_name) {
                driverTd.textContent = e.driver_name;
            }
        }

        // Update ambulance column
        const ambulanceTd = row.querySelector('[data-ambulance-cell]');
        if (ambulanceTd) {
            if (e.status === '1') {
                // Driver rejected — clear assignment
                ambulanceTd.textContent = '—';
            } else if (e.ambulance_no) {
                ambulanceTd.textContent = e.ambulance_no;
            } else if (e.ambulance_id && window.reqAmbulances) {
                // For emergency.status.updated (status 8) — look up by id
                const amb = window.reqAmbulances.find(function(a) { return String(a.id) === String(e.ambulance_id); });
                if (amb) ambulanceTd.textContent = amb.label.split('—')[0].trim();
            }
        }
    }

    // ── Open modal's Status cell ──────────────────────────────────────────────
    const modalStatus = document.getElementById('admModalReqStatus');
    if (modalStatus && String(modalStatus.dataset.reqId) === String(e.request_id)) {
        modalStatus.textContent = e.status_label || humanStatus(e.status);

        // Driver accepted → reload modal to show live tracking panel
        if (e.status === '2') {
            setTimeout(function() { admRefreshModalIfOpen(e.request_id); }, 400);
        }

        // Driver rejected → reload modal to show fresh dispatch form
        if (e.status === '1') {
            // Show immediate "Rejected" feedback inside whatever panel is currently open
            // (may be .adm-dispatch-panel for pending, or the awaiting-acceptance panel)
            const anyPanel = document.querySelector('#reqDetailModal .adm-dispatch-panel')
                          || document.querySelector('#reqDetailModal [class*="adm-dispatch"]');
            if (anyPanel) {
                anyPanel.style.borderColor = 'rgba(239,68,68,.25)';
                anyPanel.style.background  = 'rgba(239,68,68,.04)';
                anyPanel.innerHTML = `
                    <div class="adm-dispatch-panel__title">
                        <i class="fa fa-exclamation-triangle" style="color:#f87171;"></i>
                        <span style="color:#f87171;"> Driver Rejected</span>
                        — Request reset to <strong>Pending</strong>
                    </div>
                    <div style="font-size:.82rem;color:rgba(255,255,255,.45);padding:8px 0 4px;">
                        The driver declined this request. You can now select a different driver and ambulance.
                    </div>`;
            }
            // Always refresh the modal — works whether dispatch panel existed or not
            // (handles rejection from status 8 where no .adm-dispatch-panel is shown)
            setTimeout(function() { admRefreshModalIfOpen(e.request_id); }, 700);
        }
    }

    // Update internal status so ETA routing picks the right destination
    if (_activeReqDriverId) {
        _activeReqStatus = e.status;

        // ── Admin live-tracking: route switch + completion ────────────────────
        if (_admTrackingData && !_admTrackingStopped) {
            _admTrackingData.status = e.status;
            // Pull driver coords from the event if available
            if (e.lat && e.lng) {
                _admTrackingData.driverLat = parseFloat(e.lat);
                _admTrackingData.driverLng = parseFloat(e.lng);
            }
            // Hide pickup marker when driver starts transporting
            if (e.status === '5' && _admPickupMarker && reqDetailMap) {
                reqDetailMap.removeLayer(_admPickupMarker);
                _admPickupMarker = null;
            }
            if (e.status === '6') {
                // Trip complete — draw static completion summary
                if (e.accepted_lat) _admTrackingData.acceptedLat = parseFloat(e.accepted_lat);
                if (e.accepted_lng) _admTrackingData.acceptedLng = parseFloat(e.accepted_lng);
                setTimeout(_admStopTracking, 400);
            } else if (e.status !== '7') {
                // Switch route for new status (force redraw)
                _admLastRouteLat = null;
                _admLastRouteLng = null;
                _admApplySmartRoute(_admTrackingData, { forceRedraw: true });
            }
        }

        // Status changed to Arrived — show "At pickup" immediately
        if (e.status === '4') {
            const etaEl = document.getElementById('liveTrackETA');
            if (etaEl) etaEl.innerHTML =
                '<i class="fa fa-circle-check" style="color:#22c55e;width:13px;"></i>'
                + ' <strong style="color:#22c55e;">At pickup</strong>';
        }

        // Completed / Cancelled — dim the badge + status pill
        if (e.status === '6' || e.status === '7') {
            _activeReqDriverId = null;
            const badge = document.getElementById('liveTrackBadge');
            if (badge) {
                badge.style.background  = 'rgba(100,116,139,.07)';
                badge.style.borderColor = 'rgba(100,116,139,.18)';
            }
            const dot = document.getElementById('liveTrackDot');
            if (dot) { dot.style.background = '#64748b'; dot.style.animation = 'none'; }
            const lbl = document.getElementById('liveTrackLabel');
            if (lbl) { lbl.textContent = e.status === '6' ? 'DONE' : 'CANCELLED'; lbl.style.color = '#64748b'; }
            const etaEl = document.getElementById('liveTrackETA');
            if (etaEl) etaEl.innerHTML = '';

            const statusWrap = document.getElementById('driverOnlineStatus');
            const statusDot  = document.getElementById('driverStatusDot');
            const statusText = document.getElementById('driverStatusText');
            if (statusWrap) { statusWrap.style.background = 'rgba(100,116,139,.1)'; statusWrap.style.color = '#94a3b8'; statusWrap.style.borderColor = 'rgba(100,116,139,.2)'; }
            if (statusDot)  { statusDot.style.background = '#64748b'; statusDot.style.animation = 'none'; }
            if (statusText) { statusText.textContent = e.status === '6' ? 'Completed' : 'Cancelled'; }
        }
    }

    // ── Move completed/cancelled ride to Past Rides in real-time ─────────────
    // Note: admAddPastRideRow is defined in past_rides.blade.php (not this page).
    // realtime.js calls it directly after this handler so it always fires on
    // the past-rides tab regardless of which page handler is active.
    if (e.status === '6' || e.status === '7') {
        // Close dispatch modal if it's open for this request
        setTimeout(function() {
            try {
                var ms = document.getElementById('admModalReqStatus');
                if (ms && String(ms.dataset.reqId) === String(e.request_id)) {
                    getReqDetailModal().hide();
                }
            } catch(ex) {}
        }, 1400);
        // Animate row out after the user sees the final status
        setTimeout(function() { admAnimateRemoveRow(e.request_id); }, 1600);
    }
};

/* ── Animate an active-requests table row out and remove it ─────────────────── */
function admAnimateRemoveRow(reqId) {
    var row = document.querySelector('#reqTable tbody tr[data-req-id="' + reqId + '"]');
    if (!row) return;
    row.style.transition = 'opacity .4s ease, transform .4s ease';
    row.style.opacity    = '0';
    row.style.transform  = 'translateX(28px)';
    setTimeout(function() {
        if (row.parentNode) row.remove();
        var tbody = document.querySelector('#reqTable tbody');
        if (tbody && !tbody.querySelector('tr')) {
            var pPage = (window.adminRoutes && window.adminRoutes.pastRidesPage) || '/admin/emergency/past-rides';
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5"'
                + ' style="color:rgba(255,255,255,.3);font-size:.9rem;">'
                + '<i class="fa fa-inbox" style="display:block;font-size:1.6rem;margin-bottom:10px;opacity:.2;"></i>'
                + 'No active requests. '
                + '<a href="' + pPage + '" style="color:#60a5fa;text-decoration:none;">View Past Rides →</a>'
                + '</td></tr>';
        }
    }, 420);
}

/* ── Real-time: driver came online or went offline ──────────────────────────── */
window.admDriverAvailabilityUpdated = function(e) {
    const driverId = String(e.driver_id);
    const status   = String(e.status);
    const isOnline = status === '1';
    const isBusy   = status === '3';

    console.log('[Reverb] Driver', e.driver_name, 'is now', e.status_label, '(status=' + status + ')');

    // ── Sync the dispatch modal's driver dropdown ─────────────────────────────
    if (isOnline) {
        // Add driver to pool if not already present
        var drvExisting = (window.reqDrivers || []).findIndex(function(d) {
            return String(d.id) === driverId;
        });
        if (drvExisting === -1) {
            var phone = e.phone || '';
            window.reqDrivers = (window.reqDrivers || []).concat([{
                id:    e.driver_id,
                label: e.driver_name + (phone ? ' \u2014 ' + phone : ''),
            }]);
            console.log('[Dispatch] Driver', e.driver_name, 'added to dispatch pool');
        }
    } else {
        // Remove driver from pool (offline or busy)
        var drvBefore = (window.reqDrivers || []).length;
        window.reqDrivers = (window.reqDrivers || []).filter(function(d) {
            return String(d.id) !== driverId;
        });
        if ((window.reqDrivers || []).length < drvBefore) {
            console.log('[Dispatch] Driver', e.driver_name, 'removed from dispatch pool (', e.status_label, ')');
        }
    }
    _refreshDispatchDriverDropdown();

    // ── Update assigned driver's live-tracking badge + status pill ────────────
    if (_activeReqDriverId && _activeReqDriverId === driverId) {
        // Stable IDs set in buildLiveTrackingBadge
        const badge      = document.getElementById('liveTrackBadge');
        const dot        = document.getElementById('liveTrackDot');
        const lbl        = document.getElementById('liveTrackLabel');
        // Status pill in the driver info row
        const statusWrap = document.getElementById('driverOnlineStatus');
        const statusDot  = document.getElementById('driverStatusDot');
        const statusText = document.getElementById('driverStatusText');

        if (isOnline) {
            // ── Green / Online ─────────────────────────────────────────────────
            if (badge) { badge.style.background = 'rgba(34,197,94,0.07)'; badge.style.borderColor = 'rgba(34,197,94,0.22)'; }
            if (dot)   { dot.style.background = '#22c55e'; dot.style.animation = 'lmPulse 1.8s infinite'; }
            if (lbl)   { lbl.textContent = 'LIVE'; lbl.style.color = '#22c55e'; }

            if (statusWrap) { statusWrap.style.background = 'rgba(34,197,94,.1)'; statusWrap.style.color = '#22c55e'; statusWrap.style.borderColor = 'rgba(34,197,94,.2)'; }
            if (statusDot)  { statusDot.style.background = '#22c55e'; statusDot.style.animation = 'lmPulse 1.8s infinite'; }
            if (statusText) { statusText.textContent = 'Online'; }

            // If the driver has last-known coords in the event, restore/create
            // their map marker immediately without waiting for the next GPS ping.
            const eLat = e.lat  != null ? parseFloat(e.lat)  : NaN;
            const eLng = e.lng  != null ? parseFloat(e.lng)  : NaN;
            const hasStoredCoords = !isNaN(eLat) && !isNaN(eLng);

            if (reqDetailMap && hasStoredCoords) {
                if (_driverMarker) {
                    _driverMarker.setLatLng([eLat, eLng]);
                    _driverMarker.setIcon(buildAmbulanceIcon(true));
                } else {
                    _driverMarker = L.marker([eLat, eLng], { icon: buildAmbulanceIcon(true) })
                        .bindPopup('<b>🚑 Ambulance — Live</b><br>Driver came online: ' + (e.time || ''))
                        .addTo(reqDetailMap);
                }
                _updateLiveBadge(eLat, eLng, e.time);
                _fetchETA(eLat, eLng);
            }

            // Restore ETA spinner if no stored coords (it will update on the next GPS ping)
            const etaEl = document.getElementById('liveTrackETA');
            if (etaEl && !hasStoredCoords) etaEl.innerHTML =
                '<i class="fa fa-spinner fa-spin" style="width:13px;color:rgba(255,255,255,.3);"></i>'
                + ' <span style="color:rgba(255,255,255,.3);">Waiting for location…</span>';

        } else if (isBusy) {
            // ── Amber / On Duty ────────────────────────────────────────────────
            if (badge) { badge.style.background = 'rgba(245,158,11,0.07)'; badge.style.borderColor = 'rgba(245,158,11,0.2)'; }
            if (dot)   { dot.style.background = '#f59e0b'; dot.style.animation = 'none'; }
            if (lbl)   { lbl.textContent = 'BUSY'; lbl.style.color = '#f59e0b'; }

            if (statusWrap) { statusWrap.style.background = 'rgba(245,158,11,.1)'; statusWrap.style.color = '#f59e0b'; statusWrap.style.borderColor = 'rgba(245,158,11,.2)'; }
            if (statusDot)  { statusDot.style.background = '#f59e0b'; statusDot.style.animation = 'none'; }
            if (statusText) { statusText.textContent = 'On Duty'; }

        } else {
            // ── Red / Offline ──────────────────────────────────────────────────
            if (badge) { badge.style.background = 'rgba(239,68,68,0.07)'; badge.style.borderColor = 'rgba(239,68,68,0.2)'; }
            if (dot)   { dot.style.background = '#ef4444'; dot.style.animation = 'none'; }
            if (lbl)   { lbl.textContent = 'OFFLINE'; lbl.style.color = '#ef4444'; }

            if (statusWrap) { statusWrap.style.background = 'rgba(239,68,68,.1)'; statusWrap.style.color = '#ef4444'; statusWrap.style.borderColor = 'rgba(239,68,68,.2)'; }
            if (statusDot)  { statusDot.style.background = '#ef4444'; statusDot.style.animation = 'none'; }
            if (statusText) { statusText.textContent = 'Offline'; }

            const etaEl = document.getElementById('liveTrackETA');
            if (etaEl) etaEl.innerHTML =
                '<i class="fa fa-triangle-exclamation" style="color:#ef4444;width:13px;"></i>'
                + ' <span style="color:#ef4444;">Driver is offline</span>';

            // Cancel any in-flight animation and evict from location cache
            if (_markerAnimFrames['assigned']) {
                cancelAnimationFrame(_markerAnimFrames['assigned']);
                delete _markerAnimFrames['assigned'];
            }
            delete window._drvLocations[driverId];
        }

        return; // assigned driver handled — skip nearby logic below
    }

    // ── Nearby driver went offline/busy → remove amber marker ────────────────
    if (reqDetailMap && _activePickupLat) {
        if (!isOnline && !isBusy) {
            _removeNearbyMarker(driverId);
        } else if (isBusy) {
            // Busy drivers (on another assignment) should not be in the nearby pool
            _removeNearbyMarker(driverId);
        } else {
            // Driver came back online — immediately restore their marker using last-known
            // coordinates broadcast with the availability event (no GPS ping needed).
            const eLat = e.lat != null ? parseFloat(e.lat) : NaN;
            const eLng = e.lng != null ? parseFloat(e.lng) : NaN;

            // Prefer coords from the event payload; fall back to the global cache
            const cachedLoc = window._drvLocations[driverId];
            const resolvedLat = !isNaN(eLat) ? eLat : (cachedLoc ? cachedLoc.lat : NaN);
            const resolvedLng = !isNaN(eLng) ? eLng : (cachedLoc ? cachedLoc.lng : NaN);

            if (!isNaN(resolvedLat) && !isNaN(resolvedLng) && driverId !== _excludedDriverId) {
                const dist = _haversineKm(_activePickupLat, _activePickupLng, resolvedLat, resolvedLng);
                if (dist <= 30) {
                    const name  = e.driver_name || ('Driver ' + driverId);
                    const phone = (window.reqDrivers || []).find(d => String(d.id) === driverId)?.label?.split('—')[1]?.trim() || '';
                    _addSingleNearbyMarker(driverId, resolvedLat, resolvedLng, name, phone, dist);
                    console.log('[NearbyDrivers] Driver', e.driver_name, 'online — restored marker immediately at last-known coords');
                } else {
                    console.log('[NearbyDrivers] Driver', e.driver_name, 'online but outside 30 km range');
                }
            } else {
                // No stored coords yet — the first GPS ping will add them
                console.log('[NearbyDrivers] Driver', e.driver_name, 'online — no stored coords, awaiting first GPS ping');
            }
        }
    }
};

// ── Inject row-flash keyframe once ───────────────────────────────────────────
(function () {
    if (document.getElementById('admRowFlashStyle')) return;
    var s = document.createElement('style');
    s.id  = 'admRowFlashStyle';
    s.textContent = '@keyframes admRowStatusFlash{'
        + '0%{background:rgba(59,130,246,.18)}'
        + '100%{background:transparent}'
        + '}'
        + '@keyframes admNewRowAppear{'
        + '0%{opacity:0;background:rgba(52,211,153,.14);transform:translateY(-6px)}'
        + '60%{opacity:1;background:rgba(52,211,153,.1)}'
        + '100%{background:transparent;transform:translateY(0)}'
        + '}';
    document.head.appendChild(s);
})();

/* ── Real-time: prepend a newly submitted emergency request row ──────────────
 * Called by realtime.js when an 'emergency.submitted' WebSocket event arrives.
 * Mirrors the exact Blade row so status-updates, filtering, and modals work.
 * ─────────────────────────────────────────────────────────────────────────── */
window.admAddEmergencyRow = function (data) {
    var id = data.request_id || data.emergency_id;
    if (!id) return;

    // ── Deduplicate: skip if this tab already has the row ────────────────
    if (document.querySelector('#reqTable tbody tr[data-req-id="' + id + '"]')) return;

    var rrebId   = data.rreb_id        || '—';
    var mobile   = data.mobile_no      || '';
    var hospital = data.hospital_name  || '';
    var pickup   = data.pickup_address || '';
    var type     = String(data.type    || '1');
    var timeStr  = data.time           || '';

    function _lim(s, n) { return s.length > n ? s.substring(0, n) + '\u2026' : s; }
    function _esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    var typePill = type === '1'
        ? '<span class="status-pill status-4">Emergency</span>'
        : '<span class="status-pill status-3">Non-Emergency</span>';

    // Build searchable string for filterReqs()
    var searchStr = (rrebId + ' ' + hospital + ' ' + pickup + ' ' + mobile).toLowerCase();

    var row = '<tr class="pgd-row"'
        + ' data-req-id="'  + parseInt(id, 10)   + '"'
        + ' data-status="1"'
        + ' data-type="'    + _esc(type)          + '"'
        + ' data-search="'  + _esc(searchStr)     + '">'

        // RREB ID
        + '<td class="ps-4">'
        + '<span style="font-family:monospace;font-size:0.8rem;background:rgba(255,255,255,0.06);'
        + 'padding:3px 8px;border-radius:6px;color:rgba(255,255,255,0.75);white-space:nowrap;">'
        + _esc(rrebId) + '</span></td>'

        // User / Mobile
        + '<td class="fs-xs" style="color:var(--adm-muted);">'
        + '<div>Guest</div>'
        + '<small class="adm-muted">' + _esc(mobile) + '</small></td>'

        // Hospital
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _esc(_lim(hospital, 22)) + '</td>'

        // Pickup Address
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _esc(_lim(pickup, 22)) + '</td>'

        // Type pill
        + '<td>' + typePill + '</td>'

        // Status → Pending (status-3 = amber/yellow)
        + '<td data-status-cell><span class="status-pill status-3">Pending</span></td>'

        // Ambulance (not assigned yet)
        + '<td class="fs-xs" style="color:var(--adm-muted);" data-ambulance-cell>—</td>'

        // Driver (not assigned yet)
        + '<td class="fs-xs" style="color:var(--adm-muted);" data-driver-cell>—</td>'

        // Requested at
        + '<td class="fs-xs" style="color:var(--adm-muted);white-space:nowrap;">'
        + _esc(timeStr) + '</td>'

        // Actions
        + '<td><div class="d-flex gap-1">'
        + '<button class="btn-adm-icon" title="View / Dispatch" onclick="viewRequest('
        + parseInt(id, 10) + ')"><i class="fa fa-eye"></i></button>'
        + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteRequest('
        + parseInt(id, 10) + ')"><i class="fa fa-trash"></i></button>'
        + '</div></td>'
        + '</tr>';

    // ── If the page shows "No emergency requests yet", swap in a real table
    var card = document.querySelector('.adm-content .card');
    if (!card) return;

    if (card.querySelector('.adm-empty')) {
        card.innerHTML =
            '<div class="pgd-scroll">'
            + '<table class="table table-hover mb-0" id="reqTable">'
            + '<thead><tr>'
            + '<th class="ps-4">RREB ID</th>'
            + '<th>User / Mobile</th>'
            + '<th>Hospital</th>'
            + '<th>Pickup Address</th>'
            + '<th>Type</th>'
            + '<th>Status</th>'
            + '<th>Ambulance</th>'
            + '<th>Driver</th>'
            + '<th>Requested</th>'
            + '<th>Actions</th>'
            + '</tr></thead><tbody></tbody></table></div>';
    }

    var tbody = document.querySelector('#reqTable tbody');
    if (!tbody) return;

    // ── Prepend and animate ──────────────────────────────────────────────
    tbody.insertAdjacentHTML('afterbegin', row);

    var newRow = tbody.querySelector('tr[data-req-id="' + parseInt(id, 10) + '"]');
    if (newRow) {
        newRow.style.animation = 'admNewRowAppear .55s ease both';
        setTimeout(function () { newRow.style.animation = ''; }, 600);
    }

    // ── Update "N Total" counter pill in the page header ─────────────────
    var totalPill = document.querySelector('.adm-page-header .status-pill');
    if (totalPill) {
        var cur = parseInt(totalPill.textContent, 10) || 0;
        totalPill.textContent = (cur + 1) + ' Total';
    }

    console.log('[Reverb] New emergency row prepended — RREB:', rrebId, '| ID:', id);
};
