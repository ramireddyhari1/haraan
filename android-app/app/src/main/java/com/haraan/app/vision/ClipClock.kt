package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.hypot

/**
 * WHERE A SAVED CLIP SITS ON THE TRACKER'S CLOCK, MEASURED FROM THE BALL ITSELF.
 *
 * A clip's frame 0 is put on the camera clock by the first analysed frame after the recorder
 * says it started — and the recorder's first frame is not that one. On the Motorola
 * (bowling session, 2026-11-19) the path drawn into a saved video ran ~100 ms behind the ball
 * it was drawn over, and the clip's re-analysis was looked for in a window shifted as much.
 *
 * The same ball is in both: tracked live on the camera clock, and tracked again in the clip
 * on the clip's clock as it was placed. Slid against each other, the two tracks line up at
 * one shift — that shift is how far the clip was misplaced.
 */
object ClipClock {

    /** The largest misplacement looked for, either way. */
    const val MAX_SHIFT_MS = 250.0
    private const val STEP_MS = 2.0
    /** Live sightings that must land inside the clip track for a shift to count. */
    private const val MIN_OVERLAP = 4
    /** The two tracks, lined up, must agree to within this (frame widths, median). */
    private const val MAX_MEDIAN_MISS = 0.012

    /**
     * δ such that the clip track at time t + δ is where the live track was at t; null when
     * the two do not line up well enough to say. A clip placed at `start` really began at
     * `start - δ`, and the clip track belongs δ earlier on the camera clock.
     */
    fun offsetMs(live: List<BallSighting>, clip: List<BallSighting>): Double? {
        if (live.size < MIN_OVERLAP || clip.size < 2) return null
        var best: Double? = null
        var bestMiss = Double.MAX_VALUE
        var shift = -MAX_SHIFT_MS
        while (shift <= MAX_SHIFT_MS) {
            val misses = live.mapNotNull { p -> at(clip, p.timestampMs + shift)?.let { (x, y) -> hypot((x - p.x).toDouble(), (y - p.y).toDouble()) } }
            if (misses.size >= MIN_OVERLAP) {
                val miss = misses.sorted()[misses.size / 2]
                // Ties go to the smaller shift: no correction is the safer one.
                if (miss < bestMiss - 1e-9 || (abs(miss - bestMiss) <= 1e-9 && abs(shift) < abs(best ?: Double.MAX_VALUE))) {
                    bestMiss = miss
                    best = shift
                }
            }
            shift += STEP_MS
        }
        return best?.takeIf { bestMiss <= MAX_MEDIAN_MISS }
    }

    /** The track's position at [ms], straight between its sightings; null outside it. */
    private fun at(track: List<BallSighting>, ms: Double): Pair<Float, Float>? {
        if (ms < track.first().timestampMs || ms > track.last().timestampMs) return null
        val i = track.indexOfLast { it.timestampMs <= ms }.coerceIn(0, track.size - 2)
        val a = track[i]
        val b = track[i + 1]
        val span = (b.timestampMs - a.timestampMs).toDouble()
        // A gap of more than a few frames is a lost ball, not a straight line.
        if (span > 140.0) return null
        val f = if (span <= 0) 0.0 else ((ms - a.timestampMs) / span).coerceIn(0.0, 1.0)
        return (a.x + (b.x - a.x) * f).toFloat() to (a.y + (b.y - a.y) * f).toFloat()
    }

    /** [track] moved by [byMs] on the clock. */
    fun shifted(track: List<BallSighting>, byMs: Double): List<BallSighting> =
        track.map { it.copy(timestampMs = it.timestampMs + Math.round(byMs)) }
}
