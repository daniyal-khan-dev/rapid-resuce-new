<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Rapid Rescue Admin</title>

    {{-- FAVICONS --}}
    <link rel="icon" type="image/png" href="{{ asset('assets/user/img/logo/logo.png') }}">

    {{-- BOOTSTRAP CSS - CDN --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    {{-- FONTAWESOME CSS - CDN --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- GOOGLE FONTS - CDN --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- CUSTOM CSS - CDN --}}
    <link rel="stylesheet" href="{{ asset('assets/admin/css/admin.css') }}">
    @stack('styles')
</head>

<body>
    {{-- Delete Confirm Modal --}}
    <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered dlt-modal-dia">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="icon-div">
                        <i class="fa fa-triangle-exclamation"></i>
                    </div>
                    <h5>Delete Confirmation</h5>
                    <p id="globalConfirmMsg">Are you sure you want to delete this? This action cannot be undone.</p>
                    <div class="btn-div">
                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="globalConfirmOkBtn" class="btn btn-danger btn-sm px-4">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Succcess alert --}}
    <div class="adm-toast adm-toast--success" id="admSuccessAlert">
        <i class="fa fa-circle-check"></i>
        <span id="admSuccessAlertText"></span>
    </div>

    {{-- Error alert --}}
    <div class="adm-toast adm-toast--error" id="admErrorAlert">
        <i class="fa fa-circle-exclamation"></i>
        <span id="admErrorAlertText"></span>
    </div>

    {{-- Sidebar overlay (mobile) --}}
    <div class="adm-sidebar-overlay" id="admSidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- Sidebar --}}
    <aside class="adm-sidebar" id="admSidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('assets/user/img/logo/logo.png') }}" alt="Rapid Rescue">
            <div>
                <strong>Rapid Rescue</strong>
                <small>Admin Panel</small>
            </div>
            <button class="btn-sidebar-close" onclick="toggleSidebar()" aria-label="Close menu">
                <i class="fa fa-xmark"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('admin.dashboard') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fa fa-gauge-high"></i> Dashboard
            </a>

            <div class="sidebar-section-label">Operations</div>
            <a href="{{ route('admin.ambulances.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.ambulances*') ? 'active' : '' }}">
                <i class="fa fa-ambulance"></i> Ambulances
            </a>

            <a href="{{ route('admin.driver.grid') }}"
               class="sidebar-nav-link {{ request()->routeIs('admin.drivers*') ? 'active' : '' }}">
                <i class="fa fa-id-card"></i> Drivers
            </a>

            @php
                $pendingEmergencyCount = \App\Models\EmergencyRequest::whereNotIn('status', ['6', '7'])->count();
            @endphp
            <a href="{{ route('admin.emergency.grid') }}"
               class="sidebar-nav-link {{ request()->routeIs('admin.emergency.grid') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-medical"></i> Emergency Requests
                <span id="emergencyReqBadge" style="display:{{ $pendingEmergencyCount > 0 ? 'inline-flex' : 'none' }};margin-left:auto;background:#D72C42;color:#fff;font-size:0.68rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;align-items:center;justify-content:center;padding:0 5px;line-height:1;">{{ $pendingEmergencyCount }}</span>
            </a>

            <a href="{{ route('admin.emergency.past-rides') }}"
               class="sidebar-nav-link {{ request()->routeIs('admin.emergency.past-rides') ? 'active' : '' }}">
                <i class="fa fa-clock-rotate-left"></i> Past Rides
            </a>

            @php
                $admChatNavCount = \App\Models\RideChatMessage::whereHas(
                    'emergencyRequest', fn($q) => $q->whereNotNull('id')
                )->where('is_read_admin', false)->where('sender_type', '!=', 'admin')->count();
            @endphp
            <a href="{{ route('admin.ride-chats.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.ride-chats.grid') ? 'active' : '' }}">
                <i class="fa fa-comments"></i> Ride Chats
                <span id="admChatNavBadge"
                      style="display:{{ $admChatNavCount > 0 ? 'inline-flex' : 'none' }};margin-left:auto;background:#3b82f6;color:#fff;font-size:0.68rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;align-items:center;justify-content:center;padding:0 5px;line-height:1;">{{ $admChatNavCount > 99 ? '99+' : $admChatNavCount }}</span>
            </a>

            <a href="{{ route('admin.live-monitoring') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.live-monitoring') ? 'active' : '' }}">
                <i class="fa fa-map-location-dot"></i> Live Monitoring
            </a>

            @php
                $contactUnreadIds      = \App\Models\User\ContactMessage::where(function ($q) {
                    $q->where('admin_read', false)
                      ->orWhereHas('replies', fn ($q2) => $q2->where('sender_type', 'user')->where('is_read', false));
                })->pluck('id')->toArray();
                $contactMsgUnreadCount = count($contactUnreadIds);
            @endphp
            <a href="{{ route('admin.contact-messages.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.contact-messages*') ? 'active' : '' }}">
                <i class="fa fa-envelope-open-text"></i> Contact Messages
                <span id="contactMsgNavBadge"
                      style="display:{{ $contactMsgUnreadCount > 0 ? 'inline-flex' : 'none' }};margin-left:auto;background:#f59e0b;color:#fff;font-size:0.68rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;align-items:center;justify-content:center;padding:0 5px;line-height:1;">{{ $contactMsgUnreadCount > 99 ? '99+' : $contactMsgUnreadCount }}</span>
            </a>

            <div class="sidebar-section-label">Content</div>
            <a href="{{ route('admin.services.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.services*') ? 'active' : '' }}">
                <i class="fa fa-briefcase-medical"></i> Services
            </a>

            <a href="{{ route('admin.testimonials.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.testimonials*') ? 'active' : '' }}">
                <i class="fa fa-star"></i> Testimonials
            </a>

            <a href="{{ route('admin.faqs.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.faqs*') ? 'active' : '' }}">
                <i class="fa fa-circle-question"></i> FAQs
            </a>

            <div class="sidebar-section-label">Administration</div>
            <a href="{{ route('admin.users.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <i class="fa fa-users"></i> Users
            </a>

            <a href="{{ route('admin.admins.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.admins*') ? 'active' : '' }}">
                <i class="fa fa-user-shield"></i> Manage Admins
            </a>

            <a href="{{ route('admin.branch.grid') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.branch*') ? 'active' : '' }}">
                <i class="fa fa-building"></i> Branches
            </a>

            <div class="sidebar-section-label">Audit</div>
            <a href="{{ route('admin.logs') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.logs*') ? 'active' : '' }}">
                <i class="fa fa-shield-halved"></i> Activity Logs
            </a>

            <a href="{{ route('admin.visitor-logs') }}"
                class="sidebar-nav-link {{ request()->routeIs('admin.visitor-logs*') ? 'active' : '' }}">
                <i class="fa fa-binoculars"></i> Visitor Logs
            </a>

        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-admin-info">
                <div class="sidebar-admin-avatar"><i class="fa fa-user-shield"></i></div>
                <div>
                    <strong>{{ Auth::guard('admin')->user()->name }}</strong>
                    <small>{{ Auth::guard('admin')->user()->email }}</small>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn-sidebar-logout">
                    <i class="fa fa-right-from-bracket"></i> Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="adm-main">
        <header class="adm-topbar">
            <button class="btn-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i class="fa fa-bars"></i>
            </button>
            <span class="adm-topbar-title">@yield('page_title', 'Dashboard')</span>

            <div class="d-flex align-items-center gap-2">
                <div class="adm-topbar-avatar"><i class="fa fa-user-shield"></i></div>
                <div class="d-none d-md-block">
                    <div class="topbar-admin-name">{{ Auth::guard('admin')->user()->name }}</div>
                    <div class="topbar-admin-role">Administrator</div>
                </div>
            </div>
        </header>

        <div class="adm-content">
            @yield('content')
        </div>

        {{-- Footer --}}
        <footer class="adm-footer">
            <span class="adm-footer-copy">
                &copy; {{ date('Y') }} <strong>Rapid Rescue</strong>. All rights reserved.
            </span>
            <span class="adm-footer-tagline">Ambulance Dispatch System</span>
        </footer>
    </main>

    {{-- ROUTES --}}
    <script>
        window.adminRoutes = {
            csrfToken: "{{ csrf_token() }}",
            ambulancesStore: "{{ route('admin.ambulances.store') }}",
            ambulancesUpdate: "{{ url('admin/ambulances/update') }}",
            ambulancesDelete: "{{ url('admin/ambulances/delete') }}",
            servicesStore: "{{ route('admin.services.add') }}",
            servicesUpdate: "{{ url('admin/services/update') }}",
            servicesDelete: "{{ url('admin/services/delete') }}",
            testimonialsStore: "{{ route('admin.testimonials.add') }}",
            testimonialsUpdate: "{{ url('admin/testimonials/update') }}",
            testimonialsDelete: "{{ url('admin/testimonials/delete') }}",
            faqsStore: "{{ route('admin.faqs.add') }}",
            faqsUpdate: "{{ url('admin/faqs/update') }}",
            faqsDelete: "{{ url('admin/faqs/delete') }}",
            adminsStore: "{{ route('admin.admins.add') }}",
            adminsUpdate: "{{ url('admin/admins/update') }}",
            adminsDelete: "{{ url('admin/admins/delete') }}",
            driverStore:  "{{ route('admin.driver.add') }}",
            driverUpdate: "{{ url('admin/driver/update') }}",
            driverDelete: "{{ url('admin/driver/delete') }}",
            emergencyDelete:  "{{ url('admin/emergency/delete') }}",
            pastRidesPage:    "{{ route('admin.emergency.past-rides') }}",
            checkAdminUsername:   "{{ route('admin.admins.checkUsername') }}",
            checkDriverUsername:  "{{ route('admin.driver.checkUsername') }}",
            rideChatPage:         "{{ route('admin.ride-chats.grid') }}",
        };

        @php
            $rrWsHost   = request()->getHost();
            $rrWsPort   = (int) env('REVERB_PORT', 8080);
            $rrForceTLS = request()->secure();
        @endphp
        window._emergencyBadgeCount = {{ $pendingEmergencyCount ?? 0 }};

        // ── Ride Chat nav badge ────────────────────────────────────────────────
        (function () {
            var _chatCount = {{ $admChatNavCount ?? 0 }};

            window.admSetChatBadge = function (n) {
                n = Math.max(0, parseInt(n, 10) || 0);
                _chatCount = n;
                var el = document.getElementById('admChatNavBadge');
                if (!el) return;
                if (n > 0) {
                    el.textContent  = n > 99 ? '99+' : String(n);
                    el.style.display = 'inline-flex';
                } else {
                    el.style.display = 'none';
                }
            };

            window.admIncrementChatBadge = function () { window.admSetChatBadge(_chatCount + 1); };
            window.admDecrementChatBadge = function (by) { window.admSetChatBadge(_chatCount - (parseInt(by, 10) || 1)); };

            // ── Admin cross-tab sync (BroadcastChannel) ──────────────────────
            var _bc;
            try { _bc = new BroadcastChannel('rr_admin_sync'); } catch (e) {}

            window.admBroadcastTabSync = function (msg) {
                if (!_bc) return;
                try { _bc.postMessage(msg); } catch (ex) {}
            };

            if (_bc) {
                _bc.onmessage = function (ev) {
                    var d = ev.data || {};
                    if (d.type === 'ride_chat_badge_set') {
                        window.admSetChatBadge(d.value);
                    } else if (d.type === 'contact_badge_set') {
                        window.admSetContactBadge(d.value);
                    } else if (d.type === 'contact_badge_delta') {
                        window.admDeltaContactBadge(d.delta);
                    }
                };
            }
        })();

        window._rrContactUnreadSet = new Set({{ json_encode($contactUnreadIds ?? []) }});

        (function () {
            var _contactCount = {{ $contactMsgUnreadCount ?? 0 }};

            window.admSetContactBadge = function (n) {
                n = Math.max(0, parseInt(n, 10) || 0);
                _contactCount = n;
                var el = document.getElementById('contactMsgNavBadge');
                if (!el) return;
                if (n > 0) {
                    el.textContent   = n > 99 ? '99+' : String(n);
                    el.style.display = 'inline-flex';
                } else {
                    el.style.display = 'none';
                }
            };

            window.admDeltaContactBadge = function (delta) {
                window.admSetContactBadge(_contactCount + (parseInt(delta, 10) || 0));
            };

            window.admIncrementContactBadge = function () {
                window.admDeltaContactBadge(+1);
                // Do NOT broadcast via BroadcastChannel here.  Every admin tab
                // has its own Reverb subscription and receives the same event
                // independently, so each tab computes the correct count on its
                // own.  Broadcasting a SET value to other tabs would cause a
                // race: the receiving tab may apply the SET before its own
                // Reverb event fires, then increment again on top of it —
                // resulting in a doubled (or tripled) badge count.
            };

            window.admDecrementContactBadge = function (by) {
                window.admDeltaContactBadge(-(parseInt(by, 10) || 1));
                // Same reasoning: all tabs receive message.read from Reverb
                // and decrement independently.  No BroadcastChannel needed.
            };
        })();

        window._rrReverb = {
            key:               '{{ env("REVERB_APP_KEY")}}',
            wsHost:            "{{ $rrWsHost }}",
            wsPort:            {{ $rrWsPort }},
            wssPort:           {{ $rrWsPort }},
            forceTLS:          {{ $rrForceTLS ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
            adminId:           {{ Auth::guard('admin')->user()->id }},
        };
    </script>

    {{-- BOOTSTRAP JS - CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- PUSHER JS (required for real-time WebSocket) --}}
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    {{-- CUSTOM JS --}}
    <script src="{{ asset('assets/admin/js/script.js') }}"></script>

    {{-- REAL-TIME EVENT HUB (Reverb/Pusher — routes events to page handlers) --}}
    <script src="{{ asset('assets/admin/js/realtime.js') }}"></script>

    @stack('scripts')
</body>

</html>
