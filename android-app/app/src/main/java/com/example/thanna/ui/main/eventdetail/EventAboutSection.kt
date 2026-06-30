package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

@Composable
fun EventAboutSection(
    title: String,
    venue: String,
    description: String,
    modifier: Modifier = Modifier
) {
    // Structured event notes (highlights / restrictions) now live solely in
    // EventImportantInfoCard — About is a clean prose overview only.
    val overview = description.ifBlank {
        "Join $title at $venue for a memorable live experience. Reserve your spot now — popular dates fill up fast."
    }

    Column(
        modifier = modifier.padding(horizontal = HaraanSpacing.Medium)
    ) {
        Text(
            text = "Overview",
            style = HaraanTypography.SectionTitle.copy(
                color = HaraanColors.TextPrimary
            )
        )

        Spacer(modifier = Modifier.height(HaraanSpacing.Small))

        Text(
            text = overview,
            style = HaraanTypography.BodyLarge.copy(
                color = HaraanColors.TextSecondary,
                lineHeight = 22.sp
            )
        )
    }
}
