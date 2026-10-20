/**
 * Haraan Partner: the app's Bookings tab — the venue's day desk — in the fast phone app.
 *
 * The day at a glance (date strip, the day's money, the court grid), then the counter's
 * work in sheets: a walk-in (time rail, the courts drawn, the customer, how they pay),
 * Block mode with hold-to-block, a pass the number holds, and the walk-in paying online
 * (UPI QR, Razorpay checkout or a link) with a countdown that confirms by itself.
 *
 * Reads and writes the same /api/partner/* endpoints as the Android app's desk.
 * Ported from: daybookings/ui/DayBookingsScreen.kt, DayCalendarStrip.kt, DayStatsHeader.kt,
 * DayBookingsGrid.kt, DayBookingsList.kt, WalkInBookingSheet.kt, BlockCourt.kt,
 * DeskPaymentSheet.kt, BookingDetailsSheet.kt and DayBookingsViewModel.kt.
 */
(function () {
    'use strict';

    var A = window.HaApp;
    // Fast app only: with it off, phones keep the console's own day desk.
    if (!A || !A.cfg.spa || window.__haBookings) return;
    window.__haBookings = true;

    var esc = A.esc, rupees = A.rupees, inr = A.inr, mat = A.mat, h = A.h, haptic = A.haptic, C = A.court;
    var DAY_MS = 86400000;
    var MONTHS = A.months;
    var WEEK = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    var ICON = {
        prev: 'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z',
        next: 'M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z',
        cal: 'M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z',
        search: 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
        clear: 'M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
        grid: 'M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z',
        tune: 'M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z',
        chart: 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z',
        add: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
        lock: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z',
        block: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9A7.902 7.902 0 0 1 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1A7.902 7.902 0 0 1 20 12c0 4.42-3.58 8-8 8z',
        build: 'M22.7 19 13.6 9.9c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z',
        school: 'M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3 1 9l11 6 9-4.91V17h2V9L12 3z',
        trophy: 'M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0 0 11 15.9V19H7v2h10v-2h-4v-3.1a5.01 5.01 0 0 0 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z',
        busy: 'M9.31 17l2.44-2.44L14.19 17l1.06-1.06-2.44-2.44 2.44-2.44L14.19 10l-2.44 2.44L9.31 10l-1.06 1.06 2.44 2.44-2.44 2.44L9.31 17zM19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z',
        person: 'M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
        cash: 'M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z',
        qr: 'M3 11h8V3H3v8zm2-6h4v4H5V5zm8-2v8h8V3h-8zm6 6h-4V5h4v4zM3 21h8v-8H3v8zm2-6h4v4H5v-4zm13 2h-2v2h2v2h-2v-2h-2v2h-2v-4h2v2h2v-2h2v-2h2v2h-2v2zm0 4h2v-2h-2v2zm-4-8h-2v2h2v-2zm4 0h2v2h-2v-2z',
        link: 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z',
        phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        chat: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z',
        copy: 'M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z',
        check: 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
        refresh: 'M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.73 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z',
        share: 'M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11A2.99 2.99 0 0 0 21 5a3 3 0 0 0-6 0c0 .24.04.47.09.7L8.04 9.81A2.99 2.99 0 0 0 3 12a2.99 2.99 0 0 0 5.04 2.19l7.12 4.16c-.05.21-.08.43-.08.65A2.92 2.92 0 1 0 18 16.08z',
        pay: 'M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z',
    };
    function ic(name, cls) { return '<svg class="' + (cls || 'ha-ic') + '" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="' + ICON[name] + '"/></svg>'; }

    /** The reasons the server accepts (VenueBlock::KINDS), in the order the desk reaches for them. */
    var KINDS = [['maintenance', 'Maintenance', 'build'], ['private', 'Private hire', 'lock'], ['academy', 'Coaching', 'school'], ['tournament', 'Tournament', 'trophy'], ['holiday', 'Closed', 'busy']];
    function kindIcon(k) { var x = KINDS.filter(function (y) { return y[0] === k; })[0]; return x ? x[2] : 'block'; }
    var METHODS = [['cash', 'Cash', 'cash'], ['upi_qr', 'UPI QR', 'qr'], ['link', 'Link', 'link']];
    var FILTERS = [['all', 'All'], ['paid', 'Paid'], ['checked', 'Checked In'], ['walkin', 'Walk-in'], ['cancelled', 'Cancelled']];

    var desk = {
        host: null, venue: null, date: null, grid: null, list: null, failed: false, loading: false,
        mode: 'grid', query: '', filter: 'all', timer: null, scrollX: 0, seq: 0,
    };

    /* ------------------------------------------------------------------ dates --- */

    function ymdOf(d) { return A.ymd(d); }
    function dateOf(s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
    function addDays(s, n) { return ymdOf(new Date(dateOf(s).getTime() + n * DAY_MS)); }
    function today() { return ymdOf(new Date()); }
    function dayDiff(s) { return Math.round((dateOf(s) - dateOf(today())) / DAY_MS); }

    /** "TODAY · TUE, 20 OCT" (formatFriendlyHeader). */
    function friendlyHeader(s) {
        var d = dateOf(s), diff = dayDiff(s);
        var full = (WEEK[d.getDay()] + ', ' + (d.getDate() < 10 ? '0' : '') + d.getDate() + ' ' + MONTHS[d.getMonth()]).toUpperCase();
        return diff === 0 ? 'TODAY · ' + full : diff === 1 ? 'TOMORROW · ' + full : diff === -1 ? 'YESTERDAY · ' + full : full;
    }
    /** "Today, 20 Oct" / "Tomorrow, 21 Oct" / "Wed, 22 Oct" (friendlyDate). */
    function friendlyDate(s) {
        var d = dateOf(s), diff = dayDiff(s), short = d.getDate() + ' ' + MONTHS[d.getMonth()];
        return diff === 0 ? 'Today, ' + short : diff === 1 ? 'Tomorrow, ' + short : WEEK[d.getDay()] + ', ' + short;
    }

    /* ------------------------------------------------------------ the day data --- */

    function slotTime(s) { return s.time || s.label || ''; }
    function slotsOf(grid) {
        // Ordered by the clock: the server lists them in the admin's sort order.
        return ((grid && grid.slots) || []).slice().sort(function (a, b) { return A.slotStart(slotTime(a)) - A.slotStart(slotTime(b)); });
    }
    function cellOf(slot, courtId) { return (slot && slot.courts || []).filter(function (c) { return c.court_id === courtId; })[0] || null; }
    function courtFree(slot, court) {
        var c = cellOf(slot, court.id);
        return !c || (!c.is_booked && !c.is_held && c.allowed !== false && !c.block);
    }
    function courtPrice(slot, courtId) { var c = cellOf(slot, courtId); return c ? c.price : slot ? slot.price : null; }
    function isWalkIn(ch) { ch = String(ch || '').toLowerCase(); return ch === 'offline' || ch === 'walk-in' || ch === 'desk'; }

    /** The bookings feed, as the desk reads it (DayBookingItem). */
    function itemOf(b) {
        var amount = +b.amount || 0, paid = +b.amount_paid || 0, status = String(b.status || '');
        var cancelled = status.toUpperCase() === 'CANCELLED';
        var awaiting = status.toUpperCase() === 'PENDING';
        var balance = Math.max(0, amount - paid);
        return {
            id: b.id, ticket: b.ticket_code || 'HRN-' + b.id, customer: b.customer || 'Guest', phone: b.phone || null,
            channel: b.channel || 'online', walkIn: isWalkIn(b.channel), status: status, checkedIn: (+b.checked_in || 0) > 0,
            amount: amount, paid: paid, balance: balance, payStatus: String(b.payment_status || ''), method: b.payment_method || null,
            slotDate: b.slot_date || '', slotLabel: b.slot_label || '', cancelled: cancelled,
            due: balance > 0 && !cancelled && amount > 0 && !awaiting,
        };
    }

    /** The day's numbers (calculateStats): cells for capacity, the bookings for money. */
    function statsOf(grid, items) {
        var total = 0, booked = 0, open = 0, expected = 0;
        slotsOf(grid).forEach(function (s) {
            var cells = s.courts || [];
            if (cells.length) {
                cells.forEach(function (c) {
                    if (c.allowed === false) return;
                    total++;
                    if (c.is_booked || c.booked > 0) { booked++; expected += +c.price || 0; }
                    else if (!c.is_held && !grid.is_blocked) open++;
                });
            } else {
                total += +s.capacity || 0; booked += +s.booked || 0; open += +s.available || 0; expected += (+s.booked || 0) * (+s.price || 0);
            }
        });
        var collected = 0, due = 0, chase = 0, cash = 0, upi = 0, online = 0;
        var live = (items || []).filter(function (b) { return !b.cancelled; });
        live.forEach(function (b) {
            collected += b.paid;
            if (b.due) { due += b.balance; chase++; }
            var m = String(b.method || '').toLowerCase();
            if (m.indexOf('cash') >= 0) cash += b.paid; else if (m.indexOf('upi') >= 0) upi += b.paid; else online += b.paid;
        });
        // A session spent from a pass is ₹0, a discounted walk-in its own price.
        if (live.length) expected = live.reduce(function (t, b) { return t + b.amount; }, 0);
        return {
            total: total, booked: booked, open: open, rate: total > 0 ? Math.min(1, booked / total) : 0,
            expected: expected, collected: collected, due: due, chase: chase, cash: cash, upi: upi, online: online,
            blocked: !!(grid && grid.is_blocked),
        };
    }

    function filtered() {
        var q = desk.query.trim().toLowerCase();
        return (desk.list || []).filter(function (b) {
            var hit = !q || b.customer.toLowerCase().indexOf(q) >= 0 || b.ticket.toLowerCase().indexOf(q) >= 0 || (b.phone && b.phone.indexOf(q) >= 0);
            var f = desk.filter;
            var st = f === 'all' ? true : f === 'paid' ? b.payStatus.toLowerCase() === 'paid' && !b.cancelled : f === 'unpaid' ? b.due
                : f === 'checked' ? b.checkedIn : f === 'walkin' ? b.walkIn && !b.cancelled : f === 'cancelled' ? b.cancelled : true;
            return hit && st;
        });
    }

    /* ----------------------------------------------------------------- loading --- */

    function venueFor() {
        return A.api('venues', { branch: false }).then(function (r) {
            var vs = r && r.data ? r.data : (r || []);
            return vs.filter(function (v) { return v.id === A.cfg.branch; })[0] || vs[0] || null;
        });
    }

    /** The grid and the day's bookings, together; [quiet] keeps what's on screen meanwhile. */
    function load(quiet) {
        var host = desk.host;
        if (!host) return;
        var seq = ++desk.seq;
        desk.loading = true;
        if (!quiet && !desk.grid) host.innerHTML = skeleton();
        var v = desk.venue;
        var p = v ? Promise.resolve(v) : venueFor();
        p.then(function (venue) {
            if (seq !== desk.seq) return null;
            if (!venue) { host.innerHTML = '<div class="ha-tab"><div class="ha-fail"><b>No venue yet</b><span>Haraan lists venues for their owners. Message us and we’ll add yours.</span></div></div>'; return null; }
            desk.venue = venue;
            var fresh = !!quiet;
            return Promise.all([
                A.api('venues/' + venue.id + '/day', { branch: false, date: desk.date, fresh: fresh }),
                A.soft(A.api('bookings', { branch: false, venue_id: venue.id, date: desk.date, fresh: fresh })),
            ]).then(function (r) {
                if (seq !== desk.seq || !desk.host) return;
                desk.grid = r[0] || { slots: [], courts: [] };
                var rows = r[1] && r[1].data ? r[1].data : (r[1] || []);
                desk.list = rows.filter(function (b) { return !b.slot_date || String(b.slot_date).slice(0, 10) === desk.date; }).map(itemOf);
                desk.failed = false;
                render();
                openFromHome();
            });
        }).catch(function () {
            if (seq !== desk.seq || !desk.host) return;
            desk.failed = true;
            if (!desk.grid) {
                host.innerHTML = '<div class="ha-tab"><div class="ha-fail"><b>Couldn’t load the day</b><span>Check your connection and try again.</span><button type="button" class="ha-cta" data-retry>Try again</button></div></div>';
            } else A.toast('Couldn’t refresh. Showing what was there.');
        }).then(function () { if (seq === desk.seq) desk.loading = false; });
    }

    function skeleton() {
        return '<div class="ha-tab ha-desk"><div class="ha-skel ha-skel-desk"><i></i><b class="s2"></b><b></b><b class="s3"></b></div></div>';
    }

    function selectDate(s) {
        if (s === desk.date) return;
        desk.date = s;
        haptic('tick');
        render(true);
        load(false);
    }

    /* ------------------------------------------------------------------ render --- */

    function render(soft) {
        var host = desk.host;
        if (!host) return;
        var keepX = desk.scrollX;
        var stats = statsOf(desk.grid, desk.list);
        var canBook = A.can('bookings') && !stats.blocked;
        var venue = desk.venue || {};
        var listMode = desk.mode === 'list';
        var out = '<div class="ha-tab ha-desk' + (soft ? ' is-soft' : '') + (listMode ? ' is-list' : '') + '">'
            + '<div class="ha-dk-title"><div><b>' + esc(venue.name || 'Your venue') + '</b><small>Day bookings &amp; desk</small></div>'
            + '<button type="button" class="ha-ibtn ha-dk-act ha-dk-tocourts" data-mode="grid" aria-label="Back to courts">' + ic('grid') + '</button>'
            + (A.can('pricing') && A.u.pricing ? '<a class="ha-ibtn ha-dk-act" href="' + esc(A.u.pricing) + '" aria-label="Pricing">' + ic('tune') + '</a>' : '')
            + (A.can('reports') && A.u.reports ? '<a class="ha-ibtn ha-dk-act" href="' + esc(A.u.reports) + '" aria-label="Reports">' + ic('chart') + '</a>' : '')
            + '</div>'
            + calendarStrip()
            + statsCard(stats, A.can('bookings'))
            + searchRow()
            + '<div class="ha-dk-body">' + (listMode ? listHtml() : gridHtml(canBook)) + '</div>'
            + '</div>'
            + (canBook ? '<button type="button" class="ha-dk-fab" data-walkin>' + ic('add') + '<b>Walk-in</b></button>' : '');
        host.innerHTML = out;
        var strip = host.querySelector('.ha-dk-pills');
        var on = strip && strip.querySelector('.is-on');
        if (on) strip.scrollLeft = Math.max(0, on.offsetLeft - strip.clientWidth / 2 + on.offsetWidth / 2);
        wireBody(keepX);
        countRing(host);
    }

    /** Search and the filter chips only redraw what's under them: the keyboard stays up. */
    function renderBody() {
        var host = desk.host;
        if (!host || !host.querySelector('.ha-dk-body')) return render();
        var stats = statsOf(desk.grid, desk.list);
        var canBook = A.can('bookings') && !stats.blocked;
        host.querySelector('.ha-desk').classList.toggle('is-list', desk.mode === 'list');
        host.querySelectorAll('.ha-dk-chips button').forEach(function (b) { b.classList.toggle('is-on', b.dataset.filter === desk.filter); });
        host.querySelector('.ha-dk-body').innerHTML = desk.mode === 'list' ? listHtml() : gridHtml(canBook);
        wireBody(desk.scrollX);
    }

    function wireBody(keepX) {
        var body = desk.host && desk.host.querySelector('.ha-dk-scroll');
        if (!body) return;
        body.scrollLeft = keepX || 0;
        syncHead(body);
        body.addEventListener('scroll', function () { desk.scrollX = body.scrollLeft; syncHead(body); }, { passive: true });
    }

    function syncHead(body) {
        var track = desk.host && desk.host.querySelector('.ha-dk-htrack');
        if (track) track.style.transform = 'translateX(' + (-body.scrollLeft) + 'px)';
    }

    function calendarStrip() {
        var pills = '';
        for (var i = -7; i <= 14; i++) {
            var s = addDays(desk.date, i), d = dateOf(s);
            var isToday = s === today(), on = s === desk.date;
            pills += '<button type="button" class="ha-dk-pill' + (on ? ' is-on' : '') + (isToday ? ' is-today' : '') + '" data-date="' + s + '">'
                + '<small>' + WEEK[d.getDay()].toUpperCase() + '</small><b>' + (d.getDate() < 10 ? '0' : '') + d.getDate() + '</b>' + (isToday && !on ? '<i></i>' : '') + '</button>';
        }
        return '<section class="ha-dk-cal"><div class="ha-dk-calhead">'
            + '<button type="button" class="ha-ibtn" data-day="-1" aria-label="Previous day">' + ic('prev') + '</button>'
            + '<label class="ha-dk-calpick">' + ic('cal') + '<b>' + esc(friendlyHeader(desk.date)) + '</b><input type="date" value="' + desk.date + '" aria-label="Pick a date"></label>'
            + '<button type="button" class="ha-ibtn" data-day="1" aria-label="Next day">' + ic('next') + '</button></div>'
            + '<div class="ha-dk-pills">' + pills + '</div></section>';
    }

    function statsCard(st, canManage) {
        var share = st.expected > 0 ? Math.min(1, st.collected / st.expected) : 0;
        var split = [st.cash > 0 ? 'Cash ' + rupees(st.cash) : null, st.upi > 0 ? 'UPI ' + rupees(st.upi) : null, st.online > 0 ? 'Online ' + rupees(st.online) : null].filter(Boolean);
        var pct = Math.round(st.rate * 100);
        return '<section class="ha-dk-stats">'
            + (st.blocked ? '<div class="ha-dk-closed">' + ic('block') + '<b>Venue closed on this day</b>' + (canManage ? '<button type="button" data-reopen>Reopen</button>' : '') + '</div>' : '')
            + '<div class="ha-dk-money"><div class="ha-dk-gauge" style="--p:' + pct + '"><svg viewBox="0 0 92 92" aria-hidden="true"><defs><linearGradient id="ha-gauge" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#93C5FD"/><stop offset=".5" stop-color="#2F6BFF"/><stop offset="1" stop-color="#1D4ED8"/></linearGradient></defs>'
            + '<circle cx="46" cy="46" r="41.5" fill="none" stroke="#EEF2F7" stroke-width="9"/>'
            + (pct > 0 ? '<circle class="ha-dk-arc" cx="46" cy="46" r="41.5" fill="none" stroke="url(#ha-gauge)" stroke-width="9" stroke-linecap="round" pathLength="100" transform="rotate(-90 46 46)"/>' : '')
            + '</svg><span><b data-count-to="' + pct + '">' + pct + '</b><em>%</em></span><small>FULL</small></div>'
            + '<div class="ha-dk-fig"><small>EXPECTED</small><b>' + rupees(st.expected) + '</b>'
            + (st.expected > 0 ? '<span class="ha-dk-bar"><i style="--w:' + (share * 100).toFixed(1) + '%"></i></span>' : '')
            + '<p><b class="' + (st.collected > 0 ? 'is-in' : '') + '">' + rupees(st.collected) + '</b> collected</p>'
            + (split.length >= 2 ? '<em>' + esc(split.join(' · ')) + '</em>' : '') + '</div></div>'
            + (st.due > 0 ? '<button type="button" class="ha-dk-due" data-due><i></i><span><b>' + rupees(st.due) + ' still to collect</b>'
                + (st.chase > 0 ? '<small>' + (st.chase === 1 ? '1 booking unpaid' : st.chase + ' bookings unpaid') + '</small>' : '') + '</span><em>View</em>' + ic('next') + '</button>' : '')
            + '<div class="ha-dk-counts"><span><b>' + st.total + '</b><small>Slots</small></span><span><b class="' + (st.booked > 0 ? 'is-blue' : '') + '">' + st.booked + '</b><small>Booked</small></span>'
            + '<span><b class="' + (st.open > 0 ? 'is-green' : 'is-muted') + '">' + st.open + '</b><small>' + (st.open > 0 ? '<i></i>' : '') + 'Open</small></span></div>'
            + '</section>';
    }

    function searchRow() {
        return '<div class="ha-dk-search"><label>' + ic('search') + '<input type="search" inputmode="search" placeholder="Search name, phone or ticket" value="' + esc(desk.query) + '" autocomplete="off" enterkeyhint="search">'
            + '<button type="button" data-clear aria-label="Clear search">' + ic('clear') + '</button></label></div>'
            + '<div class="ha-dk-chips">' + FILTERS.map(function (x) {
                return '<button type="button" class="' + (desk.filter === x[0] ? 'is-on' : '') + '" data-filter="' + x[0] + '">' + x[1] + '</button>';
            }).join('') + '</div>';
    }

    /* ------------------------------------------------------------------- grid --- */

    function blockWindow(b) {
        if (b.all_day) return 'All day';
        function fmt(hm) {
            if (!hm) return null;
            var p = String(hm).split(':'), hh = +p[0], mm = +(String(p[1] || '0').slice(0, 2));
            if (isNaN(hh)) return null;
            return (hh % 12 === 0 ? 12 : hh % 12) + ':' + (mm < 10 ? '0' : '') + mm + ' ' + (hh >= 12 && hh <= 23 ? 'PM' : 'AM');
        }
        var s = fmt(b.start), e = fmt(b.end);
        return s ? (e ? s + ' – ' + e : s) : null;
    }

    function gridHtml(canBook) {
        var g = desk.grid;
        if (!g) return '';
        var courts = g.courts || [];
        var slots = slotsOf(g);
        if (!courts.length && slots.length) courts = [{ id: null, name: 'Court', sports: [] }];
        var head = '<div class="ha-dk-head"><span class="ha-dk-time">TIME</span><div class="ha-dk-hclip"><div class="ha-dk-htrack">'
            + courts.map(function (c) {
                return '<span class="ha-dk-hcourt"><b>' + esc(c.name) + '</b>' + (c.sports && c.sports.length ? '<small>' + esc(c.sports.join(', ')) + '</small>' : '') + '</span>';
            }).join('') + '</div></div></div>';
        if (!slots.length) return '<section class="ha-dk-grid">' + head + '<div class="ha-dk-empty">No time slots on this day</div></section>';
        var rows = slots.map(function (s, si) {
            var cells = courts.map(function (court, ci) {
                var cell = court.id != null ? cellOf(s, court.id) : null;
                var price = cell ? cell.price : s.price;
                var at = ' data-slot="' + si + '" data-court="' + ci + '" style="--o:' + Math.min(si + ci, 14) + '"';
                var bks = cell ? (cell.bookings || []) : (s.bookings || []);
                var booked = cell ? (cell.is_booked || cell.booked > 0) : (s.booked > 0 && s.available <= 0);
                if (booked && bks.length) {
                    var b = bks[0], walk = isWalkIn(b.channel);
                    return '<button type="button" class="ha-dk-cell is-booked' + (walk ? ' is-walk' : '') + '"' + at + ' data-booking="' + b.id + '">'
                        + '<span class="ha-dk-ctop"><em>' + (walk ? 'WALK-IN' : 'ONLINE') + '</em>' + (b.checked_in > 0 ? '<i>✓ IN</i>' : '') + '</span>'
                        + '<b>' + esc(b.customer || 'Guest') + '</b><small>' + rupees(b.amount) + '</small></button>';
                }
                if (cell && cell.is_held) {
                    return '<span class="ha-dk-cell is-held"' + at + '>' + ic('lock') + '<b>IN CHECKOUT</b><small>Reserved</small></span>';
                }
                if (cell && cell.block && !g.is_blocked) {
                    var w = blockWindow(cell.block);
                    return '<button type="button" class="ha-dk-cell is-block"' + at + ' data-block>'
                        + '<span class="ha-dk-ctop">' + ic(kindIcon(cell.block.kind)) + '<em>Blocked</em></span>'
                        + '<b>' + esc(cell.block.label || 'Blocked') + '</b>' + (w ? '<small>' + esc(w) + '</small>' : '') + '</button>';
                }
                if (g.is_blocked || (cell && cell.allowed === false) || s.is_open === false) {
                    return '<span class="ha-dk-cell is-closed"' + at + '><b>CLOSED</b></span>';
                }
                return '<button type="button" class="ha-dk-cell is-open"' + at + (canBook ? ' data-open' : ' disabled') + '>'
                    + '<span class="ha-dk-ctop"><i></i><em>' + (cell && cell.is_peak ? 'Open · Peak' : 'Open') + '</em></span>'
                    + '<b>' + rupees(price) + '</b>' + (canBook ? '<span class="ha-dk-plus">' + ic('add') + '</span>' : '') + '</button>';
            }).join('');
            return '<div class="ha-dk-row"><span class="ha-dk-time"><b>' + esc(slotTime(s)) + '</b></span>' + cells + '</div>';
        }).join('');
        return '<section class="ha-dk-grid">' + head + '<div class="ha-dk-scroll">' + rows + '</div></section>';
    }

    /* ------------------------------------------------------------------- list --- */

    function listHtml() {
        var rows = filtered();
        if (!rows.length) return '<section class="ha-dk-none"><b>No bookings matching criteria</b><small>Try adjusting your search or filters</small></section>';
        return '<section class="ha-dk-list">' + rows.map(function (b) {
            var digits = String(b.phone || '').replace(/\D/g, '');
            return '<article class="ha-dk-item' + (b.cancelled ? ' is-off' : '') + '" data-item="' + b.id + '">'
                + '<span class="ha-dk-av' + (b.walkIn ? ' is-walk' : '') + '">' + esc(b.customer.trim().charAt(0).toUpperCase() || '•') + '</span>'
                + '<div class="ha-dk-who"><b>' + esc(b.customer) + '</b>'
                + (b.cancelled ? '<em class="is-off">Cancelled</em>' : b.checkedIn ? '<em class="is-in">Checked in</em>' : '')
                + '<small>' + esc([b.walkIn ? 'Walk-in' : 'Online', b.slotLabel, b.ticket].filter(Boolean).join(' · ')) + '</small></div>'
                + '<div class="ha-dk-amt"><b>' + rupees(b.amount) + '</b>' + (b.cancelled ? '' : b.due ? '<small class="is-due">' + rupees(b.balance) + ' due</small>' : '<small class="is-paid">Paid</small>') + '</div>'
                + (b.cancelled ? '' : '<div class="ha-dk-itemacts">'
                    + (digits.length >= 10 ? '<a href="tel:+91' + digits.slice(-10) + '" aria-label="Call">' + ic('phone') + '</a><a href="https://wa.me/91' + digits.slice(-10) + '" target="_blank" rel="noopener" aria-label="WhatsApp" class="is-wa">' + ic('chat') + '</a>' : '')
                    + (!b.checkedIn ? '<button type="button" data-checkin="' + esc(b.ticket) + '">Check-in</button>' : '') + '</div>')
                + '</article>';
        }).join('') + '</section>';
    }

    /** The gauge counts up as its arc sweeps (OccupancyGauge). */
    function countRing(host) {
        var el = host.querySelector('[data-count-to]');
        if (!el) return;
        var to = +el.getAttribute('data-count-to');
        var from = +(desk.lastPct || 0);
        desk.lastPct = to;
        if (from === to || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var t0 = performance.now();
        el.textContent = from;
        requestAnimationFrame(function step(t) {
            var k = Math.min(1, Math.max(0, (t - t0) / 750)), e = 1 - Math.pow(1 - k, 3);
            el.textContent = Math.round(from + (to - from) * e);
            if (k < 1) requestAnimationFrame(step);
        });
    }

    /* ============================================================= WALK-IN SHEET === */

    var wk = null;

    /**
     * Taking a walk-in at the counter: when, who, which court, how they pay. One button
     * that says exactly what it will do and for how much.
     */
    function openWalkIn(slot, courtId) {
        var g = desk.grid;
        if (!g) return;
        var slots = slotsOf(g);
        if (!slot) {
            var sellable = slots.filter(function (s) { return s.is_open !== false && (s.courts && s.courts.length ? s.courts.some(function (c) { return !c.is_booked && !c.is_held && c.allowed !== false && !c.block; }) : s.available > 0); });
            // Today, the big button lands on the hour still running or the next one, not on
            // a free 6 AM long gone; the rail still offers the whole day.
            var now = A.minutesNow();
            var ahead = desk.date === today() ? sellable.filter(function (s) { return A.slotStart(slotTime(s)) + 60 > now; }) : sellable;
            slot = ahead[0] || sellable[0];
        }
        if (!slot) { haptic('reject'); A.toast('Nothing left to sell on this day.'); return; }
        wk = {
            slotId: slot.slot_id, courtId: courtId, name: '', phone: '', method: 'cash', passes: [], passId: null,
            tried: false, blocking: false, blockSlots: 1, blockKind: 'maintenance', note: '', busy: false, order: 0, lastOrder: 0, amount: null,
        };
        var s = A.sheet('<div class="ha-wk-body"></div><div class="ha-wk-foot"></div>', 'ha-wk');
        wk.sheet = s;
        s.onclose = function () { wk = null; };
        s.addEventListener('click', onWalkInClick);
        s.addEventListener('input', onWalkInInput);
        s.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            if (e.target.name === 'name') { e.preventDefault(); var ph = s.querySelector('input[name=phone]'); if (ph) ph.focus(); }
            else if (e.target.name === 'phone') { e.preventDefault(); e.target.blur(); }
        });
        renderWalkIn(true);
        wireHold(s);
        // Let the sheet finish rising first, or the keyboard fights the animation.
        setTimeout(function () { var n = s.querySelector('input[name=name]'); if (n && wk && !wk.blocking) try { n.focus({ preventScroll: true }); } catch (e) { n.focus(); } }, 300);
    }

    function wkSlots() {
        var g = desk.grid;
        return slotsOf(g).filter(function (s) {
            return s.slot_id === wk.slotId || (s.is_open !== false && (s.courts && s.courts.length
                ? s.courts.some(function (c) { return !c.is_booked && !c.is_held && c.allowed !== false && !c.block; })
                : s.available > 0));
        });
    }

    /** The slots this court is free for, back to back, from the picked one (freeRun). */
    function freeRun() {
        var slots = slotsOf(desk.grid).filter(function (s) { return s.is_open !== false; });
        var starts = slots.map(function (s) { return A.slotStart(slotTime(s)); }).filter(isFinite).filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
        var gaps = [];
        for (var i = 1; i < starts.length; i++) if (starts[i] > starts[i - 1]) gaps.push(starts[i] - starts[i - 1]);
        var len = gaps.length ? Math.max(15, Math.min(120, Math.min.apply(null, gaps))) : 60;
        var at = -1;
        slots.forEach(function (s, k) { if (at < 0 && s.slot_id === wk.slotId) at = k; });
        if (at < 0 || wk.courtId == null) return { run: [], len: len };
        var run = [], expect = A.slotStart(slotTime(slots[at]));
        for (var j = at; j < slots.length; j++) {
            var st = A.slotStart(slotTime(slots[j]));
            if (st !== expect) break;
            var c = cellOf(slots[j], wk.courtId);
            if (!c || c.is_booked || c.is_held || c.allowed === false || c.block) break;
            run.push(slots[j]);
            expect = st + len;
        }
        return { run: run, len: len };
    }

    function hm(m) { return (m < 600 ? '0' : '') + Math.floor(m / 60) + ':' + (m % 60 < 10 ? '0' : '') + (m % 60); }
    function clock12(m) { var x = m % 1440, hh = Math.floor(x / 60); return (hh % 12 === 0 ? 12 : hh % 12) + ':' + (x % 60 < 10 ? '0' : '') + (x % 60) + ' ' + (hh >= 12 ? 'PM' : 'AM'); }
    function duration(min) { return min % 60 === 0 ? (min / 60) + ' hr' + (min > 60 ? 's' : '') : min > 60 ? Math.floor(min / 60) + 'h ' + (min % 60) + 'm' : min + ' min'; }
    var CLOCK = /(\d{1,2}(?::\d{2})?)\s*([AaPp][Mm])?/g;
    function clocks(raw) { var out = [], m; CLOCK.lastIndex = 0; while ((m = CLOCK.exec(raw || ''))) { if (m[0].trim()) out.push([m[1], (m[2] || '').toUpperCase()]); } return out; }
    function startLabel(raw) { var c = clocks(raw)[0]; return c ? c[0] + (c[1] ? ' ' + c[1] : '') : String(raw || ''); }
    function endLabel(raw) { var c = clocks(raw)[1]; return c ? c[0] + (c[1] ? ' ' + c[1] : '') : null; }
    function groupPhone(p) { return p.length > 5 ? p.slice(0, 5) + ' ' + p.slice(5) : p; }

    function renderWalkIn(first) {
        if (!wk) return;
        var s = wk.sheet, g = desk.grid;
        var courts = g.courts || [];
        var slots = wkSlots();
        var slot = slotsOf(g).filter(function (x) { return x.slot_id === wk.slotId; })[0];
        var free = courts.filter(function (c) { return courtFree(slot, c); });
        if (free.length && !free.some(function (c) { return c.id === wk.courtId; })) wk.courtId = free[0].id;
        var court = courts.filter(function (c) { return c.id === wk.courtId; })[0];
        var prices = {};
        free.forEach(function (c) { prices[c.id] = courtPrice(slot, c.id); });
        var distinct = Object.keys(prices).map(function (k) { return prices[k]; }).filter(function (v, i, a) { return v != null && a.indexOf(v) === i; });
        var price = courtPrice(slot, wk.courtId);
        if (price == null) price = slot ? slot.price : 0;
        var fr = freeRun();
        if (fr.run.length && wk.blockSlots > fr.run.length) wk.blockSlots = fr.run.length;
        var bStart = fr.run.length ? A.slotStart(slotTime(fr.run[0])) : null;
        var bEnd = bStart != null ? bStart + Math.min(wk.blockSlots, fr.run.length) * fr.len : null;
        var order = slots.map(function (x) { return x.slot_id; }).indexOf(wk.slotId);
        var roll = first ? '' : order > wk.lastOrder ? ' ha-roll-up' : order < wk.lastOrder ? ' ha-roll-down' : '';
        var repaint = wk.paintSlot !== wk.slotId;
        var arrive = wk.arriveKey !== wk.courtId + ':' + wk.blocking + ':' + wk.slotId;
        wk.paintSlot = wk.slotId;
        wk.arriveKey = wk.courtId + ':' + wk.blocking + ':' + wk.slotId;
        var raw = slot ? slotTime(slot) : '';
        var end = wk.blocking && bEnd != null ? clock12(bEnd) : (slot ? (endLabel(slot.label) || endLabel(slot.time)) : null);

        var body = '<header class="ha-wk-head"><div><small>' + (wk.blocking ? 'Block' : 'Walk-in') + '  ·  ' + esc(friendlyDate(desk.date)) + '</small>'
            + '<h2 class="ha-wk-time-h' + roll + '"><b>' + esc(startLabel(raw)) + '</b>' + (end ? '<em>  –  ' + esc(end) + '</em>' : '') + '</h2>'
            + (court ? '<p><svg viewBox="0 0 14 10" aria-hidden="true"><rect x=".7" y=".7" width="12.6" height="8.6" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.4"/><line x1="7" y1="0" x2="7" y2="10" stroke="currentColor" stroke-width="1.4"/></svg>' + esc(court.name) + '</p>' : '')
            + '</div><button type="button" class="ha-wk-x" data-x aria-label="Close">' + ic('clear') + '</button></header>';

        if (A.can('bookings') && courts.length) {
            body += '<div class="ha-dk-mode' + (wk.blocking ? ' is-right' : '') + '"><i></i>'
                + '<button type="button" data-blocking="0" class="' + (!wk.blocking ? 'is-on' : '') + '">' + ic('person') + 'Walk-in</button>'
                + '<button type="button" data-blocking="1" class="' + (wk.blocking ? 'is-on' : '') + '">' + ic('block') + 'Block court</button></div>';
        }
        if (slots.length > 1) {
            body += '<h4 class="ha-wk-label">Time</h4><div class="ha-wk-rail">' + slots.map(function (x) {
                var on = x.slot_id === wk.slotId;
                var c = clocks(slotTime(x))[0] || [slotTime(x), ''];
                var dots = courts.length && x.courts && x.courts.length
                    ? courts.map(function (cc) { return [courtFree(x, cc), cc.id]; })
                    : Array.apply(null, Array(Math.max(0, Math.min(8, x.capacity || 0)))).map(function (_, i) { return [i < (x.available || 0), null]; });
                var nFree = dots.filter(function (d) { return d[0]; }).length;
                return '<button type="button" class="ha-wk-time' + (on ? ' is-on' : '') + '" data-slot="' + x.slot_id + '" aria-label="' + esc(slotTime(x) + ', ' + nFree + ' free') + '">'
                    + '<span><b>' + esc(c[0]) + '</b>' + (c[1] ? '<small>' + c[1] + '</small>' : '') + '</span>'
                    + (!dots.length || dots.length > 8 ? '<em>' + nFree + ' free</em>' : '<i>' + dots.map(function (d) {
                        return '<u class="' + (!d[0] ? 'is-taken' : '') + (on && d[1] != null && d[1] === wk.courtId ? ' is-mine' : '') + '"></u>';
                    }).join('') + '</i>') + '</button>';
            }).join('') + '</div>';
        }
        if (courts.length) {
            body += '<div class="ha-wk-row"><h4 class="ha-wk-label">' + (courts.length > 1 ? 'Court' : 'Your court') + '</h4>'
                + (courts.length > 1 ? '<span>' + (free.length === courts.length ? 'All ' + courts.length + ' free' : free.length + ' of ' + courts.length + ' free') + '</span>' : '') + '</div>'
                + courtMap(slot, courts, distinct.length > 1 ? prices : null, repaint, arrive);
        }
        if (wk.blocking) body += blockPanel(fr, court);
        else body += customerPanel(price);

        s.querySelector('.ha-wk-body').innerHTML = body;
        var label = wk.passId ? 'Use a session' : wk.method === 'upi_qr' ? 'Book & show QR' : wk.method === 'link' ? 'Book & send link' : 'Collect cash';
        var pass = wk.passId ? wk.passes.filter(function (p) { return p.id === wk.passId; })[0] : null;
        var foot = wk.blocking
            ? '<button type="button" class="ha-hold' + (bStart == null ? ' is-off' : '') + '" data-hold>'
                + '<i class="ha-hold-sweep"></i><span>' + ic('block') + '<b data-hold-label>' + (wk.busy ? 'Blocking…' : 'Hold to block ' + esc(court ? court.name : 'court')) + '</b><em></em><strong>' + (bStart != null ? duration(bEnd - bStart) : '') + '</strong></span></button>'
            : '<button type="button" class="ha-wk-cta' + (wk.busy ? ' is-busy' : '') + '" data-submit' + (wk.busy ? ' disabled' : '') + '>'
                + (wk.busy ? '<span class="ha-spin"></span><b>Booking…</b>' : '<b>' + label + '</b><em></em><strong class="' + (wk.amount == null || price === wk.amount ? '' : price < wk.amount ? 'ha-roll-down' : 'ha-roll-up') + '">' + (pass ? (pass.remaining - 1) + ' left' : rupees(price)) + '</strong>') + '</button>';
        s.querySelector('.ha-wk-foot').innerHTML = foot;
        wk.amount = price;
        wk.lastOrder = order;
        if (first) {
            var rail = s.querySelector('.ha-wk-rail');
            var on = rail && rail.querySelector('.is-on');
            if (on) rail.scrollLeft = Math.max(0, on.offsetLeft - 22 - (on.previousElementSibling ? on.previousElementSibling.offsetWidth + 8 : 0));
        }
        railEdges(s);
    }

    /** The rail fades at whichever edge has more behind it. */
    function railEdges(s) {
        var rail = s.querySelector('.ha-wk-rail');
        if (!rail) return;
        function upd() {
            rail.classList.toggle('fade-l', rail.scrollLeft > 4);
            rail.classList.toggle('fade-r', rail.scrollLeft + rail.clientWidth < rail.scrollWidth - 4);
        }
        upd();
        rail.onscroll = upd;
    }

    /**
     * The venue's courts at the picked hour, drawn from above (CourtMap): someone playing
     * is a blue court with two players, mid-payment is amber hatching, free is bare turf.
     * The picked court gets a blue ring and the walk-in's two players drop onto it.
     */
    function courtMap(slot, courts, prices, repaint, arrive) {
        var single = courts.length === 1, scrolls = courts.length > 4;
        return '<div class="ha-wk-map' + (scrolls ? ' is-scroll' : '') + (single ? ' is-single' : '') + (repaint ? ' is-repaint' : '') + (arrive ? ' is-arrive' : '') + '">' + courts.map(function (c, i) {
            var cell = cellOf(slot, c.id);
            var state = !cell ? 'open' : cell.is_booked ? 'booked' : cell.is_held ? 'held' : cell.allowed === false || cell.block ? 'closed' : 'open';
            if (!courtFree(slot, c) && state === 'open') state = 'closed';
            var kind = C.kind(c.sports && c.sports.length ? c.sports : (slot && slot.sports) || []);
            var picked = c.id === wk.courtId && state === 'open';
            var W = single ? 300 : 80, H = single ? 96 : 112;
            var art = C.lane([0, 0, W, H], kind, state, { radius: 8, inset: 5, paint: true });
            if (picked && !wk.blocking) art += C.players([5, 5, W - 10, H - 10], kind, 'ha-arrive', '#2563EB');
            if (picked && wk.blocking) art += shutterSvg(W, H, 'ha-shut');
            if (picked) art += '<rect x="1" y="1" width="' + (W - 2) + '" height="' + (H - 2) + '" rx="8" fill="none" stroke="#2563EB" stroke-width="2.4"/>';
            var price = prices ? prices[c.id] : null;
            var sub = state === 'booked' ? 'Booked' : state === 'held' ? 'Paying now' : state === 'closed' ? (cell && cell.block ? 'Blocked' : 'Not this sport')
                : picked && wk.blocking ? 'Blocking' : price != null ? rupees(price) + (cell && cell.is_peak ? ' · peak' : '') : 'Free';
            return '<button type="button" class="ha-wk-lane is-' + state + (picked ? ' is-picked' : '') + '" data-lane="' + c.id + '" style="--i:' + i + '" aria-label="' + esc(c.name + ', ' + sub) + '">'
                + '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">' + C.defs + art + '</svg>'
                + '<b>' + esc(c.name) + '</b><small class="' + (state === 'held' ? 'is-held' : '') + '">' + esc(sub) + '</small></button>';
        }).join('') + '</div>';
    }

    /** Slate slats rolled down over a court, a darker rail at the foot (shutter()). */
    function shutterSvg(W, H, cls) {
        var slats = '';
        for (var y = 7; y < H - 3; y += 7) slats += '<line x1="0" y1="' + y + '" x2="' + W + '" y2="' + y + '" stroke="#CDD5E0" stroke-width="1.2"/>';
        return '<g class="' + (cls || '') + '"><rect x="0" y="0" width="' + W + '" height="' + H + '" rx="8" fill="#E2E7EE"/>' + slats + '<rect x="0" y="' + (H - 3) + '" width="' + W + '" height="3" fill="#475569"/></g>';
    }

    function customerPanel(price) {
        var nameOk = wk.name.trim().length > 0, phoneOk = wk.phone.length === 10;
        var err = wk.tried && (!nameOk || !phoneOk);
        var msg = !nameOk && !phoneOk ? 'Add their name and mobile number' : !nameOk ? 'Add the customer’s name' : !wk.phone.length ? 'Add a mobile number'
            : (10 - wk.phone.length) + ' more digit' + (10 - wk.phone.length === 1 ? '' : 's');
        var out = '<h4 class="ha-wk-label">Customer</h4>'
            + '<div class="ha-wk-card' + (err ? ' is-err' : '') + '">'
            + '<label class="ha-wk-field' + (wk.tried && !nameOk ? ' is-err' : '') + '" data-shake="name"><span>Name</span><input name="name" type="text" autocomplete="off" autocapitalize="words" enterkeyhint="next" maxlength="60" placeholder="Customer’s full name" value="' + esc(wk.name) + '"></label>'
            + '<label class="ha-wk-field' + (wk.tried && !phoneOk ? ' is-err' : '') + '" data-shake="phone"><span>Mobile</span><em class="' + (wk.phone ? 'is-on' : '') + '">+91</em><input name="phone" type="tel" inputmode="numeric" autocomplete="off" enterkeyhint="done" placeholder="98765 43210" value="' + esc(groupPhone(wk.phone)) + '">'
            + '<svg class="ha-tick' + (phoneOk ? ' is-on' : '') + '" viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="9" fill="rgba(22,163,74,.12)"/><path d="M5 9.4l2.9 2.7L13.2 6.5" fill="none" stroke="#16A34A" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/></svg></label>'
            + '</div>' + (err ? '<p class="ha-wk-err">' + esc(msg) + '</p>' : '');
        if (wk.passes.length) {
            out += wk.passes.map(function (p) {
                var on = wk.passId === p.id, n = Math.max(1, Math.min(40, p.total || 1));
                var used = Math.floor(((p.used || 0) / Math.max(1, p.total || 1)) * n);
                var marks = '';
                for (var i = 0; i < n; i++) marks += '<u class="' + (i < used ? 'is-used' : i === used && on ? 'is-spend' : '') + '"></u>';
                return '<button type="button" class="ha-dk-pass' + (on ? ' is-on' : '') + '" data-pass="' + p.id + '"><span><b>' + (on ? 'Using ' : 'Has a ') + esc(p.package_name || p.packageName || 'pass') + '</b>'
                    + '<small>' + p.remaining + ' of ' + p.total + ' sessions left' + (p.expires_at ? ' · till ' + esc(shortDay(p.expires_at)) : '') + '</small></span>'
                    + '<i class="ha-dk-pass-tick">' + (on ? ic('check') : '') + '</i><span class="ha-dk-pass-strip">' + marks + '</span></button>';
            }).join('');
        }
        if (wk.passId) return out + '<p class="ha-wk-note">One session comes off their pass. No money is taken.</p>';
        var idx = METHODS.map(function (m) { return m[0]; }).indexOf(wk.method);
        return out + '<h4 class="ha-wk-label">Payment</h4><div class="ha-dk-meth" style="--k:' + idx + '"><i></i>' + METHODS.map(function (m) {
            return '<button type="button" data-method="' + m[0] + '" class="' + (wk.method === m[0] ? 'is-on' : '') + '">' + ic(m[2]) + m[1] + '</button>';
        }).join('') + '</div><p class="ha-wk-note">' + (wk.method === 'upi_qr' ? 'A UPI QR for this amount opens next. It confirms itself when they pay.'
            : wk.method === 'link' ? 'Razorpay texts them a payment link. It confirms itself when they pay.'
            : 'You’re taking the cash now. The booking is confirmed straight away.') + '</p>';
    }

    function shortDay(iso) { var d = dateOf(String(iso).slice(0, 10)); return isNaN(d) ? iso : d.getDate() + ' ' + MONTHS[d.getMonth()]; }

    function blockPanel(fr, court) {
        var start = fr.run.length ? A.slotStart(slotTime(fr.run[0])) : null;
        var out = '<h4 class="ha-wk-label">How long</h4>';
        if (start == null) out += '<p class="ha-wk-note">This court isn’t free at this time.</p>';
        else {
            var opts = [1, 2, 3].filter(function (n) { return n < fr.run.length; }).concat([fr.run.length]).filter(function (v, i, a) { return a.indexOf(v) === i; });
            out += '<div class="ha-wk-chips">' + opts.map(function (n) {
                var rest = n === fr.run.length && n > 3;
                return '<button type="button" class="ha-len' + (n === wk.blockSlots ? ' is-on' : '') + '" data-len="' + n + '"><b>' + (rest ? 'Rest of day' : duration(n * fr.len)) + '</b><small>till ' + clock12(start + n * fr.len) + '</small></button>';
            }).join('') + '</div>';
        }
        out += '<h4 class="ha-wk-label">Why</h4><div class="ha-wk-chips">' + KINDS.map(function (k) {
            return '<button type="button" class="ha-why' + (k[0] === wk.blockKind ? ' is-on' : '') + '" data-why="' + k[0] + '">' + ic(k[2]) + k[1] + '</button>';
        }).join('') + '</div>'
            + '<label class="ha-wk-notefield"><input name="note" type="text" maxlength="80" autocapitalize="sentences" enterkeyhint="done" placeholder="Add a note for your team (optional)" value="' + esc(wk.note) + '"></label>';
        if (start != null && court) {
            out += '<p class="ha-wk-note">Players and the desk can’t book ' + esc(court.name) + ' from ' + clock12(start) + ' to ' + clock12(start + wk.blockSlots * fr.len) + '. Tap it on the grid to open it again.</p>';
        }
        return out;
    }

    function onWalkInInput(e) {
        if (!wk) return;
        var t = e.target;
        if (t.name === 'name') wk.name = t.value.slice(0, 60);
        else if (t.name === 'note') wk.note = t.value.slice(0, 80);
        else if (t.name === 'phone') {
            var digits = t.value.replace(/\D/g, '');
            // A pasted "+91 98765 43210" keeps its last ten; typing stops at ten.
            var next = digits.length > 10 && digits.length - wk.phone.length > 1 ? digits.slice(-10) : digits.slice(0, 10);
            var was = wk.phone;
            wk.phone = next;
            var shown = groupPhone(next);
            if (t.value !== shown) {
                var caret = t.selectionStart, before = t.value.slice(0, caret).replace(/\D/g, '').length;
                t.value = shown;
                var pos = before > 5 ? before + 1 : before;
                try { t.setSelectionRange(pos, pos); } catch (err) { /* none */ }
            }
            var tick = wk.sheet.querySelector('.ha-tick');
            if (tick) tick.classList.toggle('is-on', next.length === 10);
            var plus = wk.sheet.querySelector('.ha-wk-field em');
            if (plus) plus.classList.toggle('is-on', !!next);
            // The tenth digit landing is the one moment the field is "done".
            if (next.length === 10 && was.length !== 10) { haptic('confirm'); lookupPasses(next); }
            if (next.length !== 10 && was.length === 10) { wk.passes = []; wk.passId = null; renderKeepFocus(); }
        }
        if (wk.tried) {
            var card = wk.sheet.querySelector('.ha-wk-card');
            var ok = wk.name.trim() && wk.phone.length === 10;
            if (ok && card && card.classList.contains('is-err')) renderKeepFocus();
        }
    }

    /** Redraw the sheet without losing the field being typed in. */
    function renderKeepFocus() {
        var a = document.activeElement, nm = a && a.name, pos = a && a.selectionStart;
        renderWalkIn();
        if (nm) {
            var el = wk && wk.sheet.querySelector('input[name="' + nm + '"]');
            if (el) { el.focus({ preventScroll: true }); try { el.setSelectionRange(pos, pos); } catch (e) { /* none */ } }
        }
    }

    function lookupPasses(phone) {
        var v = desk.venue;
        if (!v || !wk) return;
        A.api('packages/holder', { branch: false, phone: phone, venue_id: v.id, fresh: true }).then(function (r) {
            if (!wk || wk.phone !== phone) return;
            var list = (r && r.data ? r.data : r || []).filter(function (p) { return p.usable !== false; });
            wk.passes = list;
            if (list.length) renderKeepFocus();
        }, function () { /* the desk still takes payment the normal way */ });
    }

    function shake(el) {
        if (!el) return;
        el.classList.remove('is-shake'); void el.offsetWidth; el.classList.add('is-shake');
    }

    function onWalkInClick(e) {
        if (!wk) return;
        var t = e.target, b;
        if ((b = t.closest('[data-blocking]'))) {
            var on = b.dataset.blocking === '1';
            if (on !== wk.blocking) { haptic('tick'); wk.blocking = on; if (on && document.activeElement) document.activeElement.blur(); renderWalkIn(); }
        } else if ((b = t.closest('[data-slot]'))) {
            var id = +b.dataset.slot;
            if (id !== wk.slotId) { haptic('tick'); wk.slotId = id; renderWalkIn(); }
        } else if ((b = t.closest('[data-lane]'))) {
            var cid = +b.dataset.lane;
            if (!b.classList.contains('is-open')) { haptic('reject'); shake(b); return; }
            if (cid !== wk.courtId) { haptic('tick'); wk.courtId = cid; renderWalkIn(); }
        } else if ((b = t.closest('[data-method]'))) {
            if (b.dataset.method !== wk.method) { haptic('tick'); wk.method = b.dataset.method; renderKeepFocus(); }
        } else if ((b = t.closest('[data-pass]'))) {
            var pid = +b.dataset.pass;
            if (wk.passId === pid) { haptic('tick'); wk.passId = null; }
            else {
                haptic('confirm'); wk.passId = pid;
                var p = wk.passes.filter(function (x) { return x.id === pid; })[0];
                if (p && !wk.name.trim() && p.name && p.name !== 'Guest') wk.name = p.name;
            }
            renderWalkIn();
        } else if ((b = t.closest('[data-len]'))) {
            var n = +b.dataset.len;
            if (n !== wk.blockSlots) { haptic('tick'); wk.blockSlots = n; renderWalkIn(); }
        } else if ((b = t.closest('[data-why]'))) {
            if (b.dataset.why !== wk.blockKind) { haptic('tick'); wk.blockKind = b.dataset.why; renderWalkIn(); }
        } else if (t.closest('[data-submit]')) {
            submitWalkIn();
        }
    }

    function submitWalkIn() {
        if (!wk || wk.busy) return;
        var nameOk = wk.name.trim().length > 0, phoneOk = wk.phone.length === 10;
        wk.tried = true;
        if (!nameOk || !phoneOk) {
            haptic('reject');
            renderWalkIn();
            var s = wk.sheet;
            if (!nameOk) shake(s.querySelector('[data-shake=name]'));
            if (!phoneOk) shake(s.querySelector('[data-shake=phone]'));
            var f = s.querySelector('input[name=' + (!nameOk ? 'name' : 'phone') + ']');
            if (f) f.focus();
            return;
        }
        haptic('tick');
        if (document.activeElement) document.activeElement.blur();
        var v = desk.venue, date = desk.date, method = wk.passId ? 'package' : wk.method;
        var body = { slotId: wk.slotId, date: date, guestName: wk.name.trim(), guestPhone: wk.phone, paymentMethod: method, nativeCheckout: true };
        if (wk.courtId != null) body.courtId = wk.courtId;
        if (wk.passId) body.customerPackageId = wk.passId;
        var name = wk.name.trim(), price = wk.amount;
        wk.busy = true;
        renderWalkIn();
        A.apiSend('POST', 'venues/' + v.id + '/bookings', body).then(function (r) {
            var b = (r && r.booking) || {};
            if ((method === 'upi_qr' || method === 'link') && b.id) {
                if (wk) wk.sheet.close();
                startPay(b.id, name, b.amount || price, r.payment);
                load(true);
                return;
            }
            haptic(method === 'package' ? 'confirm' : 'money');
            if (wk) wk.sheet.close();
            A.toast(method === 'package' ? '1 session used from ' + name + '’s pass' : 'Walk-in booked for ' + name);
            load(true);
        }, function (err) {
            haptic('reject');
            if (wk) { wk.busy = false; renderWalkIn(); }
            A.toast(err.message || 'Couldn’t record the walk-in');
        });
    }

    /**
     * Press and hold to block (HoldToBlockButton): a deeper blue sweeps under the finger
     * with a tick at each quarter, and it commits when the sweep lands. A quick tap shakes
     * and says to press and hold. Taking a court off sale costs half a second of intent.
     */
    function wireHold(s) {
        var raf = null, t0 = 0, from = 0, quarter = 0, holding = false;
        function btn() { return s.querySelector('[data-hold]'); }
        function setP(p) { var b = btn(); if (b) b.style.setProperty('--p', p.toFixed(3)); }
        function label(text) { var l = s.querySelector('[data-hold-label]'); if (l) l.textContent = text; }
        function restLabel() {
            var g = desk.grid, court = (g.courts || []).filter(function (c) { return wk && c.id === wk.courtId; })[0];
            return 'Hold to block ' + (court ? court.name : 'court');
        }
        s.addEventListener('pointerdown', function (e) {
            var b = e.target.closest('[data-hold]');
            if (!b || !wk || wk.busy || b.classList.contains('is-off')) return;
            e.preventDefault();
            try { b.setPointerCapture(e.pointerId); } catch (err) { /* none */ }
            holding = true; quarter = 0; t0 = performance.now(); from = 0;
            b.classList.add('is-holding');
            haptic('tick');
            label('Keep holding…');
            cancelAnimationFrame(raf);
            (function step(now) {
                var p = Math.min(1, from + (now - t0) / 650);
                setP(p);
                var q = Math.floor(p * 4);
                if (q >= 1 && q <= 3 && q > quarter) { quarter = q; haptic('tick'); }
                if (p >= 1) { holding = false; b.classList.remove('is-holding'); haptic('confirm'); commitBlock(); setP(0); return; }
                if (holding) raf = requestAnimationFrame(step);
            })(t0);
        });
        function release(e) {
            var b = btn();
            if (!holding || !b) return;
            holding = false;
            cancelAnimationFrame(raf);
            b.classList.remove('is-holding');
            var p = parseFloat(b.style.getPropertyValue('--p')) || 0;
            b.classList.add('is-spring'); setP(0);
            setTimeout(function () { var x = btn(); if (x) x.classList.remove('is-spring'); }, 320);
            if (p < 0.6) {
                haptic('reject');
                shake(b);
                label('Press and hold to block');
                setTimeout(function () { if (wk && !holding && !wk.busy) label(restLabel()); }, 2200);
            } else label(restLabel());
        }
        s.addEventListener('pointerup', release);
        s.addEventListener('pointercancel', release);
        s.addEventListener('contextmenu', function (e) { if (e.target.closest('[data-hold]')) e.preventDefault(); });
    }

    function commitBlock() {
        if (!wk || wk.busy) return;
        var fr = freeRun();
        if (!fr.run.length || wk.courtId == null) return;
        var start = A.slotStart(slotTime(fr.run[0]));
        var end = Math.min(24 * 60, start + Math.min(wk.blockSlots, fr.run.length) * fr.len);
        var court = (desk.grid.courts || []).filter(function (c) { return c.id === wk.courtId; })[0];
        var summary = (court ? court.name : 'Court') + ' blocked · ' + clock12(start) + ' – ' + clock12(end);
        wk.busy = true;
        var b = wk.sheet.querySelector('[data-hold-label]');
        if (b) b.textContent = 'Blocking…';
        A.apiSend('POST', 'venues/' + desk.venue.id + '/court-blocks', {
            court_id: wk.courtId, date: desk.date, start: hm(start), end: end >= 1440 ? '24:00' : hm(end), kind: wk.blockKind, note: wk.note.trim() || null,
        }).then(function () {
            if (wk) wk.sheet.close();
            A.toast(summary);
            load(true);
        }, function (err) {
            haptic('reject');
            if (wk) { wk.busy = false; renderWalkIn(); }
            A.toast(err.message || 'Couldn’t block the court');
        });
    }

    /* ======================================================== BLOCK DETAILS SHEET === */

    function openBlock(block, courtName) {
        var canUnblock = A.can('bookings') && block.removable;
        var w = blockWindow(block);
        var s = A.sheet('<div class="ha-blk"><div class="ha-blk-top"><svg viewBox="0 0 52 64" aria-hidden="true">'
            + '<rect width="52" height="64" rx="8" fill="#E3F4E8"/>' + shutterSvg(52, 64) + '</svg>'
            + '<div><small>Blocked  ·  ' + esc(courtName) + '</small><h3>' + esc(block.label || 'Blocked') + '</h3>' + (w ? '<p>' + esc(w) + '</p>' : '') + '</div></div>'
            + (block.note && block.note !== block.label ? '<p class="ha-blk-note">' + esc(block.note) + '</p>' : '')
            + (block.reason && block.reason !== block.label ? '<p class="ha-blk-why">' + esc(block.reason) + '</p>' : '')
            + (canUnblock ? '<button type="button" class="ha-wk-cta is-center" data-unblock><b>Open ' + esc(courtName) + ' again</b></button>'
                : '<p class="ha-blk-why">Haraan set this one up (it repeats or covers the whole venue). Message Haraan support to change it.</p>')
            + '</div>');
        s.addEventListener('click', function (e) {
            var b = e.target.closest('[data-unblock]');
            if (!b || b.disabled) return;
            haptic('confirm');
            b.disabled = true; b.innerHTML = '<span class="ha-spin"></span>';
            A.apiSend('DELETE', 'venues/' + desk.venue.id + '/court-blocks/' + block.id).then(function () {
                s.close(); A.toast(courtName + ' is open again'); load(true);
            }, function (err) {
                haptic('reject'); b.disabled = false; b.innerHTML = '<b>Open ' + esc(courtName) + ' again</b>';
                A.toast(err.message || 'Couldn’t unblock the court');
            });
        });
    }

    /* ====================================================== BOOKING DETAILS SHEET === */

    function openBooking(item, extra) {
        var b = item;
        var digits = String(b.phone || '').replace(/\D/g, '');
        var local = digits.length > 10 ? digits.slice(-10) : digits;
        var where = [extra && extra.time, friendlyDate(desk.date), extra && extra.court].filter(Boolean).join(' · ');
        var s = A.sheet('<div class="ha-bk">'
            + '<div class="ha-bk-head"><div><span class="ha-bk-code"><b>' + esc(b.ticket) + '</b><button type="button" data-copy aria-label="Copy ticket code">' + ic('copy') + '</button></span><small>' + esc(where) + '</small></div>'
            + '<button type="button" class="ha-wk-x" data-x aria-label="Close">' + ic('clear') + '</button></div>'
            + '<div class="ha-bk-who"><span class="ha-dk-av' + (b.walkIn ? ' is-walk' : '') + '">' + esc(b.customer.trim().charAt(0).toUpperCase() || '•') + '</span>'
            + '<div><b>' + esc(b.customer) + '</b><small>' + (b.walkIn ? 'Walk-in desk customer' : 'Booked on Haraan') + '</small></div>'
            + (local.length === 10 ? '<a class="ha-bk-ic" href="tel:+91' + local + '" aria-label="Call">' + ic('phone') + '</a><a class="ha-bk-ic is-wa" href="https://wa.me/91' + local + '" target="_blank" rel="noopener" aria-label="WhatsApp">' + ic('chat') + '</a>' : '')
            + '</div>'
            + '<div class="ha-bk-ledger"><p><span>Booking price</span><b>' + rupees(b.amount) + '</b></p><p><span>Paid</span><b class="is-in">' + rupees(b.paid) + '</b></p>'
            + (b.balance > 0 && !b.cancelled ? '<p class="is-due"><span>Still to collect</span><b>' + rupees(b.balance) + '</b></p>' : '')
            + '<p class="is-small"><span>Paid by</span><em>' + esc(String(b.method || (b.walkIn ? 'Desk' : 'Online')).replace('_', ' ').toUpperCase()) + '</em></p></div>'
            + (b.cancelled ? '<div class="ha-bk-off">This booking was cancelled</div>'
                : (!b.checkedIn ? '<button type="button" class="ha-wk-cta is-center" data-checkin><b>' + ic('check') + 'Check in</b></button>' : '<div class="ha-bk-in">' + ic('check') + 'Checked in</div>')
                + '<button type="button" class="ha-bk-cancel" data-cancel>Cancel booking</button>')
            + '</div>');
        s.addEventListener('click', function (e) {
            var t = e.target, x;
            if (t.closest('[data-copy]')) {
                haptic('tick');
                try { navigator.clipboard.writeText(b.ticket).then(function () { A.toast('Ticket code copied'); }); } catch (err) { /* none */ }
            } else if ((x = t.closest('[data-checkin]'))) {
                x.disabled = true;
                checkIn(b.ticket).then(function (ok) { if (ok) s.close(); else x.disabled = false; });
            } else if ((x = t.closest('[data-cancel]'))) {
                if (!x.classList.contains('is-armed')) {
                    // A cancel frees the court for good: one tap arms it, the second does it.
                    haptic('tick');
                    x.classList.add('is-armed');
                    x.textContent = 'Tap again to cancel ' + b.ticket;
                    setTimeout(function () { if (x.isConnected && !x.disabled) { x.classList.remove('is-armed'); x.textContent = 'Cancel booking'; } }, 3500);
                    return;
                }
                x.disabled = true; x.textContent = 'Cancelling…';
                A.apiSend('POST', 'bookings/' + b.id + '/cancel', {}).then(function () {
                    haptic('confirm'); s.close(); A.toast('Booking ' + b.ticket + ' cancelled'); load(true);
                }, function (err) {
                    haptic('reject'); x.disabled = false; x.classList.remove('is-armed'); x.textContent = 'Cancel booking';
                    A.toast(err.message || 'Couldn’t cancel the booking');
                });
            }
        });
    }

    function checkIn(code) {
        return A.apiSend('POST', 'check-in', { code: code }).then(function (r) {
            var st = String((r && r.status) || 'ok');
            if (st === 'already' || st === 'already_checked_in') { haptic('tick'); A.toast('Already checked in'); }
            else { haptic('confirm'); A.toast('Checked in'); }
            load(true);
            return true;
        }, function (err) { haptic('reject'); A.toast(err.message || 'Couldn’t check in'); return false; });
    }

    /* ========================================================= DESK PAYMENT SHEET === */

    var pay = null;

    function usable(p) { return !!p && !p.error && p.id != null && !!(p.qr || p.image_url || p.url || p.checkout); }
    function isQr(p) { return p && p.kind === 'upi_qr'; }
    function isPage(p) { return p && p.present === 'page' && p.url; }
    function isCheckout(p) { return p && p.kind === 'order' && p.checkout; }
    function idField(p) { return p.kind === 'upi_qr' ? 'qrId' : p.kind === 'order' ? 'orderId' : 'linkId'; }

    /** A walk-in paying online: the QR (or page/link) on the desk's screen, a countdown, a live check. */
    function startPay(bookingId, customer, amount, payment) {
        var ok = usable(payment);
        var total = Math.max(30, (payment && payment.expires_in) || 300);
        if (pay) { clearInterval(pay.timer); }
        var fresh = !pay || !pay.sheet;
        pay = pay && pay.sheet ? pay : {};
        pay.bookingId = bookingId; pay.customer = customer;
        pay.amount = payment && payment.amount > 0 ? payment.amount : amount;
        pay.payment = payment; pay.left = total; pay.total = total; pay.busy = false; pay.checking = false;
        pay.phase = ok ? 'waiting' : 'failed'; pay.via = null; pay.armCash = false; pay.armCancel = false;
        pay.message = ok ? null : (payment && payment.error) || 'Couldn’t start an online payment. Take cash or try again.';
        if (fresh) {
            var s = A.sheet('<div class="ha-dp"></div>', 'ha-dpsheet');
            pay.sheet = s;
            // Not paid yet, the scrim doesn't close it: leaving means cancelling, and that's asked.
            s.querySelector('.ha-scrim').removeAttribute('data-x');
            s.addEventListener('click', onPayClick);
        }
        renderPay();
        if (ok) {
            watchPay();
            if (isCheckout(payment)) openCheckout();
        } else haptic('reject');
    }

    function mmss(n) { n = Math.max(0, n); return Math.floor(n / 60) + ':' + (n % 60 < 10 ? '0' : '') + (n % 60); }

    function renderPay() {
        if (!pay || !pay.sheet) return;
        var p = pay.payment, link = p && !isQr(p) && !isCheckout(p);
        var title = pay.phase === 'paid' ? 'Payment received' : pay.phase === 'expired' ? (link ? 'Link not paid yet' : 'QR expired')
            : pay.phase === 'failed' ? (link ? 'Link unavailable' : 'UPI QR unavailable')
            : isPage(p) || isCheckout(p) ? 'Pay on Razorpay' : link ? 'Payment link' : 'Scan to pay';
        var out = '<header class="ha-dp-head"><div><h3>' + title + '</h3><small>' + esc(pay.customer) + ' · Booking #' + pay.bookingId + '</small></div>'
            + '<button type="button" class="ha-wk-x" data-leave aria-label="Close"' + (pay.busy ? ' disabled' : '') + '>' + ic('clear') + '</button></header>';
        if (pay.phase === 'paid') {
            out += '<div class="ha-dp-done"><span class="ha-dp-check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.6 4.4L19 7.5" fill="none" stroke="#16A34A" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/></svg></span>'
                + '<b>' + rupees(pay.amount) + '</b><p>' + (pay.via === 'cash' ? 'Taken in cash. The booking is confirmed.' : pay.via === 'upi' || pay.via === 'card' ? 'Recorded at the desk. The booking is confirmed.'
                    : 'Paid online. The booking is confirmed and the ticket is on its way to ' + esc(pay.customer) + '.') + '</p>'
                + '<button type="button" class="ha-wk-cta is-center" data-done><b>Done</b></button></div>';
            pay.sheet.querySelector('.ha-dp').innerHTML = out;
            return;
        }
        out += '<div class="ha-dp-amt">' + rupees(pay.amount) + '</div>';
        if (pay.phase !== 'failed') {
            if (isPage(p) || isCheckout(p)) {
                out += '<div class="ha-dp-panel">' + ic('pay') + '<b>Customer pays on Razorpay</b><p>' + (isCheckout(p) ? 'Razorpay opens right here with ' + esc(pay.customer) + '’s number already in. This turns green by itself when the money lands.'
                    : 'Razorpay’s page opens for the customer to pay. This turns green by itself when the money lands.') + '</p>'
                    + (pay.phase === 'waiting' ? '<button type="button" class="ha-wk-cta is-center is-small" data-open-page><b>' + (isCheckout(p) ? 'Open payment' : 'Open payment page') + '</b></button>' : '') + '</div>';
            } else if (p && !isQr(p) && !p.qr) {
                out += '<div class="ha-dp-panel">' + ic('link') + '<b>Link texted to ' + esc(pay.customer) + '</b><a href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc(p.url) + '</a><p>It confirms by itself when they pay.</p></div>';
            } else {
                out += '<div class="ha-dp-qr' + (pay.phase === 'expired' ? ' is-expired' : '') + '"><div data-qr></div>' + (pay.phase === 'expired' ? '<b>' + (isQr(p) ? 'Expired' : 'Timer ended') + '</b>' : '') + '</div>'
                    + '<p class="ha-dp-hint">' + (isQr(p) ? 'Any UPI app can scan this. The amount is already filled in.' : 'Scan with the phone camera to open the payment page. Razorpay has also texted the link.') + '</p>';
            }
            var frac = pay.total > 0 ? pay.left / pay.total : 0;
            var tone = pay.phase === 'expired' ? 'is-muted' : pay.left <= 15 ? 'is-red' : pay.left <= 60 ? 'is-amber' : '';
            out += '<div class="ha-dp-count ' + tone + '"><span class="ha-dp-bar"><i style="--w:' + (frac * 100).toFixed(1) + '%"></i></span><p>'
                + (pay.phase === 'waiting' ? '<i class="' + (pay.checking ? 'is-on' : '') + '"></i>Waiting for payment' : '') + '<b data-left>' + (pay.phase === 'expired' ? '0:00' : mmss(pay.left)) + '</b></p></div>';
        }
        if (pay.message) out += '<p class="ha-dp-msg">' + esc(pay.message) + '</p>';
        // Actions (Actions()).
        out += '<div class="ha-dp-acts">';
        if (pay.phase !== 'waiting') {
            out += '<button type="button" class="ha-wk-cta is-center is-small" data-retry="' + (!p || isPage(p) || isCheckout(p) ? 'upi_qr' : p.kind) + '"' + (pay.busy ? ' disabled' : '') + '>'
                + (pay.busy ? '<span class="ha-spin"></span>' : '<b>' + ic('refresh') + (isPage(p) || isCheckout(p) ? 'Start again' : p && !isQr(p) ? 'Send a new link' : 'Show a new QR') + '</b>') + '</button>';
            if (pay.phase === 'failed' && (!p || isQr(p) || isPage(p) || isCheckout(p))) out += '<button type="button" class="ha-dp-ghost" data-retry="link"' + (pay.busy ? ' disabled' : '') + '>Send a payment link instead</button>';
        }
        out += '<div class="ha-dp-row"><button type="button" class="ha-dp-ghost' + (pay.armCash ? ' is-armed' : '') + '" data-cash' + (pay.busy ? ' disabled' : '') + '>' + (pay.armCash ? 'Confirm ' + rupees(pay.amount) + ' cash' : 'Paid in cash') + '</button>'
            + (p && p.url && p.present === 'share' ? '<button type="button" class="ha-dp-ghost" data-share>' + ic('share') + 'Share link</button>' : '') + '</div>'
            + '<button type="button" class="ha-dp-leave' + (pay.armCancel ? ' is-armed' : '') + '" data-cancelpay' + (pay.busy ? ' disabled' : '') + '>' + (pay.armCancel ? 'Tap again to cancel the booking' : 'Customer left — cancel booking') + '</button></div>';
        pay.sheet.querySelector('.ha-dp').innerHTML = out;
        drawQr();
    }

    function drawQr() {
        var box = pay && pay.sheet.querySelector('[data-qr]');
        var p = pay && pay.payment;
        if (!box || !p) return;
        if (p.qr) {
            var paint = function () {
                if (!window.qrcode || !box.isConnected) return;
                var q = window.qrcode(0, 'M');
                q.addData(p.qr); q.make();
                box.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
            };
            if (window.qrcode) paint();
            else A.loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js').then(paint, function () {
                if (p.image_url) box.innerHTML = '<img src="' + esc(p.image_url) + '" alt="Payment QR code">';
            });
        } else if (p.image_url) box.innerHTML = '<img src="' + esc(p.image_url) + '" alt="Payment QR code">';
    }

    /** One-second countdown; asks Razorpay every third second; closes the QR at zero. */
    function watchPay() {
        clearInterval(pay.timer);
        var tick = 0;
        pay.timer = setInterval(function () {
            if (!pay || pay.phase !== 'waiting') { if (pay) clearInterval(pay.timer); return; }
            pay.left = Math.max(0, pay.left - 1);
            tick++;
            var el = pay.sheet.querySelector('[data-left]');
            if (el) el.textContent = mmss(pay.left);
            var bar = pay.sheet.querySelector('.ha-dp-bar i');
            if (bar) bar.style.setProperty('--w', (pay.left / pay.total * 100).toFixed(1) + '%');
            var cnt = pay.sheet.querySelector('.ha-dp-count');
            if (cnt) { cnt.classList.toggle('is-amber', pay.left <= 60 && pay.left > 15); cnt.classList.toggle('is-red', pay.left <= 15); }
            var expired = pay.left === 0;
            if (tick % 3 !== 0 && !expired) return;
            var p = pay.payment, id = pay.bookingId;
            var body = {}; body[idField(p)] = p.id; body.close = expired && (isQr(p) || !!isPage(p) || !!isCheckout(p));
            pay.checking = true;
            A.apiSend('POST', 'bookings/' + id + '/payment-status', body).then(function (r) {
                if (!pay || pay.bookingId !== id) return;
                pay.checking = false;
                if (r && r.paid) { paid('online'); return; }
                if (expired) { clearInterval(pay.timer); pay.phase = 'expired'; haptic('reject'); renderPay(); }
            }, function () {
                if (!pay || pay.bookingId !== id) return;
                pay.checking = false;
                if (expired) { clearInterval(pay.timer); pay.phase = 'expired'; renderPay(); }
            });
        }, 1000);
    }

    function paid(via) {
        clearInterval(pay.timer);
        pay.phase = 'paid'; pay.via = via; pay.busy = false;
        haptic('money');
        renderPay();
        load(true);
    }

    /** Razorpay's checkout over the desk, the customer's number already in (DeskCheckoutPage). */
    function openCheckout() {
        var p = pay && pay.payment;
        if (!isCheckout(p)) return;
        var c = p.checkout, id = pay.bookingId;
        A.loadScript('https://checkout.razorpay.com/v1/checkout.js').then(function () {
            if (!pay || pay.bookingId !== id || pay.phase !== 'waiting') return;
            var rz = new window.Razorpay({
                key: c.key, order_id: c.order_id, name: c.name || 'Haraan', description: c.description || '',
                prefill: { contact: c.contact || '', name: c.customer || '' },
                theme: { color: '#2563EB' },
                handler: function () {
                    // "Paid" is the customer's phone talking: ask the server, which asks Razorpay.
                    if (!pay || pay.bookingId !== id) return;
                    pay.message = null; renderPay();
                    var tries = 0;
                    (function ask() {
                        var body = {}; body.orderId = p.id;
                        A.apiSend('POST', 'bookings/' + id + '/payment-status', body).then(function (r) {
                            if (!pay || pay.bookingId !== id) return;
                            if (r && r.paid) { paid('online'); return; }
                            if (++tries < 5) setTimeout(ask, 1500 * tries);
                            else { pay.message = 'Paid on Razorpay. Waiting for it to confirm…'; renderPay(); }
                        }, function () { if (++tries < 5) setTimeout(ask, 1500 * tries); });
                    })();
                },
                modal: { ondismiss: function () { if (pay && pay.bookingId === id && pay.phase === 'waiting') { pay.message = 'Payment closed. Open it again, or take cash.'; renderPay(); } } },
            });
            rz.on('payment.failed', function (r) { if (pay && pay.bookingId === id) { pay.message = (r && r.error && r.error.description) || 'The payment didn’t go through.'; renderPay(); } });
            rz.open();
        }, function () { if (pay) { pay.message = 'Couldn’t open Razorpay. Take cash or try again.'; renderPay(); } });
    }

    function onPayClick(e) {
        if (!pay) return;
        var t = e.target, b;
        if (t.closest('[data-done]')) { closePay(); return; }
        if (t.closest('[data-leave]')) {
            if (pay.phase === 'paid') { closePay(); return; }
            if (pay.busy) return;
            leaveDialog();
            return;
        }
        if (t.closest('[data-open-page]')) {
            haptic('tick');
            if (isCheckout(pay.payment)) openCheckout();
            else window.open(pay.payment.url, '_blank', 'noopener');
            return;
        }
        if ((b = t.closest('[data-retry]'))) { retryPay(b.dataset.retry); return; }
        if (t.closest('[data-cash]')) {
            if (!pay.armCash) { haptic('tick'); pay.armCash = true; renderPay(); return; }
            collect('cash');
            return;
        }
        if (t.closest('[data-share]')) {
            var text = 'Pay ' + rupees(pay.amount) + ' for your booking: ' + pay.payment.url;
            if (navigator.share) navigator.share({ text: text }).catch(function () {});
            else { try { navigator.clipboard.writeText(text); A.toast('Link copied'); } catch (err) { /* none */ } }
            return;
        }
        if (t.closest('[data-cancelpay]')) {
            if (!pay.armCancel) { haptic('tick'); pay.armCancel = true; renderPay(); return; }
            cancelPay();
        }
    }

    function leaveDialog() {
        var d = h('<div class="ha-dialog ha-dp-dialog"><div class="ha-scrim" data-no></div><div class="ha-dialog-in" role="alertdialog"><h3>Cancel this booking?</h3>'
            + '<p>' + esc(pay.customer) + ' hasn’t paid yet. The booking is cancelled and the court goes back on sale.</p>'
            + '<div><button type="button" data-no>Keep waiting</button><button type="button" class="is-red" data-yes>Cancel booking</button></div></div></div>');
        document.body.appendChild(d);
        requestAnimationFrame(function () { d.classList.add('is-open'); });
        d.addEventListener('click', function (e) {
            if (e.target.closest('[data-yes]')) { d.remove(); cancelPay(); }
            else if (e.target.closest('[data-no]')) d.remove();
        });
    }

    function retryPay(kind) {
        var id = pay.bookingId, p = pay.payment;
        pay.busy = true; pay.message = null; renderPay();
        var body = { kind: kind, nativeCheckout: true };
        if (p && p.id != null) { body.replaceKind = p.kind; body.replaceId = p.id; }
        A.apiSend('POST', 'bookings/' + id + '/payment-request', body).then(function (r) {
            if (!pay || pay.bookingId !== id) return;
            if (r && r.paid) { paid('online'); return; }
            startPay(id, pay.customer, pay.amount, r && r.payment);
        }, function (err) {
            if (!pay) return;
            pay.busy = false; pay.message = err.message || 'Couldn’t make a new QR'; renderPay();
        });
    }

    /** The customer paid at the counter after all. The server closes the QR first. */
    function collect(method) {
        var id = pay.bookingId, p = pay.payment, wasWaiting = pay.phase === 'waiting';
        clearInterval(pay.timer);
        pay.busy = true; pay.message = null; renderPay();
        var body = { method: method };
        if (p && p.id != null) { body.closeKind = p.kind; body.closeId = p.id; }
        A.apiSend('POST', 'bookings/' + id + '/collect', body).then(function (r) {
            if (!pay || pay.bookingId !== id) return;
            paid((r && r.via) || method);
        }, function (err) {
            if (!pay) return;
            pay.busy = false; pay.armCash = false; pay.message = err.message || 'Couldn’t record the payment'; renderPay();
            if (wasWaiting) watchPay();
        });
    }

    /** Drop the booking — the customer walked away. Closes the QR so it can't be paid. */
    function cancelPay() {
        var id = pay.bookingId, p = pay.payment, who = pay.customer;
        clearInterval(pay.timer);
        pay.busy = true; pay.message = null; renderPay();
        var check = p && p.id != null ? (function () { var b = {}; b[idField(p)] = p.id; b.close = true; return A.apiSend('POST', 'bookings/' + id + '/payment-status', b).then(function (r) { return !!(r && r.paid); }, function () { return false; }); })() : Promise.resolve(false);
        check.then(function (paidMeanwhile) {
            // A scan that just landed is not cancelled.
            if (paidMeanwhile) { paid('online'); return; }
            return A.apiSend('POST', 'bookings/' + id + '/cancel', {}).then(function () {
                haptic('confirm');
                var s = pay.sheet; pay = null; s.close();
                A.toast('Booking for ' + who + ' cancelled');
                load(true);
            }, function (err) {
                if (!pay) return;
                pay.busy = false; pay.message = err.message || 'Couldn’t cancel the booking'; renderPay();
            });
        });
    }

    function closePay() {
        if (!pay) return;
        clearInterval(pay.timer);
        var s = pay.sheet, msg = pay.phase === 'paid' ? rupees(pay.amount) + ' received from ' + pay.customer : null;
        pay = null;
        s.close();
        if (msg) A.toast(msg);
    }

    /* =========================================================== from Home's board === */

    /** Home's board tapped an open court-hour: open the walk-in sheet right on it. */
    function openFromHome() {
        var want = null;
        try { want = JSON.parse(sessionStorage.getItem('haDeskOpen') || 'null'); } catch (e) { /* none */ }
        if (!want) return;
        if (want.date && want.date !== desk.date) { sessionStorage.removeItem('haDeskOpen'); return; }
        sessionStorage.removeItem('haDeskOpen');
        var slot = slotsOf(desk.grid).filter(function (s) { return slotTime(s).trim() === String(want.time || '').trim() || A.slotStart(slotTime(s)) === A.slotStart(want.time); })[0];
        if (!slot || !A.can('bookings') || desk.grid.is_blocked) return;
        var court = (desk.grid.courts || []).filter(function (c) { return c.name === want.court; })[0];
        openWalkIn(slot, court ? court.id : null);
    }

    /* ================================================================== screen === */

    function onClick(e) {
        var t = e.target, b;
        if (t.closest('[data-retry]')) { load(false); return; }
        if ((b = t.closest('[data-day]'))) { selectDate(addDays(desk.date, +b.dataset.day)); return; }
        if ((b = t.closest('[data-date]'))) { selectDate(b.dataset.date); return; }
        if ((b = t.closest('[data-mode]'))) {
            haptic('tick');
            desk.mode = b.dataset.mode;
            if (desk.mode === 'grid') { desk.filter = 'all'; desk.query = ''; var q = desk.host.querySelector('.ha-dk-search input'); if (q) q.value = ''; }
            renderBody();
            return;
        }
        if ((b = t.closest('[data-filter]'))) {
            if (desk.filter === b.dataset.filter) return;
            haptic('tick');
            desk.filter = b.dataset.filter;
            desk.mode = desk.filter === 'all' && !desk.query ? desk.mode : 'list';
            renderBody();
            return;
        }
        if (t.closest('[data-due]')) { haptic('tick'); desk.mode = 'list'; desk.filter = 'unpaid'; renderBody(); window.scrollTo({ top: (desk.host.querySelector('.ha-dk-chips') || desk.host).getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' }); return; }
        if (t.closest('[data-clear]')) {
            desk.query = '';
            var inp = desk.host.querySelector('.ha-dk-search input');
            if (inp) { inp.value = ''; inp.focus(); }
            renderBody();
            return;
        }
        if (t.closest('[data-reopen]')) {
            haptic('tick');
            A.apiSend('DELETE', 'venues/' + desk.venue.id + '/block?date=' + desk.date).then(function () { haptic('confirm'); A.toast('Venue open again on this day'); load(true); },
                function (err) { haptic('reject'); A.toast(err.message || 'Couldn’t reopen the day'); });
            return;
        }
        if (t.closest('[data-walkin]')) { haptic('tick'); openWalkIn(null, null); return; }
        if ((b = t.closest('[data-checkin]')) && b.closest('.ha-dk-item')) { b.disabled = true; checkIn(b.dataset.checkin).then(function () { b.disabled = false; }); return; }
        if ((b = t.closest('.ha-dk-item')) && !t.closest('a,button')) {
            var it = (desk.list || []).filter(function (x) { return x.id === +b.dataset.item; })[0];
            if (it) { haptic('tick'); openBooking(it); }
            return;
        }
        if ((b = t.closest('.ha-dk-cell'))) {
            var slots = slotsOf(desk.grid), courts = desk.grid.courts || [];
            var slot = slots[+b.dataset.slot], court = courts[+b.dataset.court];
            if (b.hasAttribute('data-booking')) {
                haptic('tick');
                var bid = +b.dataset.booking;
                var item = (desk.list || []).filter(function (x) { return x.id === bid; })[0];
                if (!item) {
                    var raw = ((court ? cellOf(slot, court.id) : null) || slot).bookings.filter(function (x) { return x.id === bid; })[0];
                    item = itemOf({ id: raw.id, customer: raw.customer, phone: raw.phone, channel: raw.channel, status: raw.status, checked_in: raw.checked_in, amount: raw.amount, amount_paid: raw.amount_paid, payment_status: raw.payment_status });
                }
                openBooking(item, { time: slotTime(slot), court: court && court.name });
            } else if (b.hasAttribute('data-block')) {
                haptic('tick');
                openBlock(cellOf(slot, court.id).block, court.name);
            } else if (b.hasAttribute('data-open')) {
                haptic('tick');
                openWalkIn(slot, court ? court.id : null);
            }
        }
    }

    A.register('bookings', {
        enter: function (host) {
            desk.host = host;
            if (!desk.date) desk.date = today();
            host.onclick = onClick;
            host.oninput = function (e) {
                if (e.target.matches('.ha-dk-search input')) {
                    desk.query = e.target.value;
                    if (desk.query && desk.mode !== 'list') desk.mode = 'list';
                    renderBody();
                }
            };
            host.onchange = function (e) {
                if (e.target.matches('.ha-dk-calpick input') && e.target.value) selectDate(e.target.value);
            };
            if (desk.grid) render();
            load(!!desk.grid);
            // The day moves on while the desk is open: a quiet refresh every minute it's in view.
            clearInterval(desk.timer);
            desk.timer = setInterval(function () {
                if (document.visibilityState === 'visible' && !wk && !pay && !desk.loading) load(true);
            }, 60000);
        },
        leave: function () {
            clearInterval(desk.timer);
            desk.host = null;
            desk.seq++;
            if (wk && wk.sheet) wk.sheet.close();
        },
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && desk.host && !wk && !pay) load(true);
    });
})();
