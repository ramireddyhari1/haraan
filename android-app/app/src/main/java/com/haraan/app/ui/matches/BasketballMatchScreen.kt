@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SportsBasketball
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
 * Basketball's match detail — built around the box score, the way the sport is actually read.
 *
 * Basketball's score moves constantly and in different sizes, so two things matter that no
 * other sport here needs: the QUARTER breakdown (a 30-12 third quarter is the story of the
 * game, and a final margin hides it) and what each bucket was WORTH. The feed says "+3", not
 * "point", because a three and a free throw are not the same event.
 */
@Composable
fun BasketballMatchScreen(
    state: MatchUiState,
    board: SportBoard,
    watching: Int = 0,
    onBack: () -> Unit = {},
    onScore: () -> Unit = {},
    /** Null unless this viewer may see who is watching. */
    onWatchers: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val theme = sportThemeFor("basketball")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    // What the seam ribbon shouts, and the key that makes it surge on a new point.
    val ribbon = crexRibbonFor(board, theme)
    val home = board.periods.sumOf { it.first }
    val away = board.periods.sumOf { it.second }

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
                    BasketballHero(state, board, theme)
                }
            }
        }

        // Pinned under the hero, so the way back to another tab never scrolls away.
        stickyHeader {
            CrexBoardTabs(
                tabs = listOf("Summary", "Play by play", "Line-ups"),
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
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Top scorers", "PTS", state, board) }
                    }
                    if (board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The box score fills in as buckets go down.") }
                    }
                    if (board.feed.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }

                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "The box score fills in as buckets go down.") }
                } else {
                    crexItem(tab) {
                        BoardPanel {
                            PanelTitle("Play by play", "${board.feed.size}")
                            Spacer(Modifier.height(10.dp))
                            board.feed.take(30).forEachIndexed { i, m ->
                                if (i > 0) Spacer(Modifier.height(10.dp))
                                val accent = if (m.side == "home") state.team1Color else state.team2Color
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Box(
                                        Modifier.size(30.dp).clip(RoundedCornerShape(9.dp))
                                            .background(accent.copy(alpha = 0.13f)),
                                        contentAlignment = Alignment.Center,
                                    ) {
                                        Text(
                                            if (m.value > 0) "+${m.value}" else "·",
                                            fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = accent,
                                        )
                                    }
                                    Spacer(Modifier.width(11.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(
                                            m.player.ifBlank { SportLook.momentLabel("basketball", m) },
                                            fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
                                            maxLines = 1, overflow = TextOverflow.Ellipsis,
                                        )
                                        Text(
                                            if (m.player.isBlank()) {
                                                if (m.side == "home") state.team1 else state.team2
                                            } else {
                                                SportLook.momentLabel("basketball", m)
                                            },
                                            fontSize = 11.sp, color = BoardInk.faint,
                                        )
                                    }
                                    Text(
                                        "${m.homeScore}–${m.awayScore}",
                                        fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.muted,
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

@Composable
private fun BoxRow(name: String, perPeriod: List<Int>, total: Int, accent: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(7.dp).clip(CircleShape).background(accent))
        Spacer(Modifier.width(8.dp))
        Text(
            name, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f),
        )
        perPeriod.forEach { p ->
            Text(
                "$p", fontSize = 13.5.sp, color = BoardInk.muted, textAlign = TextAlign.Center,
                modifier = Modifier.width(36.dp),
            )
        }
        Text(
            "$total", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink,
            textAlign = TextAlign.Center, modifier = Modifier.width(40.dp),
        )
    }
}

/**
 * Basketball's hero: the totals, and the quarter line right under them.
 *
 * A 30-12 third quarter IS the game, and a final margin hides it — so the quarter breakdown
 * belongs in the hero, not three scrolls down in a panel. Nothing else on the platform puts a
 * table inside the score card, and nothing else needs to.
 */
@Composable
private fun BasketballHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val home = board.periods.sumOf { it.first }
    val away = board.periods.sumOf { it.second }
    val periodShort = board.periodLabel.ifBlank { "Q${board.period}" }

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            HeroSideTag(state.team1, state.team1Logo, active = false, label = state.team1FullName)
            Spacer(Modifier.height(4.dp))
            HeroNumeral(home, theme.deep, 40)
        }
        // The period chip is the clock this sport gets: a self-scored game has no trustworthy
        // running clock, but everyone in the gym knows which quarter they are in.
        HeroChip(periodShort)
        Column(Modifier.weight(1f), horizontalAlignment = Alignment.End) {
            HeroSideTag(
                state.team2, state.team2Logo, active = false, modifier = Modifier.fillMaxWidth(),
                alignEnd = true, label = state.team2FullName,
            )
            Spacer(Modifier.height(4.dp))
            HeroNumeral(away, theme.soft, 40)
        }
    }
    if (board.periods.isNotEmpty()) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(9.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Spacer(Modifier.width(44.dp))
            board.periods.indices.forEach { i ->
                HeroLabel("Q${i + 1}", Modifier.weight(1f), align = TextAlign.Center)
            }
            HeroLabel("T", Modifier.width(36.dp), align = TextAlign.Center)
        }
        Spacer(Modifier.height(6.dp))
        QuarterRow(teamShortCode(state.team1), board.periods.map { it.first }, home, theme.deep)
        Spacer(Modifier.height(5.dp))
        QuarterRow(teamShortCode(state.team2), board.periods.map { it.second }, away, theme.soft)
    }
}

@Composable
private fun QuarterRow(code: String, perPeriod: List<Int>, total: Int, totalColor: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            code, color = Color(0xFF334155), fontSize = 11.5.sp, fontWeight = FontWeight.Bold,
            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.width(44.dp),
        )
        perPeriod.forEach { v ->
            Text(
                "$v", color = Color(0xFF475569), fontSize = 12.5.sp, textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f),
            )
        }
        Text(
            "$total", color = totalColor, fontSize = 13.5.sp, fontWeight = FontWeight.ExtraBold,
            textAlign = TextAlign.Center, modifier = Modifier.width(36.dp),
        )
    }
}
