package com.example.thanna.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.alpha
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalView
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
    modifier: Modifier = Modifier,
    // When the match is in progress, the "Live" tab (index 2) gets a pulsing red dot.
    liveActive: Boolean = false
) {
    val view = LocalView.current

    // One shared pulse so the live dot breathes rather than sits static.
    val pulse = rememberInfiniteTransition(label = "livePulse")
    val dotAlpha by pulse.animateFloat(
        initialValue = 1f,
        targetValue = 0.25f,
        animationSpec = infiniteRepeatable(tween(900), RepeatMode.Reverse),
        label = "liveDotAlpha"
    )

    val count = tabsList.size
    val indicatorWidth = 22.dp

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
    ) {
        val tabWidth = maxWidth / count
        // The 2dp underline slides to sit centred under the selected tab, rather than
        // snapping between tabs — the single biggest "premium" tell on a tab bar.
        val indicatorOffset by animateDpAsState(
            targetValue = tabWidth * selectedTabIndex + (tabWidth - indicatorWidth) / 2,
            animationSpec = tween(260),
            label = "tabIndicator"
        )

        Column(modifier = Modifier.fillMaxWidth()) {
            Row(modifier = Modifier.fillMaxWidth()) {
                tabsList.forEachIndexed { index, _ ->
                    val title = tabLabel(index)
                    val isSelected = selectedTabIndex == index
                    val textColor by animateColorAsState(
                        targetValue = if (isSelected) CrexColors.TextPrimary else CrexColors.TextSecondary,
                        label = "tabText"
                    )
                    // Only the Live tab (index 2) shows the pulsing dot, and only while live.
                    val showLiveDot = index == 2 && liveActive

                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .weight(1f)
                            .clickable {
                                if (index != selectedTabIndex) hapticTick(view)
                                onTabSelected(index)
                            }
                            .padding(vertical = 12.dp)
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (showLiveDot) {
                                Box(
                                    modifier = Modifier
                                        .size(6.dp)
                                        .alpha(dotAlpha)
                                        .clip(CircleShape)
                                        .background(CrexColors.AccentRed)
                                )
                                Spacer(Modifier.width(5.dp))
                            }
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

            // Underline lane: full-width hairline with the sliding red indicator on top.
            Box(modifier = Modifier.fillMaxWidth().height(2.dp)) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(1.dp)
                        .align(Alignment.BottomCenter)
                        .background(CrexColors.Border)
                )
                Box(
                    modifier = Modifier
                        .offset(x = indicatorOffset)
                        .width(indicatorWidth)
                        .height(2.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(CrexColors.AccentRed)
                )
            }
        }
    }
}
