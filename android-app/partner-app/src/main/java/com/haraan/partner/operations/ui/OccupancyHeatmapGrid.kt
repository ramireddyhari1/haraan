package com.haraan.partner.operations.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.operations.model.OccupancyHeatmapCell
import com.haraan.partner.operations.model.OccupancyHeatmapRow

@Composable
fun OccupancyHeatmapGrid(
    rows: List<OccupancyHeatmapRow>,
    modifier: Modifier = Modifier
) {
    var selectedCell by remember { mutableStateOf<Pair<String, OccupancyHeatmapCell>?>(null) }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(12.dp)
    ) {
        Text(
            text = "Court Occupancy Heatmap (Past 4 Weeks)",
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF0F172A)
        )
        Text(
            text = "Aggregated across all courts by day & hour (06:00 - 24:00)",
            fontSize = 12.sp,
            color = Color(0xFF64748B)
        )

        Spacer(Modifier.height(12.dp))

        // Legend
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            LegendItem("Empty", Color(0xFFE2E8F0))
            LegendItem("1-20%", Color(0xFFA7F3D0))
            LegendItem("21-50%", Color(0xFF34D399))
            LegendItem("51-80%", Color(0xFFFBBF24))
            LegendItem("Peak >80%", Color(0xFFEF4444))
        }

        Spacer(Modifier.height(12.dp))

        // Heatmap Matrix Table
        val scrollState = rememberScrollState()
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(scrollState)
        ) {
            Column {
                // Header row of hours
                Row {
                    Box(modifier = Modifier.width(42.dp)) // Corner
                    for (h in 6..23) {
                        Box(
                            modifier = Modifier
                                .width(34.dp)
                                .padding(vertical = 2.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = sprintf("%02d", h),
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF64748B)
                            )
                        }
                    }
                }

                // Days rows
                rows.forEach { row ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = row.dayName,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Color(0xFF1E293B),
                            modifier = Modifier.width(42.dp)
                        )

                        row.hours.forEach { cell ->
                            val cellColor = when (cell.intensity) {
                                "peak"   -> Color(0xFFEF4444)
                                "high"   -> Color(0xFFFBBF24)
                                "medium" -> Color(0xFF34D399)
                                "low"    -> Color(0xFFA7F3D0)
                                else     -> Color(0xFFF1F5F9)
                            }

                            Box(
                                modifier = Modifier
                                    .size(34.dp)
                                    .padding(2.dp)
                                    .background(cellColor, RoundedCornerShape(4.dp))
                                    .border(0.5.dp, Color(0xFFCBD5E1), RoundedCornerShape(4.dp))
                                    .clickable { selectedCell = Pair(row.dayName, cell) },
                                contentAlignment = Alignment.Center
                            ) {
                                if (cell.occupancyPct > 0) {
                                    Text(
                                        text = "${cell.occupancyPct.toInt()}%",
                                        fontSize = 8.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (cell.intensity == "peak") Color.White else Color(0xFF0F172A)
                                    )
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(2.dp))
                }
            }
        }
    }

    // Selected Cell Details Dialog
    selectedCell?.let { (day, cell) ->
        AlertDialog(
            onDismissRequest = { selectedCell = null },
            title = { Text("$day ${cell.hourLabel}") },
            text = {
                Column {
                    Text("Occupancy: ${cell.occupancyPct}%", fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text("Booked slots: ${cell.bookedCount} / ${cell.capacity}")
                    Spacer(Modifier.height(4.dp))
                    Text("Intensity level: ${cell.intensity.uppercase()}")
                }
            },
            confirmButton = {
                TextButton(onClick = { selectedCell = null }) {
                    Text("Close")
                }
            }
        )
    }
}

@Composable
fun LegendItem(label: String, color: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            modifier = Modifier
                .size(10.dp)
                .background(color, RoundedCornerShape(2.dp))
        )
        Spacer(Modifier.width(3.dp))
        Text(label, fontSize = 9.sp, color = Color(0xFF64748B))
    }
}

private fun sprintf(format: String, vararg args: Any): String {
    return java.lang.String.format(format, *args)
}