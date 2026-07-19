<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\Page as ResourcePage;

/**
 * Renders a cluster's child pages/resources as nested items in the main sidebar,
 * instead of Filament's default in-content sub-navigation (a left column on
 * desktop, a "Overview ▾" dropdown at the top on mobile).
 *
 * The cluster keeps its routes, URLs and breadcrumbs untouched — only the
 * navigation entry changes: the sidebar item gains its clustered components as
 * childItems, so the section expands to reveal Overview / <resources> when it (or
 * one of its pages) is active. Pair this with CSS that hides the now-duplicate
 * in-content sub-navigation (see the panel providers' render hook).
 *
 * Access is gated per component exactly as the sub-navigation was, so partners
 * still only see the pages their role allows.
 */
trait NestsClusterItemsInSidebar
{
    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $children = [];

        foreach (static::getClusteredComponents() as $component) {
            $isResourcePage = is_subclass_of($component, ResourcePage::class);

            $shouldRegister = $isResourcePage
                ? $component::shouldRegisterNavigation([])
                : $component::shouldRegisterNavigation();
            if (! $shouldRegister) {
                continue;
            }

            $canAccess = $isResourcePage
                ? $component::canAccess([])
                : $component::canAccess();
            if (! $canAccess) {
                continue;
            }

            $items = $isResourcePage
                ? $component::getNavigationItems([])
                : $component::getNavigationItems();

            foreach ($items as $item) {
                $children[] = $item;
            }
        }

        // Nothing this role can reach → register nothing (matches the cluster's
        // own shouldRegisterNavigation() gate).
        if ($children === []) {
            return [];
        }

        // Filament sorts top-level nav items but not child items, so order them
        // by each component's navigationSort (Overview first, etc.) ourselves.
        usort($children, fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->icon(static::getNavigationIcon())
                ->url(static::getUrl())
                ->sort(static::getNavigationSort())
                ->group(static::getNavigationGroup())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getNavigationItemActiveRoutePattern()))
                ->childItems($children),
        ];
    }
}
