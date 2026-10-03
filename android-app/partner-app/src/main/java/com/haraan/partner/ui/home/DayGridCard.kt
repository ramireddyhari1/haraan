package com.haraan.partner.ui.home

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
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
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathOperation
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import kotlinx.coroutines.delay

// Haraan blue, ramped like the launcher icon and the bottom bar.
private val BrandLight = Color(0xFF4D8BFF)
private val Brand = Color(0xFF2563EB)
private val BrandDeep = Color(0xFF1E40AF)
private val BrandInk = Color(0xFF1D4ED8)
private val OnBlue = Color.White
private val OnBlueSoft = Color(0xD9FFFFFF)
private val OnBlueQuiet = Color(0x99FFFFFF)

/**
 * The venue's board, built to be read in a couple of seconds:
 *  1. what's on court right now — drawn, not described;
 *  2. what's left to sell, in rupees as well as hours;
 *  3. the day, courts down and hours across, split into morning / afternoon /
 *     evening / night so seventeen columns read as four parts.
 *
 * A booked block carries the customer's initials and opens their details; an open
 * block goes to booking it. With [nowMinutes] it's today, live; without, a day ahead.
 */
@Composable
fun DayGridCard(
    label: String,
    hours: List<CourtHour>,
    nowMinutes: Int?,
    slotMinutes: Int,
    onBooked: (CellBooking, time: String, court: String?) -> Unit,
    onOpen: (time: String, court: String?) -> Unit,
    action: String?,
    onAction: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    fun past(h: CourtHour) = nowMinutes != null && h.start != Int.MAX_VALUE && nowMinutes >= h.start + slotMinutes
    fun isNow(h: CourtHour) = nowMinutes != null && h.start != Int.MAX_VALUE && nowMinutes >= h.start && nowMinutes < h.start + slotMinutes
    val ahead = hours.filterNot { past(it) }
    val open = ahead.sumOf { it.free }
    val booked = hours.sumOf { h -> h.cells.count { it == CellState.Booked } }
    // Rupees still sellable: each open court-hour ahead, at the rate it would charge.
    val sellable = ahead.sumOf { h -> h.cells.indices.sumOf { i -> if (h.cells[i] == CellState.Open) h.cellPrices.getOrElse(i) { h.price } else 0.0 } }
    val courts = hours.maxByOrNull { it.courts.size }?.courts.orEmpty()
    val rows = if (courts.isNotEmpty()) courts.size else (hours.maxOfOrNull { it.total } ?: 0)
    val current = hours.firstOrNull { isNow(it) }

    Column(
        modifier
            .fillMaxWidth()
            .shadow(14.dp, RoundedCornerShape(20.dp), clip = false, spotColor = Color(0x661E40AF), ambientColor = Color(0x331E40AF))
            .clip(RoundedCornerShape(20.dp))
            .background(Brush.linearGradient(listOf(BrandLight, Brand, BrandDeep), start = Offset.Zero, end = Offset(1100f, 1300f)))
            // Light from the top-left, as on the app icon.
            .drawBehind {
                drawCircle(
                    Brush.radialGradient(listOf(Color.White.copy(alpha = 0.18f), Color.Transparent), Offset(size.width * 0.1f, 0f), size.width * 0.7f),
                    radius = size.width * 0.7f, center = Offset(size.width * 0.1f, 0f),
                )
            }
            .border(1.dp, Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.35f), Color.White.copy(alpha = 0.06f))), RoundedCornerShape(20.dp))
            .padding(18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = OnBlueSoft, modifier = Modifier.weight(1f))
            if (nowMinutes != null) LiveTag()
        }
        Spacer(Modifier.height(8.dp))

        // 1 + 2: the number that matters, and the courts as they are this minute.
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        "$open", fontSize = 40.sp, lineHeight = 42.sp, fontWeight = FontWeight.Bold, color = OnBlue,
                        letterSpacing = (-1).sp, style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(
                        if (open == 1) "court-hour\nto sell" else "court-hours\nto sell",
                        fontSize = 13.5.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold, color = OnBlueSoft,
                        modifier = Modifier.padding(bottom = 5.dp),
                    )
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    when {
                        sellable > 0 && nowMinutes != null -> "₹" + inr(sellable) + " still to earn today"
                        sellable > 0 -> "₹" + inr(sellable) + " up for grabs"
                        booked > 0 -> "$booked booked · sold out"
                        else -> "Nothing on sale"
                    },
                    fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = OnBlue,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text(
                    if (booked == 0) "Nothing booked yet" else "$booked already booked",
                    fontSize = 12.sp, color = OnBlueQuiet,
                )
            }
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                NowCourts(
                    cells = current?.cells ?: List(rows.coerceAtLeast(1)) { CellState.Open },
                    live = current != null,
                    modifier = Modifier.size(width = 92.dp, height = 58.dp),
                )
                Spacer(Modifier.height(5.dp))
                Text(
                    nowLine(current, courts, nowMinutes, hours, slotMinutes),
                    fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = OnBlueSoft, maxLines = 1,
                    textAlign = TextAlign.Center, modifier = Modifier.width(110.dp), overflow = TextOverflow.Ellipsis,
                )
            }
        }
        Spacer(Modifier.height(16.dp))

        // 3: the day. Parts of the day ride above the hours.
        DayParts(hours, Modifier.fillMaxWidth().padding(start = 46.dp))
        Spacer(Modifier.height(6.dp))
        Box {
            Column {
                for (r in 0 until rows) {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                        Text(
                            courts.getOrNull(r)?.name ?: "Court ${r + 1}",
                            fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = OnBlueSoft,
                            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.width(46.dp),
                        )
                        Row(Modifier.weight(1f), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
                            hours.forEachIndexed { c, h ->
                                val court = courts.getOrNull(r)?.name
                                HourBlock(
                                    state = h.cells.getOrNull(r),
                                    booking = h.bookings.getOrNull(r),
                                    past = past(h),
                                    now = isNow(h),
                                    order = c + r,
                                    time = h.time,
                                    court = court,
                                    onBooked = { b -> onBooked(b, h.time, court) },
                                    onOpen = { onOpen(h.time, court) },
                                    modifier = Modifier.weight(1f),
                                )
                            }
                        }
                    }
                    if (r < rows - 1) Spacer(Modifier.height(5.dp))
                }
            }
        }
        // Hours: every third, and "Now" where it is.
        if (hours.isNotEmpty()) {
            Spacer(Modifier.height(6.dp))
            Row(Modifier.fillMaxWidth().padding(start = 46.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
                hours.forEachIndexed { i, h ->
                    val hr = if (h.start == Int.MAX_VALUE) -1 else h.start / 60
                    val show = isNow(h) || i == 0 || i == hours.lastIndex || (hr >= 0 && hr % 3 == 0 && h.start % 60 == 0)
                    Text(
                        when {
                            isNow(h) -> "Now"
                            show -> short(h.time)
                            else -> ""
                        },
                        fontSize = 9.5.sp, maxLines = 1, softWrap = false,
                        fontWeight = if (isNow(h)) FontWeight.Bold else FontWeight.Medium,
                        color = if (isNow(h)) OnBlue else OnBlueQuiet,
                        textAlign = TextAlign.Center, modifier = Modifier.weight(1f),
                    )
                }
            }
        }
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(9.dp).clip(RoundedCornerShape(2.dp)).background(OnBlue))
            Spacer(Modifier.width(5.dp))
            Text("Booked", fontSize = 11.sp, color = OnBlueSoft)
            Spacer(Modifier.width(12.dp))
            Box(Modifier.size(9.dp).clip(RoundedCornerShape(2.dp)).border(1.dp, Color(0x80FFFFFF), RoundedCornerShape(2.dp)))
            Spacer(Modifier.width(5.dp))
            Text("Open · tap to book", fontSize = 11.sp, color = OnBlueSoft)
        }
        if (action != null) {
            Spacer(Modifier.height(14.dp))
            val interaction = remember { MutableInteractionSource() }
            Text(
                action, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BrandInk,
                modifier = Modifier
                    .pressScale(interaction, pressedScale = 0.95f)
                    .shadow(6.dp, RoundedCornerShape(12.dp), clip = false, spotColor = Color(0x661E3A8A))
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color.White)
                    .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onAction() }
                    .padding(horizontal = 18.dp, vertical = 11.dp),
            )
        }
    }
}

/** A small pulsing dot and the word: this card is the day as it happens. */
@Composable
private fun LiveTag() {
    val t = rememberInfiniteTransition(label = "live")
    val ring by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1600), RepeatMode.Restart), label = "ring")
    Row(
        Modifier.clip(RoundedCornerShape(99.dp)).background(Color(0x26FFFFFF)).padding(horizontal = 8.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(8.dp)) {
            drawCircle(Color(0xFF86EFAC).copy(alpha = 1f - ring), radius = size.minDimension / 2 * (0.6f + 0.9f * ring))
            drawCircle(Color(0xFF4ADE80), radius = size.minDimension / 2 * 0.6f)
        }
        Spacer(Modifier.width(5.dp))
        Text("Live", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = OnBlue)
    }
}

/** "Right now: both courts free", "VADI in play", "Opens 6 AM". */
private fun nowLine(current: CourtHour?, courts: List<CourtTag>, now: Int?, hours: List<CourtHour>, len: Int): String {
    if (now == null) return hours.firstOrNull()?.let { "Opens " + short(it.time) } ?: "Closed"
    if (current == null) {
        val next = hours.firstOrNull { it.start != Int.MAX_VALUE && it.start > now }
        return if (next != null) "Opens " + short(next.time) else "Closed now"
    }
    val playing = current.cells.indices.filter { current.cells[it] == CellState.Booked }
    return when {
        playing.isEmpty() && current.cells.size > 1 -> if (current.cells.size == 2) "Both courts free" else "All courts free"
        playing.isEmpty() -> "Court free"
        playing.size == current.cells.size -> "All in play"
        playing.size == 1 -> (courts.getOrNull(playing[0])?.name ?: "1 court") + " in play"
        else -> "${playing.size} courts in play"
    }
}

/**
 * The courts as they are this minute, drawn small: each lane a court with its lines,
 * empty or with two players shifting on it. The one picture on the card, and it's live.
 */
@Composable
private fun NowCourts(cells: List<CellState>, live: Boolean, modifier: Modifier) {
    val t = rememberInfiniteTransition(label = "players")
    val bob by t.animateFloat(-1f, 1f, infiniteRepeatable(tween(950, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "bob")
    val ball by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1300, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "ball")
    Canvas(modifier) {
        val n = cells.size.coerceAtLeast(1)
        val gap = 4.dp.toPx()
        val w = (size.width - gap * (n - 1)) / n
        cells.forEachIndexed { i, state ->
            val r = Rect(i * (w + gap), 0f, i * (w + gap) + w, size.height)
            val playing = live && state == CellState.Booked
            drawRoundRect(
                if (playing) Color.White.copy(alpha = 0.95f) else Color.White.copy(alpha = 0.14f),
                r.topLeft, r.size, CornerRadius(6.dp.toPx()),
            )
            val line = if (playing) Color(0x591D4ED8) else Color(0x66FFFFFF)
            val inner = r.deflate(4.dp.toPx())
            val sw = 1.dp.toPx()
            drawRect(line, inner.topLeft, inner.size, style = Stroke(sw))
            drawLine(line, Offset(inner.left, inner.center.y), Offset(inner.right, inner.center.y), sw)
            drawCircle(line, inner.width * 0.18f, inner.center, style = Stroke(sw))
            if (playing) {
                val sway = bob * 1.4.dp.toPx()
                val a = Offset(inner.center.x - inner.width * 0.18f + sway, inner.top + inner.height * 0.25f)
                val b = Offset(inner.center.x + inner.width * 0.18f - sway, inner.bottom - inner.height * 0.25f)
                drawCircle(BrandInk, 3.dp.toPx(), a)
                drawCircle(BrandInk, 3.dp.toPx(), b)
                val p = Offset(a.x + (b.x - a.x) * ball, a.y + (b.y - a.y) * ball)
                drawCircle(Color(0xFFF59E0B), 1.8.dp.toPx(), p)
            }
        }
    }
}

private enum class Part(val label: String) { Morning("Morning"), Afternoon("Afternoon"), Evening("Evening"), Night("Night") }

private fun partOf(start: Int): Part = when (start) {
    in 0 until 12 * 60 -> if (start < 5 * 60) Part.Night else Part.Morning
    in 12 * 60 until 17 * 60 -> Part.Afternoon
    in 17 * 60 until 21 * 60 -> Part.Evening
    else -> Part.Night
}

/** Morning / Afternoon / Evening / Night over their columns, each with a drawn sky. */
@Composable
private fun DayParts(hours: List<CourtHour>, modifier: Modifier) {
    if (hours.isEmpty()) return
    val runs = mutableListOf<Pair<Part, Int>>()
    hours.forEach { h ->
        val p = partOf(if (h.start == Int.MAX_VALUE) 0 else h.start)
        if (runs.isNotEmpty() && runs.last().first == p) runs[runs.lastIndex] = p to runs.last().second + 1 else runs += p to 1
    }
    Row(modifier, horizontalArrangement = Arrangement.spacedBy(3.dp)) {
        runs.forEach { (part, count) ->
            Column(Modifier.weight(count.toFloat())) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Canvas(Modifier.size(width = 15.dp, height = 11.dp)) { sky(part) }
                    if (count >= 3) {
                        Spacer(Modifier.width(4.dp))
                        Text(part.label, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = OnBlueSoft, maxLines = 1, softWrap = false)
                    }
                }
                Spacer(Modifier.height(3.dp))
                Box(Modifier.fillMaxWidth().height(2.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x40FFFFFF)))
            }
        }
    }
}

/** Tiny sky glyphs in white: rising sun, sun, setting sun, crescent moon. */
private fun DrawScope.sky(part: Part) {
    val w = size.width
    val h = size.height
    val c = Color.White
    val sw = 1.2.dp.toPx()
    when (part) {
        Part.Morning -> {
            drawLine(c.copy(alpha = 0.7f), Offset(0f, h * 0.9f), Offset(w, h * 0.9f), sw, StrokeCap.Round)
            drawArc(c, 180f, 180f, true, Offset(w * 0.25f, h * 0.4f), Size(w * 0.5f, h))
        }
        Part.Afternoon -> {
            drawCircle(c, h * 0.32f, Offset(w / 2, h / 2))
            for (k in 0 until 8) {
                val a = Math.toRadians(k * 45.0)
                val o = Offset(w / 2 + (h * 0.42f) * Math.cos(a).toFloat(), h / 2 + (h * 0.42f) * Math.sin(a).toFloat())
                val e = Offset(w / 2 + (h * 0.56f) * Math.cos(a).toFloat(), h / 2 + (h * 0.56f) * Math.sin(a).toFloat())
                drawLine(c, o, e, sw * 0.8f, StrokeCap.Round)
            }
        }
        Part.Evening -> {
            drawArc(Color(0xFFFDBA74), 180f, 180f, true, Offset(w * 0.25f, h * 0.45f), Size(w * 0.5f, h * 0.9f))
            drawLine(c.copy(alpha = 0.7f), Offset(0f, h * 0.9f), Offset(w, h * 0.9f), sw, StrokeCap.Round)
        }
        Part.Night -> {
            val m = Offset(w / 2, h / 2)
            val rr = h * 0.42f
            val moon = Path().apply { addOval(Rect(m, rr)) }
            val bite = Path().apply { addOval(Rect(Offset(m.x + rr * 0.55f, m.y - rr * 0.35f), rr * 0.9f)) }
            drawPath(Path().apply { op(moon, bite, PathOperation.Difference) }, c)
        }
    }
}

@Composable
private fun HourBlock(
    state: CellState?,
    booking: CellBooking?,
    past: Boolean,
    now: Boolean,
    order: Int,
    time: String,
    court: String?,
    onBooked: (CellBooking) -> Unit,
    onOpen: () -> Unit,
    modifier: Modifier,
) {
    val view = LocalView.current
    val pop = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        delay(80L + order * 18L)
        pop.animateTo(1f, spring(dampingRatio = 0.9f, stiffness = Spring.StiffnessMediumLow))
    }
    val glow = if (now) {
        rememberInfiniteTransition(label = "now").animateFloat(0.55f, 1f, infiniteRepeatable(tween(1400), RepeatMode.Reverse), label = "glow").value
    } else 0f
    val interaction = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(4.dp)
    val tappable = (state == CellState.Booked && booking != null) || (state == CellState.Open && !past)
    Box(
        modifier
            .height(28.dp)
            .graphicsLayer {
                val sc = 0.92f + 0.08f * pop.value
                scaleX = sc; scaleY = sc
                alpha = pop.value.coerceIn(0f, 1f) * if (past) 0.38f else 1f
            }
            .pressScale(interaction, pressedScale = 0.88f)
            .clip(shape)
            .then(
                when (state) {
                    CellState.Booked -> Modifier.background(Color.White)
                    CellState.Held -> Modifier.background(Color(0xFFFCD34D))
                    CellState.Open -> Modifier
                        .background(Color.White.copy(alpha = if (now) 0.16f + 0.18f * glow else 0.12f))
                        .border(if (now) 1.5.dp else 1.dp, Color.White.copy(alpha = if (now) 0.9f else 0.4f), shape)
                    null -> Modifier.background(Color(0x0DFFFFFF))
                }
            )
            .clickable(interactionSource = interaction, indication = null, enabled = tappable) {
                Haptics.tick(view)
                if (state == CellState.Booked && booking != null) onBooked(booking) else onOpen()
            }
            .semantics {
                contentDescription = "${court ?: "Court"} at $time: " + when (state) {
                    CellState.Booked -> "booked by ${booking?.customer ?: "a customer"}"
                    CellState.Held -> "someone is paying"
                    CellState.Open -> if (past) "played, not booked" else "open, tap to book"
                    null -> "not sold"
                }
            },
        contentAlignment = Alignment.Center,
    ) {
        if (state == CellState.Booked && booking != null) {
            Text(initials(booking.customer), fontSize = 9.sp, fontWeight = FontWeight.Bold, color = BrandInk, maxLines = 1, softWrap = false)
        }
    }
}

private fun inr(v: Double): String {
    val n = kotlin.math.round(v).toLong()
    val s = n.toString()
    if (s.length <= 3) return s
    val last3 = s.takeLast(3)
    val rest = s.dropLast(3).reversed().chunked(2).joinToString(",").reversed()
    return "$rest,$last3"
}

/** "Hariharan Reddy" → "HR"; one word → its first two letters. */
private fun initials(name: String): String {
    val parts = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
    return when {
        parts.isEmpty() -> "•"
        parts.size == 1 -> parts[0].take(2).uppercase()
        else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
    }
}

/** "6:00 AM" → "6 AM". */
private fun short(t: String): String = t.replace(":00", "").trim()
