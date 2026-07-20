package com.haraan.app.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.HaraanAuthRepository
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
    val stage: LoginStage = LoginStage.EnterCredentials
) {
    /** Deliberately loose — the server is the authority; this only gates the button. */
    val isEmailValid: Boolean
        get() = email.contains('@') && email.substringAfterLast('@').contains('.')

    /** Matches the backend rule (min:6) so the button doesn't promise a request that 422s. */
    val isPasswordValid: Boolean
        get() = password.length >= 6

    val canSubmit: Boolean
        get() = isEmailValid && isPasswordValid && !isLoading
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
}
