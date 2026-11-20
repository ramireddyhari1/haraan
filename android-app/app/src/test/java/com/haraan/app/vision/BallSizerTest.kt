package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * The ball's width at full resolution, on drawn frames laid out as the camera's: sideways
 * 1280x720 luma, turned 90° to upright.
 */
class BallSizerTest {

    private val w = 1280
    private val h = 720

    /** A frame of textured grey with a bright disc of [diameterPx] at sensor ([cx], [cy]), smeared [smearPx] along x. */
    private fun frame(cx: Double?, cy: Double, diameterPx: Double, smearPx: Double = 0.0, seed: Int = 1): ByteArray {
        val f = ByteArray(w * h)
        for (y in 0 until h) for (x in 0 until w) {
            val n = ((x * 7 + y * 13 + seed) % 11) - 5
            f[y * w + x] = (110 + n).toByte()
        }
        if (cx != null) {
            val r = diameterPx / 2
            for (y in (cy - r - 2).toInt()..(cy + r + 2).toInt()) for (x in (cx - r - smearPx - 2).toInt()..(cx + r + 2).toInt()) {
                if (x !in 0 until w || y !in 0 until h) continue
                // Inside the capsule swept from cx - smear to cx.
                val px = x.coerceIn((cx - smearPx).toInt(), cx.toInt()).toDouble()
                val dx = x - px
                val dy = y - cy
                if (dx * dx + dy * dy <= r * r) f[y * w + x] = 230.toByte()
            }
        }
        return f
    }

    /** Upright normalised position of sensor ([sx], [sy]) at a 90° turn. */
    private fun upright(sx: Double, sy: Double) = Pair((1 - sy / h).toFloat(), (sx / w).toFloat())

    @Test
    fun `a still-sharp ball is measured at its width`() {
        for (d in listOf(10.0, 16.0, 24.0, 36.0)) {
            val cur = frame(600.0, 300.0, d)
            val old = frame(null, 0.0, 0.0)
            val (ux, uy) = upright(600.0, 300.0)
            val got = BallSizer.measure(cur, old, w, h, w, w, 90, ux, uy)!!
            assertEquals("a $d px ball", d / h, got.toDouble(), 1.6 / h)
        }
    }

    @Test
    fun `a smeared ball is measured across its smear`() {
        val cur = frame(600.0, 300.0, 20.0, smearPx = 18.0)
        val old = frame(null, 0.0, 0.0)
        val (ux, uy) = upright(600.0, 300.0)
        assertEquals(20.0 / h, BallSizer.measure(cur, old, w, h, w, w, 90, ux, uy)!!.toDouble(), 1.6 / h)
    }

    @Test
    fun `the ball where it was a moment ago is not counted`() {
        // The older frame has the ball 60 px back: its ghost is a separate patch.
        val cur = frame(600.0, 300.0, 20.0)
        val old = frame(540.0, 300.0, 20.0)
        val (ux, uy) = upright(600.0, 300.0)
        assertEquals(20.0 / h, BallSizer.measure(cur, old, w, h, w, w, 90, ux, uy)!!.toDouble(), 1.6 / h)
    }

    @Test
    fun `nothing moved, nothing measured`() {
        val still = frame(600.0, 300.0, 20.0)
        val (ux, uy) = upright(600.0, 300.0)
        assertNull(BallSizer.measure(still, still, w, h, w, w, 90, ux, uy))
    }
}
