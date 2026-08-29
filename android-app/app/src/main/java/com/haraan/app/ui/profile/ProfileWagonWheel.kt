package com.haraan.app.ui.profile

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.CareerAnalysis
import com.haraan.app.data.WagonWheel
import com.haraan.app.data.WagonZone
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow
import kotlin.math.cos
import kotlin.math.max
import kotlin.math.sin

// ─────────────────────────────────────────────────────────────────────────────
//  THE WAGON WHEEL
//
//  The one chart in cricket that says something no table can: not how many runs
//  a player scored, but WHERE. A batter who scores 60% of their runs square is a
//  different cricketer from one who only goes straight, and no average, strike
//  rate or boundary count will ever tell you which one you are looking at.
//
//  Every line on this ground is a boundary the scorer actually placed while the
//  match was being played. Nothing is reconstructed, spread evenly, or filled in
//  to make the wheel look busier than the record is — an empty half of the ground
//  is information, and faking a shot into it would destroy the only thing the
//  chart has, which is that it happened.
// ─────────────────────────────────────────────────────────────────────────────

private val Grass = Color(0xFF2F7D4F)
private val GrassDeep = Color(0xFF276843)
private val FourInk = Color(0xFF9BE7FF)
private val SixInk = Color(0xFFFFD166)

@Composable
fun WagonWheelCard(wagon: WagonWheel) {
    val grow = remember(wagon) { Animatable(0f) }
    LaunchedEffect(wagon) {
        // The shots are drawn one after another rather than all at once, so the wheel
        // reads as an innings being played rather than a graphic being pasted in.
        grow.animateTo(1f, tween(durationMillis = 900, easing = FastOutSlowInEasing))
    }

    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 20.dp, ambient = 16.dp, contact = 2.dp)
            .clip(RoundedCornerShape(20.dp))
            .background(HaraanColors.Surface)
            .border(1.dp, Color(0xFFEDF1F6), RoundedCornerShape(20.dp))
            .padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier
                        .width(3.dp)
                        .height(15.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(
                            Brush.verticalGradient(
                                listOf(HaraanColors.EventsBlue, HaraanColors.EventsBlue.copy(alpha = 0.5f)),
                            ),
                        ),
                )
                Spacer(Modifier.width(9.dp))
                Text(
                    wagon.title,
                    color = HaraanColors.TextPrimary,
                    fontSize = 15.5.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = (-0.2).sp,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "${wagon.total}",
                    color = HaraanColors.EventsBlue,
                    fontSize = 28.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = (-0.8).sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text(
                    "RUNS PLACED",
                    color = HaraanColors.TextMuted,
                    fontSize = 10.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    letterSpacing = 0.7.sp,
                )
            }
        }

        Spacer(Modifier.height(16.dp))
        Box(
            Modifier
                .fillMaxWidth()
                .aspectRatio(1f)
                .clip(RoundedCornerShape(18.dp)),
        ) {
            Canvas(Modifier.fillMaxWidth().aspectRatio(1f)) {
                drawGround()
                drawShots(wagon.zones, wagon.total, grow.value)
            }
        }

        Spacer(Modifier.height(14.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            LegendDot(FourInk, "Fours")
            Spacer(Modifier.width(16.dp))
            LegendDot(SixInk, "Sixes")
            Spacer(Modifier.weight(1f))
            Text(
                "${wagon.shots} placed",
                color = HaraanColors.TextMuted,
                fontSize = 12.sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }

        wagon.caption?.let {
            Spacer(Modifier.height(12.dp))
            Text(it, color = HaraanColors.TextSecondary, fontSize = 12.5.sp, lineHeight = 18.sp)
        }

        // The regions themselves, so the drawing is readable to someone who wants the
        // figure rather than the shape.
        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFEDF1F6)))
        Spacer(Modifier.height(14.dp))
        wagon.zones.sortedByDescending { it.runs }.take(4).forEachIndexed { i, zone ->
            if (i > 0) Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    zone.label,
                    color = HaraanColors.TextSecondary,
                    fontSize = 13.sp,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    "${zone.runs}",
                    color = HaraanColors.TextPrimary,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    buildString {
                        if (zone.fours > 0) append("${zone.fours}×4")
                        if (zone.fours > 0 && zone.sixes > 0) append("  ")
                        if (zone.sixes > 0) append("${zone.sixes}×6")
                    },
                    color = HaraanColors.TextMuted,
                    fontSize = 12.sp,
                    modifier = Modifier.width(72.dp),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

@Composable
private fun LegendDot(color: Color, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(8.dp).clip(RoundedCornerShape(4.dp)).background(color))
        Spacer(Modifier.width(7.dp))
        Text(label, color = HaraanColors.TextSecondary, fontSize = 12.5.sp)
    }
}

/** The ground: an outfield, a 30-yard circle, and the strip the shots leave from. */
private fun DrawScope.drawGround() {
    val radius = size.minDimension / 2f
    val centre = Offset(size.width / 2f, size.height / 2f)

    drawCircle(
        brush = Brush.radialGradient(
            colors = listOf(Grass, GrassDeep),
            center = centre,
            radius = radius,
        ),
        radius = radius,
        center = centre,
    )
    // Mown rings rather than mown stripes: a wheel is read radially, and stripes fight
    // the lines drawn on top of them.
    for (i in 1..4) {
        drawCircle(
            color = Color.White.copy(alpha = 0.05f),
            radius = radius * (i / 5f),
            center = centre,
            style = Stroke(width = radius * 0.12f),
        )
    }
    drawCircle(
        color = Color.White.copy(alpha = 0.55f),
        radius = radius * 0.97f,
        center = centre,
        style = Stroke(width = 2.dp.toPx()),
    )
    drawCircle(
        color = Color.White.copy(alpha = 0.35f),
        radius = radius * 0.55f,
        center = centre,
        style = Stroke(width = 1.2.dp.toPx()),
    )
    // The pitch, with the batter's end at the centre — which is where every line on a
    // wagon wheel starts.
    val pitchW = radius * 0.10f
    val pitchH = radius * 0.30f
    drawRoundRect(
        color = Color(0xFFCBBF92).copy(alpha = 0.9f),
        topLeft = Offset(centre.x - pitchW / 2f, centre.y - pitchH * 0.62f),
        size = androidx.compose.ui.geometry.Size(pitchW, pitchH),
        cornerRadius = androidx.compose.ui.geometry.CornerRadius(2.dp.toPx()),
    )
}

/**
 * One line per zone, leaving the middle: length carries the runs scored there and the
 * colour says whether they came in fours or sixes.
 */
private fun DrawScope.drawShots(zones: List<WagonZone>, total: Int, progress: Float) {
    if (zones.isEmpty()) return
    val radius = size.minDimension / 2f
    val centre = Offset(size.width / 2f, size.height / 2f)
    val most = max(1, zones.maxOf { it.runs })

    zones.forEachIndexed { index, zone ->
        // Each line starts a little after the one before it, so the wheel fills in the
        // order a scorer would have entered it.
        val slice = 1f / zones.size
        val local = ((progress - index * slice * 0.6f) / (1f - index * slice * 0.6f)).coerceIn(0f, 1f)
        if (local <= 0f) return@forEachIndexed

        val angle = Math.toRadians((zone.zone.coerceIn(0, 7) * 45.0) - 90.0)
        // Never shorter than half the ground: a single run in a region should still be
        // a visible shot, not a stub that reads as an error.
        val reach = (0.52f + 0.45f * (zone.runs.toFloat() / most)) * radius * local
        val end = Offset(
            centre.x + (cos(angle) * reach).toFloat(),
            centre.y + (sin(angle) * reach).toFloat(),
        )
        val ink = if (zone.sixes > zone.fours) SixInk else FourInk

        drawLine(
            color = ink.copy(alpha = 0.28f),
            start = centre,
            end = end,
            strokeWidth = 6.dp.toPx(),
            cap = StrokeCap.Round,
        )
        drawLine(
            color = ink,
            start = centre,
            end = end,
            strokeWidth = 2.4.dp.toPx(),
            cap = StrokeCap.Round,
        )
        // A dot where the ball crossed, sized by how many shots went there.
        drawCircle(
            color = ink,
            radius = (2.6f + 1.1f * zone.shots.coerceAtMost(4)).dp.toPx() * local,
            center = end,
        )
        drawCircle(
            color = Color.White.copy(alpha = 0.85f),
            radius = 1.3.dp.toPx() * local,
            center = end,
        )
    }
    // Every line leaves from the same point, so the middle is the busiest part of the
    // ground. A small knock-out keeps the origin readable where six strokes converge —
    // without it the pitch disappears under them.
    drawCircle(color = GrassDeep, radius = radius * 0.055f, center = centre)
    drawCircle(
        color = Color.White.copy(alpha = 0.5f),
        radius = radius * 0.055f,
        center = centre,
        style = Stroke(width = 1.dp.toPx()),
    )
}

// ─────────────────────────────────────────────────────────── The read ─────

/**
 * What the figures add up to, in words.
 *
 * Marked as written, not counted. Every number in these sentences was handed to the
 * model from the tables above and copied; the model is forbidden to produce one of its
 * own. Saying so on the card is the difference between a feature a player trusts and
 * one that quietly poisons every real figure around it.
 */
@Composable
fun CareerAnalysisCard(analysis: CareerAnalysis) {
    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 20.dp, ambient = 16.dp, contact = 2.dp)
            .clip(RoundedCornerShape(20.dp))
            .background(HaraanColors.Surface)
            .border(1.dp, Color(0xFFEDF1F6), RoundedCornerShape(20.dp))
            .padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .width(3.dp)
                    .height(15.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(
                        Brush.verticalGradient(
                            listOf(HaraanColors.EventsBlue, HaraanColors.EventsBlue.copy(alpha = 0.5f)),
                        ),
                    ),
            )
            Spacer(Modifier.width(9.dp))
            Text(
                analysis.title,
                color = HaraanColors.TextPrimary,
                fontSize = 15.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = (-0.2).sp,
                modifier = Modifier.weight(1f),
            )
            Box(
                Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(HaraanColors.AccentTint)
                    .padding(horizontal = 9.dp, vertical = 4.dp),
            ) {
                Text(
                    "AI",
                    color = HaraanColors.EventsBlue,
                    fontSize = 9.5.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 0.7.sp,
                )
            }
        }
        Spacer(Modifier.height(16.dp))
        analysis.lines.forEachIndexed { i, line ->
            if (i > 0) Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.Top) {
                // A rule per observation, not a bullet: three dots down a card of prose
                // reads as a checklist of instructions the player is meant to follow.
                Box(
                    Modifier
                        .padding(top = 6.dp)
                        .width(14.dp)
                        .height(2.dp)
                        .clip(RoundedCornerShape(1.dp))
                        .background(HaraanColors.EventsBlue.copy(alpha = 0.45f)),
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    line,
                    color = HaraanColors.TextPrimary,
                    fontSize = 13.5.sp,
                    lineHeight = 20.sp,
                )
            }
        }
        if (analysis.source.isNotBlank()) {
            Spacer(Modifier.height(16.dp))
            Text(
                analysis.source,
                color = HaraanColors.TextMuted,
                fontSize = 11.5.sp,
                lineHeight = 16.sp,
            )
        }
    }
}
