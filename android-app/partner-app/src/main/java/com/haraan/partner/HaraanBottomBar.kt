package com.haraan.partner

import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics

/** One destination in the bar: a stable [key], its word, and its two glyph states. */
internal data class HaraanNavItem(
    val key: String,
    val label: String,
    val outline: ImageVector,
    val active: ImageVector,
)

private val BarTop = Color(0xFF111B31)
private val BarBottom = Color(0xFF0A1226)
private val IdleGlyph = Color(0xB3FFFFFF)

/**
 * The partner app's bottom navigation: a floating navy capsule.
 *
 * The selected tab is a white pill carrying its glyph and its name; every other
 * tab is a glyph alone. Selection is drawn as width: each slot's weight springs
 * between "icon" and "icon + word", so as one tab opens the last one closes, and
 * the white pill reads as one piece of material gliding across the bar rather
 * than two things fading. The word fades in behind the glyph once there's room
 * for it, so it never squeezes or wraps mid-flight.
 *
 * Touch answers before the tab changes: the slot squashes under the finger, and
 * a light tick plays on the switch itself (never on a re-tap of the current tab).
 *
 * [container] is the band the capsule floats over; it should match the page
 * background so the capsule looks lifted off the screen, not sat on a strip.
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
    /**
     * The bar floats over live media (the Scan camera): no backdrop band, and a
     * translucent capsule with a brighter rim so it holds its edge over a picture.
     */
    overMedia: Boolean = false,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val glass by animateFloatAsState(if (overMedia) 1f else 0f, tween(260), label = "nav-glass")

    Box(
        modifier
            .fillMaxWidth()
            .background(if (overMedia) Color.Transparent else container)
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 10.dp),
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .height(64.dp)
                .shadow(22.dp, RoundedCornerShape(32.dp), clip = false, ambientColor = BarBottom, spotColor = BarBottom)
                .clip(RoundedCornerShape(32.dp))
                .background(
                    Brush.verticalGradient(
                        listOf(BarTop.copy(alpha = 1f - 0.2f * glass), BarBottom.copy(alpha = 1f - 0.16f * glass))
                    )
                )
                // A hairline of light along the capsule's edge, so it reads as a
                // solid object with a lit top rather than a flat cut-out. Over the
                // camera it brightens, so the capsule keeps its edge on a busy picture.
                .border(
                    1.dp,
                    Brush.verticalGradient(
                        listOf(Color.White.copy(alpha = 0.2f + 0.14f * glass), Color.White.copy(alpha = 0.04f + 0.08f * glass))
                    ),
                    RoundedCornerShape(32.dp),
                )
                .padding(6.dp),
            horizontalArrangement = Arrangement.spacedBy(2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            items.forEach { item ->
                val selected = item.key == selectedKey
                val interaction = remember { MutableInteractionSource() }
                val pressed by interaction.collectIsPressedAsState()

                // Slightly under-damped: the pill overshoots its width a touch and
                // settles, which is the "glide and land" of the whole bar.
                val on by animateFloatAsState(
                    targetValue = if (selected) 1f else 0f,
                    animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
                    label = "nav-selected",
                )
                // Stiff and nearly critically damped: the squash lands under the
                // finger instead of bouncing after it.
                val squash by animateFloatAsState(
                    targetValue = if (pressed) 0.9f else 1f,
                    animationSpec = spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessHigh),
                    label = "nav-press",
                )
                val open = on.coerceIn(0f, 1f)

                Box(
                    Modifier
                        // Icon-only slots share the room; the open one takes about
                        // two and a half of them for its word.
                        .weight(1f + 1.6f * on.coerceAtLeast(0f))
                        .fillMaxHeight()
                        .selectable(
                            selected = selected,
                            interactionSource = interaction,
                            // No ripple: the squash is the feedback.
                            indication = null,
                            role = Role.Tab,
                            onClick = {
                                if (!selected) {
                                    Haptics.tick(view)
                                    onSelect(item.key)
                                }
                            },
                        )
                        .graphicsLayer { scaleX = squash; scaleY = squash },
                    contentAlignment = Alignment.Center,
                ) {
                    // The white pill. Scales and fades with the slot so it seems to
                    // travel with the width rather than pop.
                    Box(
                        Modifier
                            .fillMaxSize()
                            .graphicsLayer {
                                alpha = open
                                val s = 0.82f + 0.18f * open
                                scaleX = s; scaleY = s
                            }
                            .shadow(8.dp * open, RoundedCornerShape(26.dp), clip = false, spotColor = Color(0x66000000))
                            .clip(RoundedCornerShape(26.dp))
                            .background(Color.White),
                    )
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.Center,
                        modifier = Modifier.padding(horizontal = 10.dp),
                    ) {
                        Box(Modifier.size(24.dp), contentAlignment = Alignment.Center) {
                            Icon(
                                item.outline,
                                contentDescription = if (selected) null else item.label,
                                tint = IdleGlyph,
                                modifier = Modifier.size(23.dp).graphicsLayer { alpha = 1f - open },
                            )
                            Icon(
                                item.active,
                                contentDescription = null,
                                tint = accent,
                                modifier = Modifier.size(23.dp).graphicsLayer {
                                    alpha = open
                                    val s = 0.9f + 0.1f * on
                                    scaleX = s; scaleY = s
                                },
                            )
                        }
                        // The word only takes space while the slot is opening, and
                        // only shows once it's mostly open, so it never clips.
                        if (on > 0.02f) {
                            Spacer(Modifier.width(7.dp * open))
                            Text(
                                item.label,
                                fontSize = 13.5.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = (-0.1).sp,
                                color = lerp(IdleGlyph, Color(0xFF0B1220), open),
                                maxLines = 1,
                                softWrap = false,
                                overflow = TextOverflow.Clip,
                                modifier = Modifier.graphicsLayer {
                                    alpha = ((open - 0.45f) / 0.55f).coerceIn(0f, 1f)
                                    translationX = (1f - open) * -6.dp.toPx()
                                },
                            )
                        }
                    }
                }
            }
        }
    }
}
