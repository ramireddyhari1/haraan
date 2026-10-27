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
    fun cutsAfterALongLossWellWideOfTheStumps() {
        // net01: lost for 125 ms (15 frames at 120 fps), then something sliding sideways at one height.
        val run = receding(30)
        val last = run.last()
        val slide = (0 until 12).map { i ->
            BallSighting(last.timestampMs + 125 + i * 8L, last.x + 0.03f + i * 0.004f, last.y - 0.07f, 0.5f, 5)
        }
        assertEquals(run, FlightTrim.receding(run + slide, aspect, stumps = Point2(last.x + 0.11, last.y + 0.01)))
    }

    @Test
    fun keepsAShortLossInTheAir() {
        // A few frames missed mid-flight is still the same ball.
        val run = receding(40)
        val gappy = run.filterIndexed { i, _ -> i !in 18..23 }
        assertEquals(gappy, FlightTrim.receding(gappy, aspect))
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
    /** IMG_6056's real tail: followed to the stumps' foot, lost eight frames, then the pads. */
    @Test
    fun cutsWhereTheTrackComesBackAfterAGapAtTheStumps() {
        val stumps = Point2(0.603, 0.497)
        val toStumps = (0 until 20).map { i ->
            BallSighting(timestampMs = 1775L + i * 8, x = 0.575f + i * 0.00075f, y = 0.455f + i * 0.0019f, trackingConfidence = 0.9f, areaPx = 12)
        }
        val pads = listOf(
            BallSighting(2000L, 0.614f, 0.465f, 0.5f, 10),
            BallSighting(2075L, 0.617f, 0.486f, 0.5f, 10),
        )
        assertEquals(toStumps, FlightTrim.receding(toStumps + pads, aspect, stumps))
    }

    /** Thirty a second, every sighting is 33 ms apart: that spacing is not a gap. */
    @Test
    fun keepsAThirtyFpsTrackThroughTheStumps() {
        val stumps = Point2(0.603, 0.497)
        val run = (0 until 10).map { i ->
            BallSighting(timestampMs = 1700L + i * 33, x = 0.585f + i * 0.002f, y = 0.47f + i * 0.003f, trackingConfidence = 0.9f, areaPx = 12)
        }
        assertEquals(run, FlightTrim.receding(run, aspect, stumps))
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
