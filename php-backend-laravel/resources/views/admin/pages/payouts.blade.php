@extends('admin.partials.layout')
@section('content')
<div class="card">
	<h2>Payout Requests</h2>
	<div style="margin-bottom:12px"><div style="display:flex;gap:8px;margin-bottom:8px"><input id="q" placeholder="Search" /> <button class="btn" onclick="loadPayouts(document.getElementById('q').value)">Search</button></div><form id="create-payout" onsubmit="return false;" style="display:flex;gap:8px"><input id="booking-id" placeholder="Booking ID" /><button class="btn">Create Payout</button></form></div>
	<table class="table"><thead><tr><th>ID</th><th>Booking</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody id="payouts-body"></tbody></table>
</div>

@section('scripts')
<script>
async function loadPayouts(q=''){
	const url = '{{ route('admin.payouts.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
	const res = await fetch(url);
	const json = await res.json();
	const rows = json.data.data || json.data;
	const body = document.getElementById('payouts-body'); body.innerHTML='';
	rows.forEach(p=>{
		const tr = document.createElement('tr');
		const bookingTitle = p.booking && p.booking.event ? p.booking.event.title : (p.booking? p.booking.id : '—');
		tr.innerHTML = `<td>${p.id}</td><td>${bookingTitle}</td><td>${p.amount}</td><td>${p.status}</td><td>${p.status!=='PAID'?`<button onclick="process(${p.id})">Process</button>`:''}</td>`;
		body.appendChild(tr);
	});
}
async function process(id){ await fetch(`{{ url('/admin/payouts') }}/${id}/process`, {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}); loadPayouts(); }
document.getElementById('create-payout').addEventListener('submit', async function(e){ e.preventDefault(); const bookingId=document.getElementById('booking-id').value; await fetch('{{ route('admin.payouts.create') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({booking_id: bookingId})}); loadPayouts(); });
document.addEventListener('DOMContentLoaded', loadPayouts);
</script>
@endsection

@endsection
