
@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Organization Units</h2>
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <input id="org-name" placeholder="Organization name" />
        <input id="org-type" placeholder="Type (STATE/DISTRICT/AREA/VENUE)" />
        <button class="btn" id="create-org">Create</button>
    </div>

    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <select id="org-select"></select>
        <select id="user-select"></select>
        <button class="btn" id="assign-user">Assign User to Org</button>
    </div>

    <table class="table"><thead><tr><th>ID</th><th>Name</th><th>Type</th></tr></thead><tbody id="orgs-body"></tbody></table>
</div>

@section('scripts')
<script>
async function loadOrgs(){
    const res = await fetch('{{ route('admin.organizations.json') }}');
    const json = await res.json();
    const orgs = json.data || [];
    const body = document.getElementById('orgs-body'); body.innerHTML = '';
    const sel = document.getElementById('org-select'); sel.innerHTML = '';
    orgs.forEach(o=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${o.id}</td><td>${o.name}</td><td>${o.type}</td>`;
        body.appendChild(tr);
        const opt = document.createElement('option'); opt.value = o.id; opt.text = o.name; sel.appendChild(opt);
    });
}

async function loadUsers(){
    const res = await fetch('{{ route('admin.users.json') }}');
    const json = await res.json();
    const users = (json.data && json.data.data) ? json.data.data : (json.data || json);
    const sel = document.getElementById('user-select'); sel.innerHTML = '';
    users.forEach(u => { const opt = document.createElement('option'); opt.value = u.id; opt.text = u.name + ' <' + u.email + '>'; sel.appendChild(opt); });
}

document.getElementById('create-org').addEventListener('click', async ()=>{
    const name = document.getElementById('org-name').value;
    const type = document.getElementById('org-type').value || 'UNKNOWN';
    await fetch('{{ route('admin.organizations.store') }}', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({name,type})});
    document.getElementById('org-name').value=''; document.getElementById('org-type').value='';
    loadOrgs();
});

document.getElementById('assign-user').addEventListener('click', async ()=>{
    const orgId = document.getElementById('org-select').value;
    const userId = document.getElementById('user-select').value;
    if (!orgId || !userId) return alert('Select org and user');
    await fetch('{{ url('admin/organizations') }}/' + orgId + '/assign', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({user_id: userId})});
    alert('Assigned');
});

document.addEventListener('DOMContentLoaded', ()=>{ loadOrgs(); loadUsers(); });
</script>
@endsection

@endsection
