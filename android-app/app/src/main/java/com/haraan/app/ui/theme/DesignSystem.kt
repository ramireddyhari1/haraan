package com.haraan.app.ui.theme

import android.os.Build
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

// --- Cinematic Color Palette & Gradients ---

object PremiumColors {
    val BackgroundTop = Color(0xFF081120)
    val BackgroundBottom = Color(0xFF050816)
    
    val CyanGlow = Color(0xFF67E8F9)
    val RedLive = Color(0xFFD32F2F)
    val Gold = Color(0xFFE8D48A)

    val GlassPrimary = Color.White.copy(alpha = 0.08f)
    val GlassSecondary = Color.White.copy(alpha = 0.04f)
    val GlassInsight = Color.White.copy(alpha = 0.12f)
    val GlassBorder = Color.White.copy(alpha = 0.08f)
}

object PremiumGradients {
    val CinematicBackground = Brush.verticalGradient(
        colors = listOf(PremiumColors.BackgroundTop, PremiumColors.BackgroundBottom)
    )
}

// --- Cinematic Typography ---

object PremiumTypography {
    val MassiveScore = TextStyle(
        fontSize = 56.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = (-2).sp,
        color = Color.White
    )

    val MatchStatus = TextStyle(
        fontSize = 16.sp,
        fontWeight = FontWeight.Bold,
        color = PremiumColors.CyanGlow,
        letterSpacing = 0.5.sp
    )

    val MetadataTiny = TextStyle(
        fontSize = 14.sp,
        fontWeight = FontWeight.Normal,
        color = Color.White.copy(alpha = 0.6f)
    )
    
    val TeamNameLarge = TextStyle(
        fontSize = 24.sp,
        fontWeight = FontWeight.Bold,
        color = Color.White
    )
    
    val TeamNameFull = TextStyle(
        fontSize = 10.sp,
        fontWeight = FontWeight.Medium,
        color = Color.White.copy(alpha = 0.9f),
        letterSpacing = 0.5.sp
    )
}

// --- Cinematic Modifiers ---

/**
 * Applies a premium glassmorphic background.
 * Uses native blur on Android 12+ (API 31), and gracefully falls back to just translucent layers on older devices.
 */
fun Modifier.premiumGlassBackground(
    cornerRadius: Dp = 24.dp,
    surfaceColor: Color = PremiumColors.GlassPrimary
): Modifier = composed {
    val baseModifier = this
        .clip(RoundedCornerShape(cornerRadius))
        .background(surfaceColor)
        .border(1.dp, PremiumColors.GlassBorder, RoundedCornerShape(cornerRadius))

    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        baseModifier.blur(radius = 24.dp)
    } else {
        baseModifier
    }
}
