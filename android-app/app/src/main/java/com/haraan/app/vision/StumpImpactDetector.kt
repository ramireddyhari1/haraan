package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.hypot

/**
 * State of stump impact evaluation.
 */
enum class StumpImpactState {
    NO_IMPACT,
    POSSIBLE_IMPACT,
    CONFIRMED_IMPACT,
}

/**
 * Result of stump impact evaluation on a frame.
 */
data class StumpImpactAssessment(
    val state: StumpImpactState,
    val confidence: Float,
    val timestampMs: Long,
    val displacementMagnitude: Float,
    val tiltDegrees: Float,
    val ballProximityDistance: Float?,
    val reason: String,
)

/**
 * Production-grade multi-signal stump impact detector.
 *
 * Rather than relying on a brittle single-threshold check (e.g. angle > 15°),
 * this detector fuses:
 * 1. Stump baseline/top displacement magnitude
 * 2. Axis angular tilt change relative to resting baseline
 * 3. Sudden onset acceleration (rate of displacement)
 * 4. Ball trajectory proximity (if ball tracking is active)
 * 5. Tracking confidence & stability
 *
 * Implements temporal confirmation:
 * NO_IMPACT -> POSSIBLE_IMPACT -> CONFIRMED_IMPACT.
 */
class StumpImpactDetector(
    private val confirmationFramesRequired: Int = 2,
    private val possibleThreshold: Float = 0.50f,
    private val confirmedThreshold: Float = 0.78f,
) {
    private var restingAnchor: WicketAnchor? = null
    private var previousAnchor: WicketAnchor? = null
    private var previousDisplacement: Float = 0f
    private var previousTimestampMs: Long = 0L

    private var consecutivePossibleFrames: Int = 0
    private var currentState: StumpImpactState = StumpImpactState.NO_IMPACT
    private var lastAssessment: StumpImpactAssessment = StumpImpactAssessment(
        state = StumpImpactState.NO_IMPACT,
        confidence = 0f,
        timestampMs = 0L,
        displacementMagnitude = 0f,
        tiltDegrees = 0f,
        ballProximityDistance = null,
        reason = "INITIAL_IDLE",
    )

    /**
     * Reset baseline resting geometry (e.g. when new wicket is acquired or recalibrated).
     */
    fun reset() {
        restingAnchor = null
        previousAnchor = null
        previousDisplacement = 0f
        previousTimestampMs = 0L
        consecutivePossibleFrames = 0
        currentState = StumpImpactState.NO_IMPACT
    }

    /**
     * Evaluate current stump anchor against previous and resting baselines.
     *
     * @param currentAnchor Current frame's detected/locked stump anchor.
     * @param ballPosition Optional current 2D ball position in normalized coordinates.
     * @param trackingConfidence Confidence of the current wicket sighting (0..1).
     * @param timestampMs Frame timestamp in milliseconds.
     */
    fun onFrame(
        currentAnchor: WicketAnchor?,
        ballPosition: Point2? = null,
        trackingConfidence: Float = 1.0f,
        timestampMs: Long = System.currentTimeMillis(),
    ): StumpImpactAssessment {
        if (currentAnchor == null) {
            // Anchor missing - if we were in possible impact, decay
            if (currentState == StumpImpactState.POSSIBLE_IMPACT) {
                consecutivePossibleFrames++
                if (consecutivePossibleFrames >= confirmationFramesRequired + 2) {
                    currentState = StumpImpactState.NO_IMPACT
                    consecutivePossibleFrames = 0
                }
            }
            return lastAssessment.copy(
                timestampMs = timestampMs,
                reason = "ANCHOR_MISSING",
            )
        }

        // Establish resting baseline from high-confidence initial sightings
        val resting = restingAnchor
        if (resting == null) {
            restingAnchor = currentAnchor
            previousAnchor = currentAnchor
            previousTimestampMs = timestampMs
            lastAssessment = StumpImpactAssessment(
                state = StumpImpactState.NO_IMPACT,
                confidence = 0f,
                timestampMs = timestampMs,
                displacementMagnitude = 0f,
                tiltDegrees = 0f,
                ballProximityDistance = null,
                reason = "RESTING_BASELINE_ACQUIRED",
            )
            return lastAssessment
        }

        // 1. Calculate displacement from resting baseline
        val baseDisp = hypot(currentAnchor.base.x - resting.base.x, currentAnchor.base.y - resting.base.y).toFloat()
        val topDisp = if (currentAnchor.top != null && resting.top != null) {
            hypot(currentAnchor.top.x - resting.top.x, currentAnchor.top.y - resting.top.y).toFloat()
        } else {
            baseDisp
        }
        val maxDisp = maxOf(baseDisp, topDisp)

        // 2. Calculate tilt angle deviation from vertical/resting
        val restingAngle = resting.top?.let { t ->
            atan2(t.x - resting.base.x, resting.base.y - t.y)
        } ?: 0.0
        val currentAngle = currentAnchor.top?.let { t ->
            atan2(t.x - currentAnchor.base.x, currentAnchor.base.y - t.y)
        } ?: 0.0
        val deltaAngleDeg = abs(Math.toDegrees(currentAngle - restingAngle)).toFloat()

        // 3. Sudden onset acceleration (rate of displacement change)
        val dt = (timestampMs - previousTimestampMs).coerceAtLeast(1L)
        val velocity = (maxDisp - previousDisplacement) / (dt / 1000f) // units / sec
        val suddenOnsetScore = (velocity / 0.5f).coerceIn(0f, 1f)

        // 4. Ball proximity score
        val ballDist = ballPosition?.let { bp ->
            val distBase = hypot(bp.x - currentAnchor.base.x, bp.y - currentAnchor.base.y)
            val distTop = currentAnchor.top?.let { t -> hypot(bp.x - t.x, bp.y - t.y) } ?: distBase
            minOf(distBase, distTop).toFloat()
        }
        val ballProximityScore = ballDist?.let { d ->
            (1.0f - (d / 0.12f)).coerceIn(0f, 1f)
        } ?: 0.0f

        // Multi-signal adaptive weighted confidence calculation
        val dispScore = (maxDisp / 0.04f).coerceIn(0f, 1f)
        val tiltScore = (deltaAngleDeg / 10f).coerceIn(0f, 1f)

        var rawConfidence = if (ballPosition != null) {
            dispScore * 0.35f + tiltScore * 0.25f + suddenOnsetScore * 0.20f + ballProximityScore * 0.20f
        } else {
            // Adaptive normalization when ball tracking is inactive / occluded
            dispScore * 0.45f + tiltScore * 0.30f + suddenOnsetScore * 0.25f
        }

        // Boost confidence if both high displacement and ball collision proximity coincide
        if (ballPosition != null && dispScore > 0.5f && ballProximityScore > 0.5f) {
            rawConfidence = minOf(1.0f, rawConfidence + 0.25f)
        }

        val compositeConfidence = (rawConfidence * trackingConfidence.coerceIn(0.5f, 1.0f)).coerceIn(0f, 1f)

        // State Machine Transition
        val newState = when {
            compositeConfidence >= confirmedThreshold -> {
                consecutivePossibleFrames++
                StumpImpactState.CONFIRMED_IMPACT
            }
            compositeConfidence >= possibleThreshold -> {
                consecutivePossibleFrames++
                if (consecutivePossibleFrames >= confirmationFramesRequired) {
                    StumpImpactState.CONFIRMED_IMPACT
                } else {
                    StumpImpactState.POSSIBLE_IMPACT
                }
            }
            else -> {
                consecutivePossibleFrames = maxOf(0, consecutivePossibleFrames - 1)
                if (consecutivePossibleFrames == 0) {
                    StumpImpactState.NO_IMPACT
                } else {
                    currentState
                }
            }
        }

        currentState = newState
        previousAnchor = currentAnchor
        previousDisplacement = maxDisp
        previousTimestampMs = timestampMs

        val reason = when (newState) {
            StumpImpactState.CONFIRMED_IMPACT -> "CONFIRMED_DISPLACEMENT_AND_TILT (disp=%.3f, tilt=%.1f°, conf=%.2f)"
                .format(maxDisp, deltaAngleDeg, compositeConfidence)
            StumpImpactState.POSSIBLE_IMPACT -> "POSSIBLE_DISRUPTION (disp=%.3f, tilt=%.1f°, conf=%.2f)"
                .format(maxDisp, deltaAngleDeg, compositeConfidence)
            StumpImpactState.NO_IMPACT -> "STATIONARY (disp=%.3f, tilt=%.1f°)"
                .format(maxDisp, deltaAngleDeg)
        }

        lastAssessment = StumpImpactAssessment(
            state = newState,
            confidence = compositeConfidence,
            timestampMs = timestampMs,
            displacementMagnitude = maxDisp,
            tiltDegrees = deltaAngleDeg,
            ballProximityDistance = ballDist,
            reason = reason,
        )
        return lastAssessment
    }
}
