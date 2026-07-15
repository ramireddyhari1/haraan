<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Services\SupportChat;
use App\Support\CityResolver;
use Illuminate\Support\Facades\Gate;
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
