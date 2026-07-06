package com.example.thanna.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.thanna.data.HaraanAuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

sealed interface LoginStage {
    /** Step 1 — email only. */
    data object EnterEmail : LoginStage
    /** Step 2 — the 6-digit code. */
    data object VerifyOtp : LoginStage
    /** Step 3 — new users only: name + date of birth. */
    data object CompleteProfile : LoginStage
    data object Success : LoginStage
}

data class LoginUiState(
    val email: String = "",
    val name: String = "",
    /** Date of birth as yyyy-MM-dd; blank until the new user picks one. */
    val dob: String = "",
    val otp: String = "",
    val verificationToken: String? = null,
    val token: String? = null,
    val newUser: Boolean = false,
    val isLoading: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null,
    val stage: LoginStage = LoginStage.EnterEmail
) {
    val isEmailValid: Boolean
        get() {
            val at = email.indexOf('@')
            return at > 0 && email.indexOf('.', at) > at + 1 && !email.endsWith(".")
        }

    val isOtpValid: Boolean
        get() = otp.length == 6

    val canContinue: Boolean
        get() = isEmailValid && !isLoading

    val canComplete: Boolean
        get() = name.trim().isNotEmpty() && dob.isNotBlank() && !isLoading
}

class LoginViewModel : ViewModel() {

    private val authRepository = HaraanAuthRepository()
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onEmailChange(input: String) {
        _uiState.update { it.copy(email = input.trim().take(255), errorMessage = null) }
    }

    fun onNameChange(input: String) {
        _uiState.update { it.copy(name = input.take(120), errorMessage = null) }
    }

    /** [isoDate] is yyyy-MM-dd from the date picker. */
    fun onDobChange(isoDate: String) {
        _uiState.update { it.copy(dob = isoDate, errorMessage = null) }
    }

    fun onOtpChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(6)
        _uiState.update { it.copy(otp = digitsOnly, errorMessage = null) }
    }

    fun resetToEmail() {
        _uiState.update {
            it.copy(
                stage = LoginStage.EnterEmail,
                otp = "",
                errorMessage = null,
                successMessage = null
            )
        }
    }

    fun requestOtp() {
        val state = _uiState.value
        if (!state.isEmailValid || state.isLoading) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            runCatching { authRepository.requestEmailOtp(state.email) }
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
            runCatching { authRepository.verifyEmailOtp(verificationToken, state.otp) }
                .onSuccess { result ->
                    if (result.newUser || result.token.isNullOrBlank()) {
                        // Brand-new email → collect name + date of birth before creating the account.
                        _uiState.update {
                            it.copy(
                                isLoading = false,
                                newUser = true,
                                verificationToken = result.verificationToken ?: verificationToken,
                                stage = LoginStage.CompleteProfile,
                                successMessage = result.message
                            )
                        }
                    } else {
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

    fun completeProfile(onSuccess: (String) -> Unit) {
        val state = _uiState.value
        val verificationToken = state.verificationToken
        if (verificationToken.isNullOrBlank()) {
            _uiState.update { it.copy(errorMessage = "Session expired. Please request a code again.") }
            return
        }
        if (!state.canComplete) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
            runCatching { authRepository.completeEmailProfile(verificationToken, state.name.trim(), state.dob) }
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
                            errorMessage = throwable.message ?: "Couldn't finish sign-up. Please try again."
                        )
                    }
                }
        }
    }
}
