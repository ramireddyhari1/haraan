{{-- Real-time refresh for the /control admin panel (Reverb). Injected into every panel page
     via a HEAD_END render hook. No-op unless BROADCAST_CONNECTION=reverb — mirrors the public
     site's wiring (resources/views/site/layout.blade.php) so both share one config source.

     The bridge is INLINE (not an external file): in Filament's Livewire-driven <head> an external
     same-origin <script src> did not reliably execute, whereas an inline script placed right
     after the Pusher CDN load runs deterministically in order. --}}
@php($reverb = config('broadcasting.connections.reverb'))
<script>
    window.HaraanRealtime = {
        enabled: {{ config('broadcasting.default') === 'reverb' ? 'true' : 'false' }},
        key: @json($reverb['key'] ?? null),
        host: @json($reverb['options']['host'] ?? null),
        port: {{ (int) ($reverb['options']['port'] ?? 443) }},
        scheme: @json($reverb['options']['scheme'] ?? 'https'),
        channel: 'content',
    };
</script>
@if(config('broadcasting.default') === 'reverb')
    <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
    <script>
    /* Pusher → Livewire bridge: on a Reverb `content.updated` broadcast, dispatch the global
       `haraan-content-updated` Livewire event so dashboard widgets / live pages (which use the
       RefreshesOnContentUpdate trait) re-render and refetch. Broadcast carries only a domain
       string — nothing is trusted. Debounced so a multi-row admin save collapses to one refresh. */
    (function () {
        'use strict';
        var cfg = window.HaraanRealtime;
        if (!cfg || !cfg.enabled) { return; }
        var debounce;

        function connect() {
            try {
                var pusher = new Pusher(cfg.key, {
                    wsHost: cfg.host, wsPort: cfg.port, wssPort: cfg.port,
                    forceTLS: cfg.scheme === 'https',
                    enabledTransports: ['ws', 'wss'], disableStats: true, cluster: '',
                });
                pusher.subscribe(cfg.channel || 'content').bind('content.updated', function (data) {
                    var domain = (data && data.domain) ? String(data.domain) : null;
                    clearTimeout(debounce);
                    debounce = setTimeout(function () {
                        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                            window.Livewire.dispatch('haraan-content-updated', { domain: domain });
                        }
                        window.dispatchEvent(new CustomEvent('haraan:content-updated', { detail: { domain: domain } }));
                    }, 400);
                });
                window.__haraanRtConnected = true;
            } catch (e) {
                if (window.console) console.warn('[haraan-realtime] disabled:', e && e.message);
            }
        }

        // Script order can vary in Filament's head; wait for the Pusher global before connecting.
        if (typeof window.Pusher !== 'undefined') {
            connect();
        } else {
            var tries = 0;
            var iv = setInterval(function () {
                if (typeof window.Pusher !== 'undefined') { clearInterval(iv); connect(); }
                else if (++tries > 100) { clearInterval(iv); }
            }, 100);
        }
    })();
    </script>
@endif
