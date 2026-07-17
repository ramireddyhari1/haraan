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
                {{-- Log in or sign up: Google first, then mobile-number OTP. --}}
                <h3>Log in or sign up</h3>
                <p class="subtitle">If you don't have an account yet, we'll create one for you</p>

                @if(session('error'))<div class="auth-alert" role="alert">{{ session('error') }}</div>@endif

                @if(config('services.google.client_id'))
                    <div class="auth-google">
                        <div id="googleSignInBtn" class="auth-google__btn"></div>
                        <p class="auth-google__error" id="googleSignInError" role="alert" hidden></p>
                    </div>
                    <div class="auth-divider"><span>or</span></div>
                @endif

                <form class="phone-form" id="phoneLoginForm" method="POST" action="{{ route('whatsapp.request') }}">
                    @csrf
                    <input type="hidden" name="phone" id="hiddenPhoneField" value="">
                    <div class="phone-input-group">
                        <div class="country-selector">
                            <img src="https://flagcdn.com/w20/in.png" alt="India">
                            <span>+91</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <input type="tel" placeholder="Enter mobile number" id="phoneNumber" maxlength="10">
                    </div>
                    <button type="submit" class="btn btn--solid btn--full btn--large">Continue</button>
                </form>

                <p class="authp__foot">By continuing, you agree to our<br><a href="#">Terms of Service</a> &nbsp; <a href="#">Privacy Policy</a></p>
            @endif
        </div>
    </div>
</div>
@endsection
