package com.haraan.app.vision

/**
 * The seam between Haraan and whatever is doing the seeing.
 *
 * Today that is OpenCV and classical motion detection. It may well end up being a trained
 * detector — this phase exists partly to find out — and when it does, only the class
 * behind this interface changes. Nothing else in the app imports `org.opencv`, so the
 * delivery pipeline, the camera screen and the upload path never learn which technique
 * produced a point.
 *
 * Everything here speaks in normalised IMAGE coordinates and camera timestamps, because
 * those are the two things every possible implementation can honestly produce. Pitch
 * coordinates, metres and km/h are deliberately absent: they need calibration that does
 * not exist, and an interface that offered them would invite somebody to fill them in.
 */
interface CricketVisionEngine {

    /**
     * Offer one analysis frame. Returns a sighting when this frame produced one, which for
     * most frames of most deliveries is null — the ball is only in flight for a fraction
     * of a clip, and a frame that shows nothing should say nothing.
     *
     * @param luma       greyscale bytes, row-major
     * @param width      luma width in pixels
     * @param height     luma height in pixels
     * @param rowStride  bytes per row, which is not always equal to width
     * @param timestampMs milliseconds since the first frame of this session
     */
    fun onFrame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        timestampMs: Long,
    ): BallSighting?

    /** Every sighting so far, oldest first. Observed positions only — nothing filled in. */
    fun track(): List<BallSighting>

    /** How much the current track can be relied on. */
    fun quality(): TrackQuality

    /** Why frames were discarded, so quality can be explained rather than asserted. */
    fun diagnostics(): VisionDiagnostics

    /** Start a new delivery. Called when recording starts, never mid-flight. */
    fun reset()

    /** Free native memory. After this the engine must not be used again. */
    fun release()
}

/**
 * One measured ball position.
 *
 * [x] and [y] are normalised 0..1 within the analysis frame: x across, y down. They are
 * positions in a picture. They are not positions on a pitch, and nothing downstream may
 * treat them as such until a calibration step exists.
 */
data class BallSighting(
    val timestampMs: Long,
    val x: Float,
    val y: Float,
    /**
     * How well this candidate behaved like a ball — its size, its roundness, and how well
     * it continued the existing track.
     *
     * Deliberately NOT called a confidence or a probability. Nothing here is calibrated
     * against ground truth, so it ranks candidates against each other and nothing more.
     */
    val trackingConfidence: Float,
    /** Candidate area in analysis pixels. Kept for tuning the size filters against real footage. */
    val areaPx: Int,
)

/**
 * Deliberately three coarse words rather than a number.
 *
 * A percentage invites arithmetic — averaging it, thresholding it, showing it to a player
 * — and none of that is meaningful for a value that has never been checked against a
 * human's judgement of the same footage.
 */
enum class TrackQuality {
    /** Enough consistent points, moving plausibly, to call it a path. */
    RELIABLE,

    /** Something was tracked, but with gaps or wobble. Usable as a hint, not as evidence. */
    PARTIAL,

    /** Too few points, or points that cannot be the same object. Report nothing. */
    UNCERTAIN,
}

/**
 * What the detector threw away and why.
 *
 * The rejection counts matter more than the acceptance count when tuning against real
 * footage: a tracker that finds the ball in every frame AND finds it in the sightscreen
 * is worse than one that finds it half the time and stays quiet otherwise.
 */
data class VisionDiagnostics(
    val framesSeen: Int,
    val framesWithCandidate: Int,
    val rejectedGlobalMotion: Int,
    val rejectedSize: Int,
    val rejectedShape: Int,
    val rejectedTrajectory: Int,
    val averageProcessingMs: Double,
    val maxProcessingMs: Long,
)
