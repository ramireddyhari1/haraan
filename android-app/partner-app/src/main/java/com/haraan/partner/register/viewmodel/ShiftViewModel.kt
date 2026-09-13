package com.haraan.partner.register.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.partner.register.data.ShiftRepository
import com.haraan.partner.register.data.ShiftResource
import com.haraan.partner.register.model.HistoricalShiftSummary
import com.haraan.partner.register.model.ShiftSessionUiModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class ShiftUiState(
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val venueId: Long = 0L,
    val venueName: String = "Arena Register",
    val activeShift: ShiftSessionUiModel? = null,
    val history: List<HistoricalShiftSummary> = emptyList(),
    val errorMessage: String? = null,
    val showOpenShiftDialog: Boolean = false,
    val showCashDropDialog: Boolean = false,
    val showCloseoutSheet: Boolean = false,
    val showHistoryScreen: Boolean = false,
    val isSubmittingAction: Boolean = false,
    val pendingOfflineActionsCount: Int = 0,
)

class ShiftViewModel(
    private val repository: ShiftRepository,
    private val token: String,
) : ViewModel() {

    private val _uiState = MutableStateFlow(ShiftUiState())
    val uiState: StateFlow<ShiftUiState> = _uiState.asStateFlow()

    fun init(venueId: Long) {
        if (_uiState.value.venueId != venueId || _uiState.value.activeShift == null) {
            _uiState.update { it.copy(venueId = venueId) }
            loadCurrentShift(venueId)
        }
    }

    fun loadCurrentShift(venueId: Long = _uiState.value.venueId, forceRefresh: Boolean = false) {
        viewModelScope.launch {
            repository.getCurrentShift(token, venueId, forceRefresh).collect { resource ->
                when (resource) {
                    is ShiftResource.Loading -> {
                        _uiState.update { it.copy(isLoading = !forceRefresh, isRefreshing = forceRefresh) }
                    }
                    is ShiftResource.Success -> {
                        _uiState.update {
                            it.copy(
                                isLoading = false,
                                isRefreshing = false,
                                activeShift = resource.data,
                                errorMessage = null,
                            )
                        }
                    }
                    is ShiftResource.Error -> {
                        _uiState.update {
                            it.copy(
                                isLoading = false,
                                isRefreshing = false,
                                errorMessage = resource.message,
                            )
                        }
                    }
                }
            }
        }
    }

    fun openShift(openingFloat: Double, note: String?) {
        val venueId = _uiState.value.venueId
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingAction = true, errorMessage = null) }
            val result = repository.openShift(token, venueId, openingFloat, note)
            result.onSuccess { shift ->
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        showOpenShiftDialog = false,
                        activeShift = shift,
                    )
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        errorMessage = err.message ?: "Failed to open shift",
                    )
                }
            }
        }
    }

    fun recordDrop(amount: Double, category: String, reason: String?) {
        val venueId = _uiState.value.venueId
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingAction = true, errorMessage = null) }
            val result = repository.recordDrop(token, venueId, amount, category, reason)
            result.onSuccess { shift ->
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        showCashDropDialog = false,
                        activeShift = shift,
                    )
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        errorMessage = err.message ?: "Failed to record cash drop",
                    )
                }
            }
        }
    }

    fun closeShift(countedCash: Double, note: String?, denominations: Map<String, Int>?) {
        val venueId = _uiState.value.venueId
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingAction = true, errorMessage = null) }
            val result = repository.closeShift(token, venueId, countedCash, note, denominations)
            result.onSuccess {
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        showCloseoutSheet = false,
                        activeShift = null, // Shift is now closed
                    )
                }
                loadHistory()
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isSubmittingAction = false,
                        errorMessage = err.message ?: "Failed to close shift",
                    )
                }
            }
        }
    }

    fun loadHistory() {
        val venueId = _uiState.value.venueId
        viewModelScope.launch {
            val result = repository.getShiftHistory(token, venueId)
            result.onSuccess { list ->
                _uiState.update { it.copy(history = list) }
            }
        }
    }

    fun syncOfflineQueue() {
        viewModelScope.launch {
            val count = repository.syncOfflineActions(token)
            if (count > 0) {
                loadCurrentShift(forceRefresh = true)
            }
        }
    }

    fun setShowOpenShiftDialog(show: Boolean) {
        _uiState.update { it.copy(showOpenShiftDialog = show) }
    }

    fun setShowCashDropDialog(show: Boolean) {
        _uiState.update { it.copy(showCashDropDialog = show) }
    }

    fun setShowCloseoutSheet(show: Boolean) {
        _uiState.update { it.copy(showCloseoutSheet = show) }
    }

    fun setShowHistoryScreen(show: Boolean) {
        _uiState.update { it.copy(showHistoryScreen = show) }
        if (show) loadHistory()
    }

    fun clearError() {
        _uiState.update { it.copy(errorMessage = null) }
    }
}
