package com.haraan.app.vision

import android.graphics.Bitmap
import android.graphics.Matrix
import android.media.MediaMetadataRetriever
import android.os.Build
import android.util.Log
import java.io.File

/**
 * THE BALL AGAIN, FROM THE SAVED CLIP, AT EVERY FRAME IT HAS.
 *
 * The live analyser looks at about thirty frames a second — all a phone can afford while it
 * is also filming, finding the stumps and drawing. That sees the first half of a delivery
 * from behind the bowler and loses it as it falls towards the far end, where it pitches. On
 * a 120 fps net clip (2026-10-11) analysing every frame instead found 9 deliveries of 9,
 * with the bounce in them; thirty a second found 3. Fulltrack-style apps answer a second or
 * two after the ball for this reason.
 *
 * So once the clip is saved, the stretch of it around the live flight is decoded and run
 * through the same tracker, with the same corridor, at the clip's own rate; the result
 * replaces the live flight when it saw more of the ball. Timestamps are put back on the
 * camera's clock through the clip's first analysed frame, so everything downstream — the
 * 3D fit, the path drawn on the replay — reads it exactly as it reads a live flight.
 */
class ClipFlightAnalyzer {
    class Result(val track: List<BallSighting>, val fps: Double, val framesAnalysed: Int)

    /**
     * Tracks the ball in [clip] between [fromSensorMs] and [toSensorMs] (camera clock), where
     * the clip's frame 0 was at [clipStartSensorMs]. [corridor] is the delivery corridor
     * (left, top, right, bottom, targetX, targetY), as given to the live tracker. Null when
     * the clip cannot be read or nothing was found. Blocking; call off the main thread.
     */
    fun analyse(
        clip: File,
        clipStartSensorMs: Double,
        fromSensorMs: Double,
        toSensorMs: Double,
        corridor: FloatArray?,
        wicket: WicketLock?,
        uprightAspect: Float,
    ): Result? {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P || !clip.isFile) return null
        val tracker = OpenCvBallTracker()
        if (!tracker.available) return null
        val retriever = MediaMetadataRetriever()
        try {
            retriever.setDataSource(clip.absolutePath)
            val count = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_FRAME_COUNT)?.toIntOrNull() ?: return null
            val durationMs = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: return null
            if (count <= 0 || durationMs <= 0) return null
            val rotation = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: 0
            val fps = count * 1000.0 / durationMs
            val first = ((fromSensorMs - clipStartSensorMs) * fps / 1000.0).toInt().coerceIn(0, count - 1)
            val last = ((toSensorMs - clipStartSensorMs) * fps / 1000.0).toInt().coerceIn(first, count - 1)
            if (corridor != null && corridor.size >= 6) {
                tracker.setCorridor(corridor[0], corridor[1], corridor[2], corridor[3], Point2(corridor[4].toDouble(), corridor[5].toDouble()))
            }
            val y = ByteArray(ClipFrames.SENSOR_W * ClipFrames.SENSOR_H)
            val u = ByteArray(ClipFrames.SENSOR_W / 2 * (ClipFrames.SENSOR_H / 2))
            val pixels = IntArray(ClipFrames.UPRIGHT_W * ClipFrames.UPRIGHT_H)
            var analysed = 0
            var at = first
            while (at <= last) {
                val n = minOf(BATCH, last - at + 1)
                val frames = retriever.getFramesAtIndex(at, n)
                for ((k, raw) in frames.withIndex()) {
                    val up = ClipFrames.upright(raw, rotation, uprightAspect)
                    up.getPixels(pixels, 0, ClipFrames.UPRIGHT_W, 0, 0, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
                    if (up !== raw) up.recycle()
                    raw.recycle()
                    ClipFrames.toSensorYuv(pixels, y, u)
                    val ts = (clipStartSensorMs + (at + k) * 1000.0 / fps).toLong()
                    tracker.onFrame(y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, ts)
                    analysed++
                    val track = tracker.track()
                    // Done as soon as the flight is: what follows is the batter and the bat.
                    if (wicket != null && FlightEnd.check(track, ts, uprightAspect, wicket) != null) {
                        return track.takeIf { it.size >= FlightEnd.MIN_FLIGHT_POINTS }?.let { Result(it, fps, analysed) }
                    }
                }
                at += n
            }
            return tracker.track().takeIf { it.size >= FlightEnd.MIN_FLIGHT_POINTS }?.let { Result(it, fps, analysed) }
        } catch (t: Throwable) {
            runCatching { Log.w(TAG, "clip analysis failed", t) }
            return null
        } finally {
            runCatching { retriever.release() }
            tracker.release()
        }
    }

    companion object {
        private const val TAG = "ClipFlightAnalyzer"

        /** Frames decoded per call: one sequential decode instead of a seek per frame. */
        private const val BATCH = 16

        /** Decoded from this long before the live flight's first sighting... */
        const val LEAD_MS = 250L

        /** ...to this long after its last: the far half the live analyser missed. */
        const val TAIL_MS = 700L
    }
}

/**
 * A clip frame as the camera's analysis stream delivers it: sensor-sideways 1280x720 luma
 * and a half-resolution U (Cb) plane, rotation 90. Shared by the after-the-ball clip
 * analysis and the debug net replay, so both see what the live chain sees.
 */
object ClipFrames {
    const val UPRIGHT_W = 720
    const val UPRIGHT_H = 1280
    const val SENSOR_W = 1280
    const val SENSOR_H = 720

    /**
     * [raw] turned upright (when the decoder left it sideways) and scaled to 720x1280. A
     * frame of a different shape is centre-cropped to the analysis stream's aspect first, so
     * normalised positions mean the same place in both.
     */
    fun upright(raw: Bitmap, rotation: Int, uprightAspect: Float): Bitmap {
        var b = raw
        val wantPortrait = uprightAspect < 1f
        if ((b.width > b.height) == wantPortrait && rotation % 180 != 0) {
            b = Bitmap.createBitmap(b, 0, 0, b.width, b.height, Matrix().apply { postRotate(rotation.toFloat()) }, true)
        }
        val target = UPRIGHT_W.toFloat() / UPRIGHT_H
        val aspect = b.width.toFloat() / b.height
        if (kotlin.math.abs(aspect - target) > 0.02f) {
            val cropped = if (aspect > target) {
                val w = (b.height * target).toInt()
                Bitmap.createBitmap(b, (b.width - w) / 2, 0, w, b.height)
            } else {
                val h = (b.width / target).toInt()
                Bitmap.createBitmap(b, 0, (b.height - h) / 2, b.width, h)
            }
            if (b !== raw) b.recycle()
            b = cropped
        }
        val scaled = Bitmap.createScaledBitmap(b, UPRIGHT_W, UPRIGHT_H, true)
        if (b !== raw && b !== scaled) b.recycle()
        return scaled
    }

    /** Upright ARGB pixels into the camera's sideways luma and half-resolution U (Cb) plane. */
    fun toSensorYuv(px: IntArray, y: ByteArray, u: ByteArray) {
        // Upright (x, y) is sensor (sx = y, sy = SENSOR_H - 1 - x), as a 90 degree turn.
        for (uy in 0 until UPRIGHT_H) {
            val row = uy * UPRIGHT_W
            for (ux in 0 until UPRIGHT_W) {
                val p = px[row + ux]
                val r = (p shr 16) and 0xFF
                val g = (p shr 8) and 0xFF
                val b = p and 0xFF
                val sx = uy
                val sy = SENSOR_H - 1 - ux
                y[sy * SENSOR_W + sx] = ((77 * r + 150 * g + 29 * b) shr 8).toByte()
                if ((sx and 1) == 0 && (sy and 1) == 0) {
                    val cb = 128 + ((-43 * r - 85 * g + 128 * b) shr 8)
                    u[(sy / 2) * (SENSOR_W / 2) + sx / 2] = cb.coerceIn(0, 255).toByte()
                }
            }
        }
    }
}
