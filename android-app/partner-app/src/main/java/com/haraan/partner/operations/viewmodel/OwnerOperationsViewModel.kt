package com.haraan.partner.operations.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.partner.operations.data.OperationsRepository
import com.haraan.partner.operations.model.OperationsMasterData
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class OwnerOperationsUiState(
    val isLoading: Boolean = false,
    val selectedTab: Int = 0, // 0: Overview/Revenue, 1: Heatmap, 2: Staff, 3: Funnel & Alerts, 4: AI Suggestions
    val data: OperationsMasterData = OperationsMasterData(),
    val actionMessage: String? = null,
    val isActionLoading: Boolean = false
)

class OwnerOperationsViewModel(
    private val repository: OperationsRepository,
    private val token: String
) : ViewModel() {

    private val _uiState = MutableStateFlow(OwnerOperationsUiState())
    val uiState: StateFlow<OwnerOperationsUiState> = _uiState.asStateFlow()

    fun loadOverview(venueId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }
            try {
                val master = repository.getOperationsOverview(token, venueId)
                _uiState.update { it.copy(isLoading = false, data = master) }
            } catch (e: Exception) {
                _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    fun selectTab(tabIndex: Int) {
        _uiState.update { it.copy(selectedTab = tabIndex) }
    }

    fun resolveAlert(venueId: Long, alertId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val ok = repository.resolveAlert(token, venueId, alertId)
                if (ok) {
                    val updatedAlerts = _uiState.value.data.alerts.filterNot { it.id == alertId }
                    val updatedData = _uiState.value.data.copy(alerts = updatedAlerts)
                    _uiState.update {
                        it.copy(
                            isActionLoading = false,
                            data = updatedData,
                            actionMessage = "Alert marked as resolved"
                        )
                    }
                } else {
                    _uiState.update { it.copy(isActionLoading = false) }
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    fun applySuggestion(venueId: Long, suggestionId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val ok = repository.applySuggestion(token, venueId, suggestionId)
                if (ok) {
                    val updatedSuggs = _uiState.value.data.suggestions.map {
                        if (it.id == suggestionId) it.copy(status = "applied") else it
                    }
                    val updatedData = _uiState.value.data.copy(suggestions = updatedSuggs)
                    _uiState.update {
                        it.copy(
                            isActionLoading = false,
                            data = updatedData,
                            actionMessage = "✨ AI Recommendation applied successfully!"
                        )
                    }
                } else {
                    _uiState.update { it.copy(isActionLoading = false) }
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    fun dismissSuggestion(venueId: Long, suggestionId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val ok = repository.dismissSuggestion(token, venueId, suggestionId)
                if (ok) {
                    val updatedSuggs = _uiState.value.data.suggestions.filterNot { it.id == suggestionId }
                    val updatedData = _uiState.value.data.copy(suggestions = updatedSuggs)
                    _uiState.update {
                        it.copy(
                            isActionLoading = false,
                            data = updatedData,
                            actionMessage = "Suggestion dismissed"
                        )
                    }
                } else {
                    _uiState.update { it.copy(isActionLoading = false) }
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    fun clearActionMessage() {
        _uiState.update { it.copy(actionMessage = null) }
    }
}