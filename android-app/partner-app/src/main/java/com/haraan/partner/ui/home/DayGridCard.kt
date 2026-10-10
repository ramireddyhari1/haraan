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
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
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
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.Layout
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Constraints
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import kotlinx.coroutines.delay

// Paper and ink: the board is a register on the desk, not a promo card.
private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hair = Color(0x140F172A)
private val Brand = Color(0xFF2563EB)
private val BrandInk = Color(0xFF1D4ED8)
private val BookedPast = Color(0xFFA9C0F2)
private val OpenFill = Color(0xFFF1F5FD)
private val OpenEdge = Color(0xFFD3DFF3)
private val GoneFill = Color(0xFFF5F6F8)
private val GoneHatch = Color(0xFFE3E7EC)
private val Held = Color(0xFFFDE68A)
private val Live = Color(0xFF16A34A)

private val LabelW = 46.dp
private val Gap = 3.dp

/**
 * Rupees still sellable today: each open court-hour that hasn't started, at the rate
 * it would charge. Home's sentence uses it so the board doesn't have to repeat it.
 */
fun sellableAhead(hours: List<CourtHour>, nowMinutes: Int, slotMinutes: Int): Double =
    hours.filterNot { it.start != Int.MAX_VALUE && nowMinutes >= it.start + slotMinutes }
        .sumOf { h -> h.cells.indices.sumOf { i -> if (h.cells[i] == CellState.Open) h.cellPrices.getOrElse(i) { h.price } else 0.0 } }

/**
 * The venue's day as a register on the counter: courts down, hours across.
 *  - On top, the courts as they are this minute, drawn with their real markings.
 *  - Booked hours are solid ink with the customer's initials; hours already gone are
 *    hatched out, so the eye only reads what's still sellable.
 *  - A line marks the exact minute, the way a calendar does.
 *  - Slide a finger along the board and it reads each hour out, with a tick per hour.
 *
 * A booked block opens its customer; an open block goes to booking it. With
 * [nowMinutes] it's today, live; without, a day ahead.
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
    val courts = hours.maxByOrNull { it.courts.size }?.courts.orEmpty()
    val rows = if (courts.isNotEmpty()) courts.size else (hours.maxOfOrNull { it.total } ?: 0)
    val current = hours.firstOrNull { isNow(it) }
    val nowCol = hours.indexOfFirst { isNow(it) }
    var scrub by remember { mutableStateOf<Int?>(null) }

    Column(
        modifier
            .fillMaxWidth()
            .shadow(8.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x1A0F172A), ambientColor = Color(0x0F0F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(18.dp))
            .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted, modifier = Modifier.weight(1f))
            if (nowMinutes != null) LiveClock(nowMinutes)
        }
        Spacer(Modifier.height(12.dp))

        // The courts, this minute.
        Row(verticalAlignment = Alignment.CenterVertically) {
            NowCourts(
                cells = current?.cells ?: List(rows.coerceAtLeast(1)) { CellState.Open },
                kinds = courts.map { it.kind },
                live = current != null,
                modifier = Modifier.size(width = (44 * rows.coerceIn(1, 4)).dp.coerceAtMost(132.dp), height = 66.dp),
            )
            Spacer(Modifier.width(14.dp))
            val (head, sub) = nowWords(current, courts, nowMinutes, hours, slotMinutes)
            Column(Modifier.weight(1f)) {
                Text(head, fontSize = 17.sp, lineHeight = 21.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 2, overflow = TextOverflow.Ellipsis)
                sub?.let {
                    Spacer(Modifier.height(2.dp))
                    Text(it, fontSize = 13.sp, lineHeight = 17.sp, color = Muted, maxLines = 2, overflow = TextOverflow.Ellipsis)
                }
            }
        }
        Spacer(Modifier.height(18.dp))

        // The day. Parts of the day ride above the hours, in words.
        DayParts(hours, Modifier.fillMaxWidth().padding(start = LabelW))
        Spacer(Modifier.height(6.dp))
        val density = LocalDensity.current
        Box(
            Modifier.pointerInput(hours.size) {
                // Slide along the board to read it hour by hour.
                val labelPx = with(density) { LabelW.toPx() }
                val gapPx = with(density) { Gap.toPx() }
                fun colAt(x: Float): Int? {
                    if (hours.isEmpty()) return null
                    val cw = (size.width - labelPx - gapPx * (hours.size - 1)) / hours.size
                    return ((x - labelPx) / (cw + gapPx)).toInt().coerceIn(0, hours.lastIndex)
                }
                detectHorizontalDragGestures(
                    onDragStart = { o -> scrub = colAt(o.x); Haptics.tick(view) },
                    onDragEnd = { scrub = null },
                    onDragCancel = { scrub = null },
                ) { change, _ ->
                    change.consume()
                    val c = colAt(change.position.x)
                    if (c != scrub) { scrub = c; Haptics.tick(view) }
                }
            },
        ) {
            Column {
                for (r in 0 until rows) {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                        Text(
                            courts.getOrNull(r)?.name ?: "Court ${r + 1}",
                            fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.width(LabelW).padding(end = 6.dp),
                        )
                        Row(Modifier.weight(1f), horizontalArrangement = Arrangement.spacedBy(Gap)) {
                            hours.forEachIndexed { c, h ->
                                val court = courts.getOrNull(r)?.name
                                HourBlock(
                                    state = h.cells.getOrNull(r),
                                    booking = h.bookings.getOrNull(r),
                                    past = past(h),
                                    order = c + r,
                                    time = h.time,
                                    court = court,
                                    lifted = scrub == c,
                                    onBooked = { b -> onBooked(b, h.time, court) },
                                    onOpen = { onOpen(h.time, court) },
                                    modifier = Modifier.weight(1f),
                                )
                            }
                        }
                    }
                    if (r < rows - 1) Spacer(Modifier.height(4.dp))
                }
            }
            // The minute we're in, as a line through every court.
            if (nowCol >= 0 && nowMinutes != null) {
                val h = hours[nowCol]
                val frac = ((nowMinutes - h.start).toFloat() / slotMinutes).coerceIn(0f, 1f)
                NowLine(col = nowCol, frac = frac, count = hours.size, modifier = Modifier.matchParentSize())
            }
        }
        if (hours.isNotEmpty()) {
            Spacer(Modifier.height(5.dp))
            HourLabels(hours, Modifier.fillMaxWidth().padding(start = LabelW))
        }
        Spacer(Modifier.height(12.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
        Spacer(Modifier.height(10.dp))
        // The legend, or what's under the finger while it slides.
        val s = scrub
        if (s != null && s in hours.indices) {
            Text(
                readout(hours[s], courts, past(hours[s])),
                fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        } else {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(10.dp).clip(RoundedCornerShape(2.dp)).background(Brand))
                Spacer(Modifier.width(5.dp))
                Text("Booked", fontSize = 11.5.sp, color = Muted)
                Spacer(Modifier.width(12.dp))
                Box(Modifier.size(10.dp).clip(RoundedCornerShape(2.dp)).background(OpenFill).border(1.dp, OpenEdge, RoundedCornerShape(2.dp)))
                Spacer(Modifier.width(5.dp))
                Text("Open", fontSize = 11.5.sp, color = Muted)
                Spacer(Modifier.weight(1f))
                Text("Slide to read hours", fontSize = 11.5.sp, color = Faint)
            }
        }
        if (action != null) {
            Spacer(Modifier.height(14.dp))
            val interaction = remember { MutableInteractionSource() }
            Text(
                action, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Color.White,
                modifier = Modifier
                    .pressScale(interaction, pressedScale = 0.95f)
                    .clip(RoundedCornerShape(12.dp))
                    .background(Brand)
                    .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onAction() }
                    .padding(horizontal = 18.dp, vertical = 11.dp),
            )
        }
    }
}

/** A green dot and the time, ticking with the board: this is the day as it happens. */
@Composable
private fun LiveClock(now: Int) {
    val t = rememberInfiniteTransition(label = "live")
    val ring by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1800), RepeatMode.Restart), label = "ring")
    Row(verticalAlignment = Alignment.CenterVertically) {
        Canvas(Modifier.size(8.dp)) {
            drawCircle(Live.copy(alpha = 0.35f * (1f - ring)), radius = size.minDimension / 2 * (0.6f + 0.9f * ring))
            drawCircle(Live, radius = size.minDimension / 2 * 0.6f)
        }
        Spacer(Modifier.width(6.dp))
        Text(clockOf(now), fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
    }
}

/**
 * What the courts are doing now, in two lines: who's on, and until when.
 * "Both courts free / Free till close", "VADI in play / padi free till 7 PM".
 */
private fun nowWords(current: CourtHour?, courts: List<CourtTag>, now: Int?, hours: List<CourtHour>, len: Int): Pair<String, String?> {
    val open = hours.filterNot { now != null && it.start != Int.MAX_VALUE && now >= it.start + len }.sumOf { it.free }
    val booked = hours.sumOf { h -> h.cells.count { it == CellState.Booked } }
    val tally = listOfNotNull(
        if (open > 0) (if (open == 1) "1 court-hour open" else "$open court-hours open") else null,
        if (booked > 0) "$booked booked" else null,
    ).joinToString(" · ").ifBlank { null }
    if (now == null) return (hours.firstOrNull()?.let { "Opens " + short(it.time) } ?: "Closed") to tally
    if (current == null) {
        val next = hours.firstOrNull { it.start != Int.MAX_VALUE && it.start > now }
        return (if (next != null) "Opens " + short(next.time) else "Closed now") to tally
    }
    val n = current.cells.size
    val playing = current.cells.indices.filter { current.cells[it] == CellState.Booked }
    // The next booked hour on any court, after this one.
    val nextBooked = hours.firstOrNull { it.start != Int.MAX_VALUE && it.start > current.start && it.cells.any { c -> c == CellState.Booked } }
    val freeTill = nextBooked?.let { "till " + short(it.time) } ?: "till close"
    fun name(i: Int) = courts.getOrNull(i)?.name ?: "Court ${i + 1}"
    return when {
        playing.isEmpty() -> (if (n > 2) "All courts free" else if (n == 2) "Both courts free" else "Court free") to "Free $freeTill"
        playing.size == n -> (if (n == 1) "${name(0)} in play" else "All courts in play") to "Ends ${clockOf(current.start + len)}"
        playing.size == 1 -> "${name(playing[0])} in play" to (current.cells.indices.firstOrNull { it !in playing }?.let { "${name(it)} free $freeTill" })
        else -> "${playing.size} of $n courts in play" to "Ends ${clockOf(current.start + len)}"
    }
}

/** "7 PM · VADI booked by HR ₹500 · padi open ₹400". */
private fun readout(h: CourtHour, courts: List<CourtTag>, past: Boolean): String {
    val parts = h.cells.indices.map { i ->
        val name = courts.getOrNull(i)?.name ?: "Court ${i + 1}"
        val price = h.cellPrices.getOrElse(i) { h.price }
        when (h.cells[i]) {
            CellState.Booked -> "$name · " + (h.bookings.getOrNull(i)?.customer?.substringBefore(' ') ?: "booked")
            CellState.Held -> "$name · being paid"
            CellState.Open -> if (past) "$name · went unsold" else "$name · open ₹" + inr(price)
        }
    }
    return (listOf(short(h.time)) + parts).joinToString("   ")
}

/**
 * The courts as they are this minute, drawn with their sport's markings (the same
 * hand as the walk-in sheet's map): bare turf when free, filled in Haraan blue with
 * two players when someone is on it.
 */
@Composable
private fun NowCourts(cells: List<CellState>, kinds: List<CourtKind>, live: Boolean, modifier: Modifier) {
    val t = rememberInfiniteTransition(label = "players")
    val bob by t.animateFloat(-1f, 1f, infiniteRepeatable(tween(950, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "bob")
    Canvas(modifier) {
        val n = cells.size.coerceAtLeast(1)
        val gap = 5.dp.toPx()
        val w = (size.width - gap * (n - 1)) / n
        val radius = CornerRadius(7.dp.toPx())
        cells.forEachIndexed { i, state ->
            val kind = kinds.getOrElse(i) { kinds.firstOrNull() ?: CourtKind.Plain }
            val lane = Rect(i * (w + gap), 0f, i * (w + gap) + w, size.height)
            val grass = kind == CourtKind.Football || kind == CourtKind.Cricket
            val playing = live && state == CellState.Booked
            when {
                playing -> drawRoundRect(
                    Brush.verticalGradient(listOf(Color(0xFF4A80FF), Color(0xFF1E40AF)), startY = lane.top, endY = lane.bottom),
                    lane.topLeft, lane.size, radius,
                )
                state == CellState.Held && live -> {
                    drawRoundRect(Color(0xFFFFF1DB), lane.topLeft, lane.size, radius)
                    clipRect(lane.left, lane.top, lane.right, lane.bottom) { hatch(lane, Color(0x66D97706)) }
                }
                else -> {
                    drawRoundRect(if (grass) Color(0xFFE3F4E8) else Color(0xFFE8F0FB), lane.topLeft, lane.size, radius)
                    if (grass) mownBands(lane, Color(0xFFD6EEDD))
                }
            }
            val line = when {
                playing -> Color(0x59FFFFFF)
                grass -> Color(0xFF9FD1AE)
                else -> Color(0xFFA9BFE6)
            }
            upright(lane) { r ->
                markings(r.deflate(4.dp.toPx()), kind, line)
                if (playing) players(r, kind, bob)
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

/** Morning / Afternoon / Evening / Night over their columns, as words on a rule. */
@Composable
private fun DayParts(hours: List<CourtHour>, modifier: Modifier) {
    if (hours.isEmpty()) return
    val runs = mutableListOf<Pair<Part, Int>>()
    hours.forEach { h ->
        val p = partOf(if (h.start == Int.MAX_VALUE) 0 else h.start)
        if (runs.isNotEmpty() && runs.last().first == p) runs[runs.lastIndex] = p to runs.last().second + 1 else runs += p to 1
    }
    Row(modifier, horizontalArrangement = Arrangement.spacedBy(Gap)) {
        runs.forEach { (part, count) ->
            Column(Modifier.weight(count.toFloat())) {
                Text(
                    if (count >= 3) part.label else "",
                    fontSize = 10.5.sp, fontWeight = FontWeight.Medium, color = Muted, maxLines = 1, softWrap = false,
                )
                Spacer(Modifier.height(3.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0x1F0F172A)))
            }
        }
    }
}

/**
 * Clock hours under the board, each set on the edge where its hour begins. Labels are
 * laid out at their own width — never squeezed into one column, which is what cut
 * "6 AM" down to "6 A" — and a label that would crowd the last one is skipped.
 */
@Composable
private fun HourLabels(hours: List<CourtHour>, modifier: Modifier) {
    val picks = hours.indices.filter { i ->
        val s = hours[i].start
        s != Int.MAX_VALUE && s % 60 == 0 && (s / 60) % 3 == 0
    }.ifEmpty { listOf(0) }
    Layout(
        modifier = modifier,
        content = {
            picks.forEach { i ->
                Text(short(hours[i].time), fontSize = 10.sp, fontWeight = FontWeight.Medium, color = Faint, maxLines = 1, softWrap = false)
            }
        },
    ) { measurables, constraints ->
        val placeables = measurables.map { it.measure(Constraints()) }
        val width = constraints.maxWidth
        val gap = Gap.roundToPx()
        val cw = (width - gap * (hours.size - 1)).toFloat() / hours.size
        val height = placeables.maxOfOrNull { it.height } ?: 0
        layout(width, height) {
            var lastRight = Int.MIN_VALUE
            placeables.forEachIndexed { k, p ->
                val edge = picks[k] * (cw + gap)
                val x = (edge - p.width / 2f).toInt().coerceIn(0, (width - p.width).coerceAtLeast(0))
                if (x >= lastRight + 6) {
                    p.place(x, 0)
                    lastRight = x + p.width
                }
            }
        }
    }
}

/** The current minute as a thin line through every court, with a head on top. */
@Composable
private fun NowLine(col: Int, frac: Float, count: Int, modifier: Modifier) {
    Canvas(modifier) {
        val label = LabelW.toPx()
        val gap = Gap.toPx()
        val cw = (size.width - label - gap * (count - 1)) / count
        val x = label + col * (cw + gap) + cw * frac
        val sw = 2.dp.toPx()
        drawLine(Ink, Offset(x, -3.dp.toPx()), Offset(x, size.height + 2.dp.toPx()), sw)
        drawCircle(Color.White, 4.5.dp.toPx(), Offset(x, -3.dp.toPx()))
        drawCircle(Ink, 3.dp.toPx(), Offset(x, -3.dp.toPx()))
    }
}

@Composable
private fun HourBlock(
    state: CellState?,
    booking: CellBooking?,
    past: Boolean,
    order: Int,
    time: String,
    court: String?,
    lifted: Boolean,
    onBooked: (CellBooking) -> Unit,
    onOpen: () -> Unit,
    modifier: Modifier,
) {
    val view = LocalView.current
    val pop = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        delay(60L + order * 14L)
        pop.animateTo(1f, spring(dampingRatio = 0.9f, stiffness = Spring.StiffnessMediumLow))
    }
    val lift = remember { Animatable(0f) }
    LaunchedEffect(lifted) { lift.animateTo(if (lifted) 1f else 0f, spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessMedium)) }
    val interaction = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(4.dp)
    val tappable = (state == CellState.Booked && booking != null) || (state == CellState.Open && !past)
    Box(
        modifier
            .height(26.dp)
            .graphicsLayer {
                val sc = (0.92f + 0.08f * pop.value) * (1f + 0.12f * lift.value)
                scaleX = sc; scaleY = sc
                translationY = -2.dp.toPx() * lift.value
                alpha = pop.value.coerceIn(0f, 1f)
            }
            .pressScale(interaction, pressedScale = 0.88f)
            .clip(shape)
            .then(
                when {
                    state == CellState.Booked -> Modifier.background(if (past) BookedPast else Brand)
                    state == CellState.Held -> Modifier.background(Held)
                    state == CellState.Open && past -> Modifier.background(GoneFill).drawBehind {
                        clipRect { hatch(Rect(Offset.Zero, size), GoneHatch) }
                    }
                    state == CellState.Open -> Modifier.background(OpenFill).border(1.dp, if (lifted) Brand else OpenEdge, shape)
                    else -> Modifier.background(Color(0xFFFAFBFC))
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
            Text(initials(booking.customer), fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1, softWrap = false)
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

/** 1302 → "9:42 PM"; whole hours drop the ":00". */
private fun clockOf(min: Int): String {
    val m = ((min % 1440) + 1440) % 1440
    val h = m / 60
    val mm = m % 60
    val h12 = if (h % 12 == 0) 12 else h % 12
    val ap = if (h < 12) "AM" else "PM"
    return if (mm == 0) "$h12 $ap" else "$h12:" + mm.toString().padStart(2, '0') + " $ap"
}

/** "6:00 AM" → "6 AM". */
private fun short(t: String): String = t.replace(":00", "").trim()
