package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Blue = HaraanColors.EventsBlue
private val Amber = HaraanColors.Warning
private val Red = HaraanColors.Danger
private val Hairline = HaraanColors.BorderLight

/**
 * Football's match detail screen.
 *
 * Cricket earns five tabs because cricket genuinely has five views. Football has
 * far less state, so it gets three — giving it five would leave two of them empty,
 * which is exactly the hollow feeling this screen exists to avoid. Stats is
 * deliberately absent until the scorer records cards and subs; a tab that can only
 * ever show "goals: 2" is worse than no tab.
 *
 * The hero leads with the score and the scorers' names — the thing you actually
 * want at a glance — instead of a cricket run-rate strip with the numbers blanked.
 */
@Composable
fun FootballMatchScreen(
    state: MatchUiState,
    onBack: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val football = state.football ?: FootballState()
    var tab by remember { mutableIntStateOf(1) }   // open on the Timeline
    val tabs = listOf("Summary", "Timeline", "Line-ups")

    Column(modifier = modifier.fillMaxSize().background(Bg)) {
        FootballHero(state, football, onBack)

        Row(
            modifier = Modifier.fillMaxWidth().background(Surface),
        ) {
            tabs.forEachIndexed { index, label ->
                val active = index == tab
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .clickable { tab = index }
                        .padding(vertical = 12.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        label,
                        fontSize = 13.5.sp,
                        fontWeight = if (active) FontWeight.Bold else FontWeight.Medium,
                        color = if (active) Blue else Muted,
                    )
                    Spacer(Modifier.height(6.dp))
                    Box(
                        Modifier
                            .height(2.dp)
                            .width(if (active) 22.dp else 0.dp)
                            .background(Blue, RoundedCornerShape(2.dp)),
                    )
                }
            }
        }

        when (tab) {
            0 -> SummaryTab(state, football)
            1 -> TimelineTab(state, football)
            else -> LineupsTab(state)
        }
    }
}

/* ------------------------------------------------------------------ hero */

@Composable
private fun FootballHero(state: MatchUiState, football: FootballState, onBack: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(Surface)
            .statusBarsPadding()
            .padding(horizontal = 16.dp)
            .padding(top = 8.dp, bottom = 18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(Color(0xFFF1F5F9))
                    .clickable(onClick = onBack),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Ink, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Text(
                state.footballSubtitle(),
                fontSize = 12.5.sp,
                color = Faint,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }

        Spacer(Modifier.height(18.dp))

        Row(verticalAlignment = Alignment.Top) {
            TeamColumn(state.team1, football.homeScorers, Modifier.weight(1f), TextAlign.Start)

            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(horizontal = 10.dp)) {
                Text(
                    "${football.timeline.lastOrNull()?.homeScore ?: 0} – ${football.timeline.lastOrNull()?.awayScore ?: 0}",
                    fontSize = 34.sp,
                    fontWeight = FontWeight.ExtraBold,
                    color = Ink,
                )
                Spacer(Modifier.height(4.dp))
                ClockPill(football.clockLabel(state.isLive), state.isLive)
            }

            TeamColumn(state.team2, football.awayScorers, Modifier.weight(1f), TextAlign.End)
        }
    }
}

/** A side's name with its scorers underneath — a scoreboard, not a stat table. */
@Composable
private fun TeamColumn(
    name: String,
    scorers: List<ScorerLine>,
    modifier: Modifier,
    align: TextAlign,
) {
    Column(
        modifier = modifier,
        horizontalAlignment = if (align == TextAlign.Start) Alignment.Start else Alignment.End,
    ) {
        Text(
            name,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = Ink,
            textAlign = align,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        if (scorers.isNotEmpty()) {
            Spacer(Modifier.height(6.dp))
            scorers.forEach {
                Text(
                    it.label,
                    fontSize = 11.5.sp,
                    color = Muted,
                    textAlign = align,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun ClockPill(label: String, isLive: Boolean) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (isLive) Color(0xFFFEE2E2) else Color(0xFFF1F5F9))
            .padding(horizontal = 9.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (isLive) {
            Box(Modifier.size(5.dp).clip(CircleShape).background(Red))
            Spacer(Modifier.width(5.dp))
        }
        Text(
            label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = if (isLive) Red else Muted,
        )
    }
}

/* --------------------------------------------------------------- summary */

@Composable
private fun SummaryTab(state: MatchUiState, football: FootballState) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item {
            Card {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Stat("Goals", football.goals.size.toString())
                    Stat("Cards", football.cards.size.toString())
                    Stat("Events", football.timeline.size.toString())
                }
            }
        }
        if (state.venue.isNotBlank()) {
            item { Card { InfoRow("Venue", state.venue) } }
        }
        item { Card { InfoRow("Status", if (state.isLive) "In play" else state.status.ifBlank { "Finished" }) } }

        if (football.goals.isEmpty()) {
            item {
                Card {
                    Text(
                        "No goals yet.",
                        fontSize = 13.5.sp,
                        color = Muted,
                    )
                }
            }
        }
    }
}

/* -------------------------------------------------------------- timeline */

/**
 * The centrepiece. Minute in a middle rail, home events to the left and away to
 * the right, so which side did what is readable without parsing any text.
 */
@Composable
private fun TimelineTab(state: MatchUiState, football: FootballState) {
    if (football.timeline.isEmpty()) {
        EmptyNote(
            "Nothing has happened yet",
            "Goals, cards and substitutions appear here the moment the scorer records them.",
        )
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(vertical = 12.dp),
    ) {
        // Newest first: the thing that just happened is what you opened the screen for.
        items(football.timeline.reversed(), key = { it.sequence }) { event ->
            TimelineRow(event)
        }
    }
}

@Composable
private fun TimelineRow(event: FootballEvent) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Left column: home events only.
        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterEnd) {
            if (event.isHome) EventChip(event, alignEnd = true)
        }

        // Middle rail: the minute.
        Column(
            modifier = Modifier.width(52.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                event.minuteLabel ?: "·",
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                color = Faint,
            )
            event.scoreLabel?.takeIf { event.kind == "goal" || event.kind == "own_goal" }?.let {
                Spacer(Modifier.height(2.dp))
                Text(it, fontSize = 10.5.sp, color = Blue, fontWeight = FontWeight.Bold)
            }
        }

        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
            if (!event.isHome && event.side != null) EventChip(event, alignEnd = false)
        }
    }
}

@Composable
private fun EventChip(event: FootballEvent, alignEnd: Boolean) {
    val accent = when (event.kind) {
        "goal" -> Blue
        "own_goal" -> Red
        "yellow" -> Amber
        "red" -> Red
        else -> Muted
    }

    Column(
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Surface)
            .padding(horizontal = 12.dp, vertical = 9.dp),
        horizontalAlignment = if (alignEnd) Alignment.End else Alignment.Start,
    ) {
        Text(
            when (event.kind) {
                "goal" -> "Goal"
                "own_goal" -> "Own goal"
                "yellow" -> "Yellow card"
                "red" -> "Red card"
                "sub" -> "Substitution"
                else -> event.kind.replaceFirstChar { it.uppercase() }
            },
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = accent,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            event.player ?: event.headline,
            fontSize = 13.5.sp,
            fontWeight = FontWeight.SemiBold,
            color = Ink,
            textAlign = if (alignEnd) TextAlign.End else TextAlign.Start,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        event.related?.let {
            Text(
                if (event.kind == "sub") "for $it" else "assist $it",
                fontSize = 11.sp,
                color = Muted,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

/* --------------------------------------------------------------- lineups */

@Composable
private fun LineupsTab(state: MatchUiState) {
    if (state.homeSquad.isEmpty() && state.awaySquad.isEmpty()) {
        EmptyNote(
            "No line-ups recorded",
            "Squads added when the match was created will show here.",
        )
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item { SquadCard(state.team1, state.homeSquad) }
        item { SquadCard(state.team2, state.awaySquad) }
    }
}

@Composable
private fun SquadCard(team: String, squad: List<com.haraan.app.data.SquadMember>) {
    Card {
        Text(team, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(8.dp))
        if (squad.isEmpty()) {
            Text("No players recorded.", fontSize = 13.sp, color = Muted)
        } else {
            squad.forEachIndexed { index, player ->
                Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "${index + 1}",
                        fontSize = 11.5.sp,
                        color = Faint,
                        modifier = Modifier.width(22.dp),
                    )
                    Text(player.name, fontSize = 13.5.sp, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
    }
}

/* ----------------------------------------------------------------- bits */

@Composable
private fun Card(content: @Composable androidx.compose.foundation.layout.ColumnScope.() -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .padding(14.dp),
        content = content,
    )
}

@Composable
private fun Stat(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(2.dp))
        Text(label, fontSize = 11.5.sp, color = Muted)
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, fontSize = 13.sp, color = Muted)
        Text(value, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink)
    }
}

@Composable
private fun EmptyNote(title: String, body: String) {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = 32.dp, vertical = 56.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = Ink)
        Spacer(Modifier.height(6.dp))
        Text(body, fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center)
    }
}

private fun MatchUiState.footballSubtitle(): String =
    venue.ifBlank { "Football" }
