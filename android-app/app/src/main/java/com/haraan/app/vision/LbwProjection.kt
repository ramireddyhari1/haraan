package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot

/** What the projection can say about the one limb of an LBW it is able to look at. */
enum class LbwVerdict {
    /** The projected path passes inside the wicket by more than the uncertainty band. */
    HITTING,

    /** It passes outside by more than the band. */
    MISSING,

    /**
     * It passes near enough to the edge that the band covers both answers.
     *
     * The name is borrowed from the broadcast because it means the same thing and nobody
     * has to have it explained. What it does NOT mean here is the broadcast's protocol —
     * there is no on-field decision to defer to. It means this pipeline cannot separate
     * the two answers, which is a thing worth saying out loud rather than rounding away.
     */
    UMPIRES_CALL,

    /** No projection was possible, and [LbwProjection.basis] says what was missing. */
    UNAVAILABLE,
}

/**
 * One of the questions an LBW decision is made of, and whether this pipeline answered it.
 *
 * THE LIST IS THE HONESTY. An LBW is five questions and a single phone behind the bowler's
 * arm can address one and a half of them. A screen that shows a wicket-projection graphic
 * and says nothing else is claiming to have decided an LBW, which it has not — so every
 * question is listed, including, and especially, the ones marked unjudged.
 */
data class LbwLimb(
    val question: String,
    val answer: String,
    val judged: Boolean,
)

/**
 * Where the ball was going when it got to the stumps.
 *
 * WHAT MAKES THIS POSSIBLE AT ALL, when [FlightMetrics] has spent its whole life saying
 * metres are unavailable: a CONFIRMED stumps lock. Three stumps are 0.2286 m outside to
 * outside by the Laws and 0.711 m tall, so a lock on them fixes a real distance in two
 * directions AT THE WICKET'S OWN DEPTH. The projection asks exactly one question — where
 * does the flight cross the stump line — and the answer is at the wicket's depth, which is
 * the one place that scale is correct.
 *
 * It is not a ball-tracking system, and the limbs below say so in the product's own words
 * rather than in a comment nobody reads.
 */
data class LbwProjection(
    val verdict: LbwVerdict,
    /**
     * Signed distance from the middle stump to where the path crosses the stump line, in
     * metres. Positive towards [WicketAnchor.baseRight].
     */
    val offsetM: Double? = null,
    /** Half-width of the band either side of [offsetM] this pipeline cannot resolve. */
    val uncertaintyM: Double? = null,
    /** Height above the ground at the stump line, in metres, when a vertical scale exists. */
    val heightM: Double? = null,
    /** How far past the last real sighting the path had to be carried, in frame widths. */
    val extrapolationFw: Double? = null,
    val basis: String,
    val limbs: List<LbwLimb> = emptyList(),
) {
    val isDecided: Boolean get() = verdict == LbwVerdict.HITTING || verdict == LbwVerdict.MISSING
}

/**
 * Projecting a flight onto the stump line.
 *
 * Pure Kotlin and pure geometry: two lines in a plane, an intersection, and an error bar.
 * No OpenCV, no Android, no clock — so every branch of it is provable at a desk, which for
 * a thing that puts the word HITTING on a screen is not optional.
 */
object LbwProjector {

    /** Half the wicket, in metres. The gate the centre of the ball is tested against. */
    const val HALF_WICKET_M = PitchGeometry.STUMP_SET_WIDTH_M / 2.0

    /** A cricket ball's radius. Part of it clipping the stump is still out. */
    const val BALL_RADIUS_M = 0.036

    /** Centre-of-ball offset beyond which no part of the ball can be on the wicket. */
    const val HITTING_LIMIT_M = HALF_WICKET_M + BALL_RADIUS_M

    /** And above this the ball is over the top of the stumps. */
    const val OVER_THE_TOP_M = PitchGeometry.STUMP_HEIGHT_M + BALL_RADIUS_M

    /**
     * How far past the fitted points the path may be carried, as a multiple of their own
     * extent along it.
     *
     * A line fitted to twenty centimetres of flight and extended two metres is not a
     * projection, it is a wish: every milliradian of fitting error becomes centimetres at
     * the far end. One and a half keeps the extrapolation comparable to the evidence.
     */
    const val MAX_EXTRAPOLATION_RATIO = 1.5

    /** Below this the two lines are near enough parallel that an intersection means nothing. */
    const val MIN_CROSSING_SINE = 0.15

    /** Nothing this pipeline produces is more certain than this, whatever the arithmetic says. */
    const val MIN_UNCERTAINTY_M = 0.02

    /** Every limb of an LBW this cannot address, in one place, phrased for a person. */
    private val UNJUDGED = listOf(
        LbwLimb(
            "Did it pitch in line?",
            "not judged — needs the pitch itself calibrated, not just the wicket",
            judged = false,
        ),
        LbwLimb(
            "Where did it strike the pad?",
            "not judged — a single camera sees a centroid, not how far the pad is from the stumps",
            judged = false,
        ),
        LbwLimb(
            "How high was the impact?",
            "not judged — needs the impact point in three dimensions",
            judged = false,
        ),
        LbwLimb(
            "Did it hit the bat first?",
            "not judged — nothing here listens, and an edge is invisible to a centroid",
            judged = false,
        ),
    )

    /**
     * Where [track] would have crossed the stump line, given [lock].
     *
     * @param track the current flight's sightings, oldest first
     * @param lock a wicket lock. Anything but a measurable STUMPS lock returns UNAVAILABLE
     *   with the reason, because a stone has no width and a coasting lock has no fixed place.
     */
    fun project(track: List<BallSighting>, lock: WicketLock?): LbwProjection {
        if (lock == null) {
            return unavailable("no wicket locked — lock the stumps before anything can be projected onto them")
        }
        if (lock.kind != WicketKind.STUMPS) {
            return unavailable("locked onto a stone, which has no width — a projection needs the stumps")
        }
        if (!lock.isMeasurable) {
            return unavailable(
                when {
                    lock.state != WicketTrackState.CONFIRMED &&
                        lock.state != WicketTrackState.REACQUIRE ->
                        "the wicket lock is ${lock.state.name.lowercase().replace('_', ' ')}"
                    lock.jitter > WicketTracker.MAX_MEASURABLE_JITTER ->
                        "the wicket lock is moving about too much to measure from"
                    else -> "the wicket is too small in frame to measure from — move closer"
                },
            )
        }

        val scaleAcross = lock.metresPerUnitAcross()
            ?: return unavailable("the wicket lock carries no scale")

        val aspect = lock.aspect
        val run = TrailGeometry.runs(track).lastOrNull().orEmpty()

        // After the bounce when there is one, because that is the path that reaches the
        // stumps. Before it the ball is still in the air on a different line, and fitting
        // the whole flight would average a delivery's swing into its turn and project
        // neither.
        val bounceAt = BallPath.bounceIndex(run, aspect)
        val segment = if (bounceAt != null) run.drop(bounceAt) else run
        if (segment.size < BallPath.MIN_FOR_LINE) {
            return unavailable(
                if (bounceAt == null) {
                    "needs ${BallPath.MIN_FOR_LINE} sightings in one flight, have ${segment.size}"
                } else {
                    "only ${segment.size} sightings after the bounce"
                },
            )
        }

        val flat = segment.map { BallPath.flatten(it, aspect) }
        val path = BallPath.fitLine(flat) ?: return unavailable("the sightings do not describe a path")

        // The stump line, as a point and a direction, in the same corrected space.
        val base = BallPath.flatten(lock.anchor.base, aspect)
        val left = BallPath.flatten(lock.anchor.baseLeft, aspect)
        val right = BallPath.flatten(lock.anchor.baseRight, aspect)
        val stumpDx = right.x - left.x
        val stumpDy = right.y - left.y
        val stumpLen = hypot(stumpDx, stumpDy)
        if (stumpLen < 1e-9) return unavailable("the locked stumps have no width in frame")
        val ux = stumpDx / stumpLen
        val uy = stumpDy / stumpLen

        /*
         * INTERSECTION, not "extrapolate to the wicket's y".
         *
         * Solving for the y of the base looks simpler and quietly assumes the stump line is
         * horizontal in the picture. On a tripod head that is a degree or two out, or after
         * the phone has been turned, it is not — and a projection that assumes it would be
         * wrong by the tilt times the extrapolation distance, which is centimetres at the
         * stumps for a tilt nobody would notice on screen.
         */
        val cross = path.dirX * uy - path.dirY * ux
        if (abs(cross) < MIN_CROSSING_SINE) {
            return unavailable("the flight runs along the stump line, so it never crosses it")
        }

        val t = ((base.x - path.pointX) * uy - (base.y - path.pointY) * ux) / cross
        val hit = path.at(t)

        // The projection must run FORWARD from the ball, and not much further than the
        // evidence it was fitted to.
        val lastAlong = path.projectionOf(flat.last())
        val carried = t - lastAlong
        if (carried < 0.0) {
            return unavailable("the wicket is behind the ball — the flight crossed the line already")
        }
        val reach = path.extent.coerceAtLeast(1e-9)
        if (carried > reach * MAX_EXTRAPOLATION_RATIO) {
            return unavailable(
                "the ball is still %.2f frame widths short of the stumps, more than this much path can carry"
                    .format(carried),
            )
        }

        val offsetFw = (hit.x - base.x) * ux + (hit.y - base.y) * uy
        val offsetM = offsetFw * scaleAcross

        /*
         * THE ERROR BAR IS THE PRODUCT.
         *
         * Three things, added because they are independent and all of them push the
         * crossing point sideways: how far the sightings sat off their own fitted line,
         * how far that line had to be carried past them — which multiplies the fitting
         * error — and how much the wicket lock itself is moving about, since the line is
         * being crossed with a landmark that is not perfectly still.
         */
        val fitError = path.residual * (1.0 + carried / reach)
        val lockError = lock.jitter.toDouble()
        val uncertaintyM = ((fitError + lockError) * scaleAcross).coerceAtLeast(MIN_UNCERTAINTY_M)

        // Height needs the vertical scale, which needs a known stump top. Absent, this is
        // simply not reported — never assumed to be low.
        val heightM = lock.metresPerUnitDown()?.let { scaleDown ->
            val upDx = -uy
            val upDy = ux
            // Which way is up in the picture: the side the stump top is on. Deriving it
            // from the lock rather than assuming negative y is what makes this survive the
            // phone being turned upside down on a clamp.
            val top = lock.anchor.top?.let { BallPath.flatten(it, aspect) }
            val sign = if (top != null && ((top.x - base.x) * upDx + (top.y - base.y) * upDy) < 0.0) -1.0 else 1.0
            ((hit.x - base.x) * upDx * sign + (hit.y - base.y) * upDy * sign) * scaleDown
        }

        val verdict = verdictFor(offsetM, uncertaintyM, heightM)

        return LbwProjection(
            verdict = verdict,
            offsetM = offsetM,
            uncertaintyM = uncertaintyM,
            heightM = heightM,
            extrapolationFw = carried,
            basis = buildString {
                append("straight-line projection of ")
                append(if (bounceAt != null) "the path after the bounce" else "the whole flight")
                append(", scaled by the locked wicket at its own depth")
                if (bounceAt == null) append(" — no bounce found, so any deviation off the pitch is not in this")
            },
            limbs = listOf(
                LbwLimb(
                    "Would it have hit the stumps?",
                    when (verdict) {
                        LbwVerdict.HITTING -> "yes, %.0f cm from the middle stump".format(abs(offsetM) * 100)
                        LbwVerdict.MISSING -> "no, %.0f cm outside".format((abs(offsetM) - HALF_WICKET_M) * 100)
                        else -> "too close to call — within %.0f cm either way".format(uncertaintyM * 100)
                    },
                    judged = true,
                ),
            ) + UNJUDGED,
        )
    }

    private fun verdictFor(offsetM: Double, uncertaintyM: Double, heightM: Double?): LbwVerdict {
        // Over the top, and certainly so. Reported as MISSING rather than as its own
        // verdict because for the one question this answers it is the same answer.
        if (heightM != null && heightM - uncertaintyM > OVER_THE_TOP_M) return LbwVerdict.MISSING

        val offset = abs(offsetM)
        return when {
            offset + uncertaintyM <= HITTING_LIMIT_M -> LbwVerdict.HITTING
            offset - uncertaintyM >= HITTING_LIMIT_M -> LbwVerdict.MISSING
            else -> LbwVerdict.UMPIRES_CALL
        }
    }

    private fun unavailable(reason: String) = LbwProjection(
        verdict = LbwVerdict.UNAVAILABLE,
        basis = reason,
        limbs = listOf(
            LbwLimb("Would it have hit the stumps?", reason, judged = false),
        ) + UNJUDGED,
    )
}
