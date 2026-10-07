package com.haraan.partner.ui.venues

import android.content.Intent
import android.net.Uri
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
import androidx.compose.material.icons.automirrored.filled.Chat
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.path
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.partner.HaraanManager
import com.haraan.partner.ui.components.pressableTile

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hairline = Color(0xFFE6EBF2)
private val Blue = Color(0xFF2563EB)
private val BlueDeep = Color(0xFF1E40AF)
private val BlueTint = Color(0xFFEFF4FF)

/**
 * The Haraan employee who looks after this partner, on top of Venues. Who it is, when
 * they're reachable and the number the admin chose all come from /control; nothing
 * here is made up. With nobody assigned it falls back to plain Haraan support.
 */
@Composable
internal fun ManagerCard(m: HaraanManager, onChat: () -> Unit) {
    val context = LocalContext.current
    val assigned = m.name != null
    val waNumber = if (assigned) m.phone.takeIf { m.showWhatsapp } else m.supportWhatsapp
    val callNumber = if (assigned) m.phone.takeIf { m.showCall } else null
    val showChat = !assigned || m.showChat

    Column(
        Modifier.fillMaxWidth()
            .shadow(8.dp, RoundedCornerShape(20.dp), clip = false, spotColor = Color(0x140F172A))
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Avatar(m)
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    (if (assigned) m.title else null)?.uppercase() ?: "HARAAN SUPPORT",
                    fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = Blue, letterSpacing = 0.9.sp,
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    m.name ?: "We're here for your venue",
                    fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, letterSpacing = (-0.2).sp,
                )
                val sub = when {
                    !assigned -> "A manager hasn't been assigned yet"
                    m.hours != null -> m.hours
                    else -> null
                }
                sub?.let {
                    Spacer(Modifier.height(3.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        if (assigned) {
                            Icon(Icons.Filled.Schedule, null, tint = Faint, modifier = Modifier.size(13.dp))
                            Spacer(Modifier.width(4.dp))
                        }
                        Text(it, fontSize = 12.5.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
        m.intro?.takeIf { assigned }?.let {
            Spacer(Modifier.height(12.dp))
            Text(it, fontSize = 13.5.sp, color = Muted, lineHeight = 19.sp)
        }
        if (callNumber != null || waNumber != null || showChat) {
            Spacer(Modifier.height(14.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                callNumber?.let { n ->
                    Action("Call", Icons.Filled.Call, primary = true, Modifier.weight(1f)) {
                        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:$n")))
                    }
                }
                waNumber?.let { n ->
                    Action("WhatsApp", WhatsAppGlyph, primary = callNumber == null, Modifier.weight(1f)) {
                        val digits = n.filter { it.isDigit() }.let { if (it.length == 10) "91$it" else it }
                        runCatching {
                            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$digits")))
                        }
                    }
                }
                if (showChat) {
                    Action("Chat", Icons.AutoMirrored.Filled.Chat, primary = callNumber == null && waNumber == null, Modifier.weight(1f), onChat)
                }
            }
        }
    }
}

@Composable
private fun Avatar(m: HaraanManager) {
    Box(
        Modifier.size(54.dp).clip(CircleShape)
            .background(Brush.linearGradient(listOf(Color(0xFFDCE8FF), Color(0xFFC7D9FF))))
            .border(2.dp, Color.White, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        when {
            m.photoUrl != null -> AsyncImage(
                model = m.photoUrl, contentDescription = m.name,
                contentScale = ContentScale.Crop, modifier = Modifier.size(54.dp).clip(CircleShape),
            )
            m.name != null -> Text(initials(m.name), fontSize = 18.sp, fontWeight = FontWeight.Bold, color = BlueDeep)
            else -> Icon(Icons.Filled.SupportAgent, null, tint = BlueDeep, modifier = Modifier.size(26.dp))
        }
    }
}

private fun initials(name: String): String =
    name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2).joinToString("") { it.first().uppercase() }

/** Compact half-height button: springs down under the finger and ticks (pressableTile). */
@Composable
private fun Action(label: String, icon: ImageVector, primary: Boolean, modifier: Modifier, onClick: () -> Unit) {
    Row(
        modifier.height(42.dp)
            .pressableTile(12.dp, pressedScale = 0.95f, onClick = onClick)
            .clip(RoundedCornerShape(12.dp))
            .background(if (primary) Blue else BlueTint),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = if (primary) Color.White else Blue, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = if (primary) Color.White else Blue)
    }
}

/** A line WhatsApp mark: speech bubble with a tail and the handset inside. */
private val WhatsAppGlyph: ImageVector by lazy {
    ImageVector.Builder("wa", 24.dp, 24.dp, 24f, 24f).apply {
        path(
            fill = null,
            stroke = androidx.compose.ui.graphics.SolidColor(Color.Black),
            strokeLineWidth = 1.8f,
            strokeLineCap = androidx.compose.ui.graphics.StrokeCap.Round,
            strokeLineJoin = androidx.compose.ui.graphics.StrokeJoin.Round,
        ) {
            moveTo(3.5f, 20.5f)
            lineTo(4.8f, 16.4f)
            arcTo(8.5f, 8.5f, 0f, true, true, 7.8f, 19.3f)
            close()
        }
        path(fill = androidx.compose.ui.graphics.SolidColor(Color.Black)) {
            moveTo(9.2f, 7.6f)
            curveTo(8.6f, 7.6f, 8f, 8.3f, 8f, 9.2f)
            curveTo(8f, 11.9f, 11.6f, 15.8f, 14.6f, 16f)
            curveTo(15.5f, 16f, 16.3f, 15.4f, 16.3f, 14.8f)
            lineTo(16.3f, 14.3f)
            lineTo(14.4f, 13.4f)
            lineTo(13.6f, 14.3f)
            curveTo(12.3f, 13.8f, 10.9f, 12.4f, 10.4f, 11.1f)
            lineTo(11.3f, 10.3f)
            lineTo(10.4f, 8.2f)
            close()
        }
    }.build()
}
