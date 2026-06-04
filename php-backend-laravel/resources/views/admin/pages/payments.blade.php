@extends('admin.partials.layout')

@section('content')
<div class="card">
	<h2>Payments (Bookings)</h2>
	<div style="display:flex;gap:8px;margin-bottom:10px"><input id="q" placeholder="Search" /> <button class="btn" onclick="loadPayments(document.getElementById('q').value)">Search</button></div>
	<table class="table">
		<thead><tr><th>ID</th><th>User</th><th>Event</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
		<tbody id="payments-body"></tbody>
	</table>
</div>

<script>
async function loadPayments(q=''){
	const url = '{{ route('admin.payments.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
	const res = await fetch(url);
	const json = await res.json();
	const tbody = document.getElementById('payments-body');
	tbody.innerHTML = '';
	(json.data.data || json.data).forEach(p => {
		const tr = document.createElement('tr');
		const user = p.user ? p.user.name : '—';
		const event = p.event ? p.event.title : '—';
		tr.innerHTML = `<td>${p.id}</td><td>${user}</td><td>${event}</td><td>${p.total_amount}</td><td>${p.status}</td><td><button onclick="markPaid(${p.id})">Mark Paid</button></td>`;
		tbody.appendChild(tr);
	});
}
async function markPaid(id){
	await fetch(`{{ url('/admin/payments') }}/${id}/mark-paid`, {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}});
	loadPayments();
}
document.addEventListener('DOMContentLoaded', loadPayments);
</script>

@endsection
