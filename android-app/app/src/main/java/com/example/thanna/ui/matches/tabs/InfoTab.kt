package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.shape.CircleShape
import com.example.thanna.data.SquadMember
import com.example.thanna.ui.matches.CrexColors
import com.example.thanna.ui.matches.InningsCard
import com.example.thanna.ui.matches.MatchUiState
import com.example.thanna.ui.matches.TeamLogo

/**
 * Match info — real data only. Shows the two sides, the format, venue and status as
 * actually stored for this match, plus per-innings summaries and the squads.
 */
@Composable
fun InfoTab(state: MatchUiState, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(vertical = 14.dp)
    ) {
        item { MatchupCard(state) }
        item { MatchDetailsCard(state) }

        // Per-innings summary — score, run rate, top scorer & best bowler.
        state.inningsCards.forEach { card ->
            item { InningsSummaryCard(card) }
        }

        // Squads
        val squads = listOf(
            (state.team1FullName.ifBlank { state.team1 }) to state.homeSquad,
            (state.team2FullName.ifBlank { state.team2 }) to state.awaySquad,
        )
        squads.forEach { (name, squad) ->
            if (squad.isNotEmpty()) item { SquadCard(name, squad) }
        }
    }
}

@Composable
private fun InningsSummaryCard(card: InningsCard) {
    val topBat = card.batters.maxByOrNull { it.runs }
    val topBowl = card.bowlers.maxByOrNull { it.wickets * 100 - it.runs }
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(18.dp)
    ) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Bottom) {
            Column {
                Text("INNINGS ${card.number}", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text(card.battingName, color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    card.scoreLine, color = CrexColors.TextPrimary, fontSize = 22.sp,
                    fontFamily = com.example.thanna.theme.ArchivoDisplay,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
                Text("${card.overs} ov · RR ${card.runRate}", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
            }
        }
        if (topBat != null && topBat.runs > 0) {
            Spacer(Modifier.height(12.dp))
            HighlightRow("Top scorer", "${topBat.name}  ${topBat.runs} (${topBat.balls})")
        }
        if (topBowl != null && topBowl.wickets > 0) {
            Spacer(Modifier.height(8.dp))
            HighlightRow("Best bowler", "${topBowl.name}  ${topBowl.wickets}-${topBowl.runs} (${topBowl.overs})")
        }
    }
}

@Composable
private fun HighlightRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, color = CrexColors.TextMuted, fontSize = 12.sp)
        Text(value, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun SquadCard(teamName: String, squad: List<SquadMember>) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(18.dp)
    ) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(teamName.uppercase(), color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Text("${squad.size} players", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        }
        Spacer(Modifier.height(12.dp))
        // Two players per row (side by side) instead of a long single column.
        squad.chunked(2).forEachIndexed { rowIndex, pair ->
            if (rowIndex > 0) Spacer(Modifier.height(12.dp))
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                SquadMemberCell(pair[0], Modifier.weight(1f))
                Spacer(Modifier.width(10.dp))
                if (pair.size > 1) {
                    SquadMemberCell(pair[1], Modifier.weight(1f))
                } else {
                    Spacer(Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun SquadMemberCell(m: SquadMember, modifier: Modifier = Modifier) {
    Row(modifier = modifier, verticalAlignment = Alignment.CenterVertically) {
        Box(
            modifier = Modifier.size(26.dp).clip(CircleShape).background(CrexColors.Background).border(1.dp, CrexColors.Border, CircleShape),
            contentAlignment = Alignment.Center
        ) {
            Text(m.name.firstOrNull()?.uppercase() ?: "?", color = CrexColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.width(8.dp))
        Text(m.name, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, modifier = Modifier.weight(1f, fill = false))
        if (m.isGuest || m.id.isBlank()) {
            Spacer(Modifier.width(6.dp))
            Box(Modifier.clip(RoundedCornerShape(5.dp)).background(CrexColors.Border).padding(horizontal = 5.dp, vertical = 1.dp)) {
                Text("G", color = CrexColors.TextSecondary, fontSize = 8.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun MatchupCard(state: MatchUiState) {
    val team1Full = state.team1FullName.ifBlank { state.team1 }
    val team2Full = state.team2FullName.ifBlank { state.team2 }
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(18.dp)
    ) {
        if (state.competition.isNotBlank()) {
            Text(state.competition, color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Spacer(Modifier.height(14.dp))
        }
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Row(modifier = Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                TeamLogo(team = state.team1, logoUrl = state.team1Logo, modifier = Modifier.size(34.dp))
                Text(team1Full.uppercase(), color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
            Text("vs", color = CrexColors.TextMuted, fontSize = 12.sp, modifier = Modifier.padding(horizontal = 8.dp))
            Row(modifier = Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(team2Full.uppercase(), color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                TeamLogo(team = state.team2, logoUrl = state.team2Logo, modifier = Modifier.size(34.dp))
            }
        }
    }
}

@Composable
private fun MatchDetailsCard(state: MatchUiState) {
    // Only rows we actually have values for — an empty field is omitted, not faked.
    val rows = buildList {
        if (state.status.isNotBlank()) add("Status" to state.status)
        if (state.competition.isNotBlank()) add("Format" to state.competition)
        if (state.venue.isNotBlank()) add("Venue" to state.venue)
        if (state.toss.isNotBlank()) add("Toss" to state.toss)
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(18.dp)
    ) {
        Text("MATCH DETAILS", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Spacer(Modifier.height(12.dp))
        if (rows.isEmpty()) {
            Text("Details will appear here once the match is set.", color = CrexColors.TextSecondary, fontSize = 13.sp)
        } else {
            rows.forEachIndexed { i, (label, value) ->
                if (i > 0) Spacer(Modifier.height(12.dp))
                Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
                    Text(label, color = CrexColors.TextMuted, fontSize = 13.sp, modifier = Modifier.width(88.dp))
                    Text(value, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                }
            }
        }
    }
}
