package com.haraan.partner.recurring.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
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
import com.haraan.partner.recurring.model.ConflictReport
import com.haraan.partner.recurring.model.CreateContractRequest
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CreateStandingContractScreen(
    availableCourts: List<Pair<Long, String>>, // List of courtId to courtName
    conflictReport: ConflictReport?,
    isCheckingConflicts: Boolean,
    isSubmitting: Boolean,
    onCheckConflicts: (courtId: Long, weekday: String, startTime: String, endTime: String, startDate: String) -> Unit,
    onSubmit: (CreateContractRequest) -> Unit,
    onNavigateBack: () -> Unit
) {
    var customerName by remember { mutableStateOf("") }
    var customerPhone by remember { mutableStateOf("") }
    var selectedCourtId by remember { mutableStateOf(availableCourts.firstOrNull()?.first ?: 1L) }
    var sport by remember { mutableStateOf("Badminton") }

    val weekdays = listOf("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday")
    var selectedWeekday by remember { mutableStateOf("monday") }

    var startTime by remember { mutableStateOf("19:00") }
    var endTime by remember { mutableStateOf("20:00") }

    var pricePerSession by remember { mutableStateOf("600") }
    var securityDeposit by remember { mutableStateOf("1000") }
    var advancePaid by remember { mutableStateOf("0") }
    var paymentMethod by remember { mutableStateOf("cash") }

    val todayStr = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date()) }
    var startDate by remember { mutableStateOf(todayStr) }
    var autoRenew by remember { mutableStateOf(true) }
    var autoSkipConflicts by remember { mutableStateOf(true) }
    var notes by remember { mutableStateOf("") }

    var validationError by remember { mutableStateOf<String?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Create Standing Contract", fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Text("Weekly recurring booking engine", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
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
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Section 1: Customer Details
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("CUSTOMER & GROUP DETAILS", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthMuted)
                        Spacer(Modifier.height(10.dp))

                        OutlinedTextField(
                            value = customerName,
                            onValueChange = { customerName = it },
                            label = { Text("Customer / Club Name *") },
                            placeholder = { Text("e.g. Hyderabad Smashers Club") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )

                        Spacer(Modifier.height(8.dp))

                        OutlinedTextField(
                            value = customerPhone,
                            onValueChange = { customerPhone = it },
                            label = { Text("Customer Phone Number *") },
                            placeholder = { Text("9876543210") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }
            }

            // Section 2: Court, Sport & Slot Schedule
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("COURT & SCHEDULE", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthMuted)
                        Spacer(Modifier.height(10.dp))

                        Text("Select Court", fontSize = 12.sp, color = RecurringColors.AuthMuted)
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(vertical = 6.dp)) {
                            items(availableCourts) { (courtId, courtName) ->
                                val isSelected = selectedCourtId == courtId
                                FilterChip(
                                    selected = isSelected,
                                    onClick = { selectedCourtId = courtId },
                                    label = { Text(courtName) },
                                    colors = FilterChipDefaults.filterChipColors(
                                        selectedContainerColor = RecurringColors.AuthAccent,
                                        selectedLabelColor = Color.White
                                    )
                                )
                            }
                        }

                        Spacer(Modifier.height(8.dp))

                        Text("Recurring Day of Week", fontSize = 12.sp, color = RecurringColors.AuthMuted)
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(vertical = 6.dp)) {
                            items(weekdays) { day ->
                                val isSelected = selectedWeekday == day
                                FilterChip(
                                    selected = isSelected,
                                    onClick = { selectedWeekday = day },
                                    label = { Text(day.take(3).uppercase()) },
                                    colors = FilterChipDefaults.filterChipColors(
                                        selectedContainerColor = RecurringColors.AuthAccent,
                                        selectedLabelColor = Color.White
                                    )
                                )
                            }
                        }

                        Spacer(Modifier.height(8.dp))

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = startTime,
                                onValueChange = { startTime = it },
                                label = { Text("Start (HH:mm)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = endTime,
                                onValueChange = { endTime = it },
                                label = { Text("End (HH:mm)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }

            // Section 3: Pricing & Deposit
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("FINANCIALS & SECURITY DEPOSIT", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthMuted)
                        Spacer(Modifier.height(10.dp))

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = pricePerSession,
                                onValueChange = { pricePerSession = it },
                                label = { Text("Price/Session (₹) *") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = securityDeposit,
                                onValueChange = { securityDeposit = it },
                                label = { Text("Security Deposit (₹)") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                        }

                        Spacer(Modifier.height(8.dp))

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = advancePaid,
                                onValueChange = { advancePaid = it },
                                label = { Text("Advance Paid (₹)") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = startDate,
                                onValueChange = { startDate = it },
                                label = { Text("Active From (Date)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }

            // Section 4: Conflict Engine Check & Schedule Simulation
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("CONFLICT ENGINE & PREVIEW", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthMuted)
                            Button(
                                onClick = {
                                    onCheckConflicts(selectedCourtId, selectedWeekday, startTime, endTime, startDate)
                                },
                                shape = RoundedCornerShape(8.dp),
                                enabled = !isCheckingConflicts,
                                colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.AuthAccent),
                                modifier = Modifier.height(34.dp),
                                contentPadding = PaddingValues(horizontal = 10.dp)
                            ) {
                                if (isCheckingConflicts) {
                                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                                } else {
                                    Icon(Icons.Default.Search, contentDescription = null, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text("Check Conflicts", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }

                        if (conflictReport != null) {
                            Spacer(Modifier.height(12.dp))
                            if (conflictReport.isClear) {
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = RecurringColors.GREEN.copy(alpha = 0.1f),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Default.CheckCircle, contentDescription = null, tint = RecurringColors.GREEN)
                                        Spacer(Modifier.width(8.dp))
                                        Text(
                                            text = "100% Clear! All ${conflictReport.totalSessionsChecked} sessions are available.",
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = RecurringColors.GREEN
                                        )
                                    }
                                }
                            } else {
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = RecurringColors.AMBER.copy(alpha = 0.1f),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Column(modifier = Modifier.padding(10.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.Warning, contentDescription = null, tint = RecurringColors.AMBER)
                                            Spacer(Modifier.width(8.dp))
                                            Text(
                                                text = "${conflictReport.conflictingSessionsCount} conflicting dates detected",
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = RecurringColors.AMBER
                                            )
                                        }
                                        Spacer(Modifier.height(4.dp))
                                        conflictReport.conflicts.take(3).forEach { c ->
                                            Text("• ${c.date}: ${c.reason}", fontSize = 11.sp, color = RecurringColors.AuthInk)
                                        }

                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            modifier = Modifier.padding(top = 8.dp)
                                        ) {
                                            Checkbox(
                                                checked = autoSkipConflicts,
                                                onCheckedChange = { autoSkipConflicts = it }
                                            )
                                            Text("Auto-skip conflicting dates when creating", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Submit Button & Validation Error
            item {
                validationError?.let {
                    Text(it, color = RecurringColors.RED, fontSize = 12.sp, modifier = Modifier.padding(bottom = 6.dp))
                }

                Button(
                    onClick = {
                        if (customerName.isBlank()) {
                            validationError = "Please enter customer/club name"
                            return@Button
                        }
                        if (customerPhone.isBlank() || customerPhone.length < 10) {
                            validationError = "Please enter a valid 10-digit phone number"
                            return@Button
                        }
                        val price = pricePerSession.toDoubleOrNull()
                        if (price == null || price <= 0) {
                            validationError = "Please enter a valid price per session"
                            return@Button
                        }

                        val req = CreateContractRequest(
                            customerName = customerName.trim(),
                            customerPhone = customerPhone.trim(),
                            courtId = selectedCourtId,
                            sport = sport,
                            dayOfWeek = selectedWeekday,
                            startTime = startTime.trim(),
                            endTime = endTime.trim(),
                            pricePerSession = price,
                            securityDeposit = securityDeposit.toDoubleOrNull() ?: 0.0,
                            advancePaid = advancePaid.toDoubleOrNull() ?: 0.0,
                            activeFrom = startDate,
                            autoRenew = autoRenew,
                            autoSkipConflicts = autoSkipConflicts,
                            notes = notes.takeIf { it.isNotBlank() },
                            paymentMethod = paymentMethod
                        )
                        onSubmit(req)
                    },
                    shape = RoundedCornerShape(12.dp),
                    enabled = !isSubmitting,
                    colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.AuthAccent),
                    modifier = Modifier.fillMaxWidth().height(50.dp)
                ) {
                    if (isSubmitting) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                        Spacer(Modifier.width(8.dp))
                        Text("Materializing Schedule...")
                    } else {
                        Text("Create Contract & Materialize Schedule", fontWeight = FontWeight.Bold)
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
