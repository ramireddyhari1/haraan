package com.haraan.app.camera

import android.graphics.Paint
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.drawscope.withTransform
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.platform.LocalContext
import com.haraan.app.R
import com.haraan.app.ui.components.MARK_H
import com.haraan.app.ui.components.MARK_W
import com.haraan.app.ui.components.MARK_X0
import com.haraan.app.ui.components.MARK_Y0
import com.haraan.app.ui.components.PIECE_LEFT
import com.haraan.app.ui.components.PIECE_RIGHT
import com.haraan.app.ui.components.PIECE_STROKE
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.delay
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.exp
import kotlin.math.hypot
import kotlin.math.ln
import kotlin.math.max
import kotlin.math.pow
import kotlin.math.sin
import kotlin.math.sqrt

/**
 * One run of the replay stinger: [covered] completes when the ball fills the screen (swap
 * what is under it then), [finished] when it has pulled back off the screen.
 */
internal class StingerRun(val word: String) {
    val covered = CompletableDeferred<Unit>()
    val finished = CompletableDeferred<Unit>()
}

/**
 * THE BALL WIPE — the broadcast way into a replay and back out of it (2026-11-20).
 *
 * A red leather ball, seam and stitches and the Haraan H stamped in gold, flies at the lens
 * spinning until it fills the screen; while it covers everything the picture behind it is
 * swapped; then it pulls away, turning, and what is behind it now shows round it. "REPLAY"
 * on the way in to a replay, "LIVE" on the way back to the camera. About a second and a half.
 */
@Composable
internal fun ReplayStinger(run: StingerRun) {
    val context = LocalContext.current
    val grow = remember(run) { Animatable(0f) }
    val shrink = remember(run) { Animatable(0f) }
    var coveredYet by remember(run) { mutableStateOf(false) }
    val pieces = remember {
        listOf(PIECE_STROKE, PIECE_LEFT, PIECE_RIGHT).map { PathParser().parsePathString(it).toPath() }
    }
    val face = remember {
        runCatching { androidx.core.content.res.ResourcesCompat.getFont(context, R.font.archivo_black) }.getOrNull()
    }
    val paint = remember { Paint(Paint.ANTI_ALIAS_FLAG).apply { textAlign = Paint.Align.CENTER } }

    LaunchedEffect(run) {
        grow.animateTo(1f, tween(IN_MS, easing = LinearEasing))
        coveredYet = true
        run.covered.complete(Unit)
        cricketThud(context, Thud.FOUR)
        delay(HOLD_MS)
        shrink.animateTo(1f, tween(OUT_MS, easing = LinearEasing))
        run.finished.complete(Unit)
    }

    Canvas(Modifier.fillMaxSize()) {
        val w = size.width
        val h = size.height
        val cover = hypot(w, h) / 2f * 1.06f
        val start = minOf(w, h) * 0.035f
        val ball: Triple<Offset, Float, Float> // centre, radius, spin
        if (!coveredYet) {
            val t = grow.value
            // Coming at the lens: the size grows geometrically, faster and faster.
            val e = t.toDouble().pow(1.7)
            val r = (start * exp(ln((cover / start).toDouble()) * e)).toFloat()
            val c = Offset(w * (0.64f - 0.14f * t), h * (0.66f - 0.16f * t))
            // Behind it, the picture dims as it arrives.
            drawRect(Color.Black.copy(alpha = 0.45f * t))
            ball = Triple(c, r, (-2.6f * (1f - t).pow(1.3f)))
        } else {
            val t = shrink.value
            // Going away: slow off the mark, then gone.
            val e = t.toDouble().pow(1.6)
            val r = (cover * exp(ln((start / cover).toDouble()) * e)).toFloat()
            val c = Offset(w * (0.5f - 0.1f * t), h * (0.5f - 0.12f * t))
            ball = Triple(c, r, 2.8f * t.pow(1.4f))
        }
        if (shrink.value >= 1f) return@Canvas
        drawCricketBall(ball.first, ball.second, ball.third, pieces, run.word, face, paint)
    }
}

/** The ball at [c], radius [r], turned [spin] radians about the upright. */
private fun DrawScope.drawCricketBall(
    c: Offset,
    r: Float,
    spin: Float,
    mark: List<Path>,
    word: String,
    face: android.graphics.Typeface?,
    paint: Paint,
) {
    // Leather, lit from the upper left.
    drawCircle(
        Brush.radialGradient(
            0f to Color(0xFFE0524A),
            0.38f to Color(0xFFAE1F25),
            0.78f to Color(0xFF6C0C12),
            1f to Color(0xFF3E0509),
            center = Offset(c.x - 0.34f * r, c.y - 0.40f * r),
            radius = 1.45f * r,
        ),
        r,
        c,
    )

    val cs = cos(spin)
    val sn = sin(spin)
    /** Body point -> (screen offset, depth towards the viewer). */
    fun project(x: Double, y: Double, z: Double): Pair<Offset, Double> {
        val rx = x * cs + z * sn
        val rz = -x * sn + z * cs
        return Offset(c.x + (rx * r).toFloat(), c.y - (y * r).toFloat()) to rz
    }

    // THE SEAM: a great circle, a dark groove with a row of stitches each side.
    val n = norm(0.5, 0.3, 1.0)
    val u = norm(cross(n, Triple(0.0, 1.0, 0.0)))
    val v = cross(n, u)
    val steps = 180
    var prev: Pair<Offset, Double>? = null
    val groove = Color(0xFF4A070B)
    val thread = Color(0xFFF2E9DA)
    for (i in 0..steps) {
        val a = 2 * PI * i / steps
        val p = add(scale(u, cos(a)), scale(v, sin(a)))
        val q = project(p.first, p.second, p.third)
        val pr = prev
        if (pr != null && q.second > 0 && pr.second > 0) {
            val fade = smooth(q.second).toFloat()
            drawLine(groove.copy(alpha = 0.9f * fade), pr.first, q.first, max(1f, r * 0.018f), StrokeCap.Round)
        }
        prev = q
        if (i % 3 == 0) {
            val tan = add(scale(u, -sin(a)), scale(v, cos(a)))
            for (side in intArrayOf(-1, 1)) {
                val a0 = add(p, scale(n, side * 0.022))
                val a1 = add(add(p, scale(n, side * 0.068)), scale(tan, 0.022))
                val s0 = project(a0.first, a0.second, a0.third)
                val s1 = project(a1.first, a1.second, a1.third)
                if (s0.second > 0.02 && s1.second > 0.02) {
                    val fade = smooth(s0.second).toFloat()
                    drawLine(thread.copy(alpha = 0.92f * fade), s0.first, s1.first, max(1f, r * 0.016f), StrokeCap.Round)
                }
            }
        }
    }

    // THE STAMP: the H and the word, in gold, on the face between the seam's halves.
    val markLat = 0.16
    val markFacing = cos(spin) * cos(markLat)
    if (markFacing > 0.12) {
        val (at, _) = project(0.0, sin(markLat), cos(markLat))
        val k = (r * 0.46f) / MARK_H
        val sx = k * cos(spin)
        val alpha = smooth(markFacing).toFloat()
        withTransform({
            translate(at.x, at.y)
            scale(sx, k, Offset.Zero)
            translate(-(MARK_X0 + MARK_W / 2), -(MARK_Y0 + MARK_H / 2))
        }) {
            for (piece in mark) {
                translate(0.9f, 0.9f) { drawPath(piece, Color(0xFF3A0508).copy(alpha = 0.5f * alpha)) }
                drawPath(
                    piece,
                    Brush.verticalGradient(
                        listOf(Color(0xFFFBE7A6), Color(0xFFE2B85A), Color(0xFFB98A2E)),
                        startY = MARK_Y0,
                        endY = MARK_Y0 + MARK_H,
                    ),
                    alpha = alpha,
                )
            }
        }
        val wordLat = -0.30
        val (wAt, _) = project(0.0, sin(wordLat), cos(wordLat))
        paint.typeface = face
        paint.textSize = r * 0.15f
        paint.letterSpacing = 0.12f
        paint.color = Color(0xFFF0CE7A).copy(alpha = alpha).toArgb()
        drawContext.canvas.nativeCanvas.apply {
            save()
            scale(cos(spin), 1f, wAt.x, wAt.y)
            drawText(word, wAt.x, wAt.y + paint.textSize * 0.35f, paint)
            restore()
        }
    }

    // The edge falls away into shadow; one soft highlight sells the curve.
    drawCircle(
        Brush.radialGradient(
            0f to Color.Transparent,
            0.80f to Color.Transparent,
            1f to Color.Black.copy(alpha = 0.42f),
            center = c,
            radius = r,
        ),
        r,
        c,
    )
    val hl = Offset(c.x - 0.38f * r, c.y - 0.44f * r)
    drawCircle(
        Brush.radialGradient(listOf(Color.White.copy(alpha = 0.42f), Color.Transparent), center = hl, radius = 0.32f * r),
        0.32f * r,
        hl,
    )
}

private typealias V3 = Triple<Double, Double, Double>

private fun add(a: V3, b: V3) = V3(a.first + b.first, a.second + b.second, a.third + b.third)
private fun scale(a: V3, k: Double) = V3(a.first * k, a.second * k, a.third * k)
private fun cross(a: V3, b: V3) = V3(
    a.second * b.third - a.third * b.second,
    a.third * b.first - a.first * b.third,
    a.first * b.second - a.second * b.first,
)
private fun norm(a: V3): V3 = sqrt(a.first * a.first + a.second * a.second + a.third * a.third).let { scale(a, 1 / it) }
private fun norm(x: Double, y: Double, z: Double) = norm(V3(x, y, z))

/** 0 at the ball's edge, 1 a little way in: lines thin out as they turn away. */
private fun smooth(z: Double): Double = (z / 0.25).coerceIn(0.0, 1.0).let { it * it * (3 - 2 * it) }

private const val IN_MS = 620
private const val HOLD_MS = 180L
private const val OUT_MS = 620
