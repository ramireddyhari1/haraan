package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * The lifecycle of a wicket lock.
 *
 * WHY THE SAME FIVE WORDS AS [TrackingState] AND NOT THE SAME ENUM. A ball and a wicket
 * are tracked for opposite reasons. The ball is a fast thing seen briefly and the states
 * describe a flight; the wicket is a stationary thing seen for an hour and the states
 * describe how much a stationary claim can still be trusted. Sharing an enum would mean
 * one screen's readout could be fed the other's value and nobody would notice.
 */
enum class WicketTrackState {
    /** Nothing locked. Every frame is a fresh search. */
    LOST,

    /** A candidate has been seen; being checked against the next frames before it counts. */
    TENTATIVE,

    /** Seen consistently, in one place, for long enough. This is the only state to measure from. */
    CONFIRMED,

    /** Was confirmed, is not being seen now. Carried on camera motion alone, and ageing. */
    TEMPORARILY_LOST,

    /** Seen again after coasting, on the frame that found it. Confirmed again if it holds. */
    REACQUIRE,
}

/** Whether a lock came from the detector or from somebody pointing at the ground. */
enum class WicketLockSource {
    DETECTED,

    /**
     * Placed by hand, and trusted above any detection — the same rule [QuadSource.TAPPED]
     * already follows. A person looking at the wicket knows where it is; a contour sweep
     * is guessing from brightness.
     */
    MANUAL,
}

/**
 * What KIND of wicket is locked, which is the same as asking whether it carries a scale.
 *
 * Kept as its own field rather than inferred from [WicketLockSource] because the two are
 * independent: a tapped pair of stump bases is manual AND carries 0.2286 m, and a detected
 * stone is automatic and carries nothing.
 */
enum class WicketKind {
    /** Three bars, or two tapped stumps. The span is [PitchGeometry.STUMP_CENTRES_SPAN_M]. */
    STUMPS,

    /** A stone, a brick, a stick. Fixes a place on the ground and no distance whatsoever. */
    STONE,
}

/**
 * Where the wicket is, as two points on the ground and optionally one above them.
 *
 * THE TWO POINTS ARE THE OUTER STUMPS' CENTRES, at their feet. That is what every detector
 * measures, what the replay and the calibration already assumed, and what a hand-placed
 * lock is converted to (the operator taps the outside edges, which are easier to see).
 * Mixing the two conventions was an 18% scale error; see [PitchGeometry.STUMP_CENTRES_SPAN_M].
 *
 * TWO POINTS RATHER THAN A CENTRE AND A WIDTH, and this is not tidiness. A scalar width
 * only describes a horizontal thing. The moment the phone is turned, or rolls on a cheap
 * tripod head, the stump line is no longer horizontal in the frame and a scalar span
 * describes nothing — while two points rotate correctly and keep meaning what they meant.
 * The rotation handling in this file is only possible because of this shape.
 */
data class WicketAnchor(
    val baseLeft: Point2,
    val baseRight: Point2,
    /**
     * The top of a stump, when one is known.
     *
     * Optional because only a bar detection or a third tap can supply it, and because it
     * buys a specific thing — [PitchGeometry.STUMP_HEIGHT_M] gives a scale in the vertical
     * direction, which the base pair alone cannot, since those two points are collinear
     * and a line fixes distances only along itself.
     */
    val top: Point2? = null,
) {
    val base: Point2
        get() = Point2((baseLeft.x + baseRight.x) / 2.0, (baseLeft.y + baseRight.y) / 2.0)

    /** Outside to outside, in FRAME WIDTHS, with y converted into the same unit as x. */
    fun span(aspect: Float): Float {
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        return hypot(baseRight.x - baseLeft.x, (baseRight.y - baseLeft.y) / a).toFloat()
    }

    /** Base to top, in frame widths, or null when no top is known. */
    fun rise(aspect: Float): Float? {
        val t = top ?: return null
        val a = if (aspect > 0f) aspect.toDouble() else 1.0
        val b = base
        return hypot(t.x - b.x, (t.y - b.y) / a).toFloat()
    }

    /** Every point this anchor is made of, for transforming them all the same way. */
    fun map(transform: (Point2) -> Point2) = WicketAnchor(
        baseLeft = transform(baseLeft),
        baseRight = transform(baseRight),
        top = top?.let(transform),
    )

    /**
     * The same wicket after the picture is turned [quarterTurnsCw] quarter turns clockwise.
     *
     * EXACT, not approximate, for the part of the scene both frames share. Rotating a W by
     * H image a quarter turn clockwise puts the pixel at (xW, yH) at (H - yH, xW) in an
     * image that is now H wide and W tall — which normalises to (1 - y, x). No aspect ratio
     * appears in it, because normalising against the new frame's own dimensions has already
     * accounted for the swap.
     *
     * The field of view also crops differently across a turn, so a point near an edge may
     * end up outside the new frame. That is why the tracker drops to [WicketTrackState.REACQUIRE]
     * after a rotation instead of carrying on as though nothing happened: the mapping is
     * right, and it is still only a prediction of where to look.
     */
    fun rotated(quarterTurnsCw: Int): WicketAnchor {
        val turns = ((quarterTurnsCw % 4) + 4) % 4
        if (turns == 0) return this
        return map { p ->
            when (turns) {
                1 -> Point2(1.0 - p.y, p.x)
                2 -> Point2(1.0 - p.x, 1.0 - p.y)
                else -> Point2(p.y, 1.0 - p.x)
            }
        }
    }

    /** Weighted blend towards [other]. 0 keeps this one, 1 takes that one. */
    fun blend(other: WicketAnchor, weight: Float): WicketAnchor {
        val w = weight.toDouble().coerceIn(0.0, 1.0)
        fun mix(a: Point2, b: Point2) = Point2(a.x + (b.x - a.x) * w, a.y + (b.y - a.y) * w)
        return WicketAnchor(
            baseLeft = mix(baseLeft, other.baseLeft),
            baseRight = mix(baseRight, other.baseRight),
            top = when {
                top == null -> other.top
                other.top == null -> top
                else -> mix(top, other.top)
            },
        )
    }

    companion object {
        /** The anchor a set of three bars describes. The tops are averaged into one. */
        fun of(set: StumpSet): WicketAnchor = WicketAnchor(
            baseLeft = set.baseLeft,
            baseRight = set.baseRight,
            // The MIDDLE stump's top, not the tallest: the outer two are foreshortened by
            // different amounts and averaging all three would put the point at a height
            // the wicket does not have at any of its three positions.
            top = Point2(set.middle.centreX.toDouble(), set.middle.topY.toDouble()),
        )

        /**
         * The anchor a single lump describes.
         *
         * Its left and right edges, which look like a span and are not one: a stone has no
         * agreed width, so [WicketKind.STONE] is what stops anything downstream reading
         * metres out of it. The two points exist so a stone rotates and tracks like
         * everything else, not so it can be measured.
         */
        fun of(mark: StoneMark): WicketAnchor = WicketAnchor(
            baseLeft = Point2((mark.centreX - mark.width / 2f).toDouble(), mark.baseY.toDouble()),
            baseRight = Point2((mark.centreX + mark.width / 2f).toDouble(), mark.baseY.toDouble()),
            top = Point2(mark.centreX.toDouble(), mark.topY.toDouble()),
        )

        fun of(sighting: WicketSighting): WicketAnchor = when (sighting) {
            is WicketSighting.Stumps -> of(sighting.set)
            is WicketSighting.Stone -> of(sighting.mark)
        }

        fun kindOf(sighting: WicketSighting): WicketKind = when (sighting) {
            is WicketSighting.Stumps -> WicketKind.STUMPS
            is WicketSighting.Stone -> WicketKind.STONE
        }
    }
}

/**
 * A wicket the tracker is prepared to stand behind, and exactly how far behind it stands.
 *
 * EVERY CAVEAT TRAVELS WITH THE COORDINATES. A lock that is coasting, a lock whose camera
 * motion could not be resolved, and a lock seen clearly this frame are all [WicketLock]s
 * and are worth wildly different amounts. A caller that wants to measure something should
 * be reading [isMeasurable], not merely checking for null — and a screen drawing this
 * should be reading [state] and [ageFrames], because a lock drawn at full strength while
 * coasting is the exact failure the first field test of the pitch-check screen produced.
 */
data class WicketLock(
    val anchor: WicketAnchor,
    val kind: WicketKind,
    val source: WicketLockSource,
    val state: WicketTrackState,
    /** 0..1. Ranks locks against each other over time. Not a probability. */
    val confidence: Float,
    /** Frames since this lock was last actually SEEN. Zero means this frame. */
    val ageFrames: Int,
    /** Recent RMS of observed-minus-predicted, in frame widths. How still the lock is. */
    val jitter: Float,
    /** Consecutive frames the lock has survived, seen or coasted. */
    val heldFrames: Int,
    /** The upright frame's width over its height, as it was when this lock was produced. */
    val aspect: Float,
    /**
     * Whether the lock's bar positions are sub-pixel — from the full-resolution comb fit,
     * or placed by hand — rather than whole contour boxes on a scaled frame.
     */
    val subPixel: Boolean = false,
    /** How the latest sighting that agreed with this lock was found. */
    val method: StumpMethod? = null,
) {
    val base: Point2 get() = anchor.base

    /** Outer stump centre to centre in the picture, frame widths. */
    val span: Float get() = anchor.span(aspect)

    /**
     * Whether anything may be measured from this lock.
     *
     * Four conditions, all of them necessary. CONFIRMED, because a tentative lock has not
     * yet shown it can be seen twice in the same place. Still, RELATIVE TO ITS OWN SIZE,
     * because a lock wandering by a third of its span is not a fixed landmark — and an
     * absolute limit would be meaningless at one distance or the other. STUMPS, because that
     * is the only kind that carries a real-world distance. And big enough: how big depends
     * on how precisely the bars were found — see [WicketTracker.MIN_MEASURABLE_SPAN].
     */
    val isMeasurable: Boolean
        get() = kind == WicketKind.STUMPS &&
            (state == WicketTrackState.CONFIRMED || state == WicketTrackState.REACQUIRE) &&
            jitter <= WicketTracker.measurableJitter(span) &&
            span >= minimumSpan

    private val minimumSpan: Float
        get() = if (subPixel) WicketTracker.MIN_MEASURABLE_SPAN_SUBPIXEL else WicketTracker.MIN_MEASURABLE_SPAN

    /**
     * Metres per frame width ALONG THE STUMP LINE, or null.
     *
     * The one honest scale a wicket gives you on its own, and the reason [WicketKind]
     * exists: the outer stumps' centres are 0.1936 m apart by the Laws, so their span in
     * the picture fixes a distance along the line they stand on. It fixes nothing in any
     * other direction — see [metresPerUnitDown], which needs a separate measurement.
     */
    fun metresPerUnitAcross(): Double? {
        if (!isMeasurable) return null
        val s = span
        if (s < minimumSpan) return null
        return PitchGeometry.STUMP_CENTRES_SPAN_M / s
    }

    /**
     * Metres per frame width UP THE PICTURE at the wicket, or null when no top is known.
     *
     * From [PitchGeometry.STUMP_HEIGHT_M]: a stump is 0.711 m of vertical world standing at
     * a known place, so its height in the picture is a scale in a direction that is not the
     * stump line. With the across-scale that is two independent directions at one depth,
     * which is what makes a speed near the wicket possible at all.
     *
     * IT IS A LOCAL SCALE AND ONLY A LOCAL ONE. It is right at the wicket's distance from
     * the camera and wrong everywhere else, growing more wrong the further up or down the
     * pitch you go. Nothing here may be used as a substitute for a [PitchQuad].
     */
    fun metresPerUnitDown(): Double? {
        if (!isMeasurable) return null
        val rise = anchor.rise(aspect) ?: return null
        if (rise < minimumSpan) return null
        return PitchGeometry.STUMP_HEIGHT_M / rise
    }
}

/** Where the detector should look next, and how sure the tracker is it will be there. */
data class WicketFocus(
    val anchor: WicketAnchor,
    val kind: WicketKind,
    /**
     * How far the span may differ from the anchor's, as a fraction. Tight for a confirmed
     * lock, which is measured; loose for a memory or a candidate, which is a hint.
     */
    val spanTolerance: Double,
    /** How far from the anchor to search, in half-spans either side. */
    val searchHalfSpans: Double,
    val aspect: Float,
)

/** Everything the tracker knows about how it is doing, for a screen to print. */
data class WicketDiagnostics(
    val state: WicketTrackState,
    val framesSeen: Int,
    val framesWithSighting: Int,
    /** Times a tentative lock was promoted. More than one or two means it keeps losing it. */
    val confirmations: Int,
    /** Times a coasting lock was found again. The headline number for a shaky mount. */
    val reacquires: Int,
    /** Times a confirmed lock was given up entirely. */
    val drops: Int,
    /** Times a sighting was refused because it was nowhere near the lock. */
    val gateRejections: Int,
    /** Times the picture turned under the lock. */
    val rotations: Int,
    /** Frames where camera motion could not be resolved at all. */
    val motionUnresolved: Int,
    /**
     * CONSECUTIVE frames with no camera-motion estimate, as opposed to the running total
     * beside it.
     *
     * The number that matters for a hand-placed lock: it is the only thing that can make
     * one stale, since the detector was never part of that claim. A screen showing [ageFrames]
     * for a hand-placed lock is showing how long the detector has disagreed, which is not a
     * measure of anything the operator cares about.
     */
    val blindFrames: Int,
    val ageFrames: Int,
    val heldFrames: Int,
    val confidence: Float,
    val jitter: Float,
    val motionConfidence: Float,
    val motionMagnitude: Float,
    /** Measured, over a rolling window of real timestamps. Never a target or a setting. */
    val framesPerSecond: Float,
    /**
     * How long the last automatic lock took, from the moment the tracker started looking
     * to CONFIRMED, in milliseconds of the camera's clock. Null until one has happened.
     * The number to read on a real ground when the lock "feels slow".
     */
    val lastLockMs: Long? = null,
    val lockSource: WicketLockSource?,
    val kind: WicketKind?,
    /** Why the last frame went the way it did, in words, for the line under the readout. */
    val note: String?,
    /** Candidate places being weighed, while searching; challengers, while locked. */
    val hypotheses: Int = 0,
    /** How long ago a lost lock's place is still remembered from, or null for none. */
    val memoryAgeMs: Long? = null,
    /** Median gap between detector runs, ms. Grace periods scale with it. */
    val cadenceMs: Float = 0f,
    /** The camera's roll as read off the locked stumps, degrees, or null before any. */
    val rollDeg: Float? = null,
    /** Whether the lock rests on sub-pixel comb fits. */
    val subPixel: Boolean = false,
    /** Re-acquired straight from memory, without a fresh search. */
    val memoryReacquires: Int = 0,
    /**
     * From the first frame this tracker saw to the first frame with any sighting, and to
     * the first frame it was READY (confirmed, or placed by hand). Camera clock, measured;
     * null until it happens. Startup speed, as it actually was on this phone.
     */
    val timeToFirstSightingMs: Long? = null,
    val timeToReadyMs: Long? = null,
    /** How the sighting that got the lock CONFIRMED was found. */
    val foundBy: StumpMethod? = null,
    /** How the latest agreeing sighting was found. */
    val method: StumpMethod? = null,
)

/**
 * A wicket, held across frames.
 *
 * WHAT WAS WRONG BEFORE THIS FILE. [OpenCvStumpDetector] answers one question about one
 * frame, honestly and with no memory. Everything else was left to whoever called it, and
 * the only caller — the pitch-check screen — did the least it could: draw the newest
 * sighting, hold the last one for twelve frames, throw it away.
 *
 * WHAT WAS STILL WRONG AFTER THE FIRST VERSION OF IT, found by tracing a real delivery
 * through it rather than by changing a number:
 *
 *   ONE CANDIDATE AT A TIME. The first sighting was seeded as THE candidate; a better one
 *   elsewhere on the next frame was "dissent" and needed five frames in a row to be heard.
 *   A pad-and-bat triple that happened to come first held the search hostage while the
 *   real wicket, seen on every frame, waited. Now every place is weighed at once
 *   ([Hypothesis]), and the one that keeps turning up wins.
 *
 *   A COAST MEASURED IN BLINKS. Eight frames, 400 ms, then the lock was gone — and the far
 *   wicket filmed from behind the bowler spends whole seconds behind the striker taking
 *   guard, the keeper, the bowler in the delivery stride. Every one of those dropped the
 *   lock and forced a fresh search. A stationary object on a camera whose motion is KNOWN
 *   can be carried for seconds; what limits the coast is the camera going blind or the
 *   anchor leaving the picture, so those are now the limits.
 *
 *   NO MEMORY OF WHERE IT WAS. A dropped lock was forgotten completely, so finding the same
 *   wicket in the same place a moment later took the full tentative-then-confirm path again.
 *   Its place is now remembered, carried on camera motion, and a sighting there goes
 *   straight back to REACQUIRE.
 *
 *   GRACE IN FRAMES, CALLED AT A RATE THAT VARIES EIGHTFOLD. In Auto the detector runs on
 *   one frame in eight, a quarter of a second apart, and a 250 ms grace expired on the
 *   first miss. Grace now scales with the measured gap between detector runs.
 *
 * THE STATES, AND WHAT MOVES BETWEEN THEM:
 *
 *   LOST → TENTATIVE      any sighting; it becomes a hypothesis.
 *   LOST → REACQUIRE      a sighting where a lost lock is remembered, at the same size.
 *   TENTATIVE → CONFIRMED a hypothesis seen [CONFIRM_FRAMES] times, steady, and clearly
 *                         ahead of any rival.
 *   TENTATIVE → LOST      every hypothesis missed past its grace.
 *   CONFIRMED → TEMPORARILY_LOST   not seen this frame.
 *   TEMPORARILY_LOST → REACQUIRE   seen again inside the widened gate.
 *   TEMPORARILY_LOST → LOST        unseen for [MAX_COAST_MS], or the camera blind for
 *                                  [MAX_COAST_FRAMES], or carried out of the picture.
 *   REACQUIRE → CONFIRMED          seen [REACQUIRE_CONFIRM_FRAMES] times.
 *   any locked → TENTATIVE         a challenger elsewhere persists while the lock is unseen.
 *
 * PURE KOTLIN. No OpenCV, no Android, no clock of its own: timestamps arrive as arguments.
 * Every transition in here is reachable from a unit test, which is the only way a state
 * machine ever gets to be trusted.
 */
class WicketTracker {

    /**
     * One place that might be the wicket, with the evidence for it.
     *
     * Kept per place rather than per frame so that evidence ACCUMULATES: a wicket seen on
     * eight frames out of ten beats a fence seen on the two it outscored the wicket on.
     */
    private class Hypothesis(
        var anchor: WicketAnchor,
        val kind: WicketKind,
        var scoreEma: Float,
        val bornMs: Long,
        var lastSeenMs: Long,
        var subPixel: Boolean,
    ) {
        var hits = 1
        var misses = 0
        val residuals = ArrayDeque<Float>()
        var method: StumpMethod? = null

        /** Persistence times quality, with misses taken off. Ranks; not a probability. */
        val evidence: Float get() = (hits - 0.5f * misses).coerceAtLeast(0f) * scoreEma
    }

    /** Where a confirmed lock was when it was given up. */
    private class Memory(var anchor: WicketAnchor, val kind: WicketKind, val lostMs: Long)

    /** The locked wicket. Null unless CONFIRMED, TEMPORARILY_LOST or REACQUIRE (or by hand). */
    private var anchor: WicketAnchor? = null
    private var kind: WicketKind? = null
    private var source: WicketLockSource = WicketLockSource.DETECTED
    private var state: WicketTrackState = WicketTrackState.LOST
    private var subPixel = false

    /** Candidates while searching; challengers while locked. */
    private val hypotheses = ArrayList<Hypothesis>()
    private var memory: Memory? = null

    private var streak = 0
    /** When the current search began — the first frame with nothing locked. */
    private var searchStartMs: Long? = null
    private var lastLockMs: Long? = null
    private var coastFrames = 0
    private var heldFrames = 0
    private var ageFrames = 0
    private var lastSeenMs: Long? = null
    private var lastFrameMs: Long? = null
    private var aspect: Float = 1f

    /** Consecutive frames whose camera motion could not be resolved. */
    private var blindFrames = 0

    /** Residuals of observed-minus-predicted, newest last, for the jitter figure. */
    private val residuals = ArrayDeque<Float>()

    /** The detector's own score, smoothed, so one weak frame does not collapse confidence. */
    private var scoreEma = 0f

    /** Roll as read off the stumps, smoothed. Null until a comb fit has supplied one. */
    private var rollEma: Float? = null

    /** Frame timestamps, for a measured frame rate rather than an assumed one. */
    private val frameTimes = ArrayDeque<Long>()

    /** Gaps between detector runs, for grace periods that scale with how often it runs. */
    private val gaps = ArrayDeque<Long>()

    private var firstFrameMs: Long? = null
    private var firstSightingMs: Long? = null
    private var firstReadyMs: Long? = null
    private var method: StumpMethod? = null
    private var foundBy: StumpMethod? = null

    private var framesSeen = 0
    private var framesWithSighting = 0
    private var confirmations = 0
    private var reacquires = 0
    private var memoryReacquires = 0
    private var drops = 0
    private var gateRejections = 0
    private var rotations = 0
    private var motionUnresolved = 0
    private var lastMotion: FrameMotion = FrameMotion.STILL
    private var note: String? = "nothing tracked yet"

    /** One sighting or none. See the list overload, which is what the camera screen calls. */
    @Synchronized
    fun onFrame(
        sighting: WicketSighting?,
        motion: FrameMotion,
        frameAspect: Float,
        timestampMs: Long,
    ): WicketLock? = onFrame(listOfNotNull(sighting), motion, frameAspect, timestampMs)

    /**
     * One frame's worth of evidence, and the lock that comes out of it.
     *
     * @param sightings every wicket the detector found in THIS frame, best first; empty when
     *   it looked and found nothing. Several, not one: see [StumpSearch.ranked].
     * @param motion how the camera moved since the previous frame. [FrameMotion.STILL]
     *   means UNKNOWN, not "held still" — see [FrameMotion.isUsable].
     * @param frameAspect the upright frame's width over its height
     * @param timestampMs the camera's clock, never the UI's
     *
     * Returns the lock as it now stands, or null while nothing is locked.
     */
    @Synchronized
    fun onFrame(
        sightings: List<WicketSighting>,
        motion: FrameMotion,
        frameAspect: Float,
        timestampMs: Long,
    ): WicketLock? {
        framesSeen++
        aspect = if (frameAspect > 0f) frameAspect else aspect
        lastMotion = motion
        recordFrameTime(timestampMs)
        val previousFrameMs = lastFrameMs
        lastFrameMs = timestampMs

        /*
         * THE CAMERA MOVES FIRST, ALWAYS.
         *
         * Everything held — the lock, every candidate, the memory — is where it was in the
         * PREVIOUS frame's picture. Before this frame's sightings can be compared to any of
         * it, it has to be moved into this frame's picture; otherwise every comparison is
         * measuring the tripod.
         */
        applyMotion(motion)

        if (firstFrameMs == null) firstFrameMs = timestampMs
        if (sightings.isNotEmpty()) {
            framesWithSighting++
            if (firstSightingMs == null) firstSightingMs = timestampMs
        }
        if (anchor == null && searchStartMs == null) searchStartMs = timestampMs
        expireMemory(timestampMs)

        val lock = when (source) {
            WicketLockSource.MANUAL -> stepManual(sightings, timestampMs)
            WicketLockSource.DETECTED -> stepDetected(sightings, timestampMs)
        }

        heldFrames = if (lock == null) 0 else heldFrames + 1
        if (firstReadyMs == null && lock != null && lock.state != WicketTrackState.TENTATIVE) {
            firstReadyMs = timestampMs
        }
        recordGap(previousFrameMs, timestampMs)
        return lock
    }

    private fun applyMotion(motion: FrameMotion) {
        if (motion.isUsable) {
            blindFrames = 0
            anchor = anchor?.map { motion.apply(it, aspect) }
            memory?.let { it.anchor = it.anchor.map { p -> motion.apply(p, aspect) } }
            for (h in hypotheses) h.anchor = h.anchor.map { motion.apply(it, aspect) }
        } else {
            motionUnresolved++
            blindFrames++
        }
    }

    /**
     * A hand-placed lock is carried, never replaced.
     *
     * The person tapped the actual wicket. The detector's opinion about where it is cannot
     * outrank that, so a sighting near the lock is allowed to NUDGE it — which is worth
     * having, because it corrects the slow drift that accumulates in any chain of
     * frame-to-frame transforms — and a sighting far from it is ignored outright rather
     * than counted as dissent.
     *
     * The one thing that can degrade a manual lock is losing track of the camera. With no
     * motion estimate for several frames the anchor is a memory of where the wicket was
     * before an unknown amount of movement, and saying so is the only honest option.
     */
    private fun stepManual(sightings: List<WicketSighting>, timestampMs: Long): WicketLock? {
        val held = anchor ?: return null

        val sameKind = sightings.filter { WicketAnchor.kindOf(it) == kind }
        val nearest = sameKind.minByOrNull { distance(WicketAnchor.of(it).base, held.base) }
        if (nearest != null) {
            val observed = WicketAnchor.of(nearest)
            val gate = gateFor(held, MANUAL_NUDGE_GATE_SPANS)
            val error = distance(observed.base, held.base)
            if (error <= gate && spanAgrees(held, observed, kind)) {
                anchor = held.blend(observed, MANUAL_NUDGE_WEIGHT)
                pushResidual(residuals, error)
                scoreEma = scoreEma + (nearest.score - scoreEma) * SCORE_SMOOTHING
                noteRoll(nearest)
                ageFrames = 0
                lastSeenMs = timestampMs
                note = "hand-placed, confirmed by the detector"
            } else {
                gateRejections++
                ageFrames++
                note = "hand-placed; a sighting %.3f away was ignored".format(error)
            }
        } else {
            ageFrames++
            note = if (blindFrames > 0) {
                "hand-placed; camera motion unresolved for $blindFrames frames"
            } else {
                "hand-placed; carried on camera motion"
            }
        }

        /*
         * Blind, not lost.
         *
         * A manual lock never falls to LOST on its own — the operator said where the wicket
         * is, and nothing here knows better. But once the camera's own movement has been
         * unmeasurable for several frames in a row, the anchor is no longer being carried;
         * it is just sitting there. TEMPORARILY_LOST is what a screen needs to see so it
         * can stop drawing this like a fact.
         */
        state = if (blindFrames > MAX_BLIND_FRAMES) {
            WicketTrackState.TEMPORARILY_LOST
        } else {
            WicketTrackState.CONFIRMED
        }
        return currentLock()
    }

    private fun stepDetected(sightings: List<WicketSighting>, timestampMs: Long): WicketLock? =
        if (anchor != null) stepLocked(sightings, timestampMs) else stepSearching(sightings, timestampMs)

    // ---- searching: LOST and TENTATIVE ----------------------------------------------------

    private fun stepSearching(sightings: List<WicketSighting>, timestampMs: Long): WicketLock? {
        /*
         * BACK WHERE IT WAS LOST. Checked before anything is weighed as new: the wicket
         * has not moved, the camera's motion has been carried, and a sighting of the same
         * size in the same place is the same wicket coming out from behind the batter. It
         * does not need to prove itself from nothing again.
         */
        val remembered = memory
        if (remembered != null) {
            val back = sightings.firstOrNull { matchesMemory(it, remembered) }
            if (back != null) {
                val observed = WicketAnchor.of(back)
                anchor = remembered.anchor.blend(observed, REACQUIRE_BLEND)
                kind = remembered.kind
                source = WicketLockSource.DETECTED
                state = WicketTrackState.REACQUIRE
                streak = 1
                coastFrames = 0
                ageFrames = 0
                lastSeenMs = timestampMs
                residuals.clear()
                pushResidual(residuals, distance(observed.base, remembered.anchor.base))
                scoreEma = back.score
                subPixel = isSubPixel(back)
                method = methodOf(back)
                noteRoll(back)
                hypotheses.clear()
                memory = null
                reacquires++
                memoryReacquires++
                note = "found again where it was lost %.1f s ago".format((timestampMs - remembered.lostMs) / 1000f)
                return currentLock()
            }
        }

        associate(sightings, timestampMs)
        decayUnseen(timestampMs)

        val lead = lead()
        if (lead == null) {
            state = WicketTrackState.LOST
            note = if (memory != null) "searching — remembering where it was" else "searching"
            return null
        }

        if (readyToConfirm(lead)) {
            promote(lead, timestampMs)
            return currentLock()
        }

        state = WicketTrackState.TENTATIVE
        streak = lead.hits
        note = buildString {
            append("tentative, ${lead.hits} of $CONFIRM_FRAMES")
            if (lead.misses > 0) append(" · missed ${lead.misses}")
            if (hypotheses.size > 1) append(" · ${hypotheses.size} places weighed")
        }
        return currentLock()
    }

    /**
     * Match this frame's sightings to the places already being weighed.
     *
     * Greedy, best sighting first, each to the nearest place of its kind inside the gate and
     * of a consistent size. A sighting that matches nothing is a new place.
     */
    private fun associate(sightings: List<WicketSighting>, timestampMs: Long) {
        val claimed = HashSet<Hypothesis>()
        for (s in sightings.sortedByDescending { it.score }) {
            val observed = WicketAnchor.of(s)
            val observedKind = WicketAnchor.kindOf(s)
            val match = hypotheses
                .filter { it !in claimed && it.kind == observedKind && spanAgrees(it.anchor, observed, observedKind) }
                .map { it to distance(observed.base, it.anchor.base) }
                .filter { (h, d) -> d <= gateFor(h.anchor, LOCK_GATE_SPANS) }
                .minByOrNull { it.second }
            if (match != null) {
                val h = match.first
                claimed.add(h)
                pushResidual(h.residuals, match.second)
                h.anchor = h.anchor.blend(observed, TENTATIVE_BLEND)
                h.hits++
                h.misses = 0
                h.lastSeenMs = timestampMs
                h.scoreEma += (s.score - h.scoreEma) * SCORE_SMOOTHING
                h.subPixel = isSubPixel(s)
                h.method = methodOf(s)
            } else {
                val fresh = Hypothesis(observed, observedKind, s.score, timestampMs, timestampMs, isSubPixel(s))
                fresh.method = methodOf(s)
                claimed.add(fresh)
                if (hypotheses.size < MAX_HYPOTHESES) {
                    hypotheses.add(fresh)
                } else {
                    // A full pool gives up its weakest place, never its strongest.
                    val weakest = hypotheses.filter { it !in claimed }.minByOrNull { it.evidence }
                    if (weakest != null && weakest.evidence < fresh.evidence + 1e-6f) {
                        hypotheses.remove(weakest)
                        hypotheses.add(fresh)
                    }
                }
            }
        }
        for (h in hypotheses) if (h !in claimed) h.misses++
    }

    /**
     * A SHORT GRACE, NOT NONE — AND MEASURED IN THE DETECTOR'S OWN TIME.
     *
     * A far wicket is found on some frames and not the next. With no grace at all, every gap
     * wiped the candidate and a wicket seen on alternate frames never got two in a row.
     * Two missed runs is room for that flicker and nothing more; the wall-clock cap stretches
     * with the gap between runs, because a quarter second is eight runs at full rate and one
     * in Auto.
     */
    private fun decayUnseen(timestampMs: Long) {
        val grace = tentativeGraceMs()
        hypotheses.removeAll { h ->
            h.misses > 0 && (h.misses > TENTATIVE_MISS_ALLOWANCE || timestampMs - h.lastSeenMs > grace)
        }
    }

    private fun lead(): Hypothesis? = hypotheses.maxByOrNull { it.evidence }

    /**
     * Seen enough, steady enough, and ahead of every rival.
     *
     * The rival test is what multi-candidate buys. Two places both seen on every frame —
     * the wicket, and a fence triple behind it — are not settled by whichever was seen
     * first. The better-evidenced one is promoted only once it is clearly ahead, or once
     * enough frames have passed that waiting longer will not change the order.
     */
    private fun readyToConfirm(lead: Hypothesis): Boolean {
        /*
         * A STONE MUST PERSIST. Three stumps are confirmed by the comb's geometry on every
         * sighting — spacing, proportions, open ground beside them — so two agreeing frames
         * is a lot of evidence. A "stone" is a compact pale lump, and a batter standing at
         * the crease or a kit bag is one too; a fraction of a second more is the cheapest
         * defence there is.
         */
        val needed = if (lead.kind == WicketKind.STONE) STONE_CONFIRM_FRAMES else CONFIRM_FRAMES
        if (lead.hits < needed) return false
        if (jitterOf(lead.residuals) > confirmJitter(lead.anchor.span(aspect))) return false
        val rival = hypotheses.filter { it !== lead && it.hits >= CONFIRM_FRAMES }.maxByOrNull { it.evidence }
            ?: return true
        return lead.evidence >= rival.evidence * RIVAL_MARGIN || lead.hits >= CONFIRM_FRAMES + RIVAL_PATIENCE
    }

    private fun promote(lead: Hypothesis, timestampMs: Long) {
        anchor = lead.anchor
        kind = lead.kind
        source = WicketLockSource.DETECTED
        state = WicketTrackState.CONFIRMED
        subPixel = lead.subPixel
        method = lead.method
        foundBy = lead.method
        streak = lead.hits
        coastFrames = 0
        ageFrames = 0
        lastSeenMs = lead.lastSeenMs
        residuals.clear()
        residuals.addAll(lead.residuals)
        scoreEma = lead.scoreEma
        confirmations++
        searchStartMs?.let { lastLockMs = timestampMs - it }
        searchStartMs = null
        note = "confirmed after ${lead.hits} sightings in the same place" +
            if (hypotheses.size > 1) " (of ${hypotheses.size} weighed)" else ""
        hypotheses.clear()
        memory = null
    }

    // ---- locked: CONFIRMED, TEMPORARILY_LOST, REACQUIRE ------------------------------------

    private fun stepLocked(sightings: List<WicketSighting>, timestampMs: Long): WicketLock? {
        val held = anchor ?: return stepSearching(sightings, timestampMs)
        val heldKind = kind

        val gate = gateFor(
            held,
            when (state) {
                // Wider while coasting, and only while coasting. The anchor has been
                // carried on prediction, so its own uncertainty has grown.
                WicketTrackState.TEMPORARILY_LOST -> REACQUIRE_GATE_SPANS
                else -> LOCK_GATE_SPANS
            },
        )

        var match: WicketSighting? = null
        var matchError = Float.MAX_VALUE
        for (s in sightings) {
            if (WicketAnchor.kindOf(s) != heldKind) continue
            val observed = WicketAnchor.of(s)
            if (!spanAgrees(held, observed, heldKind)) continue
            val error = distance(observed.base, held.base)
            if (error <= gate && error < matchError) { match = s; matchError = error }
        }

        // Everything else is a challenger. Counted as refused when it is the lock's kind,
        // which is the number that says the detector keeps seeing a wicket somewhere else.
        val others = sightings.filter { it !== match }
        gateRejections += others.count { WicketAnchor.kindOf(it) == heldKind }
        associate(others, timestampMs)
        decayChallengers(timestampMs)

        val lock = if (match != null) seen(held, match, matchError, timestampMs) else missed(timestampMs)

        // A challenger that has persisted while the lock has not: the lock is the mistake.
        val switched = challengerTakesOver(timestampMs)
        if (switched != null) return switched

        if (match == null && anchor != null && others.isNotEmpty() && note?.startsWith("not seen") == true) {
            note = "sighting elsewhere, ignored (${hypotheses.maxOfOrNull { it.hits } ?: 0} of $DISSENT_FRAMES)"
        }
        return lock
    }

    /** Inside the gate: this is the same wicket. */
    private fun seen(held: WicketAnchor, sighting: WicketSighting, error: Float, timestampMs: Long): WicketLock? {
        val observed = WicketAnchor.of(sighting)
        pushResidual(residuals, error)
        scoreEma += (sighting.score - scoreEma) * SCORE_SMOOTHING
        subPixel = isSubPixel(sighting)
        method = methodOf(sighting)
        noteRoll(sighting)
        ageFrames = 0
        lastSeenMs = timestampMs

        when (state) {
            WicketTrackState.TEMPORARILY_LOST -> {
                state = WicketTrackState.REACQUIRE
                reacquires++
                streak = 1
                anchor = held.blend(observed, REACQUIRE_BLEND)
                note = "re-acquired after coasting $coastFrames frames"
                coastFrames = 0
            }

            WicketTrackState.REACQUIRE -> {
                streak++
                anchor = held.blend(observed, CONFIRMED_BLEND)
                if (streak >= REACQUIRE_CONFIRM_FRAMES) {
                    state = WicketTrackState.CONFIRMED
                    note = "confirmed again"
                } else {
                    note = "re-acquiring, $streak of $REACQUIRE_CONFIRM_FRAMES"
                }
            }

            else -> {
                streak++
                /*
                 * A LIGHT BLEND, DELIBERATELY.
                 *
                 * A confirmed lock on a stationary object should move almost entirely on
                 * camera motion and only trim itself against detections. Tracking the
                 * detector closely would import its per-frame noise into a landmark
                 * everything downstream is measured from, and a scale that breathes is
                 * worse than one that is slightly stale.
                 */
                anchor = held.blend(observed, CONFIRMED_BLEND)
                state = WicketTrackState.CONFIRMED
                note = null
            }
        }
        return currentLock()
    }

    /**
     * Not seen this frame — or what was seen was not it.
     *
     * HOW LONG A LOCK MAY COAST is decided by what is known, not by a frame count. The
     * wicket does not move; the camera's motion is being measured and applied. So while
     * that measurement holds, the anchor is a good prediction for as long as the striker
     * cares to stand in front of it. What ends a coast:
     *
     *   the camera going blind — no motion estimate for [MAX_COAST_FRAMES] frames, after
     *   which the anchor is a guess about an unknown move;
     *
     *   the anchor being carried out of the picture — the phone was pointed elsewhere;
     *
     *   [MAX_COAST_MS] with no sighting at all, as a backstop.
     *
     * A lock given up for the third reason, with the camera still tracked, leaves a MEMORY:
     * the place, carried on, so the same wicket found there again is re-acquired at once.
     */
    private fun missed(timestampMs: Long): WicketLock? {
        ageFrames++
        val elapsed = lastSeenMs?.let { timestampMs - it } ?: 0L

        when (state) {
            WicketTrackState.CONFIRMED, WicketTrackState.REACQUIRE -> {
                state = WicketTrackState.TEMPORARILY_LOST
                coastFrames = 1
                streak = 0
                note = "not seen — coasting"
            }

            else -> {
                coastFrames++
                note = "not seen — coasting %.1f s".format(elapsed / 1000f)
            }
        }

        val held = anchor
        val outOfFrame = held != null && !inFrame(held)
        val blind = blindFrames > MAX_COAST_FRAMES
        if (elapsed > MAX_COAST_MS || blind || outOfFrame) {
            drops++
            val heldKind = kind
            if (!blind && !outOfFrame && held != null && heldKind != null) {
                memory = Memory(held, heldKind, timestampMs)
            }
            clearLock()
            note = when {
                outOfFrame -> "carried out of the picture — lock given up"
                blind -> "camera motion lost for $blindFrames frames — lock given up"
                else -> "not seen for %.1f s — remembering where it was".format(elapsed / 1000f)
            }
        }
        return currentLock()
    }

    private fun decayChallengers(timestampMs: Long) = decayUnseen(timestampMs)

    /**
     * THE ESCAPE HATCH. If the detector insists, frame after frame, that the wicket is
     * somewhere else, refusing forever is worse than starting again — the phone was
     * repointed, or the first lock was a fence.
     *
     * Two ways to insist. While the lock itself is NOT being seen, [DISSENT_FRAMES] sightings
     * of one other place. While it IS being seen too — two wickets in frame, or a lock on the
     * wrong one of two — nothing less than a long, clearly better record, because both are
     * real objects and only one of them can be the one that was confirmed.
     */
    private fun challengerTakesOver(timestampMs: Long): WicketLock? {
        val lastSeen = lastSeenMs
        val best = hypotheses.maxByOrNull { it.evidence } ?: return null
        val lockUnseenSinceItAppeared = anchor == null || lastSeen == null || lastSeen < best.bornMs
        /*
         * Comb-verified stumps outrank a stone at once. A stone lock is the weaker claim —
         * no scale, and the kind of lump a batter's pads can pass for — so verified stumps
         * seen twice anywhere replace it rather than waiting out the dissent count.
         */
        if (kind == WicketKind.STONE && best.kind == WicketKind.STUMPS && best.subPixel && best.hits >= CONFIRM_FRAMES) {
            drops++
            val rivals = hypotheses.filter { it !== best }
            clearLock()
            hypotheses.clear()
            hypotheses.add(best)
            hypotheses.addAll(rivals)
            promote(best, timestampMs)
            note = "stumps found — replacing the stone"
            return currentLock()
        }
        val outright = lockUnseenSinceItAppeared && best.hits >= DISSENT_FRAMES
        val onMerit = best.hits >= DISSENT_FRAMES * 3 && best.scoreEma > scoreEma + CHALLENGER_SCORE_MARGIN
        if (!outright && !onMerit) return null

        drops++
        val rivals = hypotheses.filter { it !== best }
        clearLock()
        hypotheses.clear()
        hypotheses.add(best)
        hypotheses.addAll(rivals)
        state = WicketTrackState.TENTATIVE
        streak = best.hits
        searchStartMs = timestampMs
        note = "the wicket is somewhere else — re-seeding after ${best.hits} frames"
        return currentLock()
    }

    private fun clearLock() {
        anchor = null
        kind = null
        source = WicketLockSource.DETECTED
        state = WicketTrackState.LOST
        subPixel = false
        method = null
        streak = 0
        coastFrames = 0
        ageFrames = 0
        heldFrames = 0
        lastSeenMs = null
        residuals.clear()
        scoreEma = 0f
    }

    /**
     * Carry the lock on camera motion alone, because the detector was not RUN this frame.
     *
     * A DIFFERENT THING FROM A MISS, and the distinction is the reason this method exists
     * rather than callers passing a null sighting. A miss is evidence: the detector looked
     * and did not find the wicket, which should age the lock towards LOST. This is the
     * absence of evidence: the camera screen deliberately does not run the detector while a
     * ball is in the air, and runs it only on some frames once a lock is steady.
     *
     * Passing null through [onFrame] then would drop a perfectly good lock eight frames in,
     * every single ball, and the readout would blame a detector that was never asked. So
     * everything held moves with the camera, the state does not change, and the note says
     * plainly that nothing looked.
     */
    @Synchronized
    fun carry(motion: FrameMotion, frameAspect: Float): WicketLock? {
        aspect = if (frameAspect > 0f) frameAspect else aspect
        lastMotion = motion
        if (anchor == null && hypotheses.isEmpty() && memory == null) return null

        applyMotion(motion)
        if (anchor == null) return currentLock()

        /*
         * The one thing that CAN still degrade a carried lock.
         *
         * With no camera-motion estimate for several frames the anchor is not being carried
         * at all, it is a memory of where the wicket was before an unknown amount of
         * movement. That is worth saying however good the reason for not running the
         * detector was.
         */
        if (blindFrames > MAX_BLIND_FRAMES && state == WicketTrackState.CONFIRMED) {
            state = WicketTrackState.TEMPORARILY_LOST
            note = "camera motion unresolved for $blindFrames frames — the lock is a memory"
        } else if (blindFrames <= MAX_BLIND_FRAMES) {
            note = "detector not run this frame — lock carried on camera motion"
        }
        return currentLock()
    }

    /**
     * The picture turned. Move the lock with it.
     *
     * @param quarterTurnsCw how many quarter turns clockwise the CONTENT rotated, which is
     *   the change in `ImageInfo.rotationDegrees` divided by ninety.
     *
     * A wicket is still in the same place in the world, the operator has not changed their
     * mind about where it is, and a hand-placed lock in particular must survive this. So the
     * anchor is mapped exactly and the state drops to REACQUIRE, which says the coordinates
     * are a prediction to be confirmed rather than a fresh measurement. Candidates and the
     * memory are mapped too: they are places in the same world.
     */
    @Synchronized
    fun onRotation(quarterTurnsCw: Int, newAspect: Float) {
        val turns = ((quarterTurnsCw % 4) + 4) % 4
        if (turns == 0) return
        rotations++
        if (newAspect > 0f) aspect = newAspect

        for (h in hypotheses) { h.anchor = h.anchor.rotated(turns); h.residuals.clear() }
        memory?.let { it.anchor = it.anchor.rotated(turns) }
        // The roll was measured against the old frame's axes and describes nothing now.
        rollEma = null

        val held = anchor
        if (held == null) {
            note = "picture turned"
            return
        }

        anchor = held.rotated(turns)
        // The residual history was measured in the old frame's geometry and describes
        // nothing in this one. Keeping it would let a jitter figure from before the turn
        // decide whether the lock is measurable after it.
        residuals.clear()
        coastFrames = 0
        ageFrames = 0
        streak = 0
        state = if (source == WicketLockSource.MANUAL) {
            // A hand-placed lock stays a fact through a turn. It was never the detector's
            // to confirm, so there is nothing to re-confirm.
            WicketTrackState.CONFIRMED
        } else {
            WicketTrackState.REACQUIRE
        }
        note = "picture turned — lock carried through"
    }

    /**
     * Put the wicket where somebody says it is.
     *
     * TWO POINTS, NOT ONE, and this is the difference between a marker and a calibration.
     * One tap fixes a place and hands back no distance — the same nothing a stone gives.
     * Two taps, on the outside of the leg stump and the outside of the off stump, fix a
     * line of known length, which is the entire reason anything downstream can report a
     * speed in km/h rather than in frame widths per second.
     *
     * The taps are on the OUTSIDE edges, because those are what a person can see and hit;
     * the anchor stores stump CENTRES, because that is what every detector measures. The
     * conversion is exact along the line: the centres are the outsides pulled in towards
     * the middle by 0.1936 / 0.2286.
     *
     * @param top optional third tap, on the top of a stump. It buys a scale in the vertical
     *   direction, which the base pair cannot give at any price.
     * @param kind [WicketKind.STUMPS] when the taps were on stumps; [WicketKind.STONE] when
     *   the operator pointed at a stone, which fixes a place and nothing else.
     */
    @Synchronized
    fun lockManually(
        baseLeft: Point2,
        baseRight: Point2,
        top: Point2? = null,
        kind: WicketKind = WicketKind.STUMPS,
        frameAspect: Float = aspect,
    ) {
        if (frameAspect > 0f) aspect = frameAspect
        // Left and right as the operator tapped them, in whichever order. Which is which
        // does not matter to a span, and asking somebody to tap in a prescribed order at
        // arm's length in the sun is how a calibration gets done wrong.
        val ordered = if (baseLeft.x <= baseRight.x) baseLeft to baseRight else baseRight to baseLeft
        val (l, r) = if (kind == WicketKind.STUMPS) {
            val k = PitchGeometry.STUMP_CENTRES_SPAN_M / PitchGeometry.STUMP_SET_WIDTH_M
            val mx = (ordered.first.x + ordered.second.x) / 2.0
            val my = (ordered.first.y + ordered.second.y) / 2.0
            Point2(mx + (ordered.first.x - mx) * k, my + (ordered.first.y - my) * k) to
                Point2(mx + (ordered.second.x - mx) * k, my + (ordered.second.y - my) * k)
        } else {
            ordered
        }
        anchor = WicketAnchor(l, r, top)
        this.kind = kind
        source = WicketLockSource.MANUAL
        state = WicketTrackState.CONFIRMED
        subPixel = true
        method = StumpMethod.MANUAL
        foundBy = StumpMethod.MANUAL
        if (firstReadyMs == null) firstReadyMs = lastFrameMs
        streak = CONFIRM_FRAMES
        coastFrames = 0
        ageFrames = 0
        heldFrames = 1
        blindFrames = 0
        hypotheses.clear()
        memory = null
        residuals.clear()
        scoreEma = 1f
        note = "hand-placed"
    }

    /** Give the wicket back to the detector. */
    @Synchronized
    fun clearManualLock() {
        if (source != WicketLockSource.MANUAL) return
        clearLock()
        hypotheses.clear()
        memory = null
        note = "hand-placed lock cleared"
    }

    /** A new delivery, a new scene, a new everything. Counters included. */
    @Synchronized
    fun reset() {
        clearLock()
        hypotheses.clear()
        memory = null
        rollEma = null
        searchStartMs = null
        lastLockMs = null
        lastFrameMs = null
        firstFrameMs = null
        firstSightingMs = null
        firstReadyMs = null
        method = null
        foundBy = null
        residuals.clear()
        frameTimes.clear()
        gaps.clear()
        blindFrames = 0
        framesSeen = 0
        framesWithSighting = 0
        confirmations = 0
        reacquires = 0
        memoryReacquires = 0
        drops = 0
        gateRejections = 0
        rotations = 0
        motionUnresolved = 0
        lastMotion = FrameMotion.STILL
        note = "nothing tracked yet"
    }

    /** The lock as it stands, without advancing anything. */
    @Synchronized
    fun lock(): WicketLock? = currentLock()

    /**
     * Where the detector should look on the next frame it runs, or null to search the lot.
     *
     * The lock, while there is one, or the remembered place of a lost one. The detector
     * looks there at FULL sensor resolution first — which is what keeps a far lock alive and
     * what makes holding one cheap.
     *
     * NEVER A MERE CANDIDATE. A focus hit skips the whole-frame search, so focusing on the
     * leading candidate would let whichever place led first — a pad-and-bat triple — be the
     * only place ever looked at again, and the weighing of rivals would be over before it
     * started. While searching, the whole frame is searched.
     */
    @Synchronized
    fun focus(): WicketFocus? {
        anchor?.let { a ->
            val k = kind ?: return null
            return when (state) {
                WicketTrackState.CONFIRMED -> WicketFocus(a, k, 0.22, 1.2, aspect)
                WicketTrackState.REACQUIRE -> WicketFocus(a, k, 0.3, 1.6, aspect)
                else -> WicketFocus(a, k, 0.35, REACQUIRE_GATE_SPANS * 2.0, aspect)
            }
        }
        memory?.let { return WicketFocus(it.anchor, it.kind, 0.4, REACQUIRE_GATE_SPANS * 2.0, aspect) }
        return null
    }

    @Synchronized
    fun diagnostics() = WicketDiagnostics(
        state = state,
        framesSeen = framesSeen,
        framesWithSighting = framesWithSighting,
        confirmations = confirmations,
        reacquires = reacquires,
        drops = drops,
        gateRejections = gateRejections,
        rotations = rotations,
        motionUnresolved = motionUnresolved,
        blindFrames = blindFrames,
        ageFrames = if (anchor == null) lead()?.misses ?: 0 else ageFrames,
        heldFrames = heldFrames,
        confidence = confidence(),
        jitter = currentJitter(),
        motionConfidence = lastMotion.confidence,
        motionMagnitude = lastMotion.magnitude,
        framesPerSecond = framesPerSecond(),
        lastLockMs = lastLockMs,
        lockSource = if (anchor == null) null else source,
        kind = kind ?: lead()?.kind,
        note = note,
        hypotheses = hypotheses.size,
        memoryAgeMs = memory?.let { m -> lastFrameMs?.let { it - m.lostMs } },
        cadenceMs = cadenceMs().toFloat(),
        rollDeg = rollEma,
        subPixel = if (anchor == null) lead()?.subPixel ?: false else subPixel,
        memoryReacquires = memoryReacquires,
        timeToFirstSightingMs = firstSightingMs?.let { s -> firstFrameMs?.let { s - it } },
        timeToReadyMs = firstReadyMs?.let { r -> firstFrameMs?.let { r - it } },
        foundBy = foundBy,
        method = if (anchor == null) lead()?.method else method,
    )

    private fun currentLock(): WicketLock? {
        val held = anchor
        val heldKind = kind
        if (held != null && heldKind != null) {
            return WicketLock(
                anchor = held,
                kind = heldKind,
                source = source,
                state = state,
                confidence = confidence(),
                ageFrames = ageFrames,
                jitter = jitterOf(residuals),
                heldFrames = heldFrames,
                aspect = aspect,
                subPixel = subPixel,
                method = method,
            )
        }
        val lead = lead() ?: return null
        return WicketLock(
            anchor = lead.anchor,
            kind = lead.kind,
            source = WicketLockSource.DETECTED,
            state = WicketTrackState.TENTATIVE,
            confidence = confidence(),
            ageFrames = lead.misses,
            jitter = jitterOf(lead.residuals),
            heldFrames = heldFrames,
            aspect = aspect,
            subPixel = lead.subPixel,
            method = lead.method,
        )
    }

    /**
     * How much the lock is worth right now, 0..1.
     *
     * Independent ways a lock goes bad, multiplied rather than averaged — because any one
     * of them being terrible should sink the number on its own, and an average lets three
     * good terms hide a fatal one.
     */
    private fun confidence(): Float {
        val lead = if (anchor == null) lead() else null
        if (anchor == null && lead == null) return 0f

        val stateTerm = when {
            lead != null -> 0.25f + 0.2f * (lead.hits.toFloat() / CONFIRM_FRAMES).coerceAtMost(1f)
            state == WicketTrackState.CONFIRMED -> 1f
            state == WicketTrackState.REACQUIRE -> 0.8f
            state == WicketTrackState.TEMPORARILY_LOST -> 0.55f
            else -> 0f
        }

        /*
         * WHAT "STALE" MEANS DEPENDS ENTIRELY ON WHERE THE LOCK CAME FROM.
         *
         * For a detected lock, staleness is time since the detector last agreed — measured
         * in TIME now, since the coast is. For a hand-placed one it measures nothing at
         * all: the operator pointed at the wicket, and what makes that stale is losing
         * track of the camera, so that is what its age term reads.
         */
        val ageTerm = when {
            source == WicketLockSource.MANUAL && anchor != null ->
                (1f - blindFrames.toFloat() / (MAX_BLIND_FRAMES + 1)).coerceIn(0.2f, 1f)
            lead != null -> (1f - lead.misses.toFloat() / (TENTATIVE_MISS_ALLOWANCE + 1)).coerceIn(0.3f, 1f)
            else -> {
                val elapsed = (lastFrameMs ?: 0L) - (lastSeenMs ?: lastFrameMs ?: 0L)
                val byTime = 1f - elapsed.toFloat() / MAX_COAST_MS
                val byFrames = 1f - ageFrames.toFloat() / (MAX_COAST_FRAMES * 8 + 1)
                minOf(byTime, byFrames).coerceIn(0.15f, 1f)
            }
        }

        val span = (anchor ?: lead?.anchor)?.span(aspect) ?: 0f
        val jitterTerm = (1f - currentJitter() / measurableJitter(span)).coerceIn(0.2f, 1f)

        // Never zero: a still scene with no trackable corners is not the same as a scene
        // where the camera is known to have lurched.
        val motionTerm = if (lastMotion.isUsable) 1f else (1f - blindFrames * 0.15f).coerceIn(0.4f, 1f)

        val scoreTerm = when {
            source == WicketLockSource.MANUAL && anchor != null -> 1f
            lead != null -> lead.scoreEma.coerceIn(0.35f, 1f)
            else -> scoreEma.coerceIn(0.35f, 1f)
        }

        return (stateTerm * ageTerm * jitterTerm * motionTerm * scoreTerm).coerceIn(0f, 1f)
    }

    private fun currentJitter(): Float =
        if (anchor != null) jitterOf(residuals) else lead()?.let { jitterOf(it.residuals) } ?: 0f

    /** RMS of recent residuals, in frame widths. Zero when there are none yet. */
    private fun jitterOf(values: ArrayDeque<Float>): Float {
        if (values.isEmpty()) return 0f
        var sum = 0.0
        for (r in values) sum += r.toDouble() * r
        return sqrt(sum / values.size).toFloat()
    }

    private fun pushResidual(into: ArrayDeque<Float>, value: Float) {
        into.addLast(value)
        while (into.size > JITTER_WINDOW) into.removeFirst()
    }

    private fun noteRoll(sighting: WicketSighting) {
        val roll = (sighting as? WicketSighting.Stumps)?.set?.rollDeg ?: return
        rollEma = rollEma?.let { it + (roll - it) * 0.15f } ?: roll
    }

    private fun methodOf(sighting: WicketSighting): StumpMethod? =
        (sighting as? WicketSighting.Stumps)?.set?.method

    private fun isSubPixel(sighting: WicketSighting) =
        (sighting as? WicketSighting.Stumps)?.set?.subPixel == true

    private fun matchesMemory(sighting: WicketSighting, remembered: Memory): Boolean {
        if (WicketAnchor.kindOf(sighting) != remembered.kind) return false
        val observed = WicketAnchor.of(sighting)
        if (!spanAgrees(remembered.anchor, observed, remembered.kind, MEMORY_SPAN_RATIO)) return false
        return distance(observed.base, remembered.anchor.base) <= gateFor(remembered.anchor, REACQUIRE_GATE_SPANS)
    }

    /**
     * Forget where a lost lock was once that place means nothing any more: too long ago,
     * the camera's motion unknown for long enough that it may have been moved, or carried
     * out of the picture.
     */
    private fun expireMemory(timestampMs: Long) {
        val m = memory ?: return
        if (timestampMs - m.lostMs > MEMORY_MS || blindFrames > MAX_BLIND_FRAMES || !inFrame(m.anchor)) {
            memory = null
        }
    }

    /**
     * Whether two readings of a wicket are the same SIZE.
     *
     * A wicket's span does not change while the camera stands still. The near wicket and
     * the far one, a pad-and-bat triple, a fence two metres behind — these can sit inside a
     * position gate and differ in size by half or double, and a stumps lock that accepted
     * them would be measuring speed with the wrong ruler. Stones carry no agreed size and
     * are not held to this.
     */
    private fun spanAgrees(a: WicketAnchor, b: WicketAnchor, k: WicketKind?, ratio: Float = SPAN_RATIO): Boolean {
        if (k != WicketKind.STUMPS) return true
        val sa = a.span(aspect)
        val sb = b.span(aspect)
        if (sa <= 1e-6f || sb <= 1e-6f) return true
        val r = if (sa > sb) sa / sb else sb / sa
        return r <= ratio
    }

    private fun inFrame(a: WicketAnchor): Boolean {
        val b = a.base
        return b.x in -IN_FRAME_MARGIN..(1.0 + IN_FRAME_MARGIN) && b.y in -IN_FRAME_MARGIN..(1.0 + IN_FRAME_MARGIN)
    }

    private fun recordFrameTime(timestampMs: Long) {
        // A clock that went backwards is a different session's timestamps, or the UI clock
        // being handed in by mistake. Either way the window is meaningless now.
        if (frameTimes.isNotEmpty() && timestampMs < frameTimes.last()) {
            frameTimes.clear()
            gaps.clear()
            lastFrameMs = null
        }
        frameTimes.addLast(timestampMs)
        while (frameTimes.size > FPS_WINDOW) frameTimes.removeFirst()
    }

    /** Recorded AFTER a frame is judged, so a gap is never the excuse for itself. */
    private fun recordGap(previousMs: Long?, timestampMs: Long) {
        val last = previousMs ?: return
        val gap = timestampMs - last
        if (gap <= 0L) return
        gaps.addLast(gap)
        while (gaps.size > CADENCE_WINDOW) gaps.removeFirst()
    }

    /** Median gap between detector runs, or a full-rate frame when none is known yet. */
    private fun cadenceMs(): Long {
        if (gaps.isEmpty()) return DEFAULT_CADENCE_MS
        val sorted = gaps.sorted()
        return sorted[sorted.size / 2]
    }

    private fun tentativeGraceMs(): Long =
        maxOf(TENTATIVE_GRACE_MS, (cadenceMs() * (TENTATIVE_MISS_ALLOWANCE + 0.5)).toLong())

    /** Measured, over the rolling window, or zero when there is not enough of one. */
    private fun framesPerSecond(): Float {
        if (frameTimes.size < 2) return 0f
        val elapsed = frameTimes.last() - frameTimes.first()
        if (elapsed <= 0L) return 0f
        return (frameTimes.size - 1) * 1000f / elapsed
    }

    /**
     * How far a sighting may be from the lock, in frame widths.
     *
     * SCALED BY THE WICKET'S OWN SIZE, which is the only way one number works at every
     * distance. A wicket filling a fifth of the frame from ten metres and one three percent
     * wide from forty are the same object; a fixed gate is either far too tight for the
     * first or hopelessly loose for the second.
     */
    private fun gateFor(held: WicketAnchor, spans: Float): Float {
        val span = held.span(aspect)
        return (span * spans).coerceIn(MIN_GATE, MAX_GATE)
    }

    /** Aspect-corrected distance between two normalised points, in frame widths. */
    private fun distance(a: Point2, b: Point2): Float {
        val asp = if (aspect > 0f) aspect.toDouble() else 1.0
        return hypot(b.x - a.x, (b.y - a.y) / asp).toFloat()
    }

    companion object {

        /**
         * Sightings of one place before it is a lock.
         *
         * Two, at the rate this runs, is under a tenth of a second — and is not the only
         * test: the place must also be steady ([confirmJitter]) and clearly ahead of every
         * other place being weighed ([RIVAL_MARGIN]).
         */
        const val CONFIRM_FRAMES = 2

        /** Sightings of one place before a STONE is a lock. See [readyToConfirm]. */
        const val STONE_CONFIRM_FRAMES = 12

        /** Missed detector runs a TENTATIVE candidate survives. */
        const val TENTATIVE_MISS_ALLOWANCE = 2

        /** And the wall-clock floor on that grace; it stretches with the detector's cadence. */
        const val TENTATIVE_GRACE_MS = 250L

        /** Frames to re-confirm after coasting. Fewer: it was already proved once. */
        const val REACQUIRE_CONFIRM_FRAMES = 2

        /**
         * Consecutive frames with NO camera-motion estimate a lock may coast through.
         *
         * Not a limit on being unseen — see [MAX_COAST_MS] — but on being BLIND: once the
         * camera's own movement has been unknown for this long the anchor is a guess, and
         * the phone may well be pointing at the car park.
         */
        const val MAX_COAST_FRAMES = 8

        /**
         * Longest a lock may go unseen with the camera tracked.
         *
         * Three seconds: a striker taking guard in front of the far stumps, the keeper
         * settling, the bowler's delivery stride. Long enough for every routine occlusion of
         * a wicket filmed from behind the arm; and the lock does not vanish after it — its
         * place is remembered for [MEMORY_MS].
         */
        const val MAX_COAST_MS = 3_000L

        /** How long a lost lock's place is remembered for instant re-acquisition. */
        const val MEMORY_MS = 30_000L

        /** Agreeing sightings away from the lock before the lock is the thing that is wrong. */
        const val DISSENT_FRAMES = 5

        /** How much better a challenger seen ALONGSIDE the lock must score to replace it. */
        const val CHALLENGER_SCORE_MARGIN = 0.15f

        /** Places weighed at once. More than this is a fence, and looking harder won't help. */
        const val MAX_HYPOTHESES = 6

        /** A leader is promoted once its evidence is this multiple of the runner-up's... */
        const val RIVAL_MARGIN = 1.25f

        /** ...or once it has this many sightings beyond [CONFIRM_FRAMES] regardless. */
        const val RIVAL_PATIENCE = 3

        /** Frames without a camera motion estimate before a manual lock stops being a fact. */
        const val MAX_BLIND_FRAMES = 6

        /** Residuals kept for the jitter figure. About half a second. */
        const val JITTER_WINDOW = 12

        /** Frame times kept for the measured rate. */
        const val FPS_WINDOW = 30

        /** Detector gaps kept for the cadence. */
        const val CADENCE_WINDOW = 7
        const val DEFAULT_CADENCE_MS = 33L

        /** Gates, as multiples of the locked wicket's own span in the picture. */
        const val LOCK_GATE_SPANS = 0.9f
        const val REACQUIRE_GATE_SPANS = 2.0f
        const val MANUAL_NUDGE_GATE_SPANS = 1.2f

        /** Largest ratio between two spans that are still the same wicket. */
        const val SPAN_RATIO = 1.45f

        /** Looser for a memory, which has been carried for seconds. */
        const val MEMORY_SPAN_RATIO = 1.6f

        /** Floor and ceiling, in frame widths, for wickets that are tiny or enormous. */
        const val MIN_GATE = 0.012f
        const val MAX_GATE = 0.20f

        /** How far outside the picture a carried anchor may drift before it has left it. */
        const val IN_FRAME_MARGIN = 0.02

        /** How hard each state pulls the anchor towards the newest sighting. */
        const val TENTATIVE_BLEND = 0.5f
        const val REACQUIRE_BLEND = 0.6f
        const val CONFIRMED_BLEND = 0.18f
        const val MANUAL_NUDGE_WEIGHT = 0.06f

        /** How fast the detector's score is followed. */
        const val SCORE_SMOOTHING = 0.25f

        /** Absolute ceiling on promotion jitter, frame widths. */
        const val MAX_CONFIRM_JITTER = 0.048f

        /** Absolute ceiling on measurable jitter, frame widths. */
        const val MAX_MEASURABLE_JITTER = 0.05f

        /**
         * Jitter allowed at promotion: a quarter of the wicket's own span.
         *
         * RELATIVE, because the old absolute 0.048 frame widths was looser than a whole far
         * wicket — a lock could hop between two different objects six metres apart and
         * still be called steady.
         */
        fun confirmJitter(span: Float): Float = (span * 0.25f).coerceIn(0.004f, MAX_CONFIRM_JITTER)

        /**
         * Jitter above which nothing may be MEASURED from the lock: a third of its span.
         *
         * Looser than promotion on purpose: this is applied continuously, and a lock
         * flickering in and out of measurable would make a speed readout appear and vanish
         * for no reason the operator can see.
         */
        fun measurableJitter(span: Float): Float = (span * 0.33f).coerceIn(0.005f, MAX_MEASURABLE_JITTER)

        /**
         * A span below this is too few pixels to divide 0.1936 m by — for CONTOUR boxes.
         *
         * At 1.5% of a 960-wide frame a wicket is fourteen pixels, and a contour box is good
         * to a pixel, so each metre out of it carries 7% error.
         */
        const val MIN_MEASURABLE_SPAN = 0.015f

        /**
         * The same floor for a SUB-PIXEL lock — a comb fit at full resolution, or taps.
         *
         * SET FROM MEASUREMENT, NOT TASTE. `WicketPipelineSimTest` runs physically scaled
         * frames (70° lens, 1280 wide, sensor noise, a jittering tripod) through the comb
         * and the tracker: the locked span is within about 1% of the truth out to 30 m —
         * 0.0046 of the frame — and 3.7% at 34 m. 0.45% of the width is therefore ~30 m on
         * that camera, which covers the far wicket from anywhere behind the bowler's arm.
         *
         * A simulation has no lens aberration and no video compression; a real phone will
         * do worse. Read the span error off a ground with a tape measure before trusting a
         * speed taken near this floor.
         */
        const val MIN_MEASURABLE_SPAN_SUBPIXEL = 0.0045f
    }
}
