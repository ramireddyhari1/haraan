@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material3.Text
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Table tennis' match detail.
 *
 * Games run to 11 and last about four minutes, so a table-tennis match is a row of small
 * finished games far more than it is a live rally — the opposite emphasis to volleyball,
 * whose sets are long. This screen therefore leads with the GAME STRIP: every game so far
 * as a compact chip, won games filled, the one in progress outlined and live.
 *
 * It also shows whose serve it is and how many serves are left in the pair — the rule that
 * decides who serves next, and the thing players argue about mid-match.
 */
@Composable
fun TableTennisMatchScreen(
    state: MatchUiState,
    board: SportBoard,
    watching: Int = 0,
    onBack: () -> Unit = {},
    onScore: () -> Unit = {},
    /** Null unless this viewer may see who is watching. */
    onWatchers: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val theme = sportThemeFor(board.sport)
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    // What the seam ribbon shouts, and the key that makes it surge on a new point.
    val ribbon = crexRibbonFor(board, theme)
    val gamesHome = board.sets.count { it.first > it.second }
    val gamesAway = board.sets.count { it.second > it.first }
    val rally = board.current ?: (0 to 0)
    val played = rally.first + rally.second

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
                ) {
                    TableTennisHero(state, board, theme)
                }
            }
        }

        // Pinned under the hero, so the way back to another tab never scrolls away.
        stickyHeader {
            CrexBoardTabs(
                tabs = listOf("Summary", "Points", "Players"),
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
                    crexItem(tab) {
                        BoardPanel {
                            PanelTitle("${board.setNoun}s", "best of ${board.bestOf} · to ${if (board.target > 0) board.target else 11}")
                            Spacer(Modifier.height(14.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                board.sets.forEach { (h, a) ->
                                    GameChip("$h–$a", homeWon = h > a, live = false, state = state)
                                }
                                if (board.current != null) {
                                    GameChip("${rally.first}–${rally.second}", homeWon = null, live = true, state = state)
                                }
                                if (board.sets.isEmpty() && board.current == null) {
                                    Text("No games yet", fontSize = 12.5.sp, color = BoardInk.faint)
                                }
                            }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Points won", "PTS", state, board) }
                    }
                    if (board.feed.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }

                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "The game strip fills in as points are played.") }
                } else {
                    crexItem(tab) {
                        BoardPanel {
                            PanelTitle("Last points", "${board.feed.size}")
                            Spacer(Modifier.height(10.dp))
                            board.feed.take(30).forEachIndexed { i, m ->
                                if (i > 0) Spacer(Modifier.height(9.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Box(
                                        Modifier.size(7.dp).clip(CircleShape)
                                            .background(if (m.side == "home") state.team1Color else state.team2Color)
                                    )
                                    Spacer(Modifier.width(10.dp))
                                    Text(
                                        m.player.ifBlank { "Point to ${if (m.side == "home") state.team1 else state.team2}" },
                                        fontSize = 13.sp, color = BoardInk.ink, fontWeight = FontWeight.Medium,
                                        maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f),
                                    )
                                    Text(
                                        "${m.homeScore}–${m.awayScore}",
                                        fontSize = 12.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint,
                                    )
                                }
                            }
                        }
                    }
                }

                else -> crexItem(tab) { BoardLineups(state) }
            }
        }
    }
}

/** One finished or in-play game. */
@Composable
private fun GameChip(label: String, homeWon: Boolean?, live: Boolean, state: MatchUiState) {
    val accent = when {
        live -> Color(0xFF4338CA)
        homeWon == true -> state.team1Color
        else -> state.team2Color
    }
    Box(
        Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (live) Color.Transparent else accent.copy(alpha = 0.13f))
            .then(
                if (live) {
                    Modifier.background(Color(0xFFEEF2FF))
                } else {
                    Modifier
                }
            )
            .padding(horizontal = 11.dp, vertical = 8.dp),
    ) {
        Text(
            label,
            fontSize = 13.sp,
            fontWeight = if (live) FontWeight.Black else FontWeight.Bold,
            color = accent,
        )
    }
}

/**
 * Table tennis' hero: the rally, the games won as pips, and a serve meter.
 *
 * The argument in every table tennis game is whose serve it is, so the hero answers it with a
 * meter rather than a sentence: two dots for the pair of serves, filled as they are used, and
 * a single dot once 10-all turns the serve over every point. Badminton has no such pair — the
 * serve simply follows the rally — so the meter is replaced by who is about to serve rather
 * than stating another sport's rule as fact.
 */
@Composable
private fun TableTennisHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val rally = board.current ?: (0 to 0)
    val gamesHome = board.sets.count { it.first > it.second }
    val gamesAway = board.sets.count { it.second > it.first }
    val isTableTennis = board.sport.equals("table_tennis", ignoreCase = true)
    val played = rally.first + rally.second
    val toWin = (board.bestOf / 2) + 1

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            HeroSideTag(
                state.team1, state.team1Logo, active = board.serving == "home",
                label = state.team1FullName,
            )
            Spacer(Modifier.height(6.dp))
            HeroNumeral(rally.first, theme.deep, 46)
            Spacer(Modifier.height(6.dp))
            GamePips(gamesHome, toWin, theme, alignEnd = false)
        }
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(horizontal = 6.dp),
        ) {
            HeroChip("${board.setNoun} ${board.sets.size + 1}")
            Spacer(Modifier.height(8.dp))
            HeroLabel("to ${if (board.target > 0) board.target else 11}")
        }
        Column(Modifier.weight(1f), horizontalAlignment = Alignment.End) {
            HeroSideTag(
                state.team2, state.team2Logo, active = board.serving == "away",
                modifier = Modifier.fillMaxWidth(), alignEnd = true, label = state.team2FullName,
            )
            Spacer(Modifier.height(6.dp))
            HeroNumeral(rally.second, theme.soft, 46)
            Spacer(Modifier.height(6.dp))
            GamePips(gamesAway, toWin, theme, alignEnd = true)
        }
    }
    Spacer(Modifier.height(12.dp))
    HeroRule()
    Spacer(Modifier.height(9.dp))
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        HeroLabel("Serve")
        Spacer(Modifier.width(10.dp))
        if (isTableTennis) {
            val deuce = rally.first >= 10 && rally.second >= 10
            val used = if (deuce) 0 else played % 2
            val pair = if (deuce) 1 else 2
            repeat(pair) { i ->
                Box(
                    Modifier.padding(end = 5.dp).size(7.dp).clip(CircleShape)
                        .background(if (i < used) theme.deep else Color.White.copy(alpha = 0.75f)),
                )
            }
            Spacer(Modifier.width(5.dp))
            Text(
                if (deuce) "changes every point" else "changes in ${pair - used} point${if (pair - used == 1) "" else "s"}",
                color = Color(0xFF475569), fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.weight(1f))
        val server = when (board.serving) {
            "home" -> teamShortCode(state.team1)
            "away" -> teamShortCode(state.team2)
            else -> ""
        }
        if (server.isNotBlank()) {
            Text(
                "$server to serve",
                color = Color(0xFF0F172A), fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
            )
        }
    }
}

/** Games won, as one pip per game needed to take the match. */
@Composable
private fun GamePips(won: Int, toWin: Int, theme: SportTheme, alignEnd: Boolean) {
    Row(
        horizontalArrangement = if (alignEnd) Arrangement.End else Arrangement.Start,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        repeat(toWin) { i ->
            Box(
                Modifier.padding(end = 4.dp).size(if (i < won) 8.dp else 6.dp).clip(CircleShape)
                    .background(if (i < won) theme.deep else Color.White.copy(alpha = 0.75f)),
            )
        }
    }
}
