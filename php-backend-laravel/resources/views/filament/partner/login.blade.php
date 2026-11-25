{{--
    Partner console sign-in form — the same screen as the Android partner app's
    PartnerSignIn.kt: "Sign in", one grey phone field, a 6-digit code on WhatsApp,
    then "or sign in with" Google | Email. On phones it is the white sheet over the
    navy band painted by auth-brand.blade.php; on desktop it is the right column.

    firebase-phone-auth.js still owns the phone flow (it finds .js-phone, .js-send,
    .js-code, .js-verify, .js-resend, .js-change, .js-error by class), so those
    elements keep their classes; this file only lays them out and reads their state.
    Self-contained (markup + styles + scripts): no theme rebuild.
--}}
@php
    $hasFirebase = (bool) config('services.firebase.api_key');
    $hasGoogle   = (bool) config('services.google.client_id');
@endphp

{{-- Single root element — this view backs a Livewire component (Filament's login
     page), which permits exactly one root node. Everything lives inside it. --}}
<div class="plgn-root">

{{-- firebase-phone-auth.js + our fetch helpers read the CSRF token from here. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="plgn" id="plgn" data-step="{{ $hasFirebase ? 'phone' : 'email' }}">
    <div class="plgn__head">
        <h1 class="plgn__title" id="plgnTitle">{{ $hasFirebase ? 'Sign in' : 'Sign in with email' }}</h1>
        <p class="plgn__sub" id="plgnSub">{{ $hasFirebase ? 'Use the mobile number your venue or events are registered with.' : 'The email and password your Haraan partner account was set up with.' }}</p>
    </div>

    <p class="plgn__alert" id="plgnAlert" role="alert" hidden></p>

    @if ($hasFirebase)
        {{-- Phone OTP. firebase-phone-auth.js wires this by [data-phone-auth]. --}}
        <div class="auth-phone" data-phone-auth
             data-post-url="{{ route('partner.auth.phone') }}"
             data-precheck-url="{{ route('partner.auth.check-phone') }}"
             {{-- WhatsApp first, Firebase SMS underneath. Runs AFTER the pre-check
                  above, so a number with no partner account never gets a code from
                  either channel. surface=partner makes the server resolve an
                  existing partner and never create an account. --}}
             data-otp-start-url="{{ route('whatsapp.otp.start') }}"
             data-otp-verify-url="{{ route('whatsapp.otp.verify') }}"
             data-otp-surface="partner">
            <div class="auth-phone__enter">
                <label class="plgn-sr" for="plgnPhone">Mobile number</label>
                <div class="plgn-field plgn-field--phone">
                    {{-- Drawn flag: the 🇮🇳 emoji renders as the letters "IN" on Windows. --}}
                    <svg class="plgn-flag" viewBox="0 0 22 15" aria-hidden="true"><rect width="22" height="5" fill="#FF9933"/><rect y="5" width="22" height="5" fill="#fff"/><rect y="10" width="22" height="5" fill="#138808"/><circle cx="11" cy="7.5" r="2.1" fill="none" stroke="#000080" stroke-width=".9"/><rect x=".5" y=".5" width="21" height="14" rx="2" fill="none" stroke="rgba(0,0,0,.1)"/></svg>
                    <span class="plgn-cc">+91</span>
                    <span class="plgn-field__rule" aria-hidden="true"></span>
                    <input type="tel" id="plgnPhone" class="js-phone" placeholder="98765 43210" autocomplete="tel-national" inputmode="tel" maxlength="16">
                    <svg class="plgn-check" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10"/><path d="M5.6 10.4l3.2 3 5.6-6.2"/></svg>
                </div>
                <p class="plgn-hint">
                    <svg class="plgn-wa" viewBox="0 0 14 14" aria-hidden="true"><path d="M2.8 9.5L.8 13.4l4.5-1.4z" fill="#25D366"/><circle cx="7" cy="7" r="6.4" fill="#25D366"/><path d="M5.3 4.6a2.8 2.8 0 0 0 4.2 4.6" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>
                    We'll send a 6-digit code on WhatsApp
                </p>
                <button type="button" class="plgn-btn auth-phone__btn js-send is-off">Continue</button>
            </div>

            <div class="auth-phone__code" hidden>
                <p class="plgn-otp__to">
                    <svg class="plgn-wa" viewBox="0 0 14 14" aria-hidden="true"><path d="M2.8 9.5L.8 13.4l4.5-1.4z" fill="#25D366"/><circle cx="7" cy="7" r="6.4" fill="#25D366"/><path d="M5.3 4.6a2.8 2.8 0 0 0 4.2 4.6" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <span id="plgnOtpTo">Sent on WhatsApp</span>
                    <a href="#" class="plgn-link js-change">Change</a>
                </p>
                {{-- ONE real input (keyboard, paste, one-time-code autofill all keep
                     working) laid invisibly over six drawn cells. --}}
                <div class="plgn-otp">
                    <input type="text" class="js-code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" aria-label="6-digit code">
                    <div class="plgn-otp__cells" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
                </div>
                <p class="plgn-otp__checking" id="plgnChecking" hidden><i></i>Checking the code</p>
                {{-- The code verifies itself on the sixth digit; the button stays in
                     the DOM for the shared script but isn't shown. --}}
                <button type="button" class="js-verify" hidden>Verify</button>
                <p class="plgn-resend">Didn't get it? <button type="button" class="js-resend" disabled>Resend code</button></p>
            </div>
            <p class="plgn-error js-error" role="alert" hidden></p>
            <div class="js-recaptcha"></div>
        </div>

        <div class="plgn-alt" id="plgnAlt">
            <div class="plgn-or"><span>or sign in with</span></div>
            <div class="plgn-alt__row">
                @if ($hasGoogle)
                    {{-- Our own button face; Google's real button sits over it,
                         near-invisible, so the tap is Google's own (GIS needs that). --}}
                    <div class="plgn-alt__btn plgn-alt__btn--google" id="plgnGoogle">
                        <svg viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 0 1 3.68 9c0-.6.1-1.18.29-1.72V4.95H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.05z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
                        <span>Google</span>
                        <div class="plgn-alt__gis" id="plgnGoogleBtn"></div>
                    </div>
                @endif
                <button type="button" class="plgn-alt__btn" id="plgnUseEmail">
                    <svg class="plgn-mail" viewBox="0 0 18 14" aria-hidden="true"><rect x=".8" y=".8" width="16.4" height="12.4" rx="2.5"/><path d="M2.4 2.4L9 7.7l6.6-5.3"/></svg>
                    <span>Email</span>
                </button>
            </div>
        </div>
    @endif

    <form class="plgn-email" id="plgnEmailForm" @if ($hasFirebase) hidden @endif>
        <label class="plgn-sr" for="plgnEmail">Email address</label>
        <div class="plgn-field">
            <input type="email" id="plgnEmail" name="email" placeholder="Email address" autocomplete="email" autocapitalize="off" spellcheck="false" required>
        </div>
        <label class="plgn-sr" for="plgnPassword">Password</label>
        <div class="plgn-field">
            <input type="password" id="plgnPassword" name="password" placeholder="Password" autocomplete="current-password" required>
            <button type="button" class="plgn-link plgn-showpw" id="plgnShowPw" hidden>Show</button>
        </div>
        <button type="submit" class="plgn-btn plgn__submit is-off">Sign in</button>
        @if ($hasFirebase)
            <p class="plgn-back"><a href="#" class="plgn-link" id="plgnUsePhone">Use mobile number instead</a></p>
        @endif
    </form>

    @if (\App\Support\PlatformRules::bool('partner_onboarding.login_link') && \App\Support\PlatformRules::bool('partner_onboarding.enabled'))
        <p class="plgn-apply">New to Haraan? <a class="plgn-link" href="{{ route('site.partner.apply', ['from' => 'partner_login']) }}">Apply to become a partner</a></p>
    @endif

    <p class="plgn-terms">By continuing you agree to our
        <a href="{{ route('site.legal', 'terms') }}">Terms</a> and
        <a href="{{ route('site.legal', 'privacy') }}">Privacy Policy</a>
        @if ($hasFirebase)
            {{-- Required wording when the floating reCAPTCHA badge is hidden. --}}
            <span class="plgn-captcha">Protected by reCAPTCHA — Google
                <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> and
                <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a> apply.</span>
        @endif
    </p>
</div>

<style>
    /* The partner app's sign-in palette (PartnerSignIn.kt), one-for-one. */
    .plgn-root {
        --p-blue: #2563EB; --p-blue-ink: #1D4ED8; --p-ink: #0B1220; --p-muted: #6B7688;
        --p-faint: #A3ABB9; --p-fill: #F3F5F9; --p-line: #E4E8EF; --p-red: #DC2626; --p-ok: #16A34A;
        --p-ease: cubic-bezier(.2, .8, .2, 1);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--p-ink);
        -webkit-tap-highlight-color: transparent;
    }
    .plgn { text-align: left; }
    .plgn p { margin: 0; }
    .plgn-sr { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    .plgn__head { margin-bottom: 22px; }
    .plgn__title { margin: 0; font-size: 22px; line-height: 1.25; font-weight: 700; letter-spacing: -.3px; color: var(--p-ink); }
    .plgn__sub { margin-top: 6px !important; font-size: 13.5px; line-height: 19px; color: var(--p-muted); }
    .plgn.is-code .plgn__sub { display: none; }
    .plgn.is-code .plgn__head { margin-bottom: 6px; }

    /* A step that appears slides in from the right, like the app's AnimatedContent. */
    @keyframes plgn-step { from { opacity: 0; transform: translate3d(22px, 0, 0); } to { opacity: 1; transform: none; } }
    @keyframes plgn-fade { from { opacity: 0; } to { opacity: 1; } }
    .plgn.is-moved .plgn__head,
    .auth-phone__enter:not([hidden]),
    .auth-phone__code:not([hidden]),
    .plgn-email:not([hidden]) { animation: plgn-step .3s var(--p-ease) backwards; }
    .plgn-error:not([hidden]), .plgn__alert:not([hidden]) { animation: plgn-fade .2s ease backwards; }

    .plgn__alert { display: flex; gap: 7px; margin: 0 0 14px !important; font-size: 13px; line-height: 18px; font-weight: 500; color: var(--p-red); }

    /* ---- Fields: grey at rest, white with a blue edge while typing, red after a failed try. */
    .plgn-field { display: flex; align-items: center; gap: 0; height: 58px; padding: 0 16px; box-sizing: border-box; border-radius: 16px; background: var(--p-fill); box-shadow: inset 0 0 0 1px transparent; transition: background .16s, box-shadow .16s; }
    .plgn-field:focus-within { background: #fff; box-shadow: inset 0 0 0 1.5px var(--p-blue); }
    .plgn-field.is-error { background: #fff; box-shadow: inset 0 0 0 1.5px var(--p-red); }
    .plgn-field + .plgn-sr + .plgn-field { margin-top: 10px; }
    .plgn-field input { flex: 1; min-width: 0; height: 100%; border: 0; outline: none; padding: 0; margin: 0; background: transparent; box-shadow: none; font: inherit; font-size: 16px; font-weight: 600; color: var(--p-ink); caret-color: var(--p-blue); }
    .plgn-field input::placeholder { color: var(--p-faint); font-weight: 400; opacity: 1; }
    .plgn-field input:focus { box-shadow: none; outline: none; }
    .plgn-flag { flex: none; width: 22px; height: 15px; border-radius: 2.5px; }
    .plgn-cc { margin-left: 9px; font-size: 16px; font-weight: 600; color: var(--p-ink); font-variant-numeric: tabular-nums; }
    .plgn-field__rule { flex: none; width: 1px; height: 22px; margin: 0 12px; background: var(--p-line); }
    .plgn-field--phone input { letter-spacing: .4px; font-variant-numeric: tabular-nums; }
    .plgn-field--phone input::placeholder { letter-spacing: .4px; }
    /* A tick that draws itself in one stroke once ten digits are in. */
    .plgn-check { flex: none; width: 20px; height: 20px; margin-left: 8px; opacity: 0; transition: opacity .14s; }
    .plgn-check circle { fill: rgba(22,163,74,.12); }
    .plgn-check path { fill: none; stroke: var(--p-ok); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 14; stroke-dashoffset: 14; transition: stroke-dashoffset .14s; }
    .plgn-field.is-valid .plgn-check { opacity: 1; }
    .plgn-field.is-valid .plgn-check path { stroke-dashoffset: 0; transition-duration: .32s; }

    .plgn-hint { display: flex; align-items: center; gap: 7px; margin: 10px 0 0 4px !important; font-size: 12.5px; color: var(--p-muted); }
    .plgn-wa { flex: none; width: 14px; height: 14px; }
    .plgn.has-error .plgn-hint { display: none; }

    .plgn-error { display: flex; gap: 7px; margin: 10px 0 0 4px !important; font-size: 13px; line-height: 18px; font-weight: 500; color: var(--p-red); }
    .plgn-error[hidden] { display: none; }
    .plgn-error::before, .plgn__alert::before { content: ''; flex: none; width: 13px; height: 13px; margin-top: 2.5px; background: no-repeat center/contain url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 13 13'%3E%3Ccircle cx='6.5' cy='6.5' r='5.8' fill='none' stroke='%23DC2626' stroke-width='1.4'/%3E%3Cpath d='M6.5 3.4v4.1' stroke='%23DC2626' stroke-width='1.5' stroke-linecap='round'/%3E%3Ccircle cx='6.5' cy='9.9' r='.95' fill='%23DC2626'/%3E%3C/svg%3E"); }
    /* In the code step the error sits where "Checking the code" goes. */
    .plgn.is-code .plgn-error { margin-top: 14px !important; }

    /* ---- Primary button: flat blue, grey until there's something to send. */
    .plgn-btn { position: relative; display: flex; align-items: center; justify-content: center; gap: 11px; width: 100%; height: 56px; margin-top: 22px; border: 0; border-radius: 16px; cursor: pointer; font: inherit; font-size: 16px; font-weight: 700; color: #fff; background: var(--p-blue); transition: background .18s, color .18s, transform .12s var(--p-ease); -webkit-user-select: none; user-select: none; }
    .plgn-btn:active:not(.is-off):not([disabled]) { transform: scale(.97); background: #1F55CF; transition-duration: .06s; }
    .plgn-btn:focus-visible { outline: 3px solid rgba(37,99,235,.3); outline-offset: 2px; }
    .plgn-btn.is-off { background: #E9EDF4; color: var(--p-faint); cursor: default; }
    .plgn-btn[disabled] { background: rgba(37,99,235,.86); color: #fff; cursor: default; }
    .plgn-btn[disabled]::before { content: ''; width: 18px; height: 18px; box-sizing: border-box; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: plgn-spin .7s linear infinite; }
    @keyframes plgn-spin { to { transform: rotate(360deg); } }
    .plgn-email .plgn-btn { margin-top: 18px; }

    .plgn-link { background: none; border: 0; padding: 4px 2px; margin: 0; cursor: pointer; font: inherit; font-size: 13.5px; font-weight: 600; color: var(--p-blue-ink); text-decoration: none; border-radius: 6px; }
    .plgn-link:hover { text-decoration: underline; text-underline-offset: 2px; }
    .plgn-link:active { opacity: .7; }

    /* ---- Code step: six cells over one hidden field. */
    .plgn-otp__to { display: flex; align-items: center; gap: 7px; font-size: 13.5px; color: var(--p-muted); }
    .plgn-otp__to span { min-width: 0; }
    .plgn-otp__to .plgn-link { margin-left: 1px; }
    .plgn-otp { position: relative; margin-top: 22px; }
    .plgn-otp__cells { display: grid; grid-template-columns: repeat(6, 1fr); gap: 9px; }
    .plgn-otp__cells span { display: grid; place-items: center; height: 60px; border-radius: 14px; background: var(--p-fill); box-shadow: inset 0 0 0 1px transparent; font-size: 24px; font-weight: 700; color: var(--p-ink); font-variant-numeric: tabular-nums; transition: background .14s, box-shadow .14s; }
    .plgn-otp__cells span.is-filled { background: #fff; box-shadow: inset 0 0 0 1px #C9D1DD; animation: plgn-pop .32s cubic-bezier(.34, 1.56, .64, 1); }
    .plgn-otp__cells span.is-active { background: #fff; box-shadow: inset 0 0 0 1.5px var(--p-blue); }
    .plgn-otp__cells span.is-active:empty::after { content: ''; width: 2px; height: 24px; background: var(--p-blue); animation: plgn-caret 1.04s steps(1) infinite; }
    @keyframes plgn-pop { from { transform: scale(.55); } to { transform: none; } }
    @keyframes plgn-caret { 50% { opacity: 0; } }
    .plgn-otp.is-shake .plgn-otp__cells { animation: plgn-shake .38s linear; }
    .plgn-otp.is-shake .plgn-otp__cells span { box-shadow: inset 0 0 0 1.5px var(--p-red); background: #fff; }
    @keyframes plgn-shake { 13% { transform: translateX(-14px); } 29% { transform: translateX(12px); } 45% { transform: translateX(-9px); } 61% { transform: translateX(6px); } 76% { transform: translateX(-3px); } }
    /* The real input sits on top, invisible, so taps, paste and autofill hit it. */
    .auth-phone .js-code { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; border: 0; padding: 0; margin: 0; font-size: 16px; color: transparent; caret-color: transparent; background: transparent; cursor: text; z-index: 1; }
    .plgn-otp__checking { display: flex; align-items: center; gap: 9px; margin-top: 14px !important; font-size: 13px; color: var(--p-muted); }
    .plgn-otp__checking[hidden] { display: none; }
    .plgn-otp__checking i { width: 14px; height: 14px; box-sizing: border-box; border-radius: 50%; border: 2px solid rgba(37,99,235,.25); border-top-color: var(--p-blue); animation: plgn-spin .7s linear infinite; }
    .plgn-resend { margin-top: 18px !important; font-size: 13.5px; color: var(--p-muted); }
    .plgn-resend button { background: none; border: 0; padding: 4px 2px; margin-left: 4px; cursor: pointer; font: inherit; font-weight: 600; color: var(--p-blue-ink); font-variant-numeric: tabular-nums; }
    .plgn-resend button[disabled] { color: var(--p-faint); cursor: default; }
    .auth-phone .js-recaptcha:empty { display: none; }
    .js-verify[hidden] { display: none !important; }

    /* ---- "or sign in with" + Google | Email, side by side. */
    .plgn-or { display: flex; align-items: center; gap: 14px; margin-top: 26px; font-size: 12.5px; color: var(--p-faint); }
    .plgn-or::before, .plgn-or::after { content: ''; flex: 1; height: 1px; background: var(--p-line); }
    .plgn-alt__row { display: flex; gap: 10px; margin-top: 18px; }
    .plgn-alt__btn { position: relative; flex: 1; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 10px; height: 52px; padding: 0 12px; box-sizing: border-box; overflow: hidden; border-radius: 16px; background: #fff; border: 1px solid var(--p-line); cursor: pointer; font: inherit; font-size: 15px; font-weight: 600; color: var(--p-ink); transition: transform .12s var(--p-ease), background .12s; }
    .plgn-alt__btn > svg { flex: none; width: 18px; height: 18px; }
    .plgn-alt__btn:active, .plgn-alt__btn.is-pressed { transform: scale(.96); background: #F7F8FB; }
    .plgn-mail { width: 18px !important; height: 14px !important; fill: none; stroke: var(--p-ink); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    /* Google's own button, stretched over ours and all but invisible. */
    .plgn-alt__gis { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: .0001; transform: scale(1.4); z-index: 1; }
    .plgn-alt__gis iframe { margin: 0 !important; }
    .plgn.is-code .plgn-alt, .plgn[data-step="email"] .plgn-alt, .plgn[data-step="email"] .auth-phone { display: none; }

    /* ---- Email step. */
    .plgn-email[hidden] { display: none; }
    .plgn-showpw { flex: none; margin-left: 10px; }
    .plgn-showpw[hidden] { display: none; }
    .plgn-back { margin-top: 20px !important; text-align: center; }

    .plgn-apply { margin-top: 22px !important; text-align: center; font-size: 13.5px; color: var(--p-muted); }
    .plgn.is-code .plgn-apply { display: none; }
    .plgn-terms { margin: 22px 8px 0 !important; text-align: center; font-size: 12px; line-height: 17px; color: var(--p-faint); }
    .plgn-terms a { color: var(--p-muted); font-weight: 600; text-decoration: none; }
    .plgn-terms a:hover { text-decoration: underline; }
    .plgn-captcha { display: block; margin-top: 4px; font-size: 10.5px; }
    .plgn-captcha a { color: inherit; font-weight: 500; }

    /* The floating reCAPTCHA badge sat on top of the footer on phones; the
       disclosure above replaces it (Google allows this with that wording).
       Google parks it at right:-186px; hidden or not it still widened the phone
       layout viewport to 545px, so pin it inside the screen as well. */
    .grecaptcha-badge { visibility: hidden !important; left: 0 !important; right: auto !important; }

    @media (prefers-reduced-motion: reduce) {
        .plgn-root *, .plgn-root *::before, .plgn-root *::after { animation-duration: .001s !important; animation-iteration-count: 1 !important; transition-duration: .001s !important; }
        .plgn-btn[disabled]::before, .plgn-otp__checking i { animation: plgn-spin 1.2s linear infinite !important; }
    }
</style>

@if ($hasFirebase)
    <script>
        window.HaraanFirebase = {
            apiKey: @json(config('services.firebase.api_key')),
            authDomain: @json(config('services.firebase.auth_domain')),
            projectId: @json(config('services.firebase.project_id')),
            appId: @json(config('services.firebase.app_id')),
        };
    </script>
    <script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-auth-compat.js"></script>
    <script src="{{ asset('js/firebase-phone-auth.js') }}?v={{ @filemtime(public_path('js/firebase-phone-auth.js')) }}"></script>
@endif

<script>
(function () {
    var root = document.getElementById('plgn');
    if (!root) return;
    var csrf = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
    var alertEl = document.getElementById('plgnAlert');
    var titleEl = document.getElementById('plgnTitle');
    var subEl = document.getElementById('plgnSub');
    function showAlert(msg) { if (!alertEl) return; alertEl.textContent = msg || ''; alertEl.hidden = !msg; }
    function buzz(ms) { try { if (navigator.vibrate) navigator.vibrate(ms); } catch (e) {} }
    function head(title, sub) {
        titleEl.textContent = title;
        if (sub != null) subEl.textContent = sub;
        root.classList.remove('is-moved'); void root.offsetWidth; root.classList.add('is-moved');
    }
    var PHONE_HEAD = ['Sign in', 'Use the mobile number your venue or events are registered with.'];
    var EMAIL_HEAD = ['Sign in with email', 'The email and password your Haraan partner account was set up with.'];

    /* ---- Phone step (firebase-phone-auth.js owns the flow; this only reads the
       same elements and keeps the look in step with them). ---- */
    var phoneRoot = root.querySelector('[data-phone-auth]');
    if (phoneRoot) {
        var phoneIn = phoneRoot.querySelector('.js-phone');
        var field = phoneRoot.querySelector('.plgn-field--phone');
        var sendBtn = phoneRoot.querySelector('.js-send');
        var codeStep = phoneRoot.querySelector('.auth-phone__code');
        var codeIn = phoneRoot.querySelector('.js-code');
        var verifyBtn = phoneRoot.querySelector('.js-verify');
        var checking = document.getElementById('plgnChecking');
        var otpBox = phoneRoot.querySelector('.plgn-otp');
        var cells = otpBox ? otpBox.querySelectorAll('.plgn-otp__cells span') : [];
        var toLine = document.getElementById('plgnOtpTo');
        var errEl = phoneRoot.querySelector('.js-error');

        function group(d) { return d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5) : d; }
        function isValid() {
            var v = phoneIn.value, d = v.replace(/\D/g, '');
            return v.charAt(0) === '+' ? d.length >= 8 : d.length === 10;
        }
        var wasValid = false;
        function paintPhone() {
            var ok = isValid();
            field.classList.toggle('is-valid', ok);
            sendBtn.classList.toggle('is-off', !ok);
            if (ok && !wasValid) buzz(8);
            wasValid = ok;
        }

        // 9876543210 → 98765 43210 as they type, caret kept on the same digit.
        // A pasted "+91 98765 43210" keeps its last ten.
        phoneIn.addEventListener('input', function () {
            var v = phoneIn.value;
            field.classList.remove('is-error');
            if (v.charAt(0) === '+' && !/^\+91/.test(v.replace(/\s/g, ''))) { paintPhone(); return; }
            var all = v.replace(/\D/g, '');
            if (/^\+91/.test(v.replace(/\s/g, ''))) all = all.slice(2);
            var digits = all.length > 10 ? all.slice(-10) : all;
            var caret = phoneIn.selectionStart || 0;
            var before = Math.min(v.slice(0, caret).replace(/\D/g, '').length, digits.length);
            var out = group(digits);
            if (out !== v) {
                phoneIn.value = out;
                var pos = 0, seen = 0;
                while (pos < out.length && seen < before) { if (/\d/.test(out.charAt(pos))) seen++; pos++; }
                try { phoneIn.setSelectionRange(pos, pos); } catch (e) {}
            }
            paintPhone();
        });
        phoneIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (!sendBtn.disabled) sendBtn.click(); }
        });
        // Grey "Continue" does nothing but nudge the field — caught before the
        // shared script's own click handler sees it.
        root.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.js-send') && sendBtn.classList.contains('is-off')) {
                e.preventDefault(); e.stopPropagation();
                phoneIn.focus();
            }
        }, true);

        function paintCells() {
            var v = (codeIn.value || '').replace(/\D/g, '').slice(0, 6);
            var focused = document.activeElement === codeIn;
            for (var i = 0; i < cells.length; i++) {
                var ch = v.charAt(i);
                if (cells[i].textContent !== ch) {
                    cells[i].textContent = ch;
                    cells[i].classList.toggle('is-filled', !!ch);
                }
                cells[i].classList.toggle('is-active', focused && !codeIn.disabled && i === Math.min(v.length, 5) && v.length < 6);
            }
        }
        codeIn.addEventListener('input', function () { otpBox.classList.remove('is-shake'); paintCells(); });
        codeIn.addEventListener('focus', paintCells);
        codeIn.addEventListener('blur', paintCells);
        // Keep the caret at the end — the cells can't show a mid-string caret.
        codeIn.addEventListener('click', function () { var n = codeIn.value.length; try { codeIn.setSelectionRange(n, n); } catch (e) {} });

        if (window.MutationObserver) {
            // The shared script flips [hidden] on the steps without firing events.
            new MutationObserver(function () {
                var inCode = !codeStep.hidden;
                if (inCode === root.classList.contains('is-code')) { paintCells(); return; }
                root.classList.toggle('is-code', inCode);
                if (inCode) {
                    var raw = phoneIn.value, d = raw.replace(/\D/g, '');
                    // Non-breaking spaces: the number never splits across lines.
                    toLine.textContent = 'Sent on WhatsApp to ' + (raw.charAt(0) === '+' ? raw : '+91 ' + group(d.slice(-10))).replace(/ /g, ' ');
                    head('Enter the code');
                    buzz(12);
                    setTimeout(function () { try { codeIn.focus(); } catch (e) {} paintCells(); }, 220);
                } else {
                    head(PHONE_HEAD[0], PHONE_HEAD[1]);
                }
                paintCells();
            }).observe(codeStep, { attributes: true, attributeFilter: ['hidden'] });

            // "Checking the code" while the shared script verifies.
            if (verifyBtn) new MutationObserver(function () {
                checking.hidden = !verifyBtn.disabled;
                if (verifyBtn.disabled) errEl.hidden = true;
            }).observe(verifyBtn, { attributes: true, attributeFilter: ['disabled'] });

            // An error: shake the cells (or mark the field red) and buzz.
            new MutationObserver(function () {
                var on = !errEl.hidden && !!errEl.textContent;
                root.classList.toggle('has-error', on);
                if (!on) return;
                buzz([20, 40, 20]);
                if (!codeStep.hidden) {
                    otpBox.classList.remove('is-shake'); void otpBox.offsetWidth; otpBox.classList.add('is-shake');
                    if (codeIn.value.length === 6) { codeIn.value = ''; paintCells(); codeIn.focus(); }
                } else {
                    field.classList.add('is-error');
                }
            }).observe(errEl, { attributes: true, attributeFilter: ['hidden'], childList: true, characterData: true, subtree: true });
        }

        paintPhone();
        paintCells();
        // Browser autofill fills the field without an input event on some phones.
        setTimeout(paintPhone, 600);
    }

    /* ---- Email step ---- */
    var useEmailBtn = document.getElementById('plgnUseEmail');
    var usePhone = document.getElementById('plgnUsePhone');
    var emailForm = document.getElementById('plgnEmailForm');
    var emailIn = document.getElementById('plgnEmail');
    var pwIn = document.getElementById('plgnPassword');
    var showPw = document.getElementById('plgnShowPw');
    var submitBtn = emailForm.querySelector('.plgn__submit');

    function paintEmail() {
        submitBtn.classList.toggle('is-off', !(emailIn.value.trim() && pwIn.value));
        showPw.hidden = !pwIn.value;
    }
    [emailIn, pwIn].forEach(function (el) {
        el.addEventListener('input', function () {
            el.parentNode.classList.remove('is-error');
            showAlert('');
            paintEmail();
        });
    });
    showPw.addEventListener('click', function () {
        var show = pwIn.type === 'password';
        pwIn.type = show ? 'text' : 'password';
        showPw.textContent = show ? 'Hide' : 'Show';
        buzz(6);
    });

    if (useEmailBtn) useEmailBtn.addEventListener('click', function () {
        showAlert('');
        root.dataset.step = 'email';
        emailForm.hidden = false;
        head(EMAIL_HEAD[0], EMAIL_HEAD[1]);
        setTimeout(function () { emailIn.focus(); }, 60);
    });
    if (usePhone) usePhone.addEventListener('click', function (e) {
        e.preventDefault();
        showAlert('');
        root.dataset.step = 'phone';
        emailForm.hidden = true;
        head(PHONE_HEAD[0], PHONE_HEAD[1]);
    });

    function failEmail(msg) {
        showAlert(msg);
        emailIn.parentNode.classList.add('is-error');
        pwIn.parentNode.classList.add('is-error');
        buzz([20, 40, 20]);
        submitBtn.disabled = false; submitBtn.textContent = 'Sign in';
    }
    emailForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (submitBtn.classList.contains('is-off') || submitBtn.disabled) return;
        showAlert('');
        submitBtn.disabled = true; submitBtn.textContent = 'Signing in';
        fetch(@json(route('partner.auth.email')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ email: emailIn.value, password: pwIn.value }),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    failEmail(data.error || (res.status === 429 ? 'Too many attempts. Please wait and try again.' : 'Sign-in failed. Please try again.'));
                    return;
                }
                window.location.assign(data.redirect || @json(route('filament.partner.pages.dashboard')));
            });
        }).catch(function () { failEmail('Network error. Check your connection and try again.'); });
    });
    paintEmail();

    @if ($hasGoogle && $hasFirebase)
    /* ---- Google Identity Services, under our own button face ---- */
    var gBox = document.getElementById('plgnGoogle');
    var gCfg = { clientId: @json(config('services.google.client_id')), postUrl: @json(route('partner.auth.google')) };
    function onGoogle(response) {
        showAlert('');
        gBox.classList.add('is-pressed');
        fetch(gCfg.postUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ credential: response.credential }),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                gBox.classList.remove('is-pressed');
                if (!res.ok) { showAlert(data.error || 'That Google sign-in did not work.'); buzz([20, 40, 20]); return; }
                window.location.assign(data.redirect || @json(route('filament.partner.pages.dashboard')));
            });
        }).catch(function () { gBox.classList.remove('is-pressed'); showAlert('Network error during Google sign-in.'); });
    }
    var gWaited = 0;
    var gTimer = setInterval(function () {
        var slot = document.getElementById('plgnGoogleBtn');
        if (window.google && window.google.accounts && window.google.accounts.id && slot) {
            clearInterval(gTimer);
            window.google.accounts.id.initialize({ client_id: gCfg.clientId, callback: onGoogle });
            // GIS draws a 200–400px iframe; size it to our button (the box clips the rest).
            var drawnW = 0;
            var draw = function () {
                var w = Math.max(200, Math.min(400, Math.floor(gBox.clientWidth)));
                if (!gBox.clientWidth || w === drawnW) return;
                drawnW = w;
                window.google.accounts.id.renderButton(slot, { theme: 'outline', size: 'large', width: w, text: 'signin_with', shape: 'rectangular' });
            };
            draw();
            if (window.ResizeObserver) {
                var rt; new ResizeObserver(function () { clearTimeout(rt); rt = setTimeout(draw, 150); }).observe(gBox);
            }
        } else if ((gWaited += 100) > 8000) {
            // No Google on this network — Email takes the whole row.
            clearInterval(gTimer);
            gBox.remove();
        }
    }, 100);
    @endif
})();
</script>

@if ($hasGoogle && $hasFirebase)
    <script src="https://accounts.google.com/gsi/client" async defer></script>
@endif

</div>{{-- /.plgn-root --}}
