package com.haraan.app.camera

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.vision.ArPath
import com.haraan.app.vision.BounceLength
import com.haraan.app.vision.Flight3d
import com.haraan.app.vision.LbwProjector
import com.haraan.app.vision.LbwVerdict
import kotlinx.coroutines.launch
import kotlin.math.abs
import kotlin.math.roundToInt

/**
 * THE RESULT ON THE LIVE PICTURE, the way a ball-tracking camera shows it.
 *
 * Within a moment of the ball the path, the channel between the wickets and the stumps lit
 * with the answer appear over the camera's own picture, with the speed, and they stay there
 * — the camera keeps running, the bowler walks back through it — until the next ball takes
 * their place. Nothing covers the screen. The full replay (the delivery at speed, then the
 * 3D stadium) is one tap away on [onReplay], not forced on every ball: taking over the
 * screen after each delivery made the camera feel slow next to an app that never leaves it.
 *
 * Drawn in quickly (the whole path in about half a second, the ball at its head) rather than
 * at the pace of the ball: by the time it appears the ball has already arrived.
 *
 * ONE CARD, READ IN THE ORDER IT HAPPENED. The speed counts up while the path draws, the
 * length is there with it, and the verdict lands last, when the path reaches the stumps —
 * with a knock in the hand at the moment the ball pitches and the verdict's own pattern at
 * the end, so the phone on a tripod can be read without looking at it. (It showed only the
 * speed: the verdict and where it pitched were in the 3D replay alone.)
 */
@Composable
internal fun LiveResultOverlay(
    path: ArPath,
    uprightAspect: Float,
    speedKmh: Double?,
    onReplay: (() -> Unit)?,
    /** The fitted flight, for where it pitched, the verdict, turn and swing. Null: the path alone. */
    flight: Flight3d? = null,
) {
    val context = LocalContext.current
    val reveal = remember(path) { Animatable(0f) }
    val glow = remember(path) { Animatable(0f) }
    val appear = remember(path) { Animatable(0f) }
    val counted = remember(path) { Animatable(0f) }
    val verdictIn = remember(path) { Animatable(0f) }
    val verdict = remember(flight, path) {
        flight?.let { LbwProjector.fromFlight(it)?.verdict } ?: when (path.hitting) {
            true -> LbwVerdict.HITTING
            false -> LbwVerdict.MISSING
            null -> null
        }
    }
    LaunchedEffect(path) {
        launch { appear.animateTo(1f, tween(160, easing = FastOutSlowInEasing)) }
        launch { counted.animateTo(1f, tween(REVEAL_MS, easing = FastOutSlowInEasing)) }
        /*
         * FELT WHERE IT IS SEEN. The path draws at an even pace through the ball's own times,
         * so it reaches the bounce at the bounce's share of the flight: the knock lands there.
         */
        val span = path.endMs - path.startMs
        val pitchedAt = path.bounceMs
            ?.let { ((it - path.startMs) / span).toFloat() }
            ?.takeIf { span > 0 && it > 0.05f && it < 0.98f }
        if (pitchedAt != null) {
            reveal.animateTo(pitchedAt, tween((REVEAL_MS * pitchedAt).roundToInt(), easing = LinearEasing))
            launch { cricketThud(context, Thud.RUN) }
            reveal.animateTo(1f, tween((REVEAL_MS * (1f - pitchedAt)).roundToInt(), easing = LinearEasing))
        } else {
            reveal.animateTo(1f, tween(REVEAL_MS, easing = LinearEasing))
        }
        launch {
            when (verdict) {
                LbwVerdict.HITTING -> cricketThud(context, Thud.WICKET)
                LbwVerdict.MISSING -> cricketThud(context, Thud.MISS)
                LbwVerdict.UMPIRES_CALL -> cricketThud(context, Thud.EXTRA)
                else -> Unit
            }
        }
        launch { verdictIn.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = 420f)) }
        glow.animateTo(1f, tween(220))
    }

    Box(Modifier.fillMaxSize().graphicsLayer { alpha = appear.value }) {
        Canvas(Modifier.fillMaxSize()) {
            val frame = frameRect(size.width, size.height, uprightAspect)
            val upto = path.startMs + (path.endMs - path.startMs) * reveal.value
            drawArPath(path, frame, upto, glow.value)
        }
        val pitchedM = flight?.bounceY?.takeIf { it in 0.0..PITCHED_MAX_M }
        if (speedKmh != null || pitchedM != null || verdict != null) {
            Column(
                Modifier
                    .align(Alignment.TopStart)
                    .statusBarsPadding()
                    .padding(start = 14.dp, top = 64.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xD9101418))
                    .padding(horizontal = 12.dp, vertical = 9.dp),
            ) {
                if (speedKmh != null) {
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            "%.0f".format(speedKmh * counted.value),
                            color = Color.White,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                        )
                        Spacer(Modifier.size(3.dp))
                        Text("km/h", color = Color.White.copy(alpha = 0.72f), fontSize = 11.sp, modifier = Modifier.padding(bottom = 3.dp))
                    }
                }
                if (pitchedM != null) {
                    Text(
                        "%.1f m · %s".format(pitchedM, BounceLength.of(pitchedM).spoken),
                        color = Color.White.copy(alpha = 0.86f),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                    )
                }
                bendLine(flight)?.let { line ->
                    Text(line, color = Color.White.copy(alpha = 0.72f), fontSize = 11.sp)
                }
                val (word, tone) = when (verdict) {
                    LbwVerdict.HITTING -> "HITTING" to Hit
                    LbwVerdict.MISSING -> "MISSING" to Miss
                    LbwVerdict.UMPIRES_CALL -> "UMPIRE'S CALL" to Umpire
                    else -> null to Color.Transparent
                }
                if (word != null) {
                    Spacer(Modifier.size(6.dp))
                    Text(
                        word,
                        color = Color.White,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.ExtraBold,
                        letterSpacing = 1.2.sp,
                        modifier = Modifier
                            .graphicsLayer {
                                alpha = verdictIn.value.coerceIn(0f, 1f)
                                val s = 0.85f + 0.15f * verdictIn.value
                                scaleX = s
                                scaleY = s
                                transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0.5f)
                            }
                            .clip(RoundedCornerShape(6.dp))
                            .background(tone)
                            .padding(horizontal = 7.dp, vertical = 3.dp),
                    )
                }
            }
        }
        if (onReplay != null) {
            Row(
                Modifier
                    .align(Alignment.TopEnd)
                    .statusBarsPadding()
                    .padding(end = 14.dp, top = 64.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color(0xFF2563EB))
                    .clickable(onClick = onReplay)
                    .padding(horizontal = 14.dp, vertical = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Canvas(Modifier.size(10.dp)) {
                    val p = Path().apply {
                        moveTo(0f, 0f)
                        lineTo(size.width, size.height / 2f)
                        lineTo(0f, size.height)
                        close()
                    }
                    drawPath(p, Color.White)
                }
                Spacer(Modifier.size(7.dp))
                Text("Replay", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/** Turn and swing the 3D fit kept, in a line — arrows point the way it moved in the picture. */
private fun bendLine(flight: Flight3d?): String? {
    flight ?: return null
    val parts = listOfNotNull(
        flight.turnDeg.takeIf { flight.turnFitted && abs(it) >= 0.05 }?.let { "Turn %.1f° %s".format(abs(it), if (it > 0) "→" else "←") },
        flight.swingM.takeIf { flight.inAx != 0.0 }?.let { "Swing %.0f cm %s".format(abs(it) * 100, if (it > 0) "→" else "←") },
    )
    return parts.takeIf { it.isNotEmpty() }?.joinToString(" · ")
}

/** The whole path is drawn in this long, the moment the ball is called. */
private const val REVEAL_MS = 520

/** A fitted bounce farther up than this is not a length worth naming. */
private const val PITCHED_MAX_M = 20.0

private val Hit = Color(0xFFE11D2E)
private val Miss = Color(0xFF16A34A)
private val Umpire = Color(0xFFD97706)
