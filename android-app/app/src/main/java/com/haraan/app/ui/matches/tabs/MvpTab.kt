package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.HeroCrest
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.MvpPlayer
import com.haraan.app.ui.matches.TeamLogo

/**
 * MVP tab — who actually decided this match. The ranking is computed on the backend from
 * the same replayed ball log as the scorecard (see LiveMatchController::buildMvp), so the
 * figures here can never drift from the scorecard tab.
 *
 * The top player gets a hero card; everyone who batted or bowled follows in a ranked list
 * with an impact bar drawn relative to the leader. The formula is spelled out at the
 * bottom rather than left as a mystery number.
 */
@Composable
fun MvpTab(state: MatchUiState, modifier: Modifier = Modifier) {
    val players = state.mvp

    if (players.isEmpty()) {
        MvpEmpty(modifier)
        return
    }

    val leader = players.first()
    val topPoints = leader.points.coerceAtLeast(1)

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 24.dp)
    ) {
        item(key = "hero") { MvpHero(state, leader, live = state.isLive) }

        if (players.size > 1) {
            item(key = "rank-header") {
                Text(
                    "IMPACT RANKING",
                    color = CrexColors.TextMuted,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.2.sp,
                    modifier = Modifier.padding(start = 4.dp, top = 4.dp)
                )
            }
        }

        itemsIndexed(players.drop(1), key = { _, p -> p.name }) { index, p ->
            MvpRow(
                state = state,
                player = p,
                rank = index + 2,   // the leader is the hero card above, so rows start at 2
                fraction = (p.points.toFloat() / topPoints).coerceIn(0f, 1f)
            )
        }

        item(key = "formula") { MvpFormula() }
    }
}

/** Team accent so a row reads as "which side" at a glance, matching the hero/keypad colours. */
private fun teamColor(state: MatchUiState, team: Int): Color =
    if (team == 2) state.team2Color else state.team1Color

// ───────────────────────────── Hero (rank 1) ─────────────────────────────

@Composable
private fun MvpHero(state: MatchUiState, p: MvpPlayer, live: Boolean) {
    val accent = teamColor(state, p.team)
    val logoUrl = if (p.team == 2) state.team2Logo else state.team1Logo
    val teamCode = if (p.team == 2) state.team2 else state.team1

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(
                Brush.linearGradient(
                    listOf(accent.copy(alpha = 0.16f), CrexColors.Surface)
                )
            )
            .border(1.dp, accent.copy(alpha = 0.35f), RoundedCornerShape(18.dp))
            .padding(16.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                // While the match is live the leader can still change hands, so the label
                // says so instead of crowning anyone early.
                if (live) "LEADING THE MATCH" else "MOST VALUABLE PLAYER",
                color = accent,
                fontSize = 10.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.2.sp,
                modifier = Modifier.weight(1f)
            )
            Text(
                p.roleLabel,
                color = CrexColors.TextMuted,
                fontSize = 9.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 0.8.sp
            )
        }

        Spacer(Modifier.height(14.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            HeroCrest(monogram = initials(p.name), modifier = Modifier.size(52.dp))
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    p.name,
                    color = CrexColors.TextPrimary,
                    fontSize = 19.sp,
                    fontWeight = FontWeight.Black,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(Modifier.height(3.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    TeamLogo(team = teamCode, logoUrl = logoUrl, modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(5.dp))
                    Text(
                        p.teamName.ifBlank { teamCode },
                        color = CrexColors.TextSecondary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "${p.points}",
                    color = accent,
                    fontSize = 30.sp,
                    fontWeight = FontWeight.Black,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
                Text(
                    "IMPACT",
                    color = CrexColors.TextMuted,
                    fontSize = 8.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.sp
                )
            }
        }

        Spacer(Modifier.height(14.dp))

        // What the impact was actually made of — only the lanes they took part in.
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            if (p.didBat) {
                HeroLane(
                    modifier = Modifier.weight(1f),
                    label = "BATTING",
                    value = p.batLine,
                    detail = buildString {
                        append("SR ${p.strikeRate}")
                        if (p.fours > 0) append(" · ${p.fours}x4")
                        if (p.sixes > 0) append(" · ${p.sixes}x6")
                    },
                    accent = CrexColors.AccentGreen
                )
            }
            if (p.didBowl) {
                HeroLane(
                    modifier = Modifier.weight(1f),
                    label = "BOWLING",
                    value = p.bowlLine,
                    detail = buildString {
                        append("ER ${p.econ}")
                        if (p.maidens > 0) append(" · ${p.maidens} mdn")
                    },
                    accent = CrexColors.AccentBlue
                )
            }
        }
    }
}

@Composable
private fun HeroLane(
    modifier: Modifier = Modifier,
    label: String,
    value: String,
    detail: String,
    accent: Color
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(CrexColors.Surface.copy(alpha = 0.85f))
            .border(1.dp, CrexColors.Border, RoundedCornerShape(12.dp))
            .padding(horizontal = 12.dp, vertical = 10.dp)
    ) {
        Text(label, color = CrexColors.TextMuted, fontSize = 8.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
        Spacer(Modifier.height(4.dp))
        Text(
            value, color = accent, fontSize = 17.sp, fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum")
        )
        Spacer(Modifier.height(2.dp))
        Text(detail, color = CrexColors.TextSecondary, fontSize = 10.sp, fontWeight = FontWeight.Medium)
    }
}

// ───────────────────────────── Ranked rows ─────────────────────────────

@Composable
private fun MvpRow(state: MatchUiState, player: MvpPlayer, rank: Int, fraction: Float) {
    val accent = teamColor(state, player.team)
    // The bar grows into place on first composition and re-animates as points move,
    // so a refresh mid-match reads as movement rather than a jump.
    val width by animateFloatAsState(targetValue = fraction, label = "impactBar")

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(14.dp))
            .padding(horizontal = 12.dp, vertical = 11.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            // Rank chip
            Box(
                modifier = Modifier
                    .size(24.dp)
                    .clip(CircleShape)
                    .background(accent.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    "$rank",
                    color = accent,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
            }
            Spacer(Modifier.width(10.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    player.name,
                    color = CrexColors.TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    // Only the lanes they actually featured in, joined with a dot.
                    listOfNotNull(
                        player.batLine.takeIf { it.isNotBlank() },
                        player.bowlLine.takeIf { it.isNotBlank() }
                    ).joinToString("  ·  "),
                    color = CrexColors.TextSecondary,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
            Spacer(Modifier.width(8.dp))
            Text(
                "${player.points}",
                color = CrexColors.TextPrimary,
                fontSize = 16.sp,
                fontWeight = FontWeight.Black,
                style = TextStyle(fontFeatureSettings = "tnum")
            )
        }

        Spacer(Modifier.height(9.dp))

        // Impact bar, scaled against the leader.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(4.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(CrexColors.Border.copy(alpha = 0.6f))
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(width)
                    .fillMaxHeight()
                    .clip(RoundedCornerShape(2.dp))
                    .background(accent)
            )
        }
    }
}

// ───────────────────────────── Footer / empty ─────────────────────────────

@Composable
private fun MvpFormula() {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(12.dp))
            .padding(14.dp)
    ) {
        Text("HOW IMPACT IS SCORED", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
        Spacer(Modifier.height(8.dp))
        listOf(
            "Batting" to "1 per run, +1 per four, +2 per six, plus a strike-rate bonus after 10 balls faced.",
            "Bowling" to "20 per wicket, 8 per maiden, plus an economy bonus after a full over.",
            "Fielding" to "Not counted — the scorer doesn't record which fielder took the catch."
        ).forEachIndexed { i, (head, body) ->
            if (i > 0) Spacer(Modifier.height(6.dp))
            Row {
                Text(
                    head, color = CrexColors.TextPrimary, fontSize = 11.sp,
                    fontWeight = FontWeight.Bold, modifier = Modifier.width(58.dp)
                )
                Text(body, color = CrexColors.TextSecondary, fontSize = 11.sp, modifier = Modifier.weight(1f))
            }
        }
        Spacer(Modifier.height(8.dp))
        Text(
            "Figures come from the same ball-by-ball log as the scorecard.",
            color = CrexColors.TextMuted, fontSize = 10.sp
        )
    }
}

@Composable
private fun MvpEmpty(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(24.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(CrexColors.Surface)
                .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text("No impact yet", color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(6.dp))
            Text(
                "Once the first ball is scored, every batter and bowler is ranked here by match impact.",
                color = CrexColors.TextSecondary, fontSize = 13.sp, textAlign = TextAlign.Center
            )
        }
    }
}

/** "Siva Kumar" -> "SK"; a single name falls back to its first two letters. */
private fun initials(name: String): String {
    val parts = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
    return when {
        parts.isEmpty() -> "?"
        parts.size == 1 -> parts[0].take(2).uppercase()
        else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
    }
}
