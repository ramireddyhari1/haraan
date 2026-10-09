package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.atan2
import kotlin.math.hypot
import kotlin.math.sqrt

/**
 * Which way the phone is pointing, from gravity — the one thing the stumps could not tell.
 *
 * WHY. From the wicket alone, the camera's tilt and its height are tangled: a phone held
 * high and looking steeply down sees the stumps' feet exactly where a lower phone looking
 * less steeply does. [PitchGround] used to cut the knot by ASSUMING a height (1.4 m), and
 * every metre of the 3D flight scaled with that guess — on a real backyard throw the bounce
 * moved half a metre between 0.8 m and 1.6 m. Gravity unties it: the accelerometer gives the
 * tilt and roll outright, and with those known the stumps' span fixes the height.
 *
 * Every phone has the sensor, so this costs nothing on a budget handset.
 *
 * CONVENTION. The upright analysis frame is the device's natural portrait: picture-right is
 * the device's +x, picture-down its -y, and the lens looks along -z. The sensor reports the
 * reaction to gravity, i.e. a vector pointing UP. So, in [PitchGround]'s terms:
 *
 *   sin(tilt down) = gz / |g|,   roll = atan2(gx, gy).
 *
 * Fed from the camera screen's sensor listener; read wherever the ground is solved. Only a
 * STEADY reading is ever handed out: a phone being carried to its stand reads nothing useful.
 */
object DeviceTilt {

    /** The phone's attitude: [pitchRad] down from level (positive = looking down), [rollRad]. */
    data class Reading(val pitchRad: Double, val rollRad: Double)

    private val lock = Any()
    private var sx = 0.0
    private var sy = 0.0
    private var sz = 0.0
    private var wobble = Double.MAX_VALUE
    private var lastMs = 0L
    private var samples = 0

    /** One gravity (or accelerometer) sample, device axes, m/s². */
    fun onGravity(gx: Float, gy: Float, gz: Float, nowMs: Long) = synchronized(lock) {
        if (samples == 0 || nowMs - lastMs > STALE_MS) {
            sx = gx.toDouble(); sy = gy.toDouble(); sz = gz.toDouble()
            wobble = Double.MAX_VALUE
            samples = 0
        }
        val dev = sqrt((gx - sx) * (gx - sx) + (gy - sy) * (gy - sy) + (gz - sz) * (gz - sz))
        sx += (gx - sx) * SMOOTH
        sy += (gy - sy) * SMOOTH
        sz += (gz - sz) * SMOOTH
        wobble = if (wobble == Double.MAX_VALUE) dev else wobble + (dev - wobble) * SMOOTH
        lastMs = nowMs
        samples++
    }

    /** Forget everything — the screen closed, the next reading starts fresh. */
    fun clear() = synchronized(lock) { samples = 0; wobble = Double.MAX_VALUE }

    /** The attitude, or null unless the phone has been still and upright long enough to trust. */
    fun steady(nowMs: Long = System.currentTimeMillis()): Reading? = synchronized(lock) {
        if (samples < MIN_SAMPLES || nowMs - lastMs > STALE_MS || wobble > MAX_WOBBLE) return null
        reading(sx, sy, sz)
    }

    /** The attitude a gravity vector means, or null when the phone is not near upright. */
    fun reading(gx: Double, gy: Double, gz: Double): Reading? {
        val g = sqrt(gx * gx + gy * gy + gz * gz)
        if (abs(g - EARTH_G) > 2.0) return null
        val inPlane = hypot(gx, gy)
        // Lying flat or pointing at the sky: no pitch is being filmed.
        if (inPlane < 0.5 * g) return null
        return Reading(pitchRad = atan2(gz, inPlane), rollRad = atan2(gx, gy))
    }

    private const val EARTH_G = 9.81
    private const val SMOOTH = 0.1
    /** m/s² of shake, averaged. A phone on a stand reads ~0.01; in a hand, 0.1 and up. */
    private const val MAX_WOBBLE = 0.06
    private const val MIN_SAMPLES = 15
    private const val STALE_MS = 1_500L
}
