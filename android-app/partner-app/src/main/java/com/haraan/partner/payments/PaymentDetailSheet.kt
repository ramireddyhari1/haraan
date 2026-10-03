package com.haraan.partner.payments

import android.graphics.Bitmap
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
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
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.RoundRect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Outline
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathOperation
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.layout.layout
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import com.haraan.partner.ApiException
import com.haraan.partner.BookingSummary
import com.haraan.partner.PartnerApi
import com.haraan.partner.PayMethod
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hair = Color(0x140F172A)
private val BrandLight = Color(0xFF4D8BFF)
private val Brand = Color(0xFF2563EB)
private val BrandDeep = Color(0xFF1E40AF)
private val BrandInk = Color(0xFF1D4ED8)
private val Good = Color(0xFF16A34A)
private val Due = Color(0xFFDC2626)
private val Amber = Color(0xFFD97706)

/**
 * One payment, opened — as a boarding pass, because that's what a booking is.
 *
 * The blue top of the pass is who and how much, with the paid stamp pressed across it.
 * A perforated tear with real notches. The stub: date, time and venue in columns, the
 * ticket's QR (the code the gate scanner reads), where the booking is on its way from
 * booked to played, and the actions that still make sense.
 *
 * The pass leans a few degrees toward your finger; the stamp thumps on with a buzz.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PaymentDetailSheet(
    b: BookingSummary,
    api: PartnerApi,
    token: String,
    canManage: Boolean,
    onChanged: () -> Unit,
    onDismiss: () -> Unit,
) {
    val view = LocalView.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val clipboard = LocalClipboardManager.current
    val sheet = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var paid by remember { mutableStateOf(b.amountPaid) }
    var method by remember { mutableStateOf(b.paymentMethod) }
    var cancelled by remember { mutableStateOf((b.status ?: "").lowercase().startsWith("cancel")) }
    var collecting by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var askCancel by remember { mutableStateOf(false) }
    var copied by remember { mutableStateOf(false) }
    val owed = (b.amount - paid).coerceAtLeast(0.0)
    val walkIn = b.channel.equals("offline", true)
    val bookedAt = parseIso(b.createdAt)
    val slotOver = slotPassed(b.slotDate, b.slotLabel)

    // Everything rises in, then the stamp lands.
    val rise = remember { Animatable(0f) }
    val stamp = remember { Animatable(0f) }
    var stampKey by remember { mutableStateOf(0) }
    LaunchedEffect(Unit) { rise.animateTo(1f, tween(420, easing = FastOutSlowInEasing)) }
    LaunchedEffect(stampKey) {
        delay(if (stampKey == 0) 380 else 120)
        stamp.snapTo(0f)
        stamp.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMediumLow))
        if (stampKey > 0) Haptics.money(view) else Haptics.confirm(view)
    }
    LaunchedEffect(copied) { if (copied) { delay(1600); copied = false } }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheet, containerColor = Color(0xFFF5F7FB)) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState())
                .padding(start = 16.dp, end = 16.dp, bottom = 20.dp).navigationBarsPadding()
                .graphicsLayer { alpha = rise.value; translationY = (1f - rise.value) * 40.dp.toPx() },
        ) {
            Pass(
                b = b, walkIn = walkIn, bookedAt = bookedAt, paid = paid, owed = owed, method = method,
                cancelled = cancelled, stamp = stamp.value, copied = copied,
                onCopy = { code -> clipboard.setText(AnnotatedString(code)); copied = true; Haptics.tick(view) },
            )
            Spacer(Modifier.height(16.dp))
            Contact(b, walkIn)
            Spacer(Modifier.height(16.dp))

            Track(
                listOf(
                    Node("Booked", true),
                    Node(if (cancelled) "Cancelled" else if (owed > 0 && paid > 0) "Part paid" else "Paid", cancelled || paid > 0, alert = cancelled || (owed > 0 && paid > 0)),
                    Node("Checked in", b.checkedIn > 0),
                    when {
                        cancelled -> Node("Played", false)
                        !slotOver -> Node("Played", false)
                        b.checkedIn > 0 -> Node("Played", true)
                        else -> Node("No-show?", true, alert = true)
                    },
                ),
            )

            error?.let {
                Spacer(Modifier.height(12.dp))
                Text(it, fontSize = 13.sp, color = Due, fontWeight = FontWeight.SemiBold)
            }

            Spacer(Modifier.height(18.dp))
            // ---- what can still be done
            if (canManage && !cancelled && owed > 0) {
                if (!collecting) {
                    PrimaryButton("Collect ₹" + formatInr(owed)) { Haptics.tick(view); collecting = true }
                } else {
                    Text("How did they pay?", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(PayMethod.CASH, PayMethod.UPI, PayMethod.CARD).forEach { m ->
                            SoftButton(m.label, Modifier.weight(1f), enabled = !busy) {
                                busy = true; error = null
                                scope.launch {
                                    runCatching { api.collectAtDesk(token, b.id, m, null) }
                                        .onSuccess { paid = b.amount; method = m.api; collecting = false; stampKey++; onChanged() }
                                        .onFailure { e -> Haptics.reject(view); error = (e as? ApiException)?.message ?: "Couldn't record that. Try again." }
                                    busy = false
                                }
                            }
                        }
                    }
                }
                Spacer(Modifier.height(10.dp))
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                SoftButton("Share receipt", Modifier.weight(1f)) {
                    Haptics.tick(view)
                    val text = buildString {
                        append("Haraan receipt\n")
                        append(b.customer).append("\n")
                        append(listOfNotNull(b.branch ?: b.label, b.slotDate?.let { dayOf(it) }, slotTime(b.slotLabel)).joinToString(" · ")).append("\n")
                        append("Amount ₹").append(formatInr(b.amount))
                        append(" · Paid ₹").append(formatInr(paid))
                        if (owed > 0) append(" · Due ₹").append(formatInr(owed))
                        b.ticketCode?.let { append("\nTicket ").append(it) }
                    }
                    runCatching {
                        context.startActivity(
                            android.content.Intent.createChooser(
                                android.content.Intent(android.content.Intent.ACTION_SEND).setType("text/plain").putExtra(android.content.Intent.EXTRA_TEXT, text),
                                "Share receipt",
                            ),
                        )
                    }
                }
                if (canManage && !cancelled && !slotOver) {
                    SoftButton("Cancel", Modifier.weight(1f), danger = true) { askCancel = true }
                }
            }
        }
    }

    if (askCancel) {
        AlertDialog(
            onDismissRequest = { askCancel = false },
            containerColor = Color.White,
            title = { Text("Cancel ${b.customer}'s booking?", fontWeight = FontWeight.Bold) },
            text = { Text("The court frees up for others. Money already taken isn't refunded from here.") },
            confirmButton = {
                TextButton(onClick = {
                    askCancel = false; busy = true
                    scope.launch {
                        runCatching { api.cancelBooking(token, b.id) }
                            .onSuccess { cancelled = true; Haptics.confirm(view); stampKey++; onChanged() }
                            .onFailure { e -> Haptics.reject(view); error = (e as? ApiException)?.message ?: "Couldn't cancel. Try again." }
                        busy = false
                    }
                }) { Text("Cancel booking", color = Due, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(onClick = { askCancel = false }) { Text("Keep it") } },
        )
    }
}

/** The boarding pass. */
@Composable
private fun Pass(
    b: BookingSummary,
    walkIn: Boolean,
    bookedAt: java.util.Date?,
    paid: Double,
    owed: Double,
    method: String?,
    cancelled: Boolean,
    stamp: Float,
    copied: Boolean,
    onCopy: (String) -> Unit,
) {
    var tearY by remember { mutableFloatStateOf(0f) }
    val shape = remember(tearY) { PassShape(tearY) }
    // A few degrees of lean toward the finger.
    var tiltX by remember { mutableFloatStateOf(0f) }
    var tiltY by remember { mutableFloatStateOf(0f) }
    val tx by animateFloatAsState(tiltX, spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessLow), label = "tx")
    val ty by animateFloatAsState(tiltY, spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessLow), label = "ty")
    val (word, stampColor) = when {
        cancelled -> "CANCELLED" to Color(0xFF94A3B8)
        owed <= 0 && paid > 0 -> "PAID" to Color(0xFF86EFAC)
        paid > 0 -> "PART PAID" to Color(0xFFFCD34D)
        else -> "DUE" to Color(0xFFFCA5A5)
    }

    Box(
        Modifier.fillMaxWidth()
            .graphicsLayer { rotationX = ty; rotationY = tx; cameraDistance = 14f * density }
            .pointerInput(Unit) {
                awaitEachGesture {
                    val down = awaitFirstDown(requireUnconsumed = false)
                    val w = size.width.toFloat()
                    val h = size.height.toFloat()
                    tiltX = ((down.position.x / w) - 0.5f) * 8f
                    tiltY = -((down.position.y / h) - 0.5f) * 6f
                    do {
                        val e = awaitPointerEvent()
                        val p = e.changes.firstOrNull() ?: break
                        tiltX = ((p.position.x / w) - 0.5f) * 8f
                        tiltY = -((p.position.y / h) - 0.5f) * 6f
                    } while (e.changes.any { it.pressed })
                    tiltX = 0f; tiltY = 0f
                }
            },
    ) {
        Column(
            Modifier.fillMaxWidth()
                .shadow(16.dp, shape, clip = false, spotColor = Color(0x551E40AF))
                .clip(shape)
                .background(Color.White),
        ) {
            // ---- blue top: who, how much
            Box(
                Modifier.fillMaxWidth()
                    .onGloballyPositioned { tearY = it.size.height.toFloat() }
                    .background(Brush.linearGradient(listOf(BrandLight, Brand, BrandDeep), start = Offset.Zero, end = Offset(1000f, 700f)))
                    .drawBehind {
                        drawCircle(Brush.radialGradient(listOf(Color.White.copy(alpha = 0.2f), Color.Transparent), Offset.Zero, size.width * 0.6f), size.width * 0.6f, Offset.Zero)
                    }
                    .padding(start = 18.dp, end = 18.dp, top = 18.dp, bottom = 20.dp),
            ) {
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(46.dp).clip(RoundedCornerShape(99.dp)).background(Color.White), contentAlignment = Alignment.Center) {
                            Text(initialsOf(b.customer), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = BrandInk)
                        }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(b.customer, fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            Text(
                                (if (walkIn) "Walk-in" else "Booked online") + (bookedAt?.let { " · " + fmt(it, "d MMM, h:mm a") } ?: ""),
                                fontSize = 12.5.sp, color = Color(0xD9FFFFFF), maxLines = 1,
                            )
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                    Row(verticalAlignment = Alignment.Bottom) {
                        Column(Modifier.weight(1f)) {
                            Text("Amount", fontSize = 12.sp, color = Color(0xCCFFFFFF))
                            Text(
                                "₹" + formatInr(b.amount), fontSize = 36.sp, lineHeight = 38.sp, fontWeight = FontWeight.Bold,
                                color = Color.White, letterSpacing = (-1).sp, style = TextStyle(fontFeatureSettings = "tnum"),
                            )
                            Text(
                                when {
                                    cancelled -> "Cancelled"
                                    owed <= 0 && paid > 0 -> "Paid" + (method?.let { " · " + wayName(it) } ?: "")
                                    paid > 0 -> "₹" + formatInr(paid) + " paid · ₹" + formatInr(owed) + " to collect"
                                    else -> "₹" + formatInr(owed) + " to collect"
                                },
                                fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color(0xE6FFFFFF),
                            )
                        }
                        // The stamp, pressed on at an angle.
                        Text(
                            word, fontSize = 14.sp, fontWeight = FontWeight.Black, color = stampColor, letterSpacing = 2.sp,
                            modifier = Modifier
                                .graphicsLayer {
                                    val k = 1.8f - 0.8f * stamp
                                    scaleX = k; scaleY = k
                                    alpha = stamp.coerceIn(0f, 1f)
                                    rotationZ = -12f
                                }
                                .border(2.5.dp, stampColor, RoundedCornerShape(8.dp))
                                .padding(horizontal = 10.dp, vertical = 5.dp),
                        )
                    }
                }
            }
            // ---- the stub
            Column(Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 18.dp, bottom = 18.dp)) {
                Row(Modifier.fillMaxWidth()) {
                    Cell("DATE", b.slotDate?.let { dayOf(it) } ?: "—", Modifier.weight(1f))
                    Cell("TIME", slotTime(b.slotLabel)?.replace(" - ", "–") ?: "—", Modifier.weight(1.3f))
                }
                Spacer(Modifier.height(14.dp))
                Cell("VENUE", b.branch ?: b.label ?: "—", Modifier.fillMaxWidth())
                b.ticketCode?.let { code ->
                    Spacer(Modifier.height(16.dp))
                    Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
                    Spacer(Modifier.height(16.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        val qr = remember(code) { qrBitmap("haraan:ticket:$code", 360) }
                        Box(
                            Modifier.size(92.dp).clip(RoundedCornerShape(12.dp)).background(Color.White)
                                .border(1.dp, Hair, RoundedCornerShape(12.dp)).padding(8.dp),
                        ) {
                            if (qr != null) Image(qr.asImageBitmap(), contentDescription = "Ticket QR", modifier = Modifier.fillMaxWidth())
                        }
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text("TICKET", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 1.2.sp)
                            Spacer(Modifier.height(2.dp))
                            Text(
                                code.chunked(4).joinToString(" "), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                                letterSpacing = 0.6.sp, lineHeight = 18.sp, style = TextStyle(fontFeatureSettings = "tnum"),
                            )
                            Spacer(Modifier.height(8.dp))
                            Text(
                                if (copied) "Copied" else "Copy code",
                                fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = if (copied) Good else BrandInk,
                                modifier = Modifier.clip(RoundedCornerShape(99.dp))
                                    .background(if (copied) Color(0x1A16A34A) else Color(0xFFEAF1FF))
                                    .clickable { onCopy(code) }
                                    .padding(horizontal = 12.dp, vertical = 6.dp),
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * How to reach them: the number and email, with Call, WhatsApp and Email one tap away.
 * When there's nothing to show it says why, rather than leaving a blank.
 */
@Composable
private fun Contact(b: BookingSummary, walkIn: Boolean) {
    val context = LocalContext.current
    val view = LocalView.current
    val digits = b.phone.orEmpty().filter { it.isDigit() }
    val local = if (digits.length > 10) digits.takeLast(10) else digits
    val pretty = if (local.length == 10) "+91 " + local.take(5) + " " + local.drop(5) else b.phone
    fun open(uri: String, action: String = android.content.Intent.ACTION_VIEW) {
        Haptics.tick(view)
        runCatching { context.startActivity(android.content.Intent(action, android.net.Uri.parse(uri))) }
    }
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(18.dp)).padding(16.dp),
    ) {
        Text("CONTACT", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 1.2.sp)
        Spacer(Modifier.height(8.dp))
        if (pretty.isNullOrBlank() && b.email.isNullOrBlank()) {
            Text(
                when {
                    !b.contactSent -> "Phone and email show here after the next server update."
                    walkIn -> "No number was taken at the desk for this walk-in."
                    else -> "This customer hasn't added a number or email."
                },
                fontSize = 13.5.sp, color = Muted, lineHeight = 19.sp,
            )
            return@Column
        }
        if (!pretty.isNullOrBlank()) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Canvas(Modifier.size(18.dp)) {
                    // A handset, drawn: the phone is the line that matters most.
                    val p = Path().apply {
                        moveTo(size.width * 0.22f, size.height * 0.1f); lineTo(size.width * 0.4f, size.height * 0.1f)
                        lineTo(size.width * 0.48f, size.height * 0.32f); lineTo(size.width * 0.36f, size.height * 0.42f)
                        quadraticTo(size.width * 0.48f, size.height * 0.62f, size.width * 0.6f, size.height * 0.66f)
                        lineTo(size.width * 0.7f, size.height * 0.54f); lineTo(size.width * 0.92f, size.height * 0.62f)
                        lineTo(size.width * 0.92f, size.height * 0.8f)
                        quadraticTo(size.width * 0.9f, size.height * 0.92f, size.width * 0.76f, size.height * 0.92f)
                        quadraticTo(size.width * 0.12f, size.height * 0.8f, size.width * 0.1f, size.height * 0.24f)
                        quadraticTo(size.width * 0.1f, size.height * 0.1f, size.width * 0.22f, size.height * 0.1f)
                        close()
                    }
                    drawPath(p, BrandInk)
                }
                Spacer(Modifier.width(10.dp))
                Text(pretty, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"), modifier = Modifier.weight(1f))
            }
            if (local.length == 10) {
                Spacer(Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    ContactButton("Call", Modifier.weight(1f), filled = true) { open("tel:+91$local", android.content.Intent.ACTION_DIAL) }
                    ContactButton("WhatsApp", Modifier.weight(1f)) { open("https://wa.me/91$local") }
                }
            }
        }
        b.email?.takeIf { it.isNotBlank() }?.let { mail ->
            if (!pretty.isNullOrBlank()) {
                Spacer(Modifier.height(12.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
                Spacer(Modifier.height(12.dp))
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("@", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = BrandInk, modifier = Modifier.width(28.dp))
                Text(mail, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                Text(
                    "Email", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BrandInk,
                    modifier = Modifier.clip(RoundedCornerShape(99.dp)).background(Color(0xFFEAF1FF))
                        .clickable { open("mailto:$mail") }.padding(horizontal = 12.dp, vertical = 6.dp),
                )
            }
        }
    }
}

@Composable
private fun ContactButton(text: String, modifier: Modifier, filled: Boolean = false, onClick: () -> Unit) {
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val k by animateFloatAsState(if (pressed) 0.94f else 1f, spring(dampingRatio = 0.6f), label = "contact")
    Box(
        modifier.height(44.dp)
            .graphicsLayer { scaleX = k; scaleY = k }
            .clip(RoundedCornerShape(12.dp))
            .background(if (filled) Brand else Color(0xFFEAF1FF))
            .clickable(interactionSource = press, indication = null, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = if (filled) Color.White else BrandInk) }
}

@Composable
private fun Cell(label: String, value: String, modifier: Modifier) {
    Column(modifier) {
        Text(label, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 1.2.sp)
        Spacer(Modifier.height(2.dp))
        Text(value, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 2, overflow = TextOverflow.Ellipsis)
    }
}

/** A rounded pass with a half-circle notch cut into each side at the tear, and the tear itself. */
private class PassShape(private val tearY: Float) : Shape {
    override fun createOutline(size: Size, layoutDirection: LayoutDirection, density: Density): Outline {
        val r = with(density) { 22.dp.toPx() }
        val notch = with(density) { 11.dp.toPx() }
        val card = Path().apply { addRoundRect(RoundRect(Rect(Offset.Zero, size), CornerRadius(r))) }
        if (tearY <= 0f) return Outline.Generic(card)
        val cuts = Path().apply {
            addOval(Rect(Offset(0f, tearY), notch))
            addOval(Rect(Offset(size.width, tearY), notch))
        }
        return Outline.Generic(Path().apply { op(card, cuts, PathOperation.Difference) })
    }
}

private data class Node(val title: String, val done: Boolean, val alert: Boolean = false)

/**
 * Booked → Paid → Checked in → Played, across: the line fills to where the booking has
 * got to, done steps carry a tick, and a warning step (part paid, no-show, cancelled)
 * turns amber.
 */
@Composable
private fun Track(nodes: List<Node>) {
    val fill = remember { Animatable(0f) }
    val reached = nodes.indexOfLast { it.done }.coerceAtLeast(0)
    LaunchedEffect(reached) { fill.animateTo(reached.toFloat(), tween(650, easing = FastOutSlowInEasing)) }
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(18.dp)).padding(horizontal = 14.dp, vertical = 16.dp),
    ) {
        Canvas(Modifier.fillMaxWidth().height(26.dp)) {
            val n = nodes.size
            val step = (size.width - 24.dp.toPx()) / (n - 1)
            val y = size.height / 2
            val x0 = 12.dp.toPx()
            drawLine(Color(0xFFE2E8F0), Offset(x0, y), Offset(x0 + step * (n - 1), y), 3.dp.toPx(), StrokeCap.Round)
            drawLine(Brand, Offset(x0, y), Offset(x0 + step * fill.value, y), 3.dp.toPx(), StrokeCap.Round)
            nodes.forEachIndexed { i, node ->
                val c = Offset(x0 + step * i, y)
                val on = node.done && fill.value >= i - 0.05f
                val col = if (node.alert) Amber else Brand
                if (on) {
                    drawCircle(col.copy(alpha = 0.18f), 12.dp.toPx(), c)
                    drawCircle(col, 8.dp.toPx(), c)
                    if (node.alert) {
                        drawLine(Color.White, Offset(c.x, c.y - 3.5.dp.toPx()), Offset(c.x, c.y + 0.8.dp.toPx()), 2.dp.toPx(), StrokeCap.Round)
                        drawCircle(Color.White, 1.2.dp.toPx(), Offset(c.x, c.y + 3.6.dp.toPx()))
                    } else {
                        drawLine(Color.White, Offset(c.x - 3.4.dp.toPx(), c.y), Offset(c.x - 0.8.dp.toPx(), c.y + 2.6.dp.toPx()), 2.dp.toPx(), StrokeCap.Round)
                        drawLine(Color.White, Offset(c.x - 0.8.dp.toPx(), c.y + 2.6.dp.toPx()), Offset(c.x + 3.6.dp.toPx(), c.y - 2.8.dp.toPx()), 2.dp.toPx(), StrokeCap.Round)
                    }
                } else {
                    drawCircle(Color.White, 8.dp.toPx(), c)
                    drawCircle(Color(0xFFCBD5E1), 8.dp.toPx(), c, style = Stroke(2.dp.toPx()))
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        // Each label centred under its own dot (clamped to the card's edges), not spread
        // over equal columns — those drift off the dots at the ends.
        androidx.compose.foundation.layout.BoxWithConstraints(Modifier.fillMaxWidth().height(18.dp)) {
            val full = constraints.maxWidth
            val inset = with(androidx.compose.ui.platform.LocalDensity.current) { 12.dp.roundToPx() }
            nodes.forEachIndexed { i, node ->
                val cx = inset + ((full - inset * 2) * i / (nodes.size - 1).coerceAtLeast(1))
                Text(
                    node.title, fontSize = 11.5.sp, maxLines = 1, softWrap = false,
                    fontWeight = if (node.done) FontWeight.SemiBold else FontWeight.Normal,
                    color = when { node.alert && node.done -> Amber; node.done -> Ink; else -> Muted },
                    modifier = Modifier.layout { m, c ->
                        val p = m.measure(c.copy(minWidth = 0))
                        layout(p.width, p.height) {
                            p.place((cx - p.width / 2).coerceIn(0, (full - p.width).coerceAtLeast(0)), 0)
                        }
                    },
                )
            }
        }
    }
}

@Composable
private fun PrimaryButton(text: String, onClick: () -> Unit) {
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val k by animateFloatAsState(if (pressed) 0.96f else 1f, spring(dampingRatio = 0.6f), label = "primary")
    Box(
        Modifier.fillMaxWidth().height(52.dp)
            .graphicsLayer { scaleX = k; scaleY = k }
            .shadow(10.dp, RoundedCornerShape(16.dp), clip = false, spotColor = Color(0x662563EB))
            .clip(RoundedCornerShape(16.dp))
            .background(Brush.verticalGradient(listOf(Color(0xFF3B76F6), Brand)))
            .clickable(interactionSource = press, indication = null, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White) }
}

@Composable
private fun SoftButton(text: String, modifier: Modifier, danger: Boolean = false, enabled: Boolean = true, onClick: () -> Unit) {
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val k by animateFloatAsState(if (pressed) 0.95f else 1f, spring(dampingRatio = 0.6f), label = "soft")
    Box(
        modifier.height(48.dp)
            .graphicsLayer { scaleX = k; scaleY = k; alpha = if (enabled) 1f else 0.5f }
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, if (danger) Color(0x40DC2626) else Color(0x262563EB), RoundedCornerShape(14.dp))
            .clickable(interactionSource = press, indication = null, enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = if (danger) Due else BrandInk) }
}

private fun qrBitmap(content: String, size: Int): Bitmap? = runCatching {
    val hints = mapOf(EncodeHintType.MARGIN to 0, EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M)
    val m = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, size, size, hints)
    val px = IntArray(size * size) { i -> if (m[i % size, i / size]) 0xFF0F172A.toInt() else 0xFFFFFFFF.toInt() }
    Bitmap.createBitmap(px, size, size, Bitmap.Config.ARGB_8888)
}.getOrNull()

private fun wayName(m: String): String = when (m.lowercase()) {
    "cash" -> "Cash"
    "upi", "upi_qr" -> "UPI"
    "card" -> "Card"
    "package" -> "Pass"
    else -> "Online on Haraan"
}

private fun initialsOf(name: String): String {
    val p = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
    return when {
        p.isEmpty() -> "•"
        p.size == 1 -> p[0].take(2).uppercase()
        else -> (p[0].take(1) + p[1].take(1)).uppercase()
    }
}

private fun parseIso(s: String?): java.util.Date? =
    s?.let { runCatching { java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.US).parse(it) }.getOrNull() }

private fun fmt(d: java.util.Date, p: String) = java.text.SimpleDateFormat(p, java.util.Locale.ENGLISH).format(d)

private fun dayOf(ymd: String): String =
    runCatching { java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(ymd.take(10)) }.getOrNull()
        ?.let { fmt(it, "EEE, d MMM") } ?: ymd

private fun slotTime(label: String?): String? = label?.substringAfterLast("·")?.trim()?.takeIf { it.isNotBlank() }

/** Has the slot's start gone by? */
private fun slotPassed(ymd: String?, label: String?): Boolean {
    if (ymd.isNullOrBlank()) return false
    val start = slotTime(label)?.split("–", "-")?.firstOrNull()?.trim() ?: return false
    val at = runCatching { java.text.SimpleDateFormat("yyyy-MM-dd h:mm a", java.util.Locale.ENGLISH).parse(ymd.take(10) + " " + start) }.getOrNull()
        ?: return false
    return at.before(java.util.Date())
}
