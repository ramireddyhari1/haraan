{{--
    GroundInsightsCard.kt on the web: the ground this match is played at and what has
    happened there before. $ground is MatchesController::ground()'s `data`; $thisInnings
    is the first innings' runs (0 when nothing is scored) for the par verdict.
--}}
@php
    $gRuns = (int) ($thisInnings ?? 0);
    $gPar = (int) ($ground['firstInningsAvg'] ?? 0);
    $gBest = (int) ($ground['highestTotal'] ?? 0);
    // Ranked by how much the reader would care — a record beats par beats the average.
    $gVerdict = match (true) {
        $gRuns <= 0 => null,
        $gBest > 0 && $gRuns > $gBest => ['HIGHEST TOTAL EVER MADE HERE', "The best before this was {$gBest}.", '#15803D'],
        $gPar > 0 && $gRuns > $gPar => [($gRuns - $gPar).' ABOVE PAR HERE', "A first innings at this ground averages {$gPar}.", '#15803D'],
        $gPar > 0 && $gRuns < $gPar => [($gPar - $gRuns).' BELOW PAR HERE', "A first innings at this ground averages {$gPar}.", '#B54708'],
        $gPar > 0 => ['EXACTLY PAR HERE', "A first innings at this ground averages {$gPar}.", '#475569'],
        default => null,
    };
    $gConf = match ($ground['confidence'] ?? '') {
        'strong' => ['Strong sample', '#16A34A'],
        'established' => ['Good sample', '#2563EB'],
        'emerging' => ['Early trend', '#D97706'],
        default => null,
    };
    $gPlace = implode(', ', array_filter([$ground['locality'] ?? '', $ground['district'] ?? '']));
    $gMaps = ($ground['latitude'] ?? null) !== null && ($ground['longitude'] ?? null) !== null
        ? 'https://www.google.com/maps/search/?api=1&query='.$ground['latitude'].','.$ground['longitude']
        : null;
@endphp
<div class="ins-head">Ground insights</div>
<div class="ins-card ins-ground">
    <div class="ins-g-map">
        @if(! empty($ground['mapUrl']))
            <img src="{{ $ground['mapUrl'] }}" alt="Satellite view of {{ $ground['name'] }}" loading="lazy">
        @else
            <svg viewBox="0 0 320 120" aria-hidden="true"><rect width="320" height="120" fill="#DCEFD9"/><ellipse cx="160" cy="60" rx="120" ry="48" fill="#C9E6C4" stroke="#B5D9AE" stroke-width="2"/><rect x="154" y="44" width="12" height="32" rx="2" fill="#E7D6B0"/></svg>
        @endif
    </div>
    <div class="ins-g-body">
        <div class="ins-g-top">
            <div>
                <b>{{ $ground['name'] ?? 'Ground' }}</b>
                @if($gPlace !== '')<span>{{ $gPlace }}</span>@endif
            </div>
            @if($gMaps)<a href="{{ $gMaps }}" target="_blank" rel="noopener" class="ins-g-dir">Directions</a>@endif
        </div>
        @if($gVerdict)
            <div class="ins-g-verdict" style="--t: {{ $gVerdict[2] }}"><b>{{ $gVerdict[0] }}</b><span>{{ $gVerdict[1] }}</span></div>
        @endif
        @if(! empty($ground['stats']))
            <div class="ins-g-stats">
                @foreach($ground['stats'] as $st)
                    <div><b>{{ $st['value'] }}</b><span>{{ $st['label'] }}</span>@if(! empty($st['note']))<em>{{ $st['note'] }}</em>@endif</div>
                @endforeach
            </div>
        @endif
        @if(! empty($ground['split']))
            @php $sp = $ground['split']; @endphp
            <div class="ins-g-split">
                <span>{{ $sp['title'] }}</span>
                <div class="ins-g-bar"><i style="flex: {{ max(1, (int) $sp['leftPercent']) }}"></i><i style="flex: {{ max(1, (int) $sp['rightPercent']) }}"></i></div>
                <div class="ins-g-legend"><span>{{ $sp['leftLabel'] }} {{ $sp['leftPercent'] }}%</span><span>{{ $sp['rightLabel'] }} {{ $sp['rightPercent'] }}%</span></div>
            </div>
        @endif
        @if(! empty($ground['bullets']))
            <ul class="ins-g-bullets">@foreach($ground['bullets'] as $bl)<li>{{ $bl }}</li>@endforeach</ul>
        @endif
        @if(! empty($ground['note']))<p class="ins-g-note">{{ $ground['note'] }}</p>@endif
        @if($gConf)<div class="ins-g-conf" style="--t: {{ $gConf[1] }}"><i></i>{{ $gConf[0] }} · {{ (int) ($ground['matchesPlayed'] ?? 0) }} match{{ (int) ($ground['matchesPlayed'] ?? 0) === 1 ? '' : 'es' }} here</div>@endif
    </div>
</div>
