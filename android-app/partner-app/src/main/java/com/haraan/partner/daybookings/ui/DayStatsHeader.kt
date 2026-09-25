package com.haraan.partner.daybookings.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Block
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.daybookings.model.DaySummaryStats
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlin.math.roundToInt

private val PrimaryBlue = Color(0xFF1D4ED8)
private val BrandBlue = Color(0xFF2F6BFF)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val RedColor = Color(0xFFDC2626)
private val Hairline = Color(0x140F172A)
private val CardBorder = Color(0x0F0F172A)

/**
 * The day at the desk, in one card.
 *
 * It was a ring beside two loose money labels, above three bordered boxes reading
 * "6 Total Slots / 0 Booked / 6 Available" — the boxed-count-card look, and the
 * money had no sense of progress. Now it reads top to bottom the way the desk
 * thinks: how full the courts are, what the day is worth, how much of that is
 * already in hand, and what is still owed. The owed line is the one thing here
 * you can act on, so it's the one thing that presses: it opens the unpaid list.
 *
 * Every figure moves to its new value rather than jumping, so flicking between
 * days reads as the day changing, not as the screen reloading.
 */
@Composable
fun DayStatsHeader(
    stats: DaySummaryStats,
    onReopenDay: () -> Unit,
    onCloseDay: () -> Unit,
    canManage: Boolean = true,
    /** Opens the Unpaid / Due filter — where the money being chased lives. */
    onShowDue: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .shadow(12.dp, RoundedCornerShape(22.dp), clip = false, spotColor = Color(0x1F0F172A))
            .clip(RoundedCornerShape(22.dp))
            .background(Color.White)
            .border(1.dp, CardBorder, RoundedCornerShape(22.dp))
            .padding(18.dp),
    ) {
        if (stats.isBlocked) {
            ClosedDayBanner(canManage = canManage, onReopenDay = onReopenDay)
            Spacer(Modifier.height(16.dp))
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
            OccupancyGauge(stats.occupancyRate)
            Spacer(Modifier.width(18.dp))
            MoneyBlock(stats, Modifier.weight(1f))
        }

        AnimatedVisibility(
            visible = stats.pendingDue > 0,
            enter = fadeIn(tween(220)) + expandVertically(tween(260)),
            exit = fadeOut(tween(160)) + shrinkVertically(tween(220)),
        ) {
            Column {
                Spacer(Modifier.height(14.dp))
                DueRow(amount = stats.pendingDue, count = stats.chaseCount, onClick = onShowDue)
            }
        }

        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        Spacer(Modifier.height(14.dp))

        // Three counts as one strip split by hairlines, not three boxes.
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            SlotCount(Modifier.weight(1f), stats.totalSlots, "Slots", InkDark)
            Box(Modifier.width(1.dp).height(30.dp).background(Hairline))
            SlotCount(Modifier.weight(1f), stats.bookedSlots, "Booked", if (stats.bookedSlots > 0) PrimaryBlue else InkDark)
            Box(Modifier.width(1.dp).height(30.dp).background(Hairline))
            SlotCount(
                Modifier.weight(1f), stats.availableSlots, "Open",
                if (stats.availableSlots > 0) GreenColor else MutedGray,
                dot = stats.availableSlots > 0,
            )
        }
    }
}

/**
 * What the day is worth, and how much of it is already in hand.
 *
 * The bar is collected over expected: a day that's fully paid up looks full, a
 * day of walk-ins who haven't paid yet looks it too. It only draws once there is
 * money expected — a bar over ₹0 says nothing.
 */
@Composable
private fun MoneyBlock(stats: DaySummaryStats, modifier: Modifier) {
    val expected by animateFloatAsState(stats.expectedRevenue.toFloat(), tween(650, easing = FastOutSlowInEasing), label = "expected")
    val collected by animateFloatAsState(stats.collectedRevenue.toFloat(), tween(650, easing = FastOutSlowInEasing), label = "collected")
    val share = if (stats.expectedRevenue > 0) (stats.collectedRevenue / stats.expectedRevenue).toFloat().coerceIn(0f, 1f) else 0f
    val fill by animateFloatAsState(share, tween(750, easing = FastOutSlowInEasing), label = "collected-share")

    Column(modifier) {
        Text("EXPECTED", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MutedGray, letterSpacing = 1.2.sp)
        Spacer(Modifier.height(3.dp))
        Text(
            "₹" + formatInr(expected.toDouble()),
            fontSize = 27.sp, lineHeight = 30.sp,
            fontWeight = FontWeight.ExtraBold, color = InkDark, letterSpacing = (-0.7).sp,
            style = TextStyle(fontFeatureSettings = "tnum, zero"),
            maxLines = 1,
        )
        Spacer(Modifier.height(10.dp))
        if (stats.expectedRevenue > 0) {
            Box(
                Modifier.fillMaxWidth().height(6.dp)
                    .clip(RoundedCornerShape(99.dp))
                    .background(Color(0xFFEEF2F7)),
            ) {
                if (fill > 0f) {
                    Box(
                        Modifier.fillMaxWidth(fill).fillMaxHeight()
                            .clip(RoundedCornerShape(99.dp))
                            .background(Brush.horizontalGradient(listOf(Color(0xFF34D399), GreenColor))),
                    )
                }
            }
            Spacer(Modifier.height(7.dp))
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "₹" + formatInr(collected.toDouble()),
                fontSize = 13.sp, fontWeight = FontWeight.ExtraBold,
                color = if (stats.collectedRevenue > 0) GreenColor else InkDark,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(" collected", fontSize = 12.5.sp, color = MutedGray)
        }
        collectedSplit(stats)?.let { split ->
            Spacer(Modifier.height(2.dp))
            Text(split, fontSize = 11.5.sp, color = MutedGray, maxLines = 1)
        }
    }
}

/** "Cash ₹400 · UPI ₹200" — only the methods that actually took money. */
private fun collectedSplit(stats: DaySummaryStats): String? {
    val parts = listOfNotNull(
        stats.cashCollected.takeIf { it > 0 }?.let { "Cash ₹" + formatInr(it) },
        stats.upiCollected.takeIf { it > 0 }?.let { "UPI ₹" + formatInr(it) },
        stats.onlineCollected.takeIf { it > 0 }?.let { "Online ₹" + formatInr(it) },
    )
    // One method is already said by the total; the split only helps with two or more.
    return if (parts.size >= 2) parts.joinToString(" · ") else null
}

/** Money still owed on this day's bookings. Presses, and opens the unpaid list. */
@Composable
private fun DueRow(amount: Double, count: Int, onClick: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .pressScale(interaction, pressedScale = 0.97f)
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFFEF2F2))
            .border(1.dp, Color(0xFFFECACA), RoundedCornerShape(14.dp))
            .pressShade(interaction, amount = 0.04f)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                onClick()
            }
            .padding(horizontal = 14.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(8.dp).clip(RoundedCornerShape(99.dp)).background(RedColor))
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                "₹" + formatInr(amount) + " still to collect",
                fontSize = 13.5.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFFB91C1C),
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            if (count > 0) {
                Text(
                    if (count == 1) "1 booking unpaid" else "$count bookings unpaid",
                    fontSize = 11.5.sp, color = RedColor.copy(alpha = 0.8f),
                )
            }
        }
        Text("View", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFFB91C1C))
        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB91C1C), modifier = Modifier.size(18.dp))
    }
}

@Composable
private fun SlotCount(modifier: Modifier, value: Int, label: String, tint: Color, dot: Boolean = false) {
    val shown by animateIntAsState(value, tween(500, easing = FastOutSlowInEasing), label = "slot-count-$label")
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            "$shown", fontSize = 20.sp, lineHeight = 22.sp,
            fontWeight = FontWeight.ExtraBold, color = tint,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(2.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (dot) {
                Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(GreenColor))
                Spacer(Modifier.width(5.dp))
            }
            Text(label, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = MutedGray)
        }
    }
}

@Composable
private fun ClosedDayBanner(canManage: Boolean, onReopenDay: () -> Unit) {
    val view = LocalView.current
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFFEF2F2))
            .border(1.dp, Color(0xFFFECACA), RoundedCornerShape(14.dp))
            .padding(start = 14.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.Block, contentDescription = null, tint = RedColor, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(8.dp))
        Text(
            "Venue closed on this day",
            fontWeight = FontWeight.Bold, fontSize = 13.sp, color = RedColor,
            modifier = Modifier.weight(1f).padding(vertical = 6.dp),
        )
        if (canManage) {
            Text(
                "Reopen",
                color = PrimaryBlue, fontWeight = FontWeight.Bold, fontSize = 13.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(10.dp))
                    .clickable { Haptics.tick(view); onReopenDay() }
                    .padding(horizontal = 10.dp, vertical = 8.dp),
            )
        }
    }
}

/**
 * How full the day is, as a ring: a slate track, a round-capped brand-blue arc
 * that sweeps to its value, and the number over its label inside the hole.
 */
@Composable
private fun OccupancyGauge(rate: Float) {
    val target = rate.coerceIn(0f, 1f)
    val sweep by animateFloatAsState(target, tween(750, easing = FastOutSlowInEasing), label = "occupancy")
    val pct by animateIntAsState((target * 100).roundToInt(), tween(750, easing = FastOutSlowInEasing), label = "occupancy-pct")
    Box(Modifier.size(92.dp), contentAlignment = Alignment.Center) {
        Canvas(Modifier.fillMaxSize()) {
            val stroke = 9.dp.toPx()
            val inset = stroke / 2f
            val arcSize = Size(size.width - stroke, size.height - stroke)
            drawArc(
                color = Color(0xFFEEF2F7),
                startAngle = 0f, sweepAngle = 360f, useCenter = false,
                topLeft = Offset(inset, inset), size = arcSize,
                style = Stroke(stroke),
            )
            if (sweep > 0f) {
                drawArc(
                    brush = Brush.sweepGradient(listOf(Color(0xFF93C5FD), BrandBlue, PrimaryBlue, Color(0xFF93C5FD))),
                    startAngle = -90f, sweepAngle = 360f * sweep, useCenter = false,
                    topLeft = Offset(inset, inset), size = arcSize,
                    style = Stroke(stroke, cap = StrokeCap.Round),
                )
            }
        }
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    "$pct",
                    fontSize = 24.sp, lineHeight = 24.sp,
                    fontWeight = FontWeight.ExtraBold, color = InkDark,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text(
                    "%",
                    fontSize = 12.sp, lineHeight = 12.sp,
                    fontWeight = FontWeight.Bold, color = MutedGray,
                    modifier = Modifier.padding(bottom = 3.dp, start = 1.dp),
                )
            }
            Spacer(Modifier.height(2.dp))
            Text("FULL", fontSize = 9.sp, lineHeight = 10.sp, fontWeight = FontWeight.Bold, color = MutedGray, letterSpacing = 1.2.sp)
        }
    }
}

private fun formatInr(amount: Double): String {
    val rounded = Math.round(amount)
    return java.text.NumberFormat.getNumberInstance(java.util.Locale.forLanguageTag("en-IN")).format(rounded)
}
