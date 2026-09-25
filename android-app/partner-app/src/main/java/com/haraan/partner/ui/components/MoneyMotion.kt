package com.haraan.partner.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.launch
import kotlin.math.abs

/**
 * The last figure each money surface actually showed, for the life of the process.
 *
 * Home leaves composition every time the partner switches tab. Without this, every
 * return to Home would count ₹0 → ₹12,400 all over again. That gets tiresome by the
 * third time, and it hides the one count-up that matters: the one that happens
 * because money actually came in.
 */
private val lastShown = HashMap<String, Double>()

/** What a money figure should draw this frame. */
class MoneyMotion internal constructor(
    /** The number to print, mid-count. Round it before formatting. */
    val shown: Double,
    /**
     * 0 → 1 → 0 once, when the figure rises above one the partner has already
     * seen. Drive the surface's lift and glow from it.
     */
    val pulse: Float,
)

/**
 * Makes a money figure count to its value instead of jumping there.
 *
 * - The first time a surface appears in a session it counts up from zero, once.
 * - When the value rises after the partner has seen it (a booking landed, a
 *   payment cleared), it counts from the old figure to the new one and [MoneyMotion.pulse]
 *   plays, so the surface can lift and glow. A fall, like a refund, just settles
 *   there: losing money is not a celebration.
 * - An unchanged value does nothing.
 *
 * The count is the visual half of a money moment. The buzz belongs to whatever
 * learned about the money (the booking poll, the payment check), because that
 * fires even when this surface is off screen.
 *
 * [memoryKey] names the surface, e.g. `"home.today"`. Two surfaces showing the
 * same number still need different keys.
 */
@Composable
fun rememberMoneyMotion(value: Double, memoryKey: String): MoneyMotion {
    var from by remember(memoryKey) { mutableDoubleStateOf(lastShown[memoryKey] ?: 0.0) }
    var to by remember(memoryKey) { mutableDoubleStateOf(lastShown[memoryKey] ?: 0.0) }
    val progress = remember(memoryKey) { Animatable(1f) }
    val pulse = remember(memoryKey) { Animatable(0f) }

    LaunchedEffect(memoryKey, value) {
        val seen = lastShown[memoryKey]
        val current = from + (to - from) * progress.value
        if (abs(value - current) < 0.5) {
            from = value; to = value
            progress.snapTo(1f)
            lastShown[memoryKey] = value
            return@LaunchedEffect
        }
        from = current
        to = value
        progress.snapTo(0f)
        val rose = seen != null && value > seen
        // Recorded before the count, not after: leaving Home mid-count must not
        // make the next visit count the same money in again.
        lastShown[memoryKey] = value
        coroutineScope {
            launch {
                // Bigger jumps get a little longer, but never long enough that the
                // partner has to wait to read the real number.
                val span = abs(value - current)
                val ms = when {
                    span < 1_000 -> 550
                    span < 50_000 -> 800
                    else -> 1_050
                }
                progress.animateTo(1f, tween(ms, easing = FastOutSlowInEasing))
            }
            if (rose) launch {
                pulse.snapTo(0f)
                pulse.animateTo(1f, tween(180, easing = LinearOutSlowInEasing))
                pulse.animateTo(0f, tween(520, easing = FastOutSlowInEasing))
            }
        }
    }

    return MoneyMotion(
        shown = from + (to - from) * progress.value,
        pulse = pulse.value,
    )
}
