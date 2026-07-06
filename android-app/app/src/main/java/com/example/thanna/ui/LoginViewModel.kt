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
    data object EnterDetails : LoginStage
    data object VerifyOtp : LoginStage
    data object Success : LoginStage
}

data class LoginUiState(
    val email: String = "",
    val name: String = "",
    val age: String = "",
    val otp: String = "",
    val verificationToken: String? = null,
    val token: String? = null,
    val isLoading: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null,
    val stage: LoginStage = LoginStage.EnterDetails
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

    fun onAgeChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(3)
        _uiState.update { it.copy(age = digitsOnly, errorMessage = null) }
    }

    fun onOtpChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(6)
        _uiState.update { it.copy(otp = digitsOnly, errorMessage = null) }
    }

    fun resetToDetails() {
        _uiState.update {
            it.copy(
                stage = LoginStage.EnterDetails,
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
            runCatching { authRepository.requestEmailOtp(state.email, state.name, state.age) }
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
}
