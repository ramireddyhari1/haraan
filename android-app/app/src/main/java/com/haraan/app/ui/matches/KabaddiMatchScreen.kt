@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Kabaddi's match detail — the mat, not just the score.
 *
 * A kabaddi total is half the story; the other half is how many players each side still has
 * in. That decides what a viewer watches for next: a defence down to three can pull off a
 * SUPER TACKLE, a mat about to empty is an ALL OUT worth two, and a side that has raided empty
 * twice is on a DO-OR-DIE. So the hero draws both mats as players, points at whoever raids
 * next, and the raid ledger names every outcome. Matches scored before mat scoring show
 * their tally honestly and simply draw no mat.
 */
@Composable
fun KabaddiMatchScreen(
    state: MatchUiState,
    board: SportBoard,
    watching: Int = 0,
    onBack: () -> Unit = {},
    onScore: () -> Unit = {},
    /** Null unless this viewer may see who is watching. */
    onWatchers: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
    /** Server id — the Insights tab reads this match's event log by it. */
    matchId: String = "",
) {
    val theme = sportThemeFor("kabaddi")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    val ribbon = crexRibbonFor(board, theme)
    val moment = rememberFreshMoment(heroMomentFor(board, state, theme), board.feed.firstOrNull()?.sequence ?: 0)

    Box(modifier.fillMaxSize().background(CrexColors.Background)) {
        LazyColumn(
            state = listState,
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 28.dp),
        ) {
            item {
                Column(Modifier.fillMaxWidth().background(CrexColors.Background).statusBarsPadding()) {
                    CrexBoardTopBar(state, theme, watching, onBack, onScore, onWatchers)
                    CrexBoardHero(
                        theme = theme,
                        meta = state.competition.ifBlank {
                            if (state.isLive) "Live" else state.status.ifBlank { theme.label }
                        },
                        ribbonWord = ribbon.first,
                        ribbonColor = ribbon.second,
                        ribbonKey = ribbon.third,
                        moment = moment,
                    ) {
                        KabaddiHero(state, board, theme)
                    }
                }
            }

            stickyHeader {
                CrexBoardTabs(
                    tabs = listOf("Summary", "Raid by raid", "Players", "Line-ups", "Insights"),
                    selectedTabIndex = tab,
                    accent = theme.deep,
                    onTabSelected = { tab = it },
                    liveTab = 1,
                    liveActive = state.isLive,
                )
            }
            item { Spacer(Modifier.height(6.dp)) }

            when (tab) {
                0 -> {
                    if (board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The ledger fills in raid by raid.") }
                    } else {
                        crexItem(tab) { RaidLedger(state, board) }
                    }
                    if (board.pointMoments.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }
                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "Every raid, tackle and all out lands here as it happens.") }
                } else {
                    crexItem(tab) { LiveFeedPanel("Raid by raid", state, board, theme) }
                }
                2 -> if (board.kabaddiPlayers.isEmpty() && board.scorers.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "Name the raider or tackler when scoring and their points appear here.") }
                } else {
                    crexItem(tab) { KabaddiPlayers(state, board) }
                }
                3 -> crexItem(tab) { BoardLineups(state) }
                else -> crexItem(tab) {
                    com.haraan.app.ui.matches.insights.KabaddiInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

/**
 * Kabaddi's hero: two mats. Each side's total sits over its players on the mat, the raiding
 * side is pointed at, and the half and the lead sit in the middle.
 */
@Composable
private fun KabaddiHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val (home, away) = board.totals
    val stats = board.teamStats

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        MatColumn(
            name = state.team1, fullName = state.team1FullName, logo = state.team1Logo, points = home,
            onMat = board.mat?.home, matSize = board.mat?.size ?: 7,
            raiding = state.isLive && board.raiding == "home",
            numberColor = if (home >= away) theme.deep else theme.soft, theme = theme,
            alignEnd = false, modifier = Modifier.weight(1f),
        )
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 4.dp),
        ) {
            HeroChip(if (state.isLive) board.periodLabel.ifBlank { "1st half" } else "FULL TIME")
            Spacer(Modifier.height(10.dp))
            HeroLabel(if (home == away) "Level" else "Lead ${kotlin.math.abs(home - away)}")
        }
        MatColumn(
            name = state.team2, fullName = state.team2FullName, logo = state.team2Logo, points = away,
            onMat = board.mat?.away, matSize = board.mat?.size ?: 7,
            raiding = state.isLive && board.raiding == "away",
            numberColor = if (away >= home) theme.deep else theme.soft, theme = theme,
            alignEnd = true, modifier = Modifier.weight(1f),
        )
    }

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        SituationRow(situations)
    }

    // The split every commentator leads with: where each side's points came from.
    Spacer(Modifier.height(12.dp))
    HeroRule()
    Spacer(Modifier.height(9.dp))
    val splits = if (stats != null) {
        listOf(
            Triple("RAID", stats.home("raid_points") + stats.home("bonus_points"), stats.away("raid_points") + stats.away("bonus_points")),
            Triple("TACKLE", stats.home("tackle_points"), stats.away("tackle_points")),
            Triple("ALL OUT", stats.home("all_out_points"), stats.away("all_out_points")),
        )
    } else {
        emptyList()
    }
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        splits.forEach { (label, h, a) ->
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                HeroLabel(label)
                Spacer(Modifier.height(3.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RollingFigure("$h", Color(0xFF0F172A), 13, fontWeight = FontWeight.ExtraBold, display = false)
                    Text("  ·  ", color = Color(0xFF64748B), fontSize = 11.sp)
                    RollingFigure("$a", Color(0xFF0F172A), 13, fontWeight = FontWeight.ExtraBold, display = false)
                }
            }
        }
    }
}

@Composable
private fun MatColumn(
    name: String,
    fullName: String,
    logo: String,
    points: Int,
    onMat: Int?,
    matSize: Int,
    raiding: Boolean,
    numberColor: Color,
    theme: SportTheme,
    alignEnd: Boolean,
    modifier: Modifier,
) {
    Column(modifier, horizontalAlignment = if (alignEnd) Alignment.End else Alignment.Start) {
        HeroSideTag(name, logo, active = raiding, modifier = Modifier.fillMaxWidth(), alignEnd = alignEnd, label = fullName, activeColor = theme.spark)
        Spacer(Modifier.height(6.dp))
        HeroNumeral(points, numberColor, 44)
        Spacer(Modifier.height(6.dp))
        if (onMat != null) {
            MatDots(onMat, matSize, theme.deep, alignEnd)
            Spacer(Modifier.height(4.dp))
            Text(
                "$onMat ON MAT",
                color = if (onMat <= 3) Color(0xFFB91C1C) else Color(0xFF475569),
                fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.7.sp,
            )
        }
        if (raiding) {
            Spacer(Modifier.height(6.dp))
            RaidingTag(theme)
        }
    }
}

/** "RAIDING" with a chevron that leans toward the other half, slowly. */
@Composable
private fun RaidingTag(theme: SportTheme) {
    val lean = rememberInfiniteTransition(label = "raid")
    val dx by lean.animateFloat(0f, 3f, infiniteRepeatable(tween(700), RepeatMode.Reverse), label = "raidLean")
    Row(
        Modifier.clip(RoundedCornerShape(6.dp)).background(theme.deep).padding(horizontal = 7.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text("RAIDING", color = Color.White, fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.8.sp)
        Spacer(Modifier.width(4.dp))
        Text("›", color = theme.spark, fontSize = 12.sp, fontWeight = FontWeight.Black, modifier = Modifier.graphicsLayer { translationX = dx })
    }
}

/** How each side's points came, and how its raids went. Only what was recorded. */
@Composable
private fun RaidLedger(state: MatchUiState, board: SportBoard) {
    val s = board.teamStats ?: return
    val rows = listOf(
        Triple("Raid points", s.home("raid_points"), s.away("raid_points")),
        Triple("Bonus points", s.home("bonus_points"), s.away("bonus_points")),
        Triple("Tackle points", s.home("tackle_points"), s.away("tackle_points")),
        Triple("All-out points", s.home("all_out_points"), s.away("all_out_points")),
        Triple("Super raids", s.home("super_raids"), s.away("super_raids")),
        Triple("Super tackles", s.home("super_tackles"), s.away("super_tackles")),
        Triple("Empty raids", s.home("empty_raids"), s.away("empty_raids")),
        Triple("Technical points", s.home("technical_points"), s.away("technical_points")),
    ).filter { it.second + it.third > 0 }

    BoardPanel {
        PanelTitle("How the points came")
        Spacer(Modifier.height(4.dp))
        Text(
            if (board.matRules) "Replayed across the mat — all outs and super tackles are counted by the rules, not tapped."
            else "Counted from what the scorer tapped — an all out is worth two.",
            fontSize = 11.5.sp, color = BoardInk.faint,
        )
        Spacer(Modifier.height(14.dp))
        rows.forEachIndexed { i, (label, h, a) ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            StatDuel(label, h, a, state.team1Color, state.team2Color)
        }
    }

    // Raid success rate — only where raids were recorded as raids (mat scoring).
    val raidsH = s.home("raids")
    val raidsA = s.away("raids")
    if (board.matRules && raidsH + raidsA > 0) {
        Spacer(Modifier.height(12.dp))
        BoardPanel {
            PanelTitle("Raids", "${raidsH + raidsA}")
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth()) {
                RaidRate(state.team1, s.home("successful_raids"), raidsH, s.home("do_or_die_won"), s.home("do_or_die_raids"), state.team1Color, Modifier.weight(1f))
                Spacer(Modifier.width(12.dp))
                RaidRate(state.team2, s.away("successful_raids"), raidsA, s.away("do_or_die_won"), s.away("do_or_die_raids"), state.team2Color, Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun RaidRate(team: String, won: Int, raids: Int, dodWon: Int, dod: Int, accent: Color, modifier: Modifier) {
    val pct = if (raids == 0) 0 else (won * 100 / raids)
    Column(modifier.clip(RoundedCornerShape(12.dp)).background(accent.copy(alpha = 0.07f)).padding(12.dp)) {
        Text(team, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Spacer(Modifier.height(4.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            RollingFigure("$pct", accent, 26)
            Text("%", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = accent, modifier = Modifier.padding(bottom = 4.dp, start = 1.dp))
        }
        Text("$won of $raids raids scored", fontSize = 11.sp, color = BoardInk.muted)
        if (dod > 0) {
            Text("Do-or-die $dodWon / $dod", fontSize = 11.sp, color = BoardInk.faint)
        }
    }
}

/** Raiders and defenders, split the way kabaddi ranks them. */
@Composable
private fun KabaddiPlayers(state: MatchUiState, board: SportBoard) {
    val players = board.kabaddiPlayers
    if (players.isEmpty()) {
        ScorerPanel("Points scored", "PTS", state, board)
        return
    }
    val raiders = players.filter { it.raidPoints + it.bonusPoints > 0 }.sortedByDescending { it.raidPoints + it.bonusPoints }
    val defenders = players.filter { it.tacklePoints > 0 }.sortedByDescending { it.tacklePoints }

    listOf(
        Triple("Raiders", raiders, true),
        Triple("Defenders", defenders, false),
    ).filter { it.second.isNotEmpty() }.forEachIndexed { i, (title, list, raid) ->
        if (i > 0) Spacer(Modifier.height(12.dp))
        BoardPanel {
            PanelTitle(title, if (raid) "RAID · BONUS" else "TACKLE")
            Spacer(Modifier.height(10.dp))
            list.take(8).forEachIndexed { j, p ->
                if (j > 0) Spacer(Modifier.height(10.dp))
                val accent = if (p.side == "home") state.team1Color else state.team2Color
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(28.dp).clip(CircleShape).background(accent.copy(alpha = 0.12f)), contentAlignment = Alignment.Center) {
                        Text(p.name.take(1).uppercase(), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = accent)
                    }
                    Spacer(Modifier.width(10.dp))
                    Column(Modifier.weight(1f)) {
                        Text(p.name, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        val tagline = buildList {
                            add(if (p.side == "home") state.team1 else state.team2)
                            if (raid && p.raids > 0) add("${p.raids} raids")
                            if (raid && p.superRaids > 0) add("${p.superRaids} super raid${if (p.superRaids > 1) "s" else ""}")
                            if (!raid && p.superTackles > 0) add("${p.superTackles} super tackle${if (p.superTackles > 1) "s" else ""}")
                        }.joinToString(" · ")
                        Text(tagline, fontSize = 11.sp, color = BoardInk.faint, maxLines = 1)
                    }
                    Text(
                        if (raid) "${p.raidPoints} · ${p.bonusPoints}" else "${p.tacklePoints}",
                        fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink, textAlign = TextAlign.End,
                    )
                }
            }
        }
    }
}
