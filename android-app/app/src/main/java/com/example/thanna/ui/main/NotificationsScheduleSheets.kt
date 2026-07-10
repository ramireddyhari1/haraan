package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors

/**
 * Bell icon → in-app notifications. There is no `notifications` table/route in the backend yet
 * ([[haraan-realtime-updates]] Reverb backbone exists but nothing persists a per-user feed), so
 * this is an honest, polished empty state rather than a faked list. It IS a working screen —
 * it opens, reads intentional, and is the seam we deepen into a real feed once the API exists.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsSheet(onDismiss: () -> Unit) {
    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 32.dp),
        ) {
            Text("Notifications", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = HaraanColors.TextPrimary)
            Spacer(Modifier.height(28.dp))
            Column(
                modifier = Modifier.fillMaxWidth().padding(vertical = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Box(
                    modifier = Modifier
                        .size(72.dp)
                        .clip(CircleShape)
                        .background(Color(0xFFEFF3F8)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Outlined.NotificationsNone,
                        contentDescription = null,
                        tint = HaraanColors.GameHubDeep,
                        modifier = Modifier.size(34.dp),
                    )
                }
                Spacer(Modifier.height(16.dp))
                Text(
                    "You're all caught up",
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    color = HaraanColors.TextPrimary,
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    "We'll let you know here about match invites, booking updates and new events near you.",
                    fontSize = 13.sp,
                    color = HaraanColors.TextSecondary,
                    modifier = Modifier.padding(horizontal = 24.dp),
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                )
            }
        }
    }
}

// The old "My Schedule" sheet lived here: a second surface over the same
// /api/bookings data, and the only place an entry pass could actually be opened.
// Bookings now have exactly one home — the account screen's Tickets lane — and the
// header calendar icon opens it directly.
