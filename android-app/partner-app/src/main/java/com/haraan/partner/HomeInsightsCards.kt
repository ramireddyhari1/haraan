package com.haraan.partner

import android.content.Intent
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentWidth
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.GridView
import androidx.compose.material.icons.filled.Hub
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.graphicsLayer
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.components.rememberMoneyMotion
import kotlinx.coroutines.launch
import kotlin.math.abs
import kotlin.math.roundToInt

// The charts under Home: the week's bars, where bookings come from, tomorrow's open
// court-hours and the busy-hours grid. Every figure is from /api/partner/insights —
// live bookings in court-hours, placed by the same rule as the desk grid.
// Palette mirrors PartnerApp's partner palette.

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Accent = Color(0xFF2F6BFF)
private val AccentDeep = Color(0xFF1E50E6)
private val AccentSoft = Color(0xFFB9CEFF)
private val AccentMist = Color(0xFFE3ECFF)
private val Ghost = Color(0xFFE7EBF2)
private val Track = Color(0xFFF1F4F9)
private val WalkInNavy = Color(0xFF0B1C46)
private val WhatsAppGreen = Color(0xFF1FAF5B)
private val Up = Color(0xFF15803D)
private val Down = Color(0xFFB45309)

/** The shown insights and the week pager behind them. */
@Stable
internal class InsightsState(initial: HomeInsights) {
    var value by mutableStateOf(initial)
    var loading by mutableStateOf(false)
}

@Composable
internal fun rememberInsightsState(initial: HomeInsights, load: suspend (week: String) -> HomeInsights): Pair<InsightsState, (String) -> Unit> {
    val state = remember(initial) { InsightsState(initial) }
    val scope = rememberCoroutineScope()
    val go: (String) -> Unit = { week ->
        if (!state.loading) {
            state.loading = true
            scope.launch {
                // A failed page keeps the week on screen; the arrows stay usable.
                runCatching { load(week) }.onSuccess { state.value = it }
                state.loading = false
            }
        }
    }
    return state to go
}

private fun rupees(v: Double) = "₹" + formatInr(kotlin.math.round(v))

private fun hrs(v: Double): String {
    val r = (v * 10).roundToInt() / 10.0
    return if (r == r.toLong().toDouble()) "${r.toLong()}" else "$r"
}

/**
 * Home's insight sections, in the order a partner acts on them: the week's money,
 * where it came from, what's still open tomorrow, and which hours usually sit empty.
 */
internal fun LazyListScope.insightItems(
    state: InsightsState,
    onWeek: (String) -> Unit,
    memoryKey: String,
    onPricing: (() -> Unit)?,
) {
    val pad = Modifier.padding(horizontal = 16.dp)
    val insights = state.value
    // The encouraging long view leads: what Haraan brought, then the next milestone.
    item(key = "ins-haraan") { Rise(8) { HaraanBroughtCard(insights.haraan, "$memoryKey.haraan", pad) } }
    item(key = "ins-milestones") { Rise(8) { MilestonesCard(insights.milestones, memoryKey, pad) } }
    item(key = "ins-week-head") { Rise(8) { Box(pad) { HomeSectionHeader(Icons.Filled.BarChart, "Day by day") } } }
    item(key = "ins-week") { Rise(9) { WeekBarsCard(state, onWeek, memoryKey, pad) } }
    item(key = "ins-channel-head") { Rise(10) { Box(pad) { HomeSectionHeader(Icons.Filled.Hub, "Where bookings come from") } } }
    item(key = "ins-channel") { Rise(10) { ChannelSplitCard(state, pad) } }
    if (insights.growth.points.size >= 2) {
        item(key = "ins-growth-head") { Rise(11) { Box(pad) { HomeSectionHeader(Icons.AutoMirrored.Filled.TrendingUp, "Games played so far") } } }
        item(key = "ins-growth") { Rise(11) { GrowthCard(insights.growth, pad) } }
    }
    val tomorrow = insights.tomorrow
    if (tomorrow.venues.isNotEmpty()) {
        item(key = "ins-tomorrow-head") { Rise(11) { Box(pad) { HomeSectionHeader(Icons.Filled.EventAvailable, "Tomorrow · ${tomorrow.label}") } } }
        item(key = "ins-tomorrow") { Rise(11) { TomorrowCard(tomorrow, pad) } }
    }
    if (insights.heatmap.hours.isNotEmpty()) {
        item(key = "ins-heat-head") { Rise(12) { Box(pad) { HomeSectionHeader(Icons.Filled.GridView, "Busy hours") } } }
        item(key = "ins-heat") { Rise(12) { BusyHoursCard(insights.heatmap, onPricing, pad) } }
    }
}

// ---- This week ------------------------------------------------------------

/**
 * Seven bars, Monday to Sunday, with last week's figure standing behind each one in
 * grey. Tap a bar for its numbers; swipe or use the arrows for other weeks.
 */
@Composable
internal fun WeekBarsCard(state: InsightsState, onWeek: (String) -> Unit, memoryKey: String, modifier: Modifier = Modifier) {
    val view = LocalView.current
    val week = state.value.week
    var hoursMode by remember { mutableStateOf(false) }
    var selected by remember(week.start) { mutableIntStateOf(week.days.indexOfFirst { it.today }) }

    val money = rememberMoneyMotion(week.revenue, "$memoryKey.${week.start}")
    val delta = if (week.lastRevenue > 0) (week.revenue - week.lastRevenue) / week.lastRevenue else null

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(week.label, fontSize = 12.5.sp, color = Muted, fontWeight = FontWeight.SemiBold)
                Text(
                    if (hoursMode) hoursLabel(week.bookedHours) else rupees(money.shown),
                    fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.6).sp,
                )
            }
            WeekArrow(Icons.Filled.ChevronLeft, "Previous week", enabled = !state.loading) { onWeek(week.prev) }
            WeekArrow(Icons.Filled.ChevronRight, "Next week", enabled = !state.loading && week.next != null) { week.next?.let(onWeek) }
        }
        Spacer(Modifier.height(2.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                if (week.totalHours > 0) "${hrs(week.bookedHours)} of ${hrs(week.totalHours)} court-hours booked" else "No bookable hours this week",
                fontSize = 12.5.sp, color = Muted, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            if (delta != null && !hoursMode) {
                val pct = (abs(delta) * 100).roundToInt()
                Text(
                    if (pct == 0) "Same as last week" else (if (delta > 0) "▲ " else "▼ ") + "$pct% vs last week",
                    fontSize = 12.sp, fontWeight = FontWeight.Bold, color = when { pct == 0 -> Muted; delta > 0 -> Up; else -> Down },
                )
            }
        }

        Spacer(Modifier.height(14.dp))
        WeekBars(
            days = week.days,
            hoursMode = hoursMode,
            selected = selected,
            onSelect = { selected = it; Haptics.tick(view) },
            onSwipe = { forward ->
                val target = if (forward) week.next else week.prev
                if (target != null && !state.loading) { Haptics.tick(view); onWeek(target) }
            },
            animateKey = week.start + hoursMode,
            loading = state.loading,
        )

        Spacer(Modifier.height(12.dp))
        val day = week.days.getOrNull(selected)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                day?.let {
                    val main = if (hoursMode) "${hrs(it.bookedHours)} of ${hrs(it.totalHours)} hrs" else rupees(it.revenue) + " · ${hrs(it.bookedHours)} of ${hrs(it.totalHours)} hrs"
                    val last = if (hoursMode) hoursLabel(it.lastBookedHours) else rupees(it.lastRevenue)
                    "${it.label} ${it.day} · $main · last week $last"
                } ?: "Tap a day to see its numbers",
                fontSize = 12.5.sp, color = Ink, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f),
                maxLines = 2,
            )
            Spacer(Modifier.width(8.dp))
            ModeSwitch(hoursMode) { hoursMode = it; Haptics.tick(view) }
        }
    }
}

@Composable
private fun WeekArrow(icon: androidx.compose.ui.graphics.vector.ImageVector, desc: String, enabled: Boolean, onClick: () -> Unit) {
    Box(
        Modifier.size(40.dp).clip(RoundedCornerShape(12.dp))
            .pressableTile(12.dp, enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = desc, tint = if (enabled) Ink else Ghost, modifier = Modifier.size(22.dp))
    }
}

/** ₹ | Hrs — which figure the bars measure. */
@Composable
private fun ModeSwitch(hours: Boolean, second: String = "Hrs", onChange: (Boolean) -> Unit) {
    Row(Modifier.clip(RoundedCornerShape(999.dp)).background(Track).padding(3.dp)) {
        listOf(false to "₹", true to second).forEach { (value, label) ->
            val on = value == hours
            Text(
                label,
                fontSize = 12.sp, fontWeight = FontWeight.Bold,
                color = if (on) Color.White else Muted,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(if (on) Ink else Color.Transparent)
                    .clickable { if (!on) onChange(value) }
                    .padding(horizontal = 12.dp, vertical = 6.dp),
            )
        }
    }
}

@Composable
private fun WeekBars(
    days: List<InsightDay>,
    hoursMode: Boolean,
    selected: Int,
    onSelect: (Int) -> Unit,
    onSwipe: (forward: Boolean) -> Unit,
    animateKey: String,
    loading: Boolean,
) {
    val value = { d: InsightDay -> if (hoursMode) d.bookedHours else d.revenue }
    val last = { d: InsightDay -> if (hoursMode) d.lastBookedHours else d.lastRevenue }
    val top = days.maxOfOrNull { maxOf(value(it), last(it)) }?.takeIf { it > 0 } ?: 1.0

    // Bars grow from the floor each time the week or the measure changes.
    val grow = remember(animateKey) { Animatable(0f) }
    LaunchedEffect(animateKey) { grow.animateTo(1f, tween(520, easing = FastOutSlowInEasing)) }
    val dim by animateFloatAsState(if (loading) 0.45f else 1f, tween(180), label = "week-dim")

    var drag by remember { mutableStateOf(0f) }
    Row(
        Modifier
            .fillMaxWidth()
            .height(150.dp)
            .pointerInput(Unit) {
                detectHorizontalDragGestures(
                    onDragStart = { drag = 0f },
                    onDragEnd = {
                        // Right swipe goes back in time, like turning a page.
                        if (abs(drag) > 60.dp.toPx()) onSwipe(drag < 0)
                        drag = 0f
                    },
                    onHorizontalDrag = { _, dx -> drag += dx },
                )
            },
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        days.forEachIndexed { i, d ->
            val isSel = i == selected
            val fill = when {
                d.today -> Accent
                isSel -> AccentDeep
                d.future -> AccentMist
                else -> AccentSoft
            }
            Column(
                Modifier.weight(1f).fillMaxHeight()
                    .clip(RoundedCornerShape(10.dp))
                    .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) { onSelect(i) }
                    .semantics {
                        contentDescription = "${d.label} ${d.day}: " +
                            (if (hoursMode) "${hrs(d.bookedHours)} hours" else rupees(d.revenue)) +
                            ", last week " + (if (hoursMode) "${hrs(d.lastBookedHours)} hours" else rupees(d.lastRevenue))
                    },
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Box(Modifier.weight(1f).fillMaxWidth(), contentAlignment = Alignment.BottomCenter) {
                    // Last week behind, a touch wider, so this week reads against it.
                    val ghost = (last(d) / top).toFloat() * grow.value
                    if (ghost > 0f) {
                        Box(Modifier.fillMaxWidth().fillMaxHeight(ghost.coerceIn(0.02f, 1f)).clip(RoundedCornerShape(8.dp)).background(Ghost))
                    }
                    val now = (value(d) / top).toFloat() * grow.value
                    Box(
                        Modifier.fillMaxWidth(0.62f)
                            .fillMaxHeight(if (value(d) > 0) now.coerceIn(0.03f, 1f) else 0.015f)
                            .clip(RoundedCornerShape(topStart = 7.dp, topEnd = 7.dp, bottomStart = 3.dp, bottomEnd = 3.dp))
                            .background(fill.copy(alpha = fill.alpha * dim)),
                    )
                }
                Spacer(Modifier.height(6.dp))
                Text(
                    d.label.take(1),
                    fontSize = 11.5.sp,
                    fontWeight = if (isSel || d.today) FontWeight.ExtraBold else FontWeight.SemiBold,
                    color = if (isSel || d.today) Ink else Muted,
                )
                Text("${d.day}", fontSize = 10.5.sp, color = Muted)
            }
        }
    }
}

// ---- Where bookings come from --------------------------------------------

/**
 * One bar split into walk-ins, app and WhatsApp, for today or the shown week, with the
 * share that came through Haraan — the number that tells a venue the app earns its keep.
 */
@Composable
internal fun ChannelSplitCard(state: InsightsState, modifier: Modifier = Modifier) {
    val view = LocalView.current
    val insights = state.value
    val isCurrentWeek = insights.week.next == null
    // "Today" only means something on the current week.
    var todayMode by remember(isCurrentWeek) { mutableStateOf(false) }
    val split = if (todayMode && isCurrentWeek) insights.channelsToday else insights.channelsWeek
    val total = split.amount

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                if (total > 0) "${plural(split.count, "booking")} · ${rupees(total)}" else "No bookings yet",
                fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink, modifier = Modifier.weight(1f),
            )
            if (isCurrentWeek) {
                Row(Modifier.clip(RoundedCornerShape(999.dp)).background(Track).padding(3.dp)) {
                    listOf(true to "Today", false to "Week").forEach { (value, label) ->
                        val on = value == todayMode
                        Text(
                            label, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                            color = if (on) Color.White else Muted,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp))
                                .background(if (on) Ink else Color.Transparent)
                                .clickable { if (!on) { todayMode = value; Haptics.tick(view) } }
                                .padding(horizontal = 12.dp, vertical = 6.dp),
                        )
                    }
                }
            }
        }
        Spacer(Modifier.height(12.dp))

        val parts = listOf(
            Triple("Walk-in", split.walkIn, WalkInNavy),
            Triple("App", split.app, Accent),
            Triple("WhatsApp", split.whatsapp, WhatsAppGreen),
        )
        // The bar itself: segments slide to their widths when the period flips.
        Row(Modifier.fillMaxWidth().height(14.dp).clip(RoundedCornerShape(999.dp)).background(Track)) {
            if (total > 0) {
                parts.forEach { (label, share, color) ->
                    val w by animateFloatAsState((share.amount / total).toFloat(), tween(420, easing = FastOutSlowInEasing), label = "ch-$label")
                    if (w > 0.001f) Box(Modifier.weight(w).fillMaxHeight().background(color))
                }
            }
        }
        Spacer(Modifier.height(12.dp))
        parts.forEach { (label, share, color) ->
            Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(10.dp).clip(RoundedCornerShape(3.dp)).background(color))
                Spacer(Modifier.width(10.dp))
                Text(label, fontSize = 13.5.sp, color = Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                Text(
                    if (share.count == 0) "—" else hoursLabel(share.hours),
                    fontSize = 12.5.sp, color = Muted,
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    if (share.count == 0) "" else rupees(share.amount),
                    fontSize = 13.5.sp, color = Ink, fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.End, modifier = Modifier.width(78.dp),
                )
            }
        }

        insightLine(split, if (todayMode && isCurrentWeek) null else insights.channelsLastWeek, todayMode && isCurrentWeek)?.let {
            Spacer(Modifier.height(8.dp))
            Text(it, fontSize = 12.5.sp, color = AccentDeep, fontWeight = FontWeight.SemiBold)
        }
    }
}

/** "Haraan brought 58% of this week's money, up from 41%." Nothing when there's no money. */
private fun insightLine(now: ChannelSplit, before: ChannelSplit?, today: Boolean): String? {
    val share = now.onlineShare ?: return null
    val pct = (share * 100).roundToInt()
    val period = if (today) "today's" else "this week's"
    val prev = before?.onlineShare?.let { (it * 100).roundToInt() }
    return when {
        prev == null || prev == pct -> "Haraan brought $pct% of $period money"
        pct > prev -> "Haraan brought $pct% of $period money, up from $prev%"
        else -> "Haraan brought $pct% of $period money, down from $prev%"
    }
}

// ---- Tomorrow --------------------------------------------------------------

/** What is still unsold tomorrow, in runs the partner can send out on WhatsApp. */
@Composable
internal fun TomorrowCard(tomorrow: TomorrowOpen, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val view = LocalView.current
    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        tomorrow.venues.forEach { v ->
            Column(Modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
                if (tomorrow.venues.size > 1) {
                    Text(v.name, fontSize = 12.5.sp, color = Muted, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Spacer(Modifier.height(2.dp))
                }
                when {
                    v.closed -> Text("Closed tomorrow", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
                    v.totalHours <= 0 -> Text("No slots set for ${tomorrow.label}", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
                    v.openHours <= 0 -> Text("Fully booked for ${tomorrow.label}", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Up)
                    else -> {
                        Row(verticalAlignment = Alignment.Bottom) {
                            Text(hrs(v.openHours), fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.5).sp)
                            Text(
                                " of ${hrs(v.totalHours)} court-hours still open",
                                fontSize = 13.sp, color = Muted, modifier = Modifier.padding(bottom = 4.dp),
                            )
                        }
                        Spacer(Modifier.height(8.dp))
                        // How much of tomorrow is sold, at a glance.
                        val sold = (1 - v.openHours / v.totalHours).toFloat().coerceIn(0f, 1f)
                        Box(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(999.dp)).background(Track)) {
                            Box(Modifier.fillMaxWidth(sold).fillMaxHeight().clip(RoundedCornerShape(999.dp)).background(Accent))
                        }
                        Spacer(Modifier.height(10.dp))
                        v.windows.forEach { (label, free) ->
                            Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                                Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(Accent))
                                Spacer(Modifier.width(10.dp))
                                Text(label, fontSize = 13.5.sp, color = Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                Text(if (free == 1) "1 court free" else "$free courts free", fontSize = 12.5.sp, color = Muted)
                            }
                        }
                        Spacer(Modifier.height(10.dp))
                        val interaction = remember { MutableInteractionSource() }
                        Row(
                            Modifier.fillMaxWidth().height(48.dp)
                                .pressScale(interaction)
                                .clip(RoundedCornerShape(14.dp))
                                .background(Accent)
                                .pressShade(interaction)
                                .clickable(interactionSource = interaction, indication = null) {
                                    Haptics.confirm(view)
                                    shareOpenSlots(context, v)
                                },
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(Icons.Filled.Share, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Share on WhatsApp", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 14.5.sp)
                        }
                    }
                }
            }
        }
    }
}

/** WhatsApp when it's installed (it's where turf regulars are), else the system share sheet. */
private fun shareOpenSlots(context: android.content.Context, v: TomorrowVenue) {
    val text = v.shareText.ifBlank {
        "Courts open tomorrow at ${v.name}: " + v.windows.joinToString(", ") { it.first } + ". Book on Haraan: " + v.shareUrl
    }
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, text)
    }
    val whatsapp = listOf("com.whatsapp", "com.whatsapp.w4b").firstOrNull { pkg ->
        runCatching { context.packageManager.getPackageInfo(pkg, 0) }.isSuccess
    }
    try {
        if (whatsapp != null) {
            context.startActivity(Intent(send).setPackage(whatsapp))
        } else {
            context.startActivity(Intent.createChooser(send, "Share open slots"))
        }
    } catch (_: Exception) {
        context.startActivity(Intent.createChooser(send, "Share open slots"))
    }
}

// ---- Busy hours ------------------------------------------------------------

/**
 * Weekday × hour, darker where courts usually fill, over the last few weeks. Under it,
 * the quiet stretches by name and the one lever that fills them: price.
 */
@Composable
internal fun BusyHoursCard(heat: BusyHours, onPricing: (() -> Unit)?, modifier: Modifier = Modifier) {
    val view = LocalView.current
    var picked by remember(heat) { mutableStateOf<Pair<Int, Int>?>(null) }

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Text(
            "Last ${heat.weeks} weeks · darker is fuller",
            fontSize = 12.5.sp, color = Muted, fontWeight = FontWeight.SemiBold,
        )
        Spacer(Modifier.height(12.dp))

        BoxWithConstraints(Modifier.fillMaxWidth()) {
            val labelW = 34.dp
            val gap = 3.dp
            val count = heat.hours.size.coerceAtLeast(1)
            // Fit the day on screen when it can; a long day scrolls instead of shrinking to specks.
            val fitted = (maxWidth - labelW - gap * (count - 1)) / count
            val cell = if (fitted < 16.dp) 16.dp else if (fitted > 30.dp) 30.dp else fitted
            Column {
                Row(Modifier.horizontalScroll(rememberScrollState())) {
                    Column(verticalArrangement = Arrangement.spacedBy(gap)) {
                        Spacer(Modifier.height(16.dp))
                        heat.rows.forEach { (label, _) ->
                            Box(Modifier.width(labelW).height(cell), contentAlignment = Alignment.CenterStart) {
                                Text(label.take(3), fontSize = 11.sp, color = Muted, fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                    Column(verticalArrangement = Arrangement.spacedBy(gap)) {
                        Row(horizontalArrangement = Arrangement.spacedBy(gap), modifier = Modifier.height(16.dp)) {
                            heat.hours.forEachIndexed { i, h ->
                                Box(Modifier.width(cell), contentAlignment = Alignment.CenterStart) {
                                    // Every third hour is labelled; the rest would collide.
                                    // No label in the last two cells: it would run off the card.
                                    if (i % 3 == 0 && i < heat.hours.size - 2) {
                                        Text(
                                            h, fontSize = 9.5.sp, color = Muted, maxLines = 1, softWrap = false,
                                            modifier = Modifier.wrapContentWidth(Alignment.Start, unbounded = true),
                                        )
                                    }
                                }
                            }
                        }
                        heat.rows.forEachIndexed { r, (_, cells) ->
                            Row(horizontalArrangement = Arrangement.spacedBy(gap)) {
                                cells.forEachIndexed { c, fill ->
                                    val isPicked = picked == (r to c)
                                    Box(
                                        Modifier.size(cell).clip(RoundedCornerShape(5.dp))
                                            .background(
                                                if (fill == null) Color.Transparent
                                                else lerp(Track, AccentDeep, fill.coerceIn(0f, 1f)),
                                            )
                                            .then(
                                                if (fill != null) Modifier.clickable {
                                                    picked = if (isPicked) null else r to c
                                                    Haptics.tick(view)
                                                } else Modifier,
                                            )
                                            .then(if (isPicked) Modifier.background(Ink.copy(alpha = 0.25f)) else Modifier),
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }

        Spacer(Modifier.height(10.dp))
        val pickedLine = picked?.let { (r, c) ->
            val row = heat.rows.getOrNull(r)
            val fill = row?.second?.getOrNull(c)
            if (row != null && fill != null) "${row.first} ${heat.hours.getOrNull(c).orEmpty()} · ${(fill * 100).roundToInt()}% full on average" else null
        }
        Text(
            pickedLine ?: "Tap a square to see how full that hour usually is",
            fontSize = 12.5.sp, color = if (pickedLine != null) Ink else Muted, fontWeight = FontWeight.Medium,
        )

        if (!heat.ready) {
            Spacer(Modifier.height(8.dp))
            Text("Quiet hours are named once a few more bookings are in.", fontSize = 12.5.sp, color = Muted)
        } else if (heat.quiet.isNotEmpty()) {
            Spacer(Modifier.height(14.dp))
            Text("Your quiet hours", fontSize = 13.5.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
            Spacer(Modifier.height(6.dp))
            heat.quiet.forEach { q ->
                Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(Down))
                    Spacer(Modifier.width(10.dp))
                    Text("${q.days} · ${q.hours}", fontSize = 13.5.sp, color = Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                    Text("${q.fill}% full", fontSize = 12.5.sp, color = Muted)
                }
            }
            if (onPricing != null) {
                Spacer(Modifier.height(10.dp))
                val interaction = remember { MutableInteractionSource() }
                Row(
                    Modifier.fillMaxWidth().height(48.dp)
                        .pressScale(interaction)
                        .clip(RoundedCornerShape(14.dp))
                        .background(AccentMist)
                        .pressShade(interaction)
                        .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onPricing() },
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("Open pricing", color = AccentDeep, fontWeight = FontWeight.Bold, fontSize = 14.5.sp)
                    Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = AccentDeep, modifier = Modifier.size(18.dp))
                }
                Spacer(Modifier.height(6.dp))
                Text(
                    "A lower price for these hours is the quickest way to fill them.",
                    fontSize = 12.sp, color = Muted, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}


// ---- Through Haraan --------------------------------------------------------

/**
 * What Haraan brought this venue: the online bookings and money that didn't walk in off
 * the street, and the real players behind them. The number that says the app earns its keep.
 */
@Composable
internal fun HaraanBroughtCard(h: HaraanBrought, memoryKey: String, modifier: Modifier = Modifier) {
    val money = rememberMoneyMotion(h.allTime.amount, memoryKey)
    Column(
        modifier.fillMaxWidth()
            .premiumSurface()
            .background(Brush.linearGradient(listOf(Color(0xFFEFF4FF), Color.White)))
            .padding(16.dp),
    ) {
        Text("Through Haraan", fontSize = 12.5.sp, color = AccentDeep, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(4.dp))
        if (h.allTime.count == 0) {
            Text("Your first online booking is next", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
            Spacer(Modifier.height(4.dp))
            Text(
                "Players near you can find and book your courts on Haraan. Share your venue link to bring them in.",
                fontSize = 13.sp, color = Muted,
            )
            return@Column
        }
        Text(
            rupees(money.shown),
            fontSize = 30.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.8).sp,
        )
        Text(
            "from ${h.allTime.count} online " + (if (h.allTime.count == 1) "booking" else "bookings") + " so far",
            fontSize = 13.sp, color = Muted,
        )
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (h.players > 0) Fact(if (h.players == 1) "1 player found you" else "${h.players} players found you")
            h.share?.takeIf { it > 0 }?.let { Fact("${(it * 100).roundToInt()}% of your bookings") }
        }
        if (h.thisMonth.count > 0 || h.lastMonth.count > 0) {
            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Ghost))
            Spacer(Modifier.height(10.dp))
            val up = h.lastMonth.count > 0 && h.thisMonth.count > h.lastMonth.count
            Text(
                "This month: ${h.thisMonth.count} online · ${rupees(h.thisMonth.amount)}" +
                    when {
                        up -> "  ▲ up from ${h.lastMonth.count}"
                        h.lastMonth.count > 0 -> "  · last month ${h.lastMonth.count}"
                        else -> ""
                    },
                fontSize = 12.5.sp, color = if (up) Up else Ink, fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun Fact(text: String) {
    Text(
        text, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AccentDeep,
        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(AccentMist)
            .padding(horizontal = 10.dp, vertical = 5.dp),
    )
}

// ---- Milestones ------------------------------------------------------------

/**
 * The next booking milestone as a bar that's already moving, and the ones already
 * reached with the day each was crossed. A milestone the partner hasn't seen yet
 * arrives with a buzz and a pop, once.
 */
@Composable
internal fun MilestonesCard(m: Milestones, memoryKey: String, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val view = LocalView.current
    val prefs = remember { context.getSharedPreferences("haraan_partner_milestones", android.content.Context.MODE_PRIVATE) }
    val newest = m.reached.maxOfOrNull { it.count } ?: 0
    // Read once: the pop plays for milestones crossed since the partner last looked.
    val seen = remember(memoryKey) { runCatching { prefs.getInt(memoryKey, -1) }.getOrDefault(-1) }
    val fresh = seen in 0 until newest
    LaunchedEffect(memoryKey, newest) {
        if (fresh) Haptics.confirm(view)
        runCatching { prefs.edit().putInt(memoryKey, newest).apply() }
    }

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.EmojiEvents, contentDescription = null, tint = Color(0xFFB45309), modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(
                if (m.total == 1) "1 booking so far" else "${m.total} bookings so far",
                fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink,
            )
        }
        m.next?.let { next ->
            Spacer(Modifier.height(12.dp))
            val grow = remember(next.count) { Animatable(0f) }
            LaunchedEffect(next.count, next.progress) {
                grow.animateTo(next.progress.coerceIn(0f, 1f), tween(900, easing = FastOutSlowInEasing))
            }
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    if (next.remaining == 1) "1 more booking to reach ${next.count}" else "${next.remaining} more bookings to reach ${next.count}",
                    fontSize = 13.sp, color = Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f),
                )
                Text("${m.total} / ${next.count}", fontSize = 12.sp, color = Muted)
            }
            Spacer(Modifier.height(8.dp))
            Box(Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(999.dp)).background(Track)) {
                Box(
                    Modifier.fillMaxWidth(grow.value.coerceAtLeast(0.03f)).fillMaxHeight()
                        .clip(RoundedCornerShape(999.dp))
                        .background(Brush.horizontalGradient(listOf(Accent, AccentDeep))),
                )
            }
        }
        val chips = buildList {
            m.reached.sortedByDescending { it.count }.take(3).forEach { add(Triple(it.label, it.date, it.count == newest && fresh)) }
            m.firstOnline?.let { add(Triple("First online booking", it, false)) }
        }
        if (chips.isNotEmpty()) {
            Spacer(Modifier.height(14.dp))
            chips.forEach { (label, date, isNew) -> ReachedRow(label, date, isNew) }
        }
    }
}

@Composable
private fun ReachedRow(label: String, date: String, isNew: Boolean) {
    val pop = remember { Animatable(if (isNew) 0.6f else 1f) }
    LaunchedEffect(isNew) {
        if (isNew) pop.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMediumLow))
    }
    Row(
        Modifier.fillMaxWidth().padding(vertical = 4.dp).graphicsLayer { scaleX = pop.value; scaleY = pop.value },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Up, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(10.dp))
        Text(label, fontSize = 13.5.sp, color = Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        if (isNew) {
            Text(
                "NEW", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = Color.White, letterSpacing = 0.6.sp,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Up).padding(horizontal = 7.dp, vertical = 2.dp),
            )
            Spacer(Modifier.width(8.dp))
        }
        Text(date, fontSize = 12.sp, color = Muted)
    }
}

// ---- Since you joined ------------------------------------------------------

/**
 * Running total since the first booking: a line that only climbs, drawn in on load.
 * Drag along it to read any week or month back; each step ticks under the thumb.
 */
@Composable
internal fun GrowthCard(g: Growth, modifier: Modifier = Modifier) {
    val view = LocalView.current
    var bookingsMode by remember { mutableStateOf(false) }
    var picked by remember(g) { mutableStateOf<Int?>(null) }
    // A zero before the first game: even a young venue's line climbs from the floor.
    val values = listOf(0f) + g.points.map { if (bookingsMode) it.bookings.toFloat() else it.revenue.toFloat() }
    val top = values.maxOrNull()?.takeIf { it > 0f } ?: 1f
    val draw = remember(g, bookingsMode) { Animatable(0f) }
    LaunchedEffect(g, bookingsMode) { draw.animateTo(1f, tween(900, easing = FastOutSlowInEasing)) }

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(g.since?.let { "Since your first game · $it" } ?: "So far", fontSize = 12.5.sp, color = Muted, fontWeight = FontWeight.SemiBold)
                Text(
                    if (bookingsMode) plural(g.bookings, "booking") else rupees(g.revenue),
                    fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.5).sp,
                )
            }
            ModeSwitch(bookingsMode, second = "Bookings") { bookingsMode = it; picked = null; Haptics.tick(view) }
        }
        Spacer(Modifier.height(6.dp))
        val p = picked?.let { g.points.getOrNull(it - 1) }
        Text(
            p?.let { "${if (g.unit == "month") "By end of" else "By week of"} ${it.label} · ${rupees(it.revenue)} · ${plural(it.bookings, "booking")}" }
                ?: if (picked == 0) "Before your first game" else "Drag along the line to look back",
            fontSize = 12.5.sp, color = if (p != null) Ink else Muted, fontWeight = FontWeight.Medium,
        )
        Spacer(Modifier.height(10.dp))

        val stepOf = { x: Float, width: Float ->
            if (values.size < 2) 0 else ((x / width) * (values.size - 1)).roundToInt().coerceIn(0, values.size - 1)
        }
        Canvas(
            Modifier.fillMaxWidth().height(130.dp)
                .pointerInput(values.size) {
                    detectTapGestures { o ->
                        val i = stepOf(o.x, size.width.toFloat())
                        picked = if (picked == i) null else i
                        Haptics.tick(view)
                    }
                }
                .pointerInput(values.size) {
                    // Sideways only: an up/down swipe over the chart must still scroll the page.
                    detectHorizontalDragGestures { change, _ ->
                        val i = stepOf(change.position.x, size.width.toFloat())
                        if (i != picked) { picked = i; Haptics.tick(view) }
                    }
                },
        ) {
            if (values.size < 2) return@Canvas
            val w = size.width
            val h = size.height
            val pad = 6.dp.toPx()
            val pts = values.mapIndexed { i, v ->
                Offset(w * i / (values.size - 1), pad + (h - 2 * pad) * (1f - v / top))
            }
            val line = Path().apply {
                moveTo(pts[0].x, pts[0].y)
                for (i in 1 until pts.size) {
                    // Soft corners: a midpoint curve reads as growth, not as a spreadsheet.
                    val a = pts[i - 1]
                    val b = pts[i]
                    val mx = (a.x + b.x) / 2
                    cubicTo(mx, a.y, mx, b.y, b.x, b.y)
                }
            }
            val area = Path().apply {
                addPath(line)
                lineTo(pts.last().x, h)
                lineTo(pts.first().x, h)
                close()
            }
            clipRect(right = w * draw.value) {
                drawPath(area, Brush.verticalGradient(listOf(Accent.copy(alpha = 0.22f), Accent.copy(alpha = 0f))))
                drawPath(line, Accent, style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round))
            }
            if (draw.value > 0.98f) {
                drawCircle(Color.White, 6.dp.toPx(), pts.last())
                drawCircle(Accent, 4.dp.toPx(), pts.last())
            }
            picked?.let { i ->
                val at = pts[i]
                drawLine(Ink.copy(alpha = 0.18f), Offset(at.x, 0f), Offset(at.x, h), strokeWidth = 1.dp.toPx())
                drawCircle(Color.White, 6.dp.toPx(), at)
                drawCircle(AccentDeep, 4.dp.toPx(), at)
            }
        }
        Spacer(Modifier.height(6.dp))
        Row {
            Text("Start", fontSize = 11.sp, color = Muted, modifier = Modifier.weight(1f))
            Text("Now", fontSize = 11.sp, color = Muted)
        }
    }
}

private fun plural(n: Int, word: String) = if (n == 1) "1 $word" else "$n ${word}s"

private fun hoursLabel(v: Double) = hrs(v) + if (hrs(v) == "1") " hr" else " hrs"
