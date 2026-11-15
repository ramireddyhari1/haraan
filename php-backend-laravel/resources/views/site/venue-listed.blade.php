@extends('site.layout')
@section('body_class', 'lv-page')
@section('content')
{{--
    A venue Haraan lists whose owner has not joined (Venue::STATE_LISTED). Only what is
    known is shown — no price, rating or slots, because there are none; photos are the
    venue's own Google listing photos, credited — and the
    page is plain about booking being off. See ListedVenueController.
--}}
<style>
    .lv-page main.container, .lv-page .container { max-width: none; }
    /* The site's phone rule pads main by 88px (!important) for pages without a footer;
       this page ends in .mfoot, so that padding was a blank slate band above it. */
    .lv-page main.container { padding-bottom: 0 !important; }
    .lv {
        --ink: #0F172A; --ink-2: #334155; --ink-3: #64748B; --rule: #E3E8EF; --paper: #FFFFFF;
        --wash: #F6F8FB; --blue: #2563EB; --blue-tint: #EEF4FF; --amber: #92400E; --amber-tint: #FEF6E7;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink);
        max-width: 1040px; margin: 0 auto; padding: 0 20px 72px; font-variant-numeric: tabular-nums;
    }
    .lv a { color: inherit; }
    .lv-crumbs { display: flex; flex-wrap: wrap; gap: 6px; font-size: 13px; color: var(--ink-3); padding: 28px 0 16px; }
    .lv-crumbs a { text-decoration: none; }
    .lv-crumbs a:hover { color: var(--blue); }
    .lv h1 { margin: 0; font-size: 38px; line-height: 1.1; font-weight: 800; letter-spacing: -0.025em; text-wrap: balance; }
    .lv-sub { margin: 10px 0 0; font-size: 16.5px; color: var(--ink-2); }
    .lv-status { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin: 16px 0 0; }
    .lv-state {
        display: inline-flex; align-items: center; gap: 8px; padding: 6px 11px; border-radius: 8px;
        background: var(--amber-tint); color: var(--amber); font-size: 13px; font-weight: 700;
    }
    .lv-state::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #D97706; }
    .lv-status .lv-open { display: inline-flex; align-items: center; gap: 7px; margin: 0; font-size: 14px; }
    .lv-status .lv-open::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

    /* What you can do here, from what is known: call, directions, ask, share. */
    .lv-acts { display: flex; gap: 8px; margin-top: 20px; overflow-x: auto; scrollbar-width: none; }
    .lv-acts::-webkit-scrollbar { display: none; }
    .lv-act {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 16px;
        border: 1px solid #CFD8E3; border-radius: 999px; background: var(--paper); color: var(--ink) !important;
        font: inherit; font-size: 14.5px; font-weight: 700; text-decoration: none; cursor: pointer; white-space: nowrap;
        transition: border-color .12s, background .12s;
    }
    .lv-act svg { width: 18px; height: 18px; flex: none; color: var(--blue); }
    .lv-act:hover { border-color: var(--blue); background: var(--blue-tint); }
    .lv-act--main { background: var(--blue); border-color: var(--blue); color: #fff !important; }
    .lv-act--main svg { color: #fff; }
    .lv-act--main:hover { background: #1F5BE0; border-color: #1F5BE0; }

    .lv-photos { display: flex; gap: 10px; margin-top: 26px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: thin; border-radius: 14px; }
    .lv-photos figure { position: relative; flex: 0 0 calc((100% - 20px) / 3); margin: 0; aspect-ratio: 4 / 3; border-radius: 14px; overflow: hidden; background: #E9EEF4; scroll-snap-align: start; }
    .lv-photos--1 figure { flex-basis: 100%; aspect-ratio: 21 / 9; }
    .lv-photos--2 figure { flex-basis: calc((100% - 10px) / 2); }
    .lv-photos img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lv-photos figcaption {
        position: absolute; left: 8px; bottom: 8px; max-width: calc(100% - 16px); padding: 3px 8px; border-radius: 6px;
        background: rgba(15, 23, 42, .72); color: #fff; font-size: 11.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lv-photos figcaption a { color: #fff; text-decoration: none; }
    .lv-photos__src { margin: 8px 0 0; font-size: 12.5px; color: var(--ink-3); }

    .lv-grid { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 40px; align-items: start; margin-top: 32px; }

    .lv h2 { margin: 0 0 14px; font-size: 20px; font-weight: 800; letter-spacing: -0.015em; }
    .lv-facts { margin: 0; border-top: 2px solid var(--ink); }
    .lv-facts div { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 16px; padding: 14px 0; border-bottom: 1px solid var(--rule); }
    .lv-facts dt { font-size: 13.5px; font-weight: 700; color: var(--ink-3); }
    .lv-facts dd { margin: 0; font-size: 15.5px; line-height: 1.55; color: var(--ink); }
    .lv-facts dd a { color: var(--blue); font-weight: 700; text-decoration: none; }
    .lv-facts dd a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .lv-open { display: block; margin-bottom: 4px; font-weight: 700; color: #B45309; }
    .lv-open.is-open { color: #15803D; }
    .lv-week { color: var(--ink-2); }
    .lv-about { margin: 18px 0 0; font-size: 15.5px; line-height: 1.65; color: var(--ink-2); }
    .lv-map { margin-top: 22px; }
    .lv-map iframe { display: block; width: 100%; height: 240px; border: 0; border-radius: 14px; background: #E9EEF4; }
    .lv-map a { display: inline-block; margin-top: 10px; font-size: 14px; font-weight: 700; color: var(--blue); text-decoration: none; }
    .lv-map a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .lv-note { margin: 14px 0 0; font-size: 13.5px; line-height: 1.6; color: var(--ink-3); }

    .lv-card { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; padding: 22px; position: sticky; top: 88px; }
    .lv-card h2 { font-size: 19px; margin-bottom: 6px; }
    .lv-card > p { margin: 0 0 16px; font-size: 14.5px; line-height: 1.55; color: var(--ink-2); }
    .lv-field { display: block; margin-bottom: 12px; }
    .lv-field span { display: block; font-size: 13px; font-weight: 700; color: var(--ink-2); margin-bottom: 6px; }
    .lv-field input, .lv-field select {
        width: 100%; height: 46px; padding: 0 13px; border: 1px solid #CBD5E1; border-radius: 10px; background: #fff;
        font: inherit; font-size: 15.5px; color: var(--ink); box-sizing: border-box;
    }
    .lv-field input:focus, .lv-field select:focus { outline: 2px solid var(--blue); outline-offset: 1px; border-color: var(--blue); }
    .lv-two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .lv-btn {
        display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 48px; padding: 0 20px; border: 0;
        border-radius: 12px; background: var(--blue); color: #fff !important; font: inherit; font-size: 15.5px; font-weight: 700;
        text-decoration: none; cursor: pointer;
        box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 2px 0 #1D4ED8, 0 6px 14px -6px rgba(37, 99, 235, .55);
        transition: transform .08s, box-shadow .08s, background .15s;
    }
    .lv-btn:hover { background: #1F5BE0; }
    .lv-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 0 0 #1D4ED8; }
    .lv-btn[disabled] { opacity: .6; cursor: default; }
    .lv-btn--auto { width: auto; }
    .lv-err { margin: 0 0 12px; font-size: 13.5px; font-weight: 600; color: #B91C1C; }
    .lv-small { margin: 12px 0 0; font-size: 12.5px; line-height: 1.55; color: var(--ink-3); }
    .lv-done h2 { margin-bottom: 8px; }
    .lv-done p { margin: 0; font-size: 15px; line-height: 1.6; color: var(--ink-2); }

    .lv-sec { margin-top: 44px; }
    .lv-owner {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: center;
        margin-top: 44px; padding: 22px 24px; border-radius: 14px; background: var(--paper); border: 1px solid var(--rule);
        border-left: 3px solid var(--blue);
    }
    .lv-owner h2 { margin-bottom: 6px; font-size: 18px; }
    .lv-owner p { margin: 0; font-size: 15px; line-height: 1.6; color: var(--ink-2); max-width: 600px; }

    .lv-list { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; overflow: hidden; }
    .lv-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 14px 18px; text-decoration: none; border-top: 1px solid var(--rule); transition: background .12s; }
    .lv-row:first-child { border-top: 0; }
    .lv-row:hover { background: var(--wash); }
    .lv-row b { display: block; font-size: 15.5px; font-weight: 700; }
    .lv-row span { font-size: 13px; color: var(--ink-3); }
    .lv-row--pic { grid-template-columns: 56px minmax(0, 1fr) auto; }
    .lv-row__pic { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; background: #E9EEF4; display: block; }
    .lv-row em { font-style: normal; font-size: 14px; font-weight: 700; color: var(--blue); white-space: nowrap; }

    .lv-faq { border-top: 2px solid var(--ink); padding-top: 0; }
    .lv-faq h2 { margin: 16px 0 6px; }
    .lv-faq details { border-bottom: 1px solid var(--rule); }
    .lv-faq summary { cursor: pointer; padding: 14px 0; font-size: 15.5px; font-weight: 700; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
    .lv-faq summary::-webkit-details-marker { display: none; }
    .lv-faq summary::after { content: '+'; color: var(--ink-3); font-weight: 600; }
    .lv-faq details[open] summary::after { content: '–'; }
    .lv-faq p { margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: var(--ink-2); }
    .lv-links h2 { margin-bottom: 12px; }
    .lv-links div { display: flex; flex-wrap: wrap; gap: 8px; }
    .lv-links a { font-size: 14px; font-weight: 600; text-decoration: none; padding: 7px 12px; border-radius: 999px; background: #EEF4FF; color: #1E40AF; }
    .lv-links a:hover { background: #DCE7FE; }

    @media (max-width: 900px) {
        .lv-grid { grid-template-columns: minmax(0, 1fr); gap: 28px; }
        .lv-card { position: static; }
    }
    @media (max-width: 600px) {
        .lv { padding: 0 16px 40px; }
        .lv-crumbs { padding: 16px 0 12px; font-size: 12.5px; }
        .lv-crumbs > :nth-child(-n+2) { display: none; }   /* "Home /" — the header logo is home */
        .lv h1 { font-size: 28px; }
        .lv-sub { font-size: 15px; margin-top: 8px; }
        .lv-acts { margin: 18px -16px 0; padding: 0 16px; }
        .lv-photos { margin-top: 20px; }
        .lv-map iframe { height: 200px; }
        .lv-sec, .lv-owner { margin-top: 36px; }
        .lv-links div { flex-wrap: nowrap; overflow-x: auto; margin: 0 -16px; padding: 0 16px; scrollbar-width: none; }
        .lv-links div::-webkit-scrollbar { display: none; }
        .lv-links a { flex: none; white-space: nowrap; }
        .lv-row { padding: 13px 14px; }
        .lv-facts div { grid-template-columns: 96px minmax(0, 1fr); gap: 12px; }
        .lv-photos { margin-right: -16px; padding-right: 16px; border-radius: 0; }
        .lv-photos figure, .lv-photos--2 figure { flex-basis: 82%; }
        .lv-photos--1 figure { flex-basis: 100%; aspect-ratio: 4 / 3; }
        .lv-owner { grid-template-columns: 1fr; gap: 14px; padding: 18px; }
        .lv-owner p { font-size: 14.5px; }
        .lv-owner .lv-btn { height: 44px; justify-self: start; }
    }
</style>

<div class="lv">
    <nav class="lv-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('site.gamehub') }}">Pulse</a><span aria-hidden="true">/</span>
        @if($citySlug)
            <a href="{{ url('/venues/'.$citySlug) }}">{{ $city }}</a><span aria-hidden="true">/</span>
        @endif
        <span>{{ $venue->name }}</span>
    </nav>

    <h1>{{ $venue->name }}</h1>
    @if($sports || $where)
        <p class="lv-sub">{{ implode(' · ', array_filter([implode(', ', $sports), $where])) }}</p>
    @endif
    <div class="lv-status">
        <span class="lv-state">No online booking yet</span>
        @if($openNow)
            <span class="lv-open {{ $openNow['open'] ? 'is-open' : '' }}">{{ $openNow['text'] }}</span>
        @endif
    </div>

    @php($lvTel = $venue->contact_phone ? preg_replace('/[^\d+]/', '', $venue->contact_phone) : null)
    <div class="lv-acts">
        @if($lvTel)
            <a class="lv-act lv-act--main" href="tel:{{ $lvTel }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg> Call venue</a>
        @endif
        @if($map)
            <a class="lv-act" href="{{ $map['directions'] }}" target="_blank" rel="noopener nofollow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg> Directions</a>
        @endif
        @if($requestsOpen)
            <a class="lv-act {{ $lvTel ? '' : 'lv-act--main' }}" href="#lv-ask"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg> Ask to book</a>
        @endif
        <button class="lv-act" type="button" id="lv-share"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> <span>Share</span></button>
    </div>

    @if($photos)
        {{-- From the venue's own Google Maps listing, proxied (VenuePhotoController::listed).
             Google requires each photographer's name beside their photo — keep the credits. --}}
        <div class="lv-photos lv-photos--{{ min(count($photos), 3) }}" id="lv-photos">
            @foreach($photos as $p)
                <figure>
                    <img src="{{ route('site.gamehub.placephoto', ['id' => $venue->id, 'index' => $p['index']]) }}"
                         alt="{{ $venue->name }}{{ $where ? ', '.$where : '' }} — photo {{ $loop->iteration }}"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}" onerror="this.closest('figure').remove()">
                    @if($p['credit'])
                        <figcaption>Photo: @if($p['credit_uri'])<a href="{{ $p['credit_uri'] }}" target="_blank" rel="noopener nofollow">{{ $p['credit'] }}</a>@else{{ $p['credit'] }}@endif</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
        <p class="lv-photos__src">Photos from this venue’s Google Maps listing.</p>
    @endif

    <div class="lv-grid">
        <div>
            <h2>About this venue</h2>
            <dl class="lv-facts">
                @if($sports)
                    <div><dt>Sports</dt><dd>{{ implode(', ', $sports) }}</dd></div>
                @endif
                @if($venue->address)
                    <div><dt>Address</dt><dd>{{ $venue->address }}</dd></div>
                @endif
                @if($lvTel)
                    <div><dt>Phone</dt><dd><a href="tel:{{ $lvTel }}">{{ $venue->contact_phone }}</a></dd></div>
                @endif
                @if($hoursLine)
                    <div><dt>Hours</dt><dd><span class="lv-week">{!! implode('<br>', array_map('e', explode(' · ', $hoursLine))) !!}</span></dd></div>
                @endif
                <div><dt>Booking</dt><dd>{{ $lvTel ? 'Call the venue to book.' : 'Not open for online booking yet.' }}@if($requestsOpen) Or ask on Haraan and we’ll tell the owner you want to play here.@endif</dd></div>
            </dl>
            @if($venue->about)
                <p class="lv-about">{{ $venue->about }}</p>
            @endif
            @if($map)
                <div class="lv-map">
                    @if($map['embed'])
                        <iframe src="{{ $map['embed'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map of {{ $venue->name }}"></iframe>
                    @endif
                    <a href="{{ $map['directions'] }}" target="_blank" rel="noopener nofollow">Open in Google Maps →</a>
                </div>
            @endif
            <p class="lv-note">Haraan has not visited or verified this venue. Details come from public listings and may be out of date — call before you go.</p>
        </div>

        @if($requestsOpen)
            <aside class="lv-card" id="lv-ask">
                <form id="lv-form" novalidate>
                    <h2>Ask to book here</h2>
                    <p>
                        @if($requests >= 3)
                            {{ $requests }} players have asked. Add your name and we’ll message you when this venue opens for booking.
                        @else
                            Leave your number. We’ll tell the owner, and message you when this venue opens for booking.
                        @endif
                    </p>
                    <label class="lv-field"><span>Your name</span>
                        <input type="text" name="name" autocomplete="name" maxlength="60" value="{{ $prefill['name'] }}" required>
                    </label>
                    <label class="lv-field"><span>Mobile number</span>
                        <input type="tel" name="phone" inputmode="numeric" autocomplete="tel" maxlength="14" placeholder="10-digit number" value="{{ $prefill['phone'] }}" required>
                    </label>
                    <div class="lv-two">
                        @if(count($sports) > 1)
                            <label class="lv-field"><span>Sport</span>
                                <select name="sport">
                                    <option value="">Any</option>
                                    @foreach($sports as $s)<option>{{ $s }}</option>@endforeach
                                </select>
                            </label>
                        @endif
                        <label class="lv-field" @if(count($sports) <= 1) style="grid-column:1 / -1" @endif><span>When you play</span>
                            <select name="preferred_time">
                                <option value="">Any time</option>
                                @foreach($times as $t)<option>{{ $t }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <p class="lv-err" id="lv-err" hidden></p>
                    <button class="lv-btn" type="submit">Ask to book</button>
                    <p class="lv-small">Your number is used only to tell you about this venue. It is not shown on the site.</p>
                </form>
                <div class="lv-done" id="lv-done" hidden>
                    <h2>Got it</h2>
                    <p>We’ll message you when {{ $venue->name }} opens for booking on Haraan.@if($bookable->isNotEmpty()) Until then, the grounds below take bookings today.@endif</p>
                </div>
            </aside>
        @endif
    </div>

    @if($activity)
        @include('site.partials.venue-activity', ['activity' => $activity, 'venueName' => $venue->name])
    @endif

    @if(!empty($faqs))
        {{-- The same questions are the page's FAQPage markup (ListedVenueController::faqs). --}}
        <div class="lv-sec lv-faq">
            <h2>Questions players ask</h2>
            @foreach($faqs as $f)
                <details @if($loop->first) open @endif>
                    <summary>{{ $f['question'] }}</summary>
                    <p>{{ $f['answer'] }}</p>
                </details>
            @endforeach
        </div>
    @endif

    <div class="lv-owner">
        <div>
            <h2>Own {{ $venue->name }}?</h2>
            <p>Take bookings online, set your own prices and hours, and keep walk-ins in the same calendar.@if($requests > 0) {{ $requests }} {{ $requests === 1 ? 'player has' : 'players have' }} asked to book here.@endif A person from Haraan sets it up with you.</p>
        </div>
        <a class="lv-btn lv-btn--auto" href="{{ route('site.partner.apply') }}">Claim this venue</a>
    </div>

    @if($bookable->isNotEmpty())
        <div class="lv-sec">
            <h2>Book today in {{ $city }}</h2>
            <div class="lv-list">
                @foreach($bookable as $b)
                    <a class="lv-row {{ $b->image ? 'lv-row--pic' : '' }}" href="{{ \App\Support\SeoUrl::venue($b) }}">
                        @if($b->image)<img class="lv-row__pic" src="{{ $b->image }}" alt="" loading="lazy" onerror="this.parentNode.classList.remove('lv-row--pic');this.remove()">@endif
                        <div><b>{{ $b->name }}</b><span>{{ implode(' · ', array_filter([$b->location, implode(', ', array_slice($b->sportsList(), 0, 3))])) }}</span></div>
                        <em>See slots →</em>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($cityLinks))
        <nav class="lv-sec lv-links" aria-label="More in {{ $city }}">
            <h2>Explore {{ $city }}</h2>
            <div>
                @foreach($cityLinks as $l)
                    <a href="{{ $l['url'] }}">{{ $l['label'] }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    @if($nearby->isNotEmpty())
        <div class="lv-sec">
            <h2>{{ isset($nearby->first()->km) ? 'Near '.$venue->name : 'More places to play in '.$city }}</h2>
            <div class="lv-list">
                @foreach($nearby as $n)
                    <a class="lv-row" href="{{ $n->url }}">
                        <div><b>{{ $n->name }}</b><span>{{ implode(' · ', array_filter([$n->area, implode(', ', $n->sports)])) ?: $city }}</span></div>
                        <em>{{ $n->km ?? 'View' }}</em>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    var b = document.getElementById('lv-share');
    if (!b) return;
    var label = b.querySelector('span');
    b.addEventListener('click', function () {
        var data = { title: document.title, url: location.href.split('#')[0] };
        if (navigator.share) { navigator.share(data).catch(function () {}); return; }
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(data.url).then(function () {
            label.textContent = 'Link copied';
            setTimeout(function () { label.textContent = 'Share'; }, 1800);
        }, function () {});
    });
})();
</script>
@if($requestsOpen)
<script>
(function () {
    var form = document.getElementById('lv-form');
    if (!form) return;
    var err = document.getElementById('lv-err');
    var btn = form.querySelector('button');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.hidden = true;
        var body = {};
        new FormData(form).forEach(function (v, k) { body[k] = v; });
        if (!body.name || body.name.trim().length < 2) { return fail('Enter your name.'); }
        if (!/^(\+?91)?[6-9]\d{9}$/.test(String(body.phone).replace(/[\s-]/g, ''))) { return fail('Enter a 10-digit mobile number.'); }

        btn.disabled = true;
        btn.textContent = 'Sending…';
        fetch(@json(route('site.gamehub.interest', $venue->id)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; });
        }).then(function (res) {
            if (res.ok) {
                form.hidden = true;
                document.getElementById('lv-done').hidden = false;
                return;
            }
            fail(res.status === 429 ? 'Too many tries. Wait a minute and try again.' : (res.j.message || 'That didn’t go through. Try again.'));
        }).catch(function () { fail('No connection. Check your internet and try again.'); });
    });

    function fail(message) {
        err.textContent = message;
        err.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Ask to book';
    }
})();
</script>
@endif
@endsection
