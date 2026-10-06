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
 * The replay's shots, cut the way a broadcast cuts a ball-tracking replay.
 *
 * LONG LENSES FROM FAR AWAY for the wide shots, as the broadcast does it: a camera close
 * behind the bowler squeezes the whole striker's end into a sliver, because perspective
 * halves things with every doubling of distance. And then the CLOSE shots the broadcast
 * cuts to for the answer — down on to the stumps, and from behind them — where the ball
 * meeting the wicket is the whole picture.
 */
enum class ReplayView(val label: String) {
    /** The director's cut: the ground, in behind the arm, then down on to the stumps. */
    AUTO("Auto"),

    /** The wickets close-up: above and behind the bowling side, looking down at the stumps. */
    STUMPS("Stumps"),

    /** Behind the striker's stumps, low, the ball coming at you. */
    KEEPER("Keeper"),

    /** Square of the pitch, for the drop and the bounce. */
    SIDE("Side"),

    /** Straight down, for where it pitched and where it went. */
    TOP("Top"),
    ;

    /** The shot at [progress] through the replay, 0..1. Only the director's cut moves. */
    fun cameraAt(progress: Double): ReplayCamera = when (this) {
        AUTO -> when {
            // Opens on the ground and pushes in behind the bowler's arm as the ball goes down,
            progress < CUT_AT -> ESTABLISH.lerp(BEHIND_ARM, smooth(progress / CUT_AT))
            // then a quick, eased cut down on to the stumps for the bounce and the arrival,
            progress < CUT_AT + CUT_LENGTH -> BEHIND_ARM.lerp(STUMP_CAM, smooth((progress - CUT_AT) / CUT_LENGTH))
            // and a slow creep in while the answer lands.
            else -> STUMP_CAM.lerp(STUMP_CAM_CLOSE, smooth((progress - CUT_AT - CUT_LENGTH) / (1 - CUT_AT - CUT_LENGTH)))
        }
        STUMPS -> STUMP_CAM
        else -> camera
    }

    /** The shot when it is not moving: where a glide between views aims. */
    val camera: ReplayCamera
        get() = when (this) {
            AUTO -> ESTABLISH
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

        val ESTABLISH = ReplayCamera(Point3(0.0, 50.0, 17.0), Point3(0.0, -8.0, 2.0), zoom = 2.2)

        // High enough — about twenty degrees down — that a ball in the air sits visibly
        // above its own shadow, with the stand still rising behind the striker's end.
        val BEHIND_ARM = ReplayCamera(Point3(0.0, 34.0, 11.0), Point3(0.0, -1.0, 2.2), zoom = 3.8)

        // The wickets close-up: high and back on the bowler's side, looking down on to the
        // stumps, so the ball runs in beneath the lens rather than through it.
        val STUMP_CAM = ReplayCamera(Point3(0.4, 6.2, 2.9), Point3(0.0, 0.0, 0.35), zoom = 1.9)
        val STUMP_CAM_CLOSE = ReplayCamera(Point3(0.35, 5.2, 2.4), Point3(0.0, 0.0, 0.38), zoom = 2.0)

        /** Ease in and out, so every move starts and settles like a camera operator's. */
        fun smooth(t: Double): Double {
            val x = t.coerceIn(0.0, 1.0)
            return x * x * (3 - 2 * x)
        }
    }
}
