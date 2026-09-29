package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.Animatable
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.runtime.mutableStateOf
import androidx.compose.ui.composed
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.animations.pressScale
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import kotlinx.coroutines.launch

/**
 * The drawn objects of the Insights tab — a ball, a set of stumps, a pair of bats.
 *
 * Illustrated rather than iconified. A flat dot is a legend key; a ball with a lit side,
 * a seam and a shadow under it is an object, and a player's eye treats it like one. Every
 * piece here stands for something the ball log actually counted — a six is a ball, a
 * wicket is a set of stumps with the bails gone — so the art carries the data instead of
 * sitting beside it.
 *
 * All of it is drawn in code: it scales to any density, takes the team's own colours, and
 * can move when it is touched, none of which a bitmap can do.
 */

internal fun lighten(c: Color, f: Float): Color = lerp(c, Color.White, f)
internal fun darken(c: Color, f: Float): Color = lerp(c, Color.Black, f)

/** Leather — the colour a real ball is, for the illustrations that are not a stroke key. */
internal val LeatherRed = Color(0xFFB91C1C)
private val Willow = Color(0xFFEAD7AE)
private val WillowEdge = Color(0xFFC7A86E)
private val StumpWood = Color(0xFFF3E6C8)
private val StumpEdge = Color(0xFFB99A62)

/**
 * A cricket ball, lit from the top left.
 *
 * [shadowAt] is where the ball's shadow falls — the ground under it. Pass the resting
 * centre while the ball itself is lifted and the gap between the two is what makes a hop
 * read as a hop.
 */
internal fun DrawScope.drawLeatherBall(
    center: Offset,
    radius: Float,
    leather: Color,
    shadowAt: Offset? = null,
) {
    shadowAt?.let { s ->
        val lift = ((s.y - center.y) / radius).coerceIn(0f, 3f)
        val w = radius * 1.7f * (1f - lift * 0.12f)
        val h = radius * 0.42f * (1f - lift * 0.12f)
        drawOval(
            color = Color.Black.copy(alpha = 0.16f * (1f - lift * 0.22f)),
            topLeft = Offset(s.x - w / 2f, s.y + radius * 0.78f),
            size = Size(w, h),
        )
    }

    drawCircle(
        brush = Brush.radialGradient(
            listOf(lighten(leather, 0.38f), leather, darken(leather, 0.3f)),
            center = center + Offset(-radius * 0.38f, -radius * 0.42f),
            radius = radius * 1.9f,
        ),
        radius = radius,
        center = center,
    )

    // The seam: two parallel stitched rows curving across the face.
    val clip = Path().apply { addOval(Rect(center, radius)) }
    clipPath(clip) {
        val seam = lighten(leather, 0.72f).copy(alpha = 0.9f)
        val w = (radius * 0.085f).coerceAtLeast(0.8f)
        for (dx in floatArrayOf(-0.09f, 0.09f)) {
            drawPath(
                Path().apply {
                    moveTo(center.x + (-0.5f + dx) * radius, center.y - 1.05f * radius)
                    quadraticTo(
                        center.x + (0.38f + dx) * radius, center.y,
                        center.x + (-0.5f + dx) * radius, center.y + 1.05f * radius,
                    )
                },
                color = seam,
                style = Stroke(width = w, cap = StrokeCap.Round),
            )
        }
        // Stitches only where there is room to see them; on a tiny ball they are mush.
        if (radius > 7.dp.toPx()) {
            var t = 0.14f
            while (t < 0.9f) {
                val u = 1f - t
                val x = u * u * (-0.5f) + 2 * u * t * 0.38f + t * t * (-0.5f)
                val y = u * u * (-1.05f) + t * t * 1.05f
                val p = Offset(center.x + x * radius, center.y + y * radius)
                drawLine(
                    color = seam.copy(alpha = 0.75f),
                    start = p + Offset(-radius * 0.17f, -radius * 0.04f),
                    end = p + Offset(radius * 0.17f, radius * 0.04f),
                    strokeWidth = w * 0.8f,
                    cap = StrokeCap.Round,
                )
                t += 0.12f
            }
        }
    }

    // A polish highlight, and a rim so a pale ball still has an edge on a white page.
    drawOval(
        color = Color.White.copy(alpha = 0.34f),
        topLeft = center + Offset(-radius * 0.62f, -radius * 0.7f),
        size = Size(radius * 0.56f, radius * 0.34f),
    )
    drawCircle(
        color = darken(leather, 0.4f).copy(alpha = 0.35f),
        radius = radius,
        center = center,
        style = Stroke(width = (radius * 0.07f).coerceAtLeast(0.7f)),
    )
}

/**
 * Three stumps and two bails, standing in [area].
 *
 * [knocked] runs 0 → 1: the bails lift off and spin away. At 1 this is cricket's own mark
 * for a wicket, which no letter W in a red circle ever quite was.
 */
internal fun DrawScope.drawStumps(area: Rect, knocked: Float = 0f, wood: Color = StumpWood, edge: Color = StumpEdge) {
    val stumpW = area.width * 0.17f
    val gap = (area.width - stumpW * 3f) / 2f
    val top = area.top + area.height * 0.2f
    val cr = CornerRadius(stumpW / 2f, stumpW / 2f)
    repeat(3) { i ->
        val x = area.left + i * (stumpW + gap)
        drawRoundRect(wood, Offset(x, top), Size(stumpW, area.bottom - top), cr)
        drawRoundRect(edge, Offset(x, top), Size(stumpW, area.bottom - top), cr, style = Stroke(stumpW * 0.28f))
    }
    val bailH = area.height * 0.07f
    val bailW = area.width * 0.46f
    listOf(-1f, 1f).forEachIndexed { i, side ->
        val restX = area.left + if (i == 0) area.width * 0.04f else area.width * 0.5f
        val restY = top - bailH * 1.2f
        val dx = side * area.width * 0.42f * knocked
        val dy = -area.height * 0.28f * knocked
        val pivot = Offset(restX + bailW / 2f + dx, restY + bailH / 2f + dy)
        rotate(side * 55f * knocked, pivot) {
            drawRoundRect(
                edge,
                Offset(restX + dx, restY + dy),
                Size(bailW, bailH),
                CornerRadius(bailH / 2f, bailH / 2f),
            )
        }
    }
}

/**
 * One bat, blade down, drawn around [handleTop] and leaning by [angle] degrees.
 * The grip takes [grip] — the side's colour — which is the one place a team owns the kit.
 */
internal fun DrawScope.drawBat(handleTop: Offset, length: Float, angle: Float, grip: Color, alpha: Float = 1f) {
    rotate(angle, handleTop) {
        val handleW = length * 0.075f
        val handleL = length * 0.3f
        val bladeW = length * 0.19f
        val x = handleTop.x
        drawRoundRect(
            grip.copy(alpha = alpha),
            Offset(x - handleW / 2f, handleTop.y),
            Size(handleW, handleL),
            CornerRadius(handleW / 2f, handleW / 2f),
        )
        // Shoulders, then the blade with a darker edge down one side for thickness.
        drawRoundRect(
            Willow.copy(alpha = alpha),
            Offset(x - bladeW / 2f, handleTop.y + handleL * 0.92f),
            Size(bladeW, length - handleL),
            CornerRadius(bladeW * 0.35f, bladeW * 0.35f),
        )
        drawRoundRect(
            WillowEdge.copy(alpha = alpha),
            Offset(x + bladeW * 0.22f, handleTop.y + handleL * 0.98f),
            Size(bladeW * 0.28f, (length - handleL) * 0.97f),
            CornerRadius(bladeW * 0.2f, bladeW * 0.2f),
        )
        drawRoundRect(
            WillowEdge.copy(alpha = alpha * 0.8f),
            Offset(x - bladeW / 2f, handleTop.y + handleL * 0.92f),
            Size(bladeW, length - handleL),
            CornerRadius(bladeW * 0.35f, bladeW * 0.35f),
            style = Stroke(length * 0.012f),
        )
    }
}

/**
 * A bat as engraved line art — a single [ink] stroke, no fill. For marks pressed into a
 * dark surface: filled at low alpha, overlapping bats turn to grey mud; a line stays a
 * drawing at any opacity.
 */
internal fun DrawScope.drawBatOutline(handleTop: Offset, length: Float, angle: Float, ink: Color) {
    rotate(angle, handleTop) {
        val w = length * 0.011f
        val handleW = length * 0.07f
        val handleL = length * 0.3f
        val bladeW = length * 0.19f
        val x = handleTop.x
        val stroke = Stroke(width = w, cap = StrokeCap.Round)
        drawRoundRect(ink, Offset(x - handleW / 2f, handleTop.y), Size(handleW, handleL), CornerRadius(handleW / 2f), style = stroke)
        // Grip wraps.
        var y = handleTop.y + handleL * 0.12f
        while (y < handleTop.y + handleL * 0.85f) {
            drawLine(ink, Offset(x - handleW / 2f, y), Offset(x + handleW / 2f, y + handleW * 0.5f), w * 0.8f)
            y += handleL * 0.11f
        }
        val bladeTop = handleTop.y + handleL
        val blade = Path().apply {
            moveTo(x - handleW / 2f, bladeTop)
            quadraticTo(x - bladeW / 2f, bladeTop, x - bladeW / 2f, bladeTop + bladeW * 0.45f)
            lineTo(x - bladeW / 2f, handleTop.y + length - bladeW * 0.25f)
            quadraticTo(x - bladeW / 2f, handleTop.y + length, x - bladeW * 0.25f, handleTop.y + length)
            lineTo(x + bladeW * 0.25f, handleTop.y + length)
            quadraticTo(x + bladeW / 2f, handleTop.y + length, x + bladeW / 2f, handleTop.y + length - bladeW * 0.25f)
            lineTo(x + bladeW / 2f, bladeTop + bladeW * 0.45f)
            quadraticTo(x + bladeW / 2f, bladeTop, x + handleW / 2f, bladeTop)
        }
        drawPath(blade, ink, style = stroke)
        // The spine down the back of the blade.
        drawLine(ink, Offset(x, bladeTop + bladeW * 0.5f), Offset(x, handleTop.y + length - bladeW * 0.4f), w * 0.7f)
    }
}

/**
 * One delivery as a chip that sits proud of the page: boundaries and wickets are lit and
 * cast a shadow, dots and singles lie flat. Pressing it pushes it in, and the phone knocks
 * the way that ball knocked on the scorer's keypad.
 */
@Composable
internal fun BallChip(token: String, size: Dp = 30.dp) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val (bg, fg) = ballInk(token)
    val source = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val wide = token.length > 1
    val shape = if (wide) androidx.compose.foundation.shape.RoundedCornerShape(size / 3) else androidx.compose.foundation.shape.CircleShape
    val raised = token.trim().lowercase() in setOf("4", "6", "w")
    androidx.compose.foundation.layout.Box(
        Modifier
            .pressScale(source)
            .size(width = if (wide) size + 4.dp else size, height = size)
            .shadow(if (raised) 3.dp else 0.dp, shape)
            .clip(shape)
            .background(Brush.verticalGradient(listOf(lighten(bg, 0.24f), bg)))
            .then(if (raised) Modifier else Modifier.border(1.dp, Color(0xFFE2E8F0), shape))
            .clickable(interactionSource = source, indication = null) {
                scope.launch { cricketThud(ctx, ballThud(token)) }
            },
        contentAlignment = androidx.compose.ui.Alignment.Center,
    ) {
        androidx.compose.material3.Text(
            token,
            color = fg,
            fontSize = if (wide) (size.value / 3f).sp else (size.value / 2.4f).sp,
            fontWeight = androidx.compose.ui.text.font.FontWeight.Black,
        )
    }
}

/**
 * A section arrives once as it scrolls into view: up from twelve pixels, fading in, on a
 * spring. It happens the first time only, so scrolling back is still.
 */
internal fun Modifier.arrive(key: Any): Modifier = composed {
    val p = androidx.compose.runtime.saveable.rememberSaveable(key) { mutableStateOf(0f) }
    val anim = remember(key) { Animatable(p.value) }
    LaunchedEffect(key) {
        if (anim.value < 1f) {
            anim.animateTo(1f, spring(dampingRatio = 0.85f, stiffness = 180f))
            p.value = 1f
        }
    }
    graphicsLayer {
        alpha = anim.value.coerceIn(0f, 1f)
        translationY = (1f - anim.value) * 14.dp.toPx()
    }
}

/** A single ball as a legend or bullet — the same object the rack and the ground use. */
@Composable
internal fun BallGlyph(leather: Color, size: Dp = 12.dp) {
    Canvas(Modifier.size(size)) {
        drawLeatherBall(center, this.size.minDimension / 2f * 0.92f, leather)
    }
}

/** A knocked set of stumps at glyph size, for marking where a wicket fell. */
@Composable
internal fun WicketGlyph(modifier: Modifier = Modifier, knocked: Float = 1f) {
    Canvas(modifier) {
        drawStumps(
            Rect(Offset(size.width * 0.18f, 0f), Size(size.width * 0.64f, size.height)),
            knocked = knocked,
            wood = Color(0xFFFDECEC),
            edge = com.haraan.app.ui.matches.CrexColors.WicketBall,
        )
    }
}

private fun easeOutBounce(x: Float): Float {
    val n = 7.5625f
    val d = 2.75f
    return when {
        x < 1f / d -> n * x * x
        x < 2f / d -> { val v = x - 1.5f / d; n * v * v + 0.75f }
        x < 2.5f / d -> { val v = x - 2.25f / d; n * v * v + 0.9375f }
        else -> { val v = x - 2.625f / d; n * v * v + 0.984375f }
    }
}

/**
 * Every boundary as the ball it was.
 *
 * Twelve sixes is twelve balls sitting in a row — a player counts them the way they would
 * count balls in a bucket, and sees the difference between a side that hit three and one
 * that hit fourteen before reading a number. They drop in once, bounce, and settle; touch
 * one and it hops and the phone knocks the way that stroke knocks on the scorer's keypad.
 */
@Composable
internal fun BallRack(count: Int, leather: Color, thud: Thud, startDelayMs: Int = 0) {
    if (count <= 0) return
    val shown = count.coerceAtMost(MAX_RACK)
    val drop = remember(count) { Animatable(0f) }
    val stagger = 45
    val each = 520
    LaunchedEffect(count) {
        drop.snapTo(0f)
        kotlinx.coroutines.delay(startDelayMs.toLong())
        drop.animateTo(1f, tween(each + stagger * (shown - 1)))
    }
    val hop = remember { Animatable(0f) }
    var hit by remember { mutableIntStateOf(-1) }
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current

    BoxWithConstraints(Modifier.fillMaxWidth()) {
        val ball = 17.dp
        val gap = 5.dp
        val perRow = ((maxWidth + gap) / (ball + gap)).toInt().coerceAtLeast(1)
        val rows = (shown + perRow - 1) / perRow
        val rowH = ball + 7.dp
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(rowH * rows)
                .pointerInput(shown, perRow) {
                    detectTapGestures { p ->
                        val col = (p.x / (ball + gap).toPx()).toInt()
                        val row = (p.y / rowH.toPx()).toInt()
                        val i = row * perRow + col
                        if (col < perRow && i in 0 until shown) {
                            hit = i
                            scope.launch { cricketThud(ctx, thud) }
                            scope.launch {
                                hop.snapTo(0f)
                                hop.animateTo(1f, tween(110))
                                hop.animateTo(0f, spring(dampingRatio = 0.38f, stiffness = 420f))
                            }
                        }
                    }
                },
        ) {
            val r = ball.toPx() / 2f
            val total = drop.value * (each + stagger * (shown - 1))
            for (i in 0 until shown) {
                val local = ((total - i * stagger) / each).coerceIn(0f, 1f)
                if (local <= 0f) continue
                val col = i % perRow
                val row = i / perRow
                val rest = Offset(col * (ball + gap).toPx() + r, row * rowH.toPx() + r)
                val fall = (1f - easeOutBounce(local)) * r * 3.2f
                val lift = if (i == hit) hop.value * r * 1.1f else 0f
                drawLeatherBall(rest - Offset(0f, fall + lift), r, leather, shadowAt = rest)
            }
        }
    }
}

internal const val MAX_RACK = 36
