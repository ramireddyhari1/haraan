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

        // 3) Default UX: a non-disruptive "refresh" nudge, unless the page has
        //    opted out (<body data-realtime-toast="off">) — e.g. because it
        //    handles updates itself via the DOM event / tagged widgets.
        if (document.body.getAttribute('data-realtime-toast') !== 'off') {
            showToast();
        }
    }

    function showToast() {
        if (document.getElementById('haraan-rt-toast')) return; // already shown

        var bar = document.createElement('div');
        bar.id = 'haraan-rt-toast';
        bar.setAttribute('role', 'status');
        bar.style.cssText = [
            'position:fixed', 'left:50%', 'bottom:24px', 'transform:translateX(-50%)',
            'z-index:9999', 'display:flex', 'align-items:center', 'gap:12px',
            'padding:10px 16px', 'border-radius:9999px',
            'background:#111827', 'color:#fff', 'font:500 14px/1.2 system-ui,sans-serif',
            'box-shadow:0 6px 24px rgba(0,0,0,.25)',
        ].join(';');

        var label = document.createElement('span');
        label.textContent = 'New updates available';

        var btn = document.createElement('button');
        btn.textContent = 'Refresh';
        btn.style.cssText = [
            'border:0', 'cursor:pointer', 'padding:6px 14px', 'border-radius:9999px',
            'background:#16a34a', 'color:#fff', 'font:600 14px/1 system-ui,sans-serif',
        ].join(';');
        btn.addEventListener('click', function () { window.location.reload(); });

        bar.appendChild(label);
        bar.appendChild(btn);
        document.body.appendChild(bar);
    }

    channel.bind('content.updated', function (data) {
        if (data && data.domain) {
            schedule(data.domain, data.at);
        }
    });
})();
