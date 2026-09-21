package com.haraan.app.vision.replay

import com.haraan.app.vision.BallSighting
import com.haraan.app.vision.Bounce
import com.haraan.app.vision.PitchDetectorReport
import com.haraan.app.vision.PitchQuad
import com.haraan.app.vision.StumpDetectorReport
import com.haraan.app.vision.StumpSet
import com.haraan.app.vision.TrackQuality
import com.haraan.app.vision.VisionDiagnostics

/**
 * The vocabulary of the replay harness.
 *
 * DEBUG SOURCE SET. Nothing in this package is compiled into a release build at all —
 * the whole directory lives under src/debug, which is a stronger guarantee than a runtime
 * flag: a tool that cannot be linked cannot be reached.
 *
 * WHAT THIS IS FOR. The live camera can only be judged by standing at a ground, and an
 * afternoon of filming answers one question per delivery. A clip on disk answers the same
 * question as many times as you like, identically, which is the only way to tell whether a
 * threshold change helped or merely moved the failure somewhere else.
 *
 * WHAT THIS IS NOT. It is not a measurement of accuracy. It replays frames through the
 * shipping detector and shows what that detector said. Whether what it said is TRUE is a
 * separate question needing labelled footage, and nothing here should be read as answering
 * it.
 */

/** Where a replay is in its life. */
enum class ReplayStatus {
    /** Nothing chosen yet. */
    IDLE,

    /** Opening the container and starting the decoder. */
    LOADING,

    /** Decoding and feeding frames. */
    PLAYING,

    /** Stopped between frames; the decoder is still open and holds its position. */
    PAUSED,

    /** The last frame of the clip has been fed. */
    FINISHED,

    /** Gave up. [ReplayUiState.error] says why. */
    FAILED,
}

/**
 * What the container says about the clip, read once at open.
 *
 * [width] and [height] are the DECODED BUFFER's dimensions, not the display ones: when
 * [rotationDegrees] is 90 or 270 the two are transposed, and this deliberately reports the
 * buffer's so that it matches what is actually handed to the engine.
 */
data class VideoMetadata(
    val mimeType: String,
    val width: Int,
    val height: Int,
    val rotationDegrees: Int,
    val durationMs: Long,
    val displayName: String,
) {
    /** How the frames appear once the engine has turned them upright. */
    val uprightWidth: Int get() = if (rotationDegrees % 180 == 0) width else height
    val uprightHeight: Int get() = if (rotationDegrees % 180 == 0) height else width
}

/**
 * Why a replay could not run, in a developer's words rather than a user's.
 *
 * [detail] is meant to be read by whoever is holding the phone and is expected to know
 * what a codec is. There is no attempt to soften any of this: a harness that says
 * "something went wrong" wastes the afternoon it exists to save.
 */
sealed class ReplayError(val title: String, val detail: String) {

    object InvalidUri : ReplayError(
        "Cannot open that URI",
        "The content URI could not be resolved, or the permission grant that came with it " +
            "has already expired. Pick the clip again.",
    )

    object NoVideoTrack : ReplayError(
        "No video track",
        "The container opened, but none of its tracks has a video/* MIME type. An audio-only " +
            "file will land here.",
    )

    class UnsupportedCodec(mime: String) : ReplayError(
        "No decoder for $mime",
        "This device has no decoder registered for that MIME type. Re-encode the clip as " +
            "H.264 baseline, which every Android device and every emulator image can decode.",
    )

    class DecoderInitFailed(reason: String) : ReplayError(
        "Decoder would not start",
        "MediaCodec.configure/start threw: $reason. On an emulator this is usually a " +
            "resolution the software decoder will not accept.",
    )

    object EmptyVideo : ReplayError(
        "Clip has no frames",
        "The video track exists but the decoder reached end-of-stream without producing a " +
            "single frame. A zero-length or truncated file lands here.",
    )

    class DecodeFailed(reason: String) : ReplayError(
        "Decode failed",
        "The decoder threw part-way through the clip: $reason",
    )

    object EngineUnavailable : ReplayError(
        "OpenCV did not load",
        "OpenCvBallTracker reported unavailable, so no frame can be analysed. Check " +
            "haraan://vision-check — on an emulator this usually means the ABI has no native " +
            "library.",
    )
}

/** Thrown inside the source; caught by the controller and turned into state. */
class ReplayException(val error: ReplayError, cause: Throwable? = null) :
    Exception("${error.title}: ${error.detail}", cause)

/** The empty diagnostics a screen shows before the first frame. */
val EMPTY_DIAGNOSTICS = VisionDiagnostics(
    framesSeen = 0,
    framesWithCandidate = 0,
    rejectedGlobalMotion = 0,
    rejectedSize = 0,
    rejectedShape = 0,
    rejectedTrajectory = 0,
    averageProcessingMs = 0.0,
    maxProcessingMs = 0L,
)

/**
 * Everything the replay screen draws.
 *
 * [timestampMs] is the engine's timestamp for the newest frame — derived from the clip's
 * presentation timestamp, never from the clock. [processingFps] IS measured against the
 * clock, because it describes how fast the harness chewed through the file and nothing
 * else; it is never fed back into the pipeline.
 */
data class ReplayUiState(
    val status: ReplayStatus = ReplayStatus.IDLE,
    val metadata: VideoMetadata? = null,
    val error: ReplayError? = null,

    val frameIndex: Int = 0,
    val framesFed: Int = 0,
    val malformedFrames: Int = 0,
    val timestampMs: Long = 0L,
    val presentationTimeMs: Long = 0L,
    val progress: Float = 0f,
    val processingFps: Double = 0.0,

    val latest: BallSighting? = null,
    val trail: List<BallSighting> = emptyList(),
    val quality: TrackQuality = TrackQuality.UNCERTAIN,
    val diagnostics: VisionDiagnostics = EMPTY_DIAGNOSTICS,
    val lostReason: String? = null,

    /**
     * The calibrated pitch, once the detector has settled on one.
     *
     * Null is the normal state for most footage, not a failure: the detector wants a view
     * from behind the bowler's arm with creases it can actually see, and a clip filmed
     * from square of the wicket will never produce one. Everything in metres stays
     * unavailable until this is non-null, which is the point.
     */
    val quad: PitchQuad? = null,

    /** Where pitch detection is getting to, so a null quad can be explained. */
    val pitchReport: PitchDetectorReport? = null,

    /** The measured bounce, when the calibration and the track together allow one. */
    val bounce: Bounce? = null,

    /**
     * The detected wicket, when one was found.
     *
     * Held separately from [quad] because it is a different kind of thing: a landmark that
     * has been measured, not a calibration that has been earned. Nothing downstream may
     * turn this into metres on its own.
     */
    val stumps: StumpSet? = null,

    /** Where the wicket search got to, so a null can be explained. */
    val stumpReport: StumpDetectorReport? = null,
) {
    /** Frames the detector produced a point for. Straight from the engine's own counter. */
    val accepted: Int get() = diagnostics.framesWithCandidate

    /**
     * Candidates the filters threw away, summed from the engine's counters.
     *
     * NOT "frames without a ball": a frame where nothing moved at all is counted in
     * neither this nor [accepted], and conflating the two would make the size filter look
     * far busier than it is.
     */
    val rejected: Int
        get() = diagnostics.rejectedGlobalMotion +
            diagnostics.rejectedSize +
            diagnostics.rejectedShape +
            diagnostics.rejectedTrajectory
}
