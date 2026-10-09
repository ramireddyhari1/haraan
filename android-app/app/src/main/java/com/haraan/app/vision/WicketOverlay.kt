package com.haraan.app.vision

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import android.graphics.Paint
import android.graphics.Typeface
import androidx.compose.ui.graphics.nativeCanvas
import kotlin.math.abs
import kotlin.math.hypot

/**
 * Where the analysis frame actually sits inside the view it is drawn over.
 *
 * WHY THIS IS A TYPE AND NOT FOUR LOCALS. It was four locals, copied into three different
 * Canvas blocks in the pitch-check screen alone, and a fourth copy inside the tap handler
 * that has to invert the same arithmetic. Every overlay in this package is normalised
 * inside the ANALYSIS frame, the preview is letterboxed inside whatever shape the phone
 * is, and the two are the same rectangle only by coincidence. Getting it wrong puts the
 * drawn wicket a finger's width from the real one, which for a screen whose whole job is
 * to show whether the wicket was found correctly is the one failure it may not have.
 */
data class FrameBox(
    val originX: Float,
    val originY: Float,
    val width: Float,
    val height: Float,
) {
    fun toView(point: Point2) = Offset(
        originX + (point.x * width).toFloat(),
        originY + (point.y * height).toFloat(),
    )

    /** The inverse: a touch on the view, in the analysis frame's normalised coordinates. */
    fun toFrame(offset: Offset) = Point2(
        ((offset.x - originX) / width).toDouble(),
        ((offset.y - originY) / height).toDouble(),
    )

    /** Whether a touch landed on the picture at all rather than in the letterbox bars. */
    fun contains(offset: Offset) = offset.x >= originX && offset.y >= originY &&
        offset.x <= originX + width && offset.y <= originY + height

    companion object {
        /**
         * The letterboxed rectangle for a frame of [frameAspect] inside a view of [viewWidth]
         * by [viewHeight]. A frameAspect of zero means the frame's shape is not known yet,
         * and the view's own is used — which is wrong, and wrong in the direction of
         * drawing nothing rather than drawing something misplaced.
         */
        fun letterbox(viewWidth: Float, viewHeight: Float, frameAspect: Float): FrameBox {
            if (viewWidth <= 0f || viewHeight <= 0f) return FrameBox(0f, 0f, 0f, 0f)
            val viewAspect = viewWidth / viewHeight
            val imageAspect = if (frameAspect > 0f) frameAspect else viewAspect
            val w: Float
            val h: Float
            if (viewAspect > imageAspect) {
                h = viewHeight
                w = h * imageAspect
            } else {
                w = viewWidth
                h = w / imageAspect
            }
            return FrameBox((viewWidth - w) / 2f, (viewHeight - h) / 2f, w, h)
        }
    }
}

/**
 * The locked wicket, drawn so that its STATE is visible without reading the readout.
 *
 * The first field test of the pitch-check screen failed on exactly this: a remembered
 * wicket was painted identically to a seen one, the headline said FOUND in green, and the
 * rejection line underneath said there was no wicket in the frame. The green won, because
 * a picture always beats a caption. So a lock that is coasting is drawn dashed and faded,
 * a tentative one is drawn thin, and a hand-placed one is drawn with its own end marks —
 * three different pictures for three different claims.
 */
fun DrawScope.drawWicketLock(
    lock: WicketLock,
    box: FrameBox,
    density: Density,
    /** The lens, for laying the length guide on the ground; no guide without it. */
    camera: CameraIntrinsics? = null,
    /** The upright analysis frame in pixels: width to height. */
    framePx: Pair<Int, Int>? = null,
    /** 0..1 — how far down the pitch the guide has been laid, for the reveal. */
    guideReveal: Float = 1f,
    /** Nothing of the guide is drawn below this y, in view pixels (the controls live there). */
    safeBottomY: Float = Float.MAX_VALUE,
    /** The face the band names are painted in; the app's display face when given. */
    guideTypeface: Typeface? = null,
) {
    if (box.width <= 0f) return

    val live = lock.ageFrames == 0 ||
        (lock.source == WicketLockSource.MANUAL && lock.state == WicketTrackState.CONFIRMED)
    val alpha = when (lock.state) {
        WicketTrackState.CONFIRMED -> 1f
        WicketTrackState.REACQUIRE -> 0.85f
        WicketTrackState.TENTATIVE -> 0.45f
        WicketTrackState.TEMPORARILY_LOST ->
            (0.30f + 0.40f * (1f - lock.ageFrames.toFloat() / WicketTracker.MAX_COAST_FRAMES))
                .coerceIn(0.25f, 0.70f)
        WicketTrackState.LOST -> 0f
    }
    if (alpha <= 0f) return

    val left = box.toView(lock.anchor.baseLeft)
    val right = box.toView(lock.anchor.baseRight)
    val mid = box.toView(lock.anchor.base)

    val dx = right.x - left.x
    val dy = right.y - left.y
    val span = hypot(dx, dy)
    if (span < 1f) return

    // Normalized direction along the base line (left to right)
    val perpX = dx / span
    val perpY = dy / span

    // Upright direction pointing towards stump tops and up the pitch
    var upX = dy / span
    var upY = -dx / span

    // Stumps must always point upward in the frame towards the bowler's end (upY <= 0)
    if (upY > 0f) {
        upX = -upX
        upY = -upY
    }

    val stumpHeight = if (lock.anchor.top != null) {
        val topView = box.toView(lock.anchor.top)
        val hx = topView.x - mid.x
        val hy = topView.y - mid.y
        val h = hypot(hx, hy)
        if (h > 5f && hy < 0f) {
            upX = hx / h
            upY = hy / h
            h
        } else {
            (span * 2.85f).coerceAtLeast(with(density) { 25.dp.toPx() })
        }
    } else {
        (span * 2.85f).coerceAtLeast(with(density) { 25.dp.toPx() })
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. THE LENGTH GUIDE, LAID ON THE REAL GROUND
    // ─────────────────────────────────────────────────────────────────────────
    if ((lock.state == WicketTrackState.CONFIRMED || lock.state == WicketTrackState.REACQUIRE) &&
        camera != null && framePx != null && guideReveal > 0f
    ) {
        drawLengthGuide(lock, box, density, camera, framePx, guideReveal, alpha, safeBottomY, guideTypeface)
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. GROUND ANCHORS & AMBIENT CONTACT SHADOWS (REPLACES CLUNKY CYAN BAR)
    // ─────────────────────────────────────────────────────────────────────────
    val stumpWidth = (span * 0.11f).coerceIn(with(density) { 4.dp.toPx() }, with(density) { 13.dp.toPx() })
    val stumpBases = listOf(left, mid, right)
    val stumpTops = stumpBases.map { b ->
        Offset(b.x + upX * stumpHeight, b.y + upY * stumpHeight)
    }

    // Realistic ambient contact shadows under the stump bases
    stumpBases.forEach { basePt ->
        drawOval(
            color = Color.Black.copy(alpha = 0.50f * alpha),
            topLeft = Offset(basePt.x - stumpWidth * 0.85f, basePt.y - stumpWidth * 0.35f),
            size = androidx.compose.ui.geometry.Size(stumpWidth * 1.7f, stumpWidth * 0.7f),
        )
    }

    // Subtle optical lock indicator: fine brackets at the base ends
    val bracketColor = Color.White.copy(alpha = 0.70f * alpha)
    val bracketStroke = with(density) { 1.2.dp.toPx() }
    val bracketLen = stumpWidth * 0.9f
    // Left bracket [
    drawLine(bracketColor, Offset(left.x - perpX * bracketLen, left.y - perpY * bracketLen), left, bracketStroke)
    drawLine(bracketColor, left, Offset(left.x + upX * bracketLen, left.y + upY * bracketLen), bracketStroke)
    // Right bracket ]
    drawLine(bracketColor, Offset(right.x + perpX * bracketLen, right.y + perpY * bracketLen), right, bracketStroke)
    drawLine(bracketColor, right, Offset(right.x + upX * bracketLen, right.y + upY * bracketLen), bracketStroke)

    // ─────────────────────────────────────────────────────────────────────────
    // 3. TURNED ENGLISH WILLOW STUMPS WITH BRASS FERRULE GROUND SPIKES
    // ─────────────────────────────────────────────────────────────────────────
    stumpBases.forEachIndexed { idx, basePt ->
        val topPt = stumpTops[idx]

        // 3D cylindrical lighting gradient across the stump width
        val gradStart = Offset(basePt.x - perpX * stumpWidth * 0.5f, basePt.y - perpY * stumpWidth * 0.5f)
        val gradEnd = Offset(basePt.x + perpX * stumpWidth * 0.5f, basePt.y + perpY * stumpWidth * 0.5f)
        val willowGradient = Brush.linearGradient(
            colorStops = arrayOf(
                0.00f to Color(0xFF3B1E08).copy(alpha = alpha), // Deep wood shadow edge
                0.15f to Color(0xFF6B3A11).copy(alpha = alpha), // Warm timber shadow
                0.36f to Color(0xFFFBF4EB).copy(alpha = alpha), // Specular varnish sheen
                0.54f to Color(0xFFD99B4B).copy(alpha = alpha), // Warm golden English willow
                0.78f to Color(0xFFA56625).copy(alpha = alpha), // Amber heartwood
                1.00f to Color(0xFF45220A).copy(alpha = alpha), // Rim shadow edge
            ),
            start = gradStart,
            end = gradEnd,
        )

        // Spike transition point (bottom 12% of stump is brass ferrule spike)
        val ferruleH = stumpHeight * 0.12f
        val ferruleTopPt = Offset(basePt.x + upX * ferruleH, basePt.y + upY * ferruleH)

        // Wooden shaft from ferrule to top
        drawLine(
            brush = willowGradient,
            start = ferruleTopPt,
            end = topPt,
            strokeWidth = stumpWidth,
            cap = StrokeCap.Butt,
        )

        // Brass ferrule ground spike (golden metallic taper)
        val brassGradient = Brush.linearGradient(
            colorStops = arrayOf(
                0.00f to Color(0xFF5C4700).copy(alpha = alpha),
                0.25f to Color(0xFFD4AF37).copy(alpha = alpha),
                0.50f to Color(0xFFFFF8DC).copy(alpha = alpha),
                0.75f to Color(0xFFD4AF37).copy(alpha = alpha),
                1.00f to Color(0xFF705800).copy(alpha = alpha),
            ),
            start = gradStart,
            end = gradEnd,
        )

        // Draw ferrule spike as tapered path to ground
        val ferrulePath = Path().apply {
            moveTo(ferruleTopPt.x - perpX * stumpWidth * 0.48f, ferruleTopPt.y - perpY * stumpWidth * 0.48f)
            lineTo(ferruleTopPt.x + perpX * stumpWidth * 0.48f, ferruleTopPt.y + perpY * stumpWidth * 0.48f)
            lineTo(basePt.x + perpX * stumpWidth * 0.15f, basePt.y + perpY * stumpWidth * 0.15f)
            lineTo(basePt.x - perpX * stumpWidth * 0.15f, basePt.y - perpY * stumpWidth * 0.15f)
            close()
        }
        drawPath(ferrulePath, brassGradient)

        // Turned brass ferrule ring
        drawLine(
            color = Color(0xFFFFE082).copy(alpha = alpha),
            start = Offset(ferruleTopPt.x - perpX * stumpWidth * 0.52f, ferruleTopPt.y - perpY * stumpWidth * 0.52f),
            end = Offset(ferruleTopPt.x + perpX * stumpWidth * 0.52f, ferruleTopPt.y + perpY * stumpWidth * 0.52f),
            strokeWidth = with(density) { 1.5.dp.toPx() },
            cap = StrokeCap.Round,
        )

        // Turned neck groove near top
        val neckPt = Offset(topPt.x - upX * stumpWidth * 0.7f, topPt.y - upY * stumpWidth * 0.7f)
        val neckStart = Offset(neckPt.x - perpX * stumpWidth * 0.45f, neckPt.y - perpY * stumpWidth * 0.45f)
        val neckEnd = Offset(neckPt.x + perpX * stumpWidth * 0.45f, neckPt.y + perpY * stumpWidth * 0.45f)
        drawLine(
            color = Color(0xFF2E1505).copy(alpha = 0.85f * alpha),
            start = neckStart,
            end = neckEnd,
            strokeWidth = with(density) { 1.2.dp.toPx() },
            cap = StrokeCap.Round,
        )

        // Domed top cap with bail groove
        drawCircle(
            color = Color(0xFFFDE68A).copy(alpha = alpha),
            radius = stumpWidth * 0.48f,
            center = topPt,
        )
        drawCircle(
            color = Color(0xFF8B5A2B).copy(alpha = alpha * 0.7f),
            radius = stumpWidth * 0.22f,
            center = topPt,
        )
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. HANDCRAFTED TWO-PIECE BAILS (Off-Bail & Leg-Bail with Turned Barrels)
    // ─────────────────────────────────────────────────────────────────────────
    val bailSpigotWidth = stumpWidth * 0.45f
    val bailBarrelWidth = stumpWidth * 0.72f
    val bailLift = Offset(upX * stumpWidth * 0.45f, upY * stumpWidth * 0.45f)

    val bailWoodBrush = Brush.linearGradient(
        colorStops = arrayOf(
            0.0f to Color(0xFFFFF7ED).copy(alpha = alpha),
            0.4f to Color(0xFFE0A94F).copy(alpha = alpha),
            1.0f to Color(0xFF5A3010).copy(alpha = alpha),
        ),
        start = stumpTops[0],
        end = stumpTops[2],
    )

    // Bail 1 (Left to Middle)
    val b1Start = stumpTops[0] + bailLift - Offset(perpX * stumpWidth * 0.15f, perpY * stumpWidth * 0.15f)
    val b1End = stumpTops[1] + bailLift + Offset(perpX * stumpWidth * 0.05f, perpY * stumpWidth * 0.05f)
    val b1Mid = Offset((b1Start.x + b1End.x) / 2f, (b1Start.y + b1End.y) / 2f)
    // Spigot line
    drawLine(bailWoodBrush, b1Start, b1End, strokeWidth = bailSpigotWidth, cap = StrokeCap.Round)
    // Turned central barrel
    val b1BarrelStart = Offset(b1Mid.x - perpX * span * 0.12f, b1Mid.y - perpY * span * 0.12f)
    val b1BarrelEnd = Offset(b1Mid.x + perpX * span * 0.12f, b1Mid.y + perpY * span * 0.12f)
    drawLine(bailWoodBrush, b1BarrelStart, b1BarrelEnd, strokeWidth = bailBarrelWidth, cap = StrokeCap.Round)

    // Bail 2 (Middle to Right)
    val b2Start = stumpTops[1] + bailLift - Offset(perpX * stumpWidth * 0.05f, perpY * stumpWidth * 0.05f)
    val b2End = stumpTops[2] + bailLift + Offset(perpX * stumpWidth * 0.15f, perpY * stumpWidth * 0.15f)
    val b2Mid = Offset((b2Start.x + b2End.x) / 2f, (b2Start.y + b2End.y) / 2f)
    // Spigot line
    drawLine(bailWoodBrush, b2Start, b2End, strokeWidth = bailSpigotWidth, cap = StrokeCap.Round)
    // Turned central barrel
    val b2BarrelStart = Offset(b2Mid.x - perpX * span * 0.12f, b2Mid.y - perpY * span * 0.12f)
    val b2BarrelEnd = Offset(b2Mid.x + perpX * span * 0.12f, b2Mid.y + perpY * span * 0.12f)
    drawLine(bailWoodBrush, b2BarrelStart, b2BarrelEnd, strokeWidth = bailBarrelWidth, cap = StrokeCap.Round)
}

/**
 * The detected stump region, boxed and labelled, for the developer readout only.
 *
 * The box is the stumps' own extent — outer feet to top, padded by a third of the span —
 * and the label is the five numbers a tester needs to judge a lock at a glance: confidence,
 * estimated distance, span in pixels, roll, and how it was found. Never drawn for players.
 */
fun DrawScope.drawWicketRegion(lock: WicketLock, box: FrameBox, label: String, density: Density) {
    val a = lock.anchor
    val left = box.toView(a.baseLeft)
    val right = box.toView(a.baseRight)
    val foot = box.toView(a.base)
    val top = a.top?.let { box.toView(it) }
    val spanPx = hypot(right.x - left.x, right.y - left.y)
    val heightPx = top?.let { abs(foot.y - it.y) } ?: (spanPx * 3.7f)
    val pad = maxOf(spanPx / 3f, with(density) { 6.dp.toPx() })
    val x0 = minOf(left.x, right.x) - pad
    val x1 = maxOf(left.x, right.x) + pad
    val y1 = maxOf(left.y, right.y) + pad
    val y0 = y1 - pad * 2 - heightPx
    val colour = when (lock.state) {
        WicketTrackState.CONFIRMED -> VisionPalette.GOOD
        WicketTrackState.TENTATIVE -> VisionPalette.WARN
        else -> VisionPalette.WARN.copy(alpha = 0.8f)
    }
    val stroke = with(density) { 1.5.dp.toPx() }
    drawRect(
        color = colour,
        topLeft = Offset(x0, y0),
        size = androidx.compose.ui.geometry.Size(x1 - x0, y1 - y0),
        style = Stroke(width = stroke),
    )
    val paint = Paint().apply {
        color = android.graphics.Color.WHITE
        textSize = with(density) { 11.sp.toPx() }
        typeface = Typeface.MONOSPACE
        isAntiAlias = true
    }
    val bg = Paint().apply { color = android.graphics.Color.argb(200, 0, 0, 0) }
    val textW = paint.measureText(label)
    val tx = (x0).coerceIn(4f, (size.width - textW - 8f).coerceAtLeast(4f))
    val ty = (y0 - with(density) { 6.dp.toPx() }).coerceAtLeast(paint.textSize + 4f)
    drawContext.canvas.nativeCanvas.apply {
        drawRect(tx - 4f, ty - paint.textSize, tx + textW + 4f, ty + 5f, bg)
        drawText(label, tx, ty, paint)
    }
}

/** The label [drawWicketRegion] puts over the box. */
fun wicketRegionLabel(lock: WicketLock, camera: CameraIntrinsics?, uprightWidthPx: Int, rollDeg: Float?): String =
    buildString {
        append("%.0f%%".format(lock.confidence * 100f))
        if (camera != null) WicketRange.fromSpan(lock, camera)?.let { append(" · %.1f m est".format(it)) }
        if (uprightWidthPx > 0) append(" · %.1f px".format(WicketRange.spanPx(lock, uprightWidthPx)))
        rollDeg?.let { append(" · %+.1f°".format(it)) }
        append(" · ").append(lock.method?.label ?: lock.state.name.lowercase())
    }

/**
 * Taped-distance validation, for the developer readout: say how far the phone really is from
 * the stumps, record a few seconds, move, repeat, save the CSV.
 */
@Composable
fun WicketValidationPanel(
    trueDistanceM: Double,
    onDistanceChange: (Double) -> Unit,
    recording: Boolean,
    onRecord: () -> Unit,
    onSave: () -> Unit,
    onClear: () -> Unit,
    rows: List<WicketValidation.Row>,
    savedPath: String?,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Color.Black.copy(alpha = 0.62f))
            .padding(horizontal = 13.dp, vertical = 11.dp),
    ) {
        Text("FIELD VALIDATION", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            ValidationButton("−") { onDistanceChange((trueDistanceM - 1.0).coerceAtLeast(1.0)) }
            Text(
                "%.0f m taped".format(trueDistanceM),
                color = Color.White,
                fontSize = 12.sp,
                fontFamily = FontFamily.Monospace,
                modifier = Modifier.padding(horizontal = 10.dp),
            )
            ValidationButton("+") { onDistanceChange((trueDistanceM + 1.0).coerceAtMost(60.0)) }
        }
        Spacer(Modifier.height(6.dp))
        Row {
            ValidationButton(if (recording) "Recording…" else "Record 3 s", enabled = !recording, onClick = onRecord)
            Spacer(Modifier.width(6.dp))
            ValidationButton("Save CSV", enabled = rows.isNotEmpty() && !recording, onClick = onSave)
            Spacer(Modifier.width(6.dp))
            ValidationButton("Clear", enabled = rows.isNotEmpty() && !recording, onClick = onClear)
        }
        if (rows.isNotEmpty()) {
            Spacer(Modifier.height(6.dp))
            DiagnosticRow("taped", "ready · est ± sd · err")
            for (r in rows) {
                DiagnosticRow(
                    "%.0f m".format(r.trueDistanceM),
                    "%.0f%% · %s · %s".format(
                        r.readyRate * 100,
                        r.meanEstimateM?.let { m -> "%.1f±%.1f".format(m, r.sdEstimateM ?: 0.0) } ?: "—",
                        r.errorPct?.let { e -> "%+.1f%%".format(e) } ?: "—",
                    ),
                )
            }
        }
        savedPath?.let {
            Spacer(Modifier.height(4.dp))
            Text(it, color = Color.White.copy(alpha = 0.55f), fontSize = 9.5.sp, fontFamily = FontFamily.Monospace)
        }
    }
}

@Composable
private fun ValidationButton(label: String, enabled: Boolean = true, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(if (enabled) Color(0xFF2563EB) else Color.White.copy(alpha = 0.12f))
            .then(if (enabled) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(horizontal = 10.dp, vertical = 6.dp),
    ) {
        Text(label, color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * Everything the tracker knows, in a column somebody can read at arm's length in the sun.
 *
 * MONOSPACE VALUES IN A FIXED COLUMN, because the numbers here are watched while they
 * change and a proportional font makes a steady value look like it is twitching. The
 * headline states the STATE, never "found": [WicketTrackState] has five answers and only
 * one of them is the one a reader would assume from the word found.
 */
@Composable
fun WicketDiagnosticsPanel(
    diagnostics: WicketDiagnostics,
    lock: WicketLock?,
    modifier: Modifier = Modifier,
    detectorAvailable: Boolean = true,
    /** The detector's own account of its last frame, when the screen has one. */
    report: StumpDetectorReport? = null,
    /** How far off level the phone is, from gravity; magnitude only. */
    deviceRollDeg: Float? = null,
    /** The lens, for a distance estimate; null when the device reported nothing usable. */
    camera: CameraIntrinsics? = null,
    /** The upright analysis frame's width in pixels, for the span in pixels. */
    uprightWidthPx: Int = 0,
    /** From the screen opening the camera to the first analysed frame, wall clock. */
    cameraToFirstFrameMs: Long? = null,
    /** The last exception frame analysis hit, or null. Shown in red: it is why nothing works. */
    analysisError: String? = null,
) {
    Column(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Color.Black.copy(alpha = 0.62f))
            .padding(horizontal = 13.dp, vertical = 11.dp),
    ) {
        val kindWord = when (diagnostics.kind) {
            WicketKind.STUMPS -> "STUMPS"
            WicketKind.STONE -> "STONE"
            null -> "WICKET"
        }
        val headline = when {
            !detectorAvailable -> "WICKET · NO OPENCV"
            diagnostics.state == WicketTrackState.LOST -> "WICKET · SEARCHING"
            else -> "$kindWord · ${diagnostics.state.name.replace('_', ' ')}"
        }
        val headlineColour = when {
            !detectorAvailable -> VisionPalette.BAD
            diagnostics.state == WicketTrackState.CONFIRMED -> VisionPalette.GOOD
            diagnostics.state == WicketTrackState.REACQUIRE -> VisionPalette.GOOD.copy(alpha = 0.8f)
            diagnostics.state == WicketTrackState.LOST -> VisionPalette.BAD
            else -> VisionPalette.WARN
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(headline, color = headlineColour, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            if (diagnostics.lockSource == WicketLockSource.MANUAL) {
                Spacer(Modifier.width(6.dp))
                Text(
                    "BY HAND",
                    color = VisionPalette.TRAIL_CORE,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.2.sp,
                )
            }
        }

        analysisError?.let {
            Spacer(Modifier.height(5.dp))
            Text(
                "analysis error: $it",
                color = VisionPalette.BAD,
                fontSize = 10.5.sp,
                lineHeight = 13.sp,
                fontFamily = FontFamily.Monospace,
            )
        }

        Spacer(Modifier.height(7.dp))

        // A bar, because confidence is the one value here that is watched continuously and
        // a moving bar is readable from further away than four digits are.
        ConfidenceBar(diagnostics.confidence)
        Spacer(Modifier.height(7.dp))

        DiagnosticRow("confidence", "%.0f%%".format(diagnostics.confidence * 100f))
        // STARTUP, as measured on this phone: camera open → first analysed frame (wall
        // clock), then first frame → first sighting → READY (camera clock).
        DiagnosticRow("camera → frame", cameraToFirstFrameMs?.let { "$it ms" } ?: "—")
        DiagnosticRow("→ first detection", diagnostics.timeToFirstSightingMs?.let { "$it ms" } ?: "—")
        DiagnosticRow("→ READY", diagnostics.timeToReadyMs?.let { "$it ms" } ?: "—")
        DiagnosticRow(
            "method",
            buildString {
                append(diagnostics.method?.label ?: "—")
                diagnostics.foundBy?.takeIf { it != diagnostics.method }?.let { append(" (found: ${it.label})") }
            },
        )
        DiagnosticRow("fps", if (diagnostics.framesPerSecond <= 0f) "—" else "%.1f".format(diagnostics.framesPerSecond))
        DiagnosticRow("time to lock", diagnostics.lastLockMs?.let { "%.1f s".format(it / 1000f) } ?: "—")
        // What staleness MEANS differs by source, so the row does too rather than printing
        // one number that is only meaningful for one of them.
        if (diagnostics.lockSource == WicketLockSource.MANUAL) {
            DiagnosticRow("camera blind", "${diagnostics.blindFrames}f")
        } else {
            DiagnosticRow("age", "${diagnostics.ageFrames}f")
        }
        DiagnosticRow("held", "${diagnostics.heldFrames}f")
        DiagnosticRow("jitter", "%.4f".format(diagnostics.jitter))
        DiagnosticRow("confirmed", "${diagnostics.confirmations}")
        // The headline number for a shaky mount, and the reason this counter exists: a lock
        // that re-acquires forty times a minute is not a lock, it is a slideshow that
        // happens to land on the right answer often enough to look like one.
        DiagnosticRow("re-acquires", "${diagnostics.reacquires}")
        DiagnosticRow("drops", "${diagnostics.drops}")
        DiagnosticRow("off-lock", "${diagnostics.gateRejections}")
        DiagnosticRow("turns", "${diagnostics.rotations}")
        DiagnosticRow(
            "camera",
            if (diagnostics.motionConfidence <= 0f) {
                "unresolved (${diagnostics.motionUnresolved})"
            } else {
                "%.2f · %.4f fw".format(diagnostics.motionConfidence, diagnostics.motionMagnitude)
            },
        )
        DiagnosticRow(
            "seen",
            "${diagnostics.framesWithSighting}/${diagnostics.framesSeen}",
        )
        // Multi-candidate and memory: how many places are being weighed, and whether a lost
        // lock's place is still held for an instant re-acquire.
        DiagnosticRow("places", "${diagnostics.hypotheses}")
        DiagnosticRow(
            "memory",
            diagnostics.memoryAgeMs?.let { "%.1f s · %d back".format(it / 1000f, diagnostics.memoryReacquires) }
                ?: "— · ${diagnostics.memoryReacquires} back",
        )
        DiagnosticRow("cadence", if (diagnostics.cadenceMs <= 0f) "—" else "%.0f ms".format(diagnostics.cadenceMs))
        DiagnosticRow("precision", if (diagnostics.subPixel) "sub-pixel" else "contour")
        DiagnosticRow(
            "roll",
            buildString {
                append(diagnostics.rollDeg?.let { "stumps %+.1f°".format(it) } ?: "stumps —")
                deviceRollDeg?.let { append(" · phone %.1f°".format(kotlin.math.abs(it))) }
            },
        )

        report?.let { r ->
            Spacer(Modifier.height(5.dp))
            DiagnosticRow("detector", "${r.mode} · ${r.lastProcessingMs} ms (avg %.0f)".format(r.averageProcessingMs))
            DiagnosticRow("comb", "${r.combFits}/${r.probes} fits")
            DiagnosticRow(
                "fit",
                if (r.lastNcc == null) "—" else "ncc %.2f · snr %.0f".format(r.lastNcc, r.lastSnr ?: 0f),
            )
            DiagnosticRow(
                "focus hits",
                if (r.focusRuns == 0) "—" else "${r.focusHits}/${r.focusRuns}",
            )
            DiagnosticRow("threshold", "C %.1f · %s".format(r.adaptiveC, if (r.stumpsWereDark) "dark" else "pale"))
            DiagnosticRow("bars", "${r.barsFound}" + if (r.barsAboveHorizon > 0) " (+${r.barsAboveHorizon} sky)" else "")
            r.lastRejection?.takeIf { it.isNotBlank() }?.let {
                Spacer(Modifier.height(4.dp))
                Text(
                    it,
                    color = Color.White.copy(alpha = 0.55f),
                    fontSize = 10.sp,
                    lineHeight = 12.sp,
                    fontFamily = FontFamily.Monospace,
                )
            }
        }

        lock?.let {
            Spacer(Modifier.height(5.dp))
            DiagnosticRow(
                "span",
                if (uprightWidthPx > 0) "%.1f px · %.4f fw".format(WicketRange.spanPx(it, uprightWidthPx), it.span)
                else "%.4f fw".format(it.span),
            )
            if (camera != null && it.kind == WicketKind.STUMPS) {
                val bySpan = WicketRange.fromSpan(it, camera)
                val byHeight = WicketRange.fromHeight(it, camera)
                // "est." and the lens source, every time: unvalidated until a taped check.
                DiagnosticRow(
                    "distance (est.)",
                    buildString {
                        append(bySpan?.let { d -> "%.1f m".format(d) } ?: "—")
                        byHeight?.let { d -> append(" · h %.1f m".format(d)) }
                    },
                )
                DiagnosticRow("lens", "%s · %.0f°".format(camera.source, camera.horizontalFovDeg))
            }
            DiagnosticRow(
                "scale",
                it.metresPerUnitAcross()?.let { m -> "%.3f m/fw".format(m) }
                    ?: if (it.kind == WicketKind.STONE) "none from a stone" else "not measurable",
            )
            it.metresPerUnitDown()?.let { m -> DiagnosticRow("scale · up", "%.3f m/fw".format(m)) }
        }

        diagnostics.note?.let {
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
}

@Composable
private fun ConfidenceBar(confidence: Float) {
    val filled = confidence.coerceIn(0f, 1f)
    Box(
        Modifier
            .fillMaxWidth()
            .height(3.dp)
            .clip(RoundedCornerShape(2.dp))
            .background(Color.White.copy(alpha = 0.14f)),
    ) {
        Box(
            Modifier
                .fillMaxWidth(filled)
                .height(3.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(
                    when {
                        filled >= 0.7f -> VisionPalette.GOOD
                        filled >= 0.4f -> VisionPalette.WARN
                        else -> VisionPalette.BAD
                    },
                ),
        )
    }
}

@Composable
private fun DiagnosticRow(label: String, value: String) {
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

/**
 * The wicket projection, and every LBW question it did not answer.
 *
 * THE UNJUDGED LIMBS ARE NOT SMALL PRINT, THEY ARE THE COMPONENT. One phone behind the
 * bowler's arm can say where a flight would have crossed the stump line. It cannot say
 * where the ball pitched relative to the line, where on the pad it struck, how high, or
 * whether it touched the bat on the way — and four of the five questions an LBW is made of
 * are in that second list. Printing the verdict alone would be a decision; printing it
 * with the list is a measurement, which is what it actually is.
 *
 * So the four unjudged rows are never collapsible, never behind a tap, and never shorter
 * than the verdict above them.
 */
@Composable
fun LbwProjectionPanel(projection: LbwProjection, modifier: Modifier = Modifier) {
    Column(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Color.Black.copy(alpha = 0.62f))
            .padding(horizontal = 13.dp, vertical = 11.dp),
    ) {
        Text(
            "WICKET PROJECTION",
            color = Color.White.copy(alpha = 0.32f),
            fontSize = 9.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(6.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .size(7.dp)
                    .clip(CircleShape)
                    .background(
                        when (projection.verdict) {
                            LbwVerdict.HITTING -> VisionPalette.GOOD
                            LbwVerdict.MISSING -> VisionPalette.BAD
                            LbwVerdict.UMPIRES_CALL -> VisionPalette.WARN
                            LbwVerdict.UNAVAILABLE -> Color.White.copy(alpha = 0.25f)
                        },
                    ),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                when (projection.verdict) {
                    LbwVerdict.HITTING -> "HITTING"
                    LbwVerdict.MISSING -> "MISSING"
                    LbwVerdict.UMPIRES_CALL -> "TOO CLOSE TO CALL"
                    LbwVerdict.UNAVAILABLE -> "NO PROJECTION"
                },
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
            )
        }

        projection.offsetM?.let { offset ->
            val band = projection.uncertaintyM ?: 0.0
            Spacer(Modifier.height(5.dp))
            Text(
                // Both numbers always, because the band is what makes the first one mean
                // anything. Eight centimetres either side of the middle stump is a
                // different claim depending on whether the band is one centimetre or six.
                "%.0f cm %s of middle · ±%.0f cm".format(
                    abs(offset) * 100,
                    if (offset >= 0) "right" else "left",
                    band * 100,
                ),
                color = Color.White.copy(alpha = 0.78f),
                fontSize = 11.5.sp,
                fontFamily = FontFamily.Monospace,
            )
            projection.heightM?.let {
                Text(
                    "%.2f m above the ground at the stumps".format(it),
                    color = Color.White.copy(alpha = 0.6f),
                    fontSize = 10.5.sp,
                    fontFamily = FontFamily.Monospace,
                )
            }
        }

        Spacer(Modifier.height(7.dp))
        Text(
            projection.basis,
            color = Color.White.copy(alpha = 0.48f),
            fontSize = 10.sp,
            lineHeight = 13.sp,
        )

        val unjudged = projection.limbs.filterNot { it.judged }
        if (unjudged.isNotEmpty()) {
            Spacer(Modifier.height(9.dp))
            Text(
                "NOT JUDGED",
                color = VisionPalette.WARN.copy(alpha = 0.85f),
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.2.sp,
            )
            Spacer(Modifier.height(4.dp))
            unjudged.forEach { limb ->
                Row(Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
                    Box(
                        Modifier
                            .padding(top = 6.dp)
                            .size(width = 5.dp, height = 1.dp)
                            .background(Color.White.copy(alpha = 0.3f)),
                    )
                    Spacer(Modifier.width(7.dp))
                    Column {
                        Text(limb.question, color = Color.White.copy(alpha = 0.6f), fontSize = 10.5.sp)
                        Text(
                            limb.answer,
                            color = Color.White.copy(alpha = 0.32f),
                            fontSize = 9.5.sp,
                            lineHeight = 12.sp,
                        )
                    }
                }
            }
        }
    }
}


/** The app's own length bands (see [BounceLength]), from the striker's stumps, metres. */
private class LengthBand(val label: String, val fromM: Double, val toM: Double, val colour: Color)

/** Band names are stretched this many times along the pitch, road-marking style. */
private const val LABEL_STRETCH = 3.0

private val LENGTH_BANDS = listOf(
    LengthBand("YORKER", 0.0, 1.0, Color(0xFFF43F5E)),
    LengthBand("FULL", 1.0, 3.0, Color(0xFFF59E0B)),
    LengthBand("GOOD LENGTH", 3.0, 6.0, Color(0xFF22C55E)),
    LengthBand("BACK OF LENGTH", 6.0, 9.0, Color(0xFF0EA5E9)),
    LengthBand("SHORT", 9.0, 14.0, Color(0xFF8B5CF6)),
)

/**
 * The length guide, in metres, on the ground.
 *
 * WHAT WAS WRONG. It was a fixed multiple of the stump height on screen with zone lines at
 * arbitrary fractions of it — no metre anywhere in it — so it never lay on the floor; every
 * label was drawn twice, left and right, and overlapped itself ("YORKERYORKER"); and every
 * line was the same thin near-white, so the zones did not read as zones.
 *
 * WHAT IT IS NOW. [PitchGround] projects real pitch metres through this camera, so the bands
 * converge and widen exactly as the floor does, and use the app's own length bands, so the
 * guide agrees with what the app calls a ball. Each band is a translucent wash with a crisp
 * painted leading edge and a soft shadow under it — paint on a surface, not a line in the
 * air. Creases are drawn at their real widths, thickness following perspective. One label
 * per band, sized to the band and left out when it would not fit. Nothing is drawn below
 * [safeBottomY], where the controls are.
 */
private fun DrawScope.drawLengthGuide(
    lock: WicketLock,
    box: FrameBox,
    density: Density,
    camera: CameraIntrinsics,
    framePx: Pair<Int, Int>,
    reveal: Float,
    alpha: Float,
    safeBottomY: Float,
    typeface: Typeface?,
) {
    val (fw, fh) = framePx
    val ground = PitchGround.fromWicket(lock, camera, fw, fh) ?: return

    fun toView(p: Pair<Double, Double>) = Offset(
        box.originX + (p.first / fw).toFloat() * box.width,
        box.originY + (p.second / fh).toFloat() * box.height,
    )
    fun at(alongM: Double, acrossM: Double): Offset? = ground.project(alongM, acrossM)?.let(::toView)

    // How far down the pitch is on screen and clear of the controls, then how much of that
    // the reveal has laid so far.
    val limitFrameY = ((minOf(safeBottomY, box.originY + box.height) - box.originY) / box.height * fh).toDouble()
    val visible = minOf(ground.visibleTo(limitFrameY, 14.0), ground.stumpsDistanceM - 1.0)
    if (visible <= 0.3) return
    val laid = visible * reveal.coerceIn(0f, 1f)

    val halfW = PitchGeometry.RETURN_CREASE_HALF_WIDTH_M
    val stumpHalf = PitchGeometry.STUMP_SET_WIDTH_M / 2.0
    val hairline = with(density) { 0.6.dp.toPx() }

    /** A patch of ground, as a path in view pixels; null if any corner is behind the camera. */
    fun patch(a0: Double, a1: Double, w0: Double, w1: Double): Path? {
        val p1 = at(a0, w0) ?: return null
        val p2 = at(a0, w1) ?: return null
        val p3 = at(a1, w1) ?: return null
        val p4 = at(a1, w0) ?: return null
        return Path().apply {
            moveTo(p1.x, p1.y); lineTo(p2.x, p2.y); lineTo(p3.x, p3.y); lineTo(p4.x, p4.y); close()
        }
    }

    /**
     * Paint ACROSS the pitch: a strip [widthM] deep on the ground, so it is thick near the
     * camera and thin far away, exactly as paint is. A dark under-edge a hairline wider keeps
     * it legible on a pale floor; a floor of about a pixel keeps a far line from vanishing.
     */
    fun paintAcross(alongM: Double, w0: Double, w1: Double, widthM: Double, colour: Color) {
        val near = at(alongM + widthM / 2, 0.0) ?: return
        val far = at(alongM - widthM / 2, 0.0) ?: return
        val depthPx = near.y - far.y
        val grow = if (depthPx < 1.2f) (1.2f - depthPx) / 2f else 0f
        val a = at(alongM, w0) ?: return
        val b = at(alongM, w1) ?: return
        val under = depthPx + grow * 2 + hairline * 2
        drawLine(Color.Black.copy(alpha = 0.30f * alpha), a, b, under, StrokeCap.Butt)
        patch(alongM - widthM / 2, alongM + widthM / 2, w0, w1)?.let {
            if (grow > 0f) drawLine(colour, a, b, depthPx + grow * 2, StrokeCap.Butt) else drawPath(it, colour)
        }
    }

    /** Paint ALONG the pitch at a fixed distance across, [widthM] wide, tapering with depth. */
    fun paintAlong(acrossM: Double, a0: Double, a1: Double, widthM: Double, colour: Color) {
        patch(a0, a1, acrossM - widthM / 2, acrossM + widthM / 2 + 0.0)?.let { path ->
            val a = at(a0, acrossM) ?: return
            val b = at(a1, acrossM) ?: return
            drawLine(Color.Black.copy(alpha = 0.22f * alpha), a, b, hairline * 2.2f, StrokeCap.Butt)
            drawPath(path, colour)
            // Far end thinner than a pixel still reads as a line.
            drawLine(colour.copy(alpha = colour.alpha * 0.6f), a, b, hairline, StrokeCap.Butt)
        }
    }

    // ── The bands: a glow off each band's leading edge, fading across it ──────────────
    // A flat tint over a busy floor (tiles, worn grass) mixes into mud; light that falls
    // off from the edge reads as a lit surface and leaves the ground itself visible.
    for (band in LENGTH_BANDS) {
        if (band.fromM >= laid) break
        val to = minOf(band.toM, laid)
        val path = patch(band.fromM, to, -halfW, halfW) ?: continue
        val from = at(band.fromM, 0.0) ?: continue
        val end = at(to, 0.0) ?: continue
        drawPath(
            path,
            Brush.linearGradient(
                colorStops = arrayOf(
                    0.00f to band.colour.copy(alpha = 0.30f * alpha),
                    0.45f to band.colour.copy(alpha = 0.10f * alpha),
                    1.00f to band.colour.copy(alpha = 0.03f * alpha),
                ),
                start = from,
                end = end,
            ),
        )
        if (band.fromM > 0.0) paintAcross(band.fromM, -halfW, halfW, 0.04, band.colour.copy(alpha = 0.95f * alpha))
    }

    // ── The pitch's edges ───────────────────────────────────────────────────────────
    for (side in listOf(-halfW, halfW)) paintAlong(side, 0.0, laid, 0.03, Color.White.copy(alpha = 0.55f * alpha))

    // ── The stumps line: the wicket's width, straight down the pitch ─────────────────
    // What an LBW is judged against, so it is the one thing drawn over everything else.
    patch(0.0, laid, -stumpHalf, stumpHalf)?.let { path ->
        val from = at(0.0, 0.0)
        val end = at(laid, 0.0)
        if (from != null && end != null) {
            drawPath(
                path,
                Brush.linearGradient(
                    colorStops = arrayOf(
                        0f to Color(0xFF7DD3FC).copy(alpha = 0.34f * alpha),
                        1f to Color(0xFF7DD3FC).copy(alpha = 0.16f * alpha),
                    ),
                    start = from,
                    end = end,
                ),
            )
        }
    }
    for (side in listOf(-stumpHalf, stumpHalf)) paintAlong(side, 0.0, laid, 0.025, Color(0xFFE0F2FE).copy(alpha = 0.95f * alpha))

    // ── The creases, at the Laws' widths ──────────────────────────────────────────────
    val chalk = Color.White.copy(alpha = 0.95f * alpha)
    paintAcross(0.0, -halfW, halfW, 0.05, chalk)
    if (laid >= PitchGeometry.POPPING_CREASE_AHEAD_M) {
        paintAcross(PitchGeometry.POPPING_CREASE_AHEAD_M, -1.83, 1.83, 0.05, chalk)
        for (side in listOf(-halfW, halfW)) {
            paintAlong(side, 0.0, PitchGeometry.POPPING_CREASE_AHEAD_M, 0.05, chalk)
        }
    }

    // ── The names, painted on the pitch ───────────────────────────────────────────────
    /*
     * Lying on the ground in perspective, not floating over it: the word's rectangle is
     * mapped onto a patch of pitch with a perspective transform, so it foreshortens with the
     * band it names, the way paint on a pitch or a road does. Stretched three times along
     * the pitch — road-marking style — so it still reads from a camera at chest height.
     * In the band's right half: the stumps line stays clear, and the left edge of the screen
     * carries the metric cards.
     */
    val canvas = drawContext.canvas.nativeCanvas
    val textPaint = Paint().apply {
        isAntiAlias = true
        isSubpixelText = true
        this.typeface = typeface ?: Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
        textSize = 120f
        letterSpacing = 0.04f
        color = android.graphics.Color.argb((235 * alpha).toInt(), 255, 255, 255)
        setShadowLayer(6f, 0f, 3f, android.graphics.Color.argb((140 * alpha).toInt(), 0, 0, 0))
    }
    val metrics = textPaint.fontMetrics
    val textH = metrics.descent - metrics.ascent
    val left = stumpHalf + 0.16
    val right = halfW - 0.10
    val roomAcross = right - left
    for (band in LENGTH_BANDS) {
        if (band.toM > laid + 0.01) break
        val textW = textPaint.measureText(band.label)
        val aspect = textW / textH
        val across = minOf(roomAcross, 1.0)
        val depth = minOf((across / aspect) * LABEL_STRETCH, (band.toM - band.fromM) * 0.62)
        val mid = (band.fromM + band.toM) / 2
        val a0 = mid - depth / 2
        val a1 = mid + depth / 2
        val w0 = left + (roomAcross - across) / 2
        val w1 = w0 + across
        val tl = at(a0, w0) ?: continue
        val tr = at(a0, w1) ?: continue
        val br = at(a1, w1) ?: continue
        val bl = at(a1, w0) ?: continue
        // Too small to read, or past the controls: leave it out rather than smudge it.
        if (bl.y - tl.y < with(density) { 8.dp.toPx() } || bl.y > safeBottomY) continue
        val m = android.graphics.Matrix()
        val ok = m.setPolyToPoly(
            floatArrayOf(0f, 0f, textW, 0f, textW, textH, 0f, textH), 0,
            floatArrayOf(tl.x, tl.y, tr.x, tr.y, br.x, br.y, bl.x, bl.y), 0,
            4,
        )
        if (!ok) continue
        canvas.save()
        canvas.concat(m)
        canvas.drawText(band.label, 0f, -metrics.ascent, textPaint)
        canvas.restore()
    }
}
