/**
 * Haraan Partner: the app's drawer tools on a phone, batch one — Customers, Staff,
 * Payouts, Reports, Notifications and Support; batch two — Cash Settlement (the shift
 * register) and the Operations Center.
 *
 * Like the app, each opens full screen over everything with its own back header (no
 * bottom bar), and reads the same endpoints the app reads. Registered with the shell
 * (window.HaApp) as `tool` screens; each draws over its console page on a phone only.
 *
 * Ported from PartnerApp.kt (CustomersScreen, StaffScreen, PayoutsScreen,
 * NotificationsScreen, SupportScreen) and reports/ReportsScreen.kt + ReportPdf.kt.
 */
(function () {
    'use strict';

    var A = window.HaApp;
    if (!A || window.__haTools) return;
    window.__haTools = true;

    var esc = A.esc, rupees = A.rupees, inr = A.inr, h = A.h;

    function list(r) { return r && r.data ? r.data : (r || []); }
    function vibrate(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { /* none */ } } }

    function page(title, actions, body) {
        return A.toolHeader(title, actions) + '<div class="ha-toolbody">' + body + '</div>';
    }

    function empty(text) { return '<div class="ha-emptyline">' + esc(text) + '</div>'; }

    function loading() {
        return '<div class="ha-skel ha-skel-tab"><b class="s2"></b><b class="s2"></b><b class="s2"></b></div>';
    }

    function failed(host, title, retry) {
        host.innerHTML = page(title, '', '<div class="ha-fail"><b>Couldn’t load this</b><span>Check your connection and try again.</span><button type="button" class="ha-cta" data-retry>Try again</button></div>');
        host.querySelector('[data-retry]').onclick = retry;
    }

    /** A centred dialog (the app's AlertDialog / Dialog). Resolves the element. */
    function dialog(html) {
        var d = h('<div class="ha-dialog"><div class="ha-scrim" data-close></div><div class="ha-dialog-in ha-dialog-wide" role="dialog">' + html + '</div></div>');
        document.body.appendChild(d);
        d.close = function () { d.remove(); };
        d.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) d.close(); });
        return d;
    }

    /* ================================================================= CUSTOMERS === */

    var cust = { host: null, q: '', timer: null, seq: 0 };

    function loadCustomers() {
        var host = cust.host;
        if (!host) return;
        var seq = ++cust.seq;
        var params = cust.q.trim() ? { q: cust.q.trim() } : {};
        A.api('customers', params).then(function (p) {
            if (seq !== cust.seq || !cust.host) return;
            var rows = list(p);
            var s = p.summary || {};
            var body = host.querySelector('.ha-custlist');
            body.innerHTML = rows.length
                ? '<div class="ha-sumstrip"><span><b>' + (s.total || 0) + '</b><small>Customers</small></span><i></i><span><b>' + (s.repeat || 0) + '</b><small>Repeat</small></span><i></i><span><b>' + (s.anonymous || 0) + '</b><small>No phone</small></span></div>'
                    + rows.map(function (c, i) {
                        return '<button type="button" class="ha-custcard" data-c="' + i + '"><span class="ha-cav' + (c.is_repeat ? ' is-regular' : '') + '">' + esc(String(c.name || '?').charAt(0).toUpperCase()) + '</span>'
                            + '<span class="ha-grow"><span class="ha-row"><b>' + esc(c.name) + '</b>' + (c.is_repeat ? '<em>REGULAR</em>' : '') + '</span>'
                            + '<small>' + c.bookings + ' booking' + (c.bookings === 1 ? '' : 's') + (c.last_visit ? ' · last ' + esc(c.last_visit) : '') + '</small></span>'
                            + '<strong>' + rupees(c.spent) + '</strong></button>';
                    }).join('')
                : empty(cust.q.trim() ? 'No customer matches "' + cust.q.trim() + '".' : 'No customers yet.');
            body.onclick = function (e) {
                var b = e.target.closest('[data-c]');
                if (b) contact(rows[+b.dataset.c]);
            };
        }, function () { if (seq === cust.seq && cust.host) failed(cust.host, 'Customers', function () { renderCustomersShell(); loadCustomers(); }); });
    }

    function contact(c) {
        var d = dialog('<div class="ha-contactdlg"><span class="ha-cav is-big">' + esc(String(c.name || '?').charAt(0).toUpperCase()) + '</span>'
            + '<b>' + esc(c.name) + '</b><small>+91 ' + esc(c.phone) + '</small>'
            + '<div class="ha-cstats"><span><b>' + c.bookings + '</b><small>bookings</small></span><span><b>' + rupees(c.spent) + '</b><small>spent</small></span></div>'
            + '<a class="ha-bigcta" href="https://wa.me/91' + esc(c.phone) + '" target="_blank" rel="noopener">Message on WhatsApp</a>'
            + '<a class="ha-outline-btn" href="tel:+91' + esc(c.phone) + '">' + A.mat('phone') + 'Call</a>'
            + '<button type="button" class="ha-textbtn" data-close>Close</button></div>');
        d.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setTimeout(d.close, 50); }); });
    }

    function renderCustomersShell() {
        cust.host.innerHTML = page('Customers', '', '<label class="ha-search ha-search-tool">' + A.mat('search') + '<input type="search" placeholder="Search name or phone" autocomplete="off" value="' + esc(cust.q) + '"></label>'
            + '<div class="ha-custlist">' + loading() + '</div>');
        cust.host.querySelector('input').addEventListener('input', function (e) {
            cust.q = e.target.value;
            clearTimeout(cust.timer);
            // Debounced, like the app: typing doesn't fire a request per keystroke.
            cust.timer = setTimeout(loadCustomers, cust.q.trim() ? 300 : 0);
        });
    }

    A.register('customers', {
        tool: true,
        enter: function (host) { cust.host = host; renderCustomersShell(); loadCustomers(); },
        leave: function () { clearTimeout(cust.timer); cust.host = null; },
    });

    /* ===================================================================== STAFF === */

    var PERMS = [['bookings', 'Bookings & walk-ins'], ['checkin', 'Ticket / slot check-in'], ['pricing', 'Pricing & slots'], ['reports', 'Reports']];
    function permLabel(p) { var x = PERMS.filter(function (q) { return q[0] === p; })[0]; return x ? x[1] : p; }

    var staff = { host: null, list: null };

    function loadStaff() {
        var host = staff.host;
        if (!host) return;
        A.api('staff', { branch: false }).then(function (r) {
            staff.list = list(r);
            renderStaff();
        }, function () { if (staff.host) failed(staff.host, 'Desk staff', loadStaff); });
    }

    function renderStaff() {
        var host = staff.host;
        if (!host) return;
        var add = '<button type="button" class="ha-ibtn" data-add aria-label="Add"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></button>';
        var rows = staff.list || [];
        host.innerHTML = page('Desk staff', add, rows.length ? rows.map(function (m, i) {
            return '<div class="ha-staffcard"><div class="ha-row"><span class="ha-grow"><b>' + esc(m.name) + '</b><small>' + esc(m.email) + '</small></span>'
                + '<button type="button" class="ha-ibtn" data-edit="' + i + '" aria-label="Edit"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>'
                + '<button type="button" class="ha-ibtn is-red" data-del="' + i + '" aria-label="Delete"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button></div>'
                + '<p>' + esc((m.permissions || []).length ? m.permissions.map(permLabel).join(' · ') : 'No permissions') + '</p></div>';
        }).join('') : empty('No desk staff yet. Tap + to add a front-desk login.'));
        host.onclick = function (e) {
            var b;
            if (e.target.closest('[data-add]')) staffDialog(null);
            else if ((b = e.target.closest('[data-edit]'))) staffDialog(rows[+b.dataset.edit]);
            else if ((b = e.target.closest('[data-del]'))) {
                var m = rows[+b.dataset.del];
                confirmBox('Remove ' + m.name + '?', 'Their login stops working straight away.', 'Remove', function () {
                    A.apiSend('DELETE', 'staff/' + m.id).then(loadStaff, function (err) { A.toast(err.message || 'Couldn’t remove. Try again.'); });
                });
            }
        };
    }

    function staffDialog(existing) {
        var perms = (existing ? existing.permissions : ['bookings', 'checkin']).slice();
        var d = dialog('<b class="ha-dlg-title">' + (existing ? 'Edit permissions' : 'Add desk person') + '</b>'
            + (existing ? '' : '<label class="ha-field"><span>Name</span><input data-f="name" autocomplete="off"></label>'
                + '<label class="ha-field"><span>Email</span><input data-f="email" type="email" autocomplete="off" autocapitalize="off"></label>'
                + '<label class="ha-field"><span>Password (min 6)</span><input data-f="password" type="password" autocomplete="new-password"></label>')
            + '<b class="ha-dlg-sub">Permissions</b>'
            + PERMS.map(function (p) { return '<label class="ha-check"><input type="checkbox" value="' + p[0] + '"' + (perms.indexOf(p[0]) >= 0 ? ' checked' : '') + '><i></i>' + p[1] + '</label>'; }).join('')
            + '<p class="ha-err" hidden></p><div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-main" data-save>Save</button></div>');
        var save = d.querySelector('[data-save]');
        function val(f) { var el = d.querySelector('[data-f="' + f + '"]'); return el ? el.value.trim() : ''; }
        function check() {
            save.disabled = !existing && !(val('name') && val('email') && (d.querySelector('[data-f="password"]').value.length >= 6));
        }
        d.addEventListener('input', check);
        check();
        save.addEventListener('click', function () {
            var chosen = Array.prototype.map.call(d.querySelectorAll('.ha-check input:checked'), function (x) { return x.value; });
            var req = existing
                ? A.apiSend('POST', 'staff/' + existing.id, { permissions: chosen })
                : A.apiSend('POST', 'staff', { name: val('name'), email: val('email'), password: d.querySelector('[data-f="password"]').value, permissions: chosen });
            save.disabled = true;
            req.then(function () { d.close(); loadStaff(); }, function (err) {
                var e = d.querySelector('.ha-err');
                e.textContent = err.message || 'Couldn’t save. Try again.';
                e.hidden = false;
                save.disabled = false;
            });
        });
    }

    function confirmBox(title, text, yes, onYes) {
        var d = dialog('<b class="ha-dlg-title">' + esc(title) + '</b><p class="ha-dlg-text">' + esc(text) + '</p>'
            + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-close>Cancel</button><button type="button" class="ha-dlg-btn is-danger" data-yes>' + esc(yes) + '</button></div>');
        d.querySelector('[data-yes]').addEventListener('click', function () { d.close(); onYes(); });
    }

    A.register('staff', {
        tool: true,
        enter: function (host) { staff.host = host; host.innerHTML = page('Desk staff', '', loading()); loadStaff(); },
        leave: function () { staff.host = null; },
    });

    /* =================================================================== PAYOUTS === */

    var payouts = { host: null };

    function loadPayouts() {
        var host = payouts.host;
        if (!host) return;
        A.api('payouts', { branch: false }).then(function (p) {
            if (!payouts.host) return;
            var b = p.balance || {};
            var acc = p.account;
            var batches = p.batches || [];
            host.innerHTML = page('Payouts', '', '<section class="ha-payhero"><small>AVAILABLE TO SETTLE</small><b>' + rupees(b.available || 0) + '</b>'
                + (b.in_flight > 0 ? '<span class="ha-inflight"><i></i>' + rupees(b.in_flight) + ' being transferred</span>' : '')
                + '<div class="ha-payhero-stats"><span><b>' + rupees(b.collected || 0) + '</b><small>Collected</small></span><i></i><span><b>' + rupees(b.settled || 0) + '</b><small>Settled</small></span></div></section>'
                + '<button type="button" class="ha-acctcard" data-account><span class="ha-acct-ic">' + A.mat('rupee') + '</span><span class="ha-grow"><small>' + (acc ? 'Money is sent to' : 'Add settlement account') + '</small>'
                + '<b>' + esc(acc ? acc.masked : 'No account yet — tap to add') + '</b>'
                + (acc ? '<em class="' + (acc.verified ? 'is-ok' : '') + '"><i></i>' + (acc.verified ? 'Verified' : 'Pending verification') + '</em>' : '') + '</span>' + A.mat('chevron') + '</button>'
                + '<small class="ha-caps">SETTLEMENT HISTORY</small>'
                + (batches.length ? batches.map(function (x) {
                    return '<div class="ha-batch"><span class="ha-grow"><b>' + rupees(x.amount) + '</b><small>' + esc([x.date, x.period].filter(Boolean).join(' · ') || '—') + '</small>'
                        + (x.reference ? '<small>Ref ' + esc(x.reference) + '</small>' : '') + '</span><em class="' + (x.is_paid ? 'is-ok' : '') + '"><i></i>' + esc(String(x.status || '').charAt(0).toUpperCase() + String(x.status || '').slice(1)) + '</em></div>';
                }).join('') : '<div class="ha-note">No settlements yet. Money you collect shows as available until it’s transferred.</div>'));
            host.querySelector('[data-account]').onclick = accountDialog;
        }, function () { if (payouts.host) failed(payouts.host, 'Payouts', loadPayouts); });
    }

    function accountDialog() {
        var method = 'bank';
        var d = dialog('<div class="ha-acctform"></div>');
        var form = d.querySelector('.ha-acctform');
        var vals = { holder: '', bank: '', acct: '', ifsc: '', vpa: '' };
        function field(k, label, attrs) { return '<label class="ha-field"><span>' + label + '</span><input data-k="' + k + '" value="' + esc(vals[k]) + '" ' + (attrs || '') + '></label>'; }
        function render() {
            form.innerHTML = '<b class="ha-dlg-title">Settlement account</b><p class="ha-dlg-text">Where your collected money is transferred.</p>'
                + '<div class="ha-row ha-gap8">' + [['bank', 'Bank account'], ['upi', 'UPI']].map(function (m) { return '<button type="button" class="ha-methodbtn' + (method === m[0] ? ' is-on' : '') + '" data-m="' + m[0] + '">' + m[1] + '</button>'; }).join('') + '</div>'
                + field('holder', 'Account holder name', 'autocomplete="off"')
                + (method === 'bank'
                    ? field('bank', 'Bank name', 'autocomplete="off"') + field('acct', 'Account number', 'inputmode="numeric" autocomplete="off"') + field('ifsc', 'IFSC code', 'autocapitalize="characters" autocomplete="off"')
                    : field('vpa', 'UPI ID', 'placeholder="name@bank" autocapitalize="off" autocomplete="off"'))
                + '<p class="ha-dlg-text ha-small">Saving sends this for re-verification before the next settlement.</p>'
                + '<p class="ha-err" hidden></p><button type="button" class="ha-bigcta" data-save disabled>Save account</button><button type="button" class="ha-textbtn" data-close>Cancel</button>';
            check();
        }
        function check() {
            var ok = vals.holder.trim() && (method === 'bank' ? vals.acct.length >= 6 && vals.ifsc.length >= 6 : vals.vpa.length >= 3);
            form.querySelector('[data-save]').disabled = !ok;
        }
        form.addEventListener('input', function (e) {
            var k = e.target.dataset.k;
            if (!k) return;
            var v = e.target.value;
            if (k === 'acct') { v = v.replace(/\D/g, '').slice(0, 18); e.target.value = v; }
            if (k === 'ifsc') { v = v.toUpperCase().slice(0, 11); e.target.value = v; }
            vals[k] = v;
            check();
        });
        form.addEventListener('click', function (e) {
            var b = e.target.closest('[data-m]');
            if (b) { method = b.dataset.m; render(); return; }
            if (e.target.closest('[data-save]')) {
                var payload = { method: method, accountHolder: vals.holder.trim() };
                if (method === 'bank') { payload.bankName = vals.bank.trim(); payload.accountNumber = vals.acct; payload.ifsc = vals.ifsc; }
                else payload.upiVpa = vals.vpa.trim();
                A.apiSend('POST', 'payouts/account', payload).then(function () { d.close(); A.toast('Account saved for verification'); loadPayouts(); }, function (err) {
                    var el = form.querySelector('.ha-err');
                    el.textContent = err.message || 'Couldn’t save. Try again.';
                    el.hidden = false;
                });
            }
        });
        render();
    }

    A.register('payouts', {
        tool: true,
        enter: function (host) { payouts.host = host; host.innerHTML = page('Payouts', '', loading()); loadPayouts(); },
        leave: function () { payouts.host = null; },
    });

    /* =================================================================== REPORTS === */

    var PRESETS = [['custom', 'Custom'], ['today', 'Today'], ['yesterday', 'Yesterday'], ['week', 'Last 7 days'], ['month', 'This month'], ['last_month', 'Last month']];
    var DEAD = ['cancelled', 'refunded', 'failed', 'expired'];
    var MON = A.months;
    var WDAY = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function iso(d) { return A.ymd(d); }
    function parseIso(s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; }

    function rangeFor(p, custom) {
        var t = new Date();
        var today = iso(t);
        if (p === 'today') return [today, today];
        if (p === 'yesterday') { var y = iso(new Date(Date.now() - 86400000)); return [y, y]; }
        if (p === 'week') return [iso(new Date(Date.now() - 6 * 86400000)), today];
        if (p === 'month') return [iso(new Date(t.getFullYear(), t.getMonth(), 1)), today];
        if (p === 'last_month') return [iso(new Date(t.getFullYear(), t.getMonth() - 1, 1)), iso(new Date(t.getFullYear(), t.getMonth(), 0))];
        return custom || [today, today];
    }

    function prettyRange(r) {
        var a = parseIso(r[0]), b = parseIso(r[1]);
        if (!a || !b) return r[0] + ' – ' + r[1];
        if (r[0] === r[1]) return WDAY[a.getDay()] + ', ' + a.getDate() + ' ' + MON[a.getMonth()] + ' ' + a.getFullYear();
        if (a.getFullYear() !== b.getFullYear()) return a.getDate() + ' ' + MON[a.getMonth()] + ' ' + a.getFullYear() + ' – ' + b.getDate() + ' ' + MON[b.getMonth()] + ' ' + b.getFullYear();
        if (a.getMonth() === b.getMonth()) return a.getDate() + ' – ' + b.getDate() + ' ' + MON[b.getMonth()] + ' ' + b.getFullYear();
        return a.getDate() + ' ' + MON[a.getMonth()] + ' – ' + b.getDate() + ' ' + MON[b.getMonth()] + ' ' + b.getFullYear();
    }

    function dayCount(r) { var a = parseIso(r[0]), b = parseIso(r[1]); return a && b ? Math.round((b - a) / 86400000) + 1 : 1; }

    function rowOf(o) {
        var amount = parseFloat(o.amount) || 0;
        var ps = String(o.payment_status || '');
        var paid = o.amount_paid != null && o.amount_paid !== '' ? parseFloat(o.amount_paid) || 0 : (ps.toLowerCase() === 'paid' ? amount : 0);
        var status = String(o.status || '');
        return {
            bookedAt: String(o.booked_at || ''), item: String(o.item || ''), slot: String(o.slot || ''), slotDate: String(o.slot_date || ''),
            customer: String(o.customer || ''), channel: String(o.channel || ''), amount: amount, paid: paid, status: status, paymentStatus: ps,
            checkedIn: parseInt(o.checked_in, 10) || 0,
            isSale: ['cancelled', 'refunded', 'failed', 'expired', 'pending'].indexOf(status.toLowerCase()) < 0,
            isWalkIn: String(o.channel || '').toLowerCase() === 'offline',
        };
    }

    function summarize(rows) {
        var sales = rows.filter(function (r) { return r.isSale; });
        var gross = sales.reduce(function (t, r) { return t + r.amount; }, 0);
        var collected = sales.reduce(function (t, r) { return t + Math.min(r.paid, r.amount); }, 0);
        return {
            gross: gross, collected: collected, due: Math.max(0, gross - collected), sales: sales.length,
            cancelled: rows.filter(function (r) { return ['cancelled', 'refunded'].indexOf(r.status.toLowerCase()) >= 0; }).length,
            walkIns: sales.filter(function (r) { return r.isWalkIn; }).length,
            online: sales.filter(function (r) { return !r.isWalkIn; }).length,
            checkedIn: sales.filter(function (r) { return r.checkedIn > 0; }).length,
        };
    }

    /** "VADI · 6:00 AM – 7:00 AM" (bookingLabel in ReportPdf.kt). */
    function bookingLabel(r, withItem) {
        var week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'every day', 'today'];
        var slot = r.slot.split('·').map(function (x) { return x.trim(); }).filter(function (x) { return x && week.indexOf(x.toLowerCase()) < 0; }).join(' · ');
        return [(withItem || !slot) && r.item ? r.item : null, slot || null].filter(Boolean).join(' · ');
    }

    function spansItems(rows) { var s = {}; rows.forEach(function (r) { s[r.item] = 1; }); return Object.keys(s).length > 1; }
    function shortDay(s) { var d = parseIso(s); return d ? d.getDate() + ' ' + MON[d.getMonth()] : s; }

    function statusOf(r) {
        var st = r.status.toLowerCase();
        if (DEAD.indexOf(st) >= 0) return [st.charAt(0).toUpperCase() + st.slice(1), 'is-red'];
        if (r.paymentStatus.toLowerCase() === 'paid') return ['Paid', 'is-green'];
        if (st === 'pending') return ['Pending', 'is-amber'];
        return ['Due', 'is-amber'];
    }

    var rep = { host: null, preset: 'custom', custom: null, played: false, data: null, seq: 0, partner: '' };

    function reportUrlParams() {
        var r = rangeFor(rep.preset, rep.custom);
        return { from: r[0], to: r[1], by: rep.played ? 'played' : 'booked' };
    }

    function renderReportShell() {
        var r = rangeFor(rep.preset, rep.custom);
        var n = dayCount(r);
        var basis = rep.played ? 'By play date' : 'By booking date';
        rep.host.innerHTML = page('Reports', '', '<div class="ha-chiprow ha-repchips">' + PRESETS.map(function (p) {
            return '<button type="button" class="ha-pchip' + (rep.preset === p[0] ? ' is-on' : '') + '" data-preset="' + p[0] + '">' + (p[0] === 'custom' ? A.mat('event') : '') + p[1] + '</button>';
        }).join('') + '</div>'
            + '<div class="ha-row ha-repline"><label class="ha-grow ha-rangepick"><b>' + esc(prettyRange(r)) + A.mat('event') + '</b><small>' + n + ' day' + (n === 1 ? '' : 's') + ' · ' + basis + '</small>'
            + '<span class="ha-rangeinputs"><input type="date" data-from value="' + r[0] + '"><input type="date" data-to value="' + r[1] + '"></span></label>'
            + '<span class="ha-basis' + (rep.played ? ' is-played' : '') + '"><i></i><button type="button" data-basis="booked">Booked</button><button type="button" data-basis="played">Played</button></span></div>'
            + '<div class="ha-repbody">' + '<div class="ha-repcard ha-skelcard"><i style="width:90px"></i><i style="width:170px;height:30px"></i><i style="height:8px"></i><i style="width:220px"></i></div>' + '</div>'
            + '<div class="ha-repacts"><button type="button" class="ha-repbtn is-main" data-pdf disabled>' + A.mat('open') + 'View PDF</button><button type="button" class="ha-repbtn" data-csv disabled>' + A.mat('bars') + 'CSV</button></div>');
    }

    function loadReport() {
        if (!rep.host) return;
        var seq = ++rep.seq;
        rep.data = null;
        renderReportShell();
        A.api('reports/bookings', Object.assign({ format: 'json', branch: false }, reportUrlParams())).then(function (o) {
            if (seq !== rep.seq || !rep.host) return;
            rep.partner = o.partner || '';
            rep.data = (o.rows || []).map(rowOf);
            renderReportBody();
        }, function (err) {
            if (seq !== rep.seq || !rep.host) return;
            rep.host.querySelector('.ha-repbody').innerHTML = '<div class="ha-repcard ha-center"><b>Couldn’t load the report</b><small>' + esc(err.message || '') + '</small><button type="button" class="ha-repbtn is-main" data-retry>Try again</button></div>';
        });
    }

    function renderReportBody() {
        var rows = rep.data;
        var s = summarize(rows);
        var share = s.gross > 0 ? s.collected / s.gross : 0;
        var html = '<section class="ha-repcard"><small class="ha-caps">GROSS SALES</small><b class="ha-repgross" data-to="' + s.gross + '">' + rupees(s.gross) + '</b>'
            + '<div class="ha-repbar"><i style="width:' + (share * 100) + '%"></i></div>'
            + '<div class="ha-row ha-repmoney"><span class="is-green">' + rupees(s.collected) + ' collected</span><span class="ha-grow"></span><span class="' + (s.due > 0 ? 'is-red' : 'is-muted') + '">' + (s.due > 0 ? rupees(s.due) + ' due' : 'Nothing due') + '</span></div>'
            + '<hr class="ha-hr ha-gap16"><div class="ha-row ha-repfacts"><span><b>' + s.sales + '</b>' + (s.sales === 1 ? 'booking' : 'bookings') + '</span><span><b>' + s.checkedIn + '</b>checked in</span>'
            + (s.cancelled > 0 ? '<span><b class="is-red">' + s.cancelled + '</b>cancelled</span>' : '') + '</div>';
        if (s.sales > 0) {
            var on = s.online / s.sales;
            html += '<div class="ha-repsplit">' + (s.online ? '<i style="flex:' + Math.max(on, 0.001) + ';background:#1D4ED8"></i>' : '') + (s.walkIns ? '<i style="flex:' + Math.max(1 - on, 0.001) + ';background:#93C5FD"></i>' : '') + '</div>'
                + '<div class="ha-row ha-replegend"><span><i style="background:#1D4ED8"></i>Online ' + s.online + '</span><span><i style="background:#93C5FD"></i>Walk-in ' + s.walkIns + '</span></div>';
        }
        html += '</section><b class="ha-repsec">In this report</b>';
        if (!rows.length) {
            html += '<div class="ha-repcard ha-center"><b>No bookings in this period</b><small>Try a longer range, or switch between booking and play date.</small></div>';
        } else {
            var withItem = spansItems(rows);
            var latest = rows.slice().sort(function (a, b) { return a.bookedAt < b.bookedAt ? 1 : -1; }).slice(0, 6);
            html += '<div class="ha-repcard ha-peek">' + latest.map(function (r, i) {
                var st = statusOf(r), dead = DEAD.indexOf(r.status.toLowerCase()) >= 0;
                return (i ? '<hr class="ha-hr">' : '') + '<div class="ha-row ha-peekrow"><span class="ha-grow"><b>' + esc(r.customer || 'Guest') + '</b><small>'
                    + esc([shortDay(r.slotDate || r.bookedAt.slice(0, 10)), bookingLabel(r, withItem), r.isWalkIn ? 'Walk-in' : null].filter(Boolean).join(' · ')) + '</small></span>'
                    + '<span class="ha-peekamt"><b class="' + (dead ? 'is-faint' : '') + '">' + rupees(r.amount) + '</b><small class="' + st[1] + '">' + st[0] + '</small></span></div>';
            }).join('') + '</div>';
            if (rows.length > latest.length) html += '<small class="ha-repmore">and ' + (rows.length - latest.length) + ' more in the PDF</small>';
        }
        rep.host.querySelector('.ha-repbody').innerHTML = html;
        rep.host.querySelectorAll('[data-pdf],[data-csv]').forEach(function (b) { b.disabled = false; });
    }

    /** The report as a printable A4 document — the phone's Print sheet saves or shares it as a PDF. */
    function openPdf() {
        var rows = rep.data.slice().sort(function (a, b) {
            var x = (a.slotDate || a.bookedAt) + a.bookedAt, y = (b.slotDate || b.bookedAt) + b.bookedAt;
            return x < y ? -1 : x > y ? 1 : 0;
        });
        var s = summarize(rows);
        var r = rangeFor(rep.preset, rep.custom);
        var withItem = spansItems(rows);
        var basis = rep.played ? 'By play date' : 'By booking date';
        var now = new Date();
        var gen = now.getDate() + ' ' + MON[now.getMonth()] + ' ' + now.getFullYear() + ', ' + ((now.getHours() % 12) || 12) + ':' + String(now.getMinutes()).padStart(2, '0') + (now.getHours() < 12 ? ' AM' : ' PM');
        var facts = s.sales + ' booking' + (s.sales === 1 ? '' : 's') + '  ·  ' + s.online + ' online, ' + s.walkIns + ' walk-in  ·  ' + s.checkedIn + ' checked in' + (s.cancelled ? '  ·  ' + s.cancelled + ' cancelled' : '');
        var table = rows.map(function (x, i) {
            var st = x.status.toLowerCase();
            var stText = DEAD.indexOf(st) >= 0 ? st.charAt(0).toUpperCase() + st.slice(1) : x.paymentStatus.toLowerCase() === 'paid' ? 'Paid' : x.paymentStatus.toLowerCase() === 'part' ? 'Part paid' : st === 'pending' ? 'Pending' : 'Unpaid';
            var stCls = DEAD.indexOf(st) >= 0 ? 'r' : x.paymentStatus.toLowerCase() === 'paid' ? 'g' : 'a';
            var d = parseIso(x.slotDate || x.bookedAt.slice(0, 10));
            return '<tr' + (i % 2 ? ' class="z"' : '') + '><td>' + (d ? d.getDate() + ' ' + MON[d.getMonth()] + ' ' + String(d.getFullYear()).slice(2) : '') + '</td><td>' + esc(x.customer || 'Guest') + '</td><td>' + esc(bookingLabel(x, withItem)) + '</td>'
                + '<td class="m">' + (x.isWalkIn ? 'Walk-in' : esc(x.channel.charAt(0).toUpperCase() + x.channel.slice(1))) + '</td><td class="b ' + stCls + '">' + stText + '</td><td class="b n">' + rupees(x.amount) + '</td></tr>';
        }).join('');
        var doc = '<div class="ha-pdfdoc"><header><small>HARAAN</small><span class="basis">' + basis + '</span><h1>Booking report</h1><p>' + esc([rep.partner, prettyRange(r)].filter(Boolean).join('  ·  ')) + '</p></header>'
            + '<div class="figs"><span><small>GROSS SALES</small><b>' + rupees(s.gross) + '</b></span><span><small>COLLECTED</small><b class="g">' + rupees(s.collected) + '</b></span><span><small>STILL DUE</small><b class="' + (s.due > 0 ? 'r' : '') + '">' + rupees(s.due) + '</b></span></div>'
            + '<div class="bar"><i style="width:' + (s.gross > 0 ? s.collected / s.gross * 100 : 0) + '%"></i></div><p class="facts">' + facts + '</p>'
            + '<table><thead><tr><th>Date</th><th>Customer</th><th>Booking</th><th>Channel</th><th>Status</th><th class="n">Amount</th></tr></thead><tbody>'
            + (table || '<tr><td colspan="6" class="m">No bookings in this period.</td></tr>') + '</tbody></table>'
            + '<footer>Generated ' + gen + ' · Haraan Partner</footer></div>';
        var v = h('<div class="ha-pdfview"><header class="ha-toolbar"><button type="button" class="ha-ibtn" data-x aria-label="Back"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<span class="ha-grow"><b>Booking report</b><small>' + esc(prettyRange(r)) + '</small></span></header><div class="ha-pdfscroll">' + doc + '</div>'
            + '<div class="ha-repacts"><button type="button" class="ha-repbtn is-main" data-print>' + A.mat('open') + 'Save PDF</button><button type="button" class="ha-repbtn" data-print>' + A.mat('share') + 'Share</button></div></div>');
        document.body.appendChild(v);
        document.documentElement.classList.add('ha-printing');
        v.addEventListener('click', function (e) {
            if (e.target.closest('[data-x]')) { v.remove(); document.documentElement.classList.remove('ha-printing'); }
            // The phone's own print sheet: Save to Files as PDF, or Share (WhatsApp, mail…).
            else if (e.target.closest('[data-print]')) window.print();
        });
    }

    function downloadCsv(btn) {
        btn.disabled = true;
        var p = reportUrlParams();
        var url = '/api/partner/reports/bookings?from=' + p.from + '&to=' + p.to + '&by=' + p.by + '&format=csv';
        var name = 'haraan_bookings_' + p.from + '_to_' + p.to + '.csv';
        // Fetch with the app token, then hand the file to the phone (share sheet where it exists).
        (function get(retried) {
            return fetch(url, { headers: { Authorization: 'Bearer ' + A.token() } }).then(function (r) {
                if (r.status === 401 && !retried) return A.refresh().then(function () { return get(true); });
                if (!r.ok) throw new Error('Couldn’t make the CSV');
                return r.blob();
            });
        })(false).then(function (blob) {
            var file = new File([blob], name, { type: 'text/csv' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) return navigator.share({ files: [file], title: name }).catch(function () {});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        }).catch(function (e) { A.toast(e.message || 'Couldn’t make the CSV'); }).finally(function () { btn.disabled = false; });
    }

    A.register('reports', {
        tool: true,
        enter: function (host) {
            rep.host = host;
            if (!rep.custom) rep.custom = rangeFor('month');
            host.onclick = function (e) {
                var b;
                if ((b = e.target.closest('[data-preset]'))) {
                    var p = b.dataset.preset;
                    vibrate(5);
                    if (p === 'custom') { rep.preset = 'custom'; loadReport(); setTimeout(function () { var x = rep.host && rep.host.querySelector('[data-from]'); if (x && x.showPicker) try { x.showPicker(); } catch (er) { /* needs a gesture */ } }, 30); return; }
                    rep.preset = p; loadReport();
                } else if ((b = e.target.closest('[data-basis]'))) {
                    var played = b.dataset.basis === 'played';
                    if (played !== rep.played) { rep.played = played; loadReport(); }
                } else if (e.target.closest('[data-retry]')) loadReport();
                else if (e.target.closest('[data-pdf]') && rep.data) openPdf();
                else if ((b = e.target.closest('[data-csv]'))) downloadCsv(b);
            };
            host.onchange = function (e) {
                var f = host.querySelector('[data-from]').value, t = host.querySelector('[data-to]').value;
                if (!f) return;
                if (!t || t < f) t = f;
                rep.custom = [f, t];
                rep.preset = 'custom';
                loadReport();
            };
            loadReport();
        },
        leave: function () { rep.host = null; document.querySelectorAll('.ha-pdfview').forEach(function (v) { v.remove(); }); document.documentElement.classList.remove('ha-printing'); },
    });

    /* ============================================================= NOTIFICATIONS === */

    var notes = { host: null };

    function loadNotes() {
        var host = notes.host;
        if (!host) return;
        fetchJson('/api/notifications').then(function (o) {
            if (!notes.host) return;
            var items = o.notifications || o.data || [];
            var unread = o.unread || 0;
            host.innerHTML = page('Notifications', unread > 0 ? '<button type="button" class="ha-textbtn is-blue" data-readall>Mark all read</button>' : '',
                items.length ? items.map(function (n) {
                    return '<div class="ha-notecard"><i class="' + (n.read ? '' : 'is-unread') + '"></i><span class="ha-grow"><b class="' + (n.read ? '' : 'is-unread') + '">' + esc(n.title) + '</b>'
                        + (n.body ? '<p>' + esc(n.body) + '</p>' : '') + (n.created_at ? '<small>' + esc(String(n.created_at).slice(0, 10)) + '</small>' : '') + '</span></div>';
                }).join('') : empty('No notifications yet.'));
            var b = host.querySelector('[data-readall]');
            if (b) b.onclick = function () { sendJson('POST', '/api/notifications/read', { ids: [] }).then(loadNotes, loadNotes); };
        }, function () { if (notes.host) failed(notes.host, 'Notifications', loadNotes); });
    }

    /** GET/POST outside /api/partner (notifications, support) with the app token. */
    function fetchJson(url, retried) {
        return fetch(url, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + A.token() } }).then(function (r) {
            if (r.status === 401 && !retried) return A.refresh().then(function () { return fetchJson(url, true); });
            if (!r.ok) throw new Error(url + ' ' + r.status);
            return r.json();
        });
    }
    function sendJson(method, url, body, retried) {
        return fetch(url, { method: method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + A.token() }, body: JSON.stringify(body || {}) }).then(function (r) {
            if (r.status === 401 && !retried) return A.refresh().then(function () { return sendJson(method, url, body, true); });
            if (!r.ok) throw new Error('Couldn’t send. Try again.');
            return r.json().catch(function () { return {}; });
        });
    }

    A.register('notifications', {
        tool: true,
        enter: function (host) { notes.host = host; host.innerHTML = page('Notifications', '', loading()); loadNotes(); },
        leave: function () { notes.host = null; },
    });

    /* =================================================================== SUPPORT === */

    var sup = { host: null, sending: false, timer: null };

    function loadThread(scrollEnd) {
        var host = sup.host;
        if (!host) return;
        fetchJson('/api/support/thread').then(function (o) {
            if (!sup.host) return;
            var msgs = o.messages || [];
            var list = host.querySelector('.ha-chat');
            list.innerHTML = msgs.length ? msgs.map(function (m) {
                var admin = m.from === 'admin';
                // Local time on this phone (the app prints the UTC clock).
                var at = m.created_at ? new Date(m.created_at) : null;
                var t = at && !isNaN(at) ? ((at.getHours() % 12) || 12) + ':' + String(at.getMinutes()).padStart(2, '0') + (at.getHours() < 12 ? ' AM' : ' PM') : '';
                return '<div class="ha-msg' + (admin ? ' is-admin' : '') + '"><p>' + esc(m.body) + '</p>' + (t ? '<small>' + t + '</small>' : '') + '</div>';
            }).join('') : empty('Ask us anything — we usually reply within a few hours.');
            if (scrollEnd) list.scrollTop = list.scrollHeight;
        }, function () { if (sup.host && !sup.host.querySelector('.ha-msg')) sup.host.querySelector('.ha-chat').innerHTML = empty('Couldn’t load the conversation. Pull to try again.'); });
    }

    A.register('support', {
        tool: true,
        enter: function (host) {
            sup.host = host;
            host.innerHTML = A.toolHeader('Support', '') + '<div class="ha-chatwrap"><div class="ha-chat">' + loading() + '</div>'
                + '<form class="ha-composer"><textarea rows="1" placeholder="Type a message" maxlength="2000"></textarea><button type="submit" disabled aria-label="Send">→</button></form></div>';
            var form = host.querySelector('form'), ta = form.querySelector('textarea'), btn = form.querySelector('button');
            ta.addEventListener('input', function () {
                btn.disabled = !ta.value.trim() || sup.sending;
                ta.style.height = 'auto';
                ta.style.height = Math.min(ta.scrollHeight, 104) + 'px';
            });
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var body = ta.value.trim();
                if (!body || sup.sending) return;
                ta.value = '';
                ta.style.height = 'auto';
                sup.sending = true;
                btn.disabled = true;
                vibrate(6);
                sendJson('POST', '/api/support/messages', { body: body }).then(function () { loadThread(true); }, function (err) { ta.value = body; A.toast(err.message); })
                    .finally(function () { sup.sending = false; btn.disabled = !ta.value.trim(); });
            });
            loadThread(true);
            // Replies arrive while the screen is open, the way a chat should.
            sup.timer = setInterval(function () { if (document.visibilityState === 'visible') loadThread(false); }, 15000);
        },
        leave: function () { clearInterval(sup.timer); sup.host = null; },
    });
    /* ======================================================= VENUE FOR A TOOL === */

    /** The venue a per-venue tool works on: the chosen outlet, else the first one. */
    function toolVenue() {
        return A.api('venues', { branch: false }).then(function (r) {
            var vs = list(r);
            return vs.filter(function (v) { return v.id === A.cfg.branch; })[0] || vs[0] || null;
        });
    }

    function noVenue(host, title) {
        host.innerHTML = page(title, '', empty('This needs a venue. Ask Haraan to add your venue first.'));
    }

    function money2(n) { return '₹' + (Math.round((parseFloat(n) || 0) * 100) / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    /** "6:42 PM" / "4 Oct" in the phone's own time for an ISO timestamp. */
    function localTime(iso) { var d = new Date(iso); return isNaN(d) ? '' : d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase(); }
    function localDay(iso) { var d = new Date(iso); return isNaN(d) ? '' : d.getDate() + ' ' + A.months[d.getMonth()] + ' ' + d.getFullYear(); }
    function whole(n) { return rupees(Math.round(parseFloat(n) || 0)); }
    function refreshBtn() { return '<button type="button" class="ha-ibtn" data-refresh aria-label="Refresh"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.73 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg></button>'; }

    /* ========================================================= CASH SETTLEMENT === */

    var DROP_CATS = [['diesel', 'Diesel / Fuel'], ['cleaning', 'Cleaning & Housekeeping'], ['maintenance', 'Turf Maintenance / Repairs'], ['supplies', 'Balls / Bibs / Gear'], ['owner_draw', 'Owner Cash Withdrawal'], ['bank_deposit', 'Bank Cash Deposit'], ['other', 'Other Expense']];
    function catLabel(k) { var x = DROP_CATS.filter(function (c) { return c[0] === k; })[0]; return x ? x[1] : k; }
    var NOTES = [500, 200, 100, 50, 20, 10];

    var reg = { host: null, venue: null, data: null, tab: 'in', history: false };

    function regPath(p) { return 'venues/' + reg.venue.id + '/' + p; }

    function loadRegister() {
        if (!reg.host) return;
        (reg.venue ? Promise.resolve(reg.venue) : toolVenue()).then(function (v) {
            if (!reg.host) return;
            if (!v) return noVenue(reg.host, 'Cash Register');
            reg.venue = v;
            return A.api(regPath('shift/current'), { branch: false }).then(function (d) {
                if (!reg.host) return;
                reg.data = d;
                renderRegister();
            });
        }).catch(function () { if (reg.host) failed(reg.host, 'Cash Register', loadRegister); });
    }

    function regHeader(sub) {
        var acts = '<button type="button" class="ha-ibtn" data-history aria-label="History"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6a7 7 0 1 1 2.05 4.95l-1.42 1.42A9 9 0 1 0 13 3zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg></button>' + refreshBtn();
        return '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-back aria-label="Back"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<span class="ha-grow"><b>Cash Register</b><small>' + esc(sub) + '</small></span>' + acts + '</header>';
    }

    function renderRegister() {
        var host = reg.host, d = reg.data || {};
        if (!host) return;
        if (!d.has_open_shift) {
            host.innerHTML = regHeader('Drawer Closed') + '<div class="ha-toolbody ha-regbody"><div class="ha-regclosed">'
                + '<span class="ha-reglock"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zM9 6a3 3 0 0 1 6 0v2H9V6zm3 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg></span>'
                + '<b>Register Is Closed</b><p>Open a shift to set your starting cash float, track walk-in payments, and reconcile drawer variance at end of day.</p>'
                + (d.unattributed_cash > 0 ? '<em class="ha-regwarn">' + whole(d.unattributed_cash) + ' cash taken today outside a shift</em>' : '')
                + '<button type="button" class="ha-regbtn is-ink" data-open>Open Shift &amp; Set Float</button>'
                + '<button type="button" class="ha-regbtn is-line" data-history>View Shift Audit History</button></div></div>';
            return;
        }
        var s = d.shift, pays = s.payments || [], drops = s.drops || [];
        var rows = reg.tab === 'in'
            ? (pays.length ? pays.map(function (p) {
                return '<div class="ha-regrow"><span class="ha-regdot is-in">₹</span><span class="ha-grow"><b>' + esc(p.customer_name || 'Walk-in') + '</b><small>Booking #' + p.id + ' · ' + esc(p.time || '') + '</small></span><strong class="is-green">+' + whole(p.amount) + '</strong></div>';
            }).join('') : '<div class="ha-regempty">No cash payments in this shift yet.</div>')
            : (drops.length ? drops.map(function (x) {
                return '<div class="ha-regrow"><span class="ha-regdot is-out">−</span><span class="ha-grow"><b>' + esc(catLabel(x.category)) + '</b><small>' + esc([x.reason, x.staff_name, x.time].filter(Boolean).join(' · ')) + '</small></span><strong class="is-red">-' + whole(x.amount) + '</strong></div>';
            }).join('') : '<div class="ha-regempty">No cash drops in this shift.</div>');

        host.innerHTML = regHeader('Active Shift · ' + (s.staff_name || '')) + '<div class="ha-toolbody ha-regbody">'
            + '<section class="ha-reghero"><div class="ha-row"><small class="ha-live"><i></i>DRAWER CASH (LIVE)</small><span class="ha-grow"></span><small>Shift #' + s.id + '</small></div>'
            + '<b>' + money2(s.expected_cash) + '</b><p>Expected physical cash in register right now</p>'
            + '<div class="ha-regstats"><span><small>OPEN FLOAT</small><b>' + whole(s.opening_float) + '</b></span><span><small>CASH COLLECTED</small><b class="is-green">+' + whole(s.cash_collected) + '</b></span><span><small>CASH DROPS</small><b class="is-red">-' + whole(s.total_drops) + '</b></span></div>'
            + '<div class="ha-row ha-gap8"><button type="button" class="ha-regbtn is-line" data-drop>Cash Drop</button><button type="button" class="ha-regbtn is-ink" data-close-shift>Close Shift</button></div></section>'
            + '<section class="ha-regdigital"><small>DIGITAL COLLECTIONS (COUNTER)</small><p>Reconciled against bank statements · Not in drawer</p>'
            + '<div class="ha-row"><span class="ha-grow"><small>UPI</small><b class="is-blue">' + whole(s.upi_collected) + '</b></span><span class="ha-grow"><small>CARD</small><b>' + whole(s.card_collected) + '</b></span></div></section>'
            + '<small class="ha-caps">SHIFT TRANSACTIONS</small>'
            + '<div class="ha-regtabs"><button type="button" data-tab="in" class="' + (reg.tab === 'in' ? 'is-on' : '') + '">Cash In (' + pays.length + ')</button><button type="button" data-tab="drops" class="' + (reg.tab === 'drops' ? 'is-on' : '') + '">Drops (' + drops.length + ')</button></div>'
            + '<div class="ha-reglist">' + rows + '</div></div>';
    }

    function regError(d, err) {
        var e = d.querySelector('.ha-err');
        e.textContent = (err && err.message) || 'Couldn’t save. Try again.';
        e.hidden = false;
    }

    function openShiftDialog() {
        var d = dialog('<b class="ha-dlg-title">Open Desk Shift</b><p class="ha-dlg-text">Set starting cash drawer float</p>'
            + '<label class="ha-field"><span>Opening Cash Float (₹)</span><input data-f="float" inputmode="decimal" placeholder="0"></label>'
            + '<b class="ha-dlg-sub">Quick Presets</b><div class="ha-presets">' + [0, 500, 1000, 2000, 5000].map(function (n) { return '<button type="button" data-p="' + n + '">₹' + n + '</button>'; }).join('') + '</div>'
            + '<label class="ha-field"><span>Shift Note (Optional)</span><input data-f="note" placeholder="e.g. Morning Shift - Terminal 1" autocomplete="off"></label>'
            + '<p class="ha-err" hidden></p><div class="ha-row ha-gap8 ha-dlg-acts"><button type="button" class="ha-regbtn is-line" data-close>Cancel</button><button type="button" class="ha-regbtn is-ink" data-save>Open Shift</button></div>');
        var f = d.querySelector('[data-f="float"]');
        d.querySelector('.ha-presets').addEventListener('click', function (e) {
            var b = e.target.closest('[data-p]');
            if (b) f.value = b.dataset.p;
        });
        d.querySelector('[data-save]').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            A.apiSend('POST', regPath('shift/open'), { opening_float: parseFloat(f.value) || 0, note: d.querySelector('[data-f="note"]').value.trim() || null })
                .then(function () { d.close(); A.toast('Shift opened'); loadRegister(); }, function (err) { regError(d, err); btn.disabled = false; });
        });
    }

    function dropDialog() {
        var cat = 'diesel';
        var d = dialog('<b class="ha-dlg-title">Record Cash Drop / Expense</b><p class="ha-dlg-text">Logs cash taken out of drawer during active shift</p>'
            + '<label class="ha-field"><span>Amount Withdrawn (₹)</span><input data-f="amount" inputmode="decimal" placeholder="500"></label>'
            + '<b class="ha-dlg-sub">Expense Category</b><div class="ha-catchips">' + DROP_CATS.map(function (c) { return '<button type="button" data-c="' + c[0] + '"' + (c[0] === cat ? ' class="is-on"' : '') + '>' + c[1] + '</button>'; }).join('') + '</div>'
            + '<label class="ha-field"><span>Reason / Notes</span><input data-f="reason" placeholder="e.g. 50L diesel for generator" autocomplete="off"></label>'
            + '<p class="ha-err" hidden></p><div class="ha-row ha-gap8 ha-dlg-acts"><button type="button" class="ha-regbtn is-line" data-close>Cancel</button><button type="button" class="ha-regbtn is-red" data-save disabled>Record Drop</button></div>');
        var amt = d.querySelector('[data-f="amount"]'), reason = d.querySelector('[data-f="reason"]'), save = d.querySelector('[data-save]');
        function check() { save.disabled = !((parseFloat(amt.value) || 0) > 0 && reason.value.trim()); }
        d.addEventListener('input', check);
        d.querySelector('.ha-catchips').addEventListener('click', function (e) {
            var b = e.target.closest('[data-c]');
            if (!b) return;
            cat = b.dataset.c;
            this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
        });
        save.addEventListener('click', function () {
            save.disabled = true;
            A.apiSend('POST', regPath('shift/drop'), { amount: parseFloat(amt.value), category: cat, reason: reason.value.trim() })
                .then(function () { d.close(); A.toast('Cash drop recorded'); reg.tab = 'drops'; loadRegister(); }, function (err) { regError(d, err); check(); });
        });
    }

    function closeoutDialog() {
        var s = reg.data.shift;
        var expected = parseFloat(s.expected_cash) || 0;
        var counts = { coins: 0 };
        NOTES.forEach(function (n) { counts[n] = 0; });
        var mode = 'count'; // denomination grid, or one typed total
        var d = dialog('<div class="ha-closeout"></div>');
        var box = d.querySelector('.ha-closeout');
        var direct = '', noteText = '';

        function counted() {
            if (mode === 'total') return parseFloat(direct) || 0;
            return NOTES.reduce(function (t, n) { return t + n * counts[n]; }, 0) + (counts.coins || 0);
        }

        function varianceHtml() {
            var v = Math.round((counted() - expected) * 100) / 100;
            var cls = Math.abs(v) < 0.01 ? 'is-square' : v < 0 ? 'is-short' : 'is-over';
            var label = Math.abs(v) < 0.01 ? 'Drawer Square · No variance' : v < 0 ? 'Drawer Short' : 'Drawer Over';
            return '<div class="ha-variance ' + cls + '"><b>' + label + '</b><strong>' + (Math.abs(v) < 0.01 ? '₹0.00' : (v < 0 ? '-' : '+') + money2(Math.abs(v))) + '</strong></div>';
        }

        function render() {
            var v = counted() - expected, square = Math.abs(v) < 0.01;
            box.innerHTML = '<b class="ha-dlg-title">End of Shift Close-Out</b><p class="ha-dlg-text">Staff: ' + esc(s.staff_name || '') + ' · Started: ' + esc(localTime(s.opened_at)) + '</p>'
                + '<div class="ha-expected"><span class="ha-grow"><small>SYSTEM EXPECTED CASH</small><small>Float (' + whole(s.opening_float) + ') + Cash In (' + whole(s.cash_collected) + ') - Drops (' + whole(s.total_drops) + ')</small></span><b>' + money2(expected) + '</b></div>'
                + '<div class="ha-row ha-catrow"><b class="ha-dlg-sub">COUNT PHYSICAL CASH</b><span class="ha-grow"></span><div class="ha-basis' + (mode === 'total' ? ' is-played' : '') + '"><i></i><button type="button" data-mode="count">Count</button><button type="button" data-mode="total">Total</button></div></div>'
                + (mode === 'total'
                    ? '<label class="ha-field"><span>Total counted cash (₹)</span><input data-direct inputmode="decimal" placeholder="Enter total cash counted" value="' + esc(direct) + '"></label>'
                    : '<div class="ha-denoms">' + NOTES.map(function (n) {
                        return '<div class="ha-denom"><b>₹' + n + '</b><span class="ha-stepper"><button type="button" data-dec="' + n + '">−</button><i>' + counts[n] + '</i><button type="button" data-inc="' + n + '">+</button></span><small>' + whole(n * counts[n]) + '</small></div>';
                    }).join('') + '<div class="ha-denom"><b>Loose Coins (₹)</b><input class="ha-coins" data-coins inputmode="numeric" value="' + (counts.coins || '') + '" placeholder="0"><small>' + whole(counts.coins) + '</small></div></div>'
                    + '<div class="ha-row ha-countsum"><span class="ha-grow">Total Counted Cash</span><b>' + money2(counted()) + '</b></div>')
                + '<div data-var>' + varianceHtml() + '</div>'
                + '<label class="ha-field"><span class="' + (square ? '' : 'is-red') + '" data-notelabel>' + (square ? 'Close-Out Notes (Optional)' : 'Variance Reason (Required) *') + '</span><input data-note autocomplete="off" placeholder="' + (square ? 'Any remarks for shift handover' : 'Explain why drawer is short/over') + '"></label>'
                + '<p class="ha-err" hidden></p><button type="button" class="ha-regbtn ' + (v < -0.009 ? 'is-red' : 'is-blue') + ' ha-wide" data-confirm>Confirm &amp; Close Shift</button><button type="button" class="ha-textbtn" data-close>Cancel</button>';
        }

        function refreshLive() {
            var v = counted() - expected, square = Math.abs(v) < 0.01;
            box.querySelector('[data-var]').innerHTML = varianceHtml();
            var lab = box.querySelector('[data-notelabel]');
            lab.textContent = square ? 'Close-Out Notes (Optional)' : 'Variance Reason (Required) *';
            lab.className = square ? '' : 'is-red';
            var c = box.querySelector('[data-confirm]');
            c.className = 'ha-regbtn ha-wide ' + (v < -0.009 ? 'is-red' : 'is-blue');
            var sum = box.querySelector('.ha-countsum b');
            if (sum) sum.textContent = money2(counted());
        }

        box.addEventListener('click', function (e) {
            var b;
            if ((b = e.target.closest('[data-mode]'))) { mode = b.dataset.mode; render(); return; }
            if ((b = e.target.closest('[data-inc]'))) { counts[b.dataset.inc]++; vibrate(4); render(); return; }
            if ((b = e.target.closest('[data-dec]'))) { counts[b.dataset.dec] = Math.max(0, counts[b.dataset.dec] - 1); vibrate(4); render(); return; }
            if (e.target.closest('[data-confirm]')) {
                var note = box.querySelector('[data-note]').value.trim();
                var v = counted() - expected;
                if (Math.abs(v) >= 0.01 && !note) return regError(box, { message: 'Mandatory explanation required for non-zero variance.' });
                var body = { counted_cash: counted(), note: note || null };
                if (mode === 'count') {
                    body.denominations = { coins: counts.coins || 0 };
                    NOTES.forEach(function (n) { body.denominations[String(n)] = counts[n]; });
                }
                var btn = e.target.closest('[data-confirm]');
                btn.disabled = true;
                A.apiSend('POST', regPath('shift/close'), body).then(function (r) {
                    d.close();
                    A.toast('Shift closed · ' + ((r && r.variance_label) || 'Done'));
                    reg.tab = 'in';
                    loadRegister();
                }, function (err) { regError(box, err); btn.disabled = false; });
            }
        });
        box.addEventListener('input', function (e) {
            if (e.target.matches('[data-note]')) noteText = e.target.value;
            if (e.target.matches('[data-direct]')) { direct = e.target.value; refreshLive(); }
            if (e.target.matches('[data-coins]')) {
                counts.coins = parseFloat(e.target.value.replace(/[^\d.]/g, '')) || 0;
                e.target.closest('.ha-denom').querySelector('small').textContent = whole(counts.coins);
                refreshLive();
            }
        });
        render();
    }

    function showHistory() {
        reg.history = true;
        var host = reg.host;
        host.innerHTML = '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-histback aria-label="Back"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<span class="ha-grow"><b>Shift Audit History</b><small>Past shift settlements &amp; variances</small></span></header><div class="ha-toolbody">' + loading() + '</div>';
        A.api(regPath('shifts'), { branch: false }).then(function (r) {
            if (!reg.host || !reg.history) return;
            var rows = list(r);
            host.querySelector('.ha-toolbody').innerHTML = rows.length ? rows.map(function (x) {
                var v = parseFloat(x.variance) || 0;
                var cls = Math.abs(v) < 0.01 ? 'is-square' : v < 0 ? 'is-short' : 'is-over';
                return '<div class="ha-histcard"><div class="ha-row"><span class="ha-grow"><b>' + esc(x.staff_name || '') + '</b><small>Shift #' + x.id + ' · ' + esc(localDay(x.opened_at) + (x.opened_at ? ' · ' + localTime(x.opened_at) : '')) + '</small></span>'
                    + '<em class="ha-varpill ' + cls + '">' + esc(x.variance_label || '') + (Math.abs(v) >= 0.01 ? ' ' + (v < 0 ? '-' : '+') + whole(Math.abs(v)) : '') + '</em></div><i class="ha-hr"></i>'
                    + '<div class="ha-histfigs"><span><small>Float</small><b>' + whole(x.opening_float) + '</b></span><span><small>Cash in</small><b>' + whole(x.cash_collected) + '</b></span><span><small>Drops</small><b>' + whole(x.total_drops) + '</b></span><span><small>Expected</small><b>' + whole(x.expected_cash) + '</b></span><span><small>Counted</small><b>' + (x.counted_cash == null ? '—' : whole(x.counted_cash)) + '</b></span></div>'
                    + (x.note ? '<p class="ha-histnote">Note: ' + esc(x.note) + '</p>' : '') + '</div>';
            }).join('') : '<div class="ha-emptyline"><b>No closed shifts recorded yet</b><br>Closed shifts will appear here for audit</div>';
        }, function () { if (reg.host && reg.history) host.querySelector('.ha-toolbody').innerHTML = empty('Couldn’t load history. Try again.'); });
    }

    A.register('settlement', {
        tool: true,
        enter: function (host) {
            reg.host = host;
            reg.history = false;
            host.innerHTML = page('Cash Register', '', loading());
            host.onclick = function (e) {
                if (e.target.closest('[data-histback]')) { reg.history = false; renderRegister(); return; }
                if (e.target.closest('[data-history]')) { if (reg.venue) showHistory(); return; }
                if (e.target.closest('[data-refresh]')) { loadRegister(); return; }
                if (e.target.closest('[data-open]')) { openShiftDialog(); return; }
                if (e.target.closest('[data-drop]')) { dropDialog(); return; }
                if (e.target.closest('[data-close-shift]')) { closeoutDialog(); return; }
                var t = e.target.closest('[data-tab]');
                if (t) { reg.tab = t.dataset.tab; renderRegister(); }
            };
            loadRegister();
        },
        leave: function () { reg.host = null; reg.venue = null; reg.data = null; },
    });

    /* ====================================================== OPERATIONS CENTER === */

    var OPS_TABS = ['Revenue', 'Heatmap', 'Staff', 'Funnel', 'AI Tips'];
    var ops = { host: null, venue: null, data: null, tab: 0 };

    function loadOps() {
        if (!ops.host) return;
        (ops.venue ? Promise.resolve(ops.venue) : toolVenue()).then(function (v) {
            if (!ops.host) return;
            if (!v) return noVenue(ops.host, 'Operations Center');
            ops.venue = v;
            return A.api('venues/' + v.id + '/operations/overview', { branch: false }).then(function (r) {
                if (!ops.host) return;
                ops.data = r.data || r;
                renderOps();
            });
        }).catch(function (err) {
            if (!ops.host) return;
            if (err && err.status === 403) { ops.host.innerHTML = page('Operations Center', '', empty('You don’t have access to reports for this venue.')); return; }
            failed(ops.host, 'Operations Center', loadOps);
        });
    }

    function kpi(title, value, sub, trend) {
        var t = '';
        if (trend != null && !isNaN(trend)) {
            var up = trend >= 0;
            t = '<em class="ha-trend ' + (up ? 'is-up' : 'is-down') + '">' + (up ? '▲' : '▼') + ' ' + Math.abs(Math.round(trend * 10) / 10) + '% DoD</em>';
        }
        return '<div class="ha-kpi"><small>' + esc(title.toUpperCase()) + '</small><b>' + value + '</b>' + t + '<p>' + esc(sub) + '</p></div>';
    }

    function channelName(k) {
        return ({ online: 'Online App', offline: 'Walk-in Desk', walk_in: 'Walk-in Desk', whatsapp: 'WhatsApp Bot', standing: 'Standing Slots', web: 'Website', app: 'Haraan App' })[k]
            || String(k).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function revenueTab(r) {
        var ch = r.channel_breakdown || {};
        var keys = Object.keys(ch);
        var total = keys.reduce(function (t, k) { return t + (parseFloat(ch[k].amount) || 0); }, 0);
        return '<div class="ha-kpigrid">'
            + kpi('Today\'s Revenue', whole(r.today_revenue), 'Active Cash & Digital', parseFloat(r.day_over_day_growth_pct))
            + kpi('Yesterday', whole(r.yesterday_revenue), 'Closed Bookings')
            + kpi('Month-to-Date', whole(r.month_to_date_revenue), 'Active Month Total')
            + kpi('Projected Month-End', whole(r.projected_month_revenue), 'AI Run-rate Forecast') + '</div>'
            + '<section class="ha-mrr"><small>CONTRACTED RECURRING MRR</small><b>' + whole(r.standing_contracts_mrr) + '<span>/mo</span></b><p>Locked in by standing slots · billed every month</p></section>'
            + '<section class="ha-opscard"><b class="ha-opstitle">Revenue by Channel</b>'
            + (keys.length ? keys.map(function (k) {
                var amt = parseFloat(ch[k].amount) || 0, pct = total > 0 ? Math.round(amt / total * 100) : 0;
                return '<div class="ha-chanrow"><div class="ha-row"><span class="ha-grow">' + esc(channelName(k)) + ' <small>· ' + (ch[k].count || 0) + ' booking' + (ch[k].count === 1 ? '' : 's') + '</small></span><b>' + whole(amt) + '</b></div><span class="ha-chanbar"><i style="width:' + pct + '%"></i></span></div>';
            }).join('') : '<p class="ha-opssub">No bookings this month yet.</p>') + '</section>';
    }

    function heatColor(i) { return ({ peak: '#EF4444', high: '#FBBF24', medium: '#34D399', low: '#A7F3D0' })[i] || '#F1F5F9'; }

    function heatmapTab(o) {
        var rows = o.heatmap || [];
        var hours = rows[0] ? rows[0].hours.map(function (c) { return c.hour; }) : [];
        return '<section class="ha-opscard"><b class="ha-opstitle">Court Occupancy Heatmap (Past 4 Weeks)</b><p class="ha-opssub">Aggregated across all courts by day &amp; hour (06:00 - 24:00)</p>'
            + '<div class="ha-legend">' + [['Empty', '#E2E8F0'], ['1-20%', '#A7F3D0'], ['21-50%', '#34D399'], ['51-80%', '#FBBF24'], ['Peak >80%', '#EF4444']].map(function (l) { return '<span><i style="background:' + l[1] + '"></i>' + l[0] + '</span>'; }).join('') + '</div>'
            + '<div class="ha-heatsum"><span><b>' + Math.round(parseFloat(o.average_occupancy_pct) || 0) + '%</b><small>Avg occupancy</small></span><span><b>' + (o.peak_slots_count || 0) + '</b><small>Peak slots</small></span></div>'
            + (rows.length ? '<div class="ha-heatscroll"><table class="ha-heat"><tr><th></th>' + hours.map(function (h) { return '<th>' + (h < 10 ? '0' : '') + h + '</th>'; }).join('') + '</tr>'
                + rows.map(function (r, ri) {
                    return '<tr><th>' + esc(r.day_name) + '</th>' + r.hours.map(function (c, ci) {
                        return '<td><button type="button" data-cell="' + ri + ':' + ci + '" style="background:' + heatColor(c.intensity) + ';color:' + (c.intensity === 'peak' ? '#fff' : '#0F172A') + '">' + Math.round(c.occupancy_pct) + '%</button></td>';
                    }).join('') + '</tr>';
                }).join('') + '</table></div>' : '<p class="ha-opssub">No bookings in the past 4 weeks.</p>') + '</section>';
    }

    function staffTab(list_) {
        return '<section class="ha-opscard"><b class="ha-opstitle">Staff Desk &amp; Cash Drawer Efficiency</b><p class="ha-opssub">Tracks shift reconciliation accuracy, cash collected &amp; bookings converted</p></section>'
            + (list_.length ? list_.map(function (s) {
                var zero = Math.abs(parseFloat(s.cash_variance) || 0) < 0.01;
                return '<div class="ha-opsstaff"><span class="ha-opsbadge"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 7h-5V4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v3H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM9 12a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm4 6H5v-.57c0-.81.48-1.53 1.22-1.85a6.95 6.95 0 0 1 5.56 0A2.01 2.01 0 0 1 13 17.43V18zm-2-11V4h2v3h-2zm8 9.5h-4V15h4v1.5zm0-3h-4V12h4v1.5z"/></svg></span>'
                    + '<span class="ha-grow"><b>' + esc(s.name) + '</b><small>' + (s.shifts_completed || 0) + ' shifts completed • ' + (s.bookings_converted || 0) + ' converted</small></span>'
                    + '<span class="ha-opsnums"><b>Cash: ' + whole(s.total_cash_handled) + '</b><small class="' + (zero ? 'is-green' : 'is-red') + '">Variance: ' + whole(s.cash_variance) + '</small></span></div>';
            }).join('') : empty('No shift sessions recorded yet'));
    }

    function funnelTab(f, alerts) {
        var open = alerts.filter(function (a) { return !a.is_resolved; });
        return '<section class="ha-opscard"><b class="ha-opstitle">WhatsApp Conversion Funnel</b>'
            + '<div class="ha-row ha-convrow"><span class="ha-grow">Overall Conversion</span><b class="is-green">' + (f.conversion_rate_pct || 0) + '%</b></div>'
            + (f.stages || []).map(function (s, i, all) {
                var top = all[0] && all[0].count ? all[0].count : 0;
                var pct = top > 0 ? Math.round(s.count / top * 100) : 0;
                return '<div class="ha-stage"><div class="ha-row"><span class="ha-grow">' + esc(s.stage) + '</span><b>' + s.count + '</b></div><span class="ha-stagebar"><i style="width:' + pct + '%"></i></span>'
                    + (s.drop_off_pct > 0 ? '<small>' + s.drop_off_pct + '% drop-off</small>' : '') + '</div>';
            }).join('') + '</section>'
            + '<b class="ha-opstitle ha-pad4">Revenue Leakage Alerts (' + open.length + ')</b><p class="ha-opssub ha-pad4">Detects abandoned holds, cash mismatches &amp; unutilized peak hours</p>'
            + (open.length ? open.map(function (a) {
                return '<div class="ha-alert"><div class="ha-row"><b class="ha-grow">' + esc(a.title) + '</b><button type="button" data-resolve="' + a.id + '">Resolve</button></div><p>' + esc(a.description) + '</p></div>';
            }).join('') : '<div class="ha-allgood"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>No revenue leakage detected. Venue running at peak efficiency!</div>');
    }

    function aiTab(list_) {
        var live = list_.filter(function (s) { return s.status !== 'dismissed'; });
        return '<section class="ha-opscard"><b class="ha-opstitle">AI-Driven Business Suggestions</b><p class="ha-opssub">Revenue optimization algorithms scanning booking trends &amp; pricing matrix</p></section>'
            + (live.length ? live.map(function (s) {
                return '<div class="ha-sugg"><div class="ha-row"><em>' + esc(String(s.category || '').toUpperCase()) + '</em><span class="ha-grow"></span><b class="is-green">+' + whole(s.projected_revenue_impact) + '/mo</b></div>'
                    + '<strong>' + esc(s.title) + '</strong><p>' + esc(s.rationale) + '</p>'
                    + (s.status === 'applied'
                        ? '<span class="ha-applied"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Applied to Pricing Matrix</span>'
                        : '<div class="ha-row ha-gap8"><button type="button" class="ha-regbtn is-ink" data-apply="' + s.id + '">✨ 1-Tap Apply</button><button type="button" class="ha-regbtn is-line" data-dismiss="' + s.id + '">Dismiss</button></div>') + '</div>';
            }).join('') : empty('All suggestions up to date!'));
    }

    function renderOps() {
        var host = ops.host, o = ops.data || {};
        if (!host) return;
        var body = [revenueTab(o.revenue || {}), heatmapTab(o.occupancy || {}), staffTab(o.staff || []), funnelTab(o.funnel || {}, o.leakage_alerts || []), aiTab(o.ai_suggestions || [])][ops.tab];
        host.innerHTML = '<header class="ha-toolbar"><button type="button" class="ha-ibtn" data-back aria-label="Back"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>'
            + '<span class="ha-grow"><b>Operations Center</b><small>Real-Time Revenue, Occupancy &amp; AI Suggestions</small></span>' + refreshBtn() + '</header>'
            + '<div class="ha-toolbody ha-opsbody"><div class="ha-opstabs">' + OPS_TABS.map(function (t, i) { return '<button type="button" data-otab="' + i + '"' + (i === ops.tab ? ' class="is-on"' : '') + '>' + t + '</button>'; }).join('') + '</div>' + body + '</div>';
    }

    function opsAction(path, okText) {
        A.apiSend('POST', 'venues/' + ops.venue.id + '/operations/' + path).then(function () { A.toast(okText); loadOps(); }, function (err) { A.toast(err.message || 'Couldn’t do that. Try again.'); });
    }

    A.register('operations', {
        tool: true,
        enter: function (host) {
            ops.host = host;
            host.innerHTML = page('Operations Center', '', loading());
            host.onclick = function (e) {
                var b;
                if (e.target.closest('[data-refresh]')) { loadOps(); return; }
                if ((b = e.target.closest('[data-otab]'))) { ops.tab = +b.dataset.otab; renderOps(); host.scrollIntoView(); return; }
                if ((b = e.target.closest('[data-resolve]'))) { b.disabled = true; opsAction('alerts/' + b.dataset.resolve + '/resolve', 'Alert resolved'); return; }
                if ((b = e.target.closest('[data-apply]'))) { b.disabled = true; opsAction('suggestions/' + b.dataset.apply + '/apply', 'Applied to pricing'); return; }
                if ((b = e.target.closest('[data-dismiss]'))) { b.disabled = true; opsAction('suggestions/' + b.dataset.dismiss + '/dismiss', 'Suggestion dismissed'); return; }
                if ((b = e.target.closest('[data-cell]'))) {
                    var p = b.dataset.cell.split(':'), row = ops.data.occupancy.heatmap[+p[0]], c = row.hours[+p[1]];
                    dialog('<b class="ha-dlg-title">' + esc(row.day_name + ' ' + c.hour_label) + '</b><p class="ha-dlg-text"><b>Occupancy: ' + c.occupancy_pct + '%</b><br>Booked slots: ' + c.booked_count + ' / ' + c.capacity + '<br>Intensity level: ' + esc(String(c.intensity).toUpperCase()) + '</p>'
                        + '<div class="ha-row ha-dlg-acts"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn is-main" data-close>Close</button></div>');
                }
            };
            loadOps();
        },
        leave: function () { ops.host = null; ops.venue = null; ops.data = null; },
    });
})();
