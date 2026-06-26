/* ── Real-Time Tracking — Leaflet + LRM + Reverb ─────────────────────────── */

/* ═══════════════════════════════════════════════════════════════════════════
 * STATE
 * ═════════════════════════════════════════════════════════════════════════ */
let trackingMap, pickupMarker, driverMarker, lrmRoute;
let hospitalMarker = null;

/* Grey breadcrumb trail (actual GPS positions driven so far) */
let _drivenPath     = [];   // [[lat,lng], ...]
let _drivenPolyline = null; // Leaflet Polyline — grey trail

/* Planned OSRM route coordinates — used for off-route detection */
let _plannedRouteCoords = [];

/* Set true when ride completes — all GPS handlers check this first */
let _trackingStopped = false;

/* Throttle: last from-point that triggered an OSRM redraw */
let _lastRouteLat = null;
let _lastRouteLng = null;

const ROUTE_REDRAW_THRESHOLD_M = 50;   // metres moved before routine redraw
const DEVIATION_THRESHOLD_M    = 180;  // metres off-plan before forced redraw

/* ═══════════════════════════════════════════════════════════════════════════
 * STATUS MAPS
 * ═════════════════════════════════════════════════════════════════════════ */
const STATUS_TEXTS = {
    1: 'Your request has been received and is being reviewed.',
    2: 'An ambulance has been assigned and will depart shortly.',
    8: 'An ambulance has been assigned and will depart shortly.',
    3: 'The ambulance is on its way to your location.',
    4: 'The paramedic team has arrived at your pickup point.',
    5: 'You are being transported to the hospital.',
    6: 'Your trip has been completed. Thank you.',
    7: 'This request has been cancelled.',
};

const STATUS_LABELS = {
    1:  '⏳ Awaiting Dispatch',
    2:  '🚑 Ambulance Assigned',
    8:  '🚑 Ambulance Assigned',
    3:  '🚨 En Route to You',
    4:  '📍 Arrived at Scene',
    5:  '🏥 Transporting',
    6:  '✅ Trip Completed',
    7:  '❌ Cancelled',
};

const STATUS_STEP = { '1':0, '2':1, '8':1, '3':2, '4':3, '5':4, '6':5, '7':-1 };

/* ═══════════════════════════════════════════════════════════════════════════
 * MAP ICONS
 * ═════════════════════════════════════════════════════════════════════════ */
function makePickupIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#D72C42;border:3px solid #fff;border-radius:50%;width:40px;height:40px;
                    box-shadow:0 2px 16px rgba(215,44,66,0.55);display:flex;align-items:center;justify-content:center;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="22" height="22">
                   <circle cx="12" cy="7" r="4"/>
                   <path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>
                 </svg>
               </div>`,
        iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -28],
    });
}

function makeAmbulanceIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#1d4ed8;border:3px solid #fff;border-radius:50%;width:40px;height:40px;
                    box-shadow:0 2px 16px rgba(29,78,216,0.6);display:flex;align-items:center;justify-content:center;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="22" height="22">
                   <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8
                            c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8
                            l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5
                            S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5
                            -.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                 </svg>
               </div>`,
        iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -28],
    });
}

function makeHospitalIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#16a34a;border:3px solid #fff;border-radius:50%;width:40px;height:40px;
                    box-shadow:0 2px 16px rgba(22,163,74,0.55);display:flex;align-items:center;justify-content:center;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="22" height="22">
                   <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z
                            m-7 3a1 1 0 0 1 1 1v3h3a1 1 0 0 1 0 2h-3v3a1 1 0 0 1-2 0v-3H8a1 1 0 0 1 0-2
                            h3V7a1 1 0 0 1 1-1z"/>
                 </svg>
               </div>`,
        iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -28],
    });
}

/* Checkered-flag icon for where the driver started/accepted the job */
function makeDriverStartIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="background:#7c3aed;border:3px solid #fff;border-radius:50%;width:40px;height:40px;
                    box-shadow:0 2px 16px rgba(124,58,237,0.55);display:flex;align-items:center;justify-content:center;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                   <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/>
                 </svg>
               </div>`,
        iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -28],
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ROUTE LOGIC  (live ride)
 * ═════════════════════════════════════════════════════════════════════════ */
function getSmartRoutePoints(data) {
    const status      = String(data.status);
    const hasDriver   = !!(data.driverLat && data.driverLng);
    const hasHospital = !!(data.hospitalLat && data.hospitalLng);

    if (status === '1') {
        if (hasHospital) return { from: [data.pickupLat, data.pickupLng], to: [data.hospitalLat, data.hospitalLng] };
        return null;
    }
    if (status === '2' || status === '8') {
        if (hasDriver) return { from: [data.driverLat, data.driverLng], to: [data.pickupLat, data.pickupLng] };
        if (hasHospital) return { from: [data.pickupLat, data.pickupLng], to: [data.hospitalLat, data.hospitalLng] };
        return null;
    }
    if (status === '3' || status === '4') {
        if (hasDriver) return { from: [data.driverLat, data.driverLng], to: [data.pickupLat, data.pickupLng] };
        return null;
    }
    if (status === '5') {
        if (hasDriver && hasHospital) return { from: [data.driverLat, data.driverLng], to: [data.hospitalLat, data.hospitalLng] };
        if (hasHospital) return { from: [data.pickupLat, data.pickupLng], to: [data.hospitalLat, data.hospitalLng] };
        return null;
    }
    return null;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GEOMETRY HELPERS
 * ═════════════════════════════════════════════════════════════════════════ */
function _haversine(lat1, lng1, lat2, lng2) {
    const R    = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a    = Math.sin(dLat / 2) ** 2 +
                 Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                 Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function _pointToSegmentDist(pLat, pLng, aLat, aLng, bLat, bLng) {
    const dLat = bLat - aLat, dLng = bLng - aLng;
    const lenSq = dLat * dLat + dLng * dLng;
    if (lenSq === 0) return _haversine(pLat, pLng, aLat, aLng);
    const t = Math.max(0, Math.min(1, ((pLat - aLat) * dLat + (pLng - aLng) * dLng) / lenSq));
    return _haversine(pLat, pLng, aLat + t * dLat, aLng + t * dLng);
}

function _isOffRoute(lat, lng) {
    if (_plannedRouteCoords.length < 2) return false;
    for (let i = 0; i < _plannedRouteCoords.length - 1; i++) {
        if (_pointToSegmentDist(lat, lng,
            _plannedRouteCoords[i][0],   _plannedRouteCoords[i][1],
            _plannedRouteCoords[i+1][0], _plannedRouteCoords[i+1][1]
        ) < DEVIATION_THRESHOLD_M) return false;
    }
    return true;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * MAP INITIALISATION
 * ═════════════════════════════════════════════════════════════════════════ */
function initTrackingMap() {
    const lat = window.trackingData.pickupLat;
    const lng = window.trackingData.pickupLng;

    trackingMap = L.map('trackingMap').setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(trackingMap);

    const s = String(window.trackingData.status);

    /* ── If page loads on a completed ride, go straight to summary ───── */
    if (s === '6') {
        _trackingStopped = true;
        updateTimeline('6');
        _drawCompletionSummary();
        return;
    }

    /* ── Normal live-ride initialisation ──────────────────────────────── */

    /* Pickup marker */
    pickupMarker = L.marker([lat, lng], { icon: makePickupIcon() })
        .bindPopup('<b>Your Pickup Location</b>')
        .addTo(trackingMap);

    /* Hospital marker */
    if (window.trackingData.hospitalLat && window.trackingData.hospitalLng) {
        hospitalMarker = L.marker(
            [window.trackingData.hospitalLat, window.trackingData.hospitalLng],
            { icon: makeHospitalIcon() }
        ).bindPopup('<b>Destination Hospital</b>').addTo(trackingMap);
    }

    /* Driver marker — if we already have a last-known position */
    if (window.trackingData.driverLat && window.trackingData.driverLng) {
        _appendDrivenPath(window.trackingData.driverLat, window.trackingData.driverLng);
        placeDriverMarker(window.trackingData.driverLat, window.trackingData.driverLng);
    }

    /* If already transporting on page load, hide pickup marker */
    if (s === '5' || s === '7') _hidePickupMarker();

    applySmartRoute(window.trackingData, { forceRedraw: true });
    updateTimeline(window.trackingData.status);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * DRIVER MARKER & GREY BREADCRUMB TRAIL  (live ride)
 * ═════════════════════════════════════════════════════════════════════════ */
function _appendDrivenPath(lat, lng) {
    _drivenPath.push([lat, lng]);
    if (_drivenPolyline) {
        _drivenPolyline.setLatLngs(_drivenPath);
    } else if (_drivenPath.length >= 2) {
        _drivenPolyline = L.polyline(_drivenPath, {
            color: '#9ca3af', weight: 4, opacity: 0.65,
        }).addTo(trackingMap);
    }
}

function placeDriverMarker(lat, lng) {
    if (driverMarker) {
        driverMarker.setLatLng([lat, lng]);
    } else {
        driverMarker = L.marker([lat, lng], { icon: makeAmbulanceIcon() })
            .bindPopup('<b>Ambulance Location</b>')
            .addTo(trackingMap);
    }
}

function _hidePickupMarker() {
    if (pickupMarker) { trackingMap.removeLayer(pickupMarker); pickupMarker = null; }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * STOP LIVE TRACKING + DRAW COMPLETION SUMMARY
 * ═════════════════════════════════════════════════════════════════════════ */
function _stopTracking() {
    _trackingStopped = true;

    /* Remove all live-tracking layers */
    if (driverMarker)   { trackingMap.removeLayer(driverMarker);   driverMarker   = null; }
    if (lrmRoute) {
        if (typeof lrmRoute.remove === 'function') trackingMap.removeControl(lrmRoute);
        else trackingMap.removeLayer(lrmRoute);
        lrmRoute = null;
    }
    if (_drivenPolyline) { trackingMap.removeLayer(_drivenPolyline); _drivenPolyline = null; }

    _plannedRouteCoords = [];
    _lastRouteLat = null;
    _lastRouteLng = null;

    /* Draw the static completed-journey summary */
    _drawCompletionSummary();
}

/* ── Static OSRM fetch → grey Leaflet polyline ───────────────────────────── */
function _fetchGreyRoute(fromLat, fromLng, toLat, toLng) {
    const url = `https://router.project-osrm.org/route/v1/driving/` +
                `${fromLng},${fromLat};${toLng},${toLat}` +
                `?overview=full&geometries=geojson`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.routes && data.routes[0] && data.routes[0].geometry) {
                /* GeoJSON coordinates are [lng, lat] — flip for Leaflet */
                const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                L.polyline(coords, {
                    color: '#6b7280', weight: 5, opacity: 0.70,
                }).addTo(trackingMap);
            } else {
                throw new Error('no route');
            }
        })
        .catch(() => {
            /* Fallback: dashed straight line */
            L.polyline([[fromLat, fromLng], [toLat, toLng]], {
                color: '#6b7280', weight: 4, opacity: 0.60, dashArray: '8,6',
            }).addTo(trackingMap);
        });
}

/*
 * Renders the static completed-journey summary:
 *   purple flag  — Driver Started Here (accepted_lat / accepted_lng)
 *   grey route   — Driver Start → Pickup
 *   red pin      — Pickup Location
 *   grey route   — Pickup → Hospital
 *   green cross  — Hospital
 *
 * All coordinates come from window.trackingData (server-side DB values).
 */
function _drawCompletionSummary() {
    const td = window.trackingData;

    const hasStart    = !!(td.acceptedLat && td.acceptedLng);
    const hasPickup   = !!(td.pickupLat   && td.pickupLng);
    const hasHospital = !!(td.hospitalLat && td.hospitalLng);

    const bounds = L.latLngBounds();

    /* ── 1. Driver-start marker ──────────────────────────────────────── */
    if (hasStart) {
        L.marker([td.acceptedLat, td.acceptedLng], { icon: makeDriverStartIcon() })
            .bindPopup(
                `<div style="text-align:center;min-width:140px;">
                    <b style="color:#7c3aed;">Driver Started Here</b><br>
                    <small style="color:#6b7280;">${Number(td.acceptedLat).toFixed(5)},
                    ${Number(td.acceptedLng).toFixed(5)}</small>
                 </div>`
            )
            .addTo(trackingMap);
        bounds.extend([td.acceptedLat, td.acceptedLng]);
    }

    /* ── 2. Pickup marker (re-add if it was removed during Transporting) */
    if (hasPickup) {
        if (!pickupMarker) {
            pickupMarker = L.marker([td.pickupLat, td.pickupLng], { icon: makePickupIcon() })
                .bindPopup(
                    `<div style="text-align:center;min-width:140px;">
                        <b style="color:#D72C42;">Pickup Location</b><br>
                        <small style="color:#6b7280;">${td.pickupLat},&nbsp;${td.pickupLng}</small>
                     </div>`
                )
                .addTo(trackingMap);
        } else {
            pickupMarker.setPopupContent(
                `<div style="text-align:center;min-width:140px;">
                    <b style="color:#D72C42;">Pickup Location</b>
                 </div>`
            );
        }
        bounds.extend([td.pickupLat, td.pickupLng]);
    }

    /* ── 3. Hospital marker ──────────────────────────────────────────── */
    if (hasHospital) {
        if (!hospitalMarker) {
            hospitalMarker = L.marker([td.hospitalLat, td.hospitalLng], { icon: makeHospitalIcon() })
                .bindPopup(
                    `<div style="text-align:center;min-width:140px;">
                        <b style="color:#16a34a;">Hospital Location</b>
                     </div>`
                )
                .addTo(trackingMap);
        }
        bounds.extend([td.hospitalLat, td.hospitalLng]);
    }

    /* ── 4. Grey routes ──────────────────────────────────────────────── */
    if (hasStart && hasPickup) {
        _fetchGreyRoute(td.acceptedLat, td.acceptedLng, td.pickupLat, td.pickupLng);
    }
    if (hasPickup && hasHospital) {
        _fetchGreyRoute(td.pickupLat, td.pickupLng, td.hospitalLat, td.hospitalLng);
    }

    /* ── 5. Fit map to show the entire journey ───────────────────────── */
    if (bounds.isValid()) {
        trackingMap.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
    }

    /* ── 6. Update the "last update" indicator ───────────────────────── */
    const lu = document.getElementById('lastTrackUpdate');
    if (lu) lu.textContent = 'Trip completed';
}

/* ═══════════════════════════════════════════════════════════════════════════
 * LIVE ROUTE DRAWING
 * ═════════════════════════════════════════════════════════════════════════ */
function applySmartRoute(data, opts) {
    if (_trackingStopped) return;

    opts = opts || {};
    const pts = getSmartRoutePoints(data);
    if (!pts) {
        if (lrmRoute) {
            if (typeof lrmRoute.remove === 'function') trackingMap.removeControl(lrmRoute);
            else trackingMap.removeLayer(lrmRoute);
            lrmRoute = null;
        }
        _lastRouteLat = null; _lastRouteLng = null;
        return;
    }

    let shouldRedraw = opts.forceRedraw;
    if (!shouldRedraw) {
        if (_lastRouteLat === null) {
            shouldRedraw = true;
        } else {
            const moved = _haversine(_lastRouteLat, _lastRouteLng, pts.from[0], pts.from[1]);
            if (moved > ROUTE_REDRAW_THRESHOLD_M) {
                shouldRedraw = true;
            } else if (opts.checkDeviation && _isOffRoute(pts.from[0], pts.from[1])) {
                shouldRedraw = true;
                console.log('[Tracking] Off-route — recalculating.');
            }
        }
    }

    if (shouldRedraw) {
        _lastRouteLat = pts.from[0];
        _lastRouteLng = pts.from[1];
        _drawLiveRoute(pts.from[0], pts.from[1], pts.to[0], pts.to[1]);
    }

    if (!opts.noFit) {
        const b = L.latLngBounds([pts.from, pts.to]);
        if (data.driverLat && data.driverLng) b.extend([data.driverLat, data.driverLng]);
        if (data.pickupLat  && data.pickupLng)  b.extend([data.pickupLat,  data.pickupLng]);
        trackingMap.fitBounds(b, { padding: [50, 50] });
    }
}

function _drawLiveRoute(fromLat, fromLng, toLat, toLng) {
    if (typeof L.Routing !== 'undefined') {
        if (lrmRoute) { trackingMap.removeControl(lrmRoute); lrmRoute = null; }
        lrmRoute = L.Routing.control({
            waypoints: [L.latLng(fromLat, fromLng), L.latLng(toLat, toLng)],
            router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
            lineOptions: { styles: [{ color: '#1d4ed8', weight: 5, opacity: 0.80 }] },
            addWaypoints: false, draggableWaypoints: false,
            show: false, createMarker: () => null,
        }).addTo(trackingMap);

        lrmRoute.on('routesfound', function (e) {
            if (e.routes && e.routes[0] && e.routes[0].coordinates) {
                _plannedRouteCoords = e.routes[0].coordinates.map(c => [c.lat, c.lng]);
            }
        });
    } else {
        if (lrmRoute) { trackingMap.removeLayer(lrmRoute); lrmRoute = null; }
        lrmRoute = L.polyline([[fromLat, fromLng], [toLat, toLng]], {
            color: '#1d4ed8', weight: 4, opacity: 0.75, dashArray: '10,8'
        }).addTo(trackingMap);
        _plannedRouteCoords = [[fromLat, fromLng], [toLat, toLng]];
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * TIMELINE
 * ═════════════════════════════════════════════════════════════════════════ */
function updateTimeline(status) {
    const curIdx = STATUS_STEP[String(status)];
    document.querySelectorAll('.rr-tl-step').forEach((el, i) => {
        el.classList.remove('done', 'active');
        if (curIdx === undefined || curIdx < 0) return;
        if (i < curIdx)  el.classList.add('done');
        if (i === curIdx) el.classList.add('active');
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
 * STATUS UPDATE HANDLER  (called by WebSocket events)
 * ═════════════════════════════════════════════════════════════════════════ */
function handleStatusUpdate(r) {
    const s   = String(r.status);
    const h3  = document.querySelector('.rr-tracking-status-big h3');
    const sub = document.getElementById('statusSubtext');

    const displayStatus = s === '8' ? '2' : s;
    if (h3)  h3.textContent  = STATUS_LABELS[displayStatus]  || STATUS_LABELS[s] || s;
    if (sub) sub.textContent = STATUS_TEXTS[displayStatus]   || STATUS_TEXTS[s]  || '';

    updateTimeline(s);

    if (r.driver_lat && r.driver_lng) {
        const dlat = parseFloat(r.driver_lat);
        const dlng = parseFloat(r.driver_lng);
        window.trackingData.driverLat = dlat;
        window.trackingData.driverLng = dlng;
        _appendDrivenPath(dlat, dlng);
        placeDriverMarker(dlat, dlng);
    }

    if (s === '5' && pickupMarker) _hidePickupMarker();

    if (s === '6') {
        window.trackingData.status = s;
        _stopTracking();
        const feedbackCta = document.getElementById('rrFeedbackCta');
        if (feedbackCta) feedbackCta.style.display = '';
        return;
    }

    window.trackingData.status = s;
    _lastRouteLat = null; _lastRouteLng = null;
    applySmartRoute(window.trackingData, { forceRedraw: true });

    const pendingNotice = document.getElementById('rrPendingNotice');
    if (pendingNotice && s !== '1') pendingNotice.style.display = 'none';

    const lu = document.getElementById('lastTrackUpdate');
    if (lu) lu.textContent = new Date().toLocaleTimeString();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GPS PING HANDLER  (called by the live tracking WebSocket)
 * ═════════════════════════════════════════════════════════════════════════ */
function handleDriverGpsPing(lat, lng) {
    if (_trackingStopped) return;
    const td = window.trackingData;
    placeDriverMarker(lat, lng);
    _appendDrivenPath(lat, lng);
    td.driverLat = lat;
    td.driverLng = lng;
    applySmartRoute(td, { noFit: true, checkDeviation: true });
    const lu = document.getElementById('lastTrackUpdate');
    if (lu) lu.textContent = new Date().toLocaleTimeString();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * WINDOW EXPORTS
 * ═════════════════════════════════════════════════════════════════════════ */
window.placeDriverMarker   = placeDriverMarker;
window.applySmartRoute     = applySmartRoute;
window.handleStatusUpdate  = handleStatusUpdate;
window.updateTimeline      = updateTimeline;
window.handleDriverGpsPing = handleDriverGpsPing;

/* ═══════════════════════════════════════════════════════════════════════════
 * BOOT
 * ═════════════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof L === 'undefined') { console.warn('[Tracking] Leaflet not loaded'); return; }
    initTrackingMap();
});
