package com.haraan.partner.ui.venues

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ApiException
import com.haraan.partner.PartnerApi
import com.haraan.partner.SlotEdit
import com.haraan.partner.VenueHours
import com.haraan.partner.slotStartMinutes
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.pricing.clock
import kotlinx.coroutines.launch

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hairline = Color(0xFFE6EBF2)
private val Blue = Color(0xFF2563EB)
private val BlueTint = Color(0xFFEFF4FF)
private val Track = Color(0xFFEEF2F8)

private const val DAY = 24 * 60

/** One day's hours as the editor holds them. open == close means round the clock. */
private data class DayHours(val isOpen: Boolean, val open: Int, val close: Int) {
    val allDay get() = isOpen && open == close
}

/** The (start, end) spans a day's hours cover on that day's own 0–24h track. */
private fun spans(h: Pair<Int, Int>?): List<Pair<Int, Int>> {
    if (h == null) return emptyList()
    val (o, c) = h
    return when {
        o == c -> listOf(0 to DAY)
        c == 0 || c > o -> listOf(o to (if (c == 0) DAY else c))
        else -> listOf(o to DAY) // past midnight: the rest shows on the next morning
    }
}

/** Minutes of selling a day's hours give, overnight carry included. */
private fun minutesOpen(h: Pair<Int, Int>?): Int = h?.let { (o, c) ->
    when {
        o == c -> DAY
        c > o -> c - o
        else -> DAY - o + c
    }
} ?: 0

/** Hours rebuilt from the slot rows, for a venue whose hours were never set (or an old server). */
internal fun hoursFromSlots(slots: List<SlotEdit>?, len: Int): VenueHours? {
    val live = slots?.filter { it.isOpen } ?: return null
    if (live.isEmpty()) return null
    val days = VenueHours.KEYS.mapIndexed { i, k ->
        val name = VenueHours.NAMES[i]
        val starts = live.filter { it.day.isNullOrBlank() || it.day.equals("Every day", true) || it.day.equals(name, true) }
            .map { slotStartMinutes(it.time) }.filter { it != Int.MAX_VALUE }
        k to if (starts.isEmpty()) null else starts.min() to ((starts.max() + len) % DAY)
    }.toMap()
    return VenueHours(set = false, days = days, slotMinutes = len)
}

private fun range(h: Pair<Int, Int>): String =
    if (h.first == h.second) "Open 24 hours" else "${clock(h.first)} – ${clock(h.second)}"

/** "6 AM – 11 PM every day", "Mon–Fri 6 AM – 11 PM · Sat, Sun closed". */
internal fun hoursSummary(h: VenueHours?): String? {
    h ?: return null
    val vals = VenueHours.KEYS.map { h.days[it] }
    if (vals.all { it == null }) return "Closed every day"
    if (vals.distinct().size == 1) return range(vals.first()!!).let { if (it == "Open 24 hours") "Open 24 hours, every day" else "$it every day" }
    val parts = mutableListOf<String>()
    var i = 0
    while (i < 7) {
        var j = i
        while (j + 1 < 7 && vals[j + 1] == vals[i]) j++
        val days = when (j - i) {
            0 -> VenueHours.KEYS[i]
            1 -> VenueHours.KEYS[i] + ", " + VenueHours.KEYS[j]
            else -> VenueHours.KEYS[i] + "–" + VenueHours.KEYS[j]
        }
        parts += days + " " + (vals[i]?.let { range(it) } ?: "closed")
        i = j + 1
    }
    return parts.joinToString(" · ")
}

private data class Preset(val label: String, val open: Int, val close: Int)

private val PRESETS = listOf(
    Preset("6 AM – 11 PM", 6 * 60, 23 * 60),
    Preset("5 AM – 12 AM", 5 * 60, 0),
    Preset("6 AM – 10 PM", 6 * 60, 22 * 60),
    Preset("Open 24 hours", 0, 0),
)

/**
 * Opening hours, set the way an owner says them: pick "6 AM – 11 PM" or "24 hours" for the
 * whole week, then switch a day off or give it its own times. The week drawing at the top
 * redraws as you go, so what players will be able to book is visible before saving.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HoursEditor(
    api: PartnerApi,
    token: String,
    venueId: Long,
    venueName: String,
    current: VenueHours?,
    onDismiss: () -> Unit,
    onSaved: () -> Unit,
) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val sheet = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val days = remember {
        mutableStateListOf<DayHours>().apply {
            VenueHours.KEYS.forEach { k ->
                val h = current?.days?.get(k)
                add(if (h == null) DayHours(current == null, 6 * 60, 23 * 60) else DayHours(true, h.first, h.second))
            }
        }
    }
    var len by remember { mutableIntStateOf(current?.slotMinutes ?: 60) }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    fun asHours() = VenueHours(
        set = true,
        days = VenueHours.KEYS.mapIndexed { i, k -> k to days[i].takeIf { it.isOpen }?.let { it.open to it.close } }.toMap(),
        slotMinutes = len,
    )

    fun pickTime(start: Int, onPicked: (Int) -> Unit) {
        android.app.TimePickerDialog(context, { _, h, m -> onPicked(h * 60 + m) }, (start / 60) % 24, start % 60, false).show()
    }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheet, containerColor = Color.White) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState())
                .padding(start = 20.dp, end = 20.dp, bottom = 20.dp).navigationBarsPadding(),
        ) {
            Text("Opening hours", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink)
            Spacer(Modifier.height(2.dp))
            Text("When players can book $venueName", fontSize = 13.5.sp, color = Muted)

            Spacer(Modifier.height(18.dp))
            WeekChart(asHours())

            Spacer(Modifier.height(20.dp))
            Text("Same for every day", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink)
            Spacer(Modifier.height(8.dp))
            val uniform = days.filter { it.isOpen }.map { it.open to it.close }.distinct().singleOrNull()
            PRESETS.chunked(2).forEachIndexed { r, pair ->
                if (r > 0) Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    pair.forEach { p ->
                        val on = days.all { it.isOpen } && uniform == (p.open to p.close)
                        PresetChip(p.label, on, Modifier.weight(1f)) {
                            Haptics.tick(view)
                            for (i in days.indices) days[i] = DayHours(true, p.open, p.close)
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            Text("Day by day", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink)
            Spacer(Modifier.height(4.dp))
            days.forEachIndexed { i, d ->
                if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
                DayLine(
                    name = VenueHours.NAMES[i],
                    d = d,
                    onToggle = { open ->
                        Haptics.tick(view)
                        days[i] = d.copy(isOpen = open)
                    },
                    onOpenAt = { pickTime(d.open) { m -> days[i] = d.copy(open = m) } },
                    onCloseAt = { pickTime(d.close) { m -> days[i] = d.copy(close = m) } },
                    onAllDay = {
                        Haptics.tick(view)
                        days[i] = if (d.allDay) d.copy(open = 6 * 60, close = 23 * 60) else d.copy(open = 0, close = 0)
                    },
                    onCopyToAll = {
                        Haptics.confirm(view)
                        for (k in days.indices) days[k] = d
                    },
                )
            }

            Spacer(Modifier.height(18.dp))
            Text("Slot length", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink)
            Spacer(Modifier.height(8.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Track).padding(4.dp),
            ) {
                listOf(30 to "30 min", 60 to "1 hour").forEach { (m, label) ->
                    val on = len == m
                    val bg by animateColorAsState(if (on) Color.White else Track, tween(180), label = "seg")
                    Box(
                        Modifier.weight(1f).pressableTile(9.dp) { len = m }
                            .clip(RoundedCornerShape(9.dp)).background(bg).padding(vertical = 10.dp),
                        contentAlignment = Alignment.Center,
                    ) { Text(label, fontSize = 14.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) Ink else Muted) }
                }
            }

            val h = asHours()
            val perWeek = VenueHours.KEYS.sumOf { minutesOpen(h.days[it]) / len }
            Spacer(Modifier.height(16.dp))
            Text(
                "$perWeek slots a week per court. Times outside these hours are removed; " +
                    "prices for the hours you keep stay as they are. Bookings already made aren't touched.",
                fontSize = 12.5.sp, color = Muted, lineHeight = 18.sp,
            )
            error?.let {
                Spacer(Modifier.height(10.dp))
                Text(it, fontSize = 13.sp, color = Color(0xFFB91C1C), lineHeight = 18.sp)
            }
            Spacer(Modifier.height(16.dp))
            val canSave = !saving && days.any { it.isOpen }
            Box(
                Modifier.fillMaxWidth().pressableTile(14.dp, enabled = canSave, pressedScale = 0.97f) {
                    saving = true
                    error = null
                    scope.launch {
                        try {
                            api.saveVenueHours(token, venueId, asHours())
                            Haptics.confirm(view)
                            onSaved()
                        } catch (e: ApiException) {
                            Haptics.reject(view)
                            error = if (e.code == 404 || e.code == 405) "Hours can be changed here after the next Haraan server update."
                            else e.message ?: "Couldn't save. Try again."
                        } catch (e: Exception) {
                            Haptics.reject(view)
                            error = "Couldn't reach Haraan. Check the connection and try again."
                        } finally {
                            saving = false
                        }
                    }
                }.height(52.dp).clip(RoundedCornerShape(14.dp))
                    .background(if (canSave) Brush.verticalGradient(listOf(Color(0xFF3B7BFF), Blue)) else Brush.verticalGradient(listOf(Color(0xFFCBD5E1), Color(0xFFCBD5E1)))),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    if (saving) "Saving…" else if (days.none { it.isOpen }) "Keep at least one day open" else "Save hours",
                    fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White,
                )
            }
        }
    }
}

/**
 * The week at a glance: one 24-hour track per day, blue where it's open. Hours that run
 * past midnight spill onto the next morning, as the slots will.
 */
@Composable
private fun WeekChart(h: VenueHours) {
    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color(0xFFF7F9FC)).padding(14.dp)) {
        VenueHours.KEYS.forEachIndexed { i, k ->
            val prev = h.days[VenueHours.KEYS[(i + 6) % 7]]
            val carry = prev?.takeIf { (o, c) -> c in 1 until o }?.let { listOf(0 to it.second) }.orEmpty()
            val bars = carry + spans(h.days[k])
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 3.dp)) {
                Text(k, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = if (bars.isEmpty()) Faint else Ink, modifier = Modifier.width(36.dp))
                DayTrack(bars, Modifier.weight(1f).height(14.dp))
            }
        }
        Row(Modifier.fillMaxWidth().padding(start = 36.dp, top = 4.dp)) {
            listOf("12 AM", "6 AM", "12 PM", "6 PM", "12 AM").forEachIndexed { i, t ->
                Text(
                    t, fontSize = 10.sp, color = Faint,
                    modifier = Modifier.weight(1f),
                    textAlign = when (i) { 0 -> androidx.compose.ui.text.style.TextAlign.Start; 4 -> androidx.compose.ui.text.style.TextAlign.End; else -> androidx.compose.ui.text.style.TextAlign.Center },
                )
            }
        }
    }
}

@Composable
private fun DayTrack(bars: List<Pair<Int, Int>>, modifier: Modifier) {
    // Each bar eases to its new edges when the hours change, instead of jumping.
    val a = bars.getOrNull(0)
    val b = bars.getOrNull(1)
    val a0 by animateFloatAsState((a?.first ?: 0) / DAY.toFloat(), tween(260), label = "a0")
    val a1 by animateFloatAsState((a?.second ?: 0) / DAY.toFloat(), tween(260), label = "a1")
    val b0 by animateFloatAsState((b?.first ?: 0) / DAY.toFloat(), tween(260), label = "b0")
    val b1 by animateFloatAsState((b?.second ?: 0) / DAY.toFloat(), tween(260), label = "b1")
    Canvas(modifier) {
        val r = CornerRadius(size.height / 2)
        drawRoundRect(Track, Offset.Zero, size, r)
        // Quarter-day ticks, so 6 AM and 6 PM can be read off the track.
        for (q in 1..3) {
            val x = size.width * q / 4f
            drawLine(Color(0xFFDDE3EC), Offset(x, 0f), Offset(x, size.height), strokeWidth = 1f * density)
        }
        listOfNotNull(a?.let { a0 to a1 }, b?.let { b0 to b1 }).forEach { (s, e) ->
            if (e > s) {
                drawRoundRect(
                    Brush.horizontalGradient(listOf(Color(0xFF4D8BFF), Blue)),
                    Offset(size.width * s, 0f), Size(size.width * (e - s), size.height), r,
                )
            }
        }
    }
}

@Composable
private fun PresetChip(label: String, on: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val bg by animateColorAsState(if (on) Blue else Color.White, tween(180), label = "chip")
    Box(
        modifier.pressableTile(12.dp, onClick = onClick).clip(RoundedCornerShape(12.dp)).background(bg)
            .border(1.dp, if (on) Blue else Hairline, RoundedCornerShape(12.dp))
            .padding(horizontal = 8.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = if (on) Color.White else Ink, maxLines = 1)
    }
}

@Composable
private fun DayLine(
    name: String,
    d: DayHours,
    onToggle: (Boolean) -> Unit,
    onOpenAt: () -> Unit,
    onCloseAt: () -> Unit,
    onAllDay: () -> Unit,
    onCopyToAll: () -> Unit,
) {
    Column(Modifier.fillMaxWidth().padding(vertical = 10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = if (d.isOpen) Ink else Faint, modifier = Modifier.weight(1f))
            if (d.isOpen) {
                Text(
                    "Copy to all", fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Blue,
                    modifier = Modifier.pressableTile(8.dp, onClick = onCopyToAll).padding(horizontal = 8.dp, vertical = 6.dp),
                )
                Spacer(Modifier.width(6.dp))
            } else {
                Text("Closed", fontSize = 13.sp, color = Faint)
                Spacer(Modifier.width(10.dp))
            }
            Switch(
                checked = d.isOpen, onCheckedChange = onToggle,
                colors = SwitchDefaults.colors(checkedTrackColor = Blue, checkedThumbColor = Color.White),
            )
        }
        if (d.isOpen) {
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (d.allDay) {
                    Box(
                        Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).background(BlueTint).padding(vertical = 11.dp),
                        contentAlignment = Alignment.Center,
                    ) { Text("Open 24 hours", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Blue) }
                } else {
                    TimeBox("Opens", clock(d.open), Modifier.weight(1f), onOpenAt)
                    Text("–", fontSize = 16.sp, color = Faint, modifier = Modifier.padding(horizontal = 8.dp))
                    TimeBox(
                        if (d.close in 1 until d.open || (d.close == 0 && d.open > 0)) "Closes (next day)" else "Closes",
                        clock(d.close), Modifier.weight(1f), onCloseAt,
                    )
                }
                Spacer(Modifier.width(8.dp))
                Box(
                    Modifier.pressableTile(10.dp, onClick = onAllDay).clip(RoundedCornerShape(10.dp))
                        .background(if (d.allDay) Blue else Color.White)
                        .border(1.dp, if (d.allDay) Blue else Hairline, RoundedCornerShape(10.dp))
                        .padding(horizontal = 12.dp, vertical = 11.dp),
                ) { Text("24h", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = if (d.allDay) Color.White else Ink) }
            }
        }
    }
}

@Composable
private fun TimeBox(label: String, value: String, modifier: Modifier, onClick: () -> Unit) {
    Column(
        modifier.pressableTile(10.dp, onClick = onClick).clip(RoundedCornerShape(10.dp))
            .border(1.dp, Hairline, RoundedCornerShape(10.dp)).padding(horizontal = 12.dp, vertical = 6.dp),
    ) {
        Text(label, fontSize = 10.5.sp, color = Muted)
        Text(value, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
    }
}
