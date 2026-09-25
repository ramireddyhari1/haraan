package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class BowlerDeliveryTrackerTest {

    private fun createDummySkeleton(
        hipY: Float,
        wristY: Float,
        armXOffset: Float = 0.1f,
        confidence: Float = 0.9f
    ): BowlerSkeleton {
        val shoulderY = hipY - 0.25f
        val shoulderX = 0.5f

        val activeWrist = PoseKeypoint(shoulderX + armXOffset, wristY, confidence)
        val defaultPt = PoseKeypoint(0.5f, 0.5f, confidence)

        return BowlerSkeleton(
            nose = defaultPt,
            leftShoulder = PoseKeypoint(shoulderX - 0.05f, shoulderY, confidence),
            rightShoulder = PoseKeypoint(shoulderX + 0.05f, shoulderY, confidence),
            leftElbow = defaultPt,
            rightElbow = defaultPt,
            leftWrist = PoseKeypoint(0.4f, 0.9f, confidence), // lower
            rightWrist = activeWrist,                         // active overhead or at hip
            leftHip = PoseKeypoint(shoulderX - 0.05f, hipY, confidence),
            rightHip = PoseKeypoint(shoulderX + 0.05f, hipY, confidence),
            leftKnee = defaultPt,
            rightKnee = defaultPt,
            leftAnkle = PoseKeypoint(0.45f, hipY + 0.35f, confidence),
            rightAnkle = PoseKeypoint(0.55f, hipY + 0.35f, confidence),
        )
    }

    @Test
    fun testDeliveryLifecycleTransitions() {
        val tracker = BowlerDeliveryTracker(
            runUpForwardVelocityThreshold = 0.010f,
            releaseZenithTolerance = 0.020f
        )

        var sighting = tracker.onFrame(null, 0L)
        assertEquals(BowlingPhase.IDLE, sighting.phase)

        // 1. Simulate forward Run-up: hip moves forward (increasing y)
        for (i in 1..6) {
            val skeleton = createDummySkeleton(
                hipY = 0.40f + (i * 0.015f),
                wristY = 0.50f,
                armXOffset = 0.10f
            )
            sighting = tracker.onFrame(skeleton, i * 33L)
        }
        assertEquals(BowlingPhase.RUN_UP, sighting.phase)

        // 2. Gather / Bound: wrist starts raising upward
        val gatherSkeleton = createDummySkeleton(
            hipY = 0.50f,
            wristY = 0.30f,
            armXOffset = 0.15f
        )
        sighting = tracker.onFrame(gatherSkeleton, 250L)
        assertEquals(BowlingPhase.GATHER, sighting.phase)

        // 3. Delivery Stride: bowling arm swings vertical (> 65 degrees)
        val strideSkeleton = createDummySkeleton(
            hipY = 0.52f,
            wristY = 0.05f,
            armXOffset = 0.02f
        )
        sighting = tracker.onFrame(strideSkeleton, 300L)
        assertEquals(BowlingPhase.DELIVERY_STRIDE, sighting.phase)

        // 4. Release: arm passes peak and wrist drops
        val releaseSkeleton = createDummySkeleton(
            hipY = 0.53f,
            wristY = 0.15f, // dropped past 0.05f + tolerance
            armXOffset = 0.10f
        )
        sighting = tracker.onFrame(releaseSkeleton, 333L)
        assertEquals(BowlingPhase.RELEASE, sighting.phase)
        assertTrue("Expected release frame flag to be true", sighting.isReleaseMoment)
        assertEquals(BowlingArm.RIGHT, sighting.bowlingArm)
    }

    @Test
    fun testSelectBowlerFiltersOutsideCorridor() {
        val tracker = BowlerDeliveryTracker()

        val umpireOutside = createDummySkeleton(hipY = 0.5f, wristY = 0.6f).copy(
            leftHip = PoseKeypoint(0.10f, 0.5f, 0.9f),
            rightHip = PoseKeypoint(0.12f, 0.5f, 0.9f),
        )
        val bowlerInside = createDummySkeleton(hipY = 0.5f, wristY = 0.6f).copy(
            leftHip = PoseKeypoint(0.48f, 0.5f, 0.9f),
            rightHip = PoseKeypoint(0.52f, 0.5f, 0.9f),
        )

        val selected = tracker.selectBowler(listOf(umpireOutside, bowlerInside))
        assertNotNull(selected)
        assertEquals(0.48f, selected!!.leftHip.x, 0.01f)
    }
}
