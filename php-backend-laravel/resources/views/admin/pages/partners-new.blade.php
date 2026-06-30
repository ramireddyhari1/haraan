@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Create Partner</h2>
    <form id="partner-create-form">
        <label class="field"><span>Name</span><input name="name" required></label>
        <label class="field"><span>Email</span><input type="email" name="email" required></label>
        <label class="field"><span>Type</span><input name="partner_type"></label>
        <div style="display:flex;gap:8px;margin-top:12px;"><button class="btn" type="submit">Create</button><a class="action action--ghost" href="{{ route('admin.partners') }}">Back</a></div>
    </form>
    <div id="partner-result" style="margin-top:12px"></div>
</div>
<script>
    (function(){
        const form = document.getElementById('partner-create-form');
        const result = document.getElementById('partner-result');
        form.addEventListener('submit', function(e){
            e.preventDefault();
            result.innerHTML = '';
            const data = Object.fromEntries(new FormData(form).entries());
            fetch('{{ route('admin.partners.store') }}', {
                method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(data)
            }).then(r=>r.json().then(j=>({s:r.status,b:j}))).then(o=>{
                if (o.s>=200 && o.s<300) { result.innerHTML = '<div class="placeholder">Partner created</div>'; setTimeout(()=>location.href='{{ route('admin.partners') }}',800);} else { result.innerHTML = '<div class="placeholder">Error</div>'; }
            }).catch(err=>{ result.innerHTML = '<div class="placeholder">Failed</div>'; console.error(err); });
        });
    })();
</script>
@endsection
