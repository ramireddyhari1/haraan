package com.haraan.app.vision

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageShader
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.ShaderBrush
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TileMode
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.DrawScope
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.max
import kotlin.math.sin

/*
 * THE GROUND AROUND THE PITCH, for the 3D replay.
 *
 * A cricket ground at night, built from the same few primitives as everything else in the
 * replay — flat polygons through [ReplayCamera] — so it moves with the camera exactly and
 * costs no 3D engine. Laid out on the pitch's own axes: the centre of the field is the
 * middle of the pitch, and the bowl rises in tiers beyond the rope.
 *
 * Far to near, always: a polygon drawn later covers one drawn earlier, and the only depth
 * test this renderer has is the order things are drawn in.
 */

private val Concrete = Color(0xFF14202C)
private val TierLow = Color(0xFF16243A)
private val TierHigh = Color(0xFF0F1A2D)
private val Wall = Color(0xFF0A111E)
private val Canopy = Color(0xFF070C16)
private val BoardDeep = Color(0xFF0A2A93)
private val BoardLight = Color(0xFF2563EB)
private val Sightscreen = Color(0xFF0B1324)
private val Rope = Color(0xFFEFF3FA)
private val Flood = Color(0xFFE9F1FF)

/** The middle of the field: the middle of the pitch. */
private const val CY = PitchGeometry.STUMPS_TO_STUMPS_M / 2.0

const val BOUNDARY_M = 64.0
private const val BOARDS_M = 67.0
private const val FIELD_EDGE_M = 70.0

/** (inner radius, outer radius, inner height, outer height, colour) for each tier. */
private val TIERS = listOf(
    doubleArrayOf(72.0, 90.0, 1.2, 11.0),
    doubleArrayOf(90.0, 108.0, 13.0, 25.0),
)
private val TIER_COLOURS = listOf(TierLow, TierHigh)

private const val SEGMENTS = 30

/** Crowd, as speckle: a stand at night reads as thousands of points of colour. */
internal fun crowdBrush(): ShaderBrush {
    val side = 64
    val pixels = IntArray(side * side)
    val random = java.util.Random(11)
    val palette = intArrayOf(0xFFFFFF, 0xDDE6FF, 0x4D8BFF, 0xF2C14E, 0xE5484D, 0x9AE6B4, 0xFFFFFF)
    for (i in pixels.indices) {
        if (random.nextInt(100) < 22) {
            val a = 40 + random.nextInt(110)
            pixels[i] = (a shl 24) or palette[random.nextInt(palette.size)]
        }
    }
    val bitmap = android.graphics.Bitmap.createBitmap(pixels, side, side, android.graphics.Bitmap.Config.ARGB_8888)
    return ShaderBrush(ImageShader(bitmap.asImageBitmap(), TileMode.Repeated, TileMode.Repeated))
}

private fun ring(r: Double, a: Double, z: Double) = Point3(r * cos(a), CY + r * sin(a), z)

/** Everything beyond the outfield grass: drawn after the sky, before the field. */
internal fun DrawScope.drawStadium(camera: ReplayCamera, crowd: ShaderBrush) {
    val w = size.width.toDouble()
    val h = size.height.toDouble()

    fun path(points: List<Point3>): Path? {
        val s = camera.polygon(points, w, h)
        if (s.size < 3 || offScreen(s, w, h)) return null
        return Path().apply {
            moveTo(s[0].x.toFloat(), s[0].y.toFloat())
            for (i in 1 until s.size) lineTo(s[i].x.toFloat(), s[i].y.toFloat())
            close()
        }
    }

    val step = 2 * PI / SEGMENTS

    // Light spilling up off the bowl into the night: the stadium's own glow.
    camera.project(Point3(0.0, CY, 40.0), w, h)?.let { sky ->
        val r = size.width * 1.1f
        val c = Offset(sky.x.toFloat(), sky.y.toFloat())
        drawCircle(Brush.radialGradient(listOf(Color(0xFF4D8BFF).copy(alpha = 0.16f), Color.Transparent), c, r), r, c)
    }

    // One list of every stand surface, then sorted far to near and drawn in that order.
    data class Face(val depth: Double, val draw: () -> Unit)
    val faces = ArrayList<Face>()

    for (i in 0 until SEGMENTS) {
        val a0 = i * step
        val a1 = a0 + step
        val mid = a0 + step / 2

        // Apron between the boards and the first tier.
        faces += Face(camera.toCamera(ring(FIELD_EDGE_M + 1, mid, 0.0)).z) {
            path(listOf(ring(FIELD_EDGE_M, a0, 0.0), ring(FIELD_EDGE_M, a1, 0.0), ring(72.0, a1, 1.2), ring(72.0, a0, 1.2)))
                ?.let { drawPath(it, Concrete) }
        }

        TIERS.forEachIndexed { t, tier ->
            val (r0, r1, z0, z1) = listOf(tier[0], tier[1], tier[2], tier[3])
            val seats = listOf(ring(r0, a0, z0), ring(r0, a1, z0), ring(r1, a1, z1), ring(r1, a0, z1))
            faces += Face(camera.toCamera(ring((r0 + r1) / 2, mid, (z0 + z1) / 2)).z) {
                path(seats)?.let {
                    drawPath(it, TIER_COLOURS[t])
                    drawPath(it, crowd, alpha = 0.9f)
                }
            }
            // The wall under the next tier's front edge.
            if (t + 1 < TIERS.size) {
                val next = TIERS[t + 1]
                faces += Face(camera.toCamera(ring(r1, mid, (z1 + next[2]) / 2)).z - 0.01) {
                    path(listOf(ring(r1, a0, z1), ring(r1, a1, z1), ring(r1, a1, next[2]), ring(r1, a0, next[2])))
                        ?.let { drawPath(it, Wall) }
                }
            }
        }

        // The roof: a dark canopy, and the strip of light along its front edge.
        faces += Face(camera.toCamera(ring(112.0, mid, 29.0)).z) {
            path(listOf(ring(104.0, a0, 30.0), ring(104.0, a1, 30.0), ring(120.0, a1, 31.0), ring(120.0, a0, 31.0)))
                ?.let { drawPath(it, Canopy) }
            path(listOf(ring(104.0, a0, 29.2), ring(104.0, a1, 29.2), ring(104.0, a1, 30.0), ring(104.0, a0, 30.0)))
                ?.let { drawPath(it, Flood.copy(alpha = 0.55f)) }
        }
    }
    faces.sortedByDescending { it.depth }.forEach { it.draw() }

    // Floodlights: a pair flanking each end and one on each side — a pylon, a bank of
    // lamps, and the glow they throw.
    for (deg in listOf(-75.0, -105.0, 75.0, 105.0, 0.0, 180.0)) {
        val a = deg * PI / 180
        val foot = ring(126.0, a, 0.0)
        val headCentre = ring(124.0, a, 52.0)
        val pa = camera.project(foot, w, h)
        val pb = camera.project(headCentre, w, h)
        if (pa != null && pb != null) {
            val px = max(1.5f, camera.pixels(1.2, headCentre, w, h).toFloat())
            drawLine(Color(0xFF1A2536), Offset(pa.x.toFloat(), pa.y.toFloat()), Offset(pb.x.toFloat(), pb.y.toFloat()), px, StrokeCap.Butt)
        }
        // The lamp bank faces the middle: a panel across the tangent.
        val tx = -sin(a)
        val ty = cos(a)
        val bank = listOf(
            Point3(headCentre.x - tx * 6, headCentre.y - ty * 6, 50.0),
            Point3(headCentre.x + tx * 6, headCentre.y + ty * 6, 50.0),
            Point3(headCentre.x + tx * 6, headCentre.y + ty * 6, 55.0),
            Point3(headCentre.x - tx * 6, headCentre.y - ty * 6, 55.0),
        )
        path(bank)?.let { drawPath(it, Flood) }
        val glowAt = camera.project(Point3(headCentre.x, headCentre.y, 52.5), w, h) ?: continue
        val c = Offset(glowAt.x.toFloat(), glowAt.y.toFloat())
        val r = max(40.0, camera.pixels(28.0, headCentre, w, h)).toFloat()
        drawCircle(
            Brush.radialGradient(listOf(Flood.copy(alpha = 0.55f), Color(0xFF4D8BFF).copy(alpha = 0.12f), Color.Transparent), c, r),
            r,
            c,
            blendMode = BlendMode.Plus,
        )
    }
}

/** The outfield, the boards, the rope and the sightscreens: drawn over the grass. */
internal fun DrawScope.drawFieldFurniture(camera: ReplayCamera) {
    val w = size.width.toDouble()
    val h = size.height.toDouble()

    fun path(points: List<Point3>): Path? {
        val s = camera.polygon(points, w, h)
        if (s.size < 3 || offScreen(s, w, h)) return null
        return Path().apply {
            moveTo(s[0].x.toFloat(), s[0].y.toFloat())
            for (i in 1 until s.size) lineTo(s[i].x.toFloat(), s[i].y.toFloat())
            close()
        }
    }

    val step = 2 * PI / 48

    // The rope, in short pieces so the near-plane cut never joins across the screen.
    for (i in 0 until 48) {
        val a = camera.project(ring(BOUNDARY_M, i * step, 0.05), w, h) ?: continue
        val b = camera.project(ring(BOUNDARY_M, (i + 1) * step, 0.05), w, h) ?: continue
        val px = max(1.2f, camera.pixels(0.12, ring(BOUNDARY_M, i * step, 0.0), w, h).toFloat())
        drawLine(Rope.copy(alpha = 0.85f), Offset(a.x.toFloat(), a.y.toFloat()), Offset(b.x.toFloat(), b.y.toFloat()), px, StrokeCap.Round)
    }

    // Sightscreens behind both ends, dark for a white ball.
    for (end in listOf(-1.0, 1.0)) {
        val y = CY + end * (BOARDS_M - 1.0)
        path(listOf(Point3(-7.0, y, 0.0), Point3(7.0, y, 0.0), Point3(7.0, y, 8.0), Point3(-7.0, y, 8.0)))
            ?.let { drawPath(it, Sightscreen) }
    }

    // LED boards: Haraan blue, with the H every few panels.
    for (i in 0 until 48) {
        val a0 = i * step
        val a1 = a0 + step
        val mid = a0 + step / 2
        // Not in front of the sightscreens.
        if (kotlin.math.abs(cos(mid)) < 0.11) continue
        val panel = listOf(ring(BOARDS_M, a0, 0.0), ring(BOARDS_M, a1, 0.0), ring(BOARDS_M, a1, 0.95), ring(BOARDS_M, a0, 0.95))
        path(panel)?.let { drawPath(it, if (i % 2 == 0) BoardDeep else BoardDeep.copy(red = 0.06f, green = 0.2f, blue = 0.66f)) }
        // A lit top edge.
        path(listOf(ring(BOARDS_M, a0, 0.85), ring(BOARDS_M, a1, 0.85), ring(BOARDS_M, a1, 0.95), ring(BOARDS_M, a0, 0.95)))
            ?.let { drawPath(it, BoardLight) }
        if (i % 3 == 0) {
            // The mark on the board's face: u along the board, v down it.
            // Read from the middle of the ground, so "right" along the board is clockwise.
            val tx = sin(mid)
            val ty = -cos(mid)
            val centre = ring(BOARDS_M - 0.02, mid, 0.45)
            HaraanMarkShapes.coarse.forEach { piece ->
                path(piece.map { (u, v) -> Point3(centre.x + tx * u * 0.62, centre.y + ty * u * 0.62, centre.z - v * 0.62) })
                    ?.let { drawPath(it, Color.White.copy(alpha = 0.92f)) }
            }
        }
    }
}

/** The field's own shape: everything inside the boards is grass. */
internal fun outfield(): List<Point3> = (0 until 72).map { i -> ring(FIELD_EDGE_M, 2 * PI * i / 72, 0.0) }
