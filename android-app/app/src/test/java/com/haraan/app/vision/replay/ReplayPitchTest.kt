package com.haraan.app.vision.replay

import com.haraan.app.vision.BallSighting
import com.haraan.app.vision.CameraEnd
import com.haraan.app.vision.CricketVisionEngine
import com.haraan.app.vision.PitchDetectorReport
import com.haraan.app.vision.PitchQuad
import com.haraan.app.vision.Point2
import com.haraan.app.vision.QuadSource
import com.haraan.app.vision.TrackQuality
import com.haraan.app.vision.VisionDiagnostics
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.yield
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * How the replay harness handles calibration.
 *
 * The thing being protected here is that a quad is HELD once found and that the bounce is
 * DROPPED when the track it explained is gone. Both matter for different reasons: a
 * corridor that blinks out every time a fielder crosses the crease is unreadable, and a
 * bounce left on screen after a scrub is a measurement attributed to a delivery nobody is
 * watching any more.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ReplayPitchTest {

    private class StubEngine : CricketVisionEngine {
        private val sightings = mutableListOf<BallSighting>()
        var frames = 0

        override fun onFrame(
            luma: ByteArray,
            width: Int,
            height: Int,
            rowStride: Int,
            rotationDegrees: Int,
            timestampMs: Long,
        ): BallSighting? {
            frames++
            val sighting = BallSighting(timestampMs, 0.4f + frames * 0.005f, 0.5f, 0.8f, 20)
            sightings += sighting
            return sighting
        }

        override fun track(): List<BallSighting> = sightings.toList()
        override fun quality() = TrackQuality.PARTIAL
        override fun diagnostics() = VisionDiagnostics(frames, sightings.size, 0, 0, 0, 0, 0.0, 0L)

        override fun reset() {
            sightings.clear()
            frames = 0
        }

        override fun release() = Unit
    }

    private class FakeSource(private val frameCount: Int) : FrameSource {
        override val metadata = VideoMetadata("video/avc", 64, 48, 0, 1_000L, "fake.mp4")
        private var index = 0

        override fun nextFrame(): DecodeStep {
            if (index >= frameCount) return DecodeStep.EndOfStream
            val current = index++
            return DecodeStep.Frame(
                LumaFrame(ByteArray(64 * 48), 64, 48, 64),
                current * 33_333L,
            )
        }

        override fun engineTimestampMs(presentationTimeUs: Long) = presentationTimeUs / 1000L

        override fun seekTo(positionUs: Long) {
            index = 0
        }

        override fun restart() = seekTo(0L)
        override fun release() = Unit
    }

    /** Answers with a quad only on the frames it is told to, like the real one. */
    private class FakePitch(
        private val foundOn: Set<Int> = setOf(3),
        private val end: CameraEnd = CameraEnd.BOWLER,
    ) : PitchSource {
        var calls = 0
        var released = false
        var lastRotation = -1

        override fun detect(
            luma: ByteArray,
            width: Int,
            height: Int,
            rowStride: Int,
            rotationDegrees: Int,
        ): PitchQuad? {
            lastRotation = rotationDegrees
            val found = calls in foundOn
            calls++
            return if (found) quad(end) else null
        }

        override fun report() = PitchDetectorReport(calls, 0, 0, 0, 0, 0.0, "still looking")
        override fun creases() = emptyList<com.haraan.app.vision.CreaseSegment>()

        override fun release() {
            released = true
        }

        companion object {
            fun quad(end: CameraEnd) = PitchQuad(
                corners = listOf(
                    Point2(0.18, 0.74),
                    Point2(0.82, 0.74),
                    Point2(0.575, 0.36),
                    Point2(0.425, 0.36),
                ),
                source = QuadSource.DETECTED,
                confidence = 0.9f,
                cameraEnd = end,
            )
        }
    }

    private fun kotlinx.coroutines.test.TestScope.controller(
        engine: CricketVisionEngine,
        pitch: PitchSource?,
    ) = ReplayController(
        engine = engine,
        scope = backgroundScope,
        decodeContext = StandardTestDispatcher(testScheduler),
        nowMs = { testScheduler.currentTime },
        pitch = pitch,
    )

    @Test
    fun `the detector sees every frame the tracker does`() = runTest {
        val engine = StubEngine()
        val pitch = FakePitch(foundOn = emptySet())
        val controller = controller(engine, pitch)

        controller.load { FakeSource(12) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        // One frame, two engines. If these ever diverge the calibration describes a
        // different picture from the one the ball was found in.
        assertEquals(engine.frames, pitch.calls)
        assertEquals(12, pitch.calls)
    }

    @Test
    fun `the detector is given the container's rotation, like the tracker`() = runTest {
        val pitch = FakePitch(foundOn = emptySet())
        val controller = controller(StubEngine(), pitch)

        controller.load { FakeSource(4) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        assertEquals(0, pitch.lastRotation)
    }

    @Test
    fun `a quad found once is held through the frames that lose it`() = runTest {
        // Found on frame 3 and never again — exactly what a fielder standing on the
        // crease does to the real detector.
        val controller = controller(StubEngine(), FakePitch(foundOn = setOf(3)))

        controller.load { FakeSource(20) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertNotNull("the corridor must not blink out", state.quad)
        assertEquals(QuadSource.DETECTED, state.quad?.source)
    }

    @Test
    fun `no calibration means no quad and no bounce, rather than a guess`() = runTest {
        val controller = controller(StubEngine(), FakePitch(foundOn = emptySet()))

        controller.load { FakeSource(20) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertNull(state.quad)
        assertNull(state.bounce)
        // The report survives so the screen can say WHY there is no quad.
        assertEquals("still looking", state.pitchReport?.lastRejection)
    }

    @Test
    fun `a replay with no pitch source behaves exactly as it did before`() = runTest {
        val controller = controller(StubEngine(), pitch = null)

        controller.load { FakeSource(10) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertNull(state.quad)
        assertNull(state.pitchReport)
        assertEquals(10, state.framesFed)
    }

    @Test
    fun `a bounce is never attempted from the striker end`() = runTest {
        // BouncePoint refuses this end outright, and the controller must not spend a
        // homography solve per frame discovering that.
        val controller = controller(
            StubEngine(),
            FakePitch(foundOn = setOf(1), end = CameraEnd.STRIKER),
        )

        controller.load { FakeSource(20) }
        controller.play()
        val state = controller.state.first { it.status == ReplayStatus.FINISHED }

        assertNotNull(state.quad)
        assertNull(state.bounce)
    }

    @Test
    fun `a scrub drops the bounce but keeps the calibration`() = runTest {
        val controller = controller(StubEngine(), FakePitch(foundOn = setOf(1)))

        controller.load { FakeSource(30) }
        controller.play()
        controller.state.first { it.status == ReplayStatus.FINISHED }

        controller.restart()
        val rewound = controller.state.first { it.status == ReplayStatus.PAUSED && it.framesFed == 0 }

        // The pitch has not moved; the delivery is gone.
        assertNotNull("re-earning the calibration after every scrub is waste", rewound.quad)
        assertNull("a bounce belongs to a track that no longer exists", rewound.bounce)
    }

    @Test
    fun `the detector is released with the rest of the replay`() = runTest {
        val pitch = FakePitch()
        val controller = controller(StubEngine(), pitch)

        controller.load { FakeSource(200) }
        controller.play()
        controller.state.first { it.framesFed > 0 }

        controller.release()
        testScheduler.advanceUntilIdle()
        yield()

        assertTrue("native pitch memory must be handed back", pitch.released)
    }
}
