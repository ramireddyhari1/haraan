package com.haraan.app.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.ui.draw.shadow
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.Canvas
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.animateColorAsState
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

        data.innings.forEachIndexed { idx, inn ->
            val accent = if (inn.battingTeam == 2) state.team2Color else state.team1Color

            // Whose charts these are. The hero used to sit directly above each innings
            // and answer that; now that it is a pager at the top, the detail below needs
            // to say it out loud — quietly, in one line, not in another card.
            if (data.innings.size > 1) {
                item(key = "inn-label-$idx") {
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
            }

            // The order is the order a person asks the questions in.
            //
            // The tab used to open on the ground's record — a page about somewhere,
            // before it said anything about what happened there. So the innings leads
            // now: the score, then the ground it was made on, then who is in form, then
            // the detail behind all three. Ground and form belong to the match as a
            // whole, so they appear once, under the first innings.
            if (idx == 0) {
                ground?.let { known -> item(key = "ground") { GroundInsightsCard(known) } }

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
            }

            if (inn.progress.isNotEmpty()) {
                item(key = "manhattan-$idx") {
                    InsightSection("RUNS PER OVER") { Manhattan(inn, accent) }
                }
                item(key = "strip-$idx") {
                    InsightSection("BALL BY BALL") { OverStrip(inn) }
                }
            }
            item(key = "wagon-$idx") {
                InsightSection("WAGON WHEEL") { WagonWheel(inn) }
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
            item(key = "breakdown-$idx") { ScoringBreakdownCard(inn) }
        }

        data.analysis?.let { text ->
            item(key = "analysis") { AnalysisCard(text) }
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
        // Tabs above the card: the swipe is discoverable, but nobody should have to
        // discover it to reach half the match.
        Row(Modifier.fillMaxWidth().padding(bottom = 12.dp)) {
            sides.forEachIndexed { i, side ->
                val on = pager.currentPage == i
                val fg by animateColorAsState(
                    if (on) CrexColors.TextPrimary else CrexColors.TextMuted,
                    tween(220),
                    label = "sideFg",
                )
                val rule by animateColorAsState(
                    if (on) side.colour else Color.Transparent,
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
                        fontSize = 13.5.sp,
                        fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                        maxLines = 1,
                    )
                    Spacer(Modifier.height(8.dp))
                    Box(Modifier.fillMaxWidth().height(2.dp).background(rule))
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
 * Drawn in the same surface as a real hero rather than as an empty state, because it is
 * not an error — it is a fact about where the match has got to, and it will become a
 * scorecard shortly.
 */
@Composable
private fun YetToBatCard(name: String, colour: Color) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), spotColor = Color(0x33101828))
            .clip(RoundedCornerShape(22.dp))
            .background(heroSurface(colour))
    ) {
        HeroTurf(Modifier.matchParentSize())
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text(
                name.uppercase(),
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 9.5.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.6.sp,
            )
            Spacer(Modifier.height(10.dp))
            Text(
                "Yet to bat",
                color = Color.White,
                fontSize = 34.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1.2).sp,
            )
            Spacer(Modifier.height(8.dp))
            Text(
                "Their innings will appear here ball by ball.",
                color = Color.White.copy(alpha = 0.6f),
                fontSize = 13.sp,
            )
            Spacer(Modifier.height(30.dp))
        }
    }
}

/**
 * A team's colour, taken down to something white type can sit on.
 *
 * Both heroes were the same navy, which meant swiping between two sides changed the
 * numbers and nothing else. Blending each side's own colour into near-black keeps its
 * hue — a blue side reads deep navy, a red one deep maroon — while holding the contrast
 * that 52sp white numerals need. The colour identifies; it never has to be legible.
 */
private fun heroSurface(team: Color): Brush = Brush.verticalGradient(
    0f to lerp(team, Color(0xFF0A1220), 0.72f),
    0.55f to lerp(team, Color(0xFF070E18), 0.86f),
    1f to lerp(team, Color(0xFF05090F), 0.9f),
)

@Composable
private fun InningsInsightCard(inn: InningsInsight, accent: Color = CrexColors.AccentBlue) {
    // THE HERO — "what happened".
    //
    // This was a white card of four grey stat cells, identical in weight to the ten
    // sections under it, so the innings itself had no more presence than a chart legend.
    // It is now the one dark surface on the tab: the page below is white and editorial,
    // and this sits on it with weight. That contrast IS the hierarchy — the reader's eye
    // has somewhere to land before the detail starts.
    //
    // Depth comes from a single hue lifted at the top, a turf horizon at the foot, and
    // one soft shadow. Not from glass, blur or a second colour.
    val reveal = remember(inn.runs, inn.overs) { Animatable(0f) }
    LaunchedEffect(inn.runs, inn.overs) {
        reveal.animateTo(1f, tween(durationMillis = 900, easing = FastOutSlowInEasing))
    }
    val t = reveal.value

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), spotColor = Color(0x33101828))
            .clip(RoundedCornerShape(22.dp))
            .background(heroSurface(accent))
    ) {
        HeroTurf(Modifier.matchParentSize())
        Column(modifier = Modifier.fillMaxWidth().padding(20.dp)) {
            Text(
                inn.battingName.uppercase(),
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 9.5.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.6.sp,
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                // The score counts to itself. On the one number the whole tab is about,
                // arriving is worth more than appearing.
                val shown = (inn.runs * ((t - 0.1f) / 0.6f).coerceIn(0f, 1f)).toInt()
                Text(
                    "$shown",
                    color = Color.White,
                    fontSize = 52.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    letterSpacing = (-2).sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text(
                    "/${inn.wickets}",
                    color = Color.White.copy(alpha = 0.5f),
                    fontSize = 26.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    modifier = Modifier.padding(bottom = 5.dp),
                )
                Spacer(Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.End, modifier = Modifier.padding(bottom = 6.dp)) {
                    Text(
                        "${inn.overs} ov",
                        color = Color.White.copy(alpha = 0.85f),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(
                        "RR ${inn.runRate}",
                        color = Color.White.copy(alpha = 0.45f),
                        fontSize = 11.5.sp,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }

            // The read. One sentence that says what KIND of innings this was, chosen from
            // the figures printed below it — so it is checkable, not asserted.
            inningsRead(inn)?.let { read ->
                Spacer(Modifier.height(10.dp))
                Text(
                    read,
                    color = Color.White.copy(alpha = 0.78f),
                    fontSize = 13.5.sp,
                    lineHeight = 20.sp,
                )
            }

            if (inn.phases.isNotEmpty()) {
                Spacer(Modifier.height(20.dp))
                val peak = inn.phases.maxOf { it.runRate }.coerceAtLeast(0.01)
                inn.phases.forEachIndexed { i, ph ->
                    if (i > 0) Spacer(Modifier.height(10.dp))
                    val grow = ((t - 0.25f - i * 0.08f) / 0.5f).coerceIn(0f, 1f)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            ph.label,
                            color = Color.White.copy(alpha = 0.55f),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.width(50.dp),
                        )
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .height(6.dp)
                                .clip(RoundedCornerShape(3.dp))
                                .background(Color.White.copy(alpha = 0.12f))
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth(((ph.runRate / peak).toFloat() * grow).coerceIn(0f, 1f))
                                    .fillMaxHeight()
                                    .clip(RoundedCornerShape(3.dp))
                                    .background(lerp(accent, Color.White, 0.62f))
                            )
                        }
                        Spacer(Modifier.width(12.dp))
                        Text(
                            "${ph.runs}",
                            color = Color.White,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                        Text(
                            " @ ${ph.runRate}",
                            color = Color.White.copy(alpha = 0.45f),
                            fontSize = 11.sp,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.10f)))
            Spacer(Modifier.height(16.dp))

            // Four figures on one line, not a 2x2 grid of grey cells. The numbers carry
            // the weight and the labels get out of the way underneath them.
            Row(modifier = Modifier.fillMaxWidth()) {
                HeroFigure("BOUNDARY", "${inn.boundaryPercent}%", Modifier.weight(1f))
                HeroFigure("DOTS", "${inn.dotPercent}%", Modifier.weight(1f))
                HeroFigure(
                    "BEST OVER",
                    if (inn.bestOverNumber > 0) "${inn.bestOverRuns}" else "-",
                    Modifier.weight(1f),
                    note = if (inn.bestOverNumber > 0) "over ${inn.bestOverNumber}" else null,
                )
                HeroFigure(
                    "BEST STAND",
                    if (inn.bestStandRuns > 0) "${inn.bestStandRuns}" else "-",
                    Modifier.weight(1f),
                    note = if (inn.bestStandRuns > 0) "${inn.bestStandBalls} balls" else null,
                )
            }
        }
    }
}

/**
 * What kind of innings this was, in one line.
 *
 * Picked by rule from the figures on the card, in order of how unusual each one is —
 * so the sentence is always something the reader can verify by looking down, and never
 * a claim the numbers do not support.
 */
private fun inningsRead(inn: InningsInsight): String? = when {
    // An innings nobody has batted in has nothing to say about itself. Without this the
    // second team's card opens with "0 fours and 0 sixes at 0.0 an over", which is true
    // and useless, and reads as a template filling itself in.
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

/** One figure in the hero row: the number leads, the label sits under it. */
@Composable
private fun HeroFigure(label: String, value: String, modifier: Modifier = Modifier, note: String? = null) {
    Column(modifier) {
        Text(
            value,
            color = Color.White,
            fontSize = 21.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-0.6).sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
            maxLines = 1,
        )
        Spacer(Modifier.height(3.dp))
        Text(
            label,
            color = Color.White.copy(alpha = 0.45f),
            fontSize = 8.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 0.9.sp,
            maxLines = 1,
        )
        note?.let {
            Text(
                it,
                color = Color.White.copy(alpha = 0.35f),
                fontSize = 9.5.sp,
                maxLines = 1,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
    }
}

/**
 * A turf horizon at the foot of the hero.
 *
 * Not a decorative gradient: it is the ground the score was made on, sitting where the
 * ground sits — under everything, at the bottom of the frame.
 *
 * The first version started the green at full strength on a hard line, which cut the
 * card in two and made the figures beneath it look like a separate footer that had been
 * pasted on. Green now arrives from nothing over the upper half of its own band, so the
 * eye reads one surface receding rather than two blocks stacked.
 */
@Composable
private fun HeroTurf(modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val horizon = size.height * 0.52f
        drawRect(
            brush = Brush.verticalGradient(
                0f to Color(0xFF16452F).copy(alpha = 0f),
                0.55f to Color(0xFF143E2B).copy(alpha = 0.42f),
                1f to Color(0xFF0C2A1D).copy(alpha = 0.72f),
                startY = horizon,
                endY = size.height,
            ),
            topLeft = Offset(0f, horizon),
            size = androidx.compose.ui.geometry.Size(size.width, size.height - horizon),
        )
        // Mown arcs curving away, which is what stops the band reading as a flat wash.
        for (i in 0..2) {
            val y = size.height * (0.74f + i * 0.13f)
            drawArc(
                color = Color.White.copy(alpha = 0.03f),
                startAngle = 200f,
                sweepAngle = 140f,
                useCenter = false,
                topLeft = Offset(-size.width * 0.3f, y - size.height * 0.15f),
                size = androidx.compose.ui.geometry.Size(size.width * 1.6f, size.height * 0.3f),
                style = Stroke(width = 7.dp.toPx()),
            )
        }
    }
}

@Composable
private fun StatCell(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier) {
        Text(
            label,
            color = CrexColors.TextMuted,
            fontSize = 8.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.sp,
        )
        Spacer(Modifier.height(3.dp))
        Text(
            value,
            color = CrexColors.TextPrimary,
            fontSize = 16.sp,
            fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
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
