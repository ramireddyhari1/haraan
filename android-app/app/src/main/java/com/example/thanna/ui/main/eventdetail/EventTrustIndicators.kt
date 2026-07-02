package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

/** A reassurance strip — the booking-confidence cues a premium ticketing flow shows. */
@Composable
fun EventTrustIndicators(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        // Trust cues are reassurance, not brand moments — keep them quiet and
        // monochrome so they don't compete with the one accent (EventsBlue).
        TrustChip(Icons.Default.CheckCircle, "Verified", Modifier.weight(1f))
        TrustChip(Icons.Default.Lock, "Secure", Modifier.weight(1f))
        TrustChip(Icons.Default.Bolt, "Instant", Modifier.weight(1f))
    }
}

@Composable
private fun TrustChip(icon: ImageVector, label: String, modifier: Modifier) {
    Surface(
        color = HaraanColors.Background,
        shape = RoundedCornerShape(HaraanRadius.Medium),
        modifier = modifier
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Icon(icon, contentDescription = null, tint = HaraanColors.TextSecondary, modifier = Modifier.size(16.dp))
            Text(
                label,
                style = HaraanTypography.TitleMedium.copy(fontSize = 12.sp, color = HaraanColors.TextPrimary),
                maxLines = 1,
                softWrap = false
            )
        }
    }
}
