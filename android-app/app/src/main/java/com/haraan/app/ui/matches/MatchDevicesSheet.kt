package com.haraan.app.ui.matches

import android.graphics.Bitmap
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import com.haraan.app.ui.Feel
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.outlined.Videocam
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import com.haraan.app.data.MatchDeviceInfo
import com.haraan.app.data.MatchDeviceRepository
import com.haraan.app.data.MatchDeviceRole
import com.haraan.app.data.PairingSession
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// ─────────────────────────────────────────────────────────────────────────────
//  MATCH DEVICES
//
//  A match is scored on one phone, but there is more than one thing worth
//  pointing a camera at. This is the scorer's end of attaching another one:
//  pick a role, show a code, watch it connect, and cut it loose again.
//
//  It is built as device pairing rather than as "add a camera" on purpose — the
//  server stores a ROLE string, so the next assisted feature that needs a second
//  phone needs a card in this sheet and nothing else.
// ─────────────────────────────────────────────────────────────────────────────

// The scorer screen's own tokens are file-private to ScoringScreen.kt; these are the
// same values from the design system rather than a fourth copy of the hexes.
private val Panel = HaraanColors.Surface
private val Well = HaraanColors.Background
private val Ink = HaraanColors.TextPrimary
private val Danger = HaraanColors.LiveRed
private val Live = HaraanColors.Success
private val Blue = Color(0xFF2563EB)
private val Ink2 = Color(0xFF64748B)
private val Hair = Color(0xFFE2E8F0)
private val Turf = Color(0xFF16A34A)
private val Strip = Color(0xFFE7D8B5)

/**
 * The "+" sheet: what is already attached, and what can be attached next.
 *
 * It opens on what the job NEEDS — a second phone, a signal, something to stand it on —
 * because a camera held in a fielder's hand with one bar of 3G produces a clip nobody
 * can review, and the scorer finds that out three overs later. Then the two places a
 * camera can stand, each drawn on a pitch, so "down the pitch" and "side-on" are a
 * picture rather than a sentence to decode.
 *
 * @param onDismiss closes the sheet. Pairing survives it — a code that is still
 *   valid is still valid, and the scorer has a match to run.
 */
@Composable
fun MatchDevicesSheet(matchId: String, onDismiss: () -> Unit, onOpenClips: () -> Unit = {}) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { MatchDeviceRepository() }

    var devices by remember { mutableStateOf<List<MatchDeviceInfo>>(emptyList()) }
    var pairing by remember { mutableStateOf<PairingSession?>(null) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    // While the sheet is open, the list is the truth about what is filming. Polling
    // rather than pushing: this is open for a few seconds at a time, and a socket for
    // that would be a connection to manage for no gain.
    LaunchedEffect(matchId, pairing) {
        while (true) {
            val token = TokenStore.getToken(ctx)
            if (TokenStore.isSignedIn(token)) {
                devices = runCatching { repo.devices(token!!, matchId) }.getOrDefault(devices)
                // The moment the code is claimed, the QR has done its job.
                pairing?.let { open ->
                    if (devices.any { it.id == open.id && it.status != "pending" }) pairing = null
                }
            }
            delay(3000)
        }
    }

    // The sheet arrives rather than appears: a short rise and settle.
    val enter = remember { Animatable(0f) }
    LaunchedEffect(Unit) { enter.animateTo(1f, spring(dampingRatio = 0.78f, stiffness = 420f)) }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .padding(horizontal = 14.dp, vertical = 24.dp)
                .fillMaxWidth()
                .graphicsLayer {
                    alpha = enter.value.coerceIn(0f, 1f)
                    translationY = (1f - enter.value) * 48.dp.toPx()
                }
                .clip(RoundedCornerShape(26.dp))
                .background(Panel)
                .verticalScroll(rememberScrollState())
                .padding(20.dp),
        ) {
            val open = pairing
            if (open == null) {
                Text("Add a camera", color = Ink, fontSize = 21.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "A second phone films every ball, so you can watch it again when there's an appeal.",
                    color = Ink2,
                    fontSize = 13.sp,
                    lineHeight = 18.sp,
                )
                Spacer(Modifier.height(16.dp))

                SetupScene(Modifier.fillMaxWidth().height(150.dp))

                Spacer(Modifier.height(18.dp))
                SectionLabel("WHAT YOU NEED")
                Spacer(Modifier.height(8.dp))
                Needs()

                Spacer(Modifier.height(20.dp))
                SectionLabel("WHERE WILL IT STAND?")
                Spacer(Modifier.height(10.dp))
                MatchDeviceRole.entries.forEach { role ->
                    RoleCard(role, enabled = !busy) {
                        error = null
                        busy = true
                        scope.launch {
                            val token = TokenStore.getToken(ctx)
                            if (!TokenStore.isSignedIn(token)) {
                                error = "Sign in to add a device."
                                busy = false
                                return@launch
                            }
                            runCatching { repo.openPairing(token!!, matchId, role) }
                                .onSuccess { pairing = it }
                                .onFailure { error = it.message ?: "Couldn't start pairing." }
                            busy = false
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                }

                if (devices.isNotEmpty()) {
                    Spacer(Modifier.height(10.dp))
                    SectionLabel("CONNECTED")
                    Spacer(Modifier.height(10.dp))
                    devices.forEach { device ->
                        DeviceRow(device) {
                            scope.launch {
                                val token = TokenStore.getToken(ctx)
                                if (TokenStore.isSignedIn(token)) {
                                    repo.revoke(token!!, matchId, device.id)
                                    devices = devices.filterNot { it.id == device.id }
                                }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                    }
                }

                // The reason for pairing anything is the footage, so the way to it sits
                // here rather than behind another control in the header.
                Spacer(Modifier.height(6.dp))
                Row(
                    Modifier
                        .pressable(onClick = onOpenClips)
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(16.dp))
                        .border(1.dp, Hair, RoundedCornerShape(16.dp))
                        .padding(horizontal = 14.dp, vertical = 13.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        Modifier.size(36.dp).clip(CircleShape).background(Blue.copy(alpha = 0.1f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(Icons.Filled.PlayArrow, null, tint = Blue, modifier = Modifier.size(20.dp))
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Match footage", color = Ink, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                        Text("Every clip the cameras have sent", color = Ink2, fontSize = 12.sp)
                    }
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Ink2)
                }
            } else {
                PairingPanel(open) { pairing = null }
            }

            error?.let {
                Spacer(Modifier.height(12.dp))
                Text(it, color = Danger, fontSize = 13.sp)
            }

            Spacer(Modifier.height(16.dp))
            // The whole bar is the target. It used to be only the word, so a thumb landing
            // beside "Done" did nothing at all.
            Row(
                Modifier
                    .pressable { if (pairing == null) onDismiss() else pairing = null }
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(Well)
                    .padding(vertical = 14.dp),
                horizontalArrangement = Arrangement.Center,
            ) {
                Text(
                    if (pairing == null) "Done" else "Back",
                    color = Ink,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, color = Ink2, fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
}

/**
 * The picture at the top: a phone standing on a tripod behind a set of stumps, sending
 * its signal. It is the whole setup in one glance — the three things the list below
 * names, in the arrangement they are used in.
 */
@Composable
private fun SetupScene(modifier: Modifier) {
    val t = rememberInfiniteTransition(label = "scene")
    val wave by t.animateFloat(
        0f, 1f, infiniteRepeatable(tween(1800, easing = LinearEasing)), label = "wave"
    )
    Canvas(
        modifier
            .clip(RoundedCornerShape(18.dp))
            .background(Brush.verticalGradient(listOf(Color(0xFFEFF6FF), Color(0xFFF8FAFC))))
    ) {
        val w = size.width
        val h = size.height
        val ground = h * 0.86f
        // Turf and the strip.
        drawRect(Turf.copy(alpha = 0.12f), topLeft = Offset(0f, ground), size = Size(w, h - ground))
        drawRect(Strip, topLeft = Offset(w * 0.55f, ground - 2.dp.toPx()), size = Size(w * 0.4f, 4.dp.toPx()))

        // Stumps, far end.
        val sx = w * 0.82f
        repeat(3) { i ->
            val x = sx + i * 5.dp.toPx()
            drawLine(Color(0xFFB45309), Offset(x, ground - 30.dp.toPx()), Offset(x, ground), 2.5.dp.toPx(), StrokeCap.Round)
        }
        drawLine(Color(0xFFB45309), Offset(sx - 1.dp.toPx(), ground - 31.dp.toPx()), Offset(sx + 11.dp.toPx(), ground - 31.dp.toPx()), 2.dp.toPx(), StrokeCap.Round)

        // Tripod.
        val cx = w * 0.3f
        val head = ground - 50.dp.toPx()
        val leg = Color(0xFF334155)
        drawLine(leg, Offset(cx, head), Offset(cx - 26.dp.toPx(), ground), 3.dp.toPx(), StrokeCap.Round)
        drawLine(leg, Offset(cx, head), Offset(cx + 26.dp.toPx(), ground), 3.dp.toPx(), StrokeCap.Round)
        drawLine(leg, Offset(cx, head), Offset(cx, ground), 3.dp.toPx(), StrokeCap.Round)

        // The phone on the head, landscape, lens facing the stumps.
        val pw = 50.dp.toPx()
        val ph = 26.dp.toPx()
        val pTop = head - ph - 2.dp.toPx()
        drawRoundRect(Ink, topLeft = Offset(cx - pw / 2, pTop), size = Size(pw, ph), cornerRadius = CornerRadius(6.dp.toPx()))
        drawRoundRect(Blue, topLeft = Offset(cx - pw / 2 + 3.dp.toPx(), pTop + 3.dp.toPx()), size = Size(pw - 6.dp.toPx(), ph - 6.dp.toPx()), cornerRadius = CornerRadius(4.dp.toPx()))
        drawCircle(Color.White, radius = 3.dp.toPx(), center = Offset(cx + pw / 2 - 9.dp.toPx(), pTop + ph / 2))

        // What the lens sees: a soft cone toward the stumps.
        val lens = Offset(cx + pw / 2, pTop + ph / 2)
        val cone = Path().apply {
            moveTo(lens.x, lens.y)
            lineTo(sx + 22.dp.toPx(), ground - 44.dp.toPx())
            lineTo(sx + 22.dp.toPx(), ground)
            close()
        }
        drawPath(cone, Brush.horizontalGradient(listOf(Blue.copy(alpha = 0.22f), Blue.copy(alpha = 0.02f)), startX = lens.x, endX = sx + 22.dp.toPx()))

        // Signal rising off the phone, on a loop — the clip leaving for the server.
        val top = Offset(cx, pTop - 6.dp.toPx())
        repeat(3) { i ->
            val p = (wave + i / 3f) % 1f
            val r = 8.dp.toPx() + p * 26.dp.toPx()
            drawArc(
                Blue.copy(alpha = (1f - p) * 0.8f),
                startAngle = 225f, sweepAngle = 90f, useCenter = false,
                topLeft = Offset(top.x - r, top.y - r), size = Size(r * 2, r * 2),
                style = Stroke(2.dp.toPx(), cap = StrokeCap.Round),
            )
        }
    }
}

/** One thing the setup needs: its drawing, what it is, and why it matters. */
private enum class Need(val title: String, val why: String) {
    PHONE("A second smartphone", "With the Haraan app, charged above 50%. It films for the whole innings."),
    NETWORK("Good internet", "4G or Wi-Fi on the camera phone. Each ball's clip uploads right after it's bowled."),
    TRIPOD("A tripod or stand", "Keeps the picture still. A clip from a moving hand can't settle an appeal."),
}

/**
 * The three needs as a checklist the scorer can tick while setting up. Ticking gates
 * nothing — it is there because walking a phone and a tripod out to a ground is a job
 * with steps, and a list you can press feels like one you have actually worked through.
 */
@Composable
private fun Needs() {
    val done = remember { mutableStateListOf<Need>() }
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .border(1.dp, Hair, RoundedCornerShape(18.dp))
    ) {
        Need.entries.forEachIndexed { i, need ->
            if (i > 0) Box(Modifier.padding(start = 70.dp).fillMaxWidth().height(1.dp).background(Hair))
            val checked = need in done
            Row(
                Modifier
                    .pressable(haptic = Feel.TICK) { if (checked) done.remove(need) else done.add(need) }
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier.size(46.dp).clip(RoundedCornerShape(13.dp)).background(Blue.copy(alpha = 0.08f)),
                    contentAlignment = Alignment.Center,
                ) {
                    NeedGlyph(need, Modifier.size(30.dp))
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(need.title, color = Ink, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(2.dp))
                    Text(need.why, color = Ink2, fontSize = 12.sp, lineHeight = 16.sp)
                }
                Spacer(Modifier.width(10.dp))
                CheckDot(checked)
            }
        }
    }
}

@Composable
private fun CheckDot(checked: Boolean) {
    val fill by animateFloatAsState(
        if (checked) 1f else 0f, spring(dampingRatio = 0.5f, stiffness = 700f), label = "check"
    )
    Box(
        Modifier
            .size(24.dp)
            .clip(CircleShape)
            .border(1.5.dp, if (checked) Blue else Color(0xFFCBD5E1), CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Box(
            Modifier
                .size(24.dp)
                .graphicsLayer { scaleX = fill; scaleY = fill }
                .clip(CircleShape)
                .background(Blue),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(15.dp))
        }
    }
}

/** Drawn, not emoji: each need as a small line illustration in brand blue. */
@Composable
private fun NeedGlyph(need: Need, modifier: Modifier) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val s = Stroke(2.dp.toPx(), cap = StrokeCap.Round, join = androidx.compose.ui.graphics.StrokeJoin.Round)
        when (need) {
            Need.PHONE -> {
                drawRoundRect(Blue, topLeft = Offset(w * 0.28f, h * 0.06f), size = Size(w * 0.44f, h * 0.88f), cornerRadius = CornerRadius(4.dp.toPx()), style = s)
                drawCircle(Blue, radius = 2.2.dp.toPx(), center = Offset(w * 0.5f, h * 0.2f))
                drawLine(Blue, Offset(w * 0.43f, h * 0.84f), Offset(w * 0.57f, h * 0.84f), 2.dp.toPx(), StrokeCap.Round)
            }
            Need.NETWORK -> {
                // Signal bars rising, the way every phone draws "good".
                listOf(0.3f, 0.5f, 0.7f, 0.92f).forEachIndexed { i, frac ->
                    val x = w * (0.18f + i * 0.2f)
                    drawLine(Blue.copy(alpha = if (i == 3) 1f else 0.85f), Offset(x, h * 0.92f), Offset(x, h * (0.92f - frac * 0.8f)), 3.2.dp.toPx(), StrokeCap.Round)
                }
            }
            Need.TRIPOD -> {
                val head = Offset(w * 0.5f, h * 0.38f)
                drawRoundRect(Blue, topLeft = Offset(w * 0.24f, h * 0.08f), size = Size(w * 0.52f, h * 0.26f), cornerRadius = CornerRadius(3.dp.toPx()), style = s)
                drawLine(Blue, head, Offset(w * 0.18f, h * 0.94f), 2.dp.toPx(), StrokeCap.Round)
                drawLine(Blue, head, Offset(w * 0.82f, h * 0.94f), 2.dp.toPx(), StrokeCap.Round)
                drawLine(Blue, head, Offset(w * 0.5f, h * 0.94f), 2.dp.toPx(), StrokeCap.Round)
            }
        }
    }
}

@Composable
private fun RoleCard(role: MatchDeviceRole, enabled: Boolean, onClick: () -> Unit) {
    Row(
        Modifier
            .pressable(enabled = enabled, onClick = onClick)
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Well)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        PlacementDiagram(role, Modifier.size(width = 64.dp, height = 72.dp))
        Spacer(Modifier.width(13.dp))
        Column(Modifier.weight(1f)) {
            Text(role.label, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(3.dp))
            Text(role.blurb, color = Ink2, fontSize = 12.sp, lineHeight = 16.sp)
        }
        Spacer(Modifier.width(6.dp))
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Ink2)
    }
}

/**
 * Where this camera stands, drawn on a pitch seen from above: the strip, the stumps at
 * each end, and the phone with the cone it sees. Down the pitch for review, square of
 * the bowler's crease for the action.
 */
@Composable
private fun PlacementDiagram(role: MatchDeviceRole, modifier: Modifier) {
    Canvas(modifier.clip(RoundedCornerShape(12.dp)).background(Turf.copy(alpha = 0.14f))) {
        val w = size.width
        val h = size.height
        val stripW = w * 0.3f
        val left = (w - stripW) / 2
        drawRect(Strip, topLeft = Offset(left, h * 0.14f), size = Size(stripW, h * 0.72f))
        val stump = Color(0xFFB45309)
        listOf(h * 0.2f, h * 0.8f).forEach { y ->
            repeat(3) { i ->
                drawCircle(stump, radius = 1.6.dp.toPx(), center = Offset(w / 2 + (i - 1) * 3.dp.toPx(), y))
            }
        }
        val phone: Offset
        val target: Offset
        when (role) {
            MatchDeviceRole.LBW_REVIEW -> { phone = Offset(w / 2, h * 0.95f); target = Offset(w / 2, h * 0.2f) }
            MatchDeviceRole.BOWLER_ANALYSIS -> { phone = Offset(w * 0.06f, h * 0.22f); target = Offset(w / 2, h * 0.22f) }
        }
        val dir = Offset(target.x - phone.x, target.y - phone.y)
        val len = kotlin.math.sqrt(dir.x * dir.x + dir.y * dir.y)
        val nx = -dir.y / len
        val ny = dir.x / len
        val spread = len * 0.32f
        val cone = Path().apply {
            moveTo(phone.x, phone.y)
            lineTo(target.x + nx * spread, target.y + ny * spread)
            lineTo(target.x - nx * spread, target.y - ny * spread)
            close()
        }
        drawPath(cone, Blue.copy(alpha = 0.22f))
        drawCircle(Color.White, radius = 5.5.dp.toPx(), center = phone)
        drawCircle(Blue, radius = 4.dp.toPx(), center = phone)
    }
}

@Composable
private fun DeviceRow(device: MatchDeviceInfo, onRevoke: () -> Unit) {
    val t = rememberInfiniteTransition(label = "liveDot")
    val ring by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1400, easing = LinearEasing)), label = "ring")
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .border(1.dp, Hair, RoundedCornerShape(16.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Live and lost are different states and are drawn differently: a phone that
        // has stopped checking in is not filming, and a green dot would say it is.
        Box(Modifier.size(16.dp), contentAlignment = Alignment.Center) {
            if (device.isLive) {
                Box(
                    Modifier
                        .size(16.dp)
                        .graphicsLayer { scaleX = 0.4f + ring * 0.6f; scaleY = 0.4f + ring * 0.6f; alpha = 1f - ring }
                        .clip(CircleShape)
                        .background(Live.copy(alpha = 0.5f))
                )
            }
            Box(Modifier.size(8.dp).clip(CircleShape).background(if (device.isLive) Live else Ink.copy(alpha = 0.3f)))
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                device.deviceName.ifBlank { "Camera phone" },
                color = Ink,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 1,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                device.roleLabel + when {
                    device.isLost -> " · reconnecting"
                    device.isLive -> " · filming"
                    else -> ""
                },
                color = Ink2,
                fontSize = 11.5.sp,
                maxLines = 1,
            )
        }
        Text(
            "Remove",
            color = Danger,
            fontSize = 12.5.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier
                .pressable(onClick = onRevoke)
                .clip(RoundedCornerShape(50))
                .border(1.dp, Danger.copy(alpha = 0.35f), RoundedCornerShape(50))
                .padding(horizontal = 12.dp, vertical = 6.dp),
        )
    }
}

/** The QR, the link, and the wait. */
@Composable
private fun PairingPanel(pairing: PairingSession, onBack: () -> Unit) {
    val ctx = LocalContext.current
    Column(Modifier.fillMaxWidth()) {
        Text("Connect another phone", color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(6.dp))
        Text(
            "Scan this code, or open the link on the other device.",
            color = Ink.copy(alpha = 0.6f),
            fontSize = 13.sp,
            lineHeight = 18.sp,
        )
        Spacer(Modifier.height(18.dp))

        val qr = remember(pairing.link) { qrBitmap(pairing.link, 560) }
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            Box(
                Modifier
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color.White)
                    .padding(14.dp),
            ) {
                if (qr != null) {
                    Image(qr.asImageBitmap(), "Pairing QR code", Modifier.size(210.dp))
                } else {
                    Box(Modifier.size(210.dp), contentAlignment = Alignment.Center) {
                        Text("Couldn't draw the code", color = Color(0xFF64748B), fontSize = 12.sp)
                    }
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        // The code in words, because a ground is not always a place where one phone can
        // see another's screen.
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            Text(
                pairing.token,
                color = Ink,
                fontSize = 22.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 4.sp,
            )
        }

        Spacer(Modifier.height(16.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            SmallAction("Copy link", Modifier.weight(1f)) {
                val clipboard = ctx.getSystemService(android.content.ClipboardManager::class.java)
                clipboard?.setPrimaryClip(
                    android.content.ClipData.newPlainText("Haraan camera link", pairing.link),
                )
            }
            SmallAction("Share", Modifier.weight(1f)) {
                val send = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
                    type = "text/plain"
                    putExtra(
                        android.content.Intent.EXTRA_TEXT,
                        "Join my match as the ${pairing.roleLabel.lowercase()}: ${pairing.link}",
                    )
                }
                ctx.startActivity(android.content.Intent.createChooser(send, "Send pairing link"))
            }
        }

        Spacer(Modifier.height(18.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(color = Ink.copy(alpha = 0.5f), strokeWidth = 2.dp, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(10.dp))
            Text("Waiting for device…", color = Ink.copy(alpha = 0.6f), fontSize = 13.sp)
        }
        Spacer(Modifier.height(8.dp))
        Text(
            "The code works once, and only for the next few minutes.",
            color = Ink.copy(alpha = 0.4f),
            fontSize = 11.5.sp,
            lineHeight = 16.sp,
        )
    }
}

@Composable
private fun SmallAction(label: String, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Row(
        modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(12.dp))
            .background(Well)
            .padding(vertical = 13.dp),
        horizontalArrangement = Arrangement.Center,
    ) {
        Text(label, color = Ink, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * The pairing link as a QR.
 *
 * Error correction is set HIGH deliberately: this is scanned off a phone screen at a
 * ground, in sunlight, by a camera that is probably being held at an angle.
 */
private fun qrBitmap(content: String, size: Int): Bitmap? = try {
    val hints = mapOf(
        EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.H,
        EncodeHintType.MARGIN to 1,
    )
    val matrix = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, size, size, hints)
    Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888).apply {
        for (x in 0 until size) {
            for (y in 0 until size) {
                setPixel(x, y, if (matrix.get(x, y)) android.graphics.Color.BLACK else android.graphics.Color.WHITE)
            }
        }
    }
} catch (_: Exception) {
    null
}

