package com.haraan.app.vision

/**
 * THE DELIVERY'S PATH, IN THE CAMERA'S OWN PICTURE.
 *
 * What gets drawn over the real footage after a ball — on the live viewfinder the moment
 * the ball is called, and over the slow-motion replay of the clip. Points are normalised
 * upright image coordinates, the same space every other overlay on the camera screen uses,
 * each with the moment (on the sightings' clock) the ball was there, so a replay can grow
 * the line in step with the ball in the video.
 *
 * Two ways to build one, and they are different claims:
 *
 *  - [fromFlight]: the fitted 3D flight sent back through the camera recovered from the
 *    pitch corners. Smooth, carried back to the hand and on to the stumps, and the stumps
 *    themselves placed in the picture. Needs a calibrated pitch.
 *  - [fromTrack]: the tracker's own sightings, split at the bounce. Only what was seen —
 *    no carrying back, no projection — because without a camera there is nothing honest to
 *    project with.
 */
data class ArPath(
    val points: List<ArPoint>,
    val bounce: Point2?,
    val bounceMs: Double?,
    /** Each stump as (foot, top) in the picture. Empty when the wicket is not known. */
    val stumps: List<Pair<Point2, Point2>>,
    /** True hitting, false missing, null not judged. */
    val hitting: Boolean?,
    /**
     * The channel between the wickets on the pitch, the stumps' width, bowler's end first: each
     * slice's (left, right) edges in the picture, with the moment the ball passes over it.
     * Empty without a fitted flight.
     */
    val shadow: List<ShadowSlice> = emptyList(),
    /** The bowler's-end stumps as (foot, top) in the picture; empty when not placed. */
    val bowlerStumps: List<Pair<Point2, Point2>> = emptyList(),
) {
    val startMs: Double get() = points.firstOrNull()?.ms ?: 0.0
    val endMs: Double get() = points.lastOrNull()?.ms ?: 0.0
    val isEmpty: Boolean get() = points.size < 2

    companion object {
        /** Points along a fitted flight: enough for a smooth curve at any size a phone draws. */
        private const val SAMPLES = 72

        /** Half the drawn tube's width in the world: a ball and a little, so it reads. */
        private const val TUBE_HALF_WIDTH_M = 0.03

        /** Half the shadow strip's width on the ground: about the stumps' own width. */
        const val SHADOW_HALF_WIDTH_M = 0.11

        /** Off a pitch, a ball rises at about this share of how fast it came down. */
        private const val PROJECTED_RESTITUTION = 0.5

        private const val GRAVITY = 9.81

        /** How far past the stumps' line a missing flight is drawn; see [fromFlight]. */
        private const val PAST_STUMPS_M = 1.2

        /**
         * The fitted flight, projected back into the picture through [quad]'s camera. Null
         * when the corners do not describe a camera.
         */
        fun fromFlight(flight: Flight3d, quad: PitchQuad, frameAspect: Float): ArPath? {
            val camera = PitchCamera.from(quad, frameAspect) ?: return null
            return fromFlight(flight, camera)
        }

        /** The fitted flight through a camera given directly (the stumps-only model). */
        fun fromFlight(flight: Flight3d, camera: PitchCamera): ArPath? {
            val start = flight.releaseMs
            /*
             * ON TO THE STUMPS, EVEN UNSEEN. Filmed from behind the bowler the ball is often
             * lost before it pitches, and the path used to stop in mid-air at the last
             * sighting. Past the bounce with no sightings of its own, the flight is carried
             * on as a ball comes off a pitch — same line and pace, rising at a typical
             * fraction of how fast it came down — to the stumps' line, drawn dashed as the
             * projection it is.
             */
            val towardsStumps = if (flight.inVy < 0) flight.bounceMs + (0.0 - flight.bounceY) / flight.inVy * 1000.0 else null
            /*
             * A BALL THAT MISSES IS DRAWN MISSING. Ended exactly on the stumps' line, a flight
             * seen from behind the bowler stops ON the stumps in the picture whether it passed
             * over them, beside them or into them — on a real net clip a ball that bounced over
             * the top and was caught at chest height read as "hitting". So a fitted flight that
             * misses is carried [PAST_STUMPS_M] beyond the line, where it plainly goes by.
             */
            val atLine = flight.stumpsMs?.takeIf { it > start }
            val pastLine = atLine?.takeIf { flight.hitsStumps == false }?.let { at ->
                flight.outVy?.takeIf { it < 0 }?.let { vy -> at + PAST_STUMPS_M / -vy * 1000.0 }
            }
            val end = pastLine ?: atLine
                ?: towardsStumps?.takeIf { !flight.hasOutLeg && it > start }
                ?: flight.lastSeenMs
            fun pos(ms: Double): Point3 {
                val s = (ms - flight.bounceMs) / 1000.0
                if (s <= 0 || flight.hasOutLeg) return flight.at(ms)
                return Point3(
                    flight.bounceX + flight.inVx * s,
                    flight.bounceY + flight.inVy * s,
                    (flight.inVzDown * PROJECTED_RESTITUTION * s - GRAVITY * s * s / 2).coerceAtLeast(0.0),
                )
            }
            val points = (0..SAMPLES).mapNotNull { i ->
                val ms = start + (end - start) * i / SAMPLES
                val at = pos(ms)
                val p = camera.project(at) ?: return@mapNotNull null
                // How wide a ball-sized tube is here, in frame widths: thick near the lens.
                val l = camera.project(Point3(at.x - TUBE_HALF_WIDTH_M, at.y, at.z))
                val r = camera.project(Point3(at.x + TUBE_HALF_WIDTH_M, at.y, at.z))
                ArPoint(
                    ms = ms,
                    x = p.x,
                    y = p.y,
                    width = if (l != null && r != null) kotlin.math.hypot(r.x - l.x, r.y - l.y) else 0.0,
                    leg = when {
                        ms <= flight.bounceMs -> ArLeg.IN
                        ms <= flight.lastSeenMs -> ArLeg.OUT
                        else -> ArLeg.PROJECTED
                    },
                )
            }
            val stumpX = PitchGeometry.STUMP_SET_WIDTH_M / 2.0 - 0.0175
            val stumps = listOf(-stumpX, 0.0, stumpX).mapNotNull { x ->
                val foot = camera.project(Point3(x, 0.0, 0.0)) ?: return@mapNotNull null
                val top = camera.project(Point3(x, 0.0, PitchGeometry.STUMP_HEIGHT_M)) ?: return@mapNotNull null
                foot to top
            }
            /*
             * THE CHANNEL BETWEEN THE WICKETS, as a broadcast lays it on the pitch: the stumps'
             * own width, from the bowler's stumps to the batter's, narrowing into the distance
             * like paint. It is what a ball is judged against — pitching in line, hitting in
             * line — so it stays put whatever the ball does; the ball and its shadow are
             * measured against it, not drawn by it. Unrolled in step with the ball: each slice
             * appears as the ball passes over it. (It was briefly the ball's own ground track,
             * and before that a strip from the bowler's stumps to the bounce — both read wrong
             * against the real net picture.)
             */
            fun passesMs(along: Double): Double {
                val inVy = flight.inVy
                val outVy = flight.outVy ?: inVy * 0.88
                val t = if (along >= flight.bounceY || inVy >= 0) {
                    if (inVy < 0) flight.bounceMs + (along - flight.bounceY) / inVy * 1000.0 else start
                } else {
                    if (outVy < 0) flight.bounceMs + (along - flight.bounceY) / outVy * 1000.0 else end
                }
                return t.coerceIn(start, end)
            }
            val channelHalf = PitchGeometry.STUMP_SET_WIDTH_M / 2.0
            val shadow = (0..SAMPLES).mapNotNull { i ->
                val along = PitchGeometry.STUMPS_TO_STUMPS_M * (1.0 - i.toDouble() / SAMPLES)
                val l = camera.project(Point3(-channelHalf, along, 0.0)) ?: return@mapNotNull null
                val r = camera.project(Point3(channelHalf, along, 0.0)) ?: return@mapNotNull null
                ShadowSlice(passesMs(along), l, r)
            }
            val bowlerStumps = listOf(-stumpX, 0.0, stumpX).mapNotNull { x ->
                val foot = camera.project(Point3(x, PitchGeometry.STUMPS_TO_STUMPS_M, 0.0)) ?: return@mapNotNull null
                val top = camera.project(Point3(x, PitchGeometry.STUMPS_TO_STUMPS_M, PitchGeometry.STUMP_HEIGHT_M)) ?: return@mapNotNull null
                foot to top
            }
            return ArPath(
                points = points,
                bounce = camera.project(Point3(flight.bounceX, flight.bounceY, 0.0)),
                bounceMs = flight.bounceMs,
                stumps = stumps,
                hitting = flight.hitsStumps,
                shadow = shadow,
                bowlerStumps = bowlerStumps,
            )
        }

        /**
         * The sightings as seen, split at the bounce the picture shows, with the wicket from
         * the lock when there is one.
         */
        fun fromTrack(
            track: List<BallSighting>,
            frameAspect: Float,
            wicket: WicketLock?,
            lbw: LbwProjection?,
        ): ArPath {
            val run = TrailGeometry.runs(track).lastOrNull().orEmpty()
            val bounceAt = BallPath.bounceIndex(run, frameAspect)
            val points = run.mapIndexed { i, s ->
                ArPoint(
                    ms = s.timestampMs.toDouble(),
                    x = s.x.toDouble(),
                    y = s.y.toDouble(),
                    leg = if (bounceAt == null || i <= bounceAt) ArLeg.IN else ArLeg.OUT,
                )
            }
            val stumps = wicket?.anchor?.let { anchor ->
                val left = anchor.baseLeft
                val right = anchor.baseRight
                // Height from the lock's own top when it has one; otherwise three times the
                // span, which is the stumps' real proportion (0.711 m over 0.2286 m).
                val rise = anchor.top?.let { t -> Point2(t.x - anchor.base.x, t.y - anchor.base.y) }
                    ?: Point2(0.0, -(right.x - left.x) * 3.1 * frameAspect.coerceAtLeast(0.1f))
                listOf(left, anchor.base, right).map { foot -> foot to Point2(foot.x + rise.x, foot.y + rise.y) }
            }.orEmpty()
            return ArPath(
                points = points,
                bounce = bounceAt?.let { Point2(run[it].x.toDouble(), run[it].y.toDouble()) },
                bounceMs = bounceAt?.let { run[it].timestampMs.toDouble() },
                stumps = stumps,
                hitting = when (lbw?.verdict) {
                    LbwVerdict.HITTING -> true
                    LbwVerdict.MISSING -> false
                    else -> null
                },
            )
        }
    }
}

/** One point of the drawn path. */
data class ArPoint(
    val ms: Double,
    val x: Double,
    val y: Double,
    val leg: ArLeg,
    /** The tube's width here in frame widths, from the 3D flight; 0 when only seen in 2D. */
    val width: Double = 0.0,
)

/** One slice of the flight's shadow on the pitch: its left and right edge in the picture. */
data class ShadowSlice(val ms: Double, val left: Point2, val right: Point2)

/** Which stretch of the delivery a point belongs to, which decides how it is drawn. */
enum class ArLeg {
    /** Hand to bounce. */
    IN,

    /** Bounce to the last sighting. */
    OUT,

    /** Beyond the last sighting, on to the stumps: a projection, drawn dashed. */
    PROJECTED,
}
