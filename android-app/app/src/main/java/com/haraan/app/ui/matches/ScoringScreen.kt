package com.haraan.app.ui.matches

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Translate
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
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import com.haraan.app.R
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import android.widget.Toast
import com.haraan.app.data.MatchRepository
import com.haraan.app.data.SquadMember
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import org.json.JSONObject
import kotlin.math.roundToInt

// ── palette ──
//
// Light, matching the rest of the app. The scorer used to be the one dark screen in a
// light product — a scorer stands in daylight at a ground, and the dark slab was both
// harder to read there and visibly a different app from the board it feeds.
private val ScDark = Color(0xFFF4F7FB)      // page
private val ScPanel = Color(0xFFFFFFFF)     // raised strips, dialogs
private val ScLine = Color(0xFFE2E8F0)      // hairlines and key borders
private val ScInk = Color(0xFF0F172A)       // primary text
private val ScInk2 = Color(0xFF64748B)      // secondary text
private val ScTeal = Color(0xFF2563EB)      // on-strike / bowler accent (brand blue)
private val ScOlive = Color(0xFF15803D)     // innings-state green, legible on white
private val ScKey = Color(0xFFEEF2F7)       // keypad bed
private val ScKeyText = Color(0xFF0F172A)
private val ScRed = Color(0xFFDC2626)
// A four and a six were both drawn olive - the two most exciting outcomes in the game,
// rendered identically. They now take the board's own ink, so the colour a scorer taps
// is the colour a viewer sees land.
private val ScFour = Color(0xFF2563EB)
private val ScSix = Color(0xFFD97706)

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
    val thisOver: List<String>,
    /** Both sides' crests, so the header can show whoever is batting. */
    val team1Logo: String = "",
    val team2Logo: String = "",
    val team1Code: String = "",
    val team2Code: String = "",
    val team1Name: String = "",
    val team2Name: String = "",
    /**
     * Which side batted FIRST (1 or 2), straight from the server's `battingTeam` at seed.
     * Everything that swaps at the innings break keys off this rather than assuming team 1
     * opened — plenty of matches are won at the toss by the side that fields.
     */
    val battedFirst: Int = 1,
    /** Where and when — shown under the toss line so the scorer can confirm the fixture. */
    val venue: String = "",
    val startLabel: String = "",
    val startIsScheduled: Boolean = false,
    // Names of batters already dismissed this innings — they can't be sent back in.
    val dismissed: Set<String> = emptySet()
)

private fun oversText(balls: Int) = "${balls / 6}.${balls % 6}"

/**
 * The squad entry behind a name on the board.
 *
 * A name join, which is normally the wrong tool — but the crease names came OUT of this
 * exact list (the pickers write them), so it is the same join the scorer already made by
 * hand, not a guess across two datasets. Returns null for a free-hand or placeholder name
 * ("New Batter"), and every caller degrades to a monogram.
 */
private fun memberFor(squad: List<SquadMember>, name: String): SquadMember? {
    val key = name.trim()
    if (key.isEmpty()) return null
    return squad.firstOrNull { it.name.trim().equals(key, ignoreCase = true) }
}

/**
 * A crease name that fits beside a 58dp portrait.
 *
 * Two cells share the width, so a full "Sandeep Varma" was ellipsing to "Sandeep Var…" —
 * which is neither the player's name nor a recognised short form. Cricket already has a
 * convention for this, so use it: keep the given name and initialise the surname. Never
 * touches the stored name, only what is drawn.
 */
private fun creaseName(full: String): String {
    val n = full.trim()
    if (n.length <= 12) return n
    val parts = n.split(Regex("\\s+")).filter { it.isNotBlank() }
    // A single long name has nothing to initialise - let it ellipsis rather than inventing.
    if (parts.size < 2) return n
    return parts[0] + " " + parts[1].take(1).uppercase() + "."
}

/** The blue tick, wherever a verified player is named. Matches the profile screen's. */
@Composable
private fun VerifiedTick(size: Dp = 14.dp) {
    Icon(
        Icons.Default.Verified,
        contentDescription = "Verified",
        tint = ScTeal,
        modifier = Modifier.size(size),
    )
}

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
        val token = com.haraan.app.data.TokenStore.getToken(ctx)
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

    // Every scoring action needs a REAL session. A guest holds the non-blank
    // "skipped_guest" token, so the old `getToken(ctx) ?: return@launch` let them
    // through to a 401 that surfaced as "check connection". Fail loudly instead.
    val scoringToken: () -> String? = {
        TokenStore.getSignedInToken(ctx).also {
            if (it == null) {
                Toast.makeText(ctx, "Please sign in to score this match.", Toast.LENGTH_SHORT).show()
            }
        }
    }

    ScorerLoaded(
        seed = remember(matchId) { seedFrom(data) },
        matchId = matchId,
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
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "start")
                        .put("innings", 2)
                        .put("batting_team", secondBattingTeam)
                        .put("striker_id", playerRef(secondBattingSquad.firstOrNull { it.name == strikerName }) ?: strikerName.ifBlank { "Batter 1" })
                        .put("non_striker_id", playerRef(secondBattingSquad.firstOrNull { it.name == nonStrikerName }) ?: nonStrikerName.ifBlank { "Batter 2" })
                        .put("bowler_id", playerRef(secondBowlingSquad.firstOrNull { it.name == bowlerName }) ?: bowlerName.ifBlank { "Bowler" })
                    val sent0 = repo.sendScoreAction(token, matchId, payload)
                    if (!sent0.ok) {
                        Toast.makeText(ctx, sent0.refusal ?: "Couldn't start 2nd innings — check connection.", Toast.LENGTH_LONG).show()
                    }
                }
            }
        },
        onEvent = { event, after ->
            scope.launch {
                val token = scoringToken() ?: return@launch
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
                        val sent1 = repo.sendScoreAction(token, matchId, start)
                        if (!sent1.ok) {
                            Toast.makeText(ctx, sent1.refusal ?: "Couldn't start scoring. Check connection.", Toast.LENGTH_LONG).show()
                            return@withLock
                        }
                        started.value = true
                    }
                    val action = scoreActionFor(event, after, battingSquad) ?: return@withLock
                    val sent2 = repo.sendScoreAction(token, matchId, action)
                    if (!sent2.ok) {
                        Toast.makeText(ctx, sent2.refusal ?: "Score didn't save — check connection.", Toast.LENGTH_LONG).show()
                    }
                }
            }
        },
        onBowlerChange = { member ->
            // End of over → a new bowler must come on; this also rolls the over server-side.
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "change_bowler")
                        .put("bowler_id", playerRef(member) ?: "Bowler")
                    val sent3 = repo.sendScoreAction(token, matchId, payload)
                    if (!sent3.ok) {
                        Toast.makeText(ctx, sent3.refusal ?: "Bowler change didn't save.", Toast.LENGTH_LONG).show()
                    }
                }
            }
        },
        onChangeBatsman = { role, member ->
            // Only meaningful once the innings has started; before the first ball the swap
            // is carried into the lazily-sent 'start' payload, so nothing to persist yet.
            if (started.value) {
                scope.launch {
                    val token = scoringToken() ?: return@launch
                    persistLock.withLock {
                        val payload = JSONObject()
                            .put("type", "change_batsman")
                            .put("role", role)
                            .put("id", playerRef(member) ?: member.name)
                        val sent4 = repo.sendScoreAction(token, matchId, payload)
                        if (!sent4.ok) {
                            Toast.makeText(ctx, sent4.refusal ?: "Batter change didn't save.", Toast.LENGTH_LONG).show()
                        }
                    }
                }
            }
        },
        onWicket = { newBatsman, dismissal ->
            // Wicket → persist with the chosen incoming batsman + how the batter was out.
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "wicket")
                        .put("new_batsman_id", playerRef(newBatsman) ?: "")
                        .put("dismissal", dismissal)
                    val sent5 = repo.sendScoreAction(token, matchId, payload)
                    if (!sent5.ok) {
                        Toast.makeText(ctx, sent5.refusal ?: "Wicket didn't save — check connection.", Toast.LENGTH_LONG).show()
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
    // On resume, carry forward who's already out this innings so they can't bat again.
    val dismissed = d.inningsCards.lastOrNull()?.batters
        ?.filter { it.out && it.name.isNotBlank() }
        ?.map { it.name }?.toSet() ?: emptySet()
    return ScorerState(
        title = d.team1FullName.ifBlank { d.team1 },
        // NOT d.status - that field carries a score string on this endpoint ("41/2"),
        // which rendered as a stale second scoreline directly under the live one.
        toss = d.toss.ifBlank { "${d.team1} elected to bat." },
        runs = parts.getOrNull(0)?.toIntOrNull() ?: 0,
        wickets = parts.getOrNull(1)?.toIntOrNull()?.coerceAtMost(10) ?: 0,
        balls = legalBalls,
        maxOvers = maxOvers,
        striker = ScorerBatter(d.striker.ifBlank { "Batter 1" }, d.strikerStats?.runs ?: 0, d.strikerStats?.balls ?: 0),
        nonStriker = ScorerBatter(d.nonStriker.ifBlank { "Batter 2" }, d.nonStrikerStats?.runs ?: 0, d.nonStrikerStats?.balls ?: 0),
        bowler = ScorerBowler(d.bowler.ifBlank { "Bowler" }, d.bowlerStats?.balls ?: 0, d.bowlerStats?.runs ?: 0, d.bowlerStats?.wickets ?: 0),
        thisOver = d.thisOver,
        team1Logo = d.team1Logo,
        team2Logo = d.team2Logo,
        team1Code = d.team1,
        team2Code = d.team2,
        team1Name = d.team1FullName.ifBlank { d.team1 },
        team2Name = d.team2FullName.ifBlank { d.team2 },
        battedFirst = d.battingTeam.takeIf { it == 1 || it == 2 } ?: 1,
        venue = d.venue,
        startLabel = d.startLabel,
        startIsScheduled = d.startIsScheduled,
        dismissed = dismissed
    )
}

@Composable
private fun ScorerLoaded(
    seed: ScorerState,
    matchId: String = "",
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
    var showLanguage by remember { mutableStateOf(false) }
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
    // "All out" depends on how many batters the side actually has — a 7-a-side gully team
    // is all out at 6 wickets, not 10. Fall back to 10 when no squad was entered (guests).
    val allOutWickets = activeBattingSquad.size.takeIf { it >= 2 }?.let { (it - 1).coerceIn(1, 10) } ?: 10
    // Innings is done once the over quota is bowled, the side is all out, or the chase is won.
    val inningsOver = state.balls >= state.maxOvers * 6 || state.wickets >= allOutWickets || chaseWon
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
        val outName = state.striker.name
        val willBeAllOut = state.wickets + 1 >= allOutWickets
        // When the side is all out there's no incoming batter; keep the crease as-is.
        val newName = if (willBeAllOut) outName else (newBatsman?.name?.takeIf { it.isNotBlank() } ?: "New Batter")
        var next = state.copy(
            wickets = (state.wickets + 1).coerceAtMost(allOutWickets),
            balls = state.balls + 1,
            bowler = state.bowler.copy(balls = state.bowler.balls + 1, wickets = state.bowler.wickets + 1),
            striker = ScorerBatter(newName, 0, 0),
            thisOver = state.thisOver + "W",
            dismissed = if (outName.isNotBlank()) state.dismissed + outName else state.dismissed
        )
        if (next.balls > 0 && next.balls % 6 == 0) {
            next = next.copy(striker = next.nonStriker, nonStriker = next.striker, thisOver = emptyList())
        }
        state = next
        onWicket(newBatsman, pendingDismissal)
        pickBatsman = false
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= allOutWickets
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
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= allOutWickets
        if (next.balls > before && next.balls % 6 == 0 && !nextOver) {
            if (activeBowlingSquad.isNotEmpty()) pickBowler = true else onBowlerChange(null)
        }
    }

    if (pickDismissal) {
        DismissalPicker(
            onPick = { type ->
                pendingDismissal = type
                pickDismissal = false
                // Last wicket → no new batter to pick; close the innings straight away.
                val willBeAllOut = state.wickets + 1 >= allOutWickets
                if (!willBeAllOut && activeBattingSquad.isNotEmpty()) pickBatsman = true else finishWicket(null)
            }
        )
    }

    if (pickBatsman) {
        BatsmanPicker(
            squad = activeBattingSquad,
            atCrease = setOf(state.striker.name, state.nonStriker.name),
            dismissed = state.dismissed,
            onPick = { member -> finishWicket(member) }
        )
    }

    if (pickChangeBatsman) {
        BatsmanPicker(
            squad = activeBattingSquad,
            atCrease = setOf(state.striker.name, state.nonStriker.name),
            dismissed = state.dismissed,
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

    // Language switch, the same dialog the match-detail header opens - the scorer is one
    // of only two screens localised so far, so it belongs here more than most.
    if (showLanguage) {
        com.haraan.app.ui.LanguageDialog(onDismiss = { showLanguage = false })
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
            // Whoever is batting NOW. This used to be pinned to team 1's name for the whole
            // match, so the scorer's header still named the side that had already finished
            // batting once the chase began.
            val battingSide = if (currentInnings >= 2) 3 - state.battedFirst else state.battedFirst
            val battingName = if (battingSide == 2) state.team2Name else state.team1Name
            Text(
                battingName.ifBlank { state.title }, color = ScInk, fontSize = 17.sp,
                fontWeight = FontWeight.Bold, textAlign = TextAlign.Center,
                maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f)
            )
            // Both of these were dead: ScCircleIcon defaults onClick to {}, so the two
            // controls in the scorer's header had never done anything at all.
            ScCircleIcon(Icons.Outlined.Share, "Share") {
                // A link to WATCH, not to score. This is what a scorer sends to the group
                // so people who aren't at the ground can follow the innings.
                val url = "https://haraan.app/gamehub/actionboard/match/$matchId"
                val text = "${state.title} — ${state.runs}/${state.wickets} (${oversText(state.balls)})" +
                    "\nWatch live on Haraan: $url"
                val send = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
                    type = "text/plain"
                    putExtra(android.content.Intent.EXTRA_TEXT, text)
                }
                ctx.startActivity(android.content.Intent.createChooser(send, "Share match"))
            }
            Spacer(Modifier.width(10.dp))
            ScCircleIcon(Icons.Outlined.Translate, stringResource(R.string.language)) {
                showLanguage = true
            }
        }

        // Hero score.
        //
        // Left-anchored and DENSE. Centred, it was a lonely number floating in air with an
        // unlabelled second line under it; a scorer wants the state of the innings in one
        // glance - what the score is, how far in, how fast, and what is being chased.
        Row(
            modifier = Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 10.dp, bottom = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
        Column(modifier = Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    "${state.runs}/${state.wickets}", color = ScInk, fontSize = 46.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
                Text(
                    "  ${oversText(state.balls)}", color = ScInk, fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(bottom = 7.dp),
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
                Text(
                    "/${state.maxOvers} ov", color = ScInk2, fontSize = 14.sp,
                    modifier = Modifier.padding(bottom = 8.dp),
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )

            }

            Spacer(Modifier.height(6.dp))

            // Every figure below is derived from the innings on screen - nothing asserted.
            val crr = if (state.balls > 0) state.runs * 6.0 / state.balls else 0.0
            val ballsLeft = (state.maxOvers * 6 - state.balls).coerceAtLeast(0)
            val target = firstInningsTotal?.let { it + 1 }

            Row(verticalAlignment = Alignment.CenterVertically) {
                StatChip("CRR", String.format(java.util.Locale.US, "%.2f", crr))
                Spacer(Modifier.width(8.dp))
                when {
                    currentInnings >= 2 && target != null && !chaseWon && !inningsOver -> {
                        val need = (target - state.runs).coerceAtLeast(0)
                        StatChip("NEED", "$need off $ballsLeft", accent = ScTeal)
                        Spacer(Modifier.width(8.dp))
                        val rrr = if (ballsLeft > 0) need * 6.0 / ballsLeft else 0.0
                        StatChip("RRR", String.format(java.util.Locale.US, "%.2f", rrr), accent = ScTeal)
                    }
                    else -> StatChip("BALLS LEFT", "$ballsLeft")
                }
            }

            val statusLine = when {
                chaseWon -> "Target chased · won by ${(allOutWickets - state.wickets).coerceAtLeast(0)} wickets" to ScOlive
                canStartSecondInnings -> "1st innings complete · ${state.runs}/${state.wickets}" to ScOlive
                inningsOver -> stringResource(R.string.innings_complete_fmt, state.maxOvers) to ScOlive
                else -> state.toss to ScInk2
            }
            if (statusLine.first.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(
                    statusLine.first, color = statusLine.second, fontSize = 12.5.sp,
                    fontWeight = if (statusLine.second == ScOlive) FontWeight.Bold else FontWeight.Medium
                )
            }

            // Ground and start time. A scorer arriving at a phone left on the bench needs to
            // confirm they are on the right fixture before they touch a key; the team name
            // alone does not settle that when a side plays twice in a day.
            val fixture = listOfNotNull(
                state.venue.takeIf { it.isNotBlank() },
                state.startLabel.takeIf { it.isNotBlank() }
            ).joinToString("  ·  ")
            if (fixture.isNotBlank()) {
                Spacer(Modifier.height(5.dp))
                Text(
                    fixture, color = ScInk2, fontSize = 11.5.sp, fontWeight = FontWeight.Medium,
                    maxLines = 2,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                )
            }
        }

            Spacer(Modifier.width(14.dp))

            // The batting side's crest. This corner was empty, and the block it sits in is
            // the one a scorer screenshots into a team group — a crest makes that crop
            // identifiably THEIR match rather than a generic scoreline.
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                val side = if (currentInnings >= 2) 3 - state.battedFirst else state.battedFirst
                val battingLogo = if (side == 2) state.team2Logo else state.team1Logo
                val battingCode = if (side == 2) state.team2Code else state.team1Code
                TeamLogo(
                    team = battingCode,
                    logoUrl = battingLogo,
                    modifier = Modifier.size(58.dp)
                )
                Spacer(Modifier.height(7.dp))
                // The maker's mark sits UNDER the team's, which is the correct order of
                // billing on a scoreboard: whose match it is first, whose tool second.
                Image(
                    painter = painterResource(id = R.drawable.haraan_wordmark),
                    contentDescription = "Haraan",
                    contentScale = androidx.compose.ui.layout.ContentScale.Fit,
                    colorFilter = ColorFilter.tint(ScInk2.copy(alpha = 0.7f)),
                    modifier = Modifier.height(14.dp),
                )
            }
        }

        Box(Modifier.fillMaxWidth().height(1.dp).background(ScLine))

        // Batsmen — tap a name to change that batter (a confirmation is asked first).
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 16.dp)) {
            BatterCell(
                Modifier.weight(1f), state.striker, onStrike = true,
                onClick = if (activeBattingSquad.isNotEmpty())
                    { { confirmChangeRole = "striker" } } else null,
                member = memberFor(activeBattingSquad, state.striker.name)
            )
            Box(Modifier.width(1.dp).height(36.dp).background(ScLine))
            BatterCell(
                Modifier.weight(1f), state.nonStriker, onStrike = false, alignStart = false,
                onClick = if (activeBattingSquad.isNotEmpty())
                    { { confirmChangeRole = "nonStriker" } } else null,
                member = memberFor(activeBattingSquad, state.nonStriker.name)
            )
        }

        // Bowler + this over (panel)
        Column(
            Modifier
                .fillMaxWidth()
                .background(ScPanel)
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                val bowlerMember = memberFor(activeBowlingSquad, state.bowler.name)
                ScorerFace(state.bowler.name, bowlerMember?.avatar.orEmpty(), ScTeal, size = 52.dp)
                Spacer(Modifier.width(10.dp))
                Text(state.bowler.name, color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                if (bowlerMember?.isVerified == true) {
                    Spacer(Modifier.width(5.dp))
                    VerifiedTick()
                }
                Spacer(Modifier.weight(1f))
                Text(
                    "${oversText(state.bowler.balls)}-0-${state.bowler.runs}-${state.bowler.wickets}",
                    color = ScInk2, fontSize = 13.sp
                )
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "THIS OVER", color = ScInk2, fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp
                )
                Spacer(Modifier.weight(1f))
                // Runs conceded off the over so far, read straight off the tokens on screen.
                // A wide and a no-ball each cost one; a wicket costs nothing.
                val offOver = state.thisOver.sumOf { t ->
                    when (t.trim().uppercase()) {
                        "W" -> 0
                        "WD", "NB" -> 1
                        else -> t.trim().toIntOrNull() ?: 0
                    }
                }
                Text(
                    "$offOver ${if (offOver == 1) "run" else "runs"}",
                    color = ScInk, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
            }

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                val recent = state.thisOver.takeLast(6)
                recent.forEachIndexed { i, token ->
                    BallBubble(token, newest = i == recent.lastIndex)
                }
            }
        }

        // The keypad TAKES the remaining height rather than being pushed down by a Spacer.
        // That spacer left a fifth of the screen as dead air above a row of thin keys - the
        // surest sign of a layout that has not decided what it is for. The keys now grow
        // into whatever the device gives them.
        if (canStartSecondInnings) {
            Spacer(Modifier.weight(1f))
            StartSecondInningsButton(onClick = ::startSecondInnings)
        } else {
            Keypad(onKey = ::apply, modifier = Modifier.weight(1f))
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
            Icon(Icons.Outlined.SportsCricket, null, tint = ScInk, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.start_second_innings), color = ScInk, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
    }
}

/**
 * One derived figure. A label and its value on a single line inside a quiet pill.
 *
 * Deliberately NOT a boxed "stat card" with a big number stacked over a caption - four of
 * those in a row is the house style of dashboards that have nothing to say, and it would
 * cost the vertical space the keypad now uses.
 */
@Composable
private fun StatChip(label: String, value: String, accent: Color = ScInk) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(7.dp))
            .background(if (accent == ScInk) Color(0xFFE8EDF4) else accent.copy(alpha = 0.12f))
            .padding(horizontal = 9.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, color = ScInk2, fontSize = 9.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.8.sp)
        Spacer(Modifier.width(6.dp))
        Text(
            value, color = accent, fontSize = 12.5.sp, fontWeight = FontWeight.Bold,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

@Composable
private fun BatterCell(
    modifier: Modifier,
    b: ScorerBatter,
    onStrike: Boolean,
    alignStart: Boolean = true,
    onClick: (() -> Unit)? = null,
    member: SquadMember? = null,
) {
    val accent = if (onStrike) ScTeal else ScInk2
    Row(
        modifier = modifier
            .then(if (onClick != null) Modifier.clip(RoundedCornerShape(10.dp)).clickable(onClick = onClick) else Modifier)
            .padding(horizontal = 6.dp, vertical = 4.dp),
        horizontalArrangement = if (alignStart) Arrangement.Start else Arrangement.End,
        verticalAlignment = Alignment.CenterVertically
    ) {
        // The player, not a bat glyph. Both ends of the crease used to carry the identical
        // icon, so telling them apart meant reading the names.
        ScorerFace(b.name, member?.avatar.orEmpty(), accent, size = 58.dp)
        Spacer(Modifier.width(12.dp))
        Column {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                Text(
                    creaseName(b.name),
                    color = if (onStrike) ScTeal else ScInk,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                )
                if (member?.isVerified == true) VerifiedTick()
                // A batter who hasn't faced a ball can be swapped — show a tap affordance.
                if (onClick != null) Icon(Icons.Outlined.Edit, "Change batter", tint = ScInk2, modifier = Modifier.size(13.dp))
            }
            Spacer(Modifier.height(2.dp))
            Text("${b.runs}(${b.balls})", color = ScInk2, fontSize = 13.sp)
        }
    }
}

@Composable
private fun BallBubble(token: String, newest: Boolean = false) {
    val (bg, fg) = when (token) {
        "6" -> ScSix to ScInk
        "4" -> ScFour to Color.White
        "W" -> ScRed to Color.White
        "0", "•" -> ScLine to ScInk2
        else -> Color.White to ScInk
    }

    // The ball just entered lands rather than appears: it punches in slightly oversized and
    // settles. It is the on-screen half of the key's knock - the scorer sees the delivery
    // reach the strip without having to re-read the whole over.
    val scale = remember(token, newest) { androidx.compose.animation.core.Animatable(if (newest) 0.55f else 1f) }
    LaunchedEffect(newest, token) {
        if (newest) scale.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = 700f))
    }

    Box(
        modifier = Modifier
            .size(44.dp)
            .graphicsLayer { scaleX = scale.value; scaleY = scale.value }
            .clip(CircleShape)
            .background(bg),
        contentAlignment = Alignment.Center
    ) {
        Text(
            token, color = fg, fontSize = 15.sp, fontWeight = FontWeight.Bold,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/**
 * What each key MEANS, which is what decides how it looks and how it feels.
 *
 * The keypad used to be sixteen identical white rectangles that differed only in their
 * label: a six and a dot ball were drawn the same way, so the most thrilling event in
 * cricket looked exactly like the most routine one. A scorer taps this well over a hundred
 * times a match, usually while watching the game rather than the screen — so the classes
 * are separated by COLOUR, by WEIGHT, and by the number of knocks they return, and a
 * thumb can find "runs" without reading a single label.
 */
private enum class KeyKind { DOT, RUN, FOUR, SIX, EXTRA, UNDO, OUT }

private fun KeyKind.thud(): Thud = when (this) {
    KeyKind.DOT -> Thud.TICK
    KeyKind.RUN -> Thud.RUN
    KeyKind.FOUR -> Thud.FOUR
    KeyKind.SIX -> Thud.SIX
    KeyKind.EXTRA -> Thud.EXTRA
    KeyKind.UNDO -> Thud.UNDO
    KeyKind.OUT -> Thud.WICKET
}

/** Ink for each class. Four and six take the SAME blue and amber the board uses. */
private fun KeyKind.accent(): Color = when (this) {
    KeyKind.DOT -> ScInk2
    KeyKind.RUN -> ScInk
    KeyKind.FOUR -> ScFour
    KeyKind.SIX -> ScSix
    KeyKind.EXTRA -> ScInk2
    KeyKind.UNDO -> ScTeal
    KeyKind.OUT -> ScRed
}

@Composable
private fun Keypad(onKey: (String) -> Unit, modifier: Modifier = Modifier) {
    // A dark instrument panel rather than a white slab. The board above it is dark, and the
    // old light keypad read as a form stapled to the bottom of a scoreboard; unified, the
    // coloured keys carry real luminance instead of being tints on white.
    Column(
        modifier
            .fillMaxWidth()
            .background(Brush.verticalGradient(listOf(Color(0xFFF8FAFC), ScKey)))
            // Clear of the system navigation. OUT sat directly against the gesture bar and
            // the hardware button row, which on a key that ends a batter's innings is the
            // difference between scoring a wicket and leaving the screen.
            .navigationBarsPadding()
            .padding(start = 8.dp, end = 8.dp, top = 8.dp, bottom = 10.dp)
    ) {
        // Runs carry the most weight, extras the least - the rows are proportioned by how
        // often a thumb lands on them over an innings, not split evenly.
        KeyRow(Modifier.weight(1.1f)) {
            Key("0", KeyKind.DOT, Modifier.weight(1f), onKey)
            Key("1", KeyKind.RUN, Modifier.weight(1f), onKey)
            Key("2", KeyKind.RUN, Modifier.weight(1f), onKey)
            Key("3", KeyKind.RUN, Modifier.weight(1f), onKey)
        }
        Spacer(Modifier.height(8.dp))
        KeyRow(Modifier.weight(1.1f)) {
            Key("4", KeyKind.FOUR, Modifier.weight(1f), onKey, sub = "FOUR")
            Key("6", KeyKind.SIX, Modifier.weight(1f), onKey, sub = "SIX")
            Key("5,7", KeyKind.RUN, Modifier.weight(1f), onKey, value = "5")
            Key("UNDO", KeyKind.UNDO, Modifier.weight(1f), onKey)
        }
        Spacer(Modifier.height(8.dp))
        KeyRow(Modifier.weight(0.8f)) {
            Key("WD", KeyKind.EXTRA, Modifier.weight(1f), onKey)
            Key("NB", KeyKind.EXTRA, Modifier.weight(1f), onKey)
            Key("BYE", KeyKind.EXTRA, Modifier.weight(1f), onKey)
            Key("LB", KeyKind.EXTRA, Modifier.weight(1f), onKey)
        }
        Spacer(Modifier.height(8.dp))
        KeyRow(Modifier.weight(0.86f)) {
            Key(stringResource(R.string.out), KeyKind.OUT, Modifier.weight(1f), onKey, value = "OUT")
        }
    }
}

@Composable
private fun KeyRow(modifier: Modifier = Modifier, content: @Composable RowScope.() -> Unit) {
    Row(
        modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        content = content
    )
}

@Composable
private fun Key(
    label: String,
    kind: KeyKind,
    modifier: Modifier,
    onKey: (String) -> Unit,
    sub: String? = null,
    value: String = label,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()

    val accent = kind.accent()
    val tinted = kind == KeyKind.FOUR || kind == KeyKind.SIX || kind == KeyKind.OUT || kind == KeyKind.UNDO

    // The key gives under the thumb and springs back. On a screen used at arm's length,
    // half-watched, this is what confirms the tap actually landed — a ripple alone is easy
    // to miss and impossible to feel.
    val scale by animateFloatAsState(
        targetValue = if (pressed) 0.94f else 1f,
        animationSpec = spring(dampingRatio = 0.42f, stiffness = 900f),
        label = "keyScale"
    )
    // OUT is SOLID, not a pale wash. It ends a batter's innings; a tinted outline made the
    // single most consequential key on the pad the faintest thing on screen.
    val solid = kind == KeyKind.OUT
    val bg by animateColorAsState(
        targetValue = when {
            solid && pressed -> Color(0xFFB91C1C)
            solid -> accent
            pressed && tinted -> accent.copy(alpha = 0.42f)
            pressed -> Color(0xFFE2E8F0)
            tinted -> accent.copy(alpha = 0.20f)
            kind == KeyKind.EXTRA -> Color(0xFFE9EEF5)
            else -> ScPanel
        },
        label = "keyBg"
    )

    Column(
        modifier = modifier
            .fillMaxHeight()
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(14.dp))
            .background(bg)
            .border(
                if (tinted && !solid) 1.5.dp else 1.dp,
                when {
                    solid -> Color.Transparent
                    tinted -> accent.copy(alpha = 0.75f)
                    // A hairline the same value as the gap between keys made the pad read as
                    // a wireframe. This is dark enough for each key to be an object.
                    else -> Color(0xFFCBD5E1)
                },
                RoundedCornerShape(14.dp)
            )
            .clickable(interactionSource = interaction, indication = null) {
                // Fire the feel FIRST, then the scoring. The vibration is what the thumb is
                // waiting on, and it must not queue behind a network write.
                scope.launch { cricketThud(context, kind.thud()) }
                onKey(value)
            },
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            label,
            color = if (solid) Color.White else accent,
            // Runs get the display face at scoreboard scale; extras stay deliberately
            // smaller, so the two classes never compete for the same glance.
            fontSize = when (kind) {
                KeyKind.EXTRA -> 15.sp
                KeyKind.OUT -> 18.sp
                KeyKind.UNDO -> 14.sp
                else -> if (sub == null) 30.sp else 28.sp
            },
            fontFamily = if (kind == KeyKind.EXTRA || kind == KeyKind.UNDO || kind == KeyKind.OUT)
                null else com.haraan.app.theme.ArchivoDisplay,
            fontWeight = if (kind == KeyKind.EXTRA || kind == KeyKind.UNDO || kind == KeyKind.OUT)
                FontWeight.Bold else FontWeight.Normal,
            letterSpacing = if (kind == KeyKind.OUT) 2.sp else 0.sp,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
        if (sub != null) {
            Spacer(Modifier.height(1.dp))
            Text(
                sub,
                color = accent.copy(alpha = 0.9f),
                fontSize = 9.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.2.sp
            )
        }
    }
}

/**
 * A player's face in a picker row.
 *
 * Every row used to carry the same generic bat icon, so choosing a batter was reading a
 * list of strings — the one moment in scoring where you are picking a PERSON looked like
 * picking a value from a dropdown. Their real photo when the squad is linked to an
 * account; otherwise their initials struck in the row's accent.
 *
 * The monogram is not a placeholder to be embarrassed about: grassroots squads are
 * overwhelmingly typed in free-hand and carry no account at all, so this is the normal
 * case and has to look deliberate rather than like a failed image load.
 */
@Composable
private fun ScorerFace(name: String, photoUrl: String, accent: Color, size: Dp = 38.dp) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(CircleShape)
            .background(accent.copy(alpha = 0.14f))
            // The ring scales with the circle. A 1dp hairline that reads fine at 34dp
            // disappears at 58dp and the face starts looking like a flat sticker.
            .border(if (size >= 48.dp) 2.dp else 1.5.dp, accent.copy(alpha = 0.55f), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        if (photoUrl.isNotBlank()) {
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = name,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        } else {
            Text(
                scorerInitials(name),
                color = accent,
                fontSize = (size.value * 0.34f).sp,
                fontWeight = FontWeight.ExtraBold
            )
        }
    }
}

/** "Suresh Pillai" -> "SP"; a single name falls back to its first two letters. */
private fun scorerInitials(name: String): String {
    val parts = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
    return when {
        parts.isEmpty() -> "?"
        parts.size == 1 -> parts[0].take(2).uppercase()
        else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
    }
}

@Composable
private fun ScCircleIcon(icon: ImageVector, desc: String, onClick: () -> Unit = {}) {
    Box(
        modifier = Modifier.size(40.dp).clip(CircleShape).background(ScPanel).clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(icon, contentDescription = desc, tint = ScInk, modifier = Modifier.size(20.dp))
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
            Text("Replace $batterName?", color = ScInk, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Text(
                if (hasFaced)
                    "This batter has already faced balls — replacing them starts the new batter at 0(0)."
                else
                    "Pick another player to bat in this spot.",
                color = ScInk2, fontSize = 13.sp, lineHeight = 18.sp
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
                    Text("Cancel", color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
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
                    Text("Change", color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.Bold)
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
            Text("How was the batter out?", color = ScInk, fontSize = 17.sp, fontWeight = FontWeight.Bold)
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
                        Text(label, color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
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
    dismissed: Set<String> = emptySet(),
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
            Text(headline ?: stringResource(R.string.select_new_batsman), color = ScInk, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            // Drop anyone already at the crease (the not-out batter stays on) and anyone
            // already dismissed this innings — a batter who is out can't come back in.
            val options = squad.filter { it.name !in atCrease && it.name !in dismissed }
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
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        ScorerFace(member.name, member.avatar, ScOlive)
                        Spacer(Modifier.width(12.dp))
                        Text(member.name, color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                        if (member.isVerified) {
                            Spacer(Modifier.width(5.dp))
                            VerifiedTick()
                        }
                        if (member.isCaptain || member.isViceCaptain) {
                            Spacer(Modifier.width(8.dp))
                            Text(
                                if (member.isCaptain) "C" else "VC",
                                color = ScOlive, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold
                            )
                        }
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
            Text(stringResource(if (opening) R.string.select_opening_bowler else R.string.select_next_bowler), color = ScInk, fontSize = 17.sp, fontWeight = FontWeight.Bold)
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
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        ScorerFace(member.name, member.avatar, ScTeal)
                        Spacer(Modifier.width(12.dp))
                        Text(member.name, color = ScInk, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                        if (member.isVerified) {
                            Spacer(Modifier.width(5.dp))
                            VerifiedTick()
                        }
                        if (member.isCaptain || member.isViceCaptain) {
                            Spacer(Modifier.width(8.dp))
                            Text(
                                if (member.isCaptain) "C" else "VC",
                                color = ScTeal, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold
                            )
                        }
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
