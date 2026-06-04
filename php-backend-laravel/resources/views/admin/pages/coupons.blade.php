@extends('admin.partials.layout')

@section('content')
<div class="card">
	<h2>Coupons</h2>
	<div>
		<div style="display:flex;gap:8px;margin-bottom:8px"><input id="q" placeholder="Search" /> <button class="btn" onclick="loadCoupons(document.getElementById('q').value)">Search</button></div>
		<form id="coupon-form" onsubmit="return false;" style="display:flex;gap:8px;align-items:center">
			<input id="coupon-code" placeholder="Code" />
			<input id="coupon-discount" placeholder="Discount" type="number" step="0.01" />
			<button class="btn" id="create-coupon">Create</button>
		</form>
	</div>
	<div style="margin-top:12px">
		<table class="table"><thead><tr><th>ID</th><th>Code</th><th>Discount</th><th>Uses</th><th>Active</th><th>Actions</th></tr></thead><tbody id="coupons-body"></tbody></table>
	</div>
</div>

@section('scripts')
<script>
async function loadCoupons(q=''){
	const url = '{{ route('admin.coupons.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
	const res = await fetch(url);
	const json = await res.json();
	const rows = json.data.data || json.data;
	const body = document.getElementById('coupons-body'); body.innerHTML='';
	rows.forEach(c=>{
		const tr = document.createElement('tr');
		tr.innerHTML = `<td>${c.id}</td><td>${c.code}</td><td>${c.discount}</td><td>${c.uses||0}</td><td>${c.active? 'Yes':'No'}</td><td><button onclick="delCoupon(${c.id})">Delete</button></td>`;
		body.appendChild(tr);
	});
}
async function createCoupon(){
	const code = document.getElementById('coupon-code').value; const discount = parseFloat(document.getElementById('coupon-discount').value||0);
	await fetch('{{ route('admin.coupons.store') }}', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({code,discount})});
	document.getElementById('coupon-code').value=''; document.getElementById('coupon-discount').value='';
	loadCoupons();
}
async function delCoupon(id){ await fetch(`{{ url('/admin/coupons') }}/${id}`, {method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}); loadCoupons(); }
document.getElementById('create-coupon').addEventListener('click', createCoupon);
document.addEventListener('DOMContentLoaded', loadCoupons);
</script>
@endsection

@endsection
