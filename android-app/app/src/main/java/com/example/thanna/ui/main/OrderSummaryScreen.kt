package com.example.thanna.ui.main

import android.widget.Toast
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.QrCode2
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.thanna.OrderLine
import com.example.thanna.OrderSummary
import com.example.thanna.R
import com.example.thanna.data.BookingLite
import com.example.thanna.data.BookingRepository
import com.example.thanna.data.BookingResult
import com.example.thanna.data.TokenStore
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography
import com.example.thanna.ui.theme.premiumCardShadow
import kotlinx.coroutines.launch

/**
 * Order Summary — the review step between the ticket cart and the real /api/bookings
 * call. Lists every tier line, shows an honest bill (each line priced at its live
 * tier/phase price — no hidden fees), then confirms. On success a single-line order
 * jumps straight to the QR entry pass; a multi-line order shows a confirmation that
 * points to My Schedule where every pass lives.
 */
@Composable
fun OrderSummaryScreen(order: OrderSummary, onBack: () -> Unit) {
    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current
    val scope = rememberCoroutineScope()

    val subtotal = order.lines.sumOf { it.unitPrice * it.quantity }
    val totalTickets = order.lines.sumOf { it.quantity }
    // Host-set convenience fee — same formula the server charges (kept in sync).
    val fee = when {
        subtotal <= 0.0 -> 0.0
        order.feeType == "flat" -> order.feeValue.coerceAtLeast(0.0)
        order.feeType == "percent" -> Math.round(subtotal * order.feeValue / 100.0).toDouble()
        else -> 0.0
    }
    // Coupon: a flat ₹ discount applied against the payable total.
    var couponInput by remember { mutableStateOf("") }
    var appliedCode by remember { mutableStateOf<String?>(null) }
    var rawDiscount by remember { mutableStateOf(0.0) }
    var couponError by remember { mutableStateOf<String?>(null) }
    var validating by remember { mutableStateOf(false) }
    val discount = rawDiscount.coerceAtMost(subtotal + fee)

    val grandTotal = (subtotal + fee - discount).coerceAtLeast(0.0)
    val isFree = grandTotal <= 0.0

    var booking by remember { mutableStateOf(false) }
    var pass by remember { mutableStateOf<BookingLite?>(null) }
    var confirmedCount by remember { mutableStateOf(0) } // >0 → multi-pass success screen

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HaraanColors.Background)
    ) {
        Column(Modifier.fillMaxSize()) {
            // ── Top bar ──────────────────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .padding(horizontal = HaraanSpacing.Compact, vertical = HaraanSpacing.Compact),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .clickable(onClick = onBack),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = HaraanColors.TextPrimary,
                        modifier = Modifier.size(22.dp),
                    )
                }
                Spacer(Modifier.width(HaraanSpacing.Micro))
                Text(
                    "Order summary",
                    style = HaraanTypography.TitleMedium.copy(
                        color = HaraanColors.TextPrimary,
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp,
                    ),
                )
            }

            // ── Scrollable content ───────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = HaraanSpacing.Medium)
                    .padding(bottom = 24.dp),
                verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Medium),
            ) {
                EventCard(order)
                OrderCard(lines = order.lines)
                BillCard(
                    subtotal = subtotal,
                    fee = fee,
                    discount = discount,
                    total = grandTotal,
                    feeType = order.feeType,
                    feeValue = order.feeValue,
                ) {
                    CouponField(
                        appliedCode = appliedCode,
                        input = couponInput,
                        error = couponError,
                        validating = validating,
                        onInputChange = { couponInput = it; couponError = null },
                        onApply = {
                            val code = couponInput.trim()
                            if (code.isEmpty() || validating) return@CouponField
                            val token = TokenStore.getToken(context)
                            if (token.isNullOrBlank()) {
                                couponError = "Please sign in to use a coupon."
                                return@CouponField
                            }
                            validating = true
                            couponError = null
                            scope.launch {
                                val res = BookingRepository().validateCoupon(token, code, order.eventId)
                                validating = false
                                if (res.valid) {
                                    appliedCode = res.code ?: code
                                    rawDiscount = res.discount
                                    couponInput = ""
                                    haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                                } else {
                                    couponError = res.message
                                }
                            }
                        },
                        onRemove = {
                            appliedCode = null
                            rawDiscount = 0.0
                            couponError = null
                        },
                    )
                }
                DeliveryNote()
                SecuredFooter()
            }

            // ── Sticky confirm bar ───────────────────────────────────────────
            Surface(
                color = HaraanColors.Surface,
                shadowElevation = 16.dp,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .padding(horizontal = HaraanSpacing.Medium, vertical = HaraanSpacing.Compact),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Column {
                        Text(
                            text = if (isFree) "Free" else "₹${grandTotal.toInt()}",
                            style = HaraanTypography.TitleLarge.copy(
                                fontSize = 22.sp,
                                color = HaraanColors.TextPrimary,
                                fontWeight = FontWeight.ExtraBold,
                            ),
                        )
                        Text(
                            text = "$totalTickets ticket${if (totalTickets == 1) "" else "s"}",
                            style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextMuted, fontSize = 12.sp),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }

                    val ctaLabel = if (isFree) "Get tickets" else "Confirm & pay"
                    Surface(
                        onClick = {
                            if (booking) return@Surface
                            haptics.performHapticFeedback(HapticFeedbackType.LongPress)

                            val token = TokenStore.getToken(context)
                            if (token.isNullOrBlank()) {
                                Toast.makeText(context, "Please sign in to book tickets.", Toast.LENGTH_LONG).show()
                                return@Surface
                            }
                            if (order.eventId <= 0) {
                                Toast.makeText(context, "This event isn't bookable yet.", Toast.LENGTH_LONG).show()
                                return@Surface
                            }

                            booking = true
                            scope.launch {
                                val items = order.lines.map { line ->
                                    line.ticketTypeId.takeIf { it > 0 } to line.quantity
                                }
                                val result = BookingRepository().createOrder(
                                    token = token,
                                    eventId = order.eventId,
                                    items = items,
                                    couponCode = appliedCode,
                                )
                                booking = false
                                when (result) {
                                    is BookingResult.Success -> {
                                        haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                                        if (result.bookingCount > 1) {
                                            confirmedCount = result.bookingCount
                                        } else {
                                            pass = BookingLite(
                                                id = result.bookingId.toLong(),
                                                status = result.status,
                                                quantity = result.quantity,
                                                totalAmount = result.totalAmount.toDoubleOrNull() ?: grandTotal,
                                                type = "event",
                                                eventTitle = order.title,
                                                eventVenue = order.venue.ifBlank { null },
                                                eventDate = order.date.ifBlank { null },
                                                ticketCode = result.ticketCode,
                                                imageUrl = order.imageUrl.ifBlank { null },
                                            )
                                        }
                                    }
                                    is BookingResult.Error ->
                                        Toast.makeText(context, result.message, Toast.LENGTH_LONG).show()
                                }
                            }
                        },
                        shape = RoundedCornerShape(24.dp),
                        color = HaraanColors.EventsBlue,
                        modifier = Modifier.height(52.dp),
                    ) {
                        Box(
                            modifier = Modifier.padding(horizontal = 28.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            if (booking) {
                                CircularProgressIndicator(
                                    color = Color.White,
                                    strokeWidth = 2.dp,
                                    modifier = Modifier.size(22.dp),
                                )
                            } else {
                                Text(
                                    text = ctaLabel,
                                    style = HaraanTypography.TitleMedium.copy(
                                        color = Color.White,
                                        fontSize = 16.sp,
                                        fontWeight = FontWeight.Bold,
                                    ),
                                )
                            }
                        }
                    }
                }
            }
        }

        // ── Success: single order → the QR pass; multi-tier → confirmation ────
        pass?.let { b ->
            TicketPassScreen(booking = b, onClose = onBack)
        }
        if (confirmedCount > 0) {
            OrderConfirmedOverlay(passCount = confirmedCount, onDone = onBack)
        }
    }
}

/** Event identity — poster thumb, title, date, venue. */
@Composable
private fun EventCard(order: OrderSummary) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = HaraanRadius.Large)
            .clip(RoundedCornerShape(HaraanRadius.Large))
            .background(HaraanColors.Surface)
            .padding(HaraanSpacing.Cozy),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(96.dp)
                .clip(RoundedCornerShape(HaraanRadius.Medium))
                .background(Color(0xFFEFF3F8)),
            contentAlignment = Alignment.Center,
        ) {
            if (order.imageUrl.isNotBlank()) {
                AsyncImage(
                    model = order.imageUrl,
                    contentDescription = order.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(HaraanRadius.Medium)),
                )
            } else {
                Image(
                    painter = painterResource(id = R.drawable.haraan_copy),
                    contentDescription = null,
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.size(40.dp),
                )
            }
        }
        Spacer(Modifier.width(HaraanSpacing.Medium))
        Column(Modifier.weight(1f)) {
            Text(
                order.title,
                style = HaraanTypography.TitleLarge.copy(
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 18.sp,
                    lineHeight = 23.sp,
                ),
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            if (order.date.isNotBlank()) {
                Text(
                    order.date,
                    style = HaraanTypography.BodyMedium.copy(color = HaraanColors.EventsBlue, fontSize = 13.sp, fontWeight = FontWeight.SemiBold),
                    modifier = Modifier.padding(top = 6.dp),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            if (order.venue.isNotBlank()) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 4.dp),
                ) {
                    Icon(
                        Icons.Outlined.Place,
                        contentDescription = null,
                        tint = HaraanColors.TextMuted,
                        modifier = Modifier.size(14.dp),
                    )
                    Spacer(Modifier.width(4.dp))
                    Text(
                        order.venue,
                        style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextSecondary, fontSize = 12.5.sp),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

/** The chosen order — one row per tier line, each with its own line total. */
@Composable
private fun OrderCard(lines: List<OrderLine>) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = HaraanRadius.Large)
            .clip(RoundedCornerShape(HaraanRadius.Large))
            .background(HaraanColors.Surface)
            .padding(HaraanSpacing.Medium),
        verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Compact),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                Icons.Outlined.ConfirmationNumber,
                contentDescription = null,
                tint = HaraanColors.EventsBlue,
                modifier = Modifier.size(18.dp),
            )
            Spacer(Modifier.width(HaraanSpacing.Small))
            Text(
                "Your order",
                style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary),
            )
        }

        lines.forEachIndexed { i, line ->
            if (i > 0) {
                Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
            }
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(
                        line.name,
                        style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    val sub = buildString {
                        append(if (line.unitPrice <= 0.0) "Free" else "₹${line.unitPrice.toInt()}")
                        append(" × ${line.quantity}")
                        if (line.admits > 1) append(" · admits ${line.admits} each")
                        if (line.phaseLabel.isNotBlank()) append(" · ${line.phaseLabel}")
                    }
                    Text(
                        sub,
                        style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextMuted, fontSize = 12.5.sp),
                        modifier = Modifier.padding(top = 2.dp),
                    )
                }
                Text(
                    text = (line.unitPrice * line.quantity).let { if (it <= 0.0) "Free" else "₹${it.toInt()}" },
                    style = HaraanTypography.TitleMedium.copy(color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp),
                )
            }
        }
    }
}

/**
 * Honest bill — subtotal, the host-set convenience fee (a real charge, or "Free"
 * when the host set none), and the grand total.
 */
@Composable
private fun BillCard(
    subtotal: Double,
    fee: Double,
    discount: Double,
    total: Double,
    feeType: String,
    feeValue: Double,
    couponSlot: @Composable () -> Unit,
) {
    val subtotalFree = subtotal <= 0.0
    val totalFree = total <= 0.0
    // Label the fee row with its rate when it's a percentage, e.g. "Convenience fee (5%)".
    val feeLabel = if (feeType == "percent" && feeValue > 0.0) {
        "Convenience fee (${feeValue.trimZeros()}%)"
    } else {
        "Convenience fee"
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = HaraanRadius.Large)
            .clip(RoundedCornerShape(HaraanRadius.Large))
            .background(HaraanColors.Surface)
            .padding(HaraanSpacing.Medium),
    ) {
        Text(
            "Bill details",
            style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary),
        )
        Spacer(Modifier.height(HaraanSpacing.Compact))

        BillRow("Subtotal", if (subtotalFree) "Free" else "₹${subtotal.toInt()}")
        Spacer(Modifier.height(HaraanSpacing.Small))
        if (fee > 0.0) {
            BillRow(feeLabel, "₹${fee.toInt()}")
        } else {
            BillRow(feeLabel, "Free", valueColor = HaraanColors.GameHubGreen)
        }
        if (discount > 0.0) {
            Spacer(Modifier.height(HaraanSpacing.Small))
            BillRow("Coupon discount", "–₹${discount.toInt()}", valueColor = HaraanColors.GameHubGreen)
        }

        // Coupon apply — sits directly under the fee/discount rows.
        Spacer(Modifier.height(HaraanSpacing.Compact))
        couponSlot()

        Spacer(Modifier.height(HaraanSpacing.Compact))
        Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
        Spacer(Modifier.height(HaraanSpacing.Compact))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "Total",
                style = HaraanTypography.TitleMedium.copy(color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold),
            )
            Text(
                text = if (totalFree) "Free" else "₹${total.toInt()}",
                style = HaraanTypography.TitleLarge.copy(
                    color = HaraanColors.TextPrimary,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.ExtraBold,
                ),
            )
        }
    }
}

/** Drop a trailing ".0" so a 5% fee reads "5%" not "5.0%". */
private fun Double.trimZeros(): String =
    if (this % 1.0 == 0.0) toInt().toString() else toString()

@Composable
private fun BillRow(label: String, value: String, valueColor: Color = HaraanColors.TextPrimary) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(label, style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextSecondary))
        Text(value, style = HaraanTypography.BodyLarge.copy(color = valueColor, fontWeight = FontWeight.SemiBold))
    }
}

/**
 * Coupon apply — a compact field + Apply button, or the applied state with a
 * remove affordance. Lives inside the bill card, under the fee rows.
 */
@Composable
private fun CouponField(
    appliedCode: String?,
    input: String,
    error: String?,
    validating: Boolean,
    onInputChange: (String) -> Unit,
    onApply: () -> Unit,
    onRemove: () -> Unit,
) {
    if (appliedCode != null) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(HaraanRadius.Small))
                .background(HaraanColors.GameHubGreen.copy(alpha = 0.10f))
                .padding(horizontal = HaraanSpacing.Compact, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.Check,
                    contentDescription = null,
                    tint = HaraanColors.GameHubGreen,
                    modifier = Modifier.size(16.dp),
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    "Coupon “$appliedCode” applied",
                    style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold),
                )
            }
            Text(
                "Remove",
                style = HaraanTypography.SectionAction.copy(color = HaraanColors.LiveRed),
                modifier = Modifier.clickable(onClick = onRemove),
            )
        }
        return
    }

    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(HaraanRadius.Small))
                .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(HaraanRadius.Small))
                .padding(start = HaraanSpacing.Compact, end = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            BasicTextField(
                value = input,
                onValueChange = onInputChange,
                singleLine = true,
                textStyle = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontSize = 14.sp),
                cursorBrush = SolidColor(HaraanColors.EventsBlue),
                modifier = Modifier.weight(1f).padding(vertical = 14.dp),
                decorationBox = { inner ->
                    if (input.isEmpty()) {
                        Text(
                            "Have a coupon code?",
                            style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextMuted, fontSize = 14.sp),
                        )
                    }
                    inner()
                },
            )
            val canApply = input.isNotBlank() && !validating
            Surface(
                onClick = onApply,
                shape = RoundedCornerShape(HaraanRadius.Small),
                color = if (canApply) HaraanColors.EventsBlue else HaraanColors.BorderLight,
                modifier = Modifier.height(40.dp),
            ) {
                Box(Modifier.padding(horizontal = 20.dp), contentAlignment = Alignment.Center) {
                    if (validating) {
                        CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                    } else {
                        Text(
                            "Apply",
                            style = HaraanTypography.TitleMedium.copy(
                                color = if (canApply) Color.White else HaraanColors.TextMuted,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                            ),
                        )
                    }
                }
            }
        }
        if (error != null) {
            Text(
                error,
                style = HaraanTypography.BodyMedium.copy(color = HaraanColors.LiveRed, fontSize = 12.sp),
            )
        }
    }
}

/** Quiet trust line that anchors the bottom of the scroll. */
@Composable
private fun SecuredFooter() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = HaraanSpacing.Small, bottom = HaraanSpacing.Small),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            Icons.Outlined.Lock,
            contentDescription = null,
            tint = HaraanColors.TextMuted,
            modifier = Modifier.size(13.dp),
        )
        Spacer(Modifier.width(6.dp))
        Text(
            "Secured by Haraan · Verified ticketing",
            style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextMuted, fontSize = 12.sp),
        )
    }
}

/** Reassurance: what the buyer gets and how they get in. */
@Composable
private fun DeliveryNote() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(HaraanRadius.Medium))
            .background(HaraanColors.EventsBlue.copy(alpha = 0.06f))
            .padding(HaraanSpacing.Compact),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            Icons.Outlined.QrCode2,
            contentDescription = null,
            tint = HaraanColors.EventsBlue,
            modifier = Modifier.size(20.dp),
        )
        Spacer(Modifier.width(HaraanSpacing.Compact))
        Column(Modifier.weight(1f)) {
            Text(
                "Instant e-ticket",
                style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp),
            )
            Text(
                "A scannable QR pass lands in My Schedule — show it at the gate.",
                style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextSecondary, fontSize = 12.sp),
                modifier = Modifier.padding(top = 1.dp),
            )
        }
        Icon(
            Icons.Outlined.Lock,
            contentDescription = null,
            tint = HaraanColors.TextMuted,
            modifier = Modifier.size(15.dp),
        )
    }
}

/**
 * Multi-tier success: several passes were issued (one per tier), so there's no single
 * QR to show here — the passes live in My Schedule. A clean, honest confirmation.
 */
@Composable
private fun OrderConfirmedOverlay(passCount: Int, onDone: () -> Unit) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xF20B1622))
            .clickable(enabled = false) {},
        contentAlignment = Alignment.Center,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .widthIn(max = 360.dp)
                .padding(24.dp)
                .clip(RoundedCornerShape(HaraanRadius.Hero))
                .background(HaraanColors.Surface)
                .padding(28.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(CircleShape)
                    .background(HaraanColors.GameHubGreen.copy(alpha = 0.14f)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.Default.Check,
                    contentDescription = null,
                    tint = HaraanColors.GameHubGreen,
                    modifier = Modifier.size(34.dp),
                )
            }
            Spacer(Modifier.height(HaraanSpacing.Medium))
            Text(
                "Order confirmed",
                style = HaraanTypography.TitleLarge.copy(color = HaraanColors.TextPrimary, fontSize = 20.sp),
            )
            Spacer(Modifier.height(HaraanSpacing.Small))
            Text(
                "$passCount passes are ready in My Schedule — open one to show its QR at the gate.",
                style = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextSecondary, fontSize = 14.sp),
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(HaraanSpacing.Large))
            Surface(
                onClick = onDone,
                shape = RoundedCornerShape(24.dp),
                color = HaraanColors.EventsBlue,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text(
                        "Done",
                        style = HaraanTypography.TitleMedium.copy(color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold),
                    )
                }
            }
        }
    }
}
