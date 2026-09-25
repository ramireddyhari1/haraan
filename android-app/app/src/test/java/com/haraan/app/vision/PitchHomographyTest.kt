package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PitchHomographyTest {

    @Test
    fun `standard perspective pitch calibration maps crease points accurately`() {
        // Perspective trapezoid in image frame
        val ref = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.20, 0.85),
            bowlingCreaseRight = Point2(0.80, 0.85),
            poppingCreaseLeft = Point2(0.28, 0.65),
            poppingCreaseRight = Point2(0.72, 0.65),
        )

        val result = PitchHomography.fromCalibration(ref)
        assertTrue("Calibration must succeed for valid trapezoid", result.isSuccess)

        val homography = result.getOrThrow()

        // 1. Project bowling crease left
        val projBcl = homography.toGround(Point2(0.20, 0.85))
        assertTrue(projBcl is HomographyProjection.GroundSurface)
        val gBcl = (projBcl as HomographyProjection.GroundSurface).groundPoint
        assertEquals(-1.32, gBcl.xMeters, 0.05)
        assertEquals(0.0, gBcl.yMeters, 0.05)

        // 2. Project popping crease right
        val projPcr = homography.toGround(Point2(0.72, 0.65))
        assertTrue(projPcr is HomographyProjection.GroundSurface)
        val gPcr = (projPcr as HomographyProjection.GroundSurface).groundPoint
        assertEquals(1.32, gPcr.xMeters, 0.05)
        assertEquals(1.2192, gPcr.yMeters, 0.05)

        // 3. Inverse projection round-trip
        val imgBack = homography.toImage(gPcr)
        assertNotNull(imgBack)
        assertEquals(0.72, imgBack!!.x, 0.01)
        assertEquals(0.65, imgBack.y, 0.01)
    }

    @Test
    fun `front foot no-ball check correctly identifies landing behind or across popping crease`() {
        val ref = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.20, 0.85),
            bowlingCreaseRight = Point2(0.80, 0.85),
            poppingCreaseLeft = Point2(0.28, 0.65),
            poppingCreaseRight = Point2(0.72, 0.65),
        )
        val homography = PitchHomography.fromCalibration(ref).getOrThrow()

        // Legal landing: 0.9m from bowling crease (behind 1.2192m popping crease)
        val legalLanding = GroundPoint(0.1, 0.90)
        assertTrue(homography.isFrontFootBehindPoppingCrease(legalLanding))

        // No-ball landing: 1.35m from bowling crease (past 1.2192m popping crease)
        val noBallLanding = GroundPoint(0.1, 1.35)
        assertFalse(homography.isFrontFootBehindPoppingCrease(noBallLanding))
    }

    @Test
    fun `airborne points produce explicit AirborneRayProjectionWarning`() {
        val ref = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.20, 0.85),
            bowlingCreaseRight = Point2(0.80, 0.85),
            poppingCreaseLeft = Point2(0.28, 0.65),
            poppingCreaseRight = Point2(0.72, 0.65),
        )
        val homography = PitchHomography.fromCalibration(ref).getOrThrow()

        // Elevated bowler hand or flying ball
        val elevatedHand = Point2(0.50, 0.30)
        val proj = homography.toGround(elevatedHand, isKnownAirborne = true)

        assertTrue("Airborne points must not be returned as raw ground contacts",
            proj is HomographyProjection.AirborneRayProjectionWarning)
    }

    @Test
    fun `degenerate collinear points are rejected with failure`() {
        // Collinear points along horizontal line
        val collinearRef = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.20, 0.80),
            bowlingCreaseRight = Point2(0.40, 0.80),
            poppingCreaseLeft = Point2(0.60, 0.80),
            poppingCreaseRight = Point2(0.80, 0.80),
        )

        val result = PitchHomography.fromCalibration(collinearRef)
        assertTrue("Collinear points must be rejected", result.isFailure)
    }

    @Test
    fun `duplicate points are rejected with failure`() {
        val duplicateRef = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.50, 0.50),
            bowlingCreaseRight = Point2(0.50, 0.50),
            poppingCreaseLeft = Point2(0.30, 0.70),
            poppingCreaseRight = Point2(0.70, 0.70),
        )

        val result = PitchHomography.fromCalibration(duplicateRef)
        assertTrue("Duplicate points must be rejected", result.isFailure)
    }
}
