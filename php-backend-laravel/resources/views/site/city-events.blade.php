@extends('site.layout')
@section('content')
@php
    // One card shape for both lists. Price and date mirror the events feed cards
    // (Event::fromPrice(), "27 Jun • 7:30 PM") so the same event reads the same everywhere.
    $card = static function ($ev): array {
        $from = $ev->fromPrice();

        return [
            'url' => \App\Support\SeoUrl::event($ev),
            'img' => $ev->heroImageUrl() ?? asset('images/haraan-social-card.png'),
            'title' => $ev->title,
            'when' => implode(' • ', array_filter([
                optional($ev->date)?->format('D, j M'),
                trim((string) $ev->time) ?: null,
            ])),
            'where' => trim((string) $ev->venue) ?: trim((string) $ev->location),
            'category' => trim((string) $ev->category),
            'price' => $from > 0
                ? '₹'.number_format($from).($ev->priceTierCount() > 1 ? ' onwards' : '')
                : 'Free',
        ];
    };
@endphp

<style>
    .ce { max-width: 1120px; margin: 0 auto; padding: 28px 16px 64px; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0F172A; }
    .ce-crumbs { display: flex; flex-wrap: wrap; gap: 6px; font-size: 13px; color: #64748B; margin-bottom: 18px; }
    .ce-crumbs a { color: #64748B; text-decoration: none; }
    .ce-crumbs a:hover { color: #2563EB; }
    .ce-head h1 { margin: 0; font-size: clamp(28px, 4.4vw, 42px); line-height: 1.1; font-weight: 800; letter-spacing: -0.02em; }
    .ce-head p { margin: 12px 0 0; max-width: 680px; font-size: 16px; line-height: 1.6; color: #475569; }
    .ce-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .ce-links a { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 999px; border: 1px solid #DBE3F0; background: #fff; color: #1E3A8A; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ce-links a:hover { border-color: #2563EB; color: #2563EB; }
    .ce h2 { margin: 40px 0 16px; font-size: 22px; font-weight: 800; letter-spacing: -0.01em; }
    .ce-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
    .ce-card { display: flex; flex-direction: column; border-radius: 16px; overflow: hidden; background: #fff; border: 1px solid #E2E8F0; text-decoration: none; color: inherit; transition: transform .18s ease, box-shadow .18s ease; }
    .ce-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, .10); }
    .ce-card__img { aspect-ratio: 4 / 5; width: 100%; object-fit: cover; background: #EEF2F7; display: block; }
    .ce-card__body { padding: 14px 14px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .ce-card__when { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #2563EB; }
    .ce-card__title { margin: 0; font-size: 16px; font-weight: 800; line-height: 1.3; }
    .ce-card__where { font-size: 13px; color: #64748B; }
    .ce-card__foot { margin-top: auto; padding-top: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
    .ce-card__price { font-weight: 800; }
    .ce-card__cat { font-size: 12px; color: #475569; background: #F1F5F9; padding: 3px 9px; border-radius: 999px; }
    .ce-card--past { opacity: .78; }
    .ce-card--past .ce-card__img { aspect-ratio: 16 / 10; filter: grayscale(.35); }
    .ce-empty { padding: 28px; border-radius: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; color: #475569; line-height: 1.6; }
    .ce-empty a { color: #2563EB; font-weight: 700; }
    .ce-faq details { border-bottom: 1px solid #E2E8F0; padding: 16px 0; }
    .ce-faq summary { cursor: pointer; font-weight: 700; font-size: 16px; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
    .ce-faq summary::-webkit-details-marker { display: none; }
    .ce-faq summary::after { content: '+'; color: #2563EB; font-weight: 800; }
    .ce-faq details[open] summary::after { content: '–'; }
    .ce-faq p { margin: 10px 0 0; color: #475569; line-height: 1.6; }
    @media (max-width: 560px) {
        .ce { padding-top: 18px; }
        .ce-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        .ce-card__body { padding: 10px; }
        .ce-card__title { font-size: 14px; }
        .ce-card__foot { flex-direction: column; align-items: flex-start; gap: 6px; }
    }
</style>

<main class="ce">
    <nav class="ce-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ url('/events') }}">Events</a><span aria-hidden="true">/</span>
        <span>{{ $cityName }}</span>
    </nav>

    <header class="ce-head">
        <h1>Events in {{ $cityName }}</h1>
        <p>
            @if($upcoming->isNotEmpty())
                {{ $upcoming->count() }} upcoming {{ \Illuminate\Support\Str::plural('event', $upcoming->count()) }} in {{ $cityName }} — {{ $catPhrase }} and more.
                See the date, venue and ticket price for each, then book online in seconds.
            @else
                Nothing is on sale in {{ $cityName }} right now. Here's what happened recently — new events are added as organisers publish them.
            @endif
        </p>
        <div class="ce-links">
            <a href="{{ url('/events') }}">All events</a>
            @if($hasVenues)
                <a href="{{ route('site.venues.city', ['city' => $citySlug]) }}">Turfs &amp; courts in {{ $cityName }}</a>
            @endif
            <a href="{{ url('/gamehub/actionboard') }}">Live match scores</a>
        </div>
    </header>

    @if($upcoming->isNotEmpty())
        <h2>Upcoming in {{ $cityName }}</h2>
        <div class="ce-grid">
            @foreach($upcoming as $ev)
                @php
                    $c = $card($ev);
                @endphp
                <a class="ce-card" href="{{ $c['url'] }}">
                    <img class="ce-card__img" src="{{ $c['img'] }}" alt="{{ $c['title'] }}" loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}">
                    <span class="ce-card__body">
                        @if($c['when'])<span class="ce-card__when">{{ $c['when'] }}</span>@endif
                        <h3 class="ce-card__title">{{ $c['title'] }}</h3>
                        @if($c['where'])<span class="ce-card__where">{{ $c['where'] }}</span>@endif
                        <span class="ce-card__foot">
                            <span class="ce-card__price">{{ $c['price'] }}</span>
                            @if($c['category'])<span class="ce-card__cat">{{ $c['category'] }}</span>@endif
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <div class="ce-empty" style="margin-top:32px">
            No upcoming events in {{ $cityName }} yet. <a href="{{ url('/events') }}">Browse events in other cities</a>.
        </div>
    @endif

    @if($past->isNotEmpty())
        <h2>Recently in {{ $cityName }}</h2>
        <div class="ce-grid">
            @foreach($past as $ev)
                @php
                    $c = $card($ev);
                @endphp
                <a class="ce-card ce-card--past" href="{{ $c['url'] }}">
                    <img class="ce-card__img" src="{{ $c['img'] }}" alt="{{ $c['title'] }}" loading="lazy">
                    <span class="ce-card__body">
                        @if($c['when'])<span class="ce-card__when">{{ $c['when'] }}</span>@endif
                        <h3 class="ce-card__title">{{ $c['title'] }}</h3>
                        @if($c['where'])<span class="ce-card__where">{{ $c['where'] }}</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <section class="ce-faq" aria-labelledby="ceFaq">
        <h2 id="ceFaq">Questions about events in {{ $cityName }}</h2>
        @foreach($faqs as $f)
            <details @if($loop->first) open @endif>
                <summary>{{ $f['question'] }}</summary>
                <p>{{ $f['answer'] }}</p>
            </details>
        @endforeach
    </section>
</main>
@endsection
