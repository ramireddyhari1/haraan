package com.haraan.partner.recurring.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.haraan.partner.recurring.model.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ContractDetailScreen(
    contract: StandingContractDetail,
    availableCourts: List<Pair<Long, String>>,
    isSubmitting: Boolean,
    onSkipSession: (date: String, reason: String?) -> Unit,
    onPauseContract: (reason: String?) -> Unit,
    onResumeContract: () -> Unit,
    onTerminateContract: (reason: String?) -> Unit,
    onTransferCourt: (courtId: Long) -> Unit,
    onMarkAttendance: (sessionId: Long, status: String) -> Unit,
    onRecordPayment: (amount: Double, paymentType: String, method: String, notes: String?) -> Unit,
    onNavigateBack: () -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Sessions, 1: Payments, 2: Audit Logs
    var showMenu by remember { mutableStateOf(false) }

    var showSkipDialog by remember { mutableStateOf(false) }
    var skipDateText by remember { mutableStateOf("") }
    var skipReasonText by remember { mutableStateOf("Manual Skip") }

    var showPaymentDialog by remember { mutableStateOf(false) }
    var paymentAmount by remember { mutableStateOf("") }
    var paymentType by remember { mutableStateOf("monthly_fee") }
    var paymentMethod by remember { mutableStateOf("upi") }
    var paymentNotes by remember { mutableStateOf("") }

    var showTransferDialog by remember { mutableStateOf(false) }
    var targetCourtId by remember { mutableStateOf(availableCourts.firstOrNull()?.first ?: contract.courtId) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(contract.customerName, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                        Text("${contract.dayOfWeek} · ${contract.startTime}-${contract.endTime}", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { showMenu = !showMenu }) {
                        Icon(Icons.Default.MoreVert, contentDescription = "Actions")
                    }
                    DropdownMenu(expanded = showMenu, onDismissRequest = { showMenu = false }) {
                        DropdownMenuItem(
                            text = { Text("Record Payment") },
                            onClick = {
                                showMenu = false
                                showPaymentDialog = true
                            },
                            leadingIcon = { Icon(Icons.Default.Payments, contentDescription = null) }
                        )
                        DropdownMenuItem(
                            text = { Text("Transfer Court") },
                            onClick = {
                                showMenu = false
                                showTransferDialog = true
                            },
                            leadingIcon = { Icon(Icons.Default.SwapHoriz, contentDescription = null) }
                        )
                        if (contract.status == "active" || contract.status == "at_risk") {
                            DropdownMenuItem(
                                text = { Text("Pause Contract") },
                                onClick = {
                                    showMenu = false
                                    onPauseContract(null)
                                },
                                leadingIcon = { Icon(Icons.Default.Pause, contentDescription = null) }
                            )
                        } else if (contract.status == "paused") {
                            DropdownMenuItem(
                                text = { Text("Resume Contract") },
                                onClick = {
                                    showMenu = false
                                    onResumeContract()
                                },
                                leadingIcon = { Icon(Icons.Default.PlayArrow, contentDescription = null) }
                            )
                        }
                        HorizontalDivider()
                        DropdownMenuItem(
                            text = { Text("Terminate Contract", color = RecurringColors.RED) },
                            onClick = {
                                showMenu = false
                                onTerminateContract("Customer cancellation")
                            },
                            leadingIcon = { Icon(Icons.Default.DeleteForever, contentDescription = null, tint = RecurringColors.RED) }
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = RecurringColors.SurfaceWhite)
            )
        },
        containerColor = RecurringColors.AuthPageBg
    ) { innerPadding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Header Hero Card
            item {
                Card(
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(contract.customerName, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                                Text("Ph: ${contract.customerPhone}", fontSize = 12.sp, color = RecurringColors.AuthMuted)
                            }
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (contract.isAtRisk) RecurringColors.RED.copy(alpha = 0.1f) else RecurringColors.GREEN.copy(alpha = 0.1f)
                            ) {
                                Text(
                                    text = if (contract.isAtRisk) "AT RISK" else contract.status.uppercase(),
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (contract.isAtRisk) RecurringColors.RED else RecurringColors.GREEN,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                )
                            }
                        }

                        HorizontalDivider(color = RecurringColors.Hairline, modifier = Modifier.padding(vertical = 12.dp))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column {
                                Text("SCHEDULE", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                                Text("${contract.dayOfWeek} ${contract.startTime}-${contract.endTime}", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                            Column {
                                Text("COURT", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                                Text(contract.courtName, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                            Column(horizontalAlignment = Alignment.End) {
                                Text("PRICE / SESSION", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                                Text("₹${contract.pricePerSession.toInt()}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = RecurringColors.GREEN)
                            }
                        }

                        Spacer(Modifier.height(10.dp))

                        // Financials Banner
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(RecurringColors.AuthPageBg)
                                .padding(10.dp),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column {
                                Text("DEPOSIT HELD", fontSize = 9.sp, color = RecurringColors.AuthMuted)
                                Text("₹${contract.securityDeposit.toInt()}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                            }
                            Column {
                                Text("ADVANCE PAID", fontSize = 9.sp, color = RecurringColors.AuthMuted)
                                Text("₹${contract.advancePaid.toInt()}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = RecurringColors.GREEN)
                            }
                            Column(horizontalAlignment = Alignment.End) {
                                Text("BALANCE DUE", fontSize = 9.sp, color = RecurringColors.AuthMuted)
                                Text("₹${contract.balanceDue.toInt()}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (contract.balanceDue > 0) RecurringColors.RED else RecurringColors.AuthInk)
                            }
                        }
                    }
                }
            }

            // Tabs Selector
            item {
                TabRow(
                    selectedTabIndex = selectedTab,
                    containerColor = RecurringColors.SurfaceWhite,
                    modifier = Modifier.clip(RoundedCornerShape(10.dp))
                ) {
                    Tab(
                        selected = selectedTab == 0,
                        onClick = { selectedTab = 0 },
                        text = { Text("Sessions (${contract.sessions.size})", fontWeight = FontWeight.SemiBold) }
                    )
                    Tab(
                        selected = selectedTab == 1,
                        onClick = { selectedTab = 1 },
                        text = { Text("Payments (${contract.payments.size})", fontWeight = FontWeight.SemiBold) }
                    )
                    Tab(
                        selected = selectedTab == 2,
                        onClick = { selectedTab = 2 },
                        text = { Text("Audit (${contract.logs.size})", fontWeight = FontWeight.SemiBold) }
                    )
                }
            }

            // Tab 0: Sessions List
            if (selectedTab == 0) {
                items(contract.sessions, key = { it.id }) { s ->
                    Card(
                        shape = RoundedCornerShape(10.dp),
                        colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(s.sessionDate, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                                    Text("${s.startTime}-${s.endTime} · ${s.courtName}", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                                }
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = when (s.attendanceStatus) {
                                        "present" -> RecurringColors.GREEN.copy(alpha = 0.1f)
                                        "absent" -> RecurringColors.RED.copy(alpha = 0.1f)
                                        "skipped_manual", "skipped_holiday" -> RecurringColors.AMBER.copy(alpha = 0.1f)
                                        else -> RecurringColors.BLUE.copy(alpha = 0.1f)
                                    }
                                ) {
                                    Text(
                                        text = s.attendanceStatus.uppercase(),
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = when (s.attendanceStatus) {
                                            "present" -> RecurringColors.GREEN
                                            "absent" -> RecurringColors.RED
                                            "skipped_manual", "skipped_holiday" -> RecurringColors.AMBER
                                            else -> RecurringColors.BLUE
                                        },
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                                    )
                                }
                            }

                            if (s.attendanceStatus == "scheduled") {
                                Spacer(Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Button(
                                        onClick = { onMarkAttendance(s.id, "present") },
                                        shape = RoundedCornerShape(6.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.GREEN),
                                        modifier = Modifier.weight(1f).height(32.dp),
                                        contentPadding = PaddingValues(0.dp)
                                    ) {
                                        Text("Present", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                    OutlinedButton(
                                        onClick = { onMarkAttendance(s.id, "absent") },
                                        shape = RoundedCornerShape(6.dp),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = RecurringColors.RED),
                                        modifier = Modifier.weight(1f).height(32.dp),
                                        contentPadding = PaddingValues(0.dp)
                                    ) {
                                        Text("Absent", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                    OutlinedButton(
                                        onClick = {
                                            skipDateText = s.sessionDate
                                            showSkipDialog = true
                                        },
                                        shape = RoundedCornerShape(6.dp),
                                        modifier = Modifier.weight(1f).height(32.dp),
                                        contentPadding = PaddingValues(0.dp)
                                    ) {
                                        Text("Skip Date", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Tab 1: Payments List
            if (selectedTab == 1) {
                item {
                    Button(
                        onClick = { showPaymentDialog = true },
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.AuthAccent),
                        modifier = Modifier.fillMaxWidth().height(42.dp)
                    ) {
                        Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("Record Payment")
                    }
                }

                items(contract.payments, key = { it.id }) { p ->
                    Card(
                        shape = RoundedCornerShape(10.dp),
                        colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(p.paymentType.replace("_", " ").uppercase(), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                                Text("via ${p.method.uppercase()} · ${p.createdAt.take(10)}", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                            }
                            Text(
                                "+₹${p.amount.toInt()}",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = RecurringColors.GREEN
                            )
                        }
                    }
                }
            }

            // Tab 2: Audit Logs
            if (selectedTab == 2) {
                items(contract.logs, key = { it.id }) { l ->
                    Card(
                        shape = RoundedCornerShape(10.dp),
                        colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text(l.action.replace("_", " ").uppercase(), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthAccent)
                                Text(l.createdAt.take(16).replace("T", " "), fontSize = 10.sp, color = RecurringColors.AuthMuted)
                            }
                            l.details?.let {
                                Text(it, fontSize = 11.sp, color = RecurringColors.AuthInk, modifier = Modifier.padding(top = 4.dp))
                            }
                        }
                    }
                }
            }
        }

        // Dialog: Skip Session
        if (showSkipDialog) {
            AlertDialog(
                onDismissRequest = { showSkipDialog = false },
                title = { Text("Skip Session ($skipDateText)") },
                text = {
                    Column {
                        Text("Skipping frees up the court on the Day Grid immediately.", fontSize = 12.sp, color = RecurringColors.AuthMuted)
                        Spacer(Modifier.height(8.dp))
                        OutlinedTextField(
                            value = skipReasonText,
                            onValueChange = { skipReasonText = it },
                            label = { Text("Reason (Holiday, Rain, Customer Request)") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                },
                confirmButton = {
                    Button(
                        onClick = {
                            showSkipDialog = false
                            onSkipSession(skipDateText, skipReasonText)
                        }
                    ) {
                        Text("Confirm Skip")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { showSkipDialog = false }) { Text("Cancel") }
                }
            )
        }

        // Dialog: Record Payment
        if (showPaymentDialog) {
            AlertDialog(
                onDismissRequest = { showPaymentDialog = false },
                title = { Text("Record Contract Payment") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = paymentAmount,
                            onValueChange = { paymentAmount = it },
                            label = { Text("Amount (₹) *") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                        OutlinedTextField(
                            value = paymentNotes,
                            onValueChange = { paymentNotes = it },
                            label = { Text("Notes / UPI Reference") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                },
                confirmButton = {
                    Button(
                        onClick = {
                            val amt = paymentAmount.toDoubleOrNull()
                            if (amt != null && amt > 0) {
                                showPaymentDialog = false
                                onRecordPayment(amt, paymentType, paymentMethod, paymentNotes.takeIf { it.isNotBlank() })
                            }
                        }
                    ) {
                        Text("Record")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { showPaymentDialog = false }) { Text("Cancel") }
                }
            )
        }

        // Dialog: Transfer Court
        if (showTransferDialog) {
            AlertDialog(
                onDismissRequest = { showTransferDialog = false },
                title = { Text("Transfer to Another Court") },
                text = {
                    Column {
                        Text("Select new court for future sessions:", fontSize = 12.sp, color = RecurringColors.AuthMuted)
                        Spacer(Modifier.height(8.dp))
                        availableCourts.forEach { (cId, cName) ->
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { targetCourtId = cId }
                                    .padding(vertical = 6.dp)
                            ) {
                                RadioButton(selected = targetCourtId == cId, onClick = { targetCourtId = cId })
                                Spacer(Modifier.width(8.dp))
                                Text(cName, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                    }
                },
                confirmButton = {
                    Button(
                        onClick = {
                            showTransferDialog = false
                            onTransferCourt(targetCourtId)
                        }
                    ) {
                        Text("Confirm Transfer")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { showTransferDialog = false }) { Text("Cancel") }
                }
            )
        }
    }
}
