package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.lazy.LazyListScope
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
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.outlined.Block
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Build
import androidx.compose.material.icons.outlined.EmojiEvents
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.School
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
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
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.text.TextStyle
import com.haraan.partner.CourtBlock
import com.haraan.partner.DayBooking
import com.haraan.partner.DayGrid
import com.haraan.partner.DaySlot
import com.haraan.partner.daybookings.model.DayBookingItem
import com.haraan.partner.ui.components.pressableTile

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val AmberColor = Color(0xFFB45309)
private val CardBorder = Color(0xFFE5E7EB)

/** Column widths and the one cell height, shared by the header and every row. */
private val GridCellWidth = 132.dp
private val GridCellHeight = 84.dp
private val GridTimeColWidth = 72.dp

/**
 * The court grid, emitted as rows of the screen's own list.
 *
 * It used to be a card holding its own LazyColumn under a fixed stack of calendar,
 * stats, search and filters, so on a phone the courts got whatever sliver was
 * left. Now every time row is an item of the page: the header stack scrolls away
 * and the court names stick to the top. [scrollState] is shared by the header and
 * every row, so the courts scroll sideways as one sheet.
 */
@OptIn(ExperimentalFoundationApi::class)
fun LazyListScope.dayBookingsGridItems(
    grid: DayGrid,
    canBook: Boolean,
    scrollState: ScrollState,
    onCellClick: (slotId: Long, slotTime: String, courtId: Long, courtName: String, price: Double) -> Unit,
    onBookingClick: (DayBooking) -> Unit,
    onBlockClick: (block: CourtBlock, courtName: String, slotTime: String) -> Unit = { _, _, _ -> },
) {
    stickyHeader(key = "grid-head") { GridHeader(grid, scrollState) }
    if (grid.slots.isEmpty()) {
        item(key = "grid-empty") {
            Box(
                Modifier.fillMaxWidth().background(Color.White).padding(vertical = 36.dp),
                contentAlignment = Alignment.Center,
            ) { Text("No time slots on this day", fontSize = 13.sp, color = MutedGray) }
        }
    }
    items(grid.slots, key = { "slot-${it.slotId}" }) { slot ->
        GridSlotRow(slot, grid, canBook, scrollState, onCellClick, onBookingClick, onBlockClick)
    }
    item(key = "grid-foot") {
        Box(
            Modifier.fillMaxWidth().height(14.dp)
                .clip(RoundedCornerShape(bottomStart = 16.dp, bottomEnd = 16.dp))
                .background(Color.White)
                .border(1.dp, CardBorder, RoundedCornerShape(bottomStart = 16.dp, bottomEnd = 16.dp)),
        )
    }
}

@Composable
private fun GridHeader(grid: DayGrid, scrollState: ScrollState) {
    val cellWidth = GridCellWidth
    val timeColWidth = GridTimeColWidth
        // Sticky Header: Court Columns
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
                .background(Color(0xFFF8FAFC))
                .border(1.dp, CardBorder, RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
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

}

@Composable
private fun GridSlotRow(
    slot: DaySlot,
    grid: DayGrid,
    canBook: Boolean,
    scrollState: ScrollState,
    onCellClick: (slotId: Long, slotTime: String, courtId: Long, courtName: String, price: Double) -> Unit,
    onBookingClick: (DayBooking) -> Unit,
    onBlockClick: (block: CourtBlock, courtName: String, slotTime: String) -> Unit,
) {
    val cellWidth = GridCellWidth
    val cellHeight = GridCellHeight
    val timeColWidth = GridTimeColWidth
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color.White)
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
                                        .pressableTile(cornerRadius = 10.dp) { onBookingClick(primaryBooking) }
                                        .width(cellWidth)
                                        .height(cellHeight)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(if (isWalkIn) Color(0xFFF0F9FF) else Color(0xFFEFF6FF))
                                        .border(1.dp, if (isWalkIn) Color(0xFFBAE6FD) else Color(0xFFBFDBFE), RoundedCornerShape(10.dp))
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
                                        .height(cellHeight)
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
                            } else if (cell?.block != null && !grid.isBlocked) {
                                // Taken off sale by the desk or by Haraan: say why, tap for details.
                                val block = cell.block
                                BlockedCell(block, cellWidth, cellHeight) {
                                    onBlockClick(block, court.name, slot.time ?: slot.label)
                                }
                            } else if (isBlocked) {
                                // Blocked / Unavailable
                                Box(
                                    modifier = Modifier
                                        .width(cellWidth)
                                        .height(cellHeight)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Color(0xFFF1F5F9))
                                        .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(10.dp)),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Text("CLOSED", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = MutedGray)
                                }
                            } else {
                                // Available Cell -> Click to add Walk-in!
                                AvailableCell(
                                    price = price,
                                    peak = cell?.isPeak == true,
                                    canBook = canBook,
                                    width = cellWidth,
                                    height = cellHeight,
                                ) { onCellClick(slot.slotId, slot.time ?: slot.label, court.id, court.name, price) }
                            }
                        }
                    }
                }
}

/**
 * A court-hour that was taken off sale: a pulled-down shutter, slate slats over the
 * cell, with the reason on top. Distinct from booked (blue), paying (amber) and open
 * (green) at a glance, and pressable, because the desk will want to know who and why.
 */
@Composable
private fun BlockedCell(block: CourtBlock, width: Dp, height: Dp, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .pressableTile(cornerRadius = 12.dp, onClick = onClick)
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF1F4F8))
            .border(1.dp, Color(0xFFD5DCE6), RoundedCornerShape(12.dp))
            .drawBehind {
                // Shutter slats, with the bottom rail a shade darker.
                val step = 7.dp.toPx()
                var y = step
                while (y < size.height - 4.dp.toPx()) {
                    drawLine(Color(0xFFE1E6EE), Offset(0f, y), Offset(size.width, y), strokeWidth = 1.dp.toPx())
                    y += step
                }
                drawRect(Color(0xFFCBD3DE), topLeft = Offset(0f, size.height - 3.dp.toPx()), size = Size(size.width, 3.dp.toPx()))
            }
            .padding(horizontal = 10.dp, vertical = 9.dp),
    ) {
        Column(Modifier.align(Alignment.TopStart)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(blockIcon(block.kind), contentDescription = null, tint = Color(0xFF475569), modifier = Modifier.size(13.dp))
                Spacer(Modifier.width(5.dp))
                Text("Blocked", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF475569), maxLines = 1)
            }
            Spacer(Modifier.height(5.dp))
            Text(
                block.label, fontSize = 13.sp, lineHeight = 15.sp, fontWeight = FontWeight.ExtraBold, color = InkDark,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            blockWindow(block)?.let {
                Text(it, fontSize = 10.5.sp, color = MutedGray, maxLines = 1)
            }
        }
    }
}

/** "6:00 AM – 8:00 AM" for a timed block; "All day" when it has no window. */
internal fun blockWindow(block: CourtBlock): String? {
    if (block.allDay) return "All day"
    fun fmt(hm: String?): String? {
        val p = hm?.split(":") ?: return null
        val h = p.getOrNull(0)?.toIntOrNull() ?: return null
        val m = p.getOrNull(1)?.take(2)?.toIntOrNull() ?: 0
        val h12 = when { h % 12 == 0 -> 12; else -> h % 12 }
        return "%d:%02d %s".format(h12, m, if (h in 12..23) "PM" else "AM")
    }
    val s = fmt(block.start) ?: return null
    val e = fmt(block.end) ?: return s
    return "$s – $e"
}

/** The picture for each block reason, shared by the grid and the sheets. */
internal fun blockIcon(kind: String): androidx.compose.ui.graphics.vector.ImageVector = when (kind) {
    "maintenance" -> Icons.Outlined.Build
    "private" -> Icons.Outlined.Lock
    "academy" -> Icons.Outlined.School
    "tournament" -> Icons.Outlined.EmojiEvents
    "holiday" -> Icons.Outlined.EventBusy
    else -> Icons.Outlined.Block
}

/**
 * An open court at an open time: the cell the desk taps most.
 *
 * It said AVAILABLE in 8sp caps, ₹ in 13sp and "+ Walk-in" in 8.5sp, and at real
 * font sizes that last line was clipped to a lone "+". The price is what the
 * desk reads to the customer, so it leads at 20sp. The status is a small dot and
 * word, and the action is a real round "+" in the corner, in the app's one action
 * blue (green here only means open), big enough to be a target, not a hint.
 */
@Composable
private fun AvailableCell(
    price: Double,
    peak: Boolean,
    canBook: Boolean,
    width: Dp,
    height: Dp,
    onClick: () -> Unit,
) {
    Box(
        modifier = Modifier
            .pressableTile(cornerRadius = 12.dp, enabled = canBook, onClick = onClick)
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF2FBF5))
            .border(1.dp, Color(0xFFC7EBD3), RoundedCornerShape(12.dp))
            .padding(horizontal = 10.dp, vertical = 9.dp),
    ) {
        Column(Modifier.align(Alignment.TopStart)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(GreenColor))
                Spacer(Modifier.width(5.dp))
                Text(
                    if (peak) "Open · Peak" else "Open",
                    fontSize = 11.sp, fontWeight = FontWeight.Bold,
                    color = if (peak) Color(0xFFB45309) else Color(0xFF15803D),
                    maxLines = 1,
                )
            }
            Spacer(Modifier.height(6.dp))
            Text(
                "₹" + price.toInt(),
                fontSize = 20.sp, lineHeight = 22.sp,
                fontWeight = FontWeight.ExtraBold, color = InkDark,
                letterSpacing = (-0.3).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
                maxLines = 1,
            )
        }
        if (canBook) {
            Box(
                Modifier.align(Alignment.BottomEnd)
                    .size(26.dp)
                    .clip(RoundedCornerShape(99.dp))
                    .background(PrimaryBlue),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Add, contentDescription = "Add walk-in", tint = Color.White, modifier = Modifier.size(16.dp))
            }
        }
    }
}
