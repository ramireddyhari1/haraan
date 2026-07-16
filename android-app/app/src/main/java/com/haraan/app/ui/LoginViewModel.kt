package com.haraan.app.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.HaraanAuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

sealed interface LoginStage {
    /** Step 1 — the mobile number the WhatsApp code goes to. */
    data object EnterPhone : LoginStage
    /** Step 2 — the 6-digit code. */
    data object VerifyOtp : LoginStage
    data object Success : LoginStage
}

data class LoginUiState(
    /** The 10 national digits only; [DIAL_CODE] is prepended when we call the API. */
    val phone: String = "",
    val otp: String = "",
    val verificationToken: String? = null,
    val token: String? = null,
    val isLoading: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null,
    val stage: LoginStage = LoginStage.EnterPhone
) {
    val isPhoneValid: Boolean
        get() = phone.length == 10

    val isOtpValid: Boolean
        get() = otp.length == 6

    val canContinue: Boolean
        get() = isPhoneValid && !isLoading
}

class LoginViewModel : ViewModel() {

    private companion object {
        /**
         * India. The website's login posts `"91" + <10 digits>` (see the phone form in
         * site/layout.blade.php) and the bridge dials exactly what it's given, so the app
         * must send the same shape or the code goes nowhere.
         */
        const val DIAL_CODE = "91"
    }

    private val authRepository = HaraanAuthRepository()
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onPhoneChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(10)
        _uiState.update { it.copy(phone = digitsOnly, errorMessage = null) }
    }

    fun onOtpChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(6)
        _uiState.update { it.copy(otp = digitsOnly, errorMessage = null) }
    }

    fun resetToPhone() {
        _uiState.update {
            it.copy(
                stage = LoginStage.EnterPhone,
                otp = "",
                // Drop the token too: it belongs to the number they're leaving, and a
                // stale one would verify against the previous number's session.
                verificationToken = null,
                errorMessage = null,
                successMessage = null
            )
        }
    }

    fun requestOtp() {
        val state = _uiState.value
        if (!state.isPhoneValid || state.isLoading) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            runCatching { authRepository.requestOtp(DIAL_CODE + state.phone) }
                .onSuccess { result ->
                    _uiState.update {
                        it.copy(
                            verificationToken = result.verificationToken,
                            stage = LoginStage.VerifyOtp,
                            isLoading = false,
                            successMessage = result.message
                        )
                    }
                }
                .onFailure { throwable ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = throwable.message ?: "Failed to send the code. Please check your network."
                        )
                    }
                }
        }
    }

    fun verifyOtp(onSuccess: (String) -> Unit) {
        val state = _uiState.value
        val verificationToken = state.verificationToken
        if (verificationToken.isNullOrBlank()) {
            _uiState.update { it.copy(errorMessage = "Session expired. Please request a code again.") }
            return
        }
        if (!state.isOtpValid || state.isLoading) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            // No profile step here, unlike email sign-up: the WhatsApp endpoint creates the
            // account when the code is requested, so a verified code is always a login.
            runCatching { authRepository.verifyOtp(verificationToken, state.otp) }
                .onSuccess { result ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            token = result.token,
                            stage = LoginStage.Success,
                            successMessage = result.message
                        )
                    }
                    onSuccess(result.token)
                }
                .onFailure { throwable ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = throwable.message ?: "Invalid code. Please try again."
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
        if (_uiState.value.isLoading) return
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

}
