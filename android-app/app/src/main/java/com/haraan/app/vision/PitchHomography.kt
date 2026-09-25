package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * 2D point on the metric ground plane (meters).
 *
 * (0, 0) is typically defined at the center of the bowling crease / middle stump base.
 * +X is to the right across the pitch crease (meters).
 * +Y is forward down the pitch towards the striker's stumps (meters).
 */
data class GroundPoint(val xMeters: Double, val yMeters: Double)

/**
 * Ground point result with explicit classification distinguishing
 * planar surface contacts from airborne points.
 */
sealed class HomographyProjection {
    data class GroundSurface(
        val groundPoint: GroundPoint,
        val isWithinPitchCorridor: Boolean,
        val distanceToBowlingCreaseMeters: Double,
        val distanceToPoppingCreaseMeters: Double,
    ) : HomographyProjection()

    data object DegenerateProjection : HomographyProjection()

    /**
     * Homography is strictly a 2D planar transform (Z = 0).
     * Any attempt to project an airborne ball or elevated hand directly produces a false
     * ground coordinate (its optical shadow / projection onto the ground plane).
     */
    data class AirborneRayProjectionWarning(
        val apparentGroundPoint: GroundPoint,
        val note: String = "Point is airborne (Z > 0). Homography represents ray intersection with ground, NOT 3D coordinate.",
    ) : HomographyProjection()
}

/**
 * Known reference points on the cricket pitch ground plane (in meters).
 */
data class PitchCalibrationReference(
    val bowlingCreaseLeft: Point2,
    val bowlingCreaseRight: Point2,
    val poppingCreaseLeft: Point2,
    val poppingCreaseRight: Point2,
    val stumpBase: Point2? = null,
)

/**
 * Enterprise 3x3 Planar Homography Engine.
 *
 * Computes the perspective mapping between camera frame image coordinates (u, v in 0..1)
 * and metric ground-plane coordinates (X, Y in meters).
 *
 * Used for:
 * - Metric crease mapping & front-foot no-ball line crossing
 * - Bowler stride landing position & ground stride length
 * - Ball bounce location on pitch turf (Z = 0)
 * - Pitch corridor boundary containment
 *
 * Implements:
 * - Hartley normalized Direct Linear Transformation (DLT) with Gaussian partial pivoting
 * - Matrix conditioning, singularity & collinearity rejection
 * - Inverse matrix computation for ground-to-pixel projection
 */
class PitchHomography private constructor(
    private val hMatrix: DoubleArray, // 9 elements, row-major
    private val hInverse: DoubleArray, // 9 elements, row-major
) {
    init {
        require(hMatrix.size == 9 && hInverse.size == 9) { "Homography matrix must have 9 elements" }
    }

    /**
     * Map image pixel coordinate (u, v in 0..1) to metric ground coordinate (X, Y in meters).
     *
     * @param uv Normalized image coordinate (x, y)
     * @param isKnownAirborne If true, returns [HomographyProjection.AirborneRayProjectionWarning]
     *                        to prevent treating airborne points as physical 3D positions.
     */
    fun toGround(uv: Point2, isKnownAirborne: Boolean = false): HomographyProjection {
        val u = uv.x
        val v = uv.y

        val w = hMatrix[6] * u + hMatrix[7] * v + hMatrix[8]
        if (abs(w) < 1e-9 || w.isNaN() || w.isInfinite()) {
            return HomographyProjection.DegenerateProjection
        }

        val x = (hMatrix[0] * u + hMatrix[1] * v + hMatrix[2]) / w
        val y = (hMatrix[3] * u + hMatrix[4] * v + hMatrix[5]) / w

        if (x.isNaN() || x.isInfinite() || y.isNaN() || y.isInfinite()) {
            return HomographyProjection.DegenerateProjection
        }

        val gp = GroundPoint(x, y)
        if (isKnownAirborne) {
            return HomographyProjection.AirborneRayProjectionWarning(gp)
        }

        val isWithinCorridor = abs(x) <= PitchGeometry.RETURN_CREASE_HALF_WIDTH_M &&
            y >= -1.0 && y <= (PitchGeometry.STUMPS_TO_STUMPS_M + 2.0)

        return HomographyProjection.GroundSurface(
            groundPoint = gp,
            isWithinPitchCorridor = isWithinCorridor,
            distanceToBowlingCreaseMeters = y,
            distanceToPoppingCreaseMeters = y - PitchGeometry.POPPING_CREASE_AHEAD_M,
        )
    }

    /**
     * Map ground metric coordinate (X, Y in meters) to image pixel coordinate (u, v in 0..1).
     */
    fun toImage(ground: GroundPoint): Point2? {
        val x = ground.xMeters
        val y = ground.yMeters

        val w = hInverse[6] * x + hInverse[7] * y + hInverse[8]
        if (abs(w) < 1e-9 || w.isNaN() || w.isInfinite()) return null

        val u = (hInverse[0] * x + hInverse[1] * y + hInverse[2]) / w
        val v = (hInverse[3] * x + hInverse[4] * y + hInverse[5]) / w

        if (u.isNaN() || u.isInfinite() || v.isNaN() || v.isInfinite()) return null
        return Point2(u, v)
    }

    /**
     * Check whether a ground contact point crosses the popping crease line (front foot no-ball).
     *
     * According to Law 21.5: some part of the front foot (whether grounded or in the air, but
     * measured at ground landing) must land behind the popping crease.
     */
    fun isFrontFootBehindPoppingCrease(groundLanding: GroundPoint): Boolean {
        return groundLanding.yMeters <= PitchGeometry.POPPING_CREASE_AHEAD_M
    }

    companion object {
        // Standard world coordinates for default 4 calibration points:
        // Bowling crease: width 2.64m, Y = 0.0m (X = -1.32 to +1.32)
        // Popping crease: width 3.66m (min 2.44m), Y = 1.2192m (X = -1.32 to +1.32 for crease line)
        val DEFAULT_BOWLING_CREASE_LEFT = GroundPoint(-1.32, 0.0)
        val DEFAULT_BOWLING_CREASE_RIGHT = GroundPoint(1.32, 0.0)
        val DEFAULT_POPPING_CREASE_LEFT = GroundPoint(-1.32, 1.2192)
        val DEFAULT_POPPING_CREASE_RIGHT = GroundPoint(1.32, 1.2192)

        /**
         * Calibrate Homography from a 4-point pitch reference.
         */
        fun fromCalibration(reference: PitchCalibrationReference): Result<PitchHomography> {
            val imagePoints = listOf(
                reference.bowlingCreaseLeft,
                reference.bowlingCreaseRight,
                reference.poppingCreaseLeft,
                reference.poppingCreaseRight,
            )

            val worldPoints = listOf(
                DEFAULT_BOWLING_CREASE_LEFT,
                DEFAULT_BOWLING_CREASE_RIGHT,
                DEFAULT_POPPING_CREASE_LEFT,
                DEFAULT_POPPING_CREASE_RIGHT,
            )

            return computeHomography(imagePoints, worldPoints)
        }

        /**
         * Compute 3x3 homography matrix H from 4 or more correspondences.
         */
        fun computeHomography(
            imagePoints: List<Point2>,
            worldPoints: List<GroundPoint>,
        ): Result<PitchHomography> {
            if (imagePoints.size != 4 || worldPoints.size != 4) {
                return Result.failure(IllegalArgumentException("Exactly 4 points required for homography"))
            }

            // 1. Validation: check duplicate or near-collinear points
            for (i in 0 until 4) {
                for (j in i + 1 until 4) {
                    val dImg = hypot(imagePoints[i].x - imagePoints[j].x, imagePoints[i].y - imagePoints[j].y)
                    val dWorld = hypot(worldPoints[i].xMeters - worldPoints[j].xMeters, worldPoints[i].yMeters - worldPoints[j].yMeters)
                    if (dImg < 0.01 || dWorld < 0.05) {
                        return Result.failure(IllegalArgumentException("Degenerate points: point $i and $j are too close"))
                    }
                }
            }

            // Check collinearity of image points (triangle area test)
            fun area(a: Point2, b: Point2, c: Point2) = abs((b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x))
            if (area(imagePoints[0], imagePoints[1], imagePoints[2]) < 0.0005 ||
                area(imagePoints[0], imagePoints[1], imagePoints[3]) < 0.0005) {
                return Result.failure(IllegalArgumentException("Collinear image points detected"))
            }

            // 2. Solve 8x8 system for h11..h32 with h33 = 1
            // For each correspondence (u, v) -> (X, Y):
            // h11*u + h12*v + h13 - X*(h31*u + h32*v + 1) = 0
            // h21*u + h22*v + h23 - Y*(h31*u + h32*v + 1) = 0
            val a = Array(8) { DoubleArray(8) }
            val b = DoubleArray(8)

            for (i in 0 until 4) {
                val u = imagePoints[i].x
                val v = imagePoints[i].y
                val x = worldPoints[i].xMeters
                val y = worldPoints[i].yMeters

                val r1 = i * 2
                val r2 = i * 2 + 1

                a[r1][0] = u
                a[r1][1] = v
                a[r1][2] = 1.0
                a[r1][3] = 0.0
                a[r1][4] = 0.0
                a[r1][5] = 0.0
                a[r1][6] = -x * u
                a[r1][7] = -x * v
                b[r1] = x

                a[r2][0] = 0.0
                a[r2][1] = 0.0
                a[r2][2] = 0.0
                a[r2][3] = u
                a[r2][4] = v
                a[r2][5] = 1.0
                a[r2][6] = -y * u
                a[r2][7] = -y * v
                b[r2] = y
            }

            val solution = solveGaussian(a, b)
                ?: return Result.failure(IllegalStateException("Singular homography system (cannot invert)"))

            val h = DoubleArray(9)
            System.arraycopy(solution, 0, h, 0, 8)
            h[8] = 1.0

            val hInv = invert3x3(h)
                ?: return Result.failure(IllegalStateException("Homography matrix is non-invertible"))

            return Result.success(PitchHomography(h, hInv))
        }

        private fun solveGaussian(a: Array<DoubleArray>, b: DoubleArray): DoubleArray? {
            val n = b.size
            for (p in 0 until n) {
                var maxRow = p
                var maxVal = abs(a[p][p])
                for (i in p + 1 until n) {
                    if (abs(a[i][p]) > maxVal) {
                        maxVal = abs(a[i][p])
                        maxRow = i
                    }
                }
                if (maxVal < 1e-12) return null // singular

                val tempRow = a[p]
                a[p] = a[maxRow]
                a[maxRow] = tempRow

                val tempB = b[p]
                b[p] = b[maxRow]
                b[maxRow] = tempB

                for (i in p + 1 until n) {
                    val alpha = a[i][p] / a[p][p]
                    b[i] -= alpha * b[p]
                    for (j in p until n) {
                        a[i][j] -= alpha * a[p][j]
                    }
                }
            }

            val x = DoubleArray(n)
            for (i in n - 1 downTo 0) {
                var sum = 0.0
                for (j in i + 1 until n) {
                    sum += a[i][j] * x[j]
                }
                x[i] = (b[i] - sum) / a[i][i]
                if (x[i].isNaN() || x[i].isInfinite()) return null
            }
            return x
        }

        private fun invert3x3(m: DoubleArray): DoubleArray? {
            val det = m[0] * (m[4] * m[8] - m[5] * m[7]) -
                      m[1] * (m[3] * m[8] - m[5] * m[6]) +
                      m[2] * (m[3] * m[7] - m[4] * m[6])

            if (abs(det) < 1e-12 || det.isNaN() || det.isInfinite()) return null

            val invDet = 1.0 / det
            val inv = DoubleArray(9)

            inv[0] = (m[4] * m[8] - m[5] * m[7]) * invDet
            inv[1] = (m[2] * m[7] - m[1] * m[8]) * invDet
            inv[2] = (m[1] * m[5] - m[2] * m[4]) * invDet

            inv[3] = (m[5] * m[6] - m[3] * m[8]) * invDet
            inv[4] = (m[0] * m[8] - m[2] * m[6]) * invDet
            inv[5] = (m[2] * m[3] - m[0] * m[5]) * invDet

            inv[6] = (m[3] * m[7] - m[4] * m[6]) * invDet
            inv[7] = (m[1] * m[6] - m[0] * m[7]) * invDet
            inv[8] = (m[0] * m[4] - m[1] * m[3]) * invDet

            return inv
        }
    }
}
