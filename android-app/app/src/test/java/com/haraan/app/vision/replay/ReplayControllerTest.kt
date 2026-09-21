package com.haraan.app.vision.replay

import com.haraan.app.vision.BallSighting
import com.haraan.app.vision.CricketVisionEngine
import com.haraan.app.vision.TrackQuality
import com.haraan.app.vision.VisionDiagnostics
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.yield
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The replay state machine, without a decoder.
 *
 * WHAT IS BEING CLAIMED HERE. Only that the harness drives frames through the engine in
 * the clip's own order, with the clip's own timestamps, and lets go of its decoder when it
 * is done. Nothing about whether a ball is found — the engine is a stub that counts calls,
 * because the question "did every frame arrive, once, in order" is answerable and the
 * question "was the detector right" is not answerable without labelled footage.
 *
 * The fakes are the point of [FrameSource] existing. MediaCodec cannot run on a JVM, so a
 * test that needed it would be an instrumented test, would take a minute to run, and would
 * be the kind of test nobody runs while changing the thing it covers.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ReplayControllerTest {

    // ---- fakes ----------------------------------------------------------------

    /** Records exactly what the pipeline was handed, and nothing more. */
    private class RecordingEngine(
        private val sightingEvery: Int = 0,
    ) : CricketVisionEngine {
        val timestamps = mutableListOf<Long>()
        val rotations = mutableListOf<Int>()
        val sizes = mutableListOf<Pair<Int, Int>>()
        var resets = 0
        var released = false
        private val track = mutableListOf<BallSighting>()
        private var seen = 0

        override fun onFrame(
            luma: ByteArray,
            width: Int,
            height: Int,
            rowStride: Int,
            rotationDegrees: Int,
            timestampMs: Long,
        ): BallSighting? {
            timestamps += timestampMs
            rotations += rotationDegrees
            sizes += width to height
            seen++
            if (sightingEvery > 0 && seen % sightingEvery == 0) {
                val sighting = BallSighting(timestampMs, 0.5f, 0.5f, 0.8f, 12)
                track += sighting
                return sighting
            }
            return null
        }

        override fun track(): List<BallSighting> = track.toList()
        override fun quality() = TrackQuality.UNCERTAIN
        override fun diagnostics() = VisionDiagnostics(
            framesSeen = timestamps.size,
            framesWithCandidate = track.size,
            rejectedGlobalMotion = 0,
            rejectedSize = 0,
            rejectedShape = 0,
            rejectedTrajectory = 0,
            averageProcessingMs = 0.0,
            maxProcessingMs = 0L,
        )

        override fun reset() {
            resets++
            timestamps.clear()
            rotations.clear()
            sizes.clear()
            track.clear()
            seen = 0
        }

        override fun release() {
            released = true
        }
    }

    /** A clip that exists only as a list of timestamps. */
    private class FakeSource(
        private val frameCount: Int,
        override val metadata: VideoMetadata = VideoMetadata(
            mimeType = "video/avc",
            width = 64,
            height = 48,
            rotationDegrees = 90,
            durationMs = 1_000L,
            displayName = "fake.mp4",
        ),
        private val frameIntervalUs: Long = 33_333L,
        private val firstPtsUs: Long = 5_000_000L,
        private val malformedAt: Set<Int> = emptySet(),
        private val throwAt: Int = -1,
    ) : FrameSource {
        var index = 0
        var released = false
        var seeks = 0

        override fun nextFrame(): DecodeStep {
            if (index == throwAt) throw ReplayException(ReplayError.DecodeFailed("fake"))
            if (index >= frameCount) return DecodeStep.EndOfStream
            val current = index++
            if (current in malformedAt) return DecodeStep.Malformed
            return DecodeStep.Frame(
                luma = LumaFrame(ByteArray(64 * 48), 64, 48, 64),
                presentationTimeUs = firstPtsUs + current * frameIntervalUs,
            )
        }

        override fun engineTimestampMs(presentationTimeUs: Long) =
            LumaPlane.engineTimestampMs(presentationTimeUs, firstPtsUs)

        override fun seekTo(positionUs: Long) {
            seeks++
            index = ((positionUs / frameIntervalUs).toInt()).coerceIn(0, frameCount)
        }

        override fun restart() = seekTo(0L)

        override fun release() {
            released = true
        }
    }

    // ---- tests ----------------------------------------------------------------

    @Test
    fun `every frame reaches the engine once, in order`() = runTest {
        val engine = RecordingEngine()
        val source = FakeSource(frameCount = 12)
        val controller = controller(engine)

        controller.load { source }
        controller.play()
        val finished = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertEquals(12, engine.timestamps.size)
        assertEquals(12, finished.framesFed)
        assertEquals(12, finished.frameIndex)
    }

    @Test
    fun `timestamps come from the clip, start at zero and never go backwards`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 20) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        assertEquals(0L, engine.timestamps.first())
        engine.timestamps.zipWithNext { a, b ->
            assertTrue("timestamps went backwards: $a then $b", b > a)
        }
        // 33_333us apart in the container, so ~33ms apart at the engine. The gap is what
        // the tracker's continuity filter reads, and it has to be the clip's gap.
        assertEquals(33L, engine.timestamps[1] - engine.timestamps[0])
    }

    @Test
    fun `the container's rotation and the frame's own size are passed through`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 3) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        // Straight from the container. The engine turns the picture upright; the harness
        // never does, exactly as the camera path never does.
        assertTrue(engine.rotations.all { it == 90 })
        assertTrue(engine.sizes.all { it == 64 to 48 })
    }

    @Test
    fun `pause stops feeding and play resumes where it stopped`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 500) }
        // Paced, so the pump yields between frames and the pause lands mid-clip rather
        // than after a flat-out run has already reached the end.
        controller.setSpeed(ReplaySpeed.REALTIME)
        controller.play()
        controller.state.first { it.framesFed > 0 }
        controller.pause()
        val paused = controller.state.first { it.status == ReplayStatus.PAUSED }

        val fedWhenPaused = paused.framesFed
        assertTrue(fedWhenPaused in 1..500)

        controller.play()
        val later = controller.state.first { it.framesFed > fedWhenPaused }
        // Resumed, not restarted: the counter carried on rather than going back to one.
        assertTrue(later.framesFed > fedWhenPaused)
    }

    @Test
    fun `step advances exactly one frame`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 50) }
        controller.state.first { it.status == ReplayStatus.PAUSED }

        controller.step()
        val afterOne = controller.state.first { it.framesFed == 1 }
        assertEquals(ReplayStatus.PAUSED, afterOne.status)

        controller.step()
        val afterTwo = controller.state.first { it.framesFed == 2 }
        assertEquals(ReplayStatus.PAUSED, afterTwo.status)
    }

    @Test
    fun `restart replays the same clip identically`() = runTest {
        val engine = RecordingEngine(sightingEvery = 3)
        val source = FakeSource(frameCount = 15)
        val controller = controller(engine)

        controller.load { source }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }
        val firstPass = engine.timestamps.toList()

        controller.restart()
        val rewound = controller.state.first { it.status == ReplayStatus.PAUSED && it.framesFed == 0 }
        assertEquals(0, rewound.frameIndex)
        assertTrue(rewound.trail.isEmpty())

        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        // The identical sequence of calls, which is the entire claim this harness makes.
        assertEquals(firstPass, engine.timestamps.toList())
    }

    @Test
    fun `a seek clears the detector rather than differencing across the jump`() = runTest {
        val engine = RecordingEngine()
        val source = FakeSource(frameCount = 60)
        val controller = controller(engine)

        controller.load { source }
        controller.play()
        controller.state.first { it.framesFed > 0 }

        val resetsBefore = engine.resets
        controller.seekToFraction(0.5f)
        val seeked = controller.state.first { it.status == ReplayStatus.PAUSED && it.framesFed == 0 }

        assertTrue(source.seeks > 0)
        assertTrue("the engine must be reset across a jump", engine.resets > resetsBefore)
        assertEquals(0f, seeked.progress, 0.001f)
        assertNotNull(seeked.metadata)
    }

    @Test
    fun `a clip with no frames is reported as empty rather than finished`() = runTest {
        val controller = controller(RecordingEngine())

        controller.load { FakeSource(frameCount = 0) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FAILED }

        assertEquals(ReplayError.EmptyVideo, state.error)
    }

    @Test
    fun `unreadable frames are counted instead of being passed off as pictures`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 10, malformedAt = setOf(2, 5)) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertEquals(2, state.malformedFrames)
        assertEquals(8, engine.timestamps.size)
    }

    @Test
    fun `a decoder that throws mid-clip fails the replay with its reason`() = runTest {
        val controller = controller(RecordingEngine())

        controller.load { FakeSource(frameCount = 20, throwAt = 4) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FAILED }

        assertTrue(state.error is ReplayError.DecodeFailed)
        assertEquals(4, state.framesFed)
    }

    @Test
    fun `a clip that will not open fails with the opener's reason`() = runTest {
        val controller = controller(RecordingEngine())

        controller.load { throw ReplayException(ReplayError.NoVideoTrack) }
        val state = controller.state.first { it.status == ReplayStatus.FAILED }

        assertEquals(ReplayError.NoVideoTrack, state.error)
    }

    @Test
    fun `nothing is decoded when the detector could not load`() = runTest {
        val engine = RecordingEngine()
        var opened = false
        val controller = ReplayController(
            engine = engine,
            scope = backgroundScope,
            decodeContext = StandardTestDispatcher(testScheduler),
            engineAvailable = { false },
        )

        controller.load {
            opened = true
            FakeSource(frameCount = 5)
        }

        val state = controller.state.first { it.status == ReplayStatus.FAILED }
        assertEquals(ReplayError.EngineUnavailable, state.error)
        assertFalse("the clip must not even be opened", opened)
        assertTrue(engine.timestamps.isEmpty())
    }

    @Test
    fun `the decoder is handed back when the replay is released`() = runTest {
        val source = FakeSource(frameCount = 500)
        val controller = controller(RecordingEngine())

        controller.load { source }
        controller.play()
        controller.state.first { it.framesFed > 0 }

        controller.release()
        // The pump owns the release and performs it in its finally block, so this becomes
        // true once the cancellation has unwound the pump — not on the calling thread.
        // Both steps are needed: advancing runs the cancelled delay's resumption, and the
        // yield lets the finally block itself be dispatched before the assertion reads it.
        testScheduler.advanceUntilIdle()
        yield()

        assertTrue("the source must be released when the screen closes", source.released)
    }

    @Test
    fun `releasing twice is harmless`() = runTest {
        val controller = controller(RecordingEngine())
        controller.load { FakeSource(frameCount = 4) }
        controller.state.first { it.status == ReplayStatus.PAUSED }

        controller.release()
        controller.release()
        testScheduler.advanceUntilIdle()
        yield()
    }

    @Test
    fun `loading a second clip does not inherit the first one's commands`() = runTest {
        val engine = RecordingEngine()
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 4) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        val second = FakeSource(frameCount = 6)
        controller.load { second }
        val loaded = controller.state.first { it.status == ReplayStatus.PAUSED }

        // Paused at the first frame, because the previous clip's PLAY was drained.
        assertEquals(0, loaded.framesFed)
        assertEquals(0, second.index)
    }

    @Test
    fun `progress and diagnostics are published as the clip runs`() = runTest {
        val engine = RecordingEngine(sightingEvery = 2)
        val controller = controller(engine)

        controller.load { FakeSource(frameCount = 30) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertEquals(1f, state.progress, 0.001f)
        assertEquals(30, state.diagnostics.framesSeen)
        assertEquals(15, state.accepted)
        assertEquals(0, state.rejected)
        assertTrue(state.trail.isNotEmpty())
        assertEquals("fake.mp4", state.metadata?.displayName)
    }

    private fun kotlinx.coroutines.test.TestScope.controller(engine: CricketVisionEngine) =
        ReplayController(
            engine = engine,
            scope = backgroundScope,
            decodeContext = StandardTestDispatcher(testScheduler),
            nowMs = { testScheduler.currentTime },
        )
}
