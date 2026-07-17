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
@endphp
<style>
    body.pass-page { background:
        radial-gradient(1200px 400px at 50% -120px, rgba(37,99,235,.10), transparent 70%),
        #EEF2F7; }
    .bp-wrap { max-width: 460px; margin: 0 auto 56px; padding: 0 16px; }

    /* Top bar — the site header is hidden on booking pages, so this is the way home. */
    .bp-top { display: flex; align-items: center; justify-content: space-between; padding: 14px 2px 10px; }
    .bp-back { display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
        color: #0F172A; font-weight: 700; font-size: 14.5px; background: #fff; border: 1px solid #E2E8F0;
        padding: 9px 14px 9px 11px; border-radius: 999px; box-shadow: 0 4px 14px rgba(15,23,42,.05); }
    .bp-back svg { width: 17px; height: 17px; }
    .bp-top__hint { font-size: 11.5px; color: #94A3B8; font-weight: 600; }

    .bp-ok { display: flex; align-items: center; gap: 10px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; border-radius: 14px; padding: 12px 14px; font-size: 13.5px; font-weight: 700; margin-bottom: 14px; }

    .bp-card { position: relative; background: #fff; border-radius: 22px; margin-bottom: 16px;
        box-shadow: 0 18px 50px rgba(15,23,42,.10); }
    .bp-brand { display: flex; align-items: center; justify-content: space-between; padding: 13px 18px;
        background: linear-gradient(120deg, #2563EB 0%, #12B76A 100%); color: #fff;
        border-radius: 22px 22px 0 0; }
    .bp-brand__mark { font-size: 17px; font-weight: 800; letter-spacing: .02em; }
    .bp-brand__tag { font-size: 10.5px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; opacity: .95; background: rgba(255,255,255,.20); padding: 4px 10px; border-radius: 999px; }

    .bp-head { display: flex; gap: 12px; align-items: center; padding: 16px 18px; }
    .bp-head img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; flex-shrink: 0; background: #121620; }
    .bp-head strong { display: block; font-size: 15.5px; font-weight: 800; color: #0F172A; line-height: 1.3; }
    .bp-head small { font-size: 12.5px; color: #64748B; }

    /* Perforated tear line with side notches — the classic ticket cut. */
    .bp-perf { position: relative; height: 22px; }
    .bp-perf::before { content: ""; position: absolute; left: 22px; right: 22px; top: 50%;
        border-top: 2px dashed #DBE2EA; }
    .bp-perf .notch { position: absolute; top: 50%; width: 22px; height: 22px; border-radius: 50%;
        background: #EEF2F7; transform: translateY(-50%); box-shadow: inset 0 1px 2px rgba(15,23,42,.06); }
    .bp-perf .notch.l { left: -11px; }
    .bp-perf .notch.r { right: -11px; }

    .bp-body { padding: 6px 18px 20px; text-align: center; }
    .bp-tier { display: inline-block; font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        color: #2563EB; background: rgba(37,99,235,.08); padding: 4px 12px; border-radius: 999px; margin-bottom: 14px; }
    .bp-qrframe { display: inline-block; padding: 12px; background: #fff; border: 1px solid #EEF2F7; border-radius: 16px;
        box-shadow: 0 6px 18px rgba(15,23,42,.06); }
    .bp-qr { display: grid; place-items: center; }
    .bp-qr canvas, .bp-qr img { border-radius: 6px; display: block; }
    .bp-code { font-size: 18px; font-weight: 800; letter-spacing: .14em; color: #121620; font-variant-numeric: tabular-nums; margin-top: 14px; }
    .bp-qty { font-size: 12.5px; color: #64748B; margin-top: 4px; }
    .bp-scan { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; font-size: 11.5px; color: #94A3B8; font-weight: 600; }
    .bp-scan svg { width: 14px; height: 14px; }

    /* Quick actions */
    .bp-quick { display: flex; gap: 8px; margin: 0 0 12px; }
    .bp-quick a, .bp-quick button { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px;
        background: #fff; border: 1px solid #E7ECF3; border-radius: 14px; padding: 12px 6px; cursor: pointer;
        text-decoration: none; color: #334155; font-size: 11.5px; font-weight: 700; font-family: inherit; }
    .bp-quick svg { width: 19px; height: 19px; color: #2563EB; }
    .bp-quick a:active, .bp-quick button:active { transform: scale(.98); }

    .bp-actions { display: flex; gap: 10px; }
    .bp-actions a { flex: 1; text-align: center; text-decoration: none; font-size: 14px; font-weight: 700; padding: 13px 16px; border-radius: 14px; }
    .bp-actions .primary { background: #2563EB; color: #fff; box-shadow: 0 8px 20px rgba(37,99,235,.28); }
    .bp-actions .ghost { background: #fff; color: #0F172A; border: 1px solid #E2E8F0; }
</style>

<div class="bp-wrap">
    <div class="bp-top">
        <a class="bp-back" href="/">
            <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Home
        </a>
        <span class="bp-top__hint">Saved in Tickets</span>
    </div>

    @if(session('success'))
        <div class="bp-ok">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }} Your ticket{{ $group->count() > 1 ? 's are' : ' is' }} below.
        </div>
    @endif

    @foreach($group as $pass)
        <div class="bp-card">
            <div class="bp-brand">
                <span class="bp-brand__mark">Haraan</span>
                <span class="bp-brand__tag">e-Ticket</span>
            </div>
            <div class="bp-head">
                <img src="{{ $event->heroImageUrl() ?? asset('events.png') }}" alt="">
                <div>
                    <strong>{{ $event->title }}</strong>
                    <small>{{ optional($event->date)->format('D, d M') }} • {{ optional($event->date)->format('g:i A') }}@if($event->venue) • {{ \Illuminate\Support\Str::before($event->venue, ',') }}@endif</small>
                </div>
            </div>
            <div class="bp-perf"><span class="notch l"></span><span class="notch r"></span></div>
            <div class="bp-body">
                <div class="bp-tier">{{ $pass->ticketType->name ?? 'Standard' }}</div>
                <div class="bp-qrframe">
                    <div class="bp-qr" data-code="haraan:ticket:{{ $pass->ticket_code }}"></div>
                </div>
                <div class="bp-code">{{ $pass->ticket_code }}</div>
                <div class="bp-qty">{{ $pass->quantity }} {{ $pass->quantity > 1 ? 'guests' : 'guest' }} on this pass</div>
                <div class="bp-scan">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg>
                    Turn up your brightness for a faster scan
                </div>
            </div>
        </div>
    @endforeach

    <div class="bp-quick">
        @if($mapsUrl)
        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Directions
        </a>
        @endif
        @if($calUrl)
        <a href="{{ $calUrl }}" target="_blank" rel="noopener">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Add to calendar
        </a>
        @endif
        <button type="button" id="bpShare"
            data-title="{{ $event->title }}"
            data-text="My Haraan ticket for {{ $event->title }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
            Share
        </button>
    </div>

    <div class="bp-actions">
        <a class="ghost" href="/events/{{ $event->id }}">Event details</a>
        <a class="primary" href="/profile">My tickets</a>
    </div>
</div>

{{-- Self-hosted so the pass never depends on a third-party CDN loading (the old
     jsdelivr path 404'd, leaving the pass with no QR). --}}
<script src="{{ asset('js/qrcode.min.js') }}"></script>
<script>
    document.querySelectorAll('.bp-qr').forEach(function (el) {
        // Payload contract shared with the app + host scanner: haraan:ticket:<code>
        new QRCode(el, {
            text: el.dataset.code,
            width: 190,
            height: 190,
            colorDark: '#0F172A',
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
            this.lastChild.textContent = ' Link copied';
        } catch (e) { /* user cancelled — no-op */ }
    });
</script>
@endsection
