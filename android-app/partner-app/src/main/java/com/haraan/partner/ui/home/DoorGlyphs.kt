package com.haraan.partner.ui.home

import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.unit.dp

/**
 * Glyphs for Home's desk doors, drawn in the same hand as the bottom bar
 * (HaraanNavIcons): 24-unit grid, 1.8 round strokes, one detail at lower alpha.
 * Stock Material icons here were the loudest "template" signal on the screen.
 */
internal object DoorGlyphs {

    /** A customer at the counter, and the plus of adding them. */
    val WalkIn: ImageVector = icon("WalkIn") {
        stroke("M9.2 11.2 A3.4 3.4 0 1 0 9.2 4.4 A3.4 3.4 0 1 0 9.2 11.2 Z", 1.8f)
        stroke("M3.2 20 C3.6 16.4 6 14.4 9.2 14.4 C11.1 14.4 12.7 15.1 13.8 16.3", 1.8f)
        stroke("M18.2 13.2 V19.6 M15 16.4 H21.4", 1.9f)
        fill("M9.2 11.2 A3.4 3.4 0 1 0 9.2 4.4 A3.4 3.4 0 1 0 9.2 11.2 Z", 0.16f)
    }

    /** A sheet with its corner folded, three bars rising inside. */
    val Reports: ImageVector = icon("Reports") {
        stroke("M6.4 3.4 H14.4 L19 8 V19.2 A1.6 1.6 0 0 1 17.4 20.8 H6.4 A1.6 1.6 0 0 1 4.8 19.2 V5 A1.6 1.6 0 0 1 6.4 3.4 Z", 1.8f)
        stroke("M14.2 3.6 V8.2 H18.8", 1.5f, alpha = 0.55f)
        stroke("M8.6 17 V14.6 M11.9 17 V12.2 M15.2 17 V10.2", 1.9f)
    }

    /** The bank the money goes to: pediment, three columns, a step. */
    val Settlement: ImageVector = icon("Settlement") {
        stroke("M3.6 9 L12 3.8 L20.4 9 Z", 1.8f)
        fill("M3.6 9 L12 3.8 L20.4 9 Z", 0.16f)
        stroke("M6.6 11.6 V17 M12 11.6 V17 M17.4 11.6 V17", 1.8f)
        stroke("M3.6 20.2 H20.4", 1.9f)
        stroke("M5 17.8 H19", 1.4f, alpha = 0.55f)
    }

    private fun icon(name: String, body: ImageVector.Builder.() -> Unit): ImageVector =
        ImageVector.Builder(name = name, defaultWidth = 24.dp, defaultHeight = 24.dp, viewportWidth = 24f, viewportHeight = 24f)
            .apply(body).build()

    private fun ImageVector.Builder.stroke(d: String, w: Float, alpha: Float = 1f) {
        addPath(
            pathData = addPathNodes(d), fill = null, stroke = SolidColor(Color.Black), strokeAlpha = alpha,
            strokeLineWidth = w, strokeLineCap = StrokeCap.Round, strokeLineJoin = StrokeJoin.Round,
        )
    }

    private fun ImageVector.Builder.fill(d: String, alpha: Float) {
        addPath(pathData = addPathNodes(d), fill = SolidColor(Color.Black), fillAlpha = alpha)
    }
}

private val WhatsAppGreen = Color(0xFF25D366)
private val LockedGrey = Color(0xFFA3ADBB)

/** The handset inside WhatsApp's bubble, on a 24-unit grid. */
private const val HANDSET =
    "M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"

/**
 * WhatsApp's own mark: the green speech bubble with its tail at the lower left and a
 * white handset inside. [locked] greys it out for a feature that isn't switched on yet.
 */
@Composable
internal fun WhatsAppMark(locked: Boolean, modifier: Modifier = Modifier) {
    val handset = remember { PathParser().parsePathString(HANDSET).toPath() }
    Canvas(modifier) {
        val s = size.minDimension
        val c = Offset(size.width / 2, size.height / 2)
        val r = s * 0.42f
        val color = if (locked) LockedGrey else WhatsAppGreen
        // Bubble: a circle with a short tail pointing down-left.
        val bubble = Path().apply {
            addOval(androidx.compose.ui.geometry.Rect(c, r))
            moveTo(c.x - r * 0.62f, c.y + r * 0.62f)
            lineTo(c.x - r * 1.02f, c.y + r * 1.04f)
            lineTo(c.x - r * 0.22f, c.y + r * 0.92f)
            close()
        }
        drawPath(bubble, color)
        // Handset, scaled into the middle of the bubble.
        val box = r * 1.08f
        translate(left = c.x - box / 2, top = c.y - box / 2) {
            scale(box / 24f, box / 24f, pivot = Offset.Zero) { drawPath(handset, Color.White) }
        }
    }
}
