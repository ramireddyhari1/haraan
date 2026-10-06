package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The camera has just opened. No taps, no lock, no memory, no history of any kind.
 *
 * Every test here runs the REAL detector — the OpenCV whole-frame search, then the
 * full-resolution comb — on physically scaled synthetic frames, through a fresh tracker,
 * exactly as the camera screen does on its first frames. What these prove is SIMULATED:
 * the frames are drawn, not filmed. They prove the pipeline's logic, its geometry and its
 * refusals; they do not prove what a real lens, real grass and real light will do.
 */
class StumpStartupTest {

    private val still = FrameMotion(0f, 0f, 1f, 0f, inliers = 30, total = 32)

    private data class Startup(
        val firstSightingFrame: Int?,
        val readyFrame: Int?,
        val lock: WicketLock?,
        val falseReady: Boolean,
        val searchMs: Double,
    )

    /**
     * Frames from a cold start, at 30 fps, through detector and tracker.
     *
     * @param sceneAt the frame to draw at index i
     * @param truthX the middle stump's x in pixels, to tell a right lock from a wrong one;
     *   null when there are no stumps and ANY ready is false
     */
    private fun startUp(frames: Int, truthX: ((Int) -> Double?), sceneAt: (Int) -> SyntheticWicket.Scene): Startup {
        OpenCvJvm.require()
        val detector = OpenCvStumpDetector()
        val tracker = WicketTracker()
        var first: Int? = null
        var ready: Int? = null
        var falseReady = false
        var searchMs = 0.0
        var lock: WicketLock? = null
        for (i in 0 until frames) {
            val scene = sceneAt(i)
            val (luma, w, h) = SyntheticWicket.sensorLuma(scene)
            val t0 = System.nanoTime()
            val sightings = detector.detectCandidates(luma, w, h, w, 0, focus = tracker.focus())
            if (i == 0) searchMs = (System.nanoTime() - t0) / 1e6
            lock = tracker.onFrame(sightings, still, w.toFloat() / h, i * 33L)
            if (sightings.isNotEmpty() && first == null) first = i
            val isReady = lock != null && lock.state != WicketTrackState.TENTATIVE
            if (isReady) {
                val tx = truthX(i)
                val onTruth = tx != null && kotlin.math.abs(lock!!.base.x * w - tx) < scene.halfSpanPx * 1.5
                if (!onTruth) falseReady = true
                if (ready == null) ready = i
            }
        }
        return Startup(first, ready, lock, falseReady, searchMs)
    }

    private fun field(d: Double, seed: Int, footY: Double = if (d < 8) 640.0 else 430.0) = SyntheticWicket.Scene(
        distanceM = d, footY = footY, horizonY = 230.0, texture = 7.0, streaks = 0.04, seed = seed, crease = true,
    )

    // ---- startup --------------------------------------------------------------------------

    @Test
    fun `from a cold start the wicket is found on the first frame and READY on the second, 5 to 40 m`() {
        val rows = StringBuilder("distance  first  ready  span-err%  est-dist  dist-err%  first-search-ms\n")
        for (d in listOf(5.0, 8.0, 12.0, 16.0, 20.0, 24.0, 28.0, 32.0, 36.0, 40.0)) {
            val r = startUp(4, { 640.3 }) { field(d, seed = it + 1) }
            val truthSpan = 2 * field(d, 1).halfSpanPx / 1280.0
            val spanErr = r.lock?.let { (it.span / truthSpan - 1) * 100 }
            // The sim's own lens: what the phone's Camera2 focal length would supply.
            val camera = CameraIntrinsics(field(d, 1).focalPx / 1280.0, "sim")
            val est = r.lock?.let { WicketRange.fromSpan(it, camera) }
            val distErr = est?.let { (it / d - 1) * 100 }
            rows.append("%5.0f m   %4s   %4s   %8s   %7s   %8s   %6.1f\n".format(
                d, r.firstSightingFrame, r.readyFrame, spanErr?.let { "%+.1f".format(it) },
                est?.let { "%.1f".format(it) }, distErr?.let { "%+.1f".format(it) }, r.searchMs,
            ))
            assertEquals("first sighting at $d m", 0, r.firstSightingFrame)
            assertEquals("READY at $d m", 1, r.readyFrame)
            assertTrue("locked on the wrong place at $d m", !r.falseReady)
            if (d <= 32.0) assertEquals("span at $d m", 0.0, spanErr!!, 5.0)
        }
        println(rows)
    }

    @Test
    fun `a phone rolled 5 degrees still starts`() {
        val r = startUp(4, { 640.3 }) { field(20.0, it + 1).copy(rollDeg = 5.0) }
        assertEquals(1, r.readyFrame)
        assertTrue(!r.falseReady)
    }

    @Test
    fun `portrait - a sensor frame turned 90 degrees - starts the same`() {
        OpenCvJvm.require()
        val detector = OpenCvStumpDetector()
        val tracker = WicketTracker()
        var ready: Int? = null
        for (i in 0 until 4) {
            val scene = SyntheticWicket.Scene(
                width = 720, height = 1280, hfovDeg = 43.0, footX = 360.4, footY = 760.0,
                distanceM = 20.0, horizonY = 420.0, texture = 7.0, seed = i + 1,
            )
            val (luma, w, h) = SyntheticWicket.sensorLuma(scene, rotationDegrees = 90)
            val sightings = detector.detectCandidates(luma, w, h, w, 90, focus = tracker.focus())
            val lock = tracker.onFrame(sightings, still, 720f / 1280f, i * 33L)
            if (lock != null && lock.state != WicketTrackState.TENTATIVE && ready == null) {
                ready = i
                assertEquals(360.4 / 720.0, lock.base.x, 1.5 / 720.0)
            }
        }
        assertEquals(1, ready)
    }

    @Test
    fun `stumps dark against a bright sightscreen are found by the second polarity`() {
        val r = startUp(6, { 640.3 }) {
            field(18.0, it + 1).copy(ground = 200.0, stump = 120.0, texture = 4.0, streaks = 0.0)
        }
        assertNotNull("never found", r.readyFrame)
        assertTrue("READY on frame ${r.readyFrame}", r.readyFrame!! <= 3)
        assertTrue(!r.falseReady)
    }

    // ---- merged stumps ----------------------------------------------------------------------

    @Test
    fun `merged far stumps are one blob to the contours and three stumps to the comb`() {
        OpenCvJvm.require()
        for (d in listOf(24.0, 30.0)) {
            val detector = OpenCvStumpDetector()
            val scene = field(d, 3)
            val (luma, w, h) = SyntheticWicket.sensorLuma(scene)
            val found = detector.detectCandidates(luma, w, h, w, 0)
            val report = detector.report()
            assertTrue(
                "at $d m the contour pass should NOT resolve three bars on its own (bars=${report.barsFound})",
                !SyntheticWicket.oldPipelineResolvesThree(scene),
            )
            val set = (found.firstOrNull() as? WicketSighting.Stumps)?.set
            assertNotNull("merged stumps not resolved at $d m", set)
            assertTrue("the comb, not a contour triple", set!!.subPixel)
            assertEquals(StumpMethod.MERGED_COMB, set.method)
            assertEquals(2 * scene.halfSpanPx, set.spanX * w.toDouble(), 2 * scene.halfSpanPx * 0.05)
        }
    }

    // ---- occlusion --------------------------------------------------------------------------

    @Test
    fun `opened while the striker stands in front - found the moment the stumps are clear`() {
        val clearFrom = 15
        val r = startUp(20, { 640.3 }) { i ->
            val s = field(20.0, i + 1)
            if (i < clearFrom) s.copy(figures = listOf(SyntheticWicket.Figure(642.0, 431.0, 0.55, 1.7, 210.0))) else s
        }
        assertTrue("no lock while hidden", r.readyFrame == null || r.readyFrame!! >= clearFrom)
        assertEquals(clearFrom + 1, r.readyFrame)
        assertTrue(!r.falseReady)
    }

    @Test
    fun `a striker walking across a confirmed lock does not break READY`() {
        val r = startUp(40, { 640.3 }) { i ->
            val s = field(20.0, i + 1)
            if (i in 10 until 34) s.copy(figures = listOf(SyntheticWicket.Figure(600.0 + i * 2.0, 431.0, 0.55, 1.7, 210.0))) else s
        }
        assertEquals(1, r.readyFrame)
        assertNotNull(r.lock)
        assertTrue("READY must hold through the occlusion: ${r.lock!!.state}", r.lock!!.state != WicketTrackState.TENTATIVE)
        assertTrue(!r.falseReady)
    }

    // ---- false positives ----------------------------------------------------------------------

    @Test
    fun `bare streaky grass never reaches READY`() {
        val r = startUp(45, { null }) { field(20.0, it + 1).copy(drawStumps = false, streaks = 0.12, texture = 10.0) }
        assertNull("READY on frame ${r.readyFrame} with no wicket in frame", r.readyFrame)
    }

    @Test
    fun `a paling fence at stump spacing never reaches READY`() {
        for (d in listOf(8.0, 20.0)) {
            val base = field(d, 1).copy(drawStumps = false)
            val posts = (-6..6).map { base.footX + it * base.halfSpanPx to PitchGeometry.STUMP_DIAMETER_M }
            val r = startUp(30, { null }) { field(d, it + 1).copy(drawStumps = false, posts = posts) }
            assertNull("fence READY at $d m on frame ${r.readyFrame}", r.readyFrame)
        }
    }

    @Test
    fun `a lone batter and a pair of poles never reach READY`() {
        val r = startUp(30, { null }) {
            field(15.0, it + 1).copy(
                drawStumps = false,
                figures = listOf(
                    SyntheticWicket.Figure(500.0, 470.0, 0.5, 1.75, 215.0),
                    SyntheticWicket.Figure(900.0, 420.0, 0.06, 2.4, 190.0),
                    SyntheticWicket.Figure(1000.0, 420.0, 0.06, 2.4, 190.0),
                ),
            )
        }
        assertNull("READY on frame ${r.readyFrame}", r.readyFrame)
    }

    @Test
    fun `beside a fence the real wicket is still the one locked`() {
        val base = field(14.0, 1)
        val fence = (0..8).map { 250.0 + it * base.halfSpanPx * 1.1 to PitchGeometry.STUMP_DIAMETER_M }
        val r = startUp(10, { 640.3 }) { field(14.0, it + 1).copy(posts = fence) }
        assertNotNull(r.readyFrame)
        assertTrue("locked the fence", !r.falseReady)
    }
}
