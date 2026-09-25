package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs

/**
 * The geometry three callers share, so they cannot quietly disagree about a delivery.
 */
class BallPathTest {

    private val aspect = 16f / 9f

    private fun flat(vararg pairs: Pair<Double, Double>) =
        pairs.map { BallPath.Flat(it.first, it.second) }

    @Test
    fun `a line is fitted through points on a line with no residual`() {
        val line = BallPath.fitLine(flat(0.1 to 0.1, 0.2 to 0.2, 0.3 to 0.3, 0.4 to 0.4))!!
        assertEquals(0.0, line.residual, 1e-9)
        assertEquals(1.0, abs(line.dirX * line.dirY) * 2, 1e-6)
    }

    /**
     * The whole reason the fit is total least squares rather than the ordinary kind. A ball
     * bowled from behind the arm travels almost straight up the picture, which is the exact
     * case where fitting y against x divides by nothing.
     */
    @Test
    fun `a vertical path is fitted exactly`() {
        val line = BallPath.fitLine(flat(0.5 to 0.9, 0.5 to 0.7, 0.5 to 0.5, 0.5 to 0.3))!!
        assertEquals(0.0, line.residual, 1e-9)
        assertEquals(0.0, abs(line.dirX), 1e-6)
        assertEquals(1.0, abs(line.dirY), 1e-6)
    }

    @Test
    fun `the direction points the way the points travel`() {
        val up = BallPath.fitLine(flat(0.5 to 0.9, 0.5 to 0.6, 0.5 to 0.3))!!
        val down = BallPath.fitLine(flat(0.5 to 0.3, 0.5 to 0.6, 0.5 to 0.9))!!
        assertTrue(up.dirY < 0)
        assertTrue(down.dirY > 0)
    }

    @Test
    fun `a scattered set reports its own residual`() {
        val line = BallPath.fitLine(flat(0.1 to 0.10, 0.2 to 0.22, 0.3 to 0.28, 0.4 to 0.41))!!
        assertTrue(line.residual > 0.0)
        assertTrue(line.residual < 0.02)
    }

    @Test
    fun `too few points is refused`() {
        assertNull(BallPath.fitLine(flat(0.1 to 0.1, 0.2 to 0.2)))
    }

    // ---- the bounce ---------------------------------------------------------------------

    private fun sighting(i: Int, x: Float, y: Float) = BallSighting(i * 33L, x, y, 0.8f, 40)

    @Test
    fun `a bounce is found where the ball stops falling and starts rising`() {
        // y grows downward: the ball falls to 0.8 and comes back up.
        val ys = listOf(0.40f, 0.55f, 0.68f, 0.76f, 0.80f, 0.74f, 0.64f, 0.52f, 0.40f)
        val run = ys.mapIndexed { i, y -> sighting(i, 0.5f + i * 0.005f, y) }

        val at = BallPath.bounceIndex(run, aspect)
        assertNotNull(at)
        assertEquals(4, at)
    }

    @Test
    fun `a full toss has no bounce`() {
        val run = (0 until 10).map { sighting(it, 0.5f, 0.8f - it * 0.05f) }
        assertNull(BallPath.bounceIndex(run, aspect))
    }

    @Test
    fun `a wobble in the detections is not a bounce`() {
        // Falling throughout, with one frame a hair out of line.
        val ys = listOf(0.30f, 0.40f, 0.50f, 0.599f, 0.70f, 0.80f, 0.90f)
        val run = ys.mapIndexed { i, y -> sighting(i, 0.5f, y) }
        assertNull(BallPath.bounceIndex(run, aspect))
    }

    @Test
    fun `a short track cannot show a bounce`() {
        val run = (0 until 3).map { sighting(it, 0.5f, 0.5f) }
        assertNull(BallPath.bounceIndex(run, aspect))
    }

    // ---- angles -------------------------------------------------------------------------

    @Test
    fun `the angle between two paths is measured in the corrected space`() {
        val a = BallPath.fitLine(flat(0.0 to 0.0, 0.1 to 0.0, 0.2 to 0.0))!!
        val b = BallPath.fitLine(flat(0.0 to 0.0, 0.0 to 0.1, 0.0 to 0.2))!!
        assertEquals(90.0, BallPath.angleBetween(a, b), 1e-6)
    }

    @Test
    fun `the signed angle tells the two ways of turning apart`() {
        val into = BallPath.fitLine(flat(0.5 to 0.9, 0.5 to 0.7, 0.5 to 0.5))!!
        val leg = BallPath.fitLine(flat(0.5 to 0.5, 0.55 to 0.3, 0.60 to 0.1))!!
        val off = BallPath.fitLine(flat(0.5 to 0.5, 0.45 to 0.3, 0.40 to 0.1))!!

        assertTrue(BallPath.signedAngleBetween(into, leg) * BallPath.signedAngleBetween(into, off) < 0)
    }

    @Test
    fun `flattening and unflattening a point is a round trip`() {
        val point = Point2(0.37, 0.62)
        val back = BallPath.unflatten(BallPath.flatten(point, aspect), aspect)
        assertEquals(point.x, back.x, 1e-12)
        assertEquals(point.y, back.y, 1e-12)
    }
}
