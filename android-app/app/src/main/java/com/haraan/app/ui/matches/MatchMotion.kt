package com.haraan.app.ui.matches

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay

/**
 * The motion vocabulary every live board shares.
 *
 * The brief for all of it is "a broadcast graphic, not a game": nothing bounces past its
 * resting place twice, nothing loops while nothing is happening, and every movement is caused
 * by something that was just recorded. A number that changes ROLLS (the old figure leaves
 * upwards as the new one arrives), a notable moment drops a single banner into the hero and
 * takes it away again, and a standing situation — deuce, a break point, a do-or-die raid —
 * gets a chip whose dot breathes slowly, the one continuous motion allowed, because a live
 * situation is exactly the thing still in progress.
 */

/**
 * A figure that rolls to its new value, digit by digit, like a split-flap board.
 *
 * Only the characters that changed move — 19 → 20 rolls both, 20 → 21 rolls only the last —
 * which is what separates a board that ticked from a screen that redrew. Up rolls upward,
 * down (an undo) rolls downward, so a correction reads as a correction.
 */
@Composable
fun RollingFigure(
    text: String,
    color: Color,
    size: Int,
    modifier: Modifier = Modifier,
    fontWeight: FontWeight? = null,
    display: Boolean = true,
) {
    // A plain holder, not state: the previous value is bookkeeping, never something to draw.
    val previous = remember { arrayOf(text) }
    val increasing = remember(text) {
        val a = previous[0].toIntOrNull()
        val b = text.toIntOrNull()
        if (a != null && b != null) b >= a else true
    }
    androidx.compose.runtime.SideEffect { previous[0] = text }
    val style = TextStyle(
        color = color,
        fontSize = size.sp,
        fontFamily = if (display) com.haraan.app.theme.ArchivoDisplay else null,
        fontWeight = fontWeight,
        letterSpacing = if (display) (-1).sp else 0.sp,
        fontFeatureSettings = "tnum",
    )
    Row(modifier) {
        // Right-aligned digit slots, so "9" → "10" grows a new slot on the left rather than
        // shifting every digit sideways.
        text.forEachIndexed { index, ch ->
            val slotKey = text.length - index
            androidx.compose.runtime.key(slotKey) {
                AnimatedContent(
                    targetState = ch,
                    transitionSpec = {
                        val dir = if (increasing) 1 else -1
                        (slideInVertically(tween(320, easing = LinearOutSlowInEasing)) { h -> dir * h } +
                            fadeIn(tween(220)))
                            .togetherWith(
                                slideOutVertically(tween(260, easing = FastOutSlowInEasing)) { h -> -dir * h } +
                                    fadeOut(tween(160))
                            )
                    },
                    label = "rollDigit",
                ) { c ->
                    Text(c.toString(), style = style, maxLines = 1)
                }
            }
        }
    }
}

/** A notable moment the hero announces once — a super tackle, a break of serve, a three. */
data class HeroMoment(
    /** Changes exactly when a NEW moment happens (the event's sequence). */
    val key: Int,
    val word: String,
    val detail: String,
    val color: Color,
)

/**
 * The newest moment, but only once it is actually new.
 *
 * Opening a match must not replay the last thing that happened as if it were happening now —
 * so the first board seen is the baseline, and a banner is only returned for a sequence
 * higher than any seen before. It clears itself after [holdMs], and a still-newer moment
 * replaces it immediately.
 */
@Composable
fun rememberFreshMoment(candidate: HeroMoment?, newestSequence: Int, holdMs: Long = 2600): HeroMoment? {
    var baseline by remember { mutableStateOf<Int?>(null) }
    var shown by remember { mutableStateOf<HeroMoment?>(null) }
    val view = LocalView.current

    LaunchedEffect(newestSequence) {
        val base = baseline
        if (base == null) {
            baseline = newestSequence
            return@LaunchedEffect
        }
        if (newestSequence <= base) {
            // An undo: the moment on screen may no longer exist.
            baseline = newestSequence
            shown = null
            return@LaunchedEffect
        }
        baseline = newestSequence
        if (candidate != null && candidate.key == newestSequence) {
            shown = candidate
            hapticConfirm(view)
            delay(holdMs)
            if (shown?.key == candidate.key) shown = null
        }
    }
    return shown
}

/**
 * The banner a notable moment drops into the top of the hero card.
 *
 * A slim ink pill with the sport's accent as a single stripe — it covers the meta line it
 * lands on for two and a half seconds, then leaves. Deliberately not a toast, a confetti burst
 * or a full-card takeover: the score underneath is still the most important thing on screen.
 */
@Composable
fun HeroMomentBanner(moment: HeroMoment?, modifier: Modifier = Modifier) {
    // Keeps the outgoing banner's content on screen while it animates away.
    val last = remember { arrayOfNulls<HeroMoment>(1) }
    if (moment != null) last[0] = moment
    AnimatedVisibility(
        visible = moment != null,
        modifier = modifier,
        enter = slideInVertically(spring(dampingRatio = 0.82f, stiffness = 520f)) { -it } + fadeIn(tween(160)),
        exit = slideOutVertically(tween(240)) { -it / 2 } + fadeOut(tween(220)),
    ) {
        val m = last[0] ?: return@AnimatedVisibility
        Row(
            Modifier
                .shadow(10.dp, RoundedCornerShape(12.dp), ambientColor = m.color, spotColor = m.color)
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFF0F1B2D))
                .padding(end = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.width(4.dp).size(width = 4.dp, height = 30.dp).background(m.color))
            Spacer(Modifier.width(10.dp))
            Text(
                m.word.uppercase(),
                color = Color.White,
                fontSize = 12.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 1.2.sp,
                maxLines = 1,
            )
            if (m.detail.isNotBlank()) {
                Spacer(Modifier.width(8.dp))
                Box(Modifier.size(3.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.4f)))
                Spacer(Modifier.width(8.dp))
                Text(
                    m.detail,
                    color = Color.White.copy(alpha = 0.78f),
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

/** A standing situation on the board — DEUCE, TIE-BREAK, BREAK POINT, DO-OR-DIE, BONUS. */
data class Situation(val text: String, val color: Color, val live: Boolean = true)

/**
 * The chip for a situation that is still in progress. Its dot breathes slowly; the chip
 * itself arrives with a short rise and leaves with a fade, so a deuce resolving reads as the
 * situation ending rather than a label vanishing.
 */
@Composable
fun SituationChip(situation: Situation, modifier: Modifier = Modifier) {
    val breathe = rememberInfiniteTransition(label = "situation")
    val dot by breathe.animateFloat(
        initialValue = 1f, targetValue = 0.35f,
        animationSpec = infiniteRepeatable(tween(1100, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "situationDot",
    )
    Row(
        modifier
            .clip(RoundedCornerShape(8.dp))
            .background(Color.White.copy(alpha = 0.82f))
            .border(1.dp, situation.color.copy(alpha = 0.35f), RoundedCornerShape(8.dp))
            .padding(horizontal = 8.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Box(
            Modifier.size(6.dp).clip(CircleShape)
                .background(situation.color.copy(alpha = if (situation.live) dot else 1f)),
        )
        Text(
            situation.text.uppercase(),
            color = situation.color,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 0.8.sp,
            maxLines = 1,
        )
    }
}

/** A row of situations that swaps as a whole when the situation changes. */
@Composable
fun SituationRow(situations: List<Situation>, modifier: Modifier = Modifier) {
    AnimatedContent(
        targetState = situations,
        transitionSpec = {
            (fadeIn(tween(220)) + slideInVertically(tween(260)) { it / 3 })
                .togetherWith(fadeOut(tween(160)))
        },
        label = "situations",
        modifier = modifier,
    ) { list ->
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
            list.forEach { SituationChip(it) }
        }
    }
}

/**
 * The serve marker — a small ball that settles beside whoever serves. When the serve changes
 * hands the old marker shrinks away and the new one grows in, instead of a dot blinking.
 */
@Composable
fun ServeMarker(active: Boolean, color: Color, modifier: Modifier = Modifier, size: Int = 8) {
    val scale by animateFloatAsState(
        targetValue = if (active) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.7f, stiffness = 420f),
        label = "serveMarker",
    )
    Box(
        modifier
            .size(size.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale; alpha = scale }
            .clip(CircleShape)
            .background(color),
    )
}

/**
 * Kabaddi's mat as a row of players — lit while in, hollow while out. A player going out
 * dims with a short shrink; a revival fills back in. Staggered by position so an all-out
 * clearing the mat reads as the mat emptying, not a switch flipping.
 */
@Composable
fun MatDots(on: Int, size: Int, color: Color, alignEnd: Boolean, modifier: Modifier = Modifier) {
    Row(
        modifier,
        horizontalArrangement = Arrangement.spacedBy(4.dp, if (alignEnd) Alignment.End else Alignment.Start),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        repeat(size) { i ->
            // Players leave from the far end, so "on" dots always sit nearest the team name.
            val lit = if (alignEnd) i >= size - on else i < on
            val fill by animateColorAsState(
                targetValue = if (lit) color else Color.Transparent,
                animationSpec = tween(360, delayMillis = 40 * i),
                label = "matFill",
            )
            val scale by animateFloatAsState(
                targetValue = if (lit) 1f else 0.78f,
                animationSpec = tween(360, delayMillis = 40 * i),
                label = "matScale",
            )
            Box(
                Modifier
                    .size(9.dp)
                    .graphicsLayer { scaleX = scale; scaleY = scale }
                    .clip(CircleShape)
                    .background(fill)
                    .border(1.2.dp, color.copy(alpha = if (lit) 1f else 0.45f), CircleShape),
            )
        }
    }
}

/**
 * A short lift for something that just changed — a stat bar, a feed row. Fires on key change
 * only, never on first composition.
 */
@Composable
fun Modifier.changeFlash(key: Any?, color: Color): Modifier {
    val flash = remember { Animatable(0f) }
    var first by remember { mutableStateOf(true) }
    LaunchedEffect(key) {
        if (first) { first = false; return@LaunchedEffect }
        flash.snapTo(1f)
        flash.animateTo(0f, tween(900, easing = FastOutSlowInEasing))
    }
    return this.then(
        Modifier.background(color.copy(alpha = 0.16f * flash.value), RoundedCornerShape(10.dp))
    )
}
