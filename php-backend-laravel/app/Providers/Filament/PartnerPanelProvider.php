<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Responses\PartnerLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use App\Filament\Auth\PartnerLogin;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The partner-facing console for event hosts and venue owners.
 *
 * It reuses the very same resources, clusters and pages as the admin "control"
 * panel — no duplicate CRUD. Two mechanisms keep partners in their lane:
 *
 *   1. Every resource self-gates via canAccess()/canManage(), so partners only
 *      ever see the Events and GameHub (venue) clusters; People, Finance,
 *      Marketing and System resources return false and stay hidden.
 *   2. ScopesToOrganization restricts each query to the partner's own records
 *      (partner_id = auth id) whenever the current panel is "partner".
 *
 * FilamentShield is intentionally omitted here — access is driven purely by the
 * PARTNER role rather than fine-grained admin permissions.
 */
class PartnerPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        // Send partners back to the website login area on logout, instead of the
        // bare Filament panel "Sign in" page (the response self-scopes to /partner).
        $this->app->bind(LogoutResponse::class, PartnerLogoutResponse::class);

        // BookMyShow-style split brand panel on the left of the partner sign-in
        // screen. Scoped to PartnerLogin so /control — which shares the same
        // compiled theme — and every other simple page stay untouched.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_LAYOUT_START,
            fn (): string => Blade::render('@include(\'filament.partner.auth-brand\')'),
            scopes: PartnerLogin::class,
        );

        // Make the partner LOGIN page discoverable in Google (only this page — the
        // console behind it stays out of the index via robots.txt + auth). The
        // panel emits no description/canonical of its own, so add search metadata
        // here; scoped to PartnerLogin so no other panel page is affected.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<meta name="description" content="Log in to the Haraan Partner dashboard — for event hosts and venue owners to manage events, bookings, earnings and payouts.">'
                . '<meta name="robots" content="index,follow,max-image-preview:large">'
                . '<link rel="canonical" href="' . e(url('/partner/login')) . '">',
            scopes: PartnerLogin::class,
        );

        // Premium visual theme for the Event create/edit wizard — same overrides the
        // control panel uses, scoped to those two pages so the partner's event-creation
        // experience is just as polished without restyling the rest of the console.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.forms.event-form-theme')->render(),
            scopes: [
                \App\Filament\Resources\Events\Pages\CreateEvent::class,
                \App\Filament\Resources\Events\Pages\EditEvent::class,
            ],
        );

        // The shared mobile brand lives in a real view, with its layout defined by
        // the compiled panel system rather than a render-hook style fragment.
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner' ? '' : view('filament.partner.topbar-brand')->render(),
        );

        // Branch switcher — the one control that turns a single-venue console into
        // a chain's. Pinned in the topbar on every page, because switching branch
        // is a filter, not a destination: nobody should have to walk
        // Settings → Branches → Koramangala → Dashboard to change what they're
        // looking at.
        //
        // Renders only for partners with more than one branch. A dropdown with a
        // single option is chrome that does nothing, so today's partners see no
        // change at all and the control appears on its own the day someone opens
        // a second outlet.
        //
        // Native <details> rather than Alpine: the topbar hook is a string of
        // Blade, this needs no state beyond open/closed, and a POST form means the
        // selection survives without any JS at all.
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner'
                || ! \App\Support\PartnerBranchContext::isMultiBranch()
                    ? ''
                    : Blade::render(<<<'BLADE'
                @php
                    $branches = \App\Support\PartnerBranchContext::branches();
                    $currentId = \App\Support\PartnerBranchContext::currentId();
                    $label = \App\Support\PartnerBranchContext::label();
                @endphp
                <details class="hrn-branch" name="hrn-branch">
                    <summary class="hrn-branch-btn" title="Switch branch">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="hrn-branch-pin">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span class="hrn-branch-label">{{ $label }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="hrn-branch-chev">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </summary>
                    <form method="POST" action="{{ route('partner.branch.switch') }}" class="hrn-branch-menu">
                        @csrf
                        <button type="submit" name="venue_id" value=""
                                class="hrn-branch-item @if ($currentId === null) is-on @endif">
                            <span class="hrn-branch-item-name">All branches</span>
                            <span class="hrn-branch-item-sub">{{ $branches->count() }} outlets</span>
                        </button>
                        <div class="hrn-branch-rule"></div>
                        @foreach ($branches as $b)
                            <button type="submit" name="venue_id" value="{{ $b->id }}"
                                    class="hrn-branch-item @if ($currentId === $b->id) is-on @endif">
                                <span class="hrn-branch-item-name">{{ $b->branchName() }}</span>
                                <span class="hrn-branch-item-sub">
                                    {{ $b->branch_code ?: $b->city ?: $b->location }}
                                    @unless ($b->is_active) · inactive @endunless
                                </span>
                            </button>
                        @endforeach
                    </form>
                </details>
            BLADE),
        );

        // Premium sidebar pass: an identity card pinned to the footer (who + which
        // workspace + quiet sign-out) plus a nav polish sheet — accent-rail active
        // state, more breathing room, and clearer section labels.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner' ? '' : Blade::render(<<<'BLADE'
                @php
                    $u = auth()->user();
                    $name = $u?->name ?: 'Partner';
                    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
                    $init = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
                    $init = $init !== '' ? $init : 'P';
                    $hue = crc32($name) % 360;
                    $lane = ($u?->partner_type === 'event') ? 'Event organiser' : 'Venue owner';
                    $photo = \App\Support\MediaUrl::resolve($u?->avatar);
                    $profileUrl = \Filament\Facades\Filament::getProfileUrl();
                    $tag = $profileUrl ? 'a' : 'span';
                @endphp
                <div class="hrn-acct">
                    <{{ $tag }} @if ($profileUrl) href="{{ $profileUrl }}" @endif class="hrn-acct-link" title="View profile">
                        @if ($photo)
                            <img src="{{ $photo }}" alt="{{ $name }}" class="hrn-acct-av hrn-acct-av-img">
                        @else
                            <span class="hrn-acct-av" style="background:hsl({{ $hue }} 52% 46%)">{{ $init }}</span>
                        @endif
                        <span class="hrn-acct-meta">
                            <span class="hrn-acct-name">{{ $name }}</span>
                            <span class="hrn-acct-lane">{{ $lane }}</span>
                        </span>
                    </{{ $tag }}>
                    <form method="POST" action="{{ route('filament.partner.auth.logout') }}" class="hrn-acct-form">
                        @csrf
                        <button type="submit" class="hrn-acct-out" title="Sign out" aria-label="Sign out">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 17l5-5-5-5M20 12H9M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/>
                            </svg>
                        </button>
                    </form>
                </div>
            BLADE),
        );

        // A prominent, lane-aware "+ Create" CTA at the top of the nav so the console
        // opens on an action, not just a menu. Uses the resource's own canCreate()
        // gate, so a desk person without listings access never sees it.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner' ? '' : Blade::render(<<<'BLADE'
                @php
                    $isEvent = auth()->user()?->partner_type === 'event';
                    if ($isEvent) {
                        $url = \App\Filament\Resources\Events\EventResource::canCreate()
                            ? \App\Filament\Resources\Events\EventResource::getUrl('create') : null;
                        $label = 'Create event';
                    } else {
                        $url = \App\Filament\Resources\Venues\VenueResource::canCreate()
                            ? \App\Filament\Resources\Venues\VenueResource::getUrl('create') : null;
                        $label = 'Add venue';
                    }
                @endphp
                @if ($url)
                    <a href="{{ $url }}" class="hrn-create-cta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        <span>{{ $label }}</span>
                    </a>
                @endif
            BLADE),
        );

        // Mobile: collapse the global search into a magnifier icon that sits beside the
        // profile menu; tapping it drops the real search field down as a full-width bar
        // under the top bar (and auto-focuses it). Desktop keeps the inline search field.
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner' ? '' : Blade::render(<<<'BLADE'
                <button type="button" class="hrn-search-btn" aria-label="Search"
                        onclick="window.hrnToggleSearch(event)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-4.2-4.2"/>
                    </svg>
                </button>
                <script>
                    (function () {
                        if (window.hrnToggleSearch) return; // guard against SPA re-inits
                        window.hrnToggleSearch = function (e) {
                            if (e) { e.preventDefault(); e.stopPropagation(); }
                            var open = document.documentElement.classList.toggle('hrn-search-open');
                            if (open) {
                                requestAnimationFrame(function () {
                                    var inp = document.querySelector('.fi-topbar .fi-global-search input[type=search]');
                                    if (inp) inp.focus();
                                });
                            }
                        };
                        document.addEventListener('click', function (e) {
                            if (!document.documentElement.classList.contains('hrn-search-open')) return;
                            if (e.target.closest('.hrn-search-btn') || e.target.closest('.fi-global-search-ctn')) return;
                            document.documentElement.classList.remove('hrn-search-open');
                        });
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') document.documentElement.classList.remove('hrn-search-open');
                        });
                    })();
                </script>
            BLADE),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('partner')
            ->brandName('Haraan Partner')
            // The partner wordmark keeps its familiar public identity while the
            // application shell uses the shared indigo interaction system.
            ->brandLogo(asset('images/haraan-logo-blue.png'))
            ->brandLogoHeight('2.2rem')
            // Square blue-H-on-white tile — the wide wordmark was illegible as a
            // 16px browser-tab icon.
            ->favicon(asset('favicon-192.png'))
            // Shared type, surface, interaction and accessibility system.
            ->font('Inter')
            ->viteTheme('resources/css/filament/control/theme.css')
            ->login(PartnerLogin::class)
            ->passwordReset()
            // Own account page, rendered inside the console shell (not Filament's
            // bare "simple" layout, which read like the panel had dropped away).
            ->profile(\App\Filament\Pages\Partner\PartnerProfile::class, isSimple: false)
            ->colors([
                // Keep interaction colour consistent with the control and
                // employee workspaces; status green is never used as navigation.
                'primary' => Color::Indigo,
            ])
            // Day theme only — no dark mode, so the profile menu's light/dark/system
            // switch disappears and the console always renders on the light palette.
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
                // The floor. Self-gates to branch lanes with the bookings
                // capability, so event hosts and read-only staff never see it.
                \App\Filament\Pages\Partner\PartnerDesk::class,
                \App\Filament\Pages\Partner\PartnerEarnings::class,
                \App\Filament\Pages\Partner\PartnerPayouts::class,
                \App\Filament\Pages\Partner\PartnerReviews::class,
                \App\Filament\Pages\Partner\PartnerPlanPage::class,
                \App\Filament\Pages\Partner\PartnerPublicProfile::class,
                \App\Filament\Pages\Partner\PartnerSupport::class,
                \App\Filament\Pages\Partner\PartnerNotifications::class,
                \App\Filament\Pages\Partner\PartnerRosterPage::class,
                \App\Filament\Pages\Partner\PartnerAttendancePage::class,
                \App\Filament\Pages\Partner\PartnerTasksPage::class,
                \App\Filament\Pages\Partner\PartnerLeaveApprovalPage::class,
                \App\Filament\Pages\Partner\PartnerStaffAppraisalPage::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Same 403 as Filament's, but with a way out (see the middleware).
                \App\Http\Middleware\AuthenticateFilamentPanel::class,
            ]);
    }
}
