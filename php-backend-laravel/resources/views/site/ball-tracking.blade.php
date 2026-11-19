@extends('site.layout')

{{-- haraan.app/ball-tracking — every word comes from /control → Platform → Ball tracking page
     (BallTrackingController). Kept editorial on purpose: numbered rows with hairlines, not a
     grid of boxed feature tiles. --}}
@push('head')
<style>
    .btk { max-width: 980px; margin: 0 auto; padding: 28px 16px 56px; color: #0F172A; }
    .btk__eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #2563EB; margin: 0 0 10px; }
    .btk__hero { display: grid; grid-template-columns: 1fr; gap: 24px; align-items: center; }
    .btk h1 { font-size: clamp(30px, 6vw, 46px); line-height: 1.08; letter-spacing: -.02em; margin: 0 0 14px; font-weight: 800; }
    .btk__intro { font-size: 17px; line-height: 1.6; color: #334155; margin: 0 0 22px; max-width: 54ch; }
    .btk__play { display: inline-flex; align-items: center; gap: 10px; background: #0F172A; color: #fff; text-decoration: none; padding: 12px 18px; border-radius: 10px; font-weight: 700; font-size: 15px; }
    .btk__play:hover { background: #1E293B; }
    .btk__play svg { width: 20px; height: 20px; }
    .btk__fig { margin: 0; border-radius: 14px; background: #fff; border: 1px solid #E2E8F0; padding: 14px; }
    .btk__fig svg { display: block; width: 100%; height: auto; }
    .btk__fig figcaption { font-size: 12.5px; color: #64748B; margin-top: 8px; }
    .btk h2 { font-size: 24px; letter-spacing: -.01em; margin: 48px 0 6px; font-weight: 800; }
    .btk__list { list-style: none; margin: 0; padding: 0; counter-reset: btk; }
    .btk__list li { counter-increment: btk; display: grid; grid-template-columns: 34px 1fr; gap: 4px 12px; padding: 16px 0; border-bottom: 1px solid #E2E8F0; }
    .btk__list li::before { content: counter(btk, decimal-leading-zero); font-size: 13px; font-weight: 700; color: #94A3B8; padding-top: 3px; font-variant-numeric: tabular-nums; }
    .btk__list h3 { margin: 0; font-size: 17px; font-weight: 700; }
    .btk__list p { grid-column: 2; margin: 0; color: #475569; line-height: 1.55; font-size: 15px; }
    .btk__steps { list-style: none; margin: 14px 0 0; padding: 0; display: grid; grid-template-columns: 1fr; gap: 0; }
    .btk__steps li { padding: 14px 0 14px 18px; border-left: 2px solid #2563EB; margin-left: 4px; }
    .btk__steps li + li { border-left-color: #BFDBFE; }
    .btk__steps b { display: block; font-size: 16px; margin-bottom: 2px; }
    .btk__steps span { color: #475569; font-size: 15px; line-height: 1.5; }
    .btk__why { margin: 14px 0 0; display: grid; grid-template-columns: 1fr; gap: 18px 32px; }
    .btk__why dt { font-weight: 700; font-size: 16px; margin-bottom: 3px; }
    .btk__why dt::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2563EB; margin: 0 9px 2px 0; }
    .btk__why dd { margin: 0 0 0 17px; color: #475569; font-size: 15px; line-height: 1.55; }
    @media (min-width: 820px) { .btk__why { grid-template-columns: repeat(2, 1fr); } }
    .btk__faq details { border-bottom: 1px solid #E2E8F0; padding: 14px 0; }
    .btk__faq summary { cursor: pointer; font-weight: 700; font-size: 16px; list-style: none; display: flex; justify-content: space-between; gap: 12px; }
    .btk__faq summary::-webkit-details-marker { display: none; }
    .btk__faq summary::after { content: '+'; color: #64748B; font-weight: 400; font-size: 20px; line-height: 1; }
    .btk__faq details[open] summary::after { content: '−'; }
    .btk__faq p { margin: 8px 0 0; color: #475569; line-height: 1.6; font-size: 15px; }
    .btk__links { margin-top: 40px; font-size: 14.5px; color: #475569; line-height: 1.7; }
    .btk__links a { color: #1D4ED8; font-weight: 600; }
    @media (min-width: 820px) {
        .btk { padding-top: 48px; }
        .btk__hero { grid-template-columns: 1.05fr 1fr; gap: 40px; }
        .btk__steps { grid-template-columns: repeat(2, 1fr); column-gap: 32px; }
    }
</style>
@endpush

@section('content')
<article class="btk">
    <header class="btk__hero">
        <div>
            <p class="btk__eyebrow">Cricket ball tracking app</p>
            <h1>{{ $heading }}</h1>
            <p class="btk__intro">{{ $intro }}</p>
            <a class="btk__play" href="{{ $playUrl }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#00E3A5" d="M4.1 1.9 14.6 12 4.1 22.1c-.4-.2-.7-.7-.7-1.3V3.2c0-.6.3-1.1.7-1.3z"/>
                    <path fill="#3DDCFF" d="M17.7 9.1 5.6 2l9 8.7z"/>
                    <path fill="#FFC933" d="m14.6 12 3.1-2.9 3 1.7c1 .6 1 1.9 0 2.4l-3 1.7z"/>
                    <path fill="#FF5C6C" d="m14.6 12-9 10 12.1-7.1z"/>
                </svg>
                {{ $ctaLabel }}
            </a>
        </div>

        {{-- A side-on diagram of what the camera reads: release, bounce, and the path carried
             on to the stumps. A diagram, not a fake screenshot with numbers on it. --}}
        <figure class="btk__fig">
            <svg viewBox="0 0 440 200" role="img" aria-label="Side-on view of a cricket pitch: the ball's path from the bowler's hand, bouncing on the pitch, carried on to the stumps">
                <rect x="20" y="150" width="400" height="14" rx="2" fill="#D9C7A0"/>
                <line x1="60" y1="150" x2="60" y2="164" stroke="#fff" stroke-width="2"/>
                <line x1="380" y1="150" x2="380" y2="164" stroke="#fff" stroke-width="2"/>
                {{-- bowler's-end stumps --}}
                <g stroke="#78350F" stroke-width="3" stroke-linecap="round"><line x1="44" y1="150" x2="44" y2="112"/><line x1="49" y1="150" x2="49" y2="112"/></g>
                {{-- batter's-end stumps --}}
                <g stroke="#78350F" stroke-width="3" stroke-linecap="round"><line x1="392" y1="150" x2="392" y2="112"/><line x1="397" y1="150" x2="397" y2="112"/></g>
                {{-- tracked flight: release → bounce --}}
                <path d="M70 56 Q 190 70 282 150" fill="none" stroke="#2563EB" stroke-width="3"/>
                {{-- after the bounce, up to the pad, then projected on --}}
                <path d="M282 150 Q 318 122 352 120" fill="none" stroke="#2563EB" stroke-width="3"/>
                <path d="M352 120 L 396 124" fill="none" stroke="#2563EB" stroke-width="3" stroke-dasharray="5 5"/>
                <circle cx="70" cy="56" r="5" fill="#B91C1C"/>
                <circle cx="282" cy="150" r="5" fill="#fff" stroke="#2563EB" stroke-width="2.5"/>
                <circle cx="352" cy="120" r="5" fill="#B91C1C"/>
                <g font-family="inherit" font-size="11" fill="#334155">
                    <text x="62" y="44">Release</text>
                    <text x="258" y="182">Pitched</text>
                    <text x="318" y="102">Impact</text>
                    <text x="370" y="96">Projected</text>
                </g>
            </svg>
            <figcaption>What one phone side-on to the pitch reads from each ball.</figcaption>
        </figure>
    </header>

    @if($features !== [])
        <section aria-labelledby="btk-what">
            <h2 id="btk-what">What Haraan ball tracking shows you</h2>
            <ol class="btk__list">
                @foreach($features as $f)
                    <li><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></li>
                @endforeach
            </ol>
        </section>
    @endif

    @if($why !== [])
        <section aria-labelledby="btk-why">
            <h2 id="btk-why">{{ $whyTitle }}</h2>
            <dl class="btk__why">
                @foreach($why as $w)
                    <div><dt>{{ $w['title'] }}</dt><dd>{{ $w['text'] }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    @if($steps !== [])
        <section aria-labelledby="btk-how">
            <h2 id="btk-how">How it works</h2>
            <ol class="btk__steps">
                @foreach($steps as $s)
                    <li><b>{{ $s['title'] }}</b><span>{{ $s['text'] }}</span></li>
                @endforeach
            </ol>
        </section>
    @endif

    @if($faqs !== [])
        <section class="btk__faq" aria-labelledby="btk-faq">
            <h2 id="btk-faq">Questions</h2>
            @foreach($faqs as $i => $q)
                <details @if($i === 0) open @endif>
                    <summary>{{ $q['title'] }}</summary>
                    <p>{{ $q['text'] }}</p>
                </details>
            @endforeach
        </section>
    @endif

    <p class="btk__links">
        Ball tracking is part of the Haraan app, alongside
        <a href="{{ url('/gamehub/actionboard') }}">live cricket scoring on ActionBoard</a>,
        <a href="{{ url('/gamehub') }}">turf and court booking</a> and the
        <a href="{{ url('/gamehub/leaderboard') }}">player leaderboard</a>.
    </p>
</article>
@endsection
