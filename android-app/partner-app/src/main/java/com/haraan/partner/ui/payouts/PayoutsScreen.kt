package com.haraan.partner.ui.payouts

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.partner.PartnerApi
import com.haraan.partner.PayoutAccount
import com.haraan.partner.PayoutBatchRow
import com.haraan.partner.PayoutsPage
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Page = Color(0xFFF7F8FB)
private val Ink = Color(0xFF0F172A)
private val Ink2 = Color(0xFF334155)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hair = Color(0x140F172A)
private val Brand = Color(0xFF2563EB)
private val BrandInk = Color(0xFF1D4ED8)
private val BrandWash = Color(0xFFEEF3FE)
private val Coin = Color(0xFFF5B83D)
private val CoinEdge = Color(0xFFD99A1E)
private val Good = Color(0xFF15803D)
private val Wait = Color(0xFFB45309)
private val Bad = Color(0xFFDC2626)

private sealed interface Load {
    data object Loading : Load
    data class Failed(val message: String) : Load
    data class Ready(val page: PayoutsPage) : Load
}

/**
 * Payouts: where the venue's money is right now, where it goes, who can change that,
 * and every transfer so far. Figures are the same PartnerSettlement numbers /control
 * settles against; nothing here is estimated.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PayoutsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableIntStateOf(0) }
    var editing by remember { mutableStateOf(false) }
    val state by produceState<Load>(Load.Loading, reload) {
        // A live refresh keeps showing the page it had instead of flashing a spinner.
        val fresh = try { Load.Ready(api.payouts(token)) } catch (e: Exception) { Load.Failed(e.message ?: "Couldn't load payouts") }
        value = if (fresh is Load.Failed && value is Load.Ready) value else fresh
    }
    // Haraan changed something here (a settlement paid, the account verified or edited):
    // refetch at once and say what happened.
    var live by remember { mutableStateOf<com.haraan.partner.PartnerUpdateItem?>(null) }
    LaunchedEffect(Unit) {
        com.haraan.partner.updates.PartnerUpdatesBus.events.collect { u ->
            if (u.screen == "payouts") { reload++; live = u }
        }
    }

    Scaffold(
        containerColor = Page,
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Page, scrolledContainerColor = Page),
                title = { Text("Payouts", fontWeight = FontWeight.Bold, color = Ink, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                },
            )
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val s = state) {
                Load.Loading -> CircularProgressIndicator(color = Brand, strokeWidth = 2.5.dp, modifier = Modifier.align(Alignment.Center).size(28.dp))
                is Load.Failed -> Column(Modifier.align(Alignment.Center).padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("Couldn't load payouts", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
                    Spacer(Modifier.height(4.dp))
                    Text(s.message, fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(14.dp))
                    BlueButton("Try again") { reload++ }
                }
                is Load.Ready -> {
                    val p = s.page
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp),
                    ) {
                        item { MoneyPathCard(p) }
                        item { AccountSlip(p.account, p.canEdit, onEdit = { editing = true }) }
                        item { WhoCanChange(p) }
                        item { Transfers(p.batches) }
                    }
                }
            }
            live?.let { u ->
                androidx.compose.runtime.key(u.id) {
                    Box(Modifier.align(Alignment.TopCenter).padding(top = 6.dp)) {
                        com.haraan.partner.updates.UpdateBanner(u, onOpen = { live = null }, onDismiss = { live = null })
                    }
                }
            }
        }
    }

    if (editing) {
        val current = (state as? Load.Ready)?.page?.account
        AccountSheet(
            api = api,
            token = token,
            current = current,
            onDismiss = { editing = false },
            onSaved = { editing = false; reload++ },
        )
    }
}

// ─── Where the money is ──────────────────────────────────────────────────────

/**
 * The money's road, drawn: the till where it was collected, Haraan holding it, the
 * bank it ends at. Coins sit at whichever stop they're actually at, and travel the
 * last stretch while a transfer is in flight. Each stop carries its real figure.
 */
@Composable
private fun MoneyPathCard(p: PayoutsPage) {
    val shown = remember { Animatable(0f) }
    LaunchedEffect(p.available) { shown.animateTo(p.available.toFloat(), tween(900, easing = FastOutSlowInEasing)) }
    val drop = remember { Animatable(0f) }
    LaunchedEffect(Unit) { delay(250); drop.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessLow)) }
    val travel = rememberInfiniteTransition(label = "travel")
    val t by travel.animateFloat(0f, 1f, infiniteRepeatable(tween(2600, easing = LinearEasing), RepeatMode.Restart), label = "t")
    val a = p.account
    val where = a?.masked

    Column(
        Modifier.fillMaxWidth().surface().padding(start = 18.dp, end = 18.dp, top = 18.dp, bottom = 16.dp),
    ) {
        Text("Waiting to be settled", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
        Text(
            // Same formatting as the stops below, so the two never disagree by a rupee.
            "₹" + formatInr(shown.value.toDouble()),
            fontSize = 36.sp, lineHeight = 40.sp, fontWeight = FontWeight.Bold, color = Ink,
            letterSpacing = (-1).sp, style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Text(
            when {
                a == null -> "Add where to send it. Nothing is transferred until you do."
                p.inFlight > 0 -> "₹" + formatInr(p.inFlight) + " is on its way to " + where + "."
                !a.verified -> "Haraan checks your account first, then sends it to $where."
                p.available > 0 -> "The next transfer goes to $where."
                else -> "Everything collected has been sent to $where."
            },
            fontSize = 13.5.sp, lineHeight = 19.sp, color = Ink2,
        )
        Spacer(Modifier.height(18.dp))

        val atHaraan = p.available > 0
        val moving = p.inFlight > 0
        Canvas(Modifier.fillMaxWidth().height(86.dp)) {
            val cy = size.height * 0.55f
            val xs = listOf(size.width / 6f, size.width / 2f, size.width * 5f / 6f)
            val r = 25.dp.toPx()
            // The road between the stops: walked (solid) up to where the money is, still to go (dashed) after.
            val sw = 2.dp.toPx()
            drawLine(Brand, Offset(xs[0] + r, cy), Offset(xs[1] - r, cy), sw, StrokeCap.Round)
            val reached = p.settled > 0 && !atHaraan && !moving
            drawLine(
                if (reached) Brand else Color(0xFFB9C7DE), Offset(xs[1] + r, cy), Offset(xs[2] - r, cy), sw, StrokeCap.Round,
                pathEffect = if (reached) null else PathEffect.dashPathEffect(floatArrayOf(6.dp.toPx(), 5.dp.toPx())),
            )
            // Stops, each on a soft disc; the one holding money is lit.
            val lit = when { moving -> 2; atHaraan -> 1; p.settled > 0 -> 2; else -> 0 }
            xs.forEachIndexed { i, x ->
                drawCircle(if (i == lit) BrandWash else Color(0xFFF3F5F9), r, Offset(x, cy))
                val ink = if (i == lit) BrandInk else Ink2
                when (i) {
                    0 -> till(Offset(x, cy), r * 0.62f, ink)
                    1 -> safe(Offset(x, cy), r * 0.6f, ink)
                    else -> bank(Offset(x, cy), r * 0.62f, ink)
                }
            }
            // Coins at Haraan, dropping onto the safe.
            if (atHaraan) {
                val n = when { p.available >= 5000 -> 3; p.available >= 500 -> 2; else -> 1 }
                for (k in 0 until n) {
                    val y = cy - r - 4.dp.toPx() - k * 5.dp.toPx() - (1f - drop.value) * 30.dp.toPx()
                    coin(Offset(xs[1] + r * 0.55f, y), 6.5.dp.toPx())
                }
            }
            // A coin on the road while a transfer is in flight.
            if (moving) {
                val x = xs[1] + r + (xs[2] - xs[1] - 2 * r) * t
                coin(Offset(x, cy - 9.dp.toPx() - kotlin.math.sin(t * Math.PI).toFloat() * 6.dp.toPx()), 6.dp.toPx())
            }
        }
        Spacer(Modifier.height(6.dp))
        Row(Modifier.fillMaxWidth()) {
            Stop("Collected", p.collected, Modifier.weight(1f))
            Stop(if (moving) "On the way" else "With Haraan", if (moving) p.inFlight else p.available, Modifier.weight(1f))
            Stop("In your bank", p.settled, Modifier.weight(1f))
        }
    }
}

@Composable
private fun Stop(label: String, amount: Double, modifier: Modifier) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text("₹" + formatInr(amount), fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
        Text(label, fontSize = 11.5.sp, color = Muted)
    }
}

/** A shop till: display on a stalk, the body, its cash drawer. */
private fun DrawScope.till(c: Offset, s: Float, ink: Color) {
    val st = Stroke(1.7.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    drawRoundRect(ink, Offset(c.x - s, c.y - s * 0.05f), Size(s * 2, s * 0.95f), CornerRadius(3.dp.toPx()), style = st)
    drawLine(ink, Offset(c.x - s, c.y + s * 0.42f), Offset(c.x + s, c.y + s * 0.42f), st.width)
    drawLine(ink, Offset(c.x - s * 0.18f, c.y + s * 0.66f), Offset(c.x + s * 0.18f, c.y + s * 0.66f), st.width)
    drawLine(ink, Offset(c.x - s * 0.35f, c.y - s * 0.05f), Offset(c.x - s * 0.35f, c.y - s * 0.4f), st.width)
    drawRoundRect(ink, Offset(c.x - s * 0.85f, c.y - s * 0.95f), Size(s * 1.1f, s * 0.55f), CornerRadius(2.dp.toPx()), style = st)
    drawLine(ink.copy(alpha = 0.45f), Offset(c.x - s * 0.65f, c.y - s * 0.68f), Offset(c.x - s * 0.05f, c.y - s * 0.68f), st.width)
}

/** A safe: the door, its dial, the handle. */
private fun DrawScope.safe(c: Offset, s: Float, ink: Color) {
    val st = Stroke(1.7.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    drawRoundRect(ink, Offset(c.x - s, c.y - s), Size(s * 2, s * 1.8f), CornerRadius(4.dp.toPx()), style = st)
    drawRoundRect(ink.copy(alpha = 0.45f), Offset(c.x - s * 0.72f, c.y - s * 0.72f), Size(s * 1.44f, s * 1.24f), CornerRadius(2.dp.toPx()), style = Stroke(1.2.dp.toPx()))
    drawCircle(ink, s * 0.32f, Offset(c.x - s * 0.08f, c.y - s * 0.1f), style = st)
    drawLine(ink, Offset(c.x - s * 0.08f, c.y - s * 0.1f), Offset(c.x - s * 0.08f, c.y - s * 0.32f), st.width)
    drawLine(ink, Offset(c.x + s * 0.48f, c.y - s * 0.3f), Offset(c.x + s * 0.48f, c.y + s * 0.1f), st.width)
    drawLine(ink, Offset(c.x - s * 0.7f, c.y + s * 0.8f), Offset(c.x - s * 0.7f, c.y + s * 1.0f), st.width)
    drawLine(ink, Offset(c.x + s * 0.7f, c.y + s * 0.8f), Offset(c.x + s * 0.7f, c.y + s * 1.0f), st.width)
}

/** A bank: pediment, three columns, the step. */
private fun DrawScope.bank(c: Offset, s: Float, ink: Color) {
    val st = Stroke(1.7.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val roof = Path().apply {
        moveTo(c.x - s * 1.05f, c.y - s * 0.38f); lineTo(c.x, c.y - s * 1.0f); lineTo(c.x + s * 1.05f, c.y - s * 0.38f); close()
    }
    drawPath(roof, ink.copy(alpha = 0.12f))
    drawPath(roof, ink, style = st)
    listOf(-0.62f, 0f, 0.62f).forEach { k ->
        drawLine(ink, Offset(c.x + s * k, c.y - s * 0.18f), Offset(c.x + s * k, c.y + s * 0.6f), st.width)
    }
    drawLine(ink, Offset(c.x - s * 1.05f, c.y + s * 0.85f), Offset(c.x + s * 1.05f, c.y + s * 0.85f), st.width)
}

private fun DrawScope.coin(c: Offset, r: Float) {
    drawOval(CoinEdge, Offset(c.x - r, c.y - r * 0.55f + 1.5.dp.toPx()), Size(r * 2, r * 1.1f))
    drawOval(Coin, Offset(c.x - r, c.y - r * 0.55f), Size(r * 2, r * 1.1f))
    drawOval(Color(0x66FFFFFF), Offset(c.x - r * 0.55f, c.y - r * 0.32f), Size(r * 1.1f, r * 0.5f), style = Stroke(1.dp.toPx()))
}

// ─── Where it goes ──────────────────────────────────────────────────────────

/**
 * The destination as a cheque leaf: fine guilloché lines, the handle or account in
 * large type, whose name it's in, and Haraan's stamp — a green VERIFIED stamp that
 * comes down once finance has checked it, an empty dashed one until then.
 */
@Composable
private fun AccountSlip(a: PayoutAccount?, canEdit: Boolean, onEdit: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    Column(
        Modifier
            .fillMaxWidth()
            .pressScale(interaction, pressedScale = if (canEdit) 0.985f else 1f)
            .surface()
            .clickable(interactionSource = interaction, indication = null, enabled = canEdit) { Haptics.tick(view); onEdit() },
    ) {
        if (a == null) {
            Column(Modifier.fillMaxWidth().padding(18.dp)) {
                Text("Money is sent to", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                Spacer(Modifier.height(10.dp))
                Box(
                    Modifier.fillMaxWidth().height(74.dp).drawBehind {
                        drawRoundRect(
                            Color(0xFFC6D2E6), style = Stroke(1.4.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(7.dp.toPx(), 5.dp.toPx()))),
                            cornerRadius = CornerRadius(12.dp.toPx()),
                        )
                    },
                    contentAlignment = Alignment.Center,
                ) { Text("No bank or UPI added yet", fontSize = 14.sp, color = Muted) }
                if (canEdit) {
                    Spacer(Modifier.height(14.dp))
                    BlueButton("Add bank or UPI", Modifier.fillMaxWidth()) { onEdit() }
                }
            }
            return@Column
        }
        Box(Modifier.fillMaxWidth().drawBehind { guilloche() }) {
            Row(Modifier.fillMaxWidth().padding(start = 18.dp, end = 14.dp, top = 16.dp, bottom = 14.dp)) {
                Column(Modifier.weight(1f)) {
                    Text(if (a.method == "upi") "UPI ID" else (a.bankName?.ifBlank { null } ?: "Bank account"), fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        if (a.method == "upi") a.masked else "•••• " + a.masked.takeLast(4),
                        fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink, letterSpacing = 0.4.sp,
                        maxLines = 1, overflow = TextOverflow.Ellipsis, style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Spacer(Modifier.height(2.dp))
                    Text("in the name of " + a.accountHolder.ifBlank { "—" }, fontSize = 13.sp, color = Ink2, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    if (a.method == "bank" && !a.ifsc.isNullOrBlank()) {
                        Text("IFSC " + a.ifsc, fontSize = 12.sp, color = Muted, style = TextStyle(fontFeatureSettings = "tnum"))
                    }
                }
                Stamp(verified = a.verified, date = shortDate(a.verifiedOn))
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
        Row(Modifier.fillMaxWidth().padding(start = 18.dp, end = 10.dp, top = 6.dp, bottom = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(
                listOfNotNull(
                    a.changedBy?.let { "Changed by $it" + (if (a.changedKind == "manager") " (Haraan)" else "") },
                    shortDate(a.changedAt),
                ).joinToString(" · ").ifBlank { if (a.verified) "Checked by Haraan finance" else "Haraan hasn't checked it yet" },
                fontSize = 12.5.sp, color = Muted, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            if (canEdit) {
                Text("Change", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = BrandInk, modifier = Modifier.padding(horizontal = 10.dp, vertical = 10.dp))
            }
        }
    }
}

/** Fine wavy lines across the leaf, like a cheque's security print. */
private fun DrawScope.guilloche() {
    val c = Color(0x0D2563EB)
    val sw = 0.8.dp.toPx()
    val amp = 5.dp.toPx()
    val step = 9.dp.toPx()
    var y = 6.dp.toPx()
    var row = 0
    while (y < size.height) {
        val path = Path()
        var x = 0f
        path.moveTo(0f, y)
        while (x <= size.width) {
            path.lineTo(x, y + amp * kotlin.math.sin((x / (34.dp.toPx())) + row * 0.7f))
            x += 4.dp.toPx()
        }
        drawPath(path, c, style = Stroke(sw))
        y += step
        row++
    }
}

/**
 * Haraan's stamp. Verified: green rings and VERIFIED with the date, inked at a tilt,
 * coming down with a thump the first time it's seen. Not yet: an empty dashed ring.
 */
@Composable
private fun Stamp(verified: Boolean, date: String?) {
    val view = LocalView.current
    val press = remember { Animatable(if (verified) 0f else 1f) }
    LaunchedEffect(verified) {
        if (verified) {
            delay(420)
            press.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = Spring.StiffnessMedium))
            Haptics.confirm(view)
        }
    }
    Box(
        Modifier.size(78.dp).graphicsLayer {
            val sc = 1.6f - 0.6f * press.value
            scaleX = sc; scaleY = sc
            alpha = press.value.coerceIn(0f, 1f)
        }.rotate(if (verified) -12f else 0f),
        contentAlignment = Alignment.Center,
    ) {
        Canvas(Modifier.matchParentSize()) {
            val r = size.minDimension / 2 - 2.dp.toPx()
            if (verified) {
                drawCircle(Good.copy(alpha = 0.85f), r, style = Stroke(2.2.dp.toPx()))
                drawCircle(Good.copy(alpha = 0.6f), r - 4.dp.toPx(), style = Stroke(1.dp.toPx()))
            } else {
                drawCircle(Color(0xFFC6D2E6), r, style = Stroke(1.4.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(5.dp.toPx(), 4.dp.toPx()))))
            }
        }
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            if (verified) {
                Text("VERIFIED", fontSize = 11.sp, fontWeight = FontWeight.Black, color = Good.copy(alpha = 0.9f), letterSpacing = 0.8.sp)
                date?.let { Text(it, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, color = Good.copy(alpha = 0.8f)) }
            } else {
                Text("Not yet\nchecked", fontSize = 10.5.sp, lineHeight = 12.sp, fontWeight = FontWeight.SemiBold, color = Faint, textAlign = TextAlign.Center)
            }
        }
    }
}

// ─── Who can change it ──────────────────────────────────────────────────────

private enum class Can(val label: String) { See("See"), Change("Change"), Verify("Verify") }

/**
 * Who holds this account, as an access table: each person, their role, and a drawn
 * tick under See / Change / Verify for exactly what they're allowed. Only finance
 * verifies, so a manager who edits it can never also vouch for it. The manager can be
 * messaged from here, and the strip under the table says what happens when someone
 * other than the owner changes it.
 */
@Composable
private fun WhoCanChange(p: PayoutsPage) {
    val context = LocalContext.current
    val view = LocalView.current
    Column(Modifier.fillMaxWidth().surface().padding(start = 18.dp, end = 12.dp, top = 16.dp, bottom = 14.dp)) {
        Text("Who can see and change this", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(12.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Spacer(Modifier.weight(1f))
            Can.entries.forEach {
                Text(it.label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = Faint, textAlign = TextAlign.Center, modifier = Modifier.width(CellW))
            }
        }
        Spacer(Modifier.height(2.dp))
        AccessRow(
            face = { Initial(if (p.canEdit) "You" else "O") },
            name = if (p.canEdit) "You" else "The venue owner",
            role = if (p.canEdit) "Venue owner" else "Only they can change it",
            can = setOf(Can.See, Can.Change),
        )
        p.manager?.let { m ->
            Box(Modifier.padding(start = 50.dp).fillMaxWidth().height(1.dp).background(Hair))
            val wa = m.whatsapp && !m.phone.isNullOrBlank()
            AccessRow(
                face = {
                    if (!m.photoUrl.isNullOrBlank()) {
                        AsyncImage(m.photoUrl, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(38.dp).clip(RoundedCornerShape(99.dp)))
                    } else Initial(m.name)
                },
                name = displayName(m.name),
                role = m.title,
                can = setOf(Can.See, Can.Change),
                action = if (wa) "WhatsApp" else null,
                onAction = {
                    Haptics.tick(view)
                    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/" + m.phone!!.filter { it.isDigit() }))) }
                },
            )
        }
        Box(Modifier.padding(start = 50.dp).fillMaxWidth().height(1.dp).background(Hair))
        AccessRow(
            face = {
                Box(Modifier.size(38.dp).clip(RoundedCornerShape(99.dp)).background(BrandWash), contentAlignment = Alignment.Center) {
                    Canvas(Modifier.size(20.dp)) { shieldTick(BrandInk) }
                }
            },
            name = "Haraan finance",
            role = "Checks it before money is sent",
            can = Can.entries.toSet(),
        )
        Spacer(Modifier.height(10.dp))
        Row(
            Modifier.padding(end = 6.dp).fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Color(0xFFF4F7FD)).padding(12.dp),
            verticalAlignment = Alignment.Top,
        ) {
            Canvas(Modifier.padding(top = 1.dp).size(16.dp)) { bell(BrandInk) }
            Spacer(Modifier.width(10.dp))
            Text(
                "Changed by someone else? You get a notification right away, and Haraan checks it again before the next transfer.",
                fontSize = 12.5.sp, lineHeight = 17.sp, color = Ink2,
            )
        }
    }
}

private val CellW = 46.dp

@Composable
private fun AccessRow(
    face: @Composable () -> Unit,
    name: String,
    role: String,
    can: Set<Can>,
    action: String? = null,
    onAction: () -> Unit = {},
) {
    Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.Top) {
        face()
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f).padding(top = 1.dp)) {
            Text(name, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 18.sp)
            Text(role, fontSize = 12.5.sp, color = Muted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 16.sp)
            if (action != null) {
                Spacer(Modifier.height(6.dp))
                val interaction = remember { MutableInteractionSource() }
                Row(
                    Modifier
                        .pressScale(interaction, pressedScale = 0.95f)
                        .clip(RoundedCornerShape(10.dp))
                        .border(1.dp, Color(0x332563EB), RoundedCornerShape(10.dp))
                        .clickable(interactionSource = interaction, indication = null) { onAction() }
                        .padding(start = 8.dp, end = 10.dp, top = 6.dp, bottom = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Canvas(Modifier.size(15.dp)) { chatBubble(BrandInk) }
                    Spacer(Modifier.width(6.dp))
                    Text(action, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = BrandInk, maxLines = 1, softWrap = false)
                }
            }
        }
        Can.entries.forEach { c ->
            Box(Modifier.width(CellW).height(38.dp), contentAlignment = Alignment.Center) {
                Canvas(Modifier.size(20.dp)) { if (c in can) allowed() else notAllowed() }
            }
        }
    }
}

/** A blue tick on a soft disc: this person can. */
private fun DrawScope.allowed() {
    drawCircle(BrandWash)
    val w = size.width
    val tick = Path().apply { moveTo(w * 0.3f, w * 0.52f); lineTo(w * 0.45f, w * 0.66f); lineTo(w * 0.72f, w * 0.36f) }
    drawPath(tick, Brand, style = Stroke(1.8.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
}

/** A short faint dash: this person can't. */
private fun DrawScope.notAllowed() {
    drawLine(Color(0xFFCBD5E1), Offset(size.width * 0.36f, center.y), Offset(size.width * 0.64f, center.y), 1.8.dp.toPx(), StrokeCap.Round)
}

/** A speech bubble with its tail, for messaging the manager. */
private fun DrawScope.chatBubble(ink: Color) {
    val w = size.width
    val h = size.height
    val b = Path().apply {
        moveTo(w * 0.2f, h * 0.15f); lineTo(w * 0.8f, h * 0.15f)
        quadraticTo(w * 0.95f, h * 0.15f, w * 0.95f, h * 0.3f); lineTo(w * 0.95f, h * 0.6f)
        quadraticTo(w * 0.95f, h * 0.75f, w * 0.8f, h * 0.75f); lineTo(w * 0.42f, h * 0.75f)
        lineTo(w * 0.22f, h * 0.92f); lineTo(w * 0.24f, h * 0.75f); lineTo(w * 0.2f, h * 0.75f)
        quadraticTo(w * 0.05f, h * 0.75f, w * 0.05f, h * 0.6f); lineTo(w * 0.05f, h * 0.3f)
        quadraticTo(w * 0.05f, h * 0.15f, w * 0.2f, h * 0.15f); close()
    }
    drawPath(b, ink, style = Stroke(1.5.dp.toPx(), join = StrokeJoin.Round))
    listOf(0.32f, 0.5f, 0.68f).forEach { drawCircle(ink, 1.dp.toPx(), Offset(w * it, h * 0.45f)) }
}

/** "HARIHARAN REDDY" → "Hariharan Reddy"; a name typed with care is left as it is. */
private fun displayName(s: String): String =
    if (s.any { it.isLetter() } && s == s.uppercase()) s.lowercase().split(' ').joinToString(" ") { w -> w.replaceFirstChar { it.uppercase() } } else s

@Composable
private fun Initial(name: String) {
    Box(Modifier.size(38.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFE8EDF5)), contentAlignment = Alignment.Center) {
        Text(
            if (name == "You") "You" else name.trim().take(1).uppercase(),
            fontSize = if (name == "You") 11.sp else 15.sp, fontWeight = FontWeight.Bold, color = Ink2,
        )
    }
}

private fun DrawScope.shieldTick(ink: Color) {
    val w = size.width
    val h = size.height
    val st = Stroke(1.6.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val shield = Path().apply {
        moveTo(w * 0.5f, h * 0.06f); lineTo(w * 0.88f, h * 0.2f); lineTo(w * 0.84f, h * 0.55f)
        quadraticTo(w * 0.78f, h * 0.82f, w * 0.5f, h * 0.95f)
        quadraticTo(w * 0.22f, h * 0.82f, w * 0.16f, h * 0.55f); lineTo(w * 0.12f, h * 0.2f); close()
    }
    drawPath(shield, ink.copy(alpha = 0.14f))
    drawPath(shield, ink, style = st)
    val tick = Path().apply { moveTo(w * 0.34f, h * 0.5f); lineTo(w * 0.46f, h * 0.62f); lineTo(w * 0.68f, h * 0.38f) }
    drawPath(tick, ink, style = st)
}

private fun DrawScope.bell(ink: Color) {
    val w = size.width
    val h = size.height
    val st = Stroke(1.3.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val b = Path().apply {
        moveTo(w * 0.2f, h * 0.72f); quadraticTo(w * 0.28f, h * 0.6f, w * 0.28f, h * 0.42f)
        quadraticTo(w * 0.3f, h * 0.14f, w * 0.5f, h * 0.14f); quadraticTo(w * 0.7f, h * 0.14f, w * 0.72f, h * 0.42f)
        quadraticTo(w * 0.72f, h * 0.6f, w * 0.8f, h * 0.72f); close()
    }
    drawPath(b, ink, style = st)
    drawLine(ink, Offset(w * 0.42f, h * 0.86f), Offset(w * 0.58f, h * 0.86f), st.width, StrokeCap.Round)
}

// ─── Transfers ──────────────────────────────────────────────────────────────

/** Every transfer, passbook-style: the date, the amount, the bank reference, the outcome. */
@Composable
private fun Transfers(batches: List<PayoutBatchRow>) {
    Column(Modifier.fillMaxWidth().surface()) {
        Row(Modifier.padding(start = 18.dp, end = 18.dp, top = 14.dp, bottom = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Transfers", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
            if (batches.isNotEmpty()) Text("${batches.size}", fontSize = 13.sp, color = Muted)
        }
        if (batches.isEmpty()) {
            Row(Modifier.padding(start = 18.dp, end = 18.dp, top = 6.dp, bottom = 18.dp), verticalAlignment = Alignment.CenterVertically) {
                Canvas(Modifier.size(width = 64.dp, height = 48.dp)) { passbook() }
                Spacer(Modifier.width(14.dp))
                Text(
                    "No transfers yet. Each one shows up here with its date and bank reference (UTR).",
                    fontSize = 13.sp, lineHeight = 18.sp, color = Muted,
                )
            }
        } else {
            batches.forEachIndexed { i, b ->
                if (i > 0) Box(Modifier.padding(start = 76.dp).fillMaxWidth().height(1.dp).background(Hair))
                TransferRow(b)
            }
            Spacer(Modifier.height(6.dp))
        }
    }
}

@Composable
private fun TransferRow(b: PayoutBatchRow) {
    val (day, month) = dayMonth(b.date)
    val failed = b.status.lowercase().startsWith("fail")
    val tone = when { b.isPaid -> Good; failed -> Bad; else -> Wait }
    Row(Modifier.fillMaxWidth().padding(horizontal = 18.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.width(44.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(day, fontSize = 19.sp, lineHeight = 21.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
            Text(month, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = Muted)
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text("₹" + formatInr(b.amount), fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
            Text(
                listOfNotNull(b.reference?.takeIf { it.isNotBlank() }?.let { "UTR $it" }, b.period).joinToString(" · ").ifBlank { "Reference comes with the transfer" },
                fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Canvas(Modifier.size(14.dp)) {
                val st = Stroke(1.7.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
                when {
                    b.isPaid -> drawPath(Path().apply { moveTo(size.width * 0.15f, size.height * 0.55f); lineTo(size.width * 0.42f, size.height * 0.8f); lineTo(size.width * 0.88f, size.height * 0.22f) }, tone, style = st)
                    failed -> {
                        drawLine(tone, Offset(size.width * 0.2f, size.height * 0.2f), Offset(size.width * 0.8f, size.height * 0.8f), st.width, StrokeCap.Round)
                        drawLine(tone, Offset(size.width * 0.8f, size.height * 0.2f), Offset(size.width * 0.2f, size.height * 0.8f), st.width, StrokeCap.Round)
                    }
                    else -> {
                        drawCircle(tone, size.minDimension * 0.42f, style = st)
                        drawLine(tone, center, Offset(center.x, size.height * 0.24f), st.width, StrokeCap.Round)
                        drawLine(tone, center, Offset(size.width * 0.72f, center.y), st.width, StrokeCap.Round)
                    }
                }
            }
            Spacer(Modifier.width(5.dp))
            Text(
                when { b.isPaid -> "Paid"; failed -> "Failed"; else -> "Sending" },
                fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = tone,
            )
        }
    }
}

/** An open passbook with ruled lines, the first row waiting for an entry. */
private fun DrawScope.passbook() {
    val ink = Color(0xFF94A3B8)
    val st = Stroke(1.5.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val w = size.width
    val h = size.height
    val spine = w / 2
    val left = Path().apply { moveTo(spine, h * 0.14f); quadraticTo(w * 0.28f, h * 0.04f, w * 0.04f, h * 0.1f); lineTo(w * 0.04f, h * 0.9f); quadraticTo(w * 0.28f, h * 0.84f, spine, h * 0.94f); close() }
    val right = Path().apply { moveTo(spine, h * 0.14f); quadraticTo(w * 0.72f, h * 0.04f, w * 0.96f, h * 0.1f); lineTo(w * 0.96f, h * 0.9f); quadraticTo(w * 0.72f, h * 0.84f, spine, h * 0.94f); close() }
    drawPath(left, Color(0xFFF8FAFC)); drawPath(right, Color(0xFFF8FAFC))
    drawPath(left, ink, style = st); drawPath(right, ink, style = st)
    for (k in 0 until 4) {
        val y = h * (0.32f + k * 0.15f)
        drawLine(ink.copy(alpha = 0.5f), Offset(w * 0.1f, y), Offset(spine - w * 0.06f, y), 1.dp.toPx())
        drawLine(if (k == 0) Brand else ink.copy(alpha = 0.5f), Offset(spine + w * 0.06f, y), Offset(w * 0.9f, y), if (k == 0) 1.8.dp.toPx() else 1.dp.toPx(), StrokeCap.Round)
    }
}

// ─── Changing it ────────────────────────────────────────────────────────────

/**
 * Where should the money go: bank or UPI, the details, and a live leaf that fills in
 * as you type so you see exactly what Haraan will check. Never prefilled — changing
 * the destination means entering it in full.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AccountSheet(api: PartnerApi, token: String, current: PayoutAccount?, onDismiss: () -> Unit, onSaved: () -> Unit) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    var method by remember { mutableStateOf(current?.method ?: "upi") }
    var holder by remember { mutableStateOf("") }
    var bankName by remember { mutableStateOf("") }
    var acct by remember { mutableStateOf("") }
    var ifsc by remember { mutableStateOf("") }
    var vpa by remember { mutableStateOf("") }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val vpaOk = Regex("^[\\w.\\-]{2,}@[a-zA-Z]{2,}$").matches(vpa.trim())
    val ifscOk = Regex("^[A-Z]{4}0[A-Z0-9]{6}$").matches(ifsc)
    val valid = holder.trim().length >= 2 && if (method == "bank") acct.length in 6..18 && ifscOk && bankName.isNotBlank() else vpaOk
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = Brand, focusedLabelColor = Brand, cursorColor = Brand, unfocusedBorderColor = Color(0x1F0F172A),
    )

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Color.White) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).imePadding().navigationBarsPadding()
                .padding(start = 20.dp, end = 20.dp, bottom = 20.dp),
        ) {
            Text("Where should we send your money?", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Ink)
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                MethodTile("upi", "UPI ID", method == "upi", Modifier.weight(1f)) { method = "upi"; Haptics.tick(view) }
                MethodTile("bank", "Bank account", method == "bank", Modifier.weight(1f)) { method = "bank"; Haptics.tick(view) }
            }
            Spacer(Modifier.height(14.dp))
            // The leaf, filling in as they type.
            LeafPreview(
                method = method,
                big = if (method == "upi") vpa.trim().ifBlank { "name@bank" } else acct.chunked(4).joinToString(" ").ifBlank { "Account number" },
                holder = holder.trim(),
                small = if (method == "bank") listOf(bankName.trim(), ifsc).filter { it.isNotBlank() }.joinToString(" · ") else null,
                filled = if (method == "upi") vpa.isNotBlank() else acct.isNotBlank(),
            )
            Spacer(Modifier.height(14.dp))
            OutlinedTextField(
                holder, { holder = it.take(120) }, label = { Text("Name on the account") }, singleLine = true,
                shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
            )
            if (method == "upi") {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    vpa, { vpa = it.trim().lowercase().take(120) }, label = { Text("UPI ID") }, placeholder = { Text("name@okhdfc") }, singleLine = true,
                    isError = vpa.isNotBlank() && !vpaOk, supportingText = if (vpa.isNotBlank() && !vpaOk) ({ Text("Looks like name@bank") }) else null,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                )
            } else {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    bankName, { bankName = it.take(120) }, label = { Text("Bank name") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    acct, { acct = it.filter { c -> c.isDigit() }.take(18) }, label = { Text("Account number") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    ifsc, { ifsc = it.uppercase().filter { c -> c.isLetterOrDigit() }.take(11) }, label = { Text("IFSC") }, placeholder = { Text("HDFC0001234") }, singleLine = true,
                    isError = ifsc.length == 11 && !ifscOk, supportingText = if (ifsc.length == 11 && !ifscOk) ({ Text("4 letters, a 0, then 6 letters or digits") }) else null,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters),
                )
            }
            Spacer(Modifier.height(12.dp))
            Text(
                "Saving asks Haraan finance to check it again before the next transfer. Your Haraan manager can see these details to help you.",
                fontSize = 12.5.sp, lineHeight = 17.sp, color = Muted,
            )
            error?.let {
                Spacer(Modifier.height(8.dp))
                Text(it, fontSize = 13.sp, color = Bad, fontWeight = FontWeight.SemiBold)
            }
            Spacer(Modifier.height(16.dp))
            BlueButton(if (saving) "Saving…" else "Save account", Modifier.fillMaxWidth(), enabled = valid && !saving) {
                saving = true
                error = null
                scope.launch {
                    try {
                        api.savePayoutAccount(
                            token, method, holder.trim(),
                            bankName.trim().ifBlank { null }, acct.ifBlank { null }, ifsc.ifBlank { null }, vpa.trim().ifBlank { null },
                        )
                        Haptics.confirm(view)
                        onSaved()
                    } catch (e: Exception) {
                        Haptics.reject(view)
                        error = e.message?.takeIf { it.isNotBlank() && !it.startsWith("{") } ?: "Couldn't save. Check the details and try again."
                        saving = false
                    }
                }
            }
        }
    }
}

@Composable
private fun MethodTile(key: String, label: String, on: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        modifier
            .pressScale(interaction, pressedScale = 0.96f)
            .clip(RoundedCornerShape(14.dp))
            .background(if (on) BrandWash else Color(0xFFF8FAFC))
            .border(if (on) 1.5.dp else 1.dp, if (on) Brand else Color(0x1A0F172A), RoundedCornerShape(14.dp))
            .clickable(interactionSource = interaction, indication = null) { onClick() }
            .padding(horizontal = 12.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(24.dp)) {
            val ink = if (on) BrandInk else Ink2
            if (key == "bank") bank(center, size.minDimension * 0.42f, ink) else phoneQr(ink)
        }
        Spacer(Modifier.width(10.dp))
        Text(label, fontSize = 14.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.SemiBold, color = if (on) BrandInk else Ink)
    }
}

/** A phone with a QR on its screen: paying by UPI. */
private fun DrawScope.phoneQr(ink: Color) {
    val w = size.width
    val h = size.height
    val st = Stroke(1.6.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    drawRoundRect(ink, Offset(w * 0.24f, h * 0.06f), Size(w * 0.52f, h * 0.88f), CornerRadius(3.dp.toPx()), style = st)
    val q = w * 0.11f
    listOf(Offset(w * 0.34f, h * 0.24f), Offset(w * 0.53f, h * 0.24f), Offset(w * 0.34f, h * 0.45f)).forEach { drawRect(ink, it, Size(q, q), style = Stroke(1.2.dp.toPx())) }
    drawRect(ink, Offset(w * 0.55f, h * 0.47f), Size(q * 0.7f, q * 0.7f))
    drawLine(ink, Offset(w * 0.44f, h * 0.82f), Offset(w * 0.56f, h * 0.82f), st.width, StrokeCap.Round)
}

@Composable
private fun LeafPreview(method: String, big: String, holder: String, small: String?, filled: Boolean) {
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0xFFFBFCFE))
            .border(1.dp, Hair, RoundedCornerShape(14.dp)).drawBehind { guilloche() }.padding(14.dp),
    ) {
        Text(if (method == "upi") "UPI ID" else "Bank account", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Muted)
        Text(big, fontSize = 19.sp, fontWeight = FontWeight.Bold, color = if (filled) Ink else Faint, maxLines = 1, overflow = TextOverflow.Ellipsis, style = TextStyle(fontFeatureSettings = "tnum"))
        Text(if (holder.isBlank()) "in the name of …" else "in the name of $holder", fontSize = 12.5.sp, color = if (holder.isBlank()) Faint else Ink2, maxLines = 1, overflow = TextOverflow.Ellipsis)
        small?.takeIf { it.isNotBlank() }?.let { Text(it, fontSize = 12.sp, color = Muted) }
    }
}

// ─── Bits ───────────────────────────────────────────────────────────────────

private fun Modifier.surface(): Modifier = this
    .shadow(6.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x140F172A), ambientColor = Color(0x0A0F172A))
    .clip(RoundedCornerShape(18.dp))
    .background(Color.White)
    .border(1.dp, Hair, RoundedCornerShape(18.dp))

@Composable
private fun BlueButton(text: String, modifier: Modifier = Modifier, enabled: Boolean = true, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Box(
        modifier
            .pressScale(interaction, pressedScale = 0.97f)
            .clip(RoundedCornerShape(14.dp))
            .background(if (enabled) Brand else Color(0xFFBFD0F5))
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) { onClick() }
            .padding(horizontal = 20.dp, vertical = 14.dp),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color.White) }
}

/** ISO date-time or yyyy-MM-dd → "3 Oct". */
private fun shortDate(iso: String?): String? {
    if (iso.isNullOrBlank()) return null
    val d = iso.take(10).split("-")
    if (d.size != 3) return null
    val months = listOf("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")
    val m = d[1].toIntOrNull()?.let { months.getOrNull(it - 1) } ?: return null
    return (d[2].toIntOrNull() ?: return null).toString() + " " + m
}

private fun dayMonth(iso: String?): Pair<String, String> {
    val s = shortDate(iso) ?: return "—" to ""
    return s.substringBefore(' ') to s.substringAfter(' ')
}
