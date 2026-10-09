package com.haraan.app.ui.matches

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlin.math.cos
import kotlin.math.sin

/**
 * Hand-drawn glyphs for the match tabs — each one a cricket object, not a stock icon:
 * the ground (Info), the broadcast mic (Commentary), the signal (Live), the scoreboard
 * (Scorecard), the trophy (MVP) and the wagon wheel (Insights).
 *
 * Two states, drawn differently rather than just recoloured: idle is a quiet outline;
 * selected is inked, gets a soft blue body and ONE red detail — the same red as the tab
 * underline, so the icon and the indicator read as one mark.
 */
@Composable
internal fun MatchTabIcon(index: Int, selected: Boolean, live: Boolean, size: Dp = 18.dp) {
    val scaled = size * LocalDensity.current.fontScale.coerceIn(1f, 1.25f)
    val ink = if (selected) CrexColors.TextPrimary else CrexColors.TextMuted
    val body = if (selected) CrexColors.AccentBlue.copy(alpha = 0.12f) else Color.Transparent
    val spark = if (selected || (index == 2 && live)) CrexColors.AccentRed else CrexColors.TextMuted
    Canvas(Modifier.size(scaled)) {
        val g = Glyph(this, ink, body, spark, selected)
        when (index) {
            0 -> g.ground()
            1 -> g.mic()
            2 -> g.signal()
            3 -> g.scoreboard()
            4 -> g.trophy()
            else -> g.wagonWheel()
        }
    }
}

/** Draws on a 24-unit grid so every glyph shares one stroke weight and optical size. */
private class Glyph(
    val d: DrawScope,
    val ink: Color,
    val body: Color,
    val spark: Color,
    val on: Boolean,
) {
    val u = d.size.minDimension / 24f
    val stroke = Stroke(width = 1.55f * u, cap = StrokeCap.Round, join = StrokeJoin.Round)
    fun p(x: Float, y: Float) = Offset(x * u, y * u)
    fun line(x1: Float, y1: Float, x2: Float, y2: Float, c: Color = ink, w: Float = 1.7f) =
        d.drawLine(c, p(x1, y1), p(x2, y2), strokeWidth = w * u, cap = StrokeCap.Round)

    /** Info — the ground from above: the rope, the 30-yard ring, the pitch with its stumps. */
    fun ground() {
        val c = p(12f, 12f)
        d.drawCircle(if (on) Color(0xFF22A06B).copy(alpha = 0.18f) else Color.Transparent, radius = 9.8f * u, center = c)
        d.drawCircle(ink, radius = 9.8f * u, center = c, style = stroke)
        // The 30-yard ring, dashed.
        for (k in 0 until 10) {
            d.drawArc(ink.copy(alpha = 0.45f), k * 36f, 20f, false, p(5.6f, 5.6f), Size(12.8f * u, 12.8f * u), style = Stroke(1f * u, cap = StrokeCap.Round))
        }
        d.drawRoundRect(if (on) Color(0xFFE8D3A6) else Color.Transparent, p(10.7f, 7.6f), Size(2.6f * u, 8.8f * u), CornerRadius(0.6f * u))
        d.drawRoundRect(ink, p(10.7f, 7.6f), Size(2.6f * u, 8.8f * u), CornerRadius(0.6f * u), style = Stroke(1.3f * u))
        line(11.4f, 8.6f, 12.6f, 8.6f, spark, 1.2f)
        line(11.4f, 15.4f, 12.6f, 15.4f, spark, 1.2f)
    }

    /** Commentary — a broadcast mic on its yoke. */
    fun mic() {
        d.drawRoundRect(body, p(8.5f, 2f), Size(7f * u, 11.5f * u), CornerRadius(3.5f * u))
        d.drawRoundRect(ink, p(8.5f, 2f), Size(7f * u, 11.5f * u), CornerRadius(3.5f * u), style = stroke)
        // Grille.
        line(10.6f, 5.6f, 13.4f, 5.6f, spark, 1.2f)
        line(10.6f, 8.2f, 13.4f, 8.2f, ink.copy(alpha = 0.6f), 1.2f)
        // Yoke, stem, base.
        d.drawArc(ink, 0f, 180f, false, p(5.5f, 4.5f), Size(13f * u, 12.5f * u), style = stroke)
        line(12f, 17f, 12f, 20.5f)
        line(8.5f, 21f, 15.5f, 21f)
    }

    /** Live — a dot sending signal both ways. */
    fun signal() {
        val c = p(12f, 12f)
        d.drawCircle(spark, radius = 2.6f * u, center = c)
        if (on) d.drawCircle(spark.copy(alpha = 0.18f), radius = 4.4f * u, center = c)
        for ((r, a) in listOf(6.2f to 1f, 9.6f to 0.6f)) {
            val tl = p(12f - r, 12f - r)
            val sz = Size(2 * r * u, 2 * r * u)
            d.drawArc(ink.copy(alpha = a), -38f, 76f, false, tl, sz, style = stroke)
            d.drawArc(ink.copy(alpha = a), 142f, 76f, false, tl, sz, style = stroke)
        }
    }

    /** Scorecard — a ground scoreboard on two posts, header lit, rows of figures. */
    fun scoreboard() {
        line(7f, 18f, 7f, 22f)
        line(17f, 18f, 17f, 22f)
        d.drawRoundRect(body, p(2.5f, 3f), Size(19f * u, 15f * u), CornerRadius(2.4f * u))
        // Header band.
        d.drawRoundRect(if (on) spark.copy(alpha = 0.9f) else ink.copy(alpha = 0.18f), p(2.5f, 3f), Size(19f * u, 4.6f * u), CornerRadius(2.4f * u))
        d.drawRoundRect(ink, p(2.5f, 3f), Size(19f * u, 15f * u), CornerRadius(2.4f * u), style = stroke)
        // Rows: a name bar and a figure box each.
        for (y in listOf(10.6f, 14.4f)) {
            line(5.4f, y, 12.5f, y, ink.copy(alpha = 0.75f), 1.4f)
            d.drawRoundRect(ink.copy(alpha = 0.85f), p(15f, y - 1.3f), Size(3.6f * u, 2.6f * u), CornerRadius(0.6f * u), style = Stroke(1.2f * u))
        }
    }

    /** MVP — a trophy cup with handles, a star struck on the bowl. */
    fun trophy() {
        val cup = Path().apply {
            moveTo(6.5f * u, 3f * u)
            lineTo(17.5f * u, 3f * u)
            cubicTo(17.5f * u, 10f * u, 15.5f * u, 13.5f * u, 12f * u, 13.8f * u)
            cubicTo(8.5f * u, 13.5f * u, 6.5f * u, 10f * u, 6.5f * u, 3f * u)
            close()
        }
        d.drawPath(cup, body)
        d.drawPath(cup, ink, style = stroke)
        // Handles.
        d.drawArc(ink, 90f, 180f, false, p(3f, 4.2f), Size(4.4f * u, 5.6f * u), style = stroke)
        d.drawArc(ink, -90f, 180f, false, p(16.6f, 4.2f), Size(4.4f * u, 5.6f * u), style = stroke)
        // Stem and plinth.
        line(12f, 14f, 12f, 17.4f)
        d.drawRoundRect(if (on) body else Color.Transparent, p(7.8f, 17.6f), Size(8.4f * u, 3.6f * u), CornerRadius(1f * u))
        d.drawRoundRect(ink, p(7.8f, 17.6f), Size(8.4f * u, 3.6f * u), CornerRadius(1f * u), style = stroke)
        star(p(12f, 7.8f), 2.6f * u, spark)
    }

    /** Insights — a wagon wheel: the field, the pitch, the shots; one carries to the rope. */
    fun wagonWheel() {
        val c = p(12f, 12f)
        d.drawCircle(body, radius = 9.5f * u, center = c)
        d.drawCircle(ink, radius = 9.5f * u, center = c, style = stroke)
        val shots = listOf(-60f to 0.62f, -10f to 0.78f, 35f to 0.55f, 120f to 0.7f, 165f to 0.5f, 215f to 0.66f)
        for ((deg, len) in shots) {
            val r = 9.5f * u * len
            val a = Math.toRadians(deg.toDouble())
            d.drawLine(ink.copy(alpha = 0.55f), c, Offset(c.x + r * cos(a).toFloat(), c.y + r * sin(a).toFloat()), strokeWidth = 1.2f * u, cap = StrokeCap.Round)
        }
        // The boundary — all the way to the rope, ball at the end.
        val a = Math.toRadians(-118.0)
        val end = Offset(c.x + 9.5f * u * cos(a).toFloat(), c.y + 9.5f * u * sin(a).toFloat())
        d.drawLine(spark, c, end, strokeWidth = 1.6f * u, cap = StrokeCap.Round)
        d.drawCircle(spark, radius = 1.9f * u, center = end)
        d.drawRoundRect(ink, p(11f, 10f), Size(2f * u, 4f * u), CornerRadius(0.5f * u))
    }

    private fun star(c: Offset, r: Float, color: Color) {
        val path = Path()
        for (i in 0 until 10) {
            val rr = if (i % 2 == 0) r else r * 0.45f
            val a = Math.toRadians(-90.0 + i * 36.0)
            val x = c.x + rr * cos(a).toFloat()
            val y = c.y + rr * sin(a).toFloat()
            if (i == 0) path.moveTo(x, y) else path.lineTo(x, y)
        }
        path.close()
        d.drawPath(path, color)
    }
}
