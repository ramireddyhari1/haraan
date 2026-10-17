package com.haraan.app.vision

import android.graphics.Bitmap
import android.media.Image
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.Rect
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc

/**
 * A decoded frame straight into what the tracker reads, in native code, with no copy.
 *
 * The first cut sampled every 4K frame pixel by pixel in Kotlin after copying its planes out
 * — 25 MB a frame for 10-bit — and that one step was 40 of the 70 seconds a 5-second clip
 * took on a real phone. Here OpenCV wraps the decoder's own buffers, turns, crops and
 * samples them down to the camera's analysis frame — sideways 1280x720 luma and a
 * 640x360 Cb plane, exactly what the live camera hands the tracker — and builds the small
 * upright replay picture only when one is asked for.
 *
 * 8-bit and 16-bit (10-bit video) planes, planar or interleaved chroma. Studio-range samples
 * are stretched to full range, as the live camera's are.
 */
internal class FastFrameSampler {
    /*
     * OpenCV's natives before the first Mat below. The detectors load them as they are made,
     * but the clip analysis makes this first: in a fresh process — Track a clip opened before
     * the camera ever was — every Mat threw UnsatisfiedLinkError and the app closed.
     */
    init {
        check(OpenCvBallTracker.ensureLoaded()) { "OpenCV native library unavailable" }
    }

    private val small = Mat()
    private val channel = Mat()
    private val narrowed = Mat()
    private val sensorY = Mat()
    private val sensorCb = Mat()
    private val sensorCr = Mat()

    /**
     * Fills [y] (SENSOR_W x SENSOR_H) and [u] (half that) from [image], stored turned [rotation].
     *
     * Shrunk first, in the stored picture's own orientation and bit depth, then narrowed to 8
     * bits, stretched to full range and turned — all on the small picture. Turning and
     * converting the whole 4K plane first, as the first cut did, was ten milliseconds a frame.
     */
    fun sensorYuv(image: Image, rotation: Int, y: ByteArray, u: ByteArray) {
        val crop = image.cropRect
        val planes = image.planes
        val wide = planes[0].pixelStride == 2
        // Luma.
        toSensor(planes[0], crop.left, crop.top, crop.width(), crop.height(), wide, 1, rotation, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, LUMA_ALPHA, LUMA_BETA, sensorY)
        sensorY.get(0, 0, y)
        // Cb, at half resolution.
        chromaToSensor(planes[1], crop, wide, rotation, sensorCb)
        sensorCb.get(0, 0, u)
        lastImage = image
        lastRotation = rotation
        lastWide = wide
    }

    private var lastImage: Image? = null
    private var lastRotation = 0
    private var lastWide = false

    /**
     * The small upright picture of the frame last passed to [sensorYuv], for the replay and
     * the progress preview. Built only when asked: the replay needs a fraction of the frames.
     */
    fun thumbnail(w: Int, h: Int): Bitmap = thumbnailParts(w, h).toBitmap()

    /**
     * The same picture's three planes already shrunk to [w]x[h] — the cheap part, which
     * needs the decoder's frame — for [ThumbParts.toBitmap] to finish on any thread.
     */
    fun thumbnailParts(w: Int, h: Int): ThumbParts {
        val image = lastImage ?: error("no frame")
        chromaToSensor(image.planes[2], image.cropRect, lastWide, lastRotation, sensorCr)
        val small = Size(h.toDouble(), w.toDouble()) // sideways until turned upright
        val ys = Mat(); val cbs = Mat(); val crs = Mat()
        Imgproc.resize(sensorY, ys, small, 0.0, 0.0, Imgproc.INTER_AREA)
        Imgproc.resize(sensorCb, cbs, small, 0.0, 0.0, Imgproc.INTER_LINEAR)
        Imgproc.resize(sensorCr, crs, small, 0.0, 0.0, Imgproc.INTER_LINEAR)
        return ThumbParts(ys, cbs, crs, w, h)
    }

    /** A replay picture's planes, sensor-sideways, owned until [toBitmap] releases them. */
    class ThumbParts(private val y: Mat, private val cb: Mat, private val cr: Mat, private val w: Int, private val h: Int) {
        fun toBitmap(): Bitmap {
            val ycrcb = Mat()
            Core.merge(listOf(y, cr, cb), ycrcb)
            val rgb = Mat()
            Imgproc.cvtColor(ycrcb, rgb, Imgproc.COLOR_YCrCb2RGB)
            // Sensor-sideways back to upright: a clockwise quarter turn.
            val up = Mat()
            Core.rotate(rgb, up, Core.ROTATE_90_CLOCKWISE)
            val rgba = Mat()
            Imgproc.cvtColor(up, rgba, Imgproc.COLOR_RGB2RGBA)
            val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
            org.opencv.android.Utils.matToBitmap(rgba, bmp)
            listOf(y, cb, cr, ycrcb, rgb, up, rgba).forEach { it.release() }
            return bmp
        }
    }

    /** A plane (or its first channel) wrapped without copying, cropped, at 8 bits. */
    private fun plane(p: Image.Plane, x: Int, y: Int, w: Int, h: Int, wide: Boolean, channels: Int, into: Mat) {
        val whole = wrap(p, wide, channels, y + h)
        val roi = whole.submat(Rect(x, y, minOf(w, whole.cols() - x), minOf(h, whole.rows() - y)))
        val one = if (channels == 2) Mat().also { Core.extractChannel(roi, it, 0) } else roi
        if (wide) one.convertTo(into, CvType.CV_8U, 1.0 / 256.0) else one.copyTo(into)
        if (one !== roi) one.release()
        roi.release()
        whole.release()
    }

    /** The decoder's plane as a Mat over its own buffer, [rows] tall at most. */
    private fun wrap(p: Image.Plane, wide: Boolean, channels: Int, rows: Int): Mat {
        val bytes = if (wide) 2 else 1
        val type = when {
            wide && channels == 2 -> CvType.CV_16UC2
            wide -> CvType.CV_16UC1
            channels == 2 -> CvType.CV_8UC2
            else -> CvType.CV_8UC1
        }
        val buf = p.buffer
        val cols = p.rowStride / (bytes * channels)
        // Only whole rows the buffer actually holds: the last row may be short of the stride.
        return Mat(minOf(rows, buf.capacity() / p.rowStride), cols, type, buf, p.rowStride.toLong())
    }

    /** Cb or Cr into the sensor frame at half resolution: planar, or the first of an interleaved pair. */
    private fun chromaToSensor(p: Image.Plane, crop: android.graphics.Rect, wide: Boolean, rotation: Int, out: Mat) {
        val interleaved = p.pixelStride == 2 * (if (wide) 2 else 1)
        toSensor(
            p, crop.left / 2, crop.top / 2, (crop.width() + 1) / 2, (crop.height() + 1) / 2, wide,
            if (interleaved) 2 else 1, rotation, ClipFrames.SENSOR_W / 2, ClipFrames.SENSOR_H / 2, CHROMA_ALPHA, CHROMA_BETA, out,
        )
    }

    /**
     * Stored picture → the camera's sideways frame: the part of [x],[y],[w],[h] that is the
     * centred 16:9 once turned by [rotation] and a quarter-turn back to the sensor's way
     * round, sampled bilinearly to [outW]x[outH], scaled by [alpha] and [beta] to full-range
     * 8 bits — the same place for every pixel as [ClipFrames.upright] then toSensorYuv.
     */
    private fun toSensor(
        p: Image.Plane, x: Int, y: Int, w: Int, h: Int, wide: Boolean, channels: Int, rotation: Int,
        outW: Int, outH: Int, alpha: Double, beta: Double, out: Mat,
    ) {
        val net = ((rotation - 90) % 360 + 360) % 360
        val sideways = net % 180 != 0
        val whole = wrap(p, wide, channels, y + h)
        var cx = x
        var cy = y
        var cw = minOf(w, whole.cols() - x)
        var ch = minOf(h, whole.rows() - y)
        // 16:9 after the turn is 9:16 before it when the turn is a quarter.
        val target = if (sideways) outH.toDouble() / outW else outW.toDouble() / outH
        val aspect = cw.toDouble() / ch
        if (kotlin.math.abs(aspect - target) > 0.01) {
            if (aspect > target) {
                val nw = (ch * target).toInt(); cx += (cw - nw) / 2; cw = nw
            } else {
                val nh = (cw / target).toInt(); cy += (ch - nh) / 2; ch = nh
            }
        }
        val roi = whole.submat(Rect(cx, cy, cw, ch))
        // Bilinear, not area-averaged: the far stumps are ten pixels wide here, and averaging
        // read them 7% narrower than every other path, which moved the whole 3D fit.
        val size = if (sideways) Size(outH.toDouble(), outW.toDouble()) else Size(outW.toDouble(), outH.toDouble())
        Imgproc.resize(roi, small, size, 0.0, 0.0, Imgproc.INTER_LINEAR)
        roi.release()
        whole.release()
        val one = if (channels == 2) channel.also { Core.extractChannel(small, it, 0) } else small
        val scale = if (wide) 1.0 / 256.0 else 1.0
        when (net) {
            90 -> { one.convertTo(narrowed, CvType.CV_8U, alpha * scale, beta); Core.rotate(narrowed, out, Core.ROTATE_90_CLOCKWISE) }
            180 -> { one.convertTo(narrowed, CvType.CV_8U, alpha * scale, beta); Core.rotate(narrowed, out, Core.ROTATE_180) }
            270 -> { one.convertTo(narrowed, CvType.CV_8U, alpha * scale, beta); Core.rotate(narrowed, out, Core.ROTATE_90_COUNTERCLOCKWISE) }
            else -> one.convertTo(out, CvType.CV_8U, alpha * scale, beta)
        }
    }

    /**
     * THE FAR STUMPS, MEASURED IN THE FULL-RESOLUTION FRAME.
     *
     * At the analysis size the stumps at the far end are about nine pixels across, and a
     * pixel either way is a tenth of the speed: on a 4K clip the 720p lock read them 11%
     * narrower than they are, and every change to how frames were shrunk moved the answer.
     * Here the band just above their base is cut from the decoder's own full-size planes —
     * about 30 pixels across at 4K — and the yellow columns in it give the outer stumps'
     * centres to a fraction of a pixel.
     *
     * [baseUx], [baseUy] and [spanFw] are the lock's, in the upright analysis frame; the
     * answer is the outer stumps' centre-to-centre span in the same unit, or null when the
     * band does not show them cleanly (a batter in front, stumps not coloured).
     */
    fun measureFarStumps(image: Image, rotation: Int, baseUx: Double, baseUy: Double, spanFw: Double): Double? {
        val crop = image.cropRect
        val planes = image.planes
        val wide = planes[0].pixelStride == 2
        val sw = crop.width()
        val sh = crop.height()
        val sideways = rotation % 180 != 0
        val wu = if (sideways) sh else sw
        val hu = if (sideways) sw else sh
        // The upright picture as the analysis frame crops it to 9:16.
        val target = ClipFrames.UPRIGHT_W.toDouble() / ClipFrames.UPRIGHT_H
        val cw: Double
        val ch: Double
        if (wu.toDouble() / hu > target) { ch = hu.toDouble(); cw = hu * target } else { cw = wu.toDouble(); ch = wu / target }
        val x0 = (wu - cw) / 2
        val y0 = (hu - ch) / 2
        val spanPx = spanFw * cw
        if (spanPx < 6) return null
        val tall = spanPx * (0.711 / 0.1936)
        val cx = x0 + baseUx * cw
        val cy = y0 + baseUy * ch
        // A band from 15% to 60% of the stumps' height above their base: above any base
        // plate, below the bails.
        val ux0 = (cx - 2.5 * spanPx).toInt().coerceIn(0, wu - 2)
        val ux1 = (cx + 2.5 * spanPx).toInt().coerceIn(ux0 + 2, wu)
        val uy0 = (cy - 0.6 * tall).toInt().coerceIn(0, hu - 2)
        val uy1 = (cy - 0.15 * tall).toInt().coerceIn(uy0 + 2, hu)
        // That band in the stored picture, which is turned [rotation] from upright.
        val sx0: Int; val sx1: Int; val sy0: Int; val sy1: Int
        when (rotation) {
            90 -> { sx0 = uy0; sx1 = uy1; sy0 = sh - ux1; sy1 = sh - ux0 }
            180 -> { sx0 = sw - ux1; sx1 = sw - ux0; sy0 = sh - uy1; sy1 = sh - uy0 }
            270 -> { sx0 = sw - uy1; sx1 = sw - uy0; sy0 = ux0; sy1 = ux1 }
            else -> { sx0 = ux0; sx1 = ux1; sy0 = uy0; sy1 = uy1 }
        }
        // Even, so the half-resolution chroma lines up with the luma.
        val ex0 = (crop.left + sx0) and 1.inv()
        val ey0 = (crop.top + sy0) and 1.inv()
        val ew = ((sx1 - sx0) and 1.inv()).coerceAtLeast(2)
        val eh = ((sy1 - sy0) and 1.inv()).coerceAtLeast(2)
        val cb = Mat()
        val upCb = Mat()
        try {
            val interleaved = planes[1].pixelStride == 2 * (if (wide) 2 else 1)
            plane(planes[1], ex0 / 2, ey0 / 2, ew / 2, eh / 2, wide, if (interleaved) 2 else 1, cb)
            turnUpright(cb, rotation, upCb)
            // Back to full size across, so the answer is in full-size pixels.
            Imgproc.resize(upCb, upCb, Size(upCb.cols() * 2.0, upCb.rows().toDouble()), 0.0, 0.0, Imgproc.INTER_LINEAR)
            val w = upCb.cols()
            val h = upCb.rows()
            if (w < 8 || h < 2) return null
            val cbBytes = ByteArray(w * h)
            upCb.get(0, 0, cbBytes)
            // Yellow sits far below the picture's usual blue-difference; the threshold follows
            // this band's own background — grass, net, a batter's whites.
            val values = IntArray(cbBytes.size) { cbBytes[it].toInt() and 0xFF }
            val sorted = values.sortedArray()
            val threshold = minOf(110, sorted[sorted.size / 2] - 20)
            val profile = DoubleArray(w)
            for (x in 0 until w) {
                var n = 0
                for (y in 0 until h) if (values[y * w + x] < threshold) n++
                profile[x] = n.toDouble() / h
            }
            // Runs of mostly-yellow columns: one per stump.
            val centres = ArrayList<Double>()
            var x = 0
            while (x < w) {
                if (profile[x] > 0.5) {
                    var sum = 0.0
                    var weight = 0.0
                    val start = x
                    while (x < w && profile[x] > 0.5) { sum += x * profile[x]; weight += profile[x]; x++ }
                    if (x - start < 0.6 * spanPx) centres += sum / weight
                } else {
                    x++
                }
            }
            if (centres.size !in 2..4) return null
            val measured = centres.last() - centres.first()
            if (measured < 0.6 * spanPx || measured > 1.6 * spanPx) return null
            return measured / cw
        } finally {
            cb.release(); upCb.release()
        }
    }

    private fun turnUpright(src: Mat, rotation: Int, out: Mat) {
        when (rotation) {
            90 -> Core.rotate(src, out, Core.ROTATE_90_CLOCKWISE)
            180 -> Core.rotate(src, out, Core.ROTATE_180)
            270 -> Core.rotate(src, out, Core.ROTATE_90_COUNTERCLOCKWISE)
            else -> src.copyTo(out)
        }
    }

    fun release() {
        listOf(small, channel, narrowed, sensorY, sensorCb, sensorCr).forEach { runCatching { it.release() } }
        lastImage = null
    }

    private companion object {
        /** Studio-range samples stretched to full range, as the live camera's are. */
        const val LUMA_ALPHA = 255.0 / 219.0
        const val LUMA_BETA = -16.0 * 255.0 / 219.0
        const val CHROMA_ALPHA = 255.0 / 224.0
        const val CHROMA_BETA = 128.0 - 128.0 * 255.0 / 224.0
    }
}
