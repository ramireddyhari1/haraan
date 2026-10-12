package com.haraan.app.data

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/** The camera's copy of the booked-slot window: on inside it, paused the moment it ends. */
class CameraAccessTest {

    private val sixPm = java.time.OffsetDateTime.parse("2026-10-12T12:30:00Z").toInstant().toEpochMilli()
    private val sevenPm = sixPm + 60 * 60_000L

    @Test
    fun `a booked slot works until its end and not after`() {
        val a = CameraAccess.from(
            JSONObject().put("allowed", true).put("via", "booking").put("angles", 1)
                .put("until", "2026-10-12T13:30:00+00:00").put("venue", "Kadapa Arena"),
        )!!
        assertEquals(sevenPm, a.untilMs)
        assertEquals(1, a.angles)
        assertTrue(a.allowsAt(sixPm))
        assertTrue(a.allowsAt(sevenPm - 1))
        assertFalse(a.allowsAt(sevenPm))
    }

    @Test
    fun `locked with the next slot says when it opens`() {
        val a = CameraAccess.from(
            JSONObject().put("allowed", false).put("via", JSONObject.NULL).put("angles", 0)
                .put("until", JSONObject.NULL).put("opensAt", "2026-10-12T12:30:00+00:00"),
        )!!
        assertFalse(a.allowsAt(sixPm))
        assertEquals(sixPm, a.opensAtMs)
        assertNull(a.via)
        assertNull(a.untilMs)
    }

    @Test
    fun `a plan has no end and no ceiling when the server sends none`() {
        val a = CameraAccess.from(JSONObject().put("allowed", true).put("via", "plan").put("angles", JSONObject.NULL))!!
        assertTrue(a.allowsAt(Long.MAX_VALUE - 1))
        assertNull(a.angles)
    }

    @Test
    fun `an older server without the field leaves the camera alone`() {
        assertNull(CameraAccess.from(null))
    }
}
