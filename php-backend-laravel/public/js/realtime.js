/**
 * realtime.js — Haraan website live updates.
 *
 * Subscribes to the public "content" channel over Reverb (Pusher protocol) and,
 * on a `content.updated` signal, re-fetches the affected content so admin/control
 * changes appear without a manual refresh.
 *
 * Fully defensive: if realtime is disabled (local default), the Pusher library
 * is absent, or no config is present, this is a silent no-op — it can never break
 * the site. Pages opt in to refresh behaviour by listening for the DOM event
 * `haraan:content-updated` (detail = { domain, at }), or by tagging an element
 * with `data-realtime-domain="home"` + `data-realtime-src="/url"` to auto-refetch.
 */
(function () {
    'use strict';

    var cfg = window.HaraanRealtime || {};
    if (!cfg.enabled || !cfg.key || typeof Pusher === 'undefined') {
        return; // realtime not configured — nothing to do
    }

    var client;
    try {
        client = new Pusher(cfg.key, {
            wsHost: cfg.host,
            wsPort: cfg.port,
            wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            cluster: '',
            disableStats: true,
        });
    } catch (e) {
        return; // connection setup failed — stay silent
    }

    var channel = client.subscribe('content');

    // Debounce bursts (e.g. a branding save writes several rows).
    var timers = {};
    function schedule(domain, at) {
        clearTimeout(timers[domain]);
        timers[domain] = setTimeout(function () {
            handle(domain, at);
        }, 400);
    }

    function handle(domain, at) {
        // 1) Let any page script react.
        document.dispatchEvent(new CustomEvent('haraan:content-updated', {
            detail: { domain: domain, at: at },
        }));

        // 2) Auto-refetch opted-in widgets: <div data-realtime-domain="home"
        //    data-realtime-src="/some.json"> ... </div>
        var nodes = document.querySelectorAll('[data-realtime-domain="' + domain + '"][data-realtime-src]');
        nodes.forEach(function (node) {
            fetch(node.getAttribute('data-realtime-src'), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (html !== null && node.dataset.realtimeHtml === 'true') {
                        node.innerHTML = html;
                    }
                })
                .catch(function () { /* ignore */ });
        });
    }

    channel.bind('content.updated', function (data) {
        if (data && data.domain) {
            schedule(data.domain, data.at);
        }
    });
})();
