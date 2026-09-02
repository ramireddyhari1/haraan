package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs

/**
 * The homography, and the guards around it.
 *
 * This is the layer that turns a dot in a picture into a distance in metres, so it is the
 * one place in the vision pipeline where "correct" has an exact answer that can be checked
 * without a cricket field. Every test here either proves the arithmetic recovers a known
 * value, or proves the solver refuses input it cannot honestly handle.
 */
class PitchGeometryTest {

    private fun assertClose(expected: Double, actual: Double, tolerance: Double = 1e-6) {
        assertTrue(
            "expected $expected but was $actual",
            abs(expected - actual) < tolerance,
        )
    }

    /**
     * A believable view from behind the bowler: the pitch narrows with distance, the near
     * crease is low and wide, the far crease high and narrow.
     */
    private fun cameraQuad() = listOf(
        Point2(0.18, 0.74), // near-left
        Point2(0.82, 0.74), // near-right
        Point2(0.575, 0.36), // far-right
        Point2(0.425, 0.36), // far-left
    )

    // ── The arithmetic ──────────────────────────────────────────────────────────

    /** The four corners must land exactly on the four real-world corners. */
    @Test
    fun corners_map_onto_the_known_pitch_rectangle() {
        val h = Homography.solve(cameraQuad(), PitchGeometry.CALIBRATION_CORNERS_M)
        assertNotNull(h)

        cameraQuad().forEachIndexed { i, imagePoint ->
            val expected = PitchGeometry.CALIBRATION_CORNERS_M[i]
            val actual = h!!.map(imagePoint)
            assertClose(expected.x, actual.x, 1e-6)
            assertClose(expected.y, actual.y, 1e-6)
        }
    }

    /**
     * The centre of the calibration rectangle is not the centre of the image quad — that
     * is the whole point of a projective map rather than a linear one. Mapping back must
     * still land where it started.
     */
    @Test
    fun mapping_there_and_back_returns_the_original_point() {
        val quad = PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f)
        val toPitch = quad.toPitch()!!
        val toImage = quad.toImage()!!

        listOf(
            Point2(0.5, 0.5),
            Point2(0.45, 0.62),
            Point2(0.6, 0.41),
        ).forEach { original ->
            val roundTrip = toImage.map(toPitch.map(original))
            assertClose(original.x, roundTrip.x, 1e-6)
            assertClose(original.y, roundTrip.y, 1e-6)
        }
    }

    /**
     * The measurement this whole pipeline exists for: a bounce point in the frame becomes
     * a length in metres from the striker's stumps.
     *
     * The bounce is ON the pitch plane, which is why this is exact rather than an estimate.
     */
    @Test
    fun a_bounce_on_the_centre_line_reads_as_a_length_in_metres() {
        val quad = PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f)
        val toPitch = quad.toPitch()!!

        // Halfway down the near edge's centre line, in image space.
        val nearCentre = Point2(0.5, 0.74)
        val farCentre = Point2(0.5, 0.36)

        val nearMetres = toPitch.map(nearCentre)
        val farMetres = toPitch.map(farCentre)

        // The near crease is 1.22 m in front of the striker's stumps, so y is negative.
        assertClose(-PitchGeometry.POPPING_CREASE_AHEAD_M, nearMetres.y, 1e-6)
        assertClose(0.0, nearMetres.x, 1e-6)

        // The far crease is the full calibration length away.
        assertClose(
            PitchGeometry.CALIBRATION_LENGTH_M - PitchGeometry.POPPING_CREASE_AHEAD_M,
            farMetres.y,
            1e-6,
        )
    }

    /** Perspective is real: equal steps in the image are not equal steps on the ground. */
    @Test
    fun equal_image_steps_are_unequal_ground_distances() {
        val toPitch = PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.9f).toPitch()!!

        val a = toPitch.map(Point2(0.5, 0.70)).y
        val b = toPitch.map(Point2(0.5, 0.60)).y
        val c = toPitch.map(Point2(0.5, 0.50)).y

        val nearStep = b - a
        val farStep = c - b

        // The same ten pixels covers more ground further away. If these came out equal the
        // map would be an affine stretch and every length beyond the crease would be wrong.
        assertTrue("expected $farStep > $nearStep", farStep > nearStep * 1.2)
    }

    // ── Refusals ────────────────────────────────────────────────────────────────

    /**
     * Three collinear points cannot define a plane. The solver must say so rather than
     * return a matrix of enormous numbers that maps everything into nonsense.
     */
    @Test
    fun collinear_points_produce_no_homography() {
        val collinear = listOf(
            Point2(0.1, 0.5),
            Point2(0.3, 0.5),
            Point2(0.5, 0.5),
            Point2(0.7, 0.5),
        )
        assertNull(Homography.solve(collinear, PitchGeometry.CALIBRATION_CORNERS_M))
    }

    @Test
    fun coincident_points_produce_no_homography() {
        val duplicated = listOf(
            Point2(0.2, 0.8),
            Point2(0.2, 0.8),
            Point2(0.6, 0.4),
            Point2(0.4, 0.4),
        )
        assertNull(Homography.solve(duplicated, PitchGeometry.CALIBRATION_CORNERS_M))
    }

    @Test
    fun the_wrong_number_of_points_is_refused() {
        val three = cameraQuad().take(3)
        assertNull(Homography.solve(three, PitchGeometry.CALIBRATION_CORNERS_M))
    }

    /**
     * Above the far crease the pitch plane meets the camera's horizon, and points there
     * project to infinite distance.
     *
     * Two earlier versions of this test were wrong in instructive ways. The first picked
     * y = -5.0 and asserted it blew up — a guess about where the horizon sits, and it sits
     * nowhere near there. The second scanned for a huge magnitude, which made the result
     * depend on whether a sample happened to land close enough to the singularity.
     *
     * What is actually step-independent is the SIGNATURE of crossing a horizon: the mapped
     * distance runs away to a large positive value, flips sign, and comes back from a
     * large negative one. Passing through zero would be an ordinary crossing; passing
     * through infinity is this.
     *
     * For this quad the horizon is at about y = 0.24, only a tenth of the frame above the
     * far crease — which is worth knowing, because it means anything detected just above
     * the pitch maps to a distance no renderer should be asked to plot.
     */
    @Test
    fun distances_diverge_through_the_horizon_rather_than_passing_through_zero() {
        val h = Homography.solve(cameraQuad(), PitchGeometry.CALIBRATION_CORNERS_M)!!

        var previous: Double? = null
        var crossedThroughInfinity = false

        var y = 0.36
        while (y > 0.0) {
            val mapped = h.map(Point2(0.5, y)).y
            if (mapped.isNaN() || !mapped.isFinite()) {
                crossedThroughInfinity = true
                break
            }
            previous?.let { before ->
                val flipped = (before > 0) != (mapped > 0)
                if (flipped && abs(before) > 100 && abs(mapped) > 100) {
                    crossedThroughInfinity = true
                }
            }
            if (crossedThroughInfinity) break
            previous = mapped
            y -= 0.001
        }

        assertTrue(
            "the map should diverge through a horizon above the far crease",
            crossedThroughInfinity,
        )
    }

    // ── Plausibility, before anything is solved ─────────────────────────────────

    @Test
    fun a_believable_quad_is_accepted() {
        assertTrue(PitchQuad(cameraQuad(), QuadSource.DETECTED, 0.8f).isPlausible())
    }

    /**
     * Seen from behind the bowler the far crease MUST look narrower. A quad where it does
     * not is not a pitch — most likely a boundary rope or a sightscreen edge.
     */
    @Test
    fun a_quad_whose_far_edge_is_wider_is_rejected() {
        val inverted = listOf(
            Point2(0.42, 0.74),
            Point2(0.58, 0.74),
            Point2(0.82, 0.36),
            Point2(0.18, 0.36),
        )
        assertFalse(PitchQuad(inverted, QuadSource.DETECTED, 0.9f).isPlausible())
    }

    @Test
    fun a_flat_sliver_is_rejected() {
        val sliver = listOf(
            Point2(0.20, 0.500),
            Point2(0.80, 0.500),
            Point2(0.55, 0.495),
            Point2(0.45, 0.495),
        )
        assertFalse(PitchQuad(sliver, QuadSource.DETECTED, 0.9f).isPlausible())
    }

    @Test
    fun nan_corners_are_rejected() {
        val broken = cameraQuad().toMutableList().also { it[2] = Point2(Double.NaN, 0.4) }
        assertFalse(PitchQuad(broken, QuadSource.DETECTED, 0.9f).isPlausible())
    }

    // ── The dimensions themselves ───────────────────────────────────────────────

    /**
     * These come from the Laws, and everything measured downstream is scaled by them. A
     * typo here would silently make every length wrong by a fixed factor — the kind of
     * error that looks plausible on screen forever.
     */
    @Test
    fun the_calibration_rectangle_matches_the_laws() {
        assertEquals(2.64, PitchGeometry.CALIBRATION_WIDTH_M, 1e-9)
        assertEquals(17.68, PitchGeometry.CALIBRATION_LENGTH_M, 1e-9)
        assertEquals(4, PitchGeometry.CALIBRATION_CORNERS_M.size)

        // The rectangle is symmetric about the middle-stump line.
        val xs = PitchGeometry.CALIBRATION_CORNERS_M.map { it.x }
        assertEquals(0.0, xs.sum(), 1e-9)
    }

    @Test
    fun a_tapped_quad_records_that_a_person_placed_it() {
        val tapped = PitchQuad(cameraQuad(), QuadSource.TAPPED, 1.0f)

        assertEquals(QuadSource.TAPPED, tapped.source)
        assertNotNull(tapped.toPitch())
    }
}
