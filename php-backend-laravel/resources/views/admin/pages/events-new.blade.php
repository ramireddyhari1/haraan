@extends('admin.partials.layout')
@section('content')
<div class="card">
	<h2>Create Event</h2>
	<form id="event-create-form">
		<label class="field"><span>Title</span><input name="title" required></label>
		<label class="field"><span>Date</span><input type="date" name="date"></label>
		<label class="field"><span>Location</span><input name="location"></label>
		<label class="field"><span>Venue</span><input name="venue"></label>
		<label class="field"><span>Price</span><input type="number" step="0.01" name="price"></label>
		<label class="field"><span>Total Slots</span><input type="number" name="totalSlots"></label>
		<div style="display:flex;gap:8px;margin-top:12px;"><button class="btn" type="submit">Create</button><a class="action action--ghost" href="{{ route('admin.events') }}">Back</a></div>
	</form>
	<div id="create-result" style="margin-top:12px"></div>
</div>
<script>
	(function(){
		const form = document.getElementById('event-create-form');
		const result = document.getElementById('create-result');
		form.addEventListener('submit', function(e){
			e.preventDefault();
			result.innerHTML = '';
			const data = Object.fromEntries(new FormData(form).entries());

			fetch('{{ route('admin.events.store') }}', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
				body: JSON.stringify(data),
			}).then(r => r.json().then(j => ({status: r.status, body: j}))).then(obj => {
				if (obj.status >= 200 && obj.status < 300) {
					result.innerHTML = '<div class="placeholder">Event created successfully.</div>';
					setTimeout(()=> location.href = '{{ route('admin.events') }}', 800);
				} else {
					result.innerHTML = '<div class="placeholder">Error: ' + (obj.body.message || JSON.stringify(obj.body)) + '</div>';
				}
			}).catch(err => {
				result.innerHTML = '<div class="placeholder">Request failed</div>';
				console.error(err);
			});
		});
	})();
</script>
@endsection
