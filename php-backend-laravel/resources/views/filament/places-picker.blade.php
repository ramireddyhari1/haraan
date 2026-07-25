{{--
    Shared location picker for the /control + /partner event and venue forms.

    Google Places Autocomplete (search a place → auto-fills name/address/city/pin)
    layered over a free Leaflet/OpenStreetMap map with a draggable marker for fine
    tuning. The two forms map different column names, so the caller passes a $fields
    map (event: venue/location; venue: name/address). Every entry is optional — a
    null skips that write.

    Degrades cleanly: with no API key the search box is hidden and it's just the
    Leaflet pin-picker (exactly what the venue form had before). Whatever the map
    can't help with, the plain text fields below it still accept manual entry.

    All behaviour lives inside x-data (with the heavy libs cached on window) so it
    survives Filament's wire:navigate SPA transitions — an inline <script> global
    would not re-run on a soft navigation. Mirrors the old venue-map-picker pattern.

    Expected @include data:
      $fields  array{lat?:string, lng?:string, name?:string, address?:string,
                     city?:string, mapLink?:string}  — bare form-field keys (data.*)
      $height  int   map height in px (default 300)
--}}
@php
    $mapsKey = (string) config('services.google_maps.key');
    $fields  = $fields ?? [];
    $height  = $height ?? 300;
@endphp

<div
    wire:ignore
    x-data="{
        key: @js($mapsKey),
        fields: @js($fields),
        map: null,
        marker: null,

        init() {
            this.loadLeaflet().then(() => this.buildMap());
            if (this.key) {
                this.loadGoogle().then(() => this.buildAutocomplete()).catch(() => {});
            }
        },

        // ── idempotent library loaders (promises cached on window) ──────────
        loadLeaflet() {
            return window.__haraanLeafletPromise || (window.__haraanLeafletPromise = new Promise((resolve) => {
                if (window.L) { resolve(); return; }
                if (! document.getElementById('leaflet-css')) {
                    const css = document.createElement('link');
                    css.id = 'leaflet-css'; css.rel = 'stylesheet';
                    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(css);
                }
                const s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.onload = () => resolve();
                document.body.appendChild(s);
            }));
        },
        loadGoogle() {
            if (! this.key) return Promise.reject('no-key');
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

        // ── form-state helpers (fields live under data.*) ──────────────────
        wGet(k) {
            const f = this.fields[k];
            if (! f) return null;
            const v = this.$wire.get('data.' + f);
            return (v === '' || v === null || v === undefined) ? null : v;
        },
        wSet(k, v) {
            const f = this.fields[k];
            if (f) this.$wire.set('data.' + f, v);
        },
        currentLatLng(fallback) {
            const lat = parseFloat(this.wGet('lat'));
            const lng = parseFloat(this.wGet('lng'));
            return (isNaN(lat) || isNaN(lng)) ? fallback : { lat, lng };
        },

        // ── map + marker ───────────────────────────────────────────────────
        buildMap() {
            const start = this.currentLatLng({ lat: 17.4239, lng: 78.4738 }); // Hyderabad default
            const hasPin = this.currentLatLng(null) !== null;

            this.map = L.map(this.$refs.map).setView([start.lat, start.lng], hasPin ? 15 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            this.marker = L.marker([start.lat, start.lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => this.setCoords(e.target.getLatLng().lat, e.target.getLatLng().lng));
            this.map.on('click', (e) => { this.marker.setLatLng(e.latlng); this.setCoords(e.latlng.lat, e.latlng.lng); });

            setTimeout(() => this.map.invalidateSize(), 200);
        },
        setCoords(lat, lng) {
            const rlat = Number(lat.toFixed(7));
            const rlng = Number(lng.toFixed(7));
            this.wSet('lat', rlat);
            this.wSet('lng', rlng);
            this.wSet('mapLink', 'https://www.google.com/maps/search/?api=1&query=' + rlat + ',' + rlng);
        },
        recenter(lat, lng) {
            if (this.map) { this.map.setView([lat, lng], 16); this.marker.setLatLng([lat, lng]); }
        },

        // ── Google Places Autocomplete ─────────────────────────────────────
        buildAutocomplete() {
            const input = this.$refs.search;
            if (! input) return;
            const ac = new google.maps.places.Autocomplete(input, {
                fields: ['geometry', 'name', 'formatted_address', 'address_components', 'url'],
                componentRestrictions: { country: 'in' },
            });
            ac.addListener('place_changed', () => {
                const place = ac.getPlace();
                if (! place || ! place.geometry || ! place.geometry.location) return;

                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();

                if (place.name) this.wSet('name', place.name);
                if (place.formatted_address) this.wSet('address', place.formatted_address);
                const city = this.cityFrom(place.address_components || []);
                if (city) this.wSet('city', city);

                this.setCoords(lat, lng);
                if (place.url) this.wSet('mapLink', place.url); // real place page beats a bare coords search
                this.recenter(lat, lng);
            });
        },
        cityFrom(components) {
            const pick = (type) => {
                const c = components.find((x) => (x.types || []).includes(type));
                return c ? c.long_name : null;
            };
            return pick('locality') || pick('administrative_area_level_2') || pick('administrative_area_level_1') || null;
        },
    }"
>
    @if ($mapsKey !== '')
        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">
            Search the venue or address
        </label>
        <input
            x-ref="search"
            type="text"
            placeholder="e.g. Quake Arena, Kondapur, Hyderabad"
            autocomplete="off"
            onkeydown="if(event.key==='Enter'){event.preventDefault();}"
            style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;margin-bottom:10px;"
        />
    @endif

    <div x-ref="map" style="height: {{ (int) $height }}px; border-radius: 12px; overflow: hidden; z-index: 0; background:#eef1f6;"></div>

    <p style="font-size:12px;color:#6b7280;margin-top:6px">
        @if ($mapsKey !== '')
            Pick the venue from the search box — the pin, address, city &amp; map link fill in automatically.
            Then <strong>drag the pin</strong> if you need to nudge it to the exact spot.
        @else
            Pan to the venue, then <strong>click the map or drag the pin</strong> to the exact spot. The latitude &amp; longitude fill in automatically.
        @endif
    </p>
</div>
