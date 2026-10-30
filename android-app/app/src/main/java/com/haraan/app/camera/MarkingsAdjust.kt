package com.haraan.app.camera

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGestures
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
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.Feel
import com.haraan.app.vision.BowlersEnd
import com.haraan.app.vision.FrameBox
import com.haraan.app.vision.Point2
import com.haraan.app.vision.WicketAnchor
import kotlin.math.hypot

/**
 * THE MARKINGS, PUT RIGHT BY HAND.
 *
 * Everything drawn on the ground — creases, the length bands, the stumps channel — hangs off
 * two points: the batter's stumps' feet and the bowler's. When the detector puts either a
 * little wrong the whole guide sits wrong, and the only fix was Setup again from the start.
 * Here each set is a ring to drag onto its own feet, and the markings follow under the
 * finger, so the person can see the lines land on the painted creases.
 *
 * A finger hides what it is placing, so a drag moves its ring at [FINE] of the finger's
 * travel: grab the ring from anywhere near it, and steer it in from the side. Where a set
 * has never been seen, its ring starts where Setup's box would have put it.
 */
@Composable
internal fun MarkingsAdjust(
    uprightAspect: Float,
    far: WicketAnchor,
    near: BowlersEnd,
    onFar: (WicketAnchor) -> Unit,
    onNear: (BowlersEnd) -> Unit,
    onReset: () -> Unit,
    /** Both sets as they stand on screen, moved or not: Done holds what the person sees. */
    onDone: (far: WicketAnchor, near: BowlersEnd) -> Unit,
) {
    val view = LocalView.current
    var farNow by remember { mutableStateOf(far) }
    var nearNow by remember { mutableStateOf(near) }
    var held by remember { mutableStateOf<Handle?>(null) }

    Box(
        Modifier
            .fillMaxSize()
            .pointerInput(uprightAspect) {
                detectDragGestures(
                    onDragStart = { at ->
                        val box = FrameBox.letterbox(size.width.toFloat(), size.height.toFloat(), uprightAspect)
                        val reach = GRAB_RADIUS.toPx()
                        val toFar = distance(at, box.toView(farNow.base))
                        val toNear = distance(at, box.toView(nearNow.base))
                        held = when {
                            toFar > reach && toNear > reach -> null
                            toFar <= toNear -> Handle.FAR
                            else -> Handle.NEAR
                        }
                        if (held != null) view.performHapticFeedback(Feel.TICK)
                    },
                    onDragEnd = { held = null },
                    onDragCancel = { held = null },
                    onDrag = { change, drag ->
                        val which = held ?: return@detectDragGestures
                        change.consume()
                        val box = FrameBox.letterbox(size.width.toFloat(), size.height.toFloat(), uprightAspect)
                        if (box.width <= 0f || box.height <= 0f) return@detectDragGestures
                        val dx = drag.x * FINE / box.width
                        val dy = drag.y * FINE / box.height
                        when (which) {
                            Handle.FAR -> {
                                farNow = farNow.map { Point2(it.x + dx, it.y + dy) }
                                onFar(farNow)
                            }
                            Handle.NEAR -> {
                                nearNow = nearNow.copy(base = Point2(nearNow.base.x + dx, nearNow.base.y + dy))
                                onNear(nearNow)
                            }
                        }
                    },
                )
            },
    ) {
        Canvas(Modifier.fillMaxSize()) {
            val box = FrameBox.letterbox(size.width, size.height, uprightAspect)
            ring(box.toView(farNow.base), held == Handle.FAR)
            ring(box.toView(nearNow.base), held == Handle.NEAR)
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
            // In the panel, not a chip at the top: up there it ran over the screen's title.
            Text(
                "Drag each ring onto the feet of its stumps",
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                "Upper ring: the batter's stumps. Lower ring: the bowler's. The lines follow as you drag — line the creases up with the painted ones.",
                color = Color.White.copy(alpha = 0.85f),
                fontSize = 13.sp,
            )
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                Text(
                    "Reset",
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 14.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(999.dp))
                        .background(Color.White.copy(alpha = 0.10f))
                        .clickable(onClick = onReset)
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
                        .clickable { onDone(farNow, nearNow) }
                        .padding(vertical = 12.dp),
                )
            }
        }
    }
}

private enum class Handle { FAR, NEAR }

/** A ring with a cross in it: the cross is what is placed, the ring is what is held. */
private fun DrawScope.ring(at: Offset, active: Boolean) {
    val r = RING_RADIUS.toPx()
    val tick = 5.dp.toPx()
    val colour = if (active) Brand else Color.White
    drawCircle(Color.Black.copy(alpha = 0.45f), radius = r, center = at, style = Stroke(4.dp.toPx()))
    drawCircle(colour, radius = r, center = at, style = Stroke(2.dp.toPx()))
    val line = 1.5.dp.toPx()
    drawLine(colour, Offset(at.x - tick, at.y), Offset(at.x + tick, at.y), strokeWidth = line)
    drawLine(colour, Offset(at.x, at.y - tick), Offset(at.x, at.y + tick), strokeWidth = line)
}

private fun distance(a: Offset, b: Offset) = hypot(a.x - b.x, a.y - b.y)

/** A drag moves its ring this share of the finger's travel, for placing to a pixel. */
private const val FINE = 0.35f
private val GRAB_RADIUS = 64.dp
private val RING_RADIUS = 18.dp
private val Brand = Color(0xFF2563EB)
