package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.collectIsFocusedAsState
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.material3.ScaffoldDefaults
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
import androidx.compose.ui.platform.LocalView
import com.haraan.partner.ui.Haptics
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
    /**
     * Drawn inside the console's own Scaffold (the Bookings tab), which has already
     * taken the status bar. Claiming it again left a blank band above this header.
     */
    embedded: Boolean = false,
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

    // The buzz lands with the outcome, before the snackbar: the desk is usually
    // looking at the customer, and showSnackbar suspends until it is dismissed.
    val view = LocalView.current
    LaunchedEffect(state.errorMessage) {
        state.errorMessage?.let {
            Haptics.reject(view)
            snackbarHostState.showSnackbar(it)
            viewModel.clearError()
        }
    }

    LaunchedEffect(state.successSnackbarMessage) {
        state.successSnackbarMessage?.let {
            if (state.successIsMoney) Haptics.money(view) else Haptics.confirm(view)
            snackbarHostState.showSnackbar(it)
            viewModel.clearSuccessMessage()
        }
    }

    LaunchedEffect(venueId, venueName) {
        viewModel.updateVenue(venueId, venueName)
    }

    // Shared by the court header and every time row, so the courts scroll
    // sideways as one sheet.
    val gridScroll = rememberScrollState()

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = PageBackground,
        contentWindowInsets = if (embedded) WindowInsets(0, 0, 0, 0) else ScaffoldDefaults.contentWindowInsets,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                windowInsets = if (embedded) WindowInsets(0, 0, 0, 0) else TopAppBarDefaults.windowInsets,
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
                    // Who booked, as a list, now lives in Payments. The list here only
                    // opens from the "owed" line, so it needs a way back to the courts.
                    if (state.viewMode == ViewMode.LIST) {
                        IconButton(onClick = { viewModel.setViewMode(ViewMode.GRID) }) {
                            Icon(Icons.Filled.GridView, contentDescription = "Back to courts", tint = PrimaryBlue)
                        }
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
        // One scrolling page. The calendar, stats, search and filters used to be
        // pinned above the grid, which left the courts a sliver of the screen;
        // now they scroll away and the court names stick to the top.
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 96.dp),
        ) {
          item(key = "desk-head") {
          Column {
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
                // The owed line opens the list of who owes it.
                onShowDue = {
                    viewModel.setViewMode(ViewMode.LIST)
                    viewModel.updateStatusFilter(StatusFilter.UNPAID)
                },
            )

            Spacer(Modifier.height(10.dp))

            // Search Bar & Status Filter Chips
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                DeskSearchField(
                    query = state.filter.searchQuery,
                    onQuery = { viewModel.updateSearchQuery(it) },
                )
            }

            Spacer(Modifier.height(8.dp))

            // Status Filter Chips
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                items(StatusFilter.values().filter { it != StatusFilter.UNPAID }) { filterOption ->
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
          }
          }

            // Main Content Area: Grid vs List View
            if (state.isLoading && state.grid == null && state.bookings.isEmpty()) {
                item(key = "desk-loading") {
                    Box(
                        modifier = Modifier.fillMaxWidth().height(220.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        CircularProgressIndicator(color = PrimaryBlue)
                    }
                }
            } else {
                when (state.viewMode) {
                    ViewMode.GRID -> {
                        state.grid?.let { grid ->
                            dayBookingsGridItems(
                                grid = grid,
                                canBook = canBookings && !state.stats.isBlocked,
                                scrollState = gridScroll,
                                onCellClick = { slotId, slotTime, courtId, courtName, price ->
                                    viewModel.openWalkInModal(slotId, slotTime, courtId, courtName, price)
                                },
                                onBlockClick = { block, courtName, slotTime ->
                                    viewModel.openBlock(com.haraan.partner.daybookings.viewmodel.BlockTarget(block, courtName, slotTime))
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
                        } ?: item(key = "desk-no-grid") {
                            Box(modifier = Modifier.fillMaxWidth().padding(vertical = 44.dp), contentAlignment = Alignment.Center) {
                                Text("No schedule available for this day", color = MutedGray)
                            }
                        }
                    }

                    ViewMode.LIST -> {
                        dayBookingsListItems(
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
            },
            onBlock = if (canBookings) { req ->
                viewModel.blockCourt(req.courtId, req.date, req.start, req.end, req.kind, req.note, req.summary)
            } else null,
        )
    }

    // A blocked court-hour tapped on the grid: why, and Unblock when the desk made it.
    state.blockTarget?.let { target ->
        BlockDetailsSheet(
            target = target,
            canUnblock = canBookings && target.block.removable,
            busy = state.isSubmittingWalkIn,
            onDismiss = { viewModel.openBlock(null) },
            onUnblock = { viewModel.unblockCourt(target) },
        )
    }

    // So the first Razorpay page opens in a moment rather than freezing the desk.
    WarmUpWebEngine()

    // A walk-in paying by UPI QR / payment link
    state.deskPay?.let { pay ->
        val pageUrl = pay.payment?.url
        if (pay.pageOpen && pay.phase == com.haraan.partner.daybookings.viewmodel.DeskPayPhase.WAITING &&
            pay.payment?.isPage == true && pageUrl != null
        ) {
            // Razorpay's page stands in for the sheet; closing it brings the sheet back.
            RazorpayPaymentPage(url = pageUrl, state = pay, onClose = { viewModel.deskPayShowPage(false) })
        } else if (pay.pageOpen && pay.phase == com.haraan.partner.daybookings.viewmodel.DeskPayPhase.WAITING &&
            pay.payment?.checkout != null
        ) {
            // Razorpay's checkout, number prefilled, every UPI option the website shows.
            DeskCheckoutPage(
                checkout = pay.payment.checkout,
                secondsLeft = pay.secondsLeft,
                onResult = { outcome -> viewModel.deskPayCheckoutResult(outcome) },
            )
        } else {
            DeskPaymentSheet(
                state = pay,
                onRetry = { kind -> viewModel.deskPayRetry(kind) },
                onCollect = { method -> viewModel.deskPayCollect(method) },
                onCancelBooking = { viewModel.deskPayCancelBooking() },
                onDismiss = { viewModel.deskPayDismiss() },
                onOpenPage = {
                    if (pay.payment?.isCheckout == true) viewModel.deskPayOpenCheckout() else viewModel.deskPayShowPage(true)
                },
            )
        }
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

/**
 * The desk's search box.
 *
 * It was an OutlinedTextField forced to 46dp. Material's field needs 56dp for its
 * own padding, so the placeholder's lower half was sliced off. This one is a
 * BasicTextField in a 48dp pill, so the text is centred by layout, not by the
 * framework's padding guesses: soft fill, a hairline that turns blue on focus,
 * and a clear button only once there's something to clear.
 */
@Composable
private fun DeskSearchField(query: String, onQuery: (String) -> Unit) {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .height(48.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(if (focused) Color.White else Color(0xFFF4F6FA))
            .border(
                if (focused) 1.5.dp else 1.dp,
                if (focused) PrimaryBlue else Color(0xFFE6EAF0),
                RoundedCornerShape(14.dp),
            )
            .padding(start = 14.dp, end = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            Icons.Filled.Search, contentDescription = null,
            tint = if (focused) PrimaryBlue else MutedGray,
            modifier = Modifier.size(20.dp),
        )
        Spacer(Modifier.width(10.dp))
        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
            if (query.isEmpty()) {
                Text(
                    "Search name, phone or ticket",
                    fontSize = 14.sp, color = MutedGray, maxLines = 1,
                )
            }
            androidx.compose.foundation.text.BasicTextField(
                value = query,
                onValueChange = onQuery,
                singleLine = true,
                interactionSource = interaction,
                textStyle = androidx.compose.ui.text.TextStyle(fontSize = 14.sp, color = InkDark, fontWeight = FontWeight.Medium),
                cursorBrush = androidx.compose.ui.graphics.SolidColor(PrimaryBlue),
                keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(imeAction = androidx.compose.ui.text.input.ImeAction.Search),
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (query.isNotEmpty()) {
            IconButton(onClick = { onQuery("") }, modifier = Modifier.size(36.dp)) {
                Icon(Icons.Filled.Clear, contentDescription = "Clear search", tint = MutedGray, modifier = Modifier.size(18.dp))
            }
        } else {
            Spacer(Modifier.width(8.dp))
        }
    }
}
