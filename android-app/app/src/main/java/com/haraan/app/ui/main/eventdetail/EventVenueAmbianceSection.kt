package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import coil.compose.AsyncImage
import com.haraan.app.data.VenuePhoto
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/**
 * "Venue ambiance" — what the room actually looks like, from the venue's own
 * Google Maps listing. Sits directly below the venue map card: the map places
 * the venue, then these photos show it.
 *
 * Two rules this screen must keep:
 *
 *  - **The credit stays.** Google requires the contributor's name to be shown
 *    with each photo, so every tile carries its caption. Don't remove it to
 *    tidy up the design.
 *  - **Empty means gone.** No listing, too few photos, or an admin who hid this
 *    venue all arrive here as an empty list, and the section draws nothing
 *    rather than an empty shell.
 *
 * The images are proxied by our own backend (never fetched from Google
 * directly), so these are ordinary URLs on the Haraan host.
 */
@Composable
fun EventVenueAmbianceSection(
    photos: List<VenuePhoto>,
    venueName: String = "",
    modifier: Modifier = Modifier
) {
    if (photos.isEmpty()) return

    Column(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(horizontal = HaraanSpacing.Medium),
            verticalAlignment = Alignment.Bottom,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = "Venue ambiance",
                style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary)
            )
            // Says where these came from — they are not the host's own photos.
            Text(
                text = "Photos from Google",
                style = HaraanTypography.LabelSmall.copy(
                    fontSize = 11.5.sp,
                    color = HaraanColors.TextSecondary,
                    fontWeight = FontWeight.Medium
                )
            )
        }

        Spacer(Modifier.height(HaraanSpacing.Small))

        // Which photo the viewer is showing, or null when it's closed.
        var opened by remember { mutableStateOf<VenuePhoto?>(null) }

        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(horizontal = HaraanSpacing.Medium),
            modifier = Modifier.fillMaxWidth()
        ) {
            items(photos) { photo ->
                Box(
                    modifier = Modifier
                        .width(248.dp)
                        .height(180.dp)
                        .clip(RoundedCornerShape(HaraanRadius.Medium))
                        .background(HaraanColors.BorderLight)
                        .clickable { opened = photo }
                ) {
                    AsyncImage(
                        model = photo.url,
                        contentDescription = if (venueName.isBlank()) {
                            "Photo of the venue"
                        } else {
                            "Inside $venueName"
                        },
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize()
                    )

                    if (photo.credit.isNotBlank()) {
                        // Scrim first so the credit stays legible over a bright
                        // photo — these are user uploads, exposure varies wildly.
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(56.dp)
                                .align(Alignment.BottomCenter)
                                .background(
                                    Brush.verticalGradient(
                                        listOf(Color.Transparent, Color(0xCC0F172A))
                                    )
                                )
                        )
                        Text(
                            text = photo.credit,
                            style = HaraanTypography.LabelSmall.copy(
                                fontSize = 10.5.sp,
                                color = Color.White.copy(alpha = 0.92f),
                                fontWeight = FontWeight.Medium
                            ),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier
                                .align(Alignment.BottomStart)
                                .padding(horizontal = 10.dp, vertical = 8.dp)
                        )
                    }
                }
            }
        }

        // Fullscreen viewer. Dialog rather than an overlay inside the scroll
        // content so the system back button closes it and it covers the sticky
        // booking bar. The credit rides along — the attribution requirement
        // applies wherever the photo is shown, blown up most of all.
        opened?.let { photo ->
            Dialog(
                onDismissRequest = { opened = null },
                properties = DialogProperties(usePlatformDefaultWidth = false)
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color(0xF2000000))
                        .clickable { opened = null },
                    contentAlignment = Alignment.Center
                ) {
                    AsyncImage(
                        model = photo.url,
                        contentDescription = if (venueName.isBlank()) {
                            "Photo of the venue"
                        } else {
                            "Inside $venueName"
                        },
                        // Fit, not Crop: the whole photo is the point once it's open.
                        contentScale = ContentScale.Fit,
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp)
                    )

                    if (photo.credit.isNotBlank()) {
                        Text(
                            text = "Photo by ${photo.credit} · Google",
                            style = HaraanTypography.LabelSmall.copy(
                                fontSize = 12.sp,
                                color = Color.White.copy(alpha = 0.75f),
                                fontWeight = FontWeight.Medium
                            ),
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier
                                .align(Alignment.BottomCenter)
                                .padding(horizontal = 24.dp, vertical = 40.dp)
                        )
                    }

                    IconButton(
                        onClick = { opened = null },
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(12.dp)
                    ) {
                        Icon(
                            Icons.Default.Close,
                            contentDescription = "Close photo",
                            tint = Color.White
                        )
                    }
                }
            }
        }
    }
}
