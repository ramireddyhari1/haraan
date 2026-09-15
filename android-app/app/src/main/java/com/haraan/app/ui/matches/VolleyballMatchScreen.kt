@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.cos
import kotlin.math.sin

/**
 * Volleyball's match detail.
 *
 * A volleyball crowd watches two numbers that are not the same kind of number: the RALLY score
 * in the set being played, and the SETS each side has banked. Put them side by side at the same
 * weight and people read the wrong one. So here the rally is the huge figure in the middle of
 * the card, and each side's sets won sits in its own dark tile beside its crest — a different
 * shape, a different colour, labelled SETS. The serve, each side's rotation and the timeouts
 * left in the set sit underneath, because those are what decide the next rally.
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
    /** Server id — the Insights tab reads this match's event log by it. */
    matchId: String = "",
) {
    val theme = sportThemeFor("volleyball")
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
                        VolleyballHero(state, board, theme)
                    }
                }
            }

            stickyHeader {
                CrexBoardTabs(
                    tabs = listOf("Summary", "Rallies", "Line-ups", "Insights"),
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
                        crexItem(tab) { SetsPanel(state, board, theme) }
                    }
                    if (board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The board fills in rally by rally.") }
                    } else {
                        board.teamStats?.let { s ->
                            if (s.has("serve_points_played")) {
                                crexItem(tab) { ServeReceivePanel(state, s) }
                            }
                        }
                    }
                    if (board.scorers.isNotEmpty()) {
                        crexItem(tab) { ScorerPanel("Points scored", "PTS", state, board) }
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
                    com.haraan.app.ui.matches.insights.VolleyballInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

@Composable
private fun VolleyballHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val rally = board.current ?: board.sets.lastOrNull() ?: (0 to 0)
    val setNumber = if (board.current != null) board.sets.size + 1 else board.sets.size

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        HeroSideTag(state.team1, state.team1Logo, board.serving == "home", Modifier.weight(1f), label = state.team1FullName, activeColor = theme.spark)
        HeroSideTag(state.team2, state.team2Logo, board.serving == "away", Modifier.weight(1f), alignEnd = true, label = state.team2FullName, activeColor = theme.spark)
    }
    Spacer(Modifier.height(12.dp))
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        SetsWonTile(board.setsHome, board.setsToWin, theme)
        Spacer(Modifier.weight(1f))
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                ServeMarker(board.serving == "home" && state.isLive, theme.spark, size = 9)
                Spacer(Modifier.width(8.dp))
                HeroNumeral(rally.first, if (rally.first >= rally.second) theme.deep else theme.soft, 56)
                Text("–", color = theme.soft.copy(alpha = 0.5f), fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 12.dp))
                HeroNumeral(rally.second, if (rally.second >= rally.first) theme.deep else theme.soft, 56)
                Spacer(Modifier.width(8.dp))
                ServeMarker(board.serving == "away" && state.isLive, theme.spark, size = 9)
            }
            HeroLabel(
                when {
                    !state.isLive || board.decided -> "Final set"
                    board.target > 0 -> "Set $setNumber · to ${board.target}" + if (board.decider) " · decider" else ""
                    else -> "Best of ${board.bestOf}"
                },
                align = TextAlign.Center,
            )
        }
        Spacer(Modifier.weight(1f))
        SetsWonTile(board.setsAway, board.setsToWin, theme)
    }

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { SituationRow(situations) }
    }

    // Rotation and timeouts — what decides the next rally, per side.
    if (state.isLive && !board.decided && (board.rotation != null || board.timeouts != null)) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            CourtState(board.rotation?.first, board.timeouts?.first, board.timeoutsAllowed ?: 2, theme, alignEnd = false, modifier = Modifier.weight(1f))
            HeroLabel("Rotation · T/O", align = TextAlign.Center)
            CourtState(board.rotation?.second, board.timeouts?.second, board.timeoutsAllowed ?: 2, theme, alignEnd = true, modifier = Modifier.weight(1f))
        }
    }

    if (board.sets.isNotEmpty() || board.current != null) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(7.dp), verticalAlignment = Alignment.Bottom) {
            board.sets.forEachIndexed { i, (h, a) ->
                SetRung("${i + 1}", "$h–$a", won = h > a, live = false, theme = theme)
            }
            if (board.current != null) {
                SetRung("${board.sets.size + 1}", "${rally.first}–${rally.second}", won = false, live = true, theme = theme)
            }
        }
    }
}

/**
 * Sets won, as a tile that cannot be mistaken for the rally score: dark, small caps label,
 * and one notch per set needed so "2" reads as "2 of 3".
 */
@Composable
private fun SetsWonTile(won: Int, toWin: Int, theme: SportTheme) {
    Column(
        Modifier.clip(RoundedCornerShape(12.dp)).background(theme.deep).padding(horizontal = 11.dp, vertical = 7.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("SETS", color = Color.White.copy(alpha = 0.7f), fontSize = 8.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
        RollingFigure("$won", Color.White, 24)
        Row(horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            repeat(toWin) { i ->
                val alpha by animateFloatAsState(if (i < won) 1f else 0.25f, tween(400), label = "setNotch")
                Box(Modifier.size(width = 7.dp, height = 3.dp).clip(RoundedCornerShape(2.dp)).background(theme.spark.copy(alpha = alpha)))
            }
        }
    }
}

@Composable
private fun CourtState(rotation: Int?, timeoutsUsed: Int?, allowed: Int, theme: SportTheme, alignEnd: Boolean, modifier: Modifier) {
    Row(
        modifier,
        horizontalArrangement = Arrangement.spacedBy(8.dp, if (alignEnd) Alignment.End else Alignment.Start),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        val dial: @Composable () -> Unit = { if (rotation != null) RotationDial(rotation, theme) }
        val pips: @Composable () -> Unit = {
            if (timeoutsUsed != null) {
                Row(horizontalArrangement = Arrangement.spacedBy(3.dp)) {
                    repeat(allowed) { i ->
                        val left = i < allowed - timeoutsUsed
                        Box(
                            Modifier.size(width = 10.dp, height = 5.dp).clip(RoundedCornerShape(2.dp))
                                .background(if (left) theme.deep else Color.White.copy(alpha = 0.55f)),
                        )
                    }
                }
            }
        }
        if (alignEnd) { pips(); dial() } else { dial(); pips() }
    }
}

/**
 * Rotation as a six-position ring. The lit position turns clockwise one step on every
 * side-out — the rotation a team physically makes — and the number says which it is.
 */
@Composable
private fun RotationDial(rotation: Int, theme: SportTheme) {
    val angle by animateFloatAsState(
        targetValue = (rotation - 1) * 60f,
        animationSpec = tween(520, easing = FastOutSlowInEasing),
        label = "rotationDial",
    )
    Box(Modifier.size(26.dp), contentAlignment = Alignment.Center) {
        Canvas(Modifier.fillMaxSize()) {
            val r = size.minDimension / 2 - 3.dp.toPx()
            val c = Offset(size.width / 2, size.height / 2)
            for (i in 0 until 6) {
                val a = Math.toRadians((i * 60f - 90f).toDouble())
                drawCircle(Color.White.copy(alpha = 0.75f), 2.dp.toPx(), Offset(c.x + r * cos(a).toFloat(), c.y + r * sin(a).toFloat()))
            }
            val a = Math.toRadians((angle - 90f).toDouble())
            drawCircle(theme.deep, 3.2.dp.toPx(), Offset(c.x + r * cos(a).toFloat(), c.y + r * sin(a).toFloat()))
        }
        Text("$rotation", color = theme.deep, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold)
    }
}

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
                .then(if (live) Modifier.border(1.dp, theme.deep.copy(alpha = 0.55f), RoundedCornerShape(7.dp)) else Modifier)
                .padding(horizontal = 8.dp, vertical = 4.dp),
        ) {
            Text(score, color = if (won && !live) Color.White else Color(0xFF334155), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
        }
    }
}

@Composable
private fun SetsPanel(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    BoardPanel {
        PanelTitle("Sets", "${board.setsHome}–${board.setsAway}  ·  best of ${board.bestOf}")
        Spacer(Modifier.height(12.dp))
        board.sets.forEachIndexed { i, (h, a) ->
            if (i > 0) Spacer(Modifier.height(10.dp))
            val homeWon = h > a
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Set ${i + 1}", fontSize = 12.5.sp, color = BoardInk.faint, modifier = Modifier.width(52.dp))
                Text(state.team1, fontSize = 13.sp, fontWeight = if (homeWon) FontWeight.Bold else FontWeight.Normal, color = if (homeWon) BoardInk.ink else BoardInk.muted, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                Text("$h", fontSize = 15.sp, fontWeight = if (homeWon) FontWeight.ExtraBold else FontWeight.Medium, color = if (homeWon) theme.deep else BoardInk.muted)
                Text("–", fontSize = 13.sp, color = BoardInk.faint, modifier = Modifier.padding(horizontal = 6.dp))
                Text("$a", fontSize = 15.sp, fontWeight = if (!homeWon) FontWeight.ExtraBold else FontWeight.Medium, color = if (!homeWon) theme.deep else BoardInk.muted)
                Text(state.team2, fontSize = 13.sp, fontWeight = if (!homeWon) FontWeight.Bold else FontWeight.Normal, color = if (!homeWon) BoardInk.ink else BoardInk.muted, maxLines = 1, overflow = TextOverflow.Ellipsis, textAlign = TextAlign.End, modifier = Modifier.weight(1f).padding(start = 8.dp))
            }
        }
    }
}

/** Serve and side-out — how often each side held its serve and won it back. */
@Composable
fun ServeReceivePanel(state: MatchUiState, s: TeamStats) {
    fun pct(won: Int, played: Int) = if (played == 0) 0 else won * 100 / played
    BoardPanel {
        PanelTitle("On serve")
        Spacer(Modifier.height(4.dp))
        Text("Rallies won while serving, from the recorded serve order.", fontSize = 11.5.sp, color = BoardInk.faint)
        Spacer(Modifier.height(12.dp))
        StatDuel("Won on serve %", pct(s.home("serve_points_won"), s.home("serve_points_played")), pct(s.away("serve_points_won"), s.away("serve_points_played")), state.team1Color, state.team2Color)
        Spacer(Modifier.height(12.dp))
        StatDuel("Side-outs", s.home("side_outs"), s.away("side_outs"), state.team1Color, state.team2Color)
        if (s.has("aces")) {
            Spacer(Modifier.height(12.dp))
            StatDuel("Aces", s.home("aces"), s.away("aces"), state.team1Color, state.team2Color)
        }
        if (s.has("timeouts")) {
            Spacer(Modifier.height(12.dp))
            StatDuel("Timeouts", s.home("timeouts"), s.away("timeouts"), state.team1Color, state.team2Color)
        }
    }
}
