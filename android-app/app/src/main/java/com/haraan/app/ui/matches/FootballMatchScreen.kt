package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.basicMarquee
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentWidth
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SportsSoccer
import androidx.compose.material.icons.filled.SwapHoriz
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.NestedScrollSource
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.SquadMember
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Blue = HaraanColors.EventsBlue
private val Amber = HaraanColors.Warning
private val Red = HaraanColors.Danger
private val Hairline = HaraanColors.BorderLight

// A night-stadium hero gradient — deep navy → brand blue. Football detail screens read
// as broadcast scoreboards; a rich dark hero over the light body is what makes the
// scoreline feel like an event rather than a table row.
private val HeroTop = Color(0xFF0C1E45)
private val HeroBottom = Color(0xFF1D4ED8)
private val OnHero = Color(0xFFF8FAFC)
private val OnHeroDim = Color(0xFFB6C4E4)

/**
 * Football's match-detail screen — rebuilt as a broadcast-style scoreboard.
 *
 * Cricket earns five tabs because it genuinely has five views; football has less
 * durable state, so it keeps three honest ones — Summary, Timeline, Line-ups — and
 * never invents possession/shots it can't measure. Everything shown is derived from
 * real recorded events: the score, the scorers, the cards, the squads.
 */
@Composable
fun FootballMatchScreen(
    state: MatchUiState,
    onBack: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val football = state.football ?: FootballState()
    var tab by remember { mutableIntStateOf(0) }
    val tabs = listOf("Summary", "Stats", "Timeline", "Line-ups")

    // Collapsing hero: the full scoreboard shrinks to a slim sticky bar as the content
    // scrolls up, the way premium sports apps do it. A nested-scroll connection eats the
    // first slice of upward scroll to resize the hero before the list moves; downward
    // scroll at the top grows it back.
    val density = LocalDensity.current
    val expandedPx = with(density) { 250.dp.toPx() }
    val collapsedPx = with(density) { 96.dp.toPx() }
    val heroHeight = remember { mutableFloatStateOf(expandedPx) }
    // Each tab starts expanded, so a short tab (few items, can't scroll) never gets
    // stuck collapsed with no way to bring the hero back.
    LaunchedEffect(tab) { heroHeight.floatValue = expandedPx }
    val connection = remember(expandedPx, collapsedPx) {
        object : NestedScrollConnection {
            override fun onPreScroll(available: Offset, source: NestedScrollSource): Offset {
                val delta = available.y
                if (delta >= 0f) return Offset.Zero          // grow handled post-scroll
                val newH = (heroHeight.floatValue + delta).coerceIn(collapsedPx, expandedPx)
                val used = newH - heroHeight.floatValue
                heroHeight.floatValue = newH
                return Offset(0f, used)
            }
            override fun onPostScroll(consumed: Offset, available: Offset, source: NestedScrollSource): Offset {
                val delta = available.y
                if (delta <= 0f) return Offset.Zero          // shrink handled pre-scroll
                val newH = (heroHeight.floatValue + delta).coerceIn(collapsedPx, expandedPx)
                val used = newH - heroHeight.floatValue
                heroHeight.floatValue = newH
                return Offset(0f, used)
            }
        }
    }
    val progress = ((expandedPx - heroHeight.floatValue) / (expandedPx - collapsedPx)).coerceIn(0f, 1f)

    Column(modifier = modifier.fillMaxSize().background(Bg).nestedScroll(connection)) {
        CollapsingFootballHero(
            state, football,
            heightDp = with(density) { heroHeight.floatValue.toDp() },
            progress = progress,
        )

        // Segmented tab bar with a single underline that glides between tabs — the
        // motion is what reads "expensive". Each tap presses in with a haptic.
        Box(Modifier.fillMaxWidth().background(Surface)) {
            Column {
                Row(Modifier.fillMaxWidth()) {
                    tabs.forEachIndexed { index, label ->
                        val active = index == tab
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .pressable { tab = index }
                                .padding(top = 14.dp, bottom = 12.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                label,
                                fontSize = 13.5.sp,
                                fontWeight = if (active) FontWeight.Bold else FontWeight.Medium,
                                color = if (active) Blue else Muted,
                            )
                        }
                    }
                }
                // Gliding indicator on its own 2.5dp track.
                BoxWithConstraints(Modifier.fillMaxWidth().height(2.5.dp)) {
                    val slot = maxWidth / tabs.size
                    val indW = 26.dp
                    val pos by animateDpAsState(
                        targetValue = slot * tab + (slot - indW) / 2,
                        animationSpec = tween(300, easing = CubicBezierEasing(0.2f, 0f, 0f, 1f)),
                        label = "fbTabSlide",
                    )
                    Box(
                        Modifier
                            .offset(x = pos)
                            .width(indW)
                            .fillMaxHeight()
                            .background(Blue, RoundedCornerShape(2.dp)),
                    )
                }
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))

        // Crossfade tab content so a switch dissolves rather than hard-cutting.
        Box(Modifier.weight(1f)) {
            androidx.compose.animation.Crossfade(targetState = tab, label = "fbTabContent") { t ->
                when (t) {
                    0 -> SummaryTab(state, football, onOpenStats = { tab = 1 })
                    1 -> StatsTab(state, football)
                    2 -> TimelineTab(state, football)
                    else -> LineupsTab(state)
                }
            }
        }
    }
}

/* ------------------------------------------------------------------ hero */

@Composable
private fun CollapsingFootballHero(
    state: MatchUiState,
    football: FootballState,
    heightDp: androidx.compose.ui.unit.Dp,
    progress: Float,   // 0 = fully expanded, 1 = fully collapsed
) {
    val homeScore = football.timeline.lastOrNull()?.homeScore ?: 0
    val awayScore = football.timeline.lastOrNull()?.awayScore ?: 0
    // Full content fades out over the first ~65% of the collapse; the compact bar fades
    // in over the last ~55%, so they cross cleanly with no empty frame.
    val fullAlpha = (1f - progress * 1.55f).coerceIn(0f, 1f)
    val compactAlpha = ((progress - 0.45f) / 0.55f).coerceIn(0f, 1f)

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(heightDp)
            .shadow(14.dp, RoundedCornerShape(bottomStart = 26.dp, bottomEnd = 26.dp), spotColor = HeroBottom)
            .clip(RoundedCornerShape(bottomStart = 26.dp, bottomEnd = 26.dp))
            .background(Brush.verticalGradient(listOf(HeroTop, HeroBottom)))
            .background(
                Brush.radialGradient(
                    colors = listOf(Color.White.copy(alpha = 0.10f), Color.Transparent),
                    radius = 620f,
                )
            ),
    ) {
        // ── Full scoreboard (expanded) — parallaxes up + fades as it collapses. ──
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .graphicsLayer { alpha = fullAlpha; translationY = -progress * 60f }
                .statusBarsPadding()
                .padding(horizontal = 16.dp)
                .padding(top = 8.dp, bottom = 20.dp),
        ) {
            Text(
                state.footballFormatLabel(),
                fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = OnHeroDim,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(16.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                HeroTeam(state.team1FullName.ifBlank { state.team1 }, state.team1Logo, Modifier.weight(1f))
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    modifier = Modifier.padding(horizontal = 8.dp),
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        AnimatedScore(homeScore, bright = homeScore >= awayScore)
                        Text("–", fontSize = 30.sp, fontWeight = FontWeight.Bold, color = OnHeroDim, modifier = Modifier.padding(horizontal = 8.dp))
                        AnimatedScore(awayScore, bright = awayScore >= homeScore)
                    }
                    Spacer(Modifier.height(8.dp))
                    HeroClock(football.clockLabel(state.isLive), state.isLive)
                }
                HeroTeam(state.team2FullName.ifBlank { state.team2 }, state.team2Logo, Modifier.weight(1f))
            }
            GoalTicker(state, football)
        }

        // ── Compact bar (collapsed) — mini crests + scoreline, pinned under the status
        // bar. Invisible until the hero is well into its collapse. ──
        if (compactAlpha > 0f) {
            Row(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .height(52.dp)
                    .padding(horizontal = 16.dp)
                    .graphicsLayer { alpha = compactAlpha },
                verticalAlignment = Alignment.CenterVertically,
            ) {
                TeamLogo(state.team1, state.team1Logo, Modifier.size(26.dp))
                Spacer(Modifier.width(8.dp))
                Text(state.team1, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = OnHero, maxLines = 1)
                Spacer(Modifier.weight(1f))
                Text("$homeScore", fontSize = 19.sp, fontWeight = FontWeight.Black, color = OnHero)
                Text("–", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = OnHeroDim, modifier = Modifier.padding(horizontal = 6.dp))
                Text("$awayScore", fontSize = 19.sp, fontWeight = FontWeight.Black, color = OnHero)
                if (state.isLive) {
                    Spacer(Modifier.width(8.dp))
                    Box(Modifier.size(6.dp).clip(CircleShape).background(Red))
                }
                Spacer(Modifier.weight(1f))
                Text(state.team2, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = OnHero, maxLines = 1, textAlign = TextAlign.End)
                Spacer(Modifier.width(8.dp))
                TeamLogo(state.team2, state.team2Logo, Modifier.size(26.dp))
            }
        }
    }
}

/**
 * A scrolling ticker of goals inside the hero. Each entry names the team credited
 * with the goal (the opponent for an own goal), the scorer and the minute. It
 * marquees continuously so a long list of goals stays on one line.
 */
@Composable
private fun GoalTicker(state: MatchUiState, football: FootballState) {
    val goals = football.goals
    if (goals.isEmpty()) return
    Spacer(Modifier.height(14.dp))
    Box(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Color.White.copy(alpha = 0.10f))
            .padding(vertical = 8.dp)
            // Fade the scrolling text out at both edges instead of hard-clipping it at
            // the band's rounded corners — the polished ticker look.
            .graphicsLayer { compositingStrategy = androidx.compose.ui.graphics.CompositingStrategy.Offscreen }
            .drawWithContent {
                drawContent()
                val fade = 24.dp.toPx()
                drawRect(
                    brush = Brush.horizontalGradient(listOf(Color.Transparent, Color.Black), startX = 0f, endX = fade),
                    blendMode = androidx.compose.ui.graphics.BlendMode.DstIn,
                )
                drawRect(
                    brush = Brush.horizontalGradient(listOf(Color.Black, Color.Transparent), startX = size.width - fade, endX = size.width),
                    blendMode = androidx.compose.ui.graphics.BlendMode.DstIn,
                )
            },
    ) {
        Row(
            modifier = Modifier.basicMarquee().padding(horizontal = 18.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            goals.forEachIndexed { i, g ->
                // An own goal is credited to the OTHER side; a normal goal to the scorer's.
                val creditedHome = if (g.kind == "own_goal") !g.isHome else g.isHome
                val teamShort = if (creditedHome) state.team1 else state.team2
                Icon(Icons.Filled.SportsSoccer, null, tint = OnHero, modifier = Modifier.size(13.dp))
                Spacer(Modifier.width(6.dp))
                Text(
                    buildString {
                        append(teamShort); append("  ")
                        append(g.player ?: "Goal")
                        g.minuteLabel?.let { append("  "); append(it) }
                        if (g.kind == "own_goal") append(" (OG)")
                    },
                    fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = OnHero, maxLines = 1,
                )
                if (i != goals.lastIndex) {
                    Text("   •   ", fontSize = 12.sp, color = OnHeroDim)
                }
            }
        }
    }
}

/**
 * A hero score digit that counts up to its value on entry and springs a scale "pop"
 * when a new goal changes it — so a goal *feels* like it happened, not just appeared.
 */
@Composable
private fun AnimatedScore(score: Int, bright: Boolean) {
    // Start the count from 0 on first show, then chase the real score.
    var target by remember { mutableIntStateOf(0) }
    LaunchedEffect(score) { target = score }
    val shown by animateIntAsState(
        targetValue = target,
        animationSpec = tween(700, easing = FastOutSlowInEasing),
        label = "scoreCount",
    )

    // Pop only on an actual change (a goal), not on the initial mount.
    val pop = remember { Animatable(1f) }
    var prev by remember { mutableIntStateOf(score) }
    LaunchedEffect(score) {
        if (score != prev) {
            prev = score
            pop.snapTo(1.35f)
            pop.animateTo(1f, spring(dampingRatio = 0.42f, stiffness = 520f))
        } else {
            prev = score
        }
    }

    Text(
        "$shown",
        fontSize = 42.sp,
        fontWeight = FontWeight.Black,
        // Trailing side is a softened white (not the blue-grey, which read as "disabled").
        color = if (bright) OnHero else OnHero.copy(alpha = 0.55f),
        modifier = Modifier.graphicsLayer { scaleX = pop.value; scaleY = pop.value },
    )
}

@Composable
private fun HeroTeam(name: String, logo: String, modifier: Modifier) {
    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        // Crest on a soft halo ring — gives the badge physical presence on the dark hero.
        Box(
            Modifier
                .size(66.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.07f))
                .border(1.dp, Color.White.copy(alpha = 0.14f), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            TeamLogo(team = name, logoUrl = logo, modifier = Modifier.size(54.dp))
        }
        Spacer(Modifier.height(9.dp))
        Text(
            name,
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = OnHero,
            textAlign = TextAlign.Center,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun HeroClock(label: String, isLive: Boolean) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(if (isLive) Red else Color.White.copy(alpha = 0.16f))
            .padding(horizontal = 12.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (isLive) {
            val pulse = rememberInfiniteTransition(label = "clockPulse")
            val a by pulse.animateFloat(
                initialValue = 0.35f, targetValue = 1f,
                animationSpec = infiniteRepeatable(tween(760), RepeatMode.Reverse),
                label = "dot",
            )
            Box(Modifier.size(6.dp).clip(CircleShape).background(Color.White.copy(alpha = a)))
            Spacer(Modifier.width(6.dp))
        }
        Text(label, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = OnHero)
    }
}

/* --------------------------------------------------------------- summary */

@Composable
private fun SummaryTab(state: MatchUiState, football: FootballState, onOpenStats: () -> Unit) {
    val homeScore = football.timeline.lastOrNull()?.homeScore ?: 0
    val awayScore = football.timeline.lastOrNull()?.awayScore ?: 0
    val stats = football.stats
    // The three headline stats to preview here (the full set lives in the Stats tab).
    val previewRows = stats?.takeIf { it.hasAny }?.groups
        ?.flatMap { it.rows }
        ?.filter { it.label in setOf("Total shots", "Shots on target", "Corners") }
        .orEmpty()

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        // ── Result headline — the goals bar. ──
        item {
            Card {
                CardTitle("Result")
                Spacer(Modifier.height(14.dp))
                StatBar("Goals", homeScore, awayScore, state.team1Color, state.team2Color)
            }
        }

        // ── Goals ──
        item {
            Card {
                CardTitle("Goals")
                Spacer(Modifier.height(10.dp))
                val goals = football.goals
                if (goals.isEmpty()) {
                    Text("No goals yet.", fontSize = 13.5.sp, color = Muted)
                } else {
                    goals.forEachIndexed { i, g ->
                        GoalRow(g, state)
                        if (i != goals.lastIndex) Spacer(Modifier.height(8.dp))
                    }
                }
            }
        }

        // ── Match stats preview — top three, with a tap-through to the full Stats tab.
        // Fills the summary with real substance and signposts where the rest lives. ──
        if (previewRows.isNotEmpty()) {
            item {
                Card {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        CardTitle("Match stats")
                        Spacer(Modifier.weight(1f))
                        Text(
                            "View all",
                            fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Blue,
                            modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onOpenStats).padding(4.dp),
                        )
                    }
                    Spacer(Modifier.height(14.dp))
                    previewRows.forEachIndexed { i, r ->
                        StatBar(r.label, r.home, r.away, state.team1Color, state.team2Color)
                        if (i != previewRows.lastIndex) Spacer(Modifier.height(15.dp))
                    }
                }
            }
        }

        // ── Match info ──
        item {
            Card {
                CardTitle("Match info")
                Spacer(Modifier.height(10.dp))
                if (state.venue.isNotBlank()) { InfoRow("Venue", state.venue); Spacer(Modifier.height(8.dp)) }
                InfoRow("Format", state.footballFormatLabel()); Spacer(Modifier.height(8.dp))
                InfoRow("Status", if (state.isLive) "In play" else state.status.ifBlank { "Full time" })
            }
        }
    }
}

/* ----------------------------------------------------------------- stats */

/**
 * The head-to-head stats section, grouped Attacking / Discipline / Defence like a
 * broadcast stats page. Every bar is a real tally of recorded events — no possession
 * or xG, because a manual scorer can't produce those honestly. Empty until the scorer
 * has tracked at least one counting stat.
 */
@Composable
private fun StatsTab(state: MatchUiState, football: FootballState) {
    val stats = football.stats
    if (stats == null || !stats.hasAny) {
        EmptyNote(
            "No match stats yet",
            "Shots, corners, fouls, offsides and more appear here as the scorer records them.",
        )
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item { StatsCrestHeader(state) }
        stats.groups.forEach { group ->
            if (group.rows.isEmpty()) return@forEach
            item {
                Card {
                    Text(
                        group.title.uppercase(),
                        fontSize = 12.sp, fontWeight = FontWeight.Black, color = Faint,
                        letterSpacing = 1.sp, textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    Spacer(Modifier.height(14.dp))
                    group.rows.forEachIndexed { i, r ->
                        StatBar(r.label, r.home, r.away, state.team1Color, state.team2Color)
                        if (i != group.rows.lastIndex) Spacer(Modifier.height(15.dp))
                    }
                }
            }
        }
    }
}

/** The two teams' crests + names, anchoring the columns of the stats bars beneath. */
@Composable
private fun StatsCrestHeader(state: MatchUiState) {
    Card {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                TeamLogo(state.team1, state.team1Logo, Modifier.size(30.dp))
                Spacer(Modifier.width(9.dp))
                Text(state.team1, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Text("STATS", fontSize = 11.sp, fontWeight = FontWeight.Black, color = Faint, letterSpacing = 1.sp)
            Row(Modifier.weight(1f), horizontalArrangement = Arrangement.End, verticalAlignment = Alignment.CenterVertically) {
                Text(state.team2, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Spacer(Modifier.width(9.dp))
                TeamLogo(state.team2, state.team2Logo, Modifier.size(30.dp))
            }
        }
    }
}

/** A home-vs-away comparison bar: values at the ends, an animated split fill between. */
@Composable
private fun StatBar(label: String, home: Int, away: Int, homeColor: Color, awayColor: Color) {
    val total = (home + away).coerceAtLeast(1)
    // Grow the split from centre on first show — a small motion that makes the numbers
    // feel measured rather than printed. Keyed on the values so it re-animates on change.
    var shown by remember(home, away) { mutableIntStateOf(0) }
    val grow by animateFloatAsState(
        targetValue = if (shown == 0) 0f else 1f,
        animationSpec = tween(600, easing = CubicBezierEasing(0.2f, 0f, 0f, 1f)),
        label = "statGrow",
    )
    androidx.compose.runtime.LaunchedEffect(home, away) { shown = 1 }
    val leadHome = home > away
    val leadAway = away > home

    Row(verticalAlignment = Alignment.CenterVertically) {
        Text("$home", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = if (leadHome) homeColor else Ink, modifier = Modifier.width(28.dp))
        Column(Modifier.weight(1f)) {
            Text(label, fontSize = 12.sp, color = Muted, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(5.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .height(7.dp)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Color(0xFFEDF1F6)),
            ) {
                val hw = (home.toFloat() / total).coerceIn(0.02f, 0.98f) * grow + 0.0001f
                Box(Modifier.weight(hw).fillMaxHeight().background(homeColor))
                Spacer(Modifier.width(2.dp))
                Box(Modifier.weight((1f - hw)).fillMaxHeight().background(awayColor.copy(alpha = 0.85f)))
            }
        }
        Text("$away", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = if (leadAway) awayColor else Ink, textAlign = TextAlign.End, modifier = Modifier.width(28.dp))
    }
}

@Composable
private fun GoalRow(g: FootballEvent, state: MatchUiState) {
    val accent = if (g.kind == "own_goal") Red else if (g.isHome) state.team1Color else state.team2Color
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(26.dp).clip(CircleShape).background(accent.copy(alpha = 0.14f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.SportsSoccer, null, tint = accent, modifier = Modifier.size(15.dp))
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                (g.player ?: "Goal") + if (g.kind == "own_goal") " (OG)" else "",
                fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            g.related?.takeIf { g.kind == "goal" }?.let {
                Text("assist $it", fontSize = 11.sp, color = Faint, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        g.scoreLabel?.let {
            Text(it, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Muted, modifier = Modifier.padding(end = 8.dp))
        }
        Text(g.minuteLabel ?: "", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = accent)
    }
}

/* -------------------------------------------------------------- timeline */

/**
 * The centrepiece: a vertical match timeline on a central spine. Home events sit left,
 * away events right, the minute rides the spine, and goals carry a running score chip.
 * Kick-off and full-time bookend it so the flow of the match reads top to bottom.
 */
@Composable
private fun TimelineTab(state: MatchUiState, football: FootballState) {
    if (football.timeline.isEmpty()) {
        EmptyNote(
            "Nothing has happened yet",
            "Goals, cards and substitutions appear here the moment the scorer records them.",
        )
        return
    }

    // Newest first — the thing that just happened is why you opened the screen.
    val rows = football.timeline.reversed()

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(vertical = 16.dp),
    ) {
        if (!state.isLive) {
            item { PhaseMarker("Full time") }
        }
        itemsIndexed(rows, key = { _, e -> e.sequence }) { _, event ->
            TimelineRow(event, state)
        }
        item { PhaseMarker("Kick-off") }
    }
}

@Composable
private fun PhaseMarker(label: String) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 8.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.weight(1f).height(1.dp).background(Hairline))
        Text(
            label,
            fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Faint,
            modifier = Modifier
                .padding(horizontal = 10.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(Surface)
                .border(1.dp, Hairline, RoundedCornerShape(20.dp))
                .padding(horizontal = 12.dp, vertical = 4.dp),
        )
        Box(Modifier.weight(1f).height(1.dp).background(Hairline))
    }
}

@Composable
private fun TimelineRow(event: FootballEvent, state: MatchUiState) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Left — home events.
        Box(Modifier.weight(1f).padding(end = 6.dp), contentAlignment = Alignment.CenterEnd) {
            if (event.isHome) EventChip(event, state, alignEnd = true)
        }

        // Central spine with the minute badge.
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(48.dp)) {
            Box(Modifier.width(2.dp).height(10.dp).background(Hairline))
            Box(
                Modifier
                    .clip(CircleShape)
                    .background(Bg)
                    .border(1.5.dp, Hairline, CircleShape)
                    .padding(horizontal = 7.dp, vertical = 3.dp),
            ) {
                Text(event.minuteLabel ?: "·", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Muted)
            }
            Box(Modifier.width(2.dp).height(10.dp).background(Hairline))
        }

        // Right — away events.
        Box(Modifier.weight(1f).padding(start = 6.dp), contentAlignment = Alignment.CenterStart) {
            if (!event.isHome && event.side != null) EventChip(event, state, alignEnd = false)
        }
    }
}

@Composable
private fun EventChip(event: FootballEvent, state: MatchUiState, alignEnd: Boolean) {
    val teamColor = if (event.isHome) state.team1Color else state.team2Color
    val label = when (event.kind) {
        "goal" -> "Goal"; "own_goal" -> "Own goal"
        "yellow" -> "Yellow card"; "red" -> "Red card"
        "sub" -> "Substitution"
        else -> event.kind.replaceFirstChar { it.uppercase() }
    }
    val accent = when (event.kind) {
        "goal" -> teamColor; "own_goal" -> Red
        "yellow" -> Amber; "red" -> Red; else -> Muted
    }

    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .padding(horizontal = 11.dp, vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (alignEnd) {
            EventText(event, label, accent, alignEnd = true)
            Spacer(Modifier.width(9.dp))
            EventGlyph(event.kind, accent)
        } else {
            EventGlyph(event.kind, accent)
            Spacer(Modifier.width(9.dp))
            EventText(event, label, accent, alignEnd = false)
        }
    }
}

/** The icon for an event — vector glyphs (not emoji): ball for goals, a card rect, swap for subs. */
@Composable
private fun EventGlyph(kind: String, accent: Color) {
    when (kind) {
        "yellow", "red" -> Box(
            Modifier.size(width = 13.dp, height = 18.dp).clip(RoundedCornerShape(3.dp)).background(accent)
        )
        "sub" -> Icon(Icons.Filled.SwapHoriz, null, tint = accent, modifier = Modifier.size(20.dp))
        else -> Box(
            Modifier.size(26.dp).clip(CircleShape).background(accent.copy(alpha = 0.14f)),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.SportsSoccer, null, tint = accent, modifier = Modifier.size(15.dp)) }
    }
}

@Composable
private fun EventText(event: FootballEvent, label: String, accent: Color, alignEnd: Boolean) {
    Column(
        horizontalAlignment = if (alignEnd) Alignment.End else Alignment.Start,
        modifier = Modifier.wrapContentWidth(),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(label, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = accent)
            event.scoreLabel?.takeIf { event.kind == "goal" || event.kind == "own_goal" }?.let {
                Spacer(Modifier.width(6.dp))
                Text(
                    it, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = Ink,
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(Bg)
                        .padding(horizontal = 5.dp, vertical = 1.dp),
                )
            }
        }
        Spacer(Modifier.height(2.dp))
        Text(
            event.player ?: event.headline,
            fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = Ink,
            textAlign = if (alignEnd) TextAlign.End else TextAlign.Start,
            maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
        event.related?.let {
            Text(
                if (event.kind == "sub") "for $it" else "assist $it",
                fontSize = 11.sp, color = Faint,
                textAlign = if (alignEnd) TextAlign.End else TextAlign.Start,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

/* --------------------------------------------------------------- lineups */

@Composable
private fun LineupsTab(state: MatchUiState) {
    if (state.homeSquad.isEmpty() && state.awaySquad.isEmpty()) {
        EmptyNote(
            "No line-ups recorded",
            "Squads added when the match was created will show here.",
        )
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item { SquadCard(state.team1FullName.ifBlank { state.team1 }, state.team1Logo, state.homeSquad, state.team1Color) }
        item { SquadCard(state.team2FullName.ifBlank { state.team2 }, state.team2Logo, state.awaySquad, state.team2Color) }
    }
}

@Composable
private fun SquadCard(team: String, logo: String, squad: List<SquadMember>, accent: Color) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp)),
    ) {
        // Colored header band with crest + name + count.
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(accent.copy(alpha = 0.10f))
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TeamLogo(team = team, logoUrl = logo, modifier = Modifier.size(28.dp))
            Spacer(Modifier.width(10.dp))
            Text(team, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text("${squad.size}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = accent)
        }
        if (squad.isEmpty()) {
            Text("No players recorded.", fontSize = 13.sp, color = Muted, modifier = Modifier.padding(14.dp))
        } else {
            squad.forEachIndexed { index, player ->
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        Modifier.size(28.dp).clip(CircleShape).background(accent.copy(alpha = 0.14f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text("${index + 1}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = accent)
                    }
                    Spacer(Modifier.width(12.dp))
                    Text(player.name, fontSize = 13.5.sp, color = Ink, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    if (player.isCaptain) RoleBadge("C", accent)
                    else if (player.isViceCaptain) RoleBadge("VC", Muted)
                }
                if (index != squad.lastIndex) {
                    Box(Modifier.fillMaxWidth().padding(start = 54.dp).height(1.dp).background(Hairline))
                }
            }
        }
    }
}

@Composable
private fun RoleBadge(text: String, color: Color) {
    Text(
        text,
        fontSize = 10.sp, fontWeight = FontWeight.Black, color = Color.White,
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(color)
            .padding(horizontal = 6.dp, vertical = 2.dp),
    )
}

/* ----------------------------------------------------------------- bits */

@Composable
private fun Card(content: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(4.dp, RoundedCornerShape(16.dp), spotColor = Color(0x22101828))
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(16.dp),
        content = content,
    )
}

@Composable
private fun CardTitle(text: String) {
    Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink)
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
        Text(label, fontSize = 13.sp, color = Muted)
        Text(
            value, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink,
            textAlign = TextAlign.End, modifier = Modifier.padding(start = 16.dp),
        )
    }
}

@Composable
private fun EmptyNote(title: String, body: String) {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = 32.dp, vertical = 56.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier.size(52.dp).clip(CircleShape).background(Bg),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.SportsSoccer, null, tint = Faint, modifier = Modifier.size(26.dp)) }
        Spacer(Modifier.height(14.dp))
        Text(title, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = Ink)
        Spacer(Modifier.height(6.dp))
        Text(body, fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center)
    }
}

/**
 * A football-safe format label. The `competition` field is sometimes a cricket string
 * ("20 Over Match") on older/mis-tagged football matches; never show overs on a
 * football screen — fall back to "Football" rather than leak cricket furniture.
 */
private fun MatchUiState.footballFormatLabel(): String {
    val c = competition.trim()
    return if (c.isBlank() || c.contains("over", ignoreCase = true)) "Football" else c
}
