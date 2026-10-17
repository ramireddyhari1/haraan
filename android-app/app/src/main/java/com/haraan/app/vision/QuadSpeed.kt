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
class PitchCamera private constructor(
    /** Focal length in frame heights. */
    val focal: Double,
    /** World axes in camera coordinates, as the columns of R: x across, y up the pitch, z. */
    private val r: Array<DoubleArray>,
    /** Camera centre in pitch metres (x, y, z), z along [r]'s third column. */
    val centre: DoubleArray,
    /** +1 when world z points up out of the pitch, -1 when the solve came out mirrored. */
    val up: Double,
    private val aspect: Double,
    /**
     * For a camera given outright ([of]): whether its height was measured or assumed. A
     * camera solved from pitch corners always measured it.
     */
    val heightMeasured: Boolean = true,
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

        /**
         * A camera given outright rather than solved from corners — the stumps-only model in
         * [PitchGround.pitchCamera]. [rows] are the camera's axes in pitch metres, [centre]
         * the lens in pitch metres, [focalHeights] the focal length in frame heights.
         */
        internal fun of(
            focalHeights: Double,
            rows: Array<DoubleArray>,
            centre: DoubleArray,
            aspect: Double,
            heightMeasured: Boolean = false,
        ): PitchCamera = PitchCamera(focalHeights, rows, centre, 1.0, aspect, heightMeasured)

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
    /*
     * The slowest thing fitted as a ball. 25 refused a real backyard throw at 24 km/h — a
     * gentle practice lob, a child's delivery — and with it every replay. People walking
     * past are ~5 km/h, so 15 still keeps them out.
     */
    const val MIN_KMH = 15.0
    const val MAX_KMH = 175.0

    /** A bounce fitted just behind the stump line is a full toss fitted low; metres behind is not a ball. */
    const val MIN_BOUNCE_ALONG_M = -0.5

    /*
     * The shortest ball bowled — a bouncer — pitches 11-13 m from the batter's stumps. A fit
     * pitching it nearer the bowler than [MAX_BOUNCE_ALONG_M] is something else: on a 120 fps
     * net clip (2026-10-11) the bowler's follow-through and a fielder fitted as 16-41 km/h
     * "deliveries" pitching 15.7-21.4 m up, under the bowler's feet. (This was 21.5, the
     * bowler's crease, which only caught a camera model gone wrong.)
     */
    const val MAX_BOUNCE_ALONG_M = 15.0

    /** A best fit this close to [MAX_BOUNCE_ALONG_M] was pushed there by the limit. */
    const val EDGE_MARGIN_M = 0.6

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
        bounce: Bounce?,
        frameAspect: Float,
    ): FitResult {
        val camera = PitchCamera.from(quad, frameAspect)
            ?: return FitResult.Refused("the pitch corners do not describe a camera — re-mark them")
        return fit(run, camera, bounce, frameAspect)
    }

    /**
     * The same fit through a camera given directly — the stumps-only model when no pitch
     * corners exist, as in a backyard with no painted creases.
     */
    fun fit(
        run: List<BallSighting>,
        camera: PitchCamera,
        bounce: Bounce?,
        frameAspect: Float,
        /**
         * When the ball was seen arriving AT the stumps (it entered their outline in the
         * picture): it was on the stumps' line then. Null when it was not seen there.
         */
        reachedStumpsMs: Double? = null,
    ): FitResult {
        if (run.size < MIN_PRE_BOUNCE + 2) {
            return FitResult.Refused("needs ${MIN_PRE_BOUNCE + 2} sightings in one flight, have ${run.size}")
        }

        /*
         * THE BOUNCE IS SOLVED FOR, NOT TAKEN.
         *
         * [BouncePoint] places the bounce to about a third of a metre at medium pace and
         * nearer a metre at 120 km/h, where the frames are a metre apart — and pinning the
         * flight to a place that is a metre out while keeping its time bends the speed by
         * a fifth. So its place is never used: the fit takes the bounce's position as two
         * more unknowns, uses the sightings after it as well (their own speeds, the same
         * landing spot), and finds the MOMENT wherever the whole flight agrees best.
         *
         * WITH NO BOUNCE TO START FROM, IT SEARCHES THE WHOLE FLIGHT. That is what makes the
         * batter's end work: [BouncePoint] refuses that end — the ball comes at the camera
         * and its ground reading never turns — but the two-leg flight through the rays does
         * not care which way the ball is travelling. A bounce from [BouncePoint] only
         * narrows the search to a frame either side.
         */
        val first = run.first().timestampMs.toDouble()
        val last = run.last().timestampMs.toDouble()
        /*
         * ...AND PAST ITS LAST SIGHTING. Filmed from behind the bowler, a ball going away
         * shrinks to a few pixels and is often lost before it pitches: on a real net video
         * all eight sightings of a delivery were in the air. Made to pitch inside them, the
         * fit bent the flight backwards ("travels away from the batter"). Searched a little
         * beyond, the sightings make one parabola, and where it meets the ground is the
         * bounce.
         */
        /*
         * ...UNLESS THE PICTURE SHOWS IT PITCH. From behind the bowler a ball comes down the
         * picture until it lands and goes back up it after: the lowest sighting with a clear
         * climb after it IS the bounce. Left to the open search, a 4K net clip with twenty
         * sightings after the bounce was fitted pitching past its last sighting — the late,
         * rising points taken alone as a ball coming down — with no out leg, so the path was
         * projected into the stumps when the ball had bounced over them.
         */
        val turn = if (bounce?.atMs == null) pictureBounceMs(run) else null
        val (from, to, step) = bounce?.atMs?.let { Triple(it - TB_SEARCH_MS, it + TB_SEARCH_MS, TB_STEP_MS) }
            ?: turn?.let { Triple(it - TURN_SEARCH_MS, it + TURN_SEARCH_MS, TB_STEP_MS) }
            ?: Triple(first, last + EXTRAPOLATE_BOUNCE_MS, TB_SCAN_STEP_MS)

        class Best(var v: DoubleArray? = null, var tb: Double = from, var error: Double = Double.MAX_VALUE)
        fun search(atStumpsMs: Double?): Best {
            val best = Best()
            fun scan(a: Double, b: Double, by: Double) {
                var tb = a
                while (tb <= b) {
                    val near = run.filter { abs(it.timestampMs - tb) <= MAX_LOOKBACK_MS }
                    val fit = fitFlight(near, camera, tb, atStumpsMs)?.takeIf { physical(it.first) }
                    if (fit != null && fit.second < best.error) {
                        best.error = fit.second
                        best.v = fit.first
                        best.tb = tb
                    }
                    tb += by
                }
            }
            scan(from, to, step)
            // A coarse scan, then the frame either side of its answer at full resolution.
            if (bounce?.atMs == null && turn == null && best.v != null) {
                val coarse = best.tb
                scan(coarse - TB_SCAN_STEP_MS, coarse + TB_SCAN_STEP_MS, TB_STEP_MS)
            }
            return best
        }
        /*
         * THE STUMPS MOMENT IS CHECKED, NOT TRUSTED. It comes from the ball being inside the
         * stumps' outline in the picture, and a ball a metre or two short of them, climbing
         * off a good length, is inside that outline too: pinned to the line there, a simulated
         * 90 km/h delivery read 114. So the flight is fitted both ways, and the pinned one is
         * kept only when it explains the sightings nearly as well as the free one — where the
         * free fit is undecided along the pitch, as on the 4K yorker, the pin costs nothing.
         */
        val free = search(null)
        val pinned = reachedStumpsMs?.let { search(it) }
        val chosen = if (pinned?.v != null && (free.v == null || pinned.error <= free.error * STUMPS_PIN_TOLERANCE)) pinned else free
        val v = chosen.v ?: return FitResult.Refused("the sightings around the bounce are too few or too close together to fit")
        val bestTb = chosen.tb
        val near = run.filter { abs(it.timestampMs - bestTb) <= MAX_LOOKBACK_MS }

        // Towards the striker is down the pitch's y.
        if (v[3] >= 0) {
            return FitResult.Refused("the fitted ball travels away from the batter — check which end the corners were marked from")
        }
        val kmh = hypot(v[2], v[3]) * 3.6
        if (kmh < MIN_KMH || kmh > MAX_KMH || kmh.isNaN()) {
            return FitResult.Refused("the fit gave %.0f km/h, which is not a delivery".format(kmh))
        }
        /*
         * WHERE IT PITCHED MUST BE ON THE PITCH. A wrong camera — corners "found" in the
         * lines of a tiled floor — fits a confident flight that lands metres behind the
         * stumps. Nothing bowled does that; refuse it rather than draw it.
         */
        if (v[1] < MIN_BOUNCE_ALONG_M) {
            return FitResult.Refused("the fit pitched the ball %.1f m behind the stumps — the camera model is wrong".format(-v[1]))
        }
        // At the very edge of where a delivery can pitch, the search found nothing in range
        // and stopped against the limit: that is not a fit (a fielder walking, a follow-through).
        if (v[1] > MAX_BOUNCE_ALONG_M - EDGE_MARGIN_M) {
            return FitResult.Refused("the fit pitched the ball %.1f m up, nearer the bowler than any delivery".format(v[1]))
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
        bounce: Bounce?,
        frameAspect: Float,
    ): MetricValue = when (val result = fit(run, quad, bounce, frameAspect)) {
        is FitResult.Refused -> MetricValue.Unavailable(result.reason)
        is FitResult.Ok -> MetricValue.Estimated(
            result.flight.speedKmh,
            "km/h",
            "release speed, from the pitch corners (camera %.1f m up) and the ball's drag"
                .format(result.flight.cameraHeightM),
        )
    }

    /** Search either side of the bounce finder's moment, and in what steps. */
    private const val TB_SEARCH_MS = 40.0

    /*
     * A BALL COMES DOWN TO THE PITCH AND GOES UP OFF IT. Unchecked, the search over bounce
     * moments found a "flight" for a full ball whose bounce was never seen (4K net clip,
     * 2026-10-12) by pitching it just after release and treating the whole descent as the
     * leg AFTER the bounce — a ball still falling once it has bounced — and fitted it better
     * than the real answer: "pitched 21.9 m up". Coming down into the bounce and, when there
     * is a leg after it, climbing out of it, are now required of every candidate.
     */
    private fun physical(v: DoubleArray): Boolean {
        if (v.size < 5 || v[4] <= 0.0) return false
        // ...and lands where a delivery can: the refusal below used to see only the single
        // best candidate, so one out of range threw away every one that was not.
        if (v[1] < MIN_BOUNCE_ALONG_M || v[1] > MAX_BOUNCE_ALONG_M) return false
        return v.size < 8 || v[7] > 0.0
    }

    /** The share of its pace along and across the pitch a ball keeps off the bounce. */
    internal const val PACE_KEPT_OFF_PITCH = 0.88

    /** How strongly "it was at the stumps then" counts against one sighting's miss, in metres. */
    private const val STUMPS_WEIGHT = 12.0

    /** How much worse the pinned fit may explain the sightings than the free one and still be kept. */
    private const val STUMPS_PIN_TOLERANCE = 1.5

    /** Either side of the lowest point in the picture, when the ball is seen to climb after it. */
    private const val TURN_SEARCH_MS = 60.0

    /**
     * When the sightings come down the picture and clearly go back up it — at least
     * [TURN_MIN_AFTER] sightings after the lowest, climbing [TURN_MIN_RISE] of the frame —
     * the time of the lowest one; else null. Picture y grows downwards.
     */
    private fun pictureBounceMs(run: List<BallSighting>): Double? {
        if (run.size < MIN_PRE_BOUNCE + TURN_MIN_AFTER) return null
        val low = run.indices.maxByOrNull { run[it].y } ?: return null
        if (low < MIN_PRE_BOUNCE || run.size - 1 - low < TURN_MIN_AFTER) return null
        val fell = run[low].y - run.first().y
        val rose = run[low].y - run.subList(low + 1, run.size).minOf { it.y }
        if (fell < TURN_MIN_RISE || rose < TURN_MIN_RISE) return null
        return run[low].timestampMs.toDouble()
    }

    private const val TURN_MIN_AFTER = 4
    private const val TURN_MIN_RISE = 0.01f
    private const val TB_STEP_MS = 2.0

    /** The whole-flight search, when no bounce was found to start from. */
    private const val TB_SCAN_STEP_MS = 8.0

    /** How far past the last sighting the bounce may be extrapolated. */
    private const val EXTRAPOLATE_BOUNCE_MS = 600.0

    /**
     * One flight through the rays, for a given bounce moment [tb]. Unknowns, all linear:
     * bounce place (bx, by); before it vx, vy and vz (vz positive = coming down); after it
     * vx', vy', vz' (vz' positive = going up). Returns the unknowns and the mean squared
     * miss, or null when the before or after side is too thin to fit.
     */
    private fun fitFlight(points: List<BallSighting>, camera: PitchCamera, tb: Double, reachedStumpsMs: Double? = null): Pair<DoubleArray, Double>? {
        val before = points.count { it.timestampMs <= tb }
        val after = points.size - before
        if (before < MIN_PRE_BOUNCE) return null
        /*
         * AFTER THE BOUNCE THE BALL KEEPS ITS LINE. Its own across-and-along speed after
         * the bounce used to be three more unknowns, and seen from behind the bowler — where
         * along-the-pitch is depth, the axis one camera sees worst — it came out pointing
         * back at the bowler on a 4K net clip with twenty clean sightings after the bounce,
         * so the leg was thrown away and the path was projected into the stumps. Off the
         * pitch a ball keeps its line and loses some pace, so that motion is the incoming
         * one scaled by [PACE_KEPT_OFF_PITCH], and only the climb off the pitch is solved.
         * The answer is still eight numbers, as before.
         */
        val n = if (after >= 2) 6 else 5
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
                j[0][2] = PACE_KEPT_OFF_PITCH * s; j[1][3] = PACE_KEPT_OFF_PITCH * s; j[2][5] = up * s
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
        /*
         * THE BALL WAS AT THE STUMPS WHEN IT REACHED THEM. From behind the bowler the far end
         * is depth, the axis one camera sees worst, and with the ball lost at the bounce a
         * flight pitched 6 m short and slow fitted the sightings as well as one pitched at
         * the stumps and quick: the same 4K delivery read 68, 95 and 99 km/h as the frames
         * were shrunk three slightly different ways. Seen entering the stumps' outline, the
         * ball was on their line (0 m) at that moment — one strong equation that pins the
         * distance against the time, and with it both the speed and where it pitched.
         */
        // Scored on the sightings alone: the pin's own row is a condition, not a sighting.
        if (reachedStumpsMs != null) {
            val s = (reachedStumpsMs - tb) / 1000.0
            val coeff = DoubleArray(n)
            coeff[1] = STUMPS_WEIGHT
            coeff[3] = STUMPS_WEIGHT * (if (s <= 0) s else PACE_KEPT_OFF_PITCH * s)
            for (p in 0 until n) {
                for (q in 0 until n) ata[p][q] += coeff[p] * coeff[q]
            }
        }
        val u = solve(ata, atb) ?: return null
        var error = 0.0
        for ((coeff, rhs) in rows) {
            var r = -rhs
            for (k in 0 until n) r += coeff[k] * u[k]
            error += r * r
        }
        val full = if (n == 6) {
            doubleArrayOf(u[0], u[1], u[2], u[3], u[4], PACE_KEPT_OFF_PITCH * u[2], PACE_KEPT_OFF_PITCH * u[3], u[5])
        } else {
            u
        }
        return full to error / rows.size
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
    /** The ball, for how fast it slows in the air. See [BallDrag]. */
    val ball: BallDrag = BallDrag.LEATHER,
) {
    /** The fitted steady speed over the ground before the bounce: the average, not the release. */
    val groundKmh: Double get() = hypot(inVx, inVy) * 3.6

    /** Release speed — what a speed gun reads — for [ball]. Every readout shows this one. */
    val speedKmh: Double get() = releaseKmh(this)
    /** Whether the leg after the bounce was SEEN and fitted (else it is projected). */
    val hasOutLeg: Boolean get() = outVx != null && outVy != null && outVzUp != null

    /*
     * OFF THE PITCH, SEEN OR PROJECTED. Filmed from behind the bowler the ball is often lost
     * at the bounce — a yorker into the stumps gave two sightings after it on a real 4K clip —
     * and with no leg after the bounce the delivery could not be judged at all: "not judged"
     * in the 3D stadium for a ball that hit the stumps. Like ball-tracking systems, the
     * flight is carried on as a ball comes off a pitch: same line, [QuadSpeed.PACE_KEPT_OFF_PITCH]
     * of the pace, rising at [RESTITUTION] of the speed it came down at.
     */
    private val legVx: Double get() = outVx ?: QuadSpeed.PACE_KEPT_OFF_PITCH * inVx
    private val legVy: Double get() = outVy ?: QuadSpeed.PACE_KEPT_OFF_PITCH * inVy
    private val legVzUp: Double get() = outVzUp ?: RESTITUTION * inVzDown

    /** Where the ball was at [ms] on the sightings' clock. Height never below the grass. */
    fun at(ms: Double): Point3 {
        val s = (ms - bounceMs) / 1000.0
        return if (s <= 0) {
            Point3(bounceX + inVx * s, bounceY + inVy * s, (-inVzDown * s - G * s * s / 2).coerceAtLeast(0.0))
        } else {
            Point3(bounceX + legVx * s, bounceY + legVy * s, (legVzUp * s - G * s * s / 2).coerceAtLeast(0.0))
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

    /** When it reaches the stumps' line — on the seen leg, or the projected one. */
    val stumpsMs: Double?
        get() {
            // Pitched at or behind the line: it got there on the way down.
            if (bounceY <= 0.0) return if (inVy < 0) bounceMs + (0.0 - bounceY) / inVy * 1000.0 else null
            val vy = legVy
            if (vy >= 0) return null
            return bounceMs + (0.0 - bounceY) / vy * 1000.0
        }

    /** Where it would have crossed the striker's stump line, in 3D. */
    val atStumps: Point3? get() = stumpsMs?.let(::at)

    /** Whether that crossing is on the wicket, ball radius included. */
    val hitsStumps: Boolean?
        get() = atStumps?.let {
            abs(it.x) <= LbwProjector.HITTING_LIMIT_M && it.z <= LbwProjector.OVER_THE_TOP_M
        }

    internal companion object {
        const val G = 9.81
        const val RELEASE_Y_M = 18.4
        const val MAX_BACKFILL_MS = 400.0
        /** How fast a ball climbs off a pitch, as a share of how fast it came down. */
        const val RESTITUTION = 0.5
    }
}
