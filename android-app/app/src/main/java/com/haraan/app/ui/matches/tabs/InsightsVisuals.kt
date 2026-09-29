package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import kotlinx.coroutines.launch
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.InningsInsight
import com.haraan.app.ui.matches.WAGON_ZONES
import com.haraan.app.ui.matches.wagonZoneAngle
import com.haraan.app.data.ProgressOver
import com.haraan.app.ui.matches.CrexColors
import kotlin.math.cos
import kotlin.math.sin

/**
 * The cricket-native half of the Insights tab.
 *
 * The first version of this screen was six variations of "label, horizontal bar, number".
 * Every figure on it was true and it still looked like a dashboard someone generated,
 * because a horizontal bar is not how cricket has ever been drawn. A player reads an over
 * as a row of balls and an innings as a skyline; those are the shapes their eye already
 * knows, and using them is the difference between a screen that reports numbers and one
 * that explains a match.
 *
 * Nothing here plots anything the ball log does not contain. There is no wagon wheel and no
 * shot map, because direction and shot type are simply not recorded — see the note on the
 * Insights tab. Everything below is the same replay the scorecard uses, drawn properly.
 */

/**
 * What a ball should FEEL like when you touch it.
 *
 * The same vocabulary the scorer's keypad and the hero burst use, so a six felt while
 * scoring, a six felt while watching, and a six felt while reading it back a week later are
 * the same sensation. That consistency is most of what makes an app feel built rather than
 * assembled.
 */
internal fun ballThud(token: String): Thud = when (token.trim().lowercase()) {
    "6" -> Thud.SIX
    "4" -> Thud.FOUR
    "w" -> Thud.WICKET
    "0" -> Thud.TICK
    "wd", "nb" -> Thud.EXTRA
    else -> Thud.RUN
}

/** Ink for one ball, by what it actually was. Matches the scorer's keypad exactly. */
internal fun ballInk(token: String): Pair<Color, Color> = when (token.trim().lowercase()) {
    "6" -> CrexColors.SixBall to Color.White
    "4" -> CrexColors.FourBall to Color.White
    "w" -> CrexColors.WicketBall to Color.White
    "0" -> Color(0xFFE8EDF3) to CrexColors.TextMuted
    else -> Color(0xFFF4F7FB) to CrexColors.TextSecondary
}

/**
 * The over strip — the innings exactly as a scoreboard shows it.
 *
 * Six pips to an over, coloured by outcome, one row per over. This is the single most
 * recognisable object in cricket: a player can read "4 1 0 2 0 4" without being told what
 * any of it means, and can see a collapse or an assault as a pattern rather than a figure.
 */
@Composable
internal fun OverStrip(inn: InningsInsight) {
    Column {
        inn.progress.forEachIndexed { i, over ->
            if (i > 0) Spacer(Modifier.height(9.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "${over.over}",
                    color = CrexColors.TextMuted,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black,
                    modifier = Modifier.width(20.dp),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Row(
                    modifier = Modifier
                        .weight(1f)
                        .horizontalScroll(rememberScrollState())
                        // Room for the raised balls' shadows, which a scroller clips.
                        .padding(vertical = 3.dp, horizontal = 1.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    over.balls.forEach { token -> BallChip(token, 27.dp) }
                }
                Spacer(Modifier.width(8.dp))
                Text(
                    "${over.runs}",
                    color = CrexColors.TextPrimary,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Black,
                    modifier = Modifier.width(24.dp),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

/**
 * Manhattan — runs per over as a skyline, wickets marked where they fell.
 *
 * Every cricket broadcast draws this, and for a reason: the shape of an innings is the
 * story of it. A flat run of low bars then a spike is a match turning; the same information
 * as a column of numbers is something you have to read rather than see.
 */
@Composable
internal fun Manhattan(inn: InningsInsight, accent: Color) {
    val peak = (inn.progress.maxOfOrNull { it.runs } ?: 0).coerceAtLeast(1)

    // The skyline builds itself once, on arrival. A chart that is simply THERE reads as a
    // picture; one that rises reads as something being shown to you.
    val grow = remember(inn.battingName, inn.progress.size) { Animatable(0f) }
    LaunchedEffect(inn.battingName, inn.progress.size) {
        grow.animateTo(1f, tween(durationMillis = 620, easing = FastOutSlowInEasing))
    }

    // Which over the skyline is really about. Drawn solid while the rest are held back,
    // so the shape of the innings and its high point are one read instead of two.
    val peakIndex = inn.progress.indexOfFirst { it.runs == peak }

    Column {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(132.dp)
        ) {
            val n = inn.progress.size
            if (n == 0) return@Canvas
            val gap = if (n > 14) 2.dp.toPx() else 5.dp.toPx()
            val barW = ((size.width - gap * (n - 1)) / n).coerceAtLeast(2f)
            val floor = size.height - 1.dp.toPx()

            inn.progress.forEachIndexed { i, over ->
                val h = (over.runs / peak.toFloat()) * (floor - 16f) * grow.value
                val x = i * (barW + gap)
                val y = floor - h
                val isPeak = i == peakIndex

                // Solid at the base, lighter at the top.
                //
                // It was the other way round, and bars that fade out where they meet the
                // axis have nothing to stand on — the chart dissolved into the page and
                // read as decoration rather than measurement. Weight belongs at the
                // bottom, which is also where the eye looks to compare heights.
                drawRoundRect(
                    brush = Brush.verticalGradient(
                        listOf(
                            accent.copy(alpha = if (isPeak) 0.85f else 0.42f),
                            accent.copy(alpha = if (isPeak) 1f else 0.72f),
                        ),
                        startY = y,
                        endY = floor,
                    ),
                    topLeft = Offset(x, y),
                    size = Size(barW, h.coerceAtLeast(2f)),
                    cornerRadius = androidx.compose.ui.geometry.CornerRadius(3f, 3f),
                )

                // Wickets sit ABOVE their own over, so a cheap over that took two is
                // visibly the important one rather than the smallest bar on the chart.
                // Ringed in the page colour: at three pixels on a coloured bar they were
                // invisible, which is a poor way to mark the most important ball of an over.
                repeat(over.wickets.coerceAtMost(3)) { w ->
                    val c = Offset(x + barW / 2f, y - 8f - (w * 10f))
                    drawCircle(color = CrexColors.Surface, radius = 5.4f, center = c)
                    drawCircle(color = CrexColors.WicketBall, radius = 3.6f, center = c)
                }
            }

            // The axis the bars stand on.
            drawRect(
                color = CrexColors.Border,
                topLeft = Offset(0f, floor),
                size = Size(size.width, 1.dp.toPx()),
            )
        }
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Over 1", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.weight(1f))
            // What the tall bar is worth, in words. A skyline with no figure on it makes
            // the reader estimate against an axis that isn't labelled.
            inn.progress.getOrNull(peakIndex)?.let { top ->
                Text(
                    "Highest: ${top.runs} in over ${top.over}",
                    color = CrexColors.TextSecondary,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                )
                Spacer(Modifier.weight(1f))
            }
            Text(
                "Over ${inn.progress.lastOrNull()?.over ?: 0}",
                color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

/**
 * The worm — both innings on one axis.
 *
 * Overlaid rather than shown one after the other, because a chase is only meaningful
 * against what it is chasing. Two lines on one set of axes answers "were they ahead?" at a
 * glance; two separate charts make the reader hold one in their head while looking at the
 * other.
 */
@Composable
internal fun Worm(innings: List<InningsInsight>, colours: List<Color>) {
    val allOvers = innings.maxOfOrNull { it.progress.size } ?: 0
    val peak = innings.flatMap { it.progress }.maxOfOrNull { it.total } ?: 0

    Column {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(150.dp)
        ) {
            if (allOvers < 2 || peak <= 0) return@Canvas
            val stepX = size.width / (allOvers - 1).coerceAtLeast(1).toFloat()
            val yOf = { total: Int -> size.height - (total / peak.toFloat()) * (size.height - 10f) }

            innings.forEachIndexed { idx, inn ->
                val colour = colours.getOrElse(idx) { CrexColors.AccentBlue }
                val pts = inn.progress
                if (pts.size < 2) return@forEachIndexed

                val path = Path().apply {
                    moveTo(0f, yOf(pts.first().total))
                    pts.forEachIndexed { i, o -> lineTo(i * stepX, yOf(o.total)) }
                }
                drawPath(path, color = colour, style = Stroke(width = 4f, cap = StrokeCap.Round))

                // A wicket is a dot on that side's own line, at the over it fell.
                pts.forEachIndexed { i, o ->
                    if (o.wickets > 0) {
                        drawCircle(colour, radius = 4.5f, center = Offset(i * stepX, yOf(o.total)))
                        drawCircle(Color.White, radius = 2f, center = Offset(i * stepX, yOf(o.total)))
                    }
                }
            }
        }
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            innings.forEachIndexed { idx, inn ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier
                            .size(width = 14.dp, height = 3.dp)
                            .background(colours.getOrElse(idx) { CrexColors.AccentBlue })
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        "${inn.battingName}  ${inn.runs}/${inn.wickets}",
                        color = CrexColors.TextSecondary,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

/**
 * A ground, drawn rather than photographed.
 *
 * Used as the backdrop to an innings' headline score — the same instinct as a broadcast
 * lower-third sitting over the outfield. It is deliberately DECORATION and carries no data:
 * nothing is plotted on it, because the ball log records no direction, and a ground with
 * invented shot positions on it would be the most convincing lie on the whole screen.
 */
@Composable
internal fun GroundBackdrop(accent: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        // Anchored off the right edge and kept very faint. The first attempt sat a strong
        // oval across the whole card and read as a pink wash over the numbers — a backdrop
        // that competes with the content it is behind is worse than no backdrop.
        val cx = size.width * 1.02f
        val cy = size.height * 0.52f
        val r = size.height * 0.92f

        drawCircle(accent.copy(alpha = 0.030f), radius = r, center = Offset(cx, cy))
        drawCircle(
            accent.copy(alpha = 0.075f), radius = r,
            center = Offset(cx, cy), style = Stroke(width = 1.4f),
        )
        drawCircle(
            accent.copy(alpha = 0.055f), radius = r * 0.56f,
            center = Offset(cx, cy), style = Stroke(width = 1.1f),
        )
        // The square, small and central to the circle rather than floating mid-card.
        drawRoundRect(
            color = accent.copy(alpha = 0.055f),
            topLeft = Offset(cx - r * 0.05f, cy - r * 0.26f),
            size = Size(r * 0.10f, r * 0.52f),
            cornerRadius = androidx.compose.ui.geometry.CornerRadius(2f, 2f),
        )
    }
}

