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

    // Track whether the visitor has entered anything on this page load. If they
    // have (a form, a search, a checkout field), we must NOT auto-reload out from
    // under them — we fall back to the manual "Refresh" nudge so nothing is lost.
    var userTyped = false;
    document.addEventListener('input', function () { userTyped = true; }, true);
    document.addEventListener('change', function () { userTyped = true; }, true);

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

        // 3) Default UX: refresh the page automatically so admin/control changes
        //    appear without the visitor doing anything. Pages can opt out with
        //    <body data-realtime-toast="off"> (e.g. they refresh themselves via the
        //    DOM event / tagged widgets above).
        if (document.body.getAttribute('data-realtime-toast') === 'off') {
            return;
        }
        autoRefresh();
    }

    var reloading = false;
    function doReload() {
        if (reloading) return;
        reloading = true;
        window.location.reload();
    }

    // Is the element actually on screen? Most pages keep modal markup
    // (role="dialog" / aria-modal) in the DOM but hidden until opened, so a bare
    // selector match is not enough — we must confirm it's rendered.
    function isShown(el) {
        if (!el || el.getAttribute('aria-hidden') === 'true') return false;
        var s = window.getComputedStyle(el);
        if (s.display === 'none' || s.visibility === 'hidden') return false;
        return el.getClientRects().length > 0;
    }

    // Reloading is unsafe while the visitor is actively working in a field or an
    // open dialog — yanking the page would lose what they were doing.
    function userIsBusy() {
        var el = document.activeElement;
        if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable)) {
            return true;
        }
        if (document.querySelector('dialog[open]')) {
            return true;
        }
        // Only a genuinely visible ARIA modal counts — hidden modal markup that
        // merely sits in the DOM must not block the auto-refresh.
        var modals = document.querySelectorAll('[aria-modal="true"], [role="dialog"]');
        for (var i = 0; i < modals.length; i++) {
            if (isShown(modals[i])) return true;
        }
        return false;
    }

    function autoRefresh() {
        // Never reload a background tab (wasteful, and it may be a parked form) —
        // wait until the visitor brings it forward, then re-decide.
        if (document.visibilityState !== 'visible') {
            document.addEventListener('visibilitychange', function onVis() {
                if (document.visibilityState === 'visible') {
                    document.removeEventListener('visibilitychange', onVis);
                    autoRefresh();
                }
            });
            return;
        }

        // If the visitor has typed/selected anything, or is mid-interaction, don't
        // reload under them — offer the manual nudge instead.
        if (userTyped || userIsBusy()) {
            showToast();
            return;
        }

        doReload();
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
