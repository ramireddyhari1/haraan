package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

private data class MetadataItem(
    val icon: ImageVector,
    val primary: String,
    val secondary: String,
    val onClick: (() -> Unit)? = null
)

@Composable
fun EventMetadataCards(
    date: String,
    venue: String,
    scheduleAvailable: Boolean = false,
    onVenueClick: () -> Unit = {},
    onScheduleClick: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    val parsed = remember(date) { parseDateForCards(date) }
    val doorsTime = parsed.time.ifBlank { "On time" }

    val items = listOf(
        MetadataItem(
            icon = Icons.Default.CalendarMonth,
            primary = parsed.day,
            secondary = parsed.month
        ),
        MetadataItem(
            icon = Icons.Default.LocationOn,
            primary = venue.substringBefore(",").trim(),
            secondary = "Directions",
            onClick = onVenueClick
        ),
        MetadataItem(
            icon = Icons.Default.Schedule,
            primary = doorsTime,
            // When the host set a run-of-show, the card becomes a tappable entry
            // point to the full schedule; otherwise it's a static doors label.
            secondary = if (scheduleAvailable) "View schedule" else "Doors Open",
            onClick = if (scheduleAvailable) onScheduleClick else null
        )
    )

    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        items.forEach { item ->
            val cardModifier = Modifier.weight(1f)
            val shape = RoundedCornerShape(HaraanRadius.Medium)
            val border = BorderStroke(1.dp, HaraanColors.BorderLight)

            if (item.onClick != null) {
                Surface(
                    onClick = item.onClick,
                    color = HaraanColors.Background,
                    shape = shape,
                    border = border,
                    modifier = cardModifier
                ) { MetadataContent(item) }
            } else {
                Surface(
                    color = HaraanColors.Background,
                    shape = shape,
                    border = border,
                    modifier = cardModifier
                ) { MetadataContent(item) }
            }
        }
    }
}

@Composable
private fun MetadataContent(item: MetadataItem) {
    Column(
        // Tighter than before — less vertical padding so the row reads compact.
        modifier = Modifier.padding(vertical = 10.dp, horizontal = HaraanSpacing.Small),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Box(
            modifier = Modifier
                .size(32.dp)
                .background(
                    color = HaraanColors.EventsBlue.copy(alpha = 0.10f),
                    shape = RoundedCornerShape(9.dp)
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = item.icon,
                contentDescription = null,
                tint = HaraanColors.EventsBlue,
                modifier = Modifier.size(18.dp)
            )
        }
        Text(
            text = item.primary,
            style = HaraanTypography.TitleMedium.copy(
                fontSize = 14.sp,
                color = HaraanColors.TextPrimary,
                fontWeight = FontWeight.Bold
            ),
            textAlign = TextAlign.Center,
            maxLines = 1
        )
        Text(
            text = item.secondary,
            style = HaraanTypography.BodyMedium.copy(
                fontSize = 11.sp,
                color = if (item.onClick != null) HaraanColors.EventsBlue else HaraanColors.TextMuted,
                fontWeight = if (item.onClick != null) FontWeight.SemiBold else FontWeight.Normal
            ),
            textAlign = TextAlign.Center,
            maxLines = 1
        )
    }
}

private data class ParsedDate(
    val day: String,
    val month: String,
    val time: String
)

/**
 * Split a pre-formatted display date like "27 Jun • 21:00" into a day, month
 * and time. Falls back gracefully for other shapes so no card shows "TBA".
 */
private fun parseDateForCards(dateStr: String): ParsedDate {
    val segments = dateStr.split("•", ",").map { it.trim() }.filter { it.isNotBlank() }
    val datePart = segments.getOrNull(0).orEmpty()
    val timePart = segments.getOrNull(1).orEmpty()

    val tokens = datePart.split(" ").filter { it.isNotBlank() }
    val day = tokens.getOrNull(0)?.take(3) ?: datePart.take(3)
    val month = tokens.drop(1).joinToString(" ").ifBlank { "Date" }

    return ParsedDate(day = day, month = month, time = timePart)
}
