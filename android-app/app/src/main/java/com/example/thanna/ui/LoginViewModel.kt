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
    data object EnterPhone : LoginStage
    data object VerifyOtp : LoginStage
    data object Success : LoginStage
}

data class LoginUiState(
    val countryCode: String = "+91",
    val phoneNumber: String = "",
    val otp: String = "",
    val verificationToken: String? = null,
    val token: String? = null,
    val isLoading: Boolean = false,
    val successMessage: String? = null,
    val errorMessage: String? = null,
    val stage: LoginStage = LoginStage.EnterPhone
) {
    val isPhoneValid: Boolean
        get() = phoneNumber.length == 10

    val isOtpValid: Boolean
        get() = otp.length == 6

    val canContinue: Boolean
        get() = isPhoneValid && !isLoading
}

class LoginViewModel : ViewModel() {

    private val authRepository = HaraanAuthRepository()
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onPhoneNumberChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(10)
        _uiState.update {
            it.copy(
                phoneNumber = digitsOnly,
                errorMessage = null
            )
        }
    }

    fun onOtpChange(input: String) {
        val digitsOnly = input.filter { it.isDigit() }.take(6)
        _uiState.update {
            it.copy(
                otp = digitsOnly,
                errorMessage = null
            )
        }
    }

    fun resetToPhoneEntry() {
        _uiState.update {
            it.copy(
                stage = LoginStage.EnterPhone,
                otp = "",
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
            val fullNumber = "${state.countryCode}${state.phoneNumber}"
            runCatching { authRepository.requestOtp(fullNumber) }
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
                            errorMessage = throwable.message ?: "Failed to request OTP. Please check your network."
                        )
                    }
                }
        }
    }

    fun verifyOtp(onSuccess: (String) -> Unit) {
        val state = _uiState.value
        val verificationToken = state.verificationToken
        if (verificationToken.isNullOrBlank()) {
            _uiState.update { it.copy(errorMessage = "Session expired. Please request OTP again.") }
            return
        }
        if (!state.isOtpValid || state.isLoading) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, successMessage = null) }
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
                            errorMessage = throwable.message ?: "Invalid OTP. Please try again."
                        )
                    }
                }
        }
    }
}
