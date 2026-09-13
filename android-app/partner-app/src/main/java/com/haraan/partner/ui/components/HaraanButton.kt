package com.haraan.partner.ui.components

import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.*
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme

enum class HaraanButtonStyle {
    Primary,    // Turf Emerald (Call-to-action, Confirm)
    Executive,  // Dark Slate (Terminal actions, Cashier mark paid)
    Secondary,  // White background, hairline border (Neutral)
    Destructive,// Crimson (Cancel, Release, Void)
    Ghost       // Transparent background
}

/**
 * Handcrafted tactile button with physical spring press feedback, haptics,
 * and loading indicator.
 */
@Composable
fun HaraanButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    style: HaraanButtonStyle = HaraanButtonStyle.Primary,
    leadingIcon: ImageVector? = null,
    trailingIcon: ImageVector? = null,
    enabled: Boolean = true,
    isLoading: Boolean = false,
    height: Dp = 50.dp
) {
    val view = LocalView.current
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()

    val scale by animateFloatAsState(
        targetValue = if (isPressed && enabled && !isLoading) 0.97f else 1.0f,
        label = "btnScale"
    )

    val (bgColor, textColor, borderColor) = when (style) {
        HaraanButtonStyle.Primary -> Triple(
            if (enabled) HaraanTheme.colors.emeraldPrimary else HaraanTheme.colors.surfaceSubtle,
            if (enabled) Color.White else HaraanTheme.colors.textMuted,
            Color.Transparent
        )
        HaraanButtonStyle.Executive -> Triple(
            if (enabled) HaraanTheme.colors.slateDark else HaraanTheme.colors.surfaceSubtle,
            if (enabled) Color.White else HaraanTheme.colors.textMuted,
            Color.Transparent
        )
        HaraanButtonStyle.Secondary -> Triple(
            if (enabled) HaraanTheme.colors.surfaceDefault else HaraanTheme.colors.surfaceSubtle,
            if (enabled) HaraanTheme.colors.textPrimary else HaraanTheme.colors.textMuted,
            if (enabled) HaraanTheme.colors.borderSubtle else HaraanTheme.colors.borderHairline
        )
        HaraanButtonStyle.Destructive -> Triple(
            if (enabled) HaraanTheme.colors.crimsonSubtle else HaraanTheme.colors.surfaceSubtle,
            if (enabled) HaraanTheme.colors.crimsonPrimary else HaraanTheme.colors.textMuted,
            if (enabled) HaraanTheme.colors.crimsonBorder else Color.Transparent
        )
        HaraanButtonStyle.Ghost -> Triple(
            Color.Transparent,
            if (enabled) HaraanTheme.colors.textPrimary else HaraanTheme.colors.textMuted,
            Color.Transparent
        )
    }

    val shape = HaraanTheme.shapes.medium

    Box(
        modifier = modifier
            .scale(scale)
            .height(height)
            .clip(shape)
            .background(bgColor)
            .then(if (borderColor != Color.Transparent) Modifier.border(1.dp, borderColor, shape) else Modifier)
            .clickable(
                interactionSource = interactionSource,
                indication = null,
                enabled = enabled && !isLoading,
                onClick = {
                    view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                    onClick()
                }
            )
            .padding(horizontal = 16.dp),
        contentAlignment = Alignment.Center
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                color = textColor,
                strokeWidth = 2.5.dp
            )
        } else {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                if (leadingIcon != null) {
                    Icon(
                        imageVector = leadingIcon,
                        contentDescription = null,
                        tint = textColor,
                        modifier = Modifier.size(18.dp)
                    )
                }

                Text(
                    text = text,
                    color = textColor,
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                )

                if (trailingIcon != null) {
                    Icon(
                        imageVector = trailingIcon,
                        contentDescription = null,
                        tint = textColor,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
        }
    }
}
