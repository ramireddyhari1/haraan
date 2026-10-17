package com.haraan.app.vision

/**
 * THE BATTER'S STUMPS, LOOKED FOR WHERE THE BOWLER'S STUMPS SAY THEY MUST BE.
 *
 * The whole-frame search finds a wicket from the shapes a frame offers, and a wicket can
 * offer none: on IMG_6053 the batter stood in light trousers right behind white stumps, the
 * stumps and legs made one pale shape with no edge of its own, and the far wicket was found
 * on 3 frames of 73 — never before the ball was bowled, so no corridor and no delivery. Yet
 * the comb, pointed at the right place, fitted those stumps on every frame.
 *
 * The bowler's stumps are big and found easily, and the other wicket is a pitch's length
 * beyond them along the same line of sight: the same camera sees it [PITCH_M] further away,
 * so narrower by d / (d + PITCH_M), higher in the picture, and near the same column. That
 * fixes the comb's spacing well enough and narrows where to look to a band. The band is
 * swept a few cells per call, so a cold clip pays for it over a second rather than in one
 * frame; a fit counts only when its width agrees with the prediction, because a comb at the
 * wrong spacing is exactly the scale error every speed downstream would inherit.
 *
 * Pure Kotlin over the luma plane, like [StumpProfile] itself.
 */
class FarWicketSearch(
    /** The lens, frame widths; the live camera sets it once Camera2 has said. */
    var focalFw: Double = ASSUMED_FOCAL_FW,
) {

    private var cell = 0

    /** Where the last one was found: looked at first on every call, then the sweep goes on. */
    private var found: StumpSet? = null

    /** The span the batter's stumps should show, frame widths between outer centres. */
    fun predictedSpan(near: BowlersEnd): Double? {
        if (near.spanFw <= 0.0) return null
        val nearM = focalFw * PitchGeometry.STUMP_CENTRES_SPAN_M / near.spanFw
        return near.spanFw * nearM / (nearM + PITCH_M)
    }

    /**
     * Up to [cells] more cells of the band above [near]; the batter's stumps when one fits,
     * else null. Successive calls continue the sweep where the last one stopped.
     *
     * @param aspect the upright frame's width over its height.
     */
    fun next(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        near: BowlersEnd,
        aspect: Float,
        cells: Int = CELLS_PER_CALL,
    ): StumpSet? {
        val span = predictedSpan(near) ?: return null
        val halfSpan = span / 2.0
        // How tall the stumps stand, in frame heights: the Laws' proportion of the span.
        val tall = span * LAWS_HEIGHT_OVER_SPAN * aspect
        val rowStep = tall * ROW_STEP_OF_HEIGHT
        val colStep = halfSpan * 2.0 * COLUMN_HALF_SPANS
        val rows = ((MAX_RISE - MIN_RISE) / rowStep).toInt() + 1
        val cols = ((RIGHT_OF_NEAR + LEFT_OF_NEAR) / colStep).toInt() + 1
        val total = rows * cols
        if (total <= 0) return null
        /*
         * WHERE IT WAS LAST TIME, FIRST. The tracker needs a wicket seen again and again to
         * confirm it; a sweep that comes back to the same place only once a second and a half
         * gave it one sighting, which lapsed before the next (IMG_6053: found at the start,
         * then nothing for the whole delivery).
         */
        found?.let { last ->
            val again = judge(luma, width, height, rowStride, rotationDegrees, near, aspect, span,
                last.baseMiddle.x.toDouble(), last.baseMiddle.y.toDouble(), last.spanX / 2.0, tall, searchHalfSpans = 1.0)
            if (again != null) return again.also { found = it }
            found = null
        }
        repeat(minOf(cells, total)) {
            val k = cell++ % total
            val row = k / cols
            val col = k % cols
            val baseY = near.base.y - MIN_RISE - row * rowStep
            val centreX = near.base.x - LEFT_OF_NEAR + (col + 0.5) * colStep
            if (centreX !in 0.02..0.98 || baseY - tall <= 0.0) return@repeat
            judge(luma, width, height, rowStride, rotationDegrees, near, aspect, span, centreX, baseY, halfSpan, tall, COLUMN_HALF_SPANS)
                ?.let { found = it; return it }
        }
        return null
    }

    private var polarityOf: BowlersEnd? = null
    private var polarity: Boolean? = null

    /**
     * Whether the bowler's stumps are lighter than what shows between them, from the comb at
     * their own place; null when the comb cannot say this frame. Remembered once read, per place.
     */
    private fun nearPolarity(luma: ByteArray, width: Int, height: Int, rowStride: Int, rotationDegrees: Int, near: BowlersEnd, aspect: Float): Boolean? {
        val held = polarityOf
        if (held != null && kotlin.math.abs(held.base.x - near.base.x) < near.spanFw * 0.5 &&
            kotlin.math.abs(held.base.y - near.base.y) < near.spanFw * 0.5
        ) {
            return polarity
        }
        val fit = StumpProfile.locate(
            luma, width, height, rowStride, rotationDegrees,
            centreX = near.base.x,
            baseY = near.base.y,
            topY = near.base.y - near.spanFw * LAWS_HEIGHT_OVER_SPAN * aspect,
            halfSpanFw = near.spanFw / 2.0,
        ) ?: return null // Not remembered: the next frame may show them.
        polarityOf = near
        polarity = fit.second.bright
        return polarity
    }

    /**
     * The comb at one place, and the tests a predicted wicket must pass: a width near the
     * prediction, and the bowler's stumps' own colour. The stumps, or null.
     */
    private fun judge(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        near: BowlersEnd,
        aspect: Float,
        span: Double,
        centreX: Double,
        baseY: Double,
        halfSpan: Double,
        tall: Double,
        searchHalfSpans: Double,
    ): StumpSet? {
        if (centreX !in 0.02..0.98 || baseY - tall <= 0.0) return null
        val fitted = StumpProfile.locate(
            luma, width, height, rowStride, rotationDegrees,
            centreX = centreX,
            baseY = baseY,
            topY = baseY - tall,
            halfSpanFw = halfSpan,
            tolerance = SPAN_TOLERANCE,
            searchHalfSpans = searchHalfSpans,
            minContrastOverClutter = MIN_CONTRAST_OVER_CLUTTER,
        ) ?: return null
        val set = fitted.first
        if (set.spanX / span !in MIN_SPAN_RATIO..MAX_SPAN_RATIO) return null
        /*
         * THE SAME STUMPS AS THE BOWLER'S. The net behind a wicket is dark posts in pairs,
         * and a stretch of two posts with a gap fits the comb at a wicket's spacing and in
         * a wicket's proportions — a dozen such fits in this band on IMG_6056. A set of
         * stumps is one colour at both ends: bars lighter than what is between them at the
         * bowler's end are lighter at the batter's, and the posts are the other way round.
         * Unknown is not "anything goes": with the bowler's stumps unreadable this frame (the
         * bowler in front of them), the posts would pass.
         */
        val nearBright = nearPolarity(luma, width, height, rowStride, rotationDegrees, near, aspect) ?: return null
        return if (fitted.second.bright == nearBright) set.copy(method = StumpMethod.PREDICTED_COMB) else null
    }

    /**
     * The batter's-end sightings for the tracker: [far] as the detector gave them, plus this
     * call's find while nothing is confirmed — the tracker weighs rivals, and once one is
     * confirmed it looks at its place itself every frame. [near] is the bowler's stumps
     * (steadied, else this frame's); [keep] is the batter's-end filter the detector's own
     * sightings went through. Shared by the clip chain and the live camera so they cannot
     * drift apart.
     */
    fun offer(
        far: List<WicketSighting>,
        held: WicketLock?,
        near: BowlersEnd?,
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        aspect: Float,
        keep: (List<WicketSighting>, BowlersEnd) -> List<WicketSighting>,
    ): List<WicketSighting> {
        val state = held?.state
        if (state != null && state != WicketTrackState.LOST && state != WicketTrackState.TENTATIVE) return far
        if (held?.source == WicketLockSource.MANUAL) return far
        val n = near ?: return far
        val found = next(luma, width, height, rowStride, rotationDegrees, n, aspect) ?: return far
        return far + keep(listOf(WicketSighting.Stumps(found)), n)
    }

    /** Start again, for a phone that was moved or turned. */
    fun reset() {
        cell = 0
        found = null
        polarityOf = null
        polarity = null
    }

    companion object {
        /** Stump to stump: the batter's set is this much further from a camera behind the bowler's. */
        const val PITCH_M = 20.12
        private const val LAWS_HEIGHT_OVER_SPAN = 0.711 / 0.1936
        private const val ASSUMED_FOCAL_FW = 1.4

        /*
         * The band, measured from the bowler's stumps' foot. On every behind-the-bowler clip
         * so far the batter's foot stood 0.27 of the frame higher and 0.0-0.19 of a width to
         * the right; the band is several times wider than that either way, for other heights,
         * lenses and angles.
         */
        private const val MIN_RISE = 0.10
        private const val MAX_RISE = 0.45
        private const val LEFT_OF_NEAR = 0.20
        private const val RIGHT_OF_NEAR = 0.30

        /** Rows half a stump-height apart: the comb still sees most of the stumps between two. */
        private const val ROW_STEP_OF_HEIGHT = 0.5

        /** Each cell lets the comb's middle roam this many half-spans either side. */
        private const val COLUMN_HALF_SPANS = 4.0

        /*
         * The prediction assumes a lens and that the camera is on the pitch's line; real
         * clips came out 1.2x the predicted span. The comb searches widely around it, but a
         * fit must land near it: half the spacing is the classic wrong fit and is refused.
         */
        private const val SPAN_TOLERANCE = 0.45
        private const val MIN_SPAN_RATIO = 0.7
        private const val MAX_SPAN_RATIO = 1.6

        /*
         * Half the whole-frame search's 6: there the comb has only a shape to go on, and grass
         * streaks are what that guard is for. Here the place, the spacing and the stumps' own
         * colour are predicted, and a batter's legs right beside the stumps (IMG_6056, 74 grey
         * levels against a ground spread of 12.7) sat just under 6.
         */
        private const val MIN_CONTRAST_OVER_CLUTTER = 3.0

        /** Cells tried per stumps check while cold: about 30 checks sweep the band. */
        const val CELLS_PER_CALL = 8
    }
}
