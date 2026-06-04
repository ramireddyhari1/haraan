@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Roles & Permissions</h2>
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <input id="role-name" placeholder="Role name" />
        <button class="btn" id="create-role">Create Role</button>
    </div>
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <input id="perm-name" placeholder="Permission name" />
        <button class="btn" id="create-perm">Create Permission</button>
    </div>
    <div>
        <table class="table"><thead><tr><th>Name</th><th>Permissions</th><th>Actions</th></tr></thead><tbody id="roles-body"></tbody></table>
    </div>
</div>

@section('scripts')
<script>
let allPermissions = [];
async function loadPermissions(){
    const res = await fetch('{{ route('admin.roles.permissions.json') }}');
    const json = await res.json();
    allPermissions = json.data || [];
}

function renderPermissionCheckboxes(role){
    return allPermissions.map(p => {
        const checked = (role.permissions||[]).some(rp=>rp.name===p.name) ? 'checked' : '';
        return `<label style="margin-right:8px"><input type="checkbox" data-perm="${p.name}" ${checked}/> ${p.name}</label>`;
    }).join('');
}

async function loadRoles(){
    await loadPermissions();
    const res = await fetch('{{ route('admin.roles.json') }}');
    const json = await res.json();
    const rows = json.data || json;
    const body = document.getElementById('roles-body'); body.innerHTML = '';
    rows.forEach(r => {
        const tr = document.createElement('tr');
        const permsHtml = renderPermissionCheckboxes(r);
        tr.innerHTML = `<td>${r.name}</td><td>${permsHtml}</td><td><button class="btn" data-role-id="${r.id}">Save</button></td>`;
        body.appendChild(tr);
    });
    // attach save handlers
    body.querySelectorAll('button[data-role-id]').forEach(btn => {
        btn.addEventListener('click', async e=>{
            const roleId = e.currentTarget.dataset.roleId;
            const row = e.currentTarget.closest('tr');
            const checked = Array.from(row.querySelectorAll('input[type=checkbox]:checked')).map(cb=>cb.dataset.perm);
            await fetch('{{ url('admin/roles') }}/' + roleId, {method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({permissions: checked})});
            loadRoles();
        });
    });
}
document.getElementById('create-role').addEventListener('click', async function(){
    const name = document.getElementById('role-name').value;
    await fetch('{{ route('admin.roles.store') }}', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({name})});
    document.getElementById('role-name').value = '';
    loadRoles();
});

document.getElementById('create-perm').addEventListener('click', async function(){
    const name = document.getElementById('perm-name').value;
    await fetch('{{ route('permissions.store') }}', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({name})});
    document.getElementById('perm-name').value = '';
    loadRoles();
});
document.addEventListener('DOMContentLoaded', loadRoles);
</script>
@endsection

@endsection
