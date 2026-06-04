@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">Access management</span>
	<h2>Control who can access the platform and what each role can touch.</h2>
	<p>Use this area for broader account management while partner, co-admin, and worker access stays separated by purpose.</p>
</section>

<div class="card" style="margin-bottom:12px;">
	<div class="grid-3">
		<div class="list-item"><strong>Admin users</strong><span>Grant staff access with scoped roles.</span></div>
		<div class="list-item"><strong>Partner users</strong><span>Track partner onboarding and status.</span></div>
		<div class="list-item"><strong>Safety controls</strong><span>Suspend or reactivate suspicious accounts quickly.</span></div>
	</div>
</div>

<div class="card">
	<div style="display:flex;gap:8px;margin-bottom:10px"><input id="q" placeholder="Search users" /> <button class="btn" onclick="loadUsers(document.getElementById('q').value)">Search</button></div>
	<div style="display:flex;gap:8px;margin-bottom:10px;align-items:center"><select id="role-select"><option value="">-- Select Role --</option></select> <button class="btn" id="apply-role">Assign Selected Role To Selected User</button></div>
	<table class="table">
		<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
		<tbody id="users-body"></tbody>
	</table>
</div>
@endsection

@section('scripts')
<script>
async function loadUsers(q=''){
	const url = '{{ route('admin.users.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
	const res = await fetch(url);
	const json = await res.json();
	const users = json.data.data || json.data;
	const body = document.getElementById('users-body');
	body.innerHTML = '';
	users.forEach(u => {
		const tr = document.createElement('tr');
		tr.innerHTML = `<td>${u.id}</td><td>${u.name}</td><td>${u.email}</td><td>${u.role||''}</td><td>${u.status||''}</td><td><button class="btn" onclick="suspend(${u.id})">Suspend</button> <input type="radio" name="selected-user" value="${u.id}"/></td>`;
		body.appendChild(tr);
	});
}

async function loadRolesSelect(){
	const res = await fetch('{{ route('admin.roles.json') }}');
	const json = await res.json();
	const rows = json.data || json;
	const sel = document.getElementById('role-select');
	rows.forEach(r=>{ const opt = document.createElement('option'); opt.value = r.name; opt.text = r.name; sel.appendChild(opt); });
}

document.getElementById('apply-role').addEventListener('click', async ()=>{
	const sel = document.querySelector('input[name=selected-user]:checked');
	const role = document.getElementById('role-select').value;
	if (!sel) return alert('Select a user');
	if (!role) return alert('Select a role');
	const userId = sel.value;
	await fetch('{{ url('admin/users') }}/' + userId + '/role', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({role})});
	loadUsers();
});
async function suspend(id){ await fetch(`{{ url('/admin/users') }}/${id}/suspend`, {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}); loadUsers(); }
async function reactivate(id){ await fetch(`{{ url('/admin/users') }}/${id}/reactivate`, {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}); loadUsers(); }
document.addEventListener('DOMContentLoaded', loadUsers);
document.addEventListener('DOMContentLoaded', loadRolesSelect);
</script>
@endsection
