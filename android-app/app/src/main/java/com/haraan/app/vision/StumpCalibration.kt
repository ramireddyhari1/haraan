package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.cos
import kotlin.math.sin
import kotlin.math.sqrt

/**
 * CALIBRATING FROM THE TWO SETS OF STUMPS.
 *
 * The crease corners are paint, and on the grounds this app is used on the paint is faded,
 * mown off or was never there. The stumps are always there, always the same size, and at
 * both ends. Six taps — the feet of the two outer stumps and the top of the middle one, at
 * the near end and then the far end — fix the camera completely: where it stands, how high,
 * where it is pointing and how far it is zoomed in.
 *
 * WHY SIX AND NOT FOUR. The obvious four — the two middle stumps' feet and tops — all lie in
 * one vertical plane down the middle of the pitch, and a phone filming from behind either
 * end is standing in that plane: it sees it edge-on, both stumps on one vertical line, and
 * there is nothing to solve. The outer feet put width into it and the tops put height, so
 * the six points are a solid box rather than a sheet, and the camera is pinned.
 *
 * HOW. A rough camera from a standing guess (behind the near stumps, chest height, looking
 * at the far ones), then Levenberg–Marquardt over the camera's seven unknowns — focal length,
 * three angles, three position — until the six points project on to the six taps. The
 * result is turned into the crease-corner [PitchQuad] the rest of the pipeline already
 * speaks, by projecting the creases through the solved camera, so nothing downstream
 * changes.
 *
 * Plain Kotlin, no OpenCV: provable at a desk against a simulated lens.
 */
object StumpCalibration {

    /**
     * The six taps, in normalised upright image coordinates. Left and right are as they
     * appear IN THE PICTURE, whichever end the phone is at — the operator never has to think
     * about which is off and which is leg.
     */
    data class Taps(
        val nearLeftFoot: Point2,
        val nearRightFoot: Point2,
        val nearMiddleTop: Point2,
        val farLeftFoot: Point2,
        val farRightFoot: Point2,
        val farMiddleTop: Point2,
    ) {
        val all: List<Point2> get() = listOf(nearLeftFoot, nearRightFoot, nearMiddleTop, farLeftFoot, farRightFoot, farMiddleTop)
    }

    /**
     * What the six taps came to: the crease quad for the rest of the pipeline, how high the
     * camera is, and how well the taps agreed with one camera — a large [rmsError] means a
     * tap landed on the wrong thing.
     */
    data class Result(val quad: PitchQuad, val cameraHeightM: Double, val rmsError: Double) {
        /** Taps that agree to within about one percent of the frame. */
        val trustworthy: Boolean get() = rmsError <= MAX_RMS
    }

    /** About a percent of the frame height: beyond this one of the taps is on the wrong thing. */
    const val MAX_RMS = 0.012

    /** The names of the taps in order, for the prompts. */
    val PROMPTS = listOf(
        "Near stumps: tap the foot of the LEFT stump",
        "Near stumps: tap the foot of the RIGHT stump",
        "Near stumps: tap the TOP of the middle stump",
        "Far stumps: tap the foot of the LEFT stump",
        "Far stumps: tap the foot of the RIGHT stump",
        "Far stumps: tap the TOP of the middle stump",
    )

    private const val STUMP_X = PitchGeometry.STUMP_SET_WIDTH_M / 2.0 - 0.0175

    fun solve(taps: Taps, end: CameraEnd, frameAspect: Float): Result? {
        val aspect = if (frameAspect > 0f) frameAspect.toDouble() else return null
        val world = worldPoints(end)
        val image = taps.all

        // Try a couple of standing guesses and keep whichever the solver settles best from.
        var best: Cam? = null
        var bestErr = Double.MAX_VALUE
        for (height in listOf(1.6, 2.4)) {
            for (behind in listOf(3.0, 8.0)) {
                val start = guess(end, height, behind) ?: continue
                val solved = refine(start, world, image, aspect) ?: continue
                val err = rms(solved, world, image, aspect)
                if (err < bestErr) {
                    bestErr = err
                    best = solved
                }
            }
        }
        val cam = best ?: return null
        val height = cam.c[2]
        if (height < 0.3 || height > 30.0) return null

        val corners = PitchGeometry.calibrationCorners(end).map { c ->
            project(cam, Point3(c.x, c.y, 0.0), aspect) ?: return null
        }
        return Result(
            quad = PitchQuad(corners, QuadSource.TAPPED, 1f, end),
            cameraHeightM = height,
            rmsError = bestErr,
        )
    }

    /** Where the six tapped points really are, in pitch metres, for a camera at [end]. */
    internal fun worldPoints(end: CameraEnd): List<Point3> {
        val nearY = if (end == CameraEnd.BOWLER) PitchGeometry.STUMPS_TO_STUMPS_M else 0.0
        val farY = if (end == CameraEnd.BOWLER) 0.0 else PitchGeometry.STUMPS_TO_STUMPS_M
        // Picture-left is -x from the bowler's end (x is right as seen from there), and +x
        // from the batter's end, where the picture is the other way round.
        val left = if (end == CameraEnd.BOWLER) -STUMP_X else STUMP_X
        val top = PitchGeometry.STUMP_HEIGHT_M
        return listOf(
            Point3(left, nearY, 0.0),
            Point3(-left, nearY, 0.0),
            Point3(0.0, nearY, top),
            Point3(left, farY, 0.0),
            Point3(-left, farY, 0.0),
            Point3(0.0, farY, top),
        )
    }

    // ── A small pinhole camera: R (world→camera, rows), centre, focal in frame heights ──

    private class Cam(val r: Array<DoubleArray>, val c: DoubleArray, val f: Double)

    private fun project(cam: Cam, p: Point3, aspect: Double): Point2? {
        val d = doubleArrayOf(p.x - cam.c[0], p.y - cam.c[1], p.z - cam.c[2])
        val x = cam.r[0][0] * d[0] + cam.r[0][1] * d[1] + cam.r[0][2] * d[2]
        val y = cam.r[1][0] * d[0] + cam.r[1][1] * d[1] + cam.r[1][2] * d[2]
        val z = cam.r[2][0] * d[0] + cam.r[2][1] * d[1] + cam.r[2][2] * d[2]
        if (z <= 1e-6) return null
        return Point2(cam.f * x / z / aspect + 0.5, cam.f * y / z + 0.5)
    }

    /** Behind the near stumps at [height], [behind] metres back, looking at the far ones. */
    private fun guess(end: CameraEnd, height: Double, behind: Double): Cam? {
        val nearY = if (end == CameraEnd.BOWLER) PitchGeometry.STUMPS_TO_STUMPS_M else 0.0
        val farY = if (end == CameraEnd.BOWLER) 0.0 else PitchGeometry.STUMPS_TO_STUMPS_M
        val away = if (end == CameraEnd.BOWLER) 1.0 else -1.0
        val eye = doubleArrayOf(0.0, nearY + away * behind, height)
        val target = doubleArrayOf(0.0, (nearY + farY) / 2, 0.0)
        val fwd = unit(DoubleArray(3) { target[it] - eye[it] })
        val right = unit(cross(doubleArrayOf(0.0, 0.0, 1.0), fwd))
        val down = cross(right, fwd)
        return Cam(arrayOf(right, down, fwd), eye, 1.3)
    }

    private fun residuals(cam: Cam, world: List<Point3>, image: List<Point2>, aspect: Double): DoubleArray? {
        val out = DoubleArray(world.size * 2)
        for (i in world.indices) {
            val p = project(cam, world[i], aspect) ?: return null
            // In frame-height units on both axes, so a pixel counts the same either way.
            out[2 * i] = (p.x - image[i].x) * aspect
            out[2 * i + 1] = p.y - image[i].y
        }
        return out
    }

    private fun rms(cam: Cam, world: List<Point3>, image: List<Point2>, aspect: Double): Double {
        val r = residuals(cam, world, image, aspect) ?: return Double.MAX_VALUE
        return sqrt(r.sumOf { it * it } / (r.size / 2))
    }

    /** Seven parameters: log focal, a small rotation (camera frame), a move of the centre. */
    private fun apply(cam: Cam, d: DoubleArray): Cam {
        val rot = rodrigues(d[1], d[2], d[3])
        val r = Array(3) { i -> DoubleArray(3) { j -> (0..2).sumOf { k -> rot[i][k] * cam.r[k][j] } } }
        return Cam(r, doubleArrayOf(cam.c[0] + d[4], cam.c[1] + d[5], cam.c[2] + d[6]), cam.f * kotlin.math.exp(d[0]))
    }

    private fun refine(start: Cam, world: List<Point3>, image: List<Point2>, aspect: Double): Cam? {
        var cam = start
        var r = residuals(cam, world, image, aspect) ?: return null
        var cost = r.sumOf { it * it }
        var lambda = 1e-2
        repeat(80) {
            val n = 7
            val m = r.size
            val jac = Array(m) { DoubleArray(n) }
            for (k in 0 until n) {
                val h = if (k == 0) 1e-5 else if (k <= 3) 1e-6 else 1e-5
                val d = DoubleArray(n).also { it[k] = h }
                val rp = residuals(apply(cam, d), world, image, aspect) ?: return@repeat
                for (i in 0 until m) jac[i][k] = (rp[i] - r[i]) / h
            }
            val jtj = Array(n) { a -> DoubleArray(n) { b -> (0 until m).sumOf { jac[it][a] * jac[it][b] } } }
            val jtr = DoubleArray(n) { a -> (0 until m).sumOf { jac[it][a] * r[it] } }
            var improved = false
            for (attempt in 0 until 8) {
                val damped = Array(n) { a -> DoubleArray(n) { b -> jtj[a][b] + if (a == b) lambda * (1 + jtj[a][a]) else 0.0 } }
                val step = solve(damped, DoubleArray(n) { -jtr[it] }) ?: break
                val next = apply(cam, step)
                val rn = residuals(next, world, image, aspect)
                val cn = rn?.sumOf { it * it } ?: Double.MAX_VALUE
                if (cn < cost) {
                    cam = next
                    r = rn!!
                    val gain = cost - cn
                    cost = cn
                    lambda = (lambda / 3).coerceAtLeast(1e-9)
                    improved = true
                    if (gain < 1e-16) return cam
                    break
                }
                lambda *= 4
            }
            if (!improved) return cam
        }
        return cam
    }

    // ── Small linear algebra ──

    private fun rodrigues(x: Double, y: Double, z: Double): Array<DoubleArray> {
        val t = sqrt(x * x + y * y + z * z)
        if (t < 1e-12) return arrayOf(doubleArrayOf(1.0, -z, y), doubleArrayOf(z, 1.0, -x), doubleArrayOf(-y, x, 1.0))
        val kx = x / t
        val ky = y / t
        val kz = z / t
        val c = cos(t)
        val s = sin(t)
        val v = 1 - c
        return arrayOf(
            doubleArrayOf(c + kx * kx * v, kx * ky * v - kz * s, kx * kz * v + ky * s),
            doubleArrayOf(ky * kx * v + kz * s, c + ky * ky * v, ky * kz * v - kx * s),
            doubleArrayOf(kz * kx * v - ky * s, kz * ky * v + kx * s, c + kz * kz * v),
        )
    }

    private fun solve(a: Array<DoubleArray>, b: DoubleArray): DoubleArray? {
        val n = b.size
        val m = Array(n) { r -> DoubleArray(n + 1) { k -> if (k < n) a[r][k] else b[r] } }
        for (col in 0 until n) {
            val pivot = (col until n).maxByOrNull { abs(m[it][col]) } ?: return null
            if (abs(m[pivot][col]) < 1e-18) return null
            val tmp = m[col]; m[col] = m[pivot]; m[pivot] = tmp
            for (row in 0 until n) {
                if (row == col) continue
                val f = m[row][col] / m[col][col]
                if (f == 0.0) continue
                for (k in col..n) m[row][k] -= f * m[col][k]
            }
        }
        return DoubleArray(n) { m[it][n] / m[it][it] }
    }

    private fun dot(a: DoubleArray, b: DoubleArray) = a[0] * b[0] + a[1] * b[1] + a[2] * b[2]
    private fun unit(a: DoubleArray): DoubleArray = sqrt(dot(a, a)).let { n -> DoubleArray(3) { a[it] / n } }
    private fun cross(a: DoubleArray, b: DoubleArray) = doubleArrayOf(
        a[1] * b[2] - a[2] * b[1],
        a[2] * b[0] - a[0] * b[2],
        a[0] * b[1] - a[1] * b[0],
    )
}
