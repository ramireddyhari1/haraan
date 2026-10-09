package com.haraan.app.camera

import android.hardware.camera2.CameraCaptureSession
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraMetadata
import android.hardware.camera2.CaptureRequest
import android.hardware.camera2.CaptureResult
import android.hardware.camera2.TotalCaptureResult
import android.util.Log
import androidx.camera.camera2.interop.Camera2CameraControl
import androidx.camera.camera2.interop.Camera2CameraInfo
import androidx.camera.camera2.interop.CaptureRequestOptions
import androidx.camera.camera2.interop.ExperimentalCamera2Interop
import kotlin.math.pow

/**
 * A FAST SHUTTER, so the ball is a ball and not a streak.
 *
 * WHY. Left alone, auto-exposure picks the longest shutter the frame rate allows before it
 * raises the gain — in a shaded backyard 1/60 s and up. A ball at 25 km/h moves 12 cm in
 * that time; at 120 km/h, half a metre. It is filmed as a faint smear, the frame difference
 * finds a long thin blob (rejected as not round) or a faint one (below the motion floor),
 * and its centre, when it is found, is somewhere along the smear. A short shutter freezes it.
 *
 * HOW. Auto-exposure is let run first and its choice read back: that is the brightness the
 * scene wants. The same total exposure (time × gain) is then given back as a 1/1000 s
 * shutter with the gain raised to match, up to a cap above which sensor noise starts to look
 * like motion; if the cap is reached, the shutter lengthens only as far as it must. From then
 * on the picture's own mean brightness is held where auto-exposure left it, a small step at a
 * time — clouds, a shadow crossing the ground.
 *
 * WHICH PHONES. Only those whose camera offers MANUAL_SENSOR, which is most mid-range and up
 * and many budget ones. Without it the camera stays fully automatic and nothing changes.
 * Nothing here touches frame rate: the frame duration is pinned at the session's 30 fps.
 */
@androidx.annotation.OptIn(ExperimentalCamera2Interop::class)
class ShutterControl {

    /** Fed to the capture session; reads what auto-exposure chose. */
    val callback = object : CameraCaptureSession.CaptureCallback() {
        override fun onCaptureCompleted(session: CameraCaptureSession, request: CaptureRequest, result: TotalCaptureResult) {
            if (mode != Mode.AUTO) return
            val t = result.get(CaptureResult.SENSOR_EXPOSURE_TIME) ?: return
            val iso = result.get(CaptureResult.SENSOR_SENSITIVITY) ?: return
            autoExposureNs = t
            autoIso = iso
            autoConverged = result.get(CaptureResult.CONTROL_AE_STATE) == CameraMetadata.CONTROL_AE_STATE_CONVERGED
        }
    }

    private enum class Mode { UNSUPPORTED, AUTO, MANUAL }

    @Volatile private var mode = Mode.UNSUPPORTED
    @Volatile private var autoExposureNs = 0L
    @Volatile private var autoIso = 0
    @Volatile private var autoConverged = false

    private var control: Camera2CameraControl? = null
    private var exposureRange: android.util.Range<Long>? = null
    private var isoRange: android.util.Range<Int>? = null
    private var attachedAtMs = 0L
    private var lastStepMs = 0L
    private var lumaRef = 0.0
    /** Total exposure, ns × ISO. */
    private var total = 0.0
    private var shutterNs = 0L
    private var iso = 0

    /** For the admin readout. */
    @Volatile var status: String = "—"
        private set

    /** The bound camera. Reads whether it can be driven by hand; leaves it automatic if not. */
    fun attach(camera: androidx.camera.core.Camera) {
        // A rebind keeps the same camera, and with it any hand-set exposure from before:
        // start from automatic so there is something to meter.
        runCatching { Camera2CameraControl.from(camera.cameraControl).clearCaptureRequestOptions() }
        val info = Camera2CameraInfo.from(camera.cameraInfo)
        val caps = info.getCameraCharacteristic(CameraCharacteristics.REQUEST_AVAILABLE_CAPABILITIES)
        val manual = caps?.contains(CameraMetadata.REQUEST_AVAILABLE_CAPABILITIES_MANUAL_SENSOR) == true
        exposureRange = info.getCameraCharacteristic(CameraCharacteristics.SENSOR_INFO_EXPOSURE_TIME_RANGE)
        isoRange = info.getCameraCharacteristic(CameraCharacteristics.SENSOR_INFO_SENSITIVITY_RANGE)
        if (!manual || exposureRange == null || isoRange == null) {
            mode = Mode.UNSUPPORTED
            status = "auto (no manual sensor)"
            Log.i(ANALYSIS_TAG, "shutter: phone has no manual sensor control; staying automatic")
            return
        }
        control = Camera2CameraControl.from(camera.cameraControl)
        mode = Mode.AUTO
        attachedAtMs = System.currentTimeMillis()
        autoConverged = false
        status = "auto (metering)"
    }

    /** The camera went away; nothing to undo — a new session starts automatic. */
    fun detach() {
        control = null
        mode = Mode.UNSUPPORTED
    }

    /**
     * The analysis frame's mean brightness (0..255), every frame or every few. Drives both the
     * switch to a fast shutter and keeping the picture's brightness steady afterwards.
     */
    fun onLuma(mean: Double, nowMs: Long) {
        when (mode) {
            Mode.UNSUPPORTED -> return
            Mode.AUTO -> {
                if (nowMs - attachedAtMs < SETTLE_MS || !autoConverged) return
                if (nowMs - lastStepMs < STEP_MS) return
                lastStepMs = nowMs
                val t = autoExposureNs
                val g = autoIso
                if (t <= 0 || g <= 0) return
                if (t <= FAST_SHUTTER_NS * 1.25) {
                    status = "auto, already 1/${1_000_000_000L / t} s"
                    return
                }
                lumaRef = mean.coerceIn(40.0, 200.0)
                total = t.toDouble() * g
                mode = Mode.MANUAL
                apply("from auto 1/${1_000_000_000L / t} s ISO $g")
            }
            Mode.MANUAL -> {
                if (nowMs - lastStepMs < STEP_MS) return
                lastStepMs = nowMs
                if (mean <= 1.0) return
                // Brightness is roughly exposure to the 1/2.2; a gentle, bounded step.
                val ratio = (lumaRef / mean).pow(2.0).coerceIn(0.7, 1.4)
                if (ratio in 0.9..1.1) return
                total *= ratio
                apply(null)
            }
        }
    }

    private fun apply(why: String?) {
        val c = control ?: return
        val er = exposureRange ?: return
        val ir = isoRange ?: return
        val isoCap = minOf(ir.upper, MAX_ISO).coerceAtLeast(ir.lower)
        total = total.coerceIn(er.lower.toDouble() * ir.lower, MAX_SHUTTER_NS.toDouble() * isoCap)
        var t = maxOf(FAST_SHUTTER_NS.toDouble(), total / isoCap)
        t = t.coerceIn(er.lower.toDouble(), minOf(er.upper, MAX_SHUTTER_NS).toDouble())
        shutterNs = t.toLong()
        iso = (total / t).toInt().coerceIn(ir.lower, isoCap)
        val options = CaptureRequestOptions.Builder()
            .setCaptureRequestOption(CaptureRequest.CONTROL_AE_MODE, CameraMetadata.CONTROL_AE_MODE_OFF)
            .setCaptureRequestOption(CaptureRequest.SENSOR_EXPOSURE_TIME, shutterNs)
            .setCaptureRequestOption(CaptureRequest.SENSOR_SENSITIVITY, iso)
            .setCaptureRequestOption(CaptureRequest.SENSOR_FRAME_DURATION, FRAME_DURATION_NS)
            .build()
        runCatching { c.setCaptureRequestOptions(options) }
            .onFailure {
                Log.w(ANALYSIS_TAG, "shutter: manual exposure refused; back to auto", it)
                mode = Mode.UNSUPPORTED
                status = "auto (manual refused)"
                return
            }
        status = "1/${1_000_000_000L / shutterNs} s · ISO $iso"
        if (why != null) Log.i(ANALYSIS_TAG, "shutter: $status ($why)")
    }

    private companion object {
        /** 1/1000 s: a 120 km/h ball moves 3 cm, a quarter of its own width at 6 m. */
        const val FAST_SHUTTER_NS = 1_000_000L
        /** Never longer than a frame at 30 fps. */
        const val MAX_SHUTTER_NS = 33_000_000L
        const val FRAME_DURATION_NS = 33_333_333L
        /** Above this, a budget sensor's noise survives the blur and starts to look like motion. */
        const val MAX_ISO = 1600
        const val SETTLE_MS = 1_500L
        const val STEP_MS = 500L
    }
}
