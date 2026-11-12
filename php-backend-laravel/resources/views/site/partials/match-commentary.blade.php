{{--
    Live commentary on the website's match page: the speaker in the header and the player.

    The match page reloads itself on every ball (the pulse) and each tab is its own page, so a
    plain reload would cut the audio mid-sentence. While someone is listening, both turn into
    an in-place update instead: the page fetches itself, swaps <main>, and re-runs the page's
    scripts. The player lives outside <main> (a bar fixed to the bottom of the window), so the
    voice carries on across balls and tabs. Leaving the match (the back arrow, another page)
    stops it, like closing the app's match screen.

    Listening is free and never autoplays. The voice comes from LiveKit with a listen-only pass
    from POST /api/live-matches/{id}/commentary/listen.

    Used by the cricket match page and the other sports' page (sport-match). Carries its own
    styles, so a page only has to render the speaker button.

    Expects: $id (match id), $commentary (['onAir' => bool, 'by' => ?string]).
--}}
<style>
/* Live commentary speaker: outline + red live dot = tap to listen; solid blue with waves that
   move with the voice (--lvl, 0..1) = listening. */
[data-commentary-speaker] { position: relative; --lvl: 0; width: 38px; height: 38px; flex: 0 0 auto; border-radius: 12px; background: #F1F5FA; border: 1px solid #E4EAF1; display: inline-flex; align-items: center; justify-content: center; color: #334155; cursor: pointer; padding: 0; }
[data-commentary-speaker] svg { width: 19px; height: 19px; }
.mdx-bar [data-commentary-speaker] { margin-right: 8px; }
[data-commentary-speaker] .spk-w1, [data-commentary-speaker] .spk-w2 { transition: opacity .1s linear; }
[data-commentary-speaker] .spk-w1 { opacity: .55; }
[data-commentary-speaker] .spk-w2 { opacity: .3; }
[data-commentary-speaker] .spk-dot { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; border-radius: 50%; background: #DC2626; box-shadow: 0 0 0 1.5px #fff; }
[data-commentary-speaker] .spk-spin { display: none; position: absolute; inset: 9px; border-radius: 50%; border: 2px solid rgba(37,99,235,.2); border-top-color: #2563EB; animation: spk-spin .8s linear infinite; }
[data-commentary-speaker].is-connecting svg, [data-commentary-speaker].is-connecting .spk-dot { opacity: 0; }
[data-commentary-speaker].is-connecting .spk-spin { display: block; }
[data-commentary-speaker].is-playing { background: #2563EB; border-color: #2563EB; color: #fff; }
[data-commentary-speaker].is-playing .spk-dot { display: none; }
[data-commentary-speaker].is-playing .spk-w1 { opacity: calc(.45 + var(--lvl) * .55); }
[data-commentary-speaker].is-playing .spk-w2 { opacity: calc(.15 + var(--lvl) * .85); }
@keyframes spk-spin { to { transform: rotate(360deg); } }

/* The player bar: fixed to the bottom, outside <main>, so in-place updates never touch it. */
.mcx-bar { position: fixed; left: 50%; bottom: 14px; z-index: 60; width: min(560px, calc(100% - 24px)); transform: translate(-50%, 140%); transition: transform .32s cubic-bezier(.2,.8,.2,1); --lvl: 0; }
.mcx-bar.is-on { transform: translate(-50%, 0); }
.mcx-bar-in { display: flex; align-items: center; gap: 10px; padding: 8px 10px 8px 6px; background: #fff; border: 1px solid rgba(220,38,38,.25); border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,.14); }
.mcx-mic { width: 46px; height: 46px; flex: 0 0 auto; }
.mcx-rings path { fill: none; stroke: #DC2626; stroke-width: 2.2; stroke-linecap: round; transition: opacity .1s linear; }
.mcx-rings path:nth-child(-n+2) { opacity: calc(.22 + var(--lvl) * .78); }
.mcx-rings path:nth-child(n+3) { opacity: calc(.06 + var(--lvl) * .94); }
.mcx-txt { flex: 1 1 auto; min-width: 0; }
.mcx-who { display: flex; align-items: center; gap: 7px; }
.mcx-lamp { font-size: 9.5px; font-weight: 800; letter-spacing: 1.3px; color: #fff; background: #DC2626; border-radius: 5px; padding: 2px 6px; }
.mcx-name { font-size: 13.5px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mcx-sub { font-size: 11.5px; color: #64748B; margin-top: 2px; }
.mcx-btn { flex: 0 0 auto; font: inherit; font-size: 12.5px; font-weight: 600; padding: 7px 12px; border-radius: 10px; background: #fff; border: 1px solid #E2E8F0; color: #0F172A; cursor: pointer; }
.mcx-stop { color: #DC2626; border-color: rgba(220,38,38,.3); }
.mcx-report { color: #64748B; }
.mcx-reasons { margin-top: 6px; background: #fff; border: 1px solid #E2E8F0; border-radius: 14px; box-shadow: 0 10px 30px rgba(15,23,42,.12); overflow: hidden; }
.mcx-reasons-h { font-size: 12px; font-weight: 700; color: #64748B; padding: 10px 14px 6px; }
.mcx-reasons button { display: block; width: 100%; text-align: left; font: inherit; font-size: 14px; color: #0F172A; padding: 11px 14px; background: none; border: 0; border-top: 1px solid #F1F5F9; cursor: pointer; }
.mcx-reasons button:hover { background: #F8FAFC; }
html.mcx-listening body { padding-bottom: 84px; }
.mcx-toast { position: fixed; left: 50%; bottom: 96px; transform: translate(-50%, 12px); opacity: 0; z-index: 70; background: #0F172A; color: #fff; font-size: 13px; padding: 9px 14px; border-radius: 10px; transition: opacity .2s, transform .2s; pointer-events: none; max-width: calc(100% - 32px); }
.mcx-toast.is-in { opacity: 1; transform: translate(-50%, 0); }
@media (prefers-reduced-motion: reduce) { .mcx-bar { transition: none; } }
</style>
<script>
(function () {
    var matchId = @json((string) $id);
    var onAir = @json((bool) ($commentary['onAir'] ?? false));
    var by = @json((string) ($commentary['by'] ?? ''));
    var C = window.HaraanCommentary;

    if (!C) {
        C = window.HaraanCommentary = (function () {
            var LIB = 'https://cdn.jsdelivr.net/npm/livekit-client@2.22.4/dist/livekit-client.umd.js';
            var LISTEN = @json(url('/api/live-matches')) + '/';
            var REPORT = @json(url('/api/live-matches/commentary')) + '/';
            var st = { phase: 'idle', match: null, session: null, by: '', room: null, level: 0, softUsed: false };
            var libPromise = null, levelTimer = null, bar = null, audioBox = null;

            function viewerId() {
                try {
                    var k = 'hrnViewer', v = localStorage.getItem(k);
                    if (!v) { v = Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem(k, v); }
                    return v;
                } catch (e) { return ''; }
            }
            function loadLib() {
                if (window.LivekitClient) return Promise.resolve(window.LivekitClient);
                if (!libPromise) {
                    libPromise = new Promise(function (ok, no) {
                        var s = document.createElement('script');
                        s.src = LIB; s.async = true;
                        s.onload = function () { window.LivekitClient ? ok(window.LivekitClient) : no(new Error('lib')); };
                        s.onerror = function () { libPromise = null; no(new Error('lib')); };
                        document.head.appendChild(s);
                    });
                }
                return libPromise;
            }
            function toast(msg) {
                var t = document.createElement('div');
                t.className = 'mcx-toast';
                t.textContent = msg;
                document.body.appendChild(t);
                requestAnimationFrame(function () { t.classList.add('is-in'); });
                setTimeout(function () { t.classList.remove('is-in'); setTimeout(function () { t.remove(); }, 300); }, 3200);
            }

            // ── The persistent player bar (outside <main>, so in-place updates never touch it) ──
            function ensureBar() {
                if (bar) return bar;
                audioBox = document.createElement('div');
                audioBox.hidden = true;
                document.body.appendChild(audioBox);
                bar = document.createElement('div');
                bar.className = 'mcx-bar';
                bar.setAttribute('role', 'region');
                bar.setAttribute('aria-label', 'Live commentary');
                bar.innerHTML =
                    '<div class="mcx-bar-in">' +
                      '<svg class="mcx-mic" viewBox="0 0 60 60" aria-hidden="true">' +
                        '<g class="mcx-rings"><path d="M14 18a18 18 0 0 0 0 22"/><path d="M46 18a18 18 0 0 1 0 22"/><path d="M8 13a26 26 0 0 0 0 32"/><path d="M52 13a26 26 0 0 1 0 32"/></g>' +
                        '<rect x="21" y="5" width="18" height="27" rx="9" fill="#1E293B"/>' +
                        '<path d="M25 11h10M25 14.5h10M25 18h10" stroke="rgba(255,255,255,.25)" stroke-width="1.2" stroke-linecap="round"/>' +
                        '<rect x="21" y="20.5" width="18" height="3.4" fill="#DC2626"/>' +
                        '<path d="M17 21a13 13 0 0 0 26 0" fill="none" stroke="#1E293B" stroke-width="2.2" stroke-linecap="round"/>' +
                        '<path d="M30 34v15" stroke="#1E293B" stroke-width="2.6" stroke-linecap="round"/>' +
                        '<rect x="18" y="49" width="24" height="4.5" rx="2.5" fill="#1E293B"/>' +
                      '</svg>' +
                      '<div class="mcx-txt"><div class="mcx-who"><span class="mcx-lamp">LIVE</span><span class="mcx-name"></span></div><div class="mcx-sub">is calling it live</div></div>' +
                      '<button type="button" class="mcx-btn mcx-report" aria-label="Report this commentary">Report</button>' +
                      '<button type="button" class="mcx-btn mcx-stop" aria-label="Stop live commentary">Stop</button>' +
                    '</div>' +
                    '<div class="mcx-reasons" hidden>' +
                      '<div class="mcx-reasons-h">Report this commentary</div>' +
                      '<button type="button" data-r="abusive">Abusive or hateful</button>' +
                      '<button type="button" data-r="sexual">Sexual content</button>' +
                      '<button type="button" data-r="spam">Ads or spam</button>' +
                      '<button type="button" data-r="not_match">Not about the match</button>' +
                      '<button type="button" data-r="other">Something else</button>' +
                    '</div>';
                document.body.appendChild(bar);
                bar.querySelector('.mcx-stop').addEventListener('click', function () { stop(); });
                var reasons = bar.querySelector('.mcx-reasons');
                bar.querySelector('.mcx-report').addEventListener('click', function () { reasons.hidden = !reasons.hidden; });
                reasons.addEventListener('click', function (e) {
                    var b = e.target.closest('button[data-r]');
                    if (!b || !st.session) return;
                    reasons.hidden = true;
                    fetch(REPORT + st.session + '/report', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ reason: b.getAttribute('data-r'), viewer: viewerId() }),
                    }).then(function (r) { toast(r.ok ? 'Thanks. Haraan will look at it.' : 'Couldn’t send the report. Try again.'); })
                      .catch(function () { toast('Couldn’t send the report. Try again.'); });
                });
                return bar;
            }

            function paint() {
                var playing = st.phase === 'playing';
                document.documentElement.classList.toggle('mcx-listening', playing);
                if (bar) {
                    bar.classList.toggle('is-on', playing);
                    bar.querySelector('.mcx-name').textContent = st.by || 'Live commentary';
                    bar.querySelector('.mcx-sub').textContent = st.by ? 'is calling it live' : 'Listening now';
                    bar.style.setProperty('--lvl', st.level.toFixed(3));
                }
                var btn = document.querySelector('[data-commentary-speaker]');
                if (!btn) return;
                var mine = st.match === btn.getAttribute('data-match');
                btn.classList.toggle('is-playing', mine && playing);
                btn.classList.toggle('is-connecting', mine && st.phase === 'connecting');
                btn.style.setProperty('--lvl', (mine && playing ? st.level : 0).toFixed(3));
                btn.setAttribute('aria-pressed', mine && playing ? 'true' : 'false');
                btn.setAttribute('aria-label', mine && playing ? 'Stop live commentary' : 'Listen to live commentary');
            }

            function start(match, who) {
                stop(true);
                st.phase = 'connecting'; st.match = match; st.by = who || '';
                paint();
                fetch(LISTEN + match + '/commentary/listen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ viewer: viewerId() }),
                }).then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw new Error(j.error || 'Couldn’t play the commentary. Try again.'); return j; });
                }).then(function (pass) {
                    return loadLib().then(function (LK) {
                        if (st.match !== match || st.phase !== 'connecting') return;
                        var room = new LK.Room({ adaptiveStream: false });
                        st.room = room;
                        room.on(LK.RoomEvent.TrackSubscribed, function (track) {
                            if (track.kind !== 'audio') return;
                            var el = track.attach();
                            ensureBar(); audioBox.appendChild(el);
                        });
                        room.on(LK.RoomEvent.Disconnected, function () {
                            if (st.room !== room) return;
                            stop(true);
                            toast('Commentary ended.');
                        });
                        return room.connect(pass.url, pass.token).then(function () {
                            return room.startAudio();
                        }).then(function () {
                            if (st.room !== room) return;
                            st.phase = 'playing'; st.session = pass.session; st.by = pass.by || st.by;
                            ensureBar(); paint();
                            levelTimer = setInterval(function () {
                                var lvl = 0;
                                room.remoteParticipants.forEach(function (p) { lvl = Math.max(lvl, p.audioLevel || 0); });
                                st.level = st.level * 0.55 + Math.min(1, lvl * 1.6) * 0.45;
                                paint();
                            }, 110);
                        });
                    });
                }).catch(function (e) {
                    if (st.match !== match) return;
                    stop(true);
                    toast(e && e.message && e.message !== 'lib' ? e.message : 'Couldn’t play the commentary. Try again.');
                });
            }

            function stop(quiet) {
                clearInterval(levelTimer); levelTimer = null;
                var room = st.room;
                st.room = null; st.phase = 'idle'; st.session = null; st.level = 0;
                if (room) { try { room.disconnect(); } catch (e) {} }
                if (audioBox) audioBox.innerHTML = '';
                if (bar) bar.querySelector('.mcx-reasons').hidden = true;
                paint();
            }

            // ── In-place page update, used only while listening ──
            function softVisit(url, push) {
                return fetch(url, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var next = doc.querySelector('main[role=main]'), cur = document.querySelector('main[role=main]');
                        if (!next || !cur) throw new Error('shape');
                        var y = window.scrollY;
                        // Every loop and listener the old content started checks this and stops.
                        window.mdxEpoch = (window.mdxEpoch || 0) + 1;
                        cur.innerHTML = next.innerHTML;
                        document.title = doc.title;
                        cur.querySelectorAll('script').forEach(function (old) {
                            var s = document.createElement('script');
                            for (var i = 0; i < old.attributes.length; i++) s.setAttribute(old.attributes[i].name, old.attributes[i].value);
                            s.text = old.textContent;
                            old.parentNode.replaceChild(s, old);
                        });
                        if (push) history.pushState({ mdxSoft: true }, '', url);
                        st.softUsed = true;
                        window.scrollTo({ top: y, behavior: 'instant' });
                    })
                    .catch(function () { location.href = url; });
            }

            // Any link to another page of the SAME match (a tab) stays in place while listening.
            document.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (!(st.phase === 'playing' || st.phase === 'connecting') || !st.match) return;
                var a = e.target.closest && e.target.closest('a[href]');
                if (!a || a.target === '_blank' || a.origin !== location.origin) return;
                var base = '/gamehub/actionboard/match/' + st.match;
                if (a.pathname !== base && a.pathname.indexOf(base + '/') !== 0) return;
                if (/\/(json|control)$/.test(a.pathname)) return;
                e.preventDefault();
                if (a.href !== location.href) softVisit(a.href, true);
            });

            window.addEventListener('popstate', function () {
                if (!st.softUsed) return;
                if (st.phase === 'playing') softVisit(location.href, false); else location.reload();
            });

            return {
                bind: function (match, air, who) {
                    var btn = document.querySelector('[data-commentary-speaker]');
                    if (btn && !btn.__mcx) {
                        btn.__mcx = true;
                        btn.addEventListener('click', function () {
                            var playing = st.match === match && (st.phase === 'playing' || st.phase === 'connecting');
                            if (playing) stop(); else start(match, who);
                        });
                    }
                    paint();
                },
                isPlaying: function () { return st.phase === 'playing' || st.phase === 'connecting'; },
                // The page's two ways of moving on: a new ball (reload) and a tab (navigate).
                refresh: function () { if (this.isPlaying()) softVisit(location.href, false); else location.reload(); },
                go: function (url) { if (this.isPlaying()) { softVisit(url, true); return true; } return false; },
            };
        })();
    }

    C.bind(matchId, onAir, by);
})();
</script>
