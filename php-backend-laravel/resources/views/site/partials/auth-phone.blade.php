{{--
    Phone-number sign-in. Reusable on both member login surfaces (the /login card and
    the site-wide drawer), so everything is scoped to the `[data-phone-auth]` root by
    class — no ids to collide. Wired by public/js/firebase-phone-auth.js.

    WhatsApp first, Firebase SMS underneath: data-otp-start-url is asked whether it
    could send the code over WhatsApp, and Firebase runs whenever it couldn't — no
    approved template, no credentials, a number not on WhatsApp, or that endpoint
    being down. The fallback is silent because a login is the one message with
    nothing behind it: if it doesn't arrive, the person cannot get in.

    The partner console keeps its own copy of this markup WITHOUT these two
    attributes, so partner sign-in stays on SMS.
--}}
@once
<style>
    .auth-phone { margin-top: 2px; text-align: left; }
    .auth-phone__label { display: block; font-size: 11.5px; font-weight: 700; color: #64748B; margin-bottom: 6px; letter-spacing: .02em; text-transform: uppercase; }

    /* Phone field: flag + fixed +91 country prefix (India-first, like BookMyShow). */
    .auth-phone__inputwrap { display: flex; align-items: stretch; border: 1.5px solid #E4E9F1; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .auth-phone__inputwrap:focus-within { border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .auth-phone__cc { display: flex; align-items: center; gap: 7px; padding: 0 12px 0 14px; font-size: 15px; font-weight: 700; color: #334155; background: #F8FAFC; border-right: 1.5px solid #E4E9F1; }
    .auth-phone__cc .flag { font-size: 16px; line-height: 1; }
    .auth-phone .js-phone { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; height: 48px; padding: 0 14px; font-size: 15px; color: #0F172A; }
    .auth-phone .js-phone::placeholder { color: #9AA6B8; letter-spacing: .04em; }

    .auth-phone .js-code { width: 100%; box-sizing: border-box; height: 52px; text-align: center; letter-spacing: .45em; font-size: 19px; font-weight: 700; color: #0F172A; background: #fff; border: 1.5px solid #E4E9F1; border-radius: 12px; }
    .auth-phone .js-code:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .auth-phone .js-code::placeholder { letter-spacing: .45em; color: #CBD5E1; }

    /* Both actions use the single solid brand accent (clean, BMS-like). */
    .auth-phone__btn { width: 100%; height: 50px; border-radius: 12px; cursor: pointer; border: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 700; color: #fff; background: #2563EB; box-shadow: 0 8px 18px -8px rgba(37,99,235,.5); margin-top: 14px; transition: background .15s, transform .12s; }
    .auth-phone__btn:hover:not([disabled]) { background: #1D4ED8; transform: translateY(-1px); }
    .auth-phone__btn[disabled] { opacity: .6; cursor: default; box-shadow: none; }
    /* Opened from the Pulse lane, the modal's CTA takes Pulse's deeper blue so it
       matches the header button that launched it. Keyed on `aurora-hub` (added
       server-side for gamehub* only) — `mode-gamehub` is set by script and is
       present on Events pages too, so it would leak the deep blue across lanes. */
    body.aurora-hub .auth-phone__btn { background: #1E3A8A; box-shadow: 0 8px 18px -8px rgba(30,58,138,.5); }
    body.aurora-hub .auth-phone__btn:hover:not([disabled]) { background: #172554; }

    .auth-phone .js-error { color: #B91C1C; font-size: 12.5px; font-weight: 600; margin: 10px 0 0; text-align: center; }
    .auth-phone__resendrow { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 12px 2px 0; }
    .auth-phone .js-change { color: #2563EB; text-decoration: none; font-weight: 600; font-size: 12.5px; }
    .auth-phone .js-change:hover { text-decoration: underline; }
    .auth-phone__resend { background: none; border: 0; padding: 0; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #2563EB; }
    .auth-phone__resend[disabled] { color: #94A3B8; cursor: default; }
    .auth-phone__resend:not([disabled]):hover { text-decoration: underline; }
    /* Invisible reCAPTCHA container must stay renderable (not display:none) to attach. */
    .auth-phone .js-recaptcha:empty { min-height: 0; }
</style>
@endonce

{{-- WhatsApp first, Firebase SMS underneath. The two urls are what turn the
     fallback on; drop them and this partial is the plain Firebase flow again. --}}
<div class="auth-phone" data-phone-auth
     data-post-url="{{ route('firebase.phone.login') }}"
     data-otp-start-url="{{ route('whatsapp.otp.start') }}"
     data-otp-verify-url="{{ route('whatsapp.otp.verify') }}">
    <div class="auth-phone__enter">
        <label class="auth-phone__label">Mobile number</label>
        <div class="auth-phone__inputwrap">
            <span class="auth-phone__cc"><span class="flag">🇮🇳</span>+91</span>
            <input type="tel" class="js-phone" placeholder="98765 43210" autocomplete="tel" inputmode="tel" maxlength="14">
        </div>
        <button type="button" class="auth-phone__btn js-send">Send OTP</button>
    </div>

    <div class="auth-phone__code" hidden>
        <label class="auth-phone__label">Enter the 6-digit code</label>
        <input type="text" class="js-code" placeholder="••••••" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
        <button type="button" class="auth-phone__btn js-verify">Verify &amp; continue</button>
        <div class="auth-phone__resendrow">
            <button type="button" class="auth-phone__resend js-resend" disabled>Resend code</button>
            <a href="#" class="js-change">Change number</a>
        </div>
    </div>

    <p class="js-error" role="alert" hidden></p>
    <div class="js-recaptcha"></div>
</div>
