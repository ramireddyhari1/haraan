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
) {
    val startMs: Double get() = points.firstOrNull()?.ms ?: 0.0
    val endMs: Double get() = points.lastOrNull()?.ms ?: 0.0
    val isEmpty: Boolean get() = points.size < 2

    companion object {
        /** Points along a fitted flight: enough for a smooth curve at any size a phone draws. */
        private const val SAMPLES = 72

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
            val end = flight.stumpsMs?.takeIf { it > start } ?: flight.lastSeenMs
            val points = (0..SAMPLES).mapNotNull { i ->
                val ms = start + (end - start) * i / SAMPLES
                val p = camera.project(flight.at(ms)) ?: return@mapNotNull null
                ArPoint(
                    ms = ms,
                    x = p.x,
                    y = p.y,
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
            return ArPath(
                points = points,
                bounce = camera.project(Point3(flight.bounceX, flight.bounceY, 0.0)),
                bounceMs = flight.bounceMs,
                stumps = stumps,
                hitting = flight.hitsStumps,
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
data class ArPoint(val ms: Double, val x: Double, val y: Double, val leg: ArLeg)

/** Which stretch of the delivery a point belongs to, which decides how it is drawn. */
enum class ArLeg {
    /** Hand to bounce. */
    IN,

    /** Bounce to the last sighting. */
    OUT,

    /** Beyond the last sighting, on to the stumps: a projection, drawn dashed. */
    PROJECTED,
}
