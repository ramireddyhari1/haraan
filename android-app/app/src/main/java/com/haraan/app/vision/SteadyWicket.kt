package com.haraan.app.vision

import kotlin.math.abs

/**
 * THE BATTER'S STUMPS FOR MEASURING, NOT FOR DRAWING.
 *
 * The lock follows the stumps frame by frame, which is right for the overlay — and wrong for
 * the ground the 3D flight is fitted through. 22 m off the far set is about ten pixels wide,
 * and on a 120 fps net clip (2026-10-11) its measured span swung by a quarter between
 * deliveries while the batter stood in front of it; every percent of span is a percent of
 * distance, and the camera's solved height went from 1.2 m to 2.0 m and back on a phone on a
 * stand. Measurements are taken from this instead: the medians of the lock's corners over
 * the recent CONFIRMED frames, kept while nothing settles somewhere else.
 *
 * It is also what the delivery corridor is built on. The bowler running through the picture
 * drops the live lock at exactly the moment the ball is let go, and a corridor that went with
 * it left the tracker searching the whole frame — where it followed the bowler's feet.
 */
class SteadyWicket(private val keep: Int = KEEP) {
    private val seen = ArrayDeque<WicketAnchor>()
    private var latest: WicketLock? = null

    private var lastConfirmedMs = Long.MIN_VALUE

    /** Where the bowler's stumps stood while these were held, and when they were last seen there. */
    private var nearRef: BowlersEnd? = null
    /** Null until they are seen: Long.MIN_VALUE here overflowed `now - it` and nothing was ever forgotten. */
    private var nearSameMs: Long? = null
    private var nearMovedStreak = 0

    /**
     * Folds in this frame's lock (checked at [nowMs] on the camera's clock); returns the steady
     * one to measure with, or null. No lock at all for [STALE_MS] — the phone picked up, the
     * stumps carried off — lets the steady one go too, unless [near], the bowler's stumps as
     * seen THIS frame (null when not seen), is still where it stood.
     */
    fun add(lock: WicketLock?, nowMs: Long, near: BowlersEnd? = null): WicketLock? {
        /*
         * THE BOWLER'S STUMPS SAY WHETHER THE PHONE HAS MOVED. A batter standing right in
         * front of his stumps hides them completely, and for longer than [STALE_MS] while he
         * takes guard: the batter's stumps were forgotten and the next ball was not tracked
         * (bench, IMG_6053 covered 8 s). While the bowler's set is still where it was, the
         * phone has not moved and nothing has carried the batter's set off, so it is kept for
         * as long as he stands there. If the bowler's set has shifted, the phone moved: the
         * held place is wrong now, and is dropped at once rather than after the wait.
         */
        if (near != null && latest != null) {
            val ref = nearRef
            if (ref == null) {
                nearRef = near
                nearSameMs = nowMs
            } else if (abs(near.base.x - ref.base.x) > ref.spanFw * NEAR_MOVED_SPANS ||
                abs(near.base.y - ref.base.y) > ref.spanFw * NEAR_MOVED_SPANS
            ) {
                // One sighting off can be the bowler's leg across them; several in a row is the phone.
                if (++nearMovedStreak >= NEAR_MOVED_SIGHTINGS) clear()
            } else {
                nearMovedStreak = 0
                nearSameMs = nowMs
            }
        }
        if (lock != null && lock.kind == WicketKind.STUMPS && lock.state == WicketTrackState.CONFIRMED &&
            lock.heldFrames >= MIN_HELD_FRAMES
        ) {
            lastConfirmedMs = nowMs
            latest = lock
            // A lock confirmed on something else — half as wide again — is not folded in.
            val typical = if (seen.size >= MIN_SIGHTINGS) medianOf(seen).span(1f) else null
            val span = lock.anchor.span(1f)
            if (typical != null && abs(span - typical) > typical * MAX_SPAN_CHANGE && !newPlace(lock.anchor)) return value()
            seen.addLast(lock.anchor)
            while (seen.size > keep) seen.removeFirst()
            moved()
        } else if (lock == null && latest != null && nowMs - lastConfirmedMs > STALE_MS && nearSameMs.let { it == null || nowMs - it > STALE_MS }) {
            // Gone, not just hidden: a batter standing in front keeps the lock coasting for
            // as long as they stand there, and that is no reason to forget the stumps.
            clear()
        }
        return value()
    }

    fun value(): WicketLock? {
        val l = latest ?: return null
        if (seen.size < MIN_SIGHTINGS) return l
        return l.copy(anchor = medianOf(seen))
    }

    fun clear() {
        seen.clear()
        latest = null
        nearRef = null
        nearSameMs = null
        nearMovedStreak = 0
    }

    /** The phone was moved: the latest anchors agree with each other and not with the rest. */
    private fun moved() {
        if (seen.size <= MOVED_RUN * 2) return
        val run = seen.toList().takeLast(MOVED_RUN)
        val r = medianOf(run)
        val all = medianOf(seen)
        val span = r.span(1f).toDouble().coerceAtLeast(1e-4)
        val agree = run.count { abs(it.base.x - r.base.x) <= span && abs(it.base.y - r.base.y) <= span }
        if (agree >= MOVED_RUN - 1 && (abs(r.base.x - all.base.x) > span * 2 || abs(r.base.y - all.base.y) > span * 2)) {
            seen.clear()
            seen.addAll(run)
        }
    }

    /**
     * The median centre, width and slope, put back together: medians of each corner taken
     * apart gave a width none of the sightings had (0.0129 from locks all near 0.0136).
     */
    private fun medianOf(a: Collection<WicketAnchor>): WicketAnchor {
        fun m(v: List<Double>) = v.sorted().let { s -> if (s.size % 2 == 1) s[s.size / 2] else (s[s.size / 2 - 1] + s[s.size / 2]) / 2.0 }
        val cx = m(a.map { it.base.x })
        val cy = m(a.map { it.base.y })
        val dx = m(a.map { it.baseRight.x - it.baseLeft.x }) / 2.0
        val dy = m(a.map { it.baseRight.y - it.baseLeft.y }) / 2.0
        val tops = a.mapNotNull { t -> t.top?.let { Point2(it.x - t.base.x, it.y - t.base.y) } }
        return WicketAnchor(
            baseLeft = Point2(cx - dx, cy - dy),
            baseRight = Point2(cx + dx, cy + dy),
            top = if (tops.size * 2 >= a.size) Point2(cx + m(tops.map { it.x }), cy + m(tops.map { it.y })) else null,
        )
    }

    /** Whether [anchor] stands well away from where the stumps have been: a moved phone. */
    private fun newPlace(anchor: WicketAnchor): Boolean {
        val all = medianOf(seen)
        val span = all.span(1f).toDouble().coerceAtLeast(1e-4)
        return abs(anchor.base.x - all.base.x) > span * 2 || abs(anchor.base.y - all.base.y) > span * 2
    }

    companion object {
        const val KEEP = 90
        const val MIN_SIGHTINGS = 5
        const val MIN_HELD_FRAMES = 15
        const val MOVED_RUN = 8
        const val STALE_MS = 10_000L
        /** The bowler's stumps this far off where they stood (in their own spans): the phone moved. */
        const val NEAR_MOVED_SPANS = 0.5
        const val NEAR_MOVED_SIGHTINGS = 5
        /** A width this far (as a share) from the usual is another object, not these stumps. */
        const val MAX_SPAN_CHANGE = 0.25f
    }
}
