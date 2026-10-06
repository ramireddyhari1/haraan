package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * The call that puts a delivery's numbers on screen a second after the ball, instead of
 * whenever the scorer taps. Calling too early truncates the flight the metrics are measured
 * from, so the refusals matter as much as the calls.
 */
class FlightEndTest {

    private val aspect = 16f / 9f

    /** Behind the arm: up the picture from [fromY] to [toY], 33 ms a frame. */
    private fun flight(
        count: Int = 10,
        fromY: Double = 0.80,
        toY: Double = 0.40,
        x: Double = 0.5,
        stepMs: Long = 33L,
    ): List<BallSighting> = (0 until count).map { i ->
        val t = if (count == 1) 0.0 else i.toDouble() / (count - 1)
        BallSighting(
            timestampMs = i * stepMs,
            x = x.toFloat(),
            y = (fromY + (toY - fromY) * t).toFloat(),
            trackingConfidence = 0.8f,
            areaPx = 40,
        )
    }

    private fun lock(baseY: Double = 0.30, span: Double = 0.06): WicketLock {
        val tracker = WicketTracker()
        tracker.lockManually(
            baseLeft = Point2(0.5 - span / 2, baseY),
            baseRight = Point2(0.5 + span / 2, baseY),
            frameAspect = aspect,
        )
        return tracker.lock()!!
    }

    @Test
    fun `nothing tracked is never done`() {
        assertNull(FlightEnd.check(emptyList(), 5_000L, aspect))
    }

    @Test
    fun `a ball still being seen is not done`() {
        val track = flight()
        assertNull(FlightEnd.check(track, track.last().timestampMs + 33L, aspect))
    }

    @Test
    fun `a real flight that goes quiet is done`() {
        val track = flight()
        val at = track.last().timestampMs + FlightEnd.QUIET_MS
        assertEquals(FlightEnd.Reason.BALL_GONE, FlightEnd.check(track, at, aspect))
    }

    @Test
    fun `one dropped frame mid-flight does not end it`() {
        val track = flight()
        assertNull(FlightEnd.check(track, track.last().timestampMs + 100L, aspect))
    }

    @Test
    fun `too few sightings is not a delivery`() {
        val track = flight(count = FlightEnd.MIN_FLIGHT_POINTS - 1)
        assertNull(FlightEnd.check(track, track.last().timestampMs + 2_000L, aspect))
    }

    @Test
    fun `something that barely moved is not a delivery`() {
        val track = flight(fromY = 0.50, toY = 0.52)
        assertNull(FlightEnd.check(track, track.last().timestampMs + 2_000L, aspect))
    }

    @Test
    fun `last seen at the stumps ends sooner`() {
        val wicket = lock(baseY = 0.30)
        val track = flight(toY = 0.31)
        val at = track.last().timestampMs + FlightEnd.AT_WICKET_QUIET_MS
        assertEquals(FlightEnd.Reason.AT_WICKET, FlightEnd.check(track, at, aspect, wicket))
    }

    @Test
    fun `stopped short of the stumps waits the full quiet gap`() {
        val wicket = lock(baseY = 0.30)
        val track = flight(toY = 0.50)
        val at = track.last().timestampMs + FlightEnd.AT_WICKET_QUIET_MS
        assertNull(FlightEnd.check(track, at, aspect, wicket))
    }

    @Test
    fun `a run that outlasts any delivery is called`() {
        val track = flight(count = 60, stepMs = 33L)
        assertEquals(
            FlightEnd.Reason.TOO_LONG,
            FlightEnd.check(track, track.last().timestampMs + 1L, aspect),
        )
    }

    @Test
    fun `only the latest run counts`() {
        // An old flight, a long gap, then two sightings of something new: the new run is
        // too short to be a delivery, so the old one must not be re-called.
        val old = flight()
        val fresh = flight(count = 2).map { it.copy(timestampMs = it.timestampMs + 5_000L) }
        assertNull(FlightEnd.check(old + fresh, fresh.last().timestampMs + 2_000L, aspect))
    }
}
