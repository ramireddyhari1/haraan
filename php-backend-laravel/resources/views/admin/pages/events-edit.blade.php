@extends('admin.partials.layout')

@section('content')
<div class="card">
    <h2>Edit Event</h2>
    <form id="event-edit-form">
        <input type="hidden" name="id" value="{{ $id }}">
        <label class="field"><span>Title</span><input name="title" required></label>
        <label class="field"><span>Date</span><input type="date" name="date"></label>
        <label class="field"><span>Location</span><input name="location"></label>
        <label class="field"><span>Venue</span><input name="venue"></label>
        <label class="field"><span>Price</span><input type="number" step="0.01" name="price"></label>
        <label class="field"><span>Total Slots</span><input type="number" name="totalSlots"></label>
        <label class="field"><span>Status</span>
            <select name="status">
                <option value="DRAFT">DRAFT</option>
                <option value="PUBLISHED">PUBLISHED</option>
                <option value="CANCELLED">CANCELLED</option>
            </select>
        </label>
        <div style="display:flex;gap:8px;margin-top:12px;"><button class="btn" type="submit">Save</button><a class="action action--ghost" href="{{ route('admin.events') }}">Back</a></div>
    </form>
    <div id="edit-result" style="margin-top:12px"></div>
</div>

<script>
(function(){
    const id = '{{ $id }}';
    const form = document.getElementById('event-edit-form');
    const result = document.getElementById('edit-result');

    fetch('/api/events/' + id)
        .then(r => r.json())
        .then(j => {
            const ev = j.data || j;
            if (!ev) { result.innerHTML = '<div class="placeholder">Event not found</div>'; return; }
            form.title.value = ev.title || '';
            form.date.value = ev.date ? ev.date.split('T')[0] : '';
            form.location.value = ev.location || '';
            form.venue.value = ev.venue || '';
            form.price.value = ev.price || '';
            form.totalSlots.value = ev.total_slots || ev.totalSlots || '';
            form.status.value = ev.status || 'DRAFT';
        }).catch(e=>{ console.error(e); result.innerHTML = '<div class="placeholder">Failed to load</div>'; });

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        fetch('/admin/events/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(data),
        }).then(r=>r.json().then(j=>({s:r.status,b:j}))).then(obj=>{
            if (obj.s >=200 && obj.s < 300) { result.innerHTML = '<div class="placeholder">Saved</div>'; setTimeout(()=> location.href='{{ route('admin.events') }}',800); }
            else { result.innerHTML = '<div class="placeholder">Error: '+(obj.b.message || JSON.stringify(obj.b))+'</div>'; }
        }).catch(err=>{ console.error(err); result.innerHTML = '<div class="placeholder">Request failed</div>'; });
    });
})();
</script>

@endsection
