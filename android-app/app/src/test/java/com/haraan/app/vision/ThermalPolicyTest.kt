package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ThermalPolicyTest {

    @Test
    fun `nominal state targets 60 FPS with full visual effects`() {
        val manager = ThermalPolicyManager()
        manager.setSimulatedState(ThermalOperationalState.NOMINAL)

        val report = manager.evaluateThermalStatus()
        assertEquals(ThermalOperationalState.NOMINAL, report.state)
        assertEquals(60, report.targetAnalysisFps)
        assertTrue(report.renderGlowEffects)
        assertTrue(report.renderDebugOverlays)
        assertEquals(0L, report.adaptiveFrameThrottleMs)
    }

    @Test
    fun `hot state throttles target FPS and disables GPU glow effects`() {
        val manager = ThermalPolicyManager()
        manager.setSimulatedState(ThermalOperationalState.HOT)

        val report = manager.evaluateThermalStatus()
        assertEquals(ThermalOperationalState.HOT, report.state)
        assertEquals(30, report.targetAnalysisFps)
        assertFalse(report.renderGlowEffects)
        assertFalse(report.renderDebugOverlays)
        assertTrue(report.adaptiveFrameThrottleMs > 0L)
    }

    @Test
    fun `stealth eco mode disables display overlays while preserving core tracking`() {
        val manager = ThermalPolicyManager()
        manager.isStealthEcoMode = true

        val report = manager.evaluateThermalStatus()
        assertTrue(report.isStealthEcoModeActive)
        assertFalse(report.renderGlowEffects)
        assertFalse(report.renderDebugOverlays)
        assertTrue(report.statusMessage.contains("STEALTH ECO MODE"))
    }
}
