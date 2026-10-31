package com.haraan.app.camera

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.drag
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.input.pointer.positionChange
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.vision.FrameBox
import com.haraan.app.vision.Point2
import com.haraan.app.vision.StumpAlignGuide

/**
 * SET UP LIKE A BALL-TRACKING CAMERA: two boxes, line the stumps up in them, done.
 *
 * The camera used to find the stumps on its own — seconds of searching, and a lock the bowler
 * knocked out on every run-up. Here the person setting up puts both sets where they belong:
 * the batter's stumps in the small box, the bowler's in the large one. Each box turns green
 * when the detector sees stumps in it, and Done locks both at once — the detector's own
 * reading where it has one, the box where it has not — so the first ball is tracked with
 * the wickets already known.
 *
 * THE BOXES MOVE. A tripod is never exactly where the guide assumes, and at night the
 * detector may not see stumps at all, so each box can be dragged onto its stumps and sized
 * by its corner handle. Done then locks them where they were put.
 */
@Composable
internal fun StumpAlignSetup(
    guide: StumpAlignGuide.Guide,
    uprightAspect: Float,
    /** The batter's stumps' base as the detector sees them now, or null. */
    farSeen: Point2?,
    /** The bowler's stumps' base as the detector sees them now, or null. */
    nearSeen: Point2?,
    /** Where the boxes were left: the batter's set, then the bowler's. */
    onDone: (farFound: Boolean, nearFound: Boolean, far: StumpAlignGuide.Box, near: StumpAlignGuide.Box) -> Unit,
    onCancel: () -> Unit,
) {
    var far by remember(guide) { mutableStateOf(guide.far) }
    var near by remember(guide) { mutableStateOf(guide.near) }
    var farMoved by remember(guide) { mutableStateOf(false) }
    var nearMoved by remember(guide) { mutableStateOf(false) }
    val farOk = farSeen != null && far.contains(farSeen, FAR_PAD_ACROSS, FAR_PAD_DOWN)
    val nearOk = nearSeen != null && near.contains(nearSeen, NEAR_PAD_ACROSS, NEAR_PAD_DOWN)
    Box(Modifier.fillMaxSize()) {
        Canvas(
            Modifier
                .fillMaxSize()
                .pointerInput(guide, uprightAspect) {
                    // Taken by where the finger first came down, not where it had got to once
                    // it counted as a drag: on the small box that was already outside it.
                    awaitEachGesture {
                        val down = awaitFirstDown(requireUnconsumed = false)
                        val frame = FrameBox.letterbox(size.width.toFloat(), size.height.toFloat(), uprightAspect)
                        if (frame.width <= 0f) return@awaitEachGesture
                        val at = down.position
                        val reach = 26.dp.toPx()
                        fun inside(b: StumpAlignGuide.Box, across: Double, d: Double) = b.contains(frame.toFrame(at), across, d)
                        // What the finger took hold of: a box to move, or a corner to size it by.
                        val grabbed = when {
                            (handleOf(far, FAR_PAD_ACROSS, FAR_PAD_DOWN, frame) - at).getDistance() <= reach -> Grab.FAR_SIZE
                            (handleOf(near, NEAR_PAD_ACROSS, NEAR_PAD_DOWN, frame) - at).getDistance() <= reach -> Grab.NEAR_SIZE
                            // The small box first: it sits over the far end of the large one.
                            inside(far, FAR_PAD_ACROSS, FAR_PAD_DOWN) -> Grab.FAR_MOVE
                            inside(near, NEAR_PAD_ACROSS, NEAR_PAD_DOWN) -> Grab.NEAR_MOVE
                            else -> null
                        } ?: return@awaitEachGesture
                        down.consume()
                        drag(down.id) { change ->
                            val moved = change.positionChange()
                            change.consume()
                            val dx = moved.x / frame.width.toDouble()
                            val dy = moved.y / frame.height.toDouble()
                            when (grabbed) {
                                Grab.FAR_MOVE -> { far = far.moved(dx, dy); farMoved = true }
                                Grab.NEAR_MOVE -> { near = near.moved(dx, dy); nearMoved = true }
                                Grab.FAR_SIZE -> { far = far.sized(dx, uprightAspect); farMoved = true }
                                Grab.NEAR_SIZE -> { near = near.sized(dx, uprightAspect); nearMoved = true }
                            }
                        }
                    }
                },
        ) {
            val frame = FrameBox.letterbox(size.width, size.height, uprightAspect)
            fun drawGuide(b: StumpAlignGuide.Box, across: Double, down: Double, ok: Boolean) {
                val r = b.padded(across, down)
                val tl = frame.toView(Point2(r[0], r[1]))
                val br = frame.toView(Point2(r[2], r[3]))
                val tone = if (ok) Found else Wanted
                drawRoundRect(
                    tone.copy(alpha = 0.16f),
                    topLeft = tl,
                    size = Size(br.x - tl.x, br.y - tl.y),
                    cornerRadius = CornerRadius(6.dp.toPx()),
                )
                drawRoundRect(
                    tone,
                    topLeft = tl,
                    size = Size(br.x - tl.x, br.y - tl.y),
                    cornerRadius = CornerRadius(6.dp.toPx()),
                    style = Stroke(2.dp.toPx()),
                )
                // The handle that sizes it, at its outer top-right corner.
                val handle = handleOf(b, across, down, frame)
                drawCircle(Color.White, 7.dp.toPx(), handle)
                drawCircle(tone, 7.dp.toPx(), handle, style = Stroke(2.dp.toPx()))
                // Where the three stumps should stand inside it, faintly.
                for (k in 0..2) {
                    val x = b.baseLeft.x + (b.baseRight.x - b.baseLeft.x) * k / 2.0
                    drawLine(
                        Color.White.copy(alpha = 0.55f),
                        frame.toView(Point2(x, b.baseY)),
                        frame.toView(Point2(x, b.topY)),
                        1.5.dp.toPx(),
                        StrokeCap.Round,
                    )
                }
            }
            drawGuide(far, FAR_PAD_ACROSS, FAR_PAD_DOWN, farOk || farMoved)
            // The stumps' own size inside the small box, which is mostly room for error.
            run {
                val tl = frame.toView(Point2(far.baseLeft.x, far.topY))
                val br = frame.toView(Point2(far.baseRight.x, far.baseY))
                drawRect(Color.White.copy(alpha = 0.8f), topLeft = tl, size = Size(br.x - tl.x, br.y - tl.y), style = Stroke(1.dp.toPx()))
            }
            drawGuide(near, NEAR_PAD_ACROSS, NEAR_PAD_DOWN, nearOk || nearMoved)
        }

        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(16.dp)
                .widthIn(max = 460.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(Color(0xE60A1120))
                .padding(16.dp),
        ) {
            // Down here with the rest, not at the top where the camera's own chips sit.
            Text(
                "Drag each box onto its stumps · corner dot to size",
                color = Color.White,
                fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                "Tripod about 1.3 m high, 4 m behind the bowler's stumps, looking straight down the pitch.",
                color = Color.White.copy(alpha = 0.85f),
                fontSize = 13.sp,
            )
            Spacer(Modifier.height(10.dp))
            Status("Batter's stumps — small box", farOk, farMoved)
            Spacer(Modifier.height(4.dp))
            Status("Bowler's stumps — large box", nearOk, nearMoved)
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                Text(
                    "Cancel",
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 14.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(999.dp))
                        .background(Color.White.copy(alpha = 0.10f))
                        .clickable(onClick = onCancel)
                        .padding(vertical = 12.dp),
                )
                Text(
                    "Done",
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .weight(2f)
                        .clip(RoundedCornerShape(999.dp))
                        .background(Brand)
                        .clickable { onDone(farOk, nearOk, far, near) }
                        .padding(vertical = 12.dp),
                )
            }
        }
    }
}

@Composable
private fun Status(label: String, ok: Boolean, placed: Boolean) {
    val good = ok || placed
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(9.dp).clip(CircleShape).background(if (good) Found else Wanted))
        Spacer(Modifier.size(8.dp))
        Text(
            when {
                ok -> "$label · found"
                placed -> "$label · placed by hand"
                else -> "$label · drag it onto them"
            },
            color = Color.White.copy(alpha = if (good) 0.95f else 0.7f),
            fontSize = 12.sp,
        )
    }
}

private enum class Grab { FAR_MOVE, NEAR_MOVE, FAR_SIZE, NEAR_SIZE }

/** Where a box's sizing dot sits on screen: the padded box's top-right corner. */
private fun handleOf(b: StumpAlignGuide.Box, across: Double, down: Double, frame: FrameBox): Offset {
    val r = b.padded(across, down)
    return frame.toView(Point2(r[2], r[1]))
}

/** The box carried by ([dx], [dy]) of the frame, kept on the picture. */
private fun StumpAlignGuide.Box.moved(dx: Double, dy: Double): StumpAlignGuide.Box {
    val ddx = dx.coerceIn(-baseLeft.x, 1.0 - baseRight.x)
    val ddy = dy.coerceIn(-topY, 1.0 - baseY)
    return StumpAlignGuide.Box(Point2(baseLeft.x + ddx, baseLeft.y + ddy), Point2(baseRight.x + ddx, baseRight.y + ddy), topY + ddy)
}

/**
 * The box grown by its corner moving [dx] across: wider and taller together, as a set of
 * stumps is at any distance, standing on the same base.
 */
private fun StumpAlignGuide.Box.sized(dx: Double, aspect: Float): StumpAlignGuide.Box {
    val newWidth = (width + 2 * dx).coerceIn(MIN_BOX_W, MAX_BOX_W)
    val k = newWidth / width
    val h = (baseY - topY) * k
    val half = newWidth / 2
    return StumpAlignGuide.Box(Point2(centreX - half, baseLeft.y), Point2(centreX + half, baseRight.y), (baseY - h).coerceAtLeast(0.0))
}

private const val MIN_BOX_W = 0.01
private const val MAX_BOX_W = 0.6

private val Wanted = Color(0xFFE5484D)
private val Found = Color(0xFF16A34A)
private val Brand = Color(0xFF2563EB)

/** How far off the box a phone set up near enough may put the stumps (frame widths / box heights). */
internal const val FAR_PAD_ACROSS = 0.05
internal const val FAR_PAD_DOWN = 0.9
internal const val NEAR_PAD_ACROSS = 0.08
internal const val NEAR_PAD_DOWN = 0.25
