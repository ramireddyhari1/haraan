package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.PI
import kotlin.math.sin
import kotlin.math.sqrt
import kotlin.math.tan

/**
 * Swing and turn in the 3D flight, against a real pinhole camera behind the bowler.
 *
 * The straight fit drew every delivery as a pace bowler's: no sideways curve into the bounce
 * and the same line out of it. These fly a spinner that turns off the pitch and a seamer
 * that swings in the air, and — the half that matters as much — straight balls with pixel
 * noise, which must never come back bent.
 */
class SwingTurnFitTest {

    private val aspect = 16.0 / 9.0

    private class Lens(val centre: DoubleArray, target: DoubleArray, val focal: Double, val aspect: Double) {
        private val forward = unit(DoubleArray(3) { target[it] - centre[it] })
        private val right = unit(cross(doubleArrayOf(0.0, 0.0, 1.0), forward))
        private val down = cross(right, forward)

        fun project(p: DoubleArray): Point2 {
            val rel = DoubleArray(3) { p[it] - centre[it] }
            val z = dot(rel, forward)
            return Point2(focal * dot(rel, right) / z / aspect + 0.5, focal * dot(rel, down) / z + 0.5)
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

    private val lens = Lens(
        centre = doubleArrayOf(0.0, PitchGeometry.STUMPS_TO_STUMPS_M + 4.0, 1.6),
        target = doubleArrayOf(0.0, 6.0, 0.0),
        focal = 1.4,
        aspect = aspect,
    )

    private val quad = PitchQuad(
        corners = PitchGeometry.calibrationCorners(CameraEnd.BOWLER).map { lens.project(doubleArrayOf(it.x, it.y, 0.0)) },
        source = QuadSource.TAPPED,
        confidence = 1f,
        cameraEnd = CameraEnd.BOWLER,
    )

    /** Deterministic noise in frame widths: [px] pixels at 1280 wide, a fixed pattern per [seed]. */
    private fun noise(px: Double, seed: Int): (Int) -> Double = { i ->
        val h = sin((i + 1) * 12.9898 + seed * 78.233) * 43758.5453
        (h - kotlin.math.floor(h) - 0.5) * 2 * px / 1280.0
    }

    /**
     * Released at 2.1 m from 18.4 m at [kmh] over the ground, aimed [aimLineM] across at the
     * bounce, pulled sideways at [swingAccel] m/s² in the air, pitching [lengthM] from the
     * striker, and turning [turnDeg] off the pitch (+ = towards +x). 30 fps.
     */
    private fun delivery(
        kmh: Double,
        lengthM: Double,
        swingAccel: Double = 0.0,
        turnDeg: Double = 0.0,
        aimLineM: Double = 0.0,
        fps: Double = 30.0,
        noise: (Int) -> Double = { 0.0 },
    ): List<BallSighting> {
        val speed = kmh / 3.6
        val releaseY = 18.4
        val releaseH = 2.1
        val g = 9.81
        val tb = (releaseY - lengthM) / speed
        val vz0 = (g * tb * tb / 2 - releaseH) / tb
        val vzOut = -0.5 * (vz0 - g * tb)
        // Sideways: x(t) = vx0 t + a t²/2, ending at the aimed line.
        val vx0 = (aimLineM - swingAccel * tb * tb / 2) / tb
        val vxIn = vx0 + swingAccel * tb
        val outAlong = 0.88 * speed
        val inAngle = kotlin.math.atan2(vxIn, speed)
        val outVx = outAlong * tan(inAngle + turnDeg * PI / 180)

        val out = ArrayList<BallSighting>()
        var t = 0.05
        var i = 0
        while (true) {
            val p = if (t <= tb) {
                doubleArrayOf(vx0 * t + swingAccel * t * t / 2, releaseY - speed * t, releaseH + vz0 * t - g * t * t / 2)
            } else {
                val s = t - tb
                doubleArrayOf(aimLineM + outVx * s, lengthM - outAlong * s, vzOut * s - g * s * s / 2)
            }
            if (p[1] < 0.3 || p[2] < 0) break
            val img = lens.project(p)
            out.add(
                BallSighting(
                    timestampMs = (t * 1000).toLong(),
                    x = (img.x + noise(i)).toFloat(),
                    y = (img.y + noise(i + 101)).toFloat(),
                    trackingConfidence = 0.8f,
                    areaPx = 30,
                ),
            )
            t += 1.0 / fps
            i++
        }
        return out
    }

    private fun fit(track: List<BallSighting>): Flight3d {
        val result = QuadSpeed.fit(track, quad, BouncePoint.find(track, quad), aspect.toFloat())
        assertTrue("expected a fit, got $result", result is QuadSpeed.FitResult.Ok)
        return (result as QuadSpeed.FitResult.Ok).flight
    }

    @Test
    fun `a straight ball stays straight, with and without pixel noise`() {
        for ((kmh, length) in listOf(130.0 to 6.0, 80.0 to 4.0, 55.0 to 3.5)) {
            for (seed in 0 until 8) {
                val f = fit(delivery(kmh, length, aimLineM = 0.05, noise = if (seed == 0) { _ -> 0.0 } else noise(1.0, seed)))
                assertEquals("swing invented at $kmh km/h seed $seed", 0.0, f.inAx, 0.0)
                assertFalse("turn invented at $kmh km/h seed $seed (${f.turnDeg}°)", f.turnFitted)
            }
        }
    }

    @Test
    fun `heavy noise on a straight ball is not read as a bend`() {
        for (seed in 1 until 12) {
            val f = fit(delivery(75.0, 4.0, noise = noise(3.0, seed)))
            assertEquals("swing invented, seed $seed", 0.0, f.inAx, 0.0)
            assertFalse("turn invented, seed $seed (${f.turnDeg}°)", f.turnFitted)
        }
    }

    @Test
    fun `a leg-break that turns is drawn turning`() {
        for (turn in listOf(6.0, -4.0, 10.0)) {
            val f = fit(delivery(65.0, 3.5, turnDeg = turn, noise = noise(1.0, 3)))
            assertTrue("turn of $turn° not fitted", f.turnFitted)
            assertEquals("turn of $turn°", turn, f.turnDeg, 1.5)
            // The path after the bounce follows it: off the straight line by the stumps.
            val at = f.atStumps!!
            val straightX = f.bounceX + 0.88 * f.inVx * (0.0 - f.bounceY) / (0.88 * f.inVy)
            assertTrue("path at the stumps did not move with the turn", (at.x - straightX) * turn > 0)
            assertEquals("speed with turn", 65.0, f.groundKmh, 65.0 * 0.04)
        }
    }

    @Test
    fun `an outswinger is drawn curving into the bounce`() {
        for (accel in listOf(4.0, -3.0)) {
            val f = fit(delivery(125.0, 6.0, swingAccel = accel, aimLineM = 0.1, noise = noise(1.0, 5)))
            assertTrue("swing of $accel m/s² not fitted", f.inAx != 0.0)
            assertEquals("swing accel", accel, f.inAx, 1.5)
            assertTrue("swing points the wrong way", f.swingM * accel > 0)
            assertEquals("pitched on its line", 0.1, f.bounceX, 0.12)
            assertEquals("speed with swing", 125.0, f.groundKmh, 125.0 * 0.04)
        }
    }

    @Test
    fun `swing and turn together are both found`() {
        val f = fit(delivery(85.0, 4.5, swingAccel = 3.0, turnDeg = -5.0, fps = 60.0, noise = noise(1.0, 9)))
        assertTrue(f.turnFitted)
        assertEquals(-5.0, f.turnDeg, 1.5)
        assertEquals(3.0, f.inAx, 1.5)
    }

    @Test
    fun `turn is measured on the ground, sign as the line card reads it`() {
        // Plus-x is picture-right from the bowler's end; a ball turning that way reads positive.
        assertTrue(QuadSpeed.turnDegrees(0.0, -10.0, 1.0, -10.0) > 0)
        assertEquals(0.0, QuadSpeed.turnDegrees(1.0, -10.0, 0.88, -8.8), 1e-9)
        assertEquals(45.0, QuadSpeed.turnDegrees(0.0, -1.0, 1.0, -1.0), 1e-9)
    }
}
