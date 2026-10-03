package com.haraan.partner.alerts

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.RoundRect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Outline
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathOperation
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.R
import com.haraan.partner.ui.Haptics
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.abs

// Haraan blue, the launcher-icon ramp.
private val BrandLight = Color(0xFF4D8BFF)
private val Brand = Color(0xFF2563EB)
private val BrandDeep = Color(0xFF1E40AF)
private val BrandInk = Color(0xFF1D4ED8)

/** How long a ticket stays before it tucks itself away. */
private const val SHOW_MS = 14_000

/**
 * A booking, delivered as a ticket — over any app while the partner is on duty, or
 * inside Haraan.
 *
 * Top half: who sent it (Haraan's own mark, like any phone notification), who booked,
 * where and when, and the amount. A perforated tear line with real notches cut into
 * the card. Bottom half: the money's state and the two choices. The Open button fills
 * as the ticket's time runs out, the way a ride request's accept button does.
 *
 * Physically: it lands with a soft settle and the amount stamps in with a tick; flick
 * it up or to either side to send it away; tap anywhere on it to open the booking.
 */
@Composable
fun BookingAlertCard(
    alert: BookingAlert,
    onOpen: () -> Unit,
    onDismiss: () -> Unit,
    formatInr: (Double) -> String,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val enter = remember { Animatable(0f) }
    val stamp = remember { Animatable(0f) }
    val dx = remember { Animatable(0f) }
    val dy = remember { Animatable(0f) }
    val timer = remember { Animatable(1f) }
    var held by remember { mutableStateOf(false) }
    var leaving by remember { mutableStateOf(false) }
    var tearY by remember { mutableFloatStateOf(0f) }

    fun leave(toX: Float, toY: Float, then: () -> Unit) {
        if (leaving) return
        leaving = true
        scope.launch {
            launch { dx.animateTo(toX, tween(240, easing = FastOutSlowInEasing)) }
            launch { dy.animateTo(toY, tween(240, easing = FastOutSlowInEasing)) }
            enter.animateTo(0f, tween(240))
            then()
        }
    }

    LaunchedEffect(alert.id) {
        enter.animateTo(1f, spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessMediumLow))
        // The amount lands like a stamp once the ticket has.
        Haptics.tick(view)
        stamp.snapTo(1f)
        stamp.animateTo(0f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMedium))
    }
    LaunchedEffect(alert.id, held) {
        if (!held) {
            delay(250)
            timer.animateTo(0f, tween((timer.value * SHOW_MS).toInt(), easing = LinearEasing))
            leave(0f, -380f, onDismiss)
        }
    }

    val ticket = remember(tearY) { TicketShape(tearY, 22f) }
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val sink by androidx.compose.animation.core.animateFloatAsState(if (pressed) 1f else 0f, spring(dampingRatio = 0.7f), label = "sink")

    Box(
        modifier
            .fillMaxWidth()
            .padding(horizontal = 10.dp)
            .graphicsLayer {
                translationY = (1f - enter.value) * -280f + dy.value
                translationX = dx.value
                val s = (0.95f + 0.05f * enter.value) * (1f - 0.02f * sink)
                scaleX = s; scaleY = s
                alpha = (enter.value * (1f - abs(dx.value) / 900f)).coerceIn(0f, 1f)
                rotationZ = dx.value / 40f
            }
            .pointerInput(alert.id) {
                detectDragGestures(
                    onDragStart = { held = true },
                    onDragEnd = {
                        held = false
                        when {
                            dy.value < -60f -> leave(dx.value, -400f, onDismiss)
                            abs(dx.value) > 120f -> leave(if (dx.value > 0) 1100f else -1100f, dy.value, onDismiss)
                            else -> scope.launch {
                                launch { dx.animateTo(0f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessMedium)) }
                                dy.animateTo(0f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessMedium))
                            }
                        }
                    },
                    onDragCancel = { held = false; scope.launch { dx.animateTo(0f); dy.animateTo(0f) } },
                    onDrag = { _, d ->
                        scope.launch {
                            dx.snapTo(dx.value + d.x)
                            // Up moves freely; down resists — the ticket is already where it lives.
                            dy.snapTo((dy.value + if (d.y > 0) d.y * 0.25f else d.y).coerceAtMost(36f))
                        }
                    },
                )
            },
    ) {
        Column(
            Modifier
                .fillMaxWidth()
                .shadow(22.dp, ticket, clip = false, spotColor = Color(0x991E3A8A), ambientColor = Color(0x551E3A8A))
                .clip(ticket)
                .background(Brush.linearGradient(listOf(BrandLight, Brand, BrandDeep), start = Offset.Zero, end = Offset(1100f, 800f)))
                .drawBehind {
                    // Light from the top-left, as on the app icon.
                    drawCircle(
                        Brush.radialGradient(listOf(Color.White.copy(alpha = 0.22f), Color.Transparent), Offset.Zero, size.width * 0.65f),
                        radius = size.width * 0.65f, center = Offset.Zero,
                    )
                    // The tear line between the notches.
                    if (tearY > 0f) {
                        drawLine(
                            Color.White.copy(alpha = 0.35f), Offset(30f, tearY), Offset(size.width - 30f, tearY), 1.5.dp.toPx(),
                            cap = StrokeCap.Round, pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 9f)),
                        )
                    }
                }
                .clickable(interactionSource = press, indication = null) { leave(0f, -380f, onOpen) },
        ) {
            // ---- top half: who, where, when, how much
            Column(
                Modifier.fillMaxWidth()
                    .onGloballyPositioned { tearY = it.size.height.toFloat() }
                    .padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 16.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    AppTile()
                    Spacer(Modifier.width(8.dp))
                    Text("Haraan Partner", fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xE6FFFFFF))
                    Text("  ·  now", fontSize = 12.5.sp, color = Color(0xB3FFFFFF))
                    Spacer(Modifier.weight(1f))
                    if (alert.more > 0) {
                        Text(
                            "+${alert.more} more", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color.White,
                            modifier = Modifier.clip(RoundedCornerShape(99.dp)).background(Color(0x33FFFFFF)).padding(horizontal = 8.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(12.dp))
                Text(
                    if (alert.walkIn) "Walk-in booked" else "New booking",
                    fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xCCFFFFFF),
                )
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Initials(alert.customer)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text(alert.customer, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text(
                            listOfNotNull(alert.where, alert.time, alert.day).joinToString(" · "),
                            fontSize = 13.sp, color = Color(0xD9FFFFFF), maxLines = 1, overflow = TextOverflow.Ellipsis,
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                    Text(
                        "₹" + formatInr(alert.amount),
                        fontSize = 28.sp, fontWeight = FontWeight.Bold, color = Color.White,
                        letterSpacing = (-0.8).sp, style = TextStyle(fontFeatureSettings = "tnum"),
                        modifier = Modifier.graphicsLayer { val k = 1f + 0.12f * stamp.value; scaleX = k; scaleY = k },
                    )
                }
            }
            // ---- bottom half: the money's state, and the choice
            Row(
                Modifier.fillMaxWidth().padding(start = 16.dp, end = 12.dp, top = 12.dp, bottom = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                PayChip(alert, formatInr)
                Spacer(Modifier.weight(1f))
                Text(
                    "Later", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Color(0xE6FFFFFF),
                    modifier = Modifier.clip(RoundedCornerShape(12.dp)).clickable { leave(0f, -380f, onDismiss) }
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                )
                Spacer(Modifier.width(4.dp))
                OpenButton(fill = timer.value) { leave(0f, -380f, onOpen) }
            }
        }
    }
}

/** The ticket: a rounded card with a half-circle notch cut into each side at the tear line. */
private class TicketShape(private val tearY: Float, private val cornerPx: Float) : Shape {
    override fun createOutline(size: Size, layoutDirection: LayoutDirection, density: Density): Outline {
        val r = with(density) { 24.dp.toPx() }
        val notch = with(density) { 9.dp.toPx() }
        val card = Path().apply { addRoundRect(RoundRect(Rect(Offset.Zero, size), androidx.compose.ui.geometry.CornerRadius(r))) }
        if (tearY <= 0f) return Outline.Generic(card)
        val cuts = Path().apply {
            addOval(Rect(Offset(0f, tearY), notch))
            addOval(Rect(Offset(size.width, tearY), notch))
        }
        return Outline.Generic(Path().apply { op(card, cuts, PathOperation.Difference) })
    }
}

/** Haraan's app tile, small — the blue ramp with the real three-piece H. */
@Composable
private fun AppTile() {
    Box(
        Modifier.size(22.dp)
            .clip(RoundedCornerShape(6.dp))
            .background(Brush.linearGradient(listOf(Color(0xFF4D8BFF), Color(0xFF2563EB), Color(0xFF0A2A93))))
            .border(1.dp, Color(0x59FFFFFF), RoundedCornerShape(6.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(painterResource(R.drawable.ic_haraan_mark), contentDescription = "Haraan", tint = Color.White, modifier = Modifier.size(13.dp))
    }
}

/** The customer's initials on a white disc. */
@Composable
private fun Initials(name: String) {
    val p = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
    val text = when {
        p.isEmpty() -> "•"
        p.size == 1 -> p[0].take(2).uppercase()
        else -> (p[0].take(1) + p[1].take(1)).uppercase()
    }
    Box(
        Modifier.size(44.dp).clip(RoundedCornerShape(99.dp)).background(Color.White),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = BrandInk) }
}

/** Open, with the time left filling it from the left like a ride request's accept button. */
@Composable
private fun OpenButton(fill: Float, onClick: () -> Unit) {
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val k by androidx.compose.animation.core.animateFloatAsState(if (pressed) 0.93f else 1f, spring(dampingRatio = 0.6f), label = "open-press")
    Box(
        Modifier
            .graphicsLayer { scaleX = k; scaleY = k }
            .shadow(8.dp, RoundedCornerShape(12.dp), clip = false, spotColor = Color(0x661E3A8A))
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFDCE7FF))
            .drawBehind { drawRect(Color.White, size = Size(size.width * fill.coerceIn(0f, 1f), size.height)) }
            .clickable(interactionSource = press, indication = null, onClick = onClick)
            .padding(horizontal = 24.dp, vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) { Text("Open", fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = BrandInk) }
}

/** Paid or not, in one chip — the first thing a desk needs to know about the money. */
@Composable
private fun PayChip(alert: BookingAlert, formatInr: (Double) -> String) {
    val paid = alert.fullyPaid
    val owed = (alert.amount - alert.paid).coerceAtLeast(0.0)
    Row(
        Modifier.clip(RoundedCornerShape(99.dp))
            .background(if (paid) Color(0x2622C55E) else Color(0x33F59E0B))
            .border(1.dp, if (paid) Color(0x6686EFAC) else Color(0x80FCD34D), RoundedCornerShape(99.dp))
            .padding(horizontal = 10.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(12.dp)) {
            if (paid) {
                drawLine(Color(0xFFBBF7D0), Offset(size.width * 0.15f, size.height * 0.55f), Offset(size.width * 0.42f, size.height * 0.8f), 2.dp.toPx(), StrokeCap.Round)
                drawLine(Color(0xFFBBF7D0), Offset(size.width * 0.42f, size.height * 0.8f), Offset(size.width * 0.88f, size.height * 0.22f), 2.dp.toPx(), StrokeCap.Round)
            } else {
                drawCircle(Color(0xFFFDE68A), size.minDimension / 2, style = Stroke(1.6.dp.toPx()))
                drawLine(Color(0xFFFDE68A), center, Offset(center.x, size.height * 0.2f), 1.6.dp.toPx(), StrokeCap.Round)
                drawLine(Color(0xFFFDE68A), center, Offset(size.width * 0.75f, center.y), 1.6.dp.toPx(), StrokeCap.Round)
            }
        }
        Spacer(Modifier.width(6.dp))
        Text(
            if (paid) "Paid" + if (alert.walkIn) "" else " online" else "₹" + formatInr(owed) + " to collect",
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold,
            color = if (paid) Color(0xFFDCFCE7) else Color(0xFFFEF3C7),
        )
    }
}
