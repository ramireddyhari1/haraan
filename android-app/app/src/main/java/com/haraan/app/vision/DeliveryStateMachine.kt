package com.haraan.app.vision

/**
 * Enterprise Cricket Delivery Lifecycle States.
 */
enum class DeliveryLifecycleState {
    IDLE,
    BOWLER_DETECTED,
    APPROACHING_CREASE,
    DELIVERY_ARMED,
    RELEASE_CANDIDATE,
    RELEASE_CONFIRMED,
    BALL_IN_FLIGHT,
    BALL_PITCHED_BOUNCE,
    STUMP_IMPACT,
    BAT_IMPACT,
    DELIVERY_COMPLETE,
    TEMPORARILY_LOST,
    CANCELLED,
    ERROR,
}

/**
 * Immutable event emitted on delivery lifecycle state changes.
 */
data class DeliveryLifecycleEvent(
    val state: DeliveryLifecycleState,
    val previousState: DeliveryLifecycleState,
    val timestampMs: Long,
    val confidence: Float,
    val source: String,
    val reason: String,
    val bowlerTrackingId: Int?,
    val deliveryIndex: Int,
    val releaseMetrics: BowlerDeliverySighting? = null,
    val impactAssessment: StumpImpactAssessment? = null,
)

/**
 * Central Enterprise Delivery Lifecycle State Machine.
 *
 * Ensures no individual vision detector independently declares a delivery in isolation.
 * Fuses:
 * - Multi-person bowler kinematic approach
 * - Bowler arm elevation & gather stride
 * - Ball in-flight tracking
 * - Pitch bounce point detection
 * - Multi-signal stump impact detection
 *
 * Implements temporal confirmation and abort/cancellation recovery.
 */
class DeliveryStateMachine(
    private val confirmationWindowMs: Long = 120L,
    private val ballInFlightTimeoutMs: Long = 2500L,
) {
    private var currentState: DeliveryLifecycleState = DeliveryLifecycleState.IDLE
    private var deliveryCount: Int = 0
    private var currentBowlerId: Int? = null

    // Temporal latching
    private var candidateTimestampMs: Long = 0L
    private var releaseConfirmedTimestampMs: Long = 0L
    private var cachedReleaseMetrics: BowlerDeliverySighting? = null
    private var missingFrames: Int = 0

    val state: DeliveryLifecycleState get() = currentState
    val deliveriesCompleted: Int get() = deliveryCount

    fun reset() {
        currentState = DeliveryLifecycleState.IDLE
        currentBowlerId = null
        candidateTimestampMs = 0L
        releaseConfirmedTimestampMs = 0L
        cachedReleaseMetrics = null
        missingFrames = 0
    }

    /**
     * Process one integrated frame across all vision sensors.
     */
    fun onFrame(
        bowlerCandidate: PersonKinematicCandidate?,
        bowlerSighting: BowlerDeliverySighting?,
        ballInFlight: Boolean = false,
        ballBounceDetected: Boolean = false,
        stumpImpact: StumpImpactAssessment? = null,
        timestampMs: Long = System.currentTimeMillis(),
    ): DeliveryLifecycleEvent {
        val prevState = currentState
        var transitionReason = ""
        var transitionSource = ""
        var confidence = 0.5f

        // Check bowler occlusion
        if (bowlerCandidate == null && bowlerSighting == null) {
            missingFrames++
            if (missingFrames in 1..8 && currentState in listOf(
                DeliveryLifecycleState.BOWLER_DETECTED,
                DeliveryLifecycleState.APPROACHING_CREASE,
                DeliveryLifecycleState.DELIVERY_ARMED,
            )) {
                // Coast gracefully through temporary occlusion
                return DeliveryLifecycleEvent(
                    state = currentState,
                    previousState = prevState,
                    timestampMs = timestampMs,
                    confidence = 0.6f,
                    source = "TEMPORAL_COAST",
                    reason = "Bowler momentarily occluded ($missingFrames frames)",
                    bowlerTrackingId = currentBowlerId,
                    deliveryIndex = deliveryCount + 1,
                )
            } else if (missingFrames > 15 && currentState !in listOf(
                DeliveryLifecycleState.IDLE,
                DeliveryLifecycleState.BALL_IN_FLIGHT,
                DeliveryLifecycleState.DELIVERY_COMPLETE
            )) {
                // Cancelled delivery
                currentState = DeliveryLifecycleState.CANCELLED
                return DeliveryLifecycleEvent(
                    state = DeliveryLifecycleState.CANCELLED,
                    previousState = prevState,
                    timestampMs = timestampMs,
                    confidence = 0.8f,
                    source = "OCCLUSION_TIMEOUT",
                    reason = "Bowler tracking lost for > 15 frames",
                    bowlerTrackingId = currentBowlerId,
                    deliveryIndex = deliveryCount + 1,
                )
            }
        } else {
            missingFrames = 0
        }

        when (currentState) {
            DeliveryLifecycleState.IDLE,
            DeliveryLifecycleState.CANCELLED,
            DeliveryLifecycleState.DELIVERY_COMPLETE -> {
                if (bowlerCandidate != null && bowlerCandidate.isLockedBowler) {
                    currentBowlerId = bowlerCandidate.candidateId
                    currentState = DeliveryLifecycleState.BOWLER_DETECTED
                    transitionSource = "BOWLER_KINEMATICS"
                    transitionReason = "Bowler identified (ID #${bowlerCandidate.candidateId}, ${bowlerCandidate.velocityKmh.toInt()} km/h)"
                    confidence = bowlerCandidate.bowlerConfidence
                }
            }

            DeliveryLifecycleState.BOWLER_DETECTED -> {
                if (bowlerCandidate != null) {
                    if (bowlerCandidate.forwardVelocityKmh > 4.0f) {
                        currentState = DeliveryLifecycleState.APPROACHING_CREASE
                        transitionSource = "FORWARD_APPROACH"
                        transitionReason = "Approaching crease at ${bowlerCandidate.forwardVelocityKmh.toInt()} km/h"
                        confidence = bowlerCandidate.bowlerConfidence
                    } else if (bowlerCandidate.trackingFrames > 12 && bowlerCandidate.velocityKmh < 3.0f) {
                        // Aborted run-up
                        currentState = DeliveryLifecycleState.IDLE
                        transitionSource = "VELOCITY_DECAY"
                        transitionReason = "Bowler stopped at mark"
                    }
                }
            }

            DeliveryLifecycleState.APPROACHING_CREASE -> {
                val isGatherPhase = bowlerSighting?.phase in listOf(BowlingPhase.GATHER, BowlingPhase.DELIVERY_STRIDE)
                val isElevatingArm = bowlerSighting != null && bowlerSighting.armAngleDegrees > 60f

                if (isGatherPhase || isElevatingArm) {
                    currentState = DeliveryLifecycleState.DELIVERY_ARMED
                    transitionSource = "BOWLER_GATHER_ARM"
                    transitionReason = "Delivery stride armed (arm angle ${bowlerSighting?.armAngleDegrees?.toInt()}°)"
                    confidence = 0.85f
                }
            }

            DeliveryLifecycleState.DELIVERY_ARMED -> {
                val isReleaseEvent = bowlerSighting?.isReleaseMoment == true
                val armPastZenith = bowlerSighting != null && bowlerSighting.armAngleDegrees > 75f

                if (isReleaseEvent || (armPastZenith && ballInFlight)) {
                    currentState = DeliveryLifecycleState.RELEASE_CANDIDATE
                    candidateTimestampMs = timestampMs
                    cachedReleaseMetrics = bowlerSighting
                    transitionSource = "ARM_ZENITH_AND_RELEASE"
                    transitionReason = "Release candidate detected (zenith y=%.2f)".format(bowlerSighting?.releaseZenithY ?: 0f)
                    confidence = 0.88f
                }
            }

            DeliveryLifecycleState.RELEASE_CANDIDATE -> {
                // Confirm release if confirmed within temporal confirmation window
                val dt = timestampMs - candidateTimestampMs
                val followThrough = bowlerSighting?.phase == BowlingPhase.FOLLOW_THROUGH
                val sustainedFlight = ballInFlight || dt in 30..confirmationWindowMs

                if (followThrough || sustainedFlight) {
                    deliveryCount++
                    currentState = DeliveryLifecycleState.RELEASE_CONFIRMED
                    releaseConfirmedTimestampMs = timestampMs
                    transitionSource = "TEMPORAL_CONFIRMATION"
                    transitionReason = "Release confirmed #$deliveryCount (Δt=${dt}ms)"
                    confidence = 0.95f
                } else if (dt > confirmationWindowMs * 3) {
                    // False positive candidate (e.g. bowler pump fake / stopped)
                    currentState = DeliveryLifecycleState.CANCELLED
                    transitionSource = "CONFIRMATION_TIMEOUT"
                    transitionReason = "Pump fake / dead ball - release not completed"
                }
            }

            DeliveryLifecycleState.RELEASE_CONFIRMED -> {
                currentState = DeliveryLifecycleState.BALL_IN_FLIGHT
                transitionSource = "TRAJECTORY_HANDOFF"
                transitionReason = "Ball in flight down pitch"
                confidence = 0.90f
            }

            DeliveryLifecycleState.BALL_IN_FLIGHT -> {
                val dt = timestampMs - releaseConfirmedTimestampMs
                if (stumpImpact != null && stumpImpact.state == StumpImpactState.CONFIRMED_IMPACT) {
                    currentState = DeliveryLifecycleState.STUMP_IMPACT
                    transitionSource = "STUMP_IMPACT_DETECTOR"
                    transitionReason = "Stumps broken: " + stumpImpact.reason
                    confidence = stumpImpact.confidence
                } else if (ballBounceDetected) {
                    currentState = DeliveryLifecycleState.BALL_PITCHED_BOUNCE
                    transitionSource = "PITCH_HOMOGRAPHY_BOUNCE"
                    transitionReason = "Ball pitched on turf"
                    confidence = 0.88f
                } else if (dt > ballInFlightTimeoutMs) {
                    currentState = DeliveryLifecycleState.DELIVERY_COMPLETE
                    transitionSource = "FLIGHT_TIMEOUT"
                    transitionReason = "Delivery concluded (time elapsed)"
                    confidence = 0.92f
                }
            }

            DeliveryLifecycleState.BALL_PITCHED_BOUNCE -> {
                val dt = timestampMs - releaseConfirmedTimestampMs
                if (stumpImpact != null && stumpImpact.state == StumpImpactState.CONFIRMED_IMPACT) {
                    currentState = DeliveryLifecycleState.STUMP_IMPACT
                    transitionSource = "STUMP_IMPACT_DETECTOR"
                    transitionReason = "Stumps broken post-bounce"
                    confidence = stumpImpact.confidence
                } else if (dt > 1200L) {
                    currentState = DeliveryLifecycleState.DELIVERY_COMPLETE
                    transitionSource = "POST_BOUNCE_SETTLED"
                    transitionReason = "Ball played / past batsman"
                    confidence = 0.90f
                }
            }

            DeliveryLifecycleState.STUMP_IMPACT -> {
                val dt = timestampMs - releaseConfirmedTimestampMs
                if (dt > 800L) {
                    currentState = DeliveryLifecycleState.DELIVERY_COMPLETE
                    transitionSource = "DISMISSAL_RECORDED"
                    transitionReason = "Delivery complete (wicket down)"
                    confidence = 0.98f
                }
            }

            else -> Unit
        }

        return DeliveryLifecycleEvent(
            state = currentState,
            previousState = prevState,
            timestampMs = timestampMs,
            confidence = confidence,
            source = transitionSource.ifEmpty { "STEADY_STATE" },
            reason = transitionReason.ifEmpty { "Maintaining ${currentState.name}" },
            bowlerTrackingId = currentBowlerId,
            deliveryIndex = deliveryCount,
            releaseMetrics = cachedReleaseMetrics,
            impactAssessment = stumpImpact,
        )
    }
}
