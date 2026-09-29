package com.haraan.app.ui.rewards

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.drawscope.withTransform
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import com.haraan.app.ui.Feel
import kotlinx.coroutines.launch
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin

/**
 * The result as a picture, drawn in code so it stays crisp at any density and costs no
 * assets. One scene per outcome, dressed for the sport that was played:
 *
 *   Victory   — a gold cup between crossed bats (cricket) or laurels (everything else).
 *   Defeat    — the stumps with a bail knocked off, or the ball at rest under a
 *               "run it back" arc. Dignified: no red, no broken things.
 *   Draw      — a balance, one ball in each pan, swinging until it settles level.
 *   Full time — the ball at rest.
 *
 * It behaves like an object, not a sticker. On first view it drops in and bounces, and the
 * haptic ([resultThud]) fires on the exact frame it touches down; a victory throws one
 * burst of confetti from that contact and stops. Tapping it knocks it and it rocks back.
 *
 * Everything is authored in a fixed 240 × 150 design space and scaled to fit.
 */
@Composable
internal fun ResultIllustration(
    outcome: Outcome,
    sport: String,
    play: Boolean,
    animate: Boolean,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()

    // 0 = held above the frame, 1 = resting. A spring overshoots past 1; distance from 1 is
    // read as height, so the overshoot comes back as a bounce instead of sinking into the floor.
    val drop = remember { Animatable(if (animate) 0f else 1f) }
    val burst = remember { Animatable(if (animate) 0f else 1f) }
    val sweep = remember { Animatable(if (animate) 0f else 1f) }
    val sway = remember { Animatable(0f) }

    LaunchedEffect(play) {
        if (!play || drop.value >= 1f) return@LaunchedEffect
        if (outcome == Outcome.TIED) sway.snapTo(15f)
        launch { sweep.animateTo(1f, tween(1500, easing = FastOutSlowInEasing)) }
        var landed = false
        drop.animateTo(
            1f,
            spring(dampingRatio = if (outcome == Outcome.WON) 0.52f else 0.36f, stiffness = 240f),
        ) {
            if (!landed && value >= 1f) {
                landed = true
                scope.launch { resultThud(context, outcome) }
                if (outcome == Outcome.WON) scope.launch { burst.animateTo(1f, tween(1300, easing = LinearEasing)) }
                if (outcome == Outcome.TIED) scope.launch { sway.animateTo(0f, spring(0.22f, 90f)) }
            }
        }
    }

    val described = when (outcome) {
        Outcome.WON -> "Trophy"
        Outcome.TIED -> "Balanced scale"
        Outcome.LOST, Outcome.FINISHED -> "Match ball at rest"
    }

    Canvas(
        modifier
            .semantics { contentDescription = described }
            .pointerInput(outcome) {
                detectTapGestures {
                    view.performHapticFeedback(Feel.SELECT)
                    scope.launch {
                        sway.snapTo(if (sway.value >= 0f) -11f else 11f)
                        sway.animateTo(0f, spring(dampingRatio = 0.2f, stiffness = 260f))
                    }
                }
            },
    ) {
        val s = min(size.width / W, size.height / H)
        val ox = (size.width - W * s) / 2f
        val oy = (size.height - H * s) / 2f
        val lift = abs(1f - drop.value) * DROP
        val fade = (drop.value * 3f).coerceIn(0f, 1f)
        withTransform({
            translate(ox, oy)
            scale(s, s, Offset.Zero)
        }) {
            when (outcome) {
                Outcome.WON -> wonScene(sport, lift, fade, sway.value, sweep.value, burst.value)
                Outcome.TIED -> tiedScene(sport, lift, fade, sway.value)
                Outcome.LOST -> restScene(sport, lift, fade, sway.value, sweep.value, lost = true)
                Outcome.FINISHED -> restScene(sport, lift, fade, sway.value, sweep.value, lost = false)
            }
        }
    }
}

private const val W = 240f
private const val H = 150f
private const val GROUND = 134f
private const val DROP = 70f

// ── Palette: the board's navy + one warm metal. Nothing neon. ──────────────────────────────
private val Navy = Color(0xFF0F172A)
private val Navy2 = Color(0xFF1E293B)
private val Slate = Color(0xFF334155)
private val SlateLine = Color(0xFFCBD5E1)
private val GoldHi = Color(0xFFFDE68A)
private val Gold = Color(0xFFF59E0B)
private val GoldLo = Color(0xFFB45309)
private val Leaf = Color(0xFF059669)
private val LeafHi = Color(0xFF34D399)
private val Willow = Color(0xFFEBCB9C)
private val WillowLo = Color(0xFFC08A52)
private val Stump = Color(0xFFF3E6CC)

// ── Shared ground ──────────────────────────────────────────────────────────────────────────

private fun DrawScope.ground(lift: Float, width: Float = 78f) {
    // The contact shadow tightens and darkens as the object comes down onto it.
    val k = 1f - (lift / DROP).coerceIn(0f, 1f) * 0.6f
    drawOval(
        Navy.copy(alpha = 0.10f * k),
        topLeft = Offset(120f - width * k / 2f, GROUND + 1f),
        size = Size(width * k, 7f),
    )
    drawLine(
        Brush.horizontalGradient(listOf(Color.Transparent, SlateLine, SlateLine, Color.Transparent), 30f, 210f),
        Offset(30f, GROUND + 4.5f), Offset(210f, GROUND + 4.5f), strokeWidth = 1f,
    )
}

// ── Victory ────────────────────────────────────────────────────────────────────────────────

private fun DrawScope.wonScene(sport: String, lift: Float, fade: Float, sway: Float, sweep: Float, burst: Float) {
    rays(Offset(120f, 62f), turn = sweep * 16f, alpha = sweep)
    ground(lift, 84f)
    withTransform({
        translate(0f, -lift)
        rotate(sway, Offset(120f, GROUND))
    }) {
        val a = fade
        if (sport == "cricket") crossedBats(a) else laurels(a)
        trophy(a, shine = sweep)
        if (sport != "cricket") sportBall(sport, Offset(170f, GROUND - 10f), 10f, a)
        else sportBall(sport, Offset(166f, GROUND - 7f), 7f, a)
    }
    confetti(Offset(120f, 40f), burst)
}

/** Soft wedges fanning from behind the cup; they turn a few degrees on entry, then hold. */
private fun DrawScope.rays(c: Offset, turn: Float, alpha: Float) {
    if (alpha <= 0f) return
    val brush = Brush.radialGradient(listOf(Gold.copy(alpha = 0.20f * alpha), Color.Transparent), c, 118f)
    val n = 14
    for (i in 0 until n step 2) {
        val a0 = Math.toRadians((i * 360.0 / n) + turn).toFloat()
        val a1 = Math.toRadians(((i + 1) * 360.0 / n) + turn).toFloat()
        val p = Path().apply {
            moveTo(c.x, c.y)
            lineTo(c.x + cos(a0) * 130f, c.y + sin(a0) * 130f)
            lineTo(c.x + cos(a1) * 130f, c.y + sin(a1) * 130f)
            close()
        }
        drawPath(p, brush)
    }
}

private fun DrawScope.trophy(a: Float, shine: Float) {
    // Plinth
    drawRoundRect(Navy2.copy(alpha = a), Offset(94f, 117f), Size(52f, 17f), CornerRadius(3f))
    drawRoundRect(Slate.copy(alpha = a), Offset(94f, 117f), Size(52f, 4f), CornerRadius(2f))
    drawRoundRect(
        Brush.horizontalGradient(listOf(GoldHi, Gold, GoldLo), 108f, 132f),
        Offset(108f, 124f), Size(24f, 6f), CornerRadius(1.5f), alpha = a,
    )
    // Stem + knot
    val stem = Path().apply {
        moveTo(111f, 117f); lineTo(129f, 117f); lineTo(124f, 101f); lineTo(116f, 101f); close()
    }
    drawPath(stem, Brush.horizontalGradient(listOf(GoldHi, Gold, GoldLo), 111f, 129f), alpha = a)
    drawOval(Brush.horizontalGradient(listOf(GoldHi, Gold, GoldLo), 108f, 132f), Offset(109f, 97f), Size(22f, 7f), alpha = a)
    val neck = Path().apply {
        moveTo(116f, 98f); lineTo(124f, 98f); lineTo(122f, 88f); lineTo(118f, 88f); close()
    }
    drawPath(neck, GoldLo.copy(alpha = a))

    // Handles, behind the bowl's silhouette edge
    val handleStroke = Stroke(width = 5f, cap = StrokeCap.Round)
    val left = Path().apply { moveTo(93f, 42f); cubicTo(70f, 40f, 70f, 72f, 102f, 76f) }
    val right = Path().apply { moveTo(147f, 42f); cubicTo(170f, 40f, 170f, 72f, 138f, 76f) }
    drawPath(left, Gold.copy(alpha = a), style = handleStroke)
    drawPath(right, GoldLo.copy(alpha = a), style = handleStroke)

    // Bowl
    val bowl = Path().apply {
        moveTo(89f, 34f); lineTo(151f, 34f)
        cubicTo(151f, 70f, 139f, 88f, 120f, 91f)
        cubicTo(101f, 88f, 89f, 70f, 89f, 34f)
        close()
    }
    drawPath(bowl, Brush.horizontalGradient(listOf(GoldHi, Gold, Gold, GoldLo), 89f, 151f), alpha = a)
    clipPath(bowl) {
        // Emblem: a star pressed into the metal
        drawPath(star(Offset(120f, 58f), 11f, 4.6f), GoldLo.copy(alpha = 0.38f * a))
        drawPath(star(Offset(119.3f, 57.2f), 11f, 4.6f), GoldHi.copy(alpha = 0.55f * a))
        // Specular sweep that crosses the cup once on entry
        if (shine in 0.01f..0.99f) {
            val x = 70f + shine * 110f
            val band = Path().apply {
                moveTo(x, 20f); lineTo(x + 14f, 20f); lineTo(x - 8f, 100f); lineTo(x - 22f, 100f); close()
            }
            drawPath(band, Color.White.copy(alpha = 0.45f * a))
        }
    }
    // Resting highlight along the left curve
    val hi = Path().apply { moveTo(96f, 41f); cubicTo(96f, 60f, 101f, 74f, 109f, 82f) }
    drawPath(hi, Color.White.copy(alpha = 0.55f * a), style = Stroke(3.2f, cap = StrokeCap.Round))
    // Rim
    drawOval(Color(0xFFFCD34D).copy(alpha = a), Offset(88f, 29f), Size(64f, 10f))
    drawOval(GoldLo.copy(alpha = 0.55f * a), Offset(93f, 31f), Size(54f, 6f))
}

private fun DrawScope.crossedBats(a: Float) {
    // Blades up, crossed low behind the plinth, fanning out past the cup's waist.
    val pivot = Offset(120f, 108f)
    for (deg in listOf(-48f, 48f)) {
        rotate(deg, pivot) {
            drawRoundRect(Willow.copy(alpha = a), Offset(113f, 42f), Size(14f, 62f), CornerRadius(5f))
            drawRoundRect(WillowLo.copy(alpha = 0.55f * a), Offset(123f, 44f), Size(4f, 58f), CornerRadius(2f))
            drawRoundRect(WillowLo.copy(alpha = a), Offset(117.5f, 102f), Size(5f, 22f), CornerRadius(2f))
            for (i in 0 until 4) {
                drawLine(Navy2.copy(alpha = 0.7f * a), Offset(117.5f, 106f + i * 4f), Offset(122.5f, 108f + i * 4f), 1.2f)
            }
        }
    }
}

/** Two sprays of leaves climbing either side of the cup, placed along a bezier. */
private fun DrawScope.laurels(a: Float) {
    for (side in listOf(-1f, 1f)) {
        val p0 = Offset(120f + side * 26f, 128f)
        val c = Offset(120f + side * 66f, 108f)
        val p1 = Offset(120f + side * 50f, 44f)
        drawPath(
            Path().apply { moveTo(p0.x, p0.y); quadraticTo(c.x, c.y, p1.x, p1.y) },
            Leaf.copy(alpha = 0.8f * a), style = Stroke(2f, cap = StrokeCap.Round),
        )
        val n = 7
        for (i in 1..n) {
            val t = i / (n + 0.6f)
            val pt = quad(p0, c, p1, t)
            val tan = quadTangent(p0, c, p1, t)
            val ang = Math.toDegrees(atan2(tan.y, tan.x).toDouble()).toFloat()
            for (flip in listOf(-1f, 1f)) {
                rotate(ang + flip * 38f, pt) {
                    val len = 13f - i * 0.8f
                    val leaf = Path().apply {
                        moveTo(pt.x, pt.y)
                        quadraticTo(pt.x + len * 0.5f, pt.y - 4.2f, pt.x + len, pt.y)
                        quadraticTo(pt.x + len * 0.5f, pt.y + 4.2f, pt.x, pt.y)
                        close()
                    }
                    drawPath(leaf, (if (flip > 0) Leaf else LeafHi).copy(alpha = a))
                }
            }
        }
    }
}

private data class Fleck(val angle: Float, val speed: Float, val spin: Float, val color: Color, val w: Float, val h: Float)

private val flecks: List<Fleck> = run {
    val colors = listOf(Color(0xFF2563EB), Leaf, Gold, Navy2, Color(0xFF60A5FA), GoldHi)
    // Deterministic, so the burst is the same every time — authored, not random noise.
    List(22) { i ->
        val t = i / 21f
        Fleck(
            angle = (-168f + t * 156f) + ((i * 37) % 11 - 5),
            speed = 120f + ((i * 53) % 7) * 12f,
            spin = ((i * 71) % 9 - 4) * 140f,
            color = colors[i % colors.size],
            w = if (i % 3 == 0) 3f else 5f,
            h = if (i % 3 == 0) 3f else 2.4f,
        )
    }
}

/** One burst, from the contact point, under gravity; gone by the end. Never loops. */
private fun DrawScope.confetti(origin: Offset, t: Float) {
    if (t <= 0f || t >= 1f) return
    val time = t * 1.3f
    val alpha = (1f - t * t).coerceIn(0f, 1f)
    for (f in flecks) {
        val r = Math.toRadians(f.angle.toDouble())
        val x = origin.x + cos(r).toFloat() * f.speed * time
        val y = origin.y + sin(r).toFloat() * f.speed * time + 0.5f * 190f * time * time
        rotate(f.spin * time, Offset(x, y)) {
            drawRect(f.color.copy(alpha = alpha), Offset(x - f.w / 2, y - f.h / 2), Size(f.w, f.h))
        }
    }
}

// ── Draw ───────────────────────────────────────────────────────────────────────────────────

private fun DrawScope.tiedScene(sport: String, lift: Float, fade: Float, sway: Float) {
    ground(lift, 64f)
    translate(0f, -lift) {
        val a = fade
        val pivot = Offset(120f, 52f)
        // Stand
        drawRoundRect(Navy2.copy(alpha = a), Offset(98f, 126f), Size(44f, 8f), CornerRadius(3f))
        drawRoundRect(Slate.copy(alpha = a), Offset(117.5f, 54f), Size(5f, 73f), CornerRadius(2f))
        // Beam
        val rad = Math.toRadians(sway.toDouble())
        val ends = listOf(-48f, 48f).map { d -> Offset(pivot.x + cos(rad).toFloat() * d, pivot.y + sin(rad).toFloat() * d) }
        drawLine(Navy2.copy(alpha = a), ends[0], ends[1], strokeWidth = 4.5f, cap = StrokeCap.Round)
        drawCircle(Brush.radialGradient(listOf(GoldHi, Gold, GoldLo), pivot, 7f), 6f, pivot, alpha = a)
        // Pans hang plumb whatever the beam does
        for (e in ends) {
            val pan = Offset(e.x, e.y + 30f)
            drawLine(SlateLine.copy(alpha = a), e, Offset(pan.x - 15f, pan.y), 1.2f)
            drawLine(SlateLine.copy(alpha = a), e, Offset(pan.x + 15f, pan.y), 1.2f)
            sportBall(sport, Offset(pan.x, pan.y - 6f), 7f, a)
            val bowl = Path().apply {
                moveTo(pan.x - 17f, pan.y)
                quadraticTo(pan.x, pan.y + 14f, pan.x + 17f, pan.y)
                close()
            }
            drawPath(bowl, Brush.horizontalGradient(listOf(GoldHi, Gold, GoldLo), pan.x - 17f, pan.x + 17f), alpha = a)
            drawLine(GoldLo.copy(alpha = a), Offset(pan.x - 17f, pan.y), Offset(pan.x + 17f, pan.y), 1.4f)
        }
    }
}

// ── Defeat / full time ─────────────────────────────────────────────────────────────────────

private fun DrawScope.restScene(sport: String, lift: Float, fade: Float, sway: Float, sweep: Float, lost: Boolean) {
    ground(lift, 56f)
    if (sport == "cricket") {
        // The stumps are planted — only the ball falls.
        stumps(knocked = lost)
        withTransform({
            translate(0f, -lift)
            rotate(sway * 3f, Offset(80f, GROUND - 9f))
        }) { sportBall(sport, Offset(80f, GROUND - 9f), 9f, fade) }
        return
    }
    if (lost) rewindArc(Offset(120f, 102f), 38f, sweep)
    withTransform({
        translate(0f, -lift)
        rotate(sway * 3f, Offset(120f, GROUND - 17f))
    }) { sportBall(sport, Offset(120f, GROUND - 17f), 17f, fade) }
}

private fun DrawScope.stumps(knocked: Boolean) {
    val xs = listOf(106f, 120f, 134f)
    xs.forEachIndexed { i, x ->
        // The off stump leans a touch when it's been hit.
        val lean = if (knocked && i == 2) 7f else 0f
        rotate(lean, Offset(x, GROUND)) {
            drawRoundRect(Stump, Offset(x - 3.2f, 66f), Size(6.4f, GROUND - 66f), CornerRadius(3f))
            drawRoundRect(Color(0xFFD9C29A), Offset(x + 0.8f, 67f), Size(2.2f, GROUND - 68f), CornerRadius(1f))
            drawRoundRect(Slate.copy(alpha = 0.35f), Offset(x - 3.2f, 66f), Size(6.4f, GROUND - 66f), CornerRadius(3f), style = Stroke(1f))
        }
    }
    // Bails: one still sitting, one on the grass when the wicket fell.
    drawRoundRect(WillowLo, Offset(106f, 62.5f), Size(14f, 3.4f), CornerRadius(1.7f))
    if (knocked) {
        rotate(-18f, Offset(156f, GROUND - 1f)) {
            drawRoundRect(WillowLo, Offset(149f, GROUND - 3f), Size(14f, 3.4f), CornerRadius(1.7f))
        }
    } else {
        drawRoundRect(WillowLo, Offset(120f, 62.5f), Size(14f, 3.4f), CornerRadius(1.7f))
    }
}

/** "Run it back" — a dashed arc that draws itself in, ending in an arrowhead. */
private fun DrawScope.rewindArc(c: Offset, r: Float, t: Float) {
    if (t <= 0f) return
    // Starts on the right and runs anticlockwise over the top — the rewind glyph.
    val start = 20f
    val sweepDeg = -220f * t
    drawArc(
        SlateLine, start, sweepDeg, useCenter = false,
        topLeft = Offset(c.x - r, c.y - r), size = Size(r * 2, r * 2),
        style = Stroke(2f, cap = StrokeCap.Round, pathEffect = PathEffect.dashPathEffect(floatArrayOf(5f, 5f))),
    )
    val endRad = Math.toRadians((start + sweepDeg).toDouble())
    val tip = Offset(c.x + cos(endRad).toFloat() * r, c.y + sin(endRad).toFloat() * r)
    val dir = endRad - PI / 2 // travelling anticlockwise
    val head = Path().apply {
        moveTo(tip.x + cos(dir).toFloat() * 6f, tip.y + sin(dir).toFloat() * 6f)
        lineTo(tip.x + cos(dir + 2.4).toFloat() * 5f, tip.y + sin(dir + 2.4).toFloat() * 5f)
        lineTo(tip.x + cos(dir - 2.4).toFloat() * 5f, tip.y + sin(dir - 2.4).toFloat() * 5f)
        close()
    }
    drawPath(head, SlateLine.copy(alpha = t))
}

// ── Balls, one per sport ───────────────────────────────────────────────────────────────────

private fun DrawScope.sportBall(sport: String, c: Offset, r: Float, a: Float) {
    if (a <= 0f) return
    when (sport) {
        "cricket" -> cricketBall(c, r, a)
        "football", "futsal" -> football(c, r, a)
        "basketball" -> basketball(c, r, a)
        "volleyball" -> volleyball(c, r, a)
        "badminton" -> shuttle(c, r, a)
        "tennis", "pickleball", "padel" -> tennisBall(c, r, a)
        "table_tennis" -> plainBall(c, r, Color(0xFFFB923C), a)
        else -> plainBall(c, r, Color(0xFF3B82F6), a)
    }
}

private fun DrawScope.shade(c: Offset, r: Float, lit: Color, base: Color, a: Float) =
    drawCircle(Brush.radialGradient(listOf(lit, base), Offset(c.x - r * 0.35f, c.y - r * 0.4f), r * 1.5f), r, c, alpha = a)

private fun DrawScope.cricketBall(c: Offset, r: Float, a: Float) {
    shade(c, r, Color(0xFFE0474C), Color(0xFF9B1C22), a)
    val oval = Path().apply { addOval(Rect(c, r)) }
    clipPath(oval) {
        val seam = Color(0xFFF5E6C8).copy(alpha = a)
        for (dx in listOf(-r * 0.12f, r * 0.12f)) {
            drawArc(seam, -60f, 120f, false, Offset(c.x - r * 1.6f + dx, c.y - r * 1.1f), Size(r * 2.2f, r * 2.2f), style = Stroke(r * 0.07f))
        }
        for (i in -3..3) {
            val y = c.y + i * r * 0.26f
            drawLine(seam, Offset(c.x + r * 0.42f, y), Offset(c.x + r * 0.62f, y), r * 0.06f)
        }
    }
    drawCircle(Color.White.copy(alpha = 0.35f * a), r * 0.18f, Offset(c.x - r * 0.38f, c.y - r * 0.42f))
}

private fun DrawScope.football(c: Offset, r: Float, a: Float) {
    shade(c, r, Color.White, Color(0xFFD5DCE6), a)
    val oval = Path().apply { addOval(Rect(c, r)) }
    clipPath(oval) {
        drawPath(polygon(c, r * 0.36f, 5, -90f), Navy2.copy(alpha = a))
        for (k in 0 until 5) {
            val ang = Math.toRadians(-90.0 + k * 72.0)
            val p = Offset(c.x + cos(ang).toFloat() * r * 1.02f, c.y + sin(ang).toFloat() * r * 1.02f)
            drawPath(polygon(p, r * 0.34f, 5, (-90f + k * 72f) + 180f), Navy2.copy(alpha = a))
            val mid = Offset(c.x + cos(ang).toFloat() * r * 0.36f, c.y + sin(ang).toFloat() * r * 0.36f)
            drawLine(Slate.copy(alpha = 0.6f * a), mid, Offset(c.x + cos(ang).toFloat() * r * 0.72f, c.y + sin(ang).toFloat() * r * 0.72f), r * 0.05f)
        }
    }
    drawCircle(Slate.copy(alpha = 0.5f * a), r, c, style = Stroke(r * 0.05f))
}

private fun DrawScope.basketball(c: Offset, r: Float, a: Float) {
    shade(c, r, Color(0xFFF59E54), Color(0xFFC2410C), a)
    val line = Navy2.copy(alpha = 0.8f * a)
    val w = r * 0.07f
    val oval = Path().apply { addOval(Rect(c, r)) }
    clipPath(oval) {
        drawLine(line, Offset(c.x, c.y - r), Offset(c.x, c.y + r), w)
        drawLine(line, Offset(c.x - r, c.y), Offset(c.x + r, c.y), w)
        drawArc(line, -60f, 120f, false, Offset(c.x - r * 2.6f, c.y - r), Size(r * 2f, r * 2f), style = Stroke(w))
        drawArc(line, 120f, 120f, false, Offset(c.x + r * 0.6f, c.y - r), Size(r * 2f, r * 2f), style = Stroke(w))
    }
}

private fun DrawScope.volleyball(c: Offset, r: Float, a: Float) {
    shade(c, r, Color.White, Color(0xFFDDE3EA), a)
    val oval = Path().apply { addOval(Rect(c, r)) }
    clipPath(oval) {
        drawArc(Color(0xFF2563EB).copy(alpha = a), 180f, 180f, true, Offset(c.x - r, c.y - r * 2.2f), Size(r * 2f, r * 2.6f))
        drawArc(Color(0xFFFACC15).copy(alpha = a), 20f, 110f, true, Offset(c.x - r * 0.2f, c.y - r * 0.3f), Size(r * 2.4f, r * 2.4f))
        for (rot in listOf(0f, 120f, 240f)) {
            rotate(rot, c) {
                drawArc(Slate.copy(alpha = 0.6f * a), 200f, 120f, false, Offset(c.x - r * 0.3f, c.y - r * 1.6f), Size(r * 2f, r * 2f), style = Stroke(r * 0.05f))
            }
        }
    }
    drawCircle(Slate.copy(alpha = 0.4f * a), r, c, style = Stroke(r * 0.05f))
}

private fun DrawScope.tennisBall(c: Offset, r: Float, a: Float) {
    shade(c, r, Color(0xFFEAF97A), Color(0xFFA3C21A), a)
    val oval = Path().apply { addOval(Rect(c, r)) }
    clipPath(oval) {
        val seam = Color.White.copy(alpha = 0.9f * a)
        drawArc(seam, -70f, 140f, false, Offset(c.x - r * 2.1f, c.y - r), Size(r * 2f, r * 2f), style = Stroke(r * 0.1f))
        drawArc(seam, 110f, 140f, false, Offset(c.x + r * 0.1f, c.y - r), Size(r * 2f, r * 2f), style = Stroke(r * 0.1f))
    }
}

private fun DrawScope.plainBall(c: Offset, r: Float, color: Color, a: Float) {
    shade(c, r, lerpWhite(color), color, a)
    drawCircle(Color.White.copy(alpha = 0.35f * a), r * 0.2f, Offset(c.x - r * 0.38f, c.y - r * 0.42f))
}

/** A shuttle standing cork-down: the cork sits where a ball would touch the ground. */
private fun DrawScope.shuttle(c: Offset, r: Float, a: Float) {
    val base = c.y + r
    val skirt = Path().apply {
        moveTo(c.x - r * 0.42f, base - r * 0.7f)
        lineTo(c.x - r * 0.95f, base - r * 2.3f)
        lineTo(c.x + r * 0.95f, base - r * 2.3f)
        lineTo(c.x + r * 0.42f, base - r * 0.7f)
        close()
    }
    drawPath(skirt, Color.White.copy(alpha = a))
    drawPath(skirt, Slate.copy(alpha = 0.45f * a), style = Stroke(r * 0.06f))
    for (k in -2..2) {
        drawLine(SlateLine.copy(alpha = a), Offset(c.x + k * r * 0.17f, base - r * 0.7f), Offset(c.x + k * r * 0.38f, base - r * 2.3f), r * 0.05f)
    }
    drawLine(Color(0xFF2563EB).copy(alpha = a), Offset(c.x - r * 0.62f, base - r * 1.25f), Offset(c.x + r * 0.62f, base - r * 1.25f), r * 0.1f)
    drawCircle(Brush.radialGradient(listOf(Color(0xFFFFF7E6), Color(0xFFE7C88F)), Offset(c.x - r * 0.15f, base - r * 0.55f), r * 0.6f), r * 0.48f, Offset(c.x, base - r * 0.48f), alpha = a)
}

// ── Geometry ───────────────────────────────────────────────────────────────────────────────

private fun star(c: Offset, outer: Float, inner: Float): Path = Path().apply {
    for (i in 0 until 10) {
        val rr = if (i % 2 == 0) outer else inner
        val ang = Math.toRadians(-90.0 + i * 36.0)
        val x = c.x + cos(ang).toFloat() * rr
        val y = c.y + sin(ang).toFloat() * rr
        if (i == 0) moveTo(x, y) else lineTo(x, y)
    }
    close()
}

private fun polygon(c: Offset, r: Float, sides: Int, startDeg: Float): Path = Path().apply {
    for (i in 0 until sides) {
        val ang = Math.toRadians(startDeg + i * 360.0 / sides)
        val x = c.x + cos(ang).toFloat() * r
        val y = c.y + sin(ang).toFloat() * r
        if (i == 0) moveTo(x, y) else lineTo(x, y)
    }
    close()
}

private fun quad(p0: Offset, c: Offset, p1: Offset, t: Float): Offset {
    val u = 1 - t
    return Offset(u * u * p0.x + 2 * u * t * c.x + t * t * p1.x, u * u * p0.y + 2 * u * t * c.y + t * t * p1.y)
}

private fun quadTangent(p0: Offset, c: Offset, p1: Offset, t: Float): Offset =
    Offset(2 * (1 - t) * (c.x - p0.x) + 2 * t * (p1.x - c.x), 2 * (1 - t) * (c.y - p0.y) + 2 * t * (p1.y - c.y))

private fun lerpWhite(c: Color): Color = Color(
    red = c.red + (1f - c.red) * 0.45f,
    green = c.green + (1f - c.green) * 0.45f,
    blue = c.blue + (1f - c.blue) * 0.45f,
)
