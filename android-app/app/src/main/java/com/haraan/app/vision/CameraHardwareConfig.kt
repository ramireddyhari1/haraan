package com.haraan.app.vision

import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CaptureRequest
import android.util.Range
import androidx.annotation.OptIn
import androidx.camera.camera2.interop.Camera2CameraInfo
import androidx.camera.camera2.interop.Camera2Interop
import androidx.camera.camera2.interop.ExperimentalCamera2Interop
import androidx.camera.core.CameraInfo
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview

/**
 * Camera2 Interop tuning for fast bowling motion capture.
 *
 * Fast bowling arms travel at 130-150 km/h (~36-42 m/s).
 * At standard 30 FPS auto-exposure (1/30s or 1/60s shutter):
 * - The wrist sweeps 60-120 cm during a single exposure, producing severe motion blur streaks.
 * - Keypoint landmark accuracy degrades because the hand appears as a translucent blur.
 *
 * This configuration:
 * 1. Negotiates 60 FPS capture range if supported by device hardware.
 * 2. Locks exposure time to <= 1/500s (2,000,000 ns) outdoors to freeze arm rotation.
 */
object CameraHardwareConfig {

    /** 1/500s in nanoseconds */
    const val SHUTTER_FAST_BOWLING_NS = 2_000_000L

    /** 1/1000s in nanoseconds (bright daylight) */
    const val SHUTTER_DAYLIGHT_NS = 1_000_000L

    /**
     * Inspect whether this camera device physically supports 60 FPS preview/analysis.
     */
    @OptIn(ExperimentalCamera2Interop::class)
    fun findBestFpsRange(cameraInfo: CameraInfo): Range<Int> {
        val c2Info = Camera2CameraInfo.from(cameraInfo)
        val ranges = c2Info.getCameraCharacteristic(CameraCharacteristics.CONTROL_AE_AVAILABLE_TARGET_FPS_RANGES)
            ?: return Range(30, 30)

        // Find 60 FPS range if available
        val sixty = ranges.firstOrNull { it.upper >= 60 && it.lower >= 30 }
        if (sixty != null) {
            return Range(60, 60)
        }

        // Fallback to highest available stable range
        return ranges.maxByOrNull { it.upper } ?: Range(30, 30)
    }

    /**
     * Apply Camera2 Interop configuration to CameraX Preview builder.
     *
     * @param targetFps e.g. 60 or 30
     * @param lockShutterSpeed if true, locks exposure to <= 1/500s to eliminate arm blur
     */
    @OptIn(ExperimentalCamera2Interop::class)
    fun configurePreview(
        builder: Preview.Builder,
        targetFps: Int = 60,
        lockShutterSpeed: Boolean = false,
    ) {
        val extender = Camera2Interop.Extender(builder)

        if (targetFps >= 60) {
            extender.setCaptureRequestOption(
                CaptureRequest.CONTROL_AE_TARGET_FPS_RANGE,
                Range(targetFps, targetFps)
            )
        }

        if (lockShutterSpeed) {
            // Manual Sensor Exposure mode
            extender.setCaptureRequestOption(
                CaptureRequest.CONTROL_AE_MODE,
                CaptureRequest.CONTROL_AE_MODE_OFF
            )
            extender.setCaptureRequestOption(
                CaptureRequest.SENSOR_EXPOSURE_TIME,
                SHUTTER_FAST_BOWLING_NS
            )
            // Auto-boost sensitivity (ISO 400-800) for outdoor fast bowling
            extender.setCaptureRequestOption(
                CaptureRequest.SENSOR_SENSITIVITY,
                400
            )
        } else {
            // Continuous sports auto-exposure with priority on high shutter speed
            extender.setCaptureRequestOption(
                CaptureRequest.CONTROL_AE_MODE,
                CaptureRequest.CONTROL_AE_MODE_ON
            )
        }
    }

    /**
     * Apply Camera2 Interop configuration to CameraX ImageAnalysis builder.
     */
    @OptIn(ExperimentalCamera2Interop::class)
    fun configureAnalysis(
        builder: ImageAnalysis.Builder,
        targetFps: Int = 60,
    ) {
        val extender = Camera2Interop.Extender(builder)
        if (targetFps >= 60) {
            extender.setCaptureRequestOption(
                CaptureRequest.CONTROL_AE_TARGET_FPS_RANGE,
                Range(targetFps, targetFps)
            )
        }
    }
}
