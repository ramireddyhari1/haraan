@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Co-admins</span>
    <h2>Delegate control without handing over the whole platform.</h2>
    <p>Co-admins can be restricted to event moderation, GameHub fixtures, partner approvals, or support workflows.</p>
</section>

<div class="card">
    <div class="list" id="coadmins-list">
        <div class="placeholder">Loading co-admins…</div>
    </div>
    <div style="margin-top:12px;"><a class="action" href="{{ route('admin.team.create', ['role' => 'COADMIN']) }}">Create Co-admin</a></div>
</div>
<script>
    (function(){
        const list = document.getElementById('coadmins-list');
        fetch('{{ url('/admin/team/COADMIN/json') }}')
            .then(r=>r.json()).then(j=>{ list.innerHTML=''; (j.data||[]).forEach(u=>{ const d=document.createElement('div'); d.className='list-item'; d.innerHTML=`<strong>${u.name}</strong><span>${u.email} · ${u.status||'—'}</span>`; list.appendChild(d); }); }).catch(e=>{ list.innerHTML='<div class="placeholder">Failed</div>'; console.error(e); });
    })();
</script>
</div>
@endsection