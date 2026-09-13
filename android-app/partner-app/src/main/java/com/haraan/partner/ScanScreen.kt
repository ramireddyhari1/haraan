package com.haraan.partner

import android.Manifest
import android.content.pm.PackageManager
import android.view.HapticFeedbackConstants
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.LinearEasing
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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.FlashlightOff
import androidx.compose.material.icons.filled.FlashlightOn
import androidx.compose.material.icons.filled.Keyboard
import androidx.compose.material.icons.filled.PhotoCamera
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
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
internal fun ScanScreen(api: PartnerApi, token: String, accent: Color) {
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
    val recent = remember { mutableListOf<Outcome>().toMutableStateList() }

    val barcode = remember {
        BarcodeView(context).apply {
            decoderFactory = DefaultDecoderFactory(listOf(BarcodeFormat.QR_CODE))
        }
    }

    fun submit(raw: String) {
        if (busy) return
        val code = ticketCodeOf(raw)
        if (code.isBlank()) return
        busy = true
        // Stop decoding while the round trip runs, so one ticket can't fire three
        // times before the first answer lands.
        barcode.pause()
        scope.launch {
            val result = runCatching { api.checkIn(token, code) }
            val done = result.fold(
                onSuccess = { Outcome.of(code, it.message) },
                onFailure = { Outcome(code, it.message ?: "Check-in failed", Tone.FAIL) },
            )
            view.performHapticFeedback(
                if (done.tone == Tone.OK) HapticFeedbackConstants.CONFIRM
                else HapticFeedbackConstants.REJECT
            )
            outcome = done
            recent.add(0, done)
            while (recent.size > 3) recent.removeAt(recent.lastIndex)
            busy = false

            // Hold the answer long enough to read, then hand the camera back. The
            // person scanning never has to press anything between tickets.
            delay(if (done.tone == Tone.OK) 1800 else 2800)
            outcome = null
            if (granted) barcode.resume()
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

    BoxWithConstraints(Modifier.fillMaxSize().background(Color.Black)) {
        // The frame is a promise about where the camera is looking, so the decoder
        // is cropped to exactly the square that gets drawn — otherwise a code
        // outside the window would scan and the frame would be decoration.
        val side: Dp = minOf(maxWidth * 0.74f, maxHeight * 0.42f)
        val sidePx = with(LocalDensity.current) { side.roundToPx() }
        LaunchedEffect(sidePx) { barcode.framingRectSize = Size(sidePx, sidePx) }

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

        ScanFrame(side = side, sweeping = outcome == null && !busy, modifier = Modifier.align(Alignment.Center))

        Column(
            Modifier.align(Alignment.TopCenter).fillMaxWidth().padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                "Point at the ticket QR",
                color = Color.White,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                "Scanning continuously — no need to tap",
                color = Color(0xB3FFFFFF),
                fontSize = 12.sp,
            )
        }

        AnimatedVisibility(
            visible = outcome != null || busy,
            enter = fadeIn() + slideInVertically { -it / 2 },
            exit = fadeOut() + slideOutVertically { -it / 2 },
            modifier = Modifier.align(Alignment.TopCenter).padding(top = 92.dp, start = 18.dp, end = 18.dp),
        ) {
            OutcomeBanner(outcome = outcome, busy = busy)
        }

        Column(
            Modifier.align(Alignment.BottomCenter).fillMaxWidth().padding(18.dp),
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
    }
}

/** What happened to one ticket. */
internal data class Outcome(val code: String, val message: String, val tone: Tone) {
    companion object {
        /** The server answers in prose; the tone is read off the sentence it sends. */
        fun of(code: String, message: String): Outcome = Outcome(
            code = code,
            message = message,
            tone = when {
                message.startsWith("Checked in", ignoreCase = true) -> Tone.OK
                message.startsWith("Already", ignoreCase = true) -> Tone.WARN
                else -> Tone.FAIL
            },
        )
    }
}

internal enum class Tone { OK, WARN, FAIL }

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
 * The aiming square: four corner brackets and a sweep.
 *
 * Brackets rather than a full rectangle because the corners alone read as "aim
 * here" without boxing in the picture, and the sweep is the one piece of motion
 * that tells the operator the camera is live rather than frozen.
 */
@Composable
private fun ScanFrame(side: Dp, sweeping: Boolean, modifier: Modifier = Modifier) {
    val transition = rememberInfiniteTransition(label = "scan-sweep")
    val travel by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(2400, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "sweep",
    )

    Canvas(modifier.size(side)) {
        val arm = size.minDimension * 0.16f
        val stroke = 4.dp.toPx()
        val inset = stroke / 2f
        val w = size.width
        val h = size.height

        fun corner(x: Float, y: Float, dx: Float, dy: Float) {
            drawLine(Color.White, Offset(x, y), Offset(x + dx * arm, y), stroke, StrokeCap.Round)
            drawLine(Color.White, Offset(x, y), Offset(x, y + dy * arm), stroke, StrokeCap.Round)
        }
        corner(inset, inset, 1f, 1f)
        corner(w - inset, inset, -1f, 1f)
        corner(inset, h - inset, 1f, -1f)
        corner(w - inset, h - inset, -1f, -1f)

        if (sweeping) {
            val y = h * (0.08f + 0.84f * travel)
            drawRect(
                brush = Brush.horizontalGradient(
                    listOf(Color.Transparent, Color(0xCCFFFFFF), Color.Transparent)
                ),
                topLeft = Offset(arm * 0.4f, y),
                size = androidx.compose.ui.geometry.Size(w - arm * 0.8f, 2.dp.toPx()),
            )
        }
    }
}

@Composable
private fun OutcomeBanner(outcome: Outcome?, busy: Boolean) {
    val tone = outcome?.tone
    val tint = tone?.color() ?: Color.White
    Row(
        Modifier.fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color(0xF2101828))
            .border(1.dp, tint.copy(alpha = 0.55f), RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (outcome == null || busy) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(12.dp))
            Text("Checking ticket…", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
        } else {
            Box(
                Modifier.size(26.dp).clip(RoundedCornerShape(999.dp)).background(tint),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    if (outcome.tone == Tone.OK) Icons.Filled.Check else Icons.Filled.ErrorOutline,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(16.dp),
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    outcome.message,
                    color = Color.White,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    lineHeight = 19.sp,
                )
                Text(
                    outcome.code,
                    color = Color(0x99FFFFFF),
                    fontSize = 11.5.sp,
                    fontFamily = FontFamily.Monospace,
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
        Text(
            entry.code,
            color = Color(0xCCFFFFFF),
            fontSize = 12.sp,
            fontFamily = FontFamily.Monospace,
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
