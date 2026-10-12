package com.haraan.app.ui.track

import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import kotlin.math.max
import kotlin.math.min

/*
 * Drawn, not iconified: the ball-tracking pictures are the one thing on these screens that
 * explains the feature without words — a ball let go behind the bowler's arm, pitching, and
 * lighting the far stumps — so they are drawn as that, in the app's own light palette.
 */

private val Turf = Color(0xFFE3F1E6)
private val TurfDeep = Color(0xFFCBE5D2)
private val Strip = Color(0xFFEBDDBF)
private val StripFar = Color(0xFFDCCBA6)
private val Crease = Color(0xFFFFFFFF)
private val Stump = Color(0xFF334155)
private val Channel = Color(0xFF9B8CFF)
private val Tube = Color(0xFFD7141E)
private val TubeRim = Color(0xFF8A0E14)
private val Lit = Color(0xFF2563EB)

/**
 * A delivery from behind the bowler's arm. [progress] 0..1 draws the ball's path in — it pitches
 * at about two thirds — and [lit] 0..1 lights the far stumps once it has arrived.
 */
@Composable
fun BallTrackScene(progress: Float, lit: Float, modifier: Modifier = Modifier) {
    Canvas(modifier) { drawBallTrackScene(progress.coerceIn(0f, 1f), lit.coerceIn(0f, 1f)) }
}

private fun DrawScope.drawBallTrackScene(progress: Float, lit: Float) {
    val w = size.width
    val h = size.height
    // Outfield, lighter towards the far end.
    drawRect(Brush.verticalGradient(listOf(Turf, TurfDeep), startY = 0f, endY = h))

    // The strip in perspective: narrow and far at the top, wide at the foot.
    val farY = h * 0.22f
    val nearY = h * 1.02f
    val farHalf = w * 0.07f
    val nearHalf = w * 0.30f
    val cx = w * 0.5f
    val strip = Path().apply {
        moveTo(cx - farHalf, farY)
        lineTo(cx + farHalf, farY)
        lineTo(cx + nearHalf, nearY)
        lineTo(cx - nearHalf, nearY)
        close()
    }
    drawPath(strip, Brush.verticalGradient(listOf(StripFar, Strip), startY = farY, endY = nearY))
    // Creases at the far end.
    fun across(t: Float): Pair<Float, Float> {
        val y = farY + (nearY - farY) * t
        val half = farHalf + (nearHalf - farHalf) * t
        return y to half
    }
    for (t in listOf(0.035f, 0.11f)) {
        val (y, half) = across(t)
        drawLine(Crease.copy(alpha = 0.95f), Offset(cx - half * 0.92f, y), Offset(cx + half * 0.92f, y), max(1f, w * 0.006f))
    }

    // The channel between the wickets.
    val chFar = farHalf * 0.22f
    val chNear = nearHalf * 0.22f
    val channel = Path().apply {
        moveTo(cx - chFar, farY + 2f)
        lineTo(cx + chFar, farY + 2f)
        lineTo(cx + chNear, nearY)
        lineTo(cx - chNear, nearY)
        close()
    }
    drawPath(channel, Channel.copy(alpha = 0.30f))

    // Far stumps: three bars and the bails, lit with the answer.
    val stumpH = h * 0.10f
    val stumpW = max(1.5f, w * 0.008f)
    val stumpTone = lerpColor(Stump, Lit, lit)
    if (lit > 0f) {
        drawCircle(
            Brush.radialGradient(listOf(Lit.copy(alpha = 0.35f * lit), Color.Transparent), Offset(cx, farY - stumpH / 2), stumpH * 1.4f),
            stumpH * 1.4f,
            Offset(cx, farY - stumpH / 2),
        )
    }
    for (k in -1..1) {
        val x = cx + k * stumpW * 2.4f
        drawLine(stumpTone, Offset(x, farY), Offset(x, farY - stumpH), stumpW, StrokeCap.Round)
    }
    drawLine(stumpTone, Offset(cx - stumpW * 2.6f, farY - stumpH - stumpW), Offset(cx + stumpW * 2.6f, farY - stumpH - stumpW), stumpW * 0.8f, StrokeCap.Round)

    // The flight: from the hand, up and to the left near the lens, down to pitch in line, then
    // a short climb into the stumps.
    val release = Offset(w * 0.30f, h * 0.30f)
    val bounce = Offset(cx - w * 0.01f, farY + (nearY - farY) * 0.20f)
    val arrive = Offset(cx + w * 0.004f, farY - stumpH * 0.45f)
    val path = Path().apply {
        moveTo(release.x, release.y)
        quadraticTo(w * 0.44f, h * 0.20f, bounce.x, bounce.y)
        quadraticTo(bounce.x + w * 0.005f, bounce.y - h * 0.05f, arrive.x, arrive.y)
    }
    val measure = PathMeasure().apply { setPath(path, false) }
    val length = measure.length
    if (progress > 0f && length > 0f) {
        val shown = Path()
        measure.getSegment(0f, length * progress, shown, true)
        val thick = max(2.5f, w * 0.022f)
        drawPath(shown, TubeRim, style = Stroke(thick * 1.25f, cap = StrokeCap.Round, join = StrokeJoin.Round))
        drawPath(shown, Tube, style = Stroke(thick, cap = StrokeCap.Round, join = StrokeJoin.Round))
        drawPath(shown, Color.White.copy(alpha = 0.35f), style = Stroke(thick * 0.25f, cap = StrokeCap.Round))
        // Where it pitched, once it has: a ring lying on the ground.
        val bounceAt = 0.80f
        if (progress >= bounceAt) {
            val r = w * 0.035f
            scale(1f, 0.42f, pivot = bounce) {
                drawCircle(Color.White, r, bounce, style = Stroke(max(1.5f, w * 0.008f)))
            }
        }
        // The ball at the head while it is still travelling.
        if (progress < 1f) {
            val head = measure.getPosition(length * progress)
            drawCircle(Color.White, thick * 0.95f, head)
            drawCircle(Tube.copy(alpha = 0.9f), thick * 0.95f, head, style = Stroke(max(1f, thick * 0.25f)))
        }
    }
}

/** A bat and a ball: playing in a match. */
@Composable
fun PlayGlyph(tint: Color, modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val s = min(size.width, size.height)
        val stroke = s * 0.09f
        // The blade, angled, and its handle.
        val bladeA = Offset(s * 0.30f, s * 0.82f)
        val bladeB = Offset(s * 0.62f, s * 0.38f)
        drawLine(tint, bladeA, bladeB, s * 0.20f, StrokeCap.Round)
        drawLine(tint, bladeB, Offset(s * 0.78f, s * 0.16f), stroke, StrokeCap.Round)
        // The ball, a seam across it.
        val c = Offset(s * 0.30f, s * 0.30f)
        drawCircle(tint, s * 0.13f, c, style = Stroke(stroke * 0.9f))
        drawLine(tint, Offset(c.x - s * 0.06f, c.y + s * 0.06f), Offset(c.x + s * 0.06f, c.y - s * 0.06f), stroke * 0.6f)
    }
}

/** A phone on a tripod, filming. */
@Composable
fun FilmGlyph(tint: Color, recording: Color, modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val s = min(size.width, size.height)
        val stroke = s * 0.085f
        // The phone, landscape on the head.
        drawRoundRect(
            tint,
            topLeft = Offset(s * 0.16f, s * 0.12f),
            size = Size(s * 0.68f, s * 0.40f),
            cornerRadius = CornerRadius(s * 0.08f),
            style = Stroke(stroke),
        )
        drawCircle(recording, s * 0.06f, Offset(s * 0.70f, s * 0.32f))
        drawCircle(tint, s * 0.07f, Offset(s * 0.36f, s * 0.32f), style = Stroke(stroke * 0.7f))
        // The tripod's three legs.
        val head = Offset(s * 0.5f, s * 0.56f)
        drawLine(tint, head, Offset(s * 0.22f, s * 0.92f), stroke, StrokeCap.Round)
        drawLine(tint, head, Offset(s * 0.78f, s * 0.92f), stroke, StrokeCap.Round)
        drawLine(tint, head, Offset(s * 0.5f, s * 0.94f), stroke, StrokeCap.Round)
    }
}

/**
 * Where one delivery pitched, on a pitch seen from above with the batter's stumps at the top:
 * [bounceM] metres from those stumps, [acrossM] to the side (+ = picture-right).
 */
@Composable
fun MiniPitchMap(bounceM: Double?, acrossM: Double?, hitting: Boolean?, modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        drawRoundRect(Strip, cornerRadius = CornerRadius(w * 0.18f))
        // The length bands a coach reads: yorker, full, good, back of a length, short.
        val pitchM = 20.12f
        val top = h * 0.08f
        val usable = h * 0.88f
        fun yOf(m: Double) = top + (m.toFloat() / pitchM).coerceIn(0f, 1f) * usable
        for (edge in listOf(2.0, 6.0, 8.0, 10.0)) {
            drawLine(Color.White.copy(alpha = 0.7f), Offset(w * 0.12f, yOf(edge)), Offset(w * 0.88f, yOf(edge)), max(1f, w * 0.03f))
        }
        // The batter's stumps.
        val stumps = lerpColor(Stump, if (hitting == true) Color(0xFFDC2626) else if (hitting == false) Color(0xFF16A34A) else Stump, 0.85f)
        for (k in -1..1) drawCircle(stumps, max(1.5f, w * 0.045f), Offset(w / 2 + k * w * 0.1f, top))
        if (bounceM != null) {
            val x = (w / 2 + ((acrossM ?: 0.0) / 1.525).toFloat().coerceIn(-1f, 1f) * w * 0.38f)
            val p = Offset(x, yOf(bounceM))
            drawCircle(Tube.copy(alpha = 0.25f), w * 0.2f, p)
            drawCircle(Tube, w * 0.11f, p)
            drawCircle(Color.White, w * 0.11f, p, style = Stroke(max(1f, w * 0.03f)))
        }
    }
}

private fun lerpColor(a: Color, b: Color, t: Float) = Color(
    red = a.red + (b.red - a.red) * t,
    green = a.green + (b.green - a.green) * t,
    blue = a.blue + (b.blue - a.blue) * t,
    alpha = a.alpha + (b.alpha - a.alpha) * t,
)
