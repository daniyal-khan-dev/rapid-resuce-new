let _cMsgId = null;
let _cUserName = null;
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

let _admTyping = false;
let _admTypingTimer = null;

// ── Wire up existing list items ───────────────────────────────────────────────
document.querySelectorAll(".msg-item").forEach(function (item) {
    item.addEventListener("click", function () {
        const id = this.id.replace("msgItem", "");
        admSelectItem(this);
        admLoadThread(id);
    });
});

function admSelectItem(el) {
    document
        .querySelectorAll(".msg-item")
        .forEach((i) => i.classList.remove("active"));
    if (el) el.classList.add("active");
}

// ── Load thread from server
async function admLoadThread(id) {
    _cMsgId = id;

    window._admCurMsgId = id;

    const item = document.getElementById("msgItem" + id);
    window._admReadByThisTab = window._admReadByThisTab || new Set();
    if (item && item.classList.contains("unread")) {
        window._admReadByThisTab.add(String(id));
        item.classList.remove("unread");
        item.querySelector(".msg-badge-unread")?.remove();
    }

    const body = document.getElementById("chatBody");
    const header = document.getElementById("chatHeader");
    if (body)
        body.innerHTML =
            '<div class="chat-placeholder"><i class="fa fa-spinner fa-spin fa-2x opacity-25"></i></div>';

    try {
        const resp = await fetch(`${window.route.cm}/${_cMsgId}/thread`, {
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": _csrf,
            },
        });
        if (!resp.ok) throw new Error("HTTP " + resp.status);
        const d = await resp.json();

        _cUserName = d.name || "User";

        // Build avatar HTML if profile picture exists
        const avatarHtml = d.profile_picture_url
            ? `<img src="${cEsc(d.profile_picture_url)}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
            : `<div style="width:36px;height:36px;border-radius:50%;background:rgba(215,44,66,0.15);color:#f87184;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;"><i class="fa fa-user"></i></div>`;

        // Header — with resolve button or resolved badge
        const resolvedBadge = d.is_resolved
            ? `<span style="font-size:0.78rem;background:rgba(34,197,94,0.1);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;flex-shrink:0;"><i class="fa fa-circle-check"></i> Resolved</span>`
            : `<button id="resolveBtn" onclick="admResolveChat(${id})" style="font-size:0.78rem;background:rgba(34,197,94,0.12);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;cursor:pointer;flex-shrink:0;"><i class="fa fa-circle-check"></i> Mark Resolved</button>`;
        if (header) {
            header.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight:700;color:#fff;font-size:0.95rem;">${cEsc(d.name)}${!d.is_user ? ' <span style="font-size:0.7rem;background:rgba(99,102,241,0.2);color:#a5b4fc;border-radius:20px;padding:2px 8px;border:1px solid rgba(99,102,241,0.3);margin-left:6px;">Guest</span>' : ""}</div>
                            <div style="font-size:0.78rem;color:var(--adm-muted);margin-top:2px;">${cEsc(d.email)} · ${cEsc(d.time)}</div>
                        </div>
                    </div>
                    ${resolvedBadge}
                </div>
                <div style="font-weight:600;color:rgba(255,255,255,0.85);font-size:0.87rem;margin-top:8px;">${cEsc(d.subject)}</div>`;
        }

        // Body
        if (body) {
            body.innerHTML = "";
            body.appendChild(cBubble("user", d.message, d.time, _cUserName));
            (d.replies || []).forEach(function (r) {
                body.appendChild(
                    cBubble(
                        r.sender_type,
                        r.message,
                        r.time,
                        r.sender_type === "user" ? _cUserName : "Operator",
                    ),
                );
            });
            body.scrollTop = body.scrollHeight;
        }

        // Footer
        const footer = document.getElementById("chatFooter");
        const resolvedNotice = document.getElementById("resolvedNotice");
        const replyInput = document.getElementById("replyInput");
        const sendBtn = document.getElementById("sendBtn");
        if (footer) footer.style.display = "flex";

        if (d.is_resolved) {
            if (resolvedNotice) resolvedNotice.style.display = "block";
            if (replyInput) replyInput.style.display = "none";
            if (sendBtn) sendBtn.style.display = "none";
        } else {
            if (resolvedNotice) resolvedNotice.style.display = "none";
            if (replyInput) replyInput.style.display = "block";
            if (sendBtn) sendBtn.style.display = "block";
        }

        // Guest notice
        const notice = document.getElementById("guestEmailNotice");
        const emailEl = document.getElementById("guestEmailAddress");
        if (!d.is_user) {
            if (emailEl) emailEl.textContent = d.email;
            if (notice) notice.style.display = "flex";
        } else {
            if (notice) notice.style.display = "none";
        }

        // Mark list item as read
        document.getElementById("msgItem" + id)?.classList.remove("unread");
    } catch (e) {
        if (body)
            body.innerHTML =
                '<div class="chat-placeholder">Error loading thread.</div>';
    }
}

// ── Build chat bubble with sender name ────────────────────────────────────────
function cBubble(senderType, message, time, senderName) {
    const isAdmin = senderType === "admin";
    const label = senderName || (isAdmin ? "Operator" : "User");
    const wrap = document.createElement("div");
    wrap.style.cssText = `display:flex;flex-direction:column;align-items:${isAdmin ? "flex-end" : "flex-start"};gap:2px;`;
    wrap.innerHTML = `
        <div style="font-size:0.7rem;font-weight:600;color:var(--adm-muted);padding:0 4px;">${cEsc(label)}</div>
        <div style="max-width:75%;background:${isAdmin ? "rgba(215,44,66,0.18)" : "rgba(255,255,255,0.06)"};
                border:1px solid ${isAdmin ? "rgba(215,44,66,0.25)" : "rgba(255,255,255,0.08)"};
                border-radius:12px;padding:10px 14px;font-size:0.88rem;
                color:${isAdmin ? "#fca5a5" : "rgba(255,255,255,0.85)"};">
            ${cEsc(message)}
        </div>
        <div style="font-size:0.68rem;color:var(--adm-muted);padding:0 4px;">${cEsc(time)}</div>`;
    return wrap;
}

function cEsc(s) {
    return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

// ── Send reply ────────────────────────────────────────────────────────────────
document.getElementById("sendBtn")?.addEventListener("click", admSendReply);
document.getElementById("replyInput")?.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        admSendReply();
    }
});

async function admSendReply() {
    if (!_cMsgId) return;
    const input = document.getElementById("replyInput");
    const msg = input?.value?.trim();
    if (!msg) return;

    const btn = document.getElementById("sendBtn");
    const origHTML = btn ? btn.innerHTML : "";
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    }

    clearTimeout(_admTypingTimer);
    _admTyping = false;

    try {
        const resp = await fetch(`${window.route.cm}/${_cMsgId}/reply`,
            {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": _csrf,
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    message: msg,
                }),
            },
        );
        const data = await resp.json();
        if (data.success) {
            input.value = "";
            input.style.height = "auto";
            const body = document.getElementById("chatBody");
            if (body) {
                body.appendChild(
                    cBubble("admin", msg, data.reply.time, "Operator"),
                );
                body.scrollTop = body.scrollHeight;
            }
            // Notify other open admin tabs so they can show the reply too
            if (window._contactTabBC) {
                try {
                    window._contactTabBC.postMessage({
                        type: "admin_reply",
                        contact_message_id: _cMsgId,
                        message: msg,
                        time: data.reply.time,
                    });
                } catch (e) {}
            }
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHTML;
        }
    }
}

// ── Real-time: new contact message prepended to list ─────────────────────────
window.admContactMsgPrepend = function admContactMsgPrepend(e) {
    const list = document.getElementById("msgList");
    if (!list) return;

    // Hide empty state when first real-time message arrives
    const emptyState = document.getElementById("msgEmptyState");
    if (emptyState) emptyState.style.display = "none";

    const div = document.createElement("div");
    div.className = "msg-item unread";
    div.id = "msgItem" + e.message_id;
    div.setAttribute(
        "data-search",
        (
            (e.name || "") +
            " " +
            (e.subject || "") +
            " " +
            (e.email || "")
        ).toLowerCase(),
    );
    div.setAttribute("data-type", e.user_id ? "user" : "guest");
    div.addEventListener("click", function () {
        admSelectItem(this);
        admLoadThread(e.message_id);
    });
    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="min-width:0;">
                <div class="fw-bold text-white" style="font-size:0.85rem;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">
                    ${cEsc(e.name)}
                    <span class="msg-badge-unread">new</span>
                    ${!e.user_id ? '<span class="msg-badge-guest">Guest</span>' : ""}
                </div>
                <div style="font-size:0.78rem;color:var(--adm-muted);text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">${cEsc(e.subject)}</div>
            </div>
            <div style="font-size:0.7rem;color:var(--adm-muted);white-space:nowrap;flex-shrink:0;">${cEsc(e.date_short)}</div>
        </div>
        <div style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-top:4px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">${cEsc((e.message || "").substring(0, 55))}</div>`;
    list.insertBefore(div, list.firstChild);
};

// ── Real-time: user sent follow-up reply — append to current thread ───────────
window.admContactThreadReply = function admContactThreadReply(e) {
    if (String(_cMsgId) !== String(e.contact_message_id)) return;
    const body = document.getElementById("chatBody");
    if (!body) return;
    body.appendChild(
        cBubble(
            "user",
            e.message || e.preview,
            e.time,
            e.user_name || _cUserName || "User",
        ),
    );
    body.scrollTop = body.scrollHeight;
};

// ── Real-time: mark a list item with a "reply" badge when a user replies ──────
window.admAddReplyBadge = function admAddReplyBadge(msgId) {
    const item = document.getElementById("msgItem" + msgId);
    if (!item) return;
    // If the admin already has that thread open, don't show an indicator
    if (item.classList.contains("active")) return;
    // Avoid duplicating the badge
    if (!item.classList.contains("unread")) {
        item.classList.add("unread");
        const badge = document.createElement("span");
        badge.className = "msg-badge-unread msg-badge-reply";
        badge.textContent = "Reply";
        // Insert just after the name element so it appears inline with the name
        const nameEl = item.querySelector(".fw-bold");
        if (nameEl) nameEl.appendChild(badge);
        else item.insertBefore(badge, item.firstChild);
    }
};

// ── Auto-resize + typing indicator
document.getElementById("replyInput")?.addEventListener("input", function () {
    this.style.height = "auto";
    this.style.height = Math.min(this.scrollHeight, 100) + "px";

    if (!_cMsgId) return;
    clearTimeout(_admTypingTimer);
    if (!_admTyping) {
        _admTyping = true;
        fetch(`${window.route.cm}/${_cMsgId}/typing`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": _csrf,
            },
        }).catch(function () {});
    }
    _admTypingTimer = setTimeout(function () {
        _admTyping = false;
    }, 2500);
});

// ── Resolve chat
async function admResolveChat(id) {
    const btn = document.getElementById("resolveBtn");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Resolving…';
    }
    try {
        const resp = await fetch(`${window.route.cm}/${_cMsgId}/resolve`,  {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": _csrf,
                Accept: "application/json",
            },
        });
        const data = await resp.json();
        if (data.success) admMarkResolved(id);
    } catch (e) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-circle-check"></i> Mark Resolved';
        }
    }
}

function admMarkResolved(id) {
    const btn = document.getElementById("resolveBtn");
    if (btn) {
        const badge = document.createElement("span");
        badge.style.cssText =
            "font-size:0.78rem;background:rgba(34,197,94,0.1);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;flex-shrink:0;";
        badge.innerHTML = '<i class="fa fa-circle-check"></i> Resolved';
        btn.replaceWith(badge);
    }
    const replyInput = document.getElementById("replyInput");
    const sendBtn = document.getElementById("sendBtn");
    if (replyInput) replyInput.style.display = "none";
    if (sendBtn) sendBtn.style.display = "none";
    const notice = document.getElementById("resolvedNotice");
    if (notice) notice.style.display = "block";
    const listItem = document.getElementById("msgItem" + id);
    if (listItem) listItem.style.opacity = "0.65";
}

// ── Search / filter (existing UI hook)
document.getElementById("msgSearch")?.addEventListener("input", filterMessages);
document.querySelectorAll(".msg-filter-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
        document.querySelectorAll(".msg-filter-btn").forEach((b) => b.classList.remove("active"));
        this.classList.add("active");
        filterMessages();
    });
});

function filterMessages() {
    const q = (document.getElementById("msgSearch")?.value || "").toLowerCase();
    const active = document.querySelector(".msg-filter-btn.active")?.id || "filterAll";
    document.querySelectorAll(".msg-item").forEach(function (item) {
        const matchQ = !q || (item.getAttribute("data-search") || "").includes(q);
        const t = item.getAttribute("data-type") || "";
        const matchT = active === "filterAll" || (active === "filterUser" && t === "user") || (active === "filterGuest" && t === "guest");
        item.style.display = matchQ && matchT ? "" : "none";
    });
}

// ── Real-time: user typing indicator ─────────────────────────────────
let _userTypingTimer = null;
window.admContactUserTyping = function admContactUserTyping(data) {
    if (String(_cMsgId) !== String(data.contact_message_id)) return;
    const indicator = document.getElementById("userTypingIndicator");
    if (!indicator) return;
    indicator.style.display = "flex";
    clearTimeout(_userTypingTimer);
    _userTypingTimer = setTimeout(function () {
        indicator.style.display = "none";
    }, 3000);
};

// ── Real-time: message marked as read (fired by another tab or this tab) ──
window.admContactMessageRead = function admContactMessageRead(data) {
    const item = document.getElementById("msgItem" + data.message_id);
    if (!item) return;
    item.classList.remove("unread");
    item.querySelectorAll(".msg-badge-unread, .msg-badge-reply").forEach(
        function (b) {
            b.remove();
        },
    );
};

// ── Cross-tab admin reply sync (BroadcastChannel) ─────────────────────
// When this tab sends a reply the original admSendReply posts to window._contactTabBC.
// Other admin tabs on this page listen here and append the bubble if they
// have the same thread open.
window._contactTabBC = null;
try {
    window._contactTabBC = new BroadcastChannel("rr-contact-admin-tab");
} catch (e) {}

if (window._contactTabBC) {
    window._contactTabBC.onmessage = function (ev) {
        const d = ev.data || {};
        if (d.type === "admin_reply" && String(_cMsgId) === String(d.contact_message_id)) {
            const body = document.getElementById("chatBody");
            if (body) {
                body.appendChild(
                    cBubble("admin", d.message, d.time, "Operator"),
                );
                body.scrollTop = body.scrollHeight;
            }
        }
    };
}
