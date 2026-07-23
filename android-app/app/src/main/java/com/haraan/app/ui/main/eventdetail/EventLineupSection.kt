package com.haraan.app.ui.main.eventdetail

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.util.lerp
import com.haraan.app.data.LineupArtist
import com.haraan.app.ui.components.HaraanImage
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography
import kotlin.math.absoluteValue

/**
 * "Who takes the stage" — a center-focused coverflow of performer cards. The
 * middle card sits full-size and sharp; neighbours shrink and fade, with the
 * next/previous cards peeking in from the edges. Hidden when the host set none.
 */
@Composable
fun EventLineupSection(
    artists: List<LineupArtist>,
    modifier: Modifier = Modifier
) {
    if (artists.isEmpty()) return

    Column(modifier = modifier.fillMaxWidth()) {
        Text(
            text = "Who takes the stage",
            style = HaraanTypography.SectionTitle.copy(color = HaraanColors.TextPrimary),
            modifier = Modifier.padding(horizontal = HaraanSpacing.Medium)
        )

        Spacer(Modifier.height(HaraanSpacing.Medium))

        val pagerState = rememberPagerState(pageCount = { artists.size })

        HorizontalPager(
            state = pagerState,
            // Side padding lets the neighbouring cards peek in at the edges.
            contentPadding = PaddingValues(horizontal = 64.dp),
            pageSpacing = 12.dp,
            modifier = Modifier.fillMaxWidth()
        ) { page ->
            // Distance of this page from the settled centre, 0 (centre) → 1 (a
            // full page away). Drives the scale + fade coverflow transform.
            val offset = (
                (pagerState.currentPage - page) + pagerState.currentPageOffsetFraction
            ).absoluteValue.coerceIn(0f, 1f)

            val scale = lerp(0.84f, 1f, 1f - offset)
            val fade = lerp(0.45f, 1f, 1f - offset)

            LineupCard(
                artist = artists[page],
                modifier = Modifier
                    .fillMaxWidth()
                    .height(300.dp)
                    .graphicsLayer {
                        scaleX = scale
                        scaleY = scale
                        alpha = fade
                    }
            )
        }
    }
}

@Composable
private fun LineupCard(artist: LineupArtist, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(20.dp))
            .background(HaraanColors.TextPrimary)
    ) {
        if (artist.imageUrl.isNotBlank()) {
            HaraanImage(
                model = artist.imageUrl,
                contentDescription = artist.name,
                modifier = Modifier.fillMaxSize()
            )
        }

        // Bottom scrim so the name/subtitle stay legible over any photo.
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        0.45f to Color.Transparent,
                        1f to Color.Black.copy(alpha = 0.78f)
                    )
                )
        )

        Column(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .padding(horizontal = 18.dp, vertical = 18.dp)
        ) {
            Text(
                text = artist.name,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 18.sp,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                ),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            if (artist.subtitle.isNotBlank()) {
                Text(
                    text = artist.subtitle,
                    style = HaraanTypography.BodyMedium.copy(
                        fontSize = 13.sp,
                        color = Color.White.copy(alpha = 0.82f)
                    ),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}
