package com.haraan.app.vision

import kotlin.math.atan2
import kotlin.math.hypot

/**
 * Normalised 2D landmark point for bowler pose tracking.
 *
 * Coordinates [x] and [y] are in 0.0 .. 1.0 upright frame space (0,0 is top-left).
 * [confidence] is 0.0 .. 1.0 indicating model belief.
 */
data class PoseKeypoint(
    val x: Float,
    val y: Float,
    val confidence: Float = 1.0f,
)

/**
 * Skeleton bundle capturing the 13 essential athletic keypoints for cricket bowler tracking.
 * Agnostic of whether the upstream provider is MediaPipe, MLKit, or YOLO-Pose.
 */
data class BowlerSkeleton(
    val nose: PoseKeypoint,
    val leftShoulder: PoseKeypoint,
    val rightShoulder: PoseKeypoint,
    val leftElbow: PoseKeypoint,
    val rightElbow: PoseKeypoint,
    val leftWrist: PoseKeypoint,
    val rightWrist: PoseKeypoint,
    val leftHip: PoseKeypoint,
    val rightHip: PoseKeypoint,
    val leftKnee: PoseKeypoint,
    val rightKnee: PoseKeypoint,
    val leftAnkle: PoseKeypoint,
    val rightAnkle: PoseKeypoint,
    val boundingBox: PitchBox = PitchBox(0f, 0f, 1f, 1f),
)

data class PitchBox(
    val minX: Float,
    val minY: Float,
    val maxX: Float,
    val maxY: Float,
)

/**
 * The discrete phases of a cricket bowling action.
 */
enum class BowlingPhase {
    /** Bowler at mark or walking back; no delivery motion. */
    IDLE,

    /** Bowler accelerating forward in the approach run-up corridor. */
    RUN_UP,

    /** Pre-delivery jump / gather / bound; back foot about to land. */
    GATHER,

    /** Front foot impact (FFI) and trunk rotation toward delivery crease. */
    DELIVERY_STRIDE,

    /** Exact ball release moment. High-priority trigger for ball tracking. */
    RELEASE,

    /** Post-release follow-through and deceleration. */
    FOLLOW_THROUGH,
}

enum class BowlingArm {
    RIGHT,
    LEFT,
    UNKNOWN,
}

/**
 * Snapshot of bowler delivery metrics emitted on each processed frame.
 */
data class BowlerDeliverySighting(
    val phase: BowlingPhase,
    val timestampMs: Long,
    val isReleaseMoment: Boolean,
    val bowlingArm: BowlingArm,
    val armAngleDegrees: Float,
    val strideWidthNormalized: Float,
    val releaseZenithY: Float,
    val elbowAngleDegrees: Float = 180f,
    val handSpeedNormalized: Float = 0f,
    val elbowFlexionDegrees: Float = 0f,
    val isActionLegal: Boolean = true,
    val releaseHeightMeters: Float = 0f,
    val strideLengthMeters: Float = 0f,
    val handSpeedKmh: Float = 0f,
    val isFrontFootNoBall: Boolean = false,
)
