@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
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
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Basketball's match detail — read the way the sport is read: by quarter, and by box score.
 *
 * Basketball's score moves constantly and in different sizes, so the hero carries what a gym
 * scoreboard carries beside the totals: the quarter, each side's TEAM FOULS in it (the fifth
 * puts a team in the penalty) and timeouts left. The quarter line sits inside the hero because
 * a 30–12 third quarter IS the game. Every figure is the server's replay of recorded events —
 * a rebound nobody tapped is not a zero, it is simply not on the box score.
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
    /** Server id — the Insights tab reads this match's event log by it. */
    matchId: String = "",
) {
    val theme = sportThemeFor("basketball")
    var tab by remember { mutableStateOf(0) }
    val listState = rememberLazyListState()
    val ribbon = crexRibbonFor(board, theme)
    val moment = rememberFreshMoment(heroMomentFor(board, state, theme), board.feed.firstOrNull()?.sequence ?: 0)
    val box = board.box
    val hasBox = box != null && (box.players.isNotEmpty() || box.home.reb + box.away.reb + box.home.pf + box.away.pf > 0)

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
                        BasketballHero(state, board, theme)
                    }
                }
            }

            stickyHeader {
                CrexBoardTabs(
                    tabs = listOf("Summary", "Box score", "Play by play", "Line-ups", "Insights"),
                    selectedTabIndex = tab,
                    accent = theme.deep,
                    onTabSelected = { tab = it },
                    liveTab = 2,
                    liveActive = state.isLive,
                )
            }
            item { Spacer(Modifier.height(6.dp)) }

            when (tab) {
                0 -> {
                    if (board.feed.isEmpty()) {
                        crexItem(tab) { BoardNotStarted(state.canScore, "The box score fills in as buckets go down.") }
                    }
                    if (box != null && board.feed.isNotEmpty()) {
                        crexItem(tab) { TeamComparison(state, box) }
                    }
                    if (box != null && box.players.isNotEmpty()) {
                        crexItem(tab) { Leaders(state, box) }
                    }
                    if (board.pointMoments.size >= 2) {
                        crexItem(tab) { BoardMomentum(state, board) }
                    }
                    crexItem(tab) { Spacer(Modifier.height(4.dp)); BoardMatchInfo(state) }
                }
                1 -> if (!hasBox) {
                    crexItem(tab) {
                        BoardNotStarted(
                            state.canScore,
                            "Name the shooter, rebounder or fouler when scoring and each player's line appears here.",
                        )
                    }
                } else {
                    crexItem(tab) { BoxScoreTable(state.team1FullName.ifBlank { state.team1 }, state.team1Color, box!!.players.filter { it.side == "home" }, box.home) }
                    crexItem(tab) { BoxScoreTable(state.team2FullName.ifBlank { state.team2 }, state.team2Color, box!!.players.filter { it.side == "away" }, box.away) }
                }
                2 -> if (board.feed.isEmpty()) {
                    crexItem(tab) { BoardNotStarted(state.canScore, "Every bucket, rebound and foul lands here as it happens.") }
                } else {
                    crexItem(tab) { LiveFeedPanel("Play by play", state, board, theme) }
                }
                3 -> crexItem(tab) { BoardLineups(state) }
                else -> crexItem(tab) {
                    com.haraan.app.ui.matches.insights.BasketballInsightsTab(matchId, state, board, theme)
                }
            }
        }
    }
}

/**
 * Basketball's hero: totals, the quarter, team fouls and timeouts, then the quarter line.
 */
@Composable
private fun BasketballHero(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val (home, away) = board.totals
    val periodShort = board.periodLabel.ifBlank { "Q${board.period}" }
    val fouls = board.teamFouls ?: (0 to 0)
    val timeouts = board.timeouts

    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            HeroSideTag(state.team1, state.team1Logo, active = false, label = state.team1FullName)
            Spacer(Modifier.height(4.dp))
            HeroNumeral(home, if (home >= away) theme.deep else theme.soft, 44)
            Spacer(Modifier.height(4.dp))
            FoulMeter(fouls.first, theme, alignEnd = false)
        }
        Column(
            Modifier.padding(top = 8.dp, start = 6.dp, end = 6.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            HeroChip(if (state.isLive) periodShort else "FINAL")
            if (timeouts != null && board.timeoutsAllowed != null && state.isLive) {
                Spacer(Modifier.height(10.dp))
                HeroLabel("Timeouts")
                Spacer(Modifier.height(4.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    TimeoutPips(board.timeoutsAllowed - timeouts.first, board.timeoutsAllowed, theme.deep)
                    Spacer(Modifier.width(8.dp))
                    TimeoutPips(board.timeoutsAllowed - timeouts.second, board.timeoutsAllowed, theme.soft)
                }
            }
        }
        Column(Modifier.weight(1f), horizontalAlignment = Alignment.End) {
            HeroSideTag(
                state.team2, state.team2Logo, active = false, modifier = Modifier.fillMaxWidth(),
                alignEnd = true, label = state.team2FullName,
            )
            Spacer(Modifier.height(4.dp))
            HeroNumeral(away, if (away >= home) theme.deep else theme.soft, 44)
            Spacer(Modifier.height(4.dp))
            FoulMeter(fouls.second, theme, alignEnd = true)
        }
    }

    val situations = situationsFor(board, state)
    if (situations.isNotEmpty()) {
        Spacer(Modifier.height(10.dp))
        SituationRow(situations, Modifier.fillMaxWidth())
    }

    if (board.periods.isNotEmpty()) {
        Spacer(Modifier.height(12.dp))
        HeroRule()
        Spacer(Modifier.height(9.dp))
        val regulation = board.regulationPeriods ?: 4
        val columns = maxOf(regulation, board.periods.size)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Spacer(Modifier.width(44.dp))
            repeat(columns) { i ->
                HeroLabel(if (i < regulation) "Q${i + 1}" else "OT${if (i - regulation > 0) i - regulation + 1 else ""}", Modifier.weight(1f), align = TextAlign.Center)
            }
            HeroLabel("T", Modifier.width(36.dp), align = TextAlign.Center)
        }
        Spacer(Modifier.height(6.dp))
        QuarterRow(teamShortCode(state.team1), board.periods.map { it.first }, columns, board.periods.size - 1, state.isLive, home, theme.deep)
        Spacer(Modifier.height(5.dp))
        QuarterRow(teamShortCode(state.team2), board.periods.map { it.second }, columns, board.periods.size - 1, state.isLive, away, theme.soft)
    }
}

/**
 * Team fouls in the quarter as five pips. The fifth turns the row amber and names the
 * penalty — from here every foul is free throws, which is what the crowd is counting.
 */
@Composable
private fun FoulMeter(fouls: Int, theme: SportTheme, alignEnd: Boolean) {
    val penalty = fouls >= 5
    Row(
        horizontalArrangement = Arrangement.spacedBy(3.dp, if (alignEnd) Alignment.End else Alignment.Start),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (alignEnd) {
            Text(
                if (penalty) "PENALTY" else "FOULS $fouls",
                color = if (penalty) Color(0xFFB45309) else Color(0xFF475569),
                fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp,
            )
            Spacer(Modifier.width(4.dp))
        }
        repeat(5) { i ->
            val lit = i < fouls
            val c by animateColorAsState(
                if (lit) (if (penalty) Color(0xFFD97706) else theme.deep) else Color.White.copy(alpha = 0.6f),
                tween(300), label = "foulPip",
            )
            Box(Modifier.size(width = 9.dp, height = 4.dp).clip(RoundedCornerShape(2.dp)).background(c))
        }
        if (!alignEnd) {
            Spacer(Modifier.width(4.dp))
            Text(
                if (penalty) "PENALTY" else "FOULS $fouls",
                color = if (penalty) Color(0xFFB45309) else Color(0xFF475569),
                fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp,
            )
        }
    }
}

@Composable
private fun TimeoutPips(left: Int, allowed: Int, color: Color) {
    Row(horizontalArrangement = Arrangement.spacedBy(2.dp)) {
        repeat(allowed) { i ->
            val alpha by animateFloatAsState(if (i < left) 1f else 0.2f, tween(300), label = "timeoutPip")
            Box(Modifier.size(5.dp).clip(CircleShape).background(color.copy(alpha = alpha)))
        }
    }
}

@Composable
private fun QuarterRow(code: String, perPeriod: List<Int>, columns: Int, liveIndex: Int, live: Boolean, total: Int, totalColor: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            code, color = Color(0xFF334155), fontSize = 11.5.sp, fontWeight = FontWeight.Bold,
            maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.width(44.dp),
        )
        repeat(columns) { i ->
            val v = perPeriod.getOrNull(i)
            val current = live && i == liveIndex
            Box(Modifier.weight(1f), contentAlignment = Alignment.Center) {
                if (v == null) {
                    Text("–", color = Color(0xFF94A3B8), fontSize = 12.5.sp)
                } else if (current) {
                    Box(
                        Modifier.clip(RoundedCornerShape(5.dp)).background(Color.White.copy(alpha = 0.7f))
                            .padding(horizontal = 5.dp, vertical = 1.dp),
                    ) { RollingFigure("$v", Color(0xFF0F172A), 12, fontWeight = FontWeight.ExtraBold, display = false) }
                } else {
                    Text("$v", color = Color(0xFF475569), fontSize = 12.5.sp, textAlign = TextAlign.Center)
                }
            }
        }
        Box(Modifier.width(36.dp), contentAlignment = Alignment.Center) {
            RollingFigure("$total", totalColor, 13, fontWeight = FontWeight.ExtraBold, display = false)
        }
    }
}

/** Head-to-head team totals — only the categories the scorer has actually recorded. */
@Composable
private fun TeamComparison(state: MatchUiState, box: BoxScore) {
    val h = box.home
    val a = box.away
    val rows = listOf(
        Triple("Two-pointers", h.fg2, a.fg2),
        Triple("Three-pointers", h.fg3, a.fg3),
        Triple("Free throws", h.ft, a.ft),
        Triple("Rebounds", h.reb, a.reb),
        Triple("Assists", h.ast, a.ast),
        Triple("Steals", h.stl, a.stl),
        Triple("Blocks", h.blk, a.blk),
        Triple("Turnovers", h.to, a.to),
        Triple("Fouls", h.pf, a.pf),
    ).filter { it.second + it.third > 0 }
    if (rows.isEmpty()) return

    BoardPanel {
        PanelTitle("Team stats")
        Spacer(Modifier.height(4.dp))
        Text("Made shots and recorded plays — nothing is estimated.", fontSize = 11.5.sp, color = BoardInk.faint)
        Spacer(Modifier.height(12.dp))
        rows.forEachIndexed { i, (label, hv, av) ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            StatDuel(label, hv, av, state.team1Color, state.team2Color)
        }
    }
}

/** One stat, two sides, a bar that grows to its share. */
@Composable
fun StatDuel(label: String, home: Int, away: Int, homeColor: Color, awayColor: Color) {
    val total = (home + away).coerceAtLeast(1)
    val homeShare by animateFloatAsState(home.toFloat() / total, tween(600), label = "duelShare")
    Column {
        Row(verticalAlignment = Alignment.CenterVertically) {
            RollingFigure("$home", BoardInk.ink, 14, fontWeight = FontWeight.ExtraBold, display = false)
            Text(label, fontSize = 12.sp, color = BoardInk.muted, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
            RollingFigure("$away", BoardInk.ink, 14, fontWeight = FontWeight.ExtraBold, display = false)
        }
        Spacer(Modifier.height(6.dp))
        Row(Modifier.fillMaxWidth().height(6.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            Box(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(3.dp)).background(Color(0xFFEFF2F6))) {
                Box(
                    Modifier.fillMaxHeight().fillMaxWidth(if (home + away == 0) 0f else homeShare.coerceAtLeast(0.02f))
                        .align(Alignment.CenterEnd).clip(RoundedCornerShape(3.dp)).background(homeColor),
                )
            }
            Box(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(3.dp)).background(Color(0xFFEFF2F6))) {
                Box(
                    Modifier.fillMaxHeight().fillMaxWidth(if (home + away == 0) 0f else (1f - homeShare).coerceAtLeast(0.02f))
                        .clip(RoundedCornerShape(3.dp)).background(awayColor),
                )
            }
        }
    }
}

/** The leaders in the three categories the sport ranks by — named players only. */
@Composable
private fun Leaders(state: MatchUiState, box: BoxScore) {
    val cats = listOf(
        Triple("Points", "PTS") { p: BoxLine -> p.pts },
        Triple("Rebounds", "REB") { p: BoxLine -> p.reb },
        Triple("Assists", "AST") { p: BoxLine -> p.ast },
    ).mapNotNull { (label, unit, pick) ->
        box.players.filter { pick(it) > 0 }.maxByOrNull(pick)?.let { Triple(label, unit, it to pick(it)) }
    }
    if (cats.isEmpty()) return

    Column {
        BoardOverline("Leaders")
        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            cats.forEach { (label, unit, pair) ->
                val (p, v) = pair
                val accent = if (p.side == "home") state.team1Color else state.team2Color
                Column(
                    Modifier.weight(1f).clip(RoundedCornerShape(14.dp)).background(CrexColors.Surface)
                        .padding(horizontal = 12.dp, vertical = 12.dp),
                ) {
                    HeroLabel(label)
                    Spacer(Modifier.height(6.dp))
                    Row(verticalAlignment = Alignment.Bottom) {
                        RollingFigure("$v", accent, 26)
                        Spacer(Modifier.width(4.dp))
                        Text(unit, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, modifier = Modifier.padding(bottom = 4.dp))
                    }
                    Text(p.name, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text(if (p.side == "home") state.team1 else state.team2, fontSize = 10.5.sp, color = BoardInk.faint, maxLines = 1)
                }
            }
        }
    }
}

/**
 * One team's box score. Scrolls sideways on a narrow phone rather than squeezing seven
 * columns into unreadable figures; the name column stays readable at the left.
 */
@Composable
private fun BoxScoreTable(team: String, accent: Color, players: List<BoxLine>, totals: BoxLine) {
    val columns = listOf(
        "PTS" to { p: BoxLine -> p.pts },
        "REB" to { p: BoxLine -> p.reb },
        "AST" to { p: BoxLine -> p.ast },
        "STL" to { p: BoxLine -> p.stl },
        "BLK" to { p: BoxLine -> p.blk },
        "3PM" to { p: BoxLine -> p.fg3 },
        "FTM" to { p: BoxLine -> p.ft },
        "TO" to { p: BoxLine -> p.to },
        "PF" to { p: BoxLine -> p.pf },
    )
    val num = TextStyle(fontFeatureSettings = "tnum")
    BoardPanel {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(accent))
            Spacer(Modifier.width(8.dp))
            Text(team, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text("${totals.pts} PTS", fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = accent)
        }
        Spacer(Modifier.height(12.dp))
        if (players.isEmpty()) {
            Text("No named players yet.", fontSize = 12.5.sp, color = BoardInk.faint)
            return@BoardPanel
        }
        Row {
            Column(Modifier.width(112.dp)) {
                Text("PLAYER", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, letterSpacing = 0.8.sp)
                players.forEach { p ->
                    Spacer(Modifier.height(10.dp))
                    Text(p.name, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.height(18.dp))
                }
                Spacer(Modifier.height(10.dp))
                Text("TEAM", fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.muted, modifier = Modifier.height(18.dp))
            }
            Row(Modifier.weight(1f).horizontalScroll(rememberScrollState())) {
                columns.forEach { (head, pick) ->
                    Column(Modifier.width(40.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(head, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, letterSpacing = 0.6.sp)
                        players.forEach { p ->
                            Spacer(Modifier.height(10.dp))
                            val v = pick(p)
                            // Foul trouble is the one number on a box score that changes decisions.
                            val color = when {
                                head == "PF" && v >= 5 -> Color(0xFFDC2626)
                                head == "PF" && v >= 4 -> Color(0xFFD97706)
                                head == "PTS" -> BoardInk.ink
                                v == 0 -> BoardInk.faint
                                else -> BoardInk.muted
                            }
                            Text(
                                "$v", fontSize = 13.sp, color = color, style = num,
                                fontWeight = if (head == "PTS" || (head == "PF" && v >= 4)) FontWeight.ExtraBold else FontWeight.Medium,
                                modifier = Modifier.height(18.dp),
                            )
                        }
                        Spacer(Modifier.height(10.dp))
                        Text("${pick(totals)}", fontSize = 13.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink, style = num, modifier = Modifier.height(18.dp))
                    }
                }
            }
        }
    }
}
