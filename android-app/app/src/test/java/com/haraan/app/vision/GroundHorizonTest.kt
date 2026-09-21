package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The constraint that stops a tree being a stump.
 *
 * The failure this was written for: on club footage the wicket detector drew three amber
 * bars across tree trunks at the treeline and across posts on the boundary fence, while
 * the real stumps sat untouched in the middle of the frame. No shape rule could have
 * caught it — three trunks ARE three evenly spaced bars of similar height standing on one
 * line. Only where they stand tells them apart.
 *
 * The most important tests here are the ones where the answer is null. A guessed horizon
 * rejects real stumps on exactly the footage this exists to rescue, so "I cannot tell" has
 * to be a first-class answer rather than a fallback to something confident.
 */
class GroundHorizonTest {

    /** A frame of [rows] rows: quiet dark grass below, busy bright background above. */
    private fun frame(
        rows: Int = 100,
        horizonRow: Int = 40,
        groundTexture: Float = 5f,
        groundBrightness: Float = 90f,
        aboveTexture: Float = 40f,
        aboveBrightness: Float = 95f,
    ): Pair<FloatArray, FloatArray> {
        val texture = FloatArray(rows) { if (it <= horizonRow) aboveTexture else groundTexture }
        val brightness = FloatArray(rows) { if (it <= horizonRow) aboveBrightness else groundBrightness }
        return texture to brightness
    }

    // ---- finding it ---------------------------------------------------------

    @Test
    fun `a treeline is found by how busy it is`() {
        val (texture, brightness) = frame(rows = 100, horizonRow = 40)

        val horizon = GroundHorizon.estimate(texture, brightness)

        assertTrue(horizon != null)
        assertEquals(0.40f, horizon!!, 0.02f)
    }

    @Test
    fun `sky is found by how bright it is, since it has no texture at all`() {
        // Texture alone would walk straight through sky — it is the smoothest thing in any
        // frame. Brightness is what catches it.
        val rows = 100
        val texture = FloatArray(rows) { if (it <= 30) 1f else 6f }
        val brightness = FloatArray(rows) { if (it <= 30) 220f else 90f }

        val horizon = GroundHorizon.estimate(texture, brightness)

        assertTrue(horizon != null)
        assertEquals(0.30f, horizon!!, 0.02f)
    }

    @Test
    fun `the boundary reported is the bottom of the background, not the top`() {
        // The lowest non-ground row is what a stump base must sit below. Reporting the top
        // of the treeline would put the line metres too high and let every trunk through.
        val (texture, brightness) = frame(rows = 200, horizonRow = 60)

        val horizon = GroundHorizon.estimate(texture, brightness)!!

        assertEquals(60f / 200f, horizon, 0.01f)
    }

    // ---- refusing to guess --------------------------------------------------

    @Test
    fun `a frame that is all ground has no horizon rather than a made-up one`() {
        val rows = 100
        val flat = FloatArray(rows) { 5f }
        val even = FloatArray(rows) { 90f }

        assertNull(GroundHorizon.estimate(flat, even))
    }

    @Test
    fun `a black lead-in frame says nothing`() {
        // Every clip tested so far opens on black. Measuring a horizon against nothing and
        // acting on it would throw out the first real frames.
        val rows = 100
        assertNull(GroundHorizon.estimate(FloatArray(rows), FloatArray(rows)))
    }

    @Test
    fun `one bright line is a crease, not a horizon`() {
        // A single busy row is a boundary rope, a sightscreen edge or painted paint. A
        // treeline is dozens of rows deep, and the run length is what separates them.
        val rows = 100
        val texture = FloatArray(rows) { if (it == 30) 60f else 5f }
        val brightness = FloatArray(rows) { 90f }

        assertNull(GroundHorizon.estimate(texture, brightness))
    }

    @Test
    fun `a horizon found near the bottom is rejected as implausible`() {
        // Ground reduced to a sliver means the statistics were misread, not that the camera
        // is pointed at the sky. Acting on it would reject every stump in the frame.
        val (texture, brightness) = frame(rows = 100, horizonRow = 95)

        assertNull(GroundHorizon.estimate(texture, brightness))
    }

    @Test
    fun `mismatched or tiny inputs are refused`() {
        assertNull(GroundHorizon.estimate(FloatArray(100), FloatArray(50)))
        assertNull(GroundHorizon.estimate(FloatArray(4) { 5f }, FloatArray(4) { 90f }))
    }

    // ---- using it -----------------------------------------------------------

    @Test
    fun `a trunk standing at the treeline is refused`() {
        // The actual failure: base halfway up the picture, far above the grass.
        assertFalse(GroundHorizon.isOnGround(baseY = 0.18f, horizon = 0.45f))
    }

    @Test
    fun `a stump standing on the pitch is allowed`() {
        assertTrue(GroundHorizon.isOnGround(baseY = 0.72f, horizon = 0.45f))
    }

    @Test
    fun `a wicket whose feet sit just into the treeline is still allowed`() {
        // The estimate is coarse and a wicket can stand a little above it. The tolerance
        // is what stops the fix throwing out the thing it was added to find.
        assertTrue(GroundHorizon.isOnGround(baseY = 0.43f, horizon = 0.45f))
        assertFalse("but not far above it", GroundHorizon.isOnGround(baseY = 0.30f, horizon = 0.45f))
    }

    @Test
    fun `an unknown horizon constrains nothing`() {
        // Null must never mean "reject everything" — on footage where the estimate fails,
        // the detector should behave exactly as it did before this existed.
        assertTrue(GroundHorizon.isOnGround(baseY = 0.05f, horizon = null))
        assertTrue(GroundHorizon.isOnGround(baseY = 0.95f, horizon = null))
    }

    @Test
    fun `row texture is the mean step between neighbours`() {
        assertEquals(0f, GroundHorizon.rowTextureOf(floatArrayOf(7f, 7f, 7f)), 1e-6f)
        assertEquals(10f, GroundHorizon.rowTextureOf(floatArrayOf(0f, 10f, 20f)), 1e-6f)
        assertEquals(0f, GroundHorizon.rowTextureOf(floatArrayOf(5f)), 1e-6f)
    }
}
