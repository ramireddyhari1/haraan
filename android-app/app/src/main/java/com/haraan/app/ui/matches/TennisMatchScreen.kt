@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Tennis' match detail — the broadcast scoreboard grid, because tennis already has a
 * universally-read score display and inventing a different one would only confuse.
 *
 * Two rows, one per player: completed sets (a tie-break set carries the loser's tie-break
 * points as a superscript, the way it is printed), games in the set being played, then the
 * point column — 15/30/40/AD, or plain numbers once 6–6 goes to a tie-break. The serve ball
 * sits against the server's name and only moves when the game ends, because that is when
 * tennis moves it. Under the grid, the situation: deuce, advantage, break point, set point.
 */
@Composable
fun TennisMatchScreen(
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
    val theme = sportThemeFor("tennis")
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
                            if (state.isLive) "Live · best of ${board.bestOf} sets" else state.status.ifBlank { theme.label }
                        },
                        ribbonWord = ribbon.first,
                        ribbonColor = ribbon.second,
                        ribbonKey = ribbon.third,
                        moment = moment,
                    ) {
                        TennisHero(state, board, theme)
                    }
                }
            }

            stickyHeader {
                CrexBoardTabs(
                    tabs = listOf("Summary", "Points", "Players", "Insights"),
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
                        crexItem(tab) { BoardNotStarted(state.canScore, "The scoreboard fills in point by point.") }
                    } else {
                        board.teamStats?.let { s -> crexItem(tab) { TennisStatsPanel(state, board, s) } }
                    }
                    if (board.pointMoments.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }
                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "Every point lands here as it is won.") }
                } else {
                    crexItem(tab) { LiveFeedPanel("Points", state, board, theme) }
                }
                2 -> crexItem(tab) { BoardLineups(state) }
                else -> crexItem(tab) {
                    com.haraan.app.ui.matches.insights.TennisInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

@Composable
private fun TennisHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val games = board.games ?: (0 to 0)
    val points = board.points ?: ("0" to "0")
    val live = state.isLive && !board.decided

    Row(verticalAlignment = Alignment.CenterVertically) {
        Spacer(Modifier.weight(1f))
        board.sets.indices.forEach { i ->
            HeroLabel("S${i + 1}", Modifier.width(28.dp), align = TextAlign.Center)
        }
        if (live) {
            HeroLabel("GM", Modifier.width(30.dp), align = TextAlign.Center)
            HeroLabel(if (board.tiebreak) "TB" else "PT", Modifier.width(46.dp), align = TextAlign.Center)
        }
    }
    Spacer(Modifier.height(8.dp))
    TennisRow(
        name = state.team1, fullName = state.team1FullName, logo = state.team1Logo,
        serving = live && board.serving == "home",
        sets = board.sets.map { it.first }, wins = board.sets.map { it.first > it.second },
        tiebreaks = board.tiebreaks.mapIndexed { i, tb -> if (tb != null && board.sets[i].first < board.sets[i].second) tb else null },
        games = games.first, point = points.first, live = live, theme = theme,
        pointLit = board.advantage == "home",
    )
    Spacer(Modifier.height(7.dp))
    HeroRule()
    Spacer(Modifier.height(7.dp))
    TennisRow(
        name = state.team2, fullName = state.team2FullName, logo = state.team2Logo,
        serving = live && board.serving == "away",
        sets = board.sets.map { it.second }, wins = board.sets.map { it.second > it.first },
        tiebreaks = board.tiebreaks.mapIndexed { i, tb -> if (tb != null && board.sets[i].second < board.sets[i].first) tb else null },
        games = games.second, point = points.second, live = live, theme = theme,
        pointLit = board.advantage == "away",
    )

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty() || (live && board.serving == null && board.feed.isNotEmpty())) {
        Spacer(Modifier.height(11.dp))
        if (situations.isNotEmpty()) {
            SituationRow(situations)
        } else {
            HeroLabel("Server not recorded")
        }
    }
}

@Composable
private fun TennisRow(
    name: String,
    fullName: String,
    logo: String,
    serving: Boolean,
    sets: List<Int>,
    wins: List<Boolean>,
    tiebreaks: List<Int?>,
    games: Int,
    point: String,
    live: Boolean,
    theme: SportTheme,
    pointLit: Boolean,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.width(12.dp), contentAlignment = Alignment.CenterStart) {
            ServeMarker(serving, Color(0xFFCADB2A), size = 8)
        }
        HeroSideTag(name, logo, active = false, modifier = Modifier.weight(1f), crest = 28, label = fullName)
        sets.forEachIndexed { i, v ->
            val won = wins.getOrElse(i) { false }
            Row(Modifier.width(28.dp), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.Top) {
                Text(
                    "$v",
                    color = if (won) Color(0xFF0F172A) else Color(0xFF64748B),
                    fontSize = 14.sp,
                    fontWeight = if (won) FontWeight.ExtraBold else FontWeight.Normal,
                )
                tiebreaks.getOrNull(i)?.let { tb ->
                    Text("$tb", color = Color(0xFF64748B), fontSize = 8.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
        if (live) {
            Box(Modifier.width(30.dp), contentAlignment = Alignment.Center) {
                RollingFigure("$games", Color(0xFF0F172A), 16, fontWeight = FontWeight.Bold, display = false)
            }
            val bg by animateColorAsState(
                if (pointLit) theme.deep else Color.White.copy(alpha = 0.72f), tween(260), label = "adCell",
            )
            Box(
                Modifier.width(46.dp).clip(RoundedCornerShape(8.dp)).background(bg).padding(vertical = 2.dp),
                contentAlignment = Alignment.Center,
            ) {
                HeroNumeralRolling(point, if (pointLit) Color.White else theme.deep, 24)
            }
        }
    }
}

/** Serve, return and shot figures — derived from the serve order and what was tapped. */
@Composable
private fun TennisStatsPanel(state: MatchUiState, board: SportBoard, s: TeamStats) {
    val c1 = state.team1Color
    val c2 = state.team2Color
    fun conv(won: String, of: String, side: String): String {
        val w = if (side == "home") s.home(won) else s.away(won)
        val o = if (side == "home") s.home(of) else s.away(of)
        return "$w/$o"
    }

    if (board.sets.isNotEmpty()) {
        BoardPanel {
            PanelTitle("Completed sets")
            Spacer(Modifier.height(12.dp))
            board.sets.forEachIndexed { i, (h, a) ->
                if (i > 0) Spacer(Modifier.height(10.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Set ${i + 1}", fontSize = 12.5.sp, color = BoardInk.faint, modifier = Modifier.width(54.dp))
                    val tb = board.tiebreaks.getOrNull(i)
                    Text("$h–$a" + (tb?.let { " ($it)" } ?: ""), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink, modifier = Modifier.weight(1f))
                    Text(if (h > a) state.team1 else state.team2, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
        Spacer(Modifier.height(12.dp))
    }

    BoardPanel {
        PanelTitle("Match stats")
        Spacer(Modifier.height(12.dp))
        StatDuel("Points won", s.home("points_won"), s.away("points_won"), c1, c2)
        if (s.has("service_games")) {
            Spacer(Modifier.height(12.dp))
            StatDuel("Service games held", s.home("service_games_held"), s.away("service_games_held"), c1, c2)
            Spacer(Modifier.height(12.dp))
            StatDuel("Breaks of serve", s.home("breaks"), s.away("breaks"), c1, c2)
        }
        if (s.has("break_point_chances")) {
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(conv("break_points_won", "break_point_chances", "home"), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
                Text("Break points converted", fontSize = 12.sp, color = BoardInk.muted, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
                Text(conv("break_points_won", "break_point_chances", "away"), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
            }
            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(conv("break_points_saved", "break_points_faced", "home"), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
                Text("Break points saved", fontSize = 12.sp, color = BoardInk.muted, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
                Text(conv("break_points_saved", "break_points_faced", "away"), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
            }
        }
        listOf(
            "aces" to "Aces", "double_faults" to "Double faults",
            "winners" to "Winners", "errors" to "Unforced errors",
        ).filter { s.has(it.first) }.forEach { (k, label) ->
            Spacer(Modifier.height(12.dp))
            StatDuel(label, s.home(k), s.away(k), c1, c2)
        }
        if (!s.has("service_games")) {
            Spacer(Modifier.height(10.dp))
            Text(
                "Serve and break figures appear once the scorer records who serves first.",
                fontSize = 11.5.sp, color = BoardInk.faint,
            )
        }
    }
}
