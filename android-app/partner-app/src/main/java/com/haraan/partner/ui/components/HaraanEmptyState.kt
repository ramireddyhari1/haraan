package com.haraan.partner.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme

/**
 * Enterprise empty state with polished typography, soft icon pedestal,
 * and optional call-to-action button.
 */
@Composable
fun HaraanEmptyState(
    icon: ImageVector,
    title: String,
    description: String,
    modifier: Modifier = Modifier,
    actionText: String? = null,
    onActionClick: (() -> Unit)? = null
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(HaraanTheme.shapes.pill)
                .background(HaraanTheme.colors.surfaceSubtle),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = HaraanTheme.colors.textSecondary,
                modifier = Modifier.size(28.dp)
            )
        }

        Text(
            text = title,
            style = HaraanTheme.typography.titleMedium,
            color = HaraanTheme.colors.textPrimary,
            textAlign = TextAlign.Center
        )

        Text(
            text = description,
            style = HaraanTheme.typography.bodyMedium,
            color = HaraanTheme.colors.textSecondary,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(horizontal = 16.dp)
        )

        if (actionText != null && onActionClick != null) {
            Spacer(Modifier.height(4.dp))
            HaraanButton(
                text = actionText,
                onClick = onActionClick,
                style = HaraanButtonStyle.Secondary,
                height = 42.dp
            )
        }
    }
}
