package com.haraan.app.ui.matches

import android.widget.Toast
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.SportsBaseball
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.MatchRepository
import com.haraan.app.data.SquadMember
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.launch
import org.json.JSONObject
import kotlin.random.Random

// ── palette (mirrors the create-flow tokens) ──
private val TossBg = Color(0xFFEBEBF0)
private val TossSurface = Color(0xFFFFFFFF)
private val TossBlue = Color(0xFF2563EB)
private val TossGreen = Color(0xFF16A34A)
private val TossAmber = Color(0xFFF59E0B)
private val TossText1 = Color(0xFF111827)
private val TossText2 = Color(0xFF5A5A6A)
private val TossText3 = Color(0xFF9A9AA8)
private val TossStroke = Color(0xFFE2E8F0)
// Gold coin tones — a warm metallic that reads as "celebration", not "disabled grey".
private val CoinHi = Color(0xFFFFF6D5)   // top highlight
private val CoinMid = Color(0xFFF4C24B)  // brass body
private val CoinLo = Color(0xFFB07A16)   // shaded edge
private val CoinRim = Color(0xFFC9950F)  // rim ring

/** What the create flow hands to the toss screen (and the share dialog, for private). */
data class TossSetup(
    val matchId: String,
    val teamA: String,
    val teamB: String,
    val squadA: List<SquadMember>,
    val squadB: List<SquadMember>,
    val isPrivate: Boolean,
    val joinCode: String,
    // Team crests so the "who won the toss?" cards read like the real teams (mirrors the
    // create-flow: a default emblem key, or a custom uploaded photo Uri that wins if present).
    val teamAEmblem: String? = null,
    val teamBEmblem: String? = null,
    val teamAPhoto: android.net.Uri? = null,
    val teamBPhoto: android.net.Uri? = null,
)

private enum class TossPhase { SETUP, LINEUP, COUNTDOWN, STARTING }

/** Colour per side so the two teams read apart at a glance (A blue, B amber). */
private fun sideColor(team: Int) = if (team == 2) TossAmber else TossBlue

private fun playerRef(member: SquadMember?): String? {
    if (member == null) return null
    val id = member.id.takeIf { it.isNotBlank() && !it.equals("null", true) }
    return (id ?: member.name).takeIf { it.isNotBlank() && !it.equals("null", true) }
}

/**
 * Post-create toss ritual: a tappable coin flip (delight only), the *actual* toss winner
 * and bat/bowl choice, and the opening lineup — striker, non-striker, bowler. Sends a
 * complete `start` score action so the match goes Live with the right batting side and
 * players (no auto-guessing). The winner is recorded by the user — the real toss happens
 * on the ground; the coin here is decoration, not the decision.
 */
@Composable
fun TossScreen(
    matchId: String,
    teamA: String,
    teamB: String,
    squadA: List<SquadMember>,
    squadB: List<SquadMember>,
    onStarted: () -> Unit,
    onCancel: () -> Unit,
    modifier: Modifier = Modifier,
    teamAEmblem: String? = null,
    teamBEmblem: String? = null,
    teamAPhoto: android.net.Uri? = null,
    teamBPhoto: android.net.Uri? = null,
) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { MatchRepository() }

    var phase by remember { mutableStateOf(TossPhase.SETUP) }
    var winner by remember { mutableStateOf(0) }            // 1 = Team A, 2 = Team B, 0 = unpicked
    var battingTeam by remember { mutableStateOf(1) }
    var decisionWord by remember { mutableStateOf("") }     // "" = unpicked, else "Bat" / "Bowl"
    // Opening lineup captured on the lineup screen, held while the 3-2-1 countdown plays.
    var pendingStriker by remember { mutableStateOf<SquadMember?>(null) }
    var pendingNonStriker by remember { mutableStateOf<SquadMember?>(null) }
    var pendingBowler by remember { mutableStateOf<SquadMember?>(null) }

    val teamAName = teamA.ifBlank { "Team A" }
    val teamBName = teamB.ifBlank { "Team B" }
    fun nameOf(team: Int) = if (team == 2) teamBName else teamAName

    // Resolved once here so lambdas / non-composable branches can reuse them.
    val helpHint = stringResource(com.haraan.app.R.string.toss_help_hint)
    val batLabel = stringResource(com.haraan.app.R.string.toss_bat)
    val bowlLabel = stringResource(com.haraan.app.R.string.toss_bowl)

    fun proceedToLineup() {
        if (winner == 0 || decisionWord.isBlank()) return
        val bat = decisionWord == "Bat"
        battingTeam = if (bat) winner else (if (winner == 1) 2 else 1)
        phase = TossPhase.LINEUP
    }

    fun start(striker: SquadMember?, nonStriker: SquadMember?, bowler: SquadMember?) {
        phase = TossPhase.STARTING
        scope.launch {
            val token = TokenStore.getToken(ctx)
            if (token.isNullOrBlank()) {
                Toast.makeText(ctx, "Please sign in to start the match.", Toast.LENGTH_SHORT).show()
                phase = TossPhase.LINEUP
                return@launch
            }
            val payload = JSONObject()
                .put("type", "start")
                .put("batting_team", battingTeam)
                .put("striker_id", playerRef(striker) ?: "Batter 1")
                .put("non_striker_id", playerRef(nonStriker) ?: "Batter 2")
                .put("bowler_id", playerRef(bowler) ?: "Bowler")
                .put("decision", "${nameOf(winner)} • $decisionWord")
            if (repo.sendScoreAction(token, matchId, payload) != null) {
                Toast.makeText(ctx, "Match is live — open it to score.", Toast.LENGTH_LONG).show()
                onStarted()
            } else {
                Toast.makeText(ctx, "Couldn't start the match. Check connection.", Toast.LENGTH_SHORT).show()
                phase = TossPhase.LINEUP
            }
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(TossBg)
            // The toss is a full-screen overlay stacked over the match list. A background alone
            // does NOT stop touches — without this, taps on empty areas fall through to the cards
            // behind and open a random match-detail page. Swallow every tap that isn't handled
            // by a child.
            .pointerInput(Unit) { detectTapGestures { } }
            .statusBarsPadding()
            .navigationBarsPadding()
    ) {
        // Header — a title and a way out (record the toss later, from the match).
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(stringResource(com.haraan.app.R.string.toss_title), color = TossText1, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
        }

        when (phase) {
            TossPhase.SETUP -> SetupStage(
                teamAName = teamAName,
                teamBName = teamBName,
                teamAEmblem = teamAEmblem,
                teamBEmblem = teamBEmblem,
                teamAPhoto = teamAPhoto,
                teamBPhoto = teamBPhoto,
                winner = winner,
                decision = decisionWord,
                onWinner = { winner = it },
                onDecision = { decisionWord = it },
                onHelp = {
                    Toast.makeText(ctx, helpHint, Toast.LENGTH_LONG).show()
                },
                onPlay = { proceedToLineup() },
            )
            TossPhase.LINEUP -> {
                val decisionLabel = if (decisionWord == "Bat") batLabel else bowlLabel
                LineupStage(
                    battingTeam = battingTeam,
                    battingName = nameOf(battingTeam),
                    bowlingName = nameOf(if (battingTeam == 1) 2 else 1),
                    battingSquad = if (battingTeam == 2) squadB else squadA,
                    bowlingSquad = if (battingTeam == 2) squadA else squadB,
                    tossLine = stringResource(com.haraan.app.R.string.toss_line_fmt, nameOf(winner), decisionLabel),
                    onStart = { s, ns, b ->
                        pendingStriker = s; pendingNonStriker = ns; pendingBowler = b
                        phase = TossPhase.COUNTDOWN
                    },
                )
            }
            TossPhase.COUNTDOWN -> CountdownStage(
                battingName = nameOf(battingTeam),
                accent = sideColor(battingTeam),
                onFinished = { start(pendingStriker, pendingNonStriker, pendingBowler) },
            )
            TossPhase.STARTING -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = TossBlue)
            }
        }
    }
}

/**
 * The toss ritual on one screen (CricHeroes-style, upgraded): tap-to-flip coin for delight,
 * then the two things that actually matter — who won the toss, and what they elected to do.
 * A sticky bottom bar carries "Need help?" and "Let's Play" (armed only once both are chosen).
 */
@Composable
private fun ColumnScope.SetupStage(
    teamAName: String,
    teamBName: String,
    teamAEmblem: String?,
    teamBEmblem: String?,
    teamAPhoto: android.net.Uri?,
    teamBPhoto: android.net.Uri?,
    winner: Int,
    decision: String,
    onWinner: (Int) -> Unit,
    onDecision: (String) -> Unit,
    onHelp: () -> Unit,
    onPlay: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .weight(1f)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp),
    ) {
        Spacer(Modifier.height(4.dp))
        SectionLabel(stringResource(com.haraan.app.R.string.toss_tap_coin))
        Spacer(Modifier.height(6.dp))
        Text(
            stringResource(com.haraan.app.R.string.toss_tap_hint),
            color = TossText3, fontSize = 12.sp,
        )
        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            FlipCoin(
                teamAName = teamAName,
                teamBName = teamBName,
                onResult = onWinner,
            )
        }
        Spacer(Modifier.height(14.dp))
        // Result banner — appears once the coin lands (or a team is tapped below).
        val winnerName = if (winner == 2) teamBName else teamAName
        if (winner != 0) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(sideColor(winner).copy(alpha = 0.12f))
                    .padding(vertical = 12.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    stringResource(com.haraan.app.R.string.toss_won_fmt, winnerName),
                    color = TossText1, fontSize = 14.sp, fontWeight = FontWeight.Bold,
                )
            }
            Spacer(Modifier.height(24.dp))
        } else {
            Spacer(Modifier.height(10.dp))
        }

        SectionLabel(stringResource(com.haraan.app.R.string.toss_who_won))
        if (winner != 0) {
            Spacer(Modifier.height(3.dp))
            Text(
                stringResource(com.haraan.app.R.string.toss_coin_editable),
                color = TossText3, fontSize = 11.sp,
            )
        }
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            TeamPickCard(
                name = teamAName, emblem = teamAEmblem, photo = teamAPhoto,
                accent = sideColor(1), selected = winner == 1,
                modifier = Modifier.weight(1f),
            ) { onWinner(1) }
            TeamPickCard(
                name = teamBName, emblem = teamBEmblem, photo = teamBPhoto,
                accent = sideColor(2), selected = winner == 2,
                modifier = Modifier.weight(1f),
            ) { onWinner(2) }
        }
        Spacer(Modifier.height(28.dp))

        SectionLabel(stringResource(com.haraan.app.R.string.toss_elected_to))
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            // Display label is localized; the "Bat"/"Bowl" state value stays canonical for the payload.
            DecisionCard(
                label = stringResource(com.haraan.app.R.string.toss_bat), icon = Icons.Filled.SportsCricket, accent = TossBlue,
                selected = decision == "Bat", enabled = winner != 0,
                modifier = Modifier.weight(1f),
            ) { onDecision("Bat") }
            DecisionCard(
                label = stringResource(com.haraan.app.R.string.toss_bowl), icon = Icons.Filled.SportsBaseball, accent = TossGreen,
                selected = decision == "Bowl", enabled = winner != 0,
                modifier = Modifier.weight(1f),
            ) { onDecision("Bowl") }
        }
        Spacer(Modifier.height(24.dp))
    }

    // Confirmation once armed, otherwise a nudge about what's left to pick.
    val armed = winner != 0 && decision.isNotBlank()
    val batLabel = stringResource(com.haraan.app.R.string.toss_bat)
    val bowlLabel = stringResource(com.haraan.app.R.string.toss_bowl)
    Text(
        text = if (armed) {
            val name = if (winner == 2) teamBName else teamAName
            stringResource(com.haraan.app.R.string.toss_line_fmt, name, if (decision == "Bat") batLabel else bowlLabel)
        } else {
            stringResource(com.haraan.app.R.string.toss_pick_hint)
        },
        color = if (armed) TossGreen else TossText3,
        fontSize = 12.sp,
        fontWeight = if (armed) FontWeight.SemiBold else FontWeight.Normal,
        textAlign = TextAlign.Center,
        modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 8.dp),
    )

    // Sticky bottom bar — mirrors the reference's "Need help? / Let's Play".
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(TossSurface)
            .border(BorderStroke(1.dp, TossStroke), RoundedCornerShape(0.dp))
            .padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(14.dp))
                .border(1.dp, TossStroke, RoundedCornerShape(14.dp))
                .clickable(onClick = onHelp)
                .padding(vertical = 16.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text(stringResource(com.haraan.app.R.string.toss_need_help), color = TossText2, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
        }
        Box(
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(14.dp))
                .background(if (armed) TossGreen else TossText3.copy(alpha = 0.4f))
                .clickable(enabled = armed, onClick = onPlay)
                .padding(vertical = 16.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text(stringResource(com.haraan.app.R.string.toss_lets_play), color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, color = TossText1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
}

/**
 * A struck gold coin the user taps to flip. Each face is *stamped* with a team's initial
 * (heads = Team A, tails = Team B) in embossed relief — no pasted-on crest, so it reads as
 * a real minted coin. On landing it reports the winning side via [onResult] (1 = A, 2 = B).
 * Idle it breathes to invite the tap; a diagonal sheen + bevel sell the metal; the spin lands
 * with a haptic + click for the "clink".
 */
@Composable
private fun FlipCoin(
    teamAName: String,
    teamBName: String,
    onResult: (Int) -> Unit,
) {
    val rotation = remember { androidx.compose.animation.core.Animatable(0f) }
    val scope = rememberCoroutineScope()
    val haptic = LocalHapticFeedback.current
    val view = LocalView.current
    var flipping by remember { mutableStateOf(false) }

    // Idle "breathing" so the coin reads as tappable; frozen while it spins.
    val pulse = rememberInfiniteTransition(label = "coinPulse")
    val idleScale by pulse.animateFloat(
        initialValue = 1f, targetValue = 1.05f,
        animationSpec = infiniteRepeatable(tween(1100, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "coinScale",
    )
    val scale = if (flipping) 1f else idleScale

    Box(
        modifier = Modifier
            .size(158.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .shadow(elevation = 14.dp, shape = CircleShape, clip = false)
            .graphicsLayer {
                rotationY = rotation.value
                cameraDistance = 16f * density
            }
            .clip(CircleShape)
            .background(
                Brush.radialGradient(
                    colors = listOf(CoinHi, CoinMid, CoinLo),
                    radius = 230f,
                )
            )
            // Directional light: bright top-left → shaded bottom-right, so the disc looks metal.
            .background(
                Brush.linearGradient(
                    colors = listOf(Color.White.copy(alpha = 0.35f), Color.Transparent, Color(0x33000000)),
                ),
                CircleShape,
            )
            // Double rim: bright inner + darker outer, so it reads as a struck coin edge.
            .border(BorderStroke(2.dp, CoinHi.copy(alpha = 0.7f)), CircleShape)
            .border(BorderStroke(6.dp, CoinRim), CircleShape)
            .clickable(enabled = !flipping) {
                haptic.performHapticFeedback(HapticFeedbackType.LongPress)   // launch flick
                scope.launch {
                    flipping = true
                    val spins = 5
                    val tails = Random.nextBoolean()
                    val land = if (tails) 180f else 0f
                    rotation.animateTo(
                        targetValue = rotation.value - (rotation.value % 360f) + 360f * spins + land,
                        animationSpec = tween(1100, easing = FastOutSlowInEasing),
                    )
                    // Landing: a firm tap + a soft system click to sell the "clink".
                    haptic.performHapticFeedback(HapticFeedbackType.LongPress)
                    view.playSoundEffect(android.view.SoundEffectConstants.CLICK)
                    flipping = false
                    onResult(if (tails) 2 else 1)   // heads = A, tails = B
                }
            },
        contentAlignment = Alignment.Center,
    ) {
        val normalized = ((rotation.value % 360f) + 360f) % 360f
        val showTails = normalized > 90f && normalized < 270f
        val faceLetter = (if (showTails) teamBName else teamAName)
            .trim().firstOrNull()?.uppercaseChar()?.toString() ?: if (showTails) "T" else "H"
        // Counter-mirror the back face so its stamp isn't drawn reversed.
        Box(
            modifier = Modifier.graphicsLayer { if (showTails) rotationY = 180f },
            contentAlignment = Alignment.Center,
        ) {
            // Engraved inner ring — a struck coin has a raised border inside the rim.
            Box(
                modifier = Modifier
                    .size(112.dp)
                    .border(BorderStroke(1.5.dp, CoinLo.copy(alpha = 0.5f)), CircleShape)
                    .border(BorderStroke(0.5.dp, CoinHi.copy(alpha = 0.5f)), CircleShape),
            )
            // Embossed monogram: bright highlight offset up-left, dark shadow down-right,
            // deep-brass face on top — the classic "stamped into metal" look.
            EmbossedGlyph(faceLetter)
        }
    }
}

/** A single character rendered as embossed relief on the coin's face. */
@Composable
private fun EmbossedGlyph(text: String) {
    Box(contentAlignment = Alignment.Center) {
        Text(text, color = CoinHi.copy(alpha = 0.9f), fontSize = 66.sp, fontWeight = FontWeight.Black,
            modifier = Modifier.offset((-1.5).dp, (-1.5).dp))
        Text(text, color = Color(0x99432B00), fontSize = 66.sp, fontWeight = FontWeight.Black,
            modifier = Modifier.offset(1.5.dp, 1.5.dp))
        Text(text, color = Color(0xFF8A5E12), fontSize = 66.sp, fontWeight = FontWeight.Black)
    }
}

/** A team card for the "who won the toss?" choice — real crest, name, and a check when picked. */
@Composable
private fun TeamPickCard(
    name: String,
    emblem: String?,
    photo: android.net.Uri?,
    accent: Color,
    selected: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Box(modifier = modifier) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(TossSurface)
                .border(
                    BorderStroke(if (selected) 2.dp else 1.dp, if (selected) accent else TossStroke),
                    RoundedCornerShape(16.dp),
                )
                .clickable(onClick = onClick)
                .padding(vertical = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            TeamCrest(emblem, photo, accent, name, size = 56.dp)
            Spacer(Modifier.height(10.dp))
            Text(
                name, color = TossText1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                textAlign = TextAlign.Center, maxLines = 1,
            )
        }
        if (selected) CheckBadge(accent, Modifier.align(Alignment.TopEnd).padding(8.dp))
    }
}

/** A bat/bowl choice card — glyph, label, brand accent when picked (locked until a winner is set). */
@Composable
private fun DecisionCard(
    label: String,
    icon: ImageVector,
    accent: Color,
    selected: Boolean,
    enabled: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Box(modifier = modifier) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(if (selected) accent else TossSurface)
                .border(
                    BorderStroke(if (selected) 2.dp else 1.dp, if (selected) accent else TossStroke),
                    RoundedCornerShape(16.dp),
                )
                .clickable(enabled = enabled, onClick = onClick)
                .padding(vertical = 18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            val dim = enabled || selected
            Icon(
                icon,
                contentDescription = null,
                tint = when {
                    selected -> Color.White
                    enabled -> accent
                    else -> TossText3
                },
                modifier = Modifier.size(30.dp).graphicsLayer { alpha = if (dim) 1f else 0.35f },
            )
            Spacer(Modifier.height(8.dp))
            Text(
                label,
                color = when {
                    selected -> Color.White
                    enabled -> TossText1
                    else -> TossText3
                },
                fontSize = 15.sp, fontWeight = FontWeight.Bold,
            )
        }
        if (selected) CheckBadge(Color.White, Modifier.align(Alignment.TopEnd).padding(8.dp), tick = accent)
    }
}

@Composable
private fun CheckBadge(ring: Color, modifier: Modifier = Modifier, tick: Color = Color.White) {
    Box(
        modifier = modifier
            .size(22.dp)
            .clip(CircleShape)
            .background(ring),
        contentAlignment = Alignment.Center,
    ) {
        Text("✓", color = tick, fontSize = 13.sp, fontWeight = FontWeight.Black)
    }
}

/** Renders a team's crest: a custom uploaded photo wins; else the chosen emblem; else initial. */
@Composable
private fun TeamCrest(emblem: String?, photo: android.net.Uri?, accent: Color, name: String, size: androidx.compose.ui.unit.Dp) {
    val res = emblem?.let { com.haraan.app.ui.matches.create.emblemDrawableFor(it) }
    when {
        photo != null -> coil.compose.AsyncImage(
            model = photo,
            contentDescription = name,
            contentScale = androidx.compose.ui.layout.ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape),
        )
        res != null -> androidx.compose.foundation.Image(
            painter = androidx.compose.ui.res.painterResource(res),
            contentDescription = name,
            contentScale = androidx.compose.ui.layout.ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape),
        )
        else -> Box(
            modifier = Modifier.size(size).clip(CircleShape).background(accent),
            contentAlignment = Alignment.Center,
        ) {
            Text(name.take(1).uppercase(), color = Color.White, fontSize = (size.value / 2.4f).sp, fontWeight = FontWeight.Black)
        }
    }
}

@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
private fun ColumnScope.LineupStage(
    battingTeam: Int,
    battingName: String,
    bowlingName: String,
    battingSquad: List<SquadMember>,
    bowlingSquad: List<SquadMember>,
    tossLine: String,
    onStart: (striker: SquadMember?, nonStriker: SquadMember?, bowler: SquadMember?) -> Unit,
) {
    var striker by remember { mutableStateOf(battingSquad.getOrNull(0)) }
    var nonStriker by remember { mutableStateOf(battingSquad.getOrNull(1)) }
    var bowler by remember { mutableStateOf(bowlingSquad.getOrNull(0)) }
    val battingColor = sideColor(battingTeam)
    val bowlingColor = sideColor(if (battingTeam == 1) 2 else 1)

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .weight(1f)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp)
    ) {
        Spacer(Modifier.height(6.dp))
        // Toss summary banner — a filled trophy medallion + the result, on a soft accent gradient.
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(
                    Brush.horizontalGradient(
                        listOf(battingColor.copy(alpha = 0.16f), battingColor.copy(alpha = 0.05f))
                    )
                )
                .border(BorderStroke(1.dp, battingColor.copy(alpha = 0.20f)), RoundedCornerShape(16.dp))
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(battingColor),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.EmojiEvents, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
            }
            Spacer(Modifier.width(12.dp))
            Text(tossLine, color = TossText1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        }
        Spacer(Modifier.height(22.dp))

        // Section header with a colored accent bar for a premium, editorial feel.
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .size(width = 4.dp, height = 20.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(battingColor)
            )
            Spacer(Modifier.width(10.dp))
            Column {
                Text(
                    stringResource(com.haraan.app.R.string.toss_opening_lineup),
                    color = TossText1, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                )
                Text(
                    stringResource(com.haraan.app.R.string.toss_opening_lineup_hint),
                    color = TossText3, fontSize = 12.sp,
                )
            }
        }
        Spacer(Modifier.height(16.dp))

        // One card per role, each led by its cricketer illustration (striker / non-striker / bowler).
        RoleCard(
            step = 1,
            illustration = com.haraan.app.R.drawable.ic_striker,
            role = stringResource(com.haraan.app.R.string.toss_striker),
            status = stringResource(com.haraan.app.R.string.toss_status_striker),
            accent = battingColor,
            squad = battingSquad,
            selected = striker,
            exclude = nonStriker,
            onSelect = { striker = it },
        )
        Spacer(Modifier.height(12.dp))
        RoleCard(
            step = 2,
            illustration = com.haraan.app.R.drawable.ic_non_striker,
            role = stringResource(com.haraan.app.R.string.toss_non_striker),
            status = stringResource(com.haraan.app.R.string.toss_status_non_striker),
            accent = battingColor,
            squad = battingSquad,
            selected = nonStriker,
            exclude = striker,
            onSelect = { nonStriker = it },
        )
        Spacer(Modifier.height(12.dp))
        RoleCard(
            step = 3,
            illustration = com.haraan.app.R.drawable.ic_bowler,
            role = stringResource(com.haraan.app.R.string.toss_opening_bowler),
            status = stringResource(com.haraan.app.R.string.toss_status_bowler),
            accent = bowlingColor,
            squad = bowlingSquad,
            selected = bowler,
            exclude = null,
            onSelect = { bowler = it },
        )

        Spacer(Modifier.height(24.dp))
        val ready = striker != null && bowler != null
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .then(
                    if (ready) Modifier.shadow(10.dp, RoundedCornerShape(16.dp), spotColor = TossGreen)
                    else Modifier
                )
                .clip(RoundedCornerShape(16.dp))
                .background(
                    if (ready) Brush.horizontalGradient(listOf(Color(0xFF16A34A), Color(0xFF0E9F6E)))
                    else Brush.horizontalGradient(listOf(TossText3.copy(alpha = 0.4f), TossText3.copy(alpha = 0.4f)))
                )
                .clickable(enabled = ready) { onStart(striker, nonStriker, bowler) }
                .padding(vertical = 17.dp),
            contentAlignment = Alignment.Center
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.PlayArrow, contentDescription = null, tint = Color.White, modifier = Modifier.size(22.dp))
                Spacer(Modifier.width(8.dp))
                Text(stringResource(com.haraan.app.R.string.toss_start_match), color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.3.sp)
            }
        }
        Spacer(Modifier.height(16.dp))
    }
}

/**
 * One role card (Striker / Non-striker / Opening bowler): a circular cricketer avatar (white,
 * ringed in the side accent, with a numbered step badge) heads it, alongside the role name and
 * a plain-English status line. A "chosen" pill and the wrapping player chips complete it.
 */
@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
private fun RoleCard(
    step: Int,
    illustration: Int,
    role: String,
    status: String,
    accent: Color,
    squad: List<SquadMember>,
    selected: SquadMember?,
    exclude: SquadMember?,
    onSelect: (SquadMember) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(6.dp, RoundedCornerShape(20.dp), spotColor = Color(0x22101828))
            .clip(RoundedCornerShape(20.dp))
            .background(TossSurface)
            .border(BorderStroke(1.dp, TossStroke), RoundedCornerShape(20.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            // Circular avatar on white — the illustration's white square vanishes into the disc.
            Box(contentAlignment = Alignment.BottomEnd) {
                Box(
                    modifier = Modifier
                        .size(60.dp)
                        .shadow(3.dp, CircleShape)
                        .clip(CircleShape)
                        .background(Color.White)
                        .border(BorderStroke(2.dp, accent.copy(alpha = 0.45f)), CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    androidx.compose.foundation.Image(
                        painter = androidx.compose.ui.res.painterResource(illustration),
                        contentDescription = role,
                        contentScale = androidx.compose.ui.layout.ContentScale.Fit,
                        modifier = Modifier.size(50.dp),
                    )
                }
                // Numbered step badge — reads as an ordered 1-2-3 lineup.
                Box(
                    modifier = Modifier
                        .size(22.dp)
                        .clip(CircleShape)
                        .background(accent)
                        .border(BorderStroke(2.dp, TossSurface), CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("$step", color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Black)
                }
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(role, color = TossText1, fontSize = 15.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                Spacer(Modifier.height(2.dp))
                Text(status, color = accent, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
            }
            // The chosen player's name, badge-style with a tick, so the pick reads at a glance.
            if (selected != null) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(accent.copy(alpha = 0.12f))
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                ) {
                    Text("✓", color = accent, fontSize = 11.sp, fontWeight = FontWeight.Black)
                    Spacer(Modifier.width(4.dp))
                    Text(selected.name, color = accent, fontSize = 12.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                }
            }
        }
        Spacer(Modifier.height(14.dp))
        androidx.compose.material3.HorizontalDivider(color = TossStroke)
        Spacer(Modifier.height(14.dp))
        if (squad.isEmpty()) {
            Text(
                stringResource(com.haraan.app.R.string.toss_no_players),
                color = TossText3, fontSize = 12.sp
            )
        } else {
            androidx.compose.foundation.layout.FlowRow(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                squad.forEach { member ->
                    PlayerChip(
                        member = member,
                        accent = accent,
                        selected = member == selected,
                        excluded = exclude != null && member == exclude,
                        onSelect = { onSelect(member) },
                    )
                }
            }
        }
    }
}

/** A selectable player chip: avatar initial + name (+ a tick when chosen), filled with the side accent. */
@Composable
private fun PlayerChip(
    member: SquadMember,
    accent: Color,
    selected: Boolean,
    excluded: Boolean,
    onSelect: () -> Unit,
) {
    // Unselected chips sit on a faint grey so they read as pickable against the white card.
    val bg = when {
        selected -> accent
        excluded -> Color(0xFFF1F2F5)
        else -> Color(0xFFF6F7F9)
    }
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(24.dp))
            .background(bg)
            .border(1.dp, if (selected) accent else TossStroke, RoundedCornerShape(24.dp))
            .clickable(enabled = !excluded, onClick = onSelect)
            .padding(start = 5.dp, end = 13.dp, top = 5.dp, bottom = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(28.dp)
                .clip(CircleShape)
                .background(if (selected) Color.White.copy(alpha = 0.28f) else accent.copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                member.name.take(1).uppercase(),
                color = if (selected) Color.White else accent,
                fontSize = 12.sp, fontWeight = FontWeight.Bold,
            )
        }
        Spacer(Modifier.width(8.dp))
        Text(
            member.name,
            color = when {
                selected -> Color.White
                excluded -> TossText3.copy(alpha = 0.6f)
                else -> TossText1
            },
            fontSize = 13.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            maxLines = 1,
        )
        if (selected) {
            Spacer(Modifier.width(6.dp))
            Text("✓", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Black)
        }
    }
}

/**
 * The 3 · 2 · 1 kickoff. After the openings are locked, a big number counts down with a
 * pop-and-fade, then "GO!" flashes and [onFinished] fires the actual `start` action. Each
 * beat lands with a haptic tick so it feels like a real countdown, not a spinner.
 */
@Composable
private fun ColumnScope.CountdownStage(
    battingName: String,
    accent: Color,
    onFinished: () -> Unit,
) {
    val haptic = LocalHapticFeedback.current
    val view = LocalView.current
    // count: 3 → 2 → 1 → 0 (0 renders as "GO!"). -1 = done.
    var count by remember { mutableStateOf(3) }

    LaunchedEffect(Unit) {
        for (n in 3 downTo 0) {
            count = n
            haptic.performHapticFeedback(HapticFeedbackType.LongPress)
            view.playSoundEffect(android.view.SoundEffectConstants.CLICK)
            kotlinx.coroutines.delay(if (n == 0) 650L else 850L)
        }
        onFinished()
    }

    Box(
        modifier = Modifier.fillMaxWidth().weight(1f).padding(24.dp),
        contentAlignment = Alignment.Center,
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
                stringResource(com.haraan.app.R.string.toss_get_ready),
                color = TossText2, fontSize = 15.sp, fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.height(28.dp))
            // Each value gets its own key so the pop-in animation restarts every tick.
            androidx.compose.animation.AnimatedContent(
                targetState = count,
                transitionSpec = {
                    (androidx.compose.animation.scaleIn(initialScale = 0.4f, animationSpec = tween(260)) +
                        androidx.compose.animation.fadeIn(tween(200))) togetherWith
                        (androidx.compose.animation.scaleOut(targetScale = 1.6f, animationSpec = tween(260)) +
                            androidx.compose.animation.fadeOut(tween(200)))
                },
                label = "countdown",
            ) { value ->
                val isGo = value == 0
                Box(
                    modifier = Modifier
                        .size(180.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.radialGradient(
                                colors = listOf(accent.copy(alpha = 0.20f), accent.copy(alpha = 0.04f)),
                            )
                        )
                        .border(BorderStroke(3.dp, accent), CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        if (isGo) stringResource(com.haraan.app.R.string.toss_go) else value.toString(),
                        color = if (isGo) TossGreen else accent,
                        fontSize = if (isGo) 54.sp else 88.sp,
                        fontWeight = FontWeight.Black,
                    )
                }
            }
            Spacer(Modifier.height(28.dp))
            Text(
                stringResource(com.haraan.app.R.string.toss_starting_fmt, battingName),
                color = TossText3, fontSize = 13.sp, fontWeight = FontWeight.Medium,
                textAlign = TextAlign.Center,
            )
        }
    }
}
