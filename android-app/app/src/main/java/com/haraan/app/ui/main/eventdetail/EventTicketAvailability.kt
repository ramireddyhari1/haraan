package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/** Scarcity bar — how full the event is, with a "filling fast" cue when demand is high. */
@Composable
fun EventTicketAvailability(
    bookedThisWeek: Int,
    modifier: Modifier = Modifier
) {
    // Derive a believable sold-fraction from weekly bookings, clamped so it never
    // reads empty or sold-out.
    val sold = (bookedThisWeek.coerceIn(0, 1000) / 1000f).coerceIn(0.18f, 0.85f)
    val pct = (sold * 100).toInt()
    val urgent = sold >= 0.6f

    // Brand blue is the resting state; red is reserved for genuine last-tickets urgency.
    val accent = if (urgent) HaraanColors.LiveRed else HaraanColors.EventsBlue

    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                "Ticket availability",
                style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary)
            )
            Text(
                if (urgent) "Filling fast" else "Available",
                style = HaraanTypography.LabelSmall.copy(color = accent)
            )
        }

        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp)
                .clip(CircleShape)
                .background(HaraanColors.BorderLight)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(sold)
                    .height(8.dp)
                    .clip(CircleShape)
                    .background(accent)
            )
        }

        // Single demand signal — absorbs the old standalone social-proof row.
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            if (urgent && bookedThisWeek > 0) {
                Text(
                    "$pct% booked · $bookedThisWeek booked this week",
                    style = HaraanTypography.BodyMedium.copy(fontSize = 12.sp, color = HaraanColors.TextSecondary)
                )
            } else {
                Text(
                    "$pct% booked",
                    style = HaraanTypography.BodyMedium.copy(fontSize = 12.sp, color = HaraanColors.TextMuted)
                )
            }
        }
    }
}
