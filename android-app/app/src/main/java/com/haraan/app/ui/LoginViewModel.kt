package com.haraan.app.ui

import android.app.Activity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.HaraanAuthRepository
import com.haraan.app.data.PhoneAuthHelper
import com.haraan.app.data.PhoneSendResult
import com.haraan.app.data.PhoneVerifyResult
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

sealed interface LoginStage {
    /** Email + password entry — the only step. Sign-in and sign-up share it, as on the website. */
    data object EnterCredentials : LoginStage
    data object Success : LoginStage
}

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    /** Only collected on the "create account" variant of the form. */
    val name: String = "",
    val isSignUp: Boolean = false,
    val token: String? = null,
    val isLoading: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null,
    val stage: LoginStage = LoginStage.EnterCredentials,
    // ── Phone sign-in (Firebase SMS OTP) ──────────────────────────────────────
    /** Local part the user types; combined with the country code into E.164 to send. */
    val phone: String = "",
    val otp: String = "",
    /** Set once Firebase has sent a code — the screen swaps to the code-entry step. */
    val phoneVerificationId: String? = null,
    /**
     * Set instead when the code went out over WhatsApp (MSG91). Exactly one of this
     * and [phoneVerificationId] is ever non-null: they are the two channels behind
     * the same code-entry step, and [phoneCodeSent] is what the screen reads.
     */
    val phoneWaToken: String? = null,
    /** Non-zero while a resend is on cooldown (seconds remaining). */
    val phoneResendSeconds: Int = 0,
) {
    /** Deliberately loose — the server is the authority; this only gates the button. */
    val isEmailValid: Boolean
        get() = email.contains('@') && email.substringAfterLast('@').contains('.')

    /** Matches the backend rule (min:6) so the button doesn't promise a request that 422s. */
    val isPasswordValid: Boolean
        get() = password.length >= 6

    val canSubmit: Boolean
        get() = isEmailValid && isPasswordValid && !isLoading

    /**
     * Normalized E.164 for the typed number, or null if it can't be one. A leading '+'
     * is taken as an already-international number; a bare 10-digit number defaults to
     * India (+91), the app's home market.
     */
    val phoneE164: String?
        get() {
            val raw = phone.filter { it.isDigit() || it == '+' }
            val candidate = when {
                raw.startsWith("+") -> raw
                raw.length == 10 -> "+91$raw"
                else -> return null
            }
            return candidate.takeIf { Regex("^\\+[1-9]\\d{7,14}$").matches(it) }
        }

    val isPhoneValid: Boolean get() = phoneE164 != null

    val isOtpValid: Boolean get() = otp.length in 6..8

    val phoneCodeSent: Boolean get() = phoneVerificationId != null || phoneWaToken != null
}

class LoginViewModel : ViewModel() {

    private companion object {
        /** How long the success confirmation holds before the app takes over. */
        const val SUCCESS_BEAT_MS = 900L
    }

    private val authRepository = HaraanAuthRepository()
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onEmailChange(input: String) {
        _uiState.update { it.copy(email = input.trim(), errorMessage = null) }
    }

    fun onPasswordChange(input: String) {
        _uiState.update { it.copy(password = input, errorMessage = null) }
    }

    fun onNameChange(input: String) {
        _uiState.update { it.copy(name = input, errorMessage = null) }
    }

    /**
     * Leaving the credentials form (system Back). Drops transient messages so a failed
     * attempt doesn't follow the user back out to the landing card.
     */
    fun clearMessages() {
        _uiState.update { it.copy(errorMessage = null, successMessage = null) }
    }

    /** Toggle between "Sign in" and "Create account" — same endpoint, extra name field. */
    fun setSignUp(signUp: Boolean) {
        _uiState.update { it.copy(isSignUp = signUp, errorMessage = null, successMessage = null) }
    }

    /**
     * One call for both sign-in and sign-up: the backend creates the account when the
     * email is unknown, exactly like the website's password login.
     */
    fun signInWithPassword(onSuccess: (String) -> Unit) {
        val state = _uiState.value
        if (!state.canSubmit) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            runCatching {
                authRepository.passwordLogin(
                    email = state.email,
                    password = state.password,
                    name = state.name.takeIf { state.isSignUp },
                )
            }
                .onSuccess { result ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            token = result.token,
                            stage = LoginStage.Success,
                            successMessage = result.message
                        )
                    }
                    // Hold on the confirmation beat before handing control to the app.
                    // Without it the screen cuts straight to home, which reads as a jump
                    // rather than an arrival — the token is already stored either way.
                    delay(SUCCESS_BEAT_MS)
                    onSuccess(result.token)
                }
                .onFailure { throwable ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = throwable.message ?: "Couldn't sign you in. Please try again."
                        )
                    }
                }
        }
    }

    /**
     * Exchange a Google ID token (obtained by the screen via Credential Manager) for an app
     * JWT. No profile step — the backend creates the account from the Google profile.
     */
    fun signInWithGoogle(idToken: String, onSuccess: (String) -> Unit) {
        // NB: no `if (isLoading) return` guard here. The screen sets loading=true the
        // moment the Credential Manager sheet opens (to disable the button), so by the
        // time we get the token loading is ALWAYS true — an early-return would silently
        // drop every Google sign-in after the account picker. Double-tap is already
        // prevented by the button's `enabled = !isLoading`.
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            runCatching { authRepository.googleSignIn(idToken) }
                .onSuccess { result ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            token = result.token,
                            stage = LoginStage.Success,
                            successMessage = result.message
                        )
                    }
                    // Same confirmation beat as the password path — both ways in should
                    // arrive identically.
                    delay(SUCCESS_BEAT_MS)
                    onSuccess(result.token)
                }
                .onFailure { throwable ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = throwable.message ?: "Google sign-in failed. Please try again."
                        )
                    }
                }
        }
    }

    /** The screen calls this when Credential Manager itself fails (cancelled, no accounts, etc.). */
    fun onGoogleError(message: String) {
        _uiState.update { it.copy(isLoading = false, errorMessage = message) }
    }

    fun setLoading(loading: Boolean) {
        _uiState.update { it.copy(isLoading = loading, errorMessage = null) }
    }

    // ── Phone sign-in (Firebase SMS OTP) ──────────────────────────────────────

    fun onPhoneChange(input: String) {
        // Keep only what a phone number can contain so paste/formatting doesn't break E.164.
        _uiState.update { it.copy(phone = input.filter { c -> c.isDigit() || c == '+' || c == ' ' }, errorMessage = null) }
    }

    fun onOtpChange(input: String) {
        _uiState.update { it.copy(otp = input.filter { c -> c.isDigit() }.take(8), errorMessage = null) }
    }

    /** Leaving the phone flow (Back / switching method): drop the in-flight verification. */
    fun resetPhone() {
        _uiState.update {
            it.copy(
                otp = "",
                phoneVerificationId = null,
                phoneWaToken = null,
                phoneResendSeconds = 0,
                errorMessage = null,
            )
        }
    }

    /**
     * Step 1 of phone sign-in: **WhatsApp (MSG91) first, Firebase SMS beneath it** —
     * the same order the website uses.
     *
     * WhatsApp leads because it costs a fraction of an SMS, lands where these users
     * already are, and — unlike Firebase — needs no Play Integrity / SHA registration,
     * which is exactly what was keeping phone sign-in from working in the app at all.
     *
     * The fallback is deliberately silent. `phoneOtpStart` answers "use SMS" for every
     * reason WhatsApp couldn't be used and never throws, so the user just sees a code
     * arrive and never learns which pipe carried it. A login is the one message with
     * nothing behind it: no email copy, so non-delivery means they cannot get in.
     *
     * Needs an [Activity] for the Firebase app-check (reCAPTCHA / Play Integrity) on
     * the fallback path only.
     */
    fun sendPhoneCode(activity: Activity, onSuccess: (String) -> Unit) {
        val state = _uiState.value
        val e164 = state.phoneE164 ?: run {
            _uiState.update { it.copy(errorMessage = "Enter a valid phone number.") }
            return
        }
        if (state.isLoading) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }

            val waToken = authRepository.phoneOtpStart(e164)
            if (waToken != null) {
                _uiState.update { it.copy(isLoading = false, phoneWaToken = waToken, phoneVerificationId = null) }
                startResendCooldown()
                return@launch
            }

            when (val result = PhoneAuthHelper.sendCode(activity, e164)) {
                is PhoneSendResult.CodeSent -> {
                    _uiState.update {
                        it.copy(isLoading = false, phoneVerificationId = result.verificationId, phoneWaToken = null)
                    }
                    startResendCooldown()
                }
                is PhoneSendResult.AutoVerified -> exchangePhoneToken(result.idToken, onSuccess)
                is PhoneSendResult.Error ->
                    _uiState.update { it.copy(isLoading = false, errorMessage = result.message) }
            }
        }
    }

    /**
     * Step 2: confirm the typed code. Branches on which channel actually sent it —
     * the WhatsApp code is verified by our own backend, the SMS code by Firebase.
     */
    fun verifyPhoneCode(onSuccess: (String) -> Unit) {
        val state = _uiState.value
        if (!state.isOtpValid || state.isLoading) return

        val waToken = state.phoneWaToken
        if (waToken != null) {
            viewModelScope.launch {
                _uiState.update { it.copy(isLoading = true, errorMessage = null) }
                finishPhoneLogin(onSuccess) { authRepository.phoneOtpVerify(waToken, state.otp) }
            }
            return
        }

        val verificationId = state.phoneVerificationId ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            when (val result = PhoneAuthHelper.verifyCode(verificationId, state.otp)) {
                is PhoneVerifyResult.Success -> exchangePhoneToken(result.idToken, onSuccess)
                is PhoneVerifyResult.Error ->
                    _uiState.update { it.copy(isLoading = false, errorMessage = result.message) }
            }
        }
    }

    /** Backend hand-off shared by the manual-code and instant-verification paths. */
    private suspend fun exchangePhoneToken(idToken: String, onSuccess: (String) -> Unit) =
        finishPhoneLogin(onSuccess) { authRepository.firebasePhoneLogin(idToken) }

    /**
     * The tail both channels share: whichever call proves the number, the success and
     * failure handling is identical, so it lives in one place.
     */
    private suspend fun finishPhoneLogin(
        onSuccess: (String) -> Unit,
        login: suspend () -> com.haraan.app.data.VerifyOtpResult,
    ) {
        runCatching { login() }
            .onSuccess { result ->
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        token = result.token,
                        stage = LoginStage.Success,
                        successMessage = result.message,
                    )
                }
                delay(SUCCESS_BEAT_MS)
                onSuccess(result.token)
            }
            .onFailure { throwable ->
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = throwable.message ?: "Phone sign-in failed. Please try again.",
                    )
                }
            }
    }

    private fun startResendCooldown(seconds: Int = 30) {
        viewModelScope.launch {
            _uiState.update { it.copy(phoneResendSeconds = seconds) }
            while (_uiState.value.phoneResendSeconds > 0) {
                delay(1000)
                _uiState.update { it.copy(phoneResendSeconds = (it.phoneResendSeconds - 1).coerceAtLeast(0)) }
            }
        }
    }
}
