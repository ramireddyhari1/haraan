package com.haraan.app.vision

import kotlin.math.abs

/**
 * The pitch, in metres, and the map between what the camera sees and where things are.
 *
 * WHY THIS IS NOT OPENCV. Solving a four-point homography is an eight-by-eight linear
 * system — a page of arithmetic, not a computer-vision problem. Written here it runs on
 * the JVM, which means it can be unit-tested at a desk with exact expected answers. The
 * ball detector cannot: it needs the native library, so it can only be exercised on a
 * device. Keeping the geometry out of OpenCV is what makes the measurable half of this
 * pipeline provable.
 */
object PitchGeometry {

    /*
     * Cricket's own dimensions, from the Laws. These are the reason a single camera can
     * measure anything at all: the pitch is a known rectangle, so four points in an image
     * are enough to recover the plane it lies on.
     *
     *   Stumps to stumps            20.12 m  (22 yards)
     *   Popping crease in front of stumps   1.22 m  (4 ft)
     *   Return crease from middle stump     1.32 m  (4 ft 4 in) each side
     *   Stump width                  0.2286 m (9 in)
     */
    const val STUMPS_TO_STUMPS_M = 20.12
    const val POPPING_CREASE_AHEAD_M = 1.22
    const val RETURN_CREASE_HALF_WIDTH_M = 1.32

    /**
     * The calibration rectangle: the four points where the popping creases meet the
     * return creases.
     *
     * Chosen because they are painted, high-contrast, and actually a rectangle — and
     * because from behind the bowler all four are usually in frame, which the stumps
     * themselves are not once a batter is standing over them.
     */
    const val CALIBRATION_WIDTH_M = RETURN_CREASE_HALF_WIDTH_M * 2                    // 2.64
    const val CALIBRATION_LENGTH_M = STUMPS_TO_STUMPS_M - (POPPING_CREASE_AHEAD_M * 2) // 17.68

    /**
     * Pitch coordinates for the four calibration corners, in the order a caller must
     * supply the image points.
     *
     * Origin is the STRIKER'S middle stump. x runs across the pitch, y runs down it away
     * from the striker. So a bounce at y = 6.0 is six metres from the batter's stumps,
     * which is how length is spoken about — "he's pitching it six metres up".
     */
    val CALIBRATION_CORNERS_M = listOf(
        // near-left, near-right, far-right, far-left — clockwise from the striker's left
        Point2(-RETURN_CREASE_HALF_WIDTH_M, -POPPING_CREASE_AHEAD_M),
        Point2(RETURN_CREASE_HALF_WIDTH_M, -POPPING_CREASE_AHEAD_M),
        Point2(RETURN_CREASE_HALF_WIDTH_M, CALIBRATION_LENGTH_M - POPPING_CREASE_AHEAD_M),
        Point2(-RETURN_CREASE_HALF_WIDTH_M, CALIBRATION_LENGTH_M - POPPING_CREASE_AHEAD_M),
    )
}

/** A point in whichever plane the caller is working in. Metres, or normalised 0..1. */
data class Point2(val x: Double, val y: Double)

/**
 * A plane-to-plane projective map, solved from exactly four point pairs.
 *
 * This is what turns "a dot in a picture" into "six metres from the stumps". It is only
 * valid for points ON the plane it was solved for — a bounce is on the pitch, so it comes
 * out exactly; a ball in flight is above the pitch and does NOT, which is why nothing here
 * offers to map one.
 */
class Homography private constructor(private val h: DoubleArray) {

    /** Map a point from the source plane to the destination plane. */
    fun map(point: Point2): Point2 {
        val denominator = h[6] * point.x + h[7] * point.y + 1.0
        if (abs(denominator) < 1e-12) {
            // The point sits on the horizon line of this mapping — it projects to
            // infinity, and there is no honest finite answer.
            return Point2(Double.NaN, Double.NaN)
        }
        return Point2(
            (h[0] * point.x + h[1] * point.y + h[2]) / denominator,
            (h[3] * point.x + h[4] * point.y + h[5]) / denominator,
        )
    }

    companion object {
        /**
         * Solve for the map taking [from] to [to]. Both lists must hold exactly four
         * points, in the same order.
         *
         * Returns null when the four points cannot define a projection — three of them
         * collinear, or two coincident. That is a real outcome rather than an error: a
         * detector that found a bad quad must be told no, not handed a matrix full of
         * enormous numbers that maps everything into nonsense.
         */
        fun solve(from: List<Point2>, to: List<Point2>): Homography? {
            if (from.size != 4 || to.size != 4) return null

            // Eight unknowns: h11 h12 h13 h21 h22 h23 h31 h32, with h33 fixed at 1.
            // Each correspondence contributes two rows.
            val a = Array(8) { DoubleArray(9) }
            for (i in 0 until 4) {
                val (u, v) = from[i]
                val (x, y) = to[i]

                val rowX = a[i * 2]
                rowX[0] = u; rowX[1] = v; rowX[2] = 1.0
                rowX[6] = -u * x; rowX[7] = -v * x; rowX[8] = x

                val rowY = a[i * 2 + 1]
                rowY[3] = u; rowY[4] = v; rowY[5] = 1.0
                rowY[6] = -u * y; rowY[7] = -v * y; rowY[8] = y
            }

            val solution = gaussianSolve(a) ?: return null
            return Homography(solution)
        }

        /**
         * Gaussian elimination with partial pivoting.
         *
         * Pivoting is not optional here. Without it a quad whose first image point sits at
         * x = 0 puts a zero on the diagonal and the whole solve divides by it — and the
         * near-left corner of a centred pitch is exactly the kind of point that lands
         * there.
         */
        private fun gaussianSolve(m: Array<DoubleArray>): DoubleArray? {
            val n = 8
            for (col in 0 until n) {
                var pivot = col
                for (row in col + 1 until n) {
                    if (abs(m[row][col]) > abs(m[pivot][col])) pivot = row
                }
                if (abs(m[pivot][col]) < 1e-10) {
                    // Singular: the points are degenerate.
                    return null
                }
                val tmp = m[col]; m[col] = m[pivot]; m[pivot] = tmp

                for (row in 0 until n) {
                    if (row == col) continue
                    val factor = m[row][col] / m[col][col]
                    if (factor == 0.0) continue
                    for (k in col..n) {
                        m[row][k] -= factor * m[col][k]
                    }
                }
            }
            return DoubleArray(n) { i -> m[i][n] / m[i][i] }
        }
    }
}

/**
 * Where the pitch is in the frame, and how we came to know.
 *
 * [corners] are normalised image coordinates in the same order as
 * [PitchGeometry.CALIBRATION_CORNERS_M]. [source] is the provenance rule the rest of the
 * pipeline already follows: a quad a person tapped and a quad a detector guessed at are
 * both usable, and a later measurement must always be able to say which it stood on.
 */
data class PitchQuad(
    val corners: List<Point2>,
    val source: QuadSource,
    /** 0..1, how strongly the detector believed it. Always 1.0 for a tapped quad. */
    val confidence: Float,
) {
    /**
     * The map from image to pitch metres, or null when these corners cannot define one.
     */
    fun toPitch(): Homography? =
        Homography.solve(corners, PitchGeometry.CALIBRATION_CORNERS_M)

    /** The map back, for drawing pitch-space guides onto the camera preview. */
    fun toImage(): Homography? =
        Homography.solve(PitchGeometry.CALIBRATION_CORNERS_M, corners)

    /**
     * A quad has to look like a pitch seen from behind the bowler before it is worth
     * solving: four points in convex order, the far edge narrower than the near one, and
     * some actual area. A detector will happily hand back four collinear points off a
     * boundary rope otherwise.
     */
    fun isPlausible(): Boolean {
        if (corners.size != 4) return false
        if (corners.any { it.x.isNaN() || it.y.isNaN() }) return false

        val nearWidth = abs(corners[1].x - corners[0].x)
        val farWidth = abs(corners[2].x - corners[3].x)
        if (nearWidth <= 0.02 || farWidth <= 0.005) return false
        // Perspective: the far crease must appear narrower than the near one.
        if (farWidth >= nearWidth) return false

        return polygonArea() > MIN_AREA
    }

    private fun polygonArea(): Double {
        var sum = 0.0
        for (i in corners.indices) {
            val a = corners[i]
            val b = corners[(i + 1) % corners.size]
            sum += a.x * b.y - b.x * a.y
        }
        return abs(sum) / 2.0
    }

    private companion object {
        /** Below this the "pitch" is a sliver, not a surface. */
        const val MIN_AREA = 0.01
    }
}

enum class QuadSource {
    /** Found by the detector in the camera frame. */
    DETECTED,

    /** Placed by a person tapping the four corners. Trusted above a detection. */
    TAPPED,
}
