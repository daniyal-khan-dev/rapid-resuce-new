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
            rideChatPage:   "{{ route('driver.ride-chats') }}",
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
                var nonce = e.dispatch_token || ('dsp_' + e.request_id + '_' + Date.now());
                if (!window._driLayoutProcessed) window._driLayoutProcessed = new Set();
                if (window._driLayoutProcessed.has(nonce)) return;
                window._driLayoutProcessed.add(nonce);
                if (window._driLayoutProcessed.size > 50) {
                    window._driLayoutProcessed.delete(window._driLayoutProcessed.values().next().value);
                }

                if (typeof window.driSetRequestBadge === 'function') {
                    var _newBadge = (typeof e.pending_count === 'number')
                        ? e.pending_count
                        : (window.driGetRequestBadge ? window.driGetRequestBadge() + 1 : 1);
                    window.driSetRequestBadge(_newBadge);
                }

                if (typeof window.driInsertDispatchRequestRow === 'function') {
                    window.driInsertDispatchRequestRow(e);
                }
                if (typeof window.driLoadActive === 'function') {
                    window.driLoadActive();
                }

                console.log('[Reverb] dispatch.request.sent → grid updated:', e.rreb_id || e.request_id);
            });

            window._driChannel = ch;

            ch.bind('pusher:subscription_error', function(err) {
                console.warn('[Reverb] Driver channel auth error:', err);
            });
            ch.bind('pusher:subscription_succeeded', function() {
                console.log('[Reverb] Subscribed to private-driver.' + window._rrReverb.driverId);
            });

            ch.bind('request.status.updated', function(e) {
                var s = String(e.status || '');
                if (s !== '6' && s !== '7') return;

                if (typeof window.driDecrementRequestBadge === 'function') {
                    window.driDecrementRequestBadge();
                }

                if (typeof window.driAddPastRideRow === 'function') {
                    window.driAddPastRideRow(e);
                }

                if (typeof window.driRemoveRequestRow === 'function') {
                    window.driRemoveRequestRow(e);
                }

                if (typeof window.driBroadcastTabSync === 'function') {
                    window.driBroadcastTabSync({ type: 'ride_moved_to_past', req: e });
                }

                console.log('[Reverb] request.status.updated → status', s, '→ badge decremented for request', e.request_id);
            });

            ch.bind('ride.chat.message', function(e) {
                if (e.sender_type === 'driver') return;

                if (typeof window.driRideChatMessageReceived === 'function') {
                    window.driRideChatMessageReceived(e);
                } else {
                    if (typeof window.driIncrementChatBadge === 'function') {
                        window.driIncrementChatBadge();
                    }
                }
            });

            ch.bind('ride.chat.typing', function(e) {
                if (typeof window.driRideChatTypingReceived === 'function') {
                    window.driRideChatTypingReceived(e);
                }
            });
        })();

        // ── Requests nav badge ────────────────────────────────────────────────
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

        // ── Cross-tab synchronisation (BroadcastChannel) ──────────────────────
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
                        if (typeof window.driAddPastRideRow === 'function') {
                            window.driAddPastRideRow(d.req);
                        }
                        if (typeof window.driRemoveRequestRow === 'function') {
                            window.driRemoveRequestRow(d.req);
                        }
                    }

                    if (d.type === 'active_refresh') {
                        if (typeof window.driLoadActive   === 'function') window.driLoadActive();
                        if (typeof window.driRefreshStats === 'function') window.driRefreshStats();
                    }

                    if (d.type === 'location_updated') {
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
