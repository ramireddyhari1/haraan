package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The streaming decoder's sampler must put every stored pixel where [ClipFrames.upright] puts
 * it: turned by the file's rotation, centre-cropped to 9:16, scaled — for 8-bit and 10-bit.
 */
class UprightSamplerTest {

    /** A stored picture whose luma is bright (235) inside [box] (x0, y0, x1, y1) and dark elsewhere. */
    private fun planes(w: Int, h: Int, box: IntArray, wide: Boolean): Triple<ByteArray, ByteArray, ByteArray> {
        val step = if (wide) 2 else 1
        val y = ByteArray(w * h * step)
        for (py in 0 until h) for (px in 0 until w) {
            val bright = px in box[0] until box[2] && py in box[1] until box[3]
            val v = if (bright) 235 else 16
            if (wide) {
                // P010: ten bits in the top of a little-endian 16-bit sample.
                val s = v shl 8
                y[(py * w + px) * 2] = (s and 0xFF).toByte()
                y[(py * w + px) * 2 + 1] = (s shr 8).toByte()
            } else {
                y[py * w + px] = v.toByte()
            }
        }
        val c = ByteArray((w / 2) * (h / 2) * step * 2) { if (wide && it % 2 == 0) 0 else 128.toByte() }
        return Triple(y, c, c)
    }

    private fun brightCentre(out: IntArray, w: Int, h: Int): Pair<Double, Double> {
        var sx = 0.0; var sy = 0.0; var n = 0
        for (i in out.indices) if ((out[i] and 0xFF) > 200) { sx += i % w; sy += i / w; n++ }
        assertTrue("nothing bright came through", n > 0)
        return (sx / n / w) to (sy / n / h)
    }

    private fun run(srcW: Int, srcH: Int, rotation: Int, box: IntArray, wide: Boolean = false): Pair<Double, Double> {
        val (y, u, v) = planes(srcW, srcH, box, wide)
        val step = if (wide) 2 else 1
        val s = UprightSampler(90, 160)
        s.prepare(
            srcW = srcW, srcH = srcH, cropX = 0, cropY = 0, rotation = rotation,
            yRowStride = srcW * step, yPixelStride = step,
            cRowStride = srcW * step, cPixelStride = 2 * step,
            vRowStride = srcW * step, vPixelStride = 2 * step,
        )
        val out = IntArray(90 * 160)
        s.fill(y, u, v, wide, out)
        return brightCentre(out, 90, 160)
    }

    @Test
    fun `a portrait clip stored sideways (rotation 90) stands up`() {
        // Stored 320x180 (landscape). A spot near the stored TOP-LEFT is, after a clockwise
        // quarter turn, near the upright TOP-RIGHT.
        val (x, y) = run(320, 180, 90, intArrayOf(10, 10, 30, 30))
        assertTrue("x $x should be right", x > 0.75)
        assertTrue("y $y should be top", y < 0.25)
    }

    @Test
    fun `rotation 270 turns the other way`() {
        val (x, y) = run(320, 180, 270, intArrayOf(10, 10, 30, 30))
        assertTrue("x $x should be left", x < 0.25)
        assertTrue("y $y should be bottom", y > 0.75)
    }

    @Test
    fun `an upright portrait clip is copied as it is`() {
        val (x, y) = run(180, 320, 0, intArrayOf(80, 150, 100, 170))
        assertEquals(0.5, x, 0.05)
        assertEquals(0.5, y, 0.05)
    }

    @Test
    fun `a 10-bit frame reads the same as an 8-bit one`() {
        val eight = run(320, 180, 90, intArrayOf(100, 40, 140, 80))
        val ten = run(320, 180, 90, intArrayOf(100, 40, 140, 80), wide = true)
        assertEquals(eight.first, ten.first, 0.01)
        assertEquals(eight.second, ten.second, 0.01)
    }

    @Test
    fun `a landscape clip with no rotation is centre-cropped to the portrait frame`() {
        // A spot in the middle of a 16:9 landscape picture stays in the middle.
        val (x, y) = run(320, 180, 0, intArrayOf(150, 80, 170, 100))
        assertEquals(0.5, x, 0.08)
        assertEquals(0.5, y, 0.08)
    }
}
