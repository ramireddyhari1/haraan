package com.example.thanna.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.NotificationsOff
import androidx.compose.material.icons.outlined.MoreVert
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.border

@Composable
fun MatchHeader(state: MatchUiState, modifier: Modifier = Modifier, scrollOffset: Int = 0) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
    ) {
        // Top Bar (Stitch design)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp)
                .border(BorderStroke(1.dp, CrexColors.Border))
                .padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Back",
                    tint = CrexColors.TextPrimary,
                    modifier = Modifier.size(24.dp)
                )
                Text(
                    text = "${state.team1} vs ${state.team2}, 2nd ODI",
                    color = CrexColors.TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(20.dp)) {
                Icon(
                    imageVector = Icons.Outlined.NotificationsOff,
                    contentDescription = "Notifications",
                    tint = CrexColors.TextSecondary,
                    modifier = Modifier.size(24.dp)
                )
                Icon(
                    imageVector = Icons.Outlined.MoreVert,
                    contentDescription = "More",
                    tint = CrexColors.TextSecondary,
                    modifier = Modifier.size(24.dp)
                )
            }
        }

        // Live Score Section
        LiveScoreCard()
    }
}
