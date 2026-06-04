package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.matches.CrexColors
import com.example.thanna.ui.matches.CrexSeriesCard
import com.example.thanna.ui.matches.MomentumGraph

@Composable
fun InfoTab(modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp, vertical = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Series Info
        item {
            CrexSeriesCard()
        }

        // 1. Momentum Visualization
        item {
            MomentumGraph()
        }

        // 2. Key Moments / Tactical Breakdown
        item {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(CrexColors.Surface)
                    .padding(20.dp)
            ) {
                Text(
                    text = "KEY STORYLINE",
                    color = CrexColors.TextSecondary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.sp
                )
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = "India looks in absolute control. Australia needs a miracle to chase this down.",
                    color = CrexColors.TextPrimary,
                    fontSize = 16.sp,
                    lineHeight = 24.sp
                )
            }
        }

        // 3. Match Details
        item {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(CrexColors.Surface)
                    .padding(20.dp)
            ) {
                Text(
                    text = "MATCH INFO",
                    color = CrexColors.TextSecondary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.sp
                )
                Spacer(modifier = Modifier.height(16.dp))
                
                InfoRow(label = "Toss", value = "India won the toss and opted to bat")
                InfoRow(label = "Venue", value = "Wankhede Stadium, Mumbai")
                InfoRow(label = "Umpires", value = "Richard Illingworth, Marais Erasmus")
            }
        }
        
        // 4. Playing XI
        item {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(16.dp))
                        .background(CrexColors.Surface)
                        .padding(16.dp)
                ) {
                    Text(
                        text = "IND XI",
                        color = CrexColors.TextSecondary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    listOf(
                        "Rohit Sharma (c)", "Virat Kohli", "Suryakumar Yadav",
                        "Rishabh Pant (wk)", "Hardik Pandya", "Ravindra Jadeja",
                        "Axar Patel", "Kuldeep Yadav", "Jasprit Bumrah",
                        "Mohammed Siraj", "Arshdeep Singh"
                    ).forEach { player ->
                        Text(
                            text = player,
                            color = CrexColors.TextPrimary,
                            fontSize = 14.sp,
                            modifier = Modifier.padding(vertical = 4.dp)
                        )
                    }
                }

                Column(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(16.dp))
                        .background(CrexColors.Surface)
                        .padding(16.dp)
                ) {
                    Text(
                        text = "AUS XI",
                        color = CrexColors.TextSecondary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    listOf(
                        "David Warner", "Travis Head", "Marnus Labuschagne",
                        "Steve Smith", "Glenn Maxwell", "Josh Inglis (wk)",
                        "Pat Cummins (c)", "Mitchell Starc", "Josh Hazlewood",
                        "Adam Zampa", "Nathan Ellis"
                    ).forEach { player ->
                        Text(
                            text = player,
                            color = CrexColors.TextPrimary,
                            fontSize = 14.sp,
                            modifier = Modifier.padding(vertical = 4.dp)
                        )
                    }
                }
            }
        }
        
        item {
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
fun InfoRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Text(text = label, color = CrexColors.TextSecondary, fontSize = 14.sp, modifier = Modifier.weight(0.3f))
        Text(text = value, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(0.7f))
    }
}
