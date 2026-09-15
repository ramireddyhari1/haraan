package com.haraan.app.ui.matches

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Undo
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.SquadMember
import com.haraan.app.ui.pressable
import kotlinx.coroutines.launch
import org.json.JSONObject

/** One thing the scorer says happened. The server decides what it is worth. */
data class ScorerEvent(
    val kind: String,
    val side: String? = null,
    val detail: String? = null,
    val player: String? = null,
    val related: String? = null,
)

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Line = Color(0xFFE6EBF2)
private val Page = Color(0xFFF4F7FB)

/**
 * The scorer for volleyball, basketball, kabaddi, tennis, table tennis and badminton.
 *
 * It posts what happened — "a three by Arjun, assisted by Ravi", "a two-point raid with a
 * bonus", "home serves first" — and re-reads the board the server replays from it. It never
 * sends a score. Each sport gets the keypad that sport's scorer actually needs:
 *
 *  · Rally sports: who serves first, one Point per side, optional ace / fault detail,
 *    timeouts where the sport has them.
 *  · Basketball: 2 / 3 / FT, then the box score row — rebound, steal, block, foul, turnover —
 *    each creditable to a player, plus timeouts and End quarter.
 *  · Kabaddi: a raid builder (defenders touched, bonus), tackle, empty raid and the do-or-die
 *    outcome, on a mat the server tracks — all-outs and super tackles are the rules' call.
 *
 * Every recorded moment is listed with its own undo, so a mis-tap three events ago can be
 * removed without undoing the two correct ones after it.
 */
@Composable
fun SportScorerScreen(
    state: MatchUiState,
    board: SportBoard,
    onEvent: suspend (ScorerEvent) -> Boolean,
    /** Undo one event by sequence, or the latest when null. */
    onUndo: suspend (sequence: Int?) -> Boolean,
    /** Merge into sport_state — used to switch a fresh kabaddi match onto mat scoring. */
    onSportState: suspend (JSONObject) -> Boolean = { false },
    onFinish: (suspend () -> Boolean)? = null,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val sport = board.sport.lowercase()
    val theme = sportThemeFor(sport)

    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf("") }
    var confirmFinish by remember { mutableStateOf(false) }
    var confirmPeriod by remember { mutableStateOf(false) }

    fun run(block: suspend () -> Boolean, failure: String) {
        if (busy) return
        busy = true
        error = ""
        scope.launch {
            val ok = runCatching { block() }.getOrDefault(false)
            if (!ok) error = failure
            busy = false
        }
    }

    fun send(e: ScorerEvent) {
        hapticConfirm(view)
        run({ onEvent(e) }, "Couldn't record that. Check your connection and try again.")
    }

    fun undo(sequence: Int?) {
        hapticTick(view)
        run({ onUndo(sequence) }, "Couldn't undo that.")
    }

    // A kabaddi match with nothing recorded yet is scored on the mat from its first raid.
    LaunchedEffect(sport, board.feed.isEmpty(), board.matRules) {
        if (sport == "kabaddi" && board.feed.isEmpty() && !board.matRules) {
            onSportState(JSONObject().put("rules", JSONObject().put("mat", true)))
        }
    }

    Column(modifier.fillMaxSize().background(Page).statusBarsPadding()) {
        // ── Bar ──
        Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            RoundIcon(onClick = onDone) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Close", tint = Ink, modifier = Modifier.size(18.dp)) }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Scoring", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
                Text(SportLook.displayName(sport), fontSize = 11.5.sp, color = Faint)
            }
            if (board.feed.isNotEmpty()) {
                Pill("Undo", enabled = !busy, icon = true) { undo(null) }
                Spacer(Modifier.width(8.dp))
            }
            if (onFinish != null) {
                Pill("Finish", enabled = !busy) { confirmFinish = true }
            }
        }

        ScorerBoard(state, board, theme)

        AnimatedVisibility(error.isNotBlank()) {
            Text(error, fontSize = 12.5.sp, color = Color(0xFFDC2626), textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp))
        }

        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 12.dp)) {
            when {
                board.decided -> DoneNote(board, state)
                sport == "basketball" -> BasketballPad(state, board, theme, !busy, ::send) { confirmPeriod = true }
                sport == "kabaddi" -> KabaddiPad(state, board, theme, !busy, ::send) { confirmPeriod = true }
                else -> RallyPad(state, board, theme, sport, !busy, ::send)
            }
            if (board.feed.isNotEmpty()) {
                Spacer(Modifier.height(18.dp))
                RecentEvents(state, board, !busy) { undo(it) }
            }
            Spacer(Modifier.height(24.dp))
        }
    }

    if (confirmFinish) {
        AlertDialog(
            onDismissRequest = { if (!busy) confirmFinish = false },
            title = { Text("Finish this match?", fontWeight = FontWeight.Bold) },
            text = { Text("The score is frozen as it stands and the match leaves the live feed.") },
            confirmButton = {
                TextButton(enabled = !busy, onClick = {
                    busy = true
                    scope.launch {
                        val ok = onFinish?.invoke() ?: false
                        busy = false
                        confirmFinish = false
                        if (ok) onDone() else error = "Couldn't finish the match."
                    }
                }) { Text("Finish", fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(enabled = !busy, onClick = { confirmFinish = false }) { Text("Keep scoring", color = Muted) } },
            containerColor = Color.White,
        )
    }

    if (confirmPeriod) {
        val label = if (sport == "basketball") "End ${board.periodLabel.ifBlank { "Q${board.period}" }}?" else "Half time?"
        AlertDialog(
            onDismissRequest = { confirmPeriod = false },
            title = { Text(label, fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    if (sport == "basketball") "Team fouls reset for the next period. Timeouts reset at half time and in overtime."
                    else "The second half starts with the side that did not raid first.",
                )
            },
            confirmButton = {
                TextButton(onClick = { confirmPeriod = false; send(ScorerEvent("period")) }) {
                    Text("Confirm", fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = { TextButton(onClick = { confirmPeriod = false }) { Text("Cancel", color = Muted) } },
            containerColor = Color.White,
        )
    }
}

/** The board the scorer keeps in view — the sport's live unit, and what is at stake. */
@Composable
private fun ScorerBoard(state: MatchUiState, board: SportBoard, theme: SportTheme) {
    val (left, right, caption) = when {
        board.sport == "tennis" -> Triple(
            board.points?.first ?: "0", board.points?.second ?: "0",
            "Games ${board.games?.first ?: 0}–${board.games?.second ?: 0} · Sets ${board.setsHome}–${board.setsAway}" + if (board.tiebreak) " · tie-break" else "",
        )
        board.isSetSport -> Triple(
            "${board.current?.first ?: 0}", "${board.current?.second ?: 0}",
            "${board.setNoun} ${board.sets.size + 1} · to ${board.target} · ${board.setNoun}s ${board.setsHome}–${board.setsAway}",
        )
        else -> Triple("${board.totals.first}", "${board.totals.second}", board.periodLabel.ifBlank { "Live" })
    }
    Column(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp).clip(RoundedCornerShape(16.dp)).background(Color.White)
            .border(1.dp, Line, RoundedCornerShape(16.dp)).padding(vertical = 12.dp, horizontal = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ScoreHalf(state.team1.ifBlank { "Home" }, left, board.serving == "home" || board.raiding == "home", theme, Modifier.weight(1f))
            Text(caption, fontSize = 10.5.sp, color = Faint, textAlign = TextAlign.Center, modifier = Modifier.weight(1.3f))
            ScoreHalf(state.team2.ifBlank { "Away" }, right, board.serving == "away" || board.raiding == "away", theme, Modifier.weight(1f))
        }
        val situations = situationsFor(board, state)
        if (situations.isNotEmpty()) {
            Spacer(Modifier.height(8.dp))
            Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { SituationRow(situations) }
        }
    }
}

@Composable
private fun ScoreHalf(name: String, figure: String, active: Boolean, theme: SportTheme, modifier: Modifier) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ServeMarker(active, theme.spark, size = 7)
            if (active) Spacer(Modifier.width(5.dp))
            Text(name, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        RollingFigure(figure, Ink, 30)
    }
}

// ─────────────────────────────── rally sports ───────────────────────────────

@Composable
private fun RallyPad(
    state: MatchUiState,
    board: SportBoard,
    theme: SportTheme,
    sport: String,
    enabled: Boolean,
    send: (ScorerEvent) -> Unit,
) {
    var detail by remember { mutableStateOf<String?>(null) }
    var homePlayer by remember { mutableStateOf<String?>(null) }
    var awayPlayer by remember { mutableStateOf<String?>(null) }
    var changeServer by remember { mutableStateOf(false) }
    val noPoints = board.feed.none { it.kind == "point" }
    // Table tennis and tennis cannot know the server without being told; the other two learn it
    // from the first rally but still benefit from being told.
    val needsServer = board.serving == null && (sport == "tennis" || sport == "table_tennis")

    if (needsServer || (board.serving == null && noPoints) || changeServer) {
        SectionLabel(if (changeServer) "Who is serving now?" else "Who serves first?")
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            listOf("home" to state.team1, "away" to state.team2).forEach { (side, name) ->
                BigButton(name.ifBlank { side }, theme.deep, enabled, Modifier.weight(1f)) {
                    changeServer = false
                    send(ScorerEvent("serve", side))
                }
            }
        }
        if (!needsServer && !changeServer) {
            Spacer(Modifier.height(6.dp))
            Text("Optional — the first rally decides it otherwise.", fontSize = 11.5.sp, color = Faint)
        }
        Spacer(Modifier.height(16.dp))
        if (needsServer) return
    }

    val details = when (sport) {
        "tennis" -> listOf("ace" to "Ace", "double_fault" to "Double fault", "winner" to "Winner", "error" to "Error")
        else -> listOf("ace" to "Ace", "error" to "Error")
    }
    SectionLabel("How was it won? (optional)")
    ChipRow(details.map { it.second }, details.firstOrNull { it.first == detail }?.second) { label ->
        val key = details.first { it.second == label }.first
        detail = if (detail == key) null else key
    }
    if (detail == "double_fault" || detail == "error") {
        Text("Tap Point for the side that WON the point.", fontSize = 11.sp, color = Faint, modifier = Modifier.padding(top = 4.dp))
    }
    Spacer(Modifier.height(12.dp))

    listOf(
        Triple("home", state.team1, state.homeSquad),
        Triple("away", state.team2, state.awaySquad),
    ).forEachIndexed { i, (side, name, squad) ->
        if (i > 0) Spacer(Modifier.height(12.dp))
        val accent = if (side == "home") state.team1Color else state.team2Color
        val selected = if (side == "home") homePlayer else awayPlayer
        SideCard(name.ifBlank { side }, accent, serving = board.serving == side) {
            PlayerChips(squad, selected, accent) { p ->
                if (side == "home") homePlayer = if (homePlayer == p) null else p else awayPlayer = if (awayPlayer == p) null else p
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                BigButton("Point", accent, enabled, Modifier.weight(1f)) {
                    send(ScorerEvent("point", side, detail, selected))
                    detail = null
                }
                if (board.timeoutsAllowed != null) {
                    val used = if (side == "home") board.timeouts?.first ?: 0 else board.timeouts?.second ?: 0
                    SmallButton("Timeout $used/${board.timeoutsAllowed}", enabled && used < board.timeoutsAllowed) {
                        send(ScorerEvent("timeout", side))
                    }
                }
            }
        }
    }
    if (board.serving != null) {
        Spacer(Modifier.height(10.dp))
        Text(
            "Server wrong? Change it",
            fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = theme.deep,
            modifier = Modifier.clip(RoundedCornerShape(8.dp)).pressable { changeServer = true }.padding(6.dp),
        )
    }
}

// ─────────────────────────────── basketball ───────────────────────────────

@Composable
private fun BasketballPad(
    state: MatchUiState,
    board: SportBoard,
    theme: SportTheme,
    enabled: Boolean,
    send: (ScorerEvent) -> Unit,
    onEndPeriod: () -> Unit,
) {
    var side by remember { mutableStateOf("home") }
    var player by remember { mutableStateOf<String?>(null) }
    var assist by remember { mutableStateOf<String?>(null) }
    val squad = if (side == "home") state.homeSquad else state.awaySquad
    val accent = if (side == "home") state.team1Color else state.team2Color

    SideToggle(state, side) { side = it; player = null; assist = null }
    Spacer(Modifier.height(12.dp))
    SideCard((if (side == "home") state.team1 else state.team2).ifBlank { side }, accent, serving = false) {
        SectionLabel(if (player != null) "Player: $player" else "Player (optional)")
        PlayerChips(squad, player, accent) { player = if (player == it) null else it; if (assist == it) assist = null }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            listOf("+2" to "2", "+3" to "3", "FT" to "1").forEach { (label, detail) ->
                BigButton(label, accent, enabled, Modifier.weight(1f)) {
                    send(ScorerEvent("point", side, detail, player, if (detail != "1") assist else null))
                    assist = null
                }
            }
        }
        if (squad.size > 1) {
            Spacer(Modifier.height(10.dp))
            SectionLabel(if (assist != null) "Assist: $assist" else "Assisted by (optional)")
            PlayerChips(squad.filter { it.name != player }, assist, accent) { assist = if (assist == it) null else it }
        }
        Spacer(Modifier.height(12.dp))
        SectionLabel("Box score")
        val stats = listOf("rebound" to "REB", "steal" to "STL", "block" to "BLK", "turnover" to "TO", "foul" to "FOUL")
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            stats.forEach { (kind, label) ->
                StatKey(label, if (kind == "foul") Color(0xFFB45309) else Ink, enabled, Modifier.weight(1f)) {
                    send(ScorerEvent(kind, side, null, player))
                }
            }
        }
    }
    Spacer(Modifier.height(12.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        val used = if (side == "home") board.timeouts?.first ?: 0 else board.timeouts?.second ?: 0
        val allowed = board.timeoutsAllowed ?: 2
        SmallButton("Timeout ($used/$allowed)", enabled && used < allowed, Modifier.weight(1f)) { send(ScorerEvent("timeout", side)) }
        SmallButton("End ${board.periodLabel.ifBlank { "Q${board.period}" }}", enabled, Modifier.weight(1f), dark = true) { onEndPeriod() }
    }
}

// ─────────────────────────────── kabaddi ───────────────────────────────

@Composable
private fun KabaddiPad(
    state: MatchUiState,
    board: SportBoard,
    theme: SportTheme,
    enabled: Boolean,
    send: (ScorerEvent) -> Unit,
    onHalf: () -> Unit,
) {
    // A match already scored the old way keeps its old keypad: replaying it across a mat would
    // rewrite its result.
    if (!board.matRules && board.feed.isNotEmpty()) {
        LegacyKabaddiPad(state, enabled, send)
        Spacer(Modifier.height(12.dp))
        SmallButton("Half time", enabled, Modifier.fillMaxWidth(), dark = true) { onHalf() }
        return
    }

    var raider by remember { mutableStateOf(board.raiding ?: "home") }
    LaunchedEffect(board.raiding) { board.raiding?.let { raider = it } }
    var touches by remember { mutableStateOf(0) }
    var bonus by remember { mutableStateOf(false) }
    var raiderName by remember { mutableStateOf<String?>(null) }
    var tackler by remember { mutableStateOf<String?>(null) }

    val defender = if (raider == "home") "away" else "home"
    val defendersOn = board.mat?.let { if (defender == "home") it.home else it.away } ?: 7
    val raidColor = if (raider == "home") state.team1Color else state.team2Color
    val defColor = if (defender == "home") state.team1Color else state.team2Color
    val raidTeam = (if (raider == "home") state.team1 else state.team2).ifBlank { raider }
    val defTeam = (if (defender == "home") state.team1 else state.team2).ifBlank { defender }
    val doOrDie = board.doOrDie && board.raiding == raider

    fun reset() { touches = 0; bonus = false; raiderName = null; tackler = null }

    if (board.raiding == null) {
        SectionLabel("Who raids first?")
    } else {
        SectionLabel("Raiding now")
    }
    SideToggle(state, raider) { raider = it; reset() }
    if (board.raiding == null) {
        Spacer(Modifier.height(8.dp))
        SmallButton("Confirm $raidTeam raids first", enabled, Modifier.fillMaxWidth()) { send(ScorerEvent("serve", raider)) }
    }
    Spacer(Modifier.height(12.dp))

    SideCard("Raid · $raidTeam", raidColor, serving = false) {
        if (doOrDie) {
            SituationChip(Situation("Do-or-die raid", Color(0xFFDC2626)))
            Spacer(Modifier.height(10.dp))
        }
        SectionLabel(if (raiderName != null) "Raider: $raiderName" else "Raider (optional)")
        PlayerChips(if (raider == "home") state.homeSquad else state.awaySquad, raiderName, raidColor) { raiderName = if (raiderName == it) null else it }
        SectionLabel("Defenders touched out ($defendersOn on mat)")
        Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            (0..defendersOn.coerceAtMost(7)).forEach { n ->
                val on = touches == n
                Box(
                    Modifier.size(44.dp).clip(CircleShape)
                        .background(if (on) raidColor else Color(0xFFF1F5F9))
                        .pressable { touches = n },
                    contentAlignment = Alignment.Center,
                ) { Text("$n", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = if (on) Color.White else Ink) }
            }
        }
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Toggle("Bonus point", bonus, raidColor) { bonus = !bonus }
            Spacer(Modifier.weight(1f))
            val pts = touches + if (bonus) 1 else 0
            Text(if (pts >= 3) "SUPER RAID" else "", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFFD97706), letterSpacing = 0.8.sp)
        }
        Spacer(Modifier.height(12.dp))
        val points = touches + if (bonus) 1 else 0
        if (points > 0) {
            BigButton("Record raid  +$points", raidColor, enabled, Modifier.fillMaxWidth()) {
                send(ScorerEvent("point", raider, "raid:$touches" + if (bonus) ":b" else "", raiderName))
                reset()
            }
        } else if (doOrDie) {
            BigButton("Raider out  ·  +1 $defTeam", Color(0xFFDC2626), enabled, Modifier.fillMaxWidth()) {
                send(ScorerEvent("point", defender, "dod_out", null))
                reset()
            }
        } else {
            BigButton("Empty raid", Color(0xFF475569), enabled, Modifier.fillMaxWidth()) {
                send(ScorerEvent("raid", raider, "empty", raiderName))
                reset()
            }
        }
    }
    Spacer(Modifier.height(12.dp))
    SideCard("Tackle · $defTeam", defColor, serving = false) {
        SectionLabel(if (tackler != null) "Tackled by: $tackler" else "Tackler (optional)")
        PlayerChips(if (defender == "home") state.homeSquad else state.awaySquad, tackler, defColor) { tackler = if (tackler == it) null else it }
        BigButton(
            if (defendersOn <= 3) "Super tackle  +2" else "Tackle  +1",
            defColor, enabled, Modifier.fillMaxWidth(),
        ) {
            send(ScorerEvent("point", defender, "tackle", tackler))
            reset()
        }
        Spacer(Modifier.height(6.dp))
        Text(
            if (defendersOn <= 3) "$defendersOn defenders on the mat — a tackle now is a super tackle." else "The raider is out; a defender comes back in.",
            fontSize = 11.sp, color = Faint,
        )
    }
    Spacer(Modifier.height(12.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        SmallButton("Technical +1 $raidTeam", enabled, Modifier.weight(1f)) { send(ScorerEvent("point", raider, "technical")) }
        SmallButton("Timeout", enabled, Modifier.weight(0.7f)) { send(ScorerEvent("timeout", raider)) }
        SmallButton("Half time", enabled, Modifier.weight(0.8f), dark = true) { onHalf() }
    }
}

@Composable
private fun LegacyKabaddiPad(state: MatchUiState, enabled: Boolean, send: (ScorerEvent) -> Unit) {
    Text(
        "This match started before mat scoring, so it keeps the original buttons.",
        fontSize = 11.5.sp, color = Faint,
    )
    Spacer(Modifier.height(10.dp))
    listOf("home" to state.team1, "away" to state.team2).forEachIndexed { i, (side, name) ->
        if (i > 0) Spacer(Modifier.height(12.dp))
        val accent = if (side == "home") state.team1Color else state.team2Color
        SideCard(name.ifBlank { side }, accent, serving = false) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                SportLook.scoreButtons("kabaddi").forEach { (label, detail, _) ->
                    BigButton(label, accent, enabled, Modifier.weight(1f), small = true) { send(ScorerEvent("point", side, detail)) }
                }
            }
        }
    }
}

// ─────────────────────────────── shared pieces ───────────────────────────────

@Composable
private fun DoneNote(board: SportBoard, state: MatchUiState) {
    val winner = if (board.setsHome > board.setsAway) state.team1 else state.team2
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color.White).border(1.dp, Line, RoundedCornerShape(16.dp)).padding(18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("$winner has won the match", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text("Tap Finish to lock the result, or Undo to correct the last point.", fontSize = 12.5.sp, color = Muted, textAlign = TextAlign.Center)
    }
}

/** The last few recorded moments, each with its own undo. */
@Composable
private fun RecentEvents(state: MatchUiState, board: SportBoard, enabled: Boolean, onUndo: (Int) -> Unit) {
    SectionLabel("Recorded")
    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color.White).border(1.dp, Line, RoundedCornerShape(16.dp))) {
        board.feed.take(8).forEachIndexed { i, m ->
            if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(Line))
            val accent = when (m.side) { "home" -> state.team1Color; "away" -> state.team2Color; else -> Muted }
            Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(8.dp).clip(CircleShape).background(accent))
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        SportLook.momentLabel(board.sport, m) + (if (m.value > 1) "  +${m.value}" else ""),
                        fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1,
                    )
                    val sub = listOf(
                        when (m.side) { "home" -> state.team1; "away" -> state.team2; else -> "" },
                        m.player, if (m.related.isNotBlank()) "ast ${m.related}" else "",
                    ).filter { it.isNotBlank() }.joinToString(" · ")
                    if (sub.isNotBlank()) Text(sub, fontSize = 11.sp, color = Faint, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                Text(m.line.ifBlank { "${m.homeScore}–${m.awayScore}" }, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Muted)
                Spacer(Modifier.width(10.dp))
                RoundIcon(size = 30, enabled = enabled, onClick = { onUndo(m.sequence) }) {
                    Icon(Icons.Filled.Undo, "Undo this", tint = Muted, modifier = Modifier.size(15.dp))
                }
            }
        }
    }
}

@Composable
private fun SideCard(title: String, accent: Color, serving: Boolean, content: @Composable ColumnScope.() -> Unit) {
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Color.White)
            .border(1.dp, if (serving) accent.copy(alpha = 0.5f) else Line, RoundedCornerShape(18.dp)).padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(accent))
            Spacer(Modifier.width(8.dp))
            Text(title, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
            if (serving) {
                Text("SERVING", fontSize = 9.5.sp, fontWeight = FontWeight.ExtraBold, color = accent, letterSpacing = 0.8.sp)
            }
        }
        Spacer(Modifier.height(10.dp))
        content()
    }
}

@Composable
private fun SideToggle(state: MatchUiState, side: String, onPick: (String) -> Unit) {
    Row(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0xFFE9EEF5)).padding(4.dp)) {
        listOf("home" to state.team1, "away" to state.team2).forEach { (key, name) ->
            val on = key == side
            val bg by animateColorAsState(if (on) Color.White else Color.Transparent, tween(200), label = "sideToggle")
            Box(
                Modifier.weight(1f).clip(RoundedCornerShape(11.dp)).background(bg).pressable { onPick(key) }.padding(vertical = 10.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(name.ifBlank { key }, fontSize = 13.5.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) Ink else Muted, maxLines = 1)
            }
        }
    }
}

@Composable
private fun PlayerChips(squad: List<SquadMember>, selected: String?, accent: Color, onPick: (String) -> Unit) {
    val names = squad.map { it.name }.filter { it.isNotBlank() }.take(15)
    if (names.isEmpty()) return
    ChipRow(names, selected, accent, onPick)
    Spacer(Modifier.height(10.dp))
}

@Composable
private fun ChipRow(items: List<String>, selected: String?, accent: Color = Color(0xFF2563EB), onPick: (String) -> Unit) {
    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        items.forEach { name ->
            val on = name == selected
            Box(
                Modifier.clip(RoundedCornerShape(9.dp))
                    .background(if (on) accent.copy(alpha = 0.14f) else Color(0xFFF6F8FB))
                    .border(1.dp, if (on) accent.copy(alpha = 0.5f) else Line, RoundedCornerShape(9.dp))
                    .pressable { onPick(name) }
                    .padding(horizontal = 11.dp, vertical = 7.dp),
            ) {
                Text(name, fontSize = 12.5.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) accent else Muted, maxLines = 1)
            }
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Faint, modifier = Modifier.padding(bottom = 7.dp))
}

@Composable
private fun BigButton(label: String, color: Color, enabled: Boolean, modifier: Modifier, small: Boolean = false, onClick: () -> Unit) {
    Box(
        modifier.clip(RoundedCornerShape(14.dp)).background(if (enabled) color else color.copy(alpha = 0.4f))
            .pressable(enabled = enabled, onClick = onClick).padding(vertical = if (small) 13.dp else 16.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = if (small) 12.5.sp else 15.sp, fontWeight = FontWeight.ExtraBold, color = Color.White, maxLines = 1)
    }
}

@Composable
private fun StatKey(label: String, color: Color, enabled: Boolean, modifier: Modifier, onClick: () -> Unit) {
    Box(
        modifier.clip(RoundedCornerShape(11.dp)).background(Color(0xFFF1F5F9)).border(1.dp, Line, RoundedCornerShape(11.dp))
            .pressable(enabled = enabled, onClick = onClick).padding(vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, fontSize = 11.5.sp, fontWeight = FontWeight.ExtraBold, color = color, maxLines = 1) }
}

@Composable
private fun SmallButton(label: String, enabled: Boolean, modifier: Modifier = Modifier, dark: Boolean = false, onClick: () -> Unit) {
    Box(
        modifier.clip(RoundedCornerShape(12.dp))
            .background(if (dark) Ink.copy(alpha = if (enabled) 1f else 0.4f) else Color(0xFFF1F5F9))
            .border(1.dp, if (dark) Color.Transparent else Line, RoundedCornerShape(12.dp))
            .pressable(enabled = enabled, onClick = onClick).padding(horizontal = 12.dp, vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = if (dark) Color.White else if (enabled) Ink else Faint, maxLines = 1)
    }
}

@Composable
private fun Toggle(label: String, on: Boolean, accent: Color, onClick: () -> Unit) {
    Row(
        Modifier.clip(RoundedCornerShape(10.dp)).background(if (on) accent.copy(alpha = 0.12f) else Color(0xFFF6F8FB))
            .border(1.dp, if (on) accent.copy(alpha = 0.5f) else Line, RoundedCornerShape(10.dp))
            .pressable(onClick = onClick).padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(14.dp).clip(RoundedCornerShape(4.dp)).background(if (on) accent else Color.White).border(1.dp, if (on) accent else Faint, RoundedCornerShape(4.dp)))
        Spacer(Modifier.width(8.dp))
        Text(label, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = if (on) accent else Muted)
    }
}

@Composable
private fun Pill(label: String, enabled: Boolean, icon: Boolean = false, onClick: () -> Unit) {
    Row(
        Modifier.clip(RoundedCornerShape(20.dp)).background(Color(0xFFF1F5F9)).pressable(enabled = enabled, onClick = onClick)
            .padding(horizontal = 13.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon) {
            Icon(Icons.Filled.Undo, null, tint = Ink, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(5.dp))
        }
        Text(label, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (enabled) Ink else Faint)
    }
}

@Composable
private fun RoundIcon(size: Int = 36, enabled: Boolean = true, onClick: () -> Unit, content: @Composable () -> Unit) {
    Box(
        Modifier.size(size.dp).clip(CircleShape).background(Color(0xFFEFF2F7)).pressable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { content() }
}
