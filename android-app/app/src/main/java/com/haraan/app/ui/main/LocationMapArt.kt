package com.haraan.app.ui.main

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import coil.compose.AsyncImage
import com.haraan.app.ui.util.VenueMap
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.LocationState
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.sin

/** How long a pick keeps the sheet open: the drop-in delay plus the pin's bounce settling. */
internal const val PIN_LANDING_MS = 720L

private val PinBlue = HaraanColors.EventsBlue
private val PinEdge = Color(0xFF1D4ED8)
private val Paper = Color(0xFFF3F5F9)

// Tight bottom corners so Google's logo and "Map data" credit along the bottom edge
// are never clipped by the card's rounding.
private val MapShape = RoundedCornerShape(topStart = 22.dp, topEnd = 22.dp, bottomStart = 8.dp, bottomEnd = 8.dp)
private val Water = Color(0xFFD3E5F7)
private val Ink = HaraanColors.TextPrimary
private val Faint = HaraanColors.TextMuted

/**
 * The place you're set to, on the real Google map of it, with a pin on the exact spot.
 *
 * The map is a Static Maps image centred on the saved coordinates — real roads and real
 * area names, nothing invented. When there are no coordinates yet it stays plain graph
 * paper rather than pretending. The pin is the app's own and behaves like an object:
 * it drops in when the sheet opens or a new place is picked, lands with a haptic on
 * the frame it touches the ground, lifts and hovers with radar rings while GPS is
 * being read, and hops when the map is tapped.
 *
 * The plate on top only states what the app knows: the area, the city, the Plus Code,
 * and whether it came from GPS or was picked by hand.
 */
@Composable
internal fun PlaceMapCard(
    state: LocationState,
    pickedByHand: Boolean,
    onLocate: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val resolved = state as? LocationState.Resolved
    val locating = state == LocationState.Locating
    val placeKey = resolved?.let { "${it.city}|${it.area}" }

    // Height of the pin above the ground, in dp. The drop springs THROUGH zero; its
    // distance from zero is drawn as height, so the overshoot comes back as a bounce.
    val lift = remember { Animatable(120f) }

    LaunchedEffect(locating, placeKey) {
        when {
            locating -> lift.animateTo(20f, spring(dampingRatio = 0.6f, stiffness = 300f))
            placeKey != null -> {
                if (abs(lift.value) < 1f) lift.snapTo(120f) // a new place drops from above
                delay(140) // let the sheet finish rising first
                var landed = false
                lift.animateTo(0f, spring(dampingRatio = 0.38f, stiffness = 420f)) {
                    if (!landed && value <= 0f) {
                        landed = true
                        view.performHapticFeedback(Feel.COMMIT)
                    }
                }
            }
            else -> lift.animateTo(10f, spring(dampingRatio = 0.7f, stiffness = 260f))
        }
    }

    val loop = rememberInfiniteTransition(label = "mapLoop")
    val radar by loop.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1500, easing = LinearEasing)),
        label = "radar",
    )
    val bob by loop.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1100, easing = LinearEasing), RepeatMode.Restart),
        label = "bob",
    )

    val lat = resolved?.latitude
    val lng = resolved?.longitude
    // An area is a few streets; a city picked by name is the whole town.
    val zoom = if (resolved?.area.isNullOrBlank()) 13 else 15

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .height(216.dp)
            .clip(MapShape)
            .background(Paper)
            .border(1.dp, Color(0xFFE3E8EF), MapShape),
    ) {
        // While GPS is read the map drifts under the pin, the way a map app pans.
        val panX = if (locating) sin(bob * 2 * PI).toFloat() * 5f else 0f
        val panY = if (locating) sin(bob * 2 * PI + 1.3).toFloat() * 3f else 0f

        if (lat != null && lng != null && VenueMap.hasKey) {
            // The real map, centred on the exact spot, at the card's own size so nothing
            // is cropped — Google's logo and credit along the bottom edge stay visible.
            AsyncImage(
                model = VenueMap.areaMapUrl(lat, lng, maxWidth.value.toInt(), maxHeight.value.toInt(), zoom),
                contentDescription = "Map of ${resolved.area.ifBlank { resolved.city }}",
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxSize()
                    .graphicsLayer {
                        translationX = panX.dp.toPx()
                        translationY = panY.dp.toPx()
                        // No fix yet: keep the last map, but quieter.
                        alpha = if (locating) 0.75f else 1f
                    },
            )
        } else {
            // No coordinates to show: plain graph paper, never made-up streets.
            Canvas(Modifier.fillMaxSize()) {
                val step = 16.dp.toPx()
                var y = step
                while (y < size.height) {
                    var x = step
                    while (x < size.width) {
                        drawCircle(Color(0xFFD5DCE6), 1.dp.toPx(), Offset(x, y))
                        x += step
                    }
                    y += step
                }
            }
        }

        Canvas(
            modifier = Modifier
                .fillMaxSize()
                .pointerInput(Unit) {
                    detectTapGestures {
                        if (locating || abs(lift.value) > 2f) return@detectTapGestures
                        view.performHapticFeedback(Feel.SELECT)
                        scope.launch {
                            lift.animateTo(16f, tween(130))
                            var landed = false
                            lift.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = 500f)) {
                                if (!landed && value <= 0f) {
                                    landed = true
                                    view.performHapticFeedback(Feel.TICK)
                                }
                            }
                        }
                    }
                },
        ) {
            // Dead centre, because that is the coordinate the map image is centred on.
            val ground = Offset(size.width / 2f, size.height / 2f)
            if (locating) {
                for (phase in floatArrayOf(0f, 0.5f)) {
                    val t = (radar + phase) % 1f
                    val r = 6.dp.toPx() + t * 74.dp.toPx()
                    drawCircle(PinBlue.copy(alpha = (1f - t) * 0.16f), r, ground)
                    drawCircle(
                        PinBlue.copy(alpha = (1f - t) * 0.45f), r, ground,
                        style = Stroke(1.2.dp.toPx()),
                    )
                }
            }
            val hover = if (locating) sin(bob * 2 * PI).toFloat() * 3f else 0f
            drawPin(
                ground = ground,
                heightPx = (abs(lift.value) + hover).dp.toPx(),
                headRadius = 13.dp.toPx(),
                fill = if (resolved != null || locating) PinBlue else Color(0xFF94A3B8),
                edge = if (resolved != null || locating) PinEdge else Color(0xFF64748B),
            )
        }

        // Both sit above the bottom strip where Google's logo and "Map data" credit are.
        PlacePlate(
            state = state,
            pickedByHand = pickedByHand,
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(start = 12.dp, end = 76.dp, bottom = 28.dp),
        )

        LocateButton(
            state = state,
            onClick = onLocate,
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(end = 12.dp, bottom = 28.dp),
        )
    }
}

/** A map label: an eyebrow saying where the place came from, the place, then its city and code. */
@Composable
private fun PlacePlate(state: LocationState, pickedByHand: Boolean, modifier: Modifier) {
    val (eyebrow, title, line) = when (state) {
        is LocationState.Resolved -> {
            val city = state.city.takeUnless { it.equals("Unknown", ignoreCase = true) }.orEmpty()
            val place = state.area.ifBlank { city }.ifBlank { "Location set" }
            val detail = listOf(
                city.takeIf { it.isNotBlank() && !it.equals(place, ignoreCase = true) },
                state.plusCode.takeIf { it.isNotBlank() },
            ).filterNotNull().joinToString("  ·  ")
            Triple(if (pickedByHand) "PICKED BY YOU" else "FROM GPS", place, detail)
        }
        LocationState.Locating -> Triple("READING GPS", "Finding you…", "Hold on, this takes a few seconds")
        LocationState.Denied -> Triple("LOCATION ACCESS OFF", "Where are you?", "Allow access, or pick a place below")
        LocationState.ServicesOff -> Triple("LOCATION IS OFF", "Turn on location", "It's switched off on this phone")
        LocationState.Unavailable -> Triple("NO GPS FIX", "Couldn't find you", "Step near a window and try again")
        else -> Triple("NOT SET", "Where are you?", "Locate me, or pick a place below")
    }
    Column(
        modifier = modifier
            .shadow(6.dp, RoundedCornerShape(14.dp), ambientColor = Color(0x22000000), spotColor = Color(0x22000000))
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .padding(start = 12.dp, end = 14.dp, top = 9.dp, bottom = 10.dp),
    ) {
        Text(
            eyebrow,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.sp,
            color = if (state is LocationState.Resolved || state == LocationState.Locating) PinBlue else Faint,
        )
        Spacer(Modifier.height(3.dp))
        Text(
            title,
            fontSize = 17.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = (-0.3).sp,
            color = Ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        if (line.isNotBlank()) {
            Spacer(Modifier.height(1.dp))
            Text(line, fontSize = 12.sp, color = HaraanColors.TextSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

/** The map's one control. Becomes a worded pill when the obstacle is a permission or a switch. */
@Composable
private fun LocateButton(state: LocationState, onClick: () -> Unit, modifier: Modifier) {
    val word = when (state) {
        LocationState.Denied -> "Allow"
        LocationState.ServicesOff -> "Settings"
        else -> null
    }
    val locating = state == LocationState.Locating
    Row(
        modifier = modifier
            .pressable(enabled = !locating, haptic = Feel.SELECT, onClick = onClick)
            .shadow(8.dp, RoundedCornerShape(50), ambientColor = PinBlue.copy(alpha = 0.3f), spotColor = PinBlue.copy(alpha = 0.3f))
            .clip(RoundedCornerShape(50))
            .background(if (locating) Color.White else PinBlue)
            .height(46.dp)
            .padding(horizontal = if (word == null) 13.dp else 16.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(7.dp),
    ) {
        Icon(
            Icons.Default.MyLocation,
            contentDescription = if (word == null) "Use my current location" else null,
            tint = if (locating) PinBlue else Color.White,
            modifier = Modifier.size(20.dp),
        )
        if (word != null) {
            Text(word, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White)
        }
    }
}

/** A small pin for list rows. Hops, and keeps hopping, while that row's place is loading. */
@Composable
internal fun RowPin(active: Boolean, highlighted: Boolean = false, modifier: Modifier = Modifier) {
    val hop = rememberInfiniteTransition(label = "rowPin")
    val t by hop.animateFloat(0f, 1f, infiniteRepeatable(tween(520, easing = LinearEasing)), label = "hop")
    Canvas(modifier.size(width = 18.dp, height = 22.dp)) {
        val ground = Offset(size.width / 2f, size.height - 2.dp.toPx())
        val h = if (active) abs(sin(t * PI)).toFloat() * 5.dp.toPx() else 0f
        drawPin(
            ground = ground,
            heightPx = h,
            headRadius = 5.6.dp.toPx(),
            fill = if (active || highlighted) PinBlue else Color(0xFFCBD5E1),
            edge = if (active || highlighted) PinEdge else Color(0xFFB4BFCD),
            shadow = active,
        )
    }
}

/** The empty state: a folded paper map with a dashed route that runs out. */
@Composable
internal fun LostMapArt(modifier: Modifier = Modifier) {
    Canvas(modifier.size(width = 132.dp, height = 84.dp)) {
        val w = size.width
        val h = size.height
        val panel = w / 3f
        val skew = 7.dp.toPx()
        for (i in 0 until 3) {
            val x0 = i * panel
            val up = i % 2 == 0
            val p = Path().apply {
                moveTo(x0, if (up) 0f else skew)
                lineTo(x0 + panel, if (up) skew else 0f)
                lineTo(x0 + panel, if (up) h else h - skew)
                lineTo(x0, if (up) h - skew else h)
                close()
            }
            drawPath(p, if (up) Color(0xFFF1F5F9) else Color(0xFFE7ECF3))
            drawPath(p, Color(0xFFD5DDE7), style = Stroke(1.dp.toPx()))
        }
        // A river and a road, so it reads as a map before the route is noticed.
        drawPath(
            Path().apply {
                moveTo(0f, h * 0.72f)
                cubicTo(w * 0.3f, h * 0.55f, w * 0.55f, h * 0.95f, w, h * 0.70f)
            },
            Water, style = Stroke(6.dp.toPx(), cap = StrokeCap.Round),
        )
        drawLine(Color.White, Offset(w * 0.18f, 0f), Offset(w * 0.26f, h), 4.dp.toPx())
        drawPath(
            Path().apply {
                moveTo(w * 0.12f, h * 0.30f)
                cubicTo(w * 0.35f, h * 0.10f, w * 0.48f, h * 0.55f, w * 0.70f, h * 0.36f)
            },
            Faint,
            style = Stroke(
                1.8.dp.toPx(),
                cap = StrokeCap.Round,
                pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 4.dp.toPx())),
            ),
        )
        drawCircle(Faint, 2.6.dp.toPx(), Offset(w * 0.12f, h * 0.30f))
        // Where the route gives up: a pin knocked over on its side.
        rotate(78f, pivot = Offset(w * 0.80f, h * 0.40f)) {
            drawPin(
                ground = Offset(w * 0.80f, h * 0.40f),
                heightPx = 0f,
                headRadius = 7.dp.toPx(),
                fill = Color(0xFF94A3B8),
                edge = Color(0xFF64748B),
                shadow = false,
            )
        }
    }
}

private val EdgeBlue = Color(0xFF2563EB)

/**
 * The blue light on the chosen place: four short corner brackets, not a lit rim. When
 * [on] turns true the corners light one after another (top-left round to bottom-left)
 * and then stay, with only a faint halo hugging each bracket. It runs once per pick,
 * not forever: a light that never stops reads as decoration; one that answers the
 * pick reads as the app saying "this one".
 *
 * Put it BEFORE the clip in the chain, so the halo is not clipped and the brackets are
 * drawn over the content.
 */
@Composable
internal fun Modifier.edgeLight(on: Boolean, corner: Dp): Modifier {
    val glow by animateFloatAsState(if (on) 1f else 0f, tween(220), label = "edgeGlow")
    val sweep = remember { Animatable(1f) }
    LaunchedEffect(on) {
        if (on) {
            sweep.snapTo(0f)
            sweep.animateTo(1f, tween(700, easing = FastOutSlowInEasing))
        }
    }
    if (glow <= 0.001f) return this
    return this.drawWithContent {
        drawContent()
        val sw = 2.dp.toPx()
        val inset = sw / 2f
        val left = inset
        val top = inset
        val right = size.width - inset
        val bottom = size.height - inset
        val r = corner.toPx().coerceAtMost(minOf(right - left, bottom - top) / 2f)
        // How far each bracket runs along the edge past the curve.
        val tail = 12.dp.toPx().coerceAtMost(((right - left) / 2f - r).coerceAtLeast(0f))
        val vTail = 12.dp.toPx().coerceAtMost(((bottom - top) / 2f - r).coerceAtLeast(0f))
        val d = r * 2f
        val brackets = listOf(
            Path().apply { // top-left
                moveTo(left, top + r + vTail)
                lineTo(left, top + r)
                arcTo(Rect(left, top, left + d, top + d), 180f, 90f, false)
                lineTo(left + r + tail, top)
            },
            Path().apply { // top-right
                moveTo(right - r - tail, top)
                lineTo(right - r, top)
                arcTo(Rect(right - d, top, right, top + d), 270f, 90f, false)
                lineTo(right, top + r + vTail)
            },
            Path().apply { // bottom-right
                moveTo(right, bottom - r - vTail)
                lineTo(right, bottom - r)
                arcTo(Rect(right - d, bottom - d, right, bottom), 0f, 90f, false)
                lineTo(right - r - tail, bottom)
            },
            Path().apply { // bottom-left
                moveTo(left + r + tail, bottom)
                lineTo(left + r, bottom)
                arcTo(Rect(left, bottom - d, left + d, bottom), 90f, 90f, false)
                lineTo(left, bottom - r - vTail)
            },
        )
        brackets.forEachIndexed { i, path ->
            val lit = ((sweep.value * 4f - i) * 1.6f).coerceIn(0f, 1f) * glow
            if (lit <= 0f) return@forEachIndexed
            drawPath(
                path, EdgeBlue.copy(alpha = 0.10f * lit),
                style = Stroke(6.dp.toPx(), cap = StrokeCap.Round),
            )
            drawPath(path, EdgeBlue.copy(alpha = lit), style = Stroke(sw, cap = StrokeCap.Round))
        }
    }
}

/** The pin, with its point on [ground] raised by [heightPx], and its shadow left on the ground. */
private fun DrawScope.drawPin(
    ground: Offset,
    heightPx: Float,
    headRadius: Float,
    fill: Color,
    edge: Color,
    shadow: Boolean = true,
) {
    val r = headRadius
    if (shadow) {
        // The higher it is, the smaller and fainter its shadow.
        val k = (1f - (heightPx / (r * 6f))).coerceIn(0.35f, 1f)
        val sw = r * 1.5f * k
        drawOval(
            Color.Black.copy(alpha = 0.20f * k),
            topLeft = Offset(ground.x - sw / 2f, ground.y - r * 0.18f),
            size = Size(sw, r * 0.36f),
        )
    }
    val tip = Offset(ground.x, ground.y - heightPx)
    val head = Offset(tip.x, tip.y - r * 2.3f)
    val p = Path().apply {
        moveTo(tip.x, tip.y)
        cubicTo(tip.x - r * 0.25f, tip.y - r * 0.6f, tip.x - r, tip.y - r * 1.4f, tip.x - r, head.y)
        arcTo(Rect(head, r), 180f, 180f, false)
        cubicTo(tip.x + r, tip.y - r * 1.4f, tip.x + r * 0.25f, tip.y - r * 0.6f, tip.x, tip.y)
        close()
    }
    drawPath(p, fill)
    drawPath(p, edge, style = Stroke(r * 0.09f))
    drawCircle(Color.White, r * 0.42f, head)
}
