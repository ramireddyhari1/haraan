package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.graphicsLayer
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlin.math.cos
import kotlin.math.sin

/** What the umpire signals for the last delivery — the real signals, as given on a ground. */
enum class UmpireCall(val caption: String) {
    STAND(""), FOUR("FOUR"), SIX("SIX"), OUT("OUT"), WIDE("WIDE"), NO_BALL("NO BALL"), BYE("BYE"), LEG_BYE("LEG BYE");

    companion object {
        /** Ball notation from the over strip: "4", "6", "W", "WD", "1NB", "2LB", "1B", "•", "0", "1"… */
        fun of(ball: String): UmpireCall {
            val b = ball.trim().uppercase()
            return when {
                b.contains("WD") -> WIDE
                b.contains("NB") -> NO_BALL
                b.contains("LB") -> LEG_BYE
                b.startsWith("W") -> OUT
                b.endsWith("B") -> BYE
                b == "4" -> FOUR
                b == "6" -> SIX
                else -> STAND
            }
        }
    }
}

/**
 * Arm directions in degrees, screen space: 0 = pointing right, 90 = straight down,
 * -90 = straight up, 180 = left. "Left" is the arm on the viewer's left.
 */
private data class Pose(val lUpper: Float, val lFore: Float, val rUpper: Float, val rFore: Float)

private val Rest = Pose(lUpper = 100f, lFore = 92f, rUpper = 80f, rFore = 88f)

private fun poseFor(call: UmpireCall): Pose = when (call) {
    UmpireCall.STAND, UmpireCall.LEG_BYE -> Rest
    // The near arm brought across the waist, forearm level — the wave is added on top.
    UmpireCall.FOUR -> Rest.copy(lUpper = 84f, lFore = 2f)
    UmpireCall.SIX -> Pose(lUpper = -114f, lFore = -100f, rUpper = -66f, rFore = -80f)
    UmpireCall.OUT -> Rest.copy(rUpper = -60f, rFore = -86f)
    UmpireCall.BYE -> Rest.copy(rUpper = -62f, rFore = -90f)
    UmpireCall.WIDE -> Pose(lUpper = 178f, lFore = 182f, rUpper = 2f, rFore = -2f)
    UmpireCall.NO_BALL -> Rest.copy(rUpper = 4f, rFore = 0f)
}

// Flat vector style: two tones per material, no outlines, no facial features — a white
// polo with grey sides, a white hat with a navy band, warm orange skin.
private val Skin = Color(0xFFF4A169)
private val SkinShade = Color(0xFFE2874F)
private val Hair = Color(0xFF1E2A44)
private val Shirt = Color(0xFFF8F9FB)
private val ShirtShade = Color(0xFFDADFE6)
private val ShirtLine = Color(0xFFC3CAD4)
private val Hat = Color(0xFFF8F9FB)
private val HatShade = Color(0xFFD6DCE3)
private val HatBand = Color(0xFF1E3A5F)
private val BrandBlue = Color(0xFF2563EB)

/**
 * The umpire for the last ball, from the waist up, standing on a floor line with his
 * reflection beneath it. For every new delivery ([ballKey]) he sinks below the line, rises
 * back up and gives the signal — a FOUR waved across the waist, a SIX with both arms up,
 * OUT with the finger. Between balls he breathes, so the slot never reads as a sticker.
 */
/** The calls the hero card flashes for — the umpire waits for those. */
private val HeldCalls = setOf(UmpireCall.FOUR, UmpireCall.SIX, UmpireCall.OUT)

@Composable
fun UmpireSignal(ball: String, ballKey: String, modifier: Modifier = Modifier, holdMs: Long = 0L) {
    val call = UmpireCall.of(ball)
    val target = poseFor(call)
    // The real three-piece brand H, stitched on the chest.
    val logo = androidx.compose.ui.res.painterResource(com.haraan.app.R.drawable.ic_haraan_ribbon_mark)
    val lu = remember { Animatable(Rest.lUpper) }
    val lf = remember { Animatable(Rest.lFore) }
    val ru = remember { Animatable(Rest.rUpper) }
    val rf = remember { Animatable(Rest.rFore) }
    val lift = remember { Animatable(0f) }
    val finger = remember { Animatable(0f) }
    // 1 = fully below the floor line, 0 = standing on it.
    val rise = remember { Animatable(1f) }
    // The ball already on screen when the board opens plays at once; only a fresh one waits.
    var opened by remember { mutableStateOf(false) }

    LaunchedEffect(ballKey) {
        // Already up from the last ball: sink out of sight first, so each delivery gets its
        // own entrance rather than the arms twitching from one signal to the next.
        if (rise.value < 0.99f) rise.animateTo(1f, tween(200, easing = FastOutSlowInEasing))
        finger.snapTo(0f)
        lu.snapTo(Rest.lUpper); lf.snapTo(Rest.lFore); ru.snapTo(Rest.rUpper); rf.snapTo(Rest.rFore)
        // A 4, 6 or wicket: the card flashes first, and he stays down until it has.
        if (opened && call in HeldCalls && holdMs > 0) delay(holdMs)
        opened = true
        rise.animateTo(0f, spring(dampingRatio = 0.62f, stiffness = 240f))
        delay(60)
        val arm = spring<Float>(dampingRatio = 0.62f, stiffness = Spring.StiffnessMediumLow)
        coroutineScope {
            listOf(lu to target.lUpper, lf to target.lFore, ru to target.rUpper, rf to target.rFore)
                .map { (a, v) -> async { a.animateTo(v, arm) } }.awaitAll()
        }
        when (call) {
            UmpireCall.FOUR -> {
                repeat(3) {
                    lf.animateTo(target.lFore - 26f, tween(230, easing = FastOutSlowInEasing))
                    lf.animateTo(target.lFore + 14f, tween(230, easing = FastOutSlowInEasing))
                }
                lf.animateTo(target.lFore, tween(180))
            }
            UmpireCall.SIX -> repeat(2) {
                lift.animateTo(1f, tween(200, easing = FastOutSlowInEasing))
                lift.animateTo(0f, spring(dampingRatio = 0.5f, stiffness = 500f))
            }
            UmpireCall.OUT -> finger.animateTo(1f, tween(260, easing = FastOutSlowInEasing))
            else -> {}
        }
    }

    val idle = rememberInfiniteTransition(label = "umpireIdle")
    val breath by idle.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1800, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "breath",
    )

    // Offscreen, so the reflection can be faded out with a mask.
    Canvas(modifier.graphicsLayer { compositingStrategy = CompositingStrategy.Offscreen; clip = true }) {
        val floorY = size.height * 0.76f
        // Headroom above the hat, so the spring's overshoot and the SIX lift never clip it.
        val dy = floorY * 0.07f + rise.value * floorY * 1.02f + breath * 0.7f * density - lift.value * 3.5f * density
        val pose = Pose(lu.value, lf.value, ru.value, rf.value)
        // The figure, standing on the floor line.
        clipRect(bottom = floorY) {
            translate(top = dy) { drawFlatUmpire(pose, finger.value, floorY, logo) }
        }
        // His reflection: the same figure flipped about the floor line.
        clipRect(top = floorY) {
            scale(scaleX = 1f, scaleY = -1f, pivot = Offset(size.width / 2f, floorY)) {
                translate(top = dy) { drawFlatUmpire(pose, finger.value, floorY, logo) }
            }
        }
        // Fade: the figure stays solid, the reflection starts faint and dies out.
        drawRect(
            Brush.verticalGradient(
                0f to Color.Black,
                (floorY / size.height) to Color.Black,
                (floorY / size.height) + 0.001f to Color.Black.copy(alpha = 0.32f),
                1f to Color.Transparent,
            ),
            blendMode = BlendMode.DstIn,
        )
        drawLine(
            Color(0xFF0F2A5C).copy(alpha = 0.14f),
            Offset(size.width * 0.08f, floorY), Offset(size.width * 0.92f, floorY),
            strokeWidth = 1f * density,
        )
    }
}

/** A flat capsule between two points. */
private fun DrawScope.capsule(a: Offset, b: Offset, width: Float, color: Color) {
    drawLine(color, a, b, strokeWidth = width, cap = StrokeCap.Round)
}

/** Unit vector along [deg] (screen degrees) and its left-hand normal. */
private fun dirOf(deg: Float): Pair<Offset, Offset> {
    val r = Math.toRadians(deg.toDouble())
    val d = Offset(cos(r).toFloat(), sin(r).toFloat())
    return d to Offset(-d.y, d.x)
}

/**
 * A hand at [wrist] pointing along [deg]. Open: a palm with four fingers and the thumb out
 * to one side, as raised for a SIX. Closed: a fist, with the index finger out for OUT.
 */
private fun DrawScope.hand(wrist: Offset, deg: Float, open: Boolean, finger: Float, thumbSide: Float, unit: Float) {
    // Local frame: fingers point up (-y), so rotate by deg + 90.
    rotate(degrees = deg + 90f, pivot = wrist) {
        val palmW = unit * 1.25f
        val palmH = unit * 1.05f
        val palmTop = wrist.y - palmH * 0.95f
        if (open) {
            val fw = unit * 0.27f
            val gaps = listOf(-0.36f, -0.12f, 0.12f, 0.36f)
            val lens = listOf(0.62f, 0.78f, 0.74f, 0.58f)
            gaps.forEachIndexed { i, g ->
                val x = wrist.x + g * palmW
                capsule(Offset(x, palmTop + unit * 0.2f), Offset(x + g * unit * 0.25f, palmTop - unit * lens[i]), fw, Skin)
            }
            // Thumb, angled out from the lower palm.
            capsule(
                Offset(wrist.x + thumbSide * palmW * 0.35f, wrist.y - palmH * 0.35f),
                Offset(wrist.x + thumbSide * palmW * 0.85f, wrist.y - palmH * 0.85f),
                unit * 0.3f, Skin,
            )
            drawRoundRect(Skin, topLeft = Offset(wrist.x - palmW / 2f, palmTop), size = Size(palmW, palmH), cornerRadius = CornerRadius(unit * 0.35f))
            // Crease where the fingers meet the palm.
            drawLine(SkinShade, Offset(wrist.x - palmW * 0.32f, palmTop + unit * 0.2f), Offset(wrist.x + palmW * 0.32f, palmTop + unit * 0.2f), strokeWidth = unit * 0.07f, cap = StrokeCap.Round)
        } else {
            drawRoundRect(Skin, topLeft = Offset(wrist.x - palmW / 2f, wrist.y - palmH * 0.9f), size = Size(palmW, palmH * 0.95f), cornerRadius = CornerRadius(unit * 0.45f))
            drawLine(SkinShade, Offset(wrist.x - palmW * 0.3f, wrist.y - palmH * 0.55f), Offset(wrist.x + palmW * 0.3f, wrist.y - palmH * 0.55f), strokeWidth = unit * 0.07f, cap = StrokeCap.Round)
            if (finger > 0f) {
                val x = wrist.x - palmW * 0.22f
                capsule(Offset(x, wrist.y - palmH * 0.8f), Offset(x, wrist.y - palmH * 0.8f - unit * 1.1f * finger), unit * 0.3f, Skin)
            }
        }
    }
}

private fun DrawScope.drawFlatUmpire(pose: Pose, fingerUp: Float, floorY: Float, logo: androidx.compose.ui.graphics.painter.Painter) {
    val w = size.width
    val fh = floorY
    val cx = w / 2f

    // Proportions from the reference: shoulders a good deal wider than the hat brim, the
    // head small on a short neck, short wide sleeves, and the floor cutting him at the waist.
    val shoulderHalf = w * 0.30f
    val neckHalf = w * 0.055f
    val neckBaseY = fh * 0.56f
    val shoulderY = fh * 0.60f
    val waistHalf = w * 0.25f

    // ── Neck ──
    drawRect(SkinShade, topLeft = Offset(cx - neckHalf, fh * 0.46f), size = Size(neckHalf * 2f, neckBaseY - fh * 0.46f + fh * 0.04f))

    // ── Polo body: grey down both sides, white across the front ──
    fun body(inset: Float) = Path().apply {
        moveTo(cx - neckHalf * 1.4f, neckBaseY)
        quadraticBezierTo(cx - shoulderHalf * 0.6f, neckBaseY + fh * 0.005f, cx - shoulderHalf + w * 0.05f + inset, shoulderY)
        lineTo(cx - shoulderHalf + w * 0.04f + inset, shoulderY + fh * 0.30f)
        lineTo(cx - waistHalf + inset, fh * 1.1f)
        lineTo(cx + waistHalf - inset, fh * 1.1f)
        lineTo(cx + shoulderHalf - w * 0.04f - inset, shoulderY + fh * 0.30f)
        lineTo(cx + shoulderHalf - w * 0.05f - inset, shoulderY)
        quadraticBezierTo(cx + shoulderHalf * 0.6f, neckBaseY + fh * 0.005f, cx + neckHalf * 1.4f, neckBaseY)
        close()
    }
    drawPath(body(0f), ShirtShade)
    drawPath(body(w * 0.045f), Shirt)

    // ── Arms ──
    val upperLen = w * 0.20f
    val foreLen = w * 0.24f
    val armW = w * 0.10f
    val sleeveR = w * 0.085f
    val unit = w * 0.07f
    val jointL = Offset(cx - shoulderHalf + sleeveR, shoulderY + fh * 0.05f)
    val jointR = Offset(cx + shoulderHalf - sleeveR, shoulderY + fh * 0.05f)

    fun skin(joint: Offset, upperDeg: Float, foreDeg: Float, finger: Float, thumbSide: Float) {
        val (u, _) = dirOf(upperDeg)
        val (f, _) = dirOf(foreDeg)
        val elbow = joint + u * upperLen
        val wrist = elbow + f * foreLen
        capsule(joint, elbow, armW, Skin)
        capsule(elbow, wrist, armW * 0.88f, Skin)
        // Raised or held out: an open hand. Hanging or across the body: a fist.
        val open = u.y < 0.4f && finger == 0f
        hand(wrist, foreDeg, open = open, finger = finger, thumbSide = thumbSide, unit = unit)
    }

    // Short sleeve: the round of the shoulder plus a wide tube, cut square at the hem,
    // grey on the side under the arm like the body.
    fun sleeve(joint: Offset, upperDeg: Float, shadeSide: Float) {
        val (u, n) = dirOf(upperDeg)
        val hem = joint + u * (upperLen * 0.55f)
        val hemHalf = sleeveR * 1.02f
        fun shape(half0: Float, half1: Float, shift: Float) = Path().apply {
            val s = n * shift
            moveTo(joint.x + n.x * half0 + s.x, joint.y + n.y * half0 + s.y)
            lineTo(hem.x + n.x * half1 + s.x, hem.y + n.y * half1 + s.y)
            lineTo(hem.x - n.x * half1 + s.x, hem.y - n.y * half1 + s.y)
            lineTo(joint.x - n.x * half0 + s.x, joint.y - n.y * half0 + s.y)
            close()
        }
        drawCircle(Shirt, radius = sleeveR, center = joint)
        drawPath(shape(sleeveR, hemHalf, 0f), ShirtShade)
        drawPath(shape(sleeveR * 0.72f, hemHalf * 0.72f, -shadeSide * sleeveR * 0.28f), Shirt)
    }

    skin(jointL, pose.lUpper, pose.lFore, 0f, thumbSide = 1f)
    skin(jointR, pose.rUpper, pose.rFore, fingerUp, thumbSide = -1f)
    sleeve(jointL, pose.lUpper, shadeSide = 1f)
    sleeve(jointR, pose.rUpper, shadeSide = -1f)

    // ── Polo collar and placket ──
    val collarY = neckBaseY - fh * 0.005f
    // The neck's V into the placket.
    drawPath(Path().apply {
        moveTo(cx - neckHalf * 1.1f, collarY); lineTo(cx, collarY + fh * 0.07f); lineTo(cx + neckHalf * 1.1f, collarY); close()
    }, SkinShade)
    // Placket with two buttons.
    val placketTop = collarY + fh * 0.065f
    drawRect(ShirtLine, topLeft = Offset(cx - w * 0.028f, placketTop), size = Size(w * 0.056f, fh * 0.17f))
    drawRect(Shirt, topLeft = Offset(cx - w * 0.022f, placketTop), size = Size(w * 0.044f, fh * 0.165f))
    drawCircle(ShirtLine, radius = w * 0.011f, center = Offset(cx, placketTop + fh * 0.05f))
    drawCircle(ShirtLine, radius = w * 0.011f, center = Offset(cx, placketTop + fh * 0.12f))
    // Folded collar points, each with a grey shadow beneath it.
    fun collarPoint(side: Float) {
        val p = Path().apply {
            moveTo(cx + side * neckHalf * 1.25f, collarY - fh * 0.015f)
            lineTo(cx + side * w * 0.135f, collarY + fh * 0.03f)
            lineTo(cx + side * w * 0.03f, collarY + fh * 0.085f)
            lineTo(cx + side * neckHalf * 0.55f, collarY + fh * 0.01f)
            close()
        }
        translate(top = fh * 0.012f) { drawPath(p, ShirtShade) }
        drawPath(p, Shirt)
    }
    collarPoint(-1f)
    collarPoint(1f)

    // ── Haraan H on the left chest (the viewer's right) ──
    val logoH = fh * 0.085f
    val logoW = logoH * (37.44f / 43f)
    translate(left = cx + w * 0.10f, top = shoulderY + fh * 0.085f) {
        with(logo) { draw(Size(logoW, logoH), colorFilter = androidx.compose.ui.graphics.ColorFilter.tint(BrandBlue)) }
    }

    // ── Head: hair at the sides, ears, a plain face ──
    val faceW = w * 0.21f
    val faceTop = fh * 0.24f
    val faceH = fh * 0.26f
    drawRoundRect(Hair, topLeft = Offset(cx - faceW * 0.62f, faceTop), size = Size(faceW * 1.24f, faceH * 0.62f), cornerRadius = CornerRadius(faceW * 0.3f))
    drawOval(Skin, topLeft = Offset(cx - faceW / 2f - w * 0.03f, faceTop + faceH * 0.40f), size = Size(w * 0.06f, faceH * 0.26f))
    drawOval(Skin, topLeft = Offset(cx + faceW / 2f - w * 0.03f, faceTop + faceH * 0.40f), size = Size(w * 0.06f, faceH * 0.26f))
    drawRoundRect(
        Skin,
        topLeft = Offset(cx - faceW / 2f, faceTop + faceH * 0.08f),
        size = Size(faceW, faceH * 0.92f),
        cornerRadius = CornerRadius(faceW * 0.5f, faceH * 0.45f),
    )

    // ── Hat: a crown with a navy band on a wide, gently curved brim ──
    val brimY = faceTop + faceH * 0.16f
    val brimHalf = w * 0.31f
    fun brim(dy: Float) = Path().apply {
        moveTo(cx - brimHalf, brimY + fh * 0.02f + dy)
        quadraticBezierTo(cx, brimY - fh * 0.05f + dy, cx + brimHalf, brimY + fh * 0.02f + dy)
        quadraticBezierTo(cx, brimY + fh * 0.055f + dy, cx - brimHalf, brimY + fh * 0.02f + dy)
        close()
    }
    drawPath(brim(fh * 0.016f), HatShade)
    drawPath(brim(0f), Hat)
    val crownBottom = brimY - fh * 0.005f
    val crownTop = brimY - fh * 0.15f
    val crownHalfB = w * 0.14f
    val crownHalfT = w * 0.115f
    val crown = Path().apply {
        moveTo(cx - crownHalfB, crownBottom)
        lineTo(cx - crownHalfT, crownTop + fh * 0.03f)
        quadraticBezierTo(cx - crownHalfT, crownTop, cx - crownHalfT + w * 0.03f, crownTop)
        lineTo(cx + crownHalfT - w * 0.03f, crownTop)
        quadraticBezierTo(cx + crownHalfT, crownTop, cx + crownHalfT, crownTop + fh * 0.03f)
        lineTo(cx + crownHalfB, crownBottom)
        close()
    }
    drawPath(crown, Hat)
    // Shaded right side of the crown.
    clipRect(left = cx + crownHalfT * 0.45f) { drawPath(crown, HatShade) }
    // Navy band round the base of the crown.
    drawPath(Path().apply {
        val t = crownBottom - fh * 0.045f
        moveTo(cx - crownHalfB, crownBottom)
        lineTo(cx - crownHalfB + w * 0.008f, t)
        lineTo(cx + crownHalfB - w * 0.008f, t)
        lineTo(cx + crownHalfB, crownBottom)
        close()
    }, HatBand)
}
