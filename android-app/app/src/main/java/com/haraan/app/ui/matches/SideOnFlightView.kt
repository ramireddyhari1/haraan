package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.withTransform
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ClipTrack
import com.haraan.app.ui.pressable
import kotlinx.coroutines.launch
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin

// ─────────────────────────────────────────────────────────────────────────────
//  THE FLIGHT, SIDE-ON
//
//  The ball's arc as the side-on camera measured it: release to the left, batter to the
//  right, the ground at the bounce. It is the one view of a delivery where a single phone
//  can honestly show HEIGHT — side-on, the picture's vertical IS the ball's height, which
//  from behind the bowler's arm it never is.
//
//  What it is drawn from: the camera phone's own tracker, which followed the ball while
//  filming. The line is a Catmull-Rom curve, which passes exactly THROUGH every sighting
//  (see TrailGeometry for why that is honest where a fitted curve would not be), is never
//  extended past the last one, and never dips below the ground — so a track that shivers
//  still shivers. The ball moves on the sensor's OWN clock: where it covered ground fast
//  it is drawn fast, where it lost pace off the pitch it is drawn slower, and the ghost
//  balls left behind it are evenly spaced in TIME, so their spacing is the ball's speed.
//
//  What it does not claim: distances in metres — the camera is not calibrated — so there
//  are no creases, no stumps and no numbers along the ground, only the shape of the flight.
// ─────────────────────────────────────────────────────────────────────────────

/** The track, laid out for drawing: left-to-right, heights above the bounce. */
internal data class FlightShape(
    /** Distance along the flight, 0..1 of the drawn width. */
    val along: List<Float>,
    /** Height above the ground line, in the same units as [along] before any stretch. */
    val height: List<Float>,
    val bounce: Int?,
    /** How much the height is stretched so a flat arc is still visible. 1 = true scale. */
    val stretch: Float,
    val durationMs: Int,
    /**
     * When each sighting was made, ms from the first, never going backwards. A track with
     * no usable clock gets an even 33 ms per frame so it still plays.
     */
    val times: List<Int> = emptyList(),
)

/**
 * From picture space to a side view.
 *
 * Across and down are put on the same scale with the picture's aspect first, because a
 * 16:9 frame normalised to 0..1 otherwise squashes every arc. A ball travelling right to
 * left is mirrored, so every flight reads the same way; that is a reflection, not an edit.
 */
internal fun flightShapeOf(track: ClipTrack, maxStretch: Float = 4f, targetRise: Float = 0.42f): FlightShape {
    val pts = track.points
    val xs = pts.map { it.x * track.aspect }
    val ys = pts.map { it.y }
    val mirrored = xs.last() < xs.first()
    val xFixed = if (mirrored) xs.map { -it } else xs
    val minX = xFixed.min()
    val span = (xFixed.max() - minX).coerceAtLeast(1e-4f)
    // Y grows downward, so the ground is the LARGEST y: the bounce when there is one,
    // otherwise the lowest point the ball was seen at.
    val ground = track.bounce?.let { ys[it] } ?: ys.max()
    val along = xFixed.map { (it - minX) / span }
    val height = ys.map { (ground - it) / span }
    val peak = height.max().coerceAtLeast(1e-4f)
    // Stretch only as far as it takes for the arc to be readable, in half steps so the
    // caption can state it plainly, and never more than [maxStretch].
    val wanted = targetRise / peak
    val stretch = if (wanted <= 1f) 1f else (kotlin.math.floor(min(wanted, maxStretch) * 2f) / 2f).coerceAtLeast(1f)
    return FlightShape(
        along = along,
        height = height,
        bounce = track.bounce,
        stretch = stretch,
        durationMs = (pts.last().tMs - pts.first().tMs).coerceAtLeast(0),
        times = flightTimesOf(pts.map { it.tMs }),
    )
}

/**
 * The sensor's timestamps, from zero and never backwards.
 *
 * A clock that stood still for the whole track (every stamp equal, or a single point) is no
 * clock at all, so it is replaced by an even frame rate rather than freezing the ball.
 */
internal fun flightTimesOf(raw: List<Int>): List<Int> {
    if (raw.isEmpty()) return emptyList()
    val t0 = raw.first()
    var last = 0
    val out = raw.map { t -> maxOf(last, t - t0).also { last = it } }
    return if (out.last() <= 0) List(raw.size) { it * 33 } else out
}

/**
 * Where along the sightings the ball is at [tMs], as a fractional index.
 *
 * Between two sightings it moves at the steady speed that joins them; a run of sightings
 * that share a stamp is passed over rather than divided by zero.
 */
internal fun indexAtTime(times: List<Int>, tMs: Float): Float {
    val n = times.size
    if (n <= 1) return 0f
    if (tMs <= times.first()) return 0f
    if (tMs >= times.last()) return (n - 1).toFloat()
    var lo = 0
    var hi = n - 1
    while (hi - lo > 1) {
        val mid = (lo + hi) ushr 1
        if (times[mid] <= tMs) lo = mid else hi = mid
    }
    val dt = (times[hi] - times[lo]).toFloat()
    return if (dt <= 0f) hi.toFloat() else lo + (tMs - times[lo]) / dt
}

private val Sky = Color(0xFF0B1424)
private val SkyLow = Color(0xFF12213A)
private val Turf = Color(0xFF12301F)
private val Strip = Color(0xFFBFA878)

// The ribbon cools from a hot coral at the ball to a deep crimson where it left the hand,
// so the newest part of the flight is always the brightest thing on screen.
private val RibbonHot = Color(0xFFFF5A4E)
private val RibbonCool = Color(0xFF8E1330)
private val RibbonGlow = Color(0xFFFF3B3B)
private val RibbonCore = Color(0xFFFFE1DC)

private val Leather = Color(0xFFC8102E)
private val LeatherDark = Color(0xFF6B0716)
private val Seam = Color(0xFFF6EBD9)

/** Curve samples between two sightings. Enough to be smooth at any width a phone draws. */
private const val SAMPLES_PER_SPAN = 10

/** One ghost ball every this many ms of real flight — their spacing IS the ball's speed. */
private const val GHOST_EVERY_MS = 40

@Composable
fun SideOnFlightView(track: ClipTrack, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val shape = remember(track) { flightShapeOf(track) }
    var generation by remember { mutableIntStateOf(0) }
    // The flight's own clock, in real ms since the first sighting.
    val clock = remember(track, generation) { Animatable(0f) }
    val ripple = remember(track, generation) { Animatable(0f) }
    val flightMs = shape.times.lastOrNull() ?: 0

    LaunchedEffect(track, generation) {
        // Real time, slowed: the flight took [flightMs]; replayed at about a third of that
        // speed, within limits, so a quick delivery is still watchable and a slow one does
        // not drag. One factor for the whole flight, so relative pace is never edited.
        val playMs = (flightMs * 3).coerceIn(1100, 2600)
        val slow = playMs / flightMs.coerceAtLeast(1).toFloat()
        val bounceT = shape.bounce?.let { shape.times.getOrNull(it) }?.toFloat()
        if (bounceT != null && bounceT > 0f && bounceT < flightMs) {
            clock.animateTo(bounceT, tween((bounceT * slow).toInt(), easing = LinearEasing))
            // The pitch, felt as the ball meets it.
            launch { cricketThud(context, Thud.TICK) }
            launch { ripple.snapTo(0f); ripple.animateTo(1f, tween(760)) }
            clock.animateTo(flightMs.toFloat(), tween(((flightMs - bounceT) * slow).toInt(), easing = LinearEasing))
        } else {
            clock.animateTo(flightMs.toFloat(), tween(playMs, easing = LinearEasing))
        }
    }

    Column(modifier.fillMaxWidth()) {
        Text(
            "FLIGHT, SIDE-ON",
            color = Color.White.copy(alpha = 0.5f),
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(10.dp))
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(190.dp)
                .clip(RoundedCornerShape(12.dp))
                .pressable(onClick = { generation++ }),
        ) {
            val w = size.width
            val h = size.height
            val groundY = h * 0.78f
            val top = h * 0.06f
            val left = w * 0.07f
            val right = w * 0.93f
            // What "high" means for the shadow: the height of the drawing's sky.
            val rise = groundY - top

            // Sky and ground, and a strip seen edge-on: a sandy band with a lit top edge.
            drawRect(Brush.verticalGradient(listOf(Sky, SkyLow), endY = groundY))
            drawRect(Turf, topLeft = Offset(0f, groundY), size = Size(w, h - groundY))
            drawRect(
                Brush.verticalGradient(
                    listOf(Strip.copy(alpha = 0.55f), Strip.copy(alpha = 0.18f)),
                    startY = groundY,
                    endY = groundY + 14.dp.toPx(),
                ),
                topLeft = Offset(left - 10.dp.toPx(), groundY),
                size = Size(right - left + 20.dp.toPx(), 14.dp.toPx()),
            )
            drawLine(Strip.copy(alpha = 0.8f), Offset(left - 10.dp.toPx(), groundY), Offset(right + 10.dp.toPx(), groundY), 1.5.dp.toPx())

            val n = shape.along.size
            val pts = List(n) { i ->
                val x = left + shape.along[i] * (right - left)
                // Heights are in units of the flight's own length, then stretched.
                val y = groundY - shape.height[i] * shape.stretch * (right - left)
                Offset(x, y.coerceIn(top, groundY))
            }

            /** The curve at a fractional sighting index: through every sighting, never underground. */
            fun curveAt(param: Float): Offset {
                if (n == 1) return pts[0]
                val p = param.coerceIn(0f, (n - 1).toFloat())
                val i = p.toInt().coerceAtMost(n - 2)
                val t = p - i
                val p0 = pts[(i - 1).coerceAtLeast(0)]
                val p1 = pts[i]
                val p2 = pts[i + 1]
                val p3 = pts[(i + 2).coerceAtMost(n - 1)]
                return Offset(
                    catmullRom(p0.x, p1.x, p2.x, p3.x, t),
                    catmullRom(p0.y, p1.y, p2.y, p3.y, t).coerceIn(top, groundY),
                )
            }

            val now = clock.value
            val head = indexAtTime(shape.times, now)

            // The drawn part of the curve, sampled densely, ending exactly on the ball.
            val params = ArrayList<Float>()
            run {
                val step = 1f / SAMPLES_PER_SPAN
                var p = 0f
                while (p < head) { params.add(p); p += step }
                params.add(head)
            }
            val samples = params.map(::curveAt)
            val ball = samples.last()

            // ── The shadow of the flight on the ground, the length the ball has travelled.
            if (samples.size > 1) {
                drawLine(
                    Color.Black.copy(alpha = 0.32f),
                    Offset(samples.first().x, groundY + 1.dp.toPx()),
                    Offset(ball.x, groundY + 1.dp.toPx()),
                    strokeWidth = 2.dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 4.dp.toPx())),
                )
            }

            // ── The ribbon. Glows first, one Path each so the soft layers never bead where
            // segments overlap; then the body, segment by segment, opaque so it cannot bead
            // either, cooling and thinning towards the release; then a hot core on top.
            if (samples.size > 1) {
                val whole = Path().apply {
                    moveTo(samples[0].x, samples[0].y)
                    for (k in 1 until samples.size) lineTo(samples[k].x, samples[k].y)
                }
                drawPath(whole, RibbonGlow.copy(alpha = 0.10f), style = Stroke(16.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
                drawPath(whole, RibbonGlow.copy(alpha = 0.16f), style = Stroke(8.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))

                // The last few frames burn hotter: the part of the flight that just happened.
                val fresh = Path()
                var started = false
                for (k in samples.indices) {
                    if (params[k] < head - 3f) continue
                    if (!started) { fresh.moveTo(samples[k].x, samples[k].y); started = true } else fresh.lineTo(samples[k].x, samples[k].y)
                }
                drawPath(fresh, RibbonGlow.copy(alpha = 0.22f), style = Stroke(14.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))

                val thin = 1.8.dp.toPx()
                val thick = 4.6.dp.toPx()
                val span = head.coerceAtLeast(1e-3f)
                for (k in 1 until samples.size) {
                    val u = (params[k] / span).coerceIn(0f, 1f)
                    val eased = u * u * (3f - 2f * u)
                    drawLine(
                        lerp(RibbonCool, RibbonHot, eased),
                        samples[k - 1],
                        samples[k],
                        strokeWidth = thin + (thick - thin) * eased,
                        cap = StrokeCap.Round,
                    )
                }

                val core = Path()
                started = false
                for (k in samples.indices) {
                    if (params[k] < head * 0.4f) continue
                    if (!started) { core.moveTo(samples[k].x, samples[k].y); started = true } else core.lineTo(samples[k].x, samples[k].y)
                }
                drawPath(core, RibbonCore.copy(alpha = 0.6f), style = Stroke(1.2.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
            }

            // ── Ghost balls, one per [GHOST_EVERY_MS] of REAL flight. Wide apart where the
            // ball was quick, bunched where it lost pace off the pitch.
            var gt = GHOST_EVERY_MS.toFloat()
            while (gt < now - GHOST_EVERY_MS * 0.5f) {
                val g = curveAt(indexAtTime(shape.times, gt))
                drawCircle(Color.White.copy(alpha = 0.10f), radius = 4.5.dp.toPx(), center = g)
                drawCircle(Color.White.copy(alpha = 0.28f), radius = 4.5.dp.toPx(), center = g, style = Stroke(0.8.dp.toPx()))
                gt += GHOST_EVERY_MS
            }

            // Where the ball was first seen — "first seen", not "release": the tracker may
            // have picked it up after it left the hand.
            drawCircle(Color.White.copy(alpha = 0.7f), radius = 3.dp.toPx(), center = pts[0])

            // ── The bounce, once the ball has reached it: a mark on the ground, a ring
            // spreading out, and a puff of dust thrown up and falling back.
            shape.bounce?.let { b ->
                if (head >= b) {
                    val o = Offset(curveAt(b.toFloat()).x, groundY)
                    drawOval(
                        Color.White.copy(alpha = 0.85f),
                        topLeft = Offset(o.x - 6.dp.toPx(), o.y - 2.dp.toPx()),
                        size = Size(12.dp.toPx(), 4.dp.toPx()),
                    )
                    val r = ripple.value
                    if (r in 0.001f..0.999f) {
                        val rw = 10.dp.toPx() + r * 34.dp.toPx()
                        drawOval(
                            Color.White.copy(alpha = (1f - r) * 0.7f),
                            topLeft = Offset(o.x - rw, o.y - rw * 0.22f),
                            size = Size(rw * 2, rw * 0.44f),
                            style = Stroke(1.5.dp.toPx()),
                        )
                        drawDust(o, r)
                    }
                }
            }

            // ── The ball's shadow. It slides along the ground under the ball and tightens
            // as the ball comes down — that is what makes a line read as a ball in the air
            // rather than a graph.
            val air = ((groundY - ball.y) / rise).coerceIn(0f, 1f)
            val shadowW = 7.dp.toPx() + air * 9.dp.toPx()
            drawOval(
                Color.Black.copy(alpha = 0.55f - 0.35f * air),
                topLeft = Offset(ball.x - shadowW, groundY - 1.5.dp.toPx()),
                size = Size(shadowW * 2, 3.5.dp.toPx()),
            )

            // ── The ball itself: leather, lit from above, stretched a little along its line
            // of flight by its speed, squashed for an instant against the pitch, and turning
            // over as it goes.
            val ahead = curveAt(head + 0.35f)
            val behind = curveAt(head - 0.35f)
            val dir = atan2(ahead.y - behind.y, ahead.x - behind.x)
            val speed = speedAt(shape.times, pts, head)
            val topSpeed = topSpeed(shape.times, pts).coerceAtLeast(1e-3f)
            val stretch = 1f + 0.35f * (speed / topSpeed).coerceIn(0f, 1f)
            val squash = shape.bounce?.let { b ->
                val tb = shape.times.getOrNull(b)?.toFloat() ?: return@let 0f
                (1f - abs(now - tb) / 45f).coerceAtLeast(0f) * 0.32f
            } ?: 0f
            val radius = 6.dp.toPx()
            val travelled = samples.zipWithNext { a, c -> (c - a).getDistance() }.sum()
            val spinDeg = (travelled / radius) * (180f / PI.toFloat()) * 0.7f

            drawCircle(RibbonGlow.copy(alpha = 0.28f), radius = radius * 2.2f, center = ball)
            withTransform({
                // Squash is against the ground, so it is applied in the ground's frame…
                scale(1f + squash * 0.7f, 1f - squash, pivot = Offset(ball.x, ball.y + radius))
                // …and the speed stretch along the ball's own line.
                rotate(dir * 180f / PI.toFloat(), pivot = ball)
            }) {
                val rx = radius * stretch
                drawOval(
                    Brush.radialGradient(
                        listOf(Color(0xFFFF6B6B), Leather, LeatherDark),
                        center = Offset(ball.x - rx * 0.3f, ball.y - radius * 0.35f),
                        radius = radius * 1.4f,
                    ),
                    topLeft = Offset(ball.x - rx, ball.y - radius),
                    size = Size(rx * 2, radius * 2),
                )
                // The seam, turning over. A narrow arc reads as a seam seen at an angle.
                rotate(spinDeg, pivot = ball) {
                    drawArc(
                        Seam.copy(alpha = 0.9f),
                        startAngle = -70f,
                        sweepAngle = 140f,
                        useCenter = false,
                        topLeft = Offset(ball.x - radius * 0.45f, ball.y - radius * 0.85f),
                        size = Size(radius * 0.9f, radius * 1.7f),
                        style = Stroke(1.1.dp.toPx(), cap = StrokeCap.Round),
                    )
                }
                drawOval(
                    Color.White.copy(alpha = 0.55f),
                    topLeft = Offset(ball.x - rx, ball.y - radius),
                    size = Size(rx * 2, radius * 2),
                    style = Stroke(0.9.dp.toPx()),
                )
            }
            // A pin of specular light, which is what makes it read as round.
            drawCircle(Color.White.copy(alpha = 0.8f), radius = 1.4.dp.toPx(), center = Offset(ball.x - radius * 0.35f, ball.y - radius * 0.4f))
        }
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                buildString {
                    append("Seen in ${shape.along.size} frames")
                    if (shape.durationMs > 0) append(" over %.1f s".format(shape.durationMs / 1000f))
                    append(if (shape.bounce != null) " · bounce found" else " · no bounce seen")
                },
                color = Color.White.copy(alpha = 0.75f),
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Medium,
                modifier = Modifier.weight(1f),
            )
            Row(
                Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color.White.copy(alpha = 0.1f))
                    .pressable(onClick = { generation++ })
                    .padding(horizontal = 12.dp, vertical = 7.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Refresh, null, tint = Color.White, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(5.dp))
                Text("Replay", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(
            buildString {
                append("Measured by the side-on camera as it filmed. ")
                if (shape.stretch > 1f) {
                    append("Height drawn ×${if (shape.stretch % 1f == 0f) shape.stretch.toInt() else shape.stretch} so the arc is visible. ")
                }
                append("Ghost balls are ${GHOST_EVERY_MS} ms apart. Not in metres — the camera isn't calibrated.")
            },
            color = Color.White.copy(alpha = 0.42f),
            fontSize = 11.5.sp,
            lineHeight = 16.sp,
        )
    }
}

/** The standard uniform Catmull-Rom basis, which passes through [p1] and [p2]. */
private fun catmullRom(p0: Float, p1: Float, p2: Float, p3: Float, t: Float): Float {
    val t2 = t * t
    val t3 = t2 * t
    return 0.5f * (
        (2f * p1) +
            (-p0 + p2) * t +
            (2f * p0 - 5f * p1 + 4f * p2 - p3) * t2 +
            (-p0 + 3f * p1 - 3f * p2 + p3) * t3
        )
}

/** Drawn pixels per real ms between the two sightings either side of [head]. */
private fun speedAt(times: List<Int>, pts: List<Offset>, head: Float): Float {
    if (pts.size < 2) return 0f
    val i = head.toInt().coerceIn(0, pts.size - 2)
    val dt = (times[i + 1] - times[i]).coerceAtLeast(1)
    return (pts[i + 1] - pts[i]).getDistance() / dt
}

private fun topSpeed(times: List<Int>, pts: List<Offset>): Float =
    (0 until (pts.size - 1).coerceAtLeast(0)).maxOfOrNull { speedAt(times, pts, it.toFloat()) } ?: 0f

/**
 * A puff of dust off the pitch: grains thrown up and forward, falling back under their own
 * weight, fading as they go. Fixed angles, so every replay throws the same puff.
 */
private fun DrawScope.drawDust(o: Offset, r: Float) {
    val grains = 9
    val reach = 26.dp.toPx()
    val fall = 30.dp.toPx()
    for (g in 0 until grains) {
        // Fanned upward, biased forward — the ball carries on to the right.
        val a = (-160f + g * (130f / (grains - 1))) * (PI.toFloat() / 180f)
        val v = reach * (0.55f + 0.45f * ((g * 37) % 10) / 10f)
        val x = o.x + cos(a) * v * r
        val y = o.y + sin(a) * v * r + fall * r * r
        if (y > o.y + 2.dp.toPx()) continue
        drawCircle(
            Strip.copy(alpha = (1f - r) * 0.75f),
            radius = (1.6.dp.toPx() * (1f - r * 0.5f)).coerceAtLeast(0.5f),
            center = Offset(x, y),
        )
    }
    // A low haze where it landed.
    drawOval(
        Strip.copy(alpha = (1f - r) * 0.22f),
        topLeft = Offset(o.x - (8.dp.toPx() + r * 22.dp.toPx()), o.y - (3.dp.toPx() + r * 7.dp.toPx())),
        size = Size(2 * (8.dp.toPx() + r * 22.dp.toPx()), 2 * (3.dp.toPx() + r * 7.dp.toPx())),
    )
}
