package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.MoreVert
import androidx.compose.material.icons.outlined.Translate
import androidx.compose.material.icons.outlined.SportsCricket
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import com.haraan.app.R
import com.haraan.app.ui.LanguageDialog
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun MatchHeader(
    state: MatchUiState,
    modifier: Modifier = Modifier,
    /** People watching this match right now (server-counted presence); 0 hides the chip. */
    watching: Int = 0,
    scrollOffset: Int = 0,
    onScoreClick: () -> Unit = {},
    onBack: () -> Unit = {},
    /** Null unless this viewer may see who is watching. */
    onWatchersClick: (() -> Unit)? = null,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
            .statusBarsPadding()
    ) {
        // Top app bar
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            BarIconButton(Icons.AutoMirrored.Filled.ArrowBack, "Back", onClick = onBack)
            Spacer(Modifier.width(12.dp))
            // Title + LIVE take the flexible space so the right cluster is always pushed
            // to the edge — guarantees a gap between LIVE and the Score button.
            Row(
                modifier = Modifier.weight(1f),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "${state.team1} vs ${state.team2}",
                    color = CrexColors.TextPrimary,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = (-0.2).sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false)
                )
                if (state.isLive) {
                    Spacer(Modifier.width(8.dp))
                    LivePill()
                    // Only while live, and only once the server has actually counted
                    // somebody — an empty eye next to a live badge reads as "nobody cares".
                    if (watching > 0) {
                        Spacer(Modifier.width(6.dp))
                        WatchingPill(watching, onClick = onWatchersClick)
                    }
                }
            }

            Spacer(Modifier.width(12.dp))

            // Live-scoring entry — only the match creator gets this.
            if (state.canScore) {
                ScoreButton(onClick = onScoreClick)
                Spacer(Modifier.width(8.dp))
            }
            var showLanguage by remember { mutableStateOf(false) }
            BarIconButton(Icons.Outlined.Translate, stringResource(R.string.language), onClick = { showLanguage = true })
            Spacer(Modifier.width(8.dp))
            BarIconButton(Icons.Outlined.MoreVert, "More")
            if (showLanguage) {
                LanguageDialog(onDismiss = { showLanguage = false })
            }
        }

        // Hairline separating the bar from the hero
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFEAEEF3)))

        // Live score hero
        LiveScoreCard(state = state)
    }
}

@Composable
private fun BarIconButton(icon: ImageVector, desc: String, onClick: (() -> Unit)? = null) {
    Box(
        modifier = Modifier
            .size(38.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF1F5FA))
            .border(1.dp, Color(0xFFE4EAF1), RoundedCornerShape(12.dp))
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier),
        contentAlignment = Alignment.Center
    ) {
        Icon(icon, contentDescription = desc, tint = Color(0xFF334155), modifier = Modifier.size(19.dp))
    }
}

@Composable
private fun LivePill() {
    val pulse = rememberInfiniteTransition(label = "live")
    val dotAlpha by pulse.animateFloat(
        initialValue = 1f, targetValue = 0.35f,
        animationSpec = infiniteRepeatable(tween(850), RepeatMode.Reverse), label = "dot"
    )
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(7.dp))
            .background(Color(0xFFFCE6E6))
            .padding(horizontal = 7.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(5.dp)
    ) {
        Box(Modifier.size(6.dp).clip(CircleShape).background(CrexColors.LivePulse.copy(alpha = dotAlpha)))
        Text("LIVE", color = CrexColors.LivePulse, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.5.sp)
    }
}

/**
 * The live audience chip — an eye and how many people are on this match right now, counted
 * server-side from presence heartbeats (not a total-views number dressed up as one).
 *
 * Kept deliberately quiet: the red LIVE badge next to it is the accent, this is the
 * supporting line. Colours are parameters because the football hero it also sits in is a
 * dark gradient, where the light slate chip would disappear.
 */
@Composable
fun WatchingPill(
    count: Int,
    modifier: Modifier = Modifier,
    background: Color = Color(0xFFF1F5FA),
    contentColor: Color = Color(0xFF475569),
    /**
     * Opens the audience. Passed only for a verified viewer, so the chip is inert for
     * everyone else rather than tappable-and-then-refused.
     */
    onClick: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(7.dp))
            .background(background)
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(horizontal = 7.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Icon(
            Icons.Outlined.Visibility,
            contentDescription = "$count watching now",
            tint = contentColor,
            modifier = Modifier.size(13.dp)
        )
        Text(
            compactViewerCount(count),
            color = contentColor,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.1.sp
        )
    }
}

/**
 * Exact up to 999, then 1.2K / 24K / 1.3M — a header chip has room for a number, not for
 * seven digits, and past a thousand nobody is reading the last three anyway.
 */
fun compactViewerCount(count: Int): String = when {
    count < 1_000 -> count.toString()
    count < 1_000_000 -> trimmedUnit(count / 1_000f, "K")
    else -> trimmedUnit(count / 1_000_000f, "M")
}

/** 1.0K reads worse than 1K; drop a trailing .0, and stop showing decimals past 10. */
private fun trimmedUnit(value: Float, unit: String): String {
    val text = if (value < 10f) String.format(java.util.Locale.US, "%.1f", value) else value.toInt().toString()
    return text.removeSuffix(".0") + unit
}

@Composable
private fun ScoreButton(onClick: () -> Unit) {
    val view = androidx.compose.ui.platform.LocalView.current
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(19.dp))
            .background(Color(0xFF0F1F33))
            .clickable { hapticConfirm(view); onClick() }
            .padding(start = 12.dp, end = 14.dp, top = 8.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(Icons.Outlined.SportsCricket, contentDescription = null, tint = Color(0xFF2DD4BF), modifier = Modifier.size(15.dp))
        Text(stringResource(R.string.action_score), color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}
