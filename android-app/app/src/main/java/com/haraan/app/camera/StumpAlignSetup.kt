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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
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
 */
@Composable
internal fun StumpAlignSetup(
    guide: StumpAlignGuide.Guide,
    uprightAspect: Float,
    /** The batter's stumps' base as the detector sees them now, or null. */
    farSeen: Point2?,
    /** The bowler's stumps' base as the detector sees them now, or null. */
    nearSeen: Point2?,
    onDone: (farFound: Boolean, nearFound: Boolean) -> Unit,
    onCancel: () -> Unit,
) {
    val farOk = farSeen != null && guide.far.contains(farSeen, FAR_PAD_ACROSS, FAR_PAD_DOWN)
    val nearOk = nearSeen != null && guide.near.contains(nearSeen, NEAR_PAD_ACROSS, NEAR_PAD_DOWN)
    Box(Modifier.fillMaxSize()) {
        Canvas(Modifier.fillMaxSize()) {
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
            drawGuide(guide.far, FAR_PAD_ACROSS, FAR_PAD_DOWN, farOk)
            drawGuide(guide.near, NEAR_PAD_ACROSS, NEAR_PAD_DOWN, nearOk)
        }

        Text(
            "Line the stumps up in the boxes",
            color = Color.White,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier
                .align(Alignment.TopCenter)
                .statusBarsPadding()
                .padding(top = 12.dp)
                .clip(RoundedCornerShape(999.dp))
                .background(Color.Black.copy(alpha = 0.55f))
                .padding(horizontal = 14.dp, vertical = 7.dp),
        )

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
            Text(
                "Tripod about 1.3 m high, 4 m behind the bowler's stumps, looking straight down the pitch.",
                color = Color.White.copy(alpha = 0.85f),
                fontSize = 13.sp,
            )
            Spacer(Modifier.height(10.dp))
            Status("Batter's stumps — small box", farOk)
            Spacer(Modifier.height(4.dp))
            Status("Bowler's stumps — large box", nearOk)
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
                        .clickable { onDone(farOk, nearOk) }
                        .padding(vertical = 12.dp),
                )
            }
        }
    }
}

@Composable
private fun Status(label: String, ok: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(9.dp).clip(CircleShape).background(if (ok) Found else Wanted))
        Spacer(Modifier.size(8.dp))
        Text(
            if (ok) "$label · found" else "$label · line them up",
            color = Color.White.copy(alpha = if (ok) 0.95f else 0.7f),
            fontSize = 12.sp,
        )
    }
}

private val Wanted = Color(0xFFE5484D)
private val Found = Color(0xFF16A34A)
private val Brand = Color(0xFF2563EB)

/** How far off the box a phone set up near enough may put the stumps (frame widths / box heights). */
internal const val FAR_PAD_ACROSS = 0.05
internal const val FAR_PAD_DOWN = 0.9
internal const val NEAR_PAD_ACROSS = 0.08
internal const val NEAR_PAD_DOWN = 0.25
