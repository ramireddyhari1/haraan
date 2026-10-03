package com.haraan.partner.daybookings.ui

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.ContentTransform
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.AnimationVector1D
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Link
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.QrCode2
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.CourtCol
import com.haraan.partner.DayGrid
import com.haraan.partner.DaySlot
import com.haraan.partner.PayMethod
import com.haraan.partner.daybookings.model.WalkInTarget
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.home.CourtKind
import com.haraan.partner.ui.home.courtKindFor
import com.haraan.partner.ui.home.hatch
import com.haraan.partner.ui.home.markings
import com.haraan.partner.ui.home.mownBands
import com.haraan.partner.ui.home.players
import com.haraan.partner.ui.home.upright
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import kotlin.math.roundToInt

private val Blue = Color(0xFF2563EB)
private val BlueInk = Color(0xFF1D4ED8)
private val BlueSoft = Color(0xFFEFF4FF)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFFA3ABB9)
private val Fill = Color(0xFFF3F5F9)
private val Hairline = Color(0xFFE4E8EF)
private val Taken = Color(0xFFD5DAE3)
private val Red = Color(0xFFDC2626)
private val Valid = Color(0xFF16A34A)

/** Figures in a column line up: 9:00 sits over 10:00, ₹600 over ₹1,200. */
private val Tabular = TextStyle(fontFeatureSettings = "tnum")

/** The three ways a walk-in pays at the desk, in the order the segmented control shows them. */
private val DeskMethods = listOf(
    Triple(PayMethod.CASH, "Cash", Icons.Outlined.Payments),
    Triple(PayMethod.UPI_QR, "UPI QR", Icons.Outlined.QrCode2),
    Triple(PayMethod.LINK, "Link", Icons.Outlined.Link),
)

/**
 * Taking a walk-in at the counter: when, who, which court, how they pay. Then one button
 * that says exactly what it will do and for how much.
 *
 * Built for a person with a customer in front of them. The name field is focused on
 * open, the keyboard steps from name to phone to done, and the phone number groups itself
 * as it's typed. A missing field shakes with a reject buzz instead of a line of red text
 * appearing somewhere below the fold. The button sits under the form, above the keyboard,
 * so it never scrolls away.
 *
 * Everything the sheet shows is read off the day grid: the dots under each time are the
 * venue's courts at that hour, and a court shows its own rate only when the courts
 * actually charge differently.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WalkInBookingSheet(
    target: WalkInTarget,
    grid: DayGrid?,
    date: String,
    isSubmitting: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (slotId: Long, courtId: Long?, date: String, name: String, phone: String, method: PayMethod) -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val view = LocalView.current
    val focus = LocalFocusManager.current
    val scope = rememberCoroutineScope()

    var guestName by remember { mutableStateOf("") }
    var guestPhone by remember { mutableStateOf("") }
    var selectedSlotId by remember { mutableStateOf(target.slotId) }
    var selectedCourtId by remember { mutableStateOf(target.courtId) }
    var selectedMethod by remember { mutableStateOf(PayMethod.CASH) }
    // Errors show only once the desk has tried to book, not while they're still typing.
    var triedSubmit by remember { mutableStateOf(false) }

    val nameShake = remember { Animatable(0f) }
    val phoneShake = remember { Animatable(0f) }
    val nameFocus = remember { FocusRequester() }

    val currentSlot = remember(grid, selectedSlotId) {
        grid?.slots?.firstOrNull { it.slotId == selectedSlotId }
    }
    // Every time the desk could still sell, earliest first, so a walk-in opened from the
    // big button (which lands on the first free slot) can be moved to the hour they want.
    val slotOptions = remember(grid, target.slotId) {
        grid?.slots.orEmpty()
            .filter { slot ->
                slot.slotId == target.slotId || (
                    slot.isOpen && if (slot.courts.isEmpty()) slot.available > 0
                    else slot.courts.any { !it.isBooked && !it.isHeld && it.allowed }
                )
            }
            .sortedBy { com.haraan.partner.slotStartMinutes(it.time ?: it.label) }
    }
    val availableCourts = remember(currentSlot, grid) {
        grid?.courts?.filter { court -> courtFree(currentSlot, court) } ?: emptyList()
    }
    // A court that is taken at the newly picked time falls back to one that's free.
    LaunchedEffect(availableCourts) {
        if (availableCourts.isNotEmpty() && availableCourts.none { it.id == selectedCourtId }) {
            selectedCourtId = availableCourts.first().id
        }
    }
    val effectivePrice = remember(selectedCourtId, currentSlot, target.basePrice) {
        courtPrice(currentSlot, selectedCourtId) ?: target.basePrice
    }
    // Rates per court only earn a line when they differ; otherwise the button says it.
    val courtPrices = remember(currentSlot, availableCourts) {
        availableCourts.associate { it.id to courtPrice(currentSlot, it.id) }
    }
    val pricesDiffer = courtPrices.values.filterNotNull().distinct().size > 1
    val courtName = availableCourts.firstOrNull { it.id == selectedCourtId }?.name ?: target.courtName
    val slotRaw = currentSlot?.let { it.time ?: it.label } ?: target.slotTime
    val slotEnd = currentSlot?.let { slotEndLabel(it.label) ?: slotEndLabel(it.time) }
    val slotIndex = slotOptions.indexOfFirst { it.slotId == selectedSlotId }

    val nameValid = guestName.isNotBlank()
    val phoneValid = guestPhone.length == 10

    LaunchedEffect(Unit) {
        // Let the sheet finish rising first, or the keyboard fights the animation.
        delay(280)
        runCatching { nameFocus.requestFocus() }
    }

    fun submit() {
        triedSubmit = true
        if (!nameValid || !phoneValid) {
            Haptics.reject(view)
            scope.launch { if (!nameValid) shake(nameShake) }
            scope.launch { if (!phoneValid) shake(phoneShake) }
            if (!nameValid) runCatching { nameFocus.requestFocus() }
            return
        }
        focus.clearFocus()
        onSubmit(selectedSlotId, selectedCourtId, date, guestName.trim(), guestPhone, selectedMethod)
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(
            Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding(),
        ) {
            val scroll = rememberScrollState()
            Column(
                modifier = Modifier
                    .weight(1f, fill = false)
                    .fillMaxWidth()
                    .verticalScroll(scroll)
                    .padding(bottom = 6.dp),
            ) {
                SlotHeader(
                    start = slotRaw,
                    end = slotEnd,
                    order = slotIndex,
                    date = friendlyDate(date),
                    court = courtName,
                    onClose = onDismiss,
                    modifier = Modifier.padding(horizontal = 22.dp),
                )

                // Time: only a choice when there is one to make.
                if (slotOptions.size > 1) {
                    Spacer(Modifier.height(22.dp))
                    SectionLabel("Time", Modifier.padding(horizontal = 22.dp))
                    Spacer(Modifier.height(10.dp))
                    TimeRail(
                        slots = slotOptions,
                        courts = grid?.courts.orEmpty(),
                        selectedSlotId = selectedSlotId,
                        selectedCourtId = selectedCourtId,
                    ) { slot ->
                        if (selectedSlotId != slot.slotId) {
                            Haptics.tick(view)
                            selectedSlotId = slot.slotId
                        }
                    }
                }

                // The venue at this hour, drawn: tap the court itself. Shown even for a
                // one-court venue, where it's the picture of what's being sold.
                val gridCourts = grid?.courts.orEmpty()
                if (gridCourts.isNotEmpty()) {
                    Spacer(Modifier.height(24.dp))
                    Row(Modifier.padding(horizontal = 22.dp), verticalAlignment = Alignment.CenterVertically) {
                        SectionLabel(if (gridCourts.size > 1) "Court" else "Your court", Modifier.weight(1f))
                        if (gridCourts.size > 1) {
                            Text(
                                if (availableCourts.size == gridCourts.size) "All ${gridCourts.size} free"
                                else "${availableCourts.size} of ${gridCourts.size} free",
                                fontSize = 12.5.sp, color = Muted, fontWeight = FontWeight.Medium,
                            )
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                    CourtMap(
                        slot = currentSlot,
                        courts = gridCourts,
                        selectedCourtId = selectedCourtId,
                        prices = if (pricesDiffer) courtPrices else emptyMap(),
                        onPick = { court ->
                            if (selectedCourtId != court.id) {
                                Haptics.tick(view)
                                selectedCourtId = court.id
                            }
                        },
                        onTaken = { Haptics.reject(view) },
                        modifier = Modifier.padding(horizontal = 22.dp),
                    )
                }

                Column(Modifier.padding(horizontal = 22.dp)) {
                    Spacer(Modifier.height(24.dp))
                    SectionLabel("Customer")
                    Spacer(Modifier.height(10.dp))
                    CustomerCard(
                        name = guestName,
                        onName = { guestName = it.take(60) },
                        phone = guestPhone,
                        onPhone = { raw ->
                            val digits = raw.filter(Char::isDigit)
                            // A pasted "+91 98765 43210" keeps its last ten; typing stops at ten.
                            val next = if (digits.length > 10 && digits.length - guestPhone.length > 1) digits.takeLast(10) else digits.take(10)
                            // The tenth digit landing is the one moment the field is "done".
                            if (next.length == 10 && guestPhone.length != 10) Haptics.confirm(view)
                            guestPhone = next
                        },
                        nameError = triedSubmit && !nameValid,
                        phoneError = triedSubmit && !phoneValid,
                        phoneValid = phoneValid,
                        nameShake = nameShake,
                        phoneShake = phoneShake,
                        nameFocus = nameFocus,
                        onNameNext = { focus.moveFocus(FocusDirection.Down) },
                        onPhoneDone = { focus.clearFocus() },
                    )
                    FieldError(
                        visible = triedSubmit && (!nameValid || !phoneValid),
                        text = when {
                            !nameValid && !phoneValid -> "Add their name and mobile number"
                            !nameValid -> "Add the customer's name"
                            guestPhone.isEmpty() -> "Add a mobile number"
                            else -> "${10 - guestPhone.length} more digit${if (10 - guestPhone.length == 1) "" else "s"}"
                        },
                    )

                    Spacer(Modifier.height(24.dp))
                    SectionLabel("Payment")
                    Spacer(Modifier.height(10.dp))
                    MethodSegments(selected = selectedMethod) { m ->
                        if (m != selectedMethod) {
                            Haptics.tick(view)
                            selectedMethod = m
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                    AnimatedContent(
                        targetState = selectedMethod,
                        transitionSpec = { fadeIn(tween(180)) togetherWith fadeOut(tween(120)) },
                        label = "method-note",
                    ) { m ->
                        Text(
                            when (m) {
                                PayMethod.UPI_QR -> "A UPI QR for this amount opens next. It confirms itself when they pay."
                                PayMethod.LINK -> "Razorpay texts them a payment link. It confirms itself when they pay."
                                else -> "You're taking the cash now. The booking is confirmed straight away."
                            },
                            fontSize = 12.5.sp, color = Muted, lineHeight = 17.sp,
                            modifier = Modifier.padding(horizontal = 2.dp),
                        )
                    }
                }
            }

            // The button lives outside the scroll. A hairline shows only while the form
            // runs on underneath it, the way a toolbar edge appears when content passes.
            val edge by animateFloatAsState(if (scroll.canScrollForward) 1f else 0f, tween(160), label = "footer-edge")
            Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline.copy(alpha = edge)))
            PrimaryCta(
                label = when (selectedMethod) {
                    PayMethod.UPI_QR -> "Book & show QR"
                    PayMethod.LINK -> "Book & send link"
                    else -> "Collect cash"
                },
                amount = effectivePrice,
                loading = isSubmitting,
                onClick = ::submit,
                modifier = Modifier.padding(start = 22.dp, end = 22.dp, top = 12.dp, bottom = 14.dp),
            )
        }
    }
}

// ---- Pieces ----------------------------------------------------------------------

/**
 * The hour being sold, set large, with the end of the hour beside it in grey when the
 * server spells one. Picking a later time rolls the figure up; an earlier one rolls it
 * down, so the eye reads the direction before the digits.
 */
@Composable
private fun SlotHeader(
    start: String,
    end: String?,
    order: Int,
    date: String,
    court: String?,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(modifier.fillMaxWidth().padding(top = 2.dp), verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            Text("Walk-in  ·  $date", fontSize = 13.sp, color = Muted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(6.dp))
            AnimatedContent(
                targetState = Triple(order, slotStartLabel(start), end),
                transitionSpec = { rollTransition(targetState.first >= initialState.first) },
                label = "slot-time",
            ) { (_, s, e) ->
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        s, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
                        lineHeight = 36.sp, letterSpacing = (-0.6).sp, style = Tabular,
                        modifier = Modifier.alignByBaseline(),
                    )
                    if (e != null) {
                        Text(
                            "  –  $e", fontSize = 17.sp, fontWeight = FontWeight.SemiBold, color = Faint,
                            style = Tabular, modifier = Modifier.alignByBaseline(),
                        )
                    }
                }
            }
            if (!court.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                AnimatedContent(
                    targetState = court,
                    transitionSpec = { fadeIn(tween(180)) togetherWith fadeOut(tween(120)) },
                    label = "slot-court",
                ) { c ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        CourtGlyph(Modifier.size(width = 14.dp, height = 10.dp), Muted)
                        Spacer(Modifier.width(7.dp))
                        Text(c, fontSize = 14.sp, color = Ink, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
        CloseButton(onClose)
    }
}

@Composable
private fun CloseButton(onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Box(
        Modifier
            .offset(x = 6.dp, y = (-4).dp)
            .pressScale(interaction, 0.88f)
            .size(40.dp)
            .clip(CircleShape)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Box(Modifier.size(32.dp).clip(CircleShape).background(Fill), contentAlignment = Alignment.Center) {
            Icon(Icons.Outlined.Close, contentDescription = "Close", tint = Muted, modifier = Modifier.size(17.dp))
        }
    }
}

@Composable
private fun SectionLabel(text: String, modifier: Modifier = Modifier) {
    Text(text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted, modifier = modifier)
}

/**
 * The day's open hours as a row of small cards: the hour, then one dot per court, solid
 * where that court is still free at that hour. The desk can see at a glance where the
 * gaps are without leaving the sheet. The row fades at whichever edge has more behind it.
 */
@Composable
private fun TimeRail(
    slots: List<DaySlot>,
    courts: List<CourtCol>,
    selectedSlotId: Long,
    selectedCourtId: Long?,
    onPick: (DaySlot) -> Unit,
) {
    val listState = rememberLazyListState()
    LaunchedEffect(slots) {
        val i = slots.indexOfFirst { it.slotId == selectedSlotId }
        if (i > 0) listState.scrollToItem((i - 1).coerceAtLeast(0))
    }
    val fadeStart by animateFloatAsState(if (listState.canScrollBackward) 1f else 0f, tween(160), label = "rail-l")
    val fadeEnd by animateFloatAsState(if (listState.canScrollForward) 1f else 0f, tween(160), label = "rail-r")
    LazyRow(
        state = listState,
        contentPadding = PaddingValues(horizontal = 22.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .graphicsLayer(compositingStrategy = CompositingStrategy.Offscreen)
            .drawWithContent {
                drawContent()
                val w = 28.dp.toPx()
                if (fadeStart > 0f) drawRect(
                    Brush.horizontalGradient(listOf(Color.Black.copy(alpha = 1f - fadeStart), Color.Black), startX = 0f, endX = w),
                    size = size.copy(width = w), blendMode = BlendMode.DstIn,
                )
                if (fadeEnd > 0f) drawRect(
                    Brush.horizontalGradient(listOf(Color.Black, Color.Black.copy(alpha = 1f - fadeEnd)), startX = size.width - w, endX = size.width),
                    topLeft = Offset(size.width - w, 0f), size = size.copy(width = w), blendMode = BlendMode.DstIn,
                )
            },
    ) {
        items(slots.size, key = { slots[it].slotId }) { i ->
            val slot = slots[i]
            TimeCard(
                slot = slot,
                courts = courts,
                selected = slot.slotId == selectedSlotId,
                selectedCourtId = selectedCourtId,
            ) { onPick(slot) }
        }
    }
}

@Composable
private fun TimeCard(
    slot: DaySlot,
    courts: List<CourtCol>,
    selected: Boolean,
    selectedCourtId: Long?,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (selected) BlueSoft else Color.White, tween(180), label = "time-bg")
    val stroke by animateColorAsState(if (selected) Blue else Hairline, tween(180), label = "time-stroke")
    val strokeW by animateDpAsState(if (selected) 1.5.dp else 1.dp, tween(180), label = "time-w")
    val ink by animateColorAsState(if (selected) BlueInk else Ink, tween(180), label = "time-ink")
    val raw = slot.time ?: slot.label
    val (clock, meridiem) = splitClock(raw)
    // Free/taken per court, in the venue's court order. A slot without court cells is a
    // pooled slot: show its capacity as dots, with the sold ones greyed.
    val dots: List<Pair<Boolean, Long?>> = if (courts.isNotEmpty() && slot.courts.isNotEmpty()) {
        courts.map { courtFree(slot, it) to it.id }
    } else {
        List(slot.capacity.coerceIn(0, 8)) { i -> (i < slot.available) to null }
    }
    val free = dots.count { it.first }
    val peak = slot.courts.any { it.isPeak }
    Column(
        Modifier
            .pressScale(interaction, 0.93f)
            .widthIn(min = 68.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(bg)
            .border(strokeW, stroke, RoundedCornerShape(14.dp))
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .semantics { contentDescription = "$raw, $free free" + if (peak) ", peak rate" else "" }
            .padding(horizontal = 12.dp, vertical = 10.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Row(verticalAlignment = Alignment.Bottom) {
            Text(clock, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = ink, style = Tabular, modifier = Modifier.alignByBaseline())
            if (meridiem != null) {
                Spacer(Modifier.width(2.dp))
                Text(meridiem, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = if (selected) BlueInk else Muted, modifier = Modifier.alignByBaseline())
            }
        }
        Spacer(Modifier.height(7.dp))
        if (dots.isEmpty() || dots.size > 8) {
            Text("$free free", fontSize = 10.5.sp, color = Muted, fontWeight = FontWeight.Medium)
        } else {
            Row(horizontalArrangement = Arrangement.spacedBy(3.dp), verticalAlignment = Alignment.CenterVertically) {
                dots.forEach { (isFree, courtId) ->
                    val mine = selected && courtId != null && courtId == selectedCourtId
                    val d by animateDpAsState(if (mine) 7.dp else 5.dp, spring(dampingRatio = 0.5f), label = "dot")
                    Box(
                        Modifier.size(d).clip(CircleShape)
                            .background(if (!isFree) Taken else if (selected) Blue else Ink.copy(alpha = 0.55f)),
                    )
                }
            }
        }
    }
}

/**
 * Name and mobile as one card with a hairline between, the way a phone's own contact
 * form groups them. The row being typed in gets a blue label; the card's edge turns
 * blue while either is focused, and red when the desk tried to book without them.
 */
@Composable
private fun CustomerCard(
    name: String,
    onName: (String) -> Unit,
    phone: String,
    onPhone: (String) -> Unit,
    nameError: Boolean,
    phoneError: Boolean,
    phoneValid: Boolean,
    nameShake: Animatable<Float, AnimationVector1D>,
    phoneShake: Animatable<Float, AnimationVector1D>,
    nameFocus: FocusRequester,
    onNameNext: () -> Unit,
    onPhoneDone: () -> Unit,
) {
    val nameInteraction = remember { MutableInteractionSource() }
    val phoneInteraction = remember { MutableInteractionSource() }
    val nameFocused by nameInteraction.collectIsFocusedAsState()
    val phoneFocused by phoneInteraction.collectIsFocusedAsState()
    val border by animateColorAsState(
        when {
            nameError || phoneError -> Red
            nameFocused || phoneFocused -> Blue
            else -> Hairline
        },
        tween(160), label = "card-border",
    )
    val width by animateDpAsState(if (nameFocused || phoneFocused || nameError || phoneError) 1.5.dp else 1.dp, tween(160), label = "card-w")

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(width, border, RoundedCornerShape(18.dp)),
    ) {
        CardRow(
            label = "Name",
            focused = nameFocused,
            error = nameError,
            modifier = Modifier.offset { IntOffset(nameShake.value.roundToInt(), 0) },
        ) {
            CardField(
                value = name,
                onValueChange = onName,
                placeholder = "Customer's full name",
                interaction = nameInteraction,
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
                keyboardActions = KeyboardActions(onNext = { onNameNext() }),
                modifier = Modifier.focusRequester(nameFocus),
            )
        }
        Box(Modifier.padding(start = 16.dp).fillMaxWidth().height(1.dp).background(Hairline))
        CardRow(
            label = "Mobile",
            focused = phoneFocused,
            error = phoneError,
            modifier = Modifier.offset { IntOffset(phoneShake.value.roundToInt(), 0) },
        ) {
            Text("+91", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = if (phone.isEmpty() && !phoneFocused) Faint else Ink, style = Tabular)
            Spacer(Modifier.width(8.dp))
            CardField(
                value = phone,
                onValueChange = onPhone,
                placeholder = "98765 43210",
                interaction = phoneInteraction,
                visualTransformation = PhoneGrouping,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { onPhoneDone() }),
            )
            DrawnCheck(visible = phoneValid, modifier = Modifier.padding(start = 8.dp).size(18.dp))
        }
    }
}

@Composable
private fun CardRow(
    label: String,
    focused: Boolean,
    error: Boolean,
    modifier: Modifier = Modifier,
    content: @Composable androidx.compose.foundation.layout.RowScope.() -> Unit,
) {
    val labelColor by animateColorAsState(
        when {
            error -> Red
            focused -> BlueInk
            else -> Muted
        },
        tween(160), label = "row-label",
    )
    Row(
        modifier.fillMaxWidth().height(56.dp).padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = labelColor, modifier = Modifier.width(64.dp))
        content()
    }
}

@Composable
private fun androidx.compose.foundation.layout.RowScope.CardField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    interaction: MutableInteractionSource,
    keyboardOptions: KeyboardOptions,
    keyboardActions: KeyboardActions,
    modifier: Modifier = Modifier,
    visualTransformation: VisualTransformation = VisualTransformation.None,
) {
    Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
        if (value.isEmpty()) Text(placeholder, fontSize = 15.sp, color = Faint, maxLines = 1)
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            interactionSource = interaction,
            visualTransformation = visualTransformation,
            keyboardOptions = keyboardOptions,
            keyboardActions = keyboardActions,
            textStyle = TextStyle(fontSize = 15.sp, color = Ink, fontWeight = FontWeight.SemiBold, letterSpacing = 0.2.sp, fontFeatureSettings = "tnum"),
            cursorBrush = SolidColor(Blue),
            modifier = modifier.fillMaxWidth(),
        )
    }
}

/** A tick that draws itself in one stroke when the tenth digit lands, and wipes back out if one is deleted. */
@Composable
private fun DrawnCheck(visible: Boolean, modifier: Modifier = Modifier) {
    val progress by animateFloatAsState(
        if (visible) 1f else 0f,
        if (visible) tween(320) else tween(140),
        label = "check",
    )
    if (progress <= 0f) {
        Spacer(modifier)
        return
    }
    Canvas(modifier) {
        val s = size.minDimension
        drawCircle(Valid.copy(alpha = 0.12f * progress), radius = s / 2f)
        val path = Path().apply {
            moveTo(s * 0.28f, s * 0.52f)
            lineTo(s * 0.44f, s * 0.67f)
            lineTo(s * 0.73f, s * 0.36f)
        }
        val measure = PathMeasure().apply { setPath(path, false) }
        val partial = Path()
        measure.getSegment(0f, measure.length * progress, partial, true)
        drawPath(partial, Valid, style = Stroke(width = s * 0.12f, cap = StrokeCap.Round, join = StrokeJoin.Round))
    }
}

@Composable
private fun FieldError(visible: Boolean, text: String) {
    AnimatedVisibility(visible, enter = fadeIn() + expandVertically(), exit = fadeOut() + shrinkVertically()) {
        Text(text, fontSize = 12.sp, color = Red, fontWeight = FontWeight.Medium, modifier = Modifier.padding(start = 6.dp, top = 8.dp))
    }
}

/** What one court is doing at the picked hour, as the map draws it. */
private enum class LaneState { Free, Booked, Held, Closed }

private fun laneState(slot: DaySlot?, court: CourtCol): LaneState {
    val cell = slot?.courts?.firstOrNull { it.courtId == court.id } ?: return LaneState.Free
    return when {
        cell.isBooked -> LaneState.Booked
        cell.isHeld -> LaneState.Held
        !cell.allowed -> LaneState.Closed
        else -> LaneState.Free
    }
}

/**
 * The venue's courts at the picked hour, drawn from above with the same court art as
 * Home: someone already playing is a blue court with two players on it, a court
 * mid-payment is amber hatching, a free court is bare turf. Tapping a free court
 * picks it and the walk-in's two players drop onto it. Tapping a taken one buzzes no
 * and wobbles. Changing the hour repaints the booked courts from the baseline up, so
 * the desk sees the hour change rather than reads it.
 */
@Composable
private fun CourtMap(
    slot: DaySlot?,
    courts: List<CourtCol>,
    selectedCourtId: Long?,
    prices: Map<Long, Double?>,
    onPick: (CourtCol) -> Unit,
    onTaken: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val paint = remember { Animatable(0f) }
    LaunchedEffect(slot?.slotId) {
        paint.snapTo(0f)
        paint.animateTo(1f, tween(520, easing = androidx.compose.animation.core.FastOutSlowInEasing))
    }
    val single = courts.size == 1
    val scrolls = courts.size > 4
    Box(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(Fill)
            .padding(horizontal = 12.dp, vertical = 12.dp),
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .then(if (scrolls) Modifier.horizontalScroll(rememberScrollState()) else Modifier),
            horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
        ) {
            courts.forEachIndexed { i, court ->
                val laneMod = when {
                    single -> Modifier.fillMaxWidth()
                    scrolls -> Modifier.width(72.dp)
                    else -> Modifier.weight(1f).widthIn(max = 104.dp)
                }
                CourtLane(
                    name = court.name,
                    kind = courtKindFor(court.sports.ifEmpty { slot?.sports.orEmpty() }),
                    state = laneState(slot, court),
                    selected = court.id == selectedCourtId,
                    price = prices[court.id],
                    peak = slot?.courts?.firstOrNull { it.courtId == court.id }?.isPeak == true,
                    // Each court's fill lands a beat after the one before it.
                    paint = ((paint.value * (courts.size + 1)) - i).coerceIn(0f, 1f),
                    tall = !single,
                    onPick = { onPick(court) },
                    onTaken = onTaken,
                    modifier = laneMod,
                )
            }
        }
    }
}

@Composable
private fun CourtLane(
    name: String,
    kind: CourtKind,
    state: LaneState,
    selected: Boolean,
    price: Double?,
    peak: Boolean,
    paint: Float,
    tall: Boolean,
    onPick: () -> Unit,
    onTaken: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val interaction = remember { MutableInteractionSource() }
    val scope = rememberCoroutineScope()
    val wobble = remember { Animatable(0f) }
    val free = state == LaneState.Free
    // The walk-in's players drop in with a little bounce when their court is picked.
    val arrive by animateFloatAsState(
        if (selected && free) 1f else 0f,
        spring(dampingRatio = 0.48f, stiffness = Spring.StiffnessMediumLow),
        label = "arrive",
    )
    val ring by animateFloatAsState(if (selected && free) 1f else 0f, tween(200), label = "ring")
    val nameColor by animateColorAsState(
        when {
            selected && free -> BlueInk
            free -> Ink
            else -> Faint
        },
        tween(180), label = "lane-name",
    )
    val grass = kind == CourtKind.Football || kind == CourtKind.Cricket

    Column(
        modifier
            .offset { IntOffset(wobble.value.roundToInt(), 0) }
            .pressScale(interaction, if (free) 0.95f else 0.98f)
            .clip(RoundedCornerShape(12.dp))
            .clickable(interactionSource = interaction, indication = null) {
                if (free) onPick() else {
                    onTaken()
                    scope.launch { shake(wobble) }
                }
            }
            .semantics {
                contentDescription = name + when (state) {
                    LaneState.Free -> if (selected) ", picked" else ", free"
                    LaneState.Booked -> ", booked"
                    LaneState.Held -> ", someone is paying for it"
                    LaneState.Closed -> ", not open for this sport"
                }
            },
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(if (tall) 112.dp else 96.dp),
        ) {
            val r = androidx.compose.ui.geometry.Rect(Offset.Zero, size)
            val radius = androidx.compose.ui.geometry.CornerRadius(8.dp.toPx())
            val turf = when {
                state == LaneState.Closed -> Color(0xFFE6EAF0)
                grass -> Color(0xFFE3F4E8)
                else -> Color(0xFFE8F0FB)
            }
            drawRoundRect(turf, r.topLeft, r.size, radius)
            if (grass && state != LaneState.Closed) mownBands(r, Color(0xFFD6EEDD))

            val filled = state == LaneState.Booked || state == LaneState.Held
            if (filled && paint > 0f) {
                val top = r.bottom - r.height * paint
                clipRect(r.left, top, r.right, r.bottom) {
                    if (state == LaneState.Booked) {
                        drawRoundRect(
                            Brush.verticalGradient(listOf(Color(0xFF4A80FF), Color(0xFF1E40AF)), startY = r.top, endY = r.bottom),
                            r.topLeft, r.size, radius,
                        )
                    } else {
                        drawRoundRect(Color(0xFFFFF1DB), r.topLeft, r.size, radius)
                        hatch(r, Color(0x66D97706))
                    }
                }
            }
            val lines = when {
                state == LaneState.Booked && paint >= 1f -> Color(0x59FFFFFF)
                state == LaneState.Held && paint >= 1f -> Color(0x55D97706)
                state == LaneState.Closed -> Color(0xFFCBD2DC)
                grass -> Color(0xFF9FD1AE)
                else -> Color(0xFFA9BFE6)
            }
            upright(r) { u ->
                markings(u.deflate(5.dp.toPx()), kind, lines)
                if (state == LaneState.Booked && paint >= 1f) players(u, kind, 0f)
                if (arrive > 0.01f) {
                    // The walk-in's pair: brand blue with a white rim, so they read as
                    // "yours" against the white players on courts already sold.
                    val head = (u.width * 0.11f).coerceIn(3.dp.toPx(), 4.6.dp.toPx())
                    val drop = (1f - arrive) * -10.dp.toPx()
                    listOf(
                        Offset(u.center.x - u.width * 0.14f, u.top + u.height * 0.27f + drop),
                        Offset(u.center.x + u.width * 0.14f, u.bottom - u.height * 0.27f + drop),
                    ).forEach { p ->
                        val s = arrive.coerceIn(0f, 1.2f)
                        drawCircle(Color(0x29000000), radius = head * 1.25f * s, center = p + Offset(0f, head * 0.6f))
                        drawCircle(Color.White, radius = head * 1.3f * s, center = p)
                        drawCircle(Blue, radius = head * s, center = p)
                    }
                }
            }
            if (ring > 0f) {
                val inset = 1.dp.toPx()
                drawRoundRect(
                    Blue.copy(alpha = ring),
                    topLeft = Offset(inset, inset),
                    size = androidx.compose.ui.geometry.Size(size.width - inset * 2, size.height - inset * 2),
                    cornerRadius = radius,
                    style = Stroke(2.dp.toPx()),
                )
            }
        }
        Spacer(Modifier.height(7.dp))
        Text(name, fontSize = 13.sp, fontWeight = if (selected && free) FontWeight.Bold else FontWeight.SemiBold, color = nameColor, maxLines = 1)
        Text(
            when (state) {
                LaneState.Booked -> "Booked"
                LaneState.Held -> "Paying now"
                LaneState.Closed -> "Not this sport"
                LaneState.Free -> if (price != null) "₹" + formatRupees(price) + if (peak) " · peak" else "" else "Free"
            },
            fontSize = 11.sp, fontWeight = FontWeight.Medium, maxLines = 1, style = Tabular,
            color = when (state) {
                LaneState.Held -> Color(0xFFB45309)
                LaneState.Free -> if (selected) BlueInk.copy(alpha = 0.75f) else Muted
                else -> Faint
            },
        )
    }
}

/** A court seen from above: the outline and the half-way line. Small enough to read as a mark, not a picture. */
@Composable
private fun CourtGlyph(modifier: Modifier, color: Color) {
    Canvas(modifier) {
        val w = 1.4.dp.toPx()
        drawRoundRect(
            color, style = Stroke(w),
            topLeft = Offset(w / 2, w / 2),
            size = size.copy(width = size.width - w, height = size.height - w),
            cornerRadius = androidx.compose.ui.geometry.CornerRadius(1.5.dp.toPx()),
        )
        drawLine(color, Offset(size.width / 2, 0f), Offset(size.width / 2, size.height), strokeWidth = w)
    }
}

/**
 * One track, one pill that slides under the choice, the way a phone's own settings
 * pick between options, instead of three boxes that each look like a button.
 */
@Composable
private fun MethodSegments(selected: PayMethod, onPick: (PayMethod) -> Unit) {
    val index = DeskMethods.indexOfFirst { it.first == selected }.coerceAtLeast(0)
    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .height(52.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Fill)
            .padding(4.dp),
    ) {
        val segment: Dp = maxWidth / DeskMethods.size
        val pillX by animateDpAsState(
            targetValue = segment * index,
            animationSpec = spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessMediumLow),
            label = "segment-pill",
        )
        Box(
            Modifier
                .offset(x = pillX)
                .width(segment)
                .fillMaxHeight()
                .shadow(3.dp, RoundedCornerShape(12.dp), ambientColor = Ink.copy(alpha = 0.10f), spotColor = Ink.copy(alpha = 0.14f))
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White),
        )
        Row(Modifier.fillMaxWidth().fillMaxHeight()) {
            DeskMethods.forEach { (method, label, icon) ->
                SegmentOption(
                    label = label,
                    icon = icon,
                    selected = method == selected,
                    modifier = Modifier.weight(1f),
                ) { onPick(method) }
            }
        }
    }
}

@Composable
private fun SegmentOption(label: String, icon: ImageVector, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val tint by animateColorAsState(if (selected) Blue else Faint, tween(180), label = "seg-tint")
    val text by animateColorAsState(if (selected) Ink else Muted, tween(180), label = "seg-text")
    Row(
        modifier
            .fillMaxHeight()
            .pressScale(interaction, 0.94f)
            .clip(RoundedCornerShape(12.dp))
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(7.dp))
        Text(label, fontSize = 13.5.sp, fontWeight = if (selected) FontWeight.Bold else FontWeight.SemiBold, color = text)
    }
}

/**
 * What the button will do, then a hairline, then for how much. The amount rolls when a
 * different court or hour changes it: up when it rises, down when it falls.
 */
@Composable
private fun PrimaryCta(label: String, amount: Double, loading: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (loading) Blue.copy(alpha = 0.82f) else Blue, tween(160), label = "cta-bg")
    Row(
        modifier
            .pressScale(interaction, 0.97f)
            .fillMaxWidth()
            .height(58.dp)
            .shadow(10.dp, RoundedCornerShape(18.dp), ambientColor = Blue.copy(alpha = 0.25f), spotColor = Blue.copy(alpha = 0.35f))
            .clip(RoundedCornerShape(18.dp))
            .background(bg)
            .pressShade(interaction, 0.12f)
            .clickable(interactionSource = interaction, indication = null, enabled = !loading, onClick = onClick)
            .padding(horizontal = 20.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            Spacer(Modifier.weight(1f))
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(12.dp))
            Text("Booking…", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White)
            Spacer(Modifier.weight(1f))
        } else {
            AnimatedContent(
                targetState = label,
                transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(100)) },
                modifier = Modifier.weight(1f),
                label = "cta-label",
            ) { l -> Text(l, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White) }
            Box(Modifier.width(1.dp).height(22.dp).background(Color.White.copy(alpha = 0.28f)))
            Spacer(Modifier.width(16.dp))
            AnimatedContent(
                targetState = amount,
                transitionSpec = { rollTransition(targetState >= initialState) },
                label = "cta-amount",
            ) { a ->
                Text("₹" + formatRupees(a), fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = Color.White, style = Tabular)
            }
        }
    }
}

// ---- Helpers ---------------------------------------------------------------------

/** A figure changing like an odometer: the new one comes from below when it went up. */
private fun rollTransition(up: Boolean): ContentTransform {
    val dir = if (up) 1 else -1
    return (slideInVertically(spring(dampingRatio = 0.8f, stiffness = Spring.StiffnessMediumLow)) { it * dir / 2 } + fadeIn(tween(160))) togetherWith
        (slideOutVertically(tween(140)) { -it * dir / 2 } + fadeOut(tween(100)))
}

private fun courtFree(slot: DaySlot?, court: CourtCol): Boolean {
    val cell = slot?.courts?.firstOrNull { it.courtId == court.id }
    return cell == null || (!cell.isBooked && !cell.isHeld && cell.allowed)
}

private fun courtPrice(slot: DaySlot?, courtId: Long?): Double? =
    slot?.courts?.firstOrNull { it.courtId == courtId }?.price ?: slot?.price

private val ClockRx = Regex("""(\d{1,2}(?::\d{2})?)\s*([AaPp][Mm])?""")

/** "9:00 AM" → ("9:00", "AM"); "18:00" → ("18:00", null). The first time in the string only. */
private fun splitClock(raw: String?): Pair<String, String?> {
    val m = ClockRx.find(raw.orEmpty()) ?: return (raw.orEmpty() to null)
    return m.groupValues[1] to m.groupValues[2].uppercase().ifEmpty { null }
}

/** The start of a slot, spelled the way the header shows it. */
private fun slotStartLabel(raw: String?): String {
    val (clock, mer) = splitClock(raw)
    return if (mer != null) "$clock $mer" else clock
}

/** The end of "9:00 AM – 10:00 AM", if the server spelled a range. Never guessed. */
private fun slotEndLabel(raw: String?): String? {
    val all = ClockRx.findAll(raw.orEmpty()).filter { it.value.isNotBlank() }.toList()
    if (all.size < 2) return null
    val m = all[1]
    val mer = m.groupValues[2].uppercase()
    return if (mer.isNotEmpty()) "${m.groupValues[1]} $mer" else m.groupValues[1]
}

/** A quick left-right shake, damping out: "this one", without a word. */
private suspend fun shake(anim: Animatable<Float, AnimationVector1D>) {
    anim.snapTo(0f)
    anim.animateTo(
        0f,
        keyframes {
            durationMillis = 380
            -14f at 50
            12f at 110
            -9f at 170
            6f at 230
            -3f at 290
        },
    )
}

/** Shows ten digits as "98765 43210" while the field keeps the bare digits. */
private val PhoneGrouping = VisualTransformation { text ->
    val raw = text.text
    val out = if (raw.length > 5) raw.substring(0, 5) + " " + raw.substring(5) else raw
    TransformedText(
        AnnotatedString(out),
        object : OffsetMapping {
            override fun originalToTransformed(offset: Int) = if (offset > 5) offset + 1 else offset
            override fun transformedToOriginal(offset: Int) = if (offset > 5) (offset - 1).coerceAtMost(raw.length) else offset
        },
    )
}

private fun formatRupees(amount: Double): String =
    if (amount % 1.0 == 0.0) "%,d".format(Locale("en", "IN"), amount.toLong())
    else String.format(Locale.US, "%.2f", amount)

/** "Today, 30 Sep", "Tomorrow, 1 Oct", else "Wed, 30 Sep". */
private fun friendlyDate(iso: String): String = runCatching {
    val parsed = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso) ?: return iso
    val day = Calendar.getInstance().apply { time = parsed }
    val today = Calendar.getInstance()
    fun sameDay(a: Calendar, b: Calendar) =
        a.get(Calendar.YEAR) == b.get(Calendar.YEAR) && a.get(Calendar.DAY_OF_YEAR) == b.get(Calendar.DAY_OF_YEAR)
    val tomorrow = (today.clone() as Calendar).apply { add(Calendar.DAY_OF_YEAR, 1) }
    val short = SimpleDateFormat("d MMM", Locale.getDefault()).format(parsed)
    when {
        sameDay(day, today) -> "Today, $short"
        sameDay(day, tomorrow) -> "Tomorrow, $short"
        else -> SimpleDateFormat("EEE, d MMM", Locale.getDefault()).format(parsed)
    }
}.getOrDefault(iso)
