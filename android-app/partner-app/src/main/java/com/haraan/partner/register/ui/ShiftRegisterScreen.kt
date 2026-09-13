package com.haraan.partner.register.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.register.model.ShiftDropItem
import com.haraan.partner.register.model.ShiftPaymentItem
import com.haraan.partner.register.model.ShiftSessionUiModel
import com.haraan.partner.register.viewmodel.ShiftViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShiftRegisterScreen(
    viewModel: ShiftViewModel,
    venueId: Long,
    onNavigateBack: (() -> Unit)? = null,
) {
    val state by viewModel.uiState.collectAsState()

    LaunchedEffect(venueId) {
        viewModel.init(venueId)
    }

    if (state.showHistoryScreen) {
        ShiftHistoryScreen(
            history = state.history,
            onBack = { viewModel.setShowHistoryScreen(false) }
        )
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = "Cash Register",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = ShiftColors.AuthInk
                        )
                        Text(
                            text = if (state.activeShift != null) "Active Shift · ${state.activeShift?.staffName}" else "Drawer Closed",
                            fontSize = 12.sp,
                            color = if (state.activeShift != null) ShiftColors.GREEN else ShiftColors.AuthMuted
                        )
                    }
                },
                navigationIcon = {
                    if (onNavigateBack != null) {
                        IconButton(onClick = onNavigateBack) {
                            Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                        }
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.setShowHistoryScreen(true) }) {
                        Icon(Icons.Default.List, contentDescription = "Audit History", tint = ShiftColors.AuthInk)
                    }
                    IconButton(onClick = { viewModel.loadCurrentShift(forceRefresh = true) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = ShiftColors.AuthInk)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White)
            )
        },
        containerColor = ShiftColors.AuthPageBg
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            when {
                state.isLoading && state.activeShift == null -> {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = ShiftColors.AuthAccent)
                    }
                }
                state.activeShift == null -> {
                    // Closed Register State
                    ClosedRegisterState(
                        onOpenShift = { viewModel.setShowOpenShiftDialog(true) },
                        onViewHistory = { viewModel.setShowHistoryScreen(true) }
                    )
                }
                else -> {
                    // Active Open Shift Dashboard
                    ActiveShiftContent(
                        shift = state.activeShift!!,
                        onRecordDrop = { viewModel.setShowCashDropDialog(true) },
                        onCloseShift = { viewModel.setShowCloseoutSheet(true) }
                    )
                }
            }

            // Error banner if any
            state.errorMessage?.let { msg ->
                Snackbar(
                    action = {
                        TextButton(onClick = { viewModel.clearError() }) {
                            Text("Dismiss", color = Color.White)
                        }
                    },
                    containerColor = ShiftColors.RED,
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .padding(16.dp)
                ) {
                    Text(msg, color = Color.White)
                }
            }
        }
    }

    // Dialogs & Sheets
    if (state.showOpenShiftDialog) {
        OpenShiftDialog(
            onDismiss = { viewModel.setShowOpenShiftDialog(false) },
            onConfirm = { float, note -> viewModel.openShift(float, note) },
            isSubmitting = state.isSubmittingAction
        )
    }

    if (state.showCashDropDialog) {
        CashDropDialog(
            onDismiss = { viewModel.setShowCashDropDialog(false) },
            onConfirm = { amount, category, reason -> viewModel.recordDrop(amount, category, reason) },
            isSubmitting = state.isSubmittingAction
        )
    }

    if (state.showCloseoutSheet && state.activeShift != null) {
        ShiftCloseoutSheet(
            shift = state.activeShift!!,
            onDismiss = { viewModel.setShowCloseoutSheet(false) },
            onConfirmClose = { countedCash, note, denoms -> viewModel.closeShift(countedCash, note, denoms) },
            isSubmitting = state.isSubmittingAction
        )
    }
}

@Composable
private fun ClosedRegisterState(
    onOpenShift: () -> Unit,
    onViewHistory: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(72.dp)
                .clip(CircleShape)
                .background(ShiftColors.AuthAccent.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.Default.Lock,
                contentDescription = null,
                tint = ShiftColors.AuthAccent,
                modifier = Modifier.size(36.dp)
            )
        }

        Spacer(Modifier.height(16.dp))

        Text(
            text = "Register Is Closed",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = ShiftColors.AuthInk
        )

        Spacer(Modifier.height(8.dp))

        Text(
            text = "Open a shift to set your starting cash float, track walk-in payments, and reconcile drawer variance at end of day.",
            fontSize = 13.sp,
            color = ShiftColors.AuthMuted,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            modifier = Modifier.padding(horizontal = 16.dp)
        )

        Spacer(Modifier.height(28.dp))

        Button(
            onClick = onOpenShift,
            colors = ButtonDefaults.buttonColors(containerColor = ShiftColors.AuthAccent),
            shape = RoundedCornerShape(12.dp),
            modifier = Modifier.fillMaxWidth().height(48.dp)
        ) {
            Icon(Icons.Default.PlayArrow, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text("Open Shift & Set Float", fontWeight = FontWeight.Bold)
        }

        Spacer(Modifier.height(12.dp))

        OutlinedButton(
            onClick = onViewHistory,
            shape = RoundedCornerShape(12.dp),
            modifier = Modifier.fillMaxWidth().height(48.dp)
        ) {
            Text("View Shift Audit History", color = ShiftColors.AuthInk)
        }
    }
}

@Composable
private fun ActiveShiftContent(
    shift: ShiftSessionUiModel,
    onRecordDrop: () -> Unit,
    onCloseShift: () -> Unit,
) {
    var selectedTab by remember { mutableStateOf(0) }

    LazyColumn(
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        modifier = Modifier.fillMaxSize()
    ) {
        // Hero Expected Cash Card
        item {
            HeroCashCard(shift, onRecordDrop, onCloseShift)
        }

        // Digital movement card
        item {
            DigitalMovementCard(shift)
        }

        // Ledger Transactions Section Header with Tabs
        item {
            Column {
                Text(
                    text = "SHIFT TRANSACTIONS",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = ShiftColors.AuthMuted,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                TabRow(
                    selectedTabIndex = selectedTab,
                    containerColor = Color.White,
                    contentColor = ShiftColors.AuthAccent,
                    modifier = Modifier.clip(RoundedCornerShape(10.dp))
                ) {
                    Tab(
                        selected = selectedTab == 0,
                        onClick = { selectedTab = 0 },
                        text = {
                            Text(
                                "Cash In (${shift.payments.count { it.method == "cash" }})",
                                fontWeight = if (selectedTab == 0) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    )
                    Tab(
                        selected = selectedTab == 1,
                        onClick = { selectedTab = 1 },
                        text = {
                            Text(
                                "Drops (${shift.drops.size})",
                                fontWeight = if (selectedTab == 1) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    )
                }
            }
        }

        if (selectedTab == 0) {
            val cashPayments = shift.payments.filter { it.method == "cash" }
            if (cashPayments.isEmpty()) {
                item {
                    EmptyListPlaceholder("No cash payments taken in this shift yet.")
                }
            } else {
                items(cashPayments) { payment ->
                    PaymentRow(payment)
                }
            }
        } else {
            if (shift.drops.isEmpty()) {
                item {
                    EmptyListPlaceholder("No cash drops or expenses recorded in this shift.")
                }
            } else {
                items(shift.drops) { drop ->
                    DropRow(drop)
                }
            }
        }
    }
}

@Composable
private fun HeroCashCard(
    shift: ShiftSessionUiModel,
    onRecordDrop: () -> Unit,
    onCloseShift: () -> Unit,
) {
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = Color.White,
        shadowElevation = 2.dp,
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(18.dp)) {
            Row(
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(8.dp)
                            .clip(CircleShape)
                            .background(ShiftColors.GREEN)
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "DRAWER CASH (LIVE)",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = ShiftColors.GREEN
                    )
                }
                Text(
                    text = "Shift #${shift.shiftId}",
                    fontSize = 11.sp,
                    color = ShiftColors.AuthMuted
                )
            }

            Spacer(Modifier.height(8.dp))

            Text(
                text = "₹${String.format("%.2f", shift.expectedCash)}",
                fontSize = 32.sp,
                fontWeight = FontWeight.Black,
                color = ShiftColors.AuthInk
            )
            Text(
                text = "Expected physical cash in register right now",
                fontSize = 12.sp,
                color = ShiftColors.AuthMuted
            )

            HorizontalDivider(color = ShiftColors.Hairline, modifier = Modifier.padding(vertical = 12.dp))

            // Breakdown
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text("OPEN FLOAT", fontSize = 10.sp, color = ShiftColors.AuthMuted, fontWeight = FontWeight.SemiBold)
                    Text("₹${shift.openingFloat.toInt()}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                }
                Column {
                    Text("CASH COLLECTED", fontSize = 10.sp, color = ShiftColors.AuthMuted, fontWeight = FontWeight.SemiBold)
                    Text("+₹${shift.cashCollected.toInt()}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = ShiftColors.GREEN)
                }
                Column {
                    Text("CASH DROPS", fontSize = 10.sp, color = ShiftColors.AuthMuted, fontWeight = FontWeight.SemiBold)
                    Text("-₹${shift.totalDrops.toInt()}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = ShiftColors.RED)
                }
            }

            Spacer(Modifier.height(16.dp))

            // Action Buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                OutlinedButton(
                    onClick = onRecordDrop,
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = ShiftColors.RED),
                    modifier = Modifier.weight(1f).height(44.dp)
                ) {
                    Icon(Icons.Default.ArrowDropDown, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Cash Drop", fontWeight = FontWeight.SemiBold)
                }

                Button(
                    onClick = onCloseShift,
                    colors = ButtonDefaults.buttonColors(containerColor = ShiftColors.AuthInk),
                    shape = RoundedCornerShape(10.dp),
                    modifier = Modifier.weight(1.3f).height(44.dp)
                ) {
                    Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Close Shift", fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun DigitalMovementCard(shift: ShiftSessionUiModel) {
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = Color.White,
        shadowElevation = 1.dp,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text(
                    text = "DIGITAL COLLECTIONS (COUNTER)",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = ShiftColors.AuthMuted
                )
                Text(
                    text = "Reconciled against bank statements · Not in drawer",
                    fontSize = 11.sp,
                    color = ShiftColors.AuthMuted
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                Column(horizontalAlignment = Alignment.End) {
                    Text("UPI", fontSize = 10.sp, color = ShiftColors.AuthMuted)
                    Text("₹${shift.upiCollected.toInt()}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthAccent)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("CARD", fontSize = 10.sp, color = ShiftColors.AuthMuted)
                    Text("₹${shift.cardCollected.toInt()}", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                }
            }
        }
    }
}

@Composable
private fun PaymentRow(payment: ShiftPaymentItem) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = Color.White,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .clip(CircleShape)
                        .background(ShiftColors.GREEN.copy(alpha = 0.1f)),
                    contentAlignment = Alignment.Center
                ) {
                    Text("₹", fontWeight = FontWeight.Bold, color = ShiftColors.GREEN, fontSize = 14.sp)
                }
                Spacer(Modifier.width(10.dp))
                Column {
                    Text(payment.customerName, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = ShiftColors.AuthInk)
                    Text("Booking #${payment.id} · ${payment.time}", fontSize = 11.sp, color = ShiftColors.AuthMuted)
                }
            }

            Text(
                text = "+₹${payment.amount.toInt()}",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = ShiftColors.GREEN
            )
        }
    }
}

@Composable
private fun DropRow(drop: ShiftDropItem) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = Color.White,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .clip(CircleShape)
                        .background(ShiftColors.RED.copy(alpha = 0.1f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(Icons.Default.ArrowDropDown, contentDescription = null, tint = ShiftColors.RED, modifier = Modifier.size(16.dp))
                }
                Spacer(Modifier.width(10.dp))
                Column {
                    Text(drop.category.uppercase(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                    Text(drop.reason ?: "Withdrawal by ${drop.staffName} · ${drop.time}", fontSize = 11.sp, color = ShiftColors.AuthMuted)
                }
            }

            Text(
                text = "-₹${drop.amount.toInt()}",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = ShiftColors.RED
            )
        }
    }
}

@Composable
private fun EmptyListPlaceholder(msg: String) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 24.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(msg, fontSize = 13.sp, color = ShiftColors.AuthMuted)
    }
}
