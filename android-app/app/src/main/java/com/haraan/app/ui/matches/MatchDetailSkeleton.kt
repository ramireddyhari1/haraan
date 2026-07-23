package com.haraan.app.ui.matches

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * Shimmering placeholder shown while the match loads — the shape of the real screen
 * (top bar · hero · tabs · cards) sweeping with a light gradient, so the load reads as
 * "content arriving" instead of a lone spinner on an empty screen.
 */
@Composable
fun MatchDetailSkeleton(modifier: Modifier = Modifier) {
    val transition = rememberInfiniteTransition(label = "skeleton")
    val shimmerX by transition.animateFloat(
        initialValue = -600f, targetValue = 900f,
        animationSpec = infiniteRepeatable(tween(1200, easing = LinearEasing), RepeatMode.Restart),
        label = "shimmerX"
    )
    val brush = Brush.linearGradient(
        colors = listOf(Color(0xFFE8EDF3), Color(0xFFF6F9FC), Color(0xFFE8EDF3)),
        start = Offset(shimmerX, 0f),
        end = Offset(shimmerX + 300f, 0f)
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .statusBarsPadding()
            .padding(horizontal = 16.dp, vertical = 12.dp)
    ) {
        // Top bar: back · title · score button
        Row(verticalAlignment = Alignment.CenterVertically) {
            Bone(38.dp, 38.dp, brush, 12.dp)
            Spacer(Modifier.width(12.dp))
            Bone(130.dp, 18.dp, brush)
            Spacer(Modifier.weight(1f))
            Bone(72.dp, 34.dp, brush, 18.dp)
        }
        Spacer(Modifier.height(20.dp))

        // Hero score card
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(172.dp)
                .clip(RoundedCornerShape(24.dp))
                .background(brush)
        )
        Spacer(Modifier.height(18.dp))

        // Tab row
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            repeat(4) { Bone(56.dp, 12.dp, brush) }
        }
        Spacer(Modifier.height(20.dp))

        // A few content cards
        repeat(3) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(64.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(brush)
            )
            Spacer(Modifier.height(12.dp))
        }
    }
}

@Composable
private fun Bone(width: Dp, height: Dp, brush: Brush, corner: Dp = 6.dp) {
    Box(
        modifier = Modifier
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(corner))
            .background(brush)
    )
}
