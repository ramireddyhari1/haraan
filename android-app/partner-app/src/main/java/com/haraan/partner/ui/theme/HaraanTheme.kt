package com.haraan.partner.ui.theme

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

val LocalHaraanColors = staticCompositionLocalOf { HaraanColors() }
val LocalHaraanTypography = staticCompositionLocalOf { HaraanTypography() }
val LocalHaraanShapes = staticCompositionLocalOf { HaraanShapes() }

object HaraanTheme {
    val colors: HaraanColors
        @Composable
        @ReadOnlyComposable
        get() = LocalHaraanColors.current

    val typography: HaraanTypography
        @Composable
        @ReadOnlyComposable
        get() = LocalHaraanTypography.current

    val shapes: HaraanShapes
        @Composable
        @ReadOnlyComposable
        get() = LocalHaraanShapes.current

    /** Centrally configurable temporary hold duration in minutes (Default: 5 minutes) */
    const val DEFAULT_HOLD_DURATION_MINUTES = 5

    /** Centrally configurable temporary hold duration in seconds (Default: 300 seconds) */
    const val DEFAULT_HOLD_DURATION_SECONDS = DEFAULT_HOLD_DURATION_MINUTES * 60
}

/**
 * Standard enterprise SaaS surface modifier:
 * Subtle lifted shadow, clean white fill, hairline border, and rounded radius.
 */
fun Modifier.haraanCard(
    radius: Dp = 18.dp,
    containerColor: Color = Color.White,
    borderColor: Color = Color(0x140F172A),
    elevation: Dp = 8.dp
): Modifier = this
    .shadow(elevation, RoundedCornerShape(radius), clip = false, spotColor = Color(0x140F172A))
    .clip(RoundedCornerShape(radius))
    .background(containerColor)
    .border(1.dp, borderColor, RoundedCornerShape(radius))

@Composable
fun HaraanTheme(
    colors: HaraanColors = HaraanColors(),
    typography: HaraanTypography = HaraanTypography(),
    shapes: HaraanShapes = HaraanShapes(),
    content: @Composable () -> Unit
) {
    CompositionLocalProvider(
        LocalHaraanColors provides colors,
        LocalHaraanTypography provides typography,
        LocalHaraanShapes provides shapes,
        content = content
    )
}
