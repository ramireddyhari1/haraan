package com.haraan.app.vision

import kotlin.math.abs

/** Which way the striker stands. The camera phone is a guest and is told, never knows. */
enum class BatterHand { RIGHT, LEFT }

/** The line words people actually say, from wide of off round to wide down leg. */
enum class LineBand(val spoken: String) {
    WIDE_OFF("Wide outside off"),
    OUTSIDE_OFF("Outside off"),
    OFF_STUMP("Off stump"),
    MIDDLE("Middle"),
    LEG_STUMP("Leg stump"),
    DOWN_LEG("Down leg"),
    WIDE_LEG("Wide down leg"),
}

/**
 * The delivery's line, in the batter's terms.
 *
 * Offsets are in metres from the middle stump, POSITIVE TOWARDS THE OFF SIDE — the picture's
 * left/right has already been turned into off/leg here, so nothing downstream has to know
 * which way the batter stands.
 *
 * [atStumps] is where the flight crossed the stump line, from the LBW projection — a
 * measured landmark, but a projection. [pitched] is where it bounced, from the pitch
 * calibration, and known to run short sideways. Either can be null, and [reason] says why
 * the stumps one is.
 */
data class DeliveryLine(
    val atStumps: LineBand?,
    val atStumpsOffM: Double?,
    val uncertaintyM: Double?,
    val pitched: LineBand?,
    val pitchedOffM: Double?,
    val reason: String?,
)

object DeliveryLines {

    /** Half the gap between stump centres: inside this the ball is on the middle stump. */
    const val MIDDLE_HALF_M = 0.048

    /** Past this the ball misses the outer stump altogether (half wicket + ball radius). */
    const val STUMP_EDGE_M = LbwProjector.HITTING_LIMIT_M

    /** The limited-overs wide guideline, measured from the middle stump. */
    const val WIDE_M = 0.89

    /** A crossing point, already turned to positive-is-off, as a band. */
    fun band(offTowardsOffM: Double): LineBand {
        val o = offTowardsOffM
        return when {
            abs(o) <= MIDDLE_HALF_M -> LineBand.MIDDLE
            o > 0 && o <= STUMP_EDGE_M -> LineBand.OFF_STUMP
            o < 0 && -o <= STUMP_EDGE_M -> LineBand.LEG_STUMP
            o > WIDE_M -> LineBand.WIDE_OFF
            o > 0 -> LineBand.OUTSIDE_OFF
            -o > WIDE_M -> LineBand.WIDE_LEG
            else -> LineBand.DOWN_LEG
        }
    }

    /**
     * Picture-right to off-side, for a camera BEHIND THE BOWLER'S ARM.
     *
     * A right-hander stands left shoulder to the bowler, facing the off side — which from
     * the bowler's end is to the LEFT. So for a right-hander the off side is negative in the
     * picture, and a left-hander is the mirror of that. Both the LBW offset and the bounce's
     * line are signed positive-to-the-right as seen from the bowler's end.
     */
    fun towardsOff(pictureRightM: Double, hand: BatterHand): Double =
        if (hand == BatterHand.RIGHT) -pictureRightM else pictureRightM

    fun of(metrics: FlightMetrics, hand: BatterHand): DeliveryLine {
        val lbw = metrics.lbw
        val stumpsOff = lbw.offsetM?.let { towardsOff(it, hand) }
        // The bounce's line is only signed from the bowler's end; from the other end the
        // picture is mirrored and the sign would lie.
        val pitchedOff = metrics.bounce
            ?.takeIf { it.cameraEnd == CameraEnd.BOWLER }
            ?.let { towardsOff(it.lineM, hand) }
        return DeliveryLine(
            atStumps = stumpsOff?.let(::band),
            atStumpsOffM = stumpsOff,
            uncertaintyM = lbw.uncertaintyM,
            pitched = pitchedOff?.let(::band),
            pitchedOffM = pitchedOff,
            reason = if (stumpsOff == null) lbw.basis else null,
        )
    }
}
