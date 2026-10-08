{{--
    AnalyseSection (PlayerFormSections.kt) on the web: pick a side, pick a player, read
    their last five innings with bat or ball. Loaded on demand from the same public
    endpoint the app calls — GET /api/players/{id}/form — so nothing is built for
    players nobody taps. Only squad members linked to a real player id can have a form.
--}}
@php
    $pfSquad = fn ($raw) => array_values(array_filter(array_map(fn ($m) => [
        'name' => trim((string) (is_array($m) ? ($m['name'] ?? '') : $m)),
        'id' => is_array($m) ? trim((string) ($m['id'] ?? '')) : '',
    ], (array) $raw), fn ($m) => $m['name'] !== '' && $m['id'] !== '' && strtolower($m['id']) !== 'null'));
    $pfSides = array_values(array_filter([
        [(string) (($d['team1Full'] ?? '') ?: ($d['team1'] ?? 'Team 1')), $pfSquad($d['homeSquad'] ?? [])],
        [(string) (($d['team2Full'] ?? '') ?: ($d['team2'] ?? 'Team 2')), $pfSquad($d['awaySquad'] ?? [])],
    ], fn ($s) => count($s[1]) > 0));
@endphp
@if(count($pfSides) > 0)
    <div class="ins-pf-head">
        <div class="ins-head">Player form</div>
        <div class="ins-pills ins-pills--sm" id="pfMode"><button class="is-on" data-m="batting">Batting</button><button data-m="bowling">Bowling</button></div>
    </div>
    <div class="ins-card ins-pf">
        @if(count($pfSides) > 1)
            <div class="ins-pf-teams">
                @foreach($pfSides as $i => [$teamName, $squad])
                    <button class="{{ $i === 0 ? 'is-on' : '' }}" data-t="{{ $i }}">{{ $teamName }}</button>
                @endforeach
            </div>
        @endif
        @foreach($pfSides as $i => [$teamName, $squad])
            <div class="ins-pf-row" data-t="{{ $i }}" @if($i > 0) hidden @endif>
                @foreach($squad as $j => $pl)
                    <button class="ins-pf-av {{ $i === 0 && $j === 0 ? 'is-on' : '' }}" data-id="{{ $pl['id'] }}" data-name="{{ $pl['name'] }}">
                        <i style="background: {{ hrn_mono_color($pl['name']) }}">{{ mb_strtoupper(mb_substr($pl['name'], 0, 1)) }}</i>
                        <span>{{ \Illuminate\Support\Str::limit($pl['name'], 10, '…') }}</span>
                    </button>
                @endforeach
            </div>
        @endforeach
        <div class="ins-pf-body" id="pfBody"><div class="ins-none">Loading…</div></div>
    </div>
    <script>
    (function () {
        var mode = 'batting', current = null, cache = {};
        var body = document.getElementById('pfBody');
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
        function render() {
            if (!current) return;
            var f = cache[current.id];
            if (f === undefined) { body.innerHTML = '<div class="ins-none">Loading…</div>'; return; }
            if (f === null) { body.innerHTML = '<div class="ins-none">Couldn’t load this player’s form.</div>'; return; }
            var block = f[mode];
            var style = mode === 'batting' ? f.battingStyle : f.bowlingStyle;
            var html = '<div class="ins-pf-who"><b>' + esc(current.name) + '</b>' + (style ? '<span>' + esc(style) + '</span>' : '') + '</div>';
            if (!block || !block.innings || !block.innings.length) {
                body.innerHTML = html + '<div class="ins-none">No ' + (mode === 'batting' ? 'innings' : 'spells') + ' in finished matches yet.</div>';
                return;
            }
            html += '<div class="ins-pf-totals">' + block.totals.map(function (t) { return '<div><b>' + esc(t.value) + '</b><span>' + esc(t.label) + '</span></div>'; }).join('') + '</div>';
            var max = Math.max.apply(null, block.innings.map(function (i) { return mode === 'batting' ? i.runs : i.wickets; }).concat([1]));
            html += '<div class="ins-pf-list">' + block.innings.map(function (i) {
                var fig = mode === 'batting' ? i.runs + (i.notOut ? '*' : '') + ' <em>(' + i.balls + ')</em>' : i.wickets + '-' + i.runs + ' <em>(' + esc(i.overs) + ')</em>';
                var v = mode === 'batting' ? i.runs : i.wickets;
                return '<div class="ins-pf-inn"><div><b>' + fig + '</b><span>' + esc(i.match) + ' · ' + esc(i.date) + '</span></div><i style="width:' + Math.max(4, Math.round(v * 100 / max)) + '%"></i></div>';
            }).join('') + '</div>';
            if (mode === 'bowling' && block.efficiency && block.efficiency.length) {
                html += '<div class="ins-pf-eff">' + block.efficiency.map(function (e) { return '<span>' + esc(e.label) + ' <b>' + esc(e.value) + '</b></span>'; }).join('') + '</div>';
            }
            body.innerHTML = html;
        }
        function pick(btn) {
            document.querySelectorAll('.ins-pf-av').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
            current = { id: btn.dataset.id, name: btn.dataset.name };
            render();
            if (cache[current.id] !== undefined) return;
            var id = current.id;
            fetch('/api/players/' + encodeURIComponent(id) + '/form', { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) { cache[id] = j && j.data ? j.data : null; render(); })
                .catch(function () { cache[id] = null; render(); });
        }
        document.querySelectorAll('.ins-pf-av').forEach(function (b) { b.addEventListener('click', function () { pick(b); }); });
        document.querySelectorAll('.ins-pf-teams button').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('.ins-pf-teams button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                document.querySelectorAll('.ins-pf-row').forEach(function (r) { r.hidden = r.dataset.t !== b.dataset.t; });
                var first = document.querySelector('.ins-pf-row[data-t="' + b.dataset.t + '"] .ins-pf-av');
                if (first) pick(first);
            });
        });
        document.querySelectorAll('#pfMode button').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('#pfMode button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                mode = b.dataset.m; render();
            });
        });
        var first = document.querySelector('.ins-pf-av.is-on');
        if (first) pick(first);
    })();
    </script>
@endif
