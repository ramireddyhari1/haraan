<x-filament-panels::page>
    @php
        $e = $this->getRecord();
        $poster = $e->heroImageUrl();
        $tiers = $this->tiers();
        $slots = $this->slots();
        $unit = $this->unitPrice();
    @endphp

    <style>
        /* Pull the card up under the back control — cut Filament's default page gap. */
        .rg-wrap{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;margin-top:-14px;}
        @media (max-width:900px){.rg-wrap{grid-template-columns:1fr;}}

        .rg-card{background:var(--rg-card,#fff);border-radius:16px;padding:22px 22px 24px;
            box-shadow:0 1px 0 rgba(17,24,39,.04),0 12px 30px -22px rgba(17,24,39,.5);
            border:1px solid rgba(17,24,39,.07);}
        .dark .rg-card{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.09);}

        .rg-h{font-size:15px;font-weight:800;letter-spacing:-.01em;margin:0 0 3px;}
        .rg-sub{font-size:12.5px;color:#6b7280;margin:0 0 14px;}
        .dark .rg-sub{color:#9ca3af;}

        .rg-field{margin-bottom:12px;}
        .rg-field:last-child{margin-bottom:0;}
        .rg-label{display:block;font-size:12px;font-weight:700;letter-spacing:.01em;
            color:#374151;margin-bottom:6px;}
        .dark .rg-label{color:#d1d5db;}
        .rg-opt{font-weight:600;color:#9ca3af;}
        .rg-in,.rg-sel{width:100%;border-radius:10px;border:1px solid rgba(17,24,39,.16);
            background:#fff;color:#111827;font-size:14px;padding:10px 12px;line-height:1.3;
            transition:border-color .15s,box-shadow .15s;}
        .rg-in:focus,.rg-sel:focus{outline:0;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.16);}
        .dark .rg-in,.dark .rg-sel{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.15);color:#f3f4f6;}
        .rg-err{display:block;font-size:11.5px;color:#dc2626;margin-top:5px;font-weight:600;}
        .dark .rg-err{color:#f87171;}
        .rg-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

        .rg-amt-wrap{position:relative;}
        .rg-amt-cur{position:absolute;left:12px;top:50%;transform:translateY(-50%);
            font-size:14px;font-weight:700;color:#6b7280;pointer-events:none;}
        .rg-amt{padding-left:26px !important;font-weight:700;}
        .rg-hint{font-size:11.5px;color:#9ca3af;margin-top:6px;}

        /* Summary rail */
        .rg-sum-poster{width:100%;aspect-ratio:16/10;border-radius:12px;background:#0b1220 center/cover no-repeat;
            margin-bottom:14px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08);}
        .rg-sum-title{font-size:15px;font-weight:800;letter-spacing:-.01em;line-height:1.25;}
        .rg-sum-meta{font-size:12px;color:#6b7280;margin-top:4px;}
        .dark .rg-sum-meta{color:#9ca3af;}
        .rg-line{display:flex;justify-content:space-between;gap:10px;font-size:13px;
            padding:9px 0;border-top:1px dashed rgba(17,24,39,.12);color:#374151;}
        .dark .rg-line{border-color:rgba(255,255,255,.12);color:#d1d5db;}
        .rg-line.rg-total{border-top:1px solid rgba(17,24,39,.2);margin-top:2px;
            font-size:16px;font-weight:800;color:#111827;padding-top:12px;}
        .dark .rg-line.rg-total{color:#fff;border-color:rgba(255,255,255,.22);}

        .rg-actions{display:flex;flex-direction:column;gap:10px;margin-top:16px;}
        .rg-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;
            width:100%;border:0;cursor:pointer;font:inherit;font-size:14px;font-weight:700;
            padding:12px 16px;border-radius:11px;transition:filter .15s,opacity .15s;}
        .rg-btn:disabled{opacity:.6;cursor:default;}
        .rg-btn-pay{background:#2563eb;color:#fff;box-shadow:0 10px 22px -12px rgba(37,99,235,.9);}
        .rg-btn-pay:hover{filter:brightness(1.06);}
        .rg-btn-cash{background:#059669;color:#fff;box-shadow:0 10px 22px -12px rgba(5,150,105,.9);}
        .rg-btn-cash:hover{filter:brightness(1.06);}
        .rg-btn svg{width:17px;height:17px;}
        .rg-or{display:flex;align-items:center;gap:10px;color:#9ca3af;font-size:11px;
            font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin:2px 0;}
        .rg-or::before,.rg-or::after{content:"";flex:1;height:1px;background:rgba(17,24,39,.12);}
        .dark .rg-or::before,.dark .rg-or::after{background:rgba(255,255,255,.12);}

        /* Back control: a crafted circular icon chip + label, not a bare text link. */
        .rg-back{display:inline-flex;align-items:center;gap:10px;text-decoration:none;
            margin:-4px 0 8px;color:#4b5563;font-size:13px;font-weight:600;letter-spacing:-.005em;
            transition:color .15s;}
        .rg-back-ic{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;
            background:#fff;color:#374151;border:1px solid rgba(17,24,39,.1);
            box-shadow:0 1px 2px rgba(17,24,39,.06);
            transition:transform .15s,border-color .15s,color .15s,box-shadow .15s;}
        .rg-back-ic svg{width:17px;height:17px;}
        .rg-back-tx{display:flex;flex-direction:column;line-height:1.15;}
        .rg-back-tx small{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#9ca3af;}
        .rg-back:hover{color:#111827;}
        .rg-back:hover .rg-back-ic{border-color:#2563eb;color:#2563eb;transform:translateX(-2px);
            box-shadow:0 4px 12px -6px rgba(37,99,235,.5);}
        .dark .rg-back{color:#9ca3af;}
        .dark .rg-back-ic{background:rgba(255,255,255,.05);color:#e5e7eb;border-color:rgba(255,255,255,.14);}
        .dark .rg-back:hover{color:#fff;}
        .dark .rg-back:hover .rg-back-ic{border-color:#7da8ff;color:#93c5fd;}

        .rg-paybox{background:rgba(37,99,235,.06);border:1px solid rgba(37,99,235,.25);
            border-radius:12px;padding:16px;text-align:center;}
        .dark .rg-paybox{background:rgba(37,99,235,.12);}
        .rg-paybox-t{font-size:13.5px;font-weight:700;color:#1d4ed8;}
        .dark .rg-paybox-t{color:#93c5fd;}
        .rg-link{background:none;border:0;color:#6b7280;font-size:12.5px;font-weight:600;
            cursor:pointer;text-decoration:underline;margin-top:8px;}
        .fi-header{display:none !important;}
    </style>

    <a class="rg-back" href="{{ \App\Filament\Resources\Events\Pages\EventAnalytics::getUrl(['record' => $e]) }}">
        <span class="rg-back-ic"><x-filament::icon icon="heroicon-m-arrow-left" /></span>
        <span class="rg-back-tx"><small>Back to</small>Analytics</span>
    </a>

    <div class="rg-wrap">
        {{-- Left: attendee + ticket form --}}
        <div class="rg-card">
            <h2 class="rg-h">Register an attendee</h2>
            <p class="rg-sub">Book someone in at the desk and take the payment for “{{ $e->title }}”.</p>

            <div class="rg-field">
                <label class="rg-label">Full name</label>
                <input type="text" class="rg-in" wire:model.blur="name" placeholder="Who is this ticket for?">
                @error('name') <span class="rg-err">{{ $message }}</span> @enderror
            </div>

            <div class="rg-row">
                <div class="rg-field">
                    <label class="rg-label">Phone number</label>
                    <input type="tel" class="rg-in" wire:model.blur="phone" placeholder="e.g. 98765 43210">
                    @error('phone') <span class="rg-err">{{ $message }}</span> @enderror
                </div>
                <div class="rg-field">
                    <label class="rg-label">Email <span class="rg-opt">· optional</span></label>
                    <input type="email" class="rg-in" wire:model.blur="email" placeholder="Where their ticket goes">
                    @error('email') <span class="rg-err">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($slots->count() > 1)
                <div class="rg-field">
                    <label class="rg-label">Session</label>
                    <select class="rg-sel" wire:model.live="eventSlotId">
                        @foreach ($slots as $slot)
                            <option value="{{ $slot->id }}">{{ $slot->displayLabel() }}</option>
                        @endforeach
                    </select>
                    @error('eventSlotId') <span class="rg-err">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="rg-row">
                @if ($tiers->isNotEmpty())
                    <div class="rg-field">
                        <label class="rg-label">Ticket type</label>
                        <select class="rg-sel" wire:model.live="ticketTypeId">
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->id }}">{{ $tier->name }} · ₹{{ number_format($tier->effectivePrice()) }}</option>
                            @endforeach
                        </select>
                        @error('ticketTypeId') <span class="rg-err">{{ $message }}</span> @enderror
                    </div>
                @else
                    <div class="rg-field">
                        <label class="rg-label">Ticket</label>
                        <input type="text" class="rg-in" value="Standard · ₹{{ number_format($unit) }}" disabled>
                    </div>
                @endif
                <div class="rg-field">
                    <label class="rg-label">Quantity</label>
                    <input type="number" min="1" max="20" class="rg-in" wire:model.live="quantity">
                    @error('quantity') <span class="rg-err">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="rg-field">
                <label class="rg-label">Amount to charge</label>
                <div class="rg-amt-wrap">
                    <span class="rg-amt-cur">₹</span>
                    <input type="number" step="0.01" min="0" class="rg-in rg-amt" wire:model.blur="amount">
                </div>
                @error('amount') <span class="rg-err">{{ $message }}</span> @enderror
                <p class="rg-hint">Pre-filled from the ticket price — edit it if you’re charging something else.</p>
            </div>
        </div>

        {{-- Right: order summary + actions --}}
        <div class="rg-card">
            @if ($poster)
                <div class="rg-sum-poster" style="background-image:url('{{ $poster }}');"></div>
            @endif
            <div class="rg-sum-title">{{ $e->title }}</div>
            <div class="rg-sum-meta">
                @if ($e->venue || $e->location){{ $e->venue ?: $e->location }}@endif
                @if ($e->date) · {{ $e->date->format('D, d M Y') }}{{ trim((string) $e->time) !== '' ? ' · ' . $e->time : '' }}@endif
            </div>

            <div style="margin-top:14px;">
                <div class="rg-line">
                    <span>{{ $tiers->firstWhere('id', $ticketTypeId)?->name ?? 'Standard' }} × {{ max(1, (int) $quantity) }}</span>
                    <span>₹{{ number_format((float) $amount, 2) }}</span>
                </div>
                <div class="rg-line rg-total">
                    <span>Total</span>
                    <span>₹{{ number_format((float) $amount, 2) }}</span>
                </div>
            </div>

            @if ($pay)
                {{-- Online payment in progress --}}
                <div class="rg-paybox" style="margin-top:16px;">
                    <div class="rg-paybox-t">Waiting for payment…</div>
                    <div style="margin-top:10px;">
                        <button type="button" class="rg-btn rg-btn-pay"
                                onclick="window.haraanOpenCheckout(@js($pay), @js(['name'=>$name,'email'=>$email,'phone'=>$phone]), $wire)">
                            Open payment window
                        </button>
                    </div>
                    <button type="button" class="rg-link" wire:click="cancelOnline('{{ $pay['orderId'] }}')">
                        Cancel this payment
                    </button>
                </div>
                <div x-data x-init="$nextTick(() => window.haraanOpenCheckout(@js($pay), @js(['name'=>$name,'email'=>$email,'phone'=>$phone]), $wire))"></div>
            @else
                <div class="rg-actions">
                    <button type="button" class="rg-btn rg-btn-pay" wire:click="payOnline" wire:loading.attr="disabled">
                        <x-filament::icon icon="heroicon-m-credit-card" />
                        <span wire:loading.remove wire:target="payOnline">Proceed to pay online</span>
                        <span wire:loading wire:target="payOnline">Starting…</span>
                    </button>
                    <div class="rg-or">or</div>
                    <button type="button" class="rg-btn rg-btn-cash" wire:click="collectCash" wire:loading.attr="disabled">
                        <x-filament::icon icon="heroicon-m-banknotes" />
                        <span wire:loading.remove wire:target="collectCash">Collect cash at desk</span>
                        <span wire:loading wire:target="collectCash">Registering…</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Razorpay Standard Checkout — loaded once; opened by the helper when a payment is reserved. --}}
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        window.haraanOpenCheckout = function (pay, contact, wire) {
            if (typeof Razorpay === 'undefined') { alert('Payment library failed to load. Check the connection and retry.'); return; }
            var rzp = new Razorpay({
                key: pay.key,
                order_id: pay.orderId,
                amount: pay.amount,
                currency: pay.currency,
                name: 'Haraan',
                description: @js('Registration · ' . $e->title),
                prefill: { name: contact.name || '', email: contact.email || '', contact: contact.phone || '' },
                theme: { color: '#2563eb' },
                handler: function (resp) {
                    wire.confirmOnline(resp.razorpay_order_id, resp.razorpay_payment_id, resp.razorpay_signature);
                },
                modal: { ondismiss: function () { wire.cancelOnline(pay.orderId); } }
            });
            rzp.on('payment.failed', function () { wire.cancelOnline(pay.orderId); });
            rzp.open();
        };
    </script>
</x-filament-panels::page>
