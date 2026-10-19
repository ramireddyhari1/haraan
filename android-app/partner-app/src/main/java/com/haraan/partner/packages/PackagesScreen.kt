package com.haraan.partner.packages

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.PackageHolder
import com.haraan.partner.PackagesPage
import com.haraan.partner.PartnerApi
import com.haraan.partner.PassDetail
import com.haraan.partner.StaffPlace
import com.haraan.partner.VenuePackageRow
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.ProblemState
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.components.rememberMoneyMotion
import com.haraan.partner.ui.desk.AddPill
import com.haraan.partner.ui.desk.DAmber
import com.haraan.partner.ui.desk.DBlue
import com.haraan.partner.ui.desk.DFaint
import com.haraan.partner.ui.desk.DGreen
import com.haraan.partner.ui.desk.DHair
import com.haraan.partner.ui.desk.DInk
import com.haraan.partner.ui.desk.DMuted
import com.haraan.partner.ui.desk.DNavy
import com.haraan.partner.ui.desk.DPage
import com.haraan.partner.ui.desk.DRed
import com.haraan.partner.ui.desk.DeskChip
import com.haraan.partner.ui.desk.DeskField
import com.haraan.partner.ui.desk.DeskLoad
import com.haraan.partner.ui.desk.DeskMethod
import com.haraan.partner.ui.desk.DeskSection
import com.haraan.partner.ui.desk.DeskSkeleton
import com.haraan.partner.ui.desk.DeskTopBar
import com.haraan.partner.ui.desk.MethodPicker
import com.haraan.partner.ui.desk.deskLoad
import com.haraan.partner.ui.desk.dial
import com.haraan.partner.ui.desk.methodLabel
import com.haraan.partner.ui.desk.openWhatsApp
import com.haraan.partner.ui.desk.readable
import com.haraan.partner.ui.desk.shortDate
import kotlinx.coroutines.launch
import kotlin.math.ceil
import kotlin.math.min
import kotlin.math.roundToLong
import kotlin.math.sqrt

/** Card colours, picked by offer id so a pass always wears its offer's colour. */
private val CARD_TONES = listOf(
    Color(0xFF2563EB), Color(0xFF0D9488), Color(0xFF7C3AED), Color(0xFFEA580C), Color(0xFF0284C7), Color(0xFFDB2777),
)

private fun toneFor(key: Long) = CARD_TONES[(key % CARD_TONES.size).toInt().coerceAtLeast(0)]

private fun holderLine(h: PackageHolder): Pair<String, Color> = when {
    h.expired -> "Expired ${shortDate(h.expiresAt) ?: ""}".trim() to DRed
    h.expiringSoon && h.expiresInDays != null ->
        (if (h.expiresInDays <= 0) "Expires today" else if (h.expiresInDays == 1) "Expires tomorrow" else "Expires in ${h.expiresInDays} days") to DAmber
    h.expiresAt != null -> "Valid till ${shortDate(h.expiresAt)}" to DMuted
    else -> "Never expires" to DMuted
}

/**
 * Passes the venue sells and who's holding one.
 *
 * [canDefine] (pricing) creates, edits and pauses offers; [canDesk] (bookings)
 * sells them. Spending a session happens on the walk-in sheet.
 */
@Composable
fun PackagesScreen(
    api: PartnerApi,
    token: String,
    venueId: Long?,
    venues: List<StaffPlace>,
    canDefine: Boolean,
    canDesk: Boolean,
    onBack: () -> Unit,
) {
    var reload by remember { mutableIntStateOf(0) }
    var load by remember { mutableStateOf<DeskLoad<PackagesPage>>(DeskLoad.Loading) }
    var openOffer by remember { mutableStateOf<VenuePackageRow?>(null) }
    var selling by remember { mutableStateOf<VenuePackageRow?>(null) }
    var editing by remember { mutableStateOf<VenuePackageRow?>(null) }
    var adding by remember { mutableStateOf(false) }
    var openPass by remember { mutableStateOf<PackageHolder?>(null) }
    var query by remember { mutableStateOf("") }
    var onlyExpiring by remember { mutableStateOf(false) }
    val snack = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val view = LocalView.current

    LaunchedEffect(reload) {
        val next = deskLoad { api.packages(token, venueId) }
        if (next is DeskLoad.Ready || load !is DeskLoad.Ready) load = next
    }
    val page = (load as? DeskLoad.Ready)?.value

    Scaffold(
        containerColor = DPage,
        snackbarHost = { SnackbarHost(snack) },
        topBar = {
            DeskTopBar("Packages", onBack) {
                if (canDefine && page != null && page.packages.isNotEmpty()) AddPill("New offer") { adding = true }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val l = load) {
                DeskLoad.Loading -> DeskSkeleton(heroHeight = 230)
                is DeskLoad.Failed -> ProblemState(l.problem, onRetry = { reload++ }, detail = l.message)
                is DeskLoad.Ready -> if (l.value.packages.isEmpty()) {
                    EmptyPackages(canDefine) { adding = true }
                } else {
                    val p = l.value
                    val q = query.trim().lowercase()
                    val holders = p.holders
                        .filter { !onlyExpiring || it.expiringSoon }
                        .filter { q.isEmpty() || it.name.lowercase().contains(q) || it.phone.contains(q) || it.packageName.lowercase().contains(q) }
                    val toneOf = p.packages.associate { it.name to toneFor(it.id) }
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        item(key = "hero") {
                            PassHero(p, onlyExpiring) { Haptics.tick(view); onlyExpiring = !onlyExpiring }
                        }
                        item(key = "h-offers") { DeskSection("Offers") }
                        items(p.packages, key = { "o${it.id}" }) { o ->
                            OfferCard(o) { openOffer = o }
                        }
                        item(key = "h-holders") {
                            Column {
                                DeskSection(if (onlyExpiring) "Expiring this week" else "On a pass now")
                                if (p.holders.size > 4) {
                                    Spacer(Modifier.height(6.dp))
                                    DeskField(query, { query = it }, "Search name, number or pass")
                                }
                            }
                        }
                        if (holders.isEmpty()) {
                            item(key = "no-holders") {
                                Text(
                                    if (p.holders.isEmpty()) "Nobody holds a pass yet. Sell one from an offer above." else "Nobody matches.",
                                    fontSize = 14.sp, color = DMuted, modifier = Modifier.padding(8.dp),
                                )
                            }
                        }
                        items(holders, key = { "h${it.id}" }) { h ->
                            HolderRow(h, toneOf[h.packageName] ?: DBlue) { openPass = h }
                        }
                    }
                }
            }
        }
    }

    openOffer?.let { o ->
        OfferSheet(
            offer = o, canDefine = canDefine, canDesk = canDesk,
            onDismiss = { openOffer = null },
            onSell = { openOffer = null; selling = o },
            onEdit = { openOffer = null; editing = o },
            onToggle = {
                openOffer = null
                scope.launch {
                    try {
                        api.savePackage(token, o.id, o.name, o.price, o.sessions, o.validityDays, o.venueId, !o.isActive)
                        Haptics.confirm(view); reload++
                        snack.showSnackbar(if (o.isActive) "${o.name} paused. Passes already sold still work." else "${o.name} is on sale again")
                    } catch (e: Exception) { Haptics.reject(view); snack.showSnackbar(e.readable()) }
                }
            },
        )
    }

    selling?.let { o ->
        SellSheet(
            offer = o,
            onDismiss = { selling = null },
            onSell = { name, phone, method, done ->
                scope.launch {
                    try {
                        api.sellPackage(token, o.id, phone, name, method.api, venueId)
                        Haptics.money(view)
                        done(null)
                        selling = null
                        reload++
                        snack.showSnackbar("${o.name} sold to ${name.ifBlank { "+91 $phone" }} · ₹${formatInr(o.price.toDouble())} ${method.label}")
                    } catch (e: Exception) { Haptics.reject(view); done(e.readable()) }
                }
            },
        )
    }

    if (adding || editing != null) {
        val target = editing
        OfferEditorSheet(
            existing = target,
            venues = venues,
            onDismiss = { adding = false; editing = null },
            onSave = { f, done ->
                scope.launch {
                    try {
                        api.savePackage(token, target?.id, f.name, f.price, f.sessions, f.validityDays, f.venueId, f.active)
                        Haptics.confirm(view)
                        done(null)
                        adding = false; editing = null
                        reload++
                        snack.showSnackbar(if (target == null) "${f.name} is on sale" else "Saved. Passes already sold keep their terms.")
                    } catch (e: Exception) { Haptics.reject(view); done(e.readable()) }
                }
            },
        )
    }

    openPass?.let { h ->
        PassSheet(api, token, h, toneFor((page?.packages?.firstOrNull { it.name == h.packageName }?.id) ?: h.id)) { openPass = null }
    }
}

// ---- Hero ---------------------------------------------------------------------------

/**
 * A fan of three punch cards, dealt in on entrance, the front one already
 * half punched. Under it: money sold this month and who's on a pass.
 */
@Composable
private fun PassHero(p: PackagesPage, onlyExpiring: Boolean, onExpiring: () -> Unit) {
    val s = p.summary
    val deal = remember { Animatable(0f) }
    LaunchedEffect(Unit) { deal.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessLow)) }
    val money = rememberMoneyMotion(s.soldMonthAmount.toDouble(), "packages.month")
    val tones = p.packages.take(3).map { toneFor(it.id) }.ifEmpty { listOf(DBlue) }

    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).border(1.dp, DHair, RoundedCornerShape(24.dp))) {
        Canvas(
            Modifier.fillMaxWidth().height(150.dp).semantics { contentDescription = "${s.activeHolders} passes in use" },
        ) {
            val u = size.height / 150f
            val cw = 150 * u
            val ch = 92 * u
            val cx = size.width / 2
            val angles = listOf(-11f, 9f, 0f)
            val shifts = listOf(-46f, 46f, 0f)
            for (i in 0..2) {
                val t = tones[i % tones.size]
                val a = angles[i] * deal.value
                val dx = shifts[i] * u * deal.value
                val dy = (1f - deal.value) * 30 * u
                rotate(a, Offset(cx + dx, 80 * u + dy)) {
                    translate(cx + dx - cw / 2, 80 * u + dy - ch / 2) {
                        punchCard(Size(cw, ch), t, holes = 10, punched = if (i == 2) 4 else 0, u = u)
                    }
                }
            }
        }
        Row(Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 6.dp), verticalAlignment = Alignment.Bottom) {
            Column(Modifier.weight(1f)) {
                Text(
                    when (s.activeHolders) { 0 -> "No passes in use"; 1 -> "1 pass in use"; else -> "${s.activeHolders} passes in use" },
                    fontSize = 16.sp, fontWeight = FontWeight.Bold, color = DInk,
                )
                Text(
                    if (s.soldMonth == 0) "None sold this month yet" else "${s.soldMonth} sold this month",
                    fontSize = 12.5.sp, color = DMuted,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(money.shown.roundToLong().toDouble()), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = DInk)
                Text("passes sold this month", fontSize = 12.sp, color = DMuted)
            }
        }
        if (s.expiringSoon > 0) {
            Row(Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
                Row(
                    Modifier
                        .pressableTile(999.dp, pressedScale = 0.95f, onClick = onExpiring)
                        .clip(RoundedCornerShape(999.dp))
                        .background(DAmber.copy(alpha = if (onlyExpiring) 0.14f else 0.07f))
                        .border(if (onlyExpiring) 1.5.dp else 1.dp, DAmber.copy(alpha = if (onlyExpiring) 0.8f else 0.25f), RoundedCornerShape(999.dp))
                        .heightIn(min = 36.dp)
                        .padding(horizontal = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(Modifier.size(7.dp).clip(CircleShape).background(DAmber))
                    Spacer(Modifier.width(7.dp))
                    Text(
                        if (s.expiringSoon == 1) "1 pass expiring this week" else "${s.expiringSoon} passes expiring this week",
                        fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DAmber,
                    )
                }
            }
        } else {
            Spacer(Modifier.height(14.dp))
        }
    }
}

/**
 * A punch card: a ticket with a notch either side, a coloured header band, and a
 * grid of holes, one per session. [punched] holes are cut out (the card behind
 * shows through); the rest are printed rings.
 */
private fun DrawScope.punchCard(size: Size, tone: Color, holes: Int, punched: Int, u: Float, faded: Boolean = false) {
    val r = 10 * u
    val notch = 7 * u
    val body = Path().apply {
        addRoundRect(androidx.compose.ui.geometry.RoundRect(0f, 0f, size.width, size.height, CornerRadius(r)))
    }
    // Shadow, paper, band.
    drawRoundRect(Color(0x140F172A), Offset(0f, 3 * u), size, CornerRadius(r))
    drawPath(body, Color.White)
    val band = size.height * 0.26f
    drawRoundRect(if (faded) DFaint else tone, Offset.Zero, Size(size.width, band), CornerRadius(r))
    drawRect(if (faded) DFaint else tone, Offset(0f, band - r), Size(size.width, r))
    drawRoundRect(Color.White.copy(alpha = 0.85f), Offset(10 * u, band * 0.38f), Size(size.width * 0.32f, 4 * u), CornerRadius(2 * u))
    drawPath(body, (if (faded) DFaint else tone).copy(alpha = 0.6f), style = Stroke(1.2f * u))
    // Side notches (drawn in page colour so the card reads as a ticket).
    drawCircle(Color.White, notch, Offset(0f, band + (size.height - band) / 2))
    drawCircle(Color.White, notch, Offset(size.width, band + (size.height - band) / 2))
    drawArc(DHair, -90f, 180f, false, Offset(-notch, band + (size.height - band) / 2 - notch), Size(notch * 2, notch * 2), style = Stroke(1f * u))
    drawArc(DHair, 90f, 180f, false, Offset(size.width - notch, band + (size.height - band) / 2 - notch), Size(notch * 2, notch * 2), style = Stroke(1f * u))
    // Holes.
    val shown = holes.coerceIn(1, 30)
    val areaW = size.width - 34 * u
    val areaH = size.height - band - 16 * u
    val guess = ceil(sqrt(shown * areaW / areaH.toDouble())).toInt().coerceIn(1, shown)
    val rows = ceil(shown / guess.toDouble()).toInt()
    val cols = ceil(shown / rows.toDouble()).toInt()
    val gx = areaW / cols
    val gy = areaH / rows
    val hr = (min(gx, gy) * 0.30f).coerceAtMost(7 * u)
    for (i in 0 until shown) {
        val p = Offset(17 * u + gx * (i % cols) + gx / 2, band + 8 * u + gy * (i / cols) + gy / 2)
        if (i < punched) {
            drawCircle(DPage, hr, p)
            drawCircle(Color(0x220F172A), hr, p, style = Stroke(1f * u))
        } else {
            drawCircle((if (faded) DFaint else tone).copy(alpha = 0.55f), hr, p, style = Stroke(1.4f * u))
        }
    }
}

// ---- Offer card -------------------------------------------------------------------------------

@Composable
private fun OfferCard(o: VenuePackageRow, onClick: () -> Unit) {
    val t = toneFor(o.id)
    Row(
        Modifier
            .fillMaxWidth()
            .pressableTile(20.dp, pressedScale = 0.97f, onClick = onClick)
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, DHair, RoundedCornerShape(20.dp))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(width = 92.dp, height = 62.dp)) {
            punchCard(size, t, holes = o.sessions, punched = 0, u = size.width / 150f * 1.6f, faded = !o.isActive)
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(o.name, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = if (o.isActive) DInk else DMuted,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (!o.isActive) { Spacer(Modifier.width(8.dp)); Tag("Paused", DMuted) }
            }
            Text(
                listOfNotNull(
                    "${o.sessions} sessions",
                    o.validityDays?.let { "valid $it days" } ?: "no expiry",
                    o.venue?.let { "only $it" },
                ).joinToString(" · "),
                fontSize = 12.5.sp, color = DMuted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("₹" + formatInr(o.price.toDouble()), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = DInk)
                Text("  ₹${formatInr(o.perSession.toDouble())}/session", fontSize = 12.sp, color = DFaint)
                Spacer(Modifier.weight(1f))
                if (o.sold > 0) Text("${o.sold} sold", fontSize = 12.sp, color = DMuted)
            }
        }
    }
}

@Composable
private fun Tag(text: String, color: Color) {
    Text(
        text, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = color,
        modifier = Modifier.clip(RoundedCornerShape(6.dp)).background(color.copy(alpha = 0.09f)).padding(horizontal = 7.dp, vertical = 2.dp),
    )
}

// ---- Holder row --------------------------------------------------------------------------------

/** A pass in someone's hand: a strip of sessions, the used ones punched out. */
@Composable
private fun HolderRow(h: PackageHolder, tone: Color, onClick: () -> Unit) {
    val (line, lineTone) = holderLine(h)
    Column(
        Modifier
            .fillMaxWidth()
            .pressableTile(18.dp, pressedScale = 0.97f, onClick = onClick)
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, if (h.expiringSoon) DAmber.copy(alpha = 0.4f) else DHair, RoundedCornerShape(18.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(h.name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text("${h.packageName} · +91 ${h.phone}", fontSize = 12.sp, color = DMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("${h.remaining} left", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = if (h.usable) DInk else DRed)
                Text("of ${h.total}", fontSize = 11.5.sp, color = DFaint)
            }
        }
        Spacer(Modifier.height(8.dp))
        Canvas(Modifier.fillMaxWidth().height(14.dp)) {
            val n = h.total.coerceIn(1, 40)
            val gap = 3.dp.toPx()
            val w = ((size.width - gap * (n - 1)) / n).coerceAtLeast(2f)
            val usedShown = ((h.used.toFloat() / h.total.coerceAtLeast(1)) * n).toInt()
            for (i in 0 until n) {
                val x = i * (w + gap)
                if (i < usedShown) {
                    drawRoundRect(Color(0xFFE2E8F0), Offset(x, 0f), Size(w, size.height), CornerRadius(minOf(w, size.height) / 2))
                } else {
                    drawRoundRect(tone, Offset(x, 0f), Size(w, size.height), CornerRadius(minOf(w, size.height) / 2))
                }
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(line, fontSize = 12.sp, color = lineTone)
    }
}

// ---- Empty --------------------------------------------------------------------------------------

@Composable
private fun EmptyPackages(canDefine: Boolean, onAdd: () -> Unit) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(36.dp))
        val deal = remember { Animatable(0f) }
        LaunchedEffect(Unit) { deal.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = 120f)) }
        Canvas(Modifier.fillMaxWidth().height(140.dp)) {
            val u = size.height / 140f
            val cw = 150 * u
            val ch = 92 * u
            rotate(-6f * deal.value, Offset(size.width / 2, 70 * u)) {
                translate(size.width / 2 - cw / 2, 70 * u - ch / 2 + (1f - deal.value) * 40 * u) {
                    punchCard(Size(cw, ch), DBlue, holes = 10, punched = 0, u = u)
                }
            }
        }
        Spacer(Modifier.height(16.dp))
        Text("No offers yet", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = DInk)
        Spacer(Modifier.height(6.dp))
        Text(
            "Sell prepaid passes like \"10 hours for ₹4,000\". The money comes in today, and the desk spends one session per visit from the walk-in sheet.",
            fontSize = 14.sp, color = DMuted, lineHeight = 20.sp, textAlign = TextAlign.Center,
        )
        if (canDefine) {
            Spacer(Modifier.height(22.dp))
            Button(
                onClick = onAdd, colors = ButtonDefaults.buttonColors(containerColor = DBlue), shape = RoundedCornerShape(14.dp),
                modifier = Modifier.heightIn(min = 52.dp).fillMaxWidth(),
            ) { Text("Create your first offer", fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
        }
        Spacer(Modifier.height(40.dp))
    }
}

// ---- Offer sheet ---------------------------------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun OfferSheet(
    offer: VenuePackageRow,
    canDefine: Boolean,
    canDesk: Boolean,
    onDismiss: () -> Unit,
    onSell: () -> Unit,
    onEdit: () -> Unit,
    onToggle: () -> Unit,
) {
    val view = LocalView.current
    val t = toneFor(offer.id)
    val lift = remember { Animatable(30f) }
    LaunchedEffect(offer.id) { lift.animateTo(0f, spring(dampingRatio = 0.5f, stiffness = 200f)) }
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(horizontal = 20.dp)) {
            Canvas(Modifier.fillMaxWidth().height(170.dp)) {
                val u = size.height / 170f
                val cw = min(size.width * 0.8f, 280 * u)
                val ch = cw * 0.6f
                translate((size.width - cw) / 2, (size.height - ch) / 2 + lift.value * u) {
                    punchCard(Size(cw, ch), t, holes = offer.sessions, punched = 0, u = u * 1.4f, faded = !offer.isActive)
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(offer.name, fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
            Text(
                "${offer.sessions} sessions for ₹${formatInr(offer.price.toDouble())} · ₹${formatInr(offer.perSession.toDouble())} each",
                fontSize = 13.5.sp, color = DMuted, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center,
            )
            Text(
                listOfNotNull(
                    offer.validityDays?.let { "Valid $it days from purchase" } ?: "Never expires",
                    offer.venue?.let { "only at $it" } ?: "any branch",
                ).joinToString(" · "),
                fontSize = 12.5.sp, color = DFaint, modifier = Modifier.fillMaxWidth().padding(top = 2.dp), textAlign = TextAlign.Center,
            )
            if (offer.sold > 0) {
                Text(
                    "${offer.sold} sold so far" + if (offer.soldMonth > 0) " · ${offer.soldMonth} this month" else "",
                    fontSize = 12.5.sp, color = DMuted, modifier = Modifier.fillMaxWidth().padding(top = 6.dp), textAlign = TextAlign.Center,
                )
            }
            Spacer(Modifier.height(20.dp))
            if (canDesk && offer.isActive) {
                Button(
                    onClick = { Haptics.tick(view); onSell() },
                    colors = ButtonDefaults.buttonColors(containerColor = DBlue), shape = RoundedCornerShape(14.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
                ) { Text("Sell to a customer", fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold) }
                Spacer(Modifier.height(10.dp))
            }
            if (canDefine) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    DeskChip("Edit", false, Modifier.weight(1f)) { Haptics.tick(view); onEdit() }
                    DeskChip(if (offer.isActive) "Pause sales" else "Resume sales", false, Modifier.weight(1f)) { Haptics.tick(view); onToggle() }
                }
            }
            Spacer(Modifier.height(20.dp))
        }
    }
}

// ---- Sell sheet ------------------------------------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SellSheet(
    offer: VenuePackageRow,
    onDismiss: () -> Unit,
    onSell: (name: String, phone: String, method: DeskMethod, done: (String?) -> Unit) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var method by remember { mutableStateOf(DeskMethod.CASH) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val view = LocalView.current

    ModalBottomSheet(
        onDismissRequest = { if (!busy) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().imePadding().navigationBarsPadding().padding(horizontal = 20.dp)) {
            Text("Sell ${offer.name}", fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk)
            Text("${offer.sessions} sessions · ₹${formatInr(offer.price.toDouble())}", fontSize = 13.sp, color = DMuted)
            Spacer(Modifier.height(16.dp))
            DeskField(name, { name = it; error = null }, "Customer name")
            Spacer(Modifier.height(10.dp))
            DeskField(phone, { phone = it.filter(Char::isDigit).take(10); error = null }, "Mobile", KeyboardType.Phone, prefix = "+91 ")
            Text("The desk finds this pass by the mobile number when they come to play.", fontSize = 12.sp, color = DFaint, modifier = Modifier.padding(top = 6.dp))
            Spacer(Modifier.height(16.dp))
            Text("Paid by", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
            Spacer(Modifier.height(8.dp))
            MethodPicker(method) { Haptics.tick(view); method = it }
            error?.let { Text(it, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(top = 10.dp)) }
            Spacer(Modifier.height(14.dp))
            Button(
                onClick = { busy = true; error = null; onSell(name.trim(), phone, method) { err -> busy = false; error = err } },
                enabled = phone.length == 10 && !busy,
                colors = ButtonDefaults.buttonColors(containerColor = DBlue, disabledContainerColor = DBlue.copy(alpha = 0.35f), disabledContentColor = Color.White),
                shape = RoundedCornerShape(14.dp),
                modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
            ) {
                Text(if (busy) "Selling…" else "Take ₹${formatInr(offer.price.toDouble())} · ${method.label}", fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold)
            }
            Spacer(Modifier.height(16.dp))
        }
    }
}

// ---- Offer editor -------------------------------------------------------------------------------------

private data class OfferForm(
    val name: String,
    val price: Int,
    val sessions: Int,
    val validityDays: Int?,
    val venueId: Long?,
    val active: Boolean,
)

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun OfferEditorSheet(
    existing: VenuePackageRow?,
    venues: List<StaffPlace>,
    onDismiss: () -> Unit,
    onSave: (OfferForm, (String?) -> Unit) -> Unit,
) {
    val view = LocalView.current
    var name by remember { mutableStateOf(existing?.name ?: "") }
    var price by remember { mutableStateOf(existing?.price?.toString() ?: "") }
    var sessions by remember { mutableStateOf(existing?.sessions?.toString() ?: "") }
    val presets = listOf(30, 60, 90, 180)
    var validity by remember { mutableStateOf(existing?.validityDays) }
    var customDays by remember { mutableStateOf(existing?.validityDays?.takeIf { it !in presets }?.toString() ?: "") }
    var custom by remember { mutableStateOf(existing?.validityDays != null && existing.validityDays !in presets) }
    var venueId by remember { mutableStateOf(existing?.venueId) }
    var active by remember { mutableStateOf(existing?.isActive ?: true) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val p = price.toIntOrNull() ?: 0
    val n = sessions.toIntOrNull() ?: 0
    val days = if (custom) customDays.toIntOrNull() else validity
    val canSave = name.isNotBlank() && p > 0 && n in 1..500 && (!custom || (days != null && days > 0)) && !busy

    ModalBottomSheet(
        onDismissRequest = { if (!busy) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().imePadding().navigationBarsPadding()) {
            Column(Modifier.weight(1f, fill = false).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp)) {
                Text(if (existing == null) "New offer" else "Edit ${existing.name}", fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk)
                if (existing != null && existing.sold > 0) {
                    Text("Changes apply to new sales. The ${existing.sold} passes already sold keep their terms.", fontSize = 12.5.sp, color = DMuted, modifier = Modifier.padding(top = 4.dp))
                }
                Spacer(Modifier.height(16.dp))
                DeskField(name, { name = it }, "Name, e.g. 10 Hour Pass")
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    DeskField(price, { price = it.filter(Char::isDigit).take(7) }, "Price", KeyboardType.Number, Modifier.weight(1f), prefix = "₹ ")
                    DeskField(sessions, { sessions = it.filter(Char::isDigit).take(3) }, "Sessions", KeyboardType.Number, Modifier.weight(1f))
                }
                if (p > 0 && n > 0) {
                    Text("₹${formatInr((p / n).toDouble())} a session", fontSize = 12.5.sp, color = DMuted, modifier = Modifier.padding(top = 6.dp))
                }

                Spacer(Modifier.height(18.dp))
                Text("Valid for", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                Spacer(Modifier.height(8.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    DeskChip("Never expires", !custom && validity == null) { Haptics.tick(view); custom = false; validity = null }
                    presets.forEach { d -> DeskChip("$d days", !custom && validity == d) { Haptics.tick(view); custom = false; validity = d } }
                    DeskChip("Other", custom) { Haptics.tick(view); custom = true }
                }
                if (custom) {
                    Spacer(Modifier.height(8.dp))
                    DeskField(customDays, { customDays = it.filter(Char::isDigit).take(4) }, "Days", KeyboardType.Number)
                }

                if (venues.size > 1) {
                    Spacer(Modifier.height(18.dp))
                    Text("Where it works", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                    Spacer(Modifier.height(8.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        DeskChip("Every branch", venueId == null) { Haptics.tick(view); venueId = null }
                        venues.forEach { v -> DeskChip("Only ${v.name}", venueId == v.id) { Haptics.tick(view); venueId = v.id } }
                    }
                }

                if (existing != null) {
                    Spacer(Modifier.height(18.dp))
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("On sale", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk)
                            Text(if (active) "The desk can sell it." else "Paused: can't be sold. Passes already sold still work.", fontSize = 12.5.sp, color = DMuted)
                        }
                        Switch(checked = active, onCheckedChange = { Haptics.tick(view); active = it },
                            colors = SwitchDefaults.colors(checkedTrackColor = DBlue, checkedThumbColor = Color.White))
                    }
                }
                Spacer(Modifier.height(16.dp))
            }
            Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 12.dp)) {
                error?.let { Text(it, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(bottom = 8.dp)) }
                Button(
                    onClick = {
                        busy = true; error = null
                        onSave(OfferForm(name.trim(), p, n, days, venueId, active)) { err -> busy = false; error = err }
                    },
                    enabled = canSave,
                    colors = ButtonDefaults.buttonColors(containerColor = DBlue, disabledContainerColor = DBlue.copy(alpha = 0.35f), disabledContentColor = Color.White),
                    shape = RoundedCornerShape(14.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
                ) { Text(if (busy) "Saving…" else if (existing == null) "Put it on sale" else "Save changes", fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold) }
            }
        }
    }
}

// ---- Pass sheet ------------------------------------------------------------------------------------------

/**
 * One customer's pass, as the card itself: used sessions punched out, one by one
 * as the sheet opens, with the dates they were spent listed under it.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PassSheet(api: PartnerApi, token: String, holder: PackageHolder, tone: Color, onDismiss: () -> Unit) {
    var load by remember { mutableStateOf<DeskLoad<PassDetail>>(DeskLoad.Loading) }
    val punch = remember { Animatable(0f) }
    val view = LocalView.current
    val context = LocalContext.current
    LaunchedEffect(holder.id) {
        load = deskLoad { api.passDetail(token, holder.id) }
        punch.animateTo(holder.used.toFloat(), tween(durationMillis = (holder.used * 90).coerceIn(200, 900)))
    }
    val (line, lineTone) = holderLine(holder)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).navigationBarsPadding().padding(horizontal = 20.dp)) {
            Canvas(Modifier.fillMaxWidth().height(180.dp).semantics { contentDescription = "${holder.remaining} of ${holder.total} sessions left" }) {
                val u = size.height / 180f
                val cw = min(size.width * 0.86f, 300 * u)
                val ch = cw * 0.6f
                translate((size.width - cw) / 2, (size.height - ch) / 2) {
                    punchCard(Size(cw, ch), tone, holes = holder.total, punched = punch.value.toInt(), u = u * 1.4f, faded = !holder.usable)
                }
            }
            Text(holder.name, fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
            Text("${holder.packageName} · +91 ${holder.phone}", fontSize = 13.sp, color = DMuted, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
            Spacer(Modifier.height(14.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Fact("Sessions left", "${holder.remaining} of ${holder.total}", Modifier.weight(1f), if (holder.usable) DInk else DRed)
                Fact("Validity", line, Modifier.weight(1f), lineTone)
            }

            when (val l = load) {
                is DeskLoad.Ready -> {
                    val d = l.value
                    Text(
                        listOfNotNull(
                            d.soldOn?.let { "Bought ${shortDate(it)}" },
                            "₹${formatInr(d.amountPaid.toDouble())} by ${methodLabel(d.paymentMethod)}",
                        ).joinToString(" · "),
                        fontSize = 12.5.sp, color = DMuted, modifier = Modifier.padding(top = 10.dp),
                    )
                    Spacer(Modifier.height(18.dp))
                    Text(if (d.uses.isEmpty()) "No sessions used yet" else "Sessions used", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                    d.uses.forEachIndexed { i, u ->
                        Row(Modifier.fillMaxWidth().padding(vertical = 9.dp), verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(24.dp).clip(CircleShape).background(tone.copy(alpha = 0.10f)), contentAlignment = Alignment.Center) {
                                Text("${d.uses.size - i}", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = tone)
                            }
                            Spacer(Modifier.width(12.dp))
                            Text(
                                listOfNotNull(shortDate(u.date ?: u.at), u.slot, u.venue).joinToString(" · ").ifEmpty { "Session" },
                                fontSize = 14.sp, color = DInk,
                            )
                        }
                    }
                }
                is DeskLoad.Failed -> Text(l.message, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(top = 10.dp))
                DeskLoad.Loading -> Spacer(Modifier.height(40.dp))
            }

            Spacer(Modifier.height(18.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                DeskChip("WhatsApp", false, Modifier.weight(1f)) {
                    Haptics.tick(view)
                    val msg = "Hi ${holder.name.substringBefore(' ')}, you have ${holder.remaining} of ${holder.total} sessions left on your ${holder.packageName}" +
                        (holder.expiresAt?.let { if (!holder.expired) ", valid till ${shortDate(it)}" else "" } ?: "") + ". See you soon!"
                    openWhatsApp(context, holder.phone, msg)
                }
                DeskChip("Call", false, Modifier.weight(1f)) { Haptics.tick(view); dial(context, holder.phone) }
            }
            Text(
                "To use a session, book a walk-in with this mobile number and pick the pass.",
                fontSize = 12.sp, color = DFaint, modifier = Modifier.padding(top = 10.dp),
            )
            Spacer(Modifier.height(20.dp))
        }
    }
}

@Composable
private fun Fact(label: String, value: String, modifier: Modifier, tone: Color = DInk) {
    Column(modifier.clip(RoundedCornerShape(16.dp)).background(DPage).padding(horizontal = 14.dp, vertical = 12.dp)) {
        Text(label, fontSize = 12.sp, color = DMuted)
        Spacer(Modifier.height(2.dp))
        Text(value, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = tone, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}
