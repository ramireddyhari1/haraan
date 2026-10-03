package com.haraan.partner.ui.pricing

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressableTile
import kotlinx.coroutines.delay

// PartnerApp's palette, repeated so these sit in the same family.
internal val StudioInk = Color(0xFF0F172A)
internal val StudioMuted = Color(0xFF64748B)
internal val StudioAccent = Color(0xFF2F6BFF)
internal val StudioAccentDeep = Color(0xFF1E50E6)
private val Hairline = Color(0x120F172A)
private val Amber = Color(0xFFF59E0B)
private val AmberDeep = Color(0xFFB45309)

/** One slot as the week drawing needs it: start minute, and how it got onto the day. */
data class DayMark(val start: Int, val own: Boolean, val open: Boolean)

/** One weekday column of the week drawing. */
data class DayColumn(val name: String, val marks: List<DayMark>)

/**
 * The week, drawn: seven columns, each a track of the venue's hours with one segment per
 * slot that runs that day. Solid blue = the day's own slot, light blue = an every-day slot
 * filling in, grey = a slot switched off. The track spans the earliest to the latest hour
 * anywhere in the week, so every column is to the same scale and a short Sunday reads short.
 * Tapping a column picks the day the list below shows.
 */
@Composable
fun WeekRibbon(
    days: List<DayColumn>,
    selected: String,
    today: String,
    slotMinutes: Int,
    onPick: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val all = days.flatMap { it.marks }
    val first = (all.minOfOrNull { it.start } ?: (6 * 60)).let { it - it % 60 }
    val last = (all.maxOfOrNull { it.start + slotMinutes } ?: (22 * 60)).let { if (it % 60 == 0) it else it + 60 - it % 60 }
    val span = (last - first).coerceAtLeast(60)

    Column(
        modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(22.dp), clip = false, spotColor = Color(0x1A0F172A))
            .clip(RoundedCornerShape(22.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(22.dp))
            .padding(start = 10.dp, end = 10.dp, top = 12.dp, bottom = 10.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Your week", fontSize = 13.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk)
            Spacer(Modifier.weight(1f))
            Text(clock(first) + " – " + clock(last), fontSize = 11.sp, color = StudioMuted)
        }
        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            days.forEach { d ->
                val on = d.name == selected
                val lift by animateFloatAsState(
                    if (on) 1f else 0f, spring(dampingRatio = 0.5f, stiffness = Spring.StiffnessMediumLow), label = "day-lift",
                )
                val bg by animateColorAsState(if (on) Color(0xFFEAF1FF) else Color.Transparent, tween(200), label = "day-bg")
                Column(
                    Modifier
                        .weight(1f)
                        .graphicsLayer { translationY = -3.dp.toPx() * lift; scaleX = 1f + 0.04f * lift; scaleY = 1f + 0.04f * lift }
                        .clip(RoundedCornerShape(14.dp))
                        .background(bg)
                        .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {
                            if (!on) { Haptics.tick(view); onPick(d.name) }
                        }
                        .padding(vertical = 6.dp)
                        .semantics { contentDescription = "${d.name}: ${d.marks.size} slots" },
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        d.name.take(3), fontSize = 11.5.sp,
                        fontWeight = if (on) FontWeight.ExtraBold else FontWeight.SemiBold,
                        color = if (on) StudioAccentDeep else StudioMuted,
                    )
                    Spacer(Modifier.height(6.dp))
                    DayTrack(d.marks, first, span, slotMinutes, on, Modifier.width(18.dp).height(84.dp))
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "${d.marks.size}", fontSize = 11.sp, fontWeight = FontWeight.Bold,
                        color = if (d.marks.isEmpty()) Color(0xFFCBD5E1) else StudioInk,
                    )
                    Spacer(Modifier.height(3.dp))
                    Box(
                        Modifier.size(5.dp).clip(RoundedCornerShape(99.dp))
                            .background(if (d.name == today) StudioAccent else Color.Transparent),
                    )
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth().padding(horizontal = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Legend(StudioAccent, "Own slot")
            Spacer(Modifier.width(12.dp))
            Legend(Color(0xFFB9CEFF), "Every day")
            Spacer(Modifier.width(12.dp))
            Legend(Color(0xFFD5DBE4), "Off")
            Spacer(Modifier.weight(1f))
            Box(Modifier.size(5.dp).clip(RoundedCornerShape(99.dp)).background(StudioAccent))
            Spacer(Modifier.width(4.dp))
            Text("Today", fontSize = 10.5.sp, color = StudioMuted)
        }
    }
}

@Composable
private fun Legend(color: Color, label: String) {
    Box(Modifier.size(width = 10.dp, height = 6.dp).clip(RoundedCornerShape(2.dp)).background(color))
    Spacer(Modifier.width(4.dp))
    Text(label, fontSize = 10.5.sp, color = StudioMuted)
}

/** One day's track. Segments grow in from the top when the screen opens. */
@Composable
private fun DayTrack(marks: List<DayMark>, first: Int, span: Int, slotMinutes: Int, selected: Boolean, modifier: Modifier) {
    val grow = remember { Animatable(0f) }
    LaunchedEffect(Unit) { grow.animateTo(1f, tween(650, easing = FastOutSlowInEasing)) }
    Canvas(modifier) {
        val r = CornerRadius(size.width / 2)
        drawRoundRect(if (selected) Color(0xFFDCE7FF) else Color(0xFFF1F4F9), cornerRadius = r)
        val gap = 1.dp.toPx()
        marks.forEach { m ->
            val top = size.height * (m.start - first) / span
            val bottom = size.height * (m.start + slotMinutes - first) / span
            if (top / size.height > grow.value) return@forEach
            val color = when {
                !m.open -> Color(0xFFD5DBE4)
                m.own -> StudioAccent
                else -> Color(0xFFB9CEFF)
            }
            drawRoundRect(
                color,
                topLeft = Offset(2.dp.toPx(), top + gap / 2),
                size = Size(size.width - 4.dp.toPx(), (bottom - top - gap).coerceAtLeast(1.5f)),
                cornerRadius = CornerRadius(3.dp.toPx()),
            )
        }
    }
}

/** Minutes after midnight as "6 AM" / "6:30 PM". */
fun clock(m: Int): String {
    val mm = ((m % 1440) + 1440) % 1440
    val h = mm / 60
    val min = mm % 60
    val h12 = if (h % 12 == 0) 12 else h % 12
    return (if (min == 0) "$h12" else "$h12:%02d".format(min)) + if (h < 12) " AM" else " PM"
}

/** The part of the day a slot falls in, for the list's section heads. */
enum class DayPart(val label: String) { Morning("Morning"), Afternoon("Afternoon"), Evening("Evening"), Night("Night") }

fun dayPartOf(start: Int): DayPart = when (start) {
    in 5 * 60 until 12 * 60 -> DayPart.Morning
    in 12 * 60 until 17 * 60 -> DayPart.Afternoon
    in 17 * 60 until 21 * 60 -> DayPart.Evening
    else -> DayPart.Night
}

/** Section head for a part of the day: a drawn sky, the name, and how many slots. */
@Composable
fun DayPartHeader(part: DayPart, count: Int, open: Int, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth().padding(top = 8.dp, bottom = 2.dp), verticalAlignment = Alignment.CenterVertically) {
        Canvas(Modifier.size(width = 30.dp, height = 22.dp)) { drawSky(part) }
        Spacer(Modifier.width(8.dp))
        Text(part.label, fontSize = 13.5.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk)
        Spacer(Modifier.width(8.dp))
        Box(Modifier.weight(1f).height(1.dp).background(Hairline))
        Spacer(Modifier.width(8.dp))
        Text(
            if (open == count) "$count slot${if (count == 1) "" else "s"}" else "$open of $count open",
            fontSize = 11.5.sp, color = StudioMuted,
        )
    }
}

private fun DrawScope.drawSky(part: DayPart) {
    val w = size.width
    val h = size.height
    val horizon = h * 0.82f
    drawLine(Color(0xFFCBD5E1), Offset(0f, horizon), Offset(w, horizon), 1.2.dp.toPx(), StrokeCap.Round)
    when (part) {
        DayPart.Morning -> {
            // Sun just clear of the horizon, rays up.
            val c = Offset(w * 0.5f, horizon - 2.dp.toPx())
            val r = 5.dp.toPx()
            drawArc(Amber, 180f, 180f, true, Offset(c.x - r, c.y - r), Size(r * 2, r * 2))
            for (i in 0..4) {
                val a = Math.toRadians(180.0 + 22.5 + i * 33.75)
                val o = Offset(c.x + (r + 2.dp.toPx()) * Math.cos(a).toFloat(), c.y + (r + 2.dp.toPx()) * Math.sin(a).toFloat())
                val e = Offset(c.x + (r + 5.dp.toPx()) * Math.cos(a).toFloat(), c.y + (r + 5.dp.toPx()) * Math.sin(a).toFloat())
                drawLine(Amber, o, e, 1.3.dp.toPx(), StrokeCap.Round)
            }
        }
        DayPart.Afternoon -> {
            val c = Offset(w * 0.5f, h * 0.38f)
            drawCircle(Color(0x33F59E0B), 7.5.dp.toPx(), c)
            drawCircle(Amber, 4.8.dp.toPx(), c)
        }
        DayPart.Evening -> {
            // Setting sun cut by the horizon, a warmer orange.
            val c = Offset(w * 0.5f, horizon)
            val r = 6.dp.toPx()
            drawArc(Color(0xFFF97316), 180f, 180f, true, Offset(c.x - r, c.y - r), Size(r * 2, r * 2))
            drawLine(Color(0x66F97316), Offset(w * 0.2f, horizon + 2.5.dp.toPx()), Offset(w * 0.8f, horizon + 2.5.dp.toPx()), 1.dp.toPx(), StrokeCap.Round)
        }
        DayPart.Night -> {
            val m = Offset(w * 0.5f, h * 0.4f)
            val rr = 5.dp.toPx()
            val moon = Path().apply { addOval(Rect(m, rr)) }
            val bite = Path().apply { addOval(Rect(Offset(m.x + rr * 0.55f, m.y - rr * 0.35f), rr * 0.9f)) }
            drawPath(Path().apply { op(moon, bite, PathOperation.Difference) }, StudioAccentDeep)
            drawCircle(Color(0xFF94A3B8), 0.9.dp.toPx(), Offset(w * 0.15f, h * 0.25f))
            drawCircle(Color(0xFF94A3B8), 0.9.dp.toPx(), Offset(w * 0.85f, h * 0.5f))
        }
    }
}

/**
 * One slot on the chosen day: its time, where it comes from, what it charges on the
 * chosen court, and a switch to take it off sale without deleting it. Tap to edit.
 */
@Composable
fun SlotLine(
    time: String,
    /** "Every day" or null when it's the day's own slot. */
    source: String?,
    detail: String?,
    price: String,
    priceNote: String?,
    priceIsOwn: Boolean,
    open: Boolean,
    index: Int,
    onToggle: (Boolean) -> Unit,
    onEdit: () -> Unit,
) {
    val enter = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        delay(index.coerceAtMost(8) * 35L)
        enter.animateTo(1f, spring(dampingRatio = 0.75f, stiffness = Spring.StiffnessMediumLow))
    }
    val dim by animateFloatAsState(if (open) 1f else 0.55f, tween(220), label = "slot-dim")
    Row(
        Modifier
            .graphicsLayer { alpha = enter.value; translationY = (1f - enter.value) * 16.dp.toPx() }
            .pressableTile(cornerRadius = 18.dp, pressedScale = 0.97f, onClick = onEdit)
            .fillMaxWidth()
            .shadow(if (open) 5.dp else 0.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x160F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(if (open) Color.White else Color(0xFFF4F6F9))
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(start = 14.dp, end = 8.dp, top = 11.dp, bottom = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // A little clock face set to the slot's start, instead of a generic icon.
        Canvas(Modifier.size(34.dp).graphicsLayer { alpha = dim }) { drawClock(time, open) }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f).graphicsLayer { alpha = dim }) {
            Text(time, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk, letterSpacing = (-0.2).sp)
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (source != null) {
                    Text(
                        source, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = StudioAccentDeep,
                        modifier = Modifier.clip(RoundedCornerShape(99.dp)).background(Color(0xFFEAF1FF)).padding(horizontal = 6.dp, vertical = 1.dp),
                    )
                    Spacer(Modifier.width(6.dp))
                }
                Text(
                    detail ?: if (open) "Open" else "Off sale",
                    fontSize = 12.sp, color = if (open) StudioMuted else Color(0xFFB91C1C),
                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
            }
        }
        Column(horizontalAlignment = Alignment.End, modifier = Modifier.graphicsLayer { alpha = dim }) {
            Text(
                price, fontSize = if (priceIsOwn) 16.sp else 14.sp, fontWeight = FontWeight.ExtraBold,
                color = if (priceIsOwn) StudioAccentDeep else StudioInk,
            )
            if (priceNote != null) Text(priceNote, fontSize = 10.5.sp, color = StudioMuted)
        }
        Spacer(Modifier.width(6.dp))
        Switch(
            checked = open,
            onCheckedChange = onToggle,
            colors = SwitchDefaults.colors(checkedTrackColor = StudioAccent, checkedThumbColor = Color.White),
        )
    }
}

private fun DrawScope.drawClock(time: String, open: Boolean) {
    val m = startMinutes(time) ?: return
    val c = center
    val r = size.minDimension / 2
    drawCircle(if (open) Color(0xFFEAF1FF) else Color(0xFFE9EDF2), r, c)
    drawCircle(if (open) Color(0x332F6BFF) else Color(0x220F172A), r, c, style = Stroke(1.dp.toPx()))
    for (i in 0 until 12) {
        val a = Math.toRadians(i * 30.0 - 90)
        val o = Offset(c.x + r * 0.78f * Math.cos(a).toFloat(), c.y + r * 0.78f * Math.sin(a).toFloat())
        drawCircle(if (open) Color(0x662F6BFF) else Color(0x440F172A), if (i % 3 == 0) 1.3.dp.toPx() else 0.7.dp.toPx(), o)
    }
    val hand = if (open) StudioAccentDeep else StudioMuted
    val ha = Math.toRadians(((m / 60) % 12 + (m % 60) / 60.0) * 30.0 - 90)
    val ma = Math.toRadians((m % 60) * 6.0 - 90)
    drawLine(hand, c, Offset(c.x + r * 0.45f * Math.cos(ha).toFloat(), c.y + r * 0.45f * Math.sin(ha).toFloat()), 2.2.dp.toPx(), StrokeCap.Round)
    drawLine(hand, c, Offset(c.x + r * 0.66f * Math.cos(ma).toFloat(), c.y + r * 0.66f * Math.sin(ma).toFloat()), 1.4.dp.toPx(), StrokeCap.Round)
    drawCircle(hand, 1.8.dp.toPx(), c)
}

/** "6:00 AM" → 360. */
fun startMinutes(raw: String?): Int? {
    val g = Regex("""(\d{1,2})(?::(\d{2}))?\s*([AaPp][Mm])?""").find(raw ?: return null) ?: return null
    var h = g.groupValues[1].toIntOrNull() ?: return null
    val min = g.groupValues[2].toIntOrNull() ?: 0
    when (g.groupValues[3].lowercase()) {
        "am" -> if (h == 12) h = 0
        "pm" -> if (h != 12) h += 12
    }
    return if (h in 0..23 && min in 0..59) h * 60 + min else null
}

/** The two tools, side by side, each with its own drawing. */
@Composable
fun ToolTile(title: String, sub: String, art: DrawScope.() -> Unit, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Column(
        modifier
            .pressableTile(cornerRadius = 20.dp, pressedScale = 0.95f, onClick = onClick)
            .shadow(6.dp, RoundedCornerShape(20.dp), clip = false, spotColor = Color(0x160F172A))
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .padding(14.dp),
    ) {
        Canvas(Modifier.fillMaxWidth().height(40.dp), onDraw = art)
        Spacer(Modifier.height(10.dp))
        Text(title, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk, maxLines = 1)
        Text(sub, fontSize = 11.5.sp, color = StudioMuted, maxLines = 2, lineHeight = 15.sp)
    }
}

/** Peak pricing: a day of bars with the busy evening ones standing taller, in amber. */
fun DrawScope.peakArt() {
    val bars = listOf(0.35f, 0.4f, 0.32f, 0.38f, 0.45f, 0.82f, 1f, 0.9f)
    val n = bars.size
    val gap = 4.dp.toPx()
    val bw = (size.width.coerceAtMost(150.dp.toPx()) - gap * (n - 1)) / n
    bars.forEachIndexed { i, f ->
        val hgt = size.height * f
        val peak = i in 5..7
        drawRoundRect(
            if (peak) Brush.verticalGradient(listOf(Color(0xFFFBBF24), AmberDeep)) else Brush.verticalGradient(listOf(Color(0xFFDCE4F2), Color(0xFFC7D2E6))),
            topLeft = Offset(i * (bw + gap), size.height - hgt),
            size = Size(bw, hgt),
            cornerRadius = CornerRadius(3.dp.toPx()),
        )
    }
}

/** Generate: a row of slot blocks, the first few already lit, a spark where the next lands. */
fun DrawScope.generateArt() {
    val n = 7
    val gap = 4.dp.toPx()
    val bw = (size.width.coerceAtMost(150.dp.toPx()) - gap * (n - 1)) / n
    val bh = size.height * 0.5f
    val top = size.height - bh
    for (i in 0 until n) {
        val lit = i < 4
        drawRoundRect(
            if (lit) StudioAccent else Color(0xFFE3EAF7),
            topLeft = Offset(i * (bw + gap), top),
            size = Size(bw, bh),
            cornerRadius = CornerRadius(4.dp.toPx()),
        )
    }
    // Four-point sparkle above the next block.
    val c = Offset(4 * (bw + gap) + bw / 2, top - 9.dp.toPx())
    val s = 6.dp.toPx()
    val p = Path().apply {
        moveTo(c.x, c.y - s); quadraticTo(c.x, c.y, c.x + s, c.y); quadraticTo(c.x, c.y, c.x, c.y + s)
        quadraticTo(c.x, c.y, c.x - s, c.y); quadraticTo(c.x, c.y, c.x, c.y - s); close()
    }
    drawPath(p, Amber)
}

/** A court to price for: a tiny drawn court, the name, its base rate. */
@Composable
fun CourtPill(name: String, rate: String?, selected: Boolean, onClick: () -> Unit) {
    val bg by animateColorAsState(if (selected) StudioInk else Color.White, tween(180), label = "court-bg")
    val fg = if (selected) Color.White else StudioInk
    Row(
        Modifier
            .pressableTile(cornerRadius = 999.dp, pressedScale = 0.93f, onClick = onClick)
            .clip(RoundedCornerShape(999.dp))
            .background(bg)
            .border(1.dp, if (selected) Color.Transparent else Color(0x1F0F172A), RoundedCornerShape(999.dp))
            .padding(start = 8.dp, end = 13.dp, top = 6.dp, bottom = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(width = 22.dp, height = 16.dp)) {
            val line = if (selected) Color(0x99FFFFFF) else Color(0xFF94A3B8)
            val w = 1.dp.toPx()
            drawRoundRect(if (selected) Color(0x26FFFFFF) else Color(0xFFE3F4E8), cornerRadius = CornerRadius(3.dp.toPx()))
            drawRect(line, Offset(2.dp.toPx(), 2.dp.toPx()), Size(size.width - 4.dp.toPx(), size.height - 4.dp.toPx()), style = Stroke(w))
            drawLine(line, Offset(size.width / 2, 2.dp.toPx()), Offset(size.width / 2, size.height - 2.dp.toPx()), w)
            drawCircle(line, 2.5.dp.toPx(), center, style = Stroke(w))
        }
        Spacer(Modifier.width(7.dp))
        Text(name, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = fg, maxLines = 1)
        if (rate != null) {
            Spacer(Modifier.width(6.dp))
            Text(rate, fontSize = 11.5.sp, color = if (selected) Color(0xB3FFFFFF) else StudioMuted, maxLines = 1)
        }
    }
}

/** A day with nothing on sale: an empty court under a stopped clock. */
@Composable
fun EmptyDayArt(modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val court = Rect(w * 0.18f, h * 0.35f, w * 0.82f, h * 0.95f)
        drawRoundRect(Color(0xFFE3F4E8), court.topLeft, court.size, CornerRadius(10.dp.toPx()))
        val line = Color(0xFFB3DDBF)
        val sw = 1.5.dp.toPx()
        val inner = court.deflate(8.dp.toPx())
        drawRect(line, inner.topLeft, inner.size, style = Stroke(sw))
        drawLine(line, Offset(inner.center.x, inner.top), Offset(inner.center.x, inner.bottom), sw)
        drawCircle(line, inner.height * 0.18f, inner.center, style = Stroke(sw))
        // The clock, hanging over the court.
        val c = Offset(w * 0.5f, h * 0.2f)
        val r = h * 0.16f
        drawCircle(Color.White, r, c)
        drawCircle(Color(0xFFCBD5E1), r, c, style = Stroke(2.dp.toPx()))
        drawLine(StudioMuted, c, Offset(c.x, c.y - r * 0.6f), 2.dp.toPx(), StrokeCap.Round)
        drawLine(StudioMuted, c, Offset(c.x + r * 0.45f, c.y), 2.dp.toPx(), StrokeCap.Round)
        drawCircle(StudioMuted, 2.dp.toPx(), c)
    }
}

/** The chosen day in one line: how many slots, the span, the cheapest rate on this court. */
@Composable
fun DaySummary(day: String, isToday: Boolean, slots: Int, open: Int, span: String?, from: String?, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.Bottom) {
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(day, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk, letterSpacing = (-0.4).sp)
                if (isToday) {
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "Today", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color.White,
                        modifier = Modifier.clip(RoundedCornerShape(99.dp)).background(StudioAccent).padding(horizontal = 8.dp, vertical = 2.dp),
                    )
                }
            }
            Text(
                when {
                    slots == 0 -> "Nothing on sale this day"
                    open == slots -> "$slots slots" + (span?.let { " · $it" } ?: "")
                    else -> "$open of $slots on sale" + (span?.let { " · $it" } ?: "")
                },
                fontSize = 12.5.sp, color = StudioMuted,
            )
        }
        if (from != null) {
            Column(horizontalAlignment = Alignment.End) {
                Text("from", fontSize = 10.5.sp, color = StudioMuted)
                Text(from, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = StudioInk)
            }
        }
    }
}

/** Fill the space when loading, so the screen doesn't jump. */
@Composable
fun StudioSkeleton() {
    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Box(Modifier.fillMaxWidth().height(160.dp).clip(RoundedCornerShape(22.dp)).background(Color(0xFFEDF1F7)))
        repeat(4) { Box(Modifier.fillMaxWidth().height(64.dp).clip(RoundedCornerShape(18.dp)).background(Color(0xFFEDF1F7))) }
    }
}
