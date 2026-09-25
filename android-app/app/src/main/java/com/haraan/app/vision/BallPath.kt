package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * The shape of a flight, in the picture, with no calibration involved.
 *
 * WHY THIS IS SEPARATE FROM [FlightMetrics]. Those are the numbers a screen shows. These
 * are the geometric facts the numbers are computed from — where the bounce is, which way
 * the ball is going, how straight the path is — and three different callers need them:
 * the metrics, the LBW projection, and any future review screen. Deriving them three times
 * from three slightly different pieces of arithmetic is how two screens end up disagreeing
 * about where a delivery bounced.
 *
 * ASPECT-CORRECTED SPACE THROUGHOUT. Every function here works in frame widths in both
 * directions: x as it comes, y divided by the frame's aspect. An angle taken from raw
 * normalised coordinates on a 16:9 picture is wrong by nearly a factor of two, and an
 * angle is what half of this file produces.
 */
object BallPath {

    /** Below this there is not enough of a path to fit a direction to. */
    const val MIN_FOR_LINE = 3

    /** Sightings needed either side of a bounce before it is called one. */
    const val MIN_EITHER_SIDE = 2

    /**
     * How much of the flight's own vertical extent the ball must come back up before a
     * turn in the path counts as a bounce rather than as noise in the detections.
     */
    const val MIN_REBOUND_FRACTION = 0.06f

    /** A point in aspect-corrected frame-width space. */
    data class Flat(val x: Double, val y: Double)

    fun flatten(sighting: BallSighting, aspect: Float): Flat {
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        return Flat(sighting.x.toDouble(), sighting.y.toDouble() / a)
    }

    fun flatten(point: Point2, aspect: Float): Flat {
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        return Flat(point.x, point.y / a)
    }

    /** Back to normalised image coordinates, for drawing. */
    fun unflatten(flat: Flat, aspect: Float): Point2 {
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        return Point2(flat.x, flat.y * a)
    }

    /**
     * A straight line fitted to points, as a point on it and a unit direction.
     *
     * TOTAL LEAST SQUARES, not the ordinary kind. Fitting y against x minimises error in
     * y alone, which is fine until the ball happens to be travelling nearly straight up the
     * picture — and a ball bowled from behind the arm is ALWAYS travelling nearly straight
     * up the picture, which is the one case that matters here. The principal axis of the
     * centred points has no preferred direction and is exact for a vertical path.
     */
    data class Line(
        val pointX: Double,
        val pointY: Double,
        val dirX: Double,
        val dirY: Double,
        /** RMS distance of the fitted points from the line, in frame widths. */
        val residual: Double,
        /** How far the fitted points span along the line. The fit's own reach. */
        val extent: Double,
    ) {
        fun at(t: Double) = Flat(pointX + dirX * t, pointY + dirY * t)

        /** Where along the line a point projects, signed, from [pointX]/[pointY]. */
        fun projectionOf(p: Flat) = (p.x - pointX) * dirX + (p.y - pointY) * dirY
    }

    fun fitLine(points: List<Flat>): Line? {
        if (points.size < MIN_FOR_LINE) return null
        val n = points.size.toDouble()
        val cx = points.sumOf { it.x } / n
        val cy = points.sumOf { it.y } / n

        var sxx = 0.0
        var syy = 0.0
        var sxy = 0.0
        for (p in points) {
            val dx = p.x - cx
            val dy = p.y - cy
            sxx += dx * dx
            syy += dy * dy
            sxy += dx * dy
        }
        if (sxx + syy < 1e-12) return null

        // Principal axis: the eigenvector of the 2x2 covariance with the larger eigenvalue,
        // in closed form. No iteration and no library.
        val theta = 0.5 * atan2(2.0 * sxy, sxx - syy)
        val dirX = kotlin.math.cos(theta)
        val dirY = kotlin.math.sin(theta)

        var residualSq = 0.0
        var minT = Double.MAX_VALUE
        var maxT = -Double.MAX_VALUE
        for (p in points) {
            val dx = p.x - cx
            val dy = p.y - cy
            val along = dx * dirX + dy * dirY
            val across = dx * -dirY + dy * dirX
            residualSq += across * across
            if (along < minT) minT = along
            if (along > maxT) maxT = along
        }

        return Line(
            pointX = cx,
            pointY = cy,
            // Pointed the way the ball is actually travelling. The principal axis has no
            // sign of its own, and a projection that runs backwards down the flight would
            // put the stumps behind the bowler.
            dirX = dirX,
            dirY = dirY,
            residual = sqrt(residualSq / n),
            extent = maxT - minT,
        ).orientedAlong(points)
    }

    private fun Line.orientedAlong(points: List<Flat>): Line {
        val first = points.first()
        val last = points.last()
        val travel = (last.x - first.x) * dirX + (last.y - first.y) * dirY
        return if (travel >= 0.0) this else copy(dirX = -dirX, dirY = -dirY)
    }

    /**
     * The index in [run] where the ball bounced, or null.
     *
     * IN THE PICTURE, WITH NO CALIBRATION. [BouncePoint] finds a bounce in METRES and needs
     * a [PitchQuad] to do it, which is the right answer when there is one and no answer at
     * all when there is not. A bounce is also a plain fact about the picture: the ball is
     * falling, then it is rising. That is visible without knowing where anything is, and it
     * is enough to split a flight into its two halves — which is all the swing and turn
     * figures need.
     *
     * Y GROWS DOWNWARD, so falling is dy > 0 and rising is dy < 0.
     */
    fun bounceIndex(run: List<BallSighting>, aspect: Float): Int? {
        if (run.size < MIN_EITHER_SIDE * 2) return null
        val flat = run.map { flatten(it, aspect) }

        val minY = flat.minOf { it.y }
        val maxY = flat.maxOf { it.y }
        val extent = maxY - minY
        if (extent < 1e-6) return null
        val minRebound = extent * MIN_REBOUND_FRACTION

        var best: Int? = null
        var bestRebound = minRebound

        for (i in MIN_EITHER_SIDE until flat.size - MIN_EITHER_SIDE) {
            // Falling into this point...
            val before = flat[i].y - flat[i - MIN_EITHER_SIDE].y
            // ...and rising out of it.
            val after = flat[i].y - flat[i + MIN_EITHER_SIDE].y
            if (before <= 0.0 || after <= 0.0) continue

            // The lowest point of the two arms, so the deepest turn wins rather than the
            // first one. A ball that bounces and is then cut will produce two turns, and
            // the bounce is the lower.
            val rebound = kotlin.math.min(before, after)
            if (rebound > bestRebound) {
                bestRebound = rebound
                best = i
            }
        }
        return best
    }

    /**
     * Angle between two directions, in degrees, 0..180.
     *
     * Used for the turn off the pitch, which is the difference between where the ball was
     * going before it landed and where it went afterwards.
     */
    fun angleBetween(a: Line, b: Line): Double {
        val dot = (a.dirX * b.dirX + a.dirY * b.dirY).coerceIn(-1.0, 1.0)
        val cross = a.dirX * b.dirY - a.dirY * b.dirX
        return Math.toDegrees(abs(atan2(cross, dot)))
    }

    /** Signed version: positive turns one way across the picture, negative the other. */
    fun signedAngleBetween(a: Line, b: Line): Double {
        val dot = (a.dirX * b.dirX + a.dirY * b.dirY).coerceIn(-1.0, 1.0)
        val cross = a.dirX * b.dirY - a.dirY * b.dirX
        return Math.toDegrees(atan2(cross, dot))
    }

    /** Path length through the points, in frame widths. The curve, not the chord. */
    fun pathLength(points: List<Flat>): Double {
        var total = 0.0
        for (i in 1 until points.size) {
            total += hypot(points[i].x - points[i - 1].x, points[i].y - points[i - 1].y)
        }
        return total
    }
}
