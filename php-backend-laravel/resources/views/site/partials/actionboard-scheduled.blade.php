{{--
    ActionBoard → Scheduled (the app's tab 2). Two lanes, as on the phone:
      Mine          — the signed-in creator's not-yet-started matches (GET /api/matches/scheduled)
      Open near me  — public matches looking for players (GET /api/matches/open)
    Both arrays come from those same API controllers via PublicWebController::actionBoard.
    Starting a match (toss → scoring) stays in the app, where the scorer lives.
--}}
@php
    $schedWhen = function (?string $iso): array {
        if ($iso === null || $iso === '') return ['Starting soon', null];
        try {
            $at = \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'));
        } catch (\Throwable $e) {
            return ['Scheduled', null];
        }
        $mins = (int) round(now()->diffInMinutes($at, false));
        $rel = match (true) {
            $mins < 0 => 'Was due',
            $mins < 60 => 'In '.max(1, $mins).' min',
            $mins < 24 * 60 => 'In '.intdiv($mins, 60).' h',
            default => null,
        };
        return [$at->format('D, j M · g:i A'), $rel];
    };
    $schedSport = fn (string $k) => ucwords(str_replace('_', ' ', $k ?: 'cricket'));
    $signedIn = auth()->check();
@endphp
<div class="mab__panel" id="mabPanel2">
    <div class="mabs__sub" role="tablist">
        <button class="is-on" type="button" data-lane="mine" onclick="mabSchedLane('mine', this)">Mine</button>
        <button type="button" data-lane="open" onclick="mabSchedLane('open', this)">Open near me</button>
    </div>

    {{-- ── Mine ── --}}
    <div class="mabs__lane" data-lane="mine">
        @if (! $signedIn)
            <div class="mabs__empty">
                <b>Your scheduled matches</b>
                <span>Sign in to see the matches you've scheduled.</span>
                <a class="mabs__cta" href="{{ route('site.login') }}">Sign in</a>
            </div>
        @elseif (count($mine) === 0)
            <div class="mabs__empty">
                <b>Nothing scheduled yet</b>
                <span>Create a match and pick Schedule. It waits here until you start it.</span>
                <a class="mabs__cta" href="{{ route('site.gamehub.actionboard.create') }}">Create match</a>
            </div>
        @else
            <div class="mab__ltitle"><span class="mab__ldot"></span><span class="mab__ltxt">Your scheduled matches</span><span class="mabs__count">{{ count($mine) === 1 ? '1 match' : count($mine).' matches' }}</span></div>
            <div class="mab__group">
                @foreach ($mine as $sm)
                    @php [$when, $rel] = $schedWhen($sm['scheduledAt'] ?? null); @endphp
                    <a class="mabs__card" data-sport="{{ $sm['sport'] ?? 'cricket' }}" href="{{ route('site.gamehub.actionboard.match', ['id' => $sm['id']]) }}">
                        <div class="mabs__meta">
                            <span class="mabs__sport">{{ $schedSport($sm['sport'] ?? '') }}</span>
                            @if (! empty($sm['isPrivate']))<span class="mabs__priv">Private</span>@endif
                            <span class="mabs__when">@if ($rel)<em class="{{ $rel === 'Was due' ? 'is-late' : '' }}">{{ $rel }}</em> · @endif{{ $when }}</span>
                        </div>
                        <div class="mabs__sides">
                            <div><i style="background:#2563EB">{{ $abInitials($sm['teamA'] ?: 'Team A') }}</i><b>{{ $sm['teamA'] ?: 'Team A' }}</b><span>{{ count($sm['squadA'] ?? []) }} players</span></div>
                            <em>vs</em>
                            <div><i style="background:#F59E0B">{{ $abInitials($sm['teamB'] ?: 'Team B') }}</i><b>{{ $sm['teamB'] ?: 'Team B' }}</b><span>{{ count($sm['squadB'] ?? []) }} players</span></div>
                        </div>
                        @php $where = implode(' · ', array_filter([$sm['venue'] ?? '', $sm['locality'] ?? ''])); @endphp
                        <div class="mabs__foot">
                            <span>{{ $where !== '' ? $where : 'Venue not set' }}</span>
                            @if (! empty($sm['joinCode']))<span class="mabs__code">Code {{ $sm['joinCode'] }}</span>@endif
                        </div>
                        <div class="mabs__hint">Start the toss and score it from the Haraan app.</div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Open near me ── --}}
    <div class="mabs__lane" data-lane="open" hidden>
        @if (count($open) === 0)
            <div class="mabs__empty">
                <b>No open matches near you</b>
                <span>Turn on "Looking for players" when you create one.</span>
            </div>
        @else
            <div class="mab__ltitle"><span class="mab__ldot"></span><span class="mab__ltxt">Open matches near you</span></div>
            <div class="mab__group">
                @foreach ($open as $om)
                    @php
                        [$when, $rel] = $schedWhen($om['scheduledAt'] ?? null);
                        $slots = (int) ($om['slotsNeeded'] ?? 0);
                        $st = $om['myStatus'] ?? 'none';
                    @endphp
                    <div class="mabs__card" data-sport="{{ $om['sport'] ?? 'cricket' }}">
                        <a class="mabs__link" href="{{ route('site.gamehub.actionboard.match', ['id' => $om['id']]) }}">
                            <div class="mabs__meta">
                                <span class="mabs__sport">{{ $schedSport($om['sport'] ?? '') }}</span>
                                @if (($om['competition'] ?? '') !== '')<span class="mabs__comp">{{ $om['competition'] }}</span>@endif
                                <span class="mabs__when">@if ($rel)<em>{{ $rel }}</em> · @endif{{ $when }}</span>
                            </div>
                            <div class="mabs__vs"><b>{{ $om['team1'] ?: 'Team A' }}</b><em>vs</em><b>{{ $om['team2'] ?: 'Team B' }}</b></div>
                            <div class="mabs__pills">
                                <span class="mabs__pill is-green">{{ $slots }} {{ $slots === 1 ? 'spot left' : 'spots left' }}</span>
                                @if (($om['distanceKm'] ?? null) !== null)<span class="mabs__pill">{{ $om['distanceKm'] }} km</span>@endif
                                @php $where = implode(' · ', array_filter([$om['venue'] ?? '', $om['locality'] ?? ''])); @endphp
                                @if ($where !== '')<span class="mabs__pill">{{ $where }}</span>@endif
                            </div>
                        </a>
                        <div class="mabs__act" data-id="{{ $om['id'] }}" data-status="{{ $st }}">
                            @if (! $signedIn)
                                <a class="mabs__btn" href="{{ route('site.login') }}">Sign in to join</a>
                            @elseif ($st === 'accepted')
                                <span class="mabs__ok">You're in</span>
                            @elseif ($st === 'declined')
                                <span class="mabs__no">Not accepted</span>
                            @elseif ($st === 'pending')
                                <button type="button" class="mabs__btn is-ghost" onclick="mabJoin(this, false)">Requested · Cancel</button>
                            @else
                                <button type="button" class="mabs__btn" onclick="mabJoin(this, true)">Request to join</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<style>
.mabs__sub { display: flex; gap: 6px; background: #E9EEF5; border-radius: 12px; padding: 4px; margin: 4px 0 10px; }
.mabs__sub button { flex: 1; border: 0; background: transparent; font: inherit; font-size: 13px; font-weight: 700; color: #64748B; padding: 8px 0; border-radius: 9px; cursor: pointer; }
.mabs__sub button.is-on { background: #fff; color: #0F172A; box-shadow: 0 1px 3px rgba(15,23,42,.12); }
.mabs__lane[hidden], .mabs__card[hidden] { display: none; }
.mabs__count { font-size: 12px; font-weight: 600; color: #94A3B8; }
.mabs__empty { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; padding: 40px 20px; }
.mabs__empty b { font-size: 15px; font-weight: 800; color: #0F172A; }
.mabs__empty span { font-size: 13px; color: #64748B; line-height: 1.5; }
.mabs__cta { margin-top: 8px; background: #2563EB; color: #fff; text-decoration: none; font-size: 13.5px; font-weight: 700; padding: 10px 20px; border-radius: 12px; }
.mabs__card { display: block; text-decoration: none; color: inherit; background: #fff; border: 1px solid #E2E8F0; border-radius: 14px; box-shadow: 0 4px 12px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.06); padding: 12px 14px; }
.mabs__link { display: block; text-decoration: none; color: inherit; }
.mabs__meta { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #94A3B8; min-width: 0; }
.mabs__sport { color: #2563EB; letter-spacing: .3px; text-transform: uppercase; font-size: 10px; font-weight: 800; }
.mabs__priv, .mabs__comp { color: #64748B; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mabs__when { margin-left: auto; white-space: nowrap; color: #475569; font-weight: 600; }
.mabs__when em { font-style: normal; color: #16A34A; font-weight: 800; }
.mabs__when em.is-late { color: #DC2626; }
.mabs__sides { display: flex; align-items: center; gap: 10px; margin: 12px 0 10px; }
.mabs__sides > div { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; }
.mabs__sides i { width: 40px; height: 40px; border-radius: 50%; color: #fff; font-style: normal; font-weight: 900; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
.mabs__sides b { font-size: 14px; font-weight: 800; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mabs__sides span { font-size: 11px; color: #94A3B8; font-weight: 600; }
.mabs__sides > em, .mabs__vs em { font-style: normal; font-size: 12px; color: #94A3B8; font-weight: 600; }
.mabs__foot { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 9px; }
.mabs__foot span:first-child { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mabs__code { font-weight: 800; color: #0F172A; letter-spacing: .5px; white-space: nowrap; }
.mabs__hint { font-size: 11px; color: #94A3B8; margin-top: 6px; }
.mabs__vs { display: flex; align-items: center; gap: 10px; margin: 10px 0 8px; }
.mabs__vs b { flex: 1; min-width: 0; font-size: 14.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mabs__vs b:last-child { text-align: right; }
.mabs__pills { display: flex; flex-wrap: wrap; gap: 6px; }
.mabs__pill { font-size: 11.5px; font-weight: 600; color: #475569; background: #F1F3F7; padding: 4px 9px; border-radius: 999px; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mabs__pill.is-green { color: #15803D; background: #DCFCE7; font-weight: 700; }
.mabs__act { margin-top: 12px; display: flex; justify-content: flex-end; }
.mabs__btn { border: 0; cursor: pointer; font: inherit; background: #2563EB; color: #fff; font-size: 13px; font-weight: 700; padding: 9px 16px; border-radius: 11px; text-decoration: none; }
.mabs__btn.is-ghost { background: #F1F5F9; color: #475569; }
.mabs__btn[disabled] { opacity: .6; cursor: default; }
.mabs__ok { color: #16A34A; font-weight: 800; font-size: 13px; }
.mabs__no { color: #94A3B8; font-weight: 600; font-size: 12.5px; }
</style>
<script>
(function () {
    window.mabSchedLane = function (lane, btn) {
        document.querySelectorAll('.mabs__sub button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
        document.querySelectorAll('.mabs__lane').forEach(function (l) { l.hidden = l.dataset.lane !== lane; });
    };
    // Request / withdraw — the same MatchJoinController rules as the app, via the session.
    window.mabJoin = function (btn, join) {
        var box = btn.closest('.mabs__act');
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        btn.disabled = true;
        fetch('/gamehub/actionboard/match/' + box.dataset.id + '/join', {
            method: join ? 'POST' : 'DELETE',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; });
        }).then(function (res) {
            btn.disabled = false;
            if (!res.ok) { alert(res.j.error || res.j.message || "Couldn't send request. Try again."); return; }
            if (join) {
                btn.textContent = 'Requested · Cancel';
                btn.classList.add('is-ghost');
                btn.setAttribute('onclick', 'mabJoin(this, false)');
            } else {
                btn.textContent = 'Request to join';
                btn.classList.remove('is-ghost');
                btn.setAttribute('onclick', 'mabJoin(this, true)');
            }
        }).catch(function () { btn.disabled = false; alert("Couldn't send request. Try again."); });
    };
})();
</script>
