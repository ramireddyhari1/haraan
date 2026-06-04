@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Create {{ $role }}</h2>
    <form id="team-create-form">
        <label class="field"><span>Name</span><input name="name" required></label>
        <label class="field"><span>Email</span><input type="email" name="email" required></label>
        <div style="display:flex;gap:8px;margin-top:12px;"><button class="btn" type="submit">Create</button><a class="action action--ghost" href="{{ url()->previous() }}">Back</a></div>
    </form>
    <div id="team-result" style="margin-top:12px"></div>
</div>
<script>
    (function(){
        const form = document.getElementById('team-create-form');
        const result = document.getElementById('team-result');
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            fetch('{{ url('/admin/team') }}/{{ $role }}', { method:'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(data)})
                .then(r=>r.json().then(j=>({s:r.status,b:j}))).then(o=>{ if (o.s>=200 && o.s<300) { result.innerHTML='<div class="placeholder">Created</div>'; setTimeout(()=>history.back(),700);} else { result.innerHTML='<div class="placeholder">Error</div>'; } }).catch(err=>{ result.innerHTML='<div class="placeholder">Failed</div>'; console.error(err); });
        });
    })();
</script>

@endsection
