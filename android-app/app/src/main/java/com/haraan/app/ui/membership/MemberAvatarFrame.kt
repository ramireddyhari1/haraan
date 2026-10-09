package com.haraan.app.ui.membership

import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.remember
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Modifier
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.unit.sp
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.painter.Painter
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import com.haraan.app.data.SquadMember
import kotlin.math.cos
import kotlin.math.max
import kotlin.math.sin

/**
 * Who in this match wears a Pro / Hero mark, looked up by player id first and then by name
 * (most scoring rows only carry the name the scorer typed). Provided once by the match
 * screen so every face on every tab can ask without the state being threaded through.
 */
fun interface MemberTierLookup {
    fun tierOf(name: String, playerId: String): MemberTier
}

val LocalMemberTiers = staticCompositionLocalOf { MemberTierLookup { _, _ -> MemberTier.REGULAR } }

/** Provide [LocalMemberTiers] for a match from its two squads. */
@Composable
fun ProvideMatchMemberTiers(squads: List<SquadMember>, content: @Composable () -> Unit) {
    val lookup = remember(squads) {
        val byId = HashMap<String, MemberTier>()
        val byName = HashMap<String, MemberTier>()
        for (m in squads) {
            val tier = MemberTier.fromBadge(m.memberBadge)
            if (!tier.isMember) continue
            if (m.id.isNotBlank()) byId[m.id] = tier
            byName[m.name.trim().lowercase()] = tier
        }
        MemberTierLookup { name, id ->
            byId[id.trim()]
                ?: byName[name.trimEnd('*', ' ').trim().lowercase()]
                ?: MemberTier.REGULAR
        }
    }
    CompositionLocalProvider(LocalMemberTiers provides lookup, content = content)
}

/** The tier this match says [name] / [playerId] wears. REGULAR outside a match screen. */
@Composable
fun memberTierOf(name: String, playerId: String = ""): MemberTier =
    LocalMemberTiers.current.tierOf(name, playerId)

// Metal for each ring, swept round the circle so the light moves as the eye does.
private val ProMetal = listOf(
    Color(0xFF1E3A8A), Color(0xFF3B82F6), Color(0xFFDBEAFE), Color(0xFF60A5FA),
    Color(0xFF1D4ED8), Color(0xFF93C5FD), Color(0xFF1E3A8A),
)
private val HeroMetal = listOf(
    Color(0xFF7A5518), Color(0xFFD4A64A), Color(0xFFFFF1C9), Color(0xFFC9973A),
    Color(0xFF8A6424), Color(0xFFF2D58C), Color(0xFF7A5518),
)
private val LeafGold = Color(0xFFC99A3E)
private val LeafLight = Color(0xFFF0D48E)
private val LeafVein = Color(0xFF8A6424)

/**
 * Wraps an existing circular avatar in its member frame: a metal ring just outside the
 * photo, the tier's mark on a medallion at the foot, and — for Hero — a hand-drawn laurel
 * sprig climbing each side. Drawn OUTSIDE the avatar's bounds, so applying it never moves
 * the layout around it; give the avatar a few dp of breathing room and it fits.
 * A regular player gets nothing.
 */
@Composable
fun Modifier.memberFrame(tier: MemberTier): Modifier {
    val style = MemberTierStyles.of(tier) ?: return this
    val mark = painterResource(style.mark)
    val disc = style.band[1]
    val isHero = tier == MemberTier.HERO
    return this.drawWithContent {
        drawContent()
        drawMemberFrame(isHero, mark, disc)
    }
}

private fun DrawScope.drawMemberFrame(isHero: Boolean, mark: Painter, disc: Color) {
    val r = size.minDimension / 2
    val c = center
    val ringW = max(2.5.dp.toPx(), r * 0.12f)
    val gap = 1.5.dp.toPx()
    val ringR = r + gap + ringW / 2

    // White collar between photo and metal, so the ring reads as a separate jewel.
    drawCircle(Color.White, radius = r + gap / 2, center = c, style = Stroke(gap))
    drawCircle(
        brush = Brush.sweepGradient(if (isHero) HeroMetal else ProMetal, center = c),
        radius = ringR, center = c, style = Stroke(ringW)
    )
    // A fine bright edge on the inside of the metal — the bevel.
    drawCircle(Color.White.copy(alpha = 0.55f), radius = ringR - ringW / 2, center = c, style = Stroke(0.8.dp.toPx()))

    if (isHero) {
        val leafR = ringR + ringW / 2 + max(1.5.dp.toPx(), r * 0.05f)
        drawLaurel(c, leafR, r, mirror = false)
        drawLaurel(c, leafR, r, mirror = true)
    } else {
        // Pro: one four-point glint on the upper-right of the ring.
        drawGlint(Offset(c.x + ringR * cos(-0.8f), c.y + ringR * sin(-0.8f)), max(3.dp.toPx(), r * 0.16f))
    }

    // Medallion at the foot of the ring carrying the tier mark.
    val d = max(14.dp.toPx(), r * 0.66f)
    val mc = Offset(c.x, c.y + ringR)
    drawCircle(Color.White, radius = d / 2 + 1.5.dp.toPx(), center = mc)
    drawCircle(disc, radius = d / 2, center = mc)
    drawCircle(
        brush = Brush.sweepGradient(if (isHero) HeroMetal else ProMetal, center = mc),
        radius = d / 2, center = mc, style = Stroke(1.2.dp.toPx())
    )
    val m = d * 0.68f
    translate(mc.x - m / 2, mc.y - m / 2) { with(mark) { draw(Size(m, m)) } }
}

/**
 * One laurel sprig along the left (or mirrored right) side of the ring: a curved stem from
 * the foot up past the side, with paired leaves that shrink toward the tip.
 */
private fun DrawScope.drawLaurel(c: Offset, radius: Float, faceR: Float, mirror: Boolean) {
    val sign = if (mirror) -1f else 1f
    // Angles in radians, 0 = right, PI/2 = down. Left sprig runs from just left of the
    // foot (~115°) up to ~215°; the mirror runs the other way round.
    val start = Math.toRadians(118.0).toFloat()
    val end = Math.toRadians(222.0).toFloat()
    fun at(a: Float, rr: Float): Offset {
        val ang = if (mirror) (Math.PI.toFloat() - a) else a
        return Offset(c.x + rr * cos(ang), c.y + rr * sin(ang))
    }

    val stem = Path()
    val steps = 16
    for (i in 0..steps) {
        val a = start + (end - start) * i / steps
        val p = at(a, radius)
        if (i == 0) stem.moveTo(p.x, p.y) else stem.lineTo(p.x, p.y)
    }
    drawPath(stem, LeafVein, style = Stroke(max(1.dp.toPx(), faceR * 0.035f), cap = StrokeCap.Round))

    val leaves = if (faceR < 18.dp.toPx()) 4 else 6
    val baseLen = max(5.dp.toPx(), faceR * 0.30f)
    for (i in 0 until leaves) {
        val t = (i + 0.6f) / leaves
        val a = start + (end - start) * t
        val len = baseLen * (1f - 0.35f * t)
        val p = at(a, radius)
        // Tangent direction of travel, in degrees, for this side.
        val ang = if (mirror) (Math.PI.toFloat() - a) else a
        val tangentDeg = Math.toDegrees(ang.toDouble()).toFloat() + 90f * sign
        for (side in listOf(-1f, 1f)) {
            drawLeaf(p, len, tangentDeg + side * 38f * sign, side > 0)
        }
    }
}

/** A single pointed leaf from [base] at [deg], gold with a lit half and a centre vein. */
private fun DrawScope.drawLeaf(base: Offset, len: Float, deg: Float, outer: Boolean) {
    val w = len * 0.42f
    rotate(deg, pivot = base) {
        val leaf = Path().apply {
            moveTo(base.x, base.y)
            quadraticBezierTo(base.x + len * 0.5f, base.y - w, base.x + len, base.y)
            quadraticBezierTo(base.x + len * 0.5f, base.y + w, base.x, base.y)
            close()
        }
        drawPath(leaf, if (outer) LeafGold else LeafGold.copy(alpha = 0.92f))
        val lit = Path().apply {
            moveTo(base.x, base.y)
            quadraticBezierTo(base.x + len * 0.5f, base.y - w, base.x + len, base.y)
            close()
        }
        drawPath(lit, LeafLight.copy(alpha = 0.7f))
        drawLine(LeafVein.copy(alpha = 0.7f), base, Offset(base.x + len * 0.85f, base.y), strokeWidth = len * 0.06f, cap = StrokeCap.Round)
    }
}

/** A four-point star glint. */
private fun DrawScope.drawGlint(p: Offset, s: Float) {
    val path = Path().apply {
        moveTo(p.x, p.y - s)
        quadraticBezierTo(p.x, p.y, p.x + s, p.y)
        quadraticBezierTo(p.x, p.y, p.x, p.y + s)
        quadraticBezierTo(p.x, p.y, p.x - s, p.y)
        quadraticBezierTo(p.x, p.y, p.x, p.y - s)
        close()
    }
    drawPath(path, Color.White)
    drawCircle(Color(0xFFBFDBFE), radius = s * 0.22f, center = p)
}

/**
 * The tier as a plate for a photo card — the tier's dark band with its metal edge, the mark,
 * and the word. Draws nothing for a regular player.
 */
@Composable
fun MemberPlate(tier: MemberTier, modifier: Modifier = Modifier) {
    val style = MemberTierStyles.of(tier) ?: return
    val isHero = tier == MemberTier.HERO
    val shape = androidx.compose.foundation.shape.RoundedCornerShape(100.dp)
    androidx.compose.foundation.layout.Row(
        modifier
            .clip(shape)
            .background(Brush.linearGradient(style.band))
            .border(1.dp, Brush.sweepGradient(if (isHero) HeroMetal else ProMetal), shape)
            .padding(start = 4.dp, end = 10.dp, top = 3.dp, bottom = 3.dp),
        verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
    ) {
        androidx.compose.foundation.Image(
            painter = painterResource(style.mark),
            contentDescription = null,
            modifier = Modifier.size(18.dp),
        )
        Spacer(Modifier.width(5.dp))
        androidx.compose.material3.Text(
            tier.label.uppercase(),
            color = style.metal,
            fontSize = 10.5.sp,
            fontWeight = androidx.compose.ui.text.font.FontWeight.ExtraBold,
            letterSpacing = 1.2.sp,
            maxLines = 1,
        )
    }
}
