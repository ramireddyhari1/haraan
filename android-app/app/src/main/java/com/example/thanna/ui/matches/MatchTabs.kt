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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.R

// Kept for sizing/indexing (e.g. pager page count); the visible labels are localized.
val tabsList = listOf("Info", "Commentary", "Live", "Scorecard")

@Composable
private fun tabLabel(index: Int): String = stringResource(
    when (index) {
        0 -> R.string.tab_info
        1 -> R.string.tab_commentary
        2 -> R.string.tab_live
        else -> R.string.tab_scorecard
    }
)

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
        itemsIndexed(tabsList) { index, _ ->
            val title = tabLabel(index)
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
