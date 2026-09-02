package com.haraan.app.vision

import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The corridor the camera screen paints inside a detected pitch.
 *
 * That band is drawn by placing four points in METRES and pushing them back through the
 * homography, which is the whole reason it is worth drawing: if the calibration is wrong,
 * the corridor visibly sits crooked on the grass. But that only holds if the arithmetic
 * behind it is right, and a wrong corridor that still looks like a trapezium would be a
 * convincing lie on screen.
 *
 * So the shape is checked here, where the expected answer is known, rather than trusted
 * because it looked plausible in a viewfinder.
 */
class PitchOverlayTest {

    /** A believable view from behind the bowler's arm. */
    private fun cameraQuad() = listOf(
        Point2(0.18, 0.74),
        Point2(0.82, 0.74),
        Point2(0.575, 0.36),
        Point2(0.425, 0.36),
    )

    /** The same four points the camera screen builds its band from. */
    private fun band(quad: PitchQuad): List<Point2> {
        val toImage = quad.toImage()!!
        val half = PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * 0.35
        val near = -PitchGeometry.POPPING_CREASE_AHEAD_M
        val far = PitchGeometry.CALIBRATION_LENGTH_M + near

        return listOf(
            Point2(-half, near),
            Point2(half, near),
            Point2(half, far),
            Point2(-half, far),
        ).map { toImage.map(it) }
    }

    /**
     * The corridor is a strip down the middle of the pitch, so every corner of it must fall
     * inside the pitch outline. A band that spilled over the edge would be drawn over the
     * grass beside the strip and would look, to anyone aiming the phone, like the app had
     * mis-found the pitch.
     */
    @Test
    fun the_corridor_sits_inside_the_pitch_outline() {
        val quad = PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f)
        val outline = quad.corners

        band(quad).forEach { corner ->
            assertTrue("$corner escaped the pitch outline", inside(corner, outline))
        }
    }

    /**
     * Perspective again: the far end of a corridor of constant real width must appear
     * narrower. If these came out equal the band would be drawn in screen space, which is
     * exactly the mistake this rendering exists to avoid.
     */
    @Test
    fun the_corridor_narrows_with_distance() {
        val corners = band(PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f))

        val nearWidth = corners[1].x - corners[0].x
        val farWidth = corners[2].x - corners[3].x

        assertTrue("near width was $nearWidth", nearWidth > 0)
        assertTrue("expected $farWidth to be well under $nearWidth", farWidth < nearWidth * 0.6)
    }

    /**
     * The band's near edge must sit on the near crease and its far edge on the far one:
     * it spans the same length as the calibration rectangle, only narrower. This catches a
     * sign flip on the popping-crease offset, which would otherwise draw a corridor that
     * started a metre behind the batter and still looked fine.
     */
    @Test
    fun the_corridor_spans_the_full_pitch_between_the_creases() {
        val quad = PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f)
        val corners = band(quad)

        val nearCreaseY = quad.corners[0].y
        val farCreaseY = quad.corners[2].y

        assertTrue(kotlin.math.abs(corners[0].y - nearCreaseY) < 1e-6)
        assertTrue(kotlin.math.abs(corners[1].y - nearCreaseY) < 1e-6)
        assertTrue(kotlin.math.abs(corners[2].y - farCreaseY) < 1e-6)
        assertTrue(kotlin.math.abs(corners[3].y - farCreaseY) < 1e-6)
    }

    /**
     * A quad the detector should never have accepted still must not paint anything
     * confident. The screen skips the band when any corner comes back NaN, so this proves
     * NaN is actually what an impossible calibration produces.
     */
    @Test
    fun a_degenerate_quad_yields_no_drawable_corridor() {
        val flat = PitchQuad(
            listOf(
                Point2(0.1, 0.5),
                Point2(0.3, 0.5),
                Point2(0.5, 0.5),
                Point2(0.7, 0.5),
            ),
            QuadSource.DETECTED,
            0.9f,
        )
        assertTrue(flat.toImage() == null)
    }

    /** Winding-number-free point-in-convex-polygon: all cross products share a sign. */
    private fun inside(point: Point2, polygon: List<Point2>): Boolean {
        var positive = false
        var negative = false
        for (i in polygon.indices) {
            val a = polygon[i]
            val b = polygon[(i + 1) % polygon.size]
            val cross = (b.x - a.x) * (point.y - a.y) - (b.y - a.y) * (point.x - a.x)
            if (cross > 1e-9) positive = true
            if (cross < -1e-9) negative = true
        }
        return !(positive && negative)
    }
}
