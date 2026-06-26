{{-- Footer --}}
<footer class="rr-footer">
    <div class="container" style="position:relative;">
        <div class="row g-5 mb-5">
            <div class="col-md-6 col-lg-4 rr-footer__col rr-footer__brand">
                <a class="rr-logo d-inline-flex align-items-center mb-3" href="{{ route('home') }}">
                    <span class="rr-logo-mark">
                        <img src="{{ asset('assets/user/img/logo/logo.png') }}" alt="Rapid Rescue"
                            style="filter:brightness(0) invert(1);width:46px;">
                    </span>

                    <span class="rr-logo-text ms-2">
                        <strong style="color:#fff;">Rapid <span style="color:#fff;">Rescue</span></strong>
                        <small class="d-block" style="color:#fff;">Ambulance System</small>
                    </span>
                </a>

                <p>Trusted 24/7 emergency response. We bring rapid, professional care to your doorstep — whenever,
                    wherever you need it.</p>

                <div class="rr-footer__social">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2 rr-footer__col">
                <h5>Explore</h5>
                <a href="javascript:void(0)" data-scroll="features">Features</a>
                <a href="javascript:void(0)" data-scroll="ambulances">Ambulances</a>
                <a href="javascript:void(0)" data-scroll="services">Services</a>
                <a href="javascript:void(0)" data-scroll="about-us">About</a>
                <a href="javascript:void(0)" data-scroll="testimonials">Testimonials</a>
                <a href="javascript:void(0)" data-scroll="faq">FAQ</a>
            </div>

            <div class="col-6 col-lg-2 rr-footer__col">
                <h5>Support</h5>
                <a href="/?s=contact-form" data-section="contact-form">Contact Us</a>
                <a href="{{ route('first-aid.page') }}">First Aid Guide</a>
                @auth('users')
                    <a href="{{ route('profile.grid') }}">My Account</a>
                    <a href="javascript:void(0)" onclick="window.location.href = window.routes.contactHistory">My Contact
                        History</a>
                @else
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('signup') }}">Signup</a>
                @endauth
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of Service</a>
            </div>

            <div class="col-md-6 col-lg-4 rr-footer__col">
                <h5>Get in Touch</h5>
                <div class="rr-footer__contact-row"><i
                        class="fas fa-map-marker-alt"></i><span>{{ $contactInfo->address ?? 'XYZ Corporate Office, DHA Phase 6, Karachi, Pakistan' }}</span>
                </div>
                <div class="rr-footer__contact-row"><i
                        class="fas fa-phone-alt"></i><span>{{ $contactInfo->phone ?? '+92 xxx xxxxxxx' }}</span></div>
                <div class="rr-footer__contact-row"><i
                        class="fas fa-envelope"></i><span>{{ $contactInfo->email ?? 'info@rapidrescue.com' }}</span>
                </div>
                <div class="rr-footer__contact-row"><i class="fas fa-clock"></i><span>24/7 Emergency Response</span>
                </div>
            </div>
        </div>

        <div class="rr-copyright">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>&copy; {{ date('Y') }} <a href="https://daniyal-khan.com/">Daniyal Khan</a>. All rights reserved.
                </div>
                <div>Designed for saving lives, every second of every day.</div>
            </div>
        </div>
    </div>
</footer>

<!-- BOOTSTRAP JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
</script>

<!-- CUSTOM JS -->
<script src="{{ asset('assets/user/js/script.js') }}"></script>

<script>
    @if (session('success'))
        showAlert('success', @json(session('success')));
    @elseif (session('error'))
        showAlert('error', @json(session('error')));
    @endif
</script>

@yield('extra_scripts')
@stack('scripts')

</body>

</html>
