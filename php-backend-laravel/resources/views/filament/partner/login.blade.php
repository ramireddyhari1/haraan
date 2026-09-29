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
        <p class="plgn__sub">Use the mobile number or email your Haraan admin registered for you.</p>
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
                    {{-- Drawn flag: the 🇮🇳 emoji renders as the letters "IN" on Windows. --}}
                    <span class="auth-phone__cc">
                        <svg class="flag" viewBox="0 0 18 12" aria-hidden="true"><rect width="18" height="4" fill="#FF9933"/><rect y="4" width="18" height="4" fill="#fff"/><rect y="8" width="18" height="4" fill="#138808"/><circle cx="9" cy="6" r="1.45" fill="none" stroke="#000080" stroke-width=".6"/></svg>
                        +91
                    </span>
                    <input type="tel" class="js-phone" placeholder="98765 43210" aria-label="Mobile number" autocomplete="tel-national" inputmode="tel" maxlength="16">
                </div>
                <button type="button" class="auth-phone__btn js-send">Send OTP</button>
            </div>
            <div class="auth-phone__code" hidden>
                <label class="auth-phone__label">Enter the 6-digit code</label>
                <p class="plgn-otp__to" id="plgnOtpTo"></p>
                {{-- ONE real input (keyboard, paste, one-time-code autofill all keep
                     working) laid invisibly over six drawn cells. --}}
                <div class="plgn-otp">
                    <input type="text" class="js-code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" aria-label="6-digit code">
                    <div class="plgn-otp__cells" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
                </div>
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
    <button type="button" class="plgn__useemail" id="plgnUseEmail">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg>
        Continue with email
    </button>

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
        <a href="{{ route('site.legal', 'privacy') }}">Privacy Policy</a>.
        @if ($hasFirebase)
            {{-- Required wording when the floating reCAPTCHA badge is hidden. --}}
            <span class="plgn__captcha">Protected by reCAPTCHA — Google
                <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> and
                <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a> apply.</span>
        @endif
    </p>
</div>

<style>
    /* One easing for everything that moves — a quick start that settles softly. */
    .plgn-root { --plgn-ease: cubic-bezier(.2, .8, .2, 1); --plgn-blue: #2563EB; --plgn-line: #E3E8F0; }

    .plgn { text-align: left; }
    .plgn__head { margin-bottom: 22px; }
    .plgn__title { font-family: 'Inter', sans-serif; font-size: 1.45rem; font-weight: 800; letter-spacing: -.025em; color: #0F172A; margin: 0; }
    .plgn__sub { margin: 6px 0 0; font-size: .86rem; line-height: 1.5; color: #64748B; font-weight: 500; }
    .dark .plgn__title { color: #F1F5F9; }

    /* Anything that appears mid-flow (alert, code step, email form) eases in
       instead of popping — the animation replays each time [hidden] lifts. */
    @keyframes plgn-in { from { opacity: 0; transform: translate3d(0, 6px, 0); } to { opacity: 1; transform: none; } }
    .plgn__alert:not([hidden]),
    .auth-phone__enter:not([hidden]),
    .auth-phone__code:not([hidden]),
    .auth-phone .js-error:not([hidden]),
    .plgn__email:not([hidden]) { animation: plgn-in .32s var(--plgn-ease) both; }

    .plgn__alert { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 12px; padding: 10px 13px; font-size: 13px; font-weight: 600; margin: 0 0 14px; }

    /* Phone block (self-contained copy of site.partials.auth-phone styles). */
    .auth-phone { margin-top: 2px; text-align: left; }
    .auth-phone__label { display: block; font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 7px; letter-spacing: .06em; text-transform: uppercase; }
    .auth-phone__inputwrap { display: flex; align-items: stretch; height: 52px; border: 1.5px solid var(--plgn-line); border-radius: 14px; overflow: hidden; background: #fff; transition: border-color .2s var(--plgn-ease), box-shadow .2s var(--plgn-ease); }
    .auth-phone__inputwrap:hover { border-color: #CBD5E1; }
    .auth-phone__inputwrap:focus-within { border-color: var(--plgn-blue); box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .auth-phone__cc { display: flex; align-items: center; gap: 8px; padding: 0 13px 0 14px; font-size: 15px; font-weight: 700; color: #334155; font-variant-numeric: tabular-nums; position: relative; }
    .auth-phone__cc::after { content: ''; position: absolute; right: 0; top: 14px; bottom: 14px; width: 1px; background: var(--plgn-line); }
    .auth-phone__cc .flag { width: 20px; height: 14px; border-radius: 3px; box-shadow: 0 0 0 1px rgba(15,23,42,.1); overflow: hidden; flex: none; }
    .auth-phone .js-phone { flex: 1; min-width: 0; border: 0; outline: none; background: transparent; padding: 0 14px 0 13px; font-size: 16px; font-weight: 600; letter-spacing: .02em; color: #0F172A; font-variant-numeric: tabular-nums; }
    .auth-phone .js-phone::placeholder { color: #A7B2C3; font-weight: 500; }

    /* Primary button — lifts on hover, gives under the finger, spins while busy. */
    .auth-phone__btn,
    .plgn__submit { position: relative; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 52px; border-radius: 14px; cursor: pointer; border: 0; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: -.005em; color: #fff; background: linear-gradient(180deg, #2F6BFF 0%, #2159E8 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,.18), 0 1px 2px rgba(15,23,42,.12), 0 10px 22px -12px rgba(37,99,235,.75); margin-top: 14px; transition: transform .18s var(--plgn-ease), box-shadow .18s var(--plgn-ease), filter .18s; -webkit-tap-highlight-color: transparent; }
    .auth-phone__btn:hover:not([disabled]),
    .plgn__submit:hover:not([disabled]) { transform: translateY(-1px); filter: brightness(1.04); box-shadow: inset 0 1px 0 rgba(255,255,255,.18), 0 1px 2px rgba(15,23,42,.12), 0 14px 26px -12px rgba(37,99,235,.8); }
    .auth-phone__btn:active:not([disabled]),
    .plgn__submit:active:not([disabled]) { transform: translateY(0) scale(.985); transition-duration: .06s; box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 4px 10px -6px rgba(37,99,235,.7); }
    .auth-phone__btn:focus-visible,
    .plgn__submit:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(37,99,235,.25); }
    .auth-phone__btn[disabled],
    .plgn__submit[disabled] { cursor: default; filter: saturate(.85); opacity: .88; }
    .auth-phone__btn[disabled]::before,
    .plgn__submit[disabled]::before { content: ''; width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: plgn-spin .7s linear infinite; }
    @keyframes plgn-spin { to { transform: rotate(360deg); } }

    .auth-phone .js-error { color: #B91C1C; font-size: 12.5px; font-weight: 600; margin: 10px 0 0; text-align: center; }
    .auth-phone__resendrow { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 14px 2px 0; }
    .auth-phone .js-change { color: var(--plgn-blue); text-decoration: none; font-weight: 600; font-size: 12.5px; }
    .auth-phone .js-change:hover { text-decoration: underline; }
    .auth-phone__resend { background: none; border: 0; padding: 0; cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--plgn-blue); font-variant-numeric: tabular-nums; }
    .auth-phone__resend[disabled] { color: #94A3B8; cursor: default; }
    .auth-phone__resend:not([disabled]):hover { text-decoration: underline; }
    .auth-phone .js-recaptcha:empty { min-height: 0; }

    /* ---- 6-cell code entry ---- */
    .plgn-otp__to { margin: -2px 0 12px; font-size: 13px; color: #64748B; }
    .plgn-otp__to b { color: #0F172A; font-weight: 700; font-variant-numeric: tabular-nums; }
    .plgn-otp { position: relative; }
    .plgn-otp__cells { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
    .plgn-otp__cells span { position: relative; display: grid; place-items: center; height: 54px; border: 1.5px solid var(--plgn-line); border-radius: 12px; background: #fff; font-size: 22px; font-weight: 700; color: #0F172A; font-variant-numeric: tabular-nums; transition: border-color .18s var(--plgn-ease), box-shadow .18s var(--plgn-ease), background .18s; }
    .plgn-otp__cells span.is-filled { border-color: #C7D4EA; background: #F8FAFF; animation: plgn-pop .22s var(--plgn-ease); }
    .plgn-otp__cells span.is-active { border-color: var(--plgn-blue); box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .plgn-otp__cells span.is-active:empty::after { content: ''; width: 2px; height: 22px; border-radius: 1px; background: var(--plgn-blue); animation: plgn-caret 1s steps(1) infinite; }
    @keyframes plgn-pop { 0% { transform: scale(.9); } 60% { transform: scale(1.04); } 100% { transform: none; } }
    @keyframes plgn-caret { 50% { opacity: 0; } }
    .plgn-otp.is-shake .plgn-otp__cells { animation: plgn-shake .38s var(--plgn-ease); }
    .plgn-otp.is-shake .plgn-otp__cells span { border-color: #F87171; }
    @keyframes plgn-shake { 20% { transform: translateX(-6px); } 40% { transform: translateX(5px); } 60% { transform: translateX(-3px); } 80% { transform: translateX(2px); } }
    /* The real input sits on top, invisible, so taps, paste and autofill hit it. */
    .auth-phone .js-code { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; border: 0; padding: 0; margin: 0; font-size: 16px; color: transparent; caret-color: transparent; background: transparent; cursor: text; z-index: 1; }

    /* "or" divider. */
    .plgn__or { display: flex; align-items: center; gap: 12px; margin: 20px 0 16px; color: #94A3B8; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .plgn__or::before, .plgn__or::after { content: ''; flex: 1; height: 1px; background: var(--plgn-line); }

    /* Google — full card width, space reserved so nothing jumps when it lands. */
    .plgn__google { display: flex; justify-content: center; min-height: 44px; }
    .plgn__google-btn { width: 100%; display: flex; justify-content: center; min-height: 44px; opacity: 0; transition: opacity .3s var(--plgn-ease); }
    .plgn__google-btn.is-ready { opacity: 1; }

    /* Email — a real secondary button, same weight as Google's. */
    .plgn__useemail { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; height: 44px; margin: 10px 0 0; background: #fff; border: 1px solid #DADCE0; border-radius: 6px; padding: 0 14px; cursor: pointer; color: #1F2937; font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 600; transition: background .15s, border-color .15s, transform .15s var(--plgn-ease); -webkit-tap-highlight-color: transparent; }
    .plgn__useemail svg { width: 18px; height: 18px; fill: none; stroke: #475569; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .plgn__useemail:hover { background: #F8FAFC; border-color: #C9CED6; }
    .plgn__useemail:active { transform: scale(.985); }
    .plgn__useemail[hidden] { display: none; }
    .plgn__email { margin-top: 16px; }
    .plgn__email[hidden] { display: none; }
    .plgn__field { margin-bottom: 13px; text-align: left; }
    .plgn__field label { display: block; font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 7px; letter-spacing: .06em; text-transform: uppercase; }
    .plgn__input { width: 100%; box-sizing: border-box; height: 50px; padding: 0 15px; font-size: 16px; color: #0F172A; background: #fff; border: 1.5px solid var(--plgn-line); border-radius: 14px; transition: border-color .2s var(--plgn-ease), box-shadow .2s var(--plgn-ease); }
    .plgn__input:hover { border-color: #CBD5E1; }
    .plgn__input:focus { outline: none; border-color: var(--plgn-blue); box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .plgn__submit { margin-top: 4px; }

    .plgn__foot { margin: 22px 0 0; text-align: center; font-size: 11.5px; color: #94A3B8; line-height: 1.7; }
    .plgn__foot a { color: #64748B; text-decoration: underline; text-decoration-color: #CBD5E1; text-underline-offset: 2px; font-weight: 600; }
    .plgn__foot a:hover { color: var(--plgn-blue); text-decoration-color: currentColor; }
    .plgn__captcha { display: block; margin-top: 4px; font-size: 10.5px; color: #A7B2C3; }
    .plgn__captcha a { color: inherit; font-weight: 500; }

    /* The floating reCAPTCHA badge sat on top of the footer on phones; the
       disclosure above replaces it (Google allows this with that wording). */
    /* Google parks it at right:-186px; hidden or not it still widened the phone
       layout viewport to 545px, so pin it inside the screen as well. */
    .grecaptcha-badge { visibility: hidden !important; left: 0 !important; right: auto !important; }

    @media (prefers-reduced-motion: reduce) {
        .plgn-root *, .plgn-root *::before, .plgn-root *::after { animation-duration: .001s !important; animation-iteration-count: 1 !important; transition-duration: .001s !important; }
        .auth-phone__btn[disabled]::before, .plgn__submit[disabled]::before { animation: plgn-spin 1.2s linear infinite !important; }
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
    var csrf = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
    var alertEl = document.getElementById('plgnAlert');
    function showAlert(msg) { if (!alertEl) return; alertEl.textContent = msg || ''; alertEl.hidden = !msg; }

    /* ---- Phone step polish (firebase-phone-auth.js still owns the flow; this
       only reads the same elements and strips spaces the same way it does). ---- */
    var phoneRoot = document.querySelector('.plgn [data-phone-auth]');
    if (phoneRoot) {
        var phoneIn = phoneRoot.querySelector('.js-phone');
        var sendBtn = phoneRoot.querySelector('.js-send');
        var codeStep = phoneRoot.querySelector('.auth-phone__code');
        var codeIn = phoneRoot.querySelector('.js-code');
        var otpBox = phoneRoot.querySelector('.plgn-otp');
        var cells = otpBox ? otpBox.querySelectorAll('.plgn-otp__cells span') : [];
        var toLine = document.getElementById('plgnOtpTo');
        var errEl = phoneRoot.querySelector('.js-error');

        function group(d) { return d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5) : d; }

        // 9701377681 → 97013 77681 as they type, caret kept on the same digit.
        if (phoneIn) phoneIn.addEventListener('input', function () {
            var v = phoneIn.value;
            if (v.charAt(0) === '+') return;          // international: leave as typed
            var caret = phoneIn.selectionStart || 0;
            var before = v.slice(0, caret).replace(/\D/g, '').length;
            var digits = v.replace(/\D/g, '').slice(0, 10);
            var out = group(digits);
            if (out === v) return;
            phoneIn.value = out;
            var pos = 0, seen = 0;
            while (pos < out.length && seen < before) { if (/\d/.test(out.charAt(pos))) seen++; pos++; }
            try { phoneIn.setSelectionRange(pos, pos); } catch (e) {}
        });
        if (phoneIn && sendBtn) phoneIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !sendBtn.disabled) { e.preventDefault(); sendBtn.click(); }
        });

        function paintCells() {
            if (!codeIn) return;
            var v = (codeIn.value || '').replace(/\D/g, '').slice(0, 6);
            var focused = document.activeElement === codeIn;
            for (var i = 0; i < cells.length; i++) {
                var ch = v.charAt(i);
                if (cells[i].textContent !== ch) {
                    cells[i].textContent = ch;
                    cells[i].classList.toggle('is-filled', !!ch);
                }
                cells[i].classList.toggle('is-active', focused && i === Math.min(v.length, 5));
            }
        }
        if (codeIn) {
            codeIn.addEventListener('input', function () { if (otpBox) otpBox.classList.remove('is-shake'); paintCells(); });
            codeIn.addEventListener('focus', paintCells);
            codeIn.addEventListener('blur', paintCells);
            // Keep the caret at the end — the cells can't show a mid-string caret.
            codeIn.addEventListener('click', function () { var n = codeIn.value.length; try { codeIn.setSelectionRange(n, n); } catch (e) {} });
        }

        // The shared script flips [hidden] on the steps and clears the code value
        // without firing `input` — watch for that and repaint.
        if (window.MutationObserver && codeStep) new MutationObserver(function () {
            if (!codeStep.hidden && toLine && phoneIn) {
                var d = phoneIn.value.replace(/\D/g, '');
                toLine.innerHTML = '';
                toLine.appendChild(document.createTextNode('Sent to '));
                var b = document.createElement('b');
                b.textContent = phoneIn.value.charAt(0) === '+' ? phoneIn.value : '+91 ' + group(d.slice(-10));
                toLine.appendChild(b);
            }
            paintCells();
        }).observe(codeStep, { attributes: true, attributeFilter: ['hidden'] });

        // A wrong code: shake the cells and clear them so the retry starts clean.
        if (window.MutationObserver && errEl) new MutationObserver(function () {
            if (errEl.hidden || !codeStep || codeStep.hidden || !otpBox) return;
            otpBox.classList.remove('is-shake'); void otpBox.offsetWidth; otpBox.classList.add('is-shake');
            if (navigator.vibrate) { try { navigator.vibrate(30); } catch (e) {} }
            if (codeIn && codeIn.value.length === 6) { codeIn.value = ''; paintCells(); codeIn.focus(); }
        }).observe(errEl, { attributes: true, attributeFilter: ['hidden'], childList: true, characterData: true, subtree: true });

        paintCells();
    }

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
            // GIS draws a fixed-width iframe (200–400px). Measure once the card has
            // settled and redraw if the column width changes, so it always spans
            // the card instead of sitting half-width in the middle.
            var drawnW = 0;
            var draw = function () {
                var w = Math.max(200, Math.min(400, Math.floor(slot.clientWidth)));
                if (!slot.clientWidth || w === drawnW) return;
                drawnW = w;
                window.google.accounts.id.renderButton(slot, { theme: 'outline', size: 'large', width: w, text: 'continue_with', shape: 'rectangular', logo_alignment: 'center' });
                setTimeout(function () { slot.classList.add('is-ready'); }, 120);
            };
            draw();
            var t = 0, di = setInterval(function () { draw(); if (++t > 20 || slot.childElementCount) clearInterval(di); }, 150);
            if (window.ResizeObserver) {
                var rt; new ResizeObserver(function () { clearTimeout(rt); rt = setTimeout(draw, 150); }).observe(slot);
            }
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
