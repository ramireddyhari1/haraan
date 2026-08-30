package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.unit.dp
import kotlin.math.cos
import kotlin.math.sin

// ─────────────────────────────────────────────────────────────────────────────
//  THE GROUND
//
//  A cricket ground seen at a low angle, cut out of the earth: grass on top,
//  soil through the side. Drawn rather than photographed, so it is the same
//  object on every card and reads instantly as "a pitch".
//
//  What makes it feel physical rather than clip-art is all in the lighting:
//  the grass is lit from the upper left and falls away at the far edge, the
//  soil wall darkens as it curves away, the mown arcs follow the ellipse
//  instead of being straight bands, and the whole disc floats on a soft
//  contact shadow that moves with it. Nothing spins — a rotating pitch would
//  be the one thing a cricketer would find absurd.
// ─────────────────────────────────────────────────────────────────────────────

private val GrassLit = Color(0xFF3F7F4A)
private val GrassMid = Color(0xFF2E6438)
private val GrassFar = Color(0xFF1F4A29)
private val SoilTop = Color(0xFF4A3524)
private val SoilDeep = Color(0xFF241809)
private val PitchTan = Color(0xFFCBB78C)
private val PitchWorn = Color(0xFFB9A075)

/**
 * @param settle 0→1 entrance: the disc rises into place and gains its depth.
 */
@Composable
fun GroundDisc3D(modifier: Modifier = Modifier, settle: Float = 1f) {
    val idle = rememberInfiniteTransition(label = "ground")
    // A slow float. Big enough to notice the object is suspended, small enough
    // that the card never feels busy while somebody reads figures beside it.
    val bob by idle.animateFloat(
        initialValue = -1f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(3600, easing = LinearEasing), RepeatMode.Reverse),
        label = "bob",
    )
    // Light travelling across the grass, which is what sells a curved surface.
    val sheen by idle.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(5200, easing = LinearEasing)),
        label = "sheen",
    )

    Box(modifier.fillMaxWidth().height(132.dp)) {
        Canvas(Modifier.fillMaxWidth().height(132.dp)) {
            val eased = settle.coerceIn(0f, 1f)
            if (eased <= 0.01f) return@Canvas

            val cx = size.width / 2f
            val rx = size.width * 0.36f * (0.94f + 0.06f * eased)
            val ry = rx * 0.40f
            // Shallow — and it took two passes to get here. A visible wall of earth
            // turns the ground into an object sitting on the page, which is exactly the
            // decorative-3D look this is meant to avoid. What is wanted is a surface seen
            // from a low angle, so the earth is now a rim you read as thickness rather
            // than a cylinder you read as a drum.
            val depth = ry * 0.20f * eased
            // Rises the last few pixels into place, then breathes.
            val cy = size.height * 0.38f + (1f - eased) * 14.dp.toPx() + bob * 2.5f.dp.toPx()

            drawContactShadow(cx, cy + depth, rx, ry, eased)
            drawSoil(cx, cy, rx, ry, depth)
            drawGrass(cx, cy, rx, ry, sheen)
            drawMarkings(cx, cy, rx, ry)
            drawPitch(cx, cy, rx, ry)
        }
    }
}

/** The ground is floating, so the shadow underneath it is what says so. */
private fun DrawScope.drawContactShadow(cx: Float, cy: Float, rx: Float, ry: Float, eased: Float) {
    drawOval(
        brush = Brush.radialGradient(
            colors = listOf(Color.Black.copy(alpha = 0.16f * eased), Color.Transparent),
            center = Offset(cx, cy + ry * 0.5f),
            radius = rx * 1.1f,
        ),
        topLeft = Offset(cx - rx * 1.05f, cy - ry * 0.2f),
        size = Size(rx * 2.1f, ry * 1.5f),
    )
}

/**
 * The earth the grass sits on: a cylinder wall between the top ellipse and its
 * copy pushed down by [depth], shaded so the sides fall into shadow.
 */
private fun DrawScope.drawSoil(cx: Float, cy: Float, rx: Float, ry: Float, depth: Float) {
    // Bottom cap first, so the wall has something to end against.
    drawOval(
        color = SoilDeep,
        topLeft = Offset(cx - rx, cy - ry + depth),
        size = Size(rx * 2f, ry * 2f),
    )
    // The wall. Curvature comes from the shading, not from geometry: a flat band
    // with a left-to-right ramp reads as round at this size.
    val wall = Path().apply {
        moveTo(cx - rx, cy)
        lineTo(cx - rx, cy + depth)
        arcTo(
            rect = Rect(cx - rx, cy - ry + depth, cx + rx, cy + ry + depth),
            startAngleDegrees = 180f,
            sweepAngleDegrees = -180f,
            forceMoveTo = false,
        )
        lineTo(cx + rx, cy)
        arcTo(
            rect = Rect(cx - rx, cy - ry, cx + rx, cy + ry),
            startAngleDegrees = 0f,
            sweepAngleDegrees = -180f,
            forceMoveTo = false,
        )
        close()
    }
    drawPath(
        wall,
        Brush.horizontalGradient(
            0f to SoilDeep,
            0.28f to SoilTop,
            0.62f to SoilTop.copy(alpha = 0.95f),
            1f to SoilDeep,
            startX = cx - rx,
            endX = cx + rx,
        ),
    )
    // Soil is not smooth. A few horizontal striations catch the light and stop the
    // wall reading as a flat brown bar.
    for (i in 1..3) {
        val y = cy + depth * (i / 4f)
        drawLine(
            color = Color.Black.copy(alpha = 0.10f),
            start = Offset(cx - rx * 0.94f, y),
            end = Offset(cx + rx * 0.94f, y),
            strokeWidth = 1.dp.toPx(),
        )
    }
}

/** The playing surface: lit from the upper left, falling away at the far edge. */
private fun DrawScope.drawGrass(cx: Float, cy: Float, rx: Float, ry: Float, sheen: Float) {
    drawOval(
        brush = Brush.radialGradient(
            colors = listOf(GrassLit, GrassMid, GrassFar),
            center = Offset(cx - rx * 0.25f, cy - ry * 0.45f),
            radius = rx * 1.5f,
        ),
        topLeft = Offset(cx - rx, cy - ry),
        size = Size(rx * 2f, ry * 2f),
    )

    // Mown arcs, following the ellipse rather than crossing it — the giveaway that
    // this is a curved surface and not a green disc with stripes on it.
    for (i in 1..6) {
        val f = i / 7f
        drawOval(
            color = if (i % 2 == 0) Color.White.copy(alpha = 0.055f) else Color.Black.copy(alpha = 0.035f),
            topLeft = Offset(cx - rx * f, cy - ry * f),
            size = Size(rx * 2f * f, ry * 2f * f),
            style = Stroke(width = ry * 0.30f),
        )
    }

    // A soft highlight travelling across the turf.
    val sweepX = cx + (sheen * 2f - 1f) * rx
    drawOval(
        brush = Brush.radialGradient(
            colors = listOf(Color.White.copy(alpha = 0.10f), Color.Transparent),
            center = Offset(sweepX, cy - ry * 0.3f),
            radius = rx * 0.55f,
        ),
        topLeft = Offset(cx - rx, cy - ry),
        size = Size(rx * 2f, ry * 2f),
    )
}

/** Boundary rope and the thirty-yard ring. */
private fun DrawScope.drawMarkings(cx: Float, cy: Float, rx: Float, ry: Float) {
    drawOval(
        color = Color.White.copy(alpha = 0.85f),
        topLeft = Offset(cx - rx * 0.965f, cy - ry * 0.965f),
        size = Size(rx * 1.93f, ry * 1.93f),
        style = Stroke(width = 1.6.dp.toPx()),
    )
    drawOval(
        color = Color.White.copy(alpha = 0.42f),
        topLeft = Offset(cx - rx * 0.60f, cy - ry * 0.60f),
        size = Size(rx * 1.2f, ry * 1.2f),
        style = Stroke(
            width = 1.2.dp.toPx(),
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(6f, 7f), 0f),
        ),
    )
}

/**
 * The square, laid ACROSS the ground.
 *
 * It ran up the ellipse at first, which made a short wide oval carry a tall narrow
 * strip: it overflowed onto the soil wall and read as a post driven through the pitch.
 * Along the long axis it fits the shape, and it is how the ground is drawn on every
 * scorecard graphic a cricketer has ever seen.
 */
private fun DrawScope.drawPitch(cx: Float, cy: Float, rx: Float, ry: Float) {
    val halfLen = rx * 0.40f
    val halfWide = ry * 0.155f

    val strip = Path().apply {
        // A touch narrower at the far side, which is all the perspective this needs.
        moveTo(cx - halfLen, cy + halfWide)
        lineTo(cx + halfLen, cy + halfWide)
        lineTo(cx + halfLen * 0.97f, cy - halfWide * 0.86f)
        lineTo(cx - halfLen * 0.97f, cy - halfWide * 0.86f)
        close()
    }
    drawPath(
        strip,
        Brush.verticalGradient(
            listOf(PitchTan, PitchWorn),
            startY = cy - halfWide,
            endY = cy + halfWide,
        ),
    )
    drawPath(strip, Color.Black.copy(alpha = 0.12f), style = Stroke(width = 1f))

    // Creases: one across the strip at each end.
    listOf(-1f, 1f).forEach { side ->
        val x = cx + side * halfLen * 0.74f
        drawLine(
            Color.White.copy(alpha = 0.75f),
            Offset(x, cy - halfWide * 0.86f),
            Offset(x, cy + halfWide),
            1.2.dp.toPx(),
        )
    }

    // Stumps: three short ticks standing at each end, leaning away from the viewer.
    listOf(-1f, 1f).forEach { side ->
        val baseX = cx + side * halfLen * 0.93f
        val stumpH = 5.5.dp.toPx()
        for (k in -1..1) {
            val x = baseX + k * 1.6.dp.toPx()
            drawLine(
                Color.White.copy(alpha = 0.95f),
                Offset(x, cy + halfWide * 0.2f),
                Offset(x, cy + halfWide * 0.2f - stumpH),
                1.2.dp.toPx(),
                cap = StrokeCap.Round,
            )
        }
    }
}
