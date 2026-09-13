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
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
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
 * PITCH CHECK — does the detector find the creases, and if not, where does it stop?
 *
 * The pitch detector only runs inside the paired-camera flow, which needs a match, a
 * second phone and a pairing code before a single frame reaches it. That is a long walk to
 * answer a question about four lines, and it makes the most likely failure — the detector
 * quietly finding nothing — indistinguishable from a pairing problem.
 *
 * So it gets its own door, the same way OpenCV loading did:
 *
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://pitch-check"
 *
 * WHAT THIS PROVES AND WHAT IT DOES NOT. Pointed at anything, it proves the detector runs
 * at a usable frame rate, does not crash, and refuses scenes that are not pitches. Pointed
 * at a real pitch it would show whether the creases are actually found — but a wall, a
 * screen, or a taped-out rectangle is not a pitch, and a quad locked onto one says nothing
 * about grass, worn paint or afternoon shadow. The readout below is instrumentation, not a
 * score.
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

@Composable
private fun PitchCheckScreen() {
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
    val detector = remember { OpenCvPitchDetector() }

    var quad by remember { mutableStateOf<PitchQuad?>(null) }
    /*
     * The shape of the frame the detector actually looked at, once turned upright.
     *
     * The overlay cannot be drawn against the whole view. The preview letterboxes the
     * camera's aspect ratio inside whatever shape the screen is, so a point at 0.5 of the
     * IMAGE is not at 0.5 of the VIEW unless the two happen to match. Held in portrait they
     * never do.
     */
    var frameAspect by remember { mutableStateOf(0f) }
    var report by remember { mutableStateOf(detector.report()) }
    var tappedCorners by remember { mutableStateOf<List<Point2>>(emptyList()) }
    var tapping by remember { mutableStateOf(false) }

    DisposableEffect(Unit) {
        onDispose {
            detector.release()
            analysisExecutor.shutdown()
        }
    }

    /*
     * Unlike the camera screen, this one keeps looking after it succeeds.
     *
     * There the answer is wanted once and then held steady; here the whole point is to
     * watch it re-decide as the phone moves, because a detector that locks onto a doorframe
     * and never lets go is exactly the failure worth catching.
     */
    val analyzer = remember {
        ImageAnalysis.Analyzer { image ->
            try {
                val plane = image.planes.getOrNull(0)
                if (plane != null) {
                    val buffer = plane.buffer
                    buffer.rewind()
                    val bytes = ByteArray(buffer.remaining())
                    buffer.get(bytes)
                    val rotation = image.imageInfo.rotationDegrees
                    quad = detector.detect(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = rotation,
                    )
                    // A quarter turn swaps the frame's width and height.
                    frameAspect = if (rotation % 180 == 0) {
                        image.width.toFloat() / image.height
                    } else {
                        image.height.toFloat() / image.width
                    }
                    report = detector.report()
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

        if (shown != null) {
            Canvas(Modifier.fillMaxSize()) {
                // Where the preview image actually sits inside the view, bars and all.
                val viewAspect = size.width / size.height
                val imageAspect = if (frameAspect > 0f) frameAspect else viewAspect
                val drawWidth: Float
                val drawHeight: Float
                if (viewAspect > imageAspect) {
                    drawHeight = size.height
                    drawWidth = drawHeight * imageAspect
                } else {
                    drawWidth = size.width
                    drawHeight = drawWidth / imageAspect
                }
                val originX = (size.width - drawWidth) / 2f
                val originY = (size.height - drawHeight) / 2f

                fun px(point: Point2) = Offset(
                    originX + (point.x * drawWidth).toFloat(),
                    originY + (point.y * drawHeight).toFloat(),
                )

                fun quadPath(points: List<Offset>) = Path().apply {
                    moveTo(points[0].x, points[0].y)
                    points.drop(1).forEach { lineTo(it.x, it.y) }
                    close()
                }

                val colour = if (shown.source == QuadSource.TAPPED) {
                    Color(0xFFFACC15)
                } else {
                    Color(0xFF4ADE80)
                }
                drawPath(
                    quadPath(shown.corners.map { px(it) }),
                    colour,
                    style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round),
                )

                // The corridor, in metres through the homography — the same drawing the
                // camera screen does, and the thing that shows a bad calibration.
                shown.toImage()?.let { toImage ->
                    val half = PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * 0.35
                    val near = -PitchGeometry.POPPING_CREASE_AHEAD_M
                    val far = PitchGeometry.CALIBRATION_LENGTH_M + near
                    val band = listOf(
                        Point2(-half, near),
                        Point2(half, near),
                        Point2(half, far),
                        Point2(-half, far),
                    ).map { px(toImage.map(it)) }
                    if (band.none { it.x.isNaN() || it.y.isNaN() }) {
                        drawPath(quadPath(band), Color(0xFF6E9BF5).copy(alpha = 0.25f))
                    }
                }
            }
        }

        if (tapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(Unit) {
                        detectTapGestures { offset ->
                            val viewAspect = size.width.toFloat() / size.height
                            val imageAspect = if (frameAspect > 0f) frameAspect else viewAspect
                            val drawWidth: Float
                            val drawHeight: Float
                            if (viewAspect > imageAspect) {
                                drawHeight = size.height.toFloat()
                                drawWidth = drawHeight * imageAspect
                            } else {
                                drawWidth = size.width.toFloat()
                                drawHeight = drawWidth / imageAspect
                            }
                            val point = Point2(
                                ((offset.x - (size.width - drawWidth) / 2f) / drawWidth).toDouble(),
                                ((offset.y - (size.height - drawHeight) / 2f) / drawHeight).toDouble(),
                            )
                            val next = tappedCorners + point
                            tappedCorners = next
                            if (next.size >= 4) tapping = false
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val viewAspect = size.width / size.height
                    val imageAspect = if (frameAspect > 0f) frameAspect else viewAspect
                    val drawWidth: Float
                    val drawHeight: Float
                    if (viewAspect > imageAspect) {
                        drawHeight = size.height
                        drawWidth = drawHeight * imageAspect
                    } else {
                        drawWidth = size.width
                        drawHeight = drawWidth / imageAspect
                    }
                    val originX = (size.width - drawWidth) / 2f
                    val originY = (size.height - drawHeight) / 2f
                    tappedCorners.forEach { point ->
                        drawCircle(
                            Color(0xFFFACC15),
                            radius = 7.dp.toPx(),
                            center = Offset(
                                originX + (point.x * drawWidth).toFloat(),
                                originY + (point.y * drawHeight).toFloat(),
                            ),
                        )
                    }
                }
            }
        }

        Column(
            Modifier
                .align(Alignment.TopStart)
                .statusBarsPadding()
                .padding(14.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color.Black.copy(alpha = 0.62f))
                .padding(horizontal = 13.dp, vertical = 11.dp),
        ) {
            Text(
                when {
                    tapping -> "TAPPING · ${tappedCorners.size}/4"
                    tappedQuad != null -> "QUAD SET BY HAND"
                    quad != null -> "PITCH FOUND"
                    else -> "SEARCHING"
                },
                color = if (shown != null) Color(0xFF4ADE80) else Color(0xFFFCA5A5),
                fontSize = 14.sp,
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

            shown?.let {
                Spacer(Modifier.height(7.dp))
                Text(
                    "source ${it.source} · confidence %.2f".format(it.confidence),
                    color = Color.White.copy(alpha = 0.72f),
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                )
            }
        }

        Row(
            Modifier
                .align(Alignment.BottomCenter)
                .padding(22.dp),
        ) {
            TestButton(if (tapping) "Cancel" else "Tap 4 corners") {
                if (tapping) {
                    tapping = false
                    tappedCorners = emptyList()
                } else {
                    tappedCorners = emptyList()
                    tapping = true
                }
            }
            Spacer(Modifier.width(10.dp))
            TestButton("Reset") {
                tapping = false
                tappedCorners = emptyList()
                detector.reset()
                quad = null
            }
        }
    }
}

@Composable
private fun ReadoutRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth()) {
        Text(
            label,
            color = Color.White.copy(alpha = 0.55f),
            fontSize = 11.5.sp,
            fontFamily = FontFamily.Monospace,
        )
        Spacer(Modifier.width(10.dp))
        Text(
            value,
            color = Color.White,
            fontSize = 11.5.sp,
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
            .padding(horizontal = 18.dp, vertical = 11.dp),
    ) {
        Text(label, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}
