package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.max

/**
 * The stump search in BRIGHTNESS and in COLOUR, best of both.
 *
 * WHY. Found on a real phone, on a real backyard pitch: yellow plastic stumps against a
 * pink wall. In brightness (the Y plane, the only thing the detector used to read) yellow
 * and pink are within a few grey levels of each other, so the stumps' upper two-thirds
 * vanished; the lower third, against black-and-white floor tiles, was found as half a
 * wicket, or not at all. In the U plane — blue-difference chroma — the same stumps are
 * three clean dark bars on a flat background, and the unchanged detector finds them on its
 * first probe. Coloured plastic stumps are what gully and backyard cricket is played with.
 *
 * HOW. The same [OpenCvStumpDetector] logic runs on the U plane, de-interleaved into a
 * packed half-resolution image. Normalised coordinates do not care about resolution, so the
 * two channels' sightings are the same currency and are merged per place, the taller and
 * better-scored one kept. Half resolution is fine for colour: chroma is only half-resolution
 * on the sensor anyway, and it is the NEAR, coloured wicket it is for — a far wicket is a
 * pixel per stump, which only the brightness pass can resolve.
 *
 * COST. The colour pass works on a quarter of the pixels. It runs on every frame where the
 * brightness pass found no good stumps, and on every focus frame (a patch, a millisecond or
 * two); otherwise it is skipped.
 */
class ColourStumpDetector(
    private val luma: OpenCvStumpDetector = OpenCvStumpDetector(),
    private val chroma: OpenCvStumpDetector = OpenCvStumpDetector(),
) {
    val available: Boolean get() = luma.available

    private var packedU: ByteArray? = null

    /** Which channel the last kept sighting came from: "luma", "colour", or "—". */
    @Volatile var lastChannel: String = "—"
        private set

    fun detectCandidates(
        y: ByteArray,
        width: Int,
        height: Int,
        yRowStride: Int,
        rotationDegrees: Int,
        u: ByteArray?,
        uRowStride: Int,
        uPixelStride: Int,
        creases: List<CreaseSegment> = emptyList(),
        lookForStones: Boolean = true,
        focus: WicketFocus? = null,
        allowSearch: Boolean = true,
    ): List<WicketSighting> {
        val fromLuma = luma.detectCandidates(
            y, width, height, yRowStride, rotationDegrees, creases, lookForStones, focus, allowSearch,
        )
        val strongLuma = fromLuma.any { it is WicketSighting.Stumps && it.score >= STRONG_SCORE }
        val runColour = u != null && (!strongLuma || focus != null)
        val fromColour = if (runColour) {
            val packed = pack(u!!, width / 2, height / 2, uRowStride, uPixelStride)
            if (packed == null) {
                emptyList()
            } else {
                // Stones are colourless lumps; the colour pass looks for stumps only.
                chroma.detectCandidates(
                    packed, width / 2, height / 2, width / 2, rotationDegrees, creases,
                    lookForStones = false, focus = focus, allowSearch = allowSearch,
                ).filterIsInstance<WicketSighting.Stumps>()
            }
        } else {
            emptyList()
        }
        val merged = merge(fromLuma, fromColour)
        lastChannel = when {
            merged.isEmpty() -> "—"
            merged.first() in fromColour -> "colour"
            else -> "luma"
        }
        return merged
    }

    /** The report of whichever channel the last kept sighting came from, labelled. */
    fun report(): StumpDetectorReport {
        val r = if (lastChannel == "colour") chroma.report() else luma.report()
        return r.copy(mode = "${r.mode} · $lastChannel")
    }

    fun release() {
        luma.release()
        chroma.release()
    }

    /** The U plane, de-interleaved into a packed width×height image; null if the buffer is short. */
    private fun pack(u: ByteArray, w: Int, h: Int, rowStride: Int, pixelStride: Int): ByteArray? {
        if (w <= 0 || h <= 0 || pixelStride <= 0) return null
        val last = (h - 1) * rowStride + (w - 1) * pixelStride
        if (last >= u.size) return null
        val out = packedU?.takeIf { it.size == w * h } ?: ByteArray(w * h).also { packedU = it }
        if (pixelStride == 1 && rowStride == w) {
            System.arraycopy(u, 0, out, 0, w * h)
        } else {
            for (r in 0 until h) {
                val from = r * rowStride
                val to = r * w
                for (c in 0 until w) out[to + c] = u[from + c * pixelStride]
            }
        }
        return out
    }

    companion object {
        /** A brightness sighting this good needs no second opinion from colour. */
        const val STRONG_SCORE = 0.75f

        /**
         * One sighting per place, best first. Where both channels found the same wicket,
         * the TALLER one wins unless it scored much worse: half a wicket found in brightness
         * (its top lost against a wall of the same grey) is the failure this class exists for.
         */
        fun merge(a: List<WicketSighting>, b: List<WicketSighting>): List<WicketSighting> {
            val all = (a + b).sortedByDescending { it.score }
            val out = ArrayList<WicketSighting>()
            for (s in all) {
                val i = out.indexOfFirst { samePlace(it, s) }
                if (i < 0) {
                    out.add(s)
                } else if (taller(s, out[i]) && s.score >= out[i].score * 0.8f) {
                    out[i] = s
                }
            }
            return out.sortedByDescending { it.score }
        }

        private fun heightOf(s: WicketSighting): Float = when (s) {
            is WicketSighting.Stumps -> s.set.meanHeight
            is WicketSighting.Stone -> s.mark.baseY - s.mark.topY
        }

        private fun spanOf(s: WicketSighting): Float = when (s) {
            is WicketSighting.Stumps -> abs(s.set.spanX)
            is WicketSighting.Stone -> s.mark.width
        }

        private fun taller(s: WicketSighting, than: WicketSighting) = heightOf(s) > heightOf(than) * 1.15f

        private fun samePlace(a: WicketSighting, b: WicketSighting): Boolean {
            val reach = max(spanOf(a), spanOf(b)).coerceAtLeast(0.01f)
            return abs(a.base.x - b.base.x) < reach && abs(a.base.y - b.base.y) < max(heightOf(a), heightOf(b))
        }
    }
}
