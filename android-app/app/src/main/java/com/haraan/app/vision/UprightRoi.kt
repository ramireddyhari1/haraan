package com.haraan.app.vision

import kotlin.math.ceil
import kotlin.math.floor
import kotlin.math.max

/**
 * A small rectangle of the UPRIGHT picture, read straight out of the sensor's luma plane.
 *
 * WHY NOT CROP THE ANALYSIS FRAME. The stump detector's whole-frame pass works on a copy
 * scaled down to 960 wide and blurred, which is right for finding candidates and fatal for
 * measuring them: a far stump is a pixel and a half across at the sensor's own resolution,
 * and after the scale and the blur the three of a wicket are one smudge. This reads the
 * region the wicket is in at FULL sensor resolution — no resize, no blur — so the three
 * bars survive to be counted.
 *
 * It is small, so it is cheap: a wicket's surroundings are a few thousand pixels, and doing
 * this in Kotlin rather than OpenCV keeps the arithmetic that decides "is this a wicket"
 * runnable in a unit test.
 *
 * COORDINATES. ROI pixel `i` covers `[i, i + 1)` in ROI units; one ROI unit is [step]
 * upright pixels; upright pixel `X` covers `[X, X + 1)`; normalised x is `X / frameWidth`.
 * No half-pixel offsets anywhere, which is the convention every normalised point in this
 * package already uses.
 */
class UprightRoi(
    val pixels: FloatArray,
    val width: Int,
    val height: Int,
    /** Upright-frame pixel at the ROI's top-left corner. */
    val originX: Int,
    val originY: Int,
    /** Upright pixels per ROI pixel. 1 means full resolution. */
    val step: Int,
    /** The whole upright frame, in pixels. */
    val frameWidth: Int,
    val frameHeight: Int,
) {
    fun at(x: Int, y: Int): Float = pixels[y * width + x]

    /** Bilinear-in-x, nearest-in-y read; x clamped to the ROI. */
    fun sampleX(x: Double, y: Int): Float {
        val cx = x.coerceIn(0.0, (width - 1).toDouble())
        val i = cx.toInt().coerceAtMost(width - 2).coerceAtLeast(0)
        if (width < 2) return at(0, y)
        val f = (cx - i).toFloat()
        val row = y * width
        return pixels[row + i] * (1f - f) + pixels[row + i + 1] * f
    }

    fun toFrameX(x: Double): Double = (originX + x * step) / frameWidth
    fun toFrameY(y: Double): Double = (originY + y * step) / frameHeight
    fun fromFrameX(u: Double): Double = (u * frameWidth - originX) / step
    fun fromFrameY(v: Double): Double = (v * frameHeight - originY) / step

    /** One ROI pixel, as a fraction of the frame's width. */
    val pixelFw: Double get() = step.toDouble() / frameWidth

    companion object {

        /**
         * Read a normalised upright rectangle out of a sensor-oriented luma plane.
         *
         * @param rotationDegrees the turn that makes the sensor frame upright, exactly as
         *   `ImageInfo.rotationDegrees` gives it and every engine here applies it with
         *   `Core.rotate`: 90 is a quarter turn CLOCKWISE.
         * @param maxSide the ROI's longer side is decimated (by box averaging) until it fits.
         *   Decimation is only ever needed for a NEAR wicket, which has pixels to spare.
         *
         * Null when the rectangle misses the frame or the buffer is short.
         */
        fun extract(
            luma: ByteArray,
            width: Int,
            height: Int,
            rowStride: Int,
            rotationDegrees: Int,
            left: Double,
            top: Double,
            right: Double,
            bottom: Double,
            maxSide: Int = 192,
        ): UprightRoi? {
            if (width <= 0 || height <= 0 || rowStride < width) return null
            val turn = ((rotationDegrees % 360) + 360) % 360
            val sideways = turn == 90 || turn == 270
            val upW = if (sideways) height else width
            val upH = if (sideways) width else height

            val x0 = floor(left * upW).toInt().coerceIn(0, upW)
            val x1 = ceil(right * upW).toInt().coerceIn(0, upW)
            val y0 = floor(top * upH).toInt().coerceIn(0, upH)
            val y1 = ceil(bottom * upH).toInt().coerceIn(0, upH)
            val roiW = x1 - x0
            val roiH = y1 - y0
            if (roiW < 4 || roiH < 4) return null

            val step = max(1, ceil(max(roiW, roiH).toDouble() / maxSide.coerceAtLeast(8)).toInt())
            val outW = roiW / step
            val outH = roiH / step
            if (outW < 4 || outH < 4) return null

            // The last byte the plane can be asked for; a short buffer is refused, never
            // read past — see the same refusal in the detectors.
            val lastIndex = (height - 1) * rowStride + (width - 1)
            if (lastIndex >= luma.size) return null

            // Box average for step 1 and 2; a 2x2 sub-sample beyond that. A near wicket
            // has tens of pixels per stump and loses nothing to the shortcut.
            val offsets = when {
                step == 1 -> intArrayOf(0)
                step == 2 -> intArrayOf(0, 1)
                else -> intArrayOf(step / 4, (3 * step) / 4)
            }
            val weight = 1f / (offsets.size * offsets.size)

            val out = FloatArray(outW * outH)
            for (j in 0 until outH) {
                for (i in 0 until outW) {
                    var sum = 0f
                    for (oy in offsets) {
                        val uy = y0 + j * step + oy
                        for (ox in offsets) {
                            val ux = x0 + i * step + ox
                            // Upright (ux, uy) back to the sensor. These are the exact
                            // inverses of Core.rotate's three turns.
                            val sx: Int
                            val sy: Int
                            when (turn) {
                                90 -> { sx = uy; sy = height - 1 - ux }
                                180 -> { sx = width - 1 - ux; sy = height - 1 - uy }
                                270 -> { sx = width - 1 - uy; sy = ux }
                                else -> { sx = ux; sy = uy }
                            }
                            sum += (luma[sy * rowStride + sx].toInt() and 0xFF).toFloat()
                        }
                    }
                    out[j * outW + i] = sum * weight
                }
            }
            return UprightRoi(out, outW, outH, x0, y0, step, upW, upH)
        }
    }
}
