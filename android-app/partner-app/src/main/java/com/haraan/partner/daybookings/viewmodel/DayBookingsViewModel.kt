package com.haraan.partner.daybookings.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.haraan.partner.DayGrid
import com.haraan.partner.DeskPayment
import com.haraan.partner.PayMethod
import com.haraan.partner.daybookings.data.DayBookingsRepository
import com.haraan.partner.daybookings.data.Resource
import com.haraan.partner.daybookings.model.BookingFilter
import com.haraan.partner.daybookings.model.ChannelFilter
import com.haraan.partner.daybookings.model.DayBookingItem
import com.haraan.partner.daybookings.model.DaySummaryStats
import com.haraan.partner.daybookings.model.StatusFilter
import com.haraan.partner.daybookings.model.ViewMode
import com.haraan.partner.daybookings.model.WalkInTarget
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

data class DayBookingsUiState(
    val selectedDate: String,
    val selectedDateMillis: Long,
    val venueId: Long,
    val venueName: String = "Venue",
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val isOffline: Boolean = false,
    val pendingOfflineActionsCount: Int = 0,
    val isSyncingQueue: Boolean = false,
    val lastSyncMessage: String? = null,
    val viewMode: ViewMode = ViewMode.GRID,
    val filter: BookingFilter = BookingFilter(),
    val stats: DaySummaryStats = DaySummaryStats(date = ""),
    val grid: DayGrid? = null,
    val bookings: List<DayBookingItem> = emptyList(),
    val filteredBookings: List<DayBookingItem> = emptyList(),
    val selectedBookingForDetails: DayBookingItem? = null,
    val walkInTarget: WalkInTarget? = null,
    val isSubmittingWalkIn: Boolean = false,
    /** A blocked grid cell the desk tapped: why it's blocked, and Unblock. */
    val blockTarget: BlockTarget? = null,
    val errorMessage: String? = null,
    val successSnackbarMessage: String? = null,
    /**
     * True when the success above is money taken (a walk-in booked), so the screen
     * plays the firm money buzz instead of the plain confirm.
     */
    val successIsMoney: Boolean = false,
    /** A walk-in paying online right now: the QR/link sheet is open while this is set. */
    val deskPay: DeskPayState? = null,
)

/** A blocked court-hour opened from the grid. */
data class BlockTarget(val block: com.haraan.partner.CourtBlock, val courtName: String, val slotTime: String)

/** Where a walk-in's online payment stands while the desk watches it. */
enum class DeskPayPhase { WAITING, PAID, EXPIRED, FAILED }

data class DeskPayState(
    val bookingId: Long,
    val customer: String,
    val amount: Double,
    val payment: DeskPayment?,
    val secondsLeft: Int,
    val totalSeconds: Int,
    val phase: DeskPayPhase,
    /** A button's request is in flight — the sheet disables its actions. */
    val busy: Boolean = false,
    /** Checking with Razorpay right now (drives the live pulse). */
    val checking: Boolean = false,
    /** How it was settled once PAID: "online", "cash", "upi", "card". */
    val paidVia: String? = null,
    val message: String? = null,
    /**
     * Razorpay's payment page is on screen (in place of the sheet). It is a sibling of
     * the sheet, never a window inside it: a dialog opened from a bottom sheet makes the
     * sheet dismiss itself, which closed the payment the customer was about to make.
     */
    val pageOpen: Boolean = false,
)

class DayBookingsViewModel(
    private val repository: DayBookingsRepository,
    private val token: String,
    private val initialVenueId: Long,
    private val initialVenueName: String = "Venue",
) : ViewModel() {

    private val dateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    private var dataLoadJob: Job? = null

    private val _uiState = MutableStateFlow(
        run {
            val now = System.currentTimeMillis()
            val cal = Calendar.getInstance().apply { timeInMillis = now }
            val dateStr = dateFormat.format(cal.time)
            DayBookingsUiState(
                selectedDate = dateStr,
                selectedDateMillis = cal.timeInMillis,
                venueId = initialVenueId,
                venueName = initialVenueName,
                stats = DaySummaryStats(date = dateStr),
            )
        }
    )
    val uiState: StateFlow<DayBookingsUiState> = _uiState.asStateFlow()

    init {
        loadData(forceRefresh = false)
        checkPendingActions()
    }

    fun updateVenue(venueId: Long, venueName: String) {
        if (_uiState.value.venueId != venueId) {
            _uiState.update { it.copy(venueId = venueId, venueName = venueName) }
            loadData(forceRefresh = true)
        }
    }

    fun selectDate(millis: Long) {
        val cal = Calendar.getInstance().apply { timeInMillis = millis }
        val dateStr = dateFormat.format(cal.time)
        _uiState.update { it.copy(selectedDate = dateStr, selectedDateMillis = cal.timeInMillis) }
        loadData(forceRefresh = false)
    }

    fun nextDay() {
        val cal = Calendar.getInstance().apply {
            timeInMillis = _uiState.value.selectedDateMillis
            add(Calendar.DAY_OF_YEAR, 1)
        }
        selectDate(cal.timeInMillis)
    }

    fun previousDay() {
        val cal = Calendar.getInstance().apply {
            timeInMillis = _uiState.value.selectedDateMillis
            add(Calendar.DAY_OF_YEAR, -1)
        }
        selectDate(cal.timeInMillis)
    }

    fun jumpToToday() {
        selectDate(System.currentTimeMillis())
    }

    fun setViewMode(mode: ViewMode) {
        _uiState.update { it.copy(viewMode = mode) }
    }

    fun updateSearchQuery(query: String) {
        _uiState.update { state ->
            val updatedFilter = state.filter.copy(searchQuery = query)
            state.copy(
                filter = updatedFilter,
                filteredBookings = applyFilters(state.bookings, updatedFilter),
            )
        }
    }

    fun updateStatusFilter(status: StatusFilter) {
        _uiState.update { state ->
            val updatedFilter = state.filter.copy(statusFilter = status)
            state.copy(
                filter = updatedFilter,
                filteredBookings = applyFilters(state.bookings, updatedFilter),
            )
        }
    }

    fun updateCourtFilter(courtId: Long?) {
        _uiState.update { state ->
            val updatedFilter = state.filter.copy(courtIdFilter = courtId)
            state.copy(
                filter = updatedFilter,
                filteredBookings = applyFilters(state.bookings, updatedFilter),
            )
        }
    }

    fun loadData(forceRefresh: Boolean = false) {
        dataLoadJob?.cancel()
        dataLoadJob = viewModelScope.launch {
            val state = _uiState.value
            _uiState.update { it.copy(isLoading = !forceRefresh, isRefreshing = forceRefresh) }

            // 1. Collect Grid Data
            launch {
                repository.getDayGrid(token, state.venueId, state.selectedDate, forceRefresh).collect { resource ->
                    when (resource) {
                        is Resource.Loading -> {
                            resource.partialData?.let { cachedGrid ->
                                _uiState.update {
                                    val newStats = repository.calculateStats(cachedGrid, it.bookings, state.selectedDate)
                                    it.copy(grid = cachedGrid, stats = newStats)
                                }
                            }
                        }
                        is Resource.Success -> {
                            _uiState.update {
                                val newStats = repository.calculateStats(resource.data, it.bookings, state.selectedDate)
                                it.copy(
                                    grid = resource.data,
                                    stats = newStats,
                                    isOffline = resource.isOffline,
                                    isLoading = false,
                                    isRefreshing = false,
                                )
                            }
                        }
                        is Resource.Error -> {
                            _uiState.update {
                                it.copy(
                                    errorMessage = resource.message,
                                    isLoading = false,
                                    isRefreshing = false,
                                )
                            }
                        }
                    }
                }
            }

            // 2. Collect Bookings Data
            launch {
                repository.getDayBookings(token, state.venueId, state.selectedDate, forceRefresh).collect { resource ->
                    when (resource) {
                        is Resource.Loading -> {
                            resource.partialData?.let { cachedList ->
                                _uiState.update {
                                    val filtered = applyFilters(cachedList, it.filter)
                                    val newStats = repository.calculateStats(it.grid, cachedList, state.selectedDate)
                                    it.copy(bookings = cachedList, filteredBookings = filtered, stats = newStats)
                                }
                            }
                        }
                        is Resource.Success -> {
                            _uiState.update {
                                val filtered = applyFilters(resource.data, it.filter)
                                val newStats = repository.calculateStats(it.grid, resource.data, state.selectedDate)
                                it.copy(
                                    bookings = resource.data,
                                    filteredBookings = filtered,
                                    stats = newStats,
                                    isOffline = resource.isOffline,
                                    isLoading = false,
                                    isRefreshing = false,
                                )
                            }
                        }
                        is Resource.Error -> {
                            _uiState.update {
                                it.copy(
                                    errorMessage = resource.message,
                                    isLoading = false,
                                    isRefreshing = false,
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    fun openWalkInModal(slotId: Long, slotTime: String, courtId: Long? = null, courtName: String? = null, basePrice: Double = 0.0) {
        _uiState.update {
            it.copy(
                walkInTarget = WalkInTarget(
                    slotId = slotId,
                    slotTime = slotTime,
                    courtId = courtId,
                    courtName = courtName,
                    basePrice = basePrice,
                )
            )
        }
    }

    fun closeWalkInModal() {
        _uiState.update { it.copy(walkInTarget = null, isSubmittingWalkIn = false) }
    }

    fun submitWalkIn(
        slotId: Long,
        courtId: Long?,
        date: String,
        name: String,
        phone: String,
        method: PayMethod,
        customerPackageId: Long? = null,
    ) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingWalkIn = true) }
            val venueId = _uiState.value.venueId
            val result = repository.createWalkIn(
                token = token,
                venueId = venueId,
                slotId = slotId,
                courtId = courtId,
                date = date,
                guestName = name,
                guestPhone = phone,
                method = method,
                customerPackageId = customerPackageId,
            )

            result.fold(
                onSuccess = { walkInResult ->
                    if ((method == PayMethod.UPI_QR || method == PayMethod.LINK) && walkInResult.bookingId > 0) {
                        // Booked, but not paid: hand over to the QR sheet, which says
                        // "paid" only when Razorpay does.
                        _uiState.update { it.copy(walkInTarget = null, isSubmittingWalkIn = false) }
                        startDeskPay(walkInResult.bookingId, name, walkInResult.amount, walkInResult.payment)
                        loadData(forceRefresh = true)
                        return@fold
                    }
                    _uiState.update {
                        it.copy(
                            walkInTarget = null,
                            isSubmittingWalkIn = false,
                            successSnackbarMessage = "Walk-in booked successfully for $name",
                            successIsMoney = true,
                        )
                    }
                    loadData(forceRefresh = true)
                    checkPendingActions()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSubmittingWalkIn = false,
                            errorMessage = error.message ?: "Failed to record walk-in",
                        )
                    }
                }
            )
        }
    }

    /** Take a court off sale from the walk-in sheet (Block mode). */
    fun blockCourt(courtId: Long, date: String, start: String, end: String, kind: String, note: String?, summary: String) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingWalkIn = true) }
            repository.blockCourt(token, _uiState.value.venueId, courtId, date, start, end, kind, note).fold(
                onSuccess = {
                    _uiState.update {
                        it.copy(walkInTarget = null, isSubmittingWalkIn = false, successSnackbarMessage = summary, successIsMoney = false)
                    }
                    loadData(forceRefresh = true)
                },
                onFailure = { e ->
                    _uiState.update { it.copy(isSubmittingWalkIn = false, errorMessage = e.message ?: "Couldn't block the court") }
                },
            )
        }
    }

    /** The court-block a tapped grid cell is showing; drives the unblock sheet. */
    fun openBlock(target: BlockTarget?) {
        _uiState.update { it.copy(blockTarget = target) }
    }

    fun unblockCourt(target: BlockTarget) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSubmittingWalkIn = true) }
            repository.unblockCourt(token, _uiState.value.venueId, target.block.id, _uiState.value.selectedDate).fold(
                onSuccess = {
                    _uiState.update {
                        it.copy(blockTarget = null, isSubmittingWalkIn = false, successSnackbarMessage = "${target.courtName} is open again", successIsMoney = false)
                    }
                    loadData(forceRefresh = true)
                },
                onFailure = { e ->
                    _uiState.update { it.copy(isSubmittingWalkIn = false, errorMessage = e.message ?: "Couldn't unblock the court") }
                },
            )
        }
    }

    // ---- Online payment at the desk (UPI QR / payment link) ---------------------

    private var deskPayJob: Job? = null

    private fun startDeskPay(bookingId: Long, customer: String, amount: Double, payment: DeskPayment?) {
        val usable = payment?.isUsable == true
        val total = (payment?.expiresInSeconds ?: 300).coerceAtLeast(30)
        _uiState.update {
            it.copy(
                deskPay = DeskPayState(
                    bookingId = bookingId,
                    customer = customer,
                    amount = payment?.amount?.takeIf { a -> a > 0 } ?: amount,
                    payment = payment,
                    secondsLeft = total,
                    totalSeconds = total,
                    phase = if (usable) DeskPayPhase.WAITING else DeskPayPhase.FAILED,
                    // Razorpay's page or checkout opens by itself; closing it shows the sheet.
                    pageOpen = usable && (payment?.isPage == true || payment?.isCheckout == true),
                    message = if (usable) null else payment?.error ?: "Couldn't start an online payment. Take cash or try again.",
                )
            )
        }
        if (usable) watchDeskPay()
    }

    /** One-second countdown; asks Razorpay every third second; closes the QR at zero. */
    private fun watchDeskPay() {
        deskPayJob?.cancel()
        deskPayJob = viewModelScope.launch {
            var tick = 0
            while (true) {
                kotlinx.coroutines.delay(1_000)
                val s = _uiState.value.deskPay ?: return@launch
                if (s.phase != DeskPayPhase.WAITING) return@launch
                val left = (s.secondsLeft - 1).coerceAtLeast(0)
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(secondsLeft = left)) }
                tick++
                val expired = left == 0
                if (tick % 3 != 0 && !expired) continue

                val payment = s.payment ?: return@launch
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(checking = true)) }
                // At zero a QR (or the page link nobody was texted) is closed, and checked one last time.
                val res = repository.deskPaymentStatus(token, s.bookingId, payment, close = expired && (payment.isQr || payment.isPage || payment.isCheckout))
                val paid = res.getOrNull()?.paid == true
                _uiState.update { st ->
                    val cur = st.deskPay ?: return@update st
                    st.copy(
                        deskPay = when {
                            paid -> cur.copy(phase = DeskPayPhase.PAID, paidVia = "online", checking = false)
                            expired -> cur.copy(phase = DeskPayPhase.EXPIRED, checking = false)
                            else -> cur.copy(checking = false)
                        }
                    )
                }
                if (paid) {
                    loadData(forceRefresh = true)
                    return@launch
                }
                if (expired) return@launch
            }
        }
    }

    /** Open Razorpay's checkout again (the customer closed it, or it hasn't opened yet). */
    fun deskPayOpenCheckout() {
        _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(pageOpen = true, message = null)) }
    }

    /**
     * What Razorpay's checkout said. "Paid" is the customer's phone talking, so it is
     * only a cue to ask the server, which asks Razorpay and settles the booking; the
     * timer's own checks carry on either way.
     */
    fun deskPayCheckoutResult(outcome: com.haraan.partner.daybookings.ui.DeskCheckoutBridge.Outcome) {
        val s = _uiState.value.deskPay ?: return
        val payment = s.payment ?: return
        when (outcome) {
            is com.haraan.partner.daybookings.ui.DeskCheckoutBridge.Outcome.Paid -> viewModelScope.launch {
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(pageOpen = false, checking = true, message = null)) }
                // Capture can trail the checkout by a few seconds.
                repeat(5) { attempt ->
                    val paid = repository.deskPaymentStatus(token, s.bookingId, payment).getOrNull()?.paid == true
                    if (paid) {
                        deskPayJob?.cancel()
                        _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(phase = DeskPayPhase.PAID, paidVia = "online", checking = false)) }
                        loadData(forceRefresh = true)
                        return@launch
                    }
                    kotlinx.coroutines.delay(1_500L * (attempt + 1))
                }
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(checking = false, message = "Paid on Razorpay. Waiting for it to confirm…")) }
            }
            com.haraan.partner.daybookings.ui.DeskCheckoutBridge.Outcome.Closed ->
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(pageOpen = false, message = "Payment closed. Open it again, or take cash.")) }
            is com.haraan.partner.daybookings.ui.DeskCheckoutBridge.Outcome.Failed ->
                _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(pageOpen = false, message = outcome.message)) }
        }
    }

    /** Show / hide Razorpay's page. Hiding it goes back to the sheet; nothing is cancelled. */
    fun deskPayShowPage(show: Boolean) {
        _uiState.update { st -> st.copy(deskPay = st.deskPay?.copy(pageOpen = show)) }
    }

    /** A fresh QR (or link) for the same booking; the old one is closed first. */
    fun deskPayRetry(kind: String) {
        val s = _uiState.value.deskPay ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(deskPay = s.copy(busy = true, message = null)) }
            repository.deskPaymentRequest(token, s.bookingId, kind, s.payment).fold(
                onSuccess = { r ->
                    if (r.paid) {
                        _uiState.update { it.copy(deskPay = s.copy(busy = false, phase = DeskPayPhase.PAID, paidVia = "online")) }
                        loadData(forceRefresh = true)
                    } else {
                        startDeskPay(s.bookingId, s.customer, s.amount, r.payment)
                    }
                },
                onFailure = { e ->
                    _uiState.update { it.copy(deskPay = s.copy(busy = false, message = e.message ?: "Couldn't make a new QR")) }
                },
            )
        }
    }

    /** The customer paid at the counter after all. The server closes the QR first. */
    fun deskPayCollect(method: PayMethod) {
        val s = _uiState.value.deskPay ?: return
        deskPayJob?.cancel()
        viewModelScope.launch {
            _uiState.update { it.copy(deskPay = s.copy(busy = true, message = null)) }
            repository.collectAtDesk(token, s.bookingId, method, s.payment).fold(
                onSuccess = { via ->
                    _uiState.update { it.copy(deskPay = s.copy(busy = false, phase = DeskPayPhase.PAID, paidVia = via)) }
                    loadData(forceRefresh = true)
                    checkPendingActions()
                },
                onFailure = { e ->
                    _uiState.update { it.copy(deskPay = s.copy(busy = false, message = e.message ?: "Couldn't record the payment")) }
                    if (s.phase == DeskPayPhase.WAITING) watchDeskPay()
                },
            )
        }
    }

    /** Drop the booking — the customer walked away. Closes the QR so it can't be paid. */
    fun deskPayCancelBooking() {
        val s = _uiState.value.deskPay ?: return
        deskPayJob?.cancel()
        viewModelScope.launch {
            _uiState.update { it.copy(deskPay = s.copy(busy = true, message = null)) }
            // Closing also checks once more, so a scan that just landed is not cancelled.
            val paidMeanwhile = s.payment?.takeIf { it.id != null }
                ?.let { repository.deskPaymentStatus(token, s.bookingId, it, close = true).getOrNull()?.paid } == true
            if (paidMeanwhile) {
                _uiState.update { it.copy(deskPay = s.copy(busy = false, phase = DeskPayPhase.PAID, paidVia = "online")) }
                loadData(forceRefresh = true)
                return@launch
            }
            val state = _uiState.value
            repository.cancelBooking(token, s.bookingId, state.venueId, state.selectedDate)
            _uiState.update { it.copy(deskPay = null, successSnackbarMessage = "Booking for ${s.customer} cancelled") }
            loadData(forceRefresh = true)
            checkPendingActions()
        }
    }

    /**
     * Close the sheet. A QR still on screen is closed on the server — nobody is watching
     * it any more, so a scan after this would be money with no booking settled. A link
     * stays valid: it was texted to the customer to pay from wherever they are.
     */
    fun deskPayDismiss() {
        val s = _uiState.value.deskPay ?: return
        // Not paid: the booking goes, and the court with it. A walk-in is paid at the desk
        // or online — never left booked and owing. (The server's hold would lapse by
        // itself anyway; this frees the court now.)
        if (s.phase != DeskPayPhase.PAID) {
            deskPayCancelBooking()
            return
        }
        deskPayJob?.cancel()
        _uiState.update {
            it.copy(
                deskPay = null,
                successSnackbarMessage = if (s.phase == DeskPayPhase.PAID) "₹${s.amount.toInt()} received from ${s.customer}" else null,
                successIsMoney = s.phase == DeskPayPhase.PAID,
            )
        }
        val p = s.payment
        if (s.phase == DeskPayPhase.WAITING && p != null && (p.isQr || p.isPage || p.isCheckout) && p.id != null) {
            viewModelScope.launch {
                val paid = repository.deskPaymentStatus(token, s.bookingId, p, close = true).getOrNull()?.paid == true
                if (paid) _uiState.update { it.copy(successSnackbarMessage = "₹${s.amount.toInt()} received from ${s.customer}", successIsMoney = true) }
                loadData(forceRefresh = true)
            }
        }
    }

    fun openBookingDetails(booking: DayBookingItem) {
        _uiState.update { it.copy(selectedBookingForDetails = booking) }
    }

    fun closeBookingDetails() {
        _uiState.update { it.copy(selectedBookingForDetails = null) }
    }

    fun cancelBooking(bookingId: Long) {
        viewModelScope.launch {
            val state = _uiState.value
            val result = repository.cancelBooking(token, bookingId, state.venueId, state.selectedDate)
            result.fold(
                onSuccess = {
                    _uiState.update {
                        it.copy(
                            selectedBookingForDetails = null,
                            successSnackbarMessage = "Booking #$bookingId cancelled",
                        )
                    }
                    loadData(forceRefresh = true)
                    checkPendingActions()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(errorMessage = error.message ?: "Failed to cancel booking")
                    }
                }
            )
        }
    }

    fun checkInBooking(ticketCode: String) {
        viewModelScope.launch {
            val state = _uiState.value
            val result = repository.checkInTicket(token, ticketCode, state.venueId, state.selectedDate)
            result.fold(
                onSuccess = { res ->
                    // A cancelled ticket is an answer, not a transport failure, so it
                    // arrives here — but it is still a "no" at the desk.
                    if (res.status == "invalid") {
                        _uiState.update { it.copy(errorMessage = res.message) }
                        return@fold
                    }
                    _uiState.update {
                        it.copy(
                            selectedBookingForDetails = null,
                            successSnackbarMessage = res.message.ifBlank { "Check-in successful" },
                        )
                    }
                    loadData(forceRefresh = true)
                    checkPendingActions()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(errorMessage = error.message ?: "Check-in failed")
                    }
                }
            )
        }
    }

    fun toggleDayClosed(closed: Boolean) {
        viewModelScope.launch {
            val state = _uiState.value
            val result = repository.setDateClosed(token, state.venueId, state.selectedDate, closed)
            result.fold(
                onSuccess = {
                    val msg = if (closed) "Day marked as closed" else "Day reopened"
                    _uiState.update { it.copy(successSnackbarMessage = msg) }
                    loadData(forceRefresh = true)
                    checkPendingActions()
                },
                onFailure = { error ->
                    _uiState.update { it.copy(errorMessage = error.message ?: "Failed to update day status") }
                }
            )
        }
    }

    fun triggerOfflineSync() {
        viewModelScope.launch {
            _uiState.update { it.copy(isSyncingQueue = true) }
            val syncResult = repository.syncOfflineQueue(token)
            _uiState.update {
                it.copy(
                    isSyncingQueue = false,
                    lastSyncMessage = "Synced ${syncResult.successful} of ${syncResult.totalProcessed} offline actions",
                    pendingOfflineActionsCount = syncResult.failed,
                )
            }
            loadData(forceRefresh = true)
        }
    }

    fun clearError() {
        _uiState.update { it.copy(errorMessage = null) }
    }

    fun clearSuccessMessage() {
        _uiState.update { it.copy(successSnackbarMessage = null, successIsMoney = false) }
    }

    private fun checkPendingActions() {
        viewModelScope.launch {
            val count = repository.getPendingActionCount()
            _uiState.update { it.copy(pendingOfflineActionsCount = count) }
        }
    }

    private fun applyFilters(list: List<DayBookingItem>, filter: BookingFilter): List<DayBookingItem> {
        return list.filter { item ->
            // Search Query Filter
            val matchesQuery = filter.searchQuery.isBlank() ||
                item.customerName.contains(filter.searchQuery, ignoreCase = true) ||
                item.ticketCode.contains(filter.searchQuery, ignoreCase = true) ||
                (item.phone?.contains(filter.searchQuery) == true)

            // Status Filter
            val matchesStatus = when (filter.statusFilter) {
                StatusFilter.ALL -> true
                StatusFilter.PAID -> item.paymentStatus.equals("paid", ignoreCase = true) && !item.isCancelled
                StatusFilter.UNPAID -> item.isDue
                StatusFilter.CHECKED_IN -> item.isCheckedIn
                StatusFilter.WALK_IN -> item.isWalkIn && !item.isCancelled
                StatusFilter.CANCELLED -> item.isCancelled
            }

            // Court Filter
            val matchesCourt = filter.courtIdFilter == null || item.courtId == filter.courtIdFilter

            // Channel Filter
            val matchesChannel = when (filter.channelFilter) {
                ChannelFilter.ALL -> true
                ChannelFilter.ONLINE -> !item.isWalkIn
                ChannelFilter.OFFLINE -> item.isWalkIn
            }

            matchesQuery && matchesStatus && matchesCourt && matchesChannel
        }
    }
}

class DayBookingsViewModelFactory(
    private val repository: DayBookingsRepository,
    private val token: String,
    private val venueId: Long,
    private val venueName: String,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(DayBookingsViewModel::class.java)) {
            return DayBookingsViewModel(repository, token, venueId, venueName) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class: ${modelClass.name}")
    }
}
