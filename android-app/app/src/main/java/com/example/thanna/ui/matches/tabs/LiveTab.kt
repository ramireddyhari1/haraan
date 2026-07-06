package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.matches.*

@Composable
fun LiveTab(state: MatchUiState, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(vertical = 14.dp)
    ) {
        // Chase tracker — once a 2nd innings is underway, show target & required rate.
        if (state.inningsCards.size >= 2 && state.isLive) {
            item { ChaseCard(state) }
        }

        // Main live card: batting + bowler + this over + last 3 overs
        item { LiveActionCard(state) }

        // Win chance bar (only when a probability can actually be known)
        WinProbability.estimate(state)?.let { pct ->
            item { WinChanceCard(state, pct) }
        }
    }
}

@Composable
private fun ChaseCard(state: MatchUiState) {
    val first = state.inningsCards.first()
    val chase = state.inningsCards.last()
    val target = first.runs + 1
    val maxOvers = Regex("(\\d+)").find(state.competition)?.value?.toIntOrNull()?.takeIf { it > 0 } ?: 20
    val ballsBowled = chase.overs.split(".").let { (it.getOrNull(0)?.toIntOrNull() ?: 0) * 6 + (it.getOrNull(1)?.toIntOrNull() ?: 0) }
    val ballsLeft = (maxOvers * 6 - ballsBowled).coerceAtLeast(0)
    val need = (target - chase.runs).coerceAtLeast(0)
    val rrr = if (ballsLeft > 0) String.format("%.2f", need / (ballsLeft / 6.0)) else "—"

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Text(
            "${chase.battingName} need $need off $ballsLeft",
            color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black
        )
        Spacer(Modifier.height(12.dp))
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            ChaseStat("TARGET", "$target")
            ChaseStat("CRR", chase.runRate)
            ChaseStat("REQ RATE", rrr)
        }
    }
}

@Composable
private fun ChaseStat(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = CrexColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Black)
        Spacer(Modifier.height(2.dp))
        Text(label, color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
    }
}


@Composable
private fun OverBallChip(ball: String) {
    val (bg, fg) = when (ball) {
        "6" -> Color(0xFF16A34A) to Color.White
        "4" -> Color(0xFF2563EB) to Color.White
        "W" -> CrexColors.AccentRed to Color.White
        else -> CrexColors.DotBall to CrexColors.TextMuted
    }
    Box(
        modifier = Modifier.size(24.dp).clip(CircleShape).background(bg),
        contentAlignment = Alignment.Center
    ) {
        Text(ball, color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun LiveActionCard(state: MatchUiState) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        // Batting header
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("BATTING", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
            Text("R (B)", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(64.dp))
            Text("SR", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(10.dp))

        BatterLine(state.striker, state.strikerStats, isStriker = true)
        if (state.nonStriker.isNotBlank()) {
            Spacer(Modifier.height(10.dp))
            BatterLine(state.nonStriker, state.nonStrikerStats, isStriker = false)
        }

        // Live partnership (real values only).
        state.partnership?.takeIf { it.balls > 0 || it.runs > 0 }?.let { p ->
            Spacer(Modifier.height(10.dp))
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text("Partnership", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                Text("${p.runs} (${p.balls})", color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
        Spacer(Modifier.height(14.dp))

        // Bowler
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("BOWLER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
            val figures = state.bowlerStats?.let { "${it.wickets}-${it.runs} (${it.overs})" } ?: ""
            Text(
                buildAnnotatedString {
                    withStyle(SpanStyle(color = CrexColors.TextPrimary, fontWeight = FontWeight.Bold)) { append(state.bowler.ifBlank { "—" }) }
                    if (figures.isNotEmpty()) {
                        append("  ")
                        withStyle(SpanStyle(color = CrexColors.TextSecondary)) { append(figures) }
                    }
                },
                fontSize = 13.sp
            )
        }

        if (state.thisOver.isNotEmpty()) {
            Spacer(Modifier.height(14.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
            Spacer(Modifier.height(14.dp))
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text("THIS OVER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    state.thisOver.forEach { OverBallChip(it) }
                }
            }
        }

        // Last 3 overs boxes
        val last3 = state.recentOvers.takeLast(3)
        if (last3.isNotEmpty()) {
            Spacer(Modifier.height(16.dp))
            Text("LAST 3 OVERS", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Spacer(Modifier.height(8.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                last3.forEach { over ->
                    Column(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(12.dp))
                            .background(CrexColors.Background)
                            .border(1.dp, CrexColors.Border, RoundedCornerShape(12.dp))
                            .padding(vertical = 12.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text("${over.runs}", color = CrexColors.TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Black)
                        Spacer(Modifier.height(2.dp))
                        Box(Modifier.width(20.dp).height(2.dp).clip(RoundedCornerShape(1.dp)).background(CrexColors.AccentRed))
                        Spacer(Modifier.height(4.dp))
                        Text(over.label, color = CrexColors.TextMuted, fontSize = 10.sp)
                    }
                }
            }
        }
    }
}

@Composable
private fun BatterLine(name: String, stats: BatterStats?, isStriker: Boolean) {
    val sr = if (stats != null && stats.balls > 0) String.format("%.1f", stats.runs.toFloat() / stats.balls * 100) else "0.0"
    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Row(modifier = Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(
                modifier = Modifier.size(28.dp).clip(CircleShape).background(CrexColors.Background).border(1.dp, CrexColors.Border, CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Text(name.firstOrNull()?.toString() ?: "?", color = CrexColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
            Text(
                name + if (isStriker) "*" else "",
                color = if (isStriker) CrexColors.AccentGreen else CrexColors.TextPrimary,
                fontSize = 14.sp,
                fontWeight = if (isStriker) FontWeight.Bold else FontWeight.SemiBold
            )
        }
        Text(
            stats?.let { "${it.runs} (${it.balls})" } ?: "0 (0)",
            color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.width(64.dp)
        )
        Text(sr, color = CrexColors.TextSecondary, fontSize = 13.sp)
    }
}

@Composable
private fun WinChanceCard(state: MatchUiState, pct: Int) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.Bottom) {
            Text(
                buildAnnotatedString {
                    val winner = if (pct >= 50) state.team1 else state.team2
                    val shown = if (pct >= 50) pct else 100 - pct
                    withStyle(SpanStyle(color = CrexColors.AccentBlue, fontWeight = FontWeight.Black, fontSize = 18.sp)) { append("$shown%") }
                    withStyle(SpanStyle(color = CrexColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 14.sp)) { append("  $winner Win Chance") }
                },
                modifier = Modifier.weight(1f)
            )
            Text("After ${state.overs} ov", color = CrexColors.TextMuted, fontSize = 11.sp)
        }
        Spacer(Modifier.height(12.dp))
        Box(
            modifier = Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(4.dp)).background(CrexColors.NormalBall)
        ) {
            val favoredFrac = maxOf(pct, 100 - pct) / 100f
            Box(
                modifier = Modifier
                    .fillMaxHeight()
                    .fillMaxWidth(favoredFrac)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Brush.horizontalGradient(listOf(CrexColors.AccentBlue, Color(0xFF1D4ED8))))
            )
        }
    }
}
