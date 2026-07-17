/*
 * Filament /control real-time refresh bridge.
 *
 * Subscribes to the same public Reverb "content" channel the app + website use, and on a
 * `content.updated` broadcast re-renders the admin's live Livewire components (dashboard
 * widgets, the Server status page, etc.) by dispatching a global Livewire event they listen
 * for. Broadcast carries only a `domain` string — components refetch their own data on render,
 * so no payload is trusted. Guarded: does nothing unless HaraanRealtime.enabled and Pusher are
 * present (i.e. BROADCAST_CONNECTION=reverb). Debounced so an admin save that touches several
 * rows (e.g. branding = many AppSetting rows) collapses into one refresh.
 */
(function () {
    'use strict';

    var cfg = window.HaraanRealtime;
    if (!cfg || !cfg.enabled) {
        return;
    }

    var pusher, debounce;

    // The Pusher CDN script and this bridge are both injected into the panel <head>; depending
    // on how Filament emits render-hook output they can execute out of order, so don't assume
    // window.Pusher exists yet — poll briefly until it does, then connect. (~10s ceiling.)
    function whenPusherReady(cb) {
        if (typeof window.Pusher !== 'undefined') {
            return cb();
        }
        var tries = 0;
        var iv = setInterval(function () {
            if (typeof window.Pusher !== 'undefined') {
                clearInterval(iv);
                cb();
            } else if (++tries > 100) {
                clearInterval(iv);
            }
        }, 100);
    }

    function connect() {
        try {
            pusher = new Pusher(cfg.key, {
                wsHost: cfg.host,
                wsPort: cfg.port,
                wssPort: cfg.port,
                forceTLS: cfg.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                cluster: '',
            });

            var channel = pusher.subscribe(cfg.channel || 'content');

            channel.bind('content.updated', function (data) {
                var domain = (data && data.domain) ? String(data.domain) : null;
                clearTimeout(debounce);
                debounce = setTimeout(function () {
                    // Global Livewire event — every mounted component with a matching
                    // #[On('haraan-content-updated')] listener re-renders (and refetches).
                    if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                        window.Livewire.dispatch('haraan-content-updated', { domain: domain });
                    }
                    // Also surface a DOM event for any non-Livewire consumer.
                    window.dispatchEvent(new CustomEvent('haraan:content-updated', { detail: { domain: domain } }));
                }, 400);
            });
        } catch (e) {
            // Never let a realtime hiccup disturb the admin panel.
            if (window.console) console.warn('[haraan-realtime] disabled:', e && e.message);
        }
    }

    whenPusherReady(connect);
})();
