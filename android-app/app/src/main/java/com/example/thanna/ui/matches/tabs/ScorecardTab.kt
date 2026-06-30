package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.SquadMember
import com.example.thanna.ui.matches.*

/**
 * Real-data scorecard. When the backend has a ball-by-ball log it renders the full
 * replayed card for EVERY innings (all batters, all bowlers, extras, fall of wickets).
 * Before any ball is bowled it falls back to the entered squads as a roster.
 */
@Composable
fun ScorecardTab(state: MatchUiState, modifier: Modifier = Modifier) {
    val cards = state.inningsCards
    val hasSquads = state.homeSquad.isNotEmpty() || state.awaySquad.isNotEmpty()

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(vertical = 14.dp)
    ) {
        when {
            cards.isNotEmpty() -> fullScorecard(state, cards)
            hasSquads -> rosterFallback(state)
            else -> item { EmptyScorecard() }
        }
    }
}

/** The real, replayed scorecard with an innings switcher. */
private fun LazyListScope.fullScorecard(state: MatchUiState, cards: List<InningsCard>) {
    item {
        var selected by remember(cards.size) { mutableStateOf(cards.lastIndex) }
        val card = cards[selected.coerceIn(0, cards.lastIndex)]

        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            // Innings switcher
            if (cards.size > 1) {
                Row(
                    modifier = Modifier.horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    cards.forEachIndexed { i, c ->
                        InningsChip(
                            label = "${c.battingName}  ${c.scoreLine}",
                            selected = i == selected,
                            onClick = { selected = i }
                        )
                    }
                }
            }

            // Innings header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Bottom
            ) {
                Column {
                    Text(card.battingName, color = CrexColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Black)
                    Text("Innings ${card.number}", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
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

            // Batting card
            BattingCard(card)

            // Did not bat — squad members who never came to the crease this innings.
            val battingSquad = if (card.battingTeam == 2) state.awaySquad else state.homeSquad
            val batted = card.batters.map { it.name }.toSet()
            val dnb = battingSquad.map { it.name }.filter { it.isNotBlank() && it !in batted }
            if (dnb.isNotEmpty()) {
                Row(
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Text("Did not bat: ", color = CrexColors.TextMuted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Text(dnb.joinToString(", "), color = CrexColors.TextSecondary, fontSize = 12.sp, modifier = Modifier.weight(1f))
                }
            }

            // Bowling card
            if (card.bowlers.isNotEmpty()) BowlingCard(card)

            // Fall of wickets
            if (card.fallOfWickets.isNotEmpty()) FallOfWicketsCard(card)
        }
    }
}

@Composable
private fun BattingCard(card: InningsCard) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("BATTER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
            listOf("R", "B", "4s", "6s", "SR").forEachIndexed { i, h ->
                Text(h, color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End,
                    modifier = Modifier.width(if (i == 4) 48.dp else 30.dp))
            }
        }
        Spacer(Modifier.height(8.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))

        card.batters.forEachIndexed { index, b ->
            Spacer(Modifier.height(12.dp))
            ScorecardBatterRow(b)
            Spacer(Modifier.height(12.dp))
            if (index < card.batters.lastIndex) Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(0.5f)))
        }

        Spacer(Modifier.height(2.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
        Spacer(Modifier.height(10.dp))

        // Extras
        if (card.extras.total > 0) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Extras", color = CrexColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                Text(
                    "${card.extras.total}  (wd ${card.extras.wides}, nb ${card.extras.noBalls}, b ${card.extras.byes}, lb ${card.extras.legByes})",
                    color = CrexColors.TextSecondary, fontSize = 12.sp
                )
            }
            Spacer(Modifier.height(10.dp))
        }

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Total", color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black)
            Text("${card.scoreLine}  (${card.overs} ov)", color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Black)
        }
    }
}

@Composable
private fun ScorecardBatterRow(b: ScorecardBatter) {
    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                b.name + if (!b.out) " *" else "",
                color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold
            )
            Text(
                if (b.out) b.dismissal else "not out",
                color = if (b.out) CrexColors.TextMuted else CrexColors.AccentGreen,
                fontSize = 11.sp,
                fontWeight = if (b.out) FontWeight.Normal else FontWeight.SemiBold
            )
        }
        Text("${b.runs}", color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.balls}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.fours}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.sixes}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text(b.strikeRate, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(48.dp))
    }
}

@Composable
private fun BowlingCard(card: InningsCard) {
    SectionCard("BOWLING") {
        Row(Modifier.fillMaxWidth()) {
            Text("BOWLER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
            listOf("O", "M", "R", "W", "ECON").forEachIndexed { i, h ->
                Text(h, color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End, modifier = Modifier.width(if (i == 4) 46.dp else 28.dp))
            }
        }
        Spacer(Modifier.height(8.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
        card.bowlers.forEach { bw ->
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(bw.name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                Text(bw.overs, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.maidens}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.runs}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.wickets}", color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text(bw.econ, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(46.dp))
            }
        }
    }
}

@Composable
private fun FallOfWicketsCard(card: InningsCard) {
    SectionCard("FALL OF WICKETS") {
        card.fallOfWickets.forEachIndexed { i, f ->
            if (i > 0) Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "${f.wicketNo}-${f.score}",
                    color = CrexColors.AccentRed, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(64.dp)
                )
                Text(f.batter, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                Text("${f.over} ov", color = CrexColors.TextMuted, fontSize = 12.sp)
            }
        }
    }
}

// ── Fallback: before any ball, just list the entered squads as a roster ──
private fun LazyListScope.rosterFallback(state: MatchUiState) {
    item {
        Text(
            "Squads are set — live figures appear here once scoring starts.",
            color = CrexColors.TextSecondary, fontSize = 13.sp
        )
    }
    listOf(
        (state.team1FullName.ifBlank { state.team1 }) to state.homeSquad,
        (state.team2FullName.ifBlank { state.team2 }) to state.awaySquad,
    ).forEach { (teamName, squad) ->
        if (squad.isNotEmpty()) {
            item {
                SectionCard(teamName.uppercase()) {
                    squad.forEachIndexed { i, m ->
                        if (i > 0) Spacer(Modifier.height(10.dp))
                        RosterRow(m)
                    }
                }
            }
        }
    }
}

@Composable
private fun RosterRow(member: SquadMember) {
    val isGuest = member.isGuest || member.id.isBlank()
    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(member.name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        if (isGuest) {
            Box(Modifier.clip(RoundedCornerShape(5.dp)).background(CrexColors.Border).padding(horizontal = 6.dp, vertical = 1.dp)) {
                Text("GUEST", color = CrexColors.TextSecondary, fontSize = 8.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
            }
        }
    }
}

@Composable
private fun EmptyScorecard() {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text("No scorecard yet", color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(6.dp))
        Text(
            "Squads and scoring will show here once this match has teams set up.",
            color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.Center
        )
    }
}

@Composable
private fun SectionCard(title: String, content: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Text(title, color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Spacer(Modifier.height(10.dp))
        content()
    }
}

@Composable
private fun InningsChip(label: String, selected: Boolean, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) CrexColors.AccentGreen.copy(0.12f) else CrexColors.Surface)
            .border(1.dp, if (selected) CrexColors.AccentGreen else CrexColors.Border, RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 9.dp)
    ) {
        Text(label, color = if (selected) CrexColors.AccentGreen else CrexColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.Bold)
    }
}
