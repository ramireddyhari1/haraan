package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.hypot
import kotlin.math.max
import kotlin.math.min

/**
 * Semantic classification of tracked persons on a cricket pitch.
 */
enum class PersonRole {
    BOWLER,
    BATTER,
    NON_STRIKER,
    WICKETKEEPER,
    UMPIRE,
    UNKNOWN,
}

/**
 * Configuration weights for bowler kinematic identification.
 * Fully configurable to support slow bowlers, spin bowlers, and fast bowlers.
 */
data class BowlerFilterConfig(
    val movementWeight: Float = 0.20f,
    val forwardMotionWeight: Float = 0.30f,
    val creaseApproachWeight: Float = 0.20f,
    val corridorWeight: Float = 0.15f,
    val persistenceWeight: Float = 0.15f,
    val stationaryPenalty: Float = 0.40f,
    val lateralMotionPenalty: Float = 0.30f,
    val bowlerConfirmThreshold: Float = 0.60f,
    val umpireMaxVelocityKmh: Float = 4.5f,
)

/**
 * Evaluated candidate output containing kinematics, role classification, and explanation.
 */
data class PersonKinematicCandidate(
    val candidateId: Int,
    val skeleton: BowlerSkeleton,
    val role: PersonRole,
    val bowlerConfidence: Float,
    val roleConfidence: Float,
    val velocityKmh: Float,
    val forwardVelocityKmh: Float,
    val lateralVelocityKmh: Float,
    val directionAngleDeg: Float,
    val accelerationKmhPerSec: Float,
    val distanceToCreaseMeters: Float?,
    val trackingFrames: Int,
    val isLockedBowler: Boolean,
    val reason: String,
)

/**
 * Production Multi-Person Bowler Kinematic Filter.
 *
 * Distinguishes the active approaching bowler from standing umpires, non-strikers,
 * and stationary background personnel.
 *
 * Avoids simplistic single-threshold velocity checks (e.g. "v >= 12 km/h") to properly
 * support spin bowlers, walking approaches, and medium-pacers.
 */
class BowlerKinematicFilter(
    private val config: BowlerFilterConfig = BowlerFilterConfig(),
) {
    private data class TrackedCandidateHistory(
        val candidateId: Int,
        var lastSkeleton: BowlerSkeleton,
        var lastCenter: Point2,
        var lastTimestampMs: Long,
        var velocityKmh: Float = 0f,
        var forwardVelocityKmh: Float = 0f,
        var lateralVelocityKmh: Float = 0f,
        var accelerationKmhPerSec: Float = 0f,
        var framesTracked: Int = 1,
        var stationaryFrames: Int = 0,
        var bowlerScoreEma: Float = 0.3f,
    )

    private val activeCandidates = mutableMapOf<Int, TrackedCandidateHistory>()
    private var lockedBowlerId: Int? = null

    fun reset() {
        activeCandidates.clear()
        lockedBowlerId = null
    }

    /**
     * Process all detected person skeletons in a frame.
     *
     * @param skeletons Map of TrackID -> BowlerSkeleton detected by person pose tracker.
     * @param homography Optional pitch homography to calculate ground metric distance to crease.
     * @param timestampMs Frame timestamp in milliseconds.
     */
    fun processFrame(
        skeletons: Map<Int, BowlerSkeleton>,
        homography: PitchHomography? = null,
        timestampMs: Long = System.currentTimeMillis(),
    ): List<PersonKinematicCandidate> {
        val results = mutableListOf<PersonKinematicCandidate>()

        // Evict missing tracks
        val activeIds = skeletons.keys
        activeCandidates.keys.retainAll(activeIds)

        // Process each candidate
        for ((id, skeleton) in skeletons) {
            val center = Point2(
                ((skeleton.leftHip.x + skeleton.rightHip.x) / 2f).toDouble(),
                ((skeleton.leftHip.y + skeleton.rightHip.y) / 2f).toDouble(),
            )

            var history = activeCandidates[id]
            if (history == null) {
                history = TrackedCandidateHistory(
                    candidateId = id,
                    lastSkeleton = skeleton,
                    lastCenter = center,
                    lastTimestampMs = timestampMs,
                )
                activeCandidates[id] = history
            } else {
                val dt = (timestampMs - history.lastTimestampMs).coerceAtLeast(1L) / 1000.0f
                val dxNorm = (center.x - history.lastCenter.x).toFloat()
                val dyNorm = (center.y - history.lastCenter.y).toFloat()

                // Rough metric scale: 1 vertical frame unit ~ 5 meters in bowler run-up corridor
                val dyMeters = dyNorm * 5.0f
                val dxMeters = dxNorm * 3.0f

                val distMeters = hypot(dxMeters, dyMeters)
                val currentSpeedKmh = (distMeters / dt) * 3.6f
                val prevSpeedKmh = history.velocityKmh
                val accel = (currentSpeedKmh - prevSpeedKmh) / dt

                // Forward motion is along +Y down/forward in camera perspective towards crease
                val forwardSpeedKmh = (dyMeters / dt) * 3.6f
                val lateralSpeedKmh = (abs(dxMeters) / dt) * 3.6f

                // EMA smoothing of speeds
                history.velocityKmh = history.velocityKmh * 0.6f + currentSpeedKmh * 0.4f
                history.forwardVelocityKmh = history.forwardVelocityKmh * 0.6f + forwardSpeedKmh * 0.4f
                history.lateralVelocityKmh = history.lateralVelocityKmh * 0.6f + lateralSpeedKmh * 0.4f
                history.accelerationKmhPerSec = history.accelerationKmhPerSec * 0.6f + accel * 0.4f
                history.framesTracked++

                if (history.velocityKmh < config.umpireMaxVelocityKmh) {
                    history.stationaryFrames++
                } else {
                    history.stationaryFrames = max(0, history.stationaryFrames - 1)
                }

                history.lastSkeleton = skeleton
                history.lastCenter = center
                history.lastTimestampMs = timestampMs
            }

            // Calculate metric distance to crease using homography if available
            val distanceToCrease: Float? = homography?.let { h ->
                val groundProj = h.toGround(center)
                if (groundProj is HomographyProjection.GroundSurface) {
                    groundProj.distanceToBowlingCreaseMeters.toFloat()
                } else null
            }

            // Kinematic Scoring for Bowler Role
            val candidate = evaluateCandidate(history, distanceToCrease)
            results.add(candidate)
        }

        // Determine active bowler lock
        val bestBowler = results
            .filter { it.bowlerConfidence >= config.bowlerConfirmThreshold }
            .maxByOrNull { it.bowlerConfidence }

        if (bestBowler != null) {
            lockedBowlerId = bestBowler.candidateId
        } else if (lockedBowlerId != null && !skeletons.containsKey(lockedBowlerId)) {
            lockedBowlerId = null
        }

        return results.map { c ->
            if (c.candidateId == lockedBowlerId) {
                c.copy(isLockedBowler = true)
            } else {
                c
            }
        }
    }

    private fun evaluateCandidate(
        history: TrackedCandidateHistory,
        distanceToCrease: Float?,
    ): PersonKinematicCandidate {
        val speed = history.velocityKmh
        val fwd = history.forwardVelocityKmh
        val lat = history.lateralVelocityKmh
        val center = history.lastCenter

        // 1. Movement score (0..1, reaches 1.0 at 22 km/h)
        val movementScore = (speed / 22f).coerceIn(0f, 1f)

        // 2. Forward motion score (ratio of forward motion vs total)
        val forwardMotionScore = if (speed > 1.0f) {
            (fwd / speed).coerceIn(0f, 1f)
        } else 0f

        // 3. Crease approach score (approaching crease if forward speed > 0)
        val creaseApproachScore = (fwd / 16f).coerceIn(0f, 1f)

        // 4. Pitch corridor score (corridor center in camera is typically 0.20 .. 0.80)
        val corridorScore = if (center.x in 0.20..0.80) 1.0f else 0.4f

        // 5. Persistence score (confirms over 5+ frames)
        val persistenceScore = (history.framesTracked.toFloat() / 8f).coerceIn(0f, 1f)

        // Penalties
        val stationaryPenalty = if (history.stationaryFrames >= 4) config.stationaryPenalty else 0f
        val lateralPenalty = if (lat > fwd && lat > 4.0f) config.lateralMotionPenalty else 0f

        var bowlerScore = (
            movementScore * config.movementWeight +
            forwardMotionScore * config.forwardMotionWeight +
            creaseApproachScore * config.creaseApproachWeight +
            corridorScore * config.corridorWeight +
            persistenceScore * config.persistenceWeight
        ) - stationaryPenalty - lateralPenalty

        bowlerScore = bowlerScore.coerceIn(0f, 1f)
        history.bowlerScoreEma = history.bowlerScoreEma * 0.7f + bowlerScore * 0.3f

        // Semantic Role Disambiguation
        val (role, roleConfidence, reason) = when {
            // Stationary umpire check
            history.stationaryFrames >= 4 && speed < config.umpireMaxVelocityKmh -> {
                val conf = min(1.0f, 0.65f + (history.stationaryFrames * 0.05f))
                Triple(PersonRole.UMPIRE, conf, "LOW_PERSISTENT_VELOCITY (${speed.toInt()} km/h)")
            }
            // Lateral mover (non-striker or fielder)
            lat > fwd && lat > 3.5f -> {
                val conf = (lat / (lat + fwd + 0.1f)).coerceIn(0.6f, 0.95f)
                Triple(PersonRole.NON_STRIKER, conf, "LATERAL_MOTION_ACROSS_CREASE")
            }
            // Active bowler
            history.bowlerScoreEma >= config.bowlerConfirmThreshold -> {
                Triple(PersonRole.BOWLER, history.bowlerScoreEma, "FORWARD_CREASE_APPROACH (${speed.toInt()} km/h)")
            }
            else -> {
                Triple(PersonRole.UNKNOWN, 0.5f, "INSUFFICIENT_KINEMATICS")
            }
        }

        val directionAngle = Math.toDegrees(atan2(fwd.toDouble(), lat.toDouble())).toFloat()

        return PersonKinematicCandidate(
            candidateId = history.candidateId,
            skeleton = history.lastSkeleton,
            role = role,
            bowlerConfidence = history.bowlerScoreEma,
            roleConfidence = roleConfidence,
            velocityKmh = speed,
            forwardVelocityKmh = fwd,
            lateralVelocityKmh = lat,
            directionAngleDeg = directionAngle,
            accelerationKmhPerSec = history.accelerationKmhPerSec,
            distanceToCreaseMeters = distanceToCrease,
            trackingFrames = history.framesTracked,
            isLockedBowler = false,
            reason = reason,
        )
    }
}
