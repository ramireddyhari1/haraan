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
    val loadError: String? = null,
    val metrics: WhatsAppMetrics = WhatsAppMetrics(),
    val selectedTab: String = "all", // all, needs_action, holds, converted
    val searchQuery: String = "",
    val conversations: List<ConversationSummary> = emptyList(),

    val selectedConversation: ConversationSummary? = null,
    val isThreadLoading: Boolean = false,
    val messages: List<ChatMessage> = emptyList(),
    val isWindowActive: Boolean = false,
    val suggestion: BookingSuggestion? = null,
    val hold: ActiveHold? = null,
    val holdSecondsLeft: Int = 0,
    val holdTotalSeconds: Int = 0,
    val quickReplies: List<QuickReplyItem> = emptyList(),

    val availability: DayAvailability? = null,
    val availabilityLoading: Boolean = false,
    val availabilityError: String? = null,

    val isSending: Boolean = false,
    val isActionLoading: Boolean = false,
    val actionFeedbackMessage: String? = null
)

class WhatsAppDeskViewModel(
    private val repository: WhatsAppRepository,
    private val token: String
) : ViewModel() {

    private val _uiState = MutableStateFlow(WhatsAppDeskUiState())
    val uiState: StateFlow<WhatsAppDeskUiState> = _uiState.asStateFlow()

    private var countdownJob: Job? = null
    private var searchJob: Job? = null

    // --- Inbox -------------------------------------------------------------------------

    fun loadDashboard(venueId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = it.conversations.isEmpty(), loadError = null) }
            val replies = repository.getQuickReplies(token, venueId)
            _uiState.update { it.copy(quickReplies = replies) }
            refreshInbox(venueId, reportErrors = true)
            _uiState.update { it.copy(isLoading = false) }
        }
    }

    /** Metrics + the current tab's list. Quiet by default: polling must not flash errors. */
    suspend fun refreshInbox(venueId: Long, reportErrors: Boolean = false) {
        repository.getDashboardMetrics(token, venueId)?.let { m -> _uiState.update { it.copy(metrics = m) } }
        try {
            val s = _uiState.value
            val convs = repository.getConversations(token, venueId, s.selectedTab, s.searchQuery)
            _uiState.update { it.copy(conversations = convs, loadError = null) }
        } catch (e: Exception) {
            if (reportErrors) {
                _uiState.update { it.copy(loadError = e.message ?: "Couldn’t load chats") }
            }
        }
    }

    fun selectTab(venueId: Long, tab: String) {
        _uiState.update { it.copy(selectedTab = tab) }
        viewModelScope.launch { refreshInbox(venueId, reportErrors = true) }
    }

    fun onSearchQueryChanged(venueId: Long, query: String) {
        _uiState.update { it.copy(searchQuery = query) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(300)
            refreshInbox(venueId, reportErrors = true)
        }
    }

    // --- One chat ------------------------------------------------------------------------

    fun selectConversation(venueId: Long, conv: ConversationSummary?) {
        countdownJob?.cancel()
        if (conv == null) {
            _uiState.update {
                it.copy(
                    selectedConversation = null, messages = emptyList(), suggestion = null, hold = null,
                    holdSecondsLeft = 0, availability = null, availabilityError = null
                )
            }
            viewModelScope.launch { refreshInbox(venueId) }
            return
        }

        _uiState.update {
            it.copy(
                selectedConversation = conv, isThreadLoading = true, messages = emptyList(),
                suggestion = null, hold = null, holdSecondsLeft = 0, isWindowActive = conv.isWindowActive
            )
        }
        viewModelScope.launch {
            try {
                applyDetail(repository.getConversationDetail(token, venueId, conv.id))
            } catch (e: Exception) {
                feedback(e.message ?: "Couldn’t open the chat")
            } finally {
                _uiState.update { it.copy(isThreadLoading = false) }
            }
        }
    }

    /**
     * Poll tick while a chat is open: new messages, and — while a payment link is out —
     * ask Razorpay (through the server) whether it's been paid.
     */
    suspend fun refreshOpenConversation(venueId: Long) {
        val conv = _uiState.value.selectedConversation ?: return

        if (_uiState.value.hold?.linkSent == true) {
            val state = runCatching { repository.paymentStatus(token, venueId, conv.id) }.getOrNull()
            if (state == "paid") feedback("Paid — the booking is confirmed and the customer has their code.")
        }

        runCatching { repository.getConversationDetail(token, venueId, conv.id) }
            .getOrNull()
            ?.takeIf { _uiState.value.selectedConversation?.id == conv.id }
            ?.let { applyDetail(it) }
    }

    private fun applyDetail(detail: ConversationDetail) {
        _uiState.update {
            it.copy(
                messages = detail.messages,
                isWindowActive = detail.isWindowActive,
                hold = detail.hold,
                suggestion = if (detail.hold != null) null else detail.suggestion,
                holdTotalSeconds = detail.holdTotalSeconds.takeIf { t -> t > 0 } ?: it.holdTotalSeconds
            )
        }
        startHoldCountdown(if (detail.hold != null) detail.holdSecondsLeft else 0)
    }

    fun sendMessage(venueId: Long, body: String, onSent: () -> Unit) {
        val conv = _uiState.value.selectedConversation ?: return
        if (body.isBlank() || _uiState.value.isSending) return

        viewModelScope.launch {
            _uiState.update { it.copy(isSending = true) }
            try {
                val msg = repository.sendMessage(token, venueId, conv.id, body.trim())
                _uiState.update { it.copy(messages = it.messages + msg) }
                onSent()
                if (msg.deliveryStatus == "failed") {
                    feedback("WhatsApp didn’t accept that message. Try again, or call the customer.")
                }
            } catch (e: Exception) {
                // The draft stays in the box: nothing was sent.
                feedback(e.message ?: "Couldn’t send — check your connection.")
            } finally {
                _uiState.update { it.copy(isSending = false) }
            }
        }
    }

    /** A quick reply with this chat's customer filled in. */
    fun render(reply: QuickReplyItem): String {
        val name = _uiState.value.selectedConversation?.customerName?.substringBefore(' ')?.takeIf { it.isNotBlank() } ?: "there"
        return reply.body.replace("{{customer_name}}", name)
    }

    // --- Holding a court -------------------------------------------------------------------

    fun loadAvailability(venueId: Long, date: String) {
        viewModelScope.launch {
            _uiState.update { it.copy(availabilityLoading = true, availabilityError = null) }
            try {
                val day = repository.getAvailability(token, venueId, date)
                _uiState.update { it.copy(availability = day) }
            } catch (e: Exception) {
                _uiState.update { it.copy(availabilityError = e.message ?: "Couldn’t load the courts") }
            } finally {
                _uiState.update { it.copy(availabilityLoading = false) }
            }
        }
    }

    fun holdSlot(venueId: Long, courtId: Long, date: String, startTime: String, endTime: String, onHeld: () -> Unit = {}) {
        val conv = _uiState.value.selectedConversation ?: return
        runAction {
            val res = repository.holdSlot(token, venueId, conv.id, courtId, date, startTime, endTime)
            _uiState.update {
                it.copy(
                    hold = res.hold,
                    suggestion = null,
                    holdTotalSeconds = res.holdTotalSeconds.takeIf { t -> t > 0 } ?: it.holdTotalSeconds
                )
            }
            startHoldCountdown(res.secondsRemaining)
            onHeld()
            feedback("Held · ${res.holdTotalSeconds / 60} min for the customer to pay")
            reloadThread(venueId)
        }
    }

    fun releaseHold(venueId: Long) {
        val conv = _uiState.value.selectedConversation ?: return
        runAction {
            val outcome = repository.releaseHold(token, venueId, conv.id)
            if (outcome == "paid") {
                feedback("The customer had already paid — the booking is confirmed.")
            } else {
                countdownJob?.cancel()
                _uiState.update { it.copy(hold = null, holdSecondsLeft = 0) }
                feedback("Hold released")
            }
            reloadThread(venueId)
        }
    }

    fun sendPaymentLink(venueId: Long) {
        val conv = _uiState.value.selectedConversation ?: return
        runAction {
            val seconds = repository.sendPaymentLink(token, venueId, conv.id)
            startHoldCountdown(seconds)
            feedback("Payment link sent on WhatsApp")
            reloadThread(venueId)
        }
    }

    fun markPaid(venueId: Long, method: String) {
        val conv = _uiState.value.selectedConversation ?: return
        runAction {
            val code = repository.markPaid(token, venueId, conv.id, method)
            countdownJob?.cancel()
            _uiState.update { it.copy(hold = null, holdSecondsLeft = 0) }
            feedback(if (code.isNotBlank()) "Paid and confirmed · code $code sent to the customer" else "Paid and confirmed")
            reloadThread(venueId)
        }
    }

    fun dismissSuggestion() {
        _uiState.update { it.copy(suggestion = null) }
    }

    fun clearFeedback() {
        _uiState.update { it.copy(actionFeedbackMessage = null) }
    }

    private suspend fun reloadThread(venueId: Long) {
        val conv = _uiState.value.selectedConversation ?: return
        runCatching { repository.getConversationDetail(token, venueId, conv.id) }.getOrNull()?.let { applyDetail(it) }
    }

    private fun runAction(block: suspend () -> Unit) {
        if (_uiState.value.isActionLoading) return
        viewModelScope.launch {
            _uiState.update { it.copy(isActionLoading = true) }
            try {
                block()
            } catch (e: Exception) {
                feedback(e.message ?: "Something went wrong — try again.")
            } finally {
                _uiState.update { it.copy(isActionLoading = false) }
            }
        }
    }

    private fun feedback(message: String) {
        _uiState.update { it.copy(actionFeedbackMessage = message) }
    }

    /** The server's clock, counted down locally; the server's sweep is what actually expires it. */
    private fun startHoldCountdown(initialSeconds: Int) {
        countdownJob?.cancel()
        _uiState.update { it.copy(holdSecondsLeft = initialSeconds.coerceAtLeast(0)) }
        if (initialSeconds <= 0) return

        countdownJob = viewModelScope.launch {
            var left = initialSeconds
            while (left > 0) {
                delay(1000L)
                left--
                _uiState.update { it.copy(holdSecondsLeft = left) }
            }
        }
    }
}
