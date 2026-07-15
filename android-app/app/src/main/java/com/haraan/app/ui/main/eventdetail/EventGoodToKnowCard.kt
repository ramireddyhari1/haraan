package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChildCare
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.EventSeat
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Language
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Terrain
import androidx.compose.material.icons.filled.VerifiedUser
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.GoodToKnowItem
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/**
 * "Good to Know" — a scannable 2-column grid of admin-authored attributes
 * (language, age limit, kid/pet friendly, layout, seating…). Each row is an
 * icon + a muted label + a bold value. Renders nothing when the host set none.
 */
@Composable
fun EventGoodToKnowCard(
    items: List<GoodToKnowItem>,
    modifier: Modifier = Modifier
) {
    if (items.isEmpty()) return

    Surface(
        color = HaraanColors.Background,
        shape = RoundedCornerShape(HaraanRadius.Medium),
        border = BorderStroke(1.dp, HaraanColors.BorderLight),
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium)
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(18.dp)
        ) {
            Text(
                text = "Good to Know",
                style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary)
            )

            // Two per row; a trailing odd item gets a phantom spacer so it stays
            // left-aligned in its column rather than stretching full width.
            items.chunked(2).forEach { pair ->
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    pair.forEach { item ->
                        GoodToKnowCell(item = item, modifier = Modifier.weight(1f))
                    }
                    if (pair.size == 1) Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun GoodToKnowCell(item: GoodToKnowItem, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.Top
    ) {
        // Tonal icon chip — soft brand-blue tile gives the row depth and a
        // consistent left rail even when values wrap to two lines.
        Box(
            modifier = Modifier
                .size(36.dp)
                .background(
                    color = HaraanColors.EventsBlue.copy(alpha = 0.10f),
                    shape = RoundedCornerShape(10.dp)
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = iconFor(item.icon),
                contentDescription = null,
                tint = HaraanColors.EventsBlue,
                modifier = Modifier.size(18.dp)
            )
        }
        Column(
            modifier = Modifier.padding(top = 1.dp),
            verticalArrangement = Arrangement.spacedBy(2.dp)
        ) {
            Text(
                text = item.label.uppercase(),
                style = HaraanTypography.BodyMedium.copy(
                    fontSize = 10.sp,
                    lineHeight = 12.sp,
                    letterSpacing = 0.6.sp,
                    color = HaraanColors.TextMuted,
                    fontWeight = FontWeight.Bold
                ),
                maxLines = 1
            )
            Text(
                text = item.value,
                style = HaraanTypography.BodyMedium.copy(
                    fontSize = 14.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.SemiBold,
                    lineHeight = 18.sp
                )
            )
        }
    }
}

/** Maps the API's stable icon key to a vector. Unknown keys fall back to info. */
private fun iconFor(key: String): ImageVector = when (key) {
    "language" -> Icons.Filled.Language
    "age"      -> Icons.Filled.VerifiedUser
    "kids"     -> Icons.Filled.ChildCare
    "pets"     -> Icons.Filled.Pets
    "layout"   -> Icons.Filled.Terrain
    "seating"  -> Icons.Filled.EventSeat
    "duration" -> Icons.Filled.Schedule
    "entry"    -> Icons.Filled.ConfirmationNumber
    else       -> Icons.Filled.Info
}
