package com.haraan.app.ui.main

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.DataRepository
import com.haraan.app.data.HaraanAuthRepository
import com.haraan.app.ui.main.MainScreenUiState.Success
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class MainScreenViewModel(dataRepository: DataRepository) : ViewModel() {
  private val authRepository = HaraanAuthRepository()
  private val _loginUiState = MutableStateFlow(LoginUiState())

  val uiState: StateFlow<MainScreenUiState> =
    dataRepository.data
      .map<List<String>, MainScreenUiState>(::Success)
      .catch { emit(MainScreenUiState.Error(it)) }
      .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), MainScreenUiState.Loading)

  val loginUiState: StateFlow<LoginUiState> = _loginUiState.asStateFlow()

  fun onPhoneChanged(phone: String) {
    _loginUiState.value = _loginUiState.value.copy(phone = phone, errorMessage = null)
  }

  fun onOtpChanged(otp: String) {
    _loginUiState.value = _loginUiState.value.copy(otp = otp, errorMessage = null)
  }

  fun requestOtp() {
    val phone = normalizePhone(_loginUiState.value.phone)
    if (phone.isBlank()) {
      _loginUiState.value = _loginUiState.value.copy(errorMessage = "Enter a valid phone number.")
      return
    }

    viewModelScope.launch {
      _loginUiState.value = _loginUiState.value.copy(isLoading = true, errorMessage = null, successMessage = null)
      runCatching { authRepository.requestOtp(phone) }
        .onSuccess { result ->
          _loginUiState.value = _loginUiState.value.copy(
            phone = result.phone,
            verificationToken = result.verificationToken,
            stage = LoginStage.VerifyOtp,
            isLoading = false,
            otp = "",
            successMessage = result.message,
            errorMessage = null,
          )
        }
        .onFailure { throwable ->
          _loginUiState.value = _loginUiState.value.copy(
            isLoading = false,
            errorMessage = throwable.message ?: "Failed to send OTP.",
          )
        }
    }
  }

  fun verifyOtp() {
    val currentState = _loginUiState.value
    val token = currentState.verificationToken
    val otp = currentState.otp.trim()
    if (token.isNullOrBlank()) {
      _loginUiState.value = currentState.copy(errorMessage = "Request OTP again.")
      return
    }
    if (otp.length != 6) {
      _loginUiState.value = currentState.copy(errorMessage = "Enter the 6-digit OTP.")
      return
    }

    viewModelScope.launch {
      _loginUiState.value = currentState.copy(isLoading = true, errorMessage = null, successMessage = null)
      runCatching { authRepository.verifyOtp(token, otp) }
        .onSuccess { result ->
          _loginUiState.value = currentState.copy(
            isLoading = false,
            stage = LoginStage.Success,
            token = result.token,
            successMessage = result.message,
            userName = result.userName,
            errorMessage = null,
          )
        }
        .onFailure { throwable ->
          _loginUiState.value = currentState.copy(
            isLoading = false,
            errorMessage = throwable.message ?: "Invalid OTP.",
          )
        }
    }
  }

  private fun normalizePhone(phone: String): String {
    return phone.filter { it.isDigit() }
  }
}

data class LoginUiState(
  val phone: String = "",
  val otp: String = "",
  val verificationToken: String? = null,
  val token: String? = null,
  val userName: String? = null,
  val isLoading: Boolean = false,
  val successMessage: String? = null,
  val errorMessage: String? = null,
  val stage: LoginStage = LoginStage.EnterPhone,
)

sealed interface LoginStage {
  data object EnterPhone : LoginStage
  data object VerifyOtp : LoginStage
  data object Success : LoginStage
}

sealed interface MainScreenUiState {
  object Loading : MainScreenUiState

  data class Error(val throwable: Throwable) : MainScreenUiState

  data class Success(val data: List<String>) : MainScreenUiState
}
