package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.sin
import kotlin.math.sqrt

/**
 * The bowler's-end stumps, as seen: their base (normalised upright) and the picture width
 * between the outer stumps' centres (frame widths) — the same Laws-sized set as the batter's,
 * so the two widths together say how far apart they look.
 */
data class BowlersEnd(val base: Point2, val spanFw: Double)

/**
 * THE BOWLER'S STUMPS DO NOT MOVE; WHAT IS SEEN OF THEM DOES.
 *
 * The ground is solved from both wickets, and it used to be solved from the LAST sighting
 * of the near set. On a 120 fps net clip (2026-10-11) that sighting was taken mid-stride
 * with the bowler's leg across the stumps half the time, and the camera's height came out
 * anywhere from 0.2 m to 1.97 m from one ball to the next on a phone that never moved —
 * so two deliveries were drawn diving through the near stumps. The median of the recent
 * sightings is what is used instead; a sighting far from it is a leg, not the stumps, and
 * a run of agreeing sightings somewhere else is the phone having been moved.
 */
class BowlersEndFilter(private val keep: Int = KEEP) {
    private val seen = ArrayDeque<BowlersEnd>()

    /** Folds in one sighting; returns the steady estimate, or null until there is one. */
    fun add(s: BowlersEnd): BowlersEnd? {
        seen.addLast(s)
        while (seen.size > keep) seen.removeFirst()
        // The phone moved: the latest sightings agree with each other and not with the rest.
        if (seen.size > MOVED_RUN * 2) {
            val run = seen.toList().takeLast(MOVED_RUN)
            val all = value()
            val rx = median(run.map { it.base.x })
            val ry = median(run.map { it.base.y })
            val rs = median(run.map { it.spanFw })
            val agree = run.count { abs(it.base.x - rx) <= rs * 0.25 && abs(it.base.y - ry) <= rs * 0.25 }
            if (agree >= MOVED_RUN - 1 && (all == null || abs(rx - all.base.x) > rs * 0.5 || abs(ry - all.base.y) > rs * 0.5)) {
                seen.clear()
                seen.addAll(run)
            }
        }
        return value()
    }

    /** The steady estimate: medians of the sightings near the overall median. */
    fun value(): BowlersEnd? {
        if (seen.size < MIN_SIGHTINGS) return null
        val mx = median(seen.map { it.base.x })
        val my = median(seen.map { it.base.y })
        val ms = median(seen.map { it.spanFw })
        val near = seen.filter { abs(it.base.x - mx) <= ms * 0.5 && abs(it.base.y - my) <= ms * 0.5 && abs(it.spanFw - ms) <= ms * 0.15 }
        if (near.size < MIN_SIGHTINGS) return null
        return BowlersEnd(Point2(median(near.map { it.base.x }), median(near.map { it.base.y })), median(near.map { it.spanFw }))
    }

    fun clear() = seen.clear()

    private fun median(v: List<Double>): Double = v.sorted().let { s -> if (s.size % 2 == 1) s[s.size / 2] else (s[s.size / 2 - 1] + s[s.size / 2]) / 2.0 }

    companion object {
        /** About ten seconds of the camera screen's one-in-three stump checks. */
        const val KEEP = 90
        const val MIN_SIGHTINGS = 5

        /** This many agreeing sightings away from the estimate mean the phone was moved. */
        const val MOVED_RUN = 8
    }
}

/**
 * The pitch as it really lies on the ground in this camera's picture, from the wicket alone.
 *
 * WHY. The length guide used to be drawn as a fixed multiple of the stump height on screen,
 * with zone boundaries at arbitrary fractions of it. Nothing in it was a metre, so it never
 * lay on the floor: tiles and grass receded one way and the guide another, and YORKER on
 * screen was wherever 0.2 × 3.8 stump-heights happened to fall. A guide that does not sit on
 * the ground has no physical feel however it is styled.
 *
 * HOW. A pinhole camera, its focal length from the lens (see [CameraIntrinsics]), looking at
 * a wicket whose size is known by the Laws:
 *
 *   the outer stumps' span in pixels gives the slant distance to the wicket;
 *   a camera height (a stand or a hand, about 1.4 m) turns that into a distance along the
 *   ground and fixes how steeply the camera looks down — solved exactly, so that the ray
 *   through the stumps' foot lands on the ground at that distance;
 *   the line of the stumps' feet gives the camera's roll.
 *
 * Every point of the pitch is then projected back through the same camera, so the zones
 * converge, foreshorten and widen exactly as the real ground does. The pitch is taken to run
 * from the wicket towards the camera's foot — the camera stands behind the bowler's arm.
 *
 * WHAT IT ASSUMES, AND SAYS. The camera height is a default, not a measurement; a wrong
 * height mostly stretches or compresses the near zones. It is a drawing aid. Measurements
 * come from the calibrated pitch quad, never from this.
 */
class PitchGround private constructor(
    private val fPx: Double,
    private val cx: Double,
    private val cy: Double,
    private val cameraHeightM: Double,
    private val pitchRad: Double,
    private val rollRad: Double,
    private val stumpsX: Double,
    private val stumpsY: Double,
    private val towardX: Double,
    private val towardY: Double,
    /** Horizontal distance from the camera's foot to the stumps, metres. */
    val stumpsDistanceM: Double,
    /**
     * True when the camera height was MEASURED (gravity gave the tilt, the stumps' span the
     * height); false when it is the [DEFAULT_CAMERA_HEIGHT_M] assumption.
     */
    val heightMeasured: Boolean = false,
) {
    /** How high the lens is above the ground, metres — measured or assumed, see [heightMeasured]. */
    val heightM: Double get() = cameraHeightM

    /**
     * Where a point on the ground lands in the upright frame, in PIXELS, or null when it is
     * behind the camera.
     *
     * @param alongM metres from the striker's stumps towards the camera (down the pitch)
     * @param acrossM metres across the pitch, positive to picture-right
     */
    fun project(alongM: Double, acrossM: Double): Pair<Double, Double>? {
        // Ground point in world metres: camera foot at the origin, z up.
        val px = stumpsX + towardX * alongM + (-towardY) * acrossM
        val py = stumpsY + towardY * alongM + towardX * acrossM
        val vx = px
        val vy = py
        val vz = -cameraHeightM
        val s = sin(pitchRad)
        val c = cos(pitchRad)
        val xc = vx
        val yc = -vy * s - vz * c
        val zc = vy * c - vz * s
        if (zc <= 0.15) return null
        val ux = fPx * xc / zc
        val uy = fPx * yc / zc
        // Back through the roll.
        val cr = cos(rollRad)
        val sr = sin(rollRad)
        return Pair(cx + ux * cr - uy * sr, cy + ux * sr + uy * cr)
    }

    /**
     * This camera as a [PitchCamera] in PITCH coordinates — x across (picture-right), y up the
     * pitch from the striker's stumps towards the camera, z up — so the 3D flight fit and the
     * replay can run on a ground with no pitch corners at all.
     */
    fun pitchCamera(widthPx: Int, heightPx: Int): PitchCamera {
        val s = sin(pitchRad)
        val c = cos(pitchRad)
        val cr = cos(rollRad)
        val sr = sin(rollRad)
        // The camera's axes in this model's world (camera foot at the origin, z up).
        val right = doubleArrayOf(1.0, 0.0, 0.0)
        val down = doubleArrayOf(0.0, -s, -c)
        val fwd = doubleArrayOf(0.0, c, -s)
        val row0 = DoubleArray(3) { right[it] * cr - down[it] * sr }
        val row1 = DoubleArray(3) { right[it] * sr + down[it] * cr }
        // Into pitch axes: across = (-ty, tx), up the pitch = (tx, ty), up = z.
        fun toPitch(v: DoubleArray) = doubleArrayOf(
            v[0] * -towardY + v[1] * towardX,
            v[0] * towardX + v[1] * towardY,
            v[2],
        )
        val rows = arrayOf(toPitch(row0), toPitch(row1), toPitch(fwd))
        val camX = -stumpsX
        val camY = -stumpsY
        val centre = doubleArrayOf(
            camX * -towardY + camY * towardX,
            camX * towardX + camY * towardY,
            cameraHeightM,
        )
        return PitchCamera.of(fPx / heightPx, rows, centre, widthPx.toDouble() / heightPx, heightMeasured)
    }

    /**
     * THE PITCH RUNS TO THE OTHER STUMPS, NOT TO THE PHONE.
     *
     * Without more to go on, the pitch is taken to run from the wicket towards the camera's
     * foot. When the bowler's-end stumps are in the picture too, the real pitch line is
     * known: from these stumps to those. [bowlersEndPx] is that set's base, in the same
     * upright frame pixels as [project]; its ray is dropped onto this ground and the pitch is
     * re-aimed along the line between the two. Null when the point is not on the ground in
     * front of the camera, or is not a plausible other end (under 5 m or past 26 m away).
     */
    fun aimedAt(bowlersEndPx: Pair<Double, Double>): PitchGround? {
        val (g, length) = aimedRaw(bowlersEndPx) ?: return null
        if (length < MIN_PITCH_SPAN_M || length > MAX_PITCH_SPAN_M) return null
        return g
    }

    /** This ground with its height marked as measured (by the pitch's length). */
    private fun copyMeasured() = PitchGround(
        fPx = fPx, cx = cx, cy = cy, cameraHeightM = cameraHeightM, pitchRad = pitchRad, rollRad = rollRad,
        stumpsX = stumpsX, stumpsY = stumpsY, towardX = towardX, towardY = towardY,
        stumpsDistanceM = stumpsDistanceM, heightMeasured = true,
    )

    /** [aimedAt] without the plausibility limits, with the two sets' distance apart. */
    private fun aimedRaw(bowlersEndPx: Pair<Double, Double>): Pair<PitchGround, Double>? {
        val cr = cos(-rollRad)
        val sr = sin(-rollRad)
        val x = bowlersEndPx.first - cx
        val y = bowlersEndPx.second - cy
        val xN = (x * cr - y * sr) / fPx
        val yN = (x * sr + y * cr) / fPx
        val c = cos(pitchRad)
        val s = sin(pitchRad)
        val dz = -(yN * c + s)
        if (dz >= -1e-6) return null
        val t = cameraHeightM / -dz
        val gx = t * xN
        val gy = t * (c - yN * s)
        val dx = gx - stumpsX
        val dy = gy - stumpsY
        val length = hypot(dx, dy)
        if (length < 1e-6) return null
        return PitchGround(
            fPx = fPx, cx = cx, cy = cy, cameraHeightM = cameraHeightM, pitchRad = pitchRad, rollRad = rollRad,
            stumpsX = stumpsX, stumpsY = stumpsY, towardX = dx / length, towardY = dy / length,
            stumpsDistanceM = stumpsDistanceM, heightMeasured = heightMeasured,
        ) to length
    }

    /**
     * The furthest distance down the pitch (towards the camera) whose centre still projects
     * above [maxYPx], searched in quarter metres up to [limitM].
     */
    fun visibleTo(maxYPx: Double, limitM: Double = 20.0): Double {
        var d = 0.0
        while (d + 0.25 <= limitM) {
            val p = project(d + 0.25, 0.0) ?: break
            if (p.second > maxYPx) break
            d += 0.25
        }
        return d
    }

    companion object {
        /** A camera on a stand, or held at chest height. */
        const val DEFAULT_CAMERA_HEIGHT_M = 1.4

        /** How far apart two sets of stumps may plausibly be: a short net to a long pitch. */
        private const val MIN_PITCH_SPAN_M = 5.0
        private const val MAX_PITCH_SPAN_M = 26.0

        /**
         * The ground under a locked wicket, or null when it cannot be solved (no span, a
         * stone, or a camera pointing up).
         *
         * @param widthPx, heightPx the upright analysis frame in pixels; the lock's
         *   normalised coordinates are multiplied out against these
         */
        fun fromWicket(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            cameraHeightM: Double = DEFAULT_CAMERA_HEIGHT_M,
        ): PitchGround? {
            if (lock.kind != WicketKind.STUMPS || widthPx <= 0 || heightPx <= 0) return null
            val f = camera.focalFw * widthPx
            val cx = widthPx / 2.0
            val cy = heightPx / 2.0
            val l = lock.anchor.baseLeft
            val r = lock.anchor.baseRight
            val lx = l.x * widthPx - cx
            val ly = l.y * heightPx - cy
            val rx = r.x * widthPx - cx
            val ry = r.y * heightPx - cy
            val spanPx = hypot(rx - lx, ry - ly)
            if (spanPx < 2.0) return null
            val seen = atan2(ry - ly, rx - lx)

            /*
             * ROLL AND TILT TOGETHER. The feet's line in the picture slopes for two reasons:
             * the phone is rolled, and a wicket off to one side is seen in perspective. Taking
             * the whole slope as roll tilted a level phone's guide. So: guess roll = slope,
             * solve the tilt, see how much slope perspective alone would give, and put only
             * the rest down to roll. Three or four rounds settle it.
             */
            var roll = seen
            var solved: Solved? = null
            repeat(6) {
                val sol = solve(lx, ly, rx, ry, roll, f, cameraHeightM) ?: return null
                solved = sol
                roll = seen - sol.perspectiveSlope
            }
            val sol = solved ?: return null
            val dist = hypot(sol.sX, sol.sY)
            if (dist < 0.3) return null
            return PitchGround(
                fPx = f, cx = cx, cy = cy, cameraHeightM = cameraHeightM, pitchRad = sol.pitch, rollRad = roll,
                stumpsX = sol.sX, stumpsY = sol.sY, towardX = -sol.sX / dist, towardY = -sol.sY / dist,
                stumpsDistanceM = dist,
            )
        }

        /** Heights a phone filming a pitch can be at; a solve outside them is a bad reading. */
        private const val MIN_MEASURED_HEIGHT_M = 0.3
        private const val MAX_MEASURED_HEIGHT_M = 3.0

        /**
         * The ground from the wicket AND the phone's attitude: nothing assumed.
         *
         * With the tilt and roll known (from gravity, [DeviceTilt]), each stump foot's ray is
         * a fixed direction in the world. Dropped onto a ground one metre below the lens the
         * two feet land some distance apart; the real feet are the Laws' span apart, and
         * since everything scales with the height, the height is the ratio. The stumps'
         * position follows at the same scale.
         *
         * Null for a stone, a ray that never reaches the ground, or a height no stand or hand
         * could be at (a reading taken mid-shake, a mis-detected foot).
         */
        fun fromWicketAndTilt(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            tilt: DeviceTilt.Reading,
        ): PitchGround? {
            if (lock.kind != WicketKind.STUMPS || widthPx <= 0 || heightPx <= 0) return null
            val f = camera.focalFw * widthPx
            val cx = widthPx / 2.0
            val cy = heightPx / 2.0
            val cr = cos(-tilt.rollRad)
            val sr = sin(-tilt.rollRad)
            val cp = cos(tilt.pitchRad)
            val sp = sin(tilt.pitchRad)
            /** A foot's pixel, roll taken out, dropped onto the ground 1 m below the lens. */
            fun ground(p: Point2): DoubleArray? {
                val x = p.x * widthPx - cx
                val y = p.y * heightPx - cy
                val xN = (x * cr - y * sr) / f
                val yN = (x * sr + y * cr) / f
                val dz = -(yN * cp + sp)
                if (dz >= -1e-6) return null
                val t = 1.0 / -dz
                return doubleArrayOf(t * xN, t * (cp - yN * sp))
            }
            val a = ground(lock.anchor.baseLeft) ?: return null
            val b = ground(lock.anchor.baseRight) ?: return null
            val apart = hypot(b[0] - a[0], b[1] - a[1])
            if (apart < 1e-6) return null
            val h = PitchGeometry.STUMP_CENTRES_SPAN_M / apart
            if (h < MIN_MEASURED_HEIGHT_M || h > MAX_MEASURED_HEIGHT_M) return null
            val sX = h * (a[0] + b[0]) / 2.0
            val sY = h * (a[1] + b[1]) / 2.0
            val dist = hypot(sX, sY)
            if (dist < 0.3) return null
            return PitchGround(
                fPx = f, cx = cx, cy = cy, cameraHeightM = h, pitchRad = tilt.pitchRad, rollRad = tilt.rollRad,
                stumpsX = sX, stumpsY = sY, towardX = -sX / dist, towardY = -sY / dist,
                stumpsDistanceM = dist, heightMeasured = true,
            )
        }

        /**
         * The best ground there is: measured from gravity when the phone is held still,
         * otherwise the stumps alone at the assumed height.
         */
        /**
         * BOTH SETS OF STUMPS IN VIEW: THE PITCH ITSELF IS THE RULER.
         *
         * The batter's stumps alone fix the ground only once a camera height is assumed or
         * read from gravity, and on a real net filmed from just behind the bowler's stumps —
         * a phone held low — the assumed 1.4 m stretched every band towards the camera: SHORT
         * ended almost at the bowler's stumps instead of six metres short of them. Two sets of
         * stumps are [PitchGeometry.STUMPS_TO_STUMPS_M] apart by the Laws, so the height is
         * the one at which, dropped onto the ground, they come out exactly that far apart.
         * The distance between them only shrinks as the height grows, so a bisection finds it.
         *
         * Null when no height in reach gives a full pitch (a short practice net, a mis-seen
         * second set) — the caller then keeps what it had.
         */
        fun fromTwoWickets(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            bowlersEnd: BowlersEnd,
        ): PitchGround? =
            solveHeight(lock, camera, widthPx, heightPx, bowlersEnd.base)
                ?: lensFromSpans(lock, bowlersEnd)?.let { solveHeight(lock, it, widthPx, heightPx, bowlersEnd.base) }

        /**
         * THE LENS, FROM THE TWO SETS' SIZES. Both sets are the Laws' width, so how much
         * smaller the far one looks, with 20.12 m between them, gives the focal length. Used
         * only when the lens the camera reported cannot make the pitch its full length — a
         * clip filmed in a cropped slow-motion mode, or re-encoded by a messenger: on a real
         * net video the assumed lens put the two sets 15 m apart and nothing could be solved.
         */
        private fun lensFromSpans(lock: WicketLock, bowlersEnd: BowlersEnd): CameraIntrinsics? {
            val far = lock.anchor.span(lock.aspect)
            val near = bowlersEnd.spanFw
            if (far <= 0.0 || near <= far * 2) return null
            val focal = PitchGeometry.STUMPS_TO_STUMPS_M / (PitchGeometry.STUMP_CENTRES_SPAN_M * (1.0 / far - 1.0 / near))
            if (focal < MIN_FOCAL_FW || focal > MAX_FOCAL_FW) return null
            return CameraIntrinsics(focal, "from the two sets of stumps")
        }

        private const val MIN_FOCAL_FW = 0.6
        private const val MAX_FOCAL_FW = 4.0

        private fun solveHeight(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            bowlersEnd: Point2,
        ): PitchGround? {
            val px = bowlersEnd.x * widthPx to bowlersEnd.y * heightPx
            fun at(h: Double) = fromWicket(lock, camera, widthPx, heightPx, h)?.aimedRaw(px)
            var lo = MIN_SOLVED_HEIGHT_M
            var hi = MAX_SOLVED_HEIGHT_M
            val low = at(lo)?.second ?: return null
            val high = at(hi)?.second ?: return null
            val target = PitchGeometry.STUMPS_TO_STUMPS_M
            if (low < target || high > target) return null
            repeat(24) {
                val mid = (lo + hi) / 2
                val len = at(mid)?.second ?: return null
                if (len > target) lo = mid else hi = mid
            }
            val g = at((lo + hi) / 2)?.first ?: return null
            return g.copyMeasured()
        }

        private const val MIN_SOLVED_HEIGHT_M = 0.15
        private const val MAX_SOLVED_HEIGHT_M = 4.0

        /*
         * The answer changes only when the stumps or the phone do, and it is asked for on every
         * drawn frame by the guide, by the stumps drawn at both ends, by the corridor and by the
         * 3D fit. Solving it each time — two bisections nested inside a third when both sets of
         * stumps are in view — froze the screen. So the last answer is kept, keyed by its
         * inputs rounded to well under a pixel.
         */
        private data class BestKey(val parts: List<Long>)

        private val bestCache = Any()
        private var bestKey: BestKey? = null
        private var bestValue: PitchGround? = null

        private fun keyOf(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            tilt: DeviceTilt.Reading?,
            bowlersEnd: BowlersEnd?,
        ): BestKey {
            fun q(v: Double, scale: Double) = Math.round(v * scale)
            val a = lock.anchor
            return BestKey(
                listOf(
                    q(a.baseLeft.x, 1e4), q(a.baseLeft.y, 1e4), q(a.baseRight.x, 1e4), q(a.baseRight.y, 1e4),
                    a.top?.let { q(it.x, 1e4) } ?: -1, a.top?.let { q(it.y, 1e4) } ?: -1,
                    lock.kind.ordinal.toLong(), q(camera.focalFw, 1e4), widthPx.toLong(), heightPx.toLong(),
                    tilt?.let { q(it.pitchRad, 1e3) } ?: Long.MIN_VALUE, tilt?.let { q(it.rollRad, 1e3) } ?: Long.MIN_VALUE,
                    bowlersEnd?.let { q(it.base.x, 1e4) } ?: -1, bowlersEnd?.let { q(it.base.y, 1e4) } ?: -1,
                    bowlersEnd?.let { q(it.spanFw, 1e4) } ?: -1,
                ),
            )
        }

        fun best(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            tilt: DeviceTilt.Reading? = DeviceTilt.steady(),
            /** The bowler's-end stumps, when they are in view. */
            bowlersEnd: BowlersEnd? = null,
        ): PitchGround? {
            val key = keyOf(lock, camera, widthPx, heightPx, tilt, bowlersEnd)
            synchronized(bestCache) { if (key == bestKey) return bestValue }
            val value = solveBest(lock, camera, widthPx, heightPx, tilt, bowlersEnd)
            synchronized(bestCache) {
                bestKey = key
                bestValue = value
            }
            return value
        }

        private fun solveBest(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            tilt: DeviceTilt.Reading?,
            bowlersEnd: BowlersEnd?,
        ): PitchGround? {
            // The pitch's own length beats both gravity and a guess, when it can be seen.
            bowlersEnd?.let { fromTwoWickets(lock, camera, widthPx, heightPx, it) }?.let { return it }
            val g = tilt?.let { fromWicketAndTilt(lock, camera, widthPx, heightPx, it) }
                ?: fromWicket(lock, camera, widthPx, heightPx)
                ?: return null
            return bowlersEnd?.let { g.aimedAt(it.base.x * widthPx to it.base.y * heightPx) } ?: g
        }

        private class Solved(val pitch: Double, val sX: Double, val sY: Double, val perspectiveSlope: Double)

        /**
         * With the roll taken out, the downward tilt at which the foot's ray lands on the
         * ground where the wicket, projected back, is exactly as wide as it measures. Wider
         * with more tilt (the foot comes nearer), so a bisection finds it.
         */
        private fun solve(lx: Double, ly: Double, rx: Double, ry: Double, roll: Double, f: Double, h: Double): Solved? {
            val cr = cos(-roll)
            val sr = sin(-roll)
            fun derot(x: Double, y: Double) = Pair(x * cr - y * sr, x * sr + y * cr)
            val a = derot(lx, ly)
            val b = derot(rx, ry)
            val rxN = (a.first + b.first) / 2.0 / f
            val ryN = (a.second + b.second) / 2.0 / f
            val spanPx = hypot(b.first - a.first, b.second - a.second)
            val half = PitchGeometry.STUMP_CENTRES_SPAN_M / 2.0

            fun groundAt(p: Double): DoubleArray? {
                val cp = cos(p)
                val sp = sin(p)
                val dz = -(ryN * cp + sp)
                if (dz >= -1e-9) return null
                val t = h / -dz
                return doubleArrayOf(t * rxN, t * (cp - ryN * sp))
            }
            fun feet(p: Double): Pair<Pair<Double, Double>, Pair<Double, Double>>? {
                val g = groundAt(p) ?: return null
                val d = hypot(g[0], g[1])
                if (d < 1e-6) return null
                val ax = g[1] / d
                val ay = -g[0] / d
                val cp = cos(p)
                val sp = sin(p)
                fun proj(x: Double, y: Double): Pair<Double, Double>? {
                    val yc = -y * sp + h * cp
                    val zc = y * cp + h * sp
                    if (zc <= 1e-6) return null
                    return Pair(f * x / zc, f * yc / zc)
                }
                val pa = proj(g[0] - ax * half, g[1] - ay * half) ?: return null
                val pb = proj(g[0] + ax * half, g[1] + ay * half) ?: return null
                return pa to pb
            }
            fun spanAt(p: Double): Double? = feet(p)?.let { (pa, pb) -> hypot(pb.first - pa.first, pb.second - pa.second) }

            var lo = -kotlin.math.atan(ryN) + 1e-4
            var hi = 1.35
            val sLo = spanAt(lo) ?: return null
            val sHi = spanAt(hi) ?: return null
            if ((sLo - spanPx) * (sHi - spanPx) > 0) return null
            repeat(60) {
                val mid = (lo + hi) / 2
                val sm = spanAt(mid) ?: return null
                if ((sLo - spanPx) * (sm - spanPx) <= 0) hi = mid else lo = mid
            }
            val pitch = (lo + hi) / 2
            val g = groundAt(pitch) ?: return null
            val (pa, pb) = feet(pitch) ?: return null
            return Solved(pitch, g[0], g[1], atan2(pb.second - pa.second, pb.first - pa.first))
        }

        /** True when two pixel points are within [tol] of each other. Test helper. */
        internal fun near(a: Pair<Double, Double>, b: Pair<Double, Double>, tol: Double) =
            abs(a.first - b.first) <= tol && abs(a.second - b.second) <= tol
    }
}
