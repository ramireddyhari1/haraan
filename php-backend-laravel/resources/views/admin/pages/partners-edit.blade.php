@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Edit Partner</h2>
    <form id="partner-edit-form">
        <input type="hidden" name="id" value="{{ $id }}">
        <label class="field"><span>Name</span><input name="name" required></label>
        <label class="field"><span>Email</span><input type="email" name="email" required></label>
        <label class="field"><span>Type</span><input name="partner_type"></label>
        <label class="field"><span>Status</span>
            <select name="status"><option value="ACTIVE">ACTIVE</option><option value="PENDING">PENDING</option><option value="SUSPENDED">SUSPENDED</option></select>
        </label>
        <label class="field"><span>New password (leave blank to keep)</span><input type="password" name="password"></label>
        <div style="display:flex;gap:8px;margin-top:12px;"><button class="btn" type="submit">Save</button><a class="action action--ghost" href="{{ route('admin.partners') }}">Back</a></div>
    </form>
    <div id="partner-edit-result" style="margin-top:12px"></div>
</div>

<script>
    (function(){
        const id = '{{ $id }}';
        const form = document.getElementById('partner-edit-form');
        const result = document.getElementById('partner-edit-result');

        fetch('/api/users/' + id).then(r=>r.json()).then(j=>{ const p = j.data || j; if (!p) { result.innerHTML = '<div class="placeholder">Not found</div>'; return; } form.name.value = p.name || ''; form.email.value = p.email || ''; form.partner_type.value = p.partner_type || ''; form.status.value = p.status || 'ACTIVE'; }).catch(e=>{ result.innerHTML = '<div class="placeholder">Failed to load</div>'; console.error(e); });

        form.addEventListener('submit', function(e){
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            fetch('{{ url('/admin/partners') }}/' + id, { method: 'PUT', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(data) })
                .then(r=>r.json().then(j=>({s:r.status,b:j}))).then(obj=>{ if (obj.s>=200 && obj.s<300) { result.innerHTML = '<div class="placeholder">Saved</div>'; setTimeout(()=>location.href='{{ route('admin.partners') }}',700);} else { result.innerHTML = '<div class="placeholder">Error</div>'; } }).catch(err=>{ result.innerHTML = '<div class="placeholder">Request failed</div>'; console.error(err); });
        });
    })();
</script>

@endsection
