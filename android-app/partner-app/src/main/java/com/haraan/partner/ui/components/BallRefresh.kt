package com.haraan.partner.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.layout.positionInWindow
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import kotlinx.coroutines.delay
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.roundToInt
import kotlin.math.sin

/**
 * Pull-to-refresh with the venue's own ball on a string.
 *
 * Material's spinner is the same grey ring in every app on the phone. Here the
 * partner pulls down the ball their courts are played with — a leather cricket
 * ball, a shuttle, a football — and it turns under the finger like it's hanging
 * from a thread. When they let go it drops onto a floor line and bounces while the
 * data loads, then sits still while one true line says what happened: "Updated
 * just now", or "Couldn't refresh · showing 6 min old".
 *
 * Drawn flat like the court art on Home (one base colour, one shade crescent, one
 * highlight): no gradients, no glow, no scenery behind it.
 */
enum class RefreshBall { Cricket, Tennis, Pickleball, Shuttle, Football, Plain }

/** The ball for a venue, read off its sports. The first sport listed wins. */
fun refreshBallFor(sports: List<String>): RefreshBall {
    for (sport in sports.map { it.lowercase() }) {
        when {
            "cricket" in sport -> return RefreshBall.Cricket
            "badminton" in sport -> return RefreshBall.Shuttle
            "pickle" in sport -> return RefreshBall.Pickleball
            "tennis" in sport || "padel" in sport -> return RefreshBall.Tennis
            "football" in sport || "futsal" in sport || "soccer" in sport -> return RefreshBall.Football
        }
    }
    return RefreshBall.Plain
}

/** Set once by the console for the venue in focus; every refreshable screen reads it. */
val LocalRefreshBall = staticCompositionLocalOf { RefreshBall.Cricket }

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Warn = Color(0xFFB45309)

/** How far the content drops while the ball is in play. */
private val Hold = 76.dp
private val BallRadius = 12.dp

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BallRefreshBox(
    isRefreshing: Boolean,
    onRefresh: () -> Unit,
    /** When the content on screen was last loaded; null before the first load. */
    updatedAt: Long?,
    /** True when the last pull failed and the old content was kept. */
    lastFailed: Boolean,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    val state = rememberPullToRefreshState()
    val view = LocalView.current
    val ball = LocalRefreshBall.current
    val density = LocalDensity.current
    // Home runs under the status bar; there the ball has to hang below the clock,
    // so the drop grows by however much of the status bar this box sits under.
    val statusTop = WindowInsets.statusBars.getTop(density)
    var boxTop by remember { mutableStateOf(0f) }
    val underBar = (statusTop - boxTop).coerceAtLeast(0f)
    val holdPx = with(density) { Hold.toPx() } + underBar

    // After a load finishes the ball stays down a moment, so the result line can
    // be read, then everything springs back up.
    var settling by remember { mutableStateOf(false) }
    var wasRefreshing by remember { mutableStateOf(false) }
    LaunchedEffect(isRefreshing) {
        if (isRefreshing) {
            wasRefreshing = true
        } else if (wasRefreshing) {
            wasRefreshing = false
            settling = true
            if (lastFailed) Haptics.warn(view) else Haptics.tick(view)
            delay(1100)
            settling = false
        }
    }

    val armed = state.distanceFraction >= 1f
    // One tick at the exact point where letting go would refresh — the thread
    // "catches". Not on every frame, and not again while it's loading.
    LaunchedEffect(armed) { if (armed && !isRefreshing && !settling) Haptics.tick(view) }

    val inPlay = isRefreshing || settling
    val held by animateFloatAsState(
        targetValue = if (inPlay) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.82f, stiffness = Spring.StiffnessMediumLow),
        label = "held",
    )
    val stringAlpha by animateFloatAsState(if (inPlay) 0f else 1f, tween(160), label = "string")

    val bounce = rememberInfiniteTransition(label = "bounce")
    val beat by bounce.animateFloat(
        0f, 1f, infiniteRepeatable(tween(640, easing = LinearEasing), RepeatMode.Restart), label = "beat",
    )
    val spin by bounce.animateFloat(
        0f, 360f, infiniteRepeatable(tween(1500, easing = LinearEasing), RepeatMode.Restart), label = "spin",
    )

    PullToRefreshBox(
        isRefreshing = isRefreshing,
        onRefresh = onRefresh,
        state = state,
        modifier = modifier.onGloballyPositioned { boxTop = it.positionInWindow().y },
        indicator = {},
    ) {
        val pull = state.distanceFraction.coerceIn(0f, 1.5f)
        val drop = maxOf(pull, held)

        Box(
            Modifier
                .fillMaxSize()
                .graphicsLayer { translationY = drop * holdPx },
            content = content,
        )

        if (drop > 0.01f) {
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(with(density) { holdPx.toDp() })
                    .offset { IntOffset(0, ((drop - 1f) * holdPx).roundToInt()) },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val r = BallRadius.toPx()
                    val floorY = size.height - 30.dp.toPx()
                    val restY = floorY - r
                    val loading = isRefreshing && !settling
                    // A thrown-up ball: zero height at the ends of a beat, top in the middle.
                    val h = if (loading) 1f - (2f * beat - 1f).let { it * it } else 0f
                    val cy = restY - h * 18.dp.toPx()
                    val c = Offset(size.width / 2f, cy)
                    val grow = if (inPlay) 1f else 0.72f + 0.28f * pull.coerceAtMost(1f)
                    // Squash only at the moment of contact, stretch just after it.
                    val squash = if (loading && h < 0.1f && ball != RefreshBall.Shuttle) (0.1f - h) / 0.1f else 0f
                    val sx = grow * (1f + 0.16f * squash)
                    val sy = grow * (1f - 0.16f * squash)
                    val turn = when {
                        ball == RefreshBall.Shuttle -> if (loading) sin(beat * 2 * PI).toFloat() * 9f else (pull - 0.5f) * 14f
                        inPlay -> spin
                        else -> pull * 320f
                    }

                    if (inPlay) {
                        // Floor line and the ball's shadow on it, wider when it's down.
                        drawLine(Ink.copy(alpha = 0.10f), Offset(c.x - 26.dp.toPx(), floorY), Offset(c.x + 26.dp.toPx(), floorY), 1.dp.toPx(), StrokeCap.Round)
                        val sw = r * (1.5f - 0.6f * h)
                        drawOval(
                            Ink.copy(alpha = 0.16f * (1f - 0.6f * h) * held),
                            topLeft = Offset(c.x - sw, floorY - r * 0.16f),
                            size = Size(sw * 2, r * 0.32f),
                        )
                    }
                    if (stringAlpha > 0.01f) {
                        val top = if (ball == RefreshBall.Shuttle) cy - r * 1.15f * sy else cy - r * sy
                        drawLine(Ink.copy(alpha = 0.32f * stringAlpha), Offset(c.x, -size.height), Offset(c.x, top), 1.dp.toPx())
                    }
                    // Anchor the squash at the floor so the ball flattens onto it.
                    scale(sx, sy, pivot = Offset(c.x, if (squash > 0f) floorY else cy)) {
                        drawRefreshBall(ball, c, r, turn)
                    }
                }
                val label = when {
                    settling && lastFailed -> "Couldn't refresh · showing ${ago(updatedAt, short = true)}"
                    settling -> "Updated just now"
                    isRefreshing -> "Refreshing"
                    armed -> "Let go to refresh"
                    else -> updatedAt?.let { "Updated ${ago(it)}" } ?: "Pull to refresh"
                }
                Text(
                    label,
                    color = if (settling && lastFailed) Warn else Muted,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .offset(y = (-8).dp)
                        .graphicsLayer { alpha = (drop * 1.4f).coerceAtMost(1f) },
                )
            }
        }
    }
}

private fun ago(at: Long?, short: Boolean = false): String {
    if (at == null) return if (short) "older data" else "a while ago"
    val s = (System.currentTimeMillis() - at) / 1000
    val text = when {
        s < 45 -> return if (short) "data from just now" else "just now"
        s < 3600 -> "${(s / 60).coerceAtLeast(1)} min"
        s < 86_400 -> "${s / 3600} hr"
        else -> "${s / 86_400} d"
    }
    return if (short) "$text old" else "$text ago"
}

// ── The balls. Each is a base colour, a shade crescent lower-right and a small
// highlight upper-left. Light stays fixed while the surface detail turns. ──

internal fun DrawScope.drawRefreshBall(ball: RefreshBall, c: Offset, r: Float, turn: Float) {
    if (ball == RefreshBall.Shuttle) {
        rotate(turn, pivot = c) { shuttle(c, r) }
        return
    }
    val (base, shade) = when (ball) {
        RefreshBall.Cricket -> Color(0xFFB01E23) to Color(0xFF7E1216)
        RefreshBall.Tennis -> Color(0xFFD4E157) to Color(0xFFAFBE34)
        RefreshBall.Pickleball -> Color(0xFFF5C542) to Color(0xFFD9A21E)
        RefreshBall.Football -> Color(0xFFFFFFFF) to Color(0xFFD5DBE3)
        else -> Color(0xFF2563EB) to Color(0xFF1D4ED8)
    }
    val disc = Path().apply { addOval(androidx.compose.ui.geometry.Rect(c, r)) }
    clipPath(disc) {
        drawCircle(shade, r, c)
        drawCircle(base, r * 0.94f, c + Offset(-r * 0.12f, -r * 0.12f))
        rotate(turn, pivot = c) {
            when (ball) {
                RefreshBall.Cricket -> cricketSeam(c, r)
                RefreshBall.Tennis -> tennisSeams(c, r)
                RefreshBall.Pickleball -> pickleHoles(c, r)
                RefreshBall.Football -> footballPanels(c, r)
                else -> drawArc(
                    Color.White.copy(alpha = 0.55f), 200f, 140f, false,
                    topLeft = Offset(c.x - r * 1.6f, c.y - r * 0.6f), size = Size(r * 3.2f, r * 2.6f),
                    style = Stroke(r * 0.12f),
                )
            }
        }
    }
    if (ball == RefreshBall.Football) drawCircle(Color(0xFFCBD5E1), r, c, style = Stroke(1f))
    drawOval(
        Color.White.copy(alpha = 0.38f),
        topLeft = Offset(c.x - r * 0.58f, c.y - r * 0.66f),
        size = Size(r * 0.42f, r * 0.26f),
    )
}

private fun DrawScope.cricketSeam(c: Offset, r: Float) {
    val thread = Color(0xFFF3E2C0)
    val gap = r * 0.13f
    for (side in listOf(-1f, 1f)) {
        val x = c.x + side * gap
        val p = Path().apply {
            moveTo(x - r * 0.08f, c.y - r * 1.05f)
            quadraticTo(x + r * 0.22f, c.y, x - r * 0.08f, c.y + r * 1.05f)
        }
        drawPath(p, thread.copy(alpha = 0.9f), style = Stroke(r * 0.07f))
        // Stitches: short ticks leaning off each seam line.
        for (i in 1..6) {
            val t = i / 7f
            val y = c.y - r + 2 * r * t
            val bow = r * 0.22f * (1 - (2 * t - 1) * (2 * t - 1)) * 0.5f
            val sx = x + bow - r * 0.04f
            drawLine(thread, Offset(sx, y), Offset(sx + side * r * 0.16f, y - r * 0.07f), r * 0.06f, StrokeCap.Round)
        }
    }
}

private fun DrawScope.tennisSeams(c: Offset, r: Float) {
    val felt = Color(0xFFF8FAEC)
    val w = r * 0.12f
    drawArc(felt, -60f, 120f, false, topLeft = Offset(c.x - r * 2.15f, c.y - r * 0.95f), size = Size(r * 1.9f, r * 1.9f), style = Stroke(w))
    drawArc(felt, 120f, 120f, false, topLeft = Offset(c.x + r * 0.25f, c.y - r * 0.95f), size = Size(r * 1.9f, r * 1.9f), style = Stroke(w))
}

private fun DrawScope.pickleHoles(c: Offset, r: Float) {
    val hole = Color(0xFFB7851A)
    drawCircle(hole, r * 0.13f, c)
    for (i in 0 until 6) {
        val a = i * PI / 3
        drawCircle(hole, r * 0.12f, c + Offset((cos(a) * r * 0.56f).toFloat(), (sin(a) * r * 0.56f).toFloat()))
    }
}

private fun DrawScope.footballPanels(c: Offset, r: Float) {
    val ink = Color(0xFF1E293B)
    fun pentagon(center: Offset, size: Float, start: Double): List<Offset> =
        (0 until 5).map { i ->
            val a = start + i * 2 * PI / 5
            center + Offset((cos(a) * size).toFloat(), (sin(a) * size).toFloat())
        }
    val core = pentagon(c, r * 0.34f, -PI / 2)
    drawPath(Path().apply { moveTo(core[0].x, core[0].y); core.drop(1).forEach { lineTo(it.x, it.y) }; close() }, ink)
    for ((i, v) in core.withIndex()) {
        val a = -PI / 2 + i * 2 * PI / 5
        val outer = c + Offset((cos(a) * r * 1.02f).toFloat(), (sin(a) * r * 1.02f).toFloat())
        val edge = c + Offset((cos(a) * r * 0.72f).toFloat(), (sin(a) * r * 0.72f).toFloat())
        drawLine(ink, v, edge, 1.2f)
        val p = pentagon(outer, r * 0.3f, a + PI)
        drawPath(Path().apply { moveTo(p[0].x, p[0].y); p.drop(1).forEach { lineTo(it.x, it.y) }; close() }, ink)
    }
}

/** A shuttle hanging skirt-up, cork-down: white feathers, ribs, a blue band, a cork base. */
private fun DrawScope.shuttle(c: Offset, r: Float) {
    val top = c.y - r * 1.15f
    val band = c.y + r * 0.42f
    val skirt = Path().apply {
        moveTo(c.x - r * 0.95f, top)
        lineTo(c.x + r * 0.95f, top)
        lineTo(c.x + r * 0.42f, band)
        lineTo(c.x - r * 0.42f, band)
        close()
    }
    drawPath(skirt, Color.White)
    val rib = Color(0xFFCBD5E1)
    for (i in -2..2) {
        val t = i / 2f
        drawLine(rib, Offset(c.x + t * r * 0.95f, top), Offset(c.x + t * r * 0.42f, band), 1f)
    }
    // The thread ring a third of the way down the feathers.
    val ringY = top + (band - top) * 0.45f
    val ringHalf = r * 0.95f - (r * 0.53f) * 0.45f
    drawLine(Color(0xFF94A3B8), Offset(c.x - ringHalf, ringY), Offset(c.x + ringHalf, ringY), 1.2f)
    drawPath(skirt, rib, style = Stroke(1f))
    drawRect(Color(0xFF2563EB), topLeft = Offset(c.x - r * 0.46f, band - r * 0.06f), size = Size(r * 0.92f, r * 0.2f))
    drawArc(
        Color(0xFFE6CFA1), 0f, 180f, true,
        topLeft = Offset(c.x - r * 0.46f, band + r * 0.14f - r * 0.46f), size = Size(r * 0.92f, r * 0.92f),
    )
    drawArc(
        Color(0xFFC9AE7A), 20f, 80f, true,
        topLeft = Offset(c.x - r * 0.46f, band + r * 0.14f - r * 0.46f), size = Size(r * 0.92f, r * 0.92f),
    )
}
