package com.haraan.app.vision

import android.util.Log
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc
import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.max
import kotlin.math.min

/**
 * Finds the pitch in the camera frame, so the guide can stop being a drawing and start
 * being a measurement.
 *
 * A static aiming outline helps somebody point a phone. It does not know where anything
 * is. Once the four crease corners are located, the same four points give a homography —
 * and from that a bounce becomes a length in metres rather than a dot on a picture.
 *
 * WHY THIS IS EASIER THAN THE BALL, and why it is not gated behind it. Creases are painted
 * white lines on grass: static, high-contrast, metres long, and identical in every frame.
 * A ball is a small fast smear. These are different problems, and this one can be worked on
 * and judged from a single photograph at a desk — it does not need an afternoon of filming
 * to make progress on.
 *
 * WHAT IT WILL GET WRONG. A worn pitch with faded paint, a boundary rope running parallel,
 * a sightscreen edge, net posts in an indoor centre. Every one of those is a long bright
 * line. So the geometry is checked before anything is accepted — a pitch seen from behind
 * the bowler narrows with distance, and that single constraint throws out most impostors.
 * When nothing survives, the answer is null and the caller falls back to tapped corners.
 */
class OpenCvPitchDetector(
    private val analysisWidth: Int = 480,
) {
    val available: Boolean = OpenCvBallTracker.ensureLoaded()

    /** Recent accepted quads, for the stability check. A tripod does not move. */
    private val recent = ArrayDeque<PitchQuad>()
    private var released = false

    /**
     * Look for the pitch in one frame.
     *
     * Returns a quad only once several consecutive frames agree on roughly the same one.
     * A single frame's answer is worth very little — a fielder walking through the crease
     * line, a shadow edge, one bad Hough threshold — and a guide that jitters between two
     * interpretations is worse than one that does not move at all.
     */
    fun detect(luma: ByteArray, width: Int, height: Int, rowStride: Int): PitchQuad? {
        if (!available || released || width <= 0 || height <= 0) return null

        return try {
            val candidate = findQuad(luma, width, height, rowStride) ?: run {
                // A miss decays the history rather than clearing it: the pitch does not
                // vanish because one frame had a fielder standing on the crease.
                if (recent.isNotEmpty()) recent.removeFirst()
                return stableQuad()
            }

            recent.addLast(candidate)
            while (recent.size > STABILITY_WINDOW) recent.removeFirst()
            stableQuad()
        } catch (t: Throwable) {
            Log.w(TAG, "pitch detection failed", t)
            null
        }
    }

    fun reset() {
        recent.clear()
    }

    fun release() {
        released = true
        recent.clear()
    }

    /**
     * The averaged quad, but only when the recent ones actually agree.
     *
     * Averaging alone would happily blend two different interpretations into a third that
     * matches neither, so the spread is checked first. Disagreement means the detector is
     * flickering between candidates, and the honest output then is nothing.
     */
    private fun stableQuad(): PitchQuad? {
        if (recent.size < STABILITY_WINDOW) return null

        val corners = (0 until 4).map { i ->
            val xs = recent.map { it.corners[i].x }
            val ys = recent.map { it.corners[i].y }
            val spread = max(xs.max() - xs.min(), ys.max() - ys.min())
            if (spread > MAX_CORNER_SPREAD) return null
            Point2(xs.average(), ys.average())
        }

        val quad = PitchQuad(
            corners = corners,
            source = QuadSource.DETECTED,
            // Steadier agreement across frames is the only thing here that resembles
            // confidence. It is not calibrated against anything and is named for what it
            // is: how much the detector agreed with itself.
            confidence = recent.map { it.confidence }.average().toFloat(),
        )
        return if (quad.isPlausible()) quad else null
    }

    /** One frame's best guess at the pitch, before any smoothing. */
    private fun findQuad(luma: ByteArray, width: Int, height: Int, rowStride: Int): PitchQuad? {
        val packed = if (rowStride == width) luma else ByteArray(width * height).also { out ->
            for (row in 0 until height) {
                val from = row * rowStride
                if (from + width > luma.size) return@also
                System.arraycopy(luma, from, out, row * width, width)
            }
        }

        val full = Mat(height, width, CvType.CV_8UC1)
        full.put(0, 0, packed)

        val scale = analysisWidth.toDouble() / width
        val small = Mat()
        if (scale < 1.0) {
            Imgproc.resize(full, small, Size(analysisWidth.toDouble(), height * scale), 0.0, 0.0, Imgproc.INTER_AREA)
        } else {
            full.copyTo(small)
        }
        full.release()

        val blurred = Mat()
        Imgproc.GaussianBlur(small, blurred, Size(5.0, 5.0), 0.0)

        // Creases are BRIGHT. Isolating the bright end before edge detection throws away
        // most of the outfield's texture, which otherwise generates hundreds of Hough
        // lines that have nothing to do with anything.
        val bright = Mat()
        Imgproc.threshold(blurred, bright, 0.0, 255.0, Imgproc.THRESH_BINARY + Imgproc.THRESH_OTSU)
        blurred.release()

        val edges = Mat()
        Imgproc.Canny(bright, edges, 50.0, 150.0)
        bright.release()

        val lines = Mat()
        Imgproc.HoughLinesP(
            edges,
            lines,
            1.0,
            Math.PI / 180.0,
            HOUGH_THRESHOLD,
            small.cols() * MIN_LINE_FRACTION,
            MAX_LINE_GAP,
        )
        val w = small.cols().toDouble()
        val h = small.rows().toDouble()
        small.release()
        edges.release()

        if (lines.rows() < 4) {
            lines.release()
            return null
        }

        // Sort every segment into "runs across the frame" (a crease) or "runs away from
        // the camera" (a pitch edge). Anything in between is neither.
        val creases = mutableListOf<Line>()
        val rails = mutableListOf<Line>()
        for (i in 0 until lines.rows()) {
            val v = lines.get(i, 0) ?: continue
            val line = Line(v[0], v[1], v[2], v[3])
            val degrees = abs(Math.toDegrees(atan2(line.y2 - line.y1, line.x2 - line.x1)))
            val fromHorizontal = min(degrees, 180.0 - degrees)
            when {
                fromHorizontal < CREASE_MAX_DEGREES -> creases.add(line)
                fromHorizontal > RAIL_MIN_DEGREES -> rails.add(line)
            }
        }
        lines.release()

        if (creases.size < 2 || rails.size < 2) return null

        // The two creases furthest apart vertically are the near and far ones; the two
        // rails furthest apart horizontally are the pitch's edges.
        val near = creases.maxByOrNull { it.midY } ?: return null
        val far = creases.minByOrNull { it.midY } ?: return null
        if (near.midY - far.midY < h * MIN_CREASE_SEPARATION) return null

        val left = rails.minByOrNull { it.midX } ?: return null
        val right = rails.maxByOrNull { it.midX } ?: return null
        if (right.midX - left.midX < w * MIN_RAIL_SEPARATION) return null

        val nearLeft = intersect(near, left) ?: return null
        val nearRight = intersect(near, right) ?: return null
        val farRight = intersect(far, right) ?: return null
        val farLeft = intersect(far, left) ?: return null

        val quad = PitchQuad(
            corners = listOf(
                Point2(nearLeft.x / w, nearLeft.y / h),
                Point2(nearRight.x / w, nearRight.y / h),
                Point2(farRight.x / w, farRight.y / h),
                Point2(farLeft.x / w, farLeft.y / h),
            ),
            source = QuadSource.DETECTED,
            // More supporting segments means the lines were not flukes.
            confidence = ((creases.size + rails.size) / 12f).coerceIn(0.1f, 1f),
        )
        return if (quad.isPlausible()) quad else null
    }

    /** Where two infinite lines cross, or null when they are parallel. */
    private fun intersect(a: Line, b: Line): Point2? {
        val a1 = a.y2 - a.y1
        val b1 = a.x1 - a.x2
        val c1 = a1 * a.x1 + b1 * a.y1

        val a2 = b.y2 - b.y1
        val b2 = b.x1 - b.x2
        val c2 = a2 * b.x1 + b2 * b.y1

        val determinant = a1 * b2 - a2 * b1
        if (abs(determinant) < 1e-6) return null

        return Point2((b2 * c1 - b1 * c2) / determinant, (a1 * c2 - a2 * c1) / determinant)
    }

    private data class Line(val x1: Double, val y1: Double, val x2: Double, val y2: Double) {
        val midX get() = (x1 + x2) / 2.0
        val midY get() = (y1 + y2) / 2.0
    }

    private companion object {
        const val TAG = "OpenCvPitchDetector"

        /** Frames that must agree before a quad is handed out. */
        const val STABILITY_WINDOW = 5

        /** How far a corner may wander between frames and still count as the same quad. */
        const val MAX_CORNER_SPREAD = 0.04

        const val HOUGH_THRESHOLD = 60
        const val MIN_LINE_FRACTION = 0.12
        const val MAX_LINE_GAP = 12.0

        /** Within this of horizontal, a segment is a crease. */
        const val CREASE_MAX_DEGREES = 25.0

        /** Beyond this from horizontal, it runs away down the pitch. */
        const val RAIL_MIN_DEGREES = 45.0

        const val MIN_CREASE_SEPARATION = 0.15
        const val MIN_RAIL_SEPARATION = 0.20
    }
}
