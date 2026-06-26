# Rapid Rescue — Ambulance System

A real-time emergency ambulance dispatching and communication platform built with **Laravel 12**, **SQLite**, **Laravel Reverb** (WebSocket), and **Pusher.js**.

---

## Table of Contents

1. [Overview](#overview)
2. [Features by Role](#features-by-role)
3. [Requirements](#requirements)
4. [Setup](#setup)
5. [Environment Configuration](#environment-configuration)
6. [Google reCAPTCHA Setup](#google-recaptcha-setup)
7. [WebSocket Configuration](#websocket-configuration)
8. [Running the Application](#running-the-application)
9. [Default Credentials](#default-credentials)
10. [Project Structure](#project-structure)
11. [Real-Time Events](#real-time-events)
12. [Database Tables](#database-tables)
13. [Troubleshooting](#troubleshooting)

---

## Overview

Rapid Rescue manages the full lifecycle of an emergency ambulance request:

1. A **user** submits an emergency request with pickup location and hospital
2. An **admin** reviews pending requests, finds the nearest available driver via Haversine distance, and dispatches
3. The **driver** accepts the assignment and progresses through status steps in real time
4. The user tracks the ambulance live on a map and can chat with the driver and admin throughout the ride

All status updates, location changes, and messages are pushed instantly via WebSocket — no page refresh needed.

---

## Features by Role

### Guest / Public
- View services, ambulance fleet, testimonials, FAQs, and branch locations
- Submit an emergency request (pickup via Google Maps autocomplete, emergency type, target hospital)
- Contact form with automated email confirmation for guest submissions
- First Aid Guide, Terms of Service, and Privacy Policy pages
- Sign up with email verification (6-digit code), login, and password reset
- **Google reCAPTCHA v2** bot protection on all public forms (registration, login, contact, password reset)

### Authenticated User
- Profile management — name, address, phone, profile picture
- Medical card — blood type, allergies, medications, emergency contact
- Booking history with status badges for all past and active rides
- Live ambulance tracking on a map after dispatch
- Real-time ride chat with the assigned driver and admin
- Real-time bell notifications for ride status changes and new chat messages
- Contact thread — ongoing support chat per inquiry with admin replies

### Driver
- Dashboard with personal stats (total, completed, active rides)
- Toggle online/offline availability
- Real-time dispatch notifications — accept or reject incoming assignments
- Active ride lifecycle: **Dispatched → En Route → Arrived → Transporting → Completed**
- Automatic GPS location sharing while online or on a ride
- View nearby pending emergency requests
- Ride chat with user and admin during active assignment
- Heartbeat-based online status (auto-marks offline after 35s without heartbeat)
- Responsive mobile layout with sidebar close button, dark overlay, and footer

### Admin
- Operations dashboard — fleet status, active logs, visitor statistics
- Live monitoring map — all online drivers, statuses, and active ride routes
- Emergency dispatch — review pending requests, sort nearby drivers by Haversine distance, assign ambulance and driver
- Fleet management — full CRUD for ambulances, drivers, and admin accounts
- User management — view registered users and their medical cards
- CMS — manage services, testimonials, FAQs, and branch details
- Contact and inquiry resolution — real-time chat thread per inquiry, mark as resolved
- Audit logs — system event logs and visitor traffic logs
- Responsive mobile layout with sidebar close button, dark overlay, and footer

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | >= 8.2 |
| Composer | >= 2.x |
| SQLite | 3.x (bundled with PHP) |

> **npm is not required.** All frontend assets are pre-built in `public/assets/`. No build step needed.

---

## Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd rapid-rescue

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# . Run migrations
php artisan migrate

```

---

## Environment Configuration

```env
APP_NAME="Rapid Rescue"
APP_ENV=local
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=true
APP_URL=http://localhost:5000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rapidrescue
DB_USERNAME=root
DB_PASSWORD=

# Broadcasting
BROADCAST_CONNECTION=reverb

# Laravel Reverb — WebSocket server
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Queue
QUEUE_CONNECTION=sync

# Google reCAPTCHA v2
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here

# Mail (for email verification and contact confirmations)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=you@example.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=you@example.com
MAIL_FROM_NAME="Rapid Rescue"
```

---

## Google reCAPTCHA Setup

All public forms (registration, login, contact, password reset) are protected by **Google reCAPTCHA v2 — "I'm not a robot" Checkbox**. Both frontend rendering and backend verification are implemented.

### Getting your keys

1. Go to [google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create)
2. Choose **reCAPTCHA v2 → "I'm not a robot" Checkbox**
3. Add your domain (e.g. `yourapp.replit.app` or `yourdomain.com`). Also add `localhost` for local development
4. Copy the **Site Key** and **Secret Key**

### Adding keys to your project

Update `.env`:

```env
RECAPTCHA_SITE_KEY=your_real_site_key
RECAPTCHA_SECRET_KEY=your_real_secret_key
```

Then clear the config cache:

```bash
php artisan config:clear
```

### How it works

| Layer | Behaviour |
|---|---|
| Frontend | reCAPTCHA widget rendered on each form; submission blocked client-side if unticked |
| Backend | `RecaptchaService::verify()` calls Google's `siteverify` API before any form processing |
| Failure response | HTTP `422` with `errors.recaptcha` message if token is missing or invalid |

> **Default test keys** are pre-configured in `config/recaptcha.php` and work out of the box for local development. They always pass verification but display a "for testing purposes only" banner. Replace with real keys before going to production.

---

## WebSocket Configuration

The frontend Pusher.js client reads its connection settings from three `.env` variables:

| Variable | Local | Production |
|---|---|---|
| `REVERB_HOST` | `127.0.0.1` | `yourdomain.com` |
| `REVERB_PORT` | `8080` | `443` |
| `REVERB_SCHEME` | `http` | `https` |

### Production (VPS / custom domain)

```env
APP_URL=https://yourdomain.com
REVERB_HOST=yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
```

Put Reverb behind your existing nginx SSL proxy:

```nginx
# Inside your HTTPS server {} block
location /app/ {
    proxy_pass         http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade    $http_upgrade;
    proxy_set_header   Connection "Upgrade";
    proxy_set_header   Host       $host;
    proxy_read_timeout 3600s;
}
```

---

## Running the Application

Two processes must run simultaneously.

**Terminal 1 — Web server:**
```bash
php artisan serve --host=0.0.0.0 --port=5000
```

**Terminal 2 — Reverb WebSocket server:**
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Open `http://localhost:5000` in your browser.

---

## Project Structure

```
app/
├── Events/                     Real-time broadcast event definitions
├── Helpers/helpers.php         Global helper functions
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              Admin panel controllers
│   │   ├── Driver/             Driver portal controllers
│   │   └── User/               User-facing controllers
│   └── Middleware/             Auth, heartbeat, stale-driver cleanup
├── Mail/                       Mailable classes (verification, contact, password reset)
├── Models/
│   ├── Admin/                  Admin, Branch
│   ├── Driver/                 Driver
│   ├── User/                   User, UserDetail, MedicalCard,
│   │                           EmailVerificationCode, PasswordResetCode
│   ├── EmergencyRequest.php
│   ├── RideChatMessage.php
│   ├── RideChatNotification.php
│   ├── RideStatusNotification.php
│   └── VisitorLog.php
└── Services/
    └── RecaptchaService.php    Google reCAPTCHA v2 token verification

config/
├── auth.php                    Three guards: admin, driver, users
├── broadcasting.php            Default: reverb
├── database.php                Default: sqlite
├── recaptcha.php               reCAPTCHA site key and secret key
└── reverb.php                  Reverb server settings

database/
├── migrations/                 All migration files
└── seeders/                    DatabaseSeeder — demo admin, driver, user, and requests

public/assets/
├── admin/                      Admin panel CSS, JS, images
├── driver/                     Driver portal CSS, JS, images
└── user/                       User-facing CSS, JS, images

resources/views/
├── admin/                      Admin Blade templates
├── driver/                     Driver Blade templates
├── emails/                     Transactional email templates
└── user/                       User Blade templates (auth, pages, layouts)

routes/
├── channels.php                Private channel authorization
└── web.php                     All application routes (user, driver, admin)
```

---

## Real-Time Events

| Event | Channel | Trigger |
|---|---|---|
| `EmergencyRequestSubmitted` | `contact.admin` | User submits emergency request |
| `NewUserRegistered` | `contact.admin` | New user completes registration |
| `DriverLocationUpdated` | `private-emergency.{id}` | Driver sends GPS ping |
| `DriverAccepted` | `private-emergency.{id}` | Driver accepts dispatch |
| `DriverStatusUpdated` | `private-emergency.{id}` | Driver advances ride status |
| `RideChatMessageSent` | `private-ride-chat.{id}` | Any party sends a chat message |
| `RideChatTyping` | `private-ride-chat.{id}` | Typing indicator broadcast |
| `ContactMessageSubmitted` | `contact.admin` | Guest or user submits contact form |
| `AdminReplyNotification` | `private-contact.user.{id}` | Admin replies to a contact inquiry |
| `UserReplySubmitted` | `contact.admin` | User replies in a contact thread |
| `EmergencyRequestStatusUpdated` | `private-contact.user.{id}` | Ride status change pushed to user bell |

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | Registered patients / requesters |
| `user_details` | Extended profile (name, email, phone, avatar, consumer number) |
| `admins` | Admin accounts |
| `drivers` | Driver accounts with live location and online status |
| `ambulances` | Fleet vehicles |
| `emergency_requests` | Core dispatch records |
| `ride_chat_messages` | In-ride messages (user ↔ driver ↔ admin) |
| `ride_chat_notifications` | Unread chat notification tracking |
| `ride_status_notifications` | Per-user bell notifications for status changes |
| `contact_messages` | General contact form submissions |
| `contact_replies` | Admin and user replies on contact threads |
| `medical_cards` | User health details (blood type, allergies, medications) |
| `email_verification_codes` | 6-digit codes for signup email verification |
| `password_reset_codes` | 6-digit codes for password reset flow |
| `branches` | Organisation branch contact details |
| `services` | CMS: service listing cards |
| `testimonials` | CMS: testimonial cards |
| `faqs` | CMS: FAQ entries |
| `feedback` | Post-ride user feedback |
| `sessions` | PHP session storage |
| `cache` | Laravel cache table |
| `visitor_logs` | IP, browser, device, and page analytics |
| `logs` | System audit event log |

---

## Troubleshooting

### WebSocket not connecting

Open browser DevTools console and run:
```javascript
console.log(window._rrReverb);
```
`wsHost`, `wsPort`, and `forceTLS` must match your `REVERB_HOST`, `REVERB_PORT`, and `REVERB_SCHEME` values.

### Reverb server not running
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Private channel 403
- Ensure the user is logged in
- Confirm the CSRF meta tag is present in the layout (`<meta name="csrf-token">`)
- Check `routes/channels.php` has the channel registered with the correct guard

### Events not arriving in real time
- Confirm `BROADCAST_CONNECTION=reverb` in `.env`
- With `QUEUE_CONNECTION=sync` events fire immediately — no queue worker needed
- With `QUEUE_CONNECTION=database` run: `php artisan queue:work`

### forceTLS error on local
Confirm `REVERB_SCHEME=http` in `.env`. When scheme is `http`, `forceTLS` is automatically set to `false`.

### reCAPTCHA "testing purposes only" banner
This appears when using the default test keys. Replace them with your real keys from [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin), then run `php artisan config:clear`.

### reCAPTCHA always failing on production
- Ensure your live domain is registered in the reCAPTCHA admin console under **Domains**
- Verify `RECAPTCHA_SECRET_KEY` in `.env` matches the secret key (not the site key)
- Run `php artisan config:clear` after any `.env` changes
