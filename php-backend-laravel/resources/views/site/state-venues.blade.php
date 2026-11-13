@extends('site.layout')
@section('body_class', 'kv-page')
@section('content')
{{-- The state hub: every city with venues, its counts and sports, linking down to the
     city and sport pages. Data from StateVenuesController (CityVenueGuide). --}}
<style>
    .kv-page main.container, .kv-page .container { max-width: none; }
    .sv {
        --ink: #0F172A; --ink-2: #334155; --ink-3: #64748B; --rule: #E3E8EF; --paper: #FFFFFF;
        --blue: #2563EB; --blue-ink: #1E40AF; --blue-tint: #EEF4FF;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink);
        max-width: 1120px; margin: 0 auto; padding: 0 20px 72px; font-variant-numeric: tabular-nums;
    }
    .sv a { color: inherit; }
    .sv-crumbs { display: flex; flex-wrap: wrap; gap: 6px; font-size: 13px; color: var(--ink-3); padding: 32px 0 18px; }
    .sv-crumbs a { text-decoration: none; }
    .sv-crumbs a:hover { color: var(--blue); }
    .sv h1 { margin: 0; font-size: 42px; line-height: 1.08; font-weight: 800; letter-spacing: -0.028em; text-wrap: balance; }
    .sv-lead { margin: 16px 0 0; max-width: 680px; font-size: 16.5px; line-height: 1.6; color: var(--ink-2); }
    .sv-lead strong { color: var(--ink); }
    .sv-figs { display: flex; flex-wrap: wrap; gap: 28px; margin: 26px 0 0; padding: 18px 0; border-top: 1px solid var(--rule); border-bottom: 1px solid var(--rule); }
    .sv-figs div b { display: block; font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
    .sv-figs div span { font-size: 13px; color: var(--ink-3); font-weight: 600; }
    .sv h2 { margin: 44px 0 16px; font-size: 22px; font-weight: 800; letter-spacing: -0.015em; }
    .sv-cities { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .sv-city { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; padding: 18px 20px; }
    .sv-city h3 { margin: 0; font-size: 18px; font-weight: 800; display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
    .sv-city h3 a { text-decoration: none; }
    .sv-city h3 a:hover { color: var(--blue); }
    .sv-city h3 small { font-size: 13px; color: var(--ink-3); font-weight: 700; white-space: nowrap; }
    .sv-city p { margin: 6px 0 0; font-size: 13.5px; color: var(--ink-3); line-height: 1.5; }
    .sv-sports { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .sv-sports a { font-size: 13px; font-weight: 600; text-decoration: none; padding: 5px 10px; border-radius: 999px; background: var(--blue-tint); color: var(--blue-ink); }
    .sv-sports a:hover { background: #DCE7FE; }
    .sv-faq details { border-top: 1px solid var(--rule); }
    .sv-faq details:last-child { border-bottom: 1px solid var(--rule); }
    .sv-faq summary { list-style: none; cursor: pointer; padding: 16px 2px; font-size: 16px; font-weight: 700; }
    .sv-faq summary::-webkit-details-marker { display: none; }
    .sv-faq p { margin: -4px 0 18px; font-size: 15px; line-height: 1.65; color: var(--ink-2); }
    .sv-owner { margin-top: 40px; font-size: 15px; color: var(--ink-2); }
    .sv-owner a { color: var(--blue); font-weight: 700; text-decoration: none; }
    @media (max-width: 600px) {
        .sv { padding: 0 16px 56px; }
        .sv h1 { font-size: 32px; }
        .sv-cities { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<div class="sv">
    <nav class="sv-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('site.gamehub') }}">Pulse</a><span aria-hidden="true">/</span>
        <span>{{ $state }}</span>
    </nav>

    <h1>Sports venues in {{ $state }}</h1>
    @if($total > 0)
        <p class="sv-lead">
            Haraan lists <strong>{{ $total }} turfs, courts and grounds</strong> across {{ count($cities) }} {{ \Illuminate\Support\Str::plural('city', count($cities)) }} in {{ $state }}.
            Pick a city to see each venue’s address, timings, photos and directions{{ $bookable > 0 ? ', and book the ones on Haraan online' : '' }}.
        </p>
        <div class="sv-figs">
            <div><b>{{ $total }}</b><span>venues</span></div>
            <div><b>{{ count($cities) }}</b><span>cities</span></div>
            @foreach(array_slice($sports, 0, 4, true) as $slug => $n)
                <div><b>{{ $n }}</b><span>{{ strtolower(\App\Support\CityVenueGuide::SPORTS[$slug][0]) }}</span></div>
            @endforeach
        </div>
    @else
        <p class="sv-lead">No venues in {{ $state }} are on Haraan yet.</p>
    @endif

    @if($cities)
        <h2>Cities</h2>
        <div class="sv-cities">
            @foreach($cities as $c)
                @php $areas = array_slice(array_keys($c['stats']['areas']), 0, 3); @endphp
                <section class="sv-city">
                    <h3>
                        <a href="{{ url('/venues/'.$c['slug']) }}">{{ $c['name'] }}</a>
                        <small>{{ $c['stats']['total'] }} {{ \Illuminate\Support\Str::plural('venue', $c['stats']['total']) }}</small>
                    </h3>
                    @if($areas)<p>{{ implode(', ', $areas) }}{{ count($c['stats']['areas']) > 3 ? ' and more' : '' }}</p>@endif
                    <div class="sv-sports">
                        @foreach($c['stats']['sports'] as $slug => $n)
                            <a href="{{ url('/venues/'.$c['slug'].'/'.$slug) }}">{{ \App\Support\CityVenueGuide::SPORTS[$slug][0] }} · {{ $n }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    @if($faqs)
        <h2>Questions</h2>
        <div class="sv-faq">
            @foreach($faqs as $f)
                <details @if($loop->first) open @endif>
                    <summary>{{ $f['question'] }}</summary>
                    <p>{{ $f['answer'] }}</p>
                </details>
            @endforeach
        </div>
    @endif

    <p class="sv-owner">Run a turf or court in {{ $state }}? <a href="{{ route('site.partner.apply') }}">List it on Haraan</a> and take bookings online.</p>
</div>
@endsection
