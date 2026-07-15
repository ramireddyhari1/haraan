package com.haraan.app.ui.animations

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.unit.IntSize

fun Modifier.haraanShimmer(): Modifier = composed {
    var size by remember { mutableStateOf(IntSize.Zero) }
    val transition = rememberInfiniteTransition(label = "ShimmerTransition")
    
    val translateAnim by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 1200, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "ShimmerTranslation"
    )

    val shimmerColors = listOf(
        Color(0xFFE2E8F0), // Slate 200
        Color(0xFFF1F5F9), // Slate 100
        Color(0xFFE2E8F0), // Slate 200
    )

    val brush = if (size.width > 0) {
        Brush.linearGradient(
            colors = shimmerColors,
            start = Offset(translateAnim - size.width, 0f),
            end = Offset(translateAnim, 0f)
        )
    } else {
        Brush.linearGradient(
            colors = shimmerColors,
            start = Offset.Zero,
            end = Offset.Zero
        )
    }

    this.background(brush).onGloballyPositioned {
        size = it.size
    }
}
