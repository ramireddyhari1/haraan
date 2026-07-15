package com.haraan.app.ui.theme

import androidx.compose.foundation.background
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.layout.layout
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

object HaraanRadius {
    val Small = 12.dp     // Badges, Chips, minor elements
    val Medium = 16.dp    // Category items, small buttons
    val Large = 24.dp     // SearchBar, Segmented toggles, cards
    val Hero = 32.dp      // Hero Carousel cards
}

// ─────────────────────────────────────────────
//  PREMIUM DEPTH — two-layer soft shadow.
//  Flat Material elevation reads "template". Funded apps stack a tight, slightly
//  darker contact shadow under a wide, faint ambient one. Apply on the card's
//  Modifier and set the Card's own elevation to 0.dp to avoid doubling.
//  Tinted toward midnight slate (0xFF0F172A) rather than pure black so it sits
//  in the slate-on-light brand instead of muddying it.
// ─────────────────────────────────────────────
private val ShadowTint = Color(0xFF0F172A)

fun Modifier.premiumCardShadow(
    radius: androidx.compose.ui.unit.Dp = HaraanRadius.Medium,
    ambient: androidx.compose.ui.unit.Dp = 18.dp,
    contact: androidx.compose.ui.unit.Dp = 3.dp,
): Modifier {
    val shape: Shape = RoundedCornerShape(radius)
    return this
        // Wide, faint ambient layer — gives the soft "floating" halo.
        .shadow(
            elevation = ambient,
            shape = shape,
            clip = false,
            ambientColor = ShadowTint.copy(alpha = 0.10f),
            spotColor = ShadowTint.copy(alpha = 0.12f),
        )
        // Tight contact layer — grounds the card so it doesn't look like a sticker.
        .shadow(
            elevation = contact,
            shape = shape,
            clip = false,
            ambientColor = ShadowTint.copy(alpha = 0.16f),
            spotColor = ShadowTint.copy(alpha = 0.20f),
        )
}

// ─────────────────────────────────────────────
//  ONE CARD LANGUAGE — the single surface treatment used for every content card
//  (ActionBoard, venue cards, widgets). White fill, rounded, soft premium shadow,
//  NO border — the shadow alone separates the card from the background. Replacing
//  the old mix of gradient borders / flat borders / mint fills with this kills the
//  "five different cards" visual noise.
// ─────────────────────────────────────────────
// Pulls a node UP by [overlap] AND shrinks its reported height by the same amount, so the
// element straddles the node above it (e.g. a card over a colored hero) WITHOUT leaving a gap
// below — unlike a plain Modifier.offset, which shifts visually but keeps its full slot height.
fun Modifier.overlapAbove(overlap: Dp): Modifier = this.layout { measurable, constraints ->
    val placeable = measurable.measure(constraints)
    val o = overlap.roundToPx()
    layout(placeable.width, (placeable.height - o).coerceAtLeast(0)) {
        placeable.place(0, -o)
    }
}

fun Modifier.haraanCard(
    radius: androidx.compose.ui.unit.Dp = HaraanRadius.Large,
    fill: Color = Color.White,
): Modifier {
    val shape: Shape = RoundedCornerShape(radius)
    return this
        .premiumCardShadow(radius = radius)
        .clip(shape)
        .background(fill)
}
