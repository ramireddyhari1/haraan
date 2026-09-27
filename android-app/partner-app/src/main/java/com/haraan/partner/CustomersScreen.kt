package com.haraan.partner

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlin.math.roundToInt

// New vs returning customers: a card on Home whose two halves open the people behind
// each number. Customers are recognised by phone, so a walk-in who later books in the
// app is one person, not two.

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Accent = Color(0xFF2F6BFF)
private val AccentDeep = Color(0xFF1E50E6)
private val AccentMist = Color(0xFFE3ECFF)
private val Back = Color(0xFF0F766E)          // returning: a settled teal, "they came back"
private val BackMist = Color(0xFFDDF3EF)
private val Track = Color(0xFFF1F4F9)
private val Hairline = Color(0x140F172A)
private val PageBg = Color(0xFFF7F8FB)
private val Up = Color(0xFF15803D)

private fun rupees(v: Double) = "₹" + formatInr(kotlin.math.round(v))

// ---- The card on Home ------------------------------------------------------

/** Two big tappable halves — New and Returning — over one split bar, for a week or a month. */
@Composable
internal fun CustomersCard(c: CustomerSummary, onOpen: (period: String, type: String) -> Unit, modifier: Modifier = Modifier) {
    val view = LocalView.current
    var monthly by remember { mutableStateOf(true) }
    val p = if (monthly) c.month else c.week
    val period = if (monthly) "month" else "week"

    Column(modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                if (p.total == 0) "No customers ${p.label.lowercase()} yet" else "${p.total} " + (if (p.total == 1) "customer" else "customers") + " ${p.label.lowercase()}",
                fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink, modifier = Modifier.weight(1f),
            )
            Segmented(listOf("Week", "Month"), if (monthly) 1 else 0) { monthly = it == 1; Haptics.tick(view) }
        }
        Spacer(Modifier.height(14.dp))

        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Half(
                count = p.new, label = "New", tone = AccentDeep, mist = AccentMist,
                note = compare(p.new, p.lastNew, monthly), modifier = Modifier.weight(1f),
            ) { onOpen(period, "new") }
            Half(
                count = p.returning, label = "Returning", tone = Back, mist = BackMist,
                note = compare(p.returning, p.lastReturning, monthly), modifier = Modifier.weight(1f),
            ) { onOpen(period, "returning") }
        }

        if (p.total > 0) {
            Spacer(Modifier.height(12.dp))
            val share by animateFloatAsState(p.new.toFloat() / p.total, tween(500, easing = FastOutSlowInEasing), label = "cust-split")
            Row(Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(999.dp)).background(Track)) {
                if (share > 0.001f) Box(Modifier.weight(share).fillMaxHeight().background(AccentDeep))
                if (share < 0.999f) Box(Modifier.weight(1f - share).fillMaxHeight().background(Back))
            }
        }
        // Only once it's true for someone: "0 of your 2 came back" reads as a verdict.
        if (c.cameBack > 0) {
            Spacer(Modifier.height(12.dp))
            Text(
                "${c.cameBack} of your ${c.total} " + (if (c.total == 1) "customer has" else "customers have") + " come back more than once",
                fontSize = 12.5.sp, color = Muted,
            )
        }
    }
}

private fun compare(now: Int, before: Int, monthly: Boolean): String {
    val last = if (monthly) "last month" else "last week"
    return when {
        before == 0 && now == 0 -> "none $last either"
        now > before -> "▲ up from $before $last"
        now < before -> "$before $last"
        else -> "same as $last"
    }
}

@Composable
private fun Half(count: Int, label: String, tone: Color, mist: Color, note: String, modifier: Modifier, onClick: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(16.dp)
    Column(
        modifier
            .pressScale(interaction)
            .clip(shape)
            .background(mist.copy(alpha = 0.55f))
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onClick() }
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(8.dp).clip(RoundedCornerShape(99.dp)).background(tone))
            Spacer(Modifier.width(6.dp))
            Text(label, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = tone, modifier = Modifier.weight(1f))
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = tone, modifier = Modifier.size(16.dp))
        }
        Spacer(Modifier.height(4.dp))
        Text("$count", fontSize = 28.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.6).sp)
        Text(note, fontSize = 11.5.sp, color = if (note.startsWith("▲")) Up else Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

/** A two- or three-way pill switch with a sliding thumb. */
@Composable
private fun Segmented(labels: List<String>, selected: Int, onSelect: (Int) -> Unit) {
    Row(Modifier.clip(RoundedCornerShape(999.dp)).background(Track).padding(3.dp)) {
        labels.forEachIndexed { i, label ->
            val on = i == selected
            Text(
                label, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                color = if (on) Color.White else Muted,
                modifier = Modifier.clip(RoundedCornerShape(999.dp))
                    .background(if (on) Ink else Color.Transparent)
                    .clickable { if (!on) onSelect(i) }
                    .padding(horizontal = 12.dp, vertical = 6.dp),
            )
        }
    }
}

// ---- The people behind it --------------------------------------------------

/**
 * Full screen over Home: the week's or month's customers, split New / Returning under a
 * sliding tab. A row opens to their last visits, with Call and WhatsApp right there.
 */
@Composable
internal fun CustomersScreen(
    api: PartnerApi,
    token: String,
    venueId: Long?,
    startPeriod: String,
    startType: String,
    onClose: () -> Unit,
) {
    val view = LocalView.current
    var period by remember { mutableStateOf(startPeriod) }
    var type by remember { mutableStateOf(startType) }
    var list by remember { mutableStateOf<CustomerList?>(null) }
    var failed by remember { mutableStateOf(false) }
    var open by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(period) {
        list = null
        failed = false
        runCatching { api.insightCustomers(token, venueId, period) }
            .onSuccess { list = it }
            .onFailure { failed = true }
    }

    Dialog(
        onDismissRequest = onClose,
        properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false),
    ) {
        // This window is white at the top: dark status-bar icons, or the clock vanishes.
        val dialogView = LocalView.current
        LaunchedEffect(dialogView) {
            (dialogView.parent as? androidx.compose.ui.window.DialogWindowProvider)?.window?.let { w ->
                androidx.core.view.WindowCompat.getInsetsController(w, dialogView).isAppearanceLightStatusBars = true
            }
        }
        Column(Modifier.fillMaxSize().background(PageBg)) {
            Row(
                Modifier.fillMaxWidth().background(Color.White).statusBarsPadding().padding(horizontal = 4.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onClose) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                Column(Modifier.weight(1f)) {
                    Text("Customers", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
                    Text(if (period == "week") "This week" else "This month", fontSize = 12.sp, color = Muted)
                }
                Segmented(listOf("Week", "Month"), if (period == "week") 0 else 1) {
                    period = if (it == 0) "week" else "month"; open = null; Haptics.tick(view)
                }
                Spacer(Modifier.width(12.dp))
            }

            val rows = list?.customers.orEmpty()
            val newCount = rows.count { it.type == "new" }
            val backCount = rows.size - newCount
            TypeTabs(
                newCount = newCount.takeIf { list != null },
                backCount = backCount.takeIf { list != null },
                returning = type == "returning",
            ) { type = if (it) "returning" else "new"; open = null; Haptics.tick(view) }

            val shown = rows.filter { it.type == type }
            when {
                failed -> Quiet("Couldn't load your customers. Check the connection and try again.")
                list == null -> Skeleton()
                shown.isEmpty() -> Quiet(
                    if (type == "new") "No new customers ${if (period == "week") "this week" else "this month"} yet."
                    else "Nobody has come back ${if (period == "week") "this week" else "this month"} yet. Regulars show up here.",
                )
                else -> LazyColumn(
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    items(shown, key = { it.phone ?: it.name + it.firstVisit }) { row ->
                        val key = row.phone ?: row.name + row.firstVisit
                        CustomerCard(row, expanded = open == key) {
                            open = if (open == key) null else key
                            Haptics.tick(view)
                        }
                    }
                }
            }
        }
    }
}

/** New | Returning, with counts and an underline that slides between them. */
@Composable
private fun TypeTabs(newCount: Int?, backCount: Int?, returning: Boolean, onSelect: (returning: Boolean) -> Unit) {
    BoxWithConstraints(Modifier.fillMaxWidth().background(Color.White)) {
        val half = maxWidth / 2
        val x by animateDpAsState(if (returning) half else 0.dp, spring(dampingRatio = 0.8f, stiffness = 500f), label = "tab-x")
        Row(Modifier.fillMaxWidth()) {
            listOf(false to ("New" to newCount), true to ("Returning" to backCount)).forEach { (isBack, pair) ->
                val on = isBack == returning
                Row(
                    Modifier.weight(1f).height(48.dp).clickable { if (!on) onSelect(isBack) },
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(pair.first, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = if (on) Ink else Muted)
                    pair.second?.let {
                        Spacer(Modifier.width(6.dp))
                        Text(
                            "$it", fontSize = 11.5.sp, fontWeight = FontWeight.ExtraBold,
                            color = if (on) Color.White else Muted,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp))
                                .background(if (on) (if (isBack) Back else AccentDeep) else Track)
                                .padding(horizontal = 7.dp, vertical = 1.dp),
                        )
                    }
                }
            }
        }
        Box(
            Modifier.offset(x = x).align(Alignment.BottomStart).width(half).height(3.dp)
                .padding(horizontal = 40.dp).clip(RoundedCornerShape(99.dp))
                .background(if (returning) Back else AccentDeep),
        )
    }
    Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
    Spacer(Modifier.height(8.dp))
}

@Composable
private fun CustomerCard(c: InsightCustomer, expanded: Boolean, onToggle: () -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(18.dp)
    val turn by animateFloatAsState(if (expanded) 180f else 0f, tween(220), label = "chev")

    Column(
        Modifier.fillMaxWidth()
            .pressScale(interaction, pressedScale = 0.985f)
            .clip(shape)
            .background(Color.White)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onToggle)
            .animateContentSize(spring(dampingRatio = 0.85f, stiffness = 500f))
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Initials(c.name, if (c.type == "new") AccentDeep else Back)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(c.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(visitLine(c), fontSize = 12.5.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(rupees(c.spent), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
                Text(viaLabel(c.via), fontSize = 11.sp, color = Muted)
            }
            Spacer(Modifier.width(4.dp))
            Icon(Icons.Filled.KeyboardArrowDown, contentDescription = null, tint = Muted, modifier = Modifier.size(20.dp).rotate(turn))
        }

        AnimatedVisibility(expanded, enter = fadeIn(tween(180)) + expandVertically(), exit = fadeOut(tween(120)) + shrinkVertically()) {
            Column {
                Spacer(Modifier.height(12.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
                Spacer(Modifier.height(10.dp))
                Text(
                    if (c.visits == 1) "Their visit" else "Last ${c.recent.size} of ${c.visits} visits · since ${c.firstVisit}",
                    fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Muted,
                )
                Spacer(Modifier.height(6.dp))
                c.recent.forEach { v ->
                    Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(if (c.type == "new") AccentDeep else Back))
                        Spacer(Modifier.width(10.dp))
                        Text(listOf(v.date, v.time).filter { it.isNotBlank() }.joinToString(" · "), fontSize = 13.sp, color = Ink, modifier = Modifier.weight(1f))
                        Text(viaLabel(v.channel), fontSize = 11.5.sp, color = Muted)
                        Spacer(Modifier.width(10.dp))
                        Text(rupees(v.amount), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink)
                    }
                }
                c.phone?.let { phone ->
                    Spacer(Modifier.height(10.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Action(Icons.Filled.Call, "Call", Modifier.weight(1f)) {
                            Haptics.tick(view)
                            runCatching { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:+91$phone"))) }
                        }
                        Action(Icons.Filled.Chat, "WhatsApp", Modifier.weight(1f)) {
                            Haptics.tick(view)
                            runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/91$phone"))) }
                        }
                    }
                }
            }
        }
    }
}

private fun visitLine(c: InsightCustomer): String = when {
    c.type == "new" && c.visits == 1 -> "First visit · ${c.lastVisit}"
    c.type == "new" -> "New · ${c.visits} visits · last ${c.lastVisit}"
    else -> "${ordinal(c.visits)} visit · last ${c.lastVisit}"
}

private fun ordinal(n: Int): String {
    val suffix = if (n % 100 in 11..13) "th" else when (n % 10) { 1 -> "st"; 2 -> "nd"; 3 -> "rd"; else -> "th" }
    return "$n$suffix"
}

private fun viaLabel(via: String) = when (via) {
    "app" -> "App"
    "whatsapp" -> "WhatsApp"
    else -> "Walk-in"
}

@Composable
private fun Initials(name: String, tone: Color) {
    val initials = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
        .let { w -> if (w.size >= 2) "${w[0][0]}${w[1][0]}" else name.trim().take(2) }.uppercase().ifBlank { "?" }
    Box(
        Modifier.size(42.dp).clip(RoundedCornerShape(14.dp)).background(tone.copy(alpha = 0.12f)),
        contentAlignment = Alignment.Center,
    ) { Text(initials, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = tone) }
}

@Composable
private fun Action(icon: androidx.compose.ui.graphics.vector.ImageVector, label: String, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        modifier.height(44.dp)
            .pressScale(interaction)
            .clip(RoundedCornerShape(12.dp))
            .background(AccentMist)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = AccentDeep, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = AccentDeep)
    }
}

/** Placeholder rows that breathe while the list loads — the shape of what's coming. */
@Composable
private fun Skeleton() {
    val t = rememberInfiniteTransition(label = "skeleton")
    val a by t.animateFloat(0.45f, 1f, infiniteRepeatable(tween(700), RepeatMode.Reverse), label = "skeleton-a")
    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        repeat(5) {
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Color.White).padding(14.dp)
                    .graphicsLayer { alpha = a },
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(42.dp).clip(RoundedCornerShape(14.dp)).background(Track))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Box(Modifier.fillMaxWidth(0.5f).height(12.dp).clip(RoundedCornerShape(6.dp)).background(Track))
                    Spacer(Modifier.height(8.dp))
                    Box(Modifier.fillMaxWidth(0.7f).height(10.dp).clip(RoundedCornerShape(6.dp)).background(Track))
                }
            }
        }
    }
}

@Composable
private fun Quiet(text: String) {
    Box(Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
        Text(text, fontSize = 14.sp, color = Muted, textAlign = TextAlign.Center)
    }
}
