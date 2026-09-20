package com.haraan.app.camera

import androidx.compose.ui.geometry.Offset
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The mapping between the camera's picture and the screen it is drawn on.
 *
 * This is here because the bug it guards against is invisible. Overlays on the camera
 * screen were being drawn straight across the viewfinder while their coordinates were
 * normalised inside the ANALYSIS frame — a different rectangle — and the result still
 * looked like a pitch outline and still looked like a ball trail. It was simply in the
 * wrong place, worst at the edges, which is where the ball is at release and where the
 * creases are.
 *
 * Nothing about that shows up in a screenshot review. It shows up as a corridor that sits
 * slightly off the crease on a real ground, which is exactly the signal the overlay exists
 * to give and exactly the one nobody can trust once it has lied.
 */
class CameraOverlayGeometryTest {

    private val tolerance = 0.001f

    @Test
    fun `a tall view letterboxes a wide picture top and bottom`() {
        // A 1080x2400 phone showing 16:9.
        val rect = frameRect(width = 1080f, height = 2400f, aspect = 16f / 9f)

        assertEquals(0f, rect.left, tolerance)
        assertEquals(1080f, rect.right, tolerance)
        // 1080 / (16/9) = 607.5 of picture, centred in 2400.
        assertEquals(607.5f, rect.height, tolerance)
        assertEquals((2400f - 607.5f) / 2f, rect.top, tolerance)
        // Bars above and below are equal, or the picture is off centre.
        assertEquals(rect.top, 2400f - rect.bottom, tolerance)
    }

    @Test
    fun `a wide view letterboxes a narrow picture at the sides`() {
        val rect = frameRect(width = 2400f, height = 1080f, aspect = 9f / 16f)

        assertEquals(0f, rect.top, tolerance)
        assertEquals(1080f, rect.height, tolerance)
        assertEquals(1080f * (9f / 16f), rect.width, tolerance)
        assertEquals(rect.left, 2400f - rect.right, tolerance)
    }

    @Test
    fun `an exactly matching view has no bars at all`() {
        val rect = frameRect(width = 1600f, height = 900f, aspect = 16f / 9f)

        assertEquals(0f, rect.left, tolerance)
        assertEquals(0f, rect.top, tolerance)
        assertEquals(1600f, rect.width, tolerance)
        assertEquals(900f, rect.height, tolerance)
    }

    @Test
    fun `an unknown aspect falls back to the whole view`() {
        // Before the first analysis frame arrives there is nothing to fit to, and the old
        // behaviour — draw across everything — is the only honest answer.
        val rect = frameRect(width = 1080f, height = 2400f, aspect = 0f)

        assertEquals(0f, rect.left, tolerance)
        assertEquals(0f, rect.top, tolerance)
        assertEquals(1080f, rect.width, tolerance)
        assertEquals(2400f, rect.height, tolerance)
    }

    @Test
    fun `the centre of the picture is the centre of the picture, not of the view`() {
        val rect = frameRect(width = 1080f, height = 2400f, aspect = 16f / 9f)
        val centre = rect.at(0.5f, 0.5f)

        assertEquals(540f, centre.x, tolerance)
        assertEquals(1200f, centre.y, tolerance)
    }

    @Test
    fun `the far edge of the picture is inside the view, not at its edge`() {
        val rect = frameRect(width = 1080f, height = 2400f, aspect = 16f / 9f)

        // The failure this whole class exists for: y = 1 used to land on the bottom of the
        // SCREEN. It belongs on the bottom of the picture, a long way above it.
        val bottom = rect.at(0.5f, 1f)
        assertEquals(rect.bottom, bottom.y, tolerance)
        assertTrue("the picture's foot must sit above the view's", bottom.y < 2400f - 800f)
    }

    @Test
    fun `placing a point and reading it back returns the same point`() {
        // A tapped crease corner goes through normalise on the way in and at on the way
        // out. Any disagreement between the two would tilt the calibration by a fixed
        // amount and stay invisible, because the taps would still be drawn where they
        // were made.
        val rect = frameRect(width = 1440f, height = 3040f, aspect = 16f / 9f)

        listOf(0f to 0f, 0.25f to 0.75f, 0.5f to 0.5f, 1f to 1f).forEach { (x, y) ->
            val roundTrip = rect.normalise(rect.at(x, y))
            assertEquals(x, roundTrip.x, tolerance)
            assertEquals(y, roundTrip.y, tolerance)
        }
    }

    @Test
    fun `a tap on a letterbox bar is outside the picture`() {
        val rect = frameRect(width = 1080f, height = 2400f, aspect = 16f / 9f)

        // Fifty pixels down from the top of a phone is black bar, not pitch. Recording it
        // as a crease corner would hand the homography a point from outside the image.
        assertTrue(rect.contains(Offset(540f, 1200f)))
        assertTrue(!rect.contains(Offset(540f, 50f)))
        assertTrue(!rect.contains(Offset(540f, 2350f)))
    }
}
