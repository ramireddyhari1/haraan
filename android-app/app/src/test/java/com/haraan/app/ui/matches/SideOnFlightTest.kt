package com.haraan.app.ui.matches

import com.haraan.app.data.ClipTrack
import com.haraan.app.data.ClipTrackPoint
import com.haraan.app.data.parseClipTrack
import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The side-on drawing may reflect and stretch a flight, and nothing else.
 */
class SideOnFlightTest {

    // Released high on the left, down to a bounce, and up again: y grows downward.
    private fun vShape(reversed: Boolean = false): ClipTrack {
        val raw = listOf(0.10f to 0.40f, 0.30f to 0.50f, 0.50f to 0.60f, 0.70f to 0.55f, 0.85f to 0.50f)
        val pts = raw.mapIndexed { i, (x, y) -> ClipTrackPoint(i * 33, if (reversed) 1f - x else x, y, 0.8f) }
        return ClipTrack(aspect = 16f / 9f, bounce = 2, points = pts)
    }

    @Test
    fun `the ground sits at the bounce`() {
        val shape = flightShapeOf(vShape())
        assertEquals(0f, shape.height[2], 1e-6f)
        assertTrue(shape.height[0] > 0f)
    }

    @Test
    fun `a right-to-left flight is mirrored to read left-to-right`() {
        val a = flightShapeOf(vShape())
        val b = flightShapeOf(vShape(reversed = true))
        assertEquals(0f, b.along.first(), 1e-6f)
        assertEquals(1f, b.along.last(), 1e-6f)
        a.along.indices.forEach { assertEquals(a.along[it], b.along[it], 1e-5f) }
        a.height.indices.forEach { assertEquals(a.height[it], b.height[it], 1e-5f) }
    }

    @Test
    fun `a flat arc is stretched, in half steps, never past the cap`() {
        val flat = ClipTrack(
            aspect = 16f / 9f,
            bounce = null,
            points = listOf(ClipTrackPoint(0, 0.1f, 0.500f, 1f), ClipTrackPoint(33, 0.5f, 0.495f, 1f), ClipTrackPoint(66, 0.9f, 0.500f, 1f)),
        )
        val shape = flightShapeOf(flat)
        assertEquals(4f, shape.stretch, 0f)
        assertEquals(0f, (shape.stretch * 2f) % 1f, 0f)
    }

    @Test
    fun `a tall arc is drawn at true scale`() {
        val tall = ClipTrack(
            aspect = 1f,
            bounce = null,
            points = listOf(ClipTrackPoint(0, 0.1f, 0.9f, 1f), ClipTrackPoint(33, 0.5f, 0.1f, 1f), ClipTrackPoint(66, 0.9f, 0.9f, 1f)),
        )
        assertEquals(1f, flightShapeOf(tall).stretch, 0f)
    }

    @Test
    fun `the server's track parses, and junk does not`() {
        val ok = parseClipTrack(JSONObject("""{"v":1,"aspect":1.7778,"bounce":1,"points":[[0,0.1,0.4,0.8],[33,0.2,0.6,0.7],[66,0.3,0.5,0.9]]}"""))
        assertNotNull(ok)
        assertEquals(1, ok!!.bounce)
        assertEquals(3, ok.points.size)

        assertNull(parseClipTrack(JSONObject("""{"aspect":1.7,"points":[[0,0.1,0.4]]}""")))
        assertNull(parseClipTrack(JSONObject("""{"aspect":0,"points":[[0,0.1,0.4],[1,0.2,0.4],[2,0.3,0.4]]}""")))
        assertNull(parseClipTrack(null))
        // A bounce index outside the points is dropped, not trusted.
        assertNull(parseClipTrack(JSONObject("""{"aspect":1.7,"bounce":9,"points":[[0,0.1,0.4],[1,0.2,0.4],[2,0.3,0.4]]}"""))!!.bounce)
    }

    @Test
    fun `the clock starts at zero and never runs backwards`() {
        assertEquals(listOf(0, 33, 33, 70), flightTimesOf(listOf(1000, 1033, 1020, 1070)))
    }

    @Test
    fun `a stopped clock is replaced by an even frame rate`() {
        assertEquals(listOf(0, 33, 66), flightTimesOf(listOf(500, 500, 500)))
    }

    @Test
    fun `the ball moves on the sensor's clock, not per frame`() {
        // Fast then slow: the first gap is 20 ms, the second 80 ms.
        val times = listOf(0, 20, 100)
        assertEquals(1f, indexAtTime(times, 20f), 1e-6f)
        assertEquals(1.5f, indexAtTime(times, 60f), 1e-6f)
        assertEquals(0f, indexAtTime(times, -5f), 0f)
        assertEquals(2f, indexAtTime(times, 500f), 0f)
    }

    @Test
    fun `sightings sharing a stamp are passed over, not divided by`() {
        assertEquals(2f, indexAtTime(listOf(0, 33, 33, 66), 33f), 1e-6f)
    }
}
