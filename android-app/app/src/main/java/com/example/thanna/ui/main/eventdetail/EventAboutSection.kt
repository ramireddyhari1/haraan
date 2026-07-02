package com.example.thanna.ui.main.eventdetail

import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
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

    // Clamp long copy to a few lines with a "Read more" toggle (District-style)
    // so the About block doesn't push everything below it down the page.
    var expanded by remember { mutableStateOf(false) }
    val clampLines = 4

    Column(
        modifier = modifier.padding(horizontal = HaraanSpacing.Medium)
    ) {
        Text(
            text = "About the event",
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
            maxLines = if (expanded) Int.MAX_VALUE else clampLines,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.animateContentSize()
        )

        // Only offer the toggle when there's plausibly more to reveal.
        if (!expanded && overview.length > 160) {
            Spacer(modifier = Modifier.height(6.dp))
            Text(
                text = "Read more  ›",
                style = HaraanTypography.BodyMedium.copy(
                    color = HaraanColors.EventsBlue,
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                ),
                modifier = Modifier.clickable { expanded = true }
            )
        }
    }
}
