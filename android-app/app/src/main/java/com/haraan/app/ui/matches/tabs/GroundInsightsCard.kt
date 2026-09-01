package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Place
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.layout
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.GroundInsights
import com.haraan.app.data.GroundStat
import com.haraan.app.ui.matches.CrexColors
import kotlin.math.roundToInt

// ─────────────────────────────────────────────────────────────────────────────
//  GROUND INSIGHTS
//
//  The composition is the one every cricket app uses for this card: four figures
//  arranged around the ground itself, joined to it by connectors, a split bar
//  under them, and the plain-English lines last.
//
//  Two deliberate departures from the card it is modelled on, both about not
//  claiming what we cannot measure:
//
//   1. The centre is a DRAWN ground — grass, soil, creases, stumps — lit and
//      floating, with the mown arcs following its curve. A satellite tile of the
//      real maidan was tried here first and read as an aerial photograph of a
//      town, not as a cricket ground; the drawn one is legible at 150dp and is
//      the same object on every card. The real imagery is still fetched and is
//      available for a ground page that can give it the room it needs.
//   2. The bar splits BOUNDARIES against RUNNING, not pace against spin. Nothing
//      in the ball log records a bowler's type on a historical delivery, so a
//      pace/spin bar would be a number we made up in the shape of a measurement.
//      The scorer has just started recording type; when a season of it exists,
//      this bar has somewhere honest to grow into.
//
//  Everything arrives rather than appears: connectors draw, figures rise, the bar
//  fills, numbers count up.
//
//  The figures are NOT on little white cards any more. Four bordered boxes inside a
//  bordered card inside a page was three levels of the same rounded rectangle, and
//  the boxes were doing nothing the type could not do on its own. They sit on the
//  ground's own surface now, separated by space and their own hierarchy.
// ─────────────────────────────────────────────────────────────────────────────

private val Hairline = Color(0xFFE8EDF4)
private val Well = Color(0xFFF4F7FB)

@Composable
fun GroundInsightsCard(
    ground: GroundInsights,
    thisInnings: Int = 0,
    modifier: Modifier = Modifier,
) {
    // One clock for the whole card, so the connectors, boxes and bar are phases of a
    // single move rather than four animations that happen to start together.
    val reveal = remember(ground.name) { Animatable(0f) }
    LaunchedEffect(ground.name) {
        reveal.animateTo(1f, tween(durationMillis = 1100, easing = FastOutSlowInEasing))
    }
    val t = reveal.value

    // No card, no border, no tinted well.
    //
    // This sat in a rounded box on a page of rounded boxes, and inside it four more
    // rounded boxes were arranged around the graphic with dotted connectors drawn
    // between them — decoration standing in for hierarchy. The connectors are gone, the
    // boxes are gone, and the figures now sit in one honest row beneath the ground they
    // describe. The place name carries the section instead of a coloured label.
    Column(modifier.fillMaxWidth()) {
        Text(
            ground.name,
            color = CrexColors.TextPrimary,
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.6).sp,
            maxLines = 2,
        )
        val place = listOfNotNull(
            ground.locality?.takeIf { it.isNotBlank() },
            ground.district?.takeIf { it.isNotBlank() },
        ).joinToString(" · ")
        // Place and sample size on one quiet line. How much data stands behind these
        // numbers belongs next to the ground's name, not in a coloured pill in the
        // corner — a pill reads as a badge, and this is a caveat.
        Spacer(Modifier.height(5.dp))
        Text(
            listOfNotNull(place.takeIf { it.isNotBlank() }, sampleLine(ground)).joinToString("  ·  "),
            color = CrexColors.TextMuted,
            fontSize = 12.5.sp,
        )

        Spacer(Modifier.height(14.dp))

        // THE COMPARISON, where a drawn pitch used to be.
        //
        // The disc was a picture of a cricket ground on a screen that had already said
        // it was about a cricket ground — decoration in the one position on the card
        // that a reader actually looks at. What belongs there is the only question this
        // section exists to answer: was that a big score HERE?
        //
        // When the ground has no history to compare against, the plain figures still
        // run, because "we don't know yet" is a fact and an empty card is not.
        val compared = thisInnings > 0 &&
            (ground.firstInningsAvg > 0 || ground.highestTotal > 0)

        Spacer(Modifier.height(4.dp))
        if (compared) {
            GroundComparison(ground, thisInnings, t)
        } else if (ground.stats.isNotEmpty()) {
            StatPair(ground.stats.take(4), t, phase = 0.25f)
        }

        ground.split?.let { split ->
            Spacer(Modifier.height(20.dp))
            SplitBar(split, t)
        }

        // One line, not a bulleted list.
        //
        // There were three, and two of them read the figures above back to the reader —
        // "62% of runs come in boundaries" directly under a bar labelled Boundaries 62%.
        // The one that survives is the one saying something the figures do not: how the
        // toss has actually played out here.
        ground.bullets.firstOrNull { it.contains("batting first", ignoreCase = true) }?.let { line ->
            Spacer(Modifier.height(16.dp))
            Staged(t, after = 0.6f) {
                Text(
                    line,
                    color = CrexColors.TextPrimary,
                    fontSize = 13.5.sp,
                    lineHeight = 20.sp,
                )
            }
        }

        ground.note?.let {
            Spacer(Modifier.height(14.dp))
            Text(it, color = CrexColors.TextMuted, fontSize = 12.5.sp, lineHeight = 18.sp)
        }
    }
}

/** The figures in a single row, the number leading and its label underneath. */
@Composable
private fun StatPair(stats: List<GroundStat>, t: Float, phase: Float) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        stats.forEachIndexed { i, stat ->
            Staged(t, after = phase + i * 0.06f, modifier = Modifier.weight(1f)) {
                Column(Modifier.fillMaxWidth()) {
                    CountUp(stat.value, t)
                    Spacer(Modifier.height(3.dp))
                    Text(
                        stat.label.uppercase(),
                        color = CrexColors.TextMuted,
                        fontSize = 9.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        letterSpacing = 0.8.sp,
                        lineHeight = 13.sp,
                        maxLines = 2,
                    )
                    stat.note?.takeIf { it.isNotBlank() }?.let {
                        Spacer(Modifier.height(2.dp))
                        Text(it, color = CrexColors.TextSecondary, fontSize = 10.5.sp, maxLines = 1)
                    }
                }
            }
        }
        if (stats.size == 1) Spacer(Modifier.weight(1f))
    }
}

/**
 * The two hairlines joining the figures to the ground.
 *
 * They draw outward from the ground rather than fading in, which is what makes the
 * card read as one diagram instead of six separate objects.
 */
@Suppress("unused")
@Composable
private fun Connectors(t: Float, downward: Boolean) {
    val grow = ((t - 0.2f) / 0.35f).coerceIn(0f, 1f)
    Row(
        Modifier.fillMaxWidth().height(18.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        repeat(2) {
            Box(Modifier.weight(1f), contentAlignment = if (downward) Alignment.TopCenter else Alignment.BottomCenter) {
                Box(
                    Modifier
                        .width(1.5.dp)
                        .fillMaxHeight(grow)
                        .background(Hairline),
                )
            }
        }
    }
}

/**
 * The split bar. Two segments, each labelled with its own share, growing from the
 * middle out as the card settles.
 */
@Composable
private fun SplitBar(split: com.haraan.app.data.GroundSplit, t: Float) {
    val grow = ((t - 0.45f) / 0.45f).coerceIn(0f, 1f)
    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "${split.leftLabel} ${split.leftPercent}%",
                color = CrexColors.TextMuted,
                fontSize = 12.sp,
                fontWeight = FontWeight.Medium,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(
                split.title,
                color = CrexColors.TextPrimary,
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.weight(1f),
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            )
            Text(
                "${split.rightLabel} ${split.rightPercent}%",
                color = CrexColors.TextMuted,
                fontSize = 12.sp,
                fontWeight = FontWeight.Medium,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
        Spacer(Modifier.height(9.dp))
        Row(
            Modifier
                .fillMaxWidth()
                .height(7.dp)
                .clip(RoundedCornerShape(4.dp))
                .background(Hairline),
        ) {
            Box(
                Modifier
                    .weight((split.leftPercent / 100f) * grow + 0.0001f)
                    .fillMaxHeight()
                    .background(CrexColors.AccentBlue),
            )
            Box(Modifier.width(2.dp).fillMaxHeight().background(Well))
            Box(
                Modifier
                    .weight((split.rightPercent / 100f) * grow + 0.0001f)
                    .fillMaxHeight()
                    // NOT CrexColors.AccentGreen: that token is defined as the brand
                    // blue in this codebase, so the two halves of the bar came out the
                    // same colour and the split could not be read. The light end of the
                    // same ramp the profile uses for its splits.
                    .background(Color(0xFF93C5FD)),
            )
            if (grow < 1f) Spacer(Modifier.weight(1f - grow))
        }
    }
}

/** How much this ground's numbers are worth, in a word. */
/**
 * How much this ground's record is worth, in words.
 *
 * The confidence band was a coloured pill saying "Early trend", which looks like a
 * status badge and tells a reader nothing about what is behind it. The sample size is
 * the honest version of the same caveat, and it is the number a cricketer actually
 * wants: six matches is six matches.
 */
private fun sampleLine(ground: GroundInsights): String? {
    val played = ground.stats.firstOrNull { it.label.contains("matches", ignoreCase = true) }?.value
        ?.filter { it.isDigit() }?.toIntOrNull() ?: return null
    return when {
        played <= 0 -> null
        played == 1 -> "1 match on record"
        else -> "$played matches on record"
    }
}

@Suppress("unused")
@Composable
private fun ConfidenceChip(ground: GroundInsights) {
    val (label, tint) = when (ground.confidence) {
        "strong" -> "Strong sample" to CrexColors.AccentGreen
        "established" -> "Good sample" to CrexColors.AccentBlue
        "emerging" -> "Early trend" to CrexColors.AccentYellow
        else -> "${ground.matchesPlayed} played" to CrexColors.TextMuted
    }
    Box(
        Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(tint.copy(alpha = 0.12f))
            .padding(horizontal = 9.dp, vertical = 4.dp),
    ) {
        Text(label, color = tint, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
    }
}

// ─────────────────────────────────────────────────────────────── Motion ─────

/** Fades and lifts a block in once the card's clock passes [after]. */
@Composable
private fun Staged(t: Float, after: Float, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    val local = ((t - after) / 0.3f).coerceIn(0f, 1f)
    Box(
        modifier
            .alpha(local)
            .layout { measurable, constraints ->
                val placeable = measurable.measure(constraints)
                val lift = ((1f - local) * 10.dp.toPx()).roundToInt()
                layout(placeable.width, placeable.height) { placeable.place(0, lift) }
            },
    ) {
        content()
    }
}

/**
 * Counts a whole number up to itself. Values that are not plain integers — "3 of 5",
 * "9.77", "65%" — are printed as they are, because a tween across them lands on
 * strings that were never true.
 */
@Composable
private fun CountUp(value: String, t: Float) {
    val target = value.toIntOrNull()
    val shown = if (target != null && target > 0) {
        (target * ((t - 0.15f) / 0.5f).coerceIn(0f, 1f)).roundToInt().toString()
    } else {
        value
    }
    Text(
        shown,
        color = CrexColors.TextPrimary,
        fontSize = 24.sp,
        fontFamily = com.haraan.app.theme.ArchivoDisplay,
        letterSpacing = (-0.7).sp,
        style = TextStyle(fontFeatureSettings = "tnum"),
        maxLines = 1,
    )
}

/**
 * This innings, measured against this ground.
 *
 * The card used to print four figures in four equal cells — matches played, first-innings
 * average, highest total, best innings — and leave the reader to do the only sum that
 * matters. They don't. A number like "232" means nothing on its own; "this is 20 above
 * par here" is the same data doing its job.
 *
 * Every figure is the ground's own history against the score at the top of the screen.
 * Nothing here is generated, and nothing is estimated.
 */
@Composable
private fun GroundComparison(ground: GroundInsights, thisInnings: Int, t: Float) {
    val par = ground.firstInningsAvg
    val best = ground.highestTotal
    val verdict = groundVerdict(thisInnings, par, best) ?: return

    Staged(t, after = 0.2f) {
        Column(Modifier.fillMaxWidth()) {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    "$thisInnings",
                    color = CrexColors.TextPrimary,
                    fontSize = 46.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    letterSpacing = (-2).sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.width(14.dp))
                Text(
                    verdict.headline,
                    color = verdict.tint,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.1.sp,
                    lineHeight = 17.sp,
                    modifier = Modifier.padding(bottom = 8.dp).weight(1f),
                )
            }
            Spacer(Modifier.height(10.dp))
            Text(
                verdict.support,
                color = CrexColors.TextSecondary,
                fontSize = 14.sp,
                lineHeight = 21.sp,
            )

            // The scale. Par is a marked point on it rather than another number in a
            // row, so "above" and "below" are things you SEE before you read them.
            if (par > 0) {
                val ceiling = maxOf(thisInnings, best, par).coerceAtLeast(1)
                val grow = ((t - 0.35f) / 0.5f).coerceIn(0f, 1f)
                Spacer(Modifier.height(20.dp))
                Box(
                    Modifier
                        .fillMaxWidth()
                        .height(10.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(Color(0xFFEFF3F8)),
                ) {
                    Box(
                        Modifier
                            .fillMaxWidth((thisInnings.toFloat() / ceiling) * grow)
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(2.dp))
                            .background(verdict.tint),
                    )
                    // Par, marked where it actually falls on the scale.
                    Box(
                        Modifier.fillMaxHeight().fillMaxWidth(par.toFloat() / ceiling),
                        contentAlignment = Alignment.CenterEnd,
                    ) {
                        Box(
                            Modifier
                                .width(2.dp)
                                .fillMaxHeight()
                                .background(CrexColors.TextPrimary.copy(alpha = 0.55f)),
                        )
                    }
                }
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth()) {
                    Text(
                        "PAR $par",
                        color = CrexColors.TextMuted,
                        fontSize = 9.5.sp,
                        fontWeight = FontWeight.ExtraBold,
                        letterSpacing = 1.sp,
                        modifier = Modifier.weight(1f),
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    if (best > 0) {
                        Text(
                            "GROUND BEST $best",
                            color = CrexColors.TextMuted,
                            fontSize = 9.5.sp,
                            fontWeight = FontWeight.ExtraBold,
                            letterSpacing = 1.sp,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                    }
                }
            }
        }
    }
}

/** What this total is worth here, and the colour that says so. */
private data class GroundVerdict(val headline: String, val support: String, val tint: Color)

/**
 * Ranked by how much the reader would care, not by which figure came first.
 *
 * A ground record outranks being above par, and being above par outranks the average
 * itself — so the strongest true thing about the innings is the thing that gets said.
 * Returns null when the ground has no history worth comparing against, and the card
 * falls back to plain figures rather than inventing a verdict from one match.
 */
private fun groundVerdict(runs: Int, par: Int, best: Int): GroundVerdict? {
    if (runs <= 0) return null
    return when {
        best > 0 && runs > best -> GroundVerdict(
            "HIGHEST TOTAL EVER MADE HERE",
            "The best before this was $best.",
            Color(0xFF15803D),
        )
        par > 0 && runs > par -> GroundVerdict(
            "${runs - par} ABOVE PAR HERE",
            "A first innings at this ground averages $par.",
            Color(0xFF15803D),
        )
        par > 0 && runs < par -> GroundVerdict(
            "${par - runs} BELOW PAR HERE",
            "A first innings at this ground averages $par.",
            Color(0xFFB54708),
        )
        par > 0 -> GroundVerdict(
            "EXACTLY PAR HERE",
            "A first innings at this ground averages $par.",
            CrexColors.TextSecondary,
        )
        else -> null
    }
}
