package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.matches.CrexColors

@Composable
fun FantasyTab(modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            FantasyCard("Top Fantasy Points", "Suryakumar Yadav", "142 pts")
        }
        item {
            FantasyCard("Key Bowler", "Mitchell Starc", "88 pts")
        }
        item {
            FantasyCard("Captaincy Pick", "Rohit Sharma", "Predicted 120+ pts")
        }
    }
}

@Composable
fun FantasyCard(title: String, player: String, points: String) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(title, fontWeight = FontWeight.Bold, color = CrexColors.TextSecondary, fontSize = 14.sp)
            Spacer(modifier = Modifier.height(12.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(player, fontWeight = FontWeight.Medium, color = CrexColors.TextPrimary, fontSize = 16.sp)
                Text(points, fontWeight = FontWeight.ExtraBold, color = CrexColors.AccentYellow, fontSize = 16.sp)
            }
        }
    }
}
