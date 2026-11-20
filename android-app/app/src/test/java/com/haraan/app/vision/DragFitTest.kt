package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * A REAL BALL SLOWS IN THE AIR, AND THE FIT MUST KNOW IT.
 *
 * Bowling session, 2026-11-19 (tennis ball, phone behind the bowler, 30 fps): every one of
 * twelve balls read 52-57 km/h, "short" and missing, while the picture showed full balls at
 * the stumps. A tennis ball loses ~30% of its pace over a pitch; a fit that holds the pace
 * steady reads that slowing as a shorter, slower ball. These fly balls WITH quadratic drag
 * through a pinhole camera behind the bowler and check the fit gets length and speed back.
 */
class DragFitTest {

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

    // As on the session phone: ~1.3 m up, a little behind the bowler's stumps.
    private val lens = Lens(
        centre = doubleArrayOf(0.0, PitchGeometry.STUMPS_TO_STUMPS_M + 2.5, 1.3),
        target = doubleArrayOf(0.0, 4.0, 0.0),
        focal = 1.2,
        aspect = aspect,
    )

    private val quad = PitchQuad(
        corners = PitchGeometry.calibrationCorners(CameraEnd.BOWLER).map { lens.project(doubleArrayOf(it.x, it.y, 0.0)) },
        source = QuadSource.TAPPED,
        confidence = 1f,
        cameraEnd = CameraEnd.BOWLER,
    )

    private class Flown(val sightings: List<BallSighting>, val bounceY: Double, val avgGroundKmh: Double)

    /**
     * Released at 2.1 m from 18.4 m at [kmh] (over the ground), aimed down the middle, with
     * quadratic drag k per metre, pitching [lengthM] from the striker. 30 fps; seen until it is
     * [lostBeforeStumpsM] short of the stumps or it bounces, whichever is first.
     */
    private fun fly(kmh: Double, lengthM: Double, k: Double, lostBeforeStumpsM: Double = 0.0): Flown {
        val g = 9.81
        val releaseY = 18.4
        val releaseH = 2.1
        // Find the downward speed at release that pitches it at lengthM.
        fun landY(vz0: Double): Double {
            var y = releaseY; var z = releaseH; var vy = -kmh / 3.6; var vz = vz0
            val dt = 0.0005
            while (z > 0) {
                val v = hypot(vy, vz)
                vy += -k * v * vy * dt
                vz += (-g - k * v * vz) * dt
                y += vy * dt; z += vz * dt
            }
            return y
        }
        var lo = -15.0; var hi = 5.0
        repeat(60) { val mid = (lo + hi) / 2; if (landY(mid) > lengthM) lo = mid else hi = mid }
        val vz0 = (lo + hi) / 2

        val out = ArrayList<BallSighting>()
        var y = releaseY; var z = releaseH; var vy = -kmh / 3.6; var vz = vz0
        val dt = 0.0005
        var t = 0.0
        var nextShot = 0.04
        var tBounce = 0.0
        while (z > 0) {
            val v = hypot(vy, vz)
            vy += -k * v * vy * dt
            vz += (-g - k * v * vz) * dt
            y += vy * dt; z += vz * dt; t += dt
            if (t >= nextShot) {
                if (y > lostBeforeStumpsM) {
                    val img = lens.project(doubleArrayOf(0.02, y, z))
                    out += BallSighting((t * 1000).toLong(), img.x.toFloat(), img.y.toFloat(), 0.8f, 30)
                }
                nextShot += 1.0 / 30
            }
            tBounce = t
        }
        return Flown(out, y, (releaseY - y) / tBounce * 3.6)
    }

    private fun fit(f: Flown, ball: BallDrag, roughBounce: Boolean = true): Flight3d {
        val result = QuadSpeed.fit(f.sightings, quad, if (roughBounce) BouncePoint.find(f.sightings, quad) else null, aspect.toFloat(), ball = ball)
        assertTrue("expected a fit, got $result", result is QuadSpeed.FitResult.Ok)
        return (result as QuadSpeed.FitResult.Ok).flight.copy(ball = ball)
    }

    @Test
    fun `a tennis ball is read at its own length and speed`() {
        for ((kmh, length) in listOf(95.0 to 1.0, 95.0 to 5.0, 80.0 to 3.0, 70.0 to 7.0, 110.0 to 0.5)) {
            val flown = fly(kmh, length, BallDrag.TENNIS.perMetre)
            val f = fit(flown, BallDrag.TENNIS)
            println("tennis $kmh km/h, pitched ${"%.1f".format(flown.bounceY)}: fit pitched ${"%.2f".format(f.bounceY)}, release ${"%.1f".format(f.speedKmh)}")
            assertEquals("length of a $kmh km/h ball pitched $length", flown.bounceY, f.bounceY, 0.6)
            assertEquals("release speed of a $kmh km/h ball pitched $length", kmh, f.speedKmh, kmh * 0.15)
        }
    }

    /** As ball 6 of the 2026-11-19 session: a full tennis ball lost a few metres before the stumps. */
    @Test
    fun `a full tennis ball lost before it pitches is read at its speed`() {
        for ((kmh, lost) in listOf(85.0 to 2.5, 85.0 to 4.0, 100.0 to 2.5, 70.0 to 3.0)) {
            val flown = fly(kmh, 0.3, BallDrag.TENNIS.perMetre, lostBeforeStumpsM = lost)
            // As the stumps-only camera fits it: no rough bounce to start from.
            val f = fit(flown, BallDrag.TENNIS, roughBounce = false)
            println("tennis-lost $kmh km/h (avg ${"%.0f".format(flown.avgGroundKmh)}), lost ${lost} m out, ${flown.sightings.size} pts: fit pitched ${"%.2f".format(f.bounceY)}, ground ${"%.1f".format(f.groundKmh)}, release ${"%.1f".format(f.speedKmh)}")
            assertEquals("release speed of a full $kmh km/h ball lost $lost m out", kmh, f.speedKmh, kmh * 0.08)
            assertEquals("length of a full $kmh km/h ball lost $lost m out", flown.bounceY, f.bounceY, 1.5)
        }
    }

    @Test
    fun `a leather ball still reads as before`() {
        for ((kmh, length) in listOf(130.0 to 5.0, 100.0 to 2.0, 80.0 to 6.0)) {
            val flown = fly(kmh, length, BallDrag.LEATHER.perMetre)
            val f = fit(flown, BallDrag.LEATHER)
            println("leather $kmh km/h, pitched ${"%.1f".format(flown.bounceY)}: fit pitched ${"%.2f".format(f.bounceY)}, release ${"%.1f".format(f.speedKmh)}")
            assertEquals(flown.bounceY, f.bounceY, 0.6)
            assertEquals(kmh, f.speedKmh, kmh * 0.06)
        }
    }
}
