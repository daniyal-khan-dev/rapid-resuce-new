/* ── Real-time: New User Registered ─────────────────────────────────────── */
(function () {
    var cfg = window._rrReverb;
    if (!cfg || typeof Pusher === 'undefined') return;

    var pusher = new Pusher(cfg.key, {
        wsHost:            cfg.wsHost,
        wsPort:            cfg.wsPort,
        wssPort:           cfg.wssPort,
        forceTLS:          cfg.forceTLS,
        enabledTransports: cfg.enabledTransports,
        cluster:           'mt1',
        disableStats:      true,
    });

    pusher.subscribe('contact.admin').bind('user.registered', function (data) {
        admNewUserRegistered(data);
    });
})();

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function admNewUserRegistered(data) {
    var tbody = document.querySelector('#userTable tbody');
    if (!tbody) return;

    var name = (data.name || ((data.first_name || '') + ' ' + (data.last_name || '')).trim()) || data.username;

    var existingRows = tbody.querySelectorAll('tr.pgd-row');
    var newIndex = existingRows.length + 1;

    var row = document.createElement('tr');
    row.className = 'pgd-row';
    row.dataset.status   = '1';
    row.dataset.verified = 'unverified';
    row.dataset.search   = (name + ' ' + (data.username || '') + ' ' + (data.email || '')).toLowerCase();
    row.innerHTML =
        '<td class="ps-4 fs-xs" style="color:var(--adm-muted);">' + newIndex + '</td>' +
        '<td><div class="d-flex align-items-center gap-2">' +
            '<div class="adm-icon-preview" style="width:32px;height:32px;border-radius:50%;font-size:0.8rem;overflow:hidden;padding:0;">' +
                '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">' +
                    '<i class="fa fa-user" style="font-size:0.8rem;"></i>' +
                '</div>' +
            '</div>' +
            '<strong>' + escHtml(name) + '</strong>' +
        '</div></td>' +
        '<td class="fs-xs" style="color:var(--adm-muted);">' + escHtml(data.username) + '</td>' +
        '<td class="fs-xs" style="color:var(--adm-muted);">' + escHtml(data.email) + '</td>' +
        '<td class="fs-xs" style="color:var(--adm-muted);">—</td>' +
        '<td><span class="status-pill status-1">Active</span></td>' +
        '<td><span class="status-pill status-4"><i class="fa fa-circle-xmark me-1" style="font-size:0.7rem;"></i>Unverified</span></td>' +
        '<td class="fs-xs" style="color:var(--adm-muted);">' + escHtml(data.created_at || '—') + '</td>' +
        '<td><button class="btn-adm-icon btn-adm-icon--edit" title="View Details" onclick="viewUser(' + parseInt(data.id, 10) + ')"><i class="fa fa-eye"></i></button></td>';

    var noResults = document.getElementById('userNoResults');
    if (noResults) tbody.insertBefore(row, noResults);
    else tbody.appendChild(row);

    var totalPill = document.querySelector('.adm-page-header .status-pill');
    if (totalPill) {
        var cur = parseInt(totalPill.textContent, 10) || 0;
        totalPill.textContent = (cur + 1) + ' Total';
    }

    if (window.PGD) {
        PGD.init({
            id:      'usr',
            sel:     '#userTable tbody tr.pgd-row',
            prevId:  'usrPrev',
            nextId:  'usrNext',
            infoId:  'usrInfo',
            pagesId: 'usrPages',
            perPage: 10,
        });
    }

}

/* ── Filter ─────────────────────────────────────────────────────────────── */
function filterUserTable() {
    var search = (
        document.getElementById("searchUser").value || ""
    ).toLowerCase();
    var status = document.getElementById("filterUserStatus").value;
    var verified = document.getElementById("filterUserVerified").value;
    var noRes = document.getElementById("userNoResults");

    var matched = [];
    document
        .querySelectorAll("#userTable tbody tr.pgd-row")
        .forEach(function (row) {
            var matchSearch =
                !search || (row.dataset.search || "").includes(search);
            var matchStatus = !status || row.dataset.status === status;
            var matchVerified = !verified || row.dataset.verified === verified;
            if (matchSearch && matchStatus && matchVerified) matched.push(row);
        });

    if (noRes)
        noRes.style.display = matched.length === 0 ? "table-row" : "none";
    if (window.PGD) PGD.applyFilter("usr", matched);
}

/* ── View User ───────────────────────────────────────────────────────────── */
function viewUser(id) {
    var loader = document.getElementById("userViewLoader");
    var content = document.getElementById("userViewContent");
    loader.style.display = "block";
    content.style.display = "none";
    content.innerHTML = "";

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById("userViewModal"),
    ).show();

    fetch(window.usersRoutes.show + "/" + id, {
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": getCsrf(),
        },
    })
        .then(function (r) {
            return r.json();
        })
        .then(function (res) {
            if (!res.success) {
                showAlert("error", "Could not load user details.");
                return;
            }
            var u = res.user;
            var d = u.details || {};
            var mc = u.medical_card || null;

            var statusHtml =
                u.status === "1"
                    ? '<span class="status-pill status-available">Active</span>'
                    : '<span class="status-pill status-maintenance">Suspended</span>';

            var html =
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">';

            /* ── Profile Card ── */
            html +=
                '<div class="card" style="grid-column:1/-1;padding:1.25rem;display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;">';
            html +=
                '<div style="width:64px;height:64px;border-radius:50%;background:rgba(215,44,66,0.1);border:2px solid rgba(215,44,66,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
            html +=
                '<i class="fa fa-user" style="font-size:1.6rem;color:var(--adm-red);"></i></div>';
            html += '<div style="flex:1;min-width:0;">';
            html +=
                '<div style="font-size:1.1rem;font-weight:700;color:#f1f5f9;">' +
                (d.first_name ? d.first_name + " " + d.last_name : u.username) +
                "</div>";
            html +=
                '<div style="font-size:0.82rem;color:var(--adm-muted);margin-top:2px;">@' +
                u.username +
                " &nbsp;·&nbsp; Consumer #" +
                (d.consumer_no || "—") +
                "</div>";
            html +=
                '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">' +
                statusHtml;
            if (d.email_verified_at) {
                html +=
                    '<span class="status-pill status-available"><i class="fa fa-circle-check me-1" style="font-size:0.7rem;"></i>Email Verified</span>';
            } else {
                html +=
                    '<span class="status-pill status-offline"><i class="fa fa-circle-xmark me-1" style="font-size:0.7rem;"></i>Unverified</span>';
            }
            html += "</div></div>";
            html +=
                '<div style="font-size:0.78rem;color:var(--adm-muted);text-align:right;"><div>Joined</div><div style="color:#f1f5f9;font-weight:600;">' +
                (u.created_at || "—") +
                '</div><div style="margin-top:4px;">' +
                u.total_messages +
                " message(s)</div></div>";
            html += "</div>";

            /* ── Personal Info ── */
            html += detailSection("Personal Information", "fa-address-card", [
                [
                    "Full Name",
                    d.first_name ? d.first_name + " " + d.last_name : "—",
                ],
                ["Email", d.email || "—"],
                ["Phone", d.phone || "—"],
                ["Date of Birth", d.date_of_birth || "—"],
                ["Address", d.address || "—"],
            ]);

            /* ── Account Info ── */
            html += detailSection("Account Info", "fa-circle-info", [
                ["Username", u.username],
                ["Consumer No.", d.consumer_no || "—"],
                ["Email Verified", d.email_verified_at || "Not verified"],
                ["Account Status", u.status === "1" ? "Active" : "Suspended"],
                ["Registered On", u.created_at || "—"],
            ]);

            /* ── Medical Card ── */
            if (mc) {
                html +=
                    '<div class="card" style="grid-column:1/-1;padding:1.25rem;">';
                html +=
                    '<div style="font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--adm-muted);margin-bottom:1rem;display:flex;align-items:center;gap:6px;"><i class="fa fa-heart-pulse" style="color:var(--adm-red);"></i> Medical Card</div>';
                html +=
                    '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem 1.5rem;">';
                [
                    ["Blood Type", mc.blood_type || "—"],
                    ["Medical History", mc.medical_history || "—"],
                    ["Allergies", mc.allergies || "—"],
                    ["Medications", mc.medications || "—"],
                    ["Emergency Contact", mc.contact_name || "—"],
                    ["Relation", mc.relation || "—"],
                    ["Contact Phone", mc.contact_phone || "—"],
                ].forEach(function (item) {
                    html +=
                        '<div><div style="font-size:0.72rem;color:var(--adm-muted);margin-bottom:2px;">' +
                        item[0] +
                        "</div>";
                    html +=
                        '<div style="font-size:0.88rem;color:#f1f5f9;font-weight:500;">' +
                        item[1] +
                        "</div></div>";
                });
                html += "</div></div>";
            } else {
                html +=
                    '<div class="card" style="grid-column:1/-1;padding:1.25rem;text-align:center;color:var(--adm-muted);">';
                html +=
                    '<i class="fa fa-heart-pulse d-block mb-2 opacity-40" style="font-size:1.5rem;"></i>';
                html +=
                    '<span style="font-size:0.85rem;">No medical card on file.</span></div>';
            }

            html += "</div>";

            loader.style.display = "none";
            content.innerHTML = html;
            content.style.display = "block";
        })
        .catch(function () {
            showAlert("error", "Server error. Please try again.");
            loader.style.display = "none";
        });
}

function detailSection(title, icon, rows) {
    var html = '<div class="card" style="padding:1.25rem;">';
    html +=
        '<div style="font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--adm-muted);margin-bottom:1rem;display:flex;align-items:center;gap:6px;"><i class="fa ' +
        icon +
        '" style="color:var(--adm-red);"></i> ' +
        title +
        "</div>";
    html += '<div style="display:flex;flex-direction:column;gap:0.65rem;">';
    rows.forEach(function (row) {
        html +=
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:1px solid rgba(255,255,255,0.04);padding-bottom:0.5rem;">';
        html +=
            '<span style="font-size:0.78rem;color:var(--adm-muted);white-space:nowrap;">' +
            row[0] +
            "</span>";
        html +=
            '<span style="font-size:0.85rem;color:#f1f5f9;font-weight:500;text-align:right;word-break:break-all;">' +
            row[1] +
            "</span>";
        html += "</div>";
    });
    html += "</div></div>";
    return html;
}

