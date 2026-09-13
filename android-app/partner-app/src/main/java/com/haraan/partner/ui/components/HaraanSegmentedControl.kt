package com.haraan.partner.ui.components

import android.view.HapticFeedbackConstants
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme

data class HaraanSegmentItem(
    val key: String,
    val title: String,
    val count: Int? = null
)

/**
 * Enterprise Linear/Apple-grade segmented control with sliding active pill,
 * smooth tactile feedback, and optional count badges.
 */
@Composable
fun HaraanSegmentedControl(
    items: List<HaraanSegmentItem>,
    selectedKey: String,
    onItemSelected: (String) -> Unit,
    modifier: Modifier = Modifier,
    height: Dp = 42.dp
) {
    val view = LocalView.current
    val shape = HaraanTheme.shapes.medium

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .height(height)
            .clip(shape)
            .background(HaraanTheme.colors.surfaceSubtle)
            .border(1.dp, HaraanTheme.colors.borderHairline, shape)
            .padding(3.dp)
    ) {
        val segmentWidth = maxWidth / items.size.coerceAtLeast(1)
        val selectedIndex = items.indexOfFirst { it.key == selectedKey }.coerceAtLeast(0)

        // Animated Sliding Indicator Pill
        val indicatorOffset by animateDpAsState(
            targetValue = segmentWidth * selectedIndex,
            animationSpec = spring(
                dampingRatio = Spring.DampingRatioLowBouncy,
                stiffness = Spring.StiffnessMediumLow
            ),
            label = "pillOffset"
        )

        Box(
            modifier = Modifier
                .offset(x = indicatorOffset)
                .width(segmentWidth)
                .fillMaxHeight()
                .shadow(2.dp, shape = HaraanTheme.shapes.subtle, clip = false, spotColor = Color(0x200F172A))
                .clip(HaraanTheme.shapes.subtle)
                .background(Color.White)
                .border(1.dp, HaraanTheme.colors.borderHairline, HaraanTheme.shapes.subtle)
        )

        // Items Row
        Row(modifier = Modifier.fillMaxSize()) {
            items.forEach { item ->
                val isSelected = item.key == selectedKey

                val textColor by animateColorAsState(
                    targetValue = if (isSelected) HaraanTheme.colors.textPrimary else HaraanTheme.colors.textSecondary,
                    label = "tabTextCol"
                )

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .clip(HaraanTheme.shapes.subtle)
                        .clickable(
                            interactionSource = remember { MutableInteractionSource() },
                            indication = null
                        ) {
                            if (!isSelected) {
                                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                                onItemSelected(item.key)
                            }
                        },
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp)
                    ) {
                        Text(
                            text = item.title,
                            fontSize = 13.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = textColor
                        )

                        if (item.count != null && item.count > 0) {
                            val badgeBg = if (isSelected) HaraanTheme.colors.slateDark else HaraanTheme.colors.borderSubtle
                            val badgeText = if (isSelected) Color.White else HaraanTheme.colors.textSecondary

                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(99.dp))
                                    .background(badgeBg)
                                    .padding(horizontal = 5.dp, vertical = 1.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "${item.count}",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = badgeText
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
