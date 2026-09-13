package com.haraan.partner.register.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.register.model.HistoricalShiftSummary
import kotlin.math.abs

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShiftHistoryScreen(
    history: List<HistoricalShiftSummary>,
    onBack: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Shift Audit History", fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Text("Past shift settlements & variances", fontSize = 11.sp, color = ShiftColors.AuthMuted)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White)
            )
        },
        containerColor = ShiftColors.AuthPageBg
    ) { innerPadding ->
        if (history.isEmpty()) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "No closed shifts recorded yet",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Medium,
                        color = ShiftColors.AuthMuted
                    )
                    Text(
                        text = "Closed shifts will appear here for audit",
                        fontSize = 12.sp,
                        color = ShiftColors.AuthMuted
                    )
                }
            }
        } else {
            LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding)
            ) {
                items(history) { shift ->
                    ShiftHistoryCard(shift)
                }
            }
        }
    }
}

@Composable
private fun ShiftHistoryCard(shift: HistoricalShiftSummary) {
    val variance = shift.variance ?: 0.0
    val isSquare = abs(variance) < 0.01
    val isShort = variance < -0.01

    val varianceBg = when {
        isSquare -> ShiftColors.GREEN.copy(alpha = 0.1f)
        isShort -> ShiftColors.RED.copy(alpha = 0.1f)
        else -> ShiftColors.AMBER.copy(alpha = 0.1f)
    }
    val varianceColor = when {
        isSquare -> ShiftColors.GREEN
        isShort -> ShiftColors.RED
        else -> ShiftColors.AMBER
    }

    Surface(
        shape = RoundedCornerShape(12.dp),
        color = Color.White,
        shadowElevation = 1.dp,
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            // Top row: Staff and variance pill
            Row(
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column {
                    Text(
                        text = shift.staffName,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = ShiftColors.AuthInk
                    )
                    Text(
                        text = "Shift #${shift.id} · ${shift.openedAt.take(10)}",
                        fontSize = 11.sp,
                        color = ShiftColors.AuthMuted
                    )
                }

                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = varianceBg
                ) {
                    Text(
                        text = when {
                            isSquare -> "SQUARE"
                            isShort -> "SHORT -₹${String.format("%.0f", abs(variance))}"
                            else -> "OVER +₹${String.format("%.0f", variance)}"
                        },
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = varianceColor,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            HorizontalDivider(color = ShiftColors.Hairline, modifier = Modifier.padding(vertical = 10.dp))

            // Metrics grid
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                MetricColumn("OPEN FLOAT", "₹${shift.openingFloat.toInt()}")
                MetricColumn("CASH IN", "+₹${shift.cashCollected.toInt()}")
                MetricColumn("DROPS", "-₹${shift.totalDrops.toInt()}")
                MetricColumn("EXPECTED", "₹${shift.expectedCash.toInt()}", isBold = true)
                MetricColumn("COUNTED", "₹${shift.countedCash?.toInt() ?: 0}", isBold = true)
            }

            if (!shift.note.isNullOrBlank()) {
                Spacer(Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = ShiftColors.AuthPageBg,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "Note: ${shift.note}",
                        fontSize = 11.sp,
                        color = ShiftColors.AuthInk,
                        modifier = Modifier.padding(8.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun MetricColumn(label: String, value: String, isBold: Boolean = false) {
    Column {
        Text(label, fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = ShiftColors.AuthMuted)
        Text(
            value,
            fontSize = 12.sp,
            fontWeight = if (isBold) FontWeight.Bold else FontWeight.Medium,
            color = ShiftColors.AuthInk
        )
    }
}
