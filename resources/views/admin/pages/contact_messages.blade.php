@extends('admin.layouts.admin')
@section('title', 'Contact Messages')
@section('page_title', 'Contact Messages')

@push('styles')
    <style>
        .msg-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .msg-item {
            padding: 14px 16px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--adm-border);
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }

        .msg-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .msg-item.active {
            border-color: rgba(215, 44, 66, 0.5);
            background: rgba(215, 44, 66, 0.06);
        }

        .msg-item.unread {
            border-left: 3px solid #f87184;
        }

        .msg-badge-unread {
            display: inline-block;
            background: #f87184;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            border-radius: 20px;
            padding: 1px 7px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .msg-badge-reply {
            display: inline-block;
            background: #f59e0b;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            border-radius: 20px;
            padding: 1px 7px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .chat-panel {
            display: flex;
            flex-direction: column;
            height: 620px;
            border: 1px solid var(--adm-border);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
            overflow: hidden;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--adm-border);
            background: rgba(255, 255, 255, 0.04);
        }

        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--adm-muted);
            font-size: 0.9rem;
            flex-direction: column;
            gap: 12px;
        }

        .chat-footer {
            padding: 14px 16px;
            border-top: 1px solid var(--adm-border);
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .typing-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--adm-muted);
            margin: 0 1px;
            animation: typingBounce 1.2s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: .2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: .4s;
        }

        @keyframes typingBounce {

            0%,
            80%,
            100% {
                transform: translateY(0);
                opacity: .4
            }

            40% {
                transform: translateY(-4px);
                opacity: 1
            }
        }

        .chat-footer textarea {
            flex: 1;
            resize: none;
            border-radius: 10px;
            min-height: 48px;
            max-height: 120px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--adm-border);
            color: #fff;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .chat-footer textarea:focus {
            outline: none;
            border-color: rgba(215, 44, 66, 0.4);
        }

        .chat-bubble {
            max-width: 78%;
            padding: 11px 16px;
            border-radius: 14px;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .bubble-admin {
            background: rgba(215, 44, 66, 0.15);
            color: #f8d0d5;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .bubble-user {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .bubble-meta {
            font-size: 0.72rem;
            color: var(--adm-muted);
            margin-top: 3px;
        }

        .bubble-original {
            padding: 11px 16px;
            border-radius: 10px;
            background: rgba(59, 130, 246, 0.10);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            font-size: 0.88rem;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .resolve-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1.5px solid rgba(34, 197, 94, 0.4);
            color: #86efac;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }

        .resolve-btn:hover {
            background: rgba(34, 197, 94, 0.15);
        }

        .resolve-btn.resolved {
            border-color: rgba(107, 114, 128, 0.4);
            color: #9ca3af;
        }

        .msg-badge-guest {
            display: inline-block;
            background: rgba(99, 102, 241, 0.25);
            color: #a5b4fc;
            font-size: 0.63rem;
            font-weight: 700;
            border-radius: 20px;
            padding: 1px 7px;
            margin-left: 5px;
            vertical-align: middle;
            border: 1px solid rgba(99, 102, 241, 0.35);
        }

        .msg-filter-btn {
            flex: 1;
            padding: 4px 0;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1.5px solid var(--adm-border);
            color: var(--adm-muted);
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }

        .msg-filter-btn:hover {
            border-color: rgba(215, 44, 66, 0.4);
            color: #fff;
        }

        .msg-filter-btn.active {
            border-color: rgba(215, 44, 66, 0.6);
            color: #fff;
            background: rgba(215, 44, 66, 0.15);
        }

        .guest-email-notice {
            display: none;
            align-items: center;
            gap: 7px;
            font-size: 0.78rem;
            color: #a5b4fc;
            padding: 0 2px 8px;
            background: rgba(99, 102, 241, 0.08);
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 8px;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
    </style>
@endpush

@section('content')
    <div class="adm-page-header">
        <div>
            <h2>Contact Messages</h2>
            <p>
                View and reply to messages from visitors and users. Guest replies are delivered via email.
                @if ($unread > 0)
                    <span class="msg-badge-unread">{{ $unread }} unread</span>
                @endif
            </p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Message list --}}
        <div class="col-lg-4">
            <div class="card" style="max-height:640px;overflow-y:auto;">
                <div class="card-header py-3 d-flex flex-column gap-2">
                    <input type="text" id="msgSearch" class="form-control form-control-sm" placeholder="Search messages…"
                        oninput="filterMessages()">
                    <div class="d-flex gap-2">
                        <button class="msg-filter-btn active" id="filterAll">All</button>
                        <button class="msg-filter-btn" id="filterUser"><i class="fa fa-user me-1"></i>Users</button>
                        <button class="msg-filter-btn" id="filterGuest"><i class="fa fa-user-slash me-1"></i>Guests</button>
                    </div>
                </div>
                @if ($messages->isEmpty())
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fa fa-inbox fa-2x d-block mb-3 opacity-25"></i>
                        <p class="mb-0">No messages yet.</p>
                    </div>
                @else
                    <div class="card-body p-2">
                        <div class="msg-list" id="msgList">
                            @foreach ($messages as $msg)
                                <div class="msg-item {{ !$msg->admin_read ? 'unread' : '' }}"
                                    id="msgItem{{ $msg->id }}"
                                    data-search="{{ strtolower($msg->name . ' ' . $msg->subject . ' ' . $msg->email) }}"
                                    data-type="{{ $msg->user_id ? 'user' : 'guest' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div style="min-width:0;">
                                            <div class="fw-bold text-white fs-xs text-truncate">
                                                {{ $msg->name }}
                                                @if (!$msg->admin_read)
                                                    <span class="msg-badge-unread">new</span>
                                                @endif
                                                @if (!$msg->user_id)
                                                    <span class="msg-badge-guest"
                                                        title="Submitted without an account — replies go via email">Guest</span>
                                                @endif
                                            </div>
                                            <div class="text-truncate" style="font-size:0.78rem;color:var(--adm-muted);">
                                                {{ $msg->subject }}</div>
                                        </div>

                                        <div
                                            style="font-size:0.7rem;color:var(--adm-muted);white-space:nowrap;flex-shrink:0;">
                                            {{ $msg->created_at->format('d M') }}
                                        </div>
                                    </div>

                                    <div class="mt-1 text-truncate" style="font-size:0.78rem;color:rgba(255,255,255,0.4);">
                                        {{ Str::limit($msg->message, 55) }}
                                    </div>

                                    @if ($msg->replies->count() > 0)
                                        <div class="mt-1" style="font-size:0.71rem;color:var(--adm-muted);">
                                            <i class="fa fa-comments"
                                                style="margin-right:3px;"></i>{{ $msg->replies->count() }}
                                            repl{{ $msg->replies->count() > 1 ? 'ies' : 'y' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div id="msgNoResults" class="text-center py-4 text-muted" style="display:none;font-size:0.85rem;">
                            <i class="fa fa-search d-block mb-2 opacity-50"></i>No messages match your search.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Chat panel --}}
        <div class="col-lg-8">
            <div class="chat-panel">
                <div class="chat-header" id="chatHeader">
                    <div class="chat-placeholder" style="height:auto;padding:0;">
                        <span style="color:var(--adm-muted);font-size:0.88rem;">
                            <i class="fa fa-arrow-left me-2"></i>Select a message to start chatting
                        </span>
                    </div>
                </div>

                <div class="chat-body" id="chatBody">
                    <div class="chat-placeholder">
                        <i class="fa fa-envelope-open-text fa-2x opacity-25"></i>
                        <span>Select a message from the left to view the conversation</span>
                    </div>
                </div>

                <div class="chat-footer" id="chatFooter" style="display:none;">
                    <div id="guestEmailNotice" class="guest-email-notice">
                        <i class="fa fa-envelope" style="flex-shrink:0;"></i>
                        <span>This is a <strong>guest</strong> message — your reply will be sent to <span
                                id="guestEmailAddress" style="font-weight:600;"></span> via email.</span>
                    </div>

                    <div id="resolvedNotice"
                        style="display:none;padding:10px 14px;background:rgba(34,197,94,0.07);border-radius:8px;color:#86efac;font-size:0.82rem;font-weight:600;text-align:center;border:1px solid rgba(34,197,94,0.22);margin-bottom:8px;">
                        <i class="fa fa-circle-check"></i> This conversation has been resolved. No new messages can be sent.
                    </div>

                    <div id="userTypingIndicator"
                        style="display:none;font-size:0.78rem;color:var(--adm-muted);padding:0 2px 8px;align-items:center;gap:6px;">
                        <span>User is typing</span>
                        <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
                    </div>

                    <div style="display:flex;gap:8px;">
                        <textarea id="replyInput" placeholder="Type your reply…" rows="1"></textarea>
                        <button class="btn btn-danger px-3" id="sendBtn" title="Send reply">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>

        let _cMsgId = null;
        let _cUserName = null;
        const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        let _admTyping = false;
        let _admTypingTimer = null;

        // ── Wire up existing list items ───────────────────────────────────────────────
        document.querySelectorAll('.msg-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const id = this.id.replace('msgItem', '');
                admSelectItem(this);
                admLoadThread(id);
            });
        });

        function admSelectItem(el) {
            document.querySelectorAll('.msg-item').forEach(i => i.classList.remove('active'));
            if (el) el.classList.add('active');
        }

        // ── Load thread from server 
        async function admLoadThread(id) {
            _cMsgId = id;

            window._admCurMsgId = id;

            const item = document.getElementById('msgItem' + id);
            window._admReadByThisTab = window._admReadByThisTab || new Set();
            if (item && item.classList.contains('unread')) {
                window._admReadByThisTab.add(String(id));
                item.classList.remove('unread');
                item.querySelector('.msg-badge-unread')?.remove();
            }

            const body = document.getElementById('chatBody');
            const header = document.getElementById('chatHeader');
            if (body) body.innerHTML =
                '<div class="chat-placeholder"><i class="fa fa-spinner fa-spin fa-2x opacity-25"></i></div>';

            try {
                const resp = await fetch('/admin/contact-messages/' + id + '/thread', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': _csrf
                    }
                });
                const d = await resp.json();

                if (d.marked_count && d.marked_count > 0) {
                    admUpdateBadge(-d.marked_count);
                }

                _cUserName = d.name || 'User';

                // Header — with resolve button or resolved badge
                const resolvedBadge = d.is_resolved ?
                    `<span style="font-size:0.78rem;background:rgba(34,197,94,0.1);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;flex-shrink:0;"><i class="fa fa-circle-check"></i> Resolved</span>` :
                    `<button id="resolveBtn" onclick="admResolveChat(${id})" style="font-size:0.78rem;background:rgba(34,197,94,0.12);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;cursor:pointer;flex-shrink:0;"><i class="fa fa-circle-check"></i> Mark Resolved</button>`;
                if (header) {
                    header.innerHTML =
                        `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div>
                        <div style="font-weight:700;color:#fff;font-size:0.95rem;">${cEsc(d.name)}${!d.is_user ? ' <span style="font-size:0.7rem;background:rgba(99,102,241,0.2);color:#a5b4fc;border-radius:20px;padding:2px 8px;border:1px solid rgba(99,102,241,0.3);margin-left:6px;">Guest</span>' : ''}</div>
                        <div style="font-size:0.78rem;color:var(--adm-muted);margin-top:2px;">${cEsc(d.email)} · ${cEsc(d.time)}</div>
                    </div>
                    ${resolvedBadge}
                </div>
                <div style="font-weight:600;color:rgba(255,255,255,0.85);font-size:0.87rem;margin-top:8px;">${cEsc(d.subject)}</div>`;
                }

                // Body
                if (body) {
                    body.innerHTML = '';
                    body.appendChild(cBubble('user', d.message, d.time, _cUserName));
                    (d.replies || []).forEach(function(r) {
                        body.appendChild(cBubble(r.sender_type, r.message, r.time, r.sender_type === 'user' ?
                            _cUserName : 'Operator'));
                    });
                    body.scrollTop = body.scrollHeight;
                }

                // Footer
                const footer = document.getElementById('chatFooter');
                const resolvedNotice = document.getElementById('resolvedNotice');
                const replyInput = document.getElementById('replyInput');
                const sendBtn = document.getElementById('sendBtn');
                if (footer) footer.style.display = 'flex';

                if (d.is_resolved) {
                    if (resolvedNotice) resolvedNotice.style.display = 'block';
                    if (replyInput) replyInput.style.display = 'none';
                    if (sendBtn) sendBtn.style.display = 'none';
                } else {
                    if (resolvedNotice) resolvedNotice.style.display = 'none';
                    if (replyInput) replyInput.style.display = 'block';
                    if (sendBtn) sendBtn.style.display = 'block';
                }

                // Guest notice
                const notice = document.getElementById('guestEmailNotice');
                const emailEl = document.getElementById('guestEmailAddress');
                if (!d.is_user) {
                    if (emailEl) emailEl.textContent = d.email;
                    if (notice) notice.style.display = 'flex';
                } else {
                    if (notice) notice.style.display = 'none';
                }

                // Mark list item as read
                document.getElementById('msgItem' + id)?.classList.remove('unread');

            } catch (e) {
                if (body) body.innerHTML = '<div class="chat-placeholder">Error loading thread.</div>';
            }
        }

        // ── Build chat bubble with sender name ────────────────────────────────────────
        function cBubble(senderType, message, time, senderName) {
            const isAdmin = senderType === 'admin';
            const label = senderName || (isAdmin ? 'Operator' : 'User');
            const wrap = document.createElement('div');
            wrap.style.cssText =
                `display:flex;flex-direction:column;align-items:${isAdmin ? 'flex-end' : 'flex-start'};gap:2px;`;
            wrap.innerHTML = `
        <div style="font-size:0.7rem;font-weight:600;color:var(--adm-muted);padding:0 4px;">${cEsc(label)}</div>
        <div style="max-width:75%;background:${isAdmin ? 'rgba(215,44,66,0.18)' : 'rgba(255,255,255,0.06)'};
                border:1px solid ${isAdmin ? 'rgba(215,44,66,0.25)' : 'rgba(255,255,255,0.08)'};
                border-radius:12px;padding:10px 14px;font-size:0.88rem;
                color:${isAdmin ? '#fca5a5' : 'rgba(255,255,255,0.85)'};">
            ${cEsc(message)}
        </div>
        <div style="font-size:0.68rem;color:var(--adm-muted);padding:0 4px;">${cEsc(time)}</div>`;
            return wrap;
        }

        function cEsc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                '&quot;');
        }

        // ── Send reply ────────────────────────────────────────────────────────────────
        document.getElementById('sendBtn')?.addEventListener('click', admSendReply);
        document.getElementById('replyInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                admSendReply();
            }
        });

        async function admSendReply() {
            if (!_cMsgId) return;
            const input = document.getElementById('replyInput');
            const msg = input?.value?.trim();
            if (!msg) return;

            const btn = document.getElementById('sendBtn');
            const origHTML = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            }

            clearTimeout(_admTypingTimer);
            _admTyping = false;

            try {
                const resp = await fetch('/admin/contact-messages/' + _cMsgId + '/reply', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': _csrf,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message: msg
                    }),
                });
                const data = await resp.json();
                if (data.success) {
                    input.value = '';
                    input.style.height = 'auto';
                    const body = document.getElementById('chatBody');
                    if (body) {
                        body.appendChild(cBubble('admin', msg, data.reply.time, 'Operator'));
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

        // ── Real-time: new contact message prepended to list ─────────────────────────
        function admContactMsgPrepend(e) {
            const list = document.getElementById('msgList');
            if (!list) return;

            const div = document.createElement('div');
            div.className = 'msg-item unread';
            div.id = 'msgItem' + e.message_id;
            div.setAttribute('data-search', ((e.name || '') + ' ' + (e.subject || '') + ' ' + (e.email || ''))
            .toLowerCase());
            div.setAttribute('data-type', e.user_id ? 'user' : 'guest');
            div.addEventListener('click', function() {
                admSelectItem(this);
                admLoadThread(e.message_id);
            });
            div.innerHTML =
                `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="min-width:0;">
                <div class="fw-bold text-white" style="font-size:0.85rem;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">
                    ${cEsc(e.name)}
                    <span class="msg-badge-unread">new</span>
                    ${!e.user_id ? '<span class="msg-badge-guest">Guest</span>' : ''}
                </div>
                <div style="font-size:0.78rem;color:var(--adm-muted);text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">${cEsc(e.subject)}</div>
            </div>
            <div style="font-size:0.7rem;color:var(--adm-muted);white-space:nowrap;flex-shrink:0;">${cEsc(e.date_short)}</div>
        </div>
        <div style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-top:4px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">${cEsc((e.message||'').substring(0,55))}</div>`;
            list.insertBefore(div, list.firstChild);
        }

        // ── Real-time: user sent follow-up reply — append to current thread ───────────
        function admContactThreadReply(e) {
            if (String(_cMsgId) !== String(e.contact_message_id)) return;
            const body = document.getElementById('chatBody');
            if (!body) return;
            body.appendChild(cBubble('user', e.message || e.preview, e.time, e.user_name || _cUserName || 'User'));
            body.scrollTop = body.scrollHeight;
        }

        // ── Real-time: mark a list item with a "reply" badge when a user replies ──────
        function admAddReplyBadge(msgId) {
            const item = document.getElementById('msgItem' + msgId);
            if (!item) return;
            // If the admin already has that thread open, don't show an indicator
            if (item.classList.contains('active')) return;
            // Avoid duplicating the badge
            if (!item.classList.contains('unread')) {
                item.classList.add('unread');
                const badge = document.createElement('span');
                badge.className = 'msg-badge-unread msg-badge-reply';
                badge.textContent = 'Reply';
                // Insert just after the name element so it appears inline with the name
                const nameEl = item.querySelector('.fw-bold');
                if (nameEl) nameEl.appendChild(badge);
                else item.insertBefore(badge, item.firstChild);
            }
        }

        // ── Auto-resize + typing indicator 
        document.getElementById('replyInput')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';

            if (!_cMsgId) return;
            clearTimeout(_admTypingTimer);
            if (!_admTyping) {
                _admTyping = true;
                fetch('/admin/contact-messages/' + _cMsgId + '/typing', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': _csrf
                    }
                }).catch(function() {});
            }
            _admTypingTimer = setTimeout(function() {
                _admTyping = false;
            }, 2500);
        });

        // ── Resolve chat 
        async function admResolveChat(id) {
            const btn = document.getElementById('resolveBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Resolving…';
            }
            try {
                const resp = await fetch('/admin/contact-messages/' + id + '/resolve', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': _csrf,
                        'Accept': 'application/json'
                    }
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
            const btn = document.getElementById('resolveBtn');
            if (btn) {
                const badge = document.createElement('span');
                badge.style.cssText =
                    'font-size:0.78rem;background:rgba(34,197,94,0.1);color:#86efac;border:1.5px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 12px;font-weight:600;flex-shrink:0;';
                badge.innerHTML = '<i class="fa fa-circle-check"></i> Resolved';
                btn.replaceWith(badge);
            }
            const replyInput = document.getElementById('replyInput');
            const sendBtn = document.getElementById('sendBtn');
            if (replyInput) replyInput.style.display = 'none';
            if (sendBtn) sendBtn.style.display = 'none';
            const notice = document.getElementById('resolvedNotice');
            if (notice) notice.style.display = 'block';
            const listItem = document.getElementById('msgItem' + id);
            if (listItem) listItem.style.opacity = '0.65';
        }

        // ── Search / filter (existing UI hook) 
        document.getElementById('msgSearch')?.addEventListener('input', filterMessages);
        document.querySelectorAll('.msg-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.msg-filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterMessages();
            });
        });

        function filterMessages() {
            const q = (document.getElementById('msgSearch')?.value || '').toLowerCase();
            const active = document.querySelector('.msg-filter-btn.active')?.id || 'filterAll';
            document.querySelectorAll('.msg-item').forEach(function(item) {
                const matchQ = !q || (item.getAttribute('data-search') || '').includes(q);
                const t = item.getAttribute('data-type') || '';
                const matchT = active === 'filterAll' || (active === 'filterUser' && t === 'user') || (active === 'filterGuest' && t === 'guest');
                item.style.display = matchQ && matchT ? '' : 'none';
            });
        }
    </script>
@endpush
