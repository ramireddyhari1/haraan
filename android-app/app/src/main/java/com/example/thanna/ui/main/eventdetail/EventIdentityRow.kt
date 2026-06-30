package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

/** Quick identity signals under the hero: rating · attending · featured tag. */
@Composable
fun EventIdentityRow(
    category: String,
    bookedThisWeek: Int,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        // Category — the single accented signal in this row. Rating (4.8) and the
        // "Featured" pill were removed: neither is backed by a real field, and a
        // fabricated star with no reviews section behind it reads as fake.
        Surface(color = HaraanColors.EventsBlue.copy(alpha = 0.10f), shape = CircleShape) {
            Text(
                category,
                color = HaraanColors.EventsBlue,
                style = HaraanTypography.LabelSmall,
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
            )
        }

        Spacer(Modifier.weight(1f))

        // Real social proof only — shown when we actually have a number.
        if (bookedThisWeek > 0) {
            Text(
                "$bookedThisWeek attending",
                style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextSecondary, fontSize = 13.sp),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}
