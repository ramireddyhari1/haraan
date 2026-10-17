package com.haraan.app.vision

import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

/** The batter's stumps kept while a batter hides them, and dropped when the phone moves. */
class SteadyWicketTest {
    private val aspect = 9f / 16f

    private fun lock(x: Double = 0.60) = WicketLock(
        anchor = WicketAnchor(Point2(x - 0.0068, 0.50), Point2(x + 0.0068, 0.50)),
        kind = WicketKind.STUMPS,
        source = WicketLockSource.DETECTED,
        state = WicketTrackState.CONFIRMED,
        confidence = 0.9f,
        ageFrames = 0,
        jitter = 0.001f,
        heldFrames = 30,
        aspect = aspect,
        subPixel = true,
    )

    private val near = BowlersEnd(Point2(0.42, 0.77), 0.087)

    /** Two seconds of clear view, ten a second. Returns the clock after. */
    private fun setUp(s: SteadyWicket): Long {
        var t = 0L
        repeat(20) { s.add(lock(), t, near); t += 100 }
        return t
    }

    @Test
    fun keptWhileTheBatterHidesThemAndTheBowlersStumpsStay() {
        val s = SteadyWicket()
        var t = setUp(s)
        // Thirty seconds hidden: no lock at all, the bowler's stumps seen where they were.
        repeat(300) { s.add(null, t, near); t += 100 }
        assertNotNull("forgotten while only hidden", s.value())
    }

    @Test
    fun forgottenWhenNothingAtAllIsSeen() {
        val s = SteadyWicket()
        var t = setUp(s)
        // Pointed at the pavilion: neither set in view.
        repeat(120) { s.add(null, t, null); t += 100 }
        assertNull(s.value())
    }

    /** Never having seen the bowler's set must not mean "never forget". */
    @Test
    fun forgottenWhenTheBowlersStumpsWereNeverSeen() {
        val s = SteadyWicket()
        var t = 0L
        repeat(20) { s.add(lock(), t, null); t += 100 }
        repeat(120) { s.add(null, t, null); t += 100 }
        assertNull(s.value())
    }

    @Test
    fun droppedAtOnceWhenTheBowlersStumpsMove() {
        val s = SteadyWicket()
        var t = setUp(s)
        val moved = BowlersEnd(Point2(0.52, 0.74), 0.087)
        repeat(SteadyWicket.NEAR_MOVED_SIGHTINGS) { s.add(null, t, moved); t += 100 }
        assertNull("the phone moved; the held place is wrong now", s.value())
    }

    @Test
    fun aLegAcrossTheBowlersStumpsIsNotAMove() {
        val s = SteadyWicket()
        var t = setUp(s)
        val leg = BowlersEnd(Point2(0.47, 0.79), 0.12)
        repeat(SteadyWicket.NEAR_MOVED_SIGHTINGS - 1) { s.add(null, t, leg); t += 100 }
        s.add(null, t, near)
        t += 100
        repeat(SteadyWicket.NEAR_MOVED_SIGHTINGS - 1) { s.add(null, t, leg); t += 100 }
        assertNotNull(s.value())
    }
}
