@file:OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)

package com.haraan.app.ui.matches.insights

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
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
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.BoardInk
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.teamShortCode
import kotlinx.coroutines.delay

/**
 * The furniture every sport's Insights tab is built from.
 *
 * Only furniture. Each sport's tab owns its composition and its signature graphic — a
 * goal story on a 90-minute line, a quarter-by-quarter margin, a raid/tackle mat, a set
 * ladder, a points-against-games ring — because those are different objects. What is
 * shared is how a PLAYER'S VALUE reads (one card, so a basketball scorer and a kabaddi
 * raider are compared the same way across the app), the momentum worm, and the rules for
 * honesty: loading, empty, and the line naming what the scorer does not record.
 */

/** 0 → 1 once, when a block first appears — drives every draw-in on the tab. */
@Composable
fun rememberReveal(key: Any? = Unit, delayMs: Int = 0, durationMs: Int = 700): Float {
    val p = remember(key) { Animatable(0f) }
    LaunchedEffect(key) {
        if (delayMs > 0) delay(delayMs.toLong())
        p.animateTo(1f, tween(durationMs, easing = FastOutSlowInEasing))
    }
    return p.value
}

/** A block rising into place, staggered by its position on the tab. */
@Composable
fun Modifier.insightEnter(index: Int): Modifier {
    val t = rememberReveal(Unit, delayMs = 40 + index * 55, durationMs = 420)
    return graphicsLayer {
        alpha = t
        translationY = (1f - t) * 34f
    }
}

/** Loading, unavailable and not-started, handled once so every sport says them the same way. */
@Composable
fun InsightsGate(
    load: InsightsLoad,
    emptyTitle: String,
    emptyLine: String,
    content: @Composable (SportInsights) -> Unit,
) {
    when (load) {
        InsightsLoad.Loading -> Box(
            Modifier.fillMaxWidth().padding(vertical = 56.dp),
            contentAlignment = Alignment.Center,
        ) {
            CircularProgressIndicator(Modifier.size(26.dp), strokeWidth = 2.5.dp, color = BoardInk.faint)
        }
        InsightsLoad.Unavailable -> InsightsEmpty(
            "Insights unavailable",
            "Couldn't reach the match log. They'll load when you're back online.",
        )
        is InsightsLoad.Locked -> com.haraan.app.ui.membership.InsightsLockedPanel(load.lock.message, load.lock.code)
        is InsightsLoad.Ready -> if (load.data.moments == 0 && load.data.players.isEmpty()) {
            InsightsEmpty(emptyTitle, emptyLine)
        } else {
            content(load.data)
        }
    }
}

@Composable
fun InsightsEmpty(title: String, line: String) {
    Column(
        Modifier.fillMaxWidth().padding(top = 40.dp, bottom = 24.dp, start = 24.dp, end = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
        Spacer(Modifier.height(6.dp))
        Text(line, fontSize = 13.sp, color = BoardInk.muted, textAlign = TextAlign.Center)
    }
}

/**
 * "Live · updates with every point". Shown only while the match is live, so a finished
 * match's insights do not pretend to be moving.
 */
@Composable
fun InsightsLiveLine(live: Boolean, unit: String, accent: Color) {
    if (!live) return
    val pulse = rememberInfiniteTransition(label = "insightsLive")
    val a by pulse.animateFloat(
        initialValue = 0.35f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "insightsLiveA",
    )
    Row(Modifier.padding(start = 4.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(7.dp).graphicsLayer { alpha = a }.clip(CircleShape).background(Color(0xFFEF4444)))
        Spacer(Modifier.width(7.dp))
        Text(
            "LIVE · UPDATES WITH EVERY $unit".uppercase(),
            fontSize = 10.sp, fontWeight = FontWeight.Bold, color = accent, letterSpacing = 1.sp,
        )
    }
}

/** A section head outside the panels: small caps on the left, a quiet figure opposite. */
@Composable
fun InsightHead(label: String, trailing: String? = null, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth().padding(horizontal = 4.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(
            label.uppercase(), fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
            color = BoardInk.faint, letterSpacing = 1.1.sp, modifier = Modifier.weight(1f),
        )
        if (trailing != null) {
            Text(trailing, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.muted)
        }
    }
}

/** The white card every panel on the board screens already uses. */
@Composable
fun InsightPanel(modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp, vertical = 15.dp),
        content = content,
    )
}

/** A number that counts up from zero the first time it shows, and rolls on every update. */
@Composable
fun RollingNumber(value: Int, color: Color, size: Int, modifier: Modifier = Modifier) {
    // Starts at zero so the first appearance counts up; later changes roll from the old value.
    val anim = remember { Animatable(0f) }
    LaunchedEffect(value) { anim.animateTo(value.toFloat(), tween(650, easing = FastOutSlowInEasing)) }
    Text(
        "${kotlin.math.round(anim.value).toInt()}",
        color = color,
        fontSize = size.sp,
        fontFamily = com.haraan.app.theme.ArchivoDisplay,
        letterSpacing = (-0.5).sp,
        maxLines = 1,
        style = TextStyle(fontFeatureSettings = "tnum"),
        modifier = modifier,
    )
}

/**
 * Head-to-head on one line: two bars growing from the centre, the larger side in full colour.
 * Used for any count where the two sides are compared — shots, tackle points, sets.
 */
@Composable
fun DuelBar(
    label: String,
    home: Int,
    away: Int,
    homeColor: Color,
    awayColor: Color,
    format: (Int) -> String = { "$it" },
) {
    val total = (home + away).coerceAtLeast(1)
    val h by animateFloatAsState(home / total.toFloat(), tween(750, easing = FastOutSlowInEasing), label = "duelH")
    val a by animateFloatAsState(away / total.toFloat(), tween(750, easing = FastOutSlowInEasing), label = "duelA")
    Column {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                format(home), fontSize = 14.sp, fontWeight = if (home >= away) FontWeight.ExtraBold else FontWeight.SemiBold,
                color = if (home >= away) BoardInk.ink else BoardInk.muted, modifier = Modifier.width(44.dp),
            )
            Text(label, fontSize = 12.sp, color = BoardInk.muted, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
            Text(
                format(away), fontSize = 14.sp, fontWeight = if (away >= home) FontWeight.ExtraBold else FontWeight.SemiBold,
                color = if (away >= home) BoardInk.ink else BoardInk.muted, textAlign = TextAlign.End, modifier = Modifier.width(44.dp),
            )
        }
        Spacer(Modifier.height(6.dp))
        Row(Modifier.fillMaxWidth().height(6.dp), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Box(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(3.dp)).background(Color(0xFFF1F5F9)), contentAlignment = Alignment.CenterEnd) {
                Box(Modifier.fillMaxWidth(h).fillMaxHeight().clip(RoundedCornerShape(3.dp))
                    .background(if (home >= away) homeColor else homeColor.copy(alpha = 0.45f)))
            }
            Box(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(3.dp)).background(Color(0xFFF1F5F9)), contentAlignment = Alignment.CenterStart) {
                Box(Modifier.fillMaxWidth(a).fillMaxHeight().clip(RoundedCornerShape(3.dp))
                    .background(if (away >= home) awayColor else awayColor.copy(alpha = 0.45f)))
            }
        }
    }
}

/**
 * The momentum worm: the margin after every scoring moment, home above the line and away
 * below, drawn left to right as it appears. Segments (quarters, sets) are parted by a
 * hairline and labelled under the axis — in a sets sport each set restarts at level, so the
 * worm visibly returns to the line, which is exactly what happened.
 */
@Composable
fun MomentumWorm(
    series: List<Pair<Int, Int>>,
    segmentLabels: List<String>,
    homeColor: Color,
    awayColor: Color,
    height: Int = 120,
    revealKey: Any? = Unit,
) {
    if (series.size < 2) return
    val t = rememberReveal(revealKey, durationMs = 1100)
    val peak = series.maxOf { kotlin.math.abs(it.first) }.coerceAtLeast(1)

    Column {
        Canvas(Modifier.fillMaxWidth().height(height.dp)) {
            val w = size.width
            val mid = size.height / 2f
            val stepX = w / (series.size - 1).coerceAtLeast(1)
            val amp = (size.height / 2f) * 0.9f

            // Segment boundaries.
            series.forEachIndexed { i, (_, seg) ->
                if (i > 0 && series[i - 1].second != seg) {
                    val x = i * stepX
                    drawLine(Color(0xFFE2E8F0), Offset(x, 0f), Offset(x, size.height), 2f)
                }
            }
            drawLine(Color(0xFFCBD5E1), Offset(0f, mid), Offset(w, mid), 2f)

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
            drawPath(fillHome, Brush.verticalGradient(listOf(homeColor.copy(alpha = 0.30f), homeColor.copy(alpha = 0.04f)), 0f, mid))
            drawPath(fillAway, Brush.verticalGradient(listOf(awayColor.copy(alpha = 0.04f), awayColor.copy(alpha = 0.30f)), mid, size.height))
            drawPath(path, Color(0xFF334155), style = Stroke(width = 3.5f, cap = StrokeCap.Round))
            val last = series[shown - 1].first
            drawCircle(
                if (last >= 0) homeColor else awayColor, 7f,
                Offset(endX, mid - (last / peak.toFloat()) * amp),
            )
        }
        if (segmentLabels.size > 1) {
            Spacer(Modifier.height(6.dp))
            Row(Modifier.fillMaxWidth()) {
                // Label each segment under its share of the axis.
                val counts = segmentLabels.indices.map { s -> series.count { it.second == s }.coerceAtLeast(0) }
                val total = counts.sum().coerceAtLeast(1)
                segmentLabels.forEachIndexed { i, label ->
                    val weight = counts.getOrElse(i) { 0 }
                    if (weight > 0) {
                        Text(
                            label, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.faint,
                            textAlign = TextAlign.Center, maxLines = 1, overflow = TextOverflow.Clip,
                            modifier = Modifier.weight(weight / total.toFloat()),
                        )
                    }
                }
            }
        }
    }
}

/**
 * The flow of the match as three figures in one panel, parted by hairlines — never three
 * equal boxes. A figure that does not exist (no run of two yet) is left out, not zeroed.
 */
@Composable
fun FlowFigures(figures: List<Triple<String, String, String?>>) {
    if (figures.isEmpty()) return
    InsightPanel {
        Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
            figures.forEachIndexed { i, (value, label, sub) ->
                if (i > 0) Box(Modifier.width(1.dp).fillMaxHeight().background(BoardInk.hairline))
                Column(Modifier.weight(1f).padding(horizontal = 8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        // A long figure steps down a size rather than clipping inside its third.
                        value, fontSize = if (value.length > 4) 17.sp else 22.sp,
                        fontFamily = com.haraan.app.theme.ArchivoDisplay, color = BoardInk.ink,
                        maxLines = 1, style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.muted, textAlign = TextAlign.Center, maxLines = 1)
                    if (sub != null) {
                        Text(sub, fontSize = 10.sp, color = BoardInk.faint, textAlign = TextAlign.Center, maxLines = 2)
                    }
                }
            }
        }
    }
}

/** Honours read gold, discipline reads red/amber, everything else wears the sport's colour. */
private fun tagTone(key: String, accent: Color): Color = when (key) {
    "hat_trick", "winner", "super_10", "high_5", "twenty", "closer", "top_scorer", "top_raider", "top_defender" -> Color(0xFFB45309)
    "sent_off", "own_goal" -> Color(0xFFDC2626)
    "booked" -> Color(0xFFD97706)
    else -> accent
}

@Composable
fun InsightTags(tags: List<InsightTag>, accent: Color) {
    if (tags.isEmpty()) return
    FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        tags.forEach { tag ->
            val tone = tagTone(tag.key, accent)
            Text(
                tag.label,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.Bold,
                color = tone,
                maxLines = 1,
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(tone.copy(alpha = 0.10f))
                    .padding(horizontal = 9.dp, vertical = 4.dp),
            )
        }
    }
}

/** The player's face when their squad entry is a real account; initials on team colour otherwise. */
@Composable
fun PlayerFace(name: String, avatar: String, color: Color, size: Int) {
    Box(
        Modifier.size(size.dp).clip(CircleShape).background(color.copy(alpha = 0.14f)),
        contentAlignment = Alignment.Center,
    ) {
        val initials = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
            .take(2).joinToString("") { it.take(1).uppercase() }
        Text(initials.ifBlank { "?" }, fontSize = (size * 0.36f).sp, fontWeight = FontWeight.ExtraBold, color = color)
        if (avatar.isNotBlank()) {
            coil.compose.AsyncImage(
                model = avatar, contentDescription = name, contentScale = ContentScale.Crop,
                modifier = Modifier.matchParentSize().clip(CircleShape),
            )
        }
    }
}

/**
 * A player's value, taken in at a glance.
 *
 * Read in the order the eye goes: the face with a ring that fills to their SHARE of the
 * team's total, the headline figure large and rolling, then the honours, then the evidence
 * as a quiet row of labelled figures. The ring is a share, not a rating — "38% of HHH's
 * points" is a fact; an invented 0–100 impact score would not be.
 *
 * The leader's card is the one allowed to raise its voice: a tinted field in the sport's
 * colour and a larger numeral. Every other card stays white, so the page has one peak.
 */
@Composable
fun PlayerValueCard(
    player: PlayerInsight,
    state: MatchUiState,
    accent: Color,
    lead: Boolean,
    index: Int,
    shareOf: String,
    footer: (@Composable ColumnScope.() -> Unit)? = null,
) {
    val teamColor = if (player.isHome) state.team1Color else state.team2Color
    val team = if (player.isHome) state.team1 else state.team2
    val ring = rememberReveal(player.name, delayMs = 120 + index * 60, durationMs = 900)
    val shareAnim by animateFloatAsState(player.share / 100f, tween(800), label = "shareRing")

    val shape = RoundedCornerShape(if (lead) 20.dp else 16.dp)
    Column(
        Modifier
            .fillMaxWidth()
            .insightEnter(index)
            .clip(shape)
            .background(
                if (lead) Brush.linearGradient(listOf(accent.copy(alpha = 0.13f), Color.White))
                else Brush.linearGradient(listOf(Color.White, Color.White))
            )
            .border(1.dp, if (lead) accent.copy(alpha = 0.28f) else Color(0xFFE2E8F0), shape)
            .padding(horizontal = 16.dp, vertical = if (lead) 18.dp else 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            val face = if (lead) 58 else 46
            Box(contentAlignment = Alignment.Center) {
                Canvas(Modifier.size((face + 10).dp)) {
                    val stroke = if (lead) 7f else 5.5f
                    val inset = stroke / 2f
                    drawArc(
                        Color(0xFFE2E8F0), -90f, 360f, false,
                        topLeft = Offset(inset, inset), size = Size(size.width - stroke, size.height - stroke),
                        style = Stroke(stroke),
                    )
                    drawArc(
                        teamColor, -90f, 360f * shareAnim * ring, false,
                        topLeft = Offset(inset, inset), size = Size(size.width - stroke, size.height - stroke),
                        style = Stroke(stroke, cap = StrokeCap.Round),
                    )
                }
                PlayerFace(player.name, player.avatar, teamColor, face)
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                if (lead) {
                    Text("STANDOUT", fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = accent, letterSpacing = 1.2.sp)
                    Spacer(Modifier.height(2.dp))
                }
                Text(
                    player.name, fontSize = if (lead) 17.sp else 15.sp, fontWeight = FontWeight.Bold,
                    color = BoardInk.ink, maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(7.dp).clip(CircleShape).background(teamColor))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        // A singles player is 100% of their side — saying so is noise, so only a real share is printed.
                        if (player.share in 1..99) "${teamShortCode(team)} · ${player.share}% of $shareOf" else team,
                        fontSize = 11.5.sp, color = BoardInk.muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            Column(horizontalAlignment = Alignment.End) {
                RollingNumber(player.headline, if (lead) accent else BoardInk.ink, if (lead) 40 else 30)
                Text(
                    player.headlineLabel.uppercase(), fontSize = 9.5.sp, fontWeight = FontWeight.Bold,
                    color = BoardInk.faint, letterSpacing = 0.9.sp,
                )
            }
        }

        if (player.tags.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            InsightTags(player.tags, accent)
        }

        val stats = player.stats.filter { it.second.isNotBlank() }
        if (stats.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(BoardInk.hairline))
            Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth()) {
                stats.forEach { (label, value) ->
                    Column(Modifier.weight(1f)) {
                        Text(
                            value, fontSize = 14.5.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink,
                            maxLines = 1, overflow = TextOverflow.Ellipsis, style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                        Text(label, fontSize = 10.5.sp, color = BoardInk.faint, maxLines = 1)
                    }
                }
            }
        }
        footer?.invoke(this)
    }
}

/**
 * The players, best first. The top card leads; the rest follow at normal weight, and past
 * the first six a quiet count says how many more were credited rather than an endless list.
 */
@Composable
fun PlayerValueList(
    players: List<PlayerInsight>,
    state: MatchUiState,
    accent: Color,
    shareUnit: String,
    startIndex: Int = 0,
    footer: (@Composable ColumnScope.(PlayerInsight) -> Unit)? = null,
) {
    if (players.isEmpty()) {
        InsightPanel {
            Text("No players credited yet", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
            Spacer(Modifier.height(3.dp))
            Text(
                "Player cards appear when the scorer credits who scored.",
                fontSize = 12.sp, color = BoardInk.muted,
            )
        }
        return
    }
    val shown = players.take(10)
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        shown.forEachIndexed { i, p ->
            PlayerValueCard(
                player = p,
                state = state,
                accent = accent,
                lead = i == 0,
                index = startIndex + i,
                shareOf = "${if (p.isHome) teamShortCode(state.team1) else teamShortCode(state.team2)}'s $shareUnit",
                footer = footer?.let { f -> { f(p) } },
            )
        }
        if (players.size > shown.size) {
            Text(
                "+${players.size - shown.size} more credited",
                fontSize = 11.5.sp, color = BoardInk.faint, modifier = Modifier.padding(start = 4.dp),
            )
        }
    }
}

/** Sentences picked by rule from the figures above them — each one checkable on the tab. */
@Composable
fun InsightReads(reads: List<String>, accent: Color) {
    if (reads.isEmpty()) return
    InsightPanel {
        reads.forEachIndexed { i, line ->
            if (i > 0) Spacer(Modifier.height(10.dp))
            Row {
                Box(Modifier.padding(top = 6.dp).size(6.dp).clip(CircleShape).background(accent))
                Spacer(Modifier.width(10.dp))
                Text(line, fontSize = 13.5.sp, color = BoardInk.ink, lineHeight = 19.sp)
            }
        }
    }
}

/** What this sport's scorer does not record — so an absent stat reads as absent, not forgotten. */
@Composable
fun UntrackedNote(items: List<String>) {
    if (items.isEmpty()) return
    Text(
        "Not recorded by the scorer: ${items.joinToString(" · ")}",
        fontSize = 11.sp,
        color = BoardInk.faint,
        modifier = Modifier.fillMaxWidth().padding(horizontal = 6.dp, vertical = 4.dp),
    )
}

/** A mini bar row for per-segment figures on a player card (quarters, sets). */
@Composable
fun SegmentBars(values: List<Int>, labels: List<String>, color: Color) {
    if (values.size < 2 || values.all { it == 0 }) return
    val peak = values.max().coerceAtLeast(1)
    val t = rememberReveal(values, durationMs = 600)
    Spacer(Modifier.height(10.dp))
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.Bottom) {
        values.forEachIndexed { i, v ->
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Text("$v", fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = if (v == peak) BoardInk.ink else BoardInk.muted)
                Spacer(Modifier.height(3.dp))
                Box(Modifier.fillMaxWidth().height(28.dp), contentAlignment = Alignment.BottomCenter) {
                    Canvas(Modifier.fillMaxWidth().fillMaxHeight(((v / peak.toFloat()) * t).coerceIn(0.04f, 1f))) {
                        drawRoundRect(if (v == peak) color else color.copy(alpha = 0.35f), cornerRadius = CornerRadius(6f, 6f))
                    }
                }
                Spacer(Modifier.height(3.dp))
                Text(labels.getOrElse(i) { "" }, fontSize = 9.5.sp, color = BoardInk.faint, maxLines = 1)
            }
        }
    }
}

fun sideName(state: MatchUiState, side: String): String = if (side == "home") state.team1 else state.team2
fun sideColor(state: MatchUiState, side: String): Color = if (side == "home") state.team1Color else state.team2Color

/**
 * The three flow figures most sports share — lead changes, biggest lead, longest run — in
 * that sport's own words. Figures that never happened are dropped rather than zeroed.
 */
fun flowFigures(
    flow: InsightFlow,
    state: MatchUiState,
    runLabel: String,
    runValue: (Run) -> String = { "${it.value}–0" },
    includeLeadChanges: Boolean = true,
): List<Triple<String, String, String?>> = buildList {
    if (includeLeadChanges) {
        add(Triple("${flow.leadChanges}", if (flow.leadChanges == 1) "Lead change" else "Lead changes",
            when (flow.ties) { 0 -> null; 1 -> "Level once"; else -> "Level ${flow.ties} times" }))
    }
    flow.biggestLead?.let { l ->
        add(Triple("+${l.margin}", "Biggest lead", "${teamShortCode(sideName(state, l.side))} at ${l.home}–${l.away}"))
    }
    flow.longestRun?.let { r ->
        add(Triple(runValue(r), runLabel, teamShortCode(sideName(state, r.side))))
    }
}
