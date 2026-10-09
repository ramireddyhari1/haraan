package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Assume
import org.junit.Test
import java.io.File
import javax.imageio.ImageIO
import kotlin.math.hypot

/**
 * A REAL THROW, filmed on a Realme RMX3933 standing still behind the bowler's arm in a
 * backyard (2026-10-09): the ball leaves the hand near the lens and falls past the left of
 * the stumps. Frames 54-67 of the recorded clip, grey, 720x1280, 20 fps, after the empty
 * scene the camera saw before the throw (background.png, the median of frames 0-39).
 *
 * GROUND TRUTH: the ball's centre in each frame, measured by subtracting that empty scene
 * from the frame and taking the round blob's centroid at full resolution. Frame 62 has no
 * truth — the dark ball is over a dark tile and nothing finds it there.
 */
class RealThrowTest {

    private val truth = mapOf(
        59 to (0.2451 to 0.2366),
        60 to (0.3030 to 0.3357),
        61 to (0.3432 to 0.4117),
        63 to (0.3953 to 0.5295),
        64 to (0.4116 to 0.5779),
    )

    private fun luma(file: File): Triple<ByteArray, Int, Int> {
        val img = ImageIO.read(file)
        val out = ByteArray(img.width * img.height)
        img.raster.getDataElements(0, 0, img.width, img.height, out)
        return Triple(out, img.width, img.height)
    }

    @Test
    fun `a real throw is tracked in the air, where the ball really is`() {
        val dir = File("src/test/resources/throw")
        val files = dir.listFiles { f -> f.name.startsWith("f") && f.name.endsWith(".png") }.orEmpty().sortedBy { it.name }
        val bg = File(dir, "background.png")
        Assume.assumeTrue(files.size >= 10 && bg.exists())
        OpenCvJvm.require()
        val tracker = OpenCvBallTracker()

        // The camera standing still before the throw: the empty scene, a few frames of it.
        val (bgLuma, bw, bh) = luma(bg)
        for (k in 0 until 8) tracker.onFrame(bgLuma, bw, bh, bw, 0, k * 50L)

        val emitted = LinkedHashMap<Int, Pair<Float, Float>>()
        var firstConfirmed: Int? = null
        for (file in files) {
            val (y, w, h) = luma(file)
            val idx = file.name.removePrefix("f").removeSuffix(".png").toInt()
            val s = tracker.onFrame(y, w, h, w, 0, 1000L + idx * 50L)
            if (s != null) {
                emitted[idx] = s.x to s.y
                if (firstConfirmed == null) firstConfirmed = idx
            }
            println("f$idx sighting=${s?.let { "%.4f,%.4f".format(it.x, it.y) }} truth=${truth[idx]}")
        }
        // The trail the screen draws, with every point's error against the truth.
        val trail = tracker.track()
        var worst = 0.0
        var compared = 0
        for (p in trail) {
            val idx = ((p.timestampMs - 1000L) / 50L).toInt()
            val t = truth[idx] ?: continue
            val err = hypot(p.x - t.first, (p.y - t.second) * 16.0 / 9.0)
            println("trail f$idx (%.4f,%.4f) error %.4f fw".format(p.x, p.y, err))
            worst = maxOf(worst, err)
            compared++
        }

        assertTrue("never confirmed", firstConfirmed != null)
        assertTrue("confirmed only at frame $firstConfirmed — the ball must be caught in the air", firstConfirmed!! <= 62)
        assertTrue("only $compared trail points against the truth", compared >= 3)
        // Every point within 1% of the frame width of where the ball really was.
        assertTrue("worst trail error %.4f of the frame width".format(worst), worst <= 0.010)
        // No stale repeats: the ghost of the last position reported again as a new one.
        val values = emitted.values.toList()
        for (i in 1 until values.size) {
            assertTrue("repeated point ${values[i]}", values[i] != values[i - 1])
        }
        assertEquals(trail.size, trail.map { it.timestampMs }.distinct().size)
    }
}
