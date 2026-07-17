@php($rows = $this->getCoupons())
@php($totals = $this->getTotals())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Coupon performance</x-slot>
        <x-slot name="description">Which promo codes drove paid bookings for this event</x-slot>

        @if (empty($rows))
            <div style="text-align:center;padding:22px 12px;color:#8a94a6;font-size:13px;">
                No coupons redeemed for this event yet.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left;color:#8a94a6;border-bottom:1px solid rgba(120,130,150,.18);">
                            <th style="padding:8px 10px;font-weight:600;">Code</th>
                            <th style="padding:8px 10px;font-weight:600;text-align:right;">Orders</th>
                            <th style="padding:8px 10px;font-weight:600;text-align:right;">Tickets</th>
                            <th style="padding:8px 10px;font-weight:600;text-align:right;">Discount given</th>
                            <th style="padding:8px 10px;font-weight:600;text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr style="border-bottom:1px solid rgba(120,130,150,.10);">
                                <td style="padding:10px;">
                                    <span style="display:inline-block;font-weight:700;letter-spacing:.03em;padding:3px 9px;border-radius:7px;background:rgba(37,99,235,.10);color:#2563eb;">{{ $r['code'] }}</span>
                                </td>
                                <td style="padding:10px;text-align:right;font-weight:600;">{{ number_format($r['orders']) }}</td>
                                <td style="padding:10px;text-align:right;">{{ number_format($r['tickets']) }}</td>
                                <td style="padding:10px;text-align:right;color:#d97706;">₹{{ number_format($r['discount']) }}</td>
                                <td style="padding:10px;text-align:right;font-weight:700;color:#059669;">₹{{ number_format($r['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid rgba(120,130,150,.2);font-weight:700;">
                            <td style="padding:10px;">Total</td>
                            <td style="padding:10px;text-align:right;">{{ number_format($totals['orders']) }}</td>
                            <td style="padding:10px;text-align:right;"></td>
                            <td style="padding:10px;text-align:right;color:#d97706;">₹{{ number_format($totals['discount']) }}</td>
                            <td style="padding:10px;text-align:right;color:#059669;">₹{{ number_format($totals['revenue']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
