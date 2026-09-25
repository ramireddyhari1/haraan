package com.haraan.partner

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.FlashlightOff
import androidx.compose.material.icons.filled.FlashlightOn
import androidx.compose.material.icons.filled.Keyboard
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.PriorityHigh
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.toMutableStateList
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathFillType
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.drawIntoCanvas
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.asAndroidPath
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.geometry.Rect
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.statusBars
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.google.zxing.BarcodeFormat
import com.google.zxing.ResultPoint
import com.journeyapps.barcodescanner.BarcodeCallback
import com.journeyapps.barcodescanner.BarcodeResult
import com.journeyapps.barcodescanner.BarcodeView
import com.journeyapps.barcodescanner.DefaultDecoderFactory
import com.journeyapps.barcodescanner.Size
import com.haraan.partner.ui.Haptics
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Ticket check-in.
 *
 * The camera IS this screen. What was here before was a poster about scanning —
 * an icon in a tinted square, a paragraph, and a button that opened somebody
 * else's capture activity. At a gate with a queue behind you, the tap to start
 * the camera is the whole problem, so the preview now runs the moment the tab is
 * selected and everything else floats over it.
 *
 * Everything on top of the preview earns its place: the frame says where to aim,
 * the torch is one tap because gates are dark, the outcome banner is large enough
 * to read at arm's length, and the last few check-ins stay on screen so the person
 * scanning can see their own progress without leaving the camera.
 */
@Composable
internal fun ScanScreen(
    api: PartnerApi,
    token: String,
    accent: Color,
    /** Room the floating bottom bar takes over the camera; the controls sit above it. */
    bottomInset: Dp = 0.dp,
) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()

    var granted by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED
        )
    }
    var refused by remember { mutableStateOf(false) }
    val askCamera = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { ok ->
        granted = ok
        refused = !ok
    }
    LaunchedEffect(Unit) { if (!granted) askCamera.launch(Manifest.permission.CAMERA) }

    var busy by remember { mutableStateOf(false) }
    var outcome by remember { mutableStateOf<Outcome?>(null) }
    var torchOn by remember { mutableStateOf(false) }
    var typing by remember { mutableStateOf(false) }
    // One QR burst per capture: the counter restarts it, the code seeds its pattern.
    var burstKey by remember { mutableStateOf(0) }
    var burstCode by remember { mutableStateOf("") }
    val recent = remember { mutableListOf<Outcome>().toMutableStateList() }

    val barcode = remember {
        BarcodeView(context).apply {
            decoderFactory = DefaultDecoderFactory(listOf(BarcodeFormat.QR_CODE))
        }
    }

    // The pending "hand the camera back" timer, so a tap on the result can skip it.
    val hold = remember { arrayOfNulls<Job>(1) }

    fun release() {
        hold[0]?.cancel()
        hold[0] = null
        outcome = null
        if (granted) barcode.resume()
    }

    fun submit(raw: String) {
        if (busy) return
        val code = ticketCodeOf(raw)
        if (code.isBlank()) return
        busy = true
        // The "got it": a crisp tick the instant the code is read, with the frame
        // locking on (see ScanFrame). The verdict's own buzz follows once the
        // server answers, so the operator feels capture and result as two beats.
        Haptics.tick(view)
        burstCode = code
        burstKey++
        // Stop decoding while the round trip runs, so one ticket can't fire three
        // times before the first answer lands.
        barcode.pause()
        val capturedAt = System.currentTimeMillis()
        scope.launch {
            val result = runCatching { api.checkIn(token, code) }
            // Let the lock-on play before the verdict covers it. A fast server
            // answers in ~200ms, which hid the capture entirely; the brackets need
            // about this long to snap in and the shutter to flash.
            val shown = System.currentTimeMillis() - capturedAt
            if (shown < CAPTURE_BEAT_MS) delay(CAPTURE_BEAT_MS - shown)
            val done = result.fold(
                onSuccess = { Outcome.of(code, it) },
                onFailure = { Outcome(code, it.message ?: "Check-in failed", Tone.FAIL) },
            )
            // Each outcome has its own feel, so the person at the gate knows how it
            // went while they are still looking at the guest.
            when (done.tone) {
                Tone.OK -> Haptics.confirm(view)
                Tone.WARN -> Haptics.warn(view)
                Tone.FAIL -> Haptics.reject(view)
            }
            outcome = done
            recent.add(0, done)
            while (recent.size > 3) recent.removeAt(recent.lastIndex)
            busy = false

            // Hold the answer long enough to read, then hand the camera back. The
            // person scanning never has to press anything between tickets, but a
            // tap on the result skips the wait when the queue is moving.
            hold[0] = scope.launch {
                delay(done.tone.holdMillis)
                hold[0] = null
                outcome = null
                if (granted) barcode.resume()
            }
        }
    }

    if (!granted) {
        CameraGate(refused = refused, accent = accent) { askCamera.launch(Manifest.permission.CAMERA) }
        return
    }

    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        barcode.decodeContinuous(object : BarcodeCallback {
            override fun barcodeResult(result: BarcodeResult) {
                result.text?.let { submit(it) }
            }

            override fun possibleResultPoints(resultPoints: MutableList<ResultPoint>?) = Unit
        })
        barcode.resume()

        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_RESUME -> if (outcome == null && !busy) barcode.resume()
                Lifecycle.Event.ON_PAUSE -> barcode.pause()
                else -> Unit
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
            barcode.pause()
        }
    }

    // The camera is the whole screen now, so the status bar sits on a dark
    // picture: its icons go light while Scan is open, and back when it closes.
    DisposableEffect(view) {
        val window = (view.context as? android.app.Activity)?.window
        val controller = window?.let { androidx.core.view.WindowCompat.getInsetsController(it, view) }
        val wasLight = controller?.isAppearanceLightStatusBars ?: true
        controller?.isAppearanceLightStatusBars = false
        onDispose { controller?.isAppearanceLightStatusBars = wasLight }
    }

    BoxWithConstraints(Modifier.fillMaxSize().background(Color.Black)) {
        val density = LocalDensity.current
        val topPad = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
        // The viewport: one large rounded window over the camera, from under the
        // status bar to just above the floating nav. No square to aim into — a
        // code anywhere in the window reads — so the decoder is cropped to most
        // of the window rather than to a box drawn in the middle.
        val viewportW = maxWidth - 20.dp
        val viewportH = maxHeight - topPad - bottomInset - 12.dp
        LaunchedEffect(viewportW, viewportH) {
            with(density) {
                barcode.framingRectSize = Size(
                    (viewportW * 0.9f).roundToPx(),
                    (viewportH * 0.72f).roundToPx(),
                )
            }
        }
        // Scale of the QR burst on capture.
        val side: Dp = minOf(maxWidth * 0.74f, maxHeight * 0.42f)

        AndroidView(
            factory = { barcode },
            modifier = Modifier.fillMaxSize(),
        )

        // Scrims: top for the title, bottom for the controls. Without them the
        // white type disappears against a bright wall.
        Box(
            Modifier.fillMaxWidth().height(150.dp).align(Alignment.TopCenter)
                .background(Brush.verticalGradient(listOf(Color(0xCC000000), Color.Transparent)))
        )
        Box(
            Modifier.fillMaxWidth().height(300.dp).align(Alignment.BottomCenter)
                .background(Brush.verticalGradient(listOf(Color.Transparent, Color(0xE6000000))))
        )

        val locked = busy || outcome != null
        ScanViewport(
            topPad = topPad,
            bottomPad = bottomInset,
            locked = locked,
            accent = accent,
            modifier = Modifier.fillMaxSize(),
        )

        Column(
            Modifier.align(Alignment.TopStart).fillMaxWidth()
                .padding(top = topPad)
                .padding(start = 26.dp, end = 26.dp, top = 22.dp),
        ) {
            Text(
                "Scan a ticket",
                color = Color.White,
                fontSize = 28.sp,
                lineHeight = 32.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = (-0.6).sp,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                "Point the camera at the ticket QR — no need to tap",
                color = Color(0xB3FFFFFF),
                fontSize = 13.sp,
            )
        }

        AnimatedVisibility(
            visible = busy,
            enter = fadeIn() + slideInVertically { -it / 2 },
            exit = fadeOut() + slideOutVertically { -it / 2 },
            modifier = Modifier.align(Alignment.TopCenter).padding(top = topPad + 104.dp, start = 18.dp, end = 18.dp),
        ) {
            CheckingBanner()
        }

        Column(
            Modifier.align(Alignment.BottomCenter).fillMaxWidth()
                .padding(bottom = bottomInset)
                .padding(18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            recent.forEach { entry -> RecentRow(entry) }
            if (recent.isNotEmpty()) Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                GlassButton(
                    icon = if (torchOn) Icons.Filled.FlashlightOn else Icons.Filled.FlashlightOff,
                    label = if (torchOn) "Torch on" else "Torch",
                    active = torchOn,
                    accent = accent,
                ) {
                    torchOn = !torchOn
                    barcode.setTorch(torchOn)
                }
                GlassButton(icon = Icons.Filled.Keyboard, label = "Enter code", active = false, accent = accent) {
                    typing = true
                }
            }
        }

        if (typing) {
            ManualEntry(
                accent = accent,
                busy = busy,
                onDismiss = { typing = false },
                onSubmit = { entered ->
                    typing = false
                    submit(entered)
                },
            )
        }

        // Last, so the answer covers everything: at arm's length, in sunlight, the
        // colour of the whole screen is what gets read, not a line of text.
        AnimatedVisibility(
            visible = outcome != null,
            enter = fadeIn(tween(90)),
            exit = fadeOut(tween(220)),
            modifier = Modifier.fillMaxSize(),
        ) {
            // recent[0] is the same outcome, and it survives the exit fade after
            // `outcome` has already gone back to null.
            recent.firstOrNull()?.let { OutcomeFlash(it, bottomInset = bottomInset, onDismiss = { release() }) }
        }

        // Over everything, the verdict included: the squares fly across the
        // result as it arrives, which is what makes the two feel like one motion.
        if (burstKey > 0) {
            QrBurst(key = burstKey, seed = burstCode, side = side, accent = accent, modifier = Modifier.fillMaxSize())
        }
    }
}

/** How long the capture lock-on shows before the verdict replaces it. */
private const val CAPTURE_BEAT_MS = 420L

/** Modules per side of the drawn QR — version 1's 21, so it reads as a real code. */
private const val QR_MODULES = 21

/**
 * The capture, drawn as the code itself coming apart.
 *
 * On a read, a QR pattern materialises inside the frame, module by module, over
 * about a quarter of a second — the picture "becomes" the code. Then every
 * module bursts outward from the centre: flying further the further out it
 * started, growing, glowing, tumbling slightly, and fading, over the verdict
 * screen as it arrives. The pattern is seeded from the ticket code, so every
 * ticket breaks apart differently, and the three finder squares are always
 * there so it is unmistakably a QR.
 *
 * Nothing here is interactive and it never blocks a tap; it simply ends.
 */
@Composable
private fun QrBurst(key: Int, seed: String, side: Dp, accent: Color, modifier: Modifier = Modifier) {
    val modules = remember(key) { qrModules(seed) }
    val t = remember(key) { Animatable(0f) }
    LaunchedEffect(key) { t.animateTo(1f, tween(1_050, easing = LinearEasing)) }
    if (t.value >= 1f) return

    Canvas(modifier) {
        val frame = side.toPx() * 0.78f
        val cell = frame / QR_MODULES
        val cx = size.width / 2f
        val cy = size.height / 2f
        val left = cx - frame / 2f
        val top = cy - frame / 2f
        val reach = maxOf(size.width, size.height) * 0.75f
        val p = t.value

        for (m in modules) {
            // Phase 1 (0 → 0.24): the module appears in place, staggered.
            val appear = ((p - m.delay * 0.16f) / 0.08f).coerceIn(0f, 1f)
            if (appear <= 0f) continue
            // Phase 2 (0.24 → 1): it flies. Eased out so the burst is sharp then drifts.
            val fly = ((p - 0.24f) / 0.76f).coerceIn(0f, 1f)
            val eased = 1f - (1f - fly) * (1f - fly) * (1f - fly)

            val homeX = left + (m.col + 0.5f) * cell
            val homeY = top + (m.row + 0.5f) * cell
            val dx = homeX - cx
            val dy = homeY - cy
            val dist = kotlin.math.sqrt(dx * dx + dy * dy).coerceAtLeast(1f)
            val travel = reach * (0.35f + 0.65f * (dist / (frame * 0.7f)).coerceAtMost(1f)) * m.speed
            val x = homeX + dx / dist * travel * eased + m.drift * cell * 3f * eased
            val y = homeY + dy / dist * travel * eased - cell * 2f * eased

            val scale = appear * (1f + 2.4f * m.grow * eased)
            val half = cell * 0.46f * scale
            val alpha = appear * (1f - ((fly - 0.35f) / 0.65f).coerceIn(0f, 1f))
            if (alpha <= 0.01f) continue

            rotate(degrees = m.spin * 40f * eased, pivot = Offset(x, y)) {
                // Glow: a larger, faint square in the brand colour behind the white one.
                if (fly > 0f) {
                    val g = half * 1.9f
                    drawRoundRect(
                        color = accent.copy(alpha = 0.22f * alpha * fly.coerceAtMost(0.6f) / 0.6f),
                        topLeft = Offset(x - g, y - g),
                        size = androidx.compose.ui.geometry.Size(g * 2, g * 2),
                        cornerRadius = androidx.compose.ui.geometry.CornerRadius(g * 0.3f),
                    )
                }
                drawRoundRect(
                    color = Color.White.copy(alpha = alpha),
                    topLeft = Offset(x - half, y - half),
                    size = androidx.compose.ui.geometry.Size(half * 2, half * 2),
                    cornerRadius = androidx.compose.ui.geometry.CornerRadius(half * 0.22f),
                )
            }
        }
    }
}

/** One dark module of the drawn QR, with its own small differences in flight. */
private class QrModule(
    val row: Int,
    val col: Int,
    val delay: Float,
    val speed: Float,
    val grow: Float,
    val spin: Float,
    val drift: Float,
)

/**
 * A believable QR: the three finder squares, timing rows, and a data area
 * filled from the ticket code, so the same ticket always breaks the same way.
 */
private fun qrModules(seed: String): List<QrModule> {
    val rnd = java.util.Random(seed.hashCode().toLong() * 31 + 7)
    val n = QR_MODULES
    fun finder(r: Int, c: Int, r0: Int, c0: Int): Boolean? {
        val y = r - r0
        val x = c - c0
        if (y !in -1..7 || x !in -1..7) return null
        if (y == -1 || y == 7 || x == -1 || x == 7) return false // quiet separator
        val ring = minOf(y, x, 6 - y, 6 - x)
        return ring == 0 || ring >= 2
    }
    val out = ArrayList<QrModule>()
    for (r in 0 until n) for (c in 0 until n) {
        val dark = finder(r, c, 0, 0) ?: finder(r, c, 0, n - 7) ?: finder(r, c, n - 7, 0)
            ?: if (r == 6 || c == 6) (r + c) % 2 == 0 else rnd.nextFloat() < 0.47f
        if (!dark) continue
        out += QrModule(
            row = r,
            col = c,
            delay = rnd.nextFloat(),
            speed = 0.75f + rnd.nextFloat() * 0.6f,
            grow = 0.4f + rnd.nextFloat() * 0.9f,
            spin = rnd.nextFloat() * 2f - 1f,
            drift = rnd.nextFloat() * 2f - 1f,
        )
    }
    return out
}

/** What happened to one ticket. */
internal data class Outcome(
    val code: String,
    val message: String,
    val tone: Tone,
    val guest: String? = null,
    val quantity: Int = 0,
    val slotLabel: String? = null,
) {
    companion object {
        fun of(code: String, r: CheckInResult): Outcome = Outcome(
            code = code,
            message = r.message,
            tone = when (r.status) {
                "ok" -> Tone.OK
                "already" -> Tone.WARN
                else -> Tone.FAIL
            },
            guest = r.guest,
            quantity = r.quantity,
            slotLabel = r.slotLabel,
        )
    }
}

internal enum class Tone(val holdMillis: Long) {
    OK(1_800),
    // A problem needs reading, and probably a word with the guest.
    WARN(2_800),
    FAIL(2_800),
}

private val OkGreen = Color(0xFF16A34A)
private val WarnAmber = Color(0xFFF59E0B)
private val FailRed = Color(0xFFDC2626)

private fun Tone.color(): Color = when (this) {
    Tone.OK -> OkGreen
    Tone.WARN -> WarnAmber
    Tone.FAIL -> FailRed
}

/**
 * The QR carries `haraan:ticket:<code>`; a person typing carries just the code.
 *
 * The app used to hand the whole payload to an endpoint that matches `ticket_code`
 * exactly, so every genuine scan came back "Ticket not found" — the same rule the
 * web check-in page has always applied is applied here now, on both sides.
 */
internal fun ticketCodeOf(raw: String): String {
    val trimmed = raw.trim()
    val match = Regex("""ticket[:/]([A-Za-z0-9]{6,})""", RegexOption.IGNORE_CASE).find(trimmed)
    return match?.groupValues?.get(1) ?: trimmed
}

/**
 * The scanner's window: one rounded viewport over the camera, with a light that
 * runs around its rim.
 *
 * There is no square to aim into any more. A box in the middle told the operator
 * to line the code up; a lit window that reads anywhere inside it only asks them
 * to point the phone. The comet of light circling the rim — bright cyan head,
 * blue tail, a soft bloom around it — is the "camera is live and looking"
 * signal the sweep line used to carry, without drawing across the picture.
 *
 * Outside the window the screen is black, so the camera reads as a lit pane
 * inset in the phone. On a capture the whole rim flashes brand blue at once,
 * the moment before the QR burst.
 */
@Composable
private fun ScanViewport(
    topPad: Dp,
    bottomPad: Dp,
    locked: Boolean,
    accent: Color,
    modifier: Modifier = Modifier,
) {
    val transition = rememberInfiniteTransition(label = "rim-light")
    val phase by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(3_400, easing = LinearEasing), RepeatMode.Restart),
        label = "rim-phase",
    )
    val rimFlash = remember { Animatable(0f) }
    LaunchedEffect(locked) {
        if (locked) {
            rimFlash.snapTo(1f)
            rimFlash.animateTo(0f, tween(700, easing = LinearEasing))
        }
    }
    val comet = remember { Path() }
    val slice = remember { Path() }
    val measure = remember { PathMeasure() }
    val glowPaint = remember {
        android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
            style = android.graphics.Paint.Style.STROKE
            strokeCap = android.graphics.Paint.Cap.ROUND
        }
    }

    Canvas(modifier) {
        val inset = 10.dp.toPx()
        val r = 30.dp.toPx()
        val left = inset
        val top = topPad.toPx() + 2.dp.toPx()
        val right = size.width - inset
        val bottom = size.height - bottomPad.toPx() - 10.dp.toPx()

        // The rim as one clockwise path from the top edge's midpoint, so the light
        // runs right, down, along the bottom and back up the left.
        val rim = Path().apply {
            val cx = (left + right) / 2f
            moveTo(cx, top)
            lineTo(right - r, top)
            arcTo(Rect(right - 2 * r, top, right, top + 2 * r), -90f, 90f, false)
            lineTo(right, bottom - r)
            arcTo(Rect(right - 2 * r, bottom - 2 * r, right, bottom), 0f, 90f, false)
            lineTo(left + r, bottom)
            arcTo(Rect(left, bottom - 2 * r, left + 2 * r, bottom), 90f, 90f, false)
            lineTo(left, top + r)
            arcTo(Rect(left, top, left + 2 * r, top + 2 * r), 180f, 90f, false)
            close()
        }

        // Black outside the window, so the camera reads as an inset pane.
        val outside = Path().apply {
            fillType = PathFillType.EvenOdd
            addRect(Rect(0f, 0f, size.width, size.height))
            addPath(rim)
        }
        drawPath(outside, Color.Black.copy(alpha = 0.9f))
        drawPath(rim, Color.White.copy(alpha = 0.10f), style = Stroke(1.dp.toPx()))

        measure.setPath(rim, false)
        val length = measure.length
        if (length <= 0f) return@Canvas
        val cometLen = length * 0.2f
        val head = phase * length
        val cyan = Color(0xFF5FD3FF)

        fun segment(dst: Path, from: Float, to: Float) {
            dst.reset()
            val a = ((from % length) + length) % length
            val b = ((to % length) + length) % length
            if (a <= b) {
                measure.getSegment(a, b, dst, true)
            } else {
                // The comet straddles the start of the path: two pieces.
                measure.getSegment(a, length, dst, true)
                measure.getSegment(0f, b, dst, true)
            }
        }

        // Bloom: the leading part of the comet, blurred wide, drawn first.
        segment(comet, head - cometLen * 0.65f, head)
        drawIntoCanvas { canvas ->
            glowPaint.color = cyan.copy(alpha = 0.55f).toArgb()
            glowPaint.strokeWidth = 12.dp.toPx()
            glowPaint.maskFilter = android.graphics.BlurMaskFilter(18.dp.toPx(), android.graphics.BlurMaskFilter.Blur.NORMAL)
            canvas.nativeCanvas.drawPath(comet.asAndroidPath(), glowPaint)
        }

        // The dot field: a halftone of small lit dots fanning inward from the rim
        // around the comet, as if the light were leaking through a fine mesh.
        // Columns follow the rim; each column is a short row of dots stepping
        // inward along the rim's normal, alternate columns offset half a step so
        // they read as a halftone rather than a grid. Dots are largest and
        // brightest at the edge near the head, reach furthest in there too, and
        // fade and shrink toward the tail and toward the centre. A slow twinkle
        // rides on each dot so the field shimmers as the light passes.
        val step = 7.dp.toPx()
        val columns = (cometLen / step).toInt().coerceAtLeast(1)
        for (k in 0..columns) {
            val along = k / columns.toFloat()               // 0 = tail, 1 = head
            // Brightest a little behind the head, where the bloom sits.
            val strength = (along * along * (3f - 2f * along)) * (1f - 0.35f * (along - 0.85f).coerceAtLeast(0f) / 0.15f)
            if (strength < 0.04f) continue
            val dist = (((head - cometLen + cometLen * along) % length) + length) % length
            val pos = measure.getPosition(dist)
            val tan = measure.getTangent(dist)
            // Clockwise rim in screen space: inward is the tangent turned +90°.
            val nx = -tan.y
            val ny = tan.x
            val rows = (3 + 6 * strength).toInt()
            val shift = if (k % 2 == 0) 0f else step * 0.5f
            for (j in 0 until rows) {
                val depth = (j + 0.6f) * step
                val fall = 1f - j / rows.toFloat()
                val twinkle = 0.75f + 0.25f * kotlin.math.sin(phase * 40f + k * 1.7f + j * 2.3f)
                val alpha = (strength * fall * fall * twinkle * 0.9f).coerceIn(0f, 1f)
                if (alpha < 0.03f) continue
                val radius = (0.55f + 1.25f * strength * fall) * density
                drawCircle(
                    color = lerp(accent, cyan, 0.35f + 0.65f * fall).copy(alpha = alpha),
                    radius = radius,
                    center = Offset(pos.x + nx * depth + tan.x * shift, pos.y + ny * depth + tan.y * shift),
                )
            }
        }

        // The line itself: faint blue tail brightening to a cyan-white head.
        val slices = 24
        for (k in 0 until slices) {
            val t0 = k / slices.toFloat()
            val t1 = (k + 1) / slices.toFloat()
            segment(slice, head - cometLen + cometLen * t0, head - cometLen + cometLen * t1 + 0.5f)
            val strength = t1 * t1
            drawPath(
                slice,
                color = lerp(accent, cyan, t1).copy(alpha = 0.15f + 0.85f * strength),
                style = Stroke(width = (1.5f + 2f * strength) * density, cap = StrokeCap.Butt),
            )
        }

        // Capture: the whole rim lights at once, then fades.
        if (rimFlash.value > 0f) {
            drawIntoCanvas { canvas ->
                glowPaint.color = accent.copy(alpha = 0.7f * rimFlash.value).toArgb()
                glowPaint.strokeWidth = 10.dp.toPx()
                glowPaint.maskFilter = android.graphics.BlurMaskFilter(16.dp.toPx(), android.graphics.BlurMaskFilter.Blur.NORMAL)
                canvas.nativeCanvas.drawPath(rim.asAndroidPath(), glowPaint)
            }
            drawPath(rim, lerp(accent, cyan, 0.5f).copy(alpha = rimFlash.value), style = Stroke(3.dp.toPx()))
        }
    }
}

/** The round trip is running. The answer itself is [OutcomeFlash]. */
@Composable
private fun CheckingBanner() {
    Row(
        Modifier.fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color(0xF2101828))
            .border(1.dp, Color(0x40FFFFFF), RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(12.dp))
        Text("Checking ticket…", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * The answer, across the whole screen.
 *
 * It used to be a dark banner near the top with a small coloured dot. At a gate
 * the phone is held at arm's length, often in sunlight, while the operator is
 * talking to the guest, so the answer is now the colour of the entire screen:
 * green, amber or red is readable from the corner of an eye. Then, in order of
 * what the operator needs: the verdict, who it is, how many to let through, and
 * the code for when there's an argument. The strip at the bottom drains over the
 * time until the camera comes back; tapping anywhere skips it.
 */
@Composable
private fun OutcomeFlash(outcome: Outcome, bottomInset: Dp = 0.dp, onDismiss: () -> Unit) {
    val tint = outcome.tone.color()
    // A bright hit on arrival, settling to a colour the text can sit on.
    val wash = remember(outcome) { Animatable(0f) }
    val badge = remember(outcome) { Animatable(0.55f) }
    val countdown = remember(outcome) { Animatable(1f) }
    LaunchedEffect(outcome) {
        launch {
            wash.animateTo(1f, tween(90))
            wash.animateTo(0.9f, tween(260))
        }
        launch { badge.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMediumLow)) }
        launch { countdown.animateTo(0f, tween(outcome.tone.holdMillis.toInt(), easing = LinearEasing)) }
    }

    Box(
        Modifier.fillMaxSize()
            .background(Color.Black)
            .background(tint.copy(alpha = wash.value))
            .clickable(
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
                onClick = onDismiss,
            ),
    ) {
        Column(
            Modifier.align(Alignment.Center).fillMaxWidth().padding(horizontal = 28.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Box(
                Modifier
                    .graphicsLayer { scaleX = badge.value; scaleY = badge.value }
                    .size(112.dp)
                    .clip(CircleShape)
                    .background(Color.White),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    when (outcome.tone) {
                        Tone.OK -> Icons.Filled.Check
                        Tone.WARN -> Icons.Filled.PriorityHigh
                        Tone.FAIL -> Icons.Filled.Close
                    },
                    contentDescription = null,
                    tint = tint,
                    modifier = Modifier.size(64.dp),
                )
            }
            Spacer(Modifier.height(26.dp))
            Text(
                when (outcome.tone) {
                    Tone.OK -> "Checked in"
                    Tone.WARN -> "Already checked in"
                    Tone.FAIL -> "Not valid"
                },
                color = Color.White,
                fontSize = 26.sp,
                fontWeight = FontWeight.ExtraBold,
                textAlign = TextAlign.Center,
            )
            outcome.guest?.let { name ->
                Spacer(Modifier.height(10.dp))
                Text(
                    name,
                    color = Color.White,
                    fontSize = 34.sp,
                    lineHeight = 38.sp,
                    fontWeight = FontWeight.Black,
                    letterSpacing = (-0.5).sp,
                    textAlign = TextAlign.Center,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            val detail = when (outcome.tone) {
                // A refusal says why; the server's reason is the useful part.
                Tone.FAIL -> outcome.message
                else -> listOfNotNull(
                    outcome.quantity.takeIf { it > 0 }?.let { if (it == 1) "1 person" else "$it people" },
                    outcome.slotLabel,
                ).joinToString(" · ").ifBlank { null }
            }
            detail?.let {
                Spacer(Modifier.height(10.dp))
                Text(
                    it,
                    color = Color(0xE6FFFFFF),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.SemiBold,
                    textAlign = TextAlign.Center,
                    lineHeight = 22.sp,
                )
            }
            Spacer(Modifier.height(14.dp))
            Text(
                outcome.code,
                color = Color(0xB3FFFFFF),
                fontSize = 13.sp,
                fontFamily = FontFamily.Monospace,
                letterSpacing = 1.5.sp,
            )
        }

        Column(
            // Above the floating bar, which sits over this screen.
            Modifier.align(Alignment.BottomCenter).fillMaxWidth()
                .padding(bottom = bottomInset)
                .padding(horizontal = 28.dp, vertical = 30.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text("Tap to scan the next ticket", color = Color(0xCCFFFFFF), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(12.dp))
            Box(
                Modifier.fillMaxWidth().height(4.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color(0x40FFFFFF)),
            ) {
                Box(
                    Modifier.fillMaxWidth(countdown.value).fillMaxHeight()
                        .clip(RoundedCornerShape(999.dp))
                        .background(Color.White),
                )
            }
        }
    }
}

@Composable
private fun RecentRow(entry: Outcome) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(7.dp).clip(RoundedCornerShape(999.dp)).background(entry.tone.color()))
        Spacer(Modifier.width(9.dp))
        // A name is what the operator remembers from thirty seconds ago; the code
        // is the fallback when the ticket didn't carry one.
        Text(
            entry.guest ?: entry.code,
            color = Color(0xCCFFFFFF),
            fontSize = 12.sp,
            fontFamily = if (entry.guest == null) FontFamily.Monospace else FontFamily.Default,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        Spacer(Modifier.weight(1f))
        Text(entry.message, color = Color(0x99FFFFFF), fontSize = 12.sp, maxLines = 1)
    }
}

@Composable
private fun GlassButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    active: Boolean,
    accent: Color,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(if (active) accent else Color(0x33FFFFFF))
            .border(1.dp, Color(0x40FFFFFF), RoundedCornerShape(999.dp))
            .clickable(interactionSource = interaction, indication = null) { onClick() }
            .padding(horizontal = 16.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(label, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * Typing a code by hand — the fallback when a screen is cracked or a phone is dead.
 *
 * A ticket code is a fixed-alphabet string people read off a screen aloud, so it
 * is set in monospace with real tracking and forced to upper case. It is a panel
 * over the camera rather than a separate screen: the queue does not stop while
 * somebody types.
 */
@Composable
private fun ManualEntry(
    accent: Color,
    busy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (String) -> Unit,
) {
    var code by remember { mutableStateOf("") }
    val focus = LocalFocusManager.current
    // The panel opens because somebody already decided to type, so it arrives
    // with the cursor in the field and the keyboard up — one tap, not three.
    val field = remember { FocusRequester() }
    LaunchedEffect(Unit) { field.requestFocus() }

    Box(
        Modifier.fillMaxSize()
            .background(Color(0xB3000000))
            .clickable(
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
            ) { focus.clearFocus(); onDismiss() },
    ) {
        Column(
            Modifier.align(Alignment.BottomCenter)
                .fillMaxWidth()
                .imePadding()
                .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
                .background(Color(0xFF0F172A))
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                ) { /* swallow taps so the panel doesn't dismiss itself */ }
                .padding(22.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Enter ticket code",
                    color = Color.White,
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Icon(
                    Icons.Filled.Close,
                    contentDescription = "Close",
                    tint = Color(0x99FFFFFF),
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .clickable { focus.clearFocus(); onDismiss() }
                        .padding(6.dp)
                        .size(20.dp),
                )
            }
            Spacer(Modifier.height(16.dp))
            BasicTextField(
                value = code,
                onValueChange = { code = it.uppercase().filter { c -> c.isLetterOrDigit() } },
                singleLine = true,
                textStyle = TextStyle(
                    color = Color.White,
                    fontSize = 22.sp,
                    fontFamily = FontFamily.Monospace,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 4.sp,
                ),
                cursorBrush = SolidColor(accent),
                keyboardOptions = KeyboardOptions(
                    capitalization = KeyboardCapitalization.Characters,
                    imeAction = ImeAction.Done,
                ),
                keyboardActions = KeyboardActions(onDone = { if (code.isNotBlank()) onSubmit(code) }),
                modifier = Modifier.fillMaxWidth().focusRequester(field),
                decorationBox = { inner ->
                    Column {
                        Box(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
                            if (code.isEmpty()) {
                                Text(
                                    "TICKET CODE",
                                    color = Color(0x59FFFFFF),
                                    fontSize = 22.sp,
                                    fontFamily = FontFamily.Monospace,
                                    fontWeight = FontWeight.Bold,
                                    letterSpacing = 4.sp,
                                )
                            }
                            inner()
                        }
                        Box(
                            Modifier.fillMaxWidth().height(2.dp)
                                .background(if (code.isEmpty()) Color(0x33FFFFFF) else accent)
                        )
                    }
                },
            )
            Spacer(Modifier.height(20.dp))
            val ready = code.isNotBlank() && !busy
            Box(
                Modifier.fillMaxWidth().height(52.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(if (ready) accent else Color(0x1FFFFFFF))
                    .clickable(enabled = ready) { focus.clearFocus(); onSubmit(code) },
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    if (busy) "Checking…" else "Check in",
                    color = if (ready) Color.White else Color(0x66FFFFFF),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

/** Shown when the camera is unavailable — the one case where this screen is not a camera. */
@Composable
private fun CameraGate(refused: Boolean, accent: Color, onAsk: () -> Unit) {
    Column(
        Modifier.fillMaxSize().background(Color(0xFF0B1220)).padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(
            Icons.Filled.PhotoCamera,
            contentDescription = null,
            tint = Color(0x80FFFFFF),
            modifier = Modifier.size(40.dp),
        )
        Spacer(Modifier.height(18.dp))
        Text(
            if (refused) "Camera access is off" else "Starting the camera…",
            color = Color.White,
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            if (refused) {
                "Check-in needs the camera to read ticket QRs. You can still type codes by hand."
            } else {
                "One moment."
            },
            color = Color(0xB3FFFFFF),
            fontSize = 13.5.sp,
            lineHeight = 19.sp,
        )
        if (refused) {
            Spacer(Modifier.height(22.dp))
            Box(
                Modifier.clip(RoundedCornerShape(999.dp)).background(accent)
                    .clickable { onAsk() }
                    .padding(horizontal = 22.dp, vertical = 13.dp),
            ) {
                Text("Allow camera", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
