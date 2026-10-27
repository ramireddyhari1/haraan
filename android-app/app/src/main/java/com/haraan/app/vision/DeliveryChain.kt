package com.haraan.app.vision

import com.haraan.app.camera.battersEndOnly
import com.haraan.app.camera.bowlersEndOf
import com.haraan.app.camera.deliveryCorridor

/**
 * A flight the chain called: the track as the tracker held it, the stumps it was measured
 * from, and what the 3D fit made of it. [flight] and [path] are null when the fit refused it
 * — the clip screen drops those; the scoreboard counts them.
 */
class CalledDelivery(
    val calledMs: Long,
    val track: List<BallSighting>,
    val lock: WicketLock,
    val flight: Flight3d?,
    val path: ArPath?,
)

/**
 * THE PER-FRAME CHAIN OF A FILMED CLIP, with no decoder and no Android in it: colour stump
 * detection → the batter's end only → the wicket tracker and its steadied lock → the delivery
 * corridor → the ball tracker → each delivery called as the camera calls it and fitted in 3D
 * through the ground both sets of stumps give.
 *
 * [ClipDeliveryAnalyzer] feeds it decoded frames on a phone; the tracking scoreboard feeds it
 * the same frames from raw streams on a desk. One chain, so a score is a score of what ships.
 *
 * Frames are the camera's analysis frame: sideways 1280x720 luma and a 640x360 Cb plane.
 */
class DeliveryChain(
    /** Full-resolution measurements of the far stumps' span, when the caller makes them. */
    private val farSpans: () -> Collection<Double> = { emptyList() },
    private val log: (String) -> Unit = {},
    /** Look for the batter's stumps where the bowler's say ([FarWicketSearch]). Off only to compare on the bench. */
    private val predictFar: Boolean = true,
) {
    val aspect = ClipFrames.UPRIGHT_W.toFloat() / ClipFrames.UPRIGHT_H

    private val stumps = ColourStumpDetector()
    private val wickets = WicketTracker()
    private val ball = OpenCvBallTracker()
    private val camera = CameraIntrinsics(ASSUMED_FOCAL_FW, "assumed")
    private val still = FrameMotion(0f, 0f, 1f, 0f, 30, 32)
    private val nearEnd = BowlersEndFilter()
    private val steadyLock = SteadyWicket()
    private val farSearch = FarWicketSearch(ASSUMED_FOCAL_FW)
    private var lock: WicketLock? = null
    private var corridor: FloatArray? = null
    private var lastStumpsMs = Long.MIN_VALUE / 2
    private var lastCorridorMs = Long.MIN_VALUE / 2
    private var stumpsChecks = 0

    /** The bowler's stumps' feet, once seen. */
    var bowlersEnd: BowlersEnd? = null
        private set

    /** Where to measure the far stumps at full size: base x, base y, span (frame widths). */
    @Volatile
    var farStumpsAt: DoubleArray? = null
        private set

    /** Nanoseconds spent on stumps and on the ball, for the timing log. */
    var stumpsNs = 0L
        private set
    var ballNs = 0L
        private set

    /** The stumps deliveries are measured from: the steadied lock, else this frame's. */
    val measuringLock: WicketLock? get() = steadyLock.value() ?: lock
    val stumpsFound: Boolean get() = steadyLock.value() != null

    /** A warm-up frame: the stumps alone, every one, before the ball is looked for. */
    fun warm(y: ByteArray, u: ByteArray, ts: Long) = feedStumps(y, u, ts)

    /** Whether the stumps are held well enough to build the corridor the ball is searched in. */
    fun readyToTrack(): Boolean = deliveryCorridor(steadyLock.value() ?: lock, camera, ClipFrames.UPRIGHT_W, aspect) != null

    /** One analysed frame. Returns the deliveries called on it (usually none, at most two). */
    fun frame(y: ByteArray, u: ByteArray, ts: Long): List<CalledDelivery> {
        // Stumps thirty a second until confirmed, then ten a second. Stumps do not move:
        // every frame of a 120 fps clip was a fifth of the whole wait.
        if (ts - lastStumpsMs >= if (lock?.state == WicketTrackState.CONFIRMED) STUMPS_EVERY_MS else STUMPS_SEARCH_EVERY_MS) {
            lastStumpsMs = ts
            feedStumps(y, u, ts)
        }
        // Re-derived until there is one, then once a second; built on the steadied stumps,
        // which outlive the live lock the run-up knocks out.
        if (corridor == null || ts - lastCorridorMs >= 1_000L) {
            lastCorridorMs = ts
            val steady = steadyLock.value()
            val c = deliveryCorridor(steady ?: lock, camera, ClipFrames.UPRIGHT_W, aspect)
            if (c != null) {
                corridor = c
                ball.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
            } else if (lock == null && steady == null) {
                corridor = null
                ball.setCorridor(null, null, null, null, null)
            }
        }
        val t0 = System.nanoTime()
        ball.onFrame(y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, ts)
        ballNs += System.nanoTime() - t0
        val l = lock ?: return emptyList()
        // A flight a newer track replaced before it went quiet is finished now.
        val replaced = ball.takeFinished()?.takeIf { fin ->
            FlightEnd.check(fin, fin.last().timestampMs + FlightEnd.QUIET_MS, aspect, l) != null
        }
        val track = replaced ?: ball.track()
        if (replaced == null && FlightEnd.check(track, ts, aspect, l) == null) return emptyList()
        // Measured from the steadied stumps, not this frame's: see [SteadyWicket].
        val called = call(track, (steadyLock.value() ?: l).withFarSpan(), ts)
        // A fresh start for the next ball, as an Auto segment does.
        if (replaced == null) ball.reset()
        return listOfNotNull(called)
    }

    /** A flight still in the air when the clip ends, called as it stands. */
    fun finish(): CalledDelivery? {
        val l = measuringLock ?: return null
        val tail = ball.track()
        if (tail.size < FlightEnd.MIN_FLIGHT_POINTS) return null
        return call(tail, l.withFarSpan(), tail.last().timestampMs)
    }

    /** The ball tracker's own rejection counts, for the scoreboard. */
    fun ballDiagnostics() = ball.diagnostics()

    /** This frame's stumps lock and the corridor the ball is searched in, for the scoreboard. */
    val currentLock: WicketLock? get() = lock
    val currentCorridor: FloatArray? get() = corridor

    /** The ball tracker's current track, for the scoreboard. */
    fun ballTrack(): List<BallSighting> = ball.track()

    fun release() {
        stumps.release()
        ball.release()
    }

    private fun feedStumps(y: ByteArray, u: ByteArray, ts: Long) {
        val t0 = System.nanoTime()
        stumpsChecks++
        val seen = stumps.detectCandidates(
            y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, u, ClipFrames.SENSOR_W / 2, 1,
            focus = wickets.focus(),
            allowSearch = searchAllowed(lock, bowlersEnd != null, stumpsChecks),
        )
        val nearNow = bowlersEndOf(seen, camera)
        nearNow?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
        /*
         * NOTHING CONFIRMED, BUT THE BOWLER'S STUMPS ARE IN VIEW: look where they say the
         * batter's must be. See [FarWicketSearch.offer]; [predictFar] off is for the bench.
         */
        val far = battersEndOnly(seen, camera, bowlersEnd).let { found ->
            if (!predictFar) found else farSearch.offer(
                found, lock, bowlersEnd ?: nearNow,
                y, ClipFrames.SENSOR_W, ClipFrames.SENSOR_H, ClipFrames.SENSOR_W, 90, aspect,
            ) { s, near -> battersEndOnly(s, camera, near) }
        }
        lock = wickets.onFrame(far, still, aspect, ts)
        steadyLock.add(lock, ts, nearNow)
        steadyLock.value()?.takeIf { it.isMeasurable }?.let { l ->
            farStumpsAt = doubleArrayOf(l.anchor.base.x, l.anchor.base.y, l.anchor.span(aspect).toDouble())
        }
        stumpsNs += System.nanoTime() - t0
    }

    /** A called delivery, fitted in 3D through the ground the stumps give; null when too short. */
    private fun call(track: List<BallSighting>, lock: WicketLock, calledMs: Long): CalledDelivery? {
        if (track.size < FlightEnd.MIN_FLIGHT_POINTS) return null
        val ground = PitchGround.best(lock, camera, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H, tilt = null, bowlersEnd = bowlersEnd)
            ?.pitchCamera(ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
        val m = ground?.let { FlightMetrics.of(track, aspect, quad = null, wicket = lock, ground = it) }
        val f = m?.flight3d
        log(
            "called ${track.size} pts ${track.first().timestampMs}..${track.last().timestampMs}ms y ${"%.3f".format(track.first().y)}..${"%.3f".format(track.maxOf { it.y })} " +
                "lock=${"%.3f,%.3f".format(lock.anchor.base.x, lock.anchor.base.y)} near=${bowlersEnd?.base} " +
                "fit=${f?.let { "bounce %.2f out=${it.hasOutLeg} hits=${it.hitsStumps} ground %.1f".format(it.bounceY, it.groundKmh) }
                    ?: refusal(track, lock, ground)} " +
                "trimmed=${FlightTrim.receding(TrailGeometry.runs(track).lastOrNull().orEmpty(), aspect, lock.anchor.base).size} " +
                "atStumps=${FlightMetrics.reachedStumps(FlightTrim.receding(track, aspect, lock.anchor.base), lock, aspect)} span=${"%.4f".format(lock.anchor.span(aspect))} measurable=${lock.isMeasurable} " +
                // BENCH_TRACK (scoreboard only) prints every sighting, not just the last eight.
                "tail=" + (if (System.getenv("BENCH_TRACK") != null) track else track.takeLast(8)).joinToString(";") { "%d,%.3f,%.3f".format(it.timestampMs, it.x, it.y) },
        )
        val path = if (f != null && ground != null) ArPath.fromFlight(f, ground)?.takeIf { !it.isEmpty } else null
        return CalledDelivery(calledMs, track, lock, f, path)
    }

    /** Why the 3D fit refused [track], as [FlightMetrics.of] would run it — for the log only. */
    private fun refusal(track: List<BallSighting>, lock: WicketLock, ground: PitchCamera?): String {
        ground ?: return "no ground"
        val run = FlightTrim.receding(TrailGeometry.runs(track).lastOrNull().orEmpty(), aspect, lock.anchor.base)
        return when (val r = QuadSpeed.fit(run, ground, null, aspect, FlightMetrics.reachedStumps(run, lock, aspect))) {
            is QuadSpeed.FitResult.Refused -> r.reason
            is QuadSpeed.FitResult.Ok -> "fitted alone but not through FlightMetrics"
        }
    }

    /**
     * The lock with its span set to the full-resolution measurement — the median, once at
     * least [FAR_SPAN_MIN] frames gave one — about the same centre.
     */
    private fun WicketLock.withFarSpan(): WicketLock {
        val list = farSpans().toList()
        if (list.size < FAR_SPAN_MIN) return this
        val span = list.sorted()[list.size / 2]
        val now = anchor.span(aspect).toDouble()
        if (now <= 0.0) return this
        val k = span / now
        val c = anchor.base
        fun scaled(p: Point2) = Point2(c.x + (p.x - c.x) * k, c.y + (p.y - c.y) * k)
        log("far stumps %.5f -> %.5f from %d full-size frames".format(now, span, list.size))
        return copy(anchor = anchor.copy(baseLeft = scaled(anchor.baseLeft), baseRight = scaled(anchor.baseRight)))
    }

    companion object {
        /**
         * Whether a frame may search the whole picture for stumps, or only look where they are
         * held — the live camera's rule. The search is most of a 40 ms detection; a held place
         * is checked in a few. Searched until the lock is sure and the bowler's end has been
         * seen, and one call in four while the lock is lost.
         */
        private fun searchAllowed(lock: WicketLock?, nearEndSeen: Boolean, call: Int): Boolean = when {
            lock == null || !nearEndSeen -> true
            lock.source == WicketLockSource.MANUAL -> false
            lock.state == WicketTrackState.TENTATIVE -> true
            lock.state == WicketTrackState.TEMPORARILY_LOST -> call % 4 == 0
            else -> false
        }

        private const val STUMPS_EVERY_MS = 100L
        /** Stumps looked for this often until the lock is confirmed. */
        private const val STUMPS_SEARCH_EVERY_MS = 33L
        private const val ASSUMED_FOCAL_FW = 1.4
        private const val FAR_SPAN_MIN = 3
    }
}
