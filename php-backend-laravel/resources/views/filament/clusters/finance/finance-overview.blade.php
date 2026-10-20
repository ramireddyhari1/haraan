<x-filament-panels::page>
    {{-- Every figure comes from FinanceOverview::getPanels(); this only draws it. --}}
    <div style="display: grid; gap: 16px">
        @foreach ($this->getPanels() as $panel)
            @if (! empty($panel['stats']))
                <x-summary-panel :s="$panel" />
            @endif
        @endforeach

        <section>
            <div class="hrn-sec-h"><h2>Manage</h2></div>
            <div class="fin-links">
                @foreach ([
                    ['receipt', 'Payout batches', 'Settle a partner: gross, commission, GST, TDS and the net that goes to their bank.', \App\Filament\Resources\PayoutBatches\PayoutBatchResource::getUrl('index')],
                    ['plug', 'Payout accounts', 'The bank or UPI each partner is paid to, and whether it is verified.', \App\Filament\Resources\PartnerPayoutAccounts\PartnerPayoutAccountResource::getUrl('index')],
                    ['players', 'Member subscriptions', 'Pro and Hero members: renewals, cancellations, free grants.', \App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource::getUrl('index')],
                    ['price-tag', 'Coupons', 'Discount codes across events and venues.', \App\Filament\Resources\Coupons\CouponResource::getUrl('index')],
                ] as [$illo, $title, $sub, $url])
                    <a href="{{ $url }}" class="fin-link">
                        <x-hrn-illo :name="$illo" :size="76" />
                        <span>
                            <b>{{ $title }}</b>
                            <span>{{ $sub }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <style>
        .fin-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .fin-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px 12px 10px; background: #fff;
            border: 1px solid #e6e9f0; border-radius: 14px; text-decoration: none; transition: border-color .15s, transform .15s; }
        .fin-link:hover { border-color: #93b4f8; transform: translateY(-1px); }
        .fin-link b { display: block; font-size: 14px; font-weight: 680; color: #0f172a; }
        .fin-link span span { display: block; margin-top: 2px; font-size: 12.5px; line-height: 1.45; color: #64748b; }
        .dark .fin-link { background: #111827; border-color: #1f2937; }
        .dark .fin-link b { color: #f8fafc; }
        .dark .fin-link span span { color: #94a3b8; }
        @media (prefers-reduced-motion: reduce) { .fin-link { transition: none; } .fin-link:hover { transform: none; } }
    </style>
</x-filament-panels::page>
