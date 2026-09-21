package com.haraan.app.vision.replay

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The arithmetic that decides whether a replayed frame is the picture or a sheared copy
 * of it.
 *
 * This is worth a test rather than a careful read because the failure is not a crash. A
 * plane read at the wrong stride still produces an image, still runs through the detector
 * and still draws a track — a diagonal one, through a picture that was never filmed. The
 * cheapest moment to catch that is here, where the right answer is known.
 */
class LumaPlaneTest {

    /** A plane whose every byte says which pixel it is, so a mis-read is visible. */
    private fun plane(width: Int, height: Int, rowStride: Int, pixelStride: Int = 1): ByteArray {
        val bytes = ByteArray(rowStride * height)
        for (y in 0 until height) {
            for (x in 0 until width) {
                bytes[y * rowStride + x * pixelStride] = (y * width + x).toByte()
            }
        }
        return bytes
    }

    @Test
    fun `a packed plane is passed through untouched`() {
        val source = plane(width = 8, height = 4, rowStride = 8)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 0,
            cropTop = 0,
            width = 8,
            height = 4,
            rowStride = 8,
        )!!

        // The SAME array, not a copy. This is the common case and the whole reason the
        // engine takes a stride: the camera path copies nothing here either.
        assertSame(source, frame.bytes)
        assertEquals(8, frame.rowStride)
    }

    @Test
    fun `a padded plane keeps its stride rather than being repacked`() {
        val source = plane(width = 6, height = 3, rowStride = 10)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 0,
            cropTop = 0,
            width = 6,
            height = 3,
            rowStride = 10,
        )!!

        assertSame(source, frame.bytes)
        assertEquals(10, frame.rowStride)
        assertEquals(6, frame.width)
    }

    @Test
    fun `a cropped plane is repacked to exactly the crop`() {
        // 16x8 of real buffer, of which the decoder says only 8x4 at (4,2) is the picture.
        val source = plane(width = 16, height = 8, rowStride = 16)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 4,
            cropTop = 2,
            width = 8,
            height = 4,
            rowStride = 16,
        )!!

        assertEquals(8, frame.width)
        assertEquals(4, frame.height)
        assertEquals(8, frame.rowStride)

        val expected = ByteArray(8 * 4)
        for (y in 0 until 4) {
            for (x in 0 until 8) {
                expected[y * 8 + x] = ((y + 2) * 16 + (x + 4)).toByte()
            }
        }
        assertArrayEquals(expected, frame.bytes)
    }

    @Test
    fun `an interleaved plane is gathered into packed bytes`() {
        val source = plane(width = 4, height = 3, rowStride = 16, pixelStride = 2)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 0,
            cropTop = 0,
            width = 4,
            height = 3,
            rowStride = 16,
            pixelStride = 2,
        )!!

        assertEquals(4, frame.rowStride)
        val expected = ByteArray(12) { (it).toByte() }
        assertArrayEquals(expected, frame.bytes)
    }

    @Test
    fun `a buffer too short for its geometry is refused rather than salvaged`() {
        val source = ByteArray(8 * 4 - 3)

        assertNull(
            LumaPlane.adapt(
                source = source,
                cropLeft = 0,
                cropTop = 0,
                width = 8,
                height = 4,
                rowStride = 8,
            ),
        )
    }

    @Test
    fun `a plane ending on its last real pixel is accepted`() {
        // A decoder is allowed to stop the buffer at the final pixel with no trailing pad.
        // Requiring a whole final row would reject a legal frame every time.
        val source = ByteArray(10 * 3 + 6)

        val frame = LumaPlane.adapt(
            source = source,
            sourceLength = source.size,
            cropLeft = 0,
            cropTop = 0,
            width = 6,
            height = 4,
            rowStride = 10,
        )
        assertTrue(frame != null)
    }

    @Test
    fun `nonsense geometry is refused`() {
        val source = ByteArray(64)
        assertNull(LumaPlane.adapt(source, cropLeft = 0, cropTop = 0, width = 0, height = 4, rowStride = 8))
        assertNull(LumaPlane.adapt(source, cropLeft = 0, cropTop = 0, width = 8, height = -1, rowStride = 8))
        assertNull(LumaPlane.adapt(source, cropLeft = -1, cropTop = 0, width = 8, height = 4, rowStride = 8))
        assertNull(LumaPlane.adapt(source, cropLeft = 0, cropTop = 0, width = 8, height = 4, rowStride = 0))
    }

    @Test
    fun `the scratch array is reused when it already fits`() {
        val source = plane(width = 16, height = 8, rowStride = 16)
        val scratch = ByteArray(8 * 4)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 4,
            cropTop = 2,
            width = 8,
            height = 4,
            rowStride = 16,
            scratch = scratch,
        )!!

        assertSame(scratch, frame.bytes)
    }

    @Test
    fun `a scratch array of the wrong size is replaced rather than overflowed`() {
        val source = plane(width = 16, height = 8, rowStride = 16)

        val frame = LumaPlane.adapt(
            source = source,
            cropLeft = 4,
            cropTop = 2,
            width = 8,
            height = 4,
            rowStride = 16,
            scratch = ByteArray(4),
        )!!

        assertEquals(32, frame.bytes.size)
    }

    /**
     * The engine reads gaps, so the origin has to come from the clip and stay there.
     */
    @Test
    fun `engine timestamps are the clip's own, measured from its first sample`() {
        // A container whose first sample sits at 10s, as a trimmed clip's does.
        val first = 10_000_000L

        assertEquals(0L, LumaPlane.engineTimestampMs(first, first))
        assertEquals(33L, LumaPlane.engineTimestampMs(first + 33_333L, first))
        assertEquals(1_000L, LumaPlane.engineTimestampMs(first + 1_000_000L, first))
    }

    @Test
    fun `a sample before the origin clamps to zero rather than running backwards`() {
        assertEquals(0L, LumaPlane.engineTimestampMs(0L, 500_000L))
    }
}
