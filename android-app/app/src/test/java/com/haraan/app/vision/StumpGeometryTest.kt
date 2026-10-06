package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Telling a wicket from everything else that is tall, thin and bright.
 *
 * A fence paling, a net pole, a sightscreen strut and a batter's front pad all pass the
 * "tall thin bright bar" test on their own. The whole difference is the RELATIONSHIP
 * between three of them — evenly spaced, the same height, standing on one line — so these
 * tests are mostly about the impostors, not the wicket.
 */
class StumpGeometryTest {

    /** A wicket about a fifth of the way up the frame, seen square on. */
    private fun wicket(
        centreX: Float = 0.5f,
        baseY: Float = 0.70f,
        height: Float = 0.12f,
        span: Float = 0.04f,
    ): List<StumpCandidate> {
        val half = span / 2f
        return listOf(
            StumpCandidate(centreX - half, baseY, baseY - height),
            StumpCandidate(centreX, baseY, baseY - height),
            StumpCandidate(centreX + half, baseY, baseY - height),
        )
    }

    private fun bar(x: Float, baseY: Float, height: Float) =
        StumpCandidate(x, baseY, baseY - height)

    // ---- what it must accept ------------------------------------------------

    @Test
    fun `three even bars on one line are a wicket`() {
        val set = StumpGeometry.findSet(wicket())

        assertNotNull(set)
        assertEquals(0.48f, set!!.left.centreX, 1e-4f)
        assertEquals(0.50f, set.middle.centreX, 1e-4f)
        assertEquals(0.52f, set.right.centreX, 1e-4f)
        assertTrue("a perfect wicket should score near the top", set.score > 0.9f)
    }

    @Test
    fun `a wicket is still found among clutter`() {
        // The wicket, plus a fence paling, a pole and a stray mark — the frame a real
        // ground actually offers.
        val clutter = listOf(
            bar(0.05f, 0.62f, 0.26f),
            bar(0.88f, 0.55f, 0.30f),
            bar(0.20f, 0.95f, 0.02f),
        )

        val set = StumpGeometry.findSet(wicket() + clutter)

        assertNotNull(set)
        assertEquals(0.50f, set!!.middle.centreX, 1e-4f)
    }

    @Test
    fun `mild perspective is tolerated`() {
        // Seen slightly from one side: the far stump is a little shorter and its base sits
        // a little higher. That is a wicket, not a rejection.
        val set = StumpGeometry.findSet(
            listOf(
                bar(0.46f, 0.700f, 0.120f),
                bar(0.50f, 0.694f, 0.112f),
                bar(0.535f, 0.689f, 0.106f),
            ),
        )

        assertNotNull(set)
    }

    // ---- what it must refuse ------------------------------------------------

    @Test
    fun `fewer than three bars cannot be a wicket`() {
        assertNull(StumpGeometry.findSet(emptyList()))
        assertNull(StumpGeometry.findSet(wicket().take(2)))
    }

    @Test
    fun `bars at different distances are three objects, not one wicket`() {
        // Same size, evenly spaced, but standing on three different lines — a row of
        // things receding, not a wicket.
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.46f, 0.50f, 0.12f),
                    bar(0.50f, 0.70f, 0.12f),
                    bar(0.54f, 0.90f, 0.12f),
                ),
            ),
        )
    }

    @Test
    fun `wildly uneven spacing is refused`() {
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.30f, 0.70f, 0.12f),
                    bar(0.50f, 0.70f, 0.12f),
                    bar(0.53f, 0.70f, 0.12f),
                ),
            ),
        )
    }

    @Test
    fun `three bars of very different heights are refused`() {
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.46f, 0.70f, 0.05f),
                    bar(0.50f, 0.70f, 0.12f),
                    bar(0.54f, 0.70f, 0.22f),
                ),
            ),
        )
    }

    @Test
    fun `a fence is refused because its palings are too far apart for their height`() {
        // Evenly spaced, same height, standing on one line — a fence passes every test but
        // the one that matters. Palings a third of the frame apart cannot be 0.2286 m.
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.20f, 0.70f, 0.08f),
                    bar(0.50f, 0.70f, 0.08f),
                    bar(0.80f, 0.70f, 0.08f),
                ),
            ),
        )
    }

    @Test
    fun `three bars squeezed together are refused as one smeared object`() {
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.4990f, 0.70f, 0.30f),
                    bar(0.5000f, 0.70f, 0.30f),
                    bar(0.5010f, 0.70f, 0.30f),
                ),
            ),
        )
    }

    @Test
    fun `a bar with no height at all is refused rather than divided by`() {
        assertNull(
            StumpGeometry.evaluate(
                bar(0.46f, 0.70f, 0.0f),
                bar(0.50f, 0.70f, 0.12f),
                bar(0.54f, 0.70f, 0.12f),
            ),
        )
    }

    @Test
    fun `candidates out of left-to-right order are refused, not silently sorted`() {
        // evaluate() takes an ordered triple. findSet is what sorts; evaluate must not
        // quietly accept a jumbled one and report a negative span.
        assertNull(
            StumpGeometry.evaluate(
                bar(0.54f, 0.70f, 0.12f),
                bar(0.50f, 0.70f, 0.12f),
                bar(0.46f, 0.70f, 0.12f),
            ),
        )
    }

    // ---- scoring and scale --------------------------------------------------

    @Test
    fun `a tidier wicket outscores a scruffier one`() {
        val tidy = StumpGeometry.evaluate(
            bar(0.46f, 0.70f, 0.12f),
            bar(0.50f, 0.70f, 0.12f),
            bar(0.54f, 0.70f, 0.12f),
        )!!
        val scruffy = StumpGeometry.evaluate(
            bar(0.46f, 0.705f, 0.100f),
            bar(0.50f, 0.700f, 0.120f),
            bar(0.56f, 0.695f, 0.115f),
        )!!

        assertTrue("${tidy.score} should beat ${scruffy.score}", tidy.score > scruffy.score)
    }

    @Test
    fun `the span fixes a scale along the wicket line and nowhere else`() {
        val set = StumpGeometry.findSet(wicket(span = 0.04f))!!

        // The bars are found by their CENTRES, so 0.04 of the frame is the 0.1936 m between
        // the outer stumps' centres — not the 0.2286 m outside to outside, which overstated
        // every distance by 18%.
        val scale = StumpGeometry.metresPerUnitAcross(set)!!
        // Loose enough to survive the float subtraction that produces the span; the claim
        // under test is the ratio, not the last decimal place of it.
        assertEquals(PitchGeometry.STUMP_CENTRES_SPAN_M / 0.04, scale, 1e-3)
    }

    @Test
    fun `a wicket with no span gives no scale rather than infinity`() {
        val degenerate = StumpSet(
            bar(0.5f, 0.7f, 0.12f),
            bar(0.5f, 0.7f, 0.12f),
            bar(0.5f, 0.7f, 0.12f),
            score = 1f,
        )

        assertNull(StumpGeometry.metresPerUnitAcross(degenerate))
    }

    @Test
    fun `the search does not blow up on a frame full of bars`() {
        // A net: fifty evenly spaced poles. The cap keeps this bounded, and whatever comes
        // back must still be a legitimately wicket-shaped triple.
        val net = (0 until 50).map { bar(0.02f + it * 0.019f, 0.70f, 0.10f) }

        val set = StumpGeometry.findSet(net)

        if (set != null) {
            assertTrue(set.left.centreX < set.middle.centreX)
            assertTrue(set.middle.centreX < set.right.centreX)
        }
    }

    @Test
    fun `runners-up are handed on, one per place`() {
        // The wicket, and a fence-like triple well to the right of it.
        val bars = wicket(centreX = 0.3f, span = 0.04f) + wicket(centreX = 0.7f, span = 0.05f)
        val ranked = StumpGeometry.search(bars).ranked
        assertTrue("both places should be offered, got ${ranked.size}", ranked.size >= 2)
        val mids = ranked.map { (it.left.centreX + it.right.centreX) / 2f }
        assertTrue(mids.any { kotlin.math.abs(it - 0.3f) < 0.01f })
        assertTrue(mids.any { kotlin.math.abs(it - 0.7f) < 0.01f })
        // And no place twice.
        for (i in mids.indices) for (j in i + 1 until mids.size) {
            assertTrue(kotlin.math.abs(mids[i] - mids[j]) > 0.015f)
        }
    }
}
