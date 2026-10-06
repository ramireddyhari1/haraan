package com.haraan.app.vision

import kotlin.math.abs

/**
 * Where the ball hit the ground, and how far up the pitch that was.
 *
 * WHY THIS IS THE ONE MEASUREMENT WORTH MAKING. [Homography] maps a plane, and it is exact
 * only for points lying ON that plane. A ball in flight is above the pitch, so mapping it
 * gives a confident, wrong answer — which is why nothing else in this package offers to. A
 * bounce is the single moment in a delivery when the ball is on the surface, and therefore
 * the single moment a phone at a boundary can say something in metres and be right. Every
 * pitch map, length and line is built from here.
 *
 * HOW IT IS FOUND, and why not the obvious way. The first instinct is to look for the kink
 * in the ball's path across the picture. From behind the bowler's arm there is barely one:
 * the ball recedes up the frame before the bounce and recedes up the frame after it, and a
 * change of direction that is enormous in the real world is a few pixels of slope on
 * screen. A detector built on that finds bounces in straight lines — it was built, and it
 * did.
 *
 * So this uses the geometry instead.
 *
 * Map every sighting through the pitch homography AS IF it were lying on the ground. For a
 * ball in the air that reading is wrong, and wrong in a known direction: the line of sight
 * carries past the ball and strikes the ground beyond it, always further from the camera
 * than the ball really is. The higher the ball, the further out the reading lands.
 *
 * So the error shrinks as the ball falls, reaches exactly zero at the bounce, and grows
 * again as it climbs away. Filmed from behind the bowler — with the ball travelling away —
 * that reading sweeps out, turns, and sweeps back, and it turns at the instant the ball is
 * on the grass. Finding the bounce is finding that turn.
 *
 * The turn is then placed BETWEEN frames by fitting a line to each side of it and
 * intersecting them. At 120 km/h a ball covers about 1.1 m between frames at 30fps, so
 * taking the nearest sample would leave the answer up to half a metre out — and half a
 * metre is the difference between a good length and a half-volley. Against a simulated
 * camera the fitted answer lands within about 0.3 m over the lengths that matter.
 *
 * NOTHING HERE TREATS AN AIRBORNE READING AS A POSITION. Those readings locate the moment
 * of the turn and nothing else. The single point reported is the one place the ball was
 * actually on the plane, which is the one place the map can be believed.
 *
 * Plain Kotlin, no OpenCV, for the reason [PitchGeometry] gives: it makes the measurable
 * half of this pipeline provable at a desk.
 */
object BouncePoint {

    /**
     * Find the bounce in a delivery's track, or return null rather than guess.
     *
     * [track] is the sightings for one delivery, oldest first, in normalised upright image
     * coordinates — exactly what [CricketVisionEngine.track] returns. [quad] is the pitch
     * the measurement stands on, and the end it was calibrated from.
     */
    fun find(track: List<BallSighting>, quad: PitchQuad): Bounce? {
        /*
         * ONLY FROM BEHIND THE BOWLER, AND THIS IS NOT LAZINESS.
         *
         * The turn above exists because the ball is moving AWAY from the camera: its true
         * position and the overshoot both run in the same direction, so the reading has a
         * genuine extremum on the frame the ball lands.
         *
         * From the striker's end the ball comes towards the camera while the overshoot
         * still points away, the two work against each other, and the reading slides
         * through the bounce without turning at all. There is a corner in the curve, but
         * locating it by the obvious means — splitting the track wherever two straight
         * fits explain it best — picks whichever stretch happens to be straightest, and it
         * was out by nine metres on a simulated delivery.
         *
         * A length that is sometimes nine metres wrong is worse than no length, so this
         * end gets no length until there is a method that earns one. Everything else the
         * quad does — the corridor, the guides, the aiming — works from either end.
         */
        if (quad.cameraEnd != CameraEnd.BOWLER) return null
        if (track.size < MIN_SIGHTINGS) return null

        val toPitch = quad.toPitch() ?: return null
        val image = track.map { Point2(it.x.toDouble(), it.y.toDouble()) }
        val grounded = image.map(toPitch::map)

        /*
         * Readings that cannot mean anything are dropped, not fitted.
         *
         * A ball above the camera is above the horizon, and the line of sight through it
         * never reaches the ground ahead — the homography answers with the horizon's other
         * side, a number in the hundreds of metres or a sign flip. A phone on a tripod sits
         * around 1.6 m up and the ball leaves the hand above 2 m, so the opening frames of
         * every delivery are like this. They are not noise to be averaged down; they are
         * not measurements at all.
         */
        val usable = grounded.indices.filter { i ->
            val point = grounded[i]
            !point.x.isNaN() && !point.y.isNaN() &&
                point.y >= -PITCH_BAND_M &&
                point.y <= PitchGeometry.STUMPS_TO_STUMPS_M + PITCH_BAND_M &&
                abs(point.x) <= USABLE_WIDTH_M
        }
        if (usable.size < MIN_PER_SIDE * 2 + 1) return null

        // The turn: the reading that came back closest to the camera.
        val turn = usable.maxByOrNull { grounded[it].y } ?: return null

        /*
         * It has to be a turn we watched happen. A peak at either end of what we could use
         * means the ball was still going out, or already coming back, for every frame we
         * had — a full toss, or a delivery picked up only after it pitched.
         */
        if (turn == usable.first() || turn == usable.last()) return null

        /*
         * And a real turn rather than a wobble. A ball released around two metres up reads
         * metres past itself, so a genuine delivery clears this comfortably while a
         * straight run with a couple of bad detections in it does not.
         */
        val swing = grounded[turn].y -
            maxOf(grounded[usable.first()].y, grounded[usable.last()].y)
        if (swing < MIN_SWING_M) return null

        // A line into the turn and a line out of it, fitted near the turn only: further
        // out the reading curves hard, and a straight fit through that bends the answer.
        val before = usable.filter { it in (turn - WINDOW)..turn }
        val after = usable.filter { it in turn..(turn + WINDOW) }
        if (before.size < MIN_PER_SIDE || after.size < MIN_PER_SIDE) return null

        val falling = fit(before, grounded) ?: return null
        val rising = fit(after, grounded) ?: return null
        if (abs(falling.slope - rising.slope) < 1e-9) return null

        // Where they cross, in frames.
        val frame = (rising.intercept - falling.intercept) / (falling.slope - rising.slope)
        if (frame < before.first() || frame > after.last()) return null

        val at = interpolate(image, frame)
        val pitch = toPitch.map(at)
        if (pitch.x.isNaN() || pitch.y.isNaN()) return null
        if (!onThePitch(pitch)) return null

        // The same fractional frame on the camera's clock: the bounce lands between
        // frames, and a speed taken from it needs the moment as finely as the place.
        val lower = frame.toInt().coerceIn(0, track.size - 2)
        val atMs = track[lower].timestampMs +
            ((track[lower + 1].timestampMs - track[lower].timestampMs) * (frame - lower))

        return Bounce(
            image = at,
            pitch = pitch,
            sightingsUsed = track.size,
            quadSource = quad.source,
            cameraEnd = quad.cameraEnd,
            atMs = atMs,
        )
    }

    private data class Fit(val slope: Double, val intercept: Double)

    /** Least squares of the ground reading against frame number. */
    private fun fit(indices: List<Int>, grounded: List<Point2>): Fit? {
        val meanX = indices.sumOf { it.toDouble() } / indices.size
        val meanY = indices.sumOf { grounded[it].y } / indices.size

        var sxx = 0.0
        var sxy = 0.0
        indices.forEach { i ->
            val dx = i - meanX
            sxx += dx * dx
            sxy += dx * (grounded[i].y - meanY)
        }
        if (abs(sxx) < 1e-12) return null

        val slope = sxy / sxx
        return Fit(slope, meanY - slope * meanX)
    }

    /** The ball's place in the picture at a fractional frame number. */
    private fun interpolate(points: List<Point2>, frame: Double): Point2 {
        val lower = frame.toInt().coerceIn(0, points.size - 2)
        val fraction = frame - lower
        val a = points[lower]
        val b = points[lower + 1]
        return Point2(a.x + (b.x - a.x) * fraction, a.y + (b.y - a.y) * fraction)
    }

    /**
     * A bounce has to have happened on the pitch.
     *
     * The homography will happily turn a point out by the sightscreen into a well-formed
     * length of ninety metres. That is a tracking failure being handed a ruler, and the
     * honest output is no output.
     */
    private fun onThePitch(pitch: Point2): Boolean =
        abs(pitch.x) <= PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * OFF_STRIP_TOLERANCE &&
            pitch.y >= -1.0 &&
            pitch.y <= PitchGeometry.STUMPS_TO_STUMPS_M + 1.0

    /** Fewer than this and there is not enough either side of a turn to be sure of one. */
    const val MIN_SIGHTINGS = 6

    /** Frames each side of the turn that the fits are allowed to use. */
    private const val WINDOW = 4

    /** Two make a line; the fit needs both sides to have at least that. */
    private const val MIN_PER_SIDE = 2

    /** How far past each set of stumps a reading can fall and still mean something. */
    private const val PITCH_BAND_M = 3.0

    /** Beyond this across, the reading is off the square and not worth fitting. */
    private const val USABLE_WIDTH_M = 8.0

    /**
     * How far the reading must sweep out and back. Tied to release height rather than to
     * pixels: a ball let go two metres up reads several metres past itself.
     */
    private const val MIN_SWING_M = 0.75

    /** A ball can pitch outside the return crease; it cannot pitch in the next field. */
    private const val OFF_STRIP_TOLERANCE = 1.6
}

/**
 * One measured bounce.
 *
 * [pitch] is in metres with the striker's middle stump at the origin: y is how far up the
 * pitch the ball landed, x how far across. Exact in the sense this package means it —
 * exact given the calibration — which is why [quadSource] and [cameraEnd] travel with the
 * number instead of being left behind with whatever produced it.
 */
data class Bounce(
    /** Normalised upright image coordinates, for drawing it back onto the viewfinder. */
    val image: Point2,
    /** Metres from the striker's middle stump: y down the pitch, x across it. */
    val pitch: Point2,
    val sightingsUsed: Int,
    val quadSource: QuadSource,
    val cameraEnd: CameraEnd,
    /** When it landed, on the sightings' clock, between frames. Null for a hand-built bounce. */
    val atMs: Double? = null,
) {
    /** How far up the pitch it landed, in metres from the striker's stumps. */
    val lengthM: Double get() = pitch.y

    /**
     * How far across, in metres. Positive is to the right seen from the bowler's end.
     *
     * Coarser than the length, and known to be: the ball moves mostly down the pitch and
     * hardly at all across it, so the fit has far less to work with sideways. Against a
     * simulated camera the line runs short by roughly a quarter of its true offset.
     */
    val lineM: Double get() = pitch.x

    /**
     * What a commentator would call it.
     *
     * Deliberately coarse. These are the bands people say out loud, and a length is worth
     * naming only as finely as the calibration under it supports — emphatically not to the
     * centimetre.
     */
    val length: BounceLength
        get() = BounceLength.of(lengthM)
}

enum class BounceLength(val spoken: String) {
    YORKER("yorker"),
    FULL("full"),
    GOOD("good length"),
    SHORT_OF_A_LENGTH("back of a length"),
    SHORT("short"),
    ;

    companion object {
        /** The band for a bounce [lengthM] metres from the striker's stumps. */
        fun of(lengthM: Double): BounceLength = when {
            lengthM < 1.0 -> YORKER
            lengthM < 3.0 -> FULL
            lengthM < 6.0 -> GOOD
            lengthM < 9.0 -> SHORT_OF_A_LENGTH
            else -> SHORT
        }
    }
}
