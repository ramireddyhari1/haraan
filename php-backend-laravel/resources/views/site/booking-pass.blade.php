@extends('site.layout')

@section('body_class', 'theme-minimal booking-page pass-page')

@section('content')
@php
    // Quick-action deep links (external). Directions = maps search on venue+city;
    // Add-to-calendar = a Google Calendar template with a sensible 3h window.
    $locParts   = array_filter([$event->venue, $event->city]);
    $mapsQuery  = urlencode(trim(implode(', ', $locParts)));
    $mapsUrl    = $mapsQuery !== '' ? "https://www.google.com/maps/search/?api=1&query={$mapsQuery}" : null;
    $calStart   = optional($event->date)?->clone()->utc()->format('Ymd\THis\Z');
    $calEnd     = optional($event->date)?->clone()->addHours(3)->utc()->format('Ymd\THis\Z');
    $calUrl     = $calStart
        ? 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . urlencode($event->title)
            . "&dates={$calStart}/{$calEnd}"
            . '&location=' . $mapsQuery
            . '&details=' . urlencode('Your Haraan ticket · code ' . $booking->ticket_code)
        : null;

    // Hero facts. Date and time are separate columns: "when is it" and "what time"
    // are two different questions and a run-on line answers neither well.
    $passDate   = optional($event->date)?->format('D, d M Y');
    $passTime   = $event->timeRangeLabel();
    $venueName  = trim((string) \Illuminate\Support\Str::before((string) $event->venue, ','));
    $venueArea  = trim(implode(', ', array_filter([$event->venueArea(), $event->city])));
    $org        = $event->organiserCard();
    $isLive     = strtoupper((string) $booking->status) === 'CONFIRMED';
    // Built here, not inline: a directive glued to a word ("entry@if") is left
    // uncompiled by Blade and the stray @endif then breaks the whole view.
    $validLine  = 'Valid for one entry' . ($passDate ? ' · ' . $passDate : '');
@endphp
<style>
    /* ------------------------------------------------------------------
       Entry pass. One elevation system, one 8pt spacing scale, one accent.
       Left-aligned by default — the QR is the only centred thing on the
       page, because it is the only thing a scanner has to find.
       ------------------------------------------------------------------ */
    body.pass-page {
        background: #F5F7FA;
        --p-blue: #0057FF;
        --p-green: #16C47F;
        --p-ink: #111827;
        --p-mute: #6B7280;
        --p-line: #E5E7EB;
        --p-card: #FFFFFF;
        --p-sh-sm: 0 4px 12px rgba(0,0,0,.06);
        --p-sh-lg: 0 12px 32px rgba(0,0,0,.08);
        --p-font: -apple-system, 'SF Pro Text', 'Segoe UI', Inter, Roboto, Helvetica, Arial, sans-serif;
    }
    .pass-page .bp-wrap {
        max-width: 448px; margin: 0 auto 48px; padding: 0 16px;
        font-family: var(--p-font); color: var(--p-ink);
        -webkit-font-smoothing: antialiased;
    }

    /* --- Navigation ------------------------------------------------- */
    .bp-nav { display: flex; align-items: center; justify-content: space-between; height: 56px; }
    .bp-nav__back {
        display: inline-flex; align-items: center; gap: 6px; margin-left: -6px; padding: 6px;
        text-decoration: none; color: var(--p-ink); font-size: 15px; font-weight: 600;
    }
    .bp-nav__back svg { width: 18px; height: 18px; stroke-width: 2.25; }
    .bp-nav__meta { font-size: 12px; font-weight: 500; color: var(--p-mute); }

    /* --- Success bar ------------------------------------------------- */
    .bp-ok {
        display: flex; align-items: flex-start; gap: 10px; margin-bottom: 16px; padding: 12px 14px;
        background: rgba(22,196,127,.08); border: 1px solid rgba(22,196,127,.28); border-left-width: 3px;
        border-radius: 10px; font-size: 13px; line-height: 1.5; font-weight: 500; color: #0B7C55;
    }
    .bp-ok svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }

    /* --- Event hero (boarding-pass header, not a marketing banner) ---- */
    .bp-hero {
        background: var(--p-card); border: 1px solid var(--p-line); border-radius: 14px;
        box-shadow: var(--p-sh-sm); padding: 16px; margin-bottom: 16px;
    }
    .bp-hero__top { display: flex; gap: 14px; align-items: flex-start; }
    .bp-hero__thumb {
        width: 64px; height: 64px; flex: none; border-radius: 8px; object-fit: cover;
        background: #E5E7EB; border: 1px solid rgba(17,24,39,.06);
    }
    .bp-hero__id { min-width: 0; flex: 1; }
    .bp-hero__status {
        display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px;
        font-size: 12px; font-weight: 600; letter-spacing: .02em; color: #0B7C55;
    }
    .bp-hero__status i { width: 6px; height: 6px; border-radius: 50%; background: var(--p-green); font-style: normal; }
    .bp-hero__status.is-off { color: var(--p-mute); }
    .bp-hero__status.is-off i { background: #9CA3AF; }
    .bp-hero h1 {
        margin: 0; font-size: 20px; line-height: 1.25; font-weight: 700; letter-spacing: -.015em;
        color: var(--p-ink);
    }
    /* Facts sit on a 2-col grid so date and time line up down the page. */
    .bp-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
    .bp-facts--full { grid-template-columns: 1fr; }
    .bp-fact__k {
        font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase;
        color: var(--p-mute);
    }
    .bp-fact__v { font-size: 15px; font-weight: 600; line-height: 1.35; margin-top: 3px; }
    .bp-fact__v small { display: block; font-size: 13px; font-weight: 400; color: var(--p-mute); margin-top: 1px; }
    .bp-hero__org {
        display: flex; align-items: center; gap: 8px; margin-top: 16px; padding-top: 12px;
        border-top: 1px solid var(--p-line); font-size: 12px; color: var(--p-mute);
    }
    .bp-hero__org img, .bp-hero__org span.ava {
        width: 20px; height: 20px; border-radius: 50%; object-fit: cover; flex: none;
        display: grid; place-items: center; background: var(--p-blue); color: #fff;
        font-size: 10px; font-weight: 700;
    }

    /* --- The pass ---------------------------------------------------- */
    .bp-pass {
        position: relative; background: var(--p-card); border: 1px solid var(--p-line);
        border-radius: 14px; box-shadow: var(--p-sh-lg); margin-bottom: 16px; overflow: hidden;
        /* Paper: a hairline weave at 2% so the stub reads as stock, not screen. */
        background-image:
            repeating-linear-gradient(45deg, rgba(17,24,39,.014) 0 1px, transparent 1px 4px),
            repeating-linear-gradient(-45deg, rgba(17,24,39,.010) 0 1px, transparent 1px 5px);
    }
    .bp-pass__bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 16px; border-bottom: 1px solid var(--p-line);
    }
    .bp-pass__brand { display: flex; align-items: baseline; gap: 8px; }
    .bp-pass__brand b { font-size: 14px; font-weight: 700; letter-spacing: -.01em; }
    .bp-pass__brand span {
        font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
        color: var(--p-mute);
    }
    .bp-pass__serial {
        font-size: 11px; font-weight: 500; color: #9CA3AF;
        font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .bp-pass__meta { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 16px; }
    .bp-badge {
        display: inline-block; padding: 4px 8px; border-radius: 6px; background: rgba(0,87,255,.07);
        color: var(--p-blue); font-size: 12px; font-weight: 700; letter-spacing: .02em;
    }
    .bp-pass__meta .bp-fact__v { font-size: 14px; }
    .bp-right { text-align: right; }

    /* Perforation — notches punch through to the page colour. */
    .bp-tear { position: relative; height: 20px; }
    .bp-tear::before {
        content: ""; position: absolute; left: 16px; right: 16px; top: 50%;
        border-top: 1px dashed #D1D5DB;
    }
    .bp-tear i {
        position: absolute; top: 50%; width: 20px; height: 20px; border-radius: 50%;
        background: #F5F7FA; transform: translateY(-50%); border: 1px solid var(--p-line);
    }
    .bp-tear i.l { left: -11px; border-right-color: transparent; }
    .bp-tear i.r { right: -11px; border-left-color: transparent; }

    /* QR: the one deliberately centred block on the page. */
    .bp-scan { padding: 24px 16px 16px; text-align: center; }
    .bp-qrframe {
        display: inline-block; padding: 16px; background: #fff;
        border: 2px solid var(--p-line); border-radius: 12px;
    }
    /* The QR's RESTING state is visible. The fade is layered on top with no
       backwards fill and no delay, so anything that stops the animation from
       running — reduced motion, a throttled/backgrounded renderer, an old
       engine — leaves the code on screen instead of an empty frame. An earlier
       version started at opacity 0 and waited for JS to reveal it; a ticket
       must never be one missed hook away from blank at the gate. */
    .bp-qr { display: block; }
    .bp-qr canvas, .bp-qr img { display: block; }
    .bp-code {
        margin-top: 16px; font-size: 16px; font-weight: 700; letter-spacing: .16em;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-all;
    }
    .bp-code__k {
        font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase;
        color: var(--p-mute); margin-top: 16px;
    }
    .bp-valid {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 12px 16px; border-top: 1px solid var(--p-line);
        font-size: 12px; color: var(--p-mute);
    }
    /* Security strip: a thin foil band, the one place any gradient is allowed. */
    .bp-foil {
        height: 6px;
        background: linear-gradient(90deg, #E5E7EB 0%, #C7D2FE 18%, #E5E7EB 34%, #BBF7D0 52%, #E5E7EB 68%, #C7D2FE 86%, #E5E7EB 100%);
        opacity: .85;
    }
    /* Watermark — barely there, clipped by the card. */
    .bp-mark {
        position: absolute; right: -14px; bottom: 44px; font-size: 120px; font-weight: 800;
        line-height: 1; color: rgba(17,24,39,.028); pointer-events: none; user-select: none;
    }

    /* --- Actions ----------------------------------------------------- */
    .bp-primary {
        display: block; width: 100%; box-sizing: border-box; text-align: center; text-decoration: none;
        background: var(--p-blue); color: #fff; font-size: 15px; font-weight: 600;
        padding: 14px 16px; border-radius: 10px; box-shadow: var(--p-sh-sm);
    }
    .bp-secondary {
        margin-top: 16px; background: var(--p-card); border: 1px solid var(--p-line);
        border-radius: 12px; overflow: hidden;
    }
    .bp-row {
        display: flex; align-items: center; gap: 12px; width: 100%; box-sizing: border-box;
        padding: 14px 16px; background: none; border: 0; border-top: 1px solid var(--p-line);
        font-family: inherit; font-size: 15px; font-weight: 500; color: var(--p-ink);
        text-align: left; text-decoration: none; cursor: pointer;
    }
    .bp-row:first-child { border-top: 0; }
    .bp-row__ic { width: 20px; height: 20px; flex: none; color: var(--p-mute); }
    .bp-row__ch { width: 16px; height: 16px; margin-left: auto; flex: none; color: #C4C9D2; }
    .bp-foot {
        margin-top: 24px; font-size: 12px; line-height: 1.6; color: #9CA3AF;
    }
    .bp-foot a { color: var(--p-mute); }

    /* --- Motion ------------------------------------------------------ */
    @keyframes bpRise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    /* Chrome fades in; the TICKET never does. An animation frozen at its first
       frame — a throttled renderer, a paused compositor — holds whatever that
       frame says, so anything carrying opacity can land on 0 and stay there.
       The pass and the QR are therefore transform-only: worst case they sit 8px
       low, never invisible. Nobody misses a gate over a micro-interaction. */
    .bp-hero, .bp-primary, .bp-secondary { animation: bpRise 250ms cubic-bezier(.2,.7,.2,1); }
    .bp-pass { animation: bpLift 250ms cubic-bezier(.2,.7,.2,1); }
    @keyframes bpLift { from { transform: translateY(8px); } to { transform: none; } }
    .bp-primary:active, .bp-row:active { transform: scale(.98); }
    .bp-primary, .bp-row { transition: transform 150ms cubic-bezier(.2,.7,.2,1); }
    @media (prefers-reduced-motion: reduce) {
        .bp-hero, .bp-pass, .bp-primary, .bp-secondary { animation: none; transition: none; opacity: 1; }
    }
</style>

<div class="bp-wrap">
    <nav class="bp-nav">
        <a class="bp-nav__back" href="/">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Home
        </a>
        <span class="bp-nav__meta">Saved in Tickets</span>
    </nav>

    @if(session('success'))
        <div class="bp-ok">
            <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ session('success') }} Your ticket{{ $group->count() > 1 ? 's are' : ' is' }} below.</span>
        </div>
    @endif

    {{-- Event hero — rendered ONCE. The old page repeated the whole event
         header above every pass, so a two-ticket order read as two separate
         events. --}}
    <section class="bp-hero">
        <div class="bp-hero__top">
            <img class="bp-hero__thumb" src="{{ $event->heroImageUrl() ?? asset('events.png') }}" alt="">
            <div class="bp-hero__id">
                <div class="bp-hero__status {{ $isLive ? '' : 'is-off' }}">
                    <i></i>{{ $isLive ? 'Confirmed' : ucfirst(strtolower((string) $booking->status)) }}
                </div>
                <h1>{{ $event->title }}</h1>
            </div>
        </div>

        <div class="bp-facts">
            @if($passDate)
            <div>
                <div class="bp-fact__k">Date</div>
                <div class="bp-fact__v">{{ $passDate }}</div>
            </div>
            @endif
            @if($passTime)
            <div>
                <div class="bp-fact__k">Time</div>
                <div class="bp-fact__v">{{ $passTime }}</div>
            </div>
            @endif
        </div>

        @if($venueName !== '')
        <div class="bp-facts bp-facts--full">
            <div>
                <div class="bp-fact__k">Venue</div>
                <div class="bp-fact__v">
                    {{ $venueName }}
                    @if($venueArea !== '')<small>{{ $venueArea }}</small>@endif
                </div>
            </div>
        </div>
        @endif

        <div class="bp-hero__org">
            @if($org['logo'])
                <img src="{{ $org['logo'] }}" alt="">
            @else
                <span class="ava">{{ $org['initial'] }}</span>
            @endif
            Hosted by {{ $org['name'] }}
        </div>
    </section>

    @foreach($group as $i => $pass)
        <section class="bp-pass">
            <div class="bp-mark" aria-hidden="true">H</div>

            <div class="bp-pass__bar">
                <div class="bp-pass__brand">
                    <b>Haraan</b>
                    <span>Entry pass{{ $group->count() > 1 ? ' · ' . ($i + 1) . '/' . $group->count() : '' }}</span>
                </div>
                <div class="bp-pass__serial">No. {{ str_pad((string) $pass->id, 6, '0', STR_PAD_LEFT) }}</div>
            </div>

            <div class="bp-pass__meta">
                <div>
                    <div class="bp-fact__k">Ticket</div>
                    <div style="margin-top:5px;"><span class="bp-badge">{{ $pass->ticketType->name ?? 'Standard' }}</span></div>
                </div>
                <div class="bp-right">
                    <div class="bp-fact__k">Admits</div>
                    <div class="bp-fact__v">{{ $pass->quantity }} {{ $pass->quantity > 1 ? 'guests' : 'guest' }}</div>
                </div>
            </div>

            <div class="bp-tear"><i class="l"></i><i class="r"></i></div>

            <div class="bp-scan">
                <div class="bp-qrframe">
                    {{-- Payload contract: haraan:ticket:<code>. Never add to it. --}}
                    <div class="bp-qr" data-code="haraan:ticket:{{ $pass->ticket_code }}"></div>
                </div>
                <div class="bp-code__k">Pass number</div>
                <div class="bp-code">{{ $pass->ticket_code }}</div>
            </div>

            <div class="bp-valid">
                <span>{{ $validLine }}</span>
                <span>Non-transferable</span>
            </div>
            <div class="bp-foil" aria-hidden="true"></div>
        </section>
    @endforeach

    <a class="bp-primary" href="/profile">My tickets</a>

    {{-- Secondary information: rows, not four equally-weighted tiles. Nothing
         here should compete with the QR. --}}
    <div class="bp-secondary">
        <a class="bp-row" href="/events/{{ $event->id }}">
            <svg class="bp-row__ic" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
            Event details
            <svg class="bp-row__ch" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        @if($mapsUrl)
        <a class="bp-row" href="{{ $mapsUrl }}" target="_blank" rel="noopener">
            <svg class="bp-row__ic" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Directions
            <svg class="bp-row__ch" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        @endif
        @if($calUrl)
        <a class="bp-row" href="{{ $calUrl }}" target="_blank" rel="noopener">
            <svg class="bp-row__ic" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Add to calendar
            <svg class="bp-row__ch" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        @endif
        <button class="bp-row" type="button" id="bpShare"
            data-title="{{ $event->title }}"
            data-text="My Haraan ticket for {{ $event->title }}">
            <svg class="bp-row__ic" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
            <span id="bpShareLabel">Share</span>
            <svg class="bp-row__ch" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>

    <p class="bp-foot">
        Show the QR at the entry gate — screen brightness up helps the scanner.
        Booked on Haraan · <a href="{{ url('/support') }}">Need help?</a>
    </p>
</div>

{{-- Self-hosted so the pass never depends on a third-party CDN loading (the old
     jsdelivr path 404'd, leaving the pass with no QR). --}}
<script src="{{ asset('js/qrcode.min.js') }}"></script>
<script>
    document.querySelectorAll('.bp-qr').forEach(function (el) {
        // Payload contract shared with the app + host scanner: haraan:ticket:<code>
        new QRCode(el, {
            text: el.dataset.code,
            width: 208,
            height: 208,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    });

    // Share: native share sheet where available, else copy the pass link.
    document.getElementById('bpShare')?.addEventListener('click', async function () {
        const data = { title: this.dataset.title, text: this.dataset.text, url: window.location.href };
        try {
            if (navigator.share) { await navigator.share(data); return; }
            await navigator.clipboard.writeText(window.location.href);
            document.getElementById('bpShareLabel').textContent = 'Link copied';
        } catch (e) { /* user cancelled — no-op */ }
    });
</script>
@endsection
