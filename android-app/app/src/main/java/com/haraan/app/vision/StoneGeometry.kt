package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min

/**
 * One compact solid thing standing on the ground that might be a gully wicket.
 *
 * Normalised upright image coordinates, like everything else in this package. [baseY] is
 * where it meets the ground, and it is the only part of this worth anchoring anything to.
 */
data class StoneCandidate(
    val centreX: Float,
    val baseY: Float,
    val topY: Float,
    val width: Float,
    /**
     * Contour area over bounding-box area, 0..1.
     *
     * The one measurement that separates a stone from a bush. Both are dark lumps of
     * roughly the right size sitting on the ground; a stone FILLS its box and a bush does
     * not. Nothing else in this file works without it.
     */
    val solidity: Float,
) {
    val height: Float get() = baseY - topY
    val aspect: Float get() = if (width <= 0f) 0f else height / width
}

/**
 * A single mark being used as a wicket, and how much it should be believed.
 *
 * WHAT IT GIVES YOU AND WHAT IT COSTS. Three stumps hand over a known 0.2286 m across the
 * set, which is a SCALE. A stone hands over nothing of the kind: a lump of granite has no
 * agreed size, so this fixes a POINT on the ground and no distance whatsoever. Anything
 * that later wants metres out of a gully wicket has to get them somewhere else — the
 * pitch length, the creases, a second mark at the other end. Pretending otherwise would
 * be the same mistake [StumpGeometry] documents at length about collinear bases, made
 * again for worse reasons.
 */
data class StoneMark(
    val centreX: Float,
    val baseY: Float,
    val topY: Float,
    val width: Float,
    val solidity: Float,
    /** How stone-like this was, 0..1. Ranks candidates; not a probability. */
    val score: Float,
    /**
     * Distance to the nearest crease-angled segment in frame widths, or null when the
     * pitch detector offered none.
     *
     * Null is not "far away", it is "nobody asked" — and the two are worth very different
     * amounts, so they are never collapsed into one number.
     */
    val creaseDistance: Float?,
    /**
     * Other blobs in frame of much the same size and solidity.
     *
     * A gully wicket is one or two marks on an otherwise empty strip. Twenty of them means
     * the camera is pointed at rubble, a stone wall or a line of fence bases, and the best
     * of twenty lookalikes is not a wicket however well it scores on its own.
     */
    val lookalikes: Int,
) {
    val base: Point2 get() = Point2(centreX.toDouble(), baseY.toDouble())
    val height: Float get() = baseY - topY
}

/**
 * Picking a gully wicket out of a frame full of lumps.
 *
 * WHY THIS EXISTS AT ALL. [StumpGeometry] looks for three tall thin bars in a particular
 * relationship, and on a real square that is the right thing to look for. On the grounds
 * this app is actually used on, the wicket is a stone, a brick, a stack of two stones, or
 * a stick jammed upright in the dirt — one object, not three, with no fixed width and no
 * relationship to anything except the ground it stands on. The three-bar detector cannot
 * find that and should not be bent into trying: its entire discriminating power comes
 * from the RELATIONSHIP between three bars, and there is only one object here.
 *
 * So this is a separate test with separate evidence. It is weaker evidence, and it says
 * so: a single lump is a far more ambiguous thing than a wicket, and the scores here are
 * deliberately lower than [StumpGeometry] returns for the same quality of fit.
 */
object StoneGeometry {

    /**
     * Wider than tall is allowed, because a brick on its side is a wicket in gully cricket
     * and refusing it would fail on exactly the grounds this is for.
     */
    const val MIN_ASPECT = 0.45f

    /**
     * Above this it is a bar, and the three-bar path owns bars.
     *
     * Overlapping the two detectors would mean a fence post could be admitted here after
     * being correctly rejected there, which is the wrong way round.
     */
    const val MAX_ASPECT = 5.0f

    /** Roughly a stone standing on end: the shape that earns full marks. */
    const val IDEAL_ASPECT = 1.8f

    /**
     * Raised from 0.02 after the first field test, which offered 478 lumps in one frame.
     *
     * At 0.02 of a frame's height a tuft of grass qualifies, and a frame of a grass
     * outfield therefore produces hundreds of candidates - which then drown the real
     * wicket in lookalikes and get everything refused. A wicket is something a batter can
     * see from twenty metres.
     */
    const val MIN_HEIGHT_FRACTION = 0.035f
    const val MAX_HEIGHT_FRACTION = 0.28f
    const val MAX_WIDTH_FRACTION = 0.18f

    /** Below this it is foliage, not a stone. See [StoneCandidate.solidity]. */
    const val MIN_SOLIDITY = 0.55f

    /** Beyond this from a crease, in frame widths, standing on the line stops counting. */
    const val MAX_CREASE_DISTANCE = 0.10f

    /** More lookalikes than this and the frame is rubble, whatever the best one scores. */
    const val MAX_LOOKALIKES = 8

    /**
     * How many candidates are scored, largest first.
     *
     * Grass gives hundreds of small lumps and a wicket gives one big one, so taking the
     * biggest few is both the cheap thing and the right thing. It also makes the lookalike
     * count mean something: counted over every blob in a grass field it only ever says
     * "there is grass here", counted over the largest forty it says "there are forty
     * things this size", which is the question being asked.
     */
    const val MAX_POOL = 40

    /** Two marks count as the same size within this fraction of each other. */
    const val LOOKALIKE_TOLERANCE = 0.35f

    /**
     * What a mark scores when no crease-angled segments were offered at all.
     *
     * Not 1.0, because "shaped like a stone" without "standing on a line" is most of a
     * roadside kerb's CV too. Not 0.0 either, because the pitch detector fails on faint
     * paint constantly and a real wicket should not be thrown away for its sake.
     */
    const val NO_CREASE_EVIDENCE = 0.62f

    /** What it scores when creases existed and it was nowhere near any of them. */
    const val OFF_CREASE_EVIDENCE = 0.45f

    /** Below this, the mark is not reported at all. */
    const val MIN_SCORE = 0.35f

    /**
     * The best gully wicket in the frame, or null.
     *
     * @param frameAspect width over height, needed to measure nearness to a crease in
     *   frame widths rather than in two incompatible units.
     */
    fun findMark(
        candidates: List<StoneCandidate>,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StoneMark? {
        if (candidates.isEmpty()) return null

        val pool = candidates
            .filter { isPlausible(it) }
            .sortedByDescending { it.height * it.width }
            .take(MAX_POOL)
        if (pool.isEmpty()) return null

        var best: StoneMark? = null
        for (candidate in pool) {
            val mark = evaluate(candidate, pool, creases, frameAspect) ?: continue
            if (best == null || mark.score > best.score) best = mark
        }
        return best
    }

    /** How many of these are the right shape to be a wicket at all. For reporting. */
    fun plausibleCount(candidates: List<StoneCandidate>): Int =
        candidates.count { isPlausible(it) }

    /** Shape alone, before any question of where it is standing. */
    fun isPlausible(candidate: StoneCandidate): Boolean {
        if (candidate.width <= 0f || candidate.height <= 0f) return false
        if (candidate.aspect < MIN_ASPECT || candidate.aspect > MAX_ASPECT) return false
        if (candidate.height < MIN_HEIGHT_FRACTION || candidate.height > MAX_HEIGHT_FRACTION) {
            return false
        }
        if (candidate.width > MAX_WIDTH_FRACTION) return false
        if (candidate.solidity < MIN_SOLIDITY) return false
        return true
    }

    /**
     * One candidate scored against the frame it was found in.
     *
     * The score is a product, not a sum, of a shape term and an evidence term. A sum would
     * let a beautifully stone-shaped object with nothing under it out-score a scruffy one
     * sitting exactly on the crease, and on a ground the second is the wicket.
     */
    fun evaluate(
        candidate: StoneCandidate,
        others: List<StoneCandidate>,
        creases: List<CreaseSegment> = emptyList(),
        frameAspect: Float = 1f,
    ): StoneMark? {
        if (!isPlausible(candidate)) return null

        // 1 at MIN_SOLIDITY's opposite extreme, falling to 0 as it approaches the floor.
        val solidityTerm = ((candidate.solidity - MIN_SOLIDITY) / (1f - MIN_SOLIDITY))
            .coerceIn(0f, 1f)

        // Falls off either side of a stone standing on end, in proportion rather than in
        // absolute aspect, so "twice as tall as ideal" and "half as tall" cost the same.
        val ratio = if (candidate.aspect > IDEAL_ASPECT) {
            candidate.aspect / IDEAL_ASPECT
        } else {
            IDEAL_ASPECT / candidate.aspect
        }
        val aspectTerm = (1f / ratio).coerceIn(0f, 1f)

        val shape = 0.60f * solidityTerm + 0.40f * aspectTerm

        val nearest = creases.minOfOrNull {
            it.distanceFrom(candidate.centreX, candidate.baseY, frameAspect)
        }
        val evidence = when {
            nearest == null -> NO_CREASE_EVIDENCE
            nearest > MAX_CREASE_DISTANCE -> OFF_CREASE_EVIDENCE
            // On the line is worth full marks; the fall-off between is linear and has no
            // deeper justification than that it is monotonic and cheap.
            else -> 1f - 0.38f * (nearest / MAX_CREASE_DISTANCE)
        }

        val lookalikes = others.count { it !== candidate && isLookalike(candidate, it) }
        if (lookalikes > MAX_LOOKALIKES) return null
        // One other mark is expected — the wicket at the far end is also a stone. Past
        // that, each extra lookalike costs.
        val crowding = 1f / (1f + LOOKALIKE_TOLERANCE * max(0, lookalikes - 1))

        val score = shape * evidence * crowding
        if (score < MIN_SCORE) return null

        return StoneMark(
            centreX = candidate.centreX,
            baseY = candidate.baseY,
            topY = candidate.topY,
            width = candidate.width,
            solidity = candidate.solidity,
            score = score,
            creaseDistance = nearest,
            lookalikes = lookalikes,
        )
    }

    /** Same size and same kind of thing, within tolerance. */
    fun isLookalike(a: StoneCandidate, b: StoneCandidate): Boolean {
        if (b.solidity < MIN_SOLIDITY) return false
        val tall = max(a.height, b.height)
        val short = min(a.height, b.height)
        if (short <= 0f) return false
        return (tall - short) / tall <= LOOKALIKE_TOLERANCE &&
            abs(a.aspect - b.aspect) <= a.aspect * LOOKALIKE_TOLERANCE
    }
}
