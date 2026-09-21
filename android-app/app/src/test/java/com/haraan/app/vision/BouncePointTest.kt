package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.abs

/**
 * The bounce, recovered from a track.
 *
 * THE DELIVERIES HERE ARE FLOWN, NOT DRAWN. An earlier version of this file sketched a
 * plausible-looking path across the picture and asserted the detector found its corner.
 * That tests the detector against an assumption about what a delivery looks like on
 * screen, which is exactly the thing worth doubting — the first attempt at this detector
 * looked for a kink in the image path, and it was the honest synthetic camera below that
 * showed there is barely a kink to find from behind the bowler's arm.
 *
 * So each test builds a ball in three dimensions — a real height above a real pitch at a
 * real distance — puts a camera somewhere a phone would actually stand, and projects the
 * ball the way a camera does: along the line of sight, onto the ground, through the same
 * homography the app uses. The detector then has to hand back the metres the delivery was
 * built from.
 *
 * The refusals matter as much as the recoveries. A detector that finds a bounce in every
 * track is worse than one that finds it half the time, because a length is the kind of
 * number people repeat.
 */
class BouncePointTest {

    /** A believable view from behind the bowler's arm. */
    private fun cameraQuad() = listOf(
        Point2(0.18, 0.74),
        Point2(0.82, 0.74),
        Point2(0.575, 0.36),
        Point2(0.425, 0.36),
    )

    private fun quad(end: CameraEnd = CameraEnd.BOWLER) =
        PitchQuad(cameraQuad(), QuadSource.TAPPED, 1f, end)

    /** Where the phone stands, in pitch metres, and how high off the ground it is. */
    private data class Camera(val x: Double, val y: Double, val height: Double)

    private fun cameraFor(end: CameraEnd) = when (end) {
        // A metre behind the bowler's stumps, on a tripod at chest height.
        CameraEnd.BOWLER -> Camera(0.0, PitchGeometry.STUMPS_TO_STUMPS_M + 1.0, 1.6)
        // And the same from behind the keeper.
        CameraEnd.STRIKER -> Camera(0.0, -1.0, 1.6)
    }

    /**
     * A ball at [height] metres above the pitch, seen from [camera], as a point in the
     * picture.
     *
     * This is the whole synthetic camera. The line of sight from the camera to the ball
     * carries on and meets the ground beyond it; that ground point is what the ball covers
     * from where the camera sits, so it is what the ball's image maps to. A ball on the
     * ground maps to itself, which is the property the detector depends on.
     */
    private fun project(
        ball: Point2,
        height: Double,
        camera: Camera,
        quad: PitchQuad,
    ): Point2 {
        val toImage = quad.toImage()!!
        val t = camera.height / (camera.height - height)
        val onGround = Point2(
            camera.x + (ball.x - camera.x) * t,
            camera.y + (ball.y - camera.y) * t,
        )
        return toImage.map(onGround)
    }

    /**
     * A delivery that pitches [lengthM] metres from the striker, flown down the pitch and
     * filmed at 30fps.
     *
     * Released around two metres up near the bowler, falling to nothing at the bounce, then
     * climbing away towards the batter — the two legs a real delivery has, as heights above
     * the ground rather than as a shape on a screen.
     */
    private fun delivery(
        lengthM: Double,
        lineM: Double = 0.0,
        end: CameraEnd = CameraEnd.BOWLER,
        releaseHeight: Double = 2.1,
        arrivalHeight: Double = 0.75,
        samples: Int = 18,
        jitter: (Int) -> Double = { 0.0 },
    ): List<BallSighting> {
        val quad = quad(end)
        val camera = cameraFor(end)

        val releaseY = PitchGeometry.STUMPS_TO_STUMPS_M - 1.5
        val arrivalY = 1.0

        return (0 until samples).map { i ->
            val progress = i.toDouble() / (samples - 1)
            // Down the pitch at a steady rate, which is close enough over 17 metres.
            val y = releaseY + (arrivalY - releaseY) * progress
            val x = lineM * progress

            // Height: falling to the bounce, then rising away from it.
            val bounceAt = (releaseY - lengthM) / (releaseY - arrivalY)
            val height = if (progress <= bounceAt) {
                releaseHeight * (1.0 - progress / bounceAt)
            } else {
                arrivalHeight * ((progress - bounceAt) / (1.0 - bounceAt))
            }

            val image = project(Point2(x, y), height.coerceAtLeast(0.0), camera, quad)
            BallSighting(
                timestampMs = i * 33L,
                x = (image.x + jitter(i)).toFloat(),
                y = (image.y + jitter(i)).toFloat(),
                trackingConfidence = 0.7f,
                areaPx = 30,
            )
        }
    }

    private fun assertClose(expected: Double, actual: Double, tolerance: Double) {
        assertTrue("expected $expected but was $actual", abs(expected - actual) < tolerance)
    }

    // ── Recovering the answer ───────────────────────────────────────────────────

    @Test
    fun `a bounce is recovered in metres from the striker's stumps`() {
        val found = BouncePoint.find(delivery(lengthM = 6.0), quad())

        assertNotNull("should have found a bounce", found)
        // Within a third of a metre: the ball is sampled every 33ms and the two legs are
        // interpolated, so this is the honest resolution rather than an exact answer.
        assertClose(6.0, found!!.lengthM, 0.35)
    }

    @Test
    fun `lengths up and down the pitch all come back`() {
        listOf(4.0, 6.0, 8.0, 10.0, 12.0).forEach { truth ->
            val found = BouncePoint.find(delivery(lengthM = truth), quad())
            assertNotNull("no bounce found for $truth m", found)
            assertClose(truth, found!!.lengthM, 0.5)
        }
    }

    @Test
    fun `a fuller ball reads shorter than a back of a length one`() {
        // Even where the absolute number drifts, the ordering has to hold — it is what a
        // pitch map is made of.
        val full = BouncePoint.find(delivery(lengthM = 4.0), quad())!!.lengthM
        val good = BouncePoint.find(delivery(lengthM = 6.5), quad())!!.lengthM
        val back = BouncePoint.find(delivery(lengthM = 9.5), quad())!!.lengthM

        assertTrue("$full < $good", full < good)
        assertTrue("$good < $back", good < back)
    }

    @Test
    fun `line off the middle comes back as line off the middle`() {
        val found = BouncePoint.find(delivery(lengthM = 5.0, lineM = 0.5), quad())

        assertNotNull(found)
        // Short of the truth by about a quarter, which is the documented behaviour of a
        // fit that has almost no sideways movement to work with.
        assertClose(0.5, found!!.lineM, 0.25)
    }

    @Test
    fun `the bounce is placed between frames, not on one`() {
        /*
         * The whole reason for interpolating rather than taking the nearest sample. At
         * 30fps the frames either side of a bounce are a metre apart on the ground.
         */
        val track = delivery(lengthM = 7.3)
        val found = BouncePoint.find(track, quad())!!

        val landedOnASample = track.any {
            abs(it.x - found.image.x) < 1e-7 && abs(it.y - found.image.y) < 1e-7
        }
        assertTrue("the answer sat exactly on a sample", !landedOnASample)
    }

    @Test
    fun `a noisy track still lands close to the truth`() {
        // A real detector wobbles by a few pixels. That must move the answer by
        // centimetres, not metres.
        val wobble = { i: Int -> if (i % 2 == 0) 0.003 else -0.003 }
        val found = BouncePoint.find(delivery(lengthM = 6.0, jitter = wobble), quad())

        assertNotNull(found)
        assertClose(6.0, found!!.lengthM, 0.8)
    }

    @Test
    fun `lengths are named the way they are spoken about`() {
        fun nameAt(metres: Double) = BouncePoint.find(delivery(lengthM = metres), quad())!!.length

        assertEquals(BounceLength.GOOD, nameAt(4.5))
        assertEquals(BounceLength.SHORT_OF_A_LENGTH, nameAt(7.5))
        assertEquals(BounceLength.SHORT, nameAt(11.0))
    }

    /**
     * A yorker is not measurable at thirty frames a second, and says so.
     *
     * It pitches at the batter's feet, so the ball reaches the bat a frame or two after it
     * lands and there is no way out of the bounce to fit. The honest response is nothing,
     * and the way to change that is a faster camera rather than a bolder estimator.
     */
    @Test
    fun `a delivery that pitches at the batter's feet is refused, not guessed`() {
        assertNull(BouncePoint.find(delivery(lengthM = 1.0), quad()))
    }

    /** A full ball still lands, though — the refusal above is about yorkers, not length. */
    @Test
    fun `a full ball is still measurable`() {
        val found = BouncePoint.find(delivery(lengthM = 2.5), quad())
        assertNotNull(found)
        assertClose(2.5, found!!.lengthM, 0.5)
    }

    @Test
    fun `a bounce carries what it was measured against`() {
        val found = BouncePoint.find(delivery(lengthM = 6.0), quad())!!

        // A length with no provenance is not a length. Both travel with the number.
        assertEquals(QuadSource.TAPPED, found.quadSource)
        assertEquals(CameraEnd.BOWLER, found.cameraEnd)
    }

    /**
     * From behind the keeper there is no length, and that is the answer rather than a
     * shortcoming to be papered over.
     *
     * The turn this detector finds exists because the ball is going AWAY from the camera.
     * Coming towards it, the reading slides through the bounce without turning, and the
     * obvious alternative — split the track where two straight fits explain it best — was
     * out by nine metres on a simulated delivery. A length that is sometimes nine metres
     * wrong is worse than no length.
     */
    @Test
    fun `filming from behind the keeper yields no length at all`() {
        val end = CameraEnd.STRIKER
        assertNull(BouncePoint.find(delivery(lengthM = 6.0, end = end), quad(end)))
    }

    // ── Refusals ────────────────────────────────────────────────────────────────

    @Test
    fun `too few sightings is no answer`() {
        val brief = delivery(lengthM = 6.0).take(BouncePoint.MIN_SIGHTINGS - 1)
        assertNull(BouncePoint.find(brief, quad()))
    }

    @Test
    fun `a full toss never turns, so there is nothing to report`() {
        // Falling all the way to the batter without reaching the ground. The as-if-on-ground
        // reading sweeps out for every frame and never comes back.
        val fullToss = delivery(lengthM = 6.0, arrivalHeight = 0.0).let { _ ->
            (0 until 14).map { i ->
                val progress = i / 13.0
                val quad = quad()
                val image = project(
                    Point2(0.0, 18.6 + (1.0 - 18.6) * progress),
                    2.1 + (0.9 - 2.1) * progress,
                    cameraFor(CameraEnd.BOWLER),
                    quad,
                )
                BallSighting(i * 33L, image.x.toFloat(), image.y.toFloat(), 0.7f, 30)
            }
        }
        assertNull(BouncePoint.find(fullToss, quad()))
    }

    @Test
    fun `a delivery picked up only after it pitched is refused`() {
        // All climb, no fall: the turn happened before the first frame, so it was not seen
        // and cannot be placed.
        val afterTheBounce = (0 until 12).map { i ->
            val progress = i / 11.0
            val image = project(
                Point2(0.0, 6.0 + (1.0 - 6.0) * progress),
                0.9 * progress,
                cameraFor(CameraEnd.BOWLER),
                quad(),
            )
            BallSighting(i * 33L, image.x.toFloat(), image.y.toFloat(), 0.7f, 30)
        }
        assertNull(BouncePoint.find(afterTheBounce, quad()))
    }

    @Test
    fun `a track that never moves is no answer`() {
        val stuck = (0 until 10).map { BallSighting(it * 33L, 0.5f, 0.5f, 0.7f, 30) }
        assertNull(BouncePoint.find(stuck, quad()))
    }

    @Test
    fun `a bounce off the strip is refused rather than measured`() {
        /*
         * The homography will turn a point out by the sightscreen into a well-formed length
         * of ninety metres. That is a tracking failure handed a ruler.
         */
        val wayOff = delivery(lengthM = 6.0, lineM = 9.0)
        assertNull(BouncePoint.find(wayOff, quad()))
    }

    @Test
    fun `a quad that cannot define a map yields no bounce`() {
        val collinear = PitchQuad(
            listOf(Point2(0.1, 0.5), Point2(0.3, 0.5), Point2(0.5, 0.5), Point2(0.7, 0.5)),
            QuadSource.DETECTED,
            0.5f,
        )
        assertNull(BouncePoint.find(delivery(lengthM = 6.0), collinear))
    }
}
