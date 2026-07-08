package com.example.thanna.ui.matches

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
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import com.example.thanna.R
import com.example.thanna.ui.LanguageDialog
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
fun MatchHeader(state: MatchUiState, modifier: Modifier = Modifier, scrollOffset: Int = 0, onScoreClick: () -> Unit = {}, onBack: () -> Unit = {}) {
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
