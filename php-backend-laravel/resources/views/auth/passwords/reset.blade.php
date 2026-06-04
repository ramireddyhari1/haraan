@extends('admin.partials.layout')

@section('content')
<div class="login-shell">
    <form class="login-card" method="post" action="{{ url()->current() == route('admin.password.reset', ['token' => $token]) ? route('admin.password.update') : route('partner.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1>Set new password</h1>
        <p style="margin:0 0 14px;color:#64748b;">Create a new password for your account.</p>
        <label class="field"><span>Email</span><input type="email" name="email" value="{{ $email ?? '' }}" required></label>
        <label class="field"><span>New password</span><input type="password" name="password" required></label>
        <label class="field"><span>Confirm password</span><input type="password" name="password_confirmation" required></label>
        <button class="btn" type="submit">Reset password</button>
    </form>
</div>
@endsection
