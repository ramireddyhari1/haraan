package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DeliveryStateMachineTest {

    private fun mockCandidate(id: Int = 1, isLocked: Boolean = true, fwdKmh: Float = 18f) =
        PersonKinematicCandidate(
            candidateId = id,
            skeleton = BowlerSkeleton(
                PoseKeypoint(0.5f, 0.4f),
                PoseKeypoint(0.45f, 0.3f), PoseKeypoint(0.55f, 0.3f),
                PoseKeypoint(0.4f, 0.4f), PoseKeypoint(0.6f, 0.4f),
                PoseKeypoint(0.4f, 0.5f), PoseKeypoint(0.6f, 0.5f),
                PoseKeypoint(0.48f, 0.5f), PoseKeypoint(0.52f, 0.5f),
                PoseKeypoint(0.48f, 0.7f), PoseKeypoint(0.52f, 0.7f),
                PoseKeypoint(0.48f, 0.9f), PoseKeypoint(0.52f, 0.9f),
            ),
            role = PersonRole.BOWLER,
            bowlerConfidence = 0.92f,
            roleConfidence = 0.92f,
            velocityKmh = fwdKmh,
            forwardVelocityKmh = fwdKmh,
            lateralVelocityKmh = 1f,
            directionAngleDeg = 85f,
            accelerationKmhPerSec = 0.5f,
            distanceToCreaseMeters = 3.5f,
            trackingFrames = 10,
            isLockedBowler = isLocked,
            reason = "FORWARD_APPROACH",
        )

    private fun mockSighting(phase: BowlingPhase, isRelease: Boolean = false, armAngle: Float = 45f) =
        BowlerDeliverySighting(
            phase = phase,
            timestampMs = 1000L,
            isReleaseMoment = isRelease,
            bowlingArm = BowlingArm.RIGHT,
            armAngleDegrees = armAngle,
            strideWidthNormalized = 0.3f,
            releaseZenithY = 0.25f,
            elbowAngleDegrees = 175f,
            elbowFlexionDegrees = 5f,
            isActionLegal = true,
            releaseHeightMeters = 2.05f,
            strideLengthMeters = 1.62f,
            handSpeedKmh = 132f,
        )

    @Test
    fun `full delivery lifecycle transitions seamlessly through release and stump impact`() {
        val sm = DeliveryStateMachine()
        assertEquals(DeliveryLifecycleState.IDLE, sm.state)

        // 1. Bowler detected
        var evt = sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.RUN_UP), timestampMs = 1000L)
        assertEquals(DeliveryLifecycleState.BOWLER_DETECTED, evt.state)

        // 2. Approaching crease
        evt = sm.onFrame(mockCandidate(1, fwdKmh = 22f), mockSighting(BowlingPhase.RUN_UP), timestampMs = 1050L)
        assertEquals(DeliveryLifecycleState.APPROACHING_CREASE, evt.state)

        // 3. Delivery Armed (Gather phase)
        evt = sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.GATHER, armAngle = 68f), timestampMs = 1100L)
        assertEquals(DeliveryLifecycleState.DELIVERY_ARMED, evt.state)

        // 4. Release Candidate
        evt = sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.RELEASE, isRelease = true, armAngle = 82f), timestampMs = 1150L)
        assertEquals(DeliveryLifecycleState.RELEASE_CANDIDATE, evt.state)

        // 5. Release Confirmed (follow-through + temporal confirmation)
        evt = sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.FOLLOW_THROUGH), ballInFlight = true, timestampMs = 1200L)
        assertEquals(DeliveryLifecycleState.RELEASE_CONFIRMED, evt.state)
        assertEquals(1, sm.deliveriesCompleted)
        assertTrue(evt.confidence >= 0.90f)

        // 6. Ball in flight
        evt = sm.onFrame(null, null, ballInFlight = true, timestampMs = 1250L)
        assertEquals(DeliveryLifecycleState.BALL_IN_FLIGHT, evt.state)

        // 7. Ball pitched bounce
        evt = sm.onFrame(null, null, ballInFlight = true, ballBounceDetected = true, timestampMs = 1450L)
        assertEquals(DeliveryLifecycleState.BALL_PITCHED_BOUNCE, evt.state)

        // 8. Stump impact
        val impact = StumpImpactAssessment(
            state = StumpImpactState.CONFIRMED_IMPACT,
            confidence = 0.96f,
            timestampMs = 1550L,
            displacementMagnitude = 0.08f,
            tiltDegrees = 22f,
            ballProximityDistance = 0.01f,
            reason = "WICKET_BROKEN",
        )
        evt = sm.onFrame(null, null, stumpImpact = impact, timestampMs = 1550L)
        assertEquals(DeliveryLifecycleState.STUMP_IMPACT, evt.state)

        // 9. Delivery Complete
        evt = sm.onFrame(null, null, timestampMs = 2600L)
        assertEquals(DeliveryLifecycleState.DELIVERY_COMPLETE, evt.state)
    }

    @Test
    fun `momentary bowler occlusion coasts without resetting state`() {
        val sm = DeliveryStateMachine()
        sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.RUN_UP), timestampMs = 1000L)
        sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.RUN_UP), timestampMs = 1050L)
        assertEquals(DeliveryLifecycleState.APPROACHING_CREASE, sm.state)

        // 3 missing frames
        repeat(3) { i ->
            val evt = sm.onFrame(null, null, timestampMs = 1100L + (i * 33L))
            assertEquals(DeliveryLifecycleState.APPROACHING_CREASE, evt.state)
            assertTrue(evt.source.contains("TEMPORAL_COAST"))
        }

        // Bowler re-seen
        val reacquired = sm.onFrame(mockCandidate(1), mockSighting(BowlingPhase.GATHER, armAngle = 70f), timestampMs = 1250L)
        assertEquals(DeliveryLifecycleState.DELIVERY_ARMED, reacquired.state)
    }
}
