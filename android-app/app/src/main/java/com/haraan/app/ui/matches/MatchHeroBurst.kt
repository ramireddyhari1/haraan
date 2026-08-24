package com.haraan.app.ui.matches

import android.content.Context
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.material3.Text
import kotlin.math.cos
import kotlin.math.sin

/**
 * The moment a boundary or a wicket lands, in the middle of the hero card.
 *
 * The card used to swap one character for another: a six and a dot ball were the same
 * event with different ink. Nothing moved, so nothing felt like anything. This is the
 * flash — the numeral punches in oversized behind a burst of speed lines, holds, and
 * settles back into the resting last-ball glyph, with a haptic shaped to the event.
 *
 * Three rules it is built on:
 *
 *  1. **Only the big three fire.** Six, four, wicket. A dot ball that flashed would make
 *     the flash mean nothing, and a phone that buzzed every delivery would be turned off
 *     inside an over.
 *  2. **A wicket is not a celebration.** It gets its own motion (a hard, short jab rather
 *     than a bloom) and its own haptic, so you can tell a six from a wicket by feel
 *     without looking at the screen.
 *  3. **Once per ball, ever.** The screen refetches on a poll and on every realtime
 *     nudge; keyed on the VALUE, a six would re-fire its fanfare every few seconds for a
 *     ball bowled minutes ago. It is keyed on the ball's identity, and it deliberately
 *     stays silent on first composition — opening a finished match must not greet you by
 *     celebrating its last delivery.
 */

/** What kind of moment this is. Everything else in the over is not a moment. */
enum class BurstKind { SIX, FOUR, WICKET }

private fun kindFor(ball: String): BurstKind? = when (ball.trim().uppercase()) {
    "6" -> BurstKind.SIX
    "4" -> BurstKind.FOUR
    "W" -> BurstKind.WICKET
    else -> null
}

private fun colorFor(kind: BurstKind): Color = when (kind) {
    BurstKind.SIX -> CrexColors.SixBall
    BurstKind.FOUR -> CrexColors.FourBall
    BurstKind.WICKET -> CrexColors.WicketBall
}

private fun glyphFor(kind: BurstKind): String = when (kind) {
    BurstKind.SIX -> "6"
    BurstKind.FOUR -> "4"
    BurstKind.WICKET -> "OUT"
}

/**
 * One moment worth marking. Carries the ball it came from, which is what makes two
 * identical events in a row two events.
 */
data class BurstEvent(val kind: BurstKind, val ballKey: String)

/**
 * Fires once when [ballKey] changes to a ball worth marking.
 *
 * [ballKey] must identify the DELIVERY, not its value — two sixes in an over are two
 * moments, and one six refetched five times is one. The returned event carries the key
 * for the same reason: a caller keyed on the KIND alone would sit still through the
 * second of back-to-back sixes, because "SIX" then "SIX" is no change at all.
 */
@Composable
fun rememberBurst(ballKey: String, lastBall: String, isLive: Boolean): BurstEvent? {
    // Seeded with the key already on screen, so the first composition is never a trigger.
    var seen by remember { mutableStateOf(ballKey) }
    var pending by remember { mutableStateOf<BurstEvent?>(null) }

    LaunchedEffect(ballKey) {
        if (ballKey != seen) {
            seen = ballKey
            pending = if (isLive) kindFor(lastBall)?.let { BurstEvent(it, ballKey) } else null
        }
    }

    return pending
}

/**
 * The burst itself: flash, oversized numeral, speed lines, then gone.
 *
 * Drawn as an overlay so it can spill past the centre column without pushing any of the
 * hero's layout around — a score that jumped sideways every six would be worse than no
 * animation at all.
 */
@Composable
fun BoundaryBurst(kind: BurstKind?, modifier: Modifier = Modifier, onFinished: () -> Unit = {}) {
    if (kind == null) return

    val context = LocalContext.current
    val progress = remember(kind) { Animatable(0f) }

    LaunchedEffect(kind) {
        thump(context, kind)
        // One second, and the shape of it matters more than the length: the punch lands
        // almost instantly (a boundary is sudden), it holds while it is read, then it
        // leaves without ceremony.
        progress.animateTo(1f, animationSpec = tween(durationMillis = 1000, easing = LinearEasing))
        onFinished()
    }

    val p = progress.value
    val accent = colorFor(kind)

    // Phases, as fractions of the second: 0-.14 punch in, .14-.70 hold, .70-1 leave.
    val punch = (p / 0.14f).coerceIn(0f, 1f)
    val exit = ((p - 0.70f) / 0.30f).coerceIn(0f, 1f)

    // Overshoot on the way in, settle, then shrink away. A wicket lands harder and
    // smaller — a jab, not a bloom.
    val peak = if (kind == BurstKind.WICKET) 1.06f else 1.18f
    val scale = when {
        punch < 1f -> 0.45f + (peak - 0.45f) * easeOutBack(punch)
        else -> peak - (peak - 1f) * ((p - 0.14f) / 0.56f).coerceIn(0f, 1f)
    } * (1f - 0.35f * exit)

    val alpha = (1f - exit) * (0.25f + 0.75f * punch)

    Box(modifier = modifier, contentAlignment = Alignment.Center) {
        // Speed lines. They start short and close in, then shoot outward and thin out —
        // the streaks read as force leaving the point of contact.
        Canvas(Modifier.fillMaxSize()) {
            val cx = size.width / 2f
            val cy = size.height / 2f
            val spread = (size.minDimension / 2f)
            val travel = easeOutCubic(p.coerceIn(0f, 0.8f) / 0.8f)

            val count = if (kind == BurstKind.WICKET) 10 else 14
            repeat(count) { i ->
                // Deterministic, not random: the same ball must draw the same burst on
                // every recomposition, or the lines jitter while the animation runs.
                val angle = (i.toFloat() / count) * (2f * Math.PI).toFloat() +
                    (if (kind == BurstKind.WICKET) 0.35f else 0f)
                val jitter = 0.55f + ((i * 37) % 45) / 100f

                val inner = spread * (0.34f + 0.55f * travel) * jitter
                val outer = inner + spread * 0.30f * (1f - travel) * jitter + 4f

                val a = (1f - travel) * 0.85f * (1f - exit)
                if (a <= 0.02f) return@repeat

                drawLine(
                    color = accent.copy(alpha = a),
                    start = Offset(cx + cos(angle) * inner, cy + sin(angle) * inner),
                    end = Offset(cx + cos(angle) * outer, cy + sin(angle) * outer),
                    strokeWidth = (if (kind == BurstKind.WICKET) 4.5f else 3.5f) * (1f - 0.5f * travel),
                    cap = StrokeCap.Round,
                )
            }

            // The flash: a wash of the event colour that blows out fast and clears fast.
            // Short enough to register as a flash rather than a colour change.
            val flash = (1f - (p / 0.22f)).coerceIn(0f, 1f)
            if (flash > 0f) {
                drawCircle(
                    color = accent.copy(alpha = 0.30f * flash),
                    radius = spread * (0.55f + 0.85f * (1f - flash)),
                    center = Offset(cx, cy),
                )
            }
        }

        // The numeral, oversized. Two layers, as the resting glyph already does: a soft
        // ghost behind for weight, the solid mark on top.
        Box(contentAlignment = Alignment.Center) {
            Text(
                glyphFor(kind),
                color = accent.copy(alpha = 0.18f * alpha),
                fontSize = if (kind == BurstKind.WICKET) 46.sp else 96.sp,
                fontWeight = FontWeight.Black,
                modifier = Modifier.graphicsLayer {
                    scaleX = scale * 1.25f; scaleY = scale * 1.25f
                },
            )
            Text(
                glyphFor(kind),
                color = accent.copy(alpha = alpha),
                fontSize = if (kind == BurstKind.WICKET) 34.sp else 72.sp,
                fontWeight = FontWeight.Black,
                modifier = Modifier.graphicsLayer { scaleX = scale; scaleY = scale },
            )
        }
    }
}

/** Overshoot on the way in — the numeral arrives past its mark and comes back. */
private fun easeOutBack(t: Float): Float {
    val c1 = 1.70158f
    val c3 = c1 + 1f
    val x = t - 1f
    return 1f + c3 * x * x * x + c1 * x * x
}

private fun easeOutCubic(t: Float): Float {
    val x = 1f - t
    return 1f - x * x * x
}

/**
 * The physical half — delegated, deliberately.
 *
 * This used to carry its own vibrator code. The keypad in ScoringScreen then needed the
 * same three signatures, and two copies of a haptic is exactly how the last bug here
 * happened: the scoring ribbon kept a confirm tick of its own and fired it on the same
 * ball, milliseconds apart, which reads as one smeared buzz rather than two deliberate
 * ones. A six must feel identical whether you are watching it or entering it, so both
 * sides call the same function. See [cricketThud] for why the signature is carried by the
 * number of knocks rather than their strength.
 */
private suspend fun thump(context: Context, kind: BurstKind) = cricketThud(
    context,
    when (kind) {
        BurstKind.SIX -> Thud.SIX
        BurstKind.FOUR -> Thud.FOUR
        BurstKind.WICKET -> Thud.WICKET
    },
)
