package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Directions
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography
import com.haraan.app.ui.util.VenueMap
import com.haraan.app.ui.util.openMap

/**
 * A Google-map preview card for the event's venue — a Static Maps image with a red
 * pin, a "VENUE LOCATION" caption over the venue name, and a Directions pill that
 * opens the user's maps app. Coordinates resolve from (1) the host-set event
 * lat/lng, (2) coordinates embedded in the pasted maps link, else (3) a geocode of
 * the venue string. Renders nothing until a location is available, so it never
 * shows an empty/wrong map.
 */
@Composable
fun EventVenueMap(
    venue: String,
    mapLink: String,
    latitude: Double?,
    longitude: Double?,
    modifier: Modifier = Modifier
) {
    if (venue.isBlank() || !VenueMap.hasKey) return

    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current

    val name = venue.substringBefore(",").trim().ifBlank { venue }
    val address = venue.substringAfter(",", "").trim()

    // Resolve coordinates once per (event) — explicit → parsed link → geocode.
    var coords by remember(venue, mapLink, latitude, longitude) {
        mutableStateOf(
            when {
                latitude != null && longitude != null -> latitude to longitude
                else -> VenueMap.coordsFromMapLink(mapLink)
            }
        )
    }
    LaunchedEffect(venue, mapLink, latitude, longitude) {
        if (coords == null) {
            coords = VenueMap.geocode(venue)
        }
    }

    val resolved = coords ?: return

    Column(modifier = modifier.padding(horizontal = HaraanSpacing.Medium)) {
        Surface(
            shape = RoundedCornerShape(HaraanRadius.Large),
            color = HaraanColors.Surface,
            border = BorderStroke(1.dp, HaraanColors.BorderLight),
            modifier = Modifier.fillMaxWidth(),
            onClick = {
                haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                openMap(context, mapLink, venue)
            }
        ) {
            Column {
                // Map image with a bottom gradient scrim carrying the location label.
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(180.dp)
                ) {
                    AsyncImage(
                        model = VenueMap.staticMapUrl(resolved.first, resolved.second),
                        contentDescription = "Map showing $name",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize()
                    )
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(72.dp)
                            .align(Alignment.BottomCenter)
                            .background(
                                Brush.verticalGradient(
                                    listOf(Color.Transparent, Color(0xCC0F172A))
                                )
                            )
                    )
                    Text(
                        text = "VENUE LOCATION",
                        style = HaraanTypography.LabelSmall.copy(
                            color = Color.White.copy(alpha = 0.85f),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            letterSpacing = 1.sp
                        ),
                        modifier = Modifier
                            .align(Alignment.BottomStart)
                            .padding(start = 16.dp, bottom = 12.dp)
                    )
                }

                // Address row + Directions action.
                Row(
                    modifier = Modifier.padding(HaraanSpacing.Medium),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
                ) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(RoundedCornerShape(HaraanRadius.Small))
                            .background(HaraanColors.EventsBlue.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.LocationOn,
                            contentDescription = null,
                            tint = HaraanColors.EventsBlue,
                            modifier = Modifier.size(20.dp)
                        )
                    }

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = name,
                            style = HaraanTypography.TitleMedium.copy(
                                fontSize = 15.sp,
                                color = HaraanColors.TextPrimary,
                                fontWeight = FontWeight.Bold
                            ),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        if (address.isNotBlank()) {
                            Text(
                                text = address,
                                style = HaraanTypography.BodyMedium.copy(
                                    fontSize = 13.sp,
                                    color = HaraanColors.TextSecondary
                                ),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }

                    Surface(
                        onClick = {
                            haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            openMap(context, mapLink, venue)
                        },
                        shape = RoundedCornerShape(HaraanRadius.Small),
                        color = HaraanColors.EventsBlue
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                Icons.Default.Directions,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                            Text(
                                text = "Directions",
                                style = HaraanTypography.LabelSmall.copy(color = Color.White),
                                maxLines = 1,
                                softWrap = false
                            )
                        }
                    }
                }
            }
        }
    }
}
