package com.haraan.partner.recurring.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.partner.recurring.data.RecurringRepository
import com.haraan.partner.recurring.model.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class StandingUiState(
    val metrics: StandingDashboardMetrics = StandingDashboardMetrics(),
    val todaySessions: List<TodaySessionItem> = emptyList(),
    val atRiskAlerts: List<AtRiskAlertItem> = emptyList(),
    val contracts: List<StandingContractSummary> = emptyList(),
    val selectedStatus: String = "all", // all, active, at_risk, paused, completed
    val searchQuery: String = "",
    val selectedContract: StandingContractDetail? = null,
    val conflictReport: ConflictReport? = null,
    val isCheckingConflicts: Boolean = false,
    val isLoading: Boolean = false,
    val isSubmitting: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val activeTab: Int = 0, // 0: Dashboard, 1: Contracts List
    val showCreateScreen: Boolean = false,
    val showDetailScreen: Boolean = false,
    val offlineCount: Int = 0
)

class StandingContractViewModel(
    private val repository: RecurringRepository,
    private val token: String
) : ViewModel() {

    private val _uiState = MutableStateFlow(StandingUiState())
    val uiState: StateFlow<StandingUiState> = _uiState.asStateFlow()

    fun loadData(venueId: Long, forceRefresh: Boolean = false) {
        loadDashboard(venueId, forceRefresh)
        loadContracts(venueId, forceRefresh)
        updateOfflineCount()
    }

    fun loadDashboard(venueId: Long, forceRefresh: Boolean = false) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }
            repository.getDashboardFlow(token, venueId, forceRefresh).collect { (metrics, sessions) ->
                _uiState.update { current ->
                    current.copy(
                        metrics = metrics,
                        todaySessions = sessions,
                        isLoading = false
                    )
                }
            }
        }
    }

    fun loadContracts(venueId: Long, forceRefresh: Boolean = false) {
        viewModelScope.launch {
            val status = _uiState.value.selectedStatus
            val search = _uiState.value.searchQuery
            repository.getContractsFlow(token, venueId, status, search, forceRefresh).collect { list ->
                _uiState.update { it.copy(contracts = list) }
            }
        }
    }

    fun setStatusFilter(venueId: Long, status: String) {
        _uiState.update { it.copy(selectedStatus = status) }
        loadContracts(venueId, forceRefresh = true)
    }

    fun setSearchQuery(venueId: Long, query: String) {
        _uiState.update { it.copy(searchQuery = query) }
        loadContracts(venueId, forceRefresh = false)
    }

    fun setActiveTab(tabIndex: Int) {
        _uiState.update { it.copy(activeTab = tabIndex) }
    }

    fun setShowCreateScreen(show: Boolean) {
        _uiState.update { it.copy(showCreateScreen = show, conflictReport = null) }
    }

    fun setShowDetailScreen(show: Boolean) {
        _uiState.update { it.copy(showDetailScreen = show) }
    }

    fun checkConflicts(
        venueId: Long,
        courtId: Long,
        dayOfWeek: String,
        startTime: String,
        endTime: String,
        activeFrom: String,
        activeUntil: String? = null,
        weeks: Int = 12
    ) {
        viewModelScope.launch {
            _uiState.update { it.copy(isCheckingConflicts = true) }
            try {
                val report = repository.checkConflicts(
                    token = token,
                    venueId = venueId,
                    courtId = courtId,
                    dayOfWeek = dayOfWeek,
                    startTime = startTime,
                    endTime = endTime,
                    activeFrom = activeFrom,
                    activeUntil = activeUntil,
                    weeks = weeks
                )
                _uiState.update { it.copy(conflictReport = report, isCheckingConflicts = false) }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(
                        isCheckingConflicts = false,
                        errorMessage = "Conflict check failed: ${e.localizedMessage}"
                    )
                }
            }
        }
    }

    fun createContract(venueId: Long, req: CreateContractRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true, errorMessage = null) }
            val result = repository.createContract(token, venueId, req)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        showCreateScreen = false,
                        successMessage = "Standing Contract created for ${req.customerName}!"
                    )
                }
                loadData(venueId, forceRefresh = true)
                onSuccess()
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to create contract"
                    )
                }
                updateOfflineCount()
            }
        }
    }

    fun selectContract(venueId: Long, contractId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, showDetailScreen = true) }
            val result = repository.fetchContractDetail(token, venueId, contractId)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isLoading = false
                    )
                }
            } else {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to load contract"
                    )
                }
            }
        }
    }

    fun skipSession(venueId: Long, contractId: Long, date: String, reason: String?) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.skipSession(token, venueId, contractId, date, reason)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Session on $date skipped. Slot freed on Day Grid."
                    )
                }
                loadDashboard(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to skip session"
                    )
                }
            }
        }
    }

    fun pauseContract(venueId: Long, contractId: Long, reason: String?) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.pauseContract(token, venueId, contractId, reason)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Contract paused."
                    )
                }
                loadData(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to pause contract"
                    )
                }
            }
        }
    }

    fun resumeContract(venueId: Long, contractId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.resumeContract(token, venueId, contractId)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Contract resumed. Upcoming slots materialized."
                    )
                }
                loadData(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to resume contract"
                    )
                }
            }
        }
    }

    fun terminateContract(venueId: Long, contractId: Long, reason: String?) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.terminateContract(token, venueId, contractId, reason)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Contract terminated and future slots released."
                    )
                }
                loadData(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to terminate contract"
                    )
                }
            }
        }
    }

    fun transferCourt(venueId: Long, contractId: Long, courtId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.transferCourt(token, venueId, contractId, courtId)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Contract transferred to new court successfully."
                    )
                }
                loadData(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to transfer court"
                    )
                }
            }
        }
    }

    fun recordAttendance(venueId: Long, contractId: Long, sessionId: Long, status: String) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.recordAttendance(token, venueId, contractId, sessionId, status)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Attendance marked: $status."
                    )
                }
                loadDashboard(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to record attendance"
                    )
                }
            }
        }
    }

    fun recordPayment(venueId: Long, contractId: Long, amount: Double, paymentType: String, method: String, notes: String?) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.recordPayment(token, venueId, contractId, amount, paymentType, method, notes)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        selectedContract = result.getOrNull(),
                        isSubmitting = false,
                        successMessage = "Payment of ₹$amount received ($method)!"
                    )
                }
                loadData(venueId, forceRefresh = true)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to record payment"
                    )
                }
            }
        }
    }

    fun syncOffline(venueId: Long) {
        viewModelScope.launch {
            val synced = repository.syncOfflineActions(token)
            updateOfflineCount()
            if (synced > 0) {
                _uiState.update { it.copy(successMessage = "Synced $synced offline actions!") }
                loadData(venueId, forceRefresh = true)
            }
        }
    }

    fun clearMessages() {
        _uiState.update { it.copy(errorMessage = null, successMessage = null) }
    }

    private fun updateOfflineCount() {
        _uiState.update { it.copy(offlineCount = repository.getPendingActionCount()) }
    }
}
