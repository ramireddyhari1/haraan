<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout · Haraan</title>
    {{-- Razorpay Standard Checkout script (opens the hosted payment modal). --}}
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        :root { --blue:#2563eb; --green:#12b76a; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: -apple-system, "Segoe UI", Roboto, system-ui, sans-serif;
            background: linear-gradient(160deg, #f8fafc, #eef2ff); color: var(--ink); padding: 24px;
        }
        .card {
            width: 100%; max-width: 380px; background: #fff; border: 1px solid var(--line);
            border-radius: 18px; padding: 28px; box-shadow: 0 18px 50px rgba(15,23,42,.10);
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p.sub { margin: 0 0 20px; color: var(--muted); font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 0 0 6px; }
        .amount { display: flex; align-items: center; border: 1px solid var(--line); border-radius: 12px; padding: 0 12px; }
        .amount span { color: var(--muted); font-weight: 700; }
        .amount input { flex: 1; border: 0; outline: 0; padding: 13px 8px; font-size: 16px; font-weight: 700; background: transparent; }
        button.pay {
            width: 100%; margin-top: 18px; border: 0; border-radius: 12px; padding: 14px;
            font-size: 16px; font-weight: 700; color: #fff; cursor: pointer;
            background: var(--blue); transition: background .15s, opacity .15s;
        }
        button.pay:hover { background: #1d4ed8; }
        button.pay:disabled { opacity: .6; cursor: progress; }
        .msg { margin-top: 16px; font-size: 14px; font-weight: 600; min-height: 20px; text-align: center; }
        .msg.ok  { color: var(--green); }
        .msg.err { color: #dc2626; }
        .foot { margin-top: 18px; text-align: center; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <div class="card">
        <h1>Complete your payment</h1>
        <p class="sub">Powered by Razorpay Standard Checkout.</p>

        <label for="amount">Amount (₹)</label>
        <div class="amount">
            <span>₹</span>
            <input id="amount" type="number" min="1" step="1" value="1" inputmode="numeric">
        </div>

        <button class="pay" id="payBtn">Pay now</button>
        <div class="msg" id="msg" role="status" aria-live="polite"></div>
        <div class="foot">Test mode — use Razorpay's test cards. No real money is charged.</div>
    </div>

    <script>
        const btn = document.getElementById('payBtn');
        const msgEl = document.getElementById('msg');
        const amountEl = document.getElementById('amount');

        const setMsg = (text, kind = '') => { msgEl.textContent = text; msgEl.className = 'msg' + (kind ? ' ' + kind : ''); };

        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, data };
        }

        async function pay() {
            const rupees = Math.floor(Number(amountEl.value));
            if (!rupees || rupees < 1) { setMsg('Enter an amount of ₹1 or more.', 'err'); return; }

            btn.disabled = true;
            setMsg('Creating order…');

            // 1) Ask the backend to create an order (amount is fixed server-side, in paise).
            const order = await postJson('/api/create-order', {
                amount: rupees * 100,
                currency: 'INR',
            });

            if (!order.ok) {
                setMsg(order.data.error || 'Could not start payment. Please try again.', 'err');
                btn.disabled = false;
                return;
            }

            setMsg('');

            // 2) Open the Razorpay modal with the returned order id + public key.
            const rzp = new Razorpay({
                key: order.data.key,
                order_id: order.data.order_id,
                amount: order.data.amount,
                currency: order.data.currency,
                name: 'Haraan',
                description: 'Payment',
                handler: async function (response) {
                    // 3) Payment succeeded in the modal — verify the signature server-side.
                    setMsg('Verifying payment…');
                    const verify = await postJson('/api/verify-payment', {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    });

                    if (verify.ok && verify.data.verified) {
                        setMsg('Payment successful and verified ✓', 'ok');
                    } else {
                        setMsg(verify.data.error || 'Payment could not be verified.', 'err');
                    }
                    btn.disabled = false;
                },
                modal: {
                    ondismiss: function () {
                        setMsg('Payment cancelled.', 'err');
                        btn.disabled = false;
                    },
                },
                theme: { color: '#2563eb' },
            });

            rzp.on('payment.failed', function (resp) {
                const desc = resp && resp.error ? resp.error.description : 'Payment failed.';
                setMsg(desc || 'Payment failed. Please try again.', 'err');
                btn.disabled = false;
            });

            rzp.open();
        }

        btn.addEventListener('click', pay);
    </script>
</body>
</html>
