package com.haraan.app.vision

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The guard that decides whether Otsu did its job.
 *
 * WHAT WENT WRONG. The binarise stage exists to keep the bright minority — crease paint,
 * whites — and throw away the outfield's texture before Hough sees it. Otsu finds the
 * widest gap in the histogram, and on a tight view from behind the bowler's arm that gap
 * is grass versus paint, exactly as intended. Put a treeline or a sightscreen in frame and
 * the widest gap becomes trees versus everything else: the threshold drops below the
 * paint, and grass, creases and stumps saturate into a single white shape. Measured on
 * real club footage, that was 62-70% of the frame white, one surviving edge, and zero
 * down-pitch segments — the detector refusing a picture that was full of straight lines.
 *
 * WHY A GUARD AND NOT A REPLACEMENT. Otsu is right for the view it was tuned for, and
 * there is no clip yet that proves an adaptive threshold does no harm there. So the
 * behaviour is unchanged whenever Otsu keeps a minority, and only swapped when it has
 * demonstrably swallowed the frame. That makes this arithmetic the hinge of the whole
 * fix, which is why it is pure and tested here rather than buried in the OpenCV path.
 */
class OtsuSaturationTest {

    @Test
    fun `a thin bright minority is Otsu working, and is left alone`() {
        // Creases, whites and a few highlights on a 480x270 analysis frame.
        assertFalse(PitchThreshold.otsuSaturated(whitePixels = 6_000, totalPixels = 129_600))
    }

    @Test
    fun `the club footage that broke it is caught`() {
        // The four frames actually measured: 62.4%, 65.5%, 62.9% and 69.9% white.
        listOf(0.624, 0.655, 0.629, 0.699).forEach { fraction ->
            val white = (129_600 * fraction).toInt()
            assertTrue(
                "a frame that is ${fraction * 100}% white must not be trusted",
                PitchThreshold.otsuSaturated(white, 129_600),
            )
        }
    }

    @Test
    fun `the line itself is where it says it is`() {
        val total = 100_000
        val limit = (total * PitchThreshold.MAX_WHITE_FRACTION).toInt()

        assertFalse("at the limit is still acceptable", PitchThreshold.otsuSaturated(limit, total))
        assertTrue("a pixel past it is not", PitchThreshold.otsuSaturated(limit + 1, total))
    }

    @Test
    fun `a fully white frame is the worst case, not a special case`() {
        assertTrue(PitchThreshold.otsuSaturated(129_600, 129_600))
    }

    @Test
    fun `a frame with nothing bright at all is not saturation`() {
        // Zero white means the threshold kept nothing — a different failure, and one the
        // segment counts downstream already describe. This guard must not claim it.
        assertFalse(PitchThreshold.otsuSaturated(0, 129_600))
    }

    @Test
    fun `an empty frame cannot be divided by and is not saturated`() {
        assertFalse(PitchThreshold.otsuSaturated(0, 0))
        assertFalse(PitchThreshold.otsuSaturated(10, 0))
    }

    @Test
    fun `the guard is a fraction, not a pixel count`() {
        // The same share at two analysis resolutions must give the same answer, or the
        // fix would silently change behaviour the day somebody tunes analysisWidth.
        val small = PitchThreshold.otsuSaturated(64_800, 129_600)
        val large = PitchThreshold.otsuSaturated(259_200, 518_400)

        assertTrue(small)
        assertTrue(large)
    }
}
