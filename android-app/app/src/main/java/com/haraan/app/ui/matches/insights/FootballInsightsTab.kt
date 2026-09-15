package com.haraan.app.ui.matches.insights

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.FootballState
import com.haraan.app.ui.matches.MatchUiState
import org.json.JSONObject

private val FootballAccent = Color(0xFF2563EB)

/**
 * Football's Insights — the goal story.
 *
 * Football is low-scoring, so its story is the ORDER of goals, not the count: who opened it,
 * who equalised, who scored the winner. The signature is the match as a 90-minute line with
 * every goal pinned at its minute — home above, away below — then each goal named for what it
 * did to the scoreline. Attacking figures follow only when the scorer tracked them; a team
 * with no recorded shots is shown as untracked, never as "0 shots".
 */
@Composable
fun FootballInsightsTab(matchId: String, state: MatchUiState, football: FootballState) {
    val load = rememberSportInsights(matchId, liveKey = football to state.score)

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 14.dp),
    ) {
        item {
            InsightsGate(load, "No goals yet", "The goal story, attacking figures and player value appear as the match is recorded.") { d ->
                val story = d.team.objectList("story")
                val attack = d.team.optJSONObject("attack")
                val tracked = d.team.optJSONObject("stats")?.optBoolean("has_any") == true

                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    InsightsLiveLine(d.live, "goal", FootballAccent)

                    InsightHead("Goal story", if (story.isEmpty()) "goalless" else "${d.flow.totalHome}–${d.flow.totalAway}")
                    if (story.isEmpty()) {
                        InsightPanel(Modifier.insightEnter(0)) {
                            Text("No goals yet.", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
                        }
                    } else {
                        InsightPanel(Modifier.insightEnter(0)) {
                            if (story.any { !it.isNull("minute") }) {
                                GoalLine(story, state)
                                Spacer(Modifier.height(14.dp))
                            }
                            story.forEachIndexed { i, g ->
                                if (i > 0) Spacer(Modifier.height(10.dp))
                                GoalRow(g, state)
                            }
                        }
                    }

                    if (story.size >= 2) {
                        FlowFigures(flowFigures(d.flow, state, "Unanswered goals", runValue = { "${it.count}" }))
                    }

                    if (attack != null) {
                        InsightHead("Attack", if (tracked) null else "not tracked")
                        InsightPanel(Modifier.insightEnter(1)) {
                            val h = attack.optJSONObject("home") ?: JSONObject()
                            val a = attack.optJSONObject("away") ?: JSONObject()
                            DuelBar("Goals", h.optInt("goals"), a.optInt("goals"), state.team1Color, state.team2Color)
                            if (tracked) {
                                listOf("shots" to "Shots", "on_target" to "On target", "corners" to "Corners", "saves" to "Saves")
                                    .filter { (k, _) -> h.optInt(k) + a.optInt(k) > 0 }
                                    .forEach { (k, label) ->
                                        Spacer(Modifier.height(14.dp))
                                        DuelBar(label, h.optInt(k), a.optInt(k), state.team1Color, state.team2Color)
                                    }
                                if (!h.isNull("conversion") && !a.isNull("conversion")) {
                                    Spacer(Modifier.height(14.dp))
                                    DuelBar("Shots converted", h.optInt("conversion"), a.optInt("conversion"),
                                        state.team1Color, state.team2Color, format = { "$it%" })
                                }
                            } else {
                                Spacer(Modifier.height(10.dp))
                                Text(
                                    "Shots, corners and saves appear when the scorer tracks them.",
                                    fontSize = 11.5.sp, color = BoardInk.faint,
                                )
                            }
                        }
                    }

                    InsightHead("Player value", "goals · assists · cards")
                    PlayerValueList(d.players, state, FootballAccent, "goals", startIndex = 2)

                    InsightReads(d.reads, FootballAccent)
                    UntrackedNote(d.untracked)
                }
            }
        }
    }
}

/** The match as a line from kick-off, every goal pinned at its minute — home above, away below. */
@Composable
private fun GoalLine(story: List<JSONObject>, state: MatchUiState) {
    val last = story.mapNotNull { if (it.isNull("minute")) null else it.optInt("minute") }.maxOrNull() ?: 90
    val end = maxOf(90, last)
    val t = rememberReveal(story.size, durationMs = 900)

    Column {
        Canvas(Modifier.fillMaxWidth().height(64.dp)) {
            val mid = size.height / 2f
            drawLine(Color(0xFFCBD5E1), Offset(0f, mid), Offset(size.width, mid), 3f)
            // Half-time.
            val ht = size.width * (45f / end)
            drawLine(
                Color(0xFF94A3B8), Offset(ht, mid - 14f), Offset(ht, mid + 14f), 2f,
                pathEffect = PathEffect.dashPathEffect(floatArrayOf(4f, 4f)),
            )
            story.forEachIndexed { i, g ->
                if (g.isNull("minute")) return@forEachIndexed
                // Each pin drops in turn, in the order the goals went in.
                val local = ((t * story.size) - i).coerceIn(0f, 1f)
                if (local <= 0f) return@forEachIndexed
                val x = size.width * (g.optInt("minute").coerceAtMost(end) / end.toFloat())
                val home = g.optString("side") == "home"
                val color = if (home) state.team1Color else state.team2Color
                val reach = (mid - 10f) * local
                val y = if (home) mid - reach else mid + reach
                drawLine(color.copy(alpha = 0.5f), Offset(x, mid), Offset(x, y), 3f)
                val big = g.optString("moment") == "winner"
                drawCircle(color, if (big) 11f else 8f, Offset(x, y))
                if (big) drawCircle(Color.White, 4f, Offset(x, y))
            }
        }
        Row(Modifier.fillMaxWidth()) {
            Text("0'", fontSize = 9.5.sp, color = BoardInk.faint, modifier = Modifier.weight(45f))
            Text("HT", fontSize = 9.5.sp, color = BoardInk.faint, modifier = Modifier.weight((end - 45).toFloat()))
            Text("$end'", fontSize = 9.5.sp, color = BoardInk.faint)
        }
    }
}

@Composable
private fun GoalRow(g: JSONObject, state: MatchUiState) {
    val home = g.optString("side") == "home"
    val color = if (home) state.team1Color else state.team2Color
    val own = g.optBoolean("own_goal")
    val (label, tone) = when {
        own -> "Own goal" to Color(0xFFDC2626)
        else -> when (g.optString("moment")) {
            "opener" -> "Opener" to FootballAccent
            "equaliser" -> "Equaliser" to FootballAccent
            "go_ahead" -> "Go-ahead" to FootballAccent
            "winner" -> "Winner" to Color(0xFFB45309)
            else -> "Goal" to BoardInk.muted
        }
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            if (g.isNull("minute")) "—" else "${g.optInt("minute")}'",
            fontSize = 12.sp, fontWeight = FontWeight.Bold, color = BoardInk.muted, modifier = Modifier.width(36.dp),
        )
        Box(Modifier.size(8.dp).clip(CircleShape).background(color))
        Spacer(Modifier.width(9.dp))
        Column(Modifier.weight(1f)) {
            Text(
                g.optString("player").ifBlank { if (home) state.team1 else state.team2 },
                fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            val assist = g.optString("assist")
            if (assist.isNotBlank()) {
                Text("Assist: $assist", fontSize = 11.sp, color = BoardInk.faint, maxLines = 1)
            }
        }
        Text(
            label, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = tone,
            modifier = Modifier.clip(RoundedCornerShape(50)).background(tone.copy(alpha = 0.10f)).padding(horizontal = 8.dp, vertical = 3.dp),
        )
        Spacer(Modifier.width(10.dp))
        Text(
            "${g.optInt("home")}–${g.optInt("away")}",
            fontSize = 14.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
            textAlign = TextAlign.End, style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}
