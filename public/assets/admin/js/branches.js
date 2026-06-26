/* Branch Modal */
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

function openAddBranchModal() {
    document.getElementById('branchModalTitle').innerHTML =
        '<span class="modal-title-icon"><i class="fa fa-building"></i></span> Add Branch';
    document.getElementById('branchForm').reset();
    document.getElementById('branch_id').value = '';
    var btn = document.getElementById('branchSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Branch'; }
    _auditHide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('branchModal')).show();
}

function openEditBranchModal(id, data) {
    document.getElementById('branchModalTitle').innerHTML = '<span class="modal-title-icon"><i class="fa fa-building"></i></span> Edit Branch';
    document.getElementById('branch_id').value      = id;
    document.getElementById('branch_name').value    = data.name    || '';
    document.getElementById('branch_address').value = data.address || '';
    document.getElementById('branch_phone').value   = data.phone   || '';
    document.getElementById('branch_email').value   = data.email   || '';
    document.getElementById('status').value         = data.status  || '0';
    var btn = document.getElementById('branchSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Branch'; }
    _auditShow(data);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('branchModal')).show();
}

function saveBranch() {
    const id     = document.getElementById('branch_id').value;
    const isEdit = !!id;
    const url    = isEdit ? window.branchesRoutes.branchUpdate + '/' + id : window.branchesRoutes.branchAdd;

    validateForm({
        formId: 'branchForm',
        fields: [
            { id: 'branch_name',    message: 'Branch name is required.',          maxLength: 30 },
            { id: 'branch_address', message: 'Branch address is required.',        maxLength: 100 },
            { id: 'branch_phone',   message: 'Branch phone is required.',          maxLength: 13 },
            { id: 'branch_email',   message: 'Branch email is required.',          validate: 'email' },
            { id: 'status',         message: 'Please select a status.',            skipIf: '0' },
        ],
        btn: 'branchSubmitBtn',
        onSuccess: function () {
            submitFormData({
                formId: 'branchForm',
                url: url,
                successMessage: isEdit ? 'Branch updated successfully.' : 'Branch added successfully.',
                onSuccess: function (resData) {
                    bootstrap.Modal.getInstance(document.getElementById('branchModal'))?.hide();
                    setTimeout(function () { location.reload(); }, 900);
                },
                onError: function () {
                    const btn = document.getElementById('branchSubmitBtn');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save Branch'; }
                },
            });
        },
    });
}

function deleteBranch(id) {
    confirmAction('Are you sure you want to delete this branch? This action cannot be undone.', function () {
        fetch(window.branchesRoutes.branchDelete + '/' + id, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', 'Branch deleted successfully.');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                showAlert('error', data.message || 'Delete failed.');
            }
        })
        .catch(function () { showAlert('error', 'Server error. Please try again.'); });
    });
}

/* ── Admin real-time branch table handler ──────────────────────────────── */
function _branchEscHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function _buildBranchRow(b) {
    var statusLabel = b.status == '2' ? 'Inactive' : 'Active';
    var statusCls   = b.status == '2' ? 'status-4' : 'status-1';
    var encoded     = JSON.stringify(b).replace(/"/g, '&quot;');
    return '<tr data-branch-id="' + b.id + '">'
        + '<td class="ps-4 fs-xs" style="color:var(--adm-muted);">—</td>'
        + '<td><div class="d-flex align-items-center gap-2">'
        + '<div class="adm-icon-preview" style="width:30px;height:30px;border-radius:50%;font-size:0.75rem;flex-shrink:0;"><i class="fa fa-building"></i></div>'
        + '<strong>' + _branchEscHtml(b.name) + '</strong></div></td>'
        + '<td style="color:var(--adm-muted);font-size:0.85rem;max-width:280px;">' + _branchEscHtml((b.address || '').substring(0, 20)) + '</td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _branchEscHtml(b.phone) + '</td>'
        + '<td class="fs-xs" style="color:var(--adm-muted);">' + _branchEscHtml(b.email) + '</td>'
        + '<td><span class="status-pill ' + statusCls + '">' + statusLabel + '</span></td>'
        + '<td><div class="d-flex gap-1">'
        + '<button class="btn-adm-icon btn-adm-icon--edit" title="Edit" onclick="openEditBranchModal(' + b.id + ',' + encoded + ')"><i class="fa fa-pen"></i></button>'
        + '<button class="btn-adm-icon btn-adm-icon--danger" title="Delete" onclick="deleteBranch(' + b.id + ')"><i class="fa fa-trash"></i></button>'
        + '</div></td></tr>';
}