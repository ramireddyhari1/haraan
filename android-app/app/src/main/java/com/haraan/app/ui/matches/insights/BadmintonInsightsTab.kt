@file:OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)

package com.haraan.app.ui.matches.insights

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
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
 * Badminton's Insights.
 *
 * The tab is built around the one question the sport is actually argued about after a match:
 * not who won the rallies, but WHERE they were won. So it reads in four movements, each with
 * its own object rather than another box of numbers:
 *
 *  1. MOMENTUM, full width and full match — one worm carrying every rally, banded by game,
 *     with the 11-point interval marked where it fell and the peak lead called out on the
 *     line itself. It is the hero because it is the only figure that shows shape.
 *  2. THE SERVE, as two rails. Badminton's serve follows the rally winner, so the server of
 *     every rally after the first is a fact, not a guess — the rails are how often each side
 *     held it and how often they broke it.
 *  3. STREAKS AND PRESSURE — the match redrawn as the runs it was made of, then the rallies
 *     that decided games: game points needed, saved, and the half played after the interval.
 *  4. PLAYER IMPACT — the shared card, with what it actually rests on underneath: points by
 *     game, rallies won on serve, and a DECISIVE count whose definition is printed on screen.
 *
 * Every figure is a count the server made by replaying the recorded rallies. Nothing is
 * rated, weighted or estimated, and what the scorer never records is named at the bottom.
 */
@Composable
fun BadmintonInsightsTab(matchId: String, state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val load = rememberSportInsights(matchId, liveKey = board.feed.firstOrNull()?.sequence to state.score)

    InsightsGate(load, "No rallies yet", "Momentum, the serve and the rallies that decide games appear with the first rally.") { d ->
        val games = d.team.objectList("sets")
        val serve = d.team.optJSONObject("serve") ?: JSONObject()
        val pressure = d.team.optJSONObject("pressure") ?: JSONObject()

        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            InsightsLiveLine(d.live, "rally", theme.deep)

            MomentumHero(d, games, state, theme)

            InsightHead("Game by game", games.count { !it.isNull("winner") }.takeIf { it > 0 }?.let { "$it complete" })
            GameLadder(games, state, theme)

            InsightHead("The serve")
            ServeRails(serve, state, theme)

            InsightHead("Streaks")
            RunSpine(pressure, state, theme)

            InsightHead("Pressure")
            PressurePanel(d, pressure, games, state)

            InsightHead("Player impact")
            ImpactNote(pressure.optInt("interval", 11))
            PlayerValueList(d.players, state, theme.deep, "rallies", startIndex = 4) { p ->
                val per = p.raw.optJSONArray("per_set")
                val values = (0 until (per?.length() ?: 0)).map { per!!.optInt(it) }
                SegmentBars(values, values.indices.map { "G${it + 1}" }, sideColor(state, p.side))
            }

            InsightReads(d.reads, theme.deep)
            UntrackedNote(d.untracked)
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1 · Momentum — the hero
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The whole match on one line: the margin inside the current game after every rally, home
 * above the axis and away below, banded and divided by game. Because each game restarts at
 * level, the line returns to the axis at every divider — which is exactly what happened.
 */
@Composable
private fun MomentumHero(d: SportInsights, games: List<JSONObject>, state: MatchUiState, theme: SportTheme) {
    val series = d.flow.series
    val shape = RoundedCornerShape(20.dp)
    Column(
        Modifier
            .fillMaxWidth()
            .insightEnter(0)
            .clip(shape)
            .background(Brush.verticalGradient(listOf(theme.cardTop.copy(alpha = 0.55f), Color.White)))
            .border(1.dp, theme.deep.copy(alpha = 0.16f), shape)
            .padding(horizontal = 16.dp, vertical = 16.dp),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text("MOMENTUM", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = theme.deep, letterSpacing = 1.3.sp)
                Spacer(Modifier.height(3.dp))
                Text(
                    "The lead in each game, rally by rally",
                    fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink,
                )
            }
            val won = games.count { it.optString("winner") == "home" } to games.count { it.optString("winner") == "away" }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "${won.first}", fontSize = 26.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    color = if (won.first >= won.second) state.team1Color else BoardInk.faint,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text("/", fontSize = 16.sp, color = BoardInk.faint, modifier = Modifier.padding(horizontal = 3.dp))
                Text(
                    "${won.second}", fontSize = 26.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    color = if (won.second >= won.first) state.team2Color else BoardInk.faint,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }

        Spacer(Modifier.height(4.dp))
        SideKeyRow(state)

        if (series.size >= 2) {
            Spacer(Modifier.height(10.dp))
            GameBandedWorm(series, games, state, theme)
        } else {
            Spacer(Modifier.height(14.dp))
            Text(
                "The line draws itself from the second rally on.",
                fontSize = 12.sp, color = BoardInk.muted,
            )
        }

        val figures = flowFigures(d.flow, state, "Rallies in a row", runValue = { "${it.count}" })
        if (figures.isNotEmpty()) {
            Spacer(Modifier.height(14.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(theme.deep.copy(alpha = 0.12f)))
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                figures.forEachIndexed { i, (value, label, sub) ->
                    if (i > 0) Box(Modifier.width(1.dp).fillMaxHeight().background(theme.deep.copy(alpha = 0.12f)))
                    Column(Modifier.weight(1f).padding(horizontal = 6.dp)) {
                        Text(
                            value, fontSize = if (value.length > 4) 18.sp else 23.sp,
                            fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
                            maxLines = 1, style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                        Text(label, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.muted, maxLines = 1)
                        if (sub != null) {
                            Text(sub, fontSize = 9.5.sp, color = BoardInk.faint, maxLines = 2, lineHeight = 12.sp)
                        }
                    }
                }
            }
        }
    }
}

/** Which colour is which side — said once, at the top, instead of in every caption. */
@Composable
private fun SideKeyRow(state: MatchUiState) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        listOf(state.team1 to state.team1Color, state.team2 to state.team2Color).forEachIndexed { i, (name, color) ->
            if (i > 0) Spacer(Modifier.width(14.dp))
            Box(Modifier.size(7.dp).clip(CircleShape).background(color))
            Spacer(Modifier.width(6.dp))
            Text(
                teamShortCode(name), fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
                color = BoardInk.muted, letterSpacing = 0.6.sp,
            )
        }
    }
}

/**
 * The momentum worm, badminton's way: game bands behind the line, a hairline at each game
 * boundary, a tick where the 11-point interval fell in a game that reached it, and a filled
 * dot on the peak lead. Drawn as one canvas so the whole match reads as one gesture.
 */
@Composable
private fun GameBandedWorm(
    series: List<Pair<Int, Int>>,
    games: List<JSONObject>,
    state: MatchUiState,
    theme: SportTheme,
) {
    val t = rememberReveal(series.size, durationMs = 1200)
    val peak = series.maxOf { kotlin.math.abs(it.first) }.coerceAtLeast(1)

    // Each game's stretch of the line, as an index range into the series.
    val spans = games.indices.mapNotNull { s ->
        val idx = series.indices.filter { series[it].second == s }
        if (idx.isEmpty()) null else s to (idx.first()..idx.last())
    }

    /*
     * Where the 11-point interval fell, exactly.
     *
     * The line carries margins, not scores, so the interval cannot be read off it. It does not
     * have to be: the server counts the rallies played AFTER the interval in each game, and a
     * game's rallies are its final score added up. The interval is therefore the rally that
     * many places from the end of the game — an exact index, not an estimate.
     */
    val intervalAt = spans.mapNotNull { (s, range) ->
        val g = games[s]
        if (!g.optBoolean("reached_interval")) return@mapNotNull null
        val after = g.optInt("interval_home") + g.optInt("interval_away")
        val at = range.last - after + 1
        if (at in range) at else null
    }
    val peakIndex = series.indices.maxByOrNull { kotlin.math.abs(series[it].first) } ?: 0

    Column {
        Canvas(Modifier.fillMaxWidth().height(168.dp)) {
            val w = size.width
            val mid = size.height / 2f
            val stepX = w / (series.size - 1).coerceAtLeast(1)
            val amp = mid * 0.86f

            // Game bands — every other game tinted, so the eye counts games without a legend,
            // with a hairline on each boundary where the score went back to level.
            spans.forEachIndexed { n, (_, range) ->
                val from = range.first * stepX
                val to = range.last * stepX
                if (n % 2 == 1) {
                    drawRect(theme.deep.copy(alpha = 0.04f), Offset(from, 0f), Size(to - from, size.height))
                }
                if (n > 0) {
                    drawLine(theme.deep.copy(alpha = 0.22f), Offset(from, 0f), Offset(from, size.height), 2f)
                }
            }

            // The axis: level. Everything above it is one side ahead, below it the other.
            drawLine(Color(0xFFCBD5E1), Offset(0f, mid), Offset(w, mid), 2f)

            // The interval, where a game reached it.
            intervalAt.forEach { i ->
                val x = i * stepX
                var y = 0f
                while (y < size.height) {
                    drawLine(BoardInk.faint.copy(alpha = 0.45f), Offset(x, y), Offset(x, y + 5f), 1.5f)
                    y += 11f
                }
            }

            val shown = (series.size * t).toInt().coerceIn(1, series.size)
            val path = Path()
            val fillHome = Path().apply { moveTo(0f, mid) }
            val fillAway = Path().apply { moveTo(0f, mid) }
            for (i in 0 until shown) {
                val x = i * stepX
                val y = mid - (series[i].first / peak.toFloat()) * amp
                if (i == 0) path.moveTo(x, y) else path.lineTo(x, y)
                fillHome.lineTo(x, minOf(y, mid))
                fillAway.lineTo(x, maxOf(y, mid))
            }
            val endX = (shown - 1) * stepX
            fillHome.lineTo(endX, mid); fillHome.close()
            fillAway.lineTo(endX, mid); fillAway.close()
            drawPath(fillHome, Brush.verticalGradient(listOf(state.team1Color.copy(alpha = 0.34f), state.team1Color.copy(alpha = 0.03f)), 0f, mid))
            drawPath(fillAway, Brush.verticalGradient(listOf(state.team2Color.copy(alpha = 0.03f), state.team2Color.copy(alpha = 0.34f)), mid, size.height))
            drawPath(path, Color(0xFF1E293B), style = Stroke(width = 3.5f, cap = StrokeCap.Round))

            // The peak lead, once the line has drawn past it.
            if (peakIndex < shown && series[peakIndex].first != 0) {
                val px = peakIndex * stepX
                val py = mid - (series[peakIndex].first / peak.toFloat()) * amp
                val c = if (series[peakIndex].first > 0) state.team1Color else state.team2Color
                drawCircle(Color.White, 9f, Offset(px, py))
                drawCircle(c, 6f, Offset(px, py))
            }

            // The head of the line — where the match stands right now.
            val last = series[shown - 1].first
            val ly = mid - (last / peak.toFloat()) * amp
            drawCircle((if (last >= 0) state.team1Color else state.team2Color).copy(alpha = 0.22f), 13f, Offset(endX, ly))
            drawCircle(if (last >= 0) state.team1Color else state.team2Color, 6.5f, Offset(endX, ly))
        }

        // Game labels, each under its own share of the axis.
        if (games.size > 1) {
            Spacer(Modifier.height(7.dp))
            Row(Modifier.fillMaxWidth()) {
                val counts = games.indices.map { s -> series.count { it.second == s } }
                val total = counts.sum().coerceAtLeast(1)
                games.forEachIndexed { i, g ->
                    val weight = counts.getOrElse(i) { 0 }
                    if (weight > 0) {
                        Text(
                            "G${i + 1}  ${g.optInt("home")}–${g.optInt("away")}",
                            fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint,
                            textAlign = TextAlign.Center, maxLines = 1, overflow = TextOverflow.Clip,
                            modifier = Modifier.weight(weight / total.toFloat()),
                        )
                    }
                }
            }
        }
        if (intervalAt.isNotEmpty()) {
            Spacer(Modifier.height(6.dp))
            Text(
                "Dotted line · the 11-point interval",
                fontSize = 10.sp, color = BoardInk.faint,
            )
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2 · Game by game
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The games as a ladder rather than a panel each: score, who took it, the rally split as one
 * thin bar, and the evidence of how it swung on a single muted line.
 */
@Composable
private fun GameLadder(games: List<JSONObject>, state: MatchUiState, theme: SportTheme) {
    if (games.isEmpty()) return
    InsightPanel(Modifier.insightEnter(1)) {
        games.forEachIndexed { i, g ->
            if (i > 0) {
                Spacer(Modifier.height(13.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(BoardInk.hairline))
                Spacer(Modifier.height(13.dp))
            }
            GameRow(g, state, theme)
        }
    }
}

@Composable
private fun GameRow(g: JSONObject, state: MatchUiState, theme: SportTheme) {
    val h = g.optInt("home")
    val a = g.optInt("away")
    val winner = g.optString("winner").takeIf { !g.isNull("winner") }

    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            g.optString("label").uppercase(), fontSize = 10.sp, fontWeight = FontWeight.Bold,
            color = BoardInk.faint, letterSpacing = 1.sp,
        )
        Spacer(Modifier.width(9.dp))
        if (winner != null) {
            Text(
                teamShortCode(sideName(state, winner)),
                fontSize = 10.sp, fontWeight = FontWeight.Bold, color = sideColor(state, winner),
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(sideColor(state, winner).copy(alpha = 0.12f))
                    .padding(horizontal = 8.dp, vertical = 3.dp),
            )
        } else {
            Text("IN PLAY", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = theme.spark, letterSpacing = 1.sp)
        }
        Spacer(Modifier.weight(1f))
        Text(
            "$h", fontSize = 21.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay,
            color = if (h >= a) BoardInk.ink else BoardInk.faint, style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Text("–", fontSize = 14.sp, color = BoardInk.faint, modifier = Modifier.padding(horizontal = 3.dp))
        Text(
            "$a", fontSize = 21.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay,
            color = if (a >= h) BoardInk.ink else BoardInk.faint, style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }

    // The rally split, as one bar rather than two numbers.
    Spacer(Modifier.height(9.dp))
    SplitBar(h, a, state.team1Color, state.team2Color)

    val target = g.optInt("target", 21)
    val lines = listOfNotNull(
        g.optInt("run_home").takeIf { it >= 3 }?.let { "${teamShortCode(state.team1)} won $it in a row" },
        g.optInt("run_away").takeIf { it >= 3 }?.let { "${teamShortCode(state.team2)} won $it in a row" },
        if (g.optBoolean("deuce")) "past ${target - 1}-all" else null,
        (g.optInt("saved_home") + g.optInt("saved_away")).takeIf { it > 0 }
            ?.let { "$it game point${if (it > 1) "s" else ""} saved" },
        if (g.optBoolean("reached_interval")) {
            "${g.optInt("interval_home")}–${g.optInt("interval_away")} after the interval"
        } else {
            null
        },
    )
    if (lines.isNotEmpty()) {
        Spacer(Modifier.height(8.dp))
        Text(lines.joinToString(" · "), fontSize = 11.sp, color = BoardInk.muted, lineHeight = 15.sp)
    }
}

/** Two counts as one bar — the share each side took, with no tick marks or axis. */
@Composable
private fun SplitBar(home: Int, away: Int, homeColor: Color, awayColor: Color) {
    val total = (home + away).coerceAtLeast(1)
    val t = rememberReveal(home to away, durationMs = 720)
    Canvas(Modifier.fillMaxWidth().height(7.dp)) {
        val r = CornerRadius(size.height / 2f, size.height / 2f)
        drawRoundRect(Color(0xFFF1F5F9), cornerRadius = r)
        val hw = size.width * (home / total.toFloat()) * t
        if (hw > 0f) {
            drawRoundRect(homeColor, size = Size(hw.coerceAtLeast(size.height), size.height), cornerRadius = r)
        }
        val aw = size.width * (away / total.toFloat()) * t
        if (aw > 0f) {
            drawRoundRect(
                awayColor,
                topLeft = Offset(size.width - aw.coerceAtLeast(size.height), 0f),
                size = Size(aw.coerceAtLeast(size.height), size.height),
                cornerRadius = r,
            )
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3 · The serve
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Two rails, one per side. The rail's LENGTH is how many rallies that side served — a side
 * that keeps winning keeps serving — and the FILLED part is how many of them they won. The
 * gap at the end of a rail is where the other side broke.
 */
@Composable
private fun ServeRails(serve: JSONObject, state: MatchUiState, theme: SportTheme) {
    if (!serve.optBoolean("known")) {
        InsightPanel(Modifier.insightEnter(2)) {
            Text("The serve hasn't changed hands yet", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
            Spacer(Modifier.height(3.dp))
            Text(
                "In badminton the rally winner serves next, so this fills in from the second rally on.",
                fontSize = 12.sp, color = BoardInk.muted, lineHeight = 16.sp,
            )
        }
        return
    }
    val home = serve.optJSONObject("home") ?: JSONObject()
    val away = serve.optJSONObject("away") ?: JSONObject()
    val widest = maxOf(home.optInt("played"), away.optInt("played")).coerceAtLeast(1)

    InsightPanel(Modifier.insightEnter(2)) {
        Text("Rallies won while serving", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
        Spacer(Modifier.height(3.dp))
        Text(
            if (serve.optBoolean("recorded")) {
                "Taken from the recorded serve order and the rally winner after it."
            } else {
                "The rally winner serves next, so this is read off the rally order itself."
            },
            fontSize = 11.5.sp, color = BoardInk.faint, lineHeight = 15.sp,
        )
        Spacer(Modifier.height(14.dp))

        listOf("home" to home, "away" to away).forEachIndexed { i, (side, s) ->
            if (i > 0) Spacer(Modifier.height(14.dp))
            ServeRail(sideName(state, side), sideColor(state, side), s, widest)
        }

        val extras = buildList {
            val hs = home.optInt("best_streak")
            val aws = away.optInt("best_streak")
            if (hs > 1 || aws > 1) add(Triple("Longest hold", hs, aws))
            add(Triple("Breaks of serve", home.optInt("breaks"), away.optInt("breaks")))
            if (serve.optBoolean("detailed")) {
                if (home.optInt("aces") + away.optInt("aces") > 0) {
                    add(Triple("Aces", home.optInt("aces"), away.optInt("aces")))
                }
                if (home.optInt("errors_forced") + away.optInt("errors_forced") > 0) {
                    add(Triple("Won on their error", home.optInt("errors_forced"), away.optInt("errors_forced")))
                }
            }
        }
        if (extras.isNotEmpty()) {
            Spacer(Modifier.height(16.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(BoardInk.hairline))
            Spacer(Modifier.height(14.dp))
            extras.forEachIndexed { i, (label, h, a) ->
                if (i > 0) Spacer(Modifier.height(13.dp))
                DuelBar(label, h, a, state.team1Color, state.team2Color)
            }
        }

        val unknown = serve.optInt("unknown")
        if (unknown > 0) {
            Spacer(Modifier.height(12.dp))
            Text(
                if (unknown == 1) {
                    "The opening serve wasn't recorded, so that one rally is left out."
                } else {
                    "$unknown rallies were played before the serve was known, and are left out."
                },
                fontSize = 10.5.sp, color = BoardInk.faint, lineHeight = 14.sp,
            )
        }
    }
}

@Composable
private fun ServeRail(name: String, color: Color, s: JSONObject, widest: Int) {
    val played = s.optInt("played")
    val won = s.optInt("won")
    val t = rememberReveal(played to won, durationMs = 850)

    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            teamShortCode(name), fontSize = 11.sp, fontWeight = FontWeight.Bold,
            color = BoardInk.muted, modifier = Modifier.width(42.dp), maxLines = 1,
        )
        Canvas(Modifier.weight(1f).height(26.dp)) {
            val r = CornerRadius(7f, 7f)
            val full = size.width * (played / widest.toFloat()) * t
            if (full > 1f) {
                drawRoundRect(color.copy(alpha = 0.16f), size = Size(full, size.height), cornerRadius = r)
                val fill = full * (if (played == 0) 0f else won / played.toFloat())
                if (fill > 1f) {
                    drawRoundRect(color, size = Size(fill, size.height), cornerRadius = r)
                }
            }
        }
        Spacer(Modifier.width(10.dp))
        Column(horizontalAlignment = Alignment.End, modifier = Modifier.width(60.dp)) {
            Text(
                "${s.optInt("pct")}%", fontSize = 17.sp, fontFamily = com.haraan.app.theme.ArchivoDisplay,
                color = BoardInk.ink, style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text("$won of $played", fontSize = 9.5.sp, color = BoardInk.faint, maxLines = 1)
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4 · Streaks
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The match as the runs it was made of: one block per unbroken run, in order, home above the
 * line and away below, each block as tall as the run was long. A match of alternating rallies
 * is a flat comb; a match decided by two long bursts looks like two towers. Runs do not cross
 * a game boundary, because the scoreboard they mattered on reset in between.
 */
@Composable
private fun RunSpine(pressure: JSONObject, state: MatchUiState, theme: SportTheme) {
    val runs = pressure.objectList("runs")
    if (runs.isEmpty()) return
    val tallest = runs.maxOf { it.optInt("count") }.coerceAtLeast(1)
    val t = rememberReveal(runs.size, durationMs = 900)
    val runs3 = pressure.optJSONObject("runs3") ?: JSONObject()
    val responses = pressure.optJSONObject("responses") ?: JSONObject()

    InsightPanel(Modifier.insightEnter(3)) {
        Text("Rallies won in a row", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
        Spacer(Modifier.height(3.dp))
        Text(
            "Each block is one unbroken run. Taller is longer.",
            fontSize = 11.5.sp, color = BoardInk.faint,
        )
        Spacer(Modifier.height(14.dp))
        Canvas(Modifier.fillMaxWidth().height(104.dp)) {
            val mid = size.height / 2f
            val gap = 2.5f
            val unit = size.width / runs.sumOf { it.optInt("count") }.coerceAtLeast(1)
            val amp = mid - 4f
            var x = 0f
            var seg = runs.first().optInt("segment")
            runs.forEach { r ->
                val n = r.optInt("count")
                val w = (n * unit - gap).coerceAtLeast(1.5f)
                if (r.optInt("segment") != seg) {
                    drawLine(theme.deep.copy(alpha = 0.2f), Offset(x - gap / 2f, 0f), Offset(x - gap / 2f, size.height), 2f)
                    seg = r.optInt("segment")
                }
                val home = r.optString("side") == "home"
                val hgt = ((n / tallest.toFloat()) * amp * t).coerceAtLeast(2.5f)
                val color = if (home) state.team1Color else state.team2Color
                drawRoundRect(
                    if (n >= 3) color else color.copy(alpha = 0.42f),
                    topLeft = Offset(x, if (home) mid - hgt else mid + 1.5f),
                    size = Size(w, hgt),
                    cornerRadius = CornerRadius(3f, 3f),
                )
                x += n * unit
            }
            drawLine(Color(0xFFCBD5E1), Offset(0f, mid), Offset(size.width, mid), 1.5f)
        }

        Spacer(Modifier.height(14.dp))
        DuelBar("Runs of three or more", runs3.optInt("home"), runs3.optInt("away"), state.team1Color, state.team2Color)
        if (responses.optInt("home") + responses.optInt("away") > 0) {
            Spacer(Modifier.height(13.dp))
            DuelBar("Rallies that stopped one", responses.optInt("home"), responses.optInt("away"), state.team1Color, state.team2Color)
        }
        pressure.optJSONObject("longest")?.let { l ->
            Spacer(Modifier.height(12.dp))
            Text(
                "Longest: ${teamShortCode(sideName(state, l.optString("side")))} won ${l.optInt("count")} in a row, " +
                    "to ${l.optInt("end_home")}–${l.optInt("end_away")} in ${
                        "Game ${l.optInt("segment") + 1}"
                    }.",
                fontSize = 11.5.sp, color = BoardInk.muted, lineHeight = 15.sp,
            )
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5 · Pressure
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The rallies that actually decided games: how many game points a side needed to win one,
 * how many they saved, how the half after the 11-point interval split, and the rallies
 * played past 20-all. Each figure is a count made under badminton's own rules.
 */
@Composable
private fun PressurePanel(
    d: SportInsights,
    pressure: JSONObject,
    games: List<JSONObject>,
    state: MatchUiState,
) {
    val gp = pressure.optJSONObject("game_points") ?: JSONObject()
    val h = gp.optJSONObject("home") ?: JSONObject()
    val a = gp.optJSONObject("away") ?: JSONObject()
    val interval = pressure.optInt("interval", 11)
    val after = pressure.optJSONObject("after_interval") ?: JSONObject()
    val deuce = pressure.optJSONObject("deuce") ?: JSONObject()
    val saved = d.team.sidePair("set_points_saved")
    val won = d.team.sidePair("points_won")

    InsightPanel(Modifier.insightEnter(4)) {
        DuelBar("Rallies won", won.first, won.second, state.team1Color, state.team2Color)

        if (h.optInt("for") + a.optInt("for") > 0) {
            Spacer(Modifier.height(14.dp))
            DuelBar("Game points taken", h.optInt("converted"), a.optInt("converted"), state.team1Color, state.team2Color)
            // The count alone hides the thing worth knowing: how many they needed to take them.
            val needed = listOf("home" to h, "away" to a).mapNotNull { (side, o) ->
                o.optInt("for").takeIf { it > o.optInt("converted") }?.let {
                    "${teamShortCode(sideName(state, side))} needed $it"
                }
            }
            if (needed.isNotEmpty()) {
                Spacer(Modifier.height(6.dp))
                Text(needed.joinToString(" · "), fontSize = 10.5.sp, color = BoardInk.faint)
            }
        }
        if (saved.first + saved.second > 0) {
            Spacer(Modifier.height(14.dp))
            DuelBar("Game points saved", saved.first, saved.second, state.team1Color, state.team2Color)
        }
        if (after.optInt("home") + after.optInt("away") > 0) {
            Spacer(Modifier.height(14.dp))
            DuelBar("Rallies after the $interval-point interval", after.optInt("home"), after.optInt("away"), state.team1Color, state.team2Color)
        }
        if (deuce.optInt("home") + deuce.optInt("away") > 0) {
            Spacer(Modifier.height(14.dp))
            val past = games.firstOrNull { it.optBoolean("deuce") }?.optInt("target", 21)?.minus(1) ?: 20
            DuelBar("Rallies won past $past-all", deuce.optInt("home"), deuce.optInt("away"), state.team1Color, state.team2Color)
        }
    }
}

/** What "Decisive" counts, printed above the cards rather than left to be guessed at. */
@Composable
private fun ImpactNote(interval: Int) {
    Text(
        "On serve — rallies won while their side served. After $interval — rallies won once the " +
            "game passed the interval. Decisive — game points won, game points saved and rallies won at deuce.",
        fontSize = 11.sp, color = BoardInk.faint, lineHeight = 15.sp,
        modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp),
    )
}
