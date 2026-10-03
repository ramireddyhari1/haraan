/**
 * Haraan Partner: the app's venue drawer tools on a phone, batch three — Pricing &
 * Courts, Standing Slots, Packages and Academy.
 *
 * Same contract as app-tools.js: each is a `tool` screen registered with the shell
 * (window.HaApp), drawn full screen over its console page on a phone only, reading the
 * same /api/partner endpoints as the Android app.
 *
 * Ported from pricing/ui (PricingMatrixDashboard and its four tabs + rule editor),
 * recurring/ui (StandingSlotsMaster, Dashboard, List, Create, ContractDetail) and
 * PartnerApp.kt (PackagesScreen, AcademyScreen, BatchRosterScreen and their dialogs).
 */
(function () {
    'use strict';

    var A = window.HaApp;
    if (!A || window.__haVenueTools) return;
    window.__haVenueTools = true;

    var esc = A.esc, rupees = A.rupees, h = A.h;
    var DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    var BACK = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>';
    var PLUS = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>';
    var REFRESH = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.73 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>';
    var MORE = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>';
    var TRASH = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM8 9h8v10H8V9zm7.5-5-1-1h-5l-1 1H5v2h14V4z"/></svg>';

    /* ================================================================= shared === */

    function list(r) { return r && r.data ? r.data : (r || []); }
    function vibrate(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { /* none */ } } }
    function whole(n) { return rupees(Math.round(parseFloat(n) || 0)); }
    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }
    function words(s) { return String(s || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
    function empty(text) { return '<div class="ha-emptyline">' + esc(text) + '</div>'; }
    function loading() { return '<div class="ha-skel ha-skel-tab"><b class="s2"></b><b class="s2"></b><b class="s2"></b></div>'; }
    function todayKey() { return DAYS[(new Date().getDay() + 6) % 7]; }
    function parseIso(s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; }
    function prettyDay(s) {
        // A date-only string is a calendar day; a full ISO timestamp is shown in local time.
        var d = typeof s === 'string' ? parseIso(s) : s;
        if (d instanceof Date && isNaN(d)) d = null;
        return d ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()] + ', ' + d.getDate() + ' ' + A.months[d.getMonth()] : String(s || '');
    }

    /** The app's white header: back (or a sub-view's back), title, subtitle, actions. */
    function header(title, sub, actions, subBack) {
        return '<header class="ha-toolbar"><button type="button" class="ha-ibtn" ' + (subBack ? 'data-subback' : 'data-back') + ' aria-label="Back">' + BACK + '</button>'
            + '<span class="ha-grow"><b>' + esc(title) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>' + (actions || '') + '</header>';
    }
    function iconBtn(attr, svg, label) { return '<button type="button" class="ha-ibtn" ' + attr + ' aria-label="' + label + '">' + svg + '</button>'; }

    function failed(host, title, retry) {
        host.innerHTML = header(title) + '<div class="ha-toolbody"><div class="ha-fail"><b>Couldn’t load this</b><span>Check your connection and try again.</span><button type="button" class="ha-cta" data-retry>Try again</button></div></div>';
        host.querySelector('[data-retry]').onclick = retry;
    }

    function dialog(html) {
        var d = h('<div class="ha-dialog"><div class="ha-scrim" data-close></div><div class="ha-dialog-in ha-dialog-wide" role="dialog">' + html + '</div></div>');
        document.body.appendChild(d);
        d.close = function () { d.remove(); };
        d.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) d.close(); });
        return d;
    }

    function confirmBox(title, text, yes, onYes, field) {
        var d = dialog('<b class="ha-dlg-title">' + esc(title) + '</b><p class="ha-dlg-text">' + esc(text) + '</p>'
            + (field ? '<label class="ha-field"><span>' + esc(field) + '</span><input data-reason autocomplete="off"></label>' : '')
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-danger" data-yes>' + esc(yes) + '</button></div>');
        d.querySelector('[data-yes]').addEventListener('click', function () {
            var r = d.querySelector('[data-reason]');
            d.close();
            onYes(r ? r.value.trim() : '');
        });
    }

    function showError(d, err) {
        var e = d.querySelector('.ha-err');
        if (!e) return A.toast((err && err.message) || 'Couldn’t save. Try again.');
        e.textContent = (err && err.message) || 'Couldn’t save. Try again.';
        e.hidden = false;
    }

    function field(key, label, attrs, value) {
        return '<label class="ha-field"><span>' + label + '</span><input data-k="' + key + '" value="' + esc(value == null ? '' : value) + '" autocomplete="off" ' + (attrs || '') + '></label>';
    }
    function chips(name, opts, on, cls) {
        return '<div class="ha-catchips ' + (cls || '') + '" data-chips="' + name + '">' + opts.map(function (o) {
            var isOn = Array.isArray(on) ? on.indexOf(o[0]) >= 0 : on === o[0];
            return '<button type="button" data-v="' + esc(o[0]) + '"' + (isOn ? ' class="is-on"' : '') + '>' + esc(o[1]) + '</button>';
        }).join('') + '</div>';
    }
    /** Single-choice chips inside `root`: tap swaps the highlighted one, returns via cb. */
    function bindChips(root, name, multi, cb) {
        var box = root.querySelector('[data-chips="' + name + '"]');
        if (!box) return;
        box.addEventListener('click', function (e) {
            var b = e.target.closest('[data-v]');
            if (!b) return;
            vibrate(4);
            if (multi) b.classList.toggle('is-on');
            else box.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            cb(multi ? Array.prototype.map.call(box.querySelectorAll('.is-on'), function (x) { return x.dataset.v; }) : b.dataset.v);
        });
    }
    function val(root, k) { var el = root.querySelector('[data-k="' + k + '"]'); return el ? el.value.trim() : ''; }
    function num(root, k) { var v = val(root, k); return v === '' ? null : parseFloat(v); }

    /** The venue a per-venue tool works on: the chosen outlet, else the first one. */
    function toolVenue() {
        return A.api('venues', { branch: false }).then(function (r) {
            var vs = list(r);
            return vs.filter(function (v) { return v.id === A.cfg.branch; })[0] || vs[0] || null;
        });
    }

    function venueCourts(venueId) {
        return A.api('venues/' + venueId + '/courts', { branch: false }).then(function (r) { return list(r); });
    }

    /* ========================================================= PRICING & COURTS === */

    var PR_TABS = ['Rate Matrix', 'Rules', 'Split / Merge', 'Yield'];
    var MODES = [['absolute', 'Flat ₹'], ['delta', '+/- ₹ Surge'], ['percentage', '+/- % Modifier']];
    var pr = { host: null, venue: null, courts: [], dash: null, rules: [], tree: null, tab: 0, court: null, day: todayKey(), matrix: {}, q: '' };

    function prPath(p) { return 'venues/' + pr.venue.id + '/pricing/' + p; }

    function loadPricing() {
        if (!pr.host) return;
        (pr.venue ? Promise.resolve(pr.venue) : toolVenue()).then(function (v) {
            if (!pr.host) return;
            if (!v) { pr.host.innerHTML = header('Pricing & Courts') + empty('This needs a venue. Ask Haraan to add your venue first.'); return; }
            pr.venue = v;
            return Promise.all([venueCourts(v.id), A.api(prPath('dashboard'), { branch: false }), A.api(prPath('rules'), { branch: false }), A.api(prPath('hierarchy'), { branch: false })]).then(function (r) {
                if (!pr.host) return;
                pr.courts = r[0];
                pr.dash = r[1];
                pr.rules = r[2].rules || [];
                pr.tree = r[3];
                pr.matrix = {};
                if (!pr.court || !pr.courts.some(function (c) { return c.id === pr.court; })) pr.court = pr.courts[0] ? pr.courts[0].id : null;
                renderPricing();
            });
        }).catch(function () { if (pr.host) failed(pr.host, 'Pricing & Courts', loadPricing); });
    }

    function renderPricing() {
        var host = pr.host, m = pr.dash || {};
        if (!host) return;
        var recs = m.top_recommendations || [];
        var body = [matrixTab, rulesTab, splitTab, yieldTab][pr.tab]();
        host.innerHTML = header('Dynamic Pricing & Courts', 'Rate matrix, rules & court partition manager', iconBtn('data-refresh', REFRESH, 'Refresh'))
            + '<div class="ha-toolbody ha-prbody"><section class="ha-prkpis">'
            + [['AVG RATE', m.average_hourly_rate > 0 ? whole(m.average_hourly_rate) + '/hr' : '—'], ['RANGE', m.max_rate > 0 ? whole(m.min_rate) + ' - ' + whole(m.max_rate) : '—'], ['ACTIVE RULES', m.active_rules_count || 0], ['COMPOSITE', m.composite_courts_count || 0]]
                .map(function (k) { return '<span><small>' + k[0] + '</small><b>' + k[1] + '</b></span>'; }).join('') + '</section>'
            + '<div class="ha-opstabs">' + PR_TABS.map(function (t, i) {
                var label = i === 1 ? 'Rules (' + pr.rules.length + ')' : t;
                return '<button type="button" data-ptab="' + i + '"' + (i === pr.tab ? ' class="is-on"' : '') + '>' + label + (i === 3 && recs.length ? '<i class="ha-tabbadge">' + recs.length + '</i>' : '') + '</button>';
            }).join('') + '</div>' + body + '</div>';
        if (pr.tab === 0) loadMatrix();
        var s = host.querySelector('[data-rulesearch]');
        if (s) s.addEventListener('input', function () { pr.q = s.value; host.querySelector('.ha-rulelist').innerHTML = ruleCards(); });
    }

    function matrixTab() {
        if (!pr.courts.length) return empty('No courts yet. Add courts to your venue to price them.');
        return '<small class="ha-caps">SELECT COURT</small>' + chips('court', pr.courts.map(function (c) { return [String(c.id), c.name]; }), String(pr.court), 'ha-scrollchips')
            + '<div class="ha-daystrip">' + DAYS.map(function (d) { return '<button type="button" data-day="' + d + '"' + (d === pr.day ? ' class="is-on"' : '') + '>' + d.slice(0, 3).toUpperCase() + '</button>'; }).join('') + '</div>'
            + '<div class="ha-row ha-prlegend"><span class="is-peak">Peak / Surge</span><span class="is-discount">Discount</span><span class="is-standard">Standard</span><span class="ha-grow"></span><button type="button" class="ha-textbtn is-blue" data-newrule>+ New Rule</button></div>'
            + '<div class="ha-slotlist">' + loading() + '</div>';
    }

    function loadMatrix() {
        var key = pr.court;
        if (!key) return;
        var draw = function () {
            var box = pr.host && pr.host.querySelector('.ha-slotlist');
            if (!box || pr.court !== key) return;
            var day = (pr.matrix[key].matrix || {})[pr.day];
            var slots = day ? day.slots : [];
            box.innerHTML = slots.length ? slots.map(function (s, i) {
                var end = (s.hour + 1 < 10 ? '0' : '') + (s.hour + 1) + ':00';
                return '<button type="button" class="ha-slotrow" data-slot="' + i + '"><span class="ha-slothr">' + s.hour + ':00</span>'
                    + '<span class="ha-grow"><b>' + esc(s.time_label) + ' - ' + end + '</b>'
                    + (s.rule_name ? '<em>' + esc(s.rule_name) + '</em>' : '')
                    + '<small>' + (s.rate !== s.base_rate ? 'Base: ' + whole(s.base_rate) : 'Standard Rack Rate') + '</small></span>'
                    + '<strong class="ha-ratepill is-' + esc(s.tag || 'standard') + '">' + whole(s.rate) + '</strong></button>';
            }).join('') : empty('No slots priced for this day.');
        };
        if (pr.matrix[key]) return draw();
        A.api(prPath('matrix'), { branch: false, court_id: key, weekday: pr.day }).then(function (r) { pr.matrix[key] = r; draw(); }, function () {
            var box = pr.host && pr.host.querySelector('.ha-slotlist');
            if (box) box.innerHTML = empty('Couldn’t load the price grid. Pull to refresh.');
        });
    }

    function slotDialog(s) {
        var d = dialog('<b class="ha-dlg-title">Slot Rate: ' + esc(s.time_label) + ' (' + cap(pr.day) + ')</b>'
            + '<p class="ha-slotrate">Effective Rate: ' + whole(s.rate) + '</p><p class="ha-dlg-text">Base Rack Rate: ' + whole(s.base_rate) + '</p>'
            + (s.rule_name ? '<p class="ha-dlg-text is-blue"><b>Active Rule: ' + esc(s.rule_name) + '</b></p>' : '')
            + (s.is_peak ? '<p class="ha-dlg-text is-red">Peak hour modifier is applied.</p>' : '')
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Close</button><button type="button" class="ha-dlg-btn is-main" data-custom>Set Custom Rule</button></div>');
        d.querySelector('[data-custom]').addEventListener('click', function () {
            d.close();
            var end = (s.hour + 1 < 10 ? '0' : '') + (s.hour + 1) + ':00';
            ruleEditor({ court: pr.court, weekdays: [pr.day], start: s.time_label, end: end === '24:00' ? '23:59' : end, amount: s.rate });
        });
    }

    function modeLabel(r) {
        var a = parseFloat(r.amount) || 0;
        if (r.pricing_mode === 'absolute') return [whole(a) + ' Flat', 'is-standard'];
        if (r.pricing_mode === 'delta') return a >= 0 ? ['+' + whole(a) + ' Surge', 'is-peak'] : ['-' + whole(-a) + ' Off', 'is-discount'];
        return a >= 0 ? ['+' + Math.round(a) + '% Surge', 'is-peak'] : [Math.round(a) + '% Off', 'is-discount'];
    }

    function ruleCards() {
        var q = pr.q.trim().toLowerCase();
        var rows = pr.rules.filter(function (r) { return !q || String(r.name).toLowerCase().indexOf(q) >= 0 || String(r.court_name).toLowerCase().indexOf(q) >= 0; });
        if (!pr.rules.length) return '<div class="ha-prempty"><b>No pricing rules configured</b><small>Create dynamic rules for weekend rush or off-peak discounts</small><button type="button" class="ha-regbtn is-blue" data-newrule>Create First Rule</button></div>';
        if (!rows.length) return empty('No rule matches "' + pr.q.trim() + '".');
        return rows.map(function (r) {
            var ml = modeLabel(r), wd = r.weekdays || [];
            return '<div class="ha-rulecard' + (r.is_active ? '' : ' is-off') + '"><div class="ha-row"><span class="ha-grow"><b>' + esc(r.name) + '</b><small>' + esc(r.court_name) + '</small></span>'
                + '<button type="button" class="ha-switch' + (r.is_active ? ' is-on' : '') + '" data-toggle="' + r.id + '" aria-label="Active"><i></i></button>'
                + iconBtn('data-delrule="' + r.id + '"', TRASH, 'Delete') + '</div><i class="ha-hr"></i>'
                + '<div class="ha-row ha-rulefoot"><span class="ha-grow"><small>ACTIVE WINDOW</small><b>' + esc(r.start_time) + ' - ' + esc(r.end_time) + '</b></span>'
                + '<strong class="ha-ratepill ' + ml[1] + '">' + ml[0] + '</strong></div>'
                + '<div class="ha-wdots">' + DAYS.map(function (d) { return '<span' + (!wd.length || wd.indexOf(d) >= 0 ? ' class="is-on"' : '') + '>' + d.charAt(0).toUpperCase() + '</span>'; }).join('') + '</div></div>';
        }).join('');
    }

    function rulesTab() {
        return '<div class="ha-row ha-gap8"><label class="ha-search ha-search-tool ha-grow">' + A.mat('search') + '<input type="search" data-rulesearch placeholder="Search pricing rules..." value="' + esc(pr.q) + '"></label>'
            + '<button type="button" class="ha-regbtn is-blue ha-newbtn" data-newrule>New</button></div><div class="ha-rulelist">' + ruleCards() + '</div>';
    }

    function splitTab() {
        var t = pr.tree || {}, comp = t.composite_courts || [], solo = t.standalone_courts || [];
        return '<small class="ha-caps is-purple">TURF PARTITION &amp; SPLIT MANAGER</small>'
            + comp.map(function (c) {
                var kids = c.children || [];
                var sum = kids.reduce(function (s, k) { return s + (parseFloat(k.price) || 0); }, 0), up = sum - (parseFloat(c.price) || 0);
                return '<div class="ha-courtcard is-composite"><div class="ha-row"><span class="ha-grow"><b>' + esc(c.name) + '</b><em class="ha-splitbadge">' + kids.length + '-WAY ' + esc(String(c.split_type || '').toUpperCase()) + '</em></span>'
                    + '<button type="button" class="ha-regbtn is-line ha-minibtn" data-merge="' + c.id + '">Merge</button></div>'
                    + '<p class="ha-opssub">Full Arena: ' + whole(c.price) + '/hr · Combined Partitions: ' + whole(sum) + '/hr</p>'
                    + (up > 0 && c.price > 0 ? '<p class="ha-uplift">Partitioning earns +' + whole(up) + '/hr (+' + Math.round(up / c.price * 100) + '%) more when booked separately.</p>' : '')
                    + kids.map(function (k) { return '<div class="ha-row ha-childrow"><span class="ha-grow">' + esc(k.name) + (k.partition_label ? ' <small>· ' + esc(k.partition_label) + '</small>' : '') + '</span><b>' + whole(k.price) + '/hr</b></div>'; }).join('') + '</div>';
            }).join('')
            + solo.map(function (c) {
                return '<div class="ha-courtcard"><div class="ha-row"><span class="ha-grow"><b>' + esc(c.name) + '</b><small>' + whole(c.price) + '/hr · Standalone' + (c.kind ? ' ' + esc(c.kind) : '') + '</small></span>'
                    + '<button type="button" class="ha-regbtn is-ink ha-minibtn" data-split="' + c.id + '">Split Court</button></div></div>';
            }).join('')
            + (!comp.length && !solo.length ? empty('No courts yet.') : '');
    }

    function yieldTab() {
        var recs = (pr.dash && pr.dash.top_recommendations) || [];
        return '<section class="ha-opscard ha-row ha-gap10"><span class="ha-yieldic">✦</span><span class="ha-grow"><b class="ha-opstitle">SMART REVENUE RECOVERY</b><p class="ha-opssub">Real rupee yield opportunities based on your last 30-day occupancy</p></span></section>'
            + (recs.length ? recs.map(function (r, i) {
                return '<div class="ha-yieldcard"><div class="ha-row"><span class="ha-grow"><b>' + esc(r.title) + '</b><small>' + esc(r.day_of_week) + ' · ' + esc(r.time_window) + '</small></span><em>+' + whole(r.projected_monthly_uplift) + '/mo</em></div>'
                    + '<p>' + esc(r.rationale) + '</p><i class="ha-hr"></i><div class="ha-row"><span class="ha-grow ha-occ">Occupancy: <b>' + Math.round(r.current_occupancy_rate) + '%</b></span>'
                    + '<button type="button" class="ha-regbtn is-green ha-minibtn" data-applyrec="' + i + '">Apply Rule</button></div></div>';
            }).join('') : '<div class="ha-prempty"><b>Pricing is fully optimized!</b><small>No dead slots or high-surge anomalies detected right now.</small></div>');
    }

    function ruleEditor(pre) {
        pre = pre || {};
        var st = { court: pre.court || null, days: pre.weekdays || [], mode: 'absolute' };
        var d = dialog('<b class="ha-dlg-title">Create Pricing Rule</b>'
            + '<b class="ha-dlg-sub ha-caps">RULE DETAILS</b>' + field('name', 'Rule Name *', 'placeholder="e.g. Friday Prime Rush"')
            + '<b class="ha-dlg-sub ha-caps">Apply to Court</b>' + chips('court', [['', 'All Courts']].concat(pr.courts.map(function (c) { return [String(c.id), c.name]; })), st.court ? String(st.court) : '')
            + '<b class="ha-dlg-sub ha-caps">SCHEDULE &amp; WINDOW</b>' + chips('days', DAYS.map(function (x) { return [x, x.slice(0, 3).toUpperCase()]; }), st.days)
            + '<div class="ha-row ha-gap8">' + field('start', 'Start Time (HH:mm)', 'inputmode="numeric" placeholder="18:00"', pre.start || '') + field('end', 'End Time (HH:mm)', 'inputmode="numeric" placeholder="22:00"', pre.end || '') + '</div>'
            + '<b class="ha-dlg-sub ha-caps">PRICING CALCULATION</b>' + chips('mode', MODES, st.mode)
            + field('amount', '<span data-amountlabel>Rate (₹/hr)</span>', 'inputmode="decimal"', pre.amount || '')
            + '<div class="ha-row ha-gap8">' + field('min', 'Floor Price (₹)', 'inputmode="decimal"') + field('max', 'Ceiling Price (₹)', 'inputmode="decimal"') + '</div>'
            + '<p class="ha-err" hidden></p><button type="button" class="ha-regbtn is-blue ha-wide" data-save>Save Pricing Rule</button><button type="button" class="ha-textbtn" data-close>Cancel</button>');
        bindChips(d, 'court', false, function (v) { st.court = v ? +v : null; });
        bindChips(d, 'days', true, function (v) { st.days = v; });
        bindChips(d, 'mode', false, function (v) {
            st.mode = v;
            d.querySelector('[data-amountlabel]').textContent = v === 'absolute' ? 'Rate (₹/hr)' : v === 'delta' ? 'Change in ₹ (use − for a discount)' : 'Change in % (use − for a discount)';
        });
        d.querySelector('[data-save]').addEventListener('click', function () {
            var b = this, t = /^\d{1,2}:\d{2}$/;
            if (!val(d, 'name')) return showError(d, { message: 'Give the rule a name.' });
            if (!t.test(val(d, 'start')) || !t.test(val(d, 'end'))) return showError(d, { message: 'Times look like 18:00.' });
            if (num(d, 'amount') == null || isNaN(num(d, 'amount'))) return showError(d, { message: 'Enter the amount.' });
            b.disabled = true;
            A.apiSend('POST', prPath('rules'), {
                venue_court_id: st.court, name: val(d, 'name'), rule_type: 'time_of_day', weekdays: st.days.length ? st.days : DAYS.slice(),
                start_time: val(d, 'start'), end_time: val(d, 'end'), pricing_mode: st.mode, amount: num(d, 'amount'),
                min_price: num(d, 'min'), max_price: num(d, 'max'), is_active: true,
            }).then(function () { d.close(); A.toast('Pricing rule saved'); pr.tab = 1; loadPricing(); }, function (err) { showError(d, err); b.disabled = false; });
        });
    }

    function splitDialog(court) {
        var type = 'half', n = 2;
        var d = dialog('<div class="ha-splitform"></div>');
        var box = d.querySelector('.ha-splitform');
        function render() {
            var keep = [];
            // Names survive a change of split type; prices are re-split for the new count.
            box.querySelectorAll('[data-pn]').forEach(function (el, i) { keep[i] = el.value; });
            var share = Math.round((parseFloat(court.price) || 0) / n * 1.2) || '';
            var html = '<b class="ha-dlg-title">Split \'' + esc(court.name) + '\'</b><p class="ha-dlg-text">Partition full court into independent sub-courts:</p>'
                + chips('type', [['half', '2 Halves'], ['third', '3 Nets'], ['quarter', '4 Zones']], type);
            for (var i = 0; i < n; i++) {
                var k = { n: keep[i] || court.name + ' ' + String.fromCharCode(65 + i), p: share };
                html += '<div class="ha-row ha-gap8"><label class="ha-field ha-grow"><span>Sub-court ' + (i + 1) + ' Name</span><input data-pn="' + i + '" value="' + esc(k.n) + '" autocomplete="off"></label>'
                    + '<label class="ha-field ha-w110"><span>Price (₹/hr)</span><input data-pp="' + i + '" inputmode="decimal" value="' + esc(k.p) + '"></label></div>';
            }
            box.innerHTML = html + '<p class="ha-err" hidden></p><div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-main" data-save>Confirm Split</button></div>';
            bindChips(box, 'type', false, function (v) { type = v; n = { half: 2, third: 3, quarter: 4 }[v]; render(); });
        }
        box.addEventListener('click', function (e) {
            if (!e.target.closest('[data-save]')) return;
            var parts = [];
            for (var i = 0; i < n; i++) {
                var nm = box.querySelector('[data-pn="' + i + '"]').value.trim();
                if (!nm) return showError(box, { message: 'Name every sub-court.' });
                parts.push({ name: nm, label: 'Partition ' + (i + 1), price: parseFloat(box.querySelector('[data-pp="' + i + '"]').value) || 0 });
            }
            e.target.disabled = true;
            A.apiSend('POST', prPath('hierarchy/split'), { court_id: court.id, split_type: type, partitions: parts })
                .then(function () { d.close(); A.toast('Court split into ' + n); loadPricing(); }, function (err) { showError(box, err); e.target.disabled = false; });
        });
        render();
    }

    A.register('pricing', {
        tool: true,
        enter: function (host) {
            pr.host = host;
            host.innerHTML = header('Dynamic Pricing & Courts', 'Rate matrix, rules & court partition manager') + '<div class="ha-toolbody">' + loading() + '</div>';
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-refresh]')) { loadPricing(); return; }
                if ((b = e.target.closest('[data-ptab]'))) { pr.tab = +b.dataset.ptab; renderPricing(); return; }
                if (e.target.closest('[data-newrule]')) { ruleEditor({ court: pr.tab === 0 ? pr.court : null, weekdays: pr.tab === 0 ? [pr.day] : [] }); return; }
                if ((b = e.target.closest('[data-chips="court"] [data-v]'))) {
                    pr.court = +b.dataset.v;
                    b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                    host.querySelector('.ha-slotlist').innerHTML = loading();
                    loadMatrix();
                    return;
                }
                if ((b = e.target.closest('[data-day]'))) {
                    pr.day = b.dataset.day;
                    b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                    loadMatrix();
                    return;
                }
                if ((b = e.target.closest('[data-slot]'))) { slotDialog(pr.matrix[pr.court].matrix[pr.day].slots[+b.dataset.slot]); return; }
                if ((b = e.target.closest('[data-toggle]'))) {
                    b.classList.toggle('is-on');
                    A.apiSend('POST', prPath('rules/' + b.dataset.toggle + '/toggle')).then(loadPricing, function (err) { b.classList.toggle('is-on'); A.toast(err.message); });
                    return;
                }
                if ((b = e.target.closest('[data-delrule]'))) {
                    var id = b.dataset.delrule;
                    confirmBox('Delete this rule?', 'Slots it priced go back to their base rate.', 'Delete', function () {
                        A.apiSend('DELETE', prPath('rules/' + id)).then(function () { A.toast('Rule deleted'); loadPricing(); }, function (err) { A.toast(err.message); });
                    });
                    return;
                }
                if ((b = e.target.closest('[data-split]'))) {
                    var c = ((pr.tree && pr.tree.standalone_courts) || []).filter(function (x) { return x.id === +b.dataset.split; })[0];
                    if (c) splitDialog(c);
                    return;
                }
                if ((b = e.target.closest('[data-merge]'))) {
                    var mc = ((pr.tree && pr.tree.composite_courts) || []).filter(function (x) { return x.id === +b.dataset.merge; })[0];
                    confirmBox('Merge \'' + (mc ? mc.name : 'court') + '\'?', 'Its sub-courts are removed and it books as one full court again.', 'Confirm Merge', function () {
                        A.apiSend('POST', prPath('hierarchy/merge'), { court_id: +b.dataset.merge }).then(function () { A.toast('Court merged'); loadPricing(); }, function (err) { A.toast(err.message); });
                    });
                    return;
                }
                if ((b = e.target.closest('[data-applyrec]'))) {
                    var rec = pr.dash.top_recommendations[+b.dataset.applyrec];
                    b.disabled = true;
                    A.apiSend('POST', prPath('recommendations/apply'), rec.rule_payload).then(function () { A.toast('Rule applied'); pr.tab = 1; loadPricing(); }, function (err) { b.disabled = false; A.toast(err.message); });
                }
            };
            loadPricing();
        },
        leave: function () { pr.host = null; pr.venue = null; pr.matrix = {}; },
    });

    /* ============================================================ STANDING SLOTS === */

    var ST_STATUS = [['all', 'All'], ['active', 'Active'], ['at_risk', 'At Risk'], ['paused', 'Paused'], ['terminated', 'Terminated']];
    var PAY_TYPES = [['session_advance', 'Session'], ['monthly_fee', 'Monthly'], ['deposit', 'Deposit'], ['penalty', 'Penalty'], ['refund', 'Refund']];
    var METHODS = [['cash', 'Cash'], ['upi', 'UPI'], ['card', 'Card']];
    var sc = { host: null, venue: null, courts: [], dash: null, list: null, total: 0, tab: 0, status: 'all', q: '', timer: null, view: 'main', detail: null, dtab: 0 };

    function scPath(p) { return 'venues/' + sc.venue.id + '/standing-contracts' + (p ? '/' + p : ''); }

    function loadStanding() {
        if (!sc.host) return;
        (sc.venue ? Promise.resolve(sc.venue) : toolVenue()).then(function (v) {
            if (!sc.host) return;
            if (!v) { sc.host.innerHTML = header('Standing Slots & Contracts') + empty('This needs a venue. Ask Haraan to add your venue first.'); return; }
            sc.venue = v;
            return Promise.all([venueCourts(v.id), A.api(scPath('dashboard'), { branch: false }), A.api(scPath(''), { branch: false, status: sc.status, search: sc.q.trim() || undefined })]).then(function (r) {
                if (!sc.host) return;
                sc.courts = r[0];
                sc.dash = r[1];
                sc.list = list(r[2]);
                sc.total = r[2].total != null ? r[2].total : sc.list.length;
                if (sc.view === 'main') renderStanding();
            });
        }).catch(function () { if (sc.host) failed(sc.host, 'Standing Slots & Contracts', loadStanding); });
    }

    function reloadContracts() {
        var seq = (sc.seq = (sc.seq || 0) + 1);
        A.api(scPath(''), { branch: false, status: sc.status, search: sc.q.trim() || undefined }).then(function (r) {
            if (seq !== sc.seq || !sc.host || sc.view !== 'main') return;
            sc.list = list(r);
            sc.total = r.total != null ? r.total : sc.list.length;
            var box = sc.host.querySelector('.ha-contractlist');
            if (box) box.innerHTML = contractCards();
        });
    }

    function statusPill(s, risk) {
        var k = risk && s === 'active' ? 'at_risk' : s;
        return '<em class="ha-cstatus is-' + esc(k) + '">' + esc(words(k)) + '</em>';
    }

    function renderStanding() {
        var host = sc.host;
        if (!host) return;
        sc.view = 'main';
        var m = (sc.dash && sc.dash.metrics) || {};
        host.innerHTML = header('Standing Slots & Contracts', 'Recurring groups & academy batches', iconBtn('data-refresh', REFRESH, 'Refresh'))
            + '<div class="ha-sttabs"><button type="button" data-stab="0"' + (sc.tab === 0 ? ' class="is-on"' : '') + '>Dashboard</button><button type="button" data-stab="1"' + (sc.tab === 1 ? ' class="is-on"' : '') + '>All Contracts (' + (m.total_contracts || 0) + ')</button></div>'
            + '<div class="ha-toolbody ha-stbody">' + (sc.tab === 0 ? standingDash() : standingList()) + '</div>';
        var s = host.querySelector('[data-csearch]');
        if (s) s.addEventListener('input', function () { sc.q = s.value; clearTimeout(sc.timer); sc.timer = setTimeout(reloadContracts, 300); });
    }

    function standingDash() {
        var d = sc.dash || {}, m = d.metrics || {}, alerts = d.at_risk_alerts || [], today = d.today_sessions || [];
        return '<section class="ha-mrrhero"><div class="ha-row"><small>MONTHLY RECURRING REVENUE</small><span class="ha-grow"></span><em>STABLE TURNOVER</em></div>'
            + '<b>' + whole(m.monthly_recurring_revenue) + '</b><p>Guaranteed baseline income across ' + (m.active_contracts || 0) + ' active contracts</p>'
            + '<div class="ha-mrrstats"><span><b>' + (m.active_contracts || 0) + '</b><small>Active</small></span><span><b>' + (m.at_risk_contracts || 0) + '</b><small>At risk</small></span><span><b>' + (m.paused_contracts || 0) + '</b><small>Paused</small></span><span><b>' + Math.round(m.average_attendance_rate || 0) + '%</b><small>Attendance</small></span></div></section>'
            + '<div class="ha-row ha-gap8"><button type="button" class="ha-regbtn is-blue" data-newcontract>+ New Contract</button><button type="button" class="ha-regbtn is-line" data-viewall>View All (' + (m.total_contracts || 0) + ')</button></div>'
            + (alerts.length ? '<section class="ha-riskcard"><b>CHURN RISK DETECTED (' + alerts.length + ')</b><p>Customers with 2+ consecutive missed sessions or low attendance. Reach out to prevent cancellations.</p>'
                + alerts.map(function (a) {
                    return '<button type="button" class="ha-riskrow" data-contract="' + a.id + '"><span class="ha-grow"><b>' + esc(a.customer_name) + '</b><small>' + esc(a.weekday) + ' · ' + esc(a.time) + ' · ' + esc(a.customer_phone) + '</small></span>'
                        + '<em>' + (a.missed_streak || 0) + ' missed · ' + Math.round(a.attendance_rate || 0) + '%</em></button>';
                }).join('') + '</section>' : '')
            + '<small class="ha-caps">TODAY’S RECURRING SESSIONS (' + today.length + ')</small>'
            + (today.length ? today.map(function (s) {
                var done = s.attendance_status !== 'scheduled';
                return '<div class="ha-todayrow"><button type="button" class="ha-grow ha-plain" data-contract="' + s.contract_id + '"><b>' + esc(s.customer_name) + '</b><small>' + esc(s.court_name) + ' · ' + esc(s.start_time) + ' - ' + esc(s.end_time) + '</small></button>'
                    + (done ? statusPill(s.attendance_status) : '<span class="ha-row ha-gap6"><button type="button" class="ha-attbtn is-present" data-att="present" data-c="' + s.contract_id + '" data-s="' + s.session_id + '">Present</button><button type="button" class="ha-attbtn is-absent" data-att="absent" data-c="' + s.contract_id + '" data-s="' + s.session_id + '">Absent</button></span>') + '</div>';
            }).join('') : '<div class="ha-note">No standing slots scheduled for today</div>');
    }

    function contractCards() {
        var rows = sc.list || [];
        if (!rows.length) return '<div class="ha-prempty"><b>No standing contracts found</b><small>Create a contract for weekly recurring clubs or academies</small><button type="button" class="ha-regbtn is-blue" data-newcontract>Create Contract</button></div>';
        return rows.map(function (c) {
            var ns = c.next_session;
            return '<button type="button" class="ha-contractcard" data-contract="' + c.id + '"><div class="ha-row"><span class="ha-grow"><b>' + esc(c.customer_name) + '</b><small>' + esc(c.customer_phone) + '</small></span>' + statusPill(c.status, c.is_at_risk) + '</div>'
                + '<div class="ha-cfacts"><span><small>DAY &amp; TIME</small><b>' + esc(String(c.day_of_week).slice(0, 3)) + ' ' + esc(c.start_time) + '-' + esc(c.end_time) + '</b></span><span><small>COURT</small><b>' + esc(c.court_name) + '</b></span><span><small>MONTHLY VALUE</small><b class="is-green">' + whole(c.monthly_value) + '</b></span></div>'
                + '<div class="ha-row ha-cfoot"><span class="ha-grow">Attendance: <b class="' + (c.attendance_rate < 60 ? 'is-red' : 'is-green') + '">' + Math.round(c.attendance_rate) + '%</b></span>'
                + (ns ? '<span>Next: ' + esc(prettyDay(ns.date)) + ' (' + esc(ns.time) + ')</span>' : '') + '</div></button>';
        }).join('');
    }

    function standingList() {
        return '<label class="ha-search ha-search-tool">' + A.mat('search') + '<input type="search" data-csearch placeholder="Search by customer name or phone..." value="' + esc(sc.q) + '"></label>'
            + chips('status', ST_STATUS, sc.status, 'ha-scrollchips') + '<div class="ha-contractlist">' + contractCards() + '</div>';
    }

    /* ----- create ----- */

    function createContract() {
        var host = sc.host;
        sc.view = 'create';
        var st = { court: sc.courts[0] ? sc.courts[0].id : null, day: todayKey(), report: null, autoSkip: false };
        host.innerHTML = header('Create Standing Contract', 'Weekly recurring booking engine', '', true)
            + '<div class="ha-toolbody ha-createbody"><section class="ha-formcard"><small class="ha-caps">CUSTOMER &amp; GROUP DETAILS</small>'
            + field('name', 'Customer / Club Name *', 'placeholder="e.g. Hyderabad Smashers Club"') + field('phone', 'Customer Phone Number *', 'inputmode="tel" placeholder="9876543210" maxlength="10"') + '</section>'
            + '<section class="ha-formcard"><small class="ha-caps">COURT &amp; SCHEDULE</small><b class="ha-dlg-sub">Select Court</b>'
            + (sc.courts.length ? chips('court', sc.courts.map(function (c) { return [String(c.id), c.name]; }), String(st.court)) : '<p class="ha-dlg-text">Add a court to your venue first.</p>')
            + '<b class="ha-dlg-sub">Recurring Day of Week</b>' + chips('day', DAYS.map(function (d) { return [d, d.slice(0, 3).toUpperCase()]; }), st.day)
            + '<div class="ha-row ha-gap8">' + field('start', 'Start (HH:mm)', 'inputmode="numeric"', '18:00') + field('end', 'End (HH:mm)', 'inputmode="numeric"', '19:00') + '</div></section>'
            + '<section class="ha-formcard"><small class="ha-caps">FINANCIALS &amp; SECURITY DEPOSIT</small>'
            + field('price', 'Price/Session (₹) *', 'inputmode="decimal"', (sc.courts[0] && sc.courts[0].price) || '')
            + '<div class="ha-row ha-gap8">' + field('deposit', 'Security Deposit (₹)', 'inputmode="decimal"', '0') + field('advance', 'Advance Paid (₹)', 'inputmode="decimal"', '0') + '</div>'
            + '<label class="ha-field"><span>Active From (Date)</span><input data-k="from" type="date" value="' + A.ymd(new Date()) + '"></label></section>'
            + '<section class="ha-formcard"><div class="ha-row"><small class="ha-caps ha-grow">CONFLICT ENGINE &amp; PREVIEW</small><button type="button" class="ha-regbtn is-line ha-minibtn" data-check>Check Conflicts</button></div><div class="ha-conflicts"></div></section>'
            + '<p class="ha-err" hidden></p><button type="button" class="ha-regbtn is-blue ha-wide ha-tallbtn" data-create>Create Contract &amp; Materialize Schedule</button></div>';
        var body = host.querySelector('.ha-createbody');
        bindChips(body, 'court', false, function (v) {
            st.court = +v;
            var c = sc.courts.filter(function (x) { return x.id === st.court; })[0];
            if (c && c.price) body.querySelector('[data-k="price"]').value = c.price;
            st.report = null; drawReport();
        });
        bindChips(body, 'day', false, function (v) { st.day = v; st.report = null; drawReport(); });
        body.addEventListener('input', function (e) {
            if (e.target.dataset.k === 'phone') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
            if (['start', 'end', 'from'].indexOf(e.target.dataset.k) >= 0) { st.report = null; drawReport(); }
        });
        function sched() { return { court_id: st.court, day_of_week: st.day, start_time: val(body, 'start'), end_time: val(body, 'end'), active_from: val(body, 'from') }; }
        function drawReport() {
            var r = st.report, box = body.querySelector('.ha-conflicts');
            if (!r) { box.innerHTML = '<p class="ha-dlg-text">Checks the next 12 weeks against bookings and blocks before you commit.</p>'; return; }
            box.innerHTML = r.is_clear
                ? '<div class="ha-allclear">100% Clear! All ' + r.total_sessions_checked + ' sessions are available.</div>'
                : '<div class="ha-conflictbox"><b>' + r.conflicting_sessions_count + ' conflicting dates detected</b>' + (r.conflicts || []).map(function (c) { return '<small>• ' + esc(prettyDay(c.date)) + ': ' + esc(words((c.conflict && c.conflict.conflict_type) || 'booked')) + '</small>'; }).join('')
                    + '<label class="ha-check"><input type="checkbox" data-autoskip' + (st.autoSkip ? ' checked' : '') + '><i></i>Auto-skip conflicting dates when creating</label></div>';
            var cb = box.querySelector('[data-autoskip]');
            if (cb) cb.addEventListener('change', function () { st.autoSkip = cb.checked; });
        }
        drawReport();
        body.addEventListener('click', function (e) {
            var b;
            if ((b = e.target.closest('[data-check]'))) {
                var s = sched();
                if (!/^\d{2}:\d{2}$/.test(s.start_time) || !/^\d{2}:\d{2}$/.test(s.end_time)) return showError(body, { message: 'Times look like 18:00.' });
                b.disabled = true; b.textContent = 'Checking…';
                A.apiSend('POST', scPath('check-conflicts'), s).then(function (r) { st.report = r; drawReport(); }, function (err) { showError(body, err); })
                    .finally(function () { b.disabled = false; b.textContent = 'Check Conflicts'; });
                return;
            }
            if ((b = e.target.closest('[data-create]'))) {
                var p = sched();
                if (!val(body, 'name')) return showError(body, { message: 'Enter the customer or club name.' });
                if (val(body, 'phone').length !== 10) return showError(body, { message: 'Enter a 10-digit phone number.' });
                if (!st.court) return showError(body, { message: 'Pick a court.' });
                if (num(body, 'price') == null) return showError(body, { message: 'Enter the price per session.' });
                p.customer_name = val(body, 'name');
                p.customer_phone = val(body, 'phone');
                p.price_per_session = num(body, 'price');
                p.security_deposit = num(body, 'deposit') || 0;
                p.advance_paid = num(body, 'advance') || 0;
                p.auto_skip_conflicts = st.autoSkip;
                p.payment_method = 'cash';
                b.disabled = true; b.textContent = 'Materializing Schedule…';
                A.apiSend('POST', scPath(''), p).then(function () {
                    A.toast('Standing contract created');
                    sc.tab = 1; sc.view = 'main';
                    loadStanding();
                }, function (err) {
                    b.disabled = false; b.textContent = 'Create Contract & Materialize Schedule';
                    showError(body, err.status === 409 ? { message: err.message + ' Tick auto-skip to create anyway.' } : err);
                    if (err.status === 409 && !st.report) body.querySelector('[data-check]').click();
                });
            }
        });
    }

    /* ----- detail ----- */

    function openContract(id) {
        sc.view = 'detail';
        sc.dtab = 0;
        sc.host.innerHTML = header('Contract', '', '', true) + '<div class="ha-toolbody">' + loading() + '</div>';
        loadContract(id);
    }

    function loadContract(id) {
        A.api(scPath(String(id)), { branch: false }).then(function (r) {
            if (!sc.host || sc.view !== 'detail') return;
            sc.detail = r.contract || r;
            renderContract();
        }, function () { if (sc.host && sc.view === 'detail') sc.host.querySelector('.ha-toolbody').innerHTML = empty('Couldn’t load this contract.'); });
    }

    function renderContract() {
        var c = sc.detail, host = sc.host;
        var sess = c.sessions || [], pays = c.payments || [], logs = c.logs || [];
        var tabBody;
        if (sc.dtab === 0) {
            tabBody = sess.length ? sess.map(function (s) {
                var open = s.attendance_status === 'scheduled';
                return '<div class="ha-sessrow"><div class="ha-row"><span class="ha-grow"><b>' + esc(prettyDay(s.session_date)) + '</b><small>' + esc(s.start_time) + '-' + esc(s.end_time) + ' · ' + esc(s.court_name) + '</small></span>' + statusPill(s.attendance_status) + '</div>'
                    + (open ? '<div class="ha-row ha-gap6 ha-sessacts"><button type="button" class="ha-attbtn is-present" data-datt="present" data-s="' + s.id + '">Present</button><button type="button" class="ha-attbtn is-absent" data-datt="absent" data-s="' + s.id + '">Absent</button><button type="button" class="ha-attbtn" data-skip="' + esc(s.session_date) + '">Skip Date</button></div>' : '') + '</div>';
            }).join('') : empty('No sessions scheduled.');
        } else if (sc.dtab === 1) {
            tabBody = '<button type="button" class="ha-regbtn is-line ha-wide" data-pay>Record Payment</button>' + (pays.length ? pays.map(function (p) {
                return '<div class="ha-sessrow ha-row"><span class="ha-grow"><b>' + esc(words(p.payment_type)) + '</b><small>via ' + esc(String(p.method).toUpperCase()) + ' · ' + esc(prettyDay(new Date(p.created_at))) + (p.notes ? ' · ' + esc(p.notes) : '') + '</small></span><strong class="is-green">' + whole(p.amount) + '</strong></div>';
            }).join('') : empty('No payments recorded yet.'));
        } else {
            tabBody = logs.length ? logs.map(function (l) {
                return '<div class="ha-sessrow"><b>' + esc(words(l.action)) + '</b><small>' + esc(l.actor_name) + ' · ' + esc(prettyDay(new Date(l.created_at))) + '</small>' + (l.details ? '<p class="ha-logtext">' + esc(typeof l.details === 'string' ? l.details : JSON.stringify(l.details)) + '</p>' : '') + '</div>';
            }).join('') : empty('No activity yet.');
        }
        host.innerHTML = header(c.customer_name, c.day_of_week + ' · ' + c.start_time + '-' + c.end_time, iconBtn('data-cmenu', MORE, 'Actions'), true)
            + '<div class="ha-toolbody ha-stbody"><section class="ha-formcard"><div class="ha-row"><span class="ha-grow"><small class="ha-muted">Ph: ' + esc(c.customer_phone) + '</small></span>' + statusPill(c.status, c.is_at_risk) + '</div>'
            + (c.risk_reason ? '<p class="ha-riskline">' + esc(c.risk_reason) + '</p>' : '')
            + '<div class="ha-cfacts"><span><small>SCHEDULE</small><b>' + esc(String(c.day_of_week).slice(0, 3)) + ' ' + esc(c.start_time) + '-' + esc(c.end_time) + '</b></span><span><small>COURT</small><b>' + esc(c.court_name) + '</b></span><span><small>PRICE / SESSION</small><b class="is-green">' + whole(c.price_per_session) + '</b></span></div></section>'
            + '<section class="ha-moneyrow"><span><small>DEPOSIT HELD</small><b>' + whole(c.security_deposit) + '</b></span><span><small>ADVANCE PAID</small><b class="is-green">' + whole(c.advance_paid) + '</b></span><span><small>BALANCE DUE</small><b class="' + (c.balance_due > 0 ? 'is-red' : '') + '">' + whole(c.balance_due) + '</b></span></section>'
            + '<div class="ha-regtabs">' + ['Sessions (' + sess.length + ')', 'Payments (' + pays.length + ')', 'Audit (' + logs.length + ')'].map(function (t, i) { return '<button type="button" data-dtab="' + i + '"' + (i === sc.dtab ? ' class="is-on"' : '') + '>' + t + '</button>'; }).join('') + '</div>'
            + '<div class="ha-sesslist">' + tabBody + '</div></div>';
    }

    function contractAction(path, body, ok) {
        return A.apiSend('POST', scPath(sc.detail.id + '/' + path), body || {}).then(function () { A.toast(ok); loadContract(sc.detail.id); }, function (err) { A.toast(err.message || 'Couldn’t do that. Try again.'); });
    }

    function contractMenu() {
        var c = sc.detail, live = c.status !== 'terminated';
        var items = [['pay', 'Record Payment'], ['transfer', 'Transfer Court']];
        if (live) items.push(c.status === 'paused' ? ['resume', 'Resume Contract'] : ['pause', 'Pause Contract']);
        if (live) items.push(['terminate', 'Terminate Contract']);
        var s = A.sheet('<div class="ha-menulist">' + items.map(function (i) { return '<button type="button" data-m="' + i[0] + '"' + (i[0] === 'terminate' ? ' class="is-red"' : '') + '>' + i[1] + '</button>'; }).join('') + '</div>');
        s.addEventListener('click', function (e) {
            var b = e.target.closest('[data-m]');
            if (!b) return;
            s.close();
            var m = b.dataset.m;
            if (m === 'pay') paymentDialog();
            else if (m === 'transfer') transferDialog();
            else if (m === 'resume') contractAction('resume', {}, 'Contract resumed');
            else if (m === 'pause') confirmBox('Pause this contract?', 'Future sessions stop until you resume it.', 'Pause', function (r) { contractAction('pause', { reason: r || null }, 'Contract paused'); }, 'Reason (optional)');
            else if (m === 'terminate') confirmBox('Terminate this contract?', 'All future sessions are cancelled and their slots freed. This can’t be undone.', 'Terminate', function (r) { contractAction('terminate', { reason: r || null }, 'Contract terminated'); }, 'Reason (optional)');
        });
    }

    function paymentDialog() {
        var st = { type: 'session_advance', method: 'cash' };
        var d = dialog('<b class="ha-dlg-title">Record Contract Payment</b>' + field('amount', 'Amount (₹) *', 'inputmode="decimal"')
            + '<b class="ha-dlg-sub">Type</b>' + chips('type', PAY_TYPES, st.type) + '<b class="ha-dlg-sub">Method</b>' + chips('method', METHODS, st.method)
            + field('notes', 'Notes / UPI Reference') + '<p class="ha-err" hidden></p>'
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-main" data-save>Record</button></div>');
        bindChips(d, 'type', false, function (v) { st.type = v; });
        bindChips(d, 'method', false, function (v) { st.method = v; });
        d.querySelector('[data-save]').addEventListener('click', function () {
            var amt = num(d, 'amount');
            if (!(amt > 0)) return showError(d, { message: 'Enter the amount.' });
            var b = this;
            b.disabled = true;
            A.apiSend('POST', scPath(sc.detail.id + '/payment'), { amount: amt, payment_type: st.type, method: st.method, notes: val(d, 'notes') || null })
                .then(function () { d.close(); A.toast('Payment recorded'); sc.dtab = 1; loadContract(sc.detail.id); }, function (err) { showError(d, err); b.disabled = false; });
        });
    }

    function transferDialog() {
        var others = sc.courts.filter(function (x) { return x.id !== sc.detail.court_id; });
        if (!others.length) return A.toast('There’s no other court to move to.');
        var pick = others[0].id;
        var d = dialog('<b class="ha-dlg-title">Transfer to Another Court</b><p class="ha-dlg-text">Select new court for future sessions:</p>'
            + chips('court', others.map(function (c) { return [String(c.id), c.name]; }), String(pick))
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-main" data-save>Confirm Transfer</button></div>');
        bindChips(d, 'court', false, function (v) { pick = +v; });
        d.querySelector('[data-save]').addEventListener('click', function () { d.close(); contractAction('transfer-court', { court_id: pick }, 'Moved to the new court'); });
    }

    function skipDialog(date) {
        var d = dialog('<b class="ha-dlg-title">Skip Session (' + esc(prettyDay(date)) + ')</b><p class="ha-dlg-text">Skipping frees up the court on the Day Grid immediately.</p>'
            + field('reason', 'Reason (Holiday, Rain, Customer Request)')
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-main" data-save>Confirm Skip</button></div>');
        d.querySelector('[data-save]').addEventListener('click', function () { var r = val(d, 'reason'); d.close(); contractAction('skip-date', { date: date, reason: r || null }, 'Session skipped'); });
    }

    A.register('standing', {
        tool: true,
        enter: function (host) {
            sc.host = host;
            sc.view = 'main';
            host.innerHTML = header('Standing Slots & Contracts', 'Recurring groups & academy batches') + '<div class="ha-toolbody">' + loading() + '</div>';
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-subback]')) { sc.view = 'main'; renderStanding(); loadStanding(); return; }
                if (e.target.closest('[data-refresh]')) { loadStanding(); return; }
                if ((b = e.target.closest('[data-stab]'))) { sc.tab = +b.dataset.stab; renderStanding(); return; }
                if (e.target.closest('[data-viewall]')) { sc.tab = 1; renderStanding(); return; }
                if (e.target.closest('[data-newcontract]')) { createContract(); return; }
                if (sc.view === 'main' && (b = e.target.closest('[data-chips="status"] [data-v]'))) {
                    sc.status = b.dataset.v;
                    b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                    reloadContracts();
                    return;
                }
                if ((b = e.target.closest('[data-att]'))) {
                    b.parentNode.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
                    vibrate(b.dataset.att === 'present' ? 12 : 6);
                    A.apiSend('POST', scPath(b.dataset.c + '/attendance'), { session_id: +b.dataset.s, status: b.dataset.att }).then(loadStanding, function (err) { A.toast(err.message); loadStanding(); });
                    return;
                }
                if ((b = e.target.closest('[data-contract]'))) { openContract(b.dataset.contract); return; }
                if (sc.view !== 'detail') return;
                if (e.target.closest('[data-cmenu]')) { contractMenu(); return; }
                if ((b = e.target.closest('[data-dtab]'))) { sc.dtab = +b.dataset.dtab; renderContract(); return; }
                if (e.target.closest('[data-pay]')) { paymentDialog(); return; }
                if ((b = e.target.closest('[data-skip]'))) { skipDialog(b.dataset.skip); return; }
                if ((b = e.target.closest('[data-datt]'))) {
                    vibrate(b.dataset.datt === 'present' ? 12 : 6);
                    contractAction('attendance', { session_id: +b.dataset.s, status: b.dataset.datt }, b.dataset.datt === 'present' ? 'Marked present' : 'Marked absent');
                }
            };
            loadStanding();
        },
        leave: function () { clearTimeout(sc.timer); sc.host = null; sc.venue = null; sc.view = 'main'; },
    });

    /* ================================================================== PACKAGES === */

    var pk = { host: null };

    function loadPackages() {
        var host = pk.host;
        if (!host) return;
        A.api('packages').then(function (p) {
            if (!pk.host) return;
            var packs = list(p), holders = p.holders || [];
            host.innerHTML = header('Packages', '', iconBtn('data-newpack', PLUS, 'New package')) + '<div class="ha-toolbody">'
                + '<small class="ha-caps">WHAT YOU SELL</small>'
                + (packs.length ? packs.map(function (x, i) {
                    return '<button type="button" class="ha-packcard" data-sell="' + i + '"><span class="ha-grow"><b>' + esc(x.name) + '</b><small>' + x.sessions + ' sessions · ' + whole(x.per_session) + '/session · ' + (x.validity_days ? x.validity_days + 'd validity' : 'no expiry') + '</small></span>'
                        + '<span class="ha-packprice"><b>' + whole(x.price) + '</b><em>SELL</em></span></button>';
                }).join('') : '<div class="ha-note">No packages yet. Tap + to create one — e.g. 10 sessions for ₹4,000.</div>')
                + '<small class="ha-caps">ON A PASS (' + holders.length + ')</small>'
                + (holders.length ? holders.map(function (x) {
                    var frac = x.total > 0 ? Math.round(x.remaining / x.total * 100) : 0;
                    return '<div class="ha-holder' + (x.expired ? ' is-expired' : '') + '"><div class="ha-row"><span class="ha-grow"><b>' + esc(x.name) + '</b><small>' + esc(x.package) + ' · +91 ' + esc(x.phone) + '</small></span><strong>' + x.remaining + '/' + x.total + '</strong></div>'
                        + '<span class="ha-holdbar"><i style="width:' + frac + '%"></i></span>'
                        + (x.expires_at ? '<small class="ha-holdexp">' + (x.expired ? 'Expired ' : 'Valid until ') + esc(x.expires_at) + '</small>' : '') + '</div>';
                }).join('') : '<div class="ha-note">Nobody is on a package yet.</div>') + '</div>';
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-newpack]')) newPackage();
                else if ((b = e.target.closest('[data-sell]'))) sellPackage(packs[+b.dataset.sell]);
            };
        }, function () { if (pk.host) failed(pk.host, 'Packages', loadPackages); });
    }

    function newPackage() {
        var d = dialog('<b class="ha-dlg-title">New package</b><p class="ha-dlg-text">A prepaid bundle of sessions customers can buy.</p>'
            + field('name', 'Name', 'placeholder="10 Session Pass"')
            + '<div class="ha-row ha-gap8">' + field('price', 'Price ₹', 'inputmode="numeric"') + field('sessions', 'Sessions', 'inputmode="numeric"') + '</div>'
            + field('days', 'Validity in days (blank = never expires)', 'inputmode="numeric"')
            + '<p class="ha-pershot" hidden></p><p class="ha-err" hidden></p><button type="button" class="ha-bigcta" data-save disabled>Create package</button><button type="button" class="ha-textbtn" data-close>Cancel</button>');
        var save = d.querySelector('[data-save]'), per = d.querySelector('.ha-pershot');
        d.addEventListener('input', function (e) {
            if (e.target.dataset.k !== 'name') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 7);
            var p = parseInt(val(d, 'price'), 10) || 0, s = parseInt(val(d, 'sessions'), 10) || 0;
            var ok = val(d, 'name') && p > 0 && s > 0;
            save.disabled = !ok;
            per.hidden = !ok;
            if (ok) per.textContent = 'That’s ₹' + Math.floor(p / s) + ' per session.';
        });
        save.addEventListener('click', function () {
            save.disabled = true;
            A.apiSend('POST', 'packages', { name: val(d, 'name'), price: parseInt(val(d, 'price'), 10), sessions: parseInt(val(d, 'sessions'), 10), validityDays: val(d, 'days') ? parseInt(val(d, 'days'), 10) : null })
                .then(function () { d.close(); A.toast('Package created'); loadPackages(); }, function (err) { showError(d, err); save.disabled = false; });
        });
    }

    function sellPackage(p) {
        var d = dialog('<b class="ha-dlg-title">Sell ' + esc(p.name) + '</b><p class="ha-dlg-text">' + p.sessions + ' sessions · ' + whole(p.price) + '</p>'
            + field('name', 'Customer name') + field('phone', 'Phone', 'inputmode="tel" maxlength="10"')
            + '<p class="ha-dlg-text ha-small">The pass is tied to this number — it appears automatically when they next book.</p>'
            + '<p class="ha-err" hidden></p><button type="button" class="ha-bigcta" data-save disabled>Sell for ' + whole(p.price) + '</button><button type="button" class="ha-textbtn" data-close>Cancel</button>');
        var save = d.querySelector('[data-save]');
        d.addEventListener('input', function (e) {
            if (e.target.dataset.k === 'phone') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
            save.disabled = !(val(d, 'name') && val(d, 'phone').length === 10);
        });
        save.addEventListener('click', function () {
            save.disabled = true;
            var body = { customerPhone: val(d, 'phone'), customerName: val(d, 'name'), paymentMethod: 'cash' };
            if (A.cfg.branch) body.venueId = A.cfg.branch;
            A.apiSend('POST', 'packages/' + p.id + '/sell', body).then(function () { d.close(); A.toast('Pass sold to ' + body.customerName); loadPackages(); }, function (err) { showError(d, err); save.disabled = false; });
        });
    }

    A.register('packages', {
        tool: true,
        enter: function (host) { pk.host = host; host.innerHTML = header('Packages') + '<div class="ha-toolbody">' + loading() + '</div>'; loadPackages(); },
        leave: function () { pk.host = null; },
    });

    /* =================================================================== ACADEMY === */

    var WEEK = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var ac = { host: null, batches: [], batch: null, day: null };

    function loadAcademy() {
        var host = ac.host;
        if (!host) return;
        ac.batch = null;
        A.api('academy', { branch: false }).then(function (r) {
            if (!ac.host || ac.batch) return;
            ac.batches = list(r);
            host.innerHTML = header('Academy', '', iconBtn('data-newbatch', PLUS, 'New batch')) + '<div class="ha-toolbody">'
                + (ac.batches.length ? ac.batches.map(function (b, i) {
                    var sched = [b.coach, (b.days || []).join(', ') || null, b.start_time && b.end_time ? b.start_time + '–' + b.end_time : null].filter(Boolean).join(' · ') || 'No schedule set';
                    return '<button type="button" class="ha-batchcard" data-batch="' + i + '"><div class="ha-row"><span class="ha-grow"><span class="ha-row ha-gap6"><b>' + esc(b.name) + '</b>' + (b.runs_today ? '<em class="ha-todaytag">TODAY</em>' : '') + '</span><small>' + esc(sched) + '</small></span>'
                        + '<span class="ha-batchfee"><b>' + whole(b.monthly_fee) + '</b><small>per month</small></span></div>'
                        + '<div class="ha-row ha-gap8 ha-batchfoot"><em class="ha-studs">' + A.mat('groups') + b.students + (b.capacity ? '/' + b.capacity : '') + ' students</em>'
                        + (b.overdue > 0 ? '<em class="ha-feesdue"><i></i>' + b.overdue + ' fees due</em>' : '') + '<span class="ha-grow"></span>' + A.mat('chevron') + '</div></button>';
                }).join('') : empty('No coaching batches yet. Tap + to add one.')) + '</div>';
        }, function () { if (ac.host) failed(ac.host, 'Academy', loadAcademy); });
    }

    function openBatch(b) {
        ac.batch = b;
        ac.day = new Date();
        ac.host.innerHTML = header(b.name, '', iconBtn('data-enrol', PLUS, 'Enrol student'), true)
            + '<div class="ha-toolbody"><div class="ha-daynav"><button type="button" class="ha-ibtn" data-dayshift="-1" aria-label="Previous day">' + A.mat('chevronLeft') + '</button><b></b><button type="button" class="ha-ibtn" data-dayshift="1" aria-label="Next day">' + A.mat('chevron') + '</button></div><div class="ha-roster">' + loading() + '</div></div>';
        loadRoster();
    }

    function loadRoster() {
        var b = ac.batch, date = A.ymd(ac.day);
        if (!b || !ac.host) return;
        ac.host.querySelector('.ha-daynav b').textContent = prettyDay(ac.day);
        A.api('academy/' + b.id + '/roster', { branch: false, date: date }).then(function (r) {
            if (!ac.host || ac.batch !== b || A.ymd(ac.day) !== date) return;
            var studs = list(r), present = studs.filter(function (s) { return s.present; }).length;
            ac.host.querySelector('.ha-roster').innerHTML = (r.runs_today === false ? '<p class="ha-dlg-text ha-pad4">This batch doesn’t run on this day.</p>' : '')
                + (studs.length ? '<small class="ha-presentline">' + present + ' of ' + studs.length + ' present</small>' + studs.map(function (s) {
                    return '<div class="ha-student' + (s.present ? ' is-present' : '') + '"><span class="ha-cav">' + esc(String(s.name || '?').charAt(0).toUpperCase()) + '</span>'
                        + '<span class="ha-grow"><span class="ha-row ha-gap6"><b>' + esc(s.name) + '</b>' + (s.overdue ? '<em class="ha-feetag">FEE DUE</em>' : '') + '</span><small>' + (s.attended || 0) + ' classes' + (s.paid_until ? ' · paid to ' + esc(s.paid_until) : '') + '</small></span>'
                        + '<button type="button" class="ha-tick" data-tick="' + s.id + '" data-on="' + (s.present ? 1 : 0) + '" aria-label="Present">✓</button></div>';
                }).join('') : empty('No students enrolled yet. Tap + to add one.'));
        }, function () { if (ac.host && ac.batch === b) ac.host.querySelector('.ha-roster').innerHTML = empty('Couldn’t load the roster.'); });
    }

    function newBatch() {
        var days = [];
        var d = dialog('<b class="ha-dlg-title">New batch</b><p class="ha-dlg-text">A recurring coaching class.</p>'
            + field('name', 'Batch name', 'placeholder="Junior Badminton"') + field('coach', 'Coach')
            + '<b class="ha-dlg-sub ha-caps">DAYS</b><div class="ha-weekpick">' + WEEK.map(function (w) { return '<button type="button" data-w="' + w + '">' + w.charAt(0) + '</button>'; }).join('') + '</div>'
            + '<div class="ha-row ha-gap8">' + field('start', 'From', 'inputmode="numeric"', '06:00') + field('end', 'To', 'inputmode="numeric"', '07:00') + '</div>'
            + '<div class="ha-row ha-gap8">' + field('fee', 'Fee ₹/month', 'inputmode="numeric"') + field('cap', 'Capacity', 'inputmode="numeric"') + '</div>'
            + '<p class="ha-err" hidden></p><button type="button" class="ha-bigcta" data-save disabled>Create batch</button><button type="button" class="ha-textbtn" data-close>Cancel</button>');
        var save = d.querySelector('[data-save]');
        d.querySelector('.ha-weekpick').addEventListener('click', function (e) {
            var b = e.target.closest('[data-w]');
            if (!b) return;
            vibrate(4);
            b.classList.toggle('is-on');
            days = WEEK.filter(function (w) { var x = d.querySelector('[data-w="' + w + '"]'); return x.classList.contains('is-on'); });
        });
        d.addEventListener('input', function (e) {
            var k = e.target.dataset.k;
            if (k === 'fee') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 7);
            if (k === 'cap') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 3);
            if (k === 'start' || k === 'end') e.target.value = e.target.value.slice(0, 5);
            save.disabled = !(val(d, 'name') && val(d, 'fee') !== '');
        });
        save.addEventListener('click', function () {
            save.disabled = true;
            A.apiSend('POST', 'academy', { name: val(d, 'name'), coachName: val(d, 'coach') || null, days: days, startTime: val(d, 'start') || null, endTime: val(d, 'end') || null, monthlyFee: parseInt(val(d, 'fee'), 10), capacity: val(d, 'cap') ? parseInt(val(d, 'cap'), 10) : null })
                .then(function () { d.close(); A.toast('Batch created'); loadAcademy(); }, function (err) { showError(d, err); save.disabled = false; });
        });
    }

    function enrolStudent() {
        var b = ac.batch, months = 1;
        var d = dialog('<b class="ha-dlg-title">Enrol student</b><p class="ha-dlg-text">Already enrolled? This extends their fees instead of adding them twice.</p>'
            + field('name', 'Student name') + field('phone', 'Phone', 'inputmode="tel" maxlength="10"')
            + '<b class="ha-dlg-sub ha-caps">MONTHS PAID</b><div class="ha-row ha-gap8">' + [1, 3, 6, 12].map(function (m) { return '<button type="button" class="ha-methodbtn ha-mbtn' + (m === 1 ? ' is-on' : '') + '" data-m="' + m + '">' + m + '</button>'; }).join('') + '</div>'
            + '<p class="ha-err" hidden></p><button type="button" class="ha-bigcta" data-save disabled>Enrol · ' + whole(b.monthly_fee) + '</button><button type="button" class="ha-textbtn" data-close>Cancel</button>');
        var save = d.querySelector('[data-save]');
        function check() { save.disabled = !(val(d, 'name') && val(d, 'phone').length === 10); save.textContent = 'Enrol · ' + whole(b.monthly_fee * months); }
        d.addEventListener('input', function (e) { if (e.target.dataset.k === 'phone') e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10); check(); });
        d.addEventListener('click', function (e) {
            var m = e.target.closest('[data-m]');
            if (!m) return;
            months = +m.dataset.m;
            d.querySelectorAll('[data-m]').forEach(function (x) { x.classList.toggle('is-on', x === m); });
            check();
        });
        save.addEventListener('click', function () {
            save.disabled = true;
            A.apiSend('POST', 'academy/' + b.id + '/enroll', { studentName: val(d, 'name'), studentPhone: val(d, 'phone'), months: months })
                .then(function () { d.close(); A.toast('Student enrolled'); loadRoster(); }, function (err) { showError(d, err); check(); });
        });
    }

    A.register('academy', {
        tool: true,
        enter: function (host) {
            ac.host = host;
            host.innerHTML = header('Academy') + '<div class="ha-toolbody">' + loading() + '</div>';
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-subback]')) { loadAcademy(); return; }
                if (e.target.closest('[data-newbatch]')) { newBatch(); return; }
                if ((b = e.target.closest('[data-batch]'))) { openBatch(ac.batches[+b.dataset.batch]); return; }
                if (e.target.closest('[data-enrol]')) { enrolStudent(); return; }
                if ((b = e.target.closest('[data-dayshift]'))) { ac.day = new Date(ac.day.getTime() + (+b.dataset.dayshift) * 86400000); ac.host.querySelector('.ha-roster').innerHTML = loading(); loadRoster(); return; }
                if ((b = e.target.closest('[data-tick]'))) {
                    var now = b.dataset.on !== '1';
                    vibrate(now ? 12 : 4);
                    b.dataset.on = now ? '1' : '0';
                    b.closest('.ha-student').classList.toggle('is-present', now);
                    A.apiSend('POST', 'academy/attendance', { enrollmentId: +b.dataset.tick, date: A.ymd(ac.day), present: now }).then(loadRoster, function (err) { A.toast(err.message); loadRoster(); });
                }
            };
            loadAcademy();
        },
        leave: function () { ac.host = null; ac.batch = null; },
    });
})();
