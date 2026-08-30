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

// On-the-fly UI translation (Google Cloud Translation proxy; server-side key + cache).
Route::middleware('throttle:120,1')->post('/translate', [\App\Http\Controllers\Api\TranslationController::class, 'translate']);

// Open matches near the viewer looking for players (browse public; requesting needs auth).
Route::middleware('auth.jwt.optional')->get('/matches/open', [\App\Http\Controllers\Api\MatchJoinController::class, 'open']);

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
    // Now behind auth.jwt: logout REVOKES the caller's sessions, so it has to know who
    // is calling. A already-invalid token gets a 401, which is the honest answer —
    // there is nothing left to revoke.
    Route::middleware('auth.jwt')->post('/logout', [AuthController::class, 'logout']);
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

// "Continue with phone" — WhatsApp (MSG91) first. `start` answers {channel} and the
// app drives whichever it names; `verify` checks the code locally and returns a JWT.
// Token-based twin of the website's /auth/whatsapp-otp/*. NOT the older
// /api/auth/whatsapp/* — that one keys accounts differently; see PhoneOtpController.
Route::prefix('auth/phone-otp')->controller(\App\Http\Controllers\Api\PhoneOtpController::class)->group(function (): void {
    Route::post('/start', 'start')->middleware('throttle:otp');
    Route::post('/verify', 'verify')->middleware('throttle:auth');
});

// The SMS fallback beneath it — the app posts a Firebase phone-auth ID token; we verify
// it and log in (creating the account on first sign-in). Token-based twin of the
// website's session-based /auth/firebase-phone.
Route::post('/auth/firebase-phone', [\App\Http\Controllers\Api\FirebasePhoneAuthController::class, 'login'])->middleware('throttle:auth');

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
//  Account deletion, in-app half (Account → Privacy → Delete account).
//  Google Play requires an in-app deletion path AND a public web URL; the web
//  twin is Web\AccountDeletionController at /account/delete. Throttled because
//  it is irreversible and there is no reason to call it twice.
// -------------------------------------------------------------------------
Route::middleware(['auth.jwt', 'throttle:6,60'])
    ->delete('/account', [\App\Http\Controllers\Api\AccountController::class, 'destroy']);

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

    // AI-assisted match analysis. Optional auth like the detail it belongs to — the
    // figures are public, and the written read is about the match, not the reader.
    Route::get('/live-matches/{id}/insights', [LiveMatchController::class, 'insights'])->whereNumber('id');

    // Live presence — the eye + count on the match detail header. Optional auth so guests
    // are counted too (keyed by the app's install id, never a raw IP). The code variant
    // covers private matches, whose viewers only ever hold the share code.
    // The limit is per IP for guests, so it has to clear a whole ground's worth of phones
    // on one Wi-Fi beating every 20s (3/min each) — 120/min leaves room for ~40 of them.
    Route::middleware('throttle:120,1')->group(function (): void {
        Route::post('/live-matches/{id}/watching', [LiveMatchController::class, 'watching'])->whereNumber('id');
        Route::post('/live-matches/code/{code}/watching', [LiveMatchController::class, 'watchingByCode']);
        // Who is in the room — verified accounts only; the controller enforces it.
        Route::get('/live-matches/{id}/viewers', [LiveMatchController::class, 'viewers'])->whereNumber('id');
        Route::get('/live-matches/code/{code}/viewers', [LiveMatchController::class, 'viewersByCode']);
    });
});

// -------------------------------------------------------------------------
//  ActionBoard Matches
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('players')->group(function (): void {
    Route::get('/me', [PlayersController::class, 'me']);
    Route::post('/profile', [PlayersController::class, 'saveProfile']);
    // Inline name + bio edit (profile Edit button), lighter than the full /profile save.
    Route::post('/profile/basics', [PlayersController::class, 'updateBasics']);
    Route::post('/avatar', [PlayersController::class, 'uploadAvatar']); // profile photo
    Route::get('/lookup', [PlayersController::class, 'lookup']);
    // Squad building: find a teammate by handle or name instead of their Player ID.
    // NB: '/find', not '/search' — `api/players/search` is already taken by the public
    // website endpoint (PublicWebController@searchPlayers, registered earlier in this
    // file), which has a different shape and no auth. Registering a second /search here
    // silently loses: Laravel matches the first route, so this controller never ran.
    Route::get('/find', [PlayersController::class, 'search']);
    Route::get('/username-available', [PlayersController::class, 'usernameAvailable']);

    // Photo posts (profile grid). Literal '/posts' registered here, before the catch-all
    // {player}/* routes below, so it is never read as a {player} segment.
    Route::post('/posts', [PlayersController::class, 'storePost']);
    Route::post('/posts/{id}/caption', [PlayersController::class, 'updatePost'])->whereNumber('id');
    Route::delete('/posts/{id}', [PlayersController::class, 'destroyPost'])->whereNumber('id');
    // POST twin, same reason /{player}/unfollow has one: Android's HttpURLConnection has
    // no dependable DELETE path, so the app calls this instead of the verb above.
    Route::post('/posts/{id}/delete', [PlayersController::class, 'destroyPost'])->whereNumber('id');

    // ❤ on the Home feed. Three segments, so never read as a {player} by the catch-all below.
    Route::post('/posts/{id}/like', [PlayersController::class, 'likePost'])->whereNumber('id');
    Route::delete('/posts/{id}/like', [PlayersController::class, 'unlikePost'])->whereNumber('id');
    // POST twin — Android's HttpURLConnection has no dependable DELETE path.
    Route::post('/posts/{id}/unlike', [PlayersController::class, 'unlikePost'])->whereNumber('id');

    // Comments + saves (bookmarks) on a post.
    Route::post('/posts/{id}/comments', [PlayersController::class, 'addComment'])->whereNumber('id');
    Route::post('/posts/{id}/save', [PlayersController::class, 'savePost'])->whereNumber('id');
    Route::delete('/posts/{id}/save', [PlayersController::class, 'unsavePost'])->whereNumber('id');
    // POST twin for unsave — HttpURLConnection has no dependable DELETE path.
    Route::post('/posts/{id}/unsave', [PlayersController::class, 'unsavePost'])->whereNumber('id');

    // Social graph. {player} accepts an HRN id or an @handle (see resolvePlayer),
    // so a deep link from a shared profile works without the client translating.
    // Registered inside this literal-prefixed group so they never collide with the
    // catch-all `players/{playerId}` below — the same trap that made
    // `api/players/search` unreachable.
    Route::post('/{player}/follow', [PlayersController::class, 'follow']);
    Route::delete('/{player}/follow', [PlayersController::class, 'unfollow']);
    // POST twin: Android's HttpURLConnection has no usable DELETE-with-body path,
    // and the consumer app speaks the same dialect as the partner check-in route.
    Route::post('/{player}/unfollow', [PlayersController::class, 'unfollow']);
    Route::get('/{player}/followers', [PlayersController::class, 'followers']);
    Route::get('/{player}/following', [PlayersController::class, 'following']);

    // Safety actions. Same POST-twin dialect as unfollow, for the same reason.
    Route::post('/{player}/block', [PlayersController::class, 'block']);
    Route::delete('/{player}/block', [PlayersController::class, 'unblock']);
    Route::post('/{player}/unblock', [PlayersController::class, 'unblock']);
    Route::post('/{player}/report', [PlayersController::class, 'report']);
});

// -------------------------------------------------------------------------
//  Direct messages between players. Separate from /support, which is the
//  player <-> admin desk and cannot represent two players talking.
//  Gated on mutual follow inside DirectMessageService.
// -------------------------------------------------------------------------
Route::middleware('auth.jwt')->prefix('dm')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Api\DirectMessageController::class, 'index']);
    // Mutual follows — the honest contents of a "start chat / add to group" picker.
    Route::get('/eligible', [\App\Http\Controllers\Api\DirectMessageController::class, 'eligible']);
    // Group creation. Registered before /{id}/* so "group" is never read as an id.
    Route::post('/group', [\App\Http\Controllers\Api\DirectMessageController::class, 'group']);
    Route::post('/with/{playerId}', [\App\Http\Controllers\Api\DirectMessageController::class, 'with']);
    Route::get('/{id}/messages', [\App\Http\Controllers\Api\DirectMessageController::class, 'messages'])->whereNumber('id');
    Route::post('/{id}/messages', [\App\Http\Controllers\Api\DirectMessageController::class, 'send'])->whereNumber('id');
    // Unsend your own message — the long-press action. Sender-only, enforced in the service.
    Route::delete('/{id}/messages/{message}', [\App\Http\Controllers\Api\DirectMessageController::class, 'unsend'])->whereNumber('id')->whereNumber('message');
    // React to a message — the emoji row on long press. Same emoji again clears it.
    Route::post('/{id}/messages/{message}/reaction', [\App\Http\Controllers\Api\DirectMessageController::class, 'react'])->whereNumber('id')->whereNumber('message');
    // Forward a message into another of your conversations.
    Route::post('/messages/{message}/forward', [\App\Http\Controllers\Api\DirectMessageController::class, 'forward'])->whereNumber('message');
    Route::post('/{id}/leave', [\App\Http\Controllers\Api\DirectMessageController::class, 'leave'])->whereNumber('id');
});

// Public (read-only): view any player's ActionBoard profile by Player ID (HRN…).
// Registered after the literal /players/* routes above so it never shadows them.
//
// OPTIONAL auth, not none: a guest must still be able to open a shared profile, but a
// signed-in viewer needs `auth_user` populated or `social.is_following` is always false
// and the Follow button opens in the wrong state for everyone you already follow.
// Pairing a second phone to a match. No account required and none assumed — whoever is
// holding the camera phone may never have signed up. The pairing token is short-lived
// and single-use; the session token it mints is what the device uses afterwards, and
// the scorer can revoke it at any time.
Route::get('match-devices/{token}/preview', [\App\Http\Controllers\Api\MatchDeviceController::class, 'preview']);
Route::post('match-devices/claim', [\App\Http\Controllers\Api\MatchDeviceController::class, 'claim']);
Route::post('match-devices/heartbeat', [\App\Http\Controllers\Api\MatchDeviceController::class, 'heartbeat']);
Route::post('match-devices/clips', [\App\Http\Controllers\Api\MatchDeviceController::class, 'uploadClip']);

Route::middleware('auth.jwt.optional')->get('players/{playerId}', [PlayersController::class, 'show']);

// Posts are public — a guest opening a shared profile sees the grid, same as the profile
// itself. OPTIONAL auth, not none: the owner viewing their own grid needs `auth_user` set
// or every cell comes back mine=false and the delete affordance disappears.
// Two segments, so it can never shadow (or be shadowed by) the literal /players/* routes
// or the single-segment players/{playerId} above.
Route::middleware('auth.jwt.optional')->get('players/{player}/posts', [PlayersController::class, 'posts']);

// The Instagram-style Home feed: recent posts from public accounts + a stories strip.
// OPTIONAL auth so a guest can browse; a signed-in viewer gets `liked`/`mine` populated.
// Literal 'posts/feed' — two segments, registered after the /players/* group, so it never
// collides with players/{playerId} or players/{player}/posts.
Route::middleware('auth.jwt.optional')->get('posts/feed', [PlayersController::class, 'feed']);

// A post's comment thread (public, read-only). Optional auth. Two segments after `posts`.
Route::middleware('auth.jwt.optional')->get('posts/{id}/comments', [PlayersController::class, 'comments'])->whereNumber('id');

// The ground a match is played at, and what has happened there before. Declared OUTSIDE
// the ranked-actions group on purpose: that group carries auth.jwt AND the
// actionboard.profile gate, and a ground's record is not private to the people standing
// on it — the Insights tab is readable by anyone watching the match, signed in or not.
Route::get('matches/{id}/ground', [MatchesController::class, 'ground'])->whereNumber('id');

// A player's last five innings with bat and ball. Public: the Insights tab is readable
// by anyone watching, and a player's recent scores are not private to them.
Route::get('players/{playerId}/form', [PlayersController::class, 'form']);

// Ranked actions require a complete ActionBoard profile (auth.jwt + gate).
Route::middleware(['auth.jwt', 'actionboard.profile'])->prefix('matches')->group(function (): void {
    Route::post('/', [MatchesController::class, 'store']);
    // The creator's not-yet-started matches (future kick-offs + skipped-toss ones),
    // for the app's Scheduled tab. Literal segment, so never read as a {id}.
    Route::get('/scheduled', [MatchesController::class, 'scheduled']);

    // Join-a-match: request to join an open match, and the owner's request inbox.
    Route::get('/join-requests', [\App\Http\Controllers\Api\MatchJoinController::class, 'incoming']);
    Route::post('/join-requests/{id}/respond', [\App\Http\Controllers\Api\MatchJoinController::class, 'respond'])->whereNumber('id');
    Route::post('/{id}/join', [\App\Http\Controllers\Api\MatchJoinController::class, 'requestJoin'])->whereNumber('id');
    Route::delete('/{id}/join', [\App\Http\Controllers\Api\MatchJoinController::class, 'cancelJoin'])->whereNumber('id');
    Route::post('/{id}/team-logo', [MatchesController::class, 'uploadTeamLogo']); // custom team crest
    Route::post('/{id}/complete', [MatchesController::class, 'complete']);
    Route::post('/{id}/confirm', [MatchesController::class, 'confirm']);   // captain confirm → Medium
    Route::post('/{id}/verify', [MatchesController::class, 'verify']);     // organizer/venue → High/Verified
    Route::post('/{id}/dispute', [MatchesController::class, 'dispute']);   // reputation penalty
    Route::post('/{id}/score-action', [MatchesController::class, 'scoreAction']);

    // Multi-device match sessions: the scorer opens a pairing for a role, lists what is
    // attached, and can cut any of it loose. Creator-only — see MatchDeviceController.
    Route::post('/{id}/devices', [\App\Http\Controllers\Api\MatchDeviceController::class, 'store']);
    Route::get('/{id}/devices', [\App\Http\Controllers\Api\MatchDeviceController::class, 'index']);
    Route::delete('/{id}/devices/{deviceId}', [\App\Http\Controllers\Api\MatchDeviceController::class, 'destroy']);
    Route::get('/{id}/clips', [\App\Http\Controllers\Api\MatchDeviceController::class, 'clips']);

    // Football / badminton scoring. Deliberately separate from /score-action:
    // cricket keeps its per-ball pipeline, and recordEvent refuses cricket, so a
    // cricket score can never be moved by two competing mechanisms.
    // The client posts WHAT HAPPENED; the server derives the scoreline.
    Route::post('/{id}/events', [MatchesController::class, 'recordEvent']);
    Route::post('/{id}/events/undo', [MatchesController::class, 'undoEvent']);
    // Match-stat tallies (shots, corners, fouls…) — inc/dec by one. Never scoring.
    Route::post('/{id}/stat', [MatchesController::class, 'adjustStat']);
    Route::post('/{id}/sport-state', [MatchesController::class, 'updateSportState']);
});

// Timeline read — signed in, but not creator-gated: anyone who can see the match
// can see how it unfolded.
Route::middleware('auth.jwt')->get('/matches/{id}/events', [MatchesController::class, 'events']);

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
        // The shell: business, capabilities, the branches this caller may act on,
        // and their altitude. Every client calls this first.
        Route::get('/context', 'context');
        Route::get('/overview', 'overview');
        Route::get('/today', 'today');
        Route::get('/events', 'events');
        Route::get('/events/{id}', 'showEvent')->whereNumber('id');
        Route::get('/events/{id}/analytics', 'eventAnalytics')->whereNumber('id');
        Route::get('/venues', 'venues');
        Route::get('/venues/{id}', 'showVenue')->whereNumber('id');
        Route::get('/venues/{id}/analytics', 'venueAnalytics')->whereNumber('id');
        Route::get('/venues/{id}/day', 'venueDay')->whereNumber('id');
        Route::get('/venues/{id}/slots', 'venueSlots')->whereNumber('id');
        Route::get('/venues/{id}/courts', 'venueCourts')->whereNumber('id');
        Route::get('/bookings', 'bookings');
        // Games on this partner's courts: booking-linked, plus public matches
        // whose GPS lands on the venue. Read-only.
        Route::get('/matches', 'matches');

        // --- Write actions gated by staff capability (owners hold all) ---
        Route::middleware('partner.can:pricing')->group(function (): void {
            Route::post('/venues/{id}/slots', 'saveSlot')->whereNumber('id');
            Route::post('/venues/{id}/slots/{slotId}', 'saveSlot')->whereNumber('id')->whereNumber('slotId');
            Route::delete('/venues/{id}/slots/{slotId}', 'deleteSlot')->whereNumber('id')->whereNumber('slotId');
            Route::post('/venues/{id}/courts/{courtId}', 'saveCourt')->whereNumber('id')->whereNumber('courtId');
            Route::post('/academy', 'saveBatch');
            Route::post('/academy/{id}/enroll', 'enrollStudent')->whereNumber('id');
            Route::post('/academy/attendance', 'markAttendance');
            Route::post('/packages', 'savePackage');
            Route::post('/packages/{id}', 'savePackage')->whereNumber('id');
            Route::post('/packages/{id}/sell', 'sellPackage')->whereNumber('id');
        });
        Route::middleware('partner.can:bookings')->group(function (): void {
            Route::post('/venues/{id}/bookings', 'storeOfflineBooking')->whereNumber('id');
            Route::post('/venues/{id}/block', 'blockDate')->whereNumber('id');
            Route::delete('/venues/{id}/block', 'unblockDate')->whereNumber('id');
            Route::patch('/bookings/{id}/cancel', 'cancelBooking')->whereNumber('id');
            Route::post('/bookings/{id}/cancel', 'cancelBooking')->whereNumber('id'); // app (no PATCH)
            Route::post('/bookings/{id}/payment-status', 'paymentStatus')->whereNumber('id');
        });
        Route::middleware('partner.can:checkin')->post('/check-in', 'checkInByCode');
        Route::middleware('partner.can:reports')->group(function (): void {
            Route::get('/reports/bookings', 'bookingsReport');
            // Settlement is money-sensitive: same 'reports' gate as Earnings, and
            // the destination itself is only ever returned masked.
            Route::get('/customers', 'customers');
            Route::get('/academy', 'academy');
            Route::get('/academy/{id}/roster', 'batchRoster')->whereNumber('id');
            Route::get('/packages', 'packages');
            Route::get('/packages/holder', 'packageHolder');
            Route::get('/payouts', 'payouts');
            Route::post('/payouts/account', 'savePayoutAccount');
        });

        // --- Staff management (owner-only; desk persons never hold 'staff') ---
        Route::middleware('partner.can:staff')->group(function (): void {
            Route::get('/staff', 'staff');
            Route::post('/staff', 'createStaff');
            Route::post('/staff/{id}', 'updateStaff')->whereNumber('id');
            Route::delete('/staff/{id}', 'deleteStaff')->whereNumber('id');
        });
    });

// Razorpay billing webhook — subscription lifecycle and prepaid credit grants.
// Unauthenticated by necessity; the HMAC signature check in the controller is
// the authentication, and it fails closed.
Route::post('/webhooks/razorpay', [\App\Http\Controllers\Api\RazorpayWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.razorpay');

// Meta / Instagram webhook. GET is the one-time subscription handshake; POST
// carries message events, signed with the app secret. Both fail closed.
// One callback URL for the whole Meta app: Instagram DMs arrive as
// entry[].messaging[], WhatsApp Cloud as entry[].changes[] — same signature.
Route::get('/webhooks/meta', [\App\Http\Controllers\Api\MetaWebhookController::class, 'verify'])
    ->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [\App\Http\Controllers\Api\MetaWebhookController::class, 'handle'])
    ->middleware('throttle:240,1')
    ->name('webhooks.meta');

// Alias kept so a callback already pointed here doesn't break.
Route::get('/webhooks/meta/instagram', [\App\Http\Controllers\Api\MetaWebhookController::class, 'verify']);
Route::post('/webhooks/meta/instagram', [\App\Http\Controllers\Api\MetaWebhookController::class, 'handle'])
    ->middleware('throttle:240,1');

// MSG91 inbound WhatsApp, when MSG91 is the BSP instead of Meta. Same destination
// (InboundMessages), different envelope. MSG91 signs nothing, so a shared secret in
// a request header is the authentication — configured in the panel under the
// number's Action menu → Webhook — and it fails closed.
Route::post('/webhooks/msg91/whatsapp', [\App\Http\Controllers\Api\Msg91WebhookController::class, 'handle'])
    ->middleware('throttle:240,1')
    ->name('webhooks.msg91.whatsapp');

// GET is a reachability check only — it carries no data and does nothing. It
// exists because a provider validating the URL (or a human pasting it into a
// browser) otherwise gets a 405, which reads as a broken endpoint.
Route::get('/webhooks/msg91/whatsapp', [\App\Http\Controllers\Api\Msg91WebhookController::class, 'ping'])
    ->middleware('throttle:60,1')
    ->name('webhooks.msg91.whatsapp.ping');
