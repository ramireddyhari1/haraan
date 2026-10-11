package com.haraan.app.camera

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.vision.ArPath
import kotlinx.coroutines.launch

/**
 * THE RESULT ON THE LIVE PICTURE, the way a ball-tracking camera shows it.
 *
 * Within a moment of the ball the path, the channel between the wickets and the stumps lit
 * with the answer appear over the camera's own picture, with the speed, and they stay there
 * — the camera keeps running, the bowler walks back through it — until the next ball takes
 * their place. Nothing covers the screen. The full replay (the delivery at speed, then the
 * 3D stadium) is one tap away on [onReplay], not forced on every ball: taking over the
 * screen after each delivery made the camera feel slow next to an app that never leaves it.
 *
 * Drawn in quickly (the whole path in about half a second, the ball at its head) rather than
 * at the pace of the ball: by the time it appears the ball has already arrived.
 */
@Composable
internal fun LiveResultOverlay(
    path: ArPath,
    uprightAspect: Float,
    speedKmh: Double?,
    onReplay: (() -> Unit)?,
) {
    val context = LocalContext.current
    val reveal = remember(path) { Animatable(0f) }
    val glow = remember(path) { Animatable(0f) }
    val appear = remember(path) { Animatable(0f) }
    LaunchedEffect(path) {
        launch { appear.animateTo(1f, tween(160, easing = FastOutSlowInEasing)) }
        reveal.animateTo(1f, tween(REVEAL_MS, easing = LinearEasing))
        launch {
            when (path.hitting) {
                true -> cricketThud(context, Thud.WICKET)
                false -> cricketThud(context, Thud.TICK)
                null -> Unit
            }
        }
        glow.animateTo(1f, tween(220))
    }

    Box(Modifier.fillMaxSize().graphicsLayer { alpha = appear.value }) {
        Canvas(Modifier.fillMaxSize()) {
            val frame = frameRect(size.width, size.height, uprightAspect)
            val upto = path.startMs + (path.endMs - path.startMs) * reveal.value
            drawArPath(path, frame, upto, glow.value)
        }
        if (speedKmh != null) {
            Column(
                Modifier
                    .align(Alignment.TopStart)
                    .statusBarsPadding()
                    .padding(start = 14.dp, top = 64.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(Color(0xD9101418))
                    .padding(horizontal = 12.dp, vertical = 8.dp),
            ) {
                Text("Speed", color = Color.White.copy(alpha = 0.72f), fontSize = 10.sp)
                Text("%.0f km/h".format(speedKmh), color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            }
        }
        if (onReplay != null) {
            Row(
                Modifier
                    .align(Alignment.TopEnd)
                    .statusBarsPadding()
                    .padding(end = 14.dp, top = 64.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color(0xFF2563EB))
                    .clickable(onClick = onReplay)
                    .padding(horizontal = 14.dp, vertical = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Canvas(Modifier.size(10.dp)) {
                    val p = Path().apply {
                        moveTo(0f, 0f)
                        lineTo(size.width, size.height / 2f)
                        lineTo(0f, size.height)
                        close()
                    }
                    drawPath(p, Color.White)
                }
                Spacer(Modifier.size(7.dp))
                Text("Replay", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/** The whole path is drawn in this long, the moment the ball is called. */
private const val REVEAL_MS = 520
