package com.haraan.app.vision

import kotlin.math.cos
import kotlin.math.exp
import kotlin.math.sin
import kotlin.math.tan
import kotlin.random.Random

/**
 * A wicket on a ground, drawn the way a phone camera would see it.
 *
 * Physically scaled, not hand-placed: a pinhole of the given horizontal field of view, the
 * Laws' stump dimensions, a distance in metres. Every pixel is the AREA the stumps cover in
 * it (4×4 supersampled), then a lens blur, then sensor noise — which is what decides
 * whether three 1.5-pixel bars are three bars or one, and therefore the only kind of test
 * scene in which a far-distance claim means anything.
 */
object SyntheticWicket {

    data class Scene(
        val width: Int = 1280,
        val height: Int = 720,
        val distanceM: Double = 22.0,
        val hfovDeg: Double = 70.0,
        /** Middle stump's foot, upright pixels. */
        val footX: Double = 640.3,
        val footY: Double = 430.0,
        val rollDeg: Double = 0.0,
        val ground: Double = 95.0,
        val stump: Double = 165.0,
        val noise: Double = 2.5,
        /** Gaussian lens/demosaic blur, pixels. */
        val blur: Double = 0.55,
        val seed: Int = 7,
        /** Extra vertical bars, as (centre x px, width m) — palings, poles, legs. */
        val posts: List<Pair<Double, Double>> = emptyList(),
        val drawStumps: Boolean = true,
        /** Where the sky meets the ground, upright pixels; null for a frame with no sky. */
        val horizonY: Double? = null,
        val sky: Double = 200.0,
        /**
         * Grass texture over the WHOLE frame, ± grey levels, uniform. The whole-frame search
         * reads every pixel, so a test of it needs clutter everywhere, not only round the
         * wicket. Zero keeps the old cheap flat ground.
         */
        val texture: Double = 0.0,
        /** Vertical grass streaks: the share of columns with a faint pale stripe. */
        val streaks: Double = 0.0,
        /** The ground's own pattern. Fixed across a sequence: grass does not re-grow per frame. */
        val groundSeed: Int = 1,
        /**
         * The bowling crease: a white line through the stump feet, 2.64 m long, a couple of
         * pixels thick at a shallow angle. Real grounds have it, and it joins the three
         * stumps into one wide contour.
         */
        val crease: Boolean = false,
        /**
         * Big upright shapes that are NOT stumps, as (centre x px, foot y px, width m,
         * height m, grey): a batter, an umpire, a sightscreen strut. Drawn over everything.
         */
        val figures: List<Figure> = emptyList(),
    ) {
        val focalPx: Double get() = (width / 2.0) / tan(Math.toRadians(hfovDeg / 2.0))
        val pxPerM: Double get() = focalPx / distanceM
        val halfSpanPx: Double get() = PitchGeometry.STUMP_CENTRES_SPAN_M / 2.0 * pxPerM
        val heightPx: Double get() = PitchGeometry.STUMP_HEIGHT_M * pxPerM
    }

    data class Figure(val x: Double, val footY: Double, val widthM: Double, val heightM: Double, val grey: Double)

    /** Upright grey image, row-major, as doubles. */
    fun renderUpright(s: Scene): DoubleArray {
        val horizon = s.horizonY
        val img = DoubleArray(s.width * s.height) {
            if (horizon != null && it / s.width < horizon) s.sky else s.ground
        }
        if (s.texture > 0.0 || s.streaks > 0.0) {
            val tr = Random(s.groundSeed * 31 + 5)
            for (y in 0 until s.height) {
                if (horizon != null && y < horizon) continue
                val row = y * s.width
                for (x in 0 until s.width) img[row + x] += (tr.nextDouble() * 2 - 1) * s.texture
            }
            // Grass blades: short pale vertical strokes, not ruled lines across the field.
            val top = horizon?.toInt() ?: 0
            val strokes = (s.streaks * s.width * (s.height - top) / 12.0).toInt()
            repeat(strokes) {
                val x = tr.nextInt(s.width)
                val y0 = top + tr.nextInt((s.height - top).coerceAtLeast(1))
                val len = 4 + tr.nextInt(22)
                for (y in y0 until minOf(s.height, y0 + len)) img[y * s.width + x] += 6.0
            }
        }
        val d = PitchGeometry.STUMP_DIAMETER_M * s.pxPerM
        val g = s.halfSpanPx
        val h = s.heightPx
        val roll = Math.toRadians(s.rollDeg)
        val cr = cos(roll); val sr = sin(roll)

        val bars = ArrayList<Pair<Double, Double>>() // centre offset px, width px
        if (s.drawStumps) for (i in -1..1) bars.add(i * g to d)
        for ((cx, wm) in s.posts) bars.add((cx - s.footX) to wm * s.pxPerM)

        val x0 = (s.footX - g * 6 - h * 0.2 - 40).toInt().coerceAtLeast(0)
        val x1 = (s.footX + g * 6 + h * 0.2 + 40).toInt().coerceAtMost(s.width - 1)
        val minPost = s.posts.minOfOrNull { it.first }?.toInt()?.minus(20) ?: x0
        val maxPost = s.posts.maxOfOrNull { it.first }?.toInt()?.plus(20) ?: x1
        val xa = minOf(x0, minPost).coerceAtLeast(0)
        val xb = maxOf(x1, maxPost).coerceAtMost(s.width - 1)
        val y0 = (s.footY - h - 10).toInt().coerceAtLeast(0)
        val y1 = (s.footY + 4).toInt().coerceAtMost(s.height - 1)

        for (y in y0..y1) for (x in xa..xb) {
            var cover = 0
            for (sy in 0 until 4) for (sx in 0 until 4) {
                val px = x + (sx + 0.5) / 4.0 - s.footX
                val py = y + (sy + 0.5) / 4.0 - s.footY
                // Undo the roll: into the wicket's own upright frame.
                val ux = px * cr + py * sr
                val uy = -px * sr + py * cr
                if (uy > 0 || uy < -h) continue
                if (bars.any { (c, w) -> kotlin.math.abs(ux - c) < w / 2 }) cover++
            }
            if (cover > 0) {
                val under = img[y * s.width + x]
                img[y * s.width + x] = under + (s.stump - under) * cover / 16.0
            }
        }
        if (s.crease) {
            val half = 1.32 * s.pxPerM
            val thick = maxOf(1.5, 0.05 * s.pxPerM * 0.35)
            for (y in (s.footY - thick / 2).toInt()..(s.footY + thick / 2).toInt()) {
                if (y !in 0 until s.height) continue
                for (x in (s.footX - half).toInt()..(s.footX + half).toInt()) {
                    if (x in 0 until s.width) img[y * s.width + x] = 205.0
                }
            }
        }
        for (f in s.figures) {
            val hw = f.widthM * s.pxPerM / 2
            val fh = f.heightM * s.pxPerM
            for (y in (f.footY - fh).toInt().coerceAtLeast(0)..f.footY.toInt().coerceAtMost(s.height - 1)) {
                for (x in (f.x - hw).toInt().coerceAtLeast(0)..(f.x + hw).toInt().coerceAtMost(s.width - 1)) {
                    img[y * s.width + x] = f.grey
                }
            }
        }
        // Blur and noise only where anything was drawn (plus a margin wider than any ROI):
        // the rest is flat ground, and a full-frame pass per frame makes a sequence slow.
        val rx0 = (xa - 60).coerceAtLeast(0); val rx1 = (xb + 60).coerceAtMost(s.width - 1)
        val ry0 = (y0 - 60).coerceAtLeast(0); val ry1 = (y1 + 60).coerceAtMost(s.height - 1)
        if (s.blur > 0) gaussianIn(img, s.width, s.blur, rx0, rx1, ry0, ry1)
        val rnd = Random(s.seed)
        for (y in ry0..ry1) for (x in rx0..rx1) img[y * s.width + x] += gauss(rnd) * s.noise
        return img
    }

    /** The same picture laid out as a sensor would deliver it before [rotationDegrees]. */
    fun sensorLuma(s: Scene, rotationDegrees: Int = 0): Triple<ByteArray, Int, Int> {
        val up = renderUpright(s)
        val turn = ((rotationDegrees % 360) + 360) % 360
        val sw = if (turn == 90 || turn == 270) s.height else s.width
        val sh = if (turn == 90 || turn == 270) s.width else s.height
        val out = ByteArray(sw * sh)
        for (uy in 0 until s.height) for (ux in 0 until s.width) {
            val (sx, sy) = when (turn) {
                90 -> uy to (sh - 1 - ux)
                180 -> (sw - 1 - ux) to (sh - 1 - uy)
                270 -> (sw - 1 - uy) to ux
                else -> ux to uy
            }
            out[sy * sw + sx] = up[uy * s.width + ux].toInt().coerceIn(0, 255).toByte()
        }
        return Triple(out, sw, sh)
    }

    private fun gauss(r: Random): Double {
        var u = 0.0
        repeat(12) { u += r.nextDouble() }
        return u - 6.0
    }

    /** Separable Gaussian, in place, inside one rectangle. */
    private fun gaussianIn(img: DoubleArray, w: Int, sigma: Double, x0: Int, x1: Int, y0: Int, y1: Int) {
        val rad = kotlin.math.ceil(sigma * 3).toInt().coerceAtLeast(1)
        val k = DoubleArray(2 * rad + 1) { exp(-((it - rad) * (it - rad)) / (2 * sigma * sigma)) }
        val sum = k.sum(); for (i in k.indices) k[i] /= sum
        val rw = x1 - x0 + 1; val rh = y1 - y0 + 1
        val tmp = DoubleArray(rw * rh)
        for (y in y0..y1) for (x in x0..x1) {
            var a = 0.0
            for (i in k.indices) a += img[y * w + (x + i - rad).coerceIn(x0, x1)] * k[i]
            tmp[(y - y0) * rw + (x - x0)] = a
        }
        for (y in y0..y1) for (x in x0..x1) {
            var a = 0.0
            for (i in k.indices) a += tmp[((y + i - rad).coerceIn(y0, y1) - y0) * rw + (x - x0)] * k[i]
            img[y * w + x] = a
        }
    }

    /**
     * Whether the OLD whole-frame pass could have seen three separate bars here.
     *
     * Its first three steps, reproduced: INTER_AREA down to 960 wide, a 3×3 Gaussian, and
     * an adaptive threshold at C = -3.5 over a 31-pixel block. Three bars survive only if a
     * row through the stumps' middle crosses three separate runs above the threshold.
     * Generous to the old code — it ignores the vertical close and the contour shape tests,
     * which can only lose bars, never find more.
     */
    fun oldPipelineResolvesThree(s: Scene): Boolean {
        val up = renderUpright(s)
        val scale = 960.0 / s.width
        val dw = 960
        val dh = (s.height * scale).toInt()
        // Area resample, only the rows near the stumps' middle are needed.
        val midY = ((s.footY - s.heightPx / 2) * scale).toInt()
        val rows = (midY - 3)..(midY + 3)
        val small = HashMap<Int, DoubleArray>()
        for (oy in rows) {
            val row = DoubleArray(dw)
            val sy0 = oy / scale; val sy1 = (oy + 1) / scale
            for (ox in 0 until dw) {
                val sx0 = ox / scale; val sx1 = (ox + 1) / scale
                var acc = 0.0; var wsum = 0.0
                var yy = kotlin.math.floor(sy0).toInt()
                while (yy < sy1) {
                    val wy = minOf(sy1, yy + 1.0) - maxOf(sy0, yy.toDouble())
                    var xx = kotlin.math.floor(sx0).toInt()
                    while (xx < sx1) {
                        val wx = minOf(sx1, xx + 1.0) - maxOf(sx0, xx.toDouble())
                        val v = up[yy.coerceIn(0, s.height - 1) * s.width + xx.coerceIn(0, s.width - 1)]
                        acc += v * wx * wy; wsum += wx * wy
                        xx++
                    }
                    yy++
                }
                row[ox] = acc / wsum
            }
            small[oy] = row
        }
        // 3×3 Gaussian at the middle row.
        val k = doubleArrayOf(0.25, 0.5, 0.25)
        val mid = DoubleArray(dw)
        for (x in 0 until dw) {
            var a = 0.0
            for (dy in -1..1) for (dx in -1..1) {
                a += small[midY + dy]!![(x + dx).coerceIn(0, dw - 1)] * k[dy + 1] * k[dx + 1]
            }
            mid[x] = a
        }
        val cx = (s.footX * scale).toInt()
        var local = 0.0
        for (x in cx - 15..cx + 15) local += mid[x.coerceIn(0, dw - 1)]
        local /= 31
        val threshold = local + 3.5
        var runs = 0
        var inRun = false
        for (x in (cx - 40).coerceAtLeast(0)..(cx + 40).coerceAtMost(dw - 1)) {
            val on = mid[x] > threshold
            if (on && !inRun) runs++
            inRun = on
        }
        return runs >= 3
    }
}
