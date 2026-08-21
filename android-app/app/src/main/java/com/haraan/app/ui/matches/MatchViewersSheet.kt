@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.MatchViewerItem

/**
 * Who is watching this match right now.
 *
 * Opened from the eye chip, and only for a verified account — the count is public, the room
 * is not. That rule is the server's: the app shows this sheet because the last heartbeat came
 * back saying it may, and the endpoint refuses anyone else regardless of what the app asks.
 *
 * A signed-in viewer appears as themselves — the name, handle, photo and tick already on
 * their public profile, and nothing that isn't. Everyone else appears as "Haraan Guest": the
 * server sends those rows with no identity attached at all, so a guest is genuinely a guest
 * here and not a person with their label filed off. They are listed rather than summarised in
 * a footnote so the room adds up to the number on the chip, which is the whole reason someone
 * opens this.
 */
@Composable
fun MatchViewersSheet(
    watching: Int,
    viewers: List<MatchViewerItem>?,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        dragHandle = { SheetGrip() },
    ) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 24.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Outlined.Visibility, contentDescription = null,
                    tint = CrexColors.TextPrimary, modifier = Modifier.size(18.dp),
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    "Watching now",
                    color = CrexColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    compactViewerCount(watching),
                    color = CrexColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold,
                )
            }
            Spacer(Modifier.height(4.dp))
            Text(
                "Only you can see this list — it comes with your verified badge.",
                color = CrexColors.TextMuted, fontSize = 12.sp,
            )
            Spacer(Modifier.height(16.dp))

            when {
                // Not loaded and not empty are different states, and saying "nobody is here"
                // when the request simply failed is the kind of small lie that costs trust.
                viewers == null -> ViewersNote("Couldn't load the room. Pull the screen to retry.")
                viewers.isEmpty() -> ViewersNote("Nobody else is on this match right now.")
                else -> LazyColumn(
                    Modifier.fillMaxWidth().heightIn(max = 420.dp),
                    verticalArrangement = Arrangement.spacedBy(2.dp),
                ) {
                    items(viewers) { v -> ViewerRow(v) }
                }
            }
        }
    }
}

@Composable
private fun SheetGrip() {
    Box(Modifier.fillMaxWidth().padding(vertical = 12.dp), contentAlignment = Alignment.Center) {
        Box(Modifier.width(36.dp).height(4.dp).clip(RoundedCornerShape(2.dp)).background(Color(0xFFE2E8F0)))
    }
}

@Composable
private fun ViewersNote(text: String) {
    Text(
        text,
        color = CrexColors.TextSecondary, fontSize = 13.sp,
        modifier = Modifier.fillMaxWidth().padding(vertical = 18.dp),
    )
}

@Composable
private fun ViewerRow(v: MatchViewerItem) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (v.guest) {
            // No photo, no handle, no link — there is nothing behind this row to open.
            Box(
                Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFF1F5FA))
                    .border(1.dp, Color(0xFFE4EAF1), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.Outlined.Person, contentDescription = null,
                    tint = Color(0xFF94A3B8), modifier = Modifier.size(18.dp),
                )
            }
        } else {
            HeroCrest(v.name.take(3), Modifier.size(36.dp), iconRef = v.avatar)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    if (v.you) "You" else v.name.ifBlank { "Haraan Guest" },
                    color = if (v.guest) CrexColors.TextSecondary else CrexColors.TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = if (v.guest) FontWeight.Medium else FontWeight.SemiBold,
                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (v.verified) {
                    Spacer(Modifier.width(5.dp))
                    Icon(
                        Icons.Default.Verified, contentDescription = "Verified",
                        tint = Color(0xFF2563EB), modifier = Modifier.size(14.dp),
                    )
                }
            }
            if (v.guest) {
                Text("Not signed in", color = CrexColors.TextMuted, fontSize = 11.5.sp)
            } else if (v.username.isNotBlank()) {
                Text("@${v.username}", color = CrexColors.TextMuted, fontSize = 11.5.sp, maxLines = 1)
            }
        }
    }
}
