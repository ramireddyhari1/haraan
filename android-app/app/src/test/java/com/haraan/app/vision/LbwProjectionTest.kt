package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs

/**
 * The projection, and — at least as importantly — everything it refuses to project.
 *
 * A screen that prints HITTING is making a claim of a different order from one that prints
 * a speed, so the refusals get as many tests as the answers. Most of this file is about
 * the cases where the honest output is "no projection", and the reason.
 */
class LbwProjectionTest {

    private val aspect = 16f / 9f

    /** A wicket locked by hand, centred at [x], [span] wide, standing on [baseY]. */
    private fun lock(
        x: Double = 0.5,
        baseY: Double = 0.30,
        span: Double = 0.10,
        top: Point2? = Point2(0.5, 0.12),
    ): WicketLock {
        val tracker = WicketTracker()
        tracker.lockManually(
            baseLeft = Point2(x - span / 2, baseY),
            baseRight = Point2(x + span / 2, baseY),
            top = top,
            kind = WicketKind.STUMPS,
            frameAspect = aspect,
        )
        return tracker.lock()!!
    }

    /**
     * A delivery filmed from behind the bowler's arm: the ball goes UP the picture, away
     * from the camera, and lands short of the stumps.
     *
     * [endX] is where the flight is aimed across the picture; the path is a straight line
     * from release to there, so the projection has an exact expected answer.
     */
    private fun flight(
        fromX: Double = 0.5,
        toX: Double = 0.5,
        fromY: Double = 0.80,
        toY: Double = 0.45,
        count: Int = 10,
        startMs: Long = 0L,
    ): List<BallSighting> = (0 until count).map { i ->
        val t = i.toDouble() / (count - 1)
        BallSighting(
            timestampMs = startMs + i * 33L,
            x = (fromX + (toX - fromX) * t).toFloat(),
            y = (fromY + (toY - fromY) * t).toFloat(),
            trackingConfidence = 0.8f,
            areaPx = 40,
        )
    }

    // ---- refusals -------------------------------------------------------------------------

    @Test
    fun `no lock means no projection`() {
        val projection = LbwProjector.project(flight(), null)
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
        assertTrue(projection.basis.contains("lock"))
    }

    @Test
    fun `a stone lock is refused because it has no width`() {
        val tracker = WicketTracker()
        tracker.lockManually(Point2(0.48, 0.3), Point2(0.52, 0.3), kind = WicketKind.STONE, frameAspect = aspect)
        val projection = LbwProjector.project(flight(), tracker.lock())
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
        assertTrue(projection.basis.contains("stone"))
    }

    @Test
    fun `a coasting lock is refused and says so`() {
        val tracker = WicketTracker()
        val stumps = WicketSighting.Stumps(
            StumpSet(
                StumpCandidate(0.45f, 0.30f, 0.18f),
                StumpCandidate(0.50f, 0.30f, 0.18f),
                StumpCandidate(0.55f, 0.30f, 0.18f),
                score = 0.9f,
            ),
        )
        val steady = FrameMotion(0f, 0f, 1f, 0f, inliers = 30, total = 30)
        repeat(WicketTracker.CONFIRM_FRAMES) { i -> tracker.onFrame(stumps, steady, aspect, i * 33L) }
        tracker.onFrame(null, steady, aspect, 300L)

        val projection = LbwProjector.project(flight(), tracker.lock())
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
        assertTrue(projection.basis.contains("temporarily lost"))
    }

    @Test
    fun `too few sightings is refused`() {
        val projection = LbwProjector.project(flight(count = 2), lock())
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
    }

    /**
     * The one that keeps a projection from becoming a wish.
     */
    @Test
    fun `a flight nowhere near the stumps is not carried all the way there`() {
        // Only a short stub of path, a long way short of the wicket.
        val projection = LbwProjector.project(flight(fromY = 0.95, toY = 0.90), lock(baseY = 0.30))
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
        assertTrue(projection.basis.contains("short of the stumps"))
    }

    @Test
    fun `a flight running along the stump line never crosses it`() {
        // Straight across the picture at a constant height: parallel to the stump line.
        val across = (0 until 10).map { i ->
            BallSighting(i * 33L, 0.2f + 0.05f * i, 0.60f, 0.8f, 40)
        }
        val projection = LbwProjector.project(across, lock(baseY = 0.30))
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
        assertTrue(projection.basis.contains("along the stump line"))
    }

    @Test
    fun `a wicket already behind the ball is refused rather than projected backwards`() {
        // The ball has travelled past the stump line and kept going.
        val past = flight(fromY = 0.50, toY = 0.10)
        val projection = LbwProjector.project(past, lock(baseY = 0.30))
        assertEquals(LbwVerdict.UNAVAILABLE, projection.verdict)
    }

    // ---- answers ---------------------------------------------------------------------------

    @Test
    fun `a flight straight at the middle stump is hitting`() {
        val projection = LbwProjector.project(flight(fromX = 0.5, toX = 0.5), lock(x = 0.5))
        assertEquals(LbwVerdict.HITTING, projection.verdict)
        assertEquals(0.0, projection.offsetM!!, 0.02)
        assertNotNull(projection.uncertaintyM)
    }

    @Test
    fun `a flight well outside the line is missing`() {
        // Aimed hard across the picture: more than a wicket's width away by the stumps.
        val projection = LbwProjector.project(
            flight(fromX = 0.50, toX = 0.75, fromY = 0.80, toY = 0.45),
            lock(x = 0.5, baseY = 0.30),
        )
        assertEquals(LbwVerdict.MISSING, projection.verdict)
        assertTrue("it should be to the right of middle", projection.offsetM!! > 0)
    }

    @Test
    fun `the offset is in real metres and scales with the wicket's size in frame`() {
        // The same flight past two locks of different apparent width. A wicket half as wide
        // in the picture is twice as far away, so the same pixels are twice the metres.
        val near = LbwProjector.project(flight(toX = 0.54), lock(span = 0.10))
        val far = LbwProjector.project(flight(toX = 0.54), lock(span = 0.05))

        assertNotNull(near.offsetM)
        assertNotNull(far.offsetM)
        assertEquals(2.0, abs(far.offsetM!! / near.offsetM!!), 0.15)
    }

    /**
     * The band is the product. A number without it is a guess wearing three decimal places.
     */
    @Test
    fun `a noisy track widens the band and can turn a verdict into too close to call`() {
        val clean = flight(fromX = 0.50, toX = 0.5575, fromY = 0.80, toY = 0.45)
        val noisy = clean.mapIndexed { i, p ->
            p.copy(x = p.x + if (i % 2 == 0) 0.012f else -0.012f)
        }

        val cleanProjection = LbwProjector.project(clean, lock())
        val noisyProjection = LbwProjector.project(noisy, lock())

        assertTrue(
            "noise should widen the band",
            noisyProjection.uncertaintyM!! > cleanProjection.uncertaintyM!!,
        )
    }

    @Test
    fun `the band never claims more precision than this pipeline has`() {
        val projection = LbwProjector.project(flight(), lock())
        assertTrue(projection.uncertaintyM!! >= LbwProjector.MIN_UNCERTAINTY_M)
    }

    @Test
    fun `a height at the stumps is reported when a stump top is known and not otherwise`() {
        val withTop = LbwProjector.project(flight(), lock(top = Point2(0.5, 0.12)))
        val without = LbwProjector.project(flight(), lock(top = null))

        assertNotNull(withTop.heightM)
        org.junit.Assert.assertNull(without.heightM)
    }

    // ---- the limbs ---------------------------------------------------------------------------

    /**
     * The assertion that keeps this from turning into an umpire.
     */
    @Test
    fun `every projection carries the four questions it did not answer`() {
        listOf(
            LbwProjector.project(flight(), lock()),
            LbwProjector.project(emptyList(), null),
        ).forEach { projection ->
            val unjudged = projection.limbs.filterNot { it.judged }
            assertTrue(unjudged.any { it.question.contains("pitch in line") })
            assertTrue(unjudged.any { it.question.contains("pad") })
            assertTrue(unjudged.any { it.question.contains("high") })
            assertTrue(unjudged.any { it.question.contains("bat first") })
        }

        // Four when the projection answered its own question, five when it could not -
        // because "would it have hit the stumps" then joins the list rather than quietly
        // disappearing from it, which is how a refusal turns into a silence.
        assertEquals(4, LbwProjector.project(flight(), lock()).limbs.count { !it.judged })
        assertEquals(5, LbwProjector.project(emptyList(), null).limbs.count { !it.judged })
    }

    @Test
    fun `an unavailable projection is not decided`() {
        assertFalse(LbwProjector.project(emptyList(), null).isDecided)
        assertTrue(LbwProjector.project(flight(), lock()).isDecided)
    }
}
