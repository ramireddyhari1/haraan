package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ClipClockTest {

    /** A ball coming down the picture and slowing, as from behind the bowler. */
    private fun ball(t: Double) = Pair((0.3 + 0.2 * (1 - Math.exp(-t / 300.0))).toFloat(), (0.3 + 0.25 * (1 - Math.exp(-t / 350.0))).toFloat())

    private fun track(fromMs: Long, toMs: Long, stepMs: Long, clockShift: Long): List<BallSighting> =
        (fromMs..toMs step stepMs).map { t ->
            val (x, y) = ball(t.toDouble())
            BallSighting(t + clockShift, x, y, 1f, 40)
        }

    @Test
    fun findsTheClipPlacedEarly() {
        // Live at ~30/s on the camera clock; the clip at 30/s placed 100 ms too early.
        val live = track(0, 600, 33, 0)
        val clip = track(5, 620, 33, -100)
        val delta = ClipClock.offsetMs(live, clip)!!
        assertEquals(-100.0, delta, 4.0)
        val fixed = ClipClock.shifted(clip, -delta)
        assertEquals((clip.first().timestampMs + 100).toDouble(), fixed.first().timestampMs.toDouble(), 4.0)
    }

    @Test
    fun noShiftWhenAlreadyAligned() {
        val live = track(0, 600, 33, 0)
        val clip = track(10, 610, 16, 0)
        assertEquals(0.0, ClipClock.offsetMs(live, clip)!!, 4.0)
    }

    @Test
    fun differentBallsDoNotLineUp() {
        val live = track(0, 600, 33, 0)
        val other = live.map { it.copy(x = 1f - it.x, y = 0.9f - it.y) }
        assertNull(ClipClock.offsetMs(live, other))
    }
}
