package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The gully wicket's rules, tested away from a camera.
 *
 * Everything in [StoneGeometry] is arithmetic on boxes, which is the only part of the
 * vision stack that can be pinned down without a ground, a phone and an afternoon. The
 * pixels that produce these boxes are judged in the field; the judgement applied to them
 * is judged here.
 */
class StoneGeometryTest {

    private fun stone(
        centreX: Float = 0.5f,
        baseY: Float = 0.70f,
        height: Float = 0.10f,
        width: Float = 0.055f,
        solidity: Float = 0.88f,
    ) = StoneCandidate(
        centreX = centreX,
        baseY = baseY,
        topY = baseY - height,
        width = width,
        solidity = solidity,
    )

    /** A crease-angled segment running across the frame at the stone's feet. */
    private fun creaseAt(y: Float) = CreaseSegment(0.1f, y, 0.9f, y)

    @Test
    fun `a solid upright lump on the ground is a wicket`() {
        val mark = StoneGeometry.findMark(listOf(stone()))
        assertNotNull(mark)
        assertEquals(0.5f, mark!!.centreX, 1e-4f)
        assertEquals(0.70f, mark.baseY, 1e-4f)
    }

    @Test
    fun `foliage is not`() {
        // Same box, same place. The only difference is that it does not fill its box.
        assertNull(StoneGeometry.findMark(listOf(stone(solidity = 0.35f))))
    }

    @Test
    fun `a bar is left to the three-bar detector`() {
        // Ten times taller than wide: a fence post or a stump, and admitting it here would
        // let it back in after StumpGeometry had correctly refused it.
        val bar = stone(height = 0.20f, width = 0.02f)
        assertTrue(bar.aspect > StoneGeometry.MAX_ASPECT)
        assertNull(StoneGeometry.findMark(listOf(bar)))
    }

    @Test
    fun `speckle and scenery are both out of range`() {
        assertNull(StoneGeometry.findMark(listOf(stone(height = 0.004f, width = 0.003f))))
        assertNull(StoneGeometry.findMark(listOf(stone(height = 0.45f, width = 0.20f))))
    }

    @Test
    fun `standing on a crease beats standing on nothing`() {
        val candidate = stone()
        val onLine = StoneGeometry.findMark(listOf(candidate), listOf(creaseAt(0.70f)), 1.78f)
        val noLines = StoneGeometry.findMark(listOf(candidate), emptyList(), 1.78f)

        assertNotNull(onLine)
        assertNotNull(noLines)
        assertTrue(
            "a mark on the crease must outscore the same mark with no crease",
            onLine!!.score > noLines!!.score,
        )
        assertEquals(0f, onLine.creaseDistance!!, 1e-3f)
        // Null is "nobody offered any", and must never be reported as a distance.
        assertNull(noLines.creaseDistance)
    }

    @Test
    fun `creases somewhere else are worse than no creases at all`() {
        val candidate = stone()
        val far = StoneGeometry.findMark(listOf(candidate), listOf(creaseAt(0.15f)), 1.78f)
        val none = StoneGeometry.findMark(listOf(candidate), emptyList(), 1.78f)

        assertNotNull(far)
        assertTrue(far!!.score < none!!.score)
    }

    @Test
    fun `a field of rubble is refused however good its best lump is`() {
        val rubble = (0..12).map { stone(centreX = 0.05f + it * 0.07f) }
        assertNull(StoneGeometry.findMark(rubble))
    }

    @Test
    fun `a wicket at each end is expected and not punished`() {
        // Two marks of the same size is the normal case: both ends of the pitch.
        val pair = listOf(stone(centreX = 0.35f), stone(centreX = 0.62f))
        val mark = StoneGeometry.findMark(pair)
        assertNotNull(mark)
        assertEquals(1, mark!!.lookalikes)
        // One lookalike costs nothing, so it scores as a lone mark would.
        assertEquals(StoneGeometry.findMark(listOf(stone()))!!.score, mark.score, 1e-4f)
    }

    @Test
    fun `the best lump in frame is the one returned`() {
        val scruffy = stone(centreX = 0.25f, solidity = 0.62f)
        val clean = stone(centreX = 0.75f, solidity = 0.97f)
        val mark = StoneGeometry.findMark(listOf(scruffy, clean))
        assertEquals(0.75f, mark!!.centreX, 1e-4f)
    }

    @Test
    fun `a stone on the line beats a better-shaped one off it`() {
        // The evidence term is a multiplier for exactly this reason: on a ground, the
        // scruffy thing sitting on the crease is the wicket.
        val prettyOffLine = stone(centreX = 0.20f, baseY = 0.40f, solidity = 0.99f)
        val scruffyOnLine = stone(centreX = 0.80f, baseY = 0.70f, solidity = 0.70f)
        val mark = StoneGeometry.findMark(
            listOf(prettyOffLine, scruffyOnLine),
            listOf(creaseAt(0.70f)),
            1.78f,
        )
        assertEquals(0.80f, mark!!.centreX, 1e-4f)
    }
}
