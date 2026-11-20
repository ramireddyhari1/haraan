package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.sqrt

/**
 * HOW BIG THE BALL IS IN THE PICTURE — measured at the camera's full resolution.
 *
 * Filmed from behind the bowler, the far half of a delivery is about level with the lens, and
 * along such a flat line of sight 8 cm of height is 4 m of distance: a full tennis ball in the
 * bowling session of 2026-11-19 was fitted at 48-60 km/h for ~67, with gravity alone left to
 * say how far away it was. Near the lens the ball says so itself — 20 px across at 4 m,
 * against a 6.7 cm ball — so its width there is a ruler the fit can use (see [QuadSpeed]).
 *
 * The tracker's own blob area is no use for that: it is a frame difference in a frame shrunk
 * to 480 wide, 1-28 px of area. This measures the ball again in the full-resolution luma: the
 * difference against a frame at least [MIN_SPAN_MS] older, in a window round the sighting, the
 * patch that contains the ball, and its NARROWER width — a moving ball smears along its path,
 * never across it.
 *
 * One per tracker, on its analysis thread; [sized] may be asked from any thread.
 */
class BallSizer {

    private class Frame(var ms: Long, val luma: ByteArray, var width: Int, var height: Int, var stride: Int, var turn: Int)

    private val ring = ArrayList<Frame>()
    /*
     * Every candidate's width, by frame: the point the tracker returned for a frame is not
     * always the one its flight keeps (a hand that frame, the ball confirmed after), so each
     * candidate is measured and a flight's point takes the width measured where it is.
     */
    private class Measured(val x: Float, val y: Float, val diameterFw: Float)
    private val sizes = object : LinkedHashMap<Long, List<Measured>>(64, 0.75f, false) {
        override fun removeEldestEntry(eldest: MutableMap.MutableEntry<Long, List<Measured>>?) = size > KEEP_SIZES
    }

    /** Forget everything: a new clip, a new clock. */
    fun reset() {
        ring.clear()
        synchronized(sizes) { sizes.clear() }
    }

    /**
     * One analysed frame: measures [sighting] (when there is one) against an older frame, then
     * keeps this frame for the next ones. Returns the diameter in upright frame widths, or null.
     */
    fun frame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        timestampMs: Long,
        sighting: BallSighting?,
        /** Every candidate of the frame (the tracker's lastCandidates); the sighting's own point is always tried. */
        candidates: List<Point2> = emptyList(),
    ): Float? {
        fun same(f: Frame) = f.width == width && f.height == height && f.turn == rotationDegrees
        val older = ring.lastOrNull { timestampMs - it.ms >= MIN_SPAN_MS && timestampMs - it.ms <= MAX_SPAN_MS }?.takeIf(::same)
        // A second, older still: the ball NOW differs from both, where it was differs from one.
        val oldest = older?.let { o -> ring.lastOrNull { o.ms - it.ms >= MIN_SPAN_MS && timestampMs - it.ms <= 2 * MAX_SPAN_MS }?.takeIf(::same) }
        var mine: Float? = null
        if (older != null) {
            val points = ArrayList<Point2>()
            sighting?.let { points.add(Point2(it.x.toDouble(), it.y.toDouble())) }
            for (c in candidates.take(MAX_CANDIDATES)) {
                if (points.none { kotlin.math.hypot(it.x - c.x, it.y - c.y) < SAME_POINT }) points.add(c)
            }
            val got = points.mapNotNull { p ->
                measure(luma, older.luma, width, height, rowStride, older.stride, rotationDegrees, p.x.toFloat(), p.y.toFloat(), oldest?.luma, oldest?.stride ?: 0)
                    ?.let { Measured(p.x.toFloat(), p.y.toFloat(), it) }
            }
            if (got.isNotEmpty()) synchronized(sizes) { sizes[timestampMs] = got }
            mine = sighting?.let { s -> got.firstOrNull { it.x == s.x && it.y == s.y }?.diameterFw }
        }
        keep(luma, width, height, rowStride, rotationDegrees, timestampMs)
        return mine
    }

    /** [track] with each point's measured width attached: the one measured where it is, at its frame. */
    fun sized(track: List<BallSighting>): List<BallSighting> = synchronized(sizes) {
        track.map { s ->
            if (s.diameterFw != null) return@map s
            val m = sizes[s.timestampMs]
                ?.minByOrNull { kotlin.math.hypot((it.x - s.x).toDouble(), (it.y - s.y).toDouble()) }
                ?.takeIf { kotlin.math.hypot((it.x - s.x).toDouble(), (it.y - s.y).toDouble()) <= SAME_POINT }
            m?.let { s.copy(diameterFw = it.diameterFw) } ?: s
        }
    }

    private fun keep(luma: ByteArray, width: Int, height: Int, stride: Int, turn: Int, ms: Long) {
        val need = stride * height
        val slot = if (ring.size < RING) {
            Frame(ms, ByteArray(need), width, height, stride, turn).also { ring.add(it) }
        } else {
            ring.removeAt(0).also { ring.add(it) }
        }
        if (slot.luma.size < need) {
            ring.remove(slot)
            ring.add(Frame(ms, ByteArray(need), width, height, stride, turn))
        }
        val f = ring.last()
        System.arraycopy(luma, 0, f.luma, 0, minOf(need, luma.size))
        f.ms = ms; f.width = width; f.height = height; f.stride = stride; f.turn = turn
    }

    companion object {
        /** Older than this, the ball has moved off itself even close to the lens at 120 fps. */
        const val MIN_SPAN_MS = 25L
        const val MAX_SPAN_MS = 120L
        private const val RING = 6
        private const val KEEP_SIZES = 400
        /** Candidates measured per frame: the ball is among the first few. */
        private const val MAX_CANDIDATES = 4
        /** A flight's point and a measured candidate this close (frame units) are the same thing. */
        private const val SAME_POINT = 0.012
        /** Half the window looked in, in sensor pixels: a ball up to ~60 px across. */
        private const val HALF = 40
        private const val MIN_DIFF = 18
        /** Under this many pixels (~3 px across) a width is rounding, not a measurement. */
        private const val MIN_PIXELS = 7

        /**
         * The ball's diameter in upright frame widths, from [cur] against [old] (same layout)
         * round the upright sighting ([ux], [uy]); null when no clean round patch is there.
         */
        internal fun measure(
            cur: ByteArray,
            old: ByteArray,
            width: Int,
            height: Int,
            stride: Int,
            oldStride: Int,
            turn: Int,
            ux: Float,
            uy: Float,
            /** An older frame still, or null: the ball now differs from both, its ghost from one. */
            older: ByteArray? = null,
            olderStride: Int = 0,
        ): Float? {
            // Upright to sensor, by the turns the tracker uses (see OpenCvBallTracker.corridorGray).
            val (sx, sy) = when (turn) {
                90 -> uy * width to (1f - ux) * height
                180 -> (1f - ux) * width to (1f - uy) * height
                270 -> (1f - uy) * width to ux * height
                else -> ux * width to uy * height
            }
            val uprightWidth = if (turn == 90 || turn == 270) height else width
            val cx = sx.toInt()
            val cy = sy.toInt()
            val x0 = (cx - HALF).coerceAtLeast(0)
            val x1 = (cx + HALF).coerceAtMost(width - 1)
            val y0 = (cy - HALF).coerceAtLeast(0)
            val y1 = (cy + HALF).coerceAtMost(height - 1)
            if (x1 - x0 < 8 || y1 - y0 < 8) return null
            val w = x1 - x0 + 1
            val h = y1 - y0 + 1
            val diff = IntArray(w * h)
            var peak = 0
            for (y in 0 until h) {
                val r = (y0 + y) * stride
                val ro = (y0 + y) * oldStride
                val ro2 = (y0 + y) * olderStride
                for (x in 0 until w) {
                    val now = cur[r + x0 + x].toInt() and 0xFF
                    val d1 = abs(now - (old[ro + x0 + x].toInt() and 0xFF))
                    val d = if (older != null) minOf(d1, abs(now - (older[ro2 + x0 + x].toInt() and 0xFF))) else d1
                    diff[y * w + x] = d
                    // The peak near the sighting, not a far-off flicker.
                    if (abs(x0 + x - cx) <= HALF / 2 && abs(y0 + y - cy) <= HALF / 2 && d > peak) peak = d
                }
            }
            if (peak < MIN_DIFF) return null
            /*
             * Gentle: where the ball passes in front of something as bright as itself (a sunlit
             * frond behind a yellow ball) its difference is small, and a strict cut bit pieces out
             * of it — every width read small (2026-11-20, session ball 6).
             */
            val threshold = maxOf(MIN_DIFF, (peak * 0.22).toInt())
            // Small bites closed: dilate then erode, 3x3.
            val raw = BooleanArray(w * h) { diff[it] >= threshold }
            val mask = erode(dilate(raw, w, h), w, h)
            // The changed pixel nearest the sighting seeds the patch.
            var seed = -1
            var best = Int.MAX_VALUE
            for (y in 0 until h) for (x in 0 until w) {
                if (!mask[y * w + x]) continue
                val dd = (x0 + x - cx) * (x0 + x - cx) + (y0 + y - cy) * (y0 + y - cy)
                if (dd < best) { best = dd; seed = y * w + x }
            }
            if (seed < 0 || best > SEED_REACH * SEED_REACH) return null
            // Flood the patch (4-connected).
            val seen = BooleanArray(w * h)
            val stack = IntArray(w * h)
            val xs = IntArray(w * h)
            val ys = IntArray(w * h)
            var top = 0
            stack[top++] = seed
            seen[seed] = true
            var n = 0
            var touchesEdge = false
            while (top > 0) {
                val p = stack[--top]
                val px = p % w
                val py = p / w
                xs[n] = px; ys[n] = py; n++
                if (px == 0 || py == 0 || px == w - 1 || py == h - 1) touchesEdge = true
                if (px > 0) { val q = p - 1; if (!seen[q] && mask[q]) { seen[q] = true; stack[top++] = q } }
                if (px < w - 1) { val q = p + 1; if (!seen[q] && mask[q]) { seen[q] = true; stack[top++] = q } }
                if (py > 0) { val q = p - w; if (!seen[q] && mask[q]) { seen[q] = true; stack[top++] = q } }
                if (py < h - 1) { val q = p + w; if (!seen[q] && mask[q]) { seen[q] = true; stack[top++] = q } }
            }
            // Running into the window's edge is an arm, a body, a moving net — not a ball.
            if (n < MIN_PIXELS || touchesEdge) return null
            /*
             * ITS OUTER WIDTHS, IN TWELVE DIRECTIONS. A bite out of the patch's middle leaves its
             * outline where it was, so the extent across it (a Feret width) does not shrink as an
             * area or a spread does. Round: the median of them. Smeared along its path: the
             * narrowest, which is across the smear.
             */
            val feret = DoubleArray(FERET_DIRECTIONS) { k ->
                val a = Math.PI * k / FERET_DIRECTIONS
                val ca = kotlin.math.cos(a)
                val sa = kotlin.math.sin(a)
                var lo = Double.MAX_VALUE
                var hi = -Double.MAX_VALUE
                for (i in 0 until n) {
                    val t = xs[i] * ca + ys[i] * sa
                    if (t < lo) lo = t
                    if (t > hi) hi = t
                }
                hi - lo + 1
            }
            val sorted = feret.sorted()
            val narrow = sorted.first()
            val wide = sorted.last()
            if (narrow <= 0) return null
            // Smeared along its path a little is a ball; three times longer than wide is not.
            if (wide > narrow * MAX_STRETCH) return null
            val outer = if (wide <= narrow * ROUND_STRETCH) sorted[sorted.size / 2] else narrow
            // Edge pixels only partly over the ball still pass the gentle cut: take that rim off.
            val diameter = outer - EDGE_PX
            if (diameter <= 1.0) return null
            // Mostly filled: a capsule of that width and length, not a ragged scrap.
            val fill = n / (Math.PI / 4 * diameter * diameter + (wide - diameter) * diameter)
            if (fill < MIN_FILL) return null
            return (diameter / uprightWidth).toFloat()
        }

        private const val MAX_STRETCH = 3.0
        private const val ROUND_STRETCH = 1.25
        /** Sensor pixels the gentle cut adds across a ball (two part-covered rims), on session ball 6. */
        private const val EDGE_PX = 1.3
        /** How far from the tracker's point the ball's patch may start, sensor pixels. */
        private const val SEED_REACH = 12
        private const val FERET_DIRECTIONS = 12

        private fun dilate(m: BooleanArray, w: Int, h: Int): BooleanArray = BooleanArray(w * h) { i ->
            val x = i % w
            val y = i / w
            var on = false
            for (dy in -1..1) for (dx in -1..1) {
                val xx = x + dx
                val yy = y + dy
                if (xx in 0 until w && yy in 0 until h && m[yy * w + xx]) on = true
            }
            on
        }

        private fun erode(m: BooleanArray, w: Int, h: Int): BooleanArray = BooleanArray(w * h) { i ->
            val x = i % w
            val y = i / w
            var on = true
            for (dy in -1..1) for (dx in -1..1) {
                val xx = x + dx
                val yy = y + dy
                if (xx in 0 until w && yy in 0 until h && !m[yy * w + xx]) on = false
            }
            on
        }

        private const val MIN_FILL = 0.6

        /** The ball's width says how far it was: metres, for a [ballM] ball through a [focalFw] lens. */
        fun rangeM(diameterFw: Double, focalFw: Double, ballM: Double): Double = focalFw * ballM / diameterFw
    }
}
