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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Event::class, EventPolicy::class);

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
            ]);
        });
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
}
