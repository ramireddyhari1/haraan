package com.haraan.partner.daybookings.ui

import android.content.Intent
import android.graphics.Bitmap
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Link
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import com.haraan.partner.PayMethod
import com.haraan.partner.daybookings.viewmodel.DeskPayPhase
import com.haraan.partner.daybookings.viewmodel.DeskPayState
import com.haraan.partner.ui.Haptics

private val Blue = Color(0xFF1D4ED8)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Hairline = Color(0xFFE5E7EB)
private val Green = Color(0xFF16A34A)
private val Amber = Color(0xFFD97706)
private val Red = Color(0xFFDC2626)

/**
 * The walk-in paying online, on the desk's screen: a UPI QR for the exact amount (or
 * the payment link as a QR for the phone camera), a countdown, and a live check with
 * Razorpay that turns the sheet green the moment the money lands.
 *
 * When the countdown ends the QR is closed on the server, so a late scan can't take
 * money for a booking nobody is watching. From there the desk makes a fresh QR, takes
 * cash, or cancels.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DeskPaymentSheet(
    state: DeskPayState,
    onRetry: (kind: String) -> Unit,
    onCollect: (PayMethod) -> Unit,
    onCancelBooking: () -> Unit,
    onDismiss: () -> Unit,
    onOpenPage: () -> Unit = {},
) {
    val paid = rememberUpdatedState(state.phase == DeskPayPhase.PAID)
    // Not paid yet, the sheet can't be swiped away: leaving means cancelling, and that's asked.
    val sheetState = rememberModalBottomSheetState(
        skipPartiallyExpanded = true,
        confirmValueChange = { it != androidx.compose.material3.SheetValue.Hidden || paid.value },
    )
    val view = LocalView.current
    var askLeave by remember { mutableStateOf(false) }
    val leave: () -> Unit = { if (state.phase == DeskPayPhase.PAID) onDismiss() else if (!state.busy) askLeave = true }

    if (askLeave) {
        AlertDialog(
            onDismissRequest = { askLeave = false },
            title = { Text("Cancel this booking?", fontWeight = FontWeight.ExtraBold) },
            text = { Text("${state.customer} hasn't paid yet. The booking is cancelled and the court goes back on sale.") },
            confirmButton = {
                TextButton(onClick = { askLeave = false; onCancelBooking() }) {
                    Text("Cancel booking", color = Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { askLeave = false }) { Text("Keep waiting", fontWeight = FontWeight.Bold) }
            },
        )
    }

    LaunchedEffect(state.phase) {
        when (state.phase) {
            DeskPayPhase.PAID -> Haptics.money(view)
            DeskPayPhase.EXPIRED, DeskPayPhase.FAILED -> Haptics.reject(view)
            DeskPayPhase.WAITING -> Unit
        }
    }

    ModalBottomSheet(
        onDismissRequest = leave,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp)
                .padding(bottom = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Header(state, leave)
            Spacer(Modifier.height(14.dp))

            if (state.phase == DeskPayPhase.PAID) {
                PaidBody(state, onDismiss)
            } else {
                Text(
                    "₹" + formatAmount(state.amount),
                    fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
                )
                Spacer(Modifier.height(12.dp))

                if (state.phase != DeskPayPhase.FAILED) {
                    val p = state.payment
                    when {
                        p?.isPage == true -> PagePanel(state, onOpenPage)
                        p != null && !p.isQr && p.qr == null -> LinkPanel(state)
                        else -> QrPanel(state)
                    }
                    Spacer(Modifier.height(14.dp))
                    Countdown(state)
                } else {
                    Text(
                        state.message ?: "Couldn't start an online payment.",
                        fontSize = 13.5.sp, color = Red, textAlign = TextAlign.Center, lineHeight = 19.sp,
                        modifier = Modifier.padding(vertical = 12.dp),
                    )
                }

                if (state.message != null && state.phase != DeskPayPhase.FAILED) {
                    Spacer(Modifier.height(8.dp))
                    Text(state.message, fontSize = 12.5.sp, color = Red, textAlign = TextAlign.Center)
                }

                Spacer(Modifier.height(18.dp))
                Actions(state, onRetry, onCollect, onCancelBooking)
            }
        }
    }
}

@Composable
private fun Header(state: DeskPayState, onDismiss: () -> Unit) {
    val isLink = state.payment?.isQr == false
    val title = when (state.phase) {
        DeskPayPhase.PAID -> "Payment received"
        DeskPayPhase.EXPIRED -> if (isLink) "Link not paid yet" else "QR expired"
        DeskPayPhase.FAILED -> if (isLink) "Link unavailable" else "UPI QR unavailable"
        DeskPayPhase.WAITING -> when { state.payment?.isPage == true -> "Pay on Razorpay"; isLink -> "Payment link"; else -> "Scan to pay" }
    }
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
            Text("${state.customer} · Booking #${state.bookingId}", fontSize = 12.sp, color = Muted)
        }
        IconButton(onClick = onDismiss, enabled = !state.busy) {
            Icon(Icons.Filled.Close, contentDescription = "Close", tint = Muted)
        }
    }
}

@Composable
private fun QrPanel(state: DeskPayState) {
    val payment = state.payment ?: return
    val expired = state.phase == DeskPayPhase.EXPIRED
    val bitmap = remember(payment.qr) { payment.qr?.let { qrBitmap(it, 720) } }

    Box(
        modifier = Modifier
            .size(236.dp)
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .padding(14.dp),
        contentAlignment = Alignment.Center,
    ) {
        val qrModifier = Modifier.fillMaxSize().alpha(if (expired) 0.12f else 1f)
        when {
            bitmap != null -> Image(bitmap.asImageBitmap(), contentDescription = "Payment QR code", modifier = qrModifier)
            payment.imageUrl != null -> AsyncImage(model = payment.imageUrl, contentDescription = "Payment QR code", modifier = qrModifier)
        }
        if (expired) {
            Text(
                if (payment.isQr) "Expired" else "Timer ended",
                fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
            )
        }
    }
    Spacer(Modifier.height(10.dp))
    Text(
        if (payment.isQr) "Any UPI app can scan this. The amount is already filled in."
        else "Scan with the phone camera to open the payment page. Razorpay has also texted the link.",
        fontSize = 12.5.sp, color = Muted, textAlign = TextAlign.Center, lineHeight = 17.sp,
    )
}

/**
 * Razorpay couldn't make a UPI QR, so the customer pays on Razorpay's own page, on the
 * desk's phone. It opens by itself; closing it keeps the sheet (and the countdown), and
 * the button brings it back.
 */
@Composable
private fun PagePanel(state: DeskPayState, onOpenPage: () -> Unit) {
    val payment = state.payment ?: return
    val view = LocalView.current

    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Color(0xFFF6F8FC))
            .border(1.dp, Hairline, RoundedCornerShape(20.dp)).padding(18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(Icons.Filled.Payments, contentDescription = null, tint = Blue, modifier = Modifier.size(30.dp))
        Spacer(Modifier.height(8.dp))
        Text("Customer pays on Razorpay", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text(
            "Razorpay's page opens right here for the customer to pay. It turns green by itself when the money lands.",
            fontSize = 12.5.sp, color = Muted, textAlign = TextAlign.Center, lineHeight = 17.sp,
        )
        if (state.phase == DeskPayPhase.WAITING) {
            Spacer(Modifier.height(12.dp))
            Button(
                onClick = { Haptics.tick(view); onOpenPage() },
                colors = ButtonDefaults.buttonColors(containerColor = Blue),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth().height(46.dp),
            ) { Text("Open payment page", fontSize = 14.sp, fontWeight = FontWeight.Bold) }
        }
    }

}

/** A link the desk chose to send: shown as text with a share button, never as a QR. */
@Composable
private fun LinkPanel(state: DeskPayState) {
    val url = state.payment?.url ?: return
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Color(0xFFF6F8FC))
            .border(1.dp, Hairline, RoundedCornerShape(20.dp)).padding(18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(Icons.Filled.Link, contentDescription = null, tint = Blue, modifier = Modifier.size(28.dp))
        Spacer(Modifier.height(8.dp))
        Text("Link texted to ${state.customer}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text(url, fontSize = 13.sp, color = Blue, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        Text("It confirms by itself when they pay.", fontSize = 12.5.sp, color = Muted)
    }
}

/**
 * Razorpay's payment page for the customer, full screen inside the app, with the
 * amount, the customer and the countdown along the top.
 *
 * The first version of this froze the desk for five seconds and Android closed the app
 * mid-payment. Three things caused it, and each is handled here:
 *  - Starting Android's web engine cold costs seconds on the main thread. The desk
 *    warms it up in the background when it opens ([WarmUpWebEngine]), so this screen
 *    only pays for a page load.
 *  - It forced Razorpay's desktop layout, several times heavier than its phone page.
 *    The phone page is what loads now.
 *  - The page started loading in the same frame the window was created. Now the window
 *    shows first (with a spinner) and the load is posted after it.
 *
 * UPI app buttons on the page (upi://, intent://) are handed to Android, so "Pay with
 * GPay" opens GPay. Back steps back inside the page before it closes it.
 */
@Composable
fun RazorpayPaymentPage(url: String, state: DeskPayState, onClose: () -> Unit) {
    val context = LocalContext.current
    var loading by remember { mutableStateOf(true) }
    var webRef by remember { mutableStateOf<android.webkit.WebView?>(null) }
    val left = state.secondsLeft

    fun back() {
        val web = webRef
        if (web != null && web.canGoBack()) web.goBack() else onClose()
    }

    androidx.compose.ui.window.Dialog(
        onDismissRequest = { back() },
        properties = androidx.compose.ui.window.DialogProperties(
            usePlatformDefaultWidth = false,
            dismissOnClickOutside = false,
        ),
    ) {
        Column(Modifier.fillMaxSize().background(Color.White)) {
            Row(
                Modifier.fillMaxWidth().background(Color.White).padding(start = 4.dp, end = 16.dp, top = 6.dp, bottom = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onClose) { Icon(Icons.Filled.Close, contentDescription = "Close", tint = Ink) }
                Column(Modifier.weight(1f)) {
                    Text("₹" + formatAmount(state.amount) + " · " + state.customer, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1)
                    Text("Secured by Razorpay", fontSize = 11.5.sp, color = Muted)
                }
                Text(
                    "${left / 60}:${(left % 60).toString().padStart(2, '0')}",
                    fontSize = 15.sp, fontWeight = FontWeight.Bold, fontFamily = FontFamily.Monospace,
                    color = if (left <= 60) Amber else Blue,
                )
            }
            LinearProgressIndicator(
                progress = { if (state.totalSeconds > 0) left.toFloat() / state.totalSeconds else 0f },
                color = if (left <= 60) Amber else Blue, trackColor = Color(0xFFEFF2F7),
                modifier = Modifier.fillMaxWidth().height(3.dp),
            )
            Box(Modifier.fillMaxSize()) {
                androidx.compose.ui.viewinterop.AndroidView(
                    modifier = Modifier.fillMaxSize(),
                    factory = { ctx ->
                        if (com.haraan.partner.BuildConfig.DEBUG) android.webkit.WebView.setWebContentsDebuggingEnabled(true)
                        android.webkit.WebView(ctx).apply {
                            // A WebView whose height is WRAP_CONTENT (Compose's default for
                            // AndroidView) reports the viewport height to CSS as 0, so every
                            // `vh`/`dvh` is 0 — Razorpay caps its checkout at `100dvh`, and its
                            // payment window rendered 0px tall behind the dimmed page.
                            layoutParams = android.view.ViewGroup.LayoutParams(
                                android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                                android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                            )
                            settings.javaScriptEnabled = true
                            settings.domStorageEnabled = true
                            // Razorpay's checkout runs in a frame from api.razorpay.com and
                            // opens its own windows; a WebView blocks both by default, which
                            // left "Proceed to Pay" dimmed forever.
                            settings.javaScriptCanOpenWindowsAutomatically = true
                            settings.setSupportMultipleWindows(false)
                            val web = this
                            android.webkit.CookieManager.getInstance().apply {
                                setAcceptCookie(true)
                                setAcceptThirdPartyCookies(web, true)
                            }
                            // Alerts, window.open and progress only work with a chrome client.
                            webChromeClient = android.webkit.WebChromeClient()
                            webViewClient = object : android.webkit.WebViewClient() {
                                override fun shouldOverrideUrlLoading(v: android.webkit.WebView, req: android.webkit.WebResourceRequest): Boolean {
                                    val u = req.url
                                    if (u.scheme == "http" || u.scheme == "https") return false
                                    // A UPI app button: let Android open that app.
                                    runCatching {
                                        val intent = if (u.scheme == "intent") Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME)
                                        else Intent(Intent.ACTION_VIEW, u)
                                        context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                                    }
                                    return true
                                }

                                override fun onPageFinished(v: android.webkit.WebView, u: String) { loading = false }
                            }
                            // Let the window draw first; the load starts on the next frame.
                            post { loadUrl(url) }
                            webRef = this
                        }
                    },
                    onRelease = { web ->
                        web.stopLoading()
                        web.destroy()
                        webRef = null
                    },
                )
                if (loading) {
                    Column(Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator(color = Blue, strokeWidth = 2.5.dp, modifier = Modifier.size(28.dp))
                        Spacer(Modifier.height(12.dp))
                        Text("Opening Razorpay…", fontSize = 13.sp, color = Muted)
                    }
                }
            }
        }
    }
}

/**
 * Starts Android's web engine once, in the background, while the desk is idle — so the
 * first Razorpay page opens in a moment instead of freezing the desk for seconds.
 * Cheap after the first run: the engine stays loaded for the life of the app.
 */
@Composable
fun WarmUpWebEngine() {
    val context = LocalContext.current
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(1_500)
        android.os.Looper.myQueue().addIdleHandler {
            runCatching { android.webkit.WebView(context.applicationContext).destroy() }
            false
        }
    }
}

@Composable
private fun Countdown(state: DeskPayState) {
    val left = state.secondsLeft
    val fraction = if (state.totalSeconds > 0) left.toFloat() / state.totalSeconds else 0f
    val tone by animateColorAsState(
        when {
            state.phase == DeskPayPhase.EXPIRED -> Muted
            left <= 15 -> Red
            left <= 60 -> Amber
            else -> Blue
        },
        label = "countdown",
    )
    val pulse = rememberInfiniteTransition(label = "pulse")
    val dot by pulse.animateFloat(
        initialValue = 0.35f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(700), RepeatMode.Reverse), label = "dot",
    )

    LinearProgressIndicator(
        progress = { fraction.coerceIn(0f, 1f) },
        color = tone,
        trackColor = Color(0xFFEFF2F7),
        modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp)),
    )
    Spacer(Modifier.height(10.dp))
    Row(verticalAlignment = Alignment.CenterVertically) {
        if (state.phase == DeskPayPhase.WAITING) {
            Box(
                Modifier.size(8.dp).clip(CircleShape)
                    .background(Green.copy(alpha = if (state.checking) 1f else dot))
            )
            Spacer(Modifier.width(8.dp))
            Text("Waiting for payment", fontSize = 13.sp, color = Muted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.width(10.dp))
        }
        Text(
            if (state.phase == DeskPayPhase.EXPIRED) "0:00" else "${left / 60}:${(left % 60).toString().padStart(2, '0')}",
            fontSize = 15.sp, fontWeight = FontWeight.Bold, color = tone, fontFamily = FontFamily.Monospace,
        )
    }
}

@Composable
private fun Actions(
    state: DeskPayState,
    onRetry: (kind: String) -> Unit,
    onCollect: (PayMethod) -> Unit,
    onCancelBooking: () -> Unit,
) {
    val context = LocalContext.current
    val payment = state.payment
    // Recording cash is money on the books: one tap arms it, the second records it.
    var confirmCash by remember(state.phase) { mutableStateOf(false) }
    var confirmCancel by remember(state.phase) { mutableStateOf(false) }

    if (state.phase != DeskPayPhase.WAITING) {
        Button(
            onClick = { onRetry(if (payment == null || payment.isPage) "upi_qr" else payment.kind) },
            enabled = !state.busy,
            colors = ButtonDefaults.buttonColors(containerColor = Blue),
            shape = RoundedCornerShape(12.dp),
            modifier = Modifier.fillMaxWidth().height(48.dp),
        ) {
            if (state.busy) {
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
            } else {
                Icon(Icons.Filled.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(
                    when { payment?.isPage == true -> "Start again"; payment?.isQr == false -> "Send a new link"; else -> "Show a new QR" },
                    fontSize = 14.5.sp, fontWeight = FontWeight.Bold,
                )
            }
        }
        Spacer(Modifier.height(10.dp))
        // No QR could be made: a link is the desk's call to make, never a silent swap.
        if (state.phase == DeskPayPhase.FAILED && payment?.isQr != false) {
            OutlinedButton(
                onClick = { onRetry("link") },
                enabled = !state.busy,
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.5.dp, Hairline),
                modifier = Modifier.fillMaxWidth().height(46.dp),
            ) {
                Text("Send a payment link instead", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = Blue)
            }
            Spacer(Modifier.height(10.dp))
        }
    }

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        OutlinedButton(
            onClick = { if (confirmCash) onCollect(PayMethod.CASH) else confirmCash = true },
            enabled = !state.busy,
            shape = RoundedCornerShape(12.dp),
            border = androidx.compose.foundation.BorderStroke(1.5.dp, if (confirmCash) Blue else Hairline),
            modifier = Modifier.weight(1f).height(46.dp),
        ) {
            Text(
                if (confirmCash) "Confirm ₹${formatAmount(state.amount)} cash" else "Paid in cash",
                fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = Blue, maxLines = 1,
            )
        }
        if (payment?.url != null && payment.present == "share") {
            OutlinedButton(
                onClick = {
                    val send = Intent(Intent.ACTION_SEND).apply {
                        type = "text/plain"
                        putExtra(Intent.EXTRA_TEXT, "Pay ₹${formatAmount(state.amount)} for your booking: ${payment.url}")
                    }
                    context.startActivity(Intent.createChooser(send, "Share payment link"))
                },
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.5.dp, Hairline),
                modifier = Modifier.weight(1f).height(46.dp),
            ) {
                Icon(Icons.Filled.Share, contentDescription = null, tint = Blue, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text("Share link", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = Blue)
            }
        }
    }

    Spacer(Modifier.height(4.dp))
    TextButton(
        onClick = { if (confirmCancel) onCancelBooking() else confirmCancel = true },
        enabled = !state.busy,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Text(
            if (confirmCancel) "Tap again to cancel the booking" else "Customer left — cancel booking",
            color = if (confirmCancel) Red else Muted, fontWeight = FontWeight.SemiBold, fontSize = 13.sp,
        )
    }
}

@Composable
private fun PaidBody(state: DeskPayState, onDone: () -> Unit) {
    Box(
        Modifier.size(72.dp).clip(CircleShape).background(Green.copy(alpha = 0.12f)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Filled.Check, contentDescription = null, tint = Green, modifier = Modifier.size(38.dp))
    }
    Spacer(Modifier.height(14.dp))
    Text("₹" + formatAmount(state.amount), fontSize = 30.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
    Spacer(Modifier.height(4.dp))
    Text(
        when (state.paidVia) {
            "cash" -> "Taken in cash. The booking is confirmed."
            "upi", "card" -> "Recorded at the desk. The booking is confirmed."
            else -> "Paid online. The booking is confirmed and the ticket is on its way to ${state.customer}."
        },
        fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center, lineHeight = 18.sp,
    )
    Spacer(Modifier.height(20.dp))
    Button(
        onClick = onDone,
        colors = ButtonDefaults.buttonColors(containerColor = Blue),
        shape = RoundedCornerShape(12.dp),
        modifier = Modifier.fillMaxWidth().height(48.dp),
    ) { Text("Done", fontSize = 14.5.sp, fontWeight = FontWeight.Bold) }
}

private fun formatAmount(amount: Double): String =
    if (amount % 1.0 == 0.0) amount.toLong().toString() else String.format(java.util.Locale.US, "%.2f", amount)

/** Draw the QR on the device: crisp at any size, and no image to download. */
private fun qrBitmap(content: String, size: Int): Bitmap? = runCatching {
    val hints = mapOf(EncodeHintType.MARGIN to 0, EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M)
    val matrix = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, size, size, hints)
    val pixels = IntArray(size * size) { i -> if (matrix[i % size, i / size]) 0xFF0B1220.toInt() else 0xFFFFFFFF.toInt() }
    Bitmap.createBitmap(pixels, size, size, Bitmap.Config.ARGB_8888)
}.getOrNull()
