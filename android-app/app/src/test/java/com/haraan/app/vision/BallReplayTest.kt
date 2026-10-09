package com.haraan.app.vision

import org.junit.Assume
import org.junit.Test
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc
import java.io.File

/**
 * The ball tracker on REAL consecutive frames from a phone standing still in front of the
 * stumps (Realme RMX3933, backyard, 2026-10-09) — as filmed, and with a ball drawn into
 * them rolling down the pitch.
 */
class BallReplayTest {

    private fun burst(): List<Pair<Long, HyuvFrame>> {
        val dir = File("src/test/resources/burst")
        val files = dir.listFiles { f -> f.name.endsWith(".hyuv") }.orEmpty().sortedBy { it.name }
        Assume.assumeTrue("no burst frames", files.size >= 4)
        return files.map { it.name.removePrefix("frame-").removeSuffix(".hyuv").toLong() to HyuvFrame.read(it) }
    }

    /** Draw a ball into a copy of the frame's Y plane, at UPRIGHT pixel (ux, uy). */
    private fun withBall(f: HyuvFrame, ux: Double, uy: Double, radius: Double, grey: Int): ByteArray {
        val out = f.y.copyOf()
        val upH = f.width // rotation 90: upright height is the sensor width
        for (dy in -radius.toInt() - 1..radius.toInt() + 1) for (dx in -radius.toInt() - 1..radius.toInt() + 1) {
            val px = ux + dx
            val py = uy + dy
            val d = kotlin.math.hypot(dx.toDouble(), dy.toDouble())
            val cover = (radius + 0.5 - d).coerceIn(0.0, 1.0)
            if (cover <= 0) continue
            // Upright (px, py) -> sensor for a 90° turn: sx = py, sy = H - 1 - px.
            val sx = py.toInt()
            val sy = f.height - 1 - px.toInt()
            if (sx !in 0 until upH || sy !in 0 until f.height) continue
            val i = sy * f.yRowStride + sx
            val under = out[i].toInt() and 0xFF
            out[i] = (under + (grey - under) * cover).toInt().coerceIn(0, 255).toByte()
        }
        return out
    }

    @Test
    fun `what the frame difference holds on a still phone`() {
        OpenCvJvm.require()
        val frames = burst()
        var prev: Mat? = null
        for ((ts, f) in frames) {
            val full = Mat(f.height, f.width, CvType.CV_8UC1)
            val packed = ByteArray(f.width * f.height)
            for (r in 0 until f.height) System.arraycopy(f.y, r * f.yRowStride, packed, r * f.width, f.width)
            full.put(0, 0, packed)
            val small = Mat()
            Imgproc.resize(full, small, Size(480.0, f.height * 480.0 / f.width), 0.0, 0.0, Imgproc.INTER_AREA)
            val blurred = Mat()
            Imgproc.GaussianBlur(small, blurred, Size(5.0, 5.0), 0.0)
            prev?.let { p ->
                val diff = Mat()
                Core.absdiff(p, blurred, diff)
                val mask = Mat()
                val otsu = Imgproc.threshold(diff, mask, 0.0, 255.0, Imgproc.THRESH_BINARY + Imgproc.THRESH_OTSU)
                val moving = Core.countNonZero(mask).toDouble() / (mask.rows() * mask.cols())
                val bytes = ByteArray(diff.rows() * diff.cols()).also { diff.get(0, 0, it) }
                val sorted = bytes.map { it.toInt() and 0xFF }.sorted()
                fun pct(q: Double) = sorted[((sorted.size - 1) * q).toInt()]
                println(
                    "t=$ts otsu=%.0f moving=%.1f%% diff p50=${pct(0.5)} p90=${pct(0.9)} p99=${pct(0.99)} p99.9=${pct(0.999)} max=${sorted.last()}"
                        .format(otsu, moving * 100),
                )
            }
            prev = blurred
            small.release()
            full.release()
        }
    }

    @Test
    fun `the tracker on the real frames, and with a ball rolled down the pitch`() = rolled(1.0)

    /**
     * The same at the finer analysis the camera screen asks for: the noise of a still scene
     * must still never pass for motion, and the ball must still be followed.
     */
    @Test
    fun `at the finer 720-wide analysis too`() = rolled(OpenCvBallTrackerDetail.FINE)

    private fun rolled(detail: Double) {
        OpenCvJvm.require()
        // The scene is still, so the stored burst is cycled for a longer run (keeps the
        // repository small): eight steps, 50 ms apart, each on a real frame's real noise.
        val stored = burst()
        val frames = (0 until 8).map { i -> (i * 50L) to stored[i % stored.size].second }
        val t0 = 0L
        for (withBall in listOf(false, true)) {
            val tracker = OpenCvBallTracker().also { it.setDetail(detail) }
            var seen = 0
            frames.forEachIndexed { i, (ts, f) ->
                // Rolling towards the camera from just in front of the stumps.
                val y = if (withBall) withBall(f, 372.0 + i * 6, 930.0 + i * 38, 7.0, 235) else f.y
                val s = tracker.onFrame(y, f.width, f.height, f.yRowStride, f.rotation, ts - t0)
                if (s != null) seen++
                println("ball=$withBall frame=$i sighting=${s?.let { "x=%.3f y=%.3f".format(it.x, it.y) }}")
            }
            val d = tracker.diagnostics()
            println(
                "ball=$withBall seen=$seen points=${tracker.track().size} rejMotion=${d.rejectedGlobalMotion} " +
                    "size=${d.rejectedSize} shape=${d.rejectedShape} traj=${d.rejectedTrajectory} " +
                    "still=${d.rejectedStationary} cluster=${d.rejectedCluster} state=${d.trackingState}",
            )
            // A phone standing still is not a panning camera: no frame may be thrown away as
            // camera motion (all of them were, on the device, before the motion floor).
            org.junit.Assert.assertEquals("frames rejected as camera motion", 0, d.rejectedGlobalMotion)
            if (withBall) {
                org.junit.Assert.assertTrue("ball track points ${tracker.track().size}", tracker.track().size >= 4)
            } else {
                org.junit.Assert.assertEquals("sightings in a still scene", 0, seen)
            }
        }
    }

    /**
     * THE TRAIL MUST NOT END AT THE BOUNCE. Seen on the phone: the red line followed the
     * ball down towards the pitch and stopped there. At the bounce the ball turns sharply in
     * the picture — down towards the pitch, then flatter and slower on to the stumps — and
     * the next sighting lands well off a straight-line prediction. Every sighting after it
     * was thrown away and the track died with half the delivery missing.
     */
    @Test
    fun `the track carries on through the bounce`() {
        OpenCvJvm.require()
        val stored = burst()
        for (detail in listOf(1.0, OpenCvBallTrackerDetail.FINE)) {
            val tracker = OpenCvBallTracker().also { it.setDetail(detail) }
            // Warm the empty scene, then 7 frames falling to the pitch and 6 after it.
            var ux = 250.0
            var uy = 380.0
            val path = ArrayList<Pair<Double, Double>>()
            repeat(4) { path.add(Double.NaN to Double.NaN) }
            repeat(7) { path.add(ux to uy); ux += 8.0; uy += 60.0 }
            repeat(6) { path.add(ux to uy); ux += 12.0; uy -= 22.0 }
            var after = 0
            path.forEachIndexed { i, (x, y) ->
                val f = stored[i % stored.size].second
                val luma = if (x.isNaN()) f.y else withBall(f, x, y, 8.0, 235)
                val s = tracker.onFrame(luma, f.width, f.height, f.yRowStride, f.rotation, i * 33L)
                if (s != null && i >= 4 + 7) after++
            }
            val d = tracker.diagnostics()
            println("detail=$detail points=${tracker.track().size} afterBounce=$after traj=${d.rejectedTrajectory} still=${d.rejectedStationary} size=${d.rejectedSize} shape=${d.rejectedShape} cluster=${d.rejectedCluster} state=${d.trackingState}")
            org.junit.Assert.assertTrue("detail $detail: sightings after the bounce $after", after >= 4)
        }
    }
}

private object OpenCvBallTrackerDetail {
    const val FINE = 1.5
}
