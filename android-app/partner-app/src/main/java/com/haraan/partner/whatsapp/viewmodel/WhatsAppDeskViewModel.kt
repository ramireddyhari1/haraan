package com.haraan.partner.whatsapp.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.partner.whatsapp.data.WhatsAppRepository
import com.haraan.partner.whatsapp.model.*
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class WhatsAppDeskUiState(
    val isLoading: Boolean = false,
    val metrics: WhatsAppMetrics = WhatsAppMetrics(),
    val selectedTab: String = "all", // all, needs_action, holds, converted, archived
    val searchQuery: String = "",
    val conversations: List<ConversationSummary> = emptyList(),
    val selectedConversation: ConversationSummary? = null,
    val messages: List<ChatMessage> = emptyList(),
    val bookingSuggestion: BookingSuggestion? = null,
    val quickReplies: List<QuickReplyItem> = emptyList(),
    val activeHoldTimerSeconds: Int = 0, // Centrally configurable (default 5-minute hold / 300s)
    val actionFeedbackMessage: String? = null,
    val isActionLoading: Boolean = false
)

class WhatsAppDeskViewModel(
    private val repository: WhatsAppRepository,
    private val token: String
) : ViewModel() {

    private val _uiState = MutableStateFlow(WhatsAppDeskUiState())
    val uiState: StateFlow<WhatsAppDeskUiState> = _uiState.asStateFlow()

    private var countdownJob: Job? = null

    fun loadDashboard(venueId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }
            try {
                val metrics = repository.getDashboardMetrics(token, venueId)
                val convs = repository.getConversations(token, venueId, _uiState.value.selectedTab, _uiState.value.searchQuery)
                val replies = repository.getQuickReplies(token, venueId)
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        metrics = metrics,
                        conversations = convs,
                        quickReplies = replies
                    )
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    fun selectTab(venueId: Long, tab: String) {
        _uiState.update { it.copy(selectedTab = tab) }
        loadConversationsOnly(venueId)
    }

    fun onSearchQueryChanged(venueId: Long, query: String) {
        _uiState.update { it.copy(searchQuery = query) }
        loadConversationsOnly(venueId)
    }

    private fun loadConversationsOnly(venueId: Long) {
        viewModelScope.launch {
            try {
                val convs = repository.getConversations(token, venueId, _uiState.value.selectedTab, _uiState.value.searchQuery)
                _uiState.update { it.copy(conversations = convs) }
            } catch (e: Exception) {
                // Keep existing cached
            }
        }
    }

    fun selectConversation(venueId: Long, conv: ConversationSummary?) {
        countdownJob?.cancel()
        if (conv == null) {
            _uiState.update {
                it.copy(
                    selectedConversation = null,
                    messages = emptyList(),
                    bookingSuggestion = null,
                    activeHoldTimerSeconds = 0
                )
            }
            return
        }

        _uiState.update { it.copy(selectedConversation = conv, isLoading = true) }
        viewModelScope.launch {
            try {
                val detail = repository.getConversationDetail(token, venueId, conv.id)
                val suggestion = detail.second

                _uiState.update {
                    it.copy(
                        isLoading = false,
                        messages = detail.first,
                        bookingSuggestion = suggestion,
                        activeHoldTimerSeconds = suggestion?.activeHoldSecondsRemaining ?: 0
                    )
                }

                if (suggestion != null && suggestion.activeHoldSecondsRemaining > 0) {
                    startHoldCountdown(suggestion.activeHoldSecondsRemaining)
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    fun sendMessage(venueId: Long, body: String, messageType: String = "text") {
        val conv = _uiState.value.selectedConversation ?: return
        if (body.isBlank()) return

        viewModelScope.launch {
            try {
                val msg = repository.sendMessage(token, venueId, conv.id, body, messageType)
                _uiState.update {
                    it.copy(messages = it.messages + msg)
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(actionFeedbackMessage = "Failed to send message") }
            }
        }
    }

    fun holdSlot(
        venueId: Long,
        courtId: Long,
        courtName: String,
        date: String,
        startTime: String,
        endTime: String,
        price: Double
    ) {
        val conv = _uiState.value.selectedConversation ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val res = repository.holdSlot(
                    token,
                    venueId,
                    conv.id,
                    HoldSlotRequest(courtId, date, startTime, endTime, price)
                )

                val seconds = res.third // strictly 120 seconds
                val updatedSuggestion = _uiState.value.bookingSuggestion?.copy(
                    courtId = courtId,
                    courtName = courtName,
                    date = date,
                    startTime = startTime,
                    endTime = endTime,
                    price = price,
                    activeHoldSecondsRemaining = seconds
                ) ?: BookingSuggestion(
                    conversationId = conv.id,
                    courtId = courtId,
                    courtName = courtName,
                    date = date,
                    startTime = startTime,
                    endTime = endTime,
                    price = price,
                    activeHoldSecondsRemaining = seconds
                )

                _uiState.update {
                    it.copy(
                        isActionLoading = false,
                        bookingSuggestion = updatedSuggestion,
                        activeHoldTimerSeconds = seconds,
                        actionFeedbackMessage = "⚡ ${com.haraan.partner.ui.theme.HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}-Minute Hold Active! (${seconds}s)"
                    )
                }

                startHoldCountdown(seconds)
                // Reload conversation detail to see system hold message
                val detail = repository.getConversationDetail(token, venueId, conv.id)
                _uiState.update { it.copy(messages = detail.first) }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(
                        isActionLoading = false,
                        actionFeedbackMessage = "Could not hold slot: ${e.message}"
                    )
                }
            }
        }
    }

    fun releaseHold(venueId: Long) {
        val conv = _uiState.value.selectedConversation ?: return
        countdownJob?.cancel()
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                repository.releaseHold(token, venueId, conv.id)
                _uiState.update {
                    it.copy(
                        isActionLoading = false,
                        activeHoldTimerSeconds = 0,
                        bookingSuggestion = it.bookingSuggestion?.copy(activeHoldSecondsRemaining = 0),
                        actionFeedbackMessage = "Hold released"
                    )
                }
                val detail = repository.getConversationDetail(token, venueId, conv.id)
                _uiState.update { it.copy(messages = detail.first) }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    fun sendPaymentLink(venueId: Long, amount: Double? = null) {
        val conv = _uiState.value.selectedConversation ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val ok = repository.sendPaymentLink(token, venueId, conv.id, amount)
                _uiState.update {
                    it.copy(
                        isActionLoading = false,
                        actionFeedbackMessage = if (ok) "💳 Payment link sent via WhatsApp!" else "Failed to send link"
                    )
                }
                val detail = repository.getConversationDetail(token, venueId, conv.id)
                _uiState.update { it.copy(messages = detail.first) }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    fun markPaidManual(venueId: Long, method: String = "cash") {
        val conv = _uiState.value.selectedConversation ?: return
        countdownJob?.cancel()
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                val ticketCode = repository.markPaidManual(token, venueId, conv.id, method)
                _uiState.update {
                    it.copy(
                        isActionLoading = false,
                        activeHoldTimerSeconds = 0,
                        bookingSuggestion = null,
                        actionFeedbackMessage = "🎉 Confirmed! Ticket #$ticketCode issued & drawer reconciled."
                    )
                }
                val detail = repository.getConversationDetail(token, venueId, conv.id)
                _uiState.update { it.copy(messages = detail.first) }
            } catch (e: Exception) {
                _uiState.update { it.copy(isActionLoading = false, actionFeedbackMessage = "Failed: ${e.message}") }
            }
        }
    }

    fun dismissSuggestion() {
        _uiState.update { it.copy(bookingSuggestion = null) }
    }

    fun clearFeedback() {
        _uiState.update { it.copy(actionFeedbackMessage = null) }
    }

    private fun startHoldCountdown(initialSeconds: Int) {
        countdownJob?.cancel()
        countdownJob = viewModelScope.launch {
            var current = initialSeconds
            while (current > 0) {
                delay(1000L)
                current--
                _uiState.update {
                    it.copy(
                        activeHoldTimerSeconds = current,
                        bookingSuggestion = it.bookingSuggestion?.copy(activeHoldSecondsRemaining = current)
                    )
                }
            }
            // Expired!
            _uiState.update {
                it.copy(
                    activeHoldTimerSeconds = 0,
                    bookingSuggestion = it.bookingSuggestion?.copy(activeHoldSecondsRemaining = 0),
                    actionFeedbackMessage = "⚠️ ${com.haraan.partner.ui.theme.HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}-Minute hold has expired."
                )
            }
        }
    }
}