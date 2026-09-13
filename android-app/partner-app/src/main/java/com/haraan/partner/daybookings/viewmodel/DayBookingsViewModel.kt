package com.haraan.partner.daybookings.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.haraan.partner.DayGrid
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
    val errorMessage: String? = null,
    val successSnackbarMessage: String? = null,
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
                    _uiState.update {
                        it.copy(
                            walkInTarget = null,
                            isSubmittingWalkIn = false,
                            successSnackbarMessage = "Walk-in booked successfully for $name",
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
        _uiState.update { it.copy(successSnackbarMessage = null) }
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
