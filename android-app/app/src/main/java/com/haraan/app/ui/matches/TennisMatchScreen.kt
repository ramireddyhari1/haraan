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
 * Tennis' match detail — the broadcast scoreboard grid, because tennis already has a
 * universally-read score display and inventing a different one would only confuse.
 *
 * Two rows, one per player. Columns are the completed sets, then games in the set being
 * played, then the live point — which is the only column that says 15/30/40/AD, and the one
 * a tennis viewer's eye goes to first. The serve dot sits against the name, where it does
 * on television.
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
) {
    val theme = sportThemeFor("tennis")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    // What the seam ribbon shouts, and the key that makes it surge on a new point.
    val ribbon = crexRibbonFor(board, theme)
    val setsHome = board.sets.count { it.first > it.second }
    val setsAway = board.sets.count { it.second > it.first }
    val games = board.games ?: (0 to 0)
    val points = board.points ?: ("0" to "0")

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
                    TennisHero(state, board, theme)
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
                    if (board.sets.isNotEmpty()) {
                        crexItem(tab) {
                            BoardPanel {
                                PanelTitle("Completed sets")
                                Spacer(Modifier.height(12.dp))
                                board.sets.forEachIndexed { i, (h, a) ->
                                    if (i > 0) Spacer(Modifier.height(10.dp))
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text("Set ${i + 1}", fontSize = 12.5.sp, color = BoardInk.faint, modifier = Modifier.width(54.dp))
                                        Text(
                                            "$h–$a", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold,
                                            color = BoardInk.ink, modifier = Modifier.weight(1f),
                                        )
                                        Text(
                                            if (h > a) state.team1 else state.team2,
                                            fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.muted,
                                            maxLines = 1, overflow = TextOverflow.Ellipsis,
                                        )
                                    }
                                }
                            }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Points won", "PTS", state, board) }
                    }
                    if (board.sets.isEmpty() && board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The scoreboard fills in point by point.") }
                    }
                    if (board.feed.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }

                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "The scoreboard fills in point by point.") }
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
                                        "Point to ${if (m.side == "home") state.team1 else state.team2}",
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

/**
 * Tennis' hero: the scoreboard grid, exactly as it hangs above a court.
 *
 * Tennis is read in rows, not sides — each player is a line, and the columns run set by set to
 * the games and then the point. Flattening that into two big numbers would throw away the only
 * layout a tennis crowd already knows how to read, so this hero is the one that looks least
 * like the others on purpose.
 */
@Composable
private fun TennisHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val games = board.games ?: (0 to 0)
    val points = board.points ?: ("0" to "0")

    Row(verticalAlignment = Alignment.CenterVertically) {
        Spacer(Modifier.weight(1f))
        board.sets.indices.forEach { i ->
            HeroLabel("S${i + 1}", Modifier.width(24.dp), align = TextAlign.Center)
        }
        HeroLabel("GM", Modifier.width(30.dp), align = TextAlign.Center)
        HeroLabel("PT", Modifier.width(42.dp), align = TextAlign.Center)
    }
    Spacer(Modifier.height(8.dp))
    TennisRow(
        name = state.team1, fullName = state.team1FullName, logo = state.team1Logo, serving = board.serving == "home",
        sets = board.sets.map { it.first }, wins = board.sets.map { it.first > it.second },
        games = games.first, point = points.first, pointColor = theme.deep,
    )
    Spacer(Modifier.height(7.dp))
    HeroRule()
    Spacer(Modifier.height(7.dp))
    TennisRow(
        name = state.team2, fullName = state.team2FullName, logo = state.team2Logo, serving = board.serving == "away",
        sets = board.sets.map { it.second }, wins = board.sets.map { it.second > it.first },
        games = games.second, point = points.second, pointColor = theme.soft,
    )
}

@Composable
private fun TennisRow(
    name: String,
    fullName: String,
    logo: String,
    serving: Boolean,
    sets: List<Int>,
    wins: List<Boolean>,
    games: Int,
    point: String,
    pointColor: Color,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        HeroSideTag(name, logo, active = serving, modifier = Modifier.weight(1f), crest = 28, label = fullName)
        sets.forEachIndexed { i, v ->
            Text(
                "$v",
                color = if (wins.getOrElse(i) { false }) Color(0xFF0F172A) else Color(0xFF64748B),
                fontSize = 13.sp,
                fontWeight = if (wins.getOrElse(i) { false }) FontWeight.Bold else FontWeight.Normal,
                textAlign = TextAlign.Center,
                modifier = Modifier.width(24.dp),
            )
        }
        Text(
            "$games", color = Color(0xFF0F172A), fontSize = 15.sp, fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center, modifier = Modifier.width(30.dp),
        )
        Box(Modifier.width(42.dp), contentAlignment = Alignment.Center) {
            HeroNumeralText(point, pointColor, 26)
        }
    }
}
