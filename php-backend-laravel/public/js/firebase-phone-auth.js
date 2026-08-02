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
        // Optional WhatsApp-first pair. When present, the code is attempted over
        // WhatsApp and Firebase becomes the fallback beneath it; when absent, this
        // file behaves exactly as it always has. Leaving them off is how the partner
        // console (or any surface that must stay on SMS) opts out.
        var otpStartUrl = root.getAttribute('data-otp-start-url');
        var otpVerifyUrl = root.getAttribute('data-otp-verify-url');
        // "member" (default) or "partner". The server re-checks it and binds it to
        // the code at send time, so this only tells it which page asked — it is not
        // what grants partner access.
        var otpSurface = root.getAttribute('data-otp-surface') || 'member';
        // Set only while a WhatsApp code is outstanding — it also decides which
        // verifier submitCode() uses, so it must be cleared whenever we fall back.
        var waToken = null;
        var confirmation = null;
        var webOtpAbort = null;
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

        // Move to the code screen. Shared by both channels so they look identical —
        // the user should never be able to tell which one carried the code.
        function showCodeStep(isResend) {
            if (!isResend) busy(sendBtn, false);
            if (enterStep) enterStep.hidden = true;
            if (codeStep) codeStep.hidden = false;
            if (codeInput) { codeInput.value = ''; codeInput.focus(); }
            startResendCountdown();
            startWebOtp();
        }

        // Try WhatsApp first, fall back to Firebase SMS.
        //
        // The fallback is silent and covers EVERY failure — no approved template, no
        // credentials, a number not on WhatsApp, a provider outage, or this endpoint
        // itself being down. A login is the one message with nothing behind it: if it
        // doesn't arrive the person cannot get in, so "couldn't send" must always
        // become "send it the other way", never an error on screen.
        function sendNow(isResend, phone) {
            if (!otpStartUrl) { sendViaFirebase(isResend, phone); return; }

            fetch(otpStartUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ phone: phone, surface: otpSurface }),
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (res.ok && data.channel === 'whatsapp' && data.token) {
                        waToken = data.token;
                        showCodeStep(isResend);
                        return;
                    }
                    sendViaFirebase(isResend, phone);
                });
            }).catch(function () {
                sendViaFirebase(isResend, phone);
            });
        }

        // The actual Firebase send — reached only after the pre-check (if any) passes.
        function sendViaFirebase(isResend, phone) {
            waToken = null;
            var v;
            try { v = ensureVerifier(); }
            catch (e) {
                releaseSendBtns(isResend);
                showError('Could not start verification. Reload and try again.');
                return;
            }

            auth.signInWithPhoneNumber(phone, v).then(function (result) {
                confirmation = result;
                showCodeStep(isResend);
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

        var submitting = false;

        // Codes we sent over WhatsApp are verified by us, not by Firebase — there is
        // no confirmation object to confirm against.
        function submitWhatsAppCode(code) {
            submitting = true;
            busy(verifyBtn, true, 'Verifying…');

            fetch(otpVerifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ token: waToken, code: code }),
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (res.ok && data.redirect) {
                        window.location.assign(data.redirect);
                        return;
                    }
                    // 410 = expired, so send them back to the number step rather than
                    // leaving them typing into a code that no longer exists.
                    if (res.status === 410 || res.status === 429) {
                        waToken = null;
                        if (codeStep) codeStep.hidden = true;
                        if (enterStep) enterStep.hidden = false;
                    }
                    showError(data.error || 'Sign-in failed. Please try again.');
                    submitting = false;
                    busy(verifyBtn, false);
                });
            }).catch(function () {
                showError('Network error. Please try again.');
                submitting = false;
                busy(verifyBtn, false);
            });
        }

        function submitCode() {
            if (submitting) return;
            showError('');
            var code = ((codeInput && codeInput.value) || '').replace(/\D/g, '');
            if (code.length < 6) { showError('Enter the 6-digit code.'); return; }

            if (waToken) { submitWhatsAppCode(code); return; }

            if (!confirmation) { showError('Please request a code first.'); return; }
            submitting = true;
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
                        submitting = false;
                        busy(verifyBtn, false);
                        return;
                    }
                    window.location.assign(data.redirect || '/');
                });
            }).catch(function (err) {
                submitting = false;
                busy(verifyBtn, false);
                showError(mapErr(err));
            });
        }
        if (verifyBtn) verifyBtn.addEventListener('click', submitCode);

        // Auto-submit the instant 6 digits are present — no button tap needed.
        if (codeInput) codeInput.addEventListener('input', function () {
            var digits = (codeInput.value || '').replace(/\D/g, '').slice(0, 6);
            if (codeInput.value !== digits) codeInput.value = digits;
            if (digits.length === 6) submitCode();
        });

        // WebOTP autofill: when the browser + SMS support it, read the code straight from
        // the SMS and submit — the user never types. No-op where unsupported / SMS isn't
        // domain-bound (progressive enhancement; manual entry still works).
        function startWebOtp() {
            if (!('OTPCredential' in window) || !codeInput) return;
            try {
                webOtpAbort = new AbortController();
                navigator.credentials.get({ otp: { transport: ['sms'] }, signal: webOtpAbort.signal })
                    .then(function (otp) {
                        if (otp && otp.code) {
                            codeInput.value = (otp.code || '').replace(/\D/g, '').slice(0, 6);
                            submitCode();
                        }
                    })
                    .catch(function () { /* dismissed / unsupported — ignore */ });
            } catch (e) { /* ignore */ }
        }

        if (changeLink) changeLink.addEventListener('click', function (e) {
            e.preventDefault();
            confirmation = null;
            if (webOtpAbort) { try { webOtpAbort.abort(); } catch (e2) {} webOtpAbort = null; }
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
