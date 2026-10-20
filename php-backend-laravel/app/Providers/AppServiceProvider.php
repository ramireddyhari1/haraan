<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Services\SupportChat;
use App\Support\CityResolver;
use Filament\Resources\Pages\CreateRecord;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Member entitlements memoise per request/job; scoped so a queue worker or a long
        // test never serves one member's plan from a previous request.
        $this->app->scoped(\App\Services\Membership\MemberEntitlements::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Event::class, EventPolicy::class);

        $this->showSystemTimestampsInBusinessTime();

        // Signing out of the partner web app unhooks that browser from booking alerts,
        // so a shared desk iPhone stops buzzing for the partner who just left. Logout
        // fires before the session is invalidated, so the token is still readable.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function (\Illuminate\Auth\Events\Logout $event): void {
            if (! app()->bound('session') || $event->user === null) {
                return;
            }

            // Signing out of a member browser frees its device slot.
            $sid = session()->get(\App\Http\Middleware\EnforceMemberDevice::SESSION_KEY);
            if (is_string($sid) && $sid !== '') {
                $device = \App\Models\MemberDevice::query()
                    ->where('public_id', $sid)
                    ->where('user_id', $event->user->getAuthIdentifier())
                    ->first();
                if ($device !== null) {
                    app(\App\Services\Membership\MemberDevices::class)->revoke($device, \App\Models\MemberDevice::REASON_SIGNED_OUT);
                }
                session()->forget(\App\Http\Middleware\EnforceMemberDevice::SESSION_KEY);
            }

            $token = session()->pull('partner_web_push_token');

            if (is_string($token) && $token !== '') {
                \App\Models\DeviceToken::query()
                    ->where('token', $token)
                    ->where('user_id', $event->user->getAuthIdentifier())
                    ->delete();
            }
        });

        // Hide the "Create & create another" button on every create form in both
        // panels. Records here are created one at a time, so the rapid multi-add
        // affordance only added clutter. Set on the base page — no subclass
        // redeclares $canCreateAnother, so all 19 create pages inherit it.
        CreateRecord::disableCreateAnother();

        // Consistent empty-state affordance across every Filament table (both panels):
        // a friendlier icon + striped layout. Individual tables can still override, and
        // Filament keeps deriving the heading from each resource's model label.
        Table::configureUsing(function (Table $table): void {
            $table->emptyStateIcon('heroicon-o-inbox')->striped();
        });

        // Cluster sections render as nested sidebar items rather than Filament's
        // in-content sub-navigation. Registered once here (not per panel) because
        // it resolves the current panel itself — twice would duplicate every item.
        \App\Filament\Support\ClusterSidebarNavigation::register();

        $this->configureRateLimiters();

        // Share the viewer's selected city with every view so the header pill
        // and any page can reflect / scope by it. Null = "All India".
        View::composer('*', function ($view): void {
            $view->with('selectedCity', CityResolver::selected());
        });

        // Unread counts behind the site header's chat + bell icons. Scoped to the
        // layout (not '*') so the two queries only run for a page that renders the
        // header, and only for a signed-in viewer — a guest has no inbox.
        View::composer('site.layout', function ($view): void {
            $user = auth()->user();

            $view->with([
                'headerSupportUnread' => $user ? app(SupportChat::class)->unreadFor($user) : 0,
                'headerBellUnread'    => $user ? $this->unreadNotifications($user) : 0,
                'headerSearchHints'   => $this->searchHints(),
            ]);
        });
    }

    /**
     * Names the mobile search field cycles through ("Search for 'Gaurav Gupta Live'").
     * Only real, bookable things: upcoming published events, soonest first.
     * Cached briefly because it runs on every page that renders the header. An empty
     * list means the field keeps its plain placeholder, never an invented example.
     *
     * @return list<string>
     */
    private function searchHints(): array
    {
        try {
            return Cache::remember('site:search-hints', now()->addMinutes(10), function (): array {
                $events = Event::query()
                    ->whereRaw('lower(status) = ?', ['published'])
                    ->notFinished()
                    ->orderBy('date')
                    ->limit(4)
                    ->pluck('title');

                return $events
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter(fn (string $name): bool => $name !== '' && mb_strlen($name) <= 60)
                    ->unique()
                    ->values()
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Abuse / cost protection for the API. Each limiter is referenced by name from routes/api.php
     * via the `throttle:<name>` middleware. A blown limit returns HTTP 429 with a Retry-After
     * header — Laravel handles the response, we only declare the ceilings here.
     */
    private function configureRateLimiters(): void
    {
        // OTP send is the most expensive & abusable path (SMTP + WhatsApp cost, and a spam vector).
        // Guard the *destination* (email / phone) AND the source IP so neither a single target nor a
        // single origin can be hammered. Keyed on the request payload, falling back to IP.
        RateLimiter::for('otp', function (Request $request) {
            $target = (string) ($request->input('email')
                ?? $request->input('phone')
                ?? $request->input('mobile')
                ?? $request->ip());

            return [
                Limit::perMinutes(10, 5)->by('otp:target:' . mb_strtolower($target)),
                Limit::perMinutes(10, 20)->by('otp:ip:' . $request->ip()),
            ];
        });

        // Credential / token endpoints (login, register, google, verify). Brute-force ceiling per IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Payment order creation & verification — tie to the signed-in user when we have one.
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(30)
            ->by(optional($request->user())->id ? 'u:' . $request->user()->id : 'ip:' . $request->ip()));

        // Broad default for the rest of the API: generous, just a runaway-client / scraper backstop.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by(optional($request->user())->id ? 'u:' . $request->user()->id : 'ip:' . $request->ip()));
    }

    /** Delivered notifications aimed at this user that they haven't opened yet. */
    private function unreadNotifications(User $user): int
    {
        return Notification::query()
            ->sent()
            ->forUser($user)
            ->whereNotIn('id', NotificationRead::query()
                ->where('user_id', $user->id)
                ->select('notification_id'))
            ->count();
    }

    /**
     * Columns holding instants the system wrote (UTC in the database). Admin-entered schedule
     * fields (starts_at, ends_at, expires_at…) are left out: those pickers store the wall-clock
     * time as typed, so converting them on display would shift what the admin entered.
     */
    public const SYSTEM_TIMESTAMPS = [
        'created_at', 'updated_at', 'deleted_at', 'last_seen_at', 'last_active_at', 'last_used_at',
        'last_message_at', 'signed_in_at', 'email_verified_at', 'verified_at', 'processed_at', 'paid_at',
        'collected_at', 'refunded_at', 'cancelled_at', 'completed_at', 'checked_in_at', 'approved_at',
        'reviewed_at', 'published_at', 'featured_at', 'sent_at', 'read_at', 'notified_at', 'opened_at',
        'closed_at', 'occurred_at', 'assigned_at', 'clock_in_at', 'clock_out_at', 'token_expires_at',
        'offer_expires_at',
    ];

    /**
     * The app clock is UTC on purpose (BusinessClock), so every "created at" in /control and
     * /partner read 5½ hours behind, unlabelled. Show system instants in the business zone.
     * Time-of-day columns (->time()) hold wall-clock times and are never converted.
     */
    private function showSystemTimestampsInBusinessTime(): void
    {
        $zoneFor = static function (string $name, bool $isTime): ?string {
            $field = str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;

            return ! $isTime && in_array($field, self::SYSTEM_TIMESTAMPS, true)
                ? \App\Support\BusinessClock::zone()
                : null; // null = Filament's default
        };

        \Filament\Tables\Columns\TextColumn::configureUsing(static function (\Filament\Tables\Columns\TextColumn $column) use ($zoneFor): void {
            $column->timezone(static fn (\Filament\Tables\Columns\TextColumn $column): ?string => $zoneFor($column->getName(), $column->isTime()));
        });

        \Filament\Infolists\Components\TextEntry::configureUsing(static function (\Filament\Infolists\Components\TextEntry $entry) use ($zoneFor): void {
            $entry->timezone(static fn (\Filament\Infolists\Components\TextEntry $component): ?string => $zoneFor($component->getName(), $component->isTime()));
        });
    }
}
