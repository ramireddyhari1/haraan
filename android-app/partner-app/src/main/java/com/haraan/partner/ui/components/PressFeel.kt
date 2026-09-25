package com.haraan.partner.ui.components

import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.unit.Dp
import com.haraan.partner.ui.Haptics

/**
 * How a tappable surface answers a finger: it gives a little, and it darkens.
 *
 * Material's ripple spreads a flat grey wash across a card, which is how a web page
 * answers a click. A physical card sinks under the thumb and comes back with a small
 * overshoot when released. The two halves sit at different points in the modifier
 * chain, so they are two modifiers sharing one [MutableInteractionSource]:
 *
 * ```
 * Modifier
 *     .pressScale(interaction)          // before the clip, so the shadow scales too
 *     .clip(shape).background(...)
 *     .pressShade(interaction)          // after the background, so it's clipped to it
 *     .clickable(interaction, indication = null) { ... }
 * ```
 */
fun Modifier.pressScale(
    interaction: MutableInteractionSource,
    pressedScale: Float = 0.97f,
): Modifier = composed {
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed) pressedScale else 1f,
        // Under-damped on the way back up: the card settles with a slight bounce
        // instead of snapping flat, which is most of what makes it feel solid.
        animationSpec = spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessMedium),
        label = "press-scale",
    )
    graphicsLayer { scaleX = scale; scaleY = scale }
}

/** The darkening half of the press. [amount] is the black overlay at full press. */
fun Modifier.pressShade(
    interaction: MutableInteractionSource,
    amount: Float = 0.05f,
): Modifier = composed {
    val pressed by interaction.collectIsPressedAsState()
    val shade by animateFloatAsState(
        targetValue = if (pressed) amount else 0f,
        // In fast so the finger sees it land, out slow so a quick tap still shows.
        animationSpec = tween(durationMillis = if (pressed) 70 else 260),
        label = "press-shade",
    )
    drawWithContent {
        drawContent()
        if (shade > 0f) drawRect(Color.Black.copy(alpha = shade))
    }
}

/**
 * Press feel, tap tick and click in one modifier, for small tiles and rows that
 * are built as a single modifier chain (the day grid's slot cells, booking rows).
 *
 * Put it FIRST in the chain, before size/clip/background: the scale then covers
 * the whole tile, and the shade is drawn over everything as a rounded rect of
 * [cornerRadius], matching the tile's own clip.
 */
fun Modifier.pressableTile(
    cornerRadius: Dp,
    enabled: Boolean = true,
    pressedScale: Float = 0.94f,
    onClick: () -> Unit,
): Modifier = composed {
    val interaction = remember { MutableInteractionSource() }
    val view = LocalView.current
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed && enabled) pressedScale else 1f,
        animationSpec = spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessMedium),
        label = "tile-scale",
    )
    val shade by animateFloatAsState(
        targetValue = if (pressed && enabled) 0.06f else 0f,
        animationSpec = tween(durationMillis = if (pressed) 70 else 260),
        label = "tile-shade",
    )
    graphicsLayer { scaleX = scale; scaleY = scale }
        .drawWithContent {
            drawContent()
            if (shade > 0f) {
                drawRoundRect(
                    Color.Black.copy(alpha = shade),
                    cornerRadius = CornerRadius(cornerRadius.toPx()),
                )
            }
        }
        .clickable(interactionSource = interaction, indication = null, enabled = enabled) {
            Haptics.tick(view)
            onClick()
        }
}
