@extends('site.layout')

@section('body_class', 'theme-minimal booking-page')

@section('content')
<style>
    .bp-wrap { max-width: 460px; margin: 24px auto 60px; padding: 0 16px; }
    .bp-ok { display: flex; align-items: center; gap: 10px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; border-radius: 14px; padding: 12px 14px; font-size: 13.5px; font-weight: 700; margin-bottom: 16px; }
    .bp-card { background: #ffffff; border: 1px solid #E2E8F0; border-radius: 22px; overflow: hidden; margin-bottom: 14px; }
    .bp-head { display: flex; gap: 12px; align-items: center; padding: 16px 18px; border-bottom: 1px dashed #E2E8F0; }
    .bp-head img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; flex-shrink: 0; background: #121620; }
    .bp-head strong { display: block; font-size: 15px; font-weight: 800; color: #0F172A; line-height: 1.3; }
    .bp-head small { font-size: 12.5px; color: #64748B; }
    .bp-body { padding: 18px; text-align: center; }
    .bp-tier { font-size: 12px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #2563EB; margin-bottom: 10px; }
    .bp-qr { display: grid; place-items: center; margin: 0 auto 12px; }
    .bp-qr canvas { border-radius: 8px; }
    .bp-code { font-size: 18px; font-weight: 800; letter-spacing: 0.14em; color: #121620; font-variant-numeric: tabular-nums; }
    .bp-qty { font-size: 12.5px; color: #64748B; margin-top: 4px; }
    .bp-hint { font-size: 12px; color: #94A3B8; text-align: center; margin: 14px 4px 0; line-height: 1.5; }
    .bp-actions { display: flex; gap: 10px; margin-top: 16px; }
    .bp-actions a {
        flex: 1; text-align: center; text-decoration: none; font-size: 14px; font-weight: 700;
        padding: 13px 16px; border-radius: 14px;
    }
    .bp-actions .primary { background: #2563EB; color: #fff; box-shadow: 0 8px 20px rgba(37,99,235,0.28); }
    .bp-actions .ghost { background: #F4F7FB; color: #0F172A; border: 1px solid #E2E8F0; }
</style>

<div class="bp-wrap">
    @if(session('success'))
        <div class="bp-ok">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }} Your ticket{{ $group->count() > 1 ? 's are' : ' is' }} below.
        </div>
    @endif

    @foreach($group as $pass)
        <div class="bp-card">
            <div class="bp-head">
                <img src="{{ $event->heroImageUrl() ?? asset('events.png') }}" alt="">
                <div>
                    <strong>{{ $event->title }}</strong>
                    <small>{{ optional($event->date)->format('D, d M') }} • {{ optional($event->date)->format('g:i A') }}@if($event->venue) • {{ \Illuminate\Support\Str::before($event->venue, ',') }}@endif</small>
                </div>
            </div>
            <div class="bp-body">
                <div class="bp-tier">{{ $pass->ticketType->name ?? 'Standard' }}</div>
                <div class="bp-qr" data-code="haraan:ticket:{{ $pass->ticket_code }}"></div>
                <div class="bp-code">{{ $pass->ticket_code }}</div>
                <div class="bp-qty">{{ $pass->quantity }} {{ $pass->quantity > 1 ? 'guests' : 'guest' }} on this pass</div>
            </div>
        </div>
    @endforeach

    <p class="bp-hint">Show the QR (or the code) at the gate. Keep this page — it's also in your account under Tickets.</p>

    <div class="bp-actions">
        <a class="ghost" href="/events/{{ $event->id }}">Event details</a>
        <a class="primary" href="/profile">My tickets</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script>
    document.querySelectorAll('.bp-qr').forEach(function (el) {
        // Payload contract shared with the app + host scanner: haraan:ticket:<code>
        QRCode.toCanvas(el.dataset.code, { width: 190, margin: 1 }, function (err, canvas) {
            if (!err) el.appendChild(canvas);
        });
    });
</script>
@endsection
