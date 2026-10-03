package com.haraan.partner.ui.venues

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.partner.CourtPricing
import com.haraan.partner.DayGrid
import com.haraan.partner.PartnerApi
import com.haraan.partner.RefreshableContent
import com.haraan.partner.SlotEdit
import com.haraan.partner.VenueSummary
import com.haraan.partner.VenueHours
import com.haraan.partner.formatInr
import com.haraan.partner.slotStartMinutes
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.home.CourtKind
import com.haraan.partner.ui.home.MiniCourt
import com.haraan.partner.ui.home.courtKindFor
import com.haraan.partner.ui.pricing.clock
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hairline = Color(0xFFE6EBF2)
private val PageBg = Color(0xFFF5F7FB)
private val Blue = Color(0xFF2563EB)
private val BlueDeep = Color(0xFF1E40AF)
private val BlueTint = Color(0xFFEFF4FF)
private val OpenGreen = Color(0xFF15803D)
private val Amber = Color(0xFFB45309)
private val AmberTint = Color(0xFFFFF7E8)

private val WEEK = listOf("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")

/**
 * Everything the Venues tab says about one venue, read off the same endpoints the desk
 * and the pricing screen use, so the numbers here can't disagree with them. Any piece
 * that fails to load is null and its section simply doesn't show.
 */
private data class VenueRoom(
    val v: VenueSummary,
    val courts: List<CourtPricing>?,
    val today: DayGrid?,
    val tomorrow: DayGrid?,
    val slots: List<SlotEdit>?,
    /** Saved opening hours, else read off the slots; null when neither is known. */
    val hours: VenueHours?,
    /** The public page's details (address, amenities…); null if not live or unreachable. */
    val details: com.haraan.partner.VenueDetails?,
)

private suspend fun loadRooms(api: PartnerApi, token: String): List<VenueRoom> = coroutineScope {
    val today = java.util.Calendar.getInstance()
    val tomorrow = (today.clone() as java.util.Calendar).apply { add(java.util.Calendar.DAY_OF_YEAR, 1) }
    val fmt = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
    val d0 = fmt.format(today.time)
    val d1 = fmt.format(tomorrow.time)
    api.venues(token).map { v ->
        async {
            val courts = async { runCatching { api.venueCourts(token, v.id) }.getOrNull() }
            val t0 = async { runCatching { api.venueDay(token, v.id, d0) }.getOrNull() }
            val t1 = async { runCatching { api.venueDay(token, v.id, d1) }.getOrNull() }
            val slots = async { runCatching { api.venueSlots(token, v.id) }.getOrNull() }
            val hours = async { runCatching { api.venueHours(token, v.id) }.getOrNull() }
            val details = async { runCatching { api.publicVenue(v.id) }.getOrNull() }
            val t = t0.await()
            val len = slotLength(hoursOf(t)).takeIf { hoursOf(t).size > 1 } ?: 60
            val saved = hours.await()?.takeIf { it.set }
            VenueRoom(v, courts.await(), t, t1.await(), slots.await(), saved ?: hoursFromSlots(slots.await(), len), details.await())
        }
    }.map { it.await() }
}

/**
 * The Venues tab: each venue's control room. What it looks like to players, how today
 * is going, what's set up wrong, each court with its own sport and rate, and the tools
 * that change them. A venue is added by Haraan, not here, so there's no "Add venue".
 */
@Composable
fun VenuesScreen(
    api: PartnerApi,
    token: String,
    canPricing: Boolean,
    canReports: Boolean,
    onDesk: (Long, String) -> Unit,
    onPricing: (Long, String) -> Unit,
    onAnalytics: (Long, String) -> Unit,
    onSupport: () -> Unit,
) {
    var reload by remember { mutableIntStateOf(0) }
    var editing by remember { mutableStateOf<VenueRoom?>(null) }
    editing?.let { room ->
        HoursEditor(
            api = api, token = token, venueId = room.v.id, venueName = room.v.name, current = room.hours,
            onDismiss = { editing = null },
            onSaved = { editing = null; reload++ },
        )
    }
    val editHours: (VenueRoom) -> Unit = { if (canPricing) editing = it else onSupport() }
    RefreshableContent(token, load = { loadRooms(api, token) }, reloadSignal = reload) { rooms ->
        LazyColumn(
            Modifier.fillMaxSize().background(PageBg),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 120.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            if (rooms.isEmpty()) {
                item { NoVenue(onSupport) }
            }
            rooms.forEachIndexed { i, room ->
                val v = room.v
                item(key = "hero-${v.id}") {
                    Reveal(i * 3) {
                        VenueHero(room, canPricing, onDesk = { onDesk(v.id, v.name) }, onPricing = { onPricing(v.id, v.name) }, onHours = { editHours(room) })
                    }
                }
                val issues = attentionFor(room)
                if (issues.isNotEmpty()) {
                    item(key = "fix-${v.id}") {
                        Reveal(i * 3 + 1) {
                            AttentionCard(issues) { action ->
                                when (action) {
                                    Fix.Pricing -> if (canPricing) onPricing(v.id, v.name) else onSupport()
                                    Fix.Support -> onSupport()
                                    Fix.Hours -> editHours(room)
                                }
                            }
                        }
                    }
                }
                item(key = "details-${v.id}") {
                    Reveal(i * 3 + 2) { VenueDetailsCard(room.details, published = room.details != null, onSupport = onSupport) }
                }
                if (!room.courts.isNullOrEmpty()) {
                    item(key = "courts-${v.id}") {
                        Reveal(i * 3 + 2) {
                            CourtsCard(room) { if (canPricing) onPricing(v.id, v.name) }
                        }
                    }
                }
                item(key = "manage-${v.id}") {
                    Reveal(i * 3 + 2) {
                        ManageCard(
                            v = v,
                            hours = hoursSummary(room.hours),
                            onHours = { editHours(room) },
                            canPricing = canPricing,
                            canReports = canReports,
                            onDesk = { onDesk(v.id, v.name) },
                            onPricing = { onPricing(v.id, v.name) },
                            onAnalytics = { onAnalytics(v.id, v.name) },
                        )
                    }
                }
            }
            if (rooms.isNotEmpty()) {
                item(key = "more") { AnotherVenue(onSupport) }
            }
        }
    }
}

/** Each card lifts into place a beat after the one above it — once, on first show. */
@Composable
private fun Reveal(order: Int, content: @Composable () -> Unit) {
    val p = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(order.coerceAtMost(6) * 55L)
        p.animateTo(1f, tween(420, easing = FastOutSlowInEasing))
    }
    Box(Modifier.graphicsLayer { alpha = p.value; translationY = (1f - p.value) * 18.dp.toPx() }) { content() }
}

private fun Modifier.card(radius: Int = 20): Modifier = this
    .shadow(8.dp, RoundedCornerShape(radius.dp), clip = false, spotColor = Color(0x140F172A))
    .clip(RoundedCornerShape(radius.dp))
    .background(Color.White)
    .border(1.dp, Hairline, RoundedCornerShape(radius.dp))

// ---- Hero ------------------------------------------------------------------

/** One hour of today, as much as the strip needs: when, and how full. */
private data class HourFill(val start: Int, val booked: Int, val total: Int, val freeValue: Double)

private fun hoursOf(grid: DayGrid?): List<HourFill> = grid?.slots.orEmpty().mapNotNull { s ->
    val start = slotStartMinutes(s.time ?: s.label)
    if (start == Int.MAX_VALUE || !s.isOpen) return@mapNotNull null
    val cells = s.courts.filter { it.allowed }
    if (cells.isNotEmpty()) {
        HourFill(start, cells.count { it.isBooked || it.isHeld }, cells.size, cells.filter { !it.isBooked && !it.isHeld }.sumOf { it.price })
    } else {
        val cap = s.capacity.coerceAtLeast(1)
        HourFill(start, s.booked.coerceAtMost(cap), cap, (cap - s.booked.coerceAtMost(cap)) * s.price)
    }
}.sortedBy { it.start }

private fun slotLength(hours: List<HourFill>): Int =
    hours.map { it.start }.distinct().zipWithNext { a, b -> b - a }.filter { it > 0 }.minOrNull()?.coerceIn(30, 60) ?: 60

private fun nowMinutes(): Int = java.util.Calendar.getInstance().let {
    it.get(java.util.Calendar.HOUR_OF_DAY) * 60 + it.get(java.util.Calendar.MINUTE)
}

/** "Open now · till 11 PM" and whether it's open, off the real slots. */
private fun openStatus(room: VenueRoom): Pair<String, Boolean> {
    if (room.today?.isBlocked == true) return "Closed today" to false
    val today = hoursOf(room.today)
    val now = nowMinutes()
    if (today.isNotEmpty()) {
        val len = slotLength(today)
        val first = today.first().start
        val end = today.last().start + len
        if (now < first) return "Opens at ${clock(first)}" to false
        if (now < end) return "Open now · till ${clock(end)}" to true
    }
    val next = if (room.tomorrow?.isBlocked == true) null else hoursOf(room.tomorrow).firstOrNull()?.start
    return (if (next != null) "Closed · opens ${clock(next)} tomorrow" else "Closed") to false
}

@Composable
private fun VenueHero(room: VenueRoom, canPricing: Boolean, onDesk: () -> Unit, onPricing: () -> Unit, onHours: () -> Unit) {
    val v = room.v
    val context = LocalContext.current
    val (status, open) = openStatus(room)
    val hours = hoursOf(room.today)
    val now = nowMinutes()
    val len = slotLength(hours)
    val ahead = hours.filter { it.start + len > now }
    val leftValue = ahead.sumOf { it.freeValue }
    val booked = hours.sumOf { it.booked }
    val total = hours.sumOf { it.total }

    Column(Modifier.fillMaxWidth().card(22)) {
        // The photo is shown as players see it, with nothing printed over it: a venue's
        // own image often carries its own lettering, and a name laid on top fights it.
        if (!v.image.isNullOrBlank()) {
            Box(Modifier.fillMaxWidth().height(156.dp)) {
                AsyncImage(
                    model = v.image, contentDescription = v.name,
                    contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize(),
                )
                StatusPill(status, open, onPhoto = true, modifier = Modifier.align(Alignment.TopStart).padding(12.dp))
            }
        }
        Column(Modifier.padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 18.dp)) {
            if (v.image.isNullOrBlank()) {
                StatusPill(status, open, onPhoto = false)
                Spacer(Modifier.height(10.dp))
            }
            Text(
                v.name, fontSize = 21.sp, fontWeight = FontWeight.Bold, color = Ink,
                letterSpacing = (-0.3).sp, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 26.sp,
            )
            (readableAddress(room.details?.address) ?: v.location?.takeIf { it.isNotBlank() }?.replaceFirstChar { c -> c.uppercase() })?.let {
                Spacer(Modifier.height(4.dp))
                Row(
                    Modifier.then(if (room.details != null) Modifier.pressableTile(8.dp, pressedScale = 0.98f) { openDirections(context, room.details) } else Modifier),
                    verticalAlignment = Alignment.Top,
                ) {
                    Icon(Icons.Filled.Place, null, tint = Faint, modifier = Modifier.padding(top = 2.dp).size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(it, fontSize = 13.sp, color = Muted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 18.sp)
                }
            }
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.fillMaxWidth().pressableTile(12.dp, pressedScale = 0.98f, onClick = onHours)
                    .clip(RoundedCornerShape(12.dp)).border(1.dp, Hairline, RoundedCornerShape(12.dp))
                    .padding(start = 12.dp, end = 8.dp, top = 9.dp, bottom = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Schedule, null, tint = Blue, modifier = Modifier.size(17.dp))
                Spacer(Modifier.width(9.dp))
                Text(
                    hoursSummary(room.hours) ?: "Set your opening hours",
                    fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                    maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp, modifier = Modifier.weight(1f),
                )
                Spacer(Modifier.width(8.dp))
                Text(if (canPricing) "Edit" else "", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue)
                Icon(Icons.Filled.ChevronRight, null, tint = if (canPricing) Blue else Faint, modifier = Modifier.size(18.dp))
            }

            Spacer(Modifier.height(18.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("Today", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
                Text(
                    when {
                        room.today == null -> "Couldn't load today"
                        room.today.isBlocked -> "Marked closed"
                        total == 0 -> "No slots today"
                        else -> "$booked of $total booked"
                    },
                    fontSize = 12.5.sp, color = Muted,
                )
            }
            Spacer(Modifier.height(8.dp))
            DayStrip(hours, now, len)

            Spacer(Modifier.height(16.dp))
            Row(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0xFFF7F9FC)).padding(vertical = 12.dp)) {
                Fact(
                    if (total == 0) "—" else "₹" + formatInr(leftValue),
                    "left to sell today", Modifier.weight(1f),
                )
                FactDivider()
                Fact(
                    (room.courts?.size ?: room.today?.courts?.size ?: 0).let { if (it == 0) "1" else "$it" },
                    if ((room.courts?.size ?: 0) == 1) "court" else "courts", Modifier.weight(1f),
                )
                FactDivider()
                Fact("₹" + formatInr(v.revenue), "earned · ${v.bookings} ${if (v.bookings == 1) "booking" else "bookings"}", Modifier.weight(1.2f))
            }

            Spacer(Modifier.height(16.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.weight(1f).pressableTile(14.dp, pressedScale = 0.97f, onClick = onDesk)
                        .height(48.dp).clip(RoundedCornerShape(14.dp))
                        .background(Brush.verticalGradient(listOf(Color(0xFF3B7BFF), Blue))),
                    contentAlignment = Alignment.Center,
                ) { Text("Open desk", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color.White) }
                if (canPricing) {
                    Spacer(Modifier.width(10.dp))
                    Box(
                        Modifier.weight(1f).pressableTile(14.dp, pressedScale = 0.97f, onClick = onPricing)
                            .height(48.dp).clip(RoundedCornerShape(14.dp)).background(BlueTint),
                        contentAlignment = Alignment.Center,
                    ) { Text("Slots & pricing", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Blue) }
                }
                Spacer(Modifier.width(10.dp))
                Box(
                    Modifier.pressableTile(14.dp) { shareVenue(context, v) }
                        .size(48.dp).clip(RoundedCornerShape(14.dp)).background(BlueTint),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Share, "Share venue link", tint = Blue, modifier = Modifier.size(20.dp)) }
            }
        }
    }
}

@Composable
private fun StatusPill(text: String, open: Boolean, onPhoto: Boolean, modifier: Modifier = Modifier) {
    Row(
        modifier.clip(CircleShape).background(if (onPhoto) Color.White else if (open) Color(0xFFEAF7EE) else Color(0xFFF1F4F8))
            .padding(horizontal = 10.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(7.dp).clip(CircleShape).background(if (open) Color(0xFF16A34A) else Faint))
        Spacer(Modifier.width(6.dp))
        Text(text, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = if (open) OpenGreen else Muted)
    }
}

/**
 * Today, hour by hour: each block fills blue from the bottom as far as it's booked,
 * so a glance says which part of the day is selling. Hours gone are greyed and a thin
 * line marks now.
 */
@Composable
private fun DayStrip(hours: List<HourFill>, now: Int, len: Int) {
    if (hours.isEmpty()) {
        Box(
            Modifier.fillMaxWidth().height(34.dp).clip(RoundedCornerShape(9.dp)).background(Color(0xFFF1F4F8)),
            contentAlignment = Alignment.Center,
        ) { Text("Nothing on sale today", fontSize = 12.sp, color = Faint) }
        return
    }
    val grow = remember { Animatable(0f) }
    LaunchedEffect(Unit) { grow.animateTo(1f, tween(700, delayMillis = 180, easing = FastOutSlowInEasing)) }
    Canvas(Modifier.fillMaxWidth().height(34.dp)) {
        val n = hours.size
        val gap = (if (n > 30) 1.5f else 3f) * density
        val w = (size.width - gap * (n - 1)) / n
        val r = CornerRadius((w / 3f).coerceAtMost(5f * density))
        hours.forEachIndexed { i, h ->
            val x = i * (w + gap)
            val past = h.start + len <= now
            drawRoundRect(if (past) Color(0xFFEDF0F4) else Color(0xFFE6EDFA), Offset(x, 0f), Size(w, size.height), r)
            if (h.total > 0 && h.booked > 0) {
                val stagger = ((grow.value * (n + 4)) - i * 0.6f).coerceIn(0f, 1f)
                val fh = size.height * (h.booked.toFloat() / h.total) * stagger
                drawRoundRect(
                    if (past) Brush.verticalGradient(listOf(Color(0xFFB9C3D3), Color(0xFFA7B2C4)))
                    else Brush.verticalGradient(listOf(Color(0xFF4D8BFF), Blue)),
                    Offset(x, size.height - fh), Size(w, fh), r,
                )
            }
            if (now >= h.start && now < h.start + len) {
                val nx = x + w * ((now - h.start).toFloat() / len)
                drawLine(BlueDeep, Offset(nx, -2f * density), Offset(nx, size.height + 2f * density), strokeWidth = 2f * density)
            }
        }
    }
    Spacer(Modifier.height(5.dp))
    Row(Modifier.fillMaxWidth()) {
        Text(clock(hours.first().start), fontSize = 11.sp, color = Faint, modifier = Modifier.weight(1f))
        Text(clock(hours.last().start + len), fontSize = 11.sp, color = Faint)
    }
}

@Composable
private fun Fact(value: String, label: String, modifier: Modifier) {
    Column(modifier.padding(horizontal = 10.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1)
        Spacer(Modifier.height(2.dp))
        Text(label, fontSize = 11.sp, color = Muted, maxLines = 2, lineHeight = 14.sp, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }
}

@Composable
private fun FactDivider() {
    Box(Modifier.width(1.dp).height(34.dp).background(Hairline))
}

// ---- Needs attention --------------------------------------------------------

private enum class Fix { Pricing, Support, Hours }

private data class Issue(val title: String, val detail: String, val action: String, val fix: Fix)

private fun shortDay(d: String) = d.take(3)

/** Real gaps only — each one costs bookings today, and each has a way to fix it. */
private fun attentionFor(room: VenueRoom): List<Issue> {
    val out = mutableListOf<Issue>()
    room.slots?.let { slots ->
        val live = slots.filter { it.isOpen }
        if (live.isEmpty()) {
            out += Issue(
                "No slots on sale",
                "Players can't book this venue online until it has slots.",
                "Set hours", Fix.Hours,
            )
        } else if (live.none { it.day.isNullOrBlank() || it.day.equals("Every day", true) }) {
            val covered = live.mapNotNull { s -> WEEK.firstOrNull { it.equals(s.day, true) } }.toSet()
            val missing = WEEK.filterNot { it in covered }
            if (missing.isNotEmpty()) {
                val weekend = missing.toSet() == setOf("Saturday", "Sunday")
                out += Issue(
                    if (weekend) "No slots on Saturday and Sunday" else "No slots on " + missing.joinToString(", ") { shortDay(it) },
                    if (weekend) "Weekends are when most people play. Players see this venue as closed on those days."
                    else "Players see this venue as closed on those days.",
                    "Set hours", Fix.Hours,
                )
            }
        }
    }
    room.courts?.let { courts ->
        val listed = room.v.sports.map { it.trim().lowercase() }.toSet()
        courts.forEach { c ->
            val missing = c.sports.filter { it.isNotBlank() && it.trim().lowercase() !in listed }
            if (missing.isNotEmpty() && listed.isNotEmpty()) {
                val sport = missing.first()
                out += Issue(
                    "${c.name} plays $sport, but the venue isn't listed for it",
                    "Players searching $sport won't find you. The venue is listed for " +
                        room.v.sports.joinToString(", ") + " only.",
                    "Ask Haraan", Fix.Support,
                )
            }
        }
        courts.filter { it.price <= 0 }.forEach { c ->
            out += Issue("${c.name} has no rate", "Its slots sell at ₹0 unless a slot sets its own price.", "Set rate", Fix.Pricing)
        }
    }
    room.details?.let { d ->
        if (d.address.isNullOrBlank()) {
            out += Issue("No address", "Players can't tell where the venue is. Send us the full address.", "Ask Haraan", Fix.Support)
        } else if (d.latitude == null || d.longitude == null) {
            out += Issue("No map pin", "Players can't get directions or find you by distance.", "Ask Haraan", Fix.Support)
        }
    }
    if (room.v.image.isNullOrBlank()) {
        out += Issue("No photo", "Players see a blank card. Send us a few photos of the courts.", "Ask Haraan", Fix.Support)
    }
    return out
}

@Composable
private fun AttentionCard(issues: List<Issue>, onFix: (Fix) -> Unit) {
    Column(Modifier.fillMaxWidth().card()) {
        Row(Modifier.padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Needs attention", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
            Text(
                "${issues.size}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Amber,
                modifier = Modifier.clip(CircleShape).background(AmberTint).padding(horizontal = 9.dp, vertical = 2.dp),
            )
        }
        issues.forEachIndexed { i, issue ->
            if (i > 0) Box(Modifier.padding(start = 44.dp).fillMaxWidth().height(1.dp).background(Hairline))
            Row(
                Modifier.fillMaxWidth().pressableTile(0.dp, pressedScale = 0.99f) { onFix(issue.fix) }
                    .padding(horizontal = 18.dp, vertical = 12.dp),
                verticalAlignment = Alignment.Top,
            ) {
                // A small drawn flag: amber, the one colour on the card that means "act".
                Canvas(Modifier.padding(top = 3.dp).size(14.dp)) {
                    drawCircle(Color(0x29D97706))
                    drawCircle(Color(0xFFD97706), radius = size.minDimension * 0.22f)
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(issue.title, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink, lineHeight = 19.sp)
                    Spacer(Modifier.height(2.dp))
                    Text(issue.detail, fontSize = 12.5.sp, color = Muted, lineHeight = 17.sp)
                }
                Spacer(Modifier.width(10.dp))
                Text(issue.action, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue, modifier = Modifier.padding(top = 1.dp))
            }
        }
        Spacer(Modifier.height(6.dp))
    }
}

// ---- Courts ------------------------------------------------------------------

private fun peakWindow(c: CourtPricing): String? {
    if (!c.peakOn) return null
    val s = com.haraan.partner.ui.pricing.startMinutes(c.peakStart)
    val e = com.haraan.partner.ui.pricing.startMinutes(c.peakEnd)
    val time = if (s != null && e != null) " ${clock(s).removeSuffix(" AM").removeSuffix(" PM")}–${clock(e)}" else ""
    return "peak ₹${c.peakPrice}$time"
}

@Composable
private fun CourtsCard(room: VenueRoom, onOpen: () -> Unit) {
    val courts = room.courts.orEmpty()
    Column(Modifier.fillMaxWidth().card()) {
        Row(Modifier.padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Courts", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
            Text("Today", fontSize = 12.sp, color = Faint)
        }
        courts.forEachIndexed { i, c ->
            if (i > 0) Box(Modifier.padding(start = 78.dp).fillMaxWidth().height(1.dp).background(Hairline))
            val cells = room.today?.slots.orEmpty().flatMap { s -> s.courts.filter { it.courtId == c.id && it.allowed } }
            val booked = cells.count { it.isBooked || it.isHeld }
            val prices = cells.map { it.price }.filter { it > 0 }
            val kind = courtKindFor(c.sports)
            Row(
                Modifier.fillMaxWidth().pressableTile(0.dp, pressedScale = 0.99f, onClick = onOpen)
                    .padding(horizontal = 18.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                MiniCourt(if (kind == CourtKind.Plain && c.sports.isEmpty()) CourtKind.Plain else kind, Modifier.size(width = 44.dp, height = 56.dp))
                Spacer(Modifier.width(16.dp))
                Column(Modifier.weight(1f)) {
                    Text(c.name.replaceFirstChar { it.uppercase() }, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Spacer(Modifier.height(2.dp))
                    Text(
                        c.sports.joinToString(" · ").ifBlank { "Any sport" },
                        fontSize = 12.5.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                    Spacer(Modifier.height(3.dp))
                    val rate = when {
                        prices.isNotEmpty() && prices.min() != prices.max() -> "₹${formatInr(prices.min())}–${formatInr(prices.max())} a slot today"
                        prices.isNotEmpty() -> "₹${formatInr(prices.first())} a slot"
                        c.price > 0 -> "₹${c.price} a slot"
                        else -> "No rate set"
                    }
                    Text(
                        listOfNotNull(rate, peakWindow(c).takeIf { prices.isEmpty() || prices.min() == prices.max() }).joinToString(" · "),
                        fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold,
                        color = if (rate == "No rate set") Amber else BlueDeep, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
                Spacer(Modifier.width(12.dp))
                Column(horizontalAlignment = Alignment.End) {
                    Text(if (cells.isEmpty()) "—" else "$booked/${cells.size}", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
                    Spacer(Modifier.height(5.dp))
                    Box(Modifier.width(54.dp).height(5.dp).clip(CircleShape).background(Color(0xFFE6EDFA))) {
                        if (cells.isNotEmpty() && booked > 0) {
                            Box(Modifier.fillMaxWidth(booked.toFloat() / cells.size).height(5.dp).clip(CircleShape).background(Blue))
                        }
                    }
                }
            }
        }
        Spacer(Modifier.height(6.dp))
    }
}

// ---- Manage ------------------------------------------------------------------

@Composable
private fun ManageCard(
    v: VenueSummary,
    hours: String?,
    onHours: () -> Unit,
    canPricing: Boolean,
    canReports: Boolean,
    onDesk: () -> Unit,
    onPricing: () -> Unit,
    onAnalytics: () -> Unit,
) {
    val context = LocalContext.current
    Column(Modifier.fillMaxWidth().card()) {
        Text("Manage", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.padding(start = 18.dp, top = 16.dp, bottom = 4.dp))
        val rows = buildList {
            add(Triple(Icons.Filled.EventAvailable, "Day desk" to "Book walk-ins, collect, mark a day closed", onDesk))
            if (canPricing) add(Triple(Icons.Filled.Schedule, "Opening hours" to (hours ?: "Set when players can book"), onHours))
            if (canPricing) add(Triple(Icons.Filled.CurrencyRupee, "Slots & pricing" to "Times, days, and each court's price", onPricing))
            if (canReports) add(Triple(Icons.Filled.BarChart, "Analytics" to "Bookings and earnings over time", onAnalytics))
            add(Triple(Icons.AutoMirrored.Filled.OpenInNew, "See what players see" to "Your venue page on haraan.app", {
                runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(publicUrl(v)))) }
                Unit
            }))
        }
        rows.forEachIndexed { i, (icon, text, go) ->
            if (i > 0) Box(Modifier.padding(start = 68.dp).fillMaxWidth().height(1.dp).background(Hairline))
            ManageRow(icon, text.first, text.second, go)
        }
        Spacer(Modifier.height(6.dp))
    }
}

@Composable
private fun ManageRow(icon: ImageVector, title: String, sub: String, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().pressableTile(0.dp, pressedScale = 0.99f, onClick = onClick)
            .padding(horizontal = 18.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(36.dp).clip(RoundedCornerShape(11.dp)).background(BlueTint), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = Blue, modifier = Modifier.size(19.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Ink)
            Text(sub, fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        Icon(Icons.Filled.ChevronRight, null, tint = Faint, modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun AnotherVenue(onSupport: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().pressableTile(16.dp, pressedScale = 0.98f, onClick = onSupport)
            .clip(RoundedCornerShape(16.dp)).border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(horizontal = 18.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.SupportAgent, null, tint = Muted, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text("Have another venue?", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink)
            Text("Haraan lists it for you. Tell us about it.", fontSize = 12.sp, color = Muted)
        }
        Text("Ask Haraan", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue)
    }
}

@Composable
private fun NoVenue(onSupport: () -> Unit) {
    Column(Modifier.fillMaxWidth().card().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        MiniCourt(CourtKind.Plain, Modifier.size(width = 52.dp, height = 68.dp))
        Spacer(Modifier.height(14.dp))
        Text("No venue on your account yet", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text("Haraan sets up your venue and courts. Message us and we'll get it live.", fontSize = 13.sp, color = Muted, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
        Spacer(Modifier.height(16.dp))
        Box(
            Modifier.pressableTile(14.dp, onClick = onSupport).clip(RoundedCornerShape(14.dp)).background(Blue)
                .padding(horizontal = 22.dp, vertical = 12.dp),
        ) { Text("Ask Haraan", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White) }
    }
}

private fun publicUrl(v: VenueSummary) = "https://haraan.app/gamehub/${v.id}"

private fun shareVenue(context: android.content.Context, v: VenueSummary) {
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, "Book a court at ${v.name} on Haraan: ${publicUrl(v)}")
    }
    runCatching { context.startActivity(Intent.createChooser(send, "Share venue")) }
}
