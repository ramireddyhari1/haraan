package com.haraan.partner

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.unit.dp

/**
 * The partner app's own navigation glyphs.
 *
 * Material's stock filled icons (Home, Place, Payments, QrCodeScanner) are solid
 * silhouettes drawn for 2014 Android — heavy, tightly packed, and instantly
 * recognisable as "the default set". They read as dated next to this app's thin
 * hairlines and light surfaces, so the five destinations are drawn here instead.
 *
 * Every glyph exists twice on the *same* geometry:
 *   - [outline] — 1.7 stroke, nothing filled: the resting state.
 *   - [active]  — 2.05 stroke plus the shape's core filled in.
 *
 * Because the two variants share their skeleton, the bar can cross-dissolve
 * between them and the eye reads a single icon *thickening and filling*, not one
 * picture swapping for another. Keep that property if you add a sixth glyph:
 * draw the outline first, then add fills — never move a line between variants.
 *
 * Drawn on a 24x24 viewport with round caps and joins throughout, so the strokes
 * stay optically even at the 23.dp the bar renders them at.
 */
internal object HaraanNavIcons {

    // ---- Home: a roof over a body, with an arched door ----------------------
    private const val HOUSE =
        "M3.7 10.3 L12 3.9 L20.3 10.3 V18.5 A2 2 0 0 1 18.3 20.5 H5.7 A2 2 0 0 1 3.7 18.5 Z"
    private const val DOOR = "M10 20.5 V16.4 A2 2 0 0 1 14 16.4 V20.5"

    val HomeOutline = icon("HomeOutline") {
        stroke(HOUSE, IDLE)
        stroke(DOOR, IDLE)
    }
    val HomeActive = icon("HomeActive") {
        stroke(HOUSE, ON)
        fill("$DOOR Z")
    }

    // ---- Bookings / Sales: a receipt ---------------------------------------
    // Two ticket shapes were tried first and both failed the only test that
    // matters — being legible at 24.dp. A card with a tear line reads as a
    // battery at that size, whichever way round it is drawn. The receipt wins on
    // its torn foot: an irregular silhouette survives shrinking where an interior
    // detail inside a rounded rectangle does not. It also says the right thing —
    // this tab is money taken, not seats reserved.
    private const val RECEIPT =
        "M5.6 3.8 H18.4 V19.2 L15.9 17.7 L13.4 19.2 L10.9 17.7 L8.4 19.2 L5.6 17.7 Z"
    private const val RECEIPT_LINES = "M8.6 8.6 H15.4 M8.6 12.4 H13.4"

    val ReceiptOutline = icon("ReceiptOutline") {
        stroke(RECEIPT, IDLE)
        stroke(RECEIPT_LINES, IDLE)
    }
    val ReceiptActive = icon("ReceiptActive") {
        // A wash across the whole body rather than one filled element: the paper
        // has no "core" to light up, and anything heavier closes the lines in.
        fill(RECEIPT, alpha = 0.15f)
        stroke(RECEIPT, ON)
        stroke(RECEIPT_LINES, IDLE)
    }

    // ---- Venues / Outlets: a pin ------------------------------------------
    private const val PIN =
        "M12 21.1 C12 21.1 19 15.3 19 10.6 A7 7 0 1 0 5 10.6 C5 15.3 12 21.1 12 21.1 Z"
    private const val PIN_EYE = "M9.6 10.6 A2.4 2.4 0 1 1 14.4 10.6 A2.4 2.4 0 1 1 9.6 10.6 Z"

    val PinOutline = icon("PinOutline") {
        stroke(PIN, IDLE)
        stroke(PIN_EYE, IDLE)
    }
    val PinActive = icon("PinActive") {
        stroke(PIN, ON)
        fill(PIN_EYE)
    }

    // ---- Events: a calendar whose today-square lights up -------------------
    private const val CAL_BODY =
        "M5.6 6.6 H18.4 A2 2 0 0 1 20.4 8.6 V18.4 A2 2 0 0 1 18.4 20.4 H5.6 " +
            "A2 2 0 0 1 3.6 18.4 V8.6 A2 2 0 0 1 5.6 6.6 Z"
    private const val CAL_RAIL = "M3.6 11.2 H20.4"
    private const val CAL_HANGERS = "M8.2 3.6 V7.5 M15.8 3.6 V7.5"
    private const val CAL_TODAY =
        "M7.4 14 H9.8 A0.9 0.9 0 0 1 10.7 14.9 V16.7 A0.9 0.9 0 0 1 9.8 17.6 H7.4 " +
            "A0.9 0.9 0 0 1 6.5 16.7 V14.9 A0.9 0.9 0 0 1 7.4 14 Z"

    val CalendarOutline = icon("CalendarOutline") {
        stroke(CAL_BODY, IDLE)
        stroke(CAL_RAIL, IDLE)
        stroke(CAL_HANGERS, IDLE)
        stroke(CAL_TODAY, IDLE)
    }
    val CalendarActive = icon("CalendarActive") {
        stroke(CAL_BODY, ON)
        stroke(CAL_RAIL, ON)
        stroke(CAL_HANGERS, ON)
        fill(CAL_TODAY)
    }

    // ---- Scan: a viewfinder, four corners and a beam ------------------------
    private const val CORNERS =
        "M3.6 8.8 V6.2 A2.6 2.6 0 0 1 6.2 3.6 H8.8 " +
            "M15.2 3.6 H17.8 A2.6 2.6 0 0 1 20.4 6.2 V8.8 " +
            "M20.4 15.2 V17.8 A2.6 2.6 0 0 1 17.8 20.4 H15.2 " +
            "M8.8 20.4 H6.2 A2.6 2.6 0 0 1 3.6 17.8 V15.2"
    private const val BEAM = "M5.3 12 H18.7"
    private const val BEAM_SOLID =
        "M6.2 11.1 H17.8 A0.9 0.9 0 0 1 17.8 12.9 H6.2 A0.9 0.9 0 0 1 6.2 11.1 Z"

    val ScanOutline = icon("ScanOutline") {
        stroke(CORNERS, IDLE)
        stroke(BEAM, IDLE)
    }
    val ScanActive = icon("ScanActive") {
        stroke(CORNERS, ON)
        fill(BEAM_SOLID)
    }

    // ---- Matches: a corner flag ---------------------------------------------
    // A scoreboard read as a computer monitor at 24.dp and a "VS" turned to mush;
    // the flag keeps an unmistakable silhouette when it shrinks, and says pitch
    // rather than podium — this tab is games in progress, not a leaderboard.
    private const val FLAG = "M7.2 3.6 V20.4 M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2"
    private const val PENNANT = "M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2 Z"

    val FlagOutline = icon("FlagOutline") {
        stroke(FLAG, IDLE)
    }
    val FlagActive = icon("FlagActive") {
        fill(PENNANT, alpha = 0.28f)
        stroke(FLAG, ON)
    }

    // ---- Builders ----------------------------------------------------------

    /** Resting stroke weight. */
    private const val IDLE = 1.7f

    /** Selected stroke weight — heavy enough to feel pressed-in, not bold. */
    private const val ON = 2.05f

    private fun icon(name: String, body: ImageVector.Builder.() -> Unit): ImageVector =
        ImageVector.Builder(
            name = name,
            defaultWidth = 24.dp,
            defaultHeight = 24.dp,
            viewportWidth = 24f,
            viewportHeight = 24f,
        ).apply(body).build()

    // Paths are laid down in black; `Icon` re-tints the whole vector, and SrcIn
    // keeps per-path alpha — which is how the two-tone selected state survives.
    private fun ImageVector.Builder.stroke(pathData: String, width: Float) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = null,
            stroke = SolidColor(Color.Black),
            strokeLineWidth = width,
            strokeLineCap = StrokeCap.Round,
            strokeLineJoin = StrokeJoin.Round,
        )
    }

    private fun ImageVector.Builder.fill(pathData: String, alpha: Float = 1f) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = SolidColor(Color.Black),
            fillAlpha = alpha,
        )
    }
}
