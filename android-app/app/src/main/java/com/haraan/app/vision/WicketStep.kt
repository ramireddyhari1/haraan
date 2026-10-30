package com.haraan.app.vision

import com.haraan.app.camera.battersEndOnly
import com.haraan.app.camera.battersEndWithPredicted
import com.haraan.app.camera.bowlersEndOf

/**
 * THE STUMPS, FRAME BY FRAME — the one copy of what is done with a detector's sightings.
 *
 * Shared by the live camera screen and [DeliveryChain] for the same reason as [BallStep]:
 * the bowler's stumps found and smoothed, the batter's kept (and looked for where the
 * bowler's say they must be), the wicket tracker stepped, and its lock steadied. How often
 * the detector runs and how hard it searches stay with each caller — that is the live
 * screen's heat budget, not a different idea of where the stumps are.
 */
object WicketStep {

    class Result(
        val lock: WicketLock?,
        /** The bowler's stumps seen on THIS frame, if any. */
        val nearNow: BowlersEnd?,
        /** The smoothed bowler's stumps, as now known. */
        val bowlersEnd: BowlersEnd?,
    )

    fun feed(
        sightings: List<WicketSighting>,
        camera: CameraIntrinsics?,
        bowlersEnd: BowlersEnd?,
        bowlersEndFilter: BowlersEndFilter,
        farSearch: FarWicketSearch,
        wickets: WicketTracker,
        steady: SteadyWicket,
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        motion: FrameMotion,
        aspect: Float,
        timestampMs: Long,
        /** Look for the batter's stumps where the bowler's say ([FarWicketSearch]). Off only to compare on the bench. */
        predictFar: Boolean = true,
    ): Result {
        val nearNow = bowlersEndOf(sightings, camera)
        val near = nearNow?.let { bowlersEndFilter.add(it) ?: bowlersEnd } ?: bowlersEnd
        val far = if (predictFar) {
            battersEndWithPredicted(
                farSearch, sightings, wickets.lock(), near ?: nearNow, camera,
                luma, width, height, rowStride, rotationDegrees, aspect,
            )
        } else {
            battersEndOnly(sightings, camera, near)
        }
        val lock = wickets.onFrame(far, motion, aspect, timestampMs)
        steady.add(lock, timestampMs, nearNow)
        return Result(lock, nearNow, near)
    }
}
