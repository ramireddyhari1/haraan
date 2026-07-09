package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

/** Quick identity signals under the hero: category pill + aggregate rating. */
@Composable
fun EventIdentityRow(
    category: String,
    rating: Double,
    ratingsCount: Int,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        // Category — the single accented signal in this row.
        Surface(color = HaraanColors.EventsBlue.copy(alpha = 0.10f), shape = CircleShape) {
            Text(
                category,
                color = HaraanColors.EventsBlue,
                style = HaraanTypography.LabelSmall,
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
            )
        }

        Spacer(Modifier.weight(1f))

        // Real rating only — shown when the event has actually been rated.
        if (rating > 0.0) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Filled.Star,
                    contentDescription = null,
                    tint = Color(0xFFF5A623),
                    modifier = Modifier.size(16.dp)
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    "%.1f".format(rating),
                    style = HaraanTypography.BodyMedium.copy(
                        color = HaraanColors.TextPrimary,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold
                    ),
                    maxLines = 1,
                )
                if (ratingsCount > 0) {
                    Spacer(Modifier.width(4.dp))
                    Text(
                        "($ratingsCount)",
                        style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextSecondary, fontSize = 13.sp),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
        }
    }
}
