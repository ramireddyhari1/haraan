package com.haraan.app.ui.matches

import androidx.compose.foundation.Canvas
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.spring
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import com.haraan.app.ui.animations.pressScale
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import kotlinx.coroutines.launch
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.sin

/**
 * Where a shot went — captured, not guessed.
 *
 * A wagon wheel is only worth drawing if the dots on it are real. Nothing in the ball log
 * recorded direction before this: a delivery was `{"type":"runs","value":6}` and no more.
 * So the wheel starts here, at the moment the shot is scored, with the one person who
 * actually saw it.
 *
 * Two rules make this survivable for a scorer working ball to ball at a ground:
 *
 *  1. **Boundaries only.** Asking for a direction on all six deliveries of an over would
 *     double the taps in a match and get switched off within an innings. Fours and sixes
 *     are what a wagon wheel is about, and they are rare enough to be worth a second tap.
 *  2. **Always skippable.** A scorer who missed where it went, or who is behind the play,
 *     taps Skip and the ball is scored exactly as before. A prompt that cannot be dismissed
 *     is a prompt that stops the match.
 *
 * Zones are the eight a commentator would name. The layout assumes a right-hander, because
 * batting hand is not recorded anywhere — which is why the picker names the REGION rather
 * than claiming a side of the wicket it cannot know.
 */
val WAGON_ZONES = listOf(
    "Straight",
    "Cover",
    "Point",
    "Third man",
    "Fine leg",
    "Square leg",
    "Mid-wicket",
    "Long-on",
)

/** Centre angle of a zone, in radians, with 0 pointing straight up the ground. */
fun wagonZoneAngle(zone: Int): Float =
    Math.toRadians((zone.coerceIn(0, 7) * 45.0) - 90.0).toFloat()

/**
 * The picker: tap the part of the ground the ball went to.
 *
 * One tap, big targets, and the whole ground is the control — a scorer should be able to
 * hit the right wedge without looking carefully, because they are watching a match.
 */
@Composable
fun WagonZonePicker(
    shot: String,
    /** Ask where it landed (shot plotting on this match's plan). */
    askZone: Boolean = true,
    /** Ask which stroke it was (shot types on this match's plan). */
    askStroke: Boolean = false,
    /**
     * What was captured, or null when the scorer skipped everything. Zone and point come
     * from the ground tap; the stroke from the second step. Either may be absent.
     */
    onDone: (ShotPlot?) -> Unit,
) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    var hovered by remember { mutableStateOf(-1) }
    // Where the scorer last touched, so the tap is visibly acknowledged before the sheet
    // moves on. A picker that vanishes with no mark leaves you unsure it registered.
    var marker by remember { mutableStateOf<Offset?>(null) }
    // The ground tap, held while the stroke is chosen.
    var plotted by remember { mutableStateOf<ShotPlot?>(null) }
    var stage by remember { mutableStateOf(if (askZone) 0 else 1) }

    val accent = if (shot == "6") Color(0xFFD97706) else Color(0xFF2563EB)

    // The shot's own knock the moment the sheet opens, so the ball already feels scored
    // while the direction is still being chosen.
    LaunchedEffect(shot) {
        cricketThud(ctx, if (shot == "6") Thud.SIX else Thud.FOUR)
    }

    fun onPick(zone: Int, x: Float, y: Float) {
        plotted = ShotPlot(zone, x, y)
        if (askStroke) {
            scope.launch {
                // Let the marker land before the sheet moves on.
                kotlinx.coroutines.delay(220)
                stage = 1
            }
        } else {
            onDone(plotted)
        }
    }

    Dialog(onDismissRequest = { onDone(plotted) }, properties = DialogProperties(dismissOnClickOutside = true)) {
        Column(
            modifier = Modifier
                .clip(RoundedCornerShape(22.dp))
                .background(Color.White)
                .padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                if (shot == "6") "SIX" else "FOUR",
                color = accent,
                fontSize = 12.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.6.sp,
            )
            Spacer(Modifier.height(4.dp))
            AnimatedContent(
                targetState = stage,
                transitionSpec = {
                    (slideInHorizontally(spring(dampingRatio = 0.85f, stiffness = 380f)) { it / 3 } + fadeIn()) togetherWith
                        (slideOutHorizontally { -it / 3 } + fadeOut())
                },
                label = "pickerStage",
            ) { st ->
                if (st == 0) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
                "Tap where it landed",
                color = Color(0xFF0F172A),
                fontSize = 18.sp,
                fontWeight = FontWeight.Black,
            )
            Spacer(Modifier.height(16.dp))

            Box(
                modifier = Modifier
                    .size(268.dp)
                    .pointerInput(shot) {
                        detectTapGestures { p ->
                            val cx = size.width / 2f
                            val cy = size.height / 2f
                            val dx = p.x - cx
                            val dy = p.y - cy
                            // A tap in the middle is not a direction — ignore it rather
                            // than recording whichever wedge the centre pixel belongs to.
                            if (hypot(dx, dy) < minOf(size.width, size.height) * 0.13f) return@detectTapGestures
                            // atan2 with 0 pointing up, clockwise, split into 45° wedges.
                            var deg = Math.toDegrees(atan2(dy.toDouble(), dx.toDouble())) + 90.0
                            if (deg < 0) deg += 360.0
                            val zone = (((deg + 22.5) % 360.0) / 45.0).toInt().coerceIn(0, 7)
                            hovered = zone
                            marker = Offset(p.x, p.y)

                            // Normalised against the ground's radius so the point survives
                            // any screen size. Clamped to the rope: a tap outside the oval
                            // still means "to the boundary there", not "off the map".
                            val r = minOf(size.width, size.height) / 2f
                            var nx = dx / r
                            var ny = dy / r
                            val mag = hypot(nx, ny)
                            if (mag > 1f) { nx /= mag; ny /= mag }

                            scope.launch { cricketThud(ctx, Thud.RUN) }
                            onPick(zone, nx, ny)
                        }
                    },
                contentAlignment = Alignment.Center,
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val r = size.minDimension / 2f
                    val c = Offset(size.width / 2f, size.height / 2f)

                    drawCircle(Color(0xFFF1F7F2), radius = r, center = c)
                    drawCircle(Color(0xFFCBD5E1), radius = r, center = c, style = Stroke(width = 2f))
                    drawCircle(
                        Color(0xFFCBD5E1), radius = r * 0.55f, center = c,
                        style = Stroke(width = 1.2f),
                    )
                    // Wedge dividers.
                    repeat(8) { i ->
                        val a = Math.toRadians((i * 45.0) + 22.5 - 90.0).toFloat()
                        drawLine(
                            color = Color(0xFFE2E8F0),
                            start = c,
                            end = Offset(c.x + cos(a) * r, c.y + sin(a) * r),
                            strokeWidth = 1.4f,
                        )
                    }
                    // The pitch, so the orientation is unmistakable.
                    drawRoundRect(
                        color = Color(0xFFE7D7B8),
                        topLeft = Offset(c.x - r * 0.045f, c.y - r * 0.20f),
                        size = Size(r * 0.09f, r * 0.40f),
                        cornerRadius = androidx.compose.ui.geometry.CornerRadius(2f, 2f),
                    )
                    drawCircle(Color(0xFF0F172A), radius = 4f, center = c)

                    marker?.let { m ->
                        drawLine(
                            color = accent,
                            start = c,
                            end = m,
                            strokeWidth = 3.5f,
                        )
                        drawCircle(accent, radius = 7f, center = m)
                        drawCircle(Color.White, radius = 3f, center = m)
                    }
                }

                // Labels, sitting in their own wedge.
                WAGON_ZONES.forEachIndexed { i, name ->
                    val a = wagonZoneAngle(i)
                    val rr = 96.dp
                    Text(
                        name,
                        color = if (hovered == i) accent else Color(0xFF64748B),
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.offset(
                            x = (rr.value * cos(a)).dp,
                            y = (rr.value * sin(a)).dp,
                        ),
                    )
                }
            }

                    }
                } else {
                    StrokeStage(accent, plotted?.zone) { kind ->
                        scope.launch { cricketThud(ctx, if (shot == "6") Thud.SIX else Thud.FOUR) }
                        onDone(plotted?.copy(stroke = kind.key) ?: ShotPlot(stroke = kind.key))
                    }
                }
            }

            Spacer(Modifier.height(14.dp))
            Text(
                "Skip",
                color = Color(0xFF64748B),
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFF1F5F9))
                    .padding(horizontal = 26.dp, vertical = 11.dp)
                    .pointerInput(Unit) { detectTapGestures { onDone(plotted) } },
            )
        }
    }
}

/**
 * Which stroke was it — each one drawn and playing itself as the sheet arrives, with the
 * strokes that usually go where the ball went already at the top.
 */
@Composable
private fun StrokeStage(accent: Color, zone: Int?, onPick: (ShotKind) -> Unit) {
    val kinds = remember(zone) { ShotKind.likelyFor(zone) }
    val likely = if (zone != null) 3 else 0
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            "What shot?",
            color = Color(0xFF0F172A),
            fontSize = 18.sp,
            fontWeight = FontWeight.Black,
        )
        Spacer(Modifier.height(14.dp))
        LazyVerticalGrid(
            columns = GridCells.Fixed(3),
            modifier = Modifier.width(284.dp).heightIn(max = 400.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            items(kinds.size) { i ->
                val kind = kinds[i]
                val source = remember { MutableInteractionSource() }
                val top = i < likely
                Column(
                    Modifier
                        .pressScale(source)
                        .clip(RoundedCornerShape(14.dp))
                        .background(if (top) accent.copy(alpha = 0.08f) else Color(0xFFF6F8FB))
                        .then(
                            if (top) Modifier.border(1.dp, accent.copy(alpha = 0.35f), RoundedCornerShape(14.dp))
                            else Modifier
                        )
                        .clickable(interactionSource = source, indication = null) { onPick(kind) }
                        .padding(vertical = 8.dp, horizontal = 4.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    ShotFigure(kind, if (top) accent else Color(0xFF64748B), Modifier.size(56.dp))
                    Spacer(Modifier.height(4.dp))
                    Text(
                        kind.label,
                        color = Color(0xFF0F172A),
                        fontSize = 11.5.sp,
                        fontWeight = if (top) FontWeight.Bold else FontWeight.SemiBold,
                        maxLines = 1,
                    )
                }
            }
        }
    }
}
