package com.haraan.app.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.Feel
import kotlinx.coroutines.delay
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.roundToInt
import kotlin.math.sin

/**
 * Pull-to-refresh with the Haraan H and a ball.
 *
 * Pulling brings the three pieces of the H together — they start apart and meet as the
 * pull arms (one tick when they lock). A ball hangs on a thread above it and turns with
 * the finger. Let go and the ball drops onto the H, then hops stem-top to stem-top while
 * the data loads; the stem it lands on gives a little. When the load ends it rests and
 * one true line says what happened.
 *
 * The H is the real three-piece mark (the launcher's traced path), never a redrawn
 * glyph. The ball follows the sport on screen. Drawn flat: base colour, one shade
 * crescent, one highlight.
 */
enum class PullBall { Cricket, Football, Basketball, Tennis, Pickleball, Volleyball, Shuttle }

/** The ball for a sport key / filter label. Anything without its own ball gets cricket's. */
fun pullBallFor(sport: String?): PullBall {
    val s = sport.orEmpty().lowercase()
    return when {
        "football" in s || "futsal" in s || "soccer" in s -> PullBall.Football
        "basket" in s -> PullBall.Basketball
        "badminton" in s -> PullBall.Shuttle
        "pickle" in s -> PullBall.Pickleball
        "tennis" in s || "padel" in s -> PullBall.Tennis
        "volley" in s -> PullBall.Volleyball
        else -> PullBall.Cricket
    }
}

private val Blue = Color(0xFF2563EB)
private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Warn = Color(0xFFB45309)

/** How far the content drops while the indicator is in play. */
private val Hold = 112.dp
/** Height the H is drawn at. */
private val MarkHeight = 30.dp
private val BallRadius = 7.dp
private val HopHeight = 20.dp

// The launcher monogram (ic_launcher_monochrome.xml, 108 viewport), split into its three
// pieces. Bounds: x 35.28–72.72, y 32.50–75.50.
private const val MARK_X0 = 35.28f
private const val MARK_Y0 = 32.50f
private const val MARK_W = 37.44f
private const val MARK_H = 43.0f
/** Right stem top + curved crossbar + left stem bottom. */
private const val PIECE_STROKE = "M63.77,32.52L63.74,32.68L63.74,33.26L63.75,42.09L63.66,43.53L63.56,44.08L63.34,44.91L63.16,45.42L62.94,45.95L62.73,46.37L62.30,47.05L61.88,47.56L61.47,47.98L61.09,48.30L60.64,48.62L60.30,48.84L59.78,49.12L59.33,49.31L58.80,49.50L58.21,49.67L57.69,49.79L56.99,49.89L56.21,49.95L51.66,49.96L50.46,50.03L49.35,50.14L48.63,50.25L47.55,50.48L46.16,50.88L45.26,51.21L44.01,51.75L42.99,52.28L41.96,52.94L41.20,53.49L40.43,54.13L39.44,55.07L39.03,55.52L38.59,56.04L37.87,57.03L37.33,57.88L37.00,58.46L36.58,59.35L36.26,60.10L35.94,61.01L35.62,62.14L35.58,62.39L35.51,62.64L35.35,63.63L35.29,63.83L35.28,75.38L35.30,75.49L44.07,75.50L44.18,75.48L44.20,74.87L44.18,65.49L44.25,64.45L44.35,63.80L44.54,63.00L44.71,62.45L45.02,61.72L45.35,61.09L45.79,60.45L46.12,60.06L46.49,59.71L47.00,59.29L47.32,59.07L47.69,58.85L48.09,58.64L48.57,58.42L49.56,58.10L50.59,57.87L51.37,57.78L51.77,57.75L56.21,57.74L57.82,57.62L58.69,57.51L59.84,57.29L61.33,56.87L62.53,56.45L63.76,55.91L64.77,55.37L65.30,55.05L65.94,54.62L66.79,53.97L67.66,53.21L68.62,52.24L69.38,51.32L70.13,50.30L70.67,49.42L71.09,48.59L71.42,47.85L71.84,46.77L72.06,46.08L72.39,44.86L72.42,44.60L72.49,44.35L72.59,43.75L72.65,43.24L72.70,43.04L72.72,42.57L72.71,32.52L72.60,32.50L63.91,32.50L63.77,32.52Z"
/** Detached bottom-right stem. */
private const val PIECE_RIGHT = "M72.68,50.03L72.62,50.09L72.48,50.46L72.37,50.67L71.83,51.63L71.50,52.15L70.76,53.18L69.79,54.30L69.05,55.03L68.22,55.77L67.69,56.20L66.66,56.94L65.43,57.71L64.59,58.13L64.09,58.35L63.89,58.42L63.77,58.49L63.72,58.55L63.71,75.43L63.72,75.47L63.75,75.49L72.63,75.50L72.71,75.48L72.72,50.19L72.71,50.06L72.68,50.03Z"
/** Detached top-left stem. */
private const val PIECE_LEFT = "M35.29,32.51L35.28,57.09L35.29,57.22L35.31,57.25L35.38,57.19L35.53,56.80L35.96,55.98L36.50,55.10L37.25,54.08L37.67,53.57L38.55,52.66L39.51,51.80L39.93,51.46L40.49,51.04L41.29,50.50L42.03,50.07L42.45,49.84L43.34,49.42L44.11,49.12L44.20,49.03L44.22,48.91L44.21,33.42L44.20,32.65L44.18,32.53L44.12,32.50L35.29,32.51Z"
/** Stem-top centres (mark units) — where the ball lands. */
private const val LEFT_TOP_X = 39.74f
private const val RIGHT_TOP_X = 68.23f

private fun parse(d: String): Path = PathParser().parsePathString(d).toPath()

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HaraanPullRefreshBox(
    isRefreshing: Boolean,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier,
    /** Which ball hops on the H. */
    ball: PullBall = PullBall.Cricket,
    /** True when the last refresh failed (old content kept); the result line says so. */
    lastFailed: Boolean = false,
    content: @Composable BoxScope.() -> Unit,
) {
    val state = rememberPullToRefreshState()
    val view = LocalView.current
    val density = LocalDensity.current
    val holdPx = with(density) { Hold.toPx() }
    val pieces = remember { Triple(parse(PIECE_STROKE), parse(PIECE_LEFT), parse(PIECE_RIGHT)) }

    // After a load the ball rests a moment so the result line can be read.
    var settling by remember { mutableStateOf(false) }
    var wasRefreshing by remember { mutableStateOf(false) }
    LaunchedEffect(isRefreshing) {
        if (isRefreshing) {
            wasRefreshing = true
        } else if (wasRefreshing) {
            wasRefreshing = false
            settling = true
            view.performHapticFeedback(if (lastFailed) Feel.REMOVE else Feel.TICK)
            delay(1100)
            settling = false
        }
    }

    val armed = state.distanceFraction >= 1f
    // One tick the moment the H locks together — letting go now refreshes.
    LaunchedEffect(armed) { if (armed && !isRefreshing && !settling) view.performHapticFeedback(Feel.TICK) }

    val inPlay = isRefreshing || settling
    val held by animateFloatAsState(
        targetValue = if (inPlay) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.82f, stiffness = Spring.StiffnessMediumLow),
        label = "held",
    )
    val threadAlpha by animateFloatAsState(if (inPlay) 0f else 1f, tween(160), label = "thread")
    // Let go: the ball falls the last stretch of thread onto the left stem.
    val landed by animateFloatAsState(
        targetValue = if (inPlay) 1f else 0f,
        animationSpec = if (inPlay) tween(260, easing = FastOutSlowInEasing) else tween(0),
        label = "landed",
    )

    // One beat = there and back (left → right → left). Starts from the left stem once the
    // ball has landed, so the first hop never jumps in mid-air; stops when loading ends.
    val hop = remember { Animatable(0f) }
    var hopping by remember { mutableStateOf(false) }
    LaunchedEffect(isRefreshing) {
        if (isRefreshing) {
            delay(260)
            hopping = true
            while (true) {
                hop.snapTo(0f)
                hop.animateTo(1f, tween(1300, easing = LinearEasing))
            }
        } else {
            hopping = false
            hop.snapTo(0f)
        }
    }
    val beat = hop.value

    PullToRefreshBox(
        isRefreshing = isRefreshing,
        onRefresh = onRefresh,
        state = state,
        modifier = modifier,
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
                    .height(Hold)
                    .offset { IntOffset(0, ((drop - 1f) * holdPx).roundToInt()) },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val markH = MarkHeight.toPx()
                    val k = markH / MARK_H                  // mark units → px
                    val markW = MARK_W * k
                    val left = size.width / 2f - markW / 2f
                    val markTop = size.height - 32.dp.toPx() - markH
                    fun mx(x: Float) = left + (x - MARK_X0) * k
                    val r = BallRadius.toPx()
                    val restY = markTop - r - 1.dp.toPx()
                    val loading = isRefreshing && !settling && hopping

                    // ── Assembly: apart → together as the pull arms. Stays together once in play.
                    // Eased IN so the pieces stay visibly apart until the pull nears its arm point.
                    val join = if (inPlay) 1f else easeIn(pull.coerceAtMost(1f))
                    val apart = (1f - join) * 16.dp.toPx()
                    val alpha = (0.25f + 0.75f * join).coerceIn(0f, 1f)

                    // ── Where the ball is.
                    var bx = mx(LEFT_TOP_X)
                    var by: Float
                    var spinDeg: Float
                    var squash = 0f
                    var landingLeft = false
                    var landingRight = false
                    if (loading) {
                        val t = beat * 2f
                        val leg = if (t < 1f) t else t - 1f
                        val fromX = if (t < 1f) mx(LEFT_TOP_X) else mx(RIGHT_TOP_X)
                        val toX = if (t < 1f) mx(RIGHT_TOP_X) else mx(LEFT_TOP_X)
                        bx = fromX + (toX - fromX) * leg
                        val h = sin(leg * PI).toFloat()
                        by = restY - h * HopHeight.toPx()
                        spinDeg = (if (t < 1f) 1f else -1f) * leg * 300f
                        // Contact at each end of a leg: squash onto the stem, which gives a little.
                        val contact = minOf(leg, 1f - leg)
                        if (contact < 0.08f) {
                            squash = (0.08f - contact) / 0.08f
                            val nearLeft = (leg < 0.5f) == (t < 1f)
                            landingLeft = nearLeft
                            landingRight = !nearLeft
                        }
                    } else if (inPlay) {
                        // Just let go (falls the last of the thread) or resting after the load.
                        by = restY - 10.dp.toPx() * (1f - landed)
                        spinDeg = 0f
                    } else {
                        // Hanging on the thread just above the stem, lowering onto it as the pull
                        // grows. Kept close so it is on screen from the first few dp of pull.
                        val hang = 8.dp.toPx() * (1f - pull.coerceAtMost(1f))
                        by = restY - 10.dp.toPx() - hang
                        spinDeg = pull * 300f
                    }

                    val give = 1.5.dp.toPx()
                    // Shadow of the ball on the stem top it is over.
                    if (inPlay) {
                        val lift = ((restY - by) / HopHeight.toPx()).coerceIn(0f, 1f)
                        val sw = r * (1.1f - 0.5f * lift)
                        drawOval(
                            Ink.copy(alpha = 0.14f * (1f - 0.7f * lift) * held),
                            topLeft = Offset(bx - sw, markTop - r * 0.12f),
                            size = Size(sw * 2, r * 0.26f),
                        )
                    }

                    // ── The H, three pieces.
                    val (stroke, stemL, stemR) = pieces
                    val blue = Blue.copy(alpha = alpha)
                    fun piece(p: Path, dx: Float, dy: Float) {
                        translate(left - MARK_X0 * k + dx, markTop - MARK_Y0 * k + dy) {
                            scale(k, k, pivot = Offset.Zero) { drawPath(p, blue) }
                        }
                    }
                    // The stroke carries the right stem top, so it gives on a right landing.
                    // Pieces drift out sideways and UP (never down into the label).
                    piece(stroke, 0f, -apart * 0.35f + if (landingRight) give * squash else 0f)
                    piece(stemL, -apart, -apart * 0.8f + if (landingLeft) give * squash else 0f)
                    piece(stemR, apart, apart * 0.15f)

                    // ── Thread while pulling.
                    if (threadAlpha > 0.01f) {
                        drawLine(
                            Ink.copy(alpha = 0.30f * threadAlpha),
                            Offset(bx, -size.height), Offset(bx, by - r), 1.dp.toPx(),
                        )
                    }

                    // ── The ball. Squash anchored at its contact point.
                    val grow = if (inPlay) 1f else 0.7f + 0.3f * pull.coerceAtMost(1f)
                    val sx = grow * (1f + 0.18f * squash)
                    val sy = grow * (1f - 0.18f * squash)
                    val c = Offset(bx, by)
                    scale(sx, sy, pivot = Offset(bx, if (squash > 0f) by + r else by)) {
                        drawPullBall(ball, c, r, spinDeg)
                    }
                }
                val label = when {
                    settling && lastFailed -> "Couldn't refresh · showing what was here"
                    settling -> "Updated just now"
                    isRefreshing -> "Refreshing"
                    armed -> "Let go to refresh"
                    else -> "Pull to refresh"
                }
                Text(
                    label,
                    color = if (settling && lastFailed) Warn else Muted,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .offset(y = (-6).dp)
                        // Fades in once there's room under the H for it.
                        .graphicsLayer { alpha = ((drop - 0.35f) / 0.35f).coerceIn(0f, 1f) },
                )
            }
        }
    }
}

private fun easeIn(x: Float): Float = x * x

// ── Balls: base colour, a shade crescent lower-right, a small fixed highlight. ──

private fun DrawScope.drawPullBall(ball: PullBall, c: Offset, r: Float, turn: Float) {
    if (ball == PullBall.Shuttle) {
        rotate(turn * 0.05f, pivot = c) { shuttle(c, r) }
        return
    }
    val (base, shade) = when (ball) {
        PullBall.Cricket -> Color(0xFFB01E23) to Color(0xFF7E1216)
        PullBall.Football -> Color(0xFFFFFFFF) to Color(0xFFD5DBE3)
        PullBall.Basketball -> Color(0xFFE8792B) to Color(0xFFC25A14)
        PullBall.Tennis -> Color(0xFFD4E157) to Color(0xFFAFBE34)
        PullBall.Pickleball -> Color(0xFFF5C542) to Color(0xFFD9A21E)
        PullBall.Volleyball -> Color(0xFFFFFFFF) to Color(0xFFDCE3EC)
        PullBall.Shuttle -> Color.White to Color.White
    }
    val disc = Path().apply { addOval(Rect(c, r)) }
    clipPath(disc) {
        drawCircle(shade, r, c)
        drawCircle(base, r * 0.94f, c + Offset(-r * 0.12f, -r * 0.12f))
        rotate(turn, pivot = c) {
            when (ball) {
                PullBall.Cricket -> cricketSeam(c, r)
                PullBall.Football -> footballPanels(c, r)
                PullBall.Basketball -> basketballLines(c, r)
                PullBall.Tennis -> tennisSeams(c, r)
                PullBall.Pickleball -> pickleHoles(c, r)
                PullBall.Volleyball -> volleyballPanels(c, r)
                PullBall.Shuttle -> Unit
            }
        }
    }
    if (ball == PullBall.Football || ball == PullBall.Volleyball) {
        drawCircle(Color(0xFFCBD5E1), r, c, style = Stroke(1f))
    }
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
        drawPath(p, thread.copy(alpha = 0.9f), style = Stroke(r * 0.08f))
        for (i in 1..5) {
            val t = i / 6f
            val y = c.y - r + 2 * r * t
            val bow = r * 0.11f * (1 - (2 * t - 1) * (2 * t - 1))
            val sx = x + bow - r * 0.04f
            drawLine(thread, Offset(sx, y), Offset(sx + side * r * 0.18f, y - r * 0.08f), r * 0.07f, StrokeCap.Round)
        }
    }
}

private fun DrawScope.footballPanels(c: Offset, r: Float) {
    val ink = Color(0xFF1E293B)
    fun pentagon(center: Offset, size: Float, start: Double): List<Offset> =
        (0 until 5).map { i ->
            val a = start + i * 2 * PI / 5
            center + Offset((cos(a) * size).toFloat(), (sin(a) * size).toFloat())
        }
    fun fill(p: List<Offset>) = drawPath(Path().apply { moveTo(p[0].x, p[0].y); p.drop(1).forEach { lineTo(it.x, it.y) }; close() }, ink)
    val core = pentagon(c, r * 0.34f, -PI / 2)
    fill(core)
    for ((i, v) in core.withIndex()) {
        val a = -PI / 2 + i * 2 * PI / 5
        val outer = c + Offset((cos(a) * r * 1.02f).toFloat(), (sin(a) * r * 1.02f).toFloat())
        val edge = c + Offset((cos(a) * r * 0.72f).toFloat(), (sin(a) * r * 0.72f).toFloat())
        drawLine(ink, v, edge, 1.2f)
        fill(pentagon(outer, r * 0.3f, a + PI))
    }
}

private fun DrawScope.basketballLines(c: Offset, r: Float) {
    val line = Color(0xFF4A2410)
    val w = r * 0.09f
    drawLine(line, Offset(c.x - r, c.y), Offset(c.x + r, c.y), w)
    drawLine(line, Offset(c.x, c.y - r), Offset(c.x, c.y + r), w)
    drawArc(line, -60f, 120f, false, topLeft = Offset(c.x - r * 2.1f, c.y - r), size = Size(r * 2f, r * 2f), style = Stroke(w))
    drawArc(line, 120f, 120f, false, topLeft = Offset(c.x + r * 0.1f, c.y - r), size = Size(r * 2f, r * 2f), style = Stroke(w))
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

private fun DrawScope.volleyballPanels(c: Offset, r: Float) {
    val seam = Color(0xFF2563EB).copy(alpha = 0.75f)
    val w = r * 0.09f
    for (i in 0 until 3) {
        rotate(i * 120f, pivot = c) {
            drawArc(seam, 200f, 110f, false, topLeft = Offset(c.x - r * 0.3f, c.y - r * 1.9f), size = Size(r * 2.2f, r * 2.2f), style = Stroke(w))
        }
    }
}

/** A shuttle cork-down: white feathers, ribs, a blue band, a cork base. */
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
    drawPath(skirt, rib, style = Stroke(1f))
    drawRect(Blue, topLeft = Offset(c.x - r * 0.46f, band - r * 0.06f), size = Size(r * 0.92f, r * 0.2f))
    drawArc(
        Color(0xFFE6CFA1), 0f, 180f, true,
        topLeft = Offset(c.x - r * 0.46f, band + r * 0.14f - r * 0.46f), size = Size(r * 0.92f, r * 0.92f),
    )
}
