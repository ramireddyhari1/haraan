@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasActiveChild = $activeChildItems;
    $isCurrentActive = $active || $hasActiveChild;
@endphp

<li
    {{
        $attributes->class([
            'fi-sidebar-item hrn-sb-item',
            'fi-active' => $isCurrentActive,
            'hrn-sb-item-active' => $active,
            'hrn-sb-item-parent-active' => $hasActiveChild,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
        ])
    }}
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @if ($sidebarCollapsible && (! $subNavigation))
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn hrn-sb-item-btn"
    >
        {{-- Linear Left Accent Indicator Bar (Active State) --}}
        <span class="hrn-sb-indicator" aria-hidden="true"></span>

        {{-- Icon --}}
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            <span class="hrn-sb-item-icon-wrapper">
                {{
                    \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Illuminate\View\ComponentAttributeBag([
                        'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                    ]))->class(['fi-sidebar-item-icon hrn-sb-item-icon']), size: \Filament\Support\Enums\IconSize::Medium)
                }}
            </span>
        @endif

        {{-- Subgrouped Connector Line (Tree Guide) --}}
        @if ((blank($icon) && $grouped) || $subGrouped)
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border hrn-sb-tree-guide"
            >
                <span class="hrn-sb-tree-stem"></span>
                <span class="hrn-sb-tree-curve"></span>
            </div>
        @endif

        {{-- Item Label --}}
        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label hrn-sb-item-label"
        >
            {{ $slot }}
        </span>

        {{-- Item Badge (Linear / Stripe style compact tabular pill) --}}
        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                @if (filled($badgeTooltip))
                    x-tooltip.html="@js($badgeTooltip)"
                @endif
                class="fi-sidebar-item-badge hrn-sb-item-badge"
            >
                {{ $badge }}
            </span>
        @endif
    </a>

    {{-- Nested Child Items (Linear-style sub-list) --}}
    @if (! empty($childItems))
        <ul
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
            @endif
            class="fi-sidebar-sub-group-items hrn-sb-sub-items"
        >
            @foreach ($childItems as $childItem)
                {{ $childItem }}
            @endforeach
        </ul>
    @endif
</li>
