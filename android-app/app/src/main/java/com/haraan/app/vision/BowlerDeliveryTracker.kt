package com.haraan.app.vision

import kotlin.math.atan2
import kotlin.math.hypot

/**
 * State machine that tracks the bowler's approach and identifies the exact release frame.
 *
 * Designed to run per-frame with O(1) complexity on second-screen companion devices.
 * Emits [BowlerDeliverySighting] which gates [CricketVisionEngine] so high-frequency
 * ball tracking runs only during the delivery window (~1.2s).
 */
class BowlerDeliveryTracker(
    private val minConfidence: Float = 0.35f,
    private val runUpForwardVelocityThreshold: Float = 0.015f,
    private val releaseZenithTolerance: Float = 0.025f,
) {

    private data class StoredFrame(
        val timestampMs: Long,
        val hipY: Float,
        val wristY: Float,
        val armAngle: Float,
        val stride: Float,
    )

    private var currentPhase: BowlingPhase = BowlingPhase.IDLE
    private var detectedArm: BowlingArm = BowlingArm.RIGHT
    private val history = mutableListOf<StoredFrame>()
    private val maxHistoryFrames = 30

    // Delivery tracking state
    private var minWristY = 1.0f // 0.0 is top of image, 1.0 is bottom
    private var previousArmAngle = 0.0f
    private var releaseDispatched = false
    private var minElbowDuringDelivery = 180f
    private var recordedFlexionDegrees = 0f
    private var isDeliveryLegal = true

    /**
     * Clear state for a fresh delivery.
     */
    fun reset() {
        currentPhase = BowlingPhase.IDLE
        history.clear()
        minWristY = 1.0f
        previousArmAngle = 0.0f
        releaseDispatched = false
        minElbowDuringDelivery = 180f
        recordedFlexionDegrees = 0f
        isDeliveryLegal = true
    }

    /**
     * Process one skeleton frame.
     *
     * @param bowler detected skeleton for the bowler, or null if player momentarily occluded.
     * @param timestampMs monotonic frame timestamp in milliseconds.
     */
    fun onFrame(bowler: BowlerSkeleton?, timestampMs: Long): BowlerDeliverySighting {
        if (bowler == null) {
            return BowlerDeliverySighting(
                phase = currentPhase,
                timestampMs = timestampMs,
                isReleaseMoment = false,
                bowlingArm = detectedArm,
                armAngleDegrees = previousArmAngle,
                strideWidthNormalized = 0f,
                releaseZenithY = minWristY,
            )
        }

        // 1. Calculate mid-hip position (stable torso anchor)
        val midHipY = (bowler.leftHip.y + bowler.rightHip.y) / 2.0f

        // 2. Identify active overhead arm (wrist that ascends highest / minimum y)
        val rwY = if (bowler.rightWrist.confidence >= minConfidence) bowler.rightWrist.y else 1.0f
        val lwY = if (bowler.leftWrist.confidence >= minConfidence) bowler.leftWrist.y else 1.0f

        val activeWrist: PoseKeypoint
        val activeShoulder: PoseKeypoint
        if (rwY < lwY) {
            activeWrist = bowler.rightWrist
            activeShoulder = bowler.rightShoulder
            detectedArm = BowlingArm.RIGHT
        } else {
            activeWrist = bowler.leftWrist
            activeShoulder = bowler.leftShoulder
            detectedArm = BowlingArm.LEFT
        }

        // 3. Compute arm angle relative to horizontal shoulder axis
        // dx: positive when pointing away from torso
        // dy: inverted so upward pointing wrist gives positive dy
        val dx = activeWrist.x - activeShoulder.x
        val dy = activeShoulder.y - activeWrist.y
        val angleRad = atan2(dy.toDouble(), dx.toDouble())
        val angleDeg = Math.toDegrees(angleRad).toFloat()

        // 4. Stride distance between ankles
        val stride = hypot(
            (bowler.leftAnkle.x - bowler.rightAnkle.x).toDouble(),
            (bowler.leftAnkle.y - bowler.rightAnkle.y).toDouble()
        ).toFloat()

        val currentFrame = StoredFrame(
            timestampMs = timestampMs,
            hipY = midHipY,
            wristY = activeWrist.y,
            armAngle = angleDeg,
            stride = stride,
        )

        history.add(currentFrame)
        if (history.size > maxHistoryFrames) {
            history.removeAt(0)
        }

        var isRelease = false

        // 5. Evaluate delivery state transitions
        when (currentPhase) {
            BowlingPhase.IDLE -> {
                // Bowler starts accelerating forward
                if (history.size >= 5) {
                    val initialHipY = history[history.size - 5].hipY
                    val deltaY = currentFrame.hipY - initialHipY
                    if (deltaY > runUpForwardVelocityThreshold) {
                        currentPhase = BowlingPhase.RUN_UP
                        minWristY = 1.0f
                        releaseDispatched = false
                    }
                }
            }

            BowlingPhase.RUN_UP -> {
                // Gather: bowler leaps or starts circumduction
                if (currentFrame.armAngle > 30f || currentFrame.wristY < (currentFrame.hipY - 0.10f)) {
                    currentPhase = BowlingPhase.GATHER
                }
            }

            BowlingPhase.GATHER -> {
                // Delivery Stride: bowling arm swings above 65 degrees
                if (currentFrame.armAngle > 65f) {
                    currentPhase = BowlingPhase.DELIVERY_STRIDE
                    minWristY = currentFrame.wristY
                }
            }

            BowlingPhase.DELIVERY_STRIDE -> {
                // Update peak wrist height
                if (currentFrame.wristY < minWristY) {
                    minWristY = currentFrame.wristY
                }

                // Release condition:
                // Arm was near-vertical, and wrist has either dropped past tolerance
                // or angular velocity inverted.
                val armPassedPeak = (currentFrame.armAngle < previousArmAngle) && (previousArmAngle > 75f)
                val wristDropped = currentFrame.wristY > (minWristY + releaseZenithTolerance)

                if (!releaseDispatched && (armPassedPeak || wristDropped)) {
                    currentPhase = BowlingPhase.RELEASE
                    isRelease = true
                    releaseDispatched = true
                }
            }

            BowlingPhase.RELEASE -> {
                currentPhase = BowlingPhase.FOLLOW_THROUGH
            }

            BowlingPhase.FOLLOW_THROUGH -> {
                // Reset back to idle once follow-through swing completes
                if (currentFrame.armAngle < 0f || history.size >= maxHistoryFrames) {
                    currentPhase = BowlingPhase.IDLE
                }
            }
        }

        val activeElbow = if (detectedArm == BowlingArm.RIGHT) bowler.rightElbow else bowler.leftElbow
        // Vector A: Shoulder -> Elbow
        val vAx = activeShoulder.x - activeElbow.x
        val vAy = activeShoulder.y - activeElbow.y
        // Vector B: Wrist -> Elbow
        val vBx = activeWrist.x - activeElbow.x
        val vBy = activeWrist.y - activeElbow.y
        val magA = hypot(vAx.toDouble(), vAy.toDouble())
        val magB = hypot(vBx.toDouble(), vBy.toDouble())
        val elbowAngle = if (magA > 0.001 && magB > 0.001) {
            val dot = (vAx * vBx + vAy * vBy) / (magA * magB)
            Math.toDegrees(kotlin.math.acos(dot.coerceIn(-1.0, 1.0))).toFloat()
        } else {
            180f
        }

        // Track minimum elbow angle once arm reaches horizontal (0 deg or higher) during delivery
        if (angleDeg >= 0f && elbowAngle < minElbowDuringDelivery) {
            minElbowDuringDelivery = elbowAngle
        }

        if (isRelease) {
            val flexion = (elbowAngle - minElbowDuringDelivery).coerceAtLeast(0f)
            recordedFlexionDegrees = flexion
            isDeliveryLegal = flexion <= 15.0f // ICC 15-degree rule
        }

        val prevWrist = history.lastOrNull()
        val handSpeed = if (prevWrist != null && timestampMs > prevWrist.timestampMs) {
            val dist = hypot((activeWrist.y - prevWrist.wristY).toDouble(), (stride - prevWrist.stride).toDouble()).toFloat()
            val dt = (timestampMs - prevWrist.timestampMs) / 1000f
            if (dt > 0.001f) (dist / dt) else 0f
        } else {
            0f
        }

        // Calculate Bowler Scale (calibrated to athletic adult bowler ~1.85m)
        val groundAnkleY = maxOf(bowler.leftAnkle.y, bowler.rightAnkle.y)
        val headZenithY = if (bowler.nose.confidence >= minConfidence) bowler.nose.y else (activeShoulder.y - 0.12f)
        val heightNorm = (groundAnkleY - headZenithY + 0.08f).coerceAtLeast(0.20f)
        val metersPerNormUnit = 1.85f / heightNorm

        val releaseHeightM = ((groundAnkleY - activeWrist.y) * metersPerNormUnit).coerceIn(1.2f, 2.7f)
        val strideMeters = (stride * metersPerNormUnit).coerceIn(0.5f, 2.5f)
        val speedKmh = (handSpeed * metersPerNormUnit * 3.6f).coerceIn(0f, 160f)

        previousArmAngle = angleDeg

        return BowlerDeliverySighting(
            phase = currentPhase,
            timestampMs = timestampMs,
            isReleaseMoment = isRelease,
            bowlingArm = detectedArm,
            armAngleDegrees = angleDeg,
            strideWidthNormalized = stride,
            releaseZenithY = minWristY,
            elbowAngleDegrees = elbowAngle,
            handSpeedNormalized = handSpeed,
            elbowFlexionDegrees = recordedFlexionDegrees,
            isActionLegal = isDeliveryLegal,
            releaseHeightMeters = releaseHeightM,
            strideLengthMeters = strideMeters,
            handSpeedKmh = speedKmh,
        )
    }

    /**
     * Filter multiple person candidates down to the bowler.
     * Selects the candidate inside the corridor with the highest upward upper-body belief.
     */
    fun selectBowler(
        candidates: List<BowlerSkeleton>,
        corridorMinX: Float = 0.25f,
        corridorMaxX: Float = 0.75f,
    ): BowlerSkeleton? {
        if (candidates.isEmpty()) return null

        return candidates.filter { cand ->
            val centerX = (cand.leftHip.x + cand.rightHip.x) / 2.0f
            centerX in corridorMinX..corridorMaxX
        }.maxByOrNull { cand ->
            (cand.leftShoulder.confidence + cand.rightShoulder.confidence +
             cand.leftHip.confidence + cand.rightHip.confidence) / 4.0f
        }
    }
}
