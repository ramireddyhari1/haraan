package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Directions
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography
import com.example.thanna.ui.util.openMap

/**
 * Venue block with an actionable "Get directions". Opens the admin's pasted
 * Google Maps link when present, else falls back to a geo: query on the venue
 * string — no fabricated map tile.
 */
@Composable
fun EventVenueSection(
    venue: String,
    mapLink: String = "",
    modifier: Modifier = Modifier
) {
    if (venue.isBlank()) return

    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current

    val name = venue.substringBefore(",").trim().ifBlank { venue }
    val address = venue.substringAfter(",", "").trim()

    Column(modifier = modifier.padding(horizontal = HaraanSpacing.Medium)) {
        Text(
            text = "Venue",
            style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary)
        )

        Spacer(Modifier.height(HaraanSpacing.Small))

        Surface(
            color = HaraanColors.Background,
            shape = RoundedCornerShape(HaraanRadius.Medium),
            border = BorderStroke(1.dp, HaraanColors.BorderLight),
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier.padding(HaraanSpacing.Medium),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
            ) {
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(RoundedCornerShape(HaraanRadius.Small))
                        .background(HaraanColors.EventsBlue.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Default.LocationOn,
                        contentDescription = null,
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(22.dp)
                    )
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = name,
                        style = HaraanTypography.TitleMedium.copy(
                            fontSize = 15.sp,
                            color = HaraanColors.TextPrimary,
                            fontWeight = FontWeight.Bold
                        ),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (address.isNotBlank()) {
                        Text(
                            text = address,
                            style = HaraanTypography.BodyMedium.copy(
                                fontSize = 13.sp,
                                color = HaraanColors.TextSecondary
                            ),
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }

                // Get directions — opens the user's maps app on the venue query.
                Surface(
                    onClick = {
                        haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                        openMap(context, mapLink, venue)
                    },
                    shape = RoundedCornerShape(HaraanRadius.Small),
                    color = HaraanColors.EventsBlue.copy(alpha = 0.10f)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(
                            Icons.Default.Directions,
                            contentDescription = null,
                            tint = HaraanColors.EventsBlue,
                            modifier = Modifier.size(16.dp)
                        )
                        Text(
                            text = "Directions",
                            style = HaraanTypography.LabelSmall.copy(color = HaraanColors.EventsBlue),
                            maxLines = 1,
                            softWrap = false
                        )
                    }
                }
            }
        }
    }
}
