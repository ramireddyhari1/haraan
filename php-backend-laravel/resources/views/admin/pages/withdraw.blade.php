@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">Financials</span>
	<h2>Withdraw workflow</h2>
	<p>Use this lane for payout planning, finance approvals, and transfer tracking.</p>
</section>

<div class="grid-3">
	<div class="card"><strong>Pending requests</strong><div class="subtle">Queue withdrawals by partner or venue.</div></div>
	<div class="card"><strong>Approval stage</strong><div class="subtle">Track who approved the transfer before processing.</div></div>
	<div class="card"><strong>Settlement history</strong><div class="subtle">Add a ledger view here when finance workflow expands.</div></div>
</div>
@endsection
