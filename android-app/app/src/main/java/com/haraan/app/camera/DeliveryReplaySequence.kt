package com.haraan.app.camera

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.withFrameNanos
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.vision.ArPath
import com.haraan.app.vision.Flight3d
import com.haraan.app.vision.FlightReplayOverlay
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.util.concurrent.Executors

/**
 * THE LAST FEW SECONDS OF WHAT THE CAMERA SAW, small, for the replay after a ball.
 *
 * Each frame the analyser looks at while a ball is being filmed is turned upright, shrunk to
 * [WIDTH] x [HEIGHT] and kept as a JPEG for [KEEP_MS]. When the ball is called the frames
 * around the flight are already here, so the replay starts at once — no waiting for the clip
 * to be written and decoded. The shrinking and compressing happen on their own thread; the
 * analyser only hands over the bitmap.
 */
internal class ReplayFrameRing {
    private val lock = Any()
    private val frames = ArrayDeque<Pair<Double, ByteArray>>()
    private val worker = Executors.newSingleThreadExecutor { r -> Thread(r, "replay-frames").apply { isDaemon = true } }

    private val pending = java.util.concurrent.atomic.AtomicInteger(0)

    /**
     * Takes ownership of [frame] (any orientation), turned by [rotationDegrees] to upright.
     * A phone that cannot keep up loses replay frames, never camera frames: past
     * [MAX_PENDING] waiting, the new one is dropped.
     */
    fun add(timestampMs: Double, frame: Bitmap, rotationDegrees: Int) {
        if (pending.get() >= MAX_PENDING) {
            frame.recycle()
            return
        }
        pending.incrementAndGet()
        worker.execute {
            try {
                val turned = if (rotationDegrees % 360 != 0) {
                    Bitmap.createBitmap(frame, 0, 0, frame.width, frame.height, Matrix().apply { postRotate(rotationDegrees.toFloat()) }, false)
                } else {
                    frame
                }
                val small = Bitmap.createScaledBitmap(turned, WIDTH, HEIGHT, true)
                val out = ByteArrayOutputStream(32 * 1024)
                small.compress(Bitmap.CompressFormat.JPEG, QUALITY, out)
                if (small !== turned) small.recycle()
                if (turned !== frame) turned.recycle()
                synchronized(lock) {
                    frames.addLast(timestampMs to out.toByteArray())
                    while (frames.isNotEmpty() && frames.first().first < timestampMs - KEEP_MS) frames.removeFirst()
                }
            } catch (_: Throwable) {
                // A frame lost to a replay is not worth disturbing the camera for.
            } finally {
                frame.recycle()
                pending.decrementAndGet()
            }
        }
    }

    /** The frames from [fromMs] on, oldest first. */
    fun since(fromMs: Double): List<Pair<Double, ByteArray>> = synchronized(lock) { frames.filter { it.first >= fromMs } }

    fun clear() = synchronized(lock) { frames.clear() }

    fun release() = worker.shutdownNow()

    companion object {
        const val WIDTH = 360
        const val HEIGHT = 640
        const val QUALITY = 78
        const val KEEP_MS = 4_000.0
        const val MAX_PENDING = 4

        /** The replay opens this long before the ball was first seen... */
        const val LEAD_MS = 450.0
    }
}

/**
 * AFTER A BALL: the delivery again at the speed it was bowled, with the path, the channel
 * between the wickets and the 3D stumps drawing in as the ball goes; the finished picture
 * held a moment; then the same flight in the 3D stadium. The camera screen and the net replay
 * show a delivery the same way.
 */
@Composable
internal fun DeliveryReplaySequence(
    frames: List<Pair<Double, ByteArray>>,
    path: ArPath,
    speedKmh: Double?,
    flight: Flight3d?,
    /** Closes the stadium itself this long after it has played (Auto: nobody holding the phone). */
    stadiumAutoCloseMs: Long?,
    onDone: () -> Unit,
) {
    var image by remember { mutableStateOf<ImageBitmap?>(null) }
    var atMs by remember { mutableStateOf(frames.firstOrNull()?.first ?: path.startMs) }
    var stadium by remember { mutableStateOf<CompletableDeferred<Unit>?>(null) }
    LaunchedEffect(frames, path) {
        // The stadium's textures are made now, while the delivery plays, so its first frame
        // is not where they get built.
        launch(Dispatchers.Default) { com.haraan.app.vision.ReplayTextures.warm() }
        if (frames.size >= 2) {
            val clipStart = frames.first().first
            val clipEnd = frames.last().first
            val began = System.nanoTime()
            var shown = -1
            while (true) {
                val clipNow = clipStart + (System.nanoTime() - began) / 1e6
                val i = frames.indexOfLast { it.first <= clipNow }.coerceAtLeast(0)
                if (i != shown) {
                    val bytes = frames[i].second
                    image = withContext(Dispatchers.Default) { BitmapFactory.decodeByteArray(bytes, 0, bytes.size) }?.asImageBitmap()
                    shown = i
                }
                atMs = clipNow
                if (clipNow >= clipEnd) break
                withFrameNanos { }
            }
        }
        atMs = maxOf(atMs, path.endMs)
        delay(RESULT_HOLD_MS)
        if (flight != null) {
            val done = CompletableDeferred<Unit>()
            stadium = done
            done.await()
            stadium = null
        }
        onDone()
    }

    Box(Modifier.fillMaxSize().background(Color.Black), contentAlignment = Alignment.Center) {
        Box(Modifier.fillMaxWidth().aspectRatio(ReplayFrameRing.WIDTH.toFloat() / ReplayFrameRing.HEIGHT)) {
            Canvas(Modifier.fillMaxSize()) {
                image?.let { img ->
                    drawImage(img, dstOffset = IntOffset.Zero, dstSize = IntSize(size.width.toInt(), size.height.toInt()))
                }
                val glow = if (atMs >= path.endMs) 1f else 0f
                drawArPath(path, Rect(0f, 0f, size.width, size.height), atMs, glow)
            }
            val bounced = path.bounceMs?.let { atMs >= it } ?: (atMs >= path.endMs)
            if (bounced && speedKmh != null) {
                Column(
                    Modifier
                        .align(Alignment.TopStart)
                        .padding(12.dp)
                        .background(Color(0xD9101418), RoundedCornerShape(9.dp))
                        .padding(horizontal = 11.dp, vertical = 7.dp),
                ) {
                    Text("Speed", color = Color.White.copy(alpha = 0.72f), fontSize = 10.sp)
                    Text("%.0f km/h".format(speedKmh), color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
        if (stadium == null) {
            Text(
                "Replay",
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 11.sp,
                modifier = Modifier.align(Alignment.TopCenter).statusBarsPadding().padding(top = 8.dp),
            )
        }
        val done = stadium
        if (done != null && flight != null) {
            FlightReplayOverlay(
                flight = flight,
                onClose = { done.complete(Unit) },
                autoCloseAfterMs = stadiumAutoCloseMs,
            )
        }
    }
}

/*
 * The finished picture is held this long before the 3D stadium: long enough to see the
 * stumps light with the answer, no longer. At 1.2 s the hand-off read as the app stalling.
 */
private const val RESULT_HOLD_MS = 400L
