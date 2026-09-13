package com.haraan.partner.recurring.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.recurring.model.StandingContractSummary

@Composable
fun StandingSlotsListScreen(
    contracts: List<StandingContractSummary>,
    selectedStatus: String,
    searchQuery: String,
    onSearchChange: (String) -> Unit,
    onStatusChange: (String) -> Unit,
    onSelectContract: (Long) -> Unit,
    onNewContract: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Search Input
        OutlinedTextField(
            value = searchQuery,
            onValueChange = onSearchChange,
            placeholder = { Text("Search by customer name or phone...") },
            leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = RecurringColors.AuthMuted) },
            trailingIcon = {
                if (searchQuery.isNotEmpty()) {
                    IconButton(onClick = { onSearchChange("") }) {
                        Icon(Icons.Default.Close, contentDescription = null, tint = RecurringColors.AuthMuted)
                    }
                }
            },
            shape = RoundedCornerShape(12.dp),
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(Modifier.height(10.dp))

        // Status Filter Chips
        val statusFilters = listOf(
            "all" to "All",
            "active" to "Active",
            "at_risk" to "At Risk",
            "paused" to "Paused",
            "terminated" to "Terminated"
        )
        LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            items(statusFilters) { (code, label) ->
                val isSelected = selectedStatus == code
                FilterChip(
                    selected = isSelected,
                    onClick = { onStatusChange(code) },
                    label = {
                        Text(
                            text = label,
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                        )
                    },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = if (code == "at_risk") RecurringColors.RED else RecurringColors.AuthAccent,
                        selectedLabelColor = Color.White
                    ),
                    shape = RoundedCornerShape(20.dp)
                )
            }
        }

        Spacer(Modifier.height(12.dp))

        // Contracts List
        if (contracts.isEmpty()) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .weight(1f),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        Icons.Default.CalendarMonth,
                        contentDescription = null,
                        tint = RecurringColors.AuthMuted.copy(alpha = 0.5f),
                        modifier = Modifier.size(56.dp)
                    )
                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = "No standing contracts found",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = RecurringColors.AuthInk
                    )
                    Text(
                        text = "Create a contract for weekly recurring clubs or academies",
                        fontSize = 12.sp,
                        color = RecurringColors.AuthMuted
                    )
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = onNewContract,
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.AuthAccent)
                    ) {
                        Text("Create Contract")
                    }
                }
            }
        } else {
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.weight(1f)
            ) {
                items(contracts, key = { it.id }) { contract ->
                    StandingContractCard(
                        contract = contract,
                        onClick = { onSelectContract(contract.id) }
                    )
                }
            }
        }
    }
}

@Composable
fun StandingContractCard(
    contract: StandingContractSummary,
    onClick: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = if (contract.isAtRisk) androidx.compose.foundation.BorderStroke(1.dp, RecurringColors.RED.copy(alpha = 0.5f)) else null,
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            // Header Row: Customer Name, Sport, and Status Pill
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(
                                if (contract.isAtRisk) RecurringColors.RED.copy(alpha = 0.1f)
                                else RecurringColors.AuthAccent.copy(alpha = 0.1f)
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = contract.customerName.take(1).uppercase(),
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (contract.isAtRisk) RecurringColors.RED else RecurringColors.AuthAccent
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text(
                            text = contract.customerName,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                            color = RecurringColors.AuthInk
                        )
                        Text(
                            text = contract.customerPhone,
                            fontSize = 11.sp,
                            color = RecurringColors.AuthMuted
                        )
                    }
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = when {
                        contract.isAtRisk -> RecurringColors.RED.copy(alpha = 0.1f)
                        contract.status == "paused" -> RecurringColors.AMBER.copy(alpha = 0.1f)
                        contract.status == "terminated" -> Color.Gray.copy(alpha = 0.1f)
                        else -> RecurringColors.GREEN.copy(alpha = 0.1f)
                    }
                ) {
                    Text(
                        text = if (contract.isAtRisk) "AT RISK" else contract.status.uppercase(),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = when {
                            contract.isAtRisk -> RecurringColors.RED
                            contract.status == "paused" -> RecurringColors.AMBER
                            contract.status == "terminated" -> Color.Gray
                            else -> RecurringColors.GREEN
                        },
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            // Churn warning callout if at risk
            if (contract.isAtRisk && contract.riskReason != null) {
                Spacer(Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = RecurringColors.RED.copy(alpha = 0.08f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, tint = RecurringColors.RED, modifier = Modifier.size(14.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(contract.riskReason, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.RED)
                    }
                }
            }

            HorizontalDivider(color = RecurringColors.Hairline, modifier = Modifier.padding(vertical = 10.dp))

            // Details Grid
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text("DAY & TIME", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                    Text(
                        "${contract.dayOfWeek} · ${contract.startTime}-${contract.endTime}",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = RecurringColors.AuthInk
                    )
                }
                Column {
                    Text("COURT", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                    Text(contract.courtName, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("MONTHLY VALUE", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
                    Text(
                        "₹${contract.monthlyValue.toInt()}/mo",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = RecurringColors.GREEN
                    )
                }
            }

            Spacer(Modifier.height(10.dp))

            // Footer: Attendance & Next Session
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Attendance pill
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Attendance: ",
                        fontSize = 11.sp,
                        color = RecurringColors.AuthMuted
                    )
                    Text(
                        text = "${contract.attendanceRate.toInt()}%",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (contract.attendanceRate < 60) RecurringColors.RED else RecurringColors.GREEN
                    )
                }

                // Next session tag
                contract.nextSession?.let { ns ->
                    Surface(
                        shape = RoundedCornerShape(6.dp),
                        color = RecurringColors.AuthPageBg
                    ) {
                        Text(
                            text = "Next: ${ns.date.takeLast(5)} (${ns.time})",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = RecurringColors.AuthInk,
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                        )
                    }
                }
            }
        }
    }
}
