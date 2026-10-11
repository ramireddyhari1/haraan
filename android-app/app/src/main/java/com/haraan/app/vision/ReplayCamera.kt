package com.haraan.app.vision

import kotlin.math.sqrt

/**
 * A virtual camera over the pitch, for the 3D replay.
 *
 * Pitch metres in (x across, y from the striker's stumps, z up), screen pixels out. Plain
 * Kotlin so the projection the replay is drawn through can be tested at a desk — a replay
 * drawn through a camera that is quietly mirrored would put every outswinger on the leg
 * side, and nobody would know from looking.
 *
 * @param up which world direction is "up the screen". [Z] for any view that looks across
 *   the ground; something along the pitch for a view looking straight down.
 * @param zoom focal length as a fraction of [lensBasis] — the screen's width, or 0.6 of its
 *   height when that is smaller, so a portrait phone and a landscape one frame alike.
 */
class ReplayCamera(
    val eye: Point3,
    val target: Point3,
    val up: Point3 = Z,
    val zoom: Double = 1.0,
) {
    private val forward = unit(sub(target, eye))
    private val right = unit(cross(up, forward))
    private val down = cross(right, forward)

    /** [p] in camera space: x right, y down, z depth along the view. */
    fun toCamera(p: Point3): Point3 {
        val r = sub(p, eye)
        return Point3(dot(r, right), dot(r, down), dot(r, forward))
    }

    /** A camera-space point on a [width] x [height] screen, or null behind the near plane. */
    fun toScreen(c: Point3, width: Double, height: Double): Point2? {
        if (c.z < NEAR) return null
        val f = zoom * lensBasis(width, height)
        return Point2(width / 2 + f * c.x / c.z, height / 2 + f * c.y / c.z)
    }

    fun project(p: Point3, width: Double, height: Double): Point2? = toScreen(toCamera(p), width, height)

    /** How many pixels [metres] spans at [p]'s depth. Zero behind the camera. */
    fun pixels(metres: Double, p: Point3, width: Double, height: Double): Double {
        val z = toCamera(p).z
        if (z < NEAR) return 0.0
        return zoom * lensBasis(width, height) * metres / z
    }

    /**
     * A flat polygon on screen, cut at the near plane first.
     *
     * Without the cut, a ground quad that runs behind the viewer projects its far corners
     * through the lens and flips them across the screen — a pitch drawn inside out.
     */
    fun polygon(points: List<Point3>, width: Double, height: Double): List<Point2> {
        val cam = points.map(::toCamera)
        val clipped = ArrayList<Point3>()
        for (i in cam.indices) {
            val a = cam[i]
            val b = cam[(i + 1) % cam.size]
            val aIn = a.z >= NEAR
            val bIn = b.z >= NEAR
            if (aIn) clipped.add(a)
            if (aIn != bIn) {
                val t = (NEAR - a.z) / (b.z - a.z)
                clipped.add(Point3(a.x + (b.x - a.x) * t, a.y + (b.y - a.y) * t, NEAR))
            }
        }
        return clipped.mapNotNull { toScreen(it, width, height) }
    }

    /** Part-way from this camera to [other], for gliding between views. */
    fun lerp(other: ReplayCamera, t: Double) = ReplayCamera(
        eye = mix(eye, other.eye, t),
        target = mix(target, other.target, t),
        up = mix(up, other.up, t).let { if (dot(it, it) < 1e-9) other.up else it },
        zoom = zoom + (other.zoom - zoom) * t,
    )

    companion object {
        val Z = Point3(0.0, 0.0, 1.0)

        fun lensBasis(width: Double, height: Double) = minOf(width, height * 0.6)
        const val NEAR = 0.2

        private fun sub(a: Point3, b: Point3) = Point3(a.x - b.x, a.y - b.y, a.z - b.z)
        private fun dot(a: Point3, b: Point3) = a.x * b.x + a.y * b.y + a.z * b.z
        private fun cross(a: Point3, b: Point3) = Point3(
            a.y * b.z - a.z * b.y,
            a.z * b.x - a.x * b.z,
            a.x * b.y - a.y * b.x,
        )
        private fun unit(a: Point3): Point3 {
            val n = sqrt(dot(a, a)).takeIf { it > 1e-12 } ?: return Point3(1.0, 0.0, 0.0)
            return Point3(a.x / n, a.y / n, a.z / n)
        }
        private fun mix(a: Point3, b: Point3, t: Double) =
            Point3(a.x + (b.x - a.x) * t, a.y + (b.y - a.y) * t, a.z + (b.z - a.z) * t)
    }
}

/**
 * Where the director's cut changes shot, for a ball that pitches [bounceProgress] (0..1) of
 * the way through its flight: the end of the ride with the ball, of the drop to the turf, and
 * of the turf shot. Always in order and inside the flight — a yorker pitching at the very end
 * used to squeeze these past each other, and the replay crashed (`coerceIn` on an empty range)
 * on the first full ball from a real net clip.
 */
internal fun directorsCutMarks(bounceProgress: Double): Triple<Double, Double, Double> {
    val pb = bounceProgress.coerceIn(0.0, 1.0)
    val chaseEnd = (pb - 0.10).coerceIn(0.17, 0.78)
    val toTurfEnd = (pb - 0.02).coerceIn(chaseEnd + 0.04, 0.86)
    val turfEnd = (pb + (1.0 - pb) * 0.55).coerceIn(toTurfEnd + 0.04, 0.94)
    return Triple(chaseEnd, toTurfEnd, turfEnd)
}

/**
 * What a moving shot needs to know about the delivery: the flight, the replay's clock, and
 * how far through the hold after the ball arrived it is (0..1), for the closing orbit.
 */
class ShotContext(val flight: Flight3d, val startMs: Double, val endMs: Double, val hold: Double = 0.0) {
    fun msAt(progress: Double) = startMs + (endMs - startMs) * progress.coerceIn(0.0, 1.0)

    /** Where the ball is at [progress], never below the turf. */
    fun ballAt(progress: Double): Point3 = flight.at(msAt(progress)).let { Point3(it.x, it.y, it.z.coerceAtLeast(0.0)) }

    /** How far through the flight the ball pitches, 0..1. */
    val bounceProgress: Double
        get() = ((flight.bounceMs - startMs) / (endMs - startMs).coerceAtLeast(1.0)).coerceIn(0.05, 0.95)
}

/**
 * The replay's shots, cut the way a broadcast cuts a ball-tracking replay.
 *
 * LONG LENSES FROM FAR AWAY for the wide shots, as the broadcast does it: a camera close
 * behind the bowler squeezes the whole striker's end into a sliver, because perspective
 * halves things with every doubling of distance. And then the CLOSE shots the broadcast
 * cuts to for the answer — down on to the stumps, and from behind them — where the ball
 * meeting the wicket is the whole picture.
 *
 * THE SHOTS FOLLOW THIS BALL. They used to be fixed cameras: a wide ball pitched out of the
 * side view's frame, the keeper's view stared at a fixed spot, and nothing moved with the
 * ball, so the replay felt like a diagram. Given a [ShotContext] each shot is placed for
 * this delivery — the side camera square of where it pitched, the top view over the bounce
 * and the stumps, the keeper's camera turning with the ball — and the director's cut rides
 * with the ball, drops to the turf for the bounce and circles the stumps for the answer.
 */
enum class ReplayView(val label: String) {
    /** The director's cut: the ground, the ball's flight, the bounce at turf level, the stumps. */
    AUTO("Auto"),

    /** Riding behind and above the ball all the way down, as a cable camera does. */
    BALL("Ball"),

    /** The wickets close-up, circling them once the ball has arrived. */
    STUMPS("Stumps"),

    /** Behind the striker's stumps, low, turning with the ball as it comes at you. */
    KEEPER("Keeper"),

    /** Square of where it pitched, for the drop and the bounce. */
    SIDE("Side"),

    /** Straight down over the bounce and the stumps, for where it pitched and where it went. */
    TOP("Top"),
    ;

    /** The fixed-camera shot at [progress], 0..1, for when no delivery is known. */
    fun cameraAt(progress: Double): ReplayCamera = when (this) {
        AUTO -> when {
            progress < CUT_AT -> ESTABLISH.lerp(BEHIND_ARM, smooth(progress / CUT_AT))
            progress < CUT_AT + CUT_LENGTH -> BEHIND_ARM.lerp(STUMP_CAM, smooth((progress - CUT_AT) / CUT_LENGTH))
            else -> STUMP_CAM.lerp(STUMP_CAM_CLOSE, smooth((progress - CUT_AT - CUT_LENGTH) / (1 - CUT_AT - CUT_LENGTH)))
        }
        STUMPS -> STUMP_CAM
        else -> camera
    }

    /** The shot at [progress] for this delivery; see the class note. */
    fun cameraAt(progress: Double, shot: ShotContext): ReplayCamera = when (this) {
        AUTO -> directorsCut(progress, shot)
        BALL -> when {
            progress >= 1.0 -> orbit(shot.hold, shot)
            progress < OPEN_END -> ESTABLISH.lerp(chase(OPEN_END, shot), smooth(progress / OPEN_END))
            // Over the last stretch, settle in on the stumps for the answer.
            progress > BALL_SETTLE -> chase(progress, shot).lerp(orbit(0.0, shot), smooth((progress - BALL_SETTLE) / (1.0 - BALL_SETTLE)))
            else -> chase(progress, shot)
        }
        STUMPS -> if (progress < 1.0) STUMP_CAM.lerp(orbit(0.0, shot), smooth(progress)) else orbit(shot.hold, shot)
        KEEPER -> keeper(progress, shot)
        SIDE -> side(shot)
        TOP -> top(shot)
    }

    /** The shot when it is not moving: where a glide between views aims. */
    val camera: ReplayCamera
        get() = when (this) {
            AUTO -> ESTABLISH
            BALL -> BEHIND_ARM
            STUMPS -> STUMP_CAM
            KEEPER -> ReplayCamera(Point3(0.45, -4.6, 1.15), Point3(0.0, 7.0, 0.55), zoom = 1.25)
            // Square on to the business end — release is off to the left; the drop, the
            // bounce and the stumps fill the frame.
            SIDE -> ReplayCamera(Point3(24.0, 6.5, 2.4), Point3(0.0, 6.5, 2.4), zoom = 1.55)
            TOP -> ReplayCamera(
                Point3(0.0, 8.5, 52.0),
                Point3(0.0, 8.5, 0.0),
                // Striker at the top of the screen, as a pitch map is read.
                up = Point3(0.0, -1.0, 0.0),
                zoom = 3.4,
            )
        }

    private companion object {
        const val CUT_AT = 0.5
        const val CUT_LENGTH = 0.14

        /** The moving shots open wide for this share of the flight. */
        const val OPEN_END = 0.12

        /** The ball camera starts settling on the stumps here. */
        const val BALL_SETTLE = 0.85

        val ESTABLISH = ReplayCamera(Point3(0.0, 50.0, 17.0), Point3(0.0, -8.0, 2.0), zoom = 2.2)

        // High enough — about twenty degrees down — that a ball in the air sits visibly
        // above its own shadow, with the stand still rising behind the striker's end.
        val BEHIND_ARM = ReplayCamera(Point3(0.0, 34.0, 11.0), Point3(0.0, -1.0, 2.2), zoom = 3.8)

        // The wickets close-up: high and back on the bowler's side, looking down on to the
        // stumps, so the ball runs in beneath the lens rather than through it.
        val STUMP_CAM = ReplayCamera(Point3(0.4, 6.2, 2.9), Point3(0.0, 0.0, 0.35), zoom = 1.9)
        val STUMP_CAM_CLOSE = ReplayCamera(Point3(0.35, 5.2, 2.4), Point3(0.0, 0.0, 0.38), zoom = 2.0)

        /*
         * THE CUT, for this ball:
         *   wide from high behind the bowler, pushing in as the ball is let go;
         *   riding with the ball down the pitch — the ground streaming under it is the pace;
         *   a quick cut down to the turf beside where it pitches, the ball dropping in, kicking
         *   up and carrying on to the stumps in front of the lens;
         *   and once it is there, close on the stumps, circling slowly while the answer shows.
         */
        fun directorsCut(p: Double, shot: ShotContext): ReplayCamera {
            val (chaseEnd, toTurfEnd, turfEnd) = directorsCutMarks(shot.bounceProgress)
            return when {
                p >= 1.0 -> orbit(shot.hold, shot)
                p < OPEN_END -> ESTABLISH.lerp(chase(OPEN_END, shot), smooth(p / OPEN_END))
                p < chaseEnd -> chase(p, shot)
                p < toTurfEnd -> chase(p, shot).lerp(turf(shot), smooth((p - chaseEnd) / (toTurfEnd - chaseEnd)))
                p < turfEnd -> turf(shot)
                else -> turf(shot).lerp(orbit(0.0, shot), smooth((p - turfEnd) / (1.0 - turfEnd)))
            }
        }

        /** Behind and above the ball, looking down the pitch past it. */
        fun chase(p: Double, shot: ShotContext): ReplayCamera {
            val b = shot.ballAt(p)
            // Never past the bowler's side of the stumps: near them it holds back and lets the
            // ball run away into the frame.
            val eye = Point3(b.x * 0.5, (b.y + CHASE_BEHIND_M).coerceAtLeast(5.0), b.z + CHASE_ABOVE_M)
            val target = Point3(b.x * 0.8, (b.y - 10.0).coerceAtLeast(-3.0), b.z * 0.4 + 0.3)
            return ReplayCamera(eye, target, zoom = 1.45)
        }

        /** Down on the turf, off to the side of where it pitched, looking along the line. */
        fun turf(shot: ShotContext): ReplayCamera {
            val bx = shot.flight.bounceX
            val by = shot.flight.bounceY
            // On the side the ball is NOT on, so it never passes between the lens and its line.
            val side = if (bx > 0) -1.0 else 1.0
            val eye = Point3(bx + side * 3.4, by + 4.2, 0.55)
            val target = Point3(bx * 0.4, (by - 3.5).coerceAtLeast(-0.5), 0.45)
            return ReplayCamera(eye, target, zoom = 1.25)
        }

        /** Close on the stumps, turning round them over the hold. */
        fun orbit(hold: Double, shot: ShotContext): ReplayCamera {
            // Starts a little to the side the ball passed, so its line beside them shows.
            val start = if (shot.flight.bounceX > 0) -0.30 else 0.30
            val a = start + (if (start < 0) 1 else -1) * ORBIT_TURN_RAD * smooth(hold)
            val eye = Point3(ORBIT_RADIUS_M * kotlin.math.sin(a), ORBIT_RADIUS_M * kotlin.math.cos(a), 2.3)
            return ReplayCamera(eye, Point3(0.0, 0.0, 0.42), zoom = 2.0)
        }

        /** Behind the striker, low, the lens turning to keep the ball near the middle. */
        fun keeper(p: Double, shot: ShotContext): ReplayCamera {
            val b = shot.ballAt(p)
            val look = Point3(b.x * 0.6, b.y.coerceAtLeast(2.0), b.z * 0.7 + 0.25)
            val calm = Point3(0.0, 7.0, 0.55)
            val target = Point3(calm.x + (look.x - calm.x) * 0.65, calm.y + (look.y - calm.y) * 0.65, calm.z + (look.z - calm.z) * 0.65)
            return ReplayCamera(Point3(0.45, -4.6, 1.15), target, zoom = 1.25)
        }

        /*
         * Square of where this ball pitched, the bounce in the middle of the frame — near and
         * a little above, so the pitch is a surface the ball drops on to rather than a line it
         * runs along (24 m away at head height it was a sliver).
         */
        fun side(shot: ShotContext): ReplayCamera {
            val y = (shot.flight.bounceY * 0.6).coerceIn(2.0, 8.0)
            return ReplayCamera(Point3(12.5, y + 0.5, 4.2), Point3(0.0, y - 0.5, 0.7), zoom = 1.6)
        }

        /** Over the bounce and the stumps, striker at the top. */
        fun top(shot: ShotContext): ReplayCamera {
            val y = (shot.flight.bounceY / 2.0).coerceIn(2.5, 9.0)
            val x = shot.flight.bounceX / 2.0
            return ReplayCamera(Point3(x, y, 44.0), Point3(x, y, 0.0), up = Point3(0.0, -1.0, 0.0), zoom = 3.4)
        }

        const val CHASE_BEHIND_M = 6.5
        const val CHASE_ABOVE_M = 2.4
        const val ORBIT_RADIUS_M = 5.4
        const val ORBIT_TURN_RAD = 0.55

        /** Ease in and out, so every move starts and settles like a camera operator's. */
        fun smooth(t: Double): Double {
            val x = t.coerceIn(0.0, 1.0)
            return x * x * (3 - 2 * x)
        }
    }
}
