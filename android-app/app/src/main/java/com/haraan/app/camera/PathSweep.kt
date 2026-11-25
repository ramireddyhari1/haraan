package com.haraan.app.camera

import android.graphics.BitmapFactory
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.CubicBezierEasing
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
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.vision.ArPath
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlin.math.max

/**
 * THE BALL AGAIN, IN A SECOND AND A HALF — straight after it is read.
 *
 * The full replay (the delivery's footage, then the stadium) ran eight to ten seconds after
 * every ball, and the next one was usually being bowled before it ended. This is the short
 * one that plays on its own (2026-11-20): the picture stops on the moment the ball reached the
 * stumps, the path grows from the hand at the ball's own pace with a glowing ball at its tip, a
 * ring pulses where it pitched (felt as a knock), and the stumps light with the verdict. Then
 * the live picture comes back and the result card lands. The long replay is on Replay.
 *
 * [frames] are the replay ring's upright JPEGs around the flight; with none the path sweeps
 * over the live picture instead.
 */
@Composable
internal fun PathSweep(
    path: ArPath,
    frames: List<Pair<Double, ByteArray>>,
    uprightAspect: Float,
    onDone: () -> Unit,
) {
    val context = LocalContext.current
    val sweep = remember(path) { Animatable(0f) }
    val pulse = remember(path) { Animatable(0f) }
    val glow = remember(path) { Animatable(0f) }
    val fade = remember(path) { Animatable(0f) }
    var still by remember(path) { mutableStateOf<ImageBitmap?>(null) }

    LaunchedEffect(path) {
        // The frame where the ball got to the stumps: the batter, the stumps, where it ended.
        still = withContext(Dispatchers.Default) {
            frames.minByOrNull { kotlin.math.abs(it.first - path.endMs) }?.second?.let { bytes ->
                runCatching { BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap() }.getOrNull()
            }
        }
        launch { fade.animateTo(1f, tween(FADE_MS)) }
        val span = path.endMs - path.startMs
        val pitchedAt = path.bounceMs
            ?.let { ((it - path.startMs) / span).toFloat() }
            ?.takeIf { span > 0 && it > 0.05f && it < 0.98f }
        if (pitchedAt != null) {
            sweep.animateTo(pitchedAt, tween((SWEEP_MS * pitchedAt).toInt(), easing = LinearEasing))
            launch { cricketThud(context, Thud.RUN) }
            launch { pulse.animateTo(1f, tween(PULSE_MS, easing = CubicBezierEasing(0.2f, 0.7f, 0.3f, 1f))) }
            sweep.animateTo(1f, tween((SWEEP_MS * (1f - pitchedAt)).toInt(), easing = LinearEasing))
        } else {
            sweep.animateTo(1f, tween(SWEEP_MS, easing = LinearEasing))
        }
        glow.animateTo(1f, tween(GLOW_MS))
        kotlinx.coroutines.delay(HOLD_MS)
        fade.animateTo(0f, tween(FADE_MS))
        onDone()
    }

    Canvas(Modifier.fillMaxSize().graphicsLayer { alpha = fade.value }) {
        val frame = frameRect(size.width, size.height, uprightAspect)
        // The stopped picture, a little darker so the path reads over it.
        still?.let { img ->
            drawImage(
                img,
                dstOffset = IntOffset(frame.left.toInt(), frame.top.toInt()),
                dstSize = IntSize(frame.width.toInt(), frame.height.toInt()),
            )
            drawRect(Color.Black.copy(alpha = 0.22f), topLeft = frame.topLeft, size = frame.size)
        }
        val upto = path.startMs + (path.endMs - path.startMs) * sweep.value
        drawArPath(path, frame, upto, glow.value)

        fun at(x: Double, y: Double) = Offset(frame.left + x.toFloat() * frame.width, frame.top + y.toFloat() * frame.height)

        // Where it pitched: a ring that opens and fades.
        val b = path.bounce
        if (b != null && pulse.value > 0f && pulse.value < 1f) {
            val p = at(b.x, b.y)
            val r = 6.dp.toPx() + 30.dp.toPx() * pulse.value
            drawCircle(Color.White.copy(alpha = 0.85f * (1f - pulse.value)), r, p, style = Stroke(2.5.dp.toPx()))
            drawCircle(Color.White.copy(alpha = 0.35f * (1f - pulse.value)), r * 0.55f, p)
        }

        // The ball at the tip of the path, while it is travelling.
        if (sweep.value < 1f) {
            val head = headAt(path, upto) ?: return@Canvas
            val c = at(head.first, head.second)
            val r = max(4.dp.toPx(), (head.third * frame.width).toFloat() * 0.9f)
            drawCircle(
                Brush.radialGradient(listOf(Color.White.copy(alpha = 0.55f), Color.Transparent), center = c, radius = r * 3.2f),
                radius = r * 3.2f,
                center = c,
            )
            drawCircle(Color.White, r, c)
        }
    }
}

/** The path's point at [ms] (x, y, tube width), straight between its samples. */
private fun headAt(path: ArPath, ms: Double): Triple<Double, Double, Double>? {
    val pts = path.points
    if (pts.isEmpty()) return null
    if (ms <= pts.first().ms) return pts.first().let { Triple(it.x, it.y, it.width) }
    for (i in 1 until pts.size) {
        val a = pts[i - 1]
        val b = pts[i]
        if (ms <= b.ms) {
            val f = if (b.ms > a.ms) (ms - a.ms) / (b.ms - a.ms) else 1.0
            return Triple(a.x + (b.x - a.x) * f, a.y + (b.y - a.y) * f, a.width + (b.width - a.width) * f)
        }
    }
    return pts.last().let { Triple(it.x, it.y, it.width) }
}

/** The path grows over this long, at the ball's own relative pace. */
private const val SWEEP_MS = 1_250
private const val PULSE_MS = 420
private const val GLOW_MS = 180
private const val HOLD_MS = 250L
private const val FADE_MS = 160
