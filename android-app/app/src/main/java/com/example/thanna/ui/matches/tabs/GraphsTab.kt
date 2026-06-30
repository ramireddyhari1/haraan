package com.example.thanna.ui.matches.tabs

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.matches.*

private val MiBlue = Color(0xFF1F6FE5)
private val CskGold = Color(0xFFF5A623)

@Composable
fun GraphsTab(state: MatchUiState, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        contentPadding = PaddingValues(vertical = 16.dp)
    ) {
        item { PhaseComparisonCard(state) }
        item { MatchMomentumCard(state) }
    }
}

@Composable
private fun GraphCard(content: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(18.dp),
        content = content
    )
}

// ── RUNS BY PHASE — grouped bars (PP / Mid / Death), MI vs CSK ──
@Composable
private fun PhaseComparisonCard(state: MatchUiState) {
    val phases = listOf(
        Triple("PP", 52, 58),
        Triple("Mid", 61, 55),
        Triple("Death", 71, 78),
    )
    val maxRuns = (phases.flatMap { listOf(it.second, it.third) }.maxOrNull() ?: 1).coerceAtLeast(1)
    GraphCard {
        Text("RUNS BY PHASE", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Spacer(Modifier.height(18.dp))
        Row(
            modifier = Modifier.fillMaxWidth().height(140.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.Bottom
        ) {
            phases.forEach { (label, mi, csk) ->
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Bottom) {
                    Row(
                        verticalAlignment = Alignment.Bottom,
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier.height(120.dp)
                    ) {
                        PhaseBar(fraction = mi.toFloat() / maxRuns, color = MiBlue)
                        PhaseBar(fraction = csk.toFloat() / maxRuns, color = CskGold)
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(label, color = CrexColors.TextSecondary, fontSize = 12.sp)
                }
            }
        }
        Spacer(Modifier.height(14.dp))
        LegendRow(
            Legend(MiBlue, state.team1),
            Legend(CskGold, state.team2)
        )
    }
}

@Composable
private fun PhaseBar(fraction: Float, color: Color) {
    Box(
        modifier = Modifier
            .width(22.dp)
            .fillMaxHeight(fraction.coerceIn(0.05f, 1f))
            .clip(RoundedCornerShape(topStart = 5.dp, topEnd = 5.dp))
            .background(color)
    )
}

// ── MATCH MOMENTUM — 3-way split + momentum worm ──
@Composable
private fun MatchMomentumCard(state: MatchUiState) {
    GraphCard {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("MATCH MOMENTUM", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                LegendDot(MiBlue, state.team1)
                LegendDot(CrexColors.NormalBall, state.team2)
            }
        }

        Spacer(Modifier.height(18.dp))

        // Three-way percentages
        val team1Pct = WinProbability.estimate(state) ?: 62
        val drawPct = 4
        val team2Pct = (100 - team1Pct - drawPct).coerceAtLeast(0)
        Row(modifier = Modifier.fillMaxWidth()) {
            ThreeWay(state.team1, "$team1Pct%", MiBlue, Alignment.Start, Modifier.weight(1f))
            ThreeWay("DRAW", "$drawPct%", CrexColors.TextMuted, Alignment.CenterHorizontally, Modifier.weight(1f))
            ThreeWay(state.team2, "$team2Pct%", CrexColors.TextSecondary, Alignment.End, Modifier.weight(1f))
        }

        Spacer(Modifier.height(10.dp))

        // Segmented bar
        Row(modifier = Modifier.fillMaxWidth().height(10.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            Box(Modifier.weight(team1Pct.toFloat()).fillMaxHeight().clip(RoundedCornerShape(5.dp)).background(MiBlue))
            Box(Modifier.weight(drawPct.toFloat()).fillMaxHeight().clip(RoundedCornerShape(5.dp)).background(CrexColors.NormalBall))
            Box(Modifier.weight(team2Pct.toFloat()).fillMaxHeight().clip(RoundedCornerShape(5.dp)).background(Color(0xFF64748B)))
        }

        Spacer(Modifier.height(20.dp))

        MomentumWorm()

        Spacer(Modifier.height(14.dp))
        LegendRow(
            Legend(CrexColors.WicketBall, "Wicket"),
            Legend(MiBlue, "Live")
        )
    }
}

@Composable
private fun MomentumWorm() {
    val points = remember {
        listOf(0f, 18f, 30f, 22f, 5f, -22f, -10f, 12f, 30f, 38f, 20f, 2f, -8f, 18f, 40f, 55f)
    }
    val wicketIdx = remember { listOf(5, 9, 13) }
    Canvas(modifier = Modifier.fillMaxWidth().height(150.dp)) {
        val w = size.width
        val h = size.height
        val mid = h / 2f
        val stepX = w / (points.size - 1)
        fun yAt(v: Float) = mid - (v / 70f * (h / 2f - 10.dp.toPx()))

        // baseline
        drawLine(CrexColors.Border, Offset(0f, mid), Offset(w, mid), strokeWidth = 1.dp.toPx())

        // vertical wicket lines (full height)
        wicketIdx.forEach { idx ->
            val x = idx * stepX
            drawLine(
                CrexColors.WicketBall.copy(alpha = 0.55f),
                Offset(x, 0f), Offset(x, h), strokeWidth = 1.5.dp.toPx()
            )
        }

        // momentum line (smooth)
        val path = Path()
        points.forEachIndexed { i, v ->
            val x = i * stepX
            val y = yAt(v)
            if (i == 0) path.moveTo(x, y) else {
                val px = (i - 1) * stepX
                val py = yAt(points[i - 1])
                val cx = px + (x - px) / 2f
                path.cubicTo(cx, py, cx, y, x, y)
            }
        }
        drawPath(path, MiBlue, style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round))

        // wicket markers (hollow red circles on the worm)
        wicketIdx.forEach { idx ->
            val x = idx * stepX
            val y = yAt(points[idx])
            drawCircle(Color.White, radius = 5.dp.toPx(), center = Offset(x, y))
            drawCircle(CrexColors.WicketBall, radius = 5.dp.toPx(), center = Offset(x, y), style = Stroke(width = 2.dp.toPx()))
        }

        // live marker at the latest point (filled blue + halo)
        val lastX = (points.size - 1) * stepX
        val lastY = yAt(points.last())
        drawCircle(MiBlue.copy(alpha = 0.18f), radius = 11.dp.toPx(), center = Offset(lastX, lastY))
        drawCircle(MiBlue, radius = 5.dp.toPx(), center = Offset(lastX, lastY))
        drawCircle(Color.White, radius = 2.dp.toPx(), center = Offset(lastX, lastY))
    }
}

// ── small reusable bits ──
private data class Legend(val color: Color, val label: String)

@Composable
private fun LegendRow(vararg items: Legend) {
    Row(horizontalArrangement = Arrangement.spacedBy(18.dp)) {
        items.forEach { LegendDot(it.color, it.label) }
    }
}

@Composable
private fun LegendDot(color: Color, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        Box(Modifier.size(8.dp).clip(CircleShape).background(color))
        Text(label, color = CrexColors.TextSecondary, fontSize = 12.sp)
    }
}

@Composable
private fun ThreeWay(label: String, value: String, valueColor: Color, align: Alignment.Horizontal, modifier: Modifier) {
    Column(modifier = modifier, horizontalAlignment = align) {
        Text(label, color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(2.dp))
        Text(value, color = valueColor, fontSize = 22.sp, fontWeight = FontWeight.Black)
    }
}
