package com.haraan.app.camera

import android.graphics.Bitmap
import android.graphics.ImageFormat
import android.graphics.Rect
import android.media.Image
import android.os.SystemClock
import android.util.Log
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageInfo
import androidx.camera.core.ImageProxy
import androidx.camera.core.impl.TagBundle
import androidx.camera.core.impl.utils.ExifData
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import com.haraan.app.vision.CameraIntrinsics
import com.haraan.app.vision.ClipFrames
import com.haraan.app.vision.FastFrameSampler
import com.haraan.app.vision.VideoFrameStream
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import java.nio.ByteBuffer
import java.util.concurrent.CountDownLatch
import java.util.concurrent.Executor
import java.util.concurrent.RejectedExecutionException
import java.util.concurrent.TimeUnit
import kotlin.math.roundToInt

/**
 * A FILMED DELIVERY AS THE CAMERA — debug builds only.
 *
 * The live screen does its own per-frame work (stumps, corridor, ball, the call, the result
 * on the picture, the replay straight after) and none of it could be tried without a bowler:
 * the clip analyser and the scoreboard run [com.haraan.app.vision.DeliveryChain], not this
 * screen. So a video stands in for the lens. Each frame is decoded to exactly what the
 * camera hands the analyser — sideways 1280x720 luma and the de-interleaved blue-difference
 * plane, as [ClipFrames] defines them — wrapped as an [ImageProxy] and given to the screen's
 * own analyzer on its own single thread, one at a time, never faster than it was filmed.
 * The camera's real frames are dropped while this runs; the screen shows the video instead.
 *
 *     adb push IMG_6053.MOV /sdcard/Android/data/com.haraan.app/files/net.mp4
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://camera/TEST?video=net.mp4"
 *
 * Times are the video's own (its presentation times on a clock that keeps going forward as
 * the clip loops), so speeds are measured as filmed even when the phone decodes slower than
 * real time. About thirty frames a second are given, as the live analyser looks at: a
 * 120 fps iPhone clip is read through its bottom temporal layer.
 */
internal class VideoCameraFeed(
    private val path: String,
    private val analyzer: ImageAnalysis.Analyzer,
    private val executor: Executor,
) {
    private val shown = MutableStateFlow<Bitmap?>(null)

    /** The frame being analysed, upright and small, for the screen. */
    val picture: StateFlow<Bitmap?> = shown

    @Volatile private var running = false
    private var thread: Thread? = null

    fun start() {
        if (running) return
        running = true
        thread = Thread(::run, "video-camera-feed").also { it.isDaemon = true; it.start() }
    }

    fun stop() {
        running = false
        thread?.interrupt()
        thread = null
    }

    private class Stopped : RuntimeException()

    private fun run() {
        val y = ByteArray(ClipFrames.SENSOR_W * ClipFrames.SENSOR_H)
        val u = ByteArray(ClipFrames.SENSOR_W / 2 * (ClipFrames.SENSOR_H / 2))
        // Neutral red-difference: nothing in the live chain reads it, but a YUV_420_888 has three planes.
        val v = ByteArray(u.size) { 128.toByte() }
        val sampler = runCatching { FastFrameSampler() }.getOrElse {
            Log.w(TAG, "no OpenCV for the video feed", it)
            return
        }
        // Where the clip's first frame sits on the camera clock; moved on each time round.
        var originNs = SystemClock.elapsedRealtimeNanos()
        try {
            while (running) {
                val stream = runCatching { VideoFrameStream.open(path) }.getOrElse {
                    Log.w(TAG, "can't open $path", it)
                    return
                }
                val wallStart = SystemClock.elapsedRealtimeNanos()
                var lastPtsMs = 0.0
                var frames = 0
                try {
                    val fps = stream.frameRate
                    val layers = if (stream.layered && fps >= LAYERED_ABOVE_FPS) VideoFrameStream.Layers(fps, Int.MAX_VALUE, 0) else null
                    val step = if (layers != null || fps <= 0.0) 1 else maxOf(1, (fps / LIVE_FPS).roundToInt())
                    Log.i(TAG, "feeding $path: %.1f fps, ${if (layers != null) "bottom layer" else "every $step"}".format(fps))
                    stream.decode(0, Int.MAX_VALUE, step, layers) { _, ptsMs, image ->
                        if (!running) throw Stopped()
                        sampler.sensorYuv(image, stream.rotation, y, u)
                        val shot = sampler.thumbnailParts(PICTURE_W, PICTURE_H).toBitmap()
                        // Never faster than it was filmed.
                        val wait = wallStart + (ptsMs * 1e6).toLong() - SystemClock.elapsedRealtimeNanos()
                        if (wait > 0) Thread.sleep(wait / 1_000_000, (wait % 1_000_000).toInt())
                        shown.value = shot
                        analyse(VideoFeedFrame(y, u, v, originNs + (ptsMs * 1e6).toLong(), shot))
                        lastPtsMs = ptsMs
                        frames++
                    }
                } catch (_: Stopped) {
                    return
                } finally {
                    runCatching { stream.close() }
                }
                Log.i(TAG, "clip done: $frames frames over %.1f s; again".format(lastPtsMs / 1000.0))
                originNs += ((lastPtsMs + LOOP_GAP_MS) * 1e6).toLong()
                Thread.sleep(LOOP_GAP_MS)
            }
        } catch (_: InterruptedException) {
            // Stopped.
        } catch (t: Throwable) {
            Log.w(TAG, "video feed stopped", t)
        } finally {
            sampler.release()
        }
    }

    /** On the analysis thread, as the camera would; the next frame waits for this one. */
    private fun analyse(frame: VideoFeedFrame) {
        val done = CountDownLatch(1)
        try {
            executor.execute {
                try {
                    analyzer.analyze(frame)
                } finally {
                    done.countDown()
                }
            }
        } catch (_: RejectedExecutionException) {
            throw Stopped()
        }
        while (running && !done.await(200, TimeUnit.MILLISECONDS)) Unit
    }

    companion object {
        private const val TAG = "HaraanVideoFeed"
        private const val LIVE_FPS = 30.0
        private const val LAYERED_ABOVE_FPS = 100.0
        private const val LOOP_GAP_MS = 1_500L
        private const val PICTURE_W = 360
        private const val PICTURE_H = 640

        /*
         * The footage was not taken by this phone's lens, so its focal length is the one the clip
         * analyser assumes (DeliveryChain), and the scoreboard's numbers are comparable.
         */
        val INTRINSICS = CameraIntrinsics(1.4, "video file (assumed, as the clip analyser)")
    }
}

/** One decoded video frame, shaped as the camera's analysis frames are. */
internal class VideoFeedFrame(
    y: ByteArray,
    u: ByteArray,
    v: ByteArray,
    private val timestampNs: Long,
    /** The frame upright and small: what the screen shows, and what the replay keeps. */
    val picture: Bitmap,
) : ImageProxy {
    private val planeProxies: Array<ImageProxy.PlaneProxy> = arrayOf(
        plane(y, ClipFrames.SENSOR_W),
        plane(u, ClipFrames.SENSOR_W / 2),
        plane(v, ClipFrames.SENSOR_W / 2),
    )
    private var crop = Rect(0, 0, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H)

    private val info = object : ImageInfo {
        override fun getTagBundle(): TagBundle = TagBundle.emptyBundle()
        override fun getTimestamp(): Long = timestampNs
        // Sideways, as a phone held upright delivers its frames.
        override fun getRotationDegrees(): Int = 90
        override fun populateExifData(exifBuilder: ExifData.Builder) = Unit
    }

    override fun close() = Unit
    override fun getCropRect(): Rect = crop
    override fun setCropRect(rect: Rect?) {
        crop = rect ?: Rect(0, 0, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H)
    }
    override fun getFormat(): Int = ImageFormat.YUV_420_888
    override fun getHeight(): Int = ClipFrames.SENSOR_H
    override fun getWidth(): Int = ClipFrames.SENSOR_W
    override fun getPlanes(): Array<ImageProxy.PlaneProxy> = planeProxies
    override fun getImageInfo(): ImageInfo = info

    @ExperimentalGetImage
    override fun getImage(): Image? = null

    private fun plane(bytes: ByteArray, rowStride: Int) = object : ImageProxy.PlaneProxy {
        private val buffer = ByteBuffer.wrap(bytes)
        override fun getRowStride(): Int = rowStride
        override fun getPixelStride(): Int = 1
        override fun getBuffer(): ByteBuffer = buffer
    }
}

/** The video in place of the viewfinder, letterboxed as the preview is so every overlay lines up. */
@Composable
internal fun VideoFeedPicture(feed: VideoCameraFeed) {
    // Collected here, not in the camera screen, so only this recomposes thirty times a second.
    val shot by feed.picture.collectAsState()
    Box(Modifier.fillMaxSize().background(Color.Black)) {
        shot?.let {
            Image(
                bitmap = it.asImageBitmap(),
                contentDescription = null,
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Fit,
            )
        }
    }
}
