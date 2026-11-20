package com.haraan.app.camera

import android.graphics.Bitmap
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay

/**
 * A MAGNIFIER FOR PLACING STUMPS.
 *
 * The batter's stumps are twenty metres off: a few pixels wide on the screen, and under the
 * finger that is placing them. While something is dragged, a round lens above the finger shows
 * the live picture round [focus] at [ZOOM]×, with a cross on the exact point being placed and,
 * via [marks], whatever is being placed drawn at the same scale — so the three lines can be
 * laid on the three stumps.
 *
 * [picture] is the camera's latest frame, UPRIGHT, in frame space: [frame] maps the screen to it.
 *
 * FAST, BECAUSE THE FINGER MOVES EVERY FRAME. The first version grabbed a fresh 14 MB picture,
 * cropped it and only then drew — and only once a finger was down — so the lens came up late
 * and trailed the drag (2026-11-20). Now the latest picture is kept ready the whole time this
 * is on screen (every [IDLE_REFRESH_MS], every [HELD_REFRESH_MS] while held), and each frame the
 * lens draws its patch straight out of it at the finger's current point: nothing to copy.
 */
@Composable
internal fun StumpLoupe(
    /** The screen-space point being placed, or null when nothing is held. */
    focus: Offset?,
    picture: () -> Bitmap?,
    /** Where the frame sits on screen (the overlay's letterbox). */
    frame: com.haraan.app.vision.FrameBox,
    /** Drawn inside the lens; map a screen point into it with the given function. */
    marks: DrawScope.(toLens: (Offset) -> Offset) -> Unit = {},
) {
    val lensPx = with(androidx.compose.ui.platform.LocalDensity.current) { LENS_DIAMETER.toPx() }
    var latest by remember { mutableStateOf<ImageBitmap?>(null) }
    val heldNow by rememberUpdatedState(focus != null)
    LaunchedEffect(Unit) {
        while (true) {
            // Only a reference to read: the analyser made it, off this thread.
            runCatching { picture() }.getOrNull()?.let { b -> if (latest?.asAndroidBitmap() !== b) latest = b.asImageBitmap() }
            delay(if (heldNow) HELD_REFRESH_MS else IDLE_REFRESH_MS)
        }
    }

    val f = focus ?: return
    Canvas(Modifier.fillMaxSize()) {
        val r = lensPx / 2f
        val edge = 8.dp.toPx()
        /*
         * BESIDE the point, on the side with more room — not above it: the stumps stand above
         * their feet, and a lens there covered the very thing being placed. Level with the
         * top half of the stumps, and never under the finger, which comes up from below.
         */
        val side = r + 44.dp.toPx()
        val cx = (if (f.x > size.width / 2f) f.x - side else f.x + side).coerceIn(r + edge, size.width - r - edge)
        val cy = (f.y - r * 0.6f).coerceIn(r + edge, size.height - r - edge)
        val centre = Offset(cx, cy)
        // A screen point into the lens: about the focus, ZOOM times bigger.
        val toLens: (Offset) -> Offset = { p -> Offset(centre.x + (p.x - f.x) * ZOOM, centre.y + (p.y - f.y) * ZOOM) }
        val circle = Path().apply { addOval(androidx.compose.ui.geometry.Rect(centre, r)) }

        drawCircle(Color.Black.copy(alpha = 0.35f), radius = r + 3.dp.toPx(), center = centre + Offset(0f, 2.dp.toPx()))
        clipPath(circle) {
            drawRect(Color(0xFF0B1220), topLeft = centre - Offset(r, r), size = Size(r * 2, r * 2))
            latest?.let { img ->
                // The patch under the lens, straight out of the kept frame: screen -> frame -> px.
                val half = r / ZOOM
                val l = maxOf(f.x - half, frame.originX)
                val t = maxOf(f.y - half, frame.originY)
                val rr = minOf(f.x + half, frame.originX + frame.width)
                val bb = minOf(f.y + half, frame.originY + frame.height)
                if (rr > l && bb > t && frame.width > 0f) {
                    val a = frame.toFrame(Offset(l, t))
                    val z = frame.toFrame(Offset(rr, bb))
                    val srcL = (a.x * img.width).toInt().coerceIn(0, img.width - 1)
                    val srcT = (a.y * img.height).toInt().coerceIn(0, img.height - 1)
                    val srcW = ((z.x - a.x) * img.width).toInt().coerceIn(1, img.width - srcL)
                    val srcH = ((z.y - a.y) * img.height).toInt().coerceIn(1, img.height - srcT)
                    val dst = toLens(Offset(l, t))
                    drawImage(
                        img,
                        srcOffset = IntOffset(srcL, srcT),
                        srcSize = IntSize(srcW, srcH),
                        dstOffset = IntOffset(dst.x.toInt(), dst.y.toInt()),
                        dstSize = IntSize(((rr - l) * ZOOM).toInt(), ((bb - t) * ZOOM).toInt()),
                        filterQuality = androidx.compose.ui.graphics.FilterQuality.Low,
                    )
                }
            }
            marks(toLens)
            // The exact point being placed.
            val arm = 10.dp.toPx()
            val line = 1.5.dp.toPx()
            drawLine(Color.Black.copy(alpha = 0.5f), centre - Offset(arm, 0f), centre + Offset(arm, 0f), line * 2.5f)
            drawLine(Color.Black.copy(alpha = 0.5f), centre - Offset(0f, arm), centre + Offset(0f, arm), line * 2.5f)
            drawLine(Color.White, centre - Offset(arm, 0f), centre + Offset(arm, 0f), line, StrokeCap.Round)
            drawLine(Color.White, centre - Offset(0f, arm), centre + Offset(0f, arm), line, StrokeCap.Round)
        }
        drawCircle(Color.White, radius = r, center = centre, style = Stroke(2.5.dp.toPx()))
    }
}

/** How much the lens magnifies. */
internal const val ZOOM = 3f
private val LENS_DIAMETER = 132.dp
private const val HELD_REFRESH_MS = 50L
private const val IDLE_REFRESH_MS = 250L
