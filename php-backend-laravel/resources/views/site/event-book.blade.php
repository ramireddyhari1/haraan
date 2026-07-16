@extends('site.layout')

@section('body_class', 'theme-minimal booking-page')

@section('content')
<style>
    .bk-wrap { max-width: 520px; margin: 24px auto 60px; padding: 0 16px; }
    .bk-back { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 700; color: #64748B; text-decoration: none; margin-bottom: 14px; }
    .bk-title { margin: 0 0 18px; font-size: 24px; font-weight: 800; letter-spacing: -0.02em; color: #121620; }
    .bk-card { background: #ffffff; border: 1px solid #E2E8F0; border-radius: 20px; padding: 18px; margin-bottom: 14px; }
    .bk-event { display: flex; gap: 12px; align-items: center; }
    .bk-event img { width: 64px; height: 64px; border-radius: 14px; object-fit: cover; flex-shrink: 0; background: #121620; }
    .bk-event strong { display: block; font-size: 15px; font-weight: 800; color: #0F172A; line-height: 1.3; }
    .bk-event small { font-size: 12.5px; color: #64748B; }
    .bk-line { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #F1F5F9; font-size: 14px; color: #0F172A; }
    .bk-line:last-child { border-bottom: none; }
    .bk-line small { display: block; color: #94A3B8; font-size: 12px; }
    .bk-line .amt { font-weight: 700; white-space: nowrap; }
    .bk-sum { font-size: 13.5px; color: #64748B; }
    .bk-sum .bk-line { padding: 7px 0; border: none; font-size: 13.5px; color: inherit; }
    .bk-total { display: flex; justify-content: space-between; padding-top: 10px; margin-top: 6px; border-top: 1px solid #E2E8F0; font-size: 16px; font-weight: 800; color: #121620; }
    .bk-coupon { display: flex; gap: 8px; }
    .bk-coupon input {
        flex: 1; min-width: 0; border: 1px solid #E2E8F0; border-radius: 12px; padding: 11px 14px;
        font: inherit; font-size: 13.5px; text-transform: uppercase; letter-spacing: 0.04em;
    }
    .bk-coupon input:focus { outline: 2px solid rgba(37,99,235,0.35); border-color: #2563EB; }
    .bk-note { font-size: 12px; color: #94A3B8; margin: 8px 2px 0; }
    .bk-cta {
        display: block; width: 100%; border: none; cursor: pointer;
        background: #2563EB; color: #fff; font: inherit; font-size: 15.5px; font-weight: 700;
        padding: 15px 24px; border-radius: 16px; letter-spacing: -0.01em;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28); margin-top: 6px;
    }
    .bk-error { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; border-radius: 14px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 14px; }
    .bk-heading { margin: 22px 2px 10px; font-size: 15px; font-weight: 800; color: #121620; letter-spacing: -0.01em; }
    .bk-fields { display: flex; flex-direction: column; gap: 14px; }
    .bk-field { display: block; }
    .bk-field > span { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 700; color: #64748B; }
    .bk-field input {
        width: 100%; min-width: 0; border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px 14px;
        font: inherit; font-size: 14.5px; color: #0F172A; background: #fff;
    }
    .bk-field input:focus { outline: 2px solid rgba(37,99,235,0.35); border-color: #2563EB; }
    .bk-field em { display: block; margin-top: 6px; font-size: 12px; font-style: normal; font-weight: 600; color: #DC2626; }
    .bk-field input:user-invalid { border-color: #FECACA; }
</style>

<div class="bk-wrap">
    <a class="bk-back" href="/events/{{ $event->id }}">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        Back to event
    </a>
    <h1 class="bk-title">Review booking</h1>

    @if(session('error'))
        <div class="bk-error">{{ session('error') }}</div>
    @endif

    <div class="bk-card">
        <div class="bk-event">
            <img src="{{ $event->heroImageUrl() ?? asset('events.png') }}" alt="">
            <div>
                <strong>{{ $event->title }}</strong>
                <small>{{ optional($event->date)->format('D, d M') }} • {{ optional($event->date)->format('g:i A') }}@if($event->city) • {{ $event->city }}@endif</small>
            </div>
        </div>
    </div>

    <form method="POST" action="/events/{{ $event->id }}/book">
        @csrf

        <div class="bk-card">
            @foreach($lines as $line)
                <div class="bk-line">
                    <span>
                        {{ $line['name'] }} × {{ $line['quantity'] }}
                        <small>₹{{ number_format($line['unit'], 2) }} each</small>
                    </span>
                    <span class="amt">₹{{ number_format($line['amount'], 2) }}</span>
                </div>
                <input type="hidden" name="qty[{{ $line['ticketTypeId'] ?? 0 }}]" value="{{ $line['quantity'] }}">
            @endforeach
        </div>

        {{-- Personal information — who the ticket is for. Prefilled from the account
             (see ContactPrefill: a WhatsApp signup's <phone>@whatsapp.local placeholder
             is deliberately NOT offered as an email), so most people only fill the gap.
             Same fields, same order, same rules as the app's checkout. --}}
        <h2 class="bk-heading">Personal information</h2>
        <div class="bk-card bk-fields">
            <label class="bk-field">
                <span>Full name</span>
                <input type="text" name="contact[name]" value="{{ old('contact.name', $contact['name']) }}"
                       placeholder="Name on the ticket" autocomplete="name" required maxlength="120">
                @error('contact.name')<em>{{ $message }}</em>@enderror
            </label>
            <label class="bk-field">
                <span>Email</span>
                <input type="email" name="contact[email]" value="{{ old('contact.email', $contact['email']) }}"
                       placeholder="you@example.com" autocomplete="email" required maxlength="255">
                @error('contact.email')<em>{{ $message }}</em>@enderror
            </label>
            <label class="bk-field">
                <span>Phone</span>
                <input type="tel" name="contact[phone]" value="{{ old('contact.phone', $contact['phone']) }}"
                       placeholder="10-digit mobile number" autocomplete="tel" required maxlength="32">
                @error('contact.phone')<em>{{ $message }}</em>@enderror
            </label>
            <p class="bk-note">Your ticket and any updates about this event go to these details.</p>
        </div>

        <div class="bk-card bk-sum">
            <div class="bk-line"><span>Subtotal</span><span>₹{{ number_format($subtotal, 2) }}</span></div>
            @if($fee > 0)
                <div class="bk-line"><span>Convenience fee</span><span>₹{{ number_format($fee, 2) }}</span></div>
            @endif
            <div class="bk-total"><span>Total</span><span>₹{{ number_format($total, 2) }}</span></div>
        </div>

        <div class="bk-card">
            <div class="bk-coupon">
                <input type="text" name="couponCode" value="{{ old('couponCode') }}" placeholder="Coupon code (optional)" autocomplete="off">
            </div>
            <p class="bk-note">A valid coupon is applied to the final amount when you confirm.</p>
        </div>

        <button type="submit" class="bk-cta">Confirm booking — ₹{{ number_format($total, 2) }}</button>
    </form>
</div>
@endsection
