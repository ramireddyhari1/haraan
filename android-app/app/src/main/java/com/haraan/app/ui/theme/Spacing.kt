package com.haraan.app.ui.theme

import androidx.compose.ui.unit.dp

// Complete 8pt grid. Reference these on every screen instead of raw `.dp` literals —
// off-grid spacing (3/11/15.dp) is the single biggest "hand-tuned / indie" tell.
object HaraanSpacing {
    val Micro = 4.dp        // tightest gap (label→value, score→name)
    val Small = 8.dp        // chip gaps, inline gaps
    val Compact = 12.dp     // dense intra-section spacing
    val Medium = 16.dp      // card inner padding, screen gutter
    val Cozy = 20.dp        // generous intra-card spacing
    val Large = 24.dp       // section → section
    val XLarge = 32.dp      // major block separation
}

/**
 * Bottom-of-screen rules, applied everywhere so the app has ONE answer for
 * "how tall is the bar and how does it clear the system navigation":
 *
 *     ┌──────────────────────┐
 *     │  Bottom nav bar      │  56dp  ← content only
 *     ├──────────────────────┤
 *     │  System navigation   │  the inset, whatever the device reports
 *     └──────────────────────┘
 *
 * The 56dp is the BAR ITSELF and must never be padded to include the system
 * navigation area. Clearance comes from `Modifier.navigationBarsPadding()`,
 * which reports the real inset — ~16dp on a gesture-nav device like the one in
 * the spec, but ~48dp with 3-button navigation. Hardcoding 16dp would put the
 * bar underneath the buttons on those devices, so the inset is always read from
 * the system, never assumed.
 *
 * Order matters: apply `.navigationBarsPadding()` BEFORE `.height()`, so the
 * height describes the bar and the padding sits outside it.
 */
object HaraanBottomBar {
    /** Height of the bottom navigation bar's own content. */
    val Height = 56.dp
}
