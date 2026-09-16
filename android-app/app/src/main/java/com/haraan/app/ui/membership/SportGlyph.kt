package com.haraan.app.ui.membership

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
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlin.math.cos
import kotlin.math.sin

/**
 * Premium, uniform vector sport icons for all 8 supported sports.
 * Designed on a normalized 24x24 coordinate grid with consistent stroke weight,
 * smooth geometry, and balanced visual weight.
 */
@Composable
fun SportGlyph(sport: String, tint: Color, size: Dp = 24.dp, modifier: Modifier = Modifier) {
    Canvas(modifier.size(size)) {
        val u = this.size.minDimension / 24f
        val stroke = Stroke(width = 1.6f * u, cap = StrokeCap.Round, join = StrokeJoin.Round)
        val thinStroke = Stroke(width = 1.1f * u, cap = StrokeCap.Round, join = StrokeJoin.Round)

        when (sport.lowercase()) {
            "cricket" -> drawCricketGlyph(tint, u, stroke)
            "football" -> drawFootballGlyph(tint, u, stroke, thinStroke)
            "badminton" -> drawBadmintonGlyph(tint, u, stroke, thinStroke)
            "volleyball" -> drawVolleyballGlyph(tint, u, stroke)
            "basketball" -> drawBasketballGlyph(tint, u, stroke)
            "kabaddi" -> drawKabaddiGlyph(tint, u, stroke)
            "tennis" -> drawTennisGlyph(tint, u, stroke, thinStroke)
            "table_tennis" -> drawTableTennisGlyph(tint, u, stroke, thinStroke)
            else -> drawCircle(tint, radius = 7f * u, center = center, style = stroke)
        }
    }
}

/** Cricket: Angled cricket bat blade + handle wraps, alongside seam ball. */
private fun DrawScope.drawCricketGlyph(tint: Color, u: Float, stroke: Stroke) {
    rotate(-30f, pivot = center) {
        // Bat blade
        val blade = Path().apply {
            moveTo(10f * u, 7f * u)
            lineTo(14f * u, 7f * u)
            lineTo(14f * u, 20f * u)
            quadraticTo(14f * u, 21.5f * u, 12f * u, 21.5f * u)
            quadraticTo(10f * u, 21.5f * u, 10f * u, 20f * u)
            close()
        }
        drawPath(blade, tint)

        // Bat handle
        drawLine(
            tint,
            start = Offset(12f * u, 7f * u),
            end = Offset(12f * u, 2.5f * u),
            strokeWidth = 2.4f * u,
            cap = StrokeCap.Round,
        )
    }

    // Cricket ball with raised seam
    val bx = 18f * u
    val by = 16.5f * u
    val br = 3.2f * u
    drawCircle(tint, radius = br, center = Offset(bx, by))
    drawCircle(Color.White.copy(alpha = 0.45f), radius = br * 0.7f, center = Offset(bx, by), style = stroke)
}

/** Football / Soccer: Classic 32-panel geometric soccer ball. */
private fun DrawScope.drawFootballGlyph(tint: Color, u: Float, stroke: Stroke, thinStroke: Stroke) {
    val cx = center.x
    val cy = center.y
    val r = 9.2f * u

    // Ball outline
    drawCircle(tint, radius = r, center = center, style = stroke)

    // Center pentagon
    val pr = 3.6f * u
    val pentagon = Path()
    for (i in 0 until 5) {
        val angle = Math.toRadians((i * 72.0 - 90.0)).toFloat()
        val px = cx + pr * cos(angle)
        val py = cy + pr * sin(angle)
        if (i == 0) pentagon.moveTo(px, py) else pentagon.lineTo(px, py)
    }
    pentagon.close()
    drawPath(pentagon, tint)

    // Radiating seams to outer edge
    for (i in 0 until 5) {
        val angle = Math.toRadians((i * 72.0 - 90.0)).toFloat()
        val px = cx + pr * cos(angle)
        val py = cy + pr * sin(angle)
        val ex = cx + r * cos(angle)
        val ey = cy + r * sin(angle)
        drawLine(tint, start = Offset(px, py), end = Offset(ex, ey), strokeWidth = thinStroke.width)
    }
}

/** Badminton: Feathered shuttlecock in dynamic tilted flight. */
private fun DrawScope.drawBadmintonGlyph(tint: Color, u: Float, stroke: Stroke, thinStroke: Stroke) {
    rotate(-30f, pivot = center) {
        val cx = center.x

        // Cork base (solid dome)
        val cork = Path().apply {
            moveTo(cx - 3.2f * u, 17f * u)
            lineTo(cx + 3.2f * u, 17f * u)
            quadraticTo(cx + 3.2f * u, 21.5f * u, cx, 21.5f * u)
            quadraticTo(cx - 3.2f * u, 21.5f * u, cx - 3.2f * u, 17f * u)
            close()
        }
        drawPath(cork, tint)

        // Skirt outline
        val skirt = Path().apply {
            moveTo(cx - 2.8f * u, 16.5f * u)
            lineTo(cx - 7.5f * u, 4f * u)
            quadraticTo(cx, 1.8f * u, cx + 7.5f * u, 4f * u)
            lineTo(cx + 2.8f * u, 16.5f * u)
            close()
        }
        drawPath(skirt, tint, style = stroke)

        // Feather rib lines
        drawLine(tint, Offset(cx - 1f * u, 16f * u), Offset(cx - 2.5f * u, 3.2f * u), strokeWidth = thinStroke.width)
        drawLine(tint, Offset(cx + 1f * u, 16f * u), Offset(cx + 2.5f * u, 3.2f * u), strokeWidth = thinStroke.width)

        // Horizontal binding bands
        drawLine(tint, Offset(cx - 4.2f * u, 12f * u), Offset(cx + 4.2f * u, 12f * u), strokeWidth = thinStroke.width)
        drawLine(tint, Offset(cx - 5.6f * u, 8f * u), Offset(cx + 5.6f * u, 8f * u), strokeWidth = thinStroke.width)
    }
}

/** Volleyball: Standard 3-lobe curved seam panels. */
private fun DrawScope.drawVolleyballGlyph(tint: Color, u: Float, stroke: Stroke) {
    val cx = center.x
    val cy = center.y
    val r = 9.2f * u

    drawCircle(tint, radius = r, center = center, style = stroke)

    // Three curved seams radiating outward
    for (i in 0 until 3) {
        val angle = i * 120f
        rotate(angle, pivot = center) {
            val seam1 = Path().apply {
                moveTo(cx, cy)
                quadraticTo(cx + 4f * u, cy - 4.5f * u, cx + 8.2f * u, cy - 4.2f * u)
            }
            val seam2 = Path().apply {
                moveTo(cx, cy)
                quadraticTo(cx - 4.5f * u, cy + 4f * u, cx - 4.2f * u, cy + 8.2f * u)
            }
            drawPath(seam1, tint, style = stroke)
            drawPath(seam2, tint, style = stroke)
        }
    }
}

/** Basketball: Crisp circular ball with cross and side arc seams. */
private fun DrawScope.drawBasketballGlyph(tint: Color, u: Float, stroke: Stroke) {
    val cx = center.x
    val cy = center.y
    val r = 9.2f * u

    drawCircle(tint, radius = r, center = center, style = stroke)

    // Cross seams
    drawLine(tint, Offset(cx - r, cy), Offset(cx + r, cy), strokeWidth = stroke.width)
    drawLine(tint, Offset(cx, cy - r), Offset(cx, cy + r), strokeWidth = stroke.width)

    // Side curved ribs
    val leftRib = Path().apply {
        moveTo(cx - 5.5f * u, cy - 7.5f * u)
        quadraticTo(cx - 1.5f * u, cy, cx - 5.5f * u, cy + 7.5f * u)
    }
    val rightRib = Path().apply {
        moveTo(cx + 5.5f * u, cy - 7.5f * u)
        quadraticTo(cx + 1.5f * u, cy, cx + 5.5f * u, cy + 7.5f * u)
    }
    drawPath(leftRib, tint, style = stroke)
    drawPath(rightRib, tint, style = stroke)
}

/** Kabaddi: Raider in dynamic athletic reach stance. */
private fun DrawScope.drawKabaddiGlyph(tint: Color, u: Float, stroke: Stroke) {
    // Raider head
    drawCircle(tint, radius = 2.4f * u, center = Offset(15f * u, 5.5f * u))

    // Torso and lunging limbs
    val torso = Path().apply {
        // Torso
        moveTo(14f * u, 8f * u)
        lineTo(10.5f * u, 13.5f * u)
        // Back leg
        lineTo(6f * u, 19f * u)
        lineTo(3.5f * u, 18.5f * u)
    }
    drawPath(torso, tint, style = stroke)

    // Front bent knee leg
    val frontLeg = Path().apply {
        moveTo(10.5f * u, 13.5f * u)
        lineTo(14.5f * u, 16.5f * u)
        lineTo(15.5f * u, 21f * u)
    }
    drawPath(frontLeg, tint, style = stroke)

    // Outstretched raiding arm (reaching left)
    val raidingArm = Path().apply {
        moveTo(13f * u, 9.5f * u)
        lineTo(8f * u, 10f * u)
        lineTo(4.5f * u, 8.5f * u)
    }
    drawPath(raidingArm, tint, style = stroke)

    // Back arm
    val backArm = Path().apply {
        moveTo(13.5f * u, 9.5f * u)
        lineTo(17.5f * u, 11.5f * u)
        lineTo(19.5f * u, 10f * u)
    }
    drawPath(backArm, tint, style = stroke)
}

/** Tennis: Strung tennis racket with tennis ball. */
private fun DrawScope.drawTennisGlyph(tint: Color, u: Float, stroke: Stroke, thinStroke: Stroke) {
    rotate(-40f, pivot = center) {
        val cx = 12f * u

        // Racket head (oval)
        val headPath = Path().apply {
            addOval(androidx.compose.ui.geometry.Rect(cx - 5.5f * u, 2f * u, cx + 5.5f * u, 13f * u))
        }
        drawPath(headPath, tint, style = stroke)

        // Strings
        drawLine(tint, Offset(cx - 3.5f * u, 7.5f * u), Offset(cx + 3.5f * u, 7.5f * u), strokeWidth = thinStroke.width)
        drawLine(tint, Offset(cx, 3.5f * u), Offset(cx, 11.5f * u), strokeWidth = thinStroke.width)

        // Throat & handle
        drawLine(tint, Offset(cx, 13f * u), Offset(cx, 22f * u), strokeWidth = 2.4f * u, cap = StrokeCap.Round)
    }

    // Tennis ball
    val bx = 18f * u
    val by = 16f * u
    val br = 3.2f * u
    drawCircle(tint, radius = br, center = Offset(bx, by), style = stroke)
    val seam = Path().apply {
        moveTo(bx - 2.2f * u, by - 1.2f * u)
        quadraticTo(bx, by, bx - 2.2f * u, by + 1.2f * u)
    }
    drawPath(seam, tint, style = thinStroke)
}

/** Table Tennis: Ping pong paddle with blade, handle, and floating ball. */
private fun DrawScope.drawTableTennisGlyph(tint: Color, u: Float, stroke: Stroke, thinStroke: Stroke) {
    rotate(-35f, pivot = center) {
        // Paddle blade (circle)
        drawCircle(tint, radius = 6.2f * u, center = Offset(11f * u, 9f * u))

        // Handle
        drawRoundRect(
            tint,
            topLeft = Offset(9.4f * u, 14.5f * u),
            size = Size(3.2f * u, 7.5f * u),
            cornerRadius = CornerRadius(1.4f * u),
        )
    }

    // Ping pong ball
    drawCircle(tint, radius = 2.6f * u, center = Offset(18.5f * u, 17f * u))
    // Motion dot
    drawCircle(tint.copy(alpha = 0.5f), radius = 1.2f * u, center = Offset(19.8f * u, 13f * u))
}
