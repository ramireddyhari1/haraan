package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Where a number came from, carried with the number.
 *
 * The whole point of this type is that a reading cannot be displayed without saying what
 * it is. A screen that shows "132 km/h" and a screen that shows "132 km/h (estimated from
 * an uncalibrated image)" are different products, and only one of them is honest about a
 * pipeline that has never been checked against a speed gun.
 */
sealed interface MetricValue {

    /**
     * Observed, in the units stated, with no modelling in between.
     *
     * [caveat] is not an apology — it is the condition under which the number means what
     * it says. An image-plane speed is exactly measured and still changes with where the
     * camera stands, and a reader who does not know that will misread it.
     */
    data class Measured(
        val value: Double,
        val unit: String,
        val caveat: String? = null,
    ) : MetricValue

    /** Derived through something that can be wrong in a known way. [basis] says how. */
    data class Estimated(
        val value: Double,
        val unit: String,
        val basis: String,
    ) : MetricValue

    /** Not available, and [reason] says what would be needed to make it available. */
    data class Unavailable(val reason: String) : MetricValue
}

/**
 * What one delivery's track can honestly be said to show.
 *
 * READ THE UNITS. Almost everything here is in FRAME WIDTHS, not metres, because the only
 * thing this pipeline has is a picture. [CricketVisionEngine] says it plainly: pitch
 * coordinates and km/h need a calibration that does not exist, and an interface offering
 * them invites somebody to fill them in. So they are offered here as
 * [MetricValue.Unavailable] with the reason attached, rather than left off the screen
 * where their absence would look like an oversight instead of a decision.
 *
 * THE ONE EXCEPTION is the bounce, and for the reason [BouncePoint] gives at length: a
 * ground-plane homography is exact only for points on the ground, and the bounce is the
 * single instant in a delivery when the ball is there. Everything metric on this screen
 * comes from that one moment or not at all.
 *
 * Plain Kotlin, no OpenCV and no Android, so every line of it is provable at a desk.
 */
data class FlightMetrics(

    /**
     * How fast the ball is crossing the picture, in frame widths per second.
     *
     * A real measurement of a real thing, and not a speed in any sense a commentator means
     * — a ball bowled straight at the camera covers almost no picture at all. It is here
     * because it is the one speed this pipeline can state without inventing a calibration,
     * and because it is genuinely useful for comparing two deliveries filmed from the same
     * position.
     */
    val imageSpeed: MetricValue,

    /** Speed over the ground. Never available without calibration; see [FlightMetrics]. */
    val groundSpeed: MetricValue,

    /**
     * How far the flight bends away from a straight line, over the whole track.
     *
     * Measured, and meaningfully so: a straight path in three dimensions projects to a
     * straight line in a pinhole camera, so a bend in the picture is evidence of a bend in
     * the air rather than an artefact of the view. What it cannot do is say which KIND of
     * bend — swing, drift and a slice of perspective all read the same until the bounce
     * splits the flight in two.
     */
    val curve: MetricValue,

    /** Bend before the bounce. Needs a located bounce to separate it from [lateral]. */
    val swing: MetricValue,

    /** Bend after the bounce — seam or turn off the pitch. Needs a located bounce. */
    val lateral: MetricValue,

    /** Revolutions. Never available; the detector sees a centroid, not a seam. */
    val spin: MetricValue,

    /** How far up the pitch it landed, in metres. Measured when calibrated. */
    val bounceLength: MetricValue,

    /** How far across, in metres. Estimated even when calibrated — see [Bounce.lineM]. */
    val bounceLine: MetricValue,

    /** The detector's own score for the newest sighting. Uncalibrated by construction. */
    val trackScore: MetricValue,

    /** The bounce itself, for drawing. Null when none was found. */
    val bounce: Bounce? = null,
) {
    companion object {

        /** Below this there is not enough of a path to say anything about its shape. */
        const val MIN_FOR_CURVE = 4

        /** Segments averaged for the speed readout — about a sixth of a second at 30fps. */
        const val SPEED_WINDOW = 5

        /**
         * Everything measurable about [track], and the reason for everything that is not.
         *
         * @param frameAspect the analysed frame's width divided by its height. Needed
         *   because x is normalised against the width and y against the height, so the two
         *   are not the same unit and a distance taken straight from them would be wrong
         *   on anything that is not square.
         * @param quad the pitch calibration, when one exists. Without it every metric in
         *   metres reports itself unavailable rather than guessing a scale.
         */
        fun of(
            track: List<BallSighting>,
            frameAspect: Float,
            quad: PitchQuad? = null,
        ): FlightMetrics {
            // The current flight only. Sightings from before the ball was lost belong to a
            // different delivery and averaging across the gap would describe neither.
            val run = TrailGeometry.runs(track).lastOrNull().orEmpty()
            val aspect = if (frameAspect > 0f) frameAspect.toDouble() else 1.0

            val bounce = quad?.let { locateBounce(run, it) }

            return FlightMetrics(
                imageSpeed = imageSpeed(run, aspect),
                groundSpeed = MetricValue.Unavailable(
                    // Not "not implemented". There is no way to get this from one camera
                    // with no calibration, and saying so is the useful answer.
                    "needs pitch calibration — the ball is off the ground plane",
                ),
                curve = deviation(run, aspect, "whole flight"),
                swing = when {
                    bounce == null -> MetricValue.Unavailable(
                        "needs a located bounce to separate it from movement off the pitch",
                    )
                    else -> deviation(run.take(indexOfBounce(run, bounce) + 1), aspect, "before the bounce")
                },
                lateral = when {
                    bounce == null -> MetricValue.Unavailable(
                        "needs a located bounce to separate it from swing",
                    )
                    else -> deviation(run.drop(indexOfBounce(run, bounce)), aspect, "after the bounce")
                },
                spin = MetricValue.Unavailable(
                    // Worth stating rather than omitting: it is the metric people most
                    // expect and the one this pipeline is furthest from.
                    "no rotation observed — a centroid and an area, never a seam",
                ),
                bounceLength = bounce?.let {
                    MetricValue.Measured(it.lengthM, "m", "from the striker's stumps")
                } ?: bounceUnavailable(run, quad),
                bounceLine = bounce?.let {
                    MetricValue.Estimated(
                        it.lineM,
                        "m",
                        "little sideways signal; runs about a quarter short",
                    )
                } ?: bounceUnavailable(run, quad),
                trackScore = run.lastOrNull()?.let {
                    MetricValue.Measured(
                        it.trackingConfidence.toDouble(),
                        "",
                        "ranks candidates; not calibrated against truth",
                    )
                } ?: MetricValue.Unavailable("nothing tracked yet"),
                bounce = bounce,
            )
        }

        /**
         * Path length over elapsed time, across the most recent few sightings.
         *
         * Path length rather than straight-line displacement, because a ball that curves
         * between two frames travelled the curve. Averaged over several segments because a
         * single one is one detection away from being nonsense.
         */
        private fun imageSpeed(run: List<BallSighting>, aspect: Double): MetricValue {
            if (run.size < 2) return MetricValue.Unavailable("needs two sightings in one flight")

            val window = run.takeLast(SPEED_WINDOW + 1)
            val elapsedMs = window.last().timestampMs - window.first().timestampMs
            if (elapsedMs <= 0L) {
                return MetricValue.Unavailable("the sightings share a timestamp")
            }

            var distance = 0.0
            for (i in 1 until window.size) {
                distance += separation(window[i - 1], window[i], aspect)
            }

            return MetricValue.Measured(
                distance / (elapsedMs / 1000.0),
                "fw/s",
                "frame widths/s — depends on the camera's position, not a ground speed",
            )
        }

        /**
         * The greatest perpendicular distance from the straight line joining the ends.
         *
         * Perpendicular to the CHORD, not to the axis: a delivery that is both fast and
         * curving would otherwise read its own length as movement.
         */
        private fun deviation(
            points: List<BallSighting>,
            aspect: Double,
            over: String,
        ): MetricValue {
            if (points.size < MIN_FOR_CURVE) {
                return MetricValue.Unavailable(
                    "needs $MIN_FOR_CURVE sightings $over, have ${points.size}",
                )
            }

            val first = points.first()
            val last = points.last()
            val cx = (last.x - first.x).toDouble()
            val cy = (last.y - first.y).toDouble() / aspect
            val chord = sqrt(cx * cx + cy * cy)
            if (chord < 1e-6) {
                return MetricValue.Unavailable("the ball did not move $over")
            }

            var worst = 0.0
            for (point in points) {
                val px = (point.x - first.x).toDouble()
                val py = (point.y - first.y).toDouble() / aspect
                // Cross product over chord length: the distance from the point to the line.
                val offset = abs(cx * py - cy * px) / chord
                if (offset > worst) worst = offset
            }

            return MetricValue.Measured(
                worst,
                "fw",
                "peak departure from straight, $over",
            )
        }

        /** Distance between two sightings in frame widths, with y put into the same unit. */
        private fun separation(a: BallSighting, b: BallSighting, aspect: Double): Double {
            val dx = (b.x - a.x).toDouble()
            val dy = (b.y - a.y).toDouble() / aspect
            return sqrt(dx * dx + dy * dy)
        }

        /** The sighting nearest the bounce, so the flight can be split either side of it. */
        private fun indexOfBounce(run: List<BallSighting>, bounce: Bounce): Int {
            var best = 0
            var bestDistance = Double.MAX_VALUE
            run.forEachIndexed { i, point ->
                val dx = point.x - bounce.image.x
                val dy = point.y - bounce.image.y
                val distance = dx * dx + dy * dy
                if (distance < bestDistance) {
                    bestDistance = distance
                    best = i
                }
            }
            return best
        }

        private fun locateBounce(run: List<BallSighting>, quad: PitchQuad): Bounce? =
            if (run.size < BouncePoint.MIN_SIGHTINGS) null else BouncePoint.find(run, quad)

        /**
         * Why there is no bounce, specifically.
         *
         * Each of these sends a tester somewhere different — to run the pitch check, to
         * walk to the other end, or to film a longer look at the delivery — so they are
         * reported apart rather than as one shrug.
         */
        private fun bounceUnavailable(run: List<BallSighting>, quad: PitchQuad?) = when {
            quad == null -> MetricValue.Unavailable(
                "no pitch calibration — nothing here can be in metres without one",
            )
            quad.cameraEnd != CameraEnd.BOWLER -> MetricValue.Unavailable(
                "only measurable from behind the bowler's arm",
            )
            run.size < BouncePoint.MIN_SIGHTINGS -> MetricValue.Unavailable(
                "needs ${BouncePoint.MIN_SIGHTINGS} sightings in one flight, have ${run.size}",
            )
            else -> MetricValue.Unavailable("no bounce found in this track")
        }
    }
}
