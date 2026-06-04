@extends('admin.partials.layout')

@section('content')
<div class="login-shell">
    <form class="login-card" method="post" action="{{ url()->current() == route('admin.password.request') ? route('admin.password.email') : route('partner.password.email') }}">
        @csrf
        <h1>Reset password</h1>
        <p style="margin:0 0 14px;color:#64748b;">Enter your account email and we'll send a reset link.</p>
        @if(session('status')) <div class="placeholder">{{ session('status') }}</div> @endif
        <label class="field"><span>Email</span><input type="email" name="email" required></label>
        <button class="btn" type="submit">Send reset link</button>
    </form>
</div>
@endsection
