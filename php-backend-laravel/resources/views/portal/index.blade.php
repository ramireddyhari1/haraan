@extends('site.layout')

@section('content')
<div class="portal-page container center-xs">
    <div class="portal-card" style="max-width:760px;">
        <h1>ERP Portal</h1>
        <p>Admin and partner access are split so events, GameHub, co-admins, and workers stay on separate operational lanes.</p>
        <div class="portal-actions" style="flex-wrap:wrap;">
            <a href="{{ route('admin.login') }}" class="btn btn--ghost">Admin Login</a>
            <a href="{{ route('partner.login') }}" class="btn btn--solid">Partner Login</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn--ghost">Admin Dashboard</a>
            <a href="{{ route('partner.dashboard') }}" class="btn btn--ghost">Partner Dashboard</a>
        </div>
    </div>
</div>
@endsection
