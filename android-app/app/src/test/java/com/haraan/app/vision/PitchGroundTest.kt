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
}
