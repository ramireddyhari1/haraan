package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * A delivery is simulated with real air drag (dv/dt = −k·v², sampled at 120 fps), a steady
 * speed is fitted to it the way [QuadSpeed] does, and [BallDrag.releaseFrom] must carry that
 * fitted average back to the speed it was actually bowled at.
 */
class BallDragTest {

    /** Positions down the pitch from release, every 1/120 s, for a ball let go at [v0] m/s. */
    private fun flight(v0: Double, k: Double, untilM: Double): List<Pair<Double, Double>> {
        val dt = 1.0 / 120.0
        var t = 0.0
        var s = 0.0
        var v = v0
        val out = ArrayList<Pair<Double, Double>>()
        while (s < untilM) {
            out.add(t to s)
            // Small steps inside each frame so the drag is integrated, not guessed.
            repeat(20) {
                val h = dt / 20
                s += v * h
                v -= k * v * v * h
            }
            t += dt
        }
        return out
    }

    /** Least-squares straight line through (t, s): its slope is the fitted steady speed. */
    private fun fittedSpeed(points: List<Pair<Double, Double>>): Double {
        val n = points.size
        val mt = points.sumOf { it.first } / n
        val ms = points.sumOf { it.second } / n
        val num = points.sumOf { (it.first - mt) * (it.second - ms) }
        val den = points.sumOf { (it.first - mt) * (it.first - mt) }
        return num / den
    }

    private fun check(ball: BallDrag, kmh: Double, seenFromM: Double, seenToM: Double) {
        val v0 = kmh / 3.6
        val seen = flight(v0, ball.perMetre, seenToM).filter { it.second >= seenFromM }
        val fitted = fittedSpeed(seen)
        val release = ball.releaseFrom(fitted, seen.first().second, seen.last().second)
        // The fitted average is genuinely low — that was the error being corrected...
        assertTrue("$ball fitted ${fitted * 3.6} should read under $kmh", fitted * 3.6 < kmh - 1.0)
        // ...and the release speed comes back within half a percent.
        assertEquals("$ball at $kmh km/h", kmh, release * 3.6, kmh * 0.005)
    }

    @Test
    fun `leather ball, full length, seen from the hand`() = check(BallDrag.LEATHER, 130.0, 0.0, 16.0)

    @Test
    fun `leather ball, seen only from a few metres out`() = check(BallDrag.LEATHER, 110.0, 3.0, 14.0)

    @Test
    fun `tennis ball loses far more and is still recovered`() = check(BallDrag.TENNIS, 100.0, 0.5, 17.0)

    @Test
    fun `tape ball on a yorker`() = check(BallDrag.TAPE, 115.0, 1.0, 18.0)

    @Test
    fun `how much a tennis ball reads low without the correction`() {
        val seen = flight(100.0 / 3.6, BallDrag.TENNIS.perMetre, 17.0)
        val fittedKmh = fittedSpeed(seen) * 3.6
        // About a sixth under the speed it was bowled at.
        assertTrue("fitted $fittedKmh", fittedKmh in 80.0..88.0)
    }

    @Test
    fun `the server's ball names map to a drag`() {
        assertEquals(BallDrag.LEATHER, BallDrag.fromServer("season"))
        assertEquals(BallDrag.LEATHER, BallDrag.fromServer("cork"))
        assertEquals(BallDrag.TENNIS, BallDrag.fromServer("Tennis"))
        assertEquals(null, BallDrag.fromServer(null))
    }
}
