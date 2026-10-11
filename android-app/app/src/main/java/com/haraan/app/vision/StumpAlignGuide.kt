package com.haraan.app.vision

import kotlin.math.atan
import kotlin.math.hypot
import kotlin.math.tan

/**
 * WHERE THE STUMPS SHOULD BE, for a phone set up the standard way.
 *
 * Ball-tracking apps that feel instant (Fulltrack AI's own setup guide: a tripod about 1.3 m
 * high, about 4 m behind the bowler's stumps, both sets of stumps lined up inside boxes on
 * the screen) do not search the picture for the wickets — the operator puts them where the
 * app expects them. Searching took about five seconds on a real net clip and flickered every
 * time the bowler ran through; lining them up takes as long as moving the tripod.
 *
 * This works out the two boxes for that placement through a pinhole camera of the phone's
 * own lens, tilted so the batter's stumps stand at [FAR_BASE_Y] of the picture's height.
 * Coordinates are normalised upright frame (0..1 across, 0..1 down).
 */
object StumpAlignGuide {
    /** One set of stumps where it should appear: the base line's ends and the top's height. */
    data class Box(val baseLeft: Point2, val baseRight: Point2, val topY: Double) {
        val baseY: Double get() = (baseLeft.y + baseRight.y) / 2.0
        val centreX: Double get() = (baseLeft.x + baseRight.x) / 2.0
        val width: Double get() = baseRight.x - baseLeft.x

        /** The box with room round it for a phone set up near enough, as (left, top, right, bottom). */
        fun padded(across: Double, down: Double): DoubleArray {
            val h = baseY - topY
            return doubleArrayOf(
                centreX - width / 2 - across,
                topY - h * down,
                centreX + width / 2 + across,
                baseY + h * down,
            )
        }

        fun contains(p: Point2, across: Double, down: Double): Boolean {
            val r = padded(across, down)
            return p.x in r[0]..r[2] && p.y in r[1]..r[3]
        }
    }

    data class Guide(val far: Box, val near: Box)

    /**
     * The two sets for a lens of [focalFw] (focal length in frame widths) and a frame
     * [aspect] (width over height), with the phone [heightM] up and [behindM] behind the
     * bowler's stumps, looking straight down the pitch.
     */
    fun guide(
        focalFw: Double,
        aspect: Float,
        heightM: Double = TRIPOD_HEIGHT_M,
        behindM: Double = BEHIND_STUMPS_M,
    ): Guide {
        val a = if (aspect > 0f) aspect.toDouble() else 9.0 / 16.0
        val nearD = behindM
        val farD = behindM + PitchGeometry.STUMPS_TO_STUMPS_M
        // Tilted down so the far stumps' foot sits at FAR_BASE_Y.
        val tilt = atan(heightM / farD) - atan((FAR_BASE_Y - 0.5) / (focalFw * a))
        fun y(depth: Double, z: Double) = 0.5 + focalFw * a * tan(atan((heightM - z) / depth) - tilt)
        fun box(depth: Double): Box {
            val halfWidth = focalFw * (PitchGeometry.STUMP_SET_WIDTH_M / 2.0) / hypot(depth, heightM)
            val base = y(depth, 0.0)
            return Box(
                baseLeft = Point2(0.5 - halfWidth, base),
                baseRight = Point2(0.5 + halfWidth, base),
                topY = y(depth, PitchGeometry.STUMP_HEIGHT_M),
            )
        }
        return Guide(far = box(farD), near = box(nearD))
    }

    /** Fulltrack's own advice, and a sensible net set-up. */
    const val TRIPOD_HEIGHT_M = 1.3
    const val BEHIND_STUMPS_M = 4.0

    /** The batter's stumps' foot, as a share of the picture's height from the top. */
    const val FAR_BASE_Y = 0.48
}
