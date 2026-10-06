package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.random.Random

/**
 * The far wicket, frame by frame, through the real comb fit and the real tracker.
 *
 * What a delivery looks like from behind the bowler's arm, in the order it happens: a
 * phone on a tripod that is never quite still, the far wicket 22 m away and eight pixels
 * across, and the striker standing in front of it for a second and a half while taking
 * guard. The only stand-in is the whole-frame search, which needs OpenCV: its one job here
 * — handing the comb a rough place to start — is played by a guess two pixels and 30% off.
 *
 * The figures this prints are the ones to compare with a real ground's diagnostics panel.
 */
class WicketPipelineSimTest {

    private data class Run(
        val firstConfirmFrame: Int?,
        val readyFramesDuringOcclusion: Int,
        val occlusionFrames: Int,
        val drops: Int,
        val backAfterOcclusionFrames: Int?,
        val scaleErrorPct: Double?,
        /** The lock's span against the truth, whether or not it is allowed to be measured. */
        val spanErrorPct: Double?,
        val measurableAt: Int?,
    )

    private fun run(
        distanceM: Double = 22.0,
        frames: Int = 120,
        occlusion: IntRange = 40 until 85,
        jitterPx: Double = 0.6,
        noise: Double = 2.5,
        seed: Int = 3,
    ): Run {
        val tracker = WicketTracker()
        val rnd = Random(seed)
        var footX = 640.3
        val base = SyntheticWicket.Scene(distanceM = distanceM, noise = noise)
        val aspect = base.width.toFloat() / base.height

        var firstConfirm: Int? = null
        var readyDuring = 0
        var backAfter: Int? = null
        var measurableAt: Int? = null
        var lastLock: WicketLock? = null

        for (i in 0 until frames) {
            // The tripod breathes; camera motion measures it (as OpenCvCameraMotion would).
            val shift = (rnd.nextDouble() * 2 - 1) * jitterPx
            footX += shift
            val motion = FrameMotion((shift / base.width).toFloat(), 0f, 1f, 0f, inliers = 30, total = 32)

            val occluded = i in occlusion
            val scene = base.copy(
                footX = footX,
                seed = seed * 1000 + i,
                // The striker: a pale body 45 cm wide, right in front of the stumps.
                posts = if (occluded) listOf(footX + 2.0 to 0.45) else emptyList(),
            )
            val (luma, w, h) = SyntheticWicket.sensorLuma(scene)

            val focus = tracker.focus()
            val found = if (focus != null) {
                StumpProfile.locateAt(luma, w, h, w, 0, focus)
            } else {
                // The search's rough candidate: a merged blob, off by 2 px and 30% in size.
                StumpProfile.locate(
                    luma, w, h, w, 0,
                    centreX = (footX + 2.0) / w,
                    baseY = scene.footY / h,
                    topY = (scene.footY - scene.heightPx) / h,
                    halfSpanFw = scene.halfSpanPx * 1.3 / w,
                    tolerance = 0.45,
                    searchHalfSpans = 0.9,
                )
            }
            val sightings = listOfNotNull(found?.first?.let { WicketSighting.Stumps(it) })
            val lock = tracker.onFrame(sightings, motion, aspect, i * 33L)
            lastLock = lock

            val ready = lock != null && lock.state != WicketTrackState.TENTATIVE
            if (ready && firstConfirm == null) firstConfirm = i
            if (lock?.isMeasurable == true && measurableAt == null) measurableAt = i
            if (occluded && ready) readyDuring++
            if (i > occlusion.last && backAfter == null && lock?.state != null &&
                lock.ageFrames == 0 && lock.state != WicketTrackState.TENTATIVE
            ) {
                backAfter = i - occlusion.last
            }
        }

        val truthScale = PitchGeometry.STUMP_CENTRES_SPAN_M / (2 * base.halfSpanPx / base.width)
        val scale = lastLock?.metresPerUnitAcross()
        return Run(
            firstConfirmFrame = firstConfirm,
            readyFramesDuringOcclusion = readyDuring,
            occlusionFrames = occlusion.count(),
            drops = tracker.diagnostics().drops,
            backAfterOcclusionFrames = backAfter,
            scaleErrorPct = scale?.let { (it - truthScale) / truthScale * 100.0 },
            spanErrorPct = lastLock?.let { (it.span * base.width / (2 * base.halfSpanPx) - 1.0) * 100.0 },
            measurableAt = measurableAt,
        ).also { println("$distanceM m: $it") }
    }

    @Test
    fun `the far wicket locks fast, survives the striker, and gives a true scale`() {
        val r = run()
        assertNotNull("never locked", r.firstConfirmFrame)
        assertTrue("locked on frame ${r.firstConfirmFrame}", r.firstConfirmFrame!! <= 3)
        assertEquals("Ready through the whole occlusion", r.occlusionFrames, r.readyFramesDuringOcclusion)
        assertEquals("no drops", 0, r.drops)
        assertNotNull("seen again after the striker moves", r.backAfterOcclusionFrames)
        assertTrue("back within ${r.backAfterOcclusionFrames} frames", r.backAfterOcclusionFrames!! <= 2)
        assertNotNull("measurable at 22 m", r.measurableAt)
        assertEquals("scale error %", 0.0, r.scaleErrorPct!!, 3.0)
    }

    @Test
    fun `at 30 m the lock still holds and its span is still true`() {
        val r = run(distanceM = 30.0, frames = 90, occlusion = 30 until 60)
        assertNotNull(r.firstConfirmFrame)
        assertEquals(0, r.drops)
        assertEquals(0.0, r.spanErrorPct!!, 4.0)
        assertNotNull("measurable at 30 m on a sub-pixel lock", r.measurableAt)
        assertEquals(0.0, r.scaleErrorPct!!, 4.0)
    }

    /** Where measurement is refused, as distance grows, and how good it is before then. */
    @Test
    fun `span accuracy by distance`() {
        for (d in listOf(14.0, 18.0, 22.0, 26.0, 30.0, 34.0)) {
            val r = run(distanceM = d, frames = 40, occlusion = 20 until 25, seed = d.toInt())
            assertNotNull("never locked at $d m", r.firstConfirmFrame)
            assertEquals("span at $d m", 0.0, r.spanErrorPct!!, 5.0)
        }
    }

    @Test
    fun `a grainy dusk frame still locks the far wicket`() {
        val r = run(noise = 5.0, frames = 60, occlusion = 30 until 40)
        assertNotNull(r.firstConfirmFrame)
        assertEquals(0, r.drops)
        assertEquals(0.0, r.scaleErrorPct!!, 4.0)
    }
}
