package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Verified
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.ArrowDropDown
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.matches.CrexColors

@Composable
fun CommentaryTab(modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White),
        contentPadding = PaddingValues(bottom = 80.dp)
    ) {
        // Dynamic Message
        item {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(CrexColors.Background) // Dark background extension
                    .padding(bottom = 6.dp) // Little extra padding to push it down if needed
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(CrexColors.AccentYellow.copy(alpha = 0.1f))
                        .padding(vertical = 6.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "PAK need 95 runs in 108 balls",
                        color = CrexColors.AccentYellow,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }

        // Over Tracker
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(CrexColors.Background) // Extended dark background
                    .padding(vertical = 12.dp), // Removed horizontal padding here to allow full scroll bleed
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(androidx.compose.foundation.rememberScrollState())
                        .padding(horizontal = 16.dp), // Added padding here for inner content
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            Box(
                                modifier = Modifier
                                    .size(16.dp)
                                    .clip(CircleShape)
                                    .background(CrexColors.AccentBlue.copy(alpha = 0.3f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Text("4", color = CrexColors.AccentBlue, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                            }
                            Text("= 5", color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                        }
                        Text("Over 32", color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                    }

                    Spacer(modifier = Modifier.width(16.dp))

                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("1", "0", "4", "1", "W", "1").forEach { ball ->
                            Box(
                                modifier = Modifier
                                    .size(24.dp)
                                    .clip(CircleShape)
                                    .border(1.dp, CrexColors.Border, CircleShape)
                                    .then(if (ball == "W") Modifier.background(CrexColors.AccentRed) else if (ball == "4") Modifier.background(CrexColors.AccentBlue.copy(alpha = 0.3f)) else Modifier),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(ball, color = if (ball == "W") Color.White else if (ball == "4") CrexColors.AccentBlue else CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = if (ball == "W" || ball == "4") FontWeight.Bold else FontWeight.Medium)
                            }
                        }
                        Text("= 7", color = CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                    }

                    Spacer(modifier = Modifier.width(16.dp))

                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                        Text("Over 33", color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                    }

                    Spacer(modifier = Modifier.width(16.dp))

                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("2", "2", "1", "0", "0").forEach { ball ->
                            Box(
                                modifier = Modifier
                                    .size(24.dp)
                                    .clip(CircleShape)
                                    .border(1.dp, CrexColors.Border, CircleShape),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(ball, color = CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                        Box(
                            modifier = Modifier
                                .size(24.dp)
                                .clip(CircleShape)
                                .background(CrexColors.AccentRed),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("W", color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                        Text("= 5", color = CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                    }

                    Spacer(modifier = Modifier.width(16.dp))

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Overs", color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                        Icon(
                            imageVector = Icons.Outlined.ChevronRight,
                            contentDescription = "Overs",
                            tint = CrexColors.TextSecondary,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }
        }

        // Batting List
        item {
            Column(modifier = Modifier.padding(top = 8.dp).background(Color.White)) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = Color(0xFFE5E7EB), start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BATTER", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("R", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
                        Text("B", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
                        Text("4S", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
                        Text("6S", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }
                
                BatterRow(name = "A Minhas", runs = "33", balls = "43", fours = "4", sixes = "0", sr = "76.74")
                BatterRow(name = "Shadab Khan", runs = "36", balls = "65", fours = "0", sixes = "0", sr = "55.38")
            }
        }

        // Stats Row
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text("P'Ship: 59 (93)", color = Color(0xFF6B7280), fontSize = 10.sp, fontWeight = FontWeight.Medium)
                Text("Last wkt: G Ghori 37 (48)", color = Color(0xFF6B7280), fontSize = 10.sp, fontWeight = FontWeight.Medium)
            }
        }

        // Bowling List
        item {
            Column(modifier = Modifier.padding(top = 8.dp).background(Color.White)) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = Color(0xFFE5E7EB), start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BOWLER", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("W-R", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
                        Text("OV", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(32.dp), textAlign = TextAlign.Center)
                        Text("ECON", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }
                
                BowlerRow(name = "Nathan Ellis", figures = "2-28", overs = "6.0", econ = "4.67")
            }
        }

        // Filters
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Filters", color = Color(0xFF0369A1), fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                Icon(
                    imageVector = Icons.Outlined.ExpandMore,
                    contentDescription = "Expand",
                    tint = Color(0xFF0369A1),
                    modifier = Modifier.size(16.dp)
                )
            }
        }

        // Event Cards
        item {
            Column(modifier = Modifier.padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                // Batting Review
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(Color(0xFFFAFAFA))
                        .border(1.dp, Color(0xFFE5E7EB), RoundedCornerShape(12.dp))
                        .padding(16.dp)
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(CircleShape)
                                .border(1.dp, Color(0xFFE5E7EB), CircleShape)
                                .background(Color.White),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("🇵🇰", fontSize = 16.sp)
                        }
                        Column {
                            Text("Batting Review", color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                            Text("On field decision: OUT", color = Color(0xFFDC2626), fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        }
                    }
                }

                // Commentary Text
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(Color(0xFFFAFAFA))
                        .border(1.dp, Color(0xFFE5E7EB), RoundedCornerShape(12.dp))
                        .padding(16.dp)
                ) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Icon(
                            imageVector = Icons.Outlined.ChatBubbleOutline,
                            contentDescription = "Commentary",
                            tint = Color(0xFF6B7280),
                            modifier = Modifier.size(20.dp)
                        )
                        Column {
                            Text(
                                "Appeal for LBW and the finger goes up.",
                                color = Color(0xFF111827),
                                fontSize = 14.sp,
                                lineHeight = 20.sp
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("REVIEW LEFT:", color = Color(0xFF9CA3AF), fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
                                Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                                    Text(
                                        buildAnnotatedString {
                                            append("AUS ")
                                            withStyle(SpanStyle(color = Color(0xFF111827))) { append("1") }
                                        },
                                        color = Color(0xFF6B7280), fontSize = 10.sp, fontWeight = FontWeight.Bold
                                    )
                                    Text(
                                        buildAnnotatedString {
                                            append("PAK ")
                                            withStyle(SpanStyle(color = Color(0xFF111827))) { append("2") }
                                        },
                                        color = Color(0xFF6B7280), fontSize = 10.sp, fontWeight = FontWeight.Bold
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun BatterRow(name: String, runs: String, balls: String, fours: String, sixes: String, sr: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = Color(0xFFE5E7EB), start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(Color(0xFFF3F4F6)),
                contentAlignment = Alignment.Center
            ) {
                Text(name.first().toString(), color = Color(0xFF6B7280))
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.Medium)
                }
                Text("SR $sr", color = Color(0xFF6B7280), fontSize = 10.sp, letterSpacing = 1.sp)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(runs, color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
            Text(balls, color = Color(0xFF6B7280), fontSize = 14.sp, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
            Text(fours, color = Color(0xFF6B7280), fontSize = 14.sp, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
            Text(sixes, color = Color(0xFF6B7280), fontSize = 14.sp, modifier = Modifier.width(28.dp), textAlign = TextAlign.Center)
            Icon(
                imageVector = Icons.Outlined.ArrowDropDown,
                contentDescription = "Expand",
                tint = Color(0xFF9CA3AF),
                modifier = Modifier.size(24.dp)
            )
        }
    }
}

@Composable
fun BowlerRow(name: String, figures: String, overs: String, econ: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = Color(0xFFE5E7EB), start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(Color(0xFFF3F4F6)),
                contentAlignment = Alignment.Center
            ) {
                Text(name.first().toString(), color = Color(0xFF6B7280))
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    Icon(
                        imageVector = Icons.Outlined.Verified,
                        contentDescription = "Verified",
                        tint = Color(0xFF6B7280),
                        modifier = Modifier.size(10.dp)
                    )
                }
                Text("BOWLER", color = Color(0xFF6B7280), fontSize = 10.sp, letterSpacing = 1.sp)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(36.dp)) {
                val split = figures.split("-")
                Text("${split[0]}-", color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                Text(split[1], color = Color(0xFF111827), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
            }
            Text(overs, color = Color(0xFF6B7280), fontSize = 14.sp, modifier = Modifier.width(32.dp), textAlign = TextAlign.Center)
            Text(econ, color = Color(0xFF6B7280), fontSize = 14.sp, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
            Icon(
                imageVector = Icons.Outlined.ArrowDropDown,
                contentDescription = "Expand",
                tint = Color(0xFF9CA3AF),
                modifier = Modifier.size(24.dp)
            )
        }
    }
}
