@extends('partner-docs.layout')

@php
    $tds = $batches->sum(fn ($b) => (float) $b->tds_amount);
    $tcs = $batches->sum(fn ($b) => (float) $b->tcs_amount);
    $gross = $batches->sum(fn ($b) => $b->gross());
@endphp

@section('title', 'TDS summary ' . $fy . ' Q' . $q)
@section('heading', 'Quarterly TDS / TCS summary')

@section('meta')
    <dt>Financial year</dt><dd>{{ $fy }}</dd>
    <dt>Quarter</dt><dd>{{ $label }}</dd>
    @if ($cert)<dt>Form 16A</dt><dd>{{ $cert->certificate_no ?: 'Issued' }}</dd>@endif
@endsection

@section('body')
    <div class="parties">
        @include('partner-docs._billed-to', ['label' => 'Deductee'])
        <div class="party">
            <h3>Deductor</h3>
            <p>
                <strong>{{ $issuer['legal_name'] ?? 'Haraan' }}</strong><br>
                @if (! empty($issuer['tan']))TAN {{ $issuer['tan'] }}<br>@endif
                @if (! empty($issuer['pan']))PAN {{ $issuer['pan'] }}@endif
            </p>
        </div>
    </div>

    @if ($batches->isEmpty())
        <p class="note">No settlements in this quarter withheld any tax.</p>
    @else
        <div class="wide">
        <table>
            <tr><th>Settlement</th><th>Date</th><th class="num">Amount credited</th><th class="num">TDS 194-O</th><th class="num">GST TCS</th></tr>
            @foreach ($batches as $b)
                <tr>
                    <td>#{{ $b->id }}@if ($b->reference)<br><small style="color:var(--muted)">UTR {{ $b->reference }}</small>@endif</td>
                    <td>{{ \App\Support\BusinessClock::local($b->created_at)->format('d M Y') }}</td>
                    <td class="num">{{ $inr($b->gross()) }}</td>
                    <td class="num">{{ $inr($b->tds_amount) }}</td>
                    <td class="num">{{ $inr($b->tcs_amount) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="2">{{ $batches->count() }} settlements</td><td class="num">{{ $inr($gross) }}</td><td class="num">{{ $inr($tds) }}</td><td class="num">{{ $inr($tcs) }}</td></tr>
        </table>
        </div>
    @endif

    <p class="note">
        @if ($cert)
            The official Form 16A for this quarter is in your Invoices list.
        @else
            The official Form 16A for this quarter will be added to your Invoices list once Haraan files the quarter’s TDS return.
        @endif
        TDS also shows in your Form 26AS / AIS against your PAN.
    </p>
@endsection
