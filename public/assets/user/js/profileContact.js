/* CONTACT HISTORY — Real-time thread loading, user replies, Echo */

window._rrCurMsgId = null;
let _rrCurMsgId = null;

let _rrTyping = false;
let _rrTypingTimer = null;

// Alias so inline onclick="openContactThread(...)" works
function openContactThread(id, el) {
    rrLoadContactThread(id, el);
}

document.addEventListener("DOMContentLoaded", function () {
    // Wire existing message items (onclick already defined via openContactThread alias above)
    // Also add via addEventListener for items added dynamically
    document.querySelectorAll(".rr-msg-item").forEach(function (item) {
        item.addEventListener("click", function (evt) {
            // Prevent double-fire if onclick already triggered
            if (evt._rrHandled) return;
            evt._rrHandled = true;
            rrLoadContactThread(this.dataset.id, this);
        });
    });

    // Send button
    document
        .getElementById("userSendBtn")
        ?.addEventListener("click", rrSendUserReply);

    // Enter key in textarea
    document
        .getElementById("userReplyInput")
        ?.addEventListener("keydown", function (e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                rrSendUserReply();
            }
        });

    // Auto-resize + typing indicator
    document
        .getElementById("userReplyInput")
        ?.addEventListener("input", function () {
            this.style.height = "auto";
            this.style.height = Math.min(this.scrollHeight, 100) + "px";

            if (!_rrCurMsgId) return;
            clearTimeout(_rrTypingTimer);
            if (!_rrTyping) {
                _rrTyping = true;
                fetch(
                    window.routes.contactThread + "/" + _rrCurMsgId + "/typing",
                    {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": window.routes.csrfToken,
                        },
                    },
                ).catch(function () {});
            }
            _rrTypingTimer = setTimeout(function () {
                _rrTyping = false;
            }, 2500);
        });

    // Auto-open thread from ?thread= URL param (used when clicking a notification)
    const urlParams = new URLSearchParams(window.location.search);
    const threadId = urlParams.get("thread");
    if (threadId) {
        // Activate the Contact History tab
        var contactTab = document.getElementById("v-pills-contacts-tab");
        if (contactTab && typeof bootstrap !== "undefined") {
            new bootstrap.Tab(contactTab).show();
        }
        // Load the thread after tab animation settles
        setTimeout(function () {
            var item = document.getElementById("msgItem" + threadId);
            rrLoadContactThread(threadId, item || null);
        }, 200);
    }
});

// Load a thread from server
async function rrLoadContactThread(id, el) {
    _rrCurMsgId = id;
    window._rrCurMsgId = id;

    // Highlight selected item
    document
        .querySelectorAll(".rr-msg-item")
        .forEach((i) => (i.style.background = ""));
    if (el) el.style.background = "rgba(185,28,44,0.05)";

    // Show spinner
    const body = document.getElementById("contactThreadBody");
    if (body)
        body.innerHTML =
            '<div style="text-align:center;padding:40px;max-height: 60vh;color:var(--rr-text-muted);"><i class="fa fa-spinner fa-spin"></i></div>';

    try {
        const resp = await fetch(
            window.routes.contactThread + "/" + id + "/thread",
            {
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": window.routes.csrfToken,
                },
            },
        );
        const d = await resp.json();

        // Thread body
        if (body) {
            body.innerHTML = "";
            body.appendChild(rrBubble("user", d.message, d.time, "You"));
            (d.replies || []).forEach((r) =>
                body.appendChild(
                    rrBubble(
                        r.sender_type,
                        r.message,
                        r.time,
                        r.sender_type === "admin" ? "Operator" : "You",
                    ),
                ),
            );
            body.scrollTop = body.scrollHeight;
        }

        // Show reply footer
        const footer = document.getElementById("contactReplyFooter");
        if (footer) footer.style.display = "block";

        // Resolved state
        if (d.is_resolved) {
            rrMarkResolved();
        } else {
            const rNotice = document.getElementById("rrResolvedNotice");
            const rInput = document.getElementById("userReplyInput");
            const rBtn = document.getElementById("userSendBtn");
            if (rNotice) rNotice.style.display = "none";
            if (rInput) {
                rInput.disabled = false;
                rInput.placeholder = "Type a follow-up message…";
            }
            if (rBtn) rBtn.disabled = false;
        }

        // Header — show resolved badge if applicable
        const header = document.getElementById("contactThreadHeader");
        if (header) {
            const resolvedTag = d.is_resolved
                ? ` <span style="font-size:0.72rem;background:rgba(34,197,94,0.1);color:#15803d;border:1px solid rgba(34,197,94,0.3);border-radius:20px;padding:2px 10px;font-weight:600;margin-left:8px;vertical-align:middle;"><i class="fa fa-circle-check"></i> Resolved</span>`
                : "";
            header.innerHTML = `<strong style="color:var(--rr-navy);font-size:0.95rem;">${rrHEsc(d.subject)}${resolvedTag}</strong>
                <div style="font-size:0.78rem;color:var(--rr-text-muted);margin-top:3px;">Sent ${rrHEsc(d.time)}</div>`;
        }

        // Clear unread badge
        document.getElementById("unreadBadge" + id)?.remove();
        const listItem = document.getElementById("msgItem" + id);
        if (listItem) listItem.style.borderLeft = "";
    } catch (e) {
        if (body)
            body.innerHTML =
                '<div style="text-align:center;padding:40px;color:var(--rr-text-muted);">Error loading thread.</div>';
    }
}

// Build a chat bubble with sender name
function rrBubble(senderType, message, time, senderName) {
    const isAdmin = senderType === "admin";
    const label = senderName || (isAdmin ? "Operator" : "You");
    const wrap = document.createElement("div");
    wrap.style.cssText = `display:flex;flex-direction:column;align-items:${isAdmin ? "flex-start" : "flex-end"};gap:2px;`;
    wrap.innerHTML = `
            <div style="font-size:0.7rem;font-weight:600;color:var(--rr-text-muted);padding:0 4px;">${rrHEsc(label)}</div>
            <div style="max-width:75%;background:${isAdmin ? "#f0fdf4" : "var(--rr-primary)"};
                    border:1px solid ${isAdmin ? "#d1fae5" : "transparent"};
                    border-radius:12px;padding:10px 14px;font-size:0.88rem;
                    color:${isAdmin ? "#065f46" : "#fff"};">${rrHEsc(message)}</div>
            <div style="font-size:0.68rem;color:var(--rr-text-light);padding:0 4px;">${rrHEsc(time)}</div>`;
    return wrap;
}

function rrHEsc(s) {
    return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

// Send a user reply
async function rrSendUserReply() {
    if (!_rrCurMsgId) return;
    const input = document.getElementById("userReplyInput");
    const msg = input?.value?.trim();
    if (!msg) return;

    const btn = document.getElementById("userSendBtn");
    const origHTML = btn ? btn.innerHTML : "";
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    }

    clearTimeout(_rrTypingTimer);
    _rrTyping = false;

    try {
        const resp = await fetch(
            window.routes.contactReply + "/" + _rrCurMsgId + "/reply",
            {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": window.routes.csrfToken,
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
            const body = document.getElementById("contactThreadBody");
            if (body) {
                body.appendChild(rrBubble("user", msg, data.reply.time, "You"));
                body.scrollTop = body.scrollHeight;
            }
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHTML;
        }
    }
}

// Mark conversation as resolved
function rrMarkResolved() {
    const notice = document.getElementById("rrResolvedNotice");
    const input = document.getElementById("userReplyInput");
    const btn = document.getElementById("userSendBtn");
    const footer = document.getElementById("contactReplyFooter");
    const typing = document.getElementById("rrAdminTypingIndicator");

    if (footer) footer.style.display = "block";
    if (notice) notice.style.display = "block";
    if (input) input.style.display = "none";
    if (btn) btn.style.display = "none";
    if (typing) typing.style.display = "none";
}

// Real-time: conversation resolved by admin
function rrChatResolved(e) {
    if (!_rrCurMsgId || String(_rrCurMsgId) !== String(e.contact_message_id))
        return;
    rrMarkResolved();
}

// Real-time: operator typing indicator
let _rrAdminTypingTimer = null;

function rrShowAdminTyping(e) {
    // Only show indicator if the user is currently viewing this thread
    if (!_rrCurMsgId || String(_rrCurMsgId) !== String(e.contact_message_id))
        return;

    const indicator = document.getElementById("rrAdminTypingIndicator");
    if (!indicator) return;

    indicator.style.display = "flex";

    // Auto-hide after 3 seconds if no further typing events arrive
    clearTimeout(_rrAdminTypingTimer);
    _rrAdminTypingTimer = setTimeout(function () {
        indicator.style.display = "none";
    }, 3000);
}

// Real-time: admin replied → update thread + badge
function rrContactHistoryReply(e) {
    // Hide typing indicator when a reply arrives
    const indicator = document.getElementById("rrAdminTypingIndicator");
    if (indicator) indicator.style.display = "none";
    clearTimeout(_rrAdminTypingTimer);
    const listItem = document.getElementById("msgItem" + e.contact_message_id);

    // Add or update unread badge on list item
    if (listItem) {
        listItem.style.borderLeft = "3px solid var(--rr-primary)";
        if (!document.getElementById("unreadBadge" + e.contact_message_id)) {
            const badge = document.createElement("span");
            badge.id = "unreadBadge" + e.contact_message_id;
            badge.style.cssText =
                "display:inline-flex;align-items:center;gap:4px;background:var(--rr-primary);color:#fff;font-size:0.62rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-top:6px;";
            badge.innerHTML =
                '<i class="fa fa-circle" style="font-size:0.4rem;"></i>NEW REPLY';
            listItem.appendChild(badge);
        }
    }

    // If user is currently viewing this thread, append the reply bubble immediately
    if (String(_rrCurMsgId) === String(e.contact_message_id)) {
        const body = document.getElementById("contactThreadBody");
        if (body) {
            body.appendChild(rrBubble("admin", e.preview, e.time, "Operator"));
            body.scrollTop = body.scrollHeight;
        }
        // Remove badge since user is actively viewing
        document.getElementById("unreadBadge" + e.contact_message_id)?.remove();
        if (listItem) listItem.style.borderLeft = "";
    }
}

// Real-time: new message from another tab → prepend to list
function rrContactHistoryNewMsg(e) {
    const emptyState = document.getElementById("contactEmptyState");
    const panel = document.getElementById("contactMsgListPanel");
    const msgList = document.getElementById("contactMsgList");
    if (!msgList) return;

    if (emptyState) emptyState.style.display = "none";
    if (panel) panel.style.display = "flex";

    const item = document.createElement("div");
    item.className = "rr-msg-item";
    item.id = "msgItem" + e.message_id;
    item.dataset.id = e.message_id;
    item.style.cssText =
        "padding:14px 16px;border-bottom:1px solid var(--rr-border);cursor:pointer;transition:background .15s;border-left:3px solid var(--rr-primary);";
    item.innerHTML = `
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                <strong style="font-size:0.88rem;color:var(--rr-navy);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rrHEsc(e.subject)}</strong>
                <span style="font-size:0.7rem;color:var(--rr-text-light);flex-shrink:0;white-space:nowrap;">${rrHEsc(e.date_short)}</span>
            </div>
            <div style="font-size:0.78rem;color:var(--rr-text-muted);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rrHEsc((e.message || "").substring(0, 45))}</div>`;
    item.addEventListener("click", function () {
        rrLoadContactThread(e.message_id, this);
    });
    msgList.insertBefore(item, msgList.firstChild);
}
