<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminManagerController;
use App\Http\Controllers\Admin\AmbulanceController;
use App\Http\Controllers\Admin\DriverManagementController;
use App\Http\Controllers\Admin\branchesController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\VisitorLogController;
use App\Http\Controllers\Admin\EmergencyController;
use App\Http\Controllers\Admin\RideChatController as AdminRideChatController;
use App\Http\Controllers\User\ContactController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\UserAuthController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\User\EmergencyRequestController;
use App\Http\Controllers\User\RideChatController as UserRideChatController;
use App\Http\Controllers\Driver\DriverAuthController;
use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Driver\DriverRequestController;
use App\Http\Controllers\Driver\DriverSessionController;
use App\Http\Controllers\Driver\DriverChatController;
use Illuminate\Support\Facades\Route;

// ── Public (with visitor tracking)
Route::middleware('track.visitor', 'redirect.auth.admin', 'redirect.auth.driver',)->prefix('/')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
    Route::get('/privacy-policy', [HomeController::class, 'privacy'])->name('privacy');
    Route::get('/first-aid-guide', [HomeController::class, 'firstAid'])->name('first-aid.page');
    Route::post('/check-availability', [UserAuthController::class, 'checkAvailability'])->name('checkAvailability');
    Route::post('/contact/submit', [ContactController::class, 'submit'])->name('contactSubmit');
    Route::post('/emergency/request',  [EmergencyRequestController::class, 'store'])->name('emergencyRequest');
    Route::get('/tracking/{id}',       [HomeController::class, 'tracking'])->name('tracking');

    // ── Real-time content endpoints (public, no visitor tracking)
    Route::prefix('rt')->name('rt.')->group(function () {
        Route::get('/ambulances',   [HomeController::class, 'rtAmbulances'])->name('ambulances');
        Route::get('/testimonials', [HomeController::class, 'rtTestimonials'])->name('testimonials');
    });

    // ── Email verification (public, no auth required)
    Route::prefix('verify-email')->name('email.')->group(function () {
        Route::post('/send', [UserAuthController::class, 'sendVerificationCode'])->name('verify.send');
        Route::post('/resend', [UserAuthController::class, 'resendVerificationCode'])->name('verify.resend');
        Route::post('/verify', [UserAuthController::class, 'verifyEmailCode'])->name('verify.verify');
    });
    
    // ── Password reset (public)
    Route::prefix('forgot-password')->name('password.')->group(function () {
        Route::post('/send', [UserAuthController::class, 'sendPasswordResetCode'])->name('reset.send');
        Route::post('/resend', [UserAuthController::class, 'resendPasswordResetCode'])->name('reset.resend');
        Route::post('/verify', [UserAuthController::class, 'verifyPasswordResetCode'])->name('reset.verify');
        Route::post('/reset', [UserAuthController::class, 'resetPassword'])->name('reset.reset');
    });
});

// ── Authenticated users (block admin & driver from entering user portal)
Route::middleware(['auth:users', 'redirect.auth.admin', 'redirect.auth.driver', 'no.cache'])->group(function () {
    Route::get('/first-aid', [HomeController::class, 'profile'])->name('first-aid');
    Route::get('/contact-history', [HomeController::class, 'profile'])->name('contact-history');
    Route::get('/my-bookings', [HomeController::class, 'profile'])->name('my-bookings');
    Route::post('/logout', [UserAuthController::class, 'logout'])->name('logout');

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [HomeController::class, 'profile'])->name('grid');
        Route::post('/update', [UserProfileController::class, 'update'])->name('update');
        Route::post('/email/send-code', [UserProfileController::class, 'sendEmailChangeCode'])->name('email.sendCode');
        Route::post('/email/resend', [UserProfileController::class, 'resendEmailChangeCode'])->name('email.resend');
        Route::post('/email/verify', [UserProfileController::class, 'verifyEmailChange'])->name('email.verify');
        Route::post('/password/send-code', [UserProfileController::class, 'sendPasswordChangeCode'])->name('password.sendCode');
        Route::post('/password/resend', [UserProfileController::class, 'resendPasswordChangeCode'])->name('password.resend');
        Route::post('/password/change', [UserProfileController::class, 'verifyAndChangePassword'])->name('password.change');
    });

    Route::prefix('medical-card')->name('medicalCard.')->group(function () {
        Route::get('/', [HomeController::class, 'profile'])->name('grid');
        Route::post('/store', [UserProfileController::class, 'storeMedicalCard'])->name('store');
        Route::post('/delete', [UserProfileController::class, 'deleteMedicalCard'])->name('delete');
    });

    Route::prefix('contact-messages')->name('contact.')->group(function () {
        Route::get('/{id}/thread',   [ContactController::class, 'loadUserThread'])->name('thread');
        Route::post('/{id}/reply',   [ContactController::class, 'sendUserReply'])->name('reply');
        Route::post('/{id}/typing',  [ContactController::class, 'userTyping'])->name('typing');
    });

    Route::prefix('ride-chat')->name('ride-chat.')->group(function () {
        Route::get('/{requestId}/thread',         [UserRideChatController::class, 'thread'])->name('thread');
        Route::post('/{requestId}/send',          [UserRideChatController::class, 'send'])->name('send');
        Route::post('/{requestId}/typing',        [UserRideChatController::class, 'typing'])->name('typing');
    });
});

// ── User guests only (cross-guard: redirect admin away from user auth pages)
Route::middleware(['guest:users', 'redirect.auth.admin', 'redirect.auth.driver'])->group(function () {
    Route::get('/login', [UserAuthController::class, 'login'])->name('login');
    Route::get('/signup', [UserAuthController::class, 'signup'])->name('signup');
    Route::post('/signup-api', [UserAuthController::class, 'register'])->name('signupApi');
    Route::post('/login-api', [UserAuthController::class, 'loginSubmit'])->name('loginApi');
});

// ── Admin portal
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['guest:admin', 'redirect.auth.user', 'redirect.auth.driver'])->group(function () {
        Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
        Route::post('/login-api', [AdminAuthController::class, 'loginSubmit'])->name('loginApi');
    });

    Route::middleware(['auth:admin', 'redirect.auth.user', 'redirect.auth.driver', 'no.cache'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/logs', [AdminDashboardController::class, 'logs'])->name('logs');
        Route::get('/visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs');
        Route::get('/live-monitoring', [\App\Http\Controllers\Admin\LiveMonitoringController::class, 'index'])->name('live-monitoring');
        Route::get('/fleet-stats',     [AdminDashboardController::class, 'fleetStats'])->name('fleet-stats');
        Route::post('/drivers/sweep-stale', [DriverSessionController::class, 'sweepStaleEndpoint'])->name('drivers.sweepStale');

        Route::prefix('ambulances')->name('ambulances.')->group(function () {
            Route::get('/', [AmbulanceController::class, 'index'])->name('grid');
            Route::post('/store', [AmbulanceController::class, 'add'])->name('store');
            Route::post('/update/{id}', [AmbulanceController::class, 'update'])->name('update');
            Route::post('/delete/{id}', [AmbulanceController::class, 'delete'])->name('delete');
        });

        Route::prefix('driver')->name('driver.')->group(function () {
            Route::get('/',             [DriverManagementController::class, 'index'])->name('grid');
            Route::get('/check-username', [DriverManagementController::class, 'checkUsername'])->name('checkUsername');
            Route::post('/add',       [DriverManagementController::class, 'add'])->name('add');
            Route::post('/update/{id}', [DriverManagementController::class, 'update'])->name('update');
            Route::post('/delete/{id}', [DriverManagementController::class, 'delete'])->name('delete');
        });
        
        Route::prefix('emergency')->name('emergency.')->group(function () {
            Route::get('/',                  [EmergencyController::class, 'index'])->name('grid');
            Route::get('/past-rides',        [EmergencyController::class, 'pastRides'])->name('past-rides');
            Route::get('/show/{id}',         [EmergencyController::class, 'show'])->name('show');
            Route::get('/nearby-drivers',    [EmergencyController::class, 'nearbyDrivers'])->name('nearbyDrivers');
            Route::post('/dispatch/{id}',    [EmergencyController::class, 'dispatch'])->name('dispatch');
            Route::post('/delete/{id}',      [EmergencyController::class, 'delete'])->name('delete');
        });

        Route::prefix('ride-chats')->name('ride-chats.')->group(function () {
            Route::get('/',                               [AdminRideChatController::class, 'index'])->name('grid');
            Route::get('/{requestId}/thread',             [AdminRideChatController::class, 'thread'])->name('thread');
            Route::post('/{requestId}/send',              [AdminRideChatController::class, 'send'])->name('send');
            Route::post('/{requestId}/typing',            [AdminRideChatController::class, 'typing'])->name('typing');
        });

        Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
            Route::get('/',               [ContactMessageController::class, 'index'])->name('grid');
            Route::get('/{id}/thread',    [ContactMessageController::class, 'loadThread'])->name('thread');
            Route::post('/{id}/reply',    [ContactMessageController::class, 'sendReply'])->name('reply');
            Route::post('/{id}/resolve',  [ContactMessageController::class, 'resolve'])->name('resolve');
            Route::post('/{id}/typing',   [ContactMessageController::class, 'adminTyping'])->name('typing');
        });

        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/', [ContentController::class, 'services'])->name('grid');
            Route::post('/add', [ContentController::class, 'serAdd'])->name('add');
            Route::post('/update/{id}', [ContentController::class, 'serUpdate'])->name('update');
            Route::post('/delete/{id}', [ContentController::class, 'serDelete'])->name('delete');
        });

        Route::prefix('testimonials')->name('testimonials.')->group(function () {
            Route::get('/', [ContentController::class, 'testimonials'])->name('grid');
            Route::post('/add', [ContentController::class, 'testiAdd'])->name('add');
            Route::post('/update/{id}', [ContentController::class, 'testiUpdate'])->name('update');
            Route::post('/delete/{id}', [ContentController::class, 'testiDelete'])->name('delete');
        });

        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/', [ContentController::class, 'faqs'])->name('grid');
            Route::post('/add', [ContentController::class, 'faqsAdd'])->name('add');
            Route::post('/update/{id}', [ContentController::class, 'faqsUpdate'])->name('update');
            Route::post('/delete/{id}', [ContentController::class, 'faqsDelete'])->name('delete');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('grid');
            Route::get('/view/{id}', [UserManagementController::class, 'view'])->name('view');
        });

        Route::prefix('admins')->name('admins.')->group(function () {
            Route::get('/', [AdminManagerController::class, 'index'])->name('grid');
            Route::get('/check-username', [AdminManagerController::class, 'checkUsername'])->name('checkUsername');
            Route::post('/add', [AdminManagerController::class, 'add'])->name('add');
            Route::post('/update/{id}', [AdminManagerController::class, 'update'])->name('update');
            Route::post('/delete/{id}', [AdminManagerController::class, 'delete'])->name('delete');
        });

        Route::prefix('branch')->name('branch.')->group(function () {
            Route::get('/', [branchesController::class, 'index'])->name('grid');
            Route::post('/add', [branchesController::class, 'add'])->name('add');
            Route::post('/update/{id}', [branchesController::class, 'update'])->name('update');
            Route::post('/delete/{id}', [branchesController::class, 'delete'])->name('delete');
        });
    });
});

Route::prefix('driver')->name('driver.')->group(function () {
    Route::middleware(['guest:driver', 'redirect.auth.user', 'redirect.auth.admin'])->group(function () {
        Route::get('/login',      [DriverAuthController::class, 'login'])->name('login');
        Route::post('/login-api', [DriverAuthController::class, 'loginSubmit'])->name('loginApi');
    });
    Route::middleware(['auth:driver', 'redirect.auth.user', 'redirect.auth.admin', 'no.cache'])->group(function () {
        Route::get('/dashboard',                          [DriverDashboardController::class, 'index'])->name('dashboard');
        Route::get('/requests',                           [DriverDashboardController::class, 'requests'])->name('requests');
        Route::get('/past-rides',                         [DriverDashboardController::class, 'pastRides'])->name('past-rides');
        Route::get('/profile',                            [DriverDashboardController::class, 'profile'])->name('profile');
        Route::post('/profile/update',                   [DriverDashboardController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/password',                 [DriverDashboardController::class, 'changePassword'])->name('profile.password');
        Route::post('/location',                         [\App\Http\Controllers\Driver\DriverLocationController::class, 'update'])->name('location.update');
        Route::post('/logout',                            [DriverAuthController::class, 'logout'])->name('logout');

        Route::get('/requests/active',                    [DriverRequestController::class, 'active'])->name('requests.active');
        Route::get('/requests/pending-nearby',            [DriverRequestController::class, 'pendingNearby'])->name('requests.pendingNearby');
        Route::post('/requests/{id}/status',              [DriverRequestController::class, 'updateStatus'])->name('requests.updateStatus');
        Route::post('/requests/{id}/accept',              [DriverRequestController::class, 'acceptDispatch'])->name('requests.accept');
        Route::post('/requests/{id}/reject',              [DriverRequestController::class, 'rejectDispatch'])->name('requests.reject');
        Route::get('/requests/stats',                     [DriverRequestController::class, 'stats'])->name('requests.stats');

        Route::post('/availability',                      [DriverRequestController::class, 'updateAvailability'])->name('availability');

        Route::get('/ride-chats',                                    [DriverChatController::class, 'index'])->name('ride-chats');
        Route::get('/ride-chats/{requestId}/thread',             [DriverChatController::class, 'thread'])->name('ride-chats.thread');
        Route::post('/ride-chats/{requestId}/send',              [DriverChatController::class, 'send'])->name('ride-chats.send');
        Route::post('/ride-chats/{requestId}/typing',            [DriverChatController::class, 'typing'])->name('ride-chats.typing');

        Route::post('/heartbeat',  [DriverSessionController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/tab-close',  [DriverSessionController::class, 'tabClose'])->name('tabClose');
    });
});