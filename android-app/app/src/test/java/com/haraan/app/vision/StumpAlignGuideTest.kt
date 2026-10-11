package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class StumpAlignGuideTest {
    private val portrait = 9f / 16f

    @Test
    fun batterStumpsSitWhereAsked() {
        val g = StumpAlignGuide.guide(1.4, portrait)
        assertEquals(StumpAlignGuide.FAR_BASE_Y, g.far.baseY, 1e-9)
        assertEquals(0.5, g.far.centreX, 1e-9)
    }

    @Test
    fun bowlersStumpsAreNearerLowerAndWider() {
        val g = StumpAlignGuide.guide(1.4, portrait)
        assertTrue(g.near.baseY > g.far.baseY + 0.1)
        assertTrue(g.near.width > g.far.width * 4)
        // Tops above bases, in picture terms.
        assertTrue(g.near.topY < g.near.baseY)
        assertTrue(g.far.topY < g.far.baseY)
    }

    @Test
    fun aTypicalLensPutsTheBowlersStumpsInTheLowerThird() {
        // ~0.69 of the height for a 1.4 fw lens, 1.3 m up, 4 m behind.
        val g = StumpAlignGuide.guide(1.4, portrait)
        assertEquals(0.69, g.near.baseY, 0.02)
        // Laws' width through the lens at about 4.2 m.
        assertEquals(1.4 * 0.2286 / Math.hypot(4.0, 1.3), g.near.width, 1e-9)
    }

    @Test
    fun aTallerTripodSeparatesTheWicketsMore() {
        val low = StumpAlignGuide.guide(1.4, portrait, heightM = 0.8)
        val high = StumpAlignGuide.guide(1.4, portrait, heightM = 1.8)
        assertTrue(high.near.baseY > low.near.baseY)
    }

    @Test
    fun theBoxForgivesANearMissButNotTheWrongPlace() {
        val g = StumpAlignGuide.guide(1.4, portrait)
        val near = Point2(g.far.centreX + 0.02, g.far.baseY + 0.01)
        val wrong = Point2(g.far.centreX + 0.3, g.far.baseY)
        assertTrue(g.far.contains(near, 0.05, 1.6))
        assertFalse(g.far.contains(wrong, 0.05, 1.6))
    }
}
