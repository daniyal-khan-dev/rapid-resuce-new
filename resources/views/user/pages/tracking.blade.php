@extends('user.layouts.user')
@section('title', 'Track Ambulance — Rapid Rescue')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
    <link rel="stylesheet" href="{{ asset('assets/user/css/tracking.css') }}">
    <style>
        .leaflet-right .leaflet-control{ display: none !important; }

        /* ── Floating Chat Button ─────────────────────────────────────────── */
        .rr-chat-fab {
            position: fixed;
            bottom: 28px;
            right: 100px;
            z-index: 1050;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #3b82f6;
            color: #fff;
            border: none;
            box-shadow: 0 4px 18px rgba(59,130,246,.5);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background .2s, transform .2s, box-shadow .2s;
        }
        .rr-chat-fab:hover { background: #2563eb; transform: scale(1.08); box-shadow: 0 6px 22px rgba(59,130,246,.6); }
        .rr-chat-fab:active { transform: scale(.95); }
        .rr-chat-fab .rr-chat-fab-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: #ef4444;
            color: #fff;
            border-radius: 50%;
            font-size: .62rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            display: none;
            align-items: center;
            justify-content: center;
            line-height: 1;
            padding: 0 3px;
            border: 2px solid #0f172a;
        }
        .rr-chat-fab .rr-chat-fab-badge.visible { display: flex; }

        /* ── Floating Chat Panel ──────────────────────────────────────────── */
        .rr-chat-panel {
            position: fixed;
            bottom: 98px;
            right: 28px;
            z-index: 1049;
            width: 340px;
            max-width: calc(100vw - 40px);
            background: #1e293b;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,.45);
            display: none;
            flex-direction: column;
            transform: translateY(16px);
            opacity: 0;
            transition: transform .25s cubic-bezier(.34,1.56,.64,1), opacity .2s;
        }
        .rr-chat-panel.rr-chat-panel--open {
            display: flex;
            transform: translateY(0);
            opacity: 1;
        }
        .rr-ride-chat__head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.02);
            cursor: default;
        }
        .rr-ride-chat__head strong { font-size: .85rem; color: #e2e8f0; }
        .rr-chat-close-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: rgba(255,255,255,.4);
            cursor: pointer;
            font-size: .85rem;
            line-height: 1;
            padding: 2px 4px;
            transition: color .15s;
        }
        .rr-chat-close-btn:hover { color: #fff; }
        .rc-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .rr-chat-messages {
            height: 260px;
            overflow-y: auto;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
        }
        .rr-chat-messages::-webkit-scrollbar { width: 4px; }
        .rr-chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
        .rc-bubble-wrap { display: flex; flex-direction: column; margin-bottom: 10px; }
        .rc-bubble-wrap--right { align-items: flex-end; }
        .rc-bubble-wrap--left  { align-items: flex-start; }
        .rc-sender { font-size: .68rem; color: rgba(255,255,255,.38); margin-bottom: 2px; }
        .rc-bubble {
            max-width: 82%;
            padding: 7px 12px;
            border-radius: 12px;
            font-size: .82rem;
            line-height: 1.45;
            word-break: break-word;
        }
        .rc-bubble--user   { background: #3b82f6; color: #fff; border-bottom-right-radius: 3px; }
        .rc-bubble--driver { background: rgba(16,185,129,.22); color: #a7f3d0; border-bottom-left-radius: 3px; }
        .rc-bubble--admin  { background: rgba(139,92,246,.22); color: #ddd6fe; border-bottom-left-radius: 3px; }
        .rc-time { font-size: .65rem; color: rgba(255,255,255,.25); margin-top: 2px; }
        .rr-chat-input-wrap {
            display: flex;
            gap: 8px;
            padding: 10px 14px;
            border-top: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.02);
        }
        .rr-chat-input {
            flex: 1;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: .82rem;
            padding: 8px 12px;
            font-family: inherit;
            outline: none;
        }
        .rr-chat-input:focus { border-color: rgba(59,130,246,.5); }
        .rr-chat-input::placeholder { color: rgba(255,255,255,.25); }
        .rr-chat-send {
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s;
        }
        .rr-chat-send:hover { background: #2563eb; }
        .rr-chat-send:disabled { opacity: .5; cursor: not-allowed; }
        .rr-chat-notice {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100px;
            color: rgba(255,255,255,.28);
            font-size: .8rem;
            gap: 8px;
        }
    </style>
@endpush

@section('content')
    <div class="rr-tracking-hero">
        <div class="container">
            <h1 style="color: white;"><i class="fas fa-map-marked-alt"></i> Real-Time Tracking</h1>
            <p>Emergency ID: <span style="text-transform: uppercase">{{ $req->rreb_id }}</span> 
                &nbsp;·&nbsp; 
                @if ($req->type === '1')Emergency
                @elseif($req->type === '2') non-emergency
                @endif
                &nbsp;·&nbsp; 
                Submitted {{ $req->created_at->diffForHumans() }}
            </p>
        </div>
    </div>

    <div class="rr-tracking-wrap">
        <div class="container">
            <div class="row g-4">
                {{-- Map column --}}
                <div class="col-lg-8">
                    <div id="trackingMap"></div>
                    <div class="rr-refresh-btn mt-2">
                        <i class="fas fa-sync-alt"></i>
                        <i class="fas fa-circle" style="color:#22c55e;font-size:.5rem;vertical-align:middle;animation:rrLivePulse 1.8s infinite;"></i>
                        Live real-time updates active &nbsp;|&nbsp; Last update:
                        <span id="lastTrackUpdate">just now</span>
                        <style>@keyframes rrLivePulse{0%,100%{opacity:1}50%{opacity:.25}}</style>
                    </div>

                    {{-- Status timeline --}}
                    <div class="rr-status-timeline">
                        @php
                            $steps = ['pending', 'dispatched', 'en_route', 'arrived', 'transporting', 'completed'];
                            /*
                             * Map numeric DB status → 0-based step index.
                             * Status 8 (Awaiting Acceptance) is shown as step 1 (Dispatched)
                             * so users never see the internal driver-acceptance handshake.
                             */
                            $statusToIdx = ['1'=>0, '2'=>1, '8'=>1, '3'=>2, '4'=>3, '5'=>4, '6'=>5];
                            $curIdx = $statusToIdx[$req->status] ?? -1;
                        @endphp

                        @foreach ($steps as $i => $step)
                            @php
                                $isCur  = ($i === $curIdx);
                                $isDone = ($i < $curIdx);
                                $cls    = $isCur ? 'active' : ($isDone ? 'done' : '');
                            @endphp

                            <div class="rr-tl-step {{ $cls }}">
                                <div class="rr-tl-dot">
                                    @if ($isDone)
                                        <i class="fa fa-check"></i>
                                    @elseif($isCur)
                                        <i class="fa fa-circle-dot"></i>
                                    @else
                                        <i class="fa fa-circle"></i>
                                    @endif
                                </div>
                                <span>{{ ucfirst(str_replace('_', ' ', $step)) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Info column --}}
                <div class="col-lg-4">
                    <div class="rr-tracking-status-big">
                        <h3 style="color: white;">
                            @php
                                $statusLabels = [
                                    '1' => '⏳ Awaiting Dispatch',
                                    '2' => '🚑 Ambulance Assigned',
                                    '8' => '🚑 Ambulance Assigned',
                                    '3' => '🚨 En Route to You',
                                    '4' => '📍 Arrived at Scene',
                                    '5' => '🏥 Transporting',
                                    '6' => '✅ Trip Completed',
                                    '7' => '❌ Cancelled',
                                ];
                            @endphp
                            {{ $statusLabels[$req->status] ?? ucfirst($req->status) }}
                        </h3>

                        <p id="statusSubtext">
                            @if ($req->status === '1')
                                Your request has been received and is being reviewed.
                            @elseif($req->status === '2')
                                An ambulance has been assigned and will depart shortly.
                            @elseif($req->status === '3')
                                The ambulance is on its way to your location.
                            @elseif($req->status === '4')
                                The paramedic team has arrived at your pickup point.
                            @elseif($req->status === '5')
                                You are being transported to the hospital.
                            @elseif($req->status === '6')
                                Your trip has been completed. Thank you.
                            @elseif($req->status === '7')
                                Your trip has been cancelled. Thank you.
                            @else
                                Status updated.
                            @endif
                        </p>
                    </div>

                    <div class="rr-tracking-info">
                        <div class="rr-tracking-row">
                            <i class="fas fa-hashtag"></i>
                            <div><small>Emergency ID</small>
                                <div><strong style="text-transform: uppercase">{{ $req->rreb_id }}</strong></div>
                            </div>
                        </div>

                        <div class="rr-tracking-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <div><small>Pickup Address</small>
                                <div>{{ $req->pickup_address }}</div>
                            </div>
                        </div>

                        @if ($req->hospital_name)
                            <div class="rr-tracking-row">
                                <i class="fas fa-hospital"></i>
                                <div><small>Destination Hospital</small>
                                    <div>{{ $req->hospital_name }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="rr-tracking-row">
                            <i class="fas fa-phone"></i>
                            <div><small>Contact Number</small>
                                <div>{{ $req->mobile_no }}</div>
                            </div>
                        </div>

                        @if ($req->ambulance)
                            <div class="rr-tracking-row">
                                <i class="fas fa-ambulance"></i>
                                <div><small>Ambulance</small>
                                    <div><strong>{{ $req->ambulance->vehicle_number }}</strong> —
                                        {{ $req->ambulance->type }}</div>
                                </div>
                            </div>
                        @endif

                        @if ($req->driver)
                            <div class="rr-tracking-row">
                                <i class="fas fa-user"></i>
                                <div><small>Driver / Paramedic</small>
                                    <div>
                                        <strong>{{ $req->driver->name }}</strong><br><small>{{ $req->driver->phone }}</small>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($req->dispatched_at)
                            <div class="rr-tracking-row">
                                <i class="fas fa-clock"></i>
                                <div><small>Dispatched At</small>
                                    <div>{{ $req->dispatched_at->format('H:i, d M Y') }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{--
                        Pending notice — only for status '1' (Awaiting Dispatch).
                        Never shown for status '8' (Awaiting Acceptance) since that
                        is an internal driver-handshake state users shouldn't see.
                        JS (handleStatusUpdate) also hides this card when status advances.
                    --}}
                    <div id="rrPendingNotice" class="rr-pending-notice mt-3"
                         @if($req->status !== '1') style="display:none;" @endif>
                        <i class="fas fa-hourglass-half"></i>
                        <h5>Request Received</h5>
                        <p>Our team is reviewing your request. An ambulance will be assigned shortly.</p>
                    </div>

                    <div id="rrFeedbackCta" class="rr-feedback-cta mt-3"
                         @if($req->status !== '6') style="display:none;" @endif>
                        <a href="{{ route('home') }}#feedback-section">
                            <i class="fas fa-star"></i> Rate This Service
                        </a>
                    </div>

                    {{-- ── Ride Chat config (vars only, UI is a floating FAB below) ── --}}
                    @php
                        use App\Models\RideChatMessage;
                        $chatStatus = RideChatMessage::chatStatus($req->status);
                        $trackUser  = Auth::guard('users')->user();
                        $rrWsHost   = env('REVERB_HOST');
                        $rrWsPort   = (int) env('REVERB_PORT');
                        $rrForceTLS = env('REVERB_SCHEME', 'http') === 'https';
                    @endphp

                </div>
            </div>
        </div>
    </div>

    {{-- ── Floating Chat FAB Button ────────────────────────────────────── --}}
    <button class="rr-chat-fab" id="rrChatFab" title="Open Ride Chat" aria-label="Open Ride Chat">
        <i class="fa fa-comments" id="rrChatFabIcon"></i>
        <span class="rr-chat-fab-badge" id="rrChatFabBadge">0</span>
    </button>

    {{-- ── Floating Chat Panel ─────────────────────────────────────────── --}}
    <div class="rr-chat-panel" id="rrChatPanel">
        <div class="rr-ride-chat__head">
            <span class="rc-dot" id="rcStatusDot"
                style="background:{{ $chatStatus === 'active' ? '#22c55e' : ($chatStatus === 'closed' ? '#ef4444' : '#94a3b8') }};"></span>
            <strong><i class="fa fa-comments" style="margin-right:5px;opacity:.7;"></i>Ride Chat</strong>
            <span style="font-size:.72rem;color:rgba(255,255,255,.35);margin-left:6px;">
                @if($chatStatus === 'active') Live
                @elseif($chatStatus === 'closed') Ended
                @else Locked
                @endif
            </span>
            <button class="rr-chat-close-btn" id="rrChatClose" title="Close chat">
                <i class="fa fa-times"></i>
            </button>
        </div>

        {{-- Messages --}}
        <div class="rr-chat-messages" id="rcMsgList">
            @if($chatStatus === 'locked')
                <div class="rr-chat-notice" id="rcLocked">
                    <i class="fa fa-lock" style="font-size:1.4rem;"></i>
                    <span>Chat unlocks when the driver is En Route</span>
                </div>
            @else
                <div id="rcLocked" style="display:none;"></div>
            @endif
            @if($chatStatus === 'closed')
                <div class="rr-chat-notice" id="rcClosed">
                    <i class="fa fa-flag-checkered" style="font-size:1.4rem;"></i>
                    <span>This ride has ended — chat is read-only</span>
                </div>
            @else
                <div id="rcClosed" style="display:none;"></div>
            @endif
        </div>

        {{-- Typing indicator --}}
        <div id="rcTyping" style="display:none;font-size:.71rem;color:rgba(255,255,255,.35);padding:2px 14px;"></div>

        {{-- Input area --}}
        @if($chatStatus === 'active')
        <div class="rr-chat-input-wrap" id="rcInputWrap">
            <input class="rr-chat-input" id="rcInput" type="text"
                   placeholder="Message your driver or dispatch…" maxlength="2000" autocomplete="off">
            <button class="rr-chat-send" id="rcSendBtn" type="button">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
        @else
        <div id="rcInputWrap" style="display:none;">
            <input class="rr-chat-input" id="rcInput" type="text">
            <button class="rr-chat-send" id="rcSendBtn" type="button"></button>
        </div>
        @endif
    </div>
    {{-- ── End Floating Chat Panel ─────────────────────────────────────── --}}

    <script>
        window.trackingData = {
            requestId:   {{ $req->id }},
            status:      @json($req->status),
            pickupLat:   {{ $req->pickup_lat  }},
            pickupLng:   {{ $req->pickup_lng }},
            driverLat:   {{ $req->driver?->lat ?? 'null' }},
            driverLng:   {{ $req->driver?->lng ?? 'null' }},
            driverId:    {{ $req->driver_id ?? 'null' }},
            hospitalLat: {{ $req->hospital_lat ?? 'null' }},
            hospitalLng: {{ $req->hospital_lng ?? 'null' }},
            /* Where the driver accepted / started the job */
            acceptedLat: {{ $req->accepted_lat ?? 'null' }},
            acceptedLng: {{ $req->accepted_lng ?? 'null' }},
        };

        window._rrReverb = {
            key:               "{{ env('REVERB_APP_KEY') }}",
            wsHost:            "{{ $rrWsHost }}",
            wsPort:            {{ $rrWsPort }},
            wssPort:           {{ $rrWsPort }},
            forceTLS:          {{ $rrForceTLS ? 'true' : 'false' }},
            enabledTransports: ['ws'],
        };

        window._rideChatCfg = {
            requestId:  {{ $req->id }},
            userId:     {{ $trackUser?->id ?? 'null' }},
            chatStatus: @json($chatStatus),
            threadUrl:  "{{ route('ride-chat.thread', $req->id) }}",
            sendUrl:    "{{ route('ride-chat.send', $req->id) }}",
            typingUrl:  "{{ route('ride-chat.typing', $req->id) }}",
        };

        // ── Auto-read tracker ────────────────────────────────────────────────
        // Publish a localStorage heartbeat so every other open tab (bell,
        // notif history, other pages) knows this tab is actively viewing
        // ride {{ $req->id }} and can suppress badge increments for it.
        // Uses setInterval (not focus/blur events) so popup open/close and
        // other UI interactions never interrupt the heartbeat.
        (function () {
            var _key = 'rrActiveTracker_{{ $req->id }}';
            function _ping() { try { localStorage.setItem(_key, String(Date.now())); } catch (ex) {} }
            _ping();
            // Refresh the key every 10 s — independent of any UI state.
            var _interval = setInterval(_ping, 10000);
            window.addEventListener('beforeunload', function () { clearInterval(_interval); try { localStorage.removeItem(_key); } catch (ex) {} });
            window.addEventListener('pagehide',     function () { clearInterval(_interval); try { localStorage.removeItem(_key); } catch (ex) {} });

            // Checked by ride_chat.js (same tab) to skip badge bump + auto-read.
            // Reads window.location.pathname at call time — not a stale closure —
            // so it is immune to any popup, dropdown, or UI state changes.
            window.rrIsActiveChatFor = function (requestId) {
                return window.location.pathname === '/tracking/' + requestId
                    || String(requestId) === '{{ $req->id }}';
            };
        })();
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
    <script src="{{ asset('assets/user/js/tracking.js') }}"></script>

    {{-- Pusher + Ride Chat WS --}}
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="{{ asset('assets/user/js/ride_chat.js') }}"></script>

    {{-- ── Dedicated real-time tracking connection ─────────────────────────
         Independent Pusher instance — does NOT depend on ride_chat.js or
         window._userPusher.  Subscribes to the public contact.admin channel
         to receive live driver GPS pings and status updates.
    ────────────────────────────────────────────────────────────────────── --}}
    <script>
    (function () {
        'use strict';

        function startTrackingConnection() {
            if (typeof Pusher === 'undefined') {
                /* Pusher not loaded yet — retry */
                setTimeout(startTrackingConnection, 300);
                return;
            }

            var cfg = window._rrReverb || {};
            if (!cfg.key) return;

            var _tp;
            try {
                _tp = new Pusher(cfg.key, {
                    wsHost:            cfg.wsHost,
                    wsPort:            cfg.wsPort,
                    wssPort:           cfg.wssPort,
                    forceTLS:          cfg.forceTLS,
                    enabledTransports: cfg.enabledTransports || ['ws'],
                    cluster:           'mt1',
                    disableStats:      true,
                });
            } catch (err) {
                console.warn('[Reverb/Tracking] Pusher init failed:', err.message || err);
                return;
            }

            var adminCh = _tp.subscribe('contact.admin');

            /* ── Live ambulance GPS pings ─────────────────────────────── */
            adminCh.bind('driver.location.updated', function (e) {
                var td = window.trackingData;
                if (!td) return;

                /* Filter: only update for this ride's driver (if driverId known) */
                if (td.driverId && String(e.driver_id) !== String(td.driverId)) return;

                var lat = parseFloat(e.lat);
                var lng = parseFloat(e.lng);
                if (isNaN(lat) || isNaN(lng)) return;

                /*
                 * Delegate to tracking.js handler — it manages:
                 *   - smooth marker movement (setLatLng)
                 *   - grey breadcrumb trail growth
                 *   - route recalc with off-route detection
                 *   - _trackingStopped guard (no-ops after completion)
                 */
                window.handleDriverGpsPing(lat, lng);
            });

            /* ── Status / driver-assignment updates ──────────────────── */
            adminCh.bind('request.status.updated', function (e) {
                var td = window.trackingData;
                if (!td || String(e.request_id) !== String(td.requestId)) return;

                var s = String(e.status);

                /* De-duplicate: ride_chat.js private channel is primary path;
                   only act here if that channel hasn't already updated status */
                if (String(td.status) === s) return;

                window.handleStatusUpdate({
                    status:     s,
                    driver_lat: e.driver_lat  || null,
                    driver_lng: e.driver_lng  || null,
                });

                /* Store driverId so GPS pings can be filtered correctly */
                if (e.driver_id) td.driverId = e.driver_id;
            });

            _tp.connection.bind('connected', function () {
                console.log('[Reverb/Tracking] Connected — subscribed to contact.admin');
            });
            _tp.connection.bind('error', function (err) {
                console.warn('[Reverb/Tracking] Connection error:', err);
            });
        }

        document.addEventListener('DOMContentLoaded', startTrackingConnection);
    })();
    </script>

    {{-- ── Chat FAB toggle logic ─────────────────────────────────────────── --}}
    <script>
    (function () {
        var fab       = document.getElementById('rrChatFab');
        var panel     = document.getElementById('rrChatPanel');
        var closeBtn  = document.getElementById('rrChatClose');
        var fabIcon   = document.getElementById('rrChatFabIcon');
        var badge     = document.getElementById('rrChatFabBadge');
        var unread    = 0;
        var isOpen    = false;

        function openChat() {
            isOpen = true;
            panel.style.display = 'flex';
            requestAnimationFrame(function () {
                panel.classList.add('rr-chat-panel--open');
            });
            fabIcon.className = 'fa fa-times';
            fab.title = 'Close Ride Chat';
            clearBadge();
            var msgList = document.getElementById('rcMsgList');
            if (msgList) msgList.scrollTop = msgList.scrollHeight;
            var inp = document.getElementById('rcInput');
            if (inp) setTimeout(function(){ inp.focus(); }, 200);
        }

        function closeChat() {
            isOpen = false;
            panel.classList.remove('rr-chat-panel--open');
            fabIcon.className = 'fa fa-comments';
            fab.title = 'Open Ride Chat';
            setTimeout(function () {
                if (!isOpen) panel.style.display = 'none';
            }, 250);
        }

        // ── Cross-tab sync (BroadcastChannel) ─────────────────────────────────
        // Broadcasts FAB badge state to other user pages/tabs so they can update
        // a nav-level unread badge without requiring their own WS subscription.
        // 'bump'  → new message received, not-yet-read
        // 'clear' → conversation was opened and badge was reset
        var _rrUserChatBc;
        try { _rrUserChatBc = new BroadcastChannel('rr_user_chat_sync'); } catch (ex) {}

        function broadcastUserChatSync(type) {
            if (!_rrUserChatBc) return;
            try { _rrUserChatBc.postMessage({ type: type }); } catch (ex) {}
        }

        // Listen for clear events broadcast by OTHER tracking tabs (so FAB badge stays
        // in sync when a sibling tab opens the chat).
        if (_rrUserChatBc) {
            _rrUserChatBc.onmessage = function (ev) {
                var d = ev.data || {};
                // Only act on clear — bump is NOT forwarded to other tracking tabs because
                // each tracking tab gets the WS event independently (no double-count).
                if (d.type === 'user_chat_clear') {
                    unread = 0;
                    badge.textContent = '0';
                    badge.classList.remove('visible');
                }
            };
        }

        function clearBadge() {
            unread = 0;
            badge.textContent = '0';
            badge.classList.remove('visible');
            broadcastUserChatSync('user_chat_clear');
        }

        function bumpBadge() {
            if (isOpen) return;
            unread++;
            badge.textContent = unread > 9 ? '9+' : unread;
            badge.classList.add('visible');
            fab.style.animation = 'none';
            fab.offsetHeight;
            fab.style.animation = 'rrFabPulse .4s ease';
            // Notify other user pages (home, profile, etc.) so they can show a nav badge.
            broadcastUserChatSync('user_chat_bump');
        }

        fab.addEventListener('click', function () {
            isOpen ? closeChat() : openChat();
        });

        if (closeBtn) closeBtn.addEventListener('click', closeChat);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) closeChat();
        });

        window.rrChatNotifyNewMessage = bumpBadge;
    })();
    </script>
    <style>
        @keyframes rrFabPulse {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.18); }
            70%  { transform: scale(.92); }
            100% { transform: scale(1); }
        }
    </style>
@endsection
