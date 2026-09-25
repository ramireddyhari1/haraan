package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class StumpImpactDetectorTest {

    private fun stumpAnchor(baseX: Double = 0.5, baseY: Double = 0.8, topX: Double = 0.5, topY: Double = 0.6) =
        WicketAnchor(
            baseLeft = Point2(baseX - 0.02, baseY),
            baseRight = Point2(baseX + 0.02, baseY),
            top = Point2(topX, topY),
        )

    @Test
    fun `initial state is NO_IMPACT`() {
        val detector = StumpImpactDetector()
        val assessment = detector.onFrame(stumpAnchor(), timestampMs = 1000L)
        assertEquals(StumpImpactState.NO_IMPACT, assessment.state)
        assertEquals(0f, assessment.confidence, 0.001f)
    }

    @Test
    fun `stationary anchor across frames produces NO_IMPACT`() {
        val detector = StumpImpactDetector()
        val anchor = stumpAnchor()
        repeat(5) { i ->
            val assessment = detector.onFrame(anchor, timestampMs = 1000L + i * 33L)
            assertEquals(StumpImpactState.NO_IMPACT, assessment.state)
            assertTrue(assessment.confidence < 0.2f)
        }
    }

    @Test
    fun `single small jitter produces NO_IMPACT without false alarm`() {
        val detector = StumpImpactDetector()
        detector.onFrame(stumpAnchor(), timestampMs = 1000L)

        // Micro-jitter: 0.002 displacement
        val jitterAnchor = stumpAnchor(baseX = 0.502, baseY = 0.801)
        val assessment = detector.onFrame(jitterAnchor, timestampMs = 1033L)

        assertEquals(StumpImpactState.NO_IMPACT, assessment.state)
        assertTrue(assessment.confidence < 0.4f)
    }

    @Test
    fun `moderate disruption enters POSSIBLE_IMPACT before CONFIRMED_IMPACT`() {
        val detector = StumpImpactDetector(confirmationFramesRequired = 2)
        val baseline = stumpAnchor()
        detector.onFrame(baseline, timestampMs = 1000L)

        // Moderate tilt/displacement (e.g. slight ball nick)
        val moved = stumpAnchor(baseX = 0.51, baseY = 0.80, topX = 0.53, topY = 0.60)
        val firstMove = detector.onFrame(moved, timestampMs = 1033L)

        assertEquals(StumpImpactState.POSSIBLE_IMPACT, firstMove.state)
        assertTrue(firstMove.confidence >= 0.60f)

        // Sustained disruption over second frame promotes to CONFIRMED
        val secondMove = detector.onFrame(moved, timestampMs = 1066L)
        assertEquals(StumpImpactState.CONFIRMED_IMPACT, secondMove.state)
    }

    @Test
    fun `multi-signal violent collision with ball proximity transitions immediately to CONFIRMED_IMPACT`() {
        val detector = StumpImpactDetector()
        val baseline = stumpAnchor()
        detector.onFrame(baseline, timestampMs = 1000L)

        // Ball right at stump top + major tumble displacement (tilted 25 degrees)
        val smashed = stumpAnchor(baseX = 0.54, baseY = 0.82, topX = 0.62, topY = 0.65)
        val ballAtStump = Point2(0.61, 0.64)

        val assessment = detector.onFrame(smashed, ballPosition = ballAtStump, timestampMs = 1033L)

        assertEquals(StumpImpactState.CONFIRMED_IMPACT, assessment.state)
        assertTrue(assessment.confidence > 0.85f)
        assertNotNull(assessment.ballProximityDistance)
    }

    @Test
    fun `reset clears state back to resting baseline`() {
        val detector = StumpImpactDetector()
        val baseline = stumpAnchor()
        detector.onFrame(baseline, timestampMs = 1000L)

        val smashed = stumpAnchor(baseX = 0.6, baseY = 0.85, topX = 0.7, topY = 0.7)
        detector.onFrame(smashed, Point2(0.6, 0.7), timestampMs = 1033L)

        detector.reset()
        val freshAssessment = detector.onFrame(stumpAnchor(0.4, 0.8, 0.4, 0.6), timestampMs = 2000L)

        assertEquals(StumpImpactState.NO_IMPACT, freshAssessment.state)
        assertEquals(0f, freshAssessment.displacementMagnitude, 0.001f)
    }
}
