package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min

/**
 * One tall thin bright thing that might be a stump.
 *
 * Normalised upright image coordinates, the same frame everything else in this package
 * speaks in. [baseY] is the bottom — the only part of a stump that is actually ON the
 * ground, and therefore the only part a plane map may ever be told about.
 */
data class StumpCandidate(
    val centreX: Float,
    val baseY: Float,
    val topY: Float,
    /**
     * How much it stands out from the ground right beside it, grey levels; 0 when unmeasured.
     * When measured, it — not height — decides which bars make the search pool: on streaky
     * grass the tallest bars are all grass, and the stumps never got a look.
     */
    val contrast: Float = 0f,
) {
    /** Height in the picture. Not a height in metres; the camera's angle sets this. */
    val height: Float get() = baseY - topY
}

/** How a set of stumps was found — the admin screen's "detection method". */
enum class StumpMethod(val label: String) {
    /** Three separate contour bars on the scaled frame, NOT confirmed by the comb. */
    CONTOUR_TRIPLE("contour triple"),

    /** Three contour bars, re-measured by the full-resolution comb. */
    TRIPLE_COMB("triple + comb"),

    /** One merged blob on the scaled frame, resolved into three stumps by the comb. */
    MERGED_COMB("merged + comb"),

    /** Re-found at the held place, comb only, no whole-frame search. */
    FOCUS_COMB("focus comb"),

    /** Tapped by the operator. */
    MANUAL("by hand"),
}

/**
 * Three bars that behave like a set of stumps.
 *
 * WHAT THIS IS GOOD FOR. A wicket is the most dependable landmark on a cricket field: it
 * is never absent, never faded, never mown off, and it is always exactly 0.2286 m across.
 * Crease paint is none of those things, which is why the crease detector fails on worn
 * squares and cannot work at all on an indoor mat.
 *
 * WHAT IT IS NOT GOOD FOR, and this is the part worth being blunt about. A homography maps
 * a PLANE and needs four points that are not all on one line. The three stump bases are
 * collinear by construction — they stand on the bowling crease. So a wicket on its own
 * fixes a LINE and a SCALE ALONG THAT LINE, and no amount of cleverness turns that into a
 * plane. Calibration still needs a second constraint running across it: the popping crease
 * at the same end, the far wicket, or the mat edges indoors.
 *
 * So nothing here produces a [PitchQuad], and nothing here should be made to. It produces
 * a measured, drawable landmark, and the next piece of work is deciding what to pair it
 * with.
 */
data class StumpSet(
    val left: StumpCandidate,
    val middle: StumpCandidate,
    val right: StumpCandidate,
    /**
     * How well these three behaved like a wicket, 0..1.
     *
     * Ranks candidate triples against each other, exactly as [BallSighting.trackingConfidence]
     * ranks blobs. It is not a probability and has never been checked against a human
     * saying "yes, those are the stumps".
     */
    val score: Float,
    /**
     * Distance from the base line's midpoint to the nearest crease-angled segment, in
     * frame widths, or null when no segments were offered.
     *
     * Reported rather than folded away because it is the difference between "this scored
     * well because it is shaped like a wicket" and "this scored well because it is shaped
     * like a wicket AND standing on a line" — and only the second is worth much.
     */
    val creaseDistance: Float? = null,
    /**
     * The camera's roll as read off these stumps, degrees, positive clockwise; null when
     * the finder did not measure it. Only the comb fit does — see [StumpProfile].
     */
    val rollDeg: Float? = null,
    /**
     * Whether the bar positions are sub-pixel, from the full-resolution comb fit, rather
     * than whole contour boxes on a scaled-down frame. It decides how small a wicket may
     * be and still be measured from: see [WicketLock.isMeasurable].
     */
    val subPixel: Boolean = false,
    val method: StumpMethod = StumpMethod.CONTOUR_TRIPLE,
) {
    /** The three bases, left to right, for drawing and for pairing with a crease later. */
    val baseLeft: Point2 get() = Point2(left.centreX.toDouble(), left.baseY.toDouble())
    val baseMiddle: Point2 get() = Point2(middle.centreX.toDouble(), middle.baseY.toDouble())
    val baseRight: Point2 get() = Point2(right.centreX.toDouble(), right.baseY.toDouble())

    /**
     * Outer stump CENTRE to centre in the picture. In the world this is
     * [PitchGeometry.STUMP_CENTRES_SPAN_M] — a stump's width less than the 9 inches.
     */
    val spanX: Float get() = right.centreX - left.centreX

    val meanHeight: Float get() = (left.height + middle.height + right.height) / 3f
}

/**
 * Picking a wicket out of a frame full of vertical things.
 *
 * Pure arithmetic on candidate boxes, deliberately: a fence paling, a sightscreen strut, a
 * net pole and a batter's leg are all tall thin bright objects, and the entire difference
 * between them and a wicket is the RELATIONSHIP between three of them. That relationship
 * is geometry, it is where this either works or doesn't, and it should be provable at a
 * desk rather than only on a ground.
 */
/**
 * What a search of one frame's bars came to.
 *
 * [reason] is populated ONLY when [set] is null, and it describes the nearest miss rather
 * than the last one tried — a frame full of fence palings should report the complaint about
 * the three most stump-like bars in it, not about three specks in a corner.
 */
data class StumpSearch(
    val set: StumpSet?,
    val reason: String?,
    /**
     * Every distinct wicket-shaped triple, best first, at most [StumpGeometry.MAX_RANKED].
     *
     * WHY MORE THAN ONE. A single "best this frame" is a hard commitment made on one
     * frame's evidence: when a fence triple outscores the wicket by a hair on alternate
     * frames, the tracker downstream sees the answer hop between them and can confirm
     * neither. Handing it the runners-up lets evidence accumulate over time for each
     * place separately, and the wicket wins on persistence rather than on one frame's luck.
     */
    val ranked: List<StumpSet> = listOfNotNull(set),
)

object StumpGeometry {

    /** Most distinct triples handed on per frame. See [StumpSearch.ranked]. */
    const val MAX_RANKED = 4

    /** Fewer than three bars cannot be a wicket. */
    const val MIN_CANDIDATES = 3

    /**
     * Most bars considered, tallest first.
     *
     * The search is over every triple, which is cheap at this size and quadratically less
     * cheap beyond it. A frame offering more than forty tall thin bright bars is a fence
     * or a net, and the wicket in it will not be found by looking harder.
     */
    const val MAX_CANDIDATES = 40

    /** Tallest over shortest. Perspective makes the far stump shorter, but not by much. */
    const val MAX_HEIGHT_RATIO = 1.85f

    /** How far the three bases may sit apart vertically, as a share of their own height. */
    const val MAX_BASE_SPREAD = 0.45f

    /** Wider gap over narrower. A wicket is evenly spaced; a fence is too, which is why
     *  this alone proves nothing and the other tests exist. */
    const val MAX_GAP_RATIO = 2.2f

    /**
     * Span across the three over their height in the picture.
     *
     * In the world that ratio is 0.2286 / 0.711 = 0.32. In a PICTURE it is not, because the
     * camera's elevation foreshortens height and distance foreshortens span, and the two do
     * not move together. From ground level it sits near the true ratio; from a raised
     * camera looking down it grows. The band is therefore generous on purpose — it is here
     * to throw out a fence paling four times its own width apart, not to measure anything.
     */
    const val MIN_SPAN_RATIO = 0.12f
    const val MAX_SPAN_RATIO = 1.30f

    /**
     * How far from a crease-angled segment a wicket may sit before nearness counts for
     * nothing, in frame widths.
     *
     * Roughly a wicket's own width at the distances this films from. Beyond it the term
     * is simply zero rather than negative: a set far from every segment is ranked on its
     * shape alone, not punished for the crease detector having had a bad frame.
     */
    const val CREASE_REACH = 0.05f

    /**
     * How much of the score nearness may claim.
     *
     * Half. Enough to settle a tie between two equally wicket-shaped triples, not enough
     * to promote a badly shaped one that happens to sit on a line — and the shape rules
     * remain a gate that must be passed before any of this is reached.
     */
    const val CREASE_WEIGHT = 0.5f

    /**
     * The best wicket-shaped triple in [candidates], or null.
     *
     * Null rather than "the closest thing available" for the reason the rest of this
     * package refuses things: a drawn wicket in the wrong place is worse than no wicket,
     * because everything downstream would then be measured from it.
     */
    fun findSet(
        candidates: List<StumpCandidate>,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StumpSet? = search(candidates, creases, frameAspect).set

    /**
     * The best wicket-shaped triple, AND — when there is none — which rule threw the
     * nearest miss out.
     *
     * WHY THIS EXISTS, WRITTEN ON THE GROUND IT WAS NEEDED ON. Pointed at three stumps a
     * metre away, the screen read "3 bars, none of them three in a wicket's shape". Three
     * bars is exactly one triple, so exactly one rule rejected it — and the readout could
     * not say which, which left tuning to guesswork from a photograph.
     *
     * A detector that can explain its refusal is tunable in an afternoon on a ground. One
     * that only says no is tunable by rebuilding with print statements, which is a
     * different day's work every time.
     */
    fun search(
        candidates: List<StumpCandidate>,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StumpSearch {
        if (candidates.size < MIN_CANDIDATES) {
            return StumpSearch(null, "only ${candidates.size} tall thin bars in frame")
        }

        val byContrast = candidates.any { it.contrast > 0f }
        val pool = candidates
            .sortedByDescending { if (byContrast) it.contrast else it.height }
            .take(MAX_CANDIDATES)
            .sortedBy { it.centreX }

        var best: StumpSet? = null
        val valid = ArrayList<StumpSet>()
        /*
         * The nearest miss is the TALLEST rejected triple, not the first or the last.
         *
         * The pool is sorted by centreX for the combinatorial search, so iteration
         * order says nothing about how stump-like a triple is. Instead, we track which
         * rejected triple had the tallest average height — the bars most likely to be
         * the real stumps — and explain only that one, once, after the search.
         */
        var missI = -1
        var missJ = -1
        var missK = -1
        var missHeight = -1f
        for (i in pool.indices) {
            for (j in i + 1 until pool.size) {
                for (k in j + 1 until pool.size) {
                    val set = evaluate(pool[i], pool[j], pool[k], creases, frameAspect)
                    if (set != null) {
                        valid.add(set)
                        if (best == null || set.score > best.score) best = set
                    } else {
                        val h = (pool[i].height + pool[j].height + pool[k].height) / 3f
                        if (h > missHeight) {
                            missHeight = h
                            missI = i; missJ = j; missK = k
                        }
                    }
                }
            }
        }
        val reason = if (best != null || missI < 0) null
            else explain(pool[missI], pool[missJ], pool[missK])
        return StumpSearch(best, reason, distinct(valid))
    }

    /**
     * The best triples that are not the same wicket counted twice.
     *
     * Four bars of one real wicket and a shadow make several valid triples that share two
     * bars; they are one place, and counting them separately would hand the tracker four
     * votes for a single object. Greedy by score: a triple whose middle sits within half a
     * span of one already taken is a re-reading of it, not a rival.
     */
    internal fun distinct(sets: List<StumpSet>): List<StumpSet> {
        if (sets.size <= 1) return sets
        val out = ArrayList<StumpSet>(MAX_RANKED)
        for (set in sets.sortedByDescending { it.score }) {
            val mid = (set.left.centreX + set.right.centreX) / 2f
            val clash = out.any { kept ->
                val keptMid = (kept.left.centreX + kept.right.centreX) / 2f
                abs(mid - keptMid) < 0.5f * max(abs(set.spanX), abs(kept.spanX)) &&
                    abs(set.middle.baseY - kept.middle.baseY) < max(set.meanHeight, kept.meanHeight)
            }
            if (!clash) out.add(set)
            if (out.size >= MAX_RANKED) break
        }
        return out
    }

    /**
     * Why these three cannot be a wicket, or null when they can.
     *
     * The same rules as [evaluate] in the same order, deliberately duplicated as text
     * rather than threaded through it as an out-parameter: [evaluate] is on the hot path
     * for every triple of up to forty candidates, and it returns null without building a
     * string. This is called only when that has already said no.
     *
     * Each message carries the MEASURED value and the LIMIT, because "too uneven" sends a
     * tester back to the source and "gaps 2.3:1, limit 1.8" sends them to a number.
     */
    fun explain(left: StumpCandidate, middle: StumpCandidate, right: StumpCandidate): String? {
        val lh = left.height; val mh = middle.height; val rh = right.height
        if (lh <= 0f || mh <= 0f || rh <= 0f) return "a bar with no height"

        val heightRatio = maxOf(lh, maxOf(mh, rh)) / minOf(lh, minOf(mh, rh))
        if (heightRatio > MAX_HEIGHT_RATIO) {
            return "heights %.1f:1, limit %.1f".format(heightRatio, MAX_HEIGHT_RATIO)
        }

        val meanHeight = (lh + mh + rh) / 3f
        val baseSpread = maxOf(left.baseY, maxOf(middle.baseY, right.baseY)) -
            minOf(left.baseY, minOf(middle.baseY, right.baseY))
        if (baseSpread > MAX_BASE_SPREAD * meanHeight) {
            return "bases %.2f apart, limit %.2f of their height"
                .format(baseSpread / meanHeight, MAX_BASE_SPREAD)
        }

        val gapLeft = middle.centreX - left.centreX
        val gapRight = right.centreX - middle.centreX
        if (gapLeft <= 0f || gapRight <= 0f) return "two bars at the same place across"

        val gapRatio = max(gapLeft, gapRight) / min(gapLeft, gapRight)
        if (gapRatio > MAX_GAP_RATIO) {
            return "gaps %.1f:1, limit %.1f".format(gapRatio, MAX_GAP_RATIO)
        }

        val spanRatio = (right.centreX - left.centreX) / meanHeight
        if (spanRatio < MIN_SPAN_RATIO) {
            return "span %.2f of its height, floor %.2f — too narrow for a wicket"
                .format(spanRatio, MIN_SPAN_RATIO)
        }
        if (spanRatio > MAX_SPAN_RATIO) {
            return "span %.2f of its height, ceiling %.2f — too wide for a wicket"
                .format(spanRatio, MAX_SPAN_RATIO)
        }
        return null
    }

    /** Three bars, left to right, scored as a wicket — or null if they cannot be one. */
    fun evaluate(
        left: StumpCandidate,
        middle: StumpCandidate,
        right: StumpCandidate,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StumpSet? {
        val lh = left.height; val mh = middle.height; val rh = right.height
        if (lh <= 0f || mh <= 0f || rh <= 0f) return null

        val tallest = maxOf(lh, maxOf(mh, rh))
        val shortest = minOf(lh, minOf(mh, rh))
        val heightRatio = tallest / shortest
        if (heightRatio > MAX_HEIGHT_RATIO) return null

        val meanHeight = (lh + mh + rh) / 3f

        // Stumps stand on one line. Bases scattered up and down the picture are three
        // separate objects at three different distances, not a wicket.
        val baseSpread = maxOf(left.baseY, maxOf(middle.baseY, right.baseY)) -
            minOf(left.baseY, minOf(middle.baseY, right.baseY))
        if (baseSpread > MAX_BASE_SPREAD * meanHeight) return null

        val gapLeft = middle.centreX - left.centreX
        val gapRight = right.centreX - middle.centreX
        if (gapLeft <= 0f || gapRight <= 0f) return null

        val gapRatio = max(gapLeft, gapRight) / min(gapLeft, gapRight)
        if (gapRatio > MAX_GAP_RATIO) return null

        val span = right.centreX - left.centreX
        val spanRatio = span / meanHeight
        if (spanRatio < MIN_SPAN_RATIO || spanRatio > MAX_SPAN_RATIO) return null

        // Each term is 1 when perfect and falls off towards its own limit, so the score
        // ranks triples by how wicket-like they are rather than merely admitting them.
        val evenness = 1f - (gapRatio - 1f) / (MAX_GAP_RATIO - 1f)
        val levelness = 1f - baseSpread / (MAX_BASE_SPREAD * meanHeight)
        val sameness = 1f - (heightRatio - 1f) / (MAX_HEIGHT_RATIO - 1f)

        val shape = ((evenness + levelness + sameness) / 3f).coerceIn(0f, 1f)

        /*
         * STANDING ON A LINE.
         *
         * Shape alone cannot separate a wicket from a batter's two pads and a bat, which
         * is what it picked on real footage once the trees were excluded: three bars, on
         * the ground, evenly spaced, similar height. Every rule above passes.
         *
         * What differs is where they stand. Stumps sit ON the bowling crease; pads sit
         * behind the popping crease, a metre and a bit in front of it. So nearness to a
         * crease-angled segment is real evidence — and it is only evidence, because the
         * batter is also near a crease, just a different one. It shifts the ranking; it
         * does not settle the question, and it is weighted accordingly.
         *
         * With no segments offered, the score is the shape score exactly as before. An
         * absent signal must never quietly reweight everything.
         */
        val midX = (left.centreX + right.centreX) / 2f
        val midY = (left.baseY + middle.baseY + right.baseY) / 3f
        val nearest = creases.minOfOrNull { it.distanceFrom(midX, midY, frameAspect) }

        if (nearest == null) return StumpSet(left, middle, right, shape, null)

        val nearness = (1f - (nearest / CREASE_REACH)).coerceIn(0f, 1f)
        val score = (shape * (1f - CREASE_WEIGHT) + nearness * CREASE_WEIGHT).coerceIn(0f, 1f)
        return StumpSet(left, middle, right, score, nearest)
    }

    /**
     * A set found some other way — the comb fit — scored for standing on a crease exactly
     * as [evaluate] scores a contour triple, with its own [StumpSet.score] as the shape term.
     */
    fun withCreases(set: StumpSet, creases: List<CreaseSegment>, frameAspect: Float): StumpSet {
        val midX = (set.left.centreX + set.right.centreX) / 2f
        val midY = (set.left.baseY + set.middle.baseY + set.right.baseY) / 3f
        val nearest = creases.minOfOrNull { it.distanceFrom(midX, midY, frameAspect) } ?: return set
        val nearness = (1f - (nearest / CREASE_REACH)).coerceIn(0f, 1f)
        val score = (set.score * (1f - CREASE_WEIGHT) + nearness * CREASE_WEIGHT).coerceIn(0f, 1f)
        return set.copy(score = score, creaseDistance = nearest)
    }

    /**
     * How far the wicket's base line runs across the picture, in metres per normalised unit.
     *
     * The one honest measurement a wicket alone supports: the outer stumps' centres are
     * [PitchGeometry.STUMP_CENTRES_SPAN_M] apart, so their span in the image fixes a scale
     * ALONG THAT LINE. It says nothing about distances in any other
     * direction, and must not be used as though it did.
     */
    fun metresPerUnitAcross(set: StumpSet): Double? {
        val span = abs(set.spanX)
        if (span < 1e-4f) return null
        return PitchGeometry.STUMP_CENTRES_SPAN_M / span
    }
}
