@extends('site.layout')

@section('body_class', 'theme-minimal auth-page-body')

@section('content')
<style>
    /* ---- Login, BookMyShow-style progressive choices (scoped to .lgn) ------- */
    .auth-page-body .site-footer, .auth-page-body .mfoot { display: none; }
    .auth-page-body {
        background:
            radial-gradient(55% 45% at 12% 0%, rgba(37,99,235,.08), transparent 60%),
            radial-gradient(50% 40% at 100% 15%, rgba(16,185,129,.08), transparent 60%),
            #F4F6FA;
        min-height: 100vh;
    }
    .lgn { max-width: 420px; margin: 44px auto 64px; padding: 0 18px; }
    .lgn__card { background: #fff; border: 1px solid #EAEEF5; border-radius: 22px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 26px 56px -30px rgba(15,23,42,.26); }

    .lgn__head { padding: 30px 30px 8px; text-align: center; position: relative; }
    .lgn__head::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #2563EB 0%, #10B981 100%); }
    .lgn__brand { display: inline-block; margin-bottom: 12px; line-height: 0; }
    .lgn__brand img { height: 30px; width: auto; display: block; }
    .lgn__title { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 22px; font-weight: 800; color: #0F172A; letter-spacing: -.02em; }
    .lgn__sub { margin: 6px 0 0; font-size: 13px; color: #64748B; }

    .lgn__body { padding: 20px 30px 28px; }
    .lgn-alert { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 12px; padding: 10px 13px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }

    /* The three uniform choices. */
    .lgn__choices { display: flex; flex-direction: column; gap: 12px; }
    .lgn__choices[hidden] { display: none; } /* flex above would otherwise defeat [hidden] */
    .lgn .auth-google { display: flex; justify-content: center; }
    .lgn .auth-google__btn { width: 100%; display: flex; justify-content: center; min-height: 50px; }
    .lgn .auth-google__error { color: #B91C1C; font-size: 12.5px; font-weight: 600; text-align: center; margin: 2px 0 0; }

    /* Custom "Continue with X" buttons — icon pinned left, label centered (BMS look). */
    .lgn__opt {
        position: relative; width: 100%; height: 50px; border: 1.5px solid #E2E7F0; border-radius: 12px;
        background: #fff; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 700; color: #1F2937;
        display: flex; align-items: center; justify-content: center; transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .lgn__opt:hover { border-color: #C7D0DE; background: #F9FAFC; }
    .lgn__opt svg { position: absolute; left: 16px; width: 20px; height: 20px; }

    /* Panels revealed after a choice. */
    .lgn__panel[hidden] { display: none; }
    .lgn__back { display: inline-flex; align-items: center; gap: 6px; background: none; border: 0; padding: 0; margin: 0 0 16px; cursor: pointer; color: #64748B; font-size: 13px; font-weight: 600; }
    .lgn__back:hover { color: #2563EB; }
    .lgn__back svg { width: 15px; height: 15px; }

    /* Email + password form */
    .lgn .pw-form .auth-field { margin-bottom: 13px; text-align: left; }
    .lgn .pw-form .auth-field label { display: block; font-size: 11.5px; font-weight: 700; color: #64748B; margin-bottom: 6px; letter-spacing: .02em; text-transform: uppercase; }
    .lgn .pw-form .auth-input { width: 100%; box-sizing: border-box; height: 48px; padding: 0 15px; font-size: 15px; color: #0F172A; background: #fff; border: 1.5px solid #E4E9F1; border-radius: 12px; transition: border-color .15s, box-shadow .15s; }
    .lgn .pw-form .auth-input::placeholder { color: #9AA6B8; }
    .lgn .pw-form .auth-input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .lgn .pw-form .auth-row { display: flex; gap: 10px; }
    .lgn .pw-form .auth-row[hidden] { display: none; }
    .lgn .pw-form .auth-row .auth-field { flex: 1; }
    .lgn .pw-form select.auth-input { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
    .lgn__meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 2px 0 16px; }
    .lgn__meta a { font-size: 12.5px; font-weight: 600; color: #2563EB; text-decoration: none; }
    .lgn__meta a:hover { text-decoration: underline; }

    /* Primary action — solid brand blue, clean (BMS uses a single solid accent). */
    .lgn__primary { width: 100%; height: 50px; border: 0; border-radius: 12px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15.5px; font-weight: 700; color: #fff; background: #2563EB; box-shadow: 0 8px 18px -8px rgba(37,99,235,.5); transition: background .15s, transform .12s; }
    .lgn__primary:hover { background: #1D4ED8; transform: translateY(-1px); }

    .lgn__foot { margin-top: 22px; text-align: center; font-size: 12px; color: #94A3B8; line-height: 1.7; }
    .lgn__foot a { color: #2563EB; text-decoration: none; font-weight: 600; }
    .lgn__foot a:hover { text-decoration: underline; }

    .lgn .otp-input { width: 100%; box-sizing: border-box; height: 52px; text-align: center; letter-spacing: .5em; font-size: 20px; font-weight: 700; color: #0F172A; background: #fff; border: 1.5px solid #E4E9F1; border-radius: 12px; }
    .lgn .otp-input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
</style>

<div class="lgn">
    <div class="lgn__card">
        <div class="lgn__head">
            <div class="lgn__brand"><img src="{{ asset('images/haraan-logo-blue.png') }}" alt="Haraan"></div>
            @if(session('whatsapp_phone'))
                <h1 class="lgn__title">Verify your number</h1>
                <p class="lgn__sub">Enter the 6-digit code sent to your WhatsApp.</p>
            @else
                <h1 class="lgn__title">Get started</h1>
                <p class="lgn__sub">Tickets and play — one account for both.</p>
            @endif
        </div>

        <div class="lgn__body">
            @if(session('whatsapp_phone'))
                @if(session('success'))<div class="lgn-alert" style="background:#ECFDF5;color:#047857;border-color:#A7F3D0;" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="lgn-alert" role="alert">{{ session('error') }}</div>@endif
                <form method="post" action="{{ route('whatsapp.verify.submit') }}">
                    @csrf
                    <input type="text" name="otp" class="otp-input" placeholder="••••••" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" style="margin-bottom:16px;">
                    <button type="submit" class="lgn__primary">Verify &amp; continue</button>
                </form>
                <p class="lgn__foot"><a href="{{ route('whatsapp.cancel') }}">Change mobile number</a></p>
            @else
                @if(session('error'))<div class="lgn-alert" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="lgn-alert" role="alert">{{ $errors->first() }}</div>@endif

                {{-- Step 1: the choices. Google, phone, email — fields stay hidden until chosen. --}}
                <div class="lgn__choices" data-lgn-choices>
                    @if(config('services.google.client_id'))
                        <div class="auth-google">
                            <div class="auth-google__btn"></div>
                        </div>
                        <p class="auth-google__error" role="alert" hidden></p>
                    @endif

                    @if(config('services.firebase.api_key'))
                        <button type="button" class="lgn__opt" data-lgn-open="phone">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            Continue with phone
                        </button>
                    @endif

                    <button type="button" class="lgn__opt" data-lgn-open="email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        Continue with Email
                    </button>
                </div>

                {{-- Step 2a: phone (Firebase OTP). --}}
                @if(config('services.firebase.api_key'))
                    <div class="lgn__panel" data-lgn-panel="phone" hidden>
                        <button type="button" class="lgn__back" data-lgn-back>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                            All sign-in options
                        </button>
                        @include('site.partials.auth-phone')
                    </div>
                @endif

                {{-- Step 2b: email + password. --}}
                <div class="lgn__panel" data-lgn-panel="email" hidden>
                    <button type="button" class="lgn__back" data-lgn-back>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        All sign-in options
                    </button>
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
                        <div class="lgn__meta">
                            <a href="#" id="signupToggle">Create new account</a>
                            <a href="{{ route('site.password.request') }}" id="forgotLink">Forgot password?</a>
                        </div>
                        <button type="submit" class="lgn__primary" id="authSubmit">Continue</button>
                    </form>
                </div>

                <p class="lgn__foot">By continuing, you agree to our <a href="{{ route('site.legal', 'terms') }}">Terms of Service</a> and <a href="{{ route('site.legal', 'privacy') }}">Privacy Policy</a></p>

                <script>
                    (function () {
                        var root = document.querySelector('.lgn__body');
                        if (!root) return;
                        var choices = root.querySelector('[data-lgn-choices]');
                        var panels = root.querySelectorAll('[data-lgn-panel]');

                        function open(name) {
                            if (choices) choices.hidden = true;
                            panels.forEach(function (p) { p.hidden = p.getAttribute('data-lgn-panel') !== name; });
                            var active = root.querySelector('[data-lgn-panel="' + name + '"]');
                            var focusable = active && active.querySelector('input, select');
                            if (focusable) setTimeout(function () { focusable.focus(); }, 30);
                        }
                        function back() {
                            panels.forEach(function (p) { p.hidden = true; });
                            if (choices) choices.hidden = false;
                        }

                        root.querySelectorAll('[data-lgn-open]').forEach(function (btn) {
                            btn.addEventListener('click', function () { open(btn.getAttribute('data-lgn-open')); });
                        });
                        root.querySelectorAll('[data-lgn-back]').forEach(function (btn) {
                            btn.addEventListener('click', back);
                        });

                        // Email panel: toggle between "log in" and "sign up" (extra fields).
                        var form = document.getElementById('authForm');
                        if (form) {
                            var toggle = document.getElementById('signupToggle');
                            var nameInput = document.getElementById('authName');
                            var pwInput = document.getElementById('authPassword');
                            var submit = document.getElementById('authSubmit');
                            var forgot = document.getElementById('forgotLink');

                            function setMode(signup) {
                                form.dataset.mode = signup ? 'signup' : 'login';
                                form.querySelectorAll('.auth-field--signup').forEach(function (el) { el.hidden = !signup; });
                                form.querySelectorAll('.auth-field--signup input, .auth-field--signup select').forEach(function (inp) { inp.disabled = !signup; });
                                nameInput.required = signup;
                                submit.textContent = signup ? 'Create account' : 'Continue';
                                toggle.textContent = signup ? 'Have an account? Log in' : 'Create new account';
                                if (forgot) forgot.hidden = signup;
                                pwInput.setAttribute('autocomplete', signup ? 'new-password' : 'current-password');
                                if (signup) nameInput.focus();
                            }
                            toggle.addEventListener('click', function (e) { e.preventDefault(); setMode(form.dataset.mode !== 'signup'); });
                        }

                        // If the server bounced back a validation error, open the email panel so it's visible.
                        @if($errors->any() && old('email'))
                            open('email');
                        @endif
                    })();
                </script>
            @endif
        </div>
    </div>
</div>
@endsection
