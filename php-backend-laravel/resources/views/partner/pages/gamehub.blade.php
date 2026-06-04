@extends('partner.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">GameHub lane</span>
    <h2>Run sports fixtures, live scoring, and leaderboard updates separately.</h2>
    <p>GameHub is reserved for match operations so partner teams can move quickly without touching event workflows.</p>
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