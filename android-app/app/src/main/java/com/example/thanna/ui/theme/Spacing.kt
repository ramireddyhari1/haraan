package com.example.thanna.ui.theme

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
