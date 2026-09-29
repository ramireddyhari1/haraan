package com.haraan.app.ui.rewards

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.Feel
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

internal val CardShape = RoundedCornerShape(20.dp)

internal fun Modifier.card(): Modifier =
    clip(CardShape).background(Board.Surface).border(1.dp, Board.Line, CardShape)

/**
 * "I earned something" — framed as what it really is: a scorecard waiting for two
 * signatures. Each captain gets a signing line; a captain who has signed shows their
 * signature inking itself in, one who hasn't shows an empty "×" line. Once the result is
 * verified the XP counts up and a VERIFIED stamp comes down on it.
 *
 * Everything written here is a fact the server sent: who signed, when it locks, the XP,
 * the bonus points. No projected numbers, no status pills.
 */
@Composable
internal fun XpPanel(xp: XpUi, verification: VerificationUi?, countUp: Boolean, onRemind: () -> Unit) {
    Column(Modifier.fillMaxWidth().card().padding(20.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Eyebrow("Match XP", modifier = Modifier.weight(1f))
            if (xp.state != XpState.NOT_ELIGIBLE) {
                Text(
                    if (xp.ranked) "Ranked match" else "Casual match",
                    color = if (xp.ranked) Board.Blue else Board.InkFaint,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 12.sp,
                )
            }
        }
        Spacer(Modifier.height(10.dp))

        when (xp.state) {
            XpState.SETTLED -> SettledXp(xp, countUp)
            XpState.NOT_ELIGIBLE -> Text(
                "Private matches don't earn leaderboard XP.",
                color = Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontSize = 14.sp,
            )
            XpState.PENDING -> PendingHeadline(xp, verification)
        }

        verification?.let {
            Spacer(Modifier.height(18.dp))
            SignOffSlip(it, xp, play = countUp)
            val who = when {
                it.needsOrganiser -> null
                it.yourCaptain && !it.opposition -> xp.oppTeam.ifBlank { "the other captain" }
                !it.yourCaptain && it.opposition -> xp.yourTeam.ifBlank { "your captain" }
                !it.yourCaptain && !it.opposition -> "both captains"
                else -> null
            }
            who?.let { name ->
                Spacer(Modifier.height(14.dp))
                ActionButton("Nudge $name", primary = false, icon = Icons.Outlined.Share, compact = true, onClick = onRemind)
            }
        }

        if (xp.bonus > 0) {
            Spacer(Modifier.height(18.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Board.Line))
            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("+${xp.bonus}", color = Board.Ink, fontFamily = PlusJakartaSans, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp)
                Text(
                    " bonus points", color = Board.InkMuted, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Medium, fontSize = 13.sp,
                    modifier = Modifier.padding(bottom = 1.dp),
                )
                Spacer(Modifier.weight(1f))
                if (xp.bonusTotal > 0) {
                    Text(
                        "${xp.bonusTotal} banked", color = Board.InkFaint, fontFamily = PlusJakartaSans, fontSize = 12.sp,
                        modifier = Modifier.padding(bottom = 1.dp),
                    )
                }
            }
            Text(
                "Spend them on perks. They don't count toward ranking.",
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontSize = 11.5.sp,
            )
        }
    }
}

@Composable
private fun PendingHeadline(xp: XpUi, v: VerificationUi?) {
    Text(
        if (v?.needsOrganiser == true) "XP unlocks when the organiser confirms" else "XP unlocks when both captains sign",
        color = Board.Ink,
        fontFamily = PlusJakartaSans,
        fontWeight = FontWeight.Bold,
        fontSize = 17.sp,
        letterSpacing = (-0.2).sp,
    )
    val sub = when {
        v?.needsOrganiser == true -> "A venue or organiser vouches for this result first."
        xp.deadline != null -> "The result locks ${xp.deadline}."
        else -> null
    }
    sub?.let {
        Spacer(Modifier.height(3.dp))
        Text(it, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontSize = 13.sp)
    }
}

/** The signing lines, side by side like the foot of a paper scorecard. */
@Composable
private fun SignOffSlip(v: VerificationUi, xp: XpUi, play: Boolean) {
    Row(Modifier.fillMaxWidth()) {
        if (v.needsOrganiser) {
            SignLine("Organiser or venue", signed = false, play = play, delayMs = 0, modifier = Modifier.weight(1f))
            Spacer(Modifier.weight(1f))
        } else {
            SignLine("${xp.yourTeam.ifBlank { "Your" }} captain", v.yourCaptain, play, delayMs = 150, modifier = Modifier.weight(1f))
            Spacer(Modifier.width(18.dp))
            SignLine("${xp.oppTeam.ifBlank { "Opposition" }} captain", v.opposition, play, delayMs = 750, modifier = Modifier.weight(1f))
        }
    }
}

private val Ink = Color(0xFF1E3A8A) // blue-black pen ink

/**
 * One signing line. Signed: the signature draws itself in, pen-speed, with a light tick
 * when the pen lifts; tap it and it's signed again. Unsigned: a dashed line and a "×".
 */
@Composable
private fun SignLine(label: String, signed: Boolean, play: Boolean, delayMs: Long, modifier: Modifier = Modifier) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val ink = remember { Animatable(1f) }

    suspend fun sign() {
        ink.snapTo(0f)
        ink.animateTo(1f, tween(900, easing = LinearOutSlowInEasing))
        view.performHapticFeedback(Feel.TICK)
    }

    // Started on the screen's scope, not the effect's: the entrance flag flips back to false
    // as soon as the staged entrance ends, which would cancel a signature mid-stroke.
    var started by remember { mutableStateOf(false) }
    LaunchedEffect(play) {
        if (play && signed && !started) {
            started = true
            ink.snapTo(0f)
            scope.launch {
                delay(delayMs)
                sign()
            }
        }
    }

    Column(modifier) {
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(46.dp)
                .semantics { contentDescription = if (signed) "$label: signed" else "$label: not signed yet" }
                .then(
                    if (signed) Modifier.pointerInput(Unit) {
                        detectTapGestures { scope.launch { sign() } }
                    } else Modifier,
                ),
        ) {
            val baseY = size.height - 2.dp.toPx()
            drawLine(
                if (signed) Board.InkFaint.copy(alpha = 0.6f) else Board.LockedStub,
                Offset(0f, baseY), Offset(size.width, baseY),
                strokeWidth = 1.dp.toPx(),
                pathEffect = if (signed) null else PathEffect.dashPathEffect(floatArrayOf(6f, 6f)),
            )
            if (!signed) {
                val c = Offset(6.dp.toPx(), baseY - 9.dp.toPx())
                val k = 4.dp.toPx()
                val w = 1.4.dp.toPx()
                drawLine(Board.InkFaint, c + Offset(-k, -k), c + Offset(k, k), w, StrokeCap.Round)
                drawLine(Board.InkFaint, c + Offset(-k, k), c + Offset(k, -k), w, StrokeCap.Round)
                return@Canvas
            }
            val full = signaturePath(label, size.width * 0.92f, size.height - 6.dp.toPx())
            val pm = PathMeasure().apply { setPath(full, false) }
            val part = Path()
            pm.getSegment(0f, pm.length * ink.value, part, true)
            drawPath(part, Ink, style = Stroke(1.8.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
        }
        Spacer(Modifier.height(6.dp))
        Text(
            label, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Medium, fontSize = 12.sp,
            maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
        Text(
            if (signed) "Signed" else "Not signed yet",
            color = if (signed) Board.Green else Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.SemiBold,
            fontSize = 11.5.sp,
        )
    }
}

/**
 * A believable scrawl, the same every time for the same name: a tall opening loop, a run
 * of joined humps of varying height, then an underline flourish swept back under it.
 */
private fun signaturePath(seedText: String, w: Float, h: Float): Path {
    var seed = seedText.hashCode()
    fun rnd(): Float {
        seed = seed * 1103515245 + 12345
        return ((seed ushr 8) and 0xFFFF) / 65535f
    }
    val base = h * 0.66f
    var x = w * 0.03f
    return Path().apply {
        moveTo(x, base)
        // Capital: up, over, and back down through itself
        cubicTo(x + w * 0.01f, base - h * 0.95f, x + w * 0.13f, base - h * 0.9f, x + w * 0.05f, base - h * 0.2f)
        cubicTo(x + w * 0.02f, base + h * 0.1f, x + w * 0.1f, base + h * 0.05f, x + w * 0.12f, base - h * 0.15f)
        x += w * 0.12f
        val letters = 5 + (rnd() * 3).toInt()
        repeat(letters) {
            val step = w * (0.07f + rnd() * 0.035f)
            val up = h * (0.18f + rnd() * 0.5f)
            val drift = h * 0.08f * (rnd() - 0.5f)
            cubicTo(x + step * 0.15f, base - up, x + step * 0.85f, base - up, x + step, base + drift)
            x += step
        }
        // Flourish: out, down, and back under the name
        cubicTo(x + w * 0.07f, base - h * 0.1f, x + w * 0.06f, base + h * 0.3f, x - w * 0.05f, base + h * 0.28f)
        quadraticTo(w * 0.35f, base + h * 0.36f, w * 0.1f, base + h * 0.22f)
    }
}

@Composable
private fun SettledXp(xp: XpUi, countUp: Boolean) {
    val view = LocalView.current
    val target = xp.xp ?: 0
    var shown by remember { mutableIntStateOf(if (countUp) 0 else target) }
    val stamp = remember { Animatable(if (countUp) 0f else 1f) }
    val scope = rememberCoroutineScope()
    var started by remember { mutableStateOf(false) }
    LaunchedEffect(countUp, target) {
        if (started) return@LaunchedEffect
        if (!countUp || target <= 0) {
            shown = target
            stamp.snapTo(1f)
            return@LaunchedEffect
        }
        started = true
        // Runs on the screen's scope so the entrance ending (countUp → false) can't cut it short.
        scope.launch {
            delay(100)
            val a = Animatable(0f)
            var last = 0
            a.animateTo(target.toFloat(), tween(650, easing = FastOutSlowInEasing)) {
                shown = value.toInt()
                if (shown - last >= maxOf(1, target / 8)) {
                    last = shown
                    view.performHapticFeedback(Feel.TICK)
                }
            }
            shown = target
            delay(120)
            // The stamp comes down: oversized and tilted, hits, settles.
            var hit = false
            stamp.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = 500f)) {
                if (!hit && value >= 1f) {
                    hit = true
                    view.performHapticFeedback(Feel.COMMIT)
                }
            }
        }
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.weight(1f)) {
            Text(
                "+$shown",
                color = Board.Green,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 44.sp,
                letterSpacing = (-1.5).sp,
            )
            Spacer(Modifier.width(8.dp))
            Text(
                "XP",
                color = Board.Ink,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 18.sp,
                modifier = Modifier.padding(bottom = 8.dp),
            )
        }
        VerifiedStamp(
            Modifier.graphicsLayer {
                val t = stamp.value
                val s = 1f + (1f - t) * 0.9f
                scaleX = s; scaleY = s
                alpha = (t * 2.5f).coerceIn(0f, 1f)
                rotationZ = -9f + (1f - t) * -10f
            },
        )
    }
    Spacer(Modifier.height(2.dp))
    Text(
        if (xp.ranked) "Added to your district, state and India leaderboards." else "Added to your casual record.",
        color = Board.InkMuted,
        fontFamily = PlusJakartaSans,
        fontSize = 12.5.sp,
    )
}

/** A rubber stamp: double rule, spaced caps, ink that doesn't quite fill. */
@Composable
private fun VerifiedStamp(modifier: Modifier = Modifier) {
    val shape = RoundedCornerShape(6.dp)
    Box(
        modifier
            .border(2.dp, Board.Green.copy(alpha = 0.85f), shape)
            .padding(3.dp)
            .border(1.dp, Board.Green.copy(alpha = 0.6f), RoundedCornerShape(4.dp))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    ) {
        Text(
            "VERIFIED",
            color = Board.Green.copy(alpha = 0.9f),
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 13.sp,
            letterSpacing = 2.4.sp,
        )
        // Worn ink: a few gaps knocked out of the letters
        Canvas(Modifier.matchParentSize()) {
            listOf(0.18f to 0.3f, 0.47f to 0.7f, 0.74f to 0.25f, 0.9f to 0.62f).forEach { (fx, fy) ->
                drawCircle(Color.White.copy(alpha = 0.75f), 1.3.dp.toPx(), Offset(size.width * fx, size.height * fy))
            }
        }
    }
}
