package com.haraan.partner.updates

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectVerticalDragGestures
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
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
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.PartnerUpdateItem
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Ink = Color(0xFF0F172A)
private val Ink2 = Color(0xFF334155)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hair = Color(0x140F172A)
private val Brand = Color(0xFF2563EB)

/** What an update is about, and the colour it speaks in. */
private enum class Tone(val ink: Color, val wash: Color) {
    Good(Color(0xFF15803D), Color(0xFFE7F6EC)),
    Warn(Color(0xFFB45309), Color(0xFFFDF3E2)),
    Bad(Color(0xFFDC2626), Color(0xFFFDECEC)),
    Info(Color(0xFF1D4ED8), Color(0xFFEEF3FE)),
}

private fun toneOf(u: PartnerUpdateItem): Tone = when {
    u.kind == "payout_batch.paid" || u.kind == "payout_account.verified" -> Tone.Good
    u.kind == "payout_batch.failed" -> Tone.Bad
    u.kind == "account.status" && u.title.contains("suspended", true) -> Tone.Bad
    u.kind == "account.status" -> Tone.Good
    u.kind == "payout_account.changed" || u.kind == "payout_account.unverified" -> Tone.Warn
    else -> Tone.Info
}

/** The buzz an update arrives with: good news confirms, bad news warns, the rest ticks. */
fun hapticFor(view: android.view.View, u: PartnerUpdateItem) = when (toneOf(u)) {
    Tone.Good -> Haptics.confirm(view)
    Tone.Bad, Tone.Warn -> Haptics.warn(view)
    Tone.Info -> Haptics.tick(view)
}

/**
 * An update dropping in from the top while the app is open: what changed, in Haraan's
 * own words, who did it, and a tap to open the screen it's about. Flick it up to
 * dismiss; it leaves by itself after a few seconds.
 */
@Composable
fun UpdateBanner(u: PartnerUpdateItem, onOpen: () -> Unit, onDismiss: () -> Unit) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val drop = remember(u.id) { Animatable(-160f) }
    val drag = remember(u.id) { Animatable(0f) }
    LaunchedEffect(u.id) {
        hapticFor(view, u)
        drop.animateTo(0f, spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessMediumLow))
        delay(7_000)
        drop.animateTo(-200f, tween(220))
        onDismiss()
    }
    val tone = toneOf(u)
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp)
            .graphicsLayer {
                translationY = (drop.value + drag.value.coerceAtMost(0f)) * density
                alpha = (1f + drag.value / 160f).coerceIn(0f, 1f)
            }
            .pointerInput(u.id) {
                detectVerticalDragGestures(
                    onDragEnd = {
                        scope.launch {
                            if (drag.value < -40f) { drag.animateTo(-220f, tween(160)); onDismiss() } else drag.animateTo(0f, spring())
                        }
                    },
                ) { change, dy -> change.consume(); scope.launch { drag.snapTo((drag.value + dy / density).coerceAtMost(0f)) } }
            }
            .pressScale(interaction, pressedScale = 0.98f)
            .shadow(16.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x330F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(18.dp))
            .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onOpen() }
            .padding(14.dp),
        verticalAlignment = Alignment.Top,
    ) {
        KindMark(u, tone, 40)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(u.title, fontSize = 14.5.sp, lineHeight = 19.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 2, overflow = TextOverflow.Ellipsis)
            u.body?.let { Text(it, fontSize = 13.sp, lineHeight = 18.sp, color = Ink2, maxLines = 3, overflow = TextOverflow.Ellipsis) }
            Spacer(Modifier.height(4.dp))
            Text(listOfNotNull(u.by?.let { "By $it" }, "just now").joinToString(" · "), fontSize = 11.5.sp, color = Faint, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

/**
 * Behind the bell: new bookings (if any) on top, then every update Haraan made to the
 * account, newest first, each opening the screen it's about.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UpdatesSheet(
    items: List<PartnerUpdateItem>,
    newBookings: Int,
    onBookings: () -> Unit,
    onOpen: (PartnerUpdateItem) -> Unit,
    onDismiss: () -> Unit,
) {
    val view = LocalView.current
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = false), containerColor = Color.White) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding()) {
            Text("Updates", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.padding(start = 20.dp, end = 20.dp, bottom = 4.dp))
            Text(
                "What Haraan changed on your account, as it happens.",
                fontSize = 13.sp, color = Muted, modifier = Modifier.padding(start = 20.dp, end = 20.dp, bottom = 10.dp),
            )
            if (newBookings > 0) {
                val interaction = remember { MutableInteractionSource() }
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 4.dp)
                        .pressScale(interaction, pressedScale = 0.98f)
                        .clip(RoundedCornerShape(14.dp)).background(Color(0xFFEEF3FE))
                        .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onBookings() }
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(Modifier.size(36.dp).clip(RoundedCornerShape(99.dp)).background(Brand), contentAlignment = Alignment.Center) {
                        Text("$newBookings", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    }
                    Spacer(Modifier.width(12.dp))
                    Text(if (newBookings == 1) "New booking" else "$newBookings new bookings", fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF1D4ED8), modifier = Modifier.weight(1f))
                    Text("Open", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF1D4ED8))
                }
            }
            if (items.isEmpty()) {
                Column(Modifier.fillMaxWidth().padding(vertical = 34.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Canvas(Modifier.size(54.dp)) { quietBell() }
                    Spacer(Modifier.height(10.dp))
                    Text("Nothing yet", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink)
                    Text("Settlements, account checks and venue changes show up here.", fontSize = 13.sp, color = Muted, modifier = Modifier.padding(horizontal = 32.dp))
                }
            } else {
                LazyColumn(Modifier.fillMaxWidth().heightIn(max = 520.dp)) {
                    items(items, key = { it.id }) { u ->
                        val interaction = remember { MutableInteractionSource() }
                        Row(
                            Modifier.fillMaxWidth()
                                .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onOpen(u) }
                                .padding(horizontal = 20.dp, vertical = 12.dp),
                            verticalAlignment = Alignment.Top,
                        ) {
                            KindMark(u, toneOf(u), 36)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(u.title, fontSize = 14.sp, fontWeight = if (u.seen) FontWeight.SemiBold else FontWeight.Bold, color = Ink, maxLines = 2, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                                    if (!u.seen) {
                                        Spacer(Modifier.width(8.dp))
                                        Box(Modifier.size(8.dp).clip(RoundedCornerShape(99.dp)).background(Brand))
                                    }
                                }
                                u.body?.let { Text(it, fontSize = 12.5.sp, lineHeight = 17.sp, color = Ink2, maxLines = 3, overflow = TextOverflow.Ellipsis) }
                                Text(listOfNotNull(u.by, ago(u.at)).joinToString(" · "), fontSize = 11.5.sp, color = Faint, maxLines = 1)
                            }
                        }
                        Box(Modifier.padding(start = 68.dp).fillMaxWidth().height(1.dp).background(Hair))
                    }
                }
            }
            Spacer(Modifier.height(12.dp))
        }
    }
}

/** The update's own little picture, on a disc in its tone. */
@Composable
private fun KindMark(u: PartnerUpdateItem, tone: Tone, sizeDp: Int) {
    Box(Modifier.size(sizeDp.dp).clip(RoundedCornerShape(99.dp)).background(tone.wash), contentAlignment = Alignment.Center) {
        Canvas(Modifier.size((sizeDp * 0.55f).dp)) {
            when {
                u.kind.startsWith("payout_batch") -> coin(tone.ink, u.kind == "payout_batch.paid", u.kind == "payout_batch.failed")
                u.kind.startsWith("payout_account") -> shield(tone.ink, u.kind == "payout_account.verified")
                u.kind.startsWith("venue") -> court(tone.ink)
                u.kind == "plan.changed" -> star(tone.ink)
                else -> person(tone.ink)
            }
        }
    }
}

private fun DrawScope.st() = Stroke(1.7.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)

/** A rupee coin; a tick when it landed, a cross when it bounced. */
private fun DrawScope.coin(ink: Color, paid: Boolean, failed: Boolean) {
    val w = size.width
    drawCircle(ink.copy(alpha = 0.14f), w * 0.42f, Offset(w * 0.5f, w * 0.5f))
    drawCircle(ink, w * 0.42f, Offset(w * 0.5f, w * 0.5f), style = st())
    val r = Path().apply {
        moveTo(w * 0.36f, w * 0.34f); lineTo(w * 0.64f, w * 0.34f)
        moveTo(w * 0.36f, w * 0.44f); lineTo(w * 0.64f, w * 0.44f)
        moveTo(w * 0.42f, w * 0.34f); quadraticTo(w * 0.62f, w * 0.36f, w * 0.56f, w * 0.48f); lineTo(w * 0.4f, w * 0.48f); lineTo(w * 0.6f, w * 0.68f)
    }
    drawPath(r, ink, style = Stroke(1.4.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round))
    if (paid || failed) {
        val c = Offset(w * 0.84f, w * 0.84f)
        drawCircle(Color.White, w * 0.2f, c)
        drawCircle(ink, w * 0.17f, c)
        val s = Stroke(1.3.dp.toPx(), cap = StrokeCap.Round)
        if (paid) drawPath(Path().apply { moveTo(c.x - w * 0.08f, c.y); lineTo(c.x - w * 0.02f, c.y + w * 0.06f); lineTo(c.x + w * 0.08f, c.y - w * 0.06f) }, Color.White, style = s)
        else {
            drawLine(Color.White, Offset(c.x - w * 0.06f, c.y - w * 0.06f), Offset(c.x + w * 0.06f, c.y + w * 0.06f), s.width, StrokeCap.Round)
            drawLine(Color.White, Offset(c.x + w * 0.06f, c.y - w * 0.06f), Offset(c.x - w * 0.06f, c.y + w * 0.06f), s.width, StrokeCap.Round)
        }
    }
}

private fun DrawScope.shield(ink: Color, tick: Boolean) {
    val w = size.width
    val p = Path().apply {
        moveTo(w * 0.5f, w * 0.06f); lineTo(w * 0.88f, w * 0.2f); lineTo(w * 0.84f, w * 0.55f)
        quadraticTo(w * 0.78f, w * 0.82f, w * 0.5f, w * 0.95f); quadraticTo(w * 0.22f, w * 0.82f, w * 0.16f, w * 0.55f); lineTo(w * 0.12f, w * 0.2f); close()
    }
    drawPath(p, ink.copy(alpha = 0.14f)); drawPath(p, ink, style = st())
    if (tick) drawPath(Path().apply { moveTo(w * 0.34f, w * 0.5f); lineTo(w * 0.46f, w * 0.62f); lineTo(w * 0.68f, w * 0.38f) }, ink, style = st())
    else drawLine(ink, Offset(w * 0.5f, w * 0.32f), Offset(w * 0.5f, w * 0.56f), st().width, StrokeCap.Round).also { drawCircle(ink, 1.3.dp.toPx(), Offset(w * 0.5f, w * 0.7f)) }
}

/** A court from above: outline, net, service lines. */
private fun DrawScope.court(ink: Color) {
    val w = size.width
    drawRoundRect(ink.copy(alpha = 0.14f), Offset(w * 0.18f, w * 0.06f), Size(w * 0.64f, w * 0.88f), CornerRadius(2.dp.toPx()))
    drawRoundRect(ink, Offset(w * 0.18f, w * 0.06f), Size(w * 0.64f, w * 0.88f), CornerRadius(2.dp.toPx()), style = st())
    drawLine(ink, Offset(w * 0.18f, w * 0.5f), Offset(w * 0.82f, w * 0.5f), 2.dp.toPx())
    drawLine(ink, Offset(w * 0.5f, w * 0.06f), Offset(w * 0.5f, w * 0.34f), 1.2.dp.toPx())
    drawLine(ink, Offset(w * 0.5f, w * 0.66f), Offset(w * 0.5f, w * 0.94f), 1.2.dp.toPx())
}

private fun DrawScope.person(ink: Color) {
    val w = size.width
    drawCircle(ink.copy(alpha = 0.14f), w * 0.18f, Offset(w * 0.5f, w * 0.32f))
    drawCircle(ink, w * 0.18f, Offset(w * 0.5f, w * 0.32f), style = st())
    drawPath(Path().apply { moveTo(w * 0.16f, w * 0.92f); quadraticTo(w * 0.2f, w * 0.58f, w * 0.5f, w * 0.58f); quadraticTo(w * 0.8f, w * 0.58f, w * 0.84f, w * 0.92f) }, ink, style = st())
}

private fun DrawScope.star(ink: Color) {
    val w = size.width
    val c = Offset(w / 2, w * 0.52f)
    val p = Path()
    for (k in 0 until 10) {
        val r = if (k % 2 == 0) w * 0.44f else w * 0.2f
        val a = Math.toRadians(-90.0 + k * 36.0)
        val x = c.x + r * Math.cos(a).toFloat()
        val y = c.y + r * Math.sin(a).toFloat()
        if (k == 0) p.moveTo(x, y) else p.lineTo(x, y)
    }
    p.close()
    drawPath(p, ink.copy(alpha = 0.14f)); drawPath(p, ink, style = st())
}

private fun DrawScope.quietBell() {
    val w = size.width
    val ink = Color(0xFFB6C2D4)
    val b = Path().apply {
        moveTo(w * 0.2f, w * 0.72f); quadraticTo(w * 0.28f, w * 0.6f, w * 0.28f, w * 0.42f)
        quadraticTo(w * 0.3f, w * 0.14f, w * 0.5f, w * 0.14f); quadraticTo(w * 0.7f, w * 0.14f, w * 0.72f, w * 0.42f)
        quadraticTo(w * 0.72f, w * 0.6f, w * 0.8f, w * 0.72f); close()
    }
    drawPath(b, Color(0xFFF1F5FB)); drawPath(b, ink, style = st())
    drawLine(ink, Offset(w * 0.42f, w * 0.84f), Offset(w * 0.58f, w * 0.84f), st().width, StrokeCap.Round)
}

/** ISO time → "now", "5 min", "3 h", "2 d", or "4 Oct". */
fun ago(iso: String?): String? {
    if (iso.isNullOrBlank()) return null
    val t = runCatching { java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.US).parse(iso)?.time }.getOrNull() ?: return null
    val s = (System.currentTimeMillis() - t) / 1000
    return when {
        s < 60 -> "now"
        s < 3600 -> "${s / 60} min"
        s < 86_400 -> "${s / 3600} h"
        s < 7 * 86_400 -> "${s / 86_400} d"
        else -> java.text.SimpleDateFormat("d MMM", java.util.Locale.ENGLISH).format(java.util.Date(t))
    }
}
