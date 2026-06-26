/* Driver Management CRUD + Real-Time Updates */

/* ── Status label maps ─────────────────────────────────────────────────────── */
var _drvStatusCfg = {
    '1': { label: 'Active',    cls: 'status-1' },
    '2': { label: 'Online',   cls: 'status-1' },
    '3': { label: 'On Duty',  cls: 'status-2' },
    '4': { label: 'Offline',  cls: 'status-4' },
    '5': { label: 'Inactive', cls: 'status-4' },
};

var _driverEditId        = null;
var _driverUsernameTimer = null;
var _driverUsernameValid = null;

function previewDriverPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        var img  = document.getElementById('drvPhotoImg');
        var prev = document.getElementById('drvPhotoPrev');
        var clr  = document.getElementById('drvPhotoClear');
        img.src           = e.target.result;
        img.style.display = 'block';
        prev.style.display = 'none';
        if (clr) clr.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

function clearDriverPhoto() {
    var img  = document.getElementById('drvPhotoImg');
    var prev = document.getElementById('drvPhotoPrev');
    var clr  = document.getElementById('drvPhotoClear');
    var file = document.getElementById('drv_photo');
    img.style.display  = 'none';
    prev.style.display = 'flex';
    if (clr) clr.style.display = 'none';
    if (file) file.value = '';
}

function _setDriverPhoto(url) {
    var img  = document.getElementById('drvPhotoImg');
    var prev = document.getElementById('drvPhotoPrev');
    var clr  = document.getElementById('drvPhotoClear');

    if (url) {
        const baseUrl = window.location.origin; 
        img.src = `${baseUrl}/assets/driver/img/${url}`;

        img.style.display = 'block';
        if (prev) prev.style.display = 'none';
        if (clr) clr.style.display = 'block';
    } else {
        img.src = '';
        img.style.display = 'none';

        if (prev) prev.style.display = 'flex';
        if (clr) clr.style.display = 'none';
    }
}

function _auditFmt(s) {
    if (!s) return '—';

    const d = new Date(s);
    if (isNaN(d.getTime())) return s;

    const date = d.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });

    const time = d.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });

    return `${date} at ${time}`;
}

function _auditShow(data) {
    var panel = document.getElementById('auditTrail');
    if (!panel) return;
    panel.style.display = 'block';
    document.getElementById('auditAddedBy').textContent = data.added_by || '—';
    document.getElementById('auditAddedAt').textContent = _auditFmt(data.created_at);
    var hasUpd = !!(data.updated_by);
    document.querySelectorAll('#auditTrail .audit-upd').forEach(function (el) {
        el.style.display = hasUpd ? '' : 'none';
    });
    if (hasUpd) {
        document.getElementById('auditUpdatedBy').textContent = data.updated_by;
        document.getElementById('auditUpdatedAt').textContent = _auditFmt(data.updated_at);
    }
}

function checkDriverUsername() {
    var input    = document.getElementById('drv_username');
    var feedback = document.getElementById('driverUsernameFeedback');
    if (!input || !feedback) return;

    var val       = input.value.trim();
    var excludeId = _driverEditId;

    if (!val) {
        feedback.innerHTML = '';
        _driverUsernameValid = null;
        return;
    }

    if (!/^[a-z0-9_.]+$/.test(val)) {
        feedback.innerHTML = '<span style="color:#f87171;font-size:0.74rem;"><i class="fa fa-circle-xmark me-1"></i>Only lowercase letters (a-z), numbers, underscore (_) and dot (.) allowed.</span>';
        _driverUsernameValid = false;
        return;
    }

    feedback.innerHTML = '<span style="color:rgba(255,255,255,0.35);font-size:0.74rem;"><i class="fa fa-spinner fa-spin me-1"></i>Checking…</span>';

    clearTimeout(_driverUsernameTimer);
    _driverUsernameTimer = setTimeout(function () {
        var url = window.adminRoutes.checkDriverUsername + '?username=' + encodeURIComponent(val);
        if (excludeId) url += '&exclude_id=' + encodeURIComponent(excludeId);
        
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _driverUsernameValid = data.available;
                if (data.available) {
                    feedback.innerHTML = '<span style="color:#86efac;font-size:0.74rem;"><i class="fa fa-circle-check me-1"></i>Username is available.</span>';
                } else {
                    feedback.innerHTML = '<span style="color:#f87171;font-size:0.74rem;"><i class="fa fa-circle-xmark me-1"></i>' + (data.message || 'Username is already taken.') + '</span>';
                }
            })
            .catch(function () {
                feedback.innerHTML = '';
                _driverUsernameValid = null;
            });
    }, 400);
}

function _resetDriverUsernameFeedback() {
    var feedback = document.getElementById('driverUsernameFeedback');
    if (feedback) feedback.innerHTML = '';
    _driverUsernameValid = null;
    clearTimeout(_driverUsernameTimer);
}

function openAddDriverModal() {
    _driverEditId = null;
    document.getElementById('driverModalTitle').innerHTML = '<span class="modal-title-icon"><i class="fa fa-id-card"></i></span> Add Driver';
    document.getElementById('driverForm').reset();
    clearDriverPhoto();
    document.getElementById('drvPasswordNote').style.display = 'none';
    document.getElementById('drvPwRequired').style.display   = 'inline';
    document.getElementById('drv_photo').required            = true;
    _resetDriverUsernameFeedback();
    _driverUsernameValid = null;

    var btn = document.getElementById('driverSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Driver'; }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('driverModal')).show();
}

function openEditDriverModal(id, data) {
    _driverEditId = id;
    document.getElementById('driverModalTitle').innerHTML =
        '<span class="modal-title-icon"><i class="fa fa-id-card"></i></span> Edit Driver';

    document.getElementById('driverForm').reset();
    document.getElementById('drv_name').value     = data.name       || '';
    document.getElementById('drv_username').value = data.username   || '';
    document.getElementById('drv_email').value    = data.email      || '';
    document.getElementById('drv_phone').value    = data.phone      || '';
    document.getElementById('drv_license').value  = data.license_no || '';
    document.getElementById('drv_status').value   = data.status     || '0';
    document.getElementById('drv_password').value = '';
    document.getElementById('drv_photo').required = false;

    _setDriverPhoto(data.photo);
    _auditShow(data);
    
    document.getElementById('drvPasswordNote').style.display = 'block';
    document.getElementById('drvPwRequired').style.display   = 'none';

    _resetDriverUsernameFeedback();
    _driverUsernameValid = true;

    var btn = document.getElementById('driverSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Driver'; }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('driverModal')).show();
}

function filterDriverTable() {
    var search = (document.getElementById('searchDrv').value || '').toLowerCase();
    var status = document.getElementById('filterDrvStatus').value;
    var noRes  = document.getElementById('driverNoResults');

    if (!search && !status) {
        if (noRes) noRes.style.display = 'none';
        if (window.PGD) PGD.applyFilter('drv', null);
        return;
    }

    var matched = [];
    document.querySelectorAll('#driverTable tbody tr.pgd-row').forEach(function (row) {
        var ms = !search ||
            (row.dataset.name  || '').includes(search) ||
            (row.dataset.email || '').includes(search) ||
            (row.dataset.phone || '').includes(search);
        var mt = !status || String(row.dataset.status) === String(status);
        if (ms && mt) matched.push(row);
    });

    if (noRes) noRes.style.display = matched.length === 0 ? 'table-row' : 'none';
    if (window.PGD) PGD.applyFilter('drv', matched);
}

function deleteDriver(id, name) {
    confirmAction('Delete driver "' + name + '"? This cannot be undone.', function () {
        fetch(window.adminRoutes.driverDelete + '/' + id, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', 'Driver removed successfully.');
                // Real-time event (content.updated) will remove the row for all tabs
            } else {
                showAlert('error', data.message || 'Delete failed.');
            }
        })
        .catch(function () { showAlert('error', 'Server error. Please try again.'); });
    });
}

function saveDriver() {
    var isEdit = !!_driverEditId;
    var url    = isEdit
        ? window.adminRoutes.driverUpdate + '/' + _driverEditId
        : window.adminRoutes.driverStore;

    var usernameVal = (document.getElementById('drv_username').value || '').trim();
    if (!usernameVal) {
        showAlert('error', 'Username is required.');
        return;
    }
    if (!/^[a-z0-9_.]+$/.test(usernameVal)) {
        showAlert('error', 'Username may only contain lowercase letters (a-z), numbers, underscore (_) and dot (.).');
        return;
    }
    if (_driverUsernameValid === false) {
        showAlert('error', 'That username is already taken — please choose a different one.');
        return;
    }

    var phone = (document.getElementById('drv_phone').value || '').trim();
    if (!/^03[0-9]{9}$/.test(phone)) {
        showAlert('error', 'Enter a valid Pakistani phone number (03XXXXXXXXX).');
        return;
    }

    var pw = document.getElementById('drv_password').value;
    if (!isEdit && !pw) {
        showAlert('error', 'Password is required when adding a new driver.');
        return;
    }
    if (pw && pw.length < 6) {
        showAlert('error', 'Password must be at least 6 characters.');
        return;
    }

    var photoFile = document.getElementById('drv_photo');
    if (!isEdit && (!photoFile.files || !photoFile.files[0])) {
        showAlert('error', 'Driver photo is required.');
        return;
    }

    var fields = [
        { id: 'drv_name',    message: 'Full name is required.' },
        { id: 'drv_email',   message: 'Email address is required.', validate: 'email' },
        { id: 'drv_license', message: 'License number is required.' },
        { id: 'drv_status',  message: 'Please select a status.', skipIf: '0' },
    ];

    validateForm({
        formId: 'driverForm',
        fields: fields,
        btn:    'driverSubmitBtn',
        onSuccess: function () {
            submitFormData({
                formId:         'driverForm',
                url:            url,
                successMessage: isEdit ? 'Driver updated successfully.' : 'Driver added successfully.',
                onSuccess: function (resData) {
                    bootstrap.Modal.getInstance(document.getElementById('driverModal'))?.hide();
                    var action = isEdit ? 'updated' : 'added';
                    var driverData = (resData && resData.driver) ? resData.driver : null;
                    if (driverData && typeof window.admDriverContentUpdated === 'function') {
                        window.admDriverContentUpdated({ module: 'driver', action: action, data: driverData });
                    }
                },
                onError: function () {
                    var btn = document.getElementById('driverSubmitBtn');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Driver'; }
                },
            });
        },
    });
}

/* ── HTML escape helper ───────────────────────────────────────────────────── */
function _drvEscHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Build a table row HTML for a new/updated driver ─────────────────────── */
function _buildDriverRow(d) {
    var stCfg    = _drvStatusCfg[String(d.status)] || { label: d.status, cls: 'status-4' };
    var photoHtml = (d.photo && d.photo !== 'default.jpg')
        ? '<img src="' + window.location.origin + '/assets/driver/img/' + _drvEscHtml(d.photo) + '" alt="' + _drvEscHtml(d.name) + '" style="width:100%;height:100%;object-fit:cover;">'
        : '<i class="fa fa-user"></i>';

    return '<tr class="pgd-row rt-flash-row"'
        + ' data-driver-id="' + d.id + '"'
        + ' data-status="' + d.status + '"'
        + ' data-name="' + (d.name || '').toLowerCase() + '"'
        + ' data-email="' + (d.email || '').toLowerCase() + '"'
        + ' data-phone="' + (d.phone || '') + '">'
        + '<td class="ps-4 fs-xs" style="color:var(--adm-muted);">—</td>'
        + '<td><div class="d-flex align-items-center gap-2">'
        + '<div class="adm-icon-preview" style="width:36px;height:36px;border-radius:50%;overflow:hidden;font-size:0.85rem;flex-shrink:0;">'
        + photoHtml + '</div>'
        + '<div><strong>' + _drvEscHtml(d.name) + '</strong>'
        + '<div class="fs-xs" style="color:var(--adm-muted);">' + _drvEscHtml(d.email) + '</div></div>'
        + '</div></td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _drvEscHtml(d.username) + '</td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _drvEscHtml(d.phone) + '</td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _drvEscHtml(d.license_no) + '</td>'
        + '<td><span class="status-pill dri-rt-status ' + stCfg.cls + '">' + stCfg.label + '</span></td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">0 <span style="color:rgba(255,255,255,0.25);">/</span> 0</td>'
        + '<td><div class="d-flex gap-1">'
        + '<button class="btn-adm-icon btn-adm-icon--edit" title="Edit" onclick="openEditDriverModal(' + d.id + ',' + JSON.stringify(d).replace(/"/g, '&quot;') + ')"><i class="fa fa-pen"></i></button>'
        + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteDriver(' + d.id + ',' + JSON.stringify(d.name).replace(/"/g, '&quot;') + ')"><i class="fa fa-trash"></i></button>'
        + '</div></td></tr>';
}

function _flashDrvRow(row) {
    row.style.transition = 'background 0.3s ease';
    row.style.background = 'rgba(99,102,241,0.12)';
    setTimeout(function () { row.style.background = ''; }, 1400);
}

/* ── Real-Time: driver content.updated ────────────────────────────────────── */
window.admDriverContentUpdated = function (event) {
    if (event.module !== 'driver') return;

    var tbody = document.querySelector('#driverTable tbody');
    if (!tbody) return;  // Not on the drivers page

    var data   = event.data;
    var action = event.action;
    var did    = String(data.id || '');

    if (action === 'deleted') {
        var row = tbody.querySelector('tr[data-driver-id="' + did + '"]');
        if (row) {
            row.style.transition = 'opacity 0.4s';
            row.style.opacity    = '0';
            setTimeout(function () { row.remove(); if (window.PGD) PGD.rebuild('drv'); }, 400);
        }
        if (typeof showAlert === 'function') showAlert('success', 'Driver removed successfully.');
        return;
    }

    if (action === 'added') {
        // Skip if row already exists (inserted directly from the AJAX response)
        if (tbody.querySelector('tr[data-driver-id="' + did + '"]')) return;

        var noResults = document.getElementById('driverNoResults');
        if (noResults) noResults.style.display = 'none';

        tbody.insertAdjacentHTML('afterbegin', _buildDriverRow(data));
        var inserted = tbody.querySelector('tr[data-driver-id="' + did + '"]');
        if (inserted) _flashDrvRow(inserted);
        if (window.PGD) PGD.rebuild('drv');
        if (typeof showAlert === 'function') showAlert('success', 'New driver added: ' + (data.name || ''));
        return;
    }

    if (action === 'updated') {
        var existingRow = tbody.querySelector('tr[data-driver-id="' + did + '"]');
        if (existingRow) {
            var stCfg    = _drvStatusCfg[String(data.status)] || { label: data.status, cls: 'status-4' };
            var photoHtml = (data.photo && data.photo !== 'default.jpg')
                ? '<img src="' + window.location.origin + '/assets/driver/img/' + _drvEscHtml(data.photo) + '" alt="' + _drvEscHtml(data.name) + '" style="width:100%;height:100%;object-fit:cover;">'
                : '<i class="fa fa-user"></i>';

            // Update data attributes
            existingRow.setAttribute('data-status',  data.status);
            existingRow.setAttribute('data-name',    (data.name  || '').toLowerCase());
            existingRow.setAttribute('data-email',   (data.email || '').toLowerCase());
            existingRow.setAttribute('data-phone',   data.phone  || '');

            var cells = existingRow.querySelectorAll('td');
            // cell[1] = photo + name + email
            if (cells[1]) {
                cells[1].innerHTML = '<div class="d-flex align-items-center gap-2">'
                    + '<div class="adm-icon-preview" style="width:36px;height:36px;border-radius:50%;overflow:hidden;font-size:0.85rem;flex-shrink:0;">'
                    + photoHtml + '</div>'
                    + '<div><strong>' + _drvEscHtml(data.name) + '</strong>'
                    + '<div class="fs-xs" style="color:var(--adm-muted);">' + _drvEscHtml(data.email) + '</div></div></div>';
            }
            // cell[2] = username
            if (cells[2]) cells[2].textContent = data.username || '';
            // cell[3] = phone
            if (cells[3]) cells[3].textContent = data.phone || '';
            // cell[4] = license_no
            if (cells[4]) cells[4].textContent = data.license_no || '';
            // cell[5] = status pill
            if (cells[5]) cells[5].innerHTML = '<span class="status-pill dri-rt-status ' + stCfg.cls + '">' + stCfg.label + '</span>';
            // cell[7] = actions (rebuild with fresh data — JSON must be &quot;-escaped for HTML attributes)
            if (cells[7]) {
                cells[7].innerHTML = '<div class="d-flex gap-1">'
                    + '<button class="btn-adm-icon btn-adm-icon--edit" title="Edit" onclick="openEditDriverModal(' + data.id + ',' + JSON.stringify(data).replace(/"/g, '&quot;') + ')"><i class="fa fa-pen"></i></button>'
                    + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteDriver(' + data.id + ',' + JSON.stringify(data.name).replace(/"/g, '&quot;') + ')"><i class="fa fa-trash"></i></button>'
                    + '</div>';
            }

            _flashDrvRow(existingRow);
        } else {
            // Not yet visible — insert
            tbody.insertAdjacentHTML('afterbegin', _buildDriverRow(data));
            var ins = tbody.querySelector('tr[data-driver-id="' + did + '"]');
            if (ins) _flashDrvRow(ins);
            if (window.PGD) PGD.rebuild('drv');
        }
        if (typeof showAlert === 'function') showAlert('success', 'Driver updated: ' + (data.name || ''));
        return;
    }
};

/* ── Real-Time: Driver Availability Updated (login/logout/manual toggle) ────── */
function admDriverAvailabilityUpdated(e) {
    var driverId = e.driver_id;
    if (!driverId) return;

    var pillMap = {
        '1': { cls: 'status-1', label: 'Online'   },
        '2': { cls: 'status-4', label: 'Offline'  },
        '3': { cls: 'status-2', label: 'On Duty'  },
    };

    var row = document.querySelector('tr[data-driver-id="' + driverId + '"]');
    if (!row) return;

    var s = pillMap[String(e.status)] || { cls: 'status-4', label: e.status_label || 'Offline' };

    row.setAttribute('data-status', e.status);

    var pill = row.querySelector('.dri-rt-status');
    if (pill) {
        pill.className = 'status-pill dri-rt-status ' + s.cls;
        pill.textContent = s.label;
    }

    row.style.transition = 'background 0.3s ease';
    row.style.background = 'rgba(99,102,241,0.10)';
    setTimeout(function () { row.style.background = ''; }, 1200);

    if (typeof showAlert === 'function') {
        var icon = e.status === '1' ? '🟢' : (e.status === '3' ? '🟡' : '⚫');
        showAlert('info', icon + ' ' + (e.driver_name || 'Driver') + ' is now ' + s.label + '.');
    }
}
