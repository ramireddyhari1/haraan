package com.haraan.partner.alerts

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * "Display over other apps" for a partner app that isn't installed from a store.
 *
 * From Android 13, an app installed from a downloaded APK (which every partner
 * install is, including our own in-app updates) gets that switch greyed out as a
 * "restricted setting". It only unlocks after the owner opens App info, taps ⋮
 * and chooses "Allow restricted settings". Sending them straight to the overlay
 * screen leaves them staring at a switch that won't move.
 */
object OverlayAccess {

    private val STORES = setOf("com.android.vending", "com.google.android.packageinstaller.store")

    /** True when the overlay switch is likely locked until restricted settings are allowed. */
    fun needsRestrictedStep(context: Context): Boolean {
        if (Build.VERSION.SDK_INT < 33) return false
        if (AlertOverlay.allowed(context)) return false
        val installer = runCatching {
            context.packageManager.getInstallSourceInfo(context.packageName).installingPackageName
        }.getOrNull()
        return installer !in STORES
    }

    fun openAppInfo(context: Context) {
        runCatching {
            context.startActivity(
                Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:" + context.packageName))
                    .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )
        }
    }

    fun openOverlaySettings(context: Context) {
        runCatching {
            context.startActivity(
                Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:" + context.packageName))
                    .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )
        }
    }
}

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Blue = Color(0xFF2563EB)
private val Tint = Color(0xFFEFF4FF)

/**
 * The two-step unlock, said plainly, with a button for each screen. The partner
 * comes back here between the two; "Use notifications only" keeps alerts working
 * meanwhile, as a heads-up notification instead of a card over other apps.
 */
@Composable
fun RestrictedSettingsDialog(
    onOpenAppInfo: () -> Unit,
    onOpenOverlay: () -> Unit,
    onNotificationsOnly: () -> Unit,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color.White,
        title = { Text("One extra step on this phone", fontWeight = FontWeight.Bold, color = Ink) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text(
                    "Android locks \"Display over other apps\" for apps installed outside the Play Store. Unlock it once:",
                    fontSize = 14.sp, color = Muted, lineHeight = 19.sp,
                )
                Step(1, "Open App info, tap the ⋮ menu at the top right, and choose \"Allow restricted settings\".") { MenuGlyph() }
                Step(2, "Come back and turn on \"Display over other apps\" for Haraan Partner.") { SwitchGlyph() }
                Spacer(Modifier.height(2.dp))
                Button(
                    onClick = onOpenAppInfo,
                    colors = ButtonDefaults.buttonColors(containerColor = Blue),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                ) { Text("1 · Open App info", fontWeight = FontWeight.SemiBold) }
                OutlinedButton(
                    onClick = onOpenOverlay,
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                ) { Text("2 · Turn on display over apps", color = Blue, fontWeight = FontWeight.SemiBold) }
            }
        },
        confirmButton = {},
        dismissButton = {
            TextButton(onClick = onNotificationsOnly) { Text("Use notifications only", color = Muted) }
        },
    )
}

@Composable
private fun Step(n: Int, text: String, glyph: @Composable () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(26.dp).clip(CircleShape).background(Tint), contentAlignment = Alignment.Center) {
            Text("$n", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue)
        }
        Spacer(Modifier.width(12.dp))
        Text(text, fontSize = 14.sp, color = Ink, lineHeight = 19.sp, modifier = Modifier.weight(1f))
        Spacer(Modifier.width(10.dp))
        Box(Modifier.size(width = 40.dp, height = 28.dp), contentAlignment = Alignment.Center) { glyph() }
    }
}

/** The ⋮ overflow menu, drawn the way the settings screen shows it. */
@Composable
private fun MenuGlyph() {
    Canvas(Modifier.size(width = 36.dp, height = 26.dp)) {
        drawRoundRect(Tint, cornerRadius = CornerRadius(6.dp.toPx()))
        val cx = size.width / 2
        for (i in -1..1) drawCircle(Blue, 2.2.dp.toPx(), Offset(cx, size.height / 2 + i * 6.dp.toPx()))
    }
}

/** A switch in the on position. */
@Composable
private fun SwitchGlyph() {
    Canvas(Modifier.size(width = 36.dp, height = 22.dp)) {
        drawRoundRect(Blue, size = Size(size.width, size.height), cornerRadius = CornerRadius(size.height / 2))
        drawCircle(Color.White, size.height / 2 - 3.dp.toPx(), Offset(size.width - size.height / 2, size.height / 2))
        drawRoundRect(Blue.copy(alpha = 0.3f), size = Size(size.width, size.height), cornerRadius = CornerRadius(size.height / 2), style = Stroke(1.dp.toPx()))
    }
}
