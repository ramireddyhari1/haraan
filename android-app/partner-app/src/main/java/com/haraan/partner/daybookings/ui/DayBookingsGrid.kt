package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.DayBooking
import com.haraan.partner.DayGrid
import com.haraan.partner.DaySlot
import com.haraan.partner.daybookings.model.DayBookingItem

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val AmberColor = Color(0xFFB45309)
private val CardBorder = Color(0xFFE5E7EB)

@Composable
fun DayBookingsGrid(
    grid: DayGrid,
    canBook: Boolean,
    onCellClick: (slotId: Long, slotTime: String, courtId: Long, courtName: String, price: Double) -> Unit,
    onBookingClick: (DayBooking) -> Unit,
    modifier: Modifier = Modifier,
) {
    val scrollState = rememberScrollState()
    val cellWidth = 120.dp
    val timeColWidth = 72.dp

    Column(
        modifier = modifier
            .fillMaxSize()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, CardBorder, RoundedCornerShape(16.dp))
    ) {
        // Sticky Header: Court Columns
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color(0xFFF8FAFC))
                .border(1.dp, CardBorder)
                .padding(vertical = 10.dp)
        ) {
            // Time Column Header
            Box(
                modifier = Modifier.width(timeColWidth),
                contentAlignment = Alignment.Center,
            ) {
                Text("TIME", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = MutedGray, letterSpacing = 1.sp)
            }

            // Scrollable Court Names Header
            Row(
                modifier = Modifier
                    .weight(1f)
                    .horizontalScroll(scrollState),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                for (court in grid.courts) {
                    Column(
                        modifier = Modifier
                            .width(cellWidth)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color.White)
                            .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(8.dp))
                            .padding(vertical = 6.dp, horizontal = 8.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(
                            court.name,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = InkDark,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        if (court.sports.isNotEmpty()) {
                            Text(
                                court.sports.joinToString(", "),
                                fontSize = 9.sp,
                                color = MutedGray,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }
                    }
                }
            }
        }

        // Body: Time Slot Rows with Court Cells
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
        ) {
            items(grid.slots, key = { it.slotId }) { slot ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(1.dp, Color(0xFFF1F5F9))
                        .padding(vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // Time Label
                    Column(
                        modifier = Modifier.width(timeColWidth),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(
                            slot.time ?: slot.label,
                            fontSize = 11.5.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = InkDark,
                            textAlign = TextAlign.Center,
                        )
                    }

                    // Cells for this time slot
                    Row(
                        modifier = Modifier
                            .weight(1f)
                            .horizontalScroll(scrollState),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        for (court in grid.courts) {
                            val cell = slot.courts.firstOrNull { it.courtId == court.id }
                            val price = cell?.price ?: slot.price
                            val isBlocked = grid.isBlocked || cell?.allowed == false

                            if (cell != null && cell.isBooked && cell.bookings.isNotEmpty()) {
                                // Booked Cell
                                val primaryBooking = cell.bookings.first()
                                val isWalkIn = primaryBooking.channel.equals("offline", ignoreCase = true)
                                val isCheckedIn = primaryBooking.checkedIn > 0

                                Column(
                                    modifier = Modifier
                                        .width(cellWidth)
                                        .height(68.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(if (isWalkIn) Color(0xFFF0F9FF) else Color(0xFFEFF6FF))
                                        .border(1.dp, if (isWalkIn) Color(0xFFBAE6FD) else Color(0xFFBFDBFE), RoundedCornerShape(10.dp))
                                        .clickable { onBookingClick(primaryBooking) }
                                        .padding(6.dp),
                                    verticalArrangement = Arrangement.SpaceBetween,
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        Text(
                                            if (isWalkIn) "WALK-IN" else "ONLINE",
                                            fontSize = 8.sp,
                                            fontWeight = FontWeight.ExtraBold,
                                            color = if (isWalkIn) Color(0xFF0369A1) else PrimaryBlue,
                                        )
                                        if (isCheckedIn) {
                                            Box(
                                                modifier = Modifier
                                                    .clip(RoundedCornerShape(4.dp))
                                                    .background(Color(0xFFDCFCE7))
                                                    .padding(horizontal = 3.dp, vertical = 1.dp)
                                            ) {
                                                Text("✓ IN", fontSize = 7.5.sp, fontWeight = FontWeight.Bold, color = GreenColor)
                                            }
                                        }
                                    }
                                    Text(
                                        primaryBooking.customer,
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = InkDark,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis,
                                    )
                                    Text(
                                        "₹" + primaryBooking.amount.toInt(),
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = MutedGray,
                                    )
                                }
                            } else if (cell != null && cell.isHeld) {
                                // Live Held Cell (Someone on payment screen)
                                Column(
                                    modifier = Modifier
                                        .width(cellWidth)
                                        .height(68.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFFFEF3C7))
                                        .border(1.dp, Color(0xFFFDE68A), RoundedCornerShape(10.dp))
                                        .padding(6.dp),
                                    verticalArrangement = Arrangement.Center,
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                ) {
                                    Icon(Icons.Filled.Lock, contentDescription = null, tint = AmberColor, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.height(3.dp))
                                    Text("IN CHECKOUT", fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, color = AmberColor)
                                    Text("Reserved", fontSize = 8.sp, color = MutedGray)
                                }
                            } else if (isBlocked) {
                                // Blocked / Unavailable
                                Box(
                                    modifier = Modifier
                                        .width(cellWidth)
                                        .height(68.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFFF1F5F9))
                                        .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(10.dp)),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Text("CLOSED", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = MutedGray)
                                }
                            } else {
                                // Available Cell -> Click to add Walk-in!
                                Column(
                                    modifier = Modifier
                                        .width(cellWidth)
                                        .height(68.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFFF0FDF4))
                                        .border(1.dp, Color(0xFFBBF7D0), RoundedCornerShape(10.dp))
                                        .clickable(enabled = canBook) {
                                            onCellClick(slot.slotId, slot.time ?: slot.label, court.id, court.name, price)
                                        }
                                        .padding(6.dp),
                                    verticalArrangement = Arrangement.SpaceBetween,
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                    ) {
                                        Text("AVAILABLE", fontSize = 8.sp, fontWeight = FontWeight.ExtraBold, color = GreenColor)
                                        if (cell?.isPeak == true) {
                                            Text("PEAK", fontSize = 7.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF7C3AED))
                                        }
                                    }
                                    Text(
                                        "₹" + price.toInt(),
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.ExtraBold,
                                        color = GreenColor,
                                    )
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Filled.Add, contentDescription = null, tint = GreenColor, modifier = Modifier.size(10.dp))
                                        Text("Walk-in", fontSize = 8.5.sp, fontWeight = FontWeight.Medium, color = GreenColor)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
