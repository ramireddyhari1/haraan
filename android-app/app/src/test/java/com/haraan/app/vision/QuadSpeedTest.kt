package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Speed from the pitch corners, against a REAL pinhole camera.
 *
 * Not the ground-plane shortcut the bounce tests use: here a lens with a focal length sits
 * at a height behind the bowler, the four crease corners are projected through it to make
 * the quad, and a ball is flown ballistically at a known speed and projected through the
 * same lens. The estimator never sees the camera — it has to recover it from the corners —
 * and has to hand back the speed the ball was flown at.
 */
class QuadSpeedTest {

    private val aspect = 16.0 / 9.0

    private class Lens(
        val centre: DoubleArray,
        target: DoubleArray,
        val focal: Double,
        val aspect: Double,
    ) {
        private val forward = unit(DoubleArray(3) { target[it] - centre[it] })
        private val right = unit(cross(doubleArrayOf(0.0, 0.0, 1.0), forward))
        private val down = cross(right, forward)

        fun project(p: DoubleArray): Point2 {
            val rel = DoubleArray(3) { p[it] - centre[it] }
            val z = dot(rel, forward)
            val u = focal * dot(rel, right) / z
            val v = focal * dot(rel, down) / z
            return Point2(u / aspect + 0.5, v + 0.5)
        }

        companion object {
            fun dot(a: DoubleArray, b: DoubleArray) = a[0] * b[0] + a[1] * b[1] + a[2] * b[2]
            fun unit(a: DoubleArray) = sqrt(dot(a, a)).let { n -> DoubleArray(3) { a[it] / n } }
            fun cross(a: DoubleArray, b: DoubleArray) = doubleArrayOf(
                a[1] * b[2] - a[2] * b[1],
                a[2] * b[0] - a[0] * b[2],
                a[0] * b[1] - a[1] * b[0],
            )
        }
    }

    /** Four metres behind the bowler's stumps, chest height, looking down the pitch. */
    private fun lens(height: Double = 1.6, behind: Double = 4.0, focal: Double = 1.4, offset: Double = 0.0) = Lens(
        centre = doubleArrayOf(offset, PitchGeometry.STUMPS_TO_STUMPS_M + behind, height),
        target = doubleArrayOf(0.0, 6.0, 0.0),
        focal = focal,
        aspect = aspect,
    )

    private fun quadFrom(lens: Lens) = PitchQuad(
        corners = PitchGeometry.calibrationCorners(CameraEnd.BOWLER).map {
            lens.project(doubleArrayOf(it.x, it.y, 0.0))
        },
        source = QuadSource.TAPPED,
        confidence = 1f,
        cameraEnd = CameraEnd.BOWLER,
    )

    /**
     * A ball released at 2.1 m from 18.4 m, travelling at [kmh] over the ground, pitching
     * [lengthM] from the striker, then climbing away slower. 30 fps, picked up a little
     * after release, with the bounce falling between frames.
     */
    private fun delivery(
        lens: Lens,
        kmh: Double,
        lengthM: Double = 6.0,
        lineM: Double = 0.1,
        noise: (Int) -> Double = { 0.0 },
    ): List<BallSighting> {
        val speed = kmh / 3.6
        val releaseY = 18.4
        val releaseH = 2.1
        val g = 9.81
        val tBounce = (releaseY - lengthM) / speed
        // z(t) = releaseH + vz0 t - g t²/2, landing at tBounce.
        val vz0 = (g * tBounce * tBounce / 2 - releaseH) / tBounce
        val vzIn = vz0 - g * tBounce // negative: coming down
        val vzOut = -0.5 * vzIn
        val after = 0.85 * speed
        val lateral = lineM / tBounce

        val out = ArrayList<BallSighting>()
        var t = 0.05
        var i = 0
        while (true) {
            val p = if (t <= tBounce) {
                doubleArrayOf(lateral * t, releaseY - speed * t, releaseH + vz0 * t - g * t * t / 2)
            } else {
                val s = t - tBounce
                doubleArrayOf(lateral * t, lengthM - after * s, vzOut * s - g * s * s / 2)
            }
            if (p[1] < 1.0 || p[2] < 0) break
            val img = lens.project(p)
            out.add(
                BallSighting(
                    timestampMs = (t * 1000).toLong(),
                    x = (img.x + noise(i)).toFloat(),
                    y = (img.y + noise(i + 7)).toFloat(),
                    trackingConfidence = 0.8f,
                    areaPx = 30,
                ),
            )
            t += 1.0 / 30.0
            i++
        }
        return out
    }

    private fun speedOf(track: List<BallSighting>, quad: PitchQuad): MetricValue {
        val bounce = BouncePoint.find(track, quad)
        assertNotNull("the bounce has to be found first", bounce)
        return QuadSpeed.estimate(track, quad, bounce!!, aspect.toFloat())
    }

    private fun assertKmh(expected: Double, value: MetricValue, tolerancePct: Double) {
        assertTrue("expected an estimate, got $value", value is MetricValue.Estimated)
        val got = (value as MetricValue.Estimated).value
        assertTrue("expected ~$expected km/h, got $got", abs(got - expected) / expected * 100 <= tolerancePct)
    }

    @Test
    fun `the camera is recovered from the corners`() {
        val camera = PitchCamera.from(quadFrom(lens()), aspect.toFloat())
        assertNotNull(camera)
        assertEquals(1.4, camera!!.focal, 0.02)
        assertEquals(1.6, camera.heightM, 0.05)
        assertEquals(PitchGeometry.STUMPS_TO_STUMPS_M + 4.0, camera.centre[1], 0.1)
    }

    @Test
    fun `a medium pacer is read at his speed`() {
        val lens = lens()
        assertKmh(120.0, speedOf(delivery(lens, kmh = 120.0), quadFrom(lens)), 5.0)
    }

    @Test
    fun `a spinner is read at his speed`() {
        val lens = lens()
        assertKmh(80.0, speedOf(delivery(lens, kmh = 80.0, lengthM = 4.0), quadFrom(lens)), 5.0)
    }

    @Test
    fun `a higher, offset tripod still reads right`() {
        val lens = lens(height = 2.4, behind = 6.0, focal = 1.8, offset = 0.4)
        assertKmh(130.0, speedOf(delivery(lens, kmh = 130.0, lengthM = 7.0), quadFrom(lens)), 6.0)
    }

    @Test
    fun `a quick bowler banging it in is read at his speed`() {
        val lens = lens()
        assertKmh(142.0, speedOf(delivery(lens, kmh = 142.0, lengthM = 9.0), quadFrom(lens)), 5.0)
    }

    @Test
    fun `a pixel of detection noise keeps it close`() {
        val lens = lens()
        // About a pixel either way on a 1080p frame, deterministic.
        val noise = { i: Int -> ((i * 7919) % 11 - 5) / 5.0 * 0.0009 }
        assertKmh(120.0, speedOf(delivery(lens, kmh = 120.0, noise = noise), quadFrom(lens)), 10.0)
    }

    @Test
    fun `the fitted 3D flight pitches where the ball did and carries on to the stumps`() {
        val lens = lens()
        val track = delivery(lens, kmh = 120.0, lengthM = 6.0, lineM = 0.1)
        val quad = quadFrom(lens)
        val bounce = BouncePoint.find(track, quad)!!
        val fit = QuadSpeed.fit(track, quad, bounce, aspect.toFloat())
        assertTrue("expected a fit, got $fit", fit is QuadSpeed.FitResult.Ok)
        val flight = (fit as QuadSpeed.FitResult.Ok).flight
        assertEquals(6.0, flight.bounceY, 0.25)
        assertEquals(0.1, flight.bounceX, 0.1)
        assertTrue(flight.hasOutLeg)
        val crossing = flight.atStumps
        assertNotNull(crossing)
        assertEquals(0.0, crossing!!.y, 1e-6)
        // Released at 2.1 m, so the drawn path starts up in the air near the bowler.
        val release = flight.at(flight.releaseMs)
        assertTrue("release should be up in the air, was ${release.z}", release.z > 1.0)
    }

    @Test
    fun `corners from the striker's end are refused`() {
        val lens = lens()
        val quad = quadFrom(lens).copy(cameraEnd = CameraEnd.STRIKER)
        val bounce = Bounce(Point2(0.5, 0.5), Point2(0.0, 6.0), 8, QuadSource.TAPPED, CameraEnd.STRIKER, atMs = 300.0)
        val value = QuadSpeed.estimate(delivery(lens, 120.0), quad, bounce, aspect.toFloat())
        assertTrue(value is MetricValue.Unavailable)
    }
}
