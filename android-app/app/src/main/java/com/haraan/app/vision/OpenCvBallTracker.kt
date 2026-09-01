package com.haraan.app.vision

import android.util.Log
import org.opencv.android.OpenCVLoader
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.MatOfPoint
import org.opencv.core.Point
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Ball tracking by classical computer vision, running while the delivery is filmed.
 *
 * THE PREMISE. Between two frames thirty milliseconds apart, almost nothing on a cricket
 * field changes except the ball. Bowler and batter move slowly and largely; the ball moves
 * fast and small. So the ball is the brightest SMALL disturbance in a frame difference,
 * and that is what this looks for — no training data, no model, no assumption about the
 * ball being red or white, because motion is colour-blind.
 *
 * THE PIPELINE, deliberately conservative at every step:
 *
 *     luma → downscale → blur → frame difference → threshold → morphology
 *          → contours → size filter → shape filter → trajectory filter → candidate
 *
 * WHAT IT CANNOT DO. It does not know what a cricket ball is. It knows what "a small round
 * thing that moved" is, which a bird, a glove, or a flapping sightscreen also satisfies.
 * Every filter below exists to discard a frame rather than emit a point it cannot stand
 * behind, because a missing point only shortens the track while a wrong point draws a
 * confident line through somewhere the ball never was.
 *
 * Nothing here interpolates and nothing smooths. Every coordinate returned was measured.
 */
class OpenCvBallTracker(
    /**
     * Analysis width. The camera records 1080p; this does not analyse it.
     *
     * A cricket ball at 480px wide is still several pixels across — enough to find, cheap
     * enough to process inside a frame interval on a mid-range phone. Recording quality is
     * untouched: this is a separate, smaller stream.
     */
    private val analysisWidth: Int = 480,
) : CricketVisionEngine {

    private var previous: Mat? = null
    private var scratchLuma: Mat? = null

    private val sightings = mutableListOf<BallSighting>()
    private var framesSeen = 0
    private var framesWithCandidate = 0
    private var rejectedGlobalMotion = 0
    private var rejectedSize = 0
    private var rejectedShape = 0
    private var rejectedTrajectory = 0
    private var totalProcessingMs = 0L
    private var maxProcessingMs = 0L
    private var released = false

    val available: Boolean = ensureLoaded()

    override fun onFrame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        timestampMs: Long,
    ): BallSighting? {
        if (!available || released || width <= 0 || height <= 0) return null

        val startedAt = System.currentTimeMillis()
        try {
            val gray = toGray(luma, width, height, rowStride) ?: return null
            framesSeen++

            val prev = previous
            if (prev == null) {
                previous = gray
                return null
            }

            val sighting = detect(prev, gray, timestampMs)
            prev.release()
            previous = gray

            if (sighting != null) {
                framesWithCandidate++
                sightings.add(sighting)
            }
            return sighting
        } catch (t: Throwable) {
            // A vision failure must never take the recording down with it. The camera is
            // the product; this is an analysis layer bolted to the side of it.
            Log.w(TAG, "frame analysis failed", t)
            return null
        } finally {
            val elapsed = System.currentTimeMillis() - startedAt
            totalProcessingMs += elapsed
            if (elapsed > maxProcessingMs) maxProcessingMs = elapsed
        }
    }

    /**
     * Luma bytes into a downscaled, blurred greyscale Mat.
     *
     * Row stride is honoured rather than assumed equal to width: on many devices the
     * camera pads each row, and ignoring that shears the image diagonally — which then
     * looks exactly like fast horizontal motion and produces a beautiful false track.
     */
    private fun toGray(luma: ByteArray, width: Int, height: Int, rowStride: Int): Mat? {
        val packed = if (rowStride == width) {
            luma
        } else {
            ByteArray(width * height).also { out ->
                for (row in 0 until height) {
                    val from = row * rowStride
                    if (from + width > luma.size) return@also
                    System.arraycopy(luma, from, out, row * width, width)
                }
            }
        }

        val full = scratchLuma ?: Mat(height, width, CvType.CV_8UC1).also { scratchLuma = it }
        if (full.rows() != height || full.cols() != width) {
            full.release()
            scratchLuma = Mat(height, width, CvType.CV_8UC1)
        }
        scratchLuma!!.put(0, 0, packed)

        val scale = analysisWidth.toDouble() / width
        if (scale >= 1.0) {
            // Already small enough; blur in place on a copy.
            val out = Mat()
            Imgproc.GaussianBlur(scratchLuma!!, out, Size(5.0, 5.0), 0.0)
            return out
        }

        val small = Mat()
        Imgproc.resize(
            scratchLuma!!,
            small,
            Size(analysisWidth.toDouble(), (height * scale)),
            0.0,
            0.0,
            Imgproc.INTER_AREA,
        )
        val blurred = Mat()
        // Blur before differencing: sensor noise is high-frequency and would otherwise
        // survive the threshold as dozens of one-pixel "candidates".
        Imgproc.GaussianBlur(small, blurred, Size(5.0, 5.0), 0.0)
        small.release()
        return blurred
    }

    /** The frame difference, and the best ball-shaped thing in it. */
    private fun detect(prev: Mat, cur: Mat, timestampMs: Long): BallSighting? {
        if (prev.size() != cur.size()) return null

        val diff = Mat()
        Core.absdiff(prev, cur, diff)

        val mask = Mat()
        // Otsu picks the threshold from this frame's own histogram, so the same code holds
        // in flat evening light and harsh midday sun. A fixed number does not travel.
        Imgproc.threshold(diff, mask, 0.0, 255.0, Imgproc.THRESH_BINARY + Imgproc.THRESH_OTSU)
        diff.release()

        val totalPx = mask.rows() * mask.cols()
        val movingPx = Core.countNonZero(mask)
        if (movingPx > totalPx * GLOBAL_MOTION_LIMIT) {
            // Most of the frame moved: the camera panned or shook. Tracking that yields a
            // smooth, convincing, entirely fictional path.
            mask.release()
            rejectedGlobalMotion++
            return null
        }

        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_ELLIPSE, Size(3.0, 3.0))
        Imgproc.morphologyEx(mask, mask, Imgproc.MORPH_OPEN, kernel)
        kernel.release()

        val contours = ArrayList<MatOfPoint>()
        Imgproc.findContours(mask, contours, Mat(), Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        mask.release()
        if (contours.isEmpty()) return null

        var best: BallSighting? = null
        var bestScore = 0f

        for (contour in contours) {
            val area = Imgproc.contourArea(contour)
            if (area < MIN_AREA_PX || area > MAX_AREA_PX) {
                rejectedSize++
                contour.release()
                continue
            }

            // Circularity: 4*pi*area / perimeter^2 is 1.0 for a perfect circle. A ball is
            // round; an arm, a bat and a shadow edge are not. This is the single most
            // useful filter for keeping people out of the track.
            val perimeter = Imgproc.arcLength(org.opencv.core.MatOfPoint2f(*contour.toArray()), true)
            if (perimeter <= 0.0) {
                contour.release()
                continue
            }
            val circularity = (4.0 * PI * area) / (perimeter * perimeter)
            if (circularity < MIN_CIRCULARITY) {
                rejectedShape++
                contour.release()
                continue
            }

            val moments = Imgproc.moments(contour)
            if (moments.m00 == 0.0) {
                contour.release()
                continue
            }
            val centre = Point(moments.m10 / moments.m00, moments.m01 / moments.m00)
            val x = (centre.x / cur.cols()).toFloat().coerceIn(0f, 1f)
            val y = (centre.y / cur.rows()).toFloat().coerceIn(0f, 1f)

            // Roundness and continuity, combined. A candidate that continues the existing
            // track outranks a rounder one that teleports.
            val continuity = continuityScore(x, y, timestampMs)
            val score = (circularity.toFloat() * 0.6f) + (continuity * 0.4f)

            if (score > bestScore) {
                bestScore = score
                best = BallSighting(
                    timestampMs = timestampMs,
                    x = x,
                    y = y,
                    trackingConfidence = score.coerceIn(0f, 1f),
                    areaPx = area.toInt(),
                )
            }
            contour.release()
        }

        // A candidate that cannot be the same object as the last one is not this ball.
        if (best != null && continuityScore(best.x, best.y, timestampMs) <= 0f) {
            rejectedTrajectory++
            return null
        }
        return best
    }

    /**
     * How well a candidate continues the track.
     *
     * 1.0 with no history — the first sighting cannot contradict anything. Otherwise it
     * falls off with distance from where the ball plausibly is by now, and hits zero for a
     * jump no ball could make between adjacent frames.
     */
    private fun continuityScore(x: Float, y: Float, timestampMs: Long): Float {
        val last = sightings.lastOrNull() ?: return 1f
        val gapMs = (timestampMs - last.timestampMs).coerceAtLeast(1)
        if (gapMs > TRACK_GAP_LIMIT_MS) return 1f // A new flight, not a continuation.

        val dx = x - last.x
        val dy = y - last.y
        val distance = sqrt((dx * dx + dy * dy).toDouble()).toFloat()
        val allowed = MAX_STEP_PER_FRAME * (gapMs / 33f).coerceAtLeast(1f)

        return if (distance > allowed) 0f else (1f - (distance / allowed)).coerceIn(0f, 1f)
    }

    override fun track(): List<BallSighting> = sightings.toList()

    override fun quality(): TrackQuality {
        if (sightings.size < MIN_POINTS_FOR_TRACK) return TrackQuality.UNCERTAIN

        var jumps = 0
        for (i in 1 until sightings.size) {
            val a = sightings[i - 1]
            val b = sightings[i]
            val gapMs = (b.timestampMs - a.timestampMs).coerceAtLeast(1)
            if (gapMs > TRACK_GAP_LIMIT_MS) continue
            val dx = b.x - a.x
            val dy = b.y - a.y
            if (sqrt((dx * dx + dy * dy).toDouble()) > MAX_STEP_PER_FRAME) jumps++
        }
        if (jumps > sightings.size / 3) return TrackQuality.UNCERTAIN

        val mean = sightings.map { it.trackingConfidence }.average()
        return if (mean >= 0.5 && sightings.size >= 6) TrackQuality.RELIABLE else TrackQuality.PARTIAL
    }

    override fun diagnostics() = VisionDiagnostics(
        framesSeen = framesSeen,
        framesWithCandidate = framesWithCandidate,
        rejectedGlobalMotion = rejectedGlobalMotion,
        rejectedSize = rejectedSize,
        rejectedShape = rejectedShape,
        rejectedTrajectory = rejectedTrajectory,
        averageProcessingMs = if (framesSeen == 0) 0.0 else totalProcessingMs.toDouble() / framesSeen,
        maxProcessingMs = maxProcessingMs,
    )

    override fun reset() {
        previous?.release()
        previous = null
        sightings.clear()
        framesSeen = 0
        framesWithCandidate = 0
        rejectedGlobalMotion = 0
        rejectedSize = 0
        rejectedShape = 0
        rejectedTrajectory = 0
        totalProcessingMs = 0
        maxProcessingMs = 0
    }

    override fun release() {
        released = true
        previous?.release()
        previous = null
        scratchLuma?.release()
        scratchLuma = null
    }

    companion object {
        private const val TAG = "OpenCvBallTracker"

        /**
         * Loaded once per process. When this fails — an ABI without the native library,
         * a stripped APK — the engine reports unavailable and the app records exactly as
         * it did before. Vision is an enhancement, never a prerequisite for filming.
         */
        @Volatile
        private var loaded: Boolean? = null

        fun ensureLoaded(): Boolean {
            loaded?.let { return it }
            synchronized(this) {
                loaded?.let { return it }
                val ok = runCatching { OpenCVLoader.initLocal() }.getOrDefault(false)
                if (!ok) Log.w(TAG, "OpenCV native library unavailable; vision disabled")
                loaded = ok
                return ok
            }
        }

        /** Below this a candidate is sensor noise; above it, it is a person or a shadow. */
        const val MIN_AREA_PX = 4.0
        const val MAX_AREA_PX = 400.0

        /** 1.0 is a perfect circle. Motion blur stretches a ball, so this cannot be strict. */
        const val MIN_CIRCULARITY = 0.45

        /** Above this share of moving pixels, the camera moved rather than the subject. */
        const val GLOBAL_MOTION_LIMIT = 0.12

        /** Fraction of the frame a ball can cross between adjacent frames. */
        const val MAX_STEP_PER_FRAME = 0.30f

        /** Longer than this and the next sighting is a new flight, not a continuation. */
        const val TRACK_GAP_LIMIT_MS = 400L

        const val MIN_POINTS_FOR_TRACK = 3
    }
}
