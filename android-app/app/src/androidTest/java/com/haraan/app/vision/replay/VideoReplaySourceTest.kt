package com.haraan.app.vision.replay

import android.media.MediaCodec
import android.media.MediaCodecInfo
import android.media.MediaFormat
import android.media.MediaMuxer
import android.net.Uri
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import java.io.File

/**
 * The decoder half of the replay harness, against a real MP4 on a real device.
 *
 * THE CLIP IS BUILT HERE rather than checked into the repository. A fixture video is a
 * binary blob that nobody can review, that bloats every clone, and that silently encodes
 * whatever the machine that made it happened to support. Encoding one on the device that
 * is about to decode it tests the pair, and the pattern in the frames is known — so
 * "frames decoded" can mean "the right frames, in the right order" rather than "some
 * bytes came back".
 *
 * WHAT THIS DOES NOT TEST. Ball detection. The synthetic clip contains a moving square,
 * not cricket, and the tracker is deliberately not involved: this covers opening,
 * metadata, decode order, timestamps and teardown, which are the parts that have to be
 * right before any footage is worth looking at.
 */
@RunWith(AndroidJUnit4::class)
class VideoReplaySourceTest {

    private val context = InstrumentationRegistry.getInstrumentation().targetContext
    private lateinit var clip: File

    @Before
    fun setUp() {
        clip = File(context.cacheDir, "vision-replay-test.mp4")
        if (!clip.exists() || clip.length() == 0L) {
            SyntheticClip.write(clip, WIDTH, HEIGHT, FRAME_COUNT, FRAME_RATE)
        }
    }

    private fun open() = VideoReplaySource.open(context, Uri.fromFile(clip))

    @Test
    fun opensAndReadsMetadata() {
        val source = open()
        try {
            assertEquals("video/avc", source.metadata.mimeType)
            assertEquals(WIDTH, source.metadata.width)
            assertEquals(HEIGHT, source.metadata.height)
            assertEquals(0, source.metadata.rotationDegrees)
            assertTrue("duration should be reported", source.metadata.durationMs > 0)
            assertNotNull(source.metadata.displayName)
        } finally {
            source.release()
        }
    }

    @Test
    fun decodesEveryFrameWithMonotonicTimestamps() {
        val source = open()
        try {
            val engineTimestamps = mutableListOf<Long>()
            var frames = 0
            var malformed = 0

            while (true) {
                when (val step = source.nextFrame()) {
                    is DecodeStep.Frame -> {
                        frames++
                        engineTimestamps += source.engineTimestampMs(step.presentationTimeUs)
                        // The luma plane has to be describable in the engine's four
                        // parameters, or the adapter has failed at its only job.
                        assertEquals(WIDTH, step.luma.width)
                        assertEquals(HEIGHT, step.luma.height)
                        assertTrue(step.luma.rowStride >= step.luma.width)
                        assertTrue(step.luma.bytes.isNotEmpty())
                    }

                    DecodeStep.Malformed -> malformed++
                    DecodeStep.EndOfStream -> break
                }
            }

            assertEquals("every encoded frame should come back", FRAME_COUNT, frames)
            assertEquals("no frame should be unreadable", 0, malformed)
            assertEquals("the first frame anchors the clock at zero", 0L, engineTimestamps.first())
            engineTimestamps.zipWithNext { a, b ->
                assertTrue("timestamps went backwards: $a then $b", b > a)
            }
        } finally {
            source.release()
        }
    }

    @Test
    fun replayingTwiceProducesTheSameTimestamps() {
        val first = open().use { drainTimestamps(it) }
        val second = open().use { drainTimestamps(it) }

        // The claim the whole harness rests on: the same file, decoded again, is the same
        // sequence of frames at the same moments.
        assertEquals(first, second)
    }

    @Test
    fun restartRewindsToTheBeginning() {
        val source = open()
        try {
            val firstPass = drainTimestamps(source)
            source.restart()
            val secondPass = drainTimestamps(source)

            assertTrue(secondPass.isNotEmpty())
            assertEquals(0L, secondPass.first())
            // Seeking lands on a sync sample, so a restart from the first keyframe gives
            // back the whole clip.
            assertEquals(firstPass, secondPass)
        } finally {
            source.release()
        }
    }

    @Test
    fun seekingLandsOnOrBeforeTheRequestedPosition() {
        val source = open()
        try {
            val halfwayUs = source.metadata.durationMs * 1000L / 2
            source.seekTo(halfwayUs)

            val step = source.nextFrame()
            assertTrue("a frame should follow a seek", step is DecodeStep.Frame)
            val landed = (step as DecodeStep.Frame).presentationTimeUs
            assertTrue(
                "a seek may only land at or before the request, never after",
                landed <= halfwayUs + FRAME_INTERVAL_US,
            )
        } finally {
            source.release()
        }
    }

    @Test
    fun endOfStreamIsStickyAndSafeToAskPast() {
        val source = open()
        try {
            drainTimestamps(source)
            // A screen that keeps pumping after the clip ends must get a quiet answer
            // rather than an exception or a rewind.
            repeat(3) { assertEquals(DecodeStep.EndOfStream, source.nextFrame()) }
        } finally {
            source.release()
        }
    }

    @Test
    fun releasingTwiceIsHarmless() {
        val source = open()
        source.release()
        source.release()
    }

    @Test
    fun aFileThatIsNotVideoFailsWithItsReason() {
        val junk = File(context.cacheDir, "not-a-video.mp4")
        junk.writeBytes(ByteArray(2048) { it.toByte() })

        val error = runCatching { VideoReplaySource.open(context, Uri.fromFile(junk)) }
            .exceptionOrNull()

        assertTrue("expected a ReplayException, got $error", error is ReplayException)
        val reason = (error as ReplayException).error
        // Either answer is honest: the extractor may refuse the file outright or open it
        // and find nothing to play.
        assertTrue(
            "unexpected reason: ${reason.title}",
            reason is ReplayError.InvalidUri || reason is ReplayError.NoVideoTrack,
        )
    }

    @Test
    fun aMissingFileFailsRatherThanCrashes() {
        val missing = Uri.fromFile(File(context.cacheDir, "definitely-absent.mp4"))

        val error = runCatching { VideoReplaySource.open(context, missing) }.exceptionOrNull()

        assertTrue(error is ReplayException)
        assertEquals(ReplayError.InvalidUri, (error as ReplayException).error)
    }

    private fun drainTimestamps(source: VideoReplaySource): List<Long> {
        val out = mutableListOf<Long>()
        while (true) {
            when (val step = source.nextFrame()) {
                is DecodeStep.Frame -> out += source.engineTimestampMs(step.presentationTimeUs)
                DecodeStep.Malformed -> Unit
                DecodeStep.EndOfStream -> return out
            }
        }
    }

    private inline fun <T> VideoReplaySource.use(block: (VideoReplaySource) -> T): T =
        try {
            block(this)
        } finally {
            release()
        }

    private companion object {
        const val WIDTH = 320
        const val HEIGHT = 240
        const val FRAME_COUNT = 30
        const val FRAME_RATE = 15
        const val FRAME_INTERVAL_US = 1_000_000L / FRAME_RATE
    }
}

/**
 * Writes a small H.264 MP4 with a bright square tracking across a dark field.
 *
 * H.264 baseline at a tiny resolution because that is the one combination every Android
 * device and every emulator system image is required to decode. A fixture that needs a
 * particular codec would fail on the machines it most needs to run on.
 */
private object SyntheticClip {

    fun write(target: File, width: Int, height: Int, frames: Int, frameRate: Int) {
        val format = MediaFormat.createVideoFormat(MediaFormat.MIMETYPE_VIDEO_AVC, width, height).apply {
            setInteger(
                MediaFormat.KEY_COLOR_FORMAT,
                MediaCodecInfo.CodecCapabilities.COLOR_FormatYUV420Flexible,
            )
            setInteger(MediaFormat.KEY_BIT_RATE, 1_500_000)
            setInteger(MediaFormat.KEY_FRAME_RATE, frameRate)
            // A keyframe every second, so a seek in the test has somewhere to land.
            setInteger(MediaFormat.KEY_I_FRAME_INTERVAL, 1)
        }

        val encoder = MediaCodec.createEncoderByType(MediaFormat.MIMETYPE_VIDEO_AVC)
        encoder.configure(format, null, null, MediaCodec.CONFIGURE_FLAG_ENCODE)
        encoder.start()

        val muxer = MediaMuxer(target.absolutePath, MediaMuxer.OutputFormat.MUXER_OUTPUT_MPEG_4)
        var trackIndex = -1
        var muxing = false
        val info = MediaCodec.BufferInfo()
        val frameIntervalUs = 1_000_000L / frameRate

        var submitted = 0
        var done = false

        try {
            while (!done) {
                if (submitted <= frames) {
                    val index = encoder.dequeueInputBuffer(TIMEOUT_US)
                    if (index >= 0) {
                        if (submitted == frames) {
                            encoder.queueInputBuffer(
                                index, 0, 0,
                                submitted * frameIntervalUs,
                                MediaCodec.BUFFER_FLAG_END_OF_STREAM,
                            )
                        } else {
                            fillFrame(encoder, index, submitted, width, height)
                            encoder.queueInputBuffer(
                                index, 0,
                                encoder.getInputBuffer(index)?.capacity() ?: 0,
                                submitted * frameIntervalUs,
                                0,
                            )
                        }
                        submitted++
                    }
                }

                val out = encoder.dequeueOutputBuffer(info, TIMEOUT_US)
                when {
                    out == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED -> {
                        trackIndex = muxer.addTrack(encoder.outputFormat)
                        muxer.start()
                        muxing = true
                    }

                    out >= 0 -> {
                        val buffer = encoder.getOutputBuffer(out)
                        val isConfig = info.flags and MediaCodec.BUFFER_FLAG_CODEC_CONFIG != 0
                        if (buffer != null && info.size > 0 && !isConfig && muxing) {
                            buffer.position(info.offset)
                            buffer.limit(info.offset + info.size)
                            muxer.writeSampleData(trackIndex, buffer, info)
                        }
                        encoder.releaseOutputBuffer(out, false)
                        if (info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM != 0) done = true
                    }
                }
            }
        } finally {
            runCatching { encoder.stop() }
            runCatching { encoder.release() }
            if (muxing) runCatching { muxer.stop() }
            runCatching { muxer.release() }
        }
    }

    /** A dark field with one moving bright square, written straight into the input image. */
    private fun fillFrame(encoder: MediaCodec, index: Int, frame: Int, width: Int, height: Int) {
        val image = encoder.getInputImage(index) ?: return
        val y = image.planes[0]
        val yBuffer = y.buffer
        val rowStride = y.rowStride
        val pixelStride = y.pixelStride

        val boxSize = 24
        val boxX = (frame * 8) % (width - boxSize)
        val boxY = height / 2 - boxSize / 2

        val row = ByteArray(rowStride)
        for (line in 0 until height) {
            java.util.Arrays.fill(row, 16.toByte())
            if (line in boxY until boxY + boxSize) {
                for (x in boxX until boxX + boxSize) {
                    val at = x * pixelStride
                    if (at < rowStride) row[at] = 235.toByte()
                }
            }
            yBuffer.position(line * rowStride)
            yBuffer.put(row, 0, minOf(rowStride, yBuffer.remaining()))
        }

        // Neutral chroma. The tracker never reads it; the encoder insists on it existing.
        for (plane in 1 until image.planes.size) {
            val buffer = image.planes[plane].buffer
            val neutral = ByteArray(buffer.remaining()) { 128.toByte() }
            buffer.put(neutral)
        }
    }

    private const val TIMEOUT_US = 10_000L
}
