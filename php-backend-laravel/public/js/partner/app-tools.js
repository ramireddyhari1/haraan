/**
 * Haraan Partner: the app's drawer tools on a phone, batch one — Customers, Staff,
 * Payouts, Reports, Notifications and Support.
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
})();
