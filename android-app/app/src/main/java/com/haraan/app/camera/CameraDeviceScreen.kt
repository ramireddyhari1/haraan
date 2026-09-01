package com.haraan.app.camera

import android.content.Context
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
import androidx.camera.view.PreviewView
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.foundation.Image
import androidx.core.content.ContextCompat
import com.haraan.app.R
import com.haraan.app.theme.ArchivoDisplay
import com.haraan.app.data.CameraDeviceRepository
import com.haraan.app.vision.OpenCvBallTracker
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

@Composable
fun CameraDeviceScreen(
    initialCode: String?,
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onExit: () -> Unit,
) {
    val repo = remember { CameraDeviceRepository() }
    val scope = rememberCoroutineScope()

    var preview by remember { mutableStateOf<PairingPreview?>(null) }
    var session by remember { mutableStateOf<CameraSession?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    LaunchedEffect(initialCode) {
        val code = initialCode
        if (code == null) {
            error = "That link is missing its pairing code."
            return@LaunchedEffect
        }
        busy = true
        runCatching { repo.preview(code) }
            .onSuccess { preview = it }
            .onFailure { error = it.message ?: "That pairing code is not valid." }
        busy = false
    }

    Box(Modifier.fillMaxSize()) {
        CameraBackdrop()
        val joined = session
        when {
            joined != null -> CameraMode(
                session = joined,
                repo = repo,
                hasCameraPermission = hasCameraPermission,
                requestCameraPermission = requestCameraPermission,
                onDropped = {
                    session = null
                    error = "The scorer removed this camera from the match."
                },
            )

            else -> JoinPanel(
                preview = preview,
                busy = busy,
                error = error,
                onJoin = {
                    val code = initialCode ?: return@JoinPanel
                    busy = true
                    error = null
                    scope.launch {
                        runCatching { repo.claim(code, android.os.Build.MODEL ?: "Camera phone") }
                            .onSuccess { session = it }
                            .onFailure { error = it.message ?: "Couldn't join the match." }
                        busy = false
                    }
                },
                onExit = onExit,
            )
        }
    }
}

/** What you are about to join, before you join it. */
@Composable
private fun JoinPanel(
    preview: PairingPreview?,
    busy: Boolean,
    error: String?,
    onJoin: () -> Unit,
    onExit: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxSize()
            .statusBarsPadding()
            .navigationBarsPadding()
            .padding(horizontal = 26.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        // Signed. Somebody who scanned a stranger's QR code is entitled to see whose
        // software just opened on their phone, and a screen with no name on it is how
        // anonymous software looks.
        Staged(0) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Image(
                    painter = painterResource(R.drawable.haraan_logo_white),
                    contentDescription = "Haraan",
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.height(19.dp),
                )
                Spacer(Modifier.weight(1f))
                Box(
                    Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Accent.copy(alpha = 0.16f))
                        .border(1.dp, Accent.copy(alpha = 0.35f), RoundedCornerShape(999.dp))
                        .padding(horizontal = 11.dp, vertical = 5.dp),
                ) {
                    Text(
                        "MATCH CAMERA",
                        color = Accent,
                        fontSize = 9.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.1.sp,
                    )
                }
            }
        }

        Spacer(Modifier.height(34.dp))
        // The brackets close on OUR mark. That is the whole identity move on this screen:
        // the camera idea and the brand in one object, instead of a stock glowing lens.
        Staged(1) {
            Box(contentAlignment = Alignment.Center) {
                LensMark()
                Image(
                    painter = painterResource(R.drawable.ic_haraan_ribbon_mark),
                    contentDescription = null,
                    modifier = Modifier.height(40.dp),
                )
            }
        }
        Spacer(Modifier.height(28.dp))

        Staged(2) {
            Text(
                "Join as match camera",
                color = Ink,
                fontSize = 30.sp,
                // The app's display face, the same one the scorer sets a total in. A
                // headline in the system font is a headline that belongs to no product.
                fontFamily = ArchivoDisplay,
                letterSpacing = (-0.9).sp,
                textAlign = TextAlign.Center,
            )
        }

        when {
            error != null -> {
                Spacer(Modifier.height(14.dp))
                Staged(3) {
                    Text(error, color = Rec, fontSize = 15.sp, lineHeight = 21.sp, textAlign = TextAlign.Center)
                }
            }

            preview == null -> {
                Spacer(Modifier.height(16.dp))
                Staged(3) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        CircularProgressIndicator(
                            color = Accent,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(15.dp),
                        )
                        Spacer(Modifier.width(10.dp))
                        Text("Checking the code…", color = Ink.copy(alpha = 0.65f), fontSize = 14.5.sp)
                    }
                }
            }

            else -> {
                Spacer(Modifier.height(10.dp))
                Staged(3) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            preview.matchTitle,
                            color = Ink.copy(alpha = 0.92f),
                            fontSize = 16.sp,
                            fontWeight = FontWeight.SemiBold,
                            textAlign = TextAlign.Center,
                        )
                        if (preview.venue.isNotBlank()) {
                            Spacer(Modifier.height(5.dp))
                            Text(
                                preview.venue,
                                color = Ink.copy(alpha = 0.45f),
                                fontSize = 13.5.sp,
                                textAlign = TextAlign.Center,
                            )
                        }
                    }
                }

                Spacer(Modifier.height(26.dp))
                Staged(4) {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(20.dp))
                            .background(Panel.copy(alpha = 0.75f))
                            // A lit top edge and a hairline: the difference between a
                            // raised surface and a lighter rectangle.
                            .border(
                                1.dp,
                                Brush.verticalGradient(
                                    listOf(Color.White.copy(alpha = 0.12f), Color.White.copy(alpha = 0.03f)),
                                ),
                                RoundedCornerShape(20.dp),
                            )
                            .padding(20.dp),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            // The same short accent rule the profile's career cards use.
                            // Small consistencies like this are what make two screens
                            // look like one product.
                            Box(
                                Modifier
                                    .width(3.dp)
                                    .height(12.dp)
                                    .clip(RoundedCornerShape(2.dp))
                                    .background(Accent),
                            )
                            Spacer(Modifier.width(9.dp))
                            Text(
                                "THIS PHONE BECOMES",
                                color = Ink.copy(alpha = 0.42f),
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 1.1.sp,
                            )
                        }
                        Spacer(Modifier.height(9.dp))
                        Text(
                            preview.roleLabel,
                            color = Accent,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.ExtraBold,
                            letterSpacing = (-0.4).sp,
                        )
                        Spacer(Modifier.height(11.dp))
                        Text(
                            preview.role.blurb,
                            color = Ink.copy(alpha = 0.62f),
                            fontSize = 13.5.sp,
                            lineHeight = 20.sp,
                        )
                    }
                }

                Spacer(Modifier.height(16.dp))
                Staged(5) {
                    Text(
                        "Records a short clip around each delivery and sends it to the scorer. " +
                            "It does not decide anything.",
                        color = Ink.copy(alpha = 0.38f),
                        fontSize = 12.5.sp,
                        lineHeight = 18.sp,
                        textAlign = TextAlign.Center,
                    )
                }
            }
        }

        Spacer(Modifier.height(30.dp))
        if (preview != null && error == null) {
            Staged(6) {
                PrimaryButton(if (busy) "Joining…" else "Join this match", enabled = !busy, onClick = onJoin)
            }
            Spacer(Modifier.height(12.dp))
        }
        Staged(7) { GhostButton("Close", onExit) }
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
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onDropped: () -> Unit,
) {
    val ctx = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()

    var granted by remember { mutableStateOf(hasCameraPermission()) }
    var recording by remember { mutableStateOf(false) }
    var uploading by remember { mutableStateOf(false) }
    // Cleared on the next tap: a refusal is about the clip just filmed, not a mode the
    // camera is stuck in.
    var uploadError by remember { mutableStateOf<String?>(null) }
    var clipsSent by remember { mutableStateOf(0) }
    var score by remember { mutableStateOf("") }
    var overs by remember { mutableStateOf("") }
    var live by remember { mutableStateOf(true) }

    val executor = remember { Executors.newSingleThreadExecutor() }
    var videoCapture by remember { mutableStateOf<VideoCapture<Recorder>?>(null) }

    /*
     * THE VISION ENGINE.
     *
     * Its own single thread, so analysis can never sit on the recorder's. Held for the
     * lifetime of the screen and reset per delivery rather than rebuilt, because the
     * native allocation is the expensive part.
     */
    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val vision = remember { OpenCvBallTracker() }
    // Only counted while a delivery is actually being recorded — the camera runs the whole
    // time somebody is holding the phone, and tracking the warm-up is noise.
    var trackedPoints by remember { mutableStateOf(0) }
    var trackingLive by remember { mutableStateOf(false) }

    DisposableEffect(Unit) {
        onDispose {
            vision.release()
            analysisExecutor.shutdown()
        }
    }

    val analyzer = remember {
        ImageAnalysis.Analyzer { image ->
            try {
                if (trackingLive) {
                    val plane = image.planes.getOrNull(0)
                    if (plane != null) {
                        val buffer = plane.buffer
                        val bytes = ByteArray(buffer.remaining())
                        buffer.get(bytes)
                        // The camera's own monotonic clock, never the UI clock: ball
                        // motion timing has to come from when the sensor saw it.
                        val sighting = vision.onFrame(
                            luma = bytes,
                            width = image.width,
                            height = image.height,
                            rowStride = plane.rowStride,
                            timestampMs = image.imageInfo.timestamp / 1_000_000,
                        )
                        if (sighting != null) trackedPoints = vision.track().size
                    }
                }
            } catch (_: Throwable) {
                // Never let analysis break the camera.
            } finally {
                image.close()
            }
        }
    }
    var activeRecording by remember { mutableStateOf<Recording?>(null) }

    DisposableEffect(Unit) { onDispose { executor.shutdown() } }

    LaunchedEffect(Unit) { if (!granted) requestCameraPermission { granted = it } }

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
        var missed = 0
        while (true) {
            val beat = repo.heartbeat(session.sessionToken)
            if (beat == null) {
                missed++
                live = false
                if (missed >= MAX_MISSED_HEARTBEATS) {
                    onDropped()
                    return@LaunchedEffect
                }
                // Back off a little before retrying, but well inside the drop window.
                delay(5_000)
                continue
            }
            missed = 0
            live = true
            score = beat.score
            overs = beat.overs
            delay(20_000)
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        if (granted) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    PreviewView(context).also { view ->
                        bindCamera(
                            context,
                            view,
                            lifecycleOwner,
                            analysisExecutor,
                            analyzer,
                        ) { capture -> videoCapture = capture }
                    }
                },
            )
        } else {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text(
                    "Camera access is needed to film the match.",
                    color = Ink,
                    fontSize = 15.sp,
                    modifier = Modifier.padding(32.dp),
                )
            }
        }

        // Status, top: which role this phone is playing and whether the match still
        // knows about it.
        Column(
            Modifier
                .align(Alignment.TopStart)
                .statusBarsPadding()
                .padding(16.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(Color.Black.copy(alpha = 0.55f))
                .padding(horizontal = 14.dp, vertical = 11.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(8.dp).clip(CircleShape).background(if (live) Good else Rec))
                Spacer(Modifier.width(8.dp))
                Text(session.roleLabel, color = Ink, fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.height(4.dp))
            Text(
                buildString {
                    append(session.matchTitle)
                    if (score.isNotBlank()) append("  ·  $score")
                    if (overs.isNotBlank()) append(" ($overs)")
                },
                color = Ink.copy(alpha = 0.7f),
                fontSize = 12.sp,
                maxLines = 1,
            )
        }

        // The control, bottom: one target, thumb-sized, reachable without looking.
        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(bottom = 30.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                when {
                    // The point count is the only honest signal of whether vision is
                    // doing anything, and it belongs where the person filming can see it.
                    recording && trackedPoints > 0 -> "Recording · $trackedPoints ball points"
                    recording -> "Recording this delivery…"
                    uploading -> "Sending to the scorer…"
                    uploadError != null -> uploadError!!
                    // Said out loud rather than left to a small coloured dot. Somebody
                    // holding this phone at the boundary needs to know the difference
                    // between "idle" and "the scorer has stopped hearing from me".
                    !live -> "Reconnecting to the match…"
                    trackedPoints > 0 -> "$clipsSent sent · $trackedPoints ball points"
                    clipsSent > 0 -> "$clipsSent sent"
                    else -> "Tap when the bowler runs in"
                },
                color = if (uploadError != null) Rec else Ink.copy(alpha = 0.85f),
                fontSize = 13.5.sp,
            )
            Spacer(Modifier.height(14.dp))
            Box(
                Modifier
                    .size(84.dp)
                    .clip(CircleShape)
                    .background(if (recording) Rec else Color.White.copy(alpha = 0.9f))
                    .border(4.dp, Color.White.copy(alpha = 0.55f), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                if (recording || uploading) {
                    CircularProgressIndicator(
                        color = if (recording) Color.White else Accent,
                        strokeWidth = 3.dp,
                        modifier = Modifier.size(30.dp),
                    )
                } else {
                    Box(
                        Modifier
                            .size(30.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Rec)
                            .then(
                                Modifier.clickableCapture(enabled = granted && videoCapture != null) {
                                    val capture = videoCapture ?: return@clickableCapture
                                    uploadError = null
                                    // A fresh delivery: the previous track must not bleed
                                    // into this one.
                                    vision.reset()
                                    trackedPoints = 0
                                    trackingLive = true
                                    recording = true
                                    activeRecording = startClip(
                                        context = ctx,
                                        capture = capture,
                                        executor = executor,
                                        onFinished = { file, durationMs ->
                                            recording = false
                                            trackingLive = false
                                            // Checked here so a clip that cannot be
                                            // accepted never crosses ground Wi-Fi at all.
                                            if (file.length() > MAX_REVIEW_BYTES) {
                                                runCatching { file.delete() }
                                                uploadError =
                                                    "That clip is too large to send. Record a shorter delivery."
                                                return@startClip
                                            }
                                            uploading = true
                                            scope.launch {
                                                val ok = repo.uploadClip(
                                                    session.sessionToken,
                                                    file,
                                                    durationMs,
                                                    overs.takeIf { it.isNotBlank() },
                                                )
                                                if (ok) clipsSent += 1
                                                // The phone is a camera, not a library:
                                                // the clip lives on the server now.
                                                runCatching { file.delete() }
                                                uploading = false
                                            }
                                        },
                                    )
                                    // Ends itself at the review ceiling.
                                    //
                                    // Ten seconds is what the server accepts, and it buys
                                    // the run-up, the release, the bounce, the shot and
                                    // enough afterwards to see where the ball went -
                                    // which is the context a review actually needs.
                                    // Nobody at a ground is watching this screen to press
                                    // stop, so the cap has to be the recorder's own.
                                    //
                                    // Slightly under the limit on purpose: the container
                                    // rounds, and a clip that measures 10.02s server-side
                                    // would be refused after the upload had already been
                                    // paid for over ground Wi-Fi.
                                    scope.launch {
                                        delay(REVIEW_CLIP_MS)
                                        activeRecording?.stop()
                                        activeRecording = null
                                    }
                                },
                            ),
                    )
                }
            }
        }
    }
}

/** The one commitment on the screen: lit, gradient-filled, and it dips under a thumb. */
@Composable
private fun PrimaryButton(label: String, enabled: Boolean, onClick: () -> Unit) {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale = remember { Animatable(1f) }
    LaunchedEffect(pressed) {
        scale.animateTo(if (pressed) 0.97f else 1f, tween(140, easing = FastOutSlowInEasing))
    }
    Box(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { scaleX = scale.value; scaleY = scale.value }
            .clip(RoundedCornerShape(16.dp))
            .background(
                Brush.horizontalGradient(
                    if (enabled) listOf(Color(0xFF3B82F6), Color(0xFF2563EB))
                    else listOf(Color(0xFF1E293B), Color(0xFF1E293B)),
                ),
            )
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
                onClick = onClick,
            )
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

/** Taps with no ripple — this screen is mostly a viewfinder. */
@Composable
private fun Modifier.clickableCapture(enabled: Boolean, onClick: () -> Unit): Modifier {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    return this.then(
        clickable(
            interactionSource = interaction,
            indication = null,
            enabled = enabled,
            onClick = onClick,
        ),
    )
}

// THE REVIEW CONTRACT, mirrored from the server.
//
// DeliveryReview holds the authoritative numbers; these exist so the phone refuses a clip
// it already knows will be refused, before spending a ground's upload on it. If the server
// limits move, these move with them.

/** Server ceiling is 10s; stop just under it so container rounding cannot push us over. */
/** Consecutive failed heartbeats before a camera is treated as genuinely gone. */
private const val MAX_MISSED_HEARTBEATS = 3

private const val REVIEW_CLIP_MS = 9_500L

/** Matches DeliveryReview::MAX_REVIEW_BYTES. */
private const val MAX_REVIEW_BYTES = 50L * 1024 * 1024

// ─────────────────────────────────────────────────────── CameraX plumbing ─────

private fun bindCamera(
    context: Context,
    view: PreviewView,
    lifecycleOwner: androidx.lifecycle.LifecycleOwner,
    analysisExecutor: java.util.concurrent.Executor,
    onFrame: ImageAnalysis.Analyzer,
    onReady: (VideoCapture<Recorder>) -> Unit,
) {
    val future = ProcessCameraProvider.getInstance(context)
    future.addListener({
        val provider = future.get()
        val preview = Preview.Builder().build().also { it.setSurfaceProvider(view.surfaceProvider) }

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
        val analysis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
            .build()
            .also { it.setAnalyzer(analysisExecutor, onFrame) }
        // FULL HD, and the comment that used to sit here was wrong.
        //
        // It said "SD is deliberate" while the code asked for Quality.HD, which is 720p -
        // neither the SD it claimed nor the Full HD anybody assumed. A review is worth
        // having only if the ball is visible in it, and at 720p across a maidan it often
        // is not, so this now asks for what it should always have asked for.
        //
        // The fallback still steps down rather than up: a handset that cannot do 1080p
        // should film the delivery at 720p, not refuse to film it.
        val recorder = Recorder.Builder()
            .setQualitySelector(
                QualitySelector.from(
                    Quality.FHD,
                    androidx.camera.video.FallbackStrategy.lowerQualityOrHigherThan(Quality.HD),
                ),
            )
            .build()
        val videoCapture = VideoCapture.withOutput(recorder)
        runCatching {
            provider.unbindAll()
            provider.bindToLifecycle(
                lifecycleOwner,
                CameraSelector.DEFAULT_BACK_CAMERA,
                preview,
                videoCapture,
                analysis,
            )
            onReady(videoCapture)
        }
    }, ContextCompat.getMainExecutor(context))
}

private fun startClip(
    context: Context,
    capture: VideoCapture<Recorder>,
    executor: java.util.concurrent.Executor,
    onFinished: (File, Long) -> Unit,
): Recording? {
    val file = File(context.cacheDir, "clip-${System.currentTimeMillis()}.mp4")
    val options = androidx.camera.video.FileOutputOptions.Builder(file).build()
    val startedAt = System.currentTimeMillis()

    return runCatching {
        capture.output
            .prepareRecording(context, options)
            .start(executor) { event ->
                if (event is VideoRecordEvent.Finalize) {
                    onFinished(file, System.currentTimeMillis() - startedAt)
                }
            }
    }.getOrNull()
}
