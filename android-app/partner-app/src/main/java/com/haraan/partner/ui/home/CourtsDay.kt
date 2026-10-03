package com.haraan.partner.ui.home

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.snapping.rememberSnapFlingBehavior
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
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
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressableTile
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import java.util.Calendar
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.sin

// Same values as PartnerApp's private palette, so the strip sits in the same family.
private val Ink = Color(0xFF0F172A)
private val InkTop = Color(0xFF0A1738)
private val InkMid = Color(0xFF0B1C46)
private val Accent = Color(0xFF2F6BFF)
private val AccentDeep = Color(0xFF1E50E6)
private val Muted = Color(0xFF64748B)
private val Hairline = Color(0x0F0F172A)
private val OpenGreen = Color(0xFF15803D)
private val HeldAmber = Color(0xFFD97706)

/** How one court stands for one hour. */
enum class CellState { Open, Booked, Held }

/** Which court markings to draw. Read off the venue's sports, never guessed. */
enum class CourtKind { Racket, Football, Cricket, Plain }

/** A court as the strip draws it: its name, and the markings its sport calls for. */
data class CourtTag(val name: String, val kind: CourtKind)

/** Who holds a court-hour, as the counter needs it: enough to greet, call and collect. */
data class CellBooking(
    val id: Long,
    val customer: String,
    val phone: String?,
    val amount: Double,
    val paid: Double,
    /** unpaid | part | paid */
    val paymentStatus: String,
    val walkIn: Boolean,
)

/** One hour of today at the venue, court by court. */
data class CourtHour(
    val time: String,
    /** Minutes after midnight; [Int.MAX_VALUE] when the label can't be read. */
    val start: Int,
    val cells: List<CellState>,
    val price: Double,
    /** The court behind each of [cells], same order. Empty for a venue without courts. */
    val courts: List<CourtTag> = emptyList(),
    /** The booking on each of [cells], same order; null where it's open or held. */
    val bookings: List<CellBooking?> = emptyList(),
    /** What each of [cells] would charge, same order (peak and slot prices included). */
    val cellPrices: List<Double> = emptyList(),
) {
    val total get() = cells.size
    val free get() = cells.count { it == CellState.Open }
}

fun courtKindFor(sports: List<String>): CourtKind {
    val s = sports.joinToString(" ").lowercase()
    return when {
        listOf("badminton", "tennis", "pickle", "squash", "table", "volley").any { it in s } -> CourtKind.Racket
        listOf("football", "futsal", "soccer").any { it in s } -> CourtKind.Football
        "cricket" in s -> CourtKind.Cricket
        else -> CourtKind.Plain
    }
}

private fun nowMinutes(): Int = Calendar.getInstance().let { it.get(Calendar.HOUR_OF_DAY) * 60 + it.get(Calendar.MINUTE) }

private fun CourtHour.isLive(now: Int) = start != Int.MAX_VALUE && now >= start && now < start + 60
private fun CourtHour.isPast(now: Int) = start != Int.MAX_VALUE && now >= start + 60

/**
 * "Today's courts": a header that draws where the sun is in the venue's day, then
 * one drawn court per hour. Each court shows its real courts side by side — lit
 * blue where someone is playing, amber where a player is mid-payment, bare turf
 * where it's still for sale. The strip snaps hour to hour with a detent tick, so
 * flicking through the day feels like turning a dial.
 */
@Composable
fun CourtsDaySection(
    hours: List<CourtHour>,
    kind: CourtKind,
    formatPrice: (Double) -> String,
    onOpenDesk: () -> Unit,
    modifier: Modifier = Modifier,
) {
    // Re-read the clock every 30s so "Now" moves on while the screen sits open.
    var now by remember { mutableIntStateOf(nowMinutes()) }
    LaunchedEffect(Unit) { while (true) { delay(30_000); now = nowMinutes() } }

    Column(modifier) {
        CourtsDayHeader(hours, now, hours.maxOfOrNull { it.courts.size } ?: 0, onOpenDesk, Modifier.padding(horizontal = 16.dp))
        Spacer(Modifier.height(12.dp))
        CourtsDayStrip(hours, kind, now, formatPrice, onOpenDesk)
    }
}

@Composable
private fun CourtsDayHeader(hours: List<CourtHour>, now: Int, courtCount: Int, onOpenDesk: () -> Unit, modifier: Modifier) {
    val view = LocalView.current
    val ahead = hours.filterNot { it.isPast(now) }
    val openHours = ahead.count { it.free > 0 }
    val fullHours = ahead.count { it.total > 0 && it.free == 0 }
    val fact = when {
        ahead.isEmpty() -> "Today's hours are done"
        openHours == 0 -> "Sold out for the rest of today"
        fullHours == 0 -> if (openHours == 1) "1 hour still open" else "$openHours hours still open"
        else -> "$openHours open · $fullHours full"
    }
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        DayArc(now, Modifier.size(width = 40.dp, height = 28.dp))
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text("Today's courts", fontSize = 16.5.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.2).sp)
            Text(if (courtCount > 1) "$courtCount courts · $fact" else fact, fontSize = 12.sp, color = Muted, maxLines = 1)
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color(0xFFEAF1FF))
                .clickable { Haptics.tick(view); onOpenDesk() }
                .padding(start = 12.dp, end = 6.dp, top = 7.dp, bottom = 7.dp),
        ) {
            Text("Open desk", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AccentDeep)
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = AccentDeep, modifier = Modifier.size(16.dp))
        }
    }
}

/**
 * The sun's path over a playing day, 6 AM to 7 PM, with the sun where it is right
 * now. After dark it's a moon under floodlight hours. Drawn, not an icon — it is
 * the one picture on Home that changes with the real clock.
 */
@Composable
private fun DayArc(now: Int, modifier: Modifier) {
    val dawn = 6 * 60
    val dusk = 19 * 60
    val daylight = now in dawn..dusk
    val target = if (daylight) (now - dawn).toFloat() / (dusk - dawn) else 0.5f
    val t by animateFloatAsState(target, tween(900, easing = FastOutSlowInEasing), label = "sun")
    val glow = rememberInfiniteTransition(label = "sun-glow").animateFloat(
        0.85f, 1.15f, infiniteRepeatable(tween(1800, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "glow",
    )
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val base = h * 0.86f
        val r = w * 0.40f
        val cx = w / 2f
        // Horizon and the dashed path of the day.
        drawLine(Color(0xFFCBD5E1), Offset(0f, base), Offset(w, base), strokeWidth = 1.2.dp.toPx(), cap = StrokeCap.Round)
        drawArc(
            color = Color(0xFFBFD0F5),
            startAngle = 180f, sweepAngle = 180f, useCenter = false,
            topLeft = Offset(cx - r, base - r), size = Size(r * 2, r * 2),
            style = Stroke(1.3.dp.toPx(), cap = StrokeCap.Round, pathEffect = PathEffect.dashPathEffect(floatArrayOf(3.dp.toPx(), 3.dp.toPx()))),
        )
        if (daylight) {
            val a = PI + PI * t
            val sun = Offset(cx + r * cos(a).toFloat(), base + r * sin(a).toFloat())
            // Trail already travelled, solid.
            drawArc(
                color = Color(0xFFF59E0B),
                startAngle = 180f, sweepAngle = 180f * t, useCenter = false,
                topLeft = Offset(cx - r, base - r), size = Size(r * 2, r * 2),
                style = Stroke(1.6.dp.toPx(), cap = StrokeCap.Round),
            )
            drawCircle(Color(0x33F59E0B), radius = 6.5.dp.toPx() * glow.value, center = sun)
            drawCircle(Color(0xFFF59E0B), radius = 3.8.dp.toPx(), center = sun)
        } else {
            // Crescent moon at the top of the arc: a disc with a bite taken out.
            val m = Offset(cx, base - r)
            val rr = 4.6.dp.toPx()
            drawCircle(Color(0x221E50E6), radius = rr * 1.9f * glow.value, center = m)
            val moon = Path().apply {
                addOval(Rect(m, rr))
            }
            val bite = Path().apply { addOval(Rect(Offset(m.x + rr * 0.55f, m.y - rr * 0.35f), rr * 0.9f)) }
            val crescent = Path().apply { op(moon, bite, androidx.compose.ui.graphics.PathOperation.Difference) }
            drawPath(crescent, AccentDeep)
        }
    }
}

@Composable
private fun CourtsDayStrip(
    hours: List<CourtHour>,
    kind: CourtKind,
    now: Int,
    formatPrice: (Double) -> String,
    onOpen: () -> Unit,
) {
    val view = LocalView.current
    val rowState = rememberLazyListState()
    val first = hours.indexOfFirst { !it.isPast(now) }.let { if (it < 0) hours.lastIndex else it }.coerceAtLeast(0)
    // The detent tick is armed only after the strip has scrolled itself to "now",
    // so opening Home doesn't buzz through the morning.
    var armed by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        rowState.animateScrollToItem((first - 1).coerceAtLeast(0))
        armed = true
    }
    LaunchedEffect(rowState) {
        snapshotFlow { rowState.firstVisibleItemIndex }
            .distinctUntilChanged()
            .drop(1)
            .collect { if (armed && rowState.isScrollInProgress) Haptics.tick(view) }
    }
    LazyRow(
        state = rowState,
        flingBehavior = rememberSnapFlingBehavior(rowState),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 14.dp),
    ) {
        itemsIndexed(hours, key = { i, h -> "${h.time}-$i" }) { i, h ->
            HourCourtTile(h, kind, index = i, hasLive = hours.any { it.isLive(now) }, live = h.isLive(now), past = h.isPast(now), now = now, formatPrice = formatPrice, onClick = onOpen)
        }
    }
}

@Composable
private fun HourCourtTile(
    h: CourtHour,
    kind: CourtKind,
    index: Int,
    hasLive: Boolean,
    live: Boolean,
    past: Boolean,
    now: Int,
    formatPrice: (Double) -> String,
    onClick: () -> Unit,
) {
    val full = h.total > 0 && h.free == 0
    val held = h.cells.count { it == CellState.Held }
    // Entrance: the tile rises in, then its courts light up one by one.
    val enter = remember { Animatable(0f) }
    val paint = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        val stagger = (index.coerceAtMost(6)) * 55L
        delay(stagger)
        enter.animateTo(1f, spring(dampingRatio = 0.62f, stiffness = Spring.StiffnessMediumLow))
    }
    LaunchedEffect(Unit) {
        delay(index.coerceAtMost(6) * 55L + 180L)
        paint.animateTo(1f, tween(520 + 90 * h.total.coerceAtMost(6), easing = FastOutSlowInEasing))
    }

    val status: String
    val statusColor: Color
    when {
        past -> { status = "Done"; statusColor = Muted }
        live -> {
            val freeName = if (h.free == 1 && h.total > 1) h.courts.getOrNull(h.cells.indexOf(CellState.Open))?.name else null
            status = when { full -> "On now · full"; freeName != null -> "On now · $freeName free"; else -> "On now · ${h.free} free" }
            statusColor = Color(0xFF9DBBFF)
        }
        full -> { status = "Full"; statusColor = AccentDeep }
        held > 0 && h.free == 0 -> { status = "Paying now"; statusColor = HeldAmber }
        h.free < h.total -> {
            // With one court left, name it: "padi free" is what the desk needs to know.
            val freeName = if (h.free == 1) h.courts.getOrNull(h.cells.indexOf(CellState.Open))?.name else null
            status = freeName?.let { "$it free" } ?: "${h.free} of ${h.total} free"
            statusColor = AccentDeep
        }
        else -> { status = "Open"; statusColor = OpenGreen }
    }
    val shape = RoundedCornerShape(20.dp)
    val tileBg: Brush = when {
        live -> Brush.linearGradient(listOf(InkTop, InkMid, Color(0xFF12296A)))
        past -> Brush.linearGradient(listOf(Color(0xFFF4F6F9), Color(0xFFF4F6F9)))
        else -> Brush.verticalGradient(listOf(Color.White, Color(0xFFF8FAFF)))
    }
    val progress = if (live) ((now - h.start).coerceIn(0, 60) / 60f) else 0f

    Box(
        Modifier
            .graphicsLayer {
                alpha = enter.value.coerceIn(0f, 1f)
                translationY = (1f - enter.value) * 18.dp.toPx()
                val s = 0.92f + 0.08f * enter.value
                scaleX = s; scaleY = s
            }
            .pressableTile(cornerRadius = 20.dp, pressedScale = 0.94f, onClick = onClick)
            .width(112.dp)
            .height((if (hasLive) 176.dp else 164.dp) + if (h.courts.size > 1) 16.dp else 0.dp)
            .shadow(if (past) 0.dp else if (live) 14.dp else 8.dp, shape, clip = false, spotColor = if (live) Color(0x552F6BFF) else Color(0x1F0F172A))
            .clip(shape)
            .background(tileBg)
            .border(1.dp, if (live) Color(0x337DA9FF) else Hairline, shape)
            .semantics {
                contentDescription = "${h.time}: " + when {
                    past -> "done"
                    full -> "all ${h.total} courts booked"
                    else -> "${h.free} of ${h.total} courts free"
                }
            },
    ) {
        Column(Modifier.padding(10.dp)) {
            CourtIllustration(
                cells = h.cells,
                kinds = h.courts.map { it.kind }.takeIf { it.size == h.cells.size } ?: List(h.cells.size) { kind },
                live = live,
                past = past,
                paint = paint.value,
                modifier = Modifier.fillMaxWidth().height(58.dp),
            )
            // Which drawing is which court — without it two pitches read as one picture.
            if (h.courts.size > 1 && h.courts.size == h.cells.size) {
                Spacer(Modifier.height(4.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
                    h.courts.forEachIndexed { i, c ->
                        Text(
                            c.name, modifier = Modifier.weight(1f),
                            fontSize = 9.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1,
                            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                            color = when {
                                live -> Color(0xB3E0E8FF)
                                past -> Muted
                                h.cells[i] == CellState.Booked -> AccentDeep
                                else -> Muted
                            },
                        )
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
            Text(
                h.time.replace(":00", "").trim(),
                fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1, letterSpacing = (-0.3).sp,
                color = when { live -> Color.White; past -> Muted; else -> Ink },
            )
            Spacer(Modifier.height(3.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (live) LiveBeacon() else Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(statusColor))
                Spacer(Modifier.width(6.dp))
                Text(status, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = statusColor, maxLines = 1)
            }
            if (!past && !full && h.price > 0) {
                Spacer(Modifier.height(2.dp))
                Text(
                    "₹" + formatPrice(h.price) + " / court",
                    fontSize = 11.sp, maxLines = 1,
                    color = if (live) Color(0xB3E0E8FF) else Muted,
                )
            }
            if (live) {
                // How far into this hour we are — real minutes, not decoration.
                Spacer(Modifier.height(8.dp))
                Box(
                    Modifier.fillMaxWidth().height(3.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x26FFFFFF))
                        .drawBehind {
                            drawRoundRect(
                                Brush.horizontalGradient(listOf(Color(0xFF7DA9FF), Color.White)),
                                size = Size(size.width * progress, size.height),
                                cornerRadius = CornerRadius(size.height / 2),
                            )
                        },
                )
            }
        }
    }
}

@Composable
private fun LiveBeacon() {
    val t = rememberInfiniteTransition(label = "beacon")
    val ring by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1400, easing = LinearEasing), RepeatMode.Restart), label = "ring")
    Box(Modifier.size(8.dp), contentAlignment = Alignment.Center) {
        Box(
            Modifier.size(8.dp)
                .graphicsLayer { scaleX = 1f + 1.6f * ring; scaleY = 1f + 1.6f * ring; alpha = 1f - ring }
                .clip(RoundedCornerShape(99.dp)).background(Color(0xFF7DA9FF)),
        )
        Box(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFF9DBBFF)))
    }
}

/**
 * The venue's courts for one hour, drawn top-down side by side. Open courts are
 * bare turf with their markings; a booked court fills with brand blue from the
 * baseline up (on first show) and gets two players on it; a held court is amber
 * hatching. More than six courts still draw, just narrower.
 */
@Composable
private fun CourtIllustration(
    cells: List<CellState>,
    kinds: List<CourtKind>,
    live: Boolean,
    past: Boolean,
    paint: Float,
    modifier: Modifier,
) {
    // Players on the court that's on right now shift their weight a little.
    val bob = if (live) {
        rememberInfiniteTransition(label = "players").animateFloat(
            -1f, 1f, infiniteRepeatable(tween(900, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "bob",
        ).value
    } else 0f
    val lanes = cells.ifEmpty { listOf(CellState.Open) }
    val laneKinds = kinds.ifEmpty { listOf(CourtKind.Plain) }
    Canvas(modifier) {
        val gap = 3.dp.toPx()
        val n = lanes.size
        val laneW = (size.width - gap * (n - 1)) / n
        val radius = CornerRadius(7.dp.toPx())
        lanes.forEachIndexed { i, state ->
            val kind = laneKinds.getOrElse(i) { laneKinds.first() }
            val left = i * (laneW + gap)
            val lane = Rect(left, 0f, left + laneW, size.height)
            // Each court's fill lands a beat after the one before it.
            val p = ((paint * (n + 1)) - i).coerceIn(0f, 1f)
            val turf = when {
                past -> Color(0xFFE6EAF0)
                live -> Color(0x1FFFFFFF)
                kind == CourtKind.Football || kind == CourtKind.Cricket -> Color(0xFFE3F4E8)
                else -> Color(0xFFE8F0FB)
            }
            val lineOnTurf = when {
                past -> Color(0xFFCBD2DC)
                live -> Color(0x40FFFFFF)
                kind == CourtKind.Football || kind == CourtKind.Cricket -> Color(0xFFB3DDBF)
                else -> Color(0xFFBCCDEB)
            }
            drawRoundRect(turf, lane.topLeft, lane.size, radius)
            if (!past && !live && (kind == CourtKind.Football || kind == CourtKind.Cricket)) {
                mownBands(lane, Color(0xFFD6EEDD))
            }
            val filled = state != CellState.Open
            if (filled && p > 0f) {
                val top = lane.bottom - lane.height * p
                clipRect(lane.left, top, lane.right, lane.bottom) {
                    if (state == CellState.Booked) {
                        drawRoundRect(
                            Brush.verticalGradient(
                                if (past) listOf(Color(0xFFB8C2D1), Color(0xFFA3AFC0))
                                else listOf(Color(0xFF4A80FF), AccentDeep),
                                startY = lane.top, endY = lane.bottom,
                            ),
                            lane.topLeft, lane.size, radius,
                        )
                    } else {
                        drawRoundRect(if (past) Color(0xFFE2E6EC) else Color(0xFFFFF1DB), lane.topLeft, lane.size, radius)
                        hatch(lane, if (past) Color(0xFFCBD2DC) else Color(0x66D97706))
                    }
                }
            }
            val lineColor = if (filled && p >= 1f) {
                if (state == CellState.Booked) Color(0x59FFFFFF) else Color(0x55D97706)
            } else lineOnTurf
            // Courts are drawn portrait; a lane wider than tall (one court filling
            // the tile) turns its court on its side instead of shrinking it.
            upright(lane) { r ->
                markings(r.deflate(3.dp.toPx()), kind, lineColor)
                if (state == CellState.Booked && p >= 1f && !past) players(r, kind, bob)
            }
        }
    }
}

/** One court, drawn small and empty with its sport's markings: a court's face in a list. */
@Composable
fun MiniCourt(kind: CourtKind, modifier: Modifier) {
    Canvas(modifier) {
        val r = Rect(Offset.Zero, size)
        val grass = kind == CourtKind.Football || kind == CourtKind.Cricket
        drawRoundRect(if (grass) Color(0xFFE3F4E8) else Color(0xFFE8F0FB), r.topLeft, r.size, CornerRadius(8.dp.toPx()))
        if (grass) mownBands(r, Color(0xFFD6EEDD))
        upright(r) { markings(it.deflate(4.dp.toPx()), kind, if (grass) Color(0xFF9FD1AE) else Color(0xFFA9BFE6)) }
    }
}

internal fun DrawScope.upright(lane: Rect, block: DrawScope.(Rect) -> Unit) {
    if (lane.width <= lane.height) { block(lane); return }
    val c = lane.center
    val turned = Rect(c.x - lane.height / 2, c.y - lane.width / 2, c.x + lane.height / 2, c.y + lane.width / 2)
    rotate(90f, pivot = c) { block(turned) }
}

/** Mowing stripes across the long side of a grass pitch. */
internal fun DrawScope.mownBands(r: Rect, color: Color) {
    val across = r.width > r.height
    val bands = 6
    clipRect(r.left, r.top, r.right, r.bottom) {
        for (b in 0 until bands step 2) {
            if (across) {
                val w = r.width / bands
                drawRect(color, Offset(r.left + b * w, r.top), Size(w, r.height))
            } else {
                val h = r.height / bands
                drawRect(color, Offset(r.left, r.top + b * h), Size(r.width, h))
            }
        }
    }
}

internal fun DrawScope.hatch(r: Rect, color: Color) {
    val step = 5.dp.toPx()
    var x = r.left - r.height
    while (x < r.right) {
        drawLine(color, Offset(x, r.bottom), Offset(x + r.height, r.top), strokeWidth = 1.4.dp.toPx())
        x += step
    }
}

/** Court lines by sport, scaled to whatever width the lane got. */
internal fun DrawScope.markings(r: Rect, kind: CourtKind, c: Color) {
    val w = 1.dp.toPx()
    val cx = r.center.x
    val cy = r.center.y
    when (kind) {
        CourtKind.Racket -> {
            drawRect(c, r.topLeft, r.size, style = Stroke(w))
            // Net across the middle, drawn heavier; service lines either side.
            drawLine(c, Offset(r.left, cy), Offset(r.right, cy), strokeWidth = w * 1.8f)
            val svc = r.height * 0.18f
            drawLine(c, Offset(r.left, cy - svc), Offset(r.right, cy - svc), strokeWidth = w)
            drawLine(c, Offset(r.left, cy + svc), Offset(r.right, cy + svc), strokeWidth = w)
            drawLine(c, Offset(cx, r.top), Offset(cx, cy - svc), strokeWidth = w)
            drawLine(c, Offset(cx, cy + svc), Offset(cx, r.bottom), strokeWidth = w)
        }
        CourtKind.Football -> {
            drawRect(c, r.topLeft, r.size, style = Stroke(w))
            drawLine(c, Offset(r.left, cy), Offset(r.right, cy), strokeWidth = w)
            drawCircle(c, radius = (r.width * 0.22f).coerceAtMost(r.height * 0.16f), center = r.center, style = Stroke(w))
            val boxW = r.width * 0.5f
            val boxH = r.height * 0.14f
            drawRect(c, Offset(cx - boxW / 2, r.top), Size(boxW, boxH), style = Stroke(w))
            drawRect(c, Offset(cx - boxW / 2, r.bottom - boxH), Size(boxW, boxH), style = Stroke(w))
        }
        CourtKind.Cricket -> {
            // The strip down the middle with its creases.
            val stripW = (r.width * 0.34f).coerceAtLeast(4.dp.toPx())
            val strip = Rect(cx - stripW / 2, r.top + r.height * 0.1f, cx + stripW / 2, r.bottom - r.height * 0.1f)
            drawRect(c, strip.topLeft, strip.size, style = Stroke(w))
            val crease = r.height * 0.1f
            drawLine(c, Offset(strip.left - 2.dp.toPx(), strip.top + crease), Offset(strip.right + 2.dp.toPx(), strip.top + crease), strokeWidth = w)
            drawLine(c, Offset(strip.left - 2.dp.toPx(), strip.bottom - crease), Offset(strip.right + 2.dp.toPx(), strip.bottom - crease), strokeWidth = w)
        }
        CourtKind.Plain -> {
            drawRect(c, r.topLeft, r.size, style = Stroke(w))
            drawLine(c, Offset(r.left, cy), Offset(r.right, cy), strokeWidth = w)
        }
    }
}

/** Two players, one each side of the net or halfway line. */
internal fun DrawScope.players(r: Rect, kind: CourtKind, bob: Float) {
    val headR = (r.width * 0.11f).coerceIn(2.dp.toPx(), 3.4.dp.toPx())
    val sway = bob * 1.2.dp.toPx()
    val a = Offset(r.center.x - r.width * 0.14f + sway, r.top + r.height * 0.27f)
    val b = Offset(r.center.x + r.width * 0.14f - sway, r.bottom - r.height * 0.27f)
    listOf(a, b).forEach { p ->
        drawCircle(Color(0x33000000), radius = headR * 1.25f, center = p + Offset(0f, headR * 0.5f))
        drawCircle(Color.White, radius = headR, center = p)
    }
    // The shuttle / ball in flight between them.
    if (kind != CourtKind.Plain) {
        val ball = Offset(r.center.x + sway * 2f, r.center.y - r.height * 0.06f)
        drawCircle(Color(0xFFFFD166), radius = headR * 0.55f, center = ball)
    }
}
