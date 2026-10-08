package com.haraan.partner.daybookings.ui

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.waitForUpOrCancellation
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Block
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.DayGrid
import com.haraan.partner.DaySlot
import com.haraan.partner.daybookings.viewmodel.BlockTarget
import com.haraan.partner.slotStartMinutes
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch

private val Blue = Color(0xFF2563EB)
private val BlueDeep = Color(0xFF1E40AF)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFFA3ABB9)
private val Fill = Color(0xFFF3F5F9)
private val Slate = Color(0xFF475569)
private val Tabular = TextStyle(fontFeatureSettings = "tnum")

/** What the sheet asks the screen to do in Block mode. Times are "HH:mm"; end may be "24:00". */
data class BlockRequest(
    val courtId: Long,
    val date: String,
    val start: String,
    val end: String,
    val kind: String,
    val note: String?,
    val summary: String,
)

/** The reasons the server accepts (VenueBlock::KINDS), in the order the desk reaches for them. */
internal val BlockKinds = listOf(
    "maintenance" to "Maintenance",
    "private" to "Private hire",
    "academy" to "Coaching",
    "tournament" to "Tournament",
    "holiday" to "Closed",
)

/** Walk-in or Block: one track, one pill that slides under the choice. */
@Composable
internal fun ModeSwitch(blocking: Boolean, onChange: (Boolean) -> Unit, modifier: Modifier = Modifier) {
    BoxWithConstraints(
        modifier
            .fillMaxWidth()
            .height(46.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Fill)
            .padding(4.dp),
    ) {
        val half: Dp = maxWidth / 2
        val x by animateDpAsState(
            if (blocking) half else 0.dp,
            spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessMediumLow),
            label = "mode-pill",
        )
        Box(
            Modifier
                .offset(x = x)
                .width(half)
                .fillMaxHeight()
                .shadow(3.dp, RoundedCornerShape(11.dp), ambientColor = Ink.copy(alpha = 0.10f), spotColor = Ink.copy(alpha = 0.14f))
                .clip(RoundedCornerShape(11.dp))
                .background(Color.White),
        )
        Row(Modifier.fillMaxSize()) {
            ModeOption("Walk-in", Icons.Outlined.PersonAdd, !blocking, Modifier.weight(1f)) { onChange(false) }
            ModeOption("Block court", Icons.Outlined.Block, blocking, Modifier.weight(1f)) { onChange(true) }
        }
    }
}

@Composable
private fun ModeOption(label: String, icon: androidx.compose.ui.graphics.vector.ImageVector, on: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val tint by animateColorAsState(if (on) Ink else Muted, tween(180), label = "mode-tint")
    Row(
        modifier
            .fillMaxHeight()
            .pressScale(interaction, 0.95f)
            .clip(RoundedCornerShape(11.dp))
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = if (on) Blue else Faint, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(7.dp))
        Text(label, fontSize = 13.5.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.SemiBold, color = tint)
    }
}

/**
 * The slots this court is free for, back to back, starting at [slotId]. A booking, a
 * hold, an existing block or a gap in the day ends the run: a block can't jump over a
 * customer. Returns the slots and the slot length in minutes.
 */
internal fun freeRun(grid: DayGrid?, slotId: Long, courtId: Long?): Pair<List<DaySlot>, Int> {
    val slots = grid?.slots.orEmpty().filter { it.isOpen }.sortedBy { slotStartMinutes(it.time ?: it.label) }
    val starts = slots.map { slotStartMinutes(it.time ?: it.label) }.distinct().filter { it != Int.MAX_VALUE }
    val len = starts.zipWithNext { a, b -> b - a }.filter { it > 0 }.minOrNull()?.coerceIn(15, 120) ?: 60
    val i = slots.indexOfFirst { it.slotId == slotId }
    if (i < 0 || courtId == null) return emptyList<DaySlot>() to len
    val run = mutableListOf<DaySlot>()
    var expect = slotStartMinutes(slots[i].time ?: slots[i].label)
    for (s in slots.drop(i)) {
        val start = slotStartMinutes(s.time ?: s.label)
        if (start != expect) break
        val cell = s.courts.firstOrNull { it.courtId == courtId } ?: break
        if (cell.isBooked || cell.isHeld || !cell.allowed) break
        run += s
        expect = start + len
    }
    return run to len
}

internal fun hm(minutes: Int): String = "%02d:%02d".format(minutes / 60, minutes % 60)

internal fun clock12(minutes: Int): String {
    val m = minutes % (24 * 60)
    val h = m / 60
    return "%d:%02d %s".format(if (h % 12 == 0) 12 else h % 12, m % 60, if (h >= 12) "PM" else "AM")
}

/**
 * Block mode's body: how long, why, and an optional note. Every length offered is one
 * the court is actually free for; the line underneath says in plain words what will
 * happen, so the hold-to-block button has nothing left to surprise anyone with.
 */
@Composable
internal fun BlockPanel(
    run: List<DaySlot>,
    slotMinutes: Int,
    hours: Int,
    onHours: (Int) -> Unit,
    kind: String,
    onKind: (String) -> Unit,
    note: String,
    onNote: (String) -> Unit,
    courtName: String?,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val start = run.firstOrNull()?.let { slotStartMinutes(it.time ?: it.label) }
    Column(modifier) {
        Text("How long", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
        Spacer(Modifier.height(10.dp))
        if (start == null || run.isEmpty()) {
            Text("This court isn't free at this time.", fontSize = 13.sp, color = Muted)
        } else {
            // 1, 2, 3 slots, then everything that's left — whichever the run allows.
            val options = (listOf(1, 2, 3).filter { it < run.size } + run.size).distinct()
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                options.forEach { n ->
                    val end = start + n * slotMinutes
                    val rest = n == run.size && n > 3
                    LengthChip(
                        title = if (rest) "Rest of day" else durationLabel(n * slotMinutes),
                        sub = "till " + clock12(end),
                        selected = n == hours,
                    ) {
                        if (n != hours) {
                            Haptics.tick(view)
                            onHours(n)
                        }
                    }
                }
            }
        }

        Spacer(Modifier.height(22.dp))
        Text("Why", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
        Spacer(Modifier.height(10.dp))
        Row(
            Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            BlockKinds.forEach { (k, label) ->
                ReasonChip(label, blockIcon(k), k == kind) {
                    if (k != kind) {
                        Haptics.tick(view)
                        onKind(k)
                    }
                }
            }
        }

        Spacer(Modifier.height(14.dp))
        Box(
            Modifier
                .fillMaxWidth()
                .height(50.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(Fill)
                .padding(horizontal = 16.dp),
            contentAlignment = Alignment.CenterStart,
        ) {
            if (note.isEmpty()) Text("Add a note for your team (optional)", fontSize = 14.sp, color = Faint)
            BasicTextField(
                value = note,
                onValueChange = { onNote(it.take(80)) },
                singleLine = true,
                textStyle = TextStyle(fontSize = 14.sp, color = Ink, fontWeight = FontWeight.Medium),
                cursorBrush = SolidColor(Blue),
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences, imeAction = ImeAction.Done),
                modifier = Modifier.fillMaxWidth(),
            )
        }

        if (start != null && run.isNotEmpty() && courtName != null) {
            Spacer(Modifier.height(10.dp))
            Text(
                "Players and the desk can't book $courtName from ${clock12(start)} to ${clock12(start + hours * slotMinutes)}. Tap it on the grid to open it again.",
                fontSize = 12.5.sp, color = Muted, lineHeight = 17.sp, modifier = Modifier.padding(horizontal = 2.dp),
            )
        }
    }
}

private fun durationLabel(minutes: Int): String = when {
    minutes % 60 == 0 -> "${minutes / 60} hr" + if (minutes > 60) "s" else ""
    minutes > 60 -> "${minutes / 60}h ${minutes % 60}m"
    else -> "$minutes min"
}

@Composable
private fun LengthChip(title: String, sub: String, selected: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val border by animateColorAsState(if (selected) Blue else Color(0xFFE4E8EF), tween(160), label = "len-b")
    val bg by animateColorAsState(if (selected) Color(0xFFEFF4FF) else Color.White, tween(160), label = "len-bg")
    Column(
        Modifier
            .pressScale(interaction, 0.94f)
            .clip(RoundedCornerShape(14.dp))
            .background(bg)
            .border(if (selected) 1.5.dp else 1.dp, border, RoundedCornerShape(14.dp))
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Text(title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = if (selected) BlueDeep else Ink, style = Tabular)
        Text(sub, fontSize = 11.5.sp, color = if (selected) BlueDeep.copy(alpha = 0.7f) else Muted, style = Tabular)
    }
}

@Composable
private fun ReasonChip(label: String, icon: androidx.compose.ui.graphics.vector.ImageVector, selected: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (selected) Ink else Color.White, tween(160), label = "why-bg")
    val fg by animateColorAsState(if (selected) Color.White else Ink, tween(160), label = "why-fg")
    Row(
        Modifier
            .pressScale(interaction, 0.94f)
            .height(40.dp)
            .clip(CircleShape)
            .background(bg)
            .border(1.dp, if (selected) Ink else Color(0xFFE4E8EF), CircleShape)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = fg, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(7.dp))
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = fg)
    }
}

/**
 * Press and hold to block. A deeper blue sweeps across the button under the finger
 * with a tick at each quarter, and it commits only when the sweep lands, with a firm
 * confirm. Letting go early springs it back. Taking a court off sale is the one desk
 * action a stray tap must never do, so it costs half a second of intent.
 */
@Composable
internal fun HoldToBlockButton(
    label: String,
    detail: String,
    enabled: Boolean,
    loading: Boolean,
    onCommit: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val progress = remember { Animatable(0f) }
    var holding by remember { mutableStateOf(false) }
    var job by remember { mutableStateOf<Job?>(null) }
    // A quick tap used to do nothing at all, which read as "broken". Now it shakes,
    // buzzes no, and the label says to press and hold.
    var hint by remember { mutableStateOf(false) }
    val wobble = remember { Animatable(0f) }
    val pressScale by androidx.compose.animation.core.animateFloatAsState(
        if (holding) 0.975f else 1f, spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessMedium), label = "hold-scale",
    )

    Box(
        modifier
            .graphicsLayer { scaleX = pressScale; scaleY = pressScale; translationX = wobble.value }
            .fillMaxWidth()
            .height(58.dp)
            .shadow(10.dp, RoundedCornerShape(18.dp), ambientColor = Blue.copy(alpha = 0.25f), spotColor = Blue.copy(alpha = 0.35f))
            .clip(RoundedCornerShape(18.dp))
            .background(if (enabled) Blue else Blue.copy(alpha = 0.45f))
            .pointerInput(enabled, loading) {
                if (!enabled || loading) return@pointerInput
                awaitEachGesture {
                    awaitFirstDown(requireUnconsumed = false)
                    holding = true
                    Haptics.tick(view)
                    job?.cancel()
                    job = scope.launch {
                        var lastQuarter = 0
                        progress.animateTo(1f, tween(((1f - progress.value) * 650).toInt().coerceAtLeast(1), easing = LinearEasing)) {
                            val q = (value * 4).toInt()
                            if (q in 1..3 && q > lastQuarter) {
                                lastQuarter = q
                                Haptics.tick(view)
                            }
                        }
                        Haptics.confirm(view)
                        holding = false
                        onCommit()
                        progress.snapTo(0f)
                    }
                    waitForUpOrCancellation()
                    if (progress.value < 1f) {
                        job?.cancel()
                        holding = false
                        val early = progress.value < 0.6f
                        scope.launch { progress.animateTo(0f, spring(dampingRatio = 0.8f, stiffness = Spring.StiffnessMediumLow)) }
                        if (early) {
                            hint = true
                            Haptics.reject(view)
                            scope.launch {
                                wobble.animateTo(0f, androidx.compose.animation.core.keyframes {
                                    durationMillis = 360
                                    -14f at 50; 12f at 110; -8f at 170; 5f at 230; -2f at 290
                                })
                            }
                            scope.launch {
                                kotlinx.coroutines.delay(2200)
                                hint = false
                            }
                        }
                    }
                }
            },
    ) {
        // The sweep: a deeper blue filling left to right under the finger.
        Canvas(Modifier.fillMaxSize()) {
            if (progress.value > 0f) {
                drawRect(BlueDeep, size = Size(size.width * progress.value, size.height))
            }
        }
        Row(
            Modifier.fillMaxSize().padding(horizontal = 20.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (loading) {
                Spacer(Modifier.weight(1f))
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(12.dp))
                Text("Blocking…", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
                Spacer(Modifier.weight(1f))
            } else {
                Icon(Icons.Outlined.Block, null, tint = Color.White, modifier = Modifier.size(19.dp))
                Spacer(Modifier.width(10.dp))
                AnimatedContent(
                    targetState = when {
                        holding -> "Keep holding…"
                        hint -> "Press and hold to block"
                        else -> label
                    },
                    transitionSpec = { fadeIn(tween(140)) togetherWith fadeOut(tween(90)) },
                    modifier = Modifier.weight(1f),
                    label = "hold-label",
                ) { l -> Text(l, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1, overflow = TextOverflow.Ellipsis) }
                Box(Modifier.width(1.dp).height(22.dp).background(Color.White.copy(alpha = 0.28f)))
                Spacer(Modifier.width(14.dp))
                Text(detail, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = Color.White, style = Tabular)
            }
        }
    }
}

/**
 * The shutter coming down over a court in Block mode: slate slats roll down from the
 * top with a darker bottom rail, the way a closed counter looks. [p] is 0..1.
 */
internal fun DrawScope.shutter(r: Rect, radius: CornerRadius, p: Float) {
    if (p <= 0f) return
    val bottom = r.top + r.height * p
    clipRect(r.left, r.top, r.right, bottom) {
        drawRoundRect(Color(0xFFE2E7EE), r.topLeft, r.size, radius)
        val step = 7.dp.toPx()
        var y = r.top + step
        while (y < bottom - 2.dp.toPx()) {
            drawLine(Color(0xFFCDD5E0), Offset(r.left, y), Offset(r.right, y), strokeWidth = 1.2.dp.toPx())
            y += step
        }
    }
    drawRect(Slate, topLeft = Offset(r.left, bottom - 3.dp.toPx()), size = Size(r.width, 3.dp.toPx()))
}

/**
 * A blocked court-hour opened from the grid: the reason, when, any note, and Unblock
 * when the desk made it. Haraan's own blocks (recurring, whole venue) say who to ask.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun BlockDetailsSheet(
    target: BlockTarget,
    canUnblock: Boolean,
    busy: Boolean,
    onDismiss: () -> Unit,
    onUnblock: () -> Unit,
) {
    val view = LocalView.current
    val b = target.block
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(start = 22.dp, end = 22.dp, bottom = 16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                // A little closed court: shutter fully down.
                Canvas(Modifier.size(width = 52.dp, height = 64.dp)) {
                    val r = Rect(Offset.Zero, size)
                    val radius = CornerRadius(8.dp.toPx())
                    drawRoundRect(Color(0xFFE3F4E8), r.topLeft, r.size, radius)
                    shutter(r, radius, 1f)
                }
                Spacer(Modifier.width(16.dp))
                Column(Modifier.weight(1f)) {
                    Text("Blocked  ·  ${target.courtName}", fontSize = 13.sp, color = Muted, fontWeight = FontWeight.Medium)
                    Spacer(Modifier.height(4.dp))
                    Text(b.label, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = Ink, maxLines = 2)
                    blockWindow(b)?.let { Text(it, fontSize = 14.sp, color = Muted, style = Tabular) }
                }
            }
            if (!b.note.isNullOrBlank() && b.note != b.label) {
                Spacer(Modifier.height(14.dp))
                Text(b.note, fontSize = 14.sp, color = Ink)
            }
            Spacer(Modifier.height(10.dp))
            Text(
                if (b.label != b.reason) b.reason else "",
                fontSize = 12.5.sp, color = Muted,
            )
            Spacer(Modifier.height(18.dp))
            if (canUnblock) {
                val interaction = remember { MutableInteractionSource() }
                Row(
                    Modifier
                        .pressScale(interaction, 0.97f)
                        .fillMaxWidth()
                        .height(56.dp)
                        .clip(RoundedCornerShape(18.dp))
                        .background(Blue)
                        .pressShade(interaction, 0.12f)
                        .clickable(interactionSource = interaction, indication = null, enabled = !busy) {
                            Haptics.confirm(view)
                            onUnblock()
                        },
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (busy) CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
                    else Text("Open ${target.courtName} again", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
                }
            } else {
                Text(
                    "Haraan set this one up (it repeats or covers the whole venue). Message Haraan support to change it.",
                    fontSize = 13.sp, color = Muted, lineHeight = 18.sp,
                )
            }
        }
    }
}
