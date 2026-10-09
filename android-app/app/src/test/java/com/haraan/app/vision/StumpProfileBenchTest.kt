package com.haraan.app.vision

import org.junit.Test

/**
 * How long the comb takes per probe — a relative measure on the build machine, to compare
 * one version of the search with another. Phone times are what the admin panel reports.
 */
class StumpProfileBenchTest {
    @Test
    fun `comb cost per probe`() {
        val cases = listOf(4.0, 12.0, 22.0).map { d ->
            val s = SyntheticWicket.Scene(distanceM = d, footY = if (d < 8) 640.0 else 430.0, texture = 7.0, seed = 3)
            val (luma, w, h) = SyntheticWicket.sensorLuma(s)
            Triple(s, luma, w to h)
        }
        fun once() {
            for ((s, luma, wh) in cases) {
                val (w, h) = wh
                StumpProfile.locate(
                    luma, w, h, w, 0,
                    centreX = (s.footX + 2) / w, baseY = s.footY / h, topY = (s.footY - s.heightPx) / h,
                    halfSpanFw = s.halfSpanPx * 1.2 / w, tolerance = 0.45, searchHalfSpans = 1.6,
                )
            }
        }
        repeat(20) { once() } // warm the JIT
        val n = 60
        val t0 = System.nanoTime()
        repeat(n) { once() }
        val perProbeUs = (System.nanoTime() - t0) / 1e3 / (n * cases.size)
        println("comb: %.0f µs per probe (laptop JIT)".format(perProbeUs))
    }
}
