package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.zip.GZIPInputStream

/**
 * The batter's stumps found from the bowler's, on real 4K frames (the camera's 1280x720
 * analysis luma, outside the searched region blanked to keep the files small).
 */
class FarWicketSearchTest {
    private val w = 1280
    private val h = 720
    private val aspect = 720f / 1280f

    private fun luma(name: String): ByteArray =
        GZIPInputStream(javaClass.classLoader!!.getResourceAsStream("vision/$name")!!).use { it.readBytes() }

    /** Calls until the band has been swept once; the first wicket found, or null. */
    private fun sweep(search: FarWicketSearch, y: ByteArray, near: BowlersEnd): StumpSet? {
        repeat(SWEEP_CALLS) { search.next(y, w, h, w, 90, near, aspect)?.let { return it } }
        return null
    }

    /** Enough calls of [FarWicketSearch.CELLS_PER_CALL] to cover the band once on these frames. */
    private companion object {
        const val SWEEP_CALLS = 60
    }

    /*
     * IMG_6053: a batter in light trousers right behind white stumps. The whole-frame search
     * found these stumps on 3 frames of 73; frame 45 (the release) was not one of them.
     */
    @Test
    fun findsTheBattersStumpsInFrontOfLightTrousers() {
        val found = sweep(FarWicketSearch(), luma("img6053_f45_luma.gz"), BowlersEnd(Point2(0.425, 0.775), 0.0872))
        assertNotNull("no far wicket found", found)
        assertEquals(0.598, found!!.baseMiddle.x.toDouble(), 0.01)
        assertEquals(0.504, found.baseMiddle.y.toDouble(), 0.01)
        assertEquals("the true span, not a half or a double", 0.0139, found.spanX.toDouble(), 0.0139 * 0.08)
        assertEquals(StumpMethod.PREDICTED_COMB, found.method)
    }

    /*
     * IMG_6056: the net behind is dark posts in pairs that fit the comb at a wicket's spacing
     * a dozen times in this band; only the stumps are the bowler's stumps' colour.
     */
    @Test
    fun findsThemAgainstAnOpenBackgroundToo() {
        val found = sweep(FarWicketSearch(), luma("img6056_f120_luma.gz"), BowlersEnd(Point2(0.422, 0.766), 0.0862))
        assertNotNull("no far wicket found", found)
        assertEquals(0.603, found!!.baseMiddle.x.toDouble(), 0.01)
        assertEquals(0.497, found.baseMiddle.y.toDouble(), 0.01)
        assertEquals(0.0130, found.spanX.toDouble(), 0.0130 * 0.1)
    }

    @Test
    fun anEmptyPictureGivesNothing() {
        val flat = ByteArray(w * h) { 110.toByte() }
        assertNull(sweep(FarWicketSearch(), flat, BowlersEnd(Point2(0.425, 0.775), 0.0872)))
    }

    @Test
    fun predictsANarrowerSetAPitchAway() {
        val span = FarWicketSearch().predictedSpan(BowlersEnd(Point2(0.425, 0.775), 0.0872))!!
        // 1.4 fw lens: the near set 3.1 m away, the far one 23.2 m, so about 0.134 as wide.
        assertEquals(0.0117, span, 0.0005)
    }
}
