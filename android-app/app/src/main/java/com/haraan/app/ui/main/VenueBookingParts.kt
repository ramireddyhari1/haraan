package com.haraan.app.ui.main

import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

/*
 * The pieces of the venue "Book a slot" sheet and its review page, drawn to match the
 * website's phone sheet (gamehub-detail.blade.php): date strip, court cards, one
 * Morning/Afternoon/Evening control over one grid of time cards, a ticket-stub dock with
 * a Checkout key, then the "Review booking" page. Colours are the site's, by value.
 */

internal object BookInk {
  val Blue = Color(0xFF2563EB)
  val BlueDeep = Color(0xFF1D4ED8)
  val BlueTint = Color(0xFFF5F8FF)
  val Ink = Color(0xFF0F172A)
  val Ink2 = Color(0xFF334155)
  val Slate = Color(0xFF475569)
  val Muted = Color(0xFF64748B)
  val Faint = Color(0xFF94A3B8)
  val Line = Color(0xFFE2E8F0)
  val Hair = Color(0xFFEEF2F7)
  val Page = Color(0xFFF4F7FB)
  val Chip = Color(0xFFF1F5F9)
  val Green = Color(0xFF16A34A)
  val Save = Color(0xFF15803D)
  val Amber = Color(0xFFB45309)
  val Red = Color(0xFFDC2626)
}

internal enum class SlotTileState { OPEN, BOOKED, CLOSED }

/** One time card in the grid: the slot, its start in minutes, what it costs, whether it can be taken. */
internal data class SlotTile(
  val slot: com.haraan.app.data.VenueSlotItem,
  val startMin: Int,
  val state: SlotTileState,
  val rate: Int,
  val isPeak: Boolean,
)

internal enum class DayPart(val label: String) { Morning("Morning"), Afternoon("Afternoon"), Evening("Evening") }

internal fun partOf(startMin: Int): DayPart = when {
  startMin < 12 * 60 -> DayPart.Morning
  startMin < 17 * 60 -> DayPart.Afternoon
  else -> DayPart.Evening
}

/** "6:00 AM" / "18:30" style clock for minutes-from-midnight, split as (clock, AM/PM). */
internal fun clockParts(min: Int): Pair<String, String> {
  val m = ((min % 1440) + 1440) % 1440
  val h = m / 60
  val h12 = when { h == 0 -> 12; h > 12 -> h - 12; else -> h }
  return "%d:%02d".format(h12, m % 60) to (if (h < 12) "AM" else "PM")
}

/** "1 hr", "1 hr 30 min", "30 min". */
internal fun lengthLabel(min: Int): String {
  val h = min / 60; val r = min % 60
  return when {
    h == 0 -> "$r min"
    r == 0 -> "$h hr"
    else -> "$h hr $r min"
  }
}

/** "in 40 min" / "in 2h 10m" — a real clock, never a sales line. */
internal fun startsIn(min: Int): String =
  if (min < 60) "in $min min" else "in ${min / 60}h${if (min % 60 != 0) " ${min % 60}m" else ""}"

// ── Sheet head ──────────────────────────────────────────────────────────────────

@Composable
internal fun BookSheetHead(title: String, sub: String, onClose: () -> Unit) {
  Column(Modifier.fillMaxWidth()) {
    Box(Modifier.fillMaxWidth().padding(top = 8.dp), contentAlignment = Alignment.Center) {
      Box(Modifier.width(40.dp).height(4.dp).clip(RoundedCornerShape(2.dp)).background(Color(0xFFDDE3EC)))
    }
    Row(
      modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 12.dp, top = 8.dp, bottom = 6.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Column(Modifier.weight(1f)) {
        Text(title, color = BookInk.Ink, fontWeight = FontWeight.Bold, fontSize = 18.sp, letterSpacing = (-0.36).sp)
        Text(
          sub, color = BookInk.Muted, fontWeight = FontWeight.Medium, fontSize = 12.5.sp,
          maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 1.dp),
        )
      }
      Box(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT, onClick = onClose)
          .size(32.dp)
          .clip(CircleShape)
          .background(BookInk.Chip),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.Close, "Close", tint = BookInk.Slate, modifier = Modifier.size(16.dp))
      }
    }
  }
}

// ── Date strip ──────────────────────────────────────────────────────────────────

/**
 * "Wednesday, 21 Oct" with a Pick-a-date pill, then two weeks of days to tap. Days past
 * the person's booking window fade and can't be pressed. A calendar pick past the
 * fortnight is appended after a dashed rule, like the site's `is-custom` pill.
 */
@Composable
internal fun BookDateStrip(
  selected: LocalDate,
  lastDate: LocalDate?,
  onPick: (LocalDate) -> Unit,
  onOpenCalendar: () -> Unit,
) {
  val today = LocalDate.now()
  val days = (0 until 14).map { today.plusDays(it.toLong()) }
  Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
    Text(
      selected.format(DateTimeFormatter.ofPattern("EEEE, d MMM", Locale.ENGLISH)),
      color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 15.sp, letterSpacing = (-0.15).sp,
      modifier = Modifier.weight(1f),
    )
    Row(
      modifier = Modifier
        .pressable(haptic = Feel.SELECT, onClick = onOpenCalendar)
        .height(34.dp)
        .clip(RoundedCornerShape(99.dp))
        .background(Color.White)
        .border(1.dp, Color(0xFFDBE3EE), RoundedCornerShape(99.dp))
        .padding(horizontal = 12.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Icon(Icons.Default.CalendarMonth, null, tint = BookInk.Blue, modifier = Modifier.size(16.dp))
      Spacer(Modifier.width(6.dp))
      Text("Pick a date", color = BookInk.Blue, fontWeight = FontWeight.Bold, fontSize = 13.sp)
    }
  }
  Spacer(Modifier.height(10.dp))
  Box(Modifier.fillMaxWidth()) {
    // The strip's hairline sits under the pills; the active pill's bar draws over it.
    Box(Modifier.align(Alignment.BottomStart).fillMaxWidth().height(1.dp).background(BookInk.Hair))
    Row(
      modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
      horizontalArrangement = Arrangement.spacedBy(2.dp),
    ) {
      days.forEachIndexed { i, d ->
        val locked = lastDate != null && d.isAfter(lastDate)
        DatePill(
          day = when {
            i == 0 -> "Today"
            d.dayOfMonth == 1 -> d.format(DateTimeFormatter.ofPattern("MMM", Locale.ENGLISH))
            else -> d.format(DateTimeFormatter.ofPattern("EEE", Locale.ENGLISH))
          },
          date = d.dayOfMonth.toString(),
          active = d == selected,
          today = i == 0,
          monthStart = i != 0 && d.dayOfMonth == 1,
          locked = locked,
          onClick = { onPick(d) },
        )
      }
      if (selected.isAfter(days.last())) {
        Spacer(Modifier.width(6.dp))
        Box(
          Modifier
            .padding(vertical = 12.dp)
            .width(1.dp)
            .height(34.dp)
            .drawBehind {
              drawLine(
                Color(0xFFCBD5E1), Offset(0f, 0f), Offset(0f, size.height), 1.dp.toPx(),
                pathEffect = PathEffect.dashPathEffect(floatArrayOf(4f, 4f)),
              )
            },
        )
        Spacer(Modifier.width(6.dp))
        DatePill(
          day = selected.format(DateTimeFormatter.ofPattern("EEE", Locale.ENGLISH)),
          date = selected.dayOfMonth.toString(),
          active = true, today = false, monthStart = false, locked = false, onClick = {},
        )
      }
    }
  }
}

@Composable
private fun DatePill(
  day: String,
  date: String,
  active: Boolean,
  today: Boolean,
  monthStart: Boolean,
  locked: Boolean,
  onClick: () -> Unit,
) {
  val dayColor = when {
    active -> BookInk.Blue
    monthStart -> BookInk.Blue
    today -> BookInk.Ink
    else -> BookInk.Muted
  }
  Box(
    modifier = Modifier
      .then(if (locked) Modifier else Modifier.pressable(haptic = Feel.SELECT, onClick = onClick))
      .widthIn(min = 52.dp)
      .clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp))
      .background(if (active) BookInk.Blue.copy(alpha = 0.07f) else Color.Transparent)
      .alpha(if (locked) 0.35f else 1f),
    contentAlignment = Alignment.TopCenter,
  ) {
    Column(
      Modifier.padding(start = 6.dp, end = 6.dp, top = 8.dp, bottom = 12.dp),
      horizontalAlignment = Alignment.CenterHorizontally,
    ) {
      Text(
        if (monthStart) day.uppercase(Locale.ENGLISH) else day,
        color = dayColor,
        fontSize = 12.sp,
        fontWeight = when { monthStart -> FontWeight.ExtraBold; active || today -> FontWeight.Bold; else -> FontWeight.SemiBold },
        letterSpacing = if (monthStart) 0.5.sp else 0.sp,
        maxLines = 1,
      )
      Spacer(Modifier.height(4.dp))
      Text(date, color = if (active) BookInk.Blue else BookInk.Ink, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, lineHeight = 20.sp)
    }
    if (active) {
      Box(
        Modifier
          .align(Alignment.BottomCenter)
          .padding(horizontal = 12.dp)
          .fillMaxWidth()
          .height(3.dp)
          .clip(RoundedCornerShape(topStart = 3.dp, topEnd = 3.dp))
          .background(BookInk.Blue),
      )
    }
  }
}

// ── Section label ───────────────────────────────────────────────────────────────

@Composable
internal fun BookLabel(text: String, trailing: String? = null) {
  Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Bottom) {
    Text(
      text.uppercase(Locale.ENGLISH), color = BookInk.Muted, fontSize = 11.sp,
      fontWeight = FontWeight.Bold, letterSpacing = 0.88.sp, modifier = Modifier.weight(1f),
    )
    if (!trailing.isNullOrBlank()) Text(trailing, color = BookInk.Faint, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
  }
}

// ── Sport chips ─────────────────────────────────────────────────────────────────

@Composable
internal fun BookSportChips(
  sports: List<String>,
  selected: String,
  icon: (String) -> androidx.compose.ui.graphics.vector.ImageVector,
  onPick: (String) -> Unit,
) {
  Row(
    modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(vertical = 2.dp),
    horizontalArrangement = Arrangement.spacedBy(8.dp),
  ) {
    sports.forEach { s ->
      val on = s.equals(selected, ignoreCase = true)
      Row(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT) { onPick(s) }
          .height(40.dp)
          .clip(RoundedCornerShape(99.dp))
          .background(if (on) BookInk.BlueTint else Color.White)
          .border(1.dp, if (on) BookInk.Blue else Color(0xFFE1E7EF), RoundedCornerShape(99.dp))
          .padding(horizontal = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        Icon(icon(s), null, tint = if (on) BookInk.Blue else BookInk.Faint, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(7.dp))
        Text(s, color = BookInk.Ink, fontWeight = if (on) FontWeight.ExtraBold else FontWeight.SemiBold, fontSize = 13.5.sp)
      }
    }
  }
}

// ── Court cards ─────────────────────────────────────────────────────────────────

/** A court card: its drawing, its name, its own rate, its peak rate when it has one. */
@Composable
internal fun CourtCard(name: String, rate: Int, peakRate: Int?, active: Boolean, onClick: () -> Unit) {
  Box(Modifier.padding(top = 6.dp, end = 6.dp)) {
    Row(
      modifier = Modifier
        .pressable(haptic = Feel.SELECT, onClick = onClick)
        .then(
          if (active) Modifier.shadow(10.dp, RoundedCornerShape(16.dp), ambientColor = BookInk.Blue, spotColor = BookInk.Blue.copy(alpha = 0.5f))
          else Modifier.shadow(2.dp, RoundedCornerShape(16.dp), ambientColor = Color(0x220F172A), spotColor = Color(0x220F172A))
        )
        .widthIn(min = 128.dp)
        .clip(RoundedCornerShape(16.dp))
        .background(if (active) BookInk.BlueTint else Color.White)
        .border(1.dp, if (active) BookInk.Blue else Color(0xFFE1E7EF), RoundedCornerShape(16.dp))
        .padding(start = 9.dp, end = 14.dp, top = 9.dp, bottom = 9.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Box(
        Modifier
          .size(width = 36.dp, height = 46.dp)
          .clip(RoundedCornerShape(10.dp))
          .background(if (active) BookInk.Blue else Color(0xFFF1F5FA)),
        contentAlignment = Alignment.Center,
      ) {
        CourtDrawing(color = if (active) Color.White else BookInk.Faint, modifier = Modifier.size(width = 22.dp, height = 32.dp))
      }
      Spacer(Modifier.width(10.dp))
      Column {
        Text(name, color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.sp, maxLines = 1, letterSpacing = (-0.14).sp)
        Spacer(Modifier.height(2.dp))
        Row(verticalAlignment = Alignment.Bottom) {
          Text("₹${inr(rate)}", color = BookInk.Ink2, fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
          Text("/hr", color = BookInk.Faint, fontWeight = FontWeight.Medium, fontSize = 12.5.sp)
        }
        if (peakRate != null) {
          Text("peak ₹${inr(peakRate)}", color = BookInk.Amber, fontWeight = FontWeight.Bold, fontSize = 10.5.sp, maxLines = 1)
        }
      }
    }
    if (active) {
      Box(
        Modifier
          .align(Alignment.TopEnd)
          .offset(x = 5.dp, y = (-5).dp)
          .size(18.dp)
          .clip(CircleShape)
          .background(BookInk.Blue),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.Check, null, tint = Color.White, modifier = Modifier.size(12.dp))
      }
    }
  }
}

/** A court seen from above: the outline, the halfway line, the centre circle. */
@Composable
internal fun CourtDrawing(color: Color, modifier: Modifier = Modifier) {
  Canvas(modifier) {
    val s = 1.6.dp.toPx()
    drawRoundRect(color, Offset(s / 2, s / 2), Size(size.width - s, size.height - s), CornerRadius(3.dp.toPx()), style = Stroke(s))
    drawLine(color, Offset(s, size.height / 2), Offset(size.width - s, size.height / 2), s)
    drawCircle(color, radius = size.width * 0.2f, center = center, style = Stroke(s))
    val crease = size.height * 0.16f
    drawLine(color.copy(alpha = 0.6f), Offset(size.width * 0.3f, crease), Offset(size.width * 0.7f, crease), s, cap = StrokeCap.Round)
    drawLine(color.copy(alpha = 0.6f), Offset(size.width * 0.3f, size.height - crease), Offset(size.width * 0.7f, size.height - crease), s, cap = StrokeCap.Round)
  }
}

// ── Part-of-day control ─────────────────────────────────────────────────────────

internal data class PartSummary(val part: DayPart, val total: Int, val open: Int, val booked: Int, val hasPick: Boolean)

/** One segmented control that filters the grid; its white thumb slides to the active part. */
@Composable
internal fun DayPartControl(parts: List<PartSummary>, active: DayPart?, onPick: (DayPart) -> Unit) {
  BoxWithConstraints(
    Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(12.dp))
      .background(Color(0xFFEEF1F5))
      .padding(3.dp),
  ) {
    val segW = maxWidth / parts.size
    val idx = parts.indexOfFirst { it.part == active }.coerceAtLeast(0)
    val x by animateDpAsState(segW * idx, tween(280), label = "segThumb")
    if (active != null) {
      Box(
        Modifier
          .offset(x = x)
          .width(segW)
          .height(43.dp)
          .shadow(3.dp, RoundedCornerShape(9.dp), ambientColor = Color(0x330F172A), spotColor = Color(0x330F172A))
          .clip(RoundedCornerShape(9.dp))
          .background(Color.White),
      )
    }
    Row(Modifier.fillMaxWidth()) {
      parts.forEach { p ->
        val on = p.part == active
        val none = p.open == 0
        val sub = when {
          p.open > 0 -> "${p.open} open"
          p.total > 0 && p.booked > 0 -> "Full"
          else -> "Closed"
        }
        Column(
          modifier = Modifier
            .weight(1f)
            .height(43.dp)
            .then(if (p.total == 0) Modifier else Modifier.pressable(haptic = Feel.SELECT) { onPick(p.part) }),
          horizontalAlignment = Alignment.CenterHorizontally,
          verticalArrangement = Arrangement.Center,
        ) {
          Box {
            Text(
              p.part.label,
              color = when { on -> BookInk.Ink; none -> Color(0xFFB6C0CE); else -> BookInk.Muted },
              fontWeight = if (on) FontWeight.ExtraBold else FontWeight.Bold,
              fontSize = 13.sp, letterSpacing = (-0.13).sp,
            )
            if (p.hasPick) {
              Box(Modifier.align(Alignment.TopEnd).offset(x = 8.dp, y = 1.dp).size(6.dp).clip(CircleShape).background(BookInk.Blue))
            }
          }
          Text(
            sub,
            color = if (on && p.open > 0) BookInk.Green else BookInk.Faint,
            fontWeight = FontWeight.SemiBold, fontSize = 11.sp,
          )
        }
      }
    }
  }
}

// ── Time cards ──────────────────────────────────────────────────────────────────

/** The grid of time cards, three to a row. */
@Composable
internal fun SlotTileGrid(
  tiles: List<SlotTile>,
  picked: Set<Int>,
  soonId: Int?,
  soonText: String?,
  onTap: (SlotTile) -> Unit,
) {
  Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
    tiles.chunked(3).forEach { row ->
      Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        row.forEach { t ->
          Box(Modifier.weight(1f)) {
            SlotTileCard(t, picked = t.slot.id in picked, soon = if (t.slot.id == soonId) soonText else null) { onTap(t) }
          }
        }
        repeat(3 - row.size) { Spacer(Modifier.weight(1f)) }
      }
    }
  }
}

/** Skeleton while the first availability read is in flight — not yesterday's guesses. */
@Composable
internal fun SlotTileSkeleton(count: Int = 6) {
  Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
    (0 until count).chunked(3).forEach { row ->
      Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        row.forEach { _ ->
          Box(
            Modifier.weight(1f).height(52.dp).clip(RoundedCornerShape(12.dp))
              .background(Color(0xFFF4F7FB)).border(1.dp, Color(0xFFE8EDF4), RoundedCornerShape(12.dp)),
          )
        }
        repeat(3 - row.size) { Spacer(Modifier.weight(1f)) }
      }
    }
  }
}

@Composable
private fun SlotTileCard(t: SlotTile, picked: Boolean, soon: String?, onClick: () -> Unit) {
  val taken = t.state != SlotTileState.OPEN
  val (clock, ampm) = clockParts(t.startMin)
  val shape = RoundedCornerShape(12.dp)
  Box(
    modifier = Modifier
      .then(if (taken) Modifier else Modifier.pressable(haptic = Feel.SELECT, onClick = onClick))
      .then(
        when {
          picked -> Modifier.shadow(8.dp, shape, ambientColor = BookInk.Blue, spotColor = BookInk.Blue.copy(alpha = 0.7f))
          !taken -> Modifier.shadow(1.dp, shape, ambientColor = Color(0x140F172A), spotColor = Color(0x140F172A))
          else -> Modifier
        }
      )
      .fillMaxWidth()
      .heightIn(min = 52.dp)
      .clip(shape)
      .background(
        when {
          picked -> BookInk.Blue
          taken -> Color(0xFFF6F8FB)
          else -> Color.White
        }
      )
      .then(
        when {
          picked -> Modifier.border(1.dp, BookInk.Blue, shape)
          taken -> Modifier.drawBehind {
            drawRoundRect(
              Color(0xFFD5DDE8), cornerRadius = CornerRadius(12.dp.toPx()),
              style = Stroke(1.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(8f, 6f))),
            )
          }
          else -> Modifier.border(1.dp, Color(0xFFDFE5EE), shape)
        }
      )
      .padding(horizontal = 6.dp, vertical = 8.dp),
    contentAlignment = Alignment.Center,
  ) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
      Row(verticalAlignment = Alignment.Bottom) {
        Text(
          clock,
          color = when { picked -> Color.White; taken -> Color(0xFFA3AFC0); else -> BookInk.Ink },
          fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, letterSpacing = (-0.34).sp,
          textDecoration = if (taken) TextDecoration.LineThrough else null,
        )
        Text(
          ampm,
          color = if (picked) Color.White.copy(alpha = 0.8f) else BookInk.Faint,
          fontWeight = FontWeight.Bold, fontSize = 10.5.sp, letterSpacing = 0.3.sp,
          modifier = Modifier.padding(start = 3.dp, bottom = 2.dp),
        )
      }
      // The price is said once, in the rate line. A card only carries one when it
      // differs (peak), or the word for why it can't be taken.
      val sub = when (t.state) {
        SlotTileState.BOOKED -> "Booked"
        SlotTileState.CLOSED -> "Closed"
        SlotTileState.OPEN -> if (t.isPeak) "₹${inr(t.rate)}" else null
      }
      when {
        sub != null -> Text(
          sub,
          color = when { picked -> Color.White.copy(alpha = 0.8f); t.isPeak && !taken -> BookInk.Amber; else -> BookInk.Faint },
          fontWeight = if (t.isPeak && !taken) FontWeight.Bold else FontWeight.SemiBold, fontSize = 11.5.sp,
        )
        soon != null -> Text(
          soon, color = if (picked) Color.White.copy(alpha = 0.8f) else BookInk.Blue,
          fontWeight = FontWeight.Bold, fontSize = 11.sp,
        )
      }
    }
    if (picked) {
      Box(
        Modifier.align(Alignment.TopEnd).offset(x = 1.dp, y = (-1).dp).size(16.dp).clip(CircleShape).background(Color.White),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.Check, null, tint = BookInk.Blue, modifier = Modifier.size(11.dp))
      }
    }
  }
}

// ── Dock: ticket stub + Checkout key ────────────────────────────────────────────

/**
 * What you picked, drawn as a pass: a date tear-off, a perforation with two punched
 * notches, the picked window, the court stamped on the right. No money — the bill is
 * the review page's job.
 */
@Composable
internal fun TicketStub(
  date: LocalDate,
  what: String,
  range: String?,
  ampm: String?,
  facts: String?,
  courtName: String?,
  onClear: (() -> Unit)?,
) {
  val filled = range != null
  val edge = if (filled) Color(0xFFCFDCF3) else Color(0xFFE1E8F4)
  Box(Modifier.fillMaxWidth().padding(top = 8.dp)) {
    Row(
      modifier = Modifier
        .then(if (filled) Modifier.shadow(6.dp, RoundedCornerShape(16.dp), ambientColor = BookInk.Blue.copy(alpha = 0.4f), spotColor = BookInk.Blue.copy(alpha = 0.4f)) else Modifier)
        .fillMaxWidth()
        .height(82.dp)
        .clip(RoundedCornerShape(16.dp))
        .background(if (filled) Color.White else Color(0xFFF7F9FD))
        .border(1.dp, edge, RoundedCornerShape(16.dp))
        .drawBehind {
          // Perforation after the date tear-off, and the two notches punched from it.
          val x = 70.dp.toPx()
          drawLine(
            Color(0xFFD5DEEC), Offset(x, 10.dp.toPx()), Offset(x, size.height - 10.dp.toPx()), 1.5.dp.toPx(),
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(6f, 5f)),
          )
          val r = 7.dp.toPx()
          drawCircle(Color.White, r, Offset(x, 0f))
          drawCircle(edge, r, Offset(x, 0f), style = Stroke(1.dp.toPx()))
          drawCircle(Color.White, r, Offset(x, size.height))
          drawCircle(edge, r, Offset(x, size.height), style = Stroke(1.dp.toPx()))
        },
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Column(Modifier.width(70.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
          date.format(DateTimeFormatter.ofPattern("EEE", Locale.ENGLISH)).uppercase(Locale.ENGLISH),
          color = if (filled) BookInk.Blue else BookInk.Muted, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.2.sp,
        )
        Text(date.dayOfMonth.toString(), color = BookInk.Ink, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.72).sp, lineHeight = 26.sp)
        Text(
          date.format(DateTimeFormatter.ofPattern("MMM", Locale.ENGLISH)).uppercase(Locale.ENGLISH),
          color = BookInk.Muted, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.2.sp,
        )
      }
      Column(Modifier.weight(1f).padding(start = 14.dp, end = 6.dp)) {
        Text(
          what.uppercase(Locale.ENGLISH), color = if (filled) BookInk.Blue else BookInk.Faint,
          fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.26.sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.height(3.dp))
        if (range != null) {
          Row(verticalAlignment = Alignment.Bottom) {
            Text(range, color = BookInk.Ink, fontSize = 21.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.63).sp, maxLines = 1)
            if (!ampm.isNullOrBlank()) {
              Text(ampm, color = BookInk.Muted, fontSize = 11.5.sp, fontWeight = FontWeight.ExtraBold, modifier = Modifier.padding(start = 4.dp, bottom = 3.dp))
            }
          }
        } else {
          Text("Tap an open time above", color = BookInk.Faint, fontSize = 15.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.15).sp)
        }
        if (!facts.isNullOrBlank()) {
          Spacer(Modifier.height(3.dp))
          Text(facts, color = BookInk.Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
      }
      Column(
        Modifier.width(58.dp).padding(end = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
      ) {
        val c = if (filled) BookInk.Blue else Color(0xFFCBD5E1)
        CourtDrawing(color = c, modifier = Modifier.size(width = 26.dp, height = 36.dp).rotate(-4f))
        if (!courtName.isNullOrBlank()) {
          Spacer(Modifier.height(2.dp))
          Text(
            courtName.uppercase(Locale.ENGLISH), color = c, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold,
            letterSpacing = 0.8.sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
          )
        }
      }
    }
    if (filled && onClear != null) {
      Box(
        Modifier
          .align(Alignment.TopEnd)
          .offset(x = (-6).dp, y = (-10).dp)
          .pressable(haptic = Feel.SELECT, onClick = onClear)
          .shadow(3.dp, CircleShape)
          .size(24.dp)
          .clip(CircleShape)
          .background(Color.White)
          .border(1.dp, Color(0xFFCFDCF3), CircleShape),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.Close, "Clear picked slots", tint = BookInk.Muted, modifier = Modifier.size(12.dp))
      }
    }
  }
}

/** The Checkout key: a pass sitting in a slot on the left, the arrow on the right, a lip underneath. */
@Composable
internal fun CheckoutKey(label: String, enabled: Boolean, busy: Boolean, onClick: () -> Unit) {
  val on = enabled && !busy
  Box(Modifier.fillMaxWidth().height(59.dp)) {
    // The 3dp lip the key sits on; a press would close it.
    if (enabled) {
      Box(Modifier.fillMaxWidth().height(56.dp).offset(y = 3.dp).clip(RoundedCornerShape(16.dp)).background(BookInk.BlueDeep))
    }
    Row(
      modifier = Modifier
        .then(if (on) Modifier.pressable(haptic = Feel.COMMIT, onClick = onClick) else Modifier)
        .fillMaxWidth()
        .height(56.dp)
        .clip(RoundedCornerShape(16.dp))
        .background(if (enabled) BookInk.Blue else Color(0xFFE8EDF5))
        .padding(horizontal = 8.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Box(
        Modifier.size(width = 44.dp, height = 40.dp).clip(RoundedCornerShape(11.dp))
          .background(if (enabled) Color.White.copy(alpha = 0.16f) else Color(0xFFDDE4EF)),
        contentAlignment = Alignment.Center,
      ) {
        PassGlyph(enabled, Modifier.size(width = 30.dp, height = 21.dp).rotate(-8f))
      }
      Spacer(Modifier.width(12.dp))
      Text(
        label, color = if (enabled) Color.White else BookInk.Faint,
        fontSize = 16.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.16).sp,
        modifier = Modifier.weight(1f), maxLines = 1,
      )
      Box(
        Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(if (enabled) Color.White else Color(0xFFDDE4EF)),
        contentAlignment = Alignment.Center,
      ) {
        if (busy) {
          CircularProgressIndicator(color = BookInk.Blue, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
        } else {
          ArrowGlyph(if (enabled) BookInk.Blue else Color(0xFFA3AFC0), Modifier.size(18.dp))
        }
      }
    }
  }
}

/** The little entry pass on the Checkout key — the site's SVG, redrawn. */
@Composable
private fun PassGlyph(enabled: Boolean, modifier: Modifier) {
  Canvas(modifier) {
    val w = size.width; val h = size.height
    val ux = w / 32f; val uy = h / 22f
    val body = Path().apply {
      moveTo(3 * ux, 2 * uy); lineTo(29 * ux, 2 * uy); lineTo(30 * ux, 3 * uy); lineTo(30 * ux, 7.5f * uy)
      cubicTo(26 * ux, 8 * uy, 26 * ux, 14 * uy, 30 * ux, 14.5f * uy)
      lineTo(30 * ux, 19 * uy); lineTo(29 * ux, 20 * uy); lineTo(3 * ux, 20 * uy); lineTo(2 * ux, 19 * uy); lineTo(2 * ux, 14.5f * uy)
      cubicTo(6 * ux, 14 * uy, 6 * ux, 8 * uy, 2 * ux, 7.5f * uy)
      lineTo(2 * ux, 3 * uy); close()
    }
    val ink = if (enabled) BookInk.Blue else Color(0xFFA3AFC0)
    drawPath(body, if (enabled) Color.White else Color(0xFFF1F4F9))
    drawLine(Color(0xFF93B4F5), Offset(21 * ux, 3.5f * uy), Offset(21 * ux, 18.5f * uy), 1.4f * ux, pathEffect = PathEffect.dashPathEffect(floatArrayOf(1.6f * ux, 1.8f * ux)))
    val sw = 1.6f * ux
    drawLine(ink, Offset(7 * ux, 8 * uy), Offset(16 * ux, 8 * uy), sw, cap = StrokeCap.Round)
    drawLine(ink, Offset(7 * ux, 11 * uy), Offset(13 * ux, 11 * uy), sw, cap = StrokeCap.Round)
    drawLine(ink, Offset(7 * ux, 14 * uy), Offset(15 * ux, 14 * uy), sw, cap = StrokeCap.Round)
    drawCircle(ink, 2 * ux, Offset(25.5f * ux, 11 * uy))
  }
}

@Composable
internal fun ArrowGlyph(color: Color, modifier: Modifier) {
  Canvas(modifier) {
    val s = 2.6.dp.toPx()
    val w = size.width; val h = size.height
    drawLine(color, Offset(w * 0.21f, h / 2), Offset(w * 0.75f, h / 2), s, cap = StrokeCap.Round)
    drawLine(color, Offset(w * 0.54f, h * 0.25f), Offset(w * 0.79f, h / 2), s, cap = StrokeCap.Round)
    drawLine(color, Offset(w * 0.54f, h * 0.75f), Offset(w * 0.79f, h / 2), s, cap = StrokeCap.Round)
  }
}

// ── Review page bits ────────────────────────────────────────────────────────────

/** A white card on the review page's grey: hairline border, 16dp corners. */
@Composable
internal fun ReviewCard(content: @Composable () -> Unit) {
  Column(
    Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(16.dp))
      .background(Color.White)
      .border(BorderStroke(1.dp, Color(0xFFE6ECF3)), RoundedCornerShape(16.dp))
      .padding(16.dp),
  ) { content() }
}

/** A price-details row: label left, amount right in bold ink. */
@Composable
internal fun ReviewRow(
  label: String,
  value: String,
  labelColor: Color = BookInk.Ink2,
  valueColor: Color = BookInk.Ink,
  size: Float = 14.5f,
  bold: Boolean = false,
  sub: (@Composable () -> Unit)? = null,
) {
  Row(
    Modifier.fillMaxWidth().padding(vertical = 10.dp),
    horizontalArrangement = Arrangement.SpaceBetween,
    verticalAlignment = Alignment.Top,
  ) {
    Column(Modifier.weight(1f)) {
      Text(label, color = labelColor, fontSize = size.sp, fontWeight = if (bold) FontWeight.ExtraBold else FontWeight.Normal)
      sub?.invoke()
    }
    Spacer(Modifier.width(12.dp))
    Text(value, color = valueColor, fontSize = (if (bold) size + 1 else size).sp, fontWeight = if (bold) FontWeight.ExtraBold else FontWeight.Bold, maxLines = 1)
  }
}

internal fun inr(v: Int): String = java.text.NumberFormat.getIntegerInstance(Locale("en", "IN")).format(v)
