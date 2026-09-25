package com.haraan.app.vision

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.haraan.app.BuildConfig
import java.util.concurrent.Executors

/**
 * PITCH CHECK — does the detector find the creases and the wicket, and if not, where does
 * it stop?
 *
 * The detectors only run inside the paired-camera flow, which needs a match, a second phone
 * and a pairing code before a single frame reaches them. That is a long walk to answer a
 * question about four lines, and it makes the most likely failure — a detector quietly
 * finding nothing — indistinguishable from a pairing problem.
 *
 * So it gets its own door, the same way OpenCV loading did:
 *
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://pitch-check"
 *
 * WHAT THIS PROVES AND WHAT IT DOES NOT. Pointed at anything, it proves the detectors run
 * at a usable frame rate, do not crash, and refuse scenes that are not pitches. Pointed at
 * a real pitch it shows whether the creases and the wicket are actually found — but a wall,
 * a screen, or a taped-out rectangle is not a pitch, and a quad locked onto one says
 * nothing about grass, worn paint or afternoon shadow. The readout is instrumentation, not
 * a score.
 *
 * THREE STAGES RUN, IN THIS ORDER, ON EVERY FRAME, and the order is load-bearing:
 *
 *   1. [OpenCvCameraMotion], because everything after it needs to know how much of the
 *      frame-to-frame change was the phone rather than the scene.
 *   2. [OpenCvPitchDetector], for the crease-angled segments.
 *   3. [OpenCvStumpDetector], given THIS frame's segments — the wicket ranking uses them,
 *      and stale ones would be worse than an empty list — and then [WicketTracker], which
 *      is the only thing on this screen that ever says the word CONFIRMED.
 *
 * The readouts are kept apart because the stages fail for unrelated reasons, and a blended
 * panel would let a reader carry one stage's bad frame over to another.
 *
 * DEBUG BUILDS ONLY, registered in the debug manifest and re-checked at runtime.
 */
class PitchCheckActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        setContent { PitchCheckScreen() }
    }
}

/** What the next tap on the preview means. */
private enum class TapMode {
    NONE,

    /** Four corners of the calibration rectangle, for a [PitchQuad]. */
    QUAD,

    /**
     * The wicket itself: the outside of one stump, the outside of the other, and
     * optionally the top of one.
     *
     * TWO TAPS, NOT ONE, and that is the difference between a marker and a calibration.
     * One tap fixes a place and hands back no distance. Two fix a line of known length —
     * 0.2286 m by the Laws — and that single number is why a speed can ever be in km/h.
     */
    WICKET,
}

/**
 * How often the text readouts are refreshed, in milliseconds.
 *
 * Four times a second. Fast enough that a counter ticking up looks live, slow enough that
 * the main thread is free to notice a finger. See the note on lastReadoutMs.
 */
private const val READOUT_INTERVAL_MS = 250L

@Composable
private fun PitchCheckScreen() {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val density = LocalDensity.current

    var granted by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) ==
                PackageManager.PERMISSION_GRANTED,
        )
    }
    val ask = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
        granted = it
    }
    LaunchedEffect(Unit) { if (!granted) ask.launch(Manifest.permission.CAMERA) }

    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val detector = remember { OpenCvPitchDetector() }
    val stumpDetector = remember { OpenCvStumpDetector() }
    val cameraMotion = remember { OpenCvCameraMotion() }
    val wicketTracker = remember { WicketTracker() }

    /*
     * TWO COPIES OF THE SAME TRUTH, AT TWO DIFFERENT RATES, AND THAT IS THE POINT.
     *
     * The live pair feed the OVERLAY, which is one Canvas and must keep up with the
     * footage, because it is the thing the eye tracks. The panel pair feed the TEXT, which
     * is forty-odd nodes of formatted strings and has no business being rebuilt twelve
     * times a second for numbers nobody can read at that rate.
     *
     * Throttling only the counters was not enough and the measurement said so: the panel
     * takes the LOCK as well, so a new WicketLock instance every frame recomposed the whole
     * column anyway and the main thread stayed pinned at 350 ms a frame.
     */
    var quad by remember { mutableStateOf<PitchQuad?>(null) }
    var wicketLock by remember { mutableStateOf<WicketLock?>(null) }

    var panelQuad by remember { mutableStateOf<PitchQuad?>(null) }
    var panelLock by remember { mutableStateOf<WicketLock?>(null) }
    var wicketDiagnostics by remember { mutableStateOf(wicketTracker.diagnostics()) }

    /*
     * The shape of the frame the detectors actually looked at, once turned upright.
     *
     * The overlay cannot be drawn against the whole view. The preview letterboxes the
     * camera's aspect ratio inside whatever shape the screen is, so a point at 0.5 of the
     * IMAGE is not at 0.5 of the VIEW unless the two happen to match. Held in portrait
     * they never do.
     */
    var frameAspect by remember { mutableStateOf(0f) }
    var report by remember { mutableStateOf(detector.report()) }
    var stumpReport by remember { mutableStateOf(stumpDetector.report()) }

    var tapMode by remember { mutableStateOf(TapMode.NONE) }
    var tappedCorners by remember { mutableStateOf<List<Point2>>(emptyList()) }
    var wicketTaps by remember { mutableStateOf<List<Point2>>(emptyList()) }

    DisposableEffect(Unit) {
        onDispose {
            detector.release()
            stumpDetector.release()
            cameraMotion.release()
            analysisExecutor.shutdown()
        }
    }

    /*
     * Unlike the camera screen, this one keeps looking after it succeeds.
     *
     * There the answer is wanted once and then held steady; here the whole point is to
     * watch it re-decide as the phone moves, because a detector that locks onto a doorframe
     * and never lets go is exactly the failure worth catching. The tracker's own counters —
     * re-acquires, drops, off-lock rejections — are the record of it doing so.
     */
    val analyzer = remember {
        /** The turn the previous frame arrived at, for spotting a rotation. */
        var lastTurn: Int? = null

        /**
         * When the text readouts were last published to Compose.
         *
         * WHY THIS COUNTER EXISTS, FOUND ON A PHONE. Every one of these panels is a column
         * of twenty-odd Text nodes, and they were being handed new values from the analyser
         * thread on EVERY analysed frame. Compose duly recomposed the lot a dozen times a
         * second, on top of three OpenCV stages already costing eighty milliseconds
         * between them, and the main thread never caught up: MiuiPerfServiceClient logged
         * "Slow handle animation" at four seconds a go.
         *
         * The symptom was not a slow screen. It was that TAPS STOPPED WORKING —
         * intermittently, because detectTapGestures needs the down and the up inside a
         * timeout measured on the starved thread. Placing a wicket by hand worked once and
         * then silently did nothing, with no error anywhere.
         *
         * Nobody can read a number that changes twelve times a second anyway, so the fix
         * costs nothing that was ever worth having. The OVERLAY still updates every frame —
         * it is one Canvas and it is the thing the eye actually tracks.
         */
        var lastReadoutMs = 0L

        ImageAnalysis.Analyzer { image ->
            try {
                val plane = image.planes.getOrNull(0)
                if (plane != null) {
                    val buffer = plane.buffer
                    buffer.rewind()
                    val bytes = ByteArray(buffer.remaining())
                    buffer.get(bytes)
                    val rotation = image.imageInfo.rotationDegrees
                    val turn = ((rotation % 360) + 360) % 360

                    // A quarter turn swaps the frame's width and height.
                    val aspect = if (turn % 180 == 0) {
                        image.width.toFloat() / image.height
                    } else {
                        image.height.toFloat() / image.width
                    }
                    frameAspect = aspect

                    /*
                     * THE ROTATION IS ANNOUNCED BEFORE ANY OF THIS FRAME IS LOOKED AT.
                     *
                     * A lock placed by hand must survive the phone being turned — that is
                     * most of what makes hand-placing worth doing at all. The tracker maps
                     * the anchor through the turn exactly and drops to REACQUIRE; the
                     * motion estimator, which would happily fit the whole rotating scene as
                     * a confident nonsense translation, restarts from this frame instead.
                     */
                    val previousTurn = lastTurn
                    if (previousTurn != null && previousTurn != turn) {
                        wicketTracker.onRotation((turn - previousTurn) / 90, aspect)
                    }
                    lastTurn = turn

                    val motion = cameraMotion.onFrame(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = rotation,
                    )

                    quad = detector.detect(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = rotation,
                    )

                    // AFTER the pitch detector, never before, and with the creases from
                    // THIS frame: the wicket ranking uses them, and stale ones would be
                    // worse than an empty list.
                    val sighting = stumpDetector.detectWicket(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = rotation,
                        creases = detector.creases(),
                    )

                    /*
                     * The camera's own monotonic clock, never the UI's.
                     *
                     * The tracker times its coasting limit and measures its frame rate from
                     * these. Handing it wall-clock milliseconds would make both wrong by
                     * however far the two clocks have drifted, and the frame rate would
                     * read as whatever the UI thread happened to be doing.
                     */
                    wicketLock = wicketTracker.onFrame(
                        sighting = sighting,
                        motion = motion,
                        frameAspect = aspect,
                        timestampMs = image.imageInfo.timestamp / 1_000_000,
                    )

                    // The numbers, at a rate a person can actually read.
                    val now = android.os.SystemClock.uptimeMillis()
                    if (now - lastReadoutMs >= READOUT_INTERVAL_MS) {
                        lastReadoutMs = now
                        wicketDiagnostics = wicketTracker.diagnostics()
                        panelLock = wicketLock
                        panelQuad = quad
                        report = detector.report()
                        stumpReport = stumpDetector.report()
                    }
                }
            } catch (_: Throwable) {
                // Never let analysis break the camera.
            } finally {
                image.close()
            }
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        if (granted) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    val view = PreviewView(ctx).apply {
                        scaleType = PreviewView.ScaleType.FIT_CENTER
                    }
                    val future = ProcessCameraProvider.getInstance(ctx)
                    future.addListener({
                        val provider = future.get()
                        val preview = Preview.Builder().build()
                            .also { it.setSurfaceProvider(view.surfaceProvider) }
                        val analysis = ImageAnalysis.Builder()
                            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                            .build()
                            .also { it.setAnalyzer(analysisExecutor, analyzer) }
                        try {
                            provider.unbindAll()
                            provider.bindToLifecycle(
                                lifecycleOwner,
                                CameraSelector.DEFAULT_BACK_CAMERA,
                                preview,
                                analysis,
                            )
                        } catch (_: Throwable) {
                            // Reported on screen by the frame counter staying at zero.
                        }
                    }, ContextCompat.getMainExecutor(ctx))
                    view
                },
            )
        }

        val tappedQuad = remember(tappedCorners) {
            if (tappedCorners.size < 4) {
                null
            } else {
                PitchQuad(tappedCorners, QuadSource.TAPPED, 1f).takeIf { it.isPlausible() }
            }
        }
        val shown = tappedQuad ?: quad

        Canvas(Modifier.fillMaxSize()) {
            val box = FrameBox.letterbox(size.width, size.height, frameAspect)
            if (box.width <= 0f) return@Canvas

            fun quadPath(points: List<Offset>) = Path().apply {
                moveTo(points[0].x, points[0].y)
                points.drop(1).forEach { lineTo(it.x, it.y) }
                close()
            }

            shown?.let { pitch ->
                val colour = if (pitch.source == QuadSource.TAPPED) Color(0xFFFACC15) else VisionPalette.GOOD
                drawPath(
                    quadPath(pitch.corners.map { box.toView(it) }),
                    colour,
                    style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round),
                )

                // The corridor, in metres through the homography — the same drawing the
                // camera screen does, and the thing that shows a bad calibration.
                pitch.toImage()?.let { toImage ->
                    val half = PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * 0.35
                    val near = -PitchGeometry.POPPING_CREASE_AHEAD_M
                    val far = PitchGeometry.CALIBRATION_LENGTH_M + near
                    val band = listOf(
                        Point2(-half, near),
                        Point2(half, near),
                        Point2(half, far),
                        Point2(-half, far),
                    ).map { box.toView(toImage.map(it)) }
                    if (band.none { it.x.isNaN() || it.y.isNaN() }) {
                        drawPath(quadPath(band), Color(0xFF6E9BF5).copy(alpha = 0.25f))
                    }
                }
            }

            /*
             * The wicket, drawn from the TRACKER's lock rather than from the newest
             * sighting.
             *
             * That swap is the whole point of this pass. A sighting is one frame's opinion
             * and was previously painted the instant it arrived, which meant a coincidental
             * alignment of a bat, a pad and a boot got the same three confident bars as a
             * real wicket seen for a minute. A lock has been seen repeatedly, in the same
             * place, with the phone's own movement subtracted — and when it has not, it is
             * drawn dashed and fading, which is a picture a reader cannot mistake for a
             * detection.
             */
            wicketLock?.let { drawWicketLock(it, box, density) }

            // Taps in progress, on top of everything, so the operator can see what they
            // have put down before it becomes a lock.
            tappedCorners.forEach {
                drawCircle(Color(0xFFFACC15), radius = 7.dp.toPx(), center = box.toView(it))
            }
            wicketTaps.forEachIndexed { index, point ->
                val at = box.toView(point)
                drawCircle(VisionPalette.TRAIL_CORE, radius = 7.dp.toPx(), center = at)
                if (index == 1 && wicketTaps.size >= 2) {
                    drawLine(
                        VisionPalette.TRAIL_CORE,
                        box.toView(wicketTaps[0]),
                        at,
                        2.dp.toPx(),
                        StrokeCap.Round,
                    )
                }
            }
        }

        if (tapMode != TapMode.NONE && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(tapMode) {
                        detectTapGestures { offset ->
                            val box = FrameBox.letterbox(
                                size.width.toFloat(),
                                size.height.toFloat(),
                                frameAspect,
                            )
                            // A tap in the letterbox bars is not a tap on the pitch. Taking
                            // it would put a corner or a stump outside the analysed frame
                            // entirely, where no detector can ever agree with it.
                            if (!box.contains(offset)) return@detectTapGestures
                            val point = box.toFrame(offset)

                            when (tapMode) {
                                TapMode.QUAD -> {
                                    val next = tappedCorners + point
                                    tappedCorners = next
                                    if (next.size >= 4) tapMode = TapMode.NONE
                                }

                                TapMode.WICKET -> {
                                    val next = wicketTaps + point
                                    wicketTaps = next
                                    if (next.size >= 2) {
                                        wicketTracker.lockManually(
                                            baseLeft = next[0],
                                            baseRight = next[1],
                                            // The third tap is optional and buys the
                                            // vertical scale. Two collinear base points
                                            // cannot give it at any price.
                                            top = next.getOrNull(2),
                                            kind = WicketKind.STUMPS,
                                            frameAspect = frameAspect,
                                        )
                                        wicketLock = wicketTracker.lock()
                                        wicketDiagnostics = wicketTracker.diagnostics()
                                    }
                                    if (next.size >= 3) tapMode = TapMode.NONE
                                }

                                TapMode.NONE -> Unit
                            }
                        }
                    },
            )
        }

        /*
         * THE READOUTS GO AWAY WHILE SOMETHING IS BEING PLACED BY HAND.
         *
         * Found on a phone, pointed at three stumps, on the first run of the tap flow: the
         * panel is scrollable, a scrollable consumes every touch inside it, and the wicket
         * was sitting directly behind it. Every tap aimed at a stump scrolled the
         * diagnostics instead. Nothing on screen said so — the taps simply did nothing.
         *
         * Hiding them is the right fix rather than re-ordering the layers, because while a
         * person is pointing at a wicket the whole picture IS the control, and a panel that
         * merely sat UNDER the tap layer would still be covering the thing they are trying
         * to aim at. The prompt and the Cancel button below stay put.
         */
        if (tapMode == TapMode.NONE) {
        Column(
            Modifier
                .align(Alignment.TopStart)
                .statusBarsPadding()
                .padding(14.dp)
                .widthIn(max = 260.dp)
                .verticalScroll(rememberScrollState()),
        ) {
            Column(
                Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color.Black.copy(alpha = 0.62f))
                    .padding(horizontal = 13.dp, vertical = 11.dp),
            ) {
                // The THROTTLED quad, not the live one: this is text, and a text node
                // rebuilt on every analysed frame is what pinned the main thread.
                val panelShown = tappedQuad ?: panelQuad
                Text(
                    when {
                        tapMode == TapMode.QUAD -> "TAPPING · ${tappedCorners.size}/4"
                        tappedQuad != null -> "QUAD SET BY HAND"
                        panelQuad != null -> "PITCH FOUND"
                        else -> "SEARCHING"
                    },
                    color = if (panelShown != null) VisionPalette.GOOD else VisionPalette.BAD,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                )
                Spacer(Modifier.height(7.dp))

                ReadoutRow("frames", "${report.framesSeen}")
                ReadoutRow("ms/frame", "%.1f".format(report.averageProcessingMs))
                ReadoutRow("straight edges", "${report.houghSegments}")
                ReadoutRow("crease-angle", "${report.creaseSegments}")
                ReadoutRow("down-pitch", "${report.railSegments}")
                ReadoutRow("agreeing", "${report.agreeingFrames}/5")

                report.lastRejection?.let {
                    Spacer(Modifier.height(7.dp))
                    Text(
                        it,
                        color = Color.White.copy(alpha = 0.72f),
                        fontSize = 11.sp,
                        fontFamily = FontFamily.Monospace,
                    )
                }

                panelShown?.let {
                    Spacer(Modifier.height(7.dp))
                    Text(
                        "source ${it.source} · confidence %.2f".format(it.confidence),
                        color = Color.White.copy(alpha = 0.72f),
                        fontSize = 11.sp,
                        fontFamily = FontFamily.Monospace,
                    )
                }
            }

            Spacer(Modifier.height(10.dp))

            /*
             * THE TRACKER'S PANEL, AND THEN THE DETECTOR'S, IN THAT ORDER.
             *
             * The tracker is the answer — it is what decides whether there is a wicket — and
             * the detector's counts below it are the evidence it worked from. Printed the
             * other way round the eye lands on "478 lumps" first and reads the state as a
             * footnote to it, which is backwards: the lump count is only interesting when
             * the state is wrong.
             */
            WicketDiagnosticsPanel(
                diagnostics = wicketDiagnostics,
                lock = panelLock,
                detectorAvailable = stumpDetector.available,
            )

            Spacer(Modifier.height(10.dp))

            Column(
                Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color.Black.copy(alpha = 0.62f))
                    .padding(horizontal = 13.dp, vertical = 11.dp),
            ) {
                Text(
                    "WICKET DETECTOR",
                    color = Color.White.copy(alpha = 0.32f),
                    fontSize = 9.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.4.sp,
                )
                Spacer(Modifier.height(6.dp))
                ReadoutRow("frames", "${stumpReport.framesSeen}")
                ReadoutRow("ms/frame", "%.1f".format(stumpReport.averageProcessingMs))
                ReadoutRow("bars", "${stumpReport.barsFound}")
                ReadoutRow("sets", "${stumpReport.setsFound}")
                ReadoutRow("lumps", "${stumpReport.stonesPlausible} of ${stumpReport.stonesFound}")
                ReadoutRow(
                    "stones",
                    "${stumpReport.stoneMarksFound}" + if (stumpReport.stoneWasDark) " (dark)" else "",
                )
                // Null horizon means NO ground constraint was applied, which changes how
                // much a detection is worth — so it says so rather than printing a dash.
                ReadoutRow("horizon", stumpReport.horizonY?.let { "%.2f".format(it) } ?: "none")
                ReadoutRow("above horizon", "${stumpReport.barsAboveHorizon}")

                stumpReport.lastRejection?.let {
                    Spacer(Modifier.height(7.dp))
                    Text(
                        it,
                        color = Color.White.copy(alpha = 0.72f),
                        fontSize = 10.5.sp,
                        lineHeight = 13.sp,
                        fontFamily = FontFamily.Monospace,
                    )
                }
            }

            Spacer(Modifier.height(80.dp))
        }
        }

        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            if (tapMode == TapMode.WICKET) {
                Text(
                    when (wicketTaps.size) {
                        0 -> "Tap the outside of one stump at the GROUND"
                        1 -> "Now the outside of the far stump, at the ground"
                        else -> "Optional: tap the TOP of a stump for a height scale"
                    },
                    color = Color.White,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(9.dp))
                        .background(Color.Black.copy(alpha = 0.7f))
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                )
                Spacer(Modifier.height(10.dp))
            }

            Row(horizontalArrangement = Arrangement.Center) {
                TestButton(if (tapMode == TapMode.QUAD) "Cancel" else "Tap 4 corners") {
                    if (tapMode == TapMode.QUAD) {
                        tapMode = TapMode.NONE
                        tappedCorners = emptyList()
                    } else {
                        tappedCorners = emptyList()
                        tapMode = TapMode.QUAD
                    }
                }
                Spacer(Modifier.width(8.dp))
                TestButton(
                    when {
                        tapMode == TapMode.WICKET -> "Cancel"
                        wicketDiagnostics.lockSource == WicketLockSource.MANUAL -> "Unlock wicket"
                        else -> "Lock wicket"
                    },
                ) {
                    when {
                        tapMode == TapMode.WICKET -> {
                            tapMode = TapMode.NONE
                            wicketTaps = emptyList()
                            wicketTracker.clearManualLock()
                            wicketLock = wicketTracker.lock()
                            wicketDiagnostics = wicketTracker.diagnostics()
                        }

                        wicketDiagnostics.lockSource == WicketLockSource.MANUAL -> {
                            wicketTaps = emptyList()
                            wicketTracker.clearManualLock()
                            wicketLock = wicketTracker.lock()
                            wicketDiagnostics = wicketTracker.diagnostics()
                        }

                        else -> {
                            wicketTaps = emptyList()
                            tapMode = TapMode.WICKET
                        }
                    }
                }
                Spacer(Modifier.width(8.dp))
                TestButton("Reset") {
                    tapMode = TapMode.NONE
                    tappedCorners = emptyList()
                    wicketTaps = emptyList()
                    detector.reset()
                    cameraMotion.reset()
                    wicketTracker.reset()
                    quad = null
                    wicketLock = null
                    wicketDiagnostics = wicketTracker.diagnostics()
                }
            }
        }
    }
}

@Composable
private fun ReadoutRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(
            label,
            color = Color.White.copy(alpha = 0.55f),
            fontSize = 11.sp,
            fontFamily = FontFamily.Monospace,
        )
        Spacer(Modifier.width(10.dp))
        Text(
            value,
            color = Color.White,
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            fontFamily = FontFamily.Monospace,
        )
    }
}

@Composable
private fun TestButton(label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(11.dp))
            .background(Color.White.copy(alpha = 0.16f))
            .pointerInput(label) { detectTapGestures { onClick() } }
            .padding(horizontal = 14.dp, vertical = 11.dp),
    ) {
        Text(label, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
    }
}
