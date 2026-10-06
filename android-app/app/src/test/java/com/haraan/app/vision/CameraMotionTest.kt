package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.cos
import kotlin.math.sin

/**
 * The half of camera motion that can be proved at a desk.
 *
 * Finding correspondences needs a native optical flow and a device. Deciding what a set of
 * correspondences MEANS is arithmetic, and it is the half that goes wrong quietly — a
 * translation that absorbs the batter, a rotation invented out of noise — so it is the half
 * that gets tested.
 */
class CameraMotionTest {

    private val aspect = 16f / 9f

    /** A grid of points spread across the frame, the way real corners are. */
    private fun grid(count: Int = 5): List<Point2> = buildList {
        for (i in 0 until count) {
            for (j in 0 until count) {
                add(Point2(0.1 + 0.2 * i, 0.1 + 0.2 * j))
            }
        }
    }

    /** Move points as a similarity, in the same aspect-corrected space the fit uses. */
    private fun transform(
        points: List<Point2>,
        dx: Double = 0.0,
        dy: Double = 0.0,
        scale: Double = 1.0,
        rotation: Double = 0.0,
        pivot: Point2 = Point2(0.5, 0.5),
    ): List<Point2> {
        val a = aspect.toDouble()
        val px = pivot.x
        val py = pivot.y / a
        return points.map {
            val x = it.x - px
            val y = it.y / a - py
            val c = cos(rotation) * scale
            val s = sin(rotation) * scale
            Point2(c * x - s * y + px + dx, (s * x + c * y + py) * a + dy)
        }
    }

    @Test
    fun `a still camera reports no motion`() {
        val points = grid()
        val motion = CameraMotion.estimate(points, points, aspect)
        assertEquals(0f, motion.dx, 1e-4f)
        assertEquals(0f, motion.dy, 1e-4f)
        assertEquals(1f, motion.scale, 1e-4f)
        assertEquals(points.size, motion.inliers)
    }

    @Test
    fun `a pure pan is recovered in frame widths`() {
        val points = grid()
        val moved = transform(points, dx = 0.03, dy = 0.02)
        val motion = CameraMotion.estimate(points, moved, aspect)

        assertEquals(0.03f, motion.dx, 1e-3f)
        assertEquals(0.02f, motion.dy, 1e-3f)
        assertTrue(motion.isUsable)
    }

    @Test
    fun `a roll is recovered as a rotation and not as a translation`() {
        val points = grid()
        val moved = transform(points, rotation = 0.05)
        val motion = CameraMotion.estimate(points, moved, aspect)

        assertEquals(0.05f, motion.rotationRad, 1e-3f)
        assertEquals(1f, motion.scale, 1e-3f)
    }

    @Test
    fun `a zoom is recovered as a scale`() {
        val points = grid()
        val moved = transform(points, scale = 1.04)
        val motion = CameraMotion.estimate(points, moved, aspect)

        assertEquals(1.04f, motion.scale, 1e-3f)
    }

    /**
     * The test this file exists for.
     *
     * A batter, a bowler and a ball all move independently of the camera. If they drag the
     * fit, the wicket lock gets pulled along with them every delivery — which is the one
     * time it must not move.
     */
    @Test
    fun `points moving on their own do not drag the fit`() {
        val points = grid()
        val moved = transform(points, dx = 0.02).toMutableList()
        // Four points doing something else entirely.
        moved[0] = Point2(0.9, 0.9)
        moved[7] = Point2(0.05, 0.8)
        moved[13] = Point2(0.8, 0.1)
        moved[20] = Point2(0.5, 0.95)

        val motion = CameraMotion.estimate(points, moved, aspect)
        assertEquals(0.02f, motion.dx, 2e-3f)
        assertTrue("outliers should have been rejected", motion.inliers < points.size)
        assertTrue(motion.isUsable)
    }

    @Test
    fun `a scene where nothing agrees produces no motion`() {
        val points = grid(4)
        /*
         * Every point moves its own way and no six of them agree: a crowd behind the
         * sightscreen, a treeline in wind, or an optical flow that has lost the plot.
         * A deterministic scatter rather than a random one, so a failure here is a real
         * change in behaviour and never a seed.
         */
        val moved = points.mapIndexed { i, p ->
            val spread = 0.004 * (i + 1)
            Point2(
                p.x + if (i % 2 == 0) spread else -spread,
                p.y + if (i % 3 == 0) spread else -spread * 0.6,
            )
        }
        val motion = CameraMotion.estimate(points, moved, aspect)
        assertFalse(
            "no camera move explains this scene, was ${motion.inliers}/${motion.total}",
            motion.isUsable,
        )
    }

    @Test
    fun `too few correspondences is refused rather than fitted`() {
        val points = grid(2)
        assertTrue(points.size < CameraMotion.MIN_CORRESPONDENCES)
        val motion = CameraMotion.estimate(points, transform(points, dx = 0.02), aspect)
        assertEquals(FrameMotion.STILL, motion)
        assertFalse(motion.isUsable)
    }

    /**
     * A phone being picked up is not a camera settling, and a transform that teleports an
     * anchor is worse than admitting the bearings are lost.
     */
    @Test
    fun `an implausibly large lurch is refused`() {
        val points = grid()
        val moved = transform(points, dx = 0.6)
        assertFalse(CameraMotion.estimate(points, moved, aspect).isUsable)
    }

    @Test
    fun `applying the motion moves a point the way the scene moved`() {
        val points = grid()
        val moved = transform(points, dx = 0.03, dy = -0.01, rotation = 0.02, scale = 1.01)
        val motion = CameraMotion.estimate(points, moved, aspect)

        points.forEachIndexed { i, p ->
            val carried = motion.apply(p, aspect)
            assertEquals("x of point $i", moved[i].x, carried.x, 2e-3)
            assertEquals("y of point $i", moved[i].y, carried.y, 2e-3)
        }
    }

    @Test
    fun `confidence rises with agreement and with support`() {
        val few = FrameMotion(0f, 0f, 1f, 0f, inliers = 6, total = 6)
        val many = FrameMotion(0f, 0f, 1f, 0f, inliers = 24, total = 24)
        val contested = FrameMotion(0f, 0f, 1f, 0f, inliers = 12, total = 48)

        assertTrue(many.confidence > few.confidence)
        assertTrue(many.confidence > contested.confidence)
        assertEquals(0f, FrameMotion.STILL.confidence, 1e-6f)
    }

    /**
     * WHAT A THREE-SECOND COAST STANDS ON.
     *
     * The tracker now carries an unseen wicket for seconds on chained camera motion alone —
     * the striker in front of the stumps — where it used to give up after eight frames. That
     * is only honest if chaining ninety noisy estimates does not walk the anchor off the
     * wicket. Optical flow at 320 wide is good to roughly a third of a pixel, about 0.001 of
     * the frame; the far wicket from behind the arm is 0.006 across.
     */
    @Test
    fun `ninety chained noisy estimates of a slow pan stay on a far wicket`() {
        val rnd = kotlin.random.Random(11)
        val noise = 0.001
        val panPerFrame = 0.0012
        var truth = Point2(0.5, 0.6)
        var carried = truth
        var points = grid(6)
        repeat(90) {
            val moved = transform(points, dx = panPerFrame, dy = panPerFrame * 0.2)
            val observed = moved.map { Point2(it.x + (rnd.nextDouble() * 2 - 1) * noise, it.y + (rnd.nextDouble() * 2 - 1) * noise) }
            val motion = CameraMotion.estimate(points, observed, aspect)
            assertTrue("every frame of a steady pan should resolve", motion.isUsable)
            carried = motion.apply(carried, aspect)
            truth = Point2(truth.x + panPerFrame, truth.y + panPerFrame * 0.2)
            points = moved
        }
        val driftFw = kotlin.math.hypot(carried.x - truth.x, (carried.y - truth.y) / aspect)
        assertTrue("drift after 3 s was %.4f fw".format(driftFw), driftFw < 0.003)
    }
}
