<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\FirebasePhoneAuthController;
use App\Http\Controllers\Auth\GoogleWebAuthController;
use App\Http\Controllers\Auth\PartnerAuthController;
use App\Http\Controllers\Auth\WebPasswordAuthController;
use App\Http\Controllers\Auth\WebPasswordResetController;
use App\Http\Controllers\Auth\WhatsAppAuthController;
use App\Http\Controllers\Auth\WhatsAppOtpController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountDeletionController;
use App\Http\Controllers\Web\AdminAuditController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminBookingsController;
use App\Http\Controllers\Web\AdminCitiesController;
use App\Http\Controllers\Web\AdminCouponsController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminEventsController;
use App\Http\Controllers\Web\AdminExportsController;
use App\Http\Controllers\Web\AdminLoginPostersController;
use App\Http\Controllers\Web\AdminOrgsController;
use App\Http\Controllers\Web\AdminPartnersController;
use App\Http\Controllers\Web\AdminPaymentsController;
use App\Http\Controllers\Web\AdminPayoutsController;
use App\Http\Controllers\Web\AdminRolesController;
use App\Http\Controllers\Web\AdminTeamController;
use App\Http\Controllers\Web\AdminUsersController;
use App\Http\Controllers\Web\EventBookingController;
use App\Http\Controllers\Web\LiveMatchController;
use App\Http\Controllers\Web\MembershipController;
use App\Http\Controllers\Web\NotificationsController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PlayerChatController;
use App\Http\Controllers\Web\PublicWebController;
use App\Http\Controllers\Web\ReviewController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SocialFeedController;
use App\Http\Controllers\Web\SupportChatController;
use App\Http\Controllers\Web\VenueBookingController;
use App\Http\Controllers\Web\VenuePhotoController;
use App\Http\Middleware\EnsureRole;
use App\Models\Hrms\EmployeePayroll;
use App\Services\Hrms\PayrollCalculationService;
use App\Support\PartnerBranchContext;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
|
| These are the public-facing routes for the Haraan website.
| All routes return server-rendered Blade views.
|
*/

// Search-engine discovery. robots.txt is the static file in public/ (nginx serves
// it before PHP); the sitemap is generated + cached hourly so new events show up.
// Sessions/CSRF are stripped so the response carries no Set-Cookie and can be
// cached — a sitemap is anonymous, identical for every caller.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])
    ->name('sitemap')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
        PreventRequestForgery::class,
    ]);

Route::controller(PublicWebController::class)->group(function (): void {
    Route::get('/', 'events');
    Route::get('/home', 'home');
    Route::get('/events', 'events');
    Route::get('/events/{id}', 'eventDetail');
    // Sponsored-slot click-through: counted, then redirected to the advertiser.
    Route::get('/go/ad/{id}', 'adClick')->whereNumber('id')->middleware('throttle:120,1')->name('site.ad.click');
    Route::get('/host/{slug}', 'hostProfile')->name('site.host');
    Route::middleware('auth')->post('/host/{slug}/follow', 'followHost')->name('site.host.follow');
    Route::get('/gamehub', 'gamehub')->name('site.gamehub');
    Route::get('/gamehub/{id}', 'gamehubDetail')->whereNumber('id');
    Route::get('/gamehub/actionboard', 'actionBoard')->name('site.gamehub.actionboard');
    Route::get('/gamehub/actionboard/match/{id}', 'actionBoardMatchLive')->name('site.gamehub.actionboard.match');
    Route::get('/gamehub/actionboard/match/{id}/info', 'actionBoardMatchInfo')->name('site.gamehub.actionboard.match.info');
    Route::get('/gamehub/actionboard/match/{id}/commentary', 'actionBoardMatchCommentary')->name('site.gamehub.actionboard.match.commentary');
    Route::get('/gamehub/actionboard/match/{id}/scorecard', 'actionBoardMatchScorecard')->name('site.gamehub.actionboard.match.scorecard');
    Route::get('/gamehub/actionboard/match/{id}/json', 'actionBoardMatchJson')->name('site.gamehub.actionboard.match.json');
    Route::get('/gamehub/actionboard/matches/json', 'actionBoardMatchesJson')->name('site.gamehub.actionboard.matches.json');
    Route::get('/gamehub/leaderboard', 'leaderboard')->name('site.gamehub.leaderboard');
    Route::get('/search', 'search')->name('site.search');
    Route::get('/api/search/suggest', 'searchSuggest')->name('api.search.suggest');
    Route::get('/login', 'login')->name('site.login');
    Route::get('/register', 'register')->name('site.register');
    // Razorpay Standard Checkout demo page — LOCAL ONLY. It's an unauthenticated page
    // that opens a real charge, so it must never be reachable on the live server. The
    // public key is delivered by the /api/create-order response (never templated in).
    if (app()->environment('local')) {
        Route::view('/pay', 'site.pay')->name('site.pay');
    }
    // NB: /profile lives in Web\AccountController (auth-gated) — the account screen is
    // the app's twin now. The old PUT /profile name/email/phone editor went with the
    // page that posted to it; nothing referenced it afterwards.
});

// New Live Match Routes
Route::middleware('auth')->controller(LiveMatchController::class)->group(function (): void {
    Route::get('/gamehub/actionboard/create', 'create')->name('site.gamehub.actionboard.create');
    Route::post('/gamehub/actionboard/create', 'store')->name('site.gamehub.actionboard.store');
    Route::get('/gamehub/actionboard/match/{id}/control', 'edit')->name('site.gamehub.actionboard.control');
    Route::put('/gamehub/actionboard/match/{id}/control', 'update')->name('site.gamehub.actionboard.update');
});
Route::get('/gamehub/actionboard/player/{id}', [PublicWebController::class, 'getPlayerDetails'])->name('site.gamehub.actionboard.player');
Route::get('/player/{player_id}', [PublicWebController::class, 'showPlayerProfile'])->name('site.player.profile');
Route::get('/api/players/search', [PublicWebController::class, 'searchPlayers'])->name('api.players.search');
Route::post('/api/players/guest', [PublicWebController::class, 'createGuestPlayer'])->name('api.players.guest');
Route::get('/api/players/claimable', [PublicWebController::class, 'getClaimablePlayers'])->name('api.players.claimable');

Route::middleware('auth')->group(function () {
    Route::get('/profile/setup', [PublicWebController::class, 'showProfileSetupForm'])->name('site.profile.setup');
    Route::post('/profile/setup', [PublicWebController::class, 'saveProfileSetup'])->name('site.profile.setup.save');
});

// Public ticket-QR image (used by the confirmation email's <img>, the WhatsApp media message,
// and anywhere a hosted QR is needed). The QR encodes the scanner contract `haraan:ticket:<code>`
// — the code itself is the only secret. Generated server-side via a public QR image service
// (no self-hosted bridge, no QR PHP extension needed); the response is cached hard so it's
// fetched once. Configurable via services.qr.endpoint.
Route::get('/t/{code}/qr.png', function (string $code) {
    $payload = 'haraan:ticket:'.$code;
    $endpoint = (string) config('services.qr.endpoint', 'https://api.qrserver.com/v1/create-qr-code/');
    try {
        $res = Http::connectTimeout(4)->timeout(15)->get($endpoint, [
            'size' => '360x360',
            'margin' => '1',
            'data' => $payload,
        ]);
        if ($res->successful()) {
            return response($res->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=31536000, immutable');
        }
    } catch (Throwable $e) {
        // fall through to 404
    }
    abort(404);
})->where('code', '[A-Za-z0-9]+')->name('ticket.qr');

// Public entry pass, addressed by ticket code — the link the confirmation SMS and
// email carry. Sessionless on purpose (tickets get bought for other people, and a
// desk walk-in's row belongs to the partner), with the code as the bearer secret
// on the same reasoning as the QR route above. Throttled so the code space can't
// be swept.
Route::get('/t/{code}', [EventBookingController::class, 'passByCode'])
    ->where('code', '[A-Za-z0-9]+')
    ->middleware('throttle:30,1')
    ->name('ticket.pass');

// "How was it?" — where the post-event WhatsApp message lands. Sessionless and
// code-addressed on the same reasoning as the pass above: the person who attended
// often isn't the person who paid, and a login wall is how you get no ratings. The
// code is the whole authorisation, so it's throttled like the pass, and one booking
// can only ever leave one review.
Route::get('/r/{code}', [ReviewController::class, 'show'])
    ->where('code', '[A-Za-z0-9]+')
    ->middleware('throttle:30,1')
    ->name('review.show');
Route::post('/r/{code}', [ReviewController::class, 'store'])
    ->where('code', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('review.store');

// One photo from the venue's Google listing, proxied so the Maps key never reaches
// the browser and only venues we already list can be billed. Disk-cached, so the
// throttle only ever bites a scraper walking event ids.
Route::get('/events/{id}/venue-photo/{index}.jpg', [VenuePhotoController::class, 'show'])
    ->whereNumber('id')
    ->whereNumber('index')
    ->middleware('throttle:120,1')
    ->name('site.event.venuephoto');

// Event ticket booking — the web twin of the app's checkout (same BookingService).
Route::middleware('auth')->controller(EventBookingController::class)->group(function (): void {
    Route::get('/events/{id}/book', 'checkout')->whereNumber('id')->name('site.booking.checkout');
    Route::post('/events/{id}/book', 'store')->whereNumber('id')->name('site.booking.store');
    // Live coupon quote for the review page. Throttled: it answers "is this a real code?"
    // one guess at a time, which is exactly what a code-harvesting script wants.
    Route::post('/events/{id}/book/coupon', 'previewCoupon')
        ->whereNumber('id')
        ->middleware('throttle:20,1')
        ->name('site.booking.coupon');
    // Razorpay payment on the reserved order (AJAX from the payment page).
    Route::post('/events/{id}/book/confirm', 'confirmWeb')->whereNumber('id')->name('site.booking.confirm');
    Route::post('/events/{id}/book/release', 'releaseWeb')->whereNumber('id')->name('site.booking.release');
    Route::get('/bookings/{id}/pass', 'pass')->whereNumber('id')->name('site.booking.pass');
});

// Sports venue court booking — web checkout with Razorpay standard integration.
Route::middleware('auth')->controller(VenueBookingController::class)->group(function (): void {
    Route::post('/gamehub/{id}/book', 'reserve')->whereNumber('id')->name('site.gamehub.book');
    Route::post('/gamehub/{id}/confirm', 'confirm')->whereNumber('id')->name('site.gamehub.confirm');
    Route::post('/gamehub/{id}/release', 'release')->whereNumber('id')->name('site.gamehub.release');
});

// Membership — Pro and Hero are bought here (the app shows plans but doesn't sell them).
// The page is public; checkout needs the session. Checkout creation is throttled: each call
// creates a Razorpay subscription.
Route::get('/membership', [MembershipController::class, 'show'])->name('site.membership');
Route::middleware('auth')->prefix('membership')->controller(MembershipController::class)->group(function (): void {
    Route::post('/checkout', 'checkout')->middleware('throttle:10,1')->name('site.membership.checkout');
    Route::post('/verify', 'verify')->name('site.membership.verify');
    Route::post('/abandon', 'abandon')->name('site.membership.abandon');
    Route::post('/cancel', 'cancel')->name('site.membership.cancel');
});

// The app's two social destinations, now that the ActionBoard's bottom bar points at
// them: Home is the photo feed, Chat is player-to-player DMs. Both read the same tables
// the JWT API serves the app — a session instead of a token is the only difference.
Route::controller(SocialFeedController::class)->group(function (): void {
    // Reading the feed is public (the app's is too — the posts on it are public accounts'
    // by definition); acting on a post is not.
    Route::get('/feed', 'index')->name('site.feed');
    Route::get('/feed/posts/{id}/comments', 'comments')->whereNumber('id')->name('site.feed.comments');
    Route::middleware('auth')->group(function (): void {
        Route::post('/feed/posts/{id}/like', 'toggleLike')->whereNumber('id')->name('site.feed.like');
        Route::post('/feed/posts/{id}/comments', 'addComment')->whereNumber('id')->name('site.feed.comment');
    });
});

Route::middleware('auth')->controller(PlayerChatController::class)->group(function (): void {
    Route::get('/chat', 'index')->name('site.chat');
    Route::get('/chat/{id}', 'show')->whereNumber('id')->name('site.chat.thread');
    Route::post('/chat/{id}/messages', 'send')->whereNumber('id')->name('site.chat.send');
    Route::get('/chat/{id}/poll', 'poll')->whereNumber('id')->name('site.chat.poll');
});

// Header inbox lanes — the web twins of the app's chat + bell icons. Both read the
// same tables the JWT API serves the app, so a conversation or a notification looks
// the same wherever the user opens it.
Route::middleware('auth')->group(function (): void {
    Route::get('/support', [SupportChatController::class, 'show'])->name('site.support');
    Route::post('/support/messages', [SupportChatController::class, 'send'])->name('site.support.send');
    Route::get('/support/poll', [SupportChatController::class, 'poll'])->name('site.support.poll');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('site.notifications');
});

// Email + password sign-in for the public website login modal (see WebPasswordAuthController).
Route::post('/auth/password', [WebPasswordAuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('site.password.login');

// Forgot / set password for site users (companion to the email+password login).
Route::controller(WebPasswordResetController::class)->group(function (): void {
    Route::get('/forgot-password', 'showRequestForm')->name('site.password.request');
    Route::post('/forgot-password', 'sendResetLink')->middleware('throttle:auth')->name('site.password.email');
    Route::get('/reset-password/{token}', 'showResetForm')->name('site.password.reset');
    Route::post('/reset-password', 'reset')->middleware('throttle:auth')->name('site.password.update');
});

// WhatsApp Auth Routes (legacy phone-OTP; the login form no longer surfaces this,
// but the routes stay so any in-flight/bookmarked flow doesn't 404).
Route::controller(WhatsAppAuthController::class)->group(function (): void {
    Route::post('/auth/whatsapp/request', 'requestOtp')->name('whatsapp.request');
    Route::get('/auth/whatsapp/verify', 'showVerifyForm')->name('whatsapp.verify.show');
    Route::post('/auth/whatsapp/verify', 'verifyOtp')->name('whatsapp.verify.submit');
    Route::get('/auth/whatsapp/cancel', 'cancel')->name('whatsapp.cancel');
});

// Account — the web twin of the app's AccountProfileScreen (hero, lanes, settings).
// /profile itself is declared with the public site routes above; these are the rows
// and lanes it opens, plus the sign-out it always needed.
Route::middleware('auth')->controller(AccountController::class)->group(function (): void {
    Route::get('/profile', 'profile')->name('site.profile');
    Route::get('/bookings', 'bookings')->name('site.bookings');
    Route::get('/account/privacy', 'privacy')->name('site.account.privacy');
    Route::post('/account/privacy', 'updatePrivacy')->name('site.account.privacy.save');
    Route::post('/account/demographics', 'saveDemographics')->name('site.account.demographics');
    Route::post('/profile/avatar', 'uploadAvatar')->name('site.profile.avatar');
    Route::post('/logout', 'logout')->name('site.logout');
});

// Terms & Conditions / Privacy Policy — public documents, readable signed out.
Route::get('/legal/{slug}', [AccountController::class, 'legal'])
    ->name('site.legal');

// Account deletion — PUBLIC on purpose. Google Play requires this URL to work for
// someone who has already uninstalled the app, so it must not sit behind 'auth'.
// This exact URL is filed in the Play Console Data safety form; renaming it there
// and not here (or the reverse) fails review.
Route::controller(AccountDeletionController::class)->group(function (): void {
    Route::get('/account/delete', 'show')->name('site.account.delete');
    Route::post('/account/delete', 'submit')->middleware('throttle:6,60')->name('site.account.delete.submit');
    Route::get('/account/delete/confirm/{token}', 'confirm')->name('site.account.delete.confirm');
});

// "Continue with Google" on the website — the login modal posts the GIS ID token here.
Route::post('/auth/google', [GoogleWebAuthController::class, 'login'])
    ->name('google.web.login');

// Phone-number sign-in (Firebase SMS OTP) — the browser completes the OTP and posts
// the resulting Firebase ID token here for verification + session login.
// Phone sign-in over WhatsApp, with the Firebase SMS flow below as the fallback.
// `start` answers {channel} — "whatsapp" when the code went out, "sms" for every
// reason it couldn't — and the browser drives whichever it names. Deployable before
// WhatsApp works: until login_otp is approved this always answers "sms" and the
// login behaves exactly as it does today.
Route::post('/auth/whatsapp-otp/start', [WhatsAppOtpController::class, 'start'])
    ->middleware('throttle:10,1')
    ->name('whatsapp.otp.start');
Route::post('/auth/whatsapp-otp/verify', [WhatsAppOtpController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('whatsapp.otp.verify');

Route::post('/auth/firebase-phone', [FirebasePhoneAuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('firebase.phone.login');

// Partner console sign-in (phone OTP / Google / email) — the console's own login
// page (/partner/login) posts here. Unlike the member flows above, these authenticate
// ONLY existing PARTNER accounts and land on the partner dashboard (see PartnerAuthController).
// The topbar branch switcher. A POST because it mutates session state, and it
// returns to wherever the partner was rather than to a landing page — switching
// branch should feel like changing a filter, not like navigating away.
Route::post('/partner/branch', function (Request $request) {
    $raw = $request->input('venue_id');

    PartnerBranchContext::select(
        (is_string($raw) || is_int($raw)) && ctype_digit((string) $raw) ? (int) $raw : null,
    );

    return back();
})->middleware(['web', 'auth'])->name('partner.branch.switch');

Route::controller(PartnerAuthController::class)
    ->middleware('throttle:auth')
    ->group(function (): void {
        Route::post('/partner/auth/check-phone', 'checkPhone')->name('partner.auth.check-phone');
        Route::post('/partner/auth/phone', 'phone')->name('partner.auth.phone');
        Route::post('/partner/auth/google', 'google')->name('partner.auth.google');
        Route::post('/partner/auth/email', 'email')->name('partner.auth.email');
    });

/*
|--------------------------------------------------------------------------
| ERP Portal Routes (Admin & Partner)
|--------------------------------------------------------------------------
|
| Protected by the erp.key middleware — requires ?key=<ERP_PORTAL_KEY>
| in the query string. This keeps these routes hidden from public users.
|
*/

Route::middleware('erp.key')->group(function (): void {
    Route::get('/erp', fn () => view('portal.index'))->name('portal.index');
    Route::get('/erp/setup-admin', [AdminAuthController::class, 'setupAdmin'])->name('portal.setup_admin');

    // Admin auth — consolidated onto the single Filament "Control" panel (/control).
    // The legacy Blade login now redirects there so there is ONE admin front door.
    Route::get('admin/login', fn () => redirect('/control'))->name('admin.login');
    Route::post('admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
    Route::post('admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    // Password reset for admin
    Route::get('admin/password/reset', [PasswordResetController::class, 'showLinkRequestForm'])->name('admin.password.request');
    Route::post('admin/password/email', [PasswordResetController::class, 'sendResetLinkEmail'])->name('admin.password.email');
    Route::get('admin/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('admin/password/reset', [PasswordResetController::class, 'reset'])->name('admin.password.update');

    // Protected admin routes
    Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureRole::class.':ADMIN,COADMIN'])->group(function (): void {
        // Admin event JSON + store endpoints
        Route::get('/events/json', [AdminEventsController::class, 'indexJson'])->name('events.json');
        Route::post('/events', [AdminEventsController::class, 'store'])->name('events.store');
        Route::delete('/events/{id}', [AdminEventsController::class, 'destroy'])->name('events.delete');
        Route::put('/events/{id}', [AdminEventsController::class, 'update'])->name('events.update');
        Route::get('/events/new', [AdminEventsController::class, 'create'])->name('events.create');
        Route::get('/events/{id}/edit', [AdminEventsController::class, 'edit'])->name('events.edit');

        // Partner management
        Route::get('/partners/json', [AdminPartnersController::class, 'indexJson'])->name('partners.json');
        Route::get('/partners/new', [AdminPartnersController::class, 'create'])->name('partners.create');
        Route::post('/partners', [AdminPartnersController::class, 'store'])->name('partners.store');
        Route::get('/partners/{id}/edit', [AdminPartnersController::class, 'edit'])->name('partners.edit');
        Route::put('/partners/{id}', [AdminPartnersController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{id}', [AdminPartnersController::class, 'destroy'])->name('partners.delete');
        // Team routes: co-admins and workers
        Route::get('/team/{role}/json', [AdminTeamController::class, 'indexJson'])->name('team.json');
        Route::get('/team/{role}/new', [AdminTeamController::class, 'create'])->name('team.create');
        Route::post('/team/{role}', [AdminTeamController::class, 'store'])->name('team.store');
        Route::get('/team/{role}/{id}/edit', [AdminTeamController::class, 'edit'])->name('team.edit');
        Route::put('/team/{role}/{id}', [AdminTeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{role}/{id}', [AdminTeamController::class, 'destroy'])->name('team.delete');
        Route::controller(AdminDashboardController::class)->group(function (): void {
            Route::get('/', 'home')->name('dashboard');
            Route::get('/events', 'events')->name('events');
            Route::get('/gamehub', 'gamehub')->name('gamehub');
            Route::get('/partners', 'partners')->name('partners');
            Route::get('/co-admins', 'coAdmins')->name('co-admins');
            Route::get('/workers', 'workers')->name('workers');
            Route::get('/bookings', 'bookings')->name('bookings');
            Route::get('/coupons', 'coupons')->name('coupons');
            Route::get('/payments', 'payments')->name('payments');
            Route::get('/payouts', 'payouts')->name('payouts');
            Route::get('/scan', 'scan')->name('scan');
            Route::get('/settings', 'settings')->name('settings');
            Route::get('/users', 'users')->name('users');
            Route::get('/withdraw', 'withdraw')->name('withdraw');
            Route::get('/cities', [AdminCitiesController::class, 'edit'])->name('cities.edit');
            Route::post('/cities', [AdminCitiesController::class, 'update'])->name('cities.update');

            // Login Posters
            Route::get('/login-posters', [AdminLoginPostersController::class, 'index'])->name('login-posters');
            Route::post('/login-posters', [AdminLoginPostersController::class, 'store'])->name('login-posters.store');
            Route::post('/login-posters/{id}', [AdminLoginPostersController::class, 'update'])->name('login-posters.update');
            Route::delete('/login-posters/{id}', [AdminLoginPostersController::class, 'destroy'])->name('login-posters.delete');
            Route::post('/login-posters/{id}/toggle', [AdminLoginPostersController::class, 'toggleActive'])->name('login-posters.toggle');
            // Admin JSON endpoints for bookings, payments, coupons, users
            Route::get('/bookings/json', [AdminBookingsController::class, 'indexJson'])->name('bookings.json');
            Route::post('/bookings/{id}/status', [AdminBookingsController::class, 'updateStatus'])->name('bookings.update_status');

            Route::get('/payments/json', [AdminPaymentsController::class, 'indexJson'])->name('payments.json');
            Route::post('/payments/{id}/mark-paid', [AdminPaymentsController::class, 'markPaid'])->name('payments.mark_paid');

            Route::get('/coupons/json', [AdminCouponsController::class, 'indexJson'])->name('coupons.json');
            Route::post('/coupons', [AdminCouponsController::class, 'store'])->name('coupons.store');
            Route::put('/coupons/{id}', [AdminCouponsController::class, 'update'])->name('coupons.update');
            Route::delete('/coupons/{id}', [AdminCouponsController::class, 'destroy'])->name('coupons.delete');

            // Payouts JSON endpoints
            Route::get('/payouts/json', [AdminPayoutsController::class, 'indexJson'])->name('payouts.json');
            Route::post('/payouts/{id}/process', [AdminPayoutsController::class, 'process'])->name('payouts.process');
            Route::post('/payouts', [AdminPayoutsController::class, 'create'])->name('payouts.create');

            // Export endpoints
            Route::get('/export/bookings', [AdminExportsController::class, 'bookingsCsv'])->name('export.bookings');
            Route::get('/export/payments', [AdminExportsController::class, 'paymentsCsv'])->name('export.payments');
            Route::get('/export/users', [AdminExportsController::class, 'usersCsv'])->name('export.users');

            Route::get('/users/json', [AdminUsersController::class, 'indexJson'])->name('users.json');
            // Organization units
            Route::get('/organizations', [AdminOrgsController::class, 'index'])->name('organizations');
            Route::get('/organizations/json', [AdminOrgsController::class, 'indexJson'])->name('organizations.json');
            Route::post('/organizations', [AdminOrgsController::class, 'store'])->name('organizations.store');
            Route::post('/organizations/{id}/assign', [AdminOrgsController::class, 'assignUser'])->name('organizations.assign');
            Route::post('/users/{id}/suspend', [AdminUsersController::class, 'suspend'])->name('users.suspend');
            Route::post('/users/{id}/reactivate', [AdminUsersController::class, 'reactivate'])->name('users.reactivate');
            Route::post('/users/{id}/role', [AdminUsersController::class, 'assignRole'])->name('users.assign_role');
            // Roles & permissions
            Route::get('/roles', [AdminRolesController::class, 'index'])->name('roles');
            Route::get('/roles/json', [AdminRolesController::class, 'indexJson'])->name('roles.json');
            Route::post('/roles', [AdminRolesController::class, 'store'])->name('roles.store');
            Route::get('/roles/permissions/json', [AdminRolesController::class, 'permissionsJson'])->name('roles.permissions.json');
            Route::post('/permissions', [AdminRolesController::class, 'storePermission'])->name('permissions.store');
            Route::put('/roles/{id}', [AdminRolesController::class, 'update'])->name('roles.update');

            // Audit logs
            Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit');
            Route::get('/audit/json', [AdminAuditController::class, 'indexJson'])->name('audit.json');
        });
    });

    // HRMS Payslip printable view
    Route::get('/payslips/{id}/print', function (int $id) {
        $payroll = EmployeePayroll::findOrFail($id);
        $user = auth()->user();

        if ($user->role === 'EMPLOYEE') {
            abort_unless($user->employeeProfile && $payroll->employee_profile_id === $user->employeeProfile->id, 403, 'Unauthorized access to payslip.');
        } elseif ($user->role === 'PARTNER') {
            $effectivePartnerId = method_exists($user, 'effectivePartnerId') ? $user->effectivePartnerId() : $user->id;
            abort_unless($payroll->employee?->partner_id === $effectivePartnerId, 403, 'Unauthorized access to venue payslip.');
        }

        $service = app(PayrollCalculationService::class);

        return response($service->generatePayslipHtml($payroll), 200, ['Content-Type' => 'text/html']);
    })->middleware(['web', 'auth'])->name('payslip.print');
});
