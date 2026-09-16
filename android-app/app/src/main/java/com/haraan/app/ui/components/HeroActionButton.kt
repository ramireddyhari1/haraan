package com.haraan.app.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.haraan.app.ui.Feel
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch

/**
 * The round back / share / save controls that float over a detail page's hero image.
 *
 * Shared by Event and Venue detail so the two pages can't drift apart again (they had
 * different sizes, gaps, scrims and icon sets). The disc is a top-lit smoked glass with
 * a hairline rim: the fill carries the icon over a bright poster, the rim defines the
 * edge over a dark one. The visible disc is [HeroActionDefaults.DiscSize] but the touch
 * target is a full 48dp, so the chrome stays small without being fiddly to hit.
 *
 * @param collapse 0 floating over the image, 1 sitting on a solid app bar — the glass
 *   fades out and the glyph darkens to text colour.
 */
@Composable
fun HeroActionButton(
    icon: ImageVector,
    contentDescription: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    collapse: Float = 0f,
    iconSize: Dp = HeroActionDefaults.IconSize,
    haptic: Int? = Feel.SELECT,
) {
    val tint = lerp(Color.White, HaraanColors.TextPrimary, collapse.coerceIn(0f, 1f))
    val performHaptic = rememberHapticPerformer()
    HeroActionDisc(
        contentDescription = contentDescription,
        stateDescription = null,
        collapse = collapse,
        active = 0f,
        activeColor = Color.Transparent,
        burst = 0f,
        onClick = {
            haptic?.let(performHaptic)
            onClick()
        },
        modifier = modifier,
    ) {
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(iconSize))
    }
}

/**
 * A two-state hero control (favourite, bookmark). Beyond swapping outline for fill, the
 * "on" state turns the glass disc solid white with the glyph in [activeColor], so the
 * state reads at a glance even on a busy poster. Turning on pops the glyph with a soft
 * overshoot and sends a single ring outwards; turning off settles back quietly.
 * Neither plays on first composition, only on a real change.
 */
@Composable
fun HeroToggleActionButton(
    checked: Boolean,
    onToggle: () -> Unit,
    iconOff: ImageVector,
    iconOn: ImageVector,
    activeColor: Color,
    contentDescriptionOff: String,
    contentDescriptionOn: String,
    modifier: Modifier = Modifier,
    collapse: Float = 0f,
    stateDescriptionOn: String = "Saved",
    stateDescriptionOff: String = "Not saved",
) {
    val active by animateFloatAsState(
        targetValue = if (checked) 1f else 0f,
        animationSpec = tween(durationMillis = 220, easing = FastOutSlowInEasing),
        label = "heroToggleActive",
    )
    val pop = remember { Animatable(1f) }
    val burst = remember { Animatable(0f) }
    val lastChecked = remember { mutableStateOf(checked) }

    LaunchedEffect(checked) {
        if (lastChecked.value == checked) return@LaunchedEffect
        lastChecked.value = checked
        if (checked) {
            launch {
                pop.snapTo(0.55f)
                pop.animateTo(1f, spring(dampingRatio = 0.38f, stiffness = 520f))
            }
            launch {
                burst.snapTo(0f)
                burst.animateTo(1f, tween(durationMillis = 480, easing = FastOutSlowInEasing))
                burst.snapTo(0f)
            }
        } else {
            burst.snapTo(0f)
            pop.snapTo(0.82f)
            pop.animateTo(1f, spring(dampingRatio = 0.6f, stiffness = 700f))
        }
    }

    val baseTint = lerp(Color.White, HaraanColors.TextPrimary, collapse.coerceIn(0f, 1f))
    val haptic = rememberHapticPerformer()

    HeroActionDisc(
        contentDescription = if (checked) contentDescriptionOn else contentDescriptionOff,
        stateDescription = if (checked) stateDescriptionOn else stateDescriptionOff,
        collapse = collapse,
        active = active,
        activeColor = activeColor,
        burst = burst.value,
        onClick = {
            // Saving is a small commitment and gets the heavier "landed" tick; un-saving
            // is a plain tap, not REMOVE, whose reject buzz reads as an error.
            haptic(if (checked) Feel.SELECT else Feel.COMMIT)
            onToggle()
        },
        modifier = modifier,
    ) {
        val iconModifier = Modifier
            .size(HeroActionDefaults.IconSize)
            .graphicsLayer {
                scaleX = pop.value
                scaleY = pop.value
            }
        Icon(
            iconOff,
            contentDescription = null,
            tint = baseTint.copy(alpha = 1f - active),
            modifier = iconModifier,
        )
        Icon(
            iconOn,
            contentDescription = null,
            tint = activeColor.copy(alpha = active),
            modifier = iconModifier,
        )
    }
}

object HeroActionDefaults {
    /** Visible glass disc. */
    val DiscSize = 38.dp

    /** Full accessible hit area around the disc. */
    val TouchSize = 48.dp

    val IconSize = 20.dp

    /** The back arrow is the only glyph with no enclosed shape, so it reads light at 20. */
    val BackIconSize = 21.dp

    /**
     * Gap between neighbouring touch targets. With 48dp targets around 38dp discs this
     * lands the visible discs 12dp apart — the same rhythm on every detail page.
     */
    val Spacing = 2.dp

    /** Row inset that puts the disc edge ~15dp from the screen edge. */
    val EdgePadding = 10.dp
}

@Composable
private fun rememberHapticPerformer(): (Int) -> Unit {
    val view = LocalView.current
    return remember(view) { { constant: Int -> view.performHapticFeedback(constant); Unit } }
}

@Composable
private fun HeroActionDisc(
    contentDescription: String,
    stateDescription: String?,
    collapse: Float,
    active: Float,
    activeColor: Color,
    burst: Float,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val pressScale by animateFloatAsState(
        targetValue = if (pressed) 0.9f else 1f,
        animationSpec = spring(dampingRatio = 0.55f, stiffness = 900f),
        label = "heroPressScale",
    )
    val pressShade by animateFloatAsState(
        targetValue = if (pressed) 1f else 0f,
        animationSpec = tween(durationMillis = 120),
        label = "heroPressShade",
    )
    val glass = 1f - collapse.coerceIn(0f, 1f)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(HeroActionDefaults.TouchSize)
            .semantics(mergeDescendants = true) {
                this.contentDescription = contentDescription
                stateDescription?.let { this.stateDescription = it }
            }
            .clickable(
                interactionSource = interaction,
                indication = null,
                role = Role.Button,
                onClick = onClick,
            ),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(HeroActionDefaults.DiscSize)
                .graphicsLayer {
                    scaleX = pressScale
                    scaleY = pressScale
                }
                .drawBehind {
                    val radius = size.minDimension / 2f
                    val hairline = 1.dp.toPx()

                    // Ring that ripples out when a toggle turns on. Drawn first and
                    // outside the disc, so it never tints the glass itself.
                    if (burst > 0f && glass > 0f) {
                        val ringAlpha = (1f - burst) * 0.75f * glass
                        drawCircle(
                            color = activeColor.copy(alpha = ringAlpha),
                            radius = radius * (1f + 0.42f * burst),
                            style = Stroke(width = 2.dp.toPx() * (1f - burst) + hairline * 0.5f),
                        )
                    }

                    if (glass <= 0f) return@drawBehind

                    // Smoked glass, lighter at the top like it's catching light.
                    val shade = 0.14f * pressShade
                    val glassTop = Ink.copy(alpha = ((0.36f + shade) * glass).coerceAtMost(1f))
                    val glassBottom = Ink.copy(alpha = ((0.54f + shade) * glass).coerceAtMost(1f))
                    val solid = Color.White.copy(alpha = (0.97f - 0.08f * pressShade) * glass)
                    drawCircle(
                        brush = Brush.verticalGradient(
                            0f to lerp(glassTop, solid, active),
                            1f to lerp(glassBottom, solid, active),
                        ),
                        radius = radius,
                    )

                    // Hairline rim: bright at the top on glass, a faint dark edge once
                    // the disc is solid white (so it still separates from a pale poster).
                    val rimTop = lerp(Color.White.copy(alpha = 0.26f), Ink.copy(alpha = 0.08f), active)
                    val rimBottom = lerp(Color.White.copy(alpha = 0.07f), Ink.copy(alpha = 0.08f), active)
                    drawCircle(
                        brush = Brush.verticalGradient(
                            0f to rimTop.copy(alpha = rimTop.alpha * glass),
                            1f to rimBottom.copy(alpha = rimBottom.alpha * glass),
                        ),
                        radius = radius - hairline / 2f,
                        center = Offset(size.width / 2f, size.height / 2f),
                        style = Stroke(width = hairline),
                    )
                },
            content = content,
        )
    }
}

private val Ink = Color(0xFF0B0E14)

/**
 * Hand-drawn 24-unit glyphs for the hero controls: round caps and joins, one stroke
 * weight, so back, share and save look like one family rather than three stock
 * Material icons at different optical weights.
 */
object HeroActionIcons {
    private const val WEIGHT = 1.9f

    /** Mirrors in RTL layouts. */
    val Back: ImageVector = icon("HeroBack", autoMirror = true) {
        stroke("M19.2 12 H5.4")
        stroke("M11.4 5.6 L5 12 L11.4 18.4")
    }

    /** Arrow lifting out of a tray — the share mark people recognise across apps. */
    val Share: ImageVector = icon("HeroShare") {
        stroke("M12 3.6 V14.6")
        stroke("M7.9 7.6 L12 3.6 L16.1 7.6")
        stroke("M5 12.4 V17.6 A2.6 2.6 0 0 0 7.6 20.2 H16.4 A2.6 2.6 0 0 0 19 17.6 V12.4")
    }

    private const val HEART =
        "M12 20.2 C7.6 17.6 3.4 13.9 3.4 9.3 C3.4 6.6 5.4 4.6 7.9 4.6 " +
            "C9.7 4.6 11.2 5.6 12 7.2 C12.8 5.6 14.3 4.6 16.1 4.6 " +
            "C18.6 4.6 20.6 6.6 20.6 9.3 C20.6 13.9 16.4 17.6 12 20.2 Z"

    val HeartOutline: ImageVector = icon("HeroHeartOutline") { stroke(HEART) }

    // Filled AND stroked at the same weight, so the glyph doesn't shrink when it fills.
    val HeartFilled: ImageVector = icon("HeroHeartFilled") {
        fill(HEART)
        stroke(HEART)
    }

    private const val BOOKMARK =
        "M6.5 4.9 A1.9 1.9 0 0 1 8.4 3 H15.6 A1.9 1.9 0 0 1 17.5 4.9 V20.2 L12 16.3 L6.5 20.2 Z"

    val BookmarkOutline: ImageVector = icon("HeroBookmarkOutline") { stroke(BOOKMARK) }

    val BookmarkFilled: ImageVector = icon("HeroBookmarkFilled") {
        fill(BOOKMARK)
        stroke(BOOKMARK)
    }

    private fun icon(
        name: String,
        autoMirror: Boolean = false,
        body: ImageVector.Builder.() -> Unit,
    ): ImageVector =
        ImageVector.Builder(
            name = name,
            defaultWidth = 24.dp,
            defaultHeight = 24.dp,
            viewportWidth = 24f,
            viewportHeight = 24f,
            autoMirror = autoMirror,
        ).apply(body).build()

    // Laid down in black; `Icon` re-tints the whole vector.
    private fun ImageVector.Builder.stroke(pathData: String) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = null,
            stroke = SolidColor(Color.Black),
            strokeLineWidth = WEIGHT,
            strokeLineCap = StrokeCap.Round,
            strokeLineJoin = StrokeJoin.Round,
        )
    }

    private fun ImageVector.Builder.fill(pathData: String) {
        addPath(pathData = addPathNodes(pathData), fill = SolidColor(Color.Black))
    }
}
