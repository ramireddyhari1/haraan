package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs

/**
 * The curve behind the broadcast trail.
 *
 * The thing worth testing about a smoothed trajectory is not that it is smooth — it is
 * that it has not started making things up. A curve that drifts off the measured points,
 * or that bridges a stretch where the tracker had lost the ball, would be an overlay that
 * shows a flight nobody observed. Both are checked here, where the right answer is known.
 */
class TrailGeometryTest {

    private fun sighting(t: Long, x: Float, y: Float) =
        BallSighting(timestampMs = t, x = x, y = y, trackingConfidence = 0.8f, areaPx = 20)

    /** A plausible delivery: across the frame and dropping, 33ms apart. */
    private fun flight(count: Int, from: Long = 0L) = (0 until count).map { i ->
        sighting(
            t = from + i * 33L,
            x = 0.2f + 0.05f * i,
            y = 0.3f + 0.01f * i * i,
        )
    }

    @Test
    fun `the curve passes exactly through every measured point`() {
        val points = flight(6)
        val samples = TrailGeometry.sample(points)

        // Catmull-Rom is an interpolating spline, which is the whole reason it was chosen:
        // every control point appears on the curve rather than merely near it.
        for (point in points) {
            val hit = samples.any {
                abs(it.x - point.x) < 1e-4f && abs(it.y - point.y) < 1e-4f
            }
            assertTrue("measured point (${point.x}, ${point.y}) is not on the curve", hit)
        }
    }

    @Test
    fun `the curve starts and ends on the measurements, never past them`() {
        val points = flight(5)
        val samples = TrailGeometry.sample(points)

        assertEquals(points.first().x, samples.first().x, 1e-4f)
        assertEquals(points.first().y, samples.first().y, 1e-4f)
        assertEquals(points.last().x, samples.last().x, 1e-4f)
        assertEquals(points.last().y, samples.last().y, 1e-4f)
    }

    @Test
    fun `the curve does not wander outside the ground its points cover`() {
        val points = flight(8)
        val samples = TrailGeometry.sample(points)

        // A little overshoot is in the nature of a spline; a lot would mean the ribbon
        // swings somewhere the ball demonstrably never went. One per cent of the frame is
        // the most this is allowed to editorialise.
        val minX = points.minOf { it.x } - 0.01f
        val maxX = points.maxOf { it.x } + 0.01f
        val minY = points.minOf { it.y } - 0.01f
        val maxY = points.maxOf { it.y } + 0.01f

        samples.forEach {
            assertTrue("x ${it.x} outside [$minX, $maxX]", it.x in minX..maxX)
            assertTrue("y ${it.y} outside [$minY, $maxY]", it.y in minY..maxY)
        }
    }

    @Test
    fun `a straight line of points stays straight`() {
        val points = (0 until 5).map { sighting(it * 33L, 0.1f + 0.1f * it, 0.5f) }

        TrailGeometry.sample(points).forEach {
            assertEquals("a straight flight must not be bent by the smoothing", 0.5f, it.y, 1e-4f)
        }
    }

    @Test
    fun `a break in time splits the ribbon instead of bridging it`() {
        // Two flights, seconds apart. The tracker would call these separate; so must the
        // drawing, or the overlay sweeps a line across a gap in the evidence.
        val first = flight(4, from = 0L)
        val second = flight(4, from = 5_000L)

        val runs = TrailGeometry.runs(first + second)

        assertEquals(2, runs.size)
        assertEquals(4, runs[0].size)
        assertEquals(4, runs[1].size)
    }

    @Test
    fun `a jump no ball could make splits the ribbon`() {
        val points = listOf(
            sighting(0L, 0.20f, 0.50f),
            sighting(33L, 0.25f, 0.50f),
            // Across the frame in one frame interval: a different object.
            sighting(66L, 0.90f, 0.20f),
            sighting(99L, 0.92f, 0.22f),
        )

        val runs = TrailGeometry.runs(points)

        assertEquals(2, runs.size)
        assertEquals(2, runs[0].size)
        assertEquals(2, runs[1].size)
    }

    @Test
    fun `the split uses the tracker's own limits rather than its own numbers`() {
        // Exactly at the tracker's gap limit still counts as one flight; a millisecond
        // past it does not. Pinned so the drawing cannot drift away from the detector.
        val limit = OpenCvBallTracker.TRACK_GAP_LIMIT_MS
        val joined = listOf(sighting(0L, 0.3f, 0.3f), sighting(limit, 0.32f, 0.31f))
        val split = listOf(sighting(0L, 0.3f, 0.3f), sighting(limit + 1, 0.32f, 0.31f))

        assertEquals(1, TrailGeometry.runs(joined).size)
        assertEquals(2, TrailGeometry.runs(split).size)
    }

    @Test
    fun `an empty or single-point track produces nothing to stroke`() {
        assertTrue(TrailGeometry.runs(emptyList()).isEmpty())
        assertTrue(TrailGeometry.sampleAll(emptyList()).isEmpty())

        val single = TrailGeometry.sample(listOf(sighting(0L, 0.4f, 0.6f)))
        assertEquals(1, single.size)
        assertEquals(0.4f, single[0].x, 1e-4f)
    }

    @Test
    fun `the fade runs from nothing at the tail to full at the head`() {
        val samples = TrailGeometry.sample(flight(6))

        assertEquals(0f, samples.first().position, 1e-4f)
        assertEquals(1f, samples.last().position, 1e-4f)
        samples.zipWithNext { a, b ->
            assertTrue("the fade must not run backwards", b.position >= a.position)
        }
    }

    @Test
    fun `separate flights share the fade in proportion to their points`() {
        val short = flight(2, from = 0L)
        val long = flight(6, from = 5_000L)

        val runs = TrailGeometry.sampleAll(short + long)

        assertEquals(2, runs.size)
        // The two-point stub gets the dimmest quarter; the six-point flight owns the rest
        // and ends at full brightness.
        assertEquals(0f, runs[0].first().position, 1e-4f)
        assertEquals(0.25f, runs[0].last().position, 1e-3f)
        assertEquals(0.25f, runs[1].first().position, 1e-3f)
        assertEquals(1f, runs[1].last().position, 1e-4f)
    }

    @Test
    fun `sampling is dense enough to read as a curve`() {
        val points = flight(5)
        val samples = TrailGeometry.sample(points)

        // Four spans, eight samples each, plus the closing point.
        assertEquals(4 * TrailGeometry.SAMPLES_PER_SPAN + 1, samples.size)
    }
}
