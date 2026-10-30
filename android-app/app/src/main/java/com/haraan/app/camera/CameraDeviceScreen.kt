package com.haraan.app.camera

import android.content.Context
import android.content.res.Configuration
import android.os.Build
import android.os.PowerManager
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.video.MediaStoreOutputOptions
import androidx.camera.video.Quality
import androidx.camera.video.QualitySelector
import androidx.camera.video.Recorder
import androidx.camera.core.ImageAnalysis
import androidx.camera.video.Recording
import androidx.camera.video.VideoCapture
import androidx.camera.video.VideoRecordEvent
import androidx.camera.core.CameraSelector
import androidx.camera.core.Preview
import androidx.camera.core.resolutionselector.AspectRatioStrategy
import androidx.camera.core.resolutionselector.ResolutionSelector
import androidx.camera.core.resolutionselector.ResolutionStrategy
import androidx.camera.view.PreviewView
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.displayCutoutPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.withFrameMillis
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.waitForUpOrCancellation
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.vision.WicketDiagnosticsPanel
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.foundation.Image
import androidx.core.content.ContextCompat
import com.haraan.app.R
import com.haraan.app.theme.ArchivoDisplay
import com.haraan.app.ui.Feel
import com.haraan.app.data.CameraDeviceRepository
import com.haraan.app.vision.OpenCvBallTracker
import com.haraan.app.vision.drawWicketLock
import com.haraan.app.vision.drawWicketRegion
import com.haraan.app.vision.TrackQuality
import com.haraan.app.data.CameraSession
import com.haraan.app.data.PairingPreview
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.File
import java.util.concurrent.Executors

// ─────────────────────────────────────────────────────────────────────────────
//  THE CAMERA PHONE
//
//  Three states, in order: what you are about to join, joining it, and filming.
//
//  Everything here is built for someone standing at a boundary holding a phone
//  they are not going to look at: one enormous button, a status line readable at
//  arm's length, and no navigation to get lost in. The screen never sleeps.
//
//  What it captures is FOOTAGE, and the wording never promises more. A single
//  uncalibrated phone at 30fps cannot adjudicate an LBW, and a screen that said
//  it could would be believed.
// ─────────────────────────────────────────────────────────────────────────────

private val Ink = Color(0xFFF8FAFC)
private val Panel = Color(0xFF111827)
private val Page = Color(0xFF0B1220)
private val Accent = Color(0xFF2563EB)
private val Rec = Color(0xFFDC2626)
private val Good = Color(0xFF16A34A)
private val Warn = Color(0xFFF59E0B)

/** The one surface colour for chrome over footage: dark enough to hold white text in sun. */
private val Scrim = Color(0x99000000)

/** Selected-state blue, lifted so it reads on a dark scrim where #2563EB goes muddy. */
private val SelectedBlue = Color(0xFF8DB0FF)

@Composable
fun CameraDeviceScreen(
    initialCode: String?,
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onExit: () -> Unit,
    /** Debug builds: a video file filmed elsewhere, fed to the live analyser in place of the lens. See [VideoCameraFeed]. */
    videoFeed: String? = null,
    /** Debug builds: the field-test session's recording quality, as /control would set it. */
    testQuality: String? = null,
) {
    val repo = remember { CameraDeviceRepository() }
    val scope = rememberCoroutineScope()

    var preview by remember { mutableStateOf<PairingPreview?>(null) }
    var session by remember { mutableStateOf<CameraSession?>(null) }
    var problem by remember { mutableStateOf<JoinProblem?>(null) }
    var busy by remember { mutableStateOf(false) }
    // Bumped by "Try again", so the preview below runs once more.
    var attempt by remember { androidx.compose.runtime.mutableIntStateOf(0) }

    LaunchedEffect(initialCode, attempt) {
        val code = initialCode
        if (code == "TEST" || code == "DEBUG" || (com.haraan.app.BuildConfig.DEBUG && code == null)) {
            session = com.haraan.app.data.CameraSession(
                sessionToken = "debug_token",
                role = com.haraan.app.data.MatchDeviceRole.LBW_REVIEW,
                roleLabel = "Review Camera (Field Test)",
                matchId = "debug_match",
                matchTitle = "Live Camera · Field Test",
                venue = "Cricket Ground",
                videoQuality = com.haraan.app.data.videoQualityOf(testQuality),
            )
            return@LaunchedEffect
        }
        if (code == null) {
            problem = JoinProblem(
                "This link has no code in it",
                "Scan the QR code on the scorer's phone again, or ask them to share the link.",
                canRetry = false,
            )
            return@LaunchedEffect
        }
        problem = null
        busy = true
        runCatching { repo.preview(code) }
            .onSuccess { preview = it }
            .onFailure { problem = joinProblemOf(it) }
        busy = false
    }

    Box(Modifier.fillMaxSize().background(Page)) {
        val joined = session
        when {
            joined != null -> CameraMode(
                session = joined,
                repo = repo,
                videoFeed = videoFeed,
                hasCameraPermission = hasCameraPermission,
                requestCameraPermission = requestCameraPermission,
                onDropped = {
                    session = null
                    problem = JoinProblem(
                        "You've left the match",
                        "The scorer removed this camera, or the connection was lost for over a minute. Scan a new code to film again.",
                        canRetry = false,
                    )
                },
                onExit = onExit,
            )

            else -> JoinPanel(
                preview = preview,
                busy = busy,
                problem = problem,
                onJoin = {
                    val code = initialCode ?: return@JoinPanel
                    busy = true
                    problem = null
                    scope.launch {
                        runCatching { repo.claim(code, android.os.Build.MODEL ?: "Camera phone") }
                            .onSuccess { session = it }
                            .onFailure { problem = joinProblemOf(it) }
                        busy = false
                    }
                },
                onRetry = { attempt++ },
                onExit = onExit,
            )
        }
    }
}

/** Why this phone is not filming yet, in words written for the person holding it. */
private data class JoinProblem(val title: String, val body: String, val canRetry: Boolean)

private fun joinProblemOf(error: Throwable): JoinProblem {
    val kind = (error as? com.haraan.app.data.PairingProblem)?.kind
        ?: if (error is java.io.IOException) com.haraan.app.data.PairingProblem.Kind.OFFLINE
        else com.haraan.app.data.PairingProblem.Kind.SERVER
    return when (kind) {
        com.haraan.app.data.PairingProblem.Kind.INVALID -> JoinProblem(
            "This code doesn't work",
            "Ask the scorer to open Add a camera and show you a new code.",
            canRetry = false,
        )
        com.haraan.app.data.PairingProblem.Kind.EXPIRED -> JoinProblem(
            "This code has expired",
            "Codes work once and for a short time. Ask the scorer for a new one.",
            canRetry = false,
        )
        com.haraan.app.data.PairingProblem.Kind.OFFLINE -> JoinProblem(
            "No connection",
            "Turn on mobile data or Wi-Fi on this phone, then try again.",
            canRetry = true,
        )
        com.haraan.app.data.PairingProblem.Kind.CAMERA_TIME_OVER -> JoinProblem(
            "This match's camera time is over",
            "Cameras work during the scorer's booked venue slot, or any time with Pro or Hero.",
            canRetry = false,
        )
        com.haraan.app.data.PairingProblem.Kind.SERVER -> JoinProblem(
            "Haraan isn't answering",
            "That's on our side, not yours. Give it a moment and try again.",
            canRetry = true,
        )
    }
}

/**
 * What you are about to join, before you join it.
 *
 * Built like the stock camera app a stranger already trusts rather than like a landing
 * page: the picture on top is what this phone is about to film, and the words underneath
 * are anchored to the bottom where the thumb is. The headline is the MATCH — the one line
 * no other screen could print — not a description of the screen.
 *
 * The viewfinder in the picture is the loading state. It hunts while the code is being
 * checked and locks onto the stumps, with a tick in the hand, when the match is found. On a
 * problem it stays open and dim: nothing to lock onto.
 */
@Composable
private fun JoinPanel(
    preview: PairingPreview?,
    busy: Boolean,
    problem: JoinProblem?,
    onJoin: () -> Unit,
    onRetry: () -> Unit,
    onExit: () -> Unit,
) {
    val view = LocalView.current
    val locked = preview != null && problem == null
    LaunchedEffect(locked) { if (locked) view.performHapticFeedback(Feel.TICK) }

    androidx.compose.foundation.layout.BoxWithConstraints(Modifier.fillMaxSize()) {
    val sideways = LocalConfiguration.current.orientation == Configuration.ORIENTATION_LANDSCAPE
    val sceneHeight = if (sideways) 170.dp else (maxHeight * 0.6f).coerceIn(240.dp, 520.dp)
    Column(
        Modifier
            .fillMaxWidth()
            // Scrolls, because this screen is reachable sideways: somebody who scans the
            // QR with the phone already turned must still be able to reach the button.
            // At least a screen tall, so the text sits on the bottom edge when it fits.
            .verticalScroll(rememberScrollState())
            .heightIn(min = maxHeight),
        verticalArrangement = Arrangement.SpaceBetween,
    ) {
        Box(
            Modifier
                .fillMaxWidth()
                .height(sceneHeight),
        ) {
            JoinScene(
                role = preview?.role,
                locked = locked,
                failed = problem != null,
                modifier = Modifier.fillMaxSize(),
            )
            // Signed: whoever scanned a stranger's code is entitled to see whose software
            // just opened on their phone.
            Row(
                Modifier
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(start = 22.dp, end = 12.dp, top = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Image(
                    painter = painterResource(R.drawable.haraan_logo_white),
                    contentDescription = "Haraan",
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.height(18.dp),
                )
                Spacer(Modifier.weight(1f))
                Box(
                    Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .clickableCapture(enabled = true, onClick = onExit)
                        .semantics { contentDescription = "Close" },
                    contentAlignment = Alignment.Center,
                ) {
                    ExitCrossGlyph(Modifier.size(14.dp), Ink.copy(alpha = 0.8f))
                }
            }
        }

        Column(
            Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .displayCutoutPadding()
                .padding(horizontal = 24.dp)
                .padding(top = 8.dp, bottom = 20.dp),
        ) {
            Staged(0) {
                Text(
                    when {
                        problem != null -> "MATCH CAMERA"
                        preview == null -> "CHECKING THE CODE"
                        else -> "YOU'RE JOINING"
                    },
                    color = if (problem != null) Ink.copy(alpha = 0.45f) else SelectedBlue,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.6.sp,
                )
            }
            Spacer(Modifier.height(10.dp))

            when {
                problem != null -> {
                    Staged(1) {
                        Text(
                            problem.title,
                            color = Ink,
                            fontSize = 30.sp,
                            fontFamily = ArchivoDisplay,
                            letterSpacing = (-0.8).sp,
                            lineHeight = 34.sp,
                        )
                    }
                    Spacer(Modifier.height(10.dp))
                    Staged(2) {
                        Text(problem.body, color = Ink.copy(alpha = 0.66f), fontSize = 15.sp, lineHeight = 22.sp)
                    }
                }

                preview == null -> {
                    // The shape of what is coming, not a spinner: the lines land where the
                    // match's name and ground will be.
                    SkeletonBar(widthFraction = 0.78f, height = 30.dp)
                    Spacer(Modifier.height(12.dp))
                    SkeletonBar(widthFraction = 0.45f, height = 15.dp)
                }

                else -> {
                    Staged(1) {
                        Text(
                            preview.matchTitle.ifBlank { "Your match" },
                            color = Ink,
                            fontSize = 30.sp,
                            fontFamily = ArchivoDisplay,
                            letterSpacing = (-0.8).sp,
                            lineHeight = 34.sp,
                        )
                    }
                    if (preview.venue.isNotBlank()) {
                        Spacer(Modifier.height(6.dp))
                        Staged(2) {
                            Text(preview.venue, color = Ink.copy(alpha = 0.55f), fontSize = 15.sp)
                        }
                    }
                    Spacer(Modifier.height(22.dp))
                    Staged(3) {
                        Row(verticalAlignment = Alignment.Top) {
                            Box(
                                Modifier
                                    .padding(top = 2.dp)
                                    .size(38.dp)
                                    .clip(RoundedCornerShape(11.dp))
                                    .background(Accent.copy(alpha = 0.18f)),
                                contentAlignment = Alignment.Center,
                            ) {
                                StumpsGlyph(Modifier.size(18.dp), SelectedBlue)
                            }
                            Spacer(Modifier.width(14.dp))
                            Column {
                                Text(
                                    "This phone becomes the ${preview.roleLabel.ifBlank { preview.role.label }.replaceFirstChar(Char::lowercase)}",
                                    color = Ink.copy(alpha = 0.92f),
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    lineHeight = 20.sp,
                                )
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    preview.role.blurb,
                                    color = Ink.copy(alpha = 0.58f),
                                    fontSize = 13.5.sp,
                                    lineHeight = 19.sp,
                                )
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(28.dp))
            when {
                problem?.canRetry == true -> PrimaryButton("Try again", enabled = !busy, onClick = onRetry)
                problem != null -> PrimaryButton("Close", enabled = true, onClick = onExit)
                else -> PrimaryButton(
                    when {
                        busy && preview != null -> "Joining…"
                        preview == null -> "Join this match"
                        else -> "Join as camera"
                    },
                    enabled = preview != null && !busy,
                    onClick = onJoin,
                )
            }
            if (problem == null) {
                Spacer(Modifier.height(14.dp))
                Text(
                    "Films a short clip of each ball for the scorer. It doesn't make any decisions.",
                    color = Ink.copy(alpha = 0.4f),
                    fontSize = 12.5.sp,
                    lineHeight = 18.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}
}

/** A placeholder line that breathes while the real one is on its way. */
@Composable
private fun SkeletonBar(widthFraction: Float, height: androidx.compose.ui.unit.Dp) {
    val t = rememberInfiniteTransition(label = "skeleton")
    val a by t.animateFloat(
        0.06f,
        0.13f,
        infiniteRepeatable(tween(750, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "skeletonAlpha",
    )
    Box(
        Modifier
            .fillMaxWidth(widthFraction)
            .height(height)
            .clip(RoundedCornerShape(8.dp))
            .background(Color.White.copy(alpha = a)),
    )
}

/**
 * The picture: a pitch at dusk, seen the way this phone will see it.
 *
 * Down the strip for the review camera, side-on for the bowler camera — the same two
 * placements the scorer was shown when they made the code, so the two phones agree about
 * where this one is meant to stand. Viewfinder brackets hunt over the far stumps and close
 * on them on a spring once the match is found.
 */
@Composable
private fun JoinScene(
    role: com.haraan.app.data.MatchDeviceRole?,
    locked: Boolean,
    failed: Boolean,
    modifier: Modifier = Modifier,
) {
    val lock by animateFloatAsState(
        targetValue = if (locked) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 260f),
        label = "lock",
    )
    val hunt = rememberInfiniteTransition(label = "hunt")
    val drift by hunt.animateFloat(
        -1f,
        1f,
        infiniteRepeatable(tween(1400, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "drift",
    )
    val sideOn = role == com.haraan.app.data.MatchDeviceRole.BOWLER_ANALYSIS

    Canvas(modifier) {
        val w = size.width
        val h = size.height

        // Sky to outfield: dusk navy into turf, then into the page colour so the picture
        // has no bottom edge.
        drawRect(
            Brush.verticalGradient(
                0f to Color(0xFF14233F),
                0.42f to Color(0xFF0E1A2E),
                0.43f to Color(0xFF0F2A22),
                0.8f to Color(0xFF0A1A17),
                1f to Page,
            ),
        )
        // Mowing bands on the outfield: the one texture that says "cricket ground".
        val horizon = h * 0.43f
        var band = 0
        var y = horizon
        while (y < h) {
            val next = y + (6f + (y - horizon) * 0.16f)
            if (band % 2 == 0) {
                drawRect(
                    Color.White.copy(alpha = 0.018f),
                    topLeft = Offset(0f, y),
                    size = androidx.compose.ui.geometry.Size(w, next - y),
                )
            }
            band++
            y = next
        }

        val strip = Color(0xFFC9B48A)
        val stumpInk = Color(0xFFF1E7D0)
        val target: Offset
        if (!sideOn) {
            // Down the pitch: a strip narrowing to the far wicket.
            val cx = w / 2f
            val farY = horizon + h * 0.08f
            val nearY = h
            drawPath(
                Path().apply {
                    moveTo(cx - w * 0.045f, farY)
                    lineTo(cx + w * 0.045f, farY)
                    lineTo(cx + w * 0.30f, nearY)
                    lineTo(cx - w * 0.30f, nearY)
                    close()
                },
                Brush.verticalGradient(
                    listOf(strip.copy(alpha = 0.55f), strip.copy(alpha = 0.18f)),
                    startY = farY,
                    endY = nearY,
                ),
            )
            // Creases.
            drawLine(stumpInk.copy(alpha = 0.5f), Offset(cx - w * 0.07f, farY + 6.dp.toPx()), Offset(cx + w * 0.07f, farY + 6.dp.toPx()), 1.dp.toPx())
            val stumpH = h * 0.075f
            listOf(-1, 0, 1).forEach { i ->
                val x = cx + i * 4.5.dp.toPx()
                drawLine(stumpInk, Offset(x, farY), Offset(x, farY - stumpH), 2.dp.toPx(), StrokeCap.Round)
            }
            drawLine(stumpInk, Offset(cx - 6.dp.toPx(), farY - stumpH - 1.5.dp.toPx()), Offset(cx + 6.dp.toPx(), farY - stumpH - 1.5.dp.toPx()), 1.6.dp.toPx(), StrokeCap.Round)
            target = Offset(cx, farY - stumpH / 2f)
        } else {
            // Side-on: the strip runs across the frame, a wicket at each end.
            val top = horizon + h * 0.2f
            val bottom = top + h * 0.1f
            drawRect(
                strip.copy(alpha = 0.4f),
                topLeft = Offset(w * 0.08f, top),
                size = androidx.compose.ui.geometry.Size(w * 0.84f, bottom - top),
            )
            val stumpH = h * 0.12f
            listOf(w * 0.18f, w * 0.82f).forEach { x ->
                listOf(-1, 0, 1).forEach { i ->
                    val sx = x + i * 3.dp.toPx()
                    drawLine(stumpInk, Offset(sx, top + 6.dp.toPx()), Offset(sx, top + 6.dp.toPx() - stumpH), 2.dp.toPx(), StrokeCap.Round)
                }
            }
            target = Offset(w * 0.5f, top - stumpH * 0.3f)
        }

        // No bottom edge: the ground fades into the page the words sit on.
        drawRect(
            Brush.verticalGradient(
                0f to Color.Transparent,
                1f to Page,
                startY = h * 0.62f,
                endY = h,
            ),
            topLeft = Offset(0f, h * 0.62f),
            size = androidx.compose.ui.geometry.Size(w, h * 0.38f),
        )

        // The viewfinder. Wide and wandering while hunting; tight and still once locked.
        val wander = (1f - lock) * (if (failed) 0f else 1f)
        val centre = Offset(target.x + drift * 10.dp.toPx() * wander, target.y + drift * 4.dp.toPx() * wander)
        val half = (if (sideOn) w * 0.36f else 40.dp.toPx()) * (1.4f - 0.4f * lock)
        val halfH = (if (sideOn) h * 0.16f else 46.dp.toPx()) * (1.3f - 0.3f * lock)
        val arm = 14.dp.toPx()
        val ink = when {
            failed -> Color.White.copy(alpha = 0.22f)
            lock > 0.5f -> lerp(Color.White, Color(0xFF8DB0FF), lock)
            else -> Color.White.copy(alpha = 0.7f)
        }
        val sw = 2.2.dp.toPx()
        listOf(-1f to -1f, 1f to -1f, -1f to 1f, 1f to 1f).forEach { (sx, sy) ->
            val p = Offset(centre.x + sx * half, centre.y + sy * halfH)
            drawLine(ink, p, Offset(p.x - sx * arm, p.y), sw, StrokeCap.Round)
            drawLine(ink, p, Offset(p.x, p.y - sy * arm), sw, StrokeCap.Round)
        }
        // A focus dot at the centre once locked — the moment the picture is "taken".
        if (lock > 0.05f) {
            drawCircle(Color(0xFF8DB0FF).copy(alpha = lock.coerceIn(0f, 1f)), radius = 2.5.dp.toPx(), center = centre)
        }
    }
}

/**
 * Filming.
 *
 * The button is the whole interface: hold-free, one tap starts an eight-second capture
 * that ends itself, because a scorer shouting "record!" across a ground cannot also
 * tell this phone when to stop.
 */
@Composable
private fun CameraMode(
    session: CameraSession,
    repo: CameraDeviceRepository,
    videoFeed: String?,
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onDropped: () -> Unit,
    onExit: () -> Unit,
) {
    val ctx = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    /*
     * THE ONLY CHANNEL THIS SCREEN HAS.
     *
     * Every other screen in the app can rely on being looked at. This one cannot: the
     * person holding it is watching a bowler run in, and a status line that changes
     * silently changes for nobody. So each thing that happens on its own - a clip ending,
     * an upload landing, a clip refused, the match going quiet - is also said in the hand.
     */
    val view = LocalView.current
    /*
     * WHICH WAY THE PHONE IS BEING HELD.
     *
     * From the configuration rather than BoxWithConstraints: this screen's state changes
     * about thirty times a second while a ball is in the air, and every one of those
     * writes would drag a subcomposition behind it. Orientation changes roughly never.
     */
    val configuration = LocalConfiguration.current
    val landscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE

    var granted by remember { mutableStateOf(hasCameraPermission()) }
    var recording by remember { mutableStateOf(false) }

    /*
     * THE PERSISTENT REVIEW CLIP QUEUE.
     *
     * Application-scoped singleton:
     * - Exactly one instance per application process, surviving screen recreation.
     * - Clips are stored durably on internal disk (context.filesDir/review_clips/).
     * - The shutter re-arms immediately so the operator can film consecutive deliveries.
     * - Drains in the background with exponential backoff on patchy cellular signal.
     * - Deletes ONLY upon confirmed 2xx landing with the scorer.
     */
    val uploadQueue = remember { ClipUploadQueue.getInstance(ctx) }
    val queueStatus by uploadQueue.status.collectAsState()
    // The phone's own gallery: waiting clips, and sent ones for the admin-set hours.
    val galleryClips by uploadQueue.gallery.collectAsState()
    var showGallery by remember { mutableStateOf(false) }

    DisposableEffect(uploadQueue) {
        val listener = object : ClipUploadQueue.UploadListener {
            override fun onUploadSuccess(overBall: String?) {
                view.performHapticFeedback(Feel.COMMIT)
            }

            override fun onUploadPermanentFailure(message: String) {
                view.performHapticFeedback(Feel.REMOVE)
            }
        }
        uploadQueue.addListener(listener)
        onDispose {
            uploadQueue.removeListener(listener)
        }
    }

    // Cleared on the next tap: a refusal is about the clip just filmed, not a mode the
    // camera is stuck in.
    var uploadError by remember { mutableStateOf<String?>(null) }
    var score by remember { mutableStateOf("") }
    var overs by remember { mutableStateOf("") }
    var live by remember { mutableStateOf(true) }

    val executor = remember { Executors.newSingleThreadExecutor() }
    var videoCapture by remember { mutableStateOf<VideoCapture<Recorder>?>(null) }

    /*
     * RECORDING QUALITY, from /control.
     *
     * Known at join, re-read on every heartbeat, and applied only BETWEEN balls: a change
     * that arrives while a clip is recording waits in [pendingQuality] until it finishes,
     * because rebinding the camera mid-delivery would end the clip being filmed.
     */
    var videoQuality by remember { mutableStateOf(session.videoQuality) }
    var pendingQuality by remember { mutableStateOf<String?>(null) }
    // The frame rate the camera actually agreed to, not the one asked for — shown on REC.
    var recordingFps by remember { mutableStateOf<Int?>(null) }
    var previewView by remember { mutableStateOf<PreviewView?>(null) }
    // Held only so its target rotation can be corrected on a turn — see below.
    var analysisUseCase by remember { mutableStateOf<ImageAnalysis?>(null) }


    /*
     * THE VISION ENGINE.
     *
     * Its own single thread, so analysis can never sit on the recorder's. Held for the
     * lifetime of the screen and reset per delivery rather than rebuilt, because the
     * native allocation is the expensive part.
     */
    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val vision = remember { OpenCvBallTracker().also { it.setDetail(BALL_DETAIL) } }
    // The ball step the clip screen and the scoreboard run too; see BallStep.
    val ballStep = remember { com.haraan.app.vision.BallStep(vision, keepLooking = true) }

    /*
     * HOW HOT THE PHONE IS.
     *
     * A phone doing all of this at once — screen pinned on in sunlight, 1080p encoder,
     * continuous OpenCV — gets hot, and Android's answer is to quietly slow it down. What
     * that looks like at a ground is a camera that filmed the first six overs and then
     * started dropping frames, and nobody can tell that from a bad clip or a bad angle.
     *
     * So the app makes the choice itself, out loud, and makes it in the order the feature
     * is worth: vision stops, filming continues. The clip is what a review is built on;
     * the trail is a nice thing to watch while it is being filmed.
     *
     * TWO LEVELS, NOT ONE. Pausing at MODERATE meant a phone in the Indian sun never
     * tracked at all: on a vivo I2407 at an outdoor net, charging, the status was SEVERE
     * before the first frame and every delivery went untracked. So:
     *
     *   warm (MODERATE, SEVERE): the ball is still tracked and the stumps still found, but
     *     lighter — the ball at its baseline resolution, the pitch-corner Hough search off,
     *     the stump search on every other frame.
     *   very hot (CRITICAL): the same, with the stump search on one frame in four. The same
     *     vivo, charging over USB in the sun, sat at CRITICAL with its CPUs at 54 °C — warm,
     *     not dangerous; its thermal HAL escalates early.
     *   hot (EMERGENCY and up, the step before Android starts shutting things down):
     *     analysis pauses, filming continues.
     *
     * Below API 29 there is nothing to listen to and both stay false.
     */
    var thermalThrottled by remember { mutableStateOf(false) }
    var thermalWarm by remember { mutableStateOf(false) }
    var thermalVeryHot by remember { mutableStateOf(false) }
    DisposableEffect(ctx) {
        // minSdk here is 24, so most of the fleet this ships to has no thermal API at all.
        // Those phones behave exactly as they did before: vision runs until the operator
        // stops it. Nothing about this feature depends on the listener existing.
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return@DisposableEffect onDispose { }
        val power = ctx.getSystemService(Context.POWER_SERVICE) as? PowerManager
            ?: return@DisposableEffect onDispose { }

        val hot = { status: Int -> status >= PowerManager.THERMAL_STATUS_EMERGENCY }
        val veryHot = { status: Int -> status >= PowerManager.THERMAL_STATUS_CRITICAL }
        val warm = { status: Int -> status >= PowerManager.THERMAL_STATUS_MODERATE }
        // Whatever it is right now: a phone handed over already warm should not have to
        // get hotter still before the app notices.
        val now = power.currentThermalStatus
        thermalThrottled = hot(now)
        thermalVeryHot = veryHot(now)
        thermalWarm = warm(now)
        val listener = PowerManager.OnThermalStatusChangedListener { status ->
            thermalThrottled = hot(status)
            thermalVeryHot = veryHot(status)
            thermalWarm = warm(status)
        }
        power.addThermalStatusListener(analysisExecutor, listener)
        onDispose { runCatching { power.removeThermalStatusListener(listener) } }
    }
    // Only counted while a delivery is actually being recorded — the camera runs the whole
    // time somebody is holding the phone, and tracking the warm-up is noise.
    var trackedPoints by remember { mutableStateOf(0) }
    var trackingLive by remember { mutableStateOf(false) }
    /*
     * MAY THIS MATCH USE ITS CAMERA RIGHT NOW.
     *
     * The match owner's plan (Pro, Hero), or a venue slot they booked — a 6–7 PM booking
     * films and tracks from 6:00 to 7:00 and no longer. Known at join, re-read every
     * heartbeat, and checked against the clock here too, so the slot ending pauses the camera
     * on time between check-ins. It stays paired: the owner's next slot or a plan resumes it.
     * Null (an older server) means the gate does not exist, so the camera works.
     */
    var trackingAccess by remember { mutableStateOf(session.cameraAccess) }
    /*
     * THE BALL, for release speed: a tennis ball loses pace three times as fast as a leather
     * one, so the same flight was bowled ~10 km/h quicker. From the match when its creator set
     * it; otherwise the last one used on this phone; changeable on the SPEED row.
     */
    var ball by remember {
        mutableStateOf(
            com.haraan.app.vision.BallDrag.fromServer(session.ballType)
                ?: com.haraan.app.vision.BallDrag.fromServer(
                    ctx.getSharedPreferences("camera_ball", android.content.Context.MODE_PRIVATE).getString("ball", null),
                )
                ?: com.haraan.app.vision.BallDrag.LEATHER,
        )
    }
    var clockMs by remember { mutableStateOf(System.currentTimeMillis()) }
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(15_000)
            clockMs = System.currentTimeMillis()
        }
    }
    val trackingAllowed = trackingAccess?.allowsAt(clockMs) ?: true
    // What the detector is seeing, for the overlay. Short on purpose: a long trail smears
    // into a blur and hides the jitter that tells you it is tracking the wrong thing.
    var ballTrail by remember { mutableStateOf<List<com.haraan.app.vision.BallSighting>>(emptyList()) }
    var latestBall by remember { mutableStateOf<com.haraan.app.vision.BallSighting?>(null) }

    /*
     * THE SAME JUDGEMENT AS THE TRAIL'S COLOUR, IN A WORD.
     *
     * Colour on its own is a signal some people cannot read and nobody can read well in
     * direct sun on a cheap screen at arm's length — which is every condition this phone
     * is held in. The tracker's own [TrackQuality] says the same thing about the track as
     * a whole, so it is worth the four characters it costs on a line that is already there.
     */
    var trackQuality by remember { mutableStateOf(TrackQuality.UNCERTAIN) }

    /*
     * WHERE THE LAST DELIVERY PITCHED.
     *
     * The only thing this phone can say in metres and be right about, so it is the only
     * number that goes on the screen. Worked out once when the clip ends rather than per
     * frame: it needs the whole track, and during the delivery there is nothing to say.
     *
     * Kept until the next tap, because it is also the plainest evidence the operator has
     * that the calibration is good — a marker sitting where the ball actually landed means
     * the corners are right, and one out in the covers means they are not.
     */
    var lastBounce by remember { mutableStateOf<com.haraan.app.vision.Bounce?>(null) }
    // Off by default: it is an aiming aid, not decoration, and it is in the way once the
    // phone is set. Persisted for the session so it does not reappear every delivery.
    var showGuide by remember { mutableStateOf(true) }

    /*
     * THE SHAPE OF THE PICTURE THE ANALYSER IS SEEING, upright.
     *
     * Every overlay here is drawn from coordinates normalised inside the ANALYSIS frame,
     * and until now they were painted across the whole viewfinder as though the two were
     * the same rectangle. They are not: the preview is letterboxed into a view of whatever
     * shape the phone happens to be, so a point at 0.9 across the analysis frame was landing
     * somewhere else entirely on screen — worst at the edges, which is precisely where a
     * ball is at release and where a crease corner is.
     *
     * Written from the analyser thread like the counters beside it, and read when drawing.
     */
    var uprightAspect by remember { mutableStateOf(0f) }

    /*
     * THE PITCH ITSELF, found rather than drawn.
     *
     * A static outline can only tell somebody roughly where to point. Once the creases are
     * located the guide stops being a template and becomes the thing a measurement stands
     * on: the same four corners are what a homography is solved from.
     *
     * So a corridor drawn on a DETECTED pitch is not decoration. It is the only visible
     * evidence that the phone understands what it is looking at - if it sits crooked on the
     * surface, the calibration behind it is wrong, and that is worth seeing before a review
     * is ever asked for.
     */
    val pitchDetector = remember { com.haraan.app.vision.OpenCvPitchDetector() }
    var pitchQuad by remember { mutableStateOf<com.haraan.app.vision.PitchQuad?>(null) }

    /*
     * And when it cannot find it: four taps.
     *
     * Faded paint, a wet outfield, an indoor net with no creases at all - detection will
     * fail on real grounds, and a feature that only works on a good pitch is not a feature.
     * Somebody who can see the ground can always point at its corners.
     *
     * A tapped quad OUTRANKS a detected one. A person looking at the pitch has better
     * information than a Hough transform, and the [QuadSource] recorded on it means every
     * measurement downstream can still say which of the two it stood on.
     */
    var tappedCorners by remember { mutableStateOf<List<com.haraan.app.vision.Point2>>(emptyList()) }
    var tapping by remember { mutableStateOf(false) }

    /*
     * THE WICKET, HELD ACROSS FRAMES.
     *
     * A pitch quad needs four painted crease corners, and on the grounds this app is used
     * on that paint is faded, mown off, or was never there. A wicket is never absent and is
     * exactly 0.2286 m across, which makes it the one landmark that can put a speed in
     * km/h and a projection in centimetres on a ground with no visible creases at all.
     *
     * Three pieces, and the order they run in matters:
     *
     *   [OpenCvCameraMotion] first, so the tripod's own settling is subtracted before
     *   anything is compared between frames.
     *
     *   [OpenCvStumpDetector] second, for one frame's opinion.
     *
     *   [WicketTracker] last, which is the only one of the three that ever says CONFIRMED.
     *   Nothing downstream reads the detector directly any more: a single frame's three
     *   aligned bars could be a bat, a pad and a boot, and the tracker is what makes them
     *   prove otherwise before a scale is taken from them.
     */
    // Brightness AND colour: yellow plastic stumps vanish in brightness against a pink wall.
    // See [com.haraan.app.vision.ColourStumpDetector].
    val stumpDetector = remember { com.haraan.app.vision.ColourStumpDetector() }
    val cameraMotion = remember { com.haraan.app.vision.OpenCvCameraMotion() }
    val wicketTracker = remember { com.haraan.app.vision.WicketTracker() }
    var wicketLock by remember { mutableStateOf<com.haraan.app.vision.WicketLock?>(null) }
    var wicketDiagnostics by remember { mutableStateOf(wicketTracker.diagnostics()) }
    var stumpReport by remember { mutableStateOf<com.haraan.app.vision.StumpDetectorReport?>(null) }
    // The last exception the analyser caught, for the developer readout. Null when healthy.
    var analysisError by remember { mutableStateOf<String?>(null) }
    /*
     * STARTUP AND RANGE, FOR THE DEVELOPER READOUT.
     *
     * When the screen asked for the camera (wall clock) and when the first frame reached the
     * analyser, so "camera → first frame" can be printed beside the tracker's own "→ first
     * detection" and "→ READY". The lens, read once from Camera2, turns a span into an
     * ESTIMATED distance. The upright frame's width turns a span into pixels.
     */
    val screenOpenedAt = remember { android.os.SystemClock.elapsedRealtime() }
    var cameraToFirstFrameMs by remember { mutableStateOf<Long?>(null) }
    val lensContext = LocalContext.current
    val lens = remember { readBackLens(lensContext) }
    var cameraIntrinsics by remember { mutableStateOf<com.haraan.app.vision.CameraIntrinsics?>(null) }
    /** The bowler's-end stumps, when seen: the pitch guide and the 3D run towards them. */
    var bowlersEndBase by remember { mutableStateOf<com.haraan.app.vision.BowlersEnd?>(null) }
    // Steadied over many sightings: one taken through the bowler's stride moved the ground.
    val bowlersEndFilter = remember { com.haraan.app.vision.BowlersEndFilter() }
    // The stumps steadied over many frames, for measuring the flight: see SteadyWicket.
    val steadyWicket = remember { com.haraan.app.vision.SteadyWicket() }
    // Its value as of the last frame, for drawing on the UI thread: see drawingLock.
    var steadyDrawLock by remember { mutableStateOf<com.haraan.app.vision.WicketLock?>(null) }
    // The batter's stumps looked for where the bowler's say: see FarWicketSearch.
    val farWicketSearch = remember { com.haraan.app.vision.FarWicketSearch() }
    // The corridor the live tracker was last given, for tracking the saved clip the same way.
    val corridorRef = remember { java.util.concurrent.atomic.AtomicReference<FloatArray?>(null) }
    // Bumped each time a clip is armed: a clip analysis finishing after the next ball is
    // under way must not put the old ball back on screen.
    var clipGeneration by remember { mutableStateOf(0) }
    /** The rate the shown flight was tracked at from the saved clip; null for the live one. */
    var refinedFps by remember { mutableStateOf<Double?>(null) }
    var uprightWidthPx by remember { androidx.compose.runtime.mutableIntStateOf(0) }
    // Field validation: taped distance, a recording window, and the log itself.
    val validation = remember { com.haraan.app.vision.WicketValidation() }
    var validationTrueM by remember { mutableStateOf(20.0) }
    var validationUntilMs by remember { mutableStateOf(0L) }
    var validationRows by remember { mutableStateOf<List<com.haraan.app.vision.WicketValidation.Row>>(emptyList()) }
    var validationSavedPath by remember { mutableStateOf<String?>(null) }
    /** Throttle: panel recomposes at ~5 Hz rather than analysis rate. */
    var lastDiagnosticsMs = remember { 0L }
    var showAdminPanel by remember { mutableStateOf(false) }
    // How far off level the phone is held, from gravity, for the developer readout only.
    // See [rememberDeviceRoll].
    val deviceRoll by rememberDeviceRoll(enabled = showAdminPanel)
    /** Holds the shutter fast while filming; see [ShutterControl]. */
    val shutter = remember { ShutterControl() }
    // The phone's own gravity says nothing about footage another camera took.
    if (videoFeed == null) rememberDeviceTiltFeed()

    /** The wicket placed by hand, two taps at the base of the outer stumps. */
    var wicketTapping by remember { mutableStateOf(false) }
    // The markings' Adjust: both sets dragged into place by hand (see MarkingsAdjust).
    var adjusting by remember { mutableStateOf(false) }
    // Set by Adjust: the bowler's stumps stay where the hand put them, not where the detector
    // next sees them. Cleared by Reset, Setup, a turn of the phone, or the phone being moved.
    var bowlersEndPinned by remember { mutableStateOf(false) }

    /*
     * CALIBRATING FROM THE STUMPS, AND WHICH END THE PHONE IS AT.
     *
     * The end is the operator's to say — the phone cannot tell a bowler's stumps from a
     * batter's — and it decides what every tap means. Six taps, the outer feet and the
     * middle top at each end, solve the camera outright (see [StumpCalibration]); the quad
     * that comes out outranks anything tapped or detected, because it was solved from the
     * one landmark every ground has.
     */
    var cameraEnd by androidx.compose.runtime.saveable.rememberSaveable {
        mutableStateOf(com.haraan.app.vision.CameraEnd.BOWLER)
    }
    var stumpCalTapping by remember { mutableStateOf(false) }

    /*
     * AUTO: FILMING WITH NOBODY SCORING.
     *
     * In the nets there is no scorer to tap BALL. With Auto on, the phone films in rolling
     * segments of up to [REVIEW_CLIP_MS] and watches every one for a delivery. A segment
     * with no ball in it is thrown away unseen; one with a ball is cut a beat after the
     * ball is done, kept, and runs the whole after-the-ball sequence — then the next
     * segment starts by itself once the replays are over.
     *
     * [autoSegment] says whether the clip now recording is one of those segments; it is
     * set by the auto loop at the moment it arms, never by the scorer's cue or the button.
     */
    var autoMode by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(false) }
    var autoSegment by remember { mutableStateOf(false) }
    var armingAuto by remember { mutableStateOf(false) }
    var deliveryFlash by remember { androidx.compose.runtime.mutableIntStateOf(0) }
    var stumpCalTaps by remember { mutableStateOf<List<com.haraan.app.vision.Point2>>(emptyList()) }
    var stumpQuad by remember { mutableStateOf<com.haraan.app.vision.PitchQuad?>(null) }
    var calibrationNote by remember { mutableStateOf<String?>(null) }
    var wicketTaps by remember { mutableStateOf<List<com.haraan.app.vision.Point2>>(emptyList()) }

    /*
     * The last delivery's numbers, worked out once when the clip ends.
     *
     * The same moment and the same reason as [lastBounce] beside it: the whole track is
     * needed, and during the delivery there is nothing to say. What is new is that the
     * wicket lock goes in with the quad, which is what lets a speed and a wicket projection
     * exist on a ground where no quad was ever found.
     */
    var lastMetrics by remember { mutableStateOf<com.haraan.app.vision.FlightMetrics?>(null) }

    /*
     * THE BALL, CALLED DONE BEFORE THE CLIP IS.
     *
     * The clip runs until the scorer taps the result or the ten-second cap, and the numbers
     * used to wait for it — up to nine seconds after a ball that was over in one. The
     * analyser now decides from the track itself ([com.haraan.app.vision.FlightEnd]) and
     * freezes the flight here; the numbers are worked out from this copy at once, while the
     * clip keeps recording for the scorer's review.
     *
     * Null while the ball is still in the air, and once set the ball tracker stops being fed
     * for the rest of the clip: what it would see next is the ball after the bat, and the
     * metrics have already been taken.
     */
    var calledFlight by remember { mutableStateOf<List<com.haraan.app.vision.BallSighting>?>(null) }
    /*
     * THE BALL IS READ BEFORE IT IS SHOWN. When a flight is called the result is held back for
     * [RESULT_AFTER_MS] while "Reading the ball…" shows on the live picture: the tracker keeps
     * looking (a later, longer track replaces a false start), and in Auto the saved clip is
     * tracked again at its full rate. Then the replay plays with whatever is best by then.
     * [analysingSince] is when the reading started (uptime ms), null when nothing is being read.
     */
    var analysingSince by remember { mutableStateOf<Long?>(null) }
    // The saved clip's own track of this ball, when its re-analysis landed while it was read.
    var refinedTrack by remember { mutableStateOf<List<com.haraan.app.vision.BallSighting>?>(null) }

    /*
     * The 3D replay of the last ball. Opens on its own when a ball is called and a 3D
     * flight could be fitted (pitch corners marked, bounce found); the next ball closes
     * it, so it never stands between the operator and a delivery.
     */
    var showReplay by remember { mutableStateOf(false) }

    /*
     * AFTER A BALL, IN ORDER: the path on the real picture, then the 3D replay, and the clip
     * itself on a tap.
     *
     * [arPath] is the delivery drawn in this camera's own picture — from the fitted 3D flight
     * when the pitch corners are marked, from the raw sightings when not. [replayClip] is a
     * private copy of the delivery's clip, kept for the slow-motion replay because the
     * upload queue moves and deletes the original on its own schedule.
     */
    var arPath by remember { mutableStateOf<com.haraan.app.vision.ArPath?>(null) }
    // The after-ball replay — the delivery at speed, the result, then the stadium — and the
    // frames it is played from; see DeliveryReplaySequence.
    val replayFrames = remember { ReplayFrameRing() }
    DisposableEffect(Unit) { onDispose { replayFrames.release() } }
    var liveSequence by remember { mutableStateOf<LiveReplay?>(null) }
    // The last ball's result, left on the live picture until the next ball; see LiveResultOverlay.
    var liveResult by remember { mutableStateOf<LiveReplay?>(null) }
    // Fulltrack-style setup: line both sets of stumps up in boxes. Opens by itself on the
    // behind-the-bowler camera, where the stumps are the whole calibration.
    var setupAligning by remember { mutableStateOf(session.role == com.haraan.app.data.MatchDeviceRole.LBW_REVIEW) }
    // The phone's tilt when Setup was done, for telling a knocked tripod from a batter in the way.
    var setupTilt by remember { mutableStateOf<com.haraan.app.vision.DeviceTilt.Reading?>(null) }
    var setupNote by remember { mutableStateOf<String?>(null) }
    var showAr by remember { mutableStateOf(false) }
    var replayClip by remember { mutableStateOf<ReplayClip?>(null) }
    var showVideo by remember { mutableStateOf(false) }
    /*
     * When the recording began on the CAMERA's clock — the clock every sighting carries — so
     * the replay can line the path up with the ball in the video. Set from the first analysed
     * frame after the recorder reports it has started: right to within a frame.
     */
    val pendingClipStart = remember { java.util.concurrent.atomic.AtomicBoolean(false) }
    // Developer: write the next analysed frame's raw planes to disk, for desk replay.
    // A burst of consecutive frames: the ball tracker works on frame DIFFERENCES, so one
    // frame alone cannot replay it.
    val dumpFramesLeft = remember { java.util.concurrent.atomic.AtomicInteger(0) }
    var lastDumpPath by remember { mutableStateOf<String?>(null) }
    val dumpContext = LocalContext.current
    val dumpDir = remember { File(dumpContext.getExternalFilesDir(null), "frames") }
    var clipStartSensorMs by remember { mutableStateOf<Double?>(null) }

    // The striker's hand, for the line card. Set by the operator; this phone is never told.
    var batterHand by androidx.compose.runtime.saveable.rememberSaveable {
        mutableStateOf(com.haraan.app.vision.BatterHand.RIGHT)
    }

    /*
     * ROTATION, now that the Activity survives one.
     *
     * A use case's target rotation is fixed at bind time. A turn of the phone used to tear
     * this whole screen down and rebuild it, so the new orientation arrived with the new
     * binding and nobody had to think about this. Keeping the session alive across a turn
     * means nobody rebinds — and a recorder still aimed at the old rotation writes the
     * delivery sideways into the clip the scorer opens.
     *
     * The analyser's rotation matters as much and shows less: ImageInfo.rotationDegrees is
     * derived from it, and both engines now turn their frames by it. Left stale, it would
     * put the pitch outline and the ball trail back a quarter turn out of the picture —
     * precisely the failure the coordinate work removed.
     *
     * The located pitch goes with it. Those corners were found in the old orientation and
     * describe nothing in this one; dropping them costs a second of re-detection and
     * avoids drawing a confident outline somewhere there is no pitch. Corners set by hand
     * go too, for the same reason and with more certainty — a person tapped them at a
     * picture that is no longer on screen.
     */
    LaunchedEffect(configuration, videoCapture, analysisUseCase) {
        val rotation = view.display?.rotation ?: return@LaunchedEffect
        videoCapture?.targetRotation = rotation
        analysisUseCase?.targetRotation = rotation
        pitchDetector.reset()
        pitchQuad = null
        tappedCorners = emptyList()
        tapping = false
        // Solved in the old orientation: describes nothing in this one.
        stumpQuad = null
        stumpCalTaps = emptyList()
        stumpCalTapping = false
        /*
         * Auto-detected wicket is reset on rotation so it immediately re-detects upright
         * in the new orientation/aspect ratio without carrying stale rotated coordinates.
         * Manual locks placed by hand are preserved.
         */
        if (wicketLock?.source != com.haraan.app.vision.WicketLockSource.MANUAL) {
            wicketTracker.reset()
            farWicketSearch.reset()
            wicketLock = null
            wicketDiagnostics = wicketTracker.diagnostics()
        }
        cameraMotion.reset()
        wicketTaps = emptyList()
        wicketTapping = false
        // Placed in the old orientation: describes nothing in this one.
        bowlersEndPinned = false
        adjusting = false
    }

    DisposableEffect(Unit) {
        onDispose {
            vision.release()
            pitchDetector.release()
            stumpDetector.release()
            cameraMotion.release()
            analysisExecutor.shutdown()
        }
    }

    val analyzer = remember {
        /*
         * ONE BUFFER, REUSED.
         *
         * The luma plane was copied into a brand new ByteArray on every single frame —
         * a quarter of a megabyte, thirty times a second, most of it for frames neither
         * engine was going to look at. That is a garbage collector running flat out for
         * the length of a match inside a phone already holding a camera, an encoder and a
         * Hough transform, and heat is the only thing it produces.
         *
         * Declared in here rather than beside the other state on purpose: it belongs to
         * the analyzer, it is only ever touched on the single-threaded analysis executor,
         * and a `remember` in the composable body would hand each recomposition a fresh
         * one while the analyzer quietly kept the first.
         */
        var lumaScratch: ByteArray? = null
        var lumaFrame = 0
        /** The U (blue-difference) plane, for the colour pass. Same one-buffer rule. */
        var chromaScratch: ByteArray? = null

        /** The turn the previous frame arrived at, so a rotation can be told from a pan. */
        var lastTurn: Int? = null
        /** Camera-clock ms of the last frame on which the detector saw anything at all. */
        var lastSightingMs = 0L
        var lastAnalysisErrorLogMs = 0L
        var lastGateLogMs = 0L
        var idleAnalysisFrame = 0
        var lastLiveFrameMs = Long.MIN_VALUE / 2

        ImageAnalysis.Analyzer { image ->
            try {
                if (dumpFramesLeft.get() > 0) {
                    dumpFramesLeft.decrementAndGet()
                    lastDumpPath = runCatching { dumpFrame(image, dumpDir) }.getOrElse { "dump failed: ${it.message}" }
                }
                val plane = image.planes.getOrNull(0)
                if (plane != null) {
                    // A quarter turn swaps the picture's width and height. Both engines
                    // report inside the upright frame, so that is the shape to draw into.
                    // Free: read off the frame's metadata, not its pixels.
                    if (pendingClipStart.compareAndSet(true, false)) {
                        clipStartSensorMs = image.imageInfo.timestamp / 1_000_000.0
                    }
                    // About thirty looked at a second whatever the camera runs at: a 60 fps
                    // session is for the CLIP, which is tracked again at full rate once saved.
                    val stampMs = image.imageInfo.timestamp / 1_000_000L
                    if (stampMs - lastLiveFrameMs in 0 until LIVE_MIN_FRAME_MS) return@Analyzer
                    lastLiveFrameMs = stampMs
                    // While a ball is being filmed, a small upright copy of each frame looked
                    // at, for the replay straight after it.
                    if (trackingLive) {
                        // A video frame comes upright already; the ring recycles what it is given.
                        val filmed = image as? VideoFeedFrame
                        runCatching { filmed?.picture?.copy(android.graphics.Bitmap.Config.ARGB_8888, false) ?: image.toBitmap() }.getOrNull()
                            ?.let { replayFrames.add(stampMs.toDouble(), it, if (filmed != null) 0 else image.imageInfo.rotationDegrees) }
                    }
                    val turn = ((image.imageInfo.rotationDegrees % 360) + 360) % 360
                    val sideways = turn == 90 || turn == 270
                    val frameW = if (sideways) image.height else image.width
                    val frameH = if (sideways) image.width else image.height
                    if (frameH > 0) uprightAspect = frameW.toFloat() / frameH.toFloat()
                    if (cameraToFirstFrameMs == null) {
                        cameraToFirstFrameMs = android.os.SystemClock.elapsedRealtime() - screenOpenedAt
                    }
                    if (uprightWidthPx != frameW) {
                        uprightWidthPx = frameW
                        cameraIntrinsics = if (image is VideoFeedFrame) {
                            VideoCameraFeed.INTRINSICS
                        } else {
                            lens?.intrinsics(image.width, image.height, sideways)
                                ?: com.haraan.app.vision.CameraIntrinsics.assumed(70.0, uprightAspect)
                        }
                    }

                    /*
                     * IS THERE ANYTHING TO LOOK FOR?
                     *
                     * The camera runs from the moment somebody joins until they walk off
                     * the ground — through the innings break, through a bowler's long
                     * walk back, through an hour of nothing. The ball is in flight for
                     * maybe a second of each delivery, and the pitch is found once.
                     *
                     * Every frame outside those windows used to be copied out of the
                     * hardware buffer in full before the code got round to deciding it had
                     * no use for it. Deciding first is most of the thermal saving on this
                     * screen, and it costs nothing that was ever worth having.
                     */
                    /*
                     * THE TURN IS ANNOUNCED BEFORE THE FRAME IS LOOKED AT.
                     *
                     * A quarter turn rotates the whole scene, so every correspondence in it
                     * agrees with every other one and the motion estimator would fit a
                     * confident nonsense translation to the lot. The tracker maps its
                     * anchor through the turn exactly instead, and the estimator restarts
                     * its difference from this frame.
                     */
                    val previousTurn = lastTurn
                    if (previousTurn != null && previousTurn != turn) {
                        wicketTracker.onRotation((turn - previousTurn) / 90, uprightAspect)
                    }
                    lastTurn = turn

                    val trackingNow = trackingLive
                    val lookingForPitch = !trackingNow &&
                        pitchQuad == null &&
                        // Corners set by hand outrank a detected quad, so once there are
                        // four of them the Hough transform is looking for an answer that
                        // has already been given.
                        tappedCorners.size < 4 &&
                        stumpQuad == null
                    // Vision yields to heat. Filming is the product; this is bolted to the
                    // side of it, and a phone that throttles its encoder mid-delivery has
                    // lost the thing the operator is actually standing there to do.
                    /*
                     * Unlike the pitch, the wicket is worth looking for on every idle
                     * frame, not only until it is found once.
                     *
                     * A quad is four corners of a painted rectangle and does not change; a
                     * wicket lock is a live claim with a state, and the whole value of the
                     * state machine is that it keeps re-deciding — re-acquiring after the
                     * batter walks across it, dropping when the phone is repointed. A lock
                     * that stopped being checked the moment it was found would be the
                     * twelve-frame hold this replaced, wearing better clothes.
                     */
                    val lookingForWicket = !trackingNow && stumpDetector.available
                    // Once a second: which gates are open. The first thing to read when the
                    // readout says the detector has seen no frames at all.
                    val gateNow = android.os.SystemClock.elapsedRealtime()
                    if (gateNow - lastGateLogMs > 1_000L) {
                        lastGateLogMs = gateNow
                        android.util.Log.i(
                            ANALYSIS_TAG,
                            "gates tracking=$trackingNow thermal=${if (thermalThrottled) "hot" else if (thermalVeryHot) "very-hot" else if (thermalWarm) "warm" else "ok"} opencv=${stumpDetector.available} " +
                                "pitch=$lookingForPitch wicket=$lookingForWicket auto=$autoSegment " +
                                "frame=${image.width}x${image.height}@${image.imageInfo.rotationDegrees} " +
                                "trackerFrames=${wicketTracker.diagnostics().framesSeen} " +
                                "ground=${groundSummary(wicketLock, cameraIntrinsics, uprightWidthPx, uprightAspect)} " +
                                "shutter=${shutter.status} ballDetail=${vision.detail()}",
                        )
                        // While filming: what the ball tracker is doing, as it does it.
                        if (trackingNow) {
                            val b = vision.diagnostics()
                            android.util.Log.i(
                                ANALYSIS_TAG,
                                "ball frames=${b.framesSeen} withCandidate=${b.framesWithCandidate} " +
                                    "points=${vision.track().size} state=${b.trackingState} " +
                                    "ms avg=%.1f max=${b.maxProcessingMs} ".format(b.averageProcessingMs) +
                                    "rej motion=${b.rejectedGlobalMotion} size=${b.rejectedSize} shape=${b.rejectedShape} " +
                                    "traj=${b.rejectedTrajectory} still=${b.rejectedStationary} cluster=${b.rejectedCluster} " +
                                    "called=${calledFlight != null}",
                            )
                            /*
                             * Finer ball analysis only while the phone keeps up. A frame the
                             * tracker is still chewing on is a frame KEEP_ONLY_LATEST drops, and
                             * a missed frame costs more than a sharper one gains.
                             */
                            if ((vision.detail() > 1.0 && b.framesSeen >= 45 && b.averageProcessingMs > BALL_BUDGET_MS) ||
                                (vision.detail() > 1.0 && thermalWarm)
                            ) {
                                vision.setDetail(1.0)
                                android.util.Log.i(
                                    ANALYSIS_TAG,
                                    "ball detail stepped down to 1.0 (avg %.1f ms, warm=$thermalWarm)".format(b.averageProcessingMs),
                                )
                            }
                        }
                    }
                    if (thermalThrottled || (!trackingNow && !lookingForPitch && !lookingForWicket)) {
                        return@Analyzer
                    }

                    val buffer = plane.buffer
                    buffer.rewind()
                    val needed = buffer.remaining()
                    val bytes = lumaScratch?.takeIf { it.size == needed }
                        ?: ByteArray(needed).also { lumaScratch = it }
                    buffer.get(bytes)
                    lumaFrame++
                    if (lumaFrame % LUMA_SAMPLE_EVERY == 0) shutter.onLuma(sampledMean(bytes), System.currentTimeMillis())
                    val uPlane = image.planes.getOrNull(1)
                    val chroma = uPlane?.let { up ->
                        val ub = up.buffer
                        ub.rewind()
                        val n = ub.remaining()
                        val out = chromaScratch?.takeIf { it.size == n } ?: ByteArray(n).also { chromaScratch = it }
                        ub.get(out)
                        out
                    }

                    /*
                     * One frame, one job.
                     *
                     * The two detectors never run on the same frame. Finding the pitch is a
                     * Hough transform over the whole image and is much the heavier of the
                     * two - but a pitch does not move while a ball is in the air, and the
                     * ball is the measurement that cannot be redone. Spending analysis time
                     * re-finding a stationary rectangle mid-delivery would cost frames of
                     * the one thing there is only one chance to see.
                     */
                    /*
                     * CAMERA MOTION RUNS ON EVERY ANALYSED FRAME, INCLUDING DURING A
                     * DELIVERY, and it is the only thing here that does.
                     *
                     * It is cheap — a hundred corners followed at 320 wide, a couple of
                     * milliseconds — and it is what keeps the wicket lock attached to the
                     * ground while the heavy detector is switched off for the delivery. A
                     * lock that stopped being carried the moment the ball was bowled would
                     * be stale at exactly the moment its scale is used.
                     */
                    val cameraMove = cameraMotion.onFrame(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = image.imageInfo.rotationDegrees,
                    )

                    if (trackingNow) {
                        /*
                         * The wicket is CARRIED, not missed.
                         *
                         * The detector is deliberately not run during a delivery — see the
                         * one-frame-one-job note below — and handing the tracker a null
                         * sighting would be telling it the wicket was looked for and not
                         * found, which would drop a perfectly good lock eight frames into
                         * every single ball.
                         */
                        wicketLock = wicketTracker.carry(cameraMove, uprightAspect)

                        /*
                         * A CALL IS NOT THE END OF LOOKING.
                         *
                         * Once a flight was called the tracker used to stop for the rest of
                         * the clip. On a real phone a person walking past the stumps made a
                         * five-point "ball" of a swinging hand, it was called, and a real ball
                         * thrown a moment later could never be tracked. The tracker keeps
                         * running; a LATER, separate flight that is at least as long replaces
                         * the call below — the same flight running on past its bounce does not.
                         */

                        // The camera's own monotonic clock, never the UI clock: ball
                        // motion timing has to come from when the sensor saw it.
                        val frameMs = image.imageInfo.timestamp / 1_000_000

                        /*
                         * An Auto segment runs for most of a session, not for one ball, so
                         * the wicket would never be looked at again. Every fourth frame with
                         * no ball about, the detector gets a turn - at the held place only,
                         * unless nothing is held: a full-resolution patch costs a millisecond
                         * or two, and a whole-frame search does not belong beside a ball
                         * tracker.
                         */
                        idleAnalysisFrame++
                        val ballQuiet = latestBall?.let { frameMs - it.timestampMs > 1_500 } ?: true
                        if (autoSegment && ballQuiet && idleAnalysisFrame % 4 == 0 && stumpDetector.available) {
                            val sightings = stumpDetector.detectCandidates(
                                y = bytes,
                                width = image.width,
                                height = image.height,
                                yRowStride = plane.rowStride,
                                rotationDegrees = image.imageInfo.rotationDegrees,
                                u = chroma,
                                uRowStride = uPlane?.rowStride ?: 0,
                                uPixelStride = uPlane?.pixelStride ?: 1,
                                creases = pitchDetector.creases(),
                                lookForStones = wicketLock?.kind != com.haraan.app.vision.WicketKind.STUMPS,
                                focus = wicketTracker.focus(),
                                allowSearch = wicketLock == null || idleAnalysisFrame % 16 == 0,
                            )
                            val step = com.haraan.app.vision.WicketStep.feed(
                                sightings, cameraIntrinsics, bowlersEndBase, bowlersEndFilter, farWicketSearch, wicketTracker, steadyWicket,
                                bytes, image.width, image.height, plane.rowStride, image.imageInfo.rotationDegrees,
                                cameraMove, uprightAspect, frameMs,
                            )
                            wicketLock = step.lock
                            if (!bowlersEndPinned) bowlersEndBase = step.bowlersEnd
                            steadyDrawLock = steadyWicket.value()
                        }
                        // From behind the bowler, look only along the delivery's corridor and
                        // expect the ball to head for the batter's stumps. See BallStep.
                        ballStep.refreshCorridor(steadyWicket.value(), wicketLock, cameraIntrinsics, uprightWidthPx, uprightAspect, frameMs)
                        corridorRef.set(ballStep.corridor)
                        val step = ballStep.frame(
                            luma = bytes,
                            width = image.width,
                            height = image.height,
                            rowStride = plane.rowStride,
                            // The same turn the pitch detector is given. Without it the
                            // trail was drawn in the sensor's frame and the corridor in the
                            // viewer's, a quarter turn apart on every portrait phone.
                            rotationDegrees = image.imageInfo.rotationDegrees,
                            timestampMs = frameMs,
                            lock = wicketLock,
                            aspect = uprightAspect,
                        )
                        val sighting = step.sighting
                        if (sighting != null) {
                            trackedPoints = vision.track().size
                            latestBall = sighting
                            /*
                             * A delivery is over in a quarter of a second. Once one is called,
                             * its trail stays on screen; a stray few points afterwards (an arm,
                             * a bird, the ball rolling back) must not wipe it. A new track takes
                             * the screen once it is long enough to be a delivery itself.
                             */
                            val live = vision.track()
                            val held = calledFlight
                            if (held == null || held.isEmpty() || live.isEmpty() ||
                                (live.first().timestampMs > held.last().timestampMs && live.size >= SUPERSEDE_MIN_POINTS)
                            ) {
                                ballTrail = live.takeLast(CAMERA_TRAIL_POINTS)
                            }
                            trackQuality = vision.quality()
                        }
                        // Done, but never came at the stumps: a person, not a ball. Let go
                        // before it is drawn as a delivery, and keep watching.
                        if (step.strayDropped) {
                            ballTrail = emptyList()
                            latestBall = null
                            trackedPoints = 0
                        }
                        step.called?.let { flight ->
                            calledFlight = flight
                            ballTrail = flight.takeLast(CAMERA_TRAIL_POINTS)
                        }
                    } else {
                        // Pitch detector is throttled to once every 4 frames so the Hough
                        // transform does not starve CPU from stump detection. Stump detector
                        // runs on every frame at full 25+ FPS for instant lock-on.
                        idleAnalysisFrame++
                        // Warm phone: the Hough search is the heaviest thing here and the least
                        // needed — the stumps give the ground on their own.
                        if (lookingForPitch && !thermalWarm && (idleAnalysisFrame % 4 == 0)) {
                            pitchDetector.detect(
                                luma = bytes,
                                width = image.width,
                                height = image.height,
                                rowStride = plane.rowStride,
                                rotationDegrees = image.imageInfo.rotationDegrees,
                            )?.let { pitchQuad = it }
                        }

                        val wicketEvery = when {
                            thermalVeryHot -> 4
                            thermalWarm -> 2
                            else -> 1
                        }
                        if (lookingForWicket && idleAnalysisFrame % wicketEvery == 0) {
                            /*
                             * HOW HARD TO LOOK, BY WHAT IS ALREADY KNOWN.
                             *
                             * A match is hours of a phone in the sun, and the wicket does not
                             * move. Once a lock is confirmed and has held, the detector checks
                             * it on one frame in three, at its own place only - a comb fit on
                             * a full-resolution patch, a millisecond or two - and camera
                             * motion carries it between. Coasting behind the striker, the
                             * patch is checked every frame and the whole frame only every
                             * fourth. Nothing held: the whole frame, every frame.
                             */
                            val held = wicketLock
                            val steady = held != null &&
                                held.state == com.haraan.app.vision.WicketTrackState.CONFIRMED &&
                                held.heldFrames >= STEADY_LOCK_FRAMES
                            /*
                             * NOTHING IN VIEW: SEARCH LESS OFTEN.
                             *
                             * Pointed at the pavilion through an innings break, the cold
                             * search — both polarities, a dozen comb fits, the stone pass —
                             * ran on every frame for as long as nothing was found. After a
                             * few quiet seconds it runs on one frame in three; the first
                             * sighting of anything puts it straight back to every frame.
                             */
                            val frameClockMs = image.imageInfo.timestamp / 1_000_000
                            if (lastSightingMs == 0L) lastSightingMs = frameClockMs
                            val idle = held == null && frameClockMs - lastSightingMs > SEARCH_BACKOFF_AFTER_MS
                            if (idle && idleAnalysisFrame % SEARCH_BACKOFF_EVERY != 0) {
                                wicketLock = wicketTracker.carry(cameraMove, uprightAspect)
                            } else if (steady && idleAnalysisFrame % STEADY_CHECK_EVERY != 0) {
                                wicketLock = wicketTracker.carry(cameraMove, uprightAspect)
                            } else {
                                val allowSearch = when {
                                    held == null -> true
                                    held.source == com.haraan.app.vision.WicketLockSource.MANUAL -> false
                                    held.state == com.haraan.app.vision.WicketTrackState.TENTATIVE -> true
                                    held.state == com.haraan.app.vision.WicketTrackState.TEMPORARILY_LOST ->
                                        idleAnalysisFrame % 4 == 0
                                    else -> false
                                }
                                val sightings = stumpDetector.detectCandidates(
                                    y = bytes,
                                    width = image.width,
                                    height = image.height,
                                    yRowStride = plane.rowStride,
                                    rotationDegrees = image.imageInfo.rotationDegrees,
                                    u = chroma,
                                    uRowStride = uPlane?.rowStride ?: 0,
                                    uPixelStride = uPlane?.pixelStride ?: 1,
                                    creases = pitchDetector.creases(),
                                    // A stumps lock is never served by a stone; skipping that
                                    // search is most of this stage's per-frame cost.
                                    lookForStones = held?.kind != com.haraan.app.vision.WicketKind.STUMPS,
                                    focus = wicketTracker.focus(),
                                    allowSearch = allowSearch,
                                )
                                if (sightings.isNotEmpty()) lastSightingMs = frameClockMs
                                val step = com.haraan.app.vision.WicketStep.feed(
                                    sightings, cameraIntrinsics, bowlersEndBase, bowlersEndFilter, farWicketSearch, wicketTracker, steadyWicket,
                                    bytes, image.width, image.height, plane.rowStride, image.imageInfo.rotationDegrees,
                                    cameraMove, uprightAspect, frameClockMs,
                                )
                                wicketLock = step.lock
                                if (!bowlersEndPinned) bowlersEndBase = step.bowlersEnd
                                steadyDrawLock = steadyWicket.value()
                            }
                            val now = System.currentTimeMillis()
                            if (now < validationUntilMs) {
                                val d = wicketTracker.diagnostics()
                                validation.record(
                                    trueDistanceM = validationTrueM,
                                    timestampMs = image.imageInfo.timestamp / 1_000_000,
                                    lock = wicketLock,
                                    camera = cameraIntrinsics,
                                    uprightWidthPx = uprightWidthPx,
                                    rollDeg = d.rollDeg,
                                    timeToReadyMs = d.timeToReadyMs,
                                )
                            }
                            if (now - lastDiagnosticsMs >= 200L) {
                                wicketDiagnostics = wicketTracker.diagnostics()
                                if (showAdminPanel) stumpReport = stumpDetector.report()
                                if (validationUntilMs != 0L && now >= validationUntilMs) {
                                    validationUntilMs = 0L
                                    validationRows = validation.summary()
                                }
                                lastDiagnosticsMs = now
                            }
                        }
                    }
                }
            } catch (t: Throwable) {
                /*
                 * Never let analysis break the camera — but never hide why it stopped, either.
                 * This used to swallow everything silently, so a detector that failed on every
                 * frame looked exactly like one that simply had not found the stumps. Logged
                 * at most every two seconds, and shown in the developer readout.
                 */
                val nowMs = android.os.SystemClock.elapsedRealtime()
                if (nowMs - lastAnalysisErrorLogMs > 2_000L) {
                    lastAnalysisErrorLogMs = nowMs
                    android.util.Log.w(ANALYSIS_TAG, "frame analysis failed", t)
                    analysisError = "${t.javaClass.simpleName}: ${t.message ?: ""}".take(160)
                }
            } finally {
                image.close()
            }
        }
    }
    var activeRecording by remember { mutableStateOf<Recording?>(null) }
    // The scorer's BALL that the clip now recording belongs to; null for a clip from this
    // phone's own button with no ball in play.
    var clipBallSeq by remember { mutableStateOf<Int?>(null) }
    // Set when the scorer calls the ball dead mid-recording: the clip is thrown away when
    // it finalises instead of being sent.
    var discardClip by remember { mutableStateOf(false) }
    // The last ball this phone filmed (by cue or by hand), so one BALL is filmed once — a
    // clip that hit the ten-second cap while the ball is still "in play" is not re-armed.
    var lastFilmedSeq by remember { androidx.compose.runtime.mutableIntStateOf(0) }
    // When the clip now recording was armed, on the uptime clock — the age of an Auto segment.
    var clipArmedAtMs by remember { androidx.compose.runtime.mutableLongStateOf(0L) }
    // The latest cue, for the manual button: pressed during a ball the cue missed, its
    // clip still carries that ball's number and REVIEW finds it.
    var latestCue by remember { mutableStateOf<com.haraan.app.data.BallCue?>(null) }
    // armDelivery is built further down, inside the layout; the cue loop reaches it here.
    val armRef = remember { mutableStateOf<((Int?) -> Unit)?>(null) }

    DisposableEffect(Unit) { onDispose { executor.shutdown() } }

    /*
     * THE SCORER'S BALL, FILMED FROM HERE.
     *
     * The scorer taps BALL as the bowler runs in; this phone starts recording without anyone
     * touching it, and stops when the scorer taps the result. Only deliveries are filmed,
     * never the gaps between them. A ball called dead is recorded and then thrown away.
     *
     * A second, not a push: the camera is a guest phone with no account and no socket, and
     * the ten-second clip is the review window, so a second of latency still catches the
     * run-up. The heartbeat below stays the judge of whether the pairing is alive — a
     * missed cue is just a missed tick. The phone's own button keeps working throughout, as
     * the backup when the two phones lose each other.
     */
    LaunchedEffect(session.sessionToken) {
        if (session.sessionToken == "debug_token") return@LaunchedEffect
        while (true) {
            val cue = repo.cue(session.sessionToken)
            if (cue != null) {
                latestCue = cue
                val wanted = if (cue.inPlay) cue.seq else null
                val filming = clipBallSeq
                // The ball this clip belongs to is over: result in, or called dead.
                if (recording && filming != null && filming != wanted) {
                    if (cue.seq == filming && cue.cancelled) discardClip = true
                    activeRecording?.stop()
                    activeRecording = null
                }
                /*
                 * The scorer's BALL while Auto is filming a segment of its own. That used
                 * to be ignored — the phone was "already recording" — and the result tap
                 * could not end a clip that belonged to no ball, so with Auto on the
                 * scorer lost the camera altogether. A young segment becomes this ball's
                 * clip (it already holds the run-up); an old one would hit the ten-second
                 * cap mid-delivery, so it is cut and the next tick films the ball afresh.
                 */
                if (recording && autoSegment && filming == null && wanted != null && wanted != lastFilmedSeq) {
                    if (android.os.SystemClock.uptimeMillis() - clipArmedAtMs <= ADOPT_SEGMENT_MAX_AGE_MS) {
                        autoSegment = false
                        clipBallSeq = wanted
                        lastFilmedSeq = wanted
                    } else {
                        activeRecording?.stop()
                        activeRecording = null
                    }
                }
                // A new ball is being bowled and nothing is filming it yet.
                if (!recording && wanted != null && wanted != lastFilmedSeq) {
                    armRef.value?.invoke(wanted)
                }
            }
            // Tighter while filming, so the stop lands close to the result tap.
            delay(if (recording) CUE_FILMING_MS else CUE_IDLE_MS)
        }
    }

    LaunchedEffect(Unit) { if (!granted) requestCameraPermission { granted = it } }

    LaunchedEffect(pendingQuality, recording) {
        val next = pendingQuality ?: return@LaunchedEffect
        if (!recording) {
            videoQuality = next
            pendingQuality = null
        }
    }
    LaunchedEffect(session.sessionToken) { session.clipKeepHours?.let { uploadQueue.setKeepHours(it) } }

    // Check in on a cadence matched to cricket, not to a chat app. Losing the pairing is
    // reported here rather than discovered when an upload fails.
    LaunchedEffect(session.sessionToken) {
        /*
         * A missed beat is not a lost pairing.
         *
         * This used to drop the camera out of the match on the FIRST null — so one blip
         * on ground Wi-Fi, or one slow response from a backend still warming up, ended a
         * session that was perfectly healthy and sent somebody back to the scorer for a
         * new pairing code.
         *
         * Three consecutive misses at a twenty-second cadence is about a minute of real
         * silence, which is long enough to be a genuine disconnection and short enough
         * that the scorer sees it before wondering why no clips are arriving. The screen
         * shows "reconnecting" in between rather than pretending nothing is wrong.
         */
        if (session.sessionToken == "debug_token") {
            live = true
            while (true) {
                delay(30_000)
            }
        }
        var missed = 0
        while (true) {
            val beat = repo.heartbeat(session.sessionToken)
            if (beat == null) {
                missed++
                // Said in the hand on the way down, once. Losing the scorer is the one
                // thing that happens on this screen with no gesture behind it, and the
                // operator has no reason to be looking when it does.
                if (live) view.performHapticFeedback(Feel.REMOVE)
                live = false
                if (missed >= MAX_MISSED_HEARTBEATS) {
                    onDropped()
                    return@LaunchedEffect
                }
                // Back off a little before retrying, but well inside the drop window.
                delay(5_000)
                continue
            }
            // And once on the way back, distinct from the drop: a tick, not a rejection.
            if (!live) view.performHapticFeedback(Feel.TICK)
            missed = 0
            live = true
            score = beat.score
            overs = beat.overs
            // The admin's retention for the gallery, applied to what is kept already.
            beat.clipKeepHours?.let { uploadQueue.setKeepHours(it) }
            beat.videoQuality?.let { if (it != videoQuality) pendingQuality = it }
            beat.cameraAccess?.let { next ->
                // Felt in the hand the moment a booked slot opens, as "the ball is on" is.
                if (next.allowsAt(System.currentTimeMillis()) && trackingAccess?.allowsAt(System.currentTimeMillis()) == false) {
                    view.performHapticFeedback(Feel.TICK)
                }
                trackingAccess = next
                clockMs = System.currentTimeMillis()
            }
            delay(20_000)
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        if (granted) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    PreviewView(context).also { view ->
                        /*
                         * FIT, not the default FILL.
                         *
                         * FILL_CENTER crops whatever does not fit the view, so the picture
                         * on screen was a centre crop of the picture the analyser measured
                         * and the recorder saved. Three different rectangles, one set of
                         * coordinates drawn across all of them. Letterboxing costs a band
                         * of black and buys an overlay that lands where the ball is - and
                         * it also stops the viewfinder from promising a wider shot than
                         * the clip the scorer will actually receive.
                         */
                        view.scaleType = PreviewView.ScaleType.FIT_CENTER
                        previewView = view
                    }
                },
            )
            /*
             * A VIDEO IN PLACE OF THE LENS (debug): the camera stays bound — the shutter still
             * records, so the clip flow runs — but its frames are dropped and the video's are
             * analysed instead, and shown over the viewfinder.
             */
            val feed = remember(videoFeed) { videoFeed?.let { VideoCameraFeed(it, analyzer, analysisExecutor) } }
            if (feed != null) {
                androidx.compose.runtime.DisposableEffect(feed) {
                    feed.start()
                    onDispose { feed.stop() }
                }
                VideoFeedPicture(feed)
            }
            // Bound here rather than in the view's factory, so a new quality from /control
            // rebinds the same view instead of needing a new screen.
            LaunchedEffect(previewView, videoQuality) {
                val target = previewView ?: return@LaunchedEffect
                bindCamera(
                    ctx,
                    target,
                    lifecycleOwner,
                    analysisExecutor,
                    if (feed != null) ImageAnalysis.Analyzer { it.close() } else analyzer,
                    videoQuality,
                    shutter,
                ) { capture, analysis, fps ->
                    videoCapture = capture
                    analysisUseCase = analysis
                    recordingFps = fps
                }
            }
        } else {
            CameraAccessNeeded(
                hasCameraPermission = hasCameraPermission,
                requestCameraPermission = requestCameraPermission,
                onGranted = { granted = true },
            )
        }

        // Status, top: which role this phone is playing and whether the match still
        // knows about it.
        // THE AIMING GUIDE.
        //
        // The person holding this phone is the only one who can fix a bad angle, and by
        // the time a scorer opens the clip they are three overs away. So the frame says
        // what a useful shot looks like: stumps upright in the lower middle, the pitch
        // running away up the frame.
        //
        // These are the same four reference points a homography will need later, so the
        // habit this builds is the habit calibration will depend on.
        val tappedQuad = remember(tappedCorners) {
            if (tappedCorners.size < 4) {
                null
            } else {
                com.haraan.app.vision.PitchQuad(
                    corners = tappedCorners,
                    source = com.haraan.app.vision.QuadSource.TAPPED,
                    confidence = 1f,
                ).takeIf { it.isPlausible() }
            }
        }
        val found = stumpQuad ?: tappedQuad ?: pitchQuad?.copy(cameraEnd = cameraEnd)
        // READY: the wicket is locked and the length guide is laid on the real ground.
        val guideReady by remember {
            androidx.compose.runtime.derivedStateOf {
                wicketLock?.let { it.state != com.haraan.app.vision.WicketTrackState.TENTATIVE } == true
            }
        }
        if (showGuide && granted && found != null) {
            /*
             * FOUND. The guide is now the pitch, not a picture of one: drawn from the
             * corners the detector located, in that pitch's own perspective.
             *
             * The corridor inside it is placed in METRES and pushed back through the
             * homography. Drawn in screen space it would be a trapezium that merely looks
             * about right; drawn this way it is visibly wrong the moment the calibration is,
             * which is the only reason to draw it at all.
             */
            Canvas(Modifier.fillMaxSize()) {
                val frame = frameRect(size.width, size.height, uprightAspect)
                fun px(point: com.haraan.app.vision.Point2) =
                    frame.at(point.x.toFloat(), point.y.toFloat())

                fun quadPath(points: List<Offset>) = Path().apply {
                    moveTo(points[0].x, points[0].y)
                    points.drop(1).forEach { lineTo(it.x, it.y) }
                    close()
                }

                drawPath(
                    quadPath(found.corners.map { px(it) }),
                    Color(0xFF4ADE80).copy(alpha = 0.8f),
                    style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round),
                )

                found.toImage()?.let { toImage ->
                    val half = com.haraan.app.vision.PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * 0.35
                    // From the quad's own calibration, so this cannot disagree with the
                    // corners it is being drawn inside — or with the end it was tapped at.
                    val near = found.calibration[0].y
                    val far = found.calibration[2].y

                    val band = listOf(
                        com.haraan.app.vision.Point2(-half, near),
                        com.haraan.app.vision.Point2(half, near),
                        com.haraan.app.vision.Point2(half, far),
                        com.haraan.app.vision.Point2(-half, far),
                    ).map { px(toImage.map(it)) }

                    // A corner on the horizon maps to NaN by design. Skip the band rather
                    // than let a broken calibration paint something confident.
                    if (band.none { it.x.isNaN() || it.y.isNaN() }) {
                        drawPath(quadPath(band), Color(0xFF6E9BF5).copy(alpha = 0.20f))
                    }
                }
            }
        } else if (showGuide && granted && !guideReady) {
            // A fixed aiming funnel, for before anything is found. Once the wicket locks, the
            // length guide is drawn on the real ground and this generic shape — which knows
            // nothing about where the pitch is — would only contradict it.
            Canvas(Modifier.fillMaxSize()) {
                val guide = Color.White.copy(alpha = 0.34f)
                val stroke = Stroke(width = 2.dp.toPx(), cap = StrokeCap.Round)

                // Proportioned against the PICTURE, like everything else drawn over it:
                // an aiming guide that overhangs the letterbox is telling somebody to put
                // the stumps where the camera cannot see.
                val frame = frameRect(size.width, size.height, uprightAspect)

                // The pitch, narrowing with distance.
                // Proportioned for a phone on a tripod behind the bowler's arm. The first
                // pass ran the pitch from 40% to 86% of the height, which read as a tall
                // funnel and put the near crease directly behind the record button - the
                // one part of the screen a thumb is always covering.
                val nearY = frame.top + frame.height * 0.74f
                val farY = frame.top + frame.height * 0.36f
                val nearHalf = frame.width * 0.32f
                val farHalf = frame.width * 0.075f
                val cx = frame.left + frame.width / 2f

                drawPath(
                    Path().apply {
                        moveTo(cx - nearHalf, nearY)
                        lineTo(cx - farHalf, farY)
                        moveTo(cx + nearHalf, nearY)
                        lineTo(cx + farHalf, farY)
                    },
                    guide,
                    style = stroke,
                )

                // Popping crease, near end.
                drawLine(
                    guide,
                    Offset(cx - nearHalf, nearY),
                    Offset(cx + nearHalf, nearY),
                    strokeWidth = 2.dp.toPx(),
                    cap = StrokeCap.Round,
                )
                // And far end, where the stumps should sit.
                drawLine(
                    guide,
                    Offset(cx - farHalf, farY),
                    Offset(cx + farHalf, farY),
                    strokeWidth = 2.dp.toPx(),
                    cap = StrokeCap.Round,
                )

                // Three stumps at the far crease — the thing to line the phone up on.
                // Tall enough to aim at. The stumps are the thing being lined up, so they
                // have to be the most legible part of the guide, not a detail.
                val stumpH = frame.height * 0.075f
                listOf(-1, 0, 1).forEach { i ->
                    val x = cx + i * (farHalf * 0.62f)
                    drawLine(
                        guide,
                        Offset(x, farY),
                        Offset(x, farY - stumpH),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
            }
        }

        // WHAT THE DETECTOR IS SEEING, live.
        //
        // This existed only in the debug field-test mode, which is the wrong place for it:
        // the operator is the one who can correct the aim, and without this they cannot
        // tell the tracker is following a fielder's shirt until somebody reviews the clip
        // long afterwards. Observed points only — nothing here is interpolated.
        if (granted && (ballTrail.isNotEmpty() || latestBall != null)) {
            Canvas(Modifier.fillMaxSize()) {
                val frame = frameRect(size.width, size.height, uprightAspect)

                /*
                 * THE TRAIL, AGED.
                 *
                 * Thirty points in one flat colour and one flat width is a scatter plot;
                 * the eye has to work out which end is the ball. Fading and thinning with
                 * age makes the direction of travel the first thing read instead of
                 * something to be deduced, which matters because the operator is glancing
                 * at this between watching a bowler run in.
                 *
                 * Drawn segment by segment, since one Path cannot vary alpha along itself.
                 *
                 * STRAIGHT segments, deliberately - no smoothing, no spline. The jitter in
                 * this line is the signal: a trail that shivers is a tracker following a
                 * fielder's shirt, and a curve fitted through the points would tidy away
                 * exactly the evidence this overlay exists to show. The same reason the
                 * tracker itself interpolates nothing.
                 *
                 * AND COLOURED BY HOW WELL EACH POINT BEHAVED.
                 *
                 * Age says which end is the ball. Colour says whether to believe the line
                 * at all — see [trackColour]. A tracker locked onto the ball draws in blue;
                 * one picking round-ish bits of a moving fielder draws the same shape in
                 * amber, and the difference is visible from the boundary without reading a
                 * word. That is the one question this overlay exists to answer, and until
                 * now it was answered by counting dots.
                 */
                fun age(i: Int) = i.toFloat() / (ballTrail.size - 1).coerceAtLeast(1)
                fun faded(point: com.haraan.app.vision.BallSighting, age: Float) =
                    trackColour(point.trackingConfidence)
                        .copy(alpha = TRAIL_FADE_FROM + (TRAIL_FADE_TO - TRAIL_FADE_FROM) * age)

                for (i in 1 until ballTrail.size) {
                    // Each segment takes the colour of the point it arrives at: it draws
                    // the step that reached there, so it is that step being judged.
                    drawLine(
                        faded(ballTrail[i], age(i)),
                        frame.at(ballTrail[i - 1].x, ballTrail[i - 1].y),
                        frame.at(ballTrail[i].x, ballTrail[i].y),
                        strokeWidth = (1.2f + 1.6f * age(i)).dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
                ballTrail.forEachIndexed { i, point ->
                    drawCircle(
                        faded(point, age(i)),
                        radius = (1.6f + 1.9f * age(i)).dp.toPx(),
                        center = frame.at(point.x, point.y),
                    )
                }
                latestBall?.let { point ->
                    val o = frame.at(point.x, point.y)
                    // On the same ramp as the rest, not a fixed green. A green head on an
                    // amber trail reads as "found it" at the exact moment the tracker is
                    // least sure, which is the wrong thing to say the loudest. The white
                    // ring is what marks this as the newest point.
                    drawCircle(trackColour(point.trackingConfidence), radius = 8.dp.toPx(), center = o)
                    drawCircle(
                        Color.White,
                        radius = 13.dp.toPx(),
                        center = o,
                        style = Stroke(width = 1.5.dp.toPx()),
                    )
                }
            }
        }

        /*
         * THE WICKET, DRAWN FROM THE LOCK.
         *
         * Not from the detector's newest sighting, which is the change worth being explicit
         * about. A sighting is one frame's opinion, and three bars that happen to line up
         * for one frame — a bat, a pad and a boot; three palings past a gap in the
         * sightscreen — used to be painted with exactly the confidence of a wicket seen for
         * a minute. The lock has survived multi-frame confirmation with the phone's own
         * movement subtracted, and when it is coasting rather than seen it is drawn dashed
         * and fading, which is a picture nobody can mistake for a detection.
         *
         * Above the trail and below the bounce marker: it is the landmark the trail is
         * measured against, and the bounce is the one thing on this screen with a position
         * in metres.
         */
        /*
         * READ THE LOCK WHILE DRAWING, NOT WHILE COMPOSING.
         *
         * The analyser replaces the lock on every frame (its confidence and anchor move a
         * little each time). Read here in composition, that invalidated the whole of
         * CameraMode — two thousand lines of layout — thirty times a second on the main
         * thread, while the phone was also recording. Read inside the Canvas, it only
         * invalidates the draw of these two layers.
         */
        if (granted && showAdminPanel) {
            Canvas(Modifier.fillMaxSize()) {
                val lock = wicketLock ?: return@Canvas
                drawWicketRegion(
                    lock = lock,
                    box = com.haraan.app.vision.FrameBox.letterbox(size.width, size.height, uprightAspect),
                    label = com.haraan.app.vision.wicketRegionLabel(
                        lock, cameraIntrinsics, uprightWidthPx, wicketDiagnostics.rollDeg,
                    ),
                    density = this,
                )
            }
        }
        /*
         * THE LENGTH GUIDE IS LAID DOWN, NOT SWITCHED ON.
         *
         * When the wicket locks, the bands sweep out from the stumps towards the camera over
         * most of a second, with a light tick in the hand as each one lands — the guide
         * arrives the way paint goes down a pitch, and the operator feels it happen while
         * looking at the stumps rather than the phone. Coasting behind the striker does not
         * replay it; only a fresh lock does.
         */
        val guideReveal = remember { androidx.compose.animation.core.Animatable(0f) }
        // The app's display face (Archivo Black) for the names painted on the pitch.
        val guideFontContext = LocalContext.current
        val guideTypeface = remember {
            runCatching { androidx.core.content.res.ResourcesCompat.getFont(guideFontContext, R.font.archivo_black) }.getOrNull()
        }
        LaunchedEffect(guideReady) {
            if (guideReady) {
                guideReveal.snapTo(0f)
                var landed = 0
                guideReveal.animateTo(
                    1f,
                    androidx.compose.animation.core.tween(900, easing = androidx.compose.animation.core.FastOutSlowInEasing),
                ) {
                    val band = (value * GUIDE_REVEAL_TICKS).toInt()
                    if (band > landed) {
                        landed = band
                        view.performHapticFeedback(Feel.TICK)
                    }
                }
            } else {
                guideReveal.snapTo(0f)
            }
        }
        if (granted) {
            Canvas(Modifier.fillMaxSize()) {
                val lock = drawingLock(wicketLock ?: return@Canvas, steadyDrawLock)
                val frameW = uprightWidthPx
                drawWicketLock(
                    lock = lock,
                    box = com.haraan.app.vision.FrameBox.letterbox(
                        size.width,
                        size.height,
                        uprightAspect,
                    ),
                    density = this,
                    camera = cameraIntrinsics,
                    framePx = if (frameW > 0 && uprightAspect > 0f) frameW to (frameW / uprightAspect).toInt() else null,
                    guideReveal = guideReveal.value,
                    // Clear of the shutter row and the hint line above it.
                    safeBottomY = size.height * GUIDE_SAFE_BOTTOM,
                    guideTypeface = guideTypeface,
                    bowlersEnd = bowlersEndBase,
                )
            }
        }

        /*
         * PLACING THE WICKET BY HAND.
         *
         * Two taps, at the base of each outer stump. The detector will fail on a wicket in
         * shadow, behind a batter, or made of a stone with a bail-coloured chip in it, and a
         * calibration that only works when the detector agrees is not a calibration. A
         * person who can see the wicket can always point at it.
         *
         * Its own overlay and its own taps rather than a mode on the corner flow, because
         * the two are answering different questions and a shared "tapping" flag would let an
         * interrupted corner sequence finish as a wicket.
         */
        if (wicketTapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(uprightAspect) {
                        detectTapGestures { offset ->
                            val box = com.haraan.app.vision.FrameBox.letterbox(
                                size.width.toFloat(),
                                size.height.toFloat(),
                                uprightAspect,
                            )
                            // A tap on a letterbox bar is outside the analysed frame, where
                            // no detector can ever agree with it.
                            if (!box.contains(offset)) return@detectTapGestures
                            val next = wicketTaps + box.toFrame(offset)
                            wicketTaps = next
                            if (next.size >= 2) {
                                wicketTracker.lockManually(
                                    baseLeft = next[0],
                                    baseRight = next[1],
                                    // The optional third tap is the top of a stump, and it
                                    // buys the vertical scale. The two base points cannot
                                    // give it at any price: they are collinear, and a line
                                    // fixes distances only along itself.
                                    top = next.getOrNull(2),
                                    kind = com.haraan.app.vision.WicketKind.STUMPS,
                                    frameAspect = uprightAspect,
                                )
                                wicketLock = wicketTracker.lock()
                                wicketDiagnostics = wicketTracker.diagnostics()
                            }
                            // Told by feel, because the person doing this is looking at a
                            // wicket rather than at the phone.
                            view.performHapticFeedback(if (next.size >= 2) Feel.COMMIT else Feel.TICK)
                            if (next.size >= 3) {
                                wicketTapping = false
                                wicketTaps = emptyList()
                            }
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val box = com.haraan.app.vision.FrameBox.letterbox(
                        size.width,
                        size.height,
                        uprightAspect,
                    )
                    wicketTaps.forEachIndexed { i, point ->
                        val o = box.toView(point)
                        drawCircle(Color(0xFFBDD3FF), radius = 7.dp.toPx(), center = o)
                        drawCircle(
                            Color.Black.copy(alpha = 0.6f),
                            radius = 7.dp.toPx(),
                            center = o,
                            style = Stroke(width = 1.5.dp.toPx()),
                        )
                        if (i > 0) {
                            drawLine(
                                Color(0xFFBDD3FF).copy(alpha = 0.75f),
                                box.toView(wicketTaps[i - 1]),
                                o,
                                strokeWidth = 2.dp.toPx(),
                            )
                        }
                    }
                }
            }
        }

        /*
         * WHERE IT PITCHED.
         *
         * Drawn flat on the grass rather than as a floating dot: a ring and a cross, at the
         * one point in the whole delivery the app is entitled to say it knows the position
         * of. If it sits where the ball landed, the calibration is good; if it sits in the
         * covers, it is not — which is worth more to the operator than the number is.
         */
        lastBounce?.let { bounce ->
            if (granted) {
                Canvas(Modifier.fillMaxSize()) {
                    val frame = frameRect(size.width, size.height, uprightAspect)
                    val at = frame.at(bounce.image.x.toFloat(), bounce.image.y.toFloat())
                    val radius = 11.dp.toPx()

                    drawCircle(
                        Color.Black.copy(alpha = 0.45f),
                        radius = radius + 1.5.dp.toPx(),
                        center = at,
                        style = Stroke(width = 3.5.dp.toPx()),
                    )
                    drawCircle(
                        BounceMark,
                        radius = radius,
                        center = at,
                        style = Stroke(width = 2.dp.toPx()),
                    )
                    val arm = radius * 0.55f
                    drawLine(
                        BounceMark,
                        Offset(at.x - arm, at.y),
                        Offset(at.x + arm, at.y),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                    drawLine(
                        BounceMark,
                        Offset(at.x, at.y - arm),
                        Offset(at.x, at.y + arm),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
            }
        }

        if (tapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(uprightAspect) {
                        detectTapGestures { offset ->
                            /*
                             * Normalised against the PICTURE, not the view.
                             *
                             * The homography is solved in the analysis frame's 0..1 space,
                             * so a corner has to be recorded there too. Dividing by the
                             * view's size instead - which is what this did - handed the
                             * solver four points from a different rectangle, and produced
                             * a calibration that solves perfectly and reads every length
                             * wrong. That is the worst kind of wrong: it looks fine.
                             */
                            val frame = frameRect(
                                size.width.toFloat(),
                                size.height.toFloat(),
                                uprightAspect,
                            )
                            // A tap on a letterbox bar is not a tap on the pitch.
                            if (!frame.contains(offset)) return@detectTapGestures
                            val local = frame.normalise(offset)
                            val point = com.haraan.app.vision.Point2(
                                local.x.toDouble(),
                                local.y.toDouble(),
                            )
                            val next = tappedCorners + point
                            tappedCorners = next
                            // Each corner lands, and the fourth one closes the shape. Told
                            // by feel because the person doing this is looking at a pitch.
                            view.performHapticFeedback(if (next.size >= 4) Feel.COMMIT else Feel.TICK)
                            if (next.size >= 4) tapping = false
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val frame = frameRect(size.width, size.height, uprightAspect)
                    tappedCorners.forEachIndexed { i, point ->
                        val o = frame.at(point.x.toFloat(), point.y.toFloat())
                        drawCircle(Color(0xFFFACC15), radius = 7.dp.toPx(), center = o)
                        drawCircle(
                            Color.Black.copy(alpha = 0.6f),
                            radius = 7.dp.toPx(),
                            center = o,
                            style = Stroke(width = 1.5.dp.toPx()),
                        )
                        if (i > 0) {
                            val previous = tappedCorners[i - 1]
                            drawLine(
                                Color(0xFFFACC15).copy(alpha = 0.7f),
                                frame.at(previous.x.toFloat(), previous.y.toFloat()),
                                o,
                                strokeWidth = 2.dp.toPx(),
                            )
                        }
                    }
                }
            }
        }

        if (stumpCalTapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(uprightAspect, cameraEnd) {
                        detectTapGestures { offset ->
                            // Normalised against the PICTURE, as every calibration tap must be.
                            val frame = frameRect(size.width.toFloat(), size.height.toFloat(), uprightAspect)
                            if (!frame.contains(offset)) return@detectTapGestures
                            val local = frame.normalise(offset)
                            val next = stumpCalTaps + com.haraan.app.vision.Point2(local.x.toDouble(), local.y.toDouble())
                            if (next.size < 6) {
                                stumpCalTaps = next
                                calibrationNote = null
                                view.performHapticFeedback(Feel.TICK)
                                return@detectTapGestures
                            }
                            val result = com.haraan.app.vision.StumpCalibration.solve(
                                com.haraan.app.vision.StumpCalibration.Taps(next[0], next[1], next[2], next[3], next[4], next[5]),
                                cameraEnd,
                                uprightAspect,
                            )
                            if (result == null || !result.trustworthy) {
                                // Kept in the flow: start the six again rather than leave.
                                stumpCalTaps = emptyList()
                                calibrationNote = "Those taps don't fit one camera — start again from the near stumps"
                                view.performHapticFeedback(Feel.REMOVE)
                            } else {
                                stumpQuad = result.quad
                                stumpCalTaps = emptyList()
                                stumpCalTapping = false
                                calibrationNote = "Calibrated · camera %.1f m up".format(result.cameraHeightM)
                                view.performHapticFeedback(Feel.COMMIT)
                            }
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val frame = frameRect(size.width, size.height, uprightAspect)
                    stumpCalTaps.forEachIndexed { i, point ->
                        val o = frame.at(point.x.toFloat(), point.y.toFloat())
                        val top = i % 3 == 2
                        drawCircle(Color(0xFF2563EB), radius = 7.dp.toPx(), center = o)
                        drawCircle(Color.White, radius = 7.dp.toPx(), center = o, style = Stroke(width = 1.8.dp.toPx()))
                        // A top is joined down to the feet it stands between.
                        if (top) {
                            val l = stumpCalTaps[i - 2]
                            val r = stumpCalTaps[i - 1]
                            val foot = frame.at(((l.x + r.x) / 2).toFloat(), ((l.y + r.y) / 2).toFloat())
                            drawLine(Color.White.copy(alpha = 0.8f), foot, o, strokeWidth = 2.dp.toPx())
                        } else if (i % 3 == 1) {
                            drawLine(
                                Color.White.copy(alpha = 0.8f),
                                frame.at(stumpCalTaps[i - 1].x.toFloat(), stumpCalTaps[i - 1].y.toFloat()),
                                o,
                                strokeWidth = 2.dp.toPx(),
                            )
                        }
                    }
                }
            }
        }

        /*
         * THE PANEL STANDS DOWN WHILE A WICKET IS BEING PLACED.
         *
         * Found on a phone with three stumps in frame: a Compose surface that handles its
         * own touches eats every tap inside it, so every tap aimed at a stump under the
         * chrome did nothing. While somebody is pointing at a wicket the whole picture IS
         * the control; the prompt and the way out are drawn separately below.
         */
        /*
         * THE CHROME, drawn the way a camera draws it.
         *
         * What was here before was a broadcast costume: a "HAWK-EYE CALIBRATED" badge (a
         * trademark, and a claim this screen's own header forbids), a hard-coded "60fps"
         * the recorder never asked for, a three-column chyron that read "-- MPH" until a
         * ball was measured — in miles, in a product that says km/h everywhere else — and
         * cyan and gold "machined" dials with knurled grooves. Every piece of it was
         * decoration pretending to be instrumentation.
         *
         * What replaces it is only what this phone actually knows: which role it is
         * playing, the score the scorer last sent, whether the wicket is locked, and —
         * while filming — how long it has been filming. One flat surface colour, no
         * gradient rims, no forever-pulsing lights.
         */
        if (!wicketTapping && !stumpCalTapping) {
            /*
             * READY IS A PROMISE TO THE PLAYER, SO IT DOES NOT FLICKER.
             *
             * Five tracker states are for the diagnostics panel. The person holding the
             * phone needs two: still looking, or ready. A confirmed lock coasting behind the
             * striker is still ready - the wicket has not gone anywhere and the numbers are
             * taken from the lock it is carrying - so it stays "Ready" rather than blinking
             * back to "Detecting" every time somebody walks past the stumps.
             */
            // Derived, so the chrome recomposes when READY flips — not on every frame's lock.
            val isReady by remember {
                androidx.compose.runtime.derivedStateOf {
                    val l = wicketLock
                    l != null && (
                        l.source == com.haraan.app.vision.WicketLockSource.MANUAL ||
                            l.state != com.haraan.app.vision.WicketTrackState.TENTATIVE
                        )
                }
            }

            // Said in the hand the moment the wicket locks — once, on the way in. Through the
            // Vibrator, not performHapticFeedback: MIUI and other skins mute View haptics when
            // the system "touch feedback" toggle is off, and the lock went unfelt.
            val lockContext = LocalContext.current
            LaunchedEffect(isReady) {
                if (isReady) com.haraan.app.ui.matches.cricketThud(lockContext, com.haraan.app.ui.matches.Thud.FOUR)
            }

            // ── Top: who this phone is, and the match it belongs to ──
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.weight(1f)) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Scrim)
                        .padding(start = 11.dp, end = 13.dp, top = 7.dp, bottom = 7.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // Green is a semantic here, not decoration: the scorer can hear us.
                    Box(
                        Modifier
                            .size(7.dp)
                            .clip(CircleShape)
                            .background(if (live) Good else Warn),
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(
                        session.roleLabel.ifBlank { "Match camera" },
                        color = Ink,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false),
                    )
                    // The score the scorer last sent — the one line on this screen no
                    // other camera app could print.
                    if (score.isNotBlank()) {
                        Text(
                            buildString {
                                append("  ·  ")
                                append(score)
                                if (overs.isNotBlank()) append(" ($overs)")
                            },
                            color = Ink.copy(alpha = 0.62f),
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            maxLines = 1,
                        )
                    }
                }
                }
                Spacer(Modifier.width(10.dp))
                AutoChip(
                    on = autoMode,
                    onToggle = {
                        autoMode = !autoMode
                        view.performHapticFeedback(Feel.SELECT)
                        // Off while an Auto segment is running: stop it, and let the finish
                        // drop it as it would any segment without a ball.
                        if (!autoMode && autoSegment && calledFlight == null) {
                            activeRecording?.stop()
                            activeRecording = null
                        }
                    },
                )
                Spacer(Modifier.width(8.dp))
                ChromeIconButton(
                    active = showAdminPanel,
                    description = "Wicket diagnostics",
                    onClick = { showAdminPanel = !showAdminPanel },
                ) { tint -> TuningGearGlyph(Modifier.size(16.dp), tint) }
                Spacer(Modifier.width(8.dp))
                ChromeIconButton(active = false, description = "Leave", onClick = onExit) { tint ->
                    ExitCrossGlyph(Modifier.size(13.dp), tint)
                }
            }

            // ── Under it: REC while filming, otherwise what the phone is waiting on ──
            Column(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(top = 62.dp, start = 14.dp, end = 14.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                val reading = analysingSince
                if (reading != null) {
                    ReadingBallChip(reading)
                } else if (recording) {
                    RecTimecode(recording = true, fps = recordingFps)
                } else {
                    // Two words for the player. Everything else is in the developer readout.
                    val (dot, words) = when {
                        thermalThrottled -> Warn to "Phone is hot · tracking paused"
                        isReady -> Good to "READY"
                        else -> Ink.copy(alpha = 0.45f) to "Detecting stumps…"
                    }
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(Scrim)
                            .padding(horizontal = 12.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(Modifier.size(6.dp).clip(CircleShape).background(dot))
                        Spacer(Modifier.width(7.dp))
                        Text(
                            words,
                            color = Ink.copy(alpha = 0.88f),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                        )
                    }
                }

                if (showAdminPanel) {
                    Spacer(Modifier.height(10.dp))
                    Column(
                        Modifier
                            .heightIn(max = 520.dp)
                            .verticalScroll(androidx.compose.foundation.rememberScrollState()),
                    ) {
                    WicketDiagnosticsPanel(
                        diagnostics = wicketDiagnostics,
                        lock = wicketLock,
                        report = stumpReport,
                        deviceRollDeg = deviceRoll,
                        extraRows = listOf(
                            "ground" to groundSummary(wicketLock, cameraIntrinsics, uprightWidthPx, uprightAspect),
                            "shutter" to shutter.status,
                            "ball res" to "×%.1f".format(vision.detail()),
                        ),
                        camera = cameraIntrinsics,
                        uprightWidthPx = uprightWidthPx,
                        cameraToFirstFrameMs = cameraToFirstFrameMs,
                        detectorAvailable = stumpDetector.available,
                        analysisError = analysisError,
                        modifier = Modifier
                            .widthIn(max = 300.dp)
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color.Black.copy(alpha = 0.82f))
                            .padding(12.dp),
                    )
                    Spacer(Modifier.height(8.dp))
                    Row(
                        Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(Color(0xFF2563EB))
                            .clickable { dumpFramesLeft.set(DUMP_BURST) }
                            .padding(horizontal = 12.dp, vertical = 8.dp),
                    ) {
                        Text("Dump $DUMP_BURST frames", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    }
                    lastDumpPath?.let {
                        Text(it, color = Color.White.copy(alpha = 0.6f), fontSize = 9.5.sp, fontFamily = FontFamily.Monospace)
                    }
                    Spacer(Modifier.height(8.dp))
                    val validationContext = LocalContext.current
                    com.haraan.app.vision.WicketValidationPanel(
                        trueDistanceM = validationTrueM,
                        onDistanceChange = { validationTrueM = it },
                        recording = validationUntilMs != 0L,
                        onRecord = {
                            validationSavedPath = null
                            validationUntilMs = System.currentTimeMillis() + VALIDATION_RECORD_MS
                        },
                        onSave = {
                            validationSavedPath = saveValidationCsv(validationContext, validation, cameraIntrinsics?.source)
                        },
                        onClear = {
                            validation.clear()
                            validationRows = emptyList()
                            validationSavedPath = null
                        },
                        rows = validationRows,
                        savedPath = validationSavedPath,
                        modifier = Modifier.widthIn(max = 300.dp),
                    )
                    }
                }
            }

            // ── The last ball's numbers, down the left edge ──
            if (!showAdminPanel) {
                DeliveryMetricsStack(
                    metrics = lastMetrics,
                    // Dim only while the ball is still in the air; once it is called, these
                    // are this ball's numbers even though the clip is still recording.
                    stale = recording && calledFlight == null,
                    showLine = session.role == com.haraan.app.data.MatchDeviceRole.LBW_REVIEW,
                    hand = batterHand,
                    onToggleHand = {
                        batterHand = if (batterHand == com.haraan.app.vision.BatterHand.RIGHT) {
                            com.haraan.app.vision.BatterHand.LEFT
                        } else {
                            com.haraan.app.vision.BatterHand.RIGHT
                        }
                    },
                    onOpenReplay = lastMetrics?.flight3d?.let { { showReplay = true } },
                    onWatchClip = replayClip?.let { { showVideo = true } },
                    ball = ball,
                    onBall = { next ->
                        ball = next
                        ctx.getSharedPreferences("camera_ball", android.content.Context.MODE_PRIVATE)
                            .edit().putString("ball", next.serverValue).apply()
                        // The ball already on screen re-reads at once, replay included.
                        liveResult?.let { r -> r.flight?.copy(ball = next)?.let { f -> liveResult = LiveReplay(r.frames, r.path, f.speedKmh, f, r.ballMs) } }
                    },
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .statusBarsPadding()
                        .displayCutoutPadding()
                        .padding(start = 14.dp, top = if (landscape) 62.dp else 108.dp),
                )
            }

            // ── The two tools, labelled, on the edge away from the shutter ──
            val railModifier = if (landscape) {
                Modifier
                    .align(Alignment.BottomStart)
                    .navigationBarsPadding()
                    .displayCutoutPadding()
                    .padding(start = 18.dp, bottom = 18.dp)
            } else {
                Modifier
                    .align(Alignment.BottomStart)
                    .navigationBarsPadding()
                    .padding(start = 22.dp, bottom = 44.dp)
            }
            val manual = wicketDiagnostics.lockSource == com.haraan.app.vision.WicketLockSource.MANUAL
            val tools: @Composable () -> Unit = {
                ToolButton(
                    label = "Guide",
                    active = showGuide,
                    onClick = { showGuide = !showGuide },
                ) { tint -> PitchGlyph(Modifier.size(20.dp), tint) }
                ToolButton(
                    label = "Setup",
                    active = setupAligning,
                    onClick = { setupAligning = !setupAligning },
                ) { tint -> CalibrateGlyph(Modifier.size(20.dp), tint) }
                // Something to put right once anything is known; tapping the feet before that.
                // Clearing a hand-placed lock is Adjust's Reset.
                val canAdjust = manual || wicketLock != null || bowlersEndBase != null
                ToolButton(
                    // Says what a tap will do, not what the state is called.
                    label = if (canAdjust) "Adjust" else "Wicket",
                    active = adjusting || manual,
                    onClick = {
                        wicketTaps = emptyList()
                        if (canAdjust) {
                            // The lines are what the rings are placed by.
                            showGuide = true
                            adjusting = true
                        } else {
                            wicketTapping = true
                        }
                    },
                ) { tint -> StumpsGlyph(Modifier.size(20.dp), tint) }
            }
            Row(railModifier, horizontalArrangement = Arrangement.spacedBy(14.dp)) { tools() }

            // ── The gallery, in the corner every camera keeps the last shot ──
            // Calibrate sits beside the gallery on the right: on a portrait phone the left
            // rail has room for two tools before it runs under the shutter.
            Row(
                if (landscape) {
                    Modifier
                        .align(Alignment.BottomEnd)
                        .navigationBarsPadding()
                        .displayCutoutPadding()
                        .padding(end = 26.dp, bottom = 18.dp)
                } else {
                    Modifier
                        .align(Alignment.BottomEnd)
                        .navigationBarsPadding()
                        .padding(end = 22.dp, bottom = 44.dp)
                },
                horizontalArrangement = Arrangement.spacedBy(14.dp),
                verticalAlignment = Alignment.Bottom,
            ) {
                ToolButton(
                    label = if (stumpQuad != null) "Re-calibrate" else "Calibrate",
                    active = stumpQuad != null,
                    onClick = {
                        stumpCalTaps = emptyList()
                        calibrationNote = null
                        stumpCalTapping = true
                    },
                ) { tint -> CalibrateGlyph(Modifier.size(20.dp), tint) }
                GalleryButton(
                    latest = galleryClips.firstOrNull(),
                    waitingCount = galleryClips.count { it.state != GalleryClip.State.SENT },
                    onClick = {
                        uploadQueue.refreshGallery()
                        showGallery = true
                    },
                )
            }
        }

        /*
         * FILMING, SAID BY THE EDGES OF THE SCREEN.
         *
         * The person holding this phone is watching the bowler, not the screen, so the start
         * of a clip has to reach the corner of their eye: the edges flash red twice, then
         * keep a slow breath for as long as the clip runs. The picture itself gets a thin red
         * line too, drawn on the footage rather than the letterbox.
         */
        if (granted) {
            RecordingEdgeGlow(recording = recording, uprightAspect = uprightAspect)
            DeliveryDetectedFlash(
                trigger = deliveryFlash,
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .padding(top = 64.dp),
            )
        }

        if (stumpCalTapping && granted) {
            StumpCalibrationPrompt(
                end = cameraEnd,
                tapsDone = stumpCalTaps.size,
                note = calibrationNote,
                onEnd = { end ->
                    cameraEnd = end
                    stumpCalTaps = emptyList()
                },
                onUndo = { stumpCalTaps = stumpCalTaps.dropLast(1) },
                onCancel = {
                    stumpCalTapping = false
                    stumpCalTaps = emptyList()
                    calibrationNote = null
                },
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(start = 16.dp, end = 16.dp, top = 52.dp),
            )
        } else if (calibrationNote != null && !recording) {
            // The result, briefly, where the prompt was.
            LaunchedEffect(calibrationNote) {
                delay(2_500)
                calibrationNote = null
            }
            Text(
                calibrationNote.orEmpty(),
                color = Ink,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .padding(top = 64.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(Color.Black.copy(alpha = 0.66f))
                    .padding(horizontal = 13.dp, vertical = 9.dp),
            )
        }

        // And what replaces it: the one instruction that matters, and the way out. Bottom
        // centre, clear of the picture, where a thumb already is.
        if (wicketTapping && granted) {
            Column(
                Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(
                    when (wicketTaps.size) {
                        0 -> "Tap the outside of one stump, at the ground"
                        1 -> "Now the far stump, at the ground"
                        else -> "Optional: tap the top of a stump"
                    },
                    color = Ink,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.Black.copy(alpha = 0.66f))
                        .padding(horizontal = 13.dp, vertical = 9.dp),
                )
                Spacer(Modifier.height(8.dp))
                Text(
                    "Cancel",
                    color = Color(0xFFBDD3FF),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.Black.copy(alpha = 0.66f))
                        .clickableCapture(enabled = true) {
                            wicketTapping = false
                            wicketTaps = emptyList()
                        }
                        .padding(horizontal = 16.dp, vertical = 9.dp),
                )
            }
        }

        /*
         * ARMING A DELIVERY.
         *
         * Lifted out of the button's modifier chain, where it used to live as eighty lines
         * of lambda nested inside a `.then()`. Separating the control from what it does is
         * what let the tap target grow to the whole disc without a line of this moving.
         *
         * An anonymous function rather than a lambda so the early return below reads as an
         * early return.
         */
        /*
         * THE MEASUREMENT, from one delivery's flight.
         *
         * Against the pitch as it was calibrated now, the one the ball was filmed on. Null is
         * the common answer for the bounce — no calibration, a full toss, too few points —
         * and simply means nothing is claimed.
         *
         * Both landmarks go into the metrics, and they answer different questions: the quad
         * is the only thing that can say how far UP the pitch the ball landed, and the
         * wicket lock is the only thing that can put a speed in km/h or a projection in
         * centimetres. The lock is read through [WicketLock.isMeasurable] inside, not
         * checked for null here: a lock that is coasting is still a lock and must not be
         * measured from.
         */
        // [replay]: play the after-ball replay for it — false when a result is only refined.
        val showFlight: (List<com.haraan.app.vision.BallSighting>, Boolean) -> Unit = { flight, replay ->
            /*
             * NO PITCH CORNERS? THE STUMPS ARE ENOUGH FOR A 3D FLIGHT.
             *
             * The 3D fit — and with it the path on the picture and the stadium replay — used
             * to need four marked crease corners, so on a ground with no paint (a backyard, a
             * worn gully pitch) a ball was tracked and then nothing followed it. The locked
             * stumps already give the camera: the lens, the Laws' stump size, and a camera
             * height assumed at chest level. Used only when there are no corners, and its
             * numbers are labelled as rough wherever they appear.
             */
            val measureLock = steadyWicket.value() ?: wicketLock
            val (quadFor3d, groundCamera) = flightCameras(found, measureLock, cameraIntrinsics, uprightWidthPx, uprightAspect, bowlersEndBase)
            lastMetrics = com.haraan.app.vision.FlightMetrics.of(flight, uprightAspect, quadFor3d, measureLock, groundCamera, ball).also { metrics ->
                // From the fitted flight when there is one — either end of the pitch.
                lastBounce = metrics.bounce
                // The path on the real picture first; the 3D replay follows it.
                val path = metrics.flight3d?.let { f3 ->
                    quadFor3d?.let { quad -> com.haraan.app.vision.ArPath.fromFlight(f3, quad, uprightAspect) }
                        ?: groundCamera?.let { cam -> com.haraan.app.vision.ArPath.fromFlight(f3, cam) }
                } ?: com.haraan.app.vision.ArPath.fromTrack(flight, uprightAspect, wicketLock, metrics.lbw)
                arPath = path
                val frames = flight.firstOrNull()?.let { replayFrames.since(it.timestampMs - ReplayFrameRing.LEAD_MS) }.orEmpty()
                when {
                    !replay -> liveResult?.let { old -> liveResult = LiveReplay(old.frames, path, metrics.flight3d?.speedKmh, metrics.flight3d, old.ballMs) }
                    // As a ball-tracking camera shows it: on the live picture, at once, and left
                    // there; the full replay is one tap away rather than forced on every ball.
                    !path.isEmpty -> liveResult = LiveReplay(frames, path, metrics.flight3d?.speedKmh, metrics.flight3d, flight.firstOrNull()?.timestampMs ?: 0L)
                    metrics.flight3d != null -> showReplay = true
                }
            }
        }
        // The analyser called the ball done mid-clip: the numbers land now, not when the
        // scorer gets round to the result.
        LaunchedEffect(calledFlight) {
            val flight = calledFlight ?: return@LaunchedEffect
            if (autoSegment) {
                /*
                 * WAS THAT A DELIVERY? With a calibrated pitch, only something that fits a
                 * ball bowled down it counts — a throw from the covers or a fielder walking
                 * past does not. A false alarm is let go and the segment keeps watching.
                 */
                // Only a pitch a PERSON marked can judge that: a detected one on a tiled or
                // lined floor threw real deliveries away as false alarms.
                val calibrated = found?.takeIf { it.source == com.haraan.app.vision.QuadSource.TAPPED }
                if (calibrated != null) {
                    val trial = com.haraan.app.vision.FlightMetrics.of(flight, uprightAspect, calibrated, wicketLock)
                    if (trial.flight3d == null) {
                        ballStep.reset()
                        ballTrail = emptyList()
                        latestBall = null
                        trackedPoints = 0
                        calledFlight = null
                        return@LaunchedEffect
                    }
                }
                deliveryFlash++
                view.performHapticFeedback(Feel.TICK)
            }
            if (autoSegment && recording) {
                // A beat after the ball for the clip, then the segment closes and is kept —
                // alongside the reading, so its full-rate re-analysis can land inside it.
                launch {
                    delay(AUTO_TAIL_MS)
                    activeRecording?.stop()
                    activeRecording = null
                }
            }
            // A newer, longer flight replacing this one restarts the reading (the effect is keyed on it).
            analysingSince = android.os.SystemClock.uptimeMillis()
            delay(RESULT_AFTER_MS)
            analysingSince = null
            // The saved clip's track when it saw more of the ball, else what the live tracker held.
            val best = refinedTrack?.takeIf { it.size > flight.size } ?: flight
            showFlight(best, true)
        }

        /*
         * THE AUTO LOOP. Starts the next segment whenever nothing else needs the screen: not
         * recording, no replay up, nobody tapping out a calibration.
         */
        LaunchedEffect(autoMode, recording, showAr, showReplay, showVideo, liveSequence, setupAligning, stumpCalTapping, wicketTapping, adjusting, videoCapture) {
            if (!autoMode || recording || showAr || showReplay || showVideo || liveSequence != null || setupAligning || stumpCalTapping || wicketTapping || adjusting) {
                return@LaunchedEffect
            }
            if (videoCapture == null || !granted) return@LaunchedEffect
            delay(AUTO_REARM_MS)
            armingAuto = true
            armRef.value?.invoke(null)
        }

        // `ballSeq`: the scorer's BALL that armed this clip, or null from the phone's own
        // button with no ball in play.
        val armDelivery: (Int?) -> Unit = fun(ballSeq: Int?) {
            val capture = videoCapture ?: return
            if (recording) return
            // Outside the owner's plan or booked slot the camera is paused: no clip, no tracking.
            if (!trackingAllowed) {
                if (ballSeq == null) view.performHapticFeedback(Feel.REMOVE)
                return
            }
            // An Auto segment only when the auto loop armed it; the scorer and the button
            // always make a clip that is kept.
            val wasAuto = armingAuto
            armingAuto = false
            autoSegment = wasAuto
            val filmedBefore = lastFilmedSeq
            if (ballSeq != null) lastFilmedSeq = ballSeq
            clipBallSeq = ballSeq
            clipArmedAtMs = android.os.SystemClock.uptimeMillis()
            discardClip = false
            uploadError = null
            // A fresh delivery: the previous track must not bleed into this one.
            ballStep.reset()
            trackedPoints = 0
            ballTrail = emptyList()
            latestBall = null
            trackQuality = TrackQuality.UNCERTAIN
            lastBounce = null
            lastMetrics = null
            calledFlight = null
            analysingSince = null
            refinedTrack = null
            showReplay = false
            showAr = false
            showVideo = false
            liveSequence = null
            replayFrames.clear()
            arPath = null
            replayClip = null
            refinedFps = null
            clipGeneration++
            val thisGeneration = clipGeneration
            clipStartSensorMs = null
            pendingClipStart.set(false)
            trackingLive = true
            recording = true
            /*
             * Felt the moment filming starts — however it started.
             *
             * Most clips are armed by the scorer's BALL with nobody touching this phone, and
             * those used to start in silence: the person holding it had to look to know. It
             * is the same rising swell the scorer's thumb feels on BALL, so the two phones
             * speak one language for "the ball is on".
             */
            scope.launch { com.haraan.app.ui.matches.cricketThud(ctx, com.haraan.app.ui.matches.Thud.DELIVERY) }
            val clipFile = uploadQueue.createClipFile()
            activeRecording = startClip(
                context = ctx,
                capture = capture,
                executor = executor,
                targetFile = clipFile,
                onStarted = { pendingClipStart.set(true) },
                onFinished = { file, durationMs ->
                    // The watchdog below already gave up on this clip, and a newer one may
                    // be recording: its state is not this clip's to reset.
                    if (thisGeneration != clipGeneration) {
                        runCatching { file.delete() }
                        return@startClip
                    }
                    // Read now, not at arming: the scorer's BALL can adopt a segment mid-way.
                    val stillAuto = autoSegment
                    recording = false
                    trackingLive = false
                    clipBallSeq = null
                    autoSegment = false
                    // An Auto segment nothing was bowled in: gone, without a buzz.
                    if (stillAuto && calledFlight == null) {
                        runCatching { file.delete() }
                        return@startClip
                    }
                    // The scorer called this ball dead: nothing was bowled worth keeping.
                    if (discardClip) {
                        discardClip = false
                        runCatching { file.delete() }
                        view.performHapticFeedback(Feel.REMOVE)
                        return@startClip
                    }
                    /*
                     * Usually already done: the ball was called over mid-clip and these
                     * are on screen. Worked out here only when it never was — a ball the
                     * tracker lost early, or a hot phone that paused tracking.
                     */
                    // Not while the ball is being read: the reading shows it when it is done.
                    if (lastMetrics == null && analysingSince == null) {
                        // A track still open at the end is shown only if it came at the stumps.
                        val open = vision.track().takeIf { t ->
                            val l = wicketLock
                            l == null || com.haraan.app.vision.FlightEnd.travelsToward(t, l, uprightAspect)
                        }
                        (calledFlight ?: open)?.let { showFlight(it, true) }
                    }
                    // The window closed. Nobody pressed stop, so this is the only way to
                    // know it is shut and the next delivery can be armed.
                    view.performHapticFeedback(Feel.TICK)
                    // Checked here so a clip that cannot be accepted never crosses ground
                    // Wi-Fi at all.
                    if (file.length() > MAX_REVIEW_BYTES) {
                        runCatching { file.delete() }
                        view.performHapticFeedback(Feel.REMOVE)
                        uploadError = "That clip is too large to send. Record a shorter delivery."
                        return@startClip
                    }
                    /*
                     * A private copy for the slow-motion replay, taken before the queue
                     * moves the file: only the latest delivery is ever kept, overwritten
                     * each ball, so it costs one clip of space and no clean-up.
                     */
                    val replayFile = File(ctx.cacheDir, "replay/last.mp4")
                    val copied = runCatching {
                        replayFile.parentFile?.mkdirs()
                        file.copyTo(replayFile, overwrite = true)
                    }.isSuccess
                    val pathForClip = arPath
                        ?: com.haraan.app.vision.ArPath.fromTrack(vision.track(), uprightAspect, wicketLock, lastMetrics?.lbw)
                    // With a video standing in for the lens the recording is of whatever the phone faces.
                    if (copied && !pathForClip.isEmpty && videoFeed == null) {
                        replayClip = ReplayClip(replayFile, clipStartSensorMs, pathForClip)
                    }
                    /*
                     * THE SAME BALL AGAIN, FROM THE SAVED CLIP AT ITS FULL RATE.
                     *
                     * The live analyser looks at ~30 frames a second and loses a far ball before
                     * it pitches; the clip has every frame the camera took (60 a second where the
                     * phone can). Tracked again over the stretch around the live flight, and shown
                     * instead when it saw more of the ball — a second or two after the ball, as
                     * a broadcast replay is. A copy is analysed: the queue moves the clip, and
                     * the replay copy is overwritten by the next ball.
                     */
                    val live = calledFlight ?: vision.track()
                    val startMs = clipStartSensorMs
                    if (videoFeed == null && copied && startMs != null && live.size >= com.haraan.app.vision.FlightEnd.MIN_FLIGHT_POINTS) {
                        val generation = clipGeneration
                        val corridorNow = corridorRef.get()
                        val lockNow = steadyWicket.value() ?: wicketLock
                        val aspectNow = uprightAspect
                        scope.launch {
                            val refined = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.Default) {
                                val copy = File(ctx.cacheDir, "replay/refine.mp4")
                                runCatching { replayFile.copyTo(copy, overwrite = true) }.getOrNull()?.let { f ->
                                    com.haraan.app.vision.ClipFlightAnalyzer().analyse(
                                        clip = f,
                                        clipStartSensorMs = startMs,
                                        fromSensorMs = live.first().timestampMs - com.haraan.app.vision.ClipFlightAnalyzer.LEAD_MS.toDouble(),
                                        toSensorMs = live.last().timestampMs + com.haraan.app.vision.ClipFlightAnalyzer.TAIL_MS.toDouble(),
                                        corridor = corridorNow,
                                        wicket = lockNow,
                                        uprightAspect = aspectNow,
                                    ).also { runCatching { f.delete() } }
                                }
                            }
                            android.util.Log.i(
                                ANALYSIS_TAG,
                                "clip re-analysis: live ${live.size} pts, clip ${refined?.track?.size} pts at ${refined?.fps?.let { "%.0f".format(it) }} fps over ${refined?.framesAnalysed} frames",
                            )
                            if (refined == null || generation != clipGeneration || recording) return@launch
                            if (refined.track.size <= live.size) return@launch
                            refinedFps = refined.fps
                            // Still being read: handed to the reading, which shows it.
                            if (analysingSince != null) {
                                refinedTrack = refined.track
                                return@launch
                            }
                            showFlight(refined.track, false)
                            arPath?.takeIf { !it.isEmpty }?.let { replayClip = ReplayClip(replayFile, startMs, it) }
                        }
                    }
                    /*
                     * Persisted in the review queue:
                     * The operator is free to film the next delivery immediately without
                     * waiting on cellular latency. Backlog drains in background with
                     * exponential backoff, deleting only upon confirmed 2xx landing.
                     */
                    uploadQueue.enqueue(
                        file = file,
                        sessionToken = session.sessionToken,
                        durationMs = durationMs,
                        overBall = overs.takeIf { it.isNotBlank() },
                        // The argument, not the state: by now the state may already name
                        // the next ball.
                        ballSeq = ballSeq,
                        // What this phone saw of the ball, so the scorer can draw the
                        // flight without asking a model to find it all over again.
                        trackJson = clipTrackJson(
                            vision.track(),
                            uprightAspect,
                            // Only from behind the bowler's arm: side-on, the stumps are not
                            // seen face-on and a projection across them means nothing.
                            wickets = lastMetrics?.lbw?.takeIf {
                                session.role == com.haraan.app.data.MatchDeviceRole.LBW_REVIEW
                            },
                        ),
                    )
                },
            )
            /*
             * Ends itself at the review ceiling.
             *
             * Ten seconds is what the server accepts, and it buys the run-up, the release,
             * the bounce, the shot and enough afterwards to see where the ball went -
             * which is the context a review actually needs. Nobody at a ground is watching
             * this screen to press stop, so the cap has to be the recorder's own.
             *
             * Slightly under the limit on purpose: the container rounds, and a clip that
             * measures 10.02s server-side would be refused after the upload had already
             * been paid for over ground Wi-Fi.
             */
            // Only THIS recording. The timer used to stop whatever was recording ten seconds
            // on — with the scorer's result now ending clips early, that could be the next
            // ball's clip, cut off a second in.
            val thisRecording = activeRecording
            /*
             * THE RECORDER REFUSED. Usually the previous clip still finalising, or the
             * camera rebinding. `recording` was already true, and nothing would ever have
             * set it back — no clip, so no Finalize — which left the phone deaf to every
             * later BALL and its own button greyed out. Undone instead, so the next cue
             * tick simply tries again.
             */
            if (thisRecording == null) {
                recording = false
                trackingLive = false
                clipBallSeq = null
                autoSegment = false
                lastFilmedSeq = filmedBefore
                if (ballSeq == null && !wasAuto) view.performHapticFeedback(Feel.REMOVE)
                return
            }
            scope.launch {
                delay(REVIEW_CLIP_MS)
                if (activeRecording === thisRecording) {
                    activeRecording?.stop()
                    activeRecording = null
                }
                /*
                 * A Finalize that never comes — the camera unbound under the recorder —
                 * would hold `recording` true for good, the same deafness as above. Past
                 * the cap and a grace, this clip is let go so the next ball can be filmed.
                 */
                delay(FINALIZE_GRACE_MS)
                if (recording && clipGeneration == thisGeneration) {
                    clipGeneration++
                    recording = false
                    trackingLive = false
                    clipBallSeq = null
                    autoSegment = false
                }
            }
        }
        armRef.value = armDelivery

        /*
         * THE CONTROL, on the short edge of whichever way the phone is being held.
         *
         * Portrait: along the bottom, under the thumb of a hand holding the phone up.
         * Landscape: against the right edge, vertically centred - where the stock camera
         * app puts a shutter, and where the right hand already is on a phone clamped to a
         * tripod side-on. A bottom-centre button in landscape sits under the middle of a
         * wide frame, which is both the hardest place on the screen to reach and directly
         * over the pitch.
         *
         * Landscape is the orientation this feature is actually used in - a phone filming
         * a cricket pitch from behind the bowler's arm is a phone on its side - and until
         * now turning it produced a portrait layout stretched across a wide screen.
         */
        /*
         * How the track is behaving, in one word, or in none.
         *
         * UNCERTAIN says nothing, because for most of a delivery it means "fewer than
         * three points so far" rather than anything being wrong, and a screen that opens
         * every capture by calling itself unreliable teaches the operator to ignore it.
         * The two words that do appear describe the TRACK — its gaps and its wobble — and
         * never whether the thing being tracked is the ball, which nothing here knows.
         */
        val trackWord = when (trackQuality) {
            TrackQuality.RELIABLE -> " · steady"
            TrackQuality.PARTIAL -> " · patchy"
            TrackQuality.UNCERTAIN -> ""
        }
        val pendingCount = queueStatus.pendingCount
        val clipsSent = queueStatus.clipsSent
        val isUploading = queueStatus.isUploading
        val queueError = queueStatus.lastError

        /*
         * WHERE THE LAST BALL PITCHED, big, above the shutter.
         *
         * Speed, spin and swing live in the cards on the left; length is the one number
         * measured in metres UP the pitch, and only when a pitch was calibrated, so it gets
         * its own place and appears only when it exists.
         */
        val readout: DeliveryReadout? = lastBounce?.takeIf { !recording || calledFlight != null }?.let { bounce ->
            DeliveryReadout(
                figure = "%.1f".format(bounce.lengthM),
                unit = "m",
                detail = "Pitched ${bounce.length.spoken}",
            )
        }
        val status = when {
            // The point count is the only honest signal of whether vision is doing
            // anything, and it belongs where the person filming can see it.
            recording && autoSegment && calledFlight == null -> "Auto · watching for a delivery"
            recording && calledFlight != null -> "Ball done · clip still recording for the scorer"
            recording && trackedPoints > 0 -> "Ball seen in $trackedPoints frames$trackWord"
            recording -> "Filming this ball"
            uploadError != null -> uploadError!!
            setupNote != null -> setupNote!!
            // The shown flight was tracked again from the saved clip at its full rate.
            refinedFps != null && live -> "Ball tracked from the %.0f fps clip".format(refinedFps)
            // Said out loud rather than left to a small coloured dot. Somebody holding
            // this phone at the boundary needs to know the difference between "idle" and
            // "the scorer has stopped hearing from me".
            !live -> "Reconnecting to the match…"
            // Paused: say when it opens again, if the owner has a slot later today.
            !trackingAllowed -> trackingAccess?.opensAtMs?.let { at ->
                "Camera opens at ${clockLabel(at)}" + (trackingAccess?.venue?.let { " · $it" } ?: "")
            } ?: trackingAccess?.untilMs?.let { "Camera paused · the booked slot ended at ${clockLabel(it)}" }
                ?: "Camera paused · needs Pro, Hero or a booked slot"
            // Said plainly, and said with what still works. A phone that has gone quiet
            // about the ball while the operator can see it is filming invites the guess
            // that the whole thing has broken.
            thermalThrottled -> "Still filming · tracking paused to cool down"
            queueError != null && pendingCount > 0 -> "Upload paused · $pendingCount pending"
            isUploading && pendingCount > 1 -> "Sending clip to scorer · $pendingCount queued"
            isUploading -> "Sending to the scorer…"
            pendingCount > 0 && clipsSent > 0 -> "$clipsSent sent · $pendingCount queued"
            pendingCount > 0 -> "$pendingCount queued · waiting to send"
            trackedPoints > 0 -> "$clipsSent sent · ball seen in $trackedPoints frames$trackWord"
            clipsSent > 0 -> "$clipsSent sent · films on the scorer's BALL"
            trackingAccess?.via == "booking" && trackingAccess?.untilMs != null && clipsSent == 0 ->
                "Camera on till ${clockLabel(trackingAccess!!.untilMs!!)} · films on BALL"
            else -> "Films on the scorer's BALL · or tap to film"
        }
        ShutterControl(
            modifier = Modifier
                .align(if (landscape) Alignment.CenterEnd else Alignment.BottomCenter)
                // The bars and the cutout move with the phone; asking for both lets the
                // insets decide which edge they are on this time.
                .navigationBarsPadding()
                .displayCutoutPadding()
                .padding(
                    end = if (landscape) 26.dp else 0.dp,
                    bottom = if (landscape) 0.dp else 30.dp,
                ),
            status = status,
            isError = uploadError != null || (queueError != null && pendingCount > 0),
            recording = recording,
            canFilm = granted && videoCapture != null && !recording && trackingAllowed,
            landscape = landscape,
            // The backup: a tap during a ball the cue missed still files the clip under
            // that ball, so the scorer's REVIEW finds it.
            readout = readout,
            // The camera's own answer, the moment the ball is done — the same one the
            // scorer's REVIEW opens with. Behind the bowler's arm only.
            wickets = lastMetrics?.lbw?.takeIf {
                (!recording || calledFlight != null) &&
                    session.role == com.haraan.app.data.MatchDeviceRole.LBW_REVIEW
            },
            onArm = { armDelivery(latestCue?.takeIf { it.inPlay }?.seq) },
        )

        /*
         * THE REPLAY PLAYS ITSELF, ONCE, a moment after the result lands on the live picture:
         * long enough to see the answer where the ball was bowled, then the delivery at speed
         * and the stadium. Keyed to the ball, so a result refined from the saved clip does not
         * start it again; the Replay button plays it again on demand.
         */
        LaunchedEffect(liveResult?.ballMs) {
            val r = liveResult ?: return@LaunchedEffect
            delay(AUTO_REPLAY_AFTER_MS)
            if (liveResult?.ballMs == r.ballMs && liveSequence == null) liveSequence = liveResult
        }
        // The last ball's result stays until the next ball is seen in flight.
        LaunchedEffect(trackedPoints, recording, calledFlight) {
            if (recording && trackedPoints > 0 && calledFlight == null) liveResult = null
        }
        // A ball being filmed takes the screen back: the person holding the phone must see
        // that it is recording, and the viewfinder is what they aim with.
        LaunchedEffect(recording) { if (recording) showGallery = false }
        val livePath = arPath
        if (showAr && livePath != null) {
            LivePathOverlay(
                path = livePath,
                uprightAspect = uprightAspect,
                onHandOff = { if (lastMetrics?.flight3d != null) showReplay = true },
                onDone = { showAr = false },
            )
        }
        if (setupAligning && granted && !recording) {
            val guide = com.haraan.app.vision.StumpAlignGuide.guide(cameraIntrinsics?.focalFw ?: 1.4, uprightAspect)
            val far = wicketLock?.takeIf {
                it.kind == com.haraan.app.vision.WicketKind.STUMPS && it.state != com.haraan.app.vision.WicketTrackState.LOST
            }?.anchor?.base
            StumpAlignSetup(
                guide = guide,
                uprightAspect = uprightAspect,
                farSeen = far,
                nearSeen = bowlersEndBase?.base,
                onDone = { farFound, nearFound ->
                    /*
                     * Both sets known at once: the detector's reading where it has one inside
                     * the box, the box itself where it has not — the person put them there.
                     * Either way the batter's stumps are HELD from here, as Fulltrack's
                     * calibration is: a batter standing in front of them no longer loses them.
                     */
                    if (!farFound || !wicketTracker.pinCurrent()) {
                        wicketTracker.lockManually(
                            baseLeft = guide.far.baseLeft,
                            baseRight = guide.far.baseRight,
                            top = com.haraan.app.vision.Point2(guide.far.centreX, guide.far.topY),
                            kind = com.haraan.app.vision.WicketKind.STUMPS,
                            frameAspect = uprightAspect,
                        )
                    }
                    wicketLock = wicketTracker.lock()
                    wicketDiagnostics = wicketTracker.diagnostics()
                    setupTilt = com.haraan.app.vision.DeviceTilt.steady()
                    setupNote = null
                    bowlersEndPinned = false
                    if (!nearFound) {
                        val k = com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / com.haraan.app.vision.PitchGeometry.STUMP_SET_WIDTH_M
                        bowlersEndBase = com.haraan.app.vision.BowlersEnd(
                            com.haraan.app.vision.Point2(guide.near.centreX, guide.near.baseY),
                            guide.near.width * k,
                        )
                    }
                    view.performHapticFeedback(Feel.COMMIT)
                    setupAligning = false
                },
                onCancel = { setupAligning = false },
            )
        }
        if (adjusting && granted && !recording) {
            // Where a set has never been seen, its ring starts where Setup's box would be.
            val guide = com.haraan.app.vision.StumpAlignGuide.guide(cameraIntrinsics?.focalFw ?: 1.4, uprightAspect)
            val k = com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / com.haraan.app.vision.PitchGeometry.STUMP_SET_WIDTH_M
            MarkingsAdjust(
                uprightAspect = uprightAspect,
                far = wicketLock?.takeIf { it.kind == com.haraan.app.vision.WicketKind.STUMPS }?.anchor
                    ?: com.haraan.app.vision.WicketAnchor(
                        com.haraan.app.vision.Point2(guide.far.centreX - guide.far.width * k / 2, guide.far.baseY),
                        com.haraan.app.vision.Point2(guide.far.centreX + guide.far.width * k / 2, guide.far.baseY),
                        com.haraan.app.vision.Point2(guide.far.centreX, guide.far.topY),
                    ),
                near = bowlersEndBase ?: com.haraan.app.vision.BowlersEnd(
                    com.haraan.app.vision.Point2(guide.near.centreX, guide.near.baseY),
                    guide.near.width * k,
                ),
                onFar = { moved ->
                    wicketTracker.placeManually(moved, uprightAspect)
                    wicketLock = wicketTracker.lock()
                    wicketDiagnostics = wicketTracker.diagnostics()
                },
                onNear = { moved ->
                    bowlersEndPinned = true
                    bowlersEndBase = moved
                },
                onReset = {
                    // Back to the detector for both sets.
                    wicketTracker.clearManualLock()
                    wicketLock = wicketTracker.lock()
                    wicketDiagnostics = wicketTracker.diagnostics()
                    bowlersEndPinned = false
                    bowlersEndFilter.clear()
                    view.performHapticFeedback(Feel.REMOVE)
                    adjusting = false
                },
                onDone = { far, near ->
                    // Held from here, and let go if the phone is moved — as Setup's are. Both
                    // sets, dragged or not: a detected lock left alone would still be let go
                    // the next time the batter hid it.
                    wicketTracker.placeManually(far, uprightAspect)
                    wicketLock = wicketTracker.lock()
                    wicketDiagnostics = wicketTracker.diagnostics()
                    bowlersEndPinned = true
                    bowlersEndBase = near
                    setupTilt = com.haraan.app.vision.DeviceTilt.steady()
                    setupNote = null
                    view.performHapticFeedback(Feel.COMMIT)
                    adjusting = false
                },
            )
        }
        SetupMovedWatch(setupTilt) {
            // Only a lock Setup (or a hand) placed is let go: the detector's own keeps re-deciding.
            if (wicketTracker.diagnostics().lockSource == com.haraan.app.vision.WicketLockSource.MANUAL) {
                wicketTracker.clearManualLock()
                wicketLock = wicketTracker.lock()
                wicketDiagnostics = wicketTracker.diagnostics()
            }
            bowlersEndPinned = false
            setupTilt = null
            setupNote = "The phone moved since Setup · tap Setup again"
            scope.launch { com.haraan.app.ui.matches.cricketThud(ctx, com.haraan.app.ui.matches.Thud.UNDO) }
        }
        val result = liveResult
        if (result != null && liveSequence == null) {
            LiveResultOverlay(
                path = result.path,
                uprightAspect = uprightAspect,
                speedKmh = result.speedKmh,
                onReplay = { liveSequence = result }.takeIf { result.frames.size >= 2 || result.flight != null },
                flight = result.flight,
            )
        }
        liveSequence?.let { seq ->
            DeliveryReplaySequence(
                frames = seq.frames,
                path = seq.path,
                speedKmh = seq.speedKmh,
                flight = seq.flight,
                // In Auto nobody may be holding the phone: the stadium closes itself.
                stadiumAutoCloseMs = if (autoMode) AUTO_REPLAY_HOLD_MS else null,
                onDone = { liveSequence = null },
            )
        }
        val clipToPlay = replayClip
        if (showVideo && clipToPlay != null) {
            VideoReplayOverlay(
                clip = clipToPlay,
                onClose = { showVideo = false },
                onOpen3d = lastMetrics?.flight3d?.let {
                    {
                        showVideo = false
                        showReplay = true
                    }
                },
            )
        }
        val replayFlight = lastMetrics?.flight3d
        if (showReplay && replayFlight != null) {
            com.haraan.app.vision.FlightReplayOverlay(
                flight = replayFlight,
                onClose = { showReplay = false },
                // In Auto nobody may be holding the phone: it closes itself and the next
                // segment starts.
                autoCloseAfterMs = if (autoMode) AUTO_REPLAY_HOLD_MS else null,
            )
        }
        if (showGallery) {
            ClipGalleryOverlay(
                clips = galleryClips,
                keepHours = uploadQueue.keepHours,
                onDelete = { uploadQueue.deleteKept(it.id) },
                onClose = { showGallery = false },
            )
        }
    }
}

/** One ball's after-ball replay: its frames, the path drawn over them, and its 3D flight. */
internal class LiveReplay(
    val frames: List<Pair<Double, ByteArray>>,
    val path: com.haraan.app.vision.ArPath,
    val speedKmh: Double?,
    val flight: com.haraan.app.vision.Flight3d?,
    /** Which ball: its first sighting on the camera's clock. */
    val ballMs: Long = 0L,
)

/**
 * NO CAMERA YET, AND A WAY TO GET ONE. The screen used to say only that access was needed, with
 * nothing to tap: a camera person who answered the system prompt "Don't allow" (or whose
 * "Only this time" ran out) was stuck. A tap asks again; once Android stops showing the prompt
 * it opens this app's settings, and coming back re-checks.
 */
@Composable
private fun CameraAccessNeeded(
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onGranted: () -> Unit,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    var refused by remember { mutableStateOf(false) }
    androidx.compose.runtime.DisposableEffect(lifecycleOwner) {
        val observer = androidx.lifecycle.LifecycleEventObserver { _, event ->
            if (event == androidx.lifecycle.Lifecycle.Event.ON_RESUME && hasCameraPermission()) onGranted()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }
    Column(
        Modifier.fillMaxSize().padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            "Camera access is needed to film the match.",
            color = Ink,
            fontSize = 15.sp,
            textAlign = TextAlign.Center,
        )
        if (refused) {
            Spacer(Modifier.height(6.dp))
            Text(
                "It's turned off for Haraan. Allow Camera in settings, then come back.",
                color = Ink.copy(alpha = 0.6f),
                fontSize = 13.sp,
                textAlign = TextAlign.Center,
            )
        }
        Spacer(Modifier.height(18.dp))
        PrimaryButton(if (refused) "Open settings" else "Allow camera", enabled = true) {
            if (refused) {
                runCatching {
                    context.startActivity(
                        android.content.Intent(
                            android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                            android.net.Uri.fromParts("package", context.packageName, null),
                        ),
                    )
                }
            } else {
                requestCameraPermission { ok -> if (ok) onGranted() else refused = true }
            }
        }
    }
}

/**
 * THE TRIPOD WAS KNOCKED. After Setup the stumps are held even when nobody can see them, so the
 * one thing that must undo it is the phone itself moving: its tilt, from gravity, settling more
 * than [SETUP_MOVED_DEG] from where Setup left it. Small bumps are followed by the camera-motion
 * carry; a re-aimed phone is not, and calls [onMoved] once. No reading (no sensor, or a video
 * standing in for the lens) never calls it.
 */
@Composable
private fun SetupMovedWatch(setupTilt: com.haraan.app.vision.DeviceTilt.Reading?, onMoved: () -> Unit) {
    LaunchedEffect(setupTilt) {
        val at = setupTilt ?: return@LaunchedEffect
        val limit = Math.toRadians(SETUP_MOVED_DEG)
        while (true) {
            kotlinx.coroutines.delay(500)
            val now = com.haraan.app.vision.DeviceTilt.steady() ?: continue
            if (kotlin.math.abs(now.pitchRad - at.pitchRad) > limit || kotlin.math.abs(now.rollRad - at.rollRad) > limit) {
                onMoved()
                return@LaunchedEffect
            }
        }
    }
}

/** How far the phone's tilt may settle from Setup's before the held stumps are let go. */
private const val SETUP_MOVED_DEG = 2.0

/** The one commitment on the screen: solid brand blue, and it dips under a thumb. */
@Composable
private fun PrimaryButton(label: String, enabled: Boolean, onClick: () -> Unit) {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale = remember { Animatable(1f) }
    val view = LocalView.current
    LaunchedEffect(pressed) {
        scale.animateTo(if (pressed) 0.97f else 1f, tween(140, easing = FastOutSlowInEasing))
    }
    Box(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { scaleX = scale.value; scaleY = scale.value }
            .clip(RoundedCornerShape(16.dp))
            .background(if (enabled) Accent else Color(0xFF1E293B))
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
            ) {
                // Joining a match is a commitment, and the only one made on this screen
                // while the phone is still being looked at.
                view.performHapticFeedback(Feel.COMMIT)
                onClick()
            }
            .padding(vertical = 18.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            color = if (enabled) Color.White else Ink.copy(alpha = 0.4f),
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.2).sp,
        )
    }
}

/** The way out. An outline, because leaving is not the thing being encouraged. */
@Composable
private fun GhostButton(label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .border(1.dp, Color.White.copy(alpha = 0.13f), RoundedCornerShape(16.dp))
            .clickableCapture(enabled = true, onClick = onClick)
            .padding(vertical = 17.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, color = Ink.copy(alpha = 0.75f), fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * The status line and the shutter — everything on the filming screen that is not the
 * picture, kept together because they are read together.
 *
 * Two arrangements of the same things. Portrait stacks them along the bottom edge;
 * landscape lays them along the right edge, shutter outermost. In both the disc ends up on
 * the short edge nearest the hand, and in neither does it sit over the middle of the pitch.
 */
@Composable
private fun ShutterControl(
    modifier: Modifier,
    status: String,
    isError: Boolean,
    recording: Boolean,
    canFilm: Boolean,
    landscape: Boolean,
    readout: DeliveryReadout?,
    wickets: com.haraan.app.vision.LbwProjection?,
    onArm: () -> Unit,
) {
    val statusLine: @Composable () -> Unit = {
        Text(
            status,
            color = if (isError) Color(0xFFFF8A8A) else Ink.copy(alpha = 0.92f),
            fontSize = 12.5.sp,
            fontWeight = FontWeight.Medium,
            textAlign = if (landscape) TextAlign.End else TextAlign.Center,
            modifier = Modifier
                .then(if (landscape) Modifier.widthIn(max = 230.dp) else Modifier)
                .clip(RoundedCornerShape(999.dp))
                .background(Scrim)
                .padding(horizontal = 14.dp, vertical = 7.dp),
        )
    }

    if (landscape) {
        Row(modifier, verticalAlignment = Alignment.CenterVertically) {
            Column(horizontalAlignment = Alignment.End) {
                WicketsChip(wickets)
                DeliveryReadoutView(readout, alignEnd = true)
                statusLine()
            }
            Spacer(Modifier.width(18.dp))
            ShutterDisc(recording = recording, canFilm = canFilm, onArm = onArm)
        }
    } else {
        Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
            WicketsChip(wickets)
            DeliveryReadoutView(readout, alignEnd = false)
            statusLine()
            Spacer(Modifier.height(16.dp))
            ShutterDisc(recording = recording, canFilm = canFilm, onArm = onArm)
        }
    }
}

/**
 * THE SHUTTER, built the way every camera the operator has ever held builds one.
 *
 * A white ring and a red disc. Filming, the disc closes into a rounded square — the stop
 * shape — and the ring fills red clockwise across the clip window, so how long is left is
 * read at a glance instead of guessed from an indeterminate spinner (a spinner says
 * "loading", which is the wrong thing to say about a camera that is filming).
 *
 * The morph runs on a spring, not a tween: it overshoots a hair and settles, which is what
 * makes a flat drawing read as a part that moved. It is driven by state, so a clip armed by
 * the scorer's BALL — with nobody touching the phone — animates exactly like a tap would.
 *
 * The whole disc takes the tap. A press while a clip is running is refused IN THE HAND with
 * the reject note, rather than landing on a disabled control in silence.
 */
@Composable
private fun ShutterDisc(recording: Boolean, canFilm: Boolean, onArm: () -> Unit) {
    val view = LocalView.current
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val pressScale by animateFloatAsState(
        targetValue = if (pressed) 0.9f else 1f,
        animationSpec = spring(dampingRatio = 0.55f, stiffness = 700f),
        label = "shutterPress",
    )
    val morph by animateFloatAsState(
        targetValue = if (recording) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.62f, stiffness = 420f),
        label = "shutterMorph",
    )
    val window = remember { Animatable(0f) }
    LaunchedEffect(recording) {
        if (recording) {
            window.snapTo(0f)
            window.animateTo(1f, tween(REVIEW_CLIP_MS.toInt(), easing = androidx.compose.animation.core.LinearEasing))
        } else {
            window.animateTo(0f, tween(260))
        }
    }
    val ready = canFilm || recording

    Box(
        Modifier
            .graphicsLayer { scaleX = pressScale; scaleY = pressScale }
            .size(80.dp)
            .clip(CircleShape)
            .clickable(interactionSource = press, indication = null) {
                if (canFilm) {
                    // No haptic here: arming plays the "ball is on" swell itself, for a tap
                    // and for the scorer's cue alike. One event, one buzz.
                    onArm()
                } else {
                    view.performHapticFeedback(Feel.REMOVE)
                }
            }
            .semantics { contentDescription = if (recording) "Filming" else "Film this ball" },
    ) {
        Canvas(Modifier.fillMaxSize()) {
            val ringW = 4.dp.toPx()
            val r = size.minDimension / 2f - ringW / 2f
            // Shadow under the ring, so it holds its edge against a sunlit outfield.
            drawCircle(Color.Black.copy(alpha = 0.28f), radius = r + ringW, style = Stroke(ringW))
            drawCircle(
                Color.White.copy(alpha = if (ready) 0.95f - 0.55f * morph else 0.4f),
                radius = r,
                style = Stroke(ringW),
            )
            if (window.value > 0f) {
                drawArc(
                    Rec,
                    startAngle = -90f,
                    sweepAngle = 360f * window.value,
                    useCenter = false,
                    topLeft = Offset(center.x - r, center.y - r),
                    size = androidx.compose.ui.geometry.Size(r * 2, r * 2),
                    style = Stroke(ringW, cap = StrokeCap.Round),
                )
            }

            // Disc → rounded square. Size and corner both ride the one spring.
            val full = size.minDimension - 18.dp.toPx()
            val stop = 30.dp.toPx()
            val side = full + (stop - full) * morph
            val corner = side / 2f + (7.dp.toPx() - side / 2f) * morph
            drawRoundRect(
                color = if (ready) Rec else Rec.copy(alpha = 0.35f),
                topLeft = Offset(center.x - side / 2f, center.y - side / 2f),
                size = androidx.compose.ui.geometry.Size(side, side),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(corner, corner),
            )
        }
    }
}

/**
 * One of the delivery's numbers, ready to draw.
 *
 * [value] is null when the phone could not measure it — never a zero, never a guess.
 * [note] is what a tap on the card reveals: how the number was got, or what it still needs.
 */
private data class MetricCardData(
    val label: String,
    val value: Double?,
    val decimals: Int,
    val unit: String,
    val note: String,
)

private fun metricCards(
    metrics: com.haraan.app.vision.FlightMetrics?,
    ball: com.haraan.app.vision.BallDrag = com.haraan.app.vision.BallDrag.LEATHER,
): List<MetricCardData> {
    fun reason(m: com.haraan.app.vision.MetricValue?, waiting: String) = when (m) {
        is com.haraan.app.vision.MetricValue.Unavailable -> "Needs ${m.reason.removePrefix("needs ")}"
        null -> waiting
        else -> null
    }
    val speed = metrics?.groundSpeed
    val turn = metrics?.turn
    val swing = metrics?.swing
    // Swing is in metres once a wicket lock gives the picture a scale; without one it is a
    // fraction of the frame, which is not a distance and is not printed as one.
    val swingCm = (swing as? com.haraan.app.vision.MetricValue.Measured)?.takeIf { it.unit == "m" }?.value?.times(100)
        ?: (swing as? com.haraan.app.vision.MetricValue.Estimated)?.takeIf { it.unit == "m" }?.value?.times(100)
    return listOf(
        MetricCardData(
            label = "Speed",
            // Measured from the stumps side-on, or estimated from the pitch corners behind
            // the arm; the note says which.
            // The fitted flight re-read for the ball chosen now, so changing the chip moves it.
            value = metrics?.flight3d?.copy(ball = ball)?.speedKmh
                ?: (speed as? com.haraan.app.vision.MetricValue.Measured)?.value
                ?: (speed as? com.haraan.app.vision.MetricValue.Estimated)?.value,
            decimals = 0,
            unit = "km/h",
            note = reason(speed, "After the next ball")
                ?: (speed as? com.haraan.app.vision.MetricValue.Estimated)?.let { "Estimated: ${it.basis}" }
                ?: "Over the ground, scaled by the stumps",
        ),
        MetricCardData(
            label = "Spin",
            value = (turn as? com.haraan.app.vision.MetricValue.Measured)?.value?.let { kotlin.math.abs(it) },
            decimals = 1,
            unit = "°",
            note = reason(turn, "After the next ball")
                ?: "How far it turned off the pitch",
        ),
        MetricCardData(
            label = "Swing",
            value = swingCm?.let { kotlin.math.abs(it) },
            decimals = 0,
            unit = "cm",
            note = reason(swing, "After the next ball")
                ?: if (swingCm == null) "Lock the wicket to measure in cm" else "Sideways movement before the bounce",
        ),
    )
}

/**
 * THE DELIVERY'S NUMBERS, as one slim readout down the left edge.
 *
 * It used to be four separate cards, each up to 190 dp wide, stacked with gaps — a third of
 * the picture's height on the side of the frame the operator aims with. One glass panel with
 * four rows does the same job in about a third of the area: the picture is the product, the
 * numbers are a caption to it.
 *
 * Still built to feel like a readout that LANDS:
 *  - a new ball's numbers count up, row after row, 90 ms apart, each row popping on a spring
 *    with a brief blue flash;
 *  - while the next ball is filmed the old numbers dim instead of vanishing;
 *  - a press dips the row under the finger with a tick and widens the panel to say how the
 *    number was measured — or, for a dash, what it needs.
 *
 * A dash is a dash: nothing here prints a figure for something the phone did not see.
 */
@Composable
private fun DeliveryMetricsStack(
    metrics: com.haraan.app.vision.FlightMetrics?,
    stale: Boolean,
    modifier: Modifier = Modifier,
    /** Behind the bowler's arm only: side-on, off and leg are not in the picture. */
    showLine: Boolean = false,
    hand: com.haraan.app.vision.BatterHand = com.haraan.app.vision.BatterHand.RIGHT,
    onToggleHand: () -> Unit = {},
    /** Null when this ball has no 3D flight to replay. */
    onOpenReplay: (() -> Unit)? = null,
    /** Null until the delivery's clip is finished and kept. */
    onWatchClip: (() -> Unit)? = null,
    ball: com.haraan.app.vision.BallDrag = com.haraan.app.vision.BallDrag.LEATHER,
    onBall: (com.haraan.app.vision.BallDrag) -> Unit = {},
) {
    /*
     * A camera readout sits ON the picture, so it does not grow with the system font size
     * the way the rest of the app does: at 1.3× (as on the phone this was checked on) the
     * panel covered half the frame's height. Capped at 1.1× — still larger for anyone who
     * asked for larger, never a wall over the viewfinder.
     */
    val baseDensity = androidx.compose.ui.platform.LocalDensity.current
    androidx.compose.runtime.CompositionLocalProvider(
        androidx.compose.ui.platform.LocalDensity provides androidx.compose.ui.unit.Density(
            baseDensity.density,
            minOf(baseDensity.fontScale, HUD_MAX_FONT_SCALE),
        ),
    ) {
        DeliveryReadout(metrics, stale, modifier, showLine, hand, onToggleHand, onOpenReplay, onWatchClip, ball, onBall)
    }
}

@Composable
private fun DeliveryReadout(
    metrics: com.haraan.app.vision.FlightMetrics?,
    stale: Boolean,
    modifier: Modifier,
    showLine: Boolean,
    hand: com.haraan.app.vision.BatterHand,
    onToggleHand: () -> Unit,
    onOpenReplay: (() -> Unit)?,
    onWatchClip: (() -> Unit)?,
    ball: com.haraan.app.vision.BallDrag,
    onBall: (com.haraan.app.vision.BallDrag) -> Unit,
) {
    val cards = metricCards(metrics, ball)
    // Which row is open, if any: one at a time, so the panel only ever widens for one note.
    var openRow by remember { mutableStateOf<Int?>(null) }
    val panelWidth by androidx.compose.animation.core.animateDpAsState(
        when {
            openRow != null -> 196.dp
            metrics == null -> 122.dp
            else -> 96.dp
        },
        spring(dampingRatio = 0.82f, stiffness = 520f),
        label = "panelWidth",
    )
    val shape = RoundedCornerShape(16.dp)
    Column(modifier, verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Column(
            Modifier
                .width(panelWidth)
                .clip(shape)
                .background(Color(0x9E070B14))
                .border(1.dp, Color.White.copy(alpha = 0.09f), shape)
                .animateContentSize(spring(dampingRatio = 0.78f, stiffness = 420f))
                .padding(vertical = 3.dp),
        ) {
            /*
             * NOTHING MEASURED YET: one line, not four rows of dashes. The readout springs
             * open into its rows the moment a ball gives it something to say.
             */
            if (metrics == null) {
                Row(
                    Modifier.padding(start = 10.dp, end = 8.dp, top = 5.dp, bottom = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(Modifier.weight(1f)) { RowLabel("LAST BALL") }
                    Spacer(Modifier.width(6.dp))
                    if (showLine) HandChip(hand, onToggleHand) else Text("—", color = Color.White.copy(alpha = 0.3f), fontSize = 12.sp)
                }
                return@Column
            }
            cards.forEachIndexed { index, card ->
                if (index > 0) MetricDivider()
                MetricRow(
                    card = card,
                    stale = stale,
                    delayMs = index * 90L,
                    generation = metrics,
                    open = openRow == index,
                    onToggle = { openRow = if (openRow == index) null else index },
                    accessory = if (card.label == "Speed") ({ BallChip(ball, onBall) }) else null,
                )
            }
            if (showLine) {
                MetricDivider()
                LineRow(
                    line = metrics?.let { com.haraan.app.vision.DeliveryLines.of(it, hand) },
                    hand = hand,
                    stale = stale,
                    generation = metrics,
                    open = openRow == cards.size,
                    onToggle = { openRow = if (openRow == cards.size) null else cards.size },
                    onToggleHand = onToggleHand,
                )
            }
        }
        if (!stale && (onWatchClip != null || onOpenReplay != null)) {
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                onWatchClip?.let { ReplayPill("Replay", it) }
                onOpenReplay?.let { ReplayPill("3D", it) }
            }
        }
    }
}

@Composable
private fun MetricDivider() {
    Box(
        Modifier
            .padding(horizontal = 10.dp)
            .fillMaxWidth()
            .height(1.dp)
            .background(Color.White.copy(alpha = 0.07f)),
    )
}

/** Reopens one of the last ball's replays. Brand blue, like every action on this screen. */
@Composable
private fun ReplayPill(label: String, onClick: () -> Unit) {
    val view = LocalView.current
    Row(
        Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0xFF2563EB))
            .clickable {
                view.performHapticFeedback(Feel.SELECT)
                onClick()
            }
            .padding(start = 10.dp, end = 12.dp, top = 6.dp, bottom = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(8.dp)) {
            val path = androidx.compose.ui.graphics.Path().apply {
                moveTo(size.width * 0.15f, 0f)
                lineTo(size.width, size.height / 2f)
                lineTo(size.width * 0.15f, size.height)
                close()
            }
            drawPath(path, Color.White)
        }
        Spacer(Modifier.width(6.dp))
        Text(label, color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
    }
}

/**
 * The press and the landing, shared by every row: dips under the finger, pops when its
 * number arrives, and flashes brand blue behind it for a moment.
 */
@Composable
private fun Modifier.landingRow(
    press: MutableInteractionSource,
    pop: Float,
    flash: Float,
    onClick: () -> Unit,
): Modifier {
    val view = LocalView.current
    val pressed by press.collectIsPressedAsState()
    val pressScale by animateFloatAsState(
        if (pressed) 0.93f else 1f,
        spring(dampingRatio = 0.5f, stiffness = 800f),
        label = "rowPress",
    )
    return this
        .fillMaxWidth()
        .graphicsLayer {
            val s = pressScale * pop
            scaleX = s
            scaleY = s
            transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0.5f)
        }
        .background(Color(0xFF3B82F6).copy(alpha = 0.22f * flash))
        .clickable(interactionSource = press, indication = null) {
            view.performHapticFeedback(Feel.SELECT)
            onClick()
        }
        .animateContentSize(spring(dampingRatio = 0.8f, stiffness = 500f))
        .padding(start = 10.dp, end = 9.dp, top = 4.dp, bottom = 4.dp)
}

/** The striker's hand: one tap, and it stays for the session. */
@Composable
private fun HandChip(hand: com.haraan.app.vision.BatterHand, onToggleHand: () -> Unit) {
    val view = LocalView.current
    Text(
        if (hand == com.haraan.app.vision.BatterHand.RIGHT) "RHB" else "LHB",
        color = Color.White,
        fontSize = 8.5.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 0.6.sp,
        maxLines = 1,
        softWrap = false,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0xFF2F5BEA))
            .clickable {
                view.performHapticFeedback(Feel.SELECT)
                onToggleHand()
            }
            .padding(horizontal = 6.dp, vertical = 1.dp),
    )
}

/**
 * The ball the speed is read for. A tap moves to the next kind; the figure above it re-reads
 * at once, because the same flight was bowled faster with a ball that slows more in the air.
 */
@Composable
private fun BallChip(ball: com.haraan.app.vision.BallDrag, onBall: (com.haraan.app.vision.BallDrag) -> Unit) {
    val view = LocalView.current
    val choices = com.haraan.app.vision.BallDrag.CHOICES
    Text(
        ball.label.uppercase(),
        color = Color.White,
        fontSize = 8.5.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 0.6.sp,
        maxLines = 1,
        softWrap = false,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(if (ball == com.haraan.app.vision.BallDrag.TENNIS) Color(0xFF65A30D) else Color(0xFFB91C1C))
            .clickable {
                view.performHapticFeedback(Feel.SELECT)
                onBall(choices[(choices.indexOf(ball) + 1) % choices.size])
            }
            .padding(horizontal = 6.dp, vertical = 1.dp),
    )
}

@Composable
private fun RowLabel(text: String) {
    Text(
        text,
        color = Color.White.copy(alpha = 0.5f),
        fontSize = 8.5.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 1.1.sp,
        maxLines = 1,
    )
}

@Composable
private fun MetricRow(
    card: MetricCardData,
    stale: Boolean,
    delayMs: Long,
    generation: Any?,
    open: Boolean,
    onToggle: () -> Unit,
    /** Beside the label: the ball chip on the SPEED row. */
    accessory: (@Composable () -> Unit)? = null,
) {
    val shown = remember { Animatable(0f) }
    val pop = remember { Animatable(1f) }
    val flash = remember { Animatable(0f) }

    // A new delivery's value: count up, pop, flash. Keyed on the metrics object, so the same
    // number arriving twice (two balls at 118) still lands twice.
    LaunchedEffect(generation, card.value) {
        val target = card.value
        if (target == null) {
            shown.snapTo(0f)
            return@LaunchedEffect
        }
        delay(delayMs)
        shown.snapTo(0f)
        launch { flash.snapTo(1f); flash.animateTo(0f, tween(900)) }
        launch {
            pop.snapTo(0.9f)
            pop.animateTo(1f, spring(dampingRatio = 0.42f, stiffness = 520f))
        }
        shown.animateTo(target.toFloat(), tween(650, easing = FastOutSlowInEasing))
    }
    val dim by animateFloatAsState(if (stale) 0.4f else 1f, tween(260), label = "stale")
    val press = remember { MutableInteractionSource() }

    Column(Modifier.landingRow(press, pop.value, flash.value, onToggle)) {
        if (accessory != null) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                RowLabel(card.label.uppercase())
                Spacer(Modifier.weight(1f))
                accessory()
            }
        } else {
            RowLabel(card.label.uppercase())
        }
        Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.graphicsLayer { alpha = dim }) {
            if (card.value == null) {
                Text("—", color = Color.White.copy(alpha = 0.3f), fontSize = 17.sp, fontFamily = ArchivoDisplay)
            } else {
                Text(
                    "%.${card.decimals}f".format(shown.value),
                    color = Color.White,
                    fontSize = 18.sp,
                    fontFamily = ArchivoDisplay,
                    letterSpacing = (-0.3).sp,
                    maxLines = 1,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.width(3.dp))
                Text(
                    card.unit,
                    color = Color.White.copy(alpha = 0.6f),
                    fontSize = 9.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    modifier = Modifier.padding(bottom = 3.dp),
                )
            }
        }
        if (open) {
            Spacer(Modifier.height(4.dp))
            Text(card.note, color = Color.White.copy(alpha = 0.62f), fontSize = 10.5.sp, lineHeight = 14.sp)
        }
    }
}

/**
 * THE LINE, as a word and a picture of the stumps, in one slim row.
 *
 * The word is the broadcast's — "Outside off", "Leg stump" — in the batter's terms. The
 * picture is drawn the way this phone sees it, from behind the bowler: three stumps, the
 * crossing point as a dot that slides in from middle, the projection's error as a faint bar
 * either side of it, and where the ball pitched as a ring when the pitch is calibrated.
 *
 * Off and leg depend on the striker, and this phone is a guest that is never told who is
 * batting, so the RHB/LHB chip is the operator's to set. It flips the words and the side
 * labels; the dot stays where the ball actually went.
 */
@Composable
private fun LineRow(
    line: com.haraan.app.vision.DeliveryLine?,
    hand: com.haraan.app.vision.BatterHand,
    stale: Boolean,
    generation: Any?,
    open: Boolean,
    onToggle: () -> Unit,
    onToggleHand: () -> Unit,
) {
    val view = LocalView.current
    val dim by animateFloatAsState(if (stale) 0.4f else 1f, tween(260), label = "lineStale")
    val blue = Color(0xFF8DB0FF)
    val right = hand == com.haraan.app.vision.BatterHand.RIGHT

    // Back to the picture's own left/right for drawing: the dot goes where the ball went.
    fun pictureRight(offTowardsOff: Double) = if (right) -offTowardsOff else offTowardsOff
    val dotTarget = line?.atStumpsOffM?.let { pictureRight(it) }
    val slide = remember { Animatable(0f) }
    val pop = remember { Animatable(1f) }
    val flash = remember { Animatable(0f) }
    LaunchedEffect(generation, dotTarget) {
        if (dotTarget == null) {
            slide.snapTo(0f)
            return@LaunchedEffect
        }
        delay(3 * 90L)
        slide.snapTo(0f)
        launch { flash.snapTo(1f); flash.animateTo(0f, tween(900)) }
        launch {
            pop.snapTo(0.9f)
            pop.animateTo(1f, spring(dampingRatio = 0.42f, stiffness = 520f))
        }
        slide.animateTo(dotTarget.toFloat(), tween(650, easing = FastOutSlowInEasing))
    }
    val press = remember { MutableInteractionSource() }

    Column(Modifier.landingRow(press, pop.value, flash.value, onToggle)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            RowLabel("LINE")
            Spacer(Modifier.weight(1f))
            HandChip(hand, onToggleHand)
        }
        Column(Modifier.graphicsLayer { alpha = dim }) {
            Text(
                line?.atStumps?.spoken ?: "—",
                color = if (line?.atStumps == null) Color.White.copy(alpha = 0.3f) else Color.White,
                fontSize = if (line?.atStumps == null) 17.sp else 12.5.sp,
                fontFamily = ArchivoDisplay,
                letterSpacing = (-0.1).sp,
                maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            )
            val pitchedAt = line?.pitchedOffM?.let { pictureRight(it) }
            val band = line?.uncertaintyM
            Canvas(
                Modifier
                    .padding(top = 3.dp)
                    .fillMaxWidth()
                    .height(18.dp),
            ) {
                val cx = size.width / 2f
                val pxPerM = (size.width / 2f) / LINE_VIEW_HALF_M
                fun x(m: Double) = (cx + m * pxPerM).toFloat().coerceIn(0f, size.width)
                val ground = size.height - 2.dp.toPx()

                // The crease, faint, edge to edge, with OFF and LEG as ticks at its ends.
                drawLine(Color.White.copy(alpha = 0.18f), Offset(0f, ground), Offset(size.width, ground), 1.dp.toPx())
                // Three stumps.
                for (m in listOf(-STUMP_CENTRE_M, 0.0, STUMP_CENTRE_M)) {
                    drawLine(
                        Color.White.copy(alpha = 0.85f),
                        Offset(x(m), ground),
                        Offset(x(m), ground - 11.dp.toPx()),
                        1.6.dp.toPx(),
                        StrokeCap.Round,
                    )
                }
                // Where it pitched, when the pitch is calibrated.
                if (pitchedAt != null) {
                    drawCircle(
                        Color.White.copy(alpha = 0.55f),
                        radius = 3.dp.toPx(),
                        center = Offset(x(pitchedAt), ground),
                        style = Stroke(1.2.dp.toPx()),
                    )
                }
                if (dotTarget != null) {
                    val at = slide.value.toDouble()
                    val dotY = ground - 6.dp.toPx()
                    if (band != null) {
                        drawLine(
                            blue.copy(alpha = 0.28f),
                            Offset(x(at - band), dotY),
                            Offset(x(at + band), dotY),
                            5.dp.toPx(),
                            StrokeCap.Round,
                        )
                    }
                    drawCircle(blue, radius = 3.5.dp.toPx(), center = Offset(x(at), dotY))
                }
            }
            Row {
                Text(if (right) "OFF" else "LEG", color = Color.White.copy(alpha = 0.38f), fontSize = 7.5.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.weight(1f))
                Text(if (right) "LEG" else "OFF", color = Color.White.copy(alpha = 0.38f), fontSize = 7.5.sp, fontWeight = FontWeight.Bold)
            }
        }
        if (open) {
            Spacer(Modifier.height(4.dp))
            val note = buildString {
                val off = line?.atStumpsOffM
                if (off != null) {
                    append("At the stumps: %.0f cm %s of middle".format(kotlin.math.abs(off) * 100, if (off >= 0) "off side" else "leg side"))
                    line.uncertaintyM?.let { append(", ±%.0f cm".format(it * 100)) }
                    append(".")
                } else {
                    append("Needs ${(line?.reason ?: "the next ball").removePrefix("needs ")}.")
                }
                line?.pitched?.let { append(" Pitched ${it.spoken.lowercase()} (rough sideways).") }
                    ?: append(" Calibrate the pitch to see where it pitched.")
            }
            Text(note, color = Color.White.copy(alpha = 0.62f), fontSize = 10.5.sp, lineHeight = 14.sp)
        }
    }
}

/** Largest system font scale the on-picture readout follows. */
private const val HUD_MAX_FONT_SCALE = 1.1f

/** How far either side of middle the line picture shows, in metres. */
private const val LINE_VIEW_HALF_M = 0.6

/** Outer stump centres from middle: half the wicket less half a stump. */
private const val STUMP_CENTRE_M = com.haraan.app.vision.PitchGeometry.STUMP_SET_WIDTH_M / 2.0 - 0.0175

/**
 * The red edge that says "filming".
 *
 * Start: two sharp flashes, like a camera's tally light catching — the shape of it is what
 * makes it read as an EVENT and not a colour change. Then a slow breath, 0.35 to 0.65, so a
 * glance at any moment of the clip still finds it lit. Stop: a quick fade.
 *
 * Drawn as a glow soaking in from all four screen edges, which is what peripheral vision
 * picks up; a thin line alone is invisible from the corner of the eye in daylight.
 */
@Composable
private fun RecordingEdgeGlow(recording: Boolean, uprightAspect: Float) {
    val glow = remember { Animatable(0f) }
    LaunchedEffect(recording) {
        if (!recording) {
            glow.animateTo(0f, tween(300))
            return@LaunchedEffect
        }
        repeat(2) {
            glow.animateTo(1f, tween(70))
            glow.animateTo(0.2f, tween(170))
        }
        glow.animateTo(0.65f, tween(220))
        while (true) {
            glow.animateTo(0.35f, tween(850, easing = FastOutSlowInEasing))
            glow.animateTo(0.65f, tween(850, easing = FastOutSlowInEasing))
        }
    }
    if (glow.value <= 0.01f) return
    Canvas(Modifier.fillMaxSize()) {
        val a = glow.value
        val depth = 34.dp.toPx()
        val w = size.width
        val h = size.height
        val hot = Rec.copy(alpha = 0.75f * a)
        val none = Color.Transparent
        drawRect(Brush.verticalGradient(listOf(hot, none), startY = 0f, endY = depth), size = androidx.compose.ui.geometry.Size(w, depth))
        drawRect(
            Brush.verticalGradient(listOf(none, hot), startY = h - depth, endY = h),
            topLeft = Offset(0f, h - depth),
            size = androidx.compose.ui.geometry.Size(w, depth),
        )
        drawRect(Brush.horizontalGradient(listOf(hot, none), startX = 0f, endX = depth), size = androidx.compose.ui.geometry.Size(depth, h))
        drawRect(
            Brush.horizontalGradient(listOf(none, hot), startX = w - depth, endX = w),
            topLeft = Offset(w - depth, 0f),
            size = androidx.compose.ui.geometry.Size(depth, h),
        )
        // And the footage's own edge, crisp.
        val frame = frameRect(w, h, uprightAspect)
        val line = 2.5.dp.toPx()
        drawRect(
            Rec.copy(alpha = (0.5f + 0.5f * a).coerceAtMost(1f)),
            topLeft = Offset(frame.left + line / 2f, frame.top + line / 2f),
            size = androidx.compose.ui.geometry.Size(frame.width - line, frame.height - line),
            style = Stroke(width = line),
        )
    }
}

/**
 * WICKETS, on the camera phone, as soon as the ball is done.
 *
 * The broadcast's chip — a dark label cell and a coloured answer that wipes in — carrying
 * the one LBW question this phone can measure. Red is hitting, green missing, amber too
 * close to call, grey not judged; the line under it is the projection's own words, so a
 * "not judged" always says what it needed ("no wicket locked", "only 3 sightings…").
 *
 * No haptic of its own: the clip ending already ticked, and one event gets one buzz.
 */
@Composable
private fun WicketsChip(projection: com.haraan.app.vision.LbwProjection?) {
    var shown by remember { mutableStateOf(projection) }
    if (projection != null) shown = projection
    val enter = remember { Animatable(0f) }
    val wipe = remember { Animatable(0f) }
    LaunchedEffect(projection) {
        if (projection == null) {
            enter.animateTo(0f, tween(180))
            wipe.snapTo(0f)
            return@LaunchedEffect
        }
        wipe.snapTo(0f)
        enter.snapTo(0f)
        launch { enter.animateTo(1f, spring(dampingRatio = 0.6f, stiffness = 420f)) }
        delay(160)
        wipe.animateTo(1f, tween(220, easing = FastOutSlowInEasing))
    }
    val p = shown ?: return
    if (enter.value <= 0.01f && projection == null) return
    val (word, tone) = when (p.verdict) {
        com.haraan.app.vision.LbwVerdict.HITTING -> "Hitting" to Rec
        com.haraan.app.vision.LbwVerdict.MISSING -> "Missing" to Good
        com.haraan.app.vision.LbwVerdict.UMPIRES_CALL -> "Umpire's call" to Color(0xFFD97706)
        com.haraan.app.vision.LbwVerdict.UNAVAILABLE -> "Not judged" to Color(0xFF475569)
    }
    val note = (if (p.verdict == com.haraan.app.vision.LbwVerdict.UNAVAILABLE) p.basis else p.limbs.firstOrNull()?.answer ?: p.basis)
        .substringBefore(" — ")
        .replaceFirstChar(Char::uppercase)
    Column(
        Modifier
            .padding(bottom = 10.dp)
            .graphicsLayer {
                alpha = enter.value.coerceIn(0f, 1f)
                translationY = (1f - enter.value) * 12.dp.toPx()
            },
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Row(
            Modifier
                .height(34.dp)
                .clip(RoundedCornerShape(8.dp))
                .border(1.dp, Color.White.copy(alpha = 0.18f), RoundedCornerShape(8.dp)),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.fillMaxHeight().background(Color(0xF20B1220)).padding(horizontal = 11.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text("WICKETS", color = Color.White.copy(alpha = 0.78f), fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
            }
            Box(Modifier.fillMaxHeight().width(132.dp).background(Color(0xF20B1220))) {
                Box(
                    Modifier
                        .fillMaxSize()
                        .graphicsLayer { scaleX = wipe.value; transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0.5f) }
                        .background(tone),
                )
                Text(
                    word,
                    color = Color.White.copy(alpha = wipe.value.coerceIn(0f, 1f)),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Black,
                    modifier = Modifier.align(Alignment.CenterStart).padding(start = 11.dp),
                )
            }
        }
        Spacer(Modifier.height(5.dp))
        Text(
            note,
            color = Color.White.copy(alpha = 0.8f),
            fontSize = 11.5.sp,
            fontWeight = FontWeight.Medium,
            maxLines = 1,
            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            modifier = Modifier
                .widthIn(max = 260.dp)
                .clip(RoundedCornerShape(999.dp))
                .background(Scrim)
                .padding(horizontal = 10.dp, vertical = 3.dp),
        )
    }
}

/** What the last delivery measured. [figure] is the big number; [unit] sits beside it. */
private data class DeliveryReadout(val figure: String, val unit: String, val detail: String)

/**
 * The delivery, landed.
 *
 * Arrives on a spring and leaves on a fade — it rises from the status line like the number
 * came out of it. Holds the last value while it leaves, so the text does not blank a frame
 * before the fade has started.
 */
@Composable
private fun DeliveryReadoutView(readout: DeliveryReadout?, alignEnd: Boolean) {
    var shown by remember { mutableStateOf(readout) }
    if (readout != null) shown = readout
    val presence by animateFloatAsState(
        targetValue = if (readout != null) 1f else 0f,
        animationSpec = if (readout != null) spring(dampingRatio = 0.7f, stiffness = 380f) else tween(200),
        label = "readout",
    )
    val value = shown ?: return
    if (presence <= 0.01f) return
    val legible = androidx.compose.ui.text.TextStyle(
        shadow = androidx.compose.ui.graphics.Shadow(Color.Black.copy(alpha = 0.55f), Offset(0f, 1.5f), 8f),
    )
    Column(
        Modifier
            .graphicsLayer {
                alpha = presence.coerceIn(0f, 1f)
                translationY = (1f - presence) * 14.dp.toPx()
            }
            .padding(bottom = 10.dp),
        horizontalAlignment = if (alignEnd) Alignment.End else Alignment.CenterHorizontally,
    ) {
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                value.figure,
                color = Color.White,
                fontSize = 46.sp,
                fontFamily = ArchivoDisplay,
                letterSpacing = (-1.2).sp,
                style = legible,
            )
            Spacer(Modifier.width(5.dp))
            Text(
                value.unit,
                color = Color.White.copy(alpha = 0.78f),
                fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold,
                style = legible,
                modifier = Modifier.padding(bottom = 9.dp),
            )
        }
        if (value.detail.isNotBlank()) {
            Text(
                value.detail,
                color = Color.White.copy(alpha = 0.85f),
                fontSize = 13.5.sp,
                fontWeight = FontWeight.Medium,
                style = legible,
            )
        }
    }
}

/**
 * REC and the running time, the way a camera says it is filming.
 *
 * The dot blinks at one hertz — the one blink on this screen, and only while it is true.
 */
@Composable
private fun RecTimecode(recording: Boolean, fps: Int? = null) {
    var elapsedMs by remember { mutableStateOf(0L) }
    LaunchedEffect(recording) {
        val start = android.os.SystemClock.elapsedRealtime()
        while (recording) {
            elapsedMs = android.os.SystemClock.elapsedRealtime() - start
            delay(200)
        }
    }
    val blinkOn = (elapsedMs / 500) % 2 == 0L
    val seconds = elapsedMs / 1000
    Row(
        Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(Scrim)
            .padding(horizontal = 11.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(8.dp)
                .clip(CircleShape)
                .background(Rec.copy(alpha = if (blinkOn) 1f else 0.25f)),
        )
        Spacer(Modifier.width(7.dp))
        Text(
            "REC",
            color = Color.White,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.sp,
        )
        Spacer(Modifier.width(9.dp))
        Text(
            "%02d:%02d".format(seconds / 60, seconds % 60),
            color = Color.White,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            // Tabular figures, so the time ticks without the pill twitching.
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"),
        )
        if (fps != null) {
            Spacer(Modifier.width(9.dp))
            // What the camera agreed to film at — never the number asked for.
            Text("$fps fps", color = Color.White.copy(alpha = 0.7f), fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
    }
}

/** A round control in the top bar: dark glass at rest, brand blue while its panel is open. */
@Composable
private fun ChromeIconButton(
    active: Boolean,
    description: String,
    onClick: () -> Unit,
    glyph: @Composable (tint: Color) -> Unit,
) {
    Box(
        Modifier
            .pressable()
            .size(40.dp)
            .clip(CircleShape)
            .background(if (active) Accent else Scrim)
            .clickableCapture(enabled = true, onClick = onClick)
            .semantics { contentDescription = description },
        contentAlignment = Alignment.Center,
    ) {
        glyph(Color.White.copy(alpha = if (active) 1f else 0.9f))
    }
}

/**
 * One of the two tools, with its name under it.
 *
 * Labelled because an unlabelled icon on this screen is a riddle handed to a stranger at a
 * boundary. Selected is a blue tint and a blue edge, the app's selection language — a solid
 * fill would read as a second shutter.
 */
/**
 * The stump calibration's instructions: which end, which tap is next, and the way out.
 * The end can only be changed before the first tap — every tap after it means something
 * different at the other end.
 */
@Composable
private fun StumpCalibrationPrompt(
    end: com.haraan.app.vision.CameraEnd,
    tapsDone: Int,
    note: String?,
    onEnd: (com.haraan.app.vision.CameraEnd) -> Unit,
    onUndo: () -> Unit,
    onCancel: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        if (tapsDone == 0) {
            Row(
                Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color.Black.copy(alpha = 0.66f))
                    .padding(3.dp),
            ) {
                listOf(
                    com.haraan.app.vision.CameraEnd.BOWLER to "Behind bowler",
                    com.haraan.app.vision.CameraEnd.STRIKER to "Behind batter",
                ).forEach { (option, label) ->
                    val selected = option == end
                    Text(
                        label,
                        color = if (selected) Color.White else Color.White.copy(alpha = 0.65f),
                        fontSize = 12.5.sp,
                        fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (selected) Color(0xFF2563EB) else Color.Transparent)
                            .clickableCapture(enabled = true) {
                                view.performHapticFeedback(Feel.SELECT)
                                onEnd(option)
                            }
                            .padding(horizontal = 14.dp, vertical = 8.dp),
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
        }
        Column(
            Modifier
                .clip(RoundedCornerShape(12.dp))
                .background(Color.Black.copy(alpha = 0.7f))
                .padding(horizontal = 14.dp, vertical = 10.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                "STEP ${tapsDone + 1} OF 6",
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 9.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.4.sp,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                com.haraan.app.vision.StumpCalibration.PROMPTS[tapsDone.coerceIn(0, 5)],
                color = Ink,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
            )
            if (note != null) {
                Spacer(Modifier.height(5.dp))
                Text(note, color = Color(0xFFFCA5A5), fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
            }
        }
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (tapsDone > 0) {
                Text(
                    "Undo",
                    color = Color(0xFFBDD3FF),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.Black.copy(alpha = 0.66f))
                        .clickableCapture(enabled = true, onClick = onUndo)
                        .padding(horizontal = 16.dp, vertical = 9.dp),
                )
            }
            Text(
                "Cancel",
                color = Color(0xFFBDD3FF),
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(RoundedCornerShape(10.dp))
                    .background(Color.Black.copy(alpha = 0.66f))
                    .clickableCapture(enabled = true, onClick = onCancel)
                    .padding(horizontal = 16.dp, vertical = 9.dp),
            )
        }
    }
}

/** A crosshair over a stump: "fix the camera from the stumps". */
@Composable
private fun CalibrateGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier) {
        val s = 1.6.dp.toPx()
        val c = Offset(size.width / 2f, size.height / 2f)
        drawCircle(tint, radius = size.minDimension * 0.34f, center = c, style = Stroke(s))
        drawLine(tint, Offset(c.x, 0f), Offset(c.x, size.height * 0.28f), s, StrokeCap.Round)
        drawLine(tint, Offset(c.x, size.height * 0.72f), Offset(c.x, size.height), s, StrokeCap.Round)
        drawLine(tint, Offset(0f, c.y), Offset(size.width * 0.28f, c.y), s, StrokeCap.Round)
        drawLine(tint, Offset(size.width * 0.72f, c.y), Offset(size.width, c.y), s, StrokeCap.Round)
        drawCircle(tint, radius = 1.6.dp.toPx(), center = c)
    }
}

/**
 * Auto, on and off. Red-dotted while on, because on means the phone is filming.
 */
@Composable
private fun AutoChip(on: Boolean, onToggle: () -> Unit) {
    Row(
        Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(if (on) Color(0xFF2563EB) else Color.White.copy(alpha = 0.12f))
            .clickableCapture(enabled = true, onClick = onToggle)
            .padding(horizontal = 11.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (on) {
            Box(Modifier.size(6.dp).clip(CircleShape).background(Color(0xFFFF5A5F)))
            Spacer(Modifier.width(6.dp))
        }
        Text("Auto", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

/** "Delivery detected", the moment Auto hears a ball — the same words FullTrack users know. */
@Composable
private fun DeliveryDetectedFlash(trigger: Int, modifier: Modifier = Modifier) {
    val show = remember { Animatable(0f) }
    LaunchedEffect(trigger) {
        if (trigger == 0) return@LaunchedEffect
        show.snapTo(0f)
        show.animateTo(1f, tween(160))
        delay(1_100)
        show.animateTo(0f, tween(260))
    }
    if (show.value <= 0.01f) return
    Text(
        "Delivery detected",
        color = Color.White,
        fontSize = 13.sp,
        fontWeight = FontWeight.Bold,
        modifier = modifier
            .graphicsLayer {
                alpha = show.value
                scaleX = 0.92f + 0.08f * show.value
                scaleY = 0.92f + 0.08f * show.value
            }
            .clip(RoundedCornerShape(999.dp))
            .background(Rec)
            .padding(horizontal = 14.dp, vertical = 7.dp),
    )
}

/** How long Auto films after the ball is done: the batter's shot and the follow-through. */
private const val AUTO_TAIL_MS = 1_200L

/** The result sits on the live picture this long before the replay plays itself. */
private const val AUTO_REPLAY_AFTER_MS = 0L

/** How long a called ball is read before its result and replay are shown; see analysingSince. */
private const val RESULT_AFTER_MS = 2_000L

/**
 * "Reading the ball…" with a bar that fills over [RESULT_AFTER_MS] from [since] (uptime ms):
 * the person knows the result is coming, and when.
 */
@Composable
private fun ReadingBallChip(since: Long) {
    var now by remember(since) { mutableLongStateOf(android.os.SystemClock.uptimeMillis()) }
    LaunchedEffect(since) {
        while (now - since < RESULT_AFTER_MS) {
            withFrameMillis { now = android.os.SystemClock.uptimeMillis() }
        }
    }
    val share = ((now - since).toFloat() / RESULT_AFTER_MS).coerceIn(0f, 1f)
    Column(
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Scrim)
            .padding(horizontal = 14.dp, vertical = 7.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("Reading the ball…", color = Ink.copy(alpha = 0.92f), fontSize = 12.sp, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(5.dp))
        Box(Modifier.width(96.dp).height(2.dp).clip(RoundedCornerShape(1.dp)).background(Ink.copy(alpha = 0.18f))) {
            Box(Modifier.fillMaxWidth(share).fillMaxHeight().background(Color(0xFF2563EB)))
        }
    }
}

/** The pause before the next Auto segment starts. */
private const val AUTO_REARM_MS = 400L

/** How long a 3D replay stays up in Auto once it has played. */
private const val AUTO_REPLAY_HOLD_MS = 2_500L

@Composable
private fun ToolButton(
    label: String,
    active: Boolean,
    onClick: () -> Unit,
    glyph: @Composable (tint: Color) -> Unit,
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            Modifier
                .pressable()
                .size(46.dp)
                .clip(CircleShape)
                .background(if (active) Accent.copy(alpha = 0.30f) else Scrim)
                .border(1.5.dp, if (active) SelectedBlue else Color.Transparent, CircleShape)
                .clickableCapture(enabled = true, onClick = onClick)
                .semantics { contentDescription = label },
            contentAlignment = Alignment.Center,
        ) {
            glyph(if (active) SelectedBlue else Color.White.copy(alpha = 0.92f))
        }
        Spacer(Modifier.height(5.dp))
        Text(
            label,
            color = Color.White.copy(alpha = 0.9f),
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            style = androidx.compose.ui.text.TextStyle(
                shadow = androidx.compose.ui.graphics.Shadow(Color.Black.copy(alpha = 0.6f), Offset(0f, 1f), 6f),
            ),
        )
    }
}

/** Dips under the finger and springs back — the difference between a drawing and a part. */
@Composable
private fun Modifier.pressable(): Modifier {
    // Reads the press from the pointer directly, so it composes with clickableCapture's
    // own interaction source without the two fighting over one.
    var down by remember { mutableStateOf(false) }
    val scale by animateFloatAsState(
        targetValue = if (down) 0.88f else 1f,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 800f),
        label = "press",
    )
    return this
        .graphicsLayer { scaleX = scale; scaleY = scale }
        .pointerInput(Unit) {
            awaitEachGesture {
                awaitFirstDown(requireUnconsumed = false)
                down = true
                waitForUpOrCancellation()
                down = false
            }
        }
}

// ───────────────────────────────────────────────────────────── Glyphs ─────

/** The aiming guide's own picture: a pitch narrowing away, with its two creases. */
@Composable
private fun PitchGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val s = 1.6.dp.toPx()
        val nearL = Offset(w * 0.14f, h * 0.86f)
        val nearR = Offset(w * 0.86f, h * 0.86f)
        val farL = Offset(w * 0.36f, h * 0.16f)
        val farR = Offset(w * 0.64f, h * 0.16f)
        drawLine(tint, nearL, farL, s, StrokeCap.Round)
        drawLine(tint, nearR, farR, s, StrokeCap.Round)
        drawLine(tint, nearL, nearR, s, StrokeCap.Round)
        drawLine(tint, farL, farR, s, StrokeCap.Round)
    }
}

/** Three stumps and the bails: the thing the wicket tool places. */
@Composable
private fun StumpsGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val s = 1.7.dp.toPx()
        listOf(0.28f, 0.5f, 0.72f).forEach { x ->
            drawLine(tint, Offset(w * x, h * 0.28f), Offset(w * x, h * 0.88f), s, StrokeCap.Round)
        }
        drawLine(tint, Offset(w * 0.22f, h * 0.16f), Offset(w * 0.78f, h * 0.16f), s, StrokeCap.Round)
    }
}

@Composable
private fun TuningGearGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val strokeW = 1.4.dp.toPx()
        val r = 2.2.dp.toPx()
        listOf(0.26f to 0.36f, 0.52f to 0.68f, 0.78f to 0.30f).forEach { (y, knob) ->
            drawLine(tint.copy(alpha = 0.45f), Offset(w * 0.1f, h * y), Offset(w * 0.9f, h * y), strokeW, StrokeCap.Round)
            drawCircle(tint, radius = r, center = Offset(w * knob, h * y))
        }
    }
}

@Composable
private fun ExitCrossGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val strokeW = 1.8.dp.toPx()
        drawLine(tint, Offset(w * 0.18f, h * 0.18f), Offset(w * 0.82f, h * 0.82f), strokeW, StrokeCap.Round)
        drawLine(tint, Offset(w * 0.82f, h * 0.18f), Offset(w * 0.18f, h * 0.82f), strokeW, StrokeCap.Round)
    }
}

/**
 * The four taps, in the order [com.haraan.app.vision.PitchGeometry.CALIBRATION_CORNERS_M]
 * expects them: clockwise from the striker's left, near crease first.
 *
 * Order is not a detail. The homography maps whatever it is handed, so corners collected
 * in the wrong sequence produce a map that solves perfectly and reads every length wrong.
 */
private val CORNER_PROMPTS = listOf(
    "Tap the NEAR crease corner on your left",
    "Now the NEAR corner on your right",
    "Now the FAR corner on the right",
    "Last one: the FAR corner on the left",
)

/**
 * Taps with no ripple — this screen is mostly a viewfinder.
 *
 * No ripple used to mean no acknowledgement at all: a tap on a control here landed in
 * total silence, on the one screen in the app where the finger is the only sense with any
 * attention to spare. The haptic is now what the ripple would have been. Pass null for a
 * control that fires its own, so nothing buzzes twice.
 */
@Composable
private fun Modifier.clickableCapture(
    enabled: Boolean,
    haptic: Int? = Feel.SELECT,
    onClick: () -> Unit,
): Modifier {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val view = LocalView.current
    // `this.then(clickable(…))` — which is what this was — resolves the bare `clickable`
    // against the same implicit receiver and then appends the result to it, putting the
    // whole incoming chain into the final modifier twice. Chaining directly is what it
    // was always meant to say.
    return this.clickable(
        interactionSource = interaction,
        indication = null,
        enabled = enabled,
    ) {
        haptic?.let { view.performHapticFeedback(it) }
        onClick()
    }
}

// THE REVIEW CONTRACT, mirrored from the server.
//
// DeliveryReview holds the authoritative numbers; these exist so the phone refuses a clip
// it already knows will be refused, before spending a ground's upload on it. If the server
// limits move, these move with them.

/** Server ceiling is 10s; stop just under it so container rounding cannot push us over. */
/** Points kept on the live overlay. Enough to see a path, few enough to stay readable. */
private const val CAMERA_TRAIL_POINTS = 30

/**
 * Analysed frames a confirmed lock must have held before it is checked only on one frame
 * in [STEADY_CHECK_EVERY]. About two thirds of a second: long enough that a lock still
 * settling gets every frame, short enough that the saving starts almost at once.
 */
internal const val ANALYSIS_TAG = "HaraanCameraAnalysis"

/** Consecutive frames one tap of the developer dump writes. */
private const val DUMP_BURST = 8

/** Haptic ticks while the length guide is laid down: about one per band. */
private const val GUIDE_REVEAL_TICKS = 4

/** The length guide stops at this share of the screen's height, above the controls. */
private const val GUIDE_SAFE_BOTTOM = 0.72f

/** Sightings a later flight needs before it replaces one already called in the same clip. */
private const val SUPERSEDE_MIN_POINTS = 5

private const val STEADY_LOCK_FRAMES = 20
private const val STEADY_CHECK_EVERY = 3

/** Quiet camera-clock ms before the empty-frame search slows to one frame in [SEARCH_BACKOFF_EVERY]. */
private const val SEARCH_BACKOFF_AFTER_MS = 4_000L
private const val SEARCH_BACKOFF_EVERY = 3

/** How long one validation recording runs: about ninety analysed frames. */
private const val VALIDATION_RECORD_MS = 3_000L

/**
 * The back camera's lens, as Camera2 reports it — the camera CameraX's DEFAULT_BACK_CAMERA
 * binds (the first LENS_FACING_BACK id).
 *
 * Preferred source: LENS_INTRINSIC_CALIBRATION, the focal length in active-array pixels,
 * measured per device at the factory. Fallback: the nominal focal length over the sensor's
 * physical width. Both are mapped onto the analysis frame on the assumption that a 16:9
 * stream uses the sensor's full width — see [com.haraan.app.vision.CameraIntrinsics].
 */
private class BackLens(
    val focalMm: Double,
    val sensorWidthMm: Double,
    /** Calibrated focal over active-array width, or null when the device does not publish it. */
    val calibratedFocalOverWidth: Double?,
) {
    fun intrinsics(outWidth: Int, outHeight: Int, sideways: Boolean): com.haraan.app.vision.CameraIntrinsics? {
        val uprightWidth = if (sideways) outHeight else outWidth
        calibratedFocalOverWidth?.let { ratio ->
            return com.haraan.app.vision.CameraIntrinsics(ratio * outWidth / uprightWidth, "lens calibration")
        }
        return com.haraan.app.vision.CameraIntrinsics.fromLens(focalMm, sensorWidthMm, outWidth, outHeight, sideways)
    }
}

private fun readBackLens(context: Context): BackLens? = runCatching {
    val manager = context.getSystemService(Context.CAMERA_SERVICE) as android.hardware.camera2.CameraManager
    val id = manager.cameraIdList.firstOrNull { cid ->
        manager.getCameraCharacteristics(cid).get(android.hardware.camera2.CameraCharacteristics.LENS_FACING) ==
            android.hardware.camera2.CameraCharacteristics.LENS_FACING_BACK
    } ?: return null
    val c = manager.getCameraCharacteristics(id)
    val focal = c.get(android.hardware.camera2.CameraCharacteristics.LENS_INFO_AVAILABLE_FOCAL_LENGTHS)?.firstOrNull()
        ?: return null
    val sensor = c.get(android.hardware.camera2.CameraCharacteristics.SENSOR_INFO_PHYSICAL_SIZE) ?: return null
    val calibrated = if (android.os.Build.VERSION.SDK_INT >= 28) {
        val k = c.get(android.hardware.camera2.CameraCharacteristics.LENS_INTRINSIC_CALIBRATION)
        val active = c.get(android.hardware.camera2.CameraCharacteristics.SENSOR_INFO_ACTIVE_ARRAY_SIZE)
        if (k != null && k.isNotEmpty() && k[0] > 0f && active != null && active.width() > 0) {
            k[0].toDouble() / active.width()
        } else {
            null
        }
    } else {
        null
    }
    BackLens(focal.toDouble(), sensor.width.toDouble(), calibrated)
}.getOrNull()

/**
 * The raw YUV planes of one analysis frame, for replaying a real scene through the detector
 * at a desk. Format: "HYUV", then big-endian ints width, height, rotation, and for each of
 * the three planes rowStride, pixelStride, byte count, bytes.
 */
private fun dumpFrame(image: androidx.camera.core.ImageProxy, dir: File): String {
    dir.mkdirs()
    val file = File(dir, "frame-${System.currentTimeMillis()}.hyuv")
    java.io.DataOutputStream(java.io.BufferedOutputStream(java.io.FileOutputStream(file))).use { out ->
        out.writeBytes("HYUV")
        out.writeInt(image.width)
        out.writeInt(image.height)
        out.writeInt(image.imageInfo.rotationDegrees)
        for (plane in image.planes) {
            val buf = plane.buffer
            buf.rewind()
            val bytes = ByteArray(buf.remaining())
            buf.get(bytes)
            buf.rewind()
            out.writeInt(plane.rowStride)
            out.writeInt(plane.pixelStride)
            out.writeInt(bytes.size)
            out.write(bytes)
        }
    }
    return file.absolutePath
}

/** Write the validation log to the app's external files; returns the path for `adb pull`. */
private fun saveValidationCsv(context: Context, log: com.haraan.app.vision.WicketValidation, cameraSource: String?): String =
    runCatching {
        val dir = File(context.getExternalFilesDir(null), "validation").apply { mkdirs() }
        val stamp = java.text.SimpleDateFormat("yyyyMMdd-HHmmss", java.util.Locale.ROOT).format(java.util.Date())
        val file = File(dir, "stumps-$stamp.csv")
        file.writeText(log.csv(cameraSource))
        file.absolutePath
    }.getOrElse { "save failed: ${it.message}" }

/**
 * How far the phone is rolled off the picture's own level, in degrees, from gravity.
 *
 * MAGNITUDE ONLY, ON PURPOSE. The angle is measured off the NEAREST quarter turn of the
 * device, which is the picture's up however the phone is held, so no display-rotation
 * convention can get its sign backwards — and nothing uses its sign: it drives one hint
 * and one diagnostics row. Null when the phone points at the sky or the ground, where roll
 * has no meaning, or when the device has no such sensor.
 *
 * TYPE_GRAVITY is the fused, low-pass sensor: low power, and steady enough to read at UI
 * rate for a whole match.
 */
@Composable
private fun rememberDeviceRoll(enabled: Boolean): androidx.compose.runtime.State<Float?> {
    val context = LocalContext.current
    val roll = remember { mutableStateOf<Float?>(null) }
    // Only while someone is looking at it: the readout is its one reader, and a sensor
    // streaming into Compose state for a three-hour match is battery for nothing.
    androidx.compose.runtime.DisposableEffect(context, enabled) {
        if (!enabled) {
            roll.value = null
            return@DisposableEffect onDispose { }
        }
        val manager = context.getSystemService(android.content.Context.SENSOR_SERVICE) as? android.hardware.SensorManager
        val sensor = manager?.getDefaultSensor(android.hardware.Sensor.TYPE_GRAVITY)
            ?: manager?.getDefaultSensor(android.hardware.Sensor.TYPE_ACCELEROMETER)
        val listener = object : android.hardware.SensorEventListener {
            override fun onSensorChanged(event: android.hardware.SensorEvent) {
                val gx = event.values[0]
                val gy = event.values[1]
                val gz = event.values[2]
                val inPlane = kotlin.math.hypot(gx, gy)
                if (inPlane < 0.5f * kotlin.math.hypot(inPlane, gz)) {
                    roll.value = null
                    return
                }
                val angle = Math.toDegrees(kotlin.math.atan2(gx.toDouble(), gy.toDouble()))
                var off = ((angle % 90.0) + 90.0) % 90.0
                if (off > 45.0) off -= 90.0
                val next = off.toFloat()
                roll.value = roll.value?.let { it + (next - it) * 0.2f } ?: next
            }

            override fun onAccuracyChanged(sensor: android.hardware.Sensor?, accuracy: Int) = Unit
        }
        if (manager != null && sensor != null) {
            manager.registerListener(listener, sensor, android.hardware.SensorManager.SENSOR_DELAY_UI)
        }
        onDispose { manager?.unregisterListener(listener) }
    }
    return roll
}

/**
 * How well-behaved a sighting was, as a colour: amber for barely, blue for thoroughly.
 *
 * WHAT THIS IS NOT. [com.haraan.app.vision.BallSighting.trackingConfidence] goes out of
 * its way not to call itself a confidence or a probability, because nothing in the tracker
 * has ever been checked against a human's judgement of the same footage. It ranks
 * candidates against each other: how round the thing was, and how well it continued the
 * track. So this ramp is not "how likely is that the ball" and the screen never says it
 * is. It is "how much did that behave like one", which is a smaller claim and an honest
 * one — and, usefully, the exact claim an operator needs, because a tracker that has
 * latched onto a fielder produces a trail of low-scoring points and always has.
 *
 * The ends of the ramp are the ends of the range the tracker can actually emit, not 0 and
 * 1. A candidate below MIN_CIRCULARITY or off the trajectory is discarded rather than
 * scored, so the lowest score that ever reaches this is around 0.3; spreading the ramp
 * across the full unit interval would paint every real track the same mid-blue and the
 * distinction would be invisible — which is the only thing it is for.
 */
internal fun trackColour(score: Float): Color {
    val t = ((score - TRACK_SCORE_FLOOR) / (TRACK_SCORE_CEILING - TRACK_SCORE_FLOOR))
        .coerceIn(0f, 1f)
    return lerp(TrailWeak, TrailStrong, t)
}

/** Barely ball-like. Deliberately not the yellow the corner-tapping flow owns. */
internal val TrailWeak = Color(0xFFF59E0B)

/** Round, and continuing the track as a thrown ball would. */
internal val TrailStrong = Color(0xFF6E9BF5)

/**
 * The bounce marker. Its own colour, because it is its own kind of thing: everything else
 * drawn over the pitch is a guess about where the ball was, and this is the one place the
 * app can say it knows.
 */
private val BounceMark = Color(0xFF4ADE80)

/*
 * THE SPAN OF SCORES WORTH TELLING APART.
 *
 * A sighting scores circularity × 0.6 + continuity × 0.4. The filters upstream mean the
 * lowest score that can ever reach this is around 0.27 — MIN_CIRCULARITY is 0.45, and a
 * candidate whose continuity hits zero is thrown away rather than scored — and the
 * arithmetic top is 1.0.
 *
 * Neither end of that arithmetic range is where the ramp ends, on purpose.
 *
 * The floor sits ABOVE the worst possible score so the bottom of the range saturates:
 * everything between a just-accepted 0.27 and 0.38 is equally "barely ball-like", and
 * grading within it would draw distinctions the numbers cannot support.
 *
 * The ceiling sits below 1.0 because a real ball in flight is motion-blurred into an oval
 * and pays for it in circularity. Requiring a perfect score for full blue would mean a
 * correctly tracked delivery never quite gets there, and a signal that never reaches its
 * good end is a signal nobody learns to read.
 */
private const val TRACK_SCORE_FLOOR = 0.38f
private const val TRACK_SCORE_CEILING = 0.78f

/**
 * How far the oldest end of the trail has faded, and how solid the newest end is.
 *
 * The floor is not zero on purpose: a point that has faded to nothing still occupies the
 * count on the status line, and an overlay that claims thirty points while showing eight
 * is lying about how much the tracker is actually holding on to.
 */
private const val TRAIL_FADE_FROM = 0.14f
private const val TRAIL_FADE_TO = 0.85f

/** Consecutive failed heartbeats before a camera is treated as genuinely gone. */
private const val MAX_MISSED_HEARTBEATS = 3

private const val REVIEW_CLIP_MS = 9_500L

/** After the cap's stop, how long a clip's Finalize may take before the clip is let go. */
private const val FINALIZE_GRACE_MS = 5_000L

/** An Auto segment younger than this becomes the scorer's ball's clip rather than being cut. */
private const val ADOPT_SEGMENT_MAX_AGE_MS = 3_000L

/** How often the camera asks whether the scorer's BALL window is open. */
private const val CUE_IDLE_MS = 1_000L

/** Tighter while filming, so the clip ends close to the scorer's result tap. */
private const val CUE_FILMING_MS = 600L

/** Matches DeliveryReview::MAX_REVIEW_BYTES. */
private const val MAX_REVIEW_BYTES = 50L * 1024 * 1024

// ────────────────────────────────────────────── Picture space, not view space ─────

/**
 * Where the camera's picture actually sits inside this view.
 *
 * The preview is fitted, not filled, so on a tall phone showing a 16:9 frame there are
 * black bars and the picture is SHORTER than the view it lives in. Every overlay here is
 * normalised inside the picture, so every overlay has to be drawn into this rectangle —
 * painting across the whole view instead stretches the pitch outline and the ball trail
 * by the height of the bars, which is most visible at the top and bottom of the frame:
 * the far crease and the near one.
 *
 * Falls back to the whole view until the first analysis frame has reported a shape, which
 * is the old behaviour and is only ever on screen for a frame or two.
 */
internal fun frameRect(width: Float, height: Float, aspect: Float): Rect {
    if (aspect <= 0f || width <= 0f || height <= 0f) return Rect(0f, 0f, width, height)
    return if (width / height > aspect) {
        // The view is wider than the picture: bars down the sides.
        val fitted = height * aspect
        val left = (width - fitted) / 2f
        Rect(left, 0f, left + fitted, height)
    } else {
        // Taller: bars top and bottom. The usual case on a phone held upright.
        val fitted = width / aspect
        val top = (height - fitted) / 2f
        Rect(0f, top, width, top + fitted)
    }
}

/** A normalised point in the picture, placed on the screen. */
internal fun Rect.at(x: Float, y: Float) = Offset(left + x * width, top + y * height)

/** And back again: a point on the screen, in the picture's own 0..1 terms. */
internal fun Rect.normalise(point: Offset) =
    Offset((point.x - left) / width, (point.y - top) / height)

// ─────────────────────────────────────────────────────── CameraX plumbing ─────

/*
 * A FIXED RATE, ON EVERY STREAM — 60 where the phone can, else 30.
 *
 * Asking only the video stream for 30 was not enough: CameraX bound "30" and a Realme
 * RMX3933 still ran the session at a fixed 20 (the repeating request carried [20, 20]) —
 * a third fewer ball sightings per delivery. The exposure frame-rate range is set directly
 * on the preview and analysis requests too. Fixed rather than [x, 30], so the camera never
 * slows itself down for exposure mid-delivery; in poor light the picture is darker instead,
 * which the tracker copes with far better than missing frames.
 */
@androidx.annotation.OptIn(androidx.camera.camera2.interop.ExperimentalCamera2Interop::class)
private fun <T> lockFrameRate(builder: androidx.camera.core.ExtendableBuilder<T>, fps: Int = TARGET_FPS) {
    androidx.camera.camera2.interop.Camera2Interop.Extender(builder).setCaptureRequestOption(
        android.hardware.camera2.CaptureRequest.CONTROL_AE_TARGET_FPS_RANGE,
        android.util.Range(fps, fps),
    )
}

private const val TARGET_FPS = 30

/** "Highest" never asks above this: what a session with preview, analysis and video can run. */
private const val HIGHEST_MAX_FPS = 60

/** "Highest" records at most this many bits a second, so a 9.5 s clip stays under 50 MB. */
private const val HIGHEST_BITRATE = 32_000_000

/** The live analyser skips frames closer together than this: ~30 a second at 60 fps. */
private const val LIVE_MIN_FRAME_MS = 20L

/**
 * THE STUMPS AND THE GROUND ARE DRAWN FROM THE STEADIED LOCK, NOT THIS FRAME'S.
 *
 * The far set is a few pixels wide, and the live lock moves by one or two of them from frame
 * to frame. The length guide is solved from it, so the whole pitch of bands swam and shook
 * on a phone standing still. The steadied lock ([com.haraan.app.vision.SteadyWicket]: medians
 * over the recent confirmed frames) is drawn instead, with the live lock's state, so a
 * coasting lock still looks coasting. A hand-placed (Setup) lock never jitters and is drawn as
 * it is; so is a live lock that has left the steady one behind — the phone was moved.
 */
private fun drawingLock(
    live: com.haraan.app.vision.WicketLock,
    steady: com.haraan.app.vision.WicketLock?,
): com.haraan.app.vision.WicketLock {
    if (steady == null || live.source == com.haraan.app.vision.WicketLockSource.MANUAL) return live
    if (live.kind != steady.kind) return live
    val span = steady.anchor.span(1f).toDouble().coerceAtLeast(1e-4)
    val off = kotlin.math.hypot(live.anchor.base.x - steady.anchor.base.x, live.anchor.base.y - steady.anchor.base.y)
    return if (off > span * DRAW_STEADY_MAX_SPANS) live else live.copy(anchor = steady.anchor)
}

/** How far (in the stumps' own widths) the live lock may sit from the steady one and still be drawn there. */
private const val DRAW_STEADY_MAX_SPANS = 1.0

/**
 * WHICH CAMERA THE 3D FLIGHT IS FITTED THROUGH.
 *
 * Corners a person tapped (or the stump calibration placed) are the best there is. Corners the
 * pitch DETECTOR found are not, wherever there are stumps to measure from instead: on a tiled
 * backyard floor it found "creases" in the tile lines, and a 25 km/h throw came out as
 * 117 km/h pitching 11 m behind the stumps. The stumps with the phone's measured tilt
 * ([com.haraan.app.vision.PitchGround.best]) are the stronger camera there.
 *
 * Returns the quad to fit through (null for none) and the stumps' camera (null when a quad is
 * used, or there are no stumps).
 */
private fun flightCameras(
    found: com.haraan.app.vision.PitchQuad?,
    lock: com.haraan.app.vision.WicketLock?,
    camera: com.haraan.app.vision.CameraIntrinsics?,
    uprightWidthPx: Int,
    uprightAspect: Float,
    bowlersEnd: com.haraan.app.vision.BowlersEnd? = null,
): Pair<com.haraan.app.vision.PitchQuad?, com.haraan.app.vision.PitchCamera?> {
    val ground = lock?.let { l ->
        val cam = camera ?: return@let null
        if (uprightWidthPx <= 0 || uprightAspect <= 0f) return@let null
        val h = (uprightWidthPx / uprightAspect).toInt()
        com.haraan.app.vision.PitchGround.best(l, cam, uprightWidthPx, h, bowlersEnd = bowlersEnd)?.pitchCamera(uprightWidthPx, h)
    }
    val quad = found?.takeIf { it.source != com.haraan.app.vision.QuadSource.DETECTED || ground == null }
    return quad to (if (quad != null) null else ground)
}

/**
 * THE BATTER'S STUMPS, NOT THE ONES THE PHONE STANDS BEHIND.
 *
 * Filmed from behind the bowler's arm at a real net (vivo I2407), the bowler's-end stumps
 * stood three metres in front of the lens, filled the bottom of the picture, and were the
 * set the detector found and locked — the batter's stumps 20 m down the pitch were never
 * looked at again, and the length guide, laid from the locked stumps back towards the
 * camera, had three metres to work with and drew nothing. The measured wicket is always the
 * batter's. A set whose width in the picture puts it nearer than [BOWLERS_END_MAX_M] is the
 * near end, so it is dropped before the tracker sees it and the search carries on down the
 * pitch.
 */
internal fun battersEndOnly(
    sightings: List<com.haraan.app.vision.WicketSighting>,
    camera: com.haraan.app.vision.CameraIntrinsics?,
    /**
     * The bowler's stumps, when seen. The batter's set lies beyond them — higher in the
     * picture and roughly behind them along the pitch — so anything lower or well off to the
     * side is not it. On a real net clip a stump-like shape low at the frame's right edge was
     * locked as the batter's stumps without this, and no delivery could be found after.
     */
    bowlersEnd: com.haraan.app.vision.BowlersEnd? = null,
): List<com.haraan.app.vision.WicketSighting> {
    val focal = camera?.focalFw ?: ASSUMED_FOCAL_FW
    return sightings.filterNot { s ->
        val set = (s as? com.haraan.app.vision.WicketSighting.Stumps)?.set ?: return@filterNot false
        val span = set.spanX
        if (span > 0f && focal * com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / span < BOWLERS_END_MAX_M) {
            return@filterNot true
        }
        /*
         * ...NOR FARTHER THAN A WICKET CAN BE. Pointed at a laptop on a desk (2026-10-28, Motorola
         * Edge 70 Pro) the comb found "stumps" in the edge of a box and the screen said READY at
         * 44-48 m — twice any pitch. The detector reaches 40 m by design; past that it is clutter.
         */
        if (span > 0f && focal * com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / span > BATTERS_END_MAX_M) {
            return@filterNot true
        }
        val near = bowlersEnd ?: return@filterNot false
        val base = set.baseMiddle
        base.y > near.base.y - BEYOND_BOWLER_MIN_RISE ||
            kotlin.math.abs(base.x - near.base.x) > BEHIND_BOWLER_MAX_SIDE ||
            // A pitch's length away, the batter's set looks several times narrower than the
            // bowler's; something nearly as wide is a nearer thing, not the other wicket.
            near.spanFw < span * BATTERS_MIN_NARROWER
    }
}

/**
 * [battersEndOnly], plus the batter's stumps looked for where the bowler's stumps say they
 * must be while nothing is confirmed — the same rule the clip analysis uses (see
 * [com.haraan.app.vision.FarWicketSearch.offer]). On a ground where the batter stands in
 * light trousers right behind the stumps, the whole-frame search almost never finds them.
 */
internal fun battersEndWithPredicted(
    search: com.haraan.app.vision.FarWicketSearch,
    sightings: List<com.haraan.app.vision.WicketSighting>,
    held: com.haraan.app.vision.WicketLock?,
    near: com.haraan.app.vision.BowlersEnd?,
    camera: com.haraan.app.vision.CameraIntrinsics?,
    luma: ByteArray,
    width: Int,
    height: Int,
    rowStride: Int,
    rotationDegrees: Int,
    uprightAspect: Float,
): List<com.haraan.app.vision.WicketSighting> {
    search.focalFw = camera?.focalFw ?: ASSUMED_FOCAL_FW
    return search.offer(
        battersEndOnly(sightings, camera, near), held, near,
        luma, width, height, rowStride, rotationDegrees, uprightAspect,
    ) { s, n -> battersEndOnly(s, camera, n) }
}

/** The batter's stumps sit at least this much higher in the picture than the bowler's. */
private const val BEYOND_BOWLER_MIN_RISE = 0.05

/** ...and within this much of them across the picture. */
private const val BEHIND_BOWLER_MAX_SIDE = 0.40

/** The bowler's set must look at least this many times wider than the batter's. */
private const val BATTERS_MIN_NARROWER = 3.0

/**
 * The bowler's-end stumps among a frame's sightings — the nearest set that [battersEndOnly]
 * drops — as its base, normalised; null when none is in view.
 */
internal fun bowlersEndOf(
    sightings: List<com.haraan.app.vision.WicketSighting>,
    camera: com.haraan.app.vision.CameraIntrinsics?,
): com.haraan.app.vision.BowlersEnd? {
    val focal = camera?.focalFw ?: ASSUMED_FOCAL_FW
    return sightings
        .mapNotNull { (it as? com.haraan.app.vision.WicketSighting.Stumps)?.set }
        .filter { it.spanX > 0f && focal * com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / it.spanX < BOWLERS_END_MAX_M }
        .maxByOrNull { it.spanX }
        ?.let { com.haraan.app.vision.BowlersEnd(it.baseMiddle, it.spanX.toDouble()) }
}

/** Nearer than this, a set of stumps is the bowler's end the camera stands behind. */
private const val BOWLERS_END_MAX_M = 4.0

/** The farthest the batter's stumps can be: the comb's designed reach. See [battersEndOnly]. */
private const val BATTERS_END_MAX_M = 40.0

/** A typical phone main camera, ~39° across a portrait frame, when the lens is not known yet. */
private const val ASSUMED_FOCAL_FW = 1.4

/**
 * The strip a delivery travels through when filmed from behind the bowler, and where it is
 * going: [left, top, right, bottom, targetX, targetY], normalised upright — or null when the
 * view is not that one (no stumps lock, or stumps nearer than [CORRIDOR_MIN_DISTANCE_M]).
 *
 * From the hand, above and beside the stumps on the picture, down to just below the batter's
 * stumps; snapped to a 0.02 grid so a lock that jitters by a pixel does not restart the
 * tracker's picture every second.
 */
internal fun deliveryCorridor(
    lock: com.haraan.app.vision.WicketLock?,
    camera: com.haraan.app.vision.CameraIntrinsics?,
    uprightWidthPx: Int,
    uprightAspect: Float,
): FloatArray? {
    val l = lock ?: return null
    if (l.kind != com.haraan.app.vision.WicketKind.STUMPS) return null
    // Only a settled lock: a stumps tracker re-acquiring after the bowler ran through the
    // picture can hold the wrong thing for a moment, and a corridor built on it pointed the
    // ball tracker at the wrong place for the very second the ball was bowled.
    if (l.state != com.haraan.app.vision.WicketTrackState.CONFIRMED || l.heldFrames < CORRIDOR_MIN_HELD_FRAMES) return null
    val cam = camera ?: return null
    if (uprightWidthPx <= 0 || uprightAspect <= 0f) return null
    val h = (uprightWidthPx / uprightAspect).toInt()
    /*
     * A search box, not a measurement: when the lock is too unsteady to measure from — the
     * foot of stumps with a batter's legs right behind them wanders a few pixels frame to
     * frame (IMG_6053) — its width and the lens still say roughly how far it is, which is all
     * the box needs. Speed and the 3D fit keep demanding a measurable lock.
     */
    val distance = com.haraan.app.vision.PitchGround.best(l, cam, uprightWidthPx, h)?.stumpsDistanceM
        ?: l.span.takeIf { it > 0f }?.let { cam.focalFw * com.haraan.app.vision.PitchGeometry.STUMP_CENTRES_SPAN_M / it }
        ?: return null
    if (distance < CORRIDOR_MIN_DISTANCE_M) return null
    fun snap(v: Double) = (Math.round(v / 0.02) * 0.02).toFloat()
    val bx = snap(l.anchor.base.x)
    val by = snap(l.anchor.base.y)
    return floatArrayOf(
        (bx - CORRIDOR_HALF_WIDTH).coerceAtLeast(0f),
        (by - CORRIDOR_ABOVE).coerceAtLeast(0f),
        (bx + CORRIDOR_HALF_WIDTH).coerceAtMost(1f),
        (by + CORRIDOR_BELOW).coerceAtMost(1f),
        bx,
        by,
    )
}

/** Frames a lock must have held, confirmed, before a corridor is built on it. */
private const val CORRIDOR_MIN_HELD_FRAMES = 15

/** Stumps at least this far off: the camera is behind the bowler, and the corridor applies. */
private const val CORRIDOR_MIN_DISTANCE_M = 10.0

/** Corridor reach either side of the stumps, above them (to the hand), and below their foot. */
private const val CORRIDOR_HALF_WIDTH = 0.36f
private const val CORRIDOR_ABOVE = 0.36f
private const val CORRIDOR_BELOW = 0.12f

/** How often the corridor is re-derived from the lock. */
private const val CORRIDOR_REFRESH_MS = 1_000L

/** The finer ball analysis asked for at start; see [OpenCvBallTracker.setDetail]. */
private const val BALL_DETAIL = 1.5

/**
 * Average ball-tracker milliseconds per frame above which the detail steps back to 1. A
 * Realme RMX3933 ran 1.5 at 15 ms and still analysed 27-28 of 30 frames a second; past
 * ~20 ms the other engines' share of the 33 ms frame starts to cost whole frames.
 */
private const val BALL_BUDGET_MS = 20.0

/**
 * How the ground under the stumps is known, for the admin readout and the log: the phone's
 * height measured from its tilt, or the assumption and why.
 */
internal fun groundSummary(
    lock: com.haraan.app.vision.WicketLock?,
    camera: com.haraan.app.vision.CameraIntrinsics?,
    uprightWidthPx: Int,
    uprightAspect: Float,
): String {
    val tilt = com.haraan.app.vision.DeviceTilt.steady()
    val tiltText = tilt?.let {
        "down %.1f° roll %+.1f°".format(Math.toDegrees(it.pitchRad), Math.toDegrees(it.rollRad))
    } ?: "phone moving"
    if (lock == null || camera == null || uprightWidthPx <= 0 || uprightAspect <= 0f) return "no stumps · $tiltText"
    val h = (uprightWidthPx / uprightAspect).toInt()
    val g = com.haraan.app.vision.PitchGround.best(lock, camera, uprightWidthPx, h, tilt) ?: return "unsolved · $tiltText"
    return if (g.heightMeasured) {
        "phone %.2f m up, stumps %.1f m · $tiltText".format(g.heightM, g.stumpsDistanceM)
    } else {
        "assumed %.1f m up, stumps %.1f m · $tiltText".format(g.heightM, g.stumpsDistanceM)
    }
}

/** Lets [ShutterControl] read what auto-exposure chose, frame by frame. */
@androidx.annotation.OptIn(androidx.camera.camera2.interop.ExperimentalCamera2Interop::class)
private fun <T> watchExposure(builder: androidx.camera.core.ExtendableBuilder<T>, shutter: ShutterControl) {
    androidx.camera.camera2.interop.Camera2Interop.Extender(builder).setSessionCaptureCallback(shutter.callback)
}

/** Mean of a luma plane from a sparse sample: brightness, not detail, is the question. */
private fun sampledMean(bytes: ByteArray): Double {
    if (bytes.isEmpty()) return 0.0
    var sum = 0L
    var n = 0
    var i = 0
    while (i < bytes.size) {
        sum += bytes[i].toInt() and 0xFF
        n++
        i += LUMA_SAMPLE_STRIDE
    }
    return sum.toDouble() / n
}

/** Every fifth frame is plenty to hold exposure; a prime stride avoids sampling one column. */
private const val LUMA_SAMPLE_EVERY = 5
private const val LUMA_SAMPLE_STRIDE = 97

/**
 * Feeds gravity to [com.haraan.app.vision.DeviceTilt] while the camera screen is up, so the
 * ground under the stumps is solved from the phone's real tilt rather than an assumed height.
 * TYPE_GRAVITY is the fused, low-pass sensor: cheap enough for a whole match.
 */
@Composable
private fun rememberDeviceTiltFeed() {
    val context = LocalContext.current
    androidx.compose.runtime.DisposableEffect(context) {
        val manager = context.getSystemService(android.content.Context.SENSOR_SERVICE) as? android.hardware.SensorManager
        val sensor = manager?.getDefaultSensor(android.hardware.Sensor.TYPE_GRAVITY)
            ?: manager?.getDefaultSensor(android.hardware.Sensor.TYPE_ACCELEROMETER)
        val listener = object : android.hardware.SensorEventListener {
            override fun onSensorChanged(event: android.hardware.SensorEvent) {
                com.haraan.app.vision.DeviceTilt.onGravity(
                    event.values[0], event.values[1], event.values[2], System.currentTimeMillis(),
                )
            }

            override fun onAccuracyChanged(sensor: android.hardware.Sensor?, accuracy: Int) = Unit
        }
        if (manager != null && sensor != null) {
            manager.registerListener(listener, sensor, android.hardware.SensorManager.SENSOR_DELAY_GAME)
        }
        onDispose {
            manager?.unregisterListener(listener)
            com.haraan.app.vision.DeviceTilt.clear()
        }
    }
}

private fun bindCamera(
    context: Context,
    view: PreviewView,
    lifecycleOwner: androidx.lifecycle.LifecycleOwner,
    analysisExecutor: java.util.concurrent.Executor,
    onFrame: ImageAnalysis.Analyzer,
    videoQuality: String,
    shutter: ShutterControl,
    onReady: (VideoCapture<Recorder>, ImageAnalysis, Int) -> Unit,
) {
    val future = ProcessCameraProvider.getInstance(context)
    future.addListener({
        val provider = future.get()

        /*
         * ONE SHAPE FOR ALL THREE STREAMS.
         *
         * Left alone, CameraX picks a resolution per use case: preview commonly 16:9,
         * analysis commonly 4:3, and the recorder whatever Quality.FHD is. That is three
         * different framings of the same scene, and an overlay measured in one of them
         * cannot be drawn correctly over another - which is why the trail drifted towards
         * the edges even once the rotation was right.
         *
         * 16:9 because that is what FHD records. The analysis frame is now a scaled-down
         * copy of the footage the scorer will watch, which is the only arrangement in
         * which "the ball was here" means the same thing on both.
         */
        /*
         * AND ONE SIZE EACH, ASKED FOR RATHER THAN ACCEPTED.
         *
         * Left to itself CameraX sizes the preview to the display, so a 1080p phone gets a
         * 1080p preview stream — every frame of which is produced, converted and composited
         * purely so somebody can check the aim. 720p is indistinguishable at arm's length
         * on a viewfinder and is a third of the pixels.
         *
         * The analysis stream is 720p, not the 640x360 the ball tracker alone would need.
         * That smaller size starved the STUMP detector, which is built for a 960-wide frame:
         * held upright, 640x360 is 360 px across, a stump is a pixel or two, and three of
         * them never survive the threshold. On a real phone the wicket was simply never
         * found. 720p gives it 720 px upright (1280 sideways, scaled to 960), and the ball,
         * pitch and motion engines still scale their own copy down to 480/320 as before.
         *
         * Neither touches the recording. The clip stays FHD, because that is the artefact
         * a review is built on.
         */
        fun sizedFor(target: android.util.Size) = ResolutionSelector.Builder()
            .setAspectRatioStrategy(AspectRatioStrategy.RATIO_16_9_FALLBACK_AUTO_STRATEGY)
            .setResolutionStrategy(
                ResolutionStrategy(
                    target,
                    // Prefer the nearest size at or below the target, but take a larger one
                    // over failing to bind: a bigger preview is a warm phone, no preview is
                    // a camera nobody can aim.
                    ResolutionStrategy.FALLBACK_RULE_CLOSEST_LOWER_THEN_HIGHER,
                ),
            )
            .build()

        fun previewAt(fps: Int) = Preview.Builder()
            .setResolutionSelector(sizedFor(android.util.Size(1280, 720)))
            .also { lockFrameRate(it, fps) }
            .build()
            .also { it.setSurfaceProvider(view.surfaceProvider) }

        /*
         * ANALYSIS, alongside recording rather than after it.
         *
         * This is the entire argument for doing vision on the phone: the frames are
         * already here, in memory, stamped with the camera's own clock, while the ball is
         * being bowled. A server would have to be sent twenty megabytes and decode it
         * again to reach the same pixels.
         *
         * KEEP_ONLY_LATEST is the important flag. Under back pressure the analyser drops
         * frames rather than queueing them, so a slow phone loses ball points but never
         * delays the recording. Filming is the product; vision is bolted to the side of
         * it, and it yields.
         */
        fun analysisAt(fps: Int) = ImageAnalysis.Builder()
            .setResolutionSelector(sizedFor(android.util.Size(1280, 720)))
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
            .also { lockFrameRate(it, fps) }
            .also { watchExposure(it, shutter) }
            .build()
            .also { it.setAnalyzer(analysisExecutor, onFrame) }
        /*
         * FRAME RATE BEFORE PIXELS.
         *
         * At 30 fps a 130 km/h delivery moves about 1.2 m between frames, so the bounce and
         * the pad are often between two of them. 60 halves that gap for the tracker and for
         * frame-by-frame review; 4K would not, and would quadruple every upload. So the
         * resolution stays at 1080p (720p on the smaller setting) and the rate goes up.
         *
         * A phone that cannot film 60 fps alongside preview and analysis drops to its default
         * rather than refusing to film — and reports the rate it actually got, so REC never
         * claims 60 on a phone doing 30.
         */
        val resolution = if (videoQuality == "720p60") Quality.HD else Quality.FHD
        val wantFps = if (videoQuality == "1080p30") 30 else 60
        val highest = videoQuality == com.haraan.app.data.VIDEO_QUALITY_HIGHEST

        fun buildCapture(fps: Int?, quality: Quality = resolution): VideoCapture<Recorder> {
            val recorder = Recorder.Builder()
                .setQualitySelector(
                    if (highest) {
                        // Exactly the size being tried: the search below steps down itself.
                        QualitySelector.from(quality)
                    } else {
                        QualitySelector.from(
                            quality,
                            // Steps down rather than up: a handset that cannot do the asked size
                            // should film the delivery smaller, not refuse to film it.
                            androidx.camera.video.FallbackStrategy.lowerQualityOrHigherThan(Quality.SD),
                        )
                    },
                )
                // The biggest size a phone offers would overrun the 50 MB a review accepts.
                .apply { if (highest) setTargetVideoEncodingBitRate(HIGHEST_BITRATE) }
                .build()
            return VideoCapture.Builder(recorder)
                .apply { if (fps != null) setTargetFrameRate(android.util.Range(fps, fps)) }
                .build()
        }

        /*
         * EVERY STREAM AT THE ONE RATE. The session has one exposure frame-rate range, and
         * the preview and analysis used to pin it to 30 whatever the recording asked for —
         * so a "60 fps" clip was filmed at 30. Now all three are pinned to the rate being
         * tried. The analyser still LOOKS at about 30 a second (see LIVE_MIN_FRAME_MS); the
         * extra frames are in the clip, where the ball is tracked again at full rate once
         * it is saved (ClipFlightAnalyzer) — the far half of the flight, where it pitches,
         * is only ever seen at the higher rate.
         */
        var analysis: ImageAnalysis? = null
        fun bindWith(fps: Int?, quality: Quality = resolution): Pair<VideoCapture<Recorder>, androidx.camera.core.Camera>? = runCatching {
            val capture = buildCapture(fps, quality)
            val rate = fps ?: TARGET_FPS
            val a = analysisAt(rate)
            provider.unbindAll()
            analysis = a
            capture to provider.bindToLifecycle(
                lifecycleOwner,
                CameraSelector.DEFAULT_BACK_CAMERA,
                previewAt(rate),
                capture,
                a,
            )
        }.getOrNull()

        fun supports(b: Pair<VideoCapture<Recorder>, androidx.camera.core.Camera>?, fps: Int) =
            b?.second?.cameraInfo?.supportedFrameRateRanges?.any { it.lower <= fps && it.upper >= fps } == true

        /*
         * 60 if the phone can, then a FIXED 30, and only then whatever the camera picks.
         *
         * The fallback used to go straight from 60 to "no preference", and a Realme RMX3933
         * (which offers 30 but not 60) then ran the whole session at a fixed 20 fps, in
         * bright sun — a third of every delivery's ball sightings gone, bigger jumps between
         * the rest, and a REC chip that said 30 regardless. A fixed 30 is asked for by name.
         */
        /*
         * "HIGHEST THIS PHONE CAN FILM" (/control). Every rate the back camera offers, the
         * fastest first - no faster than the 60 an ordinary session can run beside preview and
         * analysis - and at each, the biggest size it records, 4K down to 720p. The first pair
         * that binds AND keeps its rate wins: frame rate before pixels, as everywhere else here.
         */
        var chosenSize: Quality = resolution
        val best: Pair<Pair<VideoCapture<Recorder>, androidx.camera.core.Camera>, Int>? = if (!highest) null else run {
            val info = runCatching { CameraSelector.DEFAULT_BACK_CAMERA.filter(provider.availableCameraInfos).firstOrNull() }.getOrNull()
                ?: return@run null
            val rates = (info.supportedFrameRateRanges.map { it.upper }.filter { it in 24..HIGHEST_MAX_FPS } + 30)
                .distinct().sortedDescending()
            val offered = runCatching {
                Recorder.getVideoCapabilities(info).getSupportedQualities(androidx.camera.core.DynamicRange.SDR).toSet()
            }.getOrDefault(emptySet())
            val sizes = listOf(Quality.UHD, Quality.FHD, Quality.HD).filter { it in offered }.ifEmpty { listOf(Quality.FHD) }
            android.util.Log.i(ANALYSIS_TAG, "highest: rates $rates, sizes offered $offered")
            for (fps in rates) for (q in sizes) {
                val b = bindWith(fps, q)
                if (b != null && supports(b, fps)) {
                    chosenSize = q
                    return@run b to fps
                }
            }
            null
        }
        val high = if (best == null && wantFps > 30) bindWith(wantFps) else null
        val bound: Pair<VideoCapture<Recorder>, androidx.camera.core.Camera>?
        val gotFps: Int
        if (best != null) {
            bound = best.first
            gotFps = best.second
        } else if (high != null && supports(high, wantFps)) {
            bound = high
            gotFps = wantFps
        } else {
            val thirty = bindWith(30)
            if (thirty != null && supports(thirty, 30)) {
                bound = thirty
                gotFps = 30
            } else {
                bound = bindWith(null)
                gotFps = 30
            }
        }
        if (bound != null) {
            android.util.Log.i(
                ANALYSIS_TAG,
                "camera bound ($videoQuality): asked ${if (highest) "highest" else wantFps}, got $gotFps fps at " +
                    "${if (chosenSize == Quality.UHD) "4K" else if (chosenSize == Quality.HD) "720p" else "1080p"}, supported " +
                    bound.second.cameraInfo.supportedFrameRateRanges.joinToString(),
            )
            shutter.attach(bound.second)
            analysis?.let { onReady(bound.first, it, gotFps) }
        }
    }, ContextCompat.getMainExecutor(context))
}

private fun startClip(
    context: Context,
    capture: VideoCapture<Recorder>,
    executor: java.util.concurrent.Executor,
    targetFile: File,
    onStarted: () -> Unit = {},
    onFinished: (File, Long) -> Unit,
): Recording? {
    val options = androidx.camera.video.FileOutputOptions.Builder(targetFile).build()
    val startedAt = System.currentTimeMillis()

    return runCatching {
        capture.output
            .prepareRecording(context, options)
            .start(executor) { event ->
                if (event is VideoRecordEvent.Start) onStarted()
                if (event is VideoRecordEvent.Finalize) {
                    onFinished(targetFile, System.currentTimeMillis() - startedAt)
                }
            }
    }.getOrNull()
}

/** "6:00 PM" in the phone's own zone — the slot as the person holding the phone reads it. */
private fun clockLabel(epochMs: Long): String =
    java.time.format.DateTimeFormatter.ofPattern("h:mm a", java.util.Locale.ENGLISH)
        .format(java.time.Instant.ofEpochMilli(epochMs).atZone(java.time.ZoneId.systemDefault()))
