package com.haraan.app.vision

import android.util.Log
import org.opencv.core.Mat
import org.opencv.core.MatOfByte
import org.opencv.core.MatOfFloat
import org.opencv.core.MatOfPoint
import org.opencv.core.MatOfPoint2f
import org.opencv.core.Point
import org.opencv.core.Size
import org.opencv.core.TermCriteria
import org.opencv.imgproc.Imgproc
import org.opencv.video.Video

/**
 * The correspondences half of camera motion: what moved where between two frames.
 *
 * Sparse rather than dense, and that is a performance decision with a correctness bonus.
 * A dense flow over a 960-wide frame costs more than everything else this screen does put
 * together; a hundred corners tracked by Lucas-Kanade costs a couple of milliseconds. The
 * bonus is that corners land on things with structure — crease paint, the sightscreen
 * frame, the boundary board, the tripod's own view of the fence — which is exactly the
 * static furniture a camera-motion estimate wants, and almost never on flat grass or on
 * the sky.
 *
 * WHAT THIS FILE DOES NOT DECIDE. It does not judge the motion. It finds points, follows
 * them, throws out the ones the tracker itself flagged as lost, and hands the pairs to
 * [CameraMotion]. Every rule about what counts as a camera move rather than a batter
 * lives there, in plain Kotlin, where a test can reach it.
 *
 * ANALYSIS SCALE. Its own, and small. A shift of a tenth of a percent of the frame is a
 * fraction of a pixel at any resolution, so the estimate does not get better with more
 * pixels — it gets slower. 320 wide is enough for corner tracking and cheap enough to run
 * on every frame beside two detectors.
 */
class OpenCvCameraMotion(
    private val analysisWidth: Int = 320,
) {
    val available: Boolean = OpenCvBallTracker.ensureLoaded()

    private var previous: Mat? = null
    private var previousTurn: Int? = null
    private var released = false

    private var scratchPacked: ByteArray? = null
    private var scratchFull: Mat? = null

    /** Frames where a usable motion was produced, and frames where none could be. */
    var framesEstimated = 0
        private set
    var framesUnresolved = 0
        private set

    /**
     * The camera's move from the previous frame to this one.
     *
     * Returns [FrameMotion.STILL] on the first frame of a session, after a rotation, and
     * whenever the scene could not support a fit — all of which mean "unknown", which
     * [FrameMotion.isUsable] reports as false. A caller must never read STILL as a promise
     * that the camera held still.
     */
    fun onFrame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
    ): FrameMotion {
        if (!available || released || width <= 0 || height <= 0) return FrameMotion.STILL

        return try {
            val turn = ((rotationDegrees % 360) + 360) % 360
            val gray = toUpright(luma, width, height, rowStride, turn) ?: return FrameMotion.STILL

            /*
             * A quarter turn is not a camera motion and must never be fitted as one.
             *
             * The whole frame's content rotates, so every correspondence agrees with every
             * other one, the inlier fraction is perfect, and the estimator would hand back
             * a confident nonsense translation. The tracker is told separately — see
             * WicketTracker.onRotation — and the difference restarts from this frame.
             */
            if (previousTurn != null && previousTurn != turn) {
                previous?.release()
                previous = null
            }
            previousTurn = turn

            val prev = previous
            if (prev == null) {
                previous = gray
                framesUnresolved++
                return FrameMotion.STILL
            }

            val aspect = gray.cols().toFloat() / gray.rows().coerceAtLeast(1)
            val motion = fit(prev, gray, aspect)
            prev.release()
            previous = gray

            if (motion.isUsable) framesEstimated++ else framesUnresolved++
            motion
        } catch (t: Throwable) {
            Log.w(TAG, "camera motion estimate failed", t)
            framesUnresolved++
            FrameMotion.STILL
        }
    }

    private fun fit(prev: Mat, next: Mat, aspect: Float): FrameMotion {
        val corners = MatOfPoint()
        val from = MatOfPoint2f()
        val to = MatOfPoint2f()
        val status = MatOfByte()
        val error = MatOfFloat()
        try {
            Imgproc.goodFeaturesToTrack(
                prev,
                corners,
                MAX_CORNERS,
                CORNER_QUALITY,
                MIN_CORNER_SEPARATION,
            )
            val seeds = corners.toArray()
            if (seeds.size < CameraMotion.MIN_CORRESPONDENCES) return FrameMotion.STILL
            from.fromArray(*seeds)

            Video.calcOpticalFlowPyrLK(
                prev,
                next,
                from,
                to,
                status,
                error,
                Size(FLOW_WINDOW, FLOW_WINDOW),
                FLOW_LEVELS,
                TermCriteria(TermCriteria.COUNT or TermCriteria.EPS, 20, 0.03),
            )

            val before = from.toArray()
            val after = to.toArray()
            val flags = status.toArray()
            val errors = error.toArray()

            val w = prev.cols().toFloat()
            val h = prev.rows().toFloat()
            if (w <= 0f || h <= 0f) return FrameMotion.STILL

            val src = ArrayList<Point2>(before.size)
            val dst = ArrayList<Point2>(before.size)
            for (i in before.indices) {
                // Lost by the flow itself, or followed so badly it is guessing. Both are
                // the tracker saying it does not know, and a point it does not know is
                // worse than no point — it is a wrong pair that still votes.
                if (flags.getOrNull(i)?.toInt() != 1) continue
                if ((errors.getOrNull(i) ?: Float.MAX_VALUE) > MAX_FLOW_ERROR) continue
                val a = before[i]
                val b = after.getOrNull(i) ?: continue
                if (!inFrame(b, w, h)) continue
                src.add(Point2(a.x / w, a.y / h))
                dst.add(Point2(b.x / w, b.y / h))
            }

            if (src.size < CameraMotion.MIN_CORRESPONDENCES) return FrameMotion.STILL
            return CameraMotion.estimate(src, dst, aspect)
        } finally {
            corners.release()
            from.release()
            to.release()
            status.release()
            error.release()
        }
    }

    private fun inFrame(p: Point, w: Float, h: Float) =
        p.x >= 0.0 && p.y >= 0.0 && p.x < w && p.y < h

    /**
     * Luma bytes to an upright greyscale Mat at the analysis width.
     *
     * The same shape of work [OpenCvStumpDetector] does, and the same refusal on a short
     * buffer, for the same reason: into a reused buffer the missing rows are last frame's,
     * and stale rows produce correspondences that all agree the camera did not move.
     */
    private fun toUpright(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        turn: Int,
    ): Mat? {
        val packed = if (rowStride == width) {
            luma
        } else {
            val out = scratchPacked?.takeIf { it.size == width * height }
                ?: ByteArray(width * height).also { scratchPacked = it }
            for (row in 0 until height) {
                val from = row * rowStride
                if (from + width > luma.size) return null
                System.arraycopy(luma, from, out, row * width, width)
            }
            out
        }

        val full = scratchFull?.takeIf { it.rows() == height && it.cols() == width }
            ?: Mat(height, width, org.opencv.core.CvType.CV_8UC1).also {
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

        return when (turn) {
            90 -> Mat().also { org.opencv.core.Core.rotate(small, it, org.opencv.core.Core.ROTATE_90_CLOCKWISE); small.release() }
            180 -> Mat().also { org.opencv.core.Core.rotate(small, it, org.opencv.core.Core.ROTATE_180); small.release() }
            270 -> Mat().also { org.opencv.core.Core.rotate(small, it, org.opencv.core.Core.ROTATE_90_COUNTERCLOCKWISE); small.release() }
            else -> small
        }
    }

    /** Forget the previous frame. The next one produces no motion, by design. */
    fun reset() {
        previous?.release()
        previous = null
        previousTurn = null
        framesEstimated = 0
        framesUnresolved = 0
    }

    fun release() {
        released = true
        previous?.release()
        previous = null
        scratchFull?.release()
        scratchFull = null
        scratchPacked = null
    }

    private companion object {
        const val TAG = "OpenCvCameraMotion"

        /**
         * Enough to outvote the batter, the bowler and the ball several times over, and
         * few enough that Lucas-Kanade stays in the low milliseconds.
         */
        const val MAX_CORNERS = 120

        /** Fraction of the best corner's score a corner must reach to be used at all. */
        const val CORNER_QUALITY = 0.01

        /**
         * Pixels between corners at the analysis width.
         *
         * Spread matters more than count. A hundred corners clustered on one sightscreen
         * measure that sightscreen; twenty spread across the frame measure the camera, and
         * a rotation or a zoom is only visible in points that are far apart.
         */
        const val MIN_CORNER_SEPARATION = 8.0

        const val FLOW_WINDOW = 21.0
        const val FLOW_LEVELS = 3

        /** Lucas-Kanade's own residual. Above this the point was followed into mush. */
        const val MAX_FLOW_ERROR = 20f
    }
}
