/* Content Management (Services / Testimonials / FAQs) */
function filterContent() {
    var q = (document.getElementById('searchContent') || {}).value;
    var noResults = document.getElementById('contentNoResults');
    if (!q) {
        if (noResults) noResults.style.display = 'none';
        if (window.PGD && window.pgdId) PGD.applyFilter(window.pgdId, null);
        return;
    }
    q = q.toLowerCase();
    var matched = [];
    document.querySelectorAll('table tbody tr.pgd-row').forEach(function(row) {
        if (row.textContent.toLowerCase().includes(q)) matched.push(row);
    });
    if (noResults) noResults.style.display = matched.length === 0 ? 'table-row' : 'none';
    if (window.PGD && window.pgdId) PGD.applyFilter(window.pgdId, matched);
}

function updateIconPreview(val) {
    const preview = document.getElementById('iconPreview');
    if (!preview) return;
    preview.innerHTML = '<i class="' + (val || 'fas fa-cogs') + '"></i>';
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
    document.getElementById('modalTitle').textContent = 'Add ' + formatType(window.contentType);
    document.getElementById('contentForm').reset();
    document.getElementById('item_id').value = '';
    const ip = document.getElementById('iconPreview');
    if (ip) ip.innerHTML = '<i class="fas fa-cogs"></i>';
    var cc;
    cc = document.getElementById('svcDescCount');    if (cc) cc.textContent = '0';
    cc = document.getElementById('svcContentCount'); if (cc) cc.textContent = '0';
    cc = document.getElementById('svcAnswerCount');  if (cc) cc.textContent = '0';
    const btn = document.getElementById('contentSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save ' + formatType(window.contentType); }
    _auditHide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('contentModal')).show();
}

function openEditModal(id, data) {
    document.getElementById('modalTitle').textContent = 'Edit ' + formatType(window.contentType);
    document.getElementById('item_id').value = id;

    setIfExists('svc_icon',        data.icon        || '');
    setIfExists('svc_title',       data.title       || '');
    setIfExists('svc_description', data.description || '');
    setIfExists('svc_name',        data.name        || '');
    setIfExists('svc_role',        data.role        || '');
    setIfExists('svc_content',     data.content     || '');
    setIfExists('svc_rating',      data.rating !== undefined ? String(data.rating) : '5');
    setIfExists('svc_question',    data.question    || '');
    setIfExists('svc_answer',      data.answer      || '');
    setIfExists('svc_sort_order',  data.sort_order !== undefined ? data.sort_order : 0);
    setIfExists('svc_status',      data.status !== undefined ? String(data.status) : '1');

    if (data.icon) updateIconPreview(data.icon);

    var cc;
    cc = document.getElementById('svcDescCount');    if (cc) cc.textContent = (data.description || '').length;
    cc = document.getElementById('svcContentCount'); if (cc) cc.textContent = (data.content || '').length;
    cc = document.getElementById('svcAnswerCount');  if (cc) cc.textContent = (data.answer || '').length;

    const btn = document.getElementById('contentSubmitBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save ' + formatType(window.contentType); }
    _auditShow(data);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('contentModal')).show();
}

function setIfExists(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function formatType(t) {
    const map = { service: 'Service', testimonial: 'Testimonial', faq: 'FAQ' };
    return map[t] || t;
}

function deleteItem(id, label) {
    confirmAction('Are you sure you want to delete this ' + label + '? This action cannot be undone.', function () {
        fetch(window.contentRoutes.del + '/' + id, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', label.charAt(0).toUpperCase() + label.slice(1) + ' deleted successfully.');
                setTimeout(function () { location.reload(); }, 1000);
            } else {
                showAlert('error', data.message || 'Delete failed.');
            }
        })
        .catch(function () { showAlert('error', 'Server error. Please try again.'); });
    });
}

function saveContent() {
    const id     = document.getElementById('item_id').value;
    const isEdit = !!id;
    const url    = isEdit ? window.contentRoutes.update + '/' + id : window.contentRoutes.store;

    const fieldsByType = {
        service: [
            { id: 'svc_icon',        message: 'Icon class is required.',         maxLength: 30 },
            { id: 'svc_title',       message: 'Title is required.',              maxLength: 50 },
            { id: 'svc_description', message: 'Description is required.',        maxLength: 500 },
            { id: 'svc_status',      message: 'Please select a status.',         skipIf: '0' },
        ],
        testimonial: [
            { id: 'svc_name',    message: 'Name is required.',                   maxLength: 30 },
            { id: 'svc_role',    message: 'Role/Location is required.',          maxLength: 30 },
            { id: 'svc_content', message: 'Testimonial content is required.',    maxLength: 500 },
            { id: 'svc_rating',  message: 'Please select a rating.',             skipIf: '0' },
            { id: 'svc_status',  message: 'Please select a status.',             skipIf: '0' },
        ],
        faq: [
            { id: 'svc_question', message: 'Question is required.', maxLength: 400 },
            { id: 'svc_answer',   message: 'Answer is required.',   maxLength: 2000 },
            { id: 'svc_status',   message: 'Please select a status.', skipIf: '0' },
        ],
    };

    const label = formatType(window.contentType);

    validateForm({
        formId: 'contentForm',
        fields: fieldsByType[window.contentType] || [],
        btn:    'contentSubmitBtn',
        onSuccess: function () {
            submitFormData({
                formId: 'contentForm',
                url: url,
                successMessage: isEdit ? label + ' updated successfully.' : label + ' added successfully.',
                onSuccess: function () {
                    bootstrap.Modal.getInstance(document.getElementById('contentModal'))?.hide();
                    setTimeout(function () { location.reload(); }, 900);
                },
                onError: function () {
                    const btn = document.getElementById('contentSubmitBtn');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-check me-1"></i> Save ' + label; }
                },
            });
        },
    });
}
