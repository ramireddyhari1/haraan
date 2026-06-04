package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.example.thanna.ui.matches.*

@Composable
fun LiveTab(modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            LiveScoreCard()
        }
        item {
            CrexMiniMatchCard(
                team1 = "🏴 ENG", score1 = "142/6 (18.4)",
                team2 = "🇿🇦 SA", score2 = "Yet to bat",
                status = "LIVE • T20I", statusColor = CrexColors.LivePulse
            )
        }
        item {
            CrexMiniMatchCard(
                team1 = "🇳🇿 NZ", score1 = "-",
                team2 = "🇵🇰 PAK", score2 = "-",
                status = "Tomorrow 14:00", statusColor = CrexColors.AccentBlue
            )
        }
    }
}
