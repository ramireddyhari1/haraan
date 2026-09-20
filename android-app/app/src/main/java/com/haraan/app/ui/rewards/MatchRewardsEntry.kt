package com.haraan.app.ui.rewards

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.slideInVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material3.Icon
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
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.TokenStore
import com.haraan.app.data.rewards.RewardsNav
import com.haraan.app.data.rewards.RewardsRepository
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.PlusJakartaSans

/**
 * "Your match rewards" on a finished match's page — the way back to the rewards screen for every
 * player in the squads, not only the scorer. Shown only when the server answers for this viewer
 * (a player in the match, match finished); spectators never see it.
 */
@Composable
fun MatchRewardsEntry(matchId: String, finished: Boolean) {
    val context = LocalContext.current
    var summary by remember(matchId) { mutableStateOf<String?>(null) }

    LaunchedEffect(matchId, finished) {
        if (!finished || matchId.isBlank()) return@LaunchedEffect
        val token = TokenStore.getSignedInToken(context) ?: return@LaunchedEffect
        summary = runCatching { RewardsRepository().forMatch(token, matchId) }.getOrNull()?.let { r ->
            val ready = r.rewards.ready.size
            when {
                ready > 0 -> if (ready == 1) "1 reward ready to claim" else "$ready rewards ready to claim"
                r.rewards.locked.isNotEmpty() -> "Rewards waiting for the result"
                else -> "Your match rewards"
            }
        }
    }

    Box(Modifier.fillMaxSize().navigationBarsPadding().padding(bottom = 20.dp), contentAlignment = Alignment.BottomCenter) {
        AnimatedVisibility(visible = summary != null, enter = fadeIn() + slideInVertically { it / 2 }) {
            Row(
                Modifier
                    .pressable(haptic = Feel.SELECT) { RewardsNav.open(matchId) }
                    .shadow(10.dp, RoundedCornerShape(24.dp))
                    .clip(RoundedCornerShape(24.dp))
                    .background(HaraanColors.EventsBlue)
                    .padding(horizontal = 18.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.EmojiEvents, null, tint = Color.White, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(summary.orEmpty(), color = Color.White, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = 14.sp)
            }
        }
    }
}
