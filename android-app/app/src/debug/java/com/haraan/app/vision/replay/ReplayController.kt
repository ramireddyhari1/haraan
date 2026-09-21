package com.haraan.app.vision.replay

import com.haraan.app.vision.BouncePoint
import com.haraan.app.vision.CameraEnd
import com.haraan.app.vision.CricketVisionEngine
import com.haraan.app.vision.PitchDetectorReport
import com.haraan.app.vision.CreaseSegment
import com.haraan.app.vision.PitchQuad
import com.haraan.app.vision.StumpDetectorReport
import com.haraan.app.vision.StumpSet
import com.haraan.app.vision.VISION_TRAIL_LENGTH
import com.haraan.app.vision.dominantRejection
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlin.coroutines.CoroutineContext
import kotlin.coroutines.coroutineContext

/**
 * A thing that produces frames in order. Implemented by [VideoReplaySource].
 *
 * The interface exists so the controller's state machine — which owns everything that can
 * go wrong about pausing, seeking and shutting down — can be exercised on a JVM against a
 * fake, without a decoder, an emulator or a file. The MediaCodec plumbing is then the only
 * part that genuinely needs a device to test, which is as small as that part gets.
 */
interface FrameSource {
    val metadata: VideoMetadata
    fun nextFrame(): DecodeStep
    fun engineTimestampMs(presentationTimeUs: Long): Long
    fun seekTo(positionUs: Long)
    fun restart()
    fun release()
}

/**
 * The pitch detector, behind an interface for the same reason [FrameSource] is.
 *
 * Calibration is the thing that turns this screen from a picture into a measurement, so
 * the controller's handling of it — when it runs, what it does with a null, how a found
 * quad reaches the overlay — is worth testing without OpenCV, a decoder or a ground.
 */
interface PitchSource {
    fun detect(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
    ): PitchQuad?

    fun report(): PitchDetectorReport?

    /** What the last analysed frame saw at a crease's angle, normalised. */
    fun creases(): List<CreaseSegment>

    fun release()
}

/**
 * The wicket detector, behind an interface for the same reason [PitchSource] is.
 *
 * Optional: a replay with no wicket source behaves exactly as it did before, which is the
 * property that lets this be added without touching what already works.
 */
interface StumpSource {
    fun detect(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment>,
    ): StumpSet?

    fun report(): StumpDetectorReport?
    fun release()
}

/** How fast the pump works through the clip. Neither setting ever skips a frame. */
enum class ReplaySpeed {
    /** As fast as the decoder and detector manage. Shortest path to a verdict. */
    FULL,

    /**
     * Paced to the clip's own timestamps, so it looks like the footage did.
     *
     * The pacing is a SLEEP between frames and nothing more. It cannot change which frames
     * are fed or what timestamps they carry, so FULL and REALTIME produce an identical
     * track — the difference is only whether a human can watch it happen.
     */
    REALTIME,
}

/**
 * Drives a [FrameSource] through a [CricketVisionEngine] and publishes what happened.
 *
 * ONE THREAD TOUCHES THE DECODER. Every command — play, pause, seek, release — is posted
 * to a channel and executed by the pump coroutine between frames. Nothing else ever calls
 * into the source, which removes the entire category of bug where the UI releases a codec
 * while the pump is mid-dequeue.
 *
 * DETERMINISM IS THE POINT. The pump never drops a frame to catch up and never takes a
 * timestamp from the clock; it decodes every frame in order and hands the engine the
 * clip's own presentation timestamps. Two runs over the same file therefore make the same
 * calls to the same detector in the same order, which is the only reason a replay is worth
 * more than watching the video.
 *
 * @param resetOnSeek clears the engine whenever the position jumps. On by default and
 *   there is no honest way to turn it off in the UI: the detector works on the difference
 *   between consecutive frames, so the first frame after a jump differs against a picture
 *   from somewhere else entirely, which lights up the whole image.
 */
class ReplayController(
    private val engine: CricketVisionEngine,
    private val scope: CoroutineScope,
    private val decodeContext: CoroutineContext,
    private val nowMs: () -> Long = System::currentTimeMillis,
    private val resetOnSeek: Boolean = true,
    /**
     * Whether the detector can run at all.
     *
     * Passed in rather than asked of the engine because availability is not part of
     * [CricketVisionEngine] — OpenCvBallTracker publishes it as a property of its own, and
     * a controller that downcast to find it would be a controller that cannot be tested
     * against anything else.
     */
    private val engineAvailable: () -> Boolean = { true },
    /**
     * Run alongside the ball tracker on every frame, when supplied.
     *
     * Every frame rather than once, because the detector earns its answer by watching
     * several frames agree — a single frame's idea of a pitch is a shadow edge away from
     * nonsense, and it says so itself. The cost is real: Hough on every frame roughly
     * halves replay throughput, which is a fair trade on a harness whose whole purpose is
     * to be thorough rather than quick.
     */
    private val pitch: PitchSource? = null,
    /**
     * Run alongside the other two on every frame, when supplied.
     *
     * A third consumer of the same luma. The cost is real and so is the reason: a wicket
     * is the landmark most likely to survive footage the crease detector cannot read.
     */
    private val stumps: StumpSource? = null,
    /**
     * Handed every frame, on the pump thread, while its buffer is still valid.
     *
     * Synchronous and documented as such because [LumaFrame.bytes] is a scratch array the
     * decoder reuses next frame. A sink that posted the array somewhere to be read later
     * would be reading the frame after it, which is the kind of bug that shows up as an
     * overlay that is one frame ahead of its picture and nothing else.
     */
    private val previewSink: ((LumaFrame) -> Unit)? = null,
) {

    private val _state = MutableStateFlow(ReplayUiState())
    val state: StateFlow<ReplayUiState> = _state.asStateFlow()

    /**
     * Where the detector said the ball was, by frame number.
     *
     * Kept beside the track rather than derived from it because [CricketVisionEngine.track]
     * reports sightings, not the frames they came from — and scoring against a human's
     * labels needs to compare frame for frame. Concurrent because the pump writes it and
     * the labelling screen reads it.
     */
    private val detectionLog = java.util.concurrent.ConcurrentHashMap<Int, Pair<Float, Float>>()

    private val commands = Channel<Command>(Channel.UNLIMITED)
    private var pump: Job? = null

    private var speed = ReplaySpeed.FULL
    private var playing = false
    private var stepsRemaining = 0
    private var lastPresentationTimeUs = Long.MIN_VALUE
    private var framesThisWindow = 0
    private var windowStartedAt = 0L

    private sealed interface Command {
        data object Play : Command
        data object Pause : Command
        data class Step(val frames: Int) : Command
        data object Restart : Command
        data class SeekTo(val positionUs: Long) : Command
        data class SetSpeed(val speed: ReplaySpeed) : Command
        data object Release : Command
    }

    /**
     * Open a clip and start the pump paused at the first frame.
     *
     * [open] is a lambda rather than a URI so that the controller never touches the
     * content resolver: the caller has the URI and the permission grant that came with it,
     * and this has the state machine.
     */
    fun load(open: () -> FrameSource) {
        if (!engineAvailable()) {
            _state.value = ReplayUiState(
                status = ReplayStatus.FAILED,
                error = ReplayError.EngineUnavailable,
            )
            return
        }

        releaseInternal()
        // A command posted at the previous clip must not reach this one's pump.
        while (commands.tryReceive().isSuccess) Unit
        _state.value = ReplayUiState(status = ReplayStatus.LOADING)

        pump = scope.launch(decodeContext) {
            val opened = try {
                open()
            } catch (e: ReplayException) {
                _state.value = ReplayUiState(status = ReplayStatus.FAILED, error = e.error)
                return@launch
            } catch (t: Throwable) {
                _state.value = ReplayUiState(
                    status = ReplayStatus.FAILED,
                    error = ReplayError.DecodeFailed(t.message ?: t::class.java.simpleName),
                )
                return@launch
            }

            engine.reset()
            detectionLog.clear()
            resetCounters()
            _state.value = ReplayUiState(
                status = ReplayStatus.PAUSED,
                metadata = opened.metadata,
            )

            try {
                runPump(opened)
            } finally {
                // Whether the clip ended, the screen closed or the decoder threw, the
                // codec goes back on this thread and only this thread.
                opened.release()
                pitch?.release()
                stumps?.release()
            }
        }
    }

    /** A snapshot of every detection so far, for scoring against labels. */
    fun detections(): Map<Int, Pair<Float, Float>> = detectionLog.toMap()

    fun play() = post(Command.Play)
    fun pause() = post(Command.Pause)
    fun step() = post(Command.Step(1))
    fun restart() = post(Command.Restart)
    fun setSpeed(value: ReplaySpeed) = post(Command.SetSpeed(value))

    /** Seek to a fraction of the clip's duration. Ignored when the duration is unknown. */
    fun seekToFraction(fraction: Float) {
        val duration = _state.value.metadata?.durationMs ?: return
        if (duration <= 0L) return
        post(Command.SeekTo((duration * fraction.coerceIn(0f, 1f) * 1000L).toLong()))
    }

    /**
     * Stop the pump and release the decoder.
     *
     * Cancels rather than waits: the pump's finally block owns the release, so cancelling
     * is enough and blocking the main thread on a decoder shutdown is not.
     */
    fun release() {
        post(Command.Release)
        releaseInternal()
    }

    private fun releaseInternal() {
        pump?.cancel()
        pump = null
    }

    private fun post(command: Command) {
        commands.trySend(command)
    }

    private suspend fun runPump(active: FrameSource) {
        while (coroutineContext.isActive) {
            var released = false
            while (true) {
                val command = commands.tryReceive().getOrNull() ?: break
                if (apply(command, active)) released = true
            }
            if (released) return

            if (!playing && stepsRemaining <= 0) {
                // Idle. Cheap enough that the pump can simply wait for the next command.
                delay(IDLE_POLL_MS)
                continue
            }
            if (_state.value.status == ReplayStatus.FINISHED) {
                playing = false
                stepsRemaining = 0
                continue
            }

            val step = try {
                active.nextFrame()
            } catch (e: ReplayException) {
                playing = false
                _state.update { it.copy(status = ReplayStatus.FAILED, error = e.error) }
                continue
            }

            when (step) {
                is DecodeStep.Frame -> {
                    if (speed == ReplaySpeed.REALTIME) paceTo(step.presentationTimeUs)
                    lastPresentationTimeUs = step.presentationTimeUs
                    feed(active, step)
                    if (stepsRemaining > 0) {
                        stepsRemaining--
                        if (stepsRemaining == 0) playing = false
                    }
                }

                DecodeStep.Malformed -> {
                    _state.update { it.copy(malformedFrames = it.malformedFrames + 1) }
                }

                DecodeStep.EndOfStream -> {
                    playing = false
                    stepsRemaining = 0
                    _state.update {
                        // A clip whose decoder never produced a picture is a different
                        // failure from one that simply finished, and says so.
                        if (it.framesFed == 0) {
                            it.copy(status = ReplayStatus.FAILED, error = ReplayError.EmptyVideo)
                        } else {
                            it.copy(status = ReplayStatus.FINISHED, progress = 1f)
                        }
                    }
                }
            }
        }
    }

    /** Returns true when the pump should shut down. */
    private fun apply(command: Command, active: FrameSource): Boolean {
        when (command) {
            Command.Play -> {
                if (_state.value.status == ReplayStatus.FINISHED) rewind(active)
                playing = true
                stepsRemaining = 0
                windowStartedAt = nowMs()
                framesThisWindow = 0
                _state.update { it.copy(status = ReplayStatus.PLAYING, error = null) }
            }

            Command.Pause -> {
                playing = false
                stepsRemaining = 0
                if (_state.value.status == ReplayStatus.PLAYING) {
                    _state.update { it.copy(status = ReplayStatus.PAUSED) }
                }
            }

            is Command.Step -> {
                if (_state.value.status == ReplayStatus.FINISHED) rewind(active)
                stepsRemaining += command.frames
                _state.update { it.copy(status = ReplayStatus.PAUSED, error = null) }
            }

            Command.Restart -> {
                rewind(active)
                playing = false
                stepsRemaining = 0
            }

            is Command.SeekTo -> {
                jump(active) { active.seekTo(command.positionUs) }
            }

            is Command.SetSpeed -> speed = command.speed

            Command.Release -> return true
        }
        return false
    }

    private fun rewind(active: FrameSource) = jump(active) { active.restart() }

    /**
     * Move the decoder, then put the detector back to a blank slate.
     *
     * The engine reset is not optional housekeeping. [CricketVisionEngine] holds the
     * previous frame to difference against, and a frame from 0:03 differenced against one
     * from 0:19 is a full-frame change — which the global-motion guard reads as a shaking
     * camera and blames for the next several seconds of missing ball.
     */
    private fun jump(active: FrameSource, move: () -> Unit) {
        runCatching { move() }
        if (resetOnSeek) engine.reset()
        detectionLog.clear()
        resetCounters()
        _state.update {
            it.copy(
                status = ReplayStatus.PAUSED,
                error = null,
                frameIndex = 0,
                framesFed = 0,
                malformedFrames = 0,
                timestampMs = 0L,
                presentationTimeMs = 0L,
                progress = 0f,
                processingFps = 0.0,
                latest = null,
                trail = emptyList(),
                quality = engine.quality(),
                diagnostics = engine.diagnostics(),
                lostReason = null,
                // The bounce belongs to a track that no longer exists. The CALIBRATION
                // does not — the pitch is still where it was, and making the detector
                // earn it again after every scrub would be pure waste.
                bounce = null,
            )
        }
    }

    /**
     * Hand one frame to the engine and record what came back.
     *
     * This is the whole reason the harness exists, and it is four lines long on purpose:
     * the call below is character for character the call the live CameraX analyser makes
     * in [com.haraan.app.vision.VisionFieldTestActivity] and the paired camera screen. A
     * replay that pre-processed its frames even slightly would be measuring a detector
     * that never ships.
     */
    private fun feed(active: FrameSource, step: DecodeStep.Frame) {
        val before = engine.diagnostics()
        val timestampMs = active.engineTimestampMs(step.presentationTimeUs)
        previewSink?.invoke(step.luma)

        // The SAME luma, the same stride, the same rotation the tracker is about to be
        // given. Two engines reading one frame, exactly as the paired camera screen runs
        // them — a replay that calibrated off a differently prepared picture would be
        // measuring a pipeline that never ships.
        val quad = pitch?.detect(
            luma = step.luma.bytes,
            width = step.luma.width,
            height = step.luma.height,
            rowStride = step.luma.rowStride,
            rotationDegrees = active.metadata.rotationDegrees,
        )

        // AFTER the pitch detector, never before: the wicket search is ranked partly by
        // nearness to crease-angled segments, and those have to come from THIS frame.
        val wicket = stumps?.detect(
            luma = step.luma.bytes,
            width = step.luma.width,
            height = step.luma.height,
            rowStride = step.luma.rowStride,
            rotationDegrees = active.metadata.rotationDegrees,
            creases = pitch?.creases().orEmpty(),
        )

        val sighting = engine.onFrame(
            luma = step.luma.bytes,
            width = step.luma.width,
            height = step.luma.height,
            rowStride = step.luma.rowStride,
            rotationDegrees = active.metadata.rotationDegrees,
            timestampMs = timestampMs,
        )

        val diagnostics = engine.diagnostics()

        // Numbered the same way the screen numbers frames, so a label tapped on the frame
        // the viewer is looking at lines up with the detection made from it.
        val frameNumber = _state.value.frameIndex + 1
        if (sighting != null) detectionLog[frameNumber] = sighting.x to sighting.y

        framesThisWindow++
        val elapsed = nowMs() - windowStartedAt
        val fps = if (elapsed >= FPS_WINDOW_MS) {
            (framesThisWindow * 1000.0 / elapsed).also {
                framesThisWindow = 0
                windowStartedAt = nowMs()
            }
        } else {
            null
        }

        val duration = active.metadata.durationMs
        val presentationMs = step.presentationTimeUs / 1000L

        _state.update { previous ->
            val trail = if (sighting != null) {
                engine.track().takeLast(VISION_TRAIL_LENGTH)
            } else {
                previous.trail
            }

            // A quad once found is kept: the detector returns null on any frame a fielder
            // happens to be standing on the crease, and a corridor that blinks out every
            // few frames would be unreadable. A later, better quad replaces it.
            val heldQuad = quad ?: previous.quad

            previous.copy(
                frameIndex = previous.frameIndex + 1,
                framesFed = previous.framesFed + 1,
                timestampMs = timestampMs,
                presentationTimeMs = presentationMs,
                progress = if (duration > 0L) {
                    (presentationMs.toFloat() / duration).coerceIn(0f, 1f)
                } else {
                    previous.progress
                },
                processingFps = fps ?: previous.processingFps,
                latest = sighting,
                trail = trail,
                quality = engine.quality(),
                diagnostics = diagnostics,
                // Named from the counter that moved, so a tester can tell a ball hidden
                // behind the batter from one the size filter ate. Shared with the field
                // test rather than re-derived, so both harnesses blame the same filter.
                lostReason = if (sighting == null && previous.latest != null) {
                    dominantRejection(diagnostics, before)
                } else if (sighting != null) {
                    null
                } else {
                    previous.lostReason
                },
                quad = heldQuad,
                pitchReport = pitch?.report() ?: previous.pitchReport,
                // Held like the quad is, and for the same reason: a batter standing over
                // the stumps loses them for a few frames, and a landmark that blinks is
                // unreadable.
                stumps = wicket ?: previous.stumps,
                stumpReport = stumps?.report() ?: previous.stumpReport,
                // Only recomputed when the track grew. BouncePoint fits two lines through
                // a homography and there is nothing new for it to find on a frame that
                // produced no sighting.
                bounce = if (sighting != null && heldQuad != null) {
                    locateBounce(trail, heldQuad) ?: previous.bounce
                } else {
                    previous.bounce
                },
            )
        }
    }

    /**
     * The bounce, or the one already found.
     *
     * Guarded on the cheap conditions first because [BouncePoint.find] is the most
     * expensive thing on this path and returns null for most tracks most of the time —
     * it only answers from the bowler's end, and only once it has watched enough of a
     * delivery to see the turn.
     */
    private fun locateBounce(track: List<com.haraan.app.vision.BallSighting>, quad: PitchQuad) =
        if (quad.cameraEnd != CameraEnd.BOWLER || track.size < BouncePoint.MIN_SIGHTINGS) {
            null
        } else {
            runCatching { BouncePoint.find(track, quad) }.getOrNull()
        }

    /** Sleep out the gap between this frame and the last, when watching in real time. */
    private suspend fun paceTo(presentationTimeUs: Long) {
        if (lastPresentationTimeUs == Long.MIN_VALUE) return
        val gapMs = (presentationTimeUs - lastPresentationTimeUs) / 1000L
        if (gapMs in 1..MAX_PACING_MS) delay(gapMs)
    }

    private fun resetCounters() {
        lastPresentationTimeUs = Long.MIN_VALUE
        framesThisWindow = 0
        windowStartedAt = nowMs()
    }

    private inline fun MutableStateFlow<ReplayUiState>.update(
        block: (ReplayUiState) -> ReplayUiState,
    ) {
        value = block(value)
    }

    companion object {
        private const val IDLE_POLL_MS = 16L
        private const val FPS_WINDOW_MS = 500L

        /** A gap longer than this is a hole in the clip, not something to sit through. */
        private const val MAX_PACING_MS = 500L
    }
}
