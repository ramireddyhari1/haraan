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
    /** Three bars, or two tapped stump bases. The span is [PitchGeometry.STUMP_SET_WIDTH_M]. */
    STUMPS,

    /** A stone, a brick, a stick. Fixes a place on the ground and no distance whatsoever. */
    STONE,
}

/**
 * Where the wicket is, as two points on the ground and optionally one above them.
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
) {
    val base: Point2 get() = anchor.base

    /** Outside to outside in the picture, frame widths. */
    val span: Float get() = anchor.span(aspect)

    /**
     * Whether anything may be measured from this lock.
     *
     * Three conditions, all of them necessary. CONFIRMED, because a tentative lock has not
     * yet shown it can be seen twice in the same place. Still, because a lock jittering by
     * a tenth of its own width is not a fixed landmark. And STUMPS, because that is the
     * only kind that carries a real-world distance at all.
     */
    val isMeasurable: Boolean
        get() = kind == WicketKind.STUMPS &&
            (state == WicketTrackState.CONFIRMED || state == WicketTrackState.REACQUIRE) &&
            jitter <= WicketTracker.MAX_MEASURABLE_JITTER &&
            span >= WicketTracker.MIN_MEASURABLE_SPAN

    /**
     * Metres per frame width ALONG THE STUMP LINE, or null.
     *
     * The one honest scale a wicket gives you on its own, and the reason [WicketKind]
     * exists: three stumps are 0.2286 m outside to outside by the Laws, so their span in
     * the picture fixes a distance along the line they stand on. It fixes nothing in any
     * other direction — see [metresPerUnitDown], which needs a separate measurement.
     */
    fun metresPerUnitAcross(): Double? {
        if (!isMeasurable) return null
        val s = span
        if (s < WicketTracker.MIN_MEASURABLE_SPAN) return null
        return PitchGeometry.STUMP_SET_WIDTH_M / s
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
        if (rise < WicketTracker.MIN_MEASURABLE_SPAN) return null
        return PitchGeometry.STUMP_HEIGHT_M / rise
    }
}

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
    val lockSource: WicketLockSource?,
    val kind: WicketKind?,
    /** Why the last frame went the way it did, in words, for the line under the readout. */
    val note: String?,
)

/**
 * A wicket, held across frames.
 *
 * WHAT WAS WRONG BEFORE THIS FILE. [OpenCvStumpDetector] answers one question about one
 * frame, honestly and with no memory. Everything else was left to whoever called it, and
 * the only caller — the pitch-check screen — did the least it could: draw the newest
 * sighting, hold the last one for twelve frames, throw it away. That has three specific
 * failures, and they are the reason this exists.
 *
 *   A SINGLE LUCKY FRAME LOOKED IDENTICAL TO A REAL LOCK. Three bars that happen to line
 *   up for one frame — a bat, a pad and a boot; three fence palings past a gap in the
 *   sightscreen — were drawn exactly like a wicket seen for a hundred frames. Multi-frame
 *   confirmation is the fix: a candidate has to be found in the SAME PLACE repeatedly
 *   before it is called anything.
 *
 *   "THE SAME PLACE" WAS UNDEFINABLE. On a phone, between two frames, everything moves a
 *   little — so either the test was loose enough to accept a different object, or tight
 *   enough that a breath of wind broke the lock. [CameraMotion] removes the camera's own
 *   contribution first, and then a tight test means what it says.
 *
 *   THE HOLD WAS A TIMER, NOT A STATE. Twelve frames of grace, applied identically to a
 *   lock that vanished behind the bowler's run-up and a lock that vanished because the
 *   phone was pointed at the car park. The states below tell those apart, and the one that
 *   matters — a lock coasting on prediction — announces itself instead of being drawn like
 *   a detection.
 *
 * PURE KOTLIN. No OpenCV, no Android, no clock of its own: timestamps arrive as arguments.
 * Every transition in here is reachable from a unit test, which is the only way a state
 * machine ever gets to be trusted.
 */
class WicketTracker {

    private var anchor: WicketAnchor? = null
    private var kind: WicketKind? = null
    private var source: WicketLockSource = WicketLockSource.DETECTED
    private var state: WicketTrackState = WicketTrackState.LOST

    private var streak = 0
    private var coastFrames = 0
    private var heldFrames = 0
    private var ageFrames = 0
    private var lastSeenMs: Long? = null
    private var aspect: Float = 1f

    /** Consecutive frames whose camera motion could not be resolved. */
    private var blindFrames = 0

    /** Residuals of observed-minus-predicted, newest last, for the jitter figure. */
    private val residuals = ArrayDeque<Float>()

    /** The detector's own score, smoothed, so one weak frame does not collapse confidence. */
    private var scoreEma = 0f

    /** Frame timestamps, for a measured frame rate rather than an assumed one. */
    private val frameTimes = ArrayDeque<Long>()

    private var framesSeen = 0
    private var framesWithSighting = 0
    private var confirmations = 0
    private var reacquires = 0
    private var drops = 0
    private var gateRejections = 0
    private var rotations = 0
    private var motionUnresolved = 0
    private var lastMotion: FrameMotion = FrameMotion.STILL
    private var note: String? = "nothing tracked yet"

    /**
     * Sightings that agreed with each other but not with the lock.
     *
     * The escape hatch from a lock that is simply wrong. If the detector insists, frame
     * after frame, that the wicket is somewhere else — because the operator repointed the
     * phone, or because the first lock was a fence — refusing forever is worse than
     * starting again. This counts the insistence.
     */
    private var dissent = 0
    private var dissentAnchor: WicketAnchor? = null

    /**
     * One frame's worth of evidence, and the lock that comes out of it.
     *
     * @param sighting what the detector found in THIS frame, or null when it found nothing
     * @param motion how the camera moved since the previous frame. [FrameMotion.STILL]
     *   means UNKNOWN, not "held still" — see [FrameMotion.isUsable].
     * @param frameAspect the upright frame's width over its height
     * @param timestampMs the camera's clock, never the UI's
     *
     * Returns the lock as it now stands, or null while nothing is locked.
     */
    @Synchronized
    fun onFrame(
        sighting: WicketSighting?,
        motion: FrameMotion,
        frameAspect: Float,
        timestampMs: Long,
    ): WicketLock? {
        framesSeen++
        aspect = if (frameAspect > 0f) frameAspect else aspect
        lastMotion = motion
        recordFrameTime(timestampMs)

        /*
         * THE CAMERA MOVES FIRST, ALWAYS.
         *
         * The anchor is where the wicket was in the PREVIOUS frame's picture. Before this
         * frame's sighting can be compared to it, it has to be moved into this frame's
         * picture — otherwise every comparison is measuring the tripod, and a gate tight
         * enough to be useful would reject the wicket on any frame the phone breathed.
         */
        if (motion.isUsable) {
            blindFrames = 0
            anchor = anchor?.map { motion.apply(it, aspect) }
        } else {
            motionUnresolved++
            blindFrames++
        }

        if (sighting != null) framesWithSighting++

        val lock = when (source) {
            WicketLockSource.MANUAL -> stepManual(sighting, timestampMs)
            WicketLockSource.DETECTED -> stepDetected(sighting, timestampMs)
        }

        heldFrames = if (lock == null) 0 else heldFrames + 1
        return lock
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
    private fun stepManual(sighting: WicketSighting?, timestampMs: Long): WicketLock? {
        val held = anchor ?: return null

        if (sighting != null && WicketAnchor.kindOf(sighting) == kind) {
            val observed = WicketAnchor.of(sighting)
            val gate = gateFor(held, MANUAL_NUDGE_GATE_SPANS)
            val error = distance(observed.base, held.base)
            if (error <= gate) {
                anchor = held.blend(observed, MANUAL_NUDGE_WEIGHT)
                pushResidual(error)
                scoreEma = scoreEma + (sighting.score - scoreEma) * SCORE_SMOOTHING
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

    private fun stepDetected(sighting: WicketSighting?, timestampMs: Long): WicketLock? {
        if (sighting == null) return stepMiss(timestampMs)

        val observed = WicketAnchor.of(sighting)
        val observedKind = WicketAnchor.kindOf(sighting)
        val held = anchor

        // A stone where a set of stumps was locked is not the same object seen worse. It
        // is a different claim with a different guarantee, and blending the two would give
        // a lock that carries a scale on the strength of frames that never measured one.
        if (held == null || kind != observedKind) {
            return seed(observed, observedKind, sighting.score, timestampMs)
        }

        val error = distance(observed.base, held.base)
        val gate = gateFor(
            held,
            when (state) {
                // Wider while coasting, and only while coasting. The anchor has been
                // carried on prediction for several frames, so its own uncertainty has
                // grown — the gate grows with it rather than being permanently loose.
                WicketTrackState.TEMPORARILY_LOST -> REACQUIRE_GATE_SPANS
                else -> LOCK_GATE_SPANS
            },
        )

        if (error > gate) return stepDissent(observed, observedKind, sighting.score, timestampMs, error)

        // Inside the gate: this is the same wicket.
        dissent = 0
        dissentAnchor = null
        pushResidual(error)
        scoreEma = scoreEma + (sighting.score - scoreEma) * SCORE_SMOOTHING
        ageFrames = 0
        coastFrames = 0
        lastSeenMs = timestampMs

        when (state) {
            WicketTrackState.TENTATIVE -> {
                streak++
                anchor = held.blend(observed, TENTATIVE_BLEND)
                if (streak >= CONFIRM_FRAMES && jitter() <= MAX_CONFIRM_JITTER) {
                    state = WicketTrackState.CONFIRMED
                    confirmations++
                    note = "confirmed after $streak frames in the same place"
                } else {
                    note = "tentative, $streak of $CONFIRM_FRAMES"
                }
            }

            WicketTrackState.TEMPORARILY_LOST -> {
                state = WicketTrackState.REACQUIRE
                reacquires++
                streak = 1
                anchor = held.blend(observed, REACQUIRE_BLEND)
                note = "re-acquired after coasting $coastFrames frames"
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

            WicketTrackState.CONFIRMED -> {
                streak++
                /*
                 * A LIGHT BLEND, DELIBERATELY.
                 *
                 * A confirmed lock on a stationary object should move almost entirely on
                 * camera motion and only trim itself against detections. Tracking the
                 * detector closely would import its per-frame noise — a threshold that
                 * catches one more row of a stump on alternate frames — into a landmark
                 * everything downstream is measured from, and a scale that breathes is
                 * worse than one that is slightly stale.
                 */
                anchor = held.blend(observed, CONFIRMED_BLEND)
                note = null
            }

            WicketTrackState.LOST -> return seed(observed, observedKind, sighting.score, timestampMs)
        }

        return currentLock()
    }

    /**
     * A sighting that is nowhere near the lock.
     *
     * Refused, and remembered. One of these is the detector having a bad frame and the
     * lock is right to ignore it. Several in a row, all agreeing with EACH OTHER, means
     * the lock is the thing that is wrong — the phone was repointed, or the first lock was
     * a fence — and continuing to refuse would be a tracker defending a mistake.
     */
    private fun stepDissent(
        observed: WicketAnchor,
        observedKind: WicketKind,
        score: Float,
        timestampMs: Long,
        error: Float,
    ): WicketLock? {
        gateRejections++
        val previousDissent = dissentAnchor
        val agrees = previousDissent != null &&
            distance(observed.base, previousDissent.base) <= gateFor(observed, LOCK_GATE_SPANS)

        dissent = if (agrees) dissent + 1 else 1
        dissentAnchor = observed

        if (dissent >= DISSENT_FRAMES) {
            note = "the wicket is somewhere else — re-seeding after $dissent frames"
            drops++
            return seed(observed, observedKind, score, timestampMs)
        }

        note = "sighting %.3f from the lock, ignored ($dissent of $DISSENT_FRAMES)".format(error)
        return stepMiss(timestampMs)
    }

    /** Nothing was seen this frame — or what was seen was refused. */
    private fun stepMiss(timestampMs: Long): WicketLock? {
        if (anchor == null) {
            state = WicketTrackState.LOST
            note = "searching"
            return null
        }

        ageFrames++
        val elapsed = lastSeenMs?.let { timestampMs - it } ?: 0L

        when (state) {
            WicketTrackState.TENTATIVE -> {
                clearLock()
                note = "tentative candidate not seen again"
            }

            WicketTrackState.CONFIRMED, WicketTrackState.REACQUIRE -> {
                state = WicketTrackState.TEMPORARILY_LOST
                coastFrames = 1
                streak = 0
                note = "not seen — coasting"
            }

            WicketTrackState.TEMPORARILY_LOST -> {
                coastFrames++
                if (coastFrames > MAX_COAST_FRAMES || elapsed > MAX_COAST_MS) {
                    drops++
                    clearLock()
                    note = "lost after coasting $coastFrames frames"
                } else {
                    note = "coasting $coastFrames of $MAX_COAST_FRAMES"
                }
            }

            WicketTrackState.LOST -> Unit
        }

        return currentLock()
    }

    private fun seed(
        observed: WicketAnchor,
        observedKind: WicketKind,
        score: Float,
        timestampMs: Long,
    ): WicketLock? {
        anchor = observed
        kind = observedKind
        source = WicketLockSource.DETECTED
        state = WicketTrackState.TENTATIVE
        streak = 1
        coastFrames = 0
        ageFrames = 0
        lastSeenMs = timestampMs
        dissent = 0
        dissentAnchor = null
        residuals.clear()
        scoreEma = score
        note = "tentative, 1 of $CONFIRM_FRAMES"
        return currentLock()
    }

    private fun clearLock() {
        anchor = null
        kind = null
        source = WicketLockSource.DETECTED
        state = WicketTrackState.LOST
        streak = 0
        coastFrames = 0
        ageFrames = 0
        heldFrames = 0
        lastSeenMs = null
        residuals.clear()
        scoreEma = 0f
        dissent = 0
        dissentAnchor = null
    }

    /**
     * Carry the lock on camera motion alone, because the detector was not RUN this frame.
     *
     * A DIFFERENT THING FROM A MISS, and the distinction is the reason this method exists
     * rather than callers passing a null sighting. A miss is evidence: the detector looked
     * and did not find the wicket, which should age the lock towards LOST. This is the
     * absence of evidence: the camera screen deliberately does not run a 960-wide contour
     * sweep while a ball is in the air, because the delivery is the measurement that cannot
     * be taken again and the wicket is not going anywhere.
     *
     * Passing null through [onFrame] during a delivery would drop a perfectly good lock
     * eight frames in, every single ball, and the readout would blame a detector that was
     * never asked. So the anchor moves with the camera, the state does not change, and the
     * note says plainly that nothing looked.
     */
    @Synchronized
    fun carry(motion: FrameMotion, frameAspect: Float): WicketLock? {
        aspect = if (frameAspect > 0f) frameAspect else aspect
        lastMotion = motion
        if (anchor == null) return null

        if (motion.isUsable) {
            blindFrames = 0
            anchor = anchor?.map { motion.apply(it, aspect) }
        } else {
            motionUnresolved++
            blindFrames++
        }

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
     * The alternative — dropping the lock on every rotation — is what the ball tracker
     * does, and is right there: a flight that jumps a quarter turn mid-air is not one ball.
     * A wicket is different. It is still in the same place in the world, the operator has
     * not changed their mind about where it is, and a hand-placed lock in particular must
     * survive this or the feature is a toy. So the anchor is mapped exactly and the state
     * drops to REACQUIRE, which says the coordinates are a prediction to be confirmed
     * rather than a fresh measurement.
     */
    @Synchronized
    fun onRotation(quarterTurnsCw: Int, newAspect: Float) {
        val turns = ((quarterTurnsCw % 4) + 4) % 4
        if (turns == 0) return
        rotations++
        if (newAspect > 0f) aspect = newAspect

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
     * line of known length, and 0.2286 m is the entire reason anything downstream can
     * report a speed in km/h rather than in frame widths per second.
     *
     * @param top optional third tap, on the top of a stump. It buys a scale in the vertical
     *   direction, which the base pair cannot give at any price: those two points are
     *   collinear, and a line fixes distances only along itself.
     * @param kind [WicketKind.STUMPS] when the taps were on stumps and the span really is
     *   0.2286 m; [WicketKind.STONE] when the operator pointed at a stone, which fixes a
     *   place and nothing else.
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
        anchor = WicketAnchor(ordered.first, ordered.second, top)
        this.kind = kind
        source = WicketLockSource.MANUAL
        state = WicketTrackState.CONFIRMED
        streak = CONFIRM_FRAMES
        coastFrames = 0
        ageFrames = 0
        heldFrames = 1
        blindFrames = 0
        dissent = 0
        dissentAnchor = null
        residuals.clear()
        scoreEma = 1f
        note = "hand-placed"
    }

    /** Give the wicket back to the detector. */
    @Synchronized
    fun clearManualLock() {
        if (source != WicketLockSource.MANUAL) return
        clearLock()
        note = "hand-placed lock cleared"
    }

    /** A new delivery, a new scene, a new everything. Counters included. */
    @Synchronized
    fun reset() {
        clearLock()
        residuals.clear()
        frameTimes.clear()
        blindFrames = 0
        framesSeen = 0
        framesWithSighting = 0
        confirmations = 0
        reacquires = 0
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
        ageFrames = ageFrames,
        heldFrames = heldFrames,
        confidence = confidence(),
        jitter = jitter(),
        motionConfidence = lastMotion.confidence,
        motionMagnitude = lastMotion.magnitude,
        framesPerSecond = framesPerSecond(),
        lockSource = if (anchor == null) null else source,
        kind = kind,
        note = note,
    )

    private fun currentLock(): WicketLock? {
        val held = anchor ?: return null
        val heldKind = kind ?: return null
        return WicketLock(
            anchor = held,
            kind = heldKind,
            source = source,
            state = state,
            confidence = confidence(),
            ageFrames = ageFrames,
            jitter = jitter(),
            heldFrames = heldFrames,
            aspect = aspect,
        )
    }

    /**
     * How much the lock is worth right now, 0..1.
     *
     * Four independent ways a lock goes bad, multiplied rather than averaged — because any
     * one of them being terrible should sink the number on its own, and an average lets
     * three good terms hide a fatal one. A rock-steady lock on a wicket the camera has not
     * seen for five frames is not three-quarters of a lock.
     */
    private fun confidence(): Float {
        if (anchor == null) return 0f

        val stateTerm = when (state) {
            WicketTrackState.CONFIRMED -> 1f
            WicketTrackState.REACQUIRE -> 0.8f
            WicketTrackState.TENTATIVE -> 0.25f + 0.2f * (streak.toFloat() / CONFIRM_FRAMES).coerceAtMost(1f)
            WicketTrackState.TEMPORARILY_LOST -> 0.55f
            WicketTrackState.LOST -> 0f
        }

        /*
         * WHAT "STALE" MEANS DEPENDS ENTIRELY ON WHERE THE LOCK CAME FROM.
         *
         * Caught on a phone, pointed at a wicket placed by hand: the panel read
         * "STUMPS · CONFIRMED · BY HAND" in green above a confidence of 0.15 and a red bar.
         * Both were computed correctly and they contradicted each other, which is precisely
         * the failure this class exists to stop.
         *
         * The cause: [ageFrames] counts frames since a DETECTION last agreed with the lock.
         * For a detected lock that is exactly the right measure of staleness. For a
         * hand-placed one it measures nothing at all — the operator pointed at the wicket,
         * the detector's opinion was never part of the claim, and a detector that never
         * finds it again changes nothing about where it is.
         *
         * What DOES make a hand-placed lock stale is losing track of the camera, because
         * then the anchor is no longer being carried. So that is what its age term reads.
         */
        val ageTerm = if (source == WicketLockSource.MANUAL) {
            (1f - blindFrames.toFloat() / (MAX_BLIND_FRAMES + 1)).coerceIn(0.2f, 1f)
        } else {
            // Linear in the age, to zero at the coasting limit. A coasting lock is drawn
            // from a prediction and the prediction gets worse every frame; the number
            // should say so continuously rather than falling off a cliff at the limit.
            (1f - ageFrames.toFloat() / (MAX_COAST_FRAMES + 1)).coerceIn(0.15f, 1f)
        }

        val jitterTerm = (1f - jitter() / MAX_MEASURABLE_JITTER).coerceIn(0.2f, 1f)

        // Never zero: a still scene with no trackable corners is not the same as a scene
        // where the camera is known to have lurched, and a lock should not be written off
        // for being pointed at a plain wall.
        val motionTerm = if (lastMotion.isUsable) 1f else (1f - blindFrames * 0.15f).coerceIn(0.4f, 1f)

        val scoreTerm = if (source == WicketLockSource.MANUAL) 1f else scoreEma.coerceIn(0.35f, 1f)

        return (stateTerm * ageTerm * jitterTerm * motionTerm * scoreTerm).coerceIn(0f, 1f)
    }

    /** RMS of the recent residuals, in frame widths. Zero when there are none yet. */
    private fun jitter(): Float {
        if (residuals.isEmpty()) return 0f
        var sum = 0.0
        for (r in residuals) sum += r.toDouble() * r
        return sqrt(sum / residuals.size).toFloat()
    }

    private fun pushResidual(value: Float) {
        residuals.addLast(value)
        while (residuals.size > JITTER_WINDOW) residuals.removeFirst()
    }

    private fun recordFrameTime(timestampMs: Long) {
        // A clock that went backwards is a different session's timestamps, or the UI clock
        // being handed in by mistake. Either way the window is meaningless now.
        if (frameTimes.isNotEmpty() && timestampMs < frameTimes.last()) frameTimes.clear()
        frameTimes.addLast(timestampMs)
        while (frameTimes.size > FPS_WINDOW) frameTimes.removeFirst()
    }

    /** Measured over the rolling window, or zero when there is not enough of one. */
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
     * first or hopelessly loose for the second — loose enough, at forty metres, to accept
     * something a couple of metres away on the ground.
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
         * Frames a candidate must be found in the same place before it is a lock.
         *
         * Four, at the twenty to thirty frames a second this screen manages with both
         * detectors running, is about a sixth of a second. Long enough that a coincidental
         * alignment of a bat, a pad and a boot has to hold still through it — which it does
         * not, because a batter moves — and short enough that the operator does not watch
         * the screen say SEARCHING at an obvious wicket.
         */
        const val CONFIRM_FRAMES = 2

        /** Frames to re-confirm after coasting. Fewer: it was already proved once. */
        const val REACQUIRE_CONFIRM_FRAMES = 2

        /**
         * Frames a confirmed lock may coast without being seen.
         *
         * Eight is a quarter to a third of a second — a bowler crossing the wicket in the
         * delivery stride, the batter's backlift covering it, one badly blurred frame. It
         * is deliberately far short of the twelve the pitch-check screen used to hold for,
         * because that was long enough to survive the phone being turned away from the
         * pitch entirely, which it duly did on the first field test.
         */
        const val MAX_COAST_FRAMES = 8

        /** And a wall-clock limit, for when frames are arriving slowly. */
        const val MAX_COAST_MS = 400L

        /** Agreeing sightings away from the lock before the lock is the thing that is wrong. */
        const val DISSENT_FRAMES = 5

        /** Frames without a camera motion estimate before a manual lock stops being a fact. */
        const val MAX_BLIND_FRAMES = 6

        /** Residuals kept for the jitter figure. About half a second. */
        const val JITTER_WINDOW = 12

        /** Frame times kept for the measured rate. */
        const val FPS_WINDOW = 30

        /** Gates, as multiples of the locked wicket's own span in the picture. */
        const val LOCK_GATE_SPANS = 0.9f
        const val REACQUIRE_GATE_SPANS = 2.0f
        const val MANUAL_NUDGE_GATE_SPANS = 1.2f

        /** Floor and ceiling, in frame widths, for wickets that are tiny or enormous. */
        const val MIN_GATE = 0.012f
        const val MAX_GATE = 0.20f

        /** How hard each state pulls the anchor towards the newest sighting. */
        const val TENTATIVE_BLEND = 0.5f
        const val REACQUIRE_BLEND = 0.6f
        const val CONFIRMED_BLEND = 0.18f
        const val MANUAL_NUDGE_WEIGHT = 0.06f

        /** How fast the detector's score is followed. */
        const val SCORE_SMOOTHING = 0.25f

        /** Jitter above which a candidate is not steady enough to be promoted. */
        const val MAX_CONFIRM_JITTER = 0.048f

        /**
         * Jitter above which nothing may be MEASURED from the lock.
         *
         * Looser than the promotion threshold on purpose. Promotion is a one-time decision
         * made when the evidence is freshest and can afford to be strict; this is applied
         * continuously, and a lock flickering in and out of measurable on alternate frames
         * would produce a speed readout that appears and vanishes for no reason the
         * operator can see.
         */
        const val MAX_MEASURABLE_JITTER = 0.05f

        /**
         * A span below this is too few pixels to divide 0.2286 m by.
         *
         * At 1.5% of the frame width a 960-wide analysis frame gives the whole wicket
         * fourteen pixels, so one pixel of error in either outer stump is a seven percent
         * error in every metre that comes out of it. Refusing is the only honest answer;
         * the operator needs to move closer or zoom, and a screen that says so is more
         * use than a number that is quietly a tenth out.
         */
        const val MIN_MEASURABLE_SPAN = 0.015f
    }
}
