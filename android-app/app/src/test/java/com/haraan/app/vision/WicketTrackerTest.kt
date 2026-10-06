package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The state machine, exercised through every transition it has.
 *
 * WHY THIS IS THE LONGEST TEST FILE IN THE PACKAGE. A tracker is a thing that decides when
 * to believe something, and the expensive failures are all in the transitions rather than
 * in the arithmetic: a lock promoted on one lucky frame, a lock that will not let go of a
 * fence, a lock that survives the phone being pointed at the car park. None of those are
 * visible in a screenshot and all of them are reachable from here.
 */
class WicketTrackerTest {

    private val aspect = 16f / 9f

    /** A wicket in a picture, centred at [x] with [span] across and standing on [baseY]. */
    private fun stumps(
        x: Float = 0.5f,
        baseY: Float = 0.7f,
        span: Float = 0.10f,
        height: Float = 0.12f,
        score: Float = 0.8f,
    ): WicketSighting.Stumps {
        fun bar(cx: Float) = StumpCandidate(centreX = cx, baseY = baseY, topY = baseY - height)
        return WicketSighting.Stumps(
            StumpSet(
                left = bar(x - span / 2f),
                middle = bar(x),
                right = bar(x + span / 2f),
                score = score,
            ),
        )
    }

    private fun stone(x: Float = 0.5f, baseY: Float = 0.7f) = WicketSighting.Stone(
        StoneMark(
            centreX = x,
            baseY = baseY,
            topY = baseY - 0.04f,
            width = 0.05f,
            solidity = 0.9f,
            score = 0.6f,
            creaseDistance = null,
            lookalikes = 1,
        ),
    )

    /** A still camera that KNOWS it is still, as opposed to one that has no idea. */
    private fun steady() = FrameMotion(0f, 0f, 1f, 0f, inliers = 30, total = 32)

    private fun WicketTracker.feed(
        sighting: WicketSighting?,
        frames: Int = 1,
        motion: FrameMotion = steady(),
        startMs: Long = 0L,
        stepMs: Long = 33L,
    ): WicketLock? {
        var last: WicketLock? = null
        repeat(frames) { i ->
            last = onFrame(sighting, motion, aspect, startMs + i * stepMs)
        }
        return last
    }

    // ---- LOST -> TENTATIVE -> CONFIRMED -------------------------------------------------

    @Test
    fun `nothing seen means nothing locked`() {
        val tracker = WicketTracker()
        assertNull(tracker.feed(null, frames = 10))
        assertEquals(WicketTrackState.LOST, tracker.diagnostics().state)
    }

    /**
     * The single most important assertion in this file.
     *
     * One frame of three aligned bars is a bat, a pad and a boot as often as it is a
     * wicket. Before the tracker existed it was drawn identically to a wicket seen for a
     * minute, and everything downstream would have been scaled by it.
     */
    @Test
    fun `one good frame is not a lock`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(stumps())
        assertNotNull(lock)
        assertEquals(WicketTrackState.TENTATIVE, lock!!.state)
        assertFalse("a tentative lock must never be measurable", lock.isMeasurable)
        assertNull(lock.metresPerUnitAcross())
    }

    @Test
    fun `the same wicket seen repeatedly is confirmed`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        assertEquals(WicketTrackState.CONFIRMED, lock!!.state)
        assertTrue(lock.isMeasurable)
        assertEquals(1, tracker.diagnostics().confirmations)
    }

    @Test
    fun `a tentative candidate that vanishes is dropped after a short grace`() {
        val tracker = WicketTracker()
        tracker.feed(stumps())
        val held = tracker.feed(null, frames = WicketTracker.TENTATIVE_MISS_ALLOWANCE, startMs = 33L)
        assertEquals(WicketTrackState.TENTATIVE, held!!.state)
        assertFalse("a tentative lock in its grace is still never measurable", held.isMeasurable)

        val after = tracker.onFrame(null, steady(), aspect, 33L * (WicketTracker.TENTATIVE_MISS_ALLOWANCE + 1))
        assertNull(after)
        assertEquals(WicketTrackState.LOST, tracker.diagnostics().state)
    }

    /**
     * Why the grace exists: a far wicket filmed from behind the arm is found on some frames
     * and not the next. Seen on alternate frames, it must still lock.
     */
    @Test
    fun `a wicket seen on alternate frames still confirms`() {
        val tracker = WicketTracker()
        tracker.onFrame(stumps(), steady(), aspect, 0L)
        tracker.onFrame(null, steady(), aspect, 33L)
        val lock = tracker.onFrame(stumps(), steady(), aspect, 66L)
        assertEquals(WicketTrackState.CONFIRMED, lock!!.state)
    }

    @Test
    fun `the grace is capped in time when frames arrive slowly`() {
        val tracker = WicketTracker()
        tracker.onFrame(stumps(), steady(), aspect, 0L)
        val after = tracker.onFrame(null, steady(), aspect, WicketTracker.TENTATIVE_GRACE_MS + 1)
        assertNull(after)
    }

    // ---- CONFIRMED -> TEMPORARILY_LOST -> REACQUIRE --------------------------------------

    @Test
    fun `a confirmed lock coasts when the wicket is not seen`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)

        val coasting = tracker.onFrame(null, steady(), aspect, 200L)
        assertEquals(WicketTrackState.TEMPORARILY_LOST, coasting!!.state)
        assertEquals(1, coasting.ageFrames)
        assertFalse("a coasting lock must never be measured from", coasting.isMeasurable)
        // And it is still drawable, which is the whole point of coasting.
        assertNotNull(coasting.anchor)
    }

    @Test
    fun `a wicket seen again after coasting re-acquires and then confirms`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        tracker.onFrame(null, steady(), aspect, 200L)
        tracker.onFrame(null, steady(), aspect, 233L)

        val back = tracker.onFrame(stumps(), steady(), aspect, 266L)
        assertEquals(WicketTrackState.REACQUIRE, back!!.state)
        assertEquals(1, tracker.diagnostics().reacquires)

        val settled = tracker.feed(
            stumps(),
            frames = WicketTracker.REACQUIRE_CONFIRM_FRAMES,
            startMs = 300L,
        )
        assertEquals(WicketTrackState.CONFIRMED, settled!!.state)
    }

    @Test
    fun `coasting past the limit gives the lock up`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)

        var last: WicketLock? = null
        for (i in 0..WicketTracker.MAX_COAST_FRAMES) {
            last = tracker.onFrame(null, steady(), aspect, 200L + i * 20L)
        }
        assertNull("this is the failure the twelve-frame hold used to survive", last)
        assertEquals(WicketTrackState.LOST, tracker.diagnostics().state)
        assertEquals(1, tracker.diagnostics().drops)
    }

    @Test
    fun `a long gap in wall time ends the coast even at a few frames`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        tracker.onFrame(null, steady(), aspect, 200L)
        val after = tracker.onFrame(null, steady(), aspect, 200L + WicketTracker.MAX_COAST_MS + 50L)
        assertNull(after)
    }

    // ---- camera motion -------------------------------------------------------------------

    /**
     * The reason [CameraMotion] exists at all.
     *
     * A nudged tripod moves the wicket across the picture by more than the association gate
     * allows. Without the camera's own motion subtracted first, the lock breaks on a breath
     * of wind; with it, the same nudge changes nothing.
     */
    @Test
    fun `a nudged camera does not break a confirmed lock`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(x = 0.5f), frames = WicketTracker.CONFIRM_FRAMES)

        val nudge = FrameMotion(0.06f, 0.01f, 1f, 0f, inliers = 28, total = 30)
        val after = tracker.onFrame(stumps(x = 0.56f), nudge, aspect, 200L)

        assertEquals(WicketTrackState.CONFIRMED, after!!.state)
        assertEquals(0.56, after.base.x, 0.01)
        assertEquals(0, tracker.diagnostics().gateRejections)
    }

    @Test
    fun `the same movement with no motion estimate is refused as a different object`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(x = 0.5f), frames = WicketTracker.CONFIRM_FRAMES)

        // The scene offered nothing to track, so the camera's move is unknown — and an
        // unknown move is not an excuse to accept a wicket a long way from the lock.
        val after = tracker.onFrame(stumps(x = 0.72f), FrameMotion.STILL, aspect, 200L)

        assertEquals(WicketTrackState.TEMPORARILY_LOST, after!!.state)
        assertEquals(1, tracker.diagnostics().gateRejections)
        assertEquals(0.5, after.base.x, 0.01)
    }

    /**
     * And the escape hatch, so a tracker cannot spend a match defending a fence.
     */
    @Test
    fun `sightings that keep agreeing with each other re-seed the lock`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(x = 0.3f), frames = WicketTracker.CONFIRM_FRAMES)

        var last: WicketLock? = null
        repeat(WicketTracker.DISSENT_FRAMES) { i ->
            last = tracker.onFrame(stumps(x = 0.75f), steady(), aspect, 200L + i * 33L)
        }

        assertEquals(WicketTrackState.TENTATIVE, last!!.state)
        assertEquals(0.75, last!!.base.x, 0.01)
    }

    // ---- kinds ---------------------------------------------------------------------------

    @Test
    fun `a stone lock never carries a scale however well tracked`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(stone(), frames = WicketTracker.CONFIRM_FRAMES * 3)
        assertEquals(WicketTrackState.CONFIRMED, lock!!.state)
        assertEquals(WicketKind.STONE, lock.kind)
        assertFalse(lock.isMeasurable)
        assertNull("a stone has no agreed width", lock.metresPerUnitAcross())
    }

    @Test
    fun `a stone where stumps were locked re-seeds rather than blending`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        val after = tracker.onFrame(stone(), steady(), aspect, 200L)
        assertEquals(WicketKind.STONE, after!!.kind)
        assertEquals(WicketTrackState.TENTATIVE, after.state)
    }

    // ---- scale ---------------------------------------------------------------------------

    @Test
    fun `a confirmed stumps lock turns its span into metres`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(stumps(span = 0.10f), frames = WicketTracker.CONFIRM_FRAMES)!!
        val scale = lock.metresPerUnitAcross()!!
        assertEquals(PitchGeometry.STUMP_SET_WIDTH_M / 0.10, scale, 1e-3)
    }

    @Test
    fun `a wicket too small in frame is refused rather than divided by`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(
            stumps(span = WicketTracker.MIN_MEASURABLE_SPAN / 2f, height = 0.02f),
            frames = WicketTracker.CONFIRM_FRAMES,
        )!!
        assertFalse(lock.isMeasurable)
        assertNull(lock.metresPerUnitAcross())
    }

    @Test
    fun `a stump top gives a vertical scale and its absence gives none`() {
        val tracker = WicketTracker()
        val lock = tracker.feed(stumps(span = 0.10f, height = 0.30f), frames = WicketTracker.CONFIRM_FRAMES)!!
        val down = lock.metresPerUnitDown()
        assertNotNull("three bars always know their own tops", down)

        val bare = WicketTracker()
        bare.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), top = null, frameAspect = aspect)
        assertNull("two collinear points cannot fix a vertical scale", bare.lock()!!.metresPerUnitDown())
    }

    // ---- manual lock ---------------------------------------------------------------------

    @Test
    fun `a hand-placed lock is confirmed and measurable immediately`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), Point2(0.5, 0.45), frameAspect = aspect)

        val lock = tracker.lock()!!
        assertEquals(WicketLockSource.MANUAL, lock.source)
        assertEquals(WicketTrackState.CONFIRMED, lock.state)
        assertTrue(lock.isMeasurable)
        assertEquals(PitchGeometry.STUMP_SET_WIDTH_M / 0.10, lock.metresPerUnitAcross()!!, 1e-3)
    }

    @Test
    fun `taps in either order describe the same wicket`() {
        val forward = WicketTracker().apply {
            lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        }
        val backward = WicketTracker().apply {
            lockManually(Point2(0.55, 0.7), Point2(0.45, 0.7), frameAspect = aspect)
        }
        assertEquals(forward.lock()!!.span, backward.lock()!!.span, 1e-6f)
        assertEquals(forward.lock()!!.base.x, backward.lock()!!.base.x, 1e-9)
    }

    /**
     * The operator said where the wicket is. The detector does not get to overrule them.
     */
    @Test
    fun `a hand-placed lock survives the detector finding nothing for a long time`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)

        var last: WicketLock? = null
        repeat(200) { i -> last = tracker.onFrame(null, steady(), aspect, i * 33L) }

        assertEquals(WicketTrackState.CONFIRMED, last!!.state)
        assertEquals(WicketLockSource.MANUAL, last!!.source)
        assertTrue(last!!.isMeasurable)
    }

    @Test
    fun `a hand-placed lock ignores a detection far from it`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        val after = tracker.onFrame(stumps(x = 0.15f), steady(), aspect, 33L)

        assertEquals(0.5, after!!.base.x, 1e-6)
        assertEquals(1, tracker.diagnostics().gateRejections)
    }

    @Test
    fun `a hand-placed lock is nudged by a detection close to it but not moved onto it`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        repeat(3) { i -> tracker.onFrame(stumps(x = 0.52f), steady(), aspect, i * 33L) }

        val x = tracker.lock()!!.base.x
        assertTrue("it should have moved towards the detection", x > 0.5)
        assertTrue("but nowhere near all the way", x < 0.51)
    }

    @Test
    fun `losing track of the camera degrades a hand-placed lock rather than hiding it`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)

        var last: WicketLock? = null
        repeat(WicketTracker.MAX_BLIND_FRAMES + 2) { i ->
            last = tracker.onFrame(null, FrameMotion.STILL, aspect, i * 33L)
        }

        assertEquals(WicketTrackState.TEMPORARILY_LOST, last!!.state)
        assertFalse("a lock that is only a memory must not be measured from", last!!.isMeasurable)
        assertNotNull("and it is still drawn, so the operator can see it is stale", last!!.anchor)
    }

    /**
     * The contradiction this caught on a real phone: the panel read CONFIRMED in green
     * above a confidence of 0.15 and a red bar, both computed correctly.
     */
    @Test
    fun `a hand-placed lock stays confident while the detector never finds it`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        repeat(60) { i -> tracker.onFrame(null, steady(), aspect, i * 33L) }

        val diagnostics = tracker.diagnostics()
        assertEquals(WicketTrackState.CONFIRMED, diagnostics.state)
        assertTrue(
            "a hand-placed lock the camera is tracking must stay confident, was ${diagnostics.confidence}",
            diagnostics.confidence > 0.9f,
        )
        assertEquals(0, diagnostics.blindFrames)
    }

    @Test
    fun `a hand-placed lock loses confidence only when the camera is lost`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        repeat(WicketTracker.MAX_BLIND_FRAMES) { i ->
            tracker.onFrame(null, FrameMotion.STILL, aspect, i * 33L)
        }

        val diagnostics = tracker.diagnostics()
        assertEquals(WicketTracker.MAX_BLIND_FRAMES, diagnostics.blindFrames)
        assertTrue(
            "blind frames must pull it down, was ${diagnostics.confidence}",
            diagnostics.confidence < 0.5f,
        )
    }

    @Test
    fun `clearing a hand-placed lock gives the wicket back to the detector`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), frameAspect = aspect)
        tracker.clearManualLock()

        assertNull(tracker.lock())
        val lock = tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        assertEquals(WicketLockSource.DETECTED, lock!!.source)
    }

    // ---- rotation ------------------------------------------------------------------------

    @Test
    fun `an anchor maps exactly through a quarter turn`() {
        val anchor = WicketAnchor(Point2(0.2, 0.8), Point2(0.4, 0.8), Point2(0.3, 0.5))
        val turned = anchor.rotated(1)

        // A quarter turn clockwise sends (x, y) to (1 - y, x).
        assertEquals(0.2, turned.baseLeft.x, 1e-9)
        assertEquals(0.2, turned.baseLeft.y, 1e-9)
        assertEquals(0.2, turned.baseRight.x, 1e-9)
        assertEquals(0.4, turned.baseRight.y, 1e-9)

        // And four of them come back to where they started.
        val round = anchor.rotated(4)
        assertEquals(anchor.baseLeft.x, round.baseLeft.x, 1e-9)
        assertEquals(anchor.baseLeft.y, round.baseLeft.y, 1e-9)
    }

    /**
     * The requirement in one test: a hand-placed lock must survive the phone being turned.
     */
    @Test
    fun `a hand-placed lock survives a quarter turn and stays measurable`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.45, 0.7), Point2(0.55, 0.7), Point2(0.5, 0.45), frameAspect = aspect)
        val before = tracker.lock()!!.metresPerUnitAcross()!!

        tracker.onRotation(1, 1f / aspect)
        val after = tracker.lock()!!

        assertEquals(WicketTrackState.CONFIRMED, after.state)
        assertEquals(WicketLockSource.MANUAL, after.source)
        assertTrue(after.isMeasurable)
        /*
         * THE SCALE MUST CHANGE, AND BY EXACTLY THIS MUCH.
         *
         * Its unit is metres per FRAME WIDTH, and a quarter turn swaps which side of the
         * picture the frame width is. The wicket has not moved and covers the same pixels,
         * so the same 0.2286 m is now a larger fraction of a narrower frame - by precisely
         * the old aspect ratio. A scale that came out UNCHANGED here would mean the anchor
         * had been carried through the turn in the wrong space, which is the failure the
         * whole mapping exists to avoid.
         */
        assertEquals(before / aspect, after.metresPerUnitAcross()!!, 1e-6)
        assertEquals(1, tracker.diagnostics().rotations)
    }

    @Test
    fun `a detected lock survives a turn but has to be confirmed again`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        tracker.onRotation(1, 1f / aspect)

        val after = tracker.lock()!!
        assertEquals(WicketTrackState.REACQUIRE, after.state)
        assertNotNull(after.anchor)
    }

    @Test
    fun `a turn with nothing locked changes nothing but the counter`() {
        val tracker = WicketTracker()
        tracker.onRotation(1, 1f / aspect)
        assertNull(tracker.lock())
        assertEquals(1, tracker.diagnostics().rotations)
    }

    // ---- carry ---------------------------------------------------------------------------

    /**
     * A frame the detector was never asked about is not a frame it failed on.
     */
    @Test
    fun `carrying a lock through a delivery does not age it towards lost`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)

        var last: WicketLock? = null
        repeat(WicketTracker.MAX_COAST_FRAMES * 4) { last = tracker.carry(steady(), aspect) }

        assertEquals(WicketTrackState.CONFIRMED, last!!.state)
        assertTrue(last!!.isMeasurable)
    }

    @Test
    fun `a carried lock still moves with the camera`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(x = 0.5f), frames = WicketTracker.CONFIRM_FRAMES)
        tracker.carry(FrameMotion(0.04f, 0f, 1f, 0f, inliers = 30, total = 30), aspect)
        assertEquals(0.54, tracker.lock()!!.base.x, 1e-3)
    }

    // ---- diagnostics ---------------------------------------------------------------------

    @Test
    fun `the frame rate is measured from the timestamps it was given`() {
        val tracker = WicketTracker()
        repeat(20) { i -> tracker.onFrame(stumps(), steady(), aspect, i * 40L) }
        assertEquals(25f, tracker.diagnostics().framesPerSecond, 0.5f)
    }

    @Test
    fun `a clock that goes backwards does not produce a nonsense frame rate`() {
        val tracker = WicketTracker()
        repeat(10) { i -> tracker.onFrame(stumps(), steady(), aspect, 10_000L + i * 33L) }
        repeat(10) { i -> tracker.onFrame(stumps(), steady(), aspect, i * 33L) }
        val fps = tracker.diagnostics().framesPerSecond
        assertTrue("fps should be plausible, was $fps", fps in 1f..200f)
    }

    @Test
    fun `confidence is highest when confirmed and falls while coasting`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES * 2)
        val confirmed = tracker.diagnostics().confidence

        tracker.onFrame(null, steady(), aspect, 500L)
        val coasting = tracker.diagnostics().confidence

        tracker.onFrame(null, steady(), aspect, 533L)
        val older = tracker.diagnostics().confidence

        assertTrue(confirmed > coasting)
        assertTrue(coasting > older)
    }

    @Test
    fun `reset clears the lock and every counter`() {
        val tracker = WicketTracker()
        tracker.feed(stumps(), frames = WicketTracker.CONFIRM_FRAMES)
        tracker.onRotation(1, aspect)
        tracker.reset()

        val diagnostics = tracker.diagnostics()
        assertNull(tracker.lock())
        assertEquals(WicketTrackState.LOST, diagnostics.state)
        assertEquals(0, diagnostics.framesSeen)
        assertEquals(0, diagnostics.confirmations)
        assertEquals(0, diagnostics.rotations)
        assertEquals(0, diagnostics.reacquires)
    }
}
