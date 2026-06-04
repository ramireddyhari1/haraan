@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
	<span class="eyebrow">Event lane</span>
	<h2>Separate event administration for singing, dance, cultural, and sports programs.</h2>
	<p>Keep event registration, schedules, approvals, and venue assignments distinct from GameHub tournaments.</p>
</section>

<div class="grid-2">
	<section class="card">
		<h3 style="margin-top:0;">Events</h3>
		<div id="events-list" class="list">
			<div class="placeholder">Loading events…</div>
		</div>
	</section>

	<section class="card">
		<h3 style="margin-top:0;">Actions</h3>
		<div class="list">
			<div class="list-item"><strong>Open create flow</strong><span>Use the event wizard for a new singing or sports event.</span></div>
			<div class="list-item"><strong>Assign co-admins</strong><span>Delegate event moderation and review tasks.</span></div>
			<div class="list-item"><strong>Allocate workers</strong><span>Set volunteers, hosts, and support staff per event.</span></div>
		</div>
		<div style="margin-top:12px;"><a class="action" href="{{ route('admin.events.create') }}">Create event</a></div>
	</section>
</div>
<script>
	(function(){
		const listEl = document.getElementById('events-list');
		fetch('{{ route('admin.events.json') }}')
			.then(r => r.json())
			.then(data => {
				listEl.innerHTML = '';
				if (!data.data || data.data.length === 0) {
					listEl.innerHTML = '<div class="placeholder">No events found</div>';
					return;
				}
				data.data.forEach(ev => {
					const node = document.createElement('div');
					node.className = 'list-item';
					node.innerHTML = `<strong>${ev.title}</strong><span>${ev.date || '—'} · ${ev.status || '—'}</span>`;
					const del = document.createElement('button');
					del.textContent = 'Delete';
					del.style.marginLeft = '12px';
					del.onclick = function(){
						if (!confirm('Delete this event?')) return;
						fetch('{{ url('/admin/events') }}/' + ev.id, { method: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
							.then(r => r.json()).then(j=>{ alert(j.message || 'Deleted'); location.reload(); }).catch(e=>alert('Failed'));
					};
					node.appendChild(del);
					listEl.appendChild(node);
				});
			}).catch(err => {
				listEl.innerHTML = '<div class="placeholder">Failed to load events</div>';
				console.error(err);
			});
	})();
</script>
@endsection
