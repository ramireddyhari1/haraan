package com.haraan.app.vision

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.VectorConverter
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * The marks every vision harness draws over its frames, in one place.
 *
 * Shared by the live field test and the replay harness so that what you see standing at a
 * ground and what you see replaying the clip afterwards are the same picture. Two copies
 * of an overlay is two overlays that drift.
 *
 * THE LOOK IS A BROADCAST ONE, deliberately: a fading ribbon along the flight and a lit
 * ball at the head of it, rather than the scatter of dots this used to draw. That is a
 * rendering decision and only a rendering decision — [BallSighting] is still the only
 * record of where the ball was seen, the ribbon still passes exactly through every one of
 * those measurements, and it still stops dead wherever the tracker lost the ball. The
 * diagnostics panel beside it continues to report discrete accepted and rejected counts,
 * so nothing about the smoothing can hide a detector that is finding half of what it
 * should.
 *
 * Everything here consumes NORMALISED UPRIGHT coordinates, straight from [BallSighting],
 * and multiplies by the size of whatever it is drawn over.
 */

/** How many recent sightings the trail keeps. Older points say nothing about this flight. */
const val VISION_TRAIL_LENGTH = 40

/**
 * The tracked ball and the flight behind it.
 *
 * A composable rather than a plain draw call because the ball marker is animated between
 * detections. The animation moves a MARKER, not a measurement: the position it glides
 * towards is always the last accepted sighting and never past it, so the marker can lag a
 * real detection by a frame but can never lead one. Nothing interpolated here is reported,
 * counted or exported.
 */
@Composable
fun BallTrackOverlay(
    trail: List<BallSighting>,
    latest: BallSighting?,
    modifier: Modifier = Modifier,
    /** The calibrated pitch, when one has been found. Drawn under the flight. */
    quad: PitchQuad? = null,
    /** The one place the ball was measurably on the ground. */
    bounce: Bounce? = null,
    /** The detected wicket, when one was found. */
    stumps: StumpSet? = null,
) {
    val marker = remember { Animatable(Offset.Zero, Offset.VectorConverter) }
    var markerVisible by remember { mutableStateOf(false) }

    LaunchedEffect(latest) {
        val target = latest?.let { Offset(it.x, it.y) }
        if (target == null) {
            markerVisible = false
            return@LaunchedEffect
        }
        val jumped = !markerVisible ||
            (marker.value - target).getDistance() > MARKER_SNAP_DISTANCE
        markerVisible = true
        if (jumped) {
            // A new flight, or the ball reappearing somewhere else entirely. Gliding
            // across the frame to meet it would draw a smooth path through everywhere the
            // ball was NOT.
            marker.snapTo(target)
        } else {
            marker.animateTo(target, tween(MARKER_GLIDE_MS, easing = LinearEasing))
        }
    }

    Canvas(modifier) {
        // Painted back to front: the ground the delivery happened on, then where it
        // landed, then the flight, then the ball. Anything drawn over the ball marker
        // would hide the one thing the screen is for.
        quad?.let { drawPitchCorridor(it) }
        stumps?.let { drawStumps(it) }
        bounce?.let { drawBounce(it) }
        drawBallTrail(trail)
        if (markerVisible && latest != null) {
            drawBallMarker(Offset(marker.value.x * size.width, marker.value.y * size.height))
        }
    }
}

/**
 * The strip the delivery is being bowled on, and the corridor down the middle of it.
 *
 * The corridor is placed in METRES and pushed back through the homography, which is the
 * whole reason it earns its place: if the calibration is wrong the band visibly sits
 * crooked on the grass, and a tester can see that in a glance rather than discovering it
 * three weeks later in a length that was always half a metre out. It is the calibration's
 * own error bar, drawn.
 *
 * Kept dim on purpose. This is the floor of the picture, not the subject of it.
 */
fun DrawScope.drawPitchCorridor(quad: PitchQuad) {
    fun px(point: Point2) = Offset(
        (point.x * size.width).toFloat(),
        (point.y * size.height).toFloat(),
    )

    fun quadPath(points: List<Offset>) = Path().apply {
        moveTo(points[0].x, points[0].y)
        points.drop(1).forEach { lineTo(it.x, it.y) }
        close()
    }

    val corners = quad.corners.map { px(it) }
    if (corners.size == 4 && corners.none { it.x.isNaN() || it.y.isNaN() }) {
        drawPath(
            quadPath(corners),
            // Tapped corners are trusted above a detection, and say so.
            if (quad.source == QuadSource.TAPPED) VisionPalette.WARN.copy(alpha = 0.45f)
            else VisionPalette.GOOD.copy(alpha = 0.32f),
            style = Stroke(width = 1.2.dp.toPx(), cap = StrokeCap.Round),
        )
    }

    // The same band the camera screen and the pitch check draw, from the same metres.
    val toImage = quad.toImage() ?: return
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
        drawPath(quadPath(band), VisionPalette.TRAIL.copy(alpha = 0.14f))
    }
}

/**
 * The detected wicket: three verticals and the line their bases stand on.
 *
 * Drawn as what was actually MEASURED — three bars and a base line — rather than as a
 * finished corridor up the pitch. A corridor would need a plane map, a wicket alone cannot
 * give one, and drawing the conclusion before the evidence exists is how an overlay starts
 * lying. What this shows is exactly what the detector claims: those three things, there.
 */
fun DrawScope.drawStumps(set: StumpSet) {
    val width = 1.6.dp.toPx()
    listOf(set.left, set.middle, set.right).forEach { stump ->
        val x = stump.centreX * size.width
        drawLine(
            VisionPalette.STUMPS,
            Offset(x, stump.topY * size.height),
            Offset(x, stump.baseY * size.height),
            width,
            StrokeCap.Round,
        )
    }

    // The base line is the useful part: it is the only thing here that is on the ground,
    // and it is what a future calibration would be anchored to.
    drawLine(
        VisionPalette.STUMPS.copy(alpha = 0.55f),
        Offset(set.baseLeft.x.toFloat() * size.width, set.baseLeft.y.toFloat() * size.height),
        Offset(set.baseRight.x.toFloat() * size.width, set.baseRight.y.toFloat() * size.height),
        1.dp.toPx(),
        StrokeCap.Round,
    )
}

/**
 * Where the ball pitched.
 *
 * Amber rather than the trail's blue, because this is a different KIND of claim from the
 * rest of the overlay: every other mark is a position in a picture, and this one is a
 * position in metres on a real pitch. Giving it the flight's colour would quietly file it
 * alongside readings that have no scale behind them.
 */
fun DrawScope.drawBounce(bounce: Bounce) {
    val centre = Offset(
        (bounce.image.x * size.width).toFloat(),
        (bounce.image.y * size.height).toFloat(),
    )
    if (centre.x.isNaN() || centre.y.isNaN()) return

    drawCircle(VisionPalette.WARN.copy(alpha = 0.16f), radius = 14.dp.toPx(), center = centre)
    drawCircle(
        VisionPalette.WARN,
        radius = 9.dp.toPx(),
        center = centre,
        style = Stroke(width = 1.4.dp.toPx()),
    )
    drawCircle(VisionPalette.WARN, radius = 2.5.dp.toPx(), center = centre)
}

/**
 * The fading ribbon.
 *
 * Drawn as a run of short segments rather than one stroked path, because the fade and the
 * taper both vary ALONG the line and a single stroke can only have one colour and one
 * width. Each segment carries its own alpha and thickness, and round caps hide the joins.
 *
 * Two passes: a wide, dim one for the glow a broadcast overlay has, then the bright core
 * over it. The glow is what stops a one-pixel line disappearing against grass.
 */
fun DrawScope.drawBallTrail(trail: List<BallSighting>) {
    if (trail.isEmpty()) return

    val runs = TrailGeometry.sampleAll(trail)
    for (samples in runs) {
        if (samples.size < 2) continue

        for (pass in 0 until 2) {
            val glow = pass == 0
            for (i in 0 until samples.size - 1) {
                val a = samples[i]
                val b = samples[i + 1]

                // Fade towards the tail, shaped so the oldest third all but vanishes
                // instead of the line ending in a visible stub.
                val along = b.position.coerceIn(0f, 1f)
                val fade = along * along * along
                val alpha = if (glow) fade * GLOW_ALPHA else fade * CORE_ALPHA
                if (alpha <= 0.01f) continue

                val width = if (glow) {
                    (GLOW_MIN_DP + (GLOW_MAX_DP - GLOW_MIN_DP) * along).dp.toPx()
                } else {
                    (CORE_MIN_DP + (CORE_MAX_DP - CORE_MIN_DP) * along).dp.toPx()
                }

                drawLine(
                    color = (if (glow) VisionPalette.TRAIL else VisionPalette.TRAIL_CORE)
                        .copy(alpha = alpha),
                    start = Offset(a.x * size.width, a.y * size.height),
                    end = Offset(b.x * size.width, b.y * size.height),
                    strokeWidth = width,
                    cap = StrokeCap.Round,
                )
            }
        }
    }
}

/**
 * The ball at the head of the flight.
 *
 * Small on purpose. The marker used to be a nine-dp disc under a fourteen-dp ring, which
 * at phone size covered a good fraction of a pitch and made it impossible to see whether
 * the detection actually sat on the ball or merely near it. A tight core with a thin ring
 * keeps the thing underneath visible, which is the only way this screen can be used to
 * judge the detector rather than to admire it.
 */
fun DrawScope.drawBallMarker(centre: Offset) {
    drawCircle(VisionPalette.GOOD.copy(alpha = 0.18f), radius = 13.dp.toPx(), center = centre)
    drawCircle(VisionPalette.GOOD.copy(alpha = 0.30f), radius = 8.5.dp.toPx(), center = centre)
    drawCircle(Color.White, radius = 3.2.dp.toPx(), center = centre)
    drawCircle(
        Color.White.copy(alpha = 0.9f),
        radius = 8.5.dp.toPx(),
        center = centre,
        style = Stroke(width = 1.2.dp.toPx()),
    )
}

/**
 * The whole overlay in one draw call, for callers that are already inside a Canvas.
 *
 * No marker animation on this path — it has no composition to hold the animation in, so
 * the marker sits exactly on the last measurement. Prefer [BallTrackOverlay].
 */
fun DrawScope.drawBallTrack(trail: List<BallSighting>, latest: BallSighting?) {
    drawBallTrail(trail)
    latest?.let { drawBallMarker(Offset(it.x * size.width, it.y * size.height)) }
}

/** One label/value line of a diagnostics panel. */
@Composable
fun VisionHudRow(label: String, value: String, colour: Color, rowWidth: Int = 200) {
    Row(Modifier.width(rowWidth.dp).padding(vertical = 2.dp)) {
        Text(
            label,
            color = Color.White.copy(alpha = 0.5f),
            fontSize = 11.sp,
            modifier = Modifier.weight(1f),
        )
        Text(value, color = colour, fontSize = 11.sp, fontFamily = FontFamily.Monospace)
    }
}

fun colourFor(quality: TrackQuality) = when (quality) {
    TrackQuality.RELIABLE -> VisionPalette.GOOD
    TrackQuality.PARTIAL -> VisionPalette.WARN
    TrackQuality.UNCERTAIN -> VisionPalette.BAD
}

/**
 * Which filter most likely swallowed the ball, from what moved since the last frame.
 *
 * A guess, and labelled as one — the detector rejects candidates without recording which
 * one was the ball, because it does not know. But "size rejections jumped just as the
 * track died" is the kind of hint that turns an afternoon of filming into a threshold
 * change, which is the entire reason these counters exist.
 */
fun dominantRejection(now: VisionDiagnostics, before: VisionDiagnostics): String {
    val deltas = listOf(
        "Occlusion or no candidate" to
            (now.framesSeen - before.framesSeen) -
            (now.framesWithCandidate - before.framesWithCandidate),
        "Rejected: camera motion" to (now.rejectedGlobalMotion - before.rejectedGlobalMotion),
        "Rejected: size" to (now.rejectedSize - before.rejectedSize),
        "Rejected: shape" to (now.rejectedShape - before.rejectedShape),
        "Rejected: trajectory" to (now.rejectedTrajectory - before.rejectedTrajectory),
        "Rejected: stationary" to (now.rejectedStationary - before.rejectedStationary),
        "Rejected: body cluster" to (now.rejectedCluster - before.rejectedCluster),
    )
    val worst = deltas.maxByOrNull { it.second }
    return if (worst == null || worst.second <= 0) "Unknown" else worst.first
}

/** The colours the vision harnesses speak in. */
object VisionPalette {
    /** The ribbon's glow. */
    val TRAIL = Color(0xFF6E9BF5)

    /** The bright line down the middle of it. */
    val TRAIL_CORE = Color(0xFFBDD3FF)
    val GOOD = Color(0xFF4ADE80)

    /** The wicket. Its own colour, because it is its own kind of claim. */
    val STUMPS = Color(0xFFF2C14E)
    val WARN = Color(0xFFF5A623)
    val BAD = Color(0xFFF97066)
}

/** Fraction of the frame a marker may glide across before it is snapped instead. */
private const val MARKER_SNAP_DISTANCE = 0.18f

/**
 * Short enough that the marker never falls behind by more than about a frame, long enough
 * that consecutive detections read as motion rather than as a flicker between two places.
 */
private const val MARKER_GLIDE_MS = 70

private const val GLOW_ALPHA = 0.34f
private const val CORE_ALPHA = 0.95f
private const val GLOW_MIN_DP = 2.5f
private const val GLOW_MAX_DP = 9f
private const val CORE_MIN_DP = 0.9f
private const val CORE_MAX_DP = 2.8f
