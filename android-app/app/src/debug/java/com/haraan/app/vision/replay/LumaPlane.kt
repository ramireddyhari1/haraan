package com.haraan.app.vision.replay

/**
 * One decoded luminance plane in the shape [com.haraan.app.vision.CricketVisionEngine]
 * accepts: row-major bytes, plus the stride needed to read them without shearing.
 */
data class LumaFrame(
    val bytes: ByteArray,
    val width: Int,
    val height: Int,
    val rowStride: Int,
) {
    // Data class over a ByteArray, so identity comparison is spelled out rather than left
    // to the generated reference equality that would quietly make every frame unequal.
    override fun equals(other: Any?): Boolean {
        if (this === other) return true
        if (other !is LumaFrame) return false
        return width == other.width &&
            height == other.height &&
            rowStride == other.rowStride &&
            bytes.contentEquals(other.bytes)
    }

    override fun hashCode(): Int {
        var result = bytes.contentHashCode()
        result = 31 * result + width
        result = 31 * result + height
        result = 31 * result + rowStride
        return result
    }
}

/**
 * The seam between a decoder's output buffer and the engine's frame contract.
 *
 * WHY THIS IS NOT JUST A COPY. A CameraX analysis image hands over a luma plane whose
 * rows may be padded, and the tracker already honours [LumaFrame.rowStride] for exactly
 * that reason — so the live path does no conversion at all, and neither does this one when
 * it can avoid it. But a MediaCodec output image adds two things the camera never does:
 *
 *  - a CROP RECT, because a decoder rounds a 1080-line clip up to whatever macroblock
 *    multiple its hardware wants and then tells you which part is real. Reading the whole
 *    buffer would feed the tracker a band of codec padding along two edges, which is a
 *    hard edge that moves with the picture — a motion detector's idea of a perfect
 *    candidate.
 *  - a luma PIXEL STRIDE above 1 on some semi-planar outputs, where Y bytes are
 *    interleaved rather than packed.
 *
 * Neither can be expressed in the engine's four parameters, so they are resolved here and
 * nowhere else. When neither applies — the common case — the buffer is passed straight
 * through with its own stride and nothing is copied or re-laid-out, which is precisely
 * what the camera path does.
 *
 * Kept free of android.* on purpose: every decision below is arithmetic, and arithmetic
 * that decides whether a frame is sheared deserves a test that runs on a JVM.
 */
object LumaPlane {

    /**
     * Lay out one plane for the engine, or return null if [source] cannot hold the
     * geometry described.
     *
     * Null rather than a best effort: a short buffer read as though it were full produces
     * a frame whose bottom rows are the previous frame's, and differencing a picture
     * against a seam of itself yields a confident detection of something that was never
     * there. A dropped frame is a gap in the track; a salvaged one is a lie in it.
     *
     * @param scratch reused destination for the packing path. Replaced when it is the
     *   wrong size, so callers may pass whatever they had last frame.
     */
    fun adapt(
        source: ByteArray,
        sourceLength: Int = source.size,
        cropLeft: Int,
        cropTop: Int,
        width: Int,
        height: Int,
        rowStride: Int,
        pixelStride: Int = 1,
        scratch: ByteArray? = null,
    ): LumaFrame? {
        if (width <= 0 || height <= 0 || rowStride <= 0 || pixelStride <= 0) return null
        if (cropLeft < 0 || cropTop < 0) return null
        if (sourceLength <= 0 || sourceLength > source.size) return null

        // The last byte the described region needs. Computed against the FINAL row only:
        // a plane is allowed to end at the last real pixel, without padding after it.
        val firstByte = cropTop.toLong() * rowStride + cropLeft.toLong() * pixelStride
        val lastRow = firstByte + (height - 1).toLong() * rowStride
        val required = lastRow + (width - 1).toLong() * pixelStride + 1
        if (required > sourceLength) return null

        val packed = cropLeft == 0 && cropTop == 0 && pixelStride == 1
        if (packed) {
            // Nothing to do. The tracker reads this exactly as it reads a camera plane.
            return LumaFrame(source, width, height, rowStride)
        }

        val out = scratch?.takeIf { it.size == width * height } ?: ByteArray(width * height)
        if (pixelStride == 1) {
            for (row in 0 until height) {
                System.arraycopy(
                    source,
                    (firstByte + row.toLong() * rowStride).toInt(),
                    out,
                    row * width,
                    width,
                )
            }
        } else {
            for (row in 0 until height) {
                var from = (firstByte + row.toLong() * rowStride).toInt()
                var to = row * width
                repeat(width) {
                    out[to] = source[from]
                    from += pixelStride
                    to++
                }
            }
        }
        return LumaFrame(out, width, height, width)
    }

    /**
     * Presentation timestamp in microseconds to the engine's milliseconds, measured from
     * the first frame of this pass.
     *
     * The engine's only use of a timestamp is the GAP between two of them — it decides
     * whether a candidate could be the same ball as the last one, and whether a pause
     * means a new flight. So what matters is that the gaps are the clip's own gaps, which
     * makes a replay reproduce frame-for-frame no matter how fast the decoder happens to
     * run today. Using the wall clock here would make a slow run look like a ball that
     * teleported, and the same file would score differently on a loaded device.
     *
     * Clamped at zero because a container may carry a first sample with a negative
     * timestamp, and a track that starts before it starts helps nobody.
     */
    fun engineTimestampMs(presentationTimeUs: Long, firstPresentationTimeUs: Long): Long =
        ((presentationTimeUs - firstPresentationTimeUs) / 1000L).coerceAtLeast(0L)
}
