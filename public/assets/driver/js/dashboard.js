/* DRIVER DASHBOARD — Active Assignment + Availability + Dispatch Accept/Reject */

var _driCurrentRequest = null;
var _driAvailUpdating  = false;
var _driPendingDispatch = null;

var _driStatusLabels = {
    '1': 'Pending', '2': 'Dispatched', '3': 'En Route',
    '4': 'Arrived', '5': 'Transporting', '6': 'Completed', '7': 'Cancelled',
    '8': 'Awaiting Acceptance',
};
var _driStatusClass = {
    '1': 's1', '2': 's2', '3': 's3', '4': 's4', '5': 's5', '6': 's6', '7': 's7', '8': 's8',
};

// ── HTML escape ───────────────────────────────────────────────────────────────
function driEscD(s) {
    return String(s || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Load active assignment from server ────────────────────────────────────────
function driLoadActive() {
    fetch(window._driRoutes.requestActive, { headers: { 'Accept': 'application/json' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        _driCurrentRequest = data.active || null;
        driRenderActive(_driCurrentRequest);
    })
    .catch(function() {
        driRenderActive(null);
    });
}

// ── Render active assignment panel ────────────────────────────────────────────
function driRenderActive(req) {
    var wrap = document.getElementById('driActiveWrap');
    if (!wrap) return;

    if (!req) {
        wrap.innerHTML = '<div class="dri-no-assignment">' +
            '<i class="fa fa-truck-medical" style="font-size:2rem;display:block;margin-bottom:10px;"></i>' +
            '<p style="font-size:.88rem;margin:0;">No active assignment at the moment.<br>' +
            '<span style="font-size:.76rem;opacity:.7;">You\'ll be notified instantly when a request is assigned.</span></p>' +
        '</div>';
        return;
    }

    var status = req.status;
    var sClass = _driStatusClass[status] || 's1';
    var sLabel = _driStatusLabels[status] || status;
    var typeClass  = req.type === '1' ? 'emergency' : 'non-emergency';
    var typeLabel  = req.type_label || (req.type === '1' ? 'Emergency' : 'Non-Emergency');
    var actionBtns = driActionButtons(req);

    wrap.innerHTML =
        '<div class="dri-assignment-card">' +
            '<div class="dri-assignment-hd">' +
                '<div class="dri-assignment-hd__left">' +
                    '<div class="dri-assignment-hd__icon"><i class="fa fa-truck-medical"></i></div>' +
                    '<div>' +
                        '<div class="dri-assignment-hd__title">' + driEscD(req.rreb_id || '#'+req.id) + '</div>' +
                        '<div class="dri-assignment-hd__sub">Active assignment — ' + driEscD(req.dispatched_at || req.status_label || '') + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;">' +
                    '<span class="dri-type-badge ' + typeClass + '">' + driEscD(typeLabel) + '</span>' +
                    '<span class="dri-status-badge ' + sClass + '" id="driActiveStatusBadge">' + driEscD(sLabel) + '</span>' +
                '</div>' +
            '</div>' +

            '<div class="dri-assignment-body">' +
                '<div class="dri-detail-row">' +
                    '<span>Patient Mobile</span>' +
                    '<strong>' + driEscD(req.mobile_no || '—') + '</strong>' +
                '</div>' +
                '<div class="dri-detail-row">' +
                    '<span>Pickup Address</span>' +
                    '<strong>' + driEscD(req.pickup_address || '—') + '</strong>' +
                '</div>' +
                '<div class="dri-detail-row">' +
                    '<span>Hospital / Destination</span>' +
                    '<strong>' + driEscD(req.hospital_name || '—') + '</strong>' +
                '</div>' +
                '<div class="dri-detail-row">' +
                    '<span>Ambulance</span>' +
                    '<strong>' + driEscD(req.ambulance_no || '—') +
                        (req.ambulance_type ? ' <small style="opacity:.5;font-size:.75rem;">(' + driEscD(req.ambulance_type) + ')</small>' : '') +
                    '</strong>' +
                '</div>' +
                (req.notes ? '<div class="dri-detail-row" style="grid-column:1/-1;">' +
                    '<span>Notes</span>' +
                    '<strong>' + driEscD(req.notes) + '</strong>' +
                '</div>' : '') +
            '</div>' +

            '<div class="dri-assignment-actions" id="driActiveActions">' +
                actionBtns +
            '</div>' +
        '</div>';
}

// ── Build action buttons based on allowed transitions ─────────────────────────
function driActionButtons(req) {
    // Special: Awaiting Acceptance — show Accept/Reject
    if (req.status === '8') {
        return '<button class="dri-action-btn accept" onclick="driAcceptFromDashboard(' + req.id + ')">' +
            '<i class="fa fa-check"></i> Accept Request</button>' +
            '<button class="dri-action-btn cancel" onclick="driRejectFromDashboard(' + req.id + ')" ' +
            'style="margin-left:auto;">' +
            '<i class="fa fa-times"></i> Reject</button>';
    }

    var allowed = req.allowed_next || [];
    if (!allowed.length) {
        if (req.status === '6') {
            return '<span style="color:#4ade80;font-size:.82rem;font-weight:600;">' +
                '<i class="fa fa-circle-check me-2"></i>Request completed.</span>';
        }
        if (req.status === '7') {
            return '<span style="color:#94a3b8;font-size:.82rem;font-weight:600;">' +
                '<i class="fa fa-ban me-2"></i>Request cancelled.</span>';
        }
        return '<span style="color:rgba(255,255,255,.3);font-size:.82rem;">No further actions available.</span>';
    }

    var html = '';

    if (allowed.includes('3')) {
        html += '<button class="dri-action-btn accept" onclick="driUpdateStatus(' + req.id + ',\'3\')">' +
            '<i class="fa fa-check"></i> Accept & En Route</button>';
    }
    if (allowed.includes('4')) {
        html += '<button class="dri-action-btn arrived" onclick="driUpdateStatus(' + req.id + ',\'4\')">' +
            '<i class="fa fa-location-dot"></i> Mark Arrived</button>';
    }
    if (allowed.includes('5')) {
        html += '<button class="dri-action-btn transport" onclick="driUpdateStatus(' + req.id + ',\'5\')">' +
            '<i class="fa fa-truck-medical"></i> Start Transport</button>';
    }
    if (allowed.includes('6')) {
        html += '<button class="dri-action-btn complete" onclick="driUpdateStatus(' + req.id + ',\'6\')">' +
            '<i class="fa fa-flag-checkered"></i> Complete Ride</button>';
    }
    if (allowed.includes('7')) {
        html += '<button class="dri-action-btn cancel" onclick="driUpdateStatus(' + req.id + ',\'7\')" ' +
            'style="margin-left:auto;">' +
            '<i class="fa fa-ban"></i> ' + (req.status === '2' ? 'Reject' : 'Cancel') + '</button>';
    }

    return html;
}

// ── Accept from dashboard card ─────────────────────────────────────────────────
function driAcceptFromDashboard(id) {
    var actions = document.getElementById('driActiveActions');
    if (actions) {
        actions.innerHTML = '<div class="dri-spinner"></div> <span style="color:rgba(255,255,255,.4);font-size:.82rem;margin-left:10px;">Accepting…</span>';
    }
    _driDoAccept(id, function() {
        driLoadActive();
        driRefreshStats();
        if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'active_refresh' });
        // Badge must NOT be decremented on accept — ride is still active.
        // Badge will only decrease when the ride reaches Completed or Cancelled.
    }, function(msg) {
        alert(msg || 'Failed to accept.');
        driRenderActive(_driCurrentRequest);
    });
}

// ── Reject from dashboard card ─────────────────────────────────────────────────
function driRejectFromDashboard(id) {
    if (!confirm('Reject this dispatch request?')) return;
    var actions = document.getElementById('driActiveActions');
    if (actions) {
        actions.innerHTML = '<div class="dri-spinner"></div> <span style="color:rgba(255,255,255,.4);font-size:.82rem;margin-left:10px;">Rejecting…</span>';
    }
    _driDoReject(id, function() {
        _driCurrentRequest = null;
        driRenderActive(null);
        driRefreshStats();
        if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'active_refresh' });
        if (typeof window.driDecrementRequestBadge === 'function') window.driDecrementRequestBadge();
    }, function(msg) {
        alert(msg || 'Failed to reject.');
        driRenderActive(_driCurrentRequest);
    });
}

// ── Core accept fetch ─────────────────────────────────────────────────────────
function _driDoAccept(id, onSuccess, onError) {
    var url = window._driRoutes.requestAccept.replace(':id', id);
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({}),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (data.request) _driCurrentRequest = data.request;
            if (typeof onSuccess === 'function') onSuccess(data);
        } else {
            if (typeof onError === 'function') onError(data.message);
        }
    })
    .catch(function() {
        if (typeof onError === 'function') onError('Server error. Please try again.');
    });
}

// ── Core reject fetch ─────────────────────────────────────────────────────────
function _driDoReject(id, onSuccess, onError) {
    var url = window._driRoutes.requestReject.replace(':id', id);
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({}),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (typeof onSuccess === 'function') onSuccess(data);
        } else {
            if (typeof onError === 'function') onError(data.message);
        }
    })
    .catch(function() {
        if (typeof onError === 'function') onError('Server error. Please try again.');
    });
}

// ── Update request status ─────────────────────────────────────────────────────
function driUpdateStatus(id, newStatus) {
    var confirmMap = {
        '6': 'Mark this ride as Completed?',
        '7': 'Cancel / reject this request?',
    };
    if (confirmMap[newStatus] && !confirm(confirmMap[newStatus])) return;

    var actions = document.getElementById('driActiveActions');
    if (actions) {
        actions.innerHTML = '<div class="dri-spinner"></div> <span style="color:rgba(255,255,255,.4);font-size:.82rem;margin-left:10px;">Updating…</span>';
    }

    var url = window._driRoutes.requestStatus.replace(':id', id);
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            alert(data.message || 'Failed to update status.');
            driRenderActive(_driCurrentRequest);
            return;
        }

        _driCurrentRequest = data.request;
        driRenderActive(_driCurrentRequest);

        driRefreshStats();
        driUpdateHistoryRow(id, data.status, data.status_label);

        if (typeof driBroadcastTabSync === 'function') {
            driBroadcastTabSync({ type: 'active_refresh' });
        }
    })
    .catch(function() {
        alert('Server error. Please try again.');
        driRenderActive(_driCurrentRequest);
    });
}

// ── Update history table row status ──────────────────────────────────────────
function driUpdateHistoryRow(id, status, label) {
    var row = document.querySelector('tr[data-req-id="' + id + '"]');
    if (!row) return;
    var badge = row.querySelector('.dri-status-badge');
    if (badge) {
        badge.className = 'dri-status-badge ' + (_driStatusClass[status] || 's1');
        badge.textContent = label;
    }
}

// ── Refresh stats ─────────────────────────────────────────────────────────────
function driRefreshStats() {
    var url = window._driRoutes.statsUrl || window._driRoutes.requestActive.replace('/active', '/stats');
    fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var f = function(id, val) { var e = document.getElementById(id); if (e && val !== undefined) e.textContent = val; };
        f('statTotal',     data.total);
        f('statCompleted', data.completed);
        f('statActive',    data.active);
        f('statCancelled', data.cancelled);
        f('statPending',   data.pending);
        f('statToday',     data.today);
    })
    .catch(function() {});
}

// ── Driver availability ───────────────────────────────────────────────────────
function driSetAvailability(status) {
    if (_driAvailUpdating) return;
    var currentStatus = window._rrReverb ? window._rrReverb.driverStatus : '1';
    if (String(currentStatus) === String(status)) return;

    _driAvailUpdating = true;
    var btnOnline  = document.getElementById('driAvailOnline');
    var btnOffline = document.getElementById('driAvailOffline');
    if (btnOnline)  btnOnline.disabled  = true;
    if (btnOffline) btnOffline.disabled = true;

    fetch(window._driRoutes.availability, {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ status: status }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            alert(data.message || 'Could not update availability.');
            return;
        }

        var newStatus = data.status;
        if (window._rrReverb) window._rrReverb.driverStatus = newStatus;

        var dot = document.getElementById('driAvailDot');
        if (dot) {
            dot.className = 'dri-avail-status-dot ' + (newStatus === '1' ? 'online' : 'offline');
        }

        if (btnOnline) {
            btnOnline.disabled = false;
            btnOnline.className = 'dri-avail-btn' + (newStatus === '1' ? ' active-online' : '');
        }
        if (btnOffline) {
            btnOffline.disabled = false;
            btnOffline.className = 'dri-avail-btn' + (newStatus === '2' ? ' active-offline' : '');
        }

        var txt = document.getElementById('driAvailText');
        if (txt) {
            if (newStatus === '1') {
                txt.innerHTML = 'You are currently <span style="color:#4ade80;font-weight:600;">Online</span> and available.';
            } else {
                txt.innerHTML = 'You are currently <span style="color:#94a3b8;font-weight:600;">Offline</span>.';
            }
        }
    })
    .catch(function() {
        alert('Server error updating availability.');
        if (btnOnline)  btnOnline.disabled  = false;
        if (btnOffline) btnOffline.disabled = false;
    })
    .finally(function() {
        _driAvailUpdating = false;
    });
}

// ── Real-time: insert a new row into the dashboard Recent Requests table ──────
// Prepends the row and enforces a max of 10 entries (matches the server query).
function driInsertHistoryRow(e) {
    var tbody = document.getElementById('driHistoryBody');
    if (!tbody) return;

    var rid = parseInt(e.request_id, 10);

    // Remove a previously inserted row for the same request (re-dispatch)
    var existing = tbody.querySelector('tr[data-req-id="' + rid + '"]');
    if (existing) existing.remove();

    // Remove the "No requests yet" empty placeholder
    var emptyRow = tbody.querySelector('.dri-empty-row');
    if (emptyRow) emptyRow.remove();

    var typeCls   = e.type === '1' ? 'emergency' : 'non-emergency';
    var typeLabel = e.type === '1' ? 'Emergency' : 'Non-Emergency';
    var addr      = (e.pickup_address || '').substring(0, 30) + ((e.pickup_address || '').length > 30 ? '…' : '');
    var hosp      = (e.hospital_name  || '').substring(0, 22) + ((e.hospital_name  || '').length > 22 ? '…' : '');

    var tr = document.createElement('tr');
    tr.setAttribute('data-req-id', rid);
    tr.style.cssText = 'animation:driNewRowFadeIn .45s ease both;';
    tr.innerHTML =
        '<td><span class="mono">' + driEscD(e.rreb_id || '#' + rid) + '</span></td>' +
        '<td><span class="dri-type-badge ' + typeCls + '">' + typeLabel + '</span></td>' +
        '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + driEscD(addr) + '</td>' +
        '<td>' + driEscD(hosp) + '</td>' +
        '<td>' + driEscD(e.ambulance_no || '—') + '</td>' +
        '<td><span class="dri-status-badge s8">Awaiting Acceptance</span></td>' +
        '<td style="white-space:nowrap;color:rgba(255,255,255,.35);font-size:.77rem;">' +
            driEscD(e.date_short || e.time || '') + '</td>';

    tbody.insertBefore(tr, tbody.firstChild);

    // Trim to max 10 rows
    var rows = tbody.querySelectorAll('tr:not(.dri-empty-row)');
    for (var i = 10; i < rows.length; i++) {
        rows[i].remove();
    }
}

// ── Real-time: insert OR re-activate a dispatch request in the grid ───────────
// Called for every dispatch.request.sent event, including re-dispatches of the
// same request after a driver rejection.
window.driInsertDispatchRequestRow = function(e) {
    if (!e || !e.request_id) return;

    var rid      = parseInt(e.request_id, 10);
    var typeLabel = e.type === '1' ? 'Emergency' : 'Non-Emergency';
    var typeCls   = e.type === '1' ? 'emergency' : 'non-emergency';
    var addr      = (e.pickup_address || '').substring(0, 32) + ((e.pickup_address || '').length > 32 ? '…' : '');
    var hosp      = (e.hospital_name  || '').substring(0, 22) + ((e.hospital_name  || '').length > 22 ? '…' : '');

    // ── 1. Requests page table ────────────────────────────────────────────────
    var tbody = document.getElementById('driReqTableBody');
    if (tbody) {
        var existing = document.getElementById('reqRow_' + rid);

        if (existing) {
            // Row is already in the DOM (left over from first dispatch).
            // Reset its status badge back to "Awaiting Acceptance" and
            // float it to the top so the driver notices the re-dispatch.
            var badge = document.getElementById('reqStatusBadge_' + rid);
            if (badge) {
                badge.className = 'status-pill s8';
                badge.textContent = 'Awaiting Acceptance';
            }
            existing.style.animation = 'none';
            // Force reflow then re-trigger fade-in
            void existing.offsetWidth;
            existing.style.animation = 'driNewRowFadeIn .45s ease both';
            // Move to top
            tbody.insertBefore(existing, tbody.firstChild);
        } else {
            // First time — build and insert a fresh row
            var emptyRow = tbody.querySelector('.req-empty');
            if (emptyRow) emptyRow.remove();

            var tr = document.createElement('tr');
            tr.id = 'reqRow_' + rid;
            tr.style.cssText = 'animation:driNewRowFadeIn .45s ease both;';
            tr.innerHTML =
                '<td><span class="mono">' + driEscD(e.rreb_id || '#' + rid) + '</span></td>' +
                '<td><span class="status-pill ' + typeCls + '">' + typeLabel + '</span></td>' +
                '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + driEscD(addr) + '</td>' +
                '<td>' + driEscD(hosp) + '</td>' +
                '<td style="white-space:nowrap;">' + driEscD(e.mobile_no || '—') + '</td>' +
                '<td>' + driEscD(e.ambulance_no || '—') + '</td>' +
                '<td><span class="status-pill s8" id="reqStatusBadge_' + rid + '">Awaiting Acceptance</span></td>' +
                '<td style="white-space:nowrap;color:rgba(255,255,255,.38);font-size:.77rem;">' + driEscD(e.date_short || e.time || '') + '</td>' +
                '<td><button class="btn-dri-icon btn-dri-icon--primary" title="View Details" ' +
                    'onclick="viewRequestDetail(' + rid + ')">' +
                    '<i class="fa fa-eye"></i></button></td>';

            tbody.insertBefore(tr, tbody.firstChild);
        }
    }

    // ── 2. Sync _reqData so viewRequestDetail() always has fresh state ────────
    if (Array.isArray(window._reqData)) {
        var freshEntry = {
            id:             rid,
            rreb_id:        e.rreb_id || '',
            type:           e.type || '1',
            status:         '8',
            mobile_no:      e.mobile_no || '',
            email:          '',
            pickup_address: e.pickup_address || '',
            pickup_lat:     e.pickup_lat   != null ? parseFloat(e.pickup_lat)   : null,
            pickup_lng:     e.pickup_lng   != null ? parseFloat(e.pickup_lng)   : null,
            hospital_name:  e.hospital_name  || '',
            hospital_lat:   e.hospital_lat != null ? parseFloat(e.hospital_lat) : null,
            hospital_lng:   e.hospital_lng != null ? parseFloat(e.hospital_lng) : null,
            accepted_lat:   null, accepted_lng: null,
            driver_lat:     null, driver_lng:   null,
            notes:          e.notes || '',
            ambulance_no:   e.ambulance_no   || '',
            ambulance_type: e.ambulance_type || '',
            dispatched_at:  e.time || '',
            completed_at:   null,
            created_at:     e.time || '',
            allowed_next:   [],
        };

        var existingIdx = window._reqData.findIndex(function(r) { return r.id === rid; });
        if (existingIdx !== -1) {
            // Update in place — re-dispatch resets status + refreshes all fields
            window._reqData[existingIdx] = freshEntry;
        } else {
            window._reqData.unshift(freshEntry);
        }
    }

    // ── 3. Dashboard active panel refresh ────────────────────────────────────
    if (typeof driLoadActive === 'function') driLoadActive();

    // ── 4. Dashboard "Recent Requests" history table (max 10) ────────────────
    driInsertHistoryRow(e);

    console.log('[Dispatch] Request inserted/refreshed in grid:', e.rreb_id || rid);
};

// ── Accept from overlay ───────────────────────────────────────────────────────
function driAcceptDispatch() {
    if (!_driPendingDispatch) return;
    var id = _driPendingDispatch.request_id;

    var acceptBtn = document.getElementById('driAcceptBtn');
    var rejectBtn = document.getElementById('driRejectBtn');
    if (acceptBtn) { acceptBtn.disabled = true; acceptBtn.innerHTML = '<span class="dri-dispatch-spinner"></span> Accepting…'; }
    if (rejectBtn) { rejectBtn.disabled = true; }

    _driDoAccept(id, function(data) {
        var overlay = document.getElementById('driDispatchOverlay');
        if (overlay) overlay.classList.remove('active');
        _driPendingDispatch = null;
        driLoadActive();
        driRefreshStats();
        if (typeof driToastSuccess === 'function') driToastSuccess('Request accepted. You are now dispatched.');
        if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'active_refresh' });
    }, function(msg) {
        if (acceptBtn) { acceptBtn.disabled = false; acceptBtn.innerHTML = '<i class="fa fa-check"></i> Accept'; }
        if (rejectBtn) { rejectBtn.disabled = false; }
        alert(msg || 'Failed to accept. Please try again.');
    });
}

// ── Reject from overlay ───────────────────────────────────────────────────────
function driRejectDispatch() {
    if (!_driPendingDispatch) return;
    if (!confirm('Reject this dispatch request?')) return;
    var id = _driPendingDispatch.request_id;

    var acceptBtn = document.getElementById('driAcceptBtn');
    var rejectBtn = document.getElementById('driRejectBtn');
    if (rejectBtn) { rejectBtn.disabled = true; rejectBtn.innerHTML = '<span class="dri-dispatch-spinner"></span> Rejecting…'; }
    if (acceptBtn) { acceptBtn.disabled = true; }

    _driDoReject(id, function() {
        var overlay = document.getElementById('driDispatchOverlay');
        if (overlay) overlay.classList.remove('active');
        _driPendingDispatch = null;
        _driCurrentRequest = null;
        driRenderActive(null);
        driRefreshStats();
        if (typeof driToastSuccess === 'function') driToastSuccess('Request rejected.');
        if (typeof driBroadcastTabSync === 'function') driBroadcastTabSync({ type: 'active_refresh' });
    }, function(msg) {
        if (rejectBtn) { rejectBtn.disabled = false; rejectBtn.innerHTML = '<i class="fa fa-times"></i> Reject'; }
        if (acceptBtn) { acceptBtn.disabled = false; }
        alert(msg || 'Failed to reject. Please try again.');
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('driActiveWrap')) {
        driLoadActive();
    }
});

// Export for notification.js
window.driLoadActive = driLoadActive;

// ── Real-time: prepend a newly dispatched request to the dashboard history table
window.driPrependRequestRow = window.driPrependRequestRow || function (e) {
    if (!e || !e.request_id) return;

    var tbody = document.getElementById('driHistoryBody');
    if (!tbody) return;

    var existing = tbody.querySelector('tr[data-req-id="' + e.request_id + '"]');
    if (existing) {
        var badge = existing.querySelector('.dri-status-badge');
        if (badge) { badge.className = 'dri-status-badge s2'; badge.textContent = 'Dispatched'; }
        existing.style.animation = 'driNewRowFadeIn .45s ease both';
        return;
    }

    var emptyRow = tbody.querySelector('.dri-empty-row');
    if (emptyRow) emptyRow.remove();

    var typeLabel = e.type === '1' ? 'Emergency' : 'Non-Emergency';
    var typeCls   = e.type === '1' ? 'emergency' : 'non-emergency';

    var tr = document.createElement('tr');
    tr.setAttribute('data-req-id', e.request_id);
    tr.style.cssText = 'animation:driNewRowFadeIn .45s ease both;';
    tr.innerHTML =
        '<td><span class="mono">' + driEscD(e.rreb_id || '#' + e.request_id) + '</span></td>' +
        '<td><span class="dri-type-badge ' + typeCls + '">' + typeLabel + '</span></td>' +
        '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
            driEscD(e.pickup_address ? (e.pickup_address.length > 30 ? e.pickup_address.substring(0, 30) + '\u2026' : e.pickup_address) : '\u2014') +
        '</td>' +
        '<td>' + driEscD(e.hospital_name ? (e.hospital_name.length > 22 ? e.hospital_name.substring(0, 22) + '\u2026' : e.hospital_name) : '\u2014') + '</td>' +
        '<td>' + driEscD(e.ambulance_no || '\u2014') + '</td>' +
        '<td><span class="dri-status-badge s2">Dispatched</span></td>' +
        '<td style="white-space:nowrap;color:rgba(255,255,255,.35);font-size:.77rem;">' + driEscD(e.date_short || e.time || '') + '</td>';

    tbody.insertBefore(tr, tbody.firstChild);

    if (!document.getElementById('driDashRowStyle')) {
        var st = document.createElement('style');
        st.id  = 'driDashRowStyle';
        st.textContent = '@keyframes driNewRowFadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}';
        document.head.appendChild(st);
    }

    driRefreshStats();
};
