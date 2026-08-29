package com.haraan.app.ui.matches

import android.graphics.Bitmap
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

/**
 * The "+" sheet: what is already attached, and what can be attached next.
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

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .padding(horizontal = 18.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(22.dp))
                .background(Panel)
                .padding(22.dp),
        ) {
            val open = pairing
            if (open == null) {
                Text("Add match device", color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(6.dp))
                Text(
                    "Use another phone as a camera for this match.",
                    color = Ink.copy(alpha = 0.6f),
                    fontSize = 13.sp,
                    lineHeight = 18.sp,
                )
                Spacer(Modifier.height(18.dp))

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

                // The reason for pairing anything is the footage, so the way to it sits
                // here rather than behind another control in the header.
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(Well)
                        .pressable(onClick = onOpenClips)
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        Modifier.size(34.dp).clip(RoundedCornerShape(11.dp)).background(Ink.copy(alpha = 0.08f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(Icons.Filled.PlayArrow, null, tint = Ink, modifier = Modifier.size(18.dp))
                    }
                    Spacer(Modifier.width(13.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Match footage", color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(3.dp))
                        Text(
                            "Watch what the cameras have sent.",
                            color = Ink.copy(alpha = 0.55f),
                            fontSize = 12.sp,
                        )
                    }
                }
                Spacer(Modifier.height(10.dp))

                if (devices.isNotEmpty()) {
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "CONNECTED",
                        color = Ink.copy(alpha = 0.45f),
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.8.sp,
                    )
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
            } else {
                PairingPanel(open) { pairing = null }
            }

            error?.let {
                Spacer(Modifier.height(12.dp))
                Text(it, color = Danger, fontSize = 13.sp)
            }

            Spacer(Modifier.height(16.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Well)
                    .padding(vertical = 13.dp),
                horizontalArrangement = Arrangement.Center,
            ) {
                Text(
                    if (pairing == null) "Done" else "Back",
                    color = Ink,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.pressable { if (pairing == null) onDismiss() else pairing = null },
                )
            }
        }
    }
}

@Composable
private fun RoleCard(role: MatchDeviceRole, enabled: Boolean, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Well)
            .pressable(enabled = enabled, onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(34.dp).clip(RoundedCornerShape(11.dp)).background(Ink.copy(alpha = 0.08f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Outlined.Videocam, null, tint = Ink, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(13.dp))
        Column(Modifier.weight(1f)) {
            Text(role.label, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(3.dp))
            Text(role.blurb, color = Ink.copy(alpha = 0.55f), fontSize = 12.sp, lineHeight = 16.sp)
        }
    }
}

@Composable
private fun DeviceRow(device: MatchDeviceInfo, onRevoke: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Well)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Live and lost are different states and are drawn differently: a phone that
        // has stopped checking in is not filming, and a green dot would say it is.
        Box(
            Modifier
                .size(8.dp)
                .clip(CircleShape)
                .background(if (device.isLive) Live else Ink.copy(alpha = 0.3f)),
        )
        Spacer(Modifier.width(11.dp))
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
                device.roleLabel + if (device.isLost) " · reconnecting" else "",
                color = Ink.copy(alpha = 0.5f),
                fontSize = 11.5.sp,
                maxLines = 1,
            )
        }
        Text(
            "Remove",
            color = Danger,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.pressable(onClick = onRevoke),
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
            .clip(RoundedCornerShape(11.dp))
            .background(Well)
            .pressable(onClick = onClick)
            .padding(vertical = 12.dp),
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

