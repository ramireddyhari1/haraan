package com.haraan.app.vision

import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import com.haraan.app.BuildConfig
import com.haraan.app.ui.pressable
import com.haraan.app.vision.replay.FrameLabel
import com.haraan.app.vision.replay.LabelScore
import com.haraan.app.vision.replay.LabelScoring
import com.haraan.app.vision.replay.LabelSet
import com.haraan.app.vision.replay.LabelStore
import com.haraan.app.vision.replay.LumaFrame
import com.haraan.app.vision.replay.LumaPreview
import com.haraan.app.vision.replay.PitchSource
import com.haraan.app.vision.replay.StumpSource
import com.haraan.app.vision.replay.ReplayController
import com.haraan.app.vision.replay.ReplaySpeed
import com.haraan.app.vision.replay.ReplayStatus
import com.haraan.app.vision.replay.ReplayUiState
import com.haraan.app.vision.replay.VideoReplaySource
import kotlinx.coroutines.asCoroutineDispatcher
import java.util.concurrent.Executors

/**
 * VISION REPLAY — the same detector, the same frames, as many times as you like.
 *
 * WHY. [VisionFieldTestActivity] can only answer a question by standing at a ground and
 * bowling another ball, which means every threshold change costs an afternoon and is
 * compared against a delivery that was never quite the same. This screen takes a clip and
 * runs the shipping detector over it, identically, every time — so a change to
 * [OpenCvBallTracker] can be judged by the difference it makes to a file rather than by a
 * memory of yesterday.
 *
 * WHAT IT DOES NOT DO. It does not tell you whether the detector is RIGHT. Nothing here
 * knows where the ball actually was; it shows what the tracker said and what its filters
 * threw away. Turning that into an accuracy figure needs clips somebody has labelled by
 * hand, which is the next piece of work and deliberately not this one.
 *
 * NOT CAMERAX. The live pipeline is untouched. This is a second frame source feeding the
 * same engine:
 *
 *     CameraX ImageAnalysis ─┐
 *                            ├─→ CricketVisionEngine.onFrame()
 *     MP4 → MediaCodec ──────┘
 *
 * DEBUG BUILDS ONLY, twice over: the class lives in the debug source set so it is not
 * compiled into a release at all, and it re-checks BuildConfig.DEBUG on the way in.
 *
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://vision-replay"
 *
 * or straight to a clip already pushed to the device:
 *
 *     adb push delivery.mp4 /sdcard/Download/
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://vision-replay" \
 *       --es clip "content://com.android.providers.downloads.documents/document/..."
 *
 * The simplest route on an emulator is to open the screen and pick the file: drag the clip
 * onto the emulator window to drop it in Downloads, then tap CHOOSE CLIP.
 */
class VisionReplayActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        val supplied = clipFromIntent(intent)
        setContent { VisionReplayScreen(initialUri = supplied) }
    }

    /**
     * A clip handed in by tooling rather than chosen on screen.
     *
     * Three shapes are accepted because three things send them: `--es clip <uri>` from a
     * shell, EXTRA_STREAM from a share sheet, and a plain content URI as the intent's own
     * data when something opens a video with this activity directly. The deep link itself
     * carries no clip, so a bare `haraan://vision-replay` lands on the picker.
     */
    private fun clipFromIntent(intent: Intent?): Uri? {
        if (intent == null) return null
        intent.getStringExtra(EXTRA_CLIP)?.let { raw ->
            return runCatching {
                if (raw.startsWith("/")) Uri.fromFile(java.io.File(raw)) else Uri.parse(raw)
            }.getOrNull()
        }

        val stream = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            intent.getParcelableExtra(Intent.EXTRA_STREAM, Uri::class.java)
        } else {
            @Suppress("DEPRECATION")
            intent.getParcelableExtra(Intent.EXTRA_STREAM) as? Uri
        }
        if (stream != null) return stream

        val data = intent.data ?: return null
        // The deep link's own scheme is not a clip; anything else might be.
        return if (data.scheme == "haraan") null else data
    }

    private companion object {
        const val EXTRA_CLIP = "clip"
    }
}

@Composable
private fun VisionReplayScreen(initialUri: Uri?) {
    val context = LocalContext.current

    // The shipping detector, constructed exactly as the camera screen constructs it. No
    // thresholds are overridden here and none may be: a replay tuned to look good is a
    // replay that has stopped being evidence.
    val engine = remember { OpenCvBallTracker() }

    // The shipping pitch detector, wrapped to the controller's seam. Without a quad every
    // metric in metres stays unavailable, so this is the single thing standing between a
    // picture of a delivery and a measurement of one.
    val detector = remember { OpenCvPitchDetector() }
    // The wicket detector. Its own analysis width, because three bars 0.23 m apart are a
    // couple of pixels at the resolution the other two engines are happy with.
    val stumpDetector = remember { OpenCvStumpDetector() }
    val stumpSource = remember {
        object : StumpSource {
            override fun detect(
                luma: ByteArray,
                width: Int,
                height: Int,
                rowStride: Int,
                rotationDegrees: Int,
                creases: List<com.haraan.app.vision.CreaseSegment>,
            ) = stumpDetector.detect(luma, width, height, rowStride, rotationDegrees, creases)

            override fun report() = stumpDetector.report()
            override fun release() = stumpDetector.release()
        }
    }
    val pitchSource = remember {
        object : PitchSource {
            override fun detect(
                luma: ByteArray,
                width: Int,
                height: Int,
                rowStride: Int,
                rotationDegrees: Int,
            ) = detector.detect(luma, width, height, rowStride, rotationDegrees)

            override fun report() = detector.report()
            override fun creases() = detector.creases()
            override fun release() = detector.release()
        }
    }

    // One thread owns the decoder and the detector, start to finish. A pool would let two
    // frames overlap, and a detector that differences against "the previous frame" cannot
    // survive two definitions of which frame that was.
    val decodeExecutor = remember { Executors.newSingleThreadExecutor { Thread(it, "vision-replay") } }
    val decodeDispatcher = remember { decodeExecutor.asCoroutineDispatcher() }

    var preview by remember { mutableStateOf<Bitmap?>(null) }
    val previewState = remember { PreviewBuffers() }

    val activity = context as ComponentActivity
    val controller = remember {
        ReplayController(
            engine = engine,
            scope = activity.lifecycleScope,
            decodeContext = decodeDispatcher,
            engineAvailable = { engine.available },
            pitch = pitchSource,
            stumps = stumpSource,
            previewSink = { frame -> preview = previewState.render(frame) },
        )
    }
    val state by controller.state.collectAsStateWithLifecycle()

    // LABELLING. The ground truth the detector gets judged against, gathered by stepping
    // the clip and pointing at the ball. Nothing here feeds the pipeline — it only ever
    // reads what the pipeline said and records what a person saw instead.
    var labelling by remember { mutableStateOf(false) }
    var labels by remember { mutableStateOf<LabelSet?>(null) }
    var score by remember { mutableStateOf<LabelScore?>(null) }
    var saveNote by remember { mutableStateOf<String?>(null) }

    // Any previous session's labels for this clip, so a review can be picked up rather
    // than started again.
    LaunchedEffect(state.metadata?.displayName) {
        val metadata = state.metadata ?: return@LaunchedEffect
        val existing = LabelStore.load(context.getExternalFilesDir(null)!!, metadata.displayName)
        labels = existing ?: LabelSet(
            clip = metadata.displayName,
            frameWidth = metadata.uprightWidth,
            frameHeight = metadata.uprightHeight,
            labels = emptyList(),
        )
        score = null
        saveNote = existing?.let { "loaded ${it.reviewed} reviewed frames" }
    }

    // ACTION_OPEN_DOCUMENT, so the clip arrives as a content URI with a read grant
    // attached and no storage permission is asked for. Nothing is copied anywhere: the
    // decoder reads through the resolver and the file stays exactly where the user put it.
    val picker = rememberLauncherForActivityResult(
        ActivityResultContracts.OpenDocument(),
    ) { uri ->
        if (uri != null) {
            controller.load { VideoReplaySource.open(context, uri) }
        }
    }

    LaunchedEffect(initialUri) {
        if (initialUri != null) controller.load { VideoReplaySource.open(context, initialUri) }
    }

    DisposableEffect(Unit) {
        onDispose {
            // Order matters. The controller cancels the pump, whose finally releases the
            // codec on the decode thread; shutting the executor first would leave that
            // release with nowhere to run and the decoder held until the process dies.
            controller.release()
            decodeExecutor.shutdown()
            engine.release()
            detector.release()
            stumpDetector.release()
            previewState.recycle()
        }
    }

    // IMMERSIVE. The footage IS the screen; everything else floats over it.
    //
    // The first build stacked a panel under the picture, which turned a delivery into a
    // postage stamp above a wall of numbers. The frame now gets the whole display and the
    // diagnostics are one tap away — present when wanted, never in the way of the one
    // thing this screen exists to show.
    val metrics = remember(state.trail, state.metadata, state.quad) {
        FlightMetrics.of(
            track = state.trail,
            // The sightings are in the UPRIGHT frame, so the aspect that puts x and y into
            // the same unit is the upright one, not the buffer's.
            frameAspect = state.metadata?.let {
                it.uprightWidth.toFloat() / it.uprightHeight.toFloat().coerceAtLeast(1f)
            } ?: 1f,
            quad = state.quad,
        )
    }
    var panelOpen by remember { mutableStateOf(false) }

    Box(Modifier.fillMaxSize().background(Color.Black)) {

        // The picture keeps its own aspect and centres itself; nothing is cropped. A
        // tool for judging where a detector put the ball may not hide part of the frame
        // to look tidier.
        FrameStage(
            state = state,
            preview = preview,
            modifier = Modifier.fillMaxSize(),
            labelling = labelling,
            label = labels?.at(state.frameIndex),
            onLabelBall = { x, y ->
                labels = labels?.with(
                    FrameLabel.Ball(state.frameIndex, state.timestampMs, x, y),
                )
                score = null
                saveNote = null
            },
        )

        // THE HEADLINE THREE. Everything else, including every unavailable metric and its
        // reason, is behind the list button — nothing is hidden, it is just not shouted.
        if (state.metadata != null) {
            Column(
                Modifier
                    .align(Alignment.TopStart)
                    .statusBarsPadding()
                    .padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                MetricChip("Speed", metrics.imageSpeed)
                MetricChip("Curve", metrics.curve)
                MetricChip("Bounce", metrics.bounceLength)
            }
        }

        Column(
            Modifier
                .align(Alignment.TopEnd)
                .statusBarsPadding()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            GlassIconButton(onClick = { activity.finish() }) { drawCloseGlyph(it) }
            GlassIconButton(active = panelOpen, onClick = { panelOpen = !panelOpen }) {
                drawListGlyph(it)
            }
            GlassIconButton(
                active = labelling,
                onClick = {
                    labelling = !labelling
                    // Labelling is a stepping job. Leaving it playing would have a person
                    // pointing at a frame that has already gone.
                    if (labelling) controller.pause()
                },
            ) { drawTagGlyph(it) }
            GlassIconButton(onClick = { picker.launch(arrayOf("video/*")) }) { drawClipGlyph(it) }
        }

        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .navigationBarsPadding(),
        ) {
            if (panelOpen) {
                Column(
                    Modifier
                        .fillMaxWidth()
                        .heightIn(max = 380.dp)
                        .background(Color(0xFF0A0C0F).copy(alpha = 0.93f))
                        .verticalScroll(rememberScrollState()),
                ) {
                    FlightMetricsStrip(metrics, Modifier.fillMaxWidth())
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 14.dp)
                            .height(1.dp)
                            .background(Color.White.copy(alpha = 0.07f)),
                    )
                    ReplayHud(state = state, modifier = Modifier.fillMaxWidth())
                }
            }

            if (labelling) {
                LabelBar(
                    labels = labels,
                    frameIndex = state.frameIndex,
                    score = score,
                    note = saveNote,
                    onAbsent = {
                        labels = labels?.with(FrameLabel.Absent(state.frameIndex, state.timestampMs))
                        score = null
                        saveNote = null
                    },
                    onClearFrame = {
                        labels = labels?.without(state.frameIndex)
                        score = null
                        saveNote = null
                    },
                    onSave = {
                        val set = labels
                        val dir = context.getExternalFilesDir(null)
                        if (set != null && dir != null) {
                            val saved = runCatching { LabelStore.save(dir, set) }
                            // Scored against the detections from THIS pass, which is why
                            // the log is cleared on every seek.
                            score = LabelScoring.score(set, controller.detections())
                            saveNote = saved.fold(
                                onSuccess = { "saved ${set.reviewed} frames to ${it.name}" },
                                onFailure = { "could not save: ${it.message}" },
                            )
                        }
                    },
                )
            }

            TransportBar(
                state = state,
                onPlayPause = {
                    if (state.status == ReplayStatus.PLAYING) controller.pause() else controller.play()
                },
                onStep = controller::step,
                onRestart = controller::restart,
                onSeek = controller::seekToFraction,
                onSpeed = controller::setSpeed,
            )
        }
    }
}

/**
 * One headline number, floating over the footage.
 *
 * It carries its provenance dot exactly as the full list does. A chip that showed a
 * number without saying whether it was measured, estimated or absent would be the one
 * place in this screen where a reader could be misled, and it would be the most-read
 * place on it.
 */
@Composable
private fun MetricChip(label: String, value: MetricValue) {
    val available = value !is MetricValue.Unavailable
    Column(
        Modifier
            .widthIn(min = 96.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFF0A0C0F).copy(alpha = 0.72f))
            .border(0.5.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(12.dp))
            .padding(horizontal = 10.dp, vertical = 7.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                label,
                color = Color.White.copy(alpha = if (available) 0.45f else 0.28f),
                fontSize = 10.sp,
            )
            Spacer(Modifier.width(6.dp))
            Box(Modifier.size(5.dp).clip(CircleShape).background(provenanceTint(value)))
        }
        Text(
            metricText(value),
            color = if (available) Color.White else Color.White.copy(alpha = 0.30f),
            fontSize = 15.sp,
            fontWeight = FontWeight.Medium,
            fontFamily = FontFamily.Monospace,
        )
    }
}

private fun provenanceTint(value: MetricValue) = when (value) {
    is MetricValue.Measured -> VisionPalette.GOOD
    is MetricValue.Estimated -> VisionPalette.WARN
    is MetricValue.Unavailable -> Color.White.copy(alpha = 0.22f)
}

/**
 * A round glass button with a hand-drawn glyph.
 *
 * Drawn rather than pulled from an icon set, the way the rest of this app's navigation
 * is: four shapes at one size, each two lines of Canvas, and no dependency that would
 * have to be kept in step with a design system for a debug screen.
 */
@Composable
private fun GlassIconButton(
    active: Boolean = false,
    onClick: () -> Unit,
    glyph: DrawScope.(Color) -> Unit,
) {
    val tint = if (active) VisionPalette.TRAIL else Color.White.copy(alpha = 0.85f)
    Box(
        Modifier
            .size(38.dp)
            .clip(CircleShape)
            .background(
                if (active) VisionPalette.TRAIL.copy(alpha = 0.20f)
                else Color(0xFF0A0C0F).copy(alpha = 0.66f),
            )
            .border(0.5.dp, Color.White.copy(alpha = if (active) 0.28f else 0.12f), CircleShape)
            .pressable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Canvas(Modifier.size(16.dp)) { glyph(tint) }
    }
}

private fun DrawScope.drawCloseGlyph(tint: Color) {
    val w = 1.6.dp.toPx()
    drawLine(tint, Offset(0f, 0f), Offset(size.width, size.height), w, StrokeCap.Round)
    drawLine(tint, Offset(size.width, 0f), Offset(0f, size.height), w, StrokeCap.Round)
}

private fun DrawScope.drawListGlyph(tint: Color) {
    val w = 1.6.dp.toPx()
    listOf(0.15f, 0.5f, 0.85f).forEach { y ->
        drawLine(
            tint,
            Offset(0f, size.height * y),
            Offset(size.width, size.height * y),
            w,
            StrokeCap.Round,
        )
    }
}

/** A crosshair, matching the cross a reviewer leaves on the picture. */
private fun DrawScope.drawTagGlyph(tint: Color) {
    val w = 1.6.dp.toPx()
    val c = size.width / 2f
    drawLine(tint, Offset(0f, c), Offset(size.width, c), w, StrokeCap.Round)
    drawLine(tint, Offset(c, 0f), Offset(c, size.height), w, StrokeCap.Round)
    drawCircle(tint, radius = size.width * 0.22f, style = Stroke(width = w))
}

/** A frame of film: the clip picker. */
private fun DrawScope.drawClipGlyph(tint: Color) {
    val w = 1.4.dp.toPx()
    drawRect(tint, topLeft = Offset(0f, size.height * 0.15f),
        size = androidx.compose.ui.geometry.Size(size.width, size.height * 0.7f),
        style = Stroke(width = w))
    drawLine(tint, Offset(size.width * 0.3f, size.height * 0.15f),
        Offset(size.width * 0.3f, size.height * 0.85f), w)
    drawLine(tint, Offset(size.width * 0.7f, size.height * 0.15f),
        Offset(size.width * 0.7f, size.height * 0.85f), w)
}

/**
 * Scrub bar and the three transport controls, over the footage.
 *
 * Play is the only filled control on the screen, and the only blue one. Everything else
 * is glass, so the eye goes to the picture and then to the one button that changes what
 * it is showing.
 */
@Composable
private fun TransportBar(
    state: ReplayUiState,
    onPlayPause: () -> Unit,
    onStep: () -> Unit,
    onRestart: () -> Unit,
    onSeek: (Float) -> Unit,
    onSpeed: (ReplaySpeed) -> Unit,
) {
    var realtime by remember { mutableStateOf(false) }
    val loaded = state.metadata != null

    Column(Modifier.fillMaxWidth().padding(horizontal = 18.dp, vertical = 10.dp)) {
        Box(
            Modifier
                .fillMaxWidth()
                .height(20.dp)
                .pointerInput(loaded) {
                    if (!loaded) return@pointerInput
                    detectHorizontalDragGestures { change, _ ->
                        onSeek(change.position.x / size.width.toFloat().coerceAtLeast(1f))
                    }
                },
            contentAlignment = Alignment.CenterStart,
        ) {
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(3.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(Color.White.copy(alpha = 0.18f)),
            )
            Box(
                Modifier
                    .fillMaxWidth(state.progress.coerceIn(0f, 1f))
                    .height(3.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(VisionPalette.TRAIL),
            )
        }
        Spacer(Modifier.height(10.dp))

        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            GlassIconButton(onClick = onRestart) { drawRestartGlyph(it) }
            Spacer(Modifier.width(18.dp))
            PlayButton(playing = state.status == ReplayStatus.PLAYING, onClick = onPlayPause)
            Spacer(Modifier.width(18.dp))
            GlassIconButton(onClick = onStep) { drawStepGlyph(it) }
        }
        Spacer(Modifier.height(8.dp))

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
            Text(
                if (realtime) "REAL TIME" else "FULL SPEED",
                color = Color.White.copy(alpha = 0.35f),
                fontSize = 9.sp,
                letterSpacing = 1.2.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier
                    .clip(RoundedCornerShape(10.dp))
                    .pressable(
                        onClick = {
                            realtime = !realtime
                            onSpeed(if (realtime) ReplaySpeed.REALTIME else ReplaySpeed.FULL)
                        },
                    )
                    .padding(horizontal = 10.dp, vertical = 5.dp),
            )
        }
    }
}

@Composable
private fun PlayButton(playing: Boolean, onClick: () -> Unit) {
    Box(
        Modifier
            .size(58.dp)
            .clip(CircleShape)
            .background(Color(0xFF378ADD))
            .pressable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Canvas(Modifier.size(20.dp)) {
            if (playing) {
                val bar = size.width * 0.28f
                drawRect(Color.White, Offset(size.width * 0.08f, 0f),
                    androidx.compose.ui.geometry.Size(bar, size.height))
                drawRect(Color.White, Offset(size.width * 0.64f, 0f),
                    androidx.compose.ui.geometry.Size(bar, size.height))
            } else {
                val path = Path().apply {
                    moveTo(size.width * 0.12f, 0f)
                    lineTo(size.width, size.height / 2f)
                    lineTo(size.width * 0.12f, size.height)
                    close()
                }
                drawPath(path, Color.White)
            }
        }
    }
}

private fun DrawScope.drawRestartGlyph(tint: Color) {
    val w = 1.6.dp.toPx()
    drawLine(tint, Offset(size.width * 0.12f, 0f), Offset(size.width * 0.12f, size.height), w)
    val path = Path().apply {
        moveTo(size.width, 0f)
        lineTo(size.width * 0.32f, size.height / 2f)
        lineTo(size.width, size.height)
        close()
    }
    drawPath(path, tint)
}

private fun DrawScope.drawStepGlyph(tint: Color) {
    val w = 1.6.dp.toPx()
    val path = Path().apply {
        moveTo(0f, 0f)
        lineTo(size.width * 0.68f, size.height / 2f)
        lineTo(0f, size.height)
        close()
    }
    drawPath(path, tint)
    drawLine(tint, Offset(size.width * 0.88f, 0f), Offset(size.width * 0.88f, size.height), w)
}

/**
 * The decoded frame with the tracker's marks on top.
 *
 * The picture is rotated for display by the container's rotation, which is the SAME number
 * handed to the engine — so when the engine reports a sighting in upright normalised
 * coordinates, those coordinates land on the upright picture. Getting this wrong is the
 * classic way to spend a day concluding a detector is broken when only the overlay was.
 */
@Composable
private fun FrameStage(
    state: ReplayUiState,
    preview: Bitmap?,
    modifier: Modifier = Modifier,
    labelling: Boolean = false,
    label: FrameLabel? = null,
    onLabelBall: (Float, Float) -> Unit = { _, _ -> },
) {
    val metadata = state.metadata
    // The gesture detector below is keyed on `labelling` alone, so it would otherwise
    // hold the callback from the composition where labelling was switched on — and that
    // one closes over the frame index of THAT moment. Every tap would then label the
    // frame the reviewer started at, however far they had stepped since.
    val labelHere by rememberUpdatedState(onLabelBall)
    Box(
        modifier
            .background(Color.Black)
            .heightIn(min = 200.dp),
        contentAlignment = Alignment.Center,
    ) {
        when {
            state.status == ReplayStatus.FAILED && state.error != null -> {
                ErrorPanel(title = state.error.title, detail = state.error.detail)
            }

            metadata == null -> {
                Text(
                    "Pick an MP4 to replay it through the shipping detector.",
                    color = Color.White.copy(alpha = 0.6f),
                    fontSize = 13.sp,
                    modifier = Modifier.padding(32.dp),
                )
            }

            else -> {
                val ratio = metadata.uprightWidth.toFloat() /
                    metadata.uprightHeight.toFloat().coerceAtLeast(1f)
                Box(
                    Modifier
                        .aspectRatio(ratio.coerceAtLeast(0.1f))
                        .pointerInput(labelling) {
                            if (!labelling) return@pointerInput
                            detectTapGestures { offset ->
                                // The overlay fills this box exactly, so a tap IS the
                                // normalised coordinate — no letterbox maths to get wrong.
                                labelHere(
                                    (offset.x / size.width).coerceIn(0f, 1f),
                                    (offset.y / size.height).coerceIn(0f, 1f),
                                )
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) {
                    preview?.let { bitmap ->
                        Image(
                            bitmap = bitmap.asImageBitmap(),
                            contentDescription = null,
                            contentScale = ContentScale.Fit,
                            modifier = Modifier
                                .fillMaxSize()
                                .rotate(metadata.rotationDegrees.toFloat()),
                        )
                    }
                    // Sightings are already upright and normalised, so they are drawn over
                    // the rotated picture without being rotated themselves.
                    BallTrackOverlay(
                        trail = state.trail,
                        latest = state.latest,
                        modifier = Modifier.fillMaxSize(),
                        quad = state.quad,
                        bounce = state.bounce,
                        stumps = state.stumps,
                    )
                    // The human's mark, drawn as a cross so it can never be mistaken for
                    // the detector's ring even at a glance.
                    if (labelling) {
                        Canvas(Modifier.fillMaxSize()) { drawLabelMark(label) }
                    }
                }
            }
        }
    }
}

/** Everything the engine reports, and nothing it does not. */
@Composable
private fun ReplayHud(state: ReplayUiState, modifier: Modifier = Modifier) {
    val d = state.diagnostics
    val metadata = state.metadata
    Column(
        modifier
            .padding(12.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(Color.White.copy(alpha = 0.04f))
            .border(1.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(10.dp))
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Text(
            "VISION REPLAY · ${state.status.name}",
            color = Color.White,
            fontSize = 11.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.1.sp,
        )
        metadata?.let {
            Text(
                it.displayName,
                color = Color.White.copy(alpha = 0.45f),
                fontSize = 10.sp,
                fontFamily = FontFamily.Monospace,
            )
        }
        Spacer(Modifier.height(8.dp))

        if (metadata != null) {
            VisionHudRow("Source", "${metadata.width}×${metadata.height}", Color.White, HUD_WIDTH)
            VisionHudRow("Rotation", "${metadata.rotationDegrees}°", Color.White, HUD_WIDTH)
            VisionHudRow("Codec", metadata.mimeType.removePrefix("video/"), Color.White, HUD_WIDTH)
            VisionHudRow("Duration", "${metadata.durationMs} ms", Color.White.copy(alpha = 0.7f), HUD_WIDTH)
            Divider()
        }

        VisionHudRow("Frame", "#${state.frameIndex}", Color.White, HUD_WIDTH)
        VisionHudRow("PTS", "${state.presentationTimeMs} ms", Color.White, HUD_WIDTH)
        VisionHudRow("Engine t", "${state.timestampMs} ms", Color.White, HUD_WIDTH)
        VisionHudRow("Progress", "${(state.progress * 100).toInt()}%", Color.White.copy(alpha = 0.7f), HUD_WIDTH)
        VisionHudRow("Fed", "${state.framesFed}", Color.White.copy(alpha = 0.7f), HUD_WIDTH)
        VisionHudRow("Replay FPS", "%.1f".format(state.processingFps), Color.White.copy(alpha = 0.7f), HUD_WIDTH)
        Divider()

        // Calibration first among the tracking rows, because when it is missing it is the
        // reason half the delivery panel above is showing dashes.
        VisionHudRow(
            "Pitch",
            state.quad?.source?.name?.lowercase() ?: "none",
            if (state.quad != null) VisionPalette.GOOD else Color.White.copy(alpha = 0.4f),
            HUD_WIDTH,
        )
        state.pitchReport?.lastRejection?.takeIf { state.quad == null }?.let {
            Text(
                it,
                color = Color.White.copy(alpha = 0.3f),
                fontSize = 9.sp,
                modifier = Modifier.padding(bottom = 3.dp),
            )
        }
        state.pitchReport?.let {
            VisionHudRow(
                "Pitch frames",
                "${it.agreeingFrames} agree · ${it.creaseSegments} crease",
                Color.White.copy(alpha = 0.55f),
                HUD_WIDTH,
            )
            // Which binarise the last frame took. "adaptive" means Otsu saturated and the
            // guard stepped in — a fact about what is in shot, not about the pitch.
            VisionHudRow(
                "Threshold",
                it.thresholdMode,
                if (it.thresholdMode == "adaptive") VisionPalette.WARN else Color.White.copy(alpha = 0.55f),
                HUD_WIDTH,
            )
        }
        state.stumpReport?.let {
            VisionHudRow(
                "Stumps",
                if (state.stumps != null) {
                    val near = state.stumps.creaseDistance
                        ?.let { d -> " · %.3f off".format(d) }
                        ?: " · no crease"
                    "%.0f%%%s".format(state.stumps.score * 100, near)
                } else {
                    "none"
                },
                if (state.stumps != null) VisionPalette.STUMPS else Color.White.copy(alpha = 0.4f),
                HUD_WIDTH,
            )
            VisionHudRow(
                "Stump bars",
                "${it.barsFound} bars · ${it.setsFound} sets",
                Color.White.copy(alpha = 0.55f),
                HUD_WIDTH,
            )
            // A null horizon means no ground constraint ran at all, which changes how much
            // a detection is worth. Worth a row of its own rather than a footnote.
            VisionHudRow(
                "Horizon",
                it.horizonY?.let { y -> "%.2f · %d cut".format(y, it.barsAboveHorizon) } ?: "unknown",
                if (it.horizonY != null) Color.White.copy(alpha = 0.55f) else VisionPalette.WARN,
                HUD_WIDTH,
            )
            it.lastRejection?.takeIf { _ -> state.stumps == null }?.let { why ->
                Text(
                    why,
                    color = Color.White.copy(alpha = 0.3f),
                    fontSize = 9.sp,
                    modifier = Modifier.padding(bottom = 3.dp),
                )
            }
        }
        VisionHudRow("Tracking", state.quality.name, colourFor(state.quality), HUD_WIDTH)
        VisionHudRow(
            "Ball",
            state.latest?.let { "%.3f, %.3f".format(it.x, it.y) } ?: "—",
            if (state.latest != null) VisionPalette.GOOD else Color.White.copy(alpha = 0.4f),
            HUD_WIDTH,
        )
        VisionHudRow(
            "Confidence",
            state.latest?.let { "${(it.trackingConfidence * 100).toInt()}%" } ?: "—",
            Color.White,
            HUD_WIDTH,
        )
        VisionHudRow(
            "Area",
            state.latest?.let { "${it.areaPx} px" } ?: "—",
            Color.White.copy(alpha = 0.7f),
            HUD_WIDTH,
        )
        Divider()

        VisionHudRow("Frames seen", "${d.framesSeen}", Color.White.copy(alpha = 0.75f), HUD_WIDTH)
        VisionHudRow("Accepted", "${state.accepted}", VisionPalette.GOOD, HUD_WIDTH)
        VisionHudRow("Rejected", "${state.rejected}", VisionPalette.WARN, HUD_WIDTH)
        VisionHudRow("Rej · motion", "${d.rejectedGlobalMotion}", VisionPalette.WARN, HUD_WIDTH)
        VisionHudRow("Rej · size", "${d.rejectedSize}", VisionPalette.WARN, HUD_WIDTH)
        VisionHudRow("Rej · shape", "${d.rejectedShape}", VisionPalette.WARN, HUD_WIDTH)
        VisionHudRow("Rej · path", "${d.rejectedTrajectory}", VisionPalette.WARN, HUD_WIDTH)
        VisionHudRow("Processing", "%.1f ms".format(d.averageProcessingMs), Color.White.copy(alpha = 0.75f), HUD_WIDTH)
        VisionHudRow("Max", "${d.maxProcessingMs} ms", Color.White.copy(alpha = 0.55f), HUD_WIDTH)

        if (state.malformedFrames > 0) {
            VisionHudRow("Malformed", "${state.malformedFrames}", VisionPalette.BAD, HUD_WIDTH)
        }
        if (state.lostReason != null && state.latest == null) {
            Spacer(Modifier.height(8.dp))
            Text("BALL LOST", color = VisionPalette.BAD, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold)
            Text(state.lostReason, color = VisionPalette.BAD.copy(alpha = 0.8f), fontSize = 10.sp)
        }
    }
}

@Composable
private fun LabelBar(
    labels: LabelSet?,
    frameIndex: Int,
    score: LabelScore?,
    note: String?,
    onAbsent: () -> Unit,
    onClearFrame: () -> Unit,
    onSave: () -> Unit,
) {
    val current = labels?.at(frameIndex)
    Column(
        Modifier
            .fillMaxWidth()
            .background(Color(0xFF14191F).copy(alpha = 0.95f))
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text(
                "GROUND TRUTH",
                color = Color.White.copy(alpha = 0.32f),
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.4.sp,
                modifier = Modifier.weight(1f),
            )
            Text(
                "frame $frameIndex · ${labels?.reviewed ?: 0} reviewed · ${labels?.withBall ?: 0} ball",
                color = Color.White.copy(alpha = 0.45f),
                fontSize = 10.sp,
                fontFamily = FontFamily.Monospace,
            )
        }
        Spacer(Modifier.height(8.dp))

        Text(
            when (current) {
                is FrameLabel.Ball -> "this frame: ball at %.3f, %.3f".format(current.x, current.y)
                is FrameLabel.Absent -> "this frame: no ball"
                null -> "this frame: not reviewed — tap the picture, or mark it empty"
            },
            color = if (current == null) Color.White.copy(alpha = 0.4f) else VisionPalette.WARN,
            fontSize = 11.sp,
        )
        Spacer(Modifier.height(8.dp))

        Row(
            Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            ReplayButton("NO BALL", onAbsent)
            ReplayButton("CLEAR", onClearFrame)
            ReplayButton("SAVE + SCORE", onSave)
        }

        if (score != null) {
            Spacer(Modifier.height(10.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.08f)))
            Spacer(Modifier.height(8.dp))
            // The first numbers in this package that can be WRONG rather than merely
            // unverified. A dash means the sample cannot support the ratio.
            VisionHudRow("Precision", score.precision.asPercent(), scoreColour(score.precision), HUD_WIDTH)
            VisionHudRow("Recall", score.recall.asPercent(), scoreColour(score.recall), HUD_WIDTH)
            VisionHudRow("F1", score.f1.asPercent(), scoreColour(score.f1), HUD_WIDTH)
            VisionHudRow("Reviewed", "${score.reviewedFrames}", Color.White.copy(alpha = 0.7f), HUD_WIDTH)
            VisionHudRow(
                "TP / FP / FN",
                "${score.truePositives} / ${score.falsePositives} / ${score.falseNegatives}",
                Color.White.copy(alpha = 0.7f),
                HUD_WIDTH,
            )
            VisionHudRow(
                "Median error",
                score.medianErrorFw?.let { "%.4f fw".format(it) } ?: "—",
                Color.White.copy(alpha = 0.7f),
                HUD_WIDTH,
            )
        }

        if (note != null) {
            Spacer(Modifier.height(6.dp))
            Text(note, color = Color.White.copy(alpha = 0.35f), fontSize = 10.sp)
        }
    }
}

private fun Double?.asPercent(): String = this?.let { "%.0f%%".format(it * 100) } ?: "—"

private fun scoreColour(value: Double?) = when {
    value == null -> Color.White.copy(alpha = 0.3f)
    value >= 0.8 -> VisionPalette.GOOD
    value >= 0.5 -> VisionPalette.WARN
    else -> VisionPalette.BAD
}

/**
 * The human's mark: an amber cross, never a ring.
 *
 * Deliberately a different SHAPE from the detector's marker rather than a different
 * colour. The entire point of putting both on screen is telling them apart, and at arm's
 * length on a bright day two coloured circles are one coloured circle.
 */
private fun DrawScope.drawLabelMark(label: FrameLabel?) {
    if (label !is FrameLabel.Ball) return
    val centre = Offset(label.x * size.width, label.y * size.height)
    val arm = 9.dp.toPx()
    val stroke = 1.6.dp.toPx()
    drawLine(VisionPalette.WARN, Offset(centre.x - arm, centre.y), Offset(centre.x + arm, centre.y), stroke)
    drawLine(VisionPalette.WARN, Offset(centre.x, centre.y - arm), Offset(centre.x, centre.y + arm), stroke)
}

@Composable
private fun ReplayButton(label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White.copy(alpha = 0.12f))
            .pressable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Text(label, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun ErrorPanel(title: String, detail: String) {
    Column(
        Modifier
            .padding(28.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFF2A1416))
            .border(1.dp, VisionPalette.BAD.copy(alpha = 0.4f), RoundedCornerShape(12.dp))
            .padding(18.dp),
    ) {
        Text(title, color = VisionPalette.BAD, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
        Spacer(Modifier.height(8.dp))
        Text(detail, color = Color.White.copy(alpha = 0.75f), fontSize = 12.sp)
    }
}

@Composable
private fun Divider() {
    Spacer(Modifier.height(6.dp))
    Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.1f)))
    Spacer(Modifier.height(6.dp))
}

/**
 * The bitmap and pixel buffer the preview reuses between frames.
 *
 * Held outside composition because it is written from the decode thread and Compose's
 * remembered state is not the right home for something that changes thirty times a second
 * on a background thread.
 */
private class PreviewBuffers {
    private var bitmap: Bitmap? = null
    private var pixels: IntArray? = null

    /**
     * A fresh bitmap each frame, drawn from a reused pixel buffer.
     *
     * Compose compares the bitmap by identity to decide whether to redraw, so handing back
     * the same mutated instance would show the first frame forever. The pixel array — the
     * part that is actually expensive — is still reused.
     *
     * The previous bitmap is dropped rather than recycled. Recycling it would be tidier
     * and would occasionally crash: the frame before this one may still be inside a draw
     * pass on the main thread, and a recycled bitmap under a live Canvas throws.
     */
    fun render(frame: LumaFrame): Bitmap {
        val (produced, buffer) = LumaPreview.toBitmap(frame, reuse = null, scratch = pixels)
        pixels = buffer
        bitmap = produced
        return produced
    }

    fun recycle() {
        bitmap = null
        pixels = null
    }
}

private const val HUD_WIDTH = 190
