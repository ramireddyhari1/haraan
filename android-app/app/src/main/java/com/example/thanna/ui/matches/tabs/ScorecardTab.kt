package com.example.thanna.ui.matches.tabs

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.KeyboardArrowDown
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.SquadMember
import com.example.thanna.ui.matches.*

/**
 * Real-data scorecard, styled after Crex. When the backend has a ball-by-ball log it
 * renders the full replayed card for EVERY innings (all batters, all bowlers, extras,
 * fall of wickets), each innings behind a tap-to-collapse header bar. Before any ball is
 * bowled it falls back to the entered squads as a roster.
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

/**
 * The real, replayed scorecard. Every innings is a collapsible block: a tinted header bar
 * (logo · score · overs · chevron) over the batting → did-not-bat → bowling → fall-of-wickets
 * detail. All innings start expanded so nothing is hidden; tapping a bar collapses it.
 */
private fun LazyListScope.fullScorecard(state: MatchUiState, cards: List<InningsCard>) {
    cards.forEachIndexed { index, card ->
        item(key = "innings-${card.number}-$index") {
            InningsBlock(state, card)
        }
    }
}

@Composable
private fun InningsBlock(state: MatchUiState, card: InningsCard) {
    var expanded by remember(card.number) { mutableStateOf(true) }
    val chevronRotation by animateFloatAsState(if (expanded) 180f else 0f, label = "chevron")
    val view = androidx.compose.ui.platform.LocalView.current

    // Resolve the batting side's logo from the match teams (battingTeam is 1 or 2).
    val logoUrl = if (card.battingTeam == 2) state.team2Logo else state.team1Logo
    val teamCode = if (card.battingTeam == 2) state.team2 else state.team1

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
    ) {
        // ── Header bar (tap to collapse) ──
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { hapticTick(view); expanded = !expanded }
                .background(CrexColors.AccentBlue.copy(alpha = 0.06f))
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            TeamLogo(team = teamCode, logoUrl = logoUrl, modifier = Modifier.size(30.dp))
            Spacer(Modifier.width(10.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(card.battingName, color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black)
                Text("Innings ${card.number}", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    card.scoreLine, color = CrexColors.TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Black,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
                Text("${card.overs} ov · RR ${card.runRate}", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
            }
            Icon(
                imageVector = Icons.Rounded.KeyboardArrowDown,
                contentDescription = if (expanded) "Collapse" else "Expand",
                tint = CrexColors.TextMuted,
                modifier = Modifier.padding(start = 6.dp).size(22.dp).rotate(chevronRotation)
            )
        }

        AnimatedVisibility(visible = expanded) {
            Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
                BattingSection(card)

                // Did not bat — squad members who never came to the crease this innings.
                val battingSquad = if (card.battingTeam == 2) state.awaySquad else state.homeSquad
                val batted = card.batters.map { it.name }.toSet()
                val dnb = battingSquad.map { it.name }.filter { it.isNotBlank() && it !in batted }
                if (dnb.isNotEmpty()) {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
                        Text("Did not bat: ", color = CrexColors.TextMuted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        Text(dnb.joinToString(", "), color = CrexColors.TextSecondary, fontSize = 12.sp, modifier = Modifier.weight(1f))
                    }
                }

                if (card.bowlers.isNotEmpty()) BowlingSection(card)
                if (card.fallOfWickets.isNotEmpty()) FallOfWicketsSection(card)
            }
        }
    }
}

// ────────────────────────────── Batting ──────────────────────────────

@Composable
private fun BattingSection(card: InningsCard) {
    Column(Modifier.fillMaxWidth()) {
        // Header strip
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(CrexColors.Background)
                .padding(horizontal = 10.dp, vertical = 7.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("BATTER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp, modifier = Modifier.weight(1f))
            listOf("R", "B", "4s", "6s", "SR").forEachIndexed { i, h ->
                Text(h, color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End,
                    modifier = Modifier.width(if (i == 4) 48.dp else 30.dp))
            }
        }

        card.batters.forEachIndexed { index, b ->
            ScorecardBatterRow(b)
            if (index < card.batters.lastIndex) {
                Box(Modifier.fillMaxWidth().padding(horizontal = 10.dp).height(1.dp).background(CrexColors.Border.copy(0.5f)))
            }
        }

        Box(Modifier.fillMaxWidth().padding(top = 4.dp).height(1.dp).background(CrexColors.Border))

        // Extras — Crex order: b, lb, w, nb
        if (card.extras.total > 0) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text("Extras", color = CrexColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                Text(
                    "${card.extras.total}  (b ${card.extras.byes}, lb ${card.extras.legByes}, w ${card.extras.wides}, nb ${card.extras.noBalls})",
                    color = CrexColors.TextSecondary, fontSize = 12.sp
                )
            }
            Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
        }

        // Total
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text("Total", color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black)
                Text("${card.overs} overs · RR ${card.runRate}", color = CrexColors.TextMuted, fontSize = 11.sp)
            }
            Text(card.scoreLine, color = CrexColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.Black,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"))
        }
    }
}

@Composable
private fun ScorecardBatterRow(b: ScorecardBatter) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
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
        // Runs emphasised (bold, green while not out — the striker/holder of the crease)
        Text("${b.runs}", color = if (b.out) CrexColors.TextPrimary else CrexColors.AccentGreen,
            fontSize = 14.sp, fontWeight = FontWeight.Black, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.balls}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.fours}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text("${b.sixes}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(30.dp))
        Text(b.strikeRate, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(48.dp))
    }
}

// ────────────────────────────── Bowling ──────────────────────────────

@Composable
private fun BowlingSection(card: InningsCard) {
    Column(Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(CrexColors.Background)
                .padding(horizontal = 10.dp, vertical = 7.dp)
        ) {
            Text("BOWLER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp, modifier = Modifier.weight(1f))
            listOf("O", "M", "R", "W", "ER").forEachIndexed { i, h ->
                Text(h, color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End, modifier = Modifier.width(if (i == 4) 46.dp else 28.dp))
            }
        }
        card.bowlers.forEachIndexed { index, bw ->
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(bw.name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                Text(bw.overs, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.maidens}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.runs}", color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text("${bw.wickets}", color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Text(bw.econ, color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.End, modifier = Modifier.width(46.dp))
            }
            if (index < card.bowlers.lastIndex) {
                Box(Modifier.fillMaxWidth().padding(horizontal = 10.dp).height(1.dp).background(CrexColors.Border.copy(0.5f)))
            }
        }
    }
}

// ─────────────────────────── Fall of wickets ──────────────────────────

@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
private fun FallOfWicketsSection(card: InningsCard) {
    Column(Modifier.fillMaxWidth()) {
        Text("FALL OF WICKETS", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp, modifier = Modifier.padding(horizontal = 10.dp))
        Spacer(Modifier.height(10.dp))
        FlowRow(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            card.fallOfWickets.forEach { f ->
                Column(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(CrexColors.AccentRed.copy(alpha = 0.06f))
                        .border(1.dp, CrexColors.AccentRed.copy(alpha = 0.25f), RoundedCornerShape(10.dp))
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text("${f.score}-${f.wicketNo}", color = CrexColors.AccentRed, fontSize = 13.sp, fontWeight = FontWeight.Black,
                        style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"))
                    Text("${f.batter} · ${f.over}", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                }
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
