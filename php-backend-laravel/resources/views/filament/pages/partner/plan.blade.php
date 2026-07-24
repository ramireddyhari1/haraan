{{-- Partner "Plan & usage": current tier → quota bar → what's included.
     Self-contained markup + inline CSS, matching the Payouts/Reviews language. --}}
@php
    $plan = $this->plan();
    $subscription = $this->subscription();
    $usage = $this->usage();
    $features = $this->features();

    $halted = $subscription?->status === \App\Models\PartnerSubscription::STATUS_HALTED;
    // Warn before they hit the wall, not after.
    $tight = $usage['allowance'] > 0 && $usage['percent'] >= 80;
@endphp

<x-filament-panels::page>
    <div class="ppl">

        {{-- ---------- Current plan ---------- --}}
        <section class="ppl-hero">
            <div class="ppl-hero-bg" aria-hidden="true"></div>
            <div class="ppl-hero-in">
                <span class="ppl-hero-lab">Your plan</span>
                <div class="ppl-hero-val">{{ $plan->name }}</div>
                @if ($plan->description)
                    <p class="ppl-hero-sub">{{ $plan->description }}</p>
                @endif

                <div class="ppl-hero-meta">
                    <span class="ppl-chip">
                        {{ $plan->price_inr > 0 ? '₹' . number_format($plan->price_inr) . '/month' : 'Free' }}
                    </span>
                    @if ($subscription && $subscription->current_period_end)
                        <span class="ppl-chip">
                            {{ $halted ? 'Paused' : 'Renews' }}
                            {{ $subscription->current_period_end->format('d M Y') }}
                        </span>
                    @endif
                </div>
            </div>
        </section>

        @if ($halted)
            {{-- Say explicitly what has and hasn't stopped: the first thing anyone
                 in this state wants to know is whether tickets are still going out. --}}
            <div class="ppl-alert">
                <strong>Your last payment didn't go through.</strong>
                Automations are paused until it's sorted. Ticket delivery and booking
                confirmations are unaffected and still going out as normal.
            </div>
        @endif

        {{-- ---------- Quota ---------- --}}
        <section class="ppl-card">
            <div class="ppl-card-head">
                <div>
                    <h2 class="ppl-card-title">Conversations this month</h2>
                    <p class="ppl-card-sub">
                        WhatsApp charges per 24-hour conversation, not per message — several
                        messages to the same person in a day count once.
                    </p>
                </div>
            </div>

            @if ($usage['allowance'] > 0)
                <div class="ppl-quota">
                    <div class="ppl-bar">
                        <span class="ppl-bar-fill {{ $tight ? 'is-tight' : '' }}"
                              style="width:{{ $usage['percent'] }}%"></span>
                    </div>
                    <div class="ppl-quota-meta">
                        <span class="ppl-quota-used">{{ number_format($usage['used']) }}</span>
                        <span class="ppl-quota-of">of {{ number_format($usage['allowance']) }} used</span>
                        <span class="ppl-quota-left {{ $tight ? 'is-tight' : '' }}">
                            {{ number_format($usage['remaining']) }} left
                        </span>
                    </div>
                    @if ($usage['credits'] > 0)
                        <p class="ppl-note">
                            Includes {{ number_format($usage['credits']) }} from top-up packs, which don't expire.
                        </p>
                    @endif
                </div>
            @else
                <p class="ppl-empty-line">
                    Your plan doesn't include automated conversations — only ticket delivery,
                    which is unlimited and always free.
                </p>
            @endif
        </section>

        {{-- ---------- What's included ---------- --}}
        <section class="ppl-card">
            <div class="ppl-card-head">
                <div>
                    <h2 class="ppl-card-title">What's included</h2>
                </div>
            </div>

            @foreach ($features as $feature)
                <div class="ppl-feature">
                    <span class="ppl-tick {{ $feature['included'] ? 'is-on' : '' }}" aria-hidden="true">
                        @if ($feature['included'])
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none">
                                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6"
                                    stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none">
                                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2"
                                    stroke-linecap="round"/>
                            </svg>
                        @endif
                    </span>
                    <span class="ppl-feature-label {{ $feature['included'] ? '' : 'is-off' }}">
                        {{ $feature['label'] }}
                    </span>
                </div>
            @endforeach

            <p class="ppl-note ppl-note-cta">
                Want to change plan? Message us from
                <a href="{{ \App\Filament\Pages\Partner\PartnerSupport::getUrl() }}">Support</a>
                and we'll sort it out — self-serve upgrades are coming.
            </p>
        </section>

        {{-- ---------- Top-up packs ---------- --}}
        @php $packs = $this->packs(); @endphp
        @if ($packs->isNotEmpty())
            <section class="ppl-card">
                <div class="ppl-card-head">
                    <div>
                        <h2 class="ppl-card-title">Need more conversations?</h2>
                        <p class="ppl-card-sub">
                            One-off top-ups. They never expire, and they're used only after
                            your monthly allowance runs out.
                        </p>
                    </div>
                </div>

                <div class="ppl-packs">
                    @foreach ($packs as $pack)
                        <div class="ppl-pack">
                            <span class="ppl-pack-n">{{ number_format($pack->conversations) }}</span>
                            <span class="ppl-pack-l">conversations</span>
                            <span class="ppl-pack-p">₹{{ number_format($pack->price_inr) }}</span>
                            <button type="button" class="ppl-buy" wire:click="buyPack({{ $pack->id }})"
                                    wire:loading.attr="disabled" wire:target="buyPack({{ $pack->id }})">
                                <span wire:loading.remove wire:target="buyPack({{ $pack->id }})">Buy</span>
                                <span wire:loading wire:target="buyPack({{ $pack->id }})">Opening…</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Razorpay Standard Checkout. The key and order id come from the
                 server on demand; nothing sensitive is templated into the page. --}}
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
                document.addEventListener('open-razorpay', (event) => {
                    const detail = event.detail ?? {};
                    const order = detail.order ?? {};

                    if (!order.order_id || !order.key) {
                        return;
                    }

                    new Razorpay({
                        key: order.key,
                        order_id: order.order_id,
                        amount: order.amount,
                        currency: order.currency,
                        name: 'Haraan',
                        description: detail.name ?? 'Conversation top-up',
                        handler: (response) => {
                            // Server re-verifies the signature; this call is a claim,
                            // not proof.
                            window.Livewire.find(
                                document.querySelector('[wire\\:id]').getAttribute('wire:id')
                            ).call(
                                'confirmPack',
                                response.razorpay_order_id,
                                response.razorpay_payment_id,
                                response.razorpay_signature,
                            );
                        },
                    }).open();
                });
            </script>
        @endif
    </div>

    <style>
        .ppl{display:flex;flex-direction:column;gap:16px;}

        /* ---- hero ---- */
        .ppl-hero{position:relative;overflow:hidden;border-radius:20px;
            box-shadow:0 18px 40px -22px rgba(21,71,170,.55);}
        .ppl-hero-bg{position:absolute;inset:0;
            background:radial-gradient(120% 140% at 0% 0%,#3d9bff 0%,#2563eb 44%,#1a4fd0 100%);}
        .ppl-hero-bg::after{content:"";position:absolute;inset:0;
            background:radial-gradient(80% 120% at 100% 0%,rgba(255,255,255,.22),transparent 60%);}
        .ppl-hero-in{position:relative;padding:20px 22px 22px;color:#fff;}
        .ppl-hero-lab{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
            color:rgba(255,255,255,.82);}
        .ppl-hero-val{font-size:34px;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:8px 0 0;}
        .ppl-hero-sub{font-size:13px;color:rgba(255,255,255,.85);margin:6px 0 0;max-width:52ch;line-height:1.5;}
        .ppl-hero-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}
        .ppl-chip{display:inline-flex;align-items:center;font-size:12px;font-weight:700;
            padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.18);color:#fff;
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);}

        /* ---- alert ---- */
        .ppl-alert{font-size:13px;line-height:1.55;color:#92400e;background:#fffbeb;
            border-radius:14px;padding:13px 15px;box-shadow:inset 0 0 0 1px rgba(217,119,6,.22);}

        /* ---- cards ---- */
        .ppl-card{background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:16px 18px 18px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);}
        .ppl-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            margin-bottom:14px;}
        .ppl-card-title{font-size:15px;font-weight:800;color:#0b1220;letter-spacing:-.01em;margin:0;}
        .ppl-card-sub{font-size:12.5px;color:#98a2b3;margin:4px 0 0;max-width:60ch;line-height:1.5;}

        /* ---- quota ---- */
        .ppl-bar{height:10px;border-radius:999px;background:#eef1f6;overflow:hidden;}
        .ppl-bar-fill{display:block;height:100%;border-radius:999px;
            background:linear-gradient(90deg,#2f6bff,#1e50e6);transition:width .3s;}
        .ppl-bar-fill.is-tight{background:linear-gradient(90deg,#f59e0b,#ea580c);}
        .ppl-quota-meta{display:flex;align-items:baseline;gap:7px;margin-top:10px;flex-wrap:wrap;}
        .ppl-quota-used{font-size:22px;font-weight:800;color:#0b1220;font-variant-numeric:tabular-nums;}
        .ppl-quota-of{font-size:12.5px;color:#98a2b3;}
        .ppl-quota-left{margin-left:auto;font-size:12px;font-weight:700;color:#0a7d4e;
            background:#e7f7ef;padding:3px 9px;border-radius:999px;}
        .ppl-quota-left.is-tight{color:#b06d09;background:#fff6e8;}

        /* ---- features ---- */
        .ppl-feature{display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid #f0f2f6;}
        .ppl-feature:first-of-type{border-top:0;padding-top:0;}
        .ppl-tick{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
            border-radius:50%;flex:none;color:#98a2b3;background:#f1f4f9;}
        .ppl-tick.is-on{color:#0a7d4e;background:#e7f7ef;}
        .ppl-feature-label{font-size:13.5px;color:#0b1220;}
        .ppl-feature-label.is-off{color:#98a2b3;}

        /* ---- top-up packs ---- */
        .ppl-packs{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;}
        .ppl-pack{display:flex;flex-direction:column;align-items:flex-start;gap:2px;
            padding:14px;border-radius:14px;background:#f6f8fc;box-shadow:inset 0 0 0 1px #e9edf4;}
        .ppl-pack-n{font-size:22px;font-weight:800;color:#0b1220;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;}
        .ppl-pack-l{font-size:11.5px;color:#98a2b3;}
        .ppl-pack-p{font-size:14px;font-weight:700;color:#0b1220;margin-top:8px;}
        .ppl-buy{margin-top:10px;width:100%;border:0;cursor:pointer;font-size:13px;font-weight:700;
            padding:9px 14px;border-radius:10px;color:#fff;
            background:linear-gradient(180deg,#2f6bff,#1e50e6);
            box-shadow:0 8px 18px -10px rgba(37,99,235,.6);}
        .ppl-buy:hover{filter:brightness(1.06);}
        .ppl-buy:disabled{opacity:.6;cursor:default;}

        .ppl-note{font-size:11.5px;color:#98a2b3;margin:10px 0 0;line-height:1.5;}
        .ppl-note-cta{margin-top:14px;}
        .ppl-note a{color:#2563eb;font-weight:700;}
        .ppl-empty-line{font-size:12.5px;color:#98a2b3;margin:0;line-height:1.55;}

        @media (max-width:520px){
            .ppl-hero-val{font-size:28px;}
            .ppl-quota-left{margin-left:0;}
        }
    </style>
</x-filament-panels::page>
