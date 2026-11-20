package com.haraan.app.vision

import org.junit.Assume
import org.junit.Test
import java.io.File
import java.io.RandomAccessFile

/**
 * The ball's full-resolution width on a REAL clip (bowling session 2026-11-19, ball 6), at the
 * hand-checked ball positions in truth.txt — the sizer judged apart from the tracker. Needs
 * VISION_BENCH_DIR with s1119b6.raw; skipped otherwise. Prints; asserts nothing yet.
 */
class BallSizerRealTest {
    @Test
    fun widths() {
        val dir = File(System.getenv("VISION_BENCH_DIR") ?: "")
        val raw = File(dir, "s1119b6.raw")
        Assume.assumeTrue(raw.isFile)
        val truth = javaClass.classLoader!!.getResource("vision-bench/truth.txt")!!.readText().lines()
        val start = truth.indexOfFirst { it.startsWith("clip s1119b6") }
        val balls = truth.drop(start + 1).takeWhile { !it.startsWith("clip") }
            .filter { it.startsWith("ball ") }
            .map { it.split(" ").let { p -> Triple(p[1].toInt(), p[2].toFloat(), p[3].toFloat()) } }
        val w = ClipFrames.SENSOR_W
        val h = ClipFrames.SENSOR_H
        val frameBytes = (w * h + w / 2 * (h / 2)).toLong()
        val f = RandomAccessFile(raw, "r")
        fun luma(i: Int) = ByteArray(w * h).also { f.seek(i * frameBytes); f.readFully(it) }
        for ((i, x, y) in balls) {
            val d = BallSizer.measure(luma(i), luma(i - 1), w, h, w, w, 90, x, y, luma(i - 2), w)
            println("frame $i at %.3f,%.3f: %s".format(x, y, d?.let { "%.4f fw -> %.1f m".format(it, 1.207 * 0.067 / it) } ?: "-"))
        }
        // The same frames through the sizer's own ring, as the pipeline feeds it.
        val sizer = BallSizer()
        val at = balls.associate { it.first to (it.second to it.third) }
        for (i in 80..110) {
            val s = at[i]?.let { (x, y) -> BallSighting(i * 33L, x, y, 1f, 10) }
            val d = sizer.frame(luma(i), w, h, w, 90, i * 33L, s)
            if (s != null) println("ring frame $i: ${d?.let { "%.4f".format(it) } ?: "-"}")
        }
        f.close()
    }
}
