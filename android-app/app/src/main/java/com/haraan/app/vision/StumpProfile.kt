package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.ceil
import kotlin.math.floor
import kotlin.math.max
import kotlin.math.min
import kotlin.math.sqrt

/**
 * Three stumps found as ONE shape, at sub-pixel precision, in a full-resolution patch.
 *
 * WHAT WAS WRONG. The whole-frame detector looks for three SEPARATE bright contours and
 * asks whether they line up. That is a sound test at ten metres and a physical
 * impossibility at twenty-two, which is where the far wicket stands from behind the
 * bowler's arm. On a 70° phone camera at 1280 wide, a 35 mm stump at 22 m is 1.45 px
 * across with its neighbour 4 px away; scaled to 960 and blurred 3×3 the gaps are gone and
 * the wicket is one blob. "Only 1 tall thin bar in frame" was never a threshold to tune.
 *
 * WHAT THIS DOES INSTEAD. It models the wicket as what it is — three bars of a known
 * width-to-spacing ratio ([PitchGeometry.STUMP_DIAMETER_M] over half of
 * [PitchGeometry.STUMP_CENTRES_SPAN_M]) — and fits that model to a COLUMN PROFILE: the
 * brightness of each column averaged down the stumps' height. Averaging twenty-odd rows
 * cuts the noise by four or five times, and fitting a template with fractional pixel
 * coverage recovers bar positions to a tenth of a pixel even when no single row shows
 * three separate peaks.
 *
 * Normalised cross-correlation is the score, because it is blind to the two things that
 * change through a match — overall brightness and contrast — and keeps the one that does
 * not: the shape. Its window reaches past the outer stumps on purpose: a fence of evenly
 * spaced palings matches the comb in the middle and fails it at the flanks, where a real
 * wicket has open ground. A single pole fails it in the gaps.
 *
 * The sign of the correlation is the polarity. Pale stumps on grass and stumps silhouetted
 * against a low sun are the same shape with the brightness flipped; both are a wicket.
 *
 * ROLL, LEARNED FROM THE STUMPS. A camera that is not level leans every stump, and a
 * column profile of a leaning bar is smeared. The profile is taken along a sheared column
 * for a range of shears and the one that makes the wicket sharpest is kept. That shear is
 * the camera's roll, measured from the only object on the field known to stand vertical,
 * which is why no separate orientation calibration is needed for this to work.
 */
data class CombFit(
    /** Middle stump, ROI pixels, at the reference row [refY]. */
    val centreX: Double,
    /** Middle stump to an outer stump, ROI pixels. Half the centre-to-centre span. */
    val halfSpan: Double,
    /** x shift per row going DOWN. The camera's roll, as a slope. */
    val shear: Double,
    /** The row the shear pivots on, ROI pixels. */
    val refY: Double,
    /** Top and foot of the stumps, ROI pixels. */
    val topY: Double,
    val baseY: Double,
    /** Normalised cross-correlation with the three-bar template. -1..1; sign is polarity. */
    val ncc: Double,
    /** Bar brightness over gap brightness, grey levels, signed like [ncc]. */
    val contrast: Double,
    /** Contrast over the profile's own noise. */
    val snr: Double,
) {
    /** Pale stumps on a darker ground, as opposed to dark ones against a bright one. */
    val bright: Boolean get() = ncc > 0.0

    fun xAt(y: Double): Double = centreX + shear * (y - refY)

    /**
     * How much the fit is worth, 0..1. Correlation carries it; SNR only gates it, so a
     * high-contrast frame does not outrank an equally well-shaped dimmer one.
     */
    val quality: Float
        get() = (abs(ncc) * min(1.0, snr / StumpProfile.FULL_SNR)).toFloat().coerceIn(0f, 1f)
}

/** Where to look and roughly what for, in ROI pixels. */
data class CombProbe(
    /** The range the middle stump may sit in. */
    val centreMin: Double,
    val centreMax: Double,
    /** The range the half-span may take. */
    val halfSpanMin: Double,
    val halfSpanMax: Double,
    /** Expected top and foot of the stumps. Only roughly: the fit finds the real ones. */
    val topY: Double,
    val baseY: Double,
    /** Largest |shear| to try. 0.13 is about 7.5°. */
    val maxShear: Double = StumpProfile.DEFAULT_MAX_SHEAR,
)

object StumpProfile {

    /** Stump diameter over half the centre-to-centre span: a bar's width in comb units. */
    val BAR_WIDTH_RATIO: Double = PitchGeometry.STUMP_DIAMETER_M / (PitchGeometry.STUMP_CENTRES_SPAN_M / 2.0)

    /**
     * Narrowest a bar is drawn in the template, in pixels.
     *
     * The lens and the sensor's demosaic spread even an infinitely thin line over about a
     * pixel, so a template narrower than that would demand a sharpness no phone delivers.
     */
    const val MIN_BAR_PX = 1.1

    /** The template's reach either side of the middle stump, in half-spans. */
    const val WINDOW_HALF_SPANS = 2.3

    /** Below this |NCC| the shape is not three stumps. */
    const val MIN_NCC = 0.72

    /** Below this the "contrast" is the noise. */
    const val MIN_SNR = 5.0

    /** SNR at which a fit's quality stops growing. */
    const val FULL_SNR = 14.0

    /**
     * Below this many grey levels a bar is not a stump, whatever the arithmetic says.
     *
     * An ASSUMPTION about real grounds, not a measurement: pale stumps against grass, or
     * silhouetted against a bright background, differ by tens of grey levels; three faint
     * grass streaks differ by a handful, and at 3 they were found and locked. The admin
     * panel prints the measured contrast of every lock so this can be checked on a ground.
     */
    const val MIN_CONTRAST = 10.0

    /**
     * The smallest half-span the comb is asked to resolve: four pixels across the wicket,
     * which on a 70° lens at 1280 wide is 40 m — the far end of the range it is meant for.
     * Below it, grass texture makes three-peaked patterns as convincingly as stumps do.
     */
    const val MIN_HALF_SPAN_PX = 2.0

    /**
     * Correlation × saturating SNR, at least. Grass blades make three-peaked patterns at the
     * smallest span the comb accepts, with middling correlation and a few grey levels of
     * contrast: quality 0.3-0.5 on the synthetic field, and they reached READY. Real stumps,
     * near or far, in sun or under cloud, saturate the SNR term and score 0.8 and up.
     */
    const val MIN_QUALITY = 0.6

    /** Bar contrast over the ground's own column-to-column spread, at least. */
    const val MIN_CONTRAST_OVER_CLUTTER = 6.0

    /** One spacing beyond the outer stumps, brighter than this share of a bar: a fence. */
    const val MAX_FLANK_RATIO = 0.45

    /** Stump height over outer centre-to-centre span. The Laws give 3.7. */
    const val MIN_HEIGHT_TO_SPAN = 2.4
    const val MAX_HEIGHT_TO_SPAN = 4.7

    const val DEFAULT_MAX_SHEAR = 0.13

    /** Shears that get the full comb search, besides level - the sharpest ones. */
    private const val SHEARS_SEARCHED = 2
    private const val SHEAR_STEP = 0.0325

    /**
     * Fit three stumps inside [probe], or null when there are not three stumps there.
     */
    /**
     * Why the last [fit] said no, or "fit". For the diagnostics panel: a comb that only says
     * no is tunable by guesswork; one that names the rule is tunable on the ground.
     */
    @Volatile var lastVerdict: String = ""
        private set

    private fun reject(why: String): CombFit? {
        lastVerdict = why
        return null
    }

    fun fit(roi: UprightRoi, probe: CombProbe): CombFit? {
        val gMin = max(probe.halfSpanMin, MIN_HALF_SPAN_PX)
        val gMax = probe.halfSpanMax
        if (gMax < gMin) return null

        // The stumps' body: below the bails, above the shadow and the crease paint at the
        // foot. Both ends are where the wicket stops looking like three bars.
        val len = probe.baseY - probe.topY
        if (len < 3.0) return null
        val r0 = floor(probe.topY + 0.15 * len).toInt().coerceIn(0, roi.height - 1)
        val r1 = ceil(probe.baseY - 0.12 * len).toInt().coerceIn(r0 + 1, roi.height)
        if (r1 - r0 < 3) return null
        val refY = (r0 + r1) / 2.0

        // --- pass 1: which shear, which comb, coarse --------------------------------------
        /*
         * SHEAR BY SHARPNESS FIRST, COMB SECOND.
         *
         * Searching every spacing and position at all nine shears was the phone's whole
         * budget: on a Realme RMX3933 the search ran at 350-550 ms a frame, under two frames a
         * second, and the tracker never saw two sightings close enough to confirm. A column
         * profile taken at the camera's true roll is the SHARPEST one - vertical bars stay
         * vertical - and sharpness costs one pass over the window. So every shear is scored
         * that way and only the sharpest two, and level, get the full comb search.
         */
        var best: Coarse? = null
        val shears = shearsUpTo(probe.maxShear)
        val profile = DoubleArray(roi.width)
        val lo = floor(probe.centreMin - WINDOW_HALF_SPANS * gMax).toInt().coerceIn(0, roi.width - 1)
        val hi = ceil(probe.centreMax + WINDOW_HALF_SPANS * gMax).toInt().coerceIn(0, roi.width - 1)
        val sharpness = DoubleArray(shears.size)
        for (i in shears.indices) {
            columnProfile(roi, r0, r1, refY, shears[i], profile)
            var e = 0.0
            for (x in lo until hi) {
                val d = profile[x + 1] - profile[x]
                e += d * d
            }
            sharpness[i] = e
        }
        val chosen = shears.indices.sortedByDescending { sharpness[it] }.take(SHEARS_SEARCHED).toMutableSet()
        chosen.add(shears.size / 2)
        for (i in chosen) {
            val k = shears[i]
            columnProfile(roi, r0, r1, refY, k, profile)
            val c = coarseSearch(profile, probe.centreMin, probe.centreMax, gMin, gMax) ?: continue
            if (best == null || abs(c.ncc) > abs(best.ncc)) best = Coarse(k, c.centre, c.halfSpan, c.ncc)
        }
        val coarse = best ?: return reject("no comb in range")
        if (abs(coarse.ncc) < MIN_NCC * 0.85) return reject("shape ncc %.2f".format(coarse.ncc))

        // --- pass 2: refine all three together --------------------------------------------
        var k = coarse.shear
        var c = coarse.centre
        var g = coarse.halfSpan
        var ncc = coarse.ncc
        columnProfile(roi, r0, r1, refY, k, profile)
        val other = DoubleArray(roi.width)
        repeat(3) {
            var dc = 0.25
            while (dc >= 0.03) {
                for (cand in doubleArrayOf(c - dc, c + dc)) {
                    val v = ncc(profile, cand, g)
                    if (abs(v) > abs(ncc)) { ncc = v; c = cand }
                }
                dc /= 2
            }
            var dg = g * 0.04
            while (dg >= g * 0.004) {
                for (cand in doubleArrayOf(g - dg, g + dg)) {
                    // Never below the floor: refinement nudging a 2 px comb to 1.9 px is how
                    // grass got through as a 3.8 px "wicket".
                    if (cand < max(gMin * 0.95, MIN_HALF_SPAN_PX) || cand > gMax * 1.05) continue
                    val v = ncc(profile, c, cand)
                    if (abs(v) > abs(ncc)) { ncc = v; g = cand }
                }
                dg /= 2
            }
            for (cand in doubleArrayOf(k - SHEAR_STEP / 3, k + SHEAR_STEP / 3)) {
                if (abs(cand) > probe.maxShear + 1e-9) continue
                columnProfile(roi, r0, r1, refY, cand, other)
                val v = ncc(other, c, g)
                if (abs(v) > abs(ncc)) { ncc = v; k = cand; other.copyInto(profile) }
            }
        }
        if (abs(ncc) < MIN_NCC) return reject("shape ncc %.2f".format(ncc))

        // --- how strong, against how noisy -------------------------------------------------
        val amp = amplitude(profile, c, g)
        val noise = profileNoise(roi, r0, r1, refY, k, c, g)
        val snr = abs(amp) / max(noise, 0.15)
        if (abs(amp) < MIN_CONTRAST || snr < MIN_SNR) return reject("faint: contrast %.1f snr %.1f".format(amp, snr))
        // A contrast whose sign disagrees with the correlation is a fit that latched onto
        // the gaps rather than the bars.
        if (amp * ncc <= 0.0) return reject("fitted the gaps")

        /*
         * --- open ground beside the wicket --------------------------------------------------
         *
         * Correlation alone CANNOT tell three stumps from three palings of a fence: three
         * bars inside a run of five correlate at sqrt(3/5) = 0.77 with the template, above any
         * sensible threshold. What a wicket has and a fence does not is NOTHING one spacing
         * further out. So the profile is read where a fourth and fifth post would stand, and
         * if either is nearly as bright as a bar, this is a fence, a gate, or a net.
         */
        val flank = flankRatio(profile, c, g, amp)
        if (flank.isInfinite()) return reject("at the edge: open ground beside it unverifiable")
        if (flank > MAX_FLANK_RATIO) return reject("fence: flank %.2f".format(flank))

        /*
         * --- standing out from the ground ---------------------------------------------------
         *
         * Static grass streaks a couple of pixels apart are, to a correlation, a tiny wicket:
         * the right pattern, dark flanks, steady from frame to frame — and on the synthetic
         * ground that models them they reached READY. Their contrast is the same few grey
         * levels as every other streak in the patch. A wicket's is many times the ground's
         * own column-to-column variation, whatever the light, so that is the test: contrast
         * against the profile OUTSIDE the wicket, not against sensor noise.
         */
        val clutter = backgroundSpread(profile, c, g)
        if (clutter != null && abs(amp) < MIN_CONTRAST_OVER_CLUTTER * clutter) {
            return reject("clutter: contrast %.1f vs ground %.1f".format(amp, clutter))
        }

        // --- how tall: walk out from the body while each row still shows the comb ----------
        val (top, base) = verticalExtent(roi, c, g, k, refY, r0, r1, amp)

        /*
         * --- the proportions of a wicket ----------------------------------------------------
         *
         * 0.711 m tall over 0.1936 m between the outer centres is 3.7. A comb that fitted a
         * spacing half the real one — two stumps' edges read as three bars, or grass streaks
         * a few pixels apart — has the right correlation and twice the ratio, and would hand
         * every distance downstream a scale out by two. The band is wide for a camera looking
         * down (which shortens the height) and for bails or a crease line adding a row; it is
         * not wide enough for a factor of two either way.
         */
        val ratio = (base - top) / (2.0 * g)
        if (ratio < MIN_HEIGHT_TO_SPAN || ratio > MAX_HEIGHT_TO_SPAN) {
            return reject("proportions: height/span %.1f".format(ratio))
        }
        if ((abs(ncc) * min(1.0, snr / FULL_SNR)) < MIN_QUALITY) {
            return reject("weak: quality %.2f".format(abs(ncc) * min(1.0, snr / FULL_SNR)))
        }
        lastVerdict = "fit"

        return CombFit(
            centreX = c,
            halfSpan = g,
            shear = k,
            refY = refY,
            topY = top,
            baseY = base,
            ncc = ncc,
            contrast = amp,
            snr = snr,
        )
    }

    private data class Coarse(val shear: Double, val centre: Double, val halfSpan: Double, val ncc: Double)
    private data class Hit(val centre: Double, val halfSpan: Double, val ncc: Double)

    private fun shearsUpTo(maxShear: Double): DoubleArray {
        val n = floor(maxShear / SHEAR_STEP).toInt()
        return DoubleArray(2 * n + 1) { (it - n) * SHEAR_STEP }
    }

    /** Mean brightness of each sheared column over rows [r0, r1). */
    internal fun columnProfile(roi: UprightRoi, r0: Int, r1: Int, refY: Double, shear: Double, out: DoubleArray) {
        val rows = r1 - r0
        for (x in 0 until roi.width) {
            var sum = 0.0
            for (y in r0 until r1) {
                sum += roi.sampleX(x + shear * (y + 0.5 - refY), y)
            }
            out[x] = sum / rows
        }
    }

    private fun coarseSearch(profile: DoubleArray, cMin: Double, cMax: Double, gMin: Double, gMax: Double): Hit? {
        var best: Hit? = null
        var g = gMin
        while (g <= gMax * 1.0001) {
            // Half a pixel for a narrow comb, a whole one for a wide one: refinement then
            // takes the centre to a few hundredths either way.
            val cStep = if (g < 4.0) 0.5 else 1.0
            var c = cMin
            while (c <= cMax) {
                val v = ncc(profile, c, g)
                if (best == null || abs(v) > abs(best.ncc)) best = Hit(c, g, v)
                c += cStep
            }
            g *= 1.06
        }
        return best
    }

    /**
     * How much of profile pixel [x] (covering [x, x+1)) the three bars cover.
     */
    internal fun template(x: Int, c: Double, g: Double): Double =
        coverage(x, c, g, max(BAR_WIDTH_RATIO * g, MIN_BAR_PX) / 2.0)

    /**
     * [template] with the bar half-width worked out once - the hot path, evaluated millions
     * of times a frame. No arrays: the old loop over doubleArrayOf(...) allocated on every
     * pixel, which a desktop JIT optimises away and a phone does not.
     */
    private fun coverage(x: Int, c: Double, g: Double, half: Double): Double {
        val lo = x.toDouble()
        val hi = x + 1.0
        var cover = 0.0
        var a = max(lo, c - g - half)
        var b = min(hi, c - g + half)
        if (b > a) cover += b - a
        a = max(lo, c - half)
        b = min(hi, c + half)
        if (b > a) cover += b - a
        a = max(lo, c + g - half)
        b = min(hi, c + g + half)
        if (b > a) cover += b - a
        return min(cover, 1.0)
    }

    private fun windowOf(profileSize: Int, c: Double, g: Double): IntRange {
        val reach = WINDOW_HALF_SPANS * g
        val lo = floor(c - reach).toInt().coerceAtLeast(0)
        val hi = ceil(c + reach).toInt().coerceAtMost(profileSize - 1)
        return lo..hi
    }

    internal fun ncc(profile: DoubleArray, c: Double, g: Double): Double {
        val window = windowOf(profile.size, c, g)
        val n = window.last - window.first + 1
        // A window clipped hard by the ROI edge has lost the flanks the fence test needs.
        if (n < max(5.0, 2.0 * WINDOW_HALF_SPANS * g * 0.8)) return 0.0
        // One pass: the template is evaluated once per pixel, not twice.
        val half = max(BAR_WIDTH_RATIO * g, MIN_BAR_PX) / 2.0
        var sp = 0.0; var st = 0.0; var spt = 0.0; var spp = 0.0; var stt = 0.0
        for (x in window) {
            val p = profile[x]
            val t = coverage(x, c, g, half)
            sp += p; st += t; spt += p * t; spp += p * p; stt += t * t
        }
        val cov = n * spt - sp * st
        val vp = n * spp - sp * sp
        val vt = n * stt - st * st
        if (vp <= 1e-9 || vt <= 1e-9) return 0.0
        return cov / sqrt(vp * vt)
    }

    /**
     * How bright the profile is one spacing beyond each outer stump, as a share of the bars'
     * own contrast — the larger of the two sides. About 0 on open ground; about 1 for a fence.
     */
    internal fun flankRatio(profile: DoubleArray, c: Double, g: Double, amp: Double): Double {
        if (abs(amp) < 1e-9) return 1.0
        val half = max(BAR_WIDTH_RATIO * g, MIN_BAR_PX) / 2.0
        fun meanOver(lo: Double, hi: Double): Double? {
            val a = floor(lo).toInt()
            val b = ceil(hi).toInt() - 1
            if (a < 0 || b >= profile.size || b < a) return null
            var sum = 0.0
            for (x in a..b) sum += profile[x]
            return sum / (b - a + 1)
        }
        // The ground level: the two gaps between the stumps.
        // Unmeasurable is NOT "open ground": at the edge of the picture a clutter fit used to
        // pass this test by default, and on a real phone held a false lock at the frame's edge.
        val gapL = meanOver(c - g / 2 - half / 2, c - g / 2 + half / 2) ?: return Double.POSITIVE_INFINITY
        val gapR = meanOver(c + g / 2 - half / 2, c + g / 2 + half / 2) ?: return Double.POSITIVE_INFINITY
        val ground = (gapL + gapR) / 2
        var worst = 0.0
        for (side in doubleArrayOf(-2.0, 2.0)) {
            val at = meanOver(c + side * g - half, c + side * g + half) ?: return Double.POSITIVE_INFINITY
            worst = max(worst, (at - ground) / amp)
        }
        return worst
    }

    /**
     * Robust spread (MAD scaled to a sigma) of the profile outside the wicket's window, or
     * null when the patch has too little outside it to say.
     */
    internal fun backgroundSpread(profile: DoubleArray, c: Double, g: Double): Double? {
        val reach = WINDOW_HALF_SPANS * g + 1.0
        var n = 0
        for (x in profile.indices) if (x + 1 < c - reach || x > c + reach) n++
        if (n < 6) return null
        val outside = DoubleArray(n)
        var i = 0
        for (x in profile.indices) if (x + 1 < c - reach || x > c + reach) outside[i++] = profile[x]
        outside.sort()
        val median = outside[n / 2]
        for (k in 0 until n) outside[k] = abs(outside[k] - median)
        outside.sort()
        return max(outside[n / 2] * 1.4826, 0.5)
    }

    /** Regression slope of profile on template: full-coverage bar minus ground, grey levels. */
    private fun amplitude(profile: DoubleArray, c: Double, g: Double): Double {
        val window = windowOf(profile.size, c, g)
        val n = window.last - window.first + 1
        var sp = 0.0; var st = 0.0
        for (x in window) { sp += profile[x]; st += template(x, c, g) }
        val mp = sp / n; val mt = st / n
        var num = 0.0; var dt = 0.0
        for (x in window) {
            val t = template(x, c, g) - mt
            num += (profile[x] - mp) * t; dt += t * t
        }
        return if (dt <= 1e-9) 0.0 else num / dt
    }

    /**
     * Noise of the profile itself: the row-to-row scatter of single pixels inside the
     * window, by median absolute difference (robust to the bars' own edges), shrunk by the
     * number of rows averaged.
     */
    private fun profileNoise(roi: UprightRoi, r0: Int, r1: Int, refY: Double, k: Double, c: Double, g: Double): Double {
        val window = windowOf(roi.width, c, g)
        val count = (r1 - r0 - 1).coerceAtLeast(0) * (window.last - window.first + 1)
        if (count <= 0) return 1.0
        val diffs = DoubleArray(count)
        var i = 0
        for (y in r0 until r1 - 1) {
            for (x in window) {
                val a = roi.sampleX(x + k * (y + 0.5 - refY), y)
                val b = roi.sampleX(x + k * (y + 1.5 - refY), y + 1)
                diffs[i++] = abs((a - b).toDouble())
            }
        }
        diffs.sort()
        val mad = diffs[count / 2]
        // MAD of a difference of two samples → sigma of one, then of an average of rows.
        val sigma = mad * 1.4826 / sqrt(2.0)
        return sigma / sqrt((r1 - r0).toDouble())
    }

    /**
     * The rows where the comb is still there, walking out from the body both ways.
     *
     * Row by row, the same regression slope as [amplitude]. A stump row carries most of the
     * body's contrast; the bails, the ground and the batter's pads do not carry it in this
     * pattern. Two weak rows in a row end the walk, so one bad row of noise does not.
     */
    private fun verticalExtent(
        roi: UprightRoi, c: Double, g: Double, k: Double, refY: Double, r0: Int, r1: Int, amp: Double,
    ): Pair<Double, Double> {
        // Far enough to see a grass streak or a pole run on well past where stumps stop —
        // which is what then fails the proportions — not merely to the probe's own guess.
        val reach = 2 * (r1 - r0) + (6 * g).toInt()
        val lo = (r0 - reach).coerceAtLeast(0)
        val hi = (r1 + reach).coerceAtMost(roi.height)
        val window = windowOf(roi.width, c, g)
        var mt = 0.0
        for (x in window) mt += template(x, c, g)
        mt /= (window.last - window.first + 1)
        var dt = 0.0
        for (x in window) { val t = template(x, c, g) - mt; dt += t * t }
        if (dt <= 1e-9) return r0.toDouble() to r1.toDouble()

        val slopes = DoubleArray(hi - lo)
        for (y in lo until hi) {
            var mp = 0.0
            for (x in window) mp += roi.sampleX(x + k * (y + 0.5 - refY), y)
            mp /= (window.last - window.first + 1)
            var num = 0.0
            for (x in window) {
                num += (roi.sampleX(x + k * (y + 0.5 - refY), y) - mp) * (template(x, c, g) - mt)
            }
            slopes[y - lo] = num / dt
        }
        val need = 0.4 * abs(amp)
        fun strong(y: Int) = slopes[y - lo] * amp > 0 && abs(slopes[y - lo]) >= need

        var top = r0
        var weak = 0
        var y = r0 - 1
        while (y >= lo) {
            if (strong(y)) { top = y; weak = 0 } else if (++weak >= 2) break
            y--
        }
        var base = r1 - 1
        weak = 0
        y = r1
        while (y < hi) {
            if (strong(y)) { base = y; weak = 0 } else if (++weak >= 2) break
            y++
        }
        return top.toDouble() to (base + 1).toDouble()
    }

    // ---- the detector's two uses: refine near a known place, verify a coarse blob ---------

    /**
     * Look for three stumps around a normalised place in the upright frame, at full sensor
     * resolution. Returns the wicket as a [StumpSet] in normalised coordinates, or null.
     *
     * @param centreX middle stump, normalised
     * @param baseY foot of the stumps, normalised
     * @param topY top of the stumps, normalised; the fit finds the real one
     * @param halfSpanFw expected middle-to-outer stump, frame widths
     * @param tolerance how far the half-span may differ from expected, as a fraction
     * @param searchHalfSpans how far either side of [centreX] the middle may be, in half-spans
     */
    fun locate(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        centreX: Double,
        baseY: Double,
        topY: Double,
        halfSpanFw: Double,
        tolerance: Double = 0.3,
        searchHalfSpans: Double = 1.5,
        maxShear: Double = DEFAULT_MAX_SHEAR,
    ): Pair<StumpSet, CombFit>? {
        if (halfSpanFw <= 0.0 || baseY <= topY) return null
        val turn = ((rotationDegrees % 360) + 360) % 360
        val upW = if (turn == 90 || turn == 270) height else width
        val upH = if (turn == 90 || turn == 270) width else height

        val gPx = halfSpanFw * upW
        val hPx = (baseY - topY) * upH
        val reachPx = gPx * (searchHalfSpans + WINDOW_HALF_SPANS + 0.6) + hPx * maxShear * 0.6 + 3.0
        val left = centreX - reachPx / upW
        val right = centreX + reachPx / upW
        val top = topY - 0.35 * (baseY - topY)
        val bottom = baseY + 0.3 * (baseY - topY)

        // Decimate only while the comb keeps eight pixels per half-span: plenty for a near
        // wicket, and a far one is never decimated at all.
        val maxSide = max(96, ceil(max((right - left) * upW, (bottom - top) * upH) * 8.0 / max(gPx, 1.0)).toInt())
            .coerceAtMost(256)
        val roi = UprightRoi.extract(luma, width, height, rowStride, rotationDegrees, left, top, right, bottom, maxSide)
            ?: return null

        val cRoi = roi.fromFrameX(centreX)
        val gRoi = halfSpanFw * upW / roi.step
        val probe = CombProbe(
            centreMin = cRoi - searchHalfSpans * gRoi,
            centreMax = cRoi + searchHalfSpans * gRoi,
            halfSpanMin = gRoi * (1.0 - tolerance),
            halfSpanMax = gRoi * (1.0 + tolerance),
            topY = roi.fromFrameY(topY),
            baseY = roi.fromFrameY(baseY),
            maxShear = maxShear,
        )
        val fit = fit(roi, probe) ?: return null
        return toStumpSet(fit, roi) to fit
    }

    /**
     * Look for the stumps where the tracker holds, remembers or suspects them.
     *
     * The anchor's two feet give the centre and the half-span; its top, when it has one,
     * the height — otherwise the Laws' proportion of height to span, which a camera looking
     * down only shortens, and the fit finds the real top either way.
     */
    fun locateAt(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        focus: WicketFocus,
    ): Pair<StumpSet, CombFit>? {
        if (focus.kind != WicketKind.STUMPS) return null
        val a = focus.anchor
        val base = a.base
        val halfSpan = a.span(focus.aspect) / 2.0
        if (halfSpan <= 0.0) return null
        val rise = a.top?.let { (base.y - it.y).coerceAtLeast(0.0) }
            ?: (halfSpan * 2.0 * (PitchGeometry.STUMP_HEIGHT_M / PitchGeometry.STUMP_CENTRES_SPAN_M) * focus.aspect)
        if (rise <= 0.0) return null
        return locate(
            luma, width, height, rowStride, rotationDegrees,
            centreX = base.x,
            baseY = base.y,
            topY = base.y - rise,
            halfSpanFw = halfSpan,
            tolerance = focus.spanTolerance,
            searchHalfSpans = focus.searchHalfSpans,
        )
    }

    /** A fit, as the same three bars the contour search would have handed over. */
    fun toStumpSet(fit: CombFit, roi: UprightRoi): StumpSet {
        val topNorm = roi.toFrameY(fit.topY).toFloat()
        val baseNorm = roi.toFrameY(fit.baseY).toFloat()
        // Each bar's x at its FOOT: the foot is what an anchor is made of, and on a rolled
        // camera the foot and the head are not above each other in the picture.
        val footX = fit.xAt(fit.baseY)
        fun bar(offset: Double) = StumpCandidate(
            centreX = roi.toFrameX(footX + offset).toFloat(),
            baseY = baseNorm,
            topY = topNorm,
        )
        return StumpSet(
            left = bar(-fit.halfSpan),
            middle = bar(0.0),
            right = bar(fit.halfSpan),
            score = fit.quality,
            // x falling going down is a clockwise roll: the tops lean right.
            rollDeg = (-Math.toDegrees(kotlin.math.atan(fit.shear))).toFloat(),
            subPixel = true,
        )
    }
}
