@php
    $debounce = filament()->getGlobalSearchDebounce();
    $keyBindings = filament()->getGlobalSearchKeyBindings();
    $suffix = filament()->getGlobalSearchFieldSuffix();
@endphp

<div class="fi-global-search-ctn hrn-global-search-ctn">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_START) }}

    <div
        x-on:focus-first-global-search-result.stop="$el.querySelector('.fi-global-search-result-link')?.focus()"
        class="fi-global-search hrn-global-search"
        x-data="{ isFocused: false }"
    >
        <div x-id="['input']" class="fi-global-search-field hrn-search-field-box">
            <label x-bind:for="$id('input')" class="fi-sr-only">
                {{ __('filament-panels::global-search.field.label') }}
            </label>

            <div
                class="hrn-search-wrapper"
                x-bind:class="{ 'is-focused': isFocused }"
                x-on:click="document.getElementById($id('input'))?.focus()"
            >
                <div class="hrn-search-icon-slot">
                    <svg class="hrn-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="9" r="6"></circle>
                        <path d="m13.5 13.5 4 4"></path>
                    </svg>
                </div>

                <input
                    autocomplete="off"
                    maxlength="1000"
                    placeholder="Search or jump to..."
                    type="search"
                    wire:key="global-search.field.input"
                    x-bind:id="$id('input')"
                    x-on:focus="isFocused = true"
                    x-on:blur="isFocused = false"
                    x-on:keydown.down.prevent.stop="$dispatch('focus-first-global-search-result')"
                    wire:model.live.debounce.{{ $debounce }}="search"
                    x-mousetrap.global.{{ collect($keyBindings)->map(fn (string $keyBinding): string => str_replace('+', '-', $keyBinding))->implode('.') }}="document.getElementById($id('input'))?.focus()"
                    class="hrn-search-input-native"
                />

                <div class="hrn-search-keycaps" aria-hidden="true" x-data="{ isMac: typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.platform) }">
                    <kbd class="hrn-kbd" x-text="isMac ? '⌘' : 'Ctrl'">Ctrl</kbd>
                    <kbd class="hrn-kbd">K</kbd>
                </div>
            </div>
        </div>

        @if ($results !== null)
            <div
                x-data="{
                    isOpen: false,

                    open(event) {
                        this.isOpen = true
                    },

                    close(event) {
                        this.isOpen = false
                    },
                }"
                x-init="$nextTick(() => open())"
                x-on:click.away="close()"
                x-on:keydown.escape.window="close()"
                x-on:keydown.up.prevent="$focus.wrap().previous()"
                x-on:keydown.down.prevent="$focus.wrap().next()"
                x-on:open-global-search-results.window="$nextTick(() => open())"
                x-show="isOpen"
                x-transition:enter="hrn-search-results-enter"
                x-transition:enter-start="hrn-search-results-enter-start"
                x-transition:enter-end="hrn-search-results-enter-end"
                x-transition:leave="hrn-search-results-leave"
                x-transition:leave-start="hrn-search-results-leave-start"
                x-transition:leave-end="hrn-search-results-leave-end"
                class="fi-global-search-results-ctn hrn-search-results-card"
            >
                @if ($results->getCategories()->isEmpty())
                    <div class="hrn-search-empty-state">
                        <svg class="hrn-search-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                            <path d="M8 11h6"></path>
                        </svg>
                        <p class="hrn-search-empty-text">
                            No matching results found for this query
                        </p>
                    </div>
                @else
                    <ul class="fi-global-search-results hrn-search-results-list">
                        @foreach ($results->getCategories() as $group => $groupedResults)
                            <li class="fi-global-search-result-group hrn-result-group">
                                <div class="hrn-result-group-header">
                                    <span class="hrn-result-group-title">{{ $group }}</span>
                                    <span class="hrn-result-group-count">{{ count($groupedResults) }}</span>
                                </div>

                                <ul class="fi-global-search-result-group-results hrn-result-items">
                                    @foreach ($groupedResults as $result)
                                        @php
                                            $resultVisibleActions = $result->getVisibleActions();
                                        @endphp

                                        <li
                                            @class([
                                                'fi-global-search-result hrn-result-item',
                                                'fi-global-search-result-has-actions' => $resultVisibleActions,
                                            ])
                                        >
                                            <a
                                                {{ \Filament\Support\generate_href_html($result->url) }}
                                                x-on:click="close()"
                                                class="fi-global-search-result-link hrn-result-link"
                                            >
                                                <div class="hrn-result-icon">
                                                    <svg viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                                                        <path fill-rule="evenodd" d="M2 3.75A.75.75 0 012.75 3h10.5a.75.75 0 010 1.5H2.75A.75.75 0 012 3.75zM2 8a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H2.75A.75.75 0 012 8zm0 4.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div class="hrn-result-meta">
                                                    <h4 class="fi-global-search-result-heading hrn-result-title">
                                                        {{ $result->title }}
                                                    </h4>

                                                    @if ($result->details)
                                                        <dl class="fi-global-search-result-details hrn-result-details">
                                                            @foreach ($result->details as $label => $value)
                                                                <div class="fi-global-search-result-detail hrn-result-detail-pair">
                                                                    @if ($isAssoc ??= \Illuminate\Support\Arr::isAssoc($result->details))
                                                                        <dt class="fi-global-search-result-detail-label hrn-result-detail-key">
                                                                            {{ $label }}:
                                                                        </dt>
                                                                    @endif

                                                                    <dd class="fi-global-search-result-detail-value hrn-result-detail-val">
                                                                        {{ $value }}
                                                                    </dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    @endif
                                                </div>
                                                <svg class="hrn-result-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M6 3l5 5-5 5" />
                                                </svg>
                                            </a>

                                            @if ($resultVisibleActions)
                                                <div class="fi-global-search-result-actions hrn-result-actions">
                                                    @foreach ($resultVisibleActions as $action)
                                                        {{ $action }}
                                                    @endforeach
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_END) }}
</div>
