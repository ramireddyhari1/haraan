@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">Operations</span>
	<h2>QR scan operations</h2>
	<p>Use this lane for check-ins, live event entry, and venue verification workflows.</p>
</section>

<div class="grid-3">
	<div class="card"><strong>Check-in</strong><div class="subtle">Scan attendee QR codes at venue entry.</div></div>
	<div class="card"><strong>Validation</strong><div class="subtle">Confirm booking tokens and session access.</div></div>
	<div class="card"><strong>Live ops</strong><div class="subtle">Add a future scan console here when hardware is connected.</div></div>
</div>
@endsection
