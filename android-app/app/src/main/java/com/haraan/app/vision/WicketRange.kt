package com.haraan.app.vision

import kotlin.math.atan
import kotlin.math.tan

/**
 * The camera's focal length, in FRAME WIDTHS of the upright analysis frame.
 *
 * Frame widths because every span in this package is in frame widths: a wicket whose outer
 * centres are `s` frame widths apart, seen through a lens of focal length `f` frame widths,
 * is `f × 0.1936 / s` metres away. No pixel counts and no resolution in that formula.
 */
data class CameraIntrinsics(
    val focalFw: Double,
    /** Where the number came from, for the admin screen: "lens calibration", "focal/sensor", "assumed 70°". */
    val source: String,
) {
    val horizontalFovDeg: Double get() = Math.toDegrees(2.0 * atan(0.5 / focalFw))

    companion object {
        /**
         * From the lens focal length and the physical sensor size, both in millimetres.
         *
         * ASSUMES the analysis stream uses the sensor's full WIDTH (a 16:9 stream cropped
         * top and bottom from a 4:3 sensor), which is how phone cameras produce 16:9 — but
         * an assumption, and the reason the admin screen prints [source] beside every
         * distance it shows.
         *
         * @param outWidth, outHeight the analysis frame as the sensor delivers it (landscape)
         * @param sideways whether the upright frame is that frame turned a quarter
         */
        fun fromLens(
            focalMm: Double,
            sensorWidthMm: Double,
            outWidth: Int,
            outHeight: Int,
            sideways: Boolean,
            source: String = "focal/sensor",
        ): CameraIntrinsics? {
            if (focalMm <= 0 || sensorWidthMm <= 0 || outWidth <= 0 || outHeight <= 0) return null
            val focalPx = focalMm * outWidth / sensorWidthMm
            val uprightWidth = if (sideways) outHeight else outWidth
            return CameraIntrinsics(focalPx / uprightWidth, source)
        }

        /** A typical phone main camera, for when the device reports nothing. Flagged as such. */
        fun assumed(hfovDeg: Double, aspect: Float): CameraIntrinsics {
            // hfov is across the sensor's LONG side; a portrait upright frame is the short one.
            val landscapeFocal = 0.5 / tan(Math.toRadians(hfovDeg / 2.0))
            val f = if (aspect < 1f) landscapeFocal / aspect else landscapeFocal
            return CameraIntrinsics(f, "assumed %.0f°".format(hfovDeg))
        }
    }
}

/**
 * How far away a locked wicket is, from its size in the picture.
 *
 * AN ESTIMATE, AND LABELLED AS ONE EVERYWHERE IT IS SHOWN. It rests on the stumps being
 * the Laws' size (club and gully stumps are not always), on the focal length being what the
 * phone reports, and on the full-width assumption in [CameraIntrinsics.fromLens]. None of
 * that has been checked against a tape measure on a ground; [WicketValidation] exists so
 * that it can be.
 */
object WicketRange {

    /** From the span between the outer stump centres: the direction the comb measures best. */
    fun fromSpan(lock: WicketLock, camera: CameraIntrinsics): Double? {
        if (lock.kind != WicketKind.STUMPS) return null
        val span = lock.span
        if (span <= 1e-6f) return null
        return camera.focalFw * PitchGeometry.STUMP_CENTRES_SPAN_M / span
    }

    /**
     * From the stump height, as a cross-check. A camera looking down shortens the height,
     * so this reads FARTHER than the truth by 1/cos(the downward angle) — about 1% at 8°.
     * Two estimates that disagree by more than that mean one of the assumptions is wrong.
     */
    fun fromHeight(lock: WicketLock, camera: CameraIntrinsics): Double? {
        if (lock.kind != WicketKind.STUMPS) return null
        val rise = lock.anchor.rise(lock.aspect) ?: return null
        if (rise <= 1e-6f) return null
        return camera.focalFw * PitchGeometry.STUMP_HEIGHT_M / rise
    }

    /** Outer-centre span in upright pixels, for the readout. */
    fun spanPx(lock: WicketLock, uprightWidthPx: Int): Double = lock.span.toDouble() * uprightWidthPx
}
