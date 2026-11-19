package com.haraan.app.maintenance

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.sin
import kotlin.math.sqrt

/**
 * The maintenance scene: a groundsman's roller working a cricket strip, one set of stumps
 * pulled and laid on the grass. Drawn, not a stock "under construction" graphic, and it's the
 * same scene as `site/maintenance.blade.php` on the web.
 *
 * Drawn in a 320×180 unit box and scaled to fit. Oblique projection: "into the screen" is
 * DEPTH = (24, -30). The only motion is the roller going up and down the strip, its drum
 * turning by exactly the distance travelled, so it reads as rolling, not sliding.
 */
@Composable
fun PitchRollerArt(modifier: Modifier = Modifier) {
    val t by rememberInfiniteTransition(label = "roller").animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(14_000, easing = LinearEasing), RepeatMode.Restart),
        label = "t",
    )
    Canvas(modifier) { drawScene(t) }
}

private val DEPTH = Offset(24f, -30f)
private const val FRONT_Y = 134f
private const val LEFT_X = 10f
private const val RIGHT_X = 286f
private const val DRUM_R = 17f
private const val TRAVEL_MIN = 92f
private const val TRAVEL_MAX = 206f

private val GrassA = Color(0xFFA8D8A0)
private val GrassB = Color(0xFF96CD8F)
private val GrassFront = Color(0xFF6DA866)
private val GrassSide = Color(0xFF5B9455)
private val Soil = Color(0xFFC7A27C)
private val SoilSide = Color(0xFFB08B66)
private val Crease = Color(0xF2FFFFFF)
private val Wood = Color(0xFFE0BC86)
private val WoodDark = Color(0xFFB4884F)
private val DrumFront = Color(0xFF2563EB)
private val DrumBody = Color(0xFF1D4ED8)
private val DrumBack = Color(0xFF1E3A8A)
private val Steel = Color(0xFF334155)
private val Ink = Color(0xFF0F172A)

private fun DrawScope.drawScene(t: Float) {
    val s = minOf(size.width / 320f, size.height / 180f)
    val ox = (size.width - 320f * s) / 2f
    val oy = (size.height - 180f * s) / 2f
    fun p(x: Float, y: Float) = Offset(ox + x * s, oy + y * s)
    fun p(o: Offset) = p(o.x, o.y)
    fun polyL(pts: List<Offset>) = Path().apply {
        moveTo(p(pts[0]).x, p(pts[0]).y)
        for (i in 1 until pts.size) lineTo(p(pts[i]).x, p(pts[i]).y)
        close()
    }
    fun poly(a: Offset, b: Offset, c: Offset, d: Offset) = polyL(listOf(a, b, c, d))

    // The outfield the strip sits in.
    drawOval(Color(0xFFE7F3E4), topLeft = p(4f, 92f), size = Size(312f * s, 70f * s))

    // Ground shadow under the whole strip.
    drawOval(
        color = Ink.copy(alpha = 0.07f),
        topLeft = p(18f, 140f),
        size = Size(296f * s, 16f * s),
    )

    // ── The strip: top face (mown stripes), front edge (grass then soil), right side.
    val fl = Offset(LEFT_X, FRONT_Y)
    val fr = Offset(RIGHT_X, FRONT_Y)
    val bl = fl + DEPTH
    val br = fr + DEPTH
    drawPath(poly(fr, br, br + Offset(0f, 7f), fr + Offset(0f, 7f)), GrassSide)
    drawPath(poly(fr + Offset(0f, 7f), br + Offset(0f, 7f), br + Offset(0f, 13f), fr + Offset(0f, 13f)), SoilSide)
    drawPath(poly(fl, fr, fr + Offset(0f, 7f), fl + Offset(0f, 7f)), GrassFront)
    drawPath(poly(fl + Offset(0f, 7f), fr + Offset(0f, 7f), fr + Offset(0f, 13f), fl + Offset(0f, 13f)), Soil)

    val top = poly(fl, fr, br, bl)
    drawPath(top, GrassA)
    clipPath(top) {
        val stripe = 23f
        var x = LEFT_X
        var i = 0
        while (x < RIGHT_X) {
            if (i % 2 == 1) {
                val a = Offset(x, FRONT_Y)
                val b = Offset(x + stripe, FRONT_Y)
                drawPath(poly(a, b, b + DEPTH, a + DEPTH), GrassB)
            }
            x += stripe
            i++
        }
    }

    // Creases near each end, across the strip.
    for (cx in listOf(42f, 254f)) {
        drawLine(Crease, p(cx, FRONT_Y - 1f), p(Offset(cx, FRONT_Y - 1f) + DEPTH * 0.96f), 1.6f * s, StrokeCap.Round)
    }

    // ── Far end: stumps standing, bails on.
    val stumpBase = Offset(254f, FRONT_Y) + DEPTH * 0.5f
    val stumps = listOf(stumpBase - DEPTH * 0.2f, stumpBase, stumpBase + DEPTH * 0.2f)
    stumps.forEach { b ->
        drawLine(Ink.copy(alpha = 0.10f), p(b), p(b + Offset(9f, 2f)), 2.4f * s, StrokeCap.Round) // shadow
        drawLine(Wood, p(b), p(b - Offset(0f, 24f)), 2.1f * s, StrokeCap.Round)
    }
    drawLine(WoodDark, p(stumps[0] - Offset(0f, 25f)), p(stumps[1] - Offset(0f, 25f)), 1.8f * s, StrokeCap.Round)
    drawLine(WoodDark, p(stumps[1] - Offset(0f, 25f)), p(stumps[2] - Offset(0f, 25f)), 1.8f * s, StrokeCap.Round)

    // ── Near end: stumps pulled and laid down beside the crease.
    for (k in 0..2) {
        val a = Offset(18f + k * 2.5f, FRONT_Y - 5f - k * 3.4f)
        drawLine(WoodDark.copy(alpha = 0.35f), p(a + Offset(1f, 1.6f)), p(a + Offset(23f, 1.6f)), 2.4f * s, StrokeCap.Round)
        drawLine(Wood, p(a), p(a + Offset(22f, 0f)), 2.6f * s, StrokeCap.Round)
    }

    // ── The roller. Position eases up and down the strip; the drum turns by distance / radius.
    val phase = 2f * PI.toFloat() * t
    val mid = (TRAVEL_MIN + TRAVEL_MAX) / 2f
    val half = (TRAVEL_MAX - TRAVEL_MIN) / 2f
    val x = mid - half * cos(phase)
    val goingRight = sin(phase) >= 0f
    val spin = (x - TRAVEL_MIN) / DRUM_R

    val drumLen = DEPTH * 0.78f
    val contact = Offset(x, FRONT_Y) + DEPTH * 0.11f
    val front = contact - Offset(0f, DRUM_R)
    val back = front + drumLen

    // Freshly rolled sheen trailing the drum.
    val trailLen = 46f
    val trailFrom = if (goingRight) x - trailLen else x + trailLen
    val ta = Offset(trailFrom, FRONT_Y) + DEPTH * 0.11f
    val tb = Offset(x, FRONT_Y) + DEPTH * 0.11f
    clipPath(top) {
        drawPath(
            poly(ta, tb, tb + drumLen, ta + drumLen),
            Brush.horizontalGradient(
                colors = listOf(Color.White.copy(alpha = 0f), Color.White.copy(alpha = 0.32f)).let { if (goingRight) it else it.reversed() },
                startX = p(minOf(trailFrom, x), 0f).x,
                endX = p(maxOf(trailFrom, x), 0f).x,
            ),
        )
    }

    // Contact shadow.
    drawOval(
        Ink.copy(alpha = 0.16f),
        topLeft = p(contact.x - 6f, contact.y - 20f + 16f),
        size = Size(30f * s, 6f * s),
    )

    // Handle: an A-frame from both hubs up to a T-bar, trailing behind the drum.
    val grip = front + Offset(-50f, -40f)
    val gripBack = grip + drumLen * 0.9f
    drawLine(Steel, p(back), p(gripBack), 3f * s, StrokeCap.Round)

    // Drum body (oblique cylinder): back cap, band, then the near cap on top.
    val len = sqrt(drumLen.x * drumLen.x + drumLen.y * drumLen.y)
    val n = Offset(-drumLen.y / len, drumLen.x / len) * DRUM_R
    drawCircle(DrumBack, DRUM_R * s, p(back))
    drawPath(poly(front + n, back + n, back - n, front - n), DrumBody)
    drawCircle(DrumFront, DRUM_R * s, p(front))
    drawCircle(Color(0xFF1E40AF), DRUM_R * s, p(front), style = Stroke(1.6f * s))
    // Highlight along the top of the band.
    drawLine(Color.White.copy(alpha = 0.28f), p(front - n * 0.72f), p(back - n * 0.72f), 2.2f * s, StrokeCap.Round)

    // Bolts on the near cap, turning with the distance travelled.
    drawCircle(Color.White.copy(alpha = 0.22f), (DRUM_R - 5f) * s, p(front), style = Stroke(1.2f * s))
    for (k in 0 until 3) {
        val a = spin + k * (2f * PI.toFloat() / 3f)
        drawCircle(Color.White.copy(alpha = 0.9f), 1.5f * s, p(front + Offset(cos(a), sin(a)) * (DRUM_R - 5f)))
    }
    drawCircle(Ink, 3.2f * s, p(front))
    drawCircle(Color.White.copy(alpha = 0.9f), 1.2f * s, p(front))

    // Near arm and the grip bar last, so they sit in front of the drum.
    drawLine(Steel, p(front), p(grip), 3.2f * s, StrokeCap.Round)
    drawLine(Ink, p(grip - drumLen * 0.12f), p(gripBack + drumLen * 0.12f), 4.2f * s, StrokeCap.Round)
}
