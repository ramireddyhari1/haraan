package com.haraan.app.ui.main

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.CityOption
import com.haraan.app.data.LocationState
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import androidx.compose.ui.platform.LocalContext

private val Accent = HaraanColors.EventsBlue
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Hairline = Color(0xFFE9EDF3)
private val Track = Color(0xFFF1F5F9)

/**
 * Where the user is.
 *
 * Built as a set of real controls rather than a stack of labelled form rows: the
 * current-location row is a card with a live status line, the city search is a field
 * that lights up when focused, and every tappable surface answers the finger through
 * [pressable]. Copy stays on things the app actually knows — the geocoded area, the
 * Plus Code — because a picker that names your street reads like software.
 *
 * The search radius deliberately isn't exposed here: it's a tuning knob, not a
 * decision the user should have to make to pick a city. It stays at its default in
 * `MainScreen` and keeps filtering the venue list.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LocationPickerSheet(
    state: LocationState,
    recents: List<CityOption>,
    onUseCurrentLocation: () -> Unit,
    onSelectCity: (CityOption) -> Unit,
    onDismiss: () -> Unit,
    // Sourced from the shared cities.json catalog; falls back to popular-only when empty.
    popularCities: List<CityOption> = emptyList(),
    allCities: List<CityOption> = emptyList(),
) {
    var citySearch by remember { mutableStateOf("") }
    val q = citySearch.trim()
    val filteredRecents = recents.filter { q.isBlank() || it.name.contains(q, ignoreCase = true) }
    // Blank query → the popular shortlist; typing → search the whole catalog.
    val cityResults = if (q.isBlank()) popularCities
        else allCities.filter { it.name.contains(q, ignoreCase = true) }

    val resolved = state as? LocationState.Resolved
    val currentCity = resolved?.city.orEmpty()
    val focusManager = LocalFocusManager.current
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
        dragHandle = { SheetGrip() },
    ) {
        // Fixed height rather than wrap-content: the list is the only part that scrolls,
        // so the header and controls stay put while typing filters the results, and a
        // long catalog can never push the title off a short screen.
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.88f)
                .imePadding()
                .padding(horizontal = 20.dp),
        ) {
            SheetHeader(state = state, onDismiss = onDismiss)

            Spacer(Modifier.height(18.dp))

            CurrentLocationCard(state = state, onClick = onUseCurrentLocation)

            Spacer(Modifier.height(22.dp))

            CitySearchField(
                value = citySearch,
                onValueChange = { citySearch = it },
                onClear = { citySearch = "" },
                onSearch = { focusManager.clearFocus() },
            )

            if (filteredRecents.isNotEmpty()) {
                Spacer(Modifier.height(18.dp))
                SectionLabel("Recent")
                Spacer(Modifier.height(10.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    filteredRecents.take(8).forEach { city ->
                        RecentCityChip(
                            city = city,
                            current = city.name.equals(currentCity, ignoreCase = true),
                            onClick = { onSelectCity(city) },
                        )
                    }
                }
            }

            Spacer(Modifier.height(18.dp))
            SectionLabel(
                if (q.isBlank()) "Popular cities" else "Matches",
                trailing = if (q.isBlank() || cityResults.isEmpty()) null
                    else "${cityResults.size} found",
            )
            Spacer(Modifier.height(4.dp))

            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
            ) {
                itemsIndexed(cityResults) { index, city ->
                    CityRow(
                        city = city,
                        current = city.name.equals(currentCity, ignoreCase = true),
                        onSelect = onSelectCity,
                    )
                    if (index < cityResults.lastIndex) {
                        HorizontalDivider(thickness = 1.dp, color = Track)
                    }
                }
                if (cityResults.isEmpty()) {
                    item {
                        Column(Modifier.padding(top = 22.dp)) {
                            Text(
                                "No city called “$q”",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Ink,
                            )
                            Spacer(Modifier.height(4.dp))
                            Text(
                                "Pick the nearest big city — everything is still ranked by real distance from there.",
                                fontSize = 12.sp,
                                lineHeight = 17.sp,
                                color = Faint,
                            )
                        }
                    }
                }
            }
            Spacer(Modifier.height(18.dp))
        }
    }
}

/** Slim grip instead of Material's default bar — reads as a handle, not a divider. */
@Composable
private fun SheetGrip() {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 12.dp, bottom = 14.dp),
        contentAlignment = Alignment.Center,
    ) {
        Box(
            Modifier
                .size(width = 38.dp, height = 4.dp)
                .clip(RoundedCornerShape(50))
                .background(Color(0xFFCBD5E1)),
        )
    }
}

@Composable
private fun SheetHeader(state: LocationState, onDismiss: () -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top,
    ) {
        Column(Modifier.weight(1f)) {
            Text(
                "Your location",
                fontSize = 22.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = (-0.4).sp,
                color = Ink,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                text = whereYouAre(state),
                fontSize = 13.sp,
                color = Muted,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Box(
            modifier = Modifier
                .pressable(haptic = Feel.SELECT, onClick = onDismiss)
                .size(32.dp)
                .clip(RoundedCornerShape(50))
                .background(Track),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                Icons.Default.Close,
                contentDescription = "Close",
                tint = Muted,
                modifier = Modifier.size(17.dp),
            )
        }
    }
}

/**
 * The primary action, shaped as a row with a real control on each end rather than a
 * full-width tinted slab — the tell that gets read as generated UI.
 */
@Composable
private fun CurrentLocationCard(state: LocationState, onClick: () -> Unit) {
    val locating = state == LocationState.Locating
    val needsPermission = state == LocationState.Denied
    val servicesOff = state == LocationState.ServicesOff
    val context = LocalContext.current

    val title = when {
        needsPermission -> "Turn on location access"
        // Name the actual obstacle. "Couldn't get a fix" sends someone hunting for a
        // problem in the app when the switch they need is in their own settings.
        servicesOff -> "Location is switched off"
        else -> "Use my current location"
    }
    val caption = when (state) {
        LocationState.Locating -> "Reading GPS…"
        LocationState.Denied -> "We only use it to rank what's nearest you"
        LocationState.ServicesOff -> "Tap to open settings and turn it on"
        LocationState.Unavailable -> "No fix yet — check GPS is on, then tap to retry"
        is LocationState.Resolved -> {
            val area = state.area.ifBlank { state.city }
            if (area.isBlank()) "Tap to refresh" else "Pinned to $area · tap to refresh"
        }
        else -> "Sorts venues and events by true distance"
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .pressable(
                haptic = Feel.SELECT,
                onClick = {
                    // Retrying is pointless while the OS switch is off, so the tap takes
                    // them to the switch instead of spinning for twelve seconds.
                    if (servicesOff) {
                        runCatching {
                            context.startActivity(
                                android.content.Intent(android.provider.Settings.ACTION_LOCATION_SOURCE_SETTINGS)
                                    .addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
                            )
                        }
                    } else {
                        onClick()
                    }
                },
            )
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, if (needsPermission || servicesOff) Accent.copy(alpha = 0.35f) else Hairline, RoundedCornerShape(16.dp))
            .padding(horizontal = 14.dp, vertical = 13.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(13.dp),
    ) {
        Box(
            modifier = Modifier
                .size(38.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Accent.copy(alpha = 0.10f)),
            contentAlignment = Alignment.Center,
        ) {
            if (locating) {
                CircularProgressIndicator(
                    modifier = Modifier.size(17.dp),
                    strokeWidth = 2.dp,
                    color = Accent,
                )
            } else {
                Icon(
                    Icons.Default.MyLocation,
                    contentDescription = null,
                    tint = Accent,
                    modifier = Modifier.size(19.dp),
                )
            }
        }
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink)
            Spacer(Modifier.height(2.dp))
            Text(
                caption,
                fontSize = 12.sp,
                color = Faint,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Icon(
            Icons.AutoMirrored.Filled.KeyboardArrowRight,
            contentDescription = null,
            tint = Color(0xFFCBD5E1),
            modifier = Modifier.size(20.dp),
        )
    }
}

@Composable
private fun CitySearchField(
    value: String,
    onValueChange: (String) -> Unit,
    onClear: () -> Unit,
    onSearch: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    val borderColor by animateColorAsState(
        targetValue = if (focused) Accent else Hairline,
        animationSpec = tween(160),
        label = "searchBorder",
    )
    val iconColor by animateColorAsState(
        targetValue = if (focused) Accent else Faint,
        animationSpec = tween(160),
        label = "searchIcon",
    )

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp)
            .clip(RoundedCornerShape(15.dp))
            .background(if (focused) Color.White else Track)
            .border(if (focused) 1.5.dp else 1.dp, borderColor, RoundedCornerShape(15.dp))
            .padding(horizontal = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Icon(
            Icons.Default.Search,
            contentDescription = null,
            tint = iconColor,
            modifier = Modifier.size(18.dp),
        )
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            modifier = Modifier.weight(1f),
            singleLine = true,
            interactionSource = interaction,
            cursorBrush = SolidColor(Accent),
            textStyle = TextStyle(fontSize = 14.5.sp, color = Ink, fontWeight = FontWeight.Medium),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            keyboardActions = KeyboardActions(onSearch = { onSearch() }),
            decorationBox = { inner ->
                if (value.isEmpty()) {
                    Text(
                        "Search any city in India",
                        color = Faint,
                        fontSize = 14.5.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                inner()
            },
        )
        if (value.isNotEmpty()) {
            Box(
                modifier = Modifier
                    .pressable(haptic = Feel.SELECT, onClick = onClear)
                    .size(22.dp)
                    .clip(RoundedCornerShape(50))
                    .background(Track),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.Default.Close,
                    contentDescription = "Clear search",
                    tint = Muted,
                    modifier = Modifier.size(13.dp),
                )
            }
        }
    }
}

@Composable
private fun SectionLabel(text: String, trailing: String? = null) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(
            text.uppercase(),
            fontSize = 10.5.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.1.sp,
            color = Faint,
        )
        if (trailing != null) {
            Text(
                trailing,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Bold,
                color = Accent,
            )
        }
    }
}

@Composable
private fun RecentCityChip(city: CityOption, current: Boolean, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .pressable(haptic = Feel.SELECT, onClick = onClick)
            .clip(RoundedCornerShape(50))
            .background(if (current) Accent.copy(alpha = 0.09f) else Color.White)
            .border(
                1.dp,
                if (current) Accent.copy(alpha = 0.40f) else Hairline,
                RoundedCornerShape(50),
            )
            .padding(start = 11.dp, end = 14.dp, top = 9.dp, bottom = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Icon(
            Icons.Default.LocationOn,
            contentDescription = null,
            tint = if (current) Accent else Color(0xFFCBD5E1),
            modifier = Modifier.size(14.dp),
        )
        Text(
            city.name,
            fontSize = 13.sp,
            fontWeight = if (current) FontWeight.Bold else FontWeight.Medium,
            color = if (current) Accent else Ink,
            maxLines = 1,
        )
    }
}

@Composable
private fun CityRow(city: CityOption, current: Boolean, onSelect: (CityOption) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .pressable(haptic = Feel.SELECT) { onSelect(city) }
            .height(52.dp)
            .padding(end = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(
                city.name,
                fontSize = 15.sp,
                fontWeight = if (current) FontWeight.Bold else FontWeight.Medium,
                color = if (current) Accent else Ink,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            if (city.district.isNotBlank() && !city.district.equals(city.name, ignoreCase = true)) {
                Spacer(Modifier.height(2.dp))
                Text(city.district, fontSize = 11.5.sp, color = Faint, maxLines = 1)
            }
        }
        if (current) {
            Icon(
                Icons.Default.Check,
                contentDescription = "Current location",
                tint = Accent,
                modifier = Modifier.size(18.dp),
            )
        }
    }
}

/** The header's second line: what we actually resolved, down to the Plus Code. */
private fun whereYouAre(state: LocationState): String = when (state) {
    LocationState.Locating -> "Finding you…"
    LocationState.Denied -> "Location access is off"
    LocationState.ServicesOff -> "Location is switched off on this device"
    LocationState.Unavailable -> "Couldn't get a fix"
    is LocationState.Resolved -> {
        val place = listOfNotNull(
            state.area.takeIf { it.isNotBlank() },
            state.city.takeIf { it.isNotBlank() && !it.equals("Unknown", ignoreCase = true) },
        ).joinToString(", ")
        listOfNotNull(
            place.takeIf { it.isNotBlank() } ?: "Location set",
            state.plusCode.takeIf { it.isNotBlank() },
        ).joinToString(" · ")
    }
    else -> "Not set yet — pick a city below"
}
