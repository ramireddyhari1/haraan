package com.haraan.app.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Verified
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.PlatformTextStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.LineHeightStyle
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.CommentaryLine
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.RecentOver

// SIX → green, FOUR → blue, WICKET → solid red. Dots/singles stay neutral grey.
private val SixGreen = Color(0xFF16A34A)
private val FourBlue = Color(0xFF2563EB)

@Composable
fun BallCircle(ball: String) {
    val isW = ball == "W"
    val accent = when (ball) {
        "6" -> SixGreen
        "4" -> FourBlue
        else -> null
    }
    Box(
        modifier = Modifier
            .size(22.dp)
            .clip(CircleShape)
            .then(
                when {
                    isW -> Modifier.background(CrexColors.AccentRed)
                    accent != null -> Modifier.background(accent.copy(alpha = 0.15f)).border(1.5.dp, accent, CircleShape)
                    else -> Modifier.background(CrexColors.Background).border(1.dp, CrexColors.Border, CircleShape)
                }
            ),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = ball,
            color = if (isW) Color.White else accent ?: CrexColors.TextSecondary,
            fontSize = 10.sp,
            fontWeight = if (isW || accent != null) FontWeight.Bold else FontWeight.Medium,
            textAlign = TextAlign.Center,
            // Strip the default font padding / line spacing so a single glyph sits dead
            // centre in the small circle instead of riding high.
            style = TextStyle(
                platformStyle = PlatformTextStyle(includeFontPadding = false),
                lineHeightStyle = LineHeightStyle(
                    alignment = LineHeightStyle.Alignment.Center,
                    trim = LineHeightStyle.Trim.Both
                )
            )
        )
    }
}

@Composable
fun CommentaryTab(state: MatchUiState, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background),
        contentPadding = PaddingValues(bottom = 80.dp)
    ) {
        // Result banner — only once the match is over (the header already shows LIVE while
        // it's in progress, so we don't repeat a "Live" line here).
        if (!state.isLive && state.status.isNotBlank()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(CrexColors.Background)
                        .padding(bottom = 6.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(CrexColors.AccentYellow.copy(alpha = 0.1f))
                            .padding(vertical = 6.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = state.status,
                            color = CrexColors.AccentYellow,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
        }

        // Over Tracker — premium scrolling over-chips
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(CrexColors.Background)
                    .horizontalScroll(androidx.compose.foundation.rememberScrollState())
                    .padding(start = 16.dp, end = 16.dp, top = 10.dp, bottom = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // recentOvers already includes the in-progress over (last item), so just
                // render it and mark the last one as current — no separate "this over" chip
                // (that was the duplicate). Fall back to thisOver only if there's no history.
                if (state.recentOvers.isNotEmpty()) {
                    state.recentOvers.forEachIndexed { i, over ->
                        OverChip(
                            label = over.label, balls = over.balls, runs = over.runs,
                            current = state.isLive && i == state.recentOvers.lastIndex
                        )
                    }
                } else if (state.thisOver.isNotEmpty()) {
                    val currentOverNum = ((state.overs.toDoubleOrNull() ?: 0.0).toInt() + 1).toString()
                    val thisOverRuns = state.thisOver.sumOf { ball ->
                        val r = ball.toIntOrNull()
                        if (r != null) r else if (ball.startsWith("wd", ignoreCase = true) || ball.startsWith("nb", ignoreCase = true)) 1 else 0
                    }
                    OverChip(label = currentOverNum, balls = state.thisOver, runs = thisOverRuns, current = true)
                }
            }
        }

        // Batter + Partnership + Bowler unified into a single card so the whole
        // "mini-scorecard" reads as one consistent surface (not two floating strips).
        item {
            val pShipText = state.partnership?.takeIf { it.balls > 0 || it.runs > 0 }?.let { "P'Ship: ${it.runs} (${it.balls})" }
            val lastWktText = state.lastWicket?.takeIf { it.name.isNotBlank() }?.let { "Last wkt: ${it.name} ${it.runs} (${it.balls})" }

            Column(
                modifier = Modifier
                    .padding(start = 12.dp, end = 12.dp, top = 2.dp, bottom = 8.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(CrexColors.Surface)
                    .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            ) {
                // ── Batter ──
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BATTER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("R", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(38.dp), textAlign = TextAlign.Center)
                        Text("B", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(34.dp), textAlign = TextAlign.Center)
                        Text("4S", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
                        Text("6S", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }

                if (state.striker.isNotEmpty()) {
                    val stats = state.strikerStats
                    val runs = stats?.runs?.toString() ?: "0"
                    val balls = stats?.balls?.toString() ?: "0"
                    val fours = stats?.fours?.toString() ?: "0"
                    val sixes = stats?.sixes?.toString() ?: "0"
                    val sr = if (stats != null && stats.balls > 0) {
                        String.format("%.2f", (stats.runs.toFloat() / stats.balls) * 100)
                    } else "0.00"
                    BatterRow(name = state.striker + " *", runs = runs, balls = balls, fours = fours, sixes = sixes, sr = sr)
                }
                if (state.nonStriker.isNotEmpty()) {
                    val stats = state.nonStrikerStats
                    val runs = stats?.runs?.toString() ?: "0"
                    val balls = stats?.balls?.toString() ?: "0"
                    val fours = stats?.fours?.toString() ?: "0"
                    val sixes = stats?.sixes?.toString() ?: "0"
                    val sr = if (stats != null && stats.balls > 0) {
                        String.format("%.2f", (stats.runs.toFloat() / stats.balls) * 100)
                    } else "0.00"
                    BatterRow(name = state.nonStriker, runs = runs, balls = balls, fours = fours, sixes = sixes, sr = sr)
                }

                // ── Partnership / last wicket — only real values ──
                if (pShipText != null || lastWktText != null) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(CrexColors.Background)
                            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                            .padding(horizontal = 16.dp, vertical = 10.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(pShipText ?: "", color = CrexColors.TextSecondary, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        Text(lastWktText ?: "", color = CrexColors.TextSecondary, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                    }
                }

                // ── Bowler ──
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BOWLER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("W-R", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
                        Text("OV", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
                        Text("ECON", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }

                if (state.bowler.isNotEmpty()) {
                    val stats = state.bowlerStats
                    val wickets = stats?.wickets ?: 0
                    val runs = stats?.runs ?: 0
                    val balls = stats?.balls ?: 0
                    val oversDecimal = "${balls / 6}.${balls % 6}"
                    val econ = if (balls > 0) {
                        String.format("%.2f", (runs.toFloat() / balls) * 6)
                    } else "0.00"
                    BowlerRow(name = state.bowler, figures = "$wickets-$runs", overs = oversDecimal, econ = econ)
                }
            }
        }

        // ── Ball-by-ball commentary feed ──
        if (state.commentary.isNotEmpty()) {
            item {
                Text(
                    "COMMENTARY",
                    color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp,
                    modifier = Modifier.fillMaxWidth().background(CrexColors.Background).padding(start = 16.dp, top = 16.dp, bottom = 4.dp)
                )
            }
            items(state.commentary) { line ->
                when {
                    line.kind == "header" -> CommentaryHeader(line.text)
                    line.kind == "batter_in" -> NewBatterBanner(line)
                    line.wicket -> WicketBanner(line, state)
                    else -> CommentaryRow(line)
                }
            }
        }

        // Nothing-yet state — shown only when there's no real scoring data at all.
        if (state.commentary.isEmpty() && state.striker.isBlank() && state.thisOver.isEmpty() && state.recentOvers.isEmpty()) {
            item {
                Box(
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "No commentary yet — it'll appear here once scoring begins.",
                        color = CrexColors.TextMuted, fontSize = 13.sp, textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}

@Composable
private fun CommentaryHeader(text: String) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(CrexColors.AccentBlue.copy(alpha = 0.08f))
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Text(text, color = CrexColors.AccentBlue, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

/**
 * Premium red WICKET banner shown inline in the feed for every dismissal — the out
 * batter's name + figures, how they went, and the score at the fall. Name/figures/score
 * are cross-referenced from the replayed innings (fall-of-wickets → batting card) so it's
 * all real; if a live wicket hasn't landed in the card yet it degrades to the dismissal
 * line alone.
 */
@Composable
private fun WicketBanner(line: CommentaryLine, state: MatchUiState) {
    val card = state.inningsCards.firstOrNull { it.number == line.innings }
    val fow = card?.fallOfWickets?.firstOrNull { it.over == line.over }
    val batterName = fow?.batter?.takeIf { it.isNotBlank() }
    val bat = batterName?.let { name -> card?.batters?.firstOrNull { it.name == name } }
    val figures = bat?.let { "${it.runs} (${it.balls})" }
    // The backend prepends "OUT! " to the dismissal — strip it for the sub-line.
    val dismissal = line.text.removePrefix("OUT!").trim().ifBlank { "out" }
    val scoreAtFall = fow?.let { "${it.score}-${it.wicketNo}" }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Brush.horizontalGradient(listOf(Color(0xFFE23B3B), Color(0xFFB91C1C))))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier.size(40.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.18f)),
            contentAlignment = Alignment.Center
        ) {
            Text((batterName ?: "?").first().uppercase(), color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Black)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("WICKET", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                Box(Modifier.clip(RoundedCornerShape(4.dp)).background(Color.White).padding(horizontal = 5.dp, vertical = 1.dp)) {
                    Text("OUT", color = Color(0xFFB91C1C), fontSize = 8.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                }
            }
            Spacer(Modifier.height(3.dp))
            Text(
                buildString {
                    append(batterName ?: line.battingName.ifBlank { "Batter" })
                    if (figures != null) append("  $figures")
                },
                color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Black, maxLines = 1
            )
            Text(dismissal, color = Color.White.copy(alpha = 0.85f), fontSize = 12.sp, fontWeight = FontWeight.Medium, maxLines = 2)
        }
        Spacer(Modifier.width(10.dp))
        Column(horizontalAlignment = Alignment.End) {
            if (scoreAtFall != null) Text(scoreAtFall, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Black)
            if (line.over.isNotBlank()) Text("${line.over} ov", color = Color.White.copy(alpha = 0.8f), fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

/**
 * Premium blue "new batter" card shown when a batter walks in. Career figures (RUNS /
 * BALLS / HS / AVG / SR) are the player's REAL totals from the ball log; guests or players
 * with no completed innings show a clean "first recorded innings" line instead of faked stats.
 */
@Composable
private fun NewBatterBanner(line: CommentaryLine) {
    val c = line.career
    val name = line.text.ifBlank { "New batter" }
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Brush.horizontalGradient(listOf(Color(0xFF2563EB), Color(0xFF1D4ED8))))
            .padding(14.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier.size(44.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.18f)),
                contentAlignment = Alignment.Center
            ) {
                Text(name.first().uppercase(), color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.Black)
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("NEW BATTER", color = Color.White.copy(alpha = 0.85f), fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                Spacer(Modifier.height(2.dp))
                Text(name, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Black, maxLines = 1)
            }
            if (line.over.isNotBlank()) {
                Text("${line.over} ov", color = Color.White.copy(alpha = 0.8f), fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
            }
        }

        if (c != null && (c.innings > 0 || c.balls > 0)) {
            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.15f)))
            Spacer(Modifier.height(10.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                StatCol("INN", c.innings.toString())
                StatCol("RUNS", c.runs.toString())
                StatCol("BALLS", c.balls.toString())
                StatCol("HS", c.highScore.toString())
                StatCol("AVG", c.avg?.let { String.format("%.1f", it) } ?: "—")
                StatCol("SR", c.sr?.let { String.format("%.1f", it) } ?: "—")
            }
        } else {
            Spacer(Modifier.height(8.dp))
            Text("First recorded innings", color = Color.White.copy(alpha = 0.8f), fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun StatCol(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Black)
        Text(label, color = Color.White.copy(alpha = 0.7f), fontSize = 9.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.5.sp)
    }
}

@Composable
private fun CommentaryRow(line: CommentaryLine) {
    val (bg, fg) = when {
        line.wicket -> CrexColors.AccentRed to Color.White
        line.boundary -> CrexColors.SixBall.copy(alpha = 0.15f) to CrexColors.SixBall
        line.label == "0" -> CrexColors.Background to CrexColors.TextMuted
        line.label.lowercase() in setOf("wd", "nb", "b", "lb") -> Color(0xFFFEF3C7) to Color(0xFF92400E)
        else -> CrexColors.Background to CrexColors.TextSecondary
    }
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(CrexColors.Surface)
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            line.over,
            color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold,
            modifier = Modifier.width(34.dp)
        )
        Box(
            modifier = Modifier.size(28.dp).clip(CircleShape).background(bg),
            contentAlignment = Alignment.Center
        ) {
            Text(
                if (line.label == "0") "•" else line.label,
                color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center,
                style = TextStyle(platformStyle = PlatformTextStyle(includeFontPadding = false))
            )
        }
        Spacer(Modifier.width(12.dp))
        Text(
            line.text,
            color = if (line.wicket) CrexColors.AccentRed else CrexColors.TextPrimary,
            fontSize = 13.sp,
            fontWeight = if (line.wicket || line.boundary) FontWeight.Bold else FontWeight.Normal,
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
fun BatterRow(name: String, runs: String, balls: String, fours: String, sixes: String, sr: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(CrexColors.Background),
                contentAlignment = Alignment.Center
            ) {
                Text(name.first().toString(), color = CrexColors.TextSecondary)
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                }
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("SR $sr", color = CrexColors.TextSecondary, fontSize = 10.sp, letterSpacing = 1.sp)
                    XpChip((runs.toIntOrNull() ?: 0) + (fours.toIntOrNull() ?: 0) + (sixes.toIntOrNull() ?: 0) * 2)
                }
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(runs, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, softWrap = false, modifier = Modifier.width(38.dp), textAlign = TextAlign.Center)
            Text(balls, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(34.dp), textAlign = TextAlign.Center)
            Text(fours, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
            Text(sixes, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
            Spacer(modifier = Modifier.width(24.dp))
        }
    }
}

@Composable
fun BowlerRow(name: String, figures: String, overs: String, econ: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(CrexColors.Background),
                contentAlignment = Alignment.Center
            ) {
                Text(name.first().toString(), color = CrexColors.TextSecondary)
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    Icon(
                        imageVector = Icons.Outlined.Verified,
                        contentDescription = "Verified",
                        tint = CrexColors.TextSecondary,
                        modifier = Modifier.size(10.dp)
                    )
                }
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("BOWLER", color = CrexColors.TextSecondary, fontSize = 10.sp, letterSpacing = 1.sp)
                    XpChip((figures.split("-").getOrNull(0)?.toIntOrNull() ?: 0) * 20 + 5)
                }
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(figures, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, softWrap = false, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
            Text(overs, color = CrexColors.TextSecondary, fontSize = 13.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
            Text(econ, color = CrexColors.TextSecondary, fontSize = 13.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
            Spacer(modifier = Modifier.width(24.dp))
        }
    }
}

/** Premium over summary: OVER label · ball circles · runs pill. Highlighted when current. */
@Composable
private fun OverChip(label: String, balls: List<String>, runs: Int, current: Boolean) {
    Row(
        modifier = Modifier
            .height(IntrinsicSize.Min)
            .clip(RoundedCornerShape(12.dp))
            .background(if (current) CrexColors.AccentBlue.copy(alpha = 0.06f) else CrexColors.Surface)
            .border(
                1.dp,
                if (current) CrexColors.AccentBlue.copy(alpha = 0.40f) else CrexColors.Border,
                RoundedCornerShape(12.dp)
            )
            .padding(horizontal = 10.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(9.dp)
    ) {
        // Fixed-width label so the divider lands in the same place for "1" and "10",
        // and the number sits centred beside the full-height divider line.
        Column(
            modifier = Modifier.widthIn(min = 26.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Text("OVER", color = CrexColors.TextMuted, fontSize = 7.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
            Text(label, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Black)
        }
        Box(Modifier.width(1.dp).fillMaxHeight().background(CrexColors.Border))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            balls.forEach { BallCircle(ball = it) }
        }
        // Over total — plain black "= N", not a coloured pill.
        Text(
            "= $runs",
            color = CrexColors.TextPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.Black
        )
    }
}

/** Small green XP credit chip shown on batter/bowler rows. */
@Composable
private fun XpChip(xp: Int) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(CrexColors.AccentGreen.copy(alpha = 0.12f))
            .padding(horizontal = 6.dp, vertical = 1.dp)
    ) {
        Text("+$xp XP", color = CrexColors.AccentGreen, fontSize = 9.sp, fontWeight = FontWeight.Bold)
    }
}
