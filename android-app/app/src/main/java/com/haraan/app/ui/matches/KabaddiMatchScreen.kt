@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SportsKabaddi
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
 * Kabaddi's match detail — a raid ledger, not a scoreboard with a different colour.
 *
 * Kabaddi's score is nearly meaningless without its shape: points come from raids, from
 * tackles, and from all-outs worth two, and a side can be ahead while losing every raid. So
 * the breakdown by KIND is the main panel here — no other sport on the board needs one —
 * and each moment in the feed names what it was, because "point" would throw away the only
 * interesting thing about it.
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
) {
    val theme = sportThemeFor("kabaddi")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    // What the seam ribbon shouts, and the key that makes it surge on a new point.
    val ribbon = crexRibbonFor(board, theme)
    val home = board.periods.sumOf { it.first }
    val away = board.periods.sumOf { it.second }
    // The breakdown the sport is read by, counted from the same feed the score came from.
    fun tally(side: String, kinds: Set<String>): Int =
        board.feed.count { it.side == side && it.kind == "point" && it.detail.lowercase() in kinds }
    val rows = listOf(
        Triple("Raid points", tally("home", setOf("raid", "super_raid", "")), tally("away", setOf("raid", "super_raid", ""))),
        Triple("Tackle points", tally("home", setOf("tackle")), tally("away", setOf("tackle"))),
        Triple("Bonus points", tally("home", setOf("bonus")), tally("away", setOf("bonus"))),
        Triple("All outs", tally("home", setOf("all_out")), tally("away", setOf("all_out"))),
    )
    val hasBreakdown = rows.any { it.second > 0 || it.third > 0 }

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
                    KabaddiHero(state, board, theme)
                }
            }
        }

        // Pinned under the hero, so the way back to another tab never scrolls away.
        stickyHeader {
            CrexBoardTabs(
                tabs = listOf("Summary", "Raid by raid", "Line-ups"),
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
                    if (hasBreakdown) {
                        crexItem(tab) {
                            BoardPanel {
                                PanelTitle("How the points came")
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    "Counted from what the scorer tapped — an all out is worth two.",
                                    fontSize = 11.5.sp, color = BoardInk.faint,
                                )
                                Spacer(Modifier.height(14.dp))
                                rows.forEachIndexed { i, (label, h, a) ->
                                    if (i > 0) Spacer(Modifier.height(13.dp))
                                    BreakdownRow(label, h, a, state.team1Color, state.team2Color)
                                }
                            }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Raiders & defenders", "PTS", state, board) }
                    }
                    if (!hasBreakdown && board.scorers.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The ledger fills in raid by raid.") }
                    }
                    if (board.feed.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }

                1 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "The ledger fills in raid by raid.") }
                } else {
                    crexItem(tab) {
                        BoardPanel {
                            PanelTitle("Raid by raid", "${board.feed.size}")
                            Spacer(Modifier.height(10.dp))
                            board.feed.take(30).forEachIndexed { i, m ->
                                if (i > 0) Spacer(Modifier.height(10.dp))
                                val accent = if (m.side == "home") state.team1Color else state.team2Color
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Box(
                                        Modifier.size(30.dp).clip(CircleShape).background(accent.copy(alpha = 0.13f)),
                                        contentAlignment = Alignment.Center,
                                    ) {
                                        Text(
                                            "+${m.value}", fontSize = 11.5.sp,
                                            fontWeight = FontWeight.ExtraBold, color = accent,
                                        )
                                    }
                                    Spacer(Modifier.width(11.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(
                                            SportLook.momentLabel("kabaddi", m),
                                            fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
                                        )
                                        Text(
                                            m.player.ifBlank { if (m.side == "home") state.team1 else state.team2 },
                                            fontSize = 11.sp, color = BoardInk.faint,
                                            maxLines = 1, overflow = TextOverflow.Ellipsis,
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

/** One breakdown row with a proportional bar — the shape of the match at a glance. */
@Composable
private fun BreakdownRow(label: String, home: Int, away: Int, homeColor: Color, awayColor: Color) {
    val total = (home + away).coerceAtLeast(1)
    Column {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("$home", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
            Text(
                label, fontSize = 12.sp, color = BoardInk.muted, textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f),
            )
            Text("$away", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
        }
        Spacer(Modifier.height(6.dp))
        Row(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp))) {
            Box(Modifier.weight(home.toFloat().coerceAtLeast(0.001f) / total).fillMaxHeight().background(homeColor))
            Box(Modifier.weight(away.toFloat().coerceAtLeast(0.001f) / total).fillMaxHeight().background(awayColor))
        }
    }
}

/**
 * Kabaddi's hero: two mats, each carrying how its points were earned.
 *
 * A kabaddi score of 34 says nothing on its own — 34 off raids is a different match from 34
 * off tackles and two all outs. So each side's total sits above its own raid and tackle count,
 * which is the split every commentator leads with, and no other sport here needs.
 */
@Composable
private fun KabaddiHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val home = board.periods.sumOf { it.first }
    val away = board.periods.sumOf { it.second }

    fun count(side: String, kinds: Set<String>): Int =
        board.feed.count { it.side == side && it.kind == "point" && it.detail.lowercase() in kinds }

    val raidKinds = setOf("raid", "super_raid", "")
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        MatColumn(
            name = state.team1, fullName = state.team1FullName, logo = state.team1Logo, points = home,
            raids = count("home", raidKinds), tackles = count("home", setOf("tackle")),
            numberColor = theme.deep, alignEnd = false, modifier = Modifier.weight(1f),
        )
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(horizontal = 8.dp),
        ) {
            HeroChip(board.periodLabel.ifBlank { "1st half" })
            Spacer(Modifier.height(10.dp))
            HeroLabel(if (home == away) "Level" else "Lead ${kotlin.math.abs(home - away)}")
        }
        MatColumn(
            name = state.team2, fullName = state.team2FullName, logo = state.team2Logo, points = away,
            raids = count("away", raidKinds), tackles = count("away", setOf("tackle")),
            numberColor = theme.soft, alignEnd = true, modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun MatColumn(
    name: String,
    fullName: String,
    logo: String,
    points: Int,
    raids: Int,
    tackles: Int,
    numberColor: Color,
    alignEnd: Boolean,
    modifier: Modifier,
) {
    Column(modifier, horizontalAlignment = if (alignEnd) Alignment.End else Alignment.Start) {
        HeroSideTag(name, logo, active = false, modifier = Modifier.fillMaxWidth(), alignEnd = alignEnd, label = fullName)
        Spacer(Modifier.height(6.dp))
        HeroNumeral(points, numberColor, 42)
        Spacer(Modifier.height(7.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            MatStat("RAID", raids)
            MatStat("TACKLE", tackles)
        }
    }
}

@Composable
private fun MatStat(label: String, value: Int) {
    Box(
        Modifier.clip(RoundedCornerShape(6.dp)).background(Color.White.copy(alpha = 0.6f))
            .padding(horizontal = 6.dp, vertical = 3.dp),
    ) {
        Text(
            "$label $value",
            color = Color(0xFF475569), fontSize = 9.sp, fontWeight = FontWeight.Bold,
            letterSpacing = 0.4.sp, maxLines = 1,
        )
    }
}
