package com.haraan.app.vision

import kotlin.math.abs
import kotlin.math.exp
import kotlin.math.sqrt

/**
 * HOW FAST THE BALL LEFT THE HAND — the number a speed gun, Fulltrack and broadcast all show.
 *
 * [QuadSpeed] fits the flight before the bounce at one steady speed, which is the AVERAGE over
 * the stretch it saw. A ball is fastest at release and slows all the way down the pitch,
 * because air drag takes a share of its speed for every metre it travels: v(s) = v₀·e^(−k·s),
 * k = ρ·Cd·A / 2m. So the fitted average always reads under the release speed — by about 7%
 * for a leather ball over a full delivery, and by about 17% for a tennis ball, which is light
 * and fuzzy and loses pace three times as fast. Reporting the average was why the camera read
 * low against a speed gun.
 *
 * k per ball, from its mass, diameter and a drag coefficient at bowling speeds (air 1.2 kg/m³):
 *  · leather / season / cork — 156–163 g, 72 mm, Cd ≈ 0.45 → 0.0068 per metre
 *  · tennis — 57 g, 67 mm, the felt drags hard, Cd ≈ 0.6 → 0.022
 *  · tape (a tennis ball wound in tape: heavier, smooth) — ~80 g, 68 mm, Cd ≈ 0.45 → 0.012
 *  · rubber — ~110 g, 70 mm, Cd ≈ 0.45 → 0.0095
 *  · synthetic / plastic match ball — ~130 g, 71 mm, Cd ≈ 0.45 → 0.0082
 * Estimates, not measurements: a worn tennis ball or a damp one drags differently, which is
 * why the ball is a choice the person filming can correct.
 */
enum class BallDrag(val serverValue: String, val label: String, val perMetre: Double) {
    LEATHER("leather", "Leather", 0.0068),
    TENNIS("tennis", "Tennis", 0.022),
    TAPE("tape", "Tape", 0.012),
    RUBBER("rubber", "Rubber", 0.0095),
    SYNTHETIC("synthetic", "Synthetic", 0.0082),
    ;

    /**
     * Release speed, m/s, from a constant speed [fittedMps] fitted to sightings that ran from
     * [fromM] to [toM] metres down the pitch from the release point.
     *
     * For v(s) = v₀·e^(−k·s) the time to cover that stretch is (e^(k·to) − e^(k·from)) / (k·v₀), so
     * a straight-line fit through it reads (to − from)·k·v₀ / (e^(k·to) − e^(k·from)). Solved for
     * v₀. A stretch too short to measure falls back to the speed at its middle.
     */
    fun releaseFrom(fittedMps: Double, fromM: Double, toM: Double): Double {
        val k = perMetre
        val a = fromM.coerceAtLeast(0.0)
        val b = toM.coerceAtLeast(a)
        return if (b - a < 0.5) {
            fittedMps * exp(k * (a + b) / 2.0)
        } else {
            fittedMps * (exp(k * b) - exp(k * a)) / (k * (b - a))
        }
    }

    companion object {
        /** The match's ball as the server stores it; season and cork fly as leather does. */
        fun fromServer(value: String?): BallDrag? = when (value?.trim()?.lowercase()) {
            "leather", "season", "cork" -> LEATHER
            "tennis" -> TENNIS
            "tape" -> TAPE
            "rubber" -> RUBBER
            "synthetic" -> SYNTHETIC
            else -> null
        }

        /** The choices offered to the person filming, most common first. */
        val CHOICES = listOf(TENNIS, LEATHER, TAPE, RUBBER, SYNTHETIC)
    }
}

/**
 * The release speed of a fitted [Flight3d], km/h: the ground speed carried back to the hand
 * through [Flight3d.ball]'s drag, with the vertical speed it was let go at.
 */
internal fun releaseKmh(f: Flight3d): Double {
    val ground = f.groundKmh / 3.6
    if (ground <= 0.0 || f.inVy >= 0.0) return f.groundKmh

    // Where the fitted sightings before the bounce began and ended, down the pitch.
    val firstMs = f.firstSeenMs
    val lastMs = minOf(f.lastSeenMs, f.bounceMs)
    val yFirst = f.at(firstMs).y
    val yLast = f.at(lastMs).y
    // The hand is at the release point, or further back if the ball was first seen beyond it.
    val releaseY = maxOf(Flight3d.RELEASE_Y_M, yFirst)
    val horizontal = f.ball.releaseFrom(ground, releaseY - yFirst, releaseY - yLast)

    // Down at the bounce is [inVzDown]; gravity added that much over the flight, so less of it
    // (or some upward speed, for a bouncer's high release) was there at the hand.
    val flightS = (releaseY - f.bounceY) / ground
    val vz = f.inVzDown - Flight3d.G * flightS
    val withVertical = if (abs(vz) < 0.5 * horizontal) sqrt(horizontal * horizontal + vz * vz) else horizontal
    return withVertical * 3.6
}
