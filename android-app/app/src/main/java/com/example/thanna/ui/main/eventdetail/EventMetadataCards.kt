package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
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
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

data class MetadataItem(
    val icon: ImageVector,
    val primary: String,
    val secondary: String
)

@Composable
fun EventMetadataCards(
    date: String,
    venue: String,
    modifier: Modifier = Modifier
) {
    // Parse date string into components
    val parsed = remember(date) { parseDateForCards(date) }

    val items = listOf(
        MetadataItem(
            icon = Icons.Default.CalendarMonth,
            primary = parsed.dayOfWeek,
            secondary = parsed.dateShort
        ),
        MetadataItem(
            icon = Icons.Default.LocationOn,
            primary = venue.substringBefore(",").trim(),
            secondary = venue.substringAfter(",", "").trim().ifBlank { "Venue" }
        ),
        MetadataItem(
            icon = Icons.Default.Schedule,
            primary = parsed.time,
            secondary = "Doors Open"
        )
    )

    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        items.forEach { item ->
            Surface(
                color = HaraanColors.Background,
                shape = RoundedCornerShape(HaraanRadius.Medium),
                border = BorderStroke(1.dp, HaraanColors.BorderLight),
                modifier = Modifier.weight(1f)
            ) {
                Column(
                    modifier = Modifier.padding(vertical = HaraanSpacing.Compact, horizontal = HaraanSpacing.Small),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(
                        imageVector = item.icon,
                        contentDescription = null,
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(20.dp)
                    )
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
                            fontSize = 12.sp,
                            color = HaraanColors.TextMuted
                        ),
                        textAlign = TextAlign.Center,
                        maxLines = 1
                    )
                }
            }
        }
    }
}

private data class ParsedDate(
    val dayOfWeek: String,
    val dateShort: String,
    val time: String
)

private fun parseDateForCards(dateStr: String): ParsedDate {
    // Handle common formats like "Saturday, 26 June, 7:00 PM" or "SAT • 13 JUN • 7:00 PM"
    val cleaned = dateStr.replace("•", ",").replace("  ", " ").trim()
    val parts = cleaned.split(",").map { it.trim() }

    return when {
        parts.size >= 3 -> ParsedDate(
            dayOfWeek = parts[0].take(3).uppercase(),
            dateShort = parts[1],
            time = parts[2]
        )
        parts.size == 2 -> ParsedDate(
            dayOfWeek = parts[0].take(3).uppercase(),
            dateShort = parts[1],
            time = "TBA"
        )
        else -> ParsedDate(
            dayOfWeek = cleaned.take(3).uppercase(),
            dateShort = cleaned,
            time = "TBA"
        )
    }
}
