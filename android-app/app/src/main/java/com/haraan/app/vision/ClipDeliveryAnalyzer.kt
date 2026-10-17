package com.haraan.app.vision

import android.content.Context
import android.graphics.Bitmap
import android.media.MediaMetadataRetriever
import android.net.Uri
import android.os.Build
import kotlinx.coroutines.ensureActive
import java.io.ByteArrayOutputStream
import kotlin.coroutines.coroutineContext

/**
 * One delivery found in a clip: the path to draw, the numbers, the 3D flight, and its own
 * frames (small upright JPEGs on the clip's clock) for the replay.
 */
class ClipDelivery(
    val calledMs: Double,
    val startMs: Double,
    val path: ArPath,
    val speedKmh: Double?,
    val flight: Flight3d?,
) {
    val frames = ArrayList<Pair<Double, ByteArray>>()
    internal var tailUntilMs = calledMs + ClipDeliveryAnalyzer.REPLAY_TAIL_MS

    /** Where it pitched, metres from the batter's stumps, or null when not fitted. */
    val bounceM: Double? get() = flight?.bounceY

    /** True hitting, false missing, null not judged. */
    val hitting: Boolean? get() = path.hitting
}

/** What a clip gave: its deliveries, and what was found of the wickets for setting-up advice. */
class ClipAnalysis(
    val deliveries: List<ClipDelivery>,
    val fps: Double,
    val durationMs: Long,
    /** The batter's stumps were found and held: without them nothing can be measured. */
    val stumpsFound: Boolean,
    /** Where the batter's and the bowler's stumps' feet were seen, for setting-up checks. */
    val farBase: Point2? = null,
    val nearBase: Point2? = null,
)

/** Why a clip could not be read at all, in words for the person who picked it. */
class ClipProblem(message: String, cause: Throwable? = null) : Exception(message, cause)

/**
 * A FILMED CLIP THROUGH THE SAME CHAIN AS THE LIVE CAMERA, every frame of it.
 *
 * Shared by the "Track a clip" screen any player can open and the debug net replay: colour
 * stump detection → the batter's end only → the wicket tracker and its steadied lock → the
 * delivery corridor → the ball tracker → each delivery called as the camera calls it, fitted
 * in 3D through the ground both sets of stumps give, and kept with its own frames.
 *
 * Built for clips straight off a phone, which the debug screen never had to handle: portrait
 * video stored sideways with a rotation tag, 4K frames (decoded a few at a time so a phone does
 * not run out of memory), and long clips (thinned to [MAX_ANALYSED_FRAMES] so the wait stays
 * reasonable — never below the clip's own rate for short clips, which is where the far half of
 * a delivery is seen).
 */
object ClipDeliveryAnalyzer {
    /** [found] deliveries so far, [preview] the frame just analysed (upright, may be null). */
    fun interface Progress {
        fun update(fraction: Float, found: Int, preview: Bitmap?)
    }

    suspend fun analyse(context: Context, uri: Uri, progress: Progress): ClipAnalysis {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) throw ClipProblem("Tracking a clip needs Android 9 or newer.")
        val retriever = MediaMetadataRetriever()
        val stream = runCatching { VideoFrameStream.open(context, uri) }
            .onFailure { android.util.Log.w(TAG, "streaming decoder unavailable, using the frame retriever", it) }
            .getOrNull()
        try {
            try {
                retriever.setDataSource(context, uri)
            } catch (t: Throwable) {
                if (stream == null) throw ClipProblem("This video can't be opened. Try a clip from your camera.", t)
            }
            return run(retriever, stream, progress)
        } finally {
            runCatching { retriever.release() }
            runCatching { stream?.close() }
        }
    }

    /** A file on disk, for the debug net replay. */
    suspend fun analyse(path: String, progress: Progress, fromFrame: Int = 0, toFrame: Int = Int.MAX_VALUE, every: Int = 0): ClipAnalysis {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) throw ClipProblem("Tracking a clip needs Android 9 or newer.")
        val retriever = MediaMetadataRetriever()
        val stream = runCatching { VideoFrameStream.open(path) }.getOrNull()
        try {
            try {
                retriever.setDataSource(path)
            } catch (t: Throwable) {
                if (stream == null) throw ClipProblem("Can't open $path: ${t.message}", t)
            }
            return run(retriever, stream, progress, fromFrame, toFrame, every)
        } finally {
            runCatching { retriever.release() }
            runCatching { stream?.close() }
        }
    }

    @android.annotation.SuppressLint("NewApi")
    private suspend fun run(
        retriever: MediaMetadataRetriever,
        stream: VideoFrameStream?,
        progress: Progress,
        fromFrame: Int = 0,
        toFrame: Int = Int.MAX_VALUE,
        every: Int = 0,
    ): ClipAnalysis {
        val meta = { key: Int -> runCatching { retriever.extractMetadata(key) }.getOrNull() }
        val count = meta(MediaMetadataRetriever.METADATA_KEY_VIDEO_FRAME_COUNT)?.toIntOrNull() ?: 0
        val durationMs = meta(MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: 0L
        if (count <= 1 || durationMs <= 0) throw ClipProblem("This video has no frames we can read.")
        val vw = meta(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toIntOrNull() ?: 1080
        val vh = meta(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toIntOrNull() ?: 1920
        val rotation = meta(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: stream?.rotation ?: 0
        /*
         * A clip whose frames come in layers is read by the frames' own times: an iPhone's
         * 120 fps clip opens with half a second at 60, and counting its frames over its length
         * (115.6 a second) put every frame of the flight 8.65 ms apart instead of 8.33 — every
         * speed 3.8% low.
         *
         * Such a clip is analysed at 60 frames a second, its two bottom layers: half the
         * decoding, and within 1% of the speed every frame gives (IMG_6056: 100.0 against
         * 101.0 km/h, the same bounce and the same hit) — 4.8 s on a Motorola Edge 70 Pro
         * against 6.4 s.
         */
        val layered = stream?.takeIf {
            count * 1000.0 / durationMs > LAYERED_ABOVE_FPS && it.layered && it.frameRate > 0
        }
        val fps = layered?.frameRate ?: (count * 1000.0 / durationMs)
        val frames = if (layered != null) maxOf(count, (durationMs * fps / 1000.0).toInt()) else count
        val first = fromFrame.coerceIn(0, frames - 1)
        val last = minOf(toFrame, frames - 1)
        val stride = if (every > 0) every else maxOf(1, Math.ceil((last - first + 1).toDouble() / MAX_ANALYSED_FRAMES).toInt())
        // 4K frames are 16 MB each even as RGB_565; decode only as many at once as fit.
        val frameBytes = vw.toLong() * vh * 2
        val batch = (BATCH_BUDGET_BYTES / frameBytes).toInt().coerceIn(1, MAX_BATCH)
        val params = MediaMetadataRetriever.BitmapParams().apply { preferredConfig = Bitmap.Config.RGB_565 }
        val aspect = ClipFrames.UPRIGHT_W.toFloat() / ClipFrames.UPRIGHT_H

        val y = ByteArray(ClipFrames.SENSOR_W * ClipFrames.SENSOR_H)
        val u = ByteArray(ClipFrames.SENSOR_W / 2 * (ClipFrames.SENSOR_H / 2))
        val pixels = IntArray(ClipFrames.UPRIGHT_W * ClipFrames.UPRIGHT_H)
        val recentFrames = ArrayDeque<Pair<Double, ByteArray>>()
        val deliveries = ArrayList<ClipDelivery>()
        var analysed = 0
        val tStart = System.nanoTime()
        var tYuv = 0L
        var tJpeg = 0L
        var tWarm = 0L

        fun upright(raw: Bitmap): Bitmap {
            val up = ClipFrames.upright(raw, rotation, aspect)
            if (up !== raw) raw.recycle()
            return up
        }

        /*
         * EVERY FRAME [from]..[to], [step] apart, into [y] and [u] — the camera's sideways
         * analysis frame — with its small upright picture to be had from [Frame.thumb].
         *
         * Through the phone's video decoder when it opens the file — any codec, 4K, 10-bit HDR
         * — and through the frame retriever otherwise, or when the decoder fails before it has
         * given a single frame (some phones' decoders refuse what their own gallery plays).
         */
        var streaming = stream != null
        val job = coroutineContext[kotlinx.coroutines.Job]
        // The far stumps measured in full-size frames on the decoding thread, where the chain's
        // steady lock says they stand. See FastFrameSampler.measureFarStumps.
        val farSpans = java.util.concurrent.ConcurrentLinkedQueue<Double>()
        val farMeter = FastFrameSampler()
        val chain = DeliveryChain(farSpans = { farSpans }, log = { line -> runCatching { android.util.Log.i(TAG, line) } })
        val pictures = java.util.concurrent.Executors.newFixedThreadPool(PICTURE_THREADS)
        fun measureFar(stream: VideoFrameStream, index: Int, image: android.media.Image) {
            val at = chain.farStumpsAt ?: return
            if (farSpans.size >= FAR_SPAN_SAMPLES || index % FAR_SPAN_EVERY != 0) return
            runCatching { farMeter.measureFarStumps(image, stream.rotation, at[0], at[1], at[2]) }
                .getOrNull()?.let { farSpans.add(it) }
        }
        // Native sampling straight off the decoder; the Kotlin sampler if it ever fails.
        val fast = FastFrameSampler()
        var fastOk = !forceSlowSampling
        fun thumbFromPixels(): Bitmap {
            val up = Bitmap.createBitmap(pixels, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H, Bitmap.Config.ARGB_8888)
            return Bitmap.createScaledBitmap(up, REPLAY_W, REPLAY_H, true).also { if (it !== up) up.recycle() }
        }
        /*
         * DECODE ON ONE THREAD, ANALYSE ON ANOTHER.
         *
         * Decoding and shrinking a 4K frame and analysing it took turns, so the wait was the
         * sum of the two. A thread of its own now decodes ahead into a few buffers while this
         * one analyses the frame before; the wait becomes the slower of the two.
         */
        fun pipelined(
            stream: VideoFrameStream,
            from: Int,
            to: Int,
            step: Int,
            needThumb: (Int) -> Boolean,
            layers: VideoFrameStream.Layers?,
            block: (index: Int, thumb: Bitmap?, jpeg: ByteArray?) -> Unit,
        ) {
            class Slot(val y: ByteArray, val u: ByteArray) {
                var index = 0
                var picture: java.util.concurrent.Future<Pair<Bitmap, ByteArray>>? = null
            }
            val free = java.util.concurrent.ArrayBlockingQueue<Slot>(PIPELINE_DEPTH)
            repeat(PIPELINE_DEPTH) { free.add(Slot(ByteArray(y.size), ByteArray(u.size))) }
            val ready = java.util.concurrent.ArrayBlockingQueue<Any>(PIPELINE_DEPTH + 1)
            val done = Any()
            val stop = java.util.concurrent.atomic.AtomicBoolean(false)
            val worker = Thread({
                try {
                    stream.decode(from, to, step, layers) { index, _, image ->
                        if (stop.get()) throw kotlinx.coroutines.CancellationException()
                        val slot = free.take()
                        fast.sensorYuv(image, stream.rotation, slot.y, slot.u)
                        measureFar(stream, index, image)
                        slot.index = index
                        // The replay picture: shrunk here, finished and compressed off this
                        // thread, which is the one the whole wait hangs on.
                        slot.picture = if (needThumb(index)) {
                            val parts = fast.thumbnailParts(REPLAY_W, REPLAY_H)
                            pictures.submit<Pair<Bitmap, ByteArray>> { parts.toBitmap().let { it to jpegOf(it) } }
                        } else {
                            null
                        }
                        ready.put(slot)
                    }
                    ready.put(done)
                } catch (t: Throwable) {
                    if (!stop.get()) runCatching { ready.put(t) }
                }
            }, "clip-decode")
            worker.start()
            try {
                while (true) {
                    if (job?.isActive == false) throw kotlinx.coroutines.CancellationException()
                    when (val m = ready.take()) {
                        done -> return
                        is Throwable -> throw m
                        is Slot -> {
                            System.arraycopy(m.y, 0, y, 0, y.size)
                            System.arraycopy(m.u, 0, u, 0, u.size)
                            val picture = m.picture?.get()
                            m.picture = null
                            free.put(m)
                            block(m.index, picture?.first, picture?.second)
                        }
                    }
                }
            } finally {
                stop.set(true)
                // Let a blocked worker finish: give it room, then wait for it.
                while (worker.isAlive) {
                    ready.clear()
                    repeat(PIPELINE_DEPTH) { free.offer(Slot(ByteArray(0), ByteArray(0))) }
                    worker.join(50)
                }
            }
        }

        fun eachFrame(
            from: Int,
            to: Int,
            step: Int,
            needThumb: (index: Int) -> Boolean = { false },
            layers: VideoFrameStream.Layers? = null,
            block: (index: Int, thumb: () -> Bitmap?, jpeg: ByteArray?) -> Unit,
        ) {
            if (streaming && stream != null) {
                var delivered = 0
                try {
                    if (fastOk) {
                        pipelined(stream, from, to, step, needThumb, layers) { index, thumb, jpeg ->
                            delivered++
                            block(index, { thumb }, jpeg)
                        }
                    } else {
                        stream.decode(from, to, step, layers) { index, _, image ->
                            if (job?.isActive == false) throw kotlinx.coroutines.CancellationException()
                            delivered++
                            measureFar(stream, index, image)
                            stream.upright(image, pixels)
                            ClipFrames.toSensorYuv(pixels, y, u)
                            block(index, { thumbFromPixels() }, null)
                        }
                    }
                    return
                } catch (c: kotlinx.coroutines.CancellationException) {
                    throw c
                } catch (t: Throwable) {
                    if (delivered > 0) throw if (t is ClipProblem) t else ClipProblem("The video stopped decoding part way through.", t)
                    android.util.Log.w(TAG, "streaming decode failed, falling back to the frame retriever", t)
                    streaming = false
                }
            }
            var at = from
            while (at <= to) {
                if (job?.isActive == false) throw kotlinx.coroutines.CancellationException()
                val n = minOf(batch * step, to - at + 1)
                val decoded: List<Pair<Int, Bitmap>> = try {
                    if (step == 1) {
                        retriever.getFramesAtIndex(at, n, params).withIndex().map { (k, b) -> (at + k) to b }
                    } else {
                        (at until at + n).filter { (it - from) % step == 0 }
                            .mapNotNull { i -> retriever.getFrameAtIndex(i, params)?.let { i to it } }
                    }
                } catch (t: Throwable) {
                    throw ClipProblem("This phone can't read frames from this video.", t)
                }
                for ((index, raw) in decoded) {
                    val up = upright(raw)
                    up.getPixels(pixels, 0, ClipFrames.UPRIGHT_W, 0, 0, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
                    up.recycle()
                    ClipFrames.toSensorYuv(pixels, y, u)
                    block(index, { thumbFromPixels() }, null)
                }
                at += n
            }
        }
        // The replay plays at normal speed: sixty pictures a second is all it can show.
        val thumbEvery = maxOf(1, Math.round(fps / REPLAY_FPS).toInt())

        // How far along the wait is, for the progress bar.
        val shareOf: (Int) -> Float = { index ->
            WARM_UP_SHARE + (1f - WARM_UP_SHARE) * (index - first).toFloat() / (last - first + 1).coerceAtLeast(1)
        }

        /** The full analysis of frames [from]..[to]: stumps, ball, deliveries, replay pictures. */
        fun analyseRange(from: Int, to: Int, layers: VideoFrameStream.Layers?) {
            eachFrame(
                from, to, stride,
                needThumb = { i -> (i - first) % thumbEvery == 0 || ((i - first) / stride + 1) % PREVIEW_EVERY == 0 },
                layers = layers,
            ) { index, thumbOf, madeJpeg ->
                    var t0: Long
                    val ts = (index * 1000.0 / fps).toLong()
                    val wantPreview = ((index - first) / stride + 1) % PREVIEW_EVERY == 0
                    val wantThumb = (index - first) % thumbEvery == 0
                    val up: Bitmap? = if (wantThumb || wantPreview) {
                        t0 = System.nanoTime()
                        thumbOf().also { tYuv += System.nanoTime() - t0 }
                    } else {
                        null
                    }
                    // A small copy of the analysed frames, kept a few seconds back, so each
                    // delivery can be replayed frame by frame without the video decoder.
                    if (wantThumb && up != null) {
                        t0 = System.nanoTime()
                        val thumb = madeJpeg ?: jpegOf(up)
                        tJpeg += System.nanoTime() - t0
                        recentFrames.addLast(ts.toDouble() to thumb)
                        while (recentFrames.isNotEmpty() && recentFrames.first().first < ts - RECENT_FRAMES_MS) recentFrames.removeFirst()
                        for (d in deliveries) if (ts > d.calledMs && ts <= d.tailUntilMs) d.frames += ts.toDouble() to thumb
                    }

                    for (called in chain.frame(y, u, ts)) {
                        deliveryOf(called)?.let { d ->
                            d.frames += recentFrames.filter { it.first >= d.startMs - REPLAY_LEAD_MS }
                            deliveries += d
                        }
                    }
                    analysed++
                    val share = shareOf(index)
                    if (wantPreview && up != null) {
                        progress.update(share, deliveries.size, up)
                    } else {
                        up?.recycle()
                    }
            }
            // A flight still in the air when the clip ends is called as it stands.
            chain.finish()?.let(::deliveryOf)?.let { d ->
                d.frames += recentFrames.filter { it.first >= d.startMs - REPLAY_LEAD_MS }
                deliveries += d
            }
        }

        try {
            /*
             * WARM-UP, as a real setup has one: the stumps are watched over the opening second
             * first, 30 a second, then the clip is analysed from its start with that lock in
             * place. A clip cut mid run-up otherwise starts cold, with the bowler running
             * through the picture, and the lock still flickering at the release.
             */
            val warmStride = maxOf(1, Math.round(fps / 30.0).toInt())
            val warmEnd = minOf(last, first + WARM_UP_FRAMES * warmStride)
            val warm0 = System.nanoTime()
            // Every fourth frame of a 120 fps clip is its bottom layer: that layer alone is
            // decoded, and every frame of it taken.
            val warmLayers = layered?.takeIf { warmStride >= 4 }?.let { VideoFrameStream.Layers(fps, Int.MAX_VALUE, 0) }
            eachFrame(first, warmEnd, if (warmLayers != null) 1 else warmStride, layers = warmLayers) { index, _, _ ->
                chain.warm(y, u, (index * 1000.0 / fps).toLong())
                progress.update(WARM_UP_SHARE * (index - first + 1).toFloat() / (warmEnd - first + 1).coerceAtLeast(1), 0, null)
            }
            tWarm = System.nanoTime() - warm0
            analyseRange(first, last, layered?.let { VideoFrameStream.Layers(fps, first, debugMaxLayer ?: LAYERED_MAX_LAYER) })
        } finally {
            chain.release()
            fast.release()
            farMeter.release()
            pictures.shutdownNow()
        }
        runCatching {
            val ms = { ns: Long -> ns / 1_000_000 }
            android.util.Log.i(
                TAG,
                "timing total ${ms(System.nanoTime() - tStart)}ms frames=$analysed streaming=$streaming " +
                    "decodeWait=${stream?.let { ms(it.waitNs) }} sample=${stream?.let { ms(it.sampleNs) }} " +
                    "stumps=${ms(chain.stumpsNs)} ball=${ms(chain.ballNs)} warm=${ms(tWarm)}",
            )
        }
        progress.update(1f, deliveries.size, null)
        return ClipAnalysis(
            deliveries = deliveries.filter { it.frames.size > 1 },
            fps = fps,
            durationMs = durationMs,
            stumpsFound = chain.stumpsFound,
            farBase = chain.measuringLock?.anchor?.base,
            nearBase = chain.bowlersEnd?.base,
        )
    }

    /** Only a delivery the 3D fit accepts is shown: a track the fit refuses — something
     *  crossing behind the net, a fielder — is not a ball bowled. */
    private fun deliveryOf(called: CalledDelivery): ClipDelivery? {
        val f3 = called.flight ?: return null
        val path = called.path ?: return null
        return ClipDelivery(called.calledMs.toDouble(), called.track.first().timestampMs.toDouble(), path, f3.speedKmh, f3)
    }

    private fun jpegOf(up: Bitmap): ByteArray {
        val small = Bitmap.createScaledBitmap(up, REPLAY_W, REPLAY_H, true)
        val out = ByteArrayOutputStream(32 * 1024)
        small.compress(Bitmap.CompressFormat.JPEG, 78, out)
        if (small !== up) small.recycle()
        return out.toByteArray()
    }

    private const val TAG = "ClipDeliveryAnalyzer"

    /** Debug only: sample frames the slow Kotlin way, to compare against the native path. */
    @Volatile
    var forceSlowSampling = false

    /** Debug only: the highest temporal layer analysed in a layered clip, in place of [LAYERED_MAX_LAYER]. */
    @Volatile
    var debugMaxLayer: Int? = null

    const val REPLAY_LEAD_MS = 450.0
    const val REPLAY_TAIL_MS = 700.0
    const val REPLAY_W = 360
    const val REPLAY_H = 640
    private const val RECENT_FRAMES_MS = 4_000L
    private const val WARM_UP_FRAMES = 30
    private const val WARM_UP_SHARE = 0.12f
    private const val MAX_BATCH = 16
    private const val BATCH_BUDGET_BYTES = 48L * 1024 * 1024
    private const val PREVIEW_EVERY = 6
    /** Replay pictures kept per second of clip: the replay plays at normal speed. */
    private const val REPLAY_FPS = 60.0
    /** Full-size measurements of the far stumps: at most this many, this many frames apart. */
    private const val FAR_SPAN_SAMPLES = 15
    private const val FAR_SPAN_EVERY = 8

    /** Clips above this rate are read by layer, when their frames come in layers. */
    private const val LAYERED_ABOVE_FPS = 50.0
    /** The top temporal layer analysed in a layered clip: 1 is 60 a second on a 120 fps iPhone clip. */
    private const val LAYERED_MAX_LAYER = 1

    /** Frames decoded ahead of the analysis: room for the replay pictures to finish meanwhile. */
    private const val PIPELINE_DEPTH = 8
    private const val PICTURE_THREADS = 2

    /** About half a minute at 120 fps; a longer clip is thinned towards 30 a second. */
    const val MAX_ANALYSED_FRAMES = 3_600
}
