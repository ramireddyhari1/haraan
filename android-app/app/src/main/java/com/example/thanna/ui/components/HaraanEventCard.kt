package com.example.thanna.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

@Composable
fun HaraanEventCard(
    title: String,
    date: String,
    venue: String,
    price: String,
    category: String,
    imageUrl: String,
    isFillingFast: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .fillMaxWidth(0.72f)
            .aspectRatio(0.75f)
            .clip(RoundedCornerShape(HaraanRadius.Hero))
            .background(Color.Black)
            .clickable { onClick() }
    ) {
        // 1. Full Bleed Image
        HaraanImage(
            model = imageUrl,
            contentDescription = title,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize()
        )

        // 2. Gradient Overlay Mask (Transitions to rich slate/black)
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            Color.Transparent,
                            Color.Black.copy(alpha = 0.15f),
                            Color.Black.copy(alpha = 0.85f)
                        ),
                        startY = 180f
                    )
                )
        )

        // 3. Category Tag & Live Badge (Top-Left)
        Row(
            modifier = Modifier
                .align(Alignment.TopStart)
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                color = Color.Black.copy(alpha = 0.45f),
                shape = CircleShape
            ) {
                Text(
                    text = category,
                    color = Color.White,
                    style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp),
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                )
            }
            if (isFillingFast) {
                Surface(
                    color = HaraanColors.GameHubGreen,
                    shape = CircleShape
                ) {
                    Text(
                        text = "LIVE",
                        color = Color.White,
                        style = HaraanTypography.LabelSmall.copy(fontSize = 9.sp, fontWeight = FontWeight.Black),
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                    )
                }
            }
        }

        // 4. Social Proof Layer (Top-Right)
        val socialProof = when (title.hashCode() % 3) {
            0 -> "🔥 120 booked this week"
            1 -> "⏳ 8 seats left"
            else -> "⭐ 4.8 rating"
        }
        Surface(
            color = Color.Black.copy(alpha = 0.6f),
            shape = RoundedCornerShape(HaraanRadius.Small),
            modifier = Modifier
                .align(Alignment.TopEnd)
                .padding(16.dp)
        ) {
            Text(
                text = socialProof,
                color = Color.White,
                style = HaraanTypography.LabelSmall.copy(fontSize = 9.sp),
                modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
            )
        }

        // 5. Foreground Content (Sits cleanly on bottom gradient)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.BottomCenter)
                .padding(HaraanSpacing.Medium),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                // Date Status Line
                Text(
                    text = date.replace(", ", "  •  ").uppercase(),
                    color = Color.White.copy(alpha = 0.9f),
                    style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp)
                )
                
                Spacer(modifier = Modifier.height(4.dp))

                // Title
                Text(
                    text = title,
                    color = Color.White,
                    style = HaraanTypography.TitleMedium.copy(
                        color = Color.White,
                        fontSize = 16.sp,
                        lineHeight = 20.sp
                    ),
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )

                Spacer(modifier = Modifier.height(6.dp))

                // Venue & Pricing (Cleanly separated)
                Text(
                    text = "$venue  •  $price",
                    color = Color.White.copy(alpha = 0.65f),
                    style = HaraanTypography.BodyMedium.copy(fontSize = 12.sp, color = Color.White.copy(alpha = 0.65f))
                )
            }

            // Elegant Booking Floating Circular Button
            Surface(
                onClick = onClick,
                shape = CircleShape,
                color = Color.White,
                modifier = Modifier
                    .padding(start = 12.dp)
                    .size(44.dp),
                shadowElevation = 4.dp
            ) {
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier.fillMaxSize()
                ) {
                    Icon(
                        imageVector = Icons.Default.PlayArrow,
                        contentDescription = "Book",
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }
        }
    }
}
