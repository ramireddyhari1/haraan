package com.haraan.app.ui.matches

import android.graphics.Bitmap
import android.graphics.Paint
import android.graphics.Path
import android.graphics.PathMeasure
import android.graphics.Typeface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat

import com.haraan.app.R
import kotlin.math.atan2
import kotlin.math.roundToInt

// ─────────────────────────────────────────────────────────────────────────────
//  THE SEAM RIBBON
//
//  One implementation of the crawling word-mark that wraps the hero card, shared
//  by the cricket board and the five sport boards so a SIX and a SPIKE ride the
//  same rail. The ribbon alternates: word, mark, word, mark — the brand H stamped
//  between repeats, cut in the word's own colour, so the green of a six and the
//  blue of a four each carry their own monogram.
// ─────────────────────────────────────────────────────────────────────────────

/** Ribbon type size — the mark is cut to match, so both share a baseline. */
private val RibbonTextSize = 10.5.dp

/** Smallest gap between repeats, used when the ribbon is running text-only. */
private const val RibbonMinGap = 5

/** How much air the logotype gets around it, as a multiple of its own width. */
private const val RibbonMarkAir = 2.9f

/** Monogram height as a fraction of the type size — a shade over the caps. */
private const val RibbonMarkScale = 0.92f

/**
 * Corner radius of the white band the ribbon rides, and of the card inside it.
 *
 * The turn is the whole design constraint here. Type is laid out straight and only
 * then bent onto the path, so a corner barely wider than a letter is tall shears the
 * letters and stacks them on each other. The fix is not to cut the corner — that walks
 * the ribbon off its band and over the card — but to give the card a corner wide
 * enough to turn in. The band's own inner edge is [RibbonCardRadius] - band, which
 * keeps the white ring exactly as thick round the corners as it is down the sides.
 */
internal val RibbonCardRadius = 38.dp

/**
 * The calm state already says HARAAN in words; stamping the logotype beside it
 * would say the name twice per repeat. Only the event words get the mark.
 */
internal fun ribbonWantsMark(word: String): Boolean = !word.contains("HARAAN")

/**
 * The monogram, rasterised once per (colour, size) and stamped along the path.
 * Returns null when the mark isn't wanted, which the draw pass reads as
 * "text only" — that keeps the decision in one place.
 */
@Composable
internal fun rememberRibbonMark(word: String, argb: Int): Bitmap? {
    val ctx = LocalContext.current
    val heightPx = with(LocalDensity.current) { (RibbonTextSize.toPx() * RibbonMarkScale).roundToInt() }
    val wanted = ribbonWantsMark(word)
    return remember(argb, heightPx, wanted) {
        if (!wanted || heightPx <= 0) return@remember null
        val art = ContextCompat.getDrawable(ctx, R.drawable.ic_haraan_ribbon_mark)?.mutate()
            ?: return@remember null
        art.setTint(argb)
        val ratio = art.intrinsicWidth.toFloat() / art.intrinsicHeight.coerceAtLeast(1).toFloat()
        val widthPx = (heightPx * ratio).roundToInt().coerceAtLeast(1)
        Bitmap.createBitmap(widthPx, heightPx, Bitmap.Config.ARGB_8888).also { bmp ->
            art.setBounds(0, 0, widthPx, heightPx)
            art.draw(android.graphics.Canvas(bmp))
        }
    }
}

/**
 * Draws one full turn of the ribbon around the card's white band.
 *
 * @param phase already wrapped to [0,1) — one segment's worth of travel.
 * @param mark  the tinted monogram from [rememberRibbonMark], or null for text only.
 */
internal fun DrawScope.drawSeamRibbon(
    word: String,
    argb: Int,
    band: Dp,
    phase: Float,
    mark: Bitmap?,
) {
    val center = band.toPx() / 2f
    val radius = (RibbonCardRadius.toPx() - center)
        .coerceIn(0f, minOf(size.width, size.height) / 2f - center)
    val paint = Paint().apply {
        isAntiAlias = true
        color = argb
        textSize = RibbonTextSize.toPx()
        letterSpacing = 0.1f
        typeface = Typeface.create(Typeface.DEFAULT_BOLD, Typeface.BOLD)
    }
    val path = Path().apply {
        addRoundRect(
            center, center, size.width - center, size.height - center,
            radius, radius, Path.Direction.CW,
        )
    }
    // Measure the real contour rather than the bounding rectangle: the rounded
    // corners cut a few pixels off each turn, and text laid out against the longer
    // figure arrives back at the start early — the seam this fitting exists to hide.
    val measure = PathMeasure(path, false)
    val perimeter = measure.length
    if (perimeter <= 0f) return

    // Size the gap to the mark it has to hold, so the pair reads as one unit however
    // long the word is — WICKET and SIX get the same air around their H.
    val spaceWidth = paint.measureText(" ").coerceAtLeast(1f)
    val gapSpaces = if (mark == null) RibbonMinGap else
        Math.max(RibbonMinGap, Math.ceil(((mark.width * RibbonMarkAir) / spaceWidth).toDouble()).toInt())
    val segment = word + " ".repeat(gapSpaces)
    // Make the pattern tile the loop EXACTLY. A repeat that doesn't divide into the
    // perimeter leaves a part-glyph sitting on the join, which renders as a small dark
    // block on the left edge — the one flaw on an otherwise clean card. Stretching the
    // letter spacing by a fraction of a pixel per character makes the repeat periodic,
    // so the text meets itself with no seam at all.
    val rawWidth = paint.measureText(segment).coerceAtLeast(1f)
    val segs = Math.round(perimeter / rawWidth).coerceIn(2, 80)
    val target = perimeter / segs
    paint.letterSpacing += (target - rawWidth) / segment.length / paint.textSize
    val segWidth = paint.measureText(segment).coerceAtLeast(1f)
    val pos = FloatArray(2)
    val tan = FloatArray(2)
    val fm = paint.fontMetrics
    val vOffset = -(fm.ascent + fm.descent) / 2f  // centre the glyphs on the path line
    val canvas = drawContext.canvas.nativeCanvas
    val baseAlpha = android.graphics.Color.alpha(argb)
    val wordWidth = paint.measureText(word)
    val gapCentre = wordWidth + (segWidth - wordWidth) / 2f
    val markPaint = if (mark == null) null else Paint().apply { isFilterBitmap = true }

    // Every repeat is placed and drawn on its own rather than as one long string laid
    // round the loop: a repeat's real advance differs from the measured segment by a
    // fraction of a pixel, and multiplying that out walks the mark into the word it
    // follows. Placing each at an exact distance also keeps the crawl unbroken — the
    // ribbon runs straight through the corners rather than clearing them.
    paint.alpha = baseAlpha
    for (i in 0 until segs) {
        val start = (i * segWidth - phase * segWidth).mod(perimeter)
        canvas.drawTextOnPath(word, path, start, vOffset, paint)
        // A unit sitting on the join renders in two halves: glyphs past the end of
        // the contour are dropped rather than wrapped, so the tail is drawn again
        // one perimeter back, where the head of the path picks it up.
        if (start + wordWidth > perimeter) {
            canvas.drawTextOnPath(word, path, start - perimeter, vOffset, paint)
        }
        if (mark == null || markPaint == null) continue

        val centre = (start + gapCentre).mod(perimeter)
        if (!measure.getPosTan(centre, pos, tan)) continue
        // Follow the path, but never past upright: the bottom edge runs right-to-left,
        // and a word can survive being read upside down where the brand mark cannot.
        var degrees = Math.toDegrees(atan2(tan[1].toDouble(), tan[0].toDouble())).toFloat()
        if (degrees > 90f) degrees -= 180f
        if (degrees < -90f) degrees += 180f
        markPaint.alpha = baseAlpha
        canvas.save()
        canvas.translate(pos[0], pos[1])
        canvas.rotate(degrees)
        canvas.drawBitmap(mark, -mark.width / 2f, -mark.height / 2f, markPaint)
        canvas.restore()
    }
}

