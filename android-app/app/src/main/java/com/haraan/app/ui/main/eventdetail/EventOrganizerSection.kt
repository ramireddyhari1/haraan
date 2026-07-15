package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/**
 * Who is running / selling the event. Renders only when we actually have an
 * organizer name — no placeholder host is invented.
 */
@Composable
fun EventOrganizerSection(
    organizer: String,
    subtitle: String,
    modifier: Modifier = Modifier
) {
    if (organizer.isBlank()) return

    Column(modifier = modifier.padding(horizontal = HaraanSpacing.Medium)) {
        Text(
            text = "Organizer",
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
                // Initial avatar — derived from the name, no remote image needed.
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(HaraanColors.EventsBlue.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = organizer.trim().take(1).uppercase(),
                        style = HaraanTypography.TitleLarge.copy(
                            fontSize = 18.sp,
                            color = HaraanColors.EventsBlue,
                            fontWeight = FontWeight.Bold
                        )
                    )
                }

                Column(modifier = Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = organizer,
                            style = HaraanTypography.TitleMedium.copy(
                                fontSize = 15.sp,
                                color = HaraanColors.TextPrimary,
                                fontWeight = FontWeight.Bold
                            ),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Spacer(Modifier.width(4.dp))
                        Icon(
                            Icons.Default.Verified,
                            contentDescription = "Verified organizer",
                            tint = HaraanColors.EventsBlue,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                    if (subtitle.isNotBlank()) {
                        Text(
                            text = subtitle,
                            style = HaraanTypography.BodyMedium.copy(
                                fontSize = 13.sp,
                                color = HaraanColors.TextSecondary
                            ),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
            }
        }
    }
}
