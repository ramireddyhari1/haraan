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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.SportBoard
import com.haraan.app.ui.matches.SportTheme
import org.json.JSONObject

/**
 * Kabaddi's Insights — the mat, split into attack and defence.
 *
 * Kabaddi is two contests at once: raiders scoring in the opposition half, defenders
 * tackling in their own. So the signature is THE MAT — both halves side by side, each with
 * its raid points and tackle points standing as columns, and the all-outs inflicted marked
 * under them. Player cards name each player's role from their own split, and carry the
 * sport's real honours: a Super 10 (ten raid points) and a High 5 (five tackle points).
 */
@Composable
fun KabaddiInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No points on the mat yet", "Raid and tackle splits, all-outs and Super 10s appear with the first point.") { d ->
        val split = d.team.optJSONObject("split") ?: JSONObject()
        val home = split.optJSONObject("home") ?: JSONObject()
        val away = split.optJSONObject("away") ?: JSONObject()

        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            InsightsLiveLine(d.live, "raid", theme.deep)

            InsightHead("The mat", "raid · tackle")
            Box(Modifier.insightEnter(0)) { Mat(state, home, away, theme) }

            InsightHead("Head to head")
            Box(Modifier.insightEnter(1)) {
                InsightPanel {
                    DuelBar("Raid points", home.optInt("raid"), away.optInt("raid"), state.team1Color, state.team2Color)
                    Spacer(Modifier.height(14.dp))
                    DuelBar("Tackle points", home.optInt("tackle"), away.optInt("tackle"), state.team1Color, state.team2Color)
                    Spacer(Modifier.height(14.dp))
                    DuelBar("Bonus points", home.optInt("bonus"), away.optInt("bonus"), state.team1Color, state.team2Color)
                    Spacer(Modifier.height(14.dp))
                    DuelBar("All outs inflicted", home.optInt("all_out"), away.optInt("all_out"), state.team1Color, state.team2Color)
                    d.flow.segments.takeIf { it.size > 1 }?.forEach { s ->
                        Spacer(Modifier.height(14.dp))
                        DuelBar(s.label, s.home, s.away, state.team1Color, state.team2Color)
                    }
                }
            }

            if (d.flow.series.size >= 3) {
                InsightHead("Momentum", "${d.flow.totalHome}–${d.flow.totalAway}")
                InsightPanel {
                    MomentumWorm(d.flow.series, d.flow.segments.map { it.label }, state.team1Color, state.team2Color, revealKey = d.moments)
                }
            }
            FlowFigures(flowFigures(d.flow, state, "Unanswered"))

            InsightHead("Raiders & defenders", "${d.players.size} on the sheet")
            PlayerValueList(d.players, state, theme.deep, "points", startIndex = 3) { p ->
                RoleSplit(p.raw, theme)
            }

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

/** Both halves of the mat: each side's raid and tackle points as columns, all-outs beneath. */
@Composable
private fun Mat(state: MatchUiState, home: JSONObject, away: JSONObject, theme: SportTheme) {
    val peak = maxOf(home.optInt("raid"), home.optInt("tackle"), away.optInt("raid"), away.optInt("tackle")).coerceAtLeast(1)
    val shape = RoundedCornerShape(18.dp)
    Row(
        Modifier
            .fillMaxWidth()
            .clip(shape)
            .background(Brush.verticalGradient(listOf(theme.cardTop, theme.cardBottom)))
            .border(1.dp, theme.deep.copy(alpha = 0.18f), shape)
            .padding(vertical = 16.dp),
    ) {
        MatHalf(state.team1, home, peak, state.team1Color, Modifier.weight(1f))
        // The mid-line — the line a raider has to cross.
        Box(Modifier.width(2.dp).height(170.dp).background(Color.White.copy(alpha = 0.85f)))
        MatHalf(state.team2, away, peak, state.team2Color, Modifier.weight(1f))
    }
}

@Composable
private fun MatHalf(team: String, o: JSONObject, peak: Int, color: Color, modifier: Modifier) {
    Column(modifier.padding(horizontal = 12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(team, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Spacer(Modifier.height(10.dp))
        Row(Modifier.height(110.dp), horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.Bottom) {
            MatColumn("RAID", o.optInt("raid"), peak, color)
            MatColumn("TACKLE", o.optInt("tackle"), peak, color.copy(alpha = 0.55f))
        }
        Spacer(Modifier.height(10.dp))
        val allOuts = o.optInt("all_out")
        val supers = o.optInt("super_raids")
        Text(
            listOfNotNull(
                if (allOuts > 0) (if (allOuts == 1) "1 all out" else "$allOuts all outs") else null,
                if (supers > 0) (if (supers == 1) "1 super raid" else "$supers super raids") else null,
            ).joinToString(" · ").ifBlank { "No all outs" },
            fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF334155).copy(alpha = 0.75f),
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun MatColumn(label: String, value: Int, peak: Int, color: Color) {
    val frac by animateFloatAsState(value / peak.toFloat(), tween(850, easing = FastOutSlowInEasing), label = "matCol")
    Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Bottom, modifier = Modifier.fillMaxHeight()) {
        RollingNumber(value, BoardInk.ink, 20)
        Spacer(Modifier.height(4.dp))
        Box(Modifier.width(30.dp).weight(1f, fill = false).fillMaxHeight(), contentAlignment = Alignment.BottomCenter) {
            Box(
                Modifier.fillMaxWidth().fillMaxHeight(frac.coerceIn(0.03f, 1f))
                    .clip(RoundedCornerShape(topStart = 8.dp, topEnd = 8.dp)).background(color),
            )
        }
        Spacer(Modifier.height(4.dp))
        Text(label, fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF334155).copy(alpha = 0.6f), letterSpacing = 1.sp)
    }
}

/** A player's role and how their points split between raiding and tackling. */
@Composable
private fun RoleSplit(raw: JSONObject, theme: SportTheme) {
    val raid = raw.optInt("raid")
    val tackle = raw.optInt("tackle")
    val total = raid + tackle
    if (total == 0) return
    val t = rememberReveal(total, durationMs = 700)
    Spacer(Modifier.height(10.dp))
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            raw.optString("role").uppercase(), fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = theme.deep, letterSpacing = 1.sp,
            modifier = Modifier.clip(RoundedCornerShape(5.dp)).background(theme.deep.copy(alpha = 0.10f)).padding(horizontal = 7.dp, vertical = 3.dp),
        )
        Spacer(Modifier.width(10.dp))
        Row(Modifier.weight(1f).height(6.dp).clip(RoundedCornerShape(50)).background(Color(0xFFF1F5F9))) {
            if (raid > 0) Box(Modifier.weight(raid * t + 0.001f).fillMaxHeight().background(theme.spark))
            if (tackle > 0) Box(Modifier.weight(tackle * t + 0.001f).fillMaxHeight().background(theme.deep))
            if (t < 1f) Spacer(Modifier.weight(total * (1f - t) + 0.001f))
        }
        Spacer(Modifier.width(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(6.dp).clip(CircleShape).background(theme.spark))
            Text(" $raid", fontSize = 10.5.sp, color = BoardInk.muted)
            Spacer(Modifier.width(6.dp))
            Box(Modifier.size(6.dp).clip(CircleShape).background(theme.deep))
            Text(" $tackle", fontSize = 10.5.sp, color = BoardInk.muted)
        }
    }
}
