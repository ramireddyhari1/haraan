package com.haraan.partner.daybookings.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

private val AmberColor = Color(0xFFB45309)
private val PrimaryBlue = Color(0xFF1D4ED8)

@Composable
fun OfflineSyncBanner(
    isOffline: Boolean,
    pendingActionsCount: Int,
    isSyncing: Boolean,
    onSyncNow: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val shouldShow = isOffline || pendingActionsCount > 0 || isSyncing

    AnimatedVisibility(
        visible = shouldShow,
        enter = fadeIn() + slideInVertically(),
        exit = fadeOut() + slideOutVertically(),
        modifier = modifier,
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(if (isOffline) Color(0xFFFEF3C7) else Color(0xFFEFF6FF))
                .border(1.dp, if (isOffline) Color(0xFFFDE68A) else Color(0xFFBFDBFE), RoundedCornerShape(12.dp))
                .padding(horizontal = 14.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.weight(1f),
            ) {
                if (isSyncing) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(16.dp),
                        strokeWidth = 2.dp,
                        color = PrimaryBlue,
                    )
                } else if (isOffline) {
                    Icon(Icons.Filled.CloudOff, contentDescription = null, tint = AmberColor, modifier = Modifier.size(16.dp))
                } else {
                    Icon(Icons.Filled.Sync, contentDescription = null, tint = PrimaryBlue, modifier = Modifier.size(16.dp))
                }

                Spacer(Modifier.width(8.dp))

                Text(
                    when {
                        isSyncing -> "Syncing offline bookings..."
                        isOffline && pendingActionsCount > 0 -> "Offline Mode · $pendingActionsCount queued"
                        isOffline -> "Offline Mode · Browsing cached bookings"
                        else -> "$pendingActionsCount offline actions ready to sync"
                    },
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isOffline) AmberColor else PrimaryBlue,
                )
            }

            if (!isSyncing && pendingActionsCount > 0) {
                TextButton(
                    onClick = onSyncNow,
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                ) {
                    Text("Sync Now", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = PrimaryBlue)
                }
            }
        }
    }
}
