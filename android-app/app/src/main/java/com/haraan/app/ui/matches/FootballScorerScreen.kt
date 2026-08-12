package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
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
import com.haraan.app.data.MatchScoreState
import com.haraan.app.data.SquadMember
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import com.haraan.app.ui.theme.HaraanColors

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Blue = HaraanColors.EventsBlue
private val Amber = HaraanColors.Warning
private val Red = HaraanColors.Danger
private val Line = HaraanColors.BorderLight

/**
 * The football match scorer.
 *
 * Rebuilt from a two-box +/− stepper, which read as a calculator rather than a
 * match: no clock, no half, no minute on a goal, no record of what you just tapped
 * and no way to see a mistake. A scorer that can't show you what it recorded is one
 * you stop trusting by the second half.
 *
 * What's on screen now is the match: a live clock you start and stop, the half,
 * a goal button per side that asks **who scored**, and a running feed of everything
 * recorded — each row undoable.
 *
 * The screen still does not own the score. Every action posts an EVENT and the
 * server hands back the settled scoreline.
 */
@Composable
fun FootballScorerScreen(
    setup: FootballScorerSetup,
    onGoal: suspend (side: String, player: String?, minuteLabel: String, minute: Int) -> MatchScoreState?,
    onCard: suspend (side: String, player: String?, kind: String, minute: Int) -> MatchScoreState?,
    onUndoGoal: suspend (side: String) -> MatchScoreState?,
    /** Adjust a match-stat tally (shots, corners, fouls…) by one; never touches the score. */
    onStat: suspend (kind: String, side: String, inc: Boolean) -> Unit = { _, _, _ -> },
    finishMatch: suspend () -> Unit,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var home by remember { mutableStateOf(setup.initialHome) }
    var away by remember { mutableStateOf(setup.initialAway) }
    var clock by remember { mutableStateOf(MatchClock(halfLengthMin = setup.halfLengthMin)) }
    var finishing by remember { mutableStateOf(false) }
    var confirmFullTime by remember { mutableStateOf(false) }
    var pickerFor by remember { mutableStateOf<String?>(null) }
    var pickerKind by remember { mutableStateOf("goal") }
    // Score vs Stats input mode — keeps each panel uncluttered on a dense scorer.
    var statsMode by remember { mutableStateOf(false) }
    // Local optimistic stat tallies, keyed "$kind-$side". The server counts the real
    // totals from events (shown on the detail screen); these drive instant feedback.
    val statCounts = remember { androidx.compose.runtime.mutableStateMapOf<String, Int>() }
    val feed = remember { mutableStateListOf<ScorerFeedItem>() }
    val scope = rememberCoroutineScope()

    fun stat(kind: String, side: String, inc: Boolean) {
        val key = "$kind-$side"
        val cur = statCounts[key] ?: 0
        if (!inc && cur <= 0) return
        statCounts[key] = (cur + if (inc) 1 else -1).coerceAtLeast(0)
        scope.launch { runCatching { onStat(kind, side, inc) } }
    }

    // One tick a second while the half is running. Stops dead when paused, so half
    // time doesn't quietly keep counting.
    LaunchedEffect(clock.running) {
        while (clock.running) {
            delay(1000)
            clock = clock.tick()
        }
    }

    fun settle(state: MatchScoreState?) {
        if (state != null) { home = state.home; away = state.away }
    }

    fun record(side: String, kind: String, player: String?) {
        val label = clock.label
        val minute = clock.minute
        val team = if (side == "home") setup.teamA else setup.teamB
        feed.add(0, ScorerFeedItem(kind, side, team, player, label))
        if (kind == "goal") { if (side == "home") home++ else away++ }

        scope.launch {
            runCatching {
                settle(
                    if (kind == "goal") onGoal(side, player, label, minute)
                    else onCard(side, player, kind, minute)
                )
            }
        }
    }

    fun undo(item: ScorerFeedItem, index: Int) {
        feed.removeAt(index)
        if (item.kind == "goal") {
            if (item.side == "home") { if (home > 0) home-- } else { if (away > 0) away-- }
            scope.launch { runCatching { settle(onUndoGoal(item.side)) } }
        }
    }

    Column(modifier = modifier.fillMaxSize().background(Bg)) {

        Scoreboard(setup, home, away, clock, onBack = onDone, enabled = !finishing)

        ClockBar(
            clock = clock,
            onToggle = { clock = clock.copy(running = !clock.running) },
            onHalfTime = { clock = clock.copy(running = false) },
            onSecondHalf = { clock = clock.startSecondHalf() },
        )

        // Score / Stats mode toggle — one dense scorer, two focused panels.
        ModeToggle(statsMode) { statsMode = it }

        if (!statsMode) {
            Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                GoalButton(setup.teamA, Modifier.weight(1f)) { pickerKind = "goal"; pickerFor = "home" }
                GoalButton(setup.teamB, Modifier.weight(1f)) { pickerKind = "goal"; pickerFor = "away" }
            }

            Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                CardButton("Card · ${setup.teamA}", Modifier.weight(1f)) { pickerKind = "yellow"; pickerFor = "home" }
                CardButton("Card · ${setup.teamB}", Modifier.weight(1f)) { pickerKind = "yellow"; pickerFor = "away" }
            }

            Spacer(Modifier.height(14.dp))

            Text(
                "Match feed",
                fontSize = 11.5.sp,
                fontWeight = FontWeight.Bold,
                color = Faint,
                modifier = Modifier.padding(horizontal = 18.dp),
            )
            Spacer(Modifier.height(6.dp))

            Box(Modifier.weight(1f)) {
                if (feed.isEmpty()) {
                    Column(
                        Modifier.fillMaxSize().padding(horizontal = 36.dp, vertical = 28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(
                            "Nothing recorded yet",
                            fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                        )
                        Spacer(Modifier.height(4.dp))
                        Text(
                            "Start the clock, then record a goal when one goes in. Everything you tap shows here so you can check it — or undo it.",
                            fontSize = 12.5.sp, color = Muted, textAlign = TextAlign.Center,
                        )
                    }
                } else {
                    LazyColumn(contentPadding = PaddingValues(horizontal = 12.dp, vertical = 2.dp)) {
                        itemsIndexed(feed) { index, item ->
                            FeedRow(item) { undo(item, index) }
                        }
                    }
                }
            }
        } else {
            // Stats panel — a compact stepper per stat, per side. Cards stay in Score
            // mode (they carry a scorer + feed row); everything here is a plain tally.
            StatsPanel(
                teamA = setup.teamA, teamB = setup.teamB,
                counts = statCounts,
                onAdjust = { kind, side, inc -> stat(kind, side, inc) },
                modifier = Modifier.weight(1f),
            )
        }

        FullTimeBar(
            home = home, away = away, teamA = setup.teamA, teamB = setup.teamB,
            enabled = !finishing,
        ) { confirmFullTime = true }
    }

    // Who scored? A goal without a name is a tally; with one it's a match record.
    pickerFor?.let { side ->
        PlayerPicker(
            title = if (pickerKind == "goal") "Who scored?" else "Who was booked?",
            team = if (side == "home") setup.teamA else setup.teamB,
            squad = if (side == "home") setup.squadA else setup.squadB,
            onPick = { name -> record(side, pickerKind, name); pickerFor = null },
            onDismiss = { pickerFor = null },
        )
    }

    if (confirmFullTime) {
        AlertDialog(
            onDismissRequest = { confirmFullTime = false },
            title = { Text("End the match?") },
            text = {
                Text(
                    "Final score ${setup.teamA} $home – $away ${setup.teamB}. " +
                        "This locks the match and freezes the stats — it can't be scored again.",
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    confirmFullTime = false
                    finishing = true
                    clock = clock.copy(running = false)
                    scope.launch { runCatching { finishMatch() }; finishing = false; onDone() }
                }) { Text("End match", color = Red, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { confirmFullTime = false }) { Text("Keep scoring", color = Muted) }
            },
        )
    }
}

/* ----------------------------------------------------------- scoreboard */

@Composable
private fun Scoreboard(
    setup: FootballScorerSetup,
    home: Int,
    away: Int,
    clock: MatchClock,
    onBack: () -> Unit,
    enabled: Boolean,
) {
    Column(
        Modifier.fillMaxWidth().background(Surface).statusBarsPadding()
            .padding(horizontal = 16.dp).padding(top = 6.dp, bottom = 16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(34.dp).clip(CircleShape).background(Color(0xFFF1F5F9))
                    .clickable(enabled = enabled, onClick = onBack),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Ink, modifier = Modifier.size(17.dp))
            }
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text("Match scorer", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
                Text(
                    setup.formatLabel.ifBlank { "Football" },
                    fontSize = 11.5.sp, color = Faint,
                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
            }
            LiveDot(clock.running)
        }

        Spacer(Modifier.height(16.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            TeamName(setup.teamA, Modifier.weight(1f), TextAlign.Start)
            Text(
                "$home",
                fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
                modifier = Modifier.padding(horizontal = 10.dp),
            )
            Text("–", fontSize = 22.sp, color = Faint)
            Text(
                "$away",
                fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
                modifier = Modifier.padding(horizontal = 10.dp),
            )
            TeamName(setup.teamB, Modifier.weight(1f), TextAlign.End)
        }
    }
}

@Composable
private fun TeamName(name: String, modifier: Modifier, align: TextAlign) {
    Text(
        name, modifier = modifier, fontSize = 15.sp, fontWeight = FontWeight.Bold,
        color = Ink, textAlign = align, maxLines = 2, overflow = TextOverflow.Ellipsis,
    )
}

@Composable
private fun LiveDot(running: Boolean) {
    Row(
        Modifier.clip(RoundedCornerShape(9.dp))
            .background(if (running) Color(0xFFFEE2E2) else Color(0xFFF1F5F9))
            .padding(horizontal = 8.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(5.dp).clip(CircleShape).background(if (running) Red else Faint))
        Spacer(Modifier.width(5.dp))
        Text(
            if (running) "LIVE" else "PAUSED",
            fontSize = 10.sp, fontWeight = FontWeight.Bold,
            color = if (running) Red else Muted,
        )
    }
}

/* -------------------------------------------------------------- clock */

@Composable
private fun ClockBar(
    clock: MatchClock,
    onToggle: () -> Unit,
    onHalfTime: () -> Unit,
    onSecondHalf: () -> Unit,
) {
    Row(
        Modifier.fillMaxWidth().background(Surface)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(clock.label, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
            Text(clock.halfLabel, fontSize = 11.5.sp, color = Faint)
        }

        ClockAction(if (clock.running) "Pause" else "Start", primary = !clock.running, onClick = onToggle)
        Spacer(Modifier.width(8.dp))
        if (clock.half == 1) {
            ClockAction("Half time", primary = false, onClick = onHalfTime)
            Spacer(Modifier.width(8.dp))
            ClockAction("2nd half", primary = false, onClick = onSecondHalf)
        }
    }
}

@Composable
private fun ClockAction(label: String, primary: Boolean, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (primary) Blue else Color(0xFFF1F5F9))
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 8.dp),
    ) {
        Text(
            label, fontSize = 12.5.sp, fontWeight = FontWeight.Bold,
            color = if (primary) Color.White else Ink,
        )
    }
}

/* ------------------------------------------------------------ actions */

@Composable
private fun GoalButton(team: String, modifier: Modifier, onClick: () -> Unit) {
    Row(
        modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Blue)
            .clickable(onClick = onClick)
            .padding(vertical = 14.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text("Goal", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White)
        Spacer(Modifier.width(6.dp))
        Text(
            team, fontSize = 12.5.sp, color = Color.White.copy(alpha = 0.82f),
            maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun CardButton(label: String, modifier: Modifier, onClick: () -> Unit) {
    Box(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Surface)
            .border(1.dp, Line, RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Muted,
            maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
    }
}

/* --------------------------------------------------------------- stats */

/** Score / Stats segmented toggle sitting under the clock. */
@Composable
private fun ModeToggle(statsMode: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 10.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF1F5F9))
            .padding(3.dp),
        horizontalArrangement = Arrangement.spacedBy(3.dp),
    ) {
        ModeChip("Score", selected = !statsMode, Modifier.weight(1f)) { onChange(false) }
        ModeChip("Stats", selected = statsMode, Modifier.weight(1f)) { onChange(true) }
    }
}

@Composable
private fun ModeChip(label: String, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    Box(
        modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) Surface else Color.Transparent)
            .clickable(onClick = onClick)
            .padding(vertical = 9.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = if (selected) Blue else Muted,
        )
    }
}

/** The tap-to-count stats grid: a stepper per stat, per side. */
@Composable
private fun StatsPanel(
    teamA: String,
    teamB: String,
    counts: Map<String, Int>,
    onAdjust: (kind: String, side: String, inc: Boolean) -> Unit,
    modifier: Modifier = Modifier,
) {
    // Order + labels mirror the detail screen's ATTACKING / DISCIPLINE / DEFENCE groups.
    val defs = listOf(
        "shot" to "Shots", "shot_on" to "On target", "shot_off" to "Off target",
        "shot_blocked" to "Blocked", "corner" to "Corners",
        "foul" to "Fouls", "offside" to "Offsides",
        "save" to "Saves", "free_kick" to "Free kicks",
    )
    Column(modifier.fillMaxWidth()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(teamA, Modifier.weight(1f), fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text("TAP TO COUNT", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = Faint)
            Text(teamB, Modifier.weight(1f), fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Ink, textAlign = TextAlign.End, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        LazyColumn(
            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            itemsIndexed(defs) { _, (kind, label) ->
                Row(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Surface)
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    StatStepper(counts["$kind-home"] ?: 0, { onAdjust(kind, "home", false) }, { onAdjust(kind, "home", true) })
                    Text(
                        label, Modifier.weight(1f), fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                        color = Ink, textAlign = TextAlign.Center, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                    StatStepper(counts["$kind-away"] ?: 0, { onAdjust(kind, "away", false) }, { onAdjust(kind, "away", true) })
                }
            }
        }
    }
}

/** A compact `[−] N [+]` stepper — plus adds a stat, minus corrects a mis-tap. */
@Composable
private fun StatStepper(count: Int, onDec: () -> Unit, onInc: () -> Unit) {
    Row(
        Modifier.clip(RoundedCornerShape(10.dp)).border(1.dp, Line, RoundedCornerShape(10.dp)),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(34.dp).clickable(onClick = onDec),
            contentAlignment = Alignment.Center,
        ) { Text("−", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = if (count > 0) Muted else Faint) }
        Text(
            "$count", Modifier.width(26.dp), fontSize = 15.sp, fontWeight = FontWeight.Bold,
            color = Ink, textAlign = TextAlign.Center,
        )
        Box(
            Modifier.size(34.dp).clip(RoundedCornerShape(9.dp)).background(Blue).clickable(onClick = onInc),
            contentAlignment = Alignment.Center,
        ) { Text("+", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = Color.White) }
    }
}

/* --------------------------------------------------------------- feed */

@Composable
private fun FeedRow(item: ScorerFeedItem, onUndo: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 4.dp)
            .clip(RoundedCornerShape(12.dp)).background(Surface)
            .padding(horizontal = 14.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            item.minuteLabel,
            fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Faint,
            modifier = Modifier.width(48.dp),
        )
        Column(Modifier.weight(1f)) {
            Text(
                when (item.kind) {
                    "goal" -> "Goal · ${item.teamName}"
                    "yellow" -> "Yellow card · ${item.teamName}"
                    "red" -> "Red card · ${item.teamName}"
                    else -> "${item.kind.replaceFirstChar { it.uppercase() }} · ${item.teamName}"
                },
                fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold,
                color = if (item.kind == "goal") Ink else Amber,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            item.player?.let {
                Text(it, fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        Text(
            "Undo",
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Red,
            modifier = Modifier.clickable(onClick = onUndo).padding(start = 10.dp, top = 4.dp, bottom = 4.dp),
        )
    }
}

/* ------------------------------------------------------------ pickers */

@Composable
private fun PlayerPicker(
    title: String,
    team: String,
    squad: List<SquadMember>,
    onPick: (String?) -> Unit,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            Column {
                Text(team, fontSize = 12.sp, color = Faint)
                Spacer(Modifier.height(8.dp))
                if (squad.isEmpty()) {
                    Text(
                        "No squad was recorded for this team, so this goes down without a name.",
                        fontSize = 13.sp, color = Muted,
                    )
                } else {
                    squad.forEach { member ->
                        Text(
                            member.name,
                            fontSize = 14.5.sp,
                            color = Ink,
                            modifier = Modifier.fillMaxWidth()
                                .clickable { onPick(member.name) }
                                .padding(vertical = 10.dp),
                        )
                    }
                }
            }
        },
        // Always offer "don't know": in gully football the scorer often doesn't, and
        // forcing a name would either stall the tap or invent one.
        confirmButton = { TextButton(onClick = { onPick(null) }) { Text("Don't know", color = Blue) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel", color = Muted) } },
    )
}

/* ----------------------------------------------------------- full time */

@Composable
private fun FullTimeBar(
    home: Int,
    away: Int,
    teamA: String,
    teamB: String,
    enabled: Boolean,
    onClick: () -> Unit,
) {
    val result = when {
        home > away -> "$teamA lead $home–$away"
        away > home -> "$teamB lead $away–$home"
        else -> "Level at $home–$away"
    }

    Column(
        Modifier.fillMaxWidth().background(Surface).navigationBarsPadding()
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Text(result, fontSize = 12.sp, color = Faint)
        Spacer(Modifier.height(8.dp))
        Box(
            Modifier.fillMaxWidth().height(48.dp)
                .clip(RoundedCornerShape(13.dp))
                // Ending the match locks it and freezes the stats. That is a
                // consequence, not a celebration — so it reads as a serious dark
                // action, never a cheerful green "done".
                .background(Ink)
                .clickable(enabled = enabled, onClick = onClick),
            contentAlignment = Alignment.Center,
        ) {
            Text("End match", fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Color.White)
        }
    }
}
