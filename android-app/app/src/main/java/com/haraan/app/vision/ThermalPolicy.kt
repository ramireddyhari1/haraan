package com.haraan.app.vision

import android.content.Context
import android.os.Build
import android.os.PowerManager

/**
 * Operational thermal tiers for continuous outdoor cricket tracking.
 */
enum class ThermalOperationalState {
    /** Optimal operating condition: full target FPS, full neon overlays, real-time kinematics. */
    NOMINAL,

    /** Warm device: slightly thinned preview/render cycles, 45 FPS target. */
    WARM,

    /** Elevated temperature: 30 FPS analysis target, disable GPU glow effects, keep delivery tracking active. */
    HOT,

    /** Severe thermal load: minimal 20 FPS analysis, drop all non-essential UI, preserve core delivery release. */
    CRITICAL,
}

/**
 * Snapshot report of the thermal policy and adaptive hardware throttles.
 */
data class ThermalStatusReport(
    val state: ThermalOperationalState,
    val isStealthEcoModeActive: Boolean,
    val targetAnalysisFps: Int,
    val renderGlowEffects: Boolean,
    val renderDebugOverlays: Boolean,
    val adaptiveFrameThrottleMs: Long,
    val statusMessage: String,
)

/**
 * Enterprise Thermal Policy Manager.
 *
 * Monitors hardware thermal status and adjusts rendering load, frame rates, and visual effects
 * to prevent thermal shutdowns during 3-hour outdoor matches under 35-40°C sunlight.
 */
class ThermalPolicyManager(
    context: Context? = null,
) {
    private val powerManager: PowerManager? = context?.getSystemService(Context.POWER_SERVICE) as? PowerManager
    private var manualStealthMode: Boolean = false
    private var simulatedState: ThermalOperationalState? = null

    var isStealthEcoMode: Boolean
        get() = manualStealthMode
        set(value) { manualStealthMode = value }

    fun setSimulatedState(state: ThermalOperationalState?) {
        simulatedState = state
    }

    fun evaluateThermalStatus(): ThermalStatusReport {
        val hardwareState = simulatedState ?: querySystemThermalState()

        val targetFps = when (hardwareState) {
            ThermalOperationalState.NOMINAL -> if (manualStealthMode) 45 else 60
            ThermalOperationalState.WARM -> 45
            ThermalOperationalState.HOT -> 30
            ThermalOperationalState.CRITICAL -> 20
        }

        val renderGlow = hardwareState == ThermalOperationalState.NOMINAL && !manualStealthMode
        val renderDebug = hardwareState in listOf(ThermalOperationalState.NOMINAL, ThermalOperationalState.WARM) && !manualStealthMode

        val throttleMs = when (hardwareState) {
            ThermalOperationalState.NOMINAL -> 0L
            ThermalOperationalState.WARM -> 8L
            ThermalOperationalState.HOT -> 18L
            ThermalOperationalState.CRITICAL -> 35L
        }

        val msg = when {
            manualStealthMode -> "STEALTH ECO MODE (OLED Black • 95% Display Power Cut)"
            hardwareState == ThermalOperationalState.CRITICAL -> "THERMAL CRITICAL: Throttled to 20 FPS"
            hardwareState == ThermalOperationalState.HOT -> "THERMAL HOT: Throttled to 30 FPS • GPU Glow Disabled"
            hardwareState == ThermalOperationalState.WARM -> "THERMAL WARM: Adaptive 45 FPS Target"
            else -> "NOMINAL: 60 FPS Target • Full Hawk-Eye Render"
        }

        return ThermalStatusReport(
            state = hardwareState,
            isStealthEcoModeActive = manualStealthMode,
            targetAnalysisFps = targetFps,
            renderGlowEffects = renderGlow,
            renderDebugOverlays = renderDebug,
            adaptiveFrameThrottleMs = throttleMs,
            statusMessage = msg,
        )
    }

    private fun querySystemThermalState(): ThermalOperationalState {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && powerManager != null) {
            return when (powerManager.currentThermalStatus) {
                PowerManager.THERMAL_STATUS_NONE -> ThermalOperationalState.NOMINAL
                PowerManager.THERMAL_STATUS_LIGHT -> ThermalOperationalState.WARM
                PowerManager.THERMAL_STATUS_MODERATE -> ThermalOperationalState.HOT
                PowerManager.THERMAL_STATUS_SEVERE,
                PowerManager.THERMAL_STATUS_CRITICAL,
                PowerManager.THERMAL_STATUS_EMERGENCY,
                PowerManager.THERMAL_STATUS_SHUTDOWN -> ThermalOperationalState.CRITICAL
                else -> ThermalOperationalState.NOMINAL
            }
        }
        return ThermalOperationalState.NOMINAL
    }
}
