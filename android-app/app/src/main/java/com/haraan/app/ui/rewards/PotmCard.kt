package com.haraan.app.ui.rewards

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.spring
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.R
import com.haraan.app.data.ApiConfig
import com.haraan.app.ui.Feel
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.launch
import kotlin.math.abs
import kotlin.math.cos
import kotlin.math.sin

/**
 * Player of the Match, as an award you were handed rather than a dashboard tile.
 *
 * Same white paper as the rest of the board. The prestige comes from one object: a drawn
 * medal on a ribbon, pinned to the card's top edge. Numbers are printed the way a
 * scorecard prints them (64 (38), 2/28 (4)), so every digit can be checked.
 *
 * It responds to touch. Press it and the card tips toward your finger, with a sheen that
 * follows the tilt. Let go and it springs flat, and the medal swings from the release.
 * On arrival the medal is hung already swinging and settles like a pendulum.
 */
@Composable
internal fun PotmCard(impact: PlayerImpactUi, shown: Boolean, animate: Boolean, modifier: Modifier = Modifier) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val tiltX = remember { Animatable(0f) }
    val tiltY = remember { Animatable(0f) }
    val swing = remember { Animatable(0f) }

    LaunchedEffect(shown) {
        if (!shown || !animate) return@LaunchedEffect
        swing.snapTo(24f)
        view.performHapticFeedback(Feel.TICK)
        swing.animateTo(0f, spring(dampingRatio = 0.1f, stiffness = 55f))
    }

    val shape = RoundedCornerShape(20.dp)
    Box(
        modifier
            .fillMaxWidth()
            .padding(top = 10.dp) // room for the ribbon's pin above the edge
            .graphicsLayer {
                rotationX = tiltX.value
                rotationY = tiltY.value
                cameraDistance = 14f * density
            }
            .pointerInput(Unit) {
                awaitEachGesture {
                    val down = awaitFirstDown(requireUnconsumed = false)
                    view.performHapticFeedback(Feel.SELECT)
                    fun tiltTo(p: Offset) {
                        val nx = (p.x / size.width * 2f - 1f).coerceIn(-1f, 1f)
                        val ny = (p.y / size.height * 2f - 1f).coerceIn(-1f, 1f)
                        scope.launch { tiltY.animateTo(nx * 7f, spring(stiffness = 900f)) }
                        scope.launch { tiltX.animateTo(-ny * 6f, spring(stiffness = 900f)) }
                    }
                    tiltTo(down.position)
                    while (true) {
                        val event = awaitPointerEvent()
                        val change = event.changes.firstOrNull() ?: break
                        // The list took over (a scroll): let go of the card rather than fight it.
                        if (!change.pressed || change.isConsumed) break
                        tiltTo(change.position)
                    }
                    val kick = tiltY.value
                    scope.launch { tiltX.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = 300f)) }
                    scope.launch { tiltY.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = 300f)) }
                    scope.launch {
                        swing.animateTo(0f, spring(dampingRatio = 0.12f, stiffness = 60f), initialVelocity = -kick * 28f)
                    }
                }
            },
    ) {
        Column(
            Modifier
                .fillMaxWidth()
                .shadow(10.dp, shape, ambientColor = Color(0x14B45309), spotColor = Color(0x1F0F172A))
                .clip(shape)
                .background(Board.Surface)
                .border(1.dp, Board.Line, shape)
                .drawWithContent {
                    drawContent()
                    // Sheen: a soft band that slides with the tilt, only while held.
                    val strength = (abs(tiltY.value) + abs(tiltX.value)) / 13f
                    if (strength > 0.01f) {
                        val cx = size.width * (0.5f + tiltY.value / 12f)
                        drawRect(
                            Brush.linearGradient(
                                listOf(Color.Transparent, Color.White.copy(alpha = 0.55f * strength), Color.Transparent),
                                start = Offset(cx - 120f, 0f),
                                end = Offset(cx + 120f, size.height),
                            ),
                        )
                    }
                }
                .padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 16.dp),
        ) {
            Text(
                "PLAYER OF THE MATCH",
                color = MedalInk,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 10.5.sp,
                letterSpacing = 1.8.sp,
            )
            Spacer(Modifier.height(12.dp))
            Row(Modifier.padding(end = 64.dp), verticalAlignment = Alignment.CenterVertically) {
                Portrait(impact)
                Spacer(Modifier.width(14.dp))
                Column {
                    Text(
                        impact.playerName.ifBlank { "Player of the Match" },
                        color = Board.Ink,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 20.sp,
                        letterSpacing = (-0.3).sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Spacer(Modifier.height(3.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Crest(impact.teamShort, impact.teamLogo, 16.dp)
                        Spacer(Modifier.width(6.dp))
                        Text(
                            listOfNotNull(
                                impact.teamName.ifBlank { impact.teamShort }.takeIf { it.isNotBlank() },
                                impact.role.takeIf { it.isNotBlank() }?.lowercase()?.replaceFirstChar { it.uppercase() },
                            ).joinToString("  ·  "),
                            color = Board.InkFaint,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Medium,
                            fontSize = 12.5.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }

            val figures = listOfNotNull(
                impact.batFigure?.let { Triple("Bat", it, impact.batNote) },
                impact.bowlFigure?.let { Triple("Ball", it, impact.bowlNote) },
            )
            if (figures.isNotEmpty()) {
                Spacer(Modifier.height(16.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(Board.Line))
                Spacer(Modifier.height(14.dp))
                Row(Modifier.fillMaxWidth()) {
                    figures.forEachIndexed { i, (label, figure, note) ->
                        if (i > 0) Spacer(Modifier.width(1.dp).height(44.dp).background(Board.Line))
                        Figure(label, figure, note, Modifier.weight(1f).padding(start = if (i > 0) 16.dp else 0.dp))
                    }
                }
            } else if (impact.chips.isNotEmpty()) {
                Spacer(Modifier.height(14.dp))
                Text(
                    impact.chips.joinToString("   ") { "${it.label.lowercase().replaceFirstChar { c -> c.uppercase() }} ${it.value}" },
                    color = Board.InkMuted,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 13.sp,
                )
            }
        }

        // The medal hangs from a pin on the top edge, outside the clip, and swings from it.
        Medal(
            Modifier
                .align(Alignment.TopEnd)
                .offset(x = (-18).dp, y = (-10).dp)
                .size(width = 48.dp, height = 82.dp)
                .graphicsLayer {
                    rotationZ = swing.value
                    transformOrigin = TransformOrigin(0.5f, 0f)
                }
                .semantics { contentDescription = "Player of the Match medal" },
        )
    }
}

@Composable
private fun Portrait(impact: PlayerImpactUi) {
    Box(
        Modifier
            .size(58.dp)
            .clip(CircleShape)
            .background(Board.Raised)
            .border(1.dp, Board.Line, CircleShape),
    ) {
        val photoUrl = ApiConfig.mediaUrl(impact.playerPhoto)
        if (!photoUrl.isNullOrBlank()) {
            AsyncImage(photoUrl, impact.playerName, Modifier.fillMaxSize().clip(CircleShape), contentScale = ContentScale.Crop)
        } else {
            Image(
                painterResource(R.drawable.ic_default_player_avatar), impact.playerName,
                Modifier.fillMaxSize().clip(CircleShape), contentScale = ContentScale.Crop,
            )
        }
    }
}

@Composable
private fun Figure(label: String, figure: String, note: String?, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(
            label.uppercase(), color = Board.InkFaint, fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold, fontSize = 10.sp, letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(2.dp))
        // "64 (38)": the headline number big, the bracket quieter — how a scorecard reads.
        val main = figure.substringBefore(" (")
        val bracket = figure.substringAfter(" (", "").takeIf { it.isNotBlank() }?.let { " ($it" }
        Row(verticalAlignment = Alignment.Bottom) {
            Text(main, color = Board.Ink, fontFamily = PlusJakartaSans, fontWeight = FontWeight.ExtraBold, fontSize = 26.sp, letterSpacing = (-0.6).sp)
            bracket?.let {
                Text(it, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontWeight = FontWeight.SemiBold, fontSize = 15.sp, modifier = Modifier.padding(bottom = 3.dp))
            }
        }
        note?.let {
            Text(it, color = Board.InkFaint, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Medium, fontSize = 12.sp)
        }
    }
}

private val MedalInk = Color(0xFF92400E)
private val GoldHi = Color(0xFFFDE68A)
private val Gold = Color(0xFFF59E0B)
private val GoldLo = Color(0xFFB45309)
private val RibbonBlue = Color(0xFF2563EB)
private val RibbonNavy = Color(0xFF1E293B)

/** Ribbon, pin and a struck gold medal, drawn in a 48 × 82 space. */
@Composable
private fun Medal(modifier: Modifier) {
    Canvas(modifier) {
        val s = size.width / 48f
        fun o(x: Float, y: Float) = Offset(x * s, y * s)

        // Ribbon: two tails crossing into a V, blue with a navy edge stripe.
        for (side in listOf(-1f, 1f)) {
            val tail = Path().apply {
                moveTo(o(24f + side * 16f, 0f).x, 0f)
                lineTo(o(24f + side * 4f, 0f).x, 0f)
                lineTo(o(24f - side * 5f, 46f).x, o(0f, 46f).y)
                lineTo(o(24f + side * 7f, 46f).x, o(0f, 46f).y)
                close()
            }
            drawPath(tail, if (side < 0) RibbonBlue else Color(0xFF1D4ED8))
            drawLine(
                RibbonNavy, o(24f + side * 13f, 0f), o(24f + side * 4.5f, 44f), strokeWidth = 2.2f * s,
            )
        }
        // Pin/clasp at the card edge
        drawRoundRect(RibbonNavy, o(10f, 0f), Size(28f * s, 5f * s), CornerRadius(2.5f * s))
        // Jump ring
        drawCircle(GoldLo, 3.4f * s, o(24f, 46f), style = Stroke(1.8f * s))

        // Medal body
        val c = o(24f, 64f)
        val r = 17f * s
        drawCircle(Color(0x220F172A), r, c + Offset(0f, 1.6f * s))
        drawCircle(Brush.linearGradient(listOf(GoldHi, Gold, GoldLo), c - Offset(r, r), c + Offset(r, r)), r, c)
        drawCircle(Brush.linearGradient(listOf(GoldLo, Gold, GoldHi), c - Offset(r, r), c + Offset(r, r)), r * 0.78f, c)
        // Reeded rim ticks, like a struck coin
        for (i in 0 until 36) {
            val a = Math.toRadians(i * 10.0)
            val p1 = Offset(c.x + cos(a).toFloat() * r * 0.82f, c.y + sin(a).toFloat() * r * 0.82f)
            val p2 = Offset(c.x + cos(a).toFloat() * r * 0.92f, c.y + sin(a).toFloat() * r * 0.92f)
            drawLine(GoldLo.copy(alpha = 0.45f), p1, p2, strokeWidth = 0.8f * s)
        }
        // Embossed star: shadow then face
        val star = { dx: Float, dy: Float ->
            Path().apply {
                for (i in 0 until 10) {
                    val rr = if (i % 2 == 0) r * 0.46f else r * 0.19f
                    val a = Math.toRadians(-90.0 + i * 36.0)
                    val x = c.x + dx + cos(a).toFloat() * rr
                    val y = c.y + dy + sin(a).toFloat() * rr
                    if (i == 0) moveTo(x, y) else lineTo(x, y)
                }
                close()
            }
        }
        drawPath(star(0.8f * s, 0.8f * s), GoldLo.copy(alpha = 0.7f))
        drawPath(star(0f, 0f), GoldHi)
        // Specular arc
        drawArc(
            Color.White.copy(alpha = 0.6f), 200f, 70f, false,
            topLeft = c - Offset(r * 0.9f, r * 0.9f), size = Size(r * 1.8f, r * 1.8f),
            style = Stroke(1.6f * s),
        )
    }
}
