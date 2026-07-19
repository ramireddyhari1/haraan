@extends('site.layout')

@section('body_class', 'theme-minimal auth-page-body')

@section('content')
<style>
    .auth-page-body .site-footer, .auth-page-body .mfoot { display: none; }
    .authp { max-width: 400px; margin: 32px auto 56px; padding: 0 16px; }
    .authp__card { background: #fff; border: 1px solid #E9EDF3; border-radius: 22px; overflow: hidden; box-shadow: 0 18px 50px rgba(15,23,42,.08); }
    .authp__banner { background: linear-gradient(120deg, #2563EB 0%, #12B76A 100%); color: #fff; text-align: center; padding: 22px 22px 20px; }
    .authp__banner .wordmark { font-size: 22px; font-weight: 800; letter-spacing: .02em; }
    .authp__banner p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; opacity: .95; }
    .authp__body { padding: 24px 22px 26px; }
    .authp__body h3 { margin: 0 0 4px; font-size: 20px; font-weight: 800; text-align: center; color: #0F172A; letter-spacing: -0.02em; }
    .authp__body .subtitle { margin: 0 0 18px; font-size: 13.5px; color: #64748B; text-align: center; line-height: 1.5; }
    .authp__foot { margin-top: 16px; text-align: center; font-size: 12.5px; color: #94A3B8; }
    .authp__foot a { color: #2563EB; text-decoration: none; font-weight: 600; }
    .auth-alert { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 10px; padding: 9px 12px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }
    .pw-form .auth-field { margin-bottom: 13px; text-align: left; }
    .pw-form .auth-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
    .pw-form .auth-input { width: 100%; box-sizing: border-box; height: 46px; padding: 0 14px; font-size: 15px; color: #0F172A; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; transition: border-color .15s, box-shadow .15s, background .15s; }
    .pw-form .auth-input::placeholder { color: #94A3B8; }
    .pw-form .auth-input:focus { outline: none; background: #fff; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.14); }
</style>

<div class="authp">
    <div class="authp__card">
        <div class="authp__banner">
            <div class="wordmark">Haraan</div>
            <p>Set a new password</p>
        </div>

        <div class="authp__body">
            <h3>Choose a new password</h3>
            <p class="subtitle">Enter a new password for your account.</p>

            @if($errors->any())<div class="auth-alert" role="alert">{{ $errors->first() }}</div>@endif

            <form class="pw-form" method="POST" action="{{ route('site.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="auth-field">
                    <label for="rpEmail">Email</label>
                    <input type="email" name="email" id="rpEmail" class="auth-input" placeholder="you@example.com" value="{{ old('email', $email) }}" required autocomplete="email" autocapitalize="off" spellcheck="false">
                </div>
                <div class="auth-field">
                    <label for="rpPassword">New password</label>
                    <input type="password" name="password" id="rpPassword" class="auth-input" placeholder="At least 6 characters" required autocomplete="new-password" minlength="6" autofocus>
                </div>
                <div class="auth-field">
                    <label for="rpPassword2">Confirm password</label>
                    <input type="password" name="password_confirmation" id="rpPassword2" class="auth-input" placeholder="Re-enter password" required autocomplete="new-password" minlength="6">
                </div>
                <button type="submit" class="btn btn--solid btn--full btn--large">Update password</button>
            </form>

            <p class="authp__foot"><a href="{{ route('site.login') }}">&larr; Back to login</a></p>
        </div>
    </div>
</div>
@endsection
