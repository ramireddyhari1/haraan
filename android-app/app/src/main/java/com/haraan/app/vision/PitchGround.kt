package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.sin
import kotlin.math.sqrt

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
        fun best(
            lock: WicketLock,
            camera: CameraIntrinsics,
            widthPx: Int,
            heightPx: Int,
            tilt: DeviceTilt.Reading? = DeviceTilt.steady(),
        ): PitchGround? =
            tilt?.let { fromWicketAndTilt(lock, camera, widthPx, heightPx, it) }
                ?: fromWicket(lock, camera, widthPx, heightPx)

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
