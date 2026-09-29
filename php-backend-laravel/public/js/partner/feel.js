/*
 * Haraan Partner console — how the console feels under the hand.
 *
 *   press    things you can tap sink a little on pointerdown (touch included)
 *   haptics  a short tick on phones for primary actions and saved/failed toasts
 *   count    money and counts run up once, the first time they're seen
 *   settle   cards rise into place the first time they scroll into view
 *   travel   a thin progress line + a small rise when the page changes (SPA)
 *
 * Loaded once per full page by PartnerPanelProvider; Livewire's SPA navigation
 * keeps it alive, so everything re-arms on `livewire:navigated` instead of on
 * load. Nothing here is required for the console to work — every effect is an
 * enhancement layered on already-rendered HTML.
 */
(function () {
    if (window.__hrnFeel) return;
    window.__hrnFeel = true;

    var doc = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;

    if (!reduce) doc.classList.add('hrn-feel');

    /* ── haptics ─────────────────────────────────────────────────────────── */
    // Android Chrome honours navigator.vibrate; iOS Safari has no API and simply
    // ignores it. Desktop never buzzes (hover-capable pointer ⇒ no haptics).
    function buzz(pattern) {
        if (!touch || !navigator.vibrate) return;
        try { navigator.vibrate(pattern); } catch (e) { /* user gesture rules */ }
    }

    /* ── press ───────────────────────────────────────────────────────────── */
    var PRESSABLE = [
        '.fi-btn', '.fi-icon-btn', '.fi-sidebar-item-btn', '.fi-tabs-item',
        '.pqh-btn', '.pqa-alert', '.hrn-create-cta', '.fi-ta-record[href]', 'a.fi-ta-record',
        '.fi-dropdown-list-item', '.hrn-acct-link', '[data-press]'
    ].join(',');
    var pressed = null;

    function release() {
        if (pressed) pressed.classList.remove('is-pressed');
        pressed = null;
    }

    document.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        var el = e.target.closest(PRESSABLE);
        if (!el || el.disabled || el.getAttribute('aria-disabled') === 'true') return;
        pressed = el;
        el.classList.add('is-pressed');
        if (el.matches('.fi-color-primary, .pqh-btn.is-primary, .hrn-create-cta, [data-haptic]')) buzz(8);
    }, { passive: true });
    ['pointerup', 'pointercancel', 'dragstart'].forEach(function (t) {
        document.addEventListener(t, release, { passive: true });
    });
    document.addEventListener('pointerleave', release, { passive: true });
    window.addEventListener('blur', release);

    /* ── toasts → haptics ────────────────────────────────────────────────── */
    // Filament notifications land as .fi-no-notification nodes; a success gets a
    // soft double tick, a failure one firmer pulse.
    new MutationObserver(function (records) {
        records.forEach(function (r) {
            r.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                var note = n.matches('.fi-no-notification') ? n : n.querySelector && n.querySelector('.fi-no-notification');
                if (!note) return;
                if (note.matches('.fi-status-success')) buzz([8, 60, 12]);
                else if (note.matches('.fi-status-danger')) buzz(40);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    /* ── count up ────────────────────────────────────────────────────────── */
    // Indian grouping, like the server renders: ₹18,42,900.
    function inr(n) {
        var s = String(Math.round(Math.abs(n)));
        if (s.length > 3) {
            var last3 = s.slice(-3), rest = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
            s = rest + ',' + last3;
        }
        return (n < 0 ? '-' : '') + s;
    }

    // Each figure counts up once per session per value, so flipping between
    // pages doesn't replay the same animation over and over.
    var seen = {};
    try { seen = JSON.parse(sessionStorage.getItem('hrn-counted') || '{}'); } catch (e) { seen = {}; }
    function remember(key) {
        seen[key] = 1;
        try { sessionStorage.setItem('hrn-counted', JSON.stringify(seen)); } catch (e) { /* private mode */ }
    }

    function countUp(el, to, prefix, suffix) {
        var key = location.pathname + '|' + (el.dataset.countKey || prefix + suffix) + '|' + to;
        if (reduce || to === 0 || seen[key]) return;
        remember(key);
        var dur = Math.min(1100, 520 + Math.log10(Math.abs(to) + 1) * 110);
        var t0 = null;
        function frame(t) {
            if (t0 === null) t0 = t;
            var p = Math.min(1, (t - t0) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + inr(to * eased) + suffix;
            if (p < 1) requestAnimationFrame(frame);
            else el.textContent = el.dataset.countFinal;
        }
        el.dataset.countFinal = el.textContent;
        el.textContent = prefix + '0' + suffix;
        requestAnimationFrame(frame);
    }

    // Stat values arrive as text ("₹6,70,800", "754", "42%"); only plain whole
    // numbers are animated — anything else ("—", "4.8 ★", dates) is left alone.
    var NUM = /^(₹?)\s?(-?[\d,]+)(%?)$/;

    function armCounts(root) {
        root.querySelectorAll('[data-count-to]:not([data-counted])').forEach(function (el) {
            el.setAttribute('data-counted', '');
            watch(el, function () {
                countUp(el, parseFloat(el.dataset.countTo) || 0, el.dataset.countPrefix || '', el.dataset.countSuffix || '');
            });
        });
        root.querySelectorAll('.fi-wi-stats-overview-stat-value:not([data-counted])').forEach(function (el) {
            el.setAttribute('data-counted', '');
            var m = (el.textContent || '').trim().match(NUM);
            if (!m) return;
            var n = parseInt(m[2].replace(/,/g, ''), 10);
            if (!isFinite(n)) return;
            el.dataset.countKey = (el.closest('.fi-wi-stats-overview-stat')?.querySelector('.fi-wi-stats-overview-stat-label')?.textContent || '').trim();
            watch(el, function () { countUp(el, n, m[1], m[3]); });
        });
    }

    /* ── settle in ───────────────────────────────────────────────────────── */
    var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (!en.isIntersecting) return;
            io.unobserve(en.target);
            var cb = en.target.__hrnOnSeen;
            if (cb) { en.target.__hrnOnSeen = null; cb(); }
        });
    }, { rootMargin: '0px 0px -6% 0px', threshold: .08 }) : null;

    function watch(el, cb) {
        if (!io) return cb();
        var prev = el.__hrnOnSeen;
        el.__hrnOnSeen = prev ? function () { prev(); cb(); } : cb;
        io.observe(el);
    }

    var SETTLE = '.fi-page-main .fi-wi-widget, .fi-page-main .fi-ta-ctn, .fi-page-main .fi-page-content > .fi-section, .fi-page-main .fi-sc-section > .fi-section';

    function armSettle(root, firstPaint) {
        if (reduce) return;
        var batch = 0;
        root.querySelectorAll(SETTLE).forEach(function (el) {
            if (el.__hrnSettled) return;
            el.__hrnSettled = true;
            // Anything already on screen at first paint of a full load gets a
            // short stagger; things further down wait until they're scrolled to.
            var r = el.getBoundingClientRect();
            if (r.top > innerHeight * 1.5 && !firstPaint) return;
            el.classList.add('hrn-pre');
            var onScreen = r.top < innerHeight;
            var delay = onScreen ? Math.min(batch++ * 55, 330) : 0;
            var reveal = function () {
                if (!el.classList.contains('hrn-pre')) return;
                el.style.setProperty('--hrn-d', delay + 'ms');
                el.classList.add('hrn-in');
                void el.offsetWidth; // commit the start state so the transition runs
                el.classList.remove('hrn-pre');
                setTimeout(function () { el.classList.remove('hrn-in'); el.style.removeProperty('--hrn-d'); }, 900 + delay);
            };
            // What's already on screen settles in straight away — it must never
            // wait on an observer (background tabs don't report intersections).
            // Only cards further down wait to be scrolled to, with a hard
            // fallback so nothing can stay hidden.
            if (onScreen) setTimeout(reveal, 16);
            else { watch(el, reveal); setTimeout(reveal, 4000); }
        });
    }

    /* ── travel ──────────────────────────────────────────────────────────── */
    var bar = document.createElement('div');
    bar.id = 'hrn-progress';
    document.body.appendChild(bar);

    document.addEventListener('livewire:navigate', function () {
        bar.classList.remove('is-done');
        void bar.offsetWidth;
        bar.classList.add('is-on');
    });

    /* ── boot / re-arm ───────────────────────────────────────────────────── */
    function arm(firstPaint) {
        var main = document.querySelector('.fi-main') || document.body;
        armSettle(main, firstPaint);
        armCounts(main);
    }

    function onNavigated() {
        bar.classList.remove('is-on');
        bar.classList.add('is-done');
        var main = document.querySelector('.fi-main');
        if (main && !reduce) {
            main.classList.remove('hrn-page-in');
            void main.offsetWidth;
            main.classList.add('hrn-page-in');
        }
        arm(true);
    }

    document.addEventListener('livewire:navigated', onNavigated);
    if (document.readyState !== 'loading') arm(true);
    else document.addEventListener('DOMContentLoaded', function () { arm(true); });

    // Lazy widgets and Livewire re-renders add stat values after boot.
    var pending = false;
    new MutationObserver(function () {
        if (pending) return;
        pending = true;
        requestAnimationFrame(function () { pending = false; arm(false); });
    }).observe(document.body, { childList: true, subtree: true });
})();
