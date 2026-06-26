/* Admin Live Monitoring — Real-time Driver Map + On-Ride Tracking */
(function () {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────
    var _map     = null;
    var _markers = {};   // Leaflet markers, keyed by driver_id
    var _drivers = {};   // All known drivers: { id, name, status, lat, lng, ... }

    // On-ride tracking (present only while driver is on an active ride)
    var _rideData       = {};  // driver_id → { req_status, request_id, pickup_lat, pickup_lng, hospital_lat, hospital_lng }
    var _rideLayers     = {};  // driver_id → { pickupMarker, hospitalMarker, routePolyline }
    var _rideRouteAbort = {};  // driver_id → AbortController (cancel in-flight route fetch)
    var _rideLastRoutePt = {}; // driver_id → { lat, lng } last point a route was drawn from

    // Redraw route only when driver moves at least this far (metres)
    var ROUTE_REDRAW_M = 50;

    var DEFAULT_CENTER = [30.3753, 69.3451];
    var DEFAULT_ZOOM   = 6;

    // ── HTML escape ─────────────────────────────────────────────────────────
    function _esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Haversine distance (metres) ─────────────────────────────────────────
    function _haversine(lat1, lng1, lat2, lng2) {
        var R    = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a    = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                   Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                   Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // ── Driver map-pin icon ──────────────────────────────────────────────────
    // onRide=false → green (available); onRide=true → blue (en-route) / amber (transporting)
    function _makeDriverIcon(onRide, rideStatus) {
        var color;
        if (onRide) {
            color = String(rideStatus) === '5' ? '#f59e0b' : '#3b82f6';
        } else {
            color = '#22c55e';
        }
        var svg =
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="38" height="44">' +
                '<filter id="ds" x="-20%" y="-10%" width="140%" height="140%">' +
                    '<feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="rgba(0,0,0,0.4)"/>' +
                '</filter>' +
                '<g filter="url(#ds)">' +
                    '<path d="M24 0C13.5 0 5 8.5 5 19c0 14 19 33 19 33s19-19 19-33C43 8.5 34.5 0 24 0z" fill="' + color + '"/>' +
                    '<circle cx="24" cy="19" r="12" fill="rgba(255,255,255,0.2)"/>' +
                    '<rect x="18" y="13" width="12" height="2.5" rx="1.2" fill="#fff"/>' +
                    '<rect x="21.75" y="10" width="4.5" height="9" rx="1.2" fill="#fff"/>' +
                    '<rect x="13" y="21" width="22" height="8" rx="2" fill="#fff" opacity="0.9"/>' +
                    '<circle cx="17" cy="29" r="2.5" fill="' + color + '"/>' +
                    '<circle cx="31" cy="29" r="2.5" fill="' + color + '"/>' +
                '</g>' +
            '</svg>';
        return L.divIcon({
            className:   '',
            html:        '<div style="cursor:pointer;">' + svg + '</div>',
            iconSize:    [38, 44],
            iconAnchor:  [19, 44],
            popupAnchor: [0, -46],
        });
    }

    // ── Pickup / hospital layer icons ────────────────────────────────────────
    function _makePickupIcon() {
        return L.divIcon({
            className: '',
            html: '<div style="background:#D72C42;border:2px solid #fff;border-radius:50%;width:28px;height:28px;' +
                  'box-shadow:0 2px 8px rgba(215,44,66,.5);display:flex;align-items:center;justify-content:center;">' +
                  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="13" height="13">' +
                  '<circle cx="12" cy="7" r="4"/>' +
                  '<path d="M12 14c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/>' +
                  '</svg></div>',
            iconSize:    [28, 28],
            iconAnchor:  [14, 14],
            popupAnchor: [0, -16],
        });
    }

    function _makeHospitalIcon() {
        return L.divIcon({
            className: '',
            html: '<div style="background:#16a34a;border:2px solid #fff;border-radius:50%;width:28px;height:28px;' +
                  'box-shadow:0 2px 8px rgba(22,163,74,.5);display:flex;align-items:center;justify-content:center;">' +
                  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="13" height="13">' +
                  '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/>' +
                  '<path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4z" fill="#16a34a"/>' +
                  '</svg></div>',
            iconSize:    [28, 28],
            iconAnchor:  [14, 14],
            popupAnchor: [0, -16],
        });
    }

    // ── Popup HTML ───────────────────────────────────────────────────────────
    function _popupHtml(d) {
        var onRide  = !!_rideData[d.id];
        var rideStatus = onRide ? String(_rideData[d.id].req_status || '') : '';
        var rideLabels = { '2': 'Dispatched', '3': 'En Route', '4': 'Arrived', '5': 'Transporting', '8': 'Awaiting' };
        var sMap       = { '1': 'Online', '2': 'Offline', '3': 'On Duty', '4': 'Offline', '5': 'Inactive' };

        var statusText = onRide ? (rideLabels[rideStatus] || 'On Ride') : (sMap[String(d.status)] || 'Unknown');
        var color      = onRide ? (rideStatus === '5' ? '#f59e0b' : '#3b82f6') : '#22c55e';
        var photo      = d.photo && d.photo !== 'default.jpg' ? '/assets/driver/img/' + d.photo : null;

        var html = '<div style="min-width:180px;font-family:\'Plus Jakarta Sans\',sans-serif;">' +
            '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">' +
                (photo
                    ? '<img src="' + photo + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.2);">'
                    : '<div style="width:36px;height:36px;border-radius:50%;background:rgba(215,44,66,0.15);display:flex;align-items:center;justify-content:center;"><i class="fa fa-user" style="color:#D72C42;font-size:.85rem;"></i></div>') +
                '<div>' +
                    '<strong style="font-size:.88rem;display:block;">' + _esc(d.name) + '</strong>' +
                    '<span style="font-size:.74rem;color:' + color + ';font-weight:600;">' + statusText + '</span>' +
                '</div>' +
            '</div>' +
            '<div style="font-size:.75rem;color:#64748b;line-height:1.8;">' +
                (d.phone ? '<div><i class="fa fa-phone" style="width:14px;"></i> ' + _esc(d.phone) + '</div>' : '') +
                (d.lat && d.lng ? '<div><i class="fa fa-location-dot" style="width:14px;"></i> ' + (+d.lat).toFixed(5) + ', ' + (+d.lng).toFixed(5) + '</div>' : '') +
                (d.last_update ? '<div><i class="fa fa-clock" style="width:14px;"></i> ' + _esc(d.last_update) + '</div>' : '') +
            '</div>';

        if (onRide && _rideData[d.id].request_id) {
            html += '<div style="margin-top:7px;padding-top:7px;border-top:1px solid rgba(255,255,255,.08);font-size:.72rem;color:#94a3b8;">' +
                '<i class="fa fa-truck-medical" style="margin-right:4px;color:' + color + ';"></i>' +
                'Request #' + _esc(String(_rideData[d.id].request_id)) + '</div>';
        }

        html += '</div>';
        return html;
    }

    // ── Add / refresh a driver marker ────────────────────────────────────────
    function _upsertMarker(d) {
        var lat = parseFloat(d.lat);
        var lng = parseFloat(d.lng);
        var hasCoords = !isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0);

        if (!hasCoords) { _removeMarker(d.id); return; }

        var onRide     = !!_rideData[d.id];
        var rideStatus = onRide ? (_rideData[d.id].req_status) : null;
        var icon       = _makeDriverIcon(onRide, rideStatus);

        if (_markers[d.id]) {
            _markers[d.id].setLatLng([lat, lng]);
            _markers[d.id].setIcon(icon);
            _markers[d.id].setPopupContent(_popupHtml(d));
        } else {
            var m = L.marker([lat, lng], { icon: icon })
                .bindPopup(_popupHtml(d), { maxWidth: 240 })
                .addTo(_map);
            m.on('click', function () { _highlightCard(d.id); });
            _markers[d.id] = m;
        }
    }

    function _removeMarker(id) {
        if (_markers[id]) { _map.removeLayer(_markers[id]); delete _markers[id]; }
    }

    // ── Fit map to all drivers with coords ───────────────────────────────────
    function _fitToDrivers() {
        var pts = [];
        Object.values(_drivers).forEach(function (d) {
            var lat = parseFloat(d.lat), lng = parseFloat(d.lng);
            if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) pts.push([lat, lng]);
        });
        if (pts.length === 0) return;
        if (pts.length === 1) { _map.setView(pts[0], 13); }
        else { _map.fitBounds(pts, { padding: [60, 60], maxZoom: 14 }); }
    }

    // ── Sidebar cards ────────────────────────────────────────────────────────
    function _buildCards() {
        var list = document.getElementById('lmDriverList');
        if (!list) return;
        list.innerHTML = '';

        // Sort: online first, on-ride second, offline last
        var sorted = Object.values(_drivers).sort(function (a, b) {
            var rA = _rideData[a.id] ? 1 : (a.status === '1' ? 0 : 2);
            var rB = _rideData[b.id] ? 1 : (b.status === '1' ? 0 : 2);
            return rA - rB;
        });

        if (sorted.length === 0) {
            list.innerHTML = '<div class="lm-empty"><i class="fa fa-users-slash"></i><p>No drivers found.</p></div>';
            return;
        }
        sorted.forEach(function (d) { list.appendChild(_buildCard(d)); });
    }

    function _buildCard(d) {
        var el = document.createElement('div');
        el.className       = 'lm-driver-card';
        el.id              = 'lm-card-' + d.id;
        el.dataset.driverId = d.id;

        var onRide     = !!_rideData[d.id];
        var rideStatus = onRide ? String(_rideData[d.id].req_status || '') : '';
        var rideLabels = { '2': 'Dispatched', '3': 'En Route', '4': 'Arrived', '5': 'Transporting', '8': 'Awaiting' };
        var sMap       = { '1': 'Online', '2': 'Offline', '3': 'On Duty', '4': 'Offline', '5': 'Inactive' };
        var sDot       = { '1': 'lm-dot--online', '2': 'lm-dot--offline', '4': 'lm-dot--offline' };

        var statusText, dotCls;
        if (onRide) {
            statusText = rideLabels[rideStatus] || 'On Ride';
            dotCls     = rideStatus === '5' ? 'lm-dot--busy' : 'lm-dot--ride';
        } else {
            statusText = sMap[String(d.status)] || 'Unknown';
            dotCls     = sDot[String(d.status)] || 'lm-dot--offline';
        }

        var photo     = d.photo && d.photo !== 'default.jpg'
            ? '<img src="/assets/driver/img/' + _esc(d.photo) + '" class="lm-card-avatar">'
            : '<div class="lm-card-avatar lm-card-avatar--icon"><i class="fa fa-user"></i></div>';

        var hasCoords = d.lat && d.lng && (parseFloat(d.lat) !== 0 || parseFloat(d.lng) !== 0);
        var coordStr  = hasCoords ? (+d.lat).toFixed(4) + ', ' + (+d.lng).toFixed(4) : 'Location unknown';

        el.innerHTML =
            photo +
            '<div class="lm-card-info">' +
                '<div class="lm-card-name">' + _esc(d.name) + '</div>' +
                '<div class="lm-card-meta">' +
                    '<span class="lm-dot ' + dotCls + '"></span>' +
                    '<span class="lm-card-status" id="lm-status-txt-' + d.id + '">' + statusText + '</span>' +
                '</div>' +
                '<div class="lm-card-coords" id="lm-coords-' + d.id + '">' +
                    '<i class="fa fa-location-dot"></i> ' + coordStr +
                '</div>' +
                (d.last_update
                    ? '<div class="lm-card-time" id="lm-time-' + d.id + '"><i class="fa fa-clock"></i> ' + _esc(d.last_update) + '</div>'
                    : '<div class="lm-card-time" id="lm-time-' + d.id + '"></div>') +
            '</div>' +
            (hasCoords
                ? '<button class="lm-card-btn" onclick="lmFlyTo(' + d.id + ')" title="Pan to driver"><i class="fa fa-crosshairs"></i></button>'
                : '<button class="lm-card-btn" style="opacity:.3;cursor:default;" title="No location"><i class="fa fa-location-xmark"></i></button>');

        el.addEventListener('click', function (e) {
            if (e.target.closest('.lm-card-btn')) return;
            lmFlyTo(d.id);
        });
        return el;
    }

    function _updateCard(d) {
        var card = document.getElementById('lm-card-' + d.id);
        if (!card) { _buildCards(); return; }

        var onRide     = !!_rideData[d.id];
        var rideStatus = onRide ? String(_rideData[d.id].req_status || '') : '';
        var rideLabels = { '2': 'Dispatched', '3': 'En Route', '4': 'Arrived', '5': 'Transporting', '8': 'Awaiting' };
        var sMap       = { '1': 'Online', '2': 'Offline', '3': 'On Duty', '4': 'Offline', '5': 'Inactive' };
        var sDot       = { '1': 'lm-dot--online', '2': 'lm-dot--offline', '4': 'lm-dot--offline' };

        var statusText, dotCls;
        if (onRide) {
            statusText = rideLabels[rideStatus] || 'On Ride';
            dotCls     = rideStatus === '5' ? 'lm-dot--busy' : 'lm-dot--ride';
        } else {
            statusText = sMap[String(d.status)] || 'Unknown';
            dotCls     = sDot[String(d.status)] || 'lm-dot--offline';
        }

        var dot = card.querySelector('.lm-dot');
        if (dot) dot.className = 'lm-dot ' + dotCls;

        var stxt = document.getElementById('lm-status-txt-' + d.id);
        if (stxt) stxt.textContent = statusText;

        var hasCoords = d.lat && d.lng && (parseFloat(d.lat) !== 0 || parseFloat(d.lng) !== 0);
        var cEl = document.getElementById('lm-coords-' + d.id);
        if (cEl) cEl.innerHTML = '<i class="fa fa-location-dot"></i> ' +
            (hasCoords ? (+d.lat).toFixed(4) + ', ' + (+d.lng).toFixed(4) : 'Location unknown');

        if (d.last_update) {
            var tEl = document.getElementById('lm-time-' + d.id);
            if (tEl) tEl.innerHTML = '<i class="fa fa-clock"></i> ' + _esc(d.last_update);
        }

        card.classList.add('lm-card-pulse');
        setTimeout(function () { card.classList.remove('lm-card-pulse'); }, 1200);
    }

    // ── Stats counters ───────────────────────────────────────────────────────
    function _updateStats() {
        var online = 0, busy = 0, offline = 0;
        Object.values(_drivers).forEach(function (d) {
            if (_rideData[d.id])                                    busy++;
            else if (d.status === '1')                              online++;
            else if (d.status === '2' || d.status === '4')         offline++;
        });
        var total = Object.keys(_drivers).length;
        var f = function (id, v) { var e = document.getElementById(id); if (e) e.textContent = v; };
        f('lmStatOnline',  online);
        f('lmStatBusy',    busy);
        f('lmStatOffline', offline);
        f('lmStatTotal',   total);
    }

    // ── Highlight a sidebar card ─────────────────────────────────────────────
    function _highlightCard(id) {
        document.querySelectorAll('.lm-driver-card.lm-card-active')
            .forEach(function (el) { el.classList.remove('lm-card-active'); });
        var card = document.getElementById('lm-card-' + id);
        if (card) {
            card.classList.add('lm-card-active');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // ── Init map ─────────────────────────────────────────────────────────────
    function _initMap() {
        _map = L.map('liveMap', { center: DEFAULT_CENTER, zoom: DEFAULT_ZOOM, zoomControl: true });
        window._admMap = _map;
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(_map);
        _fitToDrivers();
    }

    // ── Public: fly to driver ────────────────────────────────────────────────
    window.lmFlyTo = function (id) {
        var d = _drivers[id];
        if (!d) return;
        var lat = parseFloat(d.lat), lng = parseFloat(d.lng);
        if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) return;
        _map.flyTo([lat, lng], 15, { animate: true, duration: 0.8 });
        if (_markers[id]) _markers[id].openPopup();
        _highlightCard(id);
    };

    // ── OSRM route draw ──────────────────────────────────────────────────────
    function _drawRoute(driverId, fromLat, fromLng, toLat, toLng) {
        // Cancel any in-flight route request
        if (_rideRouteAbort[driverId]) {
            try { _rideRouteAbort[driverId].abort(); } catch (e) {}
            delete _rideRouteAbort[driverId];
        }

        var ctl = new AbortController();
        _rideRouteAbort[driverId] = ctl;

        var url = 'https://router.project-osrm.org/route/v1/driving/' +
            fromLng + ',' + fromLat + ';' + toLng + ',' + toLat +
            '?overview=full&geometries=geojson';

        fetch(url, { signal: ctl.signal })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                delete _rideRouteAbort[driverId];
                var layers = _rideLayers[driverId];
                if (!layers || !_map) return;

                if (layers.routePolyline) { _map.removeLayer(layers.routePolyline); layers.routePolyline = null; }

                if (data.routes && data.routes[0] && data.routes[0].geometry) {
                    var coords = data.routes[0].geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
                    layers.routePolyline = L.polyline(coords, { color: '#3b82f6', weight: 4, opacity: 0.82 }).addTo(_map);
                }
            })
            .catch(function () { delete _rideRouteAbort[driverId]; });
    }

    // Determine the current route destination (pickup → hospital when transporting)
    function _routeDest(rd) {
        if (String(rd.req_status) === '5') {
            return { lat: rd.hospital_lat, lng: rd.hospital_lng };
        }
        return { lat: rd.pickup_lat, lng: rd.pickup_lng };
    }

    // Refresh route, throttled by ROUTE_REDRAW_M
    function _refreshRoute(driverId, force) {
        var d  = _drivers[driverId];
        var rd = _rideData[driverId];
        if (!d || !rd || !d.lat || !d.lng) return;

        var dest = _routeDest(rd);
        if (!dest.lat || !dest.lng) return;

        var prev  = _rideLastRoutePt[driverId];
        var moved = prev ? _haversine(parseFloat(d.lat), parseFloat(d.lng), prev.lat, prev.lng) : Infinity;
        if (!force && moved < ROUTE_REDRAW_M) return;

        _rideLastRoutePt[driverId] = { lat: parseFloat(d.lat), lng: parseFloat(d.lng) };
        _drawRoute(driverId, parseFloat(d.lat), parseFloat(d.lng), dest.lat, dest.lng);
    }

    // ── Start ride tracking ──────────────────────────────────────────────────
    function _startRideTracking(driverId, payload) {
        driverId = String(driverId);

        // Ensure the driver exists in our map
        if (!_drivers[driverId]) {
            _drivers[driverId] = {
                id:          driverId,
                name:        payload.driver_name || ('Driver #' + driverId),
                phone:       null,
                photo:       null,
                status:      '3',
                lat:         payload.driver_lat  || null,
                lng:         payload.driver_lng  || null,
                last_update: payload.time        || null,
            };
        }
        _drivers[driverId].status = '3';

        var prevStatus = _rideData[driverId] ? _rideData[driverId].req_status : null;

        // Save ride meta
        _rideData[driverId] = {
            request_id:   payload.request_id   || null,
            req_status:   payload.status        || '3',
            pickup_lat:   payload.pickup_lat    ? parseFloat(payload.pickup_lat)   : null,
            pickup_lng:   payload.pickup_lng    ? parseFloat(payload.pickup_lng)   : null,
            hospital_lat: payload.hospital_lat  ? parseFloat(payload.hospital_lat) : null,
            hospital_lng: payload.hospital_lng  ? parseFloat(payload.hospital_lng) : null,
        };

        if (!_rideLayers[driverId]) _rideLayers[driverId] = {};

        var layers = _rideLayers[driverId];
        var rd     = _rideData[driverId];

        // Pickup marker — only visible before Transporting (status 5)
        if (layers.pickupMarker) { _map.removeLayer(layers.pickupMarker); layers.pickupMarker = null; }
        if (rd.pickup_lat && rd.pickup_lng && String(rd.req_status) !== '5') {
            layers.pickupMarker = L.marker([rd.pickup_lat, rd.pickup_lng], { icon: _makePickupIcon() })
                .bindPopup('<strong style="color:#D72C42;">Pickup Location</strong>', { maxWidth: 160 })
                .addTo(_map);
        }

        // (Re)place hospital marker
        if (layers.hospitalMarker) { _map.removeLayer(layers.hospitalMarker); layers.hospitalMarker = null; }
        if (rd.hospital_lat && rd.hospital_lng) {
            layers.hospitalMarker = L.marker([rd.hospital_lat, rd.hospital_lng], { icon: _makeHospitalIcon() })
                .bindPopup('<strong style="color:#16a34a;">Hospital</strong>', { maxWidth: 160 })
                .addTo(_map);
        }

        _upsertMarker(_drivers[driverId]);
        _updateCard(_drivers[driverId]);
        _updateStats();

        // Draw route — force redraw if this is a fresh start OR status changed destination
        var destChanged = String(prevStatus) !== String(rd.req_status) &&
                          (String(prevStatus) === '5' || String(rd.req_status) === '5');
        if (!prevStatus || destChanged) delete _rideLastRoutePt[driverId];
        _refreshRoute(driverId, !prevStatus || destChanged);
    }

    // ── Update ride status mid-ride (e.g. arrived → transporting) ───────────
    function _updateRideStatus(driverId, newStatus) {
        driverId = String(driverId);
        if (!_rideData[driverId]) return;
        var prev = _rideData[driverId].req_status;
        _rideData[driverId].req_status = newStatus;
        if (_drivers[driverId]) {
            _upsertMarker(_drivers[driverId]);
            _updateCard(_drivers[driverId]);
        }
        // Switching to Transporting → remove pickup marker, switch route to hospital
        if (String(prev) !== '5' && String(newStatus) === '5') {
            var layers = _rideLayers[driverId];
            if (layers && layers.pickupMarker) {
                _map.removeLayer(layers.pickupMarker);
                layers.pickupMarker = null;
            }
            delete _rideLastRoutePt[driverId];
            _refreshRoute(driverId, true);
        }
    }

    // ── Remove all ride layers for a driver ──────────────────────────────────
    function _clearRideLayers(driverId) {
        var layers = _rideLayers[driverId];
        if (!layers) return;
        if (layers.pickupMarker)   { _map.removeLayer(layers.pickupMarker);   layers.pickupMarker   = null; }
        if (layers.hospitalMarker) { _map.removeLayer(layers.hospitalMarker); layers.hospitalMarker = null; }
        if (layers.routePolyline)  { _map.removeLayer(layers.routePolyline);  layers.routePolyline  = null; }
        delete _rideLayers[driverId];
        if (_rideRouteAbort[driverId]) {
            try { _rideRouteAbort[driverId].abort(); } catch (e) {}
            delete _rideRouteAbort[driverId];
        }
    }

    // ── Stop ride tracking — restore driver to idle state ────────────────────
    function _stopRideTracking(driverId, restoredLat, restoredLng, restoredTime) {
        driverId = String(driverId);
        _clearRideLayers(driverId);
        delete _rideData[driverId];
        delete _rideLastRoutePt[driverId];

        if (_drivers[driverId]) {
            _drivers[driverId].status = '1';
            if (restoredLat != null) _drivers[driverId].lat         = restoredLat;
            if (restoredLng != null) _drivers[driverId].lng         = restoredLng;
            if (restoredTime)        _drivers[driverId].last_update = restoredTime;
            _upsertMarker(_drivers[driverId]);
            _updateCard(_drivers[driverId]);
        }
        _updateStats();
    }

    // ── Real-time: driver GPS ping ───────────────────────────────────────────
    window.admDriverLocationUpdated = function (e) {
        var id = String(e.driver_id);

        if (!_drivers[id]) {
            _drivers[id] = {
                id: id, name: e.driver_name || ('Driver #' + id),
                phone: null, photo: null, status: '1', lat: null, lng: null, last_update: null,
            };
        }

        _drivers[id].lat         = e.lat;
        _drivers[id].lng         = e.lng;
        _drivers[id].last_update = e.time;

        // If ping carries active-ride info and we haven't started tracking yet → start it
        var activeStatuses = ['2', '3', '4', '5', '8'];
        if (e.req_status && activeStatuses.indexOf(String(e.req_status)) !== -1) {
            if (!_rideData[id]) {
                // Bootstrap ride tracking from the GPS ping payload
                _startRideTracking(id, {
                    request_id:   e.request_id,
                    driver_name:  e.driver_name,
                    status:       e.req_status,
                    pickup_lat:   e.pickup_lat,
                    pickup_lng:   e.pickup_lng,
                    hospital_lat: e.hospital_lat,
                    hospital_lng: e.hospital_lng,
                });
            } else {
                // Already tracking — just update status if it changed
                if (String(_rideData[id].req_status) !== String(e.req_status)) {
                    _updateRideStatus(id, e.req_status);
                }
            }
        } else if (!e.req_status && _rideData[id]) {
            // No active request in ping → ride likely ended; clean up
            _stopRideTracking(id, e.lat, e.lng, e.time);
        }

        _upsertMarker(_drivers[id]);
        _updateCard(_drivers[id]);

        // Cache last known location for other admin modules
        if (!window._drvLocations) window._drvLocations = {};
        window._drvLocations[id] = { lat: e.lat, lng: e.lng, time: e.time, name: e.driver_name };

        // Throttled route refresh for on-ride drivers
        if (_rideData[id]) _refreshRoute(id, false);

        console.log('[LiveMap] Driver', id, 'at', e.lat, e.lng, _rideData[id] ? '(on ride: ' + _rideData[id].req_status + ')' : '');
    };

    // ── Real-time: driver availability changed ───────────────────────────────
    window.admDriverAvailabilityUpdated = function (e) {
        var id        = String(e.driver_id);
        var newStatus = String(e.status);

        if (_drivers[id]) _drivers[id].status = newStatus;

        if ((newStatus === '1' || newStatus === '2' || newStatus === '4') && _rideData[id]) {
            // Ride ended / driver offline
            var loc = window._drvLocations && window._drvLocations[id];
            _stopRideTracking(id,
                loc ? loc.lat  : null,
                loc ? loc.lng  : null,
                loc ? loc.time : null
            );
        } else if (_drivers[id]) {
            _upsertMarker(_drivers[id]);
            _updateCard(_drivers[id]);
            _updateStats();
        }

        // Sync drivers-page status pill if that page is open in the same tab
        var row = document.querySelector('tr[data-driver-id="' + id + '"]');
        if (row) {
            var pill    = row.querySelector('.dri-rt-status');
            var pillMap = {
                '1': { cls: 'status-1', label: 'Online'  },
                '2': { cls: 'status-4', label: 'Offline' },
                '3': { cls: 'status-2', label: 'On Duty' },
            };
            var s = pillMap[newStatus] || { cls: 'status-4', label: e.status_label || 'Offline' };
            row.setAttribute('data-status', e.status);
            if (pill) { pill.className = 'status-pill dri-rt-status ' + s.cls; pill.textContent = s.label; }
        }
    };

    // ── Real-time: request status changed ────────────────────────────────────
    window.admRequestStatusUpdated = function (e) {
        var id     = String(e.driver_id || '');
        var status = String(e.status    || '');

        if (!id || id === 'null' || id === 'undefined') return;

        if (status === '2' || status === '3' || status === '4' || status === '8') {
            _startRideTracking(id, e);

        } else if (status === '5') {
            if (_rideData[id]) {
                _updateRideStatus(id, '5');
            } else {
                _startRideTracking(id, e);
            }

        } else if (status === '6' || status === '7') {
            var loc = window._drvLocations && window._drvLocations[id];
            _stopRideTracking(
                id,
                e.driver_lat || (loc ? loc.lat  : null),
                e.driver_lng || (loc ? loc.lng  : null),
                e.time       || (loc ? loc.time : null)
            );
        }
    };

    // ── Stale-driver sweep (offline-detection fallback) ──────────────────────
    var _sweepUrl  = null;
    var _sweepCsrf = null;

    function _sweepStale() {
        if (!_sweepUrl || !_sweepCsrf) return;
        fetch(_sweepUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': _sweepCsrf,
            },
            body: JSON.stringify({}),
        }).catch(function () {});
    }

    // ── DOMContentLoaded ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var dataEl = document.getElementById('lmDriverData');
        if (!dataEl) return;

        try {
            var raw = JSON.parse(dataEl.textContent || '[]');
            raw.forEach(function (d) {
                var id  = String(d.id);
                var obj = {
                    id:          id,
                    name:        d.name,
                    phone:       d.phone,
                    photo:       d.photo,
                    status:      String(d.status),
                    lat:         d.lat,
                    lng:         d.lng,
                    last_update: null,
                };
                _drivers[id] = obj;

                // On-duty (status 3) drivers already on a ride at page load
                if (String(d.status) === '3') {
                    _rideData[id] = {
                        request_id:   null,
                        req_status:   d.req_status || '3',
                        pickup_lat:   d.pickup_lat   ? parseFloat(d.pickup_lat)   : null,
                        pickup_lng:   d.pickup_lng   ? parseFloat(d.pickup_lng)   : null,
                        hospital_lat: d.hospital_lat ? parseFloat(d.hospital_lat) : null,
                        hospital_lng: d.hospital_lng ? parseFloat(d.hospital_lng) : null,
                    };
                }
            });
        } catch (err) {
            console.warn('[LiveMap] Failed to parse driver data:', err);
        }

        _initMap();

        // Place driver markers; for on-ride drivers also draw pickup/hospital layers
        Object.values(_drivers).forEach(function (d) {
            _upsertMarker(d);

            if (_rideData[d.id] && d.lat && d.lng) {
                var id     = d.id;
                var rd     = _rideData[id];
                if (!_rideLayers[id]) _rideLayers[id] = {};
                var layers = _rideLayers[id];

                if (rd.pickup_lat && rd.pickup_lng) {
                    layers.pickupMarker = L.marker([rd.pickup_lat, rd.pickup_lng], { icon: _makePickupIcon() })
                        .bindPopup('<strong style="color:#D72C42;">Pickup Location</strong>', { maxWidth: 160 })
                        .addTo(_map);
                }
                if (rd.hospital_lat && rd.hospital_lng) {
                    layers.hospitalMarker = L.marker([rd.hospital_lat, rd.hospital_lng], { icon: _makeHospitalIcon() })
                        .bindPopup('<strong style="color:#16a34a;">Hospital</strong>', { maxWidth: 160 })
                        .addTo(_map);
                }
                // Draw initial route for on-duty driver
                _refreshRoute(id, true);
            }
        });

        _buildCards();
        _updateStats();

        var sweepEl = document.getElementById('lmSweepUrl');
        var csrfEl  = document.querySelector('meta[name="csrf-token"]');
        if (sweepEl && csrfEl) {
            _sweepUrl  = sweepEl.value;
            _sweepCsrf = csrfEl.content;
            setInterval(_sweepStale, 30000);
        }
    });
})();
