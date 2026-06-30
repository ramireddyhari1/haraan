package com.example.thanna.ui.main.eventdetail

import android.os.Build
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.unit.dp
import com.example.thanna.ui.components.HaraanImage
import com.example.thanna.ui.theme.HaraanColors

@Composable
fun EventHeroSection(
    imageUrl: String,
    title: String,
    scrollState: ScrollState,
    modifier: Modifier = Modifier
) {
    val configuration = LocalConfiguration.current
    val screenHeight = configuration.screenHeightDp.dp
    val posterHeight = screenHeight * 0.42f

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(posterHeight + 48.dp) // extra for blurred bleed
            .graphicsLayer {
                translationY = -scrollState.value * 0.35f
                alpha = (1f - (scrollState.value / (posterHeight.toPx() * 0.8f)).coerceIn(0f, 1f))
            }
    ) {
        // 1. Blurred background duplicate — Spotify depth
        val blurModifier = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            Modifier.blur(radius = 40.dp)
        } else {
            Modifier // graceful fallback — no blur on older devices
        }
        Box(
            modifier = Modifier
                .fillMaxSize()
                .then(blurModifier)
        ) {
            HaraanImage(
                model = imageUrl,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxSize()
                    .graphicsLayer { alpha = 0.7f }
            )
            // Dark overlay for non-blur devices
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color.Black.copy(alpha = 0.4f))
                )
            }
        }

        // 2. Actual poster — centered, slightly inset, with shadow
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(posterHeight)
                .padding(horizontal = 0.dp)
                .align(Alignment.TopCenter)
                // Square bottom — the content sheet overlaps and supplies the only
                // curve at the seam, so the poster doesn't add a second opposing one.
                .shadow(
                    elevation = 24.dp,
                    shape = RoundedCornerShape(0.dp),
                    clip = false,
                    ambientColor = Color.Black.copy(alpha = 0.3f),
                    spotColor = Color.Black.copy(alpha = 0.4f)
                )
        ) {
            HaraanImage(
                model = imageUrl,
                contentDescription = title,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize()
            )

            // Bottom gradient for seamless transition
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(120.dp)
                    .align(Alignment.BottomCenter)
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                Color.Transparent,
                                Color.Black.copy(alpha = 0.6f)
                            )
                        )
                    )
            )
        }
    }
}
