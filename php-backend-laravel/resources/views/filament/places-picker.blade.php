{{--
    Shared location picker for the /control + /partner event and venue forms.

    Google Places Autocomplete (search a place → auto-fills name/address/city/pin)
    over a Google Maps draggable marker for fine tuning. The two forms map
    different column names, so the caller passes a $fields map (event:
    venue/location; venue: name/address). Every entry is optional — a null skips
    that write.

    Degrades cleanly: with NO API key the search box is hidden and the map falls
    back to a free Leaflet/OpenStreetMap pin-picker (exactly what the venue form
    had before). Whatever the map can't help with, the plain text fields below it
    still accept manual entry.

    All behaviour lives inside x-data (with the heavy libs cached on window) so it
    survives Filament's wire:navigate SPA transitions — an inline <script> global
    would not re-run on a soft navigation.

    Expected @include data:
      $fields  array{lat?:string, lng?:string, name?:string, address?:string,
                     city?:string, mapLink?:string, placeId?:string}
                     — bare form-field keys (data.*)
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
        mapType: null, // 'google' | 'leaflet'
        placeAnchor: null, // {lat,lng} of the Google listing picked this session

        init() {
            // With a key: Google map + Places search. On any Google failure, fall
            // back to the free Leaflet pin-picker so the form is never mapless.
            if (this.key) {
                this.loadGoogle()
                    .then(() => { this.buildGoogleMap(); this.buildAutocomplete(); })
                    .catch(() => this.loadLeaflet().then(() => this.buildLeafletMap()));
            } else {
                this.loadLeaflet().then(() => this.buildLeafletMap());
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

        // ── Google map + marker ────────────────────────────────────────────
        buildGoogleMap() {
            const start = this.currentLatLng({ lat: 17.4239, lng: 78.4738 }); // Hyderabad default
            const hasPin = this.currentLatLng(null) !== null;

            this.map = new google.maps.Map(this.$refs.map, {
                center: start,
                zoom: hasPin ? 16 : 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                clickableIcons: false,
                gestureHandling: 'greedy',
            });
            this.marker = new google.maps.Marker({
                position: start, map: this.map, draggable: true,
            });
            this.marker.addListener('dragend', (e) => this.setCoords(e.latLng.lat(), e.latLng.lng()));
            this.map.addListener('click', (e) => {
                this.marker.setPosition(e.latLng);
                this.setCoords(e.latLng.lat(), e.latLng.lng());
            });
            this.mapType = 'google';

            // The picker lives in a wizard step that is hidden until navigated to;
            // a Google map built while hidden renders grey. Re-render + re-center
            // the first time the container actually becomes visible.
            const io = new IntersectionObserver((entries) => {
                entries.forEach((en) => {
                    if (en.isIntersecting && this.map) {
                        google.maps.event.trigger(this.map, 'resize');
                        this.map.setCenter(this.currentLatLng(start));
                    }
                });
            });
            io.observe(this.$refs.map);
        },

        // ── Leaflet map + marker (no-key / Google-failure fallback) ─────────
        buildLeafletMap() {
            const start = this.currentLatLng({ lat: 17.4239, lng: 78.4738 });
            const hasPin = this.currentLatLng(null) !== null;

            this.map = L.map(this.$refs.map).setView([start.lat, start.lng], hasPin ? 15 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            this.marker = L.marker([start.lat, start.lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => this.setCoords(e.target.getLatLng().lat, e.target.getLatLng().lng));
            this.map.on('click', (e) => { this.marker.setLatLng(e.latlng); this.setCoords(e.latlng.lat, e.latlng.lng); });
            this.mapType = 'leaflet';

            setTimeout(() => this.map.invalidateSize(), 200);
        },

        setCoords(lat, lng) {
            const rlat = Number(lat.toFixed(7));
            const rlng = Number(lng.toFixed(7));
            this.wSet('lat', rlat);
            this.wSet('lng', rlng);
            this.wSet('mapLink', 'https://www.google.com/maps/search/?api=1&query=' + rlat + ',' + rlng);

            // A stored place_id must keep pointing at the listing the pin is on.
            // Nudging the pin a few metres is fine-tuning the same place; dragging
            // it down the road is a different spot, so the stale id has to go —
            // otherwise we'd later show another business's photos on this event.
            if (this.movedAwayFromPlace(rlat, rlng)) {
                this.placeAnchor = null;
                this.wSet('placeId', null);
            }
        },
        movedAwayFromPlace(lat, lng) {
            if (! this.fields.placeId) return false;
            // Never picked a place this session: any manual move invalidates
            // whatever id an earlier edit stored.
            if (! this.placeAnchor) return true;
            const dLat = (lat - this.placeAnchor.lat) * 111320;
            const dLng = (lng - this.placeAnchor.lng) * 111320 * Math.cos(lat * Math.PI / 180);
            return Math.sqrt(dLat * dLat + dLng * dLng) > 200; // metres
        },
        recenter(lat, lng) {
            if (! this.map) return;
            if (this.mapType === 'google') {
                this.map.setCenter({ lat, lng });
                this.map.setZoom(16);
                this.marker.setPosition({ lat, lng });
            } else {
                this.map.setView([lat, lng], 16);
                this.marker.setLatLng([lat, lng]);
            }
        },

        // ── Google Places Autocomplete ─────────────────────────────────────
        buildAutocomplete() {
            const input = this.$refs.search;
            if (! input) return;
            const ac = new google.maps.places.Autocomplete(input, {
                // place_id rides along free inside the same Autocomplete session and
                // may be stored indefinitely — it's the permanent handle back to this
                // listing (photos, hours, rating) long after the pin is set.
                fields: ['geometry', 'name', 'formatted_address', 'address_components', 'url', 'place_id'],
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

                // Anchor first so setCoords() sees a zero-distance move and keeps
                // the id it's about to be given.
                this.placeAnchor = { lat, lng };
                if (place.place_id) this.wSet('placeId', place.place_id);

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
