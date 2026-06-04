@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Workers</span>
    <h2>Organize on-ground staff for events and GameHub operations.</h2>
    <p>This lane is for volunteers, scorekeepers, support staff, and venue crew that should not have full admin privileges.</p>
</section>

<div class="card">
    <div class="list" id="workers-list">
        <div class="placeholder">Loading workers…</div>
    </div>
    <div style="margin-top:12px;"><a class="action" href="{{ route('admin.team.create', ['role' => 'WORKER']) }}">Create worker</a></div>
</div>
<script>
    (function(){
        const list = document.getElementById('workers-list');
        fetch('{{ url('/admin/team/WORKER/json') }}')
            .then(r=>r.json()).then(j=>{ list.innerHTML=''; (j.data||[]).forEach(u=>{ const d=document.createElement('div'); d.className='list-item'; d.innerHTML=`<strong>${u.name}</strong><span>${u.email} · ${u.status||'—'}</span>`; list.appendChild(d); }); }).catch(e=>{ list.innerHTML='<div class="placeholder">Failed</div>'; console.error(e); });
    })();
</script>
</div>
@endsection