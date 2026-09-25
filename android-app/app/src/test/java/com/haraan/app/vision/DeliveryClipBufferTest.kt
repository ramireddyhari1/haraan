package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DeliveryClipBufferTest {

    @Test
    fun `rolling buffer enforces maximum duration and discards expired frames`() {
        val buffer = DeliveryClipBuffer(bufferDurationMs = 3000L, maxFrameCapacity = 100)

        // Enqueue 200 frames spanning 6 seconds (30ms intervals)
        repeat(200) { i ->
            buffer.onFrame(timestampMs = 1000L + (i * 30L))
        }

        assertTrue("Buffered frames must not exceed capacity", buffer.bufferedFrameCount <= 100)
        assertTrue("Buffer duration must be <= 3000ms", buffer.currentBufferDurationMs <= 3000L)
    }

    @Test
    fun `slice delivery clip extracts pre-roll and post-roll window around release`() {
        val buffer = DeliveryClipBuffer(bufferDurationMs = 5000L, preRollMs = 1500L, postRollMs = 2000L)

        // Enqueue 150 frames spanning 4.5 seconds: 1000ms to 5500ms
        repeat(150) { i ->
            buffer.onFrame(timestampMs = 1000L + (i * 30L))
        }

        val releaseTime = 3000L
        val pkg = buffer.sliceDeliveryClip(
            deliveryIndex = 5,
            releaseTimestampMs = releaseTime,
            matchId = "match_final",
            bowlerId = 1,
            bowlerConfidence = 0.94f,
        )

        assertEquals(5, pkg.deliveryIndex)
        assertEquals("READY", pkg.status)
        assertEquals(releaseTime, pkg.releaseTimestampMs)

        // Target window: 3000 - 1500 = 1500ms to 3000 + 2000 = 5000ms (3500ms duration)
        assertTrue("Clip start must be around 1500ms", pkg.clipStartTimestampMs in 1470L..1530L)
        assertTrue("Clip end must be around 5000ms", pkg.clipEndTimestampMs in 4970L..5030L)
        assertTrue("Duration must be ~3500ms", pkg.durationMs in 3400L..3600L)
        assertTrue("Must contain frames across the 3.5s window", pkg.frameCount > 100)
    }

    @Test
    fun `reset clears rolling buffer cleanly`() {
        val buffer = DeliveryClipBuffer()
        repeat(30) { i ->
            buffer.onFrame(timestampMs = 1000L + i * 30L)
        }
        assertEquals(30, buffer.bufferedFrameCount)

        buffer.reset()
        assertEquals(0, buffer.bufferedFrameCount)
        assertEquals(0L, buffer.currentBufferDurationMs)
    }
}
