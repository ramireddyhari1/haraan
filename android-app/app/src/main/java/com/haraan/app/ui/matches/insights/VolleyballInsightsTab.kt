package com.haraan.app.ui.matches.insights

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
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
 * Volleyball's Insights — the set ladder, and the pressure at the end of each set.
 *
 * A volleyball match is five short races, and a set is decided in its last few rallies. So
 * the signature is the SET LADDER: each set as a race bar split by the rallies each side
 * won, with the pressure marked on it — a set that went past 24-all, set points saved. The
 * figures come from the sport's own rules on the server (25 a set, the decider to 15), so a
 * "set point saved" here is one the rulebook would agree was a set point.
 */
@Composable
fun VolleyballInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No rallies yet", "The set ladder, set points saved and player value appear with the first rally.") { d ->
        val sets = d.team.objectList("sets")
        val saved = d.team.sidePair("set_points_saved")
        val deuce = d.team.sidePair("deuce_points")
        val won = d.team.sidePair("points_won")

        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "rally", theme.deep)

            InsightHead("Set ladder", "rallies won")
            sets.forEachIndexed { i, s ->
                Box(Modifier.insightEnter(i)) { SetRung(s, state, theme, live = d.live && i == sets.lastIndex && s.isNull("winner")) }
            }

            InsightHead("Under pressure")
            InsightPanel(Modifier.insightEnter(sets.size)) {
                DuelBar("Rallies won", won.first, won.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("Set points saved", saved.first, saved.second, state.team1Color, state.team2Color)
                Spacer(Modifier.height(14.dp))
                DuelBar("Points won at deuce", deuce.first, deuce.second, state.team1Color, state.team2Color)
            }

            if (d.flow.series.size >= 4) {
                InsightHead("Momentum", "each set starts level")
                InsightPanel {
                    MomentumWorm(d.flow.series, sets.map { it.optString("label") }, state.team1Color, state.team2Color, revealKey = d.moments)
                }
            }
            FlowFigures(flowFigures(d.flow, state, "Rallies in a row", runValue = { "${it.count}" }))

            InsightHead("Player value", "${d.players.size} credited")
            PlayerValueList(d.players, state, theme.deep, "rallies", startIndex = sets.size + 1) { p ->
                val per = p.raw.optJSONArray("per_set")
                val values = (0 until (per?.length() ?: 0)).map { per!!.optInt(it) }
                SegmentBars(values, values.indices.map { "S${it + 1}" }, sideColor(state, p.side))
            }

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

/** One set: who won it, the race bar of rallies, and the pressure it carried. */
@Composable
private fun SetRung(s: JSONObject, state: MatchUiState, theme: SportTheme, live: Boolean) {
    val h = s.optInt("home")
    val a = s.optInt("away")
    val winner = s.optString("winner").takeIf { !s.isNull("winner") }
    val total = (h + a).coerceAtLeast(1)
    val frac by animateFloatAsState(h / total.toFloat(), tween(800, easing = FastOutSlowInEasing), label = "setRung")
    val winColor = winner?.let { sideColor(state, it) } ?: theme.deep

    InsightPanel(
        Modifier.border(if (live) 1.5.dp else 0.dp, if (live) theme.spark else Color.Transparent, RoundedCornerShape(16.dp)),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(s.optString("label"), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
            Spacer(Modifier.width(8.dp))
            when {
                live -> Text("IN PLAY", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = theme.spark, letterSpacing = 1.sp)
                winner != null -> Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(7.dp).clip(CircleShape).background(winColor))
                    Spacer(Modifier.width(5.dp))
                    Text(teamShortCode(sideName(state, winner)), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = winColor)
                }
            }
            Spacer(Modifier.weight(1f))
            Text(
                "$h–$a", fontSize = 20.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(50))) {
            Box(Modifier.weight(frac.coerceIn(0.001f, 0.999f)).fillMaxHeight().background(state.team1Color))
            Box(Modifier.width(2.dp).fillMaxHeight().background(Color.White))
            Box(Modifier.weight((1f - frac).coerceIn(0.001f, 0.999f)).fillMaxHeight().background(state.team2Color))
        }
        val notes = listOfNotNull(
            if (s.optBoolean("deuce")) "Went past ${s.optInt("target") - 1}-all" else null,
            s.optInt("saved_home").takeIf { it > 0 }?.let { "${teamShortCode(state.team1)} saved $it set point${if (it > 1) "s" else ""}" },
            s.optInt("saved_away").takeIf { it > 0 }?.let { "${teamShortCode(state.team2)} saved $it set point${if (it > 1) "s" else ""}" },
            s.optString("closed_by").takeIf { !s.isNull("closed_by") && it.isNotBlank() }?.let { "Closed by $it" },
            maxOf(s.optInt("run_home"), s.optInt("run_away")).takeIf { it >= 4 }?.let { "Best run $it" },
        )
        if (notes.isNotEmpty()) {
            Spacer(Modifier.height(9.dp))
            Text(
                notes.joinToString("  ·  "), fontSize = 11.sp, color = BoardInk.muted,
                maxLines = 2, overflow = TextOverflow.Ellipsis,
            )
        }
    }
}
