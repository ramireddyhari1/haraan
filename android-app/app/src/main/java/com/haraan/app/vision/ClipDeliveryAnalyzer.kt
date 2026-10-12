package com.haraan.app.vision

import android.content.Context
import android.graphics.Bitmap
import android.media.MediaMetadataRetriever
import android.net.Uri
import android.os.Build
import com.haraan.app.camera.battersEndOnly
import com.haraan.app.camera.bowlersEndOf
import com.haraan.app.camera.deliveryCorridor
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
class ClipProblem(message: String) : Exception(message)

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
        try {
            try {
                retriever.setDataSource(context, uri)
            } catch (t: Throwable) {
                throw ClipProblem("This video can't be opened. Try a clip from your camera.")
            }
            return run(retriever, progress)
        } finally {
            runCatching { retriever.release() }
        }
    }

    /** A file on disk, for the debug net replay. */
    suspend fun analyse(path: String, progress: Progress, fromFrame: Int = 0, toFrame: Int = Int.MAX_VALUE, every: Int = 0): ClipAnalysis {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) throw ClipProblem("Tracking a clip needs Android 9 or newer.")
        val retriever = MediaMetadataRetriever()
        try {
            try {
                retriever.setDataSource(path)
            } catch (t: Throwable) {
                throw ClipProblem("Can't open $path: ${t.message}")
            }
            return run(retriever, progress, fromFrame, toFrame, every)
        } finally {
            runCatching { retriever.release() }
        }
    }

    @android.annotation.SuppressLint("NewApi")
    private suspend fun run(
        retriever: MediaMetadataRetriever,
        progress: Progress,
        fromFrame: Int = 0,
        toFrame: Int = Int.MAX_VALUE,
        every: Int = 0,
    ): ClipAnalysis {
        val count = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_FRAME_COUNT)?.toIntOrNull() ?: 0
        val durationMs = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: 0L
        if (count <= 1 || durationMs <= 0) throw ClipProblem("This video has no frames we can read.")
        val vw = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toIntOrNull() ?: 1080
        val vh = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toIntOrNull() ?: 1920
        val rotation = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: 0
        val fps = count * 1000.0 / durationMs
        val first = fromFrame.coerceIn(0, count - 1)
        val last = minOf(toFrame, count - 1)
        val stride = if (every > 0) every else maxOf(1, Math.ceil((last - first + 1).toDouble() / MAX_ANALYSED_FRAMES).toInt())
        // 4K frames are 16 MB each even as RGB_565; decode only as many at once as fit.
        val frameBytes = vw.toLong() * vh * 2
        val batch = (BATCH_BUDGET_BYTES / frameBytes).toInt().coerceIn(1, MAX_BATCH)
        val params = MediaMetadataRetriever.BitmapParams().apply { preferredConfig = Bitmap.Config.RGB_565 }
        val aspect = ClipFrames.UPRIGHT_W.toFloat() / ClipFrames.UPRIGHT_H

        val stumps = ColourStumpDetector()
        val wickets = WicketTracker()
        val ball = OpenCvBallTracker()
        val camera = CameraIntrinsics(ASSUMED_FOCAL_FW, "assumed")
        val still = FrameMotion(0f, 0f, 1f, 0f, 30, 32)
        val y = ByteArray(ClipFrames.SENSOR_W * ClipFrames.SENSOR_H)
        val u = ByteArray(ClipFrames.SENSOR_W / 2 * (ClipFrames.SENSOR_H / 2))
        val pixels = IntArray(ClipFrames.UPRIGHT_W * ClipFrames.UPRIGHT_H)
        var lock: WicketLock? = null
        var bowlersEnd: BowlersEnd? = null
        val nearEnd = BowlersEndFilter()
        var corridor: FloatArray? = null
        val steadyLock = SteadyWicket()
        val recentFrames = ArrayDeque<Pair<Double, ByteArray>>()
        var lastStumpsMs = Long.MIN_VALUE / 2
        var lastCorridorMs = Long.MIN_VALUE / 2
        val deliveries = ArrayList<ClipDelivery>()
        var analysed = 0

        fun upright(raw: Bitmap): Bitmap {
            val up = ClipFrames.upright(raw, rotation, aspect)
            if (up !== raw) raw.recycle()
            return up
        }

        fun feedStumps(ts: Long) {
            val seen = stumps.detectCandidates(
                y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, u, ClipFrames.SENSOR_W / 2, 1,
                focus = wickets.focus(),
            )
            bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
            lock = wickets.onFrame(battersEndOnly(seen, camera, bowlersEnd), still, aspect, ts)
            steadyLock.add(lock, ts)
        }

        try {
            /*
             * WARM-UP, as a real setup has one: the stumps are watched over the opening seconds
             * first, 30 a second, then the clip is analysed from its start with that lock in
             * place. A clip cut mid run-up otherwise starts cold, with the bowler running
             * through the picture, and the lock still flickering at the release.
             */
            val warmStride = maxOf(1, Math.round(fps / 30.0).toInt())
            val warmEnd = minOf(last, first + WARM_UP_FRAMES * warmStride)
            var w = first
            while (w <= warmEnd) {
                coroutineContext.ensureActive()
                val n = minOf(batch, warmEnd - w + 1)
                for ((k, raw) in retriever.getFramesAtIndex(w, n, params).withIndex()) {
                    val index = w + k
                    if ((index - first) % warmStride != 0) {
                        raw.recycle()
                        continue
                    }
                    val up = upright(raw)
                    up.getPixels(pixels, 0, ClipFrames.UPRIGHT_W, 0, 0, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
                    up.recycle()
                    ClipFrames.toSensorYuv(pixels, y, u)
                    feedStumps((index * 1000.0 / fps).toLong())
                }
                w += n
                progress.update(WARM_UP_SHARE * (w - first).toFloat() / (warmEnd - first + 1).coerceAtLeast(1), 0, null)
            }

            var at = first
            while (at <= last) {
                coroutineContext.ensureActive()
                val n = minOf(batch * stride, last - at + 1)
                // Only the frames that will be analysed are decoded.
                val wanted = (at until at + n).filter { (it - first) % stride == 0 }
                val decoded: List<Pair<Int, Bitmap>> = if (stride == 1) {
                    retriever.getFramesAtIndex(at, n, params).withIndex().map { (k, b) -> (at + k) to b }
                } else {
                    wanted.mapNotNull { i -> retriever.getFrameAtIndex(i, params)?.let { i to it } }
                }
                for ((index, raw) in decoded) {
                    val up = upright(raw)
                    up.getPixels(pixels, 0, ClipFrames.UPRIGHT_W, 0, 0, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
                    ClipFrames.toSensorYuv(pixels, y, u)
                    val ts = (index * 1000.0 / fps).toLong()
                    // A small copy of each analysed frame, kept a few seconds back, so each
                    // delivery can be replayed frame by frame without the video decoder.
                    val thumb = jpegOf(up)
                    recentFrames.addLast(ts.toDouble() to thumb)
                    while (recentFrames.isNotEmpty() && recentFrames.first().first < ts - RECENT_FRAMES_MS) recentFrames.removeFirst()
                    for (d in deliveries) if (ts > d.calledMs && ts <= d.tailUntilMs) d.frames += ts.toDouble() to thumb

                    // Stumps every frame until confirmed, then ten a second — as the camera screen.
                    if (lock?.state != WicketTrackState.CONFIRMED || ts - lastStumpsMs >= STUMPS_EVERY_MS) {
                        lastStumpsMs = ts
                        feedStumps(ts)
                    }
                    // Re-derived until there is one, then once a second; built on the steadied
                    // stumps, which outlive the live lock the run-up knocks out.
                    if (corridor == null || ts - lastCorridorMs >= 1_000L) {
                        lastCorridorMs = ts
                        val steady = steadyLock.value()
                        val c = deliveryCorridor(steady ?: lock, camera, ClipFrames.UPRIGHT_W, aspect)
                        if (c != null) {
                            corridor = c
                            ball.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
                        } else if (lock == null && steady == null) {
                            corridor = null
                            ball.setCorridor(null, null, null, null, null)
                        }
                    }
                    ball.onFrame(y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, ts)
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
                    val share = WARM_UP_SHARE + (1f - WARM_UP_SHARE) * (index - first).toFloat() / (last - first + 1).coerceAtLeast(1)
                    if (analysed % PREVIEW_EVERY == 0) {
                        progress.update(share, deliveries.size, up)
                    } else {
                        up.recycle()
                    }
                }
                at += n
            }
            // A flight still in the air when the clip ends is called as it stands.
            val l = steadyLock.value() ?: lock
            val tail = ball.track()
            if (l != null && tail.size >= FlightEnd.MIN_FLIGHT_POINTS) {
                deliveryOf(tail, l, bowlersEnd, camera, aspect, tail.last().timestampMs)?.let { d ->
                    d.frames += recentFrames.filter { it.first >= d.startMs - REPLAY_LEAD_MS }
                    deliveries += d
                }
            }
        } finally {
            stumps.release()
            ball.release()
        }
        progress.update(1f, deliveries.size, null)
        return ClipAnalysis(
            deliveries = deliveries.filter { it.frames.size > 1 },
            fps = fps,
            durationMs = durationMs,
            stumpsFound = steadyLock.value() != null,
            farBase = (steadyLock.value() ?: lock)?.anchor?.base,
            nearBase = bowlersEnd?.base,
        )
    }

    /** A called delivery: fitted in 3D through the ground the stumps give, else nothing. */
    private fun deliveryOf(
        track: List<BallSighting>,
        lock: WicketLock,
        bowlersEnd: BowlersEnd?,
        camera: CameraIntrinsics,
        aspect: Float,
        calledMs: Long,
    ): ClipDelivery? {
        if (track.size < FlightEnd.MIN_FLIGHT_POINTS) return null
        val ground = PitchGround.best(lock, camera, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H, tilt = null, bowlersEnd = bowlersEnd)
            ?.pitchCamera(ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H) ?: return null
        val m = FlightMetrics.of(track, aspect, quad = null, wicket = lock, ground = ground)
        // Only a delivery the 3D fit accepts is shown: a track the fit refuses — something
        // crossing behind the net, a fielder — is not a ball bowled.
        val f3 = m.flight3d ?: return null
        val path = ArPath.fromFlight(f3, ground) ?: return null
        if (path.isEmpty) return null
        return ClipDelivery(calledMs.toDouble(), track.first().timestampMs.toDouble(), path, f3.speedKmh, f3)
    }

    private fun jpegOf(up: Bitmap): ByteArray {
        val small = Bitmap.createScaledBitmap(up, REPLAY_W, REPLAY_H, true)
        val out = ByteArrayOutputStream(32 * 1024)
        small.compress(Bitmap.CompressFormat.JPEG, 78, out)
        if (small !== up) small.recycle()
        return out.toByteArray()
    }

    const val REPLAY_LEAD_MS = 450.0
    const val REPLAY_TAIL_MS = 700.0
    const val REPLAY_W = 360
    const val REPLAY_H = 640
    private const val RECENT_FRAMES_MS = 4_000L
    private const val STUMPS_EVERY_MS = 100L
    private const val WARM_UP_FRAMES = 90
    private const val WARM_UP_SHARE = 0.12f
    private const val ASSUMED_FOCAL_FW = 1.4
    private const val MAX_BATCH = 16
    private const val BATCH_BUDGET_BYTES = 48L * 1024 * 1024
    private const val PREVIEW_EVERY = 6

    /** About half a minute at 120 fps; a longer clip is thinned towards 30 a second. */
    const val MAX_ANALYSED_FRAMES = 3_600
}
