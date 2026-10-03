package com.haraan.partner.ui.venues

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
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
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Directions
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.partner.VenueDetails
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressableTile

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hairline = Color(0xFFE6EBF2)
private val Blue = Color(0xFF2563EB)
private val BlueTint = Color(0xFFEFF4FF)
private val Soft = Color(0xFFF7F9FC)

/** "CJW6+RMC, MDR175, Vaddeswaram…" → "MDR175, Vaddeswaram…": a plus code means nothing to a player. */
internal fun readableAddress(raw: String?): String? =
    raw?.trim()?.replace(Regex("""^[A-Z0-9]{4,}\+[A-Z0-9]{2,}\s*,?\s*"""), "")
        ?.removeSuffix(", India")?.trim()?.takeIf { it.isNotBlank() }

internal fun openDirections(context: Context, d: VenueDetails) {
    val uri = when {
        d.latitude != null && d.longitude != null -> "https://www.google.com/maps/dir/?api=1&destination=${d.latitude},${d.longitude}"
        !d.mapLink.isNullOrBlank() -> d.mapLink
        !d.address.isNullOrBlank() -> "https://www.google.com/maps/search/?api=1&query=" + Uri.encode(d.address)
        else -> return
    }
    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(uri))) }
}

/**
 * The venue's own details as players read them on its page — where it is, what it has,
 * its rules and policy, and what's charged on top — so the owner can see them and spot
 * anything wrong. They're kept by Haraan, so the card ends with a way to ask for a change.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun VenueDetailsCard(d: VenueDetails?, published: Boolean, onSupport: () -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val view = LocalView.current
    Column(
        modifier.fillMaxWidth()
            .shadow(8.dp, RoundedCornerShape(20.dp), clip = false, spotColor = Color(0x140F172A))
            .clip(RoundedCornerShape(20.dp)).background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .animateContentSize(spring(dampingRatio = 0.9f, stiffness = Spring.StiffnessMediumLow)),
    ) {
        Row(Modifier.padding(start = 18.dp, end = 18.dp, top = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Venue details", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
            if (d != null) {
                if (d.rating != null && d.ratingsCount > 0) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFF59E0B), modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(3.dp))
                    Text(
                        "%.1f".format(d.rating) + " · ${d.ratingsCount} " + if (d.ratingsCount == 1) "rating" else "ratings",
                        fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                    )
                } else {
                    Text("No ratings yet", fontSize = 12.sp, color = Faint)
                }
            }
        }

        if (d == null) {
            Text(
                if (published) "Couldn't load the details. Pull down to try again."
                else "Your venue's address, amenities and rules show here once it's live on Haraan.",
                fontSize = 13.sp, color = Muted, lineHeight = 18.sp,
                modifier = Modifier.padding(start = 18.dp, end = 18.dp, top = 6.dp, bottom = 16.dp),
            )
            return@Column
        }

        d.tagline?.let {
            Text(it, fontSize = 13.sp, color = Muted, modifier = Modifier.padding(start = 18.dp, end = 18.dp, top = 2.dp))
        }

        // Address, with the two things anyone does with one.
        Spacer(Modifier.height(14.dp))
        Column(Modifier.padding(horizontal = 18.dp).fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Soft).padding(14.dp)) {
            Row(verticalAlignment = Alignment.Top) {
                Icon(Icons.Filled.Place, null, tint = Blue, modifier = Modifier.padding(top = 1.dp).size(18.dp))
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text("Address", fontSize = 11.5.sp, color = Muted)
                    Text(
                        readableAddress(d.address) ?: "No address yet",
                        fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
                        color = if (d.address.isNullOrBlank()) Faint else Ink, lineHeight = 19.sp,
                    )
                    if (d.latitude == null || d.longitude == null) {
                        Spacer(Modifier.height(2.dp))
                        Text("No map pin. Players can't get directions.", fontSize = 12.sp, color = Color(0xFFB45309))
                    }
                }
            }
            if (!d.address.isNullOrBlank() || d.latitude != null) {
                Spacer(Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    SmallAction(Icons.Filled.Directions, "Directions", Modifier.weight(1f)) { openDirections(context, d) }
                    if (!d.address.isNullOrBlank()) {
                        SmallAction(Icons.Filled.ContentCopy, "Copy", Modifier.weight(1f)) {
                            val cm = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                            cm.setPrimaryClip(ClipData.newPlainText("Address", d.address))
                            Haptics.confirm(view)
                            Toast.makeText(context, "Address copied", Toast.LENGTH_SHORT).show()
                        }
                    }
                }
            }
        }

        if (d.images.isNotEmpty()) {
            Section("Photos · ${d.images.size}")
            LazyRow(
                contentPadding = PaddingValues(horizontal = 18.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                itemsIndexed(d.images) { _, url ->
                    AsyncImage(
                        model = url, contentDescription = null, contentScale = ContentScale.Crop,
                        modifier = Modifier.size(width = 104.dp, height = 78.dp).clip(RoundedCornerShape(12.dp)).background(Soft),
                    )
                }
            }
        }

        if (d.amenities.isNotEmpty()) {
            Section("Amenities")
            FlowRow(
                Modifier.padding(horizontal = 18.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                d.amenities.forEach { Chip(it) }
            }
        }

        if (d.rules.isNotEmpty()) {
            Section("House rules")
            Column(Modifier.padding(horizontal = 18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                d.rules.forEach { r ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(5.dp).clip(CircleShape).background(Faint))
                        Spacer(Modifier.width(10.dp))
                        Text(r, fontSize = 13.5.sp, color = Ink)
                    }
                }
            }
        }

        if (d.cancellation != null || d.fees.isNotEmpty()) {
            Section("Policy & charges")
            Column(Modifier.padding(horizontal = 18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                d.cancellation?.let { KeyLine("Cancellation", it) }
                if (d.fees.isNotEmpty()) KeyLine("Players also pay", d.fees.joinToString(" · "))
            }
        }

        d.about?.takeIf { it.isNotBlank() }?.let { about ->
            Section("About")
            var open by remember { mutableStateOf(false) }
            Column(Modifier.padding(horizontal = 18.dp)) {
                Text(
                    about.trim(), fontSize = 13.5.sp, color = Ink, lineHeight = 19.sp,
                    maxLines = if (open) Int.MAX_VALUE else 4, overflow = TextOverflow.Ellipsis,
                )
                if (about.lines().size > 4 || about.length > 220) {
                    Text(
                        if (open) "Show less" else "Read more", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue,
                        modifier = Modifier.padding(top = 4.dp).pressableTile(8.dp) { open = !open },
                    )
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        Row(
            Modifier.fillMaxWidth().pressableTile(0.dp, pressedScale = 0.99f, onClick = onSupport)
                .padding(horizontal = 18.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text("Something wrong or missing here?", fontSize = 13.sp, color = Muted, modifier = Modifier.weight(1f))
            Text("Ask Haraan to update", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue)
        }
    }
}

@Composable
private fun Section(title: String) {
    Text(
        title, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Muted,
        modifier = Modifier.padding(start = 18.dp, end = 18.dp, top = 18.dp, bottom = 8.dp),
    )
}

@Composable
private fun Chip(text: String) {
    Text(
        text, fontSize = 12.5.sp, fontWeight = FontWeight.Medium, color = Ink,
        modifier = Modifier.clip(RoundedCornerShape(10.dp)).background(Soft)
            .border(1.dp, Hairline, RoundedCornerShape(10.dp)).padding(horizontal = 11.dp, vertical = 7.dp),
    )
}

@Composable
private fun KeyLine(label: String, value: String) {
    Column {
        Text(label, fontSize = 11.5.sp, color = Muted)
        Text(value, fontSize = 13.5.sp, color = Ink, lineHeight = 19.sp)
    }
}

@Composable
private fun SmallAction(icon: ImageVector, label: String, modifier: Modifier, onClick: () -> Unit) {
    Row(
        modifier.pressableTile(10.dp, onClick = onClick).clip(RoundedCornerShape(10.dp)).background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(10.dp)).padding(vertical = 10.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = Blue, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Blue)
    }
}
