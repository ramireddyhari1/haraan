@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Stadium
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.basicMarquee
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.runtime.getValue
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.animation.core.Animatable
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.State
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.pressable

/**
 * The few pieces every sport's detail screen genuinely shares — a top bar and a panel.
 *
 * Deliberately small. Each sport gets its OWN screen because a volleyball set list, a
 * basketball box score, a kabaddi raid ledger and a tennis scoreboard grid are different
 * objects, not one object with five colour schemes; the moment they share a layout the
 * screens start describing a generic "match" instead of the game being played. What they
 * can share without lying is furniture: the back bar, the card, the scorer chip.
 */

/** Ink palette used across the sport screens. */
object BoardInk {
    val page = CrexColors.Background
    val surface = Color.White
    val hairline = Color(0xFFE6EBF2)
    val ink = Color(0xFF0F172A)
    val muted = Color(0xFF64748B)
    val faint = Color(0xFF94A3B8)
}

/**
 * A content panel.
 *
 * Lifted on a soft shadow rather than outlined with a hairline. A screen where every block is
 * a white rectangle with a 1dp border and identical spacing reads as generated — the eye gets
 * no hierarchy, just a stack of equal boxes. Light and elevation give the same separation
 * while telling you what sits on top of what.
 */
@Composable
fun BoardPanel(content: @Composable ColumnScope.() -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp, vertical = 15.dp),
        content = content,
    )
}

/**
 * A section label that lives OUTSIDE a panel — small caps, wide tracking, quiet.
 *
 * Broadcast score pages label sections this way instead of giving every group its own titled
 * box; it lets two or three panels read as one section rather than three unrelated cards.
 */
@Composable
fun BoardOverline(text: String, modifier: Modifier = Modifier) {
    Text(
        text.uppercase(),
        fontSize = 10.5.sp,
        fontWeight = FontWeight.Bold,
        color = BoardInk.faint,
        letterSpacing = 1.1.sp,
        modifier = modifier.padding(start = 4.dp),
    )
}

@Composable
fun PanelTitle(text: String, trailing: String? = null) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink, modifier = Modifier.weight(1f))
        if (trailing != null) {
            Text(trailing, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.faint)
        }
    }
}

/** Shown before anybody has scored, instead of a board full of honest but useless zeroes. */
@Composable
fun BoardNotStarted(canScore: Boolean, line: String) {
    Column(
        Modifier.fillMaxWidth().padding(top = 36.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("Nothing scored yet", fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
        Spacer(Modifier.height(6.dp))
        Text(
            if (canScore) "Tap Score to start recording." else line,
            fontSize = 13.sp, color = BoardInk.muted,
        )
    }
}

/** Who scored and how much — shared because "a list of names with numbers" is the same
 *  object in every sport; only the heading changes. */
@Composable
fun ScorerPanel(title: String, unit: String, state: MatchUiState, board: SportBoard) {
    BoardPanel {
        PanelTitle(title, unit)
        Spacer(Modifier.height(10.dp))
        board.scorers.take(8).forEachIndexed { i, s ->
            if (i > 0) Spacer(Modifier.height(9.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(6.dp).clip(CircleShape)
                        .background(if (s.side == "home") state.team1Color else state.team2Color)
                )
                Spacer(Modifier.width(9.dp))
                Text(
                    s.name, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f),
                )
                Text(
                    (if (s.side == "home") state.team1 else state.team2),
                    fontSize = 11.5.sp, color = BoardInk.faint, modifier = Modifier.padding(end = 10.dp),
                )
                Text("${s.points}", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = BoardInk.ink)
            }
        }
    }
}

/**
 * Who is on each side. Shared because a squad list is the same object in every sport — the
 * only thing that differs is whether the sport calls them players or a team.
 */
@Composable
fun BoardLineups(state: MatchUiState) {
    val home = state.homeSquad.filter { it.name.isNotBlank() }
    val away = state.awaySquad.filter { it.name.isNotBlank() }

    if (home.isEmpty() && away.isEmpty()) {
        BoardNotStarted(false, "No line-ups were recorded for this match.")
        return
    }

    listOf(
        Triple(state.team1.ifBlank { "Home" }, home, state.team1Color),
        Triple(state.team2.ifBlank { "Away" }, away, state.team2Color),
    ).forEachIndexed { i, (name, squad, accent) ->
        if (i > 0) Spacer(Modifier.height(12.dp))
        BoardPanel {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(8.dp).clip(CircleShape).background(accent))
                Spacer(Modifier.width(9.dp))
                Text(name, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.ink)
                Spacer(Modifier.weight(1f))
                Text("${squad.size}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.faint)
            }
            if (squad.isEmpty()) {
                Spacer(Modifier.height(10.dp))
                Text("No players listed.", fontSize = 12.5.sp, color = BoardInk.faint)
            }
            squad.forEachIndexed { j, member ->
                Spacer(Modifier.height(if (j == 0) 12.dp else 9.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier.size(26.dp).clip(CircleShape).background(accent.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            member.name.take(1).uppercase(),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = accent,
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                    Text(
                        member.name,
                        fontSize = 13.5.sp,
                        color = BoardInk.ink,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

/** Match facts every sport's Summary can end with — venue, format, status. */
@Composable
fun BoardMatchInfo(state: MatchUiState) {
    val rows = buildList {
        // A booked Haraan court gets its own row above the rest, because it is the one line
        // here that is a CLAIM rather than a detail: the venue itself stands behind this
        // result, which is why the match auto-verifies at x1.25 XP.
        if (state.venueBadgeName.isBlank() && state.venue.isNotBlank()) add("Venue" to state.venue)
        if (state.competition.isNotBlank()) add("Format" to state.competition)
        add("Status" to if (state.isLive) "In play" else state.status.ifBlank { "Full time" })
    }
    Column(Modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        if (state.venueBadgeName.isNotBlank()) {
            HaraanVenueRow(state.venueBadgeName, state.venueBadgeArea)
            Spacer(Modifier.height(16.dp))
        }
        Text(
            "MATCH INFO",
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = BoardInk.faint,
            letterSpacing = 0.9.sp,
        )
        rows.forEachIndexed { i, (label, value) ->
            Spacer(Modifier.height(if (i == 0) 10.dp else 0.dp))
            if (i > 0) {
                Box(Modifier.fillMaxWidth().padding(vertical = 9.dp).height(1.dp).background(BoardInk.hairline))
            }
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(label, fontSize = 13.sp, color = BoardInk.muted, modifier = Modifier.weight(1f))
                Text(
                    value,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = BoardInk.ink,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

/**
 * Tab content entering: a short rise and fade, restarted whenever the tab changes.
 *
 * Swapping tabs used to replace the content between two frames. A 240ms entrance is the
 * difference between "the screen changed" and "I moved somewhere".
 */
@Composable
fun Modifier.boardTabEnter(tab: Int): Modifier {
    val enter = remember(tab) { Animatable(0f) }
    LaunchedEffect(tab) { enter.animateTo(1f, tween(durationMillis = 240)) }
    return this.graphicsLayer {
        alpha = enter.value
        translationY = (1f - enter.value) * 26f
    }
}


/**
 * Run of play — the last stretch of points as a row of ticks, oldest to newest.
 *
 * This is the panel every broadcast board has and none of these screens did: a scoreline says
 * who is ahead, but a run of ticks says who is ON TOP RIGHT NOW, which is the thing a viewer
 * opens a live match to find out. Every tick is a recorded point, and in the sports where a
 * point can be worth more than one, the tick grows with it — nothing here is modelled or
 * smoothed.
 */
@Composable
fun BoardMomentum(state: MatchUiState, board: SportBoard) {
    // The feed arrives newest-first; a run reads left to right in time order.
    val points = board.feed.filter { it.kind == "point" }.take(24).reversed()
    if (points.size < 2) return

    // The current streak: how many of the most recent points went one way.
    val newestSide = points.lastOrNull()?.side
    val streak = points.reversed().takeWhile { it.side == newestSide }.size

    Column {
        Row(verticalAlignment = Alignment.CenterVertically) {
            BoardOverline("Run of play", Modifier.weight(1f))
            if (streak >= 2 && newestSide != null) {
                Text(
                    "${if (newestSide == "home") state.team1 else state.team2} · $streak in a row",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (newestSide == "home") state.team1Color else state.team2Color,
                )
            }
        }
        Spacer(Modifier.height(10.dp))
        BoardPanel {
            Row(
                Modifier.fillMaxWidth().height(46.dp),
                verticalAlignment = Alignment.CenterVertically,
                // Fixed-width ticks, centred — sharing the width out meant two points early in
                // a match drew two enormous slabs, which read as a bar chart of nothing.
                horizontalArrangement = Arrangement.spacedBy(2.dp, Alignment.CenterHorizontally),
            ) {
                points.forEach { m ->
                    val home = m.side == "home"
                    val accent = if (home) state.team1Color else state.team2Color
                    // A tick's HEIGHT is what the point was worth, so a basketball three
                    // reads taller than a free throw without needing a legend.
                    val weight = when {
                        m.value >= 3 -> 1f
                        m.value == 2 -> 0.72f
                        else -> 0.5f
                    }
                    Column(
                        Modifier.width(9.dp).fillMaxHeight(),
                        verticalArrangement = Arrangement.Center,
                    ) {
                        // Home ticks hang from the top, away ticks from the bottom, so the
                        // strip reads as two sides of one contest rather than one bar chart.
                        Box(
                            Modifier
                                .fillMaxWidth()
                                .fillMaxHeight(0.5f),
                            contentAlignment = Alignment.BottomCenter,
                        ) {
                            if (home) {
                                Box(
                                    Modifier
                                        .fillMaxWidth()
                                        .fillMaxHeight(weight)
                                        .clip(RoundedCornerShape(topStart = 3.dp, topEnd = 3.dp))
                                        .background(accent)
                                )
                            }
                        }
                        Box(
                            Modifier.fillMaxWidth().fillMaxHeight(),
                            contentAlignment = Alignment.TopCenter,
                        ) {
                            if (!home) {
                                Box(
                                    Modifier
                                        .fillMaxWidth()
                                        .fillMaxHeight(weight)
                                        .clip(RoundedCornerShape(bottomStart = 3.dp, bottomEnd = 3.dp))
                                        .background(accent)
                                )
                            }
                        }
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(7.dp).clip(CircleShape).background(state.team1Color))
                Spacer(Modifier.width(6.dp))
                Text(state.team1, fontSize = 11.sp, color = BoardInk.muted)
                Spacer(Modifier.weight(1f))
                Text("LAST ${points.size} POINTS", fontSize = 9.5.sp, fontWeight = FontWeight.Bold,
                    color = BoardInk.faint, letterSpacing = 0.8.sp)
                Spacer(Modifier.weight(1f))
                Text(state.team2, fontSize = 11.sp, color = BoardInk.muted)
                Spacer(Modifier.width(6.dp))
                Box(Modifier.size(7.dp).clip(CircleShape).background(state.team2Color))
            }
        }
    }
}

/**
 * "Played at" - the booked Haraan court this match was held on.
 *
 * Given a whole row rather than a line in the info table because it is the only thing on the
 * screen that a venue is standing behind. The tick is deliberately NOT the account badge: a
 * verified person and a verified place are different claims, and one glyph for both makes
 * each of them mean less.
 */
@Composable
fun HaraanVenueRow(name: String, area: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFF2563EB).copy(alpha = 0.07f))
            .border(1.dp, Color(0xFF2563EB).copy(alpha = 0.18f), RoundedCornerShape(14.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(34.dp).clip(CircleShape).background(Color(0xFF2563EB).copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                Icons.Filled.Stadium,
                contentDescription = null,
                tint = Color(0xFF2563EB),
                modifier = Modifier.size(17.dp),
            )
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                "PLAYED AT A HARAAN VENUE",
                fontSize = 9.5.sp,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF2563EB),
                letterSpacing = 0.9.sp,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                name,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = BoardInk.ink,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            if (area.isNotBlank()) {
                Text(area, fontSize = 11.5.sp, color = BoardInk.muted, maxLines = 1)
            }
        }
    }
}

/**
 * The live feed — every recorded moment, newest first, in the sport's own words.
 *
 * Each row carries what the server's replay said the moment MEANT (a break of serve, a super
 * tackle, a set point saved) as small tags, and the score in the sport's live unit at that
 * instant. A row that arrives while the screen is open slides in and lifts once; everything
 * already there stays still.
 */
@Composable
fun LiveFeedPanel(
    title: String,
    state: MatchUiState,
    board: SportBoard,
    theme: SportTheme,
    limit: Int = 40,
    /** Which moments to list — defaults to everything the board sent. */
    filter: (BoardMoment) -> Boolean = { true },
) {
    val rows = board.feed.filter(filter).take(limit)
    // The first sequence seen when the panel opened: only rows NEWER than it animate in.
    val baseline = remember { intArrayOf(rows.firstOrNull()?.sequence ?: 0) }
    BoardPanel {
        PanelTitle(title, "${rows.size}")
        Spacer(Modifier.height(8.dp))
        rows.forEachIndexed { i, m ->
            androidx.compose.runtime.key(m.sequence) {
                val fresh = m.sequence > baseline[0]
                if (i > 0) {
                    Box(Modifier.fillMaxWidth().padding(start = 44.dp).height(1.dp).background(BoardInk.hairline))
                }
                FeedRow(m, state, board, theme, fresh)
            }
        }
    }
}

@Composable
private fun FeedRow(m: BoardMoment, state: MatchUiState, board: SportBoard, theme: SportTheme, fresh: Boolean) {
    val enter = remember { Animatable(if (fresh) 0f else 1f) }
    LaunchedEffect(Unit) { if (fresh) enter.animateTo(1f, tween(420)) }
    val accent = when (m.side) {
        "home" -> state.team1Color
        "away" -> state.team2Color
        else -> BoardInk.muted
    }
    val neutral = m.kind == "period" || m.side.isBlank()
    val badge = when {
        m.kind == "period" -> "‖"
        m.kind == "timeout" -> "T"
        m.kind == "serve" -> "S"
        m.kind == "raid" -> "0"
        m.kind in setOf("rebound", "assist", "steal", "block", "foul", "turnover") -> when (m.kind) {
            "rebound" -> "REB"; "assist" -> "AST"; "steal" -> "STL"; "block" -> "BLK"; "foul" -> "PF"; else -> "TO"
        }
        m.value > 0 && board.isPointsSport -> "+${m.value}"
        else -> teamShortCode(if (m.side == "home") state.team1 else state.team2).take(3)
    }
    val tags = feedTagLabels(m, board)
    val sideName = when (m.side) { "home" -> state.team1; "away" -> state.team2; else -> "" }
    val subtitle = listOf(
        if (m.player.isNotBlank()) sideName else "",
        if (m.related.isNotBlank() && board.sport == "basketball") "ast ${m.related}" else "",
    ).filter { it.isNotBlank() }.joinToString(" · ")

    Row(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { alpha = enter.value; translationY = (1f - enter.value) * -18f }
            .changeFlash(if (fresh) m.sequence else null, accent)
            .padding(vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(34.dp).clip(RoundedCornerShape(10.dp))
                .background(if (neutral) Color(0xFFF1F5F9) else accent.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                badge,
                fontSize = if (badge.length > 2) 9.5.sp else 12.sp,
                fontWeight = FontWeight.ExtraBold,
                color = if (neutral) BoardInk.muted else accent,
                maxLines = 1,
            )
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                if (m.player.isNotBlank()) m.player else SportLook.momentLabel(board.sport, m).let {
                    if (sideName.isNotBlank() && m.kind == "point" && !board.isPointsSport) "$it · $sideName" else it
                },
                fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BoardInk.ink,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            val second = listOf(
                if (m.player.isNotBlank()) SportLook.momentLabel(board.sport, m) else "",
                subtitle,
            ).filter { it.isNotBlank() }.joinToString(" · ")
            if (second.isNotBlank() || tags.isNotEmpty()) {
                Spacer(Modifier.height(3.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (second.isNotBlank()) {
                        Text(
                            second, fontSize = 11.sp, color = BoardInk.faint, maxLines = 1,
                            overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false),
                        )
                    }
                    tags.forEach { (label, color) ->
                        Spacer(Modifier.width(6.dp))
                        Box(
                            Modifier.clip(RoundedCornerShape(5.dp)).background(color.copy(alpha = 0.12f))
                                .padding(horizontal = 5.dp, vertical = 1.5.dp),
                        ) {
                            Text(label, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, color = color, letterSpacing = 0.4.sp, maxLines = 1)
                        }
                    }
                }
            }
        }
        Spacer(Modifier.width(8.dp))
        Text(
            m.line.ifBlank { "${m.homeScore}–${m.awayScore}" },
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = BoardInk.muted, maxLines = 1,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}

/** The tags a feed row shows, in words — at most two, most important first. */
private fun feedTagLabels(m: BoardMoment, board: SportBoard): List<Pair<String, Color>> {
    val amber = Color(0xFFD97706)
    val red = Color(0xFFDC2626)
    val green = Color(0xFF059669)
    val slate = Color(0xFF475569)
    val noun = board.setNoun.uppercase()
    return buildList {
        if (m.has("match")) add("MATCH" to green)
        else if (m.has("set")) add("$noun" to green)
        else if (m.has("game") && board.sport == "tennis") add("GAME" to slate)
        if (m.has("all_out")) add("ALL OUT" to red)
        if (m.has("super_tackle")) add("SUPER TACKLE" to Color(0xFF6D28D9))
        if (m.has("super_raid")) add("SUPER RAID" to amber)
        if (m.has("do_or_die")) add("DO-OR-DIE" to red)
        if (m.has("break")) add("BREAK" to amber)
        if (m.has("break_point_saved")) add("BP SAVED" to green)
        if (m.has("saved")) add("$noun PT SAVED" to green)
        if (m.has("tiebreak")) add("TIE-BREAK" to slate)
        if (m.has("side_out")) add("SIDE-OUT" to slate)
        if (m.has("fouled_out")) add("FOULED OUT" to red)
        if (m.has("penalty")) add("PENALTY" to amber)
        if (m.has("change_ends")) add("ENDS" to slate)
    }.take(2)
}
