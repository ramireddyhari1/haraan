package com.haraan.app.vision.replay

import android.graphics.Bitmap

/**
 * The frame, as the detector sees it, turned into something a person can look at.
 *
 * GREYSCALE ON PURPOSE. It would be easy to decode colour here and show a prettier
 * picture, and it would be misleading: the tracker is handed the luma plane and nothing
 * else, so a red ball that is obvious against green grass in colour and invisible in
 * greyscale is a fact about this detector worth seeing rather than hiding. What is drawn
 * is exactly what was analysed.
 *
 * NEAREST NEIGHBOUR, NOT AVERAGED. Downsampling by picking pixels rather than blending
 * them keeps this cheap enough to run on the decode thread between frames, and this image
 * is never fed back into anything — it is looked at, and that is all.
 */
object LumaPreview {

    /** Wide enough to see a ball on a phone screen, small enough to build per frame. */
    const val MAX_WIDTH = 720

    /**
     * Convert [frame] into an opaque greyscale bitmap.
     *
     * @param reuse a bitmap from a previous call. Reused when its dimensions still match,
     *   because a replay builds one of these thirty times a second and a fresh allocation
     *   each time is how a debug screen ends up slower than the thing it is debugging.
     */
    fun toBitmap(frame: LumaFrame, reuse: Bitmap?, scratch: IntArray?): Pair<Bitmap, IntArray> {
        val scale = if (frame.width > MAX_WIDTH) MAX_WIDTH.toFloat() / frame.width else 1f
        val outWidth = (frame.width * scale).toInt().coerceAtLeast(1)
        val outHeight = (frame.height * scale).toInt().coerceAtLeast(1)

        val pixels = scratch?.takeIf { it.size == outWidth * outHeight }
            ?: IntArray(outWidth * outHeight)

        val stepX = frame.width.toFloat() / outWidth
        val stepY = frame.height.toFloat() / outHeight

        for (y in 0 until outHeight) {
            val sourceRow = (y * stepY).toInt().coerceIn(0, frame.height - 1)
            val rowStart = sourceRow * frame.rowStride
            val outRow = y * outWidth
            for (x in 0 until outWidth) {
                val sourceCol = (x * stepX).toInt().coerceIn(0, frame.width - 1)
                val index = rowStart + sourceCol
                // Defensive: a plane that ends exactly at its last real pixel is legal,
                // and a rounding error here would otherwise throw on the bottom-right.
                val luma = if (index < frame.bytes.size) frame.bytes[index].toInt() and 0xFF else 0
                pixels[outRow + x] = 0xFF000000.toInt() or (luma shl 16) or (luma shl 8) or luma
            }
        }

        val bitmap = reuse?.takeIf { !it.isRecycled && it.width == outWidth && it.height == outHeight }
            ?: Bitmap.createBitmap(outWidth, outHeight, Bitmap.Config.ARGB_8888)
        bitmap.setPixels(pixels, 0, outWidth, 0, 0, outWidth, outHeight)
        return bitmap to pixels
    }
}
