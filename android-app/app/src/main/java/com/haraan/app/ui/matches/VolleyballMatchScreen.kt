@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SportsVolleyball
import androidx.compose.material3.Text
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.border
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
 * Volleyball's match detail.
 *
 * A volleyball crowd watches two numbers that are not the scoreline: the RALLY score in the
 * set being played, and who is serving. The set count decides the match but barely moves —
 * so here the live rally is the hero at full size, the sets sit under it as won/lost pips,
 * and the serve is called out in words rather than left to a legend.
 *
 * The deciding set runs to 15 instead of 25, so the "first to" line is read from the server
 * rather than hardcoded — the rule everyone who plays knows and most apps get wrong.
 */
@Composable
fun VolleyballMatchScreen(
    state: MatchUiState,
    board: SportBoard,
    watching: Int = 0,
    onBack: () -> Unit = {},
    onScore: () -> Unit = {},
    /** Null unless this viewer may see who is watching. */
    onWatchers: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val theme = sportThemeFor("volleyball")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    // What the seam ribbon shouts, and the key that makes it surge on a new point.
    val ribbon = crexRibbonFor(board, theme)
    val setsHome = board.sets.count { it.first > it.second }
    val setsAway = board.sets.count { it.second > it.first }
    val rally = board.current ?: (0 to 0)

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
                    VolleyballHero(state, board, theme)
                }
            }
        }

        // Pinned under the hero, so the way back to another tab never scrolls away.
        stickyHeader {
            CrexBoardTabs(
                tabs = listOf("Summary", "Points", "Line-ups"),
                selectedTabIndex = tab,
                accent = theme.deep,
                onTabSelected = { tab = it },
                liveTab = 1,
                liveActive = state.isLive,
            )
        }
        item { Spacer(Modifier.height(6.dp)) }

        when (tab) {
                // ── Summary: the set line and who is scoring ──
                0 -> {
                    if (board.sets.isNotEmpty()) {
                        crexItem(tab) {
                            BoardPanel {
                                PanelTitle("Sets", "$setsHome–$setsAway  ·  best of ${board.bestOf}")
                                Spacer(Modifier.height(12.dp))
                                board.sets.forEachIndexed { i, (h, a) ->
                                    if (i > 0) Spacer(Modifier.height(10.dp))
                                    SetScoreRow(i + 1, h, a, state)
                                }
                            }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Points scored", "PTS", state, board) }
                    }
                    if (board.sets.isEmpty() && board.scorers.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The board fills in rally by rally.") }
                    }
                    if (board.feed.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }

                // ── Points: every rally, newest first ──
                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "The board fills in rally by rally.") }
                } else {
                    crexItem(tab) {
                        BoardPanel {
                            PanelTitle("Last rallies", "${board.feed.size}")
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
                                        m.player.ifBlank {
                                            "Point to ${if (m.side == "home") state.team1 else state.team2}"
                                        },
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

                // ── Line-ups ──
                else -> crexItem(tab) { BoardLineups(state) }
            }
        }
    }
}

@Composable
private fun SetScoreRow(number: Int, home: Int, away: Int, state: MatchUiState) {
    val homeWon = home > away
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text("Set $number", fontSize = 12.5.sp, color = BoardInk.faint, modifier = Modifier.width(58.dp))
        Text(
            state.team1.ifBlank { "Home" },
            fontSize = 13.sp,
            fontWeight = if (homeWon) FontWeight.Bold else FontWeight.Normal,
            color = if (homeWon) BoardInk.ink else BoardInk.muted,
            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f),
        )
        Text(
            "$home",
            fontSize = 15.sp,
            fontWeight = if (homeWon) FontWeight.ExtraBold else FontWeight.Medium,
            color = if (homeWon) BoardInk.ink else BoardInk.muted,
        )
        Text("–", fontSize = 13.sp, color = BoardInk.faint, modifier = Modifier.padding(horizontal = 6.dp))
        Text(
            "$away",
            fontSize = 15.sp,
            fontWeight = if (!homeWon) FontWeight.ExtraBold else FontWeight.Medium,
            color = if (!homeWon) BoardInk.ink else BoardInk.muted,
        )
        Text(
            state.team2.ifBlank { "Away" },
            fontSize = 13.sp,
            fontWeight = if (!homeWon) FontWeight.Bold else FontWeight.Normal,
            color = if (!homeWon) BoardInk.ink else BoardInk.muted,
            maxLines = 1, overflow = TextOverflow.Ellipsis, textAlign = TextAlign.End,
            modifier = Modifier.weight(1f).padding(start = 8.dp),
        )
    }
}

/**
 * Volleyball's hero: the rally at the size the room shouts it, then the ladder of sets.
 *
 * Volleyball is the one sport here where the number that decides the match is NOT the number
 * everybody is watching. The set count moves three or four times an evening; the rally moves
 * every twenty seconds. So the rally is the whole middle of the card, and the sets run along
 * the bottom as a ladder — each finished set at its real score, the one being played still
 * open at the end of the row. A viewer reads the story left to right without a legend.
 */
@Composable
private fun VolleyballHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val rally = board.current ?: (0 to 0)
    val setsHome = board.sets.count { it.first > it.second }
    val setsAway = board.sets.count { it.second > it.first }

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        HeroSideTag(
            state.team1, state.team1Logo, board.serving == "home", Modifier.weight(1f),
            label = state.team1FullName,
        )
        HeroSideTag(
            state.team2, state.team2Logo, board.serving == "away", Modifier.weight(1f),
            alignEnd = true, label = state.team2FullName,
        )
    }
    Spacer(Modifier.height(12.dp))
    Row(
        Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        HeroNumeral(rally.first, theme.deep, 54)
        Text(
            "\u2013",
            color = theme.soft.copy(alpha = 0.55f),
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 16.dp),
        )
        HeroNumeral(rally.second, theme.soft, 54)
    }
    Spacer(Modifier.height(2.dp))
    HeroLabel(
        // The decider runs to 15, not 25 — read from the server, never assumed.
        if (board.target > 0) {
            "Set ${board.sets.size + 1} \u00b7 first to ${board.target}" +
                if (board.target == 15) " \u00b7 decider" else ""
        } else {
            "Best of ${board.bestOf}"
        },
        Modifier.fillMaxWidth(),
        align = TextAlign.Center,
    )
    if (board.sets.isNotEmpty() || board.current != null) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(10.dp))
        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(7.dp),
            verticalAlignment = Alignment.Bottom,
        ) {
            board.sets.forEachIndexed { i, (h, a) ->
                SetRung("${i + 1}", "$h\u2013$a", won = h > a, live = false, theme = theme)
            }
            if (board.current != null) {
                SetRung(
                    "${board.sets.size + 1}", "${rally.first}\u2013${rally.second}",
                    won = false, live = true, theme = theme,
                )
            }
            Spacer(Modifier.weight(1f))
            Column(horizontalAlignment = Alignment.End) {
                HeroLabel("Sets")
                Spacer(Modifier.height(2.dp))
                Text(
                    "$setsHome\u2013$setsAway",
                    color = Color(0xFF0F172A), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold,
                )
            }
        }
    }
}

/** One rung of the set ladder: which set, what it finished, and who took it. */
@Composable
private fun SetRung(number: String, score: String, won: Boolean, live: Boolean, theme: SportTheme) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        HeroLabel("S$number")
        Spacer(Modifier.height(3.dp))
        Box(
            Modifier
                .clip(RoundedCornerShape(7.dp))
                .background(
                    when {
                        live -> Color.White
                        won -> theme.deep
                        else -> Color.White.copy(alpha = 0.55f)
                    }
                )
                .then(
                    // The set in progress is outlined rather than filled — it hasn't been won yet,
                    // and filling it would claim it had.
                    if (live) Modifier.border(1.dp, theme.deep.copy(alpha = 0.55f), RoundedCornerShape(7.dp))
                    else Modifier
                )
                .padding(horizontal = 8.dp, vertical = 4.dp),
        ) {
            Text(
                score,
                color = if (won && !live) Color.White else Color(0xFF334155),
                fontSize = 11.sp,
                fontWeight = FontWeight.ExtraBold,
                maxLines = 1,
            )
        }
    }
}
