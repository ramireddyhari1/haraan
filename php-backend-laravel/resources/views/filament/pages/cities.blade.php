<x-filament-panels::page>
    @php $mapsKey = (string) config('services.google_maps.key'); @endphp

    {{-- Enterprise City Directory Manager.
         Drives cities.json used across the consumer web & mobile apps.
         Preserves 100% of Livewire save() contract and raw JSON fallback. --}}
    <div
        wire:ignore
        x-data="citiesManager(@js($this->data['cities_json'] ?? '[]'), @js($mapsKey))"
        x-init="init()"
        class="space-y-6"
    >
        <!-- 1. Operational Telemetry Ribbon -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-[var(--hrn-border,#e2e8f0)] bg-[var(--hrn-surface,#ffffff)] p-4 shadow-sm dark:bg-gray-900 dark:border-white/10">
                <span class="text-xs font-semibold uppercase tracking-wider text-[var(--hrn-ink-2,#64748b)] dark:text-gray-400">Total Active Cities</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-[var(--hrn-ink,#0b1220)] dark:text-white" x-text="cities.length"></span>
                    <span class="text-xs text-[var(--hrn-ink-3,#94a3b8)]">in catalog</span>
                </div>
            </div>

            <div class="rounded-xl border border-[var(--hrn-border,#e2e8f0)] bg-[var(--hrn-surface,#ffffff)] p-4 shadow-sm dark:bg-gray-900 dark:border-white/10">
                <span class="text-xs font-semibold uppercase tracking-wider text-[var(--hrn-ink-2,#64748b)] dark:text-gray-400">Featured / Popular</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-amber-600 dark:text-amber-400" x-text="cities.filter(c => c.popular).length"></span>
                    <span class="text-xs text-[var(--hrn-ink-3,#94a3b8)]">pinned on home</span>
                </div>
            </div>

            <div class="rounded-xl border border-[var(--hrn-border,#e2e8f0)] bg-[var(--hrn-surface,#ffffff)] p-4 shadow-sm dark:bg-gray-900 dark:border-white/10">
                <span class="text-xs font-semibold uppercase tracking-wider text-[var(--hrn-ink-2,#64748b)] dark:text-gray-400">Geocoded</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400" x-text="cities.filter(c => c.lat && c.lng).length"></span>
                    <span class="text-xs text-[var(--hrn-ink-3,#94a3b8)]">with GPS coordinates</span>
                </div>
            </div>
        </div>

        <!-- 2. Dual-Add Action & Search Console -->
        <div class="rounded-xl border border-[var(--hrn-border,#e2e8f0)] bg-[var(--hrn-surface,#ffffff)] p-5 shadow-sm space-y-4 dark:bg-gray-900 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-[var(--hrn-border,#e2e8f0)] dark:border-white/10">
                <div>
                    <h3 class="text-base font-bold text-[var(--hrn-ink,#0b1220)] dark:text-white">Directory Actions</h3>
                    <p class="text-xs text-[var(--hrn-ink-2,#64748b)] dark:text-gray-400">Add new regional hubs via Google Places or manual entry</p>
                </div>

                <button
                    type="button"
                    x-on:click="showManualModal = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 active:scale-95 rounded-lg shadow-sm transition-all outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add City Manually
                </button>
            </div>

            <!-- Google Places Autocomplete (If key present) -->
            <template x-if="key">
                <div>
                    <label class="block text-xs font-semibold text-[var(--hrn-ink-2,#64748b)] dark:text-gray-400 mb-1.5">
                        Search via Google Places Autocomplete:
                    </label>
                    <div class="relative">
                        <input
                            x-ref="search"
                            type="text"
                            placeholder="Type an Indian city (e.g. Hyderabad, Coimbatore, Pune)…"
                            autocomplete="off"
                            class="block w-full rounded-lg border border-[var(--hrn-border,#e2e8f0)] bg-white px-3.5 py-2.5 pl-10 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <div class="absolute left-3 top-3 text-gray-400 pointer-events-none">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="!key">
                <div class="rounded-lg bg-blue-50 p-3.5 text-xs text-blue-800 dark:bg-blue-500/10 dark:text-blue-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600 flex-none" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        Google Places API key unconfigured — use the "Add City Manually" button above.
                    </span>
                </div>
            </template>

            <!-- Search, Filter Tabs & Sort Controls -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-2 flex-1 max-w-md">
                    <div class="relative w-full">
                        <input
                            type="text"
                            x-model="filterQuery"
                            placeholder="Filter current cities by name…"
                            aria-label="Filter current cities by name or country"
                            class="block w-full rounded-lg border border-[var(--hrn-border,#e2e8f0)] bg-white px-3 py-1.5 pl-8 text-xs shadow-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <div class="absolute left-2.5 top-2 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex p-0.5 rounded-lg bg-gray-100 dark:bg-white/10 text-xs">
                        <button
                            type="button"
                            x-on:click="activeFilter = 'all'"
                            :class="activeFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm font-bold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400'"
                            class="px-2.5 py-1 rounded-md transition"
                        >
                            All (<span x-text="cities.length"></span>)
                        </button>
                        <button
                            type="button"
                            x-on:click="activeFilter = 'popular'"
                            :class="activeFilter === 'popular' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm font-bold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400'"
                            class="px-2.5 py-1 rounded-md transition"
                        >
                            ★ Popular (<span x-text="cities.filter(c => c.popular).length"></span>)
                        </button>
                    </div>

                    <select
                        x-model="sortBy"
                        aria-label="Sort cities"
                        class="text-xs rounded-lg border border-[var(--hrn-border,#e2e8f0)] bg-white px-2.5 py-1.5 shadow-sm outline-none dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="az">A to Z</option>
                        <option value="za">Z to A</option>
                        <option value="popular">Popular First</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 3. City Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <template x-for="(c, i) in filteredCities" :key="(c.id || c.name) + '-' + i">
                <div class="group relative flex items-center justify-between gap-3 rounded-xl border border-[var(--hrn-border,#e2e8f0)] bg-[var(--hrn-surface,#ffffff)] p-3.5 shadow-sm hover:shadow hover:border-emerald-300 dark:bg-gray-900 dark:border-white/10 dark:hover:border-emerald-500/50 transition">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-bold text-sm text-[var(--hrn-ink,#0b1220)] dark:text-white" x-text="c.name"></span>
                            <template x-if="c.popular">
                                <span class="flex-none px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">★ Popular</span>
                            </template>
                        </div>
                        <div class="mt-1 text-xs text-[var(--hrn-ink-3,#94a3b8)] flex items-center gap-2 truncate">
                            <span x-text="c.country || 'India'"></span>
                            <template x-if="c.lat && c.lng">
                                <span class="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    · <span x-text="Number(c.lat).toFixed(2)"></span>, <span x-text="Number(c.lng).toFixed(2)"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 flex-none">
                        <button
                            type="button"
                            x-on:click="togglePopular(c)"
                            class="rounded-lg px-2 py-1 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-amber-500 outline-none"
                            :class="c.popular ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300'"
                            :title="c.popular ? 'Unmark from Popular' : 'Pin to Popular'"
                            :aria-label="c.popular ? ('Unmark ' + c.name + ' from popular') : ('Mark ' + c.name + ' as popular')"
                        >
                            <span x-text="c.popular ? '★' : '☆'" aria-hidden="true"></span>
                        </button>

                        <button
                            type="button"
                            x-on:click="confirmRemove(c)"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 transition focus-visible:ring-2 focus-visible:ring-red-500 outline-none"
                            :aria-label="'Remove ' + c.name + ' from catalog'"
                            title="Remove city from directory"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty Filter Result -->
        <template x-if="filteredCities.length === 0">
            <div class="rounded-xl border border-dashed border-[var(--hrn-border,#e2e8f0)] p-8 text-center dark:border-white/10">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <h4 class="mt-2 text-sm font-bold text-[var(--hrn-ink,#0b1220)] dark:text-white">No matching cities found</h4>
                <p class="mt-1 text-xs text-[var(--hrn-ink-2,#64748b)]">Try adjusting your filter or add a new city using the button above.</p>
                <button type="button" x-on:click="filterQuery = ''; activeFilter = 'all'" class="mt-3 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Clear search filter</button>
            </div>
        </template>

        <!-- 4. Manual Add Modal -->
        <div
            x-show="showManualModal"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="manual-add-title"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="showManualModal = false"
        >
            <div
                x-on:click.outside="showManualModal = false"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-gray-200 dark:bg-gray-900 dark:border-white/10 space-y-4"
            >
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-white/10">
                    <h3 id="manual-add-title" class="text-base font-bold text-gray-900 dark:text-white">Add City to Catalog</h3>
                    <button type="button" x-on:click="showManualModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1" aria-label="Close dialog">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">City Name *</label>
                        <input
                            type="text"
                            x-ref="newCityNameInput"
                            x-model="newCity.name"
                            placeholder="e.g. Visakhapatnam"
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Country</label>
                        <input
                            type="text"
                            x-model="newCity.country"
                            placeholder="India"
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Latitude (opt)</label>
                            <input
                                type="number"
                                step="any"
                                x-model="newCity.lat"
                                placeholder="17.6868"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Longitude (opt)</label>
                            <input
                                type="number"
                                step="any"
                                x-model="newCity.lng"
                                placeholder="83.2185"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="pt-2 flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="chk-new-popular"
                            x-model="newCity.popular"
                            class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                        />
                        <label for="chk-new-popular" class="text-xs font-medium text-gray-700 dark:text-gray-300 cursor-pointer">Mark as Popular / Featured Hub</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-white/10">
                    <button
                        type="button"
                        x-on:click="showManualModal = false"
                        class="px-3.5 py-2 text-xs font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="submitManualAdd()"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 active:scale-95 rounded-lg shadow-sm transition outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        Add to Catalog
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. Delete Confirmation Dialog (Replaces native window.confirm) -->
        <div
            x-show="showDeleteModal"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-dialog-title"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="showDeleteModal = false"
        >
            <div
                x-on:click.outside="showDeleteModal = false"
                class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl border border-gray-200 dark:bg-gray-900 dark:border-white/10 space-y-3"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400 flex items-center justify-center flex-none">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <div>
                        <h4 id="delete-dialog-title" class="text-sm font-bold text-gray-900 dark:text-white">Remove City?</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">This will remove <strong class="text-gray-800 dark:text-gray-200" x-text="cityToDelete?.name"></strong> from the active city directory.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-white/10">
                    <button
                        type="button"
                        x-on:click="showDeleteModal = false"
                        class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300"
                    >
                        Keep City
                    </button>
                    <button
                        type="button"
                        x-on:click="executeRemove()"
                        class="px-3.5 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. Floating In-App Toast Notification -->
        <div
            x-show="toast.show"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-2.5 rounded-xl shadow-xl border text-xs font-bold backdrop-blur-md"
            :class="{
                'bg-gray-900/95 text-white border-white/10': toast.type === 'info',
                'bg-emerald-900/95 text-emerald-200 border-emerald-500/30': toast.type === 'success',
                'bg-red-900/95 text-red-200 border-red-500/30': toast.type === 'error',
                'bg-amber-900/95 text-amber-200 border-amber-500/30': toast.type === 'warn'
            }"
            role="status"
            aria-live="polite"
        >
            <span x-text="toast.message"></span>
        </div>

        <!-- 7. Save Changes Form & Advanced Fallback -->
        <form wire:submit="save" class="pt-4 border-t border-[var(--hrn-border,#e2e8f0)] dark:border-white/10 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs text-[var(--hrn-ink-2,#64748b)]">
                    <span x-show="hasUnsavedChanges" class="inline-flex items-center gap-1 text-amber-600 font-bold">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Unsaved modifications pending
                    </span>
                    <span x-show="!hasUnsavedChanges" class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Synced with cities.json
                    </span>
                </div>

                <x-filament::button type="submit" icon="heroicon-m-check" color="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save Changes to cities.json</span>
                    <span wire:loading>Saving Changes...</span>
                </x-filament::button>
            </div>

            <details class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <summary class="cursor-pointer text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Developer &amp; Power User: Inspect / Edit Raw JSON
                </summary>
                <div class="mt-3">
                    {{ $this->form }}
                </div>
            </details>
        </form>
    </div>

    <script>
        function citiesManager(initialJson, key) {
            return {
                key: key,
                cities: [],
                filterQuery: '',
                activeFilter: 'all',
                sortBy: 'az',
                showManualModal: false,
                showDeleteModal: false,
                cityToDelete: null,
                toast: { show: false, message: '', type: 'info', timer: null },
                hasUnsavedChanges: false,
                newCity: {
                    name: '',
                    country: 'India',
                    lat: '',
                    lng: '',
                    popular: false
                },

                init() {
                    try {
                        const parsed = JSON.parse(initialJson);
                        this.cities = Array.isArray(parsed) ? parsed : [];
                    } catch (e) {
                        this.cities = [];
                    }
                    if (this.key) {
                        this.loadGoogle().then(() => this.buildAutocomplete()).catch(() => {});
                    }
                },

                notify(message, type = 'info') {
                    if (this.toast.timer) clearTimeout(this.toast.timer);
                    this.toast.message = message;
                    this.toast.type = type;
                    this.toast.show = true;
                    this.toast.timer = setTimeout(() => {
                        this.toast.show = false;
                    }, 3200);
                },

                get filteredCities() {
                    let list = this.cities.filter(c => {
                        const matchesQuery = !this.filterQuery ||
                            (c.name || '').toLowerCase().includes(this.filterQuery.toLowerCase()) ||
                            (c.country || '').toLowerCase().includes(this.filterQuery.toLowerCase());
                        
                        const matchesFilter = this.activeFilter === 'all' ||
                            (this.activeFilter === 'popular' && c.popular);

                        return matchesQuery && matchesFilter;
                    });

                    if (this.sortBy === 'az') {
                        list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                    } else if (this.sortBy === 'za') {
                        list.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
                    } else if (this.sortBy === 'popular') {
                        list.sort((a, b) => (b.popular ? 1 : 0) - (a.popular ? 1 : 0));
                    }

                    return list;
                },

                loadGoogle() {
                    return window.__haraanGooglePromise || (window.__haraanGooglePromise = new Promise((resolve, reject) => {
                        if (window.google && window.google.maps && window.google.maps.places) { resolve(); return; }
                        window.__haraanGmapsReady = () => resolve();
                        const s = document.createElement('script');
                        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.key)
                              + '&libraries=places&loading=async&callback=__haraanGmapsReady';
                        s.async = true;
                        s.onerror = () => reject('load-failed');
                        document.head.appendChild(s);
                    }));
                },

                buildAutocomplete() {
                    const input = this.$refs.search;
                    if (!input || !window.google) return;
                    const ac = new google.maps.places.Autocomplete(input, {
                        types: ['(cities)'],
                        componentRestrictions: { country: 'in' },
                        fields: ['name', 'geometry', 'address_components'],
                    });
                    ac.addListener('place_changed', () => {
                        const p = ac.getPlace();
                        if (!p || !p.name) return;
                        let country = 'India';
                        (p.address_components || []).forEach((comp) => {
                            if (comp.types.includes('country')) country = comp.long_name;
                        });
                        const loc = p.geometry && p.geometry.location;
                        this.add({
                            id: this.slug(p.name),
                            name: p.name.trim(),
                            country: country,
                            popular: false,
                            lat: loc ? +loc.lat().toFixed(5) : null,
                            lng: loc ? +loc.lng().toFixed(5) : null,
                        });
                        input.value = '';
                    });
                },

                slug(s) {
                    return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                },

                add(city) {
                    const dup = this.cities.some((c) =>
                        (c.id || '') === city.id ||
                        String(c.name || '').toLowerCase() === city.name.toLowerCase()
                    );
                    if (dup) {
                        this.notify('City "' + city.name + '" is already present in the catalog.', 'warn');
                        return;
                    }
                    this.cities.push(city);
                    this.sync();
                    this.notify('Added ' + city.name + ' to catalog.', 'success');
                },

                submitManualAdd() {
                    if (!this.newCity.name.trim()) {
                        this.notify('Please provide a valid city name.', 'warn');
                        return;
                    }
                    const city = {
                        id: this.slug(this.newCity.name),
                        name: this.newCity.name.trim(),
                        country: this.newCity.country.trim() || 'India',
                        popular: Boolean(this.newCity.popular),
                        lat: this.newCity.lat ? Number(this.newCity.lat) : null,
                        lng: this.newCity.lng ? Number(this.newCity.lng) : null,
                    };
                    this.add(city);
                    this.newCity = { name: '', country: 'India', lat: '', lng: '', popular: false };
                    this.showManualModal = false;
                },

                togglePopular(city) {
                    city.popular = !city.popular;
                    this.sync();
                    this.notify(city.name + (city.popular ? ' marked as popular' : ' unmarked as popular'), 'info');
                },

                confirmRemove(city) {
                    this.cityToDelete = city;
                    this.showDeleteModal = true;
                },

                executeRemove() {
                    if (!this.cityToDelete) return;
                    const targetName = this.cityToDelete.name;
                    const idx = this.cities.findIndex(c => (c.id || c.name) === (this.cityToDelete.id || this.cityToDelete.name));
                    if (idx !== -1) {
                        this.cities.splice(idx, 1);
                        this.sync();
                        this.notify('Removed ' + targetName + ' from catalog.', 'info');
                    }
                    this.cityToDelete = null;
                    this.showDeleteModal = false;
                },

                sync() {
                    this.hasUnsavedChanges = true;
                    this.$wire.set('data.cities_json', JSON.stringify(this.cities, null, 2), false);
                },
            };
        }
    </script>
</x-filament-panels::page>

