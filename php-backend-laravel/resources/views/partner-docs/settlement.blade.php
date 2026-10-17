@extends('partner-docs.layout')

@section('title', 'Settlement statement #' . $b->id)
@section('heading', 'Settlement statement')

@section('meta')
    <dt>Settlement</dt><dd>#{{ $b->id }}</dd>
    <dt>Date</dt><dd>{{ $issued->format('d M Y') }}</dd>
    @if ($period)<dt>Period</dt><dd>{{ $period }}</dd>@endif
    <dt>Status</dt><dd>{{ $status }}</dd>
    @if ($b->reference)<dt>UTR</dt><dd>{{ $b->reference }}</dd>@endif
@endsection

@section('body')
    <div class="parties">
        @include('partner-docs._billed-to', ['label' => 'Partner'])
        <div class="party">
            <h3>Paid to</h3>
            <p>
                @php $acct = \App\Models\PartnerPayoutAccount::query()->where('partner_id', $b->partner_id)->first(); @endphp
                @if ($acct)
                    <strong>{{ $acct->account_holder ?: '—' }}</strong><br>
                    {{ $acct->summaryLine() }}<br>
                @else
                    No settlement account on file<br>
                @endif
                @if ($b->processed_at)Paid on {{ \App\Support\BusinessClock::local($b->processed_at)->format('d M Y') }}@endif
            </p>
        </div>
    </div>

    <h2>Settlement</h2>
    <table>
        <tr><th>Item</th><th class="num">Rate</th><th class="num">Amount</th></tr>
        <tr><td>Settlement amount (your collections)</td><td class="num"></td><td class="num">{{ $inr($b->gross()) }}</td></tr>
        @if ((float) $b->commission_amount > 0)
            <tr class="sub"><td>Less: Haraan commission @if ($b->invoice_number)<br><small>Tax invoice {{ $b->invoice_number }}</small>@endif</td><td class="num">{{ $pct($b->commission_percent) }}</td><td class="num">− {{ $inr($b->commission_amount) }}</td></tr>
        @endif
        @if ((float) $b->commission_gst_amount > 0)
            <tr class="sub"><td>Less: GST on commission</td><td class="num">{{ $pct($b->commission_gst_percent) }}</td><td class="num">− {{ $inr($b->commission_gst_amount) }}</td></tr>
        @endif
        @if ((float) $b->tds_amount > 0)
            <tr class="sub"><td>Less: TDS, section 194-O</td><td class="num">{{ $pct($b->tds_percent) }}</td><td class="num">− {{ $inr($b->tds_amount) }}</td></tr>
        @endif
        @if ((float) $b->tcs_amount > 0)
            <tr class="sub"><td>Less: GST TCS, section 52</td><td class="num">{{ $pct($b->tcs_percent) }}</td><td class="num">− {{ $inr($b->tcs_amount) }}</td></tr>
        @endif
        <tr class="total"><td>Net payout</td><td></td><td class="num">{{ $inr($b->amount) }}</td></tr>
    </table>
    <div class="net"><span>{{ $cancelled ? 'Not paid — this settlement was cancelled' : ($b->isPaid() ? 'Sent to your account' : 'Being sent to your account') }}</span><b>{{ $inr($b->amount) }}</b></div>
    @if ((float) $b->tds_amount > 0 || (float) $b->tcs_amount > 0)
        <p class="note">Tax withheld is deposited with the government against your PAN / GSTIN and can be claimed in your returns. See the TDS / TCS certificate for this settlement.</p>
    @endif

    <h2>Bookings in this period</h2>
    @if (! $period)
        <p class="note">This settlement has no period set, so no booking list is attached.</p>
    @elseif ($bookingTotals['count'] === 0)
        <p class="note">No paid bookings were made in this period.</p>
    @else
        <div class="wide">
        <table class="small">
            <tr><th>Booking</th><th>Date</th><th>For</th><th>Channel</th><th class="num">Amount</th><th class="num">Haraan fees</th><th class="num">Your share</th></tr>
            @foreach ($bookings as $r)
                <tr><td>{{ $r['ref'] }}</td><td>{{ $r['date'] }}</td><td>{{ $r['what'] }}</td><td>{{ $r['channel'] }}</td><td class="num">{{ $inr($r['amount']) }}</td><td class="num">{{ $inr($r['deduction']) }}</td><td class="num">{{ $inr($r['share']) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="4">{{ $bookingTotals['count'] }} bookings</td><td class="num">{{ $inr($bookingTotals['amount']) }}</td><td class="num">{{ $inr($bookingTotals['deduction']) }}</td><td class="num">{{ $inr($bookingTotals['share']) }}</td></tr>
        </table>
        </div>
        @if ($bookingTotals['count'] > $bookings->count())
            <p class="note">Showing the first {{ $bookings->count() }} of {{ $bookingTotals['count'] }} bookings. Download the CSV for all of them.</p>
        @endif
        <p class="note">“Haraan fees” were already taken at booking time (online commission and host-paid fees); they are not deducted again above. Bookings are listed by the day they were made; a settlement can cover only part of a period’s collections.</p>
    @endif
@endsection
