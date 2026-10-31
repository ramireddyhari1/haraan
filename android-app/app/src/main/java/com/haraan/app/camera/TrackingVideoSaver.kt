package com.haraan.app.camera

import android.content.ContentValues
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Typeface
import android.media.MediaCodec
import android.media.MediaCodecInfo
import android.media.MediaFormat
import android.media.MediaMuxer
import android.os.Build
import android.os.Environment
import android.os.Process
import android.provider.MediaStore
import android.util.Log
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.drawscope.CanvasDrawScope
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import com.haraan.app.vision.ArPath
import com.haraan.app.vision.BounceLength
import com.haraan.app.vision.ClipFrames
import com.haraan.app.vision.Flight3d
import com.haraan.app.vision.LbwProjector
import com.haraan.app.vision.LbwVerdict
import com.haraan.app.vision.ReplayFrameRenderer
import com.haraan.app.vision.VideoFrameStream
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

/**
 * ONE BALL, KEPT ON THE CAMERA PHONE TO CHECK AFTER THE MATCH.
 *
 * After each tracked ball a video goes into the phone's Gallery (Movies/Haraan Ball Tracking):
 * the delivery as filmed with the ball's path drawing in as it goes, the result card, then the
 * 3D replay — what the screen showed, so the call can be looked at again once the match is over.
 *
 * Made on one background thread, a ball at a time, so the camera keeps filming and tracking the
 * next delivery at full speed. No GL and no screen recording: the picture is drawn on an
 * ordinary bitmap and handed to the phone's video encoder frame by frame, at exact times.
 */
internal class TrackingVideoSaver private constructor(private val context: Context) {

    /** One ball to save. [clip] is this job's own copy and is deleted when done. */
    class Job(
        /** The recorded clip, or null to draw from [frames] instead. */
        val clip: File?,
        /** False when [clip] is not this job's to delete (the debug feed's own video). */
        val ownsClip: Boolean = true,
        /** The tracker's clock at the clip's first frame; with [clip]. */
        val clipStartSensorMs: Double?,
        /** The analyser's small frames around the ball, on the tracker's clock: the fallback picture. */
        val frames: List<Pair<Double, ByteArray>>,
        val path: ArPath,
        val flight: Flight3d?,
        /** Shown in the corner and the file name: the over and ball, or empty. */
        val label: String,
        val takenAtMs: Long = System.currentTimeMillis(),
    )

    /**
     * True while the camera is filming a ball. The saver stands still until it is over: the
     * tracker reading the ball live comes first, and a decode and encode running beside it
     * took the processor and the video decoder it needs.
     */
    @Volatile var filming = false

    private fun waitWhileFilming() {
        while (filming) Thread.sleep(100)
    }

    private val worker = Executors.newSingleThreadExecutor { r ->
        Thread({
            Process.setThreadPriority(Process.THREAD_PRIORITY_BACKGROUND)
            r.run()
        }, "tracking-video").apply { isDaemon = true }
    }

    fun save(job: Job) {
        worker.execute {
            waitWhileFilming()
            val began = System.nanoTime()
            val out = File(context.cacheDir, "tracking/render-${job.takenAtMs}.mp4")
            try {
                out.parentFile?.mkdirs()
                // A full phone is not where a match's footage goes to die half-written.
                if (context.cacheDir.usableSpace < MIN_FREE_BYTES) {
                    Log.w(TAG, "skipped: phone storage nearly full")
                    return@execute
                }
                Log.i(TAG, "job: clip ${job.clip?.name} start ${job.clipStartSensorMs} path ${job.path.startMs}..${job.path.endMs} (${if (job.path.isEmpty) "empty" else "drawn"}) 3D ${job.flight != null} frames ${job.frames.size}")
                val frames = render(job, out)
                val name = publish(out, job)
                Log.i(TAG, "saved $name: $frames frames in ${(System.nanoTime() - began) / 1_000_000} ms")
            } catch (t: Throwable) {
                Log.w(TAG, "ball not saved: ${t.message}", t)
            } finally {
                runCatching { out.delete() }
                job.clip?.takeIf { job.ownsClip }?.let { runCatching { it.delete() } }
            }
        }
    }

    // ── Drawing ──────────────────────────────────────────────────────────────────

    private fun render(job: Job, out: File): Int {
        val enc = Encoder(out)
        val target = FrameTarget()
        val compose = CanvasDrawScope()
        val density = Density(DENSITY)
        val full = Rect(0f, 0f, W.toFloat(), H.toFloat())
        val card = cardOf(job.flight, job.path)
        try {
            /*
             * THE DELIVERY AS FILMED. The path draws in at the ball's own times, the stumps light
             * at the end, and the result card comes up once it has pitched — as on the screen.
             */
            var last: Bitmap? = null
            fun frame(picture: Bitmap, atSensorMs: Double?) = target.paint(enc) { canvas ->
                canvas.drawColor(android.graphics.Color.BLACK)
                canvas.drawBitmap(picture, null, android.graphics.Rect(0, 0, W, H), bitmapPaint)
                val upto = atSensorMs ?: job.path.endMs
                val glow = if (upto >= job.path.endMs) 1f else 0f
                compose.draw(density, LayoutDirection.Ltr, androidx.compose.ui.graphics.Canvas(canvas), Size(W.toFloat(), H.toFloat())) {
                    drawArPath(job.path, full, upto, glow)
                }
                header(canvas, job.label, "One-camera estimate")
                val pitched = job.path.bounceMs?.let { upto >= it } ?: (upto >= job.path.endMs)
                if (pitched) card.draw(canvas)
            }

            val clip = job.clip
            val clipStart = job.clipStartSensorMs
            if (clip != null && clipStart != null) {
                val fromMs = job.path.startMs - clipStart - LEAD_MS
                val toMs = job.path.endMs - clipStart + TAIL_MS
                val picture = Bitmap.createBitmap(ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H, Bitmap.Config.ARGB_8888)
                val argb = IntArray(ClipFrames.UPRIGHT_W * ClipFrames.UPRIGHT_H)
                var nextMs = fromMs
                val beganClip = System.nanoTime()
                var decoded = 0
                var drawn = 0
                var firstPts = Double.NaN
                var lastPts = Double.NaN
                VideoFrameStream.open(clip.absolutePath).use { stream ->
                    // A layered clip (an iPhone's) starts at the key frame before the window
                    // instead of the clip's first, and only its bottom layer is read — about the
                    // thirty a second this video is made at. Others are short and read whole.
                    val layers = if (stream.layered && stream.frameRate > 0) VideoFrameStream.Layers(stream.frameRate, Int.MAX_VALUE, 0) else null
                    val from = if (layers != null) (fromMs / 1000.0 * stream.frameRate).toInt().coerceAtLeast(0) else 0
                    // A real last frame, not Int.MAX_VALUE: the layered decode adds slack to it.
                    val to = if (layers != null) (toMs / 1000.0 * stream.frameRate).toInt() + 2 else Int.MAX_VALUE
                    try {
                        stream.decode(from, to, 1, layers) { _, ptsMs, image ->
                            decoded++
                            if (firstPts.isNaN()) firstPts = ptsMs
                            lastPts = ptsMs
                            if (ptsMs > toMs) throw DoneSignal
                            if (ptsMs + 0.5 >= nextMs) {
                                stream.upright(image, argb)
                                picture.setPixels(argb, 0, ClipFrames.UPRIGHT_W, 0, 0, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
                                frame(picture, clipStart + ptsMs)
                                drawn++
                                nextMs = maxOf(nextMs + FRAME_MS, ptsMs + FRAME_MS * 0.5)
                            }
                        }
                    } catch (_: DoneSignal) {
                    }
                }
                Log.i(TAG, "clip window ${fromMs.toInt()}..${toMs.toInt()} ms: decoded $decoded (pts $firstPts..$lastPts), drawn $drawn in ${(System.nanoTime() - beganClip) / 1_000_000} ms")
                last = picture
            } else if (job.frames.size >= 2) {
                // Resampled to the video's steady rate from the analyser's uneven one.
                val first = job.frames.first().first
                val end = job.frames.last().first
                var t = first
                var shown = -1
                var picture: Bitmap? = null
                while (t <= end) {
                    val i = job.frames.indexOfLast { it.first <= t }.coerceAtLeast(0)
                    if (i != shown) {
                        val bytes = job.frames[i].second
                        picture?.recycle()
                        picture = BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
                        shown = i
                    }
                    picture?.let { frame(it, t) }
                    t += FRAME_MS
                }
                last = picture
            }
            // The finished picture held a moment, path complete and the stumps lit.
            last?.let { pic -> repeat((HOLD_MS / FRAME_MS).toInt()) { frame(pic, null) } }

            // THE 3D REPLAY, as the screen plays it.
            job.flight?.let { flight ->
                val began3d = System.nanoTime()
                val replay = ReplayFrameRenderer(flight)
                var t = 0.0
                while (t <= replay.durationMs) {
                    val at = t.toInt()
                    target.paint(enc) { canvas ->
                        compose.draw(density, LayoutDirection.Ltr, androidx.compose.ui.graphics.Canvas(canvas), Size(W.toFloat(), H.toFloat())) {
                            replay.draw(this, at)
                        }
                        header(canvas, job.label, "3D replay · one-camera estimate")
                        card.draw(canvas)
                    }
                    t += FRAME_MS
                }
                Log.i(TAG, "3D: ${(replay.durationMs / FRAME_MS).toInt()} frames in ${(System.nanoTime() - began3d) / 1_000_000} ms")
            }
            return enc.finish()
        } catch (t: Throwable) {
            enc.abandon()
            throw t
        } finally {
            target.close()
        }
    }

    /**
     * WHERE A FRAME IS DRAWN: on the GPU, as the screen draws, into a buffer read straight back —
     * the 3D stadium took a third of a second a frame drawn in software. A phone that will not
     * draw there gets the software bitmap instead, slower but the same picture.
     */
    private inner class FrameTarget : java.io.Closeable {
        private val reader = android.media.ImageReader.newInstance(W, H, android.graphics.PixelFormat.RGBA_8888, 2)
        private var gpu = true
        private var bitmap: Bitmap? = null
        private var bytes: java.nio.ByteBuffer? = null

        fun paint(enc: Encoder, draw: (android.graphics.Canvas) -> Unit) {
            waitWhileFilming()
            if (gpu) {
                val drawn = runCatching {
                    // Whatever is left over from a frame that failed half-way is not this one.
                    reader.acquireLatestImage()?.close()
                    val surface = reader.surface
                    val canvas = surface.lockHardwareCanvas()
                    try {
                        draw(canvas)
                    } finally {
                        surface.unlockCanvasAndPost(canvas)
                    }
                    val deadline = System.nanoTime() + 2_000_000_000L
                    var image = reader.acquireLatestImage()
                    while (image == null && System.nanoTime() < deadline) {
                        Thread.sleep(1)
                        image = reader.acquireLatestImage()
                    }
                    (image ?: error("the GPU gave no frame back")).use { img ->
                        val plane = img.planes[0]
                        enc.add(plane.buffer, plane.rowStride, plane.pixelStride)
                    }
                }
                if (drawn.isSuccess) return
                Log.w(TAG, "drawing in software: ${drawn.exceptionOrNull()?.message}")
                gpu = false
            }
            val b = bitmap ?: Bitmap.createBitmap(W, H, Bitmap.Config.ARGB_8888).also { bitmap = it }
            val buf = bytes ?: java.nio.ByteBuffer.allocate(W * H * 4).also { bytes = it }
            draw(android.graphics.Canvas(b))
            buf.clear()
            // RGBA in memory, as the GPU's buffer is.
            b.copyPixelsToBuffer(buf)
            buf.rewind()
            enc.add(buf, W * 4, 4)
        }

        override fun close() {
            runCatching { reader.close() }
            bitmap?.recycle()
        }
    }

    private val bitmapPaint = Paint(Paint.FILTER_BITMAP_FLAG)
    private val shade = Paint().apply { color = 0xD9101418.toInt() }
    private val bold = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = android.graphics.Color.WHITE; typeface = Typeface.DEFAULT_BOLD }
    private val plain = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = android.graphics.Color.WHITE; typeface = Typeface.DEFAULT }

    /** The wordmark line across the top, the ball's label at the right. */
    private fun header(c: android.graphics.Canvas, label: String, note: String) {
        c.drawRect(0f, 0f, W.toFloat(), 104f, shade)
        bold.textSize = 26f
        bold.letterSpacing = 0.12f
        bold.alpha = 255
        c.drawText("HARAAN", 32f, 52f, bold)
        val mark = bold.measureText("HARAAN")
        bold.textSize = 19f
        bold.letterSpacing = 0.22f
        bold.alpha = 220
        c.drawText("BALL TRACKING", 32f + mark + 22f, 50f, bold)
        plain.textSize = 20f
        plain.alpha = 140
        c.drawText(note, 32f, 84f, plain)
        if (label.isNotBlank()) {
            bold.textSize = 24f
            bold.letterSpacing = 0.04f
            bold.alpha = 255
            c.drawText(label, W - 32f - bold.measureText(label), 52f, bold)
        }
        bold.letterSpacing = 0f
        bold.alpha = 255
    }

    /** The result card the camera screen shows: speed, where it pitched, the verdict. */
    private inner class Card(val speedKmh: Double?, val pitched: String?, val verdict: Pair<String, Int>?) {
        fun draw(c: android.graphics.Canvas) {
            if (speedKmh == null && pitched == null && verdict == null) return
            val left = 28f
            val top = H - 300f
            val width = 420f
            var height = 32f
            if (speedKmh != null) height += 64f
            if (pitched != null) height += 36f
            if (verdict != null) height += 50f
            c.drawRoundRect(RectF(left, top, left + width, top + height), 24f, 24f, shade)
            var y = top + 20f
            if (speedKmh != null) {
                bold.textSize = 52f
                val n = "%.0f".format(speedKmh)
                c.drawText(n, left + 24f, y + 50f, bold)
                plain.textSize = 22f
                plain.alpha = 190
                c.drawText("km/h", left + 32f + bold.measureText(n), y + 48f, plain)
                y += 64f
            }
            if (pitched != null) {
                plain.textSize = 24f
                plain.alpha = 225
                c.drawText(pitched, left + 24f, y + 28f, plain)
                y += 36f
            }
            if (verdict != null) {
                bold.textSize = 20f
                bold.letterSpacing = 0.1f
                val w = bold.measureText(verdict.first) + 28f
                val pill = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = verdict.second }
                c.drawRoundRect(RectF(left + 24f, y + 10f, left + 24f + w, y + 46f), 12f, 12f, pill)
                c.drawText(verdict.first, left + 38f, y + 35f, bold)
                bold.letterSpacing = 0f
            }
        }
    }

    private fun cardOf(flight: Flight3d?, path: ArPath): Card {
        val verdict = flight?.let { LbwProjector.fromFlight(it)?.verdict } ?: when (path.hitting) {
            true -> LbwVerdict.HITTING
            false -> LbwVerdict.MISSING
            null -> null
        }
        val pitchedM = flight?.bounceY?.takeIf { it in 0.0..PITCHED_MAX_M }
        return Card(
            speedKmh = flight?.speedKmh,
            pitched = pitchedM?.let { "%.1f m · %s".format(it, BounceLength.of(it).spoken) },
            verdict = when (verdict) {
                LbwVerdict.HITTING -> "HITTING" to 0xFFE11D2E.toInt()
                LbwVerdict.MISSING -> "MISSING" to 0xFF16A34A.toInt()
                LbwVerdict.UMPIRES_CALL -> "UMPIRE'S CALL" to 0xFFD97706.toInt()
                else -> null
            },
        )
    }

    // ── Into the Gallery ─────────────────────────────────────────────────────────

    private fun publish(file: File, job: Job): String {
        val stamp = SimpleDateFormat("yyyy-MM-dd HH-mm-ss", Locale.US).format(Date(job.takenAtMs))
        val ball = job.label.replace(Regex("[^A-Za-z0-9. ]"), "").trim()
        val name = if (ball.isNotEmpty()) "Haraan $ball $stamp.mp4" else "Haraan ball $stamp.mp4"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val resolver = context.contentResolver
            val values = ContentValues().apply {
                put(MediaStore.Video.Media.DISPLAY_NAME, name)
                put(MediaStore.Video.Media.MIME_TYPE, "video/mp4")
                put(MediaStore.Video.Media.RELATIVE_PATH, "${Environment.DIRECTORY_MOVIES}/$ALBUM")
                put(MediaStore.Video.Media.DATE_TAKEN, job.takenAtMs)
                put(MediaStore.Video.Media.IS_PENDING, 1)
            }
            val uri = resolver.insert(MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY), values)
                ?: error("the Gallery refused a new video")
            try {
                resolver.openOutputStream(uri)?.use { out -> file.inputStream().use { it.copyTo(out) } }
                    ?: error("the Gallery gave no place to write")
                resolver.update(uri, ContentValues().apply { put(MediaStore.Video.Media.IS_PENDING, 0) }, null, null)
            } catch (t: Throwable) {
                runCatching { resolver.delete(uri, null, null) }
                throw t
            }
        } else {
            // Before Android 10 the shared Movies folder needs a storage permission this app
            // does not ask for: kept in the app's own Movies folder instead.
            val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_MOVIES), ALBUM).apply { mkdirs() }
            file.copyTo(File(dir, name), overwrite = true)
        }
        return name
    }

    // ── The encoder ──────────────────────────────────────────────────────────────

    /**
     * H.264 at [W]x[H], [FPS] a second, fed bitmaps through the codec's YUV input buffers so
     * every frame carries its exact time — a drawn video goes as fast as the phone can draw
     * it, and a timestamp from the wall clock would play it back at that speed.
     */
    private inner class Encoder(out: File) {
        private val codec: MediaCodec
        private val muxer = MediaMuxer(out.absolutePath, MediaMuxer.OutputFormat.MUXER_OUTPUT_MPEG_4)
        private var track = -1
        private var frames = 0
        private val info = MediaCodec.BufferInfo()
        private var rgba = ByteArray(0)
        private val yRow = ByteArray(W)

        init {
            val format = MediaFormat.createVideoFormat(MediaFormat.MIMETYPE_VIDEO_AVC, W, H).apply {
                setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatYUV420Flexible)
                setInteger(MediaFormat.KEY_BIT_RATE, BITRATE)
                setInteger(MediaFormat.KEY_FRAME_RATE, FPS)
                setInteger(MediaFormat.KEY_I_FRAME_INTERVAL, 1)
            }
            codec = MediaCodec.createEncoderByType(MediaFormat.MIMETYPE_VIDEO_AVC)
            codec.configure(format, null, null, MediaCodec.CONFIGURE_FLAG_ENCODE)
            codec.start()
        }

        /** One frame, RGBA [pixelStride] bytes apart and [rowStride] bytes a row, from [pixels]. */
        fun add(pixels: java.nio.ByteBuffer, rowStride: Int, pixelStride: Int) {
            val need = minOf(pixels.remaining(), rowStride * H)
            if (rgba.size < need) rgba = ByteArray(need)
            pixels.get(rgba, 0, need)
            var index: Int
            while (true) {
                index = codec.dequeueInputBuffer(10_000)
                if (index >= 0) break
                drain(false)
            }
            val image = codec.getInputImage(index) ?: error("the encoder gave no input picture")
            val (yp, up, vp) = image.planes
            val y = yp.buffer
            val u = up.buffer
            val v = vp.buffer
            val px = rgba
            for (row in 0 until H) {
                val base = row * rowStride
                for (col in 0 until W) {
                    val i = base + col * pixelStride
                    val r = px[i].toInt() and 0xFF
                    val g = px[i + 1].toInt() and 0xFF
                    val b = px[i + 2].toInt() and 0xFF
                    yRow[col] = (((66 * r + 129 * g + 25 * b + 128) shr 8) + 16).toByte()
                }
                if (yp.pixelStride == 1) {
                    y.position(row * yp.rowStride)
                    y.put(yRow, 0, W)
                } else {
                    for (col in 0 until W) y.put(row * yp.rowStride + col * yp.pixelStride, yRow[col])
                }
                if (row % 2 == 0) {
                    val cRow = row / 2
                    var col = 0
                    while (col < W) {
                        val i = base + col * pixelStride
                        val r = px[i].toInt() and 0xFF
                        val g = px[i + 1].toInt() and 0xFF
                        val b = px[i + 2].toInt() and 0xFF
                        val cCol = col / 2
                        u.put(cRow * up.rowStride + cCol * up.pixelStride, (((-38 * r - 74 * g + 112 * b + 128) shr 8) + 128).toByte())
                        v.put(cRow * vp.rowStride + cCol * vp.pixelStride, (((112 * r - 94 * g - 18 * b + 128) shr 8) + 128).toByte())
                        col += 2
                    }
                }
            }
            codec.queueInputBuffer(index, 0, W * H * 3 / 2, frames * 1_000_000L / FPS, 0)
            frames++
            drain(false)
        }

        private fun drain(end: Boolean) {
            var idle = 0
            while (true) {
                val i = codec.dequeueOutputBuffer(info, if (end) 10_000 else 0)
                when {
                    i == MediaCodec.INFO_TRY_AGAIN_LATER -> {
                        if (!end || ++idle > 300) return
                    }
                    i == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED -> {
                        track = muxer.addTrack(codec.outputFormat)
                        muxer.start()
                    }
                    i >= 0 -> {
                        val buf = codec.getOutputBuffer(i)
                        if (info.flags and MediaCodec.BUFFER_FLAG_CODEC_CONFIG != 0) info.size = 0
                        if (buf != null && info.size > 0 && track >= 0) {
                            buf.position(info.offset)
                            buf.limit(info.offset + info.size)
                            muxer.writeSampleData(track, buf, info)
                        }
                        codec.releaseOutputBuffer(i, false)
                        if (info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM != 0) return
                    }
                }
            }
        }

        /** Ends the stream and closes the file. Returns the frames written. */
        fun finish(): Int {
            var index: Int
            while (true) {
                index = codec.dequeueInputBuffer(10_000)
                if (index >= 0) break
                drain(false)
            }
            codec.queueInputBuffer(index, 0, 0, frames * 1_000_000L / FPS, MediaCodec.BUFFER_FLAG_END_OF_STREAM)
            drain(true)
            codec.stop()
            codec.release()
            if (track >= 0) muxer.stop()
            muxer.release()
            if (frames == 0 || track < 0) error("nothing was drawn")
            return frames
        }

        fun abandon() {
            runCatching { codec.stop() }
            runCatching { codec.release() }
            runCatching { if (track >= 0) muxer.stop() }
            runCatching { muxer.release() }
        }
    }

    /** Thrown from inside the decode once the window is drawn: the rest of the clip is not needed. */
    private object DoneSignal : RuntimeException() {
        private fun readResolve(): Any = DoneSignal
        override fun fillInStackTrace(): Throwable = this
    }

    companion object {
        private const val TAG = "HaraanTrackingVideo"
        const val ALBUM = "Haraan Ball Tracking"
        private const val W = 720
        private const val H = 1280
        private const val DENSITY = 2f
        private const val FPS = 30
        private const val FRAME_MS = 1000.0 / FPS
        // 720p30 at 5 Mbps: a ten-second ball is about 6 MB.
        private const val BITRATE = 5_000_000
        /** Clip kept before the ball is first seen: the run-up and the release. */
        private const val LEAD_MS = 2_000.0
        /** And after it ends: the shot. */
        private const val TAIL_MS = 2_000.0
        private const val HOLD_MS = 500.0
        private const val PITCHED_MAX_M = 20.0
        private const val MIN_FREE_BYTES = 300L * 1024 * 1024

        @Volatile private var instance: TrackingVideoSaver? = null

        fun get(context: Context): TrackingVideoSaver =
            instance ?: synchronized(this) { instance ?: TrackingVideoSaver(context.applicationContext).also { instance = it } }
    }
}
