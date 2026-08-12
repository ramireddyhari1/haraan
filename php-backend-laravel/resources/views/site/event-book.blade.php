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
    .bk-coupon.is-applied input { border-color: #86EFAC; background: #F0FDF4; color: #15803D; font-weight: 700; }
    .bk-coupon.is-bad input { border-color: #FECACA; background: #FEF2F2; }
    .bk-coupon.is-bad input:focus { outline-color: rgba(220,38,38,0.3); border-color: #DC2626; }
    .bk-apply {
        flex-shrink: 0; border: 1px solid #CBD5E1; background: #fff; color: #0F172A; cursor: pointer;
        font: inherit; font-size: 13.5px; font-weight: 700; padding: 0 18px; border-radius: 12px;
    }
    .bk-apply:disabled { opacity: 0.55; cursor: default; }
    .bk-coupon.is-applied .bk-apply { color: #64748B; }
    .bk-note { font-size: 12px; color: #94A3B8; margin: 8px 2px 0; }
    .bk-note.ok { color: #15803D; font-weight: 600; }
    .bk-note.bad { color: #DC2626; font-weight: 600; }
    .bk-sum .bk-discount { color: #15803D; font-weight: 700; }
    /* .bk-line is display:flex, which would otherwise beat the UA rule for [hidden]. */
    .bk-sum .bk-line[hidden] { display: none; }
    /* The discount line and the new total are the whole point of Apply — make the
       change register instead of silently swapping numbers under the cursor. */
    .bk-pop { animation: bkPop 0.28s ease-out; }
    @keyframes bkPop { from { transform: scale(0.94); opacity: 0.4; } to { transform: scale(1); opacity: 1; } }
    .bk-shake { animation: bkShake 0.3s ease-in-out; }
    @keyframes bkShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    @media (prefers-reduced-motion: reduce) { .bk-pop, .bk-shake { animation: none; } }
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
    <a class="bk-back" id="bkBack" href="/events/{{ $event->id }}">
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
                {{-- `date` is date-only, so formatting its clock printed "12:00 AM"
                     on every booking review. The real time lives in `time`/`end_time`
                     (Event::timeRangeLabel), and is simply omitted when unset. --}}
                <small>{{ optional($event->date)->format('D, d M') }}@if($event->timeRangeLabel()) • {{ $event->timeRangeLabel() }}@endif@if($event->city) • {{ $event->city }}@endif</small>
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
            @foreach(($feeLines ?? []) as $line)
                <div class="bk-line"><span>{{ $line['label'] }}</span><span>₹{{ number_format($line['amount'], 2) }}</span></div>
            @endforeach
            <div class="bk-line bk-discount" id="bkDiscountLine" hidden>
                <span>Coupon <span id="bkDiscountCode"></span></span><span id="bkDiscountAmount"></span>
            </div>
            <div class="bk-total"><span>Total</span><span id="bkTotal">₹{{ number_format($total, 2) }}</span></div>
        </div>

        <div class="bk-card">
            <div class="bk-coupon" id="bkCoupon">
                <input type="text" id="bkCouponCode" name="couponCode" value="{{ old('couponCode') }}"
                       placeholder="Coupon code (optional)" autocomplete="off" maxlength="40">
                <button type="button" class="bk-apply" id="bkApply">Apply</button>
            </div>
            <p class="bk-note" id="bkCouponNote">A valid coupon is applied to the final amount when you confirm.</p>
        </div>

        <button type="submit" class="bk-cta">Confirm booking — ₹<span id="bkCtaAmount">{{ number_format($total, 2) }}</span></button>
    </form>
</div>

{{-- Coupon: Apply quotes the code against this exact cart and shows the discount and the
     new total right here, before the buyer commits to anything. The quote is display-only
     — the code still rides along in the form field and is re-resolved server-side on
     confirm (BookingService::resolveCoupon), which is what actually decides the charge.
     With JS off there is no Apply step: the typed code is simply applied on confirm, as
     the note says, which is how this page behaved before. --}}
<script>
    (function () {
        var wrap  = document.getElementById('bkCoupon');
        var input = document.getElementById('bkCouponCode');
        var btn   = document.getElementById('bkApply');
        var note  = document.getElementById('bkCouponNote');
        var form  = input && input.form;
        if (!wrap || !input || !btn || !form) return;

        var line      = document.getElementById('bkDiscountLine');
        var lineCode  = document.getElementById('bkDiscountCode');
        var lineAmt   = document.getElementById('bkDiscountAmount');
        var totalEl   = document.getElementById('bkTotal');
        var ctaAmount = document.getElementById('bkCtaAmount');
        var idleNote  = note.textContent;
        var total     = totalEl.textContent;
        var applied   = false;
        var busy      = false;

        function pop(el) {
            el.classList.remove('bk-pop');
            void el.offsetWidth;
            el.classList.add('bk-pop');
        }

        function say(text, kind) {
            note.textContent = text;
            note.className = 'bk-note' + (kind ? ' ' + kind : '');
        }

        function clear() {
            applied = false;
            wrap.className = 'bk-coupon';
            btn.textContent = 'Apply';
            btn.disabled = false;
            line.hidden = true;
            totalEl.textContent = total;
            ctaAmount.textContent = total.replace('₹', '');
            say(idleNote, '');
            input.focus();
        }

        function apply() {
            var code = input.value.trim();
            if (busy) return;
            if (code === '') {
                wrap.className = 'bk-coupon is-bad';
                pop(wrap);
                wrap.classList.add('bk-shake');
                say('Enter a coupon code.', 'bad');
                input.focus();
                return;
            }

            busy = true;
            btn.disabled = true;
            btn.textContent = 'Checking…';
            say('Checking this code…', '');

            var body = new FormData(form);
            body.set('couponCode', code);

            fetch(@json(route('site.booking.coupon', ['id' => $event->id])), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    busy = false;
                    btn.disabled = false;

                    if (!data || !data.applied) {
                        applied = false;
                        wrap.className = 'bk-coupon is-bad';
                        wrap.classList.add('bk-shake');
                        btn.textContent = 'Apply';
                        line.hidden = true;
                        totalEl.textContent = total;
                        ctaAmount.textContent = total.replace('₹', '');
                        say((data && data.message) || 'This code isn’t valid.', 'bad');
                        input.select();
                        return;
                    }

                    applied = true;
                    input.value = data.code;
                    wrap.className = 'bk-coupon is-applied';
                    btn.textContent = 'Remove';

                    lineCode.textContent = '(' + data.code + ')';
                    lineAmt.textContent = '− ₹' + data.discountLabel;
                    line.hidden = false;
                    totalEl.textContent = '₹' + data.totalLabel;
                    ctaAmount.textContent = data.totalLabel;

                    pop(line);
                    pop(totalEl);
                    say('Coupon applied — ₹' + data.discountLabel + ' off.', 'ok');
                })
                .catch(function () {
                    busy = false;
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                    say('Couldn’t check that code. It will still be applied when you confirm.', 'bad');
                });
        }

        wrap.addEventListener('animationend', function () { wrap.classList.remove('bk-shake'); });

        btn.addEventListener('click', function () {
            if (applied) { clear(); } else { apply(); }
        });

        // Enter inside the coupon field means "apply this code", not "confirm the booking".
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (!applied) apply();
        });

        // Editing an applied code drops back to the un-applied total rather than leaving a
        // stale discount on screen next to a code that no longer produced it.
        input.addEventListener('input', function () {
            if (applied) { clear(); return; }
            if (wrap.classList.contains('is-bad')) { wrap.className = 'bk-coupon'; say(idleNote, ''); }
        });

        // A code typed but never applied is still honoured on confirm; don't let the
        // Apply button read like a step they forgot.
        if (input.value.trim() !== '') apply();
    })();
</script>

{{-- "Back to event" must POP the event-detail entry the user came from, not push
     a fresh one. Pushing a second event-detail entry created a loop: Back-to-event
     → event detail, then the browser Back button → back HERE (Review booking).
     So: if we arrived straight from the event page, history.back() returns to that
     exact entry (Back again then goes to the events list). If we got here another
     way (reload / deep link / a validation-error redirect), replace the current
     entry with the event page so a review-booking entry is never left behind. --}}
<script>
    (function () {
        var back = document.getElementById('bkBack');
        if (!back) return;
        back.addEventListener('click', function (e) {
            var url = back.getAttribute('href');
            var ref = (document.referrer || '').replace(/[?#].*$/, '').replace(/\/+$/, '');
            e.preventDefault();
            if (ref && ref.endsWith(url) && window.history.length > 1) {
                window.history.back();
            } else {
                window.location.replace(url);
            }
        });
    })();
</script>
@endsection
