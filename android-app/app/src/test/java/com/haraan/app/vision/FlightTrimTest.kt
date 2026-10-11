package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

class FlightTrimTest {
    private val aspect = 9f / 16f

    /** A receding ball: slowing steadily down and across the picture, 120 fps. */
    private fun receding(n: Int) = (0 until n).map { i ->
        val t = i * 8L
        BallSighting(timestampMs = t, x = 0.30f + 0.2f * (1f - 1f / (1f + i * 0.08f)), y = 0.28f + 0.22f * (1f - 1f / (1f + i * 0.08f)), trackingConfidence = 0.9f, areaPx = 5)
    }

    @Test
    fun keepsAWholeRecedingFlight() {
        val run = receding(30)
        assertEquals(run, FlightTrim.receding(run, aspect))
    }

    @Test
    fun cutsAtABatSwungThroughTheEnd() {
        val run = receding(30)
        val last = run.last()
        val bat = BallSighting(last.timestampMs + 50, last.x + 0.08f, last.y - 0.002f, 0.4f, 4)
        assertEquals(run, FlightTrim.receding(run + bat, aspect))
    }

    @Test
    fun cutsAtAJumpBackUpThePicture() {
        val run = receding(30)
        val last = run.last()
        val throwBack = BallSighting(last.timestampMs + 60, last.x + 0.001f, last.y - 0.05f, 0.4f, 4)
        assertEquals(run, FlightTrim.receding(run + throwBack, aspect))
    }

    @Test
    fun keepsTheSmallRiseOffThePitch() {
        val run = receding(30)
        val last = run.last()
        val rise = (1..4).map { k -> BallSighting(last.timestampMs + 8L * k, last.x + 0.002f * k, last.y - 0.004f * k, 0.9f, 4) }
        assertEquals(run + rise, FlightTrim.receding(run + rise, aspect))
    }

    @Test
    fun neverCutsTheStart() {
        val run = receding(4)
        assertEquals(run, FlightTrim.receding(run, aspect))
    }
}

class BowlersEndFilterTest {
    private fun s(x: Double, y: Double, span: Double = 0.082) = BowlersEnd(Point2(x, y), span)

    @Test
    fun needsSeveralSightings() {
        val f = BowlersEndFilter()
        repeat(BowlersEndFilter.MIN_SIGHTINGS - 1) { assertNull(f.add(s(0.42, 0.77))) }
        assertNotNull(f.add(s(0.42, 0.77)))
    }

    @Test
    fun aLegAcrossTheStumpsDoesNotMoveTheEstimate() {
        val f = BowlersEndFilter()
        repeat(20) { f.add(s(0.42, 0.77)) }
        // Mid-stride: a much wider, displaced "set" made of stumps and a shin.
        repeat(3) { f.add(s(0.47, 0.80, 0.13)) }
        val v = f.value()!!
        assertEquals(0.42, v.base.x, 1e-9)
        assertEquals(0.082, v.spanFw, 1e-9)
    }

    @Test
    fun followsAPhoneThatWasMoved() {
        val f = BowlersEndFilter()
        repeat(40) { f.add(s(0.42, 0.77)) }
        repeat(BowlersEndFilter.MOVED_RUN) { f.add(s(0.54, 0.78)) }
        assertEquals(0.54, f.value()!!.base.x, 1e-9)
    }
}
