package com.haraan.partner.daybookings.ui

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.AnimationVector1D
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.togetherWith
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
import androidx.compose.material.icons.outlined.Person
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
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
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
import com.haraan.partner.DayGrid
import com.haraan.partner.PayMethod
import com.haraan.partner.daybookings.model.WalkInTarget
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import kotlin.math.roundToInt

private val Blue = Color(0xFF1D4ED8)
private val BlueSoft = Color(0xFFEFF4FF)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFF9AA3B2)
private val Fill = Color(0xFFF4F6FA)
private val Hairline = Color(0xFFE6EAF0)
private val Red = Color(0xFFDC2626)

/** The three ways a walk-in pays at the desk, in the order the segmented control shows them. */
private val DeskMethods = listOf(
    Triple(PayMethod.CASH, "Cash", Icons.Outlined.Payments),
    Triple(PayMethod.UPI_QR, "UPI QR", Icons.Outlined.QrCode2),
    Triple(PayMethod.LINK, "Link", Icons.Outlined.Link),
)

/**
 * Taking a walk-in at the counter: who, which court, how they pay — then one button
 * that says exactly what it will do and for how much.
 *
 * Built for a person with a customer in front of them: the name field is focused on
 * open, the keyboard steps from name to phone to done, the phone number groups itself
 * as it's typed, and a missing field shakes with a reject buzz instead of a line of
 * red text appearing somewhere below the fold.
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
    var selectedCourtId by remember { mutableStateOf(target.courtId) }
    var selectedMethod by remember { mutableStateOf(PayMethod.CASH) }
    // Errors show only once the desk has tried to book — not while they're still typing.
    var triedSubmit by remember { mutableStateOf(false) }

    val nameShake = remember { Animatable(0f) }
    val phoneShake = remember { Animatable(0f) }
    val nameFocus = remember { FocusRequester() }

    val currentSlot = remember(grid, target.slotId) {
        grid?.slots?.firstOrNull { it.slotId == target.slotId }
    }
    val availableCourts = remember(currentSlot, grid) {
        grid?.courts?.filter { court ->
            val cell = currentSlot?.courts?.firstOrNull { it.courtId == court.id }
            cell == null || (!cell.isBooked && !cell.isHeld && cell.allowed)
        } ?: emptyList()
    }
    val effectivePrice = remember(selectedCourtId, currentSlot, target.basePrice) {
        val cell = currentSlot?.courts?.firstOrNull { it.courtId == selectedCourtId }
        cell?.price ?: currentSlot?.price ?: target.basePrice
    }
    val courtName = availableCourts.firstOrNull { it.id == selectedCourtId }?.name ?: target.courtName

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
        onSubmit(target.slotId, selectedCourtId, date, guestName.trim(), guestPhone, selectedMethod)
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp)
                .padding(bottom = 14.dp),
        ) {
            SlotHeader(
                time = target.slotTime,
                date = friendlyDate(date),
                court = courtName,
                price = effectivePrice,
                onClose = onDismiss,
            )

            Spacer(Modifier.height(22.dp))

            SectionLabel("Customer")
            Spacer(Modifier.height(8.dp))
            DeskField(
                value = guestName,
                onValueChange = { guestName = it.take(60) },
                placeholder = "Full name",
                leading = { Icon(Icons.Outlined.Person, null, tint = Faint, modifier = Modifier.size(20.dp)) },
                isError = triedSubmit && !nameValid,
                keyboardOptions = KeyboardOptions(
                    capitalization = KeyboardCapitalization.Words,
                    imeAction = ImeAction.Next,
                ),
                keyboardActions = KeyboardActions(onNext = { focus.moveFocus(androidx.compose.ui.focus.FocusDirection.Down) }),
                modifier = Modifier
                    .offset { IntOffset(nameShake.value.roundToInt(), 0) }
                    .focusRequester(nameFocus),
            )
            FieldError(visible = triedSubmit && !nameValid, text = "Add the customer's name")

            Spacer(Modifier.height(10.dp))
            DeskField(
                value = guestPhone,
                onValueChange = { raw ->
                    val digits = raw.filter(Char::isDigit)
                    // A pasted "+91 98765 43210" keeps its last ten; typing stops at ten.
                    guestPhone = if (digits.length > 10 && digits.length - guestPhone.length > 1) digits.takeLast(10) else digits.take(10)
                },
                placeholder = "98765 43210",
                leading = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("+91", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink)
                        Spacer(Modifier.width(10.dp))
                        Box(Modifier.width(1.dp).height(20.dp).background(Hairline))
                    }
                },
                isError = triedSubmit && !phoneValid,
                visualTransformation = PhoneGrouping,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { focus.clearFocus() }),
                trailing = if (phoneValid) {
                    { Box(Modifier.size(8.dp).clip(CircleShape).background(Color(0xFF16A34A))) }
                } else null,
                modifier = Modifier.offset { IntOffset(phoneShake.value.roundToInt(), 0) },
            )
            FieldError(
                visible = triedSubmit && !phoneValid,
                text = if (guestPhone.isEmpty()) "Add a mobile number" else "${10 - guestPhone.length} more digit${if (10 - guestPhone.length == 1) "" else "s"}",
            )

            // Courts: only a choice when there is one to make.
            if (availableCourts.size > 1) {
                Spacer(Modifier.height(20.dp))
                SectionLabel("Court")
                Spacer(Modifier.height(8.dp))
                Row(
                    Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    availableCourts.forEach { court ->
                        CourtPill(
                            label = court.name,
                            selected = court.id == selectedCourtId,
                        ) {
                            if (selectedCourtId != court.id) {
                                Haptics.tick(view)
                                selectedCourtId = court.id
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            SectionLabel("Payment")
            Spacer(Modifier.height(8.dp))
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
                )
            }

            Spacer(Modifier.height(22.dp))
            PrimaryCta(
                label = when (selectedMethod) {
                    PayMethod.UPI_QR -> "Book & show QR"
                    PayMethod.LINK -> "Book & send link"
                    else -> "Collect cash"
                },
                amount = effectivePrice,
                loading = isSubmitting,
                onClick = ::submit,
            )
        }
    }
}

// ---- Pieces ----------------------------------------------------------------------

@Composable
private fun SlotHeader(time: String, date: String, court: String?, price: Double, onClose: () -> Unit) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            Text("NEW WALK-IN", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Blue, letterSpacing = 1.2.sp)
            Spacer(Modifier.height(6.dp))
            Text(time, fontSize = 28.sp, fontWeight = FontWeight.ExtraBold, color = Ink, lineHeight = 30.sp)
            Spacer(Modifier.height(4.dp))
            Text(
                listOfNotNull(date, court).joinToString("  ·  "),
                fontSize = 13.5.sp, color = Muted, fontWeight = FontWeight.Medium,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            CloseButton(onClose)
            Spacer(Modifier.height(10.dp))
            Text("₹" + formatRupees(price), fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
        }
    }
}

@Composable
private fun CloseButton(onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Box(
        Modifier
            .pressScale(interaction, 0.9f)
            .size(36.dp)
            .clip(CircleShape)
            .background(Fill)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Outlined.Close, contentDescription = "Close", tint = Muted, modifier = Modifier.size(18.dp))
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink)
}

@Composable
private fun DeskField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    leading: @Composable () -> Unit,
    isError: Boolean,
    keyboardOptions: KeyboardOptions,
    keyboardActions: KeyboardActions,
    modifier: Modifier = Modifier,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailing: (@Composable () -> Unit)? = null,
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    val border by animateColorAsState(
        when {
            isError -> Red
            focused -> Blue
            else -> Hairline
        },
        animationSpec = tween(160), label = "field-border",
    )
    val bg by animateColorAsState(if (focused) Color.White else Fill, tween(160), label = "field-bg")
    val width by animateDpAsState(if (focused || isError) 1.5.dp else 1.dp, tween(160), label = "field-w")

    Row(
        modifier = modifier
            .fillMaxWidth()
            .height(56.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .border(width, border, RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        leading()
        Spacer(Modifier.width(12.dp))
        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
            if (value.isEmpty()) Text(placeholder, fontSize = 15.sp, color = Faint)
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = true,
                interactionSource = interaction,
                visualTransformation = visualTransformation,
                keyboardOptions = keyboardOptions,
                keyboardActions = keyboardActions,
                textStyle = TextStyle(fontSize = 15.sp, color = Ink, fontWeight = FontWeight.SemiBold, letterSpacing = 0.2.sp),
                cursorBrush = SolidColor(Blue),
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (trailing != null) {
            Spacer(Modifier.width(10.dp))
            trailing()
        }
    }
}

@Composable
private fun FieldError(visible: Boolean, text: String) {
    AnimatedVisibility(visible, enter = fadeIn() + expandVertically(), exit = fadeOut() + shrinkVertically()) {
        Text(text, fontSize = 12.sp, color = Red, fontWeight = FontWeight.Medium, modifier = Modifier.padding(start = 6.dp, top = 6.dp))
    }
}

@Composable
private fun CourtPill(label: String, selected: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (selected) Ink else Fill, tween(180), label = "court-bg")
    val fg by animateColorAsState(if (selected) Color.White else Ink, tween(180), label = "court-fg")
    Box(
        Modifier
            .pressScale(interaction, 0.94f)
            .height(40.dp)
            .clip(RoundedCornerShape(99.dp))
            .background(bg)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 18.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = fg, maxLines = 1)
    }
}

/**
 * One track, one pill that slides under the choice — the way a phone's own settings
 * pick between options — instead of three boxes that each look like a button.
 */
@Composable
private fun MethodSegments(selected: PayMethod, onPick: (PayMethod) -> Unit) {
    val index = DeskMethods.indexOfFirst { it.first == selected }.coerceAtLeast(0)
    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .height(54.dp)
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
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(12.dp)),
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
    val tint by animateColorAsState(if (selected) Blue else Muted, tween(180), label = "seg-tint")
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
        Text(label, fontSize = 13.5.sp, fontWeight = if (selected) FontWeight.Bold else FontWeight.SemiBold, color = if (selected) Ink else Muted)
    }
}

@Composable
private fun PrimaryCta(label: String, amount: Double, loading: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .pressScale(interaction, 0.97f)
            .fillMaxWidth()
            .height(58.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(if (loading) Blue.copy(alpha = 0.85f) else Blue)
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
            Box(
                Modifier.clip(RoundedCornerShape(10.dp)).background(Color.White.copy(alpha = 0.16f))
                    .padding(horizontal = 12.dp, vertical = 6.dp),
            ) {
                Text("₹" + formatRupees(amount), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Color.White)
            }
        }
    }
}

// ---- Helpers ---------------------------------------------------------------------

/** A quick left-right shake, damping out — "this one", without a word. */
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

/** "Today · 30 Sep", "Tomorrow · 1 Oct", else "Wed, 30 Sep". */
private fun friendlyDate(iso: String): String = runCatching {
    val parsed = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso) ?: return iso
    val day = Calendar.getInstance().apply { time = parsed }
    val today = Calendar.getInstance()
    fun sameDay(a: Calendar, b: Calendar) =
        a.get(Calendar.YEAR) == b.get(Calendar.YEAR) && a.get(Calendar.DAY_OF_YEAR) == b.get(Calendar.DAY_OF_YEAR)
    val tomorrow = (today.clone() as Calendar).apply { add(Calendar.DAY_OF_YEAR, 1) }
    val short = SimpleDateFormat("d MMM", Locale.getDefault()).format(parsed)
    when {
        sameDay(day, today) -> "Today · $short"
        sameDay(day, tomorrow) -> "Tomorrow · $short"
        else -> SimpleDateFormat("EEE, d MMM", Locale.getDefault()).format(parsed)
    }
}.getOrDefault(iso)
