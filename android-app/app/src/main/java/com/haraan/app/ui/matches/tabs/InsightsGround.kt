package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.ui.text.drawText
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.InningsInsight
import com.haraan.app.ui.animations.pressScale
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.WAGON_ZONES
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.ui.matches.wagonZoneAngle
import kotlinx.coroutines.launch
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.roundToInt
import kotlin.math.sin

private val GrassLight = Color(0xFFCFE7C8)
private val GrassDark = Color(0xFFBADAB1)
internal val ClayStrip = Color(0xFFE7D3A8)
internal val ClayWorn = Color(0xFFDAC293)

/**
 * The wagon wheel, drawn as the ground it happened on.
 *
 * Every line is a shot a scorer watched and placed — nothing is inferred, and a boundary
 * whose direction was skipped simply is not here. What changed is that it now LOOKS like
 * a ground: mown rings, a rope with a shadow, the thirty-yard circle, a pitch with its
 * creases and stumps. A four is drawn along the turf, because that is where it went; a six
 * is drawn through the air, with its shadow on the grass underneath, because that is where
 * IT went. The difference between the two strokes is the one thing the runs already say,
 * and here it is shown instead of coloured.
 *
 * The ground is a control. Touch cover and cover lights up, every other shot steps back,
 * and the figures for that region replace the list below — with the phone knocking once
 * for a region of fours and twice for one that went for six.
 *
 * Shots captured before exact points existed carry only a region; those fan out inside
 * their wedge on a fixed seed, so the wheel is the same on every recomposition.
 */
@Composable
internal fun WagonWheel(inn: InningsInsight, only: Int = 0) {
    // An empty ground drawn full-width is the loudest thing on the page and says nothing.
    // Until a boundary has been placed, it is a thumbnail and a line.
    if (inn.shots.isEmpty()) {
        EmptyGround(boundaries = inn.fours + inn.sixes)
        return
    }
    val shots = when (only) {
        4 -> inn.shots.filter { it.runs == 4 }
        6 -> inn.shots.filter { it.runs >= 6 }
        else -> inn.shots
    }
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val measurer = androidx.compose.ui.text.rememberTextMeasurer()
    var picked by remember(inn.battingName, only) { mutableIntStateOf(-1) }
    // Each region's share of these shots' runs, printed on the grass where it went.
    val shares = remember(shots) {
        val total = shots.sumOf { it.runs }.coerceAtLeast(1)
        shots.groupBy { it.zone }.mapValues { (_, s) -> s.sumOf { it.runs } * 100 / total }
    }

    val draw = remember(inn.battingName, shots.size, only) { Animatable(0f) }
    LaunchedEffect(inn.battingName, shots.size, only) {
        draw.animateTo(1f, tween(650 + shots.size.coerceAtMost(30) * 55, easing = FastOutSlowInEasing))
    }
    val glow by animateFloatAsState(
        if (picked >= 0) 1f else 0f,
        spring(dampingRatio = 0.8f, stiffness = 420f),
        label = "zoneGlow",
    )
    // The lit wedge swings round to the new region by the short way, rather than blinking.
    val wedge = remember { Animatable(0f) }
    LaunchedEffect(picked) {
        if (picked < 0) return@LaunchedEffect
        val target = picked * 45f
        if (glow < 0.05f) {
            wedge.snapTo(target)
        } else {
            val delta = ((target - wedge.value + 540f) % 360f) - 180f
            wedge.animateTo(wedge.value + delta, spring(dampingRatio = 0.72f, stiffness = 380f))
        }
    }

    fun pick(zone: Int) {
        picked = if (zone == picked) -1 else zone
        val inZone = shots.filter { it.zone == zone }
        val kind = when {
            picked < 0 -> Thud.TICK
            inZone.any { it.runs >= 6 } -> Thud.SIX
            inZone.isNotEmpty() -> Thud.FOUR
            else -> Thud.TICK
        }
        scope.launch { cricketThud(ctx, kind) }
    }

    Column {
        Box(
            modifier = Modifier.fillMaxWidth().aspectRatio(1f),
            contentAlignment = Alignment.Center,
        ) {
            Canvas(
                Modifier
                    .fillMaxSize()
                    .pointerInput(shots) {
                        detectTapGestures { p ->
                            val r = minOf(size.width, size.height) / 2f * 0.96f
                            val dx = p.x - size.width / 2f
                            val dy = p.y - size.height / 2f
                            val d = hypot(dx, dy)
                            if (d > r * 1.02f || d < r * 0.1f) {
                                if (picked >= 0) pick(picked)
                                return@detectTapGestures
                            }
                            val deg = Math.toDegrees(atan2(dy, dx).toDouble()).toFloat()
                            pick((((deg + 90f) / 45f).roundToInt() % 8 + 8) % 8)
                        }
                    },
            ) {
                val r = minOf(size.width, size.height) / 2f * 0.96f
                val c = center

                // Mown rings, outermost first.
                for (k in 8 downTo 1) {
                    drawCircle(if (k % 2 == 0) GrassLight else GrassDark, radius = r * k / 8f, center = c)
                }
                // Light falls off toward the rope, so the middle reads as the middle.
                drawCircle(
                    Brush.radialGradient(
                        listOf(Color.White.copy(alpha = 0.22f), Color.Transparent, Color.Black.copy(alpha = 0.07f)),
                        center = c,
                        radius = r,
                    ),
                    radius = r,
                    center = c,
                )

                // The region being read.
                if (glow > 0.01f) {
                    drawArc(
                        color = Color.White.copy(alpha = 0.5f * glow),
                        startAngle = wedge.value - 90f - 22.5f,
                        sweepAngle = 45f,
                        useCenter = true,
                        topLeft = Offset(c.x - r, c.y - r),
                        size = Size(r * 2f, r * 2f),
                    )
                }

                // Region lines, chalked faintly on the grass.
                repeat(8) { i ->
                    val a = Math.toRadians((i * 45.0) + 22.5 - 90.0).toFloat()
                    drawLine(
                        Color.White.copy(alpha = 0.45f),
                        Offset(c.x + cos(a) * r * 0.14f, c.y + sin(a) * r * 0.14f),
                        Offset(c.x + cos(a) * r, c.y + sin(a) * r),
                        strokeWidth = 1.dp.toPx(),
                    )
                }

                // Thirty-yard circle.
                drawCircle(
                    Color.White.copy(alpha = 0.85f),
                    radius = r * 0.52f,
                    center = c,
                    style = Stroke(
                        width = 1.3.dp.toPx(),
                        pathEffect = PathEffect.dashPathEffect(floatArrayOf(6.dp.toPx(), 5.dp.toPx())),
                    ),
                )

                // The rope, with a shadow so it sits on the ground rather than printed on it.
                drawCircle(Color.Black.copy(alpha = 0.1f), r, c + Offset(0f, 1.6.dp.toPx()), style = Stroke(3.6.dp.toPx()))
                drawCircle(Color.White, r, c, style = Stroke(2.6.dp.toPx()))

                // The pitch: clay strip, worn middle, creases, stumps at each end.
                val pw = r * 0.085f
                val ph = r * 0.36f
                val pl = Offset(c.x - pw / 2f, c.y - ph / 2f)
                drawRoundRect(ClayStrip, pl, Size(pw, ph), CornerRadius(2.dp.toPx()))
                drawRoundRect(ClayWorn, pl + Offset(pw * 0.25f, ph * 0.18f), Size(pw * 0.5f, ph * 0.64f), CornerRadius(pw * 0.2f))
                for (end in floatArrayOf(0.12f, 0.88f)) {
                    val y = pl.y + ph * end
                    drawLine(Color.White, Offset(c.x - pw * 0.9f, y), Offset(c.x + pw * 0.9f, y), 1.dp.toPx())
                    val sy = pl.y + ph * (if (end < 0.5f) 0.04f else 0.96f)
                    for (s in -1..1) {
                        drawCircle(Color(0xFF8A6A3A), radius = 0.9.dp.toPx(), center = Offset(c.x + s * pw * 0.2f, sy))
                    }
                }

                // The shots.
                val n = shots.size
                val stagger = 0.22f
                val span = 1f + (n - 1).coerceAtLeast(0) * stagger
                shots.forEachIndexed { i, sh ->
                    val local = ((draw.value * span) - i * stagger).coerceIn(0f, 1f)
                    if (local <= 0f) return@forEachIndexed
                    val e = 1f - (1f - local) * (1f - local)
                    val six = sh.runs >= 6
                    val colour = if (six) CrexColors.SixBall else CrexColors.FourBall
                    val dim = if (picked >= 0 && sh.zone != picked) 1f - 0.78f * glow else 1f

                    val end = if (sh.x != null && sh.y != null) {
                        Offset(c.x + sh.x * r, c.y + sh.y * r)
                    } else {
                        val spread = (((i * 37) % 31) / 31f - 0.5f) * 0.62f
                        val a = wagonZoneAngle(sh.zone) + spread
                        val reach = if (six) 0.97f else 0.9f
                        Offset(c.x + cos(a) * r * reach, c.y + sin(a) * r * reach)
                    }

                    if (six) {
                        // Shadow on the turf, then the ball's flight over it.
                        val tip = lerpOffset(c, end, e)
                        drawLine(
                            Color.Black.copy(alpha = 0.13f * dim), c, tip, 1.6.dp.toPx(),
                            cap = StrokeCap.Round,
                            pathEffect = PathEffect.dashPathEffect(floatArrayOf(3.dp.toPx(), 3.dp.toPx())),
                        )
                        val len = (end - c).getDistance().coerceAtLeast(1f)
                        val perp = Offset(-(end.y - c.y) / len, (end.x - c.x) / len)
                        val ctrl = lerpOffset(c, end, 0.5f) + perp * (len * 0.3f)
                        val flight = Path().apply {
                            moveTo(c.x, c.y)
                            val steps = 22
                            for (s in 1..steps) {
                                val t = e * s / steps
                                val u = 1f - t
                                lineTo(
                                    u * u * c.x + 2 * u * t * ctrl.x + t * t * end.x,
                                    u * u * c.y + 2 * u * t * ctrl.y + t * t * end.y,
                                )
                            }
                        }
                        drawPath(flight, Color.White.copy(alpha = 0.75f * dim), style = Stroke(5.dp.toPx(), cap = StrokeCap.Round))
                        drawPath(flight, colour.copy(alpha = dim), style = Stroke(2.4.dp.toPx(), cap = StrokeCap.Round))
                    } else {
                        val tip = lerpOffset(c, end, e)
                        drawLine(Color.White.copy(alpha = 0.7f * dim), c, tip, 4.2.dp.toPx(), StrokeCap.Round)
                        drawLine(colour.copy(alpha = dim), c, tip, 2.dp.toPx(), StrokeCap.Round)
                    }

                    // The ball lands — pops in at the end of its line.
                    if (local > 0.82f) {
                        val pop = ((local - 0.82f) / 0.18f).coerceIn(0f, 1f)
                        val br = (if (six) 5.2.dp.toPx() else 4.2.dp.toPx()) * (0.55f + 0.45f * pop)
                        if (dim > 0.5f) {
                            drawLeatherBall(end, br, colour, shadowAt = end)
                        } else {
                            drawCircle(colour.copy(alpha = dim), br * 0.8f, end)
                        }
                    }
                }

                // Each region's share, set on a small white tag so it reads on grass and
                // over the lines alike. Only regions that were actually hit carry one.
                if (draw.value > 0.6f) {
                    val fade = ((draw.value - 0.6f) / 0.4f).coerceIn(0f, 1f)
                    shares.forEach { (zone, pct) ->
                        if (pct <= 0) return@forEach
                        val a = wagonZoneAngle(zone)
                        val at = Offset(c.x + cos(a) * r * 0.7f, c.y + sin(a) * r * 0.7f)
                        val lit = picked < 0 || picked == zone
                        val t = measurer.measure(
                            "$pct%",
                            TextStyle(color = CrexColors.TextPrimary.copy(alpha = if (lit) 1f else 0.4f), fontSize = 12.sp, fontWeight = FontWeight.Bold),
                        )
                        val padX = 6.dp.toPx()
                        val padY = 3.dp.toPx()
                        drawRoundRect(
                            Color.White.copy(alpha = 0.92f * fade * (if (lit) 1f else 0.6f)),
                            Offset(at.x - t.size.width / 2f - padX, at.y - t.size.height / 2f - padY),
                            Size(t.size.width + padX * 2, t.size.height + padY * 2),
                            CornerRadius(8.dp.toPx()),
                        )
                        drawText(t, topLeft = Offset(at.x - t.size.width / 2f, at.y - t.size.height / 2f), alpha = fade)
                    }
                }
            }
        }

        Spacer(Modifier.height(14.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            BallGlyph(CrexColors.FourBall, 13.dp)
            Spacer(Modifier.width(7.dp))
            Text(
                "${shots.count { it.runs == 4 }} fours",
                color = CrexColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.width(16.dp))
            BallGlyph(CrexColors.SixBall, 13.dp)
            Spacer(Modifier.width(7.dp))
            Text(
                "${shots.count { it.runs >= 6 }} sixes",
                color = CrexColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
            )
        }

        AnimatedVisibility(
            visible = picked >= 0,
            enter = fadeIn(tween(180)) + expandVertically(),
            exit = fadeOut(tween(120)) + shrinkVertically(),
        ) {
            Column {
                Spacer(Modifier.height(16.dp))
                if (picked >= 0) ZoneRead(inn, picked)
            }
        }
    }
}

@Composable
private fun EmptyGround(boundaries: Int) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Canvas(Modifier.size(64.dp)) {
            val r = size.minDimension / 2f * 0.94f
            for (k in 4 downTo 1) {
                drawCircle(if (k % 2 == 0) GrassLight else GrassDark, radius = r * k / 4f, center = center)
            }
            drawCircle(Color(0xFF9CC994), r + 1.2.dp.toPx(), center, style = Stroke(1.dp.toPx()))
            drawCircle(Color.White, r, center, style = Stroke(1.6.dp.toPx()))
            drawCircle(
                Color.White.copy(alpha = 0.85f), r * 0.52f, center,
                style = Stroke(1.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(3.dp.toPx(), 3.dp.toPx()))),
            )
            drawRoundRect(
                ClayStrip,
                Offset(center.x - r * 0.06f, center.y - r * 0.2f),
                Size(r * 0.12f, r * 0.4f),
                CornerRadius(1.dp.toPx()),
            )
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(
                if (boundaries > 0) "$boundaries boundar${if (boundaries == 1) "y" else "ies"}, none placed yet" else "No boundaries yet",
                color = CrexColors.TextPrimary,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                "Each four and six lands here once the scorer marks where it went.",
                color = CrexColors.TextMuted,
                fontSize = 12.sp,
                lineHeight = 17.sp,
            )
        }
    }
}

/** One region, read in full — what the lit wedge on the ground is worth. */
@Composable
private fun ZoneRead(inn: InningsInsight, zone: Int) {
    val inZone = inn.shots.filter { it.zone == zone }
    val fours = inZone.count { it.runs == 4 }
    val sixes = inZone.count { it.runs >= 6 }
    val runs = inZone.sumOf { it.runs }
    val share = if (inn.runs > 0) runs * 100 / inn.runs else 0
    Column(Modifier.fillMaxWidth()) {
        Text(
            WAGON_ZONES.getOrElse(zone) { "Region" }.uppercase(),
            color = CrexColors.AccentBlue,
            fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(4.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "$runs",
                color = CrexColors.TextPrimary,
                fontSize = 40.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1.4).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                if (runs == 1) "run" else "runs",
                color = CrexColors.TextSecondary,
                fontSize = 14.sp,
                modifier = Modifier.padding(bottom = 7.dp),
            )
        }
        Text(
            when {
                inZone.isEmpty() -> "Nothing placed through here this innings."
                else -> buildList {
                    if (fours > 0) add("$fours four${if (fours == 1) "" else "s"}")
                    if (sixes > 0) add("$sixes six${if (sixes == 1) "" else "es"}")
                }.joinToString(" and ") + " — $share% of the ${inn.runs}."
            },
            color = CrexColors.TextSecondary,
            fontSize = 13.sp,
            lineHeight = 19.sp,
        )
        if (inZone.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                inZone.take(14).forEach { BallGlyph(if (it.runs >= 6) CrexColors.SixBall else CrexColors.FourBall, 15.dp) }
            }
        }
    }
}

private fun lerpOffset(a: Offset, b: Offset, t: Float) = Offset(a.x + (b.x - a.x) * t, a.y + (b.y - a.y) * t)
