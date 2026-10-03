/**
 * Haraan Partner as an installed web app: the iPhone answer to the Android partner app.
 *
 *   - Registers /partner-sw.js (push + offline page) for the /partner scope.
 *   - iPhone/iPad in a browser tab: a bottom sheet showing how to Add to Home Screen.
 *     iOS has no install API, so the guide IS the install button.
 *   - Installed on iPhone, or on a desktop browser: a "Turn on booking alerts" card. The
 *     tap asks for notification permission, then registers an FCM web token with
 *     POST /partner/push/subscribe. From then on the partner gets the same booking
 *     pushes the Android app gets (BookingNotifier → FcmClient → partner-sw.js).
 *   - Android browsers get no prompts: those partners have the Android app.
 *
 * Config comes from window.HaraanPartnerPwa (PartnerPanelProvider HEAD_END hook); the
 * admin switches behind it live in /control → Platform rules → Partner web app.
 */
(function () {
    'use strict';

    var cfg = window.HaraanPartnerPwa;
    if (!cfg || window.__hrnPwa) return;
    window.__hrnPwa = true;

    var FIREBASE_SDK = 'https://www.gstatic.com/firebasejs/10.12.5/';
    var DAY = 86400000;

    var ua = navigator.userAgent || '';
    var isIOS = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
    var isAndroid = /Android/.test(ua);
    var standalone = navigator.standalone === true
        || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    var pushCapable = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    /* --------------------------------------------------------- storage --- */

    function store(key, value) {
        try {
            if (value === undefined) return window.localStorage.getItem(key);
            if (value === null) window.localStorage.removeItem(key);
            else window.localStorage.setItem(key, value);
        } catch (e) { /* private mode / blocked storage: prompts just re-show */ }
        return null;
    }

    function snoozed(key) {
        var at = parseInt(store(key) || '0', 10);
        return at > 0 && Date.now() - at < cfg.reshowDays * DAY;
    }

    /* -------------------------------------------------- service worker --- */

    var swReady = null;

    function registerWorker() {
        if (!('serviceWorker' in navigator)) return Promise.resolve(null);
        if (!swReady) {
            swReady = navigator.serviceWorker
                .register(cfg.swUrl, { scope: '/partner', updateViaCache: 'none' })
                .then(function () { return navigator.serviceWorker.ready; })
                .catch(function () { return null; });
        }
        return swReady;
    }

    // A push landed while the console is open: refresh what's on screen now.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function (e) {
            if (!e.data || e.data.source !== 'hrn-push' || document.visibilityState !== 'visible') return;
            if (window.Livewire && typeof window.Livewire.all === 'function') {
                window.Livewire.all().forEach(function (c) {
                    try { c.$wire.$refresh(); } catch (err) { /* component gone mid-navigation */ }
                });
            }
            if (navigator.clearAppBadge) navigator.clearAppBadge().catch(function () {});
        });
    }

    /* ------------------------------------------------------- push token --- */

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing && existing.dataset.loaded) return resolve();
            var s = existing || document.createElement('script');
            s.addEventListener('load', function () { s.dataset.loaded = '1'; resolve(); });
            s.addEventListener('error', reject);
            if (!existing) {
                s.src = src;
                s.async = true;
                document.head.appendChild(s);
            }
        });
    }

    function loadFirebase() {
        var app = window.firebase && window.firebase.app ? Promise.resolve() : loadScript(FIREBASE_SDK + 'firebase-app-compat.js');
        return app.then(function () {
            return window.firebase.messaging ? null : loadScript(FIREBASE_SDK + 'firebase-messaging-compat.js');
        }).then(function () {
            // Own named app: the login page's phone-auth app has no messagingSenderId.
            var name = 'hrn-partner-push';
            var existing = (window.firebase.apps || []).filter(function (a) { return a.name === name; })[0];
            return existing || window.firebase.initializeApp(cfg.firebase, name);
        });
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r;
        });
    }

    var tokenKey = 'hrn-push-token:' + cfg.userId;

    // Fetch this browser's FCM token and tell the server whose phone it is. Cheap to
    // repeat: the server call is skipped unless the token changed or a day has passed,
    // so a token that moved to another partner on a shared phone is re-claimed quickly.
    function syncToken(force) {
        return registerWorker().then(function (reg) {
            if (!reg) throw new Error('no-worker');
            return loadFirebase().then(function (app) {
                var opts = { serviceWorkerRegistration: reg };
                if (cfg.vapidKey) opts.vapidKey = cfg.vapidKey;
                return app.messaging().getToken(opts);
            });
        }).then(function (token) {
            if (!token) throw new Error('no-token');
            var saved = (store(tokenKey) || '').split('|');
            if (!force && saved[0] === token && Date.now() - parseInt(saved[1] || '0', 10) < DAY) return token;
            return post(cfg.subscribeUrl, { token: token }).then(function () {
                store(tokenKey, token + '|' + Date.now());
                return token;
            });
        });
    }

    /* --------------------------------------------------------------- UI --- */

    var ICONS = {
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
        share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M7 11H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1"/></svg>',
        add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8.5v7M8.5 12h7"/></svg>',
        bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg>',
    };

    function el(html) {
        var t = document.createElement('div');
        t.innerHTML = html.trim();
        return t.firstChild;
    }

    function mount(node) {
        document.body.appendChild(node);
        requestAnimationFrame(function () { node.classList.add('is-in'); });
        return node;
    }

    function unmount(node) {
        if (!node || !node.parentNode) return;
        node.classList.remove('is-in');
        setTimeout(function () { if (node.parentNode) node.parentNode.removeChild(node); }, 220);
    }

    function toast(text) {
        var t = mount(el('<div class="hrn-pwa-toast" role="status"></div>'));
        t.textContent = text;
        setTimeout(function () { unmount(t); }, 4200);
    }

    /* The iPhone install guide. */
    function showInstallGuide() {
        if (document.querySelector('.hrn-pwa-sheet')) return;

        var sheet = mount(el(
            '<div class="hrn-pwa-sheet" role="dialog" aria-modal="false" aria-labelledby="hrn-pwa-title">'
            + '<button type="button" class="hrn-pwa-x" aria-label="Close">' + ICONS.close + '</button>'
            + '<div class="hrn-pwa-head">'
            + '<img src="' + cfg.icon + '" alt="" class="hrn-pwa-icon" width="52" height="52">'
            + '<div><p id="hrn-pwa-title" class="hrn-pwa-title">Put Haraan Partner on your Home Screen</p>'
            + '<p class="hrn-pwa-sub">It opens full screen like an app, stays signed in, and can alert you to new bookings.</p></div>'
            + '</div>'
            + '<ol class="hrn-pwa-steps">'
            + '<li><span class="hrn-pwa-n">1</span><span>Tap <b class="hrn-pwa-key">' + ICONS.share + 'Share</b> in the browser bar. On newer iPhones it is inside the <b>•••</b> menu.</span></li>'
            + '<li><span class="hrn-pwa-n">2</span><span>Scroll down and choose <b class="hrn-pwa-key">' + ICONS.add + 'Add to Home Screen</b>, then tap <b>Add</b>.</span></li>'
            + '<li><span class="hrn-pwa-n">3</span><span>Open <b>Haraan Partner</b> from your Home Screen and turn on booking alerts.</span></li>'
            + '</ol>'
            + '<button type="button" class="hrn-pwa-later">Not now</button>'
            + '</div>'
        ));

        function close() {
            store('hrn-pwa-install-snooze', String(Date.now()));
            unmount(sheet);
        }
        sheet.querySelector('.hrn-pwa-x').addEventListener('click', close);
        sheet.querySelector('.hrn-pwa-later').addEventListener('click', close);
    }

    /* The "turn on alerts" card. */
    function showAlertsCard() {
        if (document.querySelector('.hrn-pwa-card')) return;

        var card = mount(el(
            '<div class="hrn-pwa-card" role="region" aria-label="Booking alerts">'
            + '<span class="hrn-pwa-bell">' + ICONS.bell + '</span>'
            + '<div class="hrn-pwa-card-body">'
            + '<p class="hrn-pwa-title">Booking alerts</p>'
            + '<p class="hrn-pwa-sub"></p>'
            + '<div class="hrn-pwa-actions">'
            + '<button type="button" class="hrn-pwa-later">Not now</button>'
            + '<button type="button" class="hrn-pwa-on">Turn on</button>'
            + '</div></div></div>'
        ));
        card.querySelector('.hrn-pwa-sub').textContent = cfg.pushPrompt;

        card.querySelector('.hrn-pwa-later').addEventListener('click', function () {
            store('hrn-pwa-alerts-snooze', String(Date.now()));
            unmount(card);
        });

        card.querySelector('.hrn-pwa-on').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Turning on…';

            // requestPermission must be the first thing the tap does: iOS only shows the
            // prompt from inside the user's gesture, so nothing may be awaited before it.
            Promise.resolve(Notification.requestPermission()).then(function (perm) {
                if (perm !== 'granted') {
                    unmount(card);
                    store('hrn-pwa-alerts-snooze', String(Date.now()));
                    toast(isIOS
                        ? 'Alerts are off. To turn them on: Settings → Notifications → Haraan Partner.'
                        : 'Alerts are blocked for this site. Allow notifications in the browser’s site settings.');
                    return;
                }
                return syncToken(true).then(function () {
                    unmount(card);
                    toast('Booking alerts are on for this ' + (isIOS ? 'phone' : 'browser') + '.');
                });
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = 'Try again';
            });
        });
    }

    /* ------------------------------------------------------------- run --- */

    var synced = false;

    function decide() {
        if (!cfg.signedIn) return;

        if (isIOS && !standalone) {
            if (cfg.installPrompt && !snoozed('hrn-pwa-install-snooze')) showInstallGuide();
            return;
        }

        if (!cfg.pushEnabled || !pushCapable || isAndroid) return;

        if (Notification.permission === 'granted') {
            if (!synced) {
                synced = true;
                syncToken(false).catch(function () { synced = false; });
            }
        } else if (Notification.permission === 'default' && !snoozed('hrn-pwa-alerts-snooze')) {
            showAlertsCard();
        }
    }

    function start() {
        registerWorker();
        // Let the console paint first; the prompts are not the first thing to read.
        setTimeout(decide, 1200);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();

    // Filament SPA navigation swaps <body>: put a visible prompt back if it was removed.
    document.addEventListener('livewire:navigated', function () { setTimeout(decide, 400); });
})();
