@extends('partner.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Workers</span>
    <h2>Track the people helping the partner run an event or GameHub program.</h2>
    <p>Use this to keep assignments clear for hosts, scorekeepers, and volunteer staff.</p>
</section>

<div class="card">
    <div class="list">
        @foreach($workers as $worker)
            <div class="list-item">
                <strong>{{ $worker['name'] }}</strong>
                <span>{{ $worker['detail'] }}</span>
            </div>
        @endforeach
    </div>
</div>
@endsection