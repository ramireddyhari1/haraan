{{--
    What players have done at this venue on Haraan (App\Support\VenueActivity): matches
    scored here and players asking to book. Real rows only; the caller skips it when null.
    Expects $activity (array) and $venueName (string).
--}}
@once
<style>
    .va { margin-top: 36px; color: #0F172A; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    .va h2 { margin: 0 0 4px; font-size: 21px; font-weight: 800; letter-spacing: -0.01em; }
    .va__sub { margin: 0 0 16px; font-size: 14px; color: #64748B; }
    .va__figs { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); border-top: 2px solid #0F172A; }
    .va__fig { padding: 12px 16px 12px 0; border-bottom: 1px solid #E3E8EF; }
    .va__fig b { display: block; font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .va__fig span { display: block; margin-top: 3px; font-size: 13px; color: #475569; }
    .va__list { margin-top: 14px; display: grid; }
    .va__row { display: grid; grid-template-columns: 92px minmax(0, 1fr) auto; gap: 12px; align-items: baseline; padding: 10px 0; border-bottom: 1px solid #E3E8EF; text-decoration: none; color: inherit; font-size: 14.5px; }
    .va__row:hover .va__teams { color: #2563EB; }
    .va__date { font-size: 12.5px; color: #64748B; font-variant-numeric: tabular-nums; }
    .va__teams { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .va__teams i { font-style: normal; font-weight: 500; color: #94A3B8; margin: 0 6px; }
    .va__state { font-size: 12px; font-weight: 700; color: #475569; }
    @media (max-width: 600px) { .va__row { grid-template-columns: minmax(0, 1fr) auto; } .va__date { grid-column: 1 / -1; } }
</style>
@endonce
<section class="va">
    <h2>Played here on Haraan</h2>
    <p class="va__sub">From matches scored on Haraan and players asking to book{{ $activity['matches'] > 0 && $activity['ground'] ? ' — scored as “'.$activity['ground'].'”' : '' }}.</p>
    <div class="va__figs">
        @if($activity['matches'] > 0)
            <div class="va__fig"><b>{{ $activity['matches'] }}</b><span>{{ $activity['matches'] === 1 ? 'match scored here' : 'matches scored here' }}</span></div>
        @endif
        @if($activity['trends'])
            @if($activity['trends']['highest'] > 0)
                <div class="va__fig"><b>{{ $activity['trends']['highest'] }}</b><span>highest team total</span></div>
            @endif
            @if($activity['trends']['avgFirst'] > 0)
                <div class="va__fig"><b>{{ $activity['trends']['avgFirst'] }}</b><span>average first-innings score</span></div>
            @endif
            @if($activity['trends']['decided'] > 0)
                <div class="va__fig"><b>{{ $activity['trends']['battingFirstWins'] }} of {{ $activity['trends']['decided'] }}</b><span>won by the side batting first</span></div>
            @endif
            @if($activity['trends']['best'] > 0 && $activity['trends']['bestBy'])
                <div class="va__fig"><b>{{ $activity['trends']['best'] }}</b><span>best innings, by {{ $activity['trends']['bestBy'] }}</span></div>
            @endif
        @endif
        @if($activity['asks'] > 0)
            <div class="va__fig"><b>{{ $activity['asks'] }}</b><span>players asked to book{{ $activity['askTime'] ? ', mostly '.strtolower($activity['askTime']) : '' }}</span></div>
        @endif
    </div>
    @if(!empty($activity['recent']))
        <div class="va__list">
            @foreach($activity['recent'] as $m)
                <a class="va__row" href="{{ route('site.gamehub.actionboard.match', $m['id']) }}">
                    <span class="va__date">{{ $m['date'] }}</span>
                    <span class="va__teams">{{ $m['home'] }}<i>vs</i>{{ $m['away'] }}</span>
                    <span class="va__state">{{ $m['status'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
