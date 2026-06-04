@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:12px;">
    <span class="eyebrow">Admin control room</span>
    <h2>Manage events, GameHub, and partner operations from one command center.</h2>
    <p>Use the admin dashboard to keep event flows separate from GameHub flows, assign co-admins and workers, and supervise partner onboarding without mixing responsibilities.</p>
</section>

<div class="grid-5" style="margin-bottom:12px;">
    <a class="card action action--ghost" href="{{ route('admin.events.create') }}">+ New Event</a>
    <a class="card action action--ghost" href="{{ route('admin.partners.create') }}">+ New Partner</a>
    <a class="card action action--ghost" href="{{ route('admin.coupons') }}">Coupons</a>
    <a class="card action action--ghost" href="{{ route('admin.payouts') }}">Payouts</a>
    <a class="card action action--ghost" href="{{ route('admin.export.users') }}">Export Users</a>
</div>

<div class="grid-4" style="margin-bottom:12px;">
    @foreach($stats as $item)
    <section class="card metric">
        <div class="label">{{ $item['label'] }}</div>
        <div class="value">{{ $item['value'] }}</div>
        <div class="note">{{ $item['note'] }}</div>
    </section>
    @endforeach
</div>

<div class="grid-2">
    <section class="card">
        <h3 style="margin-top:0;">Primary modules</h3>
        <div class="list">
            @foreach($modules as $module)
                <div class="list-item">
                    <strong>{{ $module['title'] }}</strong>
                    <span>{{ $module['description'] }}</span>
                    <div style="margin-top:10px;"><a class="action" href="{{ $module['link'] }}">Open module</a></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h3 style="margin-top:0;">Management split</h3>
        <p class="subtle">Use separate dashboards for different operational lanes.</p>
        <div class="list">
            <div class="list-item">
                <strong>Events</strong>
                <span>Singing, dance, cultural, and community event flows.</span>
            </div>
            <div class="list-item">
                <strong>GameHub</strong>
                <span>Sports fixtures, live scoring, brackets, and results.</span>
            </div>
            <div class="list-item">
                <strong>People access</strong>
                <span>Partners, co-admins, and workers each get separate access scopes.</span>
            </div>
        </div>
        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
            <span class="muted-chip">ERP gate locked</span>
            <span class="muted-chip">Role-aware routes</span>
            <span class="muted-chip">Org-scoped data</span>
        </div>
    </section>
</div>
@endsection
