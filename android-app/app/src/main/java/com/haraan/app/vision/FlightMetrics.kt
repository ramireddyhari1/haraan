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
 * READ THE UNITS. Much of this is in FRAME WIDTHS rather than metres, because for most of
 * what a phone sees a picture is all there is. What changed, and why several fields that
 * spent their whole life saying "needs pitch calibration" can now hold a number, is
 * [WicketTracker]: a CONFIRMED lock on three stumps is a known 0.2286 m across and a known
 * 0.711 m tall, sitting at a known place in the picture. That is a real scale in two
 * directions AT THE WICKET'S OWN DEPTH, and everything metric here that does not come from
 * a [PitchQuad] comes from that and says so.
 *
 * WHAT A WICKET LOCK STILL CANNOT DO, said plainly because the temptation is obvious. It
 * cannot see depth. A ball bowled straight down the pitch at a camera behind the bowler's
 * arm crosses almost no picture at all, and no amount of scale applied to almost no picture
 * produces a speed. [groundSpeed] is therefore available when the camera is SQUARE to the
 * flight and unavailable when it is not — which is a real condition about where somebody is
 * standing, not a hedge.
 *
 * Plain Kotlin, no OpenCV and no Android, so every line of it is provable at a desk.
 */
data class FlightMetrics(

    /**
     * How fast the ball is crossing the picture, in frame widths per second.
     *
     * A real measurement of a real thing, and not a speed in any sense a commentator means
     * — a ball bowled straight at the camera covers almost no picture at all. It is here
     * because it is the one speed this pipeline can state with no landmark at all, and
     * because it is genuinely useful for comparing two deliveries filmed from the same
     * position.
     */
    val imageSpeed: MetricValue,

    /**
     * Speed over the ground, in km/h.
     *
     * Two ways to get one. Measured: a measurable stumps lock AND a camera square enough
     * to the flight that the across-the-wicket scale is the right scale for the ball's
     * motion — see [FlightMetrics.SQUARE_ENOUGH_DEGREES]. Estimated: from behind the arm,
     * the pitch corners plus a found bounce — see [QuadSpeed].
     */
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

    /** Bend before the bounce. In metres once a wicket lock supplies a scale. */
    val swing: MetricValue,

    /** Bend after the bounce — seam or turn off the pitch. Metres with a lock. */
    val lateral: MetricValue,

    /**
     * How many degrees the ball changed direction at the bounce.
     *
     * What everybody means by spin, and a different measurement from [spin]: it is the
     * angle between the path into the bounce and the path out of it, which is visible in
     * the picture and needs no calibration at all. Straight lines in the world project to
     * straight lines in a camera, so a kink in the picture is a kink in the flight.
     */
    val turn: MetricValue,

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

    /**
     * Where the flight would have crossed the stump line.
     *
     * Its own type rather than a [MetricValue] because it is not one number: it is a
     * verdict, an error bar, and a list of the LBW questions this pipeline did not answer.
     * Flattening it into a metric row would lose the list, which is the part that keeps it
     * honest.
     */
    val lbw: LbwProjection = LbwProjector.project(emptyList(), null),

    /** The wicket the metric rows were scaled by, when one was used. For a readout. */
    val wicket: WicketLock? = null,
) {
    companion object {

        /** Below this there is not enough of a path to say anything about its shape. */
        const val MIN_FOR_CURVE = 4

        /** Segments averaged for the speed readout — about a sixth of a second at 30fps. */
        const val SPEED_WINDOW = 5

        /**
         * How far the flight may run away from the stump line's own direction before the
         * across-the-wicket scale stops being the right scale for it.
         *
         * THE WHOLE ARGUMENT FOR THIS NUMBER. A stumps lock measures distance along the
         * line the three stumps stand on, and nowhere else. A ball travelling along that
         * same direction in the picture is moving in a plane the scale describes, and its
         * speed comes out right. A ball travelling at right angles to it is going up or
         * down the pitch, which is depth, which one camera cannot see — and at forty-five
         * degrees the reading is out by a factor of the cosine, silently.
         *
         * Thirty degrees keeps the geometric error under about fifteen percent. Past it the
         * reading is refused, because the failure is invisible: the number looks completely
         * reasonable while being half of what it should be.
         */
        const val SQUARE_ENOUGH_DEGREES = 30.0

        /**
         * Everything measurable about [track], and the reason for everything that is not.
         *
         * @param frameAspect the analysed frame's width divided by its height. Needed
         *   because x is normalised against the width and y against the height, so the two
         *   are not the same unit and a distance taken straight from them would be wrong
         *   on anything that is not square.
         * @param quad the pitch calibration, when one exists. It is the only thing that
         *   puts a bounce at a length up the pitch; a wicket lock cannot.
         * @param wicket a wicket lock, when one is CONFIRMED and measurable. It is what
         *   puts metres and km/h on rows a quad alone cannot reach, and it is checked
         *   through [WicketLock.isMeasurable] rather than for null — a coasting lock is a
         *   lock and must not be measured from.
         */
        fun of(
            track: List<BallSighting>,
            frameAspect: Float,
            quad: PitchQuad? = null,
            wicket: WicketLock? = null,
        ): FlightMetrics {
            // The current flight only. Sightings from before the ball was lost belong to a
            // different delivery and averaging across the gap would describe neither.
            val run = TrailGeometry.runs(track).lastOrNull().orEmpty()
            val aspect = if (frameAspect > 0f) frameAspect.toDouble() else 1.0

            val bounce = quad?.let { locateBounce(run, it) }
            val scale = wicket?.takeIf { it.isMeasurable }?.metresPerUnitAcross()

            /*
             * THE BOUNCE IS FOUND IN THE PICTURE, NOT IN METRES.
             *
             * This used to need a [PitchQuad], which meant swing and turn — the two things
             * a bowler actually wants — were unavailable on every ground with worn creases,
             * which is every ground this app is used on. A bounce is also a plain fact
             * about a picture: falling, then rising. [BallPath] finds it with no
             * calibration whatsoever, and the quad is still used when there is one, for
             * the one thing only it can give: a length up the pitch in metres.
             */
            val bounceAt = BallPath.bounceIndex(run, frameAspect)
            val before = if (bounceAt == null) emptyList() else run.take(bounceAt + 1)
            val after = if (bounceAt == null) emptyList() else run.drop(bounceAt)

            return FlightMetrics(
                imageSpeed = imageSpeed(run, aspect),
                groundSpeed = groundSpeed(run, frameAspect, wicket).let { fromWicket ->
                    if (fromWicket is MetricValue.Measured) fromWicket else cornerSpeed(run, quad, bounce, frameAspect, fromWicket)
                },
                curve = deviation(run, aspect, "whole flight", scale),
                swing = when {
                    bounceAt == null -> MetricValue.Unavailable(
                        "needs a bounce in the track to separate it from movement off the pitch",
                    )
                    else -> deviation(before, aspect, "before the bounce", scale)
                },
                lateral = when {
                    bounceAt == null -> MetricValue.Unavailable(
                        "needs a bounce in the track to separate it from swing",
                    )
                    else -> deviation(after, aspect, "after the bounce", scale)
                },
                turn = turn(before, after, frameAspect),
                spin = MetricValue.Unavailable(
                    // Worth stating rather than omitting: it is the metric people most
                    // expect, and [turn] beside it is the thing they usually mean. A
                    // centroid cannot show a seam however good the lock is.
                    "no rotation observed — a centroid and an area, never a seam. See turn.",
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
                lbw = LbwProjector.project(track, wicket),
                wicket = wicket,
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
         * Speed in km/h, when the geometry allows it and not otherwise.
         *
         * THE TEST IS ABOUT WHERE THE OPERATOR IS STANDING, and that is why it can be
         * applied honestly. If the ball's path in the picture runs along the same direction
         * as the stump line, the camera is square to the flight and the wicket's own scale
         * is the right one for the ball's motion. If it runs across the stump line, the ball
         * is going up or down the pitch — depth — and a single camera has nothing to say.
         *
         * The refusal names the fix, because there is one: film from square.
         */
        private fun groundSpeed(
            run: List<BallSighting>,
            aspect: Float,
            wicket: WicketLock?,
        ): MetricValue {
            if (wicket == null) {
                return MetricValue.Unavailable("no wicket locked — lock the stumps to fix a scale")
            }
            if (wicket.kind != WicketKind.STUMPS) {
                return MetricValue.Unavailable("locked onto a stone, which has no known width")
            }
            if (!wicket.isMeasurable) {
                return MetricValue.Unavailable(
                    "the wicket lock is ${wicket.state.name.lowercase().replace('_', ' ')}",
                )
            }
            val scale = wicket.metresPerUnitAcross()
                ?: return MetricValue.Unavailable("the wicket lock carries no scale")
            if (run.size < 2) return MetricValue.Unavailable("needs two sightings in one flight")

            val window = run.takeLast(SPEED_WINDOW + 1)
            val elapsedMs = window.last().timestampMs - window.first().timestampMs
            if (elapsedMs <= 0L) return MetricValue.Unavailable("the sightings share a timestamp")

            val flat = window.map { BallPath.flatten(it, aspect) }
            val path = BallPath.fitLine(flat)
                ?: return MetricValue.Unavailable("the sightings do not describe a path")

            /*
             * The stump line's direction, taken straight from the two base points.
             *
             * NOT through [BallPath.fitLine], which needs three points to fit anything and
             * returned null for the two there are — so this whole metric was unreachable
             * and reported the stumps as having no width in frame while looking at a
             * perfectly good lock. A line through exactly two points is not a fit, it is
             * subtraction.
             */
            val left = BallPath.flatten(wicket.anchor.baseLeft, aspect)
            val right = BallPath.flatten(wicket.anchor.baseRight, aspect)
            val stumpDx = right.x - left.x
            val stumpDy = right.y - left.y
            val stumpLen = kotlin.math.hypot(stumpDx, stumpDy)
            if (stumpLen < 1e-9) {
                return MetricValue.Unavailable("the locked stumps have no width in frame")
            }
            val stumpLine = BallPath.Line(
                pointX = left.x,
                pointY = left.y,
                dirX = stumpDx / stumpLen,
                dirY = stumpDy / stumpLen,
                residual = 0.0,
                extent = stumpLen,
            )

            // 0 means the flight runs along the stump line, which is the square-on case.
            // 90 means it runs straight up the pitch, which is the one that cannot be read.
            val offSquare = BallPath.angleBetween(path, stumpLine).let { if (it > 90.0) 180.0 - it else it }
            if (offSquare > SQUARE_ENOUGH_DEGREES) {
                return MetricValue.Unavailable(
                    "the ball is travelling %.0f° away from square — one camera cannot see depth. Film from side-on."
                        .format(offSquare),
                )
            }

            var distanceFw = 0.0
            for (i in 1 until flat.size) {
                distanceFw += kotlin.math.hypot(flat[i].x - flat[i - 1].x, flat[i].y - flat[i - 1].y)
            }

            val metresPerSecond = distanceFw * scale / (elapsedMs / 1000.0)
            return MetricValue.Measured(
                metresPerSecond * 3.6,
                "km/h",
                "scaled by the locked wicket, %.0f° off square — right at the stumps' depth, fast nearer the camera and slow further away"
                    .format(offSquare),
            )
        }

        /**
         * The behind-the-arm speed, when the wicket could not give one.
         *
         * The refusal keeps the wicket's own reason and adds what would fix it from here,
         * because both fixes are real: film side-on, or mark the pitch corners.
         */
        private fun cornerSpeed(
            run: List<BallSighting>,
            quad: PitchQuad?,
            bounce: Bounce?,
            frameAspect: Float,
            fromWicket: MetricValue,
        ): MetricValue {
            val why = (fromWicket as? MetricValue.Unavailable)?.reason ?: "no wicket speed"
            if (quad == null) {
                return MetricValue.Unavailable("$why; or mark the pitch corners from behind the arm")
            }
            if (bounce == null) {
                return MetricValue.Unavailable("$why; the pitch corners need a bounce in the track to time the ball")
            }
            return QuadSpeed.estimate(run, quad, bounce, frameAspect)
        }

        /**
         * The greatest perpendicular distance from the straight line joining the ends.
         *
         * Perpendicular to the CHORD, not to the axis: a delivery that is both fast and
         * curving would otherwise read its own length as movement.
         *
         * In metres when [scale] is supplied, which is a genuine conversion for a camera
         * behind the arm: the deviation there is sideways across the pitch, which is the
         * direction the stump line runs, which is the direction the scale describes.
         */
        private fun deviation(
            points: List<BallSighting>,
            aspect: Double,
            over: String,
            scale: Double? = null,
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

            if (scale == null) {
                return MetricValue.Measured(worst, "fw", "peak departure from straight, $over")
            }
            return MetricValue.Estimated(
                worst * scale,
                "m",
                "peak departure from straight, $over, at the wicket's depth — the ball was nearer the camera for part of it",
            )
        }

        /**
         * The kink at the bounce, in degrees.
         *
         * Measured in the picture, which is where it happens. Its size on the ground is
         * the same only when the camera is square; from behind the arm a big turn reads
         * smaller than it was, and the caveat says so rather than the number being quietly
         * wrong.
         */
        private fun turn(
            before: List<BallSighting>,
            after: List<BallSighting>,
            aspect: Float,
        ): MetricValue {
            if (before.size < BallPath.MIN_FOR_LINE || after.size < BallPath.MIN_FOR_LINE) {
                return MetricValue.Unavailable(
                    "needs ${BallPath.MIN_FOR_LINE} sightings each side of the bounce, " +
                        "have ${before.size} and ${after.size}",
                )
            }
            val into = BallPath.fitLine(before.map { BallPath.flatten(it, aspect) })
            val out = BallPath.fitLine(after.map { BallPath.flatten(it, aspect) })
            if (into == null || out == null) {
                return MetricValue.Unavailable("the sightings either side do not describe two paths")
            }
            return MetricValue.Measured(
                BallPath.signedAngleBetween(into, out),
                "°",
                "the angle in the picture — equal to the angle on the ground only from square",
            )
        }

        /** Distance between two sightings in frame widths, with y put into the same unit. */
        private fun separation(a: BallSighting, b: BallSighting, aspect: Double): Double {
            val dx = (b.x - a.x).toDouble()
            val dy = (b.y - a.y).toDouble() / aspect
            return sqrt(dx * dx + dy * dy)
        }

        private fun locateBounce(run: List<BallSighting>, quad: PitchQuad): Bounce? =
            if (run.size < BouncePoint.MIN_SIGHTINGS) null else BouncePoint.find(run, quad)

        /**
         * Why there is no bounce IN METRES, specifically.
         *
         * Each of these sends a tester somewhere different — to run the pitch check, to
         * walk to the other end, or to film a longer look at the delivery — so they are
         * reported apart rather than as one shrug. A wicket lock does not help here and is
         * not offered as though it might: it fixes a place across the pitch, and a length
         * up the pitch is the one thing only a ground-plane calibration can give.
         */
        private fun bounceUnavailable(run: List<BallSighting>, quad: PitchQuad?) = when {
            quad == null -> MetricValue.Unavailable(
                "no pitch calibration — a wicket lock cannot give a length up the pitch",
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
