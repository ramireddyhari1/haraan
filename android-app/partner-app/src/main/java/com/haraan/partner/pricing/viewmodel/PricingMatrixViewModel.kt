package com.haraan.partner.pricing.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.partner.pricing.data.PricingRepository
import com.haraan.partner.pricing.model.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PricingUiState(
    val metrics: PricingDashboardMetrics = PricingDashboardMetrics(),
    val matrix: WeeklyPricingMatrix = WeeklyPricingMatrix(),
    val rules: List<PricingRuleItem> = emptyList(),
    val hierarchy: CourtHierarchyData = CourtHierarchyData(),
    val selectedCourtId: Long? = null,
    val activeTab: Int = 0, // 0: Matrix Grid, 1: Pricing Rules, 2: Court Split/Merge, 3: Yield Opportunities
    val showCreateRuleSheet: Boolean = false,
    val showSplitCourtDialog: Boolean = false,
    val showSlotRateDialog: Boolean = false,
    val selectedSlot: HourSlotRate? = null,
    val selectedSlotDay: String? = null,
    val isLoading: Boolean = false,
    val isSubmitting: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val offlineCount: Int = 0
)

class PricingMatrixViewModel(
    private val repository: PricingRepository,
    private val token: String
) : ViewModel() {

    private val _uiState = MutableStateFlow(PricingUiState())
    val uiState: StateFlow<PricingUiState> = _uiState.asStateFlow()

    fun loadAll(venueId: Long, courtId: Long? = null) {
        val targetCourtId = courtId ?: _uiState.value.selectedCourtId ?: 1L
        _uiState.update { it.copy(selectedCourtId = targetCourtId, isLoading = true) }
        loadDashboard(venueId)
        loadMatrix(venueId, targetCourtId)
        loadRules(venueId)
        loadHierarchy(venueId)
        updateOfflineCount()
    }

    fun selectCourt(venueId: Long, courtId: Long) {
        _uiState.update { it.copy(selectedCourtId = courtId) }
        loadMatrix(venueId, courtId)
    }

    fun setActiveTab(tabIndex: Int) {
        _uiState.update { it.copy(activeTab = tabIndex) }
    }

    fun setShowCreateRuleSheet(show: Boolean) {
        _uiState.update { it.copy(showCreateRuleSheet = show) }
    }

    fun setShowSplitCourtDialog(show: Boolean) {
        _uiState.update { it.copy(showSplitCourtDialog = show) }
    }

    fun selectSlot(slot: HourSlotRate, day: String) {
        _uiState.update { it.copy(selectedSlot = slot, selectedSlotDay = day, showSlotRateDialog = true) }
    }

    fun dismissSlotDialog() {
        _uiState.update { it.copy(showSlotRateDialog = false, selectedSlot = null, selectedSlotDay = null) }
    }

    fun loadDashboard(venueId: Long) {
        viewModelScope.launch {
            repository.getDashboardFlow(token, venueId).collect { metrics ->
                _uiState.update { it.copy(metrics = metrics) }
            }
        }
    }

    fun loadMatrix(venueId: Long, courtId: Long) {
        viewModelScope.launch {
            repository.getMatrixFlow(token, venueId, courtId).collect { matrix ->
                _uiState.update { it.copy(matrix = matrix, isLoading = false) }
            }
        }
    }

    fun loadRules(venueId: Long) {
        viewModelScope.launch {
            repository.getRulesFlow(token, venueId).collect { rules ->
                _uiState.update { it.copy(rules = rules) }
            }
        }
    }

    fun loadHierarchy(venueId: Long) {
        viewModelScope.launch {
            repository.getHierarchyFlow(token, venueId).collect { hierarchy ->
                _uiState.update { it.copy(hierarchy = hierarchy) }
            }
        }
    }

    fun createRule(venueId: Long, req: CreatePricingRuleRequest) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true, errorMessage = null) }
            val result = repository.createRule(token, venueId, req)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        showCreateRuleSheet = false,
                        successMessage = "Pricing rule '${req.name}' created!"
                    )
                }
                loadAll(venueId, _uiState.value.selectedCourtId)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to create rule"
                    )
                }
                updateOfflineCount()
            }
        }
    }

    fun toggleRule(venueId: Long, ruleId: Long) {
        viewModelScope.launch {
            val result = repository.toggleRule(token, venueId, ruleId)
            if (result.isSuccess) {
                _uiState.update { it.copy(successMessage = "Rule state updated.") }
                loadRules(venueId)
                _uiState.value.selectedCourtId?.let { loadMatrix(venueId, it) }
            } else {
                _uiState.update { it.copy(errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to toggle rule") }
            }
        }
    }

    fun deleteRule(venueId: Long, ruleId: Long) {
        viewModelScope.launch {
            val result = repository.deleteRule(token, venueId, ruleId)
            if (result.isSuccess) {
                _uiState.update { it.copy(successMessage = "Pricing rule deleted.") }
                loadRules(venueId)
                _uiState.value.selectedCourtId?.let { loadMatrix(venueId, it) }
            } else {
                _uiState.update { it.copy(errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to delete rule") }
            }
        }
    }

    fun splitCourt(venueId: Long, req: SplitCourtRequest) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.splitCourt(token, venueId, req)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        showSplitCourtDialog = false,
                        successMessage = "Court partitioned successfully into ${req.partitions.size} sub-courts!"
                    )
                }
                loadHierarchy(venueId)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to split court"
                    )
                }
            }
        }
    }

    fun mergeCourts(venueId: Long, courtId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.mergeCourts(token, venueId, courtId)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        successMessage = "Sub-courts merged back into parent arena!"
                    )
                }
                loadHierarchy(venueId)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to merge courts"
                    )
                }
            }
        }
    }

    fun applyRecommendation(venueId: Long, rec: YieldRecommendationItem) {
        val payload = rec.rulePayload ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true) }
            val result = repository.applyRecommendation(token, venueId, payload)
            if (result.isSuccess) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        successMessage = "Yield opportunity applied! '${payload.name}' rule active."
                    )
                }
                loadAll(venueId, _uiState.value.selectedCourtId)
            } else {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        errorMessage = result.exceptionOrNull()?.localizedMessage ?: "Failed to apply recommendation"
                    )
                }
            }
        }
    }

    fun syncOffline(venueId: Long) {
        viewModelScope.launch {
            val count = repository.syncOfflineActions(token)
            updateOfflineCount()
            if (count > 0) {
                _uiState.update { it.copy(successMessage = "Synced $count offline pricing updates!") }
                loadAll(venueId, _uiState.value.selectedCourtId)
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
