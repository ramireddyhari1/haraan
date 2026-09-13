package com.haraan.partner

import android.os.Build
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/** One destination in the bar: a stable [key], its word, and its two glyph states. */
internal data class HaraanNavItem(
    val key: String,
    val label: String,
    val outline: ImageVector,
    val active: ImageVector,
)

/**
 * The partner app's bottom navigation.
 *
 * This replaces Material 3's `NavigationBar`, which brought two things that made
 * the bar feel like stock Android: the lozenge that pops in behind the selected
 * icon, and a ripple that washes across the whole item on touch. Both are chrome
 * drawn *around* the icon; neither is in this app's vocabulary anywhere else.
 *
 * What's here instead is motion on the icon itself:
 *   - selecting springs the glyph up and past its size before settling (the
 *     under-damped spec is deliberate — that overshoot is the "pop"),
 *   - the outline dissolves into the filled variant as it travels,
 *   - a soft radial bloom fades in behind it, light rather than a box,
 *   - and pressing *anything* squashes it under the finger straight away, so the
 *     bar answers touch before the tab has even changed.
 *
 * The haptic fires on the state change, not on every touch: a confirm-class tick
 * where the platform has one (API 30+), the keyboard tap everywhere else.
 */
@Composable
internal fun HaraanBottomBar(
    items: List<HaraanNavItem>,
    selectedKey: String,
    onSelect: (String) -> Unit,
    accent: Color,
    idle: Color,
    container: Color,
    hairline: Color,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current

    Column(modifier.fillMaxWidth().background(container)) {
        HorizontalDivider(color = hairline)
        Row(
            Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .height(62.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            items.forEach { item ->
                val selected = item.key == selectedKey
                val interaction = remember { MutableInteractionSource() }
                val pressed by interaction.collectIsPressedAsState()

                // Under-damped on purpose: `on` sails past 1 and comes back, which
                // is what gives the icon its lift-and-settle instead of a fade.
                val on by animateFloatAsState(
                    targetValue = if (selected) 1f else 0f,
                    animationSpec = spring(dampingRatio = 0.46f, stiffness = 420f),
                    label = "nav-selected",
                )
                // Stiff and nearly critically damped: the squash must land under the
                // finger, not bounce after it.
                val squash by animateFloatAsState(
                    targetValue = if (pressed) 0.88f else 1f,
                    animationSpec = spring(dampingRatio = 0.62f, stiffness = Spring.StiffnessHigh),
                    label = "nav-press",
                )

                Column(
                    Modifier
                        .weight(1f)
                        .selectable(
                            selected = selected,
                            interactionSource = interaction,
                            // No ripple: the squash is the feedback, and a spreading
                            // wash would put a Material rectangle back on the bar.
                            indication = null,
                            role = Role.Tab,
                            onClick = {
                                if (!selected) {
                                    view.performHapticFeedback(
                                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                                            HapticFeedbackConstants.CONFIRM
                                        } else {
                                            HapticFeedbackConstants.KEYBOARD_TAP
                                        }
                                    )
                                    onSelect(item.key)
                                }
                            },
                        )
                        .height(62.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(
                        Modifier
                            .size(30.dp)
                            .graphicsLayer {
                                val s = squash * (1f + 0.10f * on)
                                scaleX = s
                                scaleY = s
                                translationY = -3.dp.toPx() * on
                            },
                        contentAlignment = Alignment.Center,
                    ) {
                        // Light behind the glyph. Clamped because `on` overshoots.
                        Box(
                            Modifier
                                .size(40.dp)
                                .alpha(on.coerceIn(0f, 1f))
                                .background(
                                    Brush.radialGradient(
                                        listOf(accent.copy(alpha = 0.18f), Color.Transparent)
                                    ),
                                    CircleShape,
                                )
                        )
                        Icon(
                            item.outline,
                            contentDescription = null,
                            tint = idle,
                            modifier = Modifier
                                .size(24.dp)
                                .alpha((1f - on).coerceIn(0f, 1f)),
                        )
                        Icon(
                            item.active,
                            contentDescription = null,
                            tint = accent,
                            modifier = Modifier
                                .size(24.dp)
                                .alpha(on.coerceIn(0f, 1f)),
                        )
                    }
                    Text(
                        item.label,
                        fontSize = 10.5.sp,
                        // Tracking opens up slightly on the selected word so the two
                        // weights sit on a similar visual width and nothing shifts.
                        letterSpacing = 0.1.sp,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                        color = lerp(idle, accent, on.coerceIn(0f, 1f)),
                        maxLines = 1,
                        modifier = Modifier.graphicsLayer { translationY = -1.dp.toPx() * on },
                    )
                }
            }
        }
    }
}
