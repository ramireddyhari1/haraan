@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">Partner control</span>
	<h2>Approve and scope partner accounts by event lane or GameHub lane.</h2>
	<p>Partners can be limited to events, sports, or both, so their dashboard stays focused while the admin panel keeps overall control.</p>
</section>

<div class="card">
	<div class="list" id="partners-list">
		<div class="placeholder">Loading partners…</div>
	</div>
	<div style="margin-top:12px;"><a class="action" href="{{ route('partners.create') }}">Create partner</a></div>
</div>
<script>
	(function(){
		const list = document.getElementById('partners-list');
		fetch('{{ route('partners.json') }}')
			.then(r=>r.json()).then(j=>{
				list.innerHTML = '';
				(j.data||[]).forEach(p=>{
					const d = document.createElement('div'); d.className='list-item';
					d.innerHTML = `<strong>${p.name}</strong><span>${p.partner_type||'—'} · ${p.status||'—'}</span>`;
					const edit = document.createElement('a'); edit.href = '{{ url('/admin/partners') }}/' + p.id + '/edit'; edit.textContent = 'Edit'; edit.style.marginLeft = '12px';
					const del = document.createElement('button'); del.textContent = 'Delete'; del.style.marginLeft='8px'; del.onclick = function(){ if(!confirm('Delete partner?')) return; fetch('{{ url('/admin/partners') }}/' + p.id, { method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'} }).then(r=>r.json()).then(j=>{ alert(j.message||'Deleted'); location.reload(); }).catch(e=>alert('Failed')); };
					d.appendChild(edit); d.appendChild(del);
					list.appendChild(d);
				});
			}).catch(e=>{ list.innerHTML = '<div class="placeholder">Failed to load partners</div>'; console.error(e); });
	})();
</script>
</div>
<script>
	(function(){
		const list = document.getElementById('partners-list');
		fetch('{{ route('partners.json') }}')
			.then(r=>r.json()).then(j=>{
				list.innerHTML = '';
				(j.data||[]).forEach(p=>{
					const d = document.createElement('div'); d.className='list-item'; d.innerHTML = `<strong>${p.name}</strong><span>${p.partner_type||'—'} · ${p.status||'—'}</span>`; list.appendChild(d);
				});
			}).catch(e=>{ list.innerHTML = '<div class="placeholder">Failed to load partners</div>'; console.error(e); });
	})();
</script>
</div>
@endsection
