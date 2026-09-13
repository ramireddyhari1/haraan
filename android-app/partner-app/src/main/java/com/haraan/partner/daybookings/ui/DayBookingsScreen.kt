package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ViewList
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.Clear
import androidx.compose.material.icons.filled.GridView
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.partner.PartnerApi
import com.haraan.partner.daybookings.data.DayBookingsLocalDataSource
import com.haraan.partner.daybookings.data.DayBookingsRemoteDataSource
import com.haraan.partner.daybookings.data.DayBookingsRepositoryImpl
import com.haraan.partner.daybookings.model.DayBookingItem
import com.haraan.partner.daybookings.model.StatusFilter
import com.haraan.partner.daybookings.model.ViewMode
import com.haraan.partner.daybookings.viewmodel.DayBookingsViewModel
import com.haraan.partner.daybookings.viewmodel.DayBookingsViewModelFactory

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val PageBackground = Color(0xFFF6F7FB)
private val CardBorder = Color(0xFFE5E7EB)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DayBookingsScreen(
    api: PartnerApi,
    token: String,
    venueId: Long,
    venueName: String,
    onBack: () -> Unit,
    canPricing: Boolean = true,
    canBookings: Boolean = true,
    onPricing: () -> Unit = {},
    onAnalytics: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val repository = remember(context, api) {
        DayBookingsRepositoryImpl(
            localDataSource = DayBookingsLocalDataSource(context),
            remoteDataSource = DayBookingsRemoteDataSource(api)
        )
    }

    val factory = remember(repository, token, venueId, venueName) {
        DayBookingsViewModelFactory(repository, token, venueId, venueName)
    }

    val viewModel: DayBookingsViewModel = viewModel(
        key = "DayBookingsViewModel_${venueId}",
        factory = factory,
    )

    val state by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(state.errorMessage) {
        state.errorMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearError()
        }
    }

    LaunchedEffect(state.successSnackbarMessage) {
        state.successSnackbarMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearSuccessMessage()
        }
    }

    LaunchedEffect(venueId, venueName) {
        viewModel.updateVenue(venueId, venueName)
    }

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = PageBackground,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White),
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = InkDark)
                    }
                },
                title = {
                    Column {
                        Text(
                            venueName,
                            fontSize = 17.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = InkDark,
                            maxLines = 1,
                        )
                        Text(
                            "Day Bookings & Desk",
                            fontSize = 11.5.sp,
                            color = MutedGray,
                        )
                    }
                },
                actions = {
                    // Switch view mode (Grid vs List)
                    IconButton(
                        onClick = {
                            viewModel.setViewMode(
                                if (state.viewMode == ViewMode.GRID) ViewMode.LIST else ViewMode.GRID
                            )
                        }
                    ) {
                        Icon(
                            if (state.viewMode == ViewMode.GRID) Icons.AutoMirrored.Filled.ViewList else Icons.Filled.GridView,
                            contentDescription = "Toggle View",
                            tint = PrimaryBlue,
                        )
                    }

                    if (state.pendingOfflineActionsCount > 0) {
                        IconButton(onClick = { viewModel.triggerOfflineSync() }) {
                            Icon(Icons.Filled.Sync, contentDescription = "Sync", tint = PrimaryBlue)
                        }
                    }

                    if (canPricing) {
                        IconButton(onClick = onPricing) {
                            Icon(Icons.Filled.Tune, contentDescription = "Pricing", tint = InkDark)
                        }
                    }

                    IconButton(onClick = onAnalytics) {
                        Icon(Icons.Filled.BarChart, contentDescription = "Analytics", tint = InkDark)
                    }
                }
            )
        },
        floatingActionButton = {
            if (canBookings && !state.stats.isBlocked) {
                ExtendedFloatingActionButton(
                    onClick = {
                        val firstAvailableSlot = state.grid?.slots?.firstOrNull { it.available > 0 }
                        val slotId = firstAvailableSlot?.slotId ?: 0L
                        val slotTime = firstAvailableSlot?.time ?: firstAvailableSlot?.label ?: "Slot"
                        val price = firstAvailableSlot?.price ?: 0.0
                        viewModel.openWalkInModal(slotId, slotTime, null, null, price)
                    },
                    icon = { Icon(Icons.Filled.Add, contentDescription = null, tint = Color.White) },
                    text = { Text("Walk-in", fontWeight = FontWeight.Bold, color = Color.White) },
                    containerColor = PrimaryBlue,
                    contentColor = Color.White,
                    shape = RoundedCornerShape(16.dp),
                )
            }
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(horizontal = 16.dp),
        ) {
            Spacer(Modifier.height(10.dp))

            // Offline Sync Banner
            OfflineSyncBanner(
                isOffline = state.isOffline,
                pendingActionsCount = state.pendingOfflineActionsCount,
                isSyncing = state.isSyncingQueue,
                onSyncNow = { viewModel.triggerOfflineSync() },
            )

            Spacer(Modifier.height(10.dp))

            // Calendar Strip
            DayCalendarStrip(
                selectedMillis = state.selectedDateMillis,
                onSelectDay = { viewModel.selectDate(it) },
                onPrevDay = { viewModel.previousDay() },
                onNextDay = { viewModel.nextDay() },
                onToday = { viewModel.jumpToToday() },
            )

            Spacer(Modifier.height(10.dp))

            // Stats Header
            DayStatsHeader(
                stats = state.stats,
                canManage = canBookings,
                onReopenDay = { viewModel.toggleDayClosed(false) },
                onCloseDay = { viewModel.toggleDayClosed(true) },
            )

            Spacer(Modifier.height(10.dp))

            // Search Bar & Status Filter Chips
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                OutlinedTextField(
                    value = state.filter.searchQuery,
                    onValueChange = { viewModel.updateSearchQuery(it) },
                    placeholder = { Text("Search by name, phone, ticket ID...", fontSize = 12.sp) },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null, tint = MutedGray, modifier = Modifier.size(16.dp)) },
                    trailingIcon = {
                        if (state.filter.searchQuery.isNotBlank()) {
                            IconButton(onClick = { viewModel.updateSearchQuery("") }) {
                                Icon(Icons.Filled.Clear, contentDescription = "Clear", tint = MutedGray, modifier = Modifier.size(16.dp))
                            }
                        }
                    },
                    singleLine = true,
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White,
                        focusedBorderColor = PrimaryBlue,
                        unfocusedBorderColor = CardBorder,
                    ),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().height(46.dp),
                )
            }

            Spacer(Modifier.height(8.dp))

            // Status Filter Chips
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                items(StatusFilter.values()) { filterOption ->
                    val isSelected = state.filter.statusFilter == filterOption
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (isSelected) PrimaryBlue else Color.White)
                            .border(1.dp, if (isSelected) PrimaryBlue else CardBorder, RoundedCornerShape(8.dp))
                            .clickable { viewModel.updateStatusFilter(filterOption) }
                            .padding(horizontal = 10.dp, vertical = 5.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            filterOption.label,
                            fontSize = 11.5.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (isSelected) Color.White else InkDark,
                        )
                    }
                }
            }

            Spacer(Modifier.height(10.dp))

            // Main Content Area: Grid vs List View
            if (state.isLoading && state.grid == null && state.bookings.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) {
                    CircularProgressIndicator(color = PrimaryBlue)
                }
            } else {
                when (state.viewMode) {
                    ViewMode.GRID -> {
                        state.grid?.let { grid ->
                            DayBookingsGrid(
                                grid = grid,
                                canBook = canBookings && !state.stats.isBlocked,
                                onCellClick = { slotId, slotTime, courtId, courtName, price ->
                                    viewModel.openWalkInModal(slotId, slotTime, courtId, courtName, price)
                                },
                                onBookingClick = { dayBooking ->
                                    viewModel.openBookingDetails(
                                        DayBookingItem(
                                            id = dayBooking.id,
                                            ticketCode = "HRN-${dayBooking.id}",
                                            customerName = dayBooking.customer,
                                            phone = dayBooking.phone,
                                            channel = dayBooking.channel,
                                            status = dayBooking.status,
                                            checkedInCount = dayBooking.checkedIn,
                                            totalAmount = dayBooking.amount,
                                            amountPaid = dayBooking.amountPaid,
                                            balanceDue = (dayBooking.amount - dayBooking.amountPaid).coerceAtLeast(0.0),
                                            paymentStatus = dayBooking.paymentStatus,
                                            paymentMethod = null,
                                            slotDate = state.selectedDate,
                                            slotTime = "",
                                            courtId = null,
                                            courtName = null,
                                            branchName = venueName,
                                            isCancelled = dayBooking.status.equals("CANCELLED", ignoreCase = true),
                                            isCheckedIn = dayBooking.checkedIn > 0,
                                        )
                                    )
                                },
                            )
                        } ?: Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text("No schedule available for this day", color = MutedGray)
                        }
                    }

                    ViewMode.LIST -> {
                        DayBookingsList(
                            bookings = state.filteredBookings,
                            onBookingClick = { viewModel.openBookingDetails(it) },
                            onQuickCheckIn = { viewModel.checkInBooking(it.ticketCode) },
                        )
                    }
                }
            }
        }
    }

    // Walk-In Booking Sheet
    state.walkInTarget?.let { target ->
        WalkInBookingSheet(
            target = target,
            grid = state.grid,
            date = state.selectedDate,
            isSubmitting = state.isSubmittingWalkIn,
            onDismiss = { viewModel.closeWalkInModal() },
            onSubmit = { slotId, courtId, date, name, phone, method ->
                viewModel.submitWalkIn(slotId, courtId, date, name, phone, method)
            }
        )
    }

    // Booking Details Sheet
    state.selectedBookingForDetails?.let { booking ->
        BookingDetailsSheet(
            booking = booking,
            onDismiss = { viewModel.closeBookingDetails() },
            onCheckIn = { code -> viewModel.checkInBooking(code) },
            onCancel = { id -> viewModel.cancelBooking(id) },
        )
    }
}
