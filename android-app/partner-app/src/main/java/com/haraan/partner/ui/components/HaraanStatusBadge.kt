package com.haraan.partner.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme

enum class HaraanStatusType {
    Hold,       // Amber (e.g. 5-Min Temporary Hold)
    Confirmed,  // Turf Emerald
    Paid,       // Turf Emerald
    Pending,    // Slate Neutral
    Variance,   // Crimson
    Alert,      // Crimson
    Locked      // Dark Slate
}

/**
 * Enterprise semantic badge with accessible contrast, clean hairline border,
 * and optional status dot.
 */
@Composable
fun HaraanStatusBadge(
    text: String,
    type: HaraanStatusType,
    modifier: Modifier = Modifier,
    showDot: Boolean = true
) {
    val (bgColor, textColor, borderColor) = when (type) {
        HaraanStatusType.Hold -> Triple(
            HaraanTheme.colors.amberSubtle,
            HaraanTheme.colors.amberPrimary,
            HaraanTheme.colors.amberBorder
        )
        HaraanStatusType.Confirmed,
        HaraanStatusType.Paid -> Triple(
            HaraanTheme.colors.emeraldSubtle,
            HaraanTheme.colors.emeraldPrimary,
            HaraanTheme.colors.emeraldBorder
        )
        HaraanStatusType.Pending -> Triple(
            HaraanTheme.colors.surfaceSubtle,
            HaraanTheme.colors.textSecondary,
            HaraanTheme.colors.borderSubtle
        )
        HaraanStatusType.Variance,
        HaraanStatusType.Alert -> Triple(
            HaraanTheme.colors.crimsonSubtle,
            HaraanTheme.colors.crimsonPrimary,
            HaraanTheme.colors.crimsonBorder
        )
        HaraanStatusType.Locked -> Triple(
            HaraanTheme.colors.surfaceSubtle,
            HaraanTheme.colors.slateDark,
            HaraanTheme.colors.borderHairline
        )
    }

    val shape = HaraanTheme.shapes.pill

    Row(
        modifier = modifier
            .clip(shape)
            .background(bgColor)
            .border(1.dp, borderColor, shape)
            .padding(horizontal = 8.dp, vertical = 3.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(5.dp)
    ) {
        if (showDot) {
            Box(
                modifier = Modifier
                    .size(6.dp)
                    .clip(HaraanTheme.shapes.pill)
                    .background(textColor)
            )
        }

        Text(
            text = text,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = textColor,
            lineHeight = 14.sp
        )
    }
}
