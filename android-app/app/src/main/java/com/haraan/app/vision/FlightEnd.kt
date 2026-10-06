package com.haraan.app.vision

import kotlin.math.hypot

/**
 * WHEN IS THE BALL DONE?
 *
 * The delivery's numbers used to be worked out when the CLIP ended — which is whenever the
 * scorer got round to tapping the result, or the ten-second cap, whichever came first. The
 * ball itself is finished in about a second, so the person at the camera stood there for
 * up to nine more waiting for an answer the phone already had.
 *
 * This decides from the track alone, on the analysis thread, frame by frame. It never
 * looks at the scorer, because the scorer is the slow part.
 *
 * WHAT COUNTS AS DONE, and why each rule is there:
 *
 *  - A real flight first. [MIN_FLIGHT_POINTS] sightings in one run that between them cross
 *    [MIN_FLIGHT_EXTENT_FW] of the picture. A few frames of a bowler's hand are not a
 *    delivery, and calling one would print a result for a ball not yet bowled.
 *
 *  - Then the ball going quiet. [QUIET_MS] with no new sighting: hit the pad, went behind
 *    the batter, reached the keeper. Longer than the tracker's own coast so a single missed
 *    frame mid-flight does not end the delivery half way.
 *
 *  - Sooner when the last sighting is AT the wicket — it has nowhere left to go. Only
 *    meaningful with a lock, and only the quiet gap is shortened; a ball still being seen
 *    is never cut off, because the frames after the bounce are what turn and the LBW
 *    projection are measured from.
 *
 *  - And a ceiling: a run longer than [MAX_FLIGHT_MS] is no longer the delivery — it is the
 *    ball after the bat, or a fielder throwing it in. A ball at 60 km/h covers the pitch in
 *    about 1.2 s.
 *
 * Pure Kotlin, no clock of its own: [nowMs] is the camera's frame timestamp, the same clock
 * the sightings carry.
 */
object FlightEnd {

    enum class Reason {
        /** No sighting for [QUIET_MS] after a real flight. */
        BALL_GONE,

        /** Last seen at the stumps, and quiet for [AT_WICKET_QUIET_MS]. */
        AT_WICKET,

        /** The run outlasted any delivery. */
        TOO_LONG,
    }

    const val MIN_FLIGHT_POINTS = 5
    const val MIN_FLIGHT_EXTENT_FW = 0.08
    const val QUIET_MS = 300L
    const val AT_WICKET_QUIET_MS = 120L
    const val MAX_FLIGHT_MS = 1_600L

    /** "At the wicket" is within this many stump-widths of the base, or [MIN_AT_WICKET_FW]. */
    const val AT_WICKET_SPANS = 1.5
    const val MIN_AT_WICKET_FW = 0.03

    /**
     * Whether the delivery in [track] is over as of [nowMs], and why — or null to keep
     * watching.
     *
     * @param frameAspect upright width over height, so x and y are one unit.
     * @param wicket any lock at all, stone or stumps: this asks WHERE the wicket is, not how
     *   big, so a stone answers it as well as three stumps do.
     */
    fun check(
        track: List<BallSighting>,
        nowMs: Long,
        frameAspect: Float,
        wicket: WicketLock? = null,
    ): Reason? {
        val run = TrailGeometry.runs(track).lastOrNull() ?: return null
        if (run.size < MIN_FLIGHT_POINTS) return null

        val first = BallPath.flatten(run.first(), frameAspect)
        val last = BallPath.flatten(run.last(), frameAspect)
        if (hypot(last.x - first.x, last.y - first.y) < MIN_FLIGHT_EXTENT_FW) return null

        if (run.last().timestampMs - run.first().timestampMs >= MAX_FLIGHT_MS) return Reason.TOO_LONG

        val quiet = nowMs - run.last().timestampMs
        if (wicket != null && quiet >= AT_WICKET_QUIET_MS && atWicket(last, wicket, frameAspect)) {
            return Reason.AT_WICKET
        }
        if (quiet >= QUIET_MS) return Reason.BALL_GONE
        return null
    }

    private fun atWicket(ball: BallPath.Flat, wicket: WicketLock, aspect: Float): Boolean {
        val base = BallPath.flatten(wicket.base, aspect)
        val reach = maxOf(wicket.span * AT_WICKET_SPANS, MIN_AT_WICKET_FW)
        return hypot(ball.x - base.x, ball.y - base.y) <= reach
    }
}
