@extends('partner.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Partner dashboard</span>
    <h2>Run event and GameHub operations from separate dashboards inside the same portal.</h2>
    <p>Partners can stay focused on either event programming or sports data entry, while the admin panel keeps oversight and permission control.</p>
</section>

<div class="grid-3" style="margin-bottom:12px;">
    @foreach($modules as $module)
        <section class="card metric">
            <div class="label">{{ $module['title'] }}</div>
            <div class="note">{{ $module['description'] }}</div>
            <div style="margin-top:12px;"><a class="action" href="{{ $module['link'] }}">Open</a></div>
        </section>
    @endforeach
</div>

<div class="grid-2">
    <section class="card">
        <h3 style="margin-top:0;">Operating principle</h3>
        <div class="list">
            <div class="list-item"><strong>Events stay separate</strong><span>Singing and stage events do not share the same workflow as GameHub.</span></div>
            <div class="list-item"><strong>GameHub stays separate</strong><span>Scores, brackets, and match boards have their own lane.</span></div>
        </div>
    </section>
    <section class="card">
        <h3 style="margin-top:0;">Next steps</h3>
        <div class="list">
            <div class="list-item"><strong>Invite workers</strong><span>Add hosts, volunteers, and scorekeepers.</span></div>
            <div class="list-item"><strong>Review assignments</strong><span>Check what the partner can edit or publish.</span></div>
        </div>
    </section>
</div>
@endsection