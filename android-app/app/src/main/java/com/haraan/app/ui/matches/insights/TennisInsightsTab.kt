package com.haraan.app.ui.matches.insights

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.SportBoard
import com.haraan.app.ui.matches.SportTheme
import com.haraan.app.ui.matches.teamShortCode
import org.json.JSONObject

/**
 * Tennis' Insights — points won against games won.
 *
 * Tennis is the one sport here where you can win more points and lose the match, because
 * points only count once they finish a game. So the signature puts the two side by side: a
 * ring split by the share of POINTS each player won, with the GAMES tally set inside it. If
 * the ring and the tally disagree, that is the story of the match. Then the sets, the deuce
 * games, and the longest streak of games. Serve is not recorded, so breaks and aces are not
 * shown — no figure here pretends to know who served.
 */
@Composable
fun TennisInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No points yet", "Points against games, deuce games and player value appear with the first point.") { d ->
        val team = d.team
        val games = team.sidePair("games")
        val points = team.sidePair("points_won")
        val deuceWon = team.sidePair("deuce_games_won")
        val streak = team.sidePair("best_game_streak")
        val sets = team.objectList("sets")

        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "point", theme.deep)

            InsightHead("Points vs games")
            InsightPanel(Modifier.insightEnter(0)) { PointsRing(state, points, games, theme) }

            if (sets.isNotEmpty()) {
                InsightHead("Set by set", "games · points")
                InsightPanel(Modifier.insightEnter(1)) {
                    sets.forEachIndexed { i, s ->
                        if (i > 0) {
                            Spacer(Modifier.height(10.dp))
                            Box(Modifier.fillMaxWidth().height(1.dp).background(BoardInk.hairline))
                            Spacer(Modifier.height(10.dp))
                        }
                        SetLine(s, state, theme)
                    }
                }
            }

            InsightHead("The long games")
            InsightPanel(Modifier.insightEnter(2)) {
                DuelBar("Deuce games won", deuceWon.first, deuceWon.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("Most games in a row", streak.first, streak.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(12.dp))
                Text(
                    "${team.optInt("deuce_games")} game${if (team.optInt("deuce_games") == 1) "" else "s"} went to deuce",
                    fontSize = 11.5.sp, color = BoardInk.muted,
                )
            }

            InsightHead("Player value")
            PlayerValueList(d.players, state, theme.deep, "points", startIndex = 3)

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

/** A ring split by points won, with the games tally inside it. */
@Composable
private fun PointsRing(state: MatchUiState, points: Pair<Int, Int>, games: Pair<Int, Int>, theme: SportTheme) {
    val total = (points.first + points.second).coerceAtLeast(1)
    val homeShare by animateFloatAsState(points.first / total.toFloat(), tween(900, easing = FastOutSlowInEasing), label = "ptsRing")
    val reveal = rememberReveal(total, durationMs = 900)

    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(132.dp), contentAlignment = Alignment.Center) {
            Canvas(Modifier.fillMaxSize()) {
                val stroke = 22f
                val topLeft = Offset(stroke / 2, stroke / 2)
                val arc = Size(size.width - stroke, size.height - stroke)
                val gap = 3f
                val homeSweep = 360f * homeShare * reveal
                val awaySweep = 360f * (1f - homeShare) * reveal
                if (homeSweep > gap) drawArc(state.team1Color, -90f, homeSweep - gap, false, topLeft, arc, style = Stroke(stroke, cap = StrokeCap.Butt))
                if (awaySweep > gap) drawArc(state.team2Color, -90f + 360f * homeShare, awaySweep - gap, false, topLeft, arc, style = Stroke(stroke, cap = StrokeCap.Butt))
            }
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RollingNumber(games.first, state.team1Color, 28)
                    Text("–", fontSize = 20.sp, color = BoardInk.faint, modifier = Modifier.padding(horizontal = 3.dp))
                    RollingNumber(games.second, state.team2Color, 28)
                }
                Text("GAMES", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, letterSpacing = 1.2.sp)
            }
        }
        Spacer(Modifier.width(18.dp))
        Column(Modifier.weight(1f)) {
            SideShare(state.team1, points.first, (points.first * 100f / total).toInt(), state.team1Color)
            Spacer(Modifier.height(14.dp))
            SideShare(state.team2, points.second, (points.second * 100f / total).toInt(), state.team2Color)
            val pointsLeader = if (points.first == points.second) null else points.first > points.second
            val gamesLeader = if (games.first == games.second) null else games.first > games.second
            if (pointsLeader != null && gamesLeader != null && pointsLeader != gamesLeader) {
                Spacer(Modifier.height(12.dp))
                Text(
                    "More points, fewer games", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = theme.deep,
                )
            }
        }
    }
}

@Composable
private fun SideShare(name: String, points: Int, pct: Int, color: androidx.compose.ui.graphics.Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(8.dp).clip(CircleShape).background(color))
        Spacer(Modifier.width(7.dp))
        Text(name, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
    Row(verticalAlignment = Alignment.Bottom) {
        Text(
            "$pct%", fontSize = 22.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.width(6.dp))
        Text("$points pts", fontSize = 11.sp, color = BoardInk.muted, modifier = Modifier.padding(bottom = 4.dp))
    }
}

@Composable
private fun SetLine(s: JSONObject, state: MatchUiState, theme: SportTheme) {
    val winner = s.optString("winner").takeIf { !s.isNull("winner") }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(s.optString("label"), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, modifier = Modifier.width(52.dp))
        Text(
            "${s.optInt("home")}–${s.optInt("away")}", fontSize = 19.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
            style = TextStyle(fontFeatureSettings = "tnum"), modifier = Modifier.width(64.dp),
        )
        Text(
            "${s.optInt("points_home")}–${s.optInt("points_away")} pts",
            fontSize = 11.5.sp, color = BoardInk.muted, modifier = Modifier.weight(1f),
        )
        Text(
            winner?.let { teamShortCode(sideName(state, it)) } ?: "IN PLAY",
            fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, textAlign = TextAlign.End,
            color = winner?.let { sideColor(state, it) } ?: theme.spark,
        )
    }
}
