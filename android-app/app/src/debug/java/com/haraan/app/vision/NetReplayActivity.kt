package com.haraan.app.vision

import android.graphics.Bitmap
import android.graphics.SurfaceTexture
import android.media.MediaMetadataRetriever
import android.media.MediaPlayer
import android.os.Build
import android.os.Bundle
import android.view.Surface
import android.view.TextureView
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.withFrameMillis
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.haraan.app.BuildConfig
import com.haraan.app.camera.battersEndOnly
import com.haraan.app.camera.bowlersEndOf
import com.haraan.app.camera.deliveryCorridor
import com.haraan.app.camera.drawArPath
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.isActive
import kotlinx.coroutines.withContext
import java.io.File

/**
 * NET REPLAY — a recorded clip through the camera screen's own vision chain, then played
 * back at normal speed with the result laid over it, the way a ball-tracking broadcast does.
 *
 * Two passes, because analysis is not playback:
 *
 *  1. ANALYSE: every frame at 30 a second of clip, as the camera feeds them (sensor-sideways
 *     1280x720 luma, half-resolution U plane), through colour stump detection → batter's end
 *     only → WicketTracker → the delivery corridor → OpenCvBallTracker. Each delivery the
 *     tracker finishes ([FlightEnd]) is fitted in 3D through the ground both sets of stumps
 *     give, and kept with the moment it was called.
 *  2. PLAY: the clip itself at 1x. As each delivery is called its whole result appears —
 *     the flight, its shadow on the pitch, where it pitched, the speed — and stays until the
 *     next ball is bowled. Nothing else is drawn over the picture.
 *
 *     adb push net.mp4 /sdcard/Android/data/com.haraan.app/files/net.mp4
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://net-replay" \
 *         --ei from 1250 --ei to 1700
 *
 * `from`/`to` are frame numbers of the clip; omitted, the whole clip. Debug builds only.
 */
class NetReplayActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        val clip = intent?.getStringExtra("clip") ?: File(getExternalFilesDir(null), "net.mp4").absolutePath
        val from = intent?.getIntExtra("from", 0) ?: 0
        val to = intent?.getIntExtra("to", Int.MAX_VALUE) ?: Int.MAX_VALUE
        val every = intent?.getIntExtra("every", 1) ?: 1
        // `--es show result|setup`: the live camera's own result overlay or setup boxes, over
        // this clip's frames and analysis, for checking them without a match to join.
        val show = intent?.getStringExtra("show")
        setContent { NetReplayScreen(clip, from, to, every, show) }
    }
}

/**
 * One finished delivery: when it was called on the clip's clock, and what to draw — and its
 * own frames, small JPEGs on the clip's clock, from just before the ball was first seen to a
 * beat after it was called, for the replay to play itself; see [DeliveryReplayView].
 */
private class Delivery(val calledMs: Double, val startMs: Double, val path: ArPath, val speedKmh: Double?, val flight: Flight3d? = null) {
    val frames = ArrayList<Pair<Double, ByteArray>>()
    var tailUntilMs = calledMs + REPLAY_TAIL_MS
}

private sealed interface Phase {
    class Analysing(val progress: Float, val preview: ImageBitmap?, val note: String) : Phase
    class Playing(
        val deliveries: List<Delivery>,
        val fromMs: Long,
        val toMs: Long,
        val videoAspect: Float,
        val fps: Double,
        val farBase: Point2? = null,
        val nearBase: Point2? = null,
    ) : Phase
    class Failed(val why: String) : Phase
}

@Composable
private fun NetReplayScreen(clip: String, from: Int, to: Int, every: Int, show: String?) {
    var phase by remember { mutableStateOf<Phase>(Phase.Analysing(0f, null, "opening the clip")) }
    LaunchedEffect(clip) {
        withContext(Dispatchers.Default) { analyse(clip, from, to, every) { phase = it } }
    }
    Box(Modifier.fillMaxSize().background(Color.Black)) {
        when (val p = phase) {
            is Phase.Analysing -> AnalysingView(p)
            is Phase.Playing -> when {
                show == "setup" -> SetupPreview(p)
                show == "result" && p.deliveries.any { it.frames.size > 1 } -> ResultPreview(p)
                p.deliveries.any { it.frames.size > 1 } -> DeliveryReplayView(p)
                else -> PlaybackView(clip, p)
            }
            is Phase.Failed -> Text(p.why, color = Color.White, modifier = Modifier.align(Alignment.Center).padding(24.dp))
        }
    }
}

@Composable
private fun AnalysingView(p: Phase.Analysing) {
    Box(Modifier.fillMaxSize()) {
        p.preview?.let {
            Image(
                it,
                contentDescription = null,
                contentScale = ContentScale.Fit,
                modifier = Modifier.fillMaxSize().graphicsLayer { alpha = 0.35f },
            )
        }
        Column(Modifier.align(Alignment.Center).padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text("Analysing deliveries", color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.SemiBold)
            Box(
                Modifier
                    .padding(top = 14.dp)
                    .width(220.dp)
                    .height(4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(Color.White.copy(alpha = 0.2f)),
            ) {
                Box(Modifier.fillMaxHeight().fillMaxWidth(p.progress.coerceIn(0f, 1f)).background(Color(0xFFD7141E)))
            }
            Text(p.note, color = Color.White.copy(alpha = 0.7f), fontSize = 12.sp, modifier = Modifier.padding(top = 10.dp))
        }
    }
}

/*
 * EACH DELIVERY REPLAYED FROM ITS OWN FRAMES. Played through the video decoder, a 120 fps
 * clip on the emulator showed a frame before and a frame after and nothing between, and on a
 * phone at 1x the ball is a second of blur. Here every analysed frame around each delivery
 * is shown on the clip's own clock — at the speed it was bowled — with the path drawn
 * as far as that frame's moment — the ball and its trace arrive together — then the whole
 * result held, then the next delivery.
 */
@Composable
private fun DeliveryReplayView(p: Phase.Playing) {
    // The camera screen's own after-ball replay, one delivery after another, round again.
    val shown = remember(p) { p.deliveries.filter { it.frames.size > 1 } }
    var round by remember { mutableStateOf(0) }
    val which = if (shown.isEmpty()) 0 else round % shown.size
    val d = shown.getOrNull(which) ?: return
    androidx.compose.runtime.key(round) {
        com.haraan.app.camera.DeliveryReplaySequence(
            frames = d.frames,
            path = d.path,
            speedKmh = d.speedKmh,
            flight = d.flight,
            stadiumAutoCloseMs = STADIUM_HOLD_MS,
            onDone = { round++ },
        )
    }
}

@Composable
private fun PlaybackView(clip: String, p: Phase.Playing) {
    val player = remember { MediaPlayer() }
    var prepared by remember { mutableStateOf(false) }
    var nowMs by remember { mutableDoubleStateOf(p.fromMs.toDouble()) }
    DisposableEffect(Unit) { onDispose { runCatching { player.release() } } }

    // Play the range at 1x, looping; the overlay reads the player's own position each frame.
    /*
     * SLOW, AND HELD AT THE END. A high-frame-rate clip at 1x is a second of blur — on the
     * emulator a 120 fps 1080p clip could not even be decoded that fast and looked stuck —
     * and looping straight back to the start never let the result be read. So: slowed to
     * about 30 shown frames a second (a quarter speed at 120 fps), and the last frame held
     * with the result on it before the clip starts again.
     */
    val speed = (30.0 / p.fps).coerceIn(0.2, 1.0).toFloat()
    LaunchedEffect(prepared) {
        if (!prepared) return@LaunchedEffect
        runCatching { player.playbackParams = android.media.PlaybackParams().setSpeed(speed) }
        player.seekTo(p.fromMs, MediaPlayer.SEEK_CLOSEST)
        player.start()
        while (isActive) {
            withFrameMillis { }
            val pos = player.currentPosition.toLong()
            nowMs = pos.toDouble()
            if (pos >= p.toMs || !player.isPlaying) {
                if (player.isPlaying) player.pause()
                nowMs = p.toMs.toDouble()
                kotlinx.coroutines.delay(END_HOLD_MS)
                player.seekTo(p.fromMs, MediaPlayer.SEEK_CLOSEST)
                kotlinx.coroutines.delay(150)
                runCatching { player.playbackParams = android.media.PlaybackParams().setSpeed(speed) }
                player.start()
            }
        }
    }

    // The delivery on show: the latest one already called, until the next ball is bowled.
    val showing = p.deliveries.lastOrNull { it.calledMs <= nowMs }
        ?.takeIf { d -> p.deliveries.none { it.startMs > d.calledMs && it.startMs <= nowMs } }
    val shown by animateFloatAsState(if (showing != null) 1f else 0f, tween(180), label = "result")
    var lastShown by remember { mutableStateOf<Delivery?>(null) }
    if (showing != null) lastShown = showing
    val drawn = showing ?: lastShown

    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Box(Modifier.fillMaxWidth().aspectRatio(p.videoAspect)) {
            AndroidView(
                factory = { ctx ->
                    TextureView(ctx).apply {
                        surfaceTextureListener = object : TextureView.SurfaceTextureListener {
                            override fun onSurfaceTextureAvailable(texture: SurfaceTexture, width: Int, height: Int) {
                                runCatching {
                                    player.setSurface(Surface(texture))
                                    player.setDataSource(clip)
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
            if (drawn != null && shown > 0f) {
                Canvas(Modifier.fillMaxSize().graphicsLayer { alpha = shown }) {
                    // The whole result at once, as a broadcast shows it.
                    drawArPath(drawn.path, Rect(0f, 0f, size.width, size.height), drawn.path.endMs, 0f)
                }
                drawn.speedKmh?.let { kmh ->
                    Column(
                        Modifier
                            .align(Alignment.TopStart)
                            .padding(12.dp)
                            .graphicsLayer { alpha = shown }
                            .background(Color(0xD9101418), RoundedCornerShape(9.dp))
                            .padding(horizontal = 11.dp, vertical = 7.dp),
                    ) {
                        Text("Speed", color = Color.White.copy(alpha = 0.72f), fontSize = 10.sp)
                        Text("%.0f km/h".format(kmh), color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
        Text(
            "${p.deliveries.size} deliver${if (p.deliveries.size == 1) "y" else "ies"} found",
            color = Color.White.copy(alpha = 0.55f),
            fontSize = 11.sp,
            modifier = Modifier.align(Alignment.TopCenter).statusBarsPadding().padding(top = 8.dp),
        )
    }
}

private suspend fun analyse(clip: String, from: Int, to: Int, every: Int, publish: (Phase) -> Unit) {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) {
        publish(Phase.Failed("needs Android 9+"))
        return
    }
    val retriever = MediaMetadataRetriever()
    try {
        retriever.setDataSource(clip)
    } catch (t: Throwable) {
        publish(Phase.Failed("cannot open $clip: ${t.message}"))
        return
    }
    val count = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_FRAME_COUNT)?.toIntOrNull() ?: 0
    val durationMs = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: 0L
    val vw = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toFloatOrNull() ?: 9f
    val vh = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toFloatOrNull() ?: 16f
    val rot = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: 0
    val videoAspect = if (rot == 90 || rot == 270) vh / vw else vw / vh
    val fps = if (durationMs > 0) count * 1000.0 / durationMs else 30.0
    /*
     * Every frame of the clip by default. At 30 a second (what the live camera analyses) a
     * 120 fps net clip gave 2 believable deliveries of 11; every frame gave 7 — the far half
     * of the flight, where it pitches, is only ever seen at the higher rate. `--ei every 4`
     * analyses as the live camera does, for comparison.
     */
    val stride = maxOf(1, every)
    val first = from.coerceAtLeast(0)
    val last = minOf(to, count - 1)

    val stumps = ColourStumpDetector()
    val wickets = WicketTracker()
    val ball = OpenCvBallTracker()
    val camera = CameraIntrinsics(ASSUMED_FOCAL_FW, "assumed")
    val aspect = UPRIGHT_W.toFloat() / UPRIGHT_H
    val still = FrameMotion(0f, 0f, 1f, 0f, 30, 32)
    val y = ByteArray(SENSOR_W * SENSOR_H)
    val u = ByteArray(SENSOR_W / 2 * (SENSOR_H / 2))
    val pixels = IntArray(UPRIGHT_W * UPRIGHT_H)
    var lock: WicketLock? = null
    var bowlersEnd: BowlersEnd? = null
    val nearEnd = BowlersEndFilter()
    var corridor: FloatArray? = null
    val steadyLock = SteadyWicket()
    val recentFrames = ArrayDeque<Pair<Double, ByteArray>>()
    var lastStumpsMs = Long.MIN_VALUE / 2
    var lastCorridorMs = Long.MIN_VALUE / 2
    val deliveries = ArrayList<Delivery>()
    var analysed = 0
    try {
        /*
         * WARM-UP, as a real setup has one: at the net the phone is placed and the stumps are
         * locked long before anyone bowls. A clip cut mid run-up starts cold, with the bowler
         * running through the picture, and the lock was still flickering when the ball was
         * let go. So the stumps alone are watched over the opening seconds first; then the
         * clip is analysed from its start with that lock in place.
         */
        // The warm-up samples 30 a second whatever the analysis rate: it is three seconds of
        // clip either way, which is what the stumps need.
        val warmStride = maxOf(1, Math.round(fps / 30.0).toInt())
        val warmEnd = minOf(last, first + WARM_UP_FRAMES * warmStride)
        var w = first
        var warmed = 0
        while (w <= warmEnd) {
            val n = minOf(BATCH, warmEnd - w + 1)
            for ((k, raw) in retriever.getFramesAtIndex(w, n).withIndex()) {
                val index = w + k
                if ((index - first) % warmStride != 0) {
                    raw.recycle()
                    continue
                }
                val up = Bitmap.createScaledBitmap(raw, UPRIGHT_W, UPRIGHT_H, true)
                if (up !== raw) raw.recycle()
                up.getPixels(pixels, 0, UPRIGHT_W, 0, 0, UPRIGHT_W, UPRIGHT_H)
                ClipFrames.toSensorYuv(pixels, y, u)
                val seen = stumps.detectCandidates(y, SENSOR_W, SENSOR_H, SENSOR_W, 90, u, SENSOR_W / 2, 1, focus = wickets.focus())
                bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
                lock = wickets.onFrame(battersEndOnly(seen, camera, bowlersEnd), still, aspect, (index * 1000.0 / fps).toLong())
                steadyLock.add(lock, (index * 1000.0 / fps).toLong())
                if (++warmed % 6 == 0) publish(Phase.Analysing(0f, up.asImageBitmap(), "finding the stumps"))
            }
            w += n
        }

        var batchStart = first
        while (batchStart <= last) {
            val n = minOf(BATCH, last - batchStart + 1)
            val frames = retriever.getFramesAtIndex(batchStart, n)
            for ((k, raw) in frames.withIndex()) {
                val index = batchStart + k
                if ((index - first) % stride != 0) {
                    raw.recycle()
                    continue
                }
                val up = Bitmap.createScaledBitmap(raw, UPRIGHT_W, UPRIGHT_H, true)
                if (up !== raw) raw.recycle()
                up.getPixels(pixels, 0, UPRIGHT_W, 0, 0, UPRIGHT_W, UPRIGHT_H)
                ClipFrames.toSensorYuv(pixels, y, u)
                val ts = (index * 1000.0 / fps).toLong()
                // A small copy of every analysed frame, kept a few seconds back, so each
                // delivery can be replayed frame by frame without the video decoder.
                val thumb = jpegOf(up)
                recentFrames.addLast(ts.toDouble() to thumb)
                while (recentFrames.isNotEmpty() && recentFrames.first().first < ts - RECENT_FRAMES_MS) recentFrames.removeFirst()
                for (d in deliveries) if (ts > d.calledMs && ts <= d.tailUntilMs) d.frames += ts.toDouble() to thumb

                // Stumps every frame until confirmed, then ten a second — as the camera screen.
                val confirmed = lock?.state == WicketTrackState.CONFIRMED
                if (!confirmed || ts - lastStumpsMs >= STUMPS_EVERY_MS) {
                    lastStumpsMs = ts
                    val seen = stumps.detectCandidates(
                        y, SENSOR_W, SENSOR_H, SENSOR_W, 90, u, SENSOR_W / 2, 1,
                        focus = wickets.focus(),
                    )
                    bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
                    lock = wickets.onFrame(battersEndOnly(seen, camera, bowlersEnd), still, aspect, ts)
                    steadyLock.add(lock, ts)
                }
                // Re-derived every frame until there is one, then once a second.
                if (corridor == null || ts - lastCorridorMs >= 1_000L) {
                    lastCorridorMs = ts
                    // As the camera screen: a flickering lock keeps the corridor it had.
                    val steady = steadyLock.value()
                    val c = deliveryCorridor(steady ?: lock, camera, UPRIGHT_W, aspect)
                    if (c != null) {
                        corridor = c
                        ball.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
                    } else if (lock == null && steady == null) {
                        corridor = null
                        ball.setCorridor(null, null, null, null, null)
                    }
                }
                ball.onFrame(y, SENSOR_W, SENSOR_H, SENSOR_W, 90, ts)
                val l = lock
                // A flight a newer track replaced before it went quiet is finished now.
                val replaced = ball.takeFinished()?.takeIf { fin ->
                    l != null && FlightEnd.check(fin, fin.last().timestampMs + FlightEnd.QUIET_MS, aspect, l) != null
                }
                val track = replaced ?: ball.track()
                if (l != null && (replaced != null || FlightEnd.check(track, ts, aspect, l) != null)) {
                    // Measured from the steadied stumps, not this frame's: see [SteadyWicket].
                    deliveryOf(track, steadyLock.value() ?: l, bowlersEnd, camera, aspect, ts)?.let { d ->
                        d.frames += recentFrames.filter { it.first >= d.startMs - REPLAY_LEAD_MS }
                        deliveries += d
                    }
                    // A fresh start for the next ball, as an Auto segment does.
                    if (replaced == null) ball.reset()
                }
                analysed++
                if (analysed % 6 == 0) {
                    publish(
                        Phase.Analysing(
                            (index - first).toFloat() / (last - first + 1).coerceAtLeast(1),
                            up.asImageBitmap(),
                            "frame $index of $last · ${deliveries.size} found",
                        ),
                    )
                }
            }
            batchStart += n
        }
    } finally {
        retriever.release()
        stumps.release()
        ball.release()
    }
    publish(
        Phase.Playing(
            deliveries,
            (first * 1000.0 / fps).toLong(),
            (last * 1000.0 / fps).toLong(),
            videoAspect,
            fps,
            farBase = (steadyLock.value() ?: lock)?.anchor?.base,
            nearBase = bowlersEnd?.base,
        ),
    )
}

/** A clip frame, upright, drawn to fill the box it is given. */
@Composable
private fun FrameBackdrop(bytes: ByteArray) {
    val image = remember(bytes) { android.graphics.BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap() }
    image?.let { Image(it, contentDescription = null, contentScale = ContentScale.FillBounds, modifier = Modifier.fillMaxSize()) }
}

/** The live camera's result overlay over the delivery's last frame, as the camera would leave it. */
@Composable
private fun ResultPreview(p: Phase.Playing) {
    val d = p.deliveries.first { it.frames.size > 1 }
    var replaying by remember { mutableStateOf(false) }
    // As the camera screen: the replay plays itself once, a moment after the result lands.
    LaunchedEffect(d) {
        kotlinx.coroutines.delay(1_000)
        replaying = true
    }
    Box(Modifier.fillMaxSize().background(Color.Black), contentAlignment = Alignment.Center) {
        Box(Modifier.fillMaxWidth().aspectRatio(9f / 16f)) {
            FrameBackdrop(d.frames.last().second)
            com.haraan.app.camera.LiveResultOverlay(
                path = d.path,
                uprightAspect = 9f / 16f,
                speedKmh = d.speedKmh,
                onReplay = { replaying = true },
            )
        }
        if (replaying) {
            com.haraan.app.camera.DeliveryReplaySequence(
                frames = d.frames,
                path = d.path,
                speedKmh = d.speedKmh,
                flight = d.flight,
                stadiumAutoCloseMs = STADIUM_HOLD_MS,
                onDone = { replaying = false },
            )
        }
    }
}

/** The live camera's setup boxes over a frame of the clip, with what the clip's analysis found. */
@Composable
private fun SetupPreview(p: Phase.Playing) {
    val frame = p.deliveries.firstOrNull()?.frames?.firstOrNull()?.second
    val guide = StumpAlignGuide.guide(ASSUMED_FOCAL_FW, 9f / 16f)
    Box(Modifier.fillMaxSize().background(Color.Black), contentAlignment = Alignment.Center) {
        Box(Modifier.fillMaxWidth().aspectRatio(9f / 16f)) {
            frame?.let { FrameBackdrop(it) }
        }
        com.haraan.app.camera.StumpAlignSetup(
            guide = guide,
            uprightAspect = 9f / 16f,
            farSeen = p.farBase,
            nearSeen = p.nearBase,
            onDone = { _, _ -> },
            onCancel = {},
        )
    }
}

/** A called delivery: fitted in 3D through the ground the stumps give, or the seen trail. */
private fun deliveryOf(
    track: List<BallSighting>,
    lock: WicketLock,
    bowlersEnd: BowlersEnd?,
    camera: CameraIntrinsics,
    aspect: Float,
    calledMs: Long,
): Delivery? {
    if (track.size < FlightEnd.MIN_FLIGHT_POINTS) return null
    val ground = PitchGround.best(lock, camera, UPRIGHT_W, UPRIGHT_H, tilt = null, bowlersEnd = bowlersEnd)
        ?.pitchCamera(UPRIGHT_W, UPRIGHT_H)
    val m = FlightMetrics.of(track, aspect, quad = null, wicket = lock, ground = ground)
    val f3 = m.flight3d
    // Only a delivery the 3D fit accepts is shown: a broadcast result is a flight with a
    // speed and a pitching point, and a track the fit refuses — something crossing behind
    // the net, a fielder — is not a ball bowled.
    if (f3 == null || ground == null) return null
    val path = ArPath.fromFlight(f3, ground) ?: return null
    if (path.isEmpty) return null
    return Delivery(calledMs.toDouble(), track.first().timestampMs.toDouble(), path, f3.speedKmh, f3)
}

private const val UPRIGHT_W = 720
private const val UPRIGHT_H = 1280
private const val SENSOR_W = 1280
private const val SENSOR_H = 720
private const val ASSUMED_FOCAL_FW = 1.4

/** A delivery's replay starts this long before its first sighting and ends this long after it was called. */
private const val REPLAY_LEAD_MS = 450.0
private const val REPLAY_TAIL_MS = 700.0

/** How far back analysed frames are kept for a delivery called later. */
private const val RECENT_FRAMES_MS = 4_000L

/** The replay's own frames: small enough to keep, sharp enough on a phone screen. */
private const val REPLAY_W = 360
private const val REPLAY_H = 640


private fun jpegOf(up: Bitmap): ByteArray {
    val small = Bitmap.createScaledBitmap(up, REPLAY_W, REPLAY_H, true)
    val out = java.io.ByteArrayOutputStream(32 * 1024)
    small.compress(Bitmap.CompressFormat.JPEG, 78, out)
    if (small !== up) small.recycle()
    return out.toByteArray()
}

/** The result on the last frame is held this long before the 3D stadium replay. */
private const val RESULT_HOLD_MS = 1_200L

/** The 3D stadium replay closes itself this long after it has played. */
private const val STADIUM_HOLD_MS = 1_500L

/** The last frame, with the result on it, is held this long before the clip plays again. */
private const val END_HOLD_MS = 2_000L

/** How often a confirmed stumps lock is checked, as the camera screen's one-in-three at 30 fps. */
private const val STUMPS_EVERY_MS = 100L

/** Analysed frames watched for the stumps alone before the deliveries are. */
private const val WARM_UP_FRAMES = 90

/** Clip frames decoded per batch: one sequential decode instead of a seek per frame. */
private const val BATCH = 16
