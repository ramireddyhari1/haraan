<x-filament-panels::page>
    @php $mapsKey = (string) config('services.google_maps.key'); @endphp

    {{-- Google Maps–powered city manager. Search a city → its name + coordinates are
         added to the list that drives the app/website city pickers (cities.json). The
         raw JSON editor is kept below as an advanced fallback. --}}
    <div
        wire:ignore
        x-data="citiesManager(@js($this->data['cities_json'] ?? '[]'), @js($mapsKey))"
        x-init="init()"
        class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Cities</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Search Google Maps to add a city — name and coordinates are saved.</p>
            </div>
            <span class="text-sm text-gray-500"><span x-text="cities.length"></span> cities</span>
        </div>

        <template x-if="key">
            <div class="mt-4">
                <input
                    x-ref="search"
                    type="text"
                    placeholder="Search a city (e.g. Bengaluru)…"
                    autocomplete="off"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                />
            </div>
        </template>
        <template x-if="!key">
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-500/10">
                No Google Maps API key configured (services.google_maps.key) — use the raw JSON editor below.
            </p>
        </template>

        <div class="mt-4 space-y-2">
            <template x-for="(c, i) in cities" :key="(c.id || c.name) + '-' + i">
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-gray-950 dark:text-white" x-text="c.name"></div>
                        <div class="text-xs text-gray-500">
                            <span x-text="c.country || 'India'"></span>
                            <template x-if="c.lat && c.lng">
                                <span> · <span x-text="Number(c.lat).toFixed(3)"></span>, <span x-text="Number(c.lng).toFixed(3)"></span></span>
                            </template>
                        </div>
                    </div>
                    <button
                        type="button"
                        x-on:click="c.popular = !c.popular; sync()"
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="c.popular ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-500 dark:bg-white/5'"
                        x-text="c.popular ? '★ Popular' : '☆ Popular'"
                    ></button>
                    <button
                        type="button"
                        x-on:click="remove(i)"
                        class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        title="Remove"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </template>
            <template x-if="cities.length === 0">
                <p class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-sm text-gray-500 dark:border-white/10">
                    No cities yet — search above to add one.
                </p>
            </template>
        </div>
    </div>

    {{-- Save + advanced raw JSON (kept for power users / bulk edits). --}}
    <form wire:submit="save" class="mt-4 space-y-4">
        <details class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-300">Advanced: edit raw JSON</summary>
            <div class="mt-3">{{ $this->form }}</div>
        </details>

        <x-filament::button type="submit">Save changes</x-filament::button>
    </form>

    <script>
        function citiesManager(initialJson, key) {
            return {
                key: key,
                cities: [],

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

                // Idempotent Google Maps loader, shared with the event/venue picker.
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
                    if (dup) return;
                    this.cities.push(city);
                    this.sync();
                },

                remove(i) {
                    this.cities.splice(i, 1);
                    this.sync();
                },

                // Mirror the list into the Livewire form state so save() persists it.
                sync() {
                    this.$wire.set('data.cities_json', JSON.stringify(this.cities, null, 2), false);
                },
            };
        }
    </script>
</x-filament-panels::page>
