package com.haraan.app.vision.replay

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The scoring rules, which are the only opinionated part of the labelling tool.
 *
 * Everything else in it just records taps. These rules decide what "accurate" means, and
 * they are exactly where a measurement quietly starts flattering the thing it measures —
 * so the two decisions that could do that are pinned down first: unreviewed frames are
 * never scored, and a confidently wrong detection is penalised twice.
 */
class LabelScoringTest {

    private val aspect16x9 = LabelSet("clip", 1920, 1080, emptyList())

    private fun set(vararg labels: FrameLabel) = aspect16x9.copy(labels = labels.toList())
    private fun ball(frame: Int, x: Float, y: Float) = FrameLabel.Ball(frame, frame * 33L, x, y)
    private fun absent(frame: Int) = FrameLabel.Absent(frame, frame * 33L)

    @Test
    fun `a detection on the ball is a true positive`() {
        val score = LabelScoring.score(
            set(ball(1, 0.50f, 0.50f)),
            mapOf(1 to (0.505f to 0.50f)),
        )

        assertEquals(1, score.truePositives)
        assertEquals(0, score.falsePositives)
        assertEquals(0, score.falseNegatives)
        assertEquals(1.0, score.precision!!, 1e-9)
        assertEquals(1.0, score.recall!!, 1e-9)
    }

    @Test
    fun `a missed ball is a false negative and nothing else`() {
        val score = LabelScoring.score(set(ball(1, 0.5f, 0.5f)), emptyMap())

        assertEquals(0, score.truePositives)
        assertEquals(0, score.falsePositives)
        assertEquals(1, score.falseNegatives)
        // Nothing was claimed, so precision has no sample to be computed from. Printing
        // 0% there would punish silence exactly as hard as a wrong answer.
        assertNull(score.precision)
        assertEquals(0.0, score.recall!!, 1e-9)
    }

    @Test
    fun `a detection in a frame confirmed empty is a false positive`() {
        val score = LabelScoring.score(set(absent(1)), mapOf(1 to (0.3f to 0.3f)))

        assertEquals(0, score.truePositives)
        assertEquals(1, score.falsePositives)
        assertEquals(0, score.trueNegatives)
        assertEquals(0.0, score.precision!!, 1e-9)
    }

    @Test
    fun `silence in a frame confirmed empty is a true negative`() {
        val score = LabelScoring.score(set(absent(1), absent(2)), emptyMap())

        assertEquals(2, score.trueNegatives)
        assertEquals(0, score.falsePositives)
        assertNull(score.precision)
        assertNull(score.recall)
    }

    @Test
    fun `a confidently wrong detection is penalised twice`() {
        // It claimed a ball where there was none AND missed the one that was there. This
        // is the arm-tracking case, and a scheme that counted it once would rate it the
        // same as a detector that honestly reported nothing.
        val score = LabelScoring.score(
            set(ball(1, 0.20f, 0.80f)),
            mapOf(1 to (0.60f to 0.20f)),
        )

        assertEquals(0, score.truePositives)
        assertEquals(1, score.falsePositives)
        assertEquals(1, score.falseNegatives)
        assertEquals(0.0, score.precision!!, 1e-9)
        assertEquals(0.0, score.recall!!, 1e-9)
    }

    @Test
    fun `frames nobody reviewed are never scored`() {
        // Frame 2 has a detection and no label. Counting it either way would be inventing
        // evidence; forgiving it quietly is how precision gets flattered.
        val score = LabelScoring.score(
            set(ball(1, 0.5f, 0.5f)),
            mapOf(1 to (0.5f to 0.5f), 2 to (0.9f to 0.9f), 3 to (0.1f to 0.1f)),
        )

        assertEquals(1, score.reviewedFrames)
        assertEquals(1, score.truePositives)
        assertEquals(0, score.falsePositives)
    }

    @Test
    fun `the tolerance is measured in frame widths, not in normalised units`() {
        // 0.03 of the HEIGHT on a 16:9 frame is 0.0169 of the width — inside a 0.02
        // tolerance. Treating the two axes as one unit would wrongly reject this.
        val score = LabelScoring.score(
            set(ball(1, 0.5f, 0.50f)),
            mapOf(1 to (0.5f to 0.53f)),
            toleranceFw = 0.02f,
        )

        assertEquals(1, score.truePositives)
    }

    @Test
    fun `the same offset across the frame is judged more strictly than down it`() {
        val across = LabelScoring.score(
            set(ball(1, 0.5f, 0.5f)),
            mapOf(1 to (0.53f to 0.5f)),
            toleranceFw = 0.02f,
        )
        val down = LabelScoring.score(
            set(ball(1, 0.5f, 0.5f)),
            mapOf(1 to (0.5f to 0.53f)),
            toleranceFw = 0.02f,
        )

        assertEquals("0.03 of the width is outside tolerance", 0, across.truePositives)
        assertEquals("0.03 of the height is not", 1, down.truePositives)
    }

    @Test
    fun `the reported error is a median, not a mean`() {
        // Four close matches and one that only just qualifies. A mean would be dragged up
        // by the outlier and would misreport typical accuracy.
        val labels = (1..5).map { ball(it, 0.5f, 0.5f) }
        val detections = mapOf(
            1 to (0.501f to 0.5f),
            2 to (0.502f to 0.5f),
            3 to (0.503f to 0.5f),
            4 to (0.504f to 0.5f),
            5 to (0.519f to 0.5f),
        )

        val score = LabelScoring.score(aspect16x9.copy(labels = labels), detections)

        assertEquals(5, score.truePositives)
        assertEquals(0.003, score.medianErrorFw!!, 1e-4)
        assertEquals(0.019, score.worstErrorFw!!, 1e-4)
    }

    @Test
    fun `an empty review claims nothing at all`() {
        val score = LabelScoring.score(set(), mapOf(1 to (0.5f to 0.5f)))

        assertEquals(0, score.reviewedFrames)
        assertNull(score.precision)
        assertNull(score.recall)
        assertNull(score.f1)
        assertNull(score.medianErrorFw)
    }

    @Test
    fun `a realistic arm-tracking run scores badly, as it should`() {
        // Ten frames: the ball is visible in six, the detector fires in all ten and is
        // right in one. This is roughly what the cricket clip did, and the point of the
        // whole tool is that it produces a number here instead of a screenshot.
        val labels = (1..6).map { ball(it, 0.3f + it * 0.02f, 0.6f) } +
            (7..10).map { absent(it) }
        val detections = (1..10).associateWith { 0.8f to 0.2f } +
            mapOf(3 to (0.36f to 0.6f))

        val score = LabelScoring.score(aspect16x9.copy(labels = labels), detections)

        assertEquals(10, score.reviewedFrames)
        assertEquals(1, score.truePositives)
        assertEquals(9, score.falsePositives)
        assertEquals(5, score.falseNegatives)
        assertTrue("precision must be poor", score.precision!! < 0.15)
        assertTrue("recall must be poor", score.recall!! < 0.2)
    }
}
