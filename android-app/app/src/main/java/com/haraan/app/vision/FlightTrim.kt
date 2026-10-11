package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot

/**
 * THE END OF A FLIGHT FILMED FROM BEHIND THE BOWLER IS WHERE THE TRACK IS LEAST SURE.
 *
 * Going away from the lens the ball only slows in the picture and keeps its way across it;
 * off the pitch it rises, to the batter's chest at most. At the far end it is
 * a few pixels against the batter, the bat and the pads, and on a 120 fps net clip
 * (2026-10-11) several tracks ended on one of them — a step five times the ball's speed, a
 * jump back up the picture — and the 3D fit, which trusts every point, read those deliveries
 * at 111 and 124 km/h with the rest near 70. The track is cut at the first step a receding
 * ball cannot make; everything before it is kept as measured.
 */
object FlightTrim {
    fun receding(run: List<BallSighting>, frameAspect: Float, stumps: Point2? = null): List<BallSighting> {
        if (run.size <= MIN_KEPT) return run
        val a = if (frameAspect > 0f) frameAspect.toDouble() else 1.0
        // The way across the picture the flight set out, from its first half.
        val half = run.subList(0, run.size / 2)
        val across = half.last().x - half.first().x
        val speeds = ArrayList<Double>()
        var lowest = run.first().y
        /*
         * AT THE STUMPS THE DELIVERY IS OVER. Once the ball has come down to the batter's
         * stumps, what is seen after it and well away from them — off to a side, or far up
         * the picture — is the bat, the pads or the batter: on a net clip the track ran on
         * to x 0.89, high up, and the fit read a 67 km/h ball as 43. Cut there.
         */
        var reachedStumps = false
        for (i in 1 until run.size) {
            val p = run[i - 1]
            val q = run[i]
            val dt = (q.timestampMs - p.timestampMs).coerceAtLeast(1L).toDouble()
            val step = hypot((q.x - p.x).toDouble(), (q.y - p.y) / a)
            val speed = step / dt
            if (i >= MIN_KEPT && speeds.size >= RECENT) {
                val recent = speeds.takeLast(RECENT).sorted()[RECENT / 2]
                val tooFast = speed > recent * MAX_SPEEDUP + SPEED_SLACK_PER_MS
                val turnedBack = abs(across) > TURN_MIN_ACROSS && (q.x - p.x) * across < 0 && abs(q.x - p.x) > TURN_MIN_STEP
                val roseTooFar = lowest - q.y > MAX_RISE
                if (tooFast || turnedBack || roseTooFar) return run.subList(0, i)
            }
            if (stumps != null) {
                if (reachedStumps && i >= MIN_KEPT &&
                    (abs(q.x - stumps.x) > AWAY_FROM_STUMPS_ACROSS || stumps.y - q.y > AWAY_FROM_STUMPS_UP)
                ) {
                    return run.subList(0, i)
                }
                if (abs(q.x - stumps.x) <= NEAR_STUMPS_ACROSS && abs(q.y - stumps.y) <= NEAR_STUMPS_DOWN) reachedStumps = true
            }
            speeds += speed
            if (q.y > lowest) lowest = q.y
        }
        return run
    }

    /** Never cut below this many points: the start of a flight is its best-measured part. */
    const val MIN_KEPT = 6
    private const val RECENT = 5
    private const val MAX_SPEEDUP = 2.5
    /** Frame widths per ms: a still far ball's pixel jitter, so a crawl is not "too fast". */
    private const val SPEED_SLACK_PER_MS = 0.00015
    private const val TURN_MIN_ACROSS = 0.03f
    private const val TURN_MIN_STEP = 0.008f
    /*
     * Frame heights a ball may rise off the far pitch. This was a far stump's height (0.035)
     * and cut the rise of a ball that bounced over the stumps to the batter's chest (0.05 on
     * a 4K net clip) — the fit then drew it hitting. Two and a half stump-heights now; a ball
     * coming back at the camera rises far more and is still cut.
     */
    private const val MAX_RISE = 0.09f

    /** Within this of the stumps' foot (frame widths across, frame heights down) the ball has arrived. */
    private const val NEAR_STUMPS_ACROSS = 0.06
    private const val NEAR_STUMPS_DOWN = 0.03

    /** After arriving, this far off to a side, or this far up the picture, is not the ball. */
    private const val AWAY_FROM_STUMPS_ACROSS = 0.09
    private const val AWAY_FROM_STUMPS_UP = 0.08
}
