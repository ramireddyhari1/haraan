<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminCitiesController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\PublicWebController;
use App\Http\Controllers\Web\LiveMatchController;
use App\Http\Controllers\Web\PartnerDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
|
| These are the public-facing routes for the Haraan website.
| All routes return server-rendered Blade views.
|
*/

Route::controller(PublicWebController::class)->group(function (): void {
    Route::get('/', 'events');
    Route::get('/home', 'home');
    Route::get('/events', 'events');
    Route::get('/events/{id}', 'eventDetail');
    Route::get('/gamehub', 'gamehub')->name('site.gamehub');
    Route::get('/gamehub/{id}', 'gamehubDetail')->whereNumber('id');
    Route::get('/gamehub/actionboard', 'actionBoard')->name('site.gamehub.actionboard');
    Route::get('/gamehub/actionboard/match/{id}', 'actionBoardMatchLive')->name('site.gamehub.actionboard.match');
    Route::get('/gamehub/actionboard/match/{id}/info', 'actionBoardMatchInfo')->name('site.gamehub.actionboard.match.info');
    Route::get('/gamehub/actionboard/match/{id}/scorecard', 'actionBoardMatchScorecard')->name('site.gamehub.actionboard.match.scorecard');
    Route::get('/gamehub/actionboard/match/{id}/json', 'actionBoardMatchJson')->name('site.gamehub.actionboard.match.json');
    Route::get('/gamehub/actionboard/matches/json', 'actionBoardMatchesJson')->name('site.gamehub.actionboard.matches.json');
    Route::get('/gamehub/leaderboard', 'leaderboard')->name('site.gamehub.leaderboard');
    Route::get('/search', 'search')->name('site.search');
    Route::get('/api/search/suggest', 'searchSuggest')->name('api.search.suggest');
    Route::get('/login', 'login')->name('site.login');
    Route::get('/register', 'register')->name('site.register');
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
Route::get('/gamehub/actionboard/player/{id}', [\App\Http\Controllers\Web\PublicWebController::class, 'getPlayerDetails'])->name('site.gamehub.actionboard.player');
Route::get('/player/{player_id}', [\App\Http\Controllers\Web\PublicWebController::class, 'showPlayerProfile'])->name('site.player.profile');
Route::get('/api/players/search', [\App\Http\Controllers\Web\PublicWebController::class, 'searchPlayers'])->name('api.players.search');
Route::post('/api/players/guest', [\App\Http\Controllers\Web\PublicWebController::class, 'createGuestPlayer'])->name('api.players.guest');
Route::get('/api/players/claimable', [\App\Http\Controllers\Web\PublicWebController::class, 'getClaimablePlayers'])->name('api.players.claimable');

Route::middleware('auth')->group(function() {
    Route::get('/profile/setup', [\App\Http\Controllers\Web\PublicWebController::class, 'showProfileSetupForm'])->name('site.profile.setup');
    Route::post('/profile/setup', [\App\Http\Controllers\Web\PublicWebController::class, 'saveProfileSetup'])->name('site.profile.setup.save');
});

// Event ticket booking — the web twin of the app's checkout (same BookingService).
Route::middleware('auth')->controller(\App\Http\Controllers\Web\EventBookingController::class)->group(function (): void {
    Route::get('/events/{id}/book', 'checkout')->whereNumber('id')->name('site.booking.checkout');
    Route::post('/events/{id}/book', 'store')->whereNumber('id')->name('site.booking.store');
    Route::get('/bookings/{id}/pass', 'pass')->whereNumber('id')->name('site.booking.pass');
});

// Header inbox lanes — the web twins of the app's chat + bell icons. Both read the
// same tables the JWT API serves the app, so a conversation or a notification looks
// the same wherever the user opens it.
Route::middleware('auth')->group(function (): void {
    Route::get('/support', [\App\Http\Controllers\Web\SupportChatController::class, 'show'])->name('site.support');
    Route::post('/support/messages', [\App\Http\Controllers\Web\SupportChatController::class, 'send'])->name('site.support.send');
    Route::get('/support/poll', [\App\Http\Controllers\Web\SupportChatController::class, 'poll'])->name('site.support.poll');
    Route::get('/notifications', [\App\Http\Controllers\Web\NotificationsController::class, 'index'])->name('site.notifications');
});

// WhatsApp Auth Routes
Route::controller(\App\Http\Controllers\Auth\WhatsAppAuthController::class)->group(function (): void {
    Route::post('/auth/whatsapp/request', 'requestOtp')->name('whatsapp.request');
    Route::get('/auth/whatsapp/verify', 'showVerifyForm')->name('whatsapp.verify.show');
    Route::post('/auth/whatsapp/verify', 'verifyOtp')->name('whatsapp.verify.submit');
    Route::get('/auth/whatsapp/cancel', 'cancel')->name('whatsapp.cancel');
});

// Account — the web twin of the app's AccountProfileScreen (hero, lanes, settings).
// /profile itself is declared with the public site routes above; these are the rows
// and lanes it opens, plus the sign-out it always needed.
Route::middleware('auth')->controller(\App\Http\Controllers\Web\AccountController::class)->group(function (): void {
    Route::get('/profile', 'profile')->name('site.profile');
    Route::get('/bookings', 'bookings')->name('site.bookings');
    Route::get('/account/privacy', 'privacy')->name('site.account.privacy');
    Route::post('/account/privacy', 'updatePrivacy')->name('site.account.privacy.save');
    Route::post('/profile/avatar', 'uploadAvatar')->name('site.profile.avatar');
    Route::post('/logout', 'logout')->name('site.logout');
});

// Terms & Conditions / Privacy Policy — public documents, readable signed out.
Route::get('/legal/{slug}', [\App\Http\Controllers\Web\AccountController::class, 'legal'])
    ->name('site.legal');

// "Continue with Google" on the website — the login modal posts the GIS ID token here.
Route::post('/auth/google', [\App\Http\Controllers\Auth\GoogleWebAuthController::class, 'login'])
    ->name('google.web.login');

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
        Route::get('/erp/setup-admin', [\App\Http\Controllers\Web\AdminAuthController::class, 'setupAdmin'])->name('portal.setup_admin');

    // Admin auth — consolidated onto the single Filament "Control" panel (/control).
    // The legacy Blade login now redirects there so there is ONE admin front door.
    Route::get('admin/login', fn () => redirect('/control'))->name('admin.login');
    Route::post('admin/login', [\App\Http\Controllers\Web\AdminAuthController::class, 'login'])->name('admin.login.submit');
    Route::post('admin/logout', [\App\Http\Controllers\Web\AdminAuthController::class, 'logout'])->name('admin.logout');
    // Password reset for admin
    Route::get('admin/password/reset', [\App\Http\Controllers\Web\PasswordResetController::class, 'showLinkRequestForm'])->name('admin.password.request');
    Route::post('admin/password/email', [\App\Http\Controllers\Web\PasswordResetController::class, 'sendResetLinkEmail'])->name('admin.password.email');
    Route::get('admin/password/reset/{token}', [\App\Http\Controllers\Web\PasswordResetController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('admin/password/reset', [\App\Http\Controllers\Web\PasswordResetController::class, 'reset'])->name('admin.password.update');

    // Protected admin routes
    Route::prefix('admin')->name('admin.')->middleware(['auth', \App\Http\Middleware\EnsureRole::class . ':ADMIN,COADMIN'])->group(function (): void {
        // Admin event JSON + store endpoints
        Route::get('/events/json', [\App\Http\Controllers\Web\AdminEventsController::class, 'indexJson'])->name('events.json');
        Route::post('/events', [\App\Http\Controllers\Web\AdminEventsController::class, 'store'])->name('events.store');
        Route::delete('/events/{id}', [\App\Http\Controllers\Web\AdminEventsController::class, 'destroy'])->name('events.delete');
        Route::put('/events/{id}', [\App\Http\Controllers\Web\AdminEventsController::class, 'update'])->name('events.update');
        Route::get('/events/new', [\App\Http\Controllers\Web\AdminEventsController::class, 'create'])->name('events.create');
        Route::get('/events/{id}/edit', [\App\Http\Controllers\Web\AdminEventsController::class, 'edit'])->name('events.edit');
        
        // Partner management
        Route::get('/partners/json', [\App\Http\Controllers\Web\AdminPartnersController::class, 'indexJson'])->name('partners.json');
        Route::get('/partners/new', [\App\Http\Controllers\Web\AdminPartnersController::class, 'create'])->name('partners.create');
        Route::post('/partners', [\App\Http\Controllers\Web\AdminPartnersController::class, 'store'])->name('partners.store');
        Route::get('/partners/{id}/edit', [\App\Http\Controllers\Web\AdminPartnersController::class, 'edit'])->name('partners.edit');
        Route::put('/partners/{id}', [\App\Http\Controllers\Web\AdminPartnersController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{id}', [\App\Http\Controllers\Web\AdminPartnersController::class, 'destroy'])->name('partners.delete');
        // Team routes: co-admins and workers
        Route::get('/team/{role}/json', [\App\Http\Controllers\Web\AdminTeamController::class, 'indexJson'])->name('team.json');
        Route::get('/team/{role}/new', [\App\Http\Controllers\Web\AdminTeamController::class, 'create'])->name('team.create');
        Route::post('/team/{role}', [\App\Http\Controllers\Web\AdminTeamController::class, 'store'])->name('team.store');
        Route::get('/team/{role}/{id}/edit', [\App\Http\Controllers\Web\AdminTeamController::class, 'edit'])->name('team.edit');
        Route::put('/team/{role}/{id}', [\App\Http\Controllers\Web\AdminTeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{role}/{id}', [\App\Http\Controllers\Web\AdminTeamController::class, 'destroy'])->name('team.delete');
        Route::controller(AdminDashboardController::class)->group(function(): void {
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
        Route::get('/login-posters', [\App\Http\Controllers\Web\AdminLoginPostersController::class, 'index'])->name('login-posters');
        Route::post('/login-posters', [\App\Http\Controllers\Web\AdminLoginPostersController::class, 'store'])->name('login-posters.store');
        Route::post('/login-posters/{id}', [\App\Http\Controllers\Web\AdminLoginPostersController::class, 'update'])->name('login-posters.update');
        Route::delete('/login-posters/{id}', [\App\Http\Controllers\Web\AdminLoginPostersController::class, 'destroy'])->name('login-posters.delete');
        Route::post('/login-posters/{id}/toggle', [\App\Http\Controllers\Web\AdminLoginPostersController::class, 'toggleActive'])->name('login-posters.toggle');
        // Admin JSON endpoints for bookings, payments, coupons, users
        Route::get('/bookings/json', [\App\Http\Controllers\Web\AdminBookingsController::class, 'indexJson'])->name('bookings.json');
        Route::post('/bookings/{id}/status', [\App\Http\Controllers\Web\AdminBookingsController::class, 'updateStatus'])->name('bookings.update_status');

        Route::get('/payments/json', [\App\Http\Controllers\Web\AdminPaymentsController::class, 'indexJson'])->name('payments.json');
        Route::post('/payments/{id}/mark-paid', [\App\Http\Controllers\Web\AdminPaymentsController::class, 'markPaid'])->name('payments.mark_paid');

        Route::get('/coupons/json', [\App\Http\Controllers\Web\AdminCouponsController::class, 'indexJson'])->name('coupons.json');
        Route::post('/coupons', [\App\Http\Controllers\Web\AdminCouponsController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{id}', [\App\Http\Controllers\Web\AdminCouponsController::class, 'update'])->name('coupons.update');
        Route::delete('/coupons/{id}', [\App\Http\Controllers\Web\AdminCouponsController::class, 'destroy'])->name('coupons.delete');

        // Payouts JSON endpoints
        Route::get('/payouts/json', [\App\Http\Controllers\Web\AdminPayoutsController::class, 'indexJson'])->name('payouts.json');
        Route::post('/payouts/{id}/process', [\App\Http\Controllers\Web\AdminPayoutsController::class, 'process'])->name('payouts.process');
        Route::post('/payouts', [\App\Http\Controllers\Web\AdminPayoutsController::class, 'create'])->name('payouts.create');

        // Export endpoints
        Route::get('/export/bookings', [\App\Http\Controllers\Web\AdminExportsController::class, 'bookingsCsv'])->name('export.bookings');
        Route::get('/export/payments', [\App\Http\Controllers\Web\AdminExportsController::class, 'paymentsCsv'])->name('export.payments');
        Route::get('/export/users', [\App\Http\Controllers\Web\AdminExportsController::class, 'usersCsv'])->name('export.users');

        Route::get('/users/json', [\App\Http\Controllers\Web\AdminUsersController::class, 'indexJson'])->name('users.json');
        // Organization units
        Route::get('/organizations', [\App\Http\Controllers\Web\AdminOrgsController::class, 'index'])->name('organizations');
        Route::get('/organizations/json', [\App\Http\Controllers\Web\AdminOrgsController::class, 'indexJson'])->name('organizations.json');
        Route::post('/organizations', [\App\Http\Controllers\Web\AdminOrgsController::class, 'store'])->name('organizations.store');
        Route::post('/organizations/{id}/assign', [\App\Http\Controllers\Web\AdminOrgsController::class, 'assignUser'])->name('organizations.assign');
        Route::post('/users/{id}/suspend', [\App\Http\Controllers\Web\AdminUsersController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{id}/reactivate', [\App\Http\Controllers\Web\AdminUsersController::class, 'reactivate'])->name('users.reactivate');
        Route::post('/users/{id}/role', [\App\Http\Controllers\Web\AdminUsersController::class, 'assignRole'])->name('users.assign_role');
        // Roles & permissions
        Route::get('/roles', [\App\Http\Controllers\Web\AdminRolesController::class, 'index'])->name('roles');
        Route::get('/roles/json', [\App\Http\Controllers\Web\AdminRolesController::class, 'indexJson'])->name('roles.json');
        Route::post('/roles', [\App\Http\Controllers\Web\AdminRolesController::class, 'store'])->name('roles.store');
        Route::get('/roles/permissions/json', [\App\Http\Controllers\Web\AdminRolesController::class, 'permissionsJson'])->name('roles.permissions.json');
        Route::post('/permissions', [\App\Http\Controllers\Web\AdminRolesController::class, 'storePermission'])->name('permissions.store');
        Route::put('/roles/{id}', [\App\Http\Controllers\Web\AdminRolesController::class, 'update'])->name('roles.update');

        // Audit logs
        Route::get('/audit', [\App\Http\Controllers\Web\AdminAuditController::class, 'index'])->name('audit');
        Route::get('/audit/json', [\App\Http\Controllers\Web\AdminAuditController::class, 'indexJson'])->name('audit.json');
        });
    });

    // NOTE: the legacy Blade partner stub (partner/login, partner dashboard,
    // password reset) has been retired — the /partner console is now the
    // Filament PartnerPanelProvider, which owns /partner/login, password-reset
    // and profile. Keeping the old Blade routes here shadowed the Filament
    // panel's own login and 404'd it, so they are intentionally removed.
});
