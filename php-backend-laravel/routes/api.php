<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\EmailAuthController;
use App\Http\Controllers\Api\WhatsAppAuthController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\DistrictsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\LeaderboardsController;
use App\Http\Controllers\Api\LiveMatchController;
use App\Http\Controllers\Api\MatchesController;
use App\Http\Controllers\Api\PlayersController;
use App\Http\Controllers\Api\RazorpayController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| RESTful JSON API for the Haraan platform.
| All routes are automatically prefixed with /api by Laravel.
|
*/

Route::get('/health', static fn () => response()->json([
    'status'    => 'success',
    'message'   => 'Haraan Laravel API is running',
    'timestamp' => now()->toIso8601String(),
]));

// Remote config / feature flags — anonymous-safe; resolved per viewer when logged in.
Route::middleware('auth.jwt.optional')->get('/config', [ConfigController::class, 'index']);

// Localization bundles — public; app overlays these on its built-in strings.
Route::get('/i18n', [\App\Http\Controllers\Api\I18nController::class, 'index']);
Route::get('/i18n/{locale}', [\App\Http\Controllers\Api\I18nController::class, 'show']);

// -------------------------------------------------------------------------
//  Authentication
// -------------------------------------------------------------------------

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    // Email + password for the Android app — same semantics as the website's
    // /auth/password (unknown email signs up), but returns a JWT.
    Route::post('/password', [AuthController::class, 'passwordLogin'])->middleware('throttle:auth');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::middleware('auth.jwt')->get('/me', [AuthController::class, 'me']);
});

Route::prefix('auth/whatsapp')->controller(WhatsAppAuthController::class)->group(function (): void {
    Route::post('/request', 'requestOtp')->middleware('throttle:otp');
    Route::post('/verify', 'verifyOtp')->middleware('throttle:auth');
});

Route::prefix('auth/email')->controller(EmailAuthController::class)->group(function (): void {
    Route::post('/request', 'requestOtp')->middleware('throttle:otp');
    Route::post('/verify', 'verifyOtp')->middleware('throttle:auth');
    Route::post('/complete', 'completeProfile'); // new user: name + date of birth after verify
});

// "Continue with Google" — the app posts a Google ID token; we verify it and log in.
Route::post('/auth/google', [\App\Http\Controllers\Api\GoogleAuthController::class, 'login'])->middleware('throttle:auth');

// -------------------------------------------------------------------------
//  Users (admin-only)
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('users')->group(function (): void {
    Route::get('/', [UsersController::class, 'index']);
    Route::get('/partners', [UsersController::class, 'partners']);
    Route::post('/partners', [UsersController::class, 'createPartner']);
    Route::get('/{id}', [UsersController::class, 'show']);
    Route::put('/{id}', [UsersController::class, 'update']);
    Route::patch('/{id}/role', [UsersController::class, 'updateRole']);
    Route::patch('/{id}/status', [UsersController::class, 'updateStatus']);
});

// -------------------------------------------------------------------------
//  Events
// -------------------------------------------------------------------------

Route::prefix('events')->group(function (): void {
    Route::get('/', [EventsController::class, 'index']);
    Route::get('/search', [EventsController::class, 'index']);
    Route::get('/categories', [EventsController::class, 'categories']);
    // optional JWT so a signed-in viewer's open is attributed (user + city) for Views analytics.
    Route::get('/{id}', [EventsController::class, 'show'])->middleware('auth.jwt.optional');

    Route::middleware('auth.jwt')->group(function (): void {
        Route::post('/', [EventsController::class, 'store']);
        Route::put('/{id}', [EventsController::class, 'update']);
    });
});

// Host (organiser) follow — Phase 2. The host object itself rides on each event.
Route::middleware('auth.jwt')->post('/host/{slug}/follow', [\App\Http\Controllers\Api\HostController::class, 'follow']);

// -------------------------------------------------------------------------
//  Venues (public, read-only) — feeds GameHub browse + venue detail screens.
//  Content managed in the Filament "Haraan Control" admin (/control/venues).
// -------------------------------------------------------------------------

Route::prefix('venues')->controller(\App\Http\Controllers\Api\VenuesController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('/{id}', 'show')->whereNumber('id');
});

// Home feed content (ads + For You / Trending), managed in Filament admin.
Route::get('/ads', [\App\Http\Controllers\Api\AppContentController::class, 'ads']);
Route::get('/home/feed', [\App\Http\Controllers\Api\AppContentController::class, 'feed']);
// Admin-curated home composition (ordered typed blocks); anonymous-safe, viewer-resolved.
Route::middleware('auth.jwt.optional')->get('/home/layout', [\App\Http\Controllers\Api\AppContentController::class, 'layout']);

// Login screen posters — public, no auth needed, used by the Android app on launch.
Route::get('/login-posters', static function () {
    $posters = \App\Models\Ad::where('placement', 'login_poster')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get(['id', 'title', 'subtitle', 'image', 'cta_url', 'sort_order']);

    // The app loads `image` straight into Coil as a URL. Filament's FileUpload stores a
    // *relative* path on the public disk (e.g. "login-posters/x.jpg"), while the older Blade
    // admin stored an absolute URL — resolve either to an absolute URL so both render.
    $posters->each(function (\App\Models\Ad $poster): void {
        if ($poster->image && ! str_starts_with($poster->image, 'http')) {
            $poster->image = \Illuminate\Support\Facades\Storage::disk('public')->url($poster->image);
        }
    });

    return response()->json($posters);
});

// -------------------------------------------------------------------------
//  Legal copy (Terms & Conditions, Privacy Policy), admin-editable in /control.
//  Public on purpose: the terms must be readable before you have an account.
// -------------------------------------------------------------------------
Route::get('/legal/{slug}', [\App\Http\Controllers\Api\LegalController::class, 'show']);

// -------------------------------------------------------------------------
//  The signed-in user's own privacy controls (Account → Privacy in the app).
// -------------------------------------------------------------------------
Route::middleware('auth.jwt')->prefix('account')->controller(\App\Http\Controllers\Api\PrivacyController::class)->group(function (): void {
    Route::get('/privacy', 'show');
    Route::put('/privacy', 'update');
});

// -------------------------------------------------------------------------
//  In-app support chat — user <-> admin. Backed by SupportController; the
//  admin side lives in the Filament "Support" resource. Requires a signed-in
//  user (JWT); the app opens the thread and polls it while the chat is open.
// -------------------------------------------------------------------------
Route::middleware('auth.jwt')->prefix('support')->controller(\App\Http\Controllers\Api\SupportController::class)->group(function (): void {
    Route::get('/categories', 'categories');
    Route::get('/thread', 'thread');
    Route::post('/messages', 'send');
});

// -------------------------------------------------------------------------
//  Bell inbox — broadcast notifications from the admin/Haraan team. The admin
//  side lives in the Filament "Notifications" resource; open apps refetch live
//  via the Reverb `notifications` signal, closed apps get FCM (Phase 2).
// -------------------------------------------------------------------------
Route::middleware('auth.jwt')->controller(\App\Http\Controllers\Api\NotificationsController::class)->group(function (): void {
    Route::get('/notifications', 'index');
    Route::post('/notifications/read', 'markRead');
    Route::post('/devices/register', 'registerDevice');
});

// -------------------------------------------------------------------------
//  Live match detail (public, read-only) — feeds the app's Match Details screen
// -------------------------------------------------------------------------

// Optional auth: guests see FEATURED matches; signed-in users also see their
// own district's LOCAL matches; admins see all. ?scope=local|featured|all.
// Private-match share-code lookup — public, the code itself is the grant.
// Declared before the {id} route so "code" isn't captured as an id.
Route::get('/live-matches/code/{code}', [LiveMatchController::class, 'showByCode']);

Route::middleware('auth.jwt.optional')->group(function (): void {
    Route::get('/live-matches', [LiveMatchController::class, 'index']);
    Route::get('/live-matches/{id}', [LiveMatchController::class, 'show'])->whereNumber('id');
});

// -------------------------------------------------------------------------
//  ActionBoard Matches
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('players')->group(function (): void {
    Route::get('/me', [PlayersController::class, 'me']);
    Route::post('/profile', [PlayersController::class, 'saveProfile']);
    Route::post('/avatar', [PlayersController::class, 'uploadAvatar']); // profile photo
    Route::get('/lookup', [PlayersController::class, 'lookup']);
});

// Public (read-only): view any player's ActionBoard profile by Player ID (HRN…).
// Registered after the literal /players/* routes above so it never shadows them.
Route::get('players/{playerId}', [PlayersController::class, 'show']);

// Ranked actions require a complete ActionBoard profile (auth.jwt + gate).
Route::middleware(['auth.jwt', 'actionboard.profile'])->prefix('matches')->group(function (): void {
    Route::post('/', [MatchesController::class, 'store']);
    Route::post('/{id}/team-logo', [MatchesController::class, 'uploadTeamLogo']); // custom team crest
    Route::post('/{id}/complete', [MatchesController::class, 'complete']);
    Route::post('/{id}/confirm', [MatchesController::class, 'confirm']);   // captain confirm → Medium
    Route::post('/{id}/verify', [MatchesController::class, 'verify']);     // organizer/venue → High/Verified
    Route::post('/{id}/dispute', [MatchesController::class, 'dispute']);   // reputation penalty
    Route::post('/{id}/score-action', [MatchesController::class, 'scoreAction']);
});

// -------------------------------------------------------------------------
//  Leaderboards (public, read-only)
// -------------------------------------------------------------------------

Route::prefix('leaderboards')->group(function (): void {
    Route::get('/all-time', [LeaderboardsController::class, 'allTime']);
    Route::get('/{scope}', [LeaderboardsController::class, 'monthly']); // india|state|district
});

// -------------------------------------------------------------------------
//  District Home — local community snapshot (optional auth → defaults to the
//  viewer's own district).
// -------------------------------------------------------------------------

Route::middleware('auth.jwt.optional')->get('/districts/summary', [DistrictsController::class, 'summary']);

// -------------------------------------------------------------------------
//  Payments (Razorpay Standard Checkout)
//  Order creation fixes the amount server-side; verification confirms the
//  signature. The KEY_SECRET never leaves the backend.
// -------------------------------------------------------------------------

Route::post('/create-order', [RazorpayController::class, 'createOrder'])->middleware('throttle:payments');
Route::post('/verify-payment', [RazorpayController::class, 'verifyPayment'])->middleware('throttle:payments');

// -------------------------------------------------------------------------
//  Bookings
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('bookings')->group(function (): void {
    Route::get('/', [BookingsController::class, 'index']);
    Route::post('/venue', [BookingsController::class, 'storeVenue']);
    Route::post('/validate-coupon', [BookingsController::class, 'validateCoupon']);
    // Payment: reserve (store) → confirm after checkout, or release an abandoned hold.
    Route::post('/confirm', [BookingsController::class, 'confirm']);
    Route::post('/release', [BookingsController::class, 'release']);
    Route::get('/{id}', [BookingsController::class, 'show']);
    Route::post('/', [BookingsController::class, 'store']);
    Route::patch('/{id}/cancel', [BookingsController::class, 'cancel']);
});

// -------------------------------------------------------------------------
//  Partner API — dashboard/read endpoints for the partner mobile app.
//  Scoped to the signed-in partner (auth.jwt + auth.partner). Ticket check-in
//  reuses the host-gated /api/bookings/resolve + check-in routes above.
// -------------------------------------------------------------------------

Route::middleware(['auth.jwt', 'auth.partner'])
    ->prefix('partner')
    ->controller(\App\Http\Controllers\Api\PartnerController::class)
    ->group(function (): void {
        Route::get('/overview', 'overview');
        Route::get('/events', 'events');
        Route::get('/events/{id}', 'showEvent')->whereNumber('id');
        Route::get('/events/{id}/analytics', 'eventAnalytics')->whereNumber('id');
        Route::get('/venues', 'venues');
        Route::get('/venues/{id}', 'showVenue')->whereNumber('id');
        Route::get('/venues/{id}/analytics', 'venueAnalytics')->whereNumber('id');
        Route::get('/venues/{id}/day', 'venueDay')->whereNumber('id');
        Route::get('/venues/{id}/slots', 'venueSlots')->whereNumber('id');
        Route::get('/bookings', 'bookings');

        // --- Write actions gated by staff capability (owners hold all) ---
        Route::middleware('partner.can:pricing')->group(function (): void {
            Route::post('/venues/{id}/slots', 'saveSlot')->whereNumber('id');
            Route::post('/venues/{id}/slots/{slotId}', 'saveSlot')->whereNumber('id')->whereNumber('slotId');
            Route::delete('/venues/{id}/slots/{slotId}', 'deleteSlot')->whereNumber('id')->whereNumber('slotId');
        });
        Route::middleware('partner.can:bookings')->group(function (): void {
            Route::post('/venues/{id}/bookings', 'storeOfflineBooking')->whereNumber('id');
            Route::post('/venues/{id}/block', 'blockDate')->whereNumber('id');
            Route::delete('/venues/{id}/block', 'unblockDate')->whereNumber('id');
            Route::patch('/bookings/{id}/cancel', 'cancelBooking')->whereNumber('id');
            Route::post('/bookings/{id}/cancel', 'cancelBooking')->whereNumber('id'); // app (no PATCH)
        });
        Route::middleware('partner.can:checkin')->post('/check-in', 'checkInByCode');
        Route::middleware('partner.can:reports')->get('/reports/bookings', 'bookingsReport');

        // --- Staff management (owner-only; desk persons never hold 'staff') ---
        Route::middleware('partner.can:staff')->group(function (): void {
            Route::get('/staff', 'staff');
            Route::post('/staff', 'createStaff');
            Route::post('/staff/{id}', 'updateStaff')->whereNumber('id');
            Route::delete('/staff/{id}', 'deleteStaff')->whereNumber('id');
        });
    });
