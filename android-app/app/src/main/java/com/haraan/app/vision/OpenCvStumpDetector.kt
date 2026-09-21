package com.haraan.app.vision

import android.util.Log
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.MatOfPoint
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc

/** Where the stump search got to, so a null can be explained rather than shrugged at. */
data class StumpDetectorReport(
    val framesSeen: Int,
    /** Tall thin bright boxes this frame offered, before the wicket test. */
    val barsFound: Int,
    val setsFound: Int,
    val averageProcessingMs: Double,
    val lastRejection: String?,
    /**
     * Where the ground was judged to start, normalised, or null when the frame could not
     * say. Null means no ground constraint was applied at all, which is worth knowing
     * before reading anything into a detection.
     */
    val horizonY: Float? = null,
    /** Bars thrown out for standing above the horizon. */
    val barsAboveHorizon: Int = 0,
)

/**
 * Finds the wicket.
 *
 * WHY, when a crease detector already exists. Because the crease detector keeps failing on
 * the half of its job that depends on faint paint. On real club footage it now finds seven
 * crease-angle segments and then dies on "the pitch edges are too close together" — the
 * RAILS, the long lines running away from the camera, are the weak half. Those are the
 * edges of a strip of grass against more grass. A wicket is three bright vertical bars
 * against a dark background, always present, never worn off, and exactly 0.2286 m across.
 * It is the most reliable landmark on the field and nothing in this package was looking
 * for it.
 *
 * WHAT IT DELIBERATELY DOES NOT DO. It does not produce a [PitchQuad] and it does not feed
 * calibration. [StumpGeometry] explains why at length: three stump bases are collinear, and
 * collinear points cannot define a plane map however badly one is wanted. This detector
 * exists to put a measured landmark on screen so that its reliability can be SEEN on real
 * footage before anything is built on top of it — which is the order everything else in
 * this package has been wrong about at least once.
 *
 * ANALYSIS WIDTH IS HIGHER THAN THE OTHER TWO, and on purpose. The ball tracker and the
 * pitch detector work at 480 because a ball is a blob and a crease is metres long. A stump
 * is 0.0229 m across: at 480 wide, from twenty metres, all three together are a couple of
 * pixels. Finding three separate bars in that is not a tuning problem, it is an arithmetic
 * impossibility, so this stage gets its own larger frame.
 */
class OpenCvStumpDetector(
    private val analysisWidth: Int = 960,
) {
    val available: Boolean = OpenCvBallTracker.ensureLoaded()

    private var framesSeen = 0
    private var barsFound = 0
    private var setsFound = 0
    private var horizonY: Float? = null
    private var barsAboveHorizon = 0
    private var totalProcessingMs = 0L
    private var lastRejection: String? = "nothing analysed yet"
    private var released = false

    private var scratchPacked: ByteArray? = null
    private var scratchFull: Mat? = null

    fun report() = StumpDetectorReport(
        framesSeen = framesSeen,
        barsFound = barsFound,
        setsFound = setsFound,
        averageProcessingMs = if (framesSeen == 0) 0.0 else totalProcessingMs.toDouble() / framesSeen,
        lastRejection = lastRejection,
        horizonY = horizonY,
        barsAboveHorizon = barsAboveHorizon,
    )

    /**
     * The wicket in one frame, or null.
     *
     * Same frame contract as every other engine here: luma bytes, the stride they are
     * really laid out at, and the turn that makes them upright.
     */
    fun detect(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        /**
         * Crease-angled segments from the pitch detector's view of the SAME frame.
         *
         * Optional, and an empty list changes nothing: with no segments the ranking is by
         * shape alone, exactly as before. Passing stale ones from an earlier frame would
         * be worse than passing none.
         */
        creases: List<CreaseSegment> = emptyList(),
    ): StumpSet? {
        if (!available || released || width <= 0 || height <= 0) return null

        framesSeen++
        val startedAt = System.nanoTime()
        return try {
            findSet(luma, width, height, rowStride, rotationDegrees, creases)
        } catch (t: Throwable) {
            Log.w(TAG, "stump detection failed", t)
            lastRejection = "OpenCV threw: ${t.javaClass.simpleName}"
            null
        } finally {
            totalProcessingMs += (System.nanoTime() - startedAt) / 1_000_000
        }
    }

    private fun findSet(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment>,
    ): StumpSet? {
        barsFound = 0
        barsAboveHorizon = 0

        val packed = if (rowStride == width) {
            luma
        } else {
            val out = scratchPacked?.takeIf { it.size == width * height }
                ?: ByteArray(width * height).also { scratchPacked = it }
            for (row in 0 until height) {
                val from = row * rowStride
                if (from + width > luma.size) {
                    // Same refusal the pitch detector makes, for the same reason: into a
                    // reused buffer the missing rows are last frame's, and the seam between
                    // them is a long straight bright edge — which is exactly what this is
                    // looking for.
                    lastRejection = "short luma buffer"
                    return null
                }
                System.arraycopy(luma, from, out, row * width, width)
            }
            out
        }

        val full = scratchFull?.takeIf { it.rows() == height && it.cols() == width }
            ?: Mat(height, width, CvType.CV_8UC1).also {
                scratchFull?.release()
                scratchFull = it
            }
        full.put(0, 0, packed)

        val scale = analysisWidth.toDouble() / width
        val small = Mat()
        if (scale < 1.0) {
            Imgproc.resize(full, small, Size(analysisWidth.toDouble(), height * scale), 0.0, 0.0, Imgproc.INTER_AREA)
        } else {
            full.copyTo(small)
        }

        val upright = when (((rotationDegrees % 360) + 360) % 360) {
            90 -> Mat().also { Core.rotate(small, it, Core.ROTATE_90_CLOCKWISE); small.release() }
            180 -> Mat().also { Core.rotate(small, it, Core.ROTATE_180); small.release() }
            270 -> Mat().also { Core.rotate(small, it, Core.ROTATE_90_COUNTERCLOCKWISE); small.release() }
            else -> small
        }

        val frameW = upright.cols()
        val frameH = upright.rows()

        // The horizon comes off the GREYSCALE frame, before any threshold. A binarised
        // picture has lost exactly the texture and brightness the estimate reads.
        horizonY = estimateHorizon(upright, frameH)

        val blurred = Mat()
        Imgproc.GaussianBlur(upright, blurred, Size(3.0, 3.0), 0.0)
        upright.release()

        /*
         * ADAPTIVE, NEVER OTSU.
         *
         * A global threshold is the mistake the pitch detector spent a session being wrong
         * about: put a treeline in frame and Otsu splits sky from ground rather than paint
         * from grass, and everything of interest saturates into one shape. Stumps are
         * BRIGHTER THAN WHAT IS DIRECTLY BEHIND THEM, which is a local claim, so it is
         * tested locally.
         */
        val bright = Mat()
        Imgproc.adaptiveThreshold(
            blurred,
            bright,
            255.0,
            Imgproc.ADAPTIVE_THRESH_GAUSSIAN_C,
            Imgproc.THRESH_BINARY,
            PitchThreshold.ADAPTIVE_BLOCK,
            PitchThreshold.ADAPTIVE_C,
        )
        blurred.release()

        // Close along the vertical only. A stump is usually broken into pieces by the
        // bails, a shadow, or the batter's pads across it; a horizontal close would just as
        // happily join it to the fence behind.
        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(1.0, 7.0))
        Imgproc.morphologyEx(bright, bright, Imgproc.MORPH_CLOSE, kernel)
        kernel.release()

        val contours = mutableListOf<MatOfPoint>()
        val hierarchy = Mat()
        Imgproc.findContours(bright, contours, hierarchy, Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        hierarchy.release()
        bright.release()

        val candidates = ArrayList<StumpCandidate>()
        for (contour in contours) {
            val box = Imgproc.boundingRect(contour)
            contour.release()

            if (box.width <= 0 || box.height <= 0) continue
            if (box.height.toFloat() / box.width < MIN_ASPECT) continue

            val heightFraction = box.height.toFloat() / frameH
            if (heightFraction < MIN_HEIGHT_FRACTION || heightFraction > MAX_HEIGHT_FRACTION) continue
            if (box.width.toFloat() / frameW > MAX_WIDTH_FRACTION) continue

            val baseY = (box.y + box.height).toFloat() / frameH
            // A wicket stands on the pitch. A trunk at the treeline does not, and this is
            // the only test that can tell them apart — geometrically they are identical.
            if (!GroundHorizon.isOnGround(baseY, horizonY)) {
                barsAboveHorizon++
                continue
            }

            candidates.add(
                StumpCandidate(
                    centreX = (box.x + box.width / 2f) / frameW,
                    baseY = baseY,
                    topY = box.y.toFloat() / frameH,
                ),
            )
        }

        barsFound = candidates.size
        if (candidates.size < StumpGeometry.MIN_CANDIDATES) {
            lastRejection = if (barsAboveHorizon > 0) {
                "only ${candidates.size} bars on the ground ($barsAboveHorizon above the horizon)"
            } else {
                "only ${candidates.size} tall thin bars in frame"
            }
            return null
        }

        val set = StumpGeometry.findSet(
            candidates,
            creases,
            frameAspect = frameW.toFloat() / frameH.coerceAtLeast(1),
        )
        if (set == null) {
            lastRejection = "$barsFound bars, none of them three in a wicket's shape"
            return null
        }

        setsFound++
        lastRejection = null
        return set
    }

    /**
     * Per-row texture and brightness, reduced to one number each, then judged.
     *
     * Two OpenCV reductions and no loop over pixels in Kotlin: a Sobel in x for the
     * texture, a row-mean for both. Cheap enough to run on every frame beside everything
     * else this screen already does.
     */
    private fun estimateHorizon(gray: Mat, rows: Int): Float? {
        if (rows < GroundHorizon.MIN_ROWS) return null

        val gradient = Mat()
        val absolute = Mat()
        val textureColumn = Mat()
        val brightnessColumn = Mat()
        try {
            Imgproc.Sobel(gray, gradient, CvType.CV_16S, 1, 0, 3)
            Core.convertScaleAbs(gradient, absolute)

            // REDUCE_AVG along dimension 1 collapses each row to its mean.
            Core.reduce(absolute, textureColumn, 1, Core.REDUCE_AVG, CvType.CV_32F)
            Core.reduce(gray, brightnessColumn, 1, Core.REDUCE_AVG, CvType.CV_32F)

            val texture = FloatArray(rows)
            val brightness = FloatArray(rows)
            textureColumn.get(0, 0, texture)
            brightnessColumn.get(0, 0, brightness)

            return GroundHorizon.estimate(texture, brightness)
        } catch (t: Throwable) {
            Log.w(TAG, "horizon estimate failed", t)
            return null
        } finally {
            gradient.release()
            absolute.release()
            textureColumn.release()
            brightnessColumn.release()
        }
    }

    fun release() {
        released = true
        scratchFull?.release()
        scratchFull = null
        scratchPacked = null
    }

    companion object {
        private const val TAG = "OpenCvStumpDetector"

        /** Taller than it is wide, by this much, before it is worth considering. */
        const val MIN_ASPECT = 2.0f

        /**
         * How much of the frame's height a stump may occupy.
         *
         * The floor keeps out speckle; the ceiling keeps out net poles, sightscreen struts
         * and the batter, all of which run most of the way up a frame in which the stumps
         * do not.
         */
        const val MIN_HEIGHT_FRACTION = 0.015f
        const val MAX_HEIGHT_FRACTION = 0.30f

        /** A bar wider than this is a post, a bat, or a leg. */
        const val MAX_WIDTH_FRACTION = 0.03f
    }
}
