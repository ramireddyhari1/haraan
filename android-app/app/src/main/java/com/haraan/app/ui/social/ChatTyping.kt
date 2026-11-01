package com.haraan.app.ui.social

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.StartOffset
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors

/**
 * How long one "typing" signal keeps the dots up. The typist's phone re-sends every 3 seconds
 * while they keep typing, so 6 seconds survives one lost frame and still clears soon after
 * they stop — there is deliberately no "stopped typing" message to lose.
 */
internal const val TYPING_HOLD_MS = 6_000L

/**
 * The other person, mid-thought: their face beside a bubble of three dots that rise and fall
 * in turn. It sits where their next message will land, so when it arrives the dots simply
 * become the words.
 */
@Composable
internal fun TypingBubble(avatar: @Composable () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(top = 6.dp),
        verticalAlignment = Alignment.Bottom,
    ) {
        // The caller's own avatar, so the face here is the one in the header above.
        avatar()
        Spacer(Modifier.width(7.dp))
        Row(
            Modifier
                .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp, bottomEnd = 16.dp, bottomStart = 4.dp))
                .background(HaraanColors.Field)
                .padding(horizontal = 14.dp, vertical = 13.dp),
            horizontalArrangement = Arrangement.spacedBy(5.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TypingDots(color = HaraanColors.TextSecondary, size = 7.dp)
        }
    }
}

/**
 * Three dots, each lifting and brightening a beat after the last. Used in the bubble and,
 * smaller, beside "typing…" in the header and the chat list.
 */
@Composable
internal fun TypingDots(color: Color, size: Dp) {
    val transition = rememberInfiniteTransition(label = "typing")
    Row(horizontalArrangement = Arrangement.spacedBy(size * 0.6f), verticalAlignment = Alignment.CenterVertically) {
        repeat(3) { i ->
            val lift by transition.animateFloat(
                initialValue = 0f,
                targetValue = 0f,
                animationSpec = infiniteRepeatable(
                    animation = keyframes {
                        durationMillis = 1_050
                        0f at 0
                        1f at 260 using FastOutSlowInEasing
                        0f at 520 using FastOutSlowInEasing
                        0f at 1_050
                    },
                    repeatMode = RepeatMode.Restart,
                    initialStartOffset = StartOffset(i * 160),
                ),
                label = "dot$i",
            )
            Box(
                Modifier
                    .graphicsLayer {
                        translationY = -lift * size.toPx() * 0.7f
                        alpha = 0.45f + 0.55f * lift
                    }
                    .size(size)
                    .clip(CircleShape)
                    .background(color),
            )
        }
    }
}

/** "typing…" in the app's blue with small dots — the header line and the chat-list preview. */
@Composable
internal fun TypingLabel(text: String, fontSize: Float) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(text, color = HaraanColors.EventsBlue, fontSize = fontSize.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
        Spacer(Modifier.width(5.dp))
        Box(Modifier.height((fontSize * 1.1f).dp), contentAlignment = Alignment.Center) {
            TypingDots(color = HaraanColors.EventsBlue, size = 4.dp)
        }
    }
}
