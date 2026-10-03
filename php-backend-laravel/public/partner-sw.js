/*
 * Haraan Partner: service worker for the partner console installed as a web app
 * (an iPhone "Add to Home Screen", or a desktop browser).
 *
 * Two jobs only:
 *   1. Push. FCM delivers the same data-only messages the Android partner app gets
 *      (BookingNotifier::pushToPartner etc.). This worker turns `data.title` /
 *      `data.body` into a notification. It must show one for EVERY push: Safari
 *      revokes the subscription of a web app that receives pushes silently.
 *   2. An offline page. Page loads go to the network; only when that fails is the
 *      cached offline screen shown. Nothing else is cached (bookings must never be
 *      served stale) and Livewire/POST traffic is never touched.
 *
 * Registered with scope /partner by public/js/partner/pwa.js.
 */
'use strict';

var CACHE = 'hrn-partner-v1';
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
                    return k.indexOf('hrn-partner-') === 0 && k !== CACHE;
                }).map(function (k) { return caches.delete(k); }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
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
