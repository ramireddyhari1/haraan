/**
 * Firebase phone-number sign-in for the public website login.
 *
 * Each `[data-phone-auth]` block (the /login card and the site-wide drawer both
 * render one) gets wired here: enter number -> invisible reCAPTCHA -> SMS code ->
 * confirm -> post the resulting Firebase ID token to the server, which verifies it
 * and opens a session (see FirebasePhoneAuthController). Config comes from
 * window.HaraanFirebase, set in the layout only when a web API key is configured.
 */
(function () {
    'use strict';

    function ready(cb) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', cb);
        } else {
            cb();
        }
    }

    // The Firebase SDK loads as separate scripts; poll briefly rather than race it.
    function whenFirebaseReady(cb) {
        var waited = 0;
        var timer = setInterval(function () {
            if (window.firebase && window.firebase.auth) {
                clearInterval(timer);
                cb();
            } else if ((waited += 100) > 8000) {
                clearInterval(timer); // SDK blocked/offline — leave the other paths working.
            }
        }, 100);
    }

    function initAll() {
        var roots = document.querySelectorAll('[data-phone-auth]');
        var cfg = window.HaraanFirebase;
        if (!roots.length || !cfg) return;

        whenFirebaseReady(function () {
            if (!firebase.apps || !firebase.apps.length) {
                firebase.initializeApp({
                    apiKey: cfg.apiKey,
                    authDomain: cfg.authDomain,
                    projectId: cfg.projectId,
                    appId: cfg.appId,
                });
            }
            var auth = firebase.auth();
            try { auth.useDeviceLanguage(); } catch (e) { /* older SDKs */ }
            roots.forEach(function (root) { wire(root, auth); });
        });
    }

    function wire(root, auth) {
        var phoneInput = root.querySelector('.js-phone');
        var codeInput = root.querySelector('.js-code');
        var sendBtn = root.querySelector('.js-send');
        var verifyBtn = root.querySelector('.js-verify');
        var changeLink = root.querySelector('.js-change');
        var resendBtn = root.querySelector('.js-resend');
        var enterStep = root.querySelector('.auth-phone__enter');
        var codeStep = root.querySelector('.auth-phone__code');
        var errorEl = root.querySelector('.js-error');
        var recaptchaHost = root.querySelector('.js-recaptcha');
        var postUrl = root.getAttribute('data-post-url');
        // Optional server pre-check: when present, we ask the server whether a number is
        // even allowed to receive a code BEFORE hitting Firebase, so a public login can't
        // be used to pump SMS to arbitrary numbers. The partner console sets this; the
        // member /login (which sends to anyone, then find-or-creates) leaves it off.
        var preCheckUrl = root.getAttribute('data-precheck-url');
        var confirmation = null;
        var verifier = null;
        var resendTimer = null;

        function showError(msg) {
            if (!errorEl) return;
            errorEl.textContent = msg || '';
            errorEl.hidden = !msg;
        }

        function busy(btn, on, label) {
            if (!btn) return;
            btn.disabled = on;
            if (on) {
                btn.dataset.idle = btn.dataset.idle || btn.textContent;
                btn.textContent = label || 'Please wait…';
            } else if (btn.dataset.idle) {
                btn.textContent = btn.dataset.idle;
            }
        }

        // Bare local numbers default to India (+91), matching the app's phone flow.
        function normalize(v) {
            var s = (v || '').replace(/[\s\-()]/g, '');
            if (!s) return '';
            if (s.charAt(0) === '+') return s;
            return '+91' + s.replace(/^0+/, '');
        }

        // Create the invisible reCAPTCHA verifier ONCE and reuse it. Recreating one on
        // the same container throws "reCAPTCHA has already been rendered in this element";
        // the invisible widget simply re-executes on each retry, so reuse is correct.
        function ensureVerifier() {
            if (verifier) return verifier;
            // Compat namespaced signature: (container, params) on the default app.
            verifier = new firebase.auth.RecaptchaVerifier(recaptchaHost, { size: 'invisible' });
            return verifier;
        }

        // Pre-warm the invisible reCAPTCHA the moment the block is wired, so the widget
        // is already loaded and rendered by the time someone taps "Send OTP". Without
        // this it cold-starts on the click — a multi-second hang that reads as the app
        // freezing. render() is idempotent per widget; failures here are harmless
        // because requestCode() re-creates/re-renders on demand.
        function prewarmRecaptcha() {
            try {
                var v = ensureVerifier();
                if (v && typeof v.render === 'function') { v.render().catch(function () {}); }
            } catch (e) { /* SDK not ready yet — the send path will render on demand */ }
        }

        function mapErr(err) {
            switch (err && err.code) {
                case 'auth/invalid-phone-number': return 'That number looks invalid. Include the country code, e.g. +91…';
                case 'auth/missing-phone-number': return 'Please enter your phone number.';
                case 'auth/too-many-requests': return 'This number is temporarily blocked by SMS verification (too many attempts). It can stay blocked for a while — please continue with Google or email below, or try a different number.';
                case 'auth/invalid-verification-code': return 'Incorrect code. Please check and try again.';
                case 'auth/code-expired': return 'That code expired. Request a new one.';
                case 'auth/captcha-check-failed':
                case 'auth/network-request-failed': return 'Verification failed. Reload the page and try again.';
                default: return (err && err.message) || 'Something went wrong. Please try again.';
            }
        }

        // Disable "Resend code" for 30s after each send so people can't spam SMS,
        // counting down in the button label; then re-enable it.
        function startResendCountdown() {
            if (!resendBtn) return;
            var secs = 30;
            clearInterval(resendTimer);
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend code in ' + secs + 's';
            resendTimer = setInterval(function () {
                secs -= 1;
                if (secs <= 0) {
                    clearInterval(resendTimer);
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend code';
                } else {
                    resendBtn.textContent = 'Resend code in ' + secs + 's';
                }
            }, 1000);
        }

        // Re-enable whichever button reflected progress after a send is aborted/failed.
        function releaseSendBtns(isResend) {
            if (isResend) { if (resendBtn) { resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; } }
            else { busy(sendBtn, false); }
        }

        // The actual Firebase send — reached only after the pre-check (if any) passes.
        function sendNow(isResend, phone) {
            var v;
            try { v = ensureVerifier(); }
            catch (e) {
                releaseSendBtns(isResend);
                showError('Could not start verification. Reload and try again.');
                return;
            }

            auth.signInWithPhoneNumber(phone, v).then(function (result) {
                confirmation = result;
                if (!isResend) busy(sendBtn, false);
                if (enterStep) enterStep.hidden = true;
                if (codeStep) codeStep.hidden = false;
                if (codeInput) { codeInput.value = ''; codeInput.focus(); }
                startResendCountdown();
            }).catch(function (err) {
                releaseSendBtns(isResend);
                showError(mapErr(err));
                // Keep the verifier — invisible reCAPTCHA re-executes on the next attempt.
            });
        }

        // Send (or resend) the OTP. `isResend` drives which button reflects progress.
        function requestCode(isResend) {
            showError('');
            var phone = normalize(phoneInput && phoneInput.value);
            if (phone.length < 8) { showError('Enter a valid phone number with country code.'); return; }

            if (isResend) { if (resendBtn) { clearInterval(resendTimer); resendBtn.disabled = true; resendBtn.textContent = 'Resending…'; } }
            else { busy(sendBtn, true, 'Sending…'); }

            // No pre-check configured → straight to Firebase (member /login behaviour).
            if (!preCheckUrl) { sendNow(isResend, phone); return; }

            // Pre-check first: only spend an SMS on a number the server will accept.
            fetch(preCheckUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ phone: phone }),
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (res.ok && data.eligible) { sendNow(isResend, phone); return; }
                    releaseSendBtns(isResend);
                    showError(data.error || 'No partner account is registered for this number. Use Google or email, or contact your admin.');
                });
            }).catch(function () {
                // Pre-check itself failed (network/500) — don't strand a real partner; fall
                // through to the normal Firebase send, which still enforces server-side gating
                // after verification.
                sendNow(isResend, phone);
            });
        }

        if (sendBtn) sendBtn.addEventListener('click', function () { requestCode(false); });
        if (resendBtn) resendBtn.addEventListener('click', function () { requestCode(true); });

        if (verifyBtn) verifyBtn.addEventListener('click', function () {
            showError('');
            if (!confirmation) { showError('Please request a code first.'); return; }
            var code = ((codeInput && codeInput.value) || '').trim();
            if (code.length < 6) { showError('Enter the 6-digit code.'); return; }
            busy(verifyBtn, true, 'Verifying…');

            confirmation.confirm(code).then(function (cred) {
                return cred.user.getIdToken();
            }).then(function (idToken) {
                return fetch(postUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id_token: idToken }),
                });
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok) {
                        // The code verified with Firebase, so a non-OK here is a *server* problem,
                        // not a wrong code. Surface the real reason instead of a misleading "bad code":
                        // 429 = throttle (shared auth limiter), 419 = expired session/CSRF, else the
                        // controller's own {error} / {message}, falling back to the status code.
                        var msg = data.error || data.message;
                        if (res.status === 429) {
                            msg = 'Too many attempts from this network. Please wait a minute and try again.';
                        } else if (res.status === 419) {
                            msg = 'Your session expired. Reload the page and sign in again.';
                        } else if (!msg) {
                            msg = 'Sign-in failed (error ' + res.status + '). Please try again in a moment.';
                        }
                        showError(msg);
                        busy(verifyBtn, false);
                        return;
                    }
                    window.location.assign(data.redirect || '/');
                });
            }).catch(function (err) {
                busy(verifyBtn, false);
                showError(mapErr(err));
            });
        });

        if (changeLink) changeLink.addEventListener('click', function (e) {
            e.preventDefault();
            confirmation = null;
            clearInterval(resendTimer);
            showError('');
            if (codeInput) codeInput.value = '';
            if (codeStep) codeStep.hidden = true;
            if (enterStep) enterStep.hidden = false;
            if (phoneInput) phoneInput.focus();
        });

        // Warm the reCAPTCHA now so the first OTP send is instant, not a cold-start hang.
        prewarmRecaptcha();
    }

    ready(initAll);
})();
