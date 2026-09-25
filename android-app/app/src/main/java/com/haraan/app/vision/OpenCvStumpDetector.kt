package com.haraan.app.vision

import android.util.Log
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.MatOfPoint
import org.opencv.core.Rect
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
    /** Compact solid lumps on the ground this frame offered, before the stone test. */
    val stonesFound: Int = 0,
    /**
     * How many of those were even the right shape to be a wicket.
     *
     * Reported beside the raw count because the two answer different questions, and the
     * first field test could not tell them apart: 478 lumps sounds like a detector running
     * wild, and the number that decides anything is how many of them got as far as being
     * scored.
     */
    val stonesPlausible: Int = 0,
    /** Frames that ended in a stone rather than a set of bars. */
    val stoneMarksFound: Int = 0,
    /**
     * Whether the last stone came from the dark pass.
     *
     * Worth reporting because it says which way round the contrast was: a pale stone on
     * dark earth and a dark stone on a concrete strip are found by opposite tests, and
     * knowing which one fired tells you what the camera is actually looking at.
     */
    val stoneWasDark: Boolean = false,
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
    private var stonesFound = 0
    private var stonesPlausible = 0
    private var stoneMarksFound = 0
    private var stoneWasDark = false
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
        stonesFound = stonesFound,
        stonesPlausible = stonesPlausible,
        stoneMarksFound = stoneMarksFound,
        stoneWasDark = stoneWasDark,
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
    ): StumpSet? = (detectWicket(luma, width, height, rowStride, rotationDegrees, creases)
        as? WicketSighting.Stumps)?.set

    /**
     * The wicket in one frame, of either kind, or null.
     *
     * THE ORDER IS THE POINT. Three bars are looked for first and win whenever they are
     * there, because a set of stumps is both harder to fake and the only one of the two
     * that carries a scale. The stone pass runs only when that fails — which on the
     * grounds this app is used on is most of the time, since the wicket is a stone, a
     * brick or a stack of two, and no amount of tuning will make three bars out of one
     * lump.
     */
    fun detectWicket(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment> = emptyList(),
    ): WicketSighting? {
        if (!available || released || width <= 0 || height <= 0) return null

        framesSeen++
        val startedAt = System.nanoTime()
        return try {
            findWicket(luma, width, height, rowStride, rotationDegrees, creases)
        } catch (t: Throwable) {
            Log.w(TAG, "stump detection failed", t)
            lastRejection = "OpenCV threw: ${t.javaClass.simpleName}"
            null
        } finally {
            totalProcessingMs += (System.nanoTime() - startedAt) / 1_000_000
        }
    }

    private fun findWicket(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment>,
    ): WicketSighting? {
        barsFound = 0
        barsAboveHorizon = 0
        stonesFound = 0
        stonesPlausible = 0
        stoneWasDark = false

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
            STUMP_ADAPTIVE_C,
        )

        /*
         * VERTICAL CLOSE, INTO A SEPARATE MAT.
         *
         * The stone pass needs the mask BEFORE the vertical close, because a stump-height
         * close distorts every lump's aspect ratio and the stone test leans on that ratio.
         * Previously this was solved by cloning bright (~500 KB) before every close. Now
         * the close writes into its own Mat, and bright stays intact for collectStones()
         * if the bar search fails. On the happy path (stumps found), no clone ever happens.
         */
        val closed = Mat()
        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(1.0, 7.0))
        Imgproc.morphologyEx(bright, closed, Imgproc.MORPH_CLOSE, kernel)
        kernel.release()

        val contours = mutableListOf<MatOfPoint>()
        val hierarchy = Mat()
        Imgproc.findContours(closed, contours, hierarchy, Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        hierarchy.release()
        closed.release()

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
        val frameAspect = frameW.toFloat() / frameH.coerceAtLeast(1)

        val search = StumpGeometry.search(candidates, creases, frameAspect)
        val set = search.set
        if (set != null) {
            bright.release()
            blurred.release()
            setsFound++
            lastRejection = null
            return WicketSighting.Stumps(set)
        }

        // Why the bar search stopped, kept aside: if the stone pass also finds nothing,
        // this is the more informative of the two reasons and the screen should show it.
        /*
         * NAME THE RULE, NOT JUST THE OUTCOME.
         *
         * "$barsFound bars, none of them three in a wicket's shape" was what this said on a
         * ground with three stumps a metre in front of the phone and exactly three bars
         * found — which is one triple, rejected by exactly one rule, and the screen could
         * not say which. [StumpGeometry.search] now carries the nearest miss's reason with
         * its measured value and its limit, so the next thing a tester does is change a
         * number rather than guess at a photograph.
         */
        val barRejection = when {
            candidates.isEmpty() && barsAboveHorizon > 0 ->
                "no bars on the ground ($barsAboveHorizon above the horizon)"
            candidates.size < StumpGeometry.MIN_CANDIDATES ->
                "only ${candidates.size} tall thin bars in frame"
            search.reason != null -> "$barsFound bars; nearest miss: ${search.reason}"
            else -> "$barsFound bars, none of them three in a wicket's shape"
        }

        /*
         * THE GULLY WICKET.
         *
         * Pale first, against the mask already computed, then dark on a second threshold -
         * and the dark pass runs only when the pale one found nothing, because it costs
         * another threshold and another contour sweep over a 960-wide frame, and most of
         * the time the first pass has already answered. A white stone on red earth and a
         * granite one on a concrete strip are opposite tests; a ground gives you either.
         */
        val paleStones = collectStones(bright, frameW, frameH)
        bright.release()
        var mark = StoneGeometry.findMark(paleStones, creases, frameAspect)
        var stones = paleStones.size
        var plausible = StoneGeometry.plausibleCount(paleStones)

        if (mark == null) {
            val dark = Mat()
            Imgproc.adaptiveThreshold(
                blurred,
                dark,
                255.0,
                Imgproc.ADAPTIVE_THRESH_GAUSSIAN_C,
                Imgproc.THRESH_BINARY_INV,
                PitchThreshold.ADAPTIVE_BLOCK,
                PitchThreshold.ADAPTIVE_C,
            )
            val darkStones = collectStones(dark, frameW, frameH)
            dark.release()
            stones += darkStones.size
            plausible += StoneGeometry.plausibleCount(darkStones)
            val darkMark = StoneGeometry.findMark(darkStones, creases, frameAspect)
            if (darkMark != null) {
                mark = darkMark
                stoneWasDark = true
            }
        }

        blurred.release()
        stonesFound = stones
        stonesPlausible = plausible

        if (mark == null) {
            lastRejection = when {
                stones == 0 -> barRejection
                plausible == 0 -> "$barRejection; $stones lumps, none wicket-shaped"
                else -> "$barRejection; $plausible of $stones lumps scored, none a wicket"
            }
            return null
        }

        stoneMarksFound++
        lastRejection = null
        return WicketSighting.Stone(mark)
    }

    /**
     * Compact solid things standing on the ground, from an already-binarised mask.
     *
     * Solidity is measured here rather than inferred later because it needs the contour
     * itself, which does not survive the trip: it is the fraction of its own bounding box
     * a shape actually fills, and it is the whole reason a stone can be told from the
     * bush behind it.
     *
     * The mask is closed IN PLACE, so callers hand over a copy they no longer need.
     */
    private fun collectStones(mask: Mat, frameW: Int, frameH: Int): List<StoneCandidate> {
        /*
         * GROUND ONLY, AND AT HALF SCALE.
         *
         * Measured on the first field test: the stone passes took this stage from about
         * 30 ms a frame to 114, which is six frames a second once the pitch detector has
         * had its turn - not a detector, a slideshow. Both savings here are free rather
         * than traded:
         *
         * The crop, because the horizon is already known and nothing above it can be a
         * wicket standing on the ground. Every blob it removes is one the ground test was
         * going to throw away anyway, and the treeline is where most of a frame's blobs
         * live.
         *
         * The half scale, because the 960-wide frame was argued for on stumps: three bars
         * 0.0229 m across, a couple of pixels each at twenty metres. A stone is a hand's
         * width or more and survives halving with tens of pixels to spare. Contour finding
         * is worked per pixel, so half the width and half the height is a quarter of it.
         */
        val top = (((horizonY ?: 0f) * frameH).toInt()).coerceIn(0, (frameH - 2).coerceAtLeast(0))
        val ground = Mat(mask, Rect(0, top, frameW, frameH - top))

        val small = Mat()
        val scaledW = (ground.cols() / 2).coerceAtLeast(1)
        val scaledH = (ground.rows() / 2).coerceAtLeast(1)
        // NEAREST on a mask that is already black or white: anything smoother would invent
        // grey edges for a later threshold to guess at.
        Imgproc.resize(ground, small, Size(scaledW.toDouble(), scaledH.toDouble()), 0.0, 0.0, Imgproc.INTER_NEAREST)
        ground.release()

        // Square, and small. Enough to close the speckle a threshold leaves inside a lump,
        // not enough to change its proportions - which are the measurement.
        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(3.0, 3.0))
        Imgproc.morphologyEx(small, small, Imgproc.MORPH_CLOSE, kernel)
        kernel.release()

        val contours = mutableListOf<MatOfPoint>()
        val hierarchy = Mat()
        Imgproc.findContours(small, contours, hierarchy, Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        hierarchy.release()
        small.release()

        // Back to the whole frame's coordinates: undo the halving, then put the crop back.
        val found = ArrayList<StoneCandidate>()
        for (contour in contours) {
            val box = Imgproc.boundingRect(contour)
            val area = Imgproc.contourArea(contour)
            contour.release()

            if (box.width <= 0 || box.height <= 0) continue

            val boxTop = top + box.y * 2
            val boxHeight = box.height * 2
            val baseY = (boxTop + boxHeight).toFloat() / frameH
            // The same ground test the bars get, for the same reason: a boulder on the
            // hillside behind the ground is a compact solid lump too. The crop already
            // removes most of them; this catches the rest, including the case where no
            // horizon could be estimated and the crop did nothing.
            if (!GroundHorizon.isOnGround(baseY, horizonY)) continue

            val boxArea = (box.width * box.height).toDouble()
            found.add(
                StoneCandidate(
                    centreX = (box.x * 2 + box.width).toFloat() / frameW,
                    baseY = baseY,
                    topY = boxTop.toFloat() / frameH,
                    width = (box.width * 2).toFloat() / frameW,
                    solidity = if (boxArea <= 0.0) 0f else (area / boxArea).toFloat(),
                ),
            )
        }
        return found
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
        const val MAX_HEIGHT_FRACTION = 0.45f

        /** A bar wider than this is a post, a bat, or a leg. */
        const val MAX_WIDTH_FRACTION = 0.045f

        /** Threshold offset: -3.5 ensures clean segmentation of stumps in room/turf lighting. */
        const val STUMP_ADAPTIVE_C = -3.5
    }
}
