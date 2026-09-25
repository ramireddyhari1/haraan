package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.sin

/**
 * How far the CAMERA moved between two frames, as opposed to what was in front of it.
 *
 * WHY THIS HAS TO EXIST BEFORE ANYTHING CAN BE TRACKED ACROSS FRAMES. A wicket does not
 * move. Every frame-to-frame change in where it appears is the phone: a tripod settling, a
 * gust, an operator shifting their weight, the autofocus breathing the field of view in
 * and out. Without a model of that, a tracker has exactly two options and both are wrong —
 * a tight association gate that breaks the moment somebody nudges the tripod, or a gate
 * wide enough to survive a nudge, which is also wide enough to hop onto the sightscreen
 * strut two metres away.
 *
 * With one, the gate can be tight AND survive a nudge, because the nudge is subtracted
 * first. That is the whole argument for this file.
 *
 * A SIMILARITY, NOT A HOMOGRAPHY, and deliberately. Between two adjacent frames a handheld
 * or tripod camera pans, rolls and zooms by a hair; it does not re-project the scene. Four
 * parameters fitted from dozens of correspondences is a massively over-determined and
 * therefore stable fit. Eight parameters fitted from the same points would absorb real
 * subject motion into a perspective warp and hand back something that tracks the batter
 * rather than the ground.
 *
 * UNITS. [dx] is in frame widths and [dy] in frame HEIGHTS, because that is what the rest
 * of this package normalises against. The arithmetic inside works in aspect-corrected
 * space so that a rotation is a rotation, and converts back at the edges.
 */
data class FrameMotion(
    /** Camera-induced shift of image content, in frame widths. */
    val dx: Float,
    /** Camera-induced shift of image content, in frame heights. */
    val dy: Float,
    /** Zoom between the frames. 1.0 is none. */
    val scale: Float,
    /** Roll between the frames, radians, positive clockwise on screen. */
    val rotationRad: Float,
    /** Correspondences that agreed with the fitted motion. */
    val inliers: Int,
    /** Correspondences offered. */
    val total: Int,
    /** Where the transform turns, normalised. Content pivots about this point. */
    val pivotX: Float = 0.5f,
    val pivotY: Float = 0.5f,
) {

    /**
     * How much this motion should be believed, 0..1.
     *
     * Two things, multiplied, because they fail separately. The AGREEMENT term is the
     * inlier fraction: a scene where most points moved together is a camera move, one
     * where they did not is a crowd. The SUPPORT term saturates with the raw inlier count,
     * because a unanimous six points is still six points, and a fit from six points on a
     * blurred frame is not worth the same as a fit from sixty.
     */
    val confidence: Float
        get() {
            if (total <= 0 || inliers <= 0) return 0f
            val agreement = inliers.toFloat() / total
            val support = (inliers.toFloat() / CameraMotion.FULL_SUPPORT).coerceAtMost(1f)
            return (agreement * support).coerceIn(0f, 1f)
        }

    /** Whether this is worth applying, as opposed to assuming the camera held still. */
    val isUsable: Boolean
        get() = inliers >= CameraMotion.MIN_CORRESPONDENCES &&
            confidence >= CameraMotion.MIN_CONFIDENCE

    /** Displacement of the frame centre, in frame widths. The number to show a human. */
    val magnitude: Float get() = hypot(dx, dy)

    /**
     * Carry a point from the previous frame into this one.
     *
     * [aspect] is the upright frame's width over its height, needed for the same reason it
     * is needed everywhere else here: x is normalised against the width and y against the
     * height, so a rotation applied to raw normalised coordinates would shear.
     */
    fun apply(point: Point2, aspect: Float): Point2 {
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        // Into aspect-corrected space, relative to the pivot.
        val px = point.x - pivotX
        val py = (point.y - pivotY) / a

        val c = cos(rotationRad.toDouble()) * scale
        val s = sin(rotationRad.toDouble()) * scale

        val rx = c * px - s * py
        val ry = s * px + c * py

        return Point2(
            rx + pivotX + dx,
            ry * a + pivotY + dy,
        )
    }

    companion object {
        /** The camera held still — and said so, rather than said nothing. */
        val STILL = FrameMotion(0f, 0f, 1f, 0f, inliers = 0, total = 0)
    }
}

/**
 * Fitting a camera motion to a set of point correspondences.
 *
 * PURE ARITHMETIC, NO OPENCV, ON PURPOSE. Finding the correspondences needs a native
 * optical flow; deciding what they mean does not, and it is the half that can be wrong in
 * ways only a test will catch. [OpenCvCameraMotion] does the first part and hands the
 * points here, so the judgement lives somewhere it can be proved at a desk — the same
 * split [PitchGeometry] makes against the pitch detector, for the same reason.
 */
object CameraMotion {

    /** Below this many agreeing points, the fit is a coincidence rather than a motion. */
    const val MIN_CORRESPONDENCES = 6

    /** Inliers at which the support term saturates. Beyond it, more points add nothing. */
    const val FULL_SUPPORT = 24f

    /** Below this the motion is not applied, and callers are told the camera is unknown. */
    const val MIN_CONFIDENCE = 0.30f

    /**
     * How far from the median displacement a correspondence may sit and still count.
     *
     * A multiple of the median absolute deviation rather than an absolute distance,
     * because the right threshold is completely different for a phone on a tripod and a
     * phone being carried. The MAD makes the test scale itself to whatever the frame is
     * actually doing.
     */
    const val OUTLIER_MAD_MULTIPLE = 3.0f

    /** A floor under the MAD, so a still frame does not reject its own sensor noise. */
    const val MIN_MAD = 0.0015f

    /**
     * Motion beyond this in one frame is not a camera settling, it is the phone being
     * picked up or pointed somewhere else — and a tracker should be told it has lost its
     * bearings rather than handed a transform that teleports its anchor.
     */
    const val MAX_PLAUSIBLE_SHIFT = 0.25f

    /** Zoom beyond this in one frame is an autofocus hunt or a bad fit, not a camera. */
    const val MAX_PLAUSIBLE_SCALE = 1.25f

    /**
     * How far the inliers may sit from what the fitted motion predicts, RMS, in frame
     * widths, before the fit is thrown away as not describing a camera at all.
     *
     * Absolute rather than relative, and that is the whole point of it — see the check in
     * [estimate]. At the 320-wide frame the estimator works on, this is about four pixels:
     * comfortably above the sub-pixel wander of a real Lucas-Kanade track on static
     * furniture, and far below the scatter of a scene where nothing is static.
     */
    const val MAX_FIT_RESIDUAL = 0.012

    /**
     * The camera motion implied by [from] moving to [to], or [FrameMotion.STILL] when the
     * points cannot support one.
     *
     * Both lists are normalised upright image points, the same length, paired by index.
     *
     * THE TWO-PASS STRUCTURE IS THE ROBUSTNESS. The first pass finds the median
     * displacement and throws out everything far from it — which is precisely the batter,
     * the bowler's arm, the ball and the tree moving in the wind, because those are the
     * points in the scene that are NOT doing what the camera is doing. The second pass
     * fits a similarity to what survives. Fitting first and rejecting after would let the
     * batter drag the fit before ever getting a chance to be an outlier.
     */
    /*
     * Pre-allocated working buffers, resized lazily. The correspondence count is stable
     * frame-to-frame (120 corners max), so resizing is rare and allocation is near zero.
     */
    private var bufSize = 0
    private var srcX = DoubleArray(0)
    private var srcY = DoubleArray(0)
    private var dstX = DoubleArray(0)
    private var dstY = DoubleArray(0)
    private var shiftX = DoubleArray(0)
    private var shiftY = DoubleArray(0)
    private var residuals = DoubleArray(0)
    private var medianScratch = DoubleArray(0)

    private fun ensureBuffers(n: Int) {
        if (n <= bufSize) return
        bufSize = n
        srcX = DoubleArray(n); srcY = DoubleArray(n)
        dstX = DoubleArray(n); dstY = DoubleArray(n)
        shiftX = DoubleArray(n); shiftY = DoubleArray(n)
        residuals = DoubleArray(n); medianScratch = DoubleArray(n)
    }

    fun estimate(from: List<Point2>, to: List<Point2>, aspect: Float): FrameMotion {
        if (from.size != to.size || from.size < MIN_CORRESPONDENCES) return FrameMotion.STILL
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        val total = from.size
        ensureBuffers(total)

        // Aspect-corrected copies. Everything below works in this space; the result is
        // converted back at the very end.
        for (i in 0 until total) {
            srcX[i] = from[i].x; srcY[i] = from[i].y / a
            dstX[i] = to[i].x; dstY[i] = to[i].y / a
            shiftX[i] = dstX[i] - srcX[i]
            shiftY[i] = dstY[i] - srcY[i]
        }

        val medianX = medianOf(shiftX, total)
        val medianY = medianOf(shiftY, total)

        for (i in 0 until total) {
            residuals[i] = hypot(shiftX[i] - medianX, shiftY[i] - medianY)
        }
        val mad = medianOf(residuals, total).coerceAtLeast(MIN_MAD.toDouble())
        val limit = mad * OUTLIER_MAD_MULTIPLE

        val keep = ArrayList<Int>(total)
        for (i in 0 until total) if (residuals[i] <= limit) keep.add(i)
        if (keep.size < MIN_CORRESPONDENCES) return FrameMotion.STILL

        /*
         * UMEYAMA, four parameters.
         *
         * Centre both clouds, then the rotation and scale fall out of two sums over the
         * centred pairs — no iteration, no matrix library, one pass. The translation is
         * whatever is left once the rotated, scaled source centroid is put on the
         * destination centroid, which is why it cannot be fitted first.
         */
        var sumSrcX = 0.0
        var sumSrcY = 0.0
        var sumDstX = 0.0
        var sumDstY = 0.0
        for (i in keep) {
            sumSrcX += srcX[i]; sumSrcY += srcY[i]; sumDstX += dstX[i]; sumDstY += dstY[i]
        }
        val n = keep.size.toDouble()
        val cSrcX = sumSrcX / n
        val cSrcY = sumSrcY / n
        val cDstX = sumDstX / n
        val cDstY = sumDstY / n

        var dot = 0.0
        var cross = 0.0
        var srcNormSq = 0.0
        for (i in keep) {
            val ax = srcX[i] - cSrcX
            val ay = srcY[i] - cSrcY
            val bx = dstX[i] - cDstX
            val by = dstY[i] - cDstY
            dot += ax * bx + ay * by
            cross += ax * by - ay * bx
            srcNormSq += ax * ax + ay * ay
        }

        // Every kept point sits on the centroid: a translation, and no way to see a
        // rotation or a scale in it. Reporting one would be inventing it.
        if (srcNormSq < 1e-12) {
            return finish(
                dxCorrected = cDstX - cSrcX,
                dyCorrected = cDstY - cSrcY,
                scale = 1.0,
                rotation = 0.0,
                pivotX = cSrcX,
                pivotYCorrected = cSrcY,
                inliers = keep.size,
                total = total,
                aspect = a,
            )
        }

        val scale = hypot(dot, cross) / srcNormSq
        val rotation = atan2(cross, dot)

        /*
         * DOES THE FITTED MOTION ACTUALLY EXPLAIN THE POINTS?
         *
         * The MAD test above is RELATIVE — it throws out whatever is unusual for this
         * scene — and that is exactly right for finding the batter in an otherwise steady
         * frame. It is exactly wrong for a scene where nothing is steady: a crowd, a
         * treeline in wind, an optical flow that lost the plot. There the scatter IS the
         * norm, the MAD widens to accommodate it, every point survives as an inlier, and a
         * four-parameter least squares dutifully hands back the best transform through a
         * cloud of noise — with a perfect inlier fraction and total confidence.
         *
         * So the fit is finally checked against an ABSOLUTE standard. A real camera move
         * leaves its inliers within a few pixels of where it predicts; a fit that cannot
         * manage that is not describing a camera, whatever its inlier count says.
         */
        var residualSq = 0.0
        val c = kotlin.math.cos(rotation) * scale
        val s = kotlin.math.sin(rotation) * scale
        for (i in keep) {
            val ax = srcX[i] - cSrcX
            val ay = srcY[i] - cSrcY
            val px = c * ax - s * ay + cDstX
            val py = s * ax + c * ay + cDstY
            residualSq += (dstX[i] - px) * (dstX[i] - px) + (dstY[i] - py) * (dstY[i] - py)
        }
        if (kotlin.math.sqrt(residualSq / keep.size) > MAX_FIT_RESIDUAL) return FrameMotion.STILL

        return finish(
            // Measured at the pivot, which is the source centroid, so the rotation and
            // scale terms vanish there and this is exactly the shift of that point.
            // apply() pivots about the same place, so the two cannot disagree.
            dxCorrected = cDstX - cSrcX,
            dyCorrected = cDstY - cSrcY,
            scale = scale,
            rotation = rotation,
            pivotX = cSrcX,
            pivotYCorrected = cSrcY,
            inliers = keep.size,
            total = total,
            aspect = a,
        )
    }

    private fun finish(
        dxCorrected: Double,
        dyCorrected: Double,
        scale: Double,
        rotation: Double,
        pivotX: Double,
        pivotYCorrected: Double,
        inliers: Int,
        total: Int,
        aspect: Double,
    ): FrameMotion {
        // Refused rather than clamped. A clamped transform still moves an anchor, just
        // less far; a refused one tells the tracker it has no idea where the camera went,
        // which is a different and much more useful thing to know.
        if (abs(dxCorrected) > MAX_PLAUSIBLE_SHIFT || abs(dyCorrected) > MAX_PLAUSIBLE_SHIFT) {
            return FrameMotion.STILL
        }
        if (scale > MAX_PLAUSIBLE_SCALE || scale < 1.0 / MAX_PLAUSIBLE_SCALE) return FrameMotion.STILL

        return FrameMotion(
            dx = dxCorrected.toFloat(),
            dy = (dyCorrected * aspect).toFloat(),
            scale = scale.toFloat(),
            rotationRad = rotation.toFloat(),
            inliers = inliers,
            total = total,
            pivotX = pivotX.toFloat(),
            pivotY = (pivotYCorrected * aspect).toFloat(),
        )
    }

    /**
     * Median of the first [count] elements, using the pre-allocated scratch buffer.
     * Does not modify the source array.
     */
    private fun medianOf(values: DoubleArray, count: Int): Double {
        if (count <= 0) return 0.0
        System.arraycopy(values, 0, medianScratch, 0, count)
        medianScratch.sort(0, count)
        val mid = count / 2
        return if (count % 2 == 1) medianScratch[mid] else (medianScratch[mid - 1] + medianScratch[mid]) / 2.0
    }
}
