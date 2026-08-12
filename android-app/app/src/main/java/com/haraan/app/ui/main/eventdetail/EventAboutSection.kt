package com.haraan.app.ui.main.eventdetail

import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.clickable
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

private const val OVERVIEW_COLLAPSED_LINES = 6

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

    var expanded by remember(overview) { mutableStateOf(false) }
    // Only surface the toggle once we know the text is actually clamped.
    var isClamped by remember(overview) { mutableStateOf(false) }

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
            ),
            maxLines = if (expanded) Int.MAX_VALUE else OVERVIEW_COLLAPSED_LINES,
            overflow = TextOverflow.Ellipsis,
            onTextLayout = { result ->
                if (!expanded) {
                    isClamped = result.hasVisualOverflow
                }
            },
            modifier = Modifier.animateContentSize()
        )

        if (isClamped || expanded) {
            Spacer(modifier = Modifier.height(HaraanSpacing.Small))
            Text(
                text = if (expanded) "Read less" else "Read more",
                style = HaraanTypography.BodyLarge.copy(
                    color = HaraanColors.EventsBlue
                ),
                modifier = Modifier.clickable { expanded = !expanded }
            )
        }
    }
}
