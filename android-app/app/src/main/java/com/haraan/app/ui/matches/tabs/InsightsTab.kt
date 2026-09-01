package com.haraan.app.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.ui.draw.shadow
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.Canvas
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.pager.HorizontalPager
import kotlinx.coroutines.launch
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.ui.graphics.lerp
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.Path
import com.haraan.app.data.ChangingOver
import com.haraan.app.data.FaceOff
import com.haraan.app.data.InningsInsight
import com.haraan.app.data.ProgressOver
import com.haraan.app.data.ScoringBreakdown
import com.haraan.app.data.Stand
import com.haraan.app.data.MatchInsights
import com.haraan.app.data.MatchRepository
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchUiState

/**
 * Insights — what the ball log says about how this match went.
 *
 * The screen is deliberately in two halves, and the split is the point:
 *
 *  · The FIGURES are computed on the server by replaying every delivery. They are the same
 *    arithmetic as the scorecard, they are correct whether or not any model is reachable,
 *    and they are what the tab is really for.
 *  · The WRITTEN READ is a model's take on those figures. It is clearly labelled as such,
 *    it sits BELOW the numbers rather than above them, and its absence costs nothing — the
 *    tab is complete without it.
 *
 * That ordering is not decoration. Put generated prose on top and it becomes the thing the
 * reader trusts; put it under the numbers it describes and it stays what it is — a summary
 * of facts already on screen, checkable against them.
 */
@Composable
fun InsightsTab(matchId: String, state: MatchUiState, modifier: Modifier = Modifier) {
    var insights by remember(matchId) { mutableStateOf<MatchInsights?>(null) }
    var ground by remember(matchId) { mutableStateOf<com.haraan.app.data.GroundInsights?>(null) }
    var loading by remember(matchId) { mutableStateOf(true) }

    // Fetched once, not with the score: a ground's record does not change ball to ball,
    // and re-pulling a satellite tile every over would be a waste of the match's data.
    LaunchedEffect(matchId) {
        ground = MatchRepository().fetchGround(matchId)
    }

    // Re-fetched when the score moves, so a live match's insights follow the match rather
    // than freezing at whatever the state was when the tab first opened.
    LaunchedEffect(matchId, state.score, state.overs) {
        loading = insights == null
        insights = MatchRepository().fetchInsights(matchId)
        loading = false
    }

    val data = insights
    if (data == null || data.innings.isEmpty()) {
        // The ground still has something to say about a match nobody has scored yet —
        // where it is, and what has happened there before.
        val known = ground
        if (known != null) {
            LazyColumn(
                modifier = modifier
                    .fillMaxSize()
                    .background(CrexColors.Background)
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
                contentPadding = PaddingValues(top = 14.dp, bottom = 28.dp),
            ) {
                item(key = "ground") { GroundInsightsCard(known) }
            }
            return
        }
        InsightsPlaceholder(loading = loading, modifier = modifier)
        return
    }

    // Survives rotation and a trip to another tab: a reader who opened the detail did
    // so deliberately, and having it snap shut underneath them is its own small betrayal.
    var showAll by rememberSaveable(matchId) { mutableStateOf(false) }

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Surface)
            .padding(horizontal = 18.dp),
        // Sections carry their own rule and rhythm now, so the list does not add a
        // uniform gap between everything.
        verticalArrangement = Arrangement.spacedBy(18.dp),
        contentPadding = PaddingValues(top = 4.dp, bottom = 36.dp),
    ) {
        // Both innings on one axis — a chase only means anything against its target.
        if (data.innings.size > 1) {
            item(key = "worm") {
                InsightSection("MATCH PROGRESS") {
                    Worm(
                        innings = data.innings,
                        colours = listOf(state.team1Color, state.team2Color),
                    )
                }
            }
        }

        // Both sides on the hero, one swipe apart.
        //
        // The hero showed whoever happened to be batting and there was no way to reach
        // the other side without scrolling past every chart of the first innings — on a
        // screen whose whole subject is two teams, that reads as half a product. The two
        // cards are now pages of one surface, and each carries its OWN colour, so moving
        // between them feels like changing ends rather than reloading the same card.
        item(key = "hero") { InningsHeroPager(data.innings, state) }

        // CRICKET IQ, immediately under the hero.
        //
        // The hero says what the TEAM did; this says what a PERSON did inside it, and that
        // is the question a player actually opens this tab with. Everything below the two
        // of them is the evidence for one or the other.
        item(key = "iq") { CricketIqSection(matchId, MatchRepository()) }

        // WHERE IT WENT, and WHERE IT WAS PLAYED.
        //
        // These two survive above the fold because each answers something the figures
        // cannot: the wheel says which parts of the ground this side scored through, and
        // the ground card says whether a total like this one is big for here.
        data.innings.forEachIndexed { idx, inn ->
            val accent = if (inn.battingTeam == 2) state.team2Color else state.team1Color

            if (data.innings.size > 1) {
                item(key = "inn-label-$idx") { InningsLabel(inn, accent) }
            }

            item(key = "wagon-$idx") {
                InsightSection("WAGON WHEEL") { WagonWheel(inn) }
            }

            // The ground belongs to the match rather than to a side, so it appears once.
            if (idx == 0) {
                ground?.let { known ->
                    item(key = "ground") { GroundInsightsCard(known, thisInnings = inn.runs) }
                }
            }
        }

        // FULL BREAKDOWN.
        //
        // Everything past this line is detail. It is complete and it is correct, and it
        // is not what the page is ABOUT — and that distinction is the one this tab was
        // missing. Thirteen sections at identical weight gave a reader no way to tell the
        // story from the evidence for it: every block announced itself with the same grey
        // label and the same hairline, so nothing led and the page read as a list that a
        // machine had emitted rather than a page somebody had edited.
        //
        // Nothing is deleted to fix that. The evidence simply stops competing with the
        // story for the top of the screen, and anyone who wants all of it is one tap away.
        item(key = "disclosure") {
            BreakdownDisclosure(open = showAll) { showAll = !showAll }
        }

        if (showAll) {
            if (state.homeSquad.isNotEmpty() || state.awaySquad.isNotEmpty()) {
                item(key = "form") {
                    AnalyseSection(
                        team1Name = state.team1FullName.ifBlank { state.team1 },
                        team2Name = state.team2FullName.ifBlank { state.team2 },
                        team1Squad = state.homeSquad,
                        team2Squad = state.awaySquad,
                    )
                }
            }

            data.innings.forEachIndexed { idx, inn ->
                if (inn.progress.isNotEmpty()) {
                    item(key = "strip-$idx") {
                        InsightSection("BALL BY BALL") { OverStrip(inn) }
                    }
                }
                if (inn.changingOvers.isNotEmpty()) {
                    item(key = "changing-$idx") { ChangingOversCard(inn) }
                }
                if (inn.partnerships.isNotEmpty()) {
                    item(key = "stands-$idx") { PartnershipsCard(inn) }
                }
                if (inn.faceoffs.isNotEmpty()) {
                    item(key = "faceoff-$idx") { FaceOffCard(inn) }
                }
                // No ScoringBreakdownCard: the hero's "How the runs were made" already
                // prints every one of its figures — the sixes, the fours, and the
                // singles, twos, threes and extras by name. It was the same table twice.
            }

            data.analysis?.let { text ->
                item(key = "analysis") { AnalysisCard(text) }
            }
        }

        item(key = "footnote") {
            Text(
                "Figures are replayed from the same ball-by-ball log as the scorecard.",
                color = CrexColors.TextMuted,
                fontSize = 10.5.sp,
                modifier = Modifier.padding(horizontal = 2.dp, vertical = 2.dp),
            )
        }
    }
}

/**
 * Whose charts these are, on one quiet line.
 *
 * Only drawn when there are two innings to tell apart — a single-innings match has
 * already said whose it is at the top of the screen, and repeating it there would be a
 * label with no job.
 */
@Composable
private fun InningsLabel(inn: InningsInsight, accent: Color) {
    Row(
        Modifier.fillMaxWidth().padding(top = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(width = 3.dp, height = 15.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(accent),
        )
        Spacer(Modifier.width(9.dp))
        Text(
            inn.battingName.uppercase(),
            color = CrexColors.TextPrimary,
            fontSize = 12.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.1.sp,
            modifier = Modifier.weight(1f),
        )
        Text(
            "${inn.runs}/${inn.wickets}  ·  ${inn.overs} ov",
            color = CrexColors.TextMuted,
            fontSize = 12.sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}

/**
 * The line that divides the story from the evidence for it.
 *
 * Deliberately not a button. A filled pill or an outlined control would be a fifteenth
 * object competing for attention on a page whose whole problem was too many objects at
 * equal weight — so this is set as a rule, a label and a drawn mark, in the same
 * typographic language as every other section head, and the whole row is the target.
 *
 * The mark is drawn rather than an icon: two strokes that become one when the section is
 * open, which is the plainest possible statement of what tapping did.
 */
@Composable
private fun BreakdownDisclosure(open: Boolean, onToggle: () -> Unit) {
    val cross by animateFloatAsState(
        if (open) 0f else 1f,
        tween(220),
        label = "disclosureCross",
    )
    Column(
        Modifier
            .fillMaxWidth()
            .clickable(
                indication = null,
                interactionSource = remember { MutableInteractionSource() },
            ) { onToggle() }
            .padding(top = 10.dp, bottom = 4.dp),
    ) {
        Box(Modifier.fillMaxWidth().height(1.5.dp).background(InsightInk.copy(alpha = 0.16f)))
        Spacer(Modifier.height(18.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    "FULL BREAKDOWN",
                    color = InsightInk,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.5.sp,
                )
                Spacer(Modifier.height(5.dp))
                Text(
                    if (open) {
                        "Tap to close"
                    } else {
                        "Ball by ball, every stand, the face-offs, form and the written read"
                    },
                    color = CrexColors.TextMuted,
                    fontSize = 12.5.sp,
                    lineHeight = 18.sp,
                )
            }
            Spacer(Modifier.width(16.dp))
            Canvas(Modifier.size(18.dp)) {
                val mid = size.height / 2f
                val w = 1.8.dp.toPx()
                drawLine(
                    color = InsightInk,
                    start = Offset(0f, mid),
                    end = Offset(size.width, mid),
                    strokeWidth = w,
                    cap = StrokeCap.Round,
                )
                if (cross > 0.01f) {
                    val half = (size.height / 2f) * cross
                    drawLine(
                        color = InsightInk,
                        start = Offset(size.width / 2f, mid - half),
                        end = Offset(size.width / 2f, mid + half),
                        strokeWidth = w,
                        cap = StrokeCap.Round,
                    )
                }
            }
        }
    }
}

/**
 * The hero, as two pages — one per side.
 *
 * A side with no innings yet still gets a page. Leaving it out would mean the swipe
 * silently does nothing for most of a live match, which is worse than a card that says
 * plainly that this team has not batted.
 */
@Composable
private fun InningsHeroPager(innings: List<InningsInsight>, state: MatchUiState) {
    data class Side(val team: Int, val name: String, val short: String, val colour: Color)

    val sides = listOf(
        Side(1, state.team1FullName.ifBlank { state.team1 }, state.team1, state.team1Color),
        Side(2, state.team2FullName.ifBlank { state.team2 }, state.team2, state.team2Color),
    )
    // Opens on whoever is actually playing, not always on the home side — and never on a
    // side that has not batted, which would greet the reader with an empty card while the
    // innings they came to see sits one swipe away.
    val live = (innings.lastOrNull { it.runs > 0 } ?: innings.lastOrNull())?.battingTeam
    val opening = sides.indexOfFirst { side -> side.team == live }.coerceAtLeast(0)
    val pager = rememberPagerState(initialPage = opening) { sides.size }
    val scope = rememberCoroutineScope()

    Column(Modifier.fillMaxWidth()) {
        // Two sides, one rule.
        //
        // The tabs sit ON a continuous hairline rather than each carrying its own bar, so
        // the selected side reads as a mark made on a single line — the way a printed
        // fixture list marks the side it is talking about. The underline is the interaction
        // colour, not the team's: a team colour here would compete with the same colour
        // three lines down, where it is identifying the innings rather than the selection.
        Box(Modifier.fillMaxWidth().padding(bottom = 20.dp)) {
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(1.dp)
                    .align(Alignment.BottomStart)
                    .background(InsightRule),
            )
            Row(Modifier.fillMaxWidth()) {
                sides.forEachIndexed { i, side ->
                    val on = pager.currentPage == i
                    val fg by animateColorAsState(
                        if (on) InsightInk else CrexColors.TextMuted,
                        tween(220),
                        label = "sideFg",
                    )
                    val rule by animateColorAsState(
                        if (on) CrexColors.AccentBlue else Color.Transparent,
                        tween(220),
                        label = "sideRule",
                    )
                    Column(
                        Modifier
                            .weight(1f)
                            .clickable(
                                indication = null,
                                interactionSource = remember { MutableInteractionSource() },
                            ) { scope.launch { pager.animateScrollToPage(i) } },
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(
                            side.name,
                            color = fg,
                            fontSize = 15.sp,
                            fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                            maxLines = 1,
                        )
                        Spacer(Modifier.height(12.dp))
                        Box(Modifier.fillMaxWidth().height(2.dp).background(rule))
                    }
                }
            }
        }

        HorizontalPager(state = pager, modifier = Modifier.fillMaxWidth()) { page ->
            val side = sides[page]
            val inn = innings.firstOrNull { it.battingTeam == side.team }
            if (inn != null) {
                InningsInsightCard(inn, side.colour)
            } else {
                YetToBatCard(side.name, side.colour)
            }
        }
    }
}

/**
 * A side that has not batted.
 *
 * Set in the same editorial type as a real innings rather than as an empty state, because
 * it is not an error — it is a fact about where the match has got to, and it will become a
 * scorecard shortly.
 */
@Composable
private fun YetToBatCard(name: String, colour: Color) {
    Column(Modifier.fillMaxWidth()) {
        InsightsEyebrow()
        Spacer(Modifier.height(18.dp))
        TeamRule(name, colour)
        Spacer(Modifier.height(10.dp))
        Text(
            "Yet to bat",
            color = InsightInk.copy(alpha = 0.35f),
            fontSize = 40.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-1.4).sp,
        )
        Spacer(Modifier.height(10.dp))
        Text(
            "Their innings will appear here ball by ball.",
            color = CrexColors.TextSecondary,
            fontSize = 14.sp,
            lineHeight = 21.sp,
        )
        Spacer(Modifier.height(34.dp))
    }
}

// ── The Insights palette ───────────────────────────────────────────────────────────────
//
// Deliberately short. Navy carries every number, one blue carries interaction, and the
// three phase hues exist only because START / MIDDLE / FINISH are three different things
// that have to be told apart inside a single bar. Nothing here is decorative: if a colour
// appears on this screen it is because it means something navy cannot.

private val InsightInk = Color(0xFF0B1B33)          // headline navy — every figure
private val InsightRule = Color(0xFFE6EBF2)         // hairline dividers
private val InsightTrack = Color(0xFFEFF3F8)        // unfilled bar track
private val PhaseStart = Color(0xFF2563EB)          // blue   — the opening
private val PhaseMiddle = Color(0xFF0D9488)         // teal   — the build
private val PhaseFinish = Color(0xFF7C3AED)         // purple — the close
private val BoundaryGreen = Color(0xFF15803D)       // positive: runs taken to the rope
private val PeakOrange = Color(0xFFEA580C)          // the single best over

private fun phaseColour(index: Int): Color = when (index) {
    0 -> PhaseStart
    1 -> PhaseMiddle
    else -> PhaseFinish
}

/** The section's own mark: a three-bar glyph and one word. Drawn, not an icon font. */
@Composable
private fun InsightsEyebrow() {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Canvas(Modifier.size(width = 12.dp, height = 10.dp)) {
            val w = size.width / 5f
            listOf(0.45f, 0.8f, 1f).forEachIndexed { i, h ->
                drawRect(
                    color = CrexColors.TextMuted,
                    topLeft = Offset(i * w * 1.7f, size.height * (1f - h)),
                    size = androidx.compose.ui.geometry.Size(w, size.height * h),
                )
            }
        }
        Spacer(Modifier.width(8.dp))
        Text(
            "INSIGHTS",
            color = CrexColors.TextMuted,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
    }
}

/** Whose innings this is: the side's own colour as a 3dp rule, then the name. */
@Composable
private fun TeamRule(name: String, colour: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .size(width = 3.dp, height = 13.dp)
                .clip(RoundedCornerShape(1.5.dp))
                .background(colour),
        )
        Spacer(Modifier.width(9.dp))
        Text(
            name.uppercase(),
            color = CrexColors.TextSecondary,
            fontSize = 11.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.3.sp,
        )
    }
}

/**
 * THE INNINGS — told as one page, not as a stack of cards.
 *
 * The old hero was a dark gradient slab with a turf horizon painted into it and four stat
 * cells along its foot, every figure competing at the same weight. It looked expensive and
 * said very little: you could not tell from it what KIND of innings this had been without
 * reading all four numbers and doing the arithmetic yourself.
 *
 * What replaces it is match analysis set the way a newspaper sets it. The score is the
 * largest thing on the screen because it is the most important thing on the screen. Under
 * it, one sentence says what kind of innings it was. Then the evidence, in the order a
 * reader asks for it: how much came in boundaries, when the runs came, the four figures
 * worth keeping, and what to take from it. Nothing sits inside a container — hierarchy is
 * size, weight and space, and sections are parted by a hairline rule.
 *
 * Every figure here is replayed from the ball log. Nothing on this screen is estimated.
 */
@Composable
private fun InningsInsightCard(inn: InningsInsight, accent: Color = CrexColors.AccentBlue) {
    val reveal = remember(inn.runs, inn.overs) { Animatable(0f) }
    LaunchedEffect(inn.runs, inn.overs) {
        reveal.animateTo(1f, tween(durationMillis = 900, easing = FastOutSlowInEasing))
    }
    val t = reveal.value

    Column(Modifier.fillMaxWidth()) {
        InsightsEyebrow()
        Spacer(Modifier.height(20.dp))
        TeamRule(inn.battingName, accent)
        Spacer(Modifier.height(6.dp))

        // The score, and only the score. Overs and run rate sit opposite it at a fraction
        // of the size — there for anyone who wants them, never competing for the eye.
        Row(verticalAlignment = Alignment.Bottom) {
            val shown = (inn.runs * ((t - 0.1f) / 0.6f).coerceIn(0f, 1f)).toInt()
            Text(
                "$shown",
                color = InsightInk,
                fontSize = 78.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-3.6).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(
                "/${inn.wickets}",
                color = InsightInk.copy(alpha = 0.3f),
                fontSize = 34.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                modifier = Modifier.padding(bottom = 7.dp),
            )
            Spacer(Modifier.weight(1f))
            Column(
                horizontalAlignment = Alignment.End,
                modifier = Modifier.padding(bottom = 10.dp),
            ) {
                Text(
                    "${inn.overs} OV",
                    color = InsightInk,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 0.4.sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    "RR ${inn.runRate}",
                    color = CrexColors.TextMuted,
                    fontSize = 11.5.sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }

        // The read: what kind of innings this was, in one line, chosen by rule from the
        // figures printed below it. The opening figure is set in navy so the eye catches
        // the claim and the number making it in the same movement.
        // The server's verified line when there is one, this screen's own rule-based
        // sentence when there is not. Both are readings of figures printed below them,
        // so both are set the same way — and neither is badged as coming from a model,
        // because a line that needs a badge to be believed has not earned its place.
        (inn.headline ?: inningsRead(inn))?.let { read ->
            Spacer(Modifier.height(12.dp))
            // Set at headline size, not body size. This sentence is the point of the
            // whole section — printed at 15sp under a 78sp score it read as a caption
            // apologising for the number above it.
            Text(
                emphasiseLeadingFigure(read),
                color = InsightInk.copy(alpha = 0.9f),
                fontSize = 21.sp,
                lineHeight = 30.sp,
                fontWeight = FontWeight.Medium,
            )
        }

        if (inn.runs > 0) {
            // The order is the order the questions get asked in: what shape was the
            // innings, what was it made of, who made it, and how it climbed.
            if (inn.progress.isNotEmpty()) InsightBlock(top = 26.dp) { InningsTimeline(inn, t) }
            InsightBlock(top = if (inn.progress.isEmpty()) 26.dp else 24.dp) { RunsMade(inn, t) }

            // The moment gets air instead of a rule. A hairline above a dark band would
            // be a seam between two surfaces; space lets the band arrive on its own.
            inn.partnerships.maxByOrNull { it.runs }?.let { stand ->
                Spacer(Modifier.height(30.dp))
                MomentBand(stand, inn.runs)
            }

            InsightBlock(top = 30.dp) { MilestoneStrip(inn) }
        }
    }
}

/** A rule, then air, then the block. The only thing that parts one section from the next. */
@Composable
private fun InsightBlock(top: Dp = 24.dp, content: @Composable ColumnScope.() -> Unit) {
    Spacer(Modifier.height(top))
    Box(Modifier.fillMaxWidth().height(1.dp).background(InsightRule))
    Spacer(Modifier.height(18.dp))
    Column(Modifier.fillMaxWidth(), content = content)
}

/** A small uppercase section head, with an optional figure sitting opposite it. */
@Composable
private fun BlockHead(label: String, trailing: String? = null) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(
            label,
            color = CrexColors.TextMuted,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
            modifier = Modifier.weight(1f),
        )
        trailing?.let {
            Text(
                it,
                color = CrexColors.TextMuted,
                fontSize = 10.5.sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
    }
}

/**
 * THE INNINGS, OVER BY OVER.
 *
 * Cricket's own axis is the over, so that is the axis this is drawn on. Everything a
 * reader wants is annotation on it rather than a second chart: the phases are shaded
 * REGIONS of the innings, the wickets are marked at the over they fell, and the over that
 * broke the game open is the column towering over its neighbours with its figure on top.
 *
 * The craft matters as much as the data here. Columns sit on a real baseline, the phase
 * tints run behind them so the eye reads powerplay-middle-death as territory, and only
 * one column is ever labelled — label them all and a graphic you take in at a glance
 * becomes a table you have to read.
 */
@Composable
private fun InningsTimeline(inn: InningsInsight, t: Float) {
    val overs = inn.progress
    if (overs.isEmpty()) return

    // Which phase each over belongs to, from the phase lengths the server sent rather than
    // guessed off the over number — a rain-shortened or gully innings has no six-over
    // powerplay, and the server has already worked out where it cut them.
    val bounds = remember(inn.phases) {
        var running = 0
        inn.phases.map { running += it.overs; running }
    }
    fun phaseOf(overNumber: Int): Int {
        bounds.forEachIndexed { i, end -> if (overNumber <= end) return i }
        return (inn.phases.size - 1).coerceAtLeast(0)
    }

    val peak = overs.maxOf { it.runs }.coerceAtLeast(1)
    val grow = ((t - 0.2f) / 0.6f).coerceIn(0f, 1f)
    val labelEvery = (overs.size / 6).coerceAtLeast(1)

    BlockHead("THE INNINGS", "${overs.size} over${if (overs.size == 1) "" else "s"}")
    Spacer(Modifier.height(20.dp))

    Box(Modifier.fillMaxWidth().height(150.dp)) {
        // Phase territory, behind the columns. Faint enough to read as ground rather than
        // as data, strong enough that the three regions of an innings are visible at all.
        Row(Modifier.matchParentSize()) {
            overs.forEach { over ->
                Box(
                    Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .background(phaseColour(phaseOf(over.over)).copy(alpha = 0.05f)),
                )
            }
        }

        Row(
            Modifier.matchParentSize().padding(top = 20.dp),
            verticalAlignment = Alignment.Bottom,
        ) {
            overs.forEach { over ->
                val isBest = over.over == inn.bestOverNumber
                val fill = if (isBest) PeakOrange else phaseColour(phaseOf(over.over))
                Column(
                    Modifier.weight(1f).fillMaxHeight(),
                    verticalArrangement = Arrangement.Bottom,
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    if (isBest) {
                        Text(
                            "${over.runs}",
                            color = PeakOrange,
                            fontSize = 13.sp,
                            fontFamily = com.haraan.app.theme.ArchivoDisplay,
                            maxLines = 1,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                        Spacer(Modifier.height(4.dp))
                    }
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 1.5.dp)
                            .fillMaxHeight(((over.runs.toFloat() / peak) * grow).coerceIn(0f, 1f))
                            .clip(RoundedCornerShape(topStart = 3.dp, topEnd = 3.dp))
                            .background(fill),
                    )
                }
            }
        }
    }

    // The baseline. Columns standing on a drawn line read as a chart; columns floating on
    // white read as coloured rectangles.
    Box(Modifier.fillMaxWidth().height(1.5.dp).background(InsightInk.copy(alpha = 0.18f)))

    // Wickets, marked at the over they fell in — cricket's own symbol, not a bar segment.
    Spacer(Modifier.height(7.dp))
    Row(Modifier.fillMaxWidth()) {
        overs.forEach { over ->
            Box(Modifier.weight(1f), contentAlignment = Alignment.Center) {
                if (over.wickets > 0) {
                    Row {
                        repeat(over.wickets.coerceAtMost(2)) { i ->
                            if (i > 0) Spacer(Modifier.width(3.dp))
                            Box(
                                Modifier.size(14.dp).clip(CircleShape).background(CrexColors.WicketBall),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    "W",
                                    color = Color.White,
                                    fontSize = 8.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    Spacer(Modifier.height(7.dp))
    Row(Modifier.fillMaxWidth()) {
        overs.forEachIndexed { i, over ->
            Box(Modifier.weight(1f), contentAlignment = Alignment.Center) {
                if (i == 0 || i == overs.lastIndex || over.over % labelEvery == 0) {
                    Text(
                        "${over.over}",
                        color = CrexColors.TextMuted,
                        fontSize = 9.5.sp,
                        maxLines = 1,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
    }

    // The phases, along the same axis and as wide as the overs they actually cover. Read
    // here they are a reading of the chart above; in their own bar, as they were, they
    // were a second chart saying the same thing worse.
    if (inn.phases.isNotEmpty()) {
        Spacer(Modifier.height(22.dp))
        Row(Modifier.fillMaxWidth()) {
            inn.phases.forEachIndexed { i, ph ->
                Column(
                    Modifier
                        .weight(ph.overs.toFloat().coerceAtLeast(1f))
                        .padding(end = if (i == inn.phases.lastIndex) 0.dp else 12.dp),
                ) {
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .height(3.dp)
                            .clip(RoundedCornerShape(1.5.dp))
                            .background(phaseColour(i)),
                    )
                    Spacer(Modifier.height(9.dp))
                    Text(
                        ph.label.uppercase(),
                        color = CrexColors.TextMuted,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.ExtraBold,
                        letterSpacing = 1.sp,
                        maxLines = 1,
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "${ph.runs}",
                        color = InsightInk,
                        fontSize = 24.sp,
                        fontFamily = com.haraan.app.theme.ArchivoDisplay,
                        letterSpacing = (-0.8).sp,
                        maxLines = 1,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(
                        "${ph.runRate} an over",
                        color = CrexColors.TextMuted,
                        fontSize = 10.5.sp,
                        maxLines = 1,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
    }

    if (inn.bestOverNumber > 0) {
        Spacer(Modifier.height(18.dp))
        Text(
            "Over ${inn.bestOverNumber} went for ${inn.bestOverRuns} — the biggest of the innings.",
            color = CrexColors.TextSecondary,
            fontSize = 13.sp,
            lineHeight = 20.sp,
        )
    }
}

/**
 * HOW THE RUNS WERE MADE.
 *
 * In cricket's units, not in percent. A bar filling to 94% invites "94% of what, toward
 * what?" — a boundary share is not progress toward anything. A total, though, is made of
 * strokes, and every one is counted in the ball log: 28 sixes is 168, 17 fours is 68, and
 * the rest of the innings is the other 16. That adds to 252 exactly, and it is the
 * sentence a cricket person actually says about an innings like this.
 */
@Composable
private fun RunsMade(inn: InningsInsight, t: Float) {
    val b = inn.breakdown
    val sixRuns = b.sixes * 6
    val fourRuns = b.fours * 4
    // Derived by subtraction from the innings total rather than re-totalled from the
    // pieces, so the three segments always add up to the score at the top of the screen.
    val restRuns = (inn.runs - sixRuns - fourRuns).coerceAtLeast(0)
    val grow = ((t - 0.3f) / 0.5f).coerceIn(0f, 1f)

    BlockHead("HOW THE ${inn.runs} WERE MADE")
    Spacer(Modifier.height(16.dp))

    Row(
        Modifier
            .fillMaxWidth()
            .height(14.dp)
            .clip(RoundedCornerShape(3.dp))
            .background(InsightTrack),
    ) {
        listOf(
            sixRuns to CrexColors.SixBall,
            fourRuns to CrexColors.FourBall,
            restRuns to CrexColors.NormalBall,
        ).forEachIndexed { i, (runs, colour) ->
            if (runs > 0) {
                Box(
                    Modifier
                        .weight(runs.toFloat())
                        .fillMaxHeight()
                        .padding(end = if (i == 2) 0.dp else 2.dp)
                        .background(colour.copy(alpha = 0.3f + 0.7f * grow)),
                )
            }
        }
    }
    Spacer(Modifier.height(18.dp))

    StrokeLine(CrexColors.SixBall, b.sixes, "six", "sixes", sixRuns)
    Spacer(Modifier.height(13.dp))
    StrokeLine(CrexColors.FourBall, b.fours, "four", "fours", fourRuns)
    Spacer(Modifier.height(13.dp))
    RestLine(b, restRuns)
}

/** One stroke type: how many were hit, and what they were worth. */
@Composable
private fun StrokeLine(colour: Color, count: Int, one: String, many: String, runs: Int) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(9.dp).clip(CircleShape).background(colour))
        Spacer(Modifier.width(12.dp))
        Text(
            "$count ${if (count == 1) one else many}",
            color = InsightInk,
            fontSize = 15.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Text(
            "$runs",
            color = InsightInk,
            fontSize = 22.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-0.6).sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}

/**
 * Everything that was not a boundary, named rather than lumped together.
 *
 * "The rest" tells a reader nothing. Nine singles and three twos tells them whether this
 * side ran between the wickets at all — which, in an innings that was 94% boundaries, is
 * the actual question.
 */
@Composable
private fun RestLine(b: ScoringBreakdown, runs: Int) {
    val parts = buildList {
        if (b.ones > 0) add("${b.ones} single${if (b.ones == 1) "" else "s"}")
        if (b.twos > 0) add("${b.twos} two${if (b.twos == 1) "" else "s"}")
        if (b.threes > 0) add("${b.threes} three${if (b.threes == 1) "" else "s"}")
        if (b.extras > 0) add("${b.extras} extra${if (b.extras == 1) "" else "s"}")
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(9.dp).clip(CircleShape).background(CrexColors.NormalBall))
        Spacer(Modifier.width(12.dp))
        Text(
            if (parts.isEmpty()) "Nothing else" else parts.joinToString(", "),
            color = InsightInk,
            fontSize = 15.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
            maxLines = 2,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Text(
            "$runs",
            color = InsightInk,
            fontSize = 22.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-0.6).sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}

/**
 * THE MOMENT — the one dark surface on a white page.
 *
 * Everything above this is quantities. This is the two people who made them, and it is
 * the only block on the screen allowed to raise its voice: a deep navy field, the stand
 * set at forty-eight point, and the batters named underneath. That contrast is the whole
 * design — a page that is calm everywhere cannot be emphatic anywhere, and an innings
 * built on one unbroken partnership deserves a screen that says so.
 *
 * It draws inside the page margins rather than bleeding off the edge: the section lives
 * in a pager inside a clipped scrolling list, and a band drawn past those bounds gets cut
 * off rather than bleeding.
 */
@Composable
private fun MomentBand(stand: Stand, innRuns: Int) {
    val strikeRate = if (stand.balls > 0) stand.runs * 100 / stand.balls else 0
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(InsightInk)
            .padding(horizontal = 22.dp, vertical = 24.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "${stand.wicket}${ordinal(stand.wicket)} WICKET",
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 10.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.5.sp,
            )
            if (stand.unbroken) {
                Spacer(Modifier.width(10.dp))
                Box(Modifier.size(3.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.35f)))
                Spacer(Modifier.width(10.dp))
                Text(
                    "UNBROKEN",
                    color = PeakOrange,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.5.sp,
                )
            }
        }
        Spacer(Modifier.height(14.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "${stand.runs}",
                color = Color.White,
                fontSize = 58.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-2.5).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(
                "  off ${stand.balls}",
                color = Color.White.copy(alpha = 0.6f),
                fontSize = 17.sp,
                modifier = Modifier.padding(bottom = 9.dp),
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
        if (stand.batters.isNotBlank()) {
            Spacer(Modifier.height(10.dp))
            Text(
                stand.batters,
                color = Color.White,
                fontSize = 17.sp,
                fontWeight = FontWeight.Medium,
                lineHeight = 25.sp,
            )
        }
        Spacer(Modifier.height(20.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.14f)))
        Spacer(Modifier.height(16.dp))
        Row {
            BandFigure("STRIKE RATE", "$strikeRate", Modifier.weight(1f))
            BandFigure(
                "SHARE OF THE ${innRuns}",
                "${(stand.runs * 100 / innRuns.coerceAtLeast(1))}%",
                Modifier.weight(1f),
            )
        }
    }
}

/** One figure inside the dark band. */
@Composable
private fun BandFigure(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(
            value,
            color = Color.White,
            fontSize = 24.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-0.8).sp,
            maxLines = 1,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(5.dp))
        Text(
            label,
            color = Color.White.copy(alpha = 0.45f),
            fontSize = 9.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.1.sp,
            maxLines = 1,
        )
    }
}

/**
 * The fifties as they came up, and the dot-ball count.
 *
 * This replaces a row of four figures in boxes. Four boxed numbers is the most
 * template-looking object a screen can carry — it gives a partnership, a percentage and
 * an over the same shape and the same weight. Cricket already marks an innings its own
 * way: by the fifties ticking over. So that is what is here.
 */
@Composable
private fun MilestoneStrip(inn: InningsInsight) {
    val milestones = remember(inn.progress, inn.runs) { milestoneLadder(inn) }
    if (milestones.isEmpty() && inn.breakdown.dots <= 0) return

    if (milestones.isNotEmpty()) {
        BlockHead("HOW IT CLIMBED")
        Spacer(Modifier.height(18.dp))
        Row(Modifier.fillMaxWidth()) {
            milestones.forEachIndexed { i, (runs, over) ->
                Column(Modifier.weight(1f)) {
                    Box(
                        Modifier
                            .width(18.dp)
                            .height(2.dp)
                            .background(CrexColors.AccentBlue),
                    )
                    Spacer(Modifier.height(9.dp))
                    Text(
                        "$runs",
                        color = InsightInk,
                        fontSize = 20.sp,
                        fontFamily = com.haraan.app.theme.ArchivoDisplay,
                        letterSpacing = (-0.6).sp,
                        maxLines = 1,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Spacer(Modifier.height(3.dp))
                    Text(
                        "ov $over",
                        color = CrexColors.TextMuted,
                        fontSize = 10.5.sp,
                        maxLines = 1,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
                if (i != milestones.lastIndex) Spacer(Modifier.width(6.dp))
            }
        }
    }

    if (inn.breakdown.dots > 0) {
        Spacer(Modifier.height(if (milestones.isEmpty()) 0.dp else 20.dp))
        Text(
            "${inn.breakdown.dots} dot ball${if (inn.breakdown.dots == 1) "" else "s"} in the innings — ${inn.dotPercent}% of it went by without a run.",
            color = CrexColors.TextMuted,
            fontSize = 12.5.sp,
            lineHeight = 19.sp,
        )
    }
}

private fun ordinal(n: Int): String = when {
    n % 100 in 11..13 -> "th"
    n % 10 == 1 -> "st"
    n % 10 == 2 -> "nd"
    n % 10 == 3 -> "rd"
    else -> "th"
}

/**
 * Each fifty of the innings and the over it came up in.
 *
 * Read off the cumulative total the server already sends per over, so the over named is
 * the over the milestone was PASSED in — never interpolated, never a guess at the ball.
 * The last five are kept, so a big innings shows its recent landmarks rather than
 * squeezing eight of them into a phone's width.
 */
private fun milestoneLadder(inn: InningsInsight): List<Pair<Int, Int>> {
    if (inn.progress.isEmpty()) return emptyList()
    val out = mutableListOf<Pair<Int, Int>>()
    var mark = 50
    while (mark <= inn.runs) {
        val over = inn.progress.firstOrNull { it.total >= mark } ?: break
        out.add(mark to over.over)
        mark += 50
    }
    return out.takeLast(5)
}

/**
 * What kind of innings this was, in one line.
 *
 * Picked by rule from the figures on the page, in order of how unusual each one is — so
 * the sentence is always something the reader can verify by looking down, and never a
 * claim the numbers do not support.
 */
private fun inningsRead(inn: InningsInsight): String? = when {
    // An innings nobody has batted in has nothing to say about itself.
    inn.runs <= 0 -> null
    inn.boundaryPercent >= 60 ->
        "${inn.boundaryPercent}% of these runs came in boundaries — this innings was built on them."
    inn.dotPercent >= 45 ->
        "${inn.dotPercent}% of it went by without a run. The pressure never really lifted."
    inn.bestStandRuns > 0 && inn.bestStandRuns * 2 >= inn.runs ->
        "One stand of ${inn.bestStandRuns} made more than half the total."
    inn.bestOverRuns >= 20 ->
        "${inn.bestOverRuns} came in a single over — over ${inn.bestOverNumber} broke it open."
    else -> "${inn.fours} fours and ${inn.sixes} sixes at ${inn.runRate} an over."
}

/**
 * The opening figure of the read, set in navy.
 *
 * "94% of these runs came in boundaries" — the reader should catch the number and the claim
 * it is making in one movement; the rest of the sentence can stay quiet.
 */
private fun emphasiseLeadingFigure(text: String): AnnotatedString {
    val end = text.indexOf(' ')
    if (end <= 0) return AnnotatedString(text)
    val head = text.take(end)
    if (head.none { it.isDigit() }) return AnnotatedString(text)
    return buildAnnotatedString {
        withStyle(SpanStyle(color = InsightInk, fontWeight = FontWeight.Bold)) { append(head) }
        append(text.substring(end))
    }
}

/**
 * The written read.
 *
 * Labelled, and placed below the figures it describes. A reader should always be able to
 * check a sentence here against a number above it — that is what stops generated prose
 * from quietly becoming the source of truth on the screen.
 */
@Composable
private fun AnalysisCard(text: String) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(
                Brush.verticalGradient(
                    listOf(
                        CrexColors.AccentBlue.copy(alpha = 0.09f),
                        CrexColors.Surface,
                    )
                )
            )
            .border(1.dp, CrexColors.AccentBlue.copy(alpha = 0.25f), RoundedCornerShape(18.dp))
            .padding(16.dp)
    ) {
        Text(
            "MATCH READ",
            color = CrexColors.AccentBlue,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(12.dp))
        text.split("\n").filter { it.isNotBlank() }.forEachIndexed { i, line ->
            if (i > 0) Spacer(Modifier.height(11.dp))
            Row(verticalAlignment = Alignment.Top) {
                // A small rule rather than a bullet glyph — this is analysis, not a list.
                Box(
                    modifier = Modifier
                        .padding(top = 7.dp)
                        .size(width = 14.dp, height = 2.dp)
                        .background(CrexColors.AccentBlue.copy(alpha = 0.55f))
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    line.trim(),
                    color = CrexColors.TextPrimary,
                    fontSize = 13.5.sp,
                    lineHeight = 19.sp,
                    fontWeight = FontWeight.Medium,
                )
            }
        }
        Spacer(Modifier.height(13.dp))
        Text(
            "Written by Haraan AI from the figures above.",
            color = CrexColors.TextMuted,
            fontSize = 10.sp,
        )
    }
}

/** Shared section shell, so every block on this tab is visibly the same kind of object. */
@Composable
private fun InsightSection(title: String, content: @Composable ColumnScope.() -> Unit) {
    // A SECTION, not a card.
    //
    // Every block on this tab used to be the same rounded, bordered, shadowed box, a
    // dozen of them stacked down a grey page. That is the single most template-looking
    // thing a screen can do: it gives every block identical weight, so nothing leads,
    // and the repetition reads as a component loop rather than as a designed page.
    //
    // What separates sections now is a rule and space — the way a printed page
    // separates them. The surface belongs to the objects that are genuinely objects
    // (the ground, an innings), and the page underneath is white so charts sit on it
    // without needing a card to lift them off grey.
    Column(Modifier.fillMaxWidth()) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(alpha = 0.55f)))
        Spacer(Modifier.height(18.dp))
        Text(
            title,
            color = CrexColors.TextMuted,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(14.dp))
        content()
        Spacer(Modifier.height(6.dp))
    }
}

/**
 * Game-changing overs.
 *
 * Ranked on runs AND wickets, not runs alone — an over that took two wickets can turn a
 * match further than one that went for twelve, and a list sorted purely by runs would
 * never show it.
 *
 * The top over is not a list row.
 *
 * This was five identical rows, each with a rounded blue tile holding the over number
 * and, beside it, the words "Over 6" repeating what the tile already said. Five equal
 * rows say five equal things, which is the opposite of what the section is for: one of
 * these overs decided more than the other four. So the first one is given the size it
 * earned and a line saying what it was worth, and the rest stay a ranked list beneath it.
 */
@Composable
private fun ChangingOversCard(inn: InningsInsight) {
    val top = inn.changingOvers.firstOrNull() ?: return
    val rest = inn.changingOvers.drop(1)

    InsightSection("THE OVER THAT TURNED IT") {
        Text(
            "OVER ${top.over}",
            color = CrexColors.AccentBlue,
            fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "${top.runs}",
                color = CrexColors.TextPrimary,
                fontSize = 46.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1.8).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                if (top.runs == 1) "run" else "runs",
                color = CrexColors.TextSecondary,
                fontSize = 14.sp,
                modifier = Modifier.padding(bottom = 8.dp),
            )
            if (top.wickets > 0) {
                Spacer(Modifier.width(12.dp))
                Text(
                    "${top.wickets}",
                    color = CrexColors.WicketBall,
                    fontSize = 46.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    letterSpacing = (-1.8).sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    if (top.wickets == 1) "wicket" else "wickets",
                    color = CrexColors.TextSecondary,
                    fontSize = 14.sp,
                    modifier = Modifier.padding(bottom = 8.dp),
                )
            }
        }
        // Why it mattered, as a share of the innings rather than an adjective. Six balls
        // out of an innings is a small slice of it; how much of the total came from them
        // is the whole point, and it is arithmetic the reader can check.
        if (inn.runs > 0) {
            val share = (top.runs * 100f / inn.runs).toInt()
            Spacer(Modifier.height(8.dp))
            Text(
                "${top.runs} of the ${inn.runs} came from these six balls — $share% of the innings.",
                color = CrexColors.TextSecondary,
                fontSize = 13.sp,
                lineHeight = 19.sp,
            )
        }

        if (rest.isNotEmpty()) {
            Spacer(Modifier.height(18.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(alpha = 0.5f)))
            Spacer(Modifier.height(12.dp))
            rest.forEachIndexed { i, ov ->
                if (i > 0) Spacer(Modifier.height(10.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "Over ${ov.over}",
                        color = CrexColors.TextSecondary,
                        fontSize = 13.sp,
                        modifier = Modifier.weight(1f),
                    )
                    Text(
                        "${ov.runs}",
                        color = CrexColors.TextPrimary,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(
                        if (ov.runs == 1) " run" else " runs",
                        color = CrexColors.TextMuted,
                        fontSize = 11.5.sp,
                    )
                    if (ov.wickets > 0) {
                        Text(
                            "  ·  ${ov.wickets}W",
                            color = CrexColors.WicketBall,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
                        )
                    }
                }
            }
        }
    }
}

/** Every stand, in the order the wickets fell. */
@Composable
private fun PartnershipsCard(inn: InningsInsight) {
    InsightSection("PARTNERSHIPS") {
        val peak = inn.partnerships.maxOf { it.runs }.coerceAtLeast(1)
        // A stand worth nothing off one ball is a wicket, not a partnership. Listing eight
        // of them at the same visual weight as a match-winning stand buries the one that
        // mattered — they are counted in a line instead.
        val worth = inn.partnerships.filter { it.runs > 0 || it.balls > 2 }
        val trivial = inn.partnerships.size - worth.size
        worth.forEachIndexed { i, st ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "${st.wicket}",
                    color = CrexColors.TextMuted, fontSize = 11.sp,
                    fontWeight = FontWeight.Black,
                    modifier = Modifier.width(18.dp),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Column(Modifier.weight(1f)) {
                    Text(
                        st.batters.ifBlank { "Partnership ${st.wicket}" },
                        color = CrexColors.TextPrimary, fontSize = 13.sp,
                        fontWeight = FontWeight.Bold, maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    )
                    Spacer(Modifier.height(5.dp))
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(7.dp)
                            .clip(RoundedCornerShape(4.dp))
                            .background(CrexColors.Border.copy(alpha = 0.7f))
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth((st.runs / peak.toFloat()).coerceIn(0.03f, 1f))
                                .fillMaxHeight()
                                .clip(RoundedCornerShape(4.dp))
                                .background(
                                    if (st.unbroken) CrexColors.AccentGreen else CrexColors.AccentBlue
                                )
                        )
                    }
                }
                Spacer(Modifier.width(12.dp))
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        "${st.runs}",
                        color = CrexColors.TextPrimary, fontSize = 15.sp,
                        fontWeight = FontWeight.Black,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(
                        if (st.unbroken) "(${st.balls})*" else "(${st.balls})",
                        color = CrexColors.TextSecondary, fontSize = 10.5.sp,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
        if (trivial > 0) {
            Spacer(Modifier.height(12.dp))
            Text(
                "$trivial more stand${if (trivial == 1) "" else "s"} ended without a run",
                color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Medium,
            )
        }
    }
}

/**
 * Face-off — bowler against batter, over the balls actually bowled between them.
 *
 * Only contests of three balls or more: two deliveries is not a duel, and listing it as one
 * would put a 300 strike rate on the screen that means nothing.
 */
@Composable
private fun FaceOffCard(inn: InningsInsight) {
    InsightSection("FACE OFF") {
        inn.faceoffs.forEachIndexed { i, f ->
            if (i > 0) {
                Spacer(Modifier.height(10.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border))
                Spacer(Modifier.height(10.dp))
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(
                        f.batter, color = CrexColors.TextPrimary, fontSize = 13.5.sp,
                        fontWeight = FontWeight.Bold, maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    )
                    Text(
                        "v ${f.bowler}", color = CrexColors.TextSecondary, fontSize = 11.5.sp,
                        maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    )
                }
                Spacer(Modifier.width(10.dp))
                Text(
                    "${f.runs} (${f.balls})",
                    color = CrexColors.TextPrimary, fontSize = 15.sp,
                    fontWeight = FontWeight.Black,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                if (f.wickets > 0) {
                    Spacer(Modifier.width(8.dp))
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(7.dp))
                            .background(CrexColors.WicketBall.copy(alpha = 0.12f))
                            .padding(horizontal = 7.dp, vertical = 3.dp)
                    ) {
                        Text(
                            if (f.wickets == 1) "OUT" else "${f.wickets} OUT",
                            color = CrexColors.WicketBall, fontSize = 10.sp,
                            fontWeight = FontWeight.Black,
                        )
                    }
                }
            }
        }
    }
}

/**
 * Scoring breakdown — where the runs actually came from.
 *
 * Counted per delivery during the replay, never divided out of a total. Dots are shown
 * alongside because an innings is as much about the balls that produced nothing.
 */
@Composable
private fun ScoringBreakdownCard(inn: InningsInsight) {
    val b: ScoringBreakdown = inn.breakdown
    val rows = listOf(
        Triple("Dot balls", b.dots, CrexColors.TextMuted),
        Triple("Singles", b.ones, CrexColors.AccentBlue),
        Triple("Twos", b.twos, CrexColors.AccentBlue),
        Triple("Threes", b.threes, CrexColors.AccentBlue),
        Triple("Fours", b.fours, CrexColors.FourBall),
        Triple("Sixes", b.sixes, CrexColors.SixBall),
        Triple("Extras", b.extras, CrexColors.TextSecondary),
    ).filter { it.second > 0 }

    InsightSection("SCORING BREAKDOWN") {
        val peak = (rows.maxOfOrNull { it.second } ?: 1).coerceAtLeast(1)
        rows.forEachIndexed { i, (label, count, colour) ->
            if (i > 0) Spacer(Modifier.height(9.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    label, color = CrexColors.TextSecondary, fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold, modifier = Modifier.width(74.dp),
                )
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(9.dp)
                        .clip(RoundedCornerShape(5.dp))
                        .background(CrexColors.Border.copy(alpha = 0.7f))
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth((count / peak.toFloat()).coerceIn(0.03f, 1f))
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(5.dp))
                            .background(colour)
                    )
                }
                Spacer(Modifier.width(10.dp))
                Text(
                    "$count", color = CrexColors.TextPrimary, fontSize = 12.5.sp,
                    fontWeight = FontWeight.Black, modifier = Modifier.width(28.dp),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

@Composable
private fun InsightsPlaceholder(loading: Boolean, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(24.dp),
        contentAlignment = Alignment.Center,
    ) {
        if (loading) {
            CircularProgressIndicator(
                modifier = Modifier.size(22.dp),
                strokeWidth = 2.dp,
                color = CrexColors.AccentBlue,
            )
        } else {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    "Nothing to read yet",
                    color = CrexColors.TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    "Insights appear once the innings has enough deliveries to say something about.",
                    color = CrexColors.TextSecondary,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}
