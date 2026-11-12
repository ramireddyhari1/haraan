package com.haraan.app.commentary

import android.view.HapticFeedbackConstants
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay

private val OnRed = Color(0xFFDC2626)
private val OnRedSoft = Color(0xFFFDECEC)
private val OnAmber = Color(0xFFB45309)
private val OnAmberSoft = Color(0xFFFEF3C7)
private val OnInk = Color(0xFF0F172A)
private val OnBody = Color(0xFF1E293B)
private val OnMuted = Color(0xFF64748B)
private val OnLine = Color(0xFFE2E8F0)
private val OnBlue = Color(0xFF2563EB)

/** Skin and shirt tones for the drawn listeners, so the crowd isn't one face repeated. */
private val Skins = listOf(Color(0xFFE8B98F), Color(0xFFC68B59), Color(0xFF8D5524), Color(0xFFF1C9A5), Color(0xFFA86B3C))
private val Shirts = listOf(Color(0xFF93C5FD), Color(0xFFFCD34D), Color(0xFFA7F3D0), Color(0xFFFCA5A5), Color(0xFFC4B5FD))

/**
 * The commentary box, under the scorer's header while going on air and while on air.
 *
 * One drawn object carries it: a broadcast mic whose sound rings move with the scorer's real
 * voice, beside an ON AIR studio lamp that flickers on like a real one, the time on air, and
 * the people listening drawn as a row of little heads in headphones that pop in as they join.
 * Muted: the lamp goes amber, the mic gets a slash, the rings stop. Mute and End sit stacked on
 * the right so the card stays one compact block and the keypad below barely moves.
 */
@Composable
fun OnAirStrip(matchId: String, modifier: Modifier = Modifier) {
    val state by CommentaryBroadcaster.state.collectAsState()
    val live = (state as? CommentaryBroadcaster.State.OnAir)?.takeIf { it.matchId == matchId }
    val starting = (state as? CommentaryBroadcaster.State.Starting)?.matchId == matchId

    AnimatedVisibility(
        visible = live != null || starting,
        enter = expandVertically() + fadeIn(),
        exit = shrinkVertically() + fadeOut(),
        modifier = modifier,
    ) {
        val view = LocalView.current
        val muted = live?.muted == true
        val mode = when {
            live == null -> LampMode.WARMING
            muted -> LampMode.MUTED
            else -> LampMode.ON
        }

        // The moment it goes live, and each new listener, is felt as well as seen.
        LaunchedEffect(live != null) {
            if (live != null) view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
        }
        var lastListeners by remember { mutableIntStateOf(0) }
        LaunchedEffect(live?.listeners) {
            val n = live?.listeners ?: 0
            if (n > lastListeners) view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
            lastListeners = n
        }

        val border by animateColorAsState(
            when (mode) {
                LampMode.ON -> OnRed.copy(alpha = 0.28f)
                LampMode.MUTED -> OnAmber.copy(alpha = 0.32f)
                LampMode.WARMING -> OnLine
            },
            label = "border",
        )

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp)
                .padding(bottom = 10.dp)
                .clip(RoundedCornerShape(18.dp))
                .background(Color.White)
                .border(1.dp, border, RoundedCornerShape(18.dp))
                .padding(start = 8.dp, end = 10.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            val level by animateFloatAsState(
                if (mode == LampMode.ON) (live?.level ?: 0f) else 0f,
                tween(90),
                label = "level",
            )
            BroadcastMic(mode = mode, level = level, modifier = Modifier.size(60.dp))
            Spacer(Modifier.width(8.dp))

            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(7.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    OnAirLamp(mode)
                    Spacer(Modifier.width(8.dp))
                    if (live != null) {
                        var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
                        LaunchedEffect(live.startedAt) { while (true) { now = System.currentTimeMillis(); delay(1000) } }
                        Text(
                            clock((now - live.startedAt) / 1000),
                            style = TextStyle(fontFeatureSettings = "tnum"),
                            color = OnInk, fontSize = 15.sp, fontWeight = FontWeight.Bold,
                        )
                    }
                }
                when {
                    live == null -> Text("Getting your mic on air…", color = OnMuted, fontSize = 12.sp)
                    muted -> Text("Muted. Listeners hear nothing.", color = OnAmber, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                    else -> ListenerCrowd(live.listeners)
                }
            }

            if (live != null) {
                Spacer(Modifier.width(8.dp))
                Column(verticalArrangement = Arrangement.spacedBy(6.dp), horizontalAlignment = Alignment.End) {
                    BoothButton(if (muted) "Unmute" else "Mute", OnInk, OnLine) { CommentaryBroadcaster.toggleMute() }
                    BoothButton("End", OnRed, OnRed.copy(alpha = 0.3f)) { CommentaryBroadcaster.stop() }
                }
            }
        }
    }
}

private enum class LampMode { WARMING, ON, MUTED }

/**
 * A studio ON AIR sign. Going live, it flickers on the way a real lamp strikes; muted, it
 * drops to amber; while connecting it breathes grey.
 */
@Composable
private fun OnAirLamp(mode: LampMode) {
    val strike = remember { Animatable(0f) }
    LaunchedEffect(mode == LampMode.ON) {
        if (mode == LampMode.ON) {
            strike.snapTo(0f)
            strike.animateTo(1f, keyframes {
                durationMillis = 520
                0f at 0
                1f at 60
                0.15f at 130
                1f at 220
                0.45f at 290
                1f at 380
            })
        } else {
            strike.snapTo(1f)
        }
    }
    val breathe = rememberInfiniteTransition(label = "warm")
    val warm by breathe.animateFloat(0.45f, 1f, infiniteRepeatable(tween(700), RepeatMode.Reverse), label = "warmA")

    val (bg, fg, label) = when (mode) {
        LampMode.ON -> Triple(OnRed, Color.White, "ON AIR")
        LampMode.MUTED -> Triple(OnAmberSoft, OnAmber, "MUTED")
        LampMode.WARMING -> Triple(Color(0xFFF1F5F9), OnMuted, "ON AIR")
    }
    val glass = if (mode == LampMode.WARMING) warm else strike.value

    Box(
        Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(if (mode == LampMode.ON) OnRedSoft else bg)
            .border(1.dp, if (mode == LampMode.ON) OnRed else fg.copy(alpha = 0.35f), RoundedCornerShape(6.dp)),
    ) {
        // The lit glass, faded in by the strike so the flicker reads as light, not a colour swap.
        if (mode == LampMode.ON) {
            Box(Modifier.matchParentSize().graphicsLayer { alpha = glass }.background(OnRed))
        }
        Text(
            label,
            color = if (mode == LampMode.ON) Color.White.copy(alpha = 0.55f + 0.45f * glass) else fg.copy(alpha = if (mode == LampMode.WARMING) glass else 1f),
            fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
            modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp),
        )
    }
}

/**
 * The drawn broadcast mic: a grilled capsule on a yoke and stand, with a tally band that
 * lights red on air. Sound rings on both sides grow and brighten with the real voice level.
 */
@Composable
private fun BroadcastMic(mode: LampMode, level: Float, modifier: Modifier) {
    val idle = rememberInfiniteTransition(label = "idle")
    val wait by idle.animateFloat(0.15f, 0.55f, infiniteRepeatable(tween(650), RepeatMode.Reverse), label = "wait")

    Canvas(modifier) {
        val u = size.minDimension / 60f // 1 unit = 1/60 of the box
        val cx = size.width / 2f
        val capW = 18f * u
        val capH = 27f * u
        val capTop = 5f * u
        val cy = capTop + capH * 0.45f

        // Sound rings first, so the mic sits on top of them.
        val ringLevel = when (mode) {
            LampMode.ON -> level.coerceIn(0f, 1f)
            LampMode.WARMING -> wait
            LampMode.MUTED -> -1f
        }
        if (ringLevel >= 0f) {
            val ringColor = if (mode == LampMode.ON) OnRed else OnMuted
            rings(cx, cy, u, ringLevel, ringColor)
        }

        // Yoke, stem and base.
        val ink = OnBody
        drawArc(
            color = ink, startAngle = 0f, sweepAngle = 180f, useCenter = false,
            topLeft = Offset(cx - 13f * u, capTop + 9f * u), size = Size(26f * u, 26f * u),
            style = Stroke(width = 2.2f * u, cap = StrokeCap.Round),
        )
        drawLine(ink, Offset(cx, capTop + 35f * u), Offset(cx, 50f * u), strokeWidth = 2.6f * u, cap = StrokeCap.Round)
        drawRoundRect(ink, topLeft = Offset(cx - 12f * u, 50f * u), size = Size(24f * u, 4.5f * u), cornerRadius = CornerRadius(2.5f * u))

        // Capsule.
        drawRoundRect(OnBody, topLeft = Offset(cx - capW / 2, capTop), size = Size(capW, capH), cornerRadius = CornerRadius(capW / 2))
        // Grille.
        val grille = Color.White.copy(alpha = 0.22f)
        var gy = capTop + 5f * u
        while (gy < capTop + capH * 0.5f) {
            drawLine(grille, Offset(cx - capW / 2 + 4f * u, gy), Offset(cx + capW / 2 - 4f * u, gy), strokeWidth = 1.1f * u, cap = StrokeCap.Round)
            gy += 3.2f * u
        }
        // Tally band: lit red on air, amber muted, dark otherwise.
        val tally = when (mode) {
            LampMode.ON -> OnRed
            LampMode.MUTED -> OnAmber
            LampMode.WARMING -> Color(0xFF475569)
        }
        drawRect(tally, topLeft = Offset(cx - capW / 2, capTop + capH * 0.58f), size = Size(capW, 3.4f * u))
        // A highlight down the left of the capsule, so it reads as round metal.
        drawLine(
            Color.White.copy(alpha = 0.32f),
            Offset(cx - capW / 2 + 3.2f * u, capTop + 5f * u), Offset(cx - capW / 2 + 3.2f * u, capTop + capH - 6f * u),
            strokeWidth = 1.3f * u, cap = StrokeCap.Round,
        )

        if (mode == LampMode.MUTED) {
            // The slash, cut out of the drawing with a white edge so it reads on the dark capsule.
            val a = Offset(cx - 15f * u, capTop + 1f * u)
            val b = Offset(cx + 15f * u, capTop + capH + 6f * u)
            drawLine(Color.White, a, b, strokeWidth = 6f * u, cap = StrokeCap.Round)
            drawLine(OnAmber, a, b, strokeWidth = 3f * u, cap = StrokeCap.Round)
        }
    }
}

private fun DrawScope.rings(cx: Float, cy: Float, u: Float, level: Float, color: Color) {
    val near = 16f * u + 3f * u * level
    val far = 22f * u + 5f * u * level
    val nearA = 0.22f + 0.78f * level
    val farA = (0.08f + 0.92f * ((level - 0.25f) / 0.75f)).coerceIn(0.08f, 1f)
    listOf(near to nearA, far to farA).forEach { (r, a) ->
        val stroke = Stroke(width = 2.2f * u, cap = StrokeCap.Round)
        drawArc(color.copy(alpha = a), -38f, 76f, false, Offset(cx - r, cy - r), Size(r * 2, r * 2), style = stroke)
        drawArc(color.copy(alpha = a), 142f, 76f, false, Offset(cx - r, cy - r), Size(r * 2, r * 2), style = stroke)
    }
}

/**
 * Who's listening, drawn: up to five little heads in headphones, overlapping like a crowd,
 * each popping in on a spring when it joins, then the count in words.
 */
@Composable
private fun ListenerCrowd(count: Int) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        val shown = count.coerceAtMost(5)
        if (shown > 0) {
            // Exactly as wide as the overlapping heads, so the count sits right after them.
            Box(Modifier.width(((shown - 1) * 14 + 22).dp).height(22.dp)) {
                for (i in 0 until shown) {
                    key(i) { ListenerHead(i, Modifier.offset(x = (i * 14).dp)) }
                }
            }
            Spacer(Modifier.width(8.dp))
        }
        AnimatedContent(
            targetState = count,
            transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(120)) },
            label = "count",
        ) { n ->
            Text(
                when (n) {
                    0 -> "Waiting for listeners"
                    1 -> "1 listening"
                    else -> "$n listening"
                },
                color = if (n == 0) OnMuted else OnInk,
                fontSize = 12.sp,
                fontWeight = if (n == 0) FontWeight.Normal else FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun ListenerHead(index: Int, modifier: Modifier) {
    val pop = remember { Animatable(0f) }
    LaunchedEffect(Unit) { pop.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMediumLow)) }
    val skin = Skins[index % Skins.size]
    val shirt = Shirts[index % Shirts.size]

    Box(
        modifier
            .size(22.dp)
            .graphicsLayer { scaleX = pop.value; scaleY = pop.value; alpha = pop.value.coerceIn(0f, 1f) }
            .clip(CircleShape)
            .background(Color(0xFFEEF2F7))
            .border(1.5.dp, Color.White, CircleShape),
    ) {
        Canvas(Modifier.size(22.dp)) {
            val u = size.minDimension / 22f
            val cx = size.width / 2f
            // Shoulders.
            drawOval(shirt, topLeft = Offset(cx - 8f * u, 15.5f * u), size = Size(16f * u, 12f * u))
            // Head.
            val headR = 4.6f * u
            val headC = Offset(cx, 10.5f * u)
            drawCircle(skin, headR, headC)
            // Headphone band and cups.
            drawArc(
                OnInk, 195f, 150f, false,
                topLeft = Offset(headC.x - 6.2f * u, headC.y - 6.4f * u), size = Size(12.4f * u, 12.4f * u),
                style = Stroke(width = 1.4f * u, cap = StrokeCap.Round),
            )
            drawRoundRect(OnBlue, topLeft = Offset(headC.x - 7f * u, headC.y - 1.6f * u), size = Size(2.8f * u, 4.6f * u), cornerRadius = CornerRadius(1.2f * u))
            drawRoundRect(OnBlue, topLeft = Offset(headC.x + 4.2f * u, headC.y - 1.6f * u), size = Size(2.8f * u, 4.6f * u), cornerRadius = CornerRadius(1.2f * u))
        }
    }
}

@Composable
private fun BoothButton(label: String, color: Color, border: Color, onClick: () -> Unit) {
    Text(
        label,
        color = color,
        fontSize = 12.sp,
        fontWeight = FontWeight.SemiBold,
        modifier = Modifier
            .widthIn(min = 64.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(Color.White)
            .border(1.dp, border, RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 7.dp),
        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
    )
}

internal fun clock(seconds: Long): String {
    val s = seconds.coerceAtLeast(0)
    val h = s / 3600
    val m = (s % 3600) / 60
    val sec = s % 60
    return if (h > 0) "%d:%02d:%02d".format(h, m, sec) else "%02d:%02d".format(m, sec)
}
