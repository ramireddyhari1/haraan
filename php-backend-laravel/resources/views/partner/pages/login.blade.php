@extends('partner.partials.layout')

@section('content')
<div class="login-shell">
    <form class="login-card" method="post" action="{{ route('partner.login.submit') }}">
        @csrf
        <h1>Partner Login</h1>
        <p style="margin:0 0 14px;color:#64748b;">Access the partner portal for event and GameHub operations.</p>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" placeholder="partner@example.com" required>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" placeholder="********" required>
        </label>
        <label style="display:flex;align-items:center;gap:8px;margin:10px 0;">
            <input type="checkbox" name="remember"> <span>Remember me</span>
        </label>
        <button class="btn" type="submit">Sign In</button>
    </form>
</div>
@endsection