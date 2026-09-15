package com.haraan.app.ui.matches.insights

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
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
 * Table tennis' Insights — games to eleven, and the ones that went to 10-all.
 *
 * Eleven-point games are over fast, so an ordinary game says little; the match turns on the
 * DEUCE BATTLES — every game that reached deuce and had to be won by two. The signature is a
 * game strip (each game a tile, its winner's colour, deuce games ringed in gold) followed by
 * those battles told one by one: how far past 10-all they went and who saved game points.
 */
@Composable
fun TableTennisInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No points yet", "The game strip, deuce battles and player value appear with the first point.") { d ->
        val games = d.team.objectList("sets")
        val noun = d.team.optString("set_noun", "Game").ifBlank { "Game" }
        val won = d.team.sidePair("points_won")
        val saved = d.team.sidePair("set_points_saved")

        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "point", theme.deep)

            InsightHead("${noun}s", "${games.count { !it.isNull("winner") }} complete")
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).insightEnter(0),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                games.forEachIndexed { i, g -> GameTile(g, i, state, theme) }
            }

            val battles = games.filter { it.optBoolean("deuce") }
            InsightHead("Deuce battles", if (battles.isEmpty()) "none yet" else "${battles.size}")
            if (battles.isEmpty()) {
                InsightPanel(Modifier.insightEnter(1)) {
                    Text("No ${noun.lowercase()} has gone to deuce.", fontSize = 13.sp, color = BoardInk.muted)
                }
            } else {
                InsightPanel(Modifier.insightEnter(1)) {
                    battles.forEachIndexed { i, g ->
                        if (i > 0) {
                            Spacer(Modifier.height(11.dp))
                            Box(Modifier.fillMaxWidth().height(1.dp).background(BoardInk.hairline))
                            Spacer(Modifier.height(11.dp))
                        }
                        DeuceBattle(g, state, noun)
                    }
                }
            }

            InsightPanel(Modifier.insightEnter(2)) {
                DuelBar("Points won", won.first, won.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("${noun} points saved", saved.first, saved.second, state.team1Color, state.team2Color)
            }
            FlowFigures(flowFigures(d.flow, state, "Points in a row", runValue = { "${it.count}" }))

            InsightHead("Player value")
            PlayerValueList(d.players, state, theme.deep, "points", startIndex = 3) { p ->
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
private fun GameTile(g: JSONObject, index: Int, state: MatchUiState, theme: SportTheme) {
    val winner = g.optString("winner").takeIf { !g.isNull("winner") }
    val color = winner?.let { sideColor(state, it) } ?: Color(0xFF94A3B8)
    val deuce = g.optBoolean("deuce")
    val t = rememberReveal(Unit, delayMs = 80 * index, durationMs = 450)
    val shape = RoundedCornerShape(14.dp)
    Column(
        Modifier
            .graphicsLayer { scaleX = 0.85f + 0.15f * t; scaleY = 0.85f + 0.15f * t; alpha = t }
            .width(82.dp)
            .clip(shape)
            .background(if (winner != null) color.copy(alpha = 0.10f) else Color.White)
            .border(if (deuce) 2.dp else 1.dp, if (deuce) Color(0xFFD97706) else Color(0xFFE2E8F0), shape)
            .padding(vertical = 11.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(g.optString("label").uppercase(), fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint, letterSpacing = 0.8.sp)
        Spacer(Modifier.height(4.dp))
        Text(
            "${g.optInt("home")}–${g.optInt("away")}", fontSize = 19.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(4.dp))
        Text(
            when {
                winner != null -> teamShortCode(sideName(state, winner))
                else -> "IN PLAY"
            },
            fontSize = 10.sp, fontWeight = FontWeight.Bold, color = if (winner != null) color else theme.spark,
        )
    }
}

@Composable
private fun DeuceBattle(g: JSONObject, state: MatchUiState, noun: String) {
    val h = g.optInt("home")
    val a = g.optInt("away")
    val winner = g.optString("winner").takeIf { !g.isNull("winner") }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(8.dp).clip(CircleShape).background(winner?.let { sideColor(state, it) } ?: Color(0xFFD97706)))
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                "${g.optString("label")} · $h–$a",
                fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink,
            )
            val lines = listOfNotNull(
                // Past 10-all the game runs two points at a time; the extra points played say how long the fight was.
                (g.optInt("target") - 1).takeIf { it > 0 }?.let { all -> ((h + a) - all * 2).takeIf { it > 0 }?.let { "$it points past $all-all" } },
                g.optInt("saved_home").takeIf { it > 0 }?.let { "${teamShortCode(state.team1)} saved $it ${noun.lowercase()} pt${if (it > 1) "s" else ""}" },
                g.optInt("saved_away").takeIf { it > 0 }?.let { "${teamShortCode(state.team2)} saved $it ${noun.lowercase()} pt${if (it > 1) "s" else ""}" },
                g.optString("closed_by").takeIf { !g.isNull("closed_by") && it.isNotBlank() }?.let { "won by $it" },
            )
            if (lines.isNotEmpty()) Text(lines.joinToString(" · "), fontSize = 11.5.sp, color = BoardInk.muted)
        }
        Text(
            winner?.let { teamShortCode(sideName(state, it)) } ?: "Live",
            fontSize = 12.sp, fontWeight = FontWeight.ExtraBold,
            color = winner?.let { sideColor(state, it) } ?: Color(0xFFD97706),
        )
    }
}
