package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Unit tests for [OpenCvBallTracker] lifecycle, track quality evaluation,
 * and diagnostics accounting.
 */
class OpenCvBallTrackerTest {

    @Test
    fun `tracker begins in clean initial state`() {
        val tracker = OpenCvBallTracker()
        assertTrue(tracker.track().isEmpty())
        assertEquals(TrackQuality.UNCERTAIN, tracker.quality())

        val diag = tracker.diagnostics()
        assertEquals(0, diag.framesSeen)
        assertEquals(0, diag.framesWithCandidate)
        assertEquals(0, diag.rejectedGlobalMotion)
        assertEquals(0, diag.rejectedSize)
        assertEquals(0, diag.rejectedShape)
        assertEquals(0, diag.rejectedTrajectory)
        assertEquals(0, diag.rejectedStationary)
        assertEquals(0, diag.rejectedCluster)
        assertEquals(TrackingState.LOST.name, diag.trackingState)
    }

    @Test
    fun `quality requires sufficient consistent points`() {
        val tracker = OpenCvBallTracker()
        assertEquals(TrackQuality.UNCERTAIN, tracker.quality())
    }

    @Test
    fun `reset clears tracking state and diagnostics`() {
        val tracker = OpenCvBallTracker()
        tracker.reset()

        val diag = tracker.diagnostics()
        assertEquals(0, diag.framesSeen)
        assertEquals(0, diag.rejectedStationary)
        assertEquals(0, diag.rejectedCluster)
        assertEquals(TrackingState.LOST.name, diag.trackingState)
        assertTrue(tracker.track().isEmpty())
    }

    @Test
    fun `public constants match tracking and geometry contracts`() {
        assertEquals(400L, OpenCvBallTracker.TRACK_GAP_LIMIT_MS)
        assertEquals(0.30f, OpenCvBallTracker.MAX_STEP_PER_FRAME, 1e-4f)
        assertEquals(0.45, OpenCvBallTracker.MIN_CIRCULARITY, 1e-4)
        assertEquals(3, OpenCvBallTracker.MIN_POINTS_FOR_TRACK)
        assertEquals(3, OpenCvBallTracker.MIN_POINTS_FOR_CONFIRMATION)
        assertTrue(OpenCvBallTracker.MIN_AREA_PX < OpenCvBallTracker.MAX_AREA_PX)
        assertTrue(OpenCvBallTracker.MIN_FLIGHT_SPEED_PER_MS < OpenCvBallTracker.MAX_FLIGHT_SPEED_PER_MS)
    }

    @Test
    fun `release is safe to call and preserves state invariants`() {
        val tracker = OpenCvBallTracker()
        tracker.release()
        tracker.release() // idempotent
        assertEquals(TrackingState.LOST.name, tracker.diagnostics().trackingState)
    }
}
