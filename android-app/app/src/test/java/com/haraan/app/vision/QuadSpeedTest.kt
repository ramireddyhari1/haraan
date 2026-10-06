package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
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
    fun `the path lands on the real picture - stumps and bounce where the lens put them`() {
        val lens = lens()
        val track = delivery(lens, kmh = 120.0, lengthM = 6.0, lineM = 0.1)
        val quad = quadFrom(lens)
        val bounce = BouncePoint.find(track, quad)!!
        val flight = (QuadSpeed.fit(track, quad, bounce, aspect.toFloat()) as QuadSpeed.FitResult.Ok).flight
        val path = ArPath.fromFlight(flight, quad, aspect.toFloat())!!

        // Middle stump's foot, through the recovered camera and through the true lens.
        val trueFoot = lens.project(doubleArrayOf(0.0, 0.0, 0.0))
        val drawnFoot = path.stumps[1].first
        assertEquals(trueFoot.x, drawnFoot.x, 0.004)
        assertEquals(trueFoot.y, drawnFoot.y, 0.004)
        val trueTop = lens.project(doubleArrayOf(0.0, 0.0, PitchGeometry.STUMP_HEIGHT_M))
        assertEquals(trueTop.y, path.stumps[1].second.y, 0.004)

        // The drawn path passes over the sightings it was fitted to.
        val seen = track.filter { it.timestampMs.toDouble() in path.startMs..path.endMs }
        for (s in seen) {
            val nearest = path.points.minByOrNull { abs(it.ms - s.timestampMs) }!!
            assertTrue("path strays from the ball at ${s.timestampMs}", abs(nearest.x - s.x) < 0.02 && abs(nearest.y - s.y) < 0.03)
        }
        assertTrue(path.points.any { it.leg == ArLeg.IN } && path.points.any { it.leg == ArLeg.OUT })
    }

    // ── Calibrating from the two sets of stumps ──

    private fun stumpTaps(lens: Lens, end: CameraEnd, noise: (Int) -> Double = { 0.0 }): StumpCalibration.Taps {
        val pts = StumpCalibration.worldPoints(end).mapIndexed { i, p ->
            val img = lens.project(doubleArrayOf(p.x, p.y, p.z))
            Point2(img.x + noise(i), img.y + noise(i + 11))
        }
        return StumpCalibration.Taps(pts[0], pts[1], pts[2], pts[3], pts[4], pts[5])
    }

    @Test
    fun `six stump taps from behind the bowler give the same pitch as the creases`() {
        val lens = lens()
        val result = StumpCalibration.solve(stumpTaps(lens, CameraEnd.BOWLER), CameraEnd.BOWLER, aspect.toFloat())
        assertNotNull(result)
        assertTrue("rms ${result!!.rmsError}", result.trustworthy)
        val truth = quadFrom(lens).corners
        result.quad.corners.zip(truth).forEach { (got, want) ->
            assertEquals(want.x, got.x, 0.004)
            assertEquals(want.y, got.y, 0.004)
        }
        assertEquals(1.6, result.cameraHeightM, 0.05)
    }

    @Test
    fun `six stump taps from behind the batter calibrate that end, and the speed follows`() {
        val lens = batterEndLens()
        val result = StumpCalibration.solve(stumpTaps(lens, CameraEnd.STRIKER), CameraEnd.STRIKER, aspect.toFloat())!!
        assertTrue(result.trustworthy)
        val track = delivery(lens, kmh = 130.0, lengthM = 5.0)
        val fit = QuadSpeed.fit(track, result.quad, null, aspect.toFloat()) as QuadSpeed.FitResult.Ok
        assertTrue("expected ~130, got ${fit.flight.speedKmh}", abs(fit.flight.speedKmh - 130.0) / 130.0 < 0.05)
    }

    @Test
    fun `a pixel of tapping error still gives a usable speed`() {
        val lens = batterEndLens()
        val noise = { i: Int -> ((i * 7919) % 11 - 5) / 5.0 * 0.0008 }
        val result = StumpCalibration.solve(stumpTaps(lens, CameraEnd.STRIKER, noise), CameraEnd.STRIKER, aspect.toFloat())!!
        val track = delivery(lens, kmh = 120.0, lengthM = 6.0)
        val fit = QuadSpeed.fit(track, result.quad, null, aspect.toFloat()) as QuadSpeed.FitResult.Ok
        assertTrue("expected ~120, got ${fit.flight.speedKmh}", abs(fit.flight.speedKmh - 120.0) / 120.0 < 0.10)
    }

    @Test
    fun `a tap on the wrong thing is caught`() {
        val lens = lens()
        val good = stumpTaps(lens, CameraEnd.BOWLER)
        // The far top tapped at the far left foot by mistake.
        val bad = good.copy(farMiddleTop = Point2(good.farLeftFoot.x, good.farLeftFoot.y + 0.05))
        val result = StumpCalibration.solve(bad, CameraEnd.BOWLER, aspect.toFloat())
        assertTrue(result == null || !result.trustworthy)
    }

    /** Behind the BATTER's stumps on a tall tripod, looking back at the bowler — Fulltrack's set-up. */
    private fun batterEndLens(height: Double = 1.7, behind: Double = 4.0, focal: Double = 1.3) = Lens(
        centre = doubleArrayOf(0.1, -behind, height),
        target = doubleArrayOf(0.0, 12.0, 0.0),
        focal = focal,
        aspect = aspect,
    )

    private fun batterEndQuad(lens: Lens) = PitchQuad(
        corners = PitchGeometry.calibrationCorners(CameraEnd.STRIKER).map {
            lens.project(doubleArrayOf(it.x, it.y, 0.0))
        },
        source = QuadSource.TAPPED,
        confidence = 1f,
        cameraEnd = CameraEnd.STRIKER,
    )

    @Test
    fun `from the batter's end the camera is recovered`() {
        val lens = batterEndLens()
        val camera = PitchCamera.from(batterEndQuad(lens), aspect.toFloat())
        assertNotNull(camera)
        assertEquals(1.7, camera!!.heightM, 0.06)
        assertEquals(-4.0, camera.centre[1], 0.15)
    }

    @Test
    fun `from the batter's end the speed and the bounce are found with no bounce to start from`() {
        val lens = batterEndLens()
        val quad = batterEndQuad(lens)
        // The bounce finder refuses this end, by design.
        val track = delivery(lens, kmh = 125.0, lengthM = 6.0, lineM = 0.1)
        assertNull(BouncePoint.find(track, quad))

        val fit = QuadSpeed.fit(track, quad, null, aspect.toFloat())
        assertTrue("expected a fit, got $fit", fit is QuadSpeed.FitResult.Ok)
        val flight = (fit as QuadSpeed.FitResult.Ok).flight
        assertTrue("expected ~125 km/h, got ${flight.speedKmh}", abs(flight.speedKmh - 125.0) / 125.0 < 0.05)
        assertEquals(6.0, flight.bounceY, 0.35)
    }

    @Test
    fun `from the batter's end a spinner is read at his speed`() {
        val lens = batterEndLens(height = 2.0, behind = 6.0, focal = 1.6)
        val track = delivery(lens, kmh = 85.0, lengthM = 4.5)
        val fit = QuadSpeed.fit(track, batterEndQuad(lens), null, aspect.toFloat()) as QuadSpeed.FitResult.Ok
        assertTrue("expected ~85 km/h, got ${fit.flight.speedKmh}", abs(fit.flight.speedKmh - 85.0) / 85.0 < 0.05)
    }

    @Test
    fun `behind the bowler the whole-flight search agrees with the bounce-led one`() {
        val lens = lens()
        val track = delivery(lens, kmh = 120.0)
        val quad = quadFrom(lens)
        val scanned = QuadSpeed.fit(track, quad, null, aspect.toFloat()) as QuadSpeed.FitResult.Ok
        assertTrue(abs(scanned.flight.speedKmh - 120.0) / 120.0 < 0.05)
    }

    @Test
    fun `the 3D flight gives the LBW answer, from either end`() {
        val lens = batterEndLens()
        val track = delivery(lens, kmh = 120.0, lengthM = 6.0, lineM = 0.05)
        val flight = (QuadSpeed.fit(track, batterEndQuad(lens), null, aspect.toFloat()) as QuadSpeed.FitResult.Ok).flight
        val lbw = LbwProjector.fromFlight(flight)
        assertNotNull(lbw)
        assertTrue(lbw!!.verdict != LbwVerdict.UNAVAILABLE)
    }
}
