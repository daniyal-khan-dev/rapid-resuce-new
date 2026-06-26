<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Driver Portal') — Rapid Rescue</title>

    {{-- FAVICONS --}}
    <link rel="icon" type="image/png" href="{{ asset('assets/user/img/logo/logo.png') }}">

    {{-- BOOTSTRAP CSS --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    {{-- FONTAWESOME --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- GOOGLE FONTS --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- CUSTOM CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/driver/css/driver.css') }}">
    @stack('styles')
    <style>
        /* ── Notification Bell ─────────────────────────────────────────────── */
        .notif-bell-wrap { position: relative; }
        .btn-notif-bell {
            background: transparent; border: none; cursor: pointer; padding: 6px 8px;
            color: rgba(255,255,255,.70); font-size: 1.05rem; position: relative;
            display: flex; align-items: center; justify-content: center; border-radius: 8px;
            transition: color .15s, background .15s;
        }
        .btn-notif-bell:hover { color: #fff; background: rgba(255,255,255,.08); }
        .notif-bell-badge {
            position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px;
            border-radius: 8px; background: #ef4444; color: #fff;
            font-size: .60rem; font-weight: 700; line-height: 1;
            display: flex; align-items: center; justify-content: center; padding: 0 3px;
            pointer-events: none; box-shadow: 0 0 0 2px #0e1728;
        }
        .notif-bell-dropdown {
            position: absolute; top: calc(100% + 8px); right: 0;
            width: 320px; background: #1a2540; border: 1px solid rgba(255,255,255,.10);
            border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.45); z-index: 1060;
            overflow: hidden;
        }
        .notif-bell-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.07);
            font-size: .82rem; font-weight: 600; color: #e2e8f0;
        }
        .notif-bell-clear-all {
            background: transparent; border: none; cursor: pointer; padding: 3px 8px;
            font-size: .74rem; color: #60a5fa; font-weight: 500; border-radius: 5px;
            transition: background .15s;
        }
        .notif-bell-clear-all:hover { background: rgba(96,165,250,.10); }
        .notif-bell-list { max-height: 340px; overflow-y: auto; padding: 4px 0; }
        .notif-bell-list::-webkit-scrollbar { width: 4px; }
        .notif-bell-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.10); border-radius: 2px; }
        .notif-bell-empty {
            display: flex; align-items: center; gap: 8px;
            padding: 24px 16px; color: rgba(255,255,255,.35); font-size: .82rem;
            justify-content: center;
        }
        .notif-bell-item { list-style: none; cursor: pointer; }
        .notif-bell-item-inner {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.04);
            transition: background .15s; position: relative;
        }
        .notif-bell-item:hover .notif-bell-item-inner { background: rgba(255,255,255,.05); }
        .notif-bell-item--unread .notif-bell-item-inner { background: rgba(59,130,246,.06); }
        .notif-bell-icon {
            margin-top: 2px; font-size: .88rem; min-width: 22px; text-align: center;
        }
        .notif-bell-icon--admin { color: #f59e0b; }
        .notif-bell-icon--user  { color: #60a5fa; }
        .notif-bell-item-body { flex: 1; min-width: 0; }
        .notif-bell-item-title {
            font-size: .80rem; font-weight: 600; color: #e2e8f0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .notif-bell-item-rreb { font-weight: 400; color: rgba(255,255,255,.45); font-size: .75rem; }
        .notif-bell-item-preview {
            font-size: .76rem; color: rgba(255,255,255,.55); margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .notif-bell-item-time { font-size: .70rem; color: rgba(255,255,255,.30); margin-top: 3px; }
        .notif-bell-footer { border-top: 1px solid rgba(255,255,255,.06); padding: 9px 14px; text-align: center; }
        .notif-bell-view-all { font-size: .74rem; font-weight: 600; color: #60a5fa; text-decoration: none; }
        .notif-bell-view-all:hover { text-decoration: underline; }
        .notif-bell-icon--assign { color: #a78bfa; }
        .notif-bell-dot {
            position: absolute; top: 50%; right: 14px; transform: translateY(-50%);
            width: 7px; height: 7px; border-radius: 50%; background: #3b82f6; flex-shrink: 0;
        }
    </style>
    <style>
        .pgd-scroll { overflow-y:auto; overflow-x:auto; max-height:364px; }
        .pgd-scroll--list { max-height:448px; }
        .pgd-scroll::-webkit-scrollbar { width:5px; height:5px; }
        .pgd-scroll::-webkit-scrollbar-track { background:transparent; }
        .pgd-scroll::-webkit-scrollbar-thumb { background:rgba(255,255,255,.12); border-radius:3px; }
        .pgd-scroll thead th { position:sticky; top:0; z-index:3; background:#0e1728 !important; box-shadow:0 1px 0 rgba(255,255,255,.07); }
        .pgd-footer { display:flex; align-items:center; justify-content:space-between; padding:10px 20px; gap:10px; flex-wrap:wrap; border-top:1px solid rgba(255,255,255,.07); background:rgba(255,255,255,.02); }
        .pgd-info { font-size:.78rem; color:rgba(255,255,255,.40); }
        .pgd-controls { display:flex; align-items:center; gap:8px; }
        .pgd-pages { font-size:.78rem; color:rgba(255,255,255,.40); min-width:94px; text-align:center; }
        .pgd-btn { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10); color:#e2e8f0; border-radius:6px; padding:5px 14px; font-size:.78rem; font-weight:500; cursor:pointer; transition:background .15s,border-color .15s; line-height:1.5; font-family:inherit; }
        .pgd-btn:hover:not(:disabled) { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.22); }
        .pgd-btn:disabled { opacity:.32; cursor:not-allowed; }

        /* ── Requests nav badge ─────────────────────────────────── */
        .dri-req-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ef4444;
            color: #fff;
            font-size: .60rem;
            font-weight: 700;
            min-width: 17px;
            height: 17px;
            border-radius: 9px;
            padding: 0 4px;
            margin-left: auto;
            line-height: 1;
            pointer-events: none;
            animation: driReqBadgePop .2s ease;
        }
        @keyframes driReqBadgePop {
            0%   { transform: scale(.6); opacity: 0; }
            80%  { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

    </style>
</head>

<body>
    {{-- Success Toast --}}
    <div class="dri-toast dri-toast--success" id="driToastSuccess">
        <i class="fa fa-circle-check"></i>
        <span class="dri-toast-text"></span>
    </div>

    {{-- Error Toast --}}
    <div class="dri-toast dri-toast--error" id="driToastError">
        <i class="fa fa-circle-exclamation"></i>
        <span class="dri-toast-text"></span>
    </div>

    {{-- Sidebar overlay (mobile) --}}
    <div class="dri-sidebar-overlay" id="driSidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- Sidebar --}}
    <aside class="dri-sidebar" id="driSidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('assets/user/img/logo/logo.png') }}" alt="Rapid Rescue">
            <div>
                <strong>Rapid Rescue</strong>
                <small>Driver Panel</small>
            </div>
            <button class="btn-sidebar-close" onclick="toggleSidebar()" aria-label="Close menu">
                <i class="fa fa-xmark"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('driver.dashboard') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.dashboard') ? 'active' : '' }}">
                <i class="fa fa-gauge-high"></i> Dashboard
            </a>

            <div class="sidebar-section-label">My Work</div>
            <a href="{{ route('driver.requests') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.requests') ? 'active' : '' }}">
                <i class="fa fa-truck-medical"></i> Requests
                <span id="driReqNavBadge" class="dri-req-badge" style="display:none;"></span>
            </a>

            <a href="{{ route('driver.past-rides') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.past-rides') ? 'active' : '' }}">
                <i class="fa fa-clock-rotate-left"></i> Past Rides
            </a>

            <a href="{{ route('driver.ride-chats') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.ride-chats') ? 'active' : '' }}">
                <i class="fa fa-comments"></i> Ride Chats
                <span id="driChatNavBadge"
                      style="display:none;margin-left:auto;background:#3b82f6;color:#fff;font-size:0.68rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;align-items:center;justify-content:center;padding:0 5px;line-height:1;"></span>
            </a>

            <a href="{{ route('driver.ride-chats.notifHistory') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.ride-chats.notifHistory') ? 'active' : '' }}">
                <i class="fa fa-bell"></i> Notification History
            </a>

            <div class="sidebar-section-label">Account</div>
            <a href="{{ route('driver.profile') }}"
                class="sidebar-nav-link {{ request()->routeIs('driver.profile') ? 'active' : '' }}">
                <i class="fa fa-circle-user"></i> My Profile
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-driver-info">
                <div class="sidebar-driver-avatar"><i class="fa fa-id-card"></i></div>
                <div>
                    <strong>{{ Auth::guard('driver')->user()->name }}</strong>
                    <small>{{ Auth::guard('driver')->user()->username }}</small>
                </div>
            </div>

            <form method="POST" action="{{ route('driver.logout') }}">
                @csrf
                <button type="submit" class="btn-sidebar-logout">
                    <i class="fa fa-right-from-bracket"></i> Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="dri-main">
        <header class="dri-topbar">
            <button class="btn-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i class="fa fa-bars"></i>
            </button>
            <span class="dri-topbar__title">@yield('page_title', 'Dashboard')</span>

            <div class="d-flex align-items-center gap-2">
                {{-- Notification Bell --}}
                <div class="notif-bell-wrap" id="driNotifBellWrap">
                    <button class="btn-notif-bell" id="driNotifBellBtn" title="Chat Notifications" aria-label="Chat Notifications">
                        <i class="fa fa-bell"></i>
                        <span class="notif-bell-badge" id="driNotifBellBadge" style="display:none;"></span>
                    </button>
                    <div class="notif-bell-dropdown" id="driNotifBellDropdown" style="display:none;">
                        <div class="notif-bell-header">
                            <span>Notifications</span>
                            <button class="notif-bell-clear-all" id="driNotifBellMarkAll">Mark all read</button>
                        </div>
                        <ul class="notif-bell-list" id="driNotifBellList">
                            <li class="notif-bell-empty"><i class="fa fa-bell-slash"></i><span>No notifications</span></li>
                        </ul>
                        <div class="notif-bell-footer">
                            <a href="{{ route('driver.ride-chats.notifHistory') }}" class="notif-bell-view-all">View all notifications</a>
                        </div>
                    </div>
                </div>

                <div class="dri-topbar__avatar"><i class="fa fa-id-card"></i></div>
                <div class="d-none d-md-block">
                    <div class="topbar-driver-name">{{ Auth::guard('driver')->user()->name }}</div>
                    <div class="topbar-driver-role">Driver</div>
                </div>
            </div>
        </header>

        <div class="dri-content">
            @yield('content')
        </div>

        {{-- Footer --}}
        <footer class="dri-footer">
            <span class="dri-footer-copy">
                &copy; {{ date('Y') }} <strong>Rapid Rescue</strong>. All rights reserved.
            </span>
            <span class="dri-footer-tagline">Ambulance Dispatch System</span>
        </footer>
    </main>

    {{-- BOOTSTRAP JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Pusher JS (for Reverb real-time) --}}
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    {{-- Driver routes for dashboard/location/session JS --}}
    <script>
        @php $driDriver = Auth::guard('driver')->user(); @endphp
        window._driRoutes = {
            requestActive:       "{{ route('driver.requests.active') }}",
            requestPendingNearby:"{{ route('driver.requests.pendingNearby') }}",
            requestStatus:  "{{ url('/driver/requests') }}/:id/status",
            requestAccept:  "{{ url('/driver/requests') }}/:id/accept",
            requestReject:  "{{ url('/driver/requests') }}/:id/reject",
            availability:   "{{ route('driver.availability') }}",
            statsUrl:       "{{ route('driver.requests.stats') }}",
            locationUpdate: "{{ route('driver.location.update') }}",
            requestsPage:   "{{ route('driver.requests') }}",
            pastRidesPage:  "{{ route('driver.past-rides') }}",
            heartbeat:      "{{ route('driver.heartbeat') }}",
            tabClose:       "{{ route('driver.tabClose') }}",
            rideChatPage:        "{{ route('driver.ride-chats') }}",
            rideChatNotifBell:   "{{ route('driver.ride-chats.notifBell') }}",
            rideChatNotifRead:   "{{ url('/driver/ride-chats/notif') }}/:id/read",
            rideChatNotifsRead:  "{{ url('/driver/ride-chats') }}/:requestId/notifs-read",
            assignmentNotifRead:          "{{ url('/driver/ride-chats/assignment-notif') }}/:id/read",
            assignmentNotifByRequestRead: "{{ url('/driver/ride-chats/assignment-notif/by-request') }}/:requestId/read",
            markAllNotifsRead:   "{{ route('driver.ride-chats.markAllNotifsRead') }}",
            notifHistoryPage:    "{{ route('driver.ride-chats.notifHistory') }}",
        };

        window._rrReverb = window._rrReverb || {};
        window._rrReverb.driverId     = {{ $driDriver->id }};
        window._rrReverb.driverStatus = '{{ $driDriver->status }}';

        @php
            $rrWsHost   = env('REVERB_HOST');
            $rrWsPort   = (int) env('REVERB_PORT');
            $rrForceTLS = env('REVERB_SCHEME', 'http') === 'https';
        @endphp

        Pusher.logToConsole = false;

        window._driPusher = new Pusher('{{ env("REVERB_APP_KEY", "local") }}', {
            wsHost:            "{{ $rrWsHost }}",
            wsPort:            {{ $rrWsPort }},
            wssPort:           {{ $rrWsPort }},
            forceTLS:          {{ $rrForceTLS ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
            disableStats:      true,
            cluster:           'mt1',
            authEndpoint:      '/driver/broadcasting/auth',
            auth: {
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }
        });

        (function() {
            var ch = window._driPusher.subscribe('private-driver.' + window._rrReverb.driverId);

            ch.bind('dispatch.request.sent', function(e) {
                // Deduplicate rapid duplicate WebSocket deliveries of the exact same
                // event (e.g. two browser tabs open simultaneously).
                // We key on dispatch_token — a UUID generated fresh on the server for
                // every single dispatch action — so a re-dispatch of the same request
                // after a rejection always carries a brand-new token and is NEVER blocked.
                var nonce = e.dispatch_token || ('dsp_' + e.request_id + '_' + Date.now());
                if (!window._driLayoutProcessed) window._driLayoutProcessed = new Set();
                if (window._driLayoutProcessed.has(nonce)) return;
                window._driLayoutProcessed.add(nonce);
                // Keep the set small — only remember the last 50 tokens
                if (window._driLayoutProcessed.size > 50) {
                    window._driLayoutProcessed.delete(window._driLayoutProcessed.values().next().value);
                }

                // ── Requests nav badge ─────────────────────────────────────────
                // Use the server-authoritative count from the event payload instead
                // of a local increment.  With multiple tabs open the increment races:
                // both tabs read the same localStorage value and each adds 1, ending
                // up one too high.  The server embeds the exact count at dispatch
                // time so we set it directly and broadcast to all other tabs.
                if (typeof window.driSetRequestBadge === 'function') {
                    var _newBadge = (typeof e.pending_count === 'number')
                        ? e.pending_count
                        : (window.driGetRequestBadge ? window.driGetRequestBadge() + 1 : 1);
                    window.driSetRequestBadge(_newBadge);
                }

                // ── Silently insert/update the requests grid (no modal) ────────
                if (typeof window.driInsertDispatchRequestRow === 'function') {
                    window.driInsertDispatchRequestRow(e);
                }
                if (typeof window.driLoadActive === 'function') {
                    window.driLoadActive();
                }

                // ── Notification bell / auto-read ─────────────────────────────
                // If the driver already has the Requests page open, the new row
                // is immediately visible in the table — auto-mark the assignment
                // notification as read so the bell badge does NOT increment.
                // On any other page, call onNewDispatch() as normal.
                if (window._driOnRequestsPage) {
                    var _arUrl = ((window._driRoutes || {}).assignmentNotifByRequestRead || '')
                        .replace(':requestId', e.request_id);
                    if (_arUrl) {
                        var _arCsrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                        fetch(_arUrl, {
                            method:  'POST',
                            headers: { 'X-CSRF-TOKEN': _arCsrf, 'X-Requested-With': 'XMLHttpRequest' },
                        })
                        .then(function() {
                            // Re-fetch the bell so the dropdown list stays fresh
                            // without the unread count going up.
                            if (window.driNotifBell && typeof window.driNotifBell.onNotifsRead === 'function') {
                                window.driNotifBell.onNotifsRead();
                            }
                        })
                        .catch(function() {});
                    }
                } else {
                    // Not on the requests page — show the notification normally.
                    if (window.driNotifBell && typeof window.driNotifBell.onNewDispatch === 'function') {
                        window.driNotifBell.onNewDispatch();
                    }
                }

                console.log('[Reverb] dispatch.request.sent → grid + bell updated:', e.rreb_id || e.request_id);
            });

            window._driChannel = ch;

            ch.bind('pusher:subscription_error', function(err) {
                console.warn('[Reverb] Driver channel auth error:', err);
            });
            ch.bind('pusher:subscription_succeeded', function() {
                console.log('[Reverb] Subscribed to private-driver.' + window._rrReverb.driverId);
            });

            // ── Real-time ride completion / cancellation ───────────────────────
            // When the driver marks a ride Completed(6) or Cancelled(7) the server
            // fires RequestStatusUpdated which broadcasts on this private channel.
            // We handle it here in the layout so it fires regardless of which page
            // the driver currently has open.
            ch.bind('request.status.updated', function(e) {
                var s = String(e.status || '');
                if (s !== '6' && s !== '7') return;

                // ── Decrement the nav badge on completion or cancellation ──────────
                // This is the single source of truth for badge decrements on terminal
                // statuses. The floor in driSetRequestBadge (Math.max(0, …)) ensures
                // a cross-tab race can never push the count below zero.
                if (typeof window.driDecrementRequestBadge === 'function') {
                    window.driDecrementRequestBadge();
                }

                // If the Past Rides page is open in this tab, prepend the row.
                if (typeof window.driAddPastRideRow === 'function') {
                    window.driAddPastRideRow(e);
                }

                // If the Active Requests page is open in this tab, remove the row.
                if (typeof window.driRemoveRequestRow === 'function') {
                    window.driRemoveRequestRow(e);
                }

                // Broadcast to every other driver tab (different page in another tab).
                if (typeof window.driBroadcastTabSync === 'function') {
                    window.driBroadcastTabSync({ type: 'ride_moved_to_past', req: e });
                }

                console.log('[Reverb] request.status.updated → status', s, '→ badge decremented for request', e.request_id);
            });

            // ── Ride Chat: new message ─────────────────────────────────────────
            // Forwards ride.chat.message events to the Ride Chats page handler.
            // The handler (driRideChatMessageReceived) is only defined when the
            // driver has the Ride Chats page open; the typeof guard is safe here.
            ch.bind('ride.chat.message', function(e) {
                if (e.sender_type === 'driver') return;

                if (typeof window.driRideChatMessageReceived === 'function') {
                    // Ride Chats page is open — it handles per-ride badge AND nav badge internally.
                    window.driRideChatMessageReceived(e);
                } else {
                    // On any other driver page — just bump the nav badge directly.
                    if (typeof window.driIncrementChatBadge === 'function') {
                        window.driIncrementChatBadge();
                    }
                }

                // ── Notification bell ─────────────────────────────────────────
                // Always increment the badge so the driver gets immediate visual
                // feedback regardless of which page they are on.
                if (typeof window.driNotifBell !== 'undefined' && window.driNotifBell) {
                    window.driNotifBell.onNewMessage();
                }

                // When the driver has this exact conversation open, also auto-mark
                // the notification as read server-side so the notification history
                // stays clean.  The server will then broadcast ride.chat.notif.read
                // which triggers onNotifsRead() → re-fetches the bell → badge returns
                // to the correct (lower) count automatically.
                var _chatIsOpen = window._rcCurrentRequestId &&
                    String(window._rcCurrentRequestId) === String(e.request_id);
                if (_chatIsOpen) {
                    var _nrUrl = ((window._driRoutes || {}).rideChatNotifsRead || '')
                        .replace(':requestId', e.request_id);
                    if (_nrUrl) {
                        var _csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                        fetch(_nrUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': _csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        })
                        .then(function () {
                            // Sync to other open driver tabs so they know this
                            // notification has been read (BroadcastChannel does NOT
                            // deliver to the sender tab, so this is safe here).
                            try {
                                var _bc = new BroadcastChannel('rr_driver_notif_sync');
                                _bc.postMessage({ type: 'notif_read', request_id: e.request_id });
                                _bc.close();
                            } catch (_ex) {}
                        })
                        .catch(function () {});
                    }
                }
            });

            // ── Ride Chat: notifications read (another tab cleared them) ──────
            ch.bind('ride.chat.notif.read', function(e) {
                if (e.recipient_type !== 'driver') return;
                if (typeof window.driNotifBell !== 'undefined' && window.driNotifBell) {
                    window.driNotifBell.onNotifsRead();
                }
                // Update the Ride Chats sidebar badge for this request (if open).
                if (typeof window.driRideChatNotifsRead === 'function') {
                    window.driRideChatNotifsRead(e.request_id);
                }
            });

            // ── Ride Chat: typing indicator ────────────────────────────────────
            ch.bind('ride.chat.typing', function(e) {
                if (typeof window.driRideChatTypingReceived === 'function') {
                    window.driRideChatTypingReceived(e);
                }
            });
        })();

        // ── Requests nav badge ────────────────────────────────────────────────
        // Ground truth: localStorage key per driver.
        // driRenderRequestBadge() — DOM-only, no storage write, no cross-tab broadcast.
        // driSetRequestBadge(n)   — writes storage, updates DOM, broadcasts to other tabs.
        (function() {
            var _badgeKey = 'rr_drv_req_badge_' + {{ $driDriver->id }};

            window.driRenderRequestBadge = function(n) {
                n = Math.max(0, parseInt(n, 10) || 0);
                var el = document.getElementById('driReqNavBadge');
                if (!el) return;
                if (n > 0) {
                    el.textContent = n > 99 ? '99+' : String(n);
                    el.style.display = '';
                    el.style.animation = 'none';
                    void el.offsetWidth;
                    el.style.animation = 'driReqBadgePop .2s ease';
                } else {
                    el.style.display = 'none';
                }
            };

            window.driGetRequestBadge = function() {
                return Math.max(0, parseInt(localStorage.getItem(_badgeKey) || '0', 10) || 0);
            };

            window.driSetRequestBadge = function(n) {
                n = Math.max(0, parseInt(n, 10) || 0);
                localStorage.setItem(_badgeKey, String(n));
                window.driRenderRequestBadge(n);
                if (typeof window.driBroadcastTabSync === 'function') {
                    window.driBroadcastTabSync({ type: 'request_badge_update', count: n });
                }
            };

            window.driIncrementRequestBadge = function() {
                window.driSetRequestBadge(window.driGetRequestBadge() + 1);
            };

            window.driDecrementRequestBadge = function() {
                window.driSetRequestBadge(window.driGetRequestBadge() - 1);
            };

            // ── Server-side badge initialisation ─────────────────────────────
            // On every page load we set the badge from the server's live count of
            // active dispatch requests for this driver.  This is authoritative —
            // it fixes the race where the driver navigates to a new page before
            // the WebSocket dispatch event arrives, causing the increment to be lost.
            // After this baseline is set, WebSocket events adjust it in real-time.
            @php
                $activeBadgeCount = \App\Models\EmergencyRequest
                    ::where('driver_id', $driDriver->id)
                    ->whereIn('status', ['2', '3', '4', '5', '8'])
                    ->count();
            @endphp

            document.addEventListener('DOMContentLoaded', function() {
                window.driSetRequestBadge({{ $activeBadgeCount }});
            });
        })();

        // ── Ride Chat nav badge ────────────────────────────────────────────────
        // Mirrors the same pattern as the request badge above, but for chat.
        // driSetChatBadge is also called by the tab-sync handler (line below).
        (function () {
            @php
                $driChatNavCount = \App\Models\RideChatMessage::whereHas(
                    'emergencyRequest', fn($q) => $q->where('driver_id', $driDriver->id)
                )->where('is_read_driver', false)->where('sender_type', '!=', 'driver')->count();
            @endphp
            var _chatCount = {{ $driChatNavCount }};

            window.driSetChatBadge = function (n) {
                n = Math.max(0, parseInt(n, 10) || 0);
                _chatCount = n;
                var el = document.getElementById('driChatNavBadge');
                if (!el) return;
                if (n > 0) {
                    el.textContent   = n > 99 ? '99+' : String(n);
                    el.style.display = 'inline-flex';
                } else {
                    el.style.display = 'none';
                }
            };

            window.driIncrementChatBadge = function () { window.driSetChatBadge(_chatCount + 1); };
            window.driDecrementChatBadge = function (by) { window.driSetChatBadge(_chatCount - (parseInt(by, 10) || 1)); };

            document.addEventListener('DOMContentLoaded', function () {
                window.driSetChatBadge({{ $driChatNavCount }});
            });
        })();

        // ── driMarkReadByRequest ───────────────────────────────────────────────
        // Mark all unread assignment notifications for a given request as read.
        // Called by: viewRequestDetail() in requests.blade.php when the driver
        // opens a request detail that has an unread notification attached.
        window.driMarkReadByRequest = function(requestId) {
            var _url = ((window._driRoutes || {}).assignmentNotifByRequestRead || '')
                .replace(':requestId', requestId);
            if (!_url) return;
            var _csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            fetch(_url, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': _csrf, 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.marked > 0 && window.driNotifBell &&
                    typeof window.driNotifBell.onNotifsRead === 'function') {
                    window.driNotifBell.onNotifsRead();
                }
            })
            .catch(function() {});
        };

        // ── Cross-tab synchronisation (BroadcastChannel) ──────────────────────
        // driBroadcastTabSync sends a message to every other open driver tab so
        // they can react without their own WebSocket event copy.
        (function() {
            var _bc;
            try { _bc = new BroadcastChannel('rr_driver_sync'); } catch(e) {}

            window.driBroadcastTabSync = function(msg) {
                if (!_bc) return;
                try { _bc.postMessage(msg); } catch(ex) {}
            };

            if (_bc) {
                _bc.onmessage = function(ev) {
                    var d = ev.data;
                    if (!d || !d.type) return;

                    if (d.type === 'ride_moved_to_past') {
                        // Past Rides tab: add the new row
                        if (typeof window.driAddPastRideRow === 'function') {
                            window.driAddPastRideRow(d.req);
                        }
                        // Active Requests tab: remove the row
                        if (typeof window.driRemoveRequestRow === 'function') {
                            window.driRemoveRequestRow(d.req);
                        }
                    }

                    if (d.type === 'active_refresh') {
                        if (typeof window.driLoadActive   === 'function') window.driLoadActive();
                        if (typeof window.driRefreshStats === 'function') window.driRefreshStats();
                    }

                    if (d.type === 'location_updated') {
                        // Another driver tab just got a GPS fix — relay it here so
                        // the self-view map on this tab stays in sync without needing
                        // its own separate GPS callback.
                        try {
                            document.dispatchEvent(new CustomEvent('driLocationUpdated', {
                                detail: { lat: d.lat, lng: d.lng }
                            }));
                        } catch(ex) {}
                    }

                    if (d.type === 'ride_chat_badge_set') {
                        if (typeof window.driSetChatBadge === 'function') window.driSetChatBadge(d.value);
                    }

                    if (d.type === 'request_badge_update') {
                        // Another tab changed the count — sync display only (no re-broadcast)
                        if (typeof window.driRenderRequestBadge === 'function') {
                            window.driRenderRequestBadge(d.count);
                        }
                    }
                };
            }
        })();
    </script>

    {{-- CUSTOM JS --}}
    <script src="{{ asset('assets/driver/js/script.js') }}"></script>
    <script src="{{ asset('assets/driver/js/dashboard.js') }}"></script>
    <script src="{{ asset('assets/driver/js/location.js') }}"></script>
    <script src="{{ asset('assets/driver/js/session.js') }}"></script>
    <script src="{{ asset('assets/driver/js/notificaiton.js') }}"></script>

    {{-- Flash session toasts --}}
    @if(session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof driToastSuccess === 'function') driToastSuccess("{{ session('success') }}");
        });
    </script>
    @endif
    @if($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof driToastError === 'function') driToastError("{{ $errors->first() }}");
        });
    </script>
    @endif

    @stack('scripts')
    @stack('modals')
</body>

</html>
