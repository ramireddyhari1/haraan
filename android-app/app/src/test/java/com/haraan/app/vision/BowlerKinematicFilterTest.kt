package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class BowlerKinematicFilterTest {

    private fun mockSkeleton(centerX: Float, centerY: Float): BowlerSkeleton {
        val kp = PoseKeypoint(centerX, centerY)
        return BowlerSkeleton(
            nose = kp,
            leftShoulder = PoseKeypoint(centerX - 0.05f, centerY - 0.2f),
            rightShoulder = PoseKeypoint(centerX + 0.05f, centerY - 0.2f),
            leftElbow = PoseKeypoint(centerX - 0.1f, centerY - 0.1f),
            rightElbow = PoseKeypoint(centerX + 0.1f, centerY - 0.1f),
            leftWrist = PoseKeypoint(centerX - 0.1f, centerY),
            rightWrist = PoseKeypoint(centerX + 0.1f, centerY),
            leftHip = PoseKeypoint(centerX - 0.04f, centerY),
            rightHip = PoseKeypoint(centerX + 0.04f, centerY),
            leftKnee = PoseKeypoint(centerX - 0.04f, centerY + 0.2f),
            rightKnee = PoseKeypoint(centerX + 0.04f, centerY + 0.2f),
            leftAnkle = PoseKeypoint(centerX - 0.04f, centerY + 0.4f),
            rightAnkle = PoseKeypoint(centerX + 0.04f, centerY + 0.4f),
        )
    }

    @Test
    fun `fast bowler running forward is identified as BOWLER with locked status`() {
        val filter = BowlerKinematicFilter()
        var lastCandidates: List<PersonKinematicCandidate> = emptyList()

        // 10 frames of forward approach (moving down the camera frame +Y)
        repeat(10) { i ->
            val y = 0.30f + (i * 0.04f) // progressing forward towards crease
            val skeletons = mapOf(1 to mockSkeleton(0.50f, y))
            lastCandidates = filter.processFrame(skeletons, timestampMs = 1000L + (i * 40L))
        }

        assertEquals(1, lastCandidates.size)
        val bowler = lastCandidates[0]
        assertEquals(1, bowler.candidateId)
        assertEquals(PersonRole.BOWLER, bowler.role)
        assertTrue("Bowler confidence must be high", bowler.bowlerConfidence >= 0.60f)
        assertTrue(bowler.isLockedBowler)
        assertTrue("Must have positive forward velocity", bowler.forwardVelocityKmh > 10f)
    }

    @Test
    fun `spin bowler with moderate walking approach is identified as BOWLER`() {
        val filter = BowlerKinematicFilter()
        var lastCandidates: List<PersonKinematicCandidate> = emptyList()

        // Moderate pace spin approach (moving steadily forward)
        repeat(12) { i ->
            val y = 0.35f + (i * 0.015f)
            val skeletons = mapOf(1 to mockSkeleton(0.50f, y))
            lastCandidates = filter.processFrame(skeletons, timestampMs = 1000L + (i * 40L))
        }

        val spinner = lastCandidates[0]
        assertEquals(PersonRole.BOWLER, spinner.role)
        assertTrue(spinner.bowlerConfidence >= 0.55f)
        assertTrue(spinner.isLockedBowler)
    }

    @Test
    fun `stationary person behind stumps is identified as UMPIRE with reason`() {
        val filter = BowlerKinematicFilter()
        var lastCandidates: List<PersonKinematicCandidate> = emptyList()

        // Stationary over 8 frames with negligible movement
        repeat(8) { i ->
            val skeletons = mapOf(2 to mockSkeleton(0.50f, 0.40f))
            lastCandidates = filter.processFrame(skeletons, timestampMs = 1000L + (i * 40L))
        }

        assertEquals(1, lastCandidates.size)
        val umpire = lastCandidates[0]
        assertEquals(PersonRole.UMPIRE, umpire.role)
        assertFalse(umpire.isLockedBowler)
        assertTrue(umpire.velocityKmh < 4.5f)
        assertTrue(umpire.reason.contains("LOW_PERSISTENT_VELOCITY"))
    }

    @Test
    fun `lateral runner across crease is classified as NON_STRIKER`() {
        val filter = BowlerKinematicFilter()
        var lastCandidates: List<PersonKinematicCandidate> = emptyList()

        // Moving purely horizontally across X axis (lateral movement)
        repeat(8) { i ->
            val x = 0.30f + (i * 0.04f)
            val skeletons = mapOf(3 to mockSkeleton(x, 0.60f))
            lastCandidates = filter.processFrame(skeletons, timestampMs = 1000L + (i * 40L))
        }

        val nonStriker = lastCandidates[0]
        assertEquals(PersonRole.NON_STRIKER, nonStriker.role)
        assertFalse(nonStriker.isLockedBowler)
        assertTrue(nonStriker.lateralVelocityKmh > nonStriker.forwardVelocityKmh)
        assertTrue(nonStriker.reason.contains("LATERAL_MOTION"))
    }

    @Test
    fun `multi-person frame correctly separates approaching bowler from standing umpire`() {
        val filter = BowlerKinematicFilter()
        var lastCandidates: List<PersonKinematicCandidate> = emptyList()

        repeat(10) { i ->
            val bowlerY = 0.30f + (i * 0.035f)
            val skeletons = mapOf(
                10 to mockSkeleton(0.50f, bowlerY),  // Bowler running forward
                20 to mockSkeleton(0.48f, 0.25f),   // Standing umpire in background
            )
            lastCandidates = filter.processFrame(skeletons, timestampMs = 1000L + (i * 40L))
        }

        assertEquals(2, lastCandidates.size)
        val bowler = lastCandidates.first { it.candidateId == 10 }
        val umpire = lastCandidates.first { it.candidateId == 20 }

        assertEquals(PersonRole.BOWLER, bowler.role)
        assertTrue(bowler.isLockedBowler)

        assertEquals(PersonRole.UMPIRE, umpire.role)
        assertFalse(umpire.isLockedBowler)
    }
}
