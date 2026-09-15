package com.haraan.app.ui.matches.insights

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.SportBoard
import com.haraan.app.ui.matches.SportTheme
import com.haraan.app.ui.matches.teamShortCode
import org.json.JSONObject

/**
 * Badminton's Insights — each game's momentum, told game by game.
 *
 * Twenty-one-point games are long enough to swing, so the question after a badminton match
 * is not just who won each game but HOW — led all the way, or dragged back from behind. The
 * signature is one momentum worm per game, side by side in time, with the swing of that game
 * written under it. Past 20-all a game is won by two, until 29-all, where the next point
 * takes it: those are the rules the server applied to every figure here.
 */
@Composable
fun BadmintonInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No rallies yet", "Each game's momentum, game points saved and player value appear with the first rally.") { d ->
        val games = d.team.objectList("sets")
        val won = d.team.sidePair("points_won")
        val saved = d.team.sidePair("set_points_saved")
        val deuce = d.team.sidePair("deuce_points")

        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "rally", theme.deep)

            InsightHead("Game by game")
            games.forEachIndexed { i, g ->
                val series = d.flow.series.filter { it.second == i }
                InsightPanel(Modifier.insightEnter(i)) { GameMomentum(g, series, state, theme, d.moments) }
            }

            InsightHead("The whole match")
            InsightPanel(Modifier.insightEnter(games.size)) {
                DuelBar("Rallies won", won.first, won.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("Game points saved", saved.first, saved.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("Points won at deuce", deuce.first, deuce.second, state.team1Color, state.team2Color)
            }
            FlowFigures(flowFigures(d.flow, state, "Rallies in a row", runValue = { "${it.count}" }))

            InsightHead("Player value")
            PlayerValueList(d.players, state, theme.deep, "rallies", startIndex = games.size + 1) { p ->
                val per = p.raw.optJSONArray("per_set")
                val values = (0 until (per?.length() ?: 0)).map { per!!.optInt(it) }
                SegmentBars(values, values.indices.map { "G${it + 1}" }, sideColor(state, p.side))
            }

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

@Composable
private fun GameMomentum(g: JSONObject, series: List<Pair<Int, Int>>, state: MatchUiState, theme: SportTheme, key: Int) {
    val h = g.optInt("home")
    val a = g.optInt("away")
    val winner = g.optString("winner").takeIf { !g.isNull("winner") }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(g.optString("label"), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
        Spacer(Modifier.width(8.dp))
        if (winner != null) {
            Box(Modifier.size(7.dp).clip(CircleShape).background(sideColor(state, winner)))
            Spacer(Modifier.width(5.dp))
            Text(teamShortCode(sideName(state, winner)), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = sideColor(state, winner))
        } else {
            Text("IN PLAY", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = theme.spark, letterSpacing = 1.sp)
        }
        Spacer(Modifier.weight(1f))
        Text(
            "$h–$a", fontSize = 20.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }
    if (series.size >= 2) {
        Spacer(Modifier.height(10.dp))
        MomentumWorm(series, emptyList(), state.team1Color, state.team2Color, height = 70, revealKey = key)
    }
    // How the game swung: the most either side led it by, read off the same worm.
    val homePeak = series.maxOfOrNull { it.first }?.coerceAtLeast(0) ?: 0
    val awayPeak = series.minOfOrNull { it.first }?.let { -it }?.coerceAtLeast(0) ?: 0
    val lines = listOfNotNull(
        homePeak.takeIf { it >= 2 }?.let { "${teamShortCode(state.team1)} led by up to $it" },
        awayPeak.takeIf { it >= 2 }?.let { "${teamShortCode(state.team2)} led by up to $it" },
        if (g.optBoolean("deuce")) "went past ${g.optInt("target") - 1}-all" else null,
        (g.optInt("saved_home") + g.optInt("saved_away")).takeIf { it > 0 }?.let { "$it game point${if (it > 1) "s" else ""} saved" },
    )
    if (lines.isNotEmpty()) {
        Spacer(Modifier.height(8.dp))
        Text(lines.joinToString(" · "), fontSize = 11.sp, color = BoardInk.muted)
    }
}
