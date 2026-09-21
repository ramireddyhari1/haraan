package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * What the delivery panel is allowed to claim.
 *
 * The tests that matter most here are the ones asserting that a number is ABSENT. A speed
 * in km/h or a revolution count would be trivial to produce and impossible to justify, and
 * the only thing standing between this screen and a confident fabrication is the decision
 * not to make one. So that decision is pinned down here, where a future change that
 * quietly starts filling those fields in will fail rather than ship.
 */
class FlightMetricsTest {

    private fun sighting(t: Long, x: Float, y: Float, score: Float = 0.8f) =
        BallSighting(timestampMs = t, x = x, y = y, trackingConfidence = score, areaPx = 20)

    /** A 16:9 frame, which is what every clip in this pipeline actually is. */
    private val aspect = 16f / 9f

    /** Straight across the picture at a constant rate, 33ms apart. */
    private fun straight(count: Int = 10, step: Float = 0.05f) =
        (0 until count).map { sighting(it * 33L, 0.1f + step * it, 0.5f) }

    // ---- the refusals -------------------------------------------------------

    @Test
    fun `ground speed is never offered, however good the track`() {
        val metrics = FlightMetrics.of(straight(30), aspect)

        val ground = metrics.groundSpeed
        assertTrue("a km/h reading must not appear without calibration", ground is MetricValue.Unavailable)
        assertTrue(
            "the reason must say what is missing",
            (ground as MetricValue.Unavailable).reason.contains("calibration"),
        )
    }

    @Test
    fun `spin is never offered at all`() {
        // Not "not yet measurable given this track" — not measurable by this pipeline,
        // whatever the footage, because nothing in it observes rotation.
        val rich = FlightMetrics.of(straight(60), aspect)
        val thin = FlightMetrics.of(straight(2), aspect)

        assertTrue(rich.spin is MetricValue.Unavailable)
        assertTrue(thin.spin is MetricValue.Unavailable)
        assertTrue((rich.spin as MetricValue.Unavailable).reason.contains("seam"))
    }

    @Test
    fun `metres are refused when there is no pitch calibration`() {
        val metrics = FlightMetrics.of(straight(30), aspect, quad = null)

        assertTrue(metrics.bounceLength is MetricValue.Unavailable)
        assertTrue(metrics.bounceLine is MetricValue.Unavailable)
        assertNull(metrics.bounce)
        assertTrue(
            (metrics.bounceLength as MetricValue.Unavailable).reason.contains("calibration"),
        )
    }

    @Test
    fun `swing and lateral movement are refused until a bounce splits the flight`() {
        val metrics = FlightMetrics.of(straight(30), aspect)

        // Both bends exist in the track; neither can be named without knowing where the
        // ball pitched, and naming one anyway is how "swing" becomes a synonym for noise.
        assertTrue(metrics.swing is MetricValue.Unavailable)
        assertTrue(metrics.lateral is MetricValue.Unavailable)
        assertTrue((metrics.swing as MetricValue.Unavailable).reason.contains("bounce"))
    }

    // ---- the measurements ---------------------------------------------------

    @Test
    fun `image speed is the path covered over the time it took`() {
        // Ten frames, 0.05 frame-widths apart, 33ms apart. The window covers the last five
        // segments: 0.25 fw in 165ms, which is about 1.515 fw/s.
        val metrics = FlightMetrics.of(straight(10), aspect)

        val speed = metrics.imageSpeed
        assertTrue(speed is MetricValue.Measured)
        assertEquals(0.25 / 0.165, (speed as MetricValue.Measured).value, 0.01)
        assertEquals("fw/s", speed.unit)
    }

    @Test
    fun `image speed says it is not a ground speed`() {
        val speed = FlightMetrics.of(straight(10), aspect).imageSpeed as MetricValue.Measured

        // The caveat is the difference between a useful comparison between two deliveries
        // filmed from one spot, and somebody reading it out as though it were a speed gun.
        assertTrue(speed.caveat!!.contains("camera"))
    }

    @Test
    fun `y is scaled into the same unit as x before any distance is taken`() {
        // The same movement across and down the frame must not measure the same on a 16:9
        // picture, because a frame height is not a frame width. Ignoring that was the most
        // likely quiet bug in here.
        val across = (0 until 6).map { sighting(it * 33L, 0.1f + 0.05f * it, 0.5f) }
        val down = (0 until 6).map { sighting(it * 33L, 0.5f, 0.1f + 0.05f * it) }

        val acrossSpeed = (FlightMetrics.of(across, aspect).imageSpeed as MetricValue.Measured).value
        val downSpeed = (FlightMetrics.of(down, aspect).imageSpeed as MetricValue.Measured).value

        assertEquals(acrossSpeed / aspect, downSpeed, 0.001)
    }

    @Test
    fun `a straight flight measures no curve`() {
        val curve = FlightMetrics.of(straight(12), aspect).curve

        assertTrue(curve is MetricValue.Measured)
        assertEquals(0.0, (curve as MetricValue.Measured).value, 1e-6)
    }

    @Test
    fun `a bent flight measures the peak departure from straight`() {
        // A path that bulges 0.05 of a frame width off the chord at its middle.
        //
        // The bulge is kept modest on purpose: a bigger one puts more than
        // MAX_STEP_PER_FRAME between two sightings, at which point the tracker's own rule
        // says these are not one flight and the track is split before it is ever measured.
        // A fixture that ignored that would be testing a delivery no detector would accept.
        val points = listOf(
            sighting(0L, 0.0f, 0.5f),
            sighting(33L, 0.1f, 0.5f),
            // y is normalised against the height, so an offset expressed in frame WIDTHS
            // has to be scaled by the aspect to land there.
            sighting(66L, 0.2f, 0.5f + 0.05f * aspect),
            sighting(99L, 0.3f, 0.5f),
            sighting(132L, 0.4f, 0.5f),
        )

        val curve = FlightMetrics.of(points, aspect).curve

        assertTrue("expected a measured curve, got $curve", curve is MetricValue.Measured)
        assertEquals(0.05, (curve as MetricValue.Measured).value, 1e-3)
    }

    @Test
    fun `the track score is reported as a ranking rather than a probability`() {
        val metrics = FlightMetrics.of(straight(8).dropLast(1) + sighting(300L, 0.6f, 0.5f, 0.42f), aspect)

        val score = metrics.trackScore
        assertTrue(score is MetricValue.Measured)
        assertEquals(0.42, (score as MetricValue.Measured).value, 1e-4)
        // The engine's own KDoc refuses to call this a confidence. The panel must not
        // quietly promote it into one.
        assertTrue(score.caveat!!.contains("not calibrated"))
    }

    // ---- boundaries ---------------------------------------------------------

    @Test
    fun `an empty track claims nothing`() {
        val metrics = FlightMetrics.of(emptyList(), aspect)

        assertTrue(metrics.imageSpeed is MetricValue.Unavailable)
        assertTrue(metrics.curve is MetricValue.Unavailable)
        assertTrue(metrics.trackScore is MetricValue.Unavailable)
        assertNull(metrics.bounce)
    }

    @Test
    fun `a curve is refused until there is enough path to have a shape`() {
        val metrics = FlightMetrics.of(straight(FlightMetrics.MIN_FOR_CURVE - 1), aspect)

        val curve = metrics.curve
        assertTrue(curve is MetricValue.Unavailable)
        assertTrue((curve as MetricValue.Unavailable).reason.contains("sightings"))
    }

    @Test
    fun `only the current flight is measured, not the one before it`() {
        // Two deliveries in one track. Averaging across the gap would describe neither,
        // and would read as a ball that crossed the frame impossibly slowly.
        val first = (0 until 8).map { sighting(it * 33L, 0.1f + 0.05f * it, 0.5f) }
        val second = (0 until 8).map { sighting(9_000L + it * 33L, 0.1f + 0.05f * it, 0.5f) }

        val both = FlightMetrics.of(first + second, aspect).imageSpeed as MetricValue.Measured
        val alone = FlightMetrics.of(second, aspect).imageSpeed as MetricValue.Measured

        assertEquals(alone.value, both.value, 1e-6)
    }

    @Test
    fun `sightings sharing a timestamp are refused rather than divided by zero`() {
        val points = (0 until 6).map { sighting(100L, 0.1f + 0.05f * it, 0.5f) }

        assertTrue(FlightMetrics.of(points, aspect).imageSpeed is MetricValue.Unavailable)
    }

    @Test
    fun `a ball that did not move reports no curve rather than a divide by zero`() {
        val points = (0 until 8).map { sighting(it * 33L, 0.5f, 0.5f) }

        val curve = FlightMetrics.of(points, aspect).curve
        assertTrue(curve is MetricValue.Unavailable)
        assertTrue((curve as MetricValue.Unavailable).reason.contains("did not move"))
    }

    @Test
    fun `a nonsense aspect does not produce a nonsense number`() {
        val metrics = FlightMetrics.of(straight(8), frameAspect = 0f)

        val speed = metrics.imageSpeed
        assertTrue(speed is MetricValue.Measured)
        assertTrue((speed as MetricValue.Measured).value.isFinite())
    }
}
