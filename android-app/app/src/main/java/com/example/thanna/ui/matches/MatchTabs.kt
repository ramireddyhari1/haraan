package com.example.thanna.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.border
import androidx.compose.foundation.BorderStroke
import androidx.compose.ui.draw.drawBehind
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

val tabsList = listOf("Fantasy", "Discussions", "Commentary", "Live", "Scorecard")

@Composable
fun MatchTabs(
    selectedTabIndex: Int,
    onTabSelected: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    LazyRow(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
            .drawBehind { drawLine(color = CrexColors.Border, start = androidx.compose.ui.geometry.Offset(0f, size.height), end = androidx.compose.ui.geometry.Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) },
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        contentPadding = PaddingValues(horizontal = 16.dp)
    ) {
        itemsIndexed(tabsList) { index, title ->
            val isSelected = selectedTabIndex == index
            val textColor by animateColorAsState(
                targetValue = if (isSelected) CrexColors.TextPrimary else CrexColors.TextSecondary,
                label = "tabText"
            )

            Box(
                modifier = Modifier
                    .clickable { onTabSelected(index) }
                    .padding(horizontal = 12.dp, vertical = 12.dp)
                    // Stitch has a 2px border on active tab
                    .then(if (isSelected) Modifier.drawBehind {
                        val strokeWidth = 2.dp.toPx()
                        drawLine(
                            color = CrexColors.AccentRed,
                            start = androidx.compose.ui.geometry.Offset(0f, size.height),
                            end = androidx.compose.ui.geometry.Offset(size.width, size.height),
                            strokeWidth = strokeWidth
                        )
                    } else Modifier)
            ) {
                Text(
                    text = title,
                    color = textColor,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}
