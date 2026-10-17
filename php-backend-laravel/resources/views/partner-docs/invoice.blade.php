@extends('partner-docs.layout')

@php $taxInvoice = ! empty($issuer['gstin']); @endphp

@section('title', ($taxInvoice ? 'Tax invoice ' : 'Commission invoice ') . ($b->invoice_number ?? '#' . $b->id))
@section('heading', $taxInvoice ? 'Tax invoice' : 'Commission invoice')

@section('meta')
    <dt>Invoice no.</dt><dd>{{ $b->invoice_number ?? '—' }}</dd>
    <dt>Invoice date</dt><dd>{{ $issued->format('d M Y') }}</dd>
    <dt>Settlement</dt><dd>#{{ $b->id }}</dd>
    @if ($period)<dt>Period</dt><dd>{{ $period }}</dd>@endif
    @if ($taxInvoice && $partnerState)<dt>Place of supply</dt><dd>{{ $partnerState }} ({{ $partner['state_code'] }})</dd>@endif
@endsection

@section('body')
    <div class="parties">
        @include('partner-docs._billed-to')
        <div class="party">
            <h3>Supplier</h3>
            <p>
                <strong>{{ $issuer['legal_name'] ?? 'Haraan' }}</strong><br>
                @if (! empty($issuer['gstin']))GSTIN {{ $issuer['gstin'] }}<br>@endif
                @if ($issuerState){{ $issuerState }} ({{ $issuer['state_code'] }})<br>@endif
                Reverse charge: No
            </p>
        </div>
    </div>

    <table>
        <tr><th>#</th><th>Description</th><th>SAC</th><th class="num">Taxable value</th></tr>
        <tr>
            <td>1</td>
            <td>Platform commission — bookings and payments facilitated on Haraan<br><small style="color:var(--muted)">{{ $pct($b->commission_percent) }} of settlement amount {{ $inr($b->gross()) }}</small></td>
            <td>{{ $issuer['sac'] ?? '' }}</td>
            <td class="num">{{ $inr($b->commission_amount) }}</td>
        </tr>
        @if ($taxInvoice)
            @if ($intra)
                <tr class="sub"><td></td><td>CGST @ {{ $pct($b->commission_gst_percent / 2) }}</td><td></td><td class="num">{{ $inr($cgst) }}</td></tr>
                <tr class="sub"><td></td><td>SGST @ {{ $pct($b->commission_gst_percent / 2) }}</td><td></td><td class="num">{{ $inr($sgst) }}</td></tr>
            @else
                <tr class="sub"><td></td><td>IGST @ {{ $pct($b->commission_gst_percent) }}</td><td></td><td class="num">{{ $inr($igst) }}</td></tr>
            @endif
        @endif
        <tr class="total"><td></td><td colspan="2">Invoice total</td><td class="num">{{ $inr((float) $b->commission_amount + (float) $b->commission_gst_amount) }}</td></tr>
    </table>

    <div class="net"><span>Settled by deduction from settlement #{{ $b->id }} — nothing to pay</span><b>{{ $inr((float) $b->commission_amount + (float) $b->commission_gst_amount) }}</b></div>
    @if ($taxInvoice && (float) $b->commission_gst_amount > 0 && ! empty($partner['gstin']))
        <p class="note">You can claim the GST on this invoice as input tax credit once it appears in your GSTR-2B.</p>
    @elseif (! $taxInvoice)
        <p class="note">Haraan is not charging GST on this commission.</p>
    @endif
@endsection
