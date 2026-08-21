package com.haraan.app.ui.social

import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Reply
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Block
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material3.Icon
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.haraan.app.data.ChatMessage
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

/**
 * The long-press experience: the thread falls back, the message you pressed stays lit, and
 * the things you can do with it arrive around it.
 *
 * This replaces a centred Material dialog, which is the shape every generated app produces
 * and reads as a system prompt rather than a gesture you performed. What makes this feel
 * like the messengers people use all day is not the list of actions — it is that the message
 * itself is still on screen, still where your thumb left it, with the menu attached to it.
 *
 * Every action here does something real: Reply quotes into the composer, Forward opens a
 * picker of your other threads, Unsend clears the message for everyone. Nothing is shown that
 * the server cannot actually do — Forward is absent, not greyed out, when there is nowhere to
 * send it.
 */
@Composable
fun MessageActionOverlay(
    message: ChatMessage,
    /** How far it got — shown as the header, the way a message-info screen would. */
    receiptLine: String,
    canUnsend: Boolean,
    onReact: (String) -> Unit,
    onCopy: () -> Unit,
    onUnsend: () -> Unit,
    onReply: () -> Unit,
    /** Null when there is nowhere to forward to — the row is then absent, not disabled. */
    onForward: (() -> Unit)? = null,
    onInfo: () -> Unit,
    onDismiss: () -> Unit,
) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        // Entrance: the whole cluster springs up together, which reads as one object
        // being lifted rather than three panels appearing.
        var shown by remember { mutableStateOf(false) }
        LaunchedEffect(Unit) { shown = true }
        val scale by animateFloatAsState(
            targetValue = if (shown) 1f else 0.88f,
            animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = 420f),
            label = "overlayScale",
        )
        val fade by animateFloatAsState(
            targetValue = if (shown) 1f else 0f,
            animationSpec = tween(140),
            label = "overlayFade",
        )

        Box(
            Modifier
                .fillMaxSize()
                // The backdrop is the dismiss target, without a ripple — tapping "away"
                // shouldn't look like pressing a button.
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                    onClick = onDismiss,
                )
                .background(Color.Black.copy(alpha = 0.55f * fade)),
            contentAlignment = Alignment.Center,
        ) {
            Column(
                Modifier
                    .padding(horizontal = 20.dp)
                    .graphicsLayer {
                        scaleX = scale
                        scaleY = scale
                        alpha = fade
                    },
                horizontalAlignment = if (message.mine) Alignment.End else Alignment.Start,
            ) {
                // ── Reaction row ──────────────────────────────────────────────────
                if (!message.deleted) {
                    Row(
                        Modifier
                            .shadow(18.dp, RoundedCornerShape(28.dp), spotColor = Color.Black.copy(alpha = 0.5f))
                            .clip(RoundedCornerShape(28.dp))
                            .background(Color.White)
                            .padding(horizontal = 8.dp, vertical = 7.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        // The six everyone reaches for. Tapping the one you already chose
                        // clears it, which the server enforces too.
                        listOf("❤️", "😂", "😮", "😢", "😡", "👍").forEach { emoji ->
                            val mine = message.reactions.any { it.mine && it.emoji == emoji }
                            Box(
                                Modifier
                                    .size(42.dp)
                                    .clip(CircleShape)
                                    .background(
                                        if (mine) HaraanColors.EventsBlue.copy(alpha = 0.14f) else Color.Transparent
                                    )
                                    .pressable(haptic = Feel.SELECT) { onReact(emoji) },
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(emoji, fontSize = 23.sp)
                            }
                        }
                    }
                    Spacer(Modifier.height(12.dp))
                }

                // ── The message, still lit ────────────────────────────────────────
                Box(
                    Modifier
                        .widthIn(max = 300.dp)
                        .clip(
                            RoundedCornerShape(
                                topStart = 16.dp,
                                topEnd = 16.dp,
                                bottomStart = if (message.mine) 16.dp else 4.dp,
                                bottomEnd = if (message.mine) 4.dp else 16.dp,
                            )
                        )
                        .background(
                            when {
                                message.deleted -> Color.White.copy(alpha = 0.12f)
                                message.mine -> HaraanColors.EventsBlue
                                else -> Color.White
                            }
                        )
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                ) {
                    Text(
                        if (message.deleted) "This message was unsent" else message.body,
                        color = if (message.mine && !message.deleted) Color.White else HaraanColors.TextPrimary,
                        fontSize = 14.5.sp,
                        lineHeight = 20.sp,
                        maxLines = 6,
                        overflow = TextOverflow.Ellipsis,
                    )
                }

                Spacer(Modifier.height(12.dp))

                // ── Menu ──────────────────────────────────────────────────────────
                Column(
                    Modifier
                        // Hug the longest row instead of stretching: the action rows use
                        // fillMaxWidth so the card would otherwise take the whole screen
                        // width and leave a wide empty gutter beside four short labels.
                        .width(IntrinsicSize.Max)
                        .widthIn(min = 200.dp)
                        .shadow(20.dp, RoundedCornerShape(18.dp), spotColor = Color.Black.copy(alpha = 0.5f))
                        .clip(RoundedCornerShape(18.dp))
                        .background(Color.White)
                        .padding(vertical = 6.dp),
                ) {
                    // The header states what the thread can only hint at with ticks.
                    Text(
                        receiptLine.uppercase(),
                        color = HaraanColors.TextMuted,
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.7.sp,
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 10.dp),
                    )
                    if (!message.deleted) {
                        OverlayAction(Icons.AutoMirrored.Filled.Reply, "Reply", onClick = onReply)
                        if (onForward != null) {
                            OverlayAction(Icons.AutoMirrored.Filled.Send, "Forward", onClick = onForward)
                        }
                        OverlayAction(Icons.Filled.ContentCopy, "Copy", onClick = onCopy)
                    }
                    OverlayAction(Icons.Outlined.Info, "Message info", onClick = onInfo)
                    if (canUnsend) {
                        OverlayAction(
                            Icons.Filled.Block,
                            "Unsend",
                            tint = HaraanColors.Danger,
                            onClick = onUnsend,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun OverlayAction(
    icon: ImageVector,
    label: String,
    tint: Color = HaraanColors.TextPrimary,
    onClick: () -> Unit,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .pressable(onClick = onClick)
            .padding(horizontal = 18.dp, vertical = 13.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = tint, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(14.dp))
        Text(label, color = tint, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold)
    }
}
