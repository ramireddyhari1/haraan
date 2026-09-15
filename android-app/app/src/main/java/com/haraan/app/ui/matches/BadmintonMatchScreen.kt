@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateOffsetAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
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
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Badminton's match detail — its own screen, not table tennis' with a different colour.
 *
 * Badminton is rally-scored: whoever wins the rally serves the next, from the RIGHT service
 * court on an even score and the LEFT on an odd one. So the hero carries a small court seen
 * from above with the shuttle sitting in the service box it will be struck from — that one
 * drawing answers "who serves, and from where" the way an umpire's call does. Deuce from
 * 20–20, the golden point at 29–29, the interval at 11 and the change of ends in a decider
 * all come from the server's replay.
 */
@Composable
fun BadmintonMatchScreen(
    state: MatchUiState,
    board: SportBoard,
    watching: Int = 0,
    onBack: () -> Unit = {},
    onScore: () -> Unit = {},
    onWatchers: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
    matchId: String = "",
) {
    val theme = sportThemeFor("badminton")
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
                            if (state.isLive) "Live · best of ${board.bestOf} games" else state.status.ifBlank { theme.label }
                        },
                        ribbonWord = ribbon.first,
                        ribbonColor = ribbon.second,
                        ribbonKey = ribbon.third,
                        moment = moment,
                    ) {
                        BadmintonHero(state, board, theme)
                    }
                }
            }
            stickyHeader {
                CrexBoardTabs(
                    tabs = listOf("Summary", "Rallies", "Players", "Insights"),
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
                        crexItem(tab) { BoardNotStarted(state.canScore, "The board fills in rally by rally.") }
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
                    crexItem(tab) { BoardNotStarted(state.canScore, "Every rally lands here as it is won.") }
                } else {
                    crexItem(tab) { LiveFeedPanel("Rallies", state, board, theme) }
                }
                2 -> crexItem(tab) { BoardLineups(state) }
                else -> crexItem(tab) {
                    com.haraan.app.ui.matches.insights.BadmintonInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

@Composable
private fun BadmintonHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val rally = board.current ?: board.sets.lastOrNull() ?: (0 to 0)
    val live = state.isLive && !board.decided

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            HeroSideTag(state.team1, state.team1Logo, active = live && board.serving == "home", label = state.team1FullName, activeColor = theme.spark)
            Spacer(Modifier.height(4.dp))
            HeroNumeral(rally.first, if (rally.first >= rally.second) theme.deep else theme.soft, 52)
            GamePips(board.setsHome, board.setsToWin, theme, alignEnd = false)
        }
        Column(Modifier.padding(top = 4.dp, start = 6.dp, end = 6.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            HeroChip(if (live) "Game ${board.sets.size + 1}" else "FINAL")
            if (live) {
                Spacer(Modifier.height(8.dp))
                ServiceCourt(board.serving, board.serviceCourt, theme)
            }
        }
        Column(Modifier.weight(1f), horizontalAlignment = Alignment.End) {
            HeroSideTag(state.team2, state.team2Logo, active = live && board.serving == "away", modifier = Modifier.fillMaxWidth(), alignEnd = true, label = state.team2FullName, activeColor = theme.spark)
            Spacer(Modifier.height(4.dp))
            HeroNumeral(rally.second, if (rally.second >= rally.first) theme.deep else theme.soft, 52)
            GamePips(board.setsAway, board.setsToWin, theme, alignEnd = true)
        }
    }

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { SituationRow(situations) }
    }
    if (live) {
        Spacer(Modifier.height(10.dp))
        HeroLabel(
            when {
                board.serving == null -> "First to ${board.target.takeIf { it > 0 } ?: 21} · serve decided by the first rally"
                else -> "${teamShortCode(if (board.serving == "home") state.team1 else state.team2)} serves from the ${board.serviceCourt ?: "right"} · first to ${board.target.takeIf { it > 0 } ?: 21}" +
                    (board.cap?.let { " · cap $it" } ?: "")
            },
            Modifier.fillMaxWidth(),
            align = TextAlign.Center,
        )
    }
}

/**
 * The court from above: home's half on the left, away's on the right, each split into its
 * two service boxes. The shuttle glides to the box the server strikes from.
 */
@Composable
private fun ServiceCourt(serving: String?, court: String?, theme: SportTheme) {
    // In each half, "right" is the box on that player's right as they face the net.
    val target = when {
        serving == "home" && court == "right" -> Offset(0.25f, 0.75f)
        serving == "home" -> Offset(0.25f, 0.25f)
        serving == "away" && court == "right" -> Offset(0.75f, 0.25f)
        serving == "away" -> Offset(0.75f, 0.75f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos by animateOffsetAsState(target, tween(460, easing = FastOutSlowInEasing), label = "shuttle")
    val line = theme.deep.copy(alpha = 0.55f)
    val lit by animateColorAsState(if (serving != null) theme.spark.copy(alpha = 0.22f) else Color.Transparent, tween(300), label = "courtLit")

    Canvas(Modifier.size(width = 64.dp, height = 38.dp)) {
        val w = size.width
        val h = size.height
        val stroke = 1.2.dp.toPx()
        drawRoundRect(Color.White.copy(alpha = 0.7f), cornerRadius = CornerRadius(4.dp.toPx()))
        if (serving != null) {
            val boxW = w / 2
            val boxH = h / 2
            val bx = if (pos.x < 0.5f) 0f else boxW
            val by = if (pos.y < 0.5f) 0f else boxH
            drawRect(lit, topLeft = Offset(bx, by), size = Size(boxW, boxH))
        }
        drawRoundRect(line, cornerRadius = CornerRadius(4.dp.toPx()), style = Stroke(stroke))
        drawLine(theme.deep, Offset(w / 2, 0f), Offset(w / 2, h), strokeWidth = 2.dp.toPx())  // net
        drawLine(line, Offset(0f, h / 2), Offset(w, h / 2), strokeWidth = stroke)            // centre lines
        if (serving != null) {
            drawCircle(theme.deep, 4.dp.toPx(), Offset(pos.x * w, pos.y * h))
            drawCircle(Color.White, 1.6.dp.toPx(), Offset(pos.x * w, pos.y * h))
        }
    }
}
