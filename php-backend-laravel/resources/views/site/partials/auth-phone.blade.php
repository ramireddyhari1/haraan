{{--
    Phone-number sign-in (Firebase SMS OTP). Reusable on both login surfaces (the
    /login card and the site-wide drawer), so everything is scoped to the
    `[data-phone-auth]` root by class — no ids to collide. Wired by
    public/js/firebase-phone-auth.js, which reads data-post-url.
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

    .auth-phone .js-error { color: #B91C1C; font-size: 12.5px; font-weight: 600; margin: 10px 0 0; text-align: center; }
    .auth-phone .js-change { color: #2563EB; text-decoration: none; font-weight: 600; }
    .auth-phone .js-change:hover { text-decoration: underline; }
    .auth-phone__hint { margin: 10px 0 0; font-size: 12px; color: #94A3B8; text-align: center; }
    /* Invisible reCAPTCHA container must stay renderable (not display:none) to attach. */
    .auth-phone .js-recaptcha:empty { min-height: 0; }
</style>
@endonce

<div class="auth-phone" data-phone-auth data-post-url="{{ route('firebase.phone.login') }}">
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
        <p class="auth-phone__hint">Code sent to your number. <a href="#" class="js-change">Change number</a></p>
    </div>

    <p class="js-error" role="alert" hidden></p>
    <div class="js-recaptcha"></div>
</div>
