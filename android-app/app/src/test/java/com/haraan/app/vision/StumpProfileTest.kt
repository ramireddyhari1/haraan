package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs
import kotlin.math.atan

/**
 * The three-stump comb fit, on physically scaled synthetic frames.
 *
 * The distances here are the real ones: the far wicket from behind the bowler's arm is
 * 20 to 25 m away, and that is where the old whole-frame detector could not, by
 * arithmetic, see three bars.
 */
class StumpProfileTest {

    private fun locate(
        s: SyntheticWicket.Scene,
        rotation: Int = 0,
        hintOffsetPx: Double = 0.0,
        spanError: Double = 1.0,
    ): Pair<StumpSet, CombFit>? {
        val (luma, w, h) = SyntheticWicket.sensorLuma(s, rotation)
        return StumpProfile.locate(
            luma = luma, width = w, height = h, rowStride = w, rotationDegrees = rotation,
            centreX = (s.footX + hintOffsetPx) / s.width,
            baseY = s.footY / s.height,
            topY = (s.footY - s.heightPx) / s.height,
            halfSpanFw = s.halfSpanPx * spanError / s.width,
        )
    }

    @Test
    fun `a far wicket at 22 m is found to a fraction of a pixel`() {
        val s = SyntheticWicket.Scene(distanceM = 22.0)
        val (set, fit) = locate(s, hintOffsetPx = 3.0, spanError = 1.2)
            ?: throw AssertionError("not found at 22 m")
        val middlePx = set.middle.centreX * s.width
        assertEquals("centre", s.footX, middlePx.toDouble(), 0.3)
        val spanPx = set.spanX * s.width
        assertEquals("span", 2 * s.halfSpanPx, spanPx.toDouble(), 2 * s.halfSpanPx * 0.06)
        assertEquals("foot", s.footY, set.middle.baseY * s.height.toDouble(), 1.6)
        assertTrue("pale stumps", fit.bright)
        assertTrue("quality ${fit.quality}", fit.quality > 0.6f)
    }

    @Test
    fun `the fit holds out to 32 m`() {
        val s = SyntheticWicket.Scene(distanceM = 32.0)
        assertNotNull("half-span is ${s.halfSpanPx} px", locate(s))
    }

    /**
     * The measurement this whole file exists for: at what distance does each approach stop
     * seeing a wicket? Same scenes, same noise, same blur.
     */
    @Test
    fun `the comb reaches at least twice as far as three separate contours`() {
        fun reach(found: (SyntheticWicket.Scene) -> Boolean): Double {
            var last = 0.0
            var d = 6.0
            while (d <= 40.0) {
                if (found(SyntheticWicket.Scene(distanceM = d))) last = d else break
                d += 2.0
            }
            return last
        }
        val old = reach { SyntheticWicket.oldPipelineResolvesThree(it) }
        val new = reach { locate(it) != null }
        println("three separate contours reach $old m; comb fit reaches $new m")
        assertTrue("old pipeline should fail before the far wicket (reached $old m)", old < 20.0)
        assertTrue("comb should reach the far wicket and beyond (reached $new m)", new >= 30.0)
        assertTrue(new >= 2 * old)
    }

    @Test
    fun `a rolled camera is found and its roll measured from the stumps`() {
        for (roll in listOf(-5.0, 4.0)) {
            val s = SyntheticWicket.Scene(distanceM = 15.0, rollDeg = roll)
            val (_, fit) = locate(s) ?: throw AssertionError("not found at roll $roll")
            // A clockwise roll leans the stumps' tops right, which is x falling going down.
            val measured = -Math.toDegrees(atan(fit.shear))
            assertEquals("roll $roll", roll, measured, 1.6)
        }
    }

    @Test
    fun `stumps against a low sun are the same wicket with the polarity flipped`() {
        val s = SyntheticWicket.Scene(distanceM = 18.0, ground = 190.0, stump = 120.0)
        val (_, fit) = locate(s) ?: throw AssertionError("silhouetted stumps not found")
        assertFalse(fit.bright)
    }

    @Test
    fun `a fence of evenly spaced palings is not a wicket`() {
        val base = SyntheticWicket.Scene(distanceM = 14.0, drawStumps = false)
        val g = base.halfSpanPx
        val posts = (-4..4).map { base.footX + it * g to PitchGeometry.STUMP_DIAMETER_M }
        assertNull(locate(base.copy(posts = posts)))
    }

    @Test
    fun `a single pole as wide as a wicket is not a wicket`() {
        val base = SyntheticWicket.Scene(distanceM = 14.0, drawStumps = false)
        assertNull(locate(base.copy(posts = listOf(base.footX to PitchGeometry.STUMP_SET_WIDTH_M))))
    }

    @Test
    fun `plain noisy ground is not a wicket`() {
        val s = SyntheticWicket.Scene(distanceM = 18.0, drawStumps = false, noise = 6.0)
        assertNull(locate(s))
    }

    @Test
    fun `dim overcast contrast is still found when the noise allows it`() {
        val s = SyntheticWicket.Scene(distanceM = 22.0, ground = 110.0, stump = 132.0, noise = 2.0)
        assertNotNull(locate(s))
    }

    @Test
    fun `a portrait frame gives the same wicket as a landscape one`() {
        // Upright scene is portrait (720 wide); the sensor delivers it landscape + 90°.
        val s = SyntheticWicket.Scene(width = 720, height = 1280, footX = 360.4, footY = 760.0, distanceM = 14.0, hfovDeg = 43.0)
        for (rotation in listOf(0, 90, 180, 270)) {
            val (set, _) = locate(s, rotation = rotation) ?: throw AssertionError("not found at $rotation")
            assertEquals("rotation $rotation", s.footX / s.width, set.middle.centreX.toDouble(), 0.6 / s.width)
            assertEquals("rotation $rotation", s.footY / s.height, set.middle.baseY.toDouble(), 2.0 / s.height)
        }
    }

    @Test
    fun `a near wicket is decimated and still measured correctly`() {
        val s = SyntheticWicket.Scene(distanceM = 4.0, footY = 600.0)
        val (set, _) = locate(s) ?: throw AssertionError("near wicket not found")
        assertEquals(2 * s.halfSpanPx, set.spanX * s.width.toDouble(), 2 * s.halfSpanPx * 0.05)
    }

    @Test
    fun `the template covers the stated stump width`() {
        // Half-span 10 px: each bar is 0.36 × 10 = 3.6 px wide, three of them.
        var total = 0.0
        for (x in 0 until 60) total += StumpProfile.template(x, 30.0, 10.0)
        assertEquals(3 * StumpProfile.BAR_WIDTH_RATIO * 10.0, total, 1e-6)
    }

    @Test
    fun `an roi reads the same pixel under every rotation`() {
        val s = SyntheticWicket.Scene(width = 64, height = 48, drawStumps = false, noise = 0.0, blur = 0.0)
        for (rotation in listOf(0, 90, 180, 270)) {
            val up = DoubleArray(s.width * s.height) { 0.0 }
            up[10 * s.width + 20] = 255.0
            val turn = rotation
            val sw = if (turn == 90 || turn == 270) s.height else s.width
            val sh = if (turn == 90 || turn == 270) s.width else s.height
            val luma = ByteArray(sw * sh)
            for (uy in 0 until s.height) for (ux in 0 until s.width) {
                val (sx, sy) = when (turn) {
                    90 -> uy to (sh - 1 - ux)
                    180 -> (sw - 1 - ux) to (sh - 1 - uy)
                    270 -> (sw - 1 - uy) to ux
                    else -> ux to uy
                }
                luma[sy * sw + sx] = up[uy * s.width + ux].toInt().toByte()
            }
            val roi = UprightRoi.extract(luma, sw, sh, sw, rotation, 0.0, 0.0, 1.0, 1.0, maxSide = 512)!!
            assertEquals(s.width, roi.frameWidth)
            assertEquals("rotation $rotation", 255f, roi.at(20, 10), 0.5f)
            assertTrue(abs(roi.at(21, 10)) < 1f)
        }
    }
}
