package com.example.thanna.ui.components

import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanTypography
import androidx.compose.foundation.shape.RoundedCornerShape
import kotlinx.coroutines.delay

@Composable
fun HaraanSearchBar(
    query: String,
    onQueryChange: (String) -> Unit,
    placeholder: String,
    activeColor: Color,
    modifier: Modifier = Modifier,
    // When non-empty (and the field is empty/unfocused), the placeholder cross-fades
    // through these hints — the "live" search bar feel of a top-tier app.
    rotatingHints: List<String> = emptyList(),
    // Trailing mic affordance; shown only when a handler is supplied.
    onVoiceClick: (() -> Unit)? = null,
) {
    var isFocused by remember { mutableStateOf(false) }

    // Cycle the hint while the user hasn't typed anything and isn't focused.
    var hintIndex by remember { mutableStateOf(0) }
    val rotate = rotatingHints.isNotEmpty()
    if (rotate) {
        LaunchedEffect(rotatingHints, query, isFocused) {
            if (query.isEmpty() && !isFocused) {
                while (true) {
                    delay(2500)
                    hintIndex = (hintIndex + 1) % rotatingHints.size
                }
            }
        }
    }
    val currentHint = if (rotate) rotatingHints[hintIndex % rotatingHints.size] else placeholder

    Row(
        modifier = modifier
            .fillMaxWidth()
            .height(48.dp)
            .shadow(
                elevation = if (isFocused) 6.dp else 2.dp,
                shape = RoundedCornerShape(HaraanRadius.Large),
                clip = false,
                ambientColor = Color.Black.copy(alpha = 0.03f),
                spotColor = Color.Black.copy(alpha = 0.08f)
            )
            .background(HaraanColors.Surface, RoundedCornerShape(HaraanRadius.Large))
            .border(
                BorderStroke(
                    width = if (isFocused) 1.5.dp else 1.dp,
                    color = if (isFocused) activeColor else HaraanColors.BorderLight
                ),
                RoundedCornerShape(HaraanRadius.Large)
            )
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = Icons.Default.Search,
            contentDescription = "Search",
            tint = HaraanColors.TextSecondary,
            modifier = Modifier.size(20.dp)
        )
        Spacer(modifier = Modifier.width(10.dp))
        BasicTextField(
            value = query,
            onValueChange = onQueryChange,
            modifier = Modifier
                .weight(1f)
                .onFocusChanged { isFocused = it.isFocused },
            singleLine = true,
            textStyle = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontSize = 14.sp),
            cursorBrush = SolidColor(activeColor),
            decorationBox = { innerTextField ->
                Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterStart) {
                    if (query.isEmpty()) {
                        if (rotate) {
                            Crossfade(targetState = currentHint, animationSpec = tween(420), label = "searchHint") { hint ->
                                Text(
                                    text = hint,
                                    color = HaraanColors.TextMuted,
                                    fontSize = 14.sp,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                    fontFamily = HaraanTypography.BodyLarge.fontFamily
                                )
                            }
                        } else {
                            Text(
                                text = placeholder,
                                color = HaraanColors.TextMuted,
                                fontSize = 14.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                fontFamily = HaraanTypography.BodyLarge.fontFamily
                            )
                        }
                    }
                    innerTextField()
                }
            }
        )
        if (onVoiceClick != null) {
            Spacer(modifier = Modifier.width(8.dp))
            Icon(
                imageVector = Icons.Default.Mic,
                contentDescription = "Voice search",
                tint = activeColor,
                modifier = Modifier
                    .size(34.dp)
                    .clip(CircleShape)
                    .clickable(onClick = onVoiceClick)
                    .padding(6.dp)
            )
        }
    }
}
