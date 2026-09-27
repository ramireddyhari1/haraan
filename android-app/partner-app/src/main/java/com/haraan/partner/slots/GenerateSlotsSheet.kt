package com.haraan.partner.slots

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.GenerateResult
import com.haraan.partner.PartnerApi
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.launch

private val Blue = Color(0xFF1D4ED8)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFF9AA3B2)
private val Fill = Color(0xFFF4F6FA)
private val Hairline = Color(0xFFE6EAF0)
private val Red = Color(0xFFDC2626)
private val Amber = Color(0xFFB45309)

private const val EVERY_DAY = "Every day"
private val WEEKDAYS = listOf("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")

/** "6:00 AM" for minutes-from-midnight; 1440 (midnight at the end of the day) is "12:00 AM". */
internal fun clockLabel(minutes: Int): String {
    val h = (minutes / 60) % 24
    val m = minutes % 60
    val h12 = if (h % 12 == 0) 12 else h % 12
    return "%d:%02d %s".format(h12, m, if (h < 12) "AM" else "PM")
}

/**
 * "Generate slots": opening time, closing time, 30-minute or 1-hour slots, which days,
 * an optional price, and whether to keep or replace what's there — then every slot in
 * between is created at once. The button says exactly how many it will make.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun GenerateSlotsSheet(
    api: PartnerApi,
    token: String,
    venueId: Long,
    existingCount: Int,
    onDismiss: () -> Unit,
    onGenerated: (GenerateResult) -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val view = LocalView.current
    val scope = rememberCoroutineScope()

    var step by remember { mutableStateOf(60) }
    var openMin by remember { mutableStateOf(6 * 60) }
    var closeMin by remember { mutableStateOf(23 * 60) }
    var days by remember { mutableStateOf(setOf(EVERY_DAY)) }
    var price by remember { mutableStateOf("") }
    var replace by remember { mutableStateOf(false) }
    var pickingOpen by remember { mutableStateOf(false) }
    var pickingClose by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var confirmReplace by remember { mutableStateOf(false) }

    // What the button will make, before it makes it.
    val close = if (closeMin == 0) 24 * 60 else closeMin
    val perDay = if (close > openMin) (close - openMin) / step else 0
    val dayCount = if (EVERY_DAY in days) 1 else days.size
    val total = perDay * dayCount
    val lastStart = openMin + (perDay - 1) * step
    val valid = perDay > 0 && dayCount > 0

    fun submit() {
        if (!valid || busy) return
        if (replace && !confirmReplace) {
            Haptics.warn(view); confirmReplace = true; return
        }
        busy = true
        error = null
        scope.launch {
            runCatching {
                api.generateSlots(
                    token, venueId,
                    open = clockLabel(openMin),
                    close = clockLabel(closeMin),
                    stepMinutes = step,
                    days = if (EVERY_DAY in days) listOf(EVERY_DAY) else WEEKDAYS.filter { it in days },
                    price = price.toDoubleOrNull()?.takeIf { it > 0 },
                    mode = if (replace) "replace" else "add",
                )
            }.onSuccess { Haptics.confirm(view); onGenerated(it) }
                .onFailure { Haptics.reject(view); error = it.message ?: "Couldn't generate slots" }
            busy = false
        }
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(
            Modifier.fillMaxWidth().navigationBarsPadding().imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp).padding(bottom = 14.dp),
        ) {
            Row(verticalAlignment = Alignment.Top) {
                Column(Modifier.weight(1f)) {
                    Text("GENERATE SLOTS", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Blue, letterSpacing = 1.2.sp)
                    Spacer(Modifier.height(6.dp))
                    Text("Your whole day in one tap", fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
                }
                IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, contentDescription = "Close", tint = Muted) }
            }

            Spacer(Modifier.height(18.dp))
            Label("Slot length")
            Spacer(Modifier.height(8.dp))
            Segments(
                options = listOf(30 to "30 minutes", 60 to "1 hour"),
                selected = step,
            ) { Haptics.tick(view); step = it }

            Spacer(Modifier.height(18.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                TimeField("Opens", clockLabel(openMin), Modifier.weight(1f)) { Haptics.tick(view); pickingOpen = true }
                TimeField("Closes", clockLabel(closeMin), Modifier.weight(1f)) { Haptics.tick(view); pickingClose = true }
            }

            Spacer(Modifier.height(18.dp))
            Label("Days")
            Spacer(Modifier.height(8.dp))
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                DayChip(EVERY_DAY, EVERY_DAY in days) {
                    Haptics.tick(view); days = setOf(EVERY_DAY)
                }
                WEEKDAYS.forEach { d ->
                    DayChip(d.take(3), d in days) {
                        Haptics.tick(view)
                        val picked = (days - EVERY_DAY).let { if (d in it) it - d else it + d }
                        days = picked.ifEmpty { setOf(EVERY_DAY) }
                    }
                }
            }

            Spacer(Modifier.height(18.dp))
            Label("Price per slot")
            Spacer(Modifier.height(8.dp))
            Row(
                Modifier.fillMaxWidth().height(54.dp).clip(RoundedCornerShape(16.dp)).background(Fill)
                    .border(1.dp, Hairline, RoundedCornerShape(16.dp)).padding(horizontal = 16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("₹", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
                Spacer(Modifier.width(10.dp))
                Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
                    if (price.isEmpty()) Text("Court rate", fontSize = 15.sp, color = Faint)
                    BasicTextField(
                        value = price,
                        onValueChange = { v -> price = v.filter { it.isDigit() }.take(6) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        textStyle = TextStyle(fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink),
                        cursorBrush = SolidColor(Blue),
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
            Text("Leave empty to charge each court's own rate (peak pricing still applies).", fontSize = 12.sp, color = Muted, modifier = Modifier.padding(start = 4.dp, top = 6.dp))

            if (existingCount > 0) {
                Spacer(Modifier.height(18.dp))
                Label("Your $existingCount existing slot${if (existingCount == 1) "" else "s"}")
                Spacer(Modifier.height(8.dp))
                Segments(
                    options = listOf(false to "Keep, add missing", true to "Replace all"),
                    selected = replace,
                ) { Haptics.tick(view); replace = it; confirmReplace = false }
                AnimatedVisibility(replace, enter = fadeIn() + expandVertically(), exit = fadeOut() + shrinkVertically()) {
                    Text(
                        "Removes all $existingCount slots and their prices, then builds these. Bookings already taken stay as they are.",
                        fontSize = 12.sp, color = Amber, modifier = Modifier.padding(start = 4.dp, top = 6.dp),
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            // The preview: exactly what the button will make.
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color(0xFFEFF4FF)).padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Outlined.Schedule, contentDescription = null, tint = Blue, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(10.dp))
                Text(
                    if (valid) "$perDay slot${if (perDay == 1) "" else "s"} a day · ${clockLabel(openMin)} → last starts ${clockLabel(lastStart)}" +
                        (if (dayCount > 1) " · $dayCount days" else if (EVERY_DAY in days) " · every day" else "")
                    else "Closing time must be at least one slot after opening.",
                    fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = if (valid) Ink else Red,
                )
            }

            error?.let {
                Spacer(Modifier.height(10.dp))
                Text(it, fontSize = 12.5.sp, color = Red, fontWeight = FontWeight.Medium)
            }

            Spacer(Modifier.height(16.dp))
            Cta(
                label = when {
                    confirmReplace -> "Tap again to replace with $total slots"
                    !valid -> "Generate slots"
                    else -> "Generate $total slot${if (total == 1) "" else "s"}"
                },
                enabled = valid && !busy,
                loading = busy,
                danger = confirmReplace,
                onClick = ::submit,
            )
        }
    }

    if (pickingOpen) ClockDialog("Opens at", openMin, onDismiss = { pickingOpen = false }) { openMin = it; pickingOpen = false }
    if (pickingClose) ClockDialog("Closes at", closeMin, onDismiss = { pickingClose = false }) { closeMin = it; pickingClose = false }
}

@Composable
private fun Label(text: String) = Text(text, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink)

@Composable
private fun TimeField(label: String, value: String, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Column(
        modifier.pressScale(interaction, 0.97f).clip(RoundedCornerShape(16.dp)).background(Fill)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp)).pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Text(label.uppercase(), fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 0.8.sp)
        Spacer(Modifier.height(4.dp))
        Text(value, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ClockDialog(title: String, minutes: Int, onDismiss: () -> Unit, onPick: (Int) -> Unit) {
    val state = rememberTimePickerState(initialHour = (minutes / 60) % 24, initialMinute = (minutes % 60 / 30) * 30, is24Hour = false)
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title, fontWeight = FontWeight.Bold) },
        text = { TimePicker(state = state) },
        confirmButton = {
            // Slots start on the hour or the half hour.
            TextButton(onClick = { onPick(state.hour * 60 + if (state.minute >= 30) 30 else 0) }) {
                Text("Set", fontWeight = FontWeight.Bold, color = Blue)
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel", color = Muted) } },
    )
}

@Composable
private fun DayChip(label: String, selected: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (selected) Ink else Color.White, tween(160), label = "day-bg")
    val fg by animateColorAsState(if (selected) Color.White else Ink, tween(160), label = "day-fg")
    Box(
        Modifier.pressScale(interaction, 0.93f).height(38.dp).clip(RoundedCornerShape(99.dp)).background(bg)
            .border(1.dp, if (selected) Ink else Hairline, RoundedCornerShape(99.dp)).pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 14.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = fg) }
}

/** A two-way pill that slides under the choice. */
@Composable
private fun <T> Segments(options: List<Pair<T, String>>, selected: T, onPick: (T) -> Unit) {
    val index = options.indexOfFirst { it.first == selected }.coerceAtLeast(0)
    BoxWithConstraints(
        Modifier.fillMaxWidth().height(50.dp).clip(RoundedCornerShape(16.dp)).background(Fill).padding(4.dp),
    ) {
        val w: Dp = maxWidth / options.size
        val x by animateDpAsState(w * index, spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessMediumLow), label = "seg")
        Box(Modifier.offset(x = x).width(w).fillMaxHeight().clip(RoundedCornerShape(12.dp)).background(Color.White).border(1.dp, Hairline, RoundedCornerShape(12.dp)))
        Row(Modifier.fillMaxWidth().fillMaxHeight()) {
            options.forEach { (value, label) ->
                Box(
                    Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(12.dp)).clickable { if (value != selected) onPick(value) },
                    contentAlignment = Alignment.Center,
                ) {
                    Text(label, fontSize = 13.5.sp, fontWeight = if (value == selected) FontWeight.Bold else FontWeight.SemiBold, color = if (value == selected) Ink else Muted)
                }
            }
        }
    }
}

@Composable
private fun Cta(label: String, enabled: Boolean, loading: Boolean, danger: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    // Action buttons are always brand blue; the replace confirm is marked by its words
    // and the amber warning above it, not by turning the button red.
    val bg by animateColorAsState(if (danger) Color(0xFF1E40AF) else Blue, tween(180), label = "cta")
    Row(
        Modifier.pressScale(interaction, 0.97f).fillMaxWidth().height(58.dp).clip(RoundedCornerShape(16.dp))
            .background(if (enabled || loading) bg else bg.copy(alpha = 0.45f)).pressShade(interaction, 0.12f)
            .clickable(interactionSource = interaction, indication = null, enabled = enabled, onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
        } else {
            Icon(Icons.Outlined.AutoAwesome, contentDescription = null, tint = Color.White, modifier = Modifier.size(19.dp))
        }
        Spacer(Modifier.width(10.dp))
        Text(label, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
    }
}
