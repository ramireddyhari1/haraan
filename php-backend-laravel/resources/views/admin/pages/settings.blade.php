@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">System</span>
	<h2>Application settings</h2>
	<p>Review access, security, and deployment settings for the internal portals.</p>
</section>

<div class="grid-2">
	<div class="card">
		<h3 style="margin-top:0;">Portal checks</h3>
		<div class="list">
			<div class="list-item"><strong>ERP key</strong><span>Confirm the entry gate is not using a shared default key in production.</span></div>
			<div class="list-item"><strong>Session auth</strong><span>Validate login, logout, and password reset routes.</span></div>
			<div class="list-item"><strong>Scopes</strong><span>Assign admin, partner, and org-scoped access deliberately.</span></div>
		</div>
	</div>
	<div class="card">
		<h3 style="margin-top:0;">Useful routes</h3>
		<div class="list">
			<div class="list-item"><strong><a href="{{ route('admin.cities.edit') }}">Cities JSON editor</a></strong><span>Edit public city data used across the site.</span></div>
			<div class="list-item"><strong><a href="{{ route('admin.export.users') }}">Export users</a></strong><span>Download a current user snapshot for audits.</span></div>
			<div class="list-item"><strong><a href="{{ route('admin.events.create') }}">Create event</a></strong><span>Jump straight to the event form.</span></div>
		</div>
	</div>
</div>
@endsection
