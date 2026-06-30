package com.example.thanna.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius

@Composable
fun HaraanCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    containerColor: Color = HaraanColors.Surface,
    borderColor: Color = HaraanColors.BorderLight,
    content: @Composable ColumnScope.() -> Unit
) {
    val shape = RoundedCornerShape(HaraanRadius.Large)
    val cardModifier = if (onClick != null) {
        modifier
            .shadow(
                elevation = 2.dp,
                shape = shape,
                clip = false,
                ambientColor = Color.Black.copy(alpha = 0.02f),
                spotColor = Color.Black.copy(alpha = 0.06f)
            )
            .clip(shape)
            .clickable { onClick() }
    } else {
        modifier.shadow(
            elevation = 2.dp,
            shape = shape,
            clip = false,
            ambientColor = Color.Black.copy(alpha = 0.02f),
            spotColor = Color.Black.copy(alpha = 0.06f)
        )
    }

    Card(
        modifier = cardModifier,
        shape = shape,
        colors = CardDefaults.cardColors(containerColor = containerColor),
        border = BorderStroke(1.dp, borderColor),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth(), content = content)
    }
}
