package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Block
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.Done
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.daybookings.model.DaySummaryStats

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val RedColor = Color(0xFFDC2626)
private val AmberColor = Color(0xFFB45309)
private val CardBorder = Color(0xFFE5E7EB)

@Composable
fun DayStatsHeader(
    stats: DaySummaryStats,
    onReopenDay: () -> Unit,
    onCloseDay: () -> Unit,
    canManage: Boolean = true,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, CardBorder, RoundedCornerShape(18.dp))
            .padding(16.dp)
    ) {
        if (stats.isBlocked) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0x14DC2626))
                    .border(1.dp, Color(0x33DC2626), RoundedCornerShape(12.dp))
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Block, contentDescription = null, tint = RedColor, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "Venue closed on this day",
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.sp,
                        color = RedColor,
                    )
                }
                if (canManage) {
                    androidx.compose.material3.TextButton(
                        onClick = onReopenDay,
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                    ) {
                        Text("Reopen", color = PrimaryBlue, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
        }

        // Hero Row: Occupancy Gauge + Key Revenue Numbers
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            // Circular Occupancy Meter
            Box(
                modifier = Modifier.size(72.dp),
                contentAlignment = Alignment.Center,
            ) {
                CircularProgressIndicator(
                    progress = { 1f },
                    modifier = Modifier.fillMaxWidth(),
                    color = Color(0xFFF1F5F9),
                    strokeWidth = 7.dp,
                )
                CircularProgressIndicator(
                    progress = { stats.occupancyRate },
                    modifier = Modifier.fillMaxWidth(),
                    color = when {
                        stats.occupancyRate >= 0.75f -> GreenColor
                        stats.occupancyRate >= 0.40f -> PrimaryBlue
                        else -> AmberColor
                    },
                    strokeWidth = 7.dp,
                )
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        "${(stats.occupancyRate * 100).toInt()}%",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.ExtraBold,
                        color = InkDark,
                    )
                    Text(
                        "OCCUPIED",
                        fontSize = 8.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = MutedGray,
                        letterSpacing = 0.5.sp,
                    )
                }
            }

            Spacer(Modifier.width(16.dp))

            // Revenue and Due Breakdown
            Column(modifier = Modifier.weight(1f)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Column {
                        Text("COLLECTED", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = MutedGray, letterSpacing = 0.8.sp)
                        Text(
                            "₹" + formatInr(stats.collectedRevenue),
                            fontSize = 17.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = GreenColor,
                        )
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("TOTAL EXPECTED", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = MutedGray, letterSpacing = 0.8.sp)
                        Text(
                            "₹" + formatInr(stats.expectedRevenue),
                            fontSize = 17.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = InkDark,
                        )
                    }
                }

                if (stats.pendingDue > 0) {
                    Spacer(Modifier.height(6.dp))
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0x14DC2626))
                            .padding(horizontal = 8.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween,
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.Warning, contentDescription = null, tint = RedColor, modifier = Modifier.size(13.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(
                                "Due balance: ₹" + formatInr(stats.pendingDue),
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.Bold,
                                color = RedColor,
                            )
                        }
                        if (stats.chaseCount > 0) {
                            Text(
                                "${stats.chaseCount} to chase",
                                fontSize = 10.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = RedColor,
                            )
                        }
                    }
                }
            }
        }

        Spacer(Modifier.height(14.dp))

        // Bottom Stats Chips: Capacity, Slots Booked, Available Slots
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            MetricChip(
                label = "Total Slots",
                value = stats.totalSlots.toString(),
                modifier = Modifier.weight(1f),
            )
            MetricChip(
                label = "Booked",
                value = stats.bookedSlots.toString(),
                tint = if (stats.bookedSlots > 0) PrimaryBlue else InkDark,
                modifier = Modifier.weight(1f),
            )
            MetricChip(
                label = "Available",
                value = stats.availableSlots.toString(),
                tint = if (stats.availableSlots > 0) GreenColor else MutedGray,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun MetricChip(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    tint: Color = InkDark,
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Color(0xFFF8FAFC))
            .border(1.dp, Color(0xFFF1F5F9), RoundedCornerShape(10.dp))
            .padding(vertical = 6.dp, horizontal = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(value, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = tint)
        Text(label, fontSize = 9.sp, fontWeight = FontWeight.Medium, color = MutedGray)
    }
}

private fun formatInr(amount: Double): String {
    val rounded = Math.round(amount)
    return java.text.NumberFormat.getNumberInstance(java.util.Locale.forLanguageTag("en-IN")).format(rounded)
}
