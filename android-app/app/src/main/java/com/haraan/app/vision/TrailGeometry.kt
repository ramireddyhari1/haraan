package com.haraan.app.vision

import kotlin.math.sqrt

/**
 * One point on the drawn ribbon.
 *
 * [position] is 0 at the oldest end of the whole trail and 1 at the newest, which is what
 * the renderer fades and tapers against. It is a position along a LINE, not a fraction of
 * time: the ball covers unequal ground between frames and the ribbon is drawn in space.
 */
data class TrailSample(
    val x: Float,
    val y: Float,
    val position: Float,
)

/**
 * The shape of the drawn trajectory, kept apart from the drawing of it.
 *
 * WHAT CHANGED AND WHY. This used to be a polyline through the sightings, with a comment
 * arguing that a spline invents positions between measurements. That argument was right
 * about the DATA and wrong about the PICTURE. Nothing here is written back to the engine,
 * exported, or counted — [BallSighting] remains the only record of where the ball was
 * seen, the diagnostics panel still reports discrete accepted and rejected counts, and a
 * curve drawn between two measured points claims nothing the straight line between them
 * did not already claim. What it does buy is a trajectory that reads as a ball's flight
 * rather than as a scatter plot.
 *
 * WHAT IS STILL REFUSED. The curve never runs past the last measurement, never bridges a
 * break in the evidence, and never smooths a measured point away from where it was
 * measured — a Catmull-Rom spline passes exactly THROUGH its control points, which is the
 * whole reason it is the one used here rather than a B-spline that would pull the line off
 * them. Where the tracker lost the ball, the ribbon stops and starts again.
 */
object TrailGeometry {

    /** Samples per span between two sightings. Eight is smooth at any size a phone draws. */
    const val SAMPLES_PER_SPAN = 8

    /**
     * Split a track into the stretches that are one continuous flight.
     *
     * Split on the tracker's OWN limits rather than on numbers chosen to look good: if
     * [OpenCvBallTracker] would have treated the next sighting as a new flight rather than
     * a continuation, the drawing has no business joining them with a line. A ribbon that
     * swept across the frame from where the ball was lost to where a different one was
     * found would be the most convincing lie this screen could tell.
     */
    fun runs(points: List<BallSighting>): List<List<BallSighting>> {
        if (points.isEmpty()) return emptyList()

        val out = mutableListOf<List<BallSighting>>()
        var current = mutableListOf(points.first())

        for (i in 1 until points.size) {
            val previous = points[i - 1]
            val next = points[i]
            val gapMs = next.timestampMs - previous.timestampMs
            val dx = next.x - previous.x
            val dy = next.y - previous.y
            val step = sqrt((dx * dx + dy * dy).toDouble()).toFloat()

            val continues = gapMs in 0..OpenCvBallTracker.TRACK_GAP_LIMIT_MS &&
                step <= OpenCvBallTracker.MAX_STEP_PER_FRAME

            if (continues) {
                current.add(next)
            } else {
                out.add(current)
                current = mutableListOf(next)
            }
        }
        out.add(current)
        return out
    }

    /**
     * A run of sightings as a smooth polyline, dense enough to draw as a curve.
     *
     * Centripetal-style Catmull-Rom with duplicated endpoints: the first and last
     * measured points are their own neighbours, so the curve starts and ends exactly on
     * them instead of overshooting past the last place the ball was actually seen.
     *
     * @param positionFrom where this run starts along the whole trail, 0..1
     * @param positionTo where it ends
     */
    fun sample(
        run: List<BallSighting>,
        positionFrom: Float = 0f,
        positionTo: Float = 1f,
        samplesPerSpan: Int = SAMPLES_PER_SPAN,
    ): List<TrailSample> {
        if (run.isEmpty()) return emptyList()
        if (run.size == 1) {
            return listOf(TrailSample(run[0].x, run[0].y, positionTo))
        }

        val steps = samplesPerSpan.coerceAtLeast(1)
        val spans = run.size - 1
        val out = ArrayList<TrailSample>(spans * steps + 1)

        for (span in 0 until spans) {
            val p0 = run[(span - 1).coerceAtLeast(0)]
            val p1 = run[span]
            val p2 = run[span + 1]
            val p3 = run[(span + 2).coerceAtMost(run.size - 1)]

            // The final span emits its endpoint; earlier ones leave it to the next span so
            // the shared point is not drawn twice.
            val last = if (span == spans - 1) steps else steps - 1
            for (i in 0..last) {
                val t = i.toFloat() / steps
                val along = (span + t) / spans
                out.add(
                    TrailSample(
                        x = catmullRom(p0.x, p1.x, p2.x, p3.x, t),
                        y = catmullRom(p0.y, p1.y, p2.y, p3.y, t),
                        position = positionFrom + (positionTo - positionFrom) * along,
                    ),
                )
            }
        }
        return out
    }

    /**
     * Every run of [points], sampled, with each run's share of the 0..1 fade.
     *
     * Share is by POINT COUNT rather than by run, so a two-point stub left over from a
     * lost ball does not get the same quarter of the ribbon's brightness as the twenty
     * points of the real flight beside it.
     */
    fun sampleAll(points: List<BallSighting>, samplesPerSpan: Int = SAMPLES_PER_SPAN): List<List<TrailSample>> {
        val runs = runs(points)
        if (runs.isEmpty()) return emptyList()

        val total = points.size.coerceAtLeast(1)
        var consumed = 0
        return runs.map { run ->
            val from = consumed.toFloat() / total
            consumed += run.size
            val to = consumed.toFloat() / total
            sample(run, positionFrom = from, positionTo = to, samplesPerSpan = samplesPerSpan)
        }
    }

    /** The standard uniform Catmull-Rom basis, which passes through [p1] and [p2]. */
    private fun catmullRom(p0: Float, p1: Float, p2: Float, p3: Float, t: Float): Float {
        val t2 = t * t
        val t3 = t2 * t
        return 0.5f * (
            (2f * p1) +
                (-p0 + p2) * t +
                (2f * p0 - 5f * p1 + 4f * p2 - p3) * t2 +
                (-p0 + 3f * p1 - 3f * p2 + p3) * t3
            )
    }
}
