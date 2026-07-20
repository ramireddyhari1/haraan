<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;

/**
 * Puts each cluster's sections into the main sidebar, nested under the cluster's
 * name, instead of Filament's in-content sub-navigation.
 *
 * Filament hard-codes clustered resources out of the sidebar
 * ({@see \Filament\Resources\Resource\Concerns\HasNavigation::registerNavigationItems},
 * which returns early when a cluster is set) and renders their sub-navigation
 * inside the page — a left column on desktop and, on mobile, a single "Overview ▾"
 * dropdown above the content. There is no setting to move that into the sidebar,
 * and the alternative (turning clusters into navigation groups) would change every
 * clustered URL. So we register the children as ordinary sidebar items grouped by
 * cluster label, and switch the clusters' own nav item + sub-navigation off.
 *
 * Built inside Filament::serving() so the current panel and the authenticated user
 * are both resolved — access checks then reflect the real viewer, and the same code
 * serves every panel without a hard-coded map.
 */
class ClusterSidebarNavigation
{
    public static function register(): void
    {
        Filament::serving(function (): void {
            $panel = Filament::getCurrentPanel();

            if ($panel === null) {
                return;
            }

            $items = self::buildFor($panel);

            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }

    /** @return array<int, NavigationItem> */
    private static function buildFor(Panel $panel): array
    {
        $items = [];

        foreach ($panel->getPages() as $class) {
            if (! is_subclass_of($class, Cluster::class)) {
                continue;
            }

            if (! $class::canAccess()) {
                continue;
            }

            $group = $class::getClusterBreadcrumb() ?? $class::getNavigationLabel();
            $fallbackSort = 0;

            foreach (Filament::getClusteredComponents($class) as $component) {
                if (! method_exists($component, 'canAccess') || ! $component::canAccess()) {
                    continue;
                }

                $url = self::urlFor($component);

                if ($url === null) {
                    continue;
                }

                $items[] = NavigationItem::make($component::getNavigationLabel())
                    ->group($group)
                    ->icon($component::getNavigationIcon())
                    ->url($url)
                    ->sort($component::getNavigationSort() ?? $fallbackSort)
                    // Prefix match so a child page (e.g. venues/create) keeps its
                    // parent section highlighted.
                    ->isActiveWhen(fn (): bool => str_starts_with(
                        rtrim(request()->url(), '/'),
                        rtrim($url, '/'),
                    ));

                $fallbackSort++;
            }
        }

        return $items;
    }

    /** Resources and pages both expose a static getUrl(); guard anything that doesn't. */
    private static function urlFor(string $component): ?string
    {
        if (! method_exists($component, 'getUrl')) {
            return null;
        }

        try {
            return $component::getUrl();
        } catch (\Throwable) {
            // A component whose route isn't registered in this panel — skip it
            // rather than take the whole sidebar down.
            return null;
        }
    }
}
