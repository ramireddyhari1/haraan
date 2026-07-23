package com.haraan.app.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ConfirmationNumber
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
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

@Composable
fun HaraanEventCard(
    title: String,
    date: String,
    venue: String,
    price: String,
    category: String,
    imageUrl: String,
    rating: Double,
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

        // 3. Category Tag (Top-Left)
        Row(
            modifier = Modifier
                .align(Alignment.TopStart)
                .padding(16.dp),
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
        }

        // 4. Rating Badge (Top-Right) — consistent star rating across every card.
        // Shown only when the event actually has a rating (no fabricated star).
        if (rating > 0.0) {
            Surface(
                color = Color.Black.copy(alpha = 0.6f),
                shape = RoundedCornerShape(HaraanRadius.Small),
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(16.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(horizontal = 9.dp, vertical = 5.dp)
                ) {
                    Text(
                        text = "★",
                        color = Color(0xFFF5A623),
                        style = HaraanTypography.LabelSmall.copy(fontSize = 11.sp)
                    )
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "%.1f".format(rating),
                        color = Color.White,
                        style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    )
                }
            }
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
                        imageVector = Icons.Default.ConfirmationNumber,
                        contentDescription = "Book tickets",
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }
        }
    }
}
