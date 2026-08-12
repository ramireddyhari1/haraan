{{--
    Partner console sign-in form (the right-hand column of the blue split-brand
    shell painted by the SIMPLE_LAYOUT_START hook). Phone-OTP first, then Google,
    with an "Use Email" fallback — all three post to PartnerAuthController and land
    on the partner dashboard. Self-contained (markup + styles + scripts) so it needs
    no theme rebuild; the Firebase SDK / GIS load here the same way the public site
    loads them.
--}}
@php
    $hasFirebase = (bool) config('services.firebase.api_key');
    $hasGoogle   = (bool) config('services.google.client_id');
@endphp

{{-- Single root element — this view backs a Livewire component (Filament's login
     page), which permits exactly one root node. Everything (form, styles, scripts)
     lives inside it. --}}
<div class="plgn-root">

{{-- firebase-phone-auth.js + our fetch helpers read the CSRF token from here. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="plgn">
    <div class="plgn__head">
        <h1 class="plgn__title">Sign in to your account</h1>
        <p class="plgn__sub">Access your event dashboard</p>
    </div>

    <p class="plgn__alert" id="plgnAlert" role="alert" hidden></p>

    @if ($hasFirebase)
        {{-- Phone OTP (Firebase). firebase-phone-auth.js wires this by [data-phone-auth]. --}}
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
                <label class="auth-phone__label">Mobile number</label>
                <div class="auth-phone__inputwrap">
                    <span class="auth-phone__cc"><span class="flag">🇮🇳</span>+91</span>
                    <input type="tel" class="js-phone" placeholder="Enter 10-digit mobile number" autocomplete="tel" inputmode="tel" maxlength="14">
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
    @endif

    @if ($hasGoogle && $hasFirebase)
        <div class="plgn__or"><span>or</span></div>
    @endif

    @if ($hasGoogle)
        <div class="plgn__google"><div class="plgn__google-btn" id="plgnGoogleBtn"></div></div>
    @endif

    {{-- Email fallback — revealed by "Use Email". --}}
    <button type="button" class="plgn__useemail" id="plgnUseEmail">Use Email</button>

    <form class="plgn__email" id="plgnEmailForm" hidden>
        <div class="plgn__field">
            <label for="plgnEmail">Email</label>
            <input type="email" id="plgnEmail" name="email" class="plgn__input" placeholder="you@example.com" autocomplete="email" autocapitalize="off" spellcheck="false" required>
        </div>
        <div class="plgn__field">
            <label for="plgnPassword">Password</label>
            <input type="password" id="plgnPassword" name="password" class="plgn__input" placeholder="Your password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="plgn__submit">Sign in</button>
    </form>

    <p class="plgn__foot">By signing in, you agree to our
        <a href="{{ route('site.legal', 'terms') }}">Terms</a> and
        <a href="{{ route('site.legal', 'privacy') }}">Privacy Policy</a></p>
</div>

<style>
    .plgn { text-align: left; }
    .plgn__head { margin-bottom: 20px; }
    .plgn__title { font-family: 'Inter', sans-serif; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: #0F172A; margin: 0; }
    .plgn__sub { margin: 6px 0 0; font-size: .9rem; color: #2563EB; font-weight: 600; }
    .dark .plgn__title { color: #F1F5F9; }

    .plgn__alert { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 12px; padding: 10px 13px; font-size: 13px; font-weight: 600; margin: 0 0 14px; }

    /* Phone block (self-contained copy of site.partials.auth-phone styles). */
    .auth-phone { margin-top: 2px; text-align: left; }
    .auth-phone__label { display: block; font-size: 11.5px; font-weight: 700; color: #64748B; margin-bottom: 6px; letter-spacing: .02em; text-transform: uppercase; }
    .auth-phone__inputwrap { display: flex; align-items: stretch; border: 1.5px solid #E4E9F1; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .auth-phone__inputwrap:focus-within { border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .auth-phone__cc { display: flex; align-items: center; gap: 7px; padding: 0 12px 0 14px; font-size: 15px; font-weight: 700; color: #334155; background: #F8FAFC; border-right: 1.5px solid #E4E9F1; }
    .auth-phone__cc .flag { font-size: 16px; line-height: 1; }
    .auth-phone .js-phone { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; height: 48px; padding: 0 14px; font-size: 15px; color: #0F172A; }
    .auth-phone .js-phone::placeholder { color: #9AA6B8; }
    .auth-phone .js-code { width: 100%; box-sizing: border-box; height: 52px; text-align: center; letter-spacing: .45em; font-size: 19px; font-weight: 700; color: #0F172A; background: #fff; border: 1.5px solid #E4E9F1; border-radius: 12px; }
    .auth-phone .js-code:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .auth-phone .js-code::placeholder { letter-spacing: .45em; color: #CBD5E1; }
    .auth-phone__btn { width: 100%; height: 50px; border-radius: 12px; cursor: pointer; border: 0; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 700; color: #fff; background: #2563EB; box-shadow: 0 8px 18px -8px rgba(37,99,235,.5); margin-top: 14px; transition: background .15s, transform .12s; }
    .auth-phone__btn:hover:not([disabled]) { background: #1D4ED8; transform: translateY(-1px); }
    .auth-phone__btn[disabled] { opacity: .6; cursor: default; box-shadow: none; }
    .auth-phone .js-error { color: #B91C1C; font-size: 12.5px; font-weight: 600; margin: 10px 0 0; text-align: center; }
    .auth-phone__resendrow { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 12px 2px 0; }
    .auth-phone .js-change { color: #2563EB; text-decoration: none; font-weight: 600; font-size: 12.5px; }
    .auth-phone .js-change:hover { text-decoration: underline; }
    .auth-phone__resend { background: none; border: 0; padding: 0; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #2563EB; }
    .auth-phone__resend[disabled] { color: #94A3B8; cursor: default; }
    .auth-phone__resend:not([disabled]):hover { text-decoration: underline; }
    .auth-phone .js-recaptcha:empty { min-height: 0; }

    /* "or" divider. */
    .plgn__or { display: flex; align-items: center; gap: 12px; margin: 18px 0; color: #94A3B8; font-size: 12px; font-weight: 600; }
    .plgn__or::before, .plgn__or::after { content: ''; flex: 1; height: 1px; background: #E4E9F1; }

    /* Google. */
    .plgn__google { display: flex; justify-content: center; }
    .plgn__google-btn { width: 100%; display: flex; justify-content: center; min-height: 44px; }

    /* Use Email + email form. */
    .plgn__useemail { display: block; margin: 16px auto 0; background: none; border: 0; padding: 4px; cursor: pointer; color: #2563EB; font-size: 13.5px; font-weight: 700; }
    .plgn__useemail:hover { text-decoration: underline; }
    .plgn__useemail[hidden] { display: none; }
    .plgn__email { margin-top: 8px; }
    .plgn__email[hidden] { display: none; }
    .plgn__field { margin-bottom: 13px; text-align: left; }
    .plgn__field label { display: block; font-size: 11.5px; font-weight: 700; color: #64748B; margin-bottom: 6px; letter-spacing: .02em; text-transform: uppercase; }
    .plgn__input { width: 100%; box-sizing: border-box; height: 48px; padding: 0 15px; font-size: 15px; color: #0F172A; background: #fff; border: 1.5px solid #E4E9F1; border-radius: 12px; transition: border-color .15s, box-shadow .15s; }
    .plgn__input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .plgn__submit { width: 100%; height: 50px; border: 0; border-radius: 12px; cursor: pointer; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 700; color: #fff; background: #2563EB; box-shadow: 0 8px 18px -8px rgba(37,99,235,.5); margin-top: 4px; transition: background .15s, transform .12s; }
    .plgn__submit:hover { background: #1D4ED8; transform: translateY(-1px); }

    .plgn__foot { margin-top: 22px; text-align: center; font-size: 12px; color: #94A3B8; line-height: 1.7; }
    .plgn__foot a { color: #2563EB; text-decoration: none; font-weight: 600; }
    .plgn__foot a:hover { text-decoration: underline; }
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
    var csrf = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
    var alertEl = document.getElementById('plgnAlert');
    function showAlert(msg) { if (!alertEl) return; alertEl.textContent = msg || ''; alertEl.hidden = !msg; }

    /* ---- Use Email toggle ---- */
    var useEmailBtn = document.getElementById('plgnUseEmail');
    var emailForm = document.getElementById('plgnEmailForm');
    if (useEmailBtn && emailForm) {
        useEmailBtn.addEventListener('click', function () {
            emailForm.hidden = false;
            useEmailBtn.hidden = true;
            var e = document.getElementById('plgnEmail');
            if (e) e.focus();
        });
        emailForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            showAlert('');
            var btn = emailForm.querySelector('.plgn__submit');
            if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; }
            fetch(@json(route('partner.auth.email')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: document.getElementById('plgnEmail').value,
                    password: document.getElementById('plgnPassword').value,
                }),
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok) {
                        showAlert(data.error || (res.status === 429 ? 'Too many attempts. Please wait and try again.' : 'Sign-in failed. Please try again.'));
                        if (btn) { btn.disabled = false; btn.textContent = 'Sign in'; }
                        return;
                    }
                    window.location.assign(data.redirect || @json(route('filament.partner.pages.dashboard')));
                });
            }).catch(function () {
                showAlert('Network error. Please check your connection and try again.');
                if (btn) { btn.disabled = false; btn.textContent = 'Sign in'; }
            });
        });
    }

    @if ($hasGoogle)
    /* ---- Google Identity Services ---- */
    var gCfg = { clientId: @json(config('services.google.client_id')), postUrl: @json(route('partner.auth.google')) };
    function onGoogle(response) {
        showAlert('');
        fetch(gCfg.postUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ credential: response.credential }),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) { showAlert(data.error || 'That Google sign-in did not work.'); return; }
                window.location.assign(data.redirect || @json(route('filament.partner.pages.dashboard')));
            });
        }).catch(function () { showAlert('Network error during Google sign-in.'); });
    }
    var gWaited = 0;
    var gTimer = setInterval(function () {
        var slot = document.getElementById('plgnGoogleBtn');
        if (window.google && window.google.accounts && window.google.accounts.id && slot) {
            clearInterval(gTimer);
            window.google.accounts.id.initialize({ client_id: gCfg.clientId, callback: onGoogle });
            var draw = function () {
                if (!slot.clientWidth) return;
                window.google.accounts.id.renderButton(slot, { theme: 'outline', size: 'large', width: slot.clientWidth, text: 'continue_with', shape: 'rectangular' });
            };
            draw();
            var t = 0, di = setInterval(function () { draw(); if (++t > 20 || slot.childElementCount) clearInterval(di); }, 150);
        } else if ((gWaited += 100) > 6000) {
            clearInterval(gTimer);
            var g = document.querySelector('.plgn__google'); if (g) g.remove();
        }
    }, 100);
    @endif
})();
</script>

@if ($hasGoogle)
    <script src="https://accounts.google.com/gsi/client" async defer></script>
@endif

</div>{{-- /.plgn-root --}}
