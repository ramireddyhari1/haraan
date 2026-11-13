@extends('site.layout')
@section('body_class', 'vn-page')
@section('content')
{{--
    "Turfs & courts near {area}, {city}" — NearVenuesController. Every venue within
    CityVenueGuide::NEAR_KM of the area, nearest first, with its real distance.
--}}
<style>
    .vn-page main.container, .vn-page .container { max-width: none; }
    .vn {
        --ink: #0F172A; --ink-2: #334155; --ink-3: #64748B; --rule: #E3E8EF; --wash: #F6F8FB; --blue: #2563EB;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink);
        max-width: 960px; margin: 0 auto; padding: 0 20px 72px; font-variant-numeric: tabular-nums;
    }
    .vn a { color: inherit; }
    .vn-crumbs { display: flex; flex-wrap: wrap; gap: 6px; font-size: 13px; color: var(--ink-3); padding: 28px 0 16px; }
    .vn-crumbs a { text-decoration: none; }
    .vn-crumbs a:hover { color: var(--blue); }
    .vn h1 { margin: 0; font-size: 36px; line-height: 1.12; font-weight: 800; letter-spacing: -0.025em; text-wrap: balance; }
    .vn-lead { margin: 12px 0 0; max-width: 700px; font-size: 16.5px; line-height: 1.6; color: var(--ink-2); }
    .vn h2 { margin: 0 0 14px; font-size: 20px; font-weight: 800; letter-spacing: -0.015em; }
    .vn-sec { margin-top: 40px; }

    .vn-list { border-top: 2px solid var(--ink); }
    .vn-row {
        display: grid; grid-template-columns: 76px minmax(0, 1fr) auto; gap: 16px; align-items: center;
        padding: 15px 0; border-bottom: 1px solid var(--rule); text-decoration: none;
    }
    .vn-row:hover b { color: var(--blue); }
    .vn-km { font-size: 17px; font-weight: 800; }
    .vn-km small { display: block; font-size: 11.5px; font-weight: 600; color: var(--ink-3); }
    .vn-row b { display: block; font-size: 16px; font-weight: 700; color: var(--ink); }
    .vn-row .vn-meta { display: block; margin-top: 2px; font-size: 13.5px; color: var(--ink-3); }
    .vn-tag { font-size: 12.5px; font-weight: 700; padding: 5px 10px; border-radius: 999px; white-space: nowrap; background: #F1F5F9; color: var(--ink-2); }
    .vn-tag--book { background: #EEF4FF; color: #1E40AF; }

    .vn-faq details { border-bottom: 1px solid var(--rule); }
    .vn-faq { border-top: 2px solid var(--ink); }
    .vn-faq summary { cursor: pointer; padding: 14px 0; font-size: 15.5px; font-weight: 700; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
    .vn-faq summary::-webkit-details-marker { display: none; }
    .vn-faq summary::after { content: '+'; color: var(--ink-3); }
    .vn-faq details[open] summary::after { content: '–'; }
    .vn-faq p { margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: var(--ink-2); }

    .vn-links { display: flex; flex-wrap: wrap; gap: 8px; }
    .vn-links a { font-size: 14px; font-weight: 600; text-decoration: none; padding: 7px 12px; border-radius: 999px; background: #EEF4FF; color: #1E40AF; }
    .vn-links a:hover { background: #DCE7FE; }
    .vn-links a small { font-weight: 600; color: #64748B; margin-left: 4px; }

    @media (max-width: 600px) {
        .vn { padding: 0 16px 56px; }
        .vn h1 { font-size: 28px; }
        .vn-row { grid-template-columns: 58px minmax(0, 1fr); }
        .vn-tag { grid-column: 2; justify-self: start; }
    }
</style>

<div class="vn">
    <nav class="vn-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('site.gamehub') }}">Pulse</a><span aria-hidden="true">/</span>
        <a href="{{ url('/venues/'.$citySlug) }}">{{ $cityName }}</a><span aria-hidden="true">/</span>
        <span>Near {{ $page['name'] }}</span>
    </nav>

    <h1>{{ $copy['h1'] }}</h1>
    @if($copy['lead'] !== '')
        <p class="vn-lead">{{ $copy['lead'] }}</p>
    @endif

    <div class="vn-sec">
        <h2>Within {{ (int) \App\Support\CityVenueGuide::NEAR_KM }} km of {{ $page['name'] }}, nearest first</h2>
        <div class="vn-list">
            @foreach($page['venues'] as $v)
                @php $hours = \App\Support\CityVenueGuide::hoursLabel($v); @endphp
                <a class="vn-row" href="{{ $v['url'] }}">
                    <span class="vn-km">{{ \App\Support\CityVenueGuide::distanceLabel($v['km']) }}<small>away</small></span>
                    <span style="min-width:0">
                        <b>{{ $v['name'] }}</b>
                        <span class="vn-meta">{{ implode(' · ', array_filter([$v['area'], implode(', ', $v['sports']), $hours])) }}</span>
                    </span>
                    @if($v['bookable'])
                        <span class="vn-tag vn-tag--book">{{ $v['price'] > 0 ? 'Book from ₹'.number_format($v['price']).'/hr' : 'Book online' }}</span>
                    @else
                        <span class="vn-tag">Ask to book</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    @if(!empty($copy['faqs']))
        <div class="vn-sec">
            <h2>Playing near {{ $page['name'] }}</h2>
            <div class="vn-faq">
                @foreach($copy['faqs'] as $f)
                    <details @if($loop->first) open @endif>
                        <summary>{{ $f['question'] }}</summary>
                        <p>{{ $f['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($others))
        <nav class="vn-sec" aria-label="Other areas of {{ $cityName }}">
            <h2>Other areas of {{ $cityName }}</h2>
            <div class="vn-links">
                @foreach($others as $o)
                    <a href="{{ $o['url'] }}">Near {{ $o['name'] }}<small>{{ $o['count'] }}</small></a>
                @endforeach
            </div>
        </nav>
    @endif

    <nav class="vn-sec" aria-label="More in {{ $cityName }}">
        <h2>More in {{ $cityName }}</h2>
        <div class="vn-links">
            <a href="{{ url('/venues/'.$citySlug) }}">All turfs & courts in {{ $cityName }}</a>
            @foreach($sportLinks as $l)
                <a href="{{ $l['url'] }}">{{ $l['label'] }}</a>
            @endforeach
        </div>
    </nav>
</div>
@endsection
