package com.haraan.app.ui.components

import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.animations.pressScale
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanTypography

@Composable
fun HaraanButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    containerColor: Color = HaraanColors.EventsBlue,
    contentColor: Color = Color.White
) {
    val interactionSource = remember { MutableInteractionSource() }

    Button(
        onClick = onClick,
        interactionSource = interactionSource,
        modifier = modifier.pressScale(interactionSource),
        colors = ButtonDefaults.buttonColors(
            containerColor = containerColor,
            contentColor = contentColor
        ),
        shape = RoundedCornerShape(HaraanRadius.Medium),
        contentPadding = PaddingValues(horizontal = 24.dp, vertical = 12.dp)
    ) {
        Text(
            text = text,
            style = HaraanTypography.TitleMedium.copy(
                color = contentColor,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold
            )
        )
    }
}
