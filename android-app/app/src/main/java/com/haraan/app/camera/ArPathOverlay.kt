package com.haraan.app.camera

import android.graphics.SurfaceTexture
import android.media.MediaMetadataRetriever
import android.media.MediaPlayer
import android.media.PlaybackParams
import android.os.Build
import android.view.Surface
import android.view.TextureView
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.withFrameMillis
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.haraan.app.R
import com.haraan.app.ui.Feel
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.vision.ArLeg
import com.haraan.app.vision.ArPath
import com.haraan.app.vision.ArPoint
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.io.File

/*
 * THE BALL'S PATH, ON THE REAL PICTURE.
 *
 * Two places, one drawing. On the live viewfinder the instant a ball is called — the phone
 * is on a tripod, so the picture still lines up with the delivery it just filmed — and over
 * the slow-motion replay of the clip, where the line grows in step with the ball.
 *
 * Red from the hand to the bounce, blue after it, dashed where it is a projection on to the
 * stumps, and the stumps lit with the answer. A tube rather than a hairline — a soft glow,
 * a solid core, a bright centre — because a single-pixel line over grass in daylight is
 * invisible from where an operator stands.
 */

private val PathIn = Color(0xFFE5484D)
private val PathOut = Color(0xFF4D8BFF)
private val PathProjected = Color(0xFFB9D0FF)
private val Hitting = Color(0xFFE11D2E)
private val Missing = Color(0xFF16A34A)
private val Unjudged = Color(0xFFF5B83D)
private val Brand = Color(0xFF2563EB)
private val Glass = Color(0xD90A1120)

private fun colourOf(leg: ArLeg) = when (leg) {
    ArLeg.IN -> PathIn
    ArLeg.OUT -> PathOut
    ArLeg.PROJECTED -> PathProjected
}

/**
 * The path as far as [uptoMs], drawn into [frame] (the picture's rectangle on screen).
 * [stumpsGlow] 0..1 lights the stumps with the verdict.
 */
internal fun DrawScope.drawArPath(path: ArPath, frame: Rect, uptoMs: Double, stumpsGlow: Float) {
    if (path.isEmpty) return
    fun at(x: Double, y: Double) = Offset(frame.left + x.toFloat() * frame.width, frame.top + y.toFloat() * frame.height)
    val core = 4.5.dp.toPx()

    clipRect(frame.left, frame.top, frame.right, frame.bottom) {
        // The stumps, lit with the answer once the ball is there.
        if (stumpsGlow > 0f && path.stumps.isNotEmpty()) {
            val tone = when (path.hitting) {
                true -> Hitting
                false -> Missing
                null -> Unjudged
            }
            val middle = path.stumps[path.stumps.size / 2]
            val c = at((middle.first.x + middle.second.x) / 2, (middle.first.y + middle.second.y) / 2)
            val span = (at(middle.first.x, middle.first.y) - at(middle.second.x, middle.second.y)).getDistance()
            val r = (span * 1.3f).coerceAtLeast(24.dp.toPx())
            drawCircle(
                Brush.radialGradient(listOf(tone.copy(alpha = 0.45f * stumpsGlow), Color.Transparent), c, r),
                r,
                c,
                blendMode = BlendMode.Plus,
            )
            for ((foot, top) in path.stumps) {
                drawLine(tone.copy(alpha = stumpsGlow), at(foot.x, foot.y), at(top.x, top.y), 3.2.dp.toPx(), StrokeCap.Round)
            }
        }

        // The line itself, three passes: glow, core, bright centre.
        val shown = ArrayList<ArPoint>()
        for (i in path.points.indices) {
            val p = path.points[i]
            if (p.ms <= uptoMs) {
                shown += p
            } else {
                // The last, partial step, so the head moves smoothly between samples.
                val prev = path.points.getOrNull(i - 1)
                if (prev != null && prev.ms < uptoMs) {
                    val f = (uptoMs - prev.ms) / (p.ms - prev.ms)
                    shown += ArPoint(uptoMs, prev.x + (p.x - prev.x) * f, prev.y + (p.y - prev.y) * f, p.leg)
                }
                break
            }
        }
        val dash = PathEffect.dashPathEffect(floatArrayOf(10.dp.toPx(), 7.dp.toPx()))
        for (pass in 0..2) {
            for (i in 1 until shown.size) {
                val a = shown[i - 1]
                val b = shown[i]
                val colour = colourOf(b.leg)
                val projected = b.leg == ArLeg.PROJECTED
                val start = at(a.x, a.y)
                val end = at(b.x, b.y)
                when (pass) {
                    0 -> if (!projected) drawLine(colour.copy(alpha = 0.28f), start, end, core * 3f, StrokeCap.Round, blendMode = BlendMode.Plus)
                    1 -> drawLine(colour, start, end, core, StrokeCap.Round, pathEffect = if (projected) dash else null)
                    2 -> if (!projected) drawLine(Color.White.copy(alpha = 0.55f), start, end, core * 0.3f, StrokeCap.Round)
                }
            }
        }

        // Where it pitched: a ring lying on the ground.
        val bounce = path.bounce
        val bounceMs = path.bounceMs
        if (bounce != null && bounceMs != null && uptoMs >= bounceMs) {
            val c = at(bounce.x, bounce.y)
            val r = 11.dp.toPx()
            scale(1f, 0.42f, pivot = c) {
                drawCircle(Brush.radialGradient(listOf(Color.White.copy(alpha = 0.5f), Color.Transparent), c, r * 2.2f), r * 2.2f, c)
                drawCircle(Color.White, r, c, style = Stroke(2.5.dp.toPx()))
            }
        }

        // The ball, while it is still travelling.
        val head = shown.lastOrNull()
        if (head != null && uptoMs < path.endMs) {
            val c = at(head.x, head.y)
            drawCircle(colourOf(head.leg).copy(alpha = 0.35f), 13.dp.toPx(), c, blendMode = BlendMode.Plus)
            drawCircle(Color.White, 5.5.dp.toPx(), c)
        }
    }
}

/**
 * STEP ONE AFTER A BALL: the path drawn over the live viewfinder.
 *
 * It draws itself in at a little under real pace, lights the stumps, holds long enough to
 * read, and gets out of the way — [onFinished] is where the 3D replay takes over. A tap
 * skips it. The knocks match the 3D replay's: the bounce, then the verdict.
 */
@Composable
internal fun LivePathOverlay(path: ArPath, uprightAspect: Float, onFinished: () -> Unit) {
    val context = LocalContext.current
    val reveal = remember { Animatable(0f) }
    val glow = remember { Animatable(0f) }
    val fade = remember { Animatable(1f) }
    val drawMs = ((path.endMs - path.startMs) * 2.2).coerceIn(900.0, 1_700.0).toInt()

    LaunchedEffect(path) {
        val bounceMs = path.bounceMs
        if (bounceMs != null) {
            launch {
                delay(((bounceMs - path.startMs) / (path.endMs - path.startMs) * drawMs).toLong().coerceAtLeast(0L))
                cricketThud(context, Thud.RUN)
            }
        }
        reveal.animateTo(1f, tween(drawMs, easing = LinearEasing))
        launch {
            when (path.hitting) {
                true -> cricketThud(context, Thud.WICKET)
                false -> cricketThud(context, Thud.TICK)
                null -> Unit
            }
        }
        glow.animateTo(1f, tween(260))
        delay(2_200)
        fade.animateTo(0f, tween(320))
        onFinished()
    }

    Box(
        Modifier
            .fillMaxSize()
            .graphicsLayer { alpha = fade.value }
            .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) { onFinished() },
    ) {
        Canvas(Modifier.fillMaxSize()) {
            val frame = frameRect(size.width, size.height, uprightAspect)
            val upto = path.startMs + (path.endMs - path.startMs) * reveal.value
            drawArPath(path, frame, upto, glow.value)
        }
        Text(
            "Tap to skip",
            color = Color.White.copy(alpha = 0.75f),
            fontSize = 11.sp,
            modifier = Modifier
                .align(Alignment.TopCenter)
                .statusBarsPadding()
                .padding(top = 14.dp)
                .clip(RoundedCornerShape(999.dp))
                .background(Color.Black.copy(alpha = 0.45f))
                .padding(horizontal = 10.dp, vertical = 4.dp),
        )
    }
}

/** The delivery's clip, kept on the phone for the replay, and when it started on the camera's clock. */
internal data class ReplayClip(val file: File, val startSensorMs: Double?, val path: ArPath)

/**
 * STEP TWO: the clip itself, in slow motion, with the path growing in step with the ball.
 *
 * Plays only the delivery — a beat before the release to a beat after the stumps — and
 * stops there, rather than ten seconds of a bowler walking back. The line is synced from
 * the moment the recording started on the camera's clock; when that is not known the whole
 * path is shown from the first frame instead of guessing a sync.
 */
@Composable
internal fun VideoReplayOverlay(
    clip: ReplayClip,
    onClose: () -> Unit,
    onOpen3d: (() -> Unit)?,
) {
    val hostView = LocalView.current
    val context = LocalContext.current
    var speed by remember { mutableFloatStateOf(0.25f) }
    var videoAspect by remember { mutableFloatStateOf(16f / 9f) }
    var positionMs by remember { mutableDoubleStateOf(0.0) }
    var playing by remember { mutableStateOf(false) }
    var generation by remember { mutableIntStateOf(0) }
    val player = remember { MediaPlayer() }
    var prepared by remember { mutableStateOf(false) }

    val start = clip.startSensorMs
    val windowStart = if (start != null) (clip.path.startMs - start - 700).coerceAtLeast(0.0) else 0.0
    val windowEnd = if (start != null) clip.path.endMs - start + 900 else Double.MAX_VALUE

    LaunchedEffect(clip.file) {
        // Rotation-corrected shape, so the picture and the line agree on where things are.
        runCatching {
            val retriever = MediaMetadataRetriever()
            retriever.setDataSource(clip.file.absolutePath)
            val w = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toFloatOrNull()
            val h = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toFloatOrNull()
            val rot = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: 0
            retriever.release()
            if (w != null && h != null && w > 0 && h > 0) {
                videoAspect = if (rot == 90 || rot == 270) h / w else w / h
            }
        }
    }

    DisposableEffect(Unit) {
        onDispose { runCatching { player.release() } }
    }

    fun seekToWindow() {
        val to = windowStart.toLong()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            player.seekTo(to, MediaPlayer.SEEK_CLOSEST)
        } else {
            player.seekTo(to.toInt())
        }
    }

    fun applySpeed() {
        runCatching { player.playbackParams = PlaybackParams().setSpeed(speed) }
    }

    // Play the window, then stop at its end.
    LaunchedEffect(prepared, generation) {
        if (!prepared) return@LaunchedEffect
        seekToWindow()
        applySpeed()
        player.start()
        playing = true
        while (isActive) {
            withFrameMillis { }
            positionMs = player.currentPosition.toDouble()
            if (positionMs >= windowEnd || !player.isPlaying) {
                if (player.isPlaying) player.pause()
                playing = false
                break
            }
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black).clickable(enabled = false) {}) {
        Box(Modifier.align(Alignment.Center).fillMaxWidth().aspectRatio(videoAspect)) {
            AndroidView(
                factory = { ctx ->
                    TextureView(ctx).apply {
                        surfaceTextureListener = object : TextureView.SurfaceTextureListener {
                            override fun onSurfaceTextureAvailable(texture: SurfaceTexture, width: Int, height: Int) {
                                runCatching {
                                    player.setSurface(Surface(texture))
                                    player.setDataSource(clip.file.absolutePath)
                                    player.setVolume(0f, 0f)
                                    player.setOnPreparedListener { prepared = true }
                                    player.prepareAsync()
                                }
                            }

                            override fun onSurfaceTextureSizeChanged(texture: SurfaceTexture, width: Int, height: Int) = Unit
                            override fun onSurfaceTextureDestroyed(texture: SurfaceTexture): Boolean = true
                            override fun onSurfaceTextureUpdated(texture: SurfaceTexture) = Unit
                        }
                    }
                },
                modifier = Modifier.fillMaxSize(),
            )
            Canvas(Modifier.fillMaxSize()) {
                val frame = Rect(0f, 0f, size.width, size.height)
                val upto = if (start != null) start + positionMs else clip.path.endMs
                val glow = if (upto >= clip.path.endMs) 1f else 0f
                drawArPath(clip.path, frame, upto, glow)
            }
        }

        // ── Lockup and close ──
        Row(
            Modifier
                .align(Alignment.TopStart)
                .statusBarsPadding()
                .padding(start = 20.dp, end = 14.dp, top = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                Image(painterResource(R.drawable.haraan_logo_white), contentDescription = "Haraan", modifier = Modifier.height(15.dp))
                Box(Modifier.padding(horizontal = 10.dp).width(1.dp).height(14.dp).background(Color.White.copy(alpha = 0.35f)))
                Text("REPLAY", color = Color.White.copy(alpha = 0.9f), fontSize = 9.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 2.6.sp)
            }
            if (onOpen3d != null) {
                Text(
                    "3D",
                    color = Color.White,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.ExtraBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Brand)
                        .clickable {
                            hostView.performHapticFeedback(Feel.SELECT)
                            onOpen3d()
                        }
                        .padding(horizontal = 14.dp, vertical = 9.dp),
                )
                Spacer(Modifier.width(8.dp))
            }
            Box(
                Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.10f))
                    .border(1.dp, Color.White.copy(alpha = 0.12f), CircleShape)
                    .clickable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) {
                Canvas(Modifier.size(11.dp)) {
                    val s = 1.7.dp.toPx()
                    drawLine(Color.White, Offset(0f, 0f), Offset(size.width, size.height), s, StrokeCap.Round)
                    drawLine(Color.White, Offset(size.width, 0f), Offset(0f, size.height), s, StrokeCap.Round)
                }
            }
        }

        // ── Speed and play again ──
        Row(
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(bottom = 22.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(
                Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(Glass)
                    .border(1.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(999.dp))
                    .padding(3.dp),
            ) {
                listOf(0.25f to "¼×", 0.5f to "½×", 1f to "1×").forEach { (value, label) ->
                    val selected = speed == value
                    Text(
                        label,
                        color = if (selected) Color.White else Color.White.copy(alpha = 0.6f),
                        fontSize = 13.sp,
                        fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (selected) Brand else Color.Transparent)
                            .clickable {
                                hostView.performHapticFeedback(Feel.SELECT)
                                speed = value
                                if (playing) applySpeed()
                            }
                            .padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                }
            }
            Spacer(Modifier.width(10.dp))
            Box(
                Modifier
                    .size(42.dp)
                    .clip(CircleShape)
                    .background(if (playing) Color.White.copy(alpha = 0.14f) else Brand)
                    .clickable {
                        hostView.performHapticFeedback(Feel.SELECT)
                        generation++
                    },
                contentAlignment = Alignment.Center,
            ) {
                Canvas(Modifier.size(14.dp)) {
                    val p = androidx.compose.ui.graphics.Path().apply {
                        moveTo(size.width * 0.18f, 0f)
                        lineTo(size.width, size.height / 2f)
                        lineTo(size.width * 0.18f, size.height)
                        close()
                    }
                    drawPath(p, Color.White)
                }
            }
        }
    }
}
