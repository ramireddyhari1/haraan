@extends('admin.partials.layout')

@section('content')
<div class="card">
	<h2>Bookings</h2>
	<div id="bookings-root">
		<div style="display:flex;gap:8px;margin-bottom:10px"><input id="q" placeholder="Search" /> <button class="btn" onclick="loadBookings(document.getElementById('q').value)">Search</button></div>
		<table class="table">
			<thead><tr><th>ID</th><th>User</th><th>Event</th><th>Quantity</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
			<tbody id="bookings-body"></tbody>
		</table>
	</div>
</div>

<script>
async function loadBookings(q=''){
	const url = '{{ route('admin.bookings.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
	const res = await fetch(url);
	const json = await res.json();
	const tbody = document.getElementById('bookings-body');
	tbody.innerHTML = '';
	(json.data.data || json.data).forEach(b => {
		const tr = document.createElement('tr');
		const user = b.user ? b.user.name : '—';
		const event = b.event ? b.event.title : '—';
		tr.innerHTML = `<td>${b.id}</td><td>${user}</td><td>${event}</td><td>${b.quantity}</td><td>${b.total_amount}</td><td>${b.status}</td><td><button onclick="updateStatus(${b.id},'PAID')">Mark Paid</button> <button onclick="updateStatus(${b.id},'CANCELLED')">Cancel</button></td>`;
		tbody.appendChild(tr);
	});
}
async function updateStatus(id, status){
	await fetch(`{{ url('/admin/bookings') }}/${id}/status`, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({status})});
	loadBookings();
}
document.addEventListener('DOMContentLoaded', loadBookings);
</script>

@endsection
