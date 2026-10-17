package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * A ball lost at the bounce is still judged: the leg off the pitch is projected — same line,
 * a little pace lost, climbing at half the speed it came down — the way ball tracking does.
 */
class ProjectedOutLegTest {

    private fun full(bounceX: Double) = Flight3d(
        bounceX = bounceX, bounceY = 2.0, bounceMs = 1000.0,
        inVx = 0.0, inVy = -27.0, inVzDown = 5.0,
        outVx = null, outVy = null, outVzUp = null,
        firstSeenMs = 400.0, lastSeenMs = 1000.0, cameraHeightM = 1.3,
    )

    @Test
    fun `a full ball on the stumps with no leg seen after the bounce is judged hitting`() {
        val f = full(0.0)
        assertFalse(f.hasOutLeg)
        assertNotNull("reaches the stumps' line", f.stumpsMs)
        val at = f.atStumps!!
        assertEquals(0.0, at.y, 1e-6)
        assertTrue("stump height at the line, got ${at.z}", at.z in 0.05..0.4)
        assertEquals(true, f.hitsStumps)
    }

    @Test
    fun `the same ball well outside off is judged missing`() {
        assertEquals(false, full(0.45).hitsStumps)
    }

    @Test
    fun `a yorker pitched on the line is judged on the way down`() {
        val y = full(0.0).copy(bounceY = -0.1)
        assertNotNull(y.stumpsMs)
        assertEquals(true, y.hitsStumps)
    }
}
