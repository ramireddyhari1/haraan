package com.haraan.app.ui.main.eventdetail

import android.widget.Toast
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.BookmarkBorder
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.animations.pressScale
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanTypography
import androidx.compose.foundation.interaction.MutableInteractionSource

/**
 * Top navigation that starts as translucent circular buttons floating over the
 * hero, and collapses into a solid titled app bar as the poster scrolls away.
 * One source of truth for back / share / save — no second button set.
 *
 * @param collapseProgress 0 over the poster, 1 when fully collapsed.
 */
@Composable
fun EventFloatingNav(
    onBack: () -> Unit,
    title: String,
    collapseProgress: Float,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current
    var isSaved by remember { mutableStateOf(false) }

    // Single bounce on bookmark toggle — never loops
    val saveScale = remember { Animatable(1f) }
    LaunchedEffect(isSaved) {
        if (isSaved) {
            saveScale.snapTo(0.7f)
            saveScale.animateTo(1f, tween(260))
        }
    }

    val p = collapseProgress
    // Buttons fade from a dark glass disc (over image) to bare icons (over the bar).
    val discColor = Color.Black.copy(alpha = 0.55f * (1f - p))
    val iconTint = lerp(Color.White, HaraanColors.TextPrimary, p)
    val discBorder = Color.White.copy(alpha = 0.12f * (1f - p))

    Surface(
        color = HaraanColors.Surface.copy(alpha = p),
        shadowElevation = (4f * p).dp,
        modifier = modifier.fillMaxWidth()
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
        ) {
            // Collapsed title — fades in, centered, leaving room for the buttons.
            Text(
                text = title,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 16.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.Bold
                ),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(horizontal = 64.dp, vertical = 8.dp)
                    .graphicsLayer { alpha = ((p - 0.4f) / 0.6f).coerceIn(0f, 1f) }
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                NavCircleButton(onClick = onBack, discColor = discColor, border = discBorder) {
                    Icon(
                        Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = iconTint,
                        modifier = Modifier.size(20.dp)
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    NavCircleButton(
                        onClick = {
                            haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            Toast.makeText(context, "Link copied!", Toast.LENGTH_SHORT).show()
                        },
                        discColor = discColor,
                        border = discBorder
                    ) {
                        Icon(
                            Icons.Default.Share,
                            contentDescription = "Share",
                            tint = iconTint,
                            modifier = Modifier.size(20.dp)
                        )
                    }

                    NavCircleButton(
                        onClick = {
                            isSaved = !isSaved
                            haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                        },
                        discColor = discColor,
                        border = discBorder,
                        scaleOverride = saveScale.value
                    ) {
                        Icon(
                            imageVector = if (isSaved) Icons.Default.Bookmark else Icons.Default.BookmarkBorder,
                            contentDescription = "Save",
                            tint = if (isSaved) HaraanColors.EventsBlue else iconTint,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun NavCircleButton(
    onClick: () -> Unit,
    discColor: Color,
    border: Color,
    scaleOverride: Float = 1f,
    content: @Composable () -> Unit
) {
    val interactionSource = remember { MutableInteractionSource() }

    Surface(
        onClick = onClick,
        interactionSource = interactionSource,
        shape = CircleShape,
        color = Color.Transparent,
        modifier = Modifier
            .size(40.dp)
            .pressScale(interactionSource)
            .graphicsLayer {
                scaleX = scaleOverride
                scaleY = scaleOverride
            }
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .fillMaxSize()
                .background(discColor, CircleShape)
                .then(
                    if (border.alpha > 0.01f) {
                        Modifier.border(BorderStroke(1.dp, border), CircleShape)
                    } else Modifier
                )
        ) {
            content()
        }
    }
}
