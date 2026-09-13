package com.haraan.partner.recurring.ui

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
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.recurring.model.AtRiskAlertItem
import com.haraan.partner.recurring.model.StandingDashboardMetrics
import com.haraan.partner.recurring.model.TodaySessionItem

@Composable
fun StandingSlotsDashboard(
    metrics: StandingDashboardMetrics,
    todaySessions: List<TodaySessionItem>,
    atRiskAlerts: List<AtRiskAlertItem>,
    onNewContract: () -> Unit,
    onViewAllContracts: () -> Unit,
    onSelectContract: (Long) -> Unit,
    onMarkAttendance: (contractId: Long, sessionId: Long, status: String) -> Unit,
    modifier: Modifier = Modifier
) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Hero MRR & KPI Row
        item {
            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "MONTHLY RECURRING REVENUE",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = RecurringColors.AuthMuted
                        )
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = RecurringColors.GREEN.copy(alpha = 0.1f)
                        ) {
                            Text(
                                text = "STABLE TURNOVER",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = RecurringColors.GREEN,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                            )
                        }
                    }

                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = "₹${String.format("%,.0f", metrics.monthlyRecurringRevenue)}",
                        fontSize = 32.sp,
                        fontWeight = FontWeight.Black,
                        color = RecurringColors.AuthInk
                    )
                    Text(
                        text = "Guaranteed baseline income across ${metrics.activeContracts} active contracts",
                        fontSize = 12.sp,
                        color = RecurringColors.AuthMuted
                    )

                    HorizontalDivider(color = RecurringColors.Hairline, modifier = Modifier.padding(vertical = 12.dp))

                    // Secondary KPIs
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        KpiSmallItem(label = "ACTIVE CONTRACTS", value = "${metrics.activeContracts}")
                        KpiSmallItem(label = "AVG ATTENDANCE", value = "${metrics.averageAttendanceRate.toInt()}%")
                        KpiSmallItem(label = "SECURITY DEPOSITS", value = "₹${metrics.totalSecurityDeposits.toInt()}")
                        KpiSmallItem(label = "AT RISK", value = "${metrics.atRiskContracts}", isWarning = metrics.atRiskContracts > 0)
                    }
                }
            }
        }

        // Action Buttons Row
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Button(
                    onClick = onNewContract,
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.AuthAccent),
                    modifier = Modifier.weight(1f).height(46.dp)
                ) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("New Contract", fontWeight = FontWeight.Bold)
                }

                OutlinedButton(
                    onClick = onViewAllContracts,
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = RecurringColors.AuthInk),
                    modifier = Modifier.weight(1f).height(46.dp)
                ) {
                    Icon(Icons.Default.List, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("View All (${metrics.totalContracts})", fontWeight = FontWeight.Bold)
                }
            }
        }

        // At-Risk Churn Alerts (Revenue Leak #4 Safeguard)
        if (atRiskAlerts.isNotEmpty()) {
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = RecurringColors.RED.copy(alpha = 0.05f)),
                    border = androidx.compose.foundation.BorderStroke(1.dp, RecurringColors.RED.copy(alpha = 0.3f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Warning, contentDescription = null, tint = RecurringColors.RED, modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(
                                text = "CHURN RISK DETECTED (${atRiskAlerts.size})",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = RecurringColors.RED
                            )
                        }
                        Text(
                            text = "Customers with 2+ consecutive missed sessions or low attendance. Reach out to prevent cancellations.",
                            fontSize = 11.sp,
                            color = RecurringColors.AuthMuted,
                            modifier = Modifier.padding(top = 4.dp, bottom = 10.dp)
                        )

                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            atRiskAlerts.forEach { alert ->
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = RecurringColors.SurfaceWhite,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable { onSelectContract(alert.id) }
                                ) {
                                    Row(
                                        modifier = Modifier.padding(10.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column {
                                            Text(alert.customerName, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = RecurringColors.AuthInk)
                                            Text("${alert.weekday} · ${alert.time} · ${alert.customerPhone}", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                                            alert.riskReason?.let {
                                                Text(it, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.RED)
                                            }
                                        }
                                        Icon(Icons.Default.ChevronRight, contentDescription = null, tint = RecurringColors.AuthMuted)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Today's Recurring Sessions
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "TODAY'S RECURRING SESSIONS (${todaySessions.size})",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = RecurringColors.AuthMuted
                )
            }
        }

        if (todaySessions.isEmpty()) {
            item {
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = RecurringColors.SurfaceWhite,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Box(modifier = Modifier.padding(24.dp), contentAlignment = Alignment.Center) {
                        Text(
                            text = "No standing slots scheduled for today",
                            fontSize = 13.sp,
                            color = RecurringColors.AuthMuted
                        )
                    }
                }
            }
        } else {
            items(todaySessions, key = { it.sessionId }) { s ->
                TodaySessionCard(
                    session = s,
                    onSelect = { onSelectContract(s.contractId) },
                    onMarkAttendance = { status -> onMarkAttendance(s.contractId, s.sessionId, status) }
                )
            }
        }
    }
}

@Composable
private fun KpiSmallItem(label: String, value: String, isWarning: Boolean = false) {
    Column {
        Text(label, fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = RecurringColors.AuthMuted)
        Text(
            value,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = if (isWarning) RecurringColors.RED else RecurringColors.AuthInk
        )
    }
}

@Composable
private fun TodaySessionCard(
    session: TodaySessionItem,
    onSelect: () -> Unit,
    onMarkAttendance: (String) -> Unit
) {
    Card(
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = RecurringColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onSelect() }
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(RecurringColors.AuthAccent.copy(alpha = 0.1f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.SportsTennis, contentDescription = null, tint = RecurringColors.AuthAccent, modifier = Modifier.size(20.dp))
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text(session.customerName, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AuthInk)
                        Text("${session.courtName} · ${session.startTime} - ${session.endTime}", fontSize = 11.sp, color = RecurringColors.AuthMuted)
                    }
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = when (session.attendanceStatus) {
                        "present" -> RecurringColors.GREEN.copy(alpha = 0.1f)
                        "absent" -> RecurringColors.RED.copy(alpha = 0.1f)
                        else -> RecurringColors.BLUE.copy(alpha = 0.1f)
                    }
                ) {
                    Text(
                        text = session.attendanceStatus.uppercase(),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = when (session.attendanceStatus) {
                            "present" -> RecurringColors.GREEN
                            "absent" -> RecurringColors.RED
                            else -> RecurringColors.BLUE
                        },
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            if (session.attendanceStatus == "scheduled") {
                Spacer(Modifier.height(10.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(
                        onClick = { onMarkAttendance("present") },
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = RecurringColors.GREEN),
                        modifier = Modifier.weight(1f).height(36.dp),
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Present", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }

                    OutlinedButton(
                        onClick = { onMarkAttendance("absent") },
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = RecurringColors.RED),
                        modifier = Modifier.weight(1f).height(36.dp),
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Icon(Icons.Default.Close, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Absent", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}
