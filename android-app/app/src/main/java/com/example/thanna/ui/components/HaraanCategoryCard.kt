package com.example.thanna.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.painter.Painter
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

@Composable
fun HaraanCategoryCard(
    title: String,
    statText: String,
    painter: Painter,
    selected: Boolean,
    onClick: () -> Unit,
    activeColor: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .height(96.dp)
            .clip(RoundedCornerShape(HaraanRadius.Medium))
            .clickable { onClick() },
        shape = RoundedCornerShape(HaraanRadius.Medium),
        colors = CardDefaults.cardColors(
            containerColor = if (selected) activeColor.copy(alpha = 0.08f) else HaraanColors.Surface
        ),
        border = BorderStroke(
            width = if (selected) 1.5.dp else 1.dp,
            color = if (selected) activeColor else HaraanColors.BorderLight
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(HaraanSpacing.Small),
            verticalArrangement = Arrangement.SpaceBetween,
            horizontalAlignment = Alignment.Start
        ) {
            // Icon layout
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .background(
                        if (selected) activeColor.copy(alpha = 0.12f) else HaraanColors.Background,
                        RoundedCornerShape(HaraanRadius.Small)
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    painter = painter,
                    contentDescription = title,
                    tint = if (selected) activeColor else HaraanColors.TextSecondary,
                    modifier = Modifier.size(24.dp)
                )
            }

            // Title and Live count details
            Column {
                Text(
                    text = title,
                    style = HaraanTypography.TitleMedium.copy(
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (selected) HaraanColors.TextPrimary else HaraanColors.TextSecondary
                    )
                )
                Text(
                    text = statText,
                    style = HaraanTypography.BodyMedium.copy(
                        fontSize = 11.sp,
                        color = HaraanColors.TextMuted
                    )
                )
            }
        }
    }
}
