package com.haraan.app.ui.payment

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.unit.dp

/**
 * Haraan Pay's own payment-method glyphs.
 *
 * Material's `QrCode` / `CreditCard` / `AccountBalance` set is the single loudest "nobody drew
 * this" signal a payment sheet can carry — it is the same stock artwork every tutorial app ships.
 * The partner app already left it behind for hand-drawn strokes; this is the same language, so the
 * two apps look like they came from one studio.
 *
 * Every glyph exists twice on the *same* geometry:
 *   - [IDLE] — 1.7 stroke, nothing filled.
 *   - [ON]   — 2.05 stroke plus the shape's core filled in.
 *
 * Sharing the skeleton is load-bearing: a method card cross-fades the two, and the eye reads one
 * icon *thickening* rather than two pictures swapping. If you add a glyph, draw the outline first
 * and then add fills — never move a line between the variants.
 *
 * Drawn on a 24x24 viewport, round caps and joins throughout. Judge every one at true 24.dp before
 * believing it: interior detail inside a rounded rectangle turns to mush at that size, which is why
 * these lean on irregular silhouettes.
 */
internal object HaraanPayIcons {

    // ---- UPI: two arrows passing each other --------------------------------
    // Not a QR square. A QR glyph is a speckled box that collapses into a grey blob at 24.dp, and
    // it describes the *scanner*, not the payment. Opposing arrows say "money moves, instantly",
    // which is the whole promise of UPI, and the silhouette survives shrinking.
    private const val UPI_ARROWS =
        "M4.6 9.1 H16.8 M13.5 5.8 L16.8 9.1 L13.5 12.4 " +
            "M19.4 14.9 H7.2 M10.5 11.6 L7.2 14.9 L10.5 18.2"
    private const val UPI_HEADS =
        "M13.2 5.4 L17.5 9.1 L13.2 12.8 Z M10.8 11.2 L6.5 14.9 L10.8 18.6 Z"

    val UpiOutline = icon("UpiOutline") { stroke(UPI_ARROWS, IDLE) }
    val UpiActive = icon("UpiActive") {
        stroke(UPI_ARROWS, ON)
        fill(UPI_HEADS)
    }

    // ---- Card: a body with a magnetic stripe -------------------------------
    private const val CARD_BODY =
        "M5.6 5.8 H18.4 A2 2 0 0 1 20.4 7.8 V16.2 A2 2 0 0 1 18.4 18.2 H5.6 " +
            "A2 2 0 0 1 3.6 16.2 V7.8 A2 2 0 0 1 5.6 5.8 Z"
    private const val CARD_STRIPE = "M3.6 10.1 H20.4"
    private const val CARD_NUMBER = "M7.1 14.4 H11.3"
    private const val CARD_STRIPE_SOLID = "M4.0 9.2 H20.0 V11.3 H4.0 Z"

    val CardOutline = icon("CardOutline") {
        stroke(CARD_BODY, IDLE)
        stroke(CARD_STRIPE, IDLE)
        stroke(CARD_NUMBER, IDLE)
    }
    val CardActive = icon("CardActive") {
        stroke(CARD_BODY, ON)
        fill(CARD_STRIPE_SOLID)
        stroke(CARD_NUMBER, IDLE)
    }

    // ---- Netbanking: a pediment over columns -------------------------------
    private const val BANK_ROOF = "M3.4 9.5 L12 4.3 L20.6 9.5"
    private const val BANK_SHELF = "M4.8 9.5 H19.2"
    private const val BANK_COLUMNS = "M7.2 12.1 V17.2 M12 12.1 V17.2 M16.8 12.1 V17.2"
    private const val BANK_BASE = "M4.4 19.7 H19.6"
    private const val BANK_ROOF_SOLID = "M3.4 9.5 L12 4.3 L20.6 9.5 Z"

    val BankOutline = icon("BankOutline") {
        stroke(BANK_ROOF, IDLE)
        stroke(BANK_SHELF, IDLE)
        stroke(BANK_COLUMNS, IDLE)
        stroke(BANK_BASE, IDLE)
    }
    val BankActive = icon("BankActive") {
        fill(BANK_ROOF_SOLID, alpha = 0.22f)
        stroke(BANK_ROOF, ON)
        stroke(BANK_SHELF, ON)
        stroke(BANK_COLUMNS, IDLE)
        stroke(BANK_BASE, ON)
    }

    // ---- More ways to pay: a wallet with a side pocket ---------------------
    private const val WALLET_BODY =
        "M5.6 5.8 H18.4 A2 2 0 0 1 20.4 7.8 V16.2 A2 2 0 0 1 18.4 18.2 H5.6 " +
            "A2 2 0 0 1 3.6 16.2 V7.8 A2 2 0 0 1 5.6 5.8 Z"
    private const val WALLET_POCKET =
        "M20.4 10.4 H16.1 A1.6 1.6 0 0 0 16.1 13.6 H20.4"
    private const val WALLET_CLASP =
        "M17.1 12 A0.85 0.85 0 1 1 18.8 12 A0.85 0.85 0 1 1 17.1 12 Z"

    val WalletOutline = icon("WalletOutline") {
        stroke(WALLET_BODY, IDLE)
        stroke(WALLET_POCKET, IDLE)
        stroke(WALLET_CLASP, IDLE)
    }
    val WalletActive = icon("WalletActive") {
        stroke(WALLET_BODY, ON)
        stroke(WALLET_POCKET, ON)
        fill(WALLET_CLASP)
    }

    // ---- Builders ----------------------------------------------------------

    /** Resting stroke weight. */
    private const val IDLE = 1.7f

    /** Selected stroke weight — pressed-in, not bold. */
    private const val ON = 2.05f

    private fun icon(name: String, body: ImageVector.Builder.() -> Unit): ImageVector =
        ImageVector.Builder(
            name = name,
            defaultWidth = 24.dp,
            defaultHeight = 24.dp,
            viewportWidth = 24f,
            viewportHeight = 24f,
        ).apply(body).build()

    // Paths are laid down in black; `Icon` re-tints the whole vector, and SrcIn keeps per-path
    // alpha — which is how the two-tone selected state survives the tint.
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
