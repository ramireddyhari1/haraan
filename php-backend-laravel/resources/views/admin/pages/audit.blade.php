@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Admin Audit Log</h2>
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <input id="audit-q" placeholder="Search actions or meta" />
        <button class="btn" id="audit-search">Search</button>
    </div>
    <div>
        <table class="table"><thead><tr><th>ID</th><th>Action</th><th>Actor</th><th>At</th><th>Meta</th></tr></thead><tbody id="audit-body"></tbody></table>
    </div>
</div>

@section('scripts')
<script>
async function loadAudit(q=''){
    const url = '{{ route('admin.audit.json') }}' + (q?('?q='+encodeURIComponent(q)):'');
    const res = await fetch(url);
    const json = await res.json();
    const rows = json.data.data || json.data;
    const body = document.getElementById('audit-body'); body.innerHTML = '';
    rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r.id}</td><td>${r.action}</td><td>${r.actor||'system'}</td><td>${r.created_at}</td><td>${r.meta||''}</td>`;
        body.appendChild(tr);
    });
}
document.getElementById('audit-search').addEventListener('click', ()=>{ loadAudit(document.getElementById('audit-q').value); });
document.addEventListener('DOMContentLoaded', ()=>loadAudit());
</script>
@endsection

@endsection
