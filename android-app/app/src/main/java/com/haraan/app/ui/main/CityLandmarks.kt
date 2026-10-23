package com.haraan.app.ui.main

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.haraan.app.ui.theme.HaraanColors

/**
 * Each served city's own landmark, drawn as one-colour line art — the thing someone
 * from there would recognise in a glance: Charminar, the Gateway of India, Vidhana
 * Soudha. Drawn in code on a 64 × 64 grid (ground line at y = 56), so they stay crisp
 * at any size and cost no assets.
 *
 * A city without a drawing returns null and the caller shows the plain pin instead —
 * never a generic skyline standing in for a place it isn't.
 */
internal object CityLandmarks {

    /** Drawings by catalog name (lower-case). Aliases share the same art. */
    private val builders: Map<String, LandmarkArt.() -> Unit> = mapOf(
        "hyderabad" to { charminar() },
        "secunderabad" to { charminar() },
        "mumbai" to { gatewayOfIndia() },
        "bengaluru" to { vidhanaSoudha() },
        "bangalore" to { vidhanaSoudha() },
        "chennai" to { chennaiCentral() },
        "kadapa" to { ameenPeerDargah() },
        "vijayawada" to { prakasamBarrage() },
        "visakhapatnam" to { kursuraSubmarine() },
        "vizag" to { kursuraSubmarine() },
        "tirupati" to { gopuram() },
    )

    private val cache = mutableMapOf<String, List<Path>?>()

    fun has(city: String): Boolean = builders.containsKey(city.trim().lowercase())

    /** The drawing as single-contour strokes in 64-unit space, or null if none. */
    fun parts(city: String): List<Path>? {
        val key = city.trim().lowercase()
        return cache.getOrPut(key) {
            builders[key]?.let { build -> LandmarkArt().apply(build).parts }
        }
    }
}

/**
 * A city's landmark. When [selected] turns true the drawing re-inks itself stroke by
 * stroke in blue, like a pen going over it, with a soft sun rising behind; unselected
 * it rests in slate. Nothing animates while idle.
 */
@Composable
internal fun CityLandmark(
    city: String,
    selected: Boolean,
    modifier: Modifier = Modifier,
) {
    val parts = remember(city) { CityLandmarks.parts(city) } ?: return
    val lengths = remember(parts) {
        val pm = PathMeasure()
        parts.map { pm.setPath(it, false); pm.length }
    }
    val total = remember(lengths) { lengths.sum() }
    val ink = remember { Animatable(1f) }
    LaunchedEffect(selected) {
        if (selected) {
            ink.snapTo(0f)
            ink.animateTo(1f, tween(900, easing = FastOutSlowInEasing))
        }
    }

    Canvas(modifier.semantics { contentDescription = "$city landmark" }) {
        val s = size.minDimension / 64f
        val dx = (size.width - 64f * s) / 2f
        val dy = (size.height - 64f * s) / 2f
        val color = if (selected) HaraanColors.EventsBlue else Color(0xFF64748B)
        val stroke = Stroke(
            width = 1.7.dp.toPx() / s,
            cap = StrokeCap.Round,
            join = StrokeJoin.Round,
        )
        val progress = if (selected) ink.value else 1f
        translate(dx, dy) { scale(s, s, pivot = Offset.Zero) {
            if (selected) {
                drawCircle(HaraanColors.EventsBlue.copy(alpha = 0.10f * progress), 9f, Offset(50f, 15f))
            }
            var budget = total * progress
            val pm = PathMeasure()
            for (i in parts.indices) {
                if (budget <= 0f) break
                val len = lengths[i]
                if (budget >= len) {
                    drawPath(parts[i], color, style = stroke)
                } else {
                    val dst = Path()
                    pm.setPath(parts[i], false)
                    pm.getSegment(0f, budget, dst, true)
                    drawPath(dst, color, style = stroke)
                }
                budget -= len
            }
        } }
    }
}

/** A tiny pen for the drawings: every call adds one stroke (one contour). */
internal class LandmarkArt {
    val parts = mutableListOf<Path>()

    fun line(x1: Float, y1: Float, x2: Float, y2: Float) {
        parts += Path().apply { moveTo(x1, y1); lineTo(x2, y2) }
    }

    fun box(l: Float, t: Float, r: Float, b: Float) {
        parts += Path().apply { addRect(Rect(l, t, r, b)) }
    }

    fun ring(cx: Float, cy: Float, r: Float) {
        parts += Path().apply { addOval(Rect(cx - r, cy - r, cx + r, cy + r)) }
    }

    fun pen(block: Path.() -> Unit) {
        parts += Path().apply(block)
    }

    /** A round-headed arch standing on the ground at [base]. */
    fun arch(x1: Float, x2: Float, spring: Float, base: Float = 56f) = pen {
        val mid = (x1 + x2) / 2f
        val rise = (x2 - x1) * 0.55f
        moveTo(x1, base)
        lineTo(x1, spring)
        quadraticTo(mid, spring - rise * 1.5f, x2, spring)
        lineTo(x2, base)
    }

    fun ground() = line(4f, 56f, 60f, 56f)
}

// --- The drawings -------------------------------------------------------------------

/** Hyderabad: four minarets round a square of arches, a small dome on the gallery. */
private fun LandmarkArt.charminar() {
    ground()
    box(19f, 31f, 45f, 56f)
    arch(26f, 38f, 44f)
    line(19f, 36f, 45f, 36f)
    // Gallery of small arches on top of the body.
    box(19f, 26f, 45f, 31f)
    for (x in listOf(21f, 27f, 33f, 39f)) arch(x, x + 4f, 30f, base = 31f)
    pen { moveTo(28f, 26f); quadraticTo(32f, 19f, 36f, 26f) }
    // Minarets with their balconies and onion caps.
    for (x in listOf(14f, 45f)) {
        box(x, 18f, x + 5f, 56f)
        line(x - 1.5f, 31f, x + 6.5f, 31f)
        line(x - 1.5f, 42f, x + 6.5f, 42f)
        pen { moveTo(x, 18f); quadraticTo(x + 2.5f, 11f, x + 5f, 18f) }
        line(x + 2.5f, 12.5f, x + 2.5f, 9f)
    }
}

/** Mumbai: the wide gateway — tall central arch, side arches, four turrets. */
private fun LandmarkArt.gatewayOfIndia() {
    ground()
    box(9f, 25f, 55f, 56f)
    line(8f, 25f, 56f, 25f)
    line(9f, 29f, 55f, 29f)
    arch(24f, 40f, 40f)
    arch(12.5f, 19.5f, 46f)
    arch(44.5f, 51.5f, 46f)
    for (x in listOf(11f, 21f, 43f, 53f)) {
        box(x - 2f, 19f, x + 2f, 25f)
        pen { moveTo(x - 2f, 19f); quadraticTo(x, 13.5f, x + 2f, 19f) }
    }
    // The central dome behind the arch.
    pen { moveTo(26f, 25f); quadraticTo(32f, 14f, 38f, 25f) }
}

/** Bengaluru: Vidhana Soudha — colonnaded portico, pediment, central dome. */
private fun LandmarkArt.vidhanaSoudha() {
    ground()
    box(5f, 37f, 59f, 56f)
    line(4f, 37f, 60f, 37f)
    // Portico with its columns and pediment.
    box(21f, 31f, 43f, 56f)
    for (x in listOf(25f, 29f, 35f, 39f)) line(x, 35f, x, 56f)
    pen { moveTo(19f, 31f); lineTo(32f, 24.5f); lineTo(45f, 31f); close() }
    // Drum and dome.
    box(27.5f, 18f, 36.5f, 24.5f)
    pen { moveTo(26.5f, 18f); quadraticTo(32f, 7f, 37.5f, 18f) }
    line(32f, 10.5f, 32f, 6f)
    // Windows in the wings.
    for (x in listOf(8f, 13f, 47f, 52f)) {
        box(x, 42f, x + 3f, 46f)
        box(x, 49f, x + 3f, 53f)
    }
}

/** Chennai: Chennai Central — long front, corner turrets, the clock tower and spire. */
private fun LandmarkArt.chennaiCentral() {
    ground()
    box(6f, 39f, 58f, 56f)
    for (x in listOf(6f, 52f)) {
        box(x, 33f, x + 6f, 56f)
        pen { moveTo(x, 33f); lineTo(x + 3f, 27.5f); lineTo(x + 6f, 33f) }
    }
    for (x in listOf(15f, 20f, 41f, 46f)) arch(x, x + 3f, 46f, base = 51f)
    // The clock tower.
    box(27f, 17f, 37f, 56f)
    ring(32f, 25f, 3f)
    line(32f, 25f, 32f, 23f)
    arch(29.5f, 34.5f, 47f)
    pen { moveTo(27f, 17f); lineTo(32f, 6f); lineTo(37f, 17f) }
    line(32f, 6f, 32f, 3f)
}

/** Kadapa: Ameen Peer Dargah — the great bulb dome on its drum, two slim minarets. */
private fun LandmarkArt.ameenPeerDargah() {
    ground()
    box(14f, 37f, 50f, 56f)
    arch(28f, 36f, 47f)
    line(13f, 37f, 51f, 37f)
    box(20f, 31f, 44f, 37f)
    pen {
        moveTo(20f, 31f)
        cubicTo(17f, 22f, 25f, 14.5f, 32f, 12.5f)
        cubicTo(39f, 14.5f, 47f, 22f, 44f, 31f)
    }
    line(32f, 12.5f, 32f, 7f)
    ring(32f, 5.5f, 1.4f)
    for (x in listOf(7f, 53f)) {
        box(x, 27f, x + 4f, 56f)
        line(x - 1f, 40f, x + 5f, 40f)
        pen { moveTo(x, 27f); quadraticTo(x + 2f, 21f, x + 4f, 27f) }
    }
}

/** Vijayawada: Prakasam Barrage's run of arches across the Krishna, the hill behind. */
private fun LandmarkArt.prakasamBarrage() {
    pen {
        moveTo(4f, 38f)
        quadraticTo(17f, 18f, 30f, 28f)
        quadraticTo(42f, 17f, 60f, 36f)
    }
    // The temple on the hilltop.
    pen { moveTo(15f, 25f); lineTo(16.5f, 20f); lineTo(19.5f, 20f); lineTo(21f, 25f) }
    line(4f, 40f, 60f, 40f)
    line(4f, 44f, 60f, 44f)
    for (i in 0 until 7) {
        val x = 5f + i * 8f
        arch(x, x + 6f, 49f, base = 53f)
    }
    pen { moveTo(6f, 58f); quadraticTo(10f, 56f, 14f, 58f); quadraticTo(18f, 60f, 22f, 58f) }
    pen { moveTo(36f, 58f); quadraticTo(40f, 56f, 44f, 58f); quadraticTo(48f, 60f, 52f, 58f) }
}

/** Visakhapatnam: INS Kursura, the submarine on the beach, on its stands. */
private fun LandmarkArt.kursuraSubmarine() {
    ground()
    pen {
        moveTo(6f, 44f)
        quadraticTo(8f, 37f, 19f, 37f)
        lineTo(49f, 37f)
        quadraticTo(59f, 39f, 59f, 44f)
        quadraticTo(57f, 50f, 48f, 50f)
        lineTo(15f, 50f)
        quadraticTo(6f, 50f, 6f, 44f)
        close()
    }
    pen { moveTo(26f, 37f); lineTo(28f, 27f); lineTo(39f, 27f); lineTo(40f, 37f) }
    line(35f, 27f, 35f, 20f)
    line(35f, 20f, 38.5f, 20f)
    for (x in listOf(17f, 47f)) line(x, 50f, x, 56f)
    for (x in listOf(20f, 30f, 40f)) ring(x, 43.5f, 1.6f)
    pen { moveTo(4f, 60f); quadraticTo(9f, 58f, 14f, 60f); quadraticTo(19f, 62f, 24f, 60f) }
}

/** Tirupati: a temple gopuram — tiers narrowing up to the barrel vault and kalasams. */
private fun LandmarkArt.gopuram() {
    ground()
    box(17f, 45f, 47f, 56f)
    arch(28f, 36f, 50f)
    pen { moveTo(17f, 45f); lineTo(19.5f, 37f); lineTo(44.5f, 37f); lineTo(47f, 45f) }
    pen { moveTo(20.5f, 37f); lineTo(22.5f, 30f); lineTo(41.5f, 30f); lineTo(43.5f, 37f) }
    pen { moveTo(23.5f, 30f); lineTo(25f, 24f); lineTo(39f, 24f); lineTo(40.5f, 30f) }
    pen { moveTo(26f, 24f); quadraticTo(26f, 17f, 32f, 17f); quadraticTo(38f, 17f, 38f, 24f) }
    for (x in listOf(28.5f, 32f, 35.5f)) {
        line(x, 17.5f, x, 13f)
        ring(x, 12f, 1f)
    }
}
