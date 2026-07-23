package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

@Composable
fun EventImportantInfoCard(
    infoNotes: List<String>,
    modifier: Modifier = Modifier
) {
    if (infoNotes.isEmpty()) return

    Surface(
        color = HaraanColors.Background,
        shape = RoundedCornerShape(HaraanRadius.Medium),
        border = BorderStroke(1.dp, HaraanColors.BorderLight),
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = HaraanSpacing.Medium)
    ) {
        Column(
            modifier = Modifier.padding(HaraanSpacing.Medium),
            verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
        ) {
            Text(
                text = "Important Information",
                style = HaraanTypography.SectionTitle.copy(
                    color = HaraanColors.TextPrimary
                )
            )

            infoNotes.forEach { note ->
                Text(
                    text = "•  $note",
                    style = HaraanTypography.BodyMedium.copy(
                        color = HaraanColors.TextSecondary,
                        lineHeight = 18.sp
                    )
                )
            }
        }
    }
}
