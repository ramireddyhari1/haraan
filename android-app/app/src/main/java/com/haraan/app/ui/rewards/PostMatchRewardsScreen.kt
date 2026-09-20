package com.haraan.app.ui.rewards

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.IosShare
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.airbnb.lottie.compose.LottieAnimation
import com.airbnb.lottie.compose.LottieCompositionSpec
import com.airbnb.lottie.compose.rememberLottieComposition
import com.haraan.app.data.rewards.MatchRewards
import com.haraan.app.push.DeepLinkState
import com.haraan.app.ui.Feel
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.delay

/**
 * The post-match screen. One page, read top to bottom in the order the player should feel it:
 *
 *   1. I won          — [ResultHeader]: crests, broadcast-size score, one word.
 *   2. I earned       — [XpPanel] (Match XP, honestly pending until verified) and the reward
 *                        tickets ([RewardTicket]).
 *   3. I'm progressing — [ProgressPanel]: weekly streak, next badge, medals from this match.
 *   4. Play again     — [NextActionBar], always on screen.
 *
 * Rendering is driven by [RewardScreenUi] ([RewardScreenMapper]); nothing here decides content.
 * The first view plays a short staged entrance; every later view is instant.
 */
@Composable
fun PostMatchRewardsScreen(
    matchId: String,
    onClose: () -> Unit,
    vm: PostMatchRewardsViewModel = viewModel(),
) {
    val state by vm.state.collectAsState()
    val context = LocalContext.current

    LaunchedEffect(matchId) { vm.enter(matchId) }
    LaunchedEffect(state.message) {
        state.message?.let {
            Toast.makeText(context, it, Toast.LENGTH_SHORT).show()
            vm.messageShown()
        }
    }

    val data = state.data
    Box(Modifier.fillMaxSize().background(Board.Base)) {
        when {
            data != null -> RewardsBoard(data, state, vm, onClose)
            state.loading -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = Board.Blue) }
            else -> ErrorState(state.error ?: "Couldn't load your rewards.", vm::retry, onClose)
        }
    }
}

@Composable
private fun RewardsBoard(data: MatchRewards, state: PostMatchRewardsState, vm: PostMatchRewardsViewModel, onClose: () -> Unit) {
    val ui = remember(data) { RewardScreenMapper.map(data) }
    val context = LocalContext.current
    val view = LocalView.current
    val animate = state.animateEntrance

    // Staged entrance: eyebrow+crests → score → result word → XP → rewards → progress.
    var stage by remember(data.match.id) { mutableIntStateOf(if (animate) 0 else FULL) }
    LaunchedEffect(data.match.id, animate) {
        if (!animate) {
            stage = FULL
            return@LaunchedEffect
        }
        stage = 1
        delay(70); stage = 2
        delay(90); stage = 3
        if (ui.result.outcome == Outcome.WON) view.performHapticFeedback(Feel.COMMIT)
        delay(110); stage = 4
        delay(100); stage = 5
        delay(80); stage = 6
        delay(180)
        vm.entranceDone()
        stage = FULL
    }

    val act: (NextActionKind) -> Unit = { kind ->
        DeepLinkState.set(if (kind == NextActionKind.CREATE_MATCH) "haraan://actionboard/create" else "haraan://gamehub")
        onClose()
    }

    Box(Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 200.dp)) {
            item(key = "top") {
                Column(Modifier.fillMaxWidth().background(Brush.verticalGradient(listOf(Board.BaseTop, Board.Base)))) {
                    TopBar(onClose) { shareText(context, ui.result.shareText, ui.result.shareUrl, "Share result") }
                    ResultHeader(ui.result, stage, animate)
                    Spacer(Modifier.height(20.dp))
                }
            }

            item(key = "xp") {
                Box(Modifier.padding(horizontal = 16.dp).rise(stage >= 4)) {
                    XpPanel(ui.xp, ui.verification, countUp = animate && stage >= 4) {
                        shareText(context, "Confirm our result on Haraan so everyone's XP and rewards unlock.", ui.result.shareUrl, "Remind captains")
                    }
                }
            }

            if (ui.rewards.isNotEmpty()) {
                item(key = "perksVault") {
                    PerksVaultSection(
                        ui = ui,
                        state = state,
                        adsAvailable = data.rewardedAds.available,
                        stage = stage,
                        onClaim = { item -> vm.claim(item) },
                        onWatch = { item -> context.findActivity()?.let { vm.watchAd(it, item) } },
                        onReveal = { item -> vm.reveal(item) },
                        onUse = { scope ->
                            // Venue coupons are spent on Pulse; everything else on Events.
                            DeepLinkState.set(if (scope == "venue") "haraan://gamehub" else "haraan://events")
                            onClose()
                        },
                    )
                }
            }

            item(key = "progress") {
                Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 28.dp).rise(stage >= 6)) {
                    Eyebrow("Progress", modifier = Modifier.padding(start = 4.dp, bottom = 12.dp))
                    ProgressPanel(ui.progress, reveal = stage >= 6)
                }
            }
        }

        // An admin-set celebration animation (/control → Platform rules) plays once over the
        // result on first view. Default is none — the staged entrance is the celebration.
        val url = data.celebration.animationUrl
        if (animate && stage < FULL && !url.isNullOrBlank()) {
            val composition by rememberLottieComposition(LottieCompositionSpec.Url(url))
            LottieAnimation(composition, iterations = 1, modifier = Modifier.fillMaxWidth().height(360.dp))
        }

        NextActionBar(ui.nextAction, Modifier.align(Alignment.BottomCenter).rise(stage >= 5), onAction = act)
    }
}

private const val FULL = 99

@Composable
private fun TopBar(onClose: () -> Unit, onShare: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 8.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconCircle(onClose) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Board.Ink, modifier = Modifier.size(20.dp)) }
        Text(
            "Match Result", color = Board.Ink, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = 16.sp,
            textAlign = TextAlign.Center, modifier = Modifier.weight(1f).semantics { heading() },
        )
        IconCircle(onShare) { Icon(Icons.Outlined.IosShare, "Share result", tint = Board.Ink, modifier = Modifier.size(19.dp)) }
    }
}

@Composable
private fun IconCircle(onClick: () -> Unit, content: @Composable () -> Unit) {
    Box(Modifier.size(48.dp).clip(CircleShape).clickable(onClick = onClick), contentAlignment = Alignment.Center) { content() }
}

@Composable
private fun ErrorState(message: String, onRetry: () -> Unit, onClose: () -> Unit) {
    Column(Modifier.fillMaxSize()) {
        TopBar(onClose) {}
        Column(Modifier.fillMaxSize().padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
            Text(message, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontSize = 15.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(16.dp))
            ActionButton("Try again", primary = true, compact = true, onClick = onRetry)
        }
    }
}
