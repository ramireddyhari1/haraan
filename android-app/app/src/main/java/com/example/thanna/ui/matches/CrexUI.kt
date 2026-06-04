package com.example.thanna.ui.matches

import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.*
import androidx.compose.ui.draw.*
import androidx.compose.ui.geometry.*
import androidx.compose.ui.graphics.*
import androidx.compose.ui.graphics.drawscope.*
import androidx.compose.ui.text.font.*
import androidx.compose.ui.text.style.*
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.unit.*

// ─────────────────────────────────────────────
//  DESIGN TOKENS
// ─────────────────────────────────────────────
object CrexColors {
    val Background      = Color(0xFF0A0E14) // bg-main
    val Surface         = Color(0xFF1C2229) // bg-card
    val SurfaceElevated = Color(0xFF1C2229) 
    val Border          = Color(0x1AFFFFFF) // outline-subtle
    val AccentGreen     = Color(0xFF38BDF8) // accent-blue (mapped from green)
    val AccentYellow    = Color(0xFFFACC15) // accent-gold
    val AccentRed       = Color(0xFFF87171) // accent-coral
    val AccentBlue      = Color(0xFF38BDF8) // accent-blue
    val TextPrimary     = Color(0xFFFFFFFF) // text-primary
    val TextSecondary   = Color(0xFFA0A5AD) // text-secondary
    val TextMuted       = Color(0xFF76777D)
    val LivePulse       = Color(0xFFF87171) // accent-coral
    val SixBall         = Color(0xFFFACC15) // accent-gold
    val FourBall        = Color(0xFF38BDF8) // accent-blue
    val WicketBall      = Color(0xFFF87171) // accent-coral
    val DotBall         = Color(0xFF37474F)
    val NormalBall      = Color(0xFF546E7A)
}

// ─────────────────────────────────────────────
//  1. LIVE SCORE CARD
// ─────────────────────────────────────────────
@Composable
fun LiveScoreCard(modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
            .padding(top = 16.dp, start = 16.dp, end = 16.dp, bottom = 8.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(bottom = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Bottom // Align bottom to push LBW check down
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                // Team logo
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .border(1.dp, Color.White.copy(alpha = 0.2f), CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Text("🇵🇰", fontSize = 24.sp)
                }
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text("PAK", color = CrexColors.TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(4.dp))
                                .background(CrexColors.AccentRed.copy(alpha = 0.2f))
                                .padding(horizontal = 4.dp, vertical = 2.dp)
                        ) {
                            Text("PP", color = CrexColors.AccentRed, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Row(verticalAlignment = Alignment.Bottom, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("137-7", color = CrexColors.AccentBlue, fontSize = 24.sp, fontWeight = FontWeight.Bold)
                        Text("32.0", color = CrexColors.TextSecondary, fontSize = 12.sp, modifier = Modifier.padding(bottom = 4.dp))
                    }
                }
            }
            Column(horizontalAlignment = Alignment.End) {
                // Not mapping volume_off icon right now, just skipping or adding a generic icon
                Spacer(modifier = Modifier.height(28.dp)) // Increased space on top
                Box(modifier = Modifier.drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, 0f), end = Offset(0f, size.height), strokeWidth = 1.dp.toPx()) }.padding(start = 16.dp)) {
                    Text("LBW Check", color = CrexColors.AccentYellow, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, style = androidx.compose.ui.text.TextStyle(fontStyle = androidx.compose.ui.text.font.FontStyle.Italic, letterSpacing = (-1).sp))
                }
            }
        }
        
        // Run Rate Row
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, 0f), end = Offset(size.width, 0f), strokeWidth = 1.dp.toPx()) }
                .padding(top = 10.dp, bottom = 4.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                    Text(
                        androidx.compose.ui.text.buildAnnotatedString {
                            append("CRR: ")
                            withStyle(SpanStyle(color = CrexColors.TextPrimary)) { append("4.28") }
                        },
                        color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium
                    )
                    Text(
                        androidx.compose.ui.text.buildAnnotatedString {
                            append("RRR: ")
                            withStyle(SpanStyle(color = CrexColors.TextPrimary)) { append("5.28") }
                        },
                        color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium
                    )
                }
                Text(
                    androidx.compose.ui.text.buildAnnotatedString {
                        append("Target: ")
                        withStyle(SpanStyle(color = CrexColors.TextPrimary)) { append("232") }
                    },
                    color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium
                )
            }
        }
    }
}

@Composable
private fun BatsmanMini(name: String, runs: Int, balls: Int, sr: Float, isStriker: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        if (isStriker) {
            Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(CrexColors.AccentGreen))
        }
        Column {
            Text(
                name + if (isStriker) " 🏏" else "",
                color = if (isStriker) CrexColors.TextPrimary else CrexColors.TextSecondary,
                fontSize = 12.sp, fontWeight = FontWeight.SemiBold
            )
            Text("$runs ($balls) • SR $sr", color = CrexColors.TextMuted, fontSize = 10.sp)
        }
    }
}

@Composable
private fun BallDot(label: String) {
    val bg = when (label) {
        "6" -> CrexColors.SixBall
        "4" -> CrexColors.FourBall
        "W" -> CrexColors.WicketBall
        "•" -> CrexColors.DotBall
        else -> CrexColors.NormalBall
    }
    Box(
        modifier = Modifier
            .size(28.dp)
            .clip(CircleShape)
            .background(bg.copy(alpha = 0.2f))
            .border(1.dp, bg, CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(label, color = bg, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun CrexTeamFlag(emoji: String, size: Dp) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(CircleShape)
            .background(CrexColors.SurfaceElevated)
            .border(1.dp, CrexColors.Border, CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(emoji, fontSize = (size.value * 0.55f).sp)
    }
}

// ─────────────────────────────────────────────
//  2. MATCH SUMMARY CARD
// ─────────────────────────────────────────────
@Composable
fun MatchSummaryCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFF111827), Color(0xFF0D1320))
                    )
                )
        ) {
            // Gradient header banner
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp)
                    .background(
                        Brush.linearGradient(
                            listOf(Color(0xFF1A2A4A), Color(0xFF0D1B2E))
                        )
                    )
                    .padding(horizontal = 16.dp),
                contentAlignment = Alignment.CenterStart
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("MATCH RESULT", color = CrexColors.AccentYellow, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(20.dp))
                            .background(CrexColors.AccentGreen.copy(alpha = 0.15f))
                            .padding(horizontal = 12.dp, vertical = 4.dp)
                    ) {
                        Text("COMPLETED", color = CrexColors.AccentGreen, fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
                    }
                }
            }

            Column(modifier = Modifier.padding(16.dp)) {
                // Teams + scores
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TeamScoreColumn(flag = "🇮🇳", name = "India", score = "186/3", overs = "20.0", isWinner = true)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("VS", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Black)
                    }
                    TeamScoreColumn(flag = "🇦🇺", name = "Australia", score = "172/8", overs = "20.0", isWinner = false)
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Result banner
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(
                            Brush.horizontalGradient(
                                listOf(CrexColors.AccentGreen.copy(0.2f), CrexColors.AccentBlue.copy(0.1f))
                            )
                        )
                        .padding(vertical = 10.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "🏆  India won by 14 runs",
                        color = CrexColors.TextPrimary,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(14.dp))

                // Key performers
                Text("KEY PERFORMERS", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(10.dp))

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    PerformerChip(modifier = Modifier.weight(1f), label = "TOP BAT", name = "V. Kohli", stat = "82 (54)", color = CrexColors.AccentYellow)
                    PerformerChip(modifier = Modifier.weight(1f), label = "TOP BOWL", name = "B. Kumar", stat = "3/24", color = CrexColors.AccentBlue)
                    PerformerChip(modifier = Modifier.weight(1f), label = "POTM", name = "V. Kohli", stat = "82 • 1ct", color = CrexColors.AccentGreen)
                }

                Spacer(modifier = Modifier.height(14.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(12.dp))

                // Fall of wickets
                Text("FALL OF WICKETS", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                FallOfWicketsBar(wickets = listOf(28, 64, 112, 140, 155, 158, 162, 168, 172))
                Spacer(modifier = Modifier.height(4.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("0", color = CrexColors.TextMuted, fontSize = 9.sp)
                    Text("172", color = CrexColors.TextMuted, fontSize = 9.sp)
                }
            }
        }
    }
}

@Composable
private fun TeamScoreColumn(flag: String, name: String, score: String, overs: String, isWinner: Boolean) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        CrexTeamFlag(emoji = flag, size = 40.dp)
        Spacer(modifier = Modifier.height(6.dp))
        Text(name, color = if (isWinner) CrexColors.TextPrimary else CrexColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(2.dp))
        Text(score, color = if (isWinner) CrexColors.AccentGreen else CrexColors.TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.Black)
        Text("($overs ov)", color = CrexColors.TextMuted, fontSize = 10.sp)
        if (isWinner) {
            Spacer(modifier = Modifier.height(4.dp))
            Text("🏆 Winner", color = CrexColors.AccentYellow, fontSize = 10.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun PerformerChip(modifier: Modifier, label: String, name: String, stat: String, color: Color) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(color.copy(alpha = 0.1f))
            .border(1.dp, color.copy(alpha = 0.3f), RoundedCornerShape(12.dp))
            .padding(10.dp)
    ) {
        Column {
            Text(label, color = color, fontSize = 8.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
            Spacer(modifier = Modifier.height(4.dp))
            Text(name, color = CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Text(stat, color = CrexColors.TextSecondary, fontSize = 10.sp)
        }
    }
}

// FIX: Draw gradient FIRST, then wicket lines on top
@Composable
private fun FallOfWicketsBar(wickets: List<Int>) {
    val totalRuns = 172f
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(18.dp)
            .clip(RoundedCornerShape(9.dp))
            .background(CrexColors.SurfaceElevated)
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            // Draw gradient background first
            drawRoundRect(
                brush = Brush.horizontalGradient(
                    listOf(CrexColors.AccentBlue.copy(0.4f), CrexColors.AccentGreen.copy(0.4f))
                ),
                cornerRadius = CornerRadius(9.dp.toPx())
            )
            // Then draw wicket markers on top
            wickets.forEach { run ->
                val x = (run / totalRuns) * size.width
                drawLine(
                    color = CrexColors.WicketBall,
                    start = Offset(x, 0f),
                    end = Offset(x, size.height),
                    strokeWidth = 2f
                )
            }
        }
    }
}

// ─────────────────────────────────────────────
//  3. PLAYER STATS CARD
// ─────────────────────────────────────────────
@Composable
fun PlayerStatsCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFF0F1C30), Color(0xFF0A0E1A))
                    )
                )
        ) {
            // Player header
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(80.dp)
                    .background(
                        Brush.linearGradient(
                            listOf(Color(0xFF1A2A4A), Color(0xFF0D1B2E), Color(0xFF0A0E1A))
                        )
                    )
            ) {
                Row(
                    modifier = Modifier.padding(16.dp).fillMaxSize(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Avatar
                    Box(
                        modifier = Modifier
                            .size(50.dp)
                            .clip(CircleShape)
                            .background(
                                Brush.radialGradient(
                                    listOf(Color(0xFF1E3A5F), Color(0xFF0D1B2E))
                                )
                            )
                            .border(2.dp, CrexColors.AccentBlue.copy(0.5f), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("VK", color = CrexColors.AccentBlue, fontSize = 16.sp, fontWeight = FontWeight.Black)
                    }
                    Column {
                        Text("Virat Kohli", color = CrexColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Black)
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            RoleBadge("BAT", CrexColors.AccentYellow)
                            RoleBadge("IND", CrexColors.AccentBlue)
                            RoleBadge("RHB", CrexColors.AccentGreen)
                        }
                    }
                    Spacer(modifier = Modifier.weight(1f))
                    Column(horizontalAlignment = Alignment.End) {
                        Text("ICC Rank", color = CrexColors.TextMuted, fontSize = 9.sp)
                        Text("#3", color = CrexColors.AccentYellow, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    }
                }
            }

            Column(modifier = Modifier.padding(16.dp)) {
                // Format tabs
                var selectedFormat by remember { mutableStateOf(0) }
                val formats = listOf("T20I", "ODI", "TEST")
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .background(CrexColors.SurfaceElevated)
                        .padding(3.dp),
                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                ) {
                    formats.forEachIndexed { idx, fmt ->
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(8.dp))
                                .background(
                                    if (selectedFormat == idx)
                                        Brush.horizontalGradient(listOf(CrexColors.AccentBlue.copy(0.3f), CrexColors.AccentGreen.copy(0.3f)))
                                    else Brush.horizontalGradient(listOf(Color.Transparent, Color.Transparent))
                                )
                                .clickable { selectedFormat = idx }
                                .padding(vertical = 8.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                fmt,
                                color = if (selectedFormat == idx) CrexColors.TextPrimary else CrexColors.TextSecondary,
                                fontSize = 12.sp,
                                fontWeight = if (selectedFormat == idx) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // FIX: Use Column+Row instead of LazyVerticalGrid (no nested scrolling issue)
                val t20Stats = listOf(
                    Triple("Matches", "125", CrexColors.AccentBlue),
                    Triple("Innings", "118", CrexColors.AccentGreen),
                    Triple("Runs", "4188", CrexColors.AccentYellow),
                    Triple("Average", "52.35", CrexColors.AccentGreen),
                    Triple("Strike Rate", "139.6", CrexColors.AccentBlue),
                    Triple("50s / 100s", "38 / 1", CrexColors.AccentYellow)
                )
                // Row 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    t20Stats.take(3).forEach { (label, value, color) ->
                        StatBox(modifier = Modifier.weight(1f), label = label, value = value, accent = color)
                    }
                }
                Spacer(modifier = Modifier.height(8.dp))
                // Row 2
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    t20Stats.drop(3).forEach { (label, value, color) ->
                        StatBox(modifier = Modifier.weight(1f), label = label, value = value, accent = color)
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(14.dp))

                // Form guide (last 5)
                Text("RECENT FORM", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(
                        Pair(82, true), Pair(14, false), Pair(67, true),
                        Pair(4, false), Pair(91, true)
                    ).forEach { (runs, good) ->
                        FormDot(runs = runs.toString(), isGood = good)
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Batting profile bars
                Text("BATTING PROFILE", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                listOf(
                    Triple("Technique", 0.92f, CrexColors.AccentGreen),
                    Triple("Power", 0.74f, CrexColors.AccentYellow),
                    Triple("Consistency", 0.88f, CrexColors.AccentBlue),
                    Triple("Temperament", 0.95f, CrexColors.AccentGreen)
                ).forEach { (skill, value, color) ->
                    SkillBar(label = skill, progress = value, color = color)
                    Spacer(modifier = Modifier.height(6.dp))
                }
            }
        }
    }
}

@Composable
private fun RoleBadge(text: String, color: Color) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(color.copy(alpha = 0.15f))
            .padding(horizontal = 6.dp, vertical = 2.dp)
    ) {
        Text(text, color = color, fontSize = 9.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun StatBox(modifier: Modifier = Modifier, label: String, value: String, accent: Color) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(CrexColors.SurfaceElevated)
            .border(1.dp, accent.copy(0.2f), RoundedCornerShape(10.dp))
            .padding(10.dp)
    ) {
        Column {
            Text(label, color = CrexColors.TextMuted, fontSize = 9.sp, maxLines = 1)
            Spacer(modifier = Modifier.height(3.dp))
            Text(value, color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black)
        }
    }
}

@Composable
private fun FormDot(runs: String, isGood: Boolean) {
    val color = if (isGood) CrexColors.AccentGreen else CrexColors.AccentRed
    Box(
        modifier = Modifier
            .size(44.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(color.copy(0.1f))
            .border(1.dp, color.copy(0.4f), RoundedCornerShape(10.dp)),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(runs, color = color, fontSize = 13.sp, fontWeight = FontWeight.Black)
            Text("runs", color = CrexColors.TextMuted, fontSize = 7.sp)
        }
    }
}

@Composable
private fun SkillBar(label: String, progress: Float, color: Color) {
    val animProg by animateFloatAsState(
        targetValue = progress,
        animationSpec = tween(1000, easing = EaseOutCubic),
        label = "bar"
    )
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(label, color = CrexColors.TextSecondary, fontSize = 10.sp, modifier = Modifier.width(90.dp))
        Box(
            modifier = Modifier
                .weight(1f)
                .height(5.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(CrexColors.SurfaceElevated)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxHeight()
                    .fillMaxWidth(animProg)
                    .clip(RoundedCornerShape(3.dp))
                    .background(
                        Brush.horizontalGradient(listOf(color.copy(0.7f), color))
                    )
            )
        }
        Text("${(progress * 100).toInt()}", color = color, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}

// ─────────────────────────────────────────────
//  4. CREX SCORECARD TABLE (fixed alignment)
// ─────────────────────────────────────────────
@Composable
fun CrexScorecardTable(modifier: Modifier = Modifier) {
    val batsmen = listOf(
        listOf("V. Kohli", "c Maxwell b Hazlewood", "82", "54", "7", "3", "151.8"),
        listOf("R. Sharma", "b Starc", "14", "11", "2", "0", "127.2"),
        listOf("S. Yadav", "not out", "34", "18", "2", "3", "188.8"),
        listOf("H. Pandya", "not out", "28", "16", "1", "3", "175.0"),
    )
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            // Header
            Row(modifier = Modifier.fillMaxWidth()) {
                Text("BATTER", color = CrexColors.TextMuted, fontSize = 9.sp, modifier = Modifier.weight(2f))
                listOf("R", "B", "4s", "6s", "SR").forEach {
                    Text(it, color = CrexColors.TextMuted, fontSize = 9.sp, modifier = Modifier.weight(0.6f), textAlign = TextAlign.End)
                }
            }
            Spacer(modifier = Modifier.height(6.dp))
            HorizontalDivider(color = CrexColors.Border)

            // FIX: Batter name + stats in same Row for proper alignment
            batsmen.forEach { row ->
                Spacer(modifier = Modifier.height(8.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(2f)) {
                        Text(row[0], color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        Text(row[1], color = CrexColors.TextMuted, fontSize = 9.sp)
                    }
                    listOf(row[2], row[3], row[4], row[5], row[6]).forEach { value ->
                        Text(value, color = CrexColors.TextSecondary, fontSize = 11.sp, modifier = Modifier.weight(0.6f), textAlign = TextAlign.End)
                    }
                }
                HorizontalDivider(color = CrexColors.Border.copy(0.5f), modifier = Modifier.padding(top = 8.dp))
            }
        }
    }
}

// ─────────────────────────────────────────────
//  5. SERIES CARD
// ─────────────────────────────────────────────
@Composable
fun CrexSeriesCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("DL20 Trophy 2025", color = CrexColors.AccentYellow, fontSize = 16.sp, fontWeight = FontWeight.Black)
            Text("T20I Series • 5 Matches", color = CrexColors.TextSecondary, fontSize = 12.sp)
            Spacer(modifier = Modifier.height(14.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("🇮🇳", fontSize = 24.sp)
                    Text("India", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text("2", color = CrexColors.AccentGreen, fontSize = 28.sp, fontWeight = FontWeight.Black)
                    Text("wins", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        val matchResults = listOf(CrexColors.AccentGreen, CrexColors.AccentGreen, CrexColors.AccentRed, CrexColors.AccentRed, CrexColors.SurfaceElevated)
                        matchResults.forEach { dotColor ->
                            Box(
                                modifier = Modifier
                                    .size(10.dp)
                                    .clip(CircleShape)
                                    .background(dotColor)
                                    .border(1.dp, CrexColors.Border, CircleShape)
                            )
                        }
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    Text("3 of 5 played", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("🇦🇺", fontSize = 24.sp)
                    Text("Australia", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text("1", color = CrexColors.AccentRed, fontSize = 28.sp, fontWeight = FontWeight.Black)
                    Text("wins", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────
//  MINI MATCH CARD (for lists)
// ─────────────────────────────────────────────
@Composable
fun CrexMiniMatchCard(
    team1: String, score1: String,
    team2: String, score2: String,
    status: String, statusColor: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Row(
            modifier = Modifier.padding(14.dp).fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(team1, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                Spacer(modifier = Modifier.height(2.dp))
                Text(team2, color = CrexColors.TextSecondary, fontSize = 13.sp)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(score1, color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(2.dp))
                Text(score2, color = CrexColors.TextSecondary, fontSize = 12.sp)
            }
            Spacer(modifier = Modifier.width(12.dp))
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(statusColor.copy(0.15f))
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            ) {
                Text(status, color = statusColor, fontSize = 9.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
