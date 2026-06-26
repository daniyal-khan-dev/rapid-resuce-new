<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rapid Rescue — 24/7 Ambulance Service')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/user/img/logo/logo.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('assets/user/img/logo/logo.png') }}">

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Autocomplete styles -->
    <link rel="stylesheet" href="{{ asset('assets/user/css/autocomplete.css') }}">

    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <!-- STYLES -->
    <link rel="stylesheet" href="{{ asset('assets/user/css/style.css') }}">

    <!-- PAGE-SPECIFIC STYLES -->
    @stack('styles')

    @auth('users')
    <style>
    /* ── User Notification Bell ─────────────────────────────────────────────── */
    .rr-notif-bell-wrap { position:relative; display:inline-flex; align-items:center; }
    .rr-notif-bell-btn { position:relative; background:none; border:none; cursor:pointer; width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#374151; transition:background .15s; padding:0; }
    .rr-notif-bell-btn:hover { background:#f3f4f6; }
    .rr-notif-bell-btn .fa-bell { font-size:1rem; }
    .rr-notif-bell-badge { position:absolute; top:3px; right:3px; min-width:16px; height:16px; border-radius:8px; background:var(--rr-primary,#D72C42); color:#fff; font-size:.62rem; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px; line-height:1; pointer-events:none; }
    .rr-notif-bell-dropdown { position:absolute; top:calc(100% + 8px); right:0; width:340px; background:#fff; border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,.14); z-index:1080; overflow:hidden; border:1px solid rgba(0,0,0,.06); }
    .rr-notif-bell-header { display:flex; align-items:center; justify-content:space-between; padding:13px 16px 11px; border-bottom:1px solid #f3f4f6; }
    .rr-notif-bell-header > span { font-size:.88rem; font-weight:700; color:#111; }
    .rr-notif-bell-clear { background:none; border:none; cursor:pointer; font-size:.76rem; font-weight:600; color:var(--rr-primary,#D72C42); padding:0; }
    .rr-notif-bell-list { list-style:none; margin:0; padding:0; max-height:320px; overflow-y:auto; }
    .rr-notif-item { display:flex; align-items:flex-start; padding:0; cursor:pointer; transition:background .12s; border-bottom:1px solid #f9fafb; }
    .rr-notif-item:hover { background:#f9fafb; }
    .rr-notif-item--unread { background:#eff6ff; }
    .rr-notif-item--unread:hover { background:#dbeafe; }
    .rr-notif-item-inner { display:flex; align-items:flex-start; gap:11px; padding:12px 14px; width:100%; }
    .rr-notif-item-icon { font-size:.85rem; margin-top:2px; flex-shrink:0; }
    .rr-notif-icon--status  { color:#059669; }
    .rr-notif-icon--admin   { color:#7c3aed; }
    .rr-notif-icon--driver  { color:#2563eb; }
    .rr-notif-icon--default { color:var(--rr-primary,#D72C42); }
    .rr-notif-item-body { flex:1; min-width:0; }
    .rr-notif-item-title { font-size:.82rem; font-weight:600; color:#111827; line-height:1.35; }
    .rr-notif-item-preview { font-size:.76rem; color:#6b7280; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rr-notif-item-time { font-size:.72rem; color:#9ca3af; margin-top:3px; }
    .rr-notif-dot { width:8px; height:8px; border-radius:50%; background:var(--rr-primary,#D72C42); flex-shrink:0; margin-top:4px; }
    .rr-notif-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:28px 16px; gap:8px; color:#9ca3af; font-size:.83rem; }
    .rr-notif-empty i { font-size:1.4rem; }
    .rr-notif-bell-footer { border-top:1px solid #f3f4f6; padding:10px 16px; text-align:center; }
    .rr-notif-bell-view-all { font-size:.78rem; font-weight:600; color:var(--rr-primary,#D72C42); text-decoration:none; }
    .rr-notif-bell-view-all:hover { text-decoration:underline; }
    </style>
    @endauth

    <!-- JQUERY -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>

<body>
    <div id="successAlert" class="alert alert-success alert-dismissible fade position-fixed top-0 end-0 m-3 shadow"
        role="alert" style="display: none; z-index: 1055;">
        <span id="successAlertText"></span>
        <button type="button" class="btn-close" aria-label="Close" onclick="hideSuccessAlert()"></button>
    </div>

    <div id="errorAlert" class="alert alert-danger alert-dismissible fade position-fixed top-0 end-0 m-3 shadow"
        role="alert" style="display: none; z-index: 1055;">
        <span id="errorAlertText"></span>
        <button type="button" class="btn-close" aria-label="Close" onclick="hideErrorAlert()"></button>
    </div>

    <!-- Page Loader -->
    <div id="rr-loader">
        <div class="rr-loader-ring"></div>
        <div class="rr-loader-logo">Rapid <span>Rescue</span></div>
    </div>
    
    {{-- Scroll to top --}}
    <a class="btn btn-secondary btn-lg-square rounded-circle back-to-top" id="scroll-top-button" data-scroll="rrTopbar" href="javascript:void(0)">
        <i class="fa fa-arrow-up"></i>
    </a>

    <!-- Top bar -->
    <div class="rr-topbar" id="rrTopbar">
        <div class="container">
            <div class="row align-items-center py-2">
                <div class="col d-flex flex-wrap fle align-items-center gap-2 rr-topbar-left">
                    <span class="rr-info">
                        <i class="fa-solid fa-clock"></i> 24/7 Ambulance Service
                    </span>

                    <a href="tel:{{ $contactInfo->phone ?? '+92 xxx xxxxxxx' }}">
                        <i class="fas fa-phone-alt"></i> {{ $contactInfo->phone ?? '+92 xxx xxxxxxx' }}
                    </a>

                    <a href="mailto:{{ $contactInfo->email ?? 'info@rapidrescue.com' }}">
                        <i class="fas fa-envelope"></i> {{ $contactInfo->email ?? 'info@rapidrescue.com' }}
                    </a>
                </div>

                <div class="col-lg-4 d-flex justify-content-lg-end">
                    <div class="rr-topbar-socials">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg rr-nav" id="rrNav">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand rr-logo" href="{{ route('home') }}">
                <span class="rr-logo-mark">
                    <img src="{{ asset('assets/user/img/logo/logo.png') }}" alt="Rapid Rescue">
                </span>

                <span class="rr-logo-text ms-2">
                    <strong>Rapid <span>Rescue</span></strong>
                    <small class="d-block">Ambulance System</small>
                </span>
            </a>

            <!-- Toggler -->
            <button class="navbar-toggler rr-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Collapsible Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Menu -->
                <ul class="navbar-nav mx-auto rr-nav-menu" id="rrNavMenu">
                    <li class="nav-item"><a class="nav-link" data-scroll="home" href="javascript:void(0)">Home</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="features" href="javascript:void(0)">Features</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="services" href="javascript:void(0)">Services</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="ambulances" href="javascript:void(0)">Ambulances</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="about-us" href="javascript:void(0)">About</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="testimonials" href="javascript:void(0)">Testimonials</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="faq" href="javascript:void(0)">FAQS</a></li>
                    <li class="nav-item"><a class="nav-link" data-scroll="contact-form" href="javascript:void(0)">Contact</a></li>
                </ul>

                <!-- CTA -->
                <div class="rr-nav__cta">
                    @guest('users')
                        <a href="{{ route('login') }}" class="rr-btn rr-btn--outline btn-sm">Login</a>
                        <a href="{{ route('signup') }}" class="rr-btn rr-btn--primary btn-sm">Sign Up</a>
                    @endguest

                    @auth('users')
                        @php
                            $authUser    = Auth::guard('users')->user()->details;
                            $displayName = $authUser ? $authUser->first_name : Auth::guard('users')->user()->username;
                            $pfp         = $authUser ? $authUser->profile_picture : 'default.jpg';
                            $email       = $authUser ? $authUser->email : '';
                            $pfpUrl      = asset('assets/user/img/users/' . $pfp);
                        @endphp

                        {{-- Notification Bell --}}
                        <div class="rr-notif-bell-wrap" id="rrNotifBellWrap">
                            <button id="rrNotifBellBtn" type="button" class="rr-notif-bell-btn" aria-label="Notifications">
                                <i class="fa fa-bell"></i>
                                <span class="rr-notif-bell-badge" id="rrNotifBellBadge" style="display:none;"></span>
                            </button>
                            <div id="rrNotifBellDropdown" class="rr-notif-bell-dropdown" style="display:none;">
                                <div class="rr-notif-bell-header">
                                    <span>Notifications</span>
                                    <button id="rrNotifBellMarkAll" class="rr-notif-bell-clear">Mark all read</button>
                                </div>
                                <ul id="rrNotifBellList" class="rr-notif-bell-list">
                                    <li class="rr-notif-empty"><i class="fa fa-bell-slash"></i><span>No notifications</span></li>
                                </ul>
                                <div class="rr-notif-bell-footer">
                                    <a href="{{ route('ride-chat.notifHistory') }}" class="rr-notif-bell-view-all">View all notifications</a>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown">
                            <div class="rr-user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="{{ $pfpUrl }}" alt="{{ $displayName }}">
                                <span>{{ $displayName }}</span>
                                <i class="fa fa-chevron-down rr-user-menu__caret"></i>
                            </div>

                            <div class="dropdown-menu dropdown-menu-end rr-user-dropdown">
                                <div class="rr-user-dropdown__header">
                                    <img src="{{ $pfpUrl }}" alt="{{ $displayName }}" class="rr-user-dropdown__avatar">
                                    <div class="rr-user-dropdown__info">
                                        <strong>{{ $displayName }}</strong>
                                        <span>{{ $email }}</span>
                                        <em>Consumer No: {{ $authUser->consumer_no ?? 'N/A' }}</em>
                                    </div>
                                </div>

                                <div class="rr-user-dropdown__divider"></div>

                                <a href="{{ route('profile.grid') }}" class="rr-user-dropdown__item">
                                    <span class="rr-user-dropdown__icon" style="background:var(--rr-primary-50);color:var(--rr-primary);">
                                        <i class="fa fa-user"></i>
                                    </span>

                                    <span class="rr-user-dropdown__label">
                                        <strong>Profile</strong>
                                        <small>View &amp; edit your info</small>
                                    </span>
                                </a>

                                <a href="{{ route('medicalCard.grid') }}" class="rr-user-dropdown__item">
                                    <span class="rr-user-dropdown__icon" style="background:#fff0f1;color:var(--rr-primary);">
                                        <i class="fa fa-id-card-clip"></i>
                                    </span>

                                    <span class="rr-user-dropdown__label">
                                        <strong>Medical Card</strong>
                                        <small>Blood type, allergies &amp; conditions</small>
                                    </span>
                                </a>

                                <a href="{{ route('first-aid') }}" class="rr-user-dropdown__item">
                                    <span class="rr-user-dropdown__icon" style="background:#f0fdf4;color:#16a34a;">
                                        <i class="fa fa-kit-medical"></i>
                                    </span>

                                    <span class="rr-user-dropdown__label">
                                        <strong>First-Aid Guide</strong>
                                        <small>Emergency instructions</small>
                                    </span>
                                </a>

                                <div class="rr-user-dropdown__divider"></div>

                                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                    @csrf
                                    <button type="submit" class="rr-user-dropdown__item rr-user-dropdown__item--danger w-100"
                                        style="background:none;border:none;text-align:left;cursor:pointer;">
                                        <span class="rr-user-dropdown__icon" style="background:#fff0f1;color:var(--rr-primary);">
                                            <i class="fa fa-sign-out-alt"></i>
                                        </span>

                                        <span class="rr-user-dropdown__label">
                                            <strong>Logout</strong>
                                            <small>Sign out of your account</small>
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    @auth('users')
    <script>
    window._rrNotifRoutes = {
        notifBell:         '{{ route("ride-chat.notifBell") }}',
        notifRead:         '/ride-chat/notif/:id/read',
        statusNotifRead:   '/ride-chat/status-notif/:id/read',
        markAllNotifsRead: '{{ route("ride-chat.markAllNotifsRead") }}',
        notifHistoryPage:  '{{ route("ride-chat.notifHistory") }}',
    };
    </script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
    @php
        $rrWsHost     = env('REVERB_HOST');
        $rrWsPort     = (int) env('REVERB_PORT');
        $rrForceTLS   = env('REVERB_SCHEME', 'http') === 'https';
        $rrAuthUserId = Auth::guard('users')->id();
    @endphp
    (function () {
        if (window._rrUserNotifPusher) return;
        Pusher.logToConsole = false;
        window._rrUserNotifPusher = new Pusher('{{ env("REVERB_APP_KEY", "local") }}', {
            wsHost:            "{{ $rrWsHost }}",
            wsPort:            {{ $rrWsPort }},
            wssPort:           {{ $rrWsPort }},
            forceTLS:          {{ $rrForceTLS ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
            disableStats:      true,
            cluster:           'mt1',
            authEndpoint:      '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }
        });
        var ch = window._rrUserNotifPusher.subscribe('private-contact.user.{{ $rrAuthUserId }}');
        ch.bind('ride.chat.message', function (e) {
            // Skip the user's own outgoing messages.
            if (e && e.sender_type === 'user') return;
            // THIS tab: actively viewing this ride's tracking page — ride_chat.js
            // handles auto-read and badge suppression directly.
            if (typeof window.rrIsActiveChatFor === 'function' && e && e.request_id && window.rrIsActiveChatFor(e.request_id)) return;
            // ANOTHER tab: check localStorage heartbeat (TTL 30 s, refreshed on focus).
            // If another tab is actively tracking this ride, don't increment the bell.
            try {
                var _ts = localStorage.getItem('rrActiveTracker_' + (e && e.request_id));
                if (_ts && (Date.now() - parseInt(_ts, 10)) < 30000) return;
            } catch (ex) {}
            if (window.rrNotifBell) window.rrNotifBell.onNewMessage();
        });
        ch.bind('ride.chat.notif.read', function (e) {
            var count = (e && e.count && e.count > 0) ? e.count : null;
            if (window.rrNotifBell) window.rrNotifBell.onNotifsRead(count);
        });
        ch.bind('request.status.updated', function () {
            if (window.rrNotifBell) window.rrNotifBell.onNewStatusNotif();
        });
        window._rrUserNotifChannel = ch;
    })();
    </script>
    <script src="{{ asset('assets/user/js/notificaiton.js') }}"></script>
    @endauth
