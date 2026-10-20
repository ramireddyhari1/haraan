/**
 * Haraan Partner: the fast app's desk tools as the Android app has them today — Desk
 * team, Academy, Packages, Payouts and Invoices.
 *
 * Each registers over the 3-Oct version in app-tools.js / app-venue-tools.js, and only
 * in the fast app (/partner/app): with the switch off, the console's phone shell keeps
 * the older screens. Same /api/partner/* endpoints as the app.
 *
 * Ported from: staff/StaffScreen.kt, academy/AcademyScreen.kt, packages/PackagesScreen.kt,
 * ui/payouts/PayoutsScreen.kt, ui/payouts/InvoicesScreen.kt and ui/desk/DeskKit.kt.
 */
(function () {
    'use strict';

    var A = window.HaApp;
    if (!A || !A.cfg.spa || window.__haDeskTools) return;
    window.__haDeskTools = true;

    var esc = A.esc, rupees = A.rupees, h = A.h, haptic = A.haptic;
    var MONTHS = A.months;

    /* ================================================================ shared kit === */

    var P = {
        ink: '#0F172A', muted: '#64748B', faint: '#94A3B8', hair: '#E2E8F0', page: '#F6F8FC', blue: '#2563EB',
        tint: '#EFF4FF', navy: '#0B1C46', green: '#16A34A', amber: '#D97706', red: '#DC2626',
    };

    function list(r) { return r && r.data ? r.data : (r || []); }
    function initials(name) {
        var p = String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
        return p.map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?';
    }
    function first(name) { return String(name || '').trim().split(/\s+/)[0] || ''; }
    function money(n) { return rupees(Math.round(Number(n) || 0)); }
    function f(n) { return (Math.round(n * 10) / 10).toString(); }

    /** "2026-11-12" or an ISO instant → "12 Nov", on the phone's own calendar. */
    function shortDate(iso) {
        if (!iso) return null;
        var d = String(iso).length > 10 ? new Date(iso) : new Date(String(iso).slice(0, 10) + 'T12:00:00');
        return isNaN(d) ? null : d.getDate() + ' ' + MONTHS[d.getMonth()];
    }
    function clockTime(iso) {
        var d = iso ? new Date(iso) : null;
        return d && !isNaN(d) ? d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase() : null;
    }
    function ago(iso) {
        var d = iso ? new Date(iso) : null;
        if (!d || isNaN(d)) return null;
        var mins = Math.max(0, Math.floor((Date.now() - d) / 60000));
        if (mins < 2) return 'just now';
        if (mins < 60) return mins + ' min ago';
        if (mins < 1440) return Math.floor(mins / 60) + 'h ago';
        if (mins < 10080) return Math.floor(mins / 1440) + 'd ago';
        return d.getDate() + ' ' + MONTHS[d.getMonth()];
    }
    function todayIso() { return A.ymd(new Date()); }
    function shiftIso(iso, days) { var d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + days); return A.ymd(d); }

    function page(title, actions, body) { return A.toolHeader(title, actions) + '<div class="ha-toolbody ha-dt">' + body + '</div>'; }
    function skeleton(hero, rows) {
        var out = '<div class="ha-dt-skel"><b style="height:' + (hero || 220) + 'px"></b>';
        for (var i = 0; i < (rows || 3); i++) out += '<b></b>';
        return out + '</div>';
    }
    function failed(host, title, msg, retry) {
        host.innerHTML = page(title, '', '<div class="ha-fail"><b>Couldn’t load this</b><span>' + esc(msg || 'Check your connection and try again.') + '</span><button type="button" class="ha-cta" data-retry>Try again</button></div>');
        host.querySelector('[data-retry]').onclick = retry;
    }
    function addPill(label) {
        return '<button type="button" class="ha-dt-add" data-add><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1v10M1 6h10" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>' + esc(label) + '</button>';
    }
    function section(text) { return '<h4 class="ha-dt-sec">' + esc(text) + '</h4>'; }
    function tick(on) {
        return on ? '<svg class="ha-dt-tick" viewBox="0 0 22 22" aria-hidden="true"><circle cx="11" cy="11" r="11" fill="#2563EB"/><path d="M6.2 11.4l3.5 3.4 6.2-6.8" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg class="ha-dt-tick" viewBox="0 0 22 22" aria-hidden="true"><circle cx="11" cy="11" r="10.2" fill="none" stroke="#E2E8F0" stroke-width="1.6"/></svg>';
    }
    function chev() { return '<svg class="ha-dt-chev" viewBox="0 0 14 14" aria-hidden="true"><path d="M5 3l4.5 4L5 11" fill="none" stroke="#94A3B8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }

    /** A centred confirm (the app's AlertDialog). Resolves true on the confirm button. */
    function confirm(title, text, yes, tone, no) {
        return new Promise(function (resolve) {
            var d = h('<div class="ha-dialog ha-dt-dialog"><div class="ha-scrim" data-no></div><div class="ha-dialog-in" role="alertdialog"><h3>' + esc(title) + '</h3><p>' + esc(text) + '</p>'
                + '<div><button type="button" data-no>' + esc(no || 'Cancel') + '</button><button type="button" data-yes style="color:' + (tone || P.blue) + '">' + esc(yes) + '</button></div></div></div>');
            document.body.appendChild(d);
            d.addEventListener('click', function (e) {
                if (e.target.closest('[data-yes]')) { d.remove(); resolve(true); }
                else if (e.target.closest('[data-no]')) { d.remove(); resolve(false); }
            });
        });
    }

    /** Money that runs up from where it last stood (rememberMoneyMotion). */
    function countUp(el, key) {
        if (!el) return;
        var to = parseFloat(el.getAttribute('data-to')) || 0;
        var from = parseFloat(A.store('ha-dt:' + key) || '0');
        A.store('ha-dt:' + key, String(to));
        if (from === to || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var t0 = performance.now();
        el.textContent = money(from);
        var done = false;
        requestAnimationFrame(function step(t) {
            if (done) return;
            var k = Math.min(1, Math.max(0, (t - t0) / 900)), e = 1 - Math.pow(1 - k, 3);
            el.textContent = money(from + (to - from) * e);
            if (k < 1) requestAnimationFrame(step);
        });
        // Frames can stall (a throttled or backgrounded page): the real figure always lands.
        setTimeout(function () { done = true; el.textContent = money(to); }, 1000);
    }

    /** Run a write: buzz the outcome, say it, then reload. */
    function act(run, done, reload, buzz) {
        return run().then(function (r) {
            haptic(buzz || 'confirm');
            if (done) A.toast(typeof done === 'function' ? done(r) : done);
            if (reload) reload();
            return r;
        }, function (e) {
            haptic('reject');
            A.toast(e.message || 'Something went wrong');
            throw e;
        });
    }

    function payMethodLabel(m) {
        return { cash: 'Cash', upi: 'UPI', card: 'Card', opening: 'Before records' }[m] || (m ? m.charAt(0).toUpperCase() + m.slice(1) : '—');
    }
    /** Cash / UPI / Card chips (MethodPicker). */
    function methodPicker(sel) {
        return '<div class="ha-dt-chips3">' + [['cash', 'Cash'], ['upi', 'UPI'], ['card', 'Card']].map(function (m) {
            return '<button type="button" class="ha-dt-chip' + (sel === m[0] ? ' is-on' : '') + '" data-method="' + m[0] + '">' + m[1] + '</button>';
        }).join('') + '</div>';
    }
    function field(name, label, value, type, attrs) {
        return '<label class="ha-dt-field"><span>' + esc(label) + '</span><input name="' + name + '" type="' + (type || 'text') + '" value="' + esc(value == null ? '' : value) + '" autocomplete="off"' + (attrs || '') + '></label>';
    }

    /* ================================================================= DESK TEAM === */

    var PERMS = ['bookings', 'checkin', 'pricing', 'reports'];
    var ROLE_COLOR = { manager: '#1E3A8A', box_office: '#2563EB', gate: '#0D9488', finance: '#6D28D9' };
    function roleColor(r) { return ROLE_COLOR[r] || '#475569'; }
    function permLine(p) {
        return { bookings: 'Take walk-ins and manage bookings', checkin: 'Check people in at the gate', pricing: 'Change prices, slots and listings', reports: 'See money, reports and settlement' }[p] || p;
    }
    function presetPitch(k) {
        return { manager: 'Runs the place. Everything except the team.', box_office: 'Front desk: walk-ins, bookings and check-in.', gate: 'Scans tickets at the entrance. Nothing else.', finance: 'Sees money and reports. Can’t take bookings.' }[k] || 'Pick exactly what they can do.';
    }
    function presetOf(perms, presets) {
        var s = perms.slice().sort().join(',');
        var p = presets.filter(function (x) { return (x.permissions || []).slice().sort().join(',') === s; })[0];
        return p ? p.key : 'custom';
    }
    function staffOf(o) {
        var today = o.today || {}, shift = o.on_shift || null;
        var m = {
            id: o.id, name: o.name || '', email: o.email || '', permissions: o.permissions || [], role: o.role || 'custom', roleLabel: o.role_label || 'Custom',
            state: o.state || 'active', lastSeen: o.last_seen_at || null, venues: o.venues || [], events: o.events || [],
            collected: +today.collected || 0, payments: +today.payments || 0, shiftVenue: shift && shift.venue, shiftSince: shift && shift.opened_at,
        };
        m.suspended = m.state === 'suspended';
        m.pending = m.state === 'pending';
        m.onShift = !!m.shiftSince && !m.suspended;
        return m;
    }
    function statusLine(m) {
        if (m.suspended) return ['Access paused', P.red];
        if (m.onShift) return ['On shift' + (m.shiftVenue ? ' · ' + m.shiftVenue : '') + (clockTime(m.shiftSince) ? ' since ' + clockTime(m.shiftSince) : ''), P.green];
        if (m.pending) return ['Invited · hasn’t signed in yet', P.amber];
        return [ago(m.lastSeen) ? 'Last active ' + ago(m.lastSeen) : 'Off duty', P.muted];
    }
    function placesLine(m) {
        var n = m.venues.concat(m.events).map(function (x) { return x.name; });
        if (!n.length) return null;
        return n.length <= 2 ? 'Only ' + n.join(' & ') : 'Only ' + n[0] + ' + ' + (n.length - 1) + ' more';
    }

    var team = { host: null, roster: null, openId: null };

    function loadTeam() {
        var host = team.host;
        if (!host) return;
        if (!team.roster) host.innerHTML = page('Desk team', '', skeleton(232, 3));
        A.api('staff', { branch: false, fresh: true }).then(function (r) {
            if (!team.host) return;
            team.roster = {
                members: list(r).map(staffOf), presets: r.presets || [],
                venues: r.venues || [], events: r.events || [],
            };
            renderTeam();
        }, function (e) { if (team.host) failed(team.host, 'Desk team', e.message, loadTeam); });
    }

    function renderTeam() {
        var host = team.host, ro = team.roster;
        if (!host || !ro) return;
        var ms = ro.members;
        if (!ms.length) {
            host.innerHTML = page('Desk team', '', '<div class="ha-dt-empty">' + emptyDeskSvg() + '<b>Nobody at the desk yet</b>'
                + '<p>Give your front-desk people their own login. They take walk-ins and check people in; you choose whether they see money.</p>'
                + '<button type="button" class="ha-dt-cta" data-add>Add your first desk person</button></div>');
            return;
        }
        var on = ms.filter(function (m) { return m.onShift; }), rest = ms.filter(function (m) { return !m.onShift; });
        var taken = ms.reduce(function (t, m) { return t + m.collected; }, 0), pays = ms.reduce(function (t, m) { return t + m.payments; }, 0);
        var pending = ms.filter(function (m) { return m.pending; }).length;
        var out = '<section class="ha-dt-card ha-dt-hero">' + deskScene(on, rest)
            + '<div class="ha-dt-herofoot"><div><b>' + (on.length === 0 ? 'Nobody on shift' : on.length === 1 ? '1 person at the desk' : on.length + ' people at the desk') + '</b>'
            + '<small>' + ms.length + ' in the team' + (pending ? ' · ' + pending + ' invite pending' : '') + '</small></div>'
            + '<div class="is-end"><b data-to="' + taken + '">' + money(taken) + '</b><small>' + (pays === 0 ? 'taken at the desk today' : 'at the desk today · ' + pays + ' paid') + '</small></div></div></section>';
        if (on.length) out += section('At the desk now') + on.map(staffRow).join('');
        if (rest.length) out += section(on.length ? 'Off duty' : 'Your team') + rest.map(staffRow).join('');
        out += '<p class="ha-dt-foot">Pausing someone signs them out of every phone straight away. Their bookings and payments stay on record.</p>';
        host.innerHTML = page('Desk team', addPill('Add person'), out);
        countUp(host.querySelector('.ha-dt-herofoot [data-to]'), 'staff.desk.today');
        if (team.openId != null) {
            var still = ms.filter(function (m) { return m.id === team.openId; })[0];
            if (!still) team.openId = null;
        }
    }

    function staffRow(m) {
        var st = statusLine(m);
        return '<button type="button" class="ha-dt-row" data-staff="' + m.id + '">' + passBadge(m)
            + '<span class="ha-dt-rowmid"><span class="ha-dt-line"><b' + (m.suspended ? ' class="is-muted"' : '') + '>' + esc(m.name) + '</b><em style="color:' + roleColor(m.role) + ';background:' + roleColor(m.role) + '17">' + esc(m.roleLabel) + '</em></span>'
            + '<small style="color:' + st[1] + '">' + (m.onShift ? '<i class="ha-dt-live"></i>' : '') + esc(st[0]) + '</small>'
            + (placesLine(m) ? '<small class="is-faint">' + esc(placesLine(m)) + '</small>' : '') + '</span>'
            + (m.collected !== 0 ? '<span class="ha-dt-rowamt"><b>' + money(m.collected) + '</b><small>today</small></span>' : '')
            + '</button>';
    }

    /** A small drawn staff pass: strap, role band, initials. Greyed when paused. */
    function passBadge(m) {
        var c = m.suspended ? P.faint : roleColor(m.role);
        return '<span class="ha-dt-pass"><svg viewBox="0 0 40 52" aria-hidden="true"><rect x="15" y="0" width="10" height="9" rx="2" fill="' + c + '" opacity=".55"/>'
            + '<rect x="2" y="7" width="36" height="45" rx="6" fill="#fff" stroke="' + c + '" stroke-width="1.4"/>'
            + '<path d="M2 13a6 6 0 0 1 6-6h24a6 6 0 0 1 6 6v5H2z" fill="' + c + '"/><rect x="15" y="10" width="10" height="2.4" rx="1.2" fill="#fff" opacity=".9"/>'
            + (m.pending ? '<rect x=".5" y="5.5" width="39" height="48" rx="7" fill="none" stroke="' + P.amber + '" stroke-width="1.2" stroke-dasharray="4 3"/>' : '')
            + '<text x="20" y="38" text-anchor="middle" font-size="13" font-weight="700" fill="' + c + '" font-family="Inter,system-ui,sans-serif">' + esc(initials(m.name)) + '</text></svg></span>';
    }

    /**
     * The desk as a picture: people on shift behind the counter wearing their pass,
     * everyone else's pass on the key board (pending = dashed sleeve, paused = red clip),
     * a wall clock at the real time, a card reader on the counter.
     */
    function deskScene(onShift, hanging) {
        var W = 360, H = 168, out = '';
        out += '<rect width="' + W + '" height="' + H + '" fill="#EEF3FB"/><rect y="' + f(H * 0.7) + '" width="' + W + '" height="' + f(H * 0.3) + '" fill="#E3EAF6"/>';
        var shown = hanging.slice(0, 4), bl = 16, bt = 18, bw = (Math.max(2, shown.length) * 30 + 14);
        out += '<rect x="' + bl + '" y="' + bt + '" width="' + bw + '" height="12" rx="4" fill="#D9C3A5"/><rect x="' + bl + '" y="' + (bt + 9) + '" width="' + bw + '" height="3" rx="2" fill="#BFA585"/>';
        shown.forEach(function (m, i) {
            var hx = bl + 14 + i * 30, hy = bt + 12, c = m.suspended ? P.faint : roleColor(m.role), top = hy + 12;
            var card = '<line x1="' + hx + '" y1="' + hy + '" x2="' + (hx - 4) + '" y2="' + top + '" stroke="' + c + '" stroke-opacity=".8" stroke-width="1.6"/>'
                + '<line x1="' + hx + '" y1="' + hy + '" x2="' + (hx + 4) + '" y2="' + top + '" stroke="' + c + '" stroke-opacity=".8" stroke-width="1.6"/>'
                + '<rect x="' + (hx - 10) + '" y="' + top + '" width="20" height="27" rx="3" fill="#fff" stroke="rgba(15,23,42,.13)" stroke-width=".8"/>'
                + '<path d="M' + (hx - 10) + ' ' + (top + 6) + 'v-3a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v3z" fill="' + c + '"/>'
                + '<circle cx="' + hx + '" cy="' + (top + 13) + '" r="4" fill="' + c + '" fill-opacity=".25"/>'
                + '<line x1="' + (hx - 6) + '" y1="' + (top + 21) + '" x2="' + (hx + 6) + '" y2="' + (top + 21) + '" stroke="#E2E8F0" stroke-width="1.4" stroke-linecap="round"/>'
                + (m.pending ? '<rect x="' + (hx - 12) + '" y="' + (top - 2) + '" width="24" height="31" rx="4" fill="none" stroke="' + P.amber + '" stroke-width="1.2" stroke-dasharray="3 2.5"/>' : '')
                + (m.suspended ? '<rect x="' + (hx - 4) + '" y="' + (top - 3) + '" width="8" height="5" rx="1.5" fill="' + P.red + '"/>' : '');
            out += '<circle cx="' + hx + '" cy="' + hy + '" r="2.2" fill="#8B95A7"/><g class="ha-dt-swing" style="transform-origin:' + hx + 'px ' + hy + 'px;animation-delay:' + (i * 120) + 'ms">' + card + '</g>';
        });
        if (hanging.length > 4) out += '<circle cx="' + (bl + bw + 14) + '" cy="' + (bt + 30) + '" r="9" fill="#fff"/><text x="' + (bl + bw + 14) + '" y="' + (bt + 33.5) + '" text-anchor="middle" font-size="9" font-weight="700" fill="#64748B" font-family="Inter,system-ui,sans-serif">+' + (hanging.length - 4) + '</text>';
        // Wall clock at the real time.
        var cx = W - 40, cy = 38, r = 18, now = new Date();
        out += '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="#fff" stroke="' + P.ink + '" stroke-width="2.4"/>';
        for (var t = 0; t < 12; t++) {
            var a = t / 12 * 2 * Math.PI, o = t % 3 === 0 ? 4.5 : 2.5;
            out += '<line x1="' + f(cx + (r - 3) * Math.sin(a)) + '" y1="' + f(cy - (r - 3) * Math.cos(a)) + '" x2="' + f(cx + (r - 3 - o) * Math.sin(a)) + '" y2="' + f(cy - (r - 3 - o) * Math.cos(a)) + '" stroke="' + P.faint + '" stroke-width="1.2"/>';
        }
        var ha = ((now.getHours() % 12) + now.getMinutes() / 60) / 12 * 360, ma = now.getMinutes() / 60 * 360;
        out += '<g data-clock><line class="ha-dt-hh" x1="' + cx + '" y1="' + cy + '" x2="' + cx + '" y2="' + (cy - r * 0.5) + '" stroke="' + P.ink + '" stroke-width="2.6" stroke-linecap="round" transform="rotate(' + f(ha) + ' ' + cx + ' ' + cy + ')"/>'
            + '<line class="ha-dt-mh" x1="' + cx + '" y1="' + cy + '" x2="' + cx + '" y2="' + (cy - r * 0.75) + '" stroke="' + P.ink + '" stroke-width="1.8" stroke-linecap="round" transform="rotate(' + f(ma) + ' ' + cx + ' ' + cy + ')"/></g>'
            + '<circle cx="' + cx + '" cy="' + cy + '" r="2.2" fill="' + P.blue + '"/>';
        // People on shift behind the counter, rising up on entrance.
        var ct = H * 0.66, people = onShift.slice(0, 4);
        if (people.length) {
            var span = Math.min(W * 0.56, people.length * 62), sx = W * 0.55 - span / 2 + span / people.length / 2;
            people.forEach(function (m, i) {
                var px = sx + i * (span / people.length), c = roleColor(m.role), b = ct;
                out += '<g class="ha-dt-rise" style="animation-delay:' + (i * 90) + 'ms"><g class="ha-dt-breathe" style="animation-delay:-' + (i * 0.9) + 's">'
                    + '<rect x="' + f(px - 20) + '" y="' + f(b - 30) + '" width="40" height="40" rx="16" fill="' + c + '"/>'
                    + '<rect x="' + f(px - 4) + '" y="' + f(b - 36) + '" width="8" height="8" fill="#E7B892"/><circle cx="' + f(px) + '" cy="' + f(b - 46) + '" r="11" fill="#E7B892"/>'
                    + '<path d="M' + f(px - 11.5) + ' ' + f(b - 48) + 'a11.5 10 0 0 1 23 0z" fill="#2B2B35"/>'
                    + '<path d="M' + f(px - 7) + ' ' + f(b - 30) + 'L' + f(px) + ' ' + f(b - 14) + 'L' + f(px + 7) + ' ' + f(b - 30) + '" fill="none" stroke="rgba(255,255,255,.85)" stroke-width="1.6"/>'
                    + '<rect x="' + f(px - 6) + '" y="' + f(b - 14) + '" width="12" height="12" rx="2" fill="#fff"/><rect x="' + f(px - 6) + '" y="' + f(b - 14) + '" width="12" height="3.5" fill="' + c + '"/></g></g>';
            });
        } else {
            var bx = W * 0.55;
            out += '<path d="M' + (bx - 9) + ' ' + f(ct) + 'a9 9 0 0 1 18 0z" fill="#CBD5E1"/><circle cx="' + bx + '" cy="' + f(ct - 10) + '" r="2" fill="#94A3B8"/>';
        }
        out += '<rect x="' + f(W * 0.18) + '" y="' + f(ct - 2) + '" width="' + f(W * 0.74) + '" height="8" rx="3" fill="#C08A5A"/><rect x="' + f(W * 0.18) + '" y="' + f(ct + 5) + '" width="' + f(W * 0.74) + '" height="2" fill="#9A6A40"/>'
            + '<defs><linearGradient id="ha-dt-navy" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14295E"/><stop offset="1" stop-color="#0B1C46"/></linearGradient></defs>'
            + '<rect x="' + f(W * 0.2) + '" y="' + f(ct + 7) + '" width="' + f(W * 0.7) + '" height="' + f(H - ct - 7) + '" fill="url(#ha-dt-navy)"/>'
            + '<line x1="' + f(W * 0.2) + '" y1="' + f(ct + 18) + '" x2="' + f(W * 0.9) + '" y2="' + f(ct + 18) + '" stroke="rgba(255,255,255,.1)" stroke-width="1.5"/>'
            + '<rect x="' + f(W * 0.8) + '" y="' + f(ct - 14) + '" width="16" height="12" rx="2.5" fill="#334155"/><rect x="' + f(W * 0.8 + 3) + '" y="' + f(ct - 12) + '" width="10" height="4" rx="1" fill="#7DD3FC"/>';
        return '<svg class="ha-dt-scene" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="' + onShift.length + ' at the desk, ' + hanging.length + ' off duty">' + out + '</svg>';
    }

    function emptyDeskSvg() {
        var cx = 150, out = '<rect x="' + (cx - 60) + '" y="14" width="120" height="11" rx="4" fill="#D9C3A5"/>';
        for (var i = 0; i < 3; i++) {
            var hx = cx - 34 + i * 34;
            out += '<circle cx="' + hx + '" cy="25" r="2.4" fill="#8B95A7"/>';
            if (i === 1) {
                out += '<g class="ha-dt-swing" style="transform-origin:' + hx + 'px 25px"><line x1="' + hx + '" y1="25" x2="' + (hx - 5) + '" y2="38" stroke="#2563EB" stroke-opacity=".6" stroke-width="1.6"/><line x1="' + hx + '" y1="25" x2="' + (hx + 5) + '" y2="38" stroke="#2563EB" stroke-opacity=".6" stroke-width="1.6"/>'
                    + '<rect x="' + (hx - 12) + '" y="38" width="24" height="32" rx="4" fill="none" stroke="#2563EB" stroke-width="1.5" stroke-dasharray="4 3"/>'
                    + '<path d="M' + (hx - 5) + ' 54h10M' + hx + ' 49v10" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/></g>';
            }
        }
        out += '<rect x="' + (cx - 120) + '" y="112" width="240" height="9" rx="3" fill="#C08A5A"/><rect x="' + (cx - 114) + '" y="121" width="228" height="46" rx="2" fill="#0B1C46"/>'
            + '<path d="M' + (cx + 50) + ' 112a10 10 0 0 1 20 0z" fill="#CBD5E1"/><circle cx="' + (cx + 60) + '" cy="101" r="2.2" fill="#94A3B8"/>';
        return '<svg class="ha-dt-emptyart" viewBox="0 0 300 170" aria-hidden="true">' + out + '</svg>';
    }

    /** The person's pass, large, swinging from its lanyard; a tap knocks it again (BigPass). */
    function bigPass(m) {
        var c = m.suspended ? P.faint : roleColor(m.role);
        var seed = m.id * 7919 + 17, bars = [];
        for (var i = 0; i < 22; i++) { seed = (seed * 16807) % 2147483647; bars.push(1 + (seed % 3)); }
        var total = bars.reduce(function (a, b) { return a + b; }, 0) + bars.length, bw = (140 - 36) / total, x = 25 + 18, bc = '';
        bars.forEach(function (b, i) { if (i % 2 === 0) bc += '<rect x="' + f(x) + '" y="178" width="' + f(b * bw) + '" height="14" fill="' + c + '" fill-opacity=".55"/>'; x += (b + 1) * bw; });
        return '<div class="ha-dt-bigpass" data-knock><svg viewBox="0 0 190 210" aria-hidden="true"><g class="ha-dt-lanyard">'
            + '<path d="M95 0L73 52M95 0l22 52" stroke="' + c + '" stroke-width="5" stroke-linecap="round"/><rect x="85" y="48" width="20" height="14" rx="3" fill="#94A3B8"/>'
            + '<rect x="25" y="62" width="140" height="148" rx="14" fill="rgba(15,23,42,.08)"/><rect x="25" y="58" width="140" height="148" rx="14" fill="#fff" stroke="' + c + '" stroke-width="2"/>'
            + '<path d="M25 72a14 14 0 0 1 14-14h112a14 14 0 0 1 14 14v20H25z" fill="' + c + '"/><rect x="79" y="66" width="32" height="6" rx="3" fill="#fff"/>'
            + '<circle cx="95" cy="120" r="24" fill="' + c + '" fill-opacity=".12"/>' + bc
            + '<text x="95" y="128" text-anchor="middle" font-size="21" font-weight="700" fill="' + c + '" font-family="Inter,system-ui,sans-serif">' + esc(initials(m.name)) + '</text>'
            + '<text x="95" y="160" text-anchor="middle" font-size="10.5" font-weight="700" letter-spacing="1.6" fill="' + c + '" font-family="Inter,system-ui,sans-serif">' + esc(m.roleLabel.toUpperCase()) + '</text>'
            + (m.suspended ? '<g transform="rotate(-14 95 168)"><rect x="52" y="154" width="86" height="28" rx="5" fill="rgba(255,255,255,.85)" stroke="' + P.red + '" stroke-width="2.4"/><text x="95" y="174" text-anchor="middle" font-size="17" font-weight="900" letter-spacing="3" fill="' + P.red + '" font-family="Inter,system-ui,sans-serif">PAUSED</text></g>' : '')
            + '</g></svg></div><h3 class="ha-dt-passname">' + esc(m.name) + '</h3><p class="ha-dt-passmail">' + esc(m.email) + '</p>';
    }

    function checkMark(on) {
        return on ? '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="rgba(22,163,74,.12)"/><path d="M5.6 10.4l3.2 3.2 5.9-6.4" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/></svg>'
            : '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9.3" fill="none" stroke="#E2E8F0" stroke-width="1.4"/><path d="M6.8 10h6.4" stroke="#94A3B8" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }

    function openStaff(m) {
        team.openId = m.id;
        var st = statusLine(m), fn = first(m.name);
        var html = bigPass(m)
            + '<div class="ha-dt-facts"><div><small>Taken today</small><b>' + money(m.collected) + '</b><em>' + (m.payments === 1 ? '1 payment' : m.payments + ' payments') + '</em></div>'
            + '<div><small>' + (m.onShift ? 'On shift' : m.suspended || m.pending ? 'Status' : 'Last active') + '</small><b style="color:' + (m.onShift ? P.green : st[1] === P.muted ? P.ink : st[1]) + '">'
            + esc(m.onShift ? (clockTime(m.shiftSince) ? 'since ' + clockTime(m.shiftSince) : 'now') : m.suspended ? 'Paused' : m.pending ? 'Invited' : ago(m.lastSeen) || 'Off duty') + '</b>'
            + '<em>' + esc(m.onShift ? m.shiftVenue || '' : m.pending ? 'hasn’t signed in' : '') + '</em></div></div>'
            + '<h4 class="ha-dt-sec">What ' + esc(fn) + ' can do</h4>'
            + PERMS.map(function (p) { var has = m.permissions.indexOf(p) >= 0; return '<p class="ha-dt-perm' + (has ? '' : ' is-off') + '">' + checkMark(has) + esc(permLine(p)) + '</p>'; }).join('')
            + '<p class="ha-dt-note">' + esc(placesLine(m) ? placesLine(m) + '.' : 'Works at all your venues and events.') + '</p>'
            + '<div class="ha-dt-acts">'
            + '<button type="button" data-do="edit" style="color:' + P.blue + '">Change role or places' + chev() + '</button>'
            + (m.pending && !m.suspended ? '<button type="button" data-do="invite" style="color:' + P.blue + '">Email the invite again' + chev() + '</button>' : '')
            + (m.suspended ? '<button type="button" data-do="restore" style="color:' + P.blue + '">Restore access' + chev() + '</button>' : '<button type="button" data-do="pause" style="color:' + P.amber + '">Pause access' + chev() + '</button>')
            + '<button type="button" data-do="remove" style="color:' + P.red + '">Remove from team' + chev() + '</button></div>';
        var s = A.sheet('<div class="ha-dt-sheet">' + html + '</div>', 'ha-dt-bs');
        s.onclose = function () { team.openId = null; };
        s.addEventListener('click', function (e) {
            var b;
            if (e.target.closest('[data-knock]')) {
                haptic('tick');
                var g = s.querySelector('.ha-dt-bigpass');
                g.classList.remove('is-knock'); void g.offsetWidth; g.classList.add('is-knock');
                return;
            }
            if (!(b = e.target.closest('[data-do]'))) return;
            haptic('tick');
            var reload = function () { loadTeam(); };
            var what = b.dataset.do;
            if (what === 'edit') { s.close(); editStaff(m); }
            else if (what === 'invite') act(function () { return A.apiSend('POST', 'staff/' + m.id + '/invite', {}); }, 'Invite sent to ' + m.email, reload);
            else if (what === 'restore') act(function () { return A.apiSend('POST', 'staff/' + m.id + '/suspend', { suspended: false }); }, fn + ' can sign in again', function () { s.close(); reload(); });
            else if (what === 'pause') {
                confirm('Pause ' + fn + '?', fn + ' is signed out of every phone now and can’t sign back in until you restore access. Nothing they recorded is lost.', 'Pause', P.amber).then(function (ok) {
                    if (ok) act(function () { return A.apiSend('POST', 'staff/' + m.id + '/suspend', { suspended: true }); }, fn + ' is paused and signed out', function () { s.close(); reload(); }, 'reject');
                });
            } else if (what === 'remove') {
                confirm('Remove ' + m.name + '?', 'Their login is deleted and they’re signed out everywhere. Bookings and payments they took stay in your records. If they might come back, pause them instead.', 'Remove', P.red, 'Keep').then(function (ok) {
                    if (ok) { s.close(); act(function () { return A.apiSend('DELETE', 'staff/' + m.id); }, m.name + ' removed from the team', reload, 'reject'); }
                });
            }
        });
    }

    /** One drawn object per role: a key, a ticket, a scanner frame, a coin stack, sliders. */
    function roleGlyph(k) {
        var c = roleColor(k), s = 26, sw = s * 0.085, g;
        if (k === 'manager') g = '<circle cx="8.3" cy="9.4" r="5.2" fill="none" stroke="' + c + '" stroke-width="' + sw + '"/><circle cx="8.3" cy="9.4" r="1.6" fill="' + c + '"/><path d="M12 13l9.3 9.4M17.2 18.2l2.6-2.6M19.5 20.5l2.6-2.6" stroke="' + c + '" stroke-width="' + sw + '" stroke-linecap="round"/>';
        else if (k === 'box_office') g = '<path d="M2.6 7.3h20.8v3.6q-2.6 2.1 0 4.2v3.6H2.6v-3.6q2.6-2.1 0-4.2z" fill="none" stroke="' + c + '" stroke-width="2.1" stroke-linejoin="round"/><path d="M16.1 8.3v9.4" stroke="' + c + '" stroke-width="1.3" stroke-dasharray="1.6 1.3"/><path d="M6.2 11.4h6.3M6.2 14.6h4.2" stroke="' + c + '" stroke-width="1.8" stroke-linecap="round"/>';
        else if (k === 'gate') g = '<path d="M3.1 8.8V3.1h5.7M22.9 8.8V3.1h-5.7M3.1 17.2v5.7h5.7M22.9 17.2v5.7h-5.7" fill="none" stroke="' + c + '" stroke-width="' + sw + '" stroke-linecap="round"/><rect x="8.3" y="8.3" width="3.6" height="3.6" fill="' + c + '"/><rect x="14" y="8.3" width="3.6" height="3.6" fill="' + c + '"/><rect x="8.3" y="14" width="3.6" height="3.6" fill="' + c + '"/><path d="M1.6 13h22.8" stroke="#EF4444" stroke-width="1.3"/>';
        else if (k === 'finance') { g = ''; for (var i = 0; i < 3; i++) { var y = s * (0.74 - i * 0.17); g += '<ellipse cx="13" cy="' + f(y) + '" rx="8.3" ry="2.6" fill="#fff" stroke="' + c + '" stroke-width="1.95"/>'; } }
        else g = '<path d="M3.1 8.3h19.8M3.1 17.7h19.8" stroke="' + c + '" stroke-opacity=".45" stroke-width="1.8" stroke-linecap="round"/><circle cx="8.8" cy="8.3" r="2.9" fill="#fff" stroke="' + c + '" stroke-width="1.95"/><circle cx="17.2" cy="17.7" r="2.9" fill="#fff" stroke="' + c + '" stroke-width="1.95"/>';
        return '<span class="ha-dt-glyph" style="background:' + c + '1a"><svg viewBox="0 0 26 26" aria-hidden="true">' + g + '</svg></span>';
    }

    function editStaff(existing) {
        var ro = team.roster;
        var form = {
            name: existing ? existing.name : '', email: existing ? existing.email : '', invite: true, password: '',
            perms: existing ? existing.permissions.slice() : ((ro.presets.filter(function (p) { return p.key === 'box_office'; })[0] || {}).permissions || ['bookings', 'checkin']).slice(),
            venues: existing ? existing.venues.map(function (v) { return v.id; }) : [], events: existing ? existing.events.map(function (v) { return v.id; }) : [],
            saving: false, error: null,
        };
        form.role = presetOf(form.perms, ro.presets);
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function valid() {
            var emailOk = existing || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email.trim());
            var passOk = existing || form.invite || form.password.length >= 6;
            return form.name.trim() && emailOk && passOk && form.perms.length && !form.saving;
        }
        function foot() {
            s.querySelector('.ha-dt-formfoot').innerHTML = (form.error ? '<p class="ha-dt-err">' + esc(form.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-save' + (valid() ? '' : ' disabled') + '>' + (form.saving ? 'Saving…' : existing ? 'Save changes' : form.invite ? 'Add and send invite' : 'Add person') + '</button>';
        }
        function draw() {
            var keys = ro.presets.map(function (p) { return p.key; }).concat(['custom']);
            var places = ro.venues.map(function (v) { return ['v', v]; }).concat(ro.events.map(function (e) { return ['e', e]; }));
            var everywhere = !form.venues.length && !form.events.length;
            var out = '<h3 class="ha-dt-formtitle">' + (existing ? 'Edit ' + esc(first(existing.name)) : 'Add a desk person') + '</h3>'
                + field('name', 'Name', form.name, 'text', ' autocapitalize="words"')
                + (existing ? '' : field('email', 'Email they’ll sign in with', form.email, 'email', ' inputmode="email" autocapitalize="off"'))
                + section('Role') + '<div class="ha-dt-roles">' + keys.map(function (k) {
                    var label = (ro.presets.filter(function (p) { return p.key === k; })[0] || {}).label || 'Custom';
                    return '<button type="button" class="ha-dt-role' + (form.role === k ? ' is-on' : '') + '" data-role="' + k + '">' + roleGlyph(k)
                        + '<span><b>' + esc(label) + '</b><small>' + esc(presetPitch(k)) + '</small></span>' + tick(form.role === k) + '</button>';
                }).join('') + '</div>';
            if (form.role === 'custom') {
                out += '<div class="ha-dt-perms">' + PERMS.map(function (p) {
                    return '<label class="ha-dt-switch"><span>' + esc(permLine(p)) + '</span><input type="checkbox" switch data-perm="' + p + '"' + (form.perms.indexOf(p) >= 0 ? ' checked' : '') + '><i></i></label>';
                }).join('') + (form.perms.length ? '' : '<p class="ha-dt-err">Pick at least one thing they can do.</p>') + '</div>';
            }
            if (places.length) {
                out += section('Where they work') + '<div class="ha-dt-flow"><button type="button" class="ha-dt-pchip' + (everywhere ? ' is-on' : '') + '" data-everywhere>Everywhere</button>'
                    + places.map(function (p) {
                        var on = (p[0] === 'v' ? form.venues : form.events).indexOf(p[1].id) >= 0;
                        return '<button type="button" class="ha-dt-pchip' + (on ? ' is-on' : '') + '" data-place="' + p[0] + ':' + p[1].id + '">' + esc(p[1].name) + '</button>';
                    }).join('') + '</div><p class="ha-dt-note">' + (everywhere ? 'They’ll see every venue and event you run.' : 'They’ll only see the places picked here.') + '</p>';
            }
            if (!existing) {
                out += section('How they sign in') + '<div class="ha-dt-seg"><button type="button" class="' + (form.invite ? 'is-on' : '') + '" data-invite="1">Email an invite</button><button type="button" class="' + (!form.invite ? 'is-on' : '') + '" data-invite="0">Set a password</button></div>'
                    + (form.invite ? '' : field('password', 'Password (6 or more)', form.password, 'password'))
                    + '<p class="ha-dt-note">' + (form.invite ? 'They get an email to set their own password. The link works for 60 minutes; you can resend it.' : 'Tell them the password in person. They can change it later.') + '</p>';
            }
            var a = document.activeElement, nm = a && a.name;
            s.querySelector('.ha-dt-formbody').innerHTML = out;
            if (nm) { var el = s.querySelector('input[name="' + nm + '"]'); if (el) { el.focus({ preventScroll: true }); try { el.setSelectionRange(el.value.length, el.value.length); } catch (e) { /* none */ } } }
            foot();
        }
        s.addEventListener('input', function (e) {
            var t = e.target;
            if (t.name === 'name' || t.name === 'email' || t.name === 'password') { form[t.name] = t.name === 'email' ? t.value.trim() : t.value; form.error = null; foot(); }
        });
        s.addEventListener('change', function (e) {
            var p = e.target.dataset && e.target.dataset.perm;
            if (!p) return;
            haptic('tick');
            if (e.target.checked) { if (form.perms.indexOf(p) < 0) form.perms.push(p); } else form.perms = form.perms.filter(function (x) { return x !== p; });
            draw();
        });
        s.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.role) {
                haptic('tick');
                form.role = b.dataset.role;
                var pr = ro.presets.filter(function (p) { return p.key === form.role; })[0];
                if (pr) form.perms = (pr.permissions || []).slice();
                draw();
                var r = s.querySelector('.ha-dt-role.is-on .ha-dt-glyph');
                if (r) r.classList.add('is-wiggle');
            } else if (b.hasAttribute('data-everywhere')) { haptic('tick'); form.venues = []; form.events = []; draw(); }
            else if (b.dataset.place) {
                haptic('tick');
                var parts = b.dataset.place.split(':'), arr = parts[0] === 'v' ? 'venues' : 'events', id = +parts[1];
                form[arr] = form[arr].indexOf(id) >= 0 ? form[arr].filter(function (x) { return x !== id; }) : form[arr].concat([id]);
                draw();
            } else if (b.dataset.invite) { haptic('tick'); form.invite = b.dataset.invite === '1'; draw(); }
            else if (b.hasAttribute('data-save')) {
                if (!valid()) return;
                form.saving = true; form.error = null; foot();
                var body = { name: form.name.trim(), permissions: form.perms, venue_ids: form.venues, event_ids: form.events };
                var req = existing ? A.apiSend('POST', 'staff/' + existing.id, body)
                    : A.apiSend('POST', 'staff', Object.assign({ email: form.email.trim() }, body, !form.invite && form.password ? { password: form.password } : {}));
                req.then(function (r) {
                    haptic('confirm');
                    s.close();
                    A.toast(existing ? 'Saved' : !form.invite ? form.name.trim() + ' can sign in now' : r && r.invited ? 'Invite emailed to ' + form.email.trim()
                        : form.name.trim() + ' added, but the invite email didn’t send. Resend it from their pass.');
                    loadTeam();
                }, function (err) { haptic('reject'); form.saving = false; form.error = err.message || 'Couldn’t save'; foot(); });
            }
        });
        draw();
    }

    A.register('staff', {
        tool: true,
        enter: function (host) {
            team.host = host;
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-add]')) { haptic('tick'); if (team.roster) editStaff(null); return; }
                if ((b = e.target.closest('[data-staff]'))) {
                    haptic('tick');
                    var m = team.roster.members.filter(function (x) { return x.id === +b.dataset.staff; })[0];
                    if (m) openStaff(m);
                }
            };
            if (team.roster) renderTeam();
            loadTeam();
        },
        leave: function () { team.host = null; },
    });

    /* =================================================================== ACADEMY === */

    var WEEK = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var SPORTS = ['Badminton', 'Cricket', 'Football', 'Tennis', 'Pickleball', 'Swimming', 'Basketball', 'Skating'];
    var SPORT_COLOR = { badminton: '#2563EB', cricket: '#0D9488', football: '#16A34A', tennis: '#CA8A04', pickleball: '#7C3AED', swimming: '#0284C7', basketball: '#EA580C', skating: '#DB2777' };
    function sportColor(s) { return SPORT_COLOR[String(s || '').toLowerCase()] || '#475569'; }
    function minutesOf(t) {
        var m = /^(\d{1,2}):(\d{2})/.exec(t || '');
        if (!m) return null;
        var hh = +m[1], mm = +m[2];
        return hh <= 23 && mm <= 59 ? hh * 60 + mm : null;
    }
    function clock24(t) { var m = minutesOf(t); return m == null ? null : A.clock(m); }
    function timeRange(b) { var s = clock24(b.start_time); if (!s) return null; var e = clock24(b.end_time); return e ? s + ' – ' + e : s; }
    function feeLine(s) {
        var d = s.days_overdue;
        if (!s.paid_until) return ['No fees on record', P.muted];
        if (s.overdue && d != null) return [d === 1 ? '1 day overdue' : d + ' days overdue', P.red];
        if (s.due_soon && d != null) return [d === 0 ? 'Due today' : 'Due in ' + (-d) + ' days', P.amber];
        return ['Paid till ' + shortDate(s.paid_until), P.green];
    }
    function dayLabel(iso) {
        if (iso === todayIso()) return 'Today';
        if (iso === shiftIso(todayIso(), -1)) return 'Yesterday';
        var d = new Date(iso + 'T12:00:00');
        return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
    }

    var acad = { host: null, page: null, filter: null, batchId: null, date: null, roster: null, local: {} };
    function canDefine() { return A.can('pricing'); }
    function canDesk() { return A.can('bookings'); }

    function loadAcademy() {
        if (!acad.host) return;
        if (!acad.page) acad.host.innerHTML = page('Academy', '', skeleton(220, 3));
        A.api('academy', { branch: false, fresh: true }).then(function (r) {
            if (!acad.host) return;
            acad.page = { batches: list(r), summary: r.summary || {}, venues: r.venues || [] };
            if (acad.batchId == null) renderAcademy();
        }, function (e) { if (acad.host && !acad.page) failed(acad.host, 'Academy', e.message, loadAcademy); });
    }

    /** A training cone; fill 0..1 pours the sport colour up from the base. */
    function cone(x, base, hgt, c, fill, lying, cls) {
        var half = hgt * 0.38, path;
        if (lying) path = 'M' + f(x - hgt * 0.5) + ' ' + f(base - half * 0.9) + 'L' + f(x + hgt * 0.5) + ' ' + f(base - 3) + 'L' + f(x - hgt * 0.5) + ' ' + f(base) + 'Z';
        else path = 'M' + f(x - half) + ' ' + f(base - 3) + 'L' + f(x - 3) + ' ' + f(base - hgt) + 'L' + f(x + 3) + ' ' + f(base - hgt) + 'L' + f(x + half) + ' ' + f(base - 3) + 'Z';
        var id = 'hac-' + Math.random().toString(36).slice(2, 8), out = '<g class="' + (cls || '') + '"><path d="' + path + '" fill="#fff"/>';
        if (!lying && fill > 0) {
            var top = base - 3 - (hgt - 3) * fill;
            out += '<clipPath id="' + id + '"><path d="' + path + '"/></clipPath><rect class="ha-dt-pour" clip-path="url(#' + id + ')" x="' + f(x - half) + '" y="' + f(top) + '" width="' + f(half * 2) + '" height="' + f(base - top) + '" fill="' + c + '"/>';
        }
        out += '<path d="' + path + '" fill="none" stroke="' + c + '" stroke-width="2"/>';
        if (!lying) out += '<line x1="' + f(x - half * 0.55) + '" y1="' + f(base - hgt * 0.45) + '" x2="' + f(x + half * 0.55) + '" y2="' + f(base - hgt * 0.45) + '" stroke="' + c + '" stroke-opacity=".45" stroke-width="2.4"/>'
            + '<rect x="' + f(x - half - 4) + '" y="' + f(base - 4) + '" width="' + f(half * 2 + 8) + '" height="5" rx="2" fill="' + c + '"/>';
        return out + '</g>';
    }

    /** Today's classes as cones on a 6 AM–10 PM track; the class on now has a ball bouncing. */
    function track(today) {
        var W = 360, H = 168, left = 22, right = W - 22, ground = H - 40, now = A.minutesNow();
        function xOf(m) { return left + (right - left) * Math.max(0, Math.min(1, (m - 360) / 960)); }
        var out = '<rect y="' + (ground - 6) + '" width="' + W + '" height="' + (H - ground + 6) + '" fill="#F1F7F2"/>';
        for (var i = 0; i < 8; i += 2) out += '<rect x="' + (W * i / 8) + '" y="' + (ground - 6) + '" width="' + (W / 8) + '" height="' + (H - ground + 6) + '" fill="#E5F1E8"/>';
        out += '<line x1="' + left + '" y1="' + ground + '" x2="' + right + '" y2="' + ground + '" stroke="#CBD5E1" stroke-width="1.4"/>';
        for (var hr = 6; hr <= 22; hr += 2) {
            var x = xOf(hr * 60);
            out += '<line x1="' + f(x) + '" y1="' + ground + '" x2="' + f(x) + '" y2="' + (ground + 4) + '" stroke="#CBD5E1" stroke-width="1.2"/>';
            if (hr % 4 === 2) out += '<text x="' + f(x) + '" y="' + (ground + 18) + '" text-anchor="middle" font-size="10" fill="#94A3B8" font-family="Inter,system-ui,sans-serif">' + (hr === 12 ? '12 PM' : hr < 12 ? hr + ' AM' : (hr - 12) + ' PM') + '</text>';
        }
        if (now >= 360 && now <= 1320) {
            var nx = xOf(now);
            out += '<line x1="' + f(nx) + '" y1="14" x2="' + f(nx) + '" y2="' + ground + '" stroke="#2563EB" stroke-opacity=".5" stroke-width="1.4" stroke-dasharray="4 4"/><circle cx="' + f(nx) + '" cy="14" r="3.5" fill="#2563EB"/>';
        }
        if (!today.length) {
            for (var k = 0; k < 3; k++) out += cone(W / 2 - 18 + k * 18, ground, 26, 'rgba(148,163,184,.5)', 0, k === 1);
        } else {
            today.forEach(function (b, j) {
                var st = minutesOf(b.start_time), en = minutesOf(b.end_time);
                if (st == null) st = 420 + j * 90;
                if (en == null) en = st + 60;
                var cx = xOf((st + en) / 2), c = sportColor(b.sport), fill = b.students ? Math.min(1, b.present_today / b.students) : 0;
                out += '<g class="ha-dt-rise" style="animation-delay:' + (j * 90) + 'ms">' + cone(cx, ground, 58, c, fill, false)
                    + '<text x="' + f(cx) + '" y="' + (ground - 82) + '" text-anchor="middle" font-size="11" font-weight="600" fill="#0F172A" font-family="Inter,system-ui,sans-serif">' + esc(String(b.name).split(' ')[0].slice(0, 10)) + '</text>'
                    + '<text x="' + f(cx) + '" y="' + (ground - 69) + '" text-anchor="middle" font-size="10" font-weight="700" fill="' + c + '" font-family="Inter,system-ui,sans-serif">' + b.present_today + '/' + b.students + '</text></g>';
                if (now >= st && now <= en) {
                    out += '<ellipse class="ha-dt-ballshadow" cx="' + f(cx + 22) + '" cy="' + (ground + 1) + '" rx="5" ry="2" fill="rgba(15,23,42,.13)"/>'
                        + '<g class="ha-dt-bounce" style="transform-origin:' + f(cx + 22) + 'px ' + (ground - 6) + 'px"><circle cx="' + f(cx + 22) + '" cy="' + (ground - 6) + '" r="6" fill="#FACC15"/><path d="M' + f(cx + 18) + ' ' + (ground - 4) + 'a6 6 0 0 0 8 0" fill="none" stroke="#CA8A04" stroke-width="1.2"/></g>';
                }
            });
        }
        return '<svg class="ha-dt-scene ha-dt-turf" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="' + today.length + ' classes today">' + out + '</svg>';
    }

    function dayLetters(days, c) {
        var every = !days || !days.length;
        return '<span class="ha-dt-days">' + WEEK.map(function (d) {
            var on = every || days.indexOf(d) >= 0;
            return '<i' + (on ? ' style="color:' + c + ';background:' + c + '24;font-weight:700"' : '') + '>' + d.charAt(0) + '</i>';
        }).join('') + '</span>';
    }

    /** The batch as a small court from above, its seats as dots (SeatCourt). */
    function seatCourt(b, c) {
        var seats = Math.max(1, Math.min(24, b.capacity != null ? b.capacity : b.students + 2));
        var W = 70, H = 84, inset = 7, cols = seats <= 6 ? 2 : seats <= 12 ? 3 : 4, rows = Math.ceil(seats / cols);
        var gx = (W - inset * 2) / cols, gy = (H - inset * 2) / rows, r = Math.min(Math.min(gx, gy) * 0.3, 7);
        var out = '<rect width="70" height="84" rx="14" fill="' + c + '" fill-opacity=".1"/><rect x="7" y="7" width="56" height="70" rx="3" fill="none" stroke="' + c + '" stroke-opacity=".35" stroke-width="1.2"/><line x1="7" y1="42" x2="63" y2="42" stroke="' + c + '" stroke-opacity=".35" stroke-width="1.2"/>';
        for (var i = 0; i < seats; i++) {
            var cx = inset + gx * (i % cols) + gx / 2, cy = inset + gy * Math.floor(i / cols) + gy / 2;
            if (i < b.present_today && b.runs_today) out += '<circle class="ha-dt-seat" cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r) + '" fill="' + c + '"/>';
            else if (i < b.students) out += '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r) + '" fill="#fff" stroke="' + c + '" stroke-width="1.6"/>';
            else out += '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r) + '" fill="none" stroke="' + c + '" stroke-opacity=".35" stroke-width="1.1" stroke-dasharray="2 2"/>';
        }
        return '<svg class="ha-dt-seatcourt" viewBox="0 0 70 84" aria-hidden="true">' + out + '</svg>';
    }

    function tag(text, c) { return '<em class="ha-dt-tag" style="color:' + c + ';background:' + c + '17">' + esc(text) + '</em>'; }

    function batchCard(b) {
        var c = b.is_active === false ? P.faint : sportColor(b.sport);
        var sub = [b.coach ? 'Coach ' + b.coach : null, timeRange(b)].filter(Boolean).join(' · ');
        return '<button type="button" class="ha-dt-row ha-dt-batch" data-batch="' + b.id + '">' + seatCourt(b, c)
            + '<span class="ha-dt-rowmid"><span class="ha-dt-line"><b' + (b.is_active === false ? ' class="is-muted"' : '') + '>' + esc(b.name) + '</b>' + (b.is_active === false ? tag('Paused', P.muted) : '') + '</span>'
            + (sub ? '<small>' + esc(sub) + '</small>' : '') + dayLetters(b.days, c)
            + '<span class="ha-dt-line"><strong>' + money(b.monthly_fee) + '</strong><small class="is-faint">&nbsp;/ month</small><span class="ha-grow"></span>'
            + (b.overdue > 0 ? tag(b.overdue + ' overdue', P.red) : b.due_soon > 0 ? tag(b.due_soon + ' due soon', P.amber) : '') + '</span></span></button>';
    }

    function renderAcademy() {
        var host = acad.host, pg = acad.page;
        if (!host || !pg) return;
        var bs = pg.batches, s = pg.summary;
        if (!bs.length) {
            var drop = '';
            [P.blue, '#0D9488', '#EA580C'].forEach(function (c, i) { drop += '<g class="ha-dt-drop" style="animation-delay:' + (i * 90) + 'ms">' + cone(150 + (i - 1) * 56, 118, 52, c, 0, false) + '</g>'; });
            host.innerHTML = page('Academy', '', '<div class="ha-dt-empty"><svg class="ha-dt-emptyart" viewBox="0 0 300 150" aria-hidden="true"><line x1="45" y1="118" x2="255" y2="118" stroke="#CBD5E1" stroke-width="1.4"/>' + drop + '</svg>'
                + '<b>No batches yet</b><p>Set up a coaching batch with its days, time and monthly fee. Then mark attendance and collect fees right from the desk.</p>'
                + (canDefine() ? '<button type="button" class="ha-dt-cta" data-add>Create your first batch</button>' : '') + '</div>');
            return;
        }
        var shown = acad.filter === 'overdue' ? bs.filter(function (b) { return b.overdue > 0; }) : acad.filter === 'soon' ? bs.filter(function (b) { return b.due_soon > 0; }) : bs;
        var today = bs.filter(function (b) { return b.is_active !== false && b.runs_today; });
        var expected = today.reduce(function (t, b) { return t + b.students; }, 0);
        var out = '<section class="ha-dt-card ha-dt-hero">' + track(today)
            + '<div class="ha-dt-herofoot"><div><b>' + (today.length === 0 ? 'No classes today' : today.length === 1 ? '1 class today' : today.length + ' classes today') + '</b>'
            + '<small>' + (expected === 0 ? (s.students || 0) + ' students across all batches' : (s.present_today || 0) + ' of ' + expected + ' marked in') + '</small></div>'
            + '<div class="is-end"><b data-to="' + (s.collected_month || 0) + '">' + money(s.collected_month) + '</b><small>fees this month</small></div></div>'
            + ((s.overdue || 0) > 0 || (s.due_soon || 0) > 0
                ? '<div class="ha-dt-feechips">' + (s.overdue > 0 ? '<button type="button" class="ha-dt-feechip' + (acad.filter === 'overdue' ? ' is-on' : '') + '" data-filter="overdue" style="--c:' + P.red + '"><i></i>' + (s.overdue === 1 ? '1 fee overdue' : s.overdue + ' fees overdue') + '</button>' : '')
                    + (s.due_soon > 0 ? '<button type="button" class="ha-dt-feechip' + (acad.filter === 'soon' ? ' is-on' : '') + '" data-filter="soon" style="--c:' + P.amber + '"><i></i>' + s.due_soon + ' due this week</button>' : '') + '</div>'
                : '<p class="ha-dt-allpaid">Every fee is paid up.</p>') + '</section>';
        var live = shown.filter(function (b) { return b.is_active !== false; }), paused = shown.filter(function (b) { return b.is_active === false; });
        var on = live.filter(function (b) { return b.runs_today; }), other = live.filter(function (b) { return !b.runs_today; });
        if (on.length) out += section('On today') + on.map(batchCard).join('');
        if (other.length) out += section(on.length ? 'Other days' : 'Batches') + other.map(batchCard).join('');
        if (paused.length) out += section('Paused') + paused.map(batchCard).join('');
        if (!shown.length) out += '<p class="ha-dt-note">No batch matches that filter.</p>';
        host.innerHTML = page('Academy', canDefine() ? addPill('New batch') : '', out);
        countUp(host.querySelector('.ha-dt-herofoot [data-to]'), 'academy.month');
    }

    /* ---- one batch: the register ---- */

    function openBatch(id) {
        acad.batchId = id; acad.date = todayIso(); acad.roster = null; acad.local = {};
        loadRoster();
    }
    function batchById(id) { return (acad.page && acad.page.batches || []).filter(function (b) { return b.id === id; })[0]; }

    function loadRoster() {
        var host = acad.host, id = acad.batchId, date = acad.date;
        if (!host || id == null) return;
        var b = batchById(id) || { name: 'Batch' };
        if (!acad.roster) host.innerHTML = subPage(b.name, '', skeleton(150, 4));
        A.api('academy/' + id + '/roster', { branch: false, date: date, fresh: true }).then(function (r) {
            if (acad.batchId !== id || acad.date !== date || !acad.host) return;
            acad.roster = { batch: r.batch || b, date: r.date || date, runsToday: !!r.runs_today, students: list(r) };
            acad.local = {};
            renderRoster();
        }, function (e) { if (acad.host && acad.batchId === id) failed(acad.host, b.name, e.message, loadRoster); });
    }

    /** A sub-screen header: back goes to the list, not out of the tool. */
    function subPage(title, actions, body) {
        return '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-sub-back aria-label="Back"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<b>' + esc(title) + '</b><span class="ha-grow"></span>' + (actions || '') + '</header><div class="ha-toolbody ha-dt">' + body + '</div>';
    }

    function presentOf(s) { return acad.local[s.id] != null ? acad.local[s.id] : !!s.present; }

    function renderRoster() {
        var host = acad.host, ro = acad.roster;
        if (!host || !ro) return;
        var b = ro.batch, c = sportColor(b.sport), total = ro.students.length;
        var here = ro.students.filter(presentOf).length;
        var prevOk = acad.date > shiftIso(todayIso(), -60), nextOk = acad.date < todayIso();
        var seats = '';
        for (var i = 0; i < Math.max(1, total); i++) seats += '<i style="background:' + (i < here ? c : c + '1f') + '"></i>';
        var dl = dayLabel(acad.date);
        var out = '<section class="ha-dt-card ha-dt-reg"><div class="ha-dt-reghead">'
            + '<button type="button" class="ha-dt-step" data-day="-1"' + (prevOk ? '' : ' disabled') + '>‹</button>'
            + '<div><b>' + esc(dl) + '</b><small>' + esc([timeRange(b), b.coach ? 'Coach ' + b.coach : null].filter(Boolean).join(' · ') || 'Register') + '</small></div>'
            + '<button type="button" class="ha-dt-step" data-day="1"' + (nextOk ? '' : ' disabled') + '>›</button></div>'
            + '<div class="ha-dt-seats">' + seats + '</div>'
            + '<div class="ha-dt-line"><b class="ha-grow">' + (total === 0 ? 'No students' : here + ' of ' + total + ' here') + '</b>'
            + (canDesk() && total ? '<button type="button" class="ha-dt-link" data-all="' + (here === total ? '0' : '1') + '">' + (here === total ? 'Clear all' : 'Mark everyone here') + '</button>' : '') + '</div>'
            + (!ro.runsToday ? '<p class="ha-dt-warn">This batch doesn’t usually run on ' + (dl === 'Today' || dl === 'Yesterday' ? 'this day' : dl.split(',')[0] + 's') + '. You can still mark it.</p>' : '')
            + '</section>';
        if (!total) out += '<p class="ha-dt-note">Nobody on this batch yet.' + (canDesk() ? ' Enrol the first student below.' : '') + '</p>';
        out += ro.students.map(function (s) {
            var now = presentOf(s), fl = feeLine(s);
            var recent = (s.recent || []).slice();
            if (ro.runsToday && recent.length) recent[recent.length - 1] = now;
            return '<div class="ha-dt-stu' + (now ? ' is-in' : '') + '"><button type="button" class="ha-dt-box" data-mark="' + s.id + '"' + (canDesk() ? '' : ' disabled') + ' aria-label="' + esc(now ? s.name + ' is here' : 'Mark ' + s.name + ' here') + '">'
                + '<svg viewBox="0 0 34 34" aria-hidden="true"><rect x="1" y="1" width="32" height="32" rx="8" fill="' + (now ? 'rgba(22,163,74,.1)' : '#fff') + '" stroke="' + (now ? P.green : '#CBD5E1') + '" stroke-width="1.8"/>'
                + '<path class="ha-dt-ink" d="M7.5 18.4Q11.6 21.1 14.3 25.8Q19.7 14.3 28.6 6.8" fill="none" stroke="' + P.green + '" stroke-width="3.2" stroke-linecap="round" pathLength="1" style="stroke-dashoffset:' + (now ? 0 : 1) + '"/></svg></button>'
                + '<button type="button" class="ha-dt-stuinfo" data-student="' + s.id + '"><b>' + esc(s.name) + '</b><small style="color:' + fl[1] + '">' + esc(fl[0]) + '</small>'
                + (recent.length ? '<span class="ha-dt-recent">' + recent.map(function (x) { return '<i class="' + (x ? 'is-on' : '') + '"></i>'; }).join('') + '</span>' : '') + '</button></div>';
        }).join('');
        host.innerHTML = subPage(b.name, canDefine() ? '<button type="button" class="ha-dt-textbtn" data-edit>Edit</button>' : '', out)
            + (canDesk() && b.is_active !== false ? '<div class="ha-dt-dock"><button type="button" class="ha-dt-cta" data-enrol>Enrol a student</button></div>' : '');
    }

    function markOne(id, now) {
        acad.local[id] = now;
        haptic(now ? 'confirm' : 'tick');
        var row = acad.host.querySelector('[data-mark="' + id + '"]');
        if (row) {
            var wrap = row.parentNode;
            wrap.classList.toggle('is-in', now);
            row.querySelector('rect').setAttribute('fill', now ? 'rgba(22,163,74,.1)' : '#fff');
            row.querySelector('rect').setAttribute('stroke', now ? P.green : '#CBD5E1');
            row.querySelector('.ha-dt-ink').style.strokeDashoffset = now ? 0 : 1;
        }
        updateHere();
        A.apiSend('POST', 'academy/attendance', { enrollmentId: id, date: acad.date, present: now }).catch(function (e) {
            acad.local[id] = !now; haptic('reject'); A.toast(e.message || 'Couldn’t mark that'); renderRoster();
        });
    }
    function updateHere() {
        var ro = acad.roster, host = acad.host;
        var here = ro.students.filter(presentOf).length, total = ro.students.length, c = sportColor(ro.batch.sport);
        host.querySelectorAll('.ha-dt-seats i').forEach(function (i, k) { i.style.background = k < here ? c : c + '1f'; });
        var line = host.querySelector('.ha-dt-reg .ha-dt-line b');
        if (line) line.textContent = total === 0 ? 'No students' : here + ' of ' + total + ' here';
        var all = host.querySelector('[data-all]');
        if (all) { all.dataset.all = here === total ? '0' : '1'; all.textContent = here === total ? 'Clear all' : 'Mark everyone here'; }
    }

    /** Six months as tiles: covered green, behind hatched red, this month outlined (FeeStrip). */
    function feeStrip(paidUntil, pop) {
        var until = paidUntil ? new Date(paidUntil + 'T12:00:00') : null, now = new Date(), out = '';
        for (var off = -2; off <= 3; off++) {
            var m = new Date(now.getFullYear(), now.getMonth() + off, 1), end = new Date(now.getFullYear(), now.getMonth() + off + 1, 0);
            var covered = until && until >= end, partly = until && !covered && until >= m;
            var isNow = off === 0, behind = !covered && !partly && m <= now && until;
            var cls = covered ? 'is-covered' : partly ? (until < now ? 'is-partlate' : 'is-part') : behind ? 'is-behind' : '';
            out += '<span class="' + cls + (isNow ? ' is-now' : '') + (pop && (covered || partly) ? ' is-pop' : '') + '"><i></i><small>' + MONTHS[m.getMonth()] + '</small></span>';
        }
        return '<div class="ha-dt-feestrip">' + out + '</div>';
    }

    function openStudent(id, pop) {
        var st = { months: 1, method: 'cash', busy: false, error: null, d: null };
        var s = A.sheet('<div class="ha-dt-sheet ha-dt-stusheet"><div class="ha-dt-skel"><b style="height:240px"></b></div></div>', 'ha-dt-bs ha-dt-tall');
        s.onclose = function () { if (acad.batchId != null) loadRoster(); };
        var batchName = (acad.roster && acad.roster.batch.name) || 'Batch';
        function load(popNow) {
            A.api('academy/enrollments/' + id, { branch: false, fresh: true }).then(function (r) { st.d = r; draw(popNow); },
                function (e) { s.querySelector('.ha-dt-sheet').innerHTML = '<p class="ha-dt-err">' + esc(e.message || 'Couldn’t load') + '</p>'; });
        }
        function draw(popNow) {
            var d = st.d, stu = d.student, fee = (d.batch && d.batch.monthly_fee) || 0, fl = feeLine(stu), pays = d.payments || [], att = d.attendance || [];
            var came = att.filter(function (a) { return a.present; }).length;
            var out = '<div class="ha-dt-stuhead"><span class="ha-dt-av">' + esc(initials(stu.name)) + '</span><div><h3>' + esc(stu.name) + '</h3><small>' + esc(batchName) + ' · +91 ' + esc(stu.phone) + '</small></div></div>'
                + '<p class="ha-dt-feeline" style="color:' + fl[1] + '">' + esc(fl[0]) + '</p>' + feeStrip(stu.paid_until, popNow);
            if (canDesk()) {
                out += section('Collect fees') + '<div class="ha-dt-chips4">' + [1, 2, 3, 6].map(function (m) {
                    return '<button type="button" class="ha-dt-chip' + (st.months === m ? ' is-on' : '') + '" data-months="' + m + '">' + (m === 1 ? '1 month' : m + ' months') + '</button>';
                }).join('') + '</div>' + methodPicker(st.method)
                    + (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                    + '<button type="button" class="ha-dt-cta" data-collect' + (st.busy ? ' disabled' : '') + '>' + (st.busy ? 'Collecting…' : 'Collect ' + money(fee * st.months) + ' · ' + payMethodLabel(st.method)) + '</button>';
            }
            if (att.length) out += section('Came to ' + came + ' of the last ' + att.length + ' classes') + '<span class="ha-dt-recent is-big">' + att.map(function (a) { return '<i class="' + (a.present ? 'is-on' : '') + '"></i>'; }).join('') + '</span>';
            var digits = String(stu.phone || '').replace(/\D/g, '').slice(-10);
            var msg = stu.overdue || stu.due_soon ? 'Hi, a reminder that ' + stu.name + '’s ' + batchName + ' fees ' + (stu.overdue ? 'were due on ' : 'are due on ') + shortDate(stu.paid_until) + '. ' + money(fee) + ' a month. Thank you!'
                : 'Hi, this is about ' + stu.name + '’s ' + batchName + ' classes.';
            out += '<div class="ha-dt-chips2"><a class="ha-dt-chip" href="https://wa.me/91' + digits + '?text=' + encodeURIComponent(msg) + '" target="_blank" rel="noopener">WhatsApp</a><a class="ha-dt-chip" href="tel:+91' + digits + '">Call</a></div>';
            if (pays.length) {
                out += section('Fees paid') + pays.map(function (p) {
                    return '<div class="ha-dt-ledger"><div><b>' + esc(p.method === 'opening' ? 'Earlier payments' : (p.months === 1 ? '1 month' : p.months + ' months') + ' · ' + payMethodLabel(p.method)) + '</b>'
                        + '<small>' + esc([shortDate(p.collected_at), p.collected_by ? 'by ' + p.collected_by : null, p.paid_until ? 'covers till ' + shortDate(p.paid_until) : null].filter(Boolean).join(' · ')) + '</small></div><strong>' + money(p.amount) + '</strong></div>';
                }).join('');
            }
            if (canDesk()) out += '<button type="button" class="ha-dt-danger" data-remove>Remove from ' + esc(batchName) + '</button>';
            s.querySelector('.ha-dt-sheet').innerHTML = out;
        }
        s.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b || !st.d) return;
            if (b.dataset.months) { haptic('tick'); st.months = +b.dataset.months; draw(); }
            else if (b.dataset.method) { haptic('tick'); st.method = b.dataset.method; draw(); }
            else if (b.hasAttribute('data-collect')) {
                st.busy = true; st.error = null; draw();
                A.apiSend('POST', 'academy/enrollments/' + id + '/fee', { months: st.months, paymentMethod: st.method }).then(function () {
                    haptic('money'); st.busy = false; load(true);
                }, function (err) { haptic('reject'); st.busy = false; st.error = err.message; draw(); });
            } else if (b.hasAttribute('data-remove')) {
                var nm = st.d.student.name;
                confirm('Remove ' + nm + '?', 'Their seat frees up. Fees and attendance stay on record, and enrolling the same number again brings them back.', 'Remove', P.red, 'Keep').then(function (ok) {
                    if (!ok) return;
                    A.apiSend('POST', 'academy/enrollments/' + id + '/remove', {}).then(function () {
                        haptic('reject'); s.close(); A.toast(nm + ' removed from ' + batchName);
                    }, function (err) { haptic('reject'); st.error = err.message; draw(); });
                });
            }
        });
        load(pop);
    }

    function openEnrol() {
        var b = acad.roster.batch;
        var st = { name: '', phone: '', months: 1, method: 'cash', busy: false, error: null };
        var full = b.capacity != null && b.students >= b.capacity;
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function foot() {
            var ok = st.name.trim() && st.phone.length === 10 && !st.busy;
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-enrol' + (ok ? '' : ' disabled') + '>' + (st.busy ? 'Enrolling…' : 'Enrol and collect ' + money(b.monthly_fee * st.months)) + '</button>';
        }
        function draw() {
            s.querySelector('.ha-dt-formbody').innerHTML = '<h3 class="ha-dt-formtitle">Enrol in ' + esc(b.name) + '</h3>'
                + '<p class="ha-dt-note" style="color:' + (full ? P.red : P.muted) + ';margin-top:-8px">' + money(b.monthly_fee) + ' a month' + (b.capacity != null ? ' · ' + b.students + ' of ' + b.capacity + ' seats taken' : '') + '</p>'
                + field('name', 'Student name', st.name, 'text', ' autocapitalize="words"')
                + '<label class="ha-dt-field"><span>Parent’s or student’s mobile</span><em>+91</em><input name="phone" type="tel" inputmode="numeric" value="' + esc(st.phone) + '" autocomplete="off"></label>'
                + '<p class="ha-dt-note">Already on this batch? Their fees are extended instead of adding them twice.</p>'
                + section('Paying for') + '<div class="ha-dt-chips4">' + [1, 2, 3, 6].map(function (m) {
                    return '<button type="button" class="ha-dt-chip' + (st.months === m ? ' is-on' : '') + '" data-months="' + m + '">' + (m === 1 ? '1 month' : m + ' months') + '</button>';
                }).join('') + '</div>' + methodPicker(st.method);
            foot();
        }
        s.addEventListener('input', function (e) {
            if (e.target.name === 'name') st.name = e.target.value;
            if (e.target.name === 'phone') { st.phone = e.target.value.replace(/\D/g, '').slice(0, 10); e.target.value = st.phone; if (st.phone.length === 10) haptic('confirm'); }
            st.error = null; foot();
        });
        s.addEventListener('click', function (e) {
            var x = e.target.closest('button');
            if (!x) return;
            if (x.dataset.months) { haptic('tick'); st.months = +x.dataset.months; draw(); }
            else if (x.dataset.method) { haptic('tick'); st.method = x.dataset.method; draw(); }
            else if (x.hasAttribute('data-enrol')) {
                st.busy = true; st.error = null; foot();
                A.apiSend('POST', 'academy/' + b.id + '/enroll', { studentName: st.name.trim(), studentPhone: st.phone, months: st.months, paymentMethod: st.method }).then(function () {
                    haptic('money'); s.close(); A.toast(st.name.trim() + ' enrolled · ' + money(b.monthly_fee * st.months) + ' collected'); loadRoster(); loadAcademy();
                }, function (err) { haptic('reject'); st.busy = false; st.error = err.message; foot(); });
            }
        });
        draw();
    }

    function editBatch(existing) {
        var venues = (acad.page && acad.page.venues) || [];
        var st = {
            name: existing ? existing.name : '', coach: existing && existing.coach || '', sport: existing && existing.sport || null,
            days: existing && existing.days ? existing.days.slice() : [], start: existing && existing.start_time || '', end: existing && existing.end_time || '',
            fee: existing && existing.monthly_fee > 0 ? String(existing.monthly_fee) : '', cap: existing && existing.capacity != null ? String(existing.capacity) : '',
            venueId: existing && existing.venue_id || (venues.length === 1 ? venues[0].id : null), active: existing ? existing.is_active !== false : true, busy: false, error: null,
        };
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function okTimes() {
            var a = minutesOf(st.start), b2 = minutesOf(st.end);
            return (!st.start.trim() || a != null) && (!st.end.trim() || (b2 != null && (a == null || b2 > a)));
        }
        function foot() {
            var ok = st.name.trim() && st.fee && okTimes() && !st.busy;
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-save' + (ok ? '' : ' disabled') + '>' + (st.busy ? 'Saving…' : existing ? 'Save changes' : 'Create batch') + '</button>';
            var w = s.querySelector('[data-timewarn]');
            if (w) w.hidden = okTimes();
        }
        function draw() {
            var a = document.activeElement, nm = a && a.name;
            var order = st.days.slice().sort(function (x, y) { return WEEK.indexOf(x) - WEEK.indexOf(y); });
            var out = '<h3 class="ha-dt-formtitle">' + (existing ? 'Edit ' + esc(existing.name) : 'New batch') + '</h3>'
                + field('name', 'Batch name, e.g. Juniors evening', st.name) + field('coach', 'Coach', st.coach, 'text', ' autocapitalize="words"')
                + section('Sport') + '<div class="ha-dt-flow">' + SPORTS.map(function (sp) {
                    return '<button type="button" class="ha-dt-chip' + (String(st.sport || '').toLowerCase() === sp.toLowerCase() ? ' is-on' : '') + '" data-sport="' + sp + '">' + sp + '</button>';
                }).join('') + '</div>'
                + section('Days') + '<div class="ha-dt-weekpick">' + WEEK.map(function (d) {
                    return '<button type="button" class="' + (st.days.indexOf(d) >= 0 ? 'is-on' : '') + '" data-wday="' + d + '">' + d.slice(0, 2) + '</button>';
                }).join('') + '</div><p class="ha-dt-note">' + (order.length ? order.join(', ') : 'No days picked: the batch runs every day.') + '</p>'
                + '<div class="ha-dt-pair">' + field('start', 'Starts', st.start, 'time') + field('end', 'Ends', st.end, 'time') + '</div>'
                + '<p class="ha-dt-err" data-timewarn' + (okTimes() ? ' hidden' : '') + '>The class has to end after it starts.</p>'
                + '<div class="ha-dt-pair"><label class="ha-dt-field"><span>Fee a month</span><em>₹</em><input name="fee" type="text" inputmode="numeric" value="' + esc(st.fee) + '"></label>'
                + field('cap', 'Seats (optional)', st.cap, 'text', ' inputmode="numeric"') + '</div>';
            if (venues.length > 1) {
                out += section('Where it runs') + '<div class="ha-dt-flow">' + venues.map(function (v) {
                    return '<button type="button" class="ha-dt-chip' + (st.venueId === v.id ? ' is-on' : '') + '" data-venue="' + v.id + '">' + esc(v.name) + '</button>';
                }).join('') + '</div><p class="ha-dt-note">Fees taken for this batch go into that venue’s cash drawer.</p>';
            }
            if (existing) {
                out += '<label class="ha-dt-switch is-big"><span><b>Taking classes</b><small>' + (st.active ? 'Shows on the desk every day it runs.' : 'Paused: hidden from today’s board.') + '</small></span><input type="checkbox" switch data-active' + (st.active ? ' checked' : '') + '><i></i></label>';
            }
            s.querySelector('.ha-dt-formbody').innerHTML = out;
            if (nm) { var el = s.querySelector('input[name="' + nm + '"]'); if (el) el.focus({ preventScroll: true }); }
            foot();
        }
        s.addEventListener('input', function (e) {
            var t = e.target, n = t.name;
            if (!n) return;
            if (n === 'fee') { st.fee = t.value.replace(/\D/g, '').slice(0, 6); t.value = st.fee; }
            else if (n === 'cap') { st.cap = t.value.replace(/\D/g, '').slice(0, 3); t.value = st.cap; }
            else st[n] = t.value;
            st.error = null; foot();
        });
        s.addEventListener('change', function (e) { if (e.target.hasAttribute('data-active')) { haptic('tick'); st.active = e.target.checked; draw(); } });
        s.addEventListener('click', function (e) {
            var x = e.target.closest('button');
            if (!x) return;
            if (x.dataset.sport) { haptic('tick'); st.sport = String(st.sport || '').toLowerCase() === x.dataset.sport.toLowerCase() ? null : x.dataset.sport; draw(); }
            else if (x.dataset.wday) { haptic('tick'); var d = x.dataset.wday; st.days = st.days.indexOf(d) >= 0 ? st.days.filter(function (y) { return y !== d; }) : st.days.concat([d]); draw(); }
            else if (x.dataset.venue) { haptic('tick'); st.venueId = +x.dataset.venue; draw(); }
            else if (x.hasAttribute('data-save')) {
                st.busy = true; st.error = null; foot();
                A.apiSend('POST', existing ? 'academy/' + existing.id : 'academy', {
                    name: st.name.trim(), coachName: st.coach.trim() || null, sport: st.sport,
                    days: st.days.slice().sort(function (a, b) { return WEEK.indexOf(a) - WEEK.indexOf(b); }),
                    startTime: st.start.trim() || null, endTime: st.end.trim() || null, monthlyFee: parseInt(st.fee, 10) || 0,
                    capacity: st.cap ? parseInt(st.cap, 10) : null, venueId: st.venueId, isActive: st.active,
                }).then(function () {
                    haptic('confirm'); s.close(); A.toast(existing ? 'Saved' : st.name.trim() + ' added');
                    loadAcademy(); if (acad.batchId != null) loadRoster();
                }, function (err) { haptic('reject'); st.busy = false; st.error = err.message; foot(); });
            }
        });
        draw();
    }

    A.register('academy', {
        tool: true,
        enter: function (host) {
            acad.host = host; acad.batchId = null;
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-sub-back]')) { haptic('tick'); acad.batchId = null; acad.roster = null; renderAcademy(); loadAcademy(); window.scrollTo(0, 0); return; }
                if (e.target.closest('[data-add]')) { haptic('tick'); editBatch(null); return; }
                if ((b = e.target.closest('[data-filter]'))) { haptic('tick'); acad.filter = acad.filter === b.dataset.filter ? null : b.dataset.filter; renderAcademy(); return; }
                if ((b = e.target.closest('[data-batch]'))) { haptic('tick'); openBatch(+b.dataset.batch); window.scrollTo(0, 0); return; }
                if ((b = e.target.closest('[data-day]'))) { if (b.disabled) return; haptic('tick'); acad.date = shiftIso(acad.date, +b.dataset.day); loadRoster(); return; }
                if ((b = e.target.closest('[data-mark]'))) { var id = +b.dataset.mark, s = acad.roster.students.filter(function (x) { return x.id === id; })[0]; if (s) markOne(id, !presentOf(s)); return; }
                if ((b = e.target.closest('[data-all]'))) {
                    var allIn = b.dataset.all === '1';
                    b.disabled = true; b.textContent = 'Marking…';
                    A.apiSend('POST', 'academy/' + acad.batchId + '/attendance/all', { date: acad.date, present: allIn }).then(function () { haptic(allIn ? 'confirm' : 'tick'); loadRoster(); },
                        function (err) { haptic('reject'); A.toast(err.message); renderRoster(); });
                    return;
                }
                if ((b = e.target.closest('[data-student]'))) { haptic('tick'); openStudent(+b.dataset.student); return; }
                if (e.target.closest('[data-enrol]')) { haptic('tick'); openEnrol(); return; }
                if (e.target.closest('[data-edit]')) { haptic('tick'); editBatch(acad.roster ? acad.roster.batch : batchById(acad.batchId)); }
            };
            if (acad.page) renderAcademy();
            loadAcademy();
        },
        leave: function () { acad.host = null; acad.batchId = null; acad.roster = null; },
    });

    /* ================================================================== PACKAGES === */

    var TONES = ['#2563EB', '#0D9488', '#7C3AED', '#EA580C', '#0284C7', '#DB2777'];
    function toneFor(id) { return TONES[Math.abs(+id || 0) % TONES.length]; }
    function holderLine(hd) {
        if (hd.expired) return [('Expired ' + (shortDate(hd.expires_at) || '')).trim(), P.red];
        if (hd.expiring_soon && hd.expires_in_days != null) return [hd.expires_in_days <= 0 ? 'Expires today' : hd.expires_in_days === 1 ? 'Expires tomorrow' : 'Expires in ' + hd.expires_in_days + ' days', P.amber];
        if (hd.expires_at) return ['Valid till ' + shortDate(hd.expires_at), P.muted];
        return ['Never expires', P.muted];
    }

    /**
     * A punch card: a ticket with a notch either side, a coloured band and one hole per
     * session. Punched holes are cut out (class ha-dt-punch, so they can punch in one by one).
     */
    function punchCard(w, hgt, tone, holes, punched, faded, staggerPunch) {
        var r = 10, notch = 7, band = hgt * 0.26, t = faded ? P.faint : tone, mid = band + (hgt - band) / 2;
        var out = '<rect y="3" width="' + w + '" height="' + f(hgt) + '" rx="' + r + '" fill="rgba(15,23,42,.08)"/>'
            + '<rect width="' + w + '" height="' + f(hgt) + '" rx="' + r + '" fill="#fff"/>'
            + '<path d="M0 ' + r + 'a' + r + ' ' + r + ' 0 0 1 ' + r + '-' + r + 'h' + (w - 2 * r) + 'a' + r + ' ' + r + ' 0 0 1 ' + r + ' ' + r + 'v' + f(band - r) + 'H0z" fill="' + t + '"/>'
            + '<rect x="10" y="' + f(band * 0.38) + '" width="' + f(w * 0.32) + '" height="4" rx="2" fill="rgba(255,255,255,.85)"/>'
            + '<rect x=".6" y=".6" width="' + (w - 1.2) + '" height="' + f(hgt - 1.2) + '" rx="' + r + '" fill="none" stroke="' + t + '" stroke-opacity=".6" stroke-width="1.2"/>'
            + '<circle cx="0" cy="' + f(mid) + '" r="' + notch + '" fill="#F6F8FC" stroke="#E2E8F0"/><circle cx="' + w + '" cy="' + f(mid) + '" r="' + notch + '" fill="#F6F8FC" stroke="#E2E8F0"/>';
        var n = Math.max(1, Math.min(30, holes || 1)), aw = w - 34, ah = hgt - band - 16;
        var guess = Math.max(1, Math.min(n, Math.ceil(Math.sqrt(n * aw / ah)))), rows = Math.ceil(n / guess), cols = Math.ceil(n / rows);
        var gx = aw / cols, gy = ah / rows, hr = Math.min(Math.min(gx, gy) * 0.3, 7);
        for (var i = 0; i < n; i++) {
            var cx = 17 + gx * (i % cols) + gx / 2, cy = band + 8 + gy * Math.floor(i / cols) + gy / 2;
            out += '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(hr) + '" fill="none" stroke="' + t + '" stroke-opacity=".55" stroke-width="1.4"/>';
            if (i < punched) out += '<circle class="' + (staggerPunch ? 'ha-dt-punch' : '') + '" style="animation-delay:' + (staggerPunch ? 200 + i * 90 : 0) + 'ms" cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(hr + 0.6) + '" fill="#F6F8FC" stroke="rgba(15,23,42,.13)"/>';
        }
        return out;
    }
    function cardSvg(w, hgt, tone, holes, punched, faded, cls, stagger) {
        return '<svg class="' + (cls || '') + '" viewBox="-8 -2 ' + (w + 16) + ' ' + (hgt + 8) + '" aria-hidden="true">' + punchCard(w, hgt, tone, holes, punched, faded, stagger) + '</svg>';
    }

    var pk = { host: null, page: null, query: '', expiring: false, venues: null };

    function loadPackages() {
        if (!pk.host) return;
        if (!pk.page) pk.host.innerHTML = page('Packages', '', skeleton(230, 3));
        Promise.all([A.api('packages', { fresh: true }), pk.venues ? null : A.soft(A.api('venues', { branch: false }))]).then(function (r) {
            if (!pk.host) return;
            if (r[1]) pk.venues = list(r[1]);
            pk.page = { packages: list(r[0]), holders: r[0].holders || [], summary: r[0].summary || {} };
            renderPackages();
        }, function (e) { if (pk.host && !pk.page) failed(pk.host, 'Packages', e.message, loadPackages); });
    }

    function renderPackages() {
        var host = pk.host, p = pk.page;
        if (!host || !p) return;
        var s = p.summary;
        if (!p.packages.length) {
            host.innerHTML = page('Packages', '', '<div class="ha-dt-empty"><div class="ha-dt-deal is-one">' + cardSvg(150, 92, P.blue, 10, 0) + '</div><b>No offers yet</b>'
                + '<p>Sell prepaid passes like “10 hours for ₹4,000”. The money comes in today, and the desk spends one session per visit from the walk-in sheet.</p>'
                + (canDefine() ? '<button type="button" class="ha-dt-cta" data-add>Create your first offer</button>' : '') + '</div>');
            return;
        }
        var tones = p.packages.slice(0, 3).map(function (o) { return toneFor(o.id); });
        if (!tones.length) tones = [P.blue];
        var fan = [[-11, -46], [9, 46], [0, 0]].map(function (a, i) {
            return '<div class="ha-dt-fancard" style="--a:' + a[0] + 'deg;--x:' + a[1] + 'px;animation-delay:' + (i * 70) + 'ms">' + cardSvg(150, 92, tones[i % tones.length], 10, i === 2 ? 4 : 0) + '</div>';
        }).join('');
        var q = pk.query.trim().toLowerCase();
        var holders = p.holders.filter(function (hd) { return !pk.expiring || hd.expiring_soon; })
            .filter(function (hd) { return !q || String(hd.name).toLowerCase().indexOf(q) >= 0 || String(hd.phone).indexOf(q) >= 0 || String(hd.package).toLowerCase().indexOf(q) >= 0; });
        var toneOf = {};
        p.packages.forEach(function (o) { toneOf[o.name] = toneFor(o.id); });
        var out = '<section class="ha-dt-card ha-dt-hero"><div class="ha-dt-fan">' + fan + '</div>'
            + '<div class="ha-dt-herofoot"><div><b>' + (s.active_holders === 1 ? '1 pass in use' : (s.active_holders || 0) === 0 ? 'No passes in use' : s.active_holders + ' passes in use') + '</b>'
            + '<small>' + (s.sold_month ? s.sold_month + ' sold this month' : 'None sold this month yet') + '</small></div>'
            + '<div class="is-end"><b data-to="' + (s.sold_month_amount || 0) + '">' + money(s.sold_month_amount) + '</b><small>passes sold this month</small></div></div>'
            + (s.expiring_soon > 0 ? '<div class="ha-dt-feechips"><button type="button" class="ha-dt-feechip' + (pk.expiring ? ' is-on' : '') + '" data-expiring style="--c:' + P.amber + '"><i></i>' + (s.expiring_soon === 1 ? '1 pass expiring this week' : s.expiring_soon + ' passes expiring this week') + '</button></div>' : '<div style="height:14px"></div>')
            + '</section>' + section('Offers')
            + p.packages.map(function (o) {
                var meta = [o.sessions + ' sessions', o.validity_days ? 'valid ' + o.validity_days + ' days' : 'no expiry', o.venue ? 'only ' + o.venue : null].filter(Boolean).join(' · ');
                return '<button type="button" class="ha-dt-row ha-dt-offer" data-offer="' + o.id + '">' + cardSvg(150, 100, toneFor(o.id), o.sessions, 0, o.is_active === false, 'ha-dt-minicard')
                    + '<span class="ha-dt-rowmid"><span class="ha-dt-line"><b' + (o.is_active === false ? ' class="is-muted"' : '') + '>' + esc(o.name) + '</b>' + (o.is_active === false ? tag('Paused', P.muted) : '') + '</span>'
                    + '<small>' + esc(meta) + '</small><span class="ha-dt-line"><strong>' + money(o.price) + '</strong><small class="is-faint">&nbsp;&nbsp;' + money(o.per_session) + '/session</small><span class="ha-grow"></span>' + (o.sold > 0 ? '<small>' + o.sold + ' sold</small>' : '') + '</span></span></button>';
            }).join('')
            + section(pk.expiring ? 'Expiring this week' : 'On a pass now')
            + (p.holders.length > 4 ? '<label class="ha-dt-search"><input type="search" placeholder="Search name, number or pass" value="' + esc(pk.query) + '" data-q></label>' : '')
            + '<div data-holders>' + holdersHtml(holders, toneOf, p.holders.length) + '</div>';
        host.innerHTML = page('Packages', canDefine() ? addPill('New offer') : '', out);
        countUp(host.querySelector('.ha-dt-herofoot [data-to]'), 'packages.month');
    }

    function holdersHtml(holders, toneOf, total) {
        if (!holders.length) return '<p class="ha-dt-note">' + (total ? 'Nobody matches.' : 'Nobody holds a pass yet. Sell one from an offer above.') + '</p>';
        return holders.map(function (hd) {
            var t = toneOf[hd.package] || P.blue, hl = holderLine(hd), n = Math.max(1, Math.min(40, hd.total || 1));
            var used = Math.floor(((hd.used || 0) / Math.max(1, hd.total || 1)) * n), strip = '';
            for (var i = 0; i < n; i++) strip += '<u style="background:' + (i < used ? '#E2E8F0' : t) + '"></u>';
            return '<button type="button" class="ha-dt-holder' + (hd.expiring_soon ? ' is-soon' : '') + '" data-holder="' + hd.id + '"><span class="ha-dt-line"><span class="ha-dt-rowmid"><b>' + esc(hd.name) + '</b><small>' + esc(hd.package + ' · +91 ' + hd.phone) + '</small></span>'
                + '<span class="ha-dt-rowamt"><b style="color:' + (hd.usable ? P.ink : P.red) + '">' + hd.remaining + ' left</b><small>of ' + hd.total + '</small></span></span>'
                + '<span class="ha-dt-strip">' + strip + '</span><small style="color:' + hl[1] + '">' + esc(hl[0]) + '</small></button>';
        }).join('');
    }

    function openOffer(o) {
        var t = toneFor(o.id), active = o.is_active !== false;
        var s = A.sheet('<div class="ha-dt-sheet is-center"><div class="ha-dt-lift">' + cardSvg(280, 168, t, o.sessions, 0, !active, 'ha-dt-bigcard') + '</div>'
            + '<h3>' + esc(o.name) + '</h3><p>' + o.sessions + ' sessions for ' + money(o.price) + ' · ' + money(o.per_session) + ' each</p>'
            + '<p class="is-faint">' + esc([o.validity_days ? 'Valid ' + o.validity_days + ' days from purchase' : 'Never expires', o.venue ? 'only at ' + o.venue : 'any branch'].join(' · ')) + '</p>'
            + (o.sold > 0 ? '<p>' + o.sold + ' sold so far' + (o.sold_month > 0 ? ' · ' + o.sold_month + ' this month' : '') + '</p>' : '')
            + (canDesk() && active ? '<button type="button" class="ha-dt-cta" data-sell>Sell to a customer</button>' : '')
            + (canDefine() ? '<div class="ha-dt-chips2"><button type="button" class="ha-dt-chip" data-edit>Edit</button><button type="button" class="ha-dt-chip" data-toggle>' + (active ? 'Pause sales' : 'Resume sales') + '</button></div>' : '')
            + '</div>', 'ha-dt-bs');
        s.addEventListener('click', function (e) {
            if (e.target.closest('[data-sell]')) { haptic('tick'); s.close(); sellOffer(o); }
            else if (e.target.closest('[data-edit]')) { haptic('tick'); s.close(); editOffer(o); }
            else if (e.target.closest('[data-toggle]')) {
                haptic('tick'); s.close();
                act(function () { return A.apiSend('POST', 'packages/' + o.id, { name: o.name, price: o.price, sessions: o.sessions, validityDays: o.validity_days == null ? null : o.validity_days, venueId: o.venue_id || null, isActive: !active }); },
                    active ? o.name + ' paused. Passes already sold still work.' : o.name + ' is on sale again', loadPackages);
            }
        });
    }

    function sellOffer(o) {
        var st = { name: '', phone: '', method: 'cash', busy: false, error: null };
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function foot() {
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-go' + (st.phone.length === 10 && !st.busy ? '' : ' disabled') + '>' + (st.busy ? 'Selling…' : 'Take ' + money(o.price) + ' · ' + payMethodLabel(st.method)) + '</button>';
        }
        function draw() {
            s.querySelector('.ha-dt-formbody').innerHTML = '<h3 class="ha-dt-formtitle">Sell ' + esc(o.name) + '</h3><p class="ha-dt-note" style="margin-top:-8px">' + o.sessions + ' sessions · ' + money(o.price) + '</p>'
                + field('name', 'Customer name', st.name, 'text', ' autocapitalize="words"')
                + '<label class="ha-dt-field"><span>Mobile</span><em>+91</em><input name="phone" type="tel" inputmode="numeric" value="' + esc(st.phone) + '" autocomplete="off"></label>'
                + '<p class="ha-dt-note">The desk finds this pass by the mobile number when they come to play.</p>' + section('Paid by') + methodPicker(st.method);
            foot();
        }
        s.addEventListener('input', function (e) {
            if (e.target.name === 'name') st.name = e.target.value;
            if (e.target.name === 'phone') { st.phone = e.target.value.replace(/\D/g, '').slice(0, 10); e.target.value = st.phone; if (st.phone.length === 10) haptic('confirm'); }
            st.error = null; foot();
        });
        s.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.method) { haptic('tick'); st.method = b.dataset.method; draw(); }
            else if (b.hasAttribute('data-go')) {
                st.busy = true; foot();
                var body = { customerPhone: st.phone, customerName: st.name.trim(), paymentMethod: st.method };
                if (A.cfg.branch) body.venueId = A.cfg.branch;
                A.apiSend('POST', 'packages/' + o.id + '/sell', body).then(function () {
                    haptic('money'); s.close(); A.toast(o.name + ' sold to ' + (st.name.trim() || '+91 ' + st.phone) + ' · ' + money(o.price) + ' ' + payMethodLabel(st.method)); loadPackages();
                }, function (err) { haptic('reject'); st.busy = false; st.error = err.message; foot(); });
            }
        });
        draw();
    }

    function editOffer(existing) {
        var presets = [30, 60, 90, 180], venues = pk.venues || [];
        var vd = existing ? existing.validity_days : null;
        var st = {
            name: existing ? existing.name : '', price: existing ? String(existing.price) : '', sessions: existing ? String(existing.sessions) : '',
            validity: vd == null ? null : vd, custom: vd != null && presets.indexOf(vd) < 0, customDays: vd != null && presets.indexOf(vd) < 0 ? String(vd) : '',
            venueId: existing ? existing.venue_id || null : null, active: existing ? existing.is_active !== false : true, busy: false, error: null,
        };
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function days() { return st.custom ? (parseInt(st.customDays, 10) || null) : st.validity; }
        function ok() { var p = parseInt(st.price, 10) || 0, n = parseInt(st.sessions, 10) || 0; return st.name.trim() && p > 0 && n >= 1 && n <= 500 && (!st.custom || days() > 0) && !st.busy; }
        function foot() {
            var p = parseInt(st.price, 10) || 0, n = parseInt(st.sessions, 10) || 0, per = s.querySelector('[data-per]');
            if (per) per.textContent = p > 0 && n > 0 ? money(Math.floor(p / n)) + ' a session' : '';
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-save' + (ok() ? '' : ' disabled') + '>' + (st.busy ? 'Saving…' : existing ? 'Save changes' : 'Put it on sale') + '</button>';
        }
        function draw() {
            var a = document.activeElement, nm = a && a.name;
            var out = '<h3 class="ha-dt-formtitle">' + (existing ? 'Edit ' + esc(existing.name) : 'New offer') + '</h3>'
                + (existing && existing.sold > 0 ? '<p class="ha-dt-note" style="margin-top:-8px">Changes apply to new sales. The ' + existing.sold + ' passes already sold keep their terms.</p>' : '')
                + field('name', 'Name, e.g. 10 Hour Pass', st.name)
                + '<div class="ha-dt-pair"><label class="ha-dt-field"><span>Price</span><em>₹</em><input name="price" type="text" inputmode="numeric" value="' + esc(st.price) + '"></label>'
                + field('sessions', 'Sessions', st.sessions, 'text', ' inputmode="numeric"') + '</div><p class="ha-dt-note" data-per></p>'
                + section('Valid for') + '<div class="ha-dt-flow"><button type="button" class="ha-dt-chip' + (!st.custom && st.validity == null ? ' is-on' : '') + '" data-valid="none">Never expires</button>'
                + presets.map(function (d) { return '<button type="button" class="ha-dt-chip' + (!st.custom && st.validity === d ? ' is-on' : '') + '" data-valid="' + d + '">' + d + ' days</button>'; }).join('')
                + '<button type="button" class="ha-dt-chip' + (st.custom ? ' is-on' : '') + '" data-valid="other">Other</button></div>'
                + (st.custom ? field('customDays', 'Days', st.customDays, 'text', ' inputmode="numeric"') : '');
            if (venues.length > 1) {
                out += section('Where it works') + '<div class="ha-dt-flow"><button type="button" class="ha-dt-chip' + (st.venueId == null ? ' is-on' : '') + '" data-venue="">Every branch</button>'
                    + venues.map(function (v) { return '<button type="button" class="ha-dt-chip' + (st.venueId === v.id ? ' is-on' : '') + '" data-venue="' + v.id + '">Only ' + esc(v.name) + '</button>'; }).join('') + '</div>';
            }
            if (existing) out += '<label class="ha-dt-switch is-big"><span><b>On sale</b><small>' + (st.active ? 'The desk can sell it.' : 'Paused: can’t be sold. Passes already sold still work.') + '</small></span><input type="checkbox" switch data-active' + (st.active ? ' checked' : '') + '><i></i></label>';
            s.querySelector('.ha-dt-formbody').innerHTML = out;
            if (nm) { var el = s.querySelector('input[name="' + nm + '"]'); if (el) el.focus({ preventScroll: true }); }
            foot();
        }
        s.addEventListener('input', function (e) {
            var t = e.target, n = t.name;
            if (!n) return;
            if (n === 'price' || n === 'sessions' || n === 'customDays') { st[n] = t.value.replace(/\D/g, '').slice(0, n === 'price' ? 7 : n === 'sessions' ? 3 : 4); t.value = st[n]; }
            else st[n] = t.value;
            st.error = null; foot();
        });
        s.addEventListener('change', function (e) { if (e.target.hasAttribute('data-active')) { haptic('tick'); st.active = e.target.checked; draw(); } });
        s.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.valid) {
                haptic('tick');
                if (b.dataset.valid === 'other') st.custom = true;
                else { st.custom = false; st.validity = b.dataset.valid === 'none' ? null : +b.dataset.valid; }
                draw();
            } else if (b.hasAttribute('data-venue')) { haptic('tick'); st.venueId = b.dataset.venue ? +b.dataset.venue : null; draw(); }
            else if (b.hasAttribute('data-save')) {
                st.busy = true; st.error = null; foot();
                A.apiSend('POST', existing ? 'packages/' + existing.id : 'packages', {
                    name: st.name.trim(), price: parseInt(st.price, 10), sessions: parseInt(st.sessions, 10), validityDays: days(), venueId: st.venueId, isActive: st.active,
                }).then(function () {
                    haptic('confirm'); s.close(); A.toast(existing ? 'Saved. Passes already sold keep their terms.' : st.name.trim() + ' is on sale'); loadPackages();
                }, function (err) { haptic('reject'); st.busy = false; st.error = err.message; foot(); });
            }
        });
        draw();
    }

    function openPass(hd) {
        var offer = (pk.page.packages || []).filter(function (o) { return o.name === hd.package; })[0];
        var t = toneFor(offer ? offer.id : hd.id), hl = holderLine(hd);
        var digits = String(hd.phone || '').replace(/\D/g, '').slice(-10);
        var msg = 'Hi ' + first(hd.name) + ', you have ' + hd.remaining + ' of ' + hd.total + ' sessions left on your ' + hd.package + (hd.expires_at && !hd.expired ? ', valid till ' + shortDate(hd.expires_at) : '') + '. See you soon!';
        var s = A.sheet('<div class="ha-dt-sheet is-center">' + cardSvg(300, 180, t, hd.total, hd.used, !hd.usable, 'ha-dt-bigcard', true)
            + '<h3>' + esc(hd.name) + '</h3><p>' + esc(hd.package + ' · +91 ' + hd.phone) + '</p>'
            + '<div class="ha-dt-facts"><div><small>Sessions left</small><b style="color:' + (hd.usable ? P.ink : P.red) + '">' + hd.remaining + ' of ' + hd.total + '</b></div><div><small>Validity</small><b style="color:' + hl[1] + '">' + esc(hl[0]) + '</b></div></div>'
            + '<div class="ha-dt-uses" data-uses><p class="ha-dt-note">Loading sessions…</p></div>'
            + '<div class="ha-dt-chips2"><a class="ha-dt-chip" href="https://wa.me/91' + digits + '?text=' + encodeURIComponent(msg) + '" target="_blank" rel="noopener">WhatsApp</a><a class="ha-dt-chip" href="tel:+91' + digits + '">Call</a></div>'
            + '<p class="ha-dt-note">To use a session, book a walk-in with this mobile number and pick the pass.</p></div>', 'ha-dt-bs ha-dt-tall');
        A.api('packages/holders/' + hd.id, { branch: false, fresh: true }).then(function (r) {
            var h2 = r.holder || {}, uses = r.uses || [];
            var box = s.querySelector('[data-uses]');
            if (!box) return;
            box.innerHTML = '<p class="ha-dt-note">' + esc([h2.sold_on ? 'Bought ' + shortDate(h2.sold_on) : null, money(h2.amount_paid) + ' by ' + payMethodLabel(h2.payment_method)].filter(Boolean).join(' · ')) + '</p>'
                + section(uses.length ? 'Sessions used' : 'No sessions used yet')
                + uses.map(function (u, i) {
                    return '<p class="ha-dt-use"><i style="color:' + t + ';background:' + t + '1a">' + (uses.length - i) + '</i>' + esc([shortDate(u.date || u.at), u.slot, u.venue].filter(Boolean).join(' · ') || 'Session') + '</p>';
                }).join('');
        }, function (e) { var box = s.querySelector('[data-uses]'); if (box) box.innerHTML = '<p class="ha-dt-err">' + esc(e.message) + '</p>'; });
    }

    A.register('packages', {
        tool: true,
        enter: function (host) {
            pk.host = host;
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-add]')) { haptic('tick'); editOffer(null); return; }
                if (e.target.closest('[data-expiring]')) { haptic('tick'); pk.expiring = !pk.expiring; renderPackages(); return; }
                if ((b = e.target.closest('[data-offer]'))) { haptic('tick'); var o = pk.page.packages.filter(function (x) { return x.id === +b.dataset.offer; })[0]; if (o) openOffer(o); return; }
                if ((b = e.target.closest('[data-holder]'))) { haptic('tick'); var hd = pk.page.holders.filter(function (x) { return x.id === +b.dataset.holder; })[0]; if (hd) openPass(hd); }
            };
            host.oninput = function (e) {
                if (!e.target.hasAttribute('data-q')) return;
                pk.query = e.target.value;
                var toneOf = {};
                pk.page.packages.forEach(function (o) { toneOf[o.name] = toneFor(o.id); });
                var q = pk.query.trim().toLowerCase();
                var hs = pk.page.holders.filter(function (hd) { return !pk.expiring || hd.expiring_soon; })
                    .filter(function (hd) { return !q || String(hd.name).toLowerCase().indexOf(q) >= 0 || String(hd.phone).indexOf(q) >= 0 || String(hd.package).toLowerCase().indexOf(q) >= 0; });
                host.querySelector('[data-holders]').innerHTML = holdersHtml(hs, toneOf, pk.page.holders.length);
            };
            if (pk.page) renderPackages();
            loadPackages();
        },
        leave: function () { pk.host = null; },
    });

    /* =================================================================== PAYOUTS === */

    var po = { host: null, page: null, view: 'main', inv: null, invFilter: null, stampSeen: false };

    function money2(v) {
        var n = Number(v) || 0, paise = Math.round(n * 100) % 100;
        return '₹' + A.inr(Math.floor(n)) + (paise ? '.' + (paise < 10 ? '0' : '') + paise : '');
    }
    function dayMonth(iso) {
        var d = String(iso || '').slice(0, 10).split('-');
        return d.length === 3 ? [String(+d[2]), MONTHS[+d[1] - 1] || ''] : ['—', ''];
    }
    function displayName(s) {
        return /[A-Za-z]/.test(s) && s === s.toUpperCase() ? s.toLowerCase().replace(/\b\w/g, function (c) { return c.toUpperCase(); }) : s;
    }

    function loadPayouts() {
        if (!po.host) return;
        if (!po.page) po.host.innerHTML = page('Payouts', '', skeleton(260, 3));
        A.api('payouts', { branch: false, fresh: true }).then(function (r) {
            if (!po.host) return;
            po.page = r;
            if (po.view === 'main') renderPayouts();
        }, function (e) { if (po.host && !po.page) failed(po.host, 'Payouts', e.message || 'Couldn’t load payouts', loadPayouts); });
    }

    /** Line icons for the money's road: a till, a safe, a bank. */
    function till(cx, cy, s, c) {
        return '<g fill="none" stroke="' + c + '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="' + f(cx - s) + '" y="' + f(cy - s * 0.05) + '" width="' + f(s * 2) + '" height="' + f(s * 0.95) + '" rx="3"/>'
            + '<path d="M' + f(cx - s) + ' ' + f(cy + s * 0.42) + 'H' + f(cx + s) + 'M' + f(cx - s * 0.18) + ' ' + f(cy + s * 0.66) + 'H' + f(cx + s * 0.18) + 'M' + f(cx - s * 0.35) + ' ' + f(cy - s * 0.05) + 'V' + f(cy - s * 0.4) + '"/>'
            + '<rect x="' + f(cx - s * 0.85) + '" y="' + f(cy - s * 0.95) + '" width="' + f(s * 1.1) + '" height="' + f(s * 0.55) + '" rx="2"/><path d="M' + f(cx - s * 0.65) + ' ' + f(cy - s * 0.68) + 'H' + f(cx - s * 0.05) + '" stroke-opacity=".45"/></g>';
    }
    function safe(cx, cy, s, c) {
        return '<g fill="none" stroke="' + c + '" stroke-width="1.7" stroke-linecap="round"><rect x="' + f(cx - s) + '" y="' + f(cy - s) + '" width="' + f(s * 2) + '" height="' + f(s * 1.8) + '" rx="4"/>'
            + '<rect x="' + f(cx - s * 0.72) + '" y="' + f(cy - s * 0.72) + '" width="' + f(s * 1.44) + '" height="' + f(s * 1.24) + '" rx="2" stroke-opacity=".45" stroke-width="1.2"/>'
            + '<circle cx="' + f(cx - s * 0.08) + '" cy="' + f(cy - s * 0.1) + '" r="' + f(s * 0.32) + '"/><path d="M' + f(cx - s * 0.08) + ' ' + f(cy - s * 0.1) + 'V' + f(cy - s * 0.32) + 'M' + f(cx + s * 0.48) + ' ' + f(cy - s * 0.3) + 'V' + f(cy + s * 0.1)
            + 'M' + f(cx - s * 0.7) + ' ' + f(cy + s * 0.8) + 'V' + f(cy + s) + 'M' + f(cx + s * 0.7) + ' ' + f(cy + s * 0.8) + 'V' + f(cy + s) + '"/></g>';
    }
    function bank(cx, cy, s, c) {
        return '<g stroke="' + c + '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M' + f(cx - s * 1.05) + ' ' + f(cy - s * 0.38) + 'L' + f(cx) + ' ' + f(cy - s) + 'L' + f(cx + s * 1.05) + ' ' + f(cy - s * 0.38) + 'Z" fill="' + c + '" fill-opacity=".12"/>'
            + '<path fill="none" d="M' + f(cx - s * 0.62) + ' ' + f(cy - s * 0.18) + 'V' + f(cy + s * 0.6) + 'M' + f(cx) + ' ' + f(cy - s * 0.18) + 'V' + f(cy + s * 0.6) + 'M' + f(cx + s * 0.62) + ' ' + f(cy - s * 0.18) + 'V' + f(cy + s * 0.6) + 'M' + f(cx - s * 1.05) + ' ' + f(cy + s * 0.85) + 'H' + f(cx + s * 1.05) + '"/></g>';
    }
    function coin(cx, cy, r, cls, style) {
        return '<g class="' + (cls || '') + '"' + (style ? ' style="' + style + '"' : '') + '><ellipse cx="' + f(cx) + '" cy="' + f(cy + 1.5) + '" rx="' + f(r) + '" ry="' + f(r * 0.55) + '" fill="#D99A1E"/><ellipse cx="' + f(cx) + '" cy="' + f(cy) + '" rx="' + f(r) + '" ry="' + f(r * 0.55) + '" fill="#F5B83D"/>'
            + '<ellipse cx="' + f(cx) + '" cy="' + f(cy) + '" rx="' + f(r * 0.55) + '" ry="' + f(r * 0.25) + '" fill="none" stroke="rgba(255,255,255,.4)"/></g>';
    }

    /** The money's road, drawn: till, Haraan holding it, the bank. Coins sit where the money is. */
    function moneyPath(p) {
        var b = p.balance || {}, a = p.account, where = a && a.masked;
        var available = +b.available || 0, inFlight = +b.in_flight || 0, settled = +b.settled || 0, collected = +b.collected || 0;
        var note = !a ? 'Add where to send it. Nothing is transferred until you do.' : inFlight > 0 ? money(inFlight) + ' is on its way to ' + where + '.'
            : !a.verified ? 'Haraan checks your account first, then sends it to ' + where + '.' : available > 0 ? 'The next transfer goes to ' + where + '.' : 'Everything collected has been sent to ' + where + '.';
        var W = 330, H = 86, cy = H * 0.55, xs = [W / 6, W / 2, W * 5 / 6], r = 25, atH = available > 0, moving = inFlight > 0;
        var reached = settled > 0 && !atH && !moving, lit = moving ? 2 : atH ? 1 : settled > 0 ? 2 : 0;
        var svg = '<line x1="' + f(xs[0] + r) + '" y1="' + cy + '" x2="' + f(xs[1] - r) + '" y2="' + cy + '" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/>'
            + '<line x1="' + f(xs[1] + r) + '" y1="' + cy + '" x2="' + f(xs[2] - r) + '" y2="' + cy + '" stroke="' + (reached ? '#2563EB' : '#B9C7DE') + '" stroke-width="2" stroke-linecap="round"' + (reached ? '' : ' stroke-dasharray="6 5"') + '/>';
        xs.forEach(function (x, i) {
            var ink = i === lit ? '#1D4ED8' : '#334155';
            svg += '<circle cx="' + f(x) + '" cy="' + cy + '" r="' + r + '" fill="' + (i === lit ? '#EEF3FE' : '#F3F5F9') + '"/>' + (i === 0 ? till(x, cy, r * 0.62, ink) : i === 1 ? safe(x, cy, r * 0.6, ink) : bank(x, cy, r * 0.62, ink));
        });
        if (atH) {
            var n = available >= 5000 ? 3 : available >= 500 ? 2 : 1;
            for (var k = 0; k < n; k++) svg += coin(xs[1] + r * 0.55, cy - r - 4 - k * 5, 6.5, 'ha-dt-coindrop', 'animation-delay:' + (250 + k * 90) + 'ms');
        }
        if (moving) svg += '<g class="ha-dt-travel" style="--d:' + f(xs[2] - xs[1] - 2 * r) + 'px">' + coin(xs[1] + r, cy - 9, 6) + '</g>';
        return '<section class="ha-dt-card ha-dt-pad"><small class="ha-dt-cap">Waiting to be settled</small>'
            + '<b class="ha-dt-big" data-to="' + available + '">' + money(available) + '</b><p class="ha-dt-lead">' + esc(note) + '</p>'
            + '<svg class="ha-dt-road" viewBox="0 0 ' + W + ' ' + H + '" aria-hidden="true">' + svg + '</svg>'
            + '<div class="ha-dt-stops"><span><b>' + money(collected) + '</b><small>Collected</small></span><span><b>' + money(moving ? inFlight : available) + '</b><small>' + (moving ? 'On the way' : 'With Haraan') + '</small></span><span><b>' + money(settled) + '</b><small>In your bank</small></span></div></section>';
    }

    function guilloche(id) {
        var out = '<pattern id="' + id + '" width="136" height="9" patternUnits="userSpaceOnUse"><path d="';
        for (var x = 0; x <= 136; x += 4) out += (x ? 'L' : 'M') + x + ' ' + f(4.5 + 4 * Math.sin(x / 21.6));
        return out + '" fill="none" stroke="rgba(37,99,235,.06)" stroke-width=".8"/></pattern>';
    }

    /** The destination as a cheque leaf, with Haraan's stamp once finance has checked it. */
    function accountSlip(p) {
        var a = p.account, canEdit = p.can_edit !== false;
        if (!a) {
            return '<section class="ha-dt-card ha-dt-pad"><small class="ha-dt-cap">Money is sent to</small><div class="ha-dt-noacct">No bank or UPI added yet</div>'
                + (canEdit ? '<button type="button" class="ha-dt-cta" data-acct>Add bank or UPI</button>' : '') + '</section>';
        }
        var ch = a.changed || {}, stamp;
        var stampAnim = !po.stampSeen && a.verified;
        if (a.verified) {
            stamp = '<span class="ha-dt-stamp is-on' + (stampAnim ? ' is-new' : '') + '"><svg viewBox="0 0 78 78" aria-hidden="true"><circle cx="39" cy="39" r="37" fill="none" stroke="rgba(21,128,61,.85)" stroke-width="2.2"/><circle cx="39" cy="39" r="33" fill="none" stroke="rgba(21,128,61,.6)"/></svg>'
                + '<b>VERIFIED</b>' + (shortDate(a.verified_on) ? '<small>' + esc(shortDate(a.verified_on)) + '</small>' : '') + '</span>';
        } else {
            stamp = '<span class="ha-dt-stamp"><svg viewBox="0 0 78 78" aria-hidden="true"><circle cx="39" cy="39" r="37" fill="none" stroke="#C6D2E6" stroke-width="1.4" stroke-dasharray="5 4"/></svg><em>Not yet<br>checked</em></span>';
        }
        po.stampSeen = po.stampSeen || a.verified;
        var foot = [ch.name ? 'Changed by ' + ch.name + (ch.kind === 'manager' ? ' (Haraan)' : '') : null, shortDate(ch.at)].filter(Boolean).join(' · ')
            || (a.verified ? 'Checked by Haraan finance' : 'Haraan hasn’t checked it yet');
        return '<section class="ha-dt-card ha-dt-slip' + (canEdit ? ' is-tap' : '') + '"' + (canEdit ? ' data-acct' : '') + '><div class="ha-dt-leaf"><svg class="ha-dt-guill" aria-hidden="true"><defs>' + guilloche('ha-g1') + '</defs><rect width="100%" height="100%" fill="url(#ha-g1)"/></svg>'
            + '<div><small>' + esc(a.method === 'upi' ? 'UPI ID' : (a.bank_name || 'Bank account')) + '</small><b>' + esc(a.method === 'upi' ? a.masked : '•••• ' + String(a.masked || '').slice(-4)) + '</b>'
            + '<p>in the name of ' + esc(a.account_holder || '—') + '</p>' + (a.method !== 'upi' && a.ifsc ? '<p class="is-small">IFSC ' + esc(a.ifsc) + '</p>' : '') + '</div>' + stamp + '</div>'
            + '<div class="ha-dt-slipfoot"><span>' + esc(foot) + '</span>' + (canEdit ? '<b>Change</b>' : '') + '</div></section>';
    }

    function allowed(on) {
        return on ? '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#EEF3FE"/><path d="M6 10.4l3 2.8 5.4-6" fill="none" stroke="#2563EB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M7.2 10h5.6" stroke="#CBD5E1" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    function accessRow(face, name, role, can, action) {
        return '<div class="ha-dt-access">' + face + '<div><b>' + esc(name) + '</b><small>' + esc(role) + '</small>' + (action || '') + '</div>'
            + ['See', 'Change', 'Verify'].map(function (c) { return '<i>' + allowed(can.indexOf(c) >= 0) + '</i>'; }).join('') + '</div>';
    }

    /** Who holds this account, as an access table (WhoCanChange). */
    function whoCanChange(p) {
        var canEdit = p.can_edit !== false, m = p.manager;
        var out = '<section class="ha-dt-card ha-dt-pad"><h4 class="ha-dt-cardtitle">Who can see and change this</h4><div class="ha-dt-accesshead"><span></span><i>See</i><i>Change</i><i>Verify</i></div>'
            + accessRow('<span class="ha-dt-face">' + (canEdit ? 'You' : 'O') + '</span>', canEdit ? 'You' : 'The venue owner', canEdit ? 'Venue owner' : 'Only they can change it', ['See', 'Change']);
        if (m) {
            var wa = m.show_whatsapp && m.phone;
            out += accessRow(m.photo_url ? '<img class="ha-dt-face" src="' + esc(m.photo_url) + '" alt="">' : '<span class="ha-dt-face">' + esc(String(m.name || '?').trim().charAt(0).toUpperCase()) + '</span>',
                displayName(m.name || ''), m.title || 'Your Haraan manager', ['See', 'Change'],
                wa ? '<a class="ha-dt-wa" href="https://wa.me/' + esc(String(m.phone).replace(/\D/g, '')) + '" target="_blank" rel="noopener"><svg viewBox="0 0 15 15" aria-hidden="true"><path d="M3 2.3h9a2.2 2.2 0 0 1 2.2 2.2v4.5a2.2 2.2 0 0 1-2.2 2.2H6.3l-3 2.5.3-2.5H3A2.2 2.2 0 0 1 .8 9V4.5A2.2 2.2 0 0 1 3 2.3z" fill="none" stroke="#1D4ED8" stroke-width="1.5" stroke-linejoin="round"/><circle cx="4.8" cy="6.8" r="1" fill="#1D4ED8"/><circle cx="7.5" cy="6.8" r="1" fill="#1D4ED8"/><circle cx="10.2" cy="6.8" r="1" fill="#1D4ED8"/></svg>WhatsApp</a>' : '');
        }
        out += accessRow('<span class="ha-dt-face is-blue"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.2l7.6 2.8-.8 7q-1.2 5.4-6.8 8-5.6-2.6-6.8-8l-.8-7z" fill="rgba(29,78,216,.14)" stroke="#1D4ED8" stroke-width="1.6" stroke-linejoin="round"/><path d="M6.8 10l2.4 2.4 4.4-4.8" fill="none" stroke="#1D4ED8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>',
            'Haraan finance', 'Checks it before money is sent', ['See', 'Change', 'Verify'])
            + '<p class="ha-dt-bellnote"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.2 11.5q1.3-1.9 1.3-4.8.3-4.5 3.5-4.5t3.5 4.5q0 2.9 1.3 4.8z" fill="none" stroke="#1D4ED8" stroke-width="1.3" stroke-linejoin="round"/><path d="M6.7 13.8h2.6" stroke="#1D4ED8" stroke-width="1.3" stroke-linecap="round"/></svg>Changed by someone else? You get a notification right away, and Haraan checks it again before the next transfer.</p></section>';
        return out;
    }

    /** Every transfer, passbook-style (Transfers). */
    function transfers(list2) {
        var out = '<section class="ha-dt-card"><div class="ha-dt-cardhead"><b>Transfers</b>' + (list2.length ? '<small>' + list2.length + '</small>' : '') + '</div>';
        if (!list2.length) {
            return out + '<div class="ha-dt-passbook"><svg viewBox="0 0 64 48" aria-hidden="true"><path d="M32 6.7Q18 2 2.6 4.8v38.4Q18 40 32 45.1zM32 6.7Q46 2 61.4 4.8v38.4Q46 40 32 45.1z" fill="#F8FAFC" stroke="#94A3B8" stroke-width="1.5" stroke-linejoin="round"/>'
                + [0, 1, 2, 3].map(function (k) { var y = 48 * (0.32 + k * 0.15); return '<path d="M6.4 ' + f(y) + 'H28.2" stroke="rgba(148,163,184,.5)"/><path d="M35.8 ' + f(y) + 'H57.6" stroke="' + (k === 0 ? '#2563EB' : 'rgba(148,163,184,.5)') + '" stroke-width="' + (k === 0 ? 1.8 : 1) + '" stroke-linecap="round"/>'; }).join('')
                + '</svg><p>No transfers yet. Each one shows up here with its date and bank reference (UTR).</p></div></section>';
        }
        return out + list2.map(function (b) {
            var dm = dayMonth(b.date), failedX = String(b.status || '').toLowerCase().indexOf('fail') === 0;
            var tone = b.is_paid ? '#15803D' : failedX ? P.red : '#B45309';
            var icon = b.is_paid ? '<path d="M2.1 7.7l3.8 3.5 6.4-8.1" fill="none" stroke="' + tone + '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>'
                : failedX ? '<path d="M2.8 2.8l8.4 8.4M11.2 2.8l-8.4 8.4" stroke="' + tone + '" stroke-width="1.7" stroke-linecap="round"/>'
                : '<circle cx="7" cy="7" r="5.9" fill="none" stroke="' + tone + '" stroke-width="1.7"/><path d="M7 7V3.4M7 7h3" stroke="' + tone + '" stroke-width="1.7" stroke-linecap="round"/>';
            return '<div class="ha-dt-transfer"><span class="ha-dt-date"><b>' + dm[0] + '</b><small>' + dm[1] + '</small></span>'
                + '<span class="ha-dt-rowmid"><b>' + money(b.amount) + '</b><small>' + esc([b.reference ? 'UTR ' + b.reference : null, b.period].filter(Boolean).join(' · ') || 'Reference comes with the transfer') + '</small></span>'
                + '<span class="ha-dt-state" style="color:' + tone + '"><svg viewBox="0 0 14 14" aria-hidden="true">' + icon + '</svg>' + (b.is_paid ? 'Paid' : failedX ? 'Failed' : 'Sending') + '</span></div>';
        }).join('') + '</section>';
    }

    function renderPayouts() {
        var host = po.host, p = po.page;
        if (!host || !p) return;
        var out = moneyPath(p)
            + '<button type="button" class="ha-dt-card ha-dt-entry" data-invoices><svg viewBox="0 0 34 38" aria-hidden="true"><path d="M3.4 1.5h27.2v32l-2.7 2.9-2.7-2.9-2.7 2.9-2.8-2.9-2.7 2.9-2.7-2.9-2.7 2.9-2.7-2.9-2.7 2.9z" fill="#EEF3FE" stroke="#2563EB" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.2 7.6h17.6M8.2 12.9h10.9M8.2 18.2h17.6" stroke="rgba(37,99,235,.55)" stroke-width="1.3" stroke-linecap="round"/><path d="M8.2 26.6h17.6" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/></svg>'
            + '<span class="ha-dt-rowmid"><b>Invoices</b><small>Statements, tax invoices and TDS certificates</small></span>' + chev() + '</button>'
            + accountSlip(p) + whoCanChange(p) + transfers(p.batches || []);
        host.innerHTML = page('Payouts', '', out);
        countUp(host.querySelector('.ha-dt-big[data-to]'), 'payouts.available');
        var st = host.querySelector('.ha-dt-stamp.is-new');
        if (st) setTimeout(function () { haptic('confirm'); }, 700);
    }

    /** Where should the money go: bank or UPI, and a leaf that fills in as you type. */
    function openAccount() {
        var cur = po.page && po.page.account;
        var st = { method: cur ? cur.method || 'upi' : 'upi', holder: '', bankName: '', acct: '', ifsc: '', vpa: '', saving: false, error: null };
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function vpaOk() { return /^[\w.\-]{2,}@[a-zA-Z]{2,}$/.test(st.vpa.trim()); }
        function ifscOk() { return /^[A-Z]{4}0[A-Z0-9]{6}$/.test(st.ifsc); }
        function valid() { return st.holder.trim().length >= 2 && (st.method === 'bank' ? st.acct.length >= 6 && st.acct.length <= 18 && ifscOk() && st.bankName.trim() : vpaOk()); }
        function leaf() {
            var big = st.method === 'upi' ? (st.vpa.trim() || 'name@bank') : (st.acct.replace(/(.{4})/g, '$1 ').trim() || 'Account number');
            var filled = st.method === 'upi' ? !!st.vpa : !!st.acct;
            var small = st.method === 'bank' ? [st.bankName.trim(), st.ifsc].filter(Boolean).join(' · ') : '';
            var l = s.querySelector('[data-leaf]');
            if (l) l.innerHTML = '<small>' + (st.method === 'upi' ? 'UPI ID' : 'Bank account') + '</small><b class="' + (filled ? '' : 'is-faint') + '">' + esc(big) + '</b>'
                + '<p class="' + (st.holder.trim() ? '' : 'is-faint') + '">in the name of ' + esc(st.holder.trim() || '…') + '</p>' + (small ? '<p class="is-small">' + esc(small) + '</p>' : '');
            var w1 = s.querySelector('[data-vpawarn]'); if (w1) w1.hidden = !(st.vpa && !vpaOk());
            var w2 = s.querySelector('[data-ifscwarn]'); if (w2) w2.hidden = !(st.ifsc.length === 11 && !ifscOk());
        }
        function foot() {
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '')
                + '<button type="button" class="ha-dt-cta" data-save' + (valid() && !st.saving ? '' : ' disabled') + '>' + (st.saving ? 'Saving…' : 'Save account') + '</button>';
            leaf();
        }
        function draw() {
            var upi = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5.8" y="1.4" width="12.5" height="21.1" rx="3" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="8.2" y="5.8" width="2.6" height="2.6" fill="none" stroke="currentColor" stroke-width="1.2"/><rect x="12.7" y="5.8" width="2.6" height="2.6" fill="none" stroke="currentColor" stroke-width="1.2"/><rect x="8.2" y="10.8" width="2.6" height="2.6" fill="none" stroke="currentColor" stroke-width="1.2"/><rect x="13.2" y="11.3" width="1.9" height="1.9" fill="currentColor"/><path d="M10.6 19.7h2.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
            var bk = '<svg viewBox="0 0 24 24" aria-hidden="true">' + bank(12, 12.5, 10, 'currentColor') + '</svg>';
            var out = '<h3 class="ha-dt-formtitle">Where should we send your money?</h3>'
                + '<div class="ha-dt-tiles"><button type="button" class="' + (st.method === 'upi' ? 'is-on' : '') + '" data-m="upi">' + upi + 'UPI ID</button><button type="button" class="' + (st.method === 'bank' ? 'is-on' : '') + '" data-m="bank">' + bk + 'Bank account</button></div>'
                + '<div class="ha-dt-leafprev"><svg class="ha-dt-guill" aria-hidden="true"><defs>' + guilloche('ha-g2') + '</defs><rect width="100%" height="100%" fill="url(#ha-g2)"/></svg><div data-leaf></div></div>'
                + field('holder', 'Name on the account', st.holder, 'text', ' autocapitalize="words" maxlength="120"');
            if (st.method === 'upi') out += field('vpa', 'UPI ID', st.vpa, 'email', ' placeholder="name@okhdfc" autocapitalize="off" maxlength="120"') + '<p class="ha-dt-err" data-vpawarn hidden>Looks like name@bank</p>';
            else out += field('bankName', 'Bank name', st.bankName, 'text', ' autocapitalize="words" maxlength="120"')
                + field('acct', 'Account number', st.acct, 'text', ' inputmode="numeric" maxlength="18"')
                + field('ifsc', 'IFSC', st.ifsc, 'text', ' placeholder="HDFC0001234" autocapitalize="characters" maxlength="11"') + '<p class="ha-dt-err" data-ifscwarn hidden>4 letters, a 0, then 6 letters or digits</p>';
            out += '<p class="ha-dt-note">Saving asks Haraan finance to check it again before the next transfer. Your Haraan manager can see these details to help you.</p>';
            s.querySelector('.ha-dt-formbody').innerHTML = out;
            foot();
        }
        s.addEventListener('input', function (e) {
            var t = e.target, n = t.name;
            if (!n) return;
            if (n === 'vpa') { st.vpa = t.value.trim().toLowerCase().slice(0, 120); }
            else if (n === 'acct') { st.acct = t.value.replace(/\D/g, '').slice(0, 18); t.value = st.acct; }
            else if (n === 'ifsc') { st.ifsc = t.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11); t.value = st.ifsc; }
            else st[n] = t.value.slice(0, 120);
            st.error = null; foot();
        });
        s.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.m) { haptic('tick'); st.method = b.dataset.m; draw(); }
            else if (b.hasAttribute('data-save')) {
                st.saving = true; foot();
                var body = { method: st.method, accountHolder: st.holder.trim() };
                if (st.method === 'bank') { body.bankName = st.bankName.trim(); body.accountNumber = st.acct; body.ifsc = st.ifsc; } else body.upiVpa = st.vpa.trim();
                A.apiSend('POST', 'payouts/account', body).then(function () {
                    haptic('confirm'); s.close(); A.toast('Saved. Haraan checks it before the next transfer.'); po.stampSeen = false; loadPayouts();
                }, function (err) {
                    haptic('reject'); st.saving = false;
                    st.error = err.message && err.message.charAt(0) !== '{' ? err.message : 'Couldn’t save. Check the details and try again.'; foot();
                });
            }
        });
        draw();
    }

    /* ------------------------------------------------------------------ invoices --- */

    var KINDS_DOC = [['invoice', 'Tax invoices', 'Tax invoice'], ['tds', 'TDS certificates', 'TDS'], ['settlement', 'Statements', 'Statement']];

    function loadInvoices() {
        if (!po.host) return;
        if (!po.inv) po.host.innerHTML = invPage(skeleton(150, 3));
        A.api('invoices', { branch: false, fresh: true }).then(function (r) {
            if (!po.host || po.view !== 'invoices') return;
            po.inv = r;
            renderInvoices();
        }, function (e) {
            if (po.host && po.view === 'invoices' && !po.inv) po.host.innerHTML = invPage('<div class="ha-fail"><b>Couldn’t load invoices</b><span>' + esc(e.message || '') + '</span><button type="button" class="ha-cta" data-invretry>Try again</button></div>');
        });
    }
    function invPage(body) {
        return '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-sub-back aria-label="Back"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button><b>Invoices</b><span class="ha-grow"></span></header><div class="ha-toolbody ha-dt">' + body + '</div>';
    }
    function paperStack() {
        return '<svg class="ha-dt-papers" viewBox="0 0 92 72" aria-hidden="true"><g transform="rotate(-12 31 66)"><rect x="8.3" y="4.3" width="46" height="62" rx="4" fill="#F1F5F9" stroke="#94A3B8" stroke-width="1.6"/></g><g transform="rotate(10 61 66)"><rect x="37.7" y="4.3" width="46" height="62" rx="4" fill="#F1F5F9" stroke="#94A3B8" stroke-width="1.6"/></g>'
            + '<rect x="23" y="4.3" width="46" height="62" rx="4" fill="#fff" stroke="#334155" stroke-width="1.6"/><path d="M30 18h32M30 27h22M30 36h32" stroke="#CBD5E1" stroke-width="1.4" stroke-linecap="round"/><circle cx="54.3" cy="51.4" r="7.8" fill="none" stroke="#2563EB" stroke-width="1.6"/><path d="M51.6 48.2h5.4M51.6 50.4h5.4M53 50.4q3 .4 0 2.4l3.3 2.6" fill="none" stroke="#2563EB" stroke-width="1.2" stroke-linecap="round"/></svg>';
    }
    function docPill(label, key, id, csv) {
        var glyph = csv ? '<svg viewBox="0 0 13 16" aria-hidden="true"><rect x=".7" y=".7" width="11.6" height="14.6" rx="1.6" fill="none" stroke="#2563EB" stroke-width="1.4"/><path d="M.7 5.6h11.6M.7 10.4h11.6M6.5.7v14.6" stroke="#2563EB" stroke-width="1.1"/></svg>'
            : '<svg viewBox="0 0 13 16" aria-hidden="true"><path d="M.7 .7h7.5l4.1 4.1v10.5H.7z" fill="none" stroke="#2563EB" stroke-width="1.4" stroke-linejoin="round"/><path d="M8.2 .7v4.1h4.1M3.4 8.6h6.2M3.4 11.6h4.2" fill="none" stroke="#2563EB" stroke-width="1.2" stroke-linecap="round"/></svg>';
        return '<button type="button" class="ha-dt-docpill" data-doc="' + key + '" data-id="' + id + '">' + glyph + esc(label) + '</button>';
    }

    function renderInvoices() {
        var host = po.host, p = po.inv;
        if (!host || !p) return;
        if (p.enabled === false) {
            host.innerHTML = invPage('<div class="ha-dt-empty">' + paperStack() + '<b>Invoices are paused</b><p>Haraan has turned document downloads off for now. Your settlements are unaffected.</p></div>');
            return;
        }
        var t = p.tax_profile || {}, canEdit = !!p.can_edit_tax, fk = po.invFilter;
        var out = '<section class="ha-dt-card ha-dt-pad"><div class="ha-dt-line"><span class="ha-dt-rowmid"><small class="ha-dt-cap2">BILLED TO</small><b class="' + (t.legal_name ? '' : 'is-faint') + '">' + esc(t.legal_name || 'Your business name') + '</b></span>'
            + (canEdit ? '<button type="button" class="ha-dt-softbtn" data-tax>' + (!t.pan && !t.gstin ? 'Add' : 'Edit') + '</button>' : '') + '</div>'
            + '<p class="ha-dt-taxline"><span>PAN</span>' + (t.pan ? '<b>' + esc(t.pan) + '</b>' : '<em class="is-warn">Not added — TDS is withheld at a higher rate</em>') + '</p>'
            + '<p class="ha-dt-taxline"><span>GSTIN</span>' + (t.gstin ? '<b>' + esc(t.gstin) + '</b>' : '<em>Not registered</em>') + '</p>'
            + (t.state ? '<p class="ha-dt-taxline"><span>State</span><b>' + esc(t.state) + '</b></p>' : '')
            + (!canEdit ? '<p class="ha-dt-note">Only the venue owner can change these.</p>' : '') + '</section>'
            + '<div class="ha-dt-chipsrow"><button type="button" class="' + (!fk ? 'is-on' : '') + '" data-kind="">All</button>' + KINDS_DOC.map(function (k) { return '<button type="button" class="' + (fk === k[0] ? 'is-on' : '') + '" data-kind="' + k[0] + '">' + k[1] + '</button>'; }).join('') + '</div>';
        var all = p.settlements || [], rows = all.filter(function (b) { return !fk || (b.docs || []).indexOf(fk) >= 0; });
        if (!all.length) out += '<section class="ha-dt-card ha-dt-pad is-center">' + paperStack() + '<b>No invoices yet</b><p class="ha-dt-note">Every settlement Haraan sends you comes with a statement here — plus a tax invoice for any commission, and a TDS certificate when tax is withheld.</p></section>';
        else if (!rows.length) out += '<p class="ha-dt-note">' + (fk === 'invoice' ? 'No tax invoices yet. One is issued whenever Haraan keeps a commission from a settlement.' : fk === 'tds' ? 'No tax has been withheld from your settlements.' : 'Nothing here yet.') + '</p>';
        var groups = [], byM = {};
        rows.forEach(function (b) { if (!byM[b.month]) { byM[b.month] = []; groups.push(b.month); } byM[b.month].push(b); });
        groups.forEach(function (mo) {
            out += '<h4 class="ha-dt-cap2 is-gap">' + esc(String(mo).toUpperCase()) + '</h4><section class="ha-dt-card">' + byM[mo].map(function (b) {
                var dm = dayMonth(b.date), tone = b.status === 'Paid' ? '#15803D' : b.status === 'Cancelled' ? P.red : '#B45309';
                var deducted = (+b.commission || 0) + (+b.commission_gst || 0) + (+b.tds || 0) + (+b.tcs || 0);
                var head = fk === 'invoice' ? (b.invoice_number || 'Tax invoice') : fk === 'tds' ? 'TDS ' + money2((+b.tds || 0) + (+b.tcs || 0)) : money2(b.net);
                var sub = fk === 'invoice' ? 'Commission ' + money2(b.commission) + (b.commission_gst > 0 ? ' + GST ' + money2(b.commission_gst) : '')
                    : fk === 'tds' ? [b.tds > 0 ? '194-O ' + money2(b.tds) : null, b.tcs > 0 ? 'GST TCS ' + money2(b.tcs) : null].filter(Boolean).join(' · ')
                    : deducted > 0 ? 'of ' + money2(b.gross) + ' · ' + money2(deducted) + ' deducted' : 'Nothing deducted';
                var keys = (fk ? [fk] : (b.docs || [])).filter(function (k) { return (b.docs || []).indexOf(k) >= 0; });
                return '<div class="ha-dt-settle"><span class="ha-dt-date"><b>' + dm[0] + '</b><small>' + dm[1] + '</small></span><div class="ha-dt-rowmid"><span class="ha-dt-line"><b>' + esc(head) + '</b><em style="color:' + tone + '">' + esc(b.status) + '</em></span>'
                    + '<small>' + esc(sub) + '</small>' + (b.period ? '<small class="is-faint">' + esc(b.period) + '</small>' : '')
                    + '<span class="ha-dt-docs">' + keys.map(function (k) { return docPill(k === 'invoice' ? 'Tax invoice' : k === 'tds' ? 'TDS certificate' : k === 'csv' ? 'Bookings CSV' : 'Statement', k, b.id, k === 'csv'); }).join('') + '</span></div></div>';
            }).join('') + '</section>';
        });
        var qs = p.quarters || [];
        if (qs.length && (!fk || fk === 'tds')) {
            out += '<h4 class="ha-dt-cap2 is-gap">TDS BY QUARTER</h4><section class="ha-dt-card">' + qs.map(function (q) {
                var cert = q.form16a;
                return '<div class="ha-dt-quarter"><b>' + esc(q.label) + '</b><small>' + esc(['TDS ' + money2(q.tds), q.tcs > 0 ? 'GST TCS ' + money2(q.tcs) : null, q.settlements + ' settlement' + (q.settlements === 1 ? '' : 's')].filter(Boolean).join(' · ')) + '</small>'
                    + '<span class="ha-dt-docs"><button type="button" class="ha-dt-docpill" data-quarter="' + esc(q.financial_year) + '/' + q.quarter + '" data-label="' + esc(q.label) + '">Summary</button>'
                    + (cert && cert.id ? '<button type="button" class="ha-dt-docpill" data-form16a="' + cert.id + '" data-name="Form16A-' + esc(q.financial_year) + '-Q' + q.quarter + '.pdf">Form 16A' + (cert.certificate_no ? ' · ' + esc(cert.certificate_no) : '') + '</button>' : '<small class="is-faint">Form 16A after the quarter is filed</small>')
                    + '</span></div>';
            }).join('') + '</section>';
        }
        host.innerHTML = invPage(out);
    }

    /** Fetch a document with the partner's token: the server sends it as it prints. */
    function fetchDoc(path) {
        return fetch(path, { headers: { Authorization: 'Bearer ' + A.token(), Accept: 'text/html,application/pdf,text/csv,*/*' }, credentials: 'omit' }).then(function (r) {
            if (r.status === 404) throw new Error('This document isn’t available.');
            if (r.status === 401 || r.status === 403) throw new Error('You don’t have access to this document.');
            if (!r.ok) throw new Error('Couldn’t open this document.');
            return r;
        });
    }

    /** The document full screen, exactly as it prints; Save PDF prints it to A4. */
    function openDocument(title, path, fileName) {
        var v = h('<div class="ha-dt-viewer"><header><button type="button" class="ha-ibtn" data-close aria-label="Back"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button><b>' + esc(title) + '</b>'
            + '<button type="button" class="ha-dt-savepdf" data-print disabled>Save PDF</button></header><div class="ha-dt-viewbody"><span class="ha-spin is-blue"></span></div></div>');
        document.body.appendChild(v);
        requestAnimationFrame(function () { v.classList.add('is-open'); });
        var frame = null;
        v.addEventListener('click', function (e) {
            if (e.target.closest('[data-close]')) { v.classList.remove('is-open'); setTimeout(function () { v.remove(); }, 220); }
            else if (e.target.closest('[data-print]') && frame) {
                haptic('tick');
                try { frame.contentWindow.document.title = fileName.replace(/\.pdf$/, ''); frame.contentWindow.focus(); frame.contentWindow.print(); } catch (err) { A.toast('Use Share → Print to save it as a PDF.'); }
            }
        });
        fetchDoc(path + '?bare=1').then(function (r) { return r.text(); }).then(function (html) {
            frame = document.createElement('iframe');
            frame.setAttribute('sandbox', 'allow-same-origin allow-modals');
            frame.setAttribute('title', title);
            frame.srcdoc = html;
            var body = v.querySelector('.ha-dt-viewbody');
            body.innerHTML = '';
            body.appendChild(frame);
            v.querySelector('[data-print]').disabled = false;
        }, function (err) {
            v.querySelector('.ha-dt-viewbody').innerHTML = '<p class="ha-dt-viewerr">' + esc(err.message) + '</p>';
        });
    }

    /** Files that aren't for reading (CSV, Form 16A) save straight to the phone. */
    function downloadFile(path, name) {
        A.toast('Downloading…');
        fetchDoc(path).then(function (r) { return r.blob(); }).then(function (blob) {
            var url = URL.createObjectURL(blob), a = document.createElement('a');
            a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
        }, function (err) { haptic('reject'); A.toast(err.message); });
    }

    function openTax() {
        var p = po.inv, t = p.tax_profile || {}, states = p.states || [];
        var st = { legal: t.legal_name || '', gstin: t.gstin || '', pan: t.pan || '', address: t.address || '', stateCode: t.state_code || '', saving: false, error: null };
        var s = A.sheet('<div class="ha-dt-form"><div class="ha-dt-formbody"></div><div class="ha-dt-formfoot"></div></div>', 'ha-dt-bs ha-dt-tall');
        function gClean() { return st.gstin.toUpperCase().replace(/[^A-Z0-9]/g, ''); }
        function gState() { var g = gClean(); return g.length >= 2 ? states.filter(function (x) { return x.code === g.slice(0, 2); })[0] : null; }
        function foot() {
            var gs = gState(), sel = s.querySelector('select[name=state]'), hint = s.querySelector('[data-statehint]');
            if (sel) { if (gs) sel.value = gs.code; sel.disabled = !!gs; }
            if (hint) hint.hidden = !gs;
            var panIn = s.querySelector('input[name=pan]');
            if (panIn && !st.pan && gClean().length === 15) panIn.placeholder = gClean().slice(2, 12);
            s.querySelector('.ha-dt-formfoot').innerHTML = (st.error ? '<p class="ha-dt-err">' + esc(st.error) + '</p>' : '') + '<button type="button" class="ha-dt-cta" data-save' + (st.saving ? ' disabled' : '') + '>' + (st.saving ? 'Saving…' : 'Save') + '</button>';
        }
        s.querySelector('.ha-dt-formbody').innerHTML = '<h3 class="ha-dt-formtitle">Tax details</h3><p class="ha-dt-note" style="margin-top:-8px">Printed on your tax invoices and TDS certificates. Settlements not yet paid use the new details.</p>'
            + field('legal', 'Legal business name', st.legal, 'text', ' autocapitalize="words"')
            + field('gstin', 'GSTIN (if registered)', st.gstin, 'text', ' placeholder="37ABCDE1234F1Z5" autocapitalize="characters" maxlength="15"')
            + field('pan', 'PAN', st.pan, 'text', ' placeholder="ABCDE1234F" autocapitalize="characters" maxlength="10"') + '<p class="ha-dt-note">Without a PAN, TDS on each settlement is withheld at a higher rate.</p>'
            + '<label class="ha-dt-field"><span>State</span><select name="state"><option value="">Choose your state</option>' + states.map(function (x) { return '<option value="' + esc(x.code) + '"' + (x.code === st.stateCode ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select></label>'
            + '<p class="ha-dt-note" data-statehint hidden>Taken from your GSTIN.</p>'
            + '<label class="ha-dt-field is-area"><span>Billing address</span><textarea name="address" rows="2" maxlength="300">' + esc(st.address) + '</textarea></label>';
        s.addEventListener('input', function (e) {
            var n = e.target.name;
            if (n === 'gstin') { st.gstin = e.target.value.toUpperCase().slice(0, 15); e.target.value = st.gstin; }
            else if (n === 'pan') { st.pan = e.target.value.toUpperCase().slice(0, 10); e.target.value = st.pan; }
            else if (n === 'legal') st.legal = e.target.value;
            else if (n === 'address') st.address = e.target.value.slice(0, 300);
            foot();
        });
        s.addEventListener('change', function (e) { if (e.target.name === 'state') st.stateCode = e.target.value; });
        s.addEventListener('click', function (e) {
            if (!e.target.closest('[data-save]')) return;
            st.saving = true; st.error = null; foot();
            var g = gClean(), gs = gState();
            var pan = (st.pan || (g.length === 15 ? g.slice(2, 12) : '')).trim();
            A.apiSend('POST', 'tax-profile', { legal_name: st.legal.trim(), gstin: g, pan: pan, address: st.address.trim(), state_code: gs ? gs.code : st.stateCode }).then(function () {
                haptic('confirm'); s.close(); A.toast('Tax details saved'); po.inv = null; loadInvoices();
            }, function (err) { haptic('reject'); st.saving = false; st.error = err.message || 'Couldn’t save'; foot(); });
        });
        foot();
    }

    A.register('payouts', {
        tool: true,
        enter: function (host) {
            po.host = host; po.view = 'main';
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-sub-back]')) { haptic('tick'); po.view = 'main'; renderPayouts(); loadPayouts(); window.scrollTo(0, 0); return; }
                if (e.target.closest('[data-invoices]')) { haptic('tick'); po.view = 'invoices'; window.scrollTo(0, 0); if (po.inv) renderInvoices(); loadInvoices(); return; }
                if (e.target.closest('[data-invretry]')) { loadInvoices(); return; }
                if (e.target.closest('[data-acct]')) { haptic('tick'); openAccount(); return; }
                if (e.target.closest('[data-tax]')) { haptic('tick'); openTax(); return; }
                if ((b = e.target.closest('[data-kind]'))) { haptic('tick'); po.invFilter = b.dataset.kind || null; renderInvoices(); return; }
                if ((b = e.target.closest('[data-doc]'))) {
                    haptic('tick');
                    var id = b.dataset.id, k = b.dataset.doc, sItem = (po.inv.settlements || []).filter(function (x) { return String(x.id) === id; })[0] || {};
                    if (k === 'csv') downloadFile('/api/partner/invoices/' + id + '/csv', 'Haraan-Settlement-' + id + '-bookings.csv');
                    else if (k === 'invoice') openDocument('Tax invoice ' + (sItem.invoice_number || '#' + id), '/api/partner/invoices/' + id + '/invoice', 'Haraan-Tax-Invoice-' + (sItem.invoice_number ? String(sItem.invoice_number).replace(/[^A-Za-z0-9]+/g, '-') : 'S' + id) + '.pdf');
                    else if (k === 'tds') openDocument('TDS / TCS certificate', '/api/partner/invoices/' + id + '/tds', 'Haraan-TDS-Certificate-S' + id + '.pdf');
                    else openDocument('Settlement statement', '/api/partner/invoices/' + id + '/settlement', 'Haraan-Settlement-Statement-S' + id + '.pdf');
                    return;
                }
                if ((b = e.target.closest('[data-quarter]'))) { haptic('tick'); var q = b.dataset.quarter.split('/'); openDocument('TDS summary · ' + b.dataset.label, '/api/partner/invoices/quarter/' + q[0] + '/' + q[1], 'Haraan-TDS-Summary-' + q[0] + '-Q' + q[1] + '.pdf'); return; }
                if ((b = e.target.closest('[data-form16a]'))) { haptic('tick'); downloadFile('/api/partner/invoices/form16a/' + b.dataset.form16a, b.dataset.name); }
            };
            if (po.page) renderPayouts();
            loadPayouts();
        },
        leave: function () { po.host = null; po.view = 'main'; },
    });
})();
