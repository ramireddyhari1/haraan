package com.haraan.app.vision

import com.haraan.app.camera.deliveryCorridor

/**
 * THE BALL, FRAME BY FRAME — the one copy of it.
 *
 * The live camera screen and [DeliveryChain] (the clip screen and the tracking scoreboard)
 * each used to carry their own version of this: when the corridor is rebuilt, when a flight
 * is done, whether it came at the stumps, whether a later flight replaces the one called. A
 * fix measured on the scoreboard then had to be copied into the live screen by hand, and
 * twice the copies had drifted apart. Both now step the ball through here, so the board
 * scores what the live screen does.
 *
 * What stays with each caller is what genuinely differs: the live screen's heat and cadence
 * rules and its drawing, the chain's 3D fit of what is called.
 *
 * Not thread-safe; called on the one analysis thread, like the tracker it wraps.
 */
class BallStep(
    val tracker: OpenCvBallTracker,
    /**
     * After a call, keep the tracker running (the live screen: a stray few points must not
     * end the clip's looking) — or start it afresh for the next ball (a clip, an Auto segment).
     */
    private val keepLooking: Boolean,
) {
    /** The corridor the ball is searched in: left, top, right, bottom, towards x, towards y. */
    var corridor: FloatArray? = null
        private set
    private var lastCorridorMs = Long.MIN_VALUE / 2

    /** The flight called and still held, while [keepLooking]; a fresh tracker holds nothing. */
    var called: List<BallSighting>? = null
        private set

    /** What one frame came to. [called] is set only on the frame a flight is called or replaced. */
    class Result(
        val sighting: BallSighting?,
        val called: List<BallSighting>?,
        /** A finished flight that never came at the stumps was let go, and the tracker restarted. */
        val strayDropped: Boolean,
    )

    /** A fresh ball: the tracker restarted, nothing called. The corridor is kept. */
    fun reset() {
        tracker.reset()
        called = null
    }

    /**
     * Re-derived until there is one, then once a second; from the steadied stumps when there
     * are any, which outlive the live lock the bowler's run-up knocks out. A lock flickering
     * for a moment keeps the corridor it had — dropping it would restart the tracker, perhaps
     * mid-delivery. Only no lock at all goes back to the whole frame.
     */
    fun refreshCorridor(steady: WicketLock?, live: WicketLock?, camera: CameraIntrinsics?, uprightWidthPx: Int, aspect: Float, nowMs: Long) {
        if (corridor != null && nowMs - lastCorridorMs < CORRIDOR_REFRESH_MS) return
        lastCorridorMs = nowMs
        val c = deliveryCorridor(steady ?: live, camera, uprightWidthPx, aspect)
        if (c != null) {
            corridor = c
            tracker.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
        } else if (live == null && steady == null) {
            corridor = null
            tracker.setCorridor(null, null, null, null, null)
        }
    }

    /**
     * One frame. With no stumps lock the ball is tracked but nothing is called: "done" and
     * "came at the stumps" both need to know where the stumps are.
     */
    fun frame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        timestampMs: Long,
        lock: WicketLock?,
        aspect: Float,
    ): Result {
        val sighting = tracker.onFrame(luma, width, height, rowStride, rotationDegrees, timestampMs)
        lock ?: return Result(sighting, null, false)

        // Asked on every frame, sighting or not: going quiet IS the signal. A flight a newer
        // track replaced before it went quiet counts as finished now.
        val replaced = tracker.takeFinished()?.takeIf { fin ->
            FlightEnd.check(fin, fin.last().timestampMs + FlightEnd.QUIET_MS, aspect, lock) != null
        }
        val flight = replaced ?: tracker.track()
        if (replaced == null && FlightEnd.check(flight, timestampMs, aspect, lock) == null) {
            return Result(sighting, null, false)
        }

        // Done, but never came at the stumps: someone walking about, not a delivery. A called
        // ball keeps its trail; a stray afterwards just cannot replace it.
        if (!FlightEnd.travelsToward(flight, lock, aspect)) {
            val drop = replaced == null && called == null
            if (drop) tracker.reset()
            return Result(sighting, null, drop)
        }

        /*
         * A CALL IS NOT THE END OF LOOKING. A person walking past the stumps can make a short
         * "ball" of a swinging hand that is called; a real ball a moment later must still be
         * able to take over. A LATER, separate flight at least as long replaces the call —
         * the same flight running on past its bounce does not.
         */
        val held = called
        val newly = when {
            held == null -> flight
            flight.isNotEmpty() && held.isNotEmpty() &&
                flight.first().timestampMs > held.last().timestampMs &&
                flight.size >= maxOf(held.size, SUPERSEDE_MIN_POINTS) -> flight
            else -> null
        } ?: return Result(sighting, null, false)

        if (keepLooking) {
            called = newly
        } else if (replaced == null) {
            // A fresh start for the next ball, as an Auto segment does.
            reset()
        }
        return Result(sighting, newly, false)
    }

    companion object {
        const val CORRIDOR_REFRESH_MS = 1_000L

        /** A later flight needs at least this many points to replace one already called. */
        const val SUPERSEDE_MIN_POINTS = 5
    }
}
