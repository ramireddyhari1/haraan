package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * The line card turns a picture-signed offset into off/leg words. Getting the side wrong
 * would call every outswinger a legside ball, so the handedness flip is tested both ways.
 */
class DeliveryLineTest {

    private fun metrics(
        stumpsRightM: Double? = null,
        pitchedRightM: Double? = null,
        end: CameraEnd = CameraEnd.BOWLER,
    ): FlightMetrics {
        val base = FlightMetrics.of(emptyList(), 16f / 9f)
        val lbw = if (stumpsRightM == null) {
            LbwProjection(verdict = LbwVerdict.UNAVAILABLE, basis = "no wicket locked")
        } else {
            LbwProjection(
                verdict = LbwVerdict.MISSING,
                offsetM = stumpsRightM,
                uncertaintyM = 0.03,
                basis = "test",
            )
        }
        val bounce = pitchedRightM?.let {
            Bounce(
                image = Point2(0.5, 0.5),
                pitch = Point2(it, 4.0),
                sightingsUsed = 8,
                quadSource = QuadSource.DETECTED,
                cameraEnd = end,
            )
        }
        return base.copy(lbw = lbw, bounce = bounce)
    }

    @Test
    fun `bands run from wide off to wide leg`() {
        assertEquals(LineBand.MIDDLE, DeliveryLines.band(0.0))
        assertEquals(LineBand.MIDDLE, DeliveryLines.band(-0.04))
        assertEquals(LineBand.OFF_STUMP, DeliveryLines.band(0.10))
        assertEquals(LineBand.LEG_STUMP, DeliveryLines.band(-0.10))
        assertEquals(LineBand.OUTSIDE_OFF, DeliveryLines.band(0.30))
        assertEquals(LineBand.DOWN_LEG, DeliveryLines.band(-0.30))
        assertEquals(LineBand.WIDE_OFF, DeliveryLines.band(1.0))
        assertEquals(LineBand.WIDE_LEG, DeliveryLines.band(-1.0))
    }

    @Test
    fun `for a right-hander the off side is the left of the picture`() {
        // Seen from behind the bowler, 30 cm to the LEFT of middle.
        val line = DeliveryLines.of(metrics(stumpsRightM = -0.30), BatterHand.RIGHT)
        assertEquals(LineBand.OUTSIDE_OFF, line.atStumps)
        assertEquals(0.30, line.atStumpsOffM!!, 1e-9)
    }

    @Test
    fun `the same ball to a left-hander is down leg`() {
        val line = DeliveryLines.of(metrics(stumpsRightM = -0.30), BatterHand.LEFT)
        assertEquals(LineBand.DOWN_LEG, line.atStumps)
    }

    @Test
    fun `no projection gives no stumps line and says why`() {
        val line = DeliveryLines.of(metrics(), BatterHand.RIGHT)
        assertNull(line.atStumps)
        assertEquals("no wicket locked", line.reason)
    }

    @Test
    fun `the pitched line comes from the bounce`() {
        val line = DeliveryLines.of(metrics(pitchedRightM = 0.10), BatterHand.RIGHT)
        assertEquals(LineBand.LEG_STUMP, line.pitched)
    }

    @Test
    fun `a bounce filmed from the striker's end is not given a side`() {
        val line = DeliveryLines.of(metrics(pitchedRightM = 0.10, end = CameraEnd.STRIKER), BatterHand.RIGHT)
        assertNull(line.pitched)
        assertNotNull(line)
    }
}
