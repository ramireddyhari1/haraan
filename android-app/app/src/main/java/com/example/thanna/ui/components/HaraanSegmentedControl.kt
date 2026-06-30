package com.example.thanna.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanTypography

@Composable
fun HaraanSegmentedControl(
    selectedTab: String,
    onTabSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val tabs = listOf("Events", "GameHub")
    val selectedIndex = tabs.indexOf(selectedTab).coerceAtLeast(0)

    val activeColor by animateColorAsState(
        targetValue = if (selectedTab == "Events") HaraanColors.EventsBlue else HaraanColors.GameHubGreen,
        animationSpec = spring(stiffness = 300f),
        label = "ActiveColor"
    )

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 24.dp, vertical = 8.dp)
            .height(50.dp)
            .background(HaraanColors.BorderLight.copy(alpha = 0.5f), RoundedCornerShape(HaraanRadius.Large))
            .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(HaraanRadius.Large))
            .padding(3.dp)
    ) {
        val containerWidth = maxWidth
        val tabWidth = containerWidth / tabs.size

        val indicatorOffset by animateDpAsState(
            targetValue = tabWidth * selectedIndex,
            animationSpec = spring(
                dampingRatio = 0.85f,
                stiffness = 350f
            ),
            label = "PillSliderOffset"
        )

        Box(modifier = Modifier.fillMaxSize()) {
            // Animated Sliding Accent Pill
            Box(
                modifier = Modifier
                    .offset(x = indicatorOffset)
                    .width(tabWidth)
                    .fillMaxHeight()
                    .clip(RoundedCornerShape(HaraanRadius.Medium))
                    .background(activeColor)
                    .shadow(1.dp, RoundedCornerShape(HaraanRadius.Medium))
            )

            // Labels Layer
            Row(modifier = Modifier.fillMaxSize()) {
                tabs.forEach { tab ->
                    val isSelected = tab == selectedTab
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(HaraanRadius.Medium))
                            .clickable { onTabSelected(tab) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = tab,
                            color = if (isSelected) Color.White else HaraanColors.TextSecondary,
                            style = HaraanTypography.TitleMedium.copy(
                                fontSize = 14.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium
                            )
                        )
                    }
                }
            }
        }
    }
}
