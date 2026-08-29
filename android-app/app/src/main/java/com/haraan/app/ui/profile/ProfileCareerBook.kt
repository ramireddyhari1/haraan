package com.haraan.app.ui.profile

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.layout
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.CareerBook
import com.haraan.app.data.CareerFigure
import com.haraan.app.data.CareerGroup
import com.haraan.app.data.CareerVisual
import com.haraan.app.data.SportRecord
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow
import kotlin.math.roundToInt

// ─────────────────────────────────────────────────────────────────────────────
//  THE CAREER BOOK
//
//  What the Stats tab used to be: the word "Career" over two bare numbers, Runs
//  and Wickets. Two numbers cannot tell a player anything about themselves — not
//  whether they average 12 or 42, not what their best spell was, not whether the
//  runs came in one innings or thirty.
//
//  This is the record instead: batting, bowling and fielding, each led by the
//  figure that discipline is judged on, every number replayed from the ball log.
//  A player who also plays another sport gets that sport's record on the same
//  shape, behind a switch — the profile stops being a cricket profile with other
//  sports bolted on.
//
//  The craft rules it holds to, because a stats page is READ as a table and FELT
//  as a product:
//    · every digit is tabular, so columns line up down the card and a number does
//      not shuffle its own width while it counts up;
//    · figures count up and bars grow, once, on first sight — the page assembles
//      itself rather than arriving printed;
//    · cards rise in staggered, so the eye is given an order to read them in;
//    · one accent colour, spent only on the figure that leads each discipline.
// ─────────────────────────────────────────────────────────────────────────────

private val Surface = HaraanColors.Surface
private val Blue = HaraanColors.EventsBlue
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted

/** The well a segmented control sits in — one step darker than the page. */
private val Track = Color(0xFFEEF2F7)

/** Hairlines inside a card sit lighter than the card's own edge, or they cage it. */
private val Hairline = Color(0xFFEDF1F6)

/**
 * Lining figures, fixed width. Without this a count-up reflows its own column as the
 * digits change and the three-column grid stops aligning down the card — the most
 * visible difference between a stats page and a spreadsheet.
 */
private val TabularStyle = TextStyle(fontFeatureSettings = "tnum")

@Composable
fun CareerBookSection(book: CareerBook) {
    // Survives the tab switch and a rotation: a player checking their bowling does
    // not expect to be put back on cricket batting for looking at Posts.
    var selected by rememberSaveable { mutableIntStateOf(0) }
    val index = selected.coerceIn(0, book.sports.lastIndex)
    val record = book.sports[index]

    Column(Modifier.fillMaxWidth()) {
        // One sport is not a choice, so it gets no switch — the record simply IS the
        // player's cricket. The control appears the day they play something else.
        if (book.sports.size > 1) {
            SportSwitch(book.sports, index) { selected = it }
            Spacer(Modifier.height(18.dp))
        }
        HeadlineRow(record.headline, key = record.key)
        val note = record.note
        if (record.groups.isEmpty() && note != null) {
            Spacer(Modifier.height(16.dp))
            // Said plainly rather than filled with zeros, and said by the server: only
            // it knows whether the line is missing because the player has not played,
            // because nobody scored the match, or because the sport scores per side.
            Text(note, color = Text3, fontSize = 13.sp, lineHeight = 19.sp)
        }
        record.groups.forEachIndexed { i, group ->
            Spacer(Modifier.height(14.dp))
            Staggered(index = i, key = record.key) { CareerGroupCard(group) }
        }
        // The wheel sits after the disciplines: it is the answer to a question the
        // tables raise (where DO those boundaries go?), so it reads as a follow-up
        // rather than as an illustration of nothing yet.
        record.wagon?.let { wagon ->
            Spacer(Modifier.height(14.dp))
            Staggered(index = record.groups.size, key = record.key) { WagonWheelCard(wagon) }
        }
        // And the written read last, because it is about everything above it.
        record.analysis?.let { analysis ->
            Spacer(Modifier.height(14.dp))
            Staggered(index = record.groups.size + 1, key = record.key) { CareerAnalysisCard(analysis) }
        }
    }
}

/**
 * The sport switch: a segmented control in a well, not a row of loose chips. The
 * selected sport lifts onto its own white surface — the affordance every native
 * segmented control uses, and the reason this reads as one switch rather than as
 * two buttons that happen to sit next to each other.
 */
@Composable
private fun SportSwitch(sports: List<SportRecord>, selected: Int, onSelect: (Int) -> Unit) {
    val scroll = rememberScrollState()
    val crowded = sports.size > 3
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Track)
            .padding(4.dp)
            // Four sports would not fit at once; three do. Scrolling engages only when
            // it has to, so the common case still fills the width evenly.
            .then(if (crowded) Modifier.horizontalScroll(scroll) else Modifier),
    ) {
        sports.forEachIndexed { i, sport ->
            val on = i == selected
            val bg by animateColorAsState(
                targetValue = if (on) Surface else Color.Transparent,
                animationSpec = tween(220),
                label = "segBg",
            )
            val fg by animateColorAsState(
                targetValue = if (on) Blue else Text2,
                animationSpec = tween(220),
                label = "segFg",
            )
            Row(
                Modifier
                    .then(if (crowded) Modifier else Modifier.weight(1f))
                    .pressable(onClick = { onSelect(i) })
                    .then(
                        if (on) Modifier.premiumCardShadow(radius = 11.dp, ambient = 7.dp, contact = 1.dp)
                        else Modifier,
                    )
                    .clip(RoundedCornerShape(11.dp))
                    .background(bg)
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(sport.label, color = fg, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                // The match count rides on the segment, so switching sports is never a
                // guess about which one has anything in it.
                Spacer(Modifier.width(7.dp))
                Text(
                    "${sport.matches}",
                    color = if (on) Blue.copy(alpha = 0.62f) else Text3,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    style = TabularStyle,
                )
            }
        }
    }
}

/**
 * The three numbers that lead the sport. No box and no dividers — the "Career"
 * heading already groups them, and a bordered card of centred numbers is the most
 * generic object this screen could open with.
 */
@Composable
private fun HeadlineRow(figures: List<CareerFigure>, key: String) {
    Row(Modifier.fillMaxWidth()) {
        figures.forEachIndexed { i, figure ->
            Column(Modifier.weight(1f)) {
                CountUpText(
                    value = figure.value,
                    key = key,
                    color = Text1,
                    fontSize = 30.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = (-0.8).sp,
                )
                Spacer(Modifier.height(3.dp))
                Label(figure.label)
            }
            if (i != figures.lastIndex) Spacer(Modifier.width(10.dp))
        }
    }
}

@Composable
private fun CareerGroupCard(group: CareerGroup) {
    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 20.dp, ambient = 16.dp, contact = 2.dp)
            .clip(RoundedCornerShape(20.dp))
            .background(Surface)
            // A shadow alone floats a card; the hairline gives it an edge to end at.
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                // A short accent rule instead of an icon: the discipline is named right
                // beside it, and a bat glyph next to the word "Batting" says it twice.
                Box(
                    Modifier
                        .width(3.dp)
                        .height(15.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(Brush.verticalGradient(listOf(Blue, Blue.copy(alpha = 0.5f)))),
                )
                Spacer(Modifier.width(9.dp))
                Text(
                    group.title,
                    color = Text1,
                    fontSize = 15.5.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = (-0.2).sp,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                CountUpText(
                    value = group.leadValue,
                    key = group.title,
                    color = Blue,
                    fontSize = 28.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = (-0.8).sp,
                )
                Spacer(Modifier.height(1.dp))
                Label(group.leadLabel)
            }
        }
        group.visual?.let { visual ->
            Spacer(Modifier.height(18.dp))
            CareerVisualBlock(visual)
        }
        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        Spacer(Modifier.height(16.dp))
        // Three to a row, in the order the server sent them — the order a scorebook
        // reads: what they did, then how well, then how often.
        group.stats.chunked(3).forEachIndexed { row, cells ->
            if (row > 0) Spacer(Modifier.height(18.dp))
            Row(Modifier.fillMaxWidth()) {
                cells.forEach { cell ->
                    Column(Modifier.weight(1f)) {
                        Text(
                            cell.value,
                            color = if (cell.value == "-") Text3 else Text1,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            style = TabularStyle,
                            letterSpacing = (-0.3).sp,
                        )
                        Spacer(Modifier.height(3.dp))
                        Label(cell.label)
                    }
                }
                // Keeps the last row's cells on the same column grid as the rows above
                // instead of stretching two cells across the full width.
                repeat(3 - cells.size) { Spacer(Modifier.weight(1f)) }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────── The drawings ─────
//
// A career page made only of numbers asks the reader to do the work: 5 fours and
// 4 sixes against 62 runs is a sum you have to actually perform before it means
// anything. These draw what the numbers already say — nothing here is a figure the
// table does not also print, and nothing is estimated.

/** The ramp a split bar reads in: strongest part first. */
private val SplitColors = listOf(
    Color(0xFF1D4ED8),
    Color(0xFF60A5FA),
    Color(0xFFC7D6E6),
)

@Composable
private fun CareerVisualBlock(visual: CareerVisual) {
    Column(Modifier.fillMaxWidth()) {
        Label(visual.title)
        Spacer(Modifier.height(10.dp))
        when (visual.kind) {
            "meter" -> Meter(visual)
            else -> SplitBar(visual)
        }
        visual.caption?.let {
            Spacer(Modifier.height(10.dp))
            Text(it, color = Text2, fontSize = 12.5.sp, lineHeight = 17.sp)
        }
    }
}

/**
 * A stacked bar of parts that sum to the whole — how the runs came, how the
 * dismissals were made. Grows from nothing on first draw, so the shape registers as
 * something that was built rather than a printed graphic.
 */
@Composable
private fun SplitBar(visual: CareerVisual) {
    val total = visual.segments.sumOf { it.value.toDouble() }.toFloat().coerceAtLeast(1f)
    val grow = remember(visual) { Animatable(0f) }
    LaunchedEffect(visual) {
        grow.animateTo(1f, tween(durationMillis = 700, easing = FastOutSlowInEasing))
    }
    Row(
        Modifier
            .fillMaxWidth()
            .height(11.dp)
            .clip(RoundedCornerShape(6.dp))
            .background(Track),
    ) {
        visual.segments.forEachIndexed { i, segment ->
            Box(
                Modifier
                    .weight((segment.value / total) * grow.value + 0.0001f)
                    .fillMaxHeight()
                    .background(SplitColors[i % SplitColors.size]),
            )
            // A hairline between parts, so two adjacent blues read as two parts rather
            // than one long smear.
            if (i != visual.segments.lastIndex && grow.value > 0.02f) {
                Box(Modifier.width(2.dp).fillMaxHeight().background(Surface))
            }
        }
        if (grow.value < 1f) Spacer(Modifier.weight(1f - grow.value))
    }
    Spacer(Modifier.height(13.dp))
    Column(verticalArrangement = Arrangement.spacedBy(9.dp)) {
        visual.segments.forEachIndexed { i, segment ->
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier
                        .size(8.dp)
                        .clip(RoundedCornerShape(3.dp))
                        .background(SplitColors[i % SplitColors.size]),
                )
                Spacer(Modifier.width(9.dp))
                Text(segment.label, color = Text2, fontSize = 13.sp, modifier = Modifier.weight(1f))
                Text(
                    "${segment.value.toInt()}",
                    color = Text1,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    style = TabularStyle,
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    "${(segment.value * 100f / total).roundToInt()}%",
                    color = Text3,
                    fontSize = 12.5.sp,
                    style = TabularStyle,
                    textAlign = TextAlign.End,
                    modifier = Modifier.width(40.dp),
                )
            }
        }
    }
}

/**
 * One value on a fixed scale — an economy rate against the range a bowler is
 * actually judged on. The verdict word arrives from the server with the caption, so
 * the client is not the thing deciding what counts as expensive.
 */
@Composable
private fun Meter(visual: CareerVisual) {
    val max = visual.max.coerceAtLeast(0.001f)
    val fraction = (visual.value / max).coerceIn(0f, 1f)
    val grow by animateFloatAsState(
        targetValue = fraction,
        animationSpec = tween(durationMillis = 700, easing = FastOutSlowInEasing),
        label = "meterGrow",
    )
    Box(
        Modifier
            .fillMaxWidth()
            .height(11.dp)
            .clip(RoundedCornerShape(6.dp))
            .background(Track),
    ) {
        Row(Modifier.fillMaxWidth()) {
            Box(
                Modifier
                    .weight(grow.coerceAtLeast(0.0001f))
                    .fillMaxHeight()
                    .clip(RoundedCornerShape(6.dp))
                    .background(Brush.horizontalGradient(listOf(Blue.copy(alpha = 0.72f), Blue))),
            )
            Spacer(Modifier.weight((1f - grow).coerceAtLeast(0.0001f)))
        }
    }
    Spacer(Modifier.height(8.dp))
    Row(Modifier.fillMaxWidth()) {
        Scale("0", TextAlign.Start)
        Scale("${(max / 2).toInt()}", TextAlign.Center)
        Scale("${max.toInt()}", TextAlign.End)
    }
}

@Composable
private fun RowScope.Scale(text: String, align: TextAlign) {
    Text(
        text,
        color = Text3,
        fontSize = 11.sp,
        style = TabularStyle,
        textAlign = align,
        modifier = Modifier.weight(1f),
    )
}

// ────────────────────────────────────────────────────────────── Fittings ─────

@Composable
private fun Label(text: String) {
    Text(
        text.uppercase(),
        color = Text3,
        fontSize = 10.5.sp,
        fontWeight = FontWeight.SemiBold,
        letterSpacing = 0.7.sp,
        lineHeight = 14.sp,
    )
}

/**
 * A figure that counts up to itself, once, when it first appears.
 *
 * Only whole numbers animate. Running a tween over "238.46" or "4/23" would land on
 * a string of nonsense on the way — and an average is read, not watched.
 */
@Composable
private fun CountUpText(
    value: String,
    key: Any,
    color: Color,
    fontSize: TextUnit,
    fontWeight: FontWeight,
    letterSpacing: TextUnit,
) {
    val target = value.toIntOrNull()
    var shown = value
    if (target != null && target > 0) {
        val progress = remember(key, value) { Animatable(0f) }
        LaunchedEffect(key, value) {
            progress.animateTo(1f, tween(durationMillis = 720, easing = FastOutSlowInEasing))
        }
        shown = (target * progress.value).roundToInt().toString()
    }
    Text(
        shown,
        color = color,
        fontSize = fontSize,
        fontWeight = fontWeight,
        letterSpacing = letterSpacing,
        style = TabularStyle,
        maxLines = 1,
    )
}

/**
 * Cards arrive one after another rather than all at once — 60ms apart, which is
 * below the point where it reads as waiting and above the point where it reads as
 * nothing at all.
 */
@Composable
private fun Staggered(index: Int, key: Any, content: @Composable () -> Unit) {
    val enter = remember(key, index) { Animatable(0f) }
    LaunchedEffect(key, index) {
        kotlinx.coroutines.delay(index * 60L)
        enter.animateTo(1f, tween(durationMillis = 380, easing = FastOutSlowInEasing))
    }
    Box(
        Modifier
            .alpha(enter.value)
            .layout { measurable, constraints ->
                val placeable = measurable.measure(constraints)
                // Rises into place. Offsetting inside layout rather than with a
                // modifier keeps the card's own shadow travelling with it.
                val lift = ((1f - enter.value) * 14.dp.toPx()).roundToInt()
                layout(placeable.width, placeable.height) { placeable.place(0, lift) }
            },
    ) {
        content()
    }
}
