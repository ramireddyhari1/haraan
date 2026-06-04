@extends('partner.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Event lane</span>
    <h2>Manage partner events like singing, dance, and local competitions.</h2>
    <p>Use this lane only for event-related setup, keeping it separate from sports and GameHub tasks.</p>
</section>

<div class="card">
    <div class="list">
        @foreach($tracks as $track)
            <div class="list-item">
                <strong>{{ $track['name'] }}</strong>
                <span>{{ $track['detail'] }}</span>
            </div>
        @endforeach
    </div>
</div>
@endsection