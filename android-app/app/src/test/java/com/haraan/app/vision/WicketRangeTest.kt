package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/** The distance estimate and the field-validation log — arithmetic only. */
class WicketRangeTest {

    private fun lockAt(spanFw: Float, riseFw: Float? = null, aspect: Float = 16f / 9f, kind: WicketKind = WicketKind.STUMPS) =
        WicketLock(
            anchor = WicketAnchor(
                Point2(0.5 - spanFw / 2.0, 0.6),
                Point2(0.5 + spanFw / 2.0, 0.6),
                riseFw?.let { Point2(0.5, 0.6 - it * aspect) },
            ),
            kind = kind,
            source = WicketLockSource.DETECTED,
            state = WicketTrackState.CONFIRMED,
            confidence = 0.9f,
            ageFrames = 0,
            jitter = 0f,
            heldFrames = 10,
            aspect = aspect,
            subPixel = true,
            method = StumpMethod.MERGED_COMB,
        )

    @Test
    fun `span and focal give the distance the pinhole says`() {
        // 70° lens: focal 0.714 frame widths. 22 m: span = 0.714 × 0.1936 / 22.
        val camera = CameraIntrinsics.assumed(70.0, 16f / 9f)
        assertEquals(70.0, camera.horizontalFovDeg, 1e-6)
        val span = (camera.focalFw * PitchGeometry.STUMP_CENTRES_SPAN_M / 22.0).toFloat()
        assertEquals(22.0, WicketRange.fromSpan(lockAt(span), camera)!!, 1e-3)
    }

    @Test
    fun `the height cross-check agrees on an upright wicket`() {
        val camera = CameraIntrinsics(0.714, "test")
        val span = (0.714 * PitchGeometry.STUMP_CENTRES_SPAN_M / 15.0).toFloat()
        val rise = (0.714 * PitchGeometry.STUMP_HEIGHT_M / 15.0).toFloat()
        val lock = lockAt(span, rise)
        assertEquals(15.0, WicketRange.fromSpan(lock, camera)!!, 1e-3)
        assertEquals(15.0, WicketRange.fromHeight(lock, camera)!!, 1e-3)
    }

    @Test
    fun `a stone has no distance`() {
        assertNull(WicketRange.fromSpan(lockAt(0.01f, kind = WicketKind.STONE), CameraIntrinsics(0.7, "t")))
    }

    @Test
    fun `the lens maps onto a portrait frame through the sensor's full width`() {
        // 4.7 mm lens on a 6.4 mm wide sensor, 1280x720 landscape stream.
        val landscape = CameraIntrinsics.fromLens(4.7, 6.4, 1280, 720, sideways = false)!!
        val portrait = CameraIntrinsics.fromLens(4.7, 6.4, 1280, 720, sideways = true)!!
        assertEquals(4.7 / 6.4, landscape.focalFw, 1e-9)
        // Same focal in pixels, over a 720-wide upright frame.
        assertEquals(4.7 / 6.4 * 1280 / 720, portrait.focalFw, 1e-9)
        assertTrue(portrait.horizontalFovDeg < landscape.horizontalFovDeg)
    }

    @Test
    fun `validation summarises each taped distance and exports every sample`() {
        val camera = CameraIntrinsics(0.714, "test")
        val log = WicketValidation()
        fun spanAt(d: Double) = (0.714 * PitchGeometry.STUMP_CENTRES_SPAN_M / d).toFloat()
        repeat(10) { log.record(20.0, it * 33L, lockAt(spanAt(21.0)), camera, 1280, 0.5f, 66L) }
        repeat(5) { log.record(30.0, 1000L + it * 33L, null, camera, 1280, null, null) }
        repeat(5) { log.record(30.0, 2000L + it * 33L, lockAt(spanAt(30.0)), camera, 1280, null, 66L) }

        val rows = log.summary()
        assertEquals(2, rows.size)
        assertEquals(20.0, rows[0].trueDistanceM, 0.0)
        assertEquals(1.0, rows[0].readyRate, 1e-9)
        assertEquals(5.0, rows[0].errorPct!!, 0.01)
        assertEquals(0.5, rows[1].readyRate, 1e-9)
        assertEquals(0.0, rows[1].errorPct!!, 0.01)

        val csv = log.csv("test")
        assertEquals("one line per sample plus headers", 20, csv.lines().count { it.startsWith("20.0,") || it.startsWith("30.0,") } - 2)
        assertTrue(csv.contains("MERGED_COMB"))
    }
}
