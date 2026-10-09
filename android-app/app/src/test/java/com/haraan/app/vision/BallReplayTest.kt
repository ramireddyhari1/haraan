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
    fun `the tracker on the real frames, and with a ball rolled down the pitch`() {
        OpenCvJvm.require()
        // The scene is still, so the stored burst is cycled for a longer run (keeps the
        // repository small): eight steps, 50 ms apart, each on a real frame's real noise.
        val stored = burst()
        val frames = (0 until 8).map { i -> (i * 50L) to stored[i % stored.size].second }
        val t0 = 0L
        for (withBall in listOf(false, true)) {
            val tracker = OpenCvBallTracker()
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
}
