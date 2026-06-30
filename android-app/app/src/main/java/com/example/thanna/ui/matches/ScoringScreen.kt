package com.example.thanna.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.SportsBaseball
import androidx.compose.material.icons.outlined.SportsCricket
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import com.example.thanna.R
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import android.widget.Toast
import com.example.thanna.data.MatchRepository
import com.example.thanna.data.SquadMember
import com.example.thanna.data.TokenStore
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import org.json.JSONObject
import kotlin.math.roundToInt

// ── palette ──
private val ScDark = Color(0xFF15181B)
private val ScPanel = Color(0xFF1E2226)
private val ScTeal = Color(0xFF2DD4BF)
private val ScOlive = Color(0xFFAEC53B)
private val ScKey = Color(0xFFF4F5F6)
private val ScKeyText = Color(0xFF1E2226)
private val ScRed = Color(0xFFE5484D)

private data class ScorerBatter(val name: String, val runs: Int, val balls: Int)
private data class ScorerBowler(val name: String, val balls: Int, val runs: Int, val wickets: Int)
private data class ScorerState(
    val title: String,
    val toss: String,
    val runs: Int,
    val wickets: Int,
    val balls: Int,
    val maxOvers: Int,
    val striker: ScorerBatter,
    val nonStriker: ScorerBatter,
    val bowler: ScorerBowler,
    val thisOver: List<String>
)

private fun oversText(balls: Int) = "${balls / 6}.${balls % 6}"

@Composable
fun ScoringScreen(
    matchId: String,
    code: String = "",
    onBack: () -> Unit = {},
    viewModel: MatchDetailsViewModel = viewModel()
) {
    val ctx = LocalContext.current
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(matchId, code) {
        val token = com.example.thanna.data.TokenStore.getToken(ctx)
        viewModel.load(id = matchId, code = code, token = token)
    }

    val data = (uiState as? MatchScreenState.Success)?.data
    if (data == null) {
        Box(Modifier.fillMaxSize().background(ScDark), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = ScTeal)
        }
        return
    }

    // ── Persistence ── every keypad press is written to the backend so the score
    // actually updates (and shows on the match detail / feed). The innings is started
    // lazily on the first action, with striker/non-striker/bowler taken from the squads.
    val scope = rememberCoroutineScope()
    val repo = remember { MatchRepository() }
    val persistLock = remember { Mutex() }
    val started = remember { mutableStateOf(data.isLive) }

    val battingSquad = if (data.battingTeam == 2) data.awaySquad else data.homeSquad
    val bowlingSquad = if (data.battingTeam == 2) data.homeSquad else data.awaySquad

    // For the chase, batting and bowling sides swap. The button that starts the 2nd
    // innings only appears during the 1st, so `data.battingTeam` here is the first
    // innings' batting side — the other team bats second.
    val secondBattingTeam = if (data.battingTeam == 2) 1 else 2
    val secondBattingSquad = if (secondBattingTeam == 2) data.awaySquad else data.homeSquad
    val secondBowlingSquad = if (secondBattingTeam == 2) data.homeSquad else data.awaySquad

    // When resuming mid-chase, the 1st-innings total (for the target) comes from the data:
    // the first innings card, else the opponent's score line.
    val initialFirstInningsTotal: Int? = if (data.innings >= 2) {
        data.inningsCards.firstOrNull()?.runs
            ?: data.opponentScore.substringBefore("/").trim().toIntOrNull()
    } else null

    ScorerLoaded(
        seed = remember(matchId) { seedFrom(data) },
        onBack = onBack,
        alreadyStarted = data.isLive,
        initialInnings = data.innings,
        initialFirstInningsTotal = initialFirstInningsTotal,
        battingSquad = battingSquad,
        bowlingSquad = bowlingSquad,
        secondBattingSquad = secondBattingSquad,
        secondBowlingSquad = secondBowlingSquad,
        onStartSecondInnings = { strikerName, nonStrikerName, bowlerName ->
            scope.launch {
                val token = TokenStore.getToken(ctx) ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "start")
                        .put("innings", 2)
                        .put("batting_team", secondBattingTeam)
                        .put("striker_id", playerRef(secondBattingSquad.firstOrNull { it.name == strikerName }) ?: strikerName.ifBlank { "Batter 1" })
                        .put("non_striker_id", playerRef(secondBattingSquad.firstOrNull { it.name == nonStrikerName }) ?: nonStrikerName.ifBlank { "Batter 2" })
                        .put("bowler_id", playerRef(secondBowlingSquad.firstOrNull { it.name == bowlerName }) ?: bowlerName.ifBlank { "Bowler" })
                    if (repo.sendScoreAction(token, matchId, payload) == null) {
                        Toast.makeText(ctx, "Couldn't start 2nd innings — check connection.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        },
        onEvent = { event, after ->
            scope.launch {
                val token = TokenStore.getToken(ctx) ?: return@launch
                persistLock.withLock {
                    // Lazily start the innings before the first ball.
                    if (!started.value && event != "UNDO") {
                        // The opening bowler is the one the scorer picked before the first
                        // ball (carried on `after.bowler`); fall back to the squad lead.
                        val openingBowler = bowlingSquad.firstOrNull { it.name == after.bowler.name }
                            ?: bowlingSquad.getOrNull(0)
                        // Openers honour any pre-first-ball batter swaps (resolved from the
                        // current crease names), falling back to the squad order.
                        val openStriker = battingSquad.firstOrNull { it.name == after.striker.name } ?: battingSquad.getOrNull(0)
                        val openNonStriker = battingSquad.firstOrNull { it.name == after.nonStriker.name } ?: battingSquad.getOrNull(1)
                        val start = JSONObject()
                            .put("type", "start")
                            .put("batting_team", data.battingTeam)
                            .put("striker_id", playerRef(openStriker) ?: after.striker.name.ifBlank { "Batter 1" })
                            .put("non_striker_id", playerRef(openNonStriker) ?: after.nonStriker.name.ifBlank { "Batter 2" })
                            .put("bowler_id", playerRef(openingBowler) ?: after.bowler.name.ifBlank { "Bowler" })
                        if (repo.sendScoreAction(token, matchId, start) == null) {
                            Toast.makeText(ctx, "Couldn't start scoring. Check connection.", Toast.LENGTH_SHORT).show()
                            return@withLock
                        }
                        started.value = true
                    }
                    val action = scoreActionFor(event, after, battingSquad) ?: return@withLock
                    if (repo.sendScoreAction(token, matchId, action) == null) {
                        Toast.makeText(ctx, "Score didn't save — check connection.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        },
        onBowlerChange = { member ->
            // End of over → a new bowler must come on; this also rolls the over server-side.
            scope.launch {
                val token = TokenStore.getToken(ctx) ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "change_bowler")
                        .put("bowler_id", playerRef(member) ?: "Bowler")
                    if (repo.sendScoreAction(token, matchId, payload) == null) {
                        Toast.makeText(ctx, "Bowler change didn't save.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        },
        onChangeBatsman = { role, member ->
            // Only meaningful once the innings has started; before the first ball the swap
            // is carried into the lazily-sent 'start' payload, so nothing to persist yet.
            if (started.value) {
                scope.launch {
                    val token = TokenStore.getToken(ctx) ?: return@launch
                    persistLock.withLock {
                        val payload = JSONObject()
                            .put("type", "change_batsman")
                            .put("role", role)
                            .put("id", playerRef(member) ?: member.name)
                        if (repo.sendScoreAction(token, matchId, payload) == null) {
                            Toast.makeText(ctx, "Batter change didn't save.", Toast.LENGTH_SHORT).show()
                        }
                    }
                }
            }
        },
        onWicket = { newBatsman, dismissal ->
            // Wicket → persist with the chosen incoming batsman + how the batter was out.
            scope.launch {
                val token = TokenStore.getToken(ctx) ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "wicket")
                        .put("new_batsman_id", playerRef(newBatsman) ?: "")
                        .put("dismissal", dismissal)
                    if (repo.sendScoreAction(token, matchId, payload) == null) {
                        Toast.makeText(ctx, "Wicket didn't save — check connection.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        }
    )
}

/** A player's backend reference — registered id when present, otherwise the name (guests). */
private fun playerRef(member: SquadMember?): String? {
    if (member == null) return null
    val id = member.id.takeIf { it.isNotBlank() && !it.equals("null", true) }
    return (id ?: member.name).takeIf { it.isNotBlank() && !it.equals("null", true) }
}

/** Map a keypad event to the backend score-action payload. */
private fun scoreActionFor(event: String, after: ScorerState, battingSquad: List<SquadMember>): JSONObject? =
    when (event) {
        "0", "1", "2", "3", "4", "5", "6" -> JSONObject().put("type", "runs").put("value", event.toInt())
        "WD" -> JSONObject().put("type", "wide").put("value", 1)
        "NB" -> JSONObject().put("type", "noball").put("runs_off_bat", 0)
        "BYE" -> JSONObject().put("type", "bye").put("value", 1)
        "LB" -> JSONObject().put("type", "legbye").put("value", 1)
        "OUT" -> JSONObject().put("type", "wicket")
            .put("new_batsman_id", playerRef(battingSquad.getOrNull(after.wickets + 1)) ?: "")
        "UNDO" -> JSONObject().put("type", "undo")
        else -> null
    }

private fun seedFrom(d: MatchUiState): ScorerState {
    val ov = d.overs.toFloatOrNull() ?: 0f
    val legalBalls = ov.toInt() * 6 + ((ov - ov.toInt()) * 10).roundToInt()
    val parts = d.score.split("/")
    // Over quota comes from the match format ("20 Over Match"); default to 20.
    val maxOvers = Regex("(\\d+)").find(d.competition)?.value?.toIntOrNull()?.takeIf { it > 0 } ?: 20
    return ScorerState(
        title = d.team1FullName.ifBlank { d.team1 },
        toss = d.status.ifBlank { "${d.team1} elected to bat." },
        runs = parts.getOrNull(0)?.toIntOrNull() ?: 0,
        wickets = parts.getOrNull(1)?.toIntOrNull()?.coerceAtMost(10) ?: 0,
        balls = legalBalls,
        maxOvers = maxOvers,
        striker = ScorerBatter(d.striker.ifBlank { "Batter 1" }, d.strikerStats?.runs ?: 0, d.strikerStats?.balls ?: 0),
        nonStriker = ScorerBatter(d.nonStriker.ifBlank { "Batter 2" }, d.nonStrikerStats?.runs ?: 0, d.nonStrikerStats?.balls ?: 0),
        bowler = ScorerBowler(d.bowler.ifBlank { "Bowler" }, d.bowlerStats?.balls ?: 0, d.bowlerStats?.runs ?: 0, d.bowlerStats?.wickets ?: 0),
        thisOver = d.thisOver
    )
}

@Composable
private fun ScorerLoaded(
    seed: ScorerState,
    onBack: () -> Unit,
    alreadyStarted: Boolean = false,
    initialInnings: Int = 1,
    initialFirstInningsTotal: Int? = null,
    battingSquad: List<SquadMember> = emptyList(),
    bowlingSquad: List<SquadMember> = emptyList(),
    secondBattingSquad: List<SquadMember> = emptyList(),
    secondBowlingSquad: List<SquadMember> = emptyList(),
    onEvent: (event: String, after: ScorerState) -> Unit = { _, _ -> },
    onBowlerChange: (SquadMember?) -> Unit = {},
    onWicket: (newBatsman: SquadMember?, dismissal: String) -> Unit = { _, _ -> },
    onStartSecondInnings: (striker: String, nonStriker: String, bowler: String) -> Unit = { _, _, _ -> },
    onChangeBatsman: (role: String, member: SquadMember) -> Unit = { _, _ -> },
) {
    val ctx = LocalContext.current
    var state by remember { mutableStateOf(seed) }
    var history by remember { mutableStateOf(listOf<ScorerState>()) }
    var pickBatsman by remember { mutableStateOf(false) }
    // Wicket flow: first pick HOW the batter was out, then who comes in.
    var pickDismissal by remember { mutableStateOf(false) }
    var pendingDismissal by remember { mutableStateOf("bowled") }
    // Swap a batter who hasn't faced a ball (wrong batter picked).
    var pickChangeBatsman by remember { mutableStateOf(false) }
    var changeRole by remember { mutableStateOf("striker") }
    // Tapping a batter's name asks for confirmation before opening the picker; holds the
    // role ("striker"/"nonStriker") pending confirmation, or null when nothing is pending.
    var confirmChangeRole by remember { mutableStateOf<String?>(null) }
    // A bowler must be on before any ball: at the over-end the next bowler is forced, and
    // for a fresh innings the opening bowler is forced before the first delivery.
    var pickBowler by remember { mutableStateOf(false) }
    var pickingOpening by remember { mutableStateOf(false) }

    // Innings tracking. `transitioned` = the user started the 2nd innings in THIS session,
    // which is when batting/bowling sides swap to the second squads.
    var currentInnings by remember { mutableStateOf(initialInnings.coerceAtLeast(1)) }
    var transitioned by remember { mutableStateOf(false) }
    var firstInningsTotal by remember { mutableStateOf(initialFirstInningsTotal) }
    var pendingSecondStart by remember { mutableStateOf(false) }

    val activeBattingSquad = if (transitioned) secondBattingSquad else battingSquad
    val activeBowlingSquad = if (transitioned) secondBowlingSquad else bowlingSquad

    // Have we already locked in the opening bowler? (Resuming a live innings counts as yes.)
    var openingBowlerSet by remember { mutableStateOf(alreadyStarted || bowlingSquad.isEmpty()) }

    // Force the opening-bowler chooser the moment a fresh innings is opened.
    LaunchedEffect(Unit) {
        if (!openingBowlerSet && bowlingSquad.isNotEmpty()) {
            pickingOpening = true
            pickBowler = true
        }
    }

    // In the chase, the match is won the instant the target is passed.
    val chaseWon = currentInnings >= 2 && firstInningsTotal != null && state.runs > firstInningsTotal!!
    // Innings is done once the over quota is bowled, the side is all out, or the chase is won.
    val inningsOver = state.balls >= state.maxOvers * 6 || state.wickets >= 10 || chaseWon
    // After the 1st innings closes (but not a won chase), the scorer rolls into the chase.
    val canStartSecondInnings = inningsOver && currentInnings < 2

    fun startSecondInnings() {
        firstInningsTotal = state.runs
        val s = secondBattingSquad.getOrNull(0)?.name?.takeIf { it.isNotBlank() } ?: "Batter 1"
        val ns = secondBattingSquad.getOrNull(1)?.name?.takeIf { it.isNotBlank() } ?: "Batter 2"
        state = state.copy(
            runs = 0, wickets = 0, balls = 0,
            striker = ScorerBatter(s, 0, 0),
            nonStriker = ScorerBatter(ns, 0, 0),
            bowler = ScorerBowler("Bowler", 0, 0, 0),
            thisOver = emptyList()
        )
        history = emptyList()
        currentInnings = 2
        transitioned = true
        // Force the opening bowler for the chase; the 'start' is sent once he's chosen.
        openingBowlerSet = false
        pendingSecondStart = true
        if (secondBowlingSquad.isNotEmpty()) {
            pickingOpening = true
            pickBowler = true
        } else {
            // No squad to pick from — start immediately with a placeholder bowler.
            openingBowlerSet = true
            pendingSecondStart = false
            onStartSecondInnings(s, ns, "Bowler")
        }
    }

    // Apply the wicket once the incoming batsman is chosen, then roll the over if it ended.
    fun finishWicket(newBatsman: SquadMember?) {
        history = history + state
        val before = state.balls
        val newName = newBatsman?.name?.takeIf { it.isNotBlank() } ?: "New Batter"
        var next = state.copy(
            wickets = (state.wickets + 1).coerceAtMost(10),
            balls = state.balls + 1,
            bowler = state.bowler.copy(balls = state.bowler.balls + 1, wickets = state.bowler.wickets + 1),
            striker = ScorerBatter(newName, 0, 0),
            thisOver = state.thisOver + "W"
        )
        if (next.balls > 0 && next.balls % 6 == 0) {
            next = next.copy(striker = next.nonStriker, nonStriker = next.striker, thisOver = emptyList())
        }
        state = next
        onWicket(newBatsman, pendingDismissal)
        pickBatsman = false
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= 10
        if (next.balls > before && next.balls % 6 == 0 && !nextOver) {
            if (activeBowlingSquad.isNotEmpty()) pickBowler = true else onBowlerChange(null)
        }
    }

    fun apply(ev: String) {
        if (ev == "UNDO") {
            history.lastOrNull()?.let { state = it; history = history.dropLast(1) }
            onEvent("UNDO", state)
            return
        }
        // Block scoring once the innings is complete (over quota / all out / chase won).
        if (inningsOver) {
            val msg = if (chaseWon) "Match won — target chased." else "Innings complete — ${state.maxOvers} overs."
            Toast.makeText(ctx, msg, Toast.LENGTH_SHORT).show()
            return
        }
        // No ball can be scored until a bowler is chosen for a fresh innings.
        if (!openingBowlerSet) {
            pickingOpening = true
            pickBowler = true
            Toast.makeText(ctx, "Select the opening bowler first.", Toast.LENGTH_SHORT).show()
            return
        }
        if (ev == "OUT") {
            // Ask HOW out first; the new-batsman step follows.
            pickDismissal = true
            return
        }
        history = history + state
        val before = state.balls
        val next = reduce(state, ev)
        state = next
        onEvent(ev, next)
        // A legal delivery just completed the over → bring on a new bowler (and roll the
        // over). Skip the prompt when that ball also ended the innings.
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= 10
        if (next.balls > before && next.balls % 6 == 0 && !nextOver) {
            if (activeBowlingSquad.isNotEmpty()) pickBowler = true else onBowlerChange(null)
        }
    }

    if (pickDismissal) {
        DismissalPicker(
            onPick = { type ->
                pendingDismissal = type
                pickDismissal = false
                if (activeBattingSquad.isNotEmpty()) pickBatsman = true else finishWicket(null)
            }
        )
    }

    if (pickBatsman) {
        BatsmanPicker(
            squad = activeBattingSquad,
            atCrease = setOf(state.striker.name, state.nonStriker.name),
            onPick = { member -> finishWicket(member) }
        )
    }

    if (pickChangeBatsman) {
        BatsmanPicker(
            squad = activeBattingSquad,
            atCrease = setOf(state.striker.name, state.nonStriker.name),
            tag = "CHANGE BATTER", headline = "Replace this batter", tagColor = ScTeal,
            dismissable = true, onDismiss = { pickChangeBatsman = false },
            onPick = { member ->
                if (changeRole == "striker") state = state.copy(striker = ScorerBatter(member.name, 0, 0))
                else state = state.copy(nonStriker = ScorerBatter(member.name, 0, 0))
                onChangeBatsman(changeRole, member)
                pickChangeBatsman = false
            }
        )
    }

    // Second confirmation before swapping a batter that was tapped by name.
    confirmChangeRole?.let { role ->
        val current = if (role == "striker") state.striker else state.nonStriker
        ChangeBatterConfirm(
            batterName = current.name,
            hasFaced = current.balls > 0,
            onConfirm = {
                changeRole = role
                pickChangeBatsman = true
                confirmChangeRole = null
            },
            onDismiss = { confirmChangeRole = null },
        )
    }

    if (pickBowler) {
        BowlerPicker(
            squad = activeBowlingSquad,
            currentName = state.bowler.name,
            opening = pickingOpening,
            onPick = { member ->
                state = state.copy(bowler = ScorerBowler(member.name, 0, 0, 0))
                if (pickingOpening) {
                    // Opening bowler locked in.
                    openingBowlerSet = true
                    pickingOpening = false
                    if (pendingSecondStart) {
                        // 2nd innings: now that the opening bowler is chosen, persist the
                        // innings 'start' so the backend swaps the batting side.
                        pendingSecondStart = false
                        onStartSecondInnings(state.striker.name, state.nonStriker.name, member.name)
                    }
                    // Otherwise (1st innings) the 'start' is sent lazily on the first ball.
                } else {
                    onBowlerChange(member)
                }
                pickBowler = false
            }
        )
    }

    Column(Modifier.fillMaxSize().background(ScDark)) {
        // Top bar
        Row(
            modifier = Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            ScCircleIcon(Icons.AutoMirrored.Filled.ArrowBack, "Back", onClick = onBack)
            Text(state.title, color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
            ScCircleIcon(Icons.Outlined.Share, "Share")
            Spacer(Modifier.width(10.dp))
            ScCircleIcon(Icons.Outlined.Settings, "Settings")
        }

        // Hero score
        Column(
            modifier = Modifier.fillMaxWidth().padding(top = 18.dp, bottom = 20.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    "${state.runs}/${state.wickets}", color = Color.White, fontSize = 42.sp,
                    fontFamily = com.example.thanna.theme.ArchivoDisplay,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
                Text(
                    "  (${oversText(state.balls)}/${state.maxOvers})", color = Color(0xFF9BA3AB), fontSize = 16.sp,
                    modifier = Modifier.padding(bottom = 6.dp),
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
            }
            Spacer(Modifier.height(8.dp))
            val target = firstInningsTotal?.let { it + 1 }
            when {
                chaseWon -> Text(
                    "Target chased · won by ${(10 - state.wickets).coerceAtLeast(0)} wickets",
                    color = ScOlive, fontSize = 13.sp, fontWeight = FontWeight.Bold
                )
                canStartSecondInnings -> Text(
                    "1st innings complete · ${state.runs}/${state.wickets}",
                    color = ScOlive, fontSize = 13.sp, fontWeight = FontWeight.SemiBold
                )
                inningsOver -> Text(
                    stringResource(R.string.innings_complete_fmt, state.maxOvers),
                    color = ScOlive, fontSize = 13.sp, fontWeight = FontWeight.SemiBold
                )
                currentInnings >= 2 && target != null -> Text(
                    "Chasing $target · need ${(target - state.runs).coerceAtLeast(0)} off ${(state.maxOvers * 6 - state.balls).coerceAtLeast(0)}",
                    color = ScTeal, fontSize = 13.sp, fontWeight = FontWeight.SemiBold
                )
                else -> Text(state.toss, color = Color(0xFFB5BCC3), fontSize = 13.sp)
            }
        }

        Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFF2A2F34)))

        // Batsmen — tap a name to change that batter (a confirmation is asked first).
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp)) {
            BatterCell(
                Modifier.weight(1f), state.striker, onStrike = true,
                onClick = if (activeBattingSquad.isNotEmpty())
                    { { confirmChangeRole = "striker" } } else null
            )
            Box(Modifier.width(1.dp).height(36.dp).background(Color(0xFF2A2F34)))
            BatterCell(
                Modifier.weight(1f), state.nonStriker, onStrike = false, alignStart = false,
                onClick = if (activeBattingSquad.isNotEmpty())
                    { { confirmChangeRole = "nonStriker" } } else null
            )
        }

        // Bowler + this over (panel)
        Column(Modifier.fillMaxWidth().background(ScPanel).padding(horizontal = 16.dp, vertical = 14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                // Bowler is represented by a ball (the bat is for batters).
                Icon(Icons.Outlined.SportsBaseball, null, tint = ScTeal, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(state.bowler.name, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.weight(1f))
                Text(
                    "${oversText(state.bowler.balls)}-0-${state.bowler.runs}-${state.bowler.wickets}",
                    color = Color(0xFF9BA3AB), fontSize = 13.sp
                )
            }
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                state.thisOver.takeLast(6).forEach { BallBubble(it) }
            }
        }

        Spacer(Modifier.weight(1f))

        // When the 1st innings closes, the keypad gives way to the chase-start CTA.
        if (canStartSecondInnings) {
            StartSecondInningsButton(onClick = ::startSecondInnings)
        } else {
            Keypad(onKey = ::apply)
        }
    }
}

@Composable
private fun StartSecondInningsButton(onClick: () -> Unit) {
    Box(
        Modifier
            .fillMaxWidth()
            .background(ScKey)
            .navigationBarsPadding()
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(ScTeal)
                .clickable(onClick = onClick)
                .padding(vertical = 16.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(Icons.Outlined.SportsCricket, null, tint = Color(0xFF06302B), modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.start_second_innings), color = Color(0xFF06302B), fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun BatterCell(modifier: Modifier, b: ScorerBatter, onStrike: Boolean, alignStart: Boolean = true, onClick: (() -> Unit)? = null) {
    Row(
        modifier = modifier
            .then(if (onClick != null) Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onClick) else Modifier)
            .padding(horizontal = 6.dp, vertical = 2.dp),
        horizontalArrangement = if (alignStart) Arrangement.Start else Arrangement.End,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Outlined.SportsCricket, null, tint = if (onStrike) ScTeal else Color(0xFF9BA3AB), modifier = Modifier.size(16.dp))
                Text(b.name, color = if (onStrike) ScTeal else Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                // A batter who hasn't faced a ball can be swapped — show a tap affordance.
                if (onClick != null) Icon(Icons.Outlined.Edit, "Change batter", tint = Color(0xFF6B7280), modifier = Modifier.size(13.dp))
            }
            Spacer(Modifier.height(2.dp))
            Text("${b.runs}(${b.balls})", color = Color(0xFFB5BCC3), fontSize = 13.sp, modifier = Modifier.padding(start = 24.dp))
        }
    }
}

@Composable
private fun BallBubble(token: String) {
    val (bg, fg) = when (token) {
        "6", "4" -> ScOlive to Color(0xFF1E2226)
        "W" -> ScRed to Color.White
        else -> Color.White to Color(0xFF1E2226)
    }
    Box(
        modifier = Modifier.size(44.dp).clip(CircleShape).background(bg),
        contentAlignment = Alignment.Center
    ) {
        Text(token, color = fg, fontSize = 15.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun Keypad(onKey: (String) -> Unit) {
    Column(Modifier.fillMaxWidth().background(ScKey).padding(2.dp)) {
        KeyRow {
            Key("0", Modifier.weight(1f), onKey)
            Key("1", Modifier.weight(1f), onKey)
            Key("2", Modifier.weight(1f), onKey)
            Key("UNDO", Modifier.weight(1f), onKey, textColor = ScTeal)
        }
        KeyRow {
            Key("3", Modifier.weight(1f), onKey)
            Key("4", Modifier.weight(1f), onKey, sub = "FOUR")
            Key("6", Modifier.weight(1f), onKey, sub = "SIX")
            Key("5,7", Modifier.weight(1f), onKey, value = "5")
        }
        KeyRow {
            Key("WD", Modifier.weight(1f), onKey)
            Key("NB", Modifier.weight(1f), onKey)
            Key("BYE", Modifier.weight(1f), onKey)
            Key("LB", Modifier.weight(1f), onKey)
        }
        KeyRow {
            Key(stringResource(R.string.out), Modifier.weight(1f), onKey, value = "OUT", textColor = ScRed)
        }
        Box(
            Modifier.fillMaxWidth().background(Color(0xFFE6E8EA)).padding(vertical = 8.dp),
            contentAlignment = Alignment.Center
        ) {
            Text("Scoring Shortcuts  ⌃", color = Color(0xFF8A9097), fontSize = 12.sp)
        }
    }
}

@Composable
private fun KeyRow(content: @Composable RowScope.() -> Unit) {
    Row(Modifier.fillMaxWidth().height(66.dp).padding(2.dp), horizontalArrangement = Arrangement.spacedBy(4.dp), content = content)
}

@Composable
private fun Key(
    label: String,
    modifier: Modifier,
    onKey: (String) -> Unit,
    sub: String? = null,
    value: String = label,
    textColor: Color = ScKeyText
) {
    Column(
        modifier = modifier
            .fillMaxHeight()
            .clip(RoundedCornerShape(8.dp))
            .background(Color.White)
            .border(1.dp, Color(0xFFE6E8EA), RoundedCornerShape(8.dp))
            .clickable { onKey(value) },
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(label, color = textColor, fontSize = if (sub == null) 18.sp else 17.sp, fontWeight = FontWeight.SemiBold)
        if (sub != null) Text(sub, color = Color(0xFF8A9097), fontSize = 10.sp, fontWeight = FontWeight.Medium)
    }
}

@Composable
private fun ScCircleIcon(icon: ImageVector, desc: String, onClick: () -> Unit = {}) {
    Box(
        modifier = Modifier.size(40.dp).clip(CircleShape).background(ScPanel).clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(icon, contentDescription = desc, tint = Color.White, modifier = Modifier.size(20.dp))
    }
}

// Tapped a batter's name → confirm before opening the replace-batter picker. Guards an
// accidental tap, and warns when the batter has already faced balls (their score resets).
@Composable
private fun ChangeBatterConfirm(
    batterName: String,
    hasFaced: Boolean,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
) {
    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.clip(RoundedCornerShape(18.dp)).background(ScPanel).padding(20.dp)
        ) {
            Text("CHANGE BATTER", color = ScTeal, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text("Replace $batterName?", color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Text(
                if (hasFaced)
                    "This batter has already faced balls — replacing them starts the new batter at 0(0)."
                else
                    "Pick another player to bat in this spot.",
                color = Color(0xFFB5BCC3), fontSize = 13.sp, lineHeight = 18.sp
            )
            Spacer(Modifier.height(18.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(ScDark)
                        .clickable(onClick = onDismiss)
                        .padding(vertical = 14.dp),
                    horizontalArrangement = Arrangement.Center
                ) {
                    Text("Cancel", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                }
                Row(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(ScTeal)
                        .clickable(onClick = onConfirm)
                        .padding(vertical = 14.dp),
                    horizontalArrangement = Arrangement.Center
                ) {
                    Text("Change", color = Color(0xFF06302B), fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

// Wicket → how was the batter out? Drives the correct scorecard notation (b / c b / lbw…).
@Composable
private fun DismissalPicker(onPick: (String) -> Unit) {
    val options = listOf(
        "Bowled" to "bowled",
        "Caught" to "caught",
        "LBW" to "lbw",
        "Run out" to "runout",
        "Stumped" to "stumped",
    )
    Dialog(
        onDismissRequest = {},
        properties = DialogProperties(dismissOnBackPress = false, dismissOnClickOutside = false)
    ) {
        Column(
            Modifier.clip(RoundedCornerShape(18.dp)).background(ScPanel).padding(20.dp)
        ) {
            Text("WICKET", color = ScRed, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text("How was the batter out?", color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                options.forEach { (label, value) ->
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(ScDark)
                            .clickable { onPick(value) }
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Outlined.SportsCricket, null, tint = ScRed, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(10.dp))
                        Text(label, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

// Batsman chooser — used both for a wicket (forced) and to swap a not-out batter who
// hasn't faced a ball yet (dismissable).
@Composable
private fun BatsmanPicker(
    squad: List<SquadMember>,
    atCrease: Set<String>,
    onPick: (SquadMember) -> Unit,
    tag: String? = null,
    headline: String? = null,
    tagColor: Color = ScRed,
    dismissable: Boolean = false,
    onDismiss: () -> Unit = {},
) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(dismissOnBackPress = dismissable, dismissOnClickOutside = dismissable)
    ) {
        Column(
            Modifier
                .clip(RoundedCornerShape(18.dp))
                .background(ScPanel)
                .padding(20.dp)
        ) {
            Text(tag ?: stringResource(R.string.wicket), color = tagColor, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(headline ?: stringResource(R.string.select_new_batsman), color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            // Drop anyone already at the crease (the not-out batter stays on).
            val options = squad.filter { it.name !in atCrease }.ifEmpty { squad }
            Column(
                Modifier.heightIn(max = 360.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                options.forEach { member ->
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(ScDark)
                            .clickable { onPick(member) }
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Outlined.SportsCricket, null, tint = ScOlive, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(10.dp))
                        Text(member.name, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

// Over-end bowler chooser. Forced (can't dismiss) so the over always rolls with a bowler.
@Composable
private fun BowlerPicker(squad: List<SquadMember>, currentName: String, opening: Boolean = false, onPick: (SquadMember) -> Unit) {
    Dialog(
        onDismissRequest = {},
        properties = DialogProperties(dismissOnBackPress = false, dismissOnClickOutside = false)
    ) {
        Column(
            Modifier
                .clip(RoundedCornerShape(18.dp))
                .background(ScPanel)
                .padding(20.dp)
        ) {
            Text(stringResource(if (opening) R.string.innings_start else R.string.over_complete), color = ScTeal, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(stringResource(if (opening) R.string.select_opening_bowler else R.string.select_next_bowler), color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            // A bowler can't bowl consecutive overs, so drop the one who just finished — but
            // at the innings start there's no previous bowler, so show the whole squad.
            val options = if (opening) squad else squad.filter { it.name != currentName }.ifEmpty { squad }
            Column(
                Modifier.heightIn(max = 360.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                options.forEach { member ->
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(ScDark)
                            .clickable { onPick(member) }
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Outlined.SportsCricket, null, tint = ScTeal, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(10.dp))
                        Text(member.name, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

// ── scoring reducer ──
private fun reduce(s: ScorerState, ev: String): ScorerState {
    fun rotate(x: ScorerState) = x.copy(striker = x.nonStriker, nonStriker = x.striker)
    fun overEnd(x: ScorerState) =
        if (x.balls > 0 && x.balls % 6 == 0) rotate(x).copy(thisOver = emptyList()) else x

    return when (ev) {
        "0", "1", "2", "3", "4", "5", "6" -> {
            val r = ev.toInt()
            var ns = s.copy(
                runs = s.runs + r,
                balls = s.balls + 1,
                striker = s.striker.copy(runs = s.striker.runs + r, balls = s.striker.balls + 1),
                bowler = s.bowler.copy(balls = s.bowler.balls + 1, runs = s.bowler.runs + r),
                thisOver = s.thisOver + ev
            )
            if (r % 2 == 1) ns = rotate(ns)
            overEnd(ns)
        }
        "WD", "NB" -> s.copy(
            runs = s.runs + 1,
            bowler = s.bowler.copy(runs = s.bowler.runs + 1),
            thisOver = s.thisOver + if (ev == "WD") "Wd" else "Nb"
        )
        "BYE", "LB" -> {
            var ns = s.copy(
                runs = s.runs + 1,
                balls = s.balls + 1,
                striker = s.striker.copy(balls = s.striker.balls + 1),
                bowler = s.bowler.copy(balls = s.bowler.balls + 1),
                thisOver = s.thisOver + if (ev == "BYE") "B" else "Lb"
            )
            ns = rotate(ns)
            overEnd(ns)
        }
        "OUT" -> {
            val ns = s.copy(
                wickets = (s.wickets + 1).coerceAtMost(10),
                balls = s.balls + 1,
                bowler = s.bowler.copy(balls = s.bowler.balls + 1, wickets = s.bowler.wickets + 1),
                striker = ScorerBatter("New Batter", 0, 0),
                thisOver = s.thisOver + "W"
            )
            overEnd(ns)
        }
        else -> s
    }
}
