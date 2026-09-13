@props([
    'active' => false,
    'collapsible' => true,
    'icon' => null,
    'items' => [],
    'label' => null,
    'sidebarCollapsible' => true,
    'subNavigation' => false,
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasDropdown = filled($label) && filled($icon) && $sidebarCollapsible;
    $displayLabel = match($label) {
        'GameHub' => 'Game Hub',
        default => $label,
    };
@endphp

<li
    x-data="{ label: @js($subNavigation ? "sub_navigation_{$label}" : $label) }"
    data-group-label="{{ $subNavigation ? "sub_navigation_{$label}" : $label }}"
    x-bind:class="{ 'fi-collapsed': $store.sidebar.groupIsCollapsed(label) }"
    {{
        $attributes->class([
            'fi-sidebar-group hrn-sb-group',
            'hrn-sb-group-standalone' => blank($label),
            'fi-active' => $active,
            'fi-collapsible' => $collapsible,
        ])
    }}
>
    @if ($label)
        <div
            @if ($collapsible)
                x-on:click="$store.sidebar.toggleCollapsedGroup(label)"
            @endif
            @if ($sidebarCollapsible)
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-group-btn hrn-sb-group-btn"
            role="{{ $collapsible ? 'button' : 'presentation' }}"
            tabindex="{{ $collapsible ? '0' : '-1' }}"
            @if ($collapsible)
                x-on:keydown.enter.prevent="$store.sidebar.toggleCollapsedGroup(label)"
                x-on:keydown.space.prevent="$store.sidebar.toggleCollapsedGroup(label)"
            @endif
        >
            <div class="hrn-sb-group-title-ctn">
                @if ($icon)
                    <span class="hrn-sb-group-icon">
                        {{ \Filament\Support\generate_icon_html($icon, size: \Filament\Support\Enums\IconSize::Medium) }}
                    </span>
                @endif

                <span class="fi-sidebar-group-label hrn-sb-group-label">
                    {{ $displayLabel }}
                </span>
            </div>

            @if ($collapsible)
                <button
                    type="button"
                    class="hrn-sb-group-collapse-indicator"
                    x-bind:aria-expanded="! $store.sidebar.groupIsCollapsed(label)"
                    aria-label="{{ $displayLabel }}"
                    tabindex="-1"
                >
                    <svg class="hrn-sb-group-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 6l4 4 4-4" />
                    </svg>
                </button>
            @endif
        </div>
    @endif

    {{-- Collapsed Rail Flyout Dropdown --}}
    @if ($hasDropdown)
        <x-filament::dropdown
            :placement="(__('filament-panels::layout.direction') === 'rtl') ? 'left-start' : 'right-start'"
            x-show="! $store.sidebar.isOpen"
            teleport
            class="hrn-sb-group-dropdown"
        >
            <x-slot name="trigger">
                <button
                    x-data="{ tooltip: false }"
                    x-effect="
                        tooltip = $store.sidebar.isOpen
                            ? false
                            : {
                                  content: @js($displayLabel),
                                  placement: document.dir === 'rtl' ? 'left' : 'right',
                                  theme: $store.theme,
                              }
                    "
                    x-tooltip.html="tooltip"
                    class="fi-sidebar-group-dropdown-trigger-btn hrn-sb-rail-trigger"
                    aria-label="{{ $displayLabel }}"
                >
                    {{ \Filament\Support\generate_icon_html($icon, size: \Filament\Support\Enums\IconSize::Medium) }}
                </button>
            </x-slot>

            @php
                $lists = [];

                foreach ($items as $item) {
                    if ($childItems = $item->getChildItems()) {
                        $lists[] = [
                            $item,
                            ...$childItems,
                        ];
                        $lists[] = [];

                        continue;
                    }

                    if (empty($lists)) {
                        $lists[] = [$item];

                        continue;
                    }

                    $lists[count($lists) - 1][] = $item;
                }

                if (empty($lists[count($lists) - 1])) {
                    array_pop($lists);
                }
            @endphp

            @if (filled($displayLabel))
                <div class="hrn-sb-flyout-header">
                    <span class="hrn-sb-flyout-title">{{ $displayLabel }}</span>
                </div>
            @endif

            @foreach ($lists as $list)
                <x-filament::dropdown.list class="hrn-sb-flyout-list">
                    @foreach ($list as $item)
                        @php
                            $isItemActive = $item->isActive();
                            $itemBadge = $item->getBadge();
                            $itemIcon = $item->getIcon();
                            $itemUrl = $item->getUrl();
                            $shouldOpenItemUrlInNewTab = $item->shouldOpenUrlInNewTab();
                        @endphp

                        <x-filament::dropdown.list.item
                            :badge="$itemBadge"
                            :href="$itemUrl"
                            :icon="$itemIcon"
                            tag="a"
                            :target="$shouldOpenItemUrlInNewTab ? '_blank' : null"
                            class="{{ $isItemActive ? 'hrn-sb-flyout-active' : '' }}"
                        >
                            {{ $item->getLabel() }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endforeach
        </x-filament::dropdown>
    @endif

    {{-- Group Items List --}}
    <ul
        @if (filled($label))
            @if ($sidebarCollapsible)
                x-show="$store.sidebar.isOpen ? ! $store.sidebar.groupIsCollapsed(label) : ! @js($hasDropdown)"
            @else
                x-show="! $store.sidebar.groupIsCollapsed(label)"
            @endif
            x-collapse.duration.180ms
        @endif
        @if ($sidebarCollapsible)
            x-transition:enter="fi-transition-enter"
            x-transition:enter-start="fi-transition-enter-start"
            x-transition:enter-end="fi-transition-enter-end"
        @endif
        class="fi-sidebar-group-items hrn-sb-group-items"
    >
        @foreach ($items as $item)
            @php
                $isItemChildItemsActive = $item->isChildItemsActive();
                $isItemActive = (! $isItemChildItemsActive) && $item->isActive();
                $itemActiveIcon = $item->getActiveIcon();
                $itemBadge = $item->getBadge();
                $itemBadgeColor = $item->getBadgeColor($itemBadge);
                $itemBadgeTooltip = $item->getBadgeTooltip($itemBadge);
                $itemChildItems = $item->getChildItems();
                $itemIcon = $item->getIcon();
                $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                $itemUrl = $item->getUrl();
                $itemExtraAttributes = $item->getExtraAttributeBag();
            @endphp

            <x-filament-panels::sidebar.item
                :active="$isItemActive"
                :active-child-items="$isItemChildItemsActive"
                :active-icon="$itemActiveIcon"
                :badge="$itemBadge"
                :badge-color="$itemBadgeColor"
                :badge-tooltip="$itemBadgeTooltip"
                :child-items="$itemChildItems"
                :first="$loop->first"
                :grouped="filled($label)"
                :icon="$itemIcon"
                :last="$loop->last"
                :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                :sidebar-collapsible="$sidebarCollapsible"
                :sub-navigation="$subNavigation"
                :url="$itemUrl"
                :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraAttributes)"
            >
                {{ $item->getLabel() }}

                @if ($itemIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                    <x-slot name="icon">
                        {{ $itemIcon }}
                    </x-slot>
                @endif

                @if ($itemActiveIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                    <x-slot name="activeIcon">
                        {{ $itemActiveIcon }}
                    </x-slot>
                @endif
            </x-filament-panels::sidebar.item>
        @endforeach
    </ul>
</li>
