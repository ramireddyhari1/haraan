<div
    wire:ignore
    x-data="{
        map: null,
        marker: null,
        init() {
            if (window.L) { this.build(); return; }
            if (! document.getElementById('leaflet-css')) {
                const css = document.createElement('link');
                css.id = 'leaflet-css';
                css.rel = 'stylesheet';
                css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
            }
            if (! window.__leafletLoading) {
                window.__leafletLoading = true;
                const s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.onload = () => { window.__leafletReady = true; window.dispatchEvent(new Event('leaflet-ready')); };
                document.body.appendChild(s);
            }
            if (window.__leafletReady) { this.build(); }
            else { window.addEventListener('leaflet-ready', () => this.build(), { once: true }); }
        },
        coord(key, fallback) {
            const v = parseFloat(this.$wire.get('data.' + key));
            return isNaN(v) ? fallback : v;
        },
        build() {
            const lat = this.coord('latitude', 19.0760);
            const lng = this.coord('longitude', 72.8777);
            const hasPin = ! isNaN(parseFloat(this.$wire.get('data.latitude')));

            this.map = L.map(this.$refs.map).setView([lat, lng], hasPin ? 15 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => this.set(e.target.getLatLng()));
            this.map.on('click', (e) => { this.marker.setLatLng(e.latlng); this.set(e.latlng); });

            // Fix tiles that render grey until the container settles.
            setTimeout(() => this.map.invalidateSize(), 200);
        },
        set(ll) {
            this.$wire.set('data.latitude', Number(ll.lat.toFixed(7)));
            this.$wire.set('data.longitude', Number(ll.lng.toFixed(7)));
        },
    }"
>
    <div x-ref="map" style="height: 320px; border-radius: 12px; overflow: hidden; z-index: 0;"></div>
    <p style="font-size: 12px; color: #6b7280; margin-top: 6px;">
        Search isn't needed — pan to the venue, then <strong>click the map or drag the pin</strong> to the exact spot.
        The latitude &amp; longitude below fill in automatically.
    </p>
</div>
