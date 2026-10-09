package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.sin

/**
 * The length guide must lie on the ground. A camera is built here independently — height,
 * downward tilt, roll, a wicket somewhere on the ground — the wicket's feet are projected
 * through it, and the lock made from those pixels alone must reproduce where every other
 * metre of the pitch falls.
 */
class PitchGroundTest {

    private val w = 720
    private val h = 1280
    private val focalFw = 1.4 // ~39° across a portrait frame, as the Realme reports

    private class Cam(val f: Double, val hM: Double, val pitch: Double, val roll: Double, val cx: Double, val cy: Double) {
        fun project(x: Double, y: Double): Pair<Double, Double> {
            val vz = -hM
            val s = sin(pitch); val c = cos(pitch)
            val yc = -y * s - vz * c
            val zc = y * c - vz * s
            val ux = f * x / zc
            val uy = f * yc / zc
            val cr = cos(roll); val sr = sin(roll)
            return Pair(cx + ux * cr - uy * sr, cy + ux * sr + uy * cr)
        }
    }

    private fun scene(stumpsX: Double, stumpsY: Double, camH: Double, pitch: Double, roll: Double): Triple<Cam, WicketLock, Pair<Double, Double>> {
        val cam = Cam(focalFw * w, camH, pitch, roll, w / 2.0, h / 2.0)
        val d = hypot(stumpsX, stumpsY)
        val tx = -stumpsX / d; val ty = -stumpsY / d       // towards the camera
        val lx = -ty; val ly = tx                           // across, picture-right
        val half = PitchGeometry.STUMP_CENTRES_SPAN_M / 2
        val left = cam.project(stumpsX - lx * half, stumpsY - ly * half)
        val right = cam.project(stumpsX + lx * half, stumpsY + ly * half)
        val lock = WicketLock(
            anchor = WicketAnchor(Point2(left.first / w, left.second / h), Point2(right.first / w, right.second / h)),
            kind = WicketKind.STUMPS, source = WicketLockSource.DETECTED, state = WicketTrackState.CONFIRMED,
            confidence = 1f, ageFrames = 0, jitter = 0f, heldFrames = 30, aspect = w.toFloat() / h, subPixel = true,
        )
        return Triple(cam, lock, tx to ty)
    }

    private fun check(stumpsX: Double, stumpsY: Double, pitch: Double, roll: Double) {
        val (cam, lock, toward) = scene(stumpsX, stumpsY, 1.4, pitch, roll)
        val ground = PitchGround.fromWicket(lock, CameraIntrinsics(focalFw, "test"), w, h, 1.4)
        assertNotNull(ground)
        assertEquals(hypot(stumpsX, stumpsY), ground!!.stumpsDistanceM, 0.05)
        val (tx, ty) = toward
        // Only ground in front of the camera: a wicket 5 m away has no 9 m mark.
        val alongs = listOf(1.0, 3.0, 6.0, 9.0).filter { it < ground.stumpsDistanceM - 1.0 }
        for (along in alongs) for (across in listOf(-1.32, 0.0, 1.32)) {
            val truth = cam.project(stumpsX + tx * along - ty * across, stumpsY + ty * along + tx * across)
            val got = ground.project(along, across)!!
            assertTrue(
                "along $along across $across: got $got, truth $truth",
                PitchGround.near(got, truth, 1.0),
            )
        }
    }

    @Test
    fun `a level camera behind the arm puts every metre of pitch where the camera does`() =
        check(stumpsX = 0.0, stumpsY = 18.0, pitch = 0.06, roll = 0.0)

    @Test
    fun `a wicket off to one side and a rolled phone still project exactly`() =
        check(stumpsX = 1.2, stumpsY = 9.0, pitch = 0.12, roll = Math.toRadians(3.0))

    @Test
    fun `a near wicket looked down on steeply projects exactly`() =
        check(stumpsX = -0.4, stumpsY = 5.0, pitch = 0.25, roll = 0.0)

    @Test
    fun `zones grow as they come nearer the camera`() {
        val (_, lock, _) = scene(0.0, 18.0, 1.4, 0.06, 0.0)
        val g = PitchGround.fromWicket(lock, CameraIntrinsics(focalFw, "test"), w, h)!!
        val y = listOf(0.0, 1.0, 3.0, 6.0, 9.0).map { g.project(it, 0.0)!!.second }
        val bands = y.zipWithNext { a, b -> b - a }
        // Yorker (1 m) is thinner on screen than full (2 m), and full than good (3 m).
        assertTrue("bands $bands", bands[0] < bands[1] && bands[1] < bands[2])
    }

    @Test
    fun `the visible stretch stops above the line it is told to`() {
        val (_, lock, _) = scene(0.0, 8.0, 1.4, 0.10, 0.0)
        val g = PitchGround.fromWicket(lock, CameraIntrinsics(focalFw, "test"), w, h)!!
        val limit = h * 0.8
        val d = g.visibleTo(limit)
        assertTrue(g.project(d, 0.0)!!.second <= limit)
        assertTrue(g.project(d + 0.25, 0.0)?.second?.let { it > limit } ?: true)
    }

    @Test
    fun `a stone gives no ground`() {
        val (_, lock, _) = scene(0.0, 18.0, 1.4, 0.06, 0.0)
        assertNull(PitchGround.fromWicket(lock.copy(kind = WicketKind.STONE), CameraIntrinsics(focalFw, "t"), w, h))
    }

    // ---- the stumps-only camera, used for the 3D flight and the replay ------------------

    @Test
    fun `the stumps-only camera sees every pitch point where the ground model does`() {
        val (_, lock, _) = scene(0.8, 12.0, 1.4, 0.09, Math.toRadians(2.0))
        val g = PitchGround.fromWicket(lock, CameraIntrinsics(focalFw, "test"), w, h)!!
        val cam = g.pitchCamera(w, h)
        for (along in listOf(0.0, 1.0, 3.0, 6.0)) for (across in listOf(-1.32, 0.0, 1.0)) {
            val px = g.project(along, across)!!
            val p = cam.project(Point3(across, along, 0.0))!!
            assertEquals("x at $along,$across", px.first / w, p.x, 1e-6)
            assertEquals("y at $along,$across", px.second / h, p.y, 1e-6)
        }
        assertEquals(1.4, cam.heightM, 1e-6)
    }

    /**
     * The point of it: a delivery filmed on a ground with NO pitch corners still gets a 3D
     * flight — and so the path on the picture and the replay — from the stumps alone.
     */
    @Test
    fun `a delivery with no pitch corners is fitted in 3D from the stumps alone`() {
        val (_, lock, _) = scene(0.0, 16.0, 1.4, 0.07, 0.0)
        val g = PitchGround.fromWicket(lock, CameraIntrinsics(focalFw, "test"), w, h)!!
        val cam = g.pitchCamera(w, h)

        // A 90 km/h ball released 2 m above the pitch 14 m from the striker's stumps,
        // bouncing at 5 m, then on to the stumps. Filmed at 50 fps.
        val speed = 90 / 3.6
        val g0 = 9.81
        val releaseY = 14.0
        val bounceY = 5.0
        val tIn = (releaseY - bounceY) / speed
        val vz = (0.0 - 2.0 + 0.5 * g0 * tIn * tIn) / tIn // lands at z = 0 at the bounce
        val track = ArrayList<BallSighting>()
        var t = 0.0
        while (true) {
            val y: Double
            val z: Double
            if (t <= tIn) {
                y = releaseY - speed * t
                z = 2.0 + vz * t - 0.5 * g0 * t * t
            } else {
                val s = t - tIn
                val vOut = speed * 0.85
                y = bounceY - vOut * s
                z = (2.5 * s - 0.5 * g0 * s * s).coerceAtLeast(0.0)
                if (y < 0.5) break
            }
            val p = cam.project(Point3(0.15, y, z)) ?: break
            track.add(BallSighting(timestampMs = (1000 + t * 1000).toLong(), x = p.x.toFloat(), y = p.y.toFloat(), trackingConfidence = 0.9f, areaPx = 40))
            t += 0.02
        }
        val metrics = FlightMetrics.of(track, w.toFloat() / h, quad = null, wicket = lock, ground = cam)
        val flight = metrics.flight3d
        assertNotNull("no 3D flight without corners", flight)
        assertEquals("speed", 90.0, flight!!.speedKmh, 9.0)
        assertEquals("bounce along the pitch", bounceY, flight.bounceY, 1.0)
        assertNotNull("a path for the picture", ArPath.fromFlight(flight, cam))
        val bounce = metrics.bounce
        assertNotNull(bounce)
        assertEquals(QuadSource.WICKET, bounce!!.quadSource)
    }

    // ---- the phone's height, measured from its tilt ---------------------------------

    /** What a phone's gravity sensor reads when it is tilted down by [pitch] and rolled by [roll]. */
    private fun gravity(pitch: Double, roll: Double): Triple<Double, Double, Double> {
        val g = 9.81
        return Triple(g * cos(pitch) * sin(roll), g * cos(pitch) * cos(roll), g * sin(pitch))
    }

    @Test
    fun `gravity gives back the tilt and roll it was made from`() {
        for ((pitch, roll) in listOf(0.0 to 0.0, 0.2 to 0.03, 0.35 to -0.05, -0.05 to 0.1)) {
            val (gx, gy, gz) = gravity(pitch, roll)
            val r = DeviceTilt.reading(gx, gy, gz)!!
            assertEquals(pitch, r.pitchRad, 1e-9)
            assertEquals(roll, r.rollRad, 1e-9)
        }
        // Flat on its back, looking at the sky: no reading.
        assertNull(DeviceTilt.reading(0.0, 0.3, 9.8))
    }

    @Test
    fun `with the tilt known, the phone's height is measured and every metre lands right`() {
        for ((camH, stumps) in listOf(0.8 to (0.4 to 6.0), 1.2 to (0.0 to 9.0), 1.7 to (-0.6 to 14.0), 1.4 to (0.3 to 18.0))) {
            val pitch = kotlin.math.atan(camH / stumps.second) + 0.03
            val roll = Math.toRadians(1.5)
            val (cam, lock, toward) = scene(stumps.first, stumps.second, camH, pitch, roll)
            val (gx, gy, gz) = gravity(pitch, roll)
            val g = PitchGround.fromWicketAndTilt(lock, CameraIntrinsics(focalFw, "test"), w, h, DeviceTilt.reading(gx, gy, gz)!!)
            assertNotNull("height $camH", g)
            assertTrue(g!!.heightMeasured)
            assertEquals("height", camH, g.heightM, 0.01)
            assertEquals("distance", hypot(stumps.first, stumps.second), g.stumpsDistanceM, 0.05)
            val (tx, ty) = toward
            for (along in listOf(1.0, 3.0).filter { it < g.stumpsDistanceM - 1.0 }) for (across in listOf(-1.32, 0.0, 1.32)) {
                val truth = cam.project(stumps.first + tx * along - ty * across, stumps.second + ty * along + tx * across)
                assertTrue("along $along across $across", PitchGround.near(g.project(along, across)!!, truth, 1.0))
            }
            assertEquals(camH, g.pitchCamera(w, h).heightM, 0.01)
        }
    }

    /**
     * How much a slightly wrong sensor costs. A phone's gravity reading and the lens's
     * mounting are each good to a few tenths of a degree; half a degree in all must keep the
     * height within ~10 cm at a backyard distance — against the 0.6 m spread of guessing.
     */
    @Test
    fun `half a degree of tilt error moves the height by centimetres, not half a metre`() {
        val camH = 1.1
        val pitch = kotlin.math.atan(camH / 6.0) + 0.05
        val (_, lock, _) = scene(0.2, 6.0, camH, pitch, 0.0)
        for (err in listOf(-0.5, 0.5)) {
            val (gx, gy, gz) = gravity(pitch + Math.toRadians(err), 0.0)
            val g = PitchGround.fromWicketAndTilt(lock, CameraIntrinsics(focalFw, "test"), w, h, DeviceTilt.reading(gx, gy, gz)!!)!!
            println("tilt error $err°: height %.3f m (true $camH)".format(g.heightM))
            assertEquals(camH, g.heightM, 0.10)
        }
    }

    @Test
    fun `a wild reading is refused and the assumption stands`() {
        val (_, lock, _) = scene(0.0, 8.0, 1.1, 0.2, 0.0)
        // Told the phone looks UP: the feet's rays never reach the ground.
        val up = DeviceTilt.Reading(pitchRad = -0.4, rollRad = 0.0)
        assertNull(PitchGround.fromWicketAndTilt(lock, CameraIntrinsics(focalFw, "test"), w, h, up))
        val best = PitchGround.best(lock, CameraIntrinsics(focalFw, "test"), w, h, up)!!
        assertTrue(!best.heightMeasured)
        assertEquals(PitchGround.DEFAULT_CAMERA_HEIGHT_M, best.heightM, 1e-9)
    }
}
