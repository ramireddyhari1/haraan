package com.haraan.app.vision

import org.junit.Test

/**
 * Real frames from a real phone, through the real detector. Exploratory readout: what the
 * search sees, in brightness and in colour, on every dumped frame under test/resources/frames.
 */
class RealFrameReplayTest {
    @Test
    fun `replay dumped frames`() {
        val frames = HyuvFrame.all()
        if (frames.isEmpty()) {
            println("no frames under src/test/resources/frames")
            return
        }
        OpenCvJvm.require()
        for ((name, f) in frames) {
            for ((label, plane) in listOf("Y" to f.y, "U" to f.uFull(), "V" to f.vFull())) {
                val stride = if (label == "Y") f.yRowStride else f.width
                val det = OpenCvStumpDetector()
                val t0 = System.nanoTime()
                val found = det.detectCandidates(plane, f.width, f.height, stride, f.rotation)
                val ms = (System.nanoTime() - t0) / 1e6
                val r = det.report()
                println(
                    "$name $label ${f.width}x${f.height}@${f.rotation} ms=%.0f n=${found.size} ".format(ms) +
                        found.joinToString { s ->
                            val set = (s as? WicketSighting.Stumps)?.set
                            "x=%.3f base=%.3f top=%s span=%s %s".format(
                                s.base.x, s.base.y, set?.middle?.topY?.let { "%.3f".format(it) },
                                set?.spanX?.let { "%.4f".format(it) }, set?.method ?: "stone",
                            )
                        } +
                        " | bars=${r.barsFound} probes=${r.probes} fits=${r.combFits} comb=${r.combVerdict} rej=${r.lastRejection}",
                )
            }
        }
    }

    /**
     * REAL-WORLD REGRESSION: the backyard frame from the Realme RMX3933, 2026-10-09.
     * Yellow plastic stumps, pink wall, black-and-white floor tiles, portrait. Brightness
     * alone found nothing (or half the stumps); colour finds all three, full height, dead
     * centre. Five cold frames of it, through the colour-aware detector and a fresh tracker,
     * must give READY on the stumps and nowhere else.
     */
    @Test
    fun `backyard yellow stumps on a pink wall are found full height and locked`() {
        val file = java.io.File("src/test/resources/frames/frame-1791513972655.hyuv")
        org.junit.Assume.assumeTrue(file.exists())
        OpenCvJvm.require()
        val f = HyuvFrame.read(file)
        val det = ColourStumpDetector()
        val tracker = WicketTracker()
        val aspect = f.height.toFloat() / f.width // portrait: rotated 90
        var lock: WicketLock? = null
        for (i in 0 until 5) {
            val found = det.detectCandidates(
                f.y, f.width, f.height, f.yRowStride, f.rotation,
                f.u, f.uvRowStride, f.uvPixelStride, focus = tracker.focus(),
            )
            lock = tracker.onFrame(found, FrameMotion(0f, 0f, 1f, 0f, 30, 32), aspect, i * 33L)
            println("frame $i channel=${det.lastChannel} n=${found.size} state=${lock?.state} " +
                "x=${lock?.base?.x} top=${lock?.anchor?.top?.y} base=${lock?.base?.y}")
        }
        org.junit.Assert.assertNotNull(lock)
        org.junit.Assert.assertEquals(WicketTrackState.CONFIRMED, lock!!.state)
        // Where the stumps are in that frame (measured from the U plane render).
        org.junit.Assert.assertEquals(0.501, lock.base.x, 0.01)
        org.junit.Assert.assertEquals(0.576, lock.base.y, 0.015)
        // FULL height: the top must be near the stump tops, not halfway down.
        val top = lock.anchor.top!!.y
        org.junit.Assert.assertTrue("top at $top — half a wicket?", top < 0.49)
    }
}
