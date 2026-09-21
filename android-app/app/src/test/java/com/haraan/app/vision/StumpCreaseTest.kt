package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Preferring the wicket that stands on a line.
 *
 * The failure this was written for: once the horizon constraint removed the trees, the
 * detector picked the batter's two pads and bat instead — three bars, on the ground,
 * evenly spaced, similar height, standing on one line. Every shape rule passes, because
 * geometrically that IS a wicket.
 *
 * What separates them is where they stand. Stumps sit on the bowling crease. So nearness
 * to a crease-angled segment breaks the tie. The tests that matter most here are the two
 * boundaries of that idea: with no segments the behaviour must be exactly what it was, and
 * nearness must never promote a badly shaped triple over a well shaped one.
 */
class StumpCreaseTest {

    private fun bar(x: Float, baseY: Float, height: Float) =
        StumpCandidate(x, baseY, baseY - height)

    /** Three bars sitting on the ground at [baseY]. */
    private fun trio(centreX: Float, baseY: Float) = listOf(
        bar(centreX - 0.02f, baseY, 0.12f),
        bar(centreX, baseY, 0.12f),
        bar(centreX + 0.02f, baseY, 0.12f),
    )

    /** A horizontal crease-angled segment across the middle of the frame. */
    private fun crease(y: Float) = CreaseSegment(0.2f, y, 0.8f, y)

    private val aspect = 16f / 9f

    // ---- the tie-break ------------------------------------------------------

    @Test
    fun `of two equally wicket-shaped triples, the one on the crease wins`() {
        // Identical shape, different places. Without creases these score the same and the
        // pick is arbitrary; with them it is not.
        val onCrease = trio(centreX = 0.35f, baseY = 0.60f)
        val offCrease = trio(centreX = 0.70f, baseY = 0.85f)

        val set = StumpGeometry.findSet(onCrease + offCrease, listOf(crease(0.60f)), aspect)

        assertNotNull(set)
        assertEquals("the triple standing on the line should win", 0.35f, set!!.middle.centreX, 1e-3f)
    }

    @Test
    fun `nearness is reported, not just folded into the score`() {
        val set = StumpGeometry.findSet(trio(0.5f, 0.60f), listOf(crease(0.60f)), aspect)!!

        assertNotNull(set.creaseDistance)
        assertEquals("standing on it means zero distance", 0.0f, set.creaseDistance!!, 1e-3f)
    }

    @Test
    fun `a triple far from every segment still scores on its shape alone`() {
        // Not punished into oblivion: the crease detector has bad frames, and a wicket
        // should not vanish because of one.
        val far = StumpGeometry.findSet(trio(0.5f, 0.90f), listOf(crease(0.20f)), aspect)!!

        assertTrue("it must still be returned", far.score > 0f)
        assertTrue("but ranked below a perfect one", far.score < 1f)
    }

    // ---- what must not change ----------------------------------------------

    @Test
    fun `with no segments the score is exactly the shape score`() {
        val withNone = StumpGeometry.evaluate(
            bar(0.48f, 0.70f, 0.12f),
            bar(0.50f, 0.70f, 0.12f),
            bar(0.52f, 0.70f, 0.12f),
        )!!

        // A perfect wicket, judged on shape alone, still scores at the top. An absent
        // signal must never quietly reweight everything.
        assertEquals(1f, withNone.score, 1e-3f)
        assertNull(withNone.creaseDistance)
    }

    @Test
    fun `nearness cannot promote a triple that is not wicket-shaped`() {
        // Sitting exactly on the crease, but spaced like a fence. The shape rules are a
        // gate, reached before any of the ranking, and no amount of nearness opens it.
        assertNull(
            StumpGeometry.findSet(
                listOf(
                    bar(0.20f, 0.60f, 0.08f),
                    bar(0.50f, 0.60f, 0.08f),
                    bar(0.80f, 0.60f, 0.08f),
                ),
                listOf(crease(0.60f)),
                aspect,
            ),
        )
    }

    @Test
    fun `a well shaped triple beats a scruffy one even when the scruffy one is nearer`() {
        // Nearness is worth half the score, so it settles ties and does not overturn a
        // clear difference in shape.
        val tidyOffCrease = StumpGeometry.evaluate(
            bar(0.48f, 0.70f, 0.12f),
            bar(0.50f, 0.70f, 0.12f),
            bar(0.52f, 0.70f, 0.12f),
            listOf(crease(0.695f)),
            aspect,
        )!!
        val scruffyOnCrease = StumpGeometry.evaluate(
            bar(0.28f, 0.612f, 0.085f),
            bar(0.32f, 0.600f, 0.130f),
            bar(0.39f, 0.605f, 0.115f),
            listOf(crease(0.600f)),
            aspect,
        )!!

        assertTrue(
            "${tidyOffCrease.score} should beat ${scruffyOnCrease.score}",
            tidyOffCrease.score > scruffyOnCrease.score,
        )
    }

    // ---- the distance itself ------------------------------------------------

    @Test
    fun `distance to a segment is measured in frame widths`() {
        val segment = CreaseSegment(0.0f, 0.5f, 1.0f, 0.5f)

        // 0.1 of the HEIGHT below the line is 0.1/aspect of the width on a 16:9 frame.
        assertEquals(0.1f / aspect, segment.distanceFrom(0.5f, 0.6f, aspect), 1e-4f)
    }

    @Test
    fun `a segment off to one side is not credited for its infinite line`() {
        // The crease runs across the far left. A wicket on the right is NOT on it, even
        // though the line extended would pass straight through.
        val segment = CreaseSegment(0.0f, 0.5f, 0.2f, 0.5f)

        val nearEnd = segment.distanceFrom(0.2f, 0.5f, aspect)
        val wellPast = segment.distanceFrom(0.9f, 0.5f, aspect)

        assertEquals(0f, nearEnd, 1e-4f)
        assertEquals("clamped to the segment, so the gap is real", 0.7f, wellPast, 1e-3f)
    }

    @Test
    fun `a degenerate segment is a point, not a divide by zero`() {
        val dot = CreaseSegment(0.5f, 0.5f, 0.5f, 0.5f)

        assertEquals(0f, dot.distanceFrom(0.5f, 0.5f, aspect), 1e-4f)
        assertTrue(dot.distanceFrom(0.9f, 0.5f, aspect) > 0.3f)
    }
}
