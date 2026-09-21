package com.haraan.app.vision

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.abs

/**
 * The delivery's numbers, kept deliberately quiet.
 *
 * SECONDARY BY DESIGN. The ball and its flight are the thing being looked at; this is the
 * caption underneath. So there is no card, no fill, no accent colour on the values and
 * nothing boxed — a hairline rule, small type, and values in a monospace column so the eye
 * can run down them without being pulled away from the footage. If it ever competes with
 * the trail for attention it has been built wrong.
 *
 * EVERY ROW CARRIES ITS PROVENANCE. A filled dot is measured, a hollow one is estimated, a
 * dash is unavailable — and the unavailable rows stay on screen with the reason beside
 * them rather than being hidden. A missing row reads as an oversight; a row that says "no
 * pitch calibration" reads as the truth, and tells whoever is holding the phone what to go
 * and do about it.
 *
 * NO DECISIONS. Nothing here says out, not out, pitched in line or anything adjacent to
 * one. The pipeline measures a ball; umpiring is a different product with a different
 * standard of evidence, and the gap between them is not one a fading trail can close.
 */
@Composable
fun FlightMetricsStrip(
    metrics: FlightMetrics,
    modifier: Modifier = Modifier,
    showUnavailable: Boolean = true,
) {
    Column(modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
        Text(
            "DELIVERY",
            color = Color.White.copy(alpha = 0.32f),
            fontSize = 9.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(8.dp))

        val rows = listOf(
            "Speed" to metrics.imageSpeed,
            "Speed · ground" to metrics.groundSpeed,
            "Curve" to metrics.curve,
            "Swing" to metrics.swing,
            "Off the pitch" to metrics.lateral,
            "Spin" to metrics.spin,
            "Bounce · length" to metrics.bounceLength,
            "Bounce · line" to metrics.bounceLine,
            "Track score" to metrics.trackScore,
        )

        rows.forEachIndexed { index, (label, value) ->
            if (value is MetricValue.Unavailable && !showUnavailable) return@forEachIndexed
            if (index > 0) Spacer(Modifier.height(1.dp))
            MetricRow(label, value)
        }
    }
}

@Composable
private fun MetricRow(label: String, value: MetricValue) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 3.dp),
        verticalAlignment = Alignment.Top,
    ) {
        ProvenanceMark(value)
        Spacer(Modifier.size(8.dp))

        Column(Modifier.weight(1f)) {
            Text(
                label,
                color = Color.White.copy(alpha = if (value is MetricValue.Unavailable) 0.32f else 0.62f),
                fontSize = 11.sp,
            )
            // The small print is the product here, not a footnote to it.
            noteFor(value)?.let {
                Text(
                    it,
                    color = Color.White.copy(alpha = 0.26f),
                    fontSize = 9.sp,
                    lineHeight = 12.sp,
                )
            }
        }

        Text(
            formatValue(value),
            color = when (value) {
                is MetricValue.Measured -> Color.White.copy(alpha = 0.92f)
                is MetricValue.Estimated -> Color.White.copy(alpha = 0.72f)
                is MetricValue.Unavailable -> Color.White.copy(alpha = 0.28f)
            },
            fontSize = 12.sp,
            fontFamily = FontFamily.Monospace,
        )
    }
}

/**
 * Filled, hollow, or a dash.
 *
 * A shape rather than a colour, because colour alone would be invisible to a reader who
 * cannot see the difference between the green and the amber this app already uses for
 * everything else — and because three states of one dot is quieter than three words.
 */
@Composable
private fun ProvenanceMark(value: MetricValue) {
    Box(Modifier.size(width = 8.dp, height = 16.dp), contentAlignment = Alignment.Center) {
        when (value) {
            is MetricValue.Measured -> Box(
                Modifier.size(6.dp).clip(CircleShape).background(VisionPalette.GOOD.copy(alpha = 0.85f)),
            )

            is MetricValue.Estimated -> Box(
                Modifier.size(6.dp).clip(CircleShape).background(VisionPalette.WARN.copy(alpha = 0.30f)),
            ) {
                Box(
                    Modifier
                        .size(2.dp)
                        .align(Alignment.Center)
                        .clip(CircleShape)
                        .background(VisionPalette.WARN),
                )
            }

            is MetricValue.Unavailable -> Box(
                Modifier.size(width = 6.dp, height = 1.dp).background(Color.White.copy(alpha = 0.25f)),
            )
        }
    }
}

/** The word under the label, and for an unavailable row the reason it is not there. */
private fun noteFor(value: MetricValue): String? = when (value) {
    is MetricValue.Measured -> value.caveat?.let { "measured · $it" } ?: "measured"
    is MetricValue.Estimated -> "estimated · ${value.basis}"
    is MetricValue.Unavailable -> "unavailable · ${value.reason}"
}

/**
 * Significant figures the reading can actually support.
 *
 * A bounce length printed to the centimetre would be claiming a precision the calibration
 * does not have, which [Bounce] says in as many words. Two decimals for the small
 * normalised numbers, one for metres, and a dash where there is nothing.
 */
fun metricText(value: MetricValue): String = formatValue(value)

private fun formatValue(value: MetricValue): String = when (value) {
    is MetricValue.Measured -> render(value.value, value.unit)
    is MetricValue.Estimated -> render(value.value, value.unit)
    is MetricValue.Unavailable -> "—"
}

private fun render(value: Double, unit: String): String {
    if (value.isNaN() || value.isInfinite()) return "—"
    val text = when {
        unit == "m" -> "%.1f".format(value)
        unit.isEmpty() -> "%.0f%%".format(value * 100)
        abs(value) >= 10 -> "%.1f".format(value)
        else -> "%.2f".format(value)
    }
    return if (unit.isEmpty() || unit == "%") text else "$text $unit"
}
