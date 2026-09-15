package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.AdItem
import com.haraan.app.data.ContentRepository
import kotlinx.coroutines.launch

/**
 * The sponsor slot on a match's live board (placement `match_live`), for every sport.
 *
 * Provided once by MatchDetailsScreen so the cricket Live tab, football's summary and all six
 * board screens show the same ad without each screen threading it through its signature.
 */
data class MatchAds(val ads: List<AdItem> = emptyList(), val matchId: String = "")

val LocalMatchAds = compositionLocalOf { MatchAds() }

/**
 * One ad, reported honestly: the impression beacon fires when the bar is first composed on
 * screen (a lazy list only composes what is visible), and a tap is reported before the link
 * opens. The server de-duplicates both per install, so recomposition and the live board's
 * refreshes never inflate a sponsor's numbers.
 */
@Composable
fun MatchAdBar(ad: AdItem, matchId: String = LocalMatchAds.current.matchId) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = ContentRepository()

    LaunchedEffect(ad.id, matchId) {
        repo.trackAd(ctx, ad.id, ContentRepository.AD_IMPRESSION, ad.placement, matchId)
    }

    Column {
        Text(
            "ADVERTISEMENT",
            color = CrexColors.TextMuted,
            fontSize = 9.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.sp,
        )
        Spacer(Modifier.height(6.dp))
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(CrexColors.Surface)
                .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
                .then(
                    if (!ad.ctaUrl.isNullOrBlank()) {
                        Modifier.clickable {
                            scope.launch { repo.trackAd(ctx, ad.id, ContentRepository.AD_CLICK, ad.placement, matchId) }
                            com.haraan.app.ui.openExternalUrl(ctx, ad.ctaUrl)
                        }
                    } else {
                        Modifier
                    },
                )
                .padding(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (!ad.image.isNullOrBlank()) {
                AsyncImage(
                    model = ad.image,
                    contentDescription = ad.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(64.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(CrexColors.Background),
                )
                Spacer(Modifier.width(12.dp))
            }
            Column(modifier = Modifier.weight(1f)) {
                if (!ad.sponsor.isNullOrBlank()) {
                    Text(
                        ad.sponsor.uppercase(),
                        color = CrexColors.TextMuted,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.5.sp,
                    )
                    Spacer(Modifier.height(2.dp))
                }
                Text(
                    ad.title,
                    color = CrexColors.TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                if (!ad.subtitle.isNullOrBlank()) {
                    Spacer(Modifier.height(2.dp))
                    Text(
                        ad.subtitle,
                        color = CrexColors.TextSecondary,
                        fontSize = 12.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            if (!ad.ctaText.isNullOrBlank() && !ad.ctaUrl.isNullOrBlank()) {
                Spacer(Modifier.width(10.dp))
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(CrexColors.AccentBlue)
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                ) {
                    Text(ad.ctaText, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}
