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
import androidx.camera.video.Quality
import androidx.camera.video.QualitySelector
import androidx.camera.video.Recorder
import androidx.camera.video.Recording
import androidx.camera.video.VideoCapture
import androidx.camera.video.VideoRecordEvent
import androidx.camera.view.PreviewView
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.haraan.app.BuildConfig
import com.haraan.app.ui.pressable
import java.io.File
import java.util.concurrent.Executors

/**
 * VISION FIELD TEST — the tool for finding out whether any of this works.
 *
 * Everything before this phase could be verified at a desk. Whether the detector can find
 * a cricket ball cannot: it needs a real ball, real light, a real background and a real
 * camera position, and the only way to know is to stand at a ground and watch.
 *
 * So this screen exists to make that afternoon productive. It films deliveries at full
 * resolution exactly as the paired camera does, runs the same detector over the same
 * frames, and draws what the detector actually saw on top of the preview — so a false
 * positive on a fielder's shirt is visible the moment it happens rather than three weeks
 * later in a spreadsheet.
 *
 * DEBUG BUILDS ONLY, registered in the debug manifest and re-checked at runtime.
 *
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://vision-test"
 *
 * WHAT IT WILL NOT DO. It never reports an accuracy figure. It can count how many
 * deliveries produced a track; it cannot know whether the thing tracked was the ball.
 * That answer comes from a person watching the exported footage, which is why the export
 * ships an annotations file with nulls in it rather than anything pre-filled.
 */
class VisionFieldTestActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        setContent { FieldTestScreen() }
    }
}

@Composable
private fun FieldTestScreen() {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

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
    val recorderExecutor = remember { Executors.newSingleThreadExecutor() }
    val tracker = remember { OpenCvBallTracker() }
    val session = remember { FieldTestSession(System.currentTimeMillis(), TestConditions.blank()) }

    var videoCapture by remember { mutableStateOf<VideoCapture<Recorder>?>(null) }
    var activeRecording by remember { mutableStateOf<Recording?>(null) }
    var recording by remember { mutableStateOf(false) }

    var deliveryNumber by remember { mutableStateOf(1) }
    var recordedCount by remember { mutableStateOf(0) }
    // The live view of the track. Kept short: drawing hundreds of points turns the trail
    // into a smear that hides exactly the jitter we are here to look for.
    var trail by remember { mutableStateOf<List<BallSighting>>(emptyList()) }
    var latest by remember { mutableStateOf<BallSighting?>(null) }
    var lostReason by remember { mutableStateOf<String?>(null) }
    var diagnostics by remember { mutableStateOf(tracker.diagnostics()) }
    var analysisFps by remember { mutableStateOf(0.0) }
    var recordingStartedAt by remember { mutableStateOf(0L) }

    DisposableEffect(Unit) {
        onDispose {
            tracker.release()
            analysisExecutor.shutdown()
            recorderExecutor.shutdown()
        }
    }

    // Analysis FPS measured from frames actually delivered, not from the camera's
    // advertised rate: under KEEP_ONLY_LATEST the analyser sees fewer frames than the
    // recorder, and the gap between those two numbers is the whole point of measuring it.
    var framesThisSecond by remember { mutableStateOf(0) }
    var windowStartedAt by remember { mutableStateOf(System.currentTimeMillis()) }

    val analyzer = remember {
        ImageAnalysis.Analyzer { image ->
            try {
                if (recording) {
                    val plane = image.planes.getOrNull(0)
                    if (plane != null) {
                        val buffer = plane.buffer
                        val bytes = ByteArray(buffer.remaining())
                        buffer.get(bytes)
                        val sighting = tracker.onFrame(
                            luma = bytes,
                            width = image.width,
                            height = image.height,
                            rowStride = plane.rowStride,
                            timestampMs = image.imageInfo.timestamp / 1_000_000,
                        )
                        framesThisSecond++
                        val now = System.currentTimeMillis()
                        if (now - windowStartedAt >= 1000) {
                            analysisFps = framesThisSecond * 1000.0 / (now - windowStartedAt)
                            framesThisSecond = 0
                            windowStartedAt = now
                        }
                        if (sighting != null) {
                            latest = sighting
                            lostReason = null
                            trail = tracker.track().takeLast(TRAIL_LENGTH)
                        } else if (latest != null) {
                            // Named from the counter that moved, so a tester can tell a
                            // ball hidden behind the batter from one the size filter ate.
                            lostReason = dominantRejection(tracker.diagnostics(), diagnostics)
                            latest = null
                        }
                        diagnostics = tracker.diagnostics()
                    }
                }
            } catch (_: Throwable) {
                // Vision must never take the camera down.
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
                    PreviewView(ctx).also { view ->
                        val future = ProcessCameraProvider.getInstance(ctx)
                        future.addListener({
                            val provider = future.get()
                            val preview = Preview.Builder().build()
                                .also { it.setSurfaceProvider(view.surfaceProvider) }
                            // The same Full HD the paired camera records. Testing the
                            // detector against a different resolution would measure a
                            // pipeline that never ships.
                            val recorder = Recorder.Builder()
                                .setQualitySelector(QualitySelector.from(Quality.FHD))
                                .build()
                            val capture = VideoCapture.withOutput(recorder)
                            val analysis = ImageAnalysis.Builder()
                                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                                .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
                                .build()
                                .also { it.setAnalyzer(analysisExecutor, analyzer) }
                            runCatching {
                                provider.unbindAll()
                                provider.bindToLifecycle(
                                    lifecycleOwner,
                                    CameraSelector.DEFAULT_BACK_CAMERA,
                                    preview,
                                    capture,
                                    analysis,
                                )
                                videoCapture = capture
                            }
                        }, ContextCompat.getMainExecutor(ctx))
                    }
                },
            )

            // THE OVERLAY. Observed points only — nothing here is interpolated or
            // projected, so a gap in the trail is a real gap in the evidence.
            Canvas(Modifier.fillMaxSize()) {
                if (trail.size >= 2) {
                    val path = Path()
                    trail.forEachIndexed { i, p ->
                        val o = Offset(p.x * size.width, p.y * size.height)
                        if (i == 0) path.moveTo(o.x, o.y) else path.lineTo(o.x, o.y)
                    }
                    drawPath(
                        path,
                        Color(0xFF6E9BF5).copy(alpha = 0.75f),
                        style = Stroke(width = 2.dp.toPx(), cap = StrokeCap.Round),
                    )
                }
                trail.forEach { p ->
                    drawCircle(
                        Color(0xFF6E9BF5),
                        radius = 3.dp.toPx(),
                        center = Offset(p.x * size.width, p.y * size.height),
                    )
                }
                latest?.let { p ->
                    val o = Offset(p.x * size.width, p.y * size.height)
                    drawCircle(Color(0xFF4ADE80), radius = 9.dp.toPx(), center = o)
                    drawCircle(
                        Color.White,
                        radius = 14.dp.toPx(),
                        center = o,
                        style = Stroke(width = 1.5.dp.toPx()),
                    )
                }
            }

            VisionHud(
                deliveryNumber = deliveryNumber,
                recording = recording,
                latest = latest,
                lostReason = lostReason,
                quality = tracker.quality(),
                diagnostics = diagnostics,
                analysisFps = analysisFps,
                pointCount = tracker.track().size,
                recordedCount = recordedCount,
            )
        } else {
            Text(
                "Camera permission is required for the vision field test.",
                color = Color.White,
                fontSize = 14.sp,
                modifier = Modifier.align(Alignment.Center).padding(32.dp),
            )
        }

        FieldTestControls(
            recording = recording,
            recordedCount = recordedCount,
            modifier = Modifier.align(Alignment.BottomCenter),
            onToggleRecord = {
                val capture = videoCapture ?: return@FieldTestControls
                if (recording) {
                    activeRecording?.stop()
                    activeRecording = null
                    recording = false
                } else {
                    tracker.reset()
                    trail = emptyList()
                    latest = null
                    lostReason = null
                    recordingStartedAt = System.currentTimeMillis()
                    val file = File(
                        context.getExternalFilesDir(null),
                        "vision-test/delivery_%03d.mp4".format(deliveryNumber),
                    ).also { it.parentFile?.mkdirs() }

                    activeRecording = runCatching {
                        capture.output
                            .prepareRecording(
                                context,
                                androidx.camera.video.FileOutputOptions.Builder(file).build(),
                            )
                            .start(recorderExecutor) { event ->
                                if (event is VideoRecordEvent.Finalize) {
                                    val d = tracker.diagnostics()
                                    // The ORIGINAL file path is stored. Nothing in this
                                    // package rewrites, re-encodes or overlays the video.
                                    session.record(
                                        DeliveryRecord(
                                            index = deliveryNumber,
                                            videoPath = file.absolutePath,
                                            startedAtMs = recordingStartedAt,
                                            durationMs = System.currentTimeMillis() - recordingStartedAt,
                                            points = tracker.track(),
                                            quality = tracker.quality(),
                                            framesSeen = d.framesSeen,
                                            framesWithCandidate = d.framesWithCandidate,
                                            rejectedGlobalMotion = d.rejectedGlobalMotion,
                                            rejectedSize = d.rejectedSize,
                                            rejectedShape = d.rejectedShape,
                                            rejectedTrajectory = d.rejectedTrajectory,
                                            averageProcessingMs = d.averageProcessingMs,
                                            maxProcessingMs = d.maxProcessingMs,
                                            analysisFps = analysisFps,
                                        ),
                                    )
                                    recordedCount = session.deliveries().size
                                }
                            }
                    }.getOrNull()
                    recording = true
                }
            },
            onNextDelivery = {
                deliveryNumber++
                tracker.reset()
                trail = emptyList()
                latest = null
                lostReason = null
                diagnostics = tracker.diagnostics()
            },
            onExport = {
                val dir = File(context.getExternalFilesDir(null), "vision-test").also { it.mkdirs() }
                runCatching { session.export(dir) }
            },
        )
    }
}

/** The numbers a tester needs while standing at a ground, and nothing else. */
@Composable
private fun VisionHud(
    deliveryNumber: Int,
    recording: Boolean,
    latest: BallSighting?,
    lostReason: String?,
    quality: TrackQuality,
    diagnostics: VisionDiagnostics,
    analysisFps: Double,
    pointCount: Int,
    recordedCount: Int,
) {
    Column(
        Modifier
            .padding(14.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(Color.Black.copy(alpha = 0.6f))
            .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(10.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(
            "VISION TEST · DELIVERY $deliveryNumber",
            color = Color.White,
            fontSize = 11.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.2.sp,
        )
        Spacer(Modifier.height(10.dp))

        HudRow("Tracking", quality.name, colourFor(quality))
        HudRow(
            "Confidence",
            latest?.let { "${(it.trackingConfidence * 100).toInt()}%" } ?: "—",
            Color.White,
        )
        HudRow("Analysis FPS", "%.1f".format(analysisFps), Color.White)
        HudRow("Processing", "%.0f ms".format(diagnostics.averageProcessingMs), Color.White)
        HudRow("Max", "${diagnostics.maxProcessingMs} ms", Color.White.copy(alpha = 0.7f))
        HudRow("Points", "$pointCount", Color.White)

        Spacer(Modifier.height(8.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.12f)))
        Spacer(Modifier.height(8.dp))

        HudRow("Frames", "${diagnostics.framesSeen}", Color.White.copy(alpha = 0.75f))
        HudRow("Candidates", "${diagnostics.framesWithCandidate}", Color.White.copy(alpha = 0.75f))
        HudRow("Rej · motion", "${diagnostics.rejectedGlobalMotion}", Color(0xFFF5A623))
        HudRow("Rej · size", "${diagnostics.rejectedSize}", Color(0xFFF5A623))
        HudRow("Rej · shape", "${diagnostics.rejectedShape}", Color(0xFFF5A623))
        HudRow("Rej · path", "${diagnostics.rejectedTrajectory}", Color(0xFFF5A623))

        if (recordedCount > 0) {
            Spacer(Modifier.height(8.dp))
            HudRow("Recorded", "$recordedCount", Color(0xFF4ADE80))
        }

        // Shown only while filming, because "ball lost" between deliveries is not news.
        if (recording && lostReason != null) {
            Spacer(Modifier.height(10.dp))
            Text("BALL LOST", color = Color(0xFFF97066), fontSize = 12.sp, fontWeight = FontWeight.ExtraBold)
            Text(lostReason, color = Color(0xFFF97066).copy(alpha = 0.8f), fontSize = 11.sp)
        }
    }
}

@Composable
private fun HudRow(label: String, value: String, colour: Color) {
    Row(Modifier.width(200.dp).padding(vertical = 2.dp)) {
        Text(
            label,
            color = Color.White.copy(alpha = 0.5f),
            fontSize = 11.sp,
            modifier = Modifier.weight(1f),
        )
        Text(value, color = colour, fontSize = 11.sp, fontFamily = FontFamily.Monospace)
    }
}

@Composable
private fun FieldTestControls(
    recording: Boolean,
    recordedCount: Int,
    modifier: Modifier = Modifier,
    onToggleRecord: () -> Unit,
    onNextDelivery: () -> Unit,
    onExport: () -> Unit,
) {
    Row(
        modifier
            .fillMaxWidth()
            .padding(20.dp),
        horizontalArrangement = Arrangement.SpaceEvenly,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        ControlButton(if (recording) "STOP" else "RECORD", onToggleRecord)
        ControlButton("NEXT", onNextDelivery)
        ControlButton(if (recordedCount > 0) "EXPORT $recordedCount" else "EXPORT", onExport)
    }
}

@Composable
private fun ControlButton(label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(24.dp))
            .background(Color.White.copy(alpha = 0.16f))
            .pressable(onClick = onClick)
            .padding(horizontal = 20.dp, vertical = 13.dp),
    ) {
        Text(label, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
    }
}

private fun colourFor(quality: TrackQuality) = when (quality) {
    TrackQuality.RELIABLE -> Color(0xFF4ADE80)
    TrackQuality.PARTIAL -> Color(0xFFF5A623)
    TrackQuality.UNCERTAIN -> Color(0xFFF97066)
}

/**
 * Which filter most likely swallowed the ball, from what moved since the last frame.
 *
 * A guess, and labelled as one — the detector rejects candidates without recording which
 * one was the ball, because it does not know. But "size rejections jumped just as the
 * track died" is the kind of hint that turns an afternoon of filming into a threshold
 * change, which is the entire reason these counters exist.
 */
private fun dominantRejection(now: VisionDiagnostics, before: VisionDiagnostics): String {
    val deltas = listOf(
        "Occlusion or no candidate" to
            (now.framesSeen - before.framesSeen) -
            (now.framesWithCandidate - before.framesWithCandidate),
        "Rejected: camera motion" to (now.rejectedGlobalMotion - before.rejectedGlobalMotion),
        "Rejected: size" to (now.rejectedSize - before.rejectedSize),
        "Rejected: shape" to (now.rejectedShape - before.rejectedShape),
        "Rejected: trajectory" to (now.rejectedTrajectory - before.rejectedTrajectory),
    )
    val worst = deltas.maxByOrNull { it.second }
    return if (worst == null || worst.second <= 0) "Unknown" else worst.first
}

private const val TRAIL_LENGTH = 40
