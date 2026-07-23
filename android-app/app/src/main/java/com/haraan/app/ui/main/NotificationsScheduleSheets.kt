package com.haraan.app.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.NotificationInbox
import com.haraan.app.data.NotificationItem
import com.haraan.app.data.NotificationRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.theme.HaraanColors

/**
 * Bell icon → the in-app notification inbox. Backed by /api/notifications: the
 * admin/Haraan team composes messages in the Filament control panel targeted at a
 * segment (everyone / district / state / sport / a user); open apps refetch live
 * via the Reverb `notifications` signal. Opening the sheet marks everything read.
 * An empty or unreachable inbox degrades to the polished caught-up state.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsSheet(onDismiss: () -> Unit) {
    val context = LocalContext.current
    val repo = remember { NotificationRepository() }
    // null = still loading; then a snapshot (possibly empty).
    var inbox by remember { mutableStateOf<NotificationInbox?>(null) }

    LaunchedEffect(Unit) {
        val token = TokenStore.getToken(context)
        if (token == null) {
            inbox = NotificationInbox(0, emptyList())
            return@LaunchedEffect
        }
        inbox = runCatching { repo.getInbox(token) }.getOrDefault(NotificationInbox(0, emptyList()))
        // Clear the unread badge for next time — we keep the loaded snapshot so the
        // user still sees which were new in this glance.
        if ((inbox?.unread ?: 0) > 0) {
            runCatching { repo.markRead(token, null) }
        }
    }

    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 32.dp),
        ) {
            Text("Notifications", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = HaraanColors.TextPrimary)
            Spacer(Modifier.height(16.dp))

            val current = inbox
            when {
                current == null -> NotificationsLoading()
                current.items.isEmpty() -> NotificationsEmpty()
                else -> LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(max = 480.dp),
                ) {
                    items(current.items, key = { it.id }) { item ->
                        NotificationRow(item)
                    }
                }
            }
        }
    }
}

@Composable
private fun NotificationsLoading() {
    Box(
        modifier = Modifier.fillMaxWidth().padding(vertical = 40.dp),
        contentAlignment = Alignment.Center,
    ) {
        CircularProgressIndicator(strokeWidth = 2.dp, color = HaraanColors.GameHubDeep)
    }
}

@Composable
private fun NotificationRow(item: NotificationItem) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(if (item.read) Color(0xFFF6F8FB) else Color(0xFFEAF2FF))
            .padding(14.dp),
    ) {
        // Unread dot — quiet blue, clears once opened.
        Box(
            modifier = Modifier
                .padding(top = 5.dp, end = 12.dp)
                .size(8.dp)
                .clip(CircleShape)
                .background(if (item.read) Color.Transparent else HaraanColors.EventsBlue),
        )
        Column(modifier = Modifier.weight(1f)) {
            Text(
                item.title,
                fontWeight = FontWeight.Bold,
                fontSize = 14.sp,
                color = HaraanColors.TextPrimary,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                item.body,
                fontSize = 13.sp,
                color = HaraanColors.TextSecondary,
                lineHeight = 18.sp,
            )
            if (!item.createdAt.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Text(
                    formatNotificationTime(item.createdAt),
                    fontSize = 11.sp,
                    color = HaraanColors.TextMuted,
                )
            }
        }
    }
}

@Composable
private fun NotificationsEmpty() {
    Column(
        modifier = Modifier.fillMaxWidth().padding(vertical = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            modifier = Modifier.size(72.dp).clip(CircleShape).background(Color(0xFFEFF3F8)),
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
        Text("You're all caught up", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = HaraanColors.TextPrimary)
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

/** ISO-8601 → a short "5 Jul" / "just now" style label. Best-effort, never throws. */
private fun formatNotificationTime(iso: String): String = runCatching {
    val cleaned = iso.take(10) // yyyy-MM-dd
    val parser = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.ENGLISH)
    val d = parser.parse(cleaned) ?: return@runCatching ""
    java.text.SimpleDateFormat("d MMM", java.util.Locale.ENGLISH).format(d)
}.getOrDefault("")

// The old "My Schedule" sheet lived here: a second surface over the same
// /api/bookings data, and the only place an entry pass could actually be opened.
// Bookings now have exactly one home — the account screen's Tickets lane — and the
// header calendar icon opens it directly.
