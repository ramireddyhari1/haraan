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
) {
    /** Height in the picture. Not a height in metres; the camera's angle sets this. */
    val height: Float get() = baseY - topY
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
) {
    /** The three bases, left to right, for drawing and for pairing with a crease later. */
    val baseLeft: Point2 get() = Point2(left.centreX.toDouble(), left.baseY.toDouble())
    val baseMiddle: Point2 get() = Point2(middle.centreX.toDouble(), middle.baseY.toDouble())
    val baseRight: Point2 get() = Point2(right.centreX.toDouble(), right.baseY.toDouble())

    /** Outside to outside in the picture. In the world this is [PitchGeometry.STUMP_SET_WIDTH_M]. */
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
object StumpGeometry {

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
    const val MAX_HEIGHT_RATIO = 1.7f

    /** How far the three bases may sit apart vertically, as a share of their own height. */
    const val MAX_BASE_SPREAD = 0.4f

    /** Wider gap over narrower. A wicket is evenly spaced; a fence is too, which is why
     *  this alone proves nothing and the other tests exist. */
    const val MAX_GAP_RATIO = 1.8f

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
    ): StumpSet? {
        if (candidates.size < MIN_CANDIDATES) return null

        val pool = candidates
            .sortedByDescending { it.height }
            .take(MAX_CANDIDATES)
            .sortedBy { it.centreX }

        var best: StumpSet? = null
        for (i in pool.indices) {
            for (j in i + 1 until pool.size) {
                for (k in j + 1 until pool.size) {
                    val set = evaluate(pool[i], pool[j], pool[k], creases, frameAspect) ?: continue
                    if (best == null || set.score > best!!.score) best = set
                }
            }
        }
        return best
    }

    /** Three bars, left to right, scored as a wicket — or null if they cannot be one. */
    fun evaluate(
        left: StumpCandidate,
        middle: StumpCandidate,
        right: StumpCandidate,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StumpSet? {
        val heights = listOf(left.height, middle.height, right.height)
        if (heights.any { it <= 0f }) return null

        val tallest = heights.max()
        val shortest = heights.min()
        val heightRatio = tallest / shortest
        if (heightRatio > MAX_HEIGHT_RATIO) return null

        val meanHeight = heights.average().toFloat()

        // Stumps stand on one line. Bases scattered up and down the picture are three
        // separate objects at three different distances, not a wicket.
        val baseSpread = listOf(left.baseY, middle.baseY, right.baseY).let { it.max() - it.min() }
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
     * How far the wicket's base line runs across the picture, in metres per normalised unit.
     *
     * The one honest measurement a wicket alone supports: the three stumps are
     * [PitchGeometry.STUMP_SET_WIDTH_M] apart outside to outside, so their span in the
     * image fixes a scale ALONG THAT LINE. It says nothing about distances in any other
     * direction, and must not be used as though it did.
     */
    fun metresPerUnitAcross(set: StumpSet): Double? {
        val span = abs(set.spanX)
        if (span < 1e-4f) return null
        return PitchGeometry.STUMP_SET_WIDTH_M / span
    }
}
