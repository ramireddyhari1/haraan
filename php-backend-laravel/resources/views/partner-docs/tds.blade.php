@extends('partner-docs.layout')

@section('title', 'TDS / TCS certificate — settlement #' . $b->id)
@section('heading', 'TDS / TCS certificate')

@section('meta')
    <dt>Settlement</dt><dd>#{{ $b->id }}</dd>
    <dt>Deducted on</dt><dd>{{ $issued->format('d M Y') }}</dd>
    <dt>Financial year</dt><dd>{{ $fy }}</dd>
    <dt>Quarter</dt><dd>{{ $quarterLabel }}</dd>
@endsection

@section('body')
    <div class="parties">
        @include('partner-docs._billed-to', ['label' => 'Deductee'])
        <div class="party">
            <h3>Deductor</h3>
            <p>
                <strong>{{ $issuer['legal_name'] ?? 'Haraan' }}</strong><br>
                @if (! empty($issuer['tan']))TAN {{ $issuer['tan'] }}<br>@endif
                @if (! empty($issuer['pan']))PAN {{ $issuer['pan'] }}<br>@endif
                @if (! empty($issuer['gstin']))GSTIN {{ $issuer['gstin'] }}@endif
            </p>
        </div>
    </div>

    <table>
        <tr><th>Tax</th><th>Section</th><th class="num">Amount paid / credited</th><th class="num">Rate</th><th class="num">Withheld</th></tr>
        @if ((float) $b->tds_amount > 0)
            <tr><td>Income tax (TDS)</td><td>194-O</td><td class="num">{{ $inr($b->gross()) }}</td><td class="num">{{ $pct($b->tds_percent) }}</td><td class="num">{{ $inr($b->tds_amount) }}</td></tr>
        @endif
        @if ((float) $b->tcs_amount > 0)
            <tr><td>GST (TCS)</td><td>CGST Act s.52</td><td class="num">{{ $inr($b->gross()) }}</td><td class="num">{{ $pct($b->tcs_percent) }}</td><td class="num">{{ $inr($b->tcs_amount) }}</td></tr>
        @endif
        <tr class="total"><td colspan="4">Total withheld</td><td class="num">{{ $inr((float) $b->tds_amount + (float) $b->tcs_amount) }}</td></tr>
    </table>

    @if (empty($partner['pan']) && (float) $b->tds_amount > 0)
        <p class="note">TDS was withheld at the higher rate because no PAN was on file. Add your PAN in the Partner app → Invoices → Tax details so future settlements use the normal rate.</p>
    @endif
    <p class="note">This statement is Haraan’s record of tax withheld from this settlement. TDS appears in your Form 26AS / AIS once Haraan files the quarter’s return, and the official Form 16A for {{ $quarterLabel }} is added to your Invoices list when it’s issued. GST TCS appears in your GSTR-2X.</p>
@endsection
