package com.haraan.app.ui

import android.os.Build
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView

/**
 * How the app answers a finger.
 *
 * Screens here were built as opaque cards with a bare `clickable` on top, which
 * puts the ripple *underneath* the fill: nothing moves, nothing ticks, and a
 * selection only appears once the finger lifts. That is the difference between a
 * screen that feels like an app and one that feels like a web form.
 *
 * Shared rather than per-screen so the same gesture never means two different
 * things in two places.
 */
object Feel {
    /** Discrete increments — one per step of a counter. */
    const val TICK = HapticFeedbackConstants.CLOCK_TICK

    /** Picking one thing out of several: a chip, a card, a toggle. */
    const val SELECT = HapticFeedbackConstants.KEYBOARD_TAP

    /**
     * Something landed: a match created, an ID copied. Heavier and distinct from
     * every other tap. CONFIRM is API 30+, so older devices get the nearest thing.
     */
    val COMMIT: Int
        get() = if (Build.VERSION.SDK_INT >= 30) {
            HapticFeedbackConstants.CONFIRM
        } else {
            HapticFeedbackConstants.LONG_PRESS
        }

    /**
     * Taking something away — dropping a player off a squad. Deliberately NOT
     * [COMMIT]: CONFIRM reads as "that worked", the wrong note for a removal.
     */
    val REMOVE: Int
        get() = if (Build.VERSION.SDK_INT >= 30) {
            HapticFeedbackConstants.REJECT
        } else {
            HapticFeedbackConstants.LONG_PRESS
        }
}

/**
 * A tappable surface that acknowledges the press *while the finger is down* —
 * scaling back a touch and firing a haptic — instead of only after it lifts.
 *
 * MUST be first in the modifier chain: the `graphicsLayer` only scales what is
 * drawn after it, so a `clip`/`background` placed ahead of it would stay put while
 * the content shrank inside it. Ripple is off because these surfaces are opaque and
 * painted over it anyway; the scale is the affordance.
 */
@Composable
fun Modifier.pressable(
    enabled: Boolean = true,
    haptic: Int? = Feel.SELECT,
    onClick: () -> Unit,
): Modifier {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed && enabled) 0.97f else 1f,
        animationSpec = spring(dampingRatio = 0.55f, stiffness = 900f),
        label = "pressScale",
    )
    val view = LocalView.current
    return this
        .graphicsLayer { scaleX = scale; scaleY = scale }
        .clickable(
            interactionSource = interaction,
            indication = null,
            enabled = enabled,
        ) {
            haptic?.let { view.performHapticFeedback(it) }
            onClick()
        }
}
