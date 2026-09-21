package com.haraan.app.vision

import kotlin.math.abs

/**
 * Where the ground stops and the background starts.
 *
 * WHY THIS EXISTS. [StumpGeometry] can tell a wicket from a badly-shaped group of bars,
 * and it cannot tell a wicket from a row of tree trunks — because geometrically there is
 * nothing to tell. Three trunks, evenly spaced, of similar height, with their bases on one
 * line, satisfy every rule a wicket satisfies. On real club footage that is exactly what
 * was detected: trunks at the treeline and posts along the boundary fence, while the
 * stumps sat untouched in the middle of the frame.
 *
 * The missing constraint was never about SHAPE. It is about PLACE. A wicket stands on the
 * pitch; the pitch is ground; ground is below the horizon. Nothing in the candidate filter
 * said so, so anything vertical above the skyline was as welcome as the stumps.
 *
 * HOW THE HORIZON IS FOUND, and what it is not. Two cheap per-row statistics, taken on the
 * greyscale frame the detectors already have:
 *
 *  - TEXTURE. Foliage is busy; mown grass is not. A run of rows far busier than the ground
 *    below them is a treeline.
 *  - BRIGHTNESS. Sky is not busy at all, so texture alone would walk straight through it.
 *    But sky is much brighter than grass, and a run of rows far brighter than the ground is
 *    equally not ground.
 *
 * Either signal is enough. Neither is a horizon in the geometric sense — this is not a
 * vanishing line and must never be used as one. It is the lowest row that is clearly not
 * the surface the camera is pointed at, which is all that is needed to throw out a trunk.
 *
 * WHEN IT CANNOT TELL, IT SAYS SO. A uniform frame, a dark frame, or a shot where the
 * ground fills the picture all return null, and a null applies NO constraint. A guessed
 * horizon would reject the real stumps on exactly the footage this is meant to rescue,
 * which is worse than the trees it was added to remove.
 *
 * Plain Kotlin, no OpenCV, for the reason the rest of this package gives: the decision is
 * arithmetic and should be provable at a desk.
 */
object GroundHorizon {

    /** Share of the frame, measured up from the bottom, taken as a sample of the ground. */
    const val GROUND_SAMPLE = 0.25f

    /** How much busier than the ground a row must be to count as background. */
    const val TEXTURE_RISE = 2.0f

    /** How much brighter than the ground a row must be to count as background. */
    const val BRIGHTNESS_RISE = 1.35f

    /**
     * Rows in a row before a change is believed.
     *
     * One bright line is a crease, a boundary rope or a sightscreen edge. A treeline is
     * dozens of rows deep, and insisting on a run is what separates the two.
     */
    const val MIN_RUN = 4

    /**
     * Below this share of ground the answer is rejected as implausible.
     *
     * A horizon found in the bottom tenth of the frame would mean the camera is pointed at
     * the sky, which is not a shot anybody films cricket from — far likelier is that the
     * statistics were misread, and acting on it would throw out every real stump.
     */
    const val MIN_GROUND_SHARE = 0.20f

    /**
     * How far above the horizon a base may still sit and be believed.
     *
     * The estimate is a row index from coarse statistics, not a surveyed line, and a
     * wicket's feet can sit a little into the treeline behind it. Tight enough to still
     * exclude a trunk whose base is halfway up the picture.
     */
    const val TOLERANCE = 0.04f

    /**
     * The lowest row that is clearly not ground, normalised 0..1, or null.
     *
     * @param rowTexture mean absolute horizontal gradient per row, top to bottom.
     * @param rowBrightness mean luma per row, top to bottom. Same length as [rowTexture].
     */
    fun estimate(rowTexture: FloatArray, rowBrightness: FloatArray): Float? {
        val rows = rowTexture.size
        if (rows < MIN_ROWS || rowBrightness.size != rows) return null

        val sampleFrom = (rows * (1f - GROUND_SAMPLE)).toInt().coerceIn(0, rows - 1)
        val groundTexture = median(rowTexture, sampleFrom, rows)
        val groundBrightness = median(rowBrightness, sampleFrom, rows)

        // A frame with no texture and no light at the bottom is a black lead-in, not a
        // ground. Measuring a horizon against nothing produces nothing.
        if (groundTexture <= 0f && groundBrightness <= 0f) return null

        val textureLimit = groundTexture * TEXTURE_RISE
        val brightnessLimit = groundBrightness * BRIGHTNESS_RISE

        var run = 0
        for (y in rows - 1 downTo 0) {
            val busier = groundTexture > 0f && rowTexture[y] > textureLimit
            val brighter = groundBrightness > 0f && rowBrightness[y] > brightnessLimit

            if (busier || brighter) {
                run++
                if (run >= MIN_RUN) {
                    // The BOTTOM of the run is the boundary; the scan is standing at its top.
                    val horizon = (y + run - 1).toFloat() / rows
                    return if (horizon > 1f - MIN_GROUND_SHARE) null else horizon
                }
            } else {
                run = 0
            }
        }
        return null
    }

    /**
     * May a bar with its base here be standing on the ground?
     *
     * A null horizon means the frame gave no usable answer, and an unknown horizon
     * constrains nothing — the alternative is inventing a line and rejecting real stumps
     * with it.
     */
    fun isOnGround(baseY: Float, horizon: Float?): Boolean {
        if (horizon == null) return true
        return baseY >= horizon - TOLERANCE
    }

    /** Too few rows to have a run in, let alone a horizon. */
    const val MIN_ROWS = 16

    private fun median(values: FloatArray, from: Int, to: Int): Float {
        if (from >= to) return 0f
        val slice = values.copyOfRange(from, to)
        slice.sort()
        val middle = slice.size / 2
        return if (slice.size % 2 == 1) {
            slice[middle]
        } else {
            (slice[middle - 1] + slice[middle]) / 2f
        }
    }

    /** Mean absolute difference between neighbours in one row. Used by the OpenCV path. */
    fun rowTextureOf(row: FloatArray): Float {
        if (row.size < 2) return 0f
        var total = 0f
        for (i in 1 until row.size) total += abs(row[i] - row[i - 1])
        return total / (row.size - 1)
    }
}
