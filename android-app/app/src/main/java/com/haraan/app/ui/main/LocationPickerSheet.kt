package com.haraan.app.ui.main

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.graphics.graphicsLayer
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
import com.haraan.app.ui.util.VenueMap
import androidx.compose.ui.platform.LocalContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Accent = HaraanColors.EventsBlue
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Hairline = Color(0xFFE9EDF3)
private val Track = Color(0xFFF1F5F9)

/**
 * Where the user is.
 *
 * Opens on a drawn street map of the place the app is set to ([PlaceMapCard]) with the
 * locate control on it, rather than a stack of labelled form rows. Below it, one search
 * covers both the served-city catalog and Google Places. Picking anything drops the pin
 * onto the new place, and the sheet closes once it has landed. Copy stays on things the
 * app actually knows — the area, the city, the Plus Code, GPS or picked — because a
 * picker that talks in generalities reads like software.
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
    // A Google Places result was tapped. Returns false when it couldn't be resolved,
    // which leaves the sheet open on the list.
    onSelectPlace: (suspend (VenueMap.PlaceSuggestion, String) -> Boolean)? = null,
    pickedByHand: Boolean = false,
) {
    var citySearch by remember { mutableStateOf("") }
    val q = citySearch.trim()
    val filteredRecents = recents.filter { q.isBlank() || it.name.contains(q, ignoreCase = true) }
    // Blank query → the popular shortlist; typing → search the whole catalog.
    val cityResults = if (q.isBlank()) popularCities
        else allCities.filter { it.name.contains(q, ignoreCase = true) }

    // The catalog only knows served cities. Typing also asks Google Places, the same as
    // the web sheet, so an area or village outside cities.json can still be picked.
    // One session token covers the keystrokes and the pick; it's renewed after a pick.
    var sessionToken by remember { mutableStateOf(java.util.UUID.randomUUID().toString()) }
    var placeResults by remember { mutableStateOf<List<VenueMap.PlaceSuggestion>>(emptyList()) }
    var searchingPlaces by remember { mutableStateOf(false) }
    var pickingPlaceId by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    LaunchedEffect(q) {
        if (onSelectPlace == null || q.length < 2) {
            placeResults = emptyList()
            searchingPlaces = false
            return@LaunchedEffect
        }
        searchingPlaces = true
        delay(250) // debounce: a new keystroke cancels this effect before it calls out
        // Drop rows that just repeat a catalog city already listed above.
        val catalogNames = cityResults.map { it.name.lowercase() }.toSet()
        placeResults = VenueMap.autocomplete(q, sessionToken)
            .filterNot { it.main.lowercase() in catalogNames }
        searchingPlaces = false
    }

    val resolved = state as? LocationState.Resolved
    val currentCity = resolved?.city.orEmpty()
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current
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
            SheetHeader(onDismiss = onDismiss)

            Spacer(Modifier.height(14.dp))

            PlaceMapCard(
                state = state,
                pickedByHand = pickedByHand,
                onLocate = {
                    // Retrying is pointless while the OS switch is off, so the tap takes
                    // them to the switch instead of spinning for twelve seconds.
                    if (state == LocationState.ServicesOff) {
                        runCatching {
                            context.startActivity(
                                android.content.Intent(android.provider.Settings.ACTION_LOCATION_SOURCE_SETTINGS)
                                    .addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
                            )
                        }
                    } else {
                        onUseCurrentLocation()
                    }
                },
            )

            Spacer(Modifier.height(18.dp))

            CitySearchField(
                value = citySearch,
                onValueChange = { citySearch = it },
                onClear = { citySearch = "" },
                onSearch = { focusManager.clearFocus() },
            )

            if (filteredRecents.isNotEmpty()) {
                Spacer(Modifier.height(18.dp))
                SectionLabel("Recent")
                Spacer(Modifier.height(4.dp))
                // Vertical padding leaves room for the edge light's glow, which the
                // scroll container would otherwise clip.
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 4.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    filteredRecents.take(8).forEach { city ->
                        RecentTile(
                            city = city,
                            current = city.name.equals(currentCity, ignoreCase = true),
                            onClick = { onSelectCity(city) },
                        )
                    }
                }
            }

            Spacer(Modifier.height(18.dp))
            // No catalog hit while typing: the Places list below carries its own label.
            if (q.isBlank() || cityResults.isNotEmpty()) {
                SectionLabel(
                    if (q.isBlank()) "Popular cities" else "Matches",
                    trailing = if (q.isBlank()) null else "${cityResults.size} found",
                )
                Spacer(Modifier.height(4.dp))
            }

            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
                // Room for the chosen row's edge glow, which the list would clip.
                contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                if (q.isBlank()) {
                    // Nothing typed: the cities as their landmarks, four to a row.
                    items(cityResults.chunked(4)) { row ->
                        Row(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                            row.forEach { city ->
                                CityTile(
                                    city = city,
                                    current = city.name.equals(currentCity, ignoreCase = true),
                                    onSelect = onSelectCity,
                                    modifier = Modifier.weight(1f),
                                )
                            }
                            repeat(4 - row.size) { Spacer(Modifier.weight(1f)) }
                        }
                    }
                } else {
                    itemsIndexed(cityResults) { _, city ->
                        CityRow(
                            city = city,
                            current = city.name.equals(currentCity, ignoreCase = true),
                            onSelect = onSelectCity,
                        )
                    }
                }
                if (placeResults.isNotEmpty()) {
                    item {
                        if (cityResults.isNotEmpty()) Spacer(Modifier.height(16.dp))
                        SectionLabel("Areas & places")
                        Spacer(Modifier.height(4.dp))
                    }
                    itemsIndexed(placeResults) { index, place ->
                        PlaceRow(
                            place = place,
                            picking = pickingPlaceId == place.placeId,
                            onSelect = {
                                val select = onSelectPlace ?: return@PlaceRow
                                if (pickingPlaceId != null) return@PlaceRow
                                pickingPlaceId = place.placeId
                                scope.launch {
                                    val ok = select(place, sessionToken)
                                    sessionToken = java.util.UUID.randomUUID().toString()
                                    if (!ok) pickingPlaceId = null
                                }
                            },
                        )
                    }
                }
                if (q.isNotBlank() && cityResults.isEmpty() && placeResults.isEmpty() && searchingPlaces) {
                    item {
                        Row(
                            Modifier.padding(top = 22.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            RowPin(active = true)
                            Text("Searching places…", fontSize = 13.sp, color = Muted)
                        }
                    }
                }
                if (cityResults.isEmpty() && placeResults.isEmpty() && !searchingPlaces) {
                    item {
                        Column(
                            Modifier
                                .fillMaxWidth()
                                .padding(top = 26.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                        ) {
                            LostMapArt()
                            Spacer(Modifier.height(14.dp))
                            Text(
                                "No place called “$q”",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Ink,
                            )
                            Spacer(Modifier.height(4.dp))
                            Text(
                                "Check the spelling, or pick the nearest town — everything is still ranked by real distance from there.",
                                fontSize = 12.sp,
                                lineHeight = 17.sp,
                                color = Faint,
                                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                                modifier = Modifier.padding(horizontal = 24.dp),
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
private fun SheetHeader(onDismiss: () -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            "Your location",
            modifier = Modifier.weight(1f),
            fontSize = 22.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = (-0.4).sp,
            color = Ink,
        )
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
                        "Search a city or area in India",
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

/** A recently used place. The one you're set to carries the blue edge light. */
@Composable
private fun RecentTile(city: CityOption, current: Boolean, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .pressable(haptic = Feel.SELECT, onClick = onClick)
            .edgeLight(current, 50.dp)
            .clip(RoundedCornerShape(50))
            .background(if (current) Accent.copy(alpha = 0.06f) else Color.White)
            .border(1.dp, if (current) Color.Transparent else Hairline, RoundedCornerShape(50))
            .padding(start = 12.dp, end = 16.dp, top = 9.dp, bottom = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(7.dp),
    ) {
        RowPin(active = false, highlighted = current)
        Text(
            city.name,
            fontSize = 14.sp,
            fontWeight = if (current) FontWeight.Bold else FontWeight.Medium,
            color = if (current) Accent else Ink,
            maxLines = 1,
        )
    }
}

/**
 * One place in the list: a pin, its name, and what we know about it. The chosen one
 * lifts into a card with the blue edge light; the rest sit flat on the sheet.
 */
@Composable
private fun PlaceListRow(
    title: String,
    subtitle: String,
    chosen: Boolean,
    loading: Boolean,
    onClick: () -> Unit,
    landmarkOf: String? = null,
) {
    val tint by animateColorAsState(
        if (chosen) Accent.copy(alpha = 0.035f) else Color.Transparent,
        tween(220),
        label = "rowTint",
    )
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .pressable(haptic = Feel.SELECT, onClick = onClick)
            .edgeLight(chosen, 18.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(tint)
            .padding(horizontal = 10.dp, vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        if (landmarkOf != null && CityLandmarks.has(landmarkOf)) {
            CityLandmark(landmarkOf, selected = chosen, modifier = Modifier.size(34.dp))
        } else {
            RowPin(active = loading, highlighted = chosen)
        }
        Column(Modifier.weight(1f)) {
            Text(
                title,
                fontSize = 15.5.sp,
                fontWeight = if (chosen || loading) FontWeight.Bold else FontWeight.SemiBold,
                color = if (chosen || loading) Accent else Ink,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            if (subtitle.isNotBlank()) {
                Spacer(Modifier.height(2.dp))
                Text(
                    subtitle,
                    fontSize = 12.sp,
                    color = if (chosen) Accent.copy(alpha = 0.75f) else Faint,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        // The tick springs in on the row that was just picked, before the sheet closes.
        val tick by animateFloatAsState(
            targetValue = if (chosen) 1f else 0f,
            animationSpec = spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessMedium),
            label = "rowTick",
        )
        if (tick > 0.01f) {
            Box(
                modifier = Modifier
                    .graphicsLayer {
                        scaleX = tick
                        scaleY = tick
                        alpha = tick.coerceIn(0f, 1f)
                    }
                    .size(26.dp)
                    .clip(RoundedCornerShape(50))
                    .background(Accent),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.Default.Check,
                    contentDescription = "Current location",
                    tint = Color.White,
                    modifier = Modifier.size(15.dp),
                )
            }
        }
    }
}

@Composable
private fun CityRow(city: CityOption, current: Boolean, onSelect: (CityOption) -> Unit) {
    PlaceListRow(
        title = city.name,
        subtitle = when {
            current -> "You're set here"
            city.district.isNotBlank() && !city.district.equals(city.name, ignoreCase = true) -> city.district
            else -> ""
        },
        chosen = current,
        loading = false,
        onClick = { onSelect(city) },
        landmarkOf = city.name,
    )
}

/**
 * A popular city as its landmark over its name. The chosen one re-inks in blue and
 * carries the corner light; a city with no drawing yet shows the pin, never a stand-in.
 */
@Composable
private fun CityTile(
    city: CityOption,
    current: Boolean,
    onSelect: (CityOption) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .pressable(haptic = Feel.SELECT) { onSelect(city) }
            .padding(horizontal = 4.dp)
            .edgeLight(current, 16.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(if (current) Accent.copy(alpha = 0.035f) else Color.Transparent)
            .padding(top = 10.dp, bottom = 9.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(Modifier.size(56.dp), contentAlignment = Alignment.Center) {
            if (CityLandmarks.has(city.name)) {
                CityLandmark(city.name, selected = current, modifier = Modifier.fillMaxSize())
            } else {
                RowPin(active = false, highlighted = current, modifier = Modifier.size(width = 24.dp, height = 30.dp))
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(
            city.name,
            fontSize = 13.sp,
            fontWeight = if (current) FontWeight.Bold else FontWeight.Medium,
            color = if (current) Accent else Ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

/** A Google Places result: the area's name over its "District, State" line. */
@Composable
private fun PlaceRow(place: VenueMap.PlaceSuggestion, picking: Boolean, onSelect: () -> Unit) {
    PlaceListRow(
        title = place.main,
        subtitle = if (picking) "Pinning it…" else place.secondary,
        chosen = false,
        loading = picking,
        onClick = onSelect,
    )
}
