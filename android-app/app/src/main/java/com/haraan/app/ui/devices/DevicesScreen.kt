package com.haraan.app.ui.devices

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Laptop
import androidx.compose.material.icons.filled.PhoneAndroid
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.devices.DeviceState
import com.haraan.app.data.devices.SignedInDevice
import com.haraan.app.ui.theme.HaraanColors

private val Blue = HaraanColors.EventsBlue
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Line = HaraanColors.BorderLight

/**
 * The device list, in two moods.
 *
 * [held] = this phone signed in past the plan's limit (Free 1, Pro 1, Hero 3). It is the
 * whole screen: the member signs another device out, upgrades, or signs this phone out
 * instead — there is no back. Otherwise it's Account → Signed-in devices, with a back arrow.
 */
@Composable
fun DevicesScreen(
    state: DeviceState,
    held: Boolean,
    busyDeviceId: String?,
    error: String?,
    onSignOutDevice: (SignedInDevice) -> Unit,
    onUpgrade: (() -> Unit)?,
    onClose: (() -> Unit)?,
    modifier: Modifier = Modifier,
) {
    val others = state.devices.filterNot { it.thisDevice }
    val here = state.devices.firstOrNull { it.thisDevice }

    Column(
        modifier
            .fillMaxSize()
            .background(HaraanColors.Background)
            .statusBarsPadding()
            .navigationBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp),
    ) {
        if (onClose != null) {
            Row(Modifier.fillMaxWidth().padding(top = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(40.dp).clip(CircleShape).clickable(onClick = onClose),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Text1) }
            }
        } else {
            Spacer(Modifier.height(32.dp))
        }

        Spacer(Modifier.height(12.dp))
        Text(
            if (held) state.title.ifBlank { "You’re signed in on too many devices" } else "Signed-in devices",
            color = Text1, fontSize = 24.sp, fontWeight = FontWeight.Bold, lineHeight = 30.sp,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            if (held) state.message else "Phones and browsers where you’re signed in to Haraan.",
            color = Text2, fontSize = 15.sp, lineHeight = 22.sp,
        )

        if (state.enforced && state.limit != null) {
            Spacer(Modifier.height(20.dp))
            SlotMeter(used = state.used, limit = state.limit, planName = state.plan.name)
        }

        if (error != null) {
            Spacer(Modifier.height(12.dp))
            Text(error, color = HaraanColors.Danger, fontSize = 13.sp)
        }

        Spacer(Modifier.height(24.dp))
        Text(
            if (held) "Sign out of one to continue here" else "Devices",
            color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(10.dp))
        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(HaraanColors.Surface)
                .border(1.dp, Line, RoundedCornerShape(16.dp)),
        ) {
            val rows = if (held) others else listOfNotNull(here) + others
            if (rows.isEmpty()) {
                Text("No other devices.", color = Text2, fontSize = 14.sp, modifier = Modifier.padding(16.dp))
            }
            rows.forEachIndexed { i, device ->
                if (i > 0) Box(Modifier.fillMaxWidth().padding(start = 68.dp).height(1.dp).background(HaraanColors.Hairline))
                DeviceRow(
                    device = device,
                    busy = busyDeviceId == device.id,
                    enabled = busyDeviceId == null,
                    onSignOut = { onSignOutDevice(device) },
                )
            }
        }

        val upgrade = state.upgrade
        if (state.enforced && upgrade != null && onUpgrade != null) {
            Spacer(Modifier.height(16.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(HaraanColors.AccentTint)
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    "${upgrade.name} keeps you signed in on ${upgrade.limitLabel} at once.",
                    color = Text1, fontSize = 14.sp, lineHeight = 20.sp, modifier = Modifier.weight(1f),
                )
                Spacer(Modifier.width(12.dp))
                Box(
                    Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(Blue)
                        .clickable(onClick = onUpgrade)
                        .padding(horizontal = 16.dp, vertical = 10.dp),
                ) { Text("See ${upgrade.name}", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold) }
            }
        }

        if (held && here != null) {
            Spacer(Modifier.height(20.dp))
            Box(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .clickable(enabled = busyDeviceId == null) { onSignOutDevice(here) }
                    .padding(vertical = 14.dp),
                contentAlignment = Alignment.Center,
            ) {
                if (busyDeviceId == here.id) {
                    CircularProgressIndicator(color = Blue, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                } else {
                    Text("Sign out of this phone instead", color = Blue, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Text(
            "Signing a device out ends its session straight away. A device you haven’t used for a while signs itself out and frees its place.",
            color = HaraanColors.TextMuted, fontSize = 12.sp, lineHeight = 18.sp,
        )
        Spacer(Modifier.height(24.dp))
    }
}

/** "1 of 1 in use on Free" with one pill per slot, filled for the ones taken. */
@Composable
private fun SlotMeter(used: Int, limit: Int, planName: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(HaraanColors.Surface)
            .border(1.dp, Line, RoundedCornerShape(14.dp))
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            repeat(limit.coerceAtMost(10)) { i ->
                Box(
                    Modifier
                        .size(width = 22.dp, height = 8.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(if (i < used) Blue else Line),
                )
            }
        }
        Spacer(Modifier.width(12.dp))
        Text(
            "${used.coerceAtMost(limit)} of $limit in use on $planName",
            color = Text2, fontSize = 14.sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun DeviceRow(device: SignedInDevice, busy: Boolean, enabled: Boolean, onSignOut: () -> Unit) {
    Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(HaraanColors.Field),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                if (device.surface == "web") Icons.Default.Laptop else Icons.Default.PhoneAndroid,
                contentDescription = null, tint = Text1, modifier = Modifier.size(20.dp),
            )
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(device.name, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Text(
                when {
                    device.thisDevice -> "This phone"
                    device.status == "pending" -> "Waiting to sign in"
                    else -> device.lastActiveLabel
                },
                color = if (device.thisDevice) Blue else Text2,
                fontSize = 13.sp,
                fontWeight = if (device.thisDevice) FontWeight.SemiBold else FontWeight.Normal,
            )
        }
        Spacer(Modifier.width(8.dp))
        Box(
            Modifier
                .clip(RoundedCornerShape(10.dp))
                .border(1.dp, Color(0xFFC7D7FE), RoundedCornerShape(10.dp))
                .clickable(enabled = enabled, onClick = onSignOut)
                .padding(horizontal = 14.dp, vertical = 8.dp),
            contentAlignment = Alignment.Center,
        ) {
            if (busy) {
                CircularProgressIndicator(color = Blue, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
            } else {
                Text("Sign out", color = Blue, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
