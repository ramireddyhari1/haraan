package com.haraan.app.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.layout
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.airbnb.lottie.compose.LottieAnimation
import com.airbnb.lottie.compose.LottieCompositionSpec
import com.airbnb.lottie.compose.LottieConstants
import com.airbnb.lottie.compose.animateLottieCompositionAsState
import com.airbnb.lottie.compose.rememberLottieComposition
import com.haraan.app.ui.theme.LocalSectionTheme

/*
 * Campaign takeover — ONE format for every campaign on every lane.
 *
 * While a campaign is live the lane's header (status bar, greeting, search, switch) is painted
 * in the campaign's deep colour by the screen, and [CampaignHeaderStrip] closes it: a short
 * strip carrying the optional decoration (image or Lottie), ending in a scalloped edge that
 * bites into the feed. No headline, no banner card — the feed starts right below, so the app
 * feels dressed for the occasion without losing a screen of content to it.
 *
 * With no campaign live, nothing here renders and the lane keeps its normal look.
 */

@Composable
fun CampaignHeaderStrip(
    /** The colour of whatever sits below, so the scallops look cut out of the header. */
    pageBackground: Color,
    modifier: Modifier = Modifier,
) {
    val theme = LocalSectionTheme.current
    val campaign = theme.campaign ?: return
    val decoration = campaign.decorationUrl

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(if (decoration != null) DECORATED_HEIGHT else PLAIN_HEIGHT)
            .clipToBounds()
            // Starts exactly on the header's deep colour (no seam) and warms toward the accent.
            .background(Brush.verticalGradient(listOf(theme.deep, lerp(theme.deep, theme.primary, 0.45f))))
            .drawWithContent {
                drawContent()
                scallopedEdge(pageBackground, radius = SCALLOP_RADIUS.toPx())
            },
    ) {
        if (decoration != null) {
            val art = Modifier
                .fillMaxSize()
                .align(Alignment.TopCenter)
                .semantics { contentDescription = campaign.name }
            if (campaign.decorationIsLottie) {
                LottieDecoration(url = decoration, modifier = art)
            } else {
                AsyncImage(
                    model = decoration,
                    contentDescription = null,
                    contentScale = ContentScale.FillWidth,
                    alignment = Alignment.TopCenter,
                    modifier = art,
                )
            }
        }
    }
}

/**
 * Loops the animation. A failed download or a malformed file yields no composition, which
 * draws nothing — the strip stays a clean gradient. Lottie honours the system
 * "remove animations" setting on its own.
 */
@Composable
private fun LottieDecoration(url: String, modifier: Modifier) {
    val composition by rememberLottieComposition(LottieCompositionSpec.Url(url))
    val progress by animateLottieCompositionAsState(composition, iterations = LottieConstants.IterateForever)
    LottieAnimation(
        composition = composition,
        progress = { progress },
        contentScale = ContentScale.FillWidth,
        alignment = Alignment.TopCenter,
        modifier = modifier,
    )
}

private val DECORATED_HEIGHT = 84.dp
private val PLAIN_HEIGHT = 22.dp
private val SCALLOP_RADIUS = 7.dp

/** Half-discs of the page colour along the bottom edge: the header looks die-cut. */
private fun DrawScope.scallopedEdge(color: Color, radius: Float) {
    val step = radius * 2.4f
    var x = step / 2f
    while (x - radius < size.width) {
        drawCircle(color = color, radius = radius, center = Offset(x, size.height))
        x += step
    }
}

/**
 * Let a child ignore [padding] of horizontal parent padding, so a full-bleed surface can sit
 * inside a padded column without restructuring it.
 */
fun Modifier.bleedHorizontal(padding: Dp): Modifier = layout { measurable, constraints ->
    val extra = (padding * 2).roundToPx()
    val placeable = measurable.measure(
        constraints.copy(
            minWidth = constraints.minWidth + extra,
            maxWidth = constraints.maxWidth + extra,
        )
    )
    layout(constraints.maxWidth, placeable.height) {
        placeable.place(-extra / 2, 0)
    }
}
