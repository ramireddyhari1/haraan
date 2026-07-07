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
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::middleware('auth.jwt')->get('/me', [AuthController::class, 'me']);
});

Route::prefix('auth/whatsapp')->controller(WhatsAppAuthController::class)->group(function (): void {
    Route::post('/request', 'requestOtp');
    Route::post('/verify', 'verifyOtp');
});

Route::prefix('auth/email')->controller(EmailAuthController::class)->group(function (): void {
    Route::post('/request', 'requestOtp');
    Route::post('/verify', 'verifyOtp');
    Route::post('/complete', 'completeProfile'); // new user: name + date of birth after verify
});

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
    Route::get('/{id}', [EventsController::class, 'show']);

    Route::middleware('auth.jwt')->group(function (): void {
        Route::post('/', [EventsController::class, 'store']);
        Route::put('/{id}', [EventsController::class, 'update']);
    });
});

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
    return response()->json($posters);
});

// -------------------------------------------------------------------------
//  In-app support chat — user <-> admin. Backed by SupportController; the
//  admin side lives in the Filament "Support" resource. Requires a signed-in
//  user (JWT); the app opens the thread and polls it while the chat is open.
// -------------------------------------------------------------------------
Route::middleware('auth.jwt')->prefix('support')->controller(\App\Http\Controllers\Api\SupportController::class)->group(function (): void {
    Route::get('/thread', 'thread');
    Route::post('/messages', 'send');
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
//  Bookings
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('bookings')->group(function (): void {
    Route::get('/', [BookingsController::class, 'index']);
    Route::post('/venue', [BookingsController::class, 'storeVenue']);
    Route::get('/{id}', [BookingsController::class, 'show']);
    Route::post('/', [BookingsController::class, 'store']);
    Route::patch('/{id}/cancel', [BookingsController::class, 'cancel']);
});
