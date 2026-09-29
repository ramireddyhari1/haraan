package com.haraan.app.ui.matches

import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.material.icons.automirrored.filled.Undo
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Translate
import androidx.compose.material.icons.outlined.Add
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
import androidx.compose.ui.graphics.drawscope.rotate
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

/**
 * A recorded shot: which region, exactly where inside it, and which stroke it was. Any of
 * them may be absent — the scorer can skip either question.
 */
data class ShotPlot(val zone: Int? = null, val x: Float? = null, val y: Float? = null, val stroke: String? = null)

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
    /**
     * Whether this match may record where boundaries went.
     *
     * Reserved for matches created by a VERIFIED account. Shot direction is the one figure
     * on the whole board that cannot be checked against anything else — a scorecard can be
     * argued with, a wagon wheel cannot — so it is only collected where there is a name
     * attached to its accuracy. Everyone else scores exactly as before, with no extra tap.
     */
    val shotPlotting: Boolean = false,
    /** Whether the scorer is also asked WHICH stroke each boundary was (its own plan feature). */
    val shotTypes: Boolean = false,
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

    // Lazily start the innings before the first ball — or before the first BALL signal,
    // which the server refuses while the match isn't live yet. Call inside persistLock.
    suspend fun ensureStarted(token: String, after: ScorerState): Boolean {
        if (started.value) return true
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
            return false
        }
        started.value = true
        return true
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
        onDelivery = { after, cancel ->
            // BALL: the bowler is running in. Viewers animate a delivery until the result
            // lands. Best-effort — a lost signal costs the viewer an animation, never a run,
            // so a failure is not worth a toast that interrupts the scorer mid-over.
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    if (!cancel && !ensureStarted(token, after)) return@withLock
                    repo.sendScoreAction(
                        token, matchId,
                        JSONObject().put("type", if (cancel) "delivery_cancel" else "delivery")
                    )
                }
            }
        },
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
        onEvent = { event, after, plot ->
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    if (event != "UNDO" && !ensureStarted(token, after)) return@withLock
                    val action = scoreActionFor(event, after, battingSquad, plot?.zone, plot?.x, plot?.y, plot?.stroke) ?: return@withLock
                    val sent2 = repo.sendScoreAction(token, matchId, action)
                    if (!sent2.ok) {
                        Toast.makeText(ctx, sent2.refusal ?: "Score didn't save — check connection.", Toast.LENGTH_LONG).show()
                    }
                }
            }
        },
        onBowlerChange = { member, bowlerType ->
            // End of over → a new bowler must come on; this also rolls the over server-side.
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "change_bowler")
                        .put("bowler_id", playerRef(member) ?: "Bowler")
                        // Pace or spin, recorded on the SPELL rather than on the player:
                        // a gully all-rounder bowls both, and a profile field could never
                        // say which one this over was. Written from today so that a
                        // wicket split becomes computable later — it is the one thing
                        // about a delivery that cannot be reconstructed afterwards.
                        .also { if (bowlerType != null) it.put("bowler_type", bowlerType) }
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
        onWicket = { newBatsman, dismissal, fielder ->
            // Wicket → persist with the chosen incoming batsman, how the batter was out,
            // and who made it happen. The fielder is what turns four wickets into four
            // wickets AND the two catches that took them; it is omitted, never guessed,
            // when the dismissal belongs to the bowler alone or the scorer skipped it.
            scope.launch {
                val token = scoringToken() ?: return@launch
                persistLock.withLock {
                    val payload = JSONObject()
                        .put("type", "wicket")
                        .put("new_batsman_id", playerRef(newBatsman) ?: "")
                        .put("dismissal", dismissal)
                    playerRef(fielder)?.let { payload.put("fielder_id", it) }
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
private fun scoreActionFor(
    event: String,
    after: ScorerState,
    battingSquad: List<SquadMember>,
    /**
     * Which of the eight regions the shot went to, 0-7, or null when it was not captured.
     * Absent is the normal case — every ball scored before the picker existed, and every
     * boundary where the scorer tapped Skip. Nothing downstream may assume it is present.
     */
    zone: Int? = null,
    /** Exact landing point, fractions of the ground radius. Null when not captured. */
    shotX: Float? = null,
    shotY: Float? = null,
    /** The stroke the scorer named, a ShotKind key. The server ignores unknown keys. */
    stroke: String? = null,
): JSONObject? =
    when (event) {
        "0", "1", "2", "3", "4", "5", "6" -> JSONObject().put("type", "runs").put("value", event.toInt())
            .also {
                if (zone != null && zone in 0..7) {
                    it.put("zone", zone)
                    if (shotX != null && shotY != null) {
                        // Three places is finer than a thumb on a 268dp circle can express,
                        // and it keeps the ball log small.
                        it.put("x", Math.round(shotX * 1000f) / 1000.0)
                        it.put("y", Math.round(shotY * 1000f) / 1000.0)
                    }
                }
                if (stroke != null && event != "0" && ShotKind.of(stroke) != null) it.put("shot", stroke)
            }
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
        shotPlotting = d.shotPlotting,
        shotTypes = d.shotTypes,
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
    onEvent: (event: String, after: ScorerState, shot: ShotPlot?) -> Unit = { _, _, _ -> },
    /** The bowler coming on, and whether they bowl pace or spin ("pace"/"spin"/null). */
    onBowlerChange: (SquadMember?, String?) -> Unit = { _, _ -> },
    onWicket: (newBatsman: SquadMember?, dismissal: String, fielder: SquadMember?) -> Unit = { _, _, _ -> },
    onStartSecondInnings: (striker: String, nonStriker: String, bowler: String) -> Unit = { _, _, _ -> },
    onChangeBatsman: (role: String, member: SquadMember) -> Unit = { _, _ -> },
    /** BALL tapped (cancel = false) or the ball in play called off (cancel = true). */
    onDelivery: (after: ScorerState, cancel: Boolean) -> Unit = { _, _ -> },
) {
    val ctx = LocalContext.current
    var state by remember { mutableStateOf(seed) }
    var history by remember { mutableStateOf(listOf<ScorerState>()) }
    // Between balls the keypad steps aside for BALL / UNDO. BALL tells viewers the bowler
    // is running in (they get the delivery animation); the keypad then takes the result.
    // Starts true: every ball, the first included, begins with BALL.
    var awaitingBall by remember { mutableStateOf(true) }
    // BALL was tapped and the result isn't in yet — what viewers are watching right now.
    var ballInPlay by remember { mutableStateOf(false) }
    // A paired camera phone is checking in. Only then does REVIEW appear: without a camera
    // there is no clip to review, and a button that always says so is clutter.
    var cameraLive by remember { mutableStateOf(false) }
    var showReview by remember { mutableStateOf(false) }
    var pickBatsman by remember { mutableStateOf(false) }
    // Wicket flow: first pick HOW the batter was out, then who comes in.
    var pickDismissal by remember { mutableStateOf(false) }
    var pendingDismissal by remember { mutableStateOf("bowled") }
    // Who took the catch / ran him out / stumped him. Null for bowled and LBW, and for
    // a scorer who genuinely did not see which fielder it was.
    var pendingFielder by remember { mutableStateOf<SquadMember?>(null) }
    var showDevices by remember { mutableStateOf(false) }
    var showClips by remember { mutableStateOf(false) }
    var pickFielder by remember { mutableStateOf(false) }
    // Swap a batter who hasn't faced a ball (wrong batter picked).
    var pickChangeBatsman by remember { mutableStateOf(false) }
    var changeRole by remember { mutableStateOf("striker") }
    // Tapping a batter's name asks for confirmation before opening the picker; holds the
    // role ("striker"/"nonStriker") pending confirmation, or null when nothing is pending.
    var confirmChangeRole by remember { mutableStateOf<String?>(null) }
    // A bowler must be on before any ball: at the over-end the next bowler is forced, and
    // for a fresh innings the opening bowler is forced before the first delivery.
    var pickBowler by remember { mutableStateOf(false) }
    // Held so the innings 'start' action can carry the opening bowler's type too — that
    // spell is sent lazily on the first ball, long after the picker has closed.
    var pendingBowlerType by remember { mutableStateOf<String?>(null) }
    // Asked ONCE per bowler per match, not once per over. A scorer between overs has a
    // fielding side waiting on them, and a question they have already answered about
    // this bowler is the kind of friction that gets a feature switched off.
    val bowlerTypes = remember { mutableStateMapOf<String, String>() }
    var showLanguage by remember { mutableStateOf(false) }
    // The boundary waiting on a direction. Null when nothing is pending.
    var pendingShot by remember { mutableStateOf<String?>(null) }
    val shotPlotting = seed.shotPlotting
    val shotTypes = seed.shotTypes
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
        awaitingBall = true
        ballInPlay = false
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
        onWicket(newBatsman, pendingDismissal, pendingFielder)
        pickBatsman = false
        ballInPlay = false
        awaitingBall = true
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= allOutWickets
        if (next.balls > before && next.balls % 6 == 0 && !nextOver) {
            if (activeBowlingSquad.isNotEmpty()) pickBowler = true else onBowlerChange(null, null)
        }
    }

    fun apply(ev: String, shot: ShotPlot? = null, asked: Boolean = false) {
        if (ev == "UNDO") {
            history.lastOrNull()?.let { state = it; history = history.dropLast(1) }
            onEvent("UNDO", state, null)
            // Straight to the keypad to re-enter the corrected ball. No BALL first: a
            // correction must not show viewers a delivery that never happened. The undo
            // also ends any ball in play server-side.
            ballInPlay = false
            awaitingBall = false
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
        // A boundary asks where it went, once, before it is scored. `zone` being non-null
        // means that question has already been answered (or skipped with -1), so this does
        // not loop.
        if ((ev == "4" || ev == "6") && shot == null && !asked && (shotPlotting || shotTypes)) {
            pendingShot = ev
            return
        }
        history = history + state
        val before = state.balls
        val next = reduce(state, ev)
        state = next
        onEvent(ev, next, shot)
        ballInPlay = false
        awaitingBall = true
        // A legal delivery just completed the over → bring on a new bowler (and roll the
        // over). Skip the prompt when that ball also ended the innings.
        val nextOver = next.balls >= next.maxOvers * 6 || next.wickets >= allOutWickets
        if (next.balls > before && next.balls % 6 == 0 && !nextOver) {
            if (activeBowlingSquad.isNotEmpty()) pickBowler = true else onBowlerChange(null, null)
        }
    }

    pendingShot?.let { shot ->
        WagonZonePicker(
            shot = shot,
            askZone = shotPlotting,
            askStroke = shotTypes,
            onDone = { plot ->
                pendingShot = null
                // A full skip scores exactly as before, with nothing attached and no second prompt.
                apply(shot, plot, asked = true)
            },
        )
    }

    // Continues a wicket once the fielder question is settled (asked or skipped).
    fun continueWicket() {
        val willBeAllOut = state.wickets + 1 >= allOutWickets
        // Last wicket → no new batter to pick; close the innings straight away.
        if (!willBeAllOut && activeBattingSquad.isNotEmpty()) pickBatsman = true else finishWicket(null)
    }

    // Is a camera paired? Re-asked on a slow cadence, and at once when the devices sheet
    // closes — that is where a camera gets paired or removed.
    LaunchedEffect(matchId, showDevices) {
        if (matchId.isBlank() || showDevices) return@LaunchedEffect
        val deviceRepo = com.haraan.app.data.MatchDeviceRepository()
        while (true) {
            val token = TokenStore.getSignedInToken(ctx)
            if (token != null) {
                cameraLive = runCatching { deviceRepo.devices(token, matchId).any { it.isLive } }
                    .getOrDefault(cameraLive)
            }
            kotlinx.coroutines.delay(20_000)
        }
    }

    if (showReview) {
        LastBallReviewSheet(matchId = matchId, onDismiss = { showReview = false })
    }

    if (showClips) {
        MatchClipsSheet(matchId = matchId, onDismiss = { showClips = false })
    }

    if (showDevices) {
        MatchDevicesSheet(
            matchId = matchId,
            onDismiss = { showDevices = false },
            onOpenClips = { showDevices = false; showClips = true },
        )
    }

    if (pickDismissal) {
        DismissalPicker(
            onPick = { type ->
                pendingDismissal = type
                pendingFielder = null
                pickDismissal = false
                // Only three dismissals have a fielder to name. Asking after a bowled
                // one would be a question with no right answer, and the scorer is
                // standing at the boundary with a game waiting on them.
                val fielded = type == "caught" || type == "runout" || type == "stumped"
                if (fielded && activeBowlingSquad.isNotEmpty()) pickFielder = true else continueWicket()
            }
        )
    }

    if (pickFielder) {
        FielderPicker(
            squad = activeBowlingSquad,
            dismissal = pendingDismissal,
            onPick = { member ->
                pendingFielder = member
                pickFielder = false
                continueWicket()
            },
            onSkip = {
                // Skipping is a real answer in gully cricket — a run-out off a deflection
                // often has no one player to credit. The wicket still stands; only the
                // fielding line goes unclaimed.
                pendingFielder = null
                pickFielder = false
                continueWicket()
            },
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
            knownTypes = bowlerTypes,
            onPick = { member, bowlerType ->
                state = state.copy(bowler = ScorerBowler(member.name, 0, 0, 0))
                pendingBowlerType = bowlerType
                if (bowlerType != null) bowlerTypes[member.name] = bowlerType
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
                    onBowlerChange(member, bowlerType)
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
            // Attach another phone to this match — a camera down the pitch, a camera on
            // the bowler, and whatever the next assisted feature needs. Sits before Share
            // because it is about running THIS match, not about telling people about it.
            ScCircleIcon(Icons.Outlined.Add, "Add match device") { showDevices = true }
            Spacer(Modifier.width(10.dp))
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
        // Left-anchored and DENSE: what the score is, how far in, how fast, and what is
        // being chased — as a scoreboard prints it, figures on one line with no boxes. Four
        // grey pills in a row is the house style of dashboards with nothing to say.
        val crr = if (state.balls > 0) state.runs * 6.0 / state.balls else 0.0
        val ballsLeft = (state.maxOvers * 6 - state.balls).coerceAtLeast(0)
        val target = firstInningsTotal?.let { it + 1 }
        Row(
            modifier = Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 6.dp, bottom = 16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.Bottom) {
                    // The number turns over like a scoreboard plate when a run lands, rather
                    // than silently becoming a different number.
                    RollingFigure(state.runs, fontSize = 48.sp, color = ScInk)
                    Text(
                        "/", color = ScInk.copy(alpha = 0.35f), fontSize = 48.sp,
                        fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    )
                    RollingFigure(state.wickets, fontSize = 48.sp, color = ScInk)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.padding(bottom = 9.dp)) {
                        Text(
                            oversText(state.balls), color = ScInk, fontSize = 19.sp,
                            fontWeight = FontWeight.Bold,
                            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                        )
                        Text(
                            "of ${state.maxOvers} overs", color = ScInk2, fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                        )
                    }
                }

                Spacer(Modifier.height(8.dp))

                // Every figure is derived from the innings on screen — nothing asserted.
                Row(horizontalArrangement = Arrangement.spacedBy(18.dp)) {
                    when {
                        currentInnings >= 2 && target != null && !chaseWon && !inningsOver -> {
                            val need = (target - state.runs).coerceAtLeast(0)
                            val rrr = if (ballsLeft > 0) need * 6.0 / ballsLeft else 0.0
                            Figure("NEED", "$need off $ballsLeft", ScTeal)
                            Figure("REQ", String.format(java.util.Locale.US, "%.2f", rrr), ScTeal)
                            Figure("CRR", String.format(java.util.Locale.US, "%.2f", crr))
                        }
                        else -> {
                            Figure("CRR", String.format(java.util.Locale.US, "%.2f", crr))
                            Figure("BALLS LEFT", "$ballsLeft")
                        }
                    }
                }

                val statusLine = when {
                    chaseWon -> "Target chased · won by ${(allOutWickets - state.wickets).coerceAtLeast(0)} wickets" to ScOlive
                    canStartSecondInnings -> "1st innings complete · ${state.runs}/${state.wickets}" to ScOlive
                    inningsOver -> stringResource(R.string.innings_complete_fmt, state.maxOvers) to ScOlive
                    else -> state.toss to ScInk2
                }
                // Toss, ground and start on ONE quiet line. A scorer arriving at a phone left
                // on the bench confirms the fixture here before touching a key; it needs to be
                // findable, not to compete with the score.
                val fixture = listOfNotNull(
                    statusLine.first.takeIf { it.isNotBlank() && statusLine.second != ScOlive },
                    state.venue.takeIf { it.isNotBlank() },
                    state.startLabel.takeIf { it.isNotBlank() }
                ).joinToString("  ·  ")
                if (statusLine.second == ScOlive) {
                    Spacer(Modifier.height(8.dp))
                    Text(statusLine.first, color = ScOlive, fontSize = 12.5.sp, fontWeight = FontWeight.Bold)
                }
                if (fixture.isNotBlank()) {
                    Spacer(Modifier.height(8.dp))
                    Text(
                        fixture, color = ScInk2, fontSize = 11.5.sp, fontWeight = FontWeight.Medium,
                        maxLines = 2, lineHeight = 15.sp,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                    )
                }
            }

            Spacer(Modifier.width(14.dp))

            // The batting side's crest, alone. The Haraan wordmark used to sit under it at
            // caption size, where it read as the TEAM's name ("Haroon") — a label under a
            // crest is always taken to be whose crest it is.
            val side = if (currentInnings >= 2) 3 - state.battedFirst else state.battedFirst
            TeamLogo(
                team = if (side == 2) state.team2Code else state.team1Code,
                logoUrl = if (side == 2) state.team2Logo else state.team1Logo,
                modifier = Modifier.size(60.dp)
            )
        }

        Box(Modifier.fillMaxWidth().height(1.dp).background(ScLine))

        // The crease. Tap a batter to change them (a confirmation is asked first).
        Crease(
            striker = state.striker,
            nonStriker = state.nonStriker,
            squad = activeBattingSquad,
            onTap = if (activeBattingSquad.isNotEmpty()) { role -> confirmChangeRole = role } else null,
        )

        // Bowler + this over (panel)
        Column(
            Modifier
                .fillMaxWidth()
                .background(ScPanel)
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                val bowlerMember = memberFor(activeBowlingSquad, state.bowler.name)
                ScorerFace(state.bowler.name, bowlerMember?.avatar.orEmpty(), ScTeal, size = 42.dp)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        "BOWLING", color = ScInk2, fontSize = 9.5.sp,
                        fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp
                    )
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            creaseName(state.bowler.name), color = ScInk, fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold, maxLines = 1,
                            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f, fill = false)
                        )
                        if (bowlerMember?.isVerified == true) {
                            Spacer(Modifier.width(5.dp))
                            VerifiedTick()
                        }
                    }
                }
                // The spell as a scorecard prints it. No maidens column: maidens are not
                // tracked, and a column of permanent zeros is a number made up.
                val b = state.bowler
                val econ = if (b.balls > 0) b.runs * 6.0 / b.balls else 0.0
                Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    SpellFigure("O", oversText(b.balls))
                    SpellFigure("R", "${b.runs}")
                    SpellFigure("W", "${b.wickets}", if (b.wickets > 0) ScRed else ScInk)
                    SpellFigure("ECON", if (b.balls > 0) String.format(java.util.Locale.US, "%.1f", econ) else "–")
                }
            }

            ThisOver(overNumber = state.balls / 6 + 1, tokens = state.thisOver)
        }

        // The keypad TAKES the remaining height rather than being pushed down by a Spacer.
        // That spacer left a fifth of the screen as dead air above a row of thin keys - the
        // surest sign of a layout that has not decided what it is for. The keys now grow
        // into whatever the device gives them.
        val bottom = when {
            canStartSecondInnings -> BottomMode.SECOND_INNINGS
            awaitingBall && !inningsOver && openingBowlerSet -> BottomMode.GATE
            else -> BottomMode.KEYPAD
        }
        // The gate and the keypad are one surface changing state, not two screens swapped.
        // BALL hands over by the keypad rising into place (the result is what's next), and
        // a scored ball hands back by the keypad sinking away under the next BALL. Quick —
        // ~200ms — because this happens every delivery and must never make a scorer wait.
        AnimatedContent(
            targetState = bottom,
            transitionSpec = {
                if (targetState == BottomMode.KEYPAD) {
                    (slideInVertically(tween(220, easing = FastOutSlowInEasing)) { it / 4 } + fadeIn(tween(160)))
                        .togetherWith(fadeOut(tween(110)) + scaleOut(tween(160), targetScale = 0.97f))
                } else {
                    (fadeIn(tween(200, delayMillis = 40)) + scaleIn(tween(240, easing = FastOutSlowInEasing), initialScale = 0.96f))
                        .togetherWith(slideOutVertically(tween(200)) { it / 5 } + fadeOut(tween(140)))
                }
            },
            label = "scorerBottom",
            modifier = Modifier.weight(1f).fillMaxWidth(),
        ) { mode ->
            when (mode) {
                BottomMode.SECOND_INNINGS -> Column(Modifier.fillMaxSize(), verticalArrangement = Arrangement.Bottom) {
                    StartSecondInningsButton(onClick = ::startSecondInnings)
                }
                BottomMode.GATE -> BallGate(
                    nextBall = "${state.balls / 6}.${state.balls % 6 + 1}",
                    bowler = creaseName(state.bowler.name),
                    striker = creaseName(state.striker.name),
                    lastBall = state.thisOver.lastOrNull(),
                    canUndo = history.isNotEmpty(),
                    showReview = cameraLive,
                    onReview = { showReview = true },
                    onBall = {
                        awaitingBall = false
                        ballInPlay = true
                        onDelivery(state, false)
                    },
                    onUndo = { apply("UNDO") },
                    modifier = Modifier.fillMaxSize(),
                )
                BottomMode.KEYPAD -> Column(Modifier.fillMaxSize()) {
                    if (ballInPlay) {
                        BallInPlayStrip(
                            bowler = creaseName(state.bowler.name),
                            striker = creaseName(state.striker.name),
                            onCancel = {
                                // Dead ball, aborted run-up: nothing was bowled. Viewers'
                                // animation stops and the scorer is back at BALL.
                                ballInPlay = false
                                awaitingBall = true
                                onDelivery(state, true)
                            },
                        )
                    }
                    Keypad(onKey = ::apply, modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

private enum class BottomMode { GATE, KEYPAD, SECOND_INNINGS }

/**
 * Between balls: BALL (the bowler is running in — viewers see the delivery) or UNDO the
 * ball just scored. BALL is the big target because it is tapped every delivery; UNDO is
 * the rare correction and sits beside it, smaller, where a thumb reaching for BALL won't
 * land on it.
 *
 * It names the actual contest — "Pillai to Sandeep" at 3.5 — because that is what the
 * scorer is about to watch, and a generic "bowler running in" could be any ball of any
 * match. With nothing to undo and no camera, the side column is gone rather than greyed:
 * a dead slab a quarter of the screen wide is the loudest thing on a quiet screen.
 */
@Composable
private fun BallGate(
    nextBall: String,
    bowler: String,
    striker: String,
    lastBall: String?,
    canUndo: Boolean,
    onBall: () -> Unit,
    onUndo: () -> Unit,
    modifier: Modifier = Modifier,
    /** A camera phone is paired: offer the last ball's clip. */
    showReview: Boolean = false,
    onReview: () -> Unit = {},
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    Column(
        modifier
            .fillMaxWidth()
            .background(Brush.verticalGradient(listOf(Color(0xFFF8FAFC), ScKey)))
            .navigationBarsPadding()
            .padding(start = 8.dp, end = 8.dp, top = 10.dp, bottom = 10.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "NEXT DELIVERY", color = ScInk2, fontSize = 10.sp,
                fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp,
            )
            Spacer(Modifier.width(8.dp))
            Text(
                nextBall, color = ScInk, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
            )
        }
        Row(
            Modifier.fillMaxWidth().weight(1f),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            BallKey(
                matchup = if (bowler.isNotBlank() && striker.isNotBlank()) "$bowler to $striker" else "Tap as the bowler runs in",
                modifier = Modifier.weight(2.2f).fillMaxHeight(),
            ) {
                scope.launch { cricketThud(context, Thud.DELIVERY) }
                onBall()
            }
            // With a camera paired, REVIEW and UNDO share the right column — both are about
            // the ball just gone, and BALL keeps its full-height target for the next one.
            if (showReview || canUndo) {
                Column(
                    Modifier.weight(1f).fillMaxHeight(),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (showReview) {
                        SideKey(
                            label = "REVIEW",
                            sub = "Watch last ball",
                            icon = Icons.Filled.PlayArrow,
                            live = true,
                            modifier = Modifier.weight(1f).fillMaxWidth(),
                        ) {
                            scope.launch { cricketThud(context, Thud.TICK) }
                            onReview()
                        }
                    }
                    if (canUndo) {
                        SideKey(
                            label = "UNDO",
                            // The ball it will take back, so nobody undoes the wrong one.
                            sub = lastBall?.let { "Last: ${ballWord(it)}" } ?: "Last ball",
                            icon = Icons.AutoMirrored.Filled.Undo,
                            modifier = Modifier.weight(1f).fillMaxWidth(),
                        ) {
                            scope.launch { cricketThud(context, Thud.UNDO) }
                            onUndo()
                        }
                    }
                }
            }
        }
    }
}

/** "4" → "four", "W" → "wicket" — how a scorer would say the ball out loud. */
private fun ballWord(token: String): String = when (token.trim().uppercase()) {
    "0", "•" -> "dot"
    "4" -> "four"
    "6" -> "six"
    "W" -> "wicket"
    "WD" -> "wide"
    "NB" -> "no-ball"
    else -> token.trim().lowercase()
}

/**
 * BALL, built like a physical key: a face sitting on a darker base, which it travels down
 * into under the thumb and springs back out of. A flat colour that only shrinks reads as a
 * picture of a button; one with travel reads as something you pressed.
 *
 * The ball on it turns slowly while waiting — the only motion on the gate, so the eye knows
 * where the next tap goes — and on the tap it is released: it shoots up and away as the
 * keypad rises, which is the delivery leaving the bowler's hand.
 */
@Composable
private fun BallKey(matchup: String, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    // One tap per gate. The key is leaving with the transition; a second tap during those
    // 200ms must not send a second BALL.
    var released by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    val travel by animateDpAsState(
        targetValue = if (pressed || released) 5.dp else 0.dp,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 1400f),
        label = "ballTravel"
    )
    val spinT = androidx.compose.animation.core.rememberInfiniteTransition(label = "ballIdle")
    val spin by spinT.animateFloat(
        initialValue = 0f, targetValue = 360f,
        animationSpec = androidx.compose.animation.core.infiniteRepeatable(
            tween(5200, easing = androidx.compose.animation.core.LinearEasing)
        ),
        label = "ballSpin"
    )
    val flight = remember { androidx.compose.animation.core.Animatable(0f) }

    Box(
        modifier
            .clip(RoundedCornerShape(20.dp))
            .background(Color(0xFF1E3A8A))
            .clickable(interactionSource = interaction, indication = null, enabled = !released) {
                released = true
                // The feel and the signal go at once; the ball's flight is only the picture
                // of it and never holds the scorer up.
                onClick()
                scope.launch {
                    flight.animateTo(1f, tween(260, easing = androidx.compose.animation.core.FastOutLinearInEasing))
                }
            },
    ) {
        Column(
            Modifier
                .fillMaxSize()
                .padding(bottom = 5.dp)
                .offset(y = travel)
                .clip(RoundedCornerShape(20.dp))
                .background(
                    Brush.verticalGradient(
                        if (pressed) listOf(Color(0xFF2563EB), Color(0xFF1D4ED8))
                        else listOf(Color(0xFF3B82F6), ScTeal)
                    )
                )
                // A hairline of light along the top edge — the face catching the sky.
                .border(
                    1.dp,
                    Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.38f), Color.Transparent)),
                    RoundedCornerShape(20.dp)
                ),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            CricketBall(
                spin = spin,
                modifier = Modifier
                    .size(46.dp)
                    .graphicsLayer {
                        val t = flight.value
                        translationY = -t * size.height * 2.4f
                        scaleX = 1f - t * 0.45f
                        scaleY = 1f - t * 0.45f
                        alpha = 1f - t
                    }
            )
            Spacer(Modifier.height(14.dp))
            Text(
                "BALL", color = Color.White,
                fontSize = 30.sp, fontWeight = FontWeight.Normal, letterSpacing = 4.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                matchup, color = Color.White.copy(alpha = 0.85f), fontSize = 12.sp,
                fontWeight = FontWeight.Medium, maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                modifier = Modifier.padding(horizontal = 12.dp),
            )
        }
    }
}

/**
 * A white ball with a real seam: two stitched lines curving across it, turning with
 * [spin]. Drawn, not an emoji — it's the one object on the screen that IS cricket.
 */
@Composable
private fun CricketBall(spin: Float, modifier: Modifier = Modifier) {
    androidx.compose.foundation.Canvas(modifier) {
        val r = size.minDimension / 2f
        // Shadow first, so the ball sits in the key rather than being stuck on it.
        drawCircle(Color.Black.copy(alpha = 0.2f), radius = r, center = center.copy(y = center.y + r * 0.12f))
        drawCircle(
            Brush.radialGradient(
                listOf(Color.White, Color(0xFFD6E0F0)),
                center = center.copy(x = center.x - r * 0.35f, y = center.y - r * 0.35f),
                radius = r * 1.6f,
            ),
            radius = r,
        )
        rotate(spin, pivot = center) {
            val stitch = androidx.compose.ui.graphics.drawscope.Stroke(
                width = r * 0.08f,
                pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(r * 0.13f, r * 0.09f)),
                cap = androidx.compose.ui.graphics.StrokeCap.Round,
            )
            // Two parallel arcs a little off-centre: a seam seen at an angle.
            for (dx in listOf(-0.12f, 0.12f)) {
                drawArc(
                    color = ScTeal,
                    startAngle = -58f, sweepAngle = 116f, useCenter = false,
                    topLeft = androidx.compose.ui.geometry.Offset(center.x - r * 2.55f + dx * r, center.y - r * 1.15f),
                    size = androidx.compose.ui.geometry.Size(r * 2.3f, r * 2.3f),
                    style = stitch,
                )
            }
        }
    }
}

/** REVIEW / UNDO: tonal, with a glyph, and giving under the thumb like the keypad keys. */
@Composable
private fun SideKey(
    label: String,
    sub: String,
    icon: ImageVector,
    modifier: Modifier,
    live: Boolean = false,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed) 0.95f else 1f,
        animationSpec = spring(dampingRatio = 0.42f, stiffness = 900f),
        label = "sideScale"
    )
    val bg by animateColorAsState(
        targetValue = if (pressed) ScTeal.copy(alpha = 0.16f) else ScPanel,
        label = "sideBg"
    )
    Box(
        modifier
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .border(1.dp, if (pressed) ScTeal.copy(alpha = 0.6f) else Color(0xFFCBD5E1), RoundedCornerShape(16.dp))
            .clickable(interactionSource = interaction, indication = null, onClick = onClick),
    ) {
        if (live) {
            // The camera is actually checking in — green is "live", as everywhere else.
            Row(
                Modifier.align(Alignment.TopEnd).padding(top = 9.dp, end = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(6.dp).clip(CircleShape).background(Color(0xFF16A34A)))
                Spacer(Modifier.width(4.dp))
                Text("CAM", color = ScInk2, fontSize = 8.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.8.sp)
            }
        }
        Column(
            Modifier.fillMaxSize().padding(horizontal = 6.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Box(
                Modifier.size(32.dp).clip(CircleShape).background(ScTeal.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(icon, null, tint = ScTeal, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.height(6.dp))
            Text(label, color = ScInk, fontSize = 14.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Spacer(Modifier.height(1.dp))
            Text(
                sub, color = ScInk2, fontSize = 11.sp, fontWeight = FontWeight.Medium,
                maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            )
        }
    }
}

/**
 * Over the keypad while a ball is in play: who is bowling to whom, how long viewers have
 * been watching the run-up, and a way out. The clock matters — a ball "in play" for 40s
 * means the scorer forgot to enter it, and viewers are staring at a looping delivery.
 */
@Composable
private fun BallInPlayStrip(bowler: String, striker: String, onCancel: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val pulse = androidx.compose.animation.core.rememberInfiniteTransition(label = "inPlay")
    val ring by pulse.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = androidx.compose.animation.core.infiniteRepeatable(
            tween(1100, easing = androidx.compose.animation.core.LinearOutSlowInEasing)
        ),
        label = "ring"
    )
    var seconds by remember { mutableIntStateOf(0) }
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(1_000)
            seconds++
        }
    }
    val stale = seconds >= 30
    val bg by animateColorAsState(
        if (stale) Color(0xFFFFF7ED) else ScTeal.copy(alpha = 0.07f), label = "inPlayBg"
    )
    Row(
        Modifier
            .fillMaxWidth()
            .background(bg)
            .padding(start = 14.dp, end = 14.dp, top = 8.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // A dot sending out a ring, like a radar blip: live, without blinking at the scorer.
        Box(Modifier.size(18.dp), contentAlignment = Alignment.Center) {
            Box(
                Modifier
                    .size(18.dp)
                    .graphicsLayer {
                        scaleX = 0.35f + ring * 0.65f
                        scaleY = 0.35f + ring * 0.65f
                        alpha = 1f - ring
                    }
                    .clip(CircleShape)
                    .background(ScTeal.copy(alpha = 0.5f))
            )
            Box(Modifier.size(7.dp).clip(CircleShape).background(ScTeal))
        }
        Spacer(Modifier.width(8.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "BALL IN PLAY", color = ScTeal, fontSize = 10.5.sp,
                    fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    if (seconds > 59) "1:00+" else "0:%02d".format(seconds),
                    color = if (stale) ScSix else ScInk2, fontSize = 11.sp, fontWeight = FontWeight.SemiBold,
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
            }
            Text(
                when {
                    stale -> "Viewers are waiting — enter the result"
                    bowler.isNotBlank() && striker.isNotBlank() -> "$bowler to $striker"
                    else -> "Tap the result"
                },
                color = ScInk, fontSize = 12.sp, fontWeight = FontWeight.Medium,
                maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            )
        }
        Text(
            "Dead ball", color = ScInk, fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
            modifier = Modifier
                .clip(RoundedCornerShape(50))
                .background(ScPanel)
                .border(1.dp, Color(0xFFCBD5E1), RoundedCornerShape(50))
                .clickable {
                    scope.launch { cricketThud(context, Thud.TICK) }
                    onCancel()
                }
                .padding(horizontal = 12.dp, vertical = 8.dp)
        )
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
 * A number that turns over like a scoreboard plate: the old value slides out, the new one
 * in, upward when it grows and downward on an undo. The count going UP is something you
 * see happen, not a digit that is quietly different next time you look.
 */
@Composable
private fun RollingFigure(value: Int, fontSize: androidx.compose.ui.unit.TextUnit, color: Color) {
    AnimatedContent(
        targetState = value,
        transitionSpec = {
            val up = targetState > initialState
            (slideInVertically(spring(dampingRatio = 0.8f, stiffness = 500f)) { if (up) it else -it } + fadeIn(tween(120)))
                .togetherWith(slideOutVertically(tween(160)) { if (up) -it / 2 else it / 2 } + fadeOut(tween(100)))
                .using(androidx.compose.animation.SizeTransform(clip = true))
        },
        label = "rollingFigure",
    ) { v ->
        Text(
            "$v", color = color, fontSize = fontSize,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/** A labelled figure on the score line: small caps label, the value in ink. No box. */
@Composable
private fun Figure(label: String, value: String, accent: Color = ScInk) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(label, color = ScInk2, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.8.sp)
        Spacer(Modifier.width(6.dp))
        Text(
            value, color = accent, fontSize = 14.sp, fontWeight = FontWeight.Bold,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/** One column of the bowler's spell: the scorecard's own heading over its value. */
@Composable
private fun SpellFigure(label: String, value: String, color: Color = ScInk) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, color = ScInk2, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp)
        Spacer(Modifier.height(2.dp))
        Text(
            value, color = color, fontSize = 15.sp, fontWeight = FontWeight.Bold,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/**
 * The two batters, and which of them is on strike.
 *
 * The batters stay where they stand. The old layout always drew the striker on the left,
 * so every single swapped the two players across the screen — the eye had to re-find
 * both names after every odd run. Now each keeps their side (a new batter takes the
 * departed one's place) and the STRIKE moves: a lit plate slides across to whoever faces
 * next. Crossing for a run is the one thing on this row that should move, so it does.
 */
@Composable
private fun Crease(
    striker: ScorerBatter,
    nonStriker: ScorerBatter,
    squad: List<SquadMember>,
    onTap: ((role: String) -> Unit)?,
) {
    // Which name stands on which side. Plain memory, not state: it is a pure function of
    // the names seen so far, recomputed deterministically on every composition.
    val sides = remember { arrayOf(striker.name, nonStriker.name) }
    val names = listOf(striker.name, nonStriker.name)
    when {
        striker.name == nonStriker.name -> { sides[0] = striker.name; sides[1] = nonStriker.name }
        sides[0] in names && sides[1] in names && sides[0] != sides[1] -> Unit
        sides[0] in names -> sides[1] = names.first { it != sides[0] }
        sides[1] in names -> sides[0] = names.first { it != sides[1] }
        else -> { sides[0] = striker.name; sides[1] = nonStriker.name }
    }
    val strikerLeft = striker.name == sides[0]
    val left = if (strikerLeft) striker else nonStriker
    val right = if (strikerLeft) nonStriker else striker

    val plate by animateFloatAsState(
        targetValue = if (strikerLeft) 0f else 1f,
        animationSpec = spring(dampingRatio = 0.74f, stiffness = 420f),
        label = "strikePlate"
    )

    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 10.dp, vertical = 10.dp)
    ) {
        val half = maxWidth / 2
        // The lit plate under the striker.
        Box(
            Modifier
                .offset(x = half * plate)
                .width(half)
                .height(IntrinsicPlateHeight)
                .padding(horizontal = 2.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(ScTeal.copy(alpha = 0.07f))
                .border(1.dp, ScTeal.copy(alpha = 0.22f), RoundedCornerShape(16.dp))
        )
        Row(Modifier.fillMaxWidth().height(IntrinsicPlateHeight), verticalAlignment = Alignment.CenterVertically) {
            CreaseBatter(
                b = left, onStrike = strikerLeft, mirrored = false,
                member = memberFor(squad, left.name),
                onClick = onTap?.let { tap -> { tap(if (strikerLeft) "striker" else "nonStriker") } },
                modifier = Modifier.weight(1f),
            )
            CreaseBatter(
                b = right, onStrike = !strikerLeft, mirrored = true,
                member = memberFor(squad, right.name),
                onClick = onTap?.let { tap -> { tap(if (strikerLeft) "nonStriker" else "striker") } },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

private val IntrinsicPlateHeight = 84.dp

@Composable
private fun CreaseBatter(
    b: ScorerBatter,
    onStrike: Boolean,
    mirrored: Boolean,
    member: SquadMember?,
    onClick: (() -> Unit)?,
    modifier: Modifier,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        if (pressed) 0.97f else 1f, spring(dampingRatio = 0.5f, stiffness = 900f), label = "batterPress"
    )
    val nameColor by animateColorAsState(if (onStrike) ScTeal else ScInk, label = "batterName")

    val face: @Composable () -> Unit = {
        ScorerFace(b.name, member?.avatar.orEmpty(), if (onStrike) ScTeal else ScInk2, size = 46.dp)
    }
    val text: @Composable () -> Unit = {
        Column(horizontalAlignment = if (mirrored) Alignment.End else Alignment.Start) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                if (onStrike && mirrored) BatGlyph()
                Text(
                    creaseName(b.name), color = nameColor, fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold, maxLines = 1,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                    modifier = Modifier.widthIn(max = 104.dp)
                )
                if (member?.isVerified == true) VerifiedTick(12.dp)
                if (onStrike && !mirrored) BatGlyph()
            }
            Row(verticalAlignment = Alignment.Bottom) {
                RollingFigure(b.runs, fontSize = 22.sp, color = ScInk)
                Text(
                    " (${b.balls})", color = ScInk2, fontSize = 13.sp, fontWeight = FontWeight.Medium,
                    modifier = Modifier.padding(bottom = 3.dp),
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
            }
            Text(
                if (b.balls > 0) "SR " + String.format(java.util.Locale.US, "%.1f", b.runs * 100.0 / b.balls) else "Yet to face",
                color = ScInk2, fontSize = 10.5.sp, fontWeight = FontWeight.Medium,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
            )
        }
    }

    Row(
        modifier
            .fillMaxHeight()
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(16.dp))
            .then(
                if (onClick != null) Modifier.clickable(interactionSource = interaction, indication = null, onClick = onClick)
                else Modifier
            )
            .padding(horizontal = 10.dp),
        horizontalArrangement = if (mirrored) Arrangement.End else Arrangement.Start,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (mirrored) { text(); Spacer(Modifier.width(10.dp)); face() }
        else { face(); Spacer(Modifier.width(10.dp)); text() }
    }
}

/**
 * A small cricket bat beside the striker's name, leaning as a bat rests against a
 * batter's pad: a long blade with a square shoulder and a thin handle. Drawn upright it
 * read as a bottle; the lean and the proportions are what make it a bat.
 */
@Composable
private fun BatGlyph() {
    androidx.compose.foundation.Canvas(Modifier.size(12.dp, 16.dp)) {
        val h = size.height
        val bladeW = h * 0.26f
        rotate(28f, pivot = center) {
            val x = center.x - bladeW / 2f
            // Handle: thin, rounded, the top third.
            drawRoundRect(
                ScTeal,
                topLeft = androidx.compose.ui.geometry.Offset(center.x - bladeW * 0.18f, 0f),
                size = androidx.compose.ui.geometry.Size(bladeW * 0.36f, h * 0.36f),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(bladeW * 0.18f),
            )
            // Blade: square shoulders, rounded toe.
            drawRoundRect(
                ScTeal,
                topLeft = androidx.compose.ui.geometry.Offset(x, h * 0.33f),
                size = androidx.compose.ui.geometry.Size(bladeW, h * 0.67f),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(bladeW * 0.22f),
            )
        }
    }
}

/**
 * The over as six places. Every legal ball fills the next place; a wide or no-ball sits
 * between them without taking one, because it has to be bowled again — which is exactly
 * how an over is counted, and why the old "last six tokens" strip dropped real balls off
 * the front whenever the bowler sprayed a wide. The empty places are the balls still to
 * come, so the scorer sees at a glance that this is ball four, not "some balls".
 */
@Composable
private fun ThisOver(overNumber: Int, tokens: List<String>) {
    val legal = tokens.count { !isIllegal(it) }
    val runs = tokens.sumOf { t ->
        when (t.trim().uppercase()) {
            "W" -> 0
            "WD", "NB" -> 1
            else -> t.trim().toIntOrNull() ?: 0
        }
    }
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "OVER $overNumber", color = ScInk2, fontSize = 10.sp,
                fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp
            )
            Spacer(Modifier.weight(1f))
            Text(
                "$runs ${if (runs == 1) "run" else "runs"}",
                color = ScInk, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
            )
        }
        val scroll = rememberScrollState()
        // A long over (wides) runs past the edge; keep the ball just bowled in view.
        LaunchedEffect(tokens.size) { scroll.animateScrollTo(scroll.maxValue) }
        Row(
            Modifier.fillMaxWidth().horizontalScroll(scroll),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            tokens.forEachIndexed { i, token ->
                BallBubble(token, newest = i == tokens.lastIndex)
            }
            repeat((6 - legal).coerceAtLeast(0)) { EmptyBall() }
        }
    }
}

private fun isIllegal(token: String) = token.trim().uppercase().let { it == "WD" || it == "NB" }

/** A ball still to be bowled: a dashed ring, the shape of what will fill it. */
@Composable
private fun EmptyBall() {
    androidx.compose.foundation.Canvas(Modifier.size(38.dp)) {
        drawCircle(
            Color(0xFFCBD5E1),
            radius = size.minDimension / 2f - 1.dp.toPx(),
            style = androidx.compose.ui.graphics.drawscope.Stroke(
                width = 1.5.dp.toPx(),
                pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(5.dp.toPx(), 4.dp.toPx()))
            )
        )
    }
}

@Composable
private fun BallBubble(token: String, newest: Boolean = false) {
    val t = token.trim().uppercase()
    // Scorecard notation, lower-case for extras so they never shout louder than runs.
    val label = when (t) {
        "0", "•" -> "•"
        "WD" -> "wd"
        "NB" -> "nb"
        "B", "BYE" -> "b"
        "LB" -> "lb"
        else -> token.trim()
    }
    // Every kind has a surface. Runs used to be white on a white panel — text floating in
    // space, with nothing to say "this is a ball".
    val bg: Color
    val fg: Color
    var ring: Color = Color.Transparent
    when (t) {
        "6" -> { bg = ScSix; fg = Color.White }
        "4" -> { bg = ScFour; fg = Color.White }
        "W" -> { bg = ScRed; fg = Color.White }
        "0", "•" -> { bg = Color(0xFFEEF2F7); fg = ScInk2 }
        "WD", "NB" -> { bg = ScSix.copy(alpha = 0.12f); fg = Color(0xFFB45309); ring = ScSix.copy(alpha = 0.45f) }
        "B", "BYE", "LB" -> { bg = Color(0xFFEEF2F7); fg = ScInk2; ring = Color(0xFFCBD5E1) }
        else -> { bg = ScPanel; fg = ScInk; ring = Color(0xFF94A3B8) }
    }

    // The ball just entered lands rather than appears: it punches in slightly oversized and
    // settles — the on-screen half of the key's knock.
    val scale = remember(token, newest) { androidx.compose.animation.core.Animatable(if (newest) 0.55f else 1f) }
    LaunchedEffect(newest, token) {
        if (newest) scale.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = 700f))
    }

    Box(
        modifier = Modifier
            .size(38.dp)
            .graphicsLayer { scaleX = scale.value; scaleY = scale.value }
            .clip(CircleShape)
            .background(bg)
            .border(1.5.dp, ring, CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(
            label, color = fg,
            fontSize = if (label.length > 1) 12.sp else 15.sp,
            fontWeight = FontWeight.Bold,
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
        Text(
            scorerInitials(name), color = accent,
            fontSize = (size.value * 0.34f).sp, fontWeight = FontWeight.Bold,
            letterSpacing = 0.5.sp,
        )
        if (photoUrl.isNotBlank()) {
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = name,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
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

/**
 * Who made the dismissal — asked only for a catch, run-out or stumping, from the
 * FIELDING side. Skippable, because a scorer who did not see it must not be forced
 * to name someone: a wrong catch on a career is worse than a missing one.
 */
@Composable
private fun FielderPicker(
    squad: List<SquadMember>,
    dismissal: String,
    onPick: (SquadMember) -> Unit,
    onSkip: () -> Unit,
) {
    val headline = when (dismissal) {
        "caught" -> "Who took the catch?"
        "runout" -> "Who ran him out?"
        else -> "Who stumped him?"
    }
    Dialog(
        onDismissRequest = onSkip,
        properties = DialogProperties(dismissOnBackPress = true, dismissOnClickOutside = false),
    ) {
        Column(
            Modifier.clip(RoundedCornerShape(18.dp)).background(ScPanel).padding(20.dp),
        ) {
            Text("FIELDING", color = ScRed, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(headline, color = ScInk, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            Column(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.heightIn(max = 320.dp).verticalScroll(rememberScrollState()),
            ) {
                squad.forEach { member ->
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(ScDark)
                            .clickable { onPick(member) }
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(
                            member.name,
                            color = ScInk,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                        )
                    }
                }
            }
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .clickable(onClick = onSkip)
                    .padding(vertical = 12.dp),
                horizontalArrangement = Arrangement.Center,
            ) {
                Text("Not sure", color = ScInk.copy(alpha = 0.6f), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
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

/**
 * Over-end bowler chooser. Forced (can't dismiss) so the over always rolls with a bowler.
 *
 * Two steps, and the second is skipped for anyone already bowled this match: pick the
 * bowler, then say whether they bowl pace or spin. That second answer is the only fact
 * about a delivery that cannot be recovered afterwards — a profile's "bowling style"
 * cannot say which of the two an all-rounder used for THIS over — so it is collected
 * now, even though nothing reads it yet.
 */
@Composable
private fun BowlerPicker(
    squad: List<SquadMember>,
    currentName: String,
    opening: Boolean = false,
    knownTypes: Map<String, String> = emptyMap(),
    onPick: (SquadMember, String?) -> Unit,
) {
    var awaitingType by remember { mutableStateOf<SquadMember?>(null) }
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
                            .clickable {
                                // Already answered for this bowler in this match: do not
                                // ask a scorer the same question every over.
                                val known = knownTypes[member.name]
                                if (known != null) onPick(member, known) else awaitingType = member
                            }
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

    awaitingType?.let { member ->
        BowlerTypePicker(member) { chosen ->
            awaitingType = null
            onPick(member, chosen)
        }
    }
}

/** Pace, spin, or an honest "not sure" — which is a real answer, not a skipped one. */
@Composable
private fun BowlerTypePicker(member: SquadMember, onPick: (String?) -> Unit) {
    Dialog(
        onDismissRequest = { onPick(null) },
        properties = DialogProperties(dismissOnBackPress = true, dismissOnClickOutside = false),
    ) {
        Column(
            Modifier.clip(RoundedCornerShape(18.dp)).background(ScPanel).padding(20.dp),
        ) {
            Text("BOWLING TYPE", color = ScTeal, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(
                "How does ${member.name} bowl?",
                color = ScInk,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                "Asked once per bowler. It is what lets Haraan show how wickets fall at a ground.",
                color = ScInk2,
                fontSize = 12.5.sp,
                lineHeight = 17.sp,
            )
            Spacer(Modifier.height(16.dp))
            listOf(
                Triple("Pace", "Fast, medium, seam", "pace"),
                Triple("Spin", "Off, leg, orthodox", "spin"),
                Triple("Not sure", "Leaves it unrecorded", null),
            ).forEach { (label, sub, value) ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(ScDark)
                        .clickable { onPick(value) }
                        .padding(horizontal = 16.dp, vertical = 13.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(
                            label,
                            color = if (value == null) ScInk2 else ScInk,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Spacer(Modifier.height(2.dp))
                        Text(sub, color = ScInk2, fontSize = 11.5.sp)
                    }
                }
                Spacer(Modifier.height(8.dp))
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
