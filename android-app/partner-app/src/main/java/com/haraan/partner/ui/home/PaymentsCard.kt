package com.haraan.partner.ui.home

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hair = Color(0x140F172A)
private val Brand = Color(0xFF2563EB)
private val BrandInk = Color(0xFF1D4ED8)
private val Due = Color(0xFFDC2626)

/** How a payment was taken. Colours stay inside Haraan's blues plus one warm accent for cash. */
enum class PayWay(val label: String, val color: Color) {
    Upi("UPI", Color(0xFF2563EB)),
    Online("Haraan", Color(0xFF60A5FA)),
    Cash("Cash", Color(0xFFF59E0B)),
    Card("Card", Color(0xFF1E3A8A)),
}

/** One line of the feed: who, how much, how, when. */
data class PaymentEntry(
    val name: String,
    val amount: Double,
    val paid: Double,
    val way: PayWay?,
    val walkIn: Boolean,
    /** "2:30 PM" for today, "Yesterday", "28 Sep" before that. */
    val whenLabel: String,
    val today: Boolean,
)

/**
 * Money on Home, the way a shop's payments app shows it: what came in today, how
 * people paid, the payments themselves, and what's on its way to the bank. Every
 * number is a row on the ledger; nothing here is estimated.
 */
@Composable
fun PaymentsCard(
    entries: List<PaymentEntry>,
    toBank: Double?,
    formatInr: (Double) -> String,
    onViewAll: () -> Unit,
    onSettlement: (() -> Unit)?,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val todays = entries.filter { it.today && it.paid > 0 }
    val received = todays.sumOf { it.paid }
    val byWay = PayWay.entries.associateWith { w -> todays.filter { it.way == w }.sumOf { it.paid } }.filterValues { it > 0 }
    val count = remember(received) { Animatable(0f) }
    LaunchedEffect(received) { count.animateTo(received.toFloat(), tween(900, easing = FastOutSlowInEasing)) }

    Column(
        modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(22.dp), clip = false, spotColor = Color(0x1F2563EB))
            .clip(RoundedCornerShape(22.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(22.dp))
            .padding(18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Received today", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted, modifier = Modifier.weight(1f))
            Row(
                Modifier.clip(RoundedCornerShape(99.dp)).clickable { Haptics.tick(view); onViewAll() }.padding(start = 8.dp, top = 4.dp, bottom = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("View all", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = BrandInk)
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = BrandInk, modifier = Modifier.size(16.dp))
            }
        }
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "₹" + formatInr(kotlin.math.round(count.value.toDouble())),
                fontSize = 32.sp, lineHeight = 36.sp, fontWeight = FontWeight.Bold, color = Ink,
                letterSpacing = (-0.8).sp, style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                when (todays.size) { 0 -> "no payments yet"; 1 -> "1 payment"; else -> "${todays.size} payments" },
                fontSize = 13.sp, color = Muted, modifier = Modifier.padding(bottom = 6.dp),
            )
        }

        // How people paid: one bar, split by the money each way brought in.
        if (byWay.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            val grow = remember { Animatable(0f) }
            LaunchedEffect(byWay) { grow.snapTo(0f); grow.animateTo(1f, tween(700, easing = FastOutSlowInEasing)) }
            Row(
                Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFEFF3FA))
                    .graphicsLayer { scaleX = grow.value; transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0.5f) },
                horizontalArrangement = Arrangement.spacedBy(2.dp),
            ) {
                byWay.forEach { (w, amt) -> Box(Modifier.weight(amt.toFloat()).fillMaxHeight().background(w.color)) }
            }
            Spacer(Modifier.height(10.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                byWay.forEach { (w, amt) ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Canvas(Modifier.size(18.dp)) { wayGlyph(w) }
                        Spacer(Modifier.width(5.dp))
                        Column {
                            Text(w.label, fontSize = 11.sp, color = Muted)
                            Text("₹" + formatInr(amt), fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
                        }
                    }
                }
            }
        }

        // The feed itself.
        val recent = entries.take(5)
        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
        if (recent.isEmpty()) {
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Canvas(Modifier.size(width = 52.dp, height = 40.dp)) { emptyTill() }
                Spacer(Modifier.width(12.dp))
                Text("Payments show up here the moment a booking is paid.", fontSize = 13.sp, color = Muted, lineHeight = 18.sp)
            }
        } else {
            recent.forEachIndexed { i, e -> PaymentRow(e, i, formatInr) }
        }

        // What's on its way to the bank.
        if (toBank != null && toBank > 0 && onSettlement != null) {
            Spacer(Modifier.height(12.dp))
            val interaction = remember { MutableInteractionSource() }
            Row(
                Modifier.fillMaxWidth()
                    .pressScale(interaction, pressedScale = 0.98f)
                    .clip(RoundedCornerShape(14.dp))
                    .background(Color(0xFFEFF5FF))
                    .pressShade(interaction, amount = 0.04f)
                    .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onSettlement() }
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Canvas(Modifier.size(22.dp)) { bankGlyph() }
                Spacer(Modifier.width(10.dp))
                Text(
                    "₹" + formatInr(toBank) + " on its way to your bank",
                    fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = BrandInk, modifier = Modifier.weight(1f),
                )
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = BrandInk, modifier = Modifier.size(18.dp))
            }
        }
    }
}

@Composable
private fun PaymentRow(e: PaymentEntry, index: Int, formatInr: (Double) -> String) {
    val enter = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        delay(120L + index * 50L)
        enter.animateTo(1f, tween(320, easing = FastOutSlowInEasing))
    }
    val owed = (e.amount - e.paid).coerceAtLeast(0.0)
    Row(
        Modifier.fillMaxWidth()
            .graphicsLayer { alpha = enter.value; translationY = (1f - enter.value) * 10.dp.toPx() }
            .padding(top = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Initials on the colour of the way they paid; grey when nothing's in yet.
        val tint = e.way?.color ?: Color(0xFFCBD5E1)
        Box(
            Modifier.size(38.dp).clip(RoundedCornerShape(99.dp)).background(tint.copy(alpha = 0.14f)),
            contentAlignment = Alignment.Center,
        ) {
            Text(initialsOf(e.name), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (e.way == PayWay.Cash) Color(0xFFB45309) else BrandInk)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(e.name, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                listOfNotNull(
                    e.way?.let { if (it == PayWay.Online) "Paid on Haraan" else it.label } ?: if (e.paid <= 0) "Not paid" else null,
                    if (e.walkIn) "Walk-in" else null,
                    e.whenLabel,
                ).joinToString(" · "),
                fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            if (e.paid > 0) {
                Text("+₹" + formatInr(e.paid), fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
            }
            if (owed > 0) {
                Text("₹" + formatInr(owed) + " due", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Due)
            } else if (e.paid > 0) {
                Text("Received", fontSize = 11.5.sp, color = Color(0xFF15803D), fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

private fun initialsOf(name: String): String {
    val p = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
    return when {
        p.isEmpty() -> "•"
        p.size == 1 -> p[0].take(2).uppercase()
        else -> (p[0].take(1) + p[1].take(1)).uppercase()
    }
}

/** Small marks for each way of paying, drawn in that way's colour. */
private fun DrawScope.wayGlyph(w: PayWay) {
    val c = w.color
    val sw = 1.6.dp.toPx()
    val s = size.minDimension
    when (w) {
        PayWay.Cash -> {
            // A note: rectangle, a coin-circle in the middle.
            drawRoundRect(c, Offset(0f, s * 0.22f), Size(s, s * 0.56f), CornerRadius(2.dp.toPx()), style = Stroke(sw))
            drawCircle(c, s * 0.13f, Offset(s / 2, s / 2), style = Stroke(sw))
        }
        PayWay.Card -> {
            drawRoundRect(c, Offset(0f, s * 0.2f), Size(s, s * 0.6f), CornerRadius(2.5.dp.toPx()), style = Stroke(sw))
            drawLine(c, Offset(0f, s * 0.38f), Offset(s, s * 0.38f), sw * 1.4f)
            drawLine(c, Offset(s * 0.15f, s * 0.64f), Offset(s * 0.42f, s * 0.64f), sw, StrokeCap.Round)
        }
        PayWay.Upi -> {
            // UPI's two leaning arrows.
            val a = Path().apply { moveTo(s * 0.18f, s * 0.15f); lineTo(s * 0.55f, s * 0.5f); lineTo(s * 0.18f, s * 0.85f); close() }
            val b = Path().apply { moveTo(s * 0.45f, s * 0.15f); lineTo(s * 0.82f, s * 0.5f); lineTo(s * 0.45f, s * 0.85f); close() }
            drawPath(a, c.copy(alpha = 0.55f))
            drawPath(b, c)
        }
        PayWay.Online -> {
            // Haraan's H: two stems and the crossbar.
            drawLine(c, Offset(s * 0.25f, s * 0.15f), Offset(s * 0.25f, s * 0.85f), sw * 1.5f, StrokeCap.Round)
            drawLine(c, Offset(s * 0.75f, s * 0.15f), Offset(s * 0.75f, s * 0.85f), sw * 1.5f, StrokeCap.Round)
            drawLine(c, Offset(s * 0.25f, s * 0.55f), Offset(s * 0.75f, s * 0.45f), sw * 1.5f, StrokeCap.Round)
        }
    }
}

private fun DrawScope.bankGlyph() {
    val c = BrandInk
    val sw = 1.6.dp.toPx()
    val w = size.width
    val h = size.height
    val roof = Path().apply { moveTo(w * 0.08f, h * 0.38f); lineTo(w * 0.5f, h * 0.1f); lineTo(w * 0.92f, h * 0.38f); close() }
    drawPath(roof, c.copy(alpha = 0.15f))
    drawPath(roof, c, style = Stroke(sw))
    listOf(0.25f, 0.5f, 0.75f).forEach { x -> drawLine(c, Offset(w * x, h * 0.48f), Offset(w * x, h * 0.78f), sw, StrokeCap.Round) }
    drawLine(c, Offset(w * 0.08f, h * 0.9f), Offset(w * 0.92f, h * 0.9f), sw * 1.2f, StrokeCap.Round)
}

/** A counter with a QR stand on it, waiting — the empty feed. */
private fun DrawScope.emptyTill() {
    val ink = Color(0xFF94A3B8)
    val sw = 1.6.dp.toPx()
    val w = size.width
    val h = size.height
    drawLine(ink, Offset(0f, h * 0.92f), Offset(w, h * 0.92f), sw, StrokeCap.Round)
    // Stand
    drawRoundRect(ink, Offset(w * 0.28f, h * 0.08f), Size(w * 0.44f, h * 0.7f), CornerRadius(3.dp.toPx()), style = Stroke(sw))
    drawLine(ink, Offset(w * 0.5f, h * 0.78f), Offset(w * 0.5f, h * 0.92f), sw)
    // QR corners in brand blue
    val q = w * 0.11f
    listOf(Offset(w * 0.34f, h * 0.16f), Offset(w * 0.53f, h * 0.16f), Offset(w * 0.34f, h * 0.42f)).forEach { o ->
        drawRect(Brand, o, Size(q, q), style = Stroke(sw * 0.9f))
    }
    drawRect(Brand, Offset(w * 0.55f, h * 0.45f), Size(q * 0.6f, q * 0.6f))
}
