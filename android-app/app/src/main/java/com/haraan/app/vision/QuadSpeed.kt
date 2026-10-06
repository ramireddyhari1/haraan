package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * The phone itself, recovered from the four pitch corners.
 *
 * A homography from the pitch to the picture is the camera's lens times its pose, squashed
 * together. With two assumptions every phone camera satisfies well enough — square pixels,
 * and the optical centre in the middle of the frame — the two can be pulled apart again:
 * the pitch's own right angle and its known proportions fix the focal length, and from that
 * the camera's position over the pitch and the way it is pointing follow. (Zhang's
 * single-plane calibration, with one unknown instead of five.)
 *
 * What that buys is a RAY for every sighting: the line in the world the ball must have been
 * on. That is what a homography alone could never give — it only answers for points on the
 * ground, and the ball is in the air.
 *
 * Image coordinates here are the package's usual normalised upright ones; internally they
 * are centred and put in frame-height units so x and y are the same unit.
 */
internal class PitchCamera private constructor(
    /** Focal length in frame heights. */
    val focal: Double,
    /** World axes in camera coordinates, as the columns of R: x across, y up the pitch, z. */
    private val r: Array<DoubleArray>,
    /** Camera centre in pitch metres (x, y, z), z along [r]'s third column. */
    val centre: DoubleArray,
    /** +1 when world z points up out of the pitch, -1 when the solve came out mirrored. */
    val up: Double,
    private val aspect: Double,
) {
    /** How high the lens is above the pitch, in metres. */
    val heightM: Double get() = up * centre[2]

    /**
     * A point over the pitch ([Point3], z UP) back into the picture, in normalised upright
     * image coordinates — or null when it is behind the lens. The inverse of [ray].
     */
    fun project(p: Point3): Point2? {
        val d = doubleArrayOf(p.x - centre[0], p.y - centre[1], up * p.z - centre[2])
        val cx = r[0][0] * d[0] + r[0][1] * d[1] + r[0][2] * d[2]
        val cy = r[1][0] * d[0] + r[1][1] * d[1] + r[1][2] * d[2]
        val cz = r[2][0] * d[0] + r[2][1] * d[1] + r[2][2] * d[2]
        if (cz <= 1e-6) return null
        return Point2(focal * cx / cz / aspect + 0.5, focal * cy / cz + 0.5)
    }

    /** Unit direction in the world through the picture point ([x], [y]). */
    fun ray(x: Double, y: Double): DoubleArray {
        val cx = (x - 0.5) * aspect / focal
        val cy = (y - 0.5) / focal
        // R^T * (cx, cy, 1): each world axis's dot product with the camera-space ray.
        val d = DoubleArray(3) { i -> r[0][i] * cx + r[1][i] * cy + r[2][i] }
        val n = sqrt(d[0] * d[0] + d[1] * d[1] + d[2] * d[2])
        return DoubleArray(3) { d[it] / n }
    }

    companion object {
        /** A lens has to be somewhere between a fisheye and a long zoom. In frame heights. */
        private const val MIN_FOCAL = 0.4
        private const val MAX_FOCAL = 8.0
        private const val MIN_HEIGHT_M = 0.3
        private const val MAX_HEIGHT_M = 30.0

        fun from(quad: PitchQuad, frameAspect: Float): PitchCamera? {
            val aspect = if (frameAspect > 0f) frameAspect.toDouble() else return null
            val image = quad.corners.map { Point2((it.x - 0.5) * aspect, it.y - 0.5) }
            val m = Homography.solve(quad.calibration, image)?.matrix() ?: return null

            // Columns of the pitch-to-picture map.
            val h1 = doubleArrayOf(m[0], m[3], m[6])
            val h2 = doubleArrayOf(m[1], m[4], m[7])
            val h3 = doubleArrayOf(m[2], m[5], m[8])

            /*
             * THE FOCAL LENGTH, from the two things a rotation must be: its first two
             * columns at right angles, and the same length. Each is linear in w = 1/f²;
             * both together, least squares, because from straight behind the arm the first
             * one is nearly 0 = 0 and only the second carries the answer.
             */
            val a1 = h1[0] * h2[0] + h1[1] * h2[1]
            val b1 = h1[2] * h2[2]
            val a2 = h1[0] * h1[0] + h1[1] * h1[1] - h2[0] * h2[0] - h2[1] * h2[1]
            val b2 = h1[2] * h1[2] - h2[2] * h2[2]
            val denom = a1 * a1 + a2 * a2
            if (denom < 1e-18) return null
            val w = -(a1 * b1 + a2 * b2) / denom
            if (w <= 0.0 || w.isNaN()) return null
            val f = 1.0 / sqrt(w)
            if (f < MIN_FOCAL || f > MAX_FOCAL) return null

            fun unK(h: DoubleArray) = doubleArrayOf(h[0] / f, h[1] / f, h[2])
            val k1 = unK(h1)
            val k2 = unK(h2)
            val k3 = unK(h3)
            val scale = (norm(k1) + norm(k2)) / 2.0
            if (scale < 1e-12) return null
            var r1 = k1.map { it / scale }.toDoubleArray()
            var r2 = k2.map { it / scale }.toDoubleArray()
            var t = k3.map { it / scale }.toDoubleArray()
            // The pitch is in front of the lens, not behind it.
            if (t[2] < 0) {
                r1 = r1.map { -it }.toDoubleArray()
                r2 = r2.map { -it }.toDoubleArray()
                t = t.map { -it }.toDoubleArray()
            }

            // Noise leaves the two columns a hair off square; square them up.
            r1 = unit(r1)
            val along = dot(r2, r1)
            r2 = unit(DoubleArray(3) { r2[it] - along * r1[it] })
            val r3 = cross(r1, r2)
            // R as rows of camera space: row i = (r1[i], r2[i], r3[i]).
            val rows = Array(3) { i -> doubleArrayOf(r1[i], r2[i], r3[i]) }

            // C = -R^T t
            val centre = DoubleArray(3) { j -> -(rows[0][j] * t[0] + rows[1][j] * t[1] + rows[2][j] * t[2]) }
            val up = if (centre[2] >= 0) 1.0 else -1.0
            val height = up * centre[2]
            if (height < MIN_HEIGHT_M || height > MAX_HEIGHT_M) return null

            return PitchCamera(f, rows, centre, up, aspect)
        }

        private fun dot(a: DoubleArray, b: DoubleArray) = a[0] * b[0] + a[1] * b[1] + a[2] * b[2]
        private fun norm(a: DoubleArray) = sqrt(dot(a, a))
        private fun unit(a: DoubleArray): DoubleArray = norm(a).let { n -> DoubleArray(3) { a[it] / n } }
        internal fun cross(a: DoubleArray, b: DoubleArray) = doubleArrayOf(
            a[1] * b[2] - a[2] * b[1],
            a[2] * b[0] - a[0] * b[2],
            a[0] * b[1] - a[1] * b[0],
        )
    }
}

/**
 * SPEED FROM THE PITCH CORNERS, for a camera behind the bowler's arm.
 *
 * The wicket lock refuses this view because one camera cannot see depth. The pitch corners
 * change that: they give back the camera itself ([PitchCamera]), so every sighting becomes
 * a ray the ball was on, and the bounce — the one frame the ball was ON the pitch — is a
 * known place at a known moment.
 *
 * Between release and bounce a cricket ball does two simple things: it travels across the
 * ground at a near-steady speed, and it falls under gravity. Written as a path that ends at
 * the bounce, it has three unknowns — the two ground speeds and how fast it was coming down
 * at the bounce — and "this point lies on that ray" is LINEAR in all three. So it is one
 * small least-squares solve, no iteration, and every sighting before the bounce votes.
 *
 * What it is: the average speed over the ground before the bounce. A delivery leaves the
 * hand a few percent quicker than that and slows through the air, so it reads a little
 * under a speed gun at release — and the caveat says so.
 */
object QuadSpeed {

    private const val G = 9.81

    /** Fewer sightings in the air than this and three unknowns are not pinned down. */
    const val MIN_PRE_BOUNCE = 3

    /** Only sightings this close to the bounce: earlier ones are the bowler's hand. */
    const val MAX_LOOKBACK_MS = 900L

    /** Outside this the fit has found something that is not a cricket delivery. */
    const val MIN_KMH = 25.0
    const val MAX_KMH = 175.0

    /**
     * The delivery in three dimensions, or why not. [estimate] is this, read for one number;
     * the 3D replay draws the whole of it.
     */
    sealed interface FitResult {
        data class Ok(val flight: Flight3d) : FitResult
        data class Refused(val reason: String) : FitResult
    }

    fun fit(
        run: List<BallSighting>,
        quad: PitchQuad,
        bounce: Bounce,
        frameAspect: Float,
    ): FitResult {
        if (quad.cameraEnd != CameraEnd.BOWLER) {
            return FitResult.Refused("pitch-corner speed works from behind the bowler's arm only")
        }
        val tb = bounce.atMs ?: return FitResult.Refused("the bounce has no time")
        val camera = PitchCamera.from(quad, frameAspect)
            ?: return FitResult.Refused("the pitch corners do not describe a camera — re-mark them")

        val air = run.filter { it.timestampMs <= tb && tb - it.timestampMs <= MAX_LOOKBACK_MS }
        if (air.size < MIN_PRE_BOUNCE) {
            return FitResult.Refused("needs $MIN_PRE_BOUNCE sightings before the bounce, have ${air.size}")
        }

        /*
         * THE BOUNCE IS SOLVED FOR, NOT TAKEN.
         *
         * [BouncePoint] places the bounce to about a third of a metre at medium pace and
         * nearer a metre at 120 km/h, where the frames are a metre apart — and pinning the
         * flight to a place that is a metre out while keeping its time bends the speed by
         * a fifth. So its place and moment are only the starting point: the fit takes the
         * bounce's position as two more unknowns, uses the sightings after it as well
         * (their own speeds, the same landing spot), and slides the moment a frame either
         * way to wherever the whole flight agrees best.
         */
        val near = run.filter { abs(it.timestampMs - tb) <= MAX_LOOKBACK_MS }
        var best: DoubleArray? = null
        var bestTb = tb
        var bestError = Double.MAX_VALUE
        var shift = -TB_SEARCH_MS
        while (shift <= TB_SEARCH_MS) {
            val fit = fitFlight(near, camera, tb + shift)
            if (fit != null && fit.second < bestError) {
                bestError = fit.second
                best = fit.first
                bestTb = tb + shift
            }
            shift += TB_STEP_MS
        }
        val v = best ?: return FitResult.Refused("the sightings around the bounce are too few or too close together to fit")

        // Towards the striker is down the pitch's y.
        if (v[3] >= 0) {
            return FitResult.Refused("the fitted ball travels away from the batter — check which end the corners were marked from")
        }
        val kmh = hypot(v[2], v[3]) * 3.6
        if (kmh < MIN_KMH || kmh > MAX_KMH || kmh.isNaN()) {
            return FitResult.Refused("the fit gave %.0f km/h, which is not a delivery".format(kmh))
        }
        val hasAfter = v.size == 8 && v[6] < 0
        return FitResult.Ok(
            Flight3d(
                bounceX = v[0],
                bounceY = v[1],
                bounceMs = bestTb,
                inVx = v[2],
                inVy = v[3],
                inVzDown = v[4],
                outVx = if (hasAfter) v[5] else null,
                outVy = if (hasAfter) v[6] else null,
                outVzUp = if (hasAfter) v[7] else null,
                firstSeenMs = near.first().timestampMs.toDouble(),
                lastSeenMs = near.last().timestampMs.toDouble(),
                cameraHeightM = camera.heightM,
            ),
        )
    }

    fun estimate(
        run: List<BallSighting>,
        quad: PitchQuad,
        bounce: Bounce,
        frameAspect: Float,
    ): MetricValue = when (val result = fit(run, quad, bounce, frameAspect)) {
        is FitResult.Refused -> MetricValue.Unavailable(result.reason)
        is FitResult.Ok -> MetricValue.Estimated(
            result.flight.speedKmh,
            "km/h",
            "average before the bounce, from the pitch corners (camera %.1f m up) — a little under release speed"
                .format(result.flight.cameraHeightM),
        )
    }

    /** Search either side of the bounce finder's moment, and in what steps. */
    private const val TB_SEARCH_MS = 40.0
    private const val TB_STEP_MS = 2.0

    /**
     * One flight through the rays, for a given bounce moment [tb]. Unknowns, all linear:
     * bounce place (bx, by); before it vx, vy and vz (vz positive = coming down); after it
     * vx', vy', vz' (vz' positive = going up). Returns the unknowns and the mean squared
     * miss, or null when the before or after side is too thin to fit.
     */
    private fun fitFlight(points: List<BallSighting>, camera: PitchCamera, tb: Double): Pair<DoubleArray, Double>? {
        val before = points.count { it.timestampMs <= tb }
        val after = points.size - before
        if (before < MIN_PRE_BOUNCE) return null
        val n = if (after >= 2) 8 else 5
        val c = camera.centre
        val up = camera.up
        val ata = Array(n) { DoubleArray(n) }
        val atb = DoubleArray(n)
        val rows = ArrayList<Pair<DoubleArray, Double>>()
        for (sighting in points) {
            val s = (sighting.timestampMs - tb) / 1000.0
            if (s > 0 && n == 5) continue
            val d = camera.ray(sighting.x.toDouble(), sighting.y.toDouble())
            // P - C = a + J u, J is 3 x n.
            val a = doubleArrayOf(-c[0], -c[1], up * (-G * s * s / 2.0) - c[2])
            val j = Array(3) { DoubleArray(n) }
            j[0][0] = 1.0
            j[1][1] = 1.0
            if (s <= 0) {
                j[0][2] = s; j[1][3] = s; j[2][4] = -up * s
            } else {
                j[0][5] = s; j[1][6] = s; j[2][7] = up * s
            }
            // cross(a + J u, d) = 0  →  ([d]x J) u = cross(a, d)
            val rhs = PitchCamera.cross(a, d)
            val skew = arrayOf(
                doubleArrayOf(0.0, -d[2], d[1]),
                doubleArrayOf(d[2], 0.0, -d[0]),
                doubleArrayOf(-d[1], d[0], 0.0),
            )
            for (row in 0 until 3) {
                val coeff = DoubleArray(n) { k -> skew[row][0] * j[0][k] + skew[row][1] * j[1][k] + skew[row][2] * j[2][k] }
                rows.add(coeff to rhs[row])
                for (p in 0 until n) {
                    atb[p] += coeff[p] * rhs[row]
                    for (q in 0 until n) ata[p][q] += coeff[p] * coeff[q]
                }
            }
        }
        val u = solve(ata, atb) ?: return null
        var error = 0.0
        for ((coeff, rhs) in rows) {
            var r = -rhs
            for (k in 0 until n) r += coeff[k] * u[k]
            error += r * r
        }
        return u to error / rows.size
    }

    /** Gaussian elimination with partial pivoting; null when singular. */
    private fun solve(a: Array<DoubleArray>, b: DoubleArray): DoubleArray? {
        val n = b.size
        val m = Array(n) { r -> DoubleArray(n + 1) { k -> if (k < n) a[r][k] else b[r] } }
        val scale = a.maxOf { row -> row.maxOf { abs(it) } }.coerceAtLeast(1e-300)
        for (col in 0 until n) {
            val pivot = (col until n).maxByOrNull { abs(m[it][col]) } ?: return null
            if (abs(m[pivot][col]) < 1e-12 * scale) return null
            val tmp = m[col]; m[col] = m[pivot]; m[pivot] = tmp
            for (r in 0 until n) {
                if (r == col) continue
                val factor = m[r][col] / m[col][col]
                if (factor == 0.0) continue
                for (k in col..n) m[r][k] -= factor * m[col][k]
            }
        }
        return DoubleArray(n) { m[it][n] / m[it][it] }
    }
}

/** A point over the pitch, in metres: x across, y from the striker's stumps, z UP. */
data class Point3(val x: Double, val y: Double, val z: Double)

/**
 * One delivery as a path through the air, fitted by [QuadSpeed].
 *
 * Ballistic either side of the bounce: steady speed over the ground, gravity on the height.
 * The "out" leg is null when too little was seen after the bounce to fit one.
 */
data class Flight3d(
    val bounceX: Double,
    val bounceY: Double,
    val bounceMs: Double,
    val inVx: Double,
    val inVy: Double,
    /** Positive = coming down at the bounce. */
    val inVzDown: Double,
    val outVx: Double?,
    val outVy: Double?,
    /** Positive = going up off the bounce. */
    val outVzUp: Double?,
    val firstSeenMs: Double,
    val lastSeenMs: Double,
    val cameraHeightM: Double,
) {
    val speedKmh: Double get() = hypot(inVx, inVy) * 3.6
    val hasOutLeg: Boolean get() = outVx != null && outVy != null && outVzUp != null

    /** Where the ball was at [ms] on the sightings' clock. Height never below the grass. */
    fun at(ms: Double): Point3 {
        val s = (ms - bounceMs) / 1000.0
        return if (s <= 0 || !hasOutLeg) {
            Point3(bounceX + inVx * s, bounceY + inVy * s, (-inVzDown * s - G * s * s / 2).coerceAtLeast(0.0))
        } else {
            Point3(bounceX + outVx!! * s, bounceY + outVy!! * s, (outVzUp!! * s - G * s * s / 2).coerceAtLeast(0.0))
        }
    }

    /**
     * When it was let go, near enough: the in-leg run back to the bowler's release spot, but
     * never more than [MAX_BACKFILL_MS] before it was first seen. Drawn, not measured.
     */
    val releaseMs: Double
        get() {
            val back = if (inVy < 0) (bounceY - RELEASE_Y_M) / inVy * 1000.0 else 0.0
            return maxOf(bounceMs + back, firstSeenMs - MAX_BACKFILL_MS).coerceAtMost(firstSeenMs)
        }

    /** When it reaches the stumps' line, or null when there is no out leg to carry it. */
    val stumpsMs: Double?
        get() {
            val vy = outVy ?: return null
            if (vy >= 0) return null
            return bounceMs + (0.0 - bounceY) / vy * 1000.0
        }

    /** Where it would have crossed the striker's stump line, in 3D. */
    val atStumps: Point3? get() = stumpsMs?.let(::at)

    /** Whether that crossing is on the wicket, ball radius included. Null without an out leg. */
    val hitsStumps: Boolean?
        get() = atStumps?.let {
            abs(it.x) <= LbwProjector.HITTING_LIMIT_M && it.z <= LbwProjector.OVER_THE_TOP_M
        }

    private companion object {
        const val G = 9.81
        const val RELEASE_Y_M = 18.4
        const val MAX_BACKFILL_MS = 400.0
    }
}
