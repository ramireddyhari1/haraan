package com.haraan.app.vision

import android.util.Log
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.MatOfPoint
import org.opencv.core.Rect
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc
import kotlin.math.abs
import kotlin.math.max

/** Where the stump search got to, so a null can be explained rather than shrugged at. */
data class StumpDetectorReport(
    val framesSeen: Int,
    /** Tall thin bright boxes this frame offered, before the wicket test. */
    val barsFound: Int,
    val setsFound: Int,
    val averageProcessingMs: Double,
    val lastRejection: String?,
    /**
     * Where the ground was judged to start, normalised, or null when the frame could not
     * say. Null means no ground constraint was applied at all, which is worth knowing
     * before reading anything into a detection.
     */
    val horizonY: Float? = null,
    /** Bars thrown out for standing above the horizon. */
    val barsAboveHorizon: Int = 0,
    /** Compact solid lumps on the ground this frame offered, before the stone test. */
    val stonesFound: Int = 0,
    /**
     * How many of those were even the right shape to be a wicket.
     *
     * Reported beside the raw count because the two answer different questions, and the
     * first field test could not tell them apart: 478 lumps sounds like a detector running
     * wild, and the number that decides anything is how many of them got as far as being
     * scored.
     */
    val stonesPlausible: Int = 0,
    /** Frames that ended in a stone rather than a set of bars. */
    val stoneMarksFound: Int = 0,
    /**
     * Whether the last stone came from the dark pass.
     *
     * Worth reporting because it says which way round the contrast was: a pale stone on
     * dark earth and a dark stone on a concrete strip are found by opposite tests, and
     * knowing which one fired tells you what the camera is actually looking at.
     */
    val stoneWasDark: Boolean = false,
    /** What the last frame did: "focus" (full-res patch only), "search" (whole frame). */
    val mode: String = "search",
    /** Last frame's own cost, ms. The average hides the difference between the modes. */
    val lastProcessingMs: Long = 0L,
    /** Candidates handed to the comb fit last frame, and how many it confirmed. */
    val probes: Int = 0,
    val combFits: Int = 0,
    /** Last comb fit's correlation and signal-to-noise, or null when none fitted. */
    val lastNcc: Float? = null,
    val lastSnr: Float? = null,
    /** The threshold offset the frame's own noise chose. See [OpenCvStumpDetector.noiseOffset]. */
    val adaptiveC: Float = 0f,
    /** Whether the last stumps were dark against a bright ground. */
    val stumpsWereDark: Boolean = false,
    /** Focus frames that found the wicket, of focus frames run. The lock's hit rate. */
    val focusHits: Int = 0,
    val focusRuns: Int = 0,
    /** The comb's last word: "fit", or the rule that refused — "fence: flank 0.8". */
    val combVerdict: String = "",
)

/**
 * Finds the wicket.
 *
 * WHY, when a crease detector already exists. Because the crease detector keeps failing on
 * the half of its job that depends on faint paint. A wicket is never absent, never worn off,
 * and exactly 0.2286 m across. It is the most reliable landmark on the field.
 *
 * WHAT IT DELIBERATELY DOES NOT DO. It does not produce a [PitchQuad] and it does not feed
 * calibration: three stump bases are collinear, and collinear points cannot define a plane
 * map. See [StumpGeometry].
 *
 * TWO MODES, AND WHY THE SECOND EXISTS.
 *
 *   SEARCH, over the whole frame scaled to 960 wide. Contours of bright vertical bars, as
 *   before — but now as a source of CANDIDATES rather than answers. A triple of separate
 *   bars is a candidate; so is a single blob the shape of a whole wicket, which is what a
 *   far wicket looks like once three 1.5-pixel stumps have been scaled and blurred into
 *   each other. Every candidate is then checked by [StumpProfile] on the FULL-resolution
 *   sensor pixels around it, where the three stumps are still three.
 *
 *   FOCUS, on a small full-resolution patch where the tracker says the wicket is (or was).
 *   No scaling, no blur, no contour sweep: a comb fit on a few thousand pixels. It is what
 *   keeps a far lock alive, and it costs a fraction of a search, which is what lets a lock
 *   be held for a three-hour match without the phone cooking.
 *
 * The bar pass runs in both polarities — stumps pale on grass, and stumps dark against a
 * low sun or a sightscreen — and its threshold offset is set from the frame's own noise, so
 * a grainy dusk frame does not turn every blade of grass into a bar and a flat overcast one
 * does not lose a faint stump.
 */
class OpenCvStumpDetector(
    private val analysisWidth: Int = 960,
) {
    val available: Boolean = OpenCvBallTracker.ensureLoaded()

    private var framesSeen = 0
    private var barsFound = 0
    private var setsFound = 0
    private var horizonY: Float? = null
    private var barsAboveHorizon = 0
    private var stonesFound = 0
    private var stonesPlausible = 0
    private var stoneMarksFound = 0
    private var stoneWasDark = false
    private var totalProcessingMs = 0L
    private var lastProcessingMs = 0L
    private var lastRejection: String? = "nothing analysed yet"
    private var released = false
    private var mode = "search"
    private var probes = 0
    private var combFits = 0
    private var lastNcc: Float? = null
    private var lastSnr: Float? = null
    private var adaptiveC = STUMP_ADAPTIVE_C.toFloat()
    private var stumpsWereDark = false
    private var focusHits = 0
    private var focusRuns = 0
    /** Search frames since the last stumps, to alternate in the dark-bar pass. */
    private var searchesWithoutStumps = 0

    /**
     * Comb fits allowed this search. A COLD start — nothing held, nothing remembered — gets
     * more: it is the moment the operator is watching the screen, and a second spent on a
     * few more fits is worth more than the CPU. Once anything is held, the budget drops.
     */
    private var probeBudget = MAX_PROBES
    private var blobBudget = MAX_BLOB_PROBES

    private var scratchPacked: ByteArray? = null
    private var scratchFull: Mat? = null

    fun report() = StumpDetectorReport(
        framesSeen = framesSeen,
        barsFound = barsFound,
        setsFound = setsFound,
        averageProcessingMs = if (framesSeen == 0) 0.0 else totalProcessingMs.toDouble() / framesSeen,
        lastRejection = lastRejection,
        horizonY = horizonY,
        barsAboveHorizon = barsAboveHorizon,
        stonesFound = stonesFound,
        stonesPlausible = stonesPlausible,
        stoneMarksFound = stoneMarksFound,
        stoneWasDark = stoneWasDark,
        mode = mode,
        lastProcessingMs = lastProcessingMs,
        probes = probes,
        combFits = combFits,
        lastNcc = lastNcc,
        lastSnr = lastSnr,
        adaptiveC = adaptiveC,
        stumpsWereDark = stumpsWereDark,
        focusHits = focusHits,
        focusRuns = focusRuns,
        combVerdict = StumpProfile.lastVerdict,
    )

    /**
     * The wicket in one frame, or null.
     *
     * Same frame contract as every other engine here: luma bytes, the stride they are
     * really laid out at, and the turn that makes them upright.
     */
    fun detect(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment> = emptyList(),
    ): StumpSet? = (detectWicket(luma, width, height, rowStride, rotationDegrees, creases)
        as? WicketSighting.Stumps)?.set

    /** The best wicket in one frame, of either kind, or null. See [detectCandidates]. */
    fun detectWicket(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment> = emptyList(),
        lookForStones: Boolean = true,
        focus: WicketFocus? = null,
    ): WicketSighting? = detectCandidates(
        luma, width, height, rowStride, rotationDegrees, creases, lookForStones, focus,
    ).firstOrNull()

    /**
     * Every wicket in one frame, best first — empty when there is none.
     *
     * THE ORDER IS THE POINT. Three bars are looked for first and win whenever they are
     * there, because a set of stumps is both harder to fake and the only one of the two
     * kinds that carries a scale. The stone pass runs only when that fails.
     *
     * @param lookForStones false to look for three bars only. The camera screen passes
     *   false while it holds a stumps lock: the stone passes are most of a search's cost,
     *   and a lump found on a frame where the bars flickered out is not the wicket.
     * @param focus where the tracker holds, remembers or suspects the wicket. Looked at
     *   first, at full resolution; when the wicket is there, nothing else is done.
     * @param allowSearch false to look only at [focus]. The camera screen holds a steady
     *   lock this way, and runs a whole-frame search only when the focus comes up empty.
     */
    fun detectCandidates(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment> = emptyList(),
        lookForStones: Boolean = true,
        focus: WicketFocus? = null,
        allowSearch: Boolean = true,
    ): List<WicketSighting> {
        if (!available || released || width <= 0 || height <= 0) return emptyList()

        framesSeen++
        val startedAt = System.nanoTime()
        return try {
            probes = 0
            combFits = 0
            if (focus != null && focus.kind == WicketKind.STUMPS) {
                mode = "focus"
                focusRuns++
                val near = lookAround(luma, width, height, rowStride, rotationDegrees, focus, creases)
                if (near != null) {
                    focusHits++
                    setsFound++
                    lastRejection = null
                    return listOf(WicketSighting.Stumps(near))
                }
                if (!allowSearch) {
                    lastRejection = "not at the held place this frame"
                    return emptyList()
                }
            }
            mode = "search"
            val cold = focus == null
            probeBudget = if (cold) COLD_MAX_PROBES else MAX_PROBES
            blobBudget = if (cold) COLD_MAX_BLOB_PROBES else MAX_BLOB_PROBES
            findWicket(luma, width, height, rowStride, rotationDegrees, creases, lookForStones)
        } catch (t: Throwable) {
            Log.w(TAG, "stump detection failed", t)
            lastRejection = "OpenCV threw: ${t.javaClass.simpleName}"
            emptyList()
        } finally {
            lastProcessingMs = (System.nanoTime() - startedAt) / 1_000_000
            totalProcessingMs += lastProcessingMs
        }
    }

    /** The comb fit around a held place. Pure Kotlin on a full-resolution patch. */
    private fun lookAround(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        focus: WicketFocus,
        creases: List<CreaseSegment>,
    ): StumpSet? {
        probes++
        val (set, fit) = StumpProfile.locateAt(luma, width, height, rowStride, rotationDegrees, focus) ?: return null
        noteFit(fit)
        return StumpGeometry.withCreases(set.copy(method = StumpMethod.FOCUS_COMB), creases, focus.aspect)
    }

    private fun noteFit(fit: CombFit) {
        combFits++
        lastNcc = fit.ncc.toFloat()
        lastSnr = fit.snr.toFloat()
        stumpsWereDark = !fit.bright
    }

    private fun findWicket(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        creases: List<CreaseSegment>,
        lookForStones: Boolean,
    ): List<WicketSighting> {
        barsFound = 0
        barsAboveHorizon = 0
        stonesFound = 0
        stonesPlausible = 0
        stoneWasDark = false

        val packed = if (rowStride == width) {
            luma
        } else {
            val out = scratchPacked?.takeIf { it.size == width * height }
                ?: ByteArray(width * height).also { scratchPacked = it }
            for (row in 0 until height) {
                val from = row * rowStride
                if (from + width > luma.size) {
                    // Into a reused buffer the missing rows are last frame's, and the seam
                    // between them is a long straight bright edge — exactly what this hunts.
                    lastRejection = "short luma buffer"
                    return emptyList()
                }
                System.arraycopy(luma, from, out, row * width, width)
            }
            out
        }

        val full = scratchFull?.takeIf { it.rows() == height && it.cols() == width }
            ?: Mat(height, width, CvType.CV_8UC1).also {
                scratchFull?.release()
                scratchFull = it
            }
        full.put(0, 0, packed)

        val scale = analysisWidth.toDouble() / width
        val small = Mat()
        if (scale < 1.0) {
            Imgproc.resize(full, small, Size(analysisWidth.toDouble(), height * scale), 0.0, 0.0, Imgproc.INTER_AREA)
        } else {
            full.copyTo(small)
        }

        val upright = when (((rotationDegrees % 360) + 360) % 360) {
            90 -> Mat().also { Core.rotate(small, it, Core.ROTATE_90_CLOCKWISE); small.release() }
            180 -> Mat().also { Core.rotate(small, it, Core.ROTATE_180); small.release() }
            270 -> Mat().also { Core.rotate(small, it, Core.ROTATE_90_COUNTERCLOCKWISE); small.release() }
            else -> small
        }

        val frameW = upright.cols()
        val frameH = upright.rows()
        val frameAspect = frameW.toFloat() / frameH.coerceAtLeast(1)

        // The horizon comes off the GREYSCALE frame, before any threshold. A binarised
        // picture has lost exactly the texture and brightness the estimate reads.
        horizonY = estimateHorizon(upright, frameH)

        val blurred = Mat()
        Imgproc.GaussianBlur(upright, blurred, Size(3.0, 3.0), 0.0)

        /*
         * THE THRESHOLD OFFSET COMES FROM THE FRAME.
         *
         * A fixed -3.5 grey levels was right for one light. At dusk the sensor's gain goes
         * up and the noise with it, and -3.5 turns grass into a field of bars; under a flat
         * overcast sky the stumps themselves are a few levels above the ground and -3.5
         * loses them. The mean absolute difference between the frame and its own blur is
         * the noise plus the fine texture — exactly the clutter the threshold must clear.
         */
        adaptiveC = noiseOffset(upright, blurred).toFloat()
        upright.release()

        /*
         * ADAPTIVE, NEVER OTSU — and stumps are BRIGHTER THAN WHAT IS DIRECTLY BEHIND THEM,
         * which is a local claim, so it is tested locally.
         */
        val bright = Mat()
        Imgproc.adaptiveThreshold(
            blurred, bright, 255.0,
            Imgproc.ADAPTIVE_THRESH_GAUSSIAN_C, Imgproc.THRESH_BINARY,
            PitchThreshold.ADAPTIVE_BLOCK, adaptiveC.toDouble(),
        )

        loadIntegral(blurred)
        var pass = barPass(bright, +1, frameW, frameH, frameAspect, creases, luma, width, height, rowStride, rotationDegrees)
        var dark = false

        /*
         * THE OTHER POLARITY. Stumps against a low sun, a white sightscreen or a pale
         * concrete strip are darker than what is behind them, and a brightness test cannot
         * see them at all. Run only when the bright pass found no stumps, and only on
         * alternate such frames: it is a second threshold and a second contour sweep.
         */
        if (pass.sightings.isEmpty() && (searchesWithoutStumps < COLD_BOTH_POLARITIES || searchesWithoutStumps % 2 == 1)) {
            val inverse = Mat()
            /*
             * THE SIGN FLIPS WITH THE POLARITY. THRESH_BINARY_INV marks a pixel when it is NOT
             * above (local mean - C). A negative C, right for "brighter than the mean by
             * |C|", here marks everything not brighter than mean + |C| — nearly the whole
             * frame — and the dark pass found one giant blob and nothing else. Dark means
             * below the mean by |C|: C positive.
             */
            Imgproc.adaptiveThreshold(
                blurred, inverse, 255.0,
                Imgproc.ADAPTIVE_THRESH_GAUSSIAN_C, Imgproc.THRESH_BINARY_INV,
                PitchThreshold.ADAPTIVE_BLOCK, -adaptiveC.toDouble(),
            )
            val darkPass = barPass(inverse, -1, frameW, frameH, frameAspect, creases, luma, width, height, rowStride, rotationDegrees)
            inverse.release()
            if (darkPass.sightings.isNotEmpty()) {
                pass = darkPass
                dark = true
            }
        }

        if (pass.sightings.isNotEmpty()) {
            bright.release()
            blurred.release()
            searchesWithoutStumps = 0
            setsFound++
            lastRejection = null
            if (dark) stumpsWereDark = true
            return pass.sightings
        }
        searchesWithoutStumps++

        val barRejection = pass.rejection

        if (!lookForStones) {
            bright.release()
            blurred.release()
            lastRejection = "$barRejection; stone search off while stumps are locked"
            return emptyList()
        }

        /*
         * THE GULLY WICKET.
         *
         * Pale first, against the mask already computed, then dark on a second threshold —
         * and the dark pass runs only when the pale one found nothing. A white stone on red
         * earth and a granite one on a concrete strip are opposite tests.
         */
        val paleStones = collectStones(bright, frameW, frameH)
        bright.release()
        var mark = StoneGeometry.findMark(paleStones, creases, frameAspect)
        var stones = paleStones.size
        var plausible = StoneGeometry.plausibleCount(paleStones)

        if (mark == null) {
            val darkMask = Mat()
            // Dark = below the local mean by |C|, so C is POSITIVE here — the same sign flip as
            // the dark bar pass. With the pitch detector's negative C this mask was nearly
            // all foreground and the dark-stone pass could never have found a stone.
            Imgproc.adaptiveThreshold(
                blurred, darkMask, 255.0,
                Imgproc.ADAPTIVE_THRESH_GAUSSIAN_C, Imgproc.THRESH_BINARY_INV,
                PitchThreshold.ADAPTIVE_BLOCK, -PitchThreshold.ADAPTIVE_C,
            )
            val darkStones = collectStones(darkMask, frameW, frameH)
            darkMask.release()
            stones += darkStones.size
            plausible += StoneGeometry.plausibleCount(darkStones)
            val darkMark = StoneGeometry.findMark(darkStones, creases, frameAspect)
            if (darkMark != null) {
                mark = darkMark
                stoneWasDark = true
            }
        }

        blurred.release()
        stonesFound = stones
        stonesPlausible = plausible

        if (mark == null) {
            lastRejection = when {
                stones == 0 -> barRejection
                plausible == 0 -> "$barRejection; $stones lumps, none wicket-shaped"
                else -> "$barRejection; $plausible of $stones lumps scored, none a wicket"
            }
            return emptyList()
        }

        stoneMarksFound++
        lastRejection = null
        return listOf(WicketSighting.Stone(mark))
    }

    private class BarPass(val sightings: List<WicketSighting>, val rejection: String)

    /** A place the comb is asked to look at, normalised, with how sure the guess is. */
    private class Probe(
        val centreX: Double,
        val baseY: Double,
        val topY: Double,
        val halfSpan: Double,
        val tolerance: Double,
        val reach: Double,
        /** The contour triple it came from; null for a blob. */
        val triple: StumpSet?,
        val rank: Double = 0.0,
    )

    /**
     * Bars out of one binary mask, turned into wickets.
     *
     * Contours give two kinds of candidate. Triples of separate bars, as before, through
     * [StumpGeometry.search] — now every distinct one rather than the single best. And
     * MERGED blobs: one box the proportions of a whole wicket, which is what three stumps
     * become at match distance once scaled and blurred. Both go to the comb fit at full
     * resolution, which is the judge. A triple the comb cannot confirm survives at a
     * reduced score — a near wicket in strong texture can defeat the comb and still be a
     * wicket — and a blob it cannot confirm is dropped, because a blob proves nothing.
     */
    private fun barPass(
        mask: Mat,
        /** +1 for pale bars, -1 for dark: the sign of "stands out" in [boxContrast]. */
        polarity: Int,
        frameW: Int,
        frameH: Int,
        frameAspect: Float,
        creases: List<CreaseSegment>,
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
    ): BarPass {
        /*
         * OPEN VERTICALLY, THEN CLOSE VERTICALLY — into their own Mats, since the stone pass
         * needs the mask untouched.
         *
         * The opening is the fix for every ground with paint on it. The bowling crease is a
         * white line through the stumps' feet, and in the mask it JOINS them: three stumps
         * and two metres of crease become one wide contour that is neither a bar nor a
         * wicket-shaped blob, and the wicket was never even offered to the comb — on the
         * synthetic field, not at 40 m and not at 5. An opening with a 1×5 column keeps only
         * what is at least five pixels tall, so the crease (a pixel or three thick) and the
         * speckle go, and every stump from 5 to 40 m (twelve pixels tall or more at this
         * scale) stays. The close then rejoins a stump broken by a shadow.
         */
        val opened = Mat()
        val openKernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(1.0, OPEN_HEIGHT_PX))
        Imgproc.morphologyEx(mask, opened, Imgproc.MORPH_OPEN, openKernel)
        openKernel.release()
        val closed = Mat()
        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(1.0, 7.0))
        Imgproc.morphologyEx(opened, closed, Imgproc.MORPH_CLOSE, kernel)
        kernel.release()
        opened.release()

        val contours = mutableListOf<MatOfPoint>()
        val hierarchy = Mat()
        Imgproc.findContours(closed, contours, hierarchy, Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        hierarchy.release()
        closed.release()

        val candidates = ArrayList<StumpCandidate>()
        val blobs = ArrayList<Probe>()
        for (contour in contours) {
            val box = Imgproc.boundingRect(contour)
            contour.release()
            if (box.width <= 0 || box.height <= 0) continue

            val heightFraction = box.height.toFloat() / frameH
            if (heightFraction < MIN_HEIGHT_FRACTION || heightFraction > MAX_HEIGHT_FRACTION) continue

            val baseY = (box.y + box.height).toFloat() / frameH
            // A wicket stands on the pitch. A trunk at the treeline does not.
            if (!GroundHorizon.isOnGround(baseY, horizonY)) {
                barsAboveHorizon++
                continue
            }
            val aspect = box.height.toFloat() / box.width
            val contrast = polarity * boxContrast(box, frameW, frameH)

            // Merged: the proportions of a whole wicket, not of one stump.
            if (aspect in MERGED_MIN_ASPECT..MERGED_MAX_ASPECT && box.width >= MERGED_MIN_WIDTH_PX) {
                /*
                 * TWO PRIORS ON THE SPACING, NOT ONE. The blob's width is outside to outside
                 * plus however much blur, so it overstates; its height is the stumps' height
                 * and fixes the spacing by the Laws' proportion (0.1936 / 0.711), less
                 * whatever a raised camera foreshortens. The comb searches the range both
                 * allow. Trusting the width alone let a narrow blob steer the comb to half
                 * the real spacing — a scale out by two.
                 */
                val fromWidth = box.width * 0.42
                val fromHeight = box.height * (PitchGeometry.STUMP_CENTRES_SPAN_M / PitchGeometry.STUMP_HEIGHT_M) / 2.0
                val lo = minOf(fromWidth, fromHeight) * 0.75
                val hi = maxOf(fromWidth, fromHeight) * 1.35
                val mid = (lo + hi) / 2.0
                val wicketish = aspect / WHOLE_WICKET_ASPECT
                blobs.add(
                    Probe(
                        centreX = (box.x + box.width / 2.0) / frameW,
                        baseY = baseY.toDouble(),
                        topY = box.y.toDouble() / frameH,
                        halfSpan = mid / frameW,
                        tolerance = (hi - lo) / (hi + lo),
                        // A blob of two merged stumps and a third apart is centred half a
                        // spacing off the middle stump; the search must reach past that.
                        reach = 1.6,
                        triple = null,
                        // Most wicket-like first: standing out from the ground, in a whole
                        // wicket's proportions. Height ranked grass streaks first.
                        rank = contrast * (if (wicketish in 0.5..2.0) 1.0 else 0.35),
                    ),
                )
            }

            if (aspect < MIN_ASPECT) continue
            if (box.width.toFloat() / frameW > MAX_WIDTH_FRACTION) continue
            candidates.add(
                StumpCandidate(
                    centreX = (box.x + box.width / 2f) / frameW,
                    baseY = baseY,
                    topY = box.y.toFloat() / frameH,
                    contrast = contrast.toFloat().coerceAtLeast(0.01f),
                ),
            )
        }
        barsFound = candidates.size

        val search = StumpGeometry.search(candidates, creases, frameAspect)
        val probeList = ArrayList<Probe>()
        for (set in search.ranked) {
            probeList.add(
                Probe(
                    centreX = set.middle.centreX.toDouble(),
                    baseY = set.middle.baseY.toDouble(),
                    topY = set.middle.topY.toDouble(),
                    halfSpan = abs(set.spanX) / 2.0,
                    tolerance = 0.3,
                    reach = 1.0,
                    triple = set,
                ),
            )
        }
        probeList.addAll(blobs.sortedByDescending { it.rank }.take(blobBudget))

        val found = ArrayList<StumpSet>()
        for (p in probeList.take(probeBudget)) {
            probes++
            val fitted = StumpProfile.locate(
                luma, width, height, rowStride, rotationDegrees,
                centreX = p.centreX,
                baseY = p.baseY,
                topY = p.topY,
                halfSpanFw = p.halfSpan,
                tolerance = p.tolerance,
                searchHalfSpans = p.reach,
            )
            if (fitted != null) {
                noteFit(fitted.second)
                val how = if (p.triple != null) StumpMethod.TRIPLE_COMB else StumpMethod.MERGED_COMB
                found.add(StumpGeometry.withCreases(fitted.first.copy(method = how), creases, frameAspect))
            }
            /*
             * A triple the comb could NOT confirm is dropped, not passed on at a discount. It
             * used to survive at 0.7 of its score, and a fence — three palings of which the
             * contour test is satisfied and the comb is not — then reached READY on persistence
             * alone, five frames in. The comb works from five metres to forty on the same
             * wickets the contours find; a set it rejects is not a set.
             */
        }

        val sightings = distinctSets(found).map { WicketSighting.Stumps(it) }
        val rejection = when {
            sightings.isNotEmpty() -> ""
            candidates.isEmpty() && blobs.isEmpty() && barsAboveHorizon > 0 ->
                "no bars on the ground ($barsAboveHorizon above the horizon)"
            blobs.isNotEmpty() && candidates.size < StumpGeometry.MIN_CANDIDATES ->
                "${blobs.size} wicket-shaped blobs, none three stumps at full resolution"
            candidates.size < StumpGeometry.MIN_CANDIDATES ->
                "only ${candidates.size} tall thin bars in frame"
            search.reason != null -> "$barsFound bars; nearest miss: ${search.reason}"
            else -> "$barsFound bars, none of them three in a wicket's shape"
        }
        return BarPass(sightings, rejection)
    }

    private var integral: IntArray? = null
    private var integralW = 0
    private var integralMat: Mat? = null

    /**
     * The grey frame's integral image, read out once, for O(1) box means.
     *
     * 32-bit and reused. As 64-bit doubles in a fresh Mat it was 4 MB allocated, filled and
     * copied across JNI on every search frame; a 960×540 frame of 8-bit pixels sums to at
     * most 132 million, which an Int holds, so half the bytes and no allocation.
     */
    private fun loadIntegral(gray: Mat) {
        val sum = integralMat?.takeIf { it.rows() == gray.rows() + 1 && it.cols() == gray.cols() + 1 }
            ?: Mat().also { integralMat?.release(); integralMat = it }
        Imgproc.integral(gray, sum, CvType.CV_32S)
        val n = sum.rows() * sum.cols()
        val out = integral?.takeIf { it.size == n } ?: IntArray(n).also { integral = it }
        sum.get(0, 0, out)
        integralW = sum.cols()
    }

    private fun boxSum(x0: Int, y0: Int, x1: Int, y1: Int): Double {
        val s = integral ?: return 0.0
        val w = integralW
        return (s[y1 * w + x1].toLong() - s[y0 * w + x1] - s[y1 * w + x0] + s[y0 * w + x0]).toDouble()
    }

    /**
     * Mean inside the box minus the mean of the ground either side of it, same rows, a box
     * width out each way. A stump is tens of grey levels; a grass streak is a few.
     */
    private fun boxContrast(box: Rect, frameW: Int, frameH: Int): Double {
        if (integral == null) return 0.0
        val pad = maxOf(2, box.width)
        val x0 = box.x
        val x1 = (box.x + box.width).coerceAtMost(frameW)
        val y0 = box.y
        val y1 = (box.y + box.height).coerceAtMost(frameH)
        val ox0 = (x0 - pad).coerceAtLeast(0)
        val ox1 = (x1 + pad).coerceAtMost(frameW)
        val inner = boxSum(x0, y0, x1, y1)
        val outer = boxSum(ox0, y0, ox1, y1)
        val innerArea = ((x1 - x0) * (y1 - y0)).toDouble()
        val ringArea = ((ox1 - ox0) * (y1 - y0)).toDouble() - innerArea
        if (innerArea <= 0 || ringArea <= 0) return 0.0
        return inner / innerArea - (outer - inner) / ringArea
    }

    /** Best first, one per place: a blob and a triple of the same wicket are one sighting. */
    private fun distinctSets(sets: List<StumpSet>): List<StumpSet> {
        val out = ArrayList<StumpSet>()
        for (s in sets.sortedByDescending { it.score }) {
            val mid = s.middle.centreX
            val clash = out.any { k ->
                abs(mid - k.middle.centreX) < 0.5f * max(abs(s.spanX), abs(k.spanX)) &&
                    abs(s.middle.baseY - k.middle.baseY) < max(s.meanHeight, k.meanHeight)
            }
            if (!clash) out.add(s)
            if (out.size >= StumpGeometry.MAX_RANKED) break
        }
        return out
    }

    /**
     * The threshold offset this frame's noise calls for, in grey levels (negative: brighter
     * than the local mean by this much).
     */
    private fun noiseOffset(gray: Mat, blurred: Mat): Double {
        val diff = Mat()
        return try {
            Core.absdiff(gray, blurred, diff)
            val meanAbs = Core.mean(diff).`val`[0]
            -(meanAbs * NOISE_TO_OFFSET).coerceIn(MIN_ADAPTIVE_OFFSET, MAX_ADAPTIVE_OFFSET)
        } catch (t: Throwable) {
            STUMP_ADAPTIVE_C
        } finally {
            diff.release()
        }
    }

    /**
     * Compact solid things standing on the ground, from an already-binarised mask.
     *
     * Solidity is measured here rather than inferred later because it needs the contour
     * itself, which does not survive the trip: it is the fraction of its own bounding box
     * a shape actually fills, and it is the whole reason a stone can be told from the
     * bush behind it.
     *
     * The mask is closed IN PLACE, so callers hand over a copy they no longer need.
     */
    private fun collectStones(mask: Mat, frameW: Int, frameH: Int): List<StoneCandidate> {
        /*
         * GROUND ONLY, AND AT HALF SCALE.
         *
         * Measured on the first field test: the stone passes took this stage from about
         * 30 ms a frame to 114, which is six frames a second once the pitch detector has
         * had its turn - not a detector, a slideshow. Both savings here are free rather
         * than traded:
         *
         * The crop, because the horizon is already known and nothing above it can be a
         * wicket standing on the ground. Every blob it removes is one the ground test was
         * going to throw away anyway, and the treeline is where most of a frame's blobs
         * live.
         *
         * The half scale, because the 960-wide frame was argued for on stumps: three bars
         * 0.0229 m across, a couple of pixels each at twenty metres. A stone is a hand's
         * width or more and survives halving with tens of pixels to spare. Contour finding
         * is worked per pixel, so half the width and half the height is a quarter of it.
         */
        val top = (((horizonY ?: 0f) * frameH).toInt()).coerceIn(0, (frameH - 2).coerceAtLeast(0))
        val ground = Mat(mask, Rect(0, top, frameW, frameH - top))

        val small = Mat()
        val scaledW = (ground.cols() / 2).coerceAtLeast(1)
        val scaledH = (ground.rows() / 2).coerceAtLeast(1)
        // NEAREST on a mask that is already black or white: anything smoother would invent
        // grey edges for a later threshold to guess at.
        Imgproc.resize(ground, small, Size(scaledW.toDouble(), scaledH.toDouble()), 0.0, 0.0, Imgproc.INTER_NEAREST)
        ground.release()

        // Square, and small. Enough to close the speckle a threshold leaves inside a lump,
        // not enough to change its proportions - which are the measurement.
        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_RECT, Size(3.0, 3.0))
        Imgproc.morphologyEx(small, small, Imgproc.MORPH_CLOSE, kernel)
        kernel.release()

        val contours = mutableListOf<MatOfPoint>()
        val hierarchy = Mat()
        Imgproc.findContours(small, contours, hierarchy, Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        hierarchy.release()
        small.release()

        // Back to the whole frame's coordinates: undo the halving, then put the crop back.
        val found = ArrayList<StoneCandidate>()
        for (contour in contours) {
            val box = Imgproc.boundingRect(contour)
            val area = Imgproc.contourArea(contour)
            contour.release()

            if (box.width <= 0 || box.height <= 0) continue

            val boxTop = top + box.y * 2
            val boxHeight = box.height * 2
            val baseY = (boxTop + boxHeight).toFloat() / frameH
            // The same ground test the bars get, for the same reason: a boulder on the
            // hillside behind the ground is a compact solid lump too. The crop already
            // removes most of them; this catches the rest, including the case where no
            // horizon could be estimated and the crop did nothing.
            if (!GroundHorizon.isOnGround(baseY, horizonY)) continue

            val boxArea = (box.width * box.height).toDouble()
            found.add(
                StoneCandidate(
                    centreX = (box.x * 2 + box.width).toFloat() / frameW,
                    baseY = baseY,
                    topY = boxTop.toFloat() / frameH,
                    width = (box.width * 2).toFloat() / frameW,
                    solidity = if (boxArea <= 0.0) 0f else (area / boxArea).toFloat(),
                ),
            )
        }
        return found
    }

    /**
     * Per-row texture and brightness, reduced to one number each, then judged.
     *
     * Two OpenCV reductions and no loop over pixels in Kotlin: a Sobel in x for the
     * texture, a row-mean for both. Cheap enough to run on every frame beside everything
     * else this screen already does.
     */
    private fun estimateHorizon(gray: Mat, rows: Int): Float? {
        if (rows < GroundHorizon.MIN_ROWS) return null

        val gradient = Mat()
        val absolute = Mat()
        val textureColumn = Mat()
        val brightnessColumn = Mat()
        try {
            Imgproc.Sobel(gray, gradient, CvType.CV_16S, 1, 0, 3)
            Core.convertScaleAbs(gradient, absolute)

            // REDUCE_AVG along dimension 1 collapses each row to its mean.
            Core.reduce(absolute, textureColumn, 1, Core.REDUCE_AVG, CvType.CV_32F)
            Core.reduce(gray, brightnessColumn, 1, Core.REDUCE_AVG, CvType.CV_32F)

            val texture = FloatArray(rows)
            val brightness = FloatArray(rows)
            textureColumn.get(0, 0, texture)
            brightnessColumn.get(0, 0, brightness)

            return GroundHorizon.estimate(texture, brightness)
        } catch (t: Throwable) {
            Log.w(TAG, "horizon estimate failed", t)
            return null
        } finally {
            gradient.release()
            absolute.release()
            textureColumn.release()
            brightnessColumn.release()
        }
    }

    fun release() {
        released = true
        integralMat?.release()
        integralMat = null
        scratchFull?.release()
        scratchFull = null
        scratchPacked = null
    }

    companion object {
        private const val TAG = "OpenCvStumpDetector"

        /** Taller than it is wide, by this much, before it is worth considering as one stump. */
        const val MIN_ASPECT = 2.0f

        /**
         * How much of the frame's height a stump may occupy.
         *
         * The floor keeps out speckle; the ceiling keeps out net poles, sightscreen struts
         * and the batter, all of which run most of the way up a frame in which the stumps
         * do not.
         */
        const val MIN_HEIGHT_FRACTION = 0.015f
        const val MAX_HEIGHT_FRACTION = 0.45f

        /** A bar wider than this is a post, a bat, or a leg. */
        const val MAX_WIDTH_FRACTION = 0.045f

        /**
         * A blob with a whole wicket's proportions: 0.711 m tall over 0.2286 m wide is 3.1,
         * squashed towards 1.3 by a camera looking down and stretched by blur to ~7 when
         * the stumps are a pixel each.
         */
        const val MERGED_MIN_ASPECT = 1.3f
        const val MERGED_MAX_ASPECT = 7.0f

        /** Narrower than this and there is no wicket inside it to resolve. */
        const val MERGED_MIN_WIDTH_PX = 3

        /** Comb fits per search frame, and how many of them may go to blobs. */
        const val MAX_PROBES = 6
        const val MAX_BLOB_PROBES = 4

        /** The same, on a cold start with nothing held or remembered. */
        const val COLD_MAX_PROBES = 12
        const val COLD_MAX_BLOB_PROBES = 9

        /** Failed searches on which both polarities run every time, before alternating. */
        const val COLD_BOTH_POLARITIES = 3

        /** Shortest vertical run the bar mask keeps; see the opening in [barPass]. */
        const val OPEN_HEIGHT_PX = 5.0

        /** Height over outside width of a whole wicket: 0.711 / 0.2286. */
        const val WHOLE_WICKET_ASPECT = 3.1

        /** The old fixed offset; the fallback when the noise cannot be measured. */
        const val STUMP_ADAPTIVE_C = -3.5

        /** Threshold offset per grey level of measured noise, and its bounds. */
        const val NOISE_TO_OFFSET = 1.6
        const val MIN_ADAPTIVE_OFFSET = 2.5
        const val MAX_ADAPTIVE_OFFSET = 6.0
    }
}
