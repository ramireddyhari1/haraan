package com.haraan.partner.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.ui.theme.haraanCard

/**
 * Enterprise executive KPI card with tabular non-jittering figures, trend indicator,
 * and high-contrast clean surfaces.
 */
@Composable
fun HaraanKpiCard(
    title: String,
    value: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    icon: ImageVector? = null,
    iconTint: Color = HaraanTheme.colors.textSecondary,
    trendPercent: Double? = null,
    trendLabel: String? = null,
    accentBorder: Color? = null,
    onClick: (() -> Unit)? = null
) {
    val shape = HaraanTheme.shapes.card
    val borderCol = accentBorder ?: HaraanTheme.colors.borderHairline

    val baseModifier = modifier
        .haraanCard(radius = 16.dp, borderColor = borderCol)
        .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
        .padding(16.dp)

    Column(
        modifier = baseModifier,
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Header Row: Title & Optional Icon
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = title.uppercase(),
                style = HaraanTheme.typography.overline,
                color = HaraanTheme.colors.textSecondary
            )
            if (icon != null) {
                Box(
                    modifier = Modifier
                        .size(28.dp)
                        .clip(HaraanTheme.shapes.subtle)
                        .background(HaraanTheme.colors.surfaceSubtle),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = iconTint,
                        modifier = Modifier.size(16.dp)
                    )
                }
            }
        }

        // Primary Value (Tabular non-jittering)
        Text(
            text = value,
            style = HaraanTheme.typography.tnumMetricLarge,
            color = HaraanTheme.colors.textPrimary
        )

        // Footer Row: Trend pill and/or Subtitle
        if (trendPercent != null || subtitle != null) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (trendPercent != null) {
                    val isPositive = trendPercent >= 0
                    val trendColor = if (isPositive) HaraanTheme.colors.emeraldPrimary else HaraanTheme.colors.crimsonPrimary
                    val trendBg = if (isPositive) HaraanTheme.colors.emeraldSubtle else HaraanTheme.colors.crimsonSubtle
                    val trendBorder = if (isPositive) HaraanTheme.colors.emeraldBorder else HaraanTheme.colors.crimsonBorder

                    Row(
                        modifier = Modifier
                            .clip(HaraanTheme.shapes.pill)
                            .background(trendBg)
                            .border(1.dp, trendBorder, HaraanTheme.shapes.pill)
                            .padding(horizontal = 6.dp, vertical = 2.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(3.dp)
                    ) {
                        Icon(
                            imageVector = if (isPositive) Icons.AutoMirrored.Filled.TrendingUp else Icons.AutoMirrored.Filled.TrendingDown,
                            contentDescription = null,
                            tint = trendColor,
                            modifier = Modifier.size(12.dp)
                        )
                        val formattedPct = String.format("%s%.1f%%", if (isPositive) "+" else "", trendPercent)
                        val fullTrendText = if (trendLabel != null) "$formattedPct $trendLabel" else formattedPct
                        Text(
                            text = fullTrendText,
                            fontSize = 11.sp,
                            fontWeight = androidx.compose.ui.text.font.FontWeight.Bold,
                            color = trendColor
                        )
                    }
                }

                if (subtitle != null) {
                    Text(
                        text = subtitle,
                        style = HaraanTheme.typography.caption,
                        color = HaraanTheme.colors.textMuted
                    )
                }
            }
        }
    }
}
