@extends('site.layout')

@section('body_class', 'theme-minimal auth-page-body')

@section('content')
<style>
    .auth-page-body .site-footer, .auth-page-body .mfoot { display: none; }
    .authp { max-width: 400px; margin: 32px auto 56px; padding: 0 16px; }
    .authp__card { background: #fff; border: 1px solid #E9EDF3; border-radius: 22px; overflow: hidden; box-shadow: 0 18px 50px rgba(15,23,42,.08); }
    .authp__banner { background: linear-gradient(120deg, #2563EB 0%, #12B76A 100%); color: #fff; text-align: center; padding: 22px 22px 20px; }
    .authp__banner img { height: 30px; margin-bottom: 8px; filter: brightness(0) invert(1); }
    .authp__banner .wordmark { font-size: 22px; font-weight: 800; letter-spacing: .02em; }
    .authp__banner p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; opacity: .95; }
    .authp__body { padding: 24px 22px 26px; }
    .authp__body h3 { margin: 0 0 4px; font-size: 20px; font-weight: 800; text-align: center; color: #0F172A; letter-spacing: -0.02em; }
    .authp__body .subtitle { margin: 0 0 18px; font-size: 13.5px; color: #64748B; text-align: center; }
    .authp__foot { margin-top: 16px; text-align: center; font-size: 12px; color: #94A3B8; line-height: 1.6; }
    .authp__foot a { color: #64748B; text-decoration: underline; }
    .auth-alert { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 10px; padding: 9px 12px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }
    .auth-alert--ok { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
    .pw-form .auth-field { margin-bottom: 13px; text-align: left; }
    .pw-form .auth-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; letter-spacing: .01em; }
    .pw-form .auth-input { width: 100%; box-sizing: border-box; height: 46px; padding: 0 14px; font-size: 15px; color: #0F172A; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; transition: border-color .15s, box-shadow .15s, background .15s; }
    .pw-form .auth-input::placeholder { color: #94A3B8; }
    .pw-form .auth-input:focus { outline: none; background: #fff; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.14); }
    .pw-form .btn--large { margin-top: 4px; }
    .pw-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: -2px 0 14px; }
    .pw-meta a { font-size: 12.5px; font-weight: 600; color: #2563EB; text-decoration: none; }
    .pw-meta a:hover { text-decoration: underline; }
    .pw-switch { color: #0F172A; }
    .pw-form .auth-row { display: flex; gap: 10px; }
    .pw-form .auth-row .auth-field { flex: 1; }
    .pw-form select.auth-input { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 13px center; padding-right: 34px; }
</style>

<div class="authp">
    <div class="authp__card">
        <div class="authp__banner">
            <div class="wordmark">Haraan</div>
            <p>Book events, play sports, follow live scores — one account for both lanes.</p>
        </div>

        <div class="authp__body">
            @if(session('whatsapp_phone'))
                {{-- OTP entry: shown after a code has been requested for a number. --}}
                <h3>Verify your number</h3>
                <p class="subtitle">Enter the 6-digit code sent to your WhatsApp.</p>

                @if(session('success'))<div class="auth-alert auth-alert--ok" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="auth-alert" role="alert">{{ session('error') }}</div>@endif

                <form method="post" action="{{ route('whatsapp.verify.submit') }}" class="phone-form">
                    @csrf
                    <div class="field" style="margin-bottom: 14px;">
                        <input type="text" name="otp" class="otp-input" placeholder="123456" required maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="btn btn--solid btn--full btn--large">Verify &amp; continue</button>
                </form>

                <p class="authp__foot"><a href="{{ route('whatsapp.cancel') }}">Change mobile number</a></p>
            @else
                {{-- Log in or sign up: Google first, then email + password. --}}
                <h3>Log in or sign up</h3>
                <p class="subtitle">If you don't have an account yet, we'll create one for you</p>

                @if(session('error'))<div class="auth-alert" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="auth-alert" role="alert">{{ $errors->first() }}</div>@endif

                @if(config('services.google.client_id'))
                    <div class="auth-google">
                        <div id="googleSignInBtn" class="auth-google__btn"></div>
                        <p class="auth-google__error" id="googleSignInError" role="alert" hidden></p>
                    </div>
                    <div class="auth-divider"><span>or</span></div>
                @endif

                <form class="pw-form" id="authForm" method="POST" action="{{ route('site.password.login') }}" data-mode="login">
                    @csrf
                    <div class="auth-field auth-field--signup" id="nameField" hidden>
                        <label for="authName">Name</label>
                        <input type="text" name="name" id="authName" class="auth-input" placeholder="Your name" autocomplete="name" maxlength="60" disabled>
                    </div>
                    <div class="auth-row auth-field--signup" hidden>
                        <div class="auth-field">
                            <label for="authAge">Age</label>
                            <input type="number" name="age" id="authAge" class="auth-input" placeholder="Age" min="5" max="120" inputmode="numeric" disabled>
                        </div>
                        <div class="auth-field">
                            <label for="authGender">Gender</label>
                            <select name="gender" id="authGender" class="auth-input" disabled>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="authEmail">Email</label>
                        <input type="email" name="email" id="authEmail" class="auth-input" placeholder="you@example.com" value="{{ old('email') }}" required autocomplete="email" autocapitalize="off" spellcheck="false">
                    </div>
                    <div class="auth-field">
                        <label for="authPassword">Password</label>
                        <input type="password" name="password" id="authPassword" class="auth-input" placeholder="Your password" required autocomplete="current-password" minlength="6">
                    </div>
                    <div class="pw-meta">
                        <a href="#" id="signupToggle" class="pw-switch">Create new account</a>
                        <a href="{{ route('site.password.request') }}" class="pw-forgot-link" id="forgotLink">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn--solid btn--full btn--large" id="authSubmit">Continue</button>
                </form>

                <script>
                    (function () {
                        var form = document.getElementById('authForm');
                        if (!form) return;
                        var toggle = document.getElementById('signupToggle');
                        var nameField = document.getElementById('nameField');
                        var nameInput = document.getElementById('authName');
                        var pwInput = document.getElementById('authPassword');
                        var submit = document.getElementById('authSubmit');
                        var forgot = document.getElementById('forgotLink');
                        var heading = document.querySelector('.authp__body h3');
                        var subtitle = document.querySelector('.authp__body .subtitle');

                        function setMode(signup) {
                            form.dataset.mode = signup ? 'signup' : 'login';
                            form.querySelectorAll('.auth-field--signup').forEach(function (el) { el.hidden = !signup; });
                            form.querySelectorAll('.auth-field--signup input, .auth-field--signup select').forEach(function (inp) { inp.disabled = !signup; });
                            nameInput.required = signup;
                            submit.textContent = signup ? 'Create account' : 'Continue';
                            toggle.textContent = signup ? 'Have an account? Log in' : 'Create new account';
                            if (forgot) forgot.hidden = signup;
                            if (heading) heading.textContent = signup ? 'Create your account' : 'Log in or sign up';
                            if (subtitle) subtitle.textContent = signup ? 'Sign up with your email and a password.' : "If you don't have an account yet, we'll create one for you";
                            pwInput.setAttribute('autocomplete', signup ? 'new-password' : 'current-password');
                            if (signup) nameInput.focus();
                        }

                        toggle.addEventListener('click', function (e) {
                            e.preventDefault();
                            setMode(form.dataset.mode !== 'signup');
                        });
                    })();
                </script>

                <p class="authp__foot">By continuing, you agree to our<br><a href="#">Terms of Service</a> &nbsp; <a href="#">Privacy Policy</a></p>
            @endif
        </div>
    </div>
</div>
@endsection
