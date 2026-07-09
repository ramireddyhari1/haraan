package com.example.thanna.ui.main

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.clip
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.LocationState

enum class LocationPillStyle { Events, Home, GameHub }

/**
 * Flat location lockup (no chip background): pin · city (bold) · region/radius below.
 */
@Composable
fun LocationPill(
    state: LocationState,
    expanded: Boolean,
    style: LocationPillStyle,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    // When set, the active search radius is shown in the sub-line (0 == "Any").
    radiusKm: Int? = null,
) {
    val isDark = style == LocationPillStyle.Home || style == LocationPillStyle.GameHub
    // GameHub uses a flat identity lockup (no boxed chip) so it doesn't compete with the
    // frosted utility icons on the trailing edge — location reads as identity, not a control.
    val isFlat = style == LocationPillStyle.GameHub || style == LocationPillStyle.Events
    val contentColor = if (isDark) Color.White else Color(0xFF0F172A)
    // On the dark green hero the pin stays white so it recedes into the brand instead of
    // popping as an off-brand cyan sticker; light surfaces keep the blue pin.
    val pinColor = if (isDark) Color.White else Color(0xFF3B82F6)
    val captionColor = if (isDark) Color.White.copy(alpha = 0.7f) else Color(0xFF64748B)

    val locating = state is LocationState.Locating
    val area = (state as? LocationState.Resolved)?.area ?: ""
    val city = when (state) {
        is LocationState.Resolved -> state.city
        LocationState.Locating -> "Locating…"
        LocationState.Denied -> "Enable location"
        LocationState.Unavailable -> "Set location"
        else -> "Set location"
    }
    val district = (state as? LocationState.Resolved)?.district ?: ""
    val hasCity = state is LocationState.Resolved

    fun radiusLabel() = radiusKm?.let { if (it == 0) "Any" else "$it km" }

    val primary = if (locating) "Locating…" else if (area.isNotBlank()) area else city
    val secondary = when {
        locating -> "Finding you"
        hasCity -> {
            val place = if (area.isNotBlank()) city else district
            listOfNotNull(place.ifBlank { null }, radiusLabel())
                .joinToString(" · ")
                .ifBlank { "Your Location" }
        }
        else -> "Tap to set"
    }

    // Pin drops in when a location resolves; pulses while detecting.
    val drop by animateFloatAsState(if (hasCity) 0f else -9f, spring(stiffness = Spring.StiffnessMediumLow), label = "pinDrop")
    val pulse = rememberInfiniteTransition(label = "pinPulse")
    val pulseAlpha by pulse.animateFloat(
        initialValue = 0.35f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(700), RepeatMode.Reverse),
        label = "pulseAlpha"
    )

    // Frosted-glass pill on the dark hero — reads as a deliberate, tappable control and
    // visually echoes the frosted bell/profile circles on the trailing edge.
    val pillModifier = if (isDark && !isFlat) {
        Modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White.copy(alpha = 0.10f))
            .border(1.dp, Color.White.copy(alpha = 0.16f), RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(start = 10.dp, end = 12.dp, top = 7.dp, bottom = 7.dp)
    } else {
        Modifier
            .clickable(onClick = onClick)
            .padding(vertical = 4.dp)
    }

    Row(
        modifier = modifier.then(pillModifier),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(7.dp),
    ) {
        Icon(
            imageVector = Icons.Default.LocationOn,
            contentDescription = null,
            tint = pinColor,
            modifier = Modifier
                .size(20.dp)
                .graphicsLayer {
                    translationY = drop * density
                    if (locating) alpha = pulseAlpha
                },
        )
        Column {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(2.dp),
            ) {
                Text(
                    text = primary,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    color = contentColor,
                    modifier = Modifier.widthIn(max = 170.dp),
                )
                if (!locating) {
                    Icon(
                        imageVector = Icons.Default.KeyboardArrowDown,
                        contentDescription = "Change location",
                        tint = contentColor.copy(alpha = 0.7f),
                        modifier = Modifier.size(18.dp),
                    )
                }
            }
            Text(
                text = secondary,
                fontSize = 11.sp,
                fontWeight = FontWeight.Medium,
                color = captionColor,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = if (locating) Modifier.graphicsLayer { alpha = pulseAlpha } else Modifier,
            )
        }
    }
}
