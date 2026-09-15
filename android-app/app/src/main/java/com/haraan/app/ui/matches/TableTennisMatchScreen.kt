@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Table tennis' match detail.
 *
 * Games run to 11 and last minutes, so a table-tennis match is a row of short games far more
 * than one long rally count. The hero leads with the rally, the games each side has banked as
 * pips, and — the thing every table-tennis game argues about — a SERVE METER: whose serve it
 * is and how many of their two are left, switching to one-each at 10–10. The server comes from
 * the recorded serve order; until the scorer names the first server, the meter is not drawn.
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
    /** Server id — the Insights tab reads this match's event log by it. */
    matchId: String = "",
) {
    val theme = sportThemeFor(board.sport)
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    val ribbon = crexRibbonFor(board, theme)
    val moment = rememberFreshMoment(heroMomentFor(board, state, theme), board.feed.firstOrNull()?.sequence ?: 0)

    Box(modifier.fillMaxSize().background(CrexColors.Background)) {
        LazyColumn(state = listState, modifier = Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
            item {
                Column(Modifier.fillMaxWidth().background(CrexColors.Background).statusBarsPadding()) {
                    CrexBoardTopBar(state, theme, watching, onBack, onScore, onWatchers)
                    CrexBoardHero(
                        theme = theme,
                        meta = state.competition.ifBlank {
                            if (state.isLive) "Live · best of ${board.bestOf}" else state.status.ifBlank { theme.label }
                        },
                        ribbonWord = ribbon.first,
                        ribbonColor = ribbon.second,
                        ribbonKey = ribbon.third,
                        moment = moment,
                    ) {
                        TableTennisHero(state, board, theme)
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
                    crexItem(tab) { GameStripPanel(state, board, theme) }
                    if (board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The game strip fills in as points are played.") }
                    } else {
                        board.teamStats?.let { s ->
                            if (s.has("serve_points_played")) crexItem(tab) { ServeReceivePanel(state, s) }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Points won", "PTS", state, board) }
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
                    com.haraan.app.ui.matches.insights.TableTennisInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

@Composable
private fun TableTennisHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val rally = board.current ?: board.sets.lastOrNull() ?: (0 to 0)
    val live = state.isLive && !board.decided

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            HeroSideTag(state.team1, state.team1Logo, active = live && board.serving == "home", label = state.team1FullName, activeColor = theme.spark)
            Spacer(Modifier.height(6.dp))
            HeroNumeral(rally.first, if (rally.first >= rally.second) theme.deep else theme.soft, 50)
            Spacer(Modifier.height(6.dp))
            GamePips(board.setsHome, board.setsToWin, theme, alignEnd = false)
        }
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(horizontal = 6.dp)) {
            HeroChip(if (live) "${board.setNoun} ${board.sets.size + 1}" else "FINAL")
            Spacer(Modifier.height(8.dp))
            HeroLabel("to ${if (board.target > 0) board.target else 11}")
        }
        Column(Modifier.weight(1f), horizontalAlignment = Alignment.End) {
            HeroSideTag(state.team2, state.team2Logo, active = live && board.serving == "away", modifier = Modifier.fillMaxWidth(), alignEnd = true, label = state.team2FullName, activeColor = theme.spark)
            Spacer(Modifier.height(6.dp))
            HeroNumeral(rally.second, if (rally.second >= rally.first) theme.deep else theme.soft, 50)
            Spacer(Modifier.height(6.dp))
            GamePips(board.setsAway, board.setsToWin, theme, alignEnd = true)
        }
    }

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { SituationRow(situations) }
    }

    if (live) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(9.dp))
        ServeMeter(state, board, theme)
    }
}

/**
 * The serve meter: a track between the two names with the serving side's end lit, and the
 * serves left in the pair as two segments that empty as they are used.
 */
@Composable
private fun ServeMeter(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val server = board.serving
    if (server == null) {
        HeroLabel("Serve order not recorded", Modifier.fillMaxWidth(), align = TextAlign.Center)
        return
    }
    val left = board.servesLeft ?: 2
    val deuce = board.deuce || (board.current?.let { it.first >= 10 && it.second >= 10 } == true)
    val pair = if (deuce) 1 else 2

    BoxWithConstraints(Modifier.fillMaxWidth()) {
        val slot = 28.dp
        val x by animateDpAsState(if (server == "home") 0.dp else maxWidth - slot, tween(420), label = "serveSlide")
        Box(Modifier.fillMaxWidth().height(22.dp)) {
            Box(Modifier.align(Alignment.Center).fillMaxWidth().height(2.dp).background(Color.White.copy(alpha = 0.6f)))
            Box(
                Modifier.offset(x = x).size(width = slot, height = 22.dp).clip(RoundedCornerShape(11.dp)).background(theme.deep),
                contentAlignment = Alignment.Center,
            ) {
                Box(Modifier.size(8.dp).clip(CircleShape).background(Color(0xFFFFF7ED)))
            }
            Row(Modifier.align(Alignment.Center), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "${teamShortCode(if (server == "home") state.team1 else state.team2)} SERVING",
                    color = Color(0xFF0F172A), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp,
                    modifier = Modifier.clip(RoundedCornerShape(6.dp)).background(Color.White.copy(alpha = 0.85f)).padding(horizontal = 6.dp, vertical = 2.dp),
                )
                Spacer(Modifier.width(6.dp))
                repeat(pair) { i ->
                    val used = i >= left
                    val c by animateColorAsState(if (used) Color.White.copy(alpha = 0.7f) else theme.deep, tween(260), label = "serveSeg")
                    Box(Modifier.padding(end = 3.dp).size(width = 12.dp, height = 5.dp).clip(RoundedCornerShape(3.dp)).background(c))
                }
                Text(
                    if (deuce) "every point" else "$left left",
                    color = Color(0xFF475569), fontSize = 9.5.sp, fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

@Composable
fun GamePips(won: Int, toWin: Int, theme: SportTheme, alignEnd: Boolean) {
    Row(horizontalArrangement = if (alignEnd) Arrangement.End else Arrangement.Start, verticalAlignment = Alignment.CenterVertically) {
        repeat(toWin) { i ->
            val lit = i < won
            val c by animateColorAsState(if (lit) theme.deep else Color.White.copy(alpha = 0.75f), tween(400), label = "gamePip")
            val s by animateDpAsState(if (lit) 9.dp else 7.dp, tween(400), label = "gamePipSize")
            Box(Modifier.padding(end = 4.dp).size(s).clip(CircleShape).background(c))
        }
    }
}

/** Every game so far as a chip — won games tinted for the winner, the live one outlined. */
@Composable
fun GameStripPanel(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    BoardPanel {
        PanelTitle("${board.setNoun}s", "best of ${board.bestOf} · to ${if (board.target > 0) board.target else 11}")
        Spacer(Modifier.height(14.dp))
        Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            board.sets.forEachIndexed { i, (h, a) ->
                GameChip(i + 1, "$h–$a", if (h > a) state.team1Color else state.team2Color, live = false)
            }
            board.current?.let { (h, a) ->
                if (!board.decided && state.isLive) GameChip(board.sets.size + 1, "$h–$a", theme.deep, live = true)
            }
            if (board.sets.isEmpty() && board.current == null) {
                Text("No ${board.setNoun.lowercase()}s yet", fontSize = 12.5.sp, color = BoardInk.faint)
            }
        }
    }
}

@Composable
private fun GameChip(number: Int, label: String, accent: Color, live: Boolean) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text("G$number", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, letterSpacing = 0.6.sp)
        Spacer(Modifier.height(4.dp))
        Box(
            Modifier
                .clip(RoundedCornerShape(10.dp))
                .background(if (live) Color.White else accent.copy(alpha = 0.13f))
                .then(if (live) Modifier.border(1.5.dp, accent, RoundedCornerShape(10.dp)) else Modifier)
                .padding(horizontal = 11.dp, vertical = 8.dp),
        ) {
            Text(label, fontSize = 13.sp, fontWeight = if (live) FontWeight.Black else FontWeight.Bold, color = accent)
        }
    }
}
