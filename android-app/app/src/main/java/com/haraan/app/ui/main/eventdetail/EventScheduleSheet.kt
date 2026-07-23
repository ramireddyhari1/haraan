package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.background
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ScheduleEntry
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/**
 * Bottom sheet showing the admin-authored run-of-show — a simple timeline of
 * {time, title, note} rows, opened by tapping the "Doors Open" metadata card.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EventScheduleSheet(
    entries: List<ScheduleEntry>,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HaraanColors.Surface,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = HaraanSpacing.Large)
                .padding(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Medium)
        ) {
            Text(
                text = "Schedule",
                style = HaraanTypography.TitleLarge.copy(
                    fontSize = 22.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            )

            entries.forEachIndexed { index, entry ->
                ScheduleRow(entry = entry, isLast = index == entries.lastIndex)
            }
        }
    }
}

@Composable
private fun ScheduleRow(entry: ScheduleEntry, isLast: Boolean) {
    Row(horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Medium)) {
        // Timeline rail — dot + connecting line
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(top = 4.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .clip(CircleShape)
                    .background(HaraanColors.EventsBlue)
            )
            if (!isLast) {
                Box(
                    modifier = Modifier
                        .padding(top = 2.dp)
                        .width(2.dp)
                        .height(38.dp)
                        .background(HaraanColors.BorderLight)
                )
            }
        }

        Column(
            modifier = Modifier.padding(bottom = if (isLast) 0.dp else 4.dp),
            verticalArrangement = Arrangement.spacedBy(2.dp)
        ) {
            Text(
                text = entry.time,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 13.sp,
                    color = HaraanColors.EventsBlue,
                    fontWeight = FontWeight.Bold
                )
            )
            Text(
                text = entry.title,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 15.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.SemiBold
                )
            )
            if (entry.note.isNotBlank()) {
                Text(
                    text = entry.note,
                    style = HaraanTypography.BodyMedium.copy(
                        fontSize = 13.sp,
                        color = HaraanColors.TextSecondary,
                        lineHeight = 18.sp
                    )
                )
            }
        }
    }
}
