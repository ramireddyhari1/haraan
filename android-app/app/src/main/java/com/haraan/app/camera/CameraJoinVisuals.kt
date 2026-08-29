package com.haraan.app.camera

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.layout.layout
import androidx.compose.ui.unit.dp
import kotlin.math.roundToInt

// ─────────────────────────────────────────────────────────────────────────────
//  THE CAMERA SCREEN'S SURFACES
//
//  This screen is handed to a stranger at a boundary who has just scanned a code,
//  and for many of them it is the first thing they ever see of Haraan. So it is
//  signed: the wordmark sits on it, and the thing inside the viewfinder brackets
//  is the brand H, not a glowing iris.
//
//  That matters more than it sounds. A dark screen with a luminous orb and a
//  gradient button is the house style of software that came from nowhere — the
//  brackets closing around OUR monogram is the same idea carrying our name.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The backdrop.
 *
 * Deliberately NOT a glowing orb on a dark gradient — that combination is the single
 * most recognisable signature of generated UI, and it is what this screen looked like
 * before. What is here instead is structure the rest of the app already uses: one hue,
 * lifted at the top, over a faint field of ruled lines that reads as a surface rather
 * than as a light source.
 */
@Composable
fun CameraBackdrop(modifier: Modifier = Modifier) {
    Canvas(modifier.fillMaxSize()) {
        drawRect(
            Brush.verticalGradient(
                0f to Color(0xFF0D172A),
                0.5f to Color(0xFF080E1A),
                1f to Color(0xFF05080F),
            ),
        )
        // Ruled lines, barely there. Gives the dark a texture to be dark ON.
        val gap = 34.dp.toPx()
        var y = 0f
        while (y < size.height) {
            drawLine(
                Color.White.copy(alpha = 0.022f),
                Offset(0f, y),
                Offset(size.width, y),
                1f,
            )
            y += gap
        }
        // One quiet band of brand blue along the top edge, the way a header rule works.
        //
        // Faded across HALF the screen, not a third: a gradient that reaches transparent
        // while the rect still has height leaves a hard line where the rect ends, and
        // that line was visible straight across the middle of the screen.
        drawRect(
            Brush.verticalGradient(
                0f to Color(0xFF2563EB).copy(alpha = 0.15f),
                0.45f to Color(0xFF2563EB).copy(alpha = 0.05f),
                1f to Color.Transparent,
            ),
            size = Size(size.width, size.height * 0.62f),
        )
    }
}

/**
 * The viewfinder: our monogram, with framing brackets closing on it.
 *
 * The brackets are the camera idea and the H is whose camera it is. Drawing a generic
 * aperture here — rings, a lens dot, a glow — would have been the same picture every
 * other app ships, and would have said nothing about the product the phone is joining.
 */
@Composable
fun LensMark(modifier: Modifier = Modifier, accent: Color = Color(0xFF2563EB)) {
    val focus = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        focus.animateTo(1f, tween(durationMillis = 900, easing = FastOutSlowInEasing))
    }
    val transition = rememberInfiniteTransition(label = "lens")
    val sweep by transition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(tween(5200, easing = LinearEasing)),
        label = "sweep",
    )
    val pulse by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(2600, easing = LinearEasing), RepeatMode.Restart),
        label = "pulse",
    )

    Canvas(modifier.size(132.dp)) {
        val centre = Offset(size.width / 2f, size.height / 2f)
        val r = size.minDimension / 2f
        val settle = focus.value

        // One recording pulse leaving the frame, so the mark reads as transmitting.
        // Kept because it means something; the aperture rings were removed because they
        // only meant "this is a technology screen".
        drawCircle(
            color = accent.copy(alpha = (1f - pulse) * 0.22f * settle),
            radius = r * (0.62f + pulse * 0.38f),
            center = centre,
            style = Stroke(width = 1.5.dp.toPx()),
        )

        // Framing brackets, closing in from the corners as focus lands.
        val reach = r * (1.02f - 0.10f * settle)
        val arm = r * 0.26f * settle
        val ink = Color.White.copy(alpha = 0.42f * settle)
        listOf(
            Offset(-reach, -reach) to Pair(Offset(arm, 0f), Offset(0f, arm)),
            Offset(reach, -reach) to Pair(Offset(-arm, 0f), Offset(0f, arm)),
            Offset(-reach, reach) to Pair(Offset(arm, 0f), Offset(0f, -arm)),
            Offset(reach, reach) to Pair(Offset(-arm, 0f), Offset(0f, -arm)),
        ).forEach { (corner, arms) ->
            val p = Offset(centre.x + corner.x, centre.y + corner.y)
            drawLine(ink, p, Offset(p.x + arms.first.x, p.y + arms.first.y), 2.dp.toPx(), StrokeCap.Round)
            drawLine(ink, p, Offset(p.x + arms.second.x, p.y + arms.second.y), 2.dp.toPx(), StrokeCap.Round)
        }
    }
}

/**
 * Content that arrives instead of appearing: each block fades up and rises, [index]
 * steps behind the one before it. 70ms is under the threshold where a reader waits
 * and over the one where nothing registers.
 */
@Composable
fun Staged(index: Int, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    val enter = remember(index) { Animatable(0f) }
    LaunchedEffect(index) {
        kotlinx.coroutines.delay(120L + index * 70L)
        enter.animateTo(1f, tween(durationMillis = 420, easing = FastOutSlowInEasing))
    }
    Box(
        modifier
            .alpha(enter.value)
            .layout { measurable, constraints ->
                val placeable = measurable.measure(constraints)
                val lift = ((1f - enter.value) * 16.dp.toPx()).roundToInt()
                layout(placeable.width, placeable.height) { placeable.place(0, lift) }
            },
    ) {
        content()
    }
}

/** A hairline that catches light at the top — the edge a raised surface would have. */
@Composable
fun Modifier.litEdge(radius: androidx.compose.ui.unit.Dp): Modifier = this.then(
    Modifier.background(
        brush = Brush.verticalGradient(
            listOf(Color.White.copy(alpha = 0.09f), Color.White.copy(alpha = 0.02f)),
        ),
        shape = androidx.compose.foundation.shape.RoundedCornerShape(radius),
    ),
)
