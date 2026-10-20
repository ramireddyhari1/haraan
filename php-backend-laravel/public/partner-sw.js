/*
 * Haraan Partner: service worker for the partner console installed as a web app
 * (an iPhone "Add to Home Screen", or a desktop browser).
 *
 * Three jobs:
 *   1. Push. FCM delivers the same data-only messages the Android partner app gets
 *      (BookingNotifier::pushToPartner etc.). This worker turns `data.title` /
 *      `data.body` into a notification. It must show one for EVERY push: Safari
 *      revokes the subscription of a web app that receives pushes silently.
 *   2. An offline page. Page loads go to the network; only when that fails is the
 *      cached offline screen shown. Nothing else is cached (bookings must never be
 *      served stale) and Livewire/POST traffic is never touched.
 *   3. The app's own files (the fast app at /partner/app and the console's phone shell):
 *      scripts, styles, icons and fonts are kept on the phone so the app opens without
 *      downloading them again. Each URL carries its version (?v=file time), so a new
 *      release is a new URL: fetched once, and the older copy of that file is dropped.
 *      API data is never cached here.
 *
 * Registered with scope /partner by public/js/partner/pwa.js.
 */
'use strict';

var CACHE = 'hrn-partner-v1';
var ASSETS = 'hrn-partner-assets-v1';
var OFFLINE_URL = '/partner-app/offline.html';
var ICON = '/partner-app/icon-192.png';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE)
            .then(function (cache) { return cache.addAll([OFFLINE_URL, ICON]); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.filter(function (k) {
                    return k.indexOf('hrn-partner-') === 0 && k !== CACHE && k !== ASSETS;
                }).map(function (k) { return caches.delete(k); }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

/** The app's own static files, and the Inter font the screens are set in. */
function isAppFile(url) {
    if (url.origin === self.location.origin) {
        return /^\/(js\/partner|css\/partner|partner-app|images)\//.test(url.pathname);
    }
    return url.hostname === 'fonts.bunny.net';
}

/**
 * A versioned file (?v=…) never changes: cache first. An unversioned one (an icon, an
 * image) is shown from the cache and refreshed behind it, so a changed file shows up on
 * the next open. A miss is fetched and stored, and any older version of it dropped.
 */
function appFile(req) {
    return caches.open(ASSETS).then(function (cache) {
        return cache.match(req).then(function (hit) {
            var versioned = new URL(req.url).searchParams.has('v');
            if (hit && versioned) return hit;
            var network = fetch(req).then(function (res) {
                if (res && (res.ok || res.type === 'opaque')) {
                    var url = new URL(req.url);
                    cache.keys().then(function (keys) {
                        keys.forEach(function (k) {
                            var ku = new URL(k.url);
                            if (ku.origin === url.origin && ku.pathname === url.pathname && ku.search !== url.search) cache.delete(k);
                        });
                    });
                    cache.put(req, res.clone());
                }
                return res;
            });
            if (hit) { network.catch(function () { /* offline: the cached copy stands */ }); return hit; }
            return network;
        });
    });
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method === 'GET' && req.mode !== 'navigate') {
        var url = new URL(req.url);
        if (isAppFile(url)) event.respondWith(appFile(req));
        return;
    }
    if (req.method !== 'GET' || req.mode !== 'navigate') return;

    event.respondWith(
        fetch(req).catch(function () {
            return caches.match(OFFLINE_URL);
        })
    );
});

/* ---------------------------------------------------------------- push --- */

// Where tapping the notification lands. The desk is the venue's live day screen;
// it self-gates, so a staff member without bookings access is sent on to Home.
function targetFor(data) {
    switch (data.type) {
        case 'partner_booking_received':
        case 'partner_booking_cancelled':
            return '/partner/desk';
        default:
            var link = data.link || data.deep_link || '';
            return /^\/partner(\/|$|\?)/.test(link) ? link : '/partner';
    }
}

function readPayload(event) {
    if (!event.data) return { title: 'Haraan Partner', body: '', data: {} };
    try {
        var json = event.data.json();
        // FCM wraps a data-only message as { data: {...}, from, fcmMessageId, ... }.
        var data = json.data || {};
        var n = json.notification || {};
        return {
            title: data.title || n.title || 'Haraan Partner',
            body: data.body || n.body || '',
            data: data,
        };
    } catch (e) {
        return { title: 'Haraan Partner', body: event.data.text(), data: {} };
    }
}

self.addEventListener('push', function (event) {
    var p = readPayload(event);
    var data = p.data;
    // One notification per booking: a retry or a cancellation replaces it, never stacks.
    var tag = data.booking_id ? 'booking-' + data.booking_id : undefined;

    event.waitUntil(
        self.registration.showNotification(p.title, {
            body: p.body,
            icon: ICON,
            badge: ICON,
            tag: tag,
            renotify: !!tag,
            timestamp: Date.now(),
            data: { url: targetFor(data), type: data.type || '' },
        }).then(function () {
            // The red count on the Home Screen icon (iOS 16.4+, desktop Chrome/Edge).
            if (!self.navigator || !self.navigator.setAppBadge) return null;
            return self.registration.getNotifications().then(function (list) {
                return self.navigator.setAppBadge(list.length);
            });
        }).then(function () {
            return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        }).then(function (clients) {
            // An open console refreshes its numbers instead of waiting for its next poll.
            (clients || []).forEach(function (c) {
                c.postMessage({ source: 'hrn-push', type: data.type || '', bookingId: data.booking_id || '' });
            });
        }).catch(function () {})
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = new URL((event.notification.data && event.notification.data.url) || '/partner', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
            if (self.navigator && self.navigator.clearAppBadge) self.navigator.clearAppBadge().catch(function () {});

            for (var i = 0; i < clients.length; i++) {
                var c = clients[i];
                if (c.url.indexOf(self.location.origin + '/partner') === 0 && 'focus' in c) {
                    return c.focus().then(function (focused) {
                        return focused && 'navigate' in focused ? focused.navigate(url) : focused;
                    });
                }
            }
            return self.clients.openWindow ? self.clients.openWindow(url) : null;
        })
    );
});
