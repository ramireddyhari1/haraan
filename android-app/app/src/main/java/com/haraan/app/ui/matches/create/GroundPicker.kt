package com.haraan.app.ui.matches.create

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.pressable
import kotlin.random.Random

// ─────────────────────────────────────────────────────────────────────────────
//  GROUND
//
//  What the match is played ON. In gully cricket it is the first thing anyone
//  asks — a tape ball on matting and the same ball on a cement strip are two
//  different games — and until now the wizard never asked, so every match filed
//  itself under the same nameless surface.
//
//  Each option is drawn rather than named: six lines of text all ending in the
//  word "pitch" are a spot-the-difference puzzle, while the tan weave of a coir
//  mat and the mown bands of a turf square are told apart at a glance.
// ─────────────────────────────────────────────────────────────────────────────
enum class GroundType(
    val label: String,
    val sub: String,
    /** Stored under `sport_state.format.ground`; the API validates this set. */
    val serverValue: String,
) {
    TURF("Natural turf", "Grass square", "turf"),
    MATTING("Matting", "Coir over a hard base", "matting"),
    CEMENT("Cement", "Concrete strip", "cement"),
    ASTRO("Astro turf", "Synthetic carpet", "astro"),
    MUD("Mud", "Bare rolled earth", "mud"),
    BOX("Box / indoor", "Netted arena", "box");

    companion object {
        fun fromServer(value: String?): GroundType =
            entries.firstOrNull { it.serverValue == value } ?: TURF
    }
}

@Composable
@OptIn(ExperimentalLayoutApi::class)
fun GroundPicker(selected: GroundType, onSelect: (GroundType) -> Unit) {
    FlowRow(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
        maxItemsInEachRow = 2,
    ) {
        GroundType.entries.forEach { ground ->
            GroundCard(
                ground = ground,
                selected = ground == selected,
                onClick = { onSelect(ground) },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun GroundCard(
    ground: GroundType,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val blue = HaraanColors.EventsBlue
    Column(
        modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(14.dp))
            .background(if (selected) HaraanColors.AccentTint else HaraanColors.Surface)
            .border(
                BorderStroke(
                    if (selected) 1.5.dp else 1.dp,
                    if (selected) blue else HaraanColors.BorderLight,
                ),
                RoundedCornerShape(14.dp),
            )
            .padding(7.dp),
    ) {
        Box(
            Modifier
                .fillMaxWidth()
                .height(66.dp)
                .clip(RoundedCornerShape(10.dp)),
        ) {
            Canvas(Modifier.fillMaxWidth().height(66.dp)) { drawGround(ground) }
            if (selected) {
                Box(
                    Modifier
                        .align(Alignment.TopEnd)
                        .padding(6.dp)
                        .size(18.dp)
                        .clip(RoundedCornerShape(9.dp))
                        .background(blue),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(12.dp))
                }
            }
        }
        Spacer(Modifier.height(7.dp))
        Text(
            ground.label,
            color = if (selected) blue else HaraanColors.TextPrimary,
            fontSize = 13.5.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
        )
        Spacer(Modifier.height(1.dp))
        Text(
            ground.sub,
            color = if (selected) blue.copy(alpha = 0.75f) else HaraanColors.TextMuted,
            fontSize = 11.sp,
            maxLines = 1,
        )
        Spacer(Modifier.height(2.dp))
    }
}

// ───────────────────────────────────────────────────────── The surfaces ─────
//
// Drawn, not photographed: a stock ground photo is somebody else's ground, and at
// 150dp wide it reads as a green smear. What is drawn here is the texture of each
// surface — mown bands, coir weave, slab joints, fibre, dried cracks, net — which
// is the part anyone who has played on it actually recognises.

private fun DrawScope.drawGround(ground: GroundType) = when (ground) {
    GroundType.TURF -> drawTurf()
    GroundType.MATTING -> drawMatting()
    GroundType.CEMENT -> drawCement()
    GroundType.ASTRO -> drawAstro()
    GroundType.MUD -> drawMud()
    GroundType.BOX -> drawBox()
}

/** Natural turf: mown bands, with the worn square down the middle and its creases. */
private fun DrawScope.drawTurf() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFF44935A), Color(0xFF2C7241))))
    val bandHeight = size.height / 7f
    var y = 0f
    var light = true
    while (y < size.height) {
        if (light) {
            drawRect(Color.White.copy(alpha = 0.07f), Offset(0f, y), Size(size.width, bandHeight))
        }
        y += bandHeight
        light = !light
    }
    // Bowled on for weeks, so the strip has gone bare and pale.
    val stripWidth = size.width * 0.20f
    val left = (size.width - stripWidth) / 2f
    drawRect(Color(0xFFC8BE8E).copy(alpha = 0.92f), Offset(left, 0f), Size(stripWidth, size.height))
    drawRect(Color(0xFF9C8F63).copy(alpha = 0.35f), Offset(left, 0f), Size(stripWidth * 0.22f, size.height))
    val crease = Color.White.copy(alpha = 0.85f)
    listOf(size.height * 0.18f, size.height * 0.82f).forEach { cy ->
        drawLine(
            crease,
            Offset(left - 3.dp.toPx(), cy),
            Offset(left + stripWidth + 3.dp.toPx(), cy),
            1.6.dp.toPx(),
        )
    }
}

/** Matting: a coir mat pinned over a hard base, its weave running both ways. */
private fun DrawScope.drawMatting() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFFA7AEB6), Color(0xFF8B939C))))
    val inset = size.width * 0.13f
    val matWidth = size.width - inset * 2f
    drawRect(Color(0xFFB2703C), Offset(inset, 0f), Size(matWidth, size.height))
    val warp = Color(0xFF7E4A22).copy(alpha = 0.30f)
    var y = 2.dp.toPx()
    while (y < size.height) {
        drawLine(warp, Offset(inset, y), Offset(inset + matWidth, y), 1.2.dp.toPx())
        y += 4.dp.toPx()
    }
    val weft = Color(0xFFD79A63).copy(alpha = 0.32f)
    var x = inset + 2.dp.toPx()
    while (x < inset + matWidth) {
        drawLine(weft, Offset(x, 0f), Offset(x, size.height), 1.dp.toPx())
        x += 4.dp.toPx()
    }
    // The pinned edges — the seam that tells matting from a plain brown strip.
    listOf(inset, inset + matWidth).forEach { edge ->
        drawLine(
            Color(0xFF6B3D1C).copy(alpha = 0.55f),
            Offset(edge, 0f),
            Offset(edge, size.height),
            1.5.dp.toPx(),
        )
    }
}

/** Cement: a poured strip, its slab joints and aggregate speckle. */
private fun DrawScope.drawCement() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFFB4BAC1), Color(0xFF959CA4))))
    val random = Random(7)
    repeat(90) {
        drawCircle(
            Color(0xFF6E757D).copy(alpha = 0.10f + random.nextFloat() * 0.16f),
            radius = (0.4f + random.nextFloat() * 0.9f).dp.toPx(),
            center = Offset(random.nextFloat() * size.width, random.nextFloat() * size.height),
        )
    }
    val joint = Color(0xFF6E757D).copy(alpha = 0.45f)
    drawLine(joint, Offset(size.width * 0.5f, 0f), Offset(size.width * 0.5f, size.height), 1.4.dp.toPx())
    drawLine(joint, Offset(0f, size.height * 0.58f), Offset(size.width, size.height * 0.58f), 1.4.dp.toPx())
    drawLine(
        Color.White.copy(alpha = 0.30f),
        Offset(size.width * 0.5f + 1.4.dp.toPx(), 0f),
        Offset(size.width * 0.5f + 1.4.dp.toPx(), size.height),
        1.dp.toPx(),
    )
}

/** Astro: dense synthetic fibre, and the painted lines every artificial pitch has. */
private fun DrawScope.drawAstro() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFF2FA25E), Color(0xFF1F7D48))))
    var x = 0f
    var light = true
    while (x < size.width) {
        drawLine(
            if (light) Color.White.copy(alpha = 0.09f) else Color.Black.copy(alpha = 0.07f),
            Offset(x, 0f),
            Offset(x, size.height),
            1.6.dp.toPx(),
        )
        x += 2.6.dp.toPx()
        light = !light
    }
    val line = Color.White.copy(alpha = 0.80f)
    drawLine(line, Offset(size.width * 0.16f, 0f), Offset(size.width * 0.16f, size.height), 2.dp.toPx())
    drawLine(line, Offset(size.width * 0.84f, 0f), Offset(size.width * 0.84f, size.height), 2.dp.toPx())
}

/** Mud: rolled earth that has dried out and opened up. */
private fun DrawScope.drawMud() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFFA1724A), Color(0xFF7A5333))))
    val crack = Color(0xFF4E3320).copy(alpha = 0.50f)
    val random = Random(11)
    repeat(7) {
        var px = random.nextFloat() * size.width
        var py = random.nextFloat() * size.height
        repeat(3) {
            val nx = (px + (random.nextFloat() - 0.5f) * size.width * 0.45f).coerceIn(0f, size.width)
            val ny = (py + (random.nextFloat() - 0.5f) * size.height * 0.7f).coerceIn(0f, size.height)
            drawLine(crack, Offset(px, py), Offset(nx, ny), 1.1.dp.toPx(), cap = StrokeCap.Round)
            px = nx
            py = ny
        }
    }
    repeat(14) {
        drawCircle(
            Color(0xFFD8B389).copy(alpha = 0.22f),
            radius = (0.7f + random.nextFloat() * 1.1f).dp.toPx(),
            center = Offset(random.nextFloat() * size.width, random.nextFloat() * size.height),
        )
    }
}

/** Box cricket: a lit floor seen through the net that closes the arena in. */
private fun DrawScope.drawBox() {
    drawRect(Brush.verticalGradient(listOf(Color(0xFF12283C), Color(0xFF1C5C3E))))
    drawRect(
        Brush.verticalGradient(listOf(Color(0xFF2A7D53), Color(0xFF1E6342))),
        Offset(0f, size.height * 0.52f),
        Size(size.width, size.height * 0.48f),
    )
    // The net runs across the whole tile, so the floor reads as being behind it
    // rather than beside it.
    val mesh = Color.White.copy(alpha = 0.22f)
    val step = 7.dp.toPx()
    var d = -size.height
    while (d < size.width + size.height) {
        drawLine(mesh, Offset(d, 0f), Offset(d + size.height, size.height), 0.9.dp.toPx())
        drawLine(mesh, Offset(d + size.height, 0f), Offset(d, size.height), 0.9.dp.toPx())
        d += step
    }
    drawLine(
        Color.White.copy(alpha = 0.55f),
        Offset(0f, size.height * 0.52f),
        Offset(size.width, size.height * 0.52f),
        1.4.dp.toPx(),
    )
    // Floodlight wash from the corner — box grounds are always played under light.
    drawCircle(
        Color(0xFFFFF6D8).copy(alpha = 0.13f),
        radius = size.width * 0.42f,
        center = Offset(size.width * 0.22f, -size.height * 0.1f),
    )
    drawRect(Color.Black.copy(alpha = 0.06f), style = Stroke(width = 2.dp.toPx()))
}
