/**
 * Haraan Partner: the app's Venues, Payments, Matches and Scan tabs on a phone.
 *
 * Each screen registers with the shell (app-shell.js → window.HaApp) and is drawn over
 * its console page, reading the same /api/partner/* endpoints the Android app reads.
 *
 * Ported from: ui/venues/VenuesScreen.kt, HoursEditor.kt, VenueDetailsCard.kt,
 * payments/PaymentsScreen.kt, PaymentDetailSheet.kt, MatchesScreen.kt, ScanScreen.kt.
 */
(function () {
    'use strict';

    var A = window.HaApp;
    if (!A || window.__haScreens) return;
    window.__haScreens = true;

    var esc = A.esc, rupees = A.rupees, inr = A.inr, mat = A.mat, h = A.h;
    var KEYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    var DAY = 1440;

    var BALL = 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 3.3 1.35-.95c1.82.56 3.37 1.76 4.38 3.34l-.39 1.34-1.35.46L13 6.7V5.3zm-3.35-.95L11 5.3v1.4L7.01 9.49l-1.35-.46-.39-1.34a8.103 8.103 0 0 1 4.38-3.34zM7.08 17.11l-1.14.1A7.938 7.938 0 0 1 4 12c0-.12.01-.23.02-.35l1-.73 1.38.48 1.46 4.34-.78 1.37zm7.42 2.48c-.79.26-1.63.41-2.5.41s-1.71-.15-2.5-.41l-.69-1.49.64-1.1h5.11l.64 1.11-.7 1.48zM14.27 15H9.73l-1.35-4.02L12 8.44l3.63 2.54L14.27 15zm3.79 2.21-1.14-.1-.79-1.37 1.46-4.34 1.39-.47 1 .73c.01.11.02.22.02.34 0 1.99-.73 3.81-1.94 5.21z';
    var MORE = {
        pin: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z',
        schedule: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z',
        star: 'M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z',
        directions: 'M21.71 11.29l-9-9a.996.996 0 0 0-1.41 0l-9 9a.996.996 0 0 0 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9a.996.996 0 0 0 0-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5l3.5 3.5-3.5 3.5z',
        copy: 'M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z',
        event: 'M16.53 11.06 15.47 10l-4.88 4.88-2.12-2.12-1.06 1.06L10.59 17l5.94-5.94zM19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z',
        rupee: 'M13.66 7c-.56-1.18-1.76-2-3.16-2H6V3h12v2h-3.26c.48.58.84 1.26 1.05 2H18v2h-2.02c-.25 2.8-2.61 5-5.48 5h-.73l6.73 7h-2.77L7 14v-2h3.5c1.76 0 3.22-1.3 3.46-3H6V7h7.66z',
        open: 'M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z',
        agent: 'M12 1a9 9 0 0 0-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7a9 9 0 0 0-9-9z',
        search: 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
        arrow: 'M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z',
        near: 'M21 3 3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z',
        stadium: 'M12 4C6.5 4 2 5.6 2 7.5v9C2 18.4 6.5 20 12 20s10-1.6 10-3.5v-9C22 5.6 17.5 4 12 4zm0 2c4.4 0 7.5 1.1 7.9 1.5-.4.4-3.5 1.5-7.9 1.5S4.5 7.9 4.1 7.5C4.5 7.1 7.6 6 12 6zm-2 12.9V15h4v3.9c-.65.06-1.32.1-2 .1s-1.35-.04-2-.1z',
        cricket: BALL, soccer: BALL, tennis: BALL, ball: BALL,
    };
    Object.keys(MORE).forEach(function (k) { if (!A.MAT[k]) A.MAT[k] = MORE[k]; });

    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }
    function list(r) { return r && r.data ? r.data : (r || []); }
    function vibrate(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { /* none */ } } }

    function failCard(host, retry) {
        host.innerHTML = '<div class="ha-fail"><b>Couldn’t load this</b><span>Check your connection and try again.</span><button type="button" class="ha-cta" data-retry>Try again</button></div>';
        host.querySelector('[data-retry]').onclick = retry;
    }

    function skeleton(rows) {
        var out = '<div class="ha-skel ha-skel-tab"><b></b>';
        for (var i = 0; i < (rows || 3); i++) out += '<b class="s2"></b>';
        return out + '</div>';
    }

    /* ==================================================================== VENUES === */

    /** One hour of a day grid, as much as the strip needs (hoursOf in VenuesScreen.kt). */
    function hoursOf(grid) {
        if (!grid || !grid.slots) return [];
        return grid.slots.map(function (s) {
            var start = A.slotStart(s.time || s.label);
            if (!isFinite(start) || s.is_open === false) return null;
            var cells = (s.courts || []).filter(function (c) { return c.allowed; });
            if (cells.length) {
                return {
                    start: start,
                    booked: cells.filter(function (c) { return c.is_booked || c.is_held; }).length,
                    total: cells.length,
                    freeValue: cells.filter(function (c) { return !c.is_booked && !c.is_held; }).reduce(function (t, c) { return t + (c.price || 0); }, 0),
                };
            }
            var capN = Math.max(1, s.capacity || 0);
            var b = Math.min(s.booked || 0, capN);
            return { start: start, booked: b, total: capN, freeValue: (capN - b) * (s.price || 0) };
        }).filter(Boolean).sort(function (a, b) { return a.start - b.start; });
    }

    function slotLength(hours) {
        var s = hours.map(function (x) { return x.start; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
        var gaps = [];
        for (var i = 1; i < s.length; i++) if (s[i] - s[i - 1] > 0) gaps.push(s[i] - s[i - 1]);
        return gaps.length ? Math.min(60, Math.max(30, Math.min.apply(null, gaps))) : 60;
    }

    function venueStatus(room) {
        if (room.today && room.today.is_blocked) return ['Closed today', false];
        var today = hoursOf(room.today);
        var now = A.minutesNow();
        if (today.length) {
            var len = slotLength(today);
            var first = today[0].start, end = today[today.length - 1].start + len;
            if (now < first) return ['Opens at ' + A.clock(first), false];
            if (now < end) return ['Open now · till ' + A.clock(end), true];
        }
        var next = room.tomorrow && room.tomorrow.is_blocked ? null : (hoursOf(room.tomorrow)[0] || {}).start;
        return [next != null ? 'Closed · opens ' + A.clock(next) + ' tomorrow' : 'Closed', false];
    }

    /** "CJW6+RMC, MDR175, Vaddeswaram…" → "MDR175, Vaddeswaram…" (readableAddress). */
    function readableAddress(raw) {
        if (!raw) return null;
        var s = String(raw).trim().replace(/^[A-Z0-9]{4,}\+[A-Z0-9]{2,}\s*,?\s*/, '').replace(/, India$/, '').trim();
        return s || null;
    }

    function directionsUrl(d) {
        if (!d) return null;
        if (d.latitude != null && d.longitude != null) return 'https://www.google.com/maps/dir/?api=1&destination=' + d.latitude + ',' + d.longitude;
        if (d.map_link) return d.map_link;
        if (d.address) return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(d.address);
        return null;
    }

    /* ---- opening hours (HoursEditor.kt) ---------------------------------- */

    function parseHours(o) {
        if (!o) return null;
        var d = o.days || {};
        function mins(t) { if (!t) return null; var p = String(t).split(':'); var hh = parseInt(p[0], 10); return isNaN(hh) ? null : hh * 60 + (parseInt(p[1] || '0', 10) || 0); }
        var days = {};
        KEYS.forEach(function (k) {
            var x = d[k];
            var a = x ? mins(x.open) : null, b = x ? mins(x.close) : null;
            days[k] = a != null && b != null ? [a, b] : null;
        });
        return { set: !!o.set, days: days, slotMinutes: o.slot_minutes || 60 };
    }

    function hoursFromSlots(slots, len) {
        var live = (slots || []).filter(function (s) { return s.is_open !== false; });
        if (!live.length) return null;
        var days = {};
        KEYS.forEach(function (k, i) {
            var starts = live.filter(function (s) {
                var d = String(s.day || '').toLowerCase();
                return !d || d === 'every day' || d === NAMES[i].toLowerCase();
            }).map(function (s) { return A.slotStart(s.time); }).filter(isFinite);
            days[k] = starts.length ? [Math.min.apply(null, starts), (Math.max.apply(null, starts) + len) % DAY] : null;
        });
        return { set: false, days: days, slotMinutes: len };
    }

    function range(x) { return x[0] === x[1] ? 'Open 24 hours' : A.clock(x[0]) + ' – ' + A.clock(x[1]); }
    function same(a, b) { return a === b || (a && b && a[0] === b[0] && a[1] === b[1]); }

    function hoursSummary(hr) {
        if (!hr) return null;
        var vals = KEYS.map(function (k) { return hr.days[k]; });
        if (vals.every(function (v) { return !v; })) return 'Closed every day';
        if (vals.every(function (v) { return same(v, vals[0]); })) {
            var r = range(vals[0]);
            return r === 'Open 24 hours' ? 'Open 24 hours, every day' : r + ' every day';
        }
        var parts = [];
        var i = 0;
        while (i < 7) {
            var j = i;
            while (j + 1 < 7 && same(vals[j + 1], vals[i])) j++;
            var days = j - i === 0 ? KEYS[i] : j - i === 1 ? KEYS[i] + ', ' + KEYS[j] : KEYS[i] + '–' + KEYS[j];
            parts.push(days + ' ' + (vals[i] ? range(vals[i]) : 'closed'));
            i = j + 1;
        }
        return parts.join(' · ');
    }

    function minutesOpen(x) { if (!x) return 0; return x[0] === x[1] ? DAY : x[1] > x[0] ? x[1] - x[0] : DAY - x[0] + x[1]; }
    function spans(x) {
        if (!x) return [];
        if (x[0] === x[1]) return [[0, DAY]];
        if (x[1] === 0 || x[1] > x[0]) return [[x[0], x[1] === 0 ? DAY : x[1]]];
        return [[x[0], DAY]];
    }
    function hm(m) { var hh = Math.floor(m / 60) % 24, mm = m % 60; return (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm; }

    var PRESETS = [['6 AM – 11 PM', 360, 1380], ['5 AM – 12 AM', 300, 0], ['6 AM – 10 PM', 360, 1320], ['Open 24 hours', 0, 0]];

    function hoursEditor(room, onSaved) {
        var cur = room.hours;
        var days = KEYS.map(function (k) {
            var x = cur && cur.days[k];
            return x ? { open: true, o: x[0], c: x[1] } : { open: !cur, o: 360, c: 1380 };
        });
        var len = (cur && cur.slotMinutes) || 60;
        var saving = false, error = null;
        var s = A.sheet('<div class="ha-hours"></div>', 'ha-tall');
        var body = s.querySelector('.ha-hours');

        function asHours() {
            var d = {};
            KEYS.forEach(function (k, i) { d[k] = days[i].open ? [days[i].o, days[i].c] : null; });
            return { days: d, slotMinutes: len };
        }

        function chart(hr) {
            var rows = KEYS.map(function (k, i) {
                var prev = hr.days[KEYS[(i + 6) % 7]];
                var carry = prev && prev[1] > 0 && prev[1] < prev[0] ? [[0, prev[1]]] : [];
                var bars = carry.concat(spans(hr.days[k]));
                return '<div class="ha-wkrow"><b class="' + (bars.length ? '' : 'is-off') + '">' + k + '</b><span class="ha-track2">'
                    + bars.map(function (b) { return '<i style="left:' + (b[0] / DAY * 100) + '%;width:' + ((b[1] - b[0]) / DAY * 100) + '%"></i>'; }).join('')
                    + '</span></div>';
            }).join('');
            return '<div class="ha-wkchart">' + rows + '<div class="ha-wkticks"><small>12 AM</small><small>6 AM</small><small>12 PM</small><small>6 PM</small><small>12 AM</small></div></div>';
        }

        function render() {
            var hr = asHours();
            var openDays = days.filter(function (d) { return d.open; });
            var uniq = openDays.map(function (d) { return d.o + ':' + d.c; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
            var uniform = uniq.length === 1 ? uniq[0] : null;
            var perWeek = KEYS.reduce(function (t, k) { return t + Math.floor(minutesOpen(hr.days[k]) / len); }, 0);
            var canSave = !saving && openDays.length > 0;
            body.innerHTML = '<h3 class="ha-sh-title">Opening hours</h3><p class="ha-muted">When players can book ' + esc(room.v.name) + '</p>'
                + chart(hr)
                + '<b class="ha-sh-label">Same for every day</b><div class="ha-presets">'
                + PRESETS.map(function (p, i) {
                    var on = openDays.length === 7 && uniform === p[1] + ':' + p[2];
                    return '<button type="button" class="ha-preset' + (on ? ' is-on' : '') + '" data-preset="' + i + '">' + p[0] + '</button>';
                }).join('') + '</div>'
                + '<b class="ha-sh-label">Day by day</b>'
                + days.map(function (d, i) {
                    var allDay = d.open && d.o === d.c;
                    var nextDay = d.c > 0 && d.c < d.o || (d.c === 0 && d.o > 0);
                    return '<div class="ha-dayline"><div class="ha-row"><b class="ha-grow' + (d.open ? '' : ' is-off') + '">' + NAMES[i] + '</b>'
                        + (d.open ? '<button type="button" class="ha-link" data-copy="' + i + '">Copy to all</button>' : '<small class="ha-faint">Closed</small>')
                        + '<label class="ha-switch"><input type="checkbox" data-toggle="' + i + '"' + (d.open ? ' checked' : '') + '><i></i></label></div>'
                        + (d.open ? '<div class="ha-row ha-times">'
                            + (allDay ? '<span class="ha-allday">Open 24 hours</span>'
                                : '<label class="ha-timebox"><small>Opens</small><b>' + A.clock(d.o) + '</b><input type="time" step="1800" value="' + hm(d.o) + '" data-open="' + i + '"></label>'
                                + '<span class="ha-dash">–</span>'
                                + '<label class="ha-timebox"><small>' + (nextDay ? 'Closes (next day)' : 'Closes') + '</small><b>' + A.clock(d.c) + '</b><input type="time" step="1800" value="' + hm(d.c) + '" data-close="' + i + '"></label>')
                            + '<button type="button" class="ha-24' + (allDay ? ' is-on' : '') + '" data-allday="' + i + '">24h</button></div>' : '')
                        + '</div>';
                }).join('')
                + '<b class="ha-sh-label">Slot length</b><div class="ha-seg2">'
                + [[30, '30 min'], [60, '1 hour']].map(function (x) { return '<button type="button" data-len="' + x[0] + '" class="' + (len === x[0] ? 'is-on' : '') + '">' + x[1] + '</button>'; }).join('') + '</div>'
                + '<p class="ha-muted ha-small ha-gap">' + perWeek + ' slots a week per court. Times outside these hours are removed; prices for the hours you keep stay as they are. Bookings already made aren’t touched.</p>'
                + (error ? '<p class="ha-err">' + esc(error) + '</p>' : '')
                + '<button type="button" class="ha-bigcta" data-save' + (canSave ? '' : ' disabled') + '>' + (saving ? 'Saving…' : openDays.length ? 'Save hours' : 'Keep at least one day open') + '</button>';
        }

        body.addEventListener('click', function (e) {
            var t = e.target, b;
            if ((b = t.closest('[data-preset]'))) { var p = PRESETS[+b.dataset.preset]; days = days.map(function () { return { open: true, o: p[1], c: p[2] }; }); vibrate(6); render(); }
            else if ((b = t.closest('[data-copy]'))) { var src = days[+b.dataset.copy]; days = days.map(function () { return { open: src.open, o: src.o, c: src.c }; }); vibrate(10); render(); }
            else if ((b = t.closest('[data-allday]'))) { var d = days[+b.dataset.allday]; if (d.o === d.c) { d.o = 360; d.c = 1380; } else { d.o = 0; d.c = 0; } render(); }
            else if ((b = t.closest('[data-len]'))) { len = +b.dataset.len; render(); }
            else if ((b = t.closest('[data-save]')) && !b.disabled) save();
        });
        body.addEventListener('change', function (e) {
            var t = e.target;
            function val() { var p = String(t.value || '').split(':'); return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0); }
            if (t.dataset.toggle != null) days[+t.dataset.toggle].open = t.checked;
            else if (t.dataset.open != null && t.value) days[+t.dataset.open].o = val();
            else if (t.dataset.close != null && t.value) days[+t.dataset.close].c = val();
            render();
        });

        function save() {
            saving = true; error = null; render();
            var hr = asHours();
            var payload = { days: {}, slot_minutes: len };
            KEYS.forEach(function (k) { var x = hr.days[k]; payload.days[k] = x ? { open: hm(x[0]), close: hm(x[1]) } : null; });
            A.apiSend('POST', 'venues/' + room.v.id + '/hours', payload).then(function () {
                vibrate(15);
                A.toast('Opening hours saved');
                s.close();
                onSaved();
            }, function (err) {
                error = err.status === 404 || err.status === 405 ? 'Hours can be changed here after the next Haraan server update.' : err.message || 'Couldn’t save. Try again.';
                saving = false;
                render();
            });
        }
        render();
    }

    /* ---- the control room -------------------------------------------------- */

    function attentionFor(room) {
        var out = [];
        if (room.slots) {
            var live = room.slots.filter(function (s) { return s.is_open !== false; });
            if (!live.length) out.push(['No slots on sale', 'Players can’t book this venue online until it has slots.', 'Set hours', 'hours']);
            else if (!live.some(function (s) { return !s.day || String(s.day).toLowerCase() === 'every day'; })) {
                var covered = {};
                live.forEach(function (s) { NAMES.forEach(function (n) { if (n.toLowerCase() === String(s.day).toLowerCase()) covered[n] = 1; }); });
                var missing = NAMES.filter(function (n) { return !covered[n]; });
                if (missing.length) {
                    var weekend = missing.length === 2 && missing[0] === 'Saturday' && missing[1] === 'Sunday';
                    out.push([weekend ? 'No slots on Saturday and Sunday' : 'No slots on ' + missing.map(function (m) { return m.slice(0, 3); }).join(', '),
                        weekend ? 'Weekends are when most people play. Players see this venue as closed on those days.' : 'Players see this venue as closed on those days.', 'Set hours', 'hours']);
                }
            }
        }
        if (room.courts) {
            var listed = (room.v.sports || []).map(function (x) { return String(x).trim().toLowerCase(); });
            room.courts.forEach(function (c) {
                var miss = (c.sports || []).filter(function (x) { return x && listed.indexOf(String(x).trim().toLowerCase()) < 0; });
                if (miss.length && listed.length) {
                    out.push([c.name + ' plays ' + miss[0] + ', but the venue isn’t listed for it',
                        'Players searching ' + miss[0] + ' won’t find you. The venue is listed for ' + room.v.sports.join(', ') + ' only.', 'Ask Haraan', 'support']);
                }
            });
            room.courts.filter(function (c) { return (c.price || 0) <= 0; }).forEach(function (c) {
                out.push([c.name + ' has no rate', 'Its slots sell at ₹0 unless a slot sets its own price.', 'Set rate', 'pricing']);
            });
        }
        if (room.details) {
            if (!room.details.address) out.push(['No address', 'Players can’t tell where the venue is. Send us the full address.', 'Ask Haraan', 'support']);
            else if (room.details.latitude == null || room.details.longitude == null) out.push(['No map pin', 'Players can’t get directions or find you by distance.', 'Ask Haraan', 'support']);
        }
        if (!room.v.image) out.push(['No photo', 'Players see a blank card. Send us a few photos of the courts.', 'Ask Haraan', 'support']);
        return out;
    }

    function courtKind(sports) {
        var s = (sports || []).join(' ').toLowerCase();
        if (/badminton|tennis|pickle|squash|table|volley/.test(s)) return 'racket';
        if (/football|futsal|soccer/.test(s)) return 'football';
        if (/cricket/.test(s)) return 'cricket';
        return 'plain';
    }

    /** A court in miniature, marked for its sport (MiniCourt in CourtsDay.kt). */
    function miniCourt(kind) {
        var grass = kind === 'football' || kind === 'cricket';
        var c = grass ? '#8FC8A0' : '#86A8E4';
        var bg = grass ? '#E3F4E8' : '#E4EDFC';
        var W = 44, H = 56, x = 4, y = 4, w = 36, hh = 48, cx = 22, cy = 28;
        var m = '';
        if (kind === 'racket') {
            m = '<rect x="4" y="4" width="36" height="48"/><line x1="4" y1="28" x2="40" y2="28" stroke-width="1.8"/><line x1="4" y1="19.4" x2="40" y2="19.4"/><line x1="4" y1="36.6" x2="40" y2="36.6"/><line x1="22" y1="4" x2="22" y2="19.4"/><line x1="22" y1="36.6" x2="22" y2="52"/>';
        } else if (kind === 'football') {
            m = '<rect x="4" y="4" width="36" height="48"/><line x1="4" y1="28" x2="40" y2="28"/><circle cx="22" cy="28" r="7.7"/><rect x="13" y="4" width="18" height="6.7"/><rect x="13" y="45.3" width="18" height="6.7"/>';
        } else if (kind === 'cricket') {
            m = '<rect x="15.9" y="8.8" width="12.2" height="38.4"/><line x1="13.9" y1="13.6" x2="30.1" y2="13.6"/><line x1="13.9" y1="42.4" x2="30.1" y2="42.4"/>';
        } else {
            m = '<rect x="4" y="4" width="36" height="48"/><line x1="4" y1="28" x2="40" y2="28"/><circle cx="22" cy="28" r="6"/><line x1="14" y1="4" x2="14" y2="10"/><line x1="30" y1="4" x2="30" y2="10"/><line x1="14" y1="46" x2="14" y2="52"/><line x1="30" y1="46" x2="30" y2="52"/>';
        }
        var bands = grass ? '<rect x="0" y="9.3" width="44" height="9.3" fill="#D6EEDD"/><rect x="0" y="28" width="44" height="9.3" fill="#D6EEDD"/><rect x="0" y="46.7" width="44" height="9.3" fill="#D6EEDD"/>' : '';
        return '<svg class="ha-minicourt" viewBox="0 0 44 56" aria-hidden="true"><defs><clipPath id="ha-mc"><rect width="44" height="56" rx="8"/></clipPath></defs>'
            + '<g clip-path="url(#ha-mc)"><rect width="44" height="56" fill="' + bg + '"/>' + bands + '</g>'
            + '<g fill="none" stroke="' + c + '" stroke-width="1">' + m + '</g></svg>';
    }

    /** "₹6.7L" for the big all-time figures, so a stat never wraps; exact below a lakh. */
    function shortInr(n) {
        n = Math.round(Number(n) || 0);
        if (n >= 10000000) return '₹' + (n / 10000000).toFixed(1).replace(/\.0$/, '') + 'Cr';
        if (n >= 100000) return '₹' + (n / 100000).toFixed(1).replace(/\.0$/, '') + 'L';
        return rupees(n);
    }

    /**
     * Today as one timeline: a cell per slot — booked share filled blue, open ones light,
     * played ones faded — with a "Now" marker and the hours under it.
     */
    function dayStrip(hours, now, len) {
        if (!hours.length) return '<div class="ha-strip-empty">Nothing on sale today</div>';
        var n = hours.length, first = hours[0].start, end = hours[n - 1].start + len, span = end - first;
        var cells = hours.map(function (x, i) {
            var past = x.start + len <= now;
            var fill = x.total > 0 && x.booked > 0 ? x.booked / x.total : 0;
            return '<span class="ha-tl-c' + (past ? ' is-past' : '') + (fill >= 1 ? ' is-full' : '') + '" style="--i:' + i + '">'
                + (fill ? '<i style="height:' + Math.round(fill * 100) + '%"></i>' : '') + '</span>';
        }).join('');
        var marks = [[first, A.clock(first)], [end, A.clock(end)]];
        [720, 1080].forEach(function (m) { if (m - first > span * 0.18 && end - m > span * 0.18) marks.push([m, A.clock(m)]); });
        var nowAt = now > first && now < end ? (now - first) / span * 100 : null;
        return '<div class="ha-tline">' + '<div class="ha-tline-cells' + (n > 30 ? ' is-dense' : '') + '">' + cells + '</div>'
            + (nowAt != null ? '<span class="ha-tline-now" style="left:' + nowAt.toFixed(2) + '%"><em>Now</em></span>' : '')
            + '<div class="ha-tline-axis">' + marks.map(function (m) {
                var at = (m[0] - first) / span * 100;
                return '<small style="left:' + at.toFixed(2) + '%" class="' + (at <= 0 ? 'is-start' : at >= 100 ? 'is-end' : '') + '">' + esc(m[1]) + '</small>';
            }).join('') + '</div></div>';
    }

    /**
     * When there is no venue photo, the venue's own courts drawn from above, side by side,
     * each marked for its sport and named; a court in play right now glows. Drawn from
     * the court list and today's grid — no stock scenery.
     */
    function courtsBanner(room, pill) {
        var courts = room.courts && room.courts.length ? room.courts : (room.today && room.today.courts) || [];
        if (!courts.length) return '';
        var now = A.minutesNow(), hours = hoursOf(room.today), len = slotLength(hours);
        var live = {};
        ((room.today && room.today.slots) || []).forEach(function (sl) {
            var st = A.slotStart(sl.time || sl.label);
            if (!isFinite(st) || now < st || now >= st + len) return;
            (sl.courts || []).forEach(function (c) { if (c.is_booked || c.is_held) live[c.court_id] = true; });
        });
        var shown = courts.slice(0, 4);
        return '<div class="ha-cbanner">' + pill
            + '<div class="ha-cb-row">' + shown.map(function (c, i) {
                var on = !!live[c.id];
                return '<span class="ha-cb-court' + (on ? ' is-live' : '') + '" style="--i:' + i + '">' + miniCourt(courtKind(c.sports))
                    + '<b>' + esc(cap(c.name)) + '</b>' + (on ? '<em>In play</em>' : '') + '</span>';
            }).join('') + (courts.length > 4 ? '<span class="ha-cb-more">+' + (courts.length - 4) + '</span>' : '') + '</div></div>';
    }

    function venueHero(room) {
        var v = room.v;
        var st = venueStatus(room);
        var hours = hoursOf(room.today);
        var now = A.minutesNow();
        var len = slotLength(hours);
        var leftValue = hours.filter(function (x) { return x.start + len > now; }).reduce(function (t, x) { return t + x.freeValue; }, 0);
        var booked = hours.reduce(function (t, x) { return t + x.booked; }, 0);
        var total = hours.reduce(function (t, x) { return t + x.total; }, 0);
        var pill = function (onPhoto) {
            return '<span class="ha-vpill' + (st[1] ? ' is-open' : '') + (onPhoto ? ' on-photo' : '') + '"><i></i>' + esc(st[0]) + '</span>';
        };
        var addr = readableAddress(room.details && room.details.address) || (v.location ? cap(v.location) : null);
        var dir = directionsUrl(room.details);
        var courtsN = (room.courts && room.courts.length) || (room.today && room.today.courts && room.today.courts.length) || 0;
        var canP = A.can('pricing');
        var banner = v.image ? '' : courtsBanner(room, pill(true));
        return '<section class="ha-vcard ha-vhero">'
            + (v.image ? '<div class="ha-vphoto"><img src="' + esc(v.image) + '" alt="' + esc(v.name) + '" loading="lazy">' + pill(true) + '</div>' : (banner || ''))
            + '<div class="ha-vbody">' + (v.image || banner ? '' : pill(false))
            + '<h2>' + esc(v.name) + '</h2>'
            + (addr ? (dir ? '<a class="ha-vaddr" href="' + esc(dir) + '" target="_blank" rel="noopener">' : '<span class="ha-vaddr">') + mat('pin') + '<span>' + esc(addr) + '</span>' + (dir ? '</a>' : '</span>') : '')
            + '<button type="button" class="ha-hoursrow" data-hours="' + v.id + '">' + mat('schedule') + '<span>' + esc(hoursSummary(room.hours) || 'Set your opening hours') + '</span>'
            + (canP ? '<b>Edit</b>' : '') + mat('chevron') + '</button>'
            + '<div class="ha-row ha-vtoday"><b class="ha-grow">Today</b><small>' + (room.today == null ? 'Couldn’t load today' : room.today.is_blocked ? 'Marked closed' : total === 0 ? 'No slots today' : booked + ' of ' + total + ' booked') + '</small></div>'
            + dayStrip(hours, now, len)
            + '<div class="ha-vfacts"><span><b>' + (total === 0 ? '—' : shortInr(leftValue)) + '</b><small>to sell today</small></span><i></i>'
            + '<span><b>' + (courtsN || 1) + '</b><small>' + (courtsN === 1 ? 'court' : 'courts') + '</small></span><i></i>'
            + '<span><b>' + shortInr(v.revenue || 0) + '</b><small>' + (v.bookings || 0) + ' ' + (v.bookings === 1 ? 'booking' : 'bookings') + '</small></span></div>'
            + '<div class="ha-row ha-vacts">'
            + (A.urlOf('sales') ? '<a class="ha-vbtn is-main" href="' + esc(A.urlOf('sales')) + '">Open desk</a>' : '')
            + (canP && A.u.pricing ? '<a class="ha-vbtn" href="' + esc(A.u.pricing) + '">Slots &amp; pricing</a>' : '')
            + '<button type="button" class="ha-vbtn is-icon" data-share="Book a court at ' + esc(v.name) + ' on Haraan: ' + esc(A.cfg.shareBase + '/' + v.id) + '" aria-label="Share venue link">' + mat('share') + '</button>'
            + '</div></div></section>';
    }

    function attentionCard(room) {
        var issues = attentionFor(room);
        if (!issues.length) return '';
        return '<section class="ha-vcard"><div class="ha-row ha-vhead"><b class="ha-grow">Needs attention</b><em class="ha-count">' + issues.length + '</em></div>'
            + issues.map(function (x, i) {
                return (i ? '<hr class="ha-hr ha-hr-44">' : '') + '<button type="button" class="ha-issue" data-fix="' + x[3] + '" data-venue="' + room.v.id + '"><i class="ha-flag"></i>'
                    + '<span class="ha-grow"><b>' + esc(x[0]) + '</b><small>' + esc(x[1]) + '</small></span><em>' + esc(x[2]) + '</em></button>';
            }).join('') + '<div class="ha-pad6"></div></section>';
    }

    function detailsCard(d, published) {
        var html = '<section class="ha-vcard"><div class="ha-row ha-vhead"><b class="ha-grow">Venue details</b>';
        if (d) {
            html += d.rating && d.reviews_count > 0
                ? '<span class="ha-rating">' + mat('star') + Number(d.rating).toFixed(1) + ' · ' + d.reviews_count + ' ' + (d.reviews_count === 1 ? 'rating' : 'ratings') + '</span>'
                : '<small class="ha-faint">No ratings yet</small>';
        }
        html += '</div>';
        if (!d) {
            return html + '<p class="ha-vnote">' + (published ? 'Couldn’t load the details. Pull down to try again.' : 'Your venue’s address, amenities and rules show here once it’s live on Haraan.') + '</p></section>';
        }
        var tag = d.tagline ? String(d.tagline).trim().replace(/^[-–\s]+/, '') : '';
        if (tag) html += '<p class="ha-vtag">' + esc(tag) + '</p>';
        var addr = readableAddress(d.address);
        var dir = directionsUrl(d);
        html += '<div class="ha-vaddrbox"><div class="ha-row ha-top-al">' + mat('pin') + '<span class="ha-grow"><small>Address</small><b class="' + (addr ? '' : 'is-faint') + '">' + esc(addr || 'No address yet') + '</b>'
            + (d.latitude == null || d.longitude == null ? '<em>No map pin. Players can’t get directions.</em>' : '') + '</span></div>'
            + (dir ? '<div class="ha-row ha-gap8"><a class="ha-smallact" href="' + esc(dir) + '" target="_blank" rel="noopener">' + mat('directions') + 'Directions</a>'
                + (d.address ? '<button type="button" class="ha-smallact" data-copytext="' + esc(d.address) + '">' + mat('copy') + 'Copy</button>' : '') + '</div>' : '')
            + '</div>';
        var imgs = d.images || [];
        if (imgs.length) html += '<b class="ha-vsec">Photos · ' + imgs.length + '</b><div class="ha-vphotos">' + imgs.map(function (src) { return '<img src="' + esc(src) + '" alt="" loading="lazy">'; }).join('') + '</div>';
        if ((d.amenities || []).length) html += '<b class="ha-vsec">Amenities</b><div class="ha-chips">' + d.amenities.map(function (a) { return '<span>' + esc(a) + '</span>'; }).join('') + '</div>';
        if ((d.rules || []).length) html += '<b class="ha-vsec">House rules</b><ul class="ha-rules">' + d.rules.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') + '</ul>';
        var fees = (d.fees || []).map(function (f) {
            var val = Number(f.value) || 0;
            if (val <= 0) return null;
            return cap(f.label || 'Fee') + ' ' + (f.type === 'percent' ? inr(val) + '%' : rupees(val));
        }).filter(Boolean);
        if (d.cancellation || fees.length) {
            html += '<b class="ha-vsec">Policy &amp; charges</b><div class="ha-keylines">'
                + (d.cancellation ? '<p><small>Cancellation</small>' + esc(d.cancellation) + '</p>' : '')
                + (fees.length ? '<p><small>Players also pay</small>' + esc(fees.join(' · ')) + '</p>' : '') + '</div>';
        }
        if (d.about && String(d.about).trim()) {
            var long = String(d.about).split('\n').length > 4 || d.about.length > 220;
            html += '<b class="ha-vsec">About</b><div class="ha-about"><p class="is-clamped">' + esc(String(d.about).trim()) + '</p>' + (long ? '<button type="button" class="ha-link" data-more>Read more</button>' : '') + '</div>';
        }
        return html + '<hr class="ha-hr ha-gap16"><a class="ha-row ha-askrow" href="' + esc(A.u.support || '#') + '"><span class="ha-grow">Something wrong or missing here?</span><b>Ask Haraan to update</b></a></section>';
    }

    function courtsCard(room) {
        var courts = room.courts || [];
        if (!courts.length) return '';
        var slots = (room.today && room.today.slots) || [];
        var now = A.minutesNow(), len = slotLength(hoursOf(room.today));
        return '<section class="ha-vcard"><div class="ha-row ha-vhead"><b class="ha-grow">Courts</b><small class="ha-faint">Today</small></div>'
            + courts.map(function (c, i) {
                var cells = [];
                slots.forEach(function (s) { (s.courts || []).forEach(function (x) { if (x.court_id === c.id && x.allowed) cells.push({ c: x, start: A.slotStart(s.time || s.label) }); }); });
                cells.forEach(function (x) { x.past = isFinite(x.start) && x.start + len <= now; Object.keys(x.c).forEach(function (k) { x[k] = x.c[k]; }); });
                var booked = cells.filter(function (x) { return x.is_booked || x.is_held; }).length;
                var prices = cells.map(function (x) { return x.price; }).filter(function (p) { return p > 0; });
                var lo = prices.length ? Math.min.apply(null, prices) : 0, hi = prices.length ? Math.max.apply(null, prices) : 0;
                var rate = prices.length && lo !== hi ? '₹' + inr(lo) + '–' + inr(hi) + ' a slot today'
                    : prices.length ? '₹' + inr(lo) + ' a slot' : c.price > 0 ? '₹' + c.price + ' a slot' : 'No rate set';
                var peak = c.peak_price != null && (!prices.length || lo === hi)
                    ? 'peak ₹' + c.peak_price + (c.peak_start && c.peak_end ? ' ' + A.clock(A.slotStart(c.peak_start)).replace(/ [AP]M$/, '') + '–' + A.clock(A.slotStart(c.peak_end)) : '') : null;
                var href = A.can('pricing') && A.u.pricing ? A.u.pricing : null;
                return (i ? '<hr class="ha-hr ha-hr-78">' : '') + '<' + (href ? 'a href="' + esc(href) + '"' : 'div') + ' class="ha-court">' + miniCourt(courtKind(c.sports))
                    + '<span class="ha-grow"><b>' + esc(cap(c.name)) + '</b><small>' + esc((c.sports || []).join(' · ') || 'Any sport') + '</small>'
                    + '<em class="' + (rate === 'No rate set' ? 'is-amber' : '') + '">' + esc([rate, peak].filter(Boolean).join(' · ')) + '</em>'
                    + (cells.length ? '<span class="ha-crail">' + cells.map(function (x) {
                        return '<i class="' + (x.is_booked || x.is_held ? 'is-b' : x.past ? 'is-p' : '') + '"></i>';
                    }).join('') + '</span>' : '') + '</span>'
                    + '<span class="ha-court-count"><b>' + (cells.length ? booked : '—') + '</b><small>' + (cells.length ? 'of ' + cells.length + ' booked' : 'no slots') + '</small></span>'
                    + '</' + (href ? 'a' : 'div') + '>';
            }).join('') + '<div class="ha-pad6"></div></section>';
    }

    function manageCard(room) {
        var rows = [];
        if (A.urlOf('sales')) rows.push(['event', 'Day desk', 'Book walk-ins, collect, mark a day closed', 'href', A.urlOf('sales')]);
        if (A.can('pricing')) rows.push(['schedule', 'Opening hours', hoursSummary(room.hours) || 'Set when players can book', 'hours', room.v.id]);
        if (A.can('pricing') && A.u.pricing) rows.push(['rupee', 'Slots & pricing', 'Times, days, and each court’s price', 'href', A.u.pricing]);
        if (A.can('reports') && (A.u.operations || A.u.reports)) rows.push(['bars', 'Analytics', 'Bookings and earnings over time', 'href', A.u.operations || A.u.reports]);
        rows.push(['open', 'See what players see', 'Your venue page on haraan.app', 'out', A.cfg.shareBase + '/' + room.v.id]);
        return '<section class="ha-vcard"><b class="ha-vtitle">Manage</b>' + rows.map(function (r, i) {
            var inner = '<span class="ha-mic">' + mat(r[0]) + '</span><span class="ha-grow"><b>' + esc(r[1]) + '</b><small>' + esc(r[2]) + '</small></span>' + mat('chevron');
            var tag = r[3] === 'hours' ? '<button type="button" class="ha-mrow" data-hours="' + r[4] + '">' + inner + '</button>'
                : '<a class="ha-mrow" href="' + esc(r[4]) + '"' + (r[3] === 'out' ? ' target="_blank" rel="noopener"' : '') + '>' + inner + '</a>';
            return (i ? '<hr class="ha-hr ha-hr-68">' : '') + tag;
        }).join('') + '<div class="ha-pad6"></div></section>';
    }

    function anotherVenue() {
        return '<a class="ha-another" href="' + esc(A.u.support || '#') + '">' + mat('agent') + '<span class="ha-grow"><b>Have another venue?</b><small>Haraan lists it for you. Tell us about it.</small></span><em>Ask Haraan</em></a>';
    }

    function noVenue() {
        return '<section class="ha-vcard ha-novenue">' + miniCourt('plain') + '<b>No venue on your account yet</b><p>Haraan sets up your venue and courts. Message us and we’ll get it live.</p>'
            + (A.u.support ? '<a class="ha-cta" href="' + esc(A.u.support) + '">Ask Haraan</a>' : '') + '</section>';
    }

    var venuesState = { rooms: null, host: null };

    function loadRooms() {
        var today = new Date(), tomorrow = new Date(Date.now() + 86400000);
        return A.api('venues', { branch: false }).then(function (r) {
            var vs = list(r);
            return Promise.all(vs.map(function (v) {
                return Promise.all([
                    A.soft(A.api('venues/' + v.id + '/courts', { branch: false })),
                    A.soft(A.api('venues/' + v.id + '/day', { branch: false, date: A.ymd(today) })),
                    A.soft(A.api('venues/' + v.id + '/day', { branch: false, date: A.ymd(tomorrow) })),
                    A.soft(A.api('venues/' + v.id + '/slots', { branch: false })),
                    A.soft(A.api('venues/' + v.id + '/hours', { branch: false })),
                    A.soft(fetch('/api/venues/' + v.id, { headers: { Accept: 'application/json' } }).then(function (x) { if (!x.ok) throw new Error(); return x.json(); })),
                ]).then(function (p) {
                    var t = p[1];
                    var hrs = hoursOf(t);
                    var len = hrs.length > 1 ? slotLength(hrs) : 60;
                    var saved = parseHours(p[4]);
                    var slots = p[3] ? list(p[3]) : null;
                    return {
                        v: v, courts: p[0] ? list(p[0]) : null, today: t, tomorrow: p[2], slots: slots,
                        hours: saved && saved.set ? saved : hoursFromSlots(slots, len),
                        details: p[5] && p[5].data ? p[5].data : null,
                    };
                });
            }));
        });
    }

    function renderVenues() {
        var host = venuesState.host;
        var rooms = venuesState.rooms;
        if (!host || !rooms) return;
        var out = '';
        if (!rooms.length) out += noVenue();
        rooms.forEach(function (room) {
            out += venueHero(room) + attentionCard(room) + detailsCard(room.details, room.details != null) + courtsCard(room) + manageCard(room);
        });
        if (rooms.length) out += anotherVenue();
        host.innerHTML = '<div class="ha-tab ha-venues">' + out + '</div>';
    }

    function loadVenues() {
        var host = venuesState.host;
        if (!venuesState.rooms) host.innerHTML = skeleton(3);
        loadRooms().then(function (rooms) {
            venuesState.rooms = rooms;
            renderVenues();
        }, function () { if (!venuesState.rooms) failCard(host, loadVenues); });
    }

    A.register('venues', {
        enter: function (host) {
            venuesState.host = host;
            host.onclick = function (e) {
                var t = e.target, b;
                if ((b = t.closest('[data-hours]'))) {
                    var room = (venuesState.rooms || []).filter(function (r) { return r.v.id === +b.dataset.hours; })[0];
                    if (!room) return;
                    if (A.can('pricing')) hoursEditor(room, loadVenues);
                    else if (A.u.support) A.go(A.u.support);
                } else if ((b = t.closest('[data-fix]'))) {
                    var fix = b.dataset.fix;
                    var r2 = (venuesState.rooms || []).filter(function (r) { return r.v.id === +b.dataset.venue; })[0];
                    if (fix === 'hours' && r2 && A.can('pricing')) hoursEditor(r2, loadVenues);
                    else if (fix === 'pricing' && A.can('pricing') && A.u.pricing) A.go(A.u.pricing);
                    else if (A.u.support) A.go(A.u.support);
                } else if ((b = t.closest('[data-share]'))) {
                    A.share(b.dataset.share, false);
                } else if ((b = t.closest('[data-copytext]'))) {
                    var txt = b.dataset.copytext;
                    (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { A.toast('Address copied'); }, function () {});
                } else if ((b = t.closest('[data-more]'))) {
                    var p = b.previousElementSibling;
                    var open = p.classList.toggle('is-clamped');
                    b.textContent = open ? 'Read more' : 'Show less';
                }
            };
            loadVenues();
        },
        leave: function () { venuesState.host = null; },
    });

    /* ================================================================== PAYMENTS === */

    var WAYS = { upi: ['UPI', '#2563EB'], online: ['Haraan', '#60A5FA'], cash: ['Cash', '#F59E0B'], card: ['Card', '#1E3A8A'] };
    var FILTERS = [['all', 'All'], ['received', 'Received'], ['due', 'To collect'], ['walkin', 'Walk-in'], ['online', 'Online']];

    function wayOf(b) {
        var m = String(b.payment_method || '').toLowerCase();
        var paid = Number(b.amount_paid) || 0;
        if (m === 'cash') return 'cash';
        if (m === 'upi' || m === 'upi_qr') return 'upi';
        if (m === 'card') return 'card';
        if (m === '') return paid > 0 ? 'online' : null;
        return 'online';
    }

    function slotTime(label) { if (!label) return null; var s = String(label).split('·').pop().trim(); return s || null; }

    function toPays(rows) {
        return rows.map(function (b) {
            var st = String(b.status || '').toLowerCase();
            if (!(b.amount > 0) || st.indexOf('cancel') === 0 || st === 'expired' || st === 'refunded') return null;
            var at = b.created_at ? new Date(b.created_at) : null;
            if (!at || isNaN(at)) return null;
            var paid = Number(b.amount_paid) || 0;
            var slot = slotTime(b.slot_label);
            if (slot) slot = slot.split(/[–-]/)[0].trim();
            return {
                id: b.id, name: b.customer || 'Guest', amount: Number(b.amount) || 0, paid: paid, way: wayOf(b),
                walkIn: String(b.channel || '').toLowerCase() === 'offline', at: at, slot: slot ? 'for ' + slot : null, src: b,
                owed: Math.max(0, (Number(b.amount) || 0) - paid),
            };
        }).filter(Boolean).sort(function (a, b) { return b.at - a.at; });
    }

    function dayKey(d) { return d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate(); }
    var WD = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var MONTH_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    function dayWord(d) {
        var k = dayKey(d);
        if (k === dayKey(new Date())) return 'Today';
        if (k === dayKey(new Date(Date.now() - 86400000))) return 'Yesterday';
        return WD[d.getDay()] + ', ' + d.getDate() + ' ' + A.months[d.getMonth()];
    }
    function timeOf(d) {
        var hh = d.getHours(), mm = d.getMinutes();
        return (hh % 12 === 0 ? 12 : hh % 12) + ':' + (mm < 10 ? '0' : '') + mm + (hh < 12 ? ' AM' : ' PM');
    }

    var pay = { host: null, all: null, filter: 'all', query: '', bins: null, selBar: null };

    var PAY_WAYS = [['upi', 'UPI', '#2563EB'], ['online', 'Haraan', '#60A5FA'], ['cash', 'Cash', '#F59E0B'], ['card', 'Card', '#1E3A8A']];

    function startOf(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }

    /**
     * The hero: this month so far against all of last month, then the last 30 days as one
     * bar per day — the shape of the business, drawn from the payments themselves. Tap a
     * day to read it and jump the list there. If only the latest page of payments is
     * loaded, days older than the oldest one are shown as "no data", not as zero.
     */
    function payHero(all) {
        var now = new Date(), today = startOf(now);
        var mStart = new Date(now.getFullYear(), now.getMonth(), 1);
        var lStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        var paidRows = all.filter(function (p) { return p.paid > 0; });
        var thisMonth = paidRows.filter(function (p) { return p.at >= mStart; }).reduce(function (t, p) { return t + p.paid; }, 0);
        var lastMonth = paidRows.filter(function (p) { return p.at >= lStart && p.at < mStart; }).reduce(function (t, p) { return t + p.paid; }, 0);
        var oldest = all.length ? startOf(all[all.length - 1].at) : today;
        var truncated = all.length >= 100;
        var lastPartial = truncated && oldest > lStart;

        var bins = [];
        for (var i = 29; i >= 0; i--) {
            var d = new Date(today.getFullYear(), today.getMonth(), today.getDate() - i);
            bins.push({ d: d, k: dayKey(d), amt: 0, n: 0, nodata: truncated && d < oldest });
        }
        var byKey = {};
        bins.forEach(function (x) { byKey[x.k] = x; });
        paidRows.forEach(function (p) { var x = byKey[dayKey(p.at)]; if (x) { x.amt += p.paid; x.n++; } });
        var top = bins.reduce(function (m, x) { return Math.max(m, x.amt); }, 0) || 1;
        var total30 = bins.reduce(function (t, x) { return t + x.amt; }, 0), n30 = bins.reduce(function (t, x) { return t + x.n; }, 0);
        pay.bins = bins;
        pay.selBar = null;

        var ways = {};
        paidRows.forEach(function (p) { if (p.at >= bins[0].d && p.way) ways[p.way] = (ways[p.way] || 0) + p.paid; });
        var due = all.reduce(function (t, p) { return t + p.owed; }, 0), dueN = all.filter(function (p) { return p.owed > 0; }).length;

        var bars = bins.map(function (x, idx) {
            var h = x.amt > 0 ? Math.max(6, x.amt / top * 100) : 0;
            return '<button type="button" class="ha-pmbar' + (x.nodata ? ' is-nodata' : '') + (x.k === dayKey(today) ? ' is-today' : '') + (x.d.getDate() === 1 ? ' is-first' : '') + '" data-pbar="' + idx + '" style="--h:' + h + '%;--i:' + idx + '" aria-label="' + esc(dayWord(x.d) + ': ' + rupees(x.amt)) + '"><i></i></button>';
        }).join('');
        var firstIdx = -1;
        bins.forEach(function (x, idx) { if (x.d.getDate() === 1) firstIdx = idx; });
        var axis = '<span style="left:0">' + bins[0].d.getDate() + ' ' + A.months[bins[0].d.getMonth()] + '</span>'
            + (firstIdx > 3 && firstIdx < 26 ? '<span class="is-mid" style="left:' + ((firstIdx + 0.5) / 30 * 100).toFixed(2) + '%">1 ' + A.months[bins[firstIdx].d.getMonth()] + '</span>' : '')
            + '<span style="right:0">Today</span>';
        var cap = 'Last 30 days · ' + rupees(total30) + (n30 ? ' from ' + n30 + (n30 === 1 ? ' payment' : ' payments') : '');

        return '<section class="ha-pmh">'
            + '<div class="ha-pmh-top"><span class="ha-grow"><small>' + MONTH_LONG[now.getMonth()] + ' so far</small><b data-count="' + thisMonth + '">' + rupees(thisMonth) + '</b></span>'
            + '<span class="ha-pmh-vs"><small>' + MONTH_LONG[lStart.getMonth()] + '</small><b>' + (lastPartial ? 'at least ' : '') + rupees(lastMonth) + '</b></span></div>'
            + '<div class="ha-pmh-chart">' + bars + '</div><div class="ha-pmh-axis">' + axis + '</div>'
            + '<p class="ha-pmh-cap" data-default="' + esc(cap) + '">' + esc(cap) + '</p>'
            + (Object.keys(ways).length ? '<div class="ha-pmh-ways">' + PAY_WAYS.filter(function (w) { return ways[w[0]] > 0; }).map(function (w) {
                return '<span><i style="background:' + w[2] + '"></i>' + w[1] + '<b>' + rupees(ways[w[0]]) + '</b></span>';
            }).join('') + '</div>' : '')
            + (due > 0 ? '<button type="button" class="ha-pmh-due" data-filter="due"><i></i><span class="ha-grow"><b>' + rupees(due) + ' still to collect</b><small>' + dueN + ' ' + (dueN === 1 ? 'booking' : 'bookings') + ' not fully paid</small></span>' + mat('chevron') + '</button>' : '')
            + '</section>';
    }

    /** A day's bar was tapped: read it out, and bring that day into view in the list. */
    function pickBar(idx) {
        var host = pay.host, x = pay.bins && pay.bins[idx];
        if (!host || !x) return;
        var same = pay.selBar === idx;
        pay.selBar = same ? null : idx;
        host.querySelectorAll('.ha-pmbar').forEach(function (b, i) { b.classList.toggle('is-sel', i === pay.selBar); });
        var cap = host.querySelector('.ha-pmh-cap');
        cap.textContent = same ? cap.dataset.default : x.nodata ? dayWord(x.d) + ' · older than the payments loaded here'
            : dayWord(x.d) + ' · ' + (x.amt > 0 ? rupees(x.amt) + ' from ' + x.n + (x.n === 1 ? ' payment' : ' payments') : 'nothing received');
        cap.classList.toggle('is-sel', !same);
        vibrate(5);
        if (same || !(x.amt > 0)) return;
        if (pay.filter !== 'all' && pay.filter !== 'received') { pay.filter = 'all'; renderPayments(); }
        var head = host.querySelector('[data-pday="' + x.k + '"]');
        if (head) window.scrollTo({ top: head.getBoundingClientRect().top + window.scrollY - 92, behavior: 'smooth' });
    }

    function payRow(p, i) {
        var bits = [p.way ? (p.way === 'online' ? 'Paid on Haraan' : WAYS[p.way][0]) : (p.paid <= 0 ? 'Not paid' : null), p.walkIn ? 'Walk-in' : null, p.slot, timeOf(p.at)].filter(Boolean);
        return '<button type="button" class="ha-payrow" data-pay="' + p.id + '" style="--i:' + Math.min(i, 10) + '">'
            + '<span class="ha-token is-' + (p.way || 'due') + '">' + A.payToken(p.way) + '</span>'
            + '<span class="ha-pay-who"><b>' + esc(p.name) + '</b><small>' + esc(bits.join(' · ')) + '</small></span>'
            + '<span class="ha-pay-amt">' + (p.paid > 0 ? '<b>+' + rupees(p.paid) + '</b>' : '<b class="is-muted">' + rupees(p.amount) + '</b>')
            // Fully paid rows say nothing more; only money still owed is flagged.
            + (p.owed > 0 && p.paid > 0 ? '<span class="ha-part"><i style="width:' + Math.round(p.paid / p.amount * 100) + '%"></i></span>' : '')
            + (p.owed > 0 ? '<small class="is-due">' + rupees(p.owed) + ' due</small>' : '') + '</span></button>';
    }

    function emptyTill(text) {
        return '<div class="ha-pempty"><svg viewBox="0 0 96 74" aria-hidden="true"><g stroke="#94A3B8" stroke-width="2" fill="none" stroke-linecap="round"><line x1="0" y1="69.6" x2="96" y2="69.6"/><rect x="28.8" y="4.4" width="38.4" height="53.3" rx="5"/><line x1="48" y1="57.7" x2="48" y2="69.6"/></g>'
            + '<g stroke="#2563EB" stroke-width="1.8" fill="none"><rect x="34.6" y="11.1" width="9.6" height="9.6"/><rect x="51.8" y="11.1" width="9.6" height="9.6"/><rect x="34.6" y="31.1" width="9.6" height="9.6"/></g><rect x="53.8" y="33.3" width="5.8" height="5.8" fill="#2563EB"/></svg>'
            + '<p>' + esc(text) + '</p></div>';
    }

    function renderPayments() {
        var host = pay.host, all = pay.all;
        if (!host || !all) return;
        var q = pay.query.trim().toLowerCase();
        var shown = all.filter(function (p) {
            var ok = pay.filter === 'all' || (pay.filter === 'received' && p.paid > 0) || (pay.filter === 'due' && p.owed > 0)
                || (pay.filter === 'walkin' && p.walkIn) || (pay.filter === 'online' && !p.walkIn);
            return ok && (!q || p.name.toLowerCase().indexOf(q) >= 0);
        });
        var groups = [];
        shown.forEach(function (p) {
            var k = dayKey(p.at);
            if (!groups.length || groups[groups.length - 1].k !== k) groups.push({ k: k, at: p.at, rows: [] });
            groups[groups.length - 1].rows.push(p);
        });
        var idx = 0;
        var listHtml = groups.map(function (g) {
            var rec = g.rows.reduce(function (t, p) { return t + p.paid; }, 0), owed = g.rows.reduce(function (t, p) { return t + p.owed; }, 0);
            return '<div class="ha-payday" data-pday="' + g.k + '"><b>' + esc(dayWord(g.at)) + '</b>' + (rec > 0 ? '<em class="is-in">+' + rupees(rec) + '</em>' : '') + (owed > 0 ? '<em class="is-due">' + rupees(owed) + ' due</em>' : '') + '</div>'
                + '<div class="ha-paylist">' + g.rows.map(function (p, i) { return (i ? '<hr class="ha-hr ha-hr-50">' : '') + payRow(p, idx++); }).join('') + '</div>';
        }).join('');
        var listEl = host.querySelector('.ha-paybody');
        var body = (shown.length ? '' : emptyTill(all.length ? 'Nothing matches that.' : 'No payments yet. They’ll show here the moment a booking is paid.'))
            + listHtml + '<p class="ha-payfoot">' + (all.length >= 100 ? 'Showing your latest ' + all.length + ' payments.' : 'That’s every payment so far.') + '</p>';
        if (listEl) {
            listEl.innerHTML = body;
            listEl.classList.remove('is-swap'); void listEl.offsetWidth; listEl.classList.add('is-swap');
            host.querySelectorAll('.ha-chip').forEach(function (c) { c.classList.toggle('is-on', c.dataset.filter === pay.filter); });
            return;
        }
        var counts = { all: all.length, received: 0, due: 0, walkin: 0, online: 0 };
        all.forEach(function (p) { if (p.paid > 0) counts.received++; if (p.owed > 0) counts.due++; if (p.walkIn) counts.walkin++; else counts.online++; });
        host.innerHTML = '<div class="ha-tab ha-payments"><h1 class="ha-tabtitle">Payments</h1>' + payHero(all)
            + '<label class="ha-search">' + mat('search') + '<input type="search" placeholder="Search by name" autocomplete="off" value="' + esc(pay.query) + '"></label>'
            + '<div class="ha-chiprow">' + FILTERS.map(function (f) {
                return '<button type="button" class="ha-chip' + (pay.filter === f[0] ? ' is-on' : '') + (f[0] === 'due' && counts.due ? ' is-alert' : '') + '" data-filter="' + f[0] + '">' + f[1] + '<em>' + counts[f[0]] + '</em></button>';
            }).join('') + '</div>'
            + '<div class="ha-paybody">' + body + '</div></div>';
        countUp(host);
    }

    function countUp(host) {
        host.querySelectorAll('[data-count]').forEach(function (el) {
            var to = parseFloat(el.dataset.count);
            if (!(to > 0) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var t0 = null;
            requestAnimationFrame(function step(t) {
                if (!t0) t0 = t;
                var k = Math.min(1, (t - t0) / 900), e = 1 - Math.pow(1 - k, 3);
                el.textContent = rupees(to * e);
                if (k < 1) requestAnimationFrame(step);
            });
        });
    }

    function loadPayments() {
        var host = pay.host;
        if (!pay.all) host.innerHTML = skeleton(4);
        A.api('bookings').then(function (r) {
            pay.all = toPays(list(r));
            if (!pay.host) return;
            pay.host.innerHTML = '';
            renderPayments();
        }, function () { if (!pay.all) failCard(host, loadPayments); });
    }

    /* ---- the pass (PaymentDetailSheet.kt) ---------------------------------- */

    function wayName(m) {
        switch (String(m || '').toLowerCase()) {
            case 'cash': return 'Cash';
            case 'upi': case 'upi_qr': return 'UPI';
            case 'card': return 'Card';
            case 'package': return 'Pass';
            default: return 'Online on Haraan';
        }
    }

    function dayOf(ymd) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd || '');
        if (!m) return ymd || '—';
        var d = new Date(+m[1], +m[2] - 1, +m[3]);
        return WD[d.getDay()] + ', ' + d.getDate() + ' ' + A.months[d.getMonth()];
    }

    function slotPassed(ymd, label) {
        var md = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd || '');
        // A booking on an earlier day has been played, whether or not its slot has a time.
        if (md && new Date(+md[1], +md[2] - 1, +md[3]) < startOf(new Date())) return true;
        var t = slotTime(label);
        if (!ymd || !t) return false;
        var start = t.split(/[–-]/)[0].trim();
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd);
        var mins = A.slotStart(start);
        if (!m || !isFinite(mins)) return false;
        return new Date(+m[1], +m[2] - 1, +m[3], Math.floor(mins / 60), mins % 60) < new Date();
    }

    function qrSvg(text) {
        if (!window.qrcode) return '';
        var q = window.qrcode(0, 'M');
        q.addData(text);
        q.make();
        var n = q.getModuleCount();
        var d = '';
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += 'M' + c + ' ' + r + 'h1v1h-1z';
        return '<svg viewBox="0 0 ' + n + ' ' + n + '" shape-rendering="crispEdges" aria-label="Ticket QR"><path d="' + d + '" fill="#0F172A"/></svg>';
    }

    function openPass(b) {
        var paid = Number(b.amount_paid) || 0;
        var method = b.payment_method;
        var cancelled = String(b.status || '').toLowerCase().indexOf('cancel') === 0;
        var collecting = false, busy = false, error = null;
        var canManage = A.can('bookings');
        var walkIn = String(b.channel || '').toLowerCase() === 'offline';
        var bookedAt = b.created_at ? new Date(b.created_at) : null;
        var over = slotPassed(b.slot_date, b.slot_label);
        var s = A.sheet('<div class="ha-passwrap"></div>', 'ha-tall ha-grey');
        var wrap = s.querySelector('.ha-passwrap');
        var stampKey = 0;

        function render() {
            var owed = Math.max(0, (Number(b.amount) || 0) - paid);
            var stamp = cancelled ? ['CANCELLED', '#94A3B8'] : owed <= 0 && paid > 0 ? ['PAID', '#86EFAC'] : paid > 0 ? ['PART PAID', '#FCD34D'] : ['DUE', '#FCA5A5'];
            var amountLine = cancelled ? 'Cancelled' : owed <= 0 && paid > 0 ? 'Paid' + (method ? ' · ' + wayName(method) : '') : paid > 0 ? rupees(paid) + ' paid · ' + rupees(owed) + ' to collect' : rupees(owed) + ' to collect';
            var code = b.ticket_code;
            var digits = String(b.phone || '').replace(/\D/g, '');
            var local = digits.length > 10 ? digits.slice(-10) : digits;
            var pretty = local.length === 10 ? '+91 ' + local.slice(0, 5) + ' ' + local.slice(5) : (b.phone || '');
            var nodes = [
                ['Booked', true, false],
                [cancelled ? 'Cancelled' : owed > 0 && paid > 0 ? 'Part paid' : 'Paid', cancelled || paid > 0, cancelled || (owed > 0 && paid > 0)],
                ['Checked in', b.checked_in > 0, false],
                cancelled || !over ? ['Played', false, false] : b.checked_in > 0 ? ['Played', true, false] : ['No-show?', true, true],
            ];
            var reached = 0;
            nodes.forEach(function (n, i) { if (n[1]) reached = i; });

            wrap.innerHTML = '<div class="ha-pass" style="--stamp:' + stamp[1] + '">'
                + '<div class="ha-pass-top"><div class="ha-row"><span class="ha-pass-av">' + esc(A.initials(b.customer)) + '</span><span class="ha-grow"><b>' + esc(b.customer || 'Guest') + '</b>'
                + '<small>' + (walkIn ? 'Walk-in' : 'Booked online') + (bookedAt && !isNaN(bookedAt) ? ' · ' + bookedAt.getDate() + ' ' + A.months[bookedAt.getMonth()] + ', ' + timeOf(bookedAt) : '') + '</small></span></div>'
                + '<div class="ha-row ha-pass-money"><span class="ha-grow"><small>Amount</small><b>' + rupees(b.amount) + '</b><em>' + esc(amountLine) + '</em></span>'
                + '<span class="ha-stamp" data-k="' + stampKey + '">' + stamp[0] + '</span></div></div>'
                + '<div class="ha-pass-stub"><div class="ha-row ha-cells"><span><small>DATE</small><b>' + esc(b.slot_date ? dayOf(b.slot_date) : '—') + '</b></span>'
                + '<span class="w13"><small>TIME</small><b>' + esc((slotTime(b.slot_label) || '—').replace(' - ', '–')) + '</b></span></div>'
                + '<div class="ha-cells"><span><small>VENUE</small><b>' + esc(b.branch || b.venue || b.event || '—') + '</b></span></div>'
                + (code ? '<hr class="ha-hr ha-gap16"><div class="ha-row ha-ticket"><span class="ha-qr">' + qrSvg('haraan:ticket:' + code) + '</span><span class="ha-grow"><small>TICKET</small>'
                    + '<b>' + esc(code.match(/.{1,4}/g).join(' ')) + '</b><button type="button" class="ha-copy" data-copycode="' + esc(code) + '">Copy code</button></span></div>' : '')
                + '</div></div>'
                + '<section class="ha-contact"><small class="ha-capsl">CONTACT</small>'
                + (pretty || b.email ? (pretty ? '<div class="ha-row ha-phone">' + mat('phone') + '<b class="ha-grow">' + esc(pretty) + '</b></div>'
                    + (local.length === 10 ? '<div class="ha-row ha-gap8 ha-mt12"><a class="ha-cbtn is-fill" href="tel:+91' + local + '">Call</a><a class="ha-cbtn" href="https://wa.me/91' + local + '" target="_blank" rel="noopener">WhatsApp</a></div>' : '') : '')
                    + (b.email ? (pretty ? '<hr class="ha-hr ha-gap12">' : '') + '<div class="ha-row"><b class="ha-at">@</b><span class="ha-grow ha-email">' + esc(b.email) + '</span><a class="ha-copy" href="mailto:' + esc(b.email) + '">Email</a></div>' : '')
                    : '<p class="ha-muted">' + (walkIn ? 'No number was taken at the desk for this walk-in.' : 'This customer hasn’t added a number or email.') + '</p>')
                + '</section>'
                + '<section class="ha-trackcard"><div class="ha-track3" style="--fill:' + (reached / 3) + '"><i class="ha-track3-line"></i><i class="ha-track3-fill"></i>'
                + nodes.map(function (n, i) {
                    return '<span class="ha-node' + (n[1] ? ' is-done' : '') + (n[2] ? ' is-alert' : '') + '" style="left:' + (i / 3 * 100) + '%"><i>' + (n[1] ? (n[2] ? '!' : '✓') : '') + '</i><small>' + esc(n[0]) + '</small></span>';
                }).join('') + '</div></section>'
                + (error ? '<p class="ha-err">' + esc(error) + '</p>' : '')
                + (canManage && !cancelled && owed > 0
                    ? (!collecting ? '<button type="button" class="ha-bigcta" data-collect>Collect ' + rupees(owed) + '</button>'
                        : '<p class="ha-muted ha-sb">How did they pay?</p><div class="ha-row ha-gap8">' + [['cash', 'Cash'], ['upi', 'UPI'], ['card', 'Card']].map(function (m) {
                            return '<button type="button" class="ha-softbtn" data-method="' + m[0] + '"' + (busy ? ' disabled' : '') + '>' + m[1] + '</button>';
                        }).join('') + '</div>') : '')
                + '<div class="ha-row ha-gap10 ha-mt10"><button type="button" class="ha-softbtn" data-receipt>Share receipt</button>'
                + (canManage && !cancelled && !over ? '<button type="button" class="ha-softbtn is-danger" data-cancel>Cancel</button>' : '') + '</div>';
            // The tear sits where the blue top ends; the notches are cut there.
            var passEl = wrap.querySelector('.ha-pass'), topEl = wrap.querySelector('.ha-pass-top');
            if (passEl && topEl) passEl.style.setProperty('--tear', topEl.offsetHeight + 'px');
        }

        wrap.addEventListener('click', function (e) {
            var t = e.target, x;
            if ((x = t.closest('[data-copycode]'))) {
                var c = x.dataset.copycode;
                (navigator.clipboard ? navigator.clipboard.writeText(c) : Promise.reject()).then(function () { x.textContent = 'Copied'; x.classList.add('is-ok'); setTimeout(function () { x.textContent = 'Copy code'; x.classList.remove('is-ok'); }, 1600); }, function () {});
            } else if (t.closest('[data-collect]')) { collecting = true; render(); }
            else if ((x = t.closest('[data-method]')) && !busy) {
                busy = true; error = null; render();
                A.apiSend('POST', 'bookings/' + b.id + '/collect', { method: x.dataset.method }).then(function () {
                    paid = Number(b.amount) || 0; method = x.dataset.method; collecting = false; busy = false; stampKey++; vibrate([10, 40, 18]); render(); loadPayments();
                }, function (err) { busy = false; error = err.message || 'Couldn’t record that. Try again.'; render(); });
            } else if (t.closest('[data-receipt]')) {
                var owed = Math.max(0, (Number(b.amount) || 0) - paid);
                var text = 'Haraan receipt\n' + (b.customer || '') + '\n' + [b.branch || b.venue, b.slot_date ? dayOf(b.slot_date) : null, slotTime(b.slot_label)].filter(Boolean).join(' · ')
                    + '\nAmount ₹' + inr(b.amount) + ' · Paid ₹' + inr(paid) + (owed > 0 ? ' · Due ₹' + inr(owed) : '') + (b.ticket_code ? '\nTicket ' + b.ticket_code : '');
                A.share(text, false);
            } else if (t.closest('[data-cancel]')) {
                confirmBox('Cancel ' + (b.customer || 'this') + '’s booking?', 'The court frees up for others. Money already taken isn’t refunded from here.', 'Cancel booking', 'Keep it', function () {
                    busy = true;
                    A.apiSend('POST', 'bookings/' + b.id + '/cancel', {}).then(function () {
                        cancelled = true; busy = false; stampKey++; render(); loadPayments();
                    }, function (err) { busy = false; error = err.message || 'Couldn’t cancel. Try again.'; render(); });
                });
            }
        });

        render();
        if (b.ticket_code && !window.qrcode) {
            A.loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js').then(render, function () {});
        }
    }

    function confirmBox(title, text, yes, no, onYes) {
        var d = h('<div class="ha-dialog"><div class="ha-scrim" data-no></div><div class="ha-dialog-in" role="alertdialog"><b>' + esc(title) + '</b><p>' + esc(text) + '</p>'
            + '<div class="ha-row"><span class="ha-grow"></span><button type="button" class="ha-dlg-btn" data-no>' + esc(no) + '</button><button type="button" class="ha-dlg-btn is-danger" data-yes>' + esc(yes) + '</button></div></div></div>');
        document.body.appendChild(d);
        requestAnimationFrame(function () { d.classList.add('is-open'); });
        d.addEventListener('click', function (e) {
            if (e.target.closest('[data-yes]')) { d.remove(); onYes(); }
            else if (e.target.closest('[data-no]')) d.remove();
        });
    }

    A.register('payments', {
        enter: function (host) {
            pay.host = host;
            host.onclick = function (e) {
                var t = e.target, b;
                if ((b = t.closest('[data-pbar]'))) { pickBar(+b.dataset.pbar); }
                else if ((b = t.closest('[data-filter]'))) {
                    if (pay.filter === b.dataset.filter) return;
                    pay.filter = b.dataset.filter; vibrate(5); renderPayments();
                    if (b.classList.contains('ha-pmh-due')) { var row = host.querySelector('.ha-chiprow'); if (row) window.scrollTo({ top: row.getBoundingClientRect().top + window.scrollY - 92, behavior: 'smooth' }); }
                }
                else if ((b = t.closest('[data-pay]'))) {
                    var p = (pay.all || []).filter(function (x) { return x.id === +b.dataset.pay; })[0];
                    if (p) openPass(p.src);
                }
            };
            host.oninput = function (e) { if (e.target.matches('.ha-search input')) { pay.query = e.target.value; renderPayments(); } };
            loadPayments();
        },
        leave: function () { pay.host = null; },
    });

    /* =================================================================== MATCHES === */

    var matches = { host: null, timer: null, data: null };
    var TONES = ['#1E50E6', '#0B1C46', '#0F766E', '#B45309', '#7C3AED', '#BE123C'];

    function hashCode(s) { var x = 0; for (var i = 0; i < s.length; i++) { x = ((x << 5) - x + s.charCodeAt(i)) | 0; } return x; }

    function monogram(name, dim) {
        var n = String(name || '').trim();
        var tone = TONES[((hashCode(n.toLowerCase()) % TONES.length) + TONES.length) % TONES.length];
        var w = n.split(/\s+/).filter(Boolean);
        var ini = (w.length >= 2 ? w[0][0] + w[1][0] : n.slice(0, 2)).toUpperCase() || '?';
        return '<span class="ha-mono2" style="--tone:' + tone + ';--a:' + (dim ? 0.07 : 0.12) + (dim ? ';color:#94A3B8' : '') + '">' + esc(ini) + '</span>';
    }

    function leader(a, b) {
        var x = parseInt(String(a).split('/')[0], 10), y = parseInt(String(b).split('/')[0], 10);
        if (isNaN(x) || isNaN(y)) return 0;
        return x > y ? 1 : y > x ? 2 : 0;
    }

    function sportGlyph(s) {
        s = String(s || '').toLowerCase();
        if (s === 'cricket') return 'cricket';
        if (/football|soccer|futsal/.test(s)) return 'soccer';
        if (/tennis|badminton|table|pickle/.test(s)) return 'tennis';
        return 'ball';
    }

    function scoreCard(m) {
        var cricket = String(m.sport).toLowerCase() === 'cricket';
        var lead = m.isFinished ? leader(m.score1, m.score2) : 0;
        var overs = cricket && m.overs ? m.overs + ' ov' : null;
        var bat2 = m.battingTeam === 2;
        function team(name, score, sub, dim, batting) {
            return '<div class="ha-row ha-team' + (dim ? ' is-dim' : '') + '">' + monogram(name, dim) + '<span class="ha-grow"><b>' + esc(name || '—') + '</b>'
                + (batting ? '<em class="ha-batting"><i></i>Batting</em>' : '') + '</span><span class="ha-score"><b>' + esc(score || '0') + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span></div>';
        }
        var state = m.isLive ? '<span class="ha-livetag"><i></i>LIVE</span>'
            : '<span class="ha-statetag">' + esc(m.isFinished ? 'Result' : m.time || (m.status ? cap(m.status) : 'Scheduled')) + '</span>';
        var where = m.branch || m.venueName || m.typedVenue;
        var how = m.distanceM != null ? m.distanceM + ' m from your venue' : 'on your booking';
        return '<a class="ha-scard' + (m.isLive ? ' is-live' : '') + '" href="/gamehub/actionboard/match/' + m.id + '" target="_blank" rel="noopener">'
            + '<div class="ha-row ha-scard-top">' + mat(sportGlyph(m.sport)) + '<small>' + esc(cap(m.sport)) + '</small><span class="ha-grow"></span>' + state + '</div>'
            + team(m.home, m.score1, cricket ? (bat2 ? null : overs) : (m.rally1 ? m.rally1 + ' in set' : null), lead === 2, cricket && m.isLive && !bat2)
            + team(m.away, m.score2, cricket ? (bat2 ? overs : null) : (m.rally2 ? m.rally2 + ' in set' : null), lead === 1, cricket && m.isLive && bat2)
            + '<hr class="ha-hr ha-gap12"><div class="ha-row ha-scard-foot">' + mat('pin') + '<span class="ha-grow">' + esc([where, how].filter(Boolean).join(' · ')) + '</span><b>Scorecard</b>' + mat('arrow') + '</div></a>';
    }

    function renderMatches() {
        var host = matches.host, m = matches.data;
        if (!host || !m) return;
        var confirmed = m.confirmed || [], nearby = m.nearby || [];
        if (!confirmed.length && !nearby.length) {
            host.innerHTML = '<div class="ha-tab ha-nomatch"><span class="ha-nomatch-ic">' + mat('stadium') + '</span><b>No games on your courts yet</b>'
                + '<p>When players score a match on Haraan at your venue, it shows up here with the live score.</p></div>';
            return;
        }
        var live = confirmed.concat(nearby).filter(function (x) { return x.isLive; }).length;
        function group(icon, title, note) {
            return '<div class="ha-mgroup"><div class="ha-row"><span class="ha-shead-ic">' + mat(icon) + '</span><b>' + title + '</b></div><small>' + note + '</small></div>';
        }
        host.innerHTML = '<div class="ha-tab ha-matches"><div class="ha-mhead"><h1>Matches</h1><p>'
            + (live > 0 ? '<i class="ha-livedot"></i><b>' + (live === 1 ? '1 game live now' : live + ' games live now') + '</b><span>&nbsp;&nbsp;·&nbsp;&nbsp;scores update on their own</span>' : '<span>Nothing live right now</span>')
            + '</p></div>'
            + (confirmed.length ? group('event', 'On your courts', 'Booked through you, so these are yours for sure.') + confirmed.map(scoreCard).join('') : '')
            + (nearby.length ? group('near', 'Playing nearby', 'Public games within 200 m. Not booked through you.') + nearby.map(scoreCard).join('') : '')
            + '</div>';
        clearTimeout(matches.timer);
        if (live > 0) matches.timer = setTimeout(loadMatches, 20000);
    }

    function loadMatches() {
        var host = matches.host;
        if (!host) return;
        if (!matches.data) host.innerHTML = skeleton(3);
        A.api('matches').then(function (r) {
            matches.data = r && r.data ? r.data : { confirmed: [], nearby: [] };
            renderMatches();
        }, function () { if (!matches.data) failCard(host, loadMatches); });
    }

    A.register('matches', {
        enter: function (host) { matches.host = host; loadMatches(); },
        leave: function () { clearTimeout(matches.timer); matches.host = null; },
    });

    /* ====================================================================== SCAN === */

    var scan = { host: null, stream: null, raf: null, busy: false, held: null, recent: [], detector: null, torch: false, video: null, canvas: null, typing: false };

    function ticketCodeOf(raw) {
        var t = String(raw || '').trim();
        var m = /ticket[:/]([A-Za-z0-9]{6,})/i.exec(t);
        return m ? m[1] : t;
    }

    var TONE = { ok: ['#16A34A', 1800, 'Checked in', 'check'], warn: ['#F59E0B', 2800, 'Already checked in', 'bang'], fail: ['#DC2626', 2800, 'Not valid', 'x'] };

    function stopCamera() {
        cancelAnimationFrame(scan.raf);
        scan.raf = null;
        if (scan.stream) scan.stream.getTracks().forEach(function (t) { t.stop(); });
        scan.stream = null;
    }

    function scanFrame() {
        var v = scan.video;
        if (!v || !scan.stream) return;
        if (scan.busy || scan.held || scan.typing || v.readyState < 2) { scan.raf = requestAnimationFrame(scanFrame); return; }
        if (scan.detector) {
            scan.detector.detect(v).then(function (codes) {
                if (codes && codes.length && codes[0].rawValue) submit(codes[0].rawValue);
                setTimeout(function () { scan.raf = requestAnimationFrame(scanFrame); }, 120);
            }, function () { scan.raf = requestAnimationFrame(scanFrame); });
            return;
        }
        if (window.jsQR) {
            var w = v.videoWidth, hh = v.videoHeight;
            var scale = Math.min(1, 640 / Math.max(w, hh));
            var cw = Math.round(w * scale), ch = Math.round(hh * scale);
            var c = scan.canvas || (scan.canvas = document.createElement('canvas'));
            c.width = cw; c.height = ch;
            var ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(v, 0, 0, cw, ch);
            var img = ctx.getImageData(0, 0, cw, ch);
            var hit = window.jsQR(img.data, cw, ch, { inversionAttempts: 'dontInvert' });
            if (hit && hit.data) submit(hit.data);
        }
        setTimeout(function () { scan.raf = requestAnimationFrame(scanFrame); }, 90);
    }

    function startCamera() {
        var host = scan.host;
        if (!host) return;
        var gate = host.querySelector('.ha-camgate');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { showGate(true, 'This browser can’t open the camera. You can still type codes by hand.'); return; }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false }).then(function (stream) {
            if (!scan.host) { stream.getTracks().forEach(function (t) { t.stop(); }); return; }
            scan.stream = stream;
            var v = scan.video = host.querySelector('video');
            v.srcObject = stream;
            v.play().catch(function () {});
            if (gate) gate.hidden = true;
            host.querySelector('.ha-scan').classList.remove('is-nocam');
            var track = stream.getVideoTracks()[0];
            var caps = track && track.getCapabilities ? track.getCapabilities() : {};
            host.querySelector('[data-torch]').hidden = !caps.torch;
            var ready = 'BarcodeDetector' in window
                ? window.BarcodeDetector.getSupportedFormats().then(function (f) { if (f.indexOf('qr_code') >= 0) scan.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); }).catch(function () {})
                : Promise.resolve();
            ready.then(function () {
                return scan.detector ? null : A.loadScript('https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js');
            }).then(scanFrame, function () { showGate(true, 'The scanner couldn’t load. Check the connection, or type codes by hand.'); });
        }, function (err) {
            showGate(true, err && err.name === 'NotAllowedError'
                ? 'Check-in needs the camera to read ticket QRs. Allow camera access for this site, or type codes by hand.'
                : 'The camera couldn’t start. You can still type codes by hand.');
        });
    }

    var IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    /**
     * How to turn the camera back on, for the phone in hand. iOS never asks twice: once
     * refused, the only way back is a setting, so say exactly where it is.
     */
    function cameraSteps() {
        if (IOS && navigator.standalone) return ['Open the Settings app, then Safari', 'Tap Camera and choose Allow', 'Come back here and tap Try again'];
        if (IOS) return ['Tap \u201caA\u201d in Safari\u2019s address bar', 'Website Settings \u203a Camera \u203a Allow', 'Tap Try again'];
        return ['Tap the icon left of the address bar', 'Permissions \u203a Camera \u203a Allow', 'Tap Try again'];
    }

    function showGate(refused, text) {
        var g = scan.host && scan.host.querySelector('.ha-camgate');
        if (!g) return;
        g.hidden = false;
        scan.host.querySelector('.ha-scan').classList.add('is-nocam');
        g.querySelector('b').textContent = refused ? 'Camera access is off' : 'Starting the camera\u2026';
        g.querySelector('p').textContent = text;
        var allow = g.querySelector('[data-allow]');
        allow.hidden = !refused;
        allow.textContent = refused ? 'Try again' : 'Allow camera';
        var steps = g.querySelector('.ha-gate-steps');
        steps.hidden = !refused;
        steps.innerHTML = refused ? cameraSteps().map(function (t, i) { return '<li><i>' + (i + 1) + '</i><span>' + esc(t) + '</span></li>'; }).join('') : '';
    }

    function gateArt() {
        var q = '';
        [[0, 0], [1, 0], [2, 0], [0, 1], [2, 1], [0, 2], [1, 2], [2, 2], [4, 0], [4, 2], [3, 3], [4, 4], [2, 4], [0, 4], [3, 1], [1, 3]].forEach(function (c) {
            q += '<rect x="' + (56 + c[0] * 6) + '" y="' + (44 + c[1] * 6) + '" width="5" height="5" rx="1"/>';
        });
        return '<svg class="ha-gate-art" viewBox="0 0 140 120" aria-hidden="true">'
            + '<rect x="38" y="6" width="64" height="108" rx="12" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.35)" stroke-width="2"/>'
            + '<circle cx="70" cy="16" r="3.2" fill="none" stroke="rgba(255,255,255,.45)" stroke-width="1.5"/>'
            + '<path d="M64 21 76 11" stroke="#F87171" stroke-width="2" stroke-linecap="round"/>'
            + '<g fill="rgba(255,255,255,.75)">' + q + '</g>'
            + '<g fill="none" stroke="#4D8BFF" stroke-width="2.4" stroke-linecap="round"><path d="M50 46v-8h8M90 46v-8h-8M50 76v8h8M90 76v8h-8"/></g>'
            + '<path d="M18 92h22M100 92h22" stroke="rgba(255,255,255,.18)" stroke-width="2" stroke-dasharray="3 4" stroke-linecap="round"/></svg>';
    }

    /** The capture: a QR seeded from the ticket bursts apart over the verdict (QrBurst). */
    function burst(seed) {
        var host = scan.host;
        if (!host || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var layer = host.querySelector('.ha-burst');
        var N = 21, cells = '';
        var rnd = (function (s) { var x = hashCode(s) || 7; return function () { x ^= x << 13; x ^= x >> 17; x ^= x << 5; return ((x >>> 0) % 1000) / 1000; }; })(seed);
        function finder(r, c) { return (r < 7 && c < 7) || (r < 7 && c >= N - 7) || (r >= N - 7 && c < 7); }
        function dark(r, c) {
            if (finder(r, c)) {
                var rr = r >= N - 7 ? r - (N - 7) : r, cc = c >= N - 7 ? c - (N - 7) : c;
                return rr === 0 || rr === 6 || cc === 0 || cc === 6 || (rr >= 2 && rr <= 4 && cc >= 2 && cc <= 4);
            }
            if (r === 6 || c === 6) return (r + c) % 2 === 0;
            return rnd() > 0.52;
        }
        for (var r = 0; r < N; r++) for (var c = 0; c < N; c++) {
            if (!dark(r, c)) continue;
            var dx = (c - N / 2) / (N / 2), dy = (r - N / 2) / (N / 2);
            var dist = Math.sqrt(dx * dx + dy * dy) + 0.2;
            cells += '<i style="--x:' + (c / N * 100) + '%;--y:' + (r / N * 100) + '%;--dx:' + (dx * 160 * dist + (rnd() - 0.5) * 40).toFixed(0) + 'px;--dy:' + (dy * 160 * dist + (rnd() - 0.5) * 40).toFixed(0) + 'px;--r:' + ((rnd() - 0.5) * 120).toFixed(0) + 'deg;--d:' + ((r + c) * 6) + 'ms"></i>';
        }
        layer.innerHTML = '<div class="ha-burst-qr">' + cells + '</div>';
        layer.classList.remove('is-go'); void layer.offsetWidth; layer.classList.add('is-go');
        setTimeout(function () { layer.innerHTML = ''; }, 1600);
    }

    function release() {
        clearTimeout(scan.holdTimer);
        scan.held = null;
        var f = scan.host && scan.host.querySelector('.ha-verdict');
        if (f) { f.classList.remove('is-in'); setTimeout(function () { f.hidden = true; }, 220); }
    }

    function renderRecent() {
        var el = scan.host && scan.host.querySelector('.ha-recent');
        if (!el) return;
        el.innerHTML = scan.recent.map(function (o) {
            return '<div class="ha-row"><i style="background:' + TONE[o.tone][0] + '"></i><span class="' + (o.guest ? '' : 'is-mono') + '">' + esc(o.guest || o.code) + '</span><span class="ha-grow"></span><small>' + esc(o.message) + '</small></div>';
        }).join('');
    }

    function showVerdict(o) {
        var f = scan.host.querySelector('.ha-verdict');
        var t = TONE[o.tone];
        var icon = { check: '<path d="M5 12.5 10 17.5 19.5 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
            bang: '<path d="M12 5v9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="18.5" r="1.8" fill="currentColor"/>',
            x: '<path d="M6.5 6.5 17.5 17.5M17.5 6.5 6.5 17.5" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>' }[t[3]];
        var detail = o.tone === 'fail' ? o.message : [o.quantity > 0 ? (o.quantity === 1 ? '1 person' : o.quantity + ' people') : null, o.slotLabel].filter(Boolean).join(' · ');
        f.style.setProperty('--tint', t[0]);
        f.style.setProperty('--hold', t[1] + 'ms');
        f.innerHTML = '<div class="ha-verdict-mid"><span class="ha-verdict-badge" style="color:' + t[0] + '"><svg viewBox="0 0 24 24">' + icon + '</svg></span>'
            + '<b>' + t[2] + '</b>' + (o.guest ? '<strong>' + esc(o.guest) + '</strong>' : '') + (detail ? '<p>' + esc(detail) + '</p>' : '') + '<code>' + esc(o.code) + '</code></div>'
            + '<div class="ha-verdict-foot"><small>Tap to scan the next ticket</small><i><i></i></i></div>';
        f.hidden = false;
        void f.offsetWidth;
        f.classList.add('is-in');
    }

    function submit(raw) {
        if (scan.busy || scan.held) return;
        var code = ticketCodeOf(raw);
        if (!code) return;
        scan.busy = true;
        vibrate(8);
        burst(code);
        var host = scan.host;
        host.classList.add('is-locked');
        host.querySelector('.ha-checking').hidden = false;
        var t0 = Date.now();
        A.apiSend('POST', 'check-in', { code: code }).then(function (r) { return r; }, function (err) {
            if (err.status === 409 && err.body) return err.body;
            throw err;
        }).then(function (r) {
            var st = (r && r.status) || 'ok';
            var bk = (r && r.booking) || {};
            return {
                code: code, tone: st === 'ok' ? 'ok' : st === 'already' ? 'warn' : 'fail',
                message: st === 'ok' ? 'Checked in' : st === 'already' ? 'Already checked in' : st === 'invalid' ? 'Ticket is cancelled/invalid' : 'Done',
                guest: bk.customer && String(bk.customer).trim() && bk.customer !== 'null' ? String(bk.customer).trim() : null,
                quantity: bk.quantity || 0, slotLabel: bk.slot_label && bk.slot_label !== 'null' ? bk.slot_label : null,
            };
        }, function (err) {
            return { code: code, tone: 'fail', message: err.message || 'Check-in failed' };
        }).then(function (o) {
            var wait = Math.max(0, 420 - (Date.now() - t0));
            setTimeout(function () {
                if (!scan.host) return;
                host.querySelector('.ha-checking').hidden = true;
                host.classList.remove('is-locked');
                var sc = host.querySelector('.ha-scan');
                sc.classList.remove('is-res-ok', 'is-res-warn', 'is-res-fail'); void sc.offsetWidth;
                sc.classList.add('is-res-' + o.tone);
                setTimeout(function () { sc.classList.remove('is-res-' + o.tone); }, 1200);
                vibrate(o.tone === 'ok' ? [12, 60, 24] : [40, 60, 40]);
                scan.held = o;
                scan.recent.unshift(o);
                scan.recent = scan.recent.slice(0, 3);
                renderRecent();
                showVerdict(o);
                scan.busy = false;
                scan.holdTimer = setTimeout(release, TONE[o.tone][1]);
            }, wait);
        });
    }

    function manualEntry() {
        scan.typing = true;
        var panel = h('<div class="ha-manual"><div class="ha-manual-scrim" data-close></div><div class="ha-manual-in"><div class="ha-row"><b class="ha-grow">Enter ticket code</b>'
            + '<button type="button" class="ha-manual-x" data-close aria-label="Close"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button></div>'
            + '<input type="text" inputmode="text" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="TICKET CODE" maxlength="40">'
            + '<button type="button" class="ha-manual-go" disabled>Check in</button></div></div>');
        scan.host.appendChild(panel);
        var input = panel.querySelector('input'), go = panel.querySelector('.ha-manual-go');
        function close() { scan.typing = false; panel.remove(); }
        input.addEventListener('input', function () {
            var v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (v !== input.value) input.value = v;
            go.disabled = !v;
        });
        function send() { var v = input.value.trim(); if (!v) return; close(); submit(v); }
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
        go.addEventListener('click', send);
        panel.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) close(); });
        setTimeout(function () { input.focus(); }, 50);
    }

    A.register('scan', {
        full: true,
        enter: function () {
            // On <body>, not in the page: the console's page-in animation leaves a transform
            // on .fi-main, which would pin a fixed layer to that (empty) column.
            var host = document.createElement('div');
            host.id = 'ha-scanlayer';
            document.body.appendChild(host);
            scan.host = host;
            scan.busy = false; scan.held = null; scan.typing = false;
            host.innerHTML = '<div class="ha-scan">'
                + '<div class="ha-scan-win"><video playsinline muted autoplay></video>'
                + '<div class="ha-finder" aria-hidden="true"><i class="tl"></i><i class="tr"></i><i class="bl"></i><i class="br"></i><span class="ha-finder-sweep"></span></div>'
                + '<p class="ha-finder-hint">Hold the ticket QR inside the frame</p>'
                + '<div class="ha-camgate" hidden>' + gateArt() + '<b>Starting the camera…</b><p>One moment.</p><ol class="ha-gate-steps" hidden></ol><button type="button" class="ha-cta" data-allow hidden>Allow camera</button></div>'
                + '<svg class="ha-scan-rim" preserveAspectRatio="none" aria-hidden="true"><rect class="ha-rim-base" x="1" y="1" rx="30" ry="30"/><rect class="ha-rim-glow" x="1" y="1" rx="30" ry="30" pathLength="100"/><rect class="ha-rim-line" x="1" y="1" rx="30" ry="30" pathLength="100"/><rect class="ha-rim-flash" x="1" y="1" rx="30" ry="30"/></svg>'
                + '</div>'
                + '<div class="ha-scan-title"><h1>Scan a ticket</h1><p>Point the camera at the ticket QR — no need to tap</p></div>'
                + '<div class="ha-checking" hidden><i class="ha-spin"></i>Checking ticket…</div>'
                + '<div class="ha-scan-foot"><div class="ha-recent"></div><div class="ha-row ha-glassrow">'
                + '<button type="button" class="ha-glass" data-torch hidden><svg viewBox="0 0 24 24"><path fill="currentColor" d="M6 2h12v4l-3 4v12H9V10L6 6V2zm6 10.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg><span>Torch</span></button>'
                + '<button type="button" class="ha-glass" data-type><svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 5H4c-1.1 0-1.99.9-1.99 2L2 17c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-9 3h2v2h-2V8zm0 3h2v2h-2v-2zM8 8h2v2H8V8zm0 3h2v2H8v-2zm-1 2H5v-2h2v2zm0-3H5V8h2v2zm9 7H8v-2h8v2zm0-4h-2v-2h2v2zm0-3h-2V8h2v2zm3 3h-2v-2h2v2zm0-3h-2V8h2v2z"/></svg><span>Enter code</span></button>'
                + '</div></div>'
                + '<div class="ha-burst"></div>'
                + '<div class="ha-verdict" hidden></div>'
                + '</div>';
            renderRecent();
            host.onclick = function (e) {
                var t = e.target, b;
                if (t.closest('.ha-verdict')) { release(); return; }
                if (t.closest('[data-type]')) { manualEntry(); return; }
                if (t.closest('[data-allow]')) { startCamera(); return; }
                if ((b = t.closest('[data-torch]')) && scan.stream) {
                    scan.torch = !scan.torch;
                    var track = scan.stream.getVideoTracks()[0];
                    track.applyConstraints({ advanced: [{ torch: scan.torch }] }).catch(function () {});
                    b.classList.toggle('is-on', scan.torch);
                    b.querySelector('span').textContent = scan.torch ? 'Torch on' : 'Torch';
                }
            };
            showGate(false, 'One moment.');
            startCamera();
        },
        leave: function () {
            stopCamera();
            clearTimeout(scan.holdTimer);
            if (scan.host && scan.host.parentNode) scan.host.parentNode.removeChild(scan.host);
            scan.host = null;
            scan.video = null;
            scan.torch = false;
        },
    });

    // The phone locked or the app was switched away: give the camera back.
    document.addEventListener('visibilitychange', function () {
        if (!scan.host) return;
        if (document.visibilityState === 'hidden') stopCamera();
        else if (!scan.stream) startCamera();
    });
})();
