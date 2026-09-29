package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.spring
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.rotate
import kotlin.math.cos
import kotlin.math.sin

/**
 * The strokes a scorer can name — and how each one is drawn.
 *
 * Every stroke is a pose of one figure: a batter built from a head, a torso, two legs,
 * the arms and the bat, each segment set at an angle. Drawn that way rather than as sixteen
 * pictures, the figures share one hand, one weight and one colour, and — the point — any
 * pose can be turned into any other. A figure in stance can play its shot in front of you.
 *
 * Keys match the server's CricketShots::TYPES exactly; the server ignores anything else.
 */
enum class ShotKind(val key: String, val label: String, val pose: BatterPose) {
    STRAIGHT_DRIVE("straight_drive", "Straight drive", BatterPose(150f, 42f, 6f, -34f, -10f, 118f, 158f, 186f)),
    COVER_DRIVE("cover_drive", "Cover drive", BatterPose(138f, 52f, 12f, -40f, -16f, 100f, 132f, 162f)),
    ON_DRIVE("on_drive", "On drive", BatterPose(156f, 30f, 2f, -30f, -6f, 132f, 172f, 200f)),
    SQUARE_DRIVE("square_drive", "Square drive", BatterPose(148f, 46f, 10f, -36f, -12f, 86f, 104f, 118f)),
    CUT("cut", "Cut", BatterPose(176f, 6f, 0f, -28f, -6f, 72f, 84f, 92f)),
    PULL("pull", "Pull", BatterPose(192f, 22f, 8f, -22f, -4f, -70f, -92f, -104f)),
    HOOK("hook", "Hook", BatterPose(198f, 18f, 4f, -26f, -8f, -118f, -150f, -140f)),
    FLICK("flick", "Flick", BatterPose(164f, 28f, 2f, -24f, -6f, 36f, -18f, -58f)),
    LEG_GLANCE("leg_glance", "Leg glance", BatterPose(170f, 20f, 4f, -16f, 0f, 24f, 2f, -24f)),
    SWEEP("sweep", "Sweep", BatterPose(128f, 82f, 2f, 12f, -88f, 64f, 58f, 70f)),
    SLOG_SWEEP("slog_sweep", "Slog sweep", BatterPose(134f, 80f, 2f, 12f, -88f, -26f, -80f, -140f)),
    REVERSE_SWEEP("reverse_sweep", "Reverse sweep", BatterPose(132f, 80f, 2f, 12f, -88f, 96f, 128f, 118f)),
    SCOOP("scoop", "Scoop", BatterPose(118f, 84f, 4f, 14f, -86f, 80f, 150f, -160f)),
    LOFTED("lofted", "Lofted shot", BatterPose(162f, 36f, 4f, -32f, -10f, 142f, 176f, 206f)),
    SLOG("slog", "Slog", BatterPose(176f, 22f, 2f, -36f, -12f, -128f, -160f, -150f)),
    EDGE("edge", "Edge", BatterPose(158f, 36f, 6f, -26f, -8f, 64f, 96f, 58f));

    companion object {
        fun of(key: String?): ShotKind? = entries.firstOrNull { it.key == key }

        /**
         * Most likely strokes for a region, first — so the scorer's thumb is usually already
         * over the right one. Zones: 0 straight, 1 cover, 2 point, 3 third man, 4 fine leg,
         * 5 square leg, 6 mid-wicket, 7 long-on (a right-hander's field).
         */
        fun likelyFor(zone: Int?): List<ShotKind> {
            val first = when (zone) {
                0 -> listOf(STRAIGHT_DRIVE, LOFTED, ON_DRIVE)
                1 -> listOf(COVER_DRIVE, LOFTED, SQUARE_DRIVE)
                2 -> listOf(CUT, SQUARE_DRIVE, REVERSE_SWEEP)
                3 -> listOf(EDGE, CUT, REVERSE_SWEEP)
                4 -> listOf(LEG_GLANCE, SCOOP, HOOK)
                5 -> listOf(PULL, SWEEP, HOOK)
                6 -> listOf(PULL, FLICK, SLOG_SWEEP)
                7 -> listOf(ON_DRIVE, SLOG, LOFTED)
                else -> emptyList()
            }
            return first + entries.filter { it !in first }
        }
    }
}

/**
 * One pose, as directions in degrees: 0 points straight down, 90 toward the bowler (the
 * figure's right), 180 straight up, negative toward the keeper. Legs are thigh then shin;
 * the arms are upper arm then forearm, with the bat carrying on from the hands.
 */
data class BatterPose(
    val torso: Float,
    val frontThigh: Float,
    val frontShin: Float,
    val backThigh: Float,
    val backShin: Float,
    val upperArm: Float,
    val forearm: Float,
    val bat: Float,
) {
    fun lerp(to: BatterPose, t: Float) = BatterPose(
        torso + (to.torso - torso) * t,
        frontThigh + (to.frontThigh - frontThigh) * t,
        frontShin + (to.frontShin - frontShin) * t,
        backThigh + (to.backThigh - backThigh) * t,
        backShin + (to.backShin - backShin) * t,
        upperArm + (to.upperArm - upperArm) * t,
        forearm + (to.forearm - forearm) * t,
        bat + (to.bat - bat) * t,
    )

    companion object {
        /** Side-on at the crease, bat grounded behind the back foot. */
        val STANCE = BatterPose(166f, 8f, -2f, -10f, 2f, 22f, 58f, -14f)
    }
}

private fun dir(deg: Float): Offset {
    val r = Math.toRadians(deg.toDouble())
    return Offset(sin(r).toFloat(), cos(r).toFloat())
}

/**
 * Draw [pose] fitted into this canvas, feet on the floor, in [ink] with a willow bat.
 * Limbs are round-capped strokes — a sports pictogram's hand, not a photo cut-out.
 */
fun DrawScope.drawBatter(pose: BatterPose, ink: Color, willow: Color = Color(0xFFE2C98F)) {
    // Build the figure in unit space first, then fit it.
    val torsoL = 3.0f; val thigh = 2.2f; val shin = 2.2f; val upper = 1.5f; val fore = 1.35f
    val bat = 3.3f; val headR = 0.62f
    val hip = Offset(0f, 0f)
    val neck = hip + dir(pose.torso) * torsoL
    val head = neck + dir(pose.torso) * (headR + 0.25f)
    val shoulder = hip + dir(pose.torso) * (torsoL * 0.9f)
    val fKnee = hip + dir(pose.frontThigh) * thigh
    val fFoot = fKnee + dir(pose.frontShin) * shin
    val bKnee = hip + dir(pose.backThigh) * thigh
    val bFoot = bKnee + dir(pose.backShin) * shin
    val elbow = shoulder + dir(pose.upperArm) * upper
    val hands = elbow + dir(pose.forearm) * fore
    val batTip = hands + dir(pose.bat) * bat
    val batNeck = hands + dir(pose.bat) * (bat * 0.28f)

    val pts = listOf(head + Offset(-headR, -headR), head + Offset(headR, headR), fFoot, bFoot, fKnee, bKnee, hands, batTip, elbow, hip)
    val minX = pts.minOf { it.x }; val maxX = pts.maxOf { it.x }
    val minY = pts.minOf { it.y }; val maxY = pts.maxOf { it.y }
    val pad = size.minDimension * 0.07f
    // One scale for every pose — a batter standing tall, so a crouched sweep is drawn
    // SMALLER than a drive, the way it is on the field, rather than blown up to fill
    // the box. Only a pose that would not fit anyway is shrunk.
    val scale = minOf(
        (size.height - pad * 2) / 9.6f,
        (size.width - pad * 2) / (maxX - minX),
        (size.height - pad * 2) / (maxY - minY),
    )
    val offX = (size.width - (maxX - minX) * scale) / 2f - minX * scale
    val offY = size.height - pad - maxY * scale
    fun p(o: Offset) = Offset(o.x * scale + offX, o.y * scale + offY)

    val limb = 0.8f * scale
    fun seg(a: Offset, b: Offset, w: Float, c: Color = ink) =
        drawLine(c, p(a), p(b), w, StrokeCap.Round)

    // Ground shadow under both feet.
    val floor = maxOf(p(fFoot).y, p(bFoot).y) + limb * 0.35f
    val l = minOf(p(fFoot).x, p(bFoot).x) - limb
    val r = maxOf(p(fFoot).x, p(bFoot).x) + limb
    drawOval(Color.Black.copy(alpha = 0.08f), Offset(l, floor - limb * 0.25f), Size(r - l, limb * 0.5f))

    // Back leg behind, a shade lighter, so the figure has depth.
    val behind = lerpColor(ink, Color.White, 0.42f)
    seg(hip, bKnee, limb * 1.05f, behind); seg(bKnee, bFoot, limb * 1.1f, behind)
    // Torso: broad at the chest, narrower at the hip.
    seg(hip, shoulder, limb * 1.7f)
    seg(hip + dir(pose.torso) * 0.2f, hip, limb * 1.35f)
    // Front leg, padded below the knee.
    seg(hip, fKnee, limb * 1.05f); seg(fKnee, fFoot, limb * 1.15f)
    // The bat: grip in the side's ink, blade in willow, broad at the toe.
    seg(hands, batNeck, limb * 0.42f)
    seg(batNeck, batTip, limb * 1.0f, willow)
    seg(batNeck + dir(pose.bat) * 0.2f, batTip, limb * 0.22f, Color.White.copy(alpha = 0.5f))
    // Arms over the handle, gloves on the grip.
    seg(shoulder, elbow, limb * 0.82f); seg(elbow, hands, limb * 0.74f)
    drawCircle(lerpColor(ink, Color.White, 0.25f), limb * 0.5f, p(hands))
    // Helmet: head, a peak toward the bowler and the grille below it.
    drawCircle(ink, headR * scale, p(head))
    val facing = dir(pose.torso + 90f)
    drawLine(ink, p(head + facing * (headR * 0.2f) + dir(pose.torso) * (headR * 0.35f)), p(head + facing * (headR * 1.45f) + dir(pose.torso) * (headR * 0.2f)), limb * 0.4f, StrokeCap.Round)
    drawLine(
        Color.White.copy(alpha = 0.6f),
        p(head + facing * (headR * 0.45f) - dir(pose.torso) * (headR * 0.2f)),
        p(head + facing * (headR * 0.95f) - dir(pose.torso) * (headR * 0.45f)),
        limb * 0.14f, StrokeCap.Round,
    )
}

private fun lerpColor(a: Color, b: Color, t: Float) = androidx.compose.ui.graphics.lerp(a, b, t)

/**
 * A shot figure that plays its stroke: it opens in stance and swings into [kind] on a
 * spring, and swings again every time [replay] changes.
 */
@Composable
fun ShotFigure(kind: ShotKind, ink: Color, modifier: Modifier = Modifier, replay: Int = 0) {
    val t = remember(kind) { Animatable(0f) }
    LaunchedEffect(kind, replay) {
        t.snapTo(0f)
        t.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = 170f))
    }
    Canvas(modifier) { drawBatter(BatterPose.STANCE.lerp(kind.pose, t.value), ink) }
}
