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
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.SwapVert
import androidx.compose.material.icons.filled.Add
import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.draw.drawBehind
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.HelpOutline
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.SportsBaseball
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.drawscope.drawIntoCanvas
import androidx.compose.ui.graphics.drawscope.withTransform
import androidx.compose.ui.graphics.nativeCanvas
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
import com.haraan.app.ui.theme.HaraanColors

// ── palette (mirrors the create-flow tokens) ──
private val TossBg = HaraanColors.Background
private val TossSurface = HaraanColors.Surface
private val TossBlue = HaraanColors.EventsBlue
private val TossGreen = HaraanColors.Success
private val TossText1 = HaraanColors.TextPrimary
private val TossText2 = HaraanColors.TextSecondary
private val TossText3 = HaraanColors.TextMuted
private val TossStroke = HaraanColors.BorderLight
// Gold coin tones — a warm metallic that reads as "celebration", not "disabled grey".
private val CoinHi = HaraanColors.WarningTint   // top highlight
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
            // getSignedInToken, not getToken: a guest's "skipped_guest" token is
            // non-blank, so a null/blank check would let them through to a 401.
            val token = TokenStore.getSignedInToken(ctx)
            if (token == null) {
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
            val started = repo.sendScoreAction(token, matchId, payload)
            if (started.ok) {
                Toast.makeText(ctx, "Match is live — open it to score.", Toast.LENGTH_LONG).show()
                onStarted()
            } else {
                // The server's reason when it gave one — this is the first place a scorer
                // meets the incomplete-profile gate, and "check connection" would send
                // them to fix a network that is working.
                Toast.makeText(
                    ctx,
                    started.refusal ?: "Couldn't start the match. Check connection.",
                    Toast.LENGTH_LONG
                ).show()
                phase = TossPhase.LINEUP
            }
        }
    }

    androidx.activity.compose.BackHandler(enabled = phase == TossPhase.LINEUP) { phase = TossPhase.SETUP }

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
        // Header — a way out (the toss can be recorded later, from the match), the fixture
        // this toss is for, and help as a quiet icon rather than half the bottom bar.
        Row(
            modifier = Modifier.fillMaxWidth().padding(start = 6.dp, end = 10.dp, top = 6.dp, bottom = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (phase == TossPhase.LINEUP) {
                androidx.compose.material3.IconButton(onClick = { phase = TossPhase.SETUP }) {
                    Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null, tint = TossText1)
                }
            } else {
                androidx.compose.material3.IconButton(onClick = onCancel) {
                    Icon(Icons.Filled.Close, contentDescription = stringResource(com.haraan.app.R.string.toss_later), tint = TossText1)
                }
            }
            Column(Modifier.weight(1f)) {
                Text(
                    stringResource(if (phase == TossPhase.LINEUP) com.haraan.app.R.string.toss_opening_lineup else com.haraan.app.R.string.toss_title),
                    color = TossText1, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                )
                Text(
                    "$teamAName  vs  $teamBName", color = TossText3, fontSize = 12.sp, fontWeight = FontWeight.Medium,
                    maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                )
            }
            if (phase == TossPhase.SETUP) {
                androidx.compose.material3.IconButton(onClick = { Toast.makeText(ctx, helpHint, Toast.LENGTH_LONG).show() }) {
                    Icon(Icons.AutoMirrored.Filled.HelpOutline, contentDescription = stringResource(com.haraan.app.R.string.toss_need_help), tint = TossText3)
                }
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
                onPlay = { proceedToLineup() },
            )
            TossPhase.LINEUP -> {
                val decisionLabel = if (decisionWord == "Bat") batLabel else bowlLabel
                val bowlingTeam = if (battingTeam == 1) 2 else 1
                LineupStage(
                    battingName = nameOf(battingTeam),
                    bowlingName = nameOf(bowlingTeam),
                    battingEmblem = if (battingTeam == 2) teamBEmblem else teamAEmblem,
                    bowlingEmblem = if (bowlingTeam == 2) teamBEmblem else teamAEmblem,
                    battingPhoto = if (battingTeam == 2) teamBPhoto else teamAPhoto,
                    bowlingPhoto = if (bowlingTeam == 2) teamBPhoto else teamAPhoto,
                    battingSquad = if (battingTeam == 2) squadB else squadA,
                    bowlingSquad = if (battingTeam == 2) squadA else squadB,
                    tossLine = stringResource(com.haraan.app.R.string.toss_line_fmt, nameOf(winner), decisionLabel.lowercase()),
                    onStart = { s, ns, b ->
                        pendingStriker = s; pendingNonStriker = ns; pendingBowler = b
                        phase = TossPhase.COUNTDOWN
                    },
                )
            }
            TossPhase.COUNTDOWN -> CountdownStage(
                battingName = nameOf(battingTeam),
                accent = TossBlue,
                onFinished = { start(pendingStriker, pendingNonStriker, pendingBowler) },
            )
            TossPhase.STARTING -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = TossBlue)
            }
        }
    }
}

/**
 * The toss on one screen. The coin is the hero; under it, the result is said as a sentence,
 * not boxed in a banner. The two decisions that actually matter — who won it, and what they
 * chose — are segmented controls (a sliding blue-tint pill), because they ARE controls, not
 * cards. Once both are set, the screen states the consequence the scorer cares about —
 * who bats first — and the one button says what happens next. Until then the button says
 * what's still missing, and tapping it nudges that control rather than showing a hint.
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
    onPlay: () -> Unit,
) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    // A short horizontal shake — what a control does when it's the thing you skipped.
    val winnerShake = remember { androidx.compose.animation.core.Animatable(0f) }
    val choiceShake = remember { androidx.compose.animation.core.Animatable(0f) }
    fun nudge(target: androidx.compose.animation.core.Animatable<Float, androidx.compose.animation.core.AnimationVector1D>) {
        hapticTick(view)
        scope.launch {
            target.snapTo(0f)
            target.animateTo(
                0f,
                androidx.compose.animation.core.keyframes {
                    durationMillis = 360
                    -9f at 50; 8f at 110; -6f at 170; 4f at 230; -2f at 290
                },
            )
        }
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .weight(1f)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp),
    ) {
        // Headroom for the coin's climb — it flies up into this space, so nothing lives here.
        Spacer(Modifier.height(36.dp))
        FlipCoin(
            teamAName = teamAName,
            teamBName = teamBName,
            onResult = onWinner,
        )
        Spacer(Modifier.height(6.dp))

        // The result, said plainly under the coin. Before the toss it's the invitation.
        androidx.compose.animation.AnimatedContent(
            targetState = winner,
            transitionSpec = {
                (androidx.compose.animation.fadeIn(tween(240)) +
                    androidx.compose.animation.slideInVertically(tween(240)) { it / 3 }) togetherWith
                    androidx.compose.animation.fadeOut(tween(120))
            },
            modifier = Modifier.fillMaxWidth(),
            label = "tossResult",
        ) { w ->
            Box(Modifier.fillMaxWidth().height(30.dp), contentAlignment = Alignment.Center) {
                if (w == 0) {
                    Text(
                        stringResource(com.haraan.app.R.string.toss_tap_to_toss),
                        color = TossText3, fontSize = 13.sp, fontWeight = FontWeight.Medium,
                    )
                } else {
                    Text(
                        androidx.compose.ui.text.buildAnnotatedString {
                            val name = if (w == 2) teamBName else teamAName
                            val line = stringResource(com.haraan.app.R.string.toss_won_fmt, name)
                            val at = line.indexOf(name)
                            append(line)
                            if (at >= 0) addStyle(
                                androidx.compose.ui.text.SpanStyle(color = TossText1, fontWeight = FontWeight.ExtraBold),
                                at, at + name.length,
                            )
                        },
                        color = TossText2, fontSize = 19.sp, fontWeight = FontWeight.Medium,
                        maxLines = 1,
                    )
                }
            }
        }

        Spacer(Modifier.height(30.dp))
        FieldLabel(stringResource(com.haraan.app.R.string.toss_won_by))
        Spacer(Modifier.height(10.dp))
        TossSegment(
            count = 2,
            selected = winner - 1,
            enabled = true,
            height = 60.dp,
            modifier = Modifier.graphicsLayer { translationX = winnerShake.value * density },
            onSelect = { onWinner(it + 1) },
        ) { i, on ->
            val name = if (i == 0) teamAName else teamBName
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 10.dp)) {
                TeamCrest(
                    emblem = if (i == 0) teamAEmblem else teamBEmblem,
                    photo = if (i == 0) teamAPhoto else teamBPhoto,
                    accent = TossBlue, name = name, size = 30.dp,
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    name,
                    color = if (on) TossBlue else TossText1,
                    fontSize = 15.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                )
            }
        }

        Spacer(Modifier.height(24.dp))
        FieldLabel(stringResource(com.haraan.app.R.string.toss_chose_to))
        Spacer(Modifier.height(10.dp))
        TossSegment(
            count = 2,
            selected = when (decision) { "Bat" -> 0; "Bowl" -> 1; else -> -1 },
            enabled = winner != 0,
            height = 56.dp,
            modifier = Modifier.graphicsLayer { translationX = choiceShake.value * density },
            onDisabledTap = { nudge(winnerShake) },
            onSelect = { onDecision(if (it == 0) "Bat" else "Bowl") },
        ) { i, on ->
            val tint = if (on) TossBlue else TossText1
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (i == 0) BatGlyph(tint) else BallGlyph(tint)
                Spacer(Modifier.width(9.dp))
                Text(
                    stringResource(if (i == 0) com.haraan.app.R.string.toss_bat else com.haraan.app.R.string.toss_bowl),
                    color = tint, fontSize = 15.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.SemiBold,
                )
            }
        }

        // The consequence — the one line the scorer actually needs from all of this.
        val armed = winner != 0 && decision.isNotBlank()
        val battingFirst = when {
            !armed -> 0
            decision == "Bat" -> winner
            else -> if (winner == 1) 2 else 1
        }
        androidx.compose.animation.AnimatedVisibility(
            visible = armed,
            enter = androidx.compose.animation.expandVertically(tween(220)) + androidx.compose.animation.fadeIn(tween(260)),
            exit = androidx.compose.animation.shrinkVertically(tween(180)) + androidx.compose.animation.fadeOut(tween(120)),
        ) {
            val bat = if (battingFirst == 2) teamBName else teamAName
            val bowl = if (battingFirst == 2) teamAName else teamBName
            Row(
                modifier = Modifier.fillMaxWidth().padding(top = 18.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(6.dp).clip(CircleShape).background(TossBlue))
                Spacer(Modifier.width(10.dp))
                Text(
                    androidx.compose.ui.text.buildAnnotatedString {
                        val line = stringResource(com.haraan.app.R.string.toss_order_fmt, bat, bowl)
                        append(line)
                        listOf(bat, bowl).forEach { n ->
                            val at = line.indexOf(n)
                            if (at >= 0) addStyle(
                                androidx.compose.ui.text.SpanStyle(color = TossText1, fontWeight = FontWeight.Bold),
                                at, at + n.length,
                            )
                        }
                    },
                    color = TossText2, fontSize = 14.sp,
                )
            }
        }
        Spacer(Modifier.height(24.dp))
    }

    // One button. Armed, it names the next step; not yet, it names what's missing.
    val armed = winner != 0 && decision.isNotBlank()
    val label = when {
        winner == 0 -> stringResource(com.haraan.app.R.string.toss_need_winner)
        decision.isBlank() -> stringResource(com.haraan.app.R.string.toss_need_choice)
        else -> stringResource(com.haraan.app.R.string.toss_next_openers)
    }
    TossCta(label = label, armed = armed, trailingIcon = Icons.AutoMirrored.Filled.ArrowForward) {
        when {
            winner == 0 -> nudge(winnerShake)
            decision.isBlank() -> nudge(choiceShake)
            else -> { hapticConfirm(view); onPlay() }
        }
    }
}

/** Small letterspaced caps over a control — a label, not a heading competing with the coin. */
@Composable
private fun FieldLabel(text: String) {
    Text(
        text.uppercase(),
        color = TossText3, fontSize = 11.sp, fontWeight = FontWeight.Bold,
        letterSpacing = 1.2.sp,
    )
}

/**
 * A segmented control with one sliding pill. The pill springs between halves (and scales in
 * on the first pick); the tapped half gives under the finger; a change ticks. [selected] -1
 * means nothing chosen yet. Disabled, it dims and routes taps to [onDisabledTap] so the
 * screen can point at what has to come first.
 */
@Composable
private fun TossSegment(
    count: Int,
    selected: Int,
    enabled: Boolean,
    height: androidx.compose.ui.unit.Dp,
    modifier: Modifier = Modifier,
    onDisabledTap: () -> Unit = {},
    onSelect: (Int) -> Unit,
    content: @Composable (index: Int, selected: Boolean) -> Unit,
) {
    val view = LocalView.current
    val dim by androidx.compose.animation.core.animateFloatAsState(if (enabled) 1f else 0.45f, tween(220), label = "segDim")
    androidx.compose.foundation.layout.BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .height(height)
            .graphicsLayer { alpha = dim }
            .clip(RoundedCornerShape(18.dp))
            .background(Color(0xFFEEF1F6))
            .padding(4.dp),
    ) {
        val slot = maxWidth / count
        val pillX by androidx.compose.animation.core.animateDpAsState(
            targetValue = slot * selected.coerceAtLeast(0),
            animationSpec = androidx.compose.animation.core.spring(dampingRatio = 0.72f, stiffness = 520f),
            label = "segPill",
        )
        val pillIn by androidx.compose.animation.core.animateFloatAsState(
            if (selected >= 0) 1f else 0f,
            androidx.compose.animation.core.spring(dampingRatio = 0.6f, stiffness = 600f),
            label = "segPillIn",
        )
        // The pill: white lifted surface, blue tint and a blue hairline — selected, not a
        // second primary button.
        Box(
            Modifier
                .offset(x = pillX)
                .width(slot)
                .fillMaxHeight()
                .graphicsLayer { alpha = pillIn; scaleX = 0.9f + 0.1f * pillIn; scaleY = 0.9f + 0.1f * pillIn }
                .shadow(3.dp, RoundedCornerShape(14.dp), spotColor = TossBlue.copy(alpha = 0.35f))
                .clip(RoundedCornerShape(14.dp))
                .background(Color.White)
                .background(TossBlue.copy(alpha = 0.07f))
                .border(1.5.dp, TossBlue.copy(alpha = 0.85f), RoundedCornerShape(14.dp)),
        )
        Row(Modifier.fillMaxSize()) {
            repeat(count) { i ->
                val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
                val pressed by interaction.collectIsPressedAsState()
                val press by androidx.compose.animation.core.animateFloatAsState(
                    if (pressed && enabled) 0.95f else 1f,
                    androidx.compose.animation.core.spring(dampingRatio = 0.55f, stiffness = 900f),
                    label = "segPress",
                )
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .graphicsLayer { scaleX = press; scaleY = press }
                        .clickable(interactionSource = interaction, indication = null) {
                            when {
                                !enabled -> onDisabledTap()
                                i != selected -> { hapticTick(view); onSelect(i) }
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) { content(i, i == selected) }
            }
        }
    }
}

/** A cricket bat, drawn — blade, shoulders and handle — at icon size. */
@Composable
private fun BatGlyph(tint: Color) {
    androidx.compose.foundation.Canvas(Modifier.size(20.dp)) {
        val w = size.width
        rotate(40f, center) {
            // Blade
            drawRoundRect(
                tint,
                topLeft = androidx.compose.ui.geometry.Offset(w * 0.36f, w * 0.34f),
                size = androidx.compose.ui.geometry.Size(w * 0.28f, w * 0.64f),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(w * 0.1f),
            )
            // Handle
            drawRoundRect(
                tint,
                topLeft = androidx.compose.ui.geometry.Offset(w * 0.455f, w * 0.02f),
                size = androidx.compose.ui.geometry.Size(w * 0.09f, w * 0.36f),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(w * 0.045f),
            )
        }
    }
}

/** A cricket ball, drawn — the seam is what makes it cricket and not baseball. */
@Composable
private fun BallGlyph(tint: Color) {
    androidx.compose.foundation.Canvas(Modifier.size(20.dp)) {
        val r = size.minDimension / 2f
        drawCircle(tint, radius = r * 0.86f, center = center)
        // Seam: two stitched lines across the ball, cut out of the fill.
        rotate(-35f, center) {
            listOf(-0.16f, 0.16f).forEach { dx ->
                drawLine(
                    Color.White,
                    start = androidx.compose.ui.geometry.Offset(center.x + r * dx, center.y - r * 0.82f),
                    end = androidx.compose.ui.geometry.Offset(center.x + r * dx, center.y + r * 0.82f),
                    strokeWidth = r * 0.11f,
                    pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(r * 0.16f, r * 0.12f)),
                )
            }
        }
    }
}

/**
 * A struck gold coin the user taps to toss. Each face is *stamped* with a team's initial
 * (heads = Team A, tails = Team B) in embossed relief. On landing it reports the winning
 * side via [onResult] (1 = A, 2 = B).
 *
 * The toss is played as physics, not as a spin-in-place, because a 1s spin read as instant:
 *   press   — the coin sinks under the thumb (anticipation)
 *   launch  — it rises toward the camera (up + larger) tumbling end over end, fast
 *   apex    — the tumble slows so the faces become readable: the suspense beat
 *   fall    — gravity pulls it back, accelerating
 *   impact  — squash, one firm knock, the clink; then a small second bounce and a wobble
 *             that damps out before the result is announced (~2.4s end to end)
 * The tumble is drawn orthographically (face squashes by |cos θ|) over a rim-coloured slab,
 * so the coin has real thickness edge-on instead of vanishing into a hairline.
 * Haptics follow the physics: a flick on launch, faint ticks as each face passes that thin
 * out as the spin slows, a firm knock on impact and a light one on the bounce.
 */
@Composable
private fun FlipCoin(
    teamAName: String,
    teamBName: String,
    onResult: (Int) -> Unit,
) {
    val rotation = remember { androidx.compose.animation.core.Animatable(0f) }   // tumble, degrees
    val lift = remember { androidx.compose.animation.core.Animatable(0f) }     // 0 = ground, 1 = apex
    val squash = remember { androidx.compose.animation.core.Animatable(1f) }     // impact squash
    val wobble = remember { androidx.compose.animation.core.Animatable(0f) }     // settle tilt, degrees
    val ring = remember { androidx.compose.animation.core.Animatable(0f) }       // landing glow, 0→1
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val view = LocalView.current
    var flipping by remember { mutableStateOf(false) }
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val pressScale by androidx.compose.animation.core.animateFloatAsState(
        targetValue = if (pressed && !flipping) 0.93f else 1f,
        animationSpec = androidx.compose.animation.core.spring(dampingRatio = 0.5f, stiffness = 900f),
        label = "coinPress",
    )

    // Idle "breathing" so the coin reads as tappable; frozen while it's in the air.
    val pulse = rememberInfiniteTransition(label = "coinPulse")
    val idleScale by pulse.animateFloat(
        initialValue = 1f, targetValue = 1.04f,
        animationSpec = infiniteRepeatable(tween(1300, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "coinScale",
    )

    // An occasional glint crossing the coin at rest — reads as polished metal, and draws the eye.
    val idleGlint by pulse.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(
            androidx.compose.animation.core.keyframes {
                durationMillis = 4200
                0f at 0
                0f at 2600
                1f at 3500 using FastOutSlowInEasing
                1f at 4200
            },
        ),
        label = "coinGlint",
    )

    val coin = 150.dp
    val thickness = 11.dp
    val rise = 64.dp
    val apexScale = 0.30f          // grows 30% at the apex — rising toward the camera

    fun toss() {
        if (flipping) return
        scope.launch {
            flipping = true
            val tails = Random.nextBoolean()
            val land = if (tails) 180f else 0f
            val target = rotation.value - (rotation.value % 360f) + 360f * 8 + land
            cricketThud(ctx, Thud.TICK)   // the flick off the thumb

            kotlinx.coroutines.coroutineScope {
                // Flight: decelerating climb, accelerating fall — gravity, not a tween.
                launch {
                    lift.animateTo(1f, tween(900, easing = androidx.compose.animation.core.CubicBezierEasing(0.33f, 1f, 0.68f, 1f)))
                    lift.animateTo(0f, tween(820, easing = androidx.compose.animation.core.CubicBezierEasing(0.45f, 0f, 0.9f, 0.55f)))
                }
                // Tumble across the whole flight, slowing so the last turns are readable and it
                // arrives flat exactly as it lands. A faint tick each time a face comes round —
                // skipped while they blur past, so the ticks slow down with the coin.
                var lastHalf = (rotation.value / 180f).toInt()
                var lastTick = 0L
                rotation.animateTo(
                    targetValue = target,
                    animationSpec = tween(1720, easing = androidx.compose.animation.core.CubicBezierEasing(0.18f, 0.62f, 0.32f, 1f)),
                ) {
                    val half = (value / 180f).toInt()
                    val now = android.os.SystemClock.uptimeMillis()
                    if (half != lastHalf) {
                        lastHalf = half
                        if (now - lastTick >= 110L && value < target - 60f) {
                            lastTick = now
                            view.performHapticFeedback(android.view.HapticFeedbackConstants.CLOCK_TICK)
                        }
                    }
                }
            }

            // Impact: squash + one firm knock + the clink, then a hop and a damped wobble.
            view.playSoundEffect(android.view.SoundEffectConstants.CLICK)
            launch { cricketThud(ctx, Thud.FOUR) }
            launch {
                squash.snapTo(0.9f)
                squash.animateTo(1f, androidx.compose.animation.core.spring(dampingRatio = 0.35f, stiffness = 700f))
            }
            launch {
                wobble.snapTo(if (tails) -14f else 14f)
                wobble.animateTo(0f, androidx.compose.animation.core.spring(dampingRatio = 0.22f, stiffness = 260f))
            }
            lift.animateTo(0.13f, tween(170, easing = androidx.compose.animation.core.CubicBezierEasing(0.33f, 1f, 0.68f, 1f)))
            lift.animateTo(0f, tween(160, easing = androidx.compose.animation.core.CubicBezierEasing(0.32f, 0f, 0.67f, 0f)))
            launch { cricketThud(ctx, Thud.TICK) }
            kotlinx.coroutines.delay(260)

            // Settled — a gold ring rings out once, and the result is called.
            launch {
                ring.snapTo(0f)
                ring.animateTo(1f, tween(650, easing = FastOutSlowInEasing))
            }
            flipping = false
            onResult(if (tails) 2 else 1)   // heads = A, tails = B
        }
    }

    // Stage: the coin rests near the bottom and flies up over the hint text above it.
    Box(
        modifier = Modifier.fillMaxWidth().height(214.dp),
        contentAlignment = Alignment.BottomCenter,
    ) {
        // Ground shadow — sits just under the coin's lower edge so it reads as contact at
        // rest; widens and fades as the coin climbs.
        androidx.compose.foundation.Canvas(
            modifier = Modifier
                .padding(bottom = 12.dp)
                .size(width = 136.dp, height = 20.dp)
                .graphicsLayer {
                    val h = lift.value
                    scaleX = 1f - 0.4f * h
                    scaleY = 1f - 0.4f * h
                    alpha = 1f - 0.75f * h
                },
        ) {
            // An oval falloff: a radial gradient drawn round, then stretched to the oval.
            val rr = size.height / 2f
            withTransform({ scale(size.width / size.height, 1f, center) }) {
                drawCircle(
                    Brush.radialGradient(
                        0f to Color(0x55301E00), 0.55f to Color(0x22301E00), 1f to Color.Transparent,
                        center = center, radius = rr,
                    ),
                    radius = rr, center = center,
                )
            }
        }

        Box(
            modifier = Modifier
                .padding(bottom = 22.dp)
                .size(coin)
                .graphicsLayer {
                    val h = lift.value
                    val s = (if (flipping) 1f else idleScale) * pressScale * (1f + apexScale * h)
                    translationY = -rise.toPx() * h
                    scaleX = s * (2f - squash.value)
                    scaleY = s * squash.value
                    rotationZ = wobble.value * 0.35f
                }
                .clickable(interactionSource = interaction, indication = null, enabled = !flipping) { toss() },
            contentAlignment = Alignment.Center,
        ) {
            val angle = rotation.value + wobble.value
            val rad = Math.toRadians(angle.toDouble())
            val cosA = kotlin.math.cos(rad).toFloat()
            val sinA = kotlin.math.sin(rad).toFloat()
            val faceScale = kotlin.math.abs(cosA).coerceAtLeast(0.001f)
            val showTails = cosA < 0f
            // The glint: idle it sweeps across on its own; in the air it rides the tumble, so
            // light rolls over the face every half-turn.
            val glint = if (flipping) ((angle / 180f) % 1f + 1f) % 1f else idleGlint

            // Landing glow — a thin gold ring that expands and fades once.
            if (ring.value in 0.001f..0.999f) {
                Box(
                    Modifier
                        .matchParentSize()
                        .graphicsLayer {
                            val r = ring.value
                            scaleX = 1f + 0.55f * r; scaleY = 1f + 0.55f * r
                            alpha = (1f - r) * 0.8f
                        }
                        .border(BorderStroke(3.dp, CoinMid), CircleShape),
                )
            }

            // The coin's edge: a milled slab behind the face, as tall as the face's projection
            // plus the visible thickness, shifted toward the side that's showing.
            Box(
                Modifier
                    .matchParentSize()
                    .graphicsLayer {
                        val t = thickness.toPx()
                        val edge = kotlin.math.abs(sinA) * t
                        scaleY = (size.height * faceScale + edge) / size.height
                        translationY = sinA * t / 2f
                    }
                    .clip(CircleShape)
                    .background(CoinEdgeReeds)
                    .background(Brush.horizontalGradient(listOf(Color(0xB0402A00), Color(0x10000000), Color(0x40FFFFFF), Color(0x10000000), Color(0xB0402A00)))),
            )

            // The face.
            Box(
                modifier = Modifier
                    .matchParentSize()
                    .graphicsLayer { scaleY = faceScale }
                    .clip(CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                val name = if (showTails) teamBName else teamAName
                CoinFace(
                    monogram = name.trim().firstOrNull()?.uppercaseChar()?.toString() ?: if (showTails) "T" else "H",
                    legend = name.trim().uppercase().take(14),
                    side = if (showTails) "TAILS" else "HEADS",
                    glint = glint,
                )
                // Tilting away from the light darkens the face — the tumble reads as metal
                // catching and losing the light, not a flat sticker squashing.
                Box(
                    Modifier
                        .matchParentSize()
                        .background(Color.Black.copy(alpha = (1f - faceScale) * 0.42f)),
                )
            }
        }
    }
}

// Milled edge: fine alternating bands, read as the reeding on a real coin's rim.
private val CoinEdgeReeds = Brush.horizontalGradient(
    List(28) { i -> if (i % 2 == 0) Color(0xFFD9A93A) else Color(0xFF9C6A12) }
)

/**
 * One face of the coin, struck like a real minted piece rather than a gradient disc:
 * a polished rim lit by a conic sweep (how turned metal actually catches light) with milled
 * reeding round its outer edge, a recessed field, a beaded ring, the team's name engraved
 * round the top and HEADS / TAILS round the bottom, and a serif monogram in relief.
 * [glint] 0..1 walks a soft specular band diagonally across the face.
 */
@Composable
private fun CoinFace(monogram: String, legend: String, side: String, glint: Float) {
    val density = androidx.compose.ui.platform.LocalDensity.current
    val legendPx = with(density) { 10.5.sp.toPx() }
    val engrave = remember(legendPx) {
        android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
            textSize = legendPx
            typeface = android.graphics.Typeface.create(android.graphics.Typeface.SERIF, android.graphics.Typeface.BOLD)
            letterSpacing = 0.18f
        }
    }
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        androidx.compose.foundation.Canvas(Modifier.fillMaxSize()) {
            val r = size.minDimension / 2f
            val c = center
            val px = 1.dp.toPx()

            // Rim — polished, lit by a conic sweep.
            drawCircle(
                Brush.sweepGradient(
                    listOf(
                        Color(0xFFFFEDB0), Color(0xFFD9A636), Color(0xFF8E5F10), Color(0xFFE8BC4E),
                        Color(0xFFFFF1C2), Color(0xFFC08A22), Color(0xFF7E540C), Color(0xFFDDAE44), Color(0xFFFFEDB0),
                    ),
                    c,
                ),
                radius = r, center = c,
            )
            // Reeding round the outer edge.
            val reeds = 132
            for (i in 0 until reeds) {
                val a = (i * 2.0 * Math.PI / reeds)
                val dx = kotlin.math.cos(a).toFloat(); val dy = kotlin.math.sin(a).toFloat()
                drawLine(
                    color = if (i % 2 == 0) Color(0x33000000) else Color(0x40FFFFFF),
                    start = androidx.compose.ui.geometry.Offset(c.x + dx * r * 0.935f, c.y + dy * r * 0.935f),
                    end = androidx.compose.ui.geometry.Offset(c.x + dx * r * 0.995f, c.y + dy * r * 0.995f),
                    strokeWidth = 1.1f * px,
                )
            }
            // Bevel between rim and field: dark lip below, bright lip above.
            drawCircle(Color(0x66503400), radius = r * 0.885f, center = c, style = androidx.compose.ui.graphics.drawscope.Stroke(2.2f * px))
            drawCircle(Color(0x99FFF4D0), radius = r * 0.905f, center = c, style = androidx.compose.ui.graphics.drawscope.Stroke(0.9f * px))

            // Field — satin gold, lit from the top-left.
            drawCircle(
                Brush.radialGradient(
                    listOf(Color(0xFFFFF0BF), Color(0xFFF2CC66), Color(0xFFDCAA3C), Color(0xFFB9831F)),
                    center = androidx.compose.ui.geometry.Offset(c.x - r * 0.32f, c.y - r * 0.38f),
                    radius = r * 1.35f,
                ),
                radius = r * 0.875f, center = c,
            )
            // Recess — the field sits below the rim, so its edge falls into shadow.
            drawCircle(
                Brush.radialGradient(
                    0.78f to Color.Transparent, 1f to Color(0x38402A00),
                    center = c, radius = r * 0.875f,
                ),
                radius = r * 0.875f, center = c,
            )

            // Beaded ring.
            val beads = 56
            for (i in 0 until beads) {
                val a = (i * 2.0 * Math.PI / beads)
                val p = androidx.compose.ui.geometry.Offset(
                    c.x + kotlin.math.cos(a).toFloat() * r * 0.8f,
                    c.y + kotlin.math.sin(a).toFloat() * r * 0.8f,
                )
                drawCircle(Color(0x88704A08), radius = 1.5f * px, center = p + androidx.compose.ui.geometry.Offset(0.4f * px, 0.5f * px))
                drawCircle(Color(0xFFF6D57C), radius = 1.25f * px, center = p)
            }

            // Engraved legend: team name over the top, HEADS / TAILS under the bottom.
            drawIntoCanvas { canvas ->
                val nc = canvas.nativeCanvas
                fun arc(text: String, radius: Float, top: Boolean) {
                    if (text.isBlank()) return
                    val path = android.graphics.Path().apply {
                        val oval = android.graphics.RectF(c.x - radius, c.y - radius, c.x + radius, c.y + radius)
                        if (top) addArc(oval, 180f, 180f) else addArc(oval, 180f, -180f)
                    }
                    val len = (Math.PI * radius).toFloat()
                    val w = engrave.measureText(text)
                    val h = (len - w) / 2f
                    // Highlight a hair up-left, then the cut — reads as letters struck in.
                    engrave.color = 0x99FFF6D6.toInt()
                    nc.drawTextOnPath(text, path, h + 0.5f * px, -0.6f * px, engrave)
                    engrave.color = 0xFF7E5410.toInt()
                    nc.drawTextOnPath(text, path, h, 0f, engrave)
                }
                arc(legend, r * 0.625f, top = true)
                arc(side, r * 0.705f, top = false)
            }
        }

        // Monogram in relief: soft drop to the lower-right, a bright top-left lip, then the
        // letter itself in darker brass graded top to bottom.
        val glyph = androidx.compose.ui.text.TextStyle(
            fontSize = 58.sp,
            fontWeight = FontWeight.Bold,
            fontFamily = androidx.compose.ui.text.font.FontFamily.Serif,
        )
        Text(monogram, style = glyph, color = Color(0x4D402A00), modifier = Modifier.offset(0.8.dp, 1.2.dp))
        Text(monogram, style = glyph, color = Color(0xCCFFF6D6), modifier = Modifier.offset((-0.7).dp, (-0.7).dp))
        Text(
            monogram,
            style = glyph.copy(brush = Brush.verticalGradient(listOf(Color(0xFFB98A2A), Color(0xFF8A5C12), Color(0xFF6E470B)))),
        )

        // Specular glint — a soft diagonal band of light walking across the face.
        androidx.compose.foundation.Canvas(Modifier.fillMaxSize()) {
            if (glint <= 0f || glint >= 1f) return@Canvas
            val w = size.width
            val x = -w * 0.6f + glint * w * 2.2f
            drawRect(
                Brush.linearGradient(
                    0f to Color.Transparent, 0.5f to Color(0x55FFFFFF), 1f to Color.Transparent,
                    start = androidx.compose.ui.geometry.Offset(x - w * 0.22f, 0f),
                    end = androidx.compose.ui.geometry.Offset(x + w * 0.22f, w * 0.35f),
                ),
            )
        }
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

/**
 * The opening lineup, set on the ground itself: a top-down pitch with the striker on strike
 * at the near end, the non-striker at the far end and the opening bowler at the top of his
 * run-up, ball in hand. Each player is a token you tap to pick that role; the squad it draws
 * from is listed once, below, as real rows (photo or monogram, blue tick, C / VC, guest).
 * Picking fills the active role and moves on, so the common path is three taps. Swap ends
 * makes the batters actually run past each other. The button names what's missing and,
 * tapped early, jumps to and shakes that token instead of explaining.
 */
@Composable
private fun ColumnScope.LineupStage(
    battingName: String,
    bowlingName: String,
    battingEmblem: String?,
    bowlingEmblem: String?,
    battingPhoto: android.net.Uri?,
    bowlingPhoto: android.net.Uri?,
    battingSquad: List<SquadMember>,
    bowlingSquad: List<SquadMember>,
    tossLine: String,
    onStart: (striker: SquadMember?, nonStriker: SquadMember?, bowler: SquadMember?) -> Unit,
) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    var striker by remember { mutableStateOf(battingSquad.getOrNull(0)) }
    var nonStriker by remember { mutableStateOf(battingSquad.getOrNull(1)) }
    var bowler by remember { mutableStateOf(bowlingSquad.getOrNull(0)) }
    // 0 striker, 1 non-striker, 2 opening bowler.
    var active by remember { mutableStateOf(0) }
    val shakes = remember { List(3) { androidx.compose.animation.core.Animatable(0f) } }

    fun shake(slot: Int) {
        scope.launch {
            shakes[slot].snapTo(0f)
            shakes[slot].animateTo(
                0f,
                androidx.compose.animation.core.keyframes {
                    durationMillis = 360
                    -9f at 50; 8f at 110; -6f at 170; 4f at 230; -2f at 290
                },
            )
        }
    }

    fun assign(m: SquadMember) {
        hapticTick(view)
        when (active) {
            0 -> { if (m == nonStriker) nonStriker = striker; striker = m }
            1 -> { if (m == striker) striker = nonStriker; nonStriker = m }
            else -> bowler = m
        }
        // Move on to the next role — the order a scorer fills a sheet in.
        if (active < 2) active += 1
    }

    fun swapEnds() {
        hapticTick(view)
        val s = striker; striker = nonStriker; nonStriker = s
        // A second knock as they make their ground at the other end.
        scope.launch { kotlinx.coroutines.delay(520); hapticTick(view) }
    }

    val roleNames = listOf(
        stringResource(com.haraan.app.R.string.toss_striker),
        stringResource(com.haraan.app.R.string.toss_non_striker),
        stringResource(com.haraan.app.R.string.toss_opening_bowler),
    )

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .weight(1f)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp),
    ) {
        Spacer(Modifier.height(2.dp))
        Text(tossLine, color = TossText3, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.padding(horizontal = 4.dp))
        Spacer(Modifier.height(12.dp))

        PitchBoard(
            battingName = battingName, bowlingName = bowlingName,
            battingEmblem = battingEmblem, bowlingEmblem = bowlingEmblem,
            battingPhoto = battingPhoto, bowlingPhoto = bowlingPhoto,
            striker = striker, nonStriker = nonStriker, bowler = bowler,
            roleNames = roleNames,
            active = active,
            shakes = shakes.map { it.value },
            canSwap = striker != null || nonStriker != null,
            onSlot = { hapticTick(view); active = it },
            onSwap = { swapEnds() },
        )

        Spacer(Modifier.height(22.dp))
        // The picker for whichever role is active — one list, not the squad repeated per role.
        androidx.compose.animation.AnimatedContent(
            targetState = active,
            transitionSpec = {
                (androidx.compose.animation.fadeIn(tween(200)) +
                    androidx.compose.animation.slideInVertically(tween(220)) { it / 12 }) togetherWith
                    androidx.compose.animation.fadeOut(tween(120))
            },
            label = "lineupPicker",
        ) { slot ->
            val list = if (slot < 2) battingSquad else bowlingSquad
            val side = if (slot < 2) battingName else bowlingName
            Column(Modifier.padding(horizontal = 4.dp)) {
                FieldLabel(stringResource(com.haraan.app.R.string.toss_choose_fmt, roleNames[slot], side))
                Spacer(Modifier.height(10.dp))
                if (list.isEmpty()) {
                    Text(
                        stringResource(com.haraan.app.R.string.toss_no_players),
                        color = TossText3, fontSize = 13.sp,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                } else {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(18.dp))
                            .background(TossSurface)
                            .border(1.dp, TossStroke, RoundedCornerShape(18.dp)),
                    ) {
                        list.forEachIndexed { i, m ->
                            if (i > 0) androidx.compose.material3.HorizontalDivider(
                                color = TossStroke, modifier = Modifier.padding(start = 64.dp),
                            )
                            val holds = when (m) {
                                striker -> if (slot < 2) 0 else -1
                                nonStriker -> if (slot < 2) 1 else -1
                                bowler -> if (slot == 2) 2 else -1
                                else -> -1
                            }
                            PlayerRow(
                                member = m,
                                roleTag = if (holds >= 0) roleNames[holds] else null,
                                chosenHere = holds == slot,
                                onClick = { assign(m) },
                            )
                        }
                    }
                }
            }
        }
        Spacer(Modifier.height(20.dp))
    }

    // Empty squads fall back to default names server-side, so they never block the start.
    val needStriker = striker == null && battingSquad.isNotEmpty()
    val needBowler = bowler == null && bowlingSquad.isNotEmpty()
    val armed = !needStriker && !needBowler
    val label = when {
        needStriker -> stringResource(com.haraan.app.R.string.toss_pick_striker)
        needBowler -> stringResource(com.haraan.app.R.string.toss_pick_bowler)
        else -> stringResource(com.haraan.app.R.string.toss_start_match)
    }
    TossCta(label = label, armed = armed, icon = Icons.Filled.PlayArrow) {
        when {
            needStriker -> { hapticTick(view); active = 0; shake(0) }
            needBowler -> { hapticTick(view); active = 2; shake(2) }
            else -> { hapticConfirm(view); onStart(striker, nonStriker, bowler) }
        }
    }
}

// Ground tones — illustration, not UI colour: mown grass and a dry, played-on strip.
private val GrassA = Color(0xFF2E7A45)
private val GrassB = Color(0xFF34864C)
private val GrassEdge = Color(0xFF1F5C33)
private val PitchLight = Color(0xFFEBDDB4)
private val PitchDark = Color(0xFFD6BF8C)
private val CreaseWhite = Color(0xF2FFFFFF)

/**
 * The ground: mown stripes, the 22-yard strip with its creases and stumps, and the three
 * players standing where they will actually stand. Batters are keyed by who they are, so a
 * swap animates each of them running to the other end rather than re-labelling two spots.
 */
@Composable
private fun PitchBoard(
    battingName: String,
    bowlingName: String,
    battingEmblem: String?,
    bowlingEmblem: String?,
    battingPhoto: android.net.Uri?,
    bowlingPhoto: android.net.Uri?,
    striker: SquadMember?,
    nonStriker: SquadMember?,
    bowler: SquadMember?,
    roleNames: List<String>,
    active: Int,
    shakes: List<Float>,
    canSwap: Boolean,
    onSlot: (Int) -> Unit,
    onSwap: () -> Unit,
) {
    val boardH = 356.dp
    val nearCrease = boardH - 96.dp      // striker's popping crease
    val farCrease = 96.dp                // non-striker's end
    androidx.compose.foundation.layout.BoxWithConstraints(
        modifier = Modifier
            .fillMaxWidth()
            .height(boardH)
            .shadow(10.dp, RoundedCornerShape(26.dp), spotColor = Color(0x552E7A45))
            .clip(RoundedCornerShape(26.dp)),
    ) {
        val w = maxWidth
        val cx = w / 2
        androidx.compose.foundation.Canvas(Modifier.fillMaxSize()) {
            val px = 1.dp.toPx()
            // Mown stripes, then a vignette so the edges fall off like a broadcast frame.
            val band = 30.dp.toPx()
            var y = 0f; var i = 0
            while (y < size.height) {
                drawRect(if (i % 2 == 0) GrassA else GrassB, androidx.compose.ui.geometry.Offset(0f, y), androidx.compose.ui.geometry.Size(size.width, band))
                y += band; i++
            }
            drawRect(
                Brush.radialGradient(
                    0.55f to Color.Transparent, 1f to GrassEdge.copy(alpha = 0.75f),
                    center = center, radius = size.maxDimension * 0.62f,
                ),
            )

            // The strip.
            val pw = 84.dp.toPx()
            val left = center.x - pw / 2
            val top = 26.dp.toPx(); val bottom = size.height - 26.dp.toPx()
            drawRoundRect(
                Brush.verticalGradient(listOf(PitchDark, PitchLight, PitchLight, PitchDark), startY = top, endY = bottom),
                topLeft = androidx.compose.ui.geometry.Offset(left, top),
                size = androidx.compose.ui.geometry.Size(pw, bottom - top),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(5.dp.toPx()),
            )
            // Worn patches where the bowlers land and the batters stand.
            listOf(farCrease.toPx(), nearCrease.toPx()).forEach { cy ->
                drawOval(
                    Brush.radialGradient(
                        listOf(Color(0x1C704F1A), Color.Transparent),
                        center = androidx.compose.ui.geometry.Offset(center.x, cy),
                        radius = pw * 0.42f,
                    ),
                    topLeft = androidx.compose.ui.geometry.Offset(center.x - pw * 0.42f, cy - 30.dp.toPx()),
                    size = androidx.compose.ui.geometry.Size(pw * 0.84f, 60.dp.toPx()),
                )
            }

            // Creases at each end: popping crease across and beyond the strip, bowling crease
            // through the stumps, return creases down the sides — then the stumps, top-down.
            fun end(popping: Float, stumps: Float) {
                val ext = 12.dp.toPx()
                drawLine(CreaseWhite, androidx.compose.ui.geometry.Offset(left - ext, popping), androidx.compose.ui.geometry.Offset(left + pw + ext, popping), 2f * px)
                drawLine(CreaseWhite, androidx.compose.ui.geometry.Offset(left + 8.dp.toPx(), stumps), androidx.compose.ui.geometry.Offset(left + pw - 8.dp.toPx(), stumps), 1.6f * px)
                val retTop = minOf(popping, stumps) - 8.dp.toPx(); val retBot = maxOf(popping, stumps) + 8.dp.toPx()
                listOf(left + 8.dp.toPx(), left + pw - 8.dp.toPx()).forEach { x ->
                    drawLine(CreaseWhite, androidx.compose.ui.geometry.Offset(x, retTop), androidx.compose.ui.geometry.Offset(x, retBot), 1.6f * px)
                }
                listOf(-5f, 0f, 5f).forEach { dx ->
                    drawCircle(Color(0x55000000), radius = 2.6f * px, center = androidx.compose.ui.geometry.Offset(center.x + dx * px + 0.8f * px, stumps + 1f * px))
                    drawCircle(Color(0xFFFFF8E8), radius = 2.3f * px, center = androidx.compose.ui.geometry.Offset(center.x + dx * px, stumps))
                }
            }
            end(popping = farCrease.toPx(), stumps = farCrease.toPx() - 22.dp.toPx())
            end(popping = nearCrease.toPx(), stumps = nearCrease.toPx() + 22.dp.toPx())

            // The bowler's run-up: a faint dashed line back from the far end.
            val bx = center.x + 92.dp.toPx(); val by = farCrease.toPx() - 30.dp.toPx()
            drawLine(
                Color(0x59FFFFFF),
                start = androidx.compose.ui.geometry.Offset(bx + 26.dp.toPx(), by - 18.dp.toPx()),
                end = androidx.compose.ui.geometry.Offset(size.width - 6.dp.toPx(), 4.dp.toPx()),
                strokeWidth = 2f * px,
                pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(6.dp.toPx(), 6.dp.toPx())),
            )
        }

        // Broadcast-style side labels at each end.
        SideTag(bowlingName, bowlingEmblem, bowlingPhoto, stringResource(com.haraan.app.R.string.toss_bowling),
            Modifier.align(Alignment.TopStart).padding(12.dp))
        SideTag(battingName, battingEmblem, battingPhoto, stringResource(com.haraan.app.R.string.toss_batting),
            Modifier.align(Alignment.BottomStart).padding(12.dp))

        // Where each role stands.
        val spots = listOf(
            androidx.compose.ui.unit.DpOffset(cx, nearCrease),           // striker, on strike
            androidx.compose.ui.unit.DpOffset(cx - 88.dp, farCrease),    // non-striker, backing up
            androidx.compose.ui.unit.DpOffset(cx + 92.dp, farCrease - 30.dp), // bowler, top of the run-up
        )
        // Batters keyed by identity so a swap is a run, not a relabel.
        listOf(0 to striker, 1 to nonStriker).forEach { (slot, m) ->
            androidx.compose.runtime.key(m?.let { it.id.ifBlank { it.name } } ?: "empty-$slot") {
                PitchToken(
                    member = m, role = roleNames[slot], active = active == slot,
                    at = spots[slot], shake = shakes[slot], withBall = false,
                    onClick = { onSlot(slot) },
                )
            }
        }
        androidx.compose.runtime.key(bowler?.let { it.id.ifBlank { it.name } } ?: "empty-2") {
            PitchToken(
                member = bowler, role = roleNames[2], active = active == 2,
                at = spots[2], shake = shakes[2], withBall = true,
                onClick = { onSlot(2) },
            )
        }

        // Swap ends, in the middle of the strip where the batters cross.
        val swapTurn = remember { androidx.compose.animation.core.Animatable(0f) }
        val swapScope = rememberCoroutineScope()
        Box(
            modifier = Modifier
                .offset(x = cx - 20.dp, y = boardH / 2 - 20.dp)
                .size(40.dp)
                .shadow(6.dp, CircleShape)
                .clip(CircleShape)
                .background(Color.White)
                .clickable(enabled = canSwap) {
                    onSwap()
                    swapScope.launch {
                        swapTurn.animateTo(swapTurn.value + 180f, androidx.compose.animation.core.spring(dampingRatio = 0.6f, stiffness = 300f))
                    }
                },
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                Icons.Filled.SwapVert,
                contentDescription = stringResource(com.haraan.app.R.string.toss_swap_ends),
                tint = TossText1,
                modifier = Modifier.size(20.dp).graphicsLayer { rotationZ = swapTurn.value },
            )
        }
    }
}

/** "ffff · BOWLING" at the top of the frame, crest first — the fixture, said on the ground. */
@Composable
private fun SideTag(name: String, emblem: String?, photo: android.net.Uri?, doing: String, modifier: Modifier) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Color(0x40000000))
            .padding(start = 4.dp, end = 9.dp, top = 4.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        TeamCrest(emblem, photo, TossBlue, name, size = 18.dp)
        Spacer(Modifier.width(6.dp))
        Text(name, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold, maxLines = 1,
            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis, modifier = Modifier.widthIn(max = 110.dp))
        Spacer(Modifier.width(6.dp))
        Text(doing.uppercase(), color = Color.White.copy(alpha = 0.72f), fontSize = 9.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
    }
}

/**
 * A player standing on the ground. Glides (springs) to [at] when their spot changes, drops
 * in with a bounce when first picked, breathes a blue ring while their role is the one being
 * chosen, and gives under the finger. An empty role is a dashed chalk circle with a +.
 */
@Composable
private fun PitchToken(
    member: SquadMember?,
    role: String,
    active: Boolean,
    at: androidx.compose.ui.unit.DpOffset,
    shake: Float,
    withBall: Boolean,
    onClick: () -> Unit,
) {
    val token = 56.dp
    val runSpec = androidx.compose.animation.core.spring<androidx.compose.ui.unit.Dp>(dampingRatio = 0.82f, stiffness = 110f)
    val x by androidx.compose.animation.core.animateDpAsState(at.x, runSpec, label = "tokenX")
    val y by androidx.compose.animation.core.animateDpAsState(at.y, runSpec, label = "tokenY")
    val drop = remember { androidx.compose.animation.core.Animatable(if (member != null) 0.55f else 1f) }
    LaunchedEffect(Unit) {
        drop.animateTo(1f, androidx.compose.animation.core.spring(dampingRatio = 0.45f, stiffness = 420f))
    }
    val breathe = rememberInfiniteTransition(label = "tokenRing")
    val ring by breathe.animateFloat(
        0f, 1f, infiniteRepeatable(tween(1400, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "tokenRingT",
    )
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val press by androidx.compose.animation.core.animateFloatAsState(
        if (pressed) 0.9f else 1f,
        androidx.compose.animation.core.spring(dampingRatio = 0.5f, stiffness = 900f),
        label = "tokenPress",
    )

    Column(
        modifier = Modifier
            .offset(x = x - 60.dp, y = y - token / 2)
            .width(120.dp)
            .graphicsLayer { translationX = shake * density }
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            modifier = Modifier
                .size(token)
                .graphicsLayer { val s = drop.value * press; scaleX = s; scaleY = s },
            contentAlignment = Alignment.Center,
        ) {
            if (active) {
                // A ring that swells out and fades, over and over — "this one, now".
                Box(
                    Modifier
                        .matchParentSize()
                        .graphicsLayer { val s = 1f + 0.38f * ring; scaleX = s; scaleY = s; alpha = (1f - ring) * 0.9f }
                        .border(3.dp, Color(0xFF93B4FF), CircleShape),
                )
            }
            if (member != null) {
                Box(
                    Modifier
                        .matchParentSize()
                        .shadow(8.dp, CircleShape)
                        .clip(CircleShape)
                        .background(Color.White)
                        .padding(3.dp),
                ) { PlayerAvatar(member, token - 6.dp) }
                if (active) Box(Modifier.matchParentSize().border(2.5.dp, TossBlue, CircleShape))
            } else {
                Box(
                    Modifier
                        .matchParentSize()
                        .clip(CircleShape)
                        .background(Color(0x2EFFFFFF))
                        .drawBehind {
                            drawCircle(
                                Color.White.copy(alpha = if (active) 1f else 0.75f),
                                radius = size.minDimension / 2 - 1.dp.toPx(),
                                style = androidx.compose.ui.graphics.drawscope.Stroke(
                                    width = 2.dp.toPx(),
                                    pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(5.dp.toPx(), 4.dp.toPx())),
                                ),
                            )
                        },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Add, contentDescription = null, tint = Color.White, modifier = Modifier.size(22.dp))
                }
            }
            if (withBall) {
                // The new ball, in the bowler's hand.
                androidx.compose.foundation.Canvas(Modifier.size(14.dp).align(Alignment.TopStart).offset((-2).dp, 2.dp)) {
                    drawCircle(Color(0xFFB3261E))
                    drawCircle(Color(0x66FFFFFF), radius = size.minDimension * 0.18f, center = center - androidx.compose.ui.geometry.Offset(size.width * 0.16f, size.height * 0.16f))
                    drawLine(Color(0xCCFFF1E0), center - androidx.compose.ui.geometry.Offset(0f, size.height / 2.4f), center + androidx.compose.ui.geometry.Offset(0f, size.height / 2.4f), 1.2.dp.toPx())
                }
            }
        }
        Spacer(Modifier.height(5.dp))
        Column(
            modifier = Modifier
                .shadow(3.dp, RoundedCornerShape(10.dp))
                .clip(RoundedCornerShape(10.dp))
                .background(if (active) TossBlue else Color.White)
                .padding(horizontal = 9.dp, vertical = 4.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                role.uppercase(),
                color = if (active) Color.White.copy(alpha = 0.8f) else TossText3,
                fontSize = 8.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp, maxLines = 1,
            )
            Text(
                member?.name ?: stringResource(com.haraan.app.R.string.toss_not_picked),
                color = if (active) Color.White else if (member != null) TossText1 else TossText3,
                fontSize = 12.sp, fontWeight = FontWeight.Bold, maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            )
        }
    }
}

/** A squad row: face, name, the truths we hold about them, and which end they're at. */
@Composable
private fun PlayerRow(
    member: SquadMember,
    roleTag: String?,
    chosenHere: Boolean,
    onClick: () -> Unit,
) {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val bg by androidx.compose.animation.animateColorAsState(
        if (pressed) Color(0xFFF1F4F9) else Color.Transparent, tween(90), label = "rowPress",
    )
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(bg)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        PlayerAvatar(member, 38.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    member.name, color = TossText1, fontSize = 15.sp,
                    fontWeight = if (chosenHere) FontWeight.Bold else FontWeight.SemiBold,
                    maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (member.isVerified) {
                    Spacer(Modifier.width(4.dp))
                    Icon(Icons.Filled.Verified, contentDescription = null, tint = TossBlue, modifier = Modifier.size(15.dp))
                }
                if (member.isCaptain || member.isViceCaptain) {
                    Spacer(Modifier.width(6.dp))
                    Text(
                        if (member.isCaptain) "C" else "VC",
                        color = TossText2, fontSize = 10.sp, fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .border(1.dp, TossStroke, RoundedCornerShape(5.dp))
                            .padding(horizontal = 5.dp, vertical = 1.dp),
                    )
                }
            }
            if (member.isGuest) {
                Text(stringResource(com.haraan.app.R.string.toss_guest), color = TossText3, fontSize = 12.sp)
            }
        }
        if (roleTag != null) {
            Spacer(Modifier.width(8.dp))
            Text(
                roleTag,
                color = if (chosenHere) TossBlue else TossText3,
                fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.width(10.dp))
        // The radio: filled blue when this player holds the slot being picked.
        Box(
            modifier = Modifier
                .size(22.dp)
                .clip(CircleShape)
                .border(if (chosenHere) 6.5.dp else 1.5.dp, if (chosenHere) TossBlue else Color(0xFFCBD2DD), CircleShape),
        )
    }
}

/** Their photo when the squad entry is a real account; otherwise a calm monogram. */
@Composable
private fun PlayerAvatar(member: SquadMember, size: androidx.compose.ui.unit.Dp) {
    if (member.avatar.isNotBlank()) {
        coil.compose.AsyncImage(
            model = member.avatar,
            contentDescription = member.name,
            contentScale = androidx.compose.ui.layout.ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape).background(Color(0xFFE9EDF3)),
        )
    } else {
        Box(
            modifier = Modifier.size(size).clip(CircleShape).background(Color(0xFFE9EEF7)),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                member.name.trim().split(Regex("\\s+")).take(2)
                    .mapNotNull { it.firstOrNull()?.uppercaseChar() }.joinToString(""),
                color = Color(0xFF3B4A63), fontSize = (size.value / 2.9f).sp, fontWeight = FontWeight.Bold,
            )
        }
    }
}

/** The screen's one button: blue and pressable when armed, quiet grey naming what's missing when not. */
@Composable
private fun TossCta(
    label: String,
    armed: Boolean,
    icon: ImageVector? = null,
    trailingIcon: ImageVector? = null,
    onClick: () -> Unit,
) {
    val bg by androidx.compose.animation.animateColorAsState(
        if (armed) TossBlue else Color(0xFFE6EAF1), tween(220), label = "ctaBg",
    )
    val fg by androidx.compose.animation.animateColorAsState(
        if (armed) Color.White else TossText3, tween(220), label = "ctaFg",
    )
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by androidx.compose.animation.core.animateFloatAsState(
        if (pressed) 0.97f else 1f,
        androidx.compose.animation.core.spring(dampingRatio = 0.55f, stiffness = 800f),
        label = "ctaPress",
    )
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 14.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .height(56.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (armed && icon != null) {
                Icon(icon, contentDescription = null, tint = fg, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(6.dp))
            }
            Text(label, color = fg, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            if (armed && trailingIcon != null) {
                Spacer(Modifier.width(8.dp))
                Icon(trailingIcon, contentDescription = null, tint = fg, modifier = Modifier.size(18.dp))
            }
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
