/* Ambulances CRUD + Real-Time Updates */

/* ── Type / status label maps ─────────────────────────────────────────────── */
var _ambTypeLabels  = { '1':'BLS', '2':'ALS', '3':'CCT', '4':'Neonatal', '5':'AIR' };
var _ambEquipLabels = { '1':'Basic', '2':'Advanced' };
var _ambStatusCfg   = {
    '1': { label:'Available',   cls:'status-1' },
    '2': { label:'On Job',      cls:'status-2' },
    '3': { label:'Maintenance', cls:'status-3' },
    '4': { label:'Inactive',    cls:'status-4' },
};

function limitRating(input) {
    if (input.value > 5) input.value = 5;
    if (input.value < 0) input.value = 0;
}

function previewAmbImage(input) {
    const preview = document.getElementById('ambImgPreview');
    if (!preview) return;
    const file = input.files[0];
    if (!file) {
        preview.innerHTML = '<i class="fa fa-image fa-2x opacity-25"></i>';
        return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
        preview.innerHTML = '<img src="' + e.target.result + '" style="width:20%;height:100%;object-fit:cover;border-radius:10px;">';
    };
    reader.readAsDataURL(file);
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

function _auditHide() {
    var panel = document.getElementById('auditTrail');
    if (panel) panel.style.display = 'none';
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Ambulance';
    document.getElementById('ambForm').reset();
    document.getElementById('amb_id').value = '';
    document.getElementById('amb_driver_id').value = '';
    const preview = document.getElementById('ambImgPreview');
    if (preview) preview.innerHTML = '<i class="fa fa-image fa-2x opacity-25"></i>';
    var nc = document.getElementById('notesCount'); if (nc) nc.textContent = '0';
    var dc = document.getElementById('cardDescCount'); if (dc) dc.textContent = '0';
    const btn = document.getElementById('ambSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Ambulance'; }
    _auditHide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ambModal')).show();
}

function openEditModal(id, data) {
    document.getElementById('modalTitle').textContent = 'Edit Ambulance';
    document.getElementById('amb_id').value            = id;
    document.getElementById('vehicle_number').value   = data.vehicle_number || '';
    document.getElementById('type').value             = data.type || '0';
    document.getElementById('equipment_level').value  = data.equipment_level || '0';
    document.getElementById('amb_status').value       = data.status || '0';
    document.getElementById('amb_driver_id').value    = data.driver_id || '';
    document.getElementById('notes').value            = data.notes || '';
    document.getElementById('card_title').value       = data.card_title || '';
    document.getElementById('card_description').value = data.card_description || '';
    document.getElementById('card_features').value    = data.card_features || '';
    document.getElementById('card_rating').value      = data.card_rating || '';
    document.getElementById('card_trips').value       = data.card_trips || '';

    const preview = document.getElementById('ambImgPreview');
    if (preview) {
        preview.innerHTML = data.card_image
            ? '<img src="/assets/admin/img/fleet/' + data.card_image + '" style="width:20%;height:100%;object-fit:cover;border-radius:10px;">'
            : '<i class="fa fa-image fa-2x opacity-25"></i>';
    }
    var nc = document.getElementById('notesCount'); if (nc) nc.textContent = (data.notes || '').length;
    var dc = document.getElementById('cardDescCount'); if (dc) dc.textContent = (data.card_description || '').length;
    const btn = document.getElementById('ambSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Ambulance'; }
    _auditShow(data);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ambModal')).show();
}

function filterTable() {
    const search = document.getElementById('searchAmb').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    var noResults = document.getElementById('ambNoResults');
    if (!search && !status) {
        if (noResults) noResults.style.display = 'none';
        if (window.PGD) PGD.applyFilter('amb', null);
        return;
    }
    var matched = [];
    document.querySelectorAll('#ambTable tbody tr.pgd-row').forEach(function (row) {
        const matchSearch = !search || (row.dataset.vehicle || '').includes(search);
        const matchStatus = !status || String(row.dataset.status) === String(status);
        if (matchSearch && matchStatus) matched.push(row);
    });
    if (noResults) noResults.style.display = matched.length === 0 ? 'table-row' : 'none';
    if (window.PGD) PGD.applyFilter('amb', matched);
}

function deleteAmbulance(id) {
    confirmAction('Are you sure you want to delete this ambulance? This action cannot be undone.', function () {
        fetch(window.adminRoutes.ambulancesDelete + '/' + id, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', 'Ambulance deleted successfully.');
                // Real-time event (content.updated) will remove the row for all tabs
            } else {
                showAlert('error', data.message || 'Delete failed.');
            }
        })
        .catch(function () { showAlert('error', 'Server error. Please try again.'); });
    })
}

function saveAmbulance() {
    const id = document.getElementById('amb_id').value;
    const isEdit = !!id;
    const url = isEdit ? window.adminRoutes.ambulancesUpdate + '/' + id : window.adminRoutes.ambulancesStore;

    validateForm({
        formId: 'ambForm',
        fields: [
            { id: 'vehicle_number', message: 'Vehicle Number is required.', maxLength: 20 },
            { id: 'type', message: 'Please select a type.', skipIf: '0' },
            { id: 'equipment_level', message: 'Please select an equipment level.', skipIf: '0' },
            { id: 'amb_status', message: 'Please select a status.', skipIf: '0' },
            { id: 'card_title', message: 'Card Title is required.', maxLength: 20 },
            { id: 'card_rating', message: 'Rating is required.', max: 5 },
            { id: 'card_description', message: 'Card Description is required.', maxLength: 500 },
            { id: 'card_features', message: 'Card Features is required.', maxLength: 50 },
        ],
        btn: 'ambSubmitBtn',
        onSuccess: function () {
            submitFormData({
                formId: 'ambForm',
                url: url,
                successMessage: isEdit ? 'Ambulance updated successfully.' : 'Ambulance added successfully.',
                onSuccess: function (resData) {
                    bootstrap.Modal.getInstance(document.getElementById('ambModal'))?.hide();
                    var action = isEdit ? 'updated' : 'added';
                    var ambData = (resData && resData.ambulance) ? resData.ambulance : null;
                    if (ambData && typeof window.admAmbulanceContentUpdated === 'function') {
                        window.admAmbulanceContentUpdated({ module: 'ambulance', action: action, data: ambData });
                    }
                },
                onError: function () {
                    const btn = document.getElementById('ambSubmitBtn');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Ambulance'; }
                },
            });
        },
    });
}

/* ── Build a table row HTML for a new/updated ambulance ───────────────────── */
function _buildAmbulanceRow(amb) {
    var id         = amb.id;
    var vnum       = amb.vehicle_number || '';
    var typeLabel  = _ambTypeLabels[String(amb.type)]  || amb.type || '—';
    var equipLabel = _ambEquipLabels[String(amb.equipment_level)] || '—';
    var equipCls   = String(amb.equipment_level) === '2' ? 'badge-advanced' : 'badge-basic';
    var stCfg      = _ambStatusCfg[String(amb.status)] || { label: amb.status, cls: 'status-3' };
    var driverHtml = (amb.driver && amb.driver.name)
        ? '<span style="font-family:monospace;background:rgba(255,255,255,0.06);padding:2px 8px;border-radius:6px;font-size:0.82rem;">' + _escHtml(amb.driver.name) + '</span>'
        : '<span style="color:rgba(255,255,255,0.25);">—</span>';

    return '<tr class="pgd-row rt-flash-row" data-ambulance-id="' + id + '" data-status="' + amb.status + '" data-vehicle="' + vnum.toLowerCase() + '">'
        + '<td class="ps-4 fs-xs" style="color:var(--adm-muted);">—</td>'
        + '<td><div class="d-flex align-items-center gap-2">'
        + '<div class="adm-icon-preview" style="width:30px;height:30px;border-radius:7px;font-size:0.78rem;"><i class="fa fa-ambulance"></i></div>'
        + '<strong>' + _escHtml(vnum) + '</strong></div></td>'
        + '<td><span class="badge rounded-pill badge-type">' + _escHtml(typeLabel) + '</span></td>'
        + '<td><span class="badge rounded-pill ' + equipCls + '">' + _escHtml(equipLabel) + '</span></td>'
        + '<td><span class="status-pill ' + stCfg.cls + '">' + stCfg.label + '</span></td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + driverHtml + '</td>'
        + '<td><div class="d-flex gap-1">'
        + '<button class="btn-adm-icon btn-adm-icon--edit" title="Edit" onclick="openEditModal(' + id + ',' + JSON.stringify(amb).replace(/"/g, '&quot;') + ')"><i class="fa fa-pen"></i></button>'
        + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteAmbulance(' + id + ')"><i class="fa fa-trash"></i></button>'
        + '</div></td></tr>';
}

function _escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function _flashRow(row) {
    row.style.transition = 'background 0.3s ease';
    row.style.background = 'rgba(99,102,241,0.12)';
    setTimeout(function () { row.style.background = ''; }, 1400);
}

/* ── Real-Time handler: ambulance content.updated ────────────────────────── */
window.admAmbulanceContentUpdated = function (event) {
    if (event.module !== 'ambulance') return;

    var tbody = document.querySelector('#ambTable tbody');
    if (!tbody) return;   // Not on the ambulances page

    var data   = event.data;
    var action = event.action;
    var aid    = String(data.id || '');

    if (action === 'deleted') {
        var row = tbody.querySelector('tr[data-ambulance-id="' + aid + '"]');
        if (row) {
            row.style.transition = 'opacity 0.4s';
            row.style.opacity    = '0';
            setTimeout(function () { row.remove(); if (window.PGD) PGD.rebuild('amb'); }, 400);
        }
        if (typeof showAlert === 'function') showAlert('success', 'Ambulance removed successfully.');
        return;
    }

    if (action === 'added') {
        // Skip if row already exists (inserted directly from the AJAX response)
        if (tbody.querySelector('tr[data-ambulance-id="' + aid + '"]')) return;

        var noResults = document.getElementById('ambNoResults');
        if (noResults) noResults.style.display = 'none';

        tbody.insertAdjacentHTML('afterbegin', _buildAmbulanceRow(data));
        var inserted = tbody.querySelector('tr[data-ambulance-id="' + aid + '"]');
        if (inserted) _flashRow(inserted);
        if (window.PGD) PGD.rebuild('amb');
        if (typeof showAlert === 'function') showAlert('success', 'New ambulance added: ' + (data.vehicle_number || ''));
        return;
    }

    if (action === 'updated') {
        var existingRow = tbody.querySelector('tr[data-ambulance-id="' + aid + '"]');
        if (existingRow) {
            var stCfg      = _ambStatusCfg[String(data.status)] || { label: data.status, cls: 'status-3' };
            var typeLabel  = _ambTypeLabels[String(data.type)]  || data.type || '—';
            var equipLabel = _ambEquipLabels[String(data.equipment_level)] || '—';
            var equipCls   = String(data.equipment_level) === '2' ? 'badge-advanced' : 'badge-basic';
            var driverHtml = (data.driver && data.driver.name)
                ? '<span style="font-family:monospace;background:rgba(255,255,255,0.06);padding:2px 8px;border-radius:6px;font-size:0.82rem;">' + _escHtml(data.driver.name) + '</span>'
                : '<span style="color:rgba(255,255,255,0.25);">—</span>';

            // Update data attributes
            existingRow.setAttribute('data-status', data.status);
            existingRow.setAttribute('data-vehicle', (data.vehicle_number || '').toLowerCase());

            var cells = existingRow.querySelectorAll('td');
            // cell[1] = vehicle number
            if (cells[1]) {
                cells[1].innerHTML = '<div class="d-flex align-items-center gap-2">'
                    + '<div class="adm-icon-preview" style="width:30px;height:30px;border-radius:7px;font-size:0.78rem;"><i class="fa fa-ambulance"></i></div>'
                    + '<strong>' + _escHtml(data.vehicle_number || '') + '</strong></div>';
            }
            // cell[2] = type
            if (cells[2]) cells[2].innerHTML = '<span class="badge rounded-pill badge-type">' + _escHtml(typeLabel) + '</span>';
            // cell[3] = equipment
            if (cells[3]) cells[3].innerHTML = '<span class="badge rounded-pill ' + equipCls + '">' + _escHtml(equipLabel) + '</span>';
            // cell[4] = status
            if (cells[4]) cells[4].innerHTML = '<span class="status-pill ' + stCfg.cls + '">' + stCfg.label + '</span>';
            // cell[5] = driver
            if (cells[5]) cells[5].innerHTML = driverHtml;
            // cell[6] = actions — rebuild with fresh data (JSON must be &quot;-escaped for HTML attributes)
            if (cells[6]) {
                cells[6].innerHTML = '<div class="d-flex gap-1">'
                    + '<button class="btn-adm-icon btn-adm-icon--edit" title="Edit" onclick="openEditModal(' + data.id + ',' + JSON.stringify(data).replace(/"/g, '&quot;') + ')"><i class="fa fa-pen"></i></button>'
                    + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteAmbulance(' + data.id + ')"><i class="fa fa-trash"></i></button>'
                    + '</div>';
            }

            _flashRow(existingRow);
        } else {
            // Row not present (e.g., first page vs paginated) — insert it
            tbody.insertAdjacentHTML('afterbegin', _buildAmbulanceRow(data));
            var ins = tbody.querySelector('tr[data-ambulance-id="' + aid + '"]');
            if (ins) _flashRow(ins);
            if (window.PGD) PGD.rebuild('amb');
        }
        if (typeof showAlert === 'function') showAlert('success', 'Ambulance updated: ' + (data.vehicle_number || ''));
        return;
    }
};
