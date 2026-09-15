package com.haraan.app.ui.matches.insights

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.SportBoard
import com.haraan.app.ui.matches.SportTheme
import com.haraan.app.ui.matches.teamShortCode
import org.json.JSONObject

/**
 * Basketball's Insights — where the game was won, and what the points were made of.
 *
 * Its signature is the QUARTER MARGIN: a diverging bar per quarter, so a 30-12 third quarter
 * visibly towers over three even ones. Then the shot mix — threes, twos and free throws as
 * shares of each side's points — because a team living off the arc and a team living at the
 * line are different teams at the same score. Every bucket carries its recorded value.
 */
@Composable
fun BasketballInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No buckets yet", "Quarter margins, the shot mix and player value appear with the first basket.") { d ->
        val team = d.team
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "basket", theme.deep)

            val quarters = team.objectList("quarters")
            if (quarters.isNotEmpty()) {
                InsightHead("Quarter by quarter", team.optJSONObject("best_quarter")?.optString("label")?.let { "Best: $it" })
                Box(Modifier.insightEnter(0)) { QuarterMargins(quarters, state) }
            }

            val mix = team.optJSONObject("shot_mix")
            if (mix != null) {
                InsightHead("Shot mix", "share of points")
                Box(Modifier.insightEnter(1)) {
                    InsightPanel {
                        ShotMixRow(state.team1, mix.optJSONObject("home"), state.team1Color)
                        Spacer(Modifier.height(16.dp))
                        ShotMixRow(state.team2, mix.optJSONObject("away"), state.team2Color)
                        Spacer(Modifier.height(12.dp))
                        ShotLegend()
                    }
                }
            }

            if (d.flow.series.size >= 3) {
                InsightHead("Momentum", "${d.flow.totalHome}–${d.flow.totalAway}")
                Box(Modifier.insightEnter(2)) {
                    InsightPanel {
                        MomentumWorm(d.flow.series, d.flow.segments.map { it.label }, state.team1Color, state.team2Color, revealKey = d.moments)
                    }
                }
            }
            FlowFigures(flowFigures(d.flow, state, "Biggest run"))

            InsightHead("Player value", "${d.players.size} scored")
            PlayerValueList(d.players, state, theme.deep, "points", startIndex = 3) { p ->
                PlayerShotSplit(p.raw.optJSONObject("mix"), sideColor(state, p.side))
                val q = p.raw.optJSONArray("quarters")
                val values = (0 until (q?.length() ?: 0)).map { q!!.optInt(it) }
                SegmentBars(values, values.indices.map { "Q${it + 1}" }, sideColor(state, p.side))
            }

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

/** One row per quarter: the margin grows from the centre toward whoever won it. */
@Composable
private fun QuarterMargins(quarters: List<JSONObject>, state: MatchUiState) {
    val peak = quarters.maxOf { kotlin.math.abs(it.optInt("home") - it.optInt("away")) }.coerceAtLeast(1)
    InsightPanel {
        quarters.forEachIndexed { i, q ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            val h = q.optInt("home")
            val a = q.optInt("away")
            val margin = h - a
            val frac by animateFloatAsState(
                kotlin.math.abs(margin) / peak.toFloat(),
                tween(700, delayMillis = 80 * i, easing = FastOutSlowInEasing), label = "qMargin",
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(q.optString("label"), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = BoardInk.muted, modifier = Modifier.width(30.dp))
                Text("$h", fontSize = 13.sp, fontWeight = if (h >= a) FontWeight.ExtraBold else FontWeight.Medium,
                    color = BoardInk.ink, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
                Spacer(Modifier.width(8.dp))
                Row(Modifier.weight(1f).height(14.dp)) {
                    Box(Modifier.weight(1f).fillMaxHeight(), contentAlignment = Alignment.CenterEnd) {
                        if (margin > 0) Box(Modifier.fillMaxWidth(frac).fillMaxHeight()
                            .clip(RoundedCornerShape(topStart = 7.dp, bottomStart = 7.dp)).background(state.team1Color))
                    }
                    Box(Modifier.width(2.dp).fillMaxHeight().background(Color(0xFFCBD5E1)))
                    Box(Modifier.weight(1f).fillMaxHeight(), contentAlignment = Alignment.CenterStart) {
                        if (margin < 0) Box(Modifier.fillMaxWidth(frac).fillMaxHeight()
                            .clip(RoundedCornerShape(topEnd = 7.dp, bottomEnd = 7.dp)).background(state.team2Color))
                    }
                }
                Spacer(Modifier.width(8.dp))
                Text("$a", fontSize = 13.sp, fontWeight = if (a >= h) FontWeight.ExtraBold else FontWeight.Medium,
                    color = BoardInk.ink, modifier = Modifier.width(28.dp))
                Text(
                    when {
                        margin > 0 -> "+$margin ${teamShortCode(state.team1)}"
                        margin < 0 -> "+${-margin} ${teamShortCode(state.team2)}"
                        else -> "Even"
                    },
                    fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.faint,
                    textAlign = TextAlign.End, modifier = Modifier.width(58.dp),
                )
            }
        }
    }
}

private val ThreeTone = Color(0xFFF97316)
private val TwoTone = Color(0xFF9A3412)
private val FtTone = Color(0xFFFDBA74)

@Composable
private fun ShotMixRow(team: String, o: JSONObject?, color: Color) {
    val threes = o?.optInt("points_threes") ?: 0
    val twos = o?.optInt("points_twos") ?: 0
    val fts = o?.optInt("points_free_throws") ?: 0
    val total = (threes + twos + fts)
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(8.dp).clip(CircleShape).background(color))
        Spacer(Modifier.width(8.dp))
        Text(team, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, modifier = Modifier.weight(1f))
        Text(
            "${o?.optInt("threes") ?: 0} threes · ${o?.optInt("twos") ?: 0} twos · ${o?.optInt("free_throws") ?: 0} FT",
            fontSize = 11.sp, color = BoardInk.muted,
        )
    }
    Spacer(Modifier.height(8.dp))
    StackedBar(listOf(threes to ThreeTone, twos to TwoTone, fts to FtTone), total, 12)
}

@Composable
private fun StackedBar(parts: List<Pair<Int, Color>>, total: Int, height: Int) {
    val t = rememberReveal(parts.map { it.first }, durationMs = 800)
    Row(
        Modifier.fillMaxWidth().height(height.dp).clip(RoundedCornerShape(50)).background(Color(0xFFF1F5F9)),
    ) {
        if (total > 0) {
            parts.filter { it.first > 0 }.forEach { (v, c) ->
                Box(Modifier.weight((v / total.toFloat()) * t + 0.0001f).fillMaxHeight().background(c))
            }
            if (t < 1f) Spacer(Modifier.weight(1f - t + 0.0001f))
        }
    }
}

@Composable
private fun ShotLegend() {
    Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
        listOf("Threes" to ThreeTone, "Twos" to TwoTone, "Free throws" to FtTone).forEach { (l, c) ->
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(8.dp).clip(RoundedCornerShape(2.dp)).background(c))
                Spacer(Modifier.width(5.dp))
                Text(l, fontSize = 10.5.sp, color = BoardInk.muted)
            }
        }
    }
}

/** A player's points as threes / twos / free throws, on one thin bar. */
@Composable
private fun PlayerShotSplit(mix: JSONObject?, color: Color) {
    if (mix == null) return
    val three = mix.optInt("three")
    val two = mix.optInt("two")
    val ft = mix.optInt("free_throw")
    val total = three + two + ft
    if (total == 0) return
    Spacer(Modifier.height(10.dp))
    StackedBar(listOf(three to ThreeTone, two to TwoTone, ft to FtTone), total, 6)
    Spacer(Modifier.height(4.dp))
    Text(
        listOfNotNull(
            if (three > 0) "${three * 100 / total}% from three" else null,
            if (ft > 0) "${ft * 100 / total}% at the line" else null,
        ).joinToString(" · ").ifBlank { "All inside the arc" },
        fontSize = 10.5.sp, color = BoardInk.faint,
    )
}
