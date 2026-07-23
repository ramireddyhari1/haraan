@extends('site.layout')

@section('body_class', 'theme-minimal booking-page')

@section('content')
<style>
    .pay-wrap { max-width: 480px; margin: 40px auto 60px; padding: 0 16px; text-align: center; }
    .pay-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 20px; padding: 28px 22px; }
    .pay-card h1 { margin: 0 0 6px; font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: #0F172A; }
    .pay-card p.sub { margin: 0 0 18px; font-size: 14px; color: #64748B; }
    .pay-event { display: flex; gap: 12px; align-items: center; text-align: left; border: 1px solid #E2E8F0; border-radius: 14px; padding: 12px; margin-bottom: 18px; }
    .pay-event img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; background: #121620; flex-shrink: 0; }
    .pay-event strong { display: block; font-size: 14.5px; font-weight: 800; color: #0F172A; }
    .pay-event small { font-size: 12.5px; color: #64748B; }
    .pay-amount { font-size: 30px; font-weight: 800; color: #0F172A; margin: 4px 0 20px; letter-spacing: -0.02em; }
    .pay-btn { width: 100%; border: 0; border-radius: 12px; padding: 15px; font-size: 16px; font-weight: 800; color: #fff; background: #2563EB; cursor: pointer; transition: background .15s, opacity .15s; }
    .pay-btn:hover { background: #1D4ED8; }
    .pay-btn:disabled { opacity: .6; cursor: progress; }
    .pay-msg { margin-top: 14px; font-size: 14px; font-weight: 700; min-height: 20px; }
    .pay-msg.err { color: #DC2626; }
    .pay-msg.ok { color: #12B76A; }
    .pay-note { margin-top: 16px; font-size: 12px; color: #94A3B8; }
</style>

<div class="pay-wrap">
    <div class="pay-card">
        <h1>Complete your payment</h1>
        <p class="sub">Your seats are held while you pay.</p>

        <div class="pay-event">
            @if($event->image)<img src="{{ $event->image }}" alt="">@endif
            <div>
                <strong>{{ $event->title }}</strong>
                <small>Ticket for {{ $contact['name'] ?? '' }}</small>
            </div>
        </div>

        <div class="pay-amount">₹{{ $amountLabel }}</div>

        <button class="pay-btn" id="payBtn">Pay ₹{{ $amountLabel }}</button>
        <div class="pay-msg" id="payMsg" role="status" aria-live="polite"></div>
        <p class="pay-note">Secured by Razorpay. Cancelling releases your held seats.</p>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    (function () {
        const CSRF = '{{ csrf_token() }}';
        const ORDER_ID = @json($orderId);
        const cfg = {
            key: @json($razorKey),
            amount: @json($amount),
            currency: @json($currency),
            orderId: ORDER_ID,
            confirmUrl: @json(route('site.booking.confirm', ['id' => $event->id])),
            releaseUrl: @json(route('site.booking.release', ['id' => $event->id])),
            passUrl: @json($passUrl),
            eventUrl: @json($eventUrl),
            name: @json($event->title),
            prefill: {
                name: @json($contact['name'] ?? ''),
                email: @json($contact['email'] ?? ''),
                contact: @json($contact['phone'] ?? ''),
            },
        };

        const btn = document.getElementById('payBtn');
        const msg = document.getElementById('payMsg');
        const setMsg = (t, k = '') => { msg.textContent = t; msg.className = 'pay-msg' + (k ? ' ' + k : ''); };

        async function post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(body),
            });
            return { ok: res.ok, data: await res.json().catch(() => ({})) };
        }

        function releaseAndReturn() {
            // Fire-and-forget: hand the held seats back, then return to the event.
            post(cfg.releaseUrl, { razorpay_order_id: cfg.orderId }).finally(() => {
                window.location.href = cfg.eventUrl;
            });
        }

        function openCheckout() {
            btn.disabled = true;
            setMsg('');
            const rzp = new Razorpay({
                key: cfg.key,
                order_id: cfg.orderId,
                amount: cfg.amount,
                currency: cfg.currency,
                name: cfg.name,
                description: 'Event tickets',
                prefill: cfg.prefill,
                theme: { color: '#2563EB' },
                handler: async function (response) {
                    setMsg('Verifying payment…');
                    const r = await post(cfg.confirmUrl, {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    });
                    if (r.ok && r.data.ok) {
                        setMsg('Payment successful ✓', 'ok');
                        window.location.href = r.data.redirect || cfg.passUrl;
                    } else {
                        setMsg((r.data && r.data.error) || 'Could not verify payment.', 'err');
                        btn.disabled = false;
                    }
                },
                modal: {
                    ondismiss: function () {
                        setMsg('Payment cancelled — your seats have been released.', 'err');
                        releaseAndReturn();
                    },
                },
            });
            rzp.on('payment.failed', function (resp) {
                const d = resp && resp.error ? resp.error.description : 'Payment failed.';
                setMsg(d || 'Payment failed. Please try again.', 'err');
                btn.disabled = false;
            });
            rzp.open();
        }

        btn.addEventListener('click', openCheckout);
        // Auto-open so the buyer lands straight in checkout; the button is the retry.
        openCheckout();
    })();
</script>
@endsection
