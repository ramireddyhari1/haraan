package com.haraan.partner.payments

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.BookingSummary
import com.haraan.partner.PartnerApi
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import kotlinx.coroutines.delay

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hair = Color(0x140F172A)
private val Page = Color(0xFFF7F8FB)
private val BrandLight = Color(0xFF4D8BFF)
private val Brand = Color(0xFF2563EB)
private val BrandDeep = Color(0xFF1E40AF)
private val BrandInk = Color(0xFF1D4ED8)
private val Due = Color(0xFFDC2626)
private val Good = Color(0xFF15803D)

/** How a payment came in. Haraan blues, plus one warm accent for cash. */
private enum class Way(val label: String, val color: Color) {
    Upi("UPI", Color(0xFF2563EB)),
    Online("Haraan", Color(0xFF60A5FA)),
    Cash("Cash", Color(0xFFF59E0B)),
    Card("Card", Color(0xFF1E3A8A)),
}

private data class Pay(
    val id: Long,
    val name: String,
    val amount: Double,
    val paid: Double,
    val way: Way?,
    val walkIn: Boolean,
    val at: java.util.Date,
    val where: String?,
    val slot: String?,
    /** The booking it came from — the detail sheet reads the rest off it. */
    val src: BookingSummary,
) {
    val owed get() = (amount - paid).coerceAtLeast(0.0)
}

private enum class Filter(val label: String) { All("All"), Received("Received"), Due("To collect"), WalkIn("Walk-in"), Online("Online") }

/**
 * Payments, as their own place: every booking's money in one history, newest first,
 * by day — like a shop's payments app. The month at the top, how it came in, then
 * each payment: who, how, when, and whether it's in or still to collect.
 */
@Composable
fun PaymentsScreen(api: PartnerApi, token: String, branchId: Long?, canManage: Boolean = true) {
    val view = LocalView.current
    var reload by remember { mutableIntStateOf(0) }
    val data by produceState<List<Pay>?>(null, token, branchId, reload) {
        value = runCatching { toPays(api.bookings(token, branchId)) }.getOrDefault(emptyList())
    }
    var filter by remember { mutableStateOf(Filter.All) }
    var open by remember { mutableStateOf<BookingSummary?>(null) }
    open?.let { b ->
        PaymentDetailSheet(
            b = b, api = api, token = token, canManage = canManage,
            onChanged = { reload++ },
            onDismiss = { open = null },
        )
    }
    var query by remember { mutableStateOf("") }

    Column(Modifier.fillMaxSize().background(Page)) {
        Text(
            "Payments", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Ink, letterSpacing = (-0.4).sp,
            modifier = Modifier.padding(start = 20.dp, end = 20.dp, top = 6.dp, bottom = 2.dp),
        )
        val all = data
        if (all == null) {
            Skeleton()
            return@Column
        }
        val month = remember(all) { thisMonth(all) }
        val shown = all.filter { p ->
            val okFilter = when (filter) {
                Filter.All -> true
                Filter.Received -> p.paid > 0
                Filter.Due -> p.owed > 0
                Filter.WalkIn -> p.walkIn
                Filter.Online -> !p.walkIn
            }
            okFilter && (query.isBlank() || p.name.contains(query.trim(), ignoreCase = true))
        }
        val days = shown.groupBy { dayKey(it.at) }

        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 120.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item(key = "month") { MonthCard(month) }
            item(key = "filters") {
                Column {
                    Spacer(Modifier.height(4.dp))
                    SearchField(query) { query = it }
                    Spacer(Modifier.height(10.dp))
                    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Filter.entries.forEach { f ->
                            Chip(f.label, filter == f) { Haptics.tick(view); filter = f }
                        }
                    }
                }
            }
            if (shown.isEmpty()) {
                item(key = "empty") { Empty(if (all.isEmpty()) "No payments yet. They'll show here the moment a booking is paid." else "Nothing matches that.") }
            }
            var index = 0
            days.forEach { (key, rows) ->
                item(key = "day-$key") { DayHeader(dayWord(rows.first().at), rows.sumOf { it.paid }, rows.sumOf { it.owed }) }
                item(key = "list-$key-$filter-$query") {
                    Column(
                        Modifier.fillMaxWidth()
                            .shadow(6.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x142563EB))
                            .clip(RoundedCornerShape(18.dp))
                            .background(Color.White)
                            .border(1.dp, Hair, RoundedCornerShape(18.dp))
                            .padding(horizontal = 14.dp, vertical = 4.dp),
                    ) {
                        rows.forEachIndexed { i, p ->
                            PayRow(p, index++) { Haptics.tick(view); open = p.src }
                            if (i < rows.lastIndex) Box(Modifier.fillMaxWidth().padding(start = 50.dp).height(1.dp).background(Hair))
                        }
                    }
                }
            }
            item(key = "foot") {
                Text(
                    // The feed is the latest 100 bookings; only then is there anything older to mention.
                    if (all.size >= 100) "Showing your latest ${all.size} payments." else "That's every payment so far.",
                    fontSize = 12.sp, color = Muted, modifier = Modifier.padding(top = 6.dp, start = 4.dp),
                )
            }
        }
    }
}

private data class Month(val received: Double, val due: Double, val count: Int, val byWay: Map<Way, Double>, val label: String)

private fun thisMonth(all: List<Pay>): Month {
    val now = java.util.Calendar.getInstance()
    val mine = all.filter {
        val c = java.util.Calendar.getInstance().apply { time = it.at }
        c.get(java.util.Calendar.YEAR) == now.get(java.util.Calendar.YEAR) && c.get(java.util.Calendar.MONTH) == now.get(java.util.Calendar.MONTH)
    }
    return Month(
        received = mine.sumOf { it.paid },
        due = all.sumOf { it.owed },
        count = mine.count { it.paid > 0 },
        byWay = Way.entries.associateWith { w -> mine.filter { it.way == w }.sumOf { it.paid } }.filterValues { it > 0 },
        label = java.text.SimpleDateFormat("MMMM", java.util.Locale.ENGLISH).format(now.time),
    )
}

/** The month: what came in, how, and what's still out — on Haraan's blue. */
@Composable
private fun MonthCard(m: Month) {
    val count = remember(m.received) { Animatable(0f) }
    LaunchedEffect(m.received) { count.animateTo(m.received.toFloat(), tween(900, easing = FastOutSlowInEasing)) }
    Column(
        Modifier.fillMaxWidth()
            .shadow(14.dp, RoundedCornerShape(22.dp), clip = false, spotColor = Color(0x661E40AF))
            .clip(RoundedCornerShape(22.dp))
            .background(Brush.linearGradient(listOf(BrandLight, Brand, BrandDeep), start = Offset.Zero, end = Offset(1000f, 900f)))
            .border(1.dp, Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.35f), Color.White.copy(alpha = 0.06f))), RoundedCornerShape(22.dp))
            .padding(18.dp),
    ) {
        Text("Received in ${m.label}", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color(0xD9FFFFFF))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "₹" + formatInr(kotlin.math.round(count.value.toDouble())),
                fontSize = 34.sp, lineHeight = 38.sp, fontWeight = FontWeight.Bold, color = Color.White,
                letterSpacing = (-1).sp, style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                when (m.count) { 0 -> "no payments yet"; 1 -> "1 payment"; else -> "${m.count} payments" },
                fontSize = 13.sp, color = Color(0xCCFFFFFF), modifier = Modifier.padding(bottom = 6.dp),
            )
        }
        if (m.byWay.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            val grow = remember { Animatable(0f) }
            LaunchedEffect(m.byWay) { grow.snapTo(0f); grow.animateTo(1f, tween(700, easing = FastOutSlowInEasing)) }
            Row(
                Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x33FFFFFF))
                    .graphicsLayer { scaleX = grow.value; transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0.5f) },
                horizontalArrangement = Arrangement.spacedBy(2.dp),
            ) { m.byWay.forEach { (w, a) -> Box(Modifier.weight(a.toFloat()).fillMaxHeight().background(if (w == Way.Cash) Color(0xFFFCD34D) else Color.White.copy(alpha = if (w == Way.Online) 0.65f else 1f))) } }
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                m.byWay.forEach { (w, a) ->
                    Column {
                        Text(w.label, fontSize = 11.sp, color = Color(0xB3FFFFFF))
                        Text("₹" + formatInr(a), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Color.White, style = TextStyle(fontFeatureSettings = "tnum"))
                    }
                }
            }
        }
        if (m.due > 0) {
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.clip(RoundedCornerShape(99.dp)).background(Color(0x33F59E0B))
                    .border(1.dp, Color(0x80FCD34D), RoundedCornerShape(99.dp))
                    .padding(horizontal = 10.dp, vertical = 5.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFFDE68A)))
                Spacer(Modifier.width(6.dp))
                Text("₹" + formatInr(m.due) + " still to collect", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFFFEF3C7))
            }
        }
    }
}

@Composable
private fun SearchField(value: String, onChange: (String) -> Unit) {
    Row(
        Modifier.fillMaxWidth().height(46.dp).clip(RoundedCornerShape(14.dp)).background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(14.dp)).padding(horizontal = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.Search, contentDescription = null, tint = Muted, modifier = Modifier.size(19.dp))
        Spacer(Modifier.width(10.dp))
        Box(Modifier.weight(1f)) {
            if (value.isEmpty()) Text("Search by name", fontSize = 14.5.sp, color = Muted)
            BasicTextField(value, onChange, singleLine = true, textStyle = TextStyle(fontSize = 14.5.sp, color = Ink), modifier = Modifier.fillMaxWidth())
        }
    }
}

@Composable
private fun Chip(label: String, on: Boolean, onClick: () -> Unit) {
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val k by animateFloatAsState(if (pressed) 0.93f else 1f, spring(dampingRatio = 0.6f), label = "chip")
    Text(
        label, fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
        color = if (on) Color.White else Ink, maxLines = 1,
        modifier = Modifier
            .graphicsLayer { scaleX = k; scaleY = k }
            .clip(RoundedCornerShape(99.dp))
            .background(if (on) BrandInk else Color.White)
            .border(1.dp, if (on) Color.Transparent else Color(0x1F0F172A), RoundedCornerShape(99.dp))
            .clickable(interactionSource = press, indication = null, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    )
}

@Composable
private fun DayHeader(day: String, received: Double, owed: Double) {
    Row(Modifier.fillMaxWidth().padding(top = 8.dp, start = 4.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(day, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
        if (received > 0) Text("+₹" + formatInr(received), fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Good)
        if (owed > 0) Text("   ₹" + formatInr(owed) + " due", fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Due)
    }
}

@Composable
private fun PayRow(p: Pay, index: Int, onOpen: () -> Unit) {
    val enter = remember { Animatable(0f) }
    LaunchedEffect(Unit) { delay(index.coerceAtMost(10) * 35L); enter.animateTo(1f, tween(300, easing = FastOutSlowInEasing)) }
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val sink by animateFloatAsState(if (pressed) 1f else 0f, spring(dampingRatio = 0.7f), label = "row")
    Row(
        Modifier.fillMaxWidth()
            .graphicsLayer {
                alpha = enter.value
                translationY = (1f - enter.value) * 10.dp.toPx()
                val k = 1f - 0.02f * sink; scaleX = k; scaleY = k
            }
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFF2563EB).copy(alpha = 0.05f * sink))
            .clickable(interactionSource = press, indication = null, onClick = onOpen)
            .padding(vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        val tint = p.way?.color ?: Color(0xFFCBD5E1)
        Box(Modifier.size(38.dp).clip(RoundedCornerShape(99.dp)).background(tint.copy(alpha = 0.14f)), contentAlignment = Alignment.Center) {
            Text(initials(p.name), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (p.way == Way.Cash) Color(0xFFB45309) else BrandInk)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(p.name, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                listOfNotNull(
                    p.way?.let { if (it == Way.Online) "Paid on Haraan" else it.label } ?: if (p.paid <= 0) "Not paid" else null,
                    if (p.walkIn) "Walk-in" else null,
                    p.slot,
                    java.text.SimpleDateFormat("h:mm a", java.util.Locale.ENGLISH).format(p.at),
                ).joinToString(" · "),
                fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            if (p.paid > 0) Text("+₹" + formatInr(p.paid), fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
            if (p.owed > 0) Text("₹" + formatInr(p.owed) + " due", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Due)
            else if (p.paid > 0) Text("Received", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Good)
        }
    }
}

/** A counter with a QR stand, waiting — nothing in the history yet. */
@Composable
private fun Empty(text: String) {
    Column(Modifier.fillMaxWidth().padding(top = 24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Canvas(Modifier.size(width = 96.dp, height = 74.dp)) {
            val ink = Color(0xFF94A3B8)
            val sw = 2.dp.toPx()
            val w = size.width
            val h = size.height
            drawLine(ink, Offset(0f, h * 0.94f), Offset(w, h * 0.94f), sw, StrokeCap.Round)
            drawRoundRect(ink, Offset(w * 0.3f, h * 0.06f), Size(w * 0.4f, h * 0.72f), CornerRadius(5.dp.toPx()), style = Stroke(sw))
            drawLine(ink, Offset(w * 0.5f, h * 0.78f), Offset(w * 0.5f, h * 0.94f), sw)
            val q = w * 0.1f
            listOf(Offset(w * 0.36f, h * 0.15f), Offset(w * 0.54f, h * 0.15f), Offset(w * 0.36f, h * 0.42f)).forEach { o ->
                drawRect(Brand, o, Size(q, q), style = Stroke(sw * 0.9f))
            }
            drawRect(Brand, Offset(w * 0.56f, h * 0.45f), Size(q * 0.6f, q * 0.6f))
        }
        Spacer(Modifier.height(10.dp))
        Text(text, fontSize = 13.5.sp, color = Muted, modifier = Modifier.padding(horizontal = 24.dp))
    }
}

@Composable
private fun Skeleton() {
    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.fillMaxWidth().height(150.dp).clip(RoundedCornerShape(22.dp)).background(Color(0xFFE8EDF5)))
        repeat(4) { Box(Modifier.fillMaxWidth().height(62.dp).clip(RoundedCornerShape(18.dp)).background(Color(0xFFEDF1F7))) }
    }
}

private fun toPays(list: List<BookingSummary>): List<Pay> {
    val fmt = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.US)
    return list.mapNotNull { b ->
        val st = (b.status ?: "").lowercase()
        if (b.amount <= 0 || st.startsWith("cancel") || st == "expired" || st == "refunded") return@mapNotNull null
        val at = b.createdAt?.let { runCatching { fmt.parse(it) }.getOrNull() } ?: return@mapNotNull null
        val way = when ((b.paymentMethod ?: "").lowercase()) {
            "cash" -> Way.Cash
            "upi", "upi_qr" -> Way.Upi
            "card" -> Way.Card
            "" -> if (b.amountPaid > 0) Way.Online else null
            else -> Way.Online
        }
        Pay(
            id = b.id, name = b.customer, amount = b.amount, paid = b.amountPaid, way = way,
            walkIn = b.channel.equals("offline", true), at = at, where = b.branch ?: b.label,
            src = b,
            slot = b.slotLabel?.substringAfterLast("·")?.split("–", "-")?.firstOrNull()?.trim()?.takeIf { it.isNotBlank() }?.let { "for $it" },
        )
    }.sortedByDescending { it.at }
}

private fun dayKey(d: java.util.Date): String = java.text.SimpleDateFormat("yyyyMMdd", java.util.Locale.US).format(d)

private fun dayWord(d: java.util.Date): String {
    val k = dayKey(d)
    val today = dayKey(java.util.Date())
    val yesterday = dayKey(java.util.Date(System.currentTimeMillis() - 86_400_000L))
    return when (k) {
        today -> "Today"
        yesterday -> "Yesterday"
        else -> java.text.SimpleDateFormat("EEE, d MMM", java.util.Locale.ENGLISH).format(d)
    }
}

private fun initials(name: String): String {
    val p = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
    return when {
        p.isEmpty() -> "•"
        p.size == 1 -> p[0].take(2).uppercase()
        else -> (p[0].take(1) + p[1].take(1)).uppercase()
    }
}
