/**
 * Haraan Partner, the Android app's shell on the web (phones + the iPhone Home Screen app).
 *
 * Draws the app's chrome over the partner console on a phone: the white header, the
 * floating blue bottom bar, the navigation drawer, and Home itself in place of the
 * dashboard. Every screen reads the same /api/partner/* endpoints the Android app reads
 * (PartnerApi.kt), with the same rules for what to show, so the two can't disagree.
 *
 * Ported from: HaraanBottomBar.kt, HaraanNavIcons.kt, ui/drawer/HaraanNavigationDrawer.kt,
 * PartnerApp.kt (HomeTab, HomeTop, TodayStatus, DeskActions, TimelineRow…),
 * ui/home/DayGridCard.kt, ui/home/PaymentsCard.kt, HomeInsightsCards.kt, CustomersScreen.kt.
 *
 * Config: window.HaraanPartnerApp (App\Support\PartnerAppShell). Desktop never sees any of
 * this — public/css/partner/app-shell.css only turns it on below the phone breakpoint, and
 * the script does no work at all on a wide screen.
 */
(function () {
    'use strict';

    var cfg = window.HaraanPartnerApp;
    if (!cfg || window.__hrnAppShell) return;
    window.__hrnAppShell = true;

    var PHONE = window.matchMedia('(max-width: 767px)');
    var root = document.documentElement;

    /* ================================================================ utils === */

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /** Indian grouping: 670800 → "6,70,800". */
    function inr(v) {
        var n = Math.round(Number(v) || 0);
        var neg = n < 0;
        var s = String(Math.abs(n));
        if (s.length > 3) {
            var last3 = s.slice(-3);
            var rest = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
            s = rest + ',' + last3;
        }
        return (neg ? '-' : '') + s;
    }
    function rupees(v) { return '₹' + inr(v); }
    function hrs(v) {
        var r = Math.round((Number(v) || 0) * 10) / 10;
        return r === Math.floor(r) ? String(r) : String(r);
    }
    function hoursLabel(v) { var h = hrs(v); return h + (h === '1' ? ' hr' : ' hrs'); }
    function plural(n, word) { return n === 1 ? '1 ' + word : n + ' ' + word + 's'; }

    function initials(name) {
        var p = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!p.length) return '•';
        if (p.length === 1) return p[0].slice(0, 2).toUpperCase();
        return (p[0][0] + p[1][0]).toUpperCase();
    }

    /** "6:00 AM" / "Every day · 6:00 AM" → minutes after midnight (slotStartMinutes). */
    function slotStart(raw) {
        var m = /(\d{1,2})(?::(\d{2}))?\s*([AaPp][Mm])?/.exec(raw || '');
        if (!m) return Infinity;
        var h = parseInt(m[1], 10);
        var min = m[2] ? parseInt(m[2], 10) : 0;
        var ap = (m[3] || '').toLowerCase();
        if (ap === 'am' && h === 12) h = 0;
        if (ap === 'pm' && h !== 12) h += 12;
        return h >= 0 && h <= 23 && min >= 0 && min <= 59 ? h * 60 + min : Infinity;
    }

    /** 1080 → "6 PM", 1110 → "6:30 PM". */
    function clock(m) {
        var mm = ((m % 1440) + 1440) % 1440;
        var h = Math.floor(mm / 60);
        var min = mm % 60;
        var h12 = h % 12 === 0 ? 12 : h % 12;
        return (min === 0 ? String(h12) : h12 + ':' + (min < 10 ? '0' : '') + min) + (h < 12 ? ' AM' : ' PM');
    }
    function shortTime(t) { return String(t || '').replace(':00', '').trim(); }
    function minutesNow() { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
    function ymd(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    function store(key, value) {
        try {
            if (value === undefined) return window.localStorage.getItem(key);
            window.localStorage.setItem(key, value);
        } catch (e) { /* storage blocked: nothing here depends on it */ }
        return null;
    }

    function h(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    /* ================================================================== api === */

    var token = cfg.token;
    var refreshing = null;

    function refreshToken() {
        if (!refreshing) {
            refreshing = fetch(cfg.tokenUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (r) {
                if (!r.ok) throw new Error('token ' + r.status);
                return r.json();
            }).then(function (j) {
                token = j.token;
                return token;
            }).finally(function () { refreshing = null; });
        }
        return refreshing;
    }

    /** GET /api/partner/<path>, scoped to the console's selected branch like the app's branchParam. */
    function api(path, params, retried) {
        var q = [];
        params = params || {};
        if (params.branch !== false && cfg.branch) q.push('venue_id=' + encodeURIComponent(cfg.branch));
        Object.keys(params).forEach(function (k) {
            if (k !== 'branch' && params[k] != null) q.push(k + '=' + encodeURIComponent(params[k]));
        });
        var url = '/api/partner/' + path + (q.length ? (path.indexOf('?') >= 0 ? '&' : '?') + q.join('&') : '');
        return fetch(url, {
            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
            credentials: 'omit',
        }).then(function (r) {
            if (r.status === 401 && !retried) {
                return refreshToken().then(function () { return api(path, params, true); });
            }
            if (!r.ok) throw new Error(path + ' ' + r.status);
            return r.json();
        });
    }

    /**
     * POST/DELETE to /api/partner/<path> with a JSON body. Resolves to the parsed body;
     * a refusal rejects with an Error whose .status and .body carry the server's answer
     * (409 on check-in is an answer about the ticket, not a failure).
     */
    function apiSend(method, path, body, retried) {
        return fetch('/api/partner/' + path, {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            credentials: 'omit',
            body: body == null ? undefined : JSON.stringify(body),
        }).then(function (r) {
            if (r.status === 401 && !retried) {
                return refreshToken().then(function () { return apiSend(method, path, body, true); });
            }
            return r.text().then(function (t) {
                var j = null;
                try { j = t ? JSON.parse(t) : null; } catch (e) { /* not JSON */ }
                if (!r.ok) {
                    var err = new Error((j && (j.message || j.error)) || ('Something went wrong (' + r.status + ')'));
                    err.status = r.status;
                    err.body = j;
                    throw err;
                }
                return j;
            });
        });
    }

    function soft(p) { return p.then(function (v) { return v; }, function () { return null; }); }

    /* ================================================================ icons === */

    /** One glyph from [d, strokeWidth|'fill', alpha] parts on the app's 24-unit grid. */
    function glyph(parts, cls) {
        var body = parts.map(function (p) {
            var alpha = p[2] == null ? 1 : p[2];
            if (p[1] === 'fill') return '<path d="' + p[0] + '" fill="currentColor" fill-opacity="' + alpha + '"/>';
            return '<path d="' + p[0] + '" fill="none" stroke="currentColor" stroke-width="' + p[1] + '" stroke-opacity="' + alpha + '" stroke-linecap="round" stroke-linejoin="round"/>';
        }).join('');
        return '<svg viewBox="0 0 24 24" aria-hidden="true" class="' + (cls || '') + '">' + body + '</svg>';
    }

    // HaraanNavIcons.kt: each tab twice on the same skeleton (1.7 resting, 2.05 + fill selected).
    var HOUSE = 'M3.7 10.3 L12 3.9 L20.3 10.3 V18.5 A2 2 0 0 1 18.3 20.5 H5.7 A2 2 0 0 1 3.7 18.5 Z';
    var DOOR = 'M10 20.5 V16.4 A2 2 0 0 1 14 16.4 V20.5';
    var RECEIPT = 'M5.6 3.8 H18.4 V19.2 L15.9 17.7 L13.4 19.2 L10.9 17.7 L8.4 19.2 L5.6 17.7 Z';
    var RECEIPT_LINES = 'M8.6 8.6 H15.4 M8.6 12.4 H13.4';
    var WALLET = 'M4.2 8.4 H18.6 A1.8 1.8 0 0 1 20.4 10.2 V18.2 A1.8 1.8 0 0 1 18.6 20 H5.4 A1.8 1.8 0 0 1 3.6 18.2 V9 Z';
    var WALLET_FLAP = 'M4.2 8.4 L15 4.4 A1.4 1.4 0 0 1 16.9 5.7 V8.4';
    var WALLET_CLASP = 'M15.6 12.4 H20.4 V16 H15.6 A1.8 1.8 0 0 1 15.6 12.4 Z';
    var PIN = 'M12 21.1 C12 21.1 19 15.3 19 10.6 A7 7 0 1 0 5 10.6 C5 15.3 12 21.1 12 21.1 Z';
    var PIN_EYE = 'M9.6 10.6 A2.4 2.4 0 1 1 14.4 10.6 A2.4 2.4 0 1 1 9.6 10.6 Z';
    var CAL_BODY = 'M5.6 6.6 H18.4 A2 2 0 0 1 20.4 8.6 V18.4 A2 2 0 0 1 18.4 20.4 H5.6 A2 2 0 0 1 3.6 18.4 V8.6 A2 2 0 0 1 5.6 6.6 Z';
    var CAL_RAIL = 'M3.6 11.2 H20.4';
    var CAL_HANGERS = 'M8.2 3.6 V7.5 M15.8 3.6 V7.5';
    var CAL_TODAY = 'M7.4 14 H9.8 A0.9 0.9 0 0 1 10.7 14.9 V16.7 A0.9 0.9 0 0 1 9.8 17.6 H7.4 A0.9 0.9 0 0 1 6.5 16.7 V14.9 A0.9 0.9 0 0 1 7.4 14 Z';
    var CORNERS = 'M3.6 8.8 V6.2 A2.6 2.6 0 0 1 6.2 3.6 H8.8 M15.2 3.6 H17.8 A2.6 2.6 0 0 1 20.4 6.2 V8.8 M20.4 15.2 V17.8 A2.6 2.6 0 0 1 17.8 20.4 H15.2 M8.8 20.4 H6.2 A2.6 2.6 0 0 1 3.6 17.8 V15.2';
    var BEAM = 'M5.3 12 H18.7';
    var BEAM_SOLID = 'M6.2 11.1 H17.8 A0.9 0.9 0 0 1 17.8 12.9 H6.2 A0.9 0.9 0 0 1 6.2 11.1 Z';
    var FLAG = 'M7.2 3.6 V20.4 M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2';
    var PENNANT = 'M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2 Z';

    var NAV = {
        home: { out: [[HOUSE, 1.7], [DOOR, 1.7]], on: [[HOUSE, 2.05], [DOOR + ' Z', 'fill']] },
        sales: { out: [[RECEIPT, 1.7], [RECEIPT_LINES, 1.7]], on: [[RECEIPT, 'fill', 0.15], [RECEIPT, 2.05], [RECEIPT_LINES, 1.7]] },
        payments: { out: [[WALLET, 1.7], [WALLET_FLAP, 1.7], [WALLET_CLASP, 1.7]], on: [[WALLET, 'fill', 0.15], [WALLET, 2.05], [WALLET_FLAP, 2.05], [WALLET_CLASP, 'fill']] },
        venues: { out: [[PIN, 1.7], [PIN_EYE, 1.7]], on: [[PIN, 2.05], [PIN_EYE, 'fill']] },
        events: { out: [[CAL_BODY, 1.7], [CAL_RAIL, 1.7], [CAL_HANGERS, 1.7], [CAL_TODAY, 1.7]], on: [[CAL_BODY, 2.05], [CAL_RAIL, 2.05], [CAL_HANGERS, 2.05], [CAL_TODAY, 'fill']] },
        scan: { out: [[CORNERS, 1.7], [BEAM, 1.7]], on: [[CORNERS, 2.05], [BEAM_SOLID, 'fill']] },
        matches: { out: [[FLAG, 1.7]], on: [[PENNANT, 'fill', 0.28], [FLAG, 2.05]] },
    };

    // ui/icons/HaraanIcons.kt: the drawer's set (1.7 primary, 1.4 secondary).
    var DRAWER = {
        home: [['M3 10.5 L12 3.5 L21 10.5 M5.5 9.5 V19.5 A1.5 1.5 0 0 0 7 21 H17 A1.5 1.5 0 0 0 18.5 19.5 V9.5', 1.7], ['M9.5 21 V16 A2.5 2.5 0 0 1 14.5 16 V21', 1.7], ['M7.5 10.5 H16.5', 1.4, 0.5]],
        venues: [['M12 21.5 C12 21.5 19 15.5 19 10.5 A7 7 0 1 0 5 10.5 C5 15.5 12 21.5 12 21.5 Z', 1.7], ['M9.5 10.5 A2.5 2.5 0 1 0 14.5 10.5 A2.5 2.5 0 1 0 9.5 10.5 Z', 1.7]],
        matches: [['M6.5 3.5 V20.5', 1.7], ['M6.5 4.5 H18.5 L14.5 8.5 L18.5 12.5 H6.5 Z', 1.7], ['M6.5 4.5 H18.5 L14.5 8.5 L18.5 12.5 H6.5 Z', 'fill', 0.22], ['M6.5 17 A3.5 3.5 0 0 1 10 20.5', 1.4, 0.6], ['M4 20.5 H11', 1.7]],
        events: [['M5 5.5 H19 A1.5 1.5 0 0 1 20.5 7 V19.5 A1.5 1.5 0 0 1 19 21 H5 A1.5 1.5 0 0 1 3.5 19.5 V7 A1.5 1.5 0 0 1 5 5.5 Z', 1.7], ['M7.5 3 V6.5 M16.5 3 V6.5', 1.7], ['M3.5 10 H20.5', 1.7], ['M7.5 13.5 H11 M14 13.5 H16.5 M7.5 17 H11 M14 17 H15.5', 1.4, 0.55]],
        sales: [['M5.5 3.5 H18.5 V19.2 L16.3 17.8 L14.1 19.2 L12 17.8 L9.9 19.2 L7.7 17.8 L5.5 19.2 Z', 1.7], ['M8.5 7.5 H15.5 M8.5 11 H13.5 M8.5 14.5 H11.5', 1.4, 0.6]],
        payments: [[WALLET, 1.7], [WALLET_FLAP, 1.7], [WALLET_CLASP, 1.7]],
        scan: [['M3.5 8.5 V5.5 A2 2 0 0 1 5.5 3.5 H8.5 M15.5 3.5 H18.5 A2 2 0 0 1 20.5 5.5 V8.5 M20.5 15.5 V18.5 A2 2 0 0 1 18.5 20.5 H15.5 M8.5 20.5 H5.5 A2 2 0 0 1 3.5 18.5 V15.5', 1.7], ['M4.5 12 H19.5', 1.7], ['M6 11.2 H18 A0.8 0.8 0 0 1 18 12.8 H6 A0.8 0.8 0 0 1 6 11.2 Z', 'fill', 0.25]],
        operations: [['M3.5 4.5 V19.5 A1 1 0 0 0 4.5 20.5 H20.5', 1.7], ['M6 15.5 L10 11.5 L14 14.5 L19.5 8', 1.7], ['M16.5 8 H19.5 V11', 1.7]],
        settlement: [['M3.5 8.5 H20.5 A1.5 1.5 0 0 1 22 10 V18.5 A1.5 1.5 0 0 1 20.5 20 H3.5 A1.5 1.5 0 0 1 2 18.5 V10 A1.5 1.5 0 0 1 3.5 8.5 Z', 1.7], ['M7 5 H17 L18.5 8.5 H5.5 Z', 1.7], ['M2 14.5 H22', 1.7], ['M10.5 17 H13.5', 1.4]],
        pricing: [['M3.5 4.5 H20.5 V19.5 H3.5 Z', 1.7], ['M12 4.5 V19.5', 1.7], ['M9.5 12 A2.5 2.5 0 1 0 14.5 12 A2.5 2.5 0 1 0 9.5 12 Z', 1.4], ['M3.5 8.5 H6.5 V15.5 H3.5 M20.5 8.5 H17.5 V15.5 H20.5', 1.4, 0.5]],
        standing: [['M4 7 H15 A1.5 1.5 0 0 1 16.5 8.5 V18 A1.5 1.5 0 0 1 15 19.5 H4 A1.5 1.5 0 0 1 2.5 18 V8.5 A1.5 1.5 0 0 1 4 7 Z', 1.7], ['M2.5 11 H16.5', 1.4], ['M6.5 15.5 L8.5 17.5 L13 13', 1.7], ['M18 4.5 C20 6 21.5 8.5 21.5 11.5 C21.5 14 20.3 16.2 18.5 17.5', 1.7], ['M16.5 3 H19.5 V6', 1.7]],
        packages: [['M3.5 8.5 H18.5 A1.5 1.5 0 0 1 20 10 V19 A1.5 1.5 0 0 1 18.5 20.5 H3.5 A1.5 1.5 0 0 1 2 19 V10 A1.5 1.5 0 0 1 3.5 8.5 Z', 1.7], ['M2 12.5 H20', 1.4, 0.5], ['M5.5 16.5 H9', 1.7], ['M6 5.5 H19.5 A1.5 1.5 0 0 1 21 7 V15.5', 1.4, 0.6]],
        academy: [['M12 3 A9 9 0 1 0 21 12 A9 9 0 1 0 12 3 Z', 1.7], ['M12 6.5 A5.5 5.5 0 1 0 17.5 12 A5.5 5.5 0 1 0 12 6.5 Z', 1.4, 0.6], ['M12 10.5 A1.5 1.5 0 1 0 13.5 12 A1.5 1.5 0 1 0 12 10.5 Z', 1.7], ['M12 10.5 A1.5 1.5 0 1 0 13.5 12 A1.5 1.5 0 1 0 12 10.5 Z', 'fill']],
        customers: [['M9 5 A3 3 0 1 0 9 11 A3 3 0 1 0 9 5 Z', 1.7], ['M3 20.5 C3 16.8 5.7 14 9 14 C12.3 14 15 16.8 15 20.5', 1.7], ['M16 6.5 A2.3 2.3 0 1 0 16 11.1', 1.4, 0.7], ['M15.5 14.5 C17.5 15.1 19.5 16.8 20 20.5', 1.4, 0.7]],
        staff: [['M5.5 6.5 H18.5 A1.5 1.5 0 0 1 20 8 V20 A1.5 1.5 0 0 1 18.5 21.5 H5.5 A1.5 1.5 0 0 1 4 20 V8 A1.5 1.5 0 0 1 5.5 6.5 Z', 1.7], ['M10 6.5 V4 A1.5 1.5 0 0 1 11.5 2.5 H12.5 A1.5 1.5 0 0 1 14 4 V6.5', 1.7], ['M12 11.5 A2 2 0 1 0 12 7.5 A2 2 0 1 0 12 11.5 Z', 1.4], ['M8 18 C8 15.8 9.8 14.5 12 14.5 C14.2 14.5 16 15.8 16 18', 1.4]],
        payouts: [['M2.5 9 L12 3.5 L21.5 9', 1.7], ['M3.5 9.5 H20.5', 1.7], ['M2.5 19.5 H21.5', 1.7], ['M6 10.5 V18.5 M10 10.5 V18.5 M14 10.5 V18.5 M18 10.5 V18.5', 1.7]],
        reports: [['M5 3.5 H15 L19.5 8 V20 A1.5 1.5 0 0 1 18 21.5 H5 A1.5 1.5 0 0 1 3.5 20 V5 A1.5 1.5 0 0 1 5 3.5 Z', 1.7], ['M15 3.5 V8 H19.5', 1.7], ['M7.5 12 H15.5 M7.5 15.5 H13.5 M7.5 18.5 H10.5', 1.4, 0.6]],
        settings: [['M3.5 8 H20.5', 1.7], ['M7 5.5 H10.5 V10.5 H7 Z', 1.7], ['M7 5.5 H10.5 V10.5 H7 Z', 'fill', 0.35], ['M3.5 16 H20.5', 1.7], ['M13.5 13.5 H17 V18.5 H13.5 Z', 1.7], ['M13.5 13.5 H17 V18.5 H13.5 Z', 'fill', 0.35]],
        support: [['M4 12 A8 8 0 0 1 20 12', 1.7], ['M3 11 H5 A1.5 1.5 0 0 1 6.5 12.5 V15.5 A1.5 1.5 0 0 1 5 17 H3 A1.5 1.5 0 0 1 1.5 15.5 V12.5 A1.5 1.5 0 0 1 3 11 Z', 1.7], ['M19 11 H21 A1.5 1.5 0 0 1 22.5 12.5 V15.5 A1.5 1.5 0 0 1 21 17 H19 A1.5 1.5 0 0 1 17.5 15.5 V12.5 A1.5 1.5 0 0 1 19 11 Z', 1.7], ['M19 16 V18 A3 3 0 0 1 16 21 H12.5', 1.7], ['M10.5 20 H12.5 V22 H10.5 Z', 1.7]],
        signout: [['M10 4 H5 A1.5 1.5 0 0 0 3.5 5.5 V18.5 A1.5 1.5 0 0 0 5 20 H10', 1.7], ['M13.5 8 L18.5 12 L13.5 16 M8 12 H18', 1.7]],
        check: [['M4.5 12.5 L9.5 17.5 L19.5 6.5', 1.7]],
        outlet: [['M12 21 C12 21 18.5 15.5 18.5 10.5 A6.5 6.5 0 1 0 5.5 10.5 C5.5 15.5 12 21 12 21 Z', 1.7], ['M10 10.5 A2 2 0 1 0 14 10.5 A2 2 0 1 0 10 10.5 Z', 1.4]],
    };

    // ui/home/DoorGlyphs.kt
    var DOORS = {
        walkin: [['M9.2 11.2 A3.4 3.4 0 1 0 9.2 4.4 A3.4 3.4 0 1 0 9.2 11.2 Z', 1.8], ['M3.2 20 C3.6 16.4 6 14.4 9.2 14.4 C11.1 14.4 12.7 15.1 13.8 16.3', 1.8], ['M18.2 13.2 V19.6 M15 16.4 H21.4', 1.9], ['M9.2 11.2 A3.4 3.4 0 1 0 9.2 4.4 A3.4 3.4 0 1 0 9.2 11.2 Z', 'fill', 0.16]],
        reports: [['M6.4 3.4 H14.4 L19 8 V19.2 A1.6 1.6 0 0 1 17.4 20.8 H6.4 A1.6 1.6 0 0 1 4.8 19.2 V5 A1.6 1.6 0 0 1 6.4 3.4 Z', 1.8], ['M14.2 3.6 V8.2 H18.8', 1.5, 0.55], ['M8.6 17 V14.6 M11.9 17 V12.2 M15.2 17 V10.2', 1.9]],
        settlement: [['M3.6 9 L12 3.8 L20.4 9 Z', 1.8], ['M3.6 9 L12 3.8 L20.4 9 Z', 'fill', 0.16], ['M6.6 11.6 V17 M12 11.6 V17 M17.4 11.6 V17', 1.8], ['M3.6 20.2 H20.4', 1.9], ['M5 17.8 H19', 1.4, 0.55]],
        scan: DRAWER.scan,
        help: DRAWER.support,
    };

    // Material glyphs the app uses for small marks (24-unit paths).
    var MAT = {
        menu: 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
        bell: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z',
        chevron: 'M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z',
        chevronLeft: 'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z',
        lock: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z',
        check: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
        trophy: 'M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z',
        groups: 'M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.73 1.17-.52 2.61-.91 4.24-.91zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm1.13 1.1c-.37-.06-.74-.1-1.13-.1-.99 0-1.93.21-2.78.58A2.01 2.01 0 0 0 0 16.43V18h4.5v-1.61c0-.83.23-1.61.63-2.29zM20 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm4 3.43c0-.81-.48-1.53-1.22-1.85A6.95 6.95 0 0 0 20 14c-.39 0-.76.04-1.13.1.4.68.63 1.46.63 2.29V18H24v-1.57zM12 6c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z',
        bars: 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z',
        hub: 'M8.4 18.2c.38.5.6 1.12.6 1.8 0 1.66-1.34 3-3 3s-3-1.34-3-3 1.34-3 3-3c.44 0 .85.09 1.23.26l1.41-1.77a4.99 4.99 0 0 1-.94-5.76L6 9.6c-.45.25-.97.4-1.5.4C2.84 10 1.5 8.66 1.5 7s1.34-3 3-3 3 1.34 3 3c0 .36-.07.7-.18 1.02l1.73 1.03a4.97 4.97 0 0 1 3.74-1.94V5.39C11.06 4.95 10 3.88 10 2.5c0-1.66 1.34-3 3-3s3 1.34 3 3c0 1.38-1.06 2.45-2.25 2.89v2.72a4.97 4.97 0 0 1 3.74 1.94l1.73-1.03A3 3 0 0 1 19.5 7c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3c-.53 0-1.05-.15-1.5-.4l-1.76 1.04a4.99 4.99 0 0 1-.94 5.76l1.41 1.77c.38-.17.79-.26 1.23-.26 1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3c0-.68.22-1.3.6-1.8l-1.42-1.78a4.96 4.96 0 0 1-5.36 0z',
        trend: 'M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z',
        grid: 'M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z',
        today: 'M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z',
        phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        share: 'M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11A2.99 2.99 0 0 0 21 5c0-1.66-1.34-3-3-3s-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81A2.99 2.99 0 0 0 3 12a2.99 2.99 0 0 0 5.04 2.19l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z',
    };
    MAT.search = 'M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z';
    MAT.rupee = 'M13.66 7c-.56-1.18-1.76-2-3.16-2H6V3h12v2h-3.26c.48.58.84 1.26 1.05 2H18v2h-2.02c-.25 2.8-2.61 5-5.48 5h-.73l6.73 7h-2.77L7 14v-2h3.5c1.76 0 3.22-1.3 3.46-3H6V7h7.66z';
    function mat(name, cls) { return '<svg viewBox="0 0 24 24" aria-hidden="true" class="' + (cls || '') + '"><path fill="currentColor" d="' + MAT[name] + '"/></svg>'; }

    /* ============================================================== context === */

    var LANE = { EVENT: 'event', VENUE: 'venue', CAFE: 'cafe', BOTH: 'both' };

    function laneOf(type) {
        switch (String(type || '').toLowerCase()) {
            case 'event': case 'host': case 'organiser': case 'organizer': return LANE.EVENT;
            case 'venue': return LANE.VENUE;
            case 'cafe': case 'café': return LANE.CAFE;
            default: return LANE.BOTH;
        }
    }

    var ctx = null;
    var ctxKey = 'ha-app-ctx:' + cfg.name;

    function can(perm) {
        if (!ctx) return false;
        var p = (ctx.user && ctx.user.permissions) || [];
        return ctx.user && ctx.user.altitude === 'owner' ? true : p.indexOf(perm) >= 0;
    }
    function isDesk() { return !!(ctx && ctx.user && ctx.user.altitude === 'desk'); }
    function lane() { return laneOf(ctx && ctx.business && ctx.business.type); }
    function courtsLane() { var l = lane(); return l === LANE.VENUE || l === LANE.CAFE || l === LANE.BOTH; }

    function loadContext() {
        try {
            var cached = JSON.parse(window.sessionStorage.getItem(ctxKey) || 'null');
            if (cached) ctx = cached;
        } catch (e) { /* none */ }
        return api('context', { branch: false }).then(function (c) {
            ctx = c;
            try { window.sessionStorage.setItem(ctxKey, JSON.stringify(c)); } catch (e) { /* none */ }
            return c;
        }, function () { return ctx; });
    }

    /* ========================================================== destinations === */

    var u = cfg.urls || {};

    /** The bar, built like PartnerApp's `tabs`: lane decides the set, permissions trim it. */
    function tabs() {
        var l = lane();
        var t = ['home'];
        if (l !== LANE.VENUE) t.push('events');
        if (l !== LANE.EVENT) t.push('venues');
        t.push('sales');
        if ((l === LANE.VENUE || l === LANE.CAFE) && can('reports')) t.push('payments');
        t.push('scan');
        return t.filter(function (k) { return !!urlOf(k); });
    }

    /** The drawer's Navigation list: the bar plus what didn't fit (Matches, combined lane's Payments). */
    function navTabs() {
        var l = lane();
        var t = tabs().slice();
        if (l === LANE.VENUE || l === LANE.BOTH) {
            var at = t.indexOf('venues');
            if (at >= 0 && urlOf('matches')) t.splice(at + 1, 0, 'matches');
            if (l === LANE.BOTH && can('reports') && urlOf('payments') && t.indexOf('payments') < 0) {
                t.splice(t.indexOf('sales') + 1, 0, 'payments');
            }
        }
        return t;
    }

    function labelFor(tab) {
        var l = lane();
        if (tab === 'sales' && (l === LANE.VENUE || l === LANE.CAFE)) return 'Bookings';
        if (tab === 'venues' && l === LANE.CAFE) return 'Outlets';
        return { home: 'Home', events: 'Events', venues: 'Venues', matches: 'Matches', sales: 'Sales', payments: 'Payments', scan: 'Scan' }[tab] || tab;
    }

    /** A venue's Bookings tab is its day desk; an event host's is ticket sales. */
    function urlOf(tab) {
        if (tab === 'sales') {
            var l = lane();
            return (l === LANE.VENUE || l === LANE.CAFE) ? (u.bookings || u.sales) : (u.sales || u.bookings);
        }
        return u[tab] || null;
    }

    function path(url) { try { return new URL(url, location.origin).pathname.replace(/\/+$/, ''); } catch (e) { return ''; } }

    /** Which tab the current page belongs to: the longest matching URL wins. */
    function currentTab() {
        var here = location.pathname.replace(/\/+$/, '');
        var best = null, bestLen = -1;
        navTabs().forEach(function (k) {
            var p = path(urlOf(k));
            if (!p) return;
            var hit = k === 'home' ? here === p : (here === p || here.indexOf(p + '/') === 0);
            if (hit && p.length > bestLen) { best = k; bestLen = p.length; }
        });
        return best;
    }

    function isHome() { return path(u.home) === location.pathname.replace(/\/+$/, ''); }

    function go(url) {
        if (!url) return;
        if (window.Livewire && typeof window.Livewire.navigate === 'function') window.Livewire.navigate(url);
        else location.href = url;
    }

    /* =========================================================== bottom bar === */

    /**
     * The floating bar, built once and kept for the whole visit. It hangs off <html>, not
     * <body>, because a Livewire page swap replaces <body>; living outside it, the bar is
     * never torn down mid-move. One white thumb slides between tabs (the app's sliding
     * pill), and the icons glide to their new places with it (FLIP), so a tab change is a
     * single continuous motion rather than one pill fading out while another fades in.
     */
    var navBar = null;

    function renderNav() {
        // Livewire's Back/Forward restores a page snapshot that carries a dead copy of
        // this bar (no listeners, stale selection). Only the live one may stay.
        Array.prototype.forEach.call(document.querySelectorAll('#ha-nav'), function (n) { if (n !== navBar) n.remove(); });
        var bar = navBar;
        var keys = tabs();
        var sig = keys.join(',');
        if (!bar) {
            bar = navBar = h('<nav id="ha-nav" class="ha-nav" aria-label="Main"><div class="ha-nav-cap" role="tablist"></div></nav>');
        }
        if (bar.parentNode !== document.documentElement) document.documentElement.appendChild(bar);
        var cap = bar.firstElementChild;
        if (cap.getAttribute('data-sig') !== sig) {
            cap.setAttribute('data-sig', sig);
            cap.innerHTML = '<span class="ha-nav-thumb" aria-hidden="true"></span>' + keys.map(function (k) {
                var icon = NAV[k];
                return '<a href="' + esc(urlOf(k)) + '" role="tab" aria-selected="false" data-tab="' + k + '" class="ha-nav-slot" aria-label="' + esc(labelFor(k)) + '">'
                    + '<span class="ha-nav-in"><span class="ha-nav-glyph">' + glyph(icon.out, 'g-out') + glyph(icon.on, 'g-on') + '</span>'
                    + '<span class="ha-nav-word">' + esc(labelFor(k)) + '</span></span></a>';
            }).join('');
            if (!cap.__wired) cap.addEventListener('click', function (e) {
                var a = e.target.closest('.ha-nav-slot');
                if (!a) return;
                e.preventDefault();
                if (a.classList.contains('is-on')) return;
                if (navigator.vibrate) { try { navigator.vibrate(8); } catch (err) { /* none */ } }
                // The thumb starts moving on the tap; the page follows underneath it.
                // Until that page has arrived, the bar keeps pointing at the tab that was
                // tapped; a redraw from the page being left must not drag the thumb back.
                cap.__pending = { key: a.getAttribute('data-tab'), until: Date.now() + 10000 };
                selectTab(cap, cap.__pending.key, true);
                go(a.getAttribute('href'));
            });
            cap.__wired = true;
            cap.removeAttribute('data-on');
        }
        var want = currentTab();
        var p = cap.__pending;
        if (p) {
            if (want === p.key || Date.now() > p.until) cap.__pending = null;
            else return;
        }
        selectTab(cap, want, !!cap.getAttribute('data-on'));
    }

    function slotLeft(slot, cap) { return slot.querySelector('.ha-nav-in').getBoundingClientRect().left - cap.getBoundingClientRect().left; }

    function selectTab(cap, key, animate) {
        var slots = Array.prototype.slice.call(cap.querySelectorAll('.ha-nav-slot'));
        var thumb = cap.querySelector('.ha-nav-thumb');
        if (cap.getAttribute('data-on') === key && thumb.style.width) return;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        animate = animate && !reduce && cap.offsetWidth > 0;

        // First: where every icon sits now.
        var first = animate ? slots.map(function (s) { return slotLeft(s, cap); }) : null;

        // The layout jumps straight to the new shape...
        slots.forEach(function (s) {
            var on = s.getAttribute('data-tab') === key;
            s.classList.toggle('is-on', on);
            s.setAttribute('aria-selected', on);
            if (on) s.removeAttribute('aria-label'); else s.setAttribute('aria-label', s.textContent.trim());
        });
        cap.setAttribute('data-on', key);
        var target = slots.filter(function (s) { return s.getAttribute('data-tab') === key; })[0];

        // ...the thumb travels there...
        cap.classList.toggle('is-still', !animate);
        if (target) {
            thumb.style.width = target.offsetWidth + 'px';
            thumb.style.transform = 'translateX(' + target.offsetLeft + 'px)';
            thumb.style.opacity = '1';
        } else {
            thumb.style.opacity = '0';
        }
        if (!animate) { void cap.offsetWidth; cap.classList.remove('is-still'); return; }

        // ...and each icon is played back from where it was (FLIP), on the thumb's curve.
        slots.forEach(function (s, i) {
            var inner = s.querySelector('.ha-nav-in');
            var dx = first[i] - slotLeft(s, cap);
            inner.style.transition = 'none';
            inner.style.transform = dx ? 'translateX(' + dx + 'px)' : '';
        });
        void cap.offsetWidth;
        slots.forEach(function (s) {
            var inner = s.querySelector('.ha-nav-in');
            inner.style.transition = '';
            inner.style.transform = '';
        });
    }

    /* =============================================================== header === */

    function bellHtml() {
        return '<a class="ha-hbtn ha-bell" href="' + esc(urlOf('sales') || u.notifications || '#') + '" aria-label="New bookings">' + mat('bell') + '</a>';
    }

    /** The white bar on every tab but Home (Home carries its own top, like the app). */
    /**
     * The header every tab carries: the desktop console's floating glass bar, sized for a
     * thumb. Menu, then who is signed in (the desktop's account chip: initials with a
     * live dot, the venue, the role and branch), then the bell.
     */
    function headerHtml() {
        var v = focusVenue();
        var name = (v && v.name) || cfg.name || 'Haraan Partner';
        var where = v && (v.branch || v.location);
        var role = altitudeLabel() === 'Owner' ? (courtsLane() ? 'Venue owner' : 'Owner') : altitudeLabel();
        return '<button type="button" class="ha-hbtn" data-drawer aria-label="Menu">' + mat('menu') + '</button>'
            + '<button type="button" class="ha-acct" data-drawer aria-label="Menu">'
            + '<span class="ha-acct-av">' + esc(initials(name)) + '<i></i></span>'
            + '<span class="ha-acct-tx"><b>' + esc(name) + '</b><small>' + esc(role + (where ? ' · ' + where : '')) + '</small></span></button>'
            + bellHtml();
    }

    /** The white bar on every tab but Home (Home carries its own, like the app). */
    function renderAppBar() {
        var bar = document.getElementById('ha-appbar');
        if (!bar) {
            bar = h('<header id="ha-appbar" class="ha-appbar ha-glassbar"></header>');
            document.body.insertBefore(bar, document.body.firstChild);
        }
        bar.innerHTML = headerHtml();
    }

    var venuesCache = null;
    function focusVenue() {
        if (!venuesCache || !venuesCache.length) return null;
        return venuesCache.filter(function (x) { return x.id === cfg.branch; })[0] || venuesCache[0];
    }

    /* =============================================================== drawer === */

    function altitudeLabel() {
        var a = ctx && ctx.user && ctx.user.altitude;
        return a === 'desk' ? 'Desk' : a === 'manager' ? 'Manager' : 'Owner';
    }

    function drawerRow(icon, label, opts) {
        opts = opts || {};
        var tag = opts.href ? 'a' : 'button';
        return '<' + tag + (opts.href ? ' href="' + esc(opts.href) + '"' : ' type="' + (opts.submit ? 'submit' : 'button') + '"')
            + ' class="ha-drow' + (opts.selected ? ' is-on' : '') + (opts.danger ? ' is-danger' : '') + '"'
            + (opts.name ? ' name="' + opts.name + '" value="' + esc(opts.value) + '"' : '') + '>'
            + '<span class="ha-drow-rail"></span>'
            + '<span class="ha-drow-ic">' + glyph(DRAWER[icon] || DRAWER.home) + '</span>'
            + '<span class="ha-drow-label">' + esc(label) + '</span>'
            + (opts.trailing ? '<span class="ha-drow-trail">' + glyph(DRAWER.check) + '</span>' : '')
            + '</' + tag + '>';
    }

    function section(title, first) {
        return '<div class="ha-dsec' + (first ? ' is-first' : '') + '"><span>' + esc(title) + '</span><i></i></div>';
    }

    function renderDrawer() {
        var wrap = document.getElementById('ha-drawer');
        if (!wrap) {
            wrap = h('<div id="ha-drawer" class="ha-drawer" aria-hidden="true"><div class="ha-scrim" data-close></div><aside class="ha-sheet" role="dialog" aria-label="Menu"></aside></div>');
            document.body.appendChild(wrap);
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('[data-close]')) closeDrawer();
                else if (e.target.closest('a.ha-drow')) closeDrawer();
            });
        }
        var b = (ctx && ctx.business) || {};
        var name = b.name || cfg.name || 'Partner';
        var sub = b.type_label || (isDesk() ? 'Desk Console' : 'Sports Arena');
        var branches = (ctx && ctx.branches) || [];
        var active = branches.filter(function (x) { return x.id === cfg.branch; })[0];
        var l = lane();
        var sel = currentTab();

        var tools = [];
        if (l === LANE.VENUE || l === LANE.BOTH) tools.push(['operations', 'Operations Center']);
        if (l === LANE.VENUE || l === LANE.CAFE) tools.push(['settlement', 'Cash Settlement']);
        if (l === LANE.VENUE || l === LANE.BOTH) tools.push(['standing', 'Standing Slots']);
        if ((l === LANE.VENUE || l === LANE.BOTH) && can('pricing')) tools.push(['pricing', 'Pricing & Courts']);
        tools.push(['customers', 'Customers']);
        if (can('pricing')) tools.push(['packages', 'Packages']);
        if (can('pricing')) tools.push(['academy', 'Academy']);
        if (!isDesk()) tools.push(['staff', 'Staff']);
        if (can('reports')) tools.push(['payouts', 'Payouts']);
        if (can('reports')) tools.push(['reports', 'Reports']);
        tools = tools.filter(function (t) { return !!u[t[0]]; });

        var html = '<div class="ha-dhead"><div class="ha-dcard">'
            + '<div class="ha-drow1"><img src="' + esc(cfg.logoWhite) + '" alt="Haraan" class="ha-dlogo">'
            + '<span class="ha-online"><i></i>ONLINE</span></div>'
            + '<div class="ha-drow2"><span class="ha-mono">' + esc(name.trim().charAt(0).toUpperCase()) + '</span>'
            + '<span class="ha-dname"><b>' + esc(name) + '</b><small>' + esc(sub) + '</small></span></div>'
            + '<div class="ha-drow3"><span class="ha-role">' + esc(altitudeLabel().toUpperCase()) + '</span>'
            + (active ? '<span class="ha-branchtag">' + esc(active.branch || active.name) + '</span>' : '') + '</div>'
            + '</div></div>'
            + '<div class="ha-dbody">' + section('Navigation', true)
            + navTabs().map(function (k) { return drawerRow(k, labelFor(k), { href: urlOf(k), selected: k === sel }); }).join('');

        if (tools.length) {
            html += section('Management') + tools.map(function (t) { return drawerRow(t[0], t[1], { href: u[t[0]] }); }).join('');
        }

        if (branches.length > 1 && cfg.branchUrl) {
            html += section('Outlets') + '<form method="POST" action="' + esc(cfg.branchUrl) + '"><input type="hidden" name="_token" value="' + esc(cfg.csrf) + '">'
                + drawerRow('outlet', 'All Outlets', { submit: true, name: 'venue_id', value: '', selected: cfg.branch == null, trailing: cfg.branch == null })
                + branches.map(function (br) {
                    var on = cfg.branch === br.id;
                    return drawerRow('outlet', br.branch || br.name, { submit: true, name: 'venue_id', value: br.id, selected: on, trailing: on });
                }).join('') + '</form>';
        }

        html += '</div><div class="ha-dfoot">'
            + (u.settings ? drawerRow('settings', 'Settings', { href: u.settings }) : '')
            + (u.support ? drawerRow('support', 'Help & Support', { href: u.support }) : '')
            + (cfg.logout ? '<form method="POST" action="' + esc(cfg.logout) + '" class="ha-dout"><input type="hidden" name="_token" value="' + esc(cfg.csrf) + '">'
                + drawerRow('signout', 'Sign Out', { submit: true, danger: true }) + '</form>' : '')
            + '</div>';
        wrap.querySelector('.ha-sheet').innerHTML = html;
    }

    function openDrawer() {
        var d = document.getElementById('ha-drawer');
        if (!d) return;
        renderDrawer();
        d.classList.add('is-open');
        d.setAttribute('aria-hidden', 'false');
        root.classList.add('ha-drawer-open');
    }
    function closeDrawer() {
        var d = document.getElementById('ha-drawer');
        if (!d) return;
        d.classList.remove('is-open');
        d.setAttribute('aria-hidden', 'true');
        root.classList.remove('ha-drawer-open');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-drawer]')) { e.preventDefault(); openDrawer(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); closeSheet(); } });

    /* ================================================================= home === */

    var home = { data: null, insights: null, loading: false, timer: null, state: {} };

    /** One hour of the day, court by court (courtHours in PartnerApp.kt). */
    function courtHours(grid) {
        if (!grid || !grid.slots) return [];
        var byId = {};
        (grid.courts || []).forEach(function (c) { byId[c.id] = c; });
        return grid.slots.map(function (s) {
            var cells = (s.courts || []).filter(function (c) { return c.allowed; });
            var states;
            if (cells.length) {
                states = cells.map(function (c) { return c.is_booked ? 'booked' : c.is_held ? 'held' : 'open'; });
            } else {
                var cap = Math.max(1, s.capacity || 0);
                states = [];
                for (var i = 0; i < cap; i++) states.push(i < Math.min(s.booked || 0, cap) ? 'booked' : 'open');
            }
            var raw = s.time || s.label;
            return {
                time: String(raw || '').trim(),
                start: slotStart(raw),
                cells: states,
                price: cells.length ? Math.min.apply(null, cells.map(function (c) { return c.price; })) : s.price,
                courts: cells.map(function (c) { var col = byId[c.court_id]; return { name: col ? col.name : 'Court' }; }),
                cellPrices: cells.map(function (c) { return c.price; }),
                bookings: cells.map(function (c) {
                    var b = (c.bookings || [])[0];
                    if (!b) return null;
                    var ch = String(b.channel || '').toLowerCase();
                    return { id: b.id, customer: b.customer || 'Guest', phone: b.phone, amount: b.amount || 0, paid: b.amount_paid || 0, walkIn: ch === 'offline' || ch === 'walk-in' || ch === 'desk' };
                }),
                total: states.length,
                free: states.filter(function (x) { return x === 'open'; }).length,
            };
        }).filter(function (hh) { return hh.cells.length; });
    }

    function slotLength(hours) {
        var starts = (hours || []).map(function (x) { return x.start; }).filter(isFinite)
            .filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
        var gaps = [];
        for (var i = 1; i < starts.length; i++) if (starts[i] - starts[i - 1] > 0) gaps.push(starts[i] - starts[i - 1]);
        return gaps.length ? Math.min(60, Math.max(30, Math.min.apply(null, gaps))) : 60;
    }

    /** Court-hours still ahead / already played today, off the clock (courtHoursByClock). */
    function hoursByClock(hours) {
        var len = slotLength(hours);
        var now = minutesNow();
        var played = 0, total = 0;
        hours.forEach(function (x) { total += x.total; if (isFinite(x.start) && now >= x.start + len) played += x.total; });
        return [total - played, played];
    }

    function homeHost() {
        var host = document.getElementById('ha-home');
        if (!host) {
            var main = document.querySelector('.fi-main') || document.querySelector('main');
            if (!main) return null;
            host = h('<div id="ha-home" class="ha-home" aria-live="polite"></div>');
            main.insertBefore(host, main.firstChild);
        }
        return host;
    }

    function loadHome(quiet) {
        if (home.loading) return;
        var host = homeHost();
        if (!host) return;
        home.loading = true;
        if (!quiet && !home.data) host.innerHTML = skeleton();

        Promise.all([soft(api('venues', { branch: false })), soft(loadContext())]).then(function (first) {
            var venues = first[0] && first[0].data ? first[0].data : (first[0] || []);
            venuesCache = venues;
            var focus = venues.filter(function (v) { return v.id === cfg.branch; })[0] || venues[0] || null;
            var cl = courtsLane();
            var today = new Date();
            var tomorrow = new Date(Date.now() + 86400000);
            return Promise.all([
                api('overview'),
                api('today'),
                focus && cl ? soft(api('venues/' + focus.id + '/day', { branch: false, date: ymd(today) })) : null,
                can('reports') ? soft(api('payouts', { branch: false })) : null,
                cl ? soft(api('bookings')) : null,
                focus && cl ? soft(api('venues/' + focus.id + '/day', { branch: false, date: ymd(tomorrow) })) : null,
                cl ? soft(api('insights')) : null,
            ]).then(function (r) {
                home.data = {
                    venues: venues, focus: focus, overview: r[0], day: r[1].data || r[1], grid: r[2],
                    payouts: r[3], recent: r[4] ? (r[4].data || r[4]) : null, tomorrowGrid: r[5],
                    insights: r[6] ? (r[6].data || r[6]) : null,
                };
                home.insights = home.data.insights;
                renderHome();
                renderAppBar();
                renderCompact();
            });
        }).catch(function () {
            if (!home.data) {
                host.innerHTML = '<div class="ha-fail"><b>Couldn’t load Home</b><span>Check your connection and try again.</span>'
                    + '<button type="button" class="ha-cta" data-retry>Try again</button></div>';
                host.querySelector('[data-retry]').addEventListener('click', function () { loadHome(); });
            }
        }).finally(function () { home.loading = false; });
    }

    function skeleton() {
        return '<div class="ha-skel"><i style="width:60%"></i><i style="width:40%"></i><b></b><b class="s2"></b><b class="s3"></b></div>';
    }

    var riseOrder = 0;
    function rise(html) { var o = riseOrder++; return html.replace(/^<(\w+)/, '<$1 style="--rise:' + Math.min(o, 12) + '"'); }

    function renderHome() {
        var host = homeHost();
        var d = home.data;
        if (!host || !d) return;
        riseOrder = 0;
        var day = d.day;
        var o = d.overview;
        var cl = courtsLane();
        var settingUp = cl && !(day.setup ? day.setup.has_slots : (day.capacity && day.capacity.total > 0));
        var todayHours = d.grid ? courtHours(d.grid) : [];
        var tomorrowHours = d.tomorrowGrid ? courtHours(d.tomorrowGrid) : [];
        var len = slotLength(todayHours.length ? todayHours : tomorrowHours);
        var ins = home.insights && home.insights.enabled && !settingUp ? home.insights : null;

        var out = '';
        // ---- HomeTop
        // Home's header is the plain bar (menu · venue · bell), the one the user preferred;
        // the old two-line top with the avatar is gone.
        out += '<section class="ha-top" style="--rise:0"><div class="ha-topbody">'
            + (settingUp ? setupCard(d) : todayStatus(d, todayHours, tomorrowHours, len))
            + '</div></section>';

        // ---- Desk doors
        out += rise(doorsCard(d, todayHours));

        // ---- Payments
        if (d.recent) out += rise(paymentsCard(d, todayHours));

        if (day.chase && day.chase.count > 0) {
            out += rise('<a class="ha-chase" href="' + esc(urlOf('sales')) + '"><i></i><span>' + day.chase.count + ' unpaid · ' + rupees(day.chase.amount) + ' to collect</span>' + mat('chevron') + '</a>');
        }
        if (day.closed && day.closed.length) {
            var names = day.closed.map(function (c) { return c.name || c; });
            out += rise('<div class="ha-closed"><i></i><span>' + esc(names.join(' · ')) + (names.length === 1 ? ' is closed today' : ' are closed today') + '</span></div>');
        }

        // ---- Later today (the first booking already sits on the top card)
        if (!settingUp && day.next && day.next.length > 1) {
            out += rise(sectionHead('today', 'Later today', 'All bookings', urlOf('sales')));
            var later = day.next.slice(1);
            out += rise('<div class="ha-timeline">' + later.map(function (b, i) { return timelineRow(b, i === 0, i === later.length - 1); }).join('') + '</div>');
        }

        // ---- Insights
        if (ins) out += insightsHtml(ins, settingUp);

        host.innerHTML = out;
        wireHome(host);
        countUps(host);
        placeSegs(host);
        // A payment that arrived since the last draw lands with the app's money buzz.
        if (home.payFresh && document.visibilityState === 'visible' && navigator.vibrate) {
            try { navigator.vibrate([12, 60, 22]); } catch (err) { /* none */ }
        }
        home.payFresh = 0;
    }

    function sectionHead(icon, title, action, href) {
        return '<div class="ha-shead"><span class="ha-shead-ic">' + mat(icon) + '</span><b>' + esc(title) + '</b>'
            + (action && href ? '<a href="' + esc(href) + '">' + esc(action) + mat('chevron') + '</a>' : '') + '</div>';
    }

    /* ---- TodayStatus -------------------------------------------------------- */

    function todayStatus(d, todayHours, tomorrowHours, len) {
        var day = d.day;
        var cap = day.capacity || {};
        var money = day.money || {};
        var clockH = todayHours.length ? hoursByClock(todayHours) : null;
        var left = clockH ? clockH[0] : Math.max(0, (cap.total || 0) - (cap.done || 0));
        var over = (cap.total || 0) > 0 && left === 0;
        var next = (day.next || [])[0];
        var running = next && next.running;
        var headline = running ? 'On court now: ' + (next.court || next.venue)
            : over ? 'Today’s hours are over.'
            : cap.booked > 0 ? cap.booked + ' of ' + cap.total + ' court-hours booked'
            : (cap.total || 0) === 0 ? 'No courts running today.'
            : 'Nothing booked yet today.';
        var sub = money.expected > 0 && over ? '<span data-count="' + money.expected + '">' + rupees(money.expected) + '</span> earned · ' + rupees(money.collected) + ' collected'
            : money.expected > 0 ? '<span data-count="' + money.expected + '">' + rupees(money.expected) + '</span> expected · ' + rupees(money.collected) + ' in so far'
            : over ? 'No bookings came in.'
            : left === 1 ? '1 court-hour still open.'
            : left > 0 ? left + ' court-hours still open.' : '';

        var html = '<div class="ha-today"><div><h2>' + esc(headline) + '</h2>' + (sub ? '<p>' + sub + '</p>' : '') + '</div>'
            + (over ? closedGate() : '') + '</div>';
        if (money.due > 0) {
            html += '<a class="ha-due" href="' + esc(urlOf('sales')) + '"><i></i>' + rupees(money.due) + ' still to collect today' + mat('chevron') + '</a>';
        }

        var ins = home.insights;
        var tomorrowOpen = ins && ins.tomorrow;
        var tv = tomorrowOpen && tomorrowOpen.venues ? (tomorrowOpen.venues.filter(function (v) { return d.focus && v.id === d.focus.id; })[0] || (tomorrowOpen.venues.length === 1 ? tomorrowOpen.venues[0] : null)) : null;
        var card = '';
        if (!over && todayHours.length) {
            card = (next ? nextBookingCard(next) : '') + dayGridCard('Today · ' + (day.day_label || ''), todayHours, minutesNow(), len);
        } else if (next) {
            card = nextBookingCard(next);
        } else if (!over && left > 0 && d.focus) {
            card = nextCard('Still open today', left === 1 ? '1 court-hour to sell' : left + ' court-hours to sell', 'Send your booking link to regulars.', 'Share booking link',
                'Book a court with us on Haraan: ' + cfg.shareBase + '/' + d.focus.id);
        } else if (over && tomorrowHours.length) {
            card = dayGridCard('Tomorrow' + (tomorrowOpen && tomorrowOpen.label ? ' · ' + tomorrowOpen.label : ''), tomorrowHours, null, len);
        } else if (tv) {
            card = nextCard('Tomorrow' + (tomorrowOpen.label ? ' · ' + tomorrowOpen.label : ''),
                tv.closed ? 'Closed tomorrow' : tv.open_hours <= 0 ? 'Fully booked' : hrs(tv.open_hours) + ' court-hour' + (tv.open_hours === 1 ? '' : 's') + ' open',
                !tv.closed && tv.windows && tv.windows.length ? tv.windows.map(function (w) { return w.label; }).join(' · ') : null,
                !tv.closed && tv.open_hours > 0 ? 'Share on WhatsApp' : null, tv.share_text, true);
        }
        return html + (card ? '<div class="ha-nextwrap">' + card + '</div>' : '');
    }

    function closedGate() {
        // A gate pulled shut, padlock where the leaves meet (ClosedGate).
        var bars = '';
        for (var x = 12.8; x < 53.8; x += 4.8) if (Math.abs(x - 32) > 3.2) bars += '<line x1="' + x.toFixed(1) + '" y1="13.8" x2="' + x.toFixed(1) + '" y2="39.5"/>';
        return '<svg class="ha-gate" viewBox="0 0 64 46" aria-hidden="true"><g stroke="#94A3B8" stroke-width="1.8" stroke-linecap="round" fill="none">'
            + '<line x1="7.7" y1="5.5" x2="7.7" y2="42.3" stroke-width="2.5"/><line x1="56.3" y1="5.5" x2="56.3" y2="42.3" stroke-width="2.5"/>'
            + '<line x1="7.7" y1="13.8" x2="56.3" y2="13.8"/><line x1="7.7" y1="39.5" x2="56.3" y2="39.5"/>'
            + '<g stroke-width="1.4">' + bars + '</g><line x1="0" y1="42.3" x2="64" y2="42.3"/></g>'
            + '<circle cx="7.7" cy="4.6" r="2.3" fill="#94A3B8"/><circle cx="56.3" cy="4.6" r="2.3" fill="#94A3B8"/>'
            + '<path d="M29.3 25.5 A2.7 4.1 0 0 1 34.7 25.5" stroke="#2F6BFF" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
            + '<rect x="27.8" y="25.4" width="8.4" height="9.2" rx="2" fill="#2F6BFF"/></svg>';
    }

    function nextBookingCard(b) {
        return '<a class="ha-next' + (b.running ? ' is-now' : '') + '" href="' + esc(urlOf('sales')) + '">'
            + '<span class="ha-next-when"><small>' + (b.running ? 'Now' : 'Next') + '</small><b>' + esc(String(b.time || '').replace(':00', '')) + '</b></span>'
            + '<span class="ha-next-who"><b>' + esc(b.customer || 'Guest') + '</b><small>' + esc([b.court || b.venue, b.walk_in ? 'Walk-in' : 'Online'].filter(Boolean).join(' · ')) + '</small></span>'
            + '<span class="ha-next-amt"><b>' + rupees(b.amount) + '</b><small class="' + (b.paid ? 'is-paid' : 'is-due') + '">' + (b.paid ? 'Paid' : 'Unpaid') + '</small></span></a>';
    }

    function nextCard(label, title, detail, action, shareText, whatsapp) {
        return '<div class="ha-nextcard"><small>' + esc(label) + '</small><b>' + esc(title) + '</b>'
            + (detail ? '<p>' + esc(detail) + '</p>' : '')
            + (action ? '<button type="button" class="ha-cta" data-share="' + esc(shareText || '') + '"' + (whatsapp ? ' data-wa' : '') + '>' + esc(action) + '</button>' : '')
            + '</div>';
    }

    function setupCard(d) {
        var listed = d.venues && d.venues.length > 0;
        var done = (listed ? 1 : 0) + (d.overview.sales && d.overview.sales.bookings_total > 0 ? 1 : 0);
        var title = !listed ? 'Your venue isn’t on Haraan yet' : (d.focus ? d.focus.name : 'Your venue') + ' isn’t bookable yet';
        var body = !listed ? 'Haraan lists venues for their owners. Message us and we’ll put yours on the app.'
            : 'Publish your time slots and players can book your courts straight away.';
        var href = listed ? (u.venues || u.bookings) : u.support;
        return '<div class="ha-setup"><small>GET SET UP · ' + done + ' OF 3</small><h2>' + esc(title) + '</h2><p>' + esc(body) + '</p>'
            + (href ? '<a class="ha-setup-cta" href="' + esc(href) + '">' + (listed ? 'Add time slots' : 'Message Haraan') + '</a>' : '') + '</div>';
    }

    /* ---- DayGridCard --------------------------------------------------------- */

    function partOf(start) {
        if (start < 12 * 60) return start < 5 * 60 ? 'Night' : 'Morning';
        if (start < 17 * 60) return 'Afternoon';
        if (start < 21 * 60) return 'Evening';
        return 'Night';
    }

    function sky(part) {
        if (part === 'Morning') return '<svg viewBox="0 0 15 11"><line x1="0" y1="9.9" x2="15" y2="9.9" stroke="rgba(255,255,255,.7)" stroke-width="1.2" stroke-linecap="round"/><path d="M3.75 9.9 A3.75 5.5 0 0 1 11.25 9.9 Z" fill="#fff"/></svg>';
        if (part === 'Afternoon') {
            var rays = '';
            for (var k = 0; k < 8; k++) {
                var a = k * Math.PI / 4;
                rays += '<line x1="' + (7.5 + 4.6 * Math.cos(a)).toFixed(2) + '" y1="' + (5.5 + 4.6 * Math.sin(a)).toFixed(2) + '" x2="' + (7.5 + 6.2 * Math.cos(a)).toFixed(2) + '" y2="' + (5.5 + 6.2 * Math.sin(a)).toFixed(2) + '"/>';
            }
            return '<svg viewBox="0 0 15 11" overflow="visible"><circle cx="7.5" cy="5.5" r="3.5" fill="#fff"/><g stroke="#fff" stroke-width="1" stroke-linecap="round">' + rays + '</g></svg>';
        }
        if (part === 'Evening') return '<svg viewBox="0 0 15 11"><path d="M3.75 9.9 A3.75 4.95 0 0 1 11.25 9.9 Z" fill="#FDBA74"/><line x1="0" y1="9.9" x2="15" y2="9.9" stroke="rgba(255,255,255,.7)" stroke-width="1.2" stroke-linecap="round"/></svg>';
        return '<svg viewBox="0 0 15 11"><mask id="ha-moon"><rect width="15" height="11" fill="#fff"/><circle cx="10" cy="3.9" r="4.2" fill="#000"/></mask><circle cx="7.5" cy="5.5" r="4.6" fill="#fff" mask="url(#ha-moon)"/></svg>';
    }

    function nowCourts(cells, live) {
        var n = Math.max(1, cells.length);
        var W = 92, H = 58, gap = 4;
        var w = (W - gap * (n - 1)) / n;
        var out = '';
        cells.forEach(function (st, i) {
            var x = i * (w + gap);
            var playing = live && st === 'booked';
            var line = playing ? 'rgba(29,78,216,.35)' : 'rgba(255,255,255,.4)';
            out += '<rect x="' + x + '" y="0" width="' + w + '" height="' + H + '" rx="6" fill="' + (playing ? 'rgba(255,255,255,.95)' : 'rgba(255,255,255,.14)') + '"/>'
                + '<g fill="none" stroke="' + line + '" stroke-width="1"><rect x="' + (x + 4) + '" y="4" width="' + (w - 8) + '" height="' + (H - 8) + '"/>'
                + '<line x1="' + (x + 4) + '" y1="' + H / 2 + '" x2="' + (x + w - 4) + '" y2="' + H / 2 + '"/>'
                + '<circle cx="' + (x + w / 2) + '" cy="' + H / 2 + '" r="' + ((w - 8) * 0.18) + '"/></g>';
            if (playing) {
                var ix = x + w / 2, sp = (w - 8) * 0.18;
                out += '<g class="ha-players"><circle cx="' + (ix - sp) + '" cy="' + (4 + (H - 8) * 0.25) + '" r="3" fill="#1D4ED8"/>'
                    + '<circle cx="' + (ix + sp) + '" cy="' + (H - 4 - (H - 8) * 0.25) + '" r="3" fill="#1D4ED8"/>'
                    + '<circle class="ha-ball" cx="' + ix + '" cy="' + H / 2 + '" r="1.8" fill="#F59E0B"/></g>';
            }
        });
        return '<svg class="ha-nowcourts" viewBox="0 0 92 58" aria-hidden="true">' + out + '</svg>';
    }

    function nowLine(current, courts, now, hours) {
        if (now == null) return hours.length ? 'Opens ' + shortTime(hours[0].time) : 'Closed';
        if (!current) {
            var nx = hours.filter(function (x) { return isFinite(x.start) && x.start > now; })[0];
            return nx ? 'Opens ' + shortTime(nx.time) : 'Closed now';
        }
        var playing = [];
        current.cells.forEach(function (c, i) { if (c === 'booked') playing.push(i); });
        if (!playing.length && current.cells.length > 1) return current.cells.length === 2 ? 'Both courts free' : 'All courts free';
        if (!playing.length) return 'Court free';
        if (playing.length === current.cells.length) return 'All in play';
        if (playing.length === 1) return ((courts[playing[0]] || {}).name || '1 court') + ' in play';
        return playing.length + ' courts in play';
    }

    function dayGridCard(label, hours, now, len) {
        function past(x) { return now != null && isFinite(x.start) && now >= x.start + len; }
        function isNow(x) { return now != null && isFinite(x.start) && now >= x.start && now < x.start + len; }
        var ahead = hours.filter(function (x) { return !past(x); });
        var open = ahead.reduce(function (s, x) { return s + x.free; }, 0);
        var booked = hours.reduce(function (s, x) { return s + x.cells.filter(function (c) { return c === 'booked'; }).length; }, 0);
        var sellable = ahead.reduce(function (s, x) {
            return s + x.cells.reduce(function (t, c, i) { return t + (c === 'open' ? (x.cellPrices[i] != null ? x.cellPrices[i] : x.price) : 0); }, 0);
        }, 0);
        var courts = hours.reduce(function (m, x) { return x.courts.length > m.length ? x.courts : m; }, []);
        var rows = courts.length || hours.reduce(function (m, x) { return Math.max(m, x.total); }, 0);
        var current = hours.filter(isNow)[0];
        var cellsNow = current ? current.cells : Array.apply(null, Array(Math.max(1, rows))).map(function () { return 'open'; });

        // Parts of the day over their columns.
        var runs = [];
        hours.forEach(function (x) {
            var p = partOf(isFinite(x.start) ? x.start : 0);
            if (runs.length && runs[runs.length - 1][0] === p) runs[runs.length - 1][1]++;
            else runs.push([p, 1]);
        });
        var parts = '<div class="ha-dg-parts">' + runs.map(function (r) {
            return '<span style="flex:' + r[1] + '"><em>' + sky(r[0]) + (r[1] >= 3 ? '<b>' + r[0] + '</b>' : '') + '</em><i></i></span>';
        }).join('') + '</div>';

        var grid = '';
        for (var r = 0; r < rows; r++) {
            var cname = (courts[r] || {}).name || 'Court ' + (r + 1);
            grid += '<div class="ha-dg-row"><span class="ha-dg-court">' + esc(cname) + '</span><span class="ha-dg-cells">';
            hours.forEach(function (x, c) {
                var st = x.cells[r];
                var bk = x.bookings[r];
                var cls = st === 'booked' ? 'is-booked' : st === 'held' ? 'is-held' : st === 'open' ? 'is-open' : 'is-none';
                if (past(x)) cls += ' is-past';
                if (isNow(x)) cls += ' is-now';
                var tappable = (st === 'booked' && bk) || (st === 'open' && !past(x));
                grid += '<button type="button" class="ha-dg-cell ' + cls + '" style="--o:' + (c + r) + '"'
                    + (tappable ? '' : ' disabled')
                    + ' data-cell="' + r + ':' + c + '" aria-label="' + esc(cname + ' at ' + x.time + ': ' + (st === 'booked' ? 'booked by ' + (bk ? bk.customer : 'a customer') : st === 'held' ? 'someone is paying' : st === 'open' ? (past(x) ? 'played, not booked' : 'open, tap to book') : 'not sold')) + '">'
                    + (st === 'booked' && bk ? esc(initials(bk.customer)) : '') + '</button>';
            });
            grid += '</span></div>';
        }
        // Hour labels: first, last, "Now" and every third hour — but never two so close
        // their text collides (a 6 AM–10 PM day used to print "9 PM" on top of "10 PM").
        // Labels are placed by priority and anything within `gap` columns of one is dropped.
        var gap = Math.max(2, Math.ceil(hours.length * 34 / 250));
        var want = [];
        hours.forEach(function (x, i) {
            var hr = isFinite(x.start) ? Math.floor(x.start / 60) : -1;
            var pr = isNow(x) ? 0 : i === 0 ? 1 : i === hours.length - 1 ? 2 : (hr >= 0 && hr % 3 === 0 && x.start % 60 === 0) ? 3 : -1;
            if (pr >= 0) want.push([pr, i]);
        });
        var kept = {};
        want.sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; }).forEach(function (w) {
            for (var k in kept) { if (Math.abs(k - w[1]) < gap) return; }
            kept[w[1]] = true;
        });
        var marks = '<div class="ha-dg-hours">' + hours.map(function (x, i) {
            return '<span class="' + (isNow(x) ? 'is-now' : '') + '">' + (!kept[i] ? '' : isNow(x) ? 'Now' : esc(shortTime(x.time))) + '</span>';
        }).join('') + '</div>';

        home.state.gridHours = hours;
        home.state.gridCourts = courts;

        return '<div class="ha-daygrid">'
            + '<div class="ha-dg-head"><span>' + esc(label) + '</span>' + (now != null ? '<span class="ha-live"><i></i>Live</span>' : '') + '</div>'
            + '<div class="ha-dg-top"><div class="ha-dg-fig"><div class="ha-dg-big"><b>' + open + '</b><span>' + (open === 1 ? 'court-hour<br>to sell' : 'court-hours<br>to sell') + '</span></div>'
            + '<p>' + (sellable > 0 && now != null ? rupees(sellable) + ' still to earn today' : sellable > 0 ? rupees(sellable) + ' up for grabs' : booked > 0 ? booked + ' booked · sold out' : 'Nothing on sale') + '</p>'
            + '<small>' + (booked === 0 ? 'Nothing booked yet' : booked + ' already booked') + '</small></div>'
            + '<div class="ha-dg-now">' + nowCourts(cellsNow, !!current) + '<span>' + esc(nowLine(current, courts, now, hours)) + '</span></div></div>'
            + parts + '<div class="ha-dg-grid">' + grid + '</div>' + marks
            + '<div class="ha-dg-key"><i class="k-booked"></i>Booked<i class="k-open"></i>Open · tap to book</div>'
            + '</div>';
    }

    /* ---- Desk doors ---------------------------------------------------------- */

    function whatsappMark() {
        return '<svg viewBox="0 0 30 30" class="ha-wa" aria-hidden="true"><path d="M15 2.4a12.6 12.6 0 1 1 0 25.2 12.6 12.6 0 0 1 0-25.2zM7.2 22.8 2.1 28.2l8.5-1.4z" fill="#A3ADBB"/>'
            + '<g transform="translate(8.2 8.2) scale(.567)"><path fill="#fff" d="' + MAT.phone + '"/></g></svg>';
    }

    function doorsCard(d) {
        var doors = [];
        var cl = courtsLane();
        if (d.focus && cl && urlOf('sales')) doors.push(['walkin', 'Walk-in', urlOf('sales')]);
        if (can('reports') && u.reports) doors.push(['reports', 'Reports', u.reports]);
        if (d.focus && cl) doors.push(['wa', 'WhatsApp', null]);
        if (can('reports') && u.payouts) doors.push(['settlement', 'Settlement', u.payouts]);
        if (doors.length < 2 && urlOf('scan')) doors.push(['scan', 'Scan', urlOf('scan')]);
        if (doors.length < 2 && u.support) doors.push(['help', 'Help', u.support]);
        if (!doors.length) return '';
        return '<div class="ha-doors"><div class="ha-doors-row">' + doors.map(function (x) {
            if (x[0] === 'wa') {
                return '<button type="button" class="ha-door is-locked" data-locked="WhatsApp bookings are coming in the next update.">'
                    + '<span class="ha-door-tile">' + whatsappMark() + '<em>Coming soon</em><span class="ha-door-lock">' + mat('lock') + '</span></span><b>WhatsApp</b></button>';
            }
            return '<a class="ha-door" href="' + esc(x[2]) + '"><span class="ha-door-tile">' + glyph(DOORS[x[0]]) + '</span><b>' + esc(x[1]) + '</b></a>';
        }).join('') + '</div><div class="ha-doors-note" hidden>' + mat('lock') + '<span></span></div></div>';
    }

    /* ---- Payments ------------------------------------------------------------ */

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var WAYS = { upi: ['UPI', '#2563EB'], online: ['Haraan', '#60A5FA'], cash: ['Cash', '#F59E0B'], card: ['Card', '#1E3A8A'] };

    function paymentEntries(list) {
        var now = new Date();
        var yest = new Date(Date.now() - 86400000);
        function same(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
        return (list || []).filter(function (b) {
            var s = String(b.status || '').toLowerCase();
            return b.amount > 0 && !(s.indexOf('cancel') === 0 || s === 'expired' || s === 'refunded');
        }).map(function (b) {
            var at = b.created_at ? new Date(b.created_at) : null;
            if (!at || isNaN(at)) return null;
            var label = same(at, now) ? at.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }).toUpperCase()
                : same(at, yest) ? 'Yesterday' : at.getDate() + ' ' + MONTHS[at.getMonth()];
            var m = String(b.payment_method || '').toLowerCase();
            var paid = Number(b.amount_paid) || 0;
            var way = m === 'cash' ? 'cash' : (m === 'upi' || m === 'upi_qr') ? 'upi' : m === 'card' ? 'card' : m === '' ? (paid > 0 ? 'online' : null) : 'online';
            return { name: b.customer || 'Guest', amount: Number(b.amount) || 0, paid: paid, way: way, walkIn: String(b.channel || '').toLowerCase() === 'offline', when: label, today: same(at, now), at: at.getTime() };
        }).filter(Boolean).sort(function (a, b) { return b.at - a.at; });
    }

    function wayGlyph(w) {
        var c = WAYS[w][1];
        if (w === 'cash') return '<svg viewBox="0 0 18 18"><rect x=".8" y="4" width="16.4" height="10" rx="2" fill="none" stroke="' + c + '" stroke-width="1.6"/><circle cx="9" cy="9" r="2.3" fill="none" stroke="' + c + '" stroke-width="1.6"/></svg>';
        if (w === 'card') return '<svg viewBox="0 0 18 18"><rect x=".8" y="3.6" width="16.4" height="10.8" rx="2.5" fill="none" stroke="' + c + '" stroke-width="1.6"/><line x1="0" y1="6.8" x2="18" y2="6.8" stroke="' + c + '" stroke-width="2.2"/><line x1="2.7" y1="11.5" x2="7.6" y2="11.5" stroke="' + c + '" stroke-width="1.6" stroke-linecap="round"/></svg>';
        if (w === 'upi') return '<svg viewBox="0 0 18 18"><path d="M3.2 2.7 9.9 9 3.2 15.3z" fill="' + c + '" fill-opacity=".55"/><path d="M8.1 2.7 14.8 9 8.1 15.3z" fill="' + c + '"/></svg>';
        return '<svg viewBox="0 0 18 18"><g stroke="' + c + '" stroke-width="2.4" stroke-linecap="round"><line x1="4.5" y1="2.7" x2="4.5" y2="15.3"/><line x1="13.5" y1="2.7" x2="13.5" y2="15.3"/><line x1="4.5" y1="9.9" x2="13.5" y2="8.1"/></g></svg>';
    }

    /**
     * The drawn object a payment row carries: how the money actually came in. A note for
     * cash, the UPI mark on a phone, a card, a Haraan ticket — or an empty coin when
     * nothing has been paid yet. 40-unit art, 1.6 strokes, the method's own colour.
     */
    function payToken(way) {
        var c = way ? WAYS[way][1] : '#D97706';
        var o = '<svg viewBox="0 0 40 40" aria-hidden="true">';
        if (way === 'cash') {
            return o + '<rect x="9" y="9.5" width="24" height="14" rx="2" transform="rotate(-9 21 16.5)" fill="#FEF3C7" stroke="#D97706" stroke-width="1.4" stroke-opacity=".55"/>'
                + '<rect x="6.5" y="14.5" width="27" height="15.5" rx="2.2" fill="#FFFBEB" stroke="#D97706" stroke-width="1.6"/>'
                + '<circle cx="20" cy="22.2" r="4.1" fill="none" stroke="#D97706" stroke-width="1.5"/>'
                + '<path d="M18.4 20.4h3.3M18.4 22h3.3M19.2 20.4c1.9 0 1.9 3.1 0 3.1l2.3 2" fill="none" stroke="#B45309" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>'
                + '<path d="M9.6 17.6v1.6M30.4 25.4v1.6" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/></svg>';
        }
        if (way === 'upi') {
            return o + '<rect x="12" y="5.5" width="16" height="29" rx="3.4" fill="#fff" stroke="' + c + '" stroke-width="1.6"/>'
                + '<path d="M17.6 8.6h4.8" stroke="' + c + '" stroke-width="1.4" stroke-linecap="round" stroke-opacity=".5"/>'
                + '<path d="M15.6 15.4 20 20l-4.4 4.6z" fill="' + c + '" fill-opacity=".45"/><path d="M19.2 15.4 23.6 20l-4.4 4.6z" fill="' + c + '"/>'
                + '<path d="M16.5 29.6h7" stroke="' + c + '" stroke-width="1.4" stroke-linecap="round" stroke-opacity=".5"/></svg>';
        }
        if (way === 'card') {
            return o + '<rect x="5.5" y="10" width="29" height="20" rx="3" fill="#EEF2FF" stroke="' + c + '" stroke-width="1.6"/>'
                + '<path d="M5.5 15.4h29" stroke="' + c + '" stroke-width="2.4"/>'
                + '<rect x="9" y="19.4" width="6" height="4.4" rx="1" fill="none" stroke="' + c + '" stroke-width="1.3"/>'
                + '<path d="M19 25.6h6.5M27.5 25.6h3" stroke="' + c + '" stroke-width="1.5" stroke-linecap="round"/></svg>';
        }
        if (way === 'online') {
            return o + '<path d="M7 12.5h26v4.2a3.3 3.3 0 0 0 0 6.6v4.2H7v-4.2a3.3 3.3 0 0 0 0-6.6z" fill="#EFF6FF" stroke="#2563EB" stroke-width="1.6" stroke-linejoin="round"/>'
                + '<path d="M25.5 13.2v13.6" stroke="#2563EB" stroke-width="1.3" stroke-dasharray="1.6 2" stroke-opacity=".6"/>'
                + '<path d="M12.2 17v6M17.4 17v6M12.2 20h5.2" stroke="#2563EB" stroke-width="1.8" stroke-linecap="round"/></svg>';
        }
        return o + '<circle cx="20" cy="20" r="11" fill="none" stroke="' + c + '" stroke-width="1.6" stroke-dasharray="3 2.6"/>'
            + '<path d="M20 15v5.4l3.4 2" fill="none" stroke="' + c + '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    function clockLabel(mins) {
        var hh = Math.floor(mins / 60) % 24;
        return (hh % 12 || 12) + (hh < 12 ? 'a' : 'p');
    }

    /**
     * Today as one drawn line: a coin lands where each payment came in, sized by what it
     * was worth and coloured by how it was paid. The stretch already behind "now" is inked.
     * Nothing here is decoration; empty, it is still the day passing.
     */
    function dayRibbon(todays, hours, fresh) {
        var W = 300, H = 50, L = 10, R = 290, Y = 30;
        var first = hours.length ? hours[0].start : null;
        var last = hours.length ? hours[hours.length - 1].start : null;
        var from = first != null && first >= 0 ? Math.floor(first / 60) * 60 : 6 * 60;
        var to = last != null && last > from ? Math.min(24 * 60, Math.ceil((last + 60) / 60) * 60) : 23 * 60;
        todays.forEach(function (e) {
            var d = new Date(e.at), m = d.getHours() * 60 + d.getMinutes();
            if (m < from) from = Math.floor(m / 60) * 60;
            if (m > to) to = Math.min(24 * 60, Math.ceil(m / 60) * 60 + 60);
        });
        var span = Math.max(60, to - from);
        function x(m) { return L + (Math.max(from, Math.min(to, m)) - from) / span * (R - L); }
        var now = new Date(), nm = now.getHours() * 60 + now.getMinutes(), nx = x(nm);
        var step = span > 12 * 60 ? 180 : span > 6 * 60 ? 120 : 60;
        var labels = '';

        var svg = '<svg class="ha-ribbon" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">'
            + '<line x1="' + L + '" y1="' + Y + '" x2="' + R + '" y2="' + Y + '" stroke="#D5DCE7" stroke-width="1.6" stroke-dasharray="2 3.4" stroke-linecap="round"/>'
            + (nm > from ? '<line class="ha-ribbon-ink" x1="' + L + '" y1="' + Y + '" x2="' + nx.toFixed(1) + '" y2="' + Y + '" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round"/>' : '');
        for (var m = Math.ceil(from / step) * step; m <= to; m += step) {
            svg += '<line x1="' + x(m).toFixed(1) + '" y1="' + (Y + 4) + '" x2="' + x(m).toFixed(1) + '" y2="' + (Y + 7.5) + '" stroke="#B6C0D0" stroke-width="1.2"/>';
            // Labels are HTML, not SVG text: the drawing stretches to the phone's width, type must not.
            labels += '<span class="ha-ribbon-l" style="left:' + (x(m) / W * 100).toFixed(2) + '%">' + clockLabel(m) + '</span>';
        }
        if (nm > from && nm < to) {
            svg += '<line x1="' + nx.toFixed(1) + '" y1="9" x2="' + nx.toFixed(1) + '" y2="' + (Y + 7.5) + '" stroke="#0B1220" stroke-width="1.2" stroke-dasharray="2 2"/>';
            labels += '<span class="ha-ribbon-l is-now' + (nx > R - 14 ? ' is-end' : nx < L + 14 ? ' is-start' : '') + '" style="left:' + (nx / W * 100).toFixed(2) + '%">now</span>';
        }
        svg += '</svg>';

        // Coins sit in an HTML layer over the line so a finger can hit them.
        var top = todays.reduce(function (mx, e) { return Math.max(mx, e.paid); }, 0) || 1;
        var placed = [];
        var coins = todays.slice().sort(function (a, b) { return a.at - b.at; }).map(function (e) {
            var d = new Date(e.at), px = (x(d.getHours() * 60 + d.getMinutes()) / W) * 100;
            var r = 6 + Math.round(Math.sqrt(e.paid / top) * 5);
            var lift = 0;
            placed.forEach(function (p) { if (Math.abs(p.px - px) < 4.2 && p.lift === lift) lift += 1; });
            placed.push({ px: px, lift: lift });
            return '<button type="button" class="ha-coin' + (fresh[e.key] ? ' is-new' : '') + '" data-pay="' + e.key + '" style="left:' + px.toFixed(2) + '%;--r:' + r + 'px;--lift:' + lift + ';--c:' + WAYS[e.way][1] + '" aria-label="' + esc(rupees(e.paid) + ' from ' + e.name + ' at ' + e.when) + '"><i></i></button>';
        }).join('');
        return '<div class="ha-ribbon-wrap">' + svg + labels + coins + '</div>';
    }

    function payKey(e) { return String(e.at) + ':' + e.paid + ':' + e.name.length; }

    function paymentsCard(d, hours) {
        var entries = paymentEntries(d.recent);
        entries.forEach(function (e) { e.key = payKey(e); });
        var todays = entries.filter(function (e) { return e.today && e.paid > 0; });
        var received = todays.reduce(function (s, e) { return s + e.paid; }, 0);
        var byWay = {};
        todays.forEach(function (e) { if (e.way) byWay[e.way] = (byWay[e.way] || 0) + e.paid; });
        var ways = Object.keys(WAYS).filter(function (w) { return byWay[w] > 0; });
        var toBank = d.payouts && d.payouts.balance ? (d.payouts.balance.in_flight || 0) + (d.payouts.balance.available || 0) : 0;

        // What's new since the last time this card was drawn: those land with a drop.
        var seen = home.paySeen;
        var fresh = {};
        if (seen) todays.forEach(function (e) { if (!seen[e.key]) fresh[e.key] = true; });
        home.paySeen = {};
        todays.forEach(function (e) { home.paySeen[e.key] = true; });
        home.payFresh = Object.keys(fresh).length;

        var latest = todays[0];
        var sub = todays.length === 0 ? 'nothing in yet today'
            : (todays.length === 1 ? '1 payment' : todays.length + ' payments') + ' · last at ' + latest.when;

        var html = '<div class="ha-card ha-pay"><div class="ha-pay-head"><span>Received today</span>'
            + (urlOf('sales') ? '<a href="' + esc(urlOf('sales')) + '">View all' + mat('chevron') + '</a>' : '') + '</div>'
            + '<div class="ha-pay-big"><b data-count="' + received + '">' + rupees(received) + '</b><span>' + esc(sub) + '</span></div>'
            + dayRibbon(todays, hours || [], fresh);
        if (ways.length) {
            html += '<div class="ha-pay-ways">' + ways.map(function (w) {
                return '<span>' + wayGlyph(w) + '<span><small>' + WAYS[w][0] + '</small><b>' + rupees(byWay[w]) + '</b></span></span>';
            }).join('') + '</div>';
        } else {
            html += '<p class="ha-pay-hint">Each payment drops onto this line at the time it comes in.</p>';
        }

        var recent = entries.slice(0, 5);
        if (recent.length) {
            html += '<div class="ha-slip">' + recent.map(function (e, i) {
                var owed = Math.max(0, e.amount - e.paid);
                var how = e.way ? (e.way === 'online' ? 'Paid on Haraan' : WAYS[e.way][0]) : 'Not paid yet';
                var bits = [how, e.walkIn ? 'Walk-in' : null, e.when].filter(Boolean);
                var part = e.paid > 0 && owed > 0 ? Math.round(e.paid / e.amount * 100) : null;
                return '<div class="ha-slip-row' + (fresh[e.key] ? ' is-new' : '') + '" data-pay="' + e.key + '" style="--i:' + i + '">'
                    + '<span class="ha-token is-' + (e.way || 'due') + '">' + payToken(e.way) + '</span>'
                    + '<span class="ha-pay-who"><b>' + esc(e.name) + '</b><small>' + esc(bits.join(' · ')) + '</small></span>'
                    + '<span class="ha-pay-amt">' + (e.paid > 0 ? '<b>+' + rupees(e.paid) + '</b>' : '<b class="is-due">' + rupees(e.amount) + '</b>')
                    + (part != null ? '<span class="ha-part"><i style="width:' + part + '%"></i></span><small class="is-due">' + rupees(owed) + ' due</small>'
                        : owed > 0 ? '<small class="is-due">due</small>' : '<small class="is-in">' + (e.today ? 'Received' : e.when) + '</small>') + '</span></div>';
            }).join('') + '</div>';
        }
        if (toBank > 0 && can('reports') && u.payouts) {
            html += '<a class="ha-pay-bank" href="' + esc(u.payouts) + '"><svg viewBox="0 0 22 22" aria-hidden="true"><path d="M1.8 8.4 11 2.2 20.2 8.4Z" fill="#1D4ED8" fill-opacity=".15" stroke="#1D4ED8" stroke-width="1.6" stroke-linejoin="round"/><g stroke="#1D4ED8" stroke-width="1.6" stroke-linecap="round"><line x1="5.5" y1="10.6" x2="5.5" y2="17.2"/><line x1="11" y1="10.6" x2="11" y2="17.2"/><line x1="16.5" y1="10.6" x2="16.5" y2="17.2"/><line x1="1.8" y1="19.8" x2="20.2" y2="19.8" stroke-width="1.9"/></g></svg>'
                + '<span>' + rupees(toBank) + ' on its way to your bank</span>' + mat('chevron') + '</a>';
        }
        return html + '</div>';
    }

    /** A coin and its slip row light up together, so either one finds the other. */
    function lightPayment(host, key) {
        host.querySelectorAll('.ha-pay [data-pay]').forEach(function (el) {
            var on = el.getAttribute('data-pay') === key;
            el.classList.remove('is-lit');
            if (on) { void el.offsetWidth; el.classList.add('is-lit'); }
        });
        if (navigator.vibrate) { try { navigator.vibrate(6); } catch (err) { /* none */ } }
        clearTimeout(home.litTimer);
        home.litTimer = setTimeout(function () { host.querySelectorAll('.ha-pay .is-lit').forEach(function (el) { el.classList.remove('is-lit'); }); }, 1600);
    }

    /* ---- Later today ------------------------------------------------------- */

    function timelineRow(b, first, last) {
        return '<div class="ha-tl' + (first ? ' is-first' : '') + (last ? ' is-last' : '') + '">'
            + '<span class="ha-tl-time' + (b.running ? ' is-now' : '') + '">' + esc(b.time || '—') + '</span>'
            + '<span class="ha-tl-rail"><i class="' + (b.running ? 'is-live' : '') + '"></i></span>'
            + '<span class="ha-tl-card"><span class="ha-tl-main"><b>' + esc(b.customer || 'Guest') + (b.running ? '<em>ON COURT</em>' : '') + '</b>'
            + '<small>' + (b.court ? '<i>' + esc(b.court) + '</i>' : '') + esc([b.venue, b.walk_in ? 'Walk-in' : null].filter(Boolean).join(' · ')) + '</small></span>'
            + '<span class="ha-tl-amt"><b>' + rupees(b.amount) + '</b>' + (b.paid ? '' : '<em>UNPAID</em>') + '</span></span></div>';
    }

    /* ---- Insights ---------------------------------------------------------- */

    function seg(name, options, on) {
        return '<span class="ha-seg" data-seg="' + name + '"><i class="ha-seg-thumb" aria-hidden="true"></i>' + options.map(function (o) {
            return '<button type="button" data-v="' + o[0] + '" class="' + (String(o[0]) === String(on) ? 'is-on' : '') + '">' + esc(o[1]) + '</button>';
        }).join('') + '</span>';
    }

    function insightsHtml(ins, settingUp) {
        var s = home.state;
        var out = '';
        out += rise(haraanCard(ins.haraan || {}));
        if (ins.milestones) out += rise(milestonesCard(ins.milestones));
        if (ins.customers) {
            out += rise(sectionHead('groups', 'Your customers'));
            out += rise(customersCard(ins.customers, s.custMonthly !== false));
        }
        out += rise(sectionHead('bars', 'Day by day'));
        out += rise(weekCard(ins));
        out += rise(sectionHead('hub', 'Where bookings come from'));
        out += rise(channelCard(ins));
        if (ins.growth && ins.growth.points && ins.growth.points.length >= 2) {
            out += rise(sectionHead('trend', 'Games played so far'));
            out += rise(growthCard(ins.growth));
        }
        if (settingUp && ins.tomorrow && ins.tomorrow.venues && ins.tomorrow.venues.length) {
            out += rise(sectionHead('today', 'Tomorrow · ' + ins.tomorrow.label));
            out += rise(tomorrowCards(ins.tomorrow));
        }
        if (ins.heatmap && ins.heatmap.hours && ins.heatmap.hours.length) {
            out += rise(sectionHead('grid', 'Busy hours'));
            out += rise(busyCard(ins.heatmap));
        }
        return out;
    }

    function milestonesCard(m) {
        var newest = (m.reached || []).reduce(function (x, r) { return Math.max(x, r.count); }, 0);
        var key = 'ha-milestone:' + (cfg.branch || 'all');
        var seen = parseInt(store(key) || '-1', 10);
        var fresh = seen >= 0 && seen < newest;
        store(key, String(newest));
        var html = '<div class="ha-card ha-miles"><div class="ha-miles-head">' + mat('trophy') + '<b>' + (m.total === 1 ? '1 booking so far' : m.total + ' bookings so far') + '</b></div>';
        if (m.next) {
            html += '<div class="ha-miles-next"><span>' + (m.next.remaining === 1 ? '1 more booking to reach ' + m.next.count : m.next.remaining + ' more bookings to reach ' + m.next.count) + '</span><small>' + m.total + ' / ' + m.next.count + '</small></div>'
                + '<div class="ha-track"><i style="--w:' + Math.max(0.03, Math.min(1, m.next.progress || 0)) + '"></i></div>';
        }
        var chips = (m.reached || []).slice().sort(function (a, b) { return b.count - a.count; }).slice(0, 3)
            .map(function (r) { return [r.label, r.date, r.count === newest && fresh]; });
        if (m.first_online) chips.push(['First online booking', m.first_online, false]);
        if (chips.length) {
            html += '<div class="ha-miles-list">' + chips.map(function (c) {
                return '<div class="ha-reached' + (c[2] ? ' is-new' : '') + '">' + mat('check') + '<span>' + esc(c[0]) + '</span>' + (c[2] ? '<em>NEW</em>' : '') + '<small>' + esc(c[1]) + '</small></div>';
            }).join('') + '</div>';
        }
        return html + '</div>';
    }

    function weekCard(ins) {
        var s = home.state;
        var w = ins.week;
        var hoursMode = !!s.hoursMode;
        var days = w.days || [];
        if (s.weekStart !== w.start) { s.weekStart = w.start; s.selDay = days.findIndex(function (x) { return x.today; }); }
        var sel = s.selDay;
        var tot = w.totals || {};
        var lastRev = w.last ? w.last.revenue : 0;
        var delta = lastRev > 0 ? (tot.revenue - lastRev) / lastRev : null;
        var pct = delta == null ? 0 : Math.round(Math.abs(delta) * 100);
        var val = function (d) { return hoursMode ? d.booked_hours : d.revenue; };
        var lst = function (d) { return hoursMode ? d.last_booked_hours : d.last_revenue; };
        var top = days.reduce(function (m, d) { return Math.max(m, val(d), lst(d)); }, 0) || 1;

        var bars = days.map(function (d, i) {
            var fill = d.today ? 'is-today' : i === sel ? 'is-sel' : d.future ? 'is-future' : '';
            var ghost = lst(d) / top;
            var now = val(d) / top;
            return '<button type="button" class="ha-wk-day' + (i === sel || d.today ? ' is-strong' : '') + '" data-day="' + i + '">'
                + '<span class="ha-wk-col">' + (ghost > 0 ? '<i class="ha-wk-ghost" style="height:' + (Math.max(0.02, Math.min(1, ghost)) * 100) + '%"></i>' : '')
                + '<i class="ha-wk-bar ' + fill + '" style="height:' + (val(d) > 0 ? Math.max(0.03, Math.min(1, now)) * 100 : 1.5) + '%"></i></span>'
                + '<b>' + esc(String(d.label || '').charAt(0)) + '</b><small>' + d.day + '</small></button>';
        }).join('');
        var dsel = days[sel];
        var line = dsel ? dsel.label + ' ' + dsel.day + ' · ' + (hoursMode ? hrs(dsel.booked_hours) + ' of ' + hrs(dsel.total_hours) + ' hrs' : rupees(dsel.revenue) + ' · ' + hrs(dsel.booked_hours) + ' of ' + hrs(dsel.total_hours) + ' hrs')
            + ' · last week ' + (hoursMode ? hoursLabel(dsel.last_booked_hours) : rupees(dsel.last_revenue)) : 'Tap a day to see its numbers';

        return '<div class="ha-card ha-week' + (s.weekLoading ? ' is-loading' : '') + '"><div class="ha-row"><span class="ha-grow"><small class="ha-label">' + esc(w.label) + '</small>'
            + '<b class="ha-mid" data-count="' + (hoursMode ? '' : tot.revenue) + '">' + (hoursMode ? hoursLabel(tot.booked_hours) : rupees(tot.revenue)) + '</b></span>'
            + '<button type="button" class="ha-arrow" data-week="' + esc(w.prev || '') + '" aria-label="Previous week"' + (w.prev ? '' : ' disabled') + '>' + mat('chevronLeft') + '</button>'
            + '<button type="button" class="ha-arrow" data-week="' + esc(w.next || '') + '" aria-label="Next week"' + (w.next ? '' : ' disabled') + '>' + mat('chevron') + '</button></div>'
            + '<div class="ha-row ha-wk-sub"><span class="ha-grow ha-muted">' + (tot.total_hours > 0 ? hrs(tot.booked_hours) + ' of ' + hrs(tot.total_hours) + ' court-hours booked' : 'No bookable hours this week') + '</span>'
            + (delta != null && !hoursMode ? '<b class="' + (pct === 0 ? '' : delta > 0 ? 'is-up' : 'is-down') + '">' + (pct === 0 ? 'Same as last week' : (delta > 0 ? '▲ ' : '▼ ') + pct + '% vs last week') + '</b>' : '') + '</div>'
            + '<div class="ha-wk-bars">' + bars + '</div>'
            + '<div class="ha-row ha-wk-foot"><span class="ha-grow">' + esc(line) + '</span>' + seg('mode', [['money', '₹'], ['hours', 'Hrs']], hoursMode ? 'hours' : 'money') + '</div></div>';
    }

    /* ---- Through Haraan ----------------------------------------------------- */

    /** A drawn ring of how much of the venue's business came through Haraan. */
    function shareRing(share) {
        var pct = Math.max(0, Math.min(100, Math.round(share * 100)));
        return '<span class="ha-ring" style="--p:' + pct + '"><svg viewBox="0 0 80 80" aria-hidden="true">'
            + '<circle cx="40" cy="40" r="32" fill="none" stroke="#E3ECFF" stroke-width="8"/>'
            + '<circle class="ha-ring-arc" cx="40" cy="40" r="32" fill="none" stroke="#1D4ED8" stroke-width="8" stroke-linecap="round" pathLength="100" stroke-dasharray="100" transform="rotate(-90 40 40)"/>'
            + '<circle cx="40" cy="40" r="23" fill="#fff"/></svg>'
            + '<b>' + pct + '<small>%</small></b></span>';
    }

    /** Small drawn mark: a map pin with a player in it — someone found the venue. */
    function foundMark() {
        return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18.6s5.8-5 5.8-9.6A5.8 5.8 0 0 0 4.2 9c0 4.6 5.8 9.6 5.8 9.6z" fill="#E3ECFF" stroke="#1D4ED8" stroke-width="1.5" stroke-linejoin="round"/>'
            + '<circle cx="10" cy="7.4" r="1.7" fill="#1D4ED8"/><path d="M7.2 12.2c.4-1.6 1.5-2.4 2.8-2.4s2.4.8 2.8 2.4" fill="none" stroke="#1D4ED8" stroke-width="1.5" stroke-linecap="round"/></svg>';
    }

    function haraanCard(hb) {
        var all = hb.all_time || { count: 0, amount: 0 };
        var html = '<div class="ha-card ha-haraan">';
        if (!all.count) {
            return html + '<small class="ha-eyebrow">Through Haraan</small><h3>Your first online booking is next</h3><p class="ha-muted">Players near you can find and book your courts on Haraan. Share your venue link to bring them in.</p></div>';
        }
        html += '<div class="ha-hb-top"><span class="ha-grow"><small class="ha-eyebrow">Through Haraan</small>'
            + '<b class="ha-big" data-count="' + all.amount + '">' + rupees(all.amount) + '</b>'
            + '<p class="ha-muted">from ' + all.count + ' online ' + (all.count === 1 ? 'booking' : 'bookings') + ' so far</p></span>'
            + (hb.share > 0 ? '<span class="ha-ring-wrap">' + shareRing(hb.share) + '<small>of all bookings</small></span>' : '') + '</div>';

        var tm = hb.this_month || { count: 0, amount: 0 }, lm = hb.last_month || { count: 0 };
        var now = new Date();
        var thisName = MONTHS[now.getMonth()], lastName = MONTHS[(now.getMonth() + 11) % 12];
        var foot = '';
        if (tm.count > 0 || lm.count > 0) {
            var top = Math.max(tm.count, lm.count, 1);
            var bar = function (name, n, cls, note) {
                return '<span class="ha-mbar ' + cls + '"><small>' + name + '</small><span class="ha-mbar-track"><i style="--w:' + (n > 0 ? Math.max(0.04, n / top) : 0) + '"></i></span><b>' + n + (note ? '<em>' + note + '</em>' : '') + '</b></span>';
            };
            var ahead = lm.count > 0 && tm.count > lm.count;
            foot += '<div class="ha-mbars"><small class="ha-label">Online bookings</small>'
                + bar(lastName, lm.count, 'is-last', '')
                + bar(thisName, tm.count, 'is-this', tm.count > 0 ? rupees(tm.amount) : 'so far')
                + (ahead ? '<p class="ha-mbar-up">▲ Already past ' + lastName + '</p>' : '') + '</div>';
        }
        if (hb.players > 0) {
            foot += '<p class="ha-found">' + foundMark() + '<span><b>' + (hb.players === 1 ? '1 player' : hb.players + ' players') + '</b> found you on Haraan</span></p>';
        }
        return html + (foot ? '<div class="ha-hb-foot">' + foot + '</div>' : '') + '</div>';
    }

    /* ---- Your customers ----------------------------------------------------- */

    /** One drawn customer: head and shoulders. Returning ones carry a small loop. */
    function personGlyph(kind, i) {
        var fill = kind === 'back' ? '#0F766E' : kind === 'new' ? '#1E50E6' : 'none';
        var stroke = kind === 'none' ? ' stroke="#C3CCDA" stroke-width="1.3" stroke-dasharray="2.4 2"' : '';
        var g = '<span class="ha-person is-' + kind + '" style="--i:' + i + '"><svg viewBox="0 0 24 26" aria-hidden="true">'
            + '<circle cx="11" cy="8" r="4.6" fill="' + fill + '"' + stroke + '/>'
            + '<path d="M2.6 25c0-5.6 3.8-9 8.4-9s8.4 3.4 8.4 9z" fill="' + fill + '"' + stroke + '/>';
        if (kind === 'back') {
            g += '<circle cx="19" cy="6" r="4.6" fill="#fff"/><path d="M21.2 5.1a2.5 2.5 0 1 0-.2 2.6" fill="none" stroke="#0F766E" stroke-width="1.4" stroke-linecap="round"/>'
                + '<path d="M21.6 3.3v2.1h-2.1" fill="none" stroke="#0F766E" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        return g + '</svg></span>';
    }

    function crowd(fresh, back) {
        var total = fresh + back, MAX = 12;
        if (total === 0) {
            var ghosts = '';
            for (var g = 0; g < 6; g++) ghosts += personGlyph('none', g);
            return '<div class="ha-crowd is-empty">' + ghosts + '</div>';
        }
        var shown = Math.min(total, total > MAX ? MAX - 1 : MAX);
        var showBack = Math.min(back, Math.round(shown * back / total));
        if (back > 0 && showBack === 0) showBack = 1;
        var showNew = Math.min(fresh, shown - showBack);
        if (fresh > 0 && showNew === 0) { showNew = 1; showBack = shown - 1; }
        var out = '', i = 0;
        for (var b = 0; b < showBack; b++) out += personGlyph('back', i++);
        for (var n = 0; n < showNew; n++) out += personGlyph('new', i++);
        if (total > shown) out += '<span class="ha-person-more" style="--i:' + i + '">+' + (total - shown) + '</span>';
        return '<div class="ha-crowd">' + out + '</div>';
    }

    function customersCard(c, monthly) {
        var p = monthly ? c.month : c.week;
        var fresh = p.new || 0, back = p.returning || 0;
        var total = fresh + back;
        var lastLbl = monthly ? 'last month' : 'last week';
        function compare(now, before) {
            if (before === 0 && now === 0) return 'none ' + lastLbl + ' either';
            if (now > before) return '▲ up from ' + before + ' ' + lastLbl;
            if (now < before) return before + ' ' + lastLbl;
            return 'same as ' + lastLbl;
        }
        var last = p.last || { new: 0, returning: 0 };
        var href = u.customers || '#';
        function half(count, label, tone, note) {
            return '<a class="ha-half is-' + tone + '" href="' + esc(href) + '"><span class="ha-half-h">' + label + mat('chevron') + '</span>'
                + '<b>' + count + '</b><small class="' + (note.indexOf('▲') === 0 ? 'is-up' : '') + '">' + esc(note) + '</small></a>';
        }
        var period = String(p.label || '').toLowerCase();
        var html = '<div class="ha-card ha-cust"><div class="ha-row"><b class="ha-ctitle">'
            + (total === 0 ? 'Nobody ' + period + ' yet' : total + ' ' + (total === 1 ? 'customer' : 'customers') + ' ' + period)
            + '</b>' + seg('cust', [['week', 'Week'], ['month', 'Month']], monthly ? 'month' : 'week') + '</div>'
            + crowd(fresh, back)
            + (total > 0 ? '<p class="ha-crowd-key"><span class="is-back">' + back + ' came back</span><span class="is-new">' + fresh + ' first time</span></p>' : '<p class="ha-crowd-key">Each customer shows up here as they book.</p>')
            + '<div class="ha-halves">' + half(fresh, 'New', 'new', compare(fresh, last.new || 0)) + half(back, 'Returning', 'back', compare(back, last.returning || 0)) + '</div>';
        if (c.came_back > 0) {
            html += '<p class="ha-loyal"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M15.6 7.4A6 6 0 1 0 16 12" fill="none" stroke="#0F766E" stroke-width="1.7" stroke-linecap="round"/><path d="M16.4 3.8v3.9h-3.9" fill="none" stroke="#0F766E" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                + '<span><b>' + c.came_back + ' of ' + c.total + '</b> ' + (c.total === 1 ? 'customer has' : 'customers have') + ' come back more than once</span></p>';
        }
        return html + '</div>';
    }

    /* ---- Where bookings come from ------------------------------------------- */

    /** Drawn source marks: someone walking in, the app on a phone, a WhatsApp chat. */
    function sourceMark(k) {
        var o = '<svg viewBox="0 0 24 24" aria-hidden="true">';
        if (k === 'walk') {
            return o + '<circle cx="13" cy="4.6" r="2.2" fill="currentColor"/>'
                + '<path d="M11.4 8.6 9 14.2l3.4 2.4-1 5.4M11.4 8.6l2.9.4 1.6 3.4 2.6 1M9 14.2l-2.6 6.6M11.4 8.6 8.6 9.8 7 12.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        if (k === 'app') {
            return o + '<rect x="6.5" y="2.5" width="11" height="19" rx="2.6" fill="none" stroke="currentColor" stroke-width="1.7"/>'
                + '<path d="M9.8 8.4v6.2M14.2 8.4v6.2M9.8 11.5h4.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.8 18.6h2.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".55"/></svg>';
        }
        return o + '<path d="M12 3.2a8.6 8.6 0 0 1 0 17.2c-1.5 0-2.9-.4-4.2-1.1L3.6 20.6l1.3-4A8.6 8.6 0 0 1 12 3.2z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>'
            + '<path d="M9.2 8.8c.3-.5.8-.5 1-.1l.7 1.5c.1.3 0 .6-.2.8l-.5.5c.5 1.1 1.4 2 2.5 2.5l.5-.5c.2-.2.5-.3.8-.2l1.5.7c.4.2.4.7-.1 1-1 .8-2.4.7-3.8-.2a8.3 8.3 0 0 1-2.6-2.6c-.9-1.4-1-2.8.2-3.4z" fill="currentColor"/></svg>';
    }

    /**
     * Where the money came from, drawn as a flow: each source sends a stream into the
     * venue, as thick as what it brought. Empty, the streams are dashed outlines — the
     * shape of the chart is still there, waiting for its first booking.
     */
    function flowChart(parts, total) {
        var W = 320, H = 132, SLOTS = [22, 66, 110], NX = 52, VX = 304, MAXH = 40;
        var top = parts.reduce(function (m, p) { return Math.max(m, p.amount); }, 0);
        var hs = parts.map(function (p) { return total > 0 && p.amount > 0 ? Math.max(4, p.amount / top * MAXH) : 0; });
        var sum = hs.reduce(function (a, b) { return a + b; }, 0) + (total > 0 ? (hs.filter(Boolean).length - 1) * 2 : 0);
        var vy = (H - Math.max(sum, 36)) / 2, vh = Math.max(sum, 36);
        var svg = '<svg class="ha-flow-svg" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true"><defs>';
        parts.forEach(function (p, i) {
            svg += '<linearGradient id="haflow' + i + '" x1="0" x2="1"><stop offset="0" stop-color="' + p.color + '" stop-opacity=".32"/><stop offset="1" stop-color="' + p.color + '" stop-opacity=".12"/></linearGradient>';
        });
        svg += '</defs>';
        var cursor = vy + (vh - sum) / 2;
        parts.forEach(function (p, i) {
            var cy = SLOTS[i], h = hs[i];
            if (!h) {
                svg += '<path d="M' + NX + ' ' + cy + ' C' + ((NX + VX) / 2) + ' ' + cy + ' ' + ((NX + VX) / 2) + ' ' + (H / 2) + ' ' + VX + ' ' + (H / 2) + '" fill="none" stroke="#D5DCE7" stroke-width="1.4" stroke-dasharray="3 4"/>';
                return;
            }
            var y0 = cy - h / 2, y1 = cy + h / 2, r0 = cursor, r1 = cursor + h, mx = (NX + VX) / 2;
            cursor = r1 + 2;
            svg += '<path class="ha-flow-band" style="--i:' + i + '" d="M' + NX + ' ' + y0 + ' C' + mx + ' ' + y0 + ' ' + mx + ' ' + r0 + ' ' + VX + ' ' + r0 + ' L' + VX + ' ' + r1 + ' C' + mx + ' ' + r1 + ' ' + mx + ' ' + y1 + ' ' + NX + ' ' + y1 + 'Z" fill="url(#haflow' + i + ')"/>'
                + '<rect x="' + (NX - 4) + '" y="' + y0 + '" width="4" height="' + h + '" rx="2" fill="' + p.color + '"/>';
        });
        svg += '<rect x="' + VX + '" y="' + vy + '" width="9" height="' + vh + '" rx="4.5" fill="' + (total > 0 ? '#0B1C46' : '#D5DCE7') + '"/></svg>';
        var marks = parts.map(function (p, i) {
            return '<span class="ha-flow-src' + (p.amount > 0 ? '' : ' is-off') + '" style="top:' + (SLOTS[i] / H * 100).toFixed(2) + '%;--c:' + p.color + '">' + sourceMark(p.mark) + '</span>';
        }).join('');
        return '<div class="ha-flow">' + svg + marks + '<span class="ha-flow-dest"><small>Your venue</small><b>' + (total > 0 ? rupees(total) : '₹0') + '</b></span></div>';
    }

    function channelCard(ins) {
        var s = home.state;
        var current = !ins.week.next;
        var todayMode = current && !!s.chToday;
        var ch = ins.channels || {};
        var split = (todayMode ? ch.today : ch.week) || {};
        var parts = [
            { name: 'Walk-in', d: split.walk_in || {}, color: '#0B1C46', mark: 'walk' },
            { name: 'App', d: split.app || {}, color: '#2F6BFF', mark: 'app' },
            { name: 'WhatsApp', d: split.whatsapp || {}, color: '#1FAF5B', mark: 'wa' },
        ];
        parts.forEach(function (p) { p.amount = p.d.amount || 0; });
        var total = parts.reduce(function (t, p) { return t + p.amount; }, 0);
        var count = parts.reduce(function (t, p) { return t + (p.d.count || 0); }, 0);
        var html = '<div class="ha-card ha-ch"><div class="ha-row"><b class="ha-ctitle">' + (total > 0 ? plural(count, 'booking') + ' · ' + rupees(total) : (todayMode ? 'No bookings today yet' : 'No bookings this week yet')) + '</b>'
            + (current ? seg('ch', [['today', 'Today'], ['week', 'Week']], todayMode ? 'today' : 'week') : '') + '</div>'
            + flowChart(parts, total)
            + parts.map(function (p) {
                var pc = total > 0 ? Math.round(p.amount / total * 100) : 0;
                return '<div class="ha-ch-row' + (p.d.count ? '' : ' is-off') + '"><i style="background:' + p.color + '"></i><span>' + p.name + '</span><small>' + (p.d.count ? hoursLabel(p.d.hours) : '—') + '</small>'
                    + '<em>' + (p.d.count ? pc + '%' : '') + '</em><b>' + (p.d.count ? rupees(p.amount) : '') + '</b></div>';
            }).join('');
        function share(x) { var t = (x.walk_in || {}).amount + (x.app || {}).amount + (x.whatsapp || {}).amount; return t > 0 ? ((x.app || {}).amount + (x.whatsapp || {}).amount) / t : null; }
        var nowShare = share({ walk_in: split.walk_in || { amount: 0 }, app: split.app || { amount: 0 }, whatsapp: split.whatsapp || { amount: 0 } });
        if (nowShare != null) {
            var pct = Math.round(nowShare * 100);
            var period = todayMode ? 'today’s' : 'this week’s';
            var lw = !todayMode && ch.last_week ? share({ walk_in: ch.last_week.walk_in || { amount: 0 }, app: ch.last_week.app || { amount: 0 }, whatsapp: ch.last_week.whatsapp || { amount: 0 } }) : null;
            var prev = lw == null ? null : Math.round(lw * 100);
            var dir = prev == null || prev === pct ? '' : pct > prev ? 'ha-dir-up' : 'ha-dir-down';
            html += '<p class="ha-ch-line ' + dir + '"><span>Haraan brought <b>' + pct + '%</b> of ' + period + ' money</span>'
                + (dir ? '<em>' + (dir === 'ha-dir-up' ? '▲' : '▼') + ' from ' + prev + '%</em>' : '') + '</p>';
        }
        return html + '</div>';
    }

    /* ---- in-place card swaps (a toggle redraws its own card, not the page) ---- */

    /** Each segmented control's dark thumb sits under its selected option. */
    function placeSegs(root) {
        root.querySelectorAll('.ha-seg').forEach(function (sg) {
            var on = sg.querySelector('button.is-on'), t = sg.querySelector('.ha-seg-thumb');
            if (!on || !t) return;
            t.style.left = on.offsetLeft + 'px';
            t.style.width = on.offsetWidth + 'px';
        });
    }

    function swapCard(sel, html) {
        var host = homeHost();
        var old = host && host.querySelector(sel);
        if (!old || !html) { renderHome(); return; }
        var thumbs = Array.prototype.map.call(old.querySelectorAll('.ha-seg'), function (sg) {
            var t = sg.querySelector('.ha-seg-thumb');
            return t ? { name: sg.getAttribute('data-seg'), left: t.offsetLeft, width: t.offsetWidth } : null;
        }).filter(Boolean);
        var el = h(html);
        if (old.getAttribute('style')) el.setAttribute('style', old.getAttribute('style'));
        el.classList.add('is-swapped');
        old.parentNode.replaceChild(el, old);
        placeSegs(el);
        // The thumb is played back from where it stood, so it slides rather than jumps.
        thumbs.forEach(function (o) {
            var t = el.querySelector('.ha-seg[data-seg="' + o.name + '"] .ha-seg-thumb');
            if (!t) return;
            var left = t.style.left, width = t.style.width;
            t.style.transition = 'none';
            t.style.left = o.left + 'px';
            t.style.width = o.width + 'px';
            void t.offsetWidth;
            t.style.transition = '';
            t.style.left = left;
            t.style.width = width;
        });
        countUps(el);
    }

    function growthCard(g) {
        var s = home.state;
        var bookingsMode = !!s.growthBookings;
        var values = [0].concat(g.points.map(function (p) { return bookingsMode ? p.bookings : p.revenue; }));
        var top = Math.max.apply(null, values) || 1;
        var W = 320, H = 130, pad = 6;
        var pts = values.map(function (v, i) { return [W * i / (values.length - 1), pad + (H - 2 * pad) * (1 - v / top)]; });
        var line = 'M' + pts[0][0] + ' ' + pts[0][1];
        for (var i = 1; i < pts.length; i++) {
            var a = pts[i - 1], b = pts[i], mx = (a[0] + b[0]) / 2;
            line += ' C' + mx + ' ' + a[1] + ' ' + mx + ' ' + b[1] + ' ' + b[0] + ' ' + b[1];
        }
        var area = line + ' L' + pts[pts.length - 1][0] + ' ' + H + ' L0 ' + H + ' Z';
        var last = pts[pts.length - 1];
        s.growthPts = pts;
        return '<div class="ha-card ha-growth"><div class="ha-row"><span class="ha-grow"><small class="ha-label">' + esc(g.since ? 'Since your first game · ' + g.since : 'So far') + '</small>'
            + '<b class="ha-mid">' + (bookingsMode ? plural(g.bookings, 'booking') : rupees(g.revenue)) + '</b></span>'
            + seg('growth', [['money', '₹'], ['bookings', 'Bookings']], bookingsMode ? 'bookings' : 'money') + '</div>'
            + '<p class="ha-gr-read">Drag along the line to look back</p>'
            + '<div class="ha-gr-chart"><svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">'
            + '<defs><linearGradient id="ha-gr-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2F6BFF" stop-opacity=".22"/><stop offset="1" stop-color="#2F6BFF" stop-opacity="0"/></linearGradient></defs>'
            + '<path class="ha-gr-area" d="' + area + '" fill="url(#ha-gr-fill)"/>'
            + '<path class="ha-gr-line" d="' + line + '" fill="none" stroke="#2F6BFF" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke" pathLength="1"/>'
            + '<line class="ha-gr-cursor" x1="0" x2="0" y1="0" y2="' + H + '" stroke="rgba(15,23,42,.18)" vector-effect="non-scaling-stroke" visibility="hidden"/></svg>'
            + '<i class="ha-gr-dot" style="left:' + (last[0] / W * 100) + '%;top:' + (last[1] / H * 100) + '%"></i>'
            + '<i class="ha-gr-pick" hidden></i></div>'
            + '<div class="ha-row ha-gr-axis"><small>Start</small><small>Now</small></div></div>';
    }

    function tomorrowCards(t) {
        return '<div class="ha-tomorrow">' + t.venues.map(function (v) {
            var body;
            if (v.closed) body = '<b class="ha-ctitle">Closed tomorrow</b>';
            else if (v.total_hours <= 0) body = '<b class="ha-ctitle">No slots set for ' + esc(t.label) + '</b>';
            else if (v.open_hours <= 0) body = '<b class="ha-ctitle is-up">Fully booked for ' + esc(t.label) + '</b>';
            else {
                var sold = Math.max(0, Math.min(1, 1 - v.open_hours / v.total_hours));
                body = '<div class="ha-tm-fig"><b>' + hrs(v.open_hours) + '</b><span> of ' + hrs(v.total_hours) + ' court-hours still open</span></div>'
                    + '<div class="ha-track"><i style="--w:' + sold + '"></i></div>'
                    + (v.windows || []).map(function (w) { return '<div class="ha-tm-win"><i></i><span>' + esc(w.label) + '</span><small>' + (w.free_courts === 1 ? '1 court free' : w.free_courts + ' courts free') + '</small></div>'; }).join('')
                    + '<button type="button" class="ha-cta ha-cta-wide" data-share="' + esc(v.share_text || '') + '" data-wa>' + mat('share') + 'Share on WhatsApp</button>';
            }
            return '<div class="ha-card">' + (t.venues.length > 1 ? '<small class="ha-label">' + esc(v.name) + '</small>' : '') + body + '</div>';
        }).join('') + '</div>';
    }

    function heatShade(f) {
        if (f <= 0) return '#F1F4F9';
        if (f < 0.25) return '#D6E3FF';
        if (f < 0.5) return '#9DBBFF';
        if (f < 0.75) return '#2F6BFF';
        return '#1537B0';
    }

    function busyCard(heat) {
        var s = home.state;
        var rows = heat.rows || [];
        var cols = [];
        heat.hours.forEach(function (lab, hIdx) {
            var sold = rows.some(function (r) { return r.fill[hIdx] != null; });
            if (sold) cols.push({ i: hIdx, label: lab });
            else if (cols.length && !cols[cols.length - 1].fold) cols.push({ fold: true });
        });
        if (cols.length && cols[cols.length - 1].fold) cols.pop();
        var busiest = null;
        rows.forEach(function (r, ri) { (r.fill || []).forEach(function (f, ci) { if (f != null && (!busiest || f > busiest[2])) busiest = [ri, ci, f]; }); });
        if (busiest && busiest[2] <= 0) busiest = null;
        var todayRow = (new Date().getDay() + 6) % 7;
        var pick = s.heatPick || (busiest ? [busiest[0], busiest[1]] : null);
        var pf = pick ? (rows[pick[0]] || {}).fill[pick[1]] : null;

        var html = '<div class="ha-card ha-heat">';
        if (pick && pf != null) {
            html += '<h3>' + (s.heatPick ? '' : 'Busiest: ') + esc(rows[pick[0]].label + ' ' + (heat.hours[pick[1]] || '')) + '</h3>'
                + '<p class="ha-muted ha-small">Usually ' + Math.round(pf * 100) + '% full · last ' + heat.weeks + ' weeks</p>';
        } else {
            html += '<h3>Every hour is still open to sell</h3><p class="ha-muted ha-small">No hour has filled up in the last ' + heat.weeks + ' weeks yet</p>';
        }
        var hourCols = cols.filter(function (c) { return !c.fold; });
        if (hourCols.length) {
            var tmpl = cols.map(function (c) { return c.fold ? '.35fr' : '1fr'; }).join(' ');
            html += '<div class="ha-heat-grid" style="--cols:' + tmpl + '">';
            rows.forEach(function (r, ri) {
                html += '<span class="ha-heat-day' + (ri === todayRow ? ' is-today' : '') + '">' + esc(String(r.label).slice(0, 3)) + (ri === todayRow ? '<i></i>' : '') + '</span>'
                    + '<span class="ha-heat-cells">' + cols.map(function (c, ci) {
                        if (c.fold) return '<i class="ha-heat-fold"></i>';
                        var f = r.fill[c.i];
                        var on = pick && s.heatPick && pick[0] === ri && pick[1] === c.i;
                        return '<b' + (f == null ? ' class="is-none"' : ' data-heat="' + ri + ':' + c.i + '" class="' + (on ? 'is-pick' : '') + '" style="background:' + heatShade(f) + ';--d:' + ci + '"') + '></b>';
                    }).join('') + '</span>';
            });
            var since = 99;
            html += '<span></span><span class="ha-heat-cells ha-heat-marks">' + cols.map(function (c, ci) {
                if (c.fold) { since = 99; return '<i class="ha-heat-fold"></i>'; }
                var mark = since >= 3 && ci < cols.length - 1;
                if (mark) since = 0;
                since++;
                return '<small>' + (mark ? esc(c.label) : '') + '</small>';
            }).join('') + '</span></div>'
                + '<div class="ha-heat-key">Empty' + [0, 0.2, 0.4, 0.6, 0.9].map(function (f) { return '<i style="background:' + heatShade(f) + '"></i>'; }).join('') + 'Full</div>';
        }
        if (heat.ready && heat.quiet && heat.quiet.length) {
            html += '<hr><b class="ha-quiet-h">Quiet hours to fill</b>' + heat.quiet.map(function (q) {
                return '<div class="ha-quiet"><span>' + esc(q.days + ' · ' + q.hours) + '</span><em>' + q.fill + '% full</em></div>';
            }).join('');
            if (can('pricing') && u.pricing) html += '<a class="ha-soft-cta" href="' + esc(u.pricing) + '">Set a lower price for these hours' + mat('chevron') + '</a>';
        } else if (!heat.ready) {
            html += '<p class="ha-muted ha-small">Quiet hours show up here after a few more bookings.</p>';
        }
        return html + '</div>';
    }

    /* ---- interactions ------------------------------------------------------ */

    function share(text, whatsapp) {
        if (!text) return;
        if (whatsapp && /iPhone|iPad|Android/.test(navigator.userAgent)) {
            location.href = 'https://wa.me/?text=' + encodeURIComponent(text);
            return;
        }
        if (navigator.share) { navigator.share({ text: text }).catch(function () {}); return; }
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
    }

    function wireHome(host) {
        host.onclick = function (e) {
            var t = e.target;
            var b;
            if ((b = t.closest('[data-share]'))) { share(b.getAttribute('data-share'), b.hasAttribute('data-wa')); return; }
            if ((b = t.closest('[data-locked]'))) {
                var note = host.querySelector('.ha-doors-note');
                note.querySelector('span').textContent = b.getAttribute('data-locked');
                note.hidden = false;
                b.classList.remove('is-shake'); void b.offsetWidth; b.classList.add('is-shake');
                clearTimeout(home.noteTimer);
                home.noteTimer = setTimeout(function () { note.hidden = true; }, 2600);
                return;
            }
            if ((b = t.closest('.ha-pay [data-pay]'))) { lightPayment(host, b.getAttribute('data-pay')); return; }
            if ((b = t.closest('[data-cell]'))) { cellTap(b.getAttribute('data-cell')); return; }
            if ((b = t.closest('.ha-seg button'))) {
                var name = b.parentNode.getAttribute('data-seg');
                var v = b.getAttribute('data-v');
                if (b.classList.contains('is-on')) return;
                if (navigator.vibrate) { try { navigator.vibrate(5); } catch (err) { /* none */ } }
                var ins = home.insights;
                // Only the toggled card redraws: the page stays put and its thumb slides.
                if (name === 'cust') { home.state.custMonthly = v === 'month'; swapCard('.ha-cust', ins && ins.customers ? customersCard(ins.customers, home.state.custMonthly) : null); }
                if (name === 'mode') { home.state.hoursMode = v === 'hours'; swapCard('.ha-week', ins ? weekCard(ins) : null); }
                if (name === 'ch') { home.state.chToday = v === 'today'; swapCard('.ha-ch', ins ? channelCard(ins) : null); }
                if (name === 'growth') { home.state.growthBookings = v === 'bookings'; swapCard('.ha-growth', ins && ins.growth ? growthCard(ins.growth) : null); }
                return;
            }
            if ((b = t.closest('[data-day]'))) {
                home.state.selDay = +b.getAttribute('data-day');
                if (navigator.vibrate) { try { navigator.vibrate(4); } catch (err) { /* none */ } }
                swapCard('.ha-week', home.insights ? weekCard(home.insights) : null);
                return;
            }
            if ((b = t.closest('[data-week]'))) {
                var week = b.getAttribute('data-week');
                if (!week || home.state.weekLoading) return;
                home.state.weekLoading = true;
                renderHome();
                api('insights', { week: week }).then(function (r) { home.insights = r.data || r; }).catch(function () {}).finally(function () {
                    home.state.weekLoading = false;
                    renderHome();
                });
                return;
            }
            if ((b = t.closest('[data-heat]'))) {
                var rc = b.getAttribute('data-heat').split(':').map(Number);
                var cur = home.state.heatPick;
                home.state.heatPick = cur && cur[0] === rc[0] && cur[1] === rc[1] ? null : rc;
                renderHome();
            }
        };
        wireGrowth(host);
        wireWeekSwipe(host);
    }

    function wireGrowth(host) {
        var chart = host.querySelector('.ha-gr-chart');
        var g = home.insights && home.insights.growth;
        if (!chart || !g) return;
        var read = host.querySelector('.ha-gr-read');
        var cursor = chart.querySelector('.ha-gr-cursor');
        var pickDot = chart.querySelector('.ha-gr-pick');
        var pts = home.state.growthPts;
        function at(clientX) {
            var r = chart.getBoundingClientRect();
            var i = Math.round(((clientX - r.left) / r.width) * (pts.length - 1));
            i = Math.max(0, Math.min(pts.length - 1, i));
            var p = pts[i];
            cursor.setAttribute('x1', p[0]); cursor.setAttribute('x2', p[0]); cursor.setAttribute('visibility', 'visible');
            pickDot.hidden = false;
            pickDot.style.left = (p[0] / 320 * 100) + '%';
            pickDot.style.top = (p[1] / 130 * 100) + '%';
            var pt = g.points[i - 1];
            read.classList.add('is-on');
            read.textContent = pt ? (g.unit === 'month' ? 'By end of ' : 'By week of ') + pt.label + ' · ' + rupees(pt.revenue) + ' · ' + plural(pt.bookings, 'booking') : 'Before your first game';
        }
        var dragging = false;
        chart.addEventListener('pointerdown', function (e) { dragging = true; at(e.clientX); });
        chart.addEventListener('pointermove', function (e) { if (dragging || e.pointerType === 'mouse') at(e.clientX); });
        window.addEventListener('pointerup', function () { dragging = false; });
    }

    function wireWeekSwipe(host) {
        var bars = host.querySelector('.ha-wk-bars');
        if (!bars) return;
        var x0 = null;
        bars.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        bars.addEventListener('touchend', function (e) {
            if (x0 == null) return;
            var dx = e.changedTouches[0].clientX - x0;
            x0 = null;
            if (Math.abs(dx) < 60) return;
            var w = home.insights.week;
            var target = dx < 0 ? w.next : w.prev;
            var btn = target && host.querySelector('[data-week="' + target + '"]');
            if (btn) btn.click();
        });
    }

    /** Numbers count up from where they last stood, like rememberMoneyMotion. */
    function countUps(host) {
        host.querySelectorAll('[data-count]').forEach(function (el) {
            var to = parseFloat(el.getAttribute('data-count'));
            if (!isFinite(to) || to <= 0) return;
            var key = 'ha-mm:' + el.closest('[class]').className.split(' ')[0] + ':' + (cfg.branch || 'all');
            var from = parseFloat(store(key) || '0');
            store(key, String(to));
            if (from === to || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var t0 = null;
            function step(t) {
                if (!t0) t0 = t;
                var k = Math.min(1, (t - t0) / 900);
                var e = 1 - Math.pow(1 - k, 3);
                el.textContent = rupees(from + (to - from) * e);
                if (k < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }

    /* ---- the counter sheet (a booked block tapped) -------------------------- */

    function closeSheet() {
        var s = document.getElementById('ha-sheet');
        if (s) { s.classList.remove('is-open'); setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 220); }
    }

    function cellTap(rc) {
        var p = rc.split(':').map(Number);
        var hour = (home.state.gridHours || [])[p[1]];
        if (!hour) return;
        var bk = hour.bookings[p[0]];
        var court = (home.state.gridCourts[p[0]] || {}).name || null;
        if (hour.cells[p[0]] !== 'booked' || !bk) { go(urlOf('sales')); return; }
        var digits = String(bk.phone || '').replace(/\D/g, '');
        var local = digits.length > 10 ? digits.slice(-10) : digits;
        var pretty = local.length === 10 ? '+91 ' + local.slice(0, 5) + ' ' + local.slice(5) : (bk.phone || '');
        var owed = Math.max(0, bk.amount - bk.paid);
        var sheet = h('<div id="ha-sheet" class="ha-bsheet"><div class="ha-scrim" data-x></div><div class="ha-bsheet-in" role="dialog" aria-label="Booking">'
            + '<i class="ha-grab"></i><h3>' + esc(bk.customer) + '</h3><p class="ha-muted">' + esc([court, hour.time, bk.walkIn ? 'Walk-in' : 'Online'].filter(Boolean).join(' · ')) + '</p>'
            + '<div class="ha-bs-money"><span><b>' + rupees(bk.amount) + '</b><small class="' + (owed > 0 ? 'is-due' : 'is-in') + '">'
            + (owed <= 0 ? 'Paid in full' : bk.paid > 0 ? rupees(bk.paid) + ' paid · ' + rupees(owed) + ' to collect' : 'Not paid yet') + '</small></span>'
            + (pretty ? '<em>' + esc(pretty) + '</em>' : '') + '</div>'
            + (local.length === 10 ? '<div class="ha-bs-acts"><a class="ha-cta" href="tel:+91' + local + '">' + mat('phone') + 'Call</a><a class="ha-ghost" href="https://wa.me/91' + local + '" target="_blank" rel="noopener">WhatsApp</a></div>' : '')
            + '<a class="ha-bs-link" href="' + esc(urlOf('sales')) + '">Open in Bookings</a></div></div>');
        document.body.appendChild(sheet);
        requestAnimationFrame(function () { sheet.classList.add('is-open'); });
        sheet.addEventListener('click', function (e) { if (e.target.closest('[data-x]') || e.target.closest('.ha-bs-link')) closeSheet(); });
    }

    /* ---- compact bar ------------------------------------------------------- */

    function renderCompact() {
        var bar = document.getElementById('ha-compact');
        if (!bar) {
            bar = h('<div id="ha-compact" class="ha-compact ha-glassbar"></div>');
            document.body.appendChild(bar);
        }
        bar.innerHTML = headerHtml();
    }

    window.addEventListener('scroll', function () {
        if (!root.classList.contains('ha-home-on')) return;
        root.classList.toggle('ha-collapsed', window.scrollY > 8);
    }, { passive: true });

    /* ============================================================== screens === */

    /**
     * The other tabs (Venues, Payments, Matches, Scan) live in app-screens.js and register
     * here. A tab's screen draws over its console page on a phone, the way Home does.
     */
    var screens = {};
    var active = null; // { key, path, screen }

    function sheet(html, cls) {
        var s = h('<div class="ha-bsheet ' + (cls || '') + '"><div class="ha-scrim" data-x></div><div class="ha-bsheet-in" role="dialog"><i class="ha-grab"></i>' + html + '</div></div>');
        document.body.appendChild(s);
        requestAnimationFrame(function () { s.classList.add('is-open'); });
        s.close = function () {
            s.classList.remove('is-open');
            setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); if (s.onclose) s.onclose(); }, 220);
        };
        s.addEventListener('click', function (e) { if (e.target.closest('[data-x]')) s.close(); });
        return s;
    }

    function toast(text) {
        var t = h('<div class="ha-toast" role="status"></div>');
        t.textContent = text;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('is-in'); });
        setTimeout(function () { t.classList.remove('is-in'); setTimeout(function () { t.remove(); }, 250); }, 2400);
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.querySelector('script[src="' + src + '"]');
            if (s && s.dataset.ready) return resolve();
            if (!s) { s = document.createElement('script'); s.src = src; s.async = true; document.head.appendChild(s); }
            s.addEventListener('load', function () { s.dataset.ready = '1'; resolve(); });
            s.addEventListener('error', reject);
        });
    }

    /** The white header a drawer tool carries in the app: back, title, optional actions. */
    function toolHeader(title, actions) {
        return '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-back aria-label="Back"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<b>' + esc(title) + '</b><span class="ha-grow"></span>' + (actions || '') + '</header>';
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.ha-toolbar [data-back]')) return;
        e.preventDefault();
        var ref = document.referrer;
        if (ref && ref.indexOf(location.origin + '/partner') === 0 && history.length > 1) history.back();
        else go(u.home);
    });

    window.HaApp = {
        cfg: cfg, u: u, api: api, apiSend: apiSend, soft: soft, esc: esc, inr: inr, rupees: rupees, hrs: hrs,
        plural: plural, initials: initials, slotStart: slotStart, clock: clock, shortTime: shortTime,
        minutesNow: minutesNow, ymd: ymd, store: store, h: h, glyph: glyph, mat: mat, MAT: MAT, DRAWER: DRAWER,
        can: can, isDesk: isDesk, lane: lane, courtsLane: courtsLane, urlOf: urlOf, go: go, share: share,
        sheet: sheet, toast: toast, loadScript: loadScript, months: MONTHS, toolHeader: toolHeader, payToken: payToken,
        token: function () { return token; }, refresh: refreshToken,
        register: function (key, screen) { screens[key] = screen; },
    };

    function leaveScreen() {
        if (!active) return;
        try { if (active.screen.leave) active.screen.leave(); } catch (e) { /* screen gone */ }
        active = null;
        root.classList.remove('ha-screen-on', 'ha-full', 'ha-tool');
    }

    function screenHost() {
        var host = document.getElementById('ha-screen');
        if (!host) {
            var main = document.querySelector('.fi-main') || document.querySelector('main');
            if (!main) return null;
            host = h('<div id="ha-screen" class="ha-screen"></div>');
            main.insertBefore(host, main.firstChild);
        }
        return host;
    }

    /** The tab whose own page this is — exactly, so a venue's edit page keeps the console. */
    function screenKey() {
        var here = location.pathname.replace(/\/+$/, '');
        var keys = Object.keys(screens);
        for (var i = 0; i < keys.length; i++) {
            var url = urlOf(keys[i]);
            if (url && path(url) === here) return keys[i];
        }
        return null;
    }

    function mountScreen() {
        var key = screenKey();
        var here = location.pathname;
        if (active && (active.key !== key || active.path !== here || !document.getElementById('ha-screen'))) leaveScreen();
        if (!key) return false;
        var screen = screens[key];
        root.classList.add('ha-screen-on');
        root.classList.toggle('ha-full', !!screen.full);
        root.classList.toggle('ha-tool', !!screen.tool);
        var host = screenHost();
        if (!host) return false;
        if (!active) {
            active = { key: key, path: here, screen: screen };
            screen.enter(host);
        }
        return true;
    }

    /* ================================================================ mount === */

    function mount() {
        if (!PHONE.matches) {
            leaveScreen();
            root.classList.remove('ha-app', 'ha-home-on');
            var wide = document.querySelector('.fi-main');
            if (wide) { wide.style.removeProperty('padding'); wide.style.removeProperty('max-width'); }
            return;
        }
        root.classList.add('ha-app');
        var onHome = isHome();
        root.classList.toggle('ha-home-on', onHome);
        var onScreen = !onHome && mountScreen();
        if (!onScreen) leaveScreen();
        // Home and the tab screens run edge to edge like the app. The theme pads .fi-main
        // with a layered !important rule, which no stylesheet can outrank — only the
        // element's own style.
        var main = document.querySelector('.fi-main');
        if (main) {
            if (onHome || onScreen) { main.style.setProperty('padding', '0', 'important'); main.style.setProperty('max-width', 'none', 'important'); }
            else { main.style.removeProperty('padding'); main.style.removeProperty('max-width'); }
        }
        renderNav();
        renderAppBar();
        renderDrawer();
        if (!venuesCache) {
            soft(api('venues', { branch: false })).then(function (r) {
                if (!r) return;
                venuesCache = r.data || r;
                renderAppBar();
                if (root.classList.contains('ha-home-on')) renderCompact();
            });
        }
        if (onHome) {
            renderCompact();
            if (home.data && document.getElementById('ha-home')) renderHome();
            loadHome(!!home.data);
        }
    }

    function start() {
        loadContext().then(function () { mount(); });
        mount();
        // Home keeps itself fresh while it's on screen, the way the app's poll does.
        setInterval(function () {
            if (document.visibilityState === 'visible' && root.classList.contains('ha-home-on')) loadHome(true);
        }, 60000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && root.classList.contains('ha-home-on')) loadHome(true);
        });
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', function (e) {
                if (e.data && e.data.source === 'hrn-push' && root.classList.contains('ha-home-on')) loadHome(true);
            });
        }
        if (PHONE.addEventListener) PHONE.addEventListener('change', mount);
        window.addEventListener('resize', function () {
            var cap = document.querySelector('#ha-nav .ha-nav-cap');
            if (cap) { cap.removeAttribute('data-on'); selectTab(cap, currentTab(), false); }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();

    // Filament's SPA navigation swaps <body>: put the shell back on every page.
    document.addEventListener('livewire:navigated', function () { closeDrawer(); mount(); });
    // Leaving the page (or SPA-navigating away) stops whatever the screen holds open — the camera.
    document.addEventListener('livewire:navigate', leaveScreen);
    window.addEventListener('pagehide', leaveScreen);
})();
