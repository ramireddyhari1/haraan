package com.example.thanna.ui.matches

import android.widget.Toast
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.MatchRepository
import com.example.thanna.data.SquadMember
import com.example.thanna.data.TokenStore
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

private enum class TossPhase { SETUP, LINEUP, STARTING }

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

    val teamAName = teamA.ifBlank { "Team A" }
    val teamBName = teamB.ifBlank { "Team B" }
    fun nameOf(team: Int) = if (team == 2) teamBName else teamAName

    // Resolved once here so lambdas / non-composable branches can reuse them.
    val helpHint = stringResource(com.example.thanna.R.string.toss_help_hint)
    val batLabel = stringResource(com.example.thanna.R.string.toss_bat)
    val bowlLabel = stringResource(com.example.thanna.R.string.toss_bowl)

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
            .statusBarsPadding()
            .navigationBarsPadding()
    ) {
        // Header — a title and a way out (record the toss later, from the match).
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(stringResource(com.example.thanna.R.string.toss_title), color = TossText1, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            if (phase != TossPhase.STARTING) {
                Text(
                    stringResource(com.example.thanna.R.string.toss_later),
                    color = TossBlue,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { onCancel() }
                        .padding(horizontal = 10.dp, vertical = 6.dp)
                )
            }
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
                    tossLine = stringResource(com.example.thanna.R.string.toss_line_fmt, nameOf(winner), decisionLabel),
                    onStart = { s, ns, b -> start(s, ns, b) },
                )
            }
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
        SectionLabel(stringResource(com.example.thanna.R.string.toss_tap_coin))
        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { FlipCoin() }
        Spacer(Modifier.height(28.dp))

        SectionLabel(stringResource(com.example.thanna.R.string.toss_who_won))
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

        SectionLabel(stringResource(com.example.thanna.R.string.toss_elected_to))
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            // Display label is localized; the "Bat"/"Bowl" state value stays canonical for the payload.
            DecisionCard(
                label = stringResource(com.example.thanna.R.string.toss_bat), glyph = "🏏", accent = TossBlue,
                selected = decision == "Bat", enabled = winner != 0,
                modifier = Modifier.weight(1f),
            ) { onDecision("Bat") }
            DecisionCard(
                label = stringResource(com.example.thanna.R.string.toss_bowl), glyph = "🎯", accent = TossGreen,
                selected = decision == "Bowl", enabled = winner != 0,
                modifier = Modifier.weight(1f),
            ) { onDecision("Bowl") }
        }
        Spacer(Modifier.height(24.dp))
    }

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
            Text(stringResource(com.example.thanna.R.string.toss_need_help), color = TossText2, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
        }
        val armed = winner != 0 && decision.isNotBlank()
        Box(
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(14.dp))
                .background(if (armed) TossGreen else TossText3.copy(alpha = 0.4f))
                .clickable(enabled = armed, onClick = onPlay)
                .padding(vertical = 16.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text(stringResource(com.example.thanna.R.string.toss_lets_play), color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, color = TossText1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
}

/** A metallic coin the user can tap to flip — 3D rotationY spin landing on Heads (logo) or Tails. */
@Composable
private fun FlipCoin() {
    val rotation = remember { androidx.compose.animation.core.Animatable(0f) }
    val scope = rememberCoroutineScope()
    val haptic = LocalHapticFeedback.current
    val view = LocalView.current
    var flipping by remember { mutableStateOf(false) }

    Box(
        modifier = Modifier
            .size(150.dp)
            .graphicsLayer {
                rotationY = rotation.value
                cameraDistance = 16f * density
            }
            .clip(CircleShape)
            .background(
                Brush.radialGradient(
                    colors = listOf(Color(0xFFF6F7F9), Color(0xFFC9CDD6), Color(0xFF9198A6)),
                    radius = 220f,
                )
            )
            .border(BorderStroke(5.dp, Color(0xFFAEB4C0)), CircleShape)
            .clickable(enabled = !flipping) {
                haptic.performHapticFeedback(HapticFeedbackType.LongPress)   // launch flick
                scope.launch {
                    flipping = true
                    val spins = 5
                    val land = if (Random.nextBoolean()) 180f else 0f
                    rotation.animateTo(
                        targetValue = rotation.value - (rotation.value % 360f) + 360f * spins + land,
                        animationSpec = tween(1100, easing = androidx.compose.animation.core.FastOutSlowInEasing),
                    )
                    // Landing: a firm tap + a soft system click to sell the "clink".
                    haptic.performHapticFeedback(HapticFeedbackType.LongPress)
                    view.playSoundEffect(android.view.SoundEffectConstants.CLICK)
                    flipping = false
                }
            },
        contentAlignment = Alignment.Center,
    ) {
        val normalized = ((rotation.value % 360f) + 360f) % 360f
        val showTails = normalized > 90f && normalized < 270f
        // Counter-mirror the back face so its content isn't drawn reversed.
        Box(
            modifier = Modifier.graphicsLayer { if (showTails) rotationY = 180f },
            contentAlignment = Alignment.Center,
        ) {
            if (showTails) {
                Text(stringResource(com.example.thanna.R.string.toss_tails), color = Color(0xFF4B5563), fontSize = 20.sp, fontWeight = FontWeight.Black)
            } else {
                androidx.compose.foundation.Image(
                    painter = androidx.compose.ui.res.painterResource(com.example.thanna.R.drawable.haraan_logo),
                    contentDescription = "Heads",
                    modifier = Modifier.size(78.dp).clip(CircleShape),
                )
            }
        }
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
    glyph: String,
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
            Text(glyph, fontSize = 30.sp, modifier = Modifier.graphicsLayer { alpha = if (dim) 1f else 0.35f })
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
    val res = emblem?.let { com.example.thanna.ui.matches.create.emblemDrawableFor(it) }
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

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .weight(1f)
            .padding(horizontal = 16.dp)
    ) {
        Spacer(Modifier.height(4.dp))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(sideColor(battingTeam).copy(alpha = 0.10f))
                .padding(14.dp)
        ) {
            Text(tossLine, color = TossText1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        }
        Spacer(Modifier.height(20.dp))

        PlayerPickRow(stringResource(com.example.thanna.R.string.toss_striker), battingName, battingSquad, selected = striker, exclude = nonStriker) { striker = it }
        Spacer(Modifier.height(18.dp))
        PlayerPickRow(stringResource(com.example.thanna.R.string.toss_non_striker), battingName, battingSquad, selected = nonStriker, exclude = striker) { nonStriker = it }
        Spacer(Modifier.height(18.dp))
        PlayerPickRow(stringResource(com.example.thanna.R.string.toss_opening_bowler), bowlingName, bowlingSquad, selected = bowler, exclude = null) { bowler = it }

        Spacer(Modifier.weight(1f))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(TossGreen)
                .clickable { onStart(striker, nonStriker, bowler) }
                .padding(vertical = 16.dp),
            contentAlignment = Alignment.Center
        ) {
            Text(stringResource(com.example.thanna.R.string.toss_start_match), color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(16.dp))
    }
}

@Composable
private fun PlayerPickRow(
    role: String,
    teamName: String,
    squad: List<SquadMember>,
    selected: SquadMember?,
    exclude: SquadMember?,
    onSelect: (SquadMember) -> Unit,
) {
    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(role, color = TossText1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            Text(teamName, color = TossText3, fontSize = 12.sp)
        }
        Spacer(Modifier.height(8.dp))
        if (squad.isEmpty()) {
            Text(
                stringResource(com.example.thanna.R.string.toss_no_players),
                color = TossText3, fontSize = 12.sp
            )
            return
        }
        Row(
            modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            squad.forEach { member ->
                val isSel = member == selected
                val isExcluded = exclude != null && member == exclude
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(if (isSel) TossBlue else TossSurface)
                        .border(1.dp, if (isSel) TossBlue else TossStroke, RoundedCornerShape(10.dp))
                        .clickable(enabled = !isExcluded) { onSelect(member) }
                        .padding(horizontal = 14.dp, vertical = 10.dp)
                ) {
                    Text(
                        member.name,
                        color = when {
                            isSel -> Color.White
                            isExcluded -> TossText3.copy(alpha = 0.5f)
                            else -> TossText1
                        },
                        fontSize = 13.sp,
                        fontWeight = if (isSel) FontWeight.Bold else FontWeight.Medium
                    )
                }
            }
        }
    }
}
