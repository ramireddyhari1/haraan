@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">GameHub lane</span>
	<h2>Run sports fixtures, scoreboards, brackets, and live results on a separate path.</h2>
	<p>GameHub is isolated from event management so partners and admins can update match data without affecting singing or cultural programs.</p>
</section>

<div class="grid-3">
	@foreach($lanes as $lane)
		<section class="card metric">
			<div class="label">{{ $lane['name'] }}</div>
			<div class="note" style="margin-top:8px;">{{ $lane['detail'] }}</div>
		</section>
	@endforeach
</div>
@endsection
