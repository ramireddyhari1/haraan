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
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ClipTrack
import com.haraan.app.ui.pressable
import kotlinx.coroutines.launch
import kotlin.math.min

// ─────────────────────────────────────────────────────────────────────────────
//  THE FLIGHT, SIDE-ON
//
//  The ball's arc as the side-on camera measured it: release to the left, batter to the
//  right, the ground at the bounce. It is the one view of a delivery where a single phone
//  can honestly show HEIGHT — side-on, the picture's vertical IS the ball's height, which
//  from behind the bowler's arm it never is.
//
//  What it is drawn from: the camera phone's own tracker, which followed the ball while
//  filming. Nothing is fitted, smoothed or extended past the last sighting, so a track
//  that shivers is drawn shivering. What it does not claim: distances in metres — the
//  camera is not calibrated — so there are no creases, no stumps and no numbers along the
//  ground, only the shape of the flight.
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
    )
}

private val Sky = Color(0xFF0B1424)
private val SkyLow = Color(0xFF12213A)
private val Turf = Color(0xFF12301F)
private val Strip = Color(0xFFBFA878)
private val PathRed = Color(0xFFEF4444)

@Composable
fun SideOnFlightView(track: ClipTrack, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val shape = remember(track) { flightShapeOf(track) }
    var generation by remember { mutableIntStateOf(0) }
    val progress = remember(track, generation) { Animatable(0f) }
    val ripple = remember(track, generation) { Animatable(0f) }

    LaunchedEffect(track, generation) {
        val n = shape.along.size
        val bounceAt = shape.bounce?.let { it.toFloat() / (n - 1).coerceAtLeast(1) }
        // Real time, slowed: the flight took [durationMs]; replayed at about a third of that
        // speed, within limits, so a quick delivery is still watchable and a slow one does
        // not drag.
        val playMs = (shape.durationMs * 3).coerceIn(1100, 2600)
        if (bounceAt != null) {
            // Steady speed — a ball does not slow into the bounce — with the tick landing
            // exactly as it meets the ground.
            progress.animateTo(bounceAt, tween((playMs * bounceAt).toInt(), easing = LinearEasing))
            launch { cricketThud(context, Thud.TICK) }
            launch { ripple.snapTo(0f); ripple.animateTo(1f, tween(700)) }
            progress.animateTo(1f, tween((playMs * (1f - bounceAt)).toInt(), easing = LinearEasing))
        } else {
            progress.animateTo(1f, tween(playMs, easing = LinearEasing))
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
            val left = w * 0.07f
            val right = w * 0.93f
            // What "high" means for the shadow: the height of the drawing's sky.
            val rise = groundY - h * 0.06f

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

            fun at(i: Int): Offset {
                val x = left + shape.along[i] * (right - left)
                // Heights are in units of the flight's own length, then stretched.
                val y = groundY - shape.height[i] * shape.stretch * (right - left)
                return Offset(x, y.coerceIn(h * 0.06f, groundY))
            }

            val n = shape.along.size
            val head = progress.value * (n - 1)
            val whole = head.toInt().coerceIn(0, n - 1)
            val frac = head - whole

            // The arc so far: a soft glow under a solid line, straight segments between
            // sightings — nothing smoothed, so what shivered is drawn shivering.
            val path = Path().apply {
                val first = at(0)
                moveTo(first.x, first.y)
                for (i in 1..whole) at(i).let { lineTo(it.x, it.y) }
                if (whole < n - 1) {
                    val a = at(whole)
                    val b = at(whole + 1)
                    lineTo(a.x + (b.x - a.x) * frac, a.y + (b.y - a.y) * frac)
                }
            }
            drawPath(path, PathRed.copy(alpha = 0.22f), style = Stroke(9.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
            drawPath(path, PathRed, style = Stroke(3.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))

            // Where the ball was first seen — "first seen", not "release": the tracker may
            // have picked it up after it left the hand.
            drawCircle(Color.White.copy(alpha = 0.7f), radius = 3.dp.toPx(), center = at(0))

            // The bounce, once the ball has reached it: a mark on the ground and a ring
            // spreading out from it.
            shape.bounce?.let { b ->
                if (head >= b) {
                    val o = Offset(at(b).x, groundY)
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
                    }
                }
            }

            // The ball and its shadow. The shadow slides along the ground under it and
            // tightens as the ball comes down — that is what makes a line read as a
            // ball in the air rather than a graph.
            val a = at(whole)
            val b = at((whole + 1).coerceAtMost(n - 1))
            val ball = Offset(a.x + (b.x - a.x) * frac, a.y + (b.y - a.y) * frac)
            val air = ((groundY - ball.y) / rise).coerceIn(0f, 1f)
            val shadowW = 7.dp.toPx() + air * 9.dp.toPx()
            drawOval(
                Color.Black.copy(alpha = 0.55f - 0.35f * air),
                topLeft = Offset(ball.x - shadowW, groundY - 1.5.dp.toPx()),
                size = Size(shadowW * 2, 3.5.dp.toPx()),
            )
            drawCircle(PathRed.copy(alpha = 0.35f), radius = 9.dp.toPx(), center = ball)
            drawCircle(Color.White, radius = 5.dp.toPx(), center = ball)
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
                append("Not in metres — the camera isn't calibrated.")
            },
            color = Color.White.copy(alpha = 0.42f),
            fontSize = 11.5.sp,
            lineHeight = 16.sp,
        )
    }
}
