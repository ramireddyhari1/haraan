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

    Expects: $id (match id), $commentary (['onAir' => bool, 'by' => ?string]).
--}}
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
