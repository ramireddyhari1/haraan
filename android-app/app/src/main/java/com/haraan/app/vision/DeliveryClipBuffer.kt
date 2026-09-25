package com.haraan.app.vision

/**
 * Metadata recorded for each camera frame within the rolling replay buffer.
 */
data class FrameReplayEntry(
    val frameIndex: Long,
    val timestampMs: Long,
    val presentationTimeUs: Long,
    val skeleton: BowlerSkeleton? = null,
    val ballSighting: Point2? = null,
    val wicketAnchor: WicketAnchor? = null,
)

/**
 * Enterprise Replay Package encapsulating telemetry, timing slices, and metadata
 * for a confirmed cricket delivery.
 */
data class DeliveryReplayPackage(
    val deliveryIndex: Int,
    val matchId: String,
    val sessionId: String,
    val deviceId: String,
    val cameraRole: String,
    val releaseTimestampMs: Long,
    val clipStartTimestampMs: Long,
    val clipEndTimestampMs: Long,
    val durationMs: Long,
    val frameCount: Int,
    val bowlerTrackingId: Int?,
    val bowlerConfidence: Float,
    val releaseMetrics: BowlerDeliverySighting?,
    val groundLandingPoint: GroundPoint?,
    val stumpImpact: StumpImpactAssessment?,
    val syncPacket: DeliverySyncPacket?,
    val status: String = "READY",
)

/**
 * Memory-safe rolling buffer for cricket delivery replays.
 *
 * Keeps a bounded circular window of frame metadata (default 5.0 seconds).
 * When release is confirmed by the central DeliveryStateMachine, slices:
 *   clipStart = releaseTimestamp - preRollMs (default 1500ms)
 *   clipEnd   = releaseTimestamp + postRollMs (default 2000ms)
 *
 * Emits an immutable [DeliveryReplayPackage] ready for UI preview,
 * companion transmission, or background upload queue.
 */
class DeliveryClipBuffer(
    private val bufferDurationMs: Long = 5000L,
    private val preRollMs: Long = 1500L,
    private val postRollMs: Long = 2000L,
    private val maxFrameCapacity: Int = 360, // 6 seconds at 60 FPS
) {
    private val ringBuffer = ArrayDeque<FrameReplayEntry>(maxFrameCapacity)
    private var totalFramesEnqueued: Long = 0L

    val currentBufferDurationMs: Long
        get() {
            if (ringBuffer.size < 2) return 0L
            return ringBuffer.last().timestampMs - ringBuffer.first().timestampMs
        }

    val bufferedFrameCount: Int get() = ringBuffer.size

    fun reset() {
        ringBuffer.clear()
        totalFramesEnqueued = 0L
    }

    /**
     * Enqueue a frame entry into the rolling circular buffer.
     * Drops the oldest entry if capacity or duration is exceeded.
     */
    fun onFrame(
        timestampMs: Long,
        presentationTimeUs: Long = timestampMs * 1000L,
        skeleton: BowlerSkeleton? = null,
        ballSighting: Point2? = null,
        wicketAnchor: WicketAnchor? = null,
    ) {
        totalFramesEnqueued++
        val entry = FrameReplayEntry(
            frameIndex = totalFramesEnqueued,
            timestampMs = timestampMs,
            presentationTimeUs = presentationTimeUs,
            skeleton = skeleton,
            ballSighting = ballSighting,
            wicketAnchor = wicketAnchor,
        )

        ringBuffer.addLast(entry)

        // Evict expired frames beyond the circular time window or capacity
        while (ringBuffer.size > maxFrameCapacity ||
            (ringBuffer.size > 1 && timestampMs - ringBuffer.first().timestampMs > bufferDurationMs)
        ) {
            ringBuffer.removeFirst()
        }
    }

    /**
     * Slice the delivery replay window around the confirmed release timestamp.
     */
    fun sliceDeliveryClip(
        deliveryIndex: Int,
        releaseTimestampMs: Long,
        matchId: String = "match_live",
        sessionId: String = "session_live",
        deviceId: String = "camera_1",
        cameraRole: String = DeviceSyncRole.BOWLER_FRONT.name,
        bowlerId: Int? = null,
        bowlerConfidence: Float = 0.90f,
        releaseMetrics: BowlerDeliverySighting? = null,
        groundLandingPoint: GroundPoint? = null,
        stumpImpact: StumpImpactAssessment? = null,
        syncPacket: DeliverySyncPacket? = null,
    ): DeliveryReplayPackage {
        val targetStartMs = releaseTimestampMs - preRollMs
        val targetEndMs = releaseTimestampMs + postRollMs

        // Find available frames matching the slice window
        val slicedFrames = ringBuffer.filter { it.timestampMs in targetStartMs..targetEndMs }

        val actualStartMs = slicedFrames.firstOrNull()?.timestampMs ?: targetStartMs
        val actualEndMs = slicedFrames.lastOrNull()?.timestampMs ?: targetEndMs
        val duration = actualEndMs - actualStartMs

        return DeliveryReplayPackage(
            deliveryIndex = deliveryIndex,
            matchId = matchId,
            sessionId = sessionId,
            deviceId = deviceId,
            cameraRole = cameraRole,
            releaseTimestampMs = releaseTimestampMs,
            clipStartTimestampMs = actualStartMs,
            clipEndTimestampMs = actualEndMs,
            durationMs = duration,
            frameCount = slicedFrames.size,
            bowlerTrackingId = bowlerId,
            bowlerConfidence = bowlerConfidence,
            releaseMetrics = releaseMetrics,
            groundLandingPoint = groundLandingPoint,
            stumpImpact = stumpImpact,
            syncPacket = syncPacket,
            status = if (slicedFrames.isNotEmpty()) "READY" else "EMPTY_SLICE",
        )
    }
}
