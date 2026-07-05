<?php

namespace App\Providers;

use App\Models\Event;
use App\Policies\EventPolicy;
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
    }
}
