package com.haraan.app.camera

import com.haraan.app.vision.OpenCvBallTracker
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The colour the live overlay paints a ball sighting.
 *
 * Worth pinning for the same reason the overlay geometry is: nothing about this is
 * checkable by looking at it. A ramp whose ends are in the wrong place still produces a
 * perfectly attractive blue trail — it just produces one for a tracker following a
 * fielder's shirt too, which is the single thing the colour is there to expose.
 */
class CameraTrailColourTest {

    @Test
    fun `a barely accepted sighting is fully amber`() {
        // The weakest score the tracker can emit: a candidate right on the circularity
        // limit whose continuity is almost nothing. Anything at or below the floor has to
        // saturate, not grade.
        val worst = (OpenCvBallTracker.MIN_CIRCULARITY * 0.6).toFloat()
        assertEquals(TrailWeak, trackColour(worst))
        assertEquals(TrailWeak, trackColour(0f))
    }

    @Test
    fun `a well behaved sighting is fully blue before it reaches a perfect score`() {
        // A real ball is motion-blurred and never scores 1.0. If full blue needed a
        // perfect score, a correctly tracked delivery would never show one.
        assertEquals(TrailStrong, trackColour(0.8f))
        assertEquals(TrailStrong, trackColour(1f))
    }

    @Test
    fun `the middle of the range is neither end`() {
        val middle = trackColour(0.58f)
        assertTrue("should have left amber", middle != TrailWeak)
        assertTrue("should not have reached blue", middle != TrailStrong)
        // Between the two, channel by channel.
        assertTrue(middle.red < TrailWeak.red && middle.red > TrailStrong.red)
        assertTrue(middle.blue > TrailWeak.blue && middle.blue < TrailStrong.blue)
    }

    @Test
    fun `a better behaved sighting is never painted worse than a poorer one`() {
        // Monotonic across the whole range, including outside it. A trail where a stronger
        // point looked weaker than the one before would be actively misleading.
        var previousBlue = -1f
        var score = 0f
        while (score <= 1f) {
            val blue = trackColour(score).blue
            assertTrue("blue went backwards at $score", blue >= previousBlue)
            previousBlue = blue
            score += 0.02f
        }
    }

    @Test
    fun `the ramp does not reuse the colour the corner tapping flow owns`() {
        // Tapped crease corners are drawn in 0xFFFACC15. A weak trail in the same yellow
        // would make two unrelated things mean one thing.
        val tapYellow = androidx.compose.ui.graphics.Color(0xFFFACC15)
        assertTrue(TrailWeak != tapYellow)
    }
}
