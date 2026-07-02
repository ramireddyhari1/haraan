package com.example.thanna.ui.main.eventdetail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

@Composable
fun EventHeader(
    title: String,
    date: String = "",
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.padding(horizontal = HaraanSpacing.Medium)
    ) {
        // Title — dominant element (category now lives in the identity row)
        Text(
            text = title,
            style = HaraanTypography.TitleLarge.copy(
                fontSize = 28.sp,
                lineHeight = 34.sp,
                color = HaraanColors.TextPrimary
            )
        )

        // Rich date line under the title, accent-coloured (e.g. "Sun, 5 Jul, 8:00 PM").
        if (date.isNotBlank()) {
            Spacer(Modifier.height(8.dp))
            Text(
                text = date,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 15.sp,
                    lineHeight = 20.sp,
                    color = HaraanColors.EventsBlue,
                    fontWeight = FontWeight.SemiBold
                )
            )
        }
    }
}
