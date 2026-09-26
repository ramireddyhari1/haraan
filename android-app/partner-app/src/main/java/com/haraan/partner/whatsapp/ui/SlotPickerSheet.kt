package com.haraan.partner.whatsapp.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.whatsapp.model.BookingSuggestion
import com.haraan.partner.whatsapp.model.CourtSlotAvailability
import com.haraan.partner.whatsapp.model.DayAvailability

/**
 * Pick the court and time to hold for a customer: a day, a court, how long, then a start
 * time. Times come from the venue's own hours and are greyed out where the court is
 * already booked, held or blocked for the whole length chosen — the same rule the hold
 * itself is checked against.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun SlotPickerSheet(
    availability: DayAvailability?,
    loading: Boolean,
    error: String?,
    busy: Boolean,
    initial: BookingSuggestion?,
    onDateChange: (String) -> Unit,
    onHold: (courtId: Long, date: String, start: String, end: String) -> Unit,
    onDismiss: () -> Unit
) {
    val view = LocalView.current
    val days = remember { DeskTime.nextDays(7) }
    var date by remember { mutableStateOf(initial?.date?.takeIf { it in days } ?: days.first()) }
    var courtId by remember { mutableStateOf(initial?.courtId) }
    var length by remember {
        mutableStateOf(
            initial?.let { s ->
                val a = s.startTime?.let(DeskTime::minutes)
                val b = s.endTime?.let(DeskTime::minutes)
                if (a != null && b != null && b > a) b - a else null
            } ?: 60
        )
    }
    var start by remember { mutableStateOf(initial?.startTime?.take(5)) }

    LaunchedEffect(date) { onDateChange(date) }

    val courts = availability?.takeIf { it.date == date }?.courts.orEmpty()
    LaunchedEffect(courts) {
        if (courts.isNotEmpty() && courts.none { it.courtId == courtId }) courtId = courts.first().courtId
    }
    val court = courts.firstOrNull { it.courtId == courtId }
    val step = availability?.slotMinutes ?: 60
    val lengths = listOf(60, 90, 120).filter { it >= step }.ifEmpty { listOf(step) }

    // A start works only if every step it covers is free.
    fun fits(slot: CourtSlotAvailability): Boolean {
        val from = DeskTime.minutes(slot.startTime) ?: return false
        val cells = court?.slots.orEmpty()
        var t = from
        while (t < from + length) {
            val cell = cells.firstOrNull { DeskTime.minutes(it.startTime) == t } ?: return false
            if (!cell.isAvailable) return false
            t += step
        }
        return from + length <= 24 * 60
    }

    val chosen = court?.slots?.firstOrNull { it.startTime.take(5) == start }?.takeIf(::fits)
    val price = chosen?.let { it.price / step * length }

    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(
            Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 18.dp)
                .padding(bottom = 24.dp)
        ) {
            Text("Hold a slot", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = WhatsAppDeskColors.TextPrimary)
            Text(
                "The court is kept for this customer while they pay.",
                fontSize = 12.sp,
                color = WhatsAppDeskColors.TextSecondary
            )

            SectionLabel("Day")
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                days.forEach { d ->
                    PickChip(DeskTime.day(d), selected = d == date) {
                        Haptics.tick(view)
                        date = d
                        start = null
                    }
                }
            }

            when {
                loading && courts.isEmpty() -> Box(Modifier.fillMaxWidth().padding(28.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = WhatsAppDeskColors.Action, strokeWidth = 2.dp)
                }
                error != null && courts.isEmpty() -> Notice(error)
                availability?.hoursMissing == true -> Notice("This venue has no opening hours or slot times set, so there are no times to offer. Add them in venue settings.")
                courts.isEmpty() && !loading -> Notice("This venue has no active courts.")
                else -> {
                    SectionLabel("Court")
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        courts.forEach { c ->
                            PickChip(c.courtName, selected = c.courtId == courtId) {
                                Haptics.tick(view)
                                courtId = c.courtId
                            }
                        }
                    }

                    SectionLabel("How long")
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        lengths.forEach { l ->
                            val label = if (l % 60 == 0) "${l / 60} hr" else "${l / 60}.5 hr"
                            PickChip(label, selected = l == length) {
                                Haptics.tick(view)
                                length = l
                            }
                        }
                    }

                    SectionLabel("Start")
                    val slots = court?.slots.orEmpty().filterNot { it.isPast }
                    if (slots.isEmpty()) {
                        Notice("No times left on this day.")
                    } else {
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            slots.forEach { slot ->
                                val ok = fits(slot)
                                PickChip(
                                    slot.timeLabel,
                                    selected = ok && slot.startTime.take(5) == start,
                                    enabled = ok
                                ) {
                                    Haptics.tick(view)
                                    start = slot.startTime.take(5)
                                }
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            DeskPrimaryButton(
                text = if (chosen != null && court != null) {
                    "Hold ${court.courtName} · ${DeskTime.slot(chosen.startTime)}" + (price?.let { " · ${DeskTime.rupees(it)}" } ?: "")
                } else "Pick a start time",
                busy = busy,
                enabled = chosen != null && court != null,
                onClick = {
                    val c = court ?: return@DeskPrimaryButton
                    val s = chosen ?: return@DeskPrimaryButton
                    val from = DeskTime.minutes(s.startTime) ?: return@DeskPrimaryButton
                    val to = from + length
                    onHold(c.courtId, date, DeskTime.hms(from), if (to >= 24 * 60) "24:00:00" else DeskTime.hms(to))
                },
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text.uppercase(),
        fontSize = 11.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 0.6.sp,
        color = WhatsAppDeskColors.TextSecondary,
        modifier = Modifier.padding(top = 18.dp, bottom = 8.dp)
    )
}

@Composable
private fun Notice(text: String) {
    Text(text, fontSize = 13.sp, color = WhatsAppDeskColors.TextSecondary, modifier = Modifier.padding(vertical = 18.dp))
}

/** Selection is a blue tint and border, never a solid fill — that would read as a button. */
@Composable
private fun PickChip(label: String, selected: Boolean, enabled: Boolean = true, onClick: () -> Unit) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = when {
            selected -> WhatsAppDeskColors.ActionTint
            enabled -> Color.White
            else -> WhatsAppDeskColors.SlateLight
        },
        border = BorderStroke(1.dp, if (selected) WhatsAppDeskColors.Action else WhatsAppDeskColors.SlateBorder),
        modifier = Modifier.clickable(enabled = enabled, onClick = onClick)
    ) {
        Text(
            label,
            fontSize = 13.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
            color = when {
                selected -> WhatsAppDeskColors.Action
                enabled -> WhatsAppDeskColors.TextPrimary
                else -> WhatsAppDeskColors.TextSecondary.copy(alpha = 0.5f)
            },
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)
        )
    }
}
