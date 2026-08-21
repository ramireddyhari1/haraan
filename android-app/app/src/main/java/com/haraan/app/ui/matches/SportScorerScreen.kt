package com.haraan.app.ui.matches

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Undo
import androidx.compose.material3.Icon
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

/**
 * The scorer for volleyball, basketball, kabaddi, tennis and table tennis.
 *
 * It posts what happened — "a point to the home side, worth three" — and re-reads the board
 * the server derives from that. It never sends a score, so a double-tap, a dropped request
 * or a phone that lost signal mid-rally cannot leave a scoreboard that disagrees with its
 * own history. Undo is the same idea in reverse: drop the last event, let the server replay.
 *
 * The keypad is the sport's own. Basketball gets 2 / 3 / free throw, kabaddi gets raid /
 * tackle / bonus / all out, and the rally sports get one big Point button per side, because
 * that is genuinely all a volleyball rally produces.
 */
@Composable
fun SportScorerScreen(
    state: MatchUiState,
    board: SportBoard,
    /** Posts one point. Returns the refreshed board, or null when the call failed. */
    onPoint: suspend (side: String, detail: String, player: String?) -> Boolean,
    /** Drops that side's last point. */
    onUndo: suspend (side: String) -> Boolean,
    /**
     * Ends the match.
     *
     * Without this a rally/points match could be STARTED but never finished — the first
     * recorded point makes it Live, and nothing would ever take it out of the live feed.
     */
    onFinish: (suspend () -> Boolean)? = null,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val ink = Color(0xFF0F172A)
    val muted = Color(0xFF64748B)
    val faint = Color(0xFF94A3B8)
    val scope = rememberCoroutineScope()
    val view = LocalView.current

    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf("") }
    var confirmFinish by remember { mutableStateOf(false) }
    // Who gets credited for the next point. Optional on purpose: a fast rally sport is
    // scored by somebody standing courtside, and forcing a name per point would mean either
    // a slower scorer or invented names.
    var homePlayer by remember { mutableStateOf<String?>(null) }
    var awayPlayer by remember { mutableStateOf<String?>(null) }

    fun post(side: String, detail: String) {
        if (busy) return
        busy = true
        error = ""
        hapticConfirm(view)
        scope.launch {
            val ok = onPoint(side, detail, if (side == "home") homePlayer else awayPlayer)
            if (!ok) error = "Couldn't record that point. Check your connection."
            busy = false
        }
    }

    fun undo(side: String) {
        if (busy) return
        busy = true
        error = ""
        hapticTick(view)
        scope.launch {
            val ok = onUndo(side)
            if (!ok) error = "Nothing to undo on that side."
            busy = false
        }
    }

    Column(
        modifier
            .fillMaxSize()
            .background(Color(0xFFF4F7FB))
            .statusBarsPadding(),
    ) {
        // ── Bar ──
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFEFF2F7))
                    .pressable(onClick = onDone),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Close", tint = ink, modifier = Modifier.size(18.dp)) }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Scoring", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = ink)
                Text(SportLook.displayName(board.sport), fontSize = 11.5.sp, color = faint)
            }
            if (onFinish != null) {
                Box(
                    Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(Color(0xFFF1F5F9))
                        .pressable(enabled = !busy) { confirmFinish = true }
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                ) {
                    Text("Finish", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = ink)
                }
            }
        }

        // Ending a match is one-way — it freezes the result and drops it out of the live
        // feed — so it asks first.
        if (confirmFinish) {
            androidx.compose.material3.AlertDialog(
                onDismissRequest = { if (!busy) confirmFinish = false },
                title = { Text("Finish this match?", fontWeight = FontWeight.Bold) },
                text = { Text("The score is frozen as it stands and the match leaves the live feed.") },
                confirmButton = {
                    androidx.compose.material3.TextButton(
                        enabled = !busy,
                        onClick = {
                            busy = true
                            scope.launch {
                                val ok = onFinish?.invoke() ?: false
                                busy = false
                                confirmFinish = false
                                if (ok) onDone() else error = "Couldn't finish the match."
                            }
                        },
                    ) { Text("Finish", fontWeight = FontWeight.Bold) }
                },
                dismissButton = {
                    androidx.compose.material3.TextButton(enabled = !busy, onClick = { confirmFinish = false }) {
                        Text("Keep scoring", color = muted)
                    }
                },
                containerColor = Color.White,
            )
        }

        // ── The live board, so the scorer never has to leave to check it ──
        Row(
            Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(Color.White)
                .border(1.dp, Color(0xFFE6EBF2), RoundedCornerShape(16.dp))
                .padding(vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            ScoreHalf(state.team1.ifBlank { "Home" }, liveFigure(board, true), Modifier.weight(1f), ink, faint)
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    if (board.isSetSport) "${board.setNoun} ${board.sets.size + 1}" else board.periodLabel.ifBlank { "Live" },
                    fontSize = 11.sp, fontWeight = FontWeight.Bold, color = faint,
                )
                if (board.isSetSport && board.target > 0) {
                    Spacer(Modifier.height(3.dp))
                    Text("to ${board.target}", fontSize = 10.5.sp, color = faint)
                }
            }
            ScoreHalf(state.team2.ifBlank { "Away" }, liveFigure(board, false), Modifier.weight(1f), ink, faint)
        }

        if (error.isNotBlank()) {
            Spacer(Modifier.height(10.dp))
            Text(
                error,
                fontSize = 12.5.sp, color = Color(0xFFDC2626), textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            )
        }

        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp)) {
            SideKeypad(
                teamName = state.team1.ifBlank { "Home" },
                accent = state.team1Color,
                squad = state.homeSquad,
                selected = homePlayer,
                onSelect = { homePlayer = if (homePlayer == it) null else it },
                buttons = SportLook.scoreButtons(board.sport),
                enabled = !busy,
                onPoint = { detail -> post("home", detail) },
                onUndo = { undo("home") },
                ink = ink, muted = muted, faint = faint,
            )
            Spacer(Modifier.height(14.dp))
            SideKeypad(
                teamName = state.team2.ifBlank { "Away" },
                accent = state.team2Color,
                squad = state.awaySquad,
                selected = awayPlayer,
                onSelect = { awayPlayer = if (awayPlayer == it) null else it },
                buttons = SportLook.scoreButtons(board.sport),
                enabled = !busy,
                onPoint = { detail -> post("away", detail) },
                onUndo = { undo("away") },
                ink = ink, muted = muted, faint = faint,
            )
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun ScoreHalf(name: String, figure: String, modifier: Modifier, ink: Color, faint: Color) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            name, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = faint,
            maxLines = 1, overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.height(4.dp))
        Text(figure, fontSize = 32.sp, fontWeight = FontWeight.Black, color = ink)
    }
}

/**
 * One side's keypad: an optional scorer picker, the sport's point buttons, and an undo that
 * only reaches this side's own points.
 */
@Composable
private fun SideKeypad(
    teamName: String,
    accent: Color,
    squad: List<SquadMember>,
    selected: String?,
    onSelect: (String) -> Unit,
    buttons: List<Triple<String, String, Int>>,
    enabled: Boolean,
    onPoint: (String) -> Unit,
    onUndo: () -> Unit,
    ink: Color,
    muted: Color,
    faint: Color,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Color(0xFFE6EBF2), RoundedCornerShape(18.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(accent))
            Spacer(Modifier.width(8.dp))
            Text(
                teamName, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = ink,
                maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f),
            )
            Box(
                Modifier.size(34.dp).clip(CircleShape).background(Color(0xFFF1F5F9))
                    .pressable(enabled = enabled, onClick = onUndo),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.Undo, "Undo", tint = muted, modifier = Modifier.size(17.dp)) }
        }

        // Scorer picker — tap a name to credit the next point, tap again to clear it.
        if (squad.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            Text(
                if (selected != null) "Next point: $selected" else "Credit a player (optional)",
                fontSize = 11.5.sp, color = faint,
            )
            Spacer(Modifier.height(8.dp))
            FlowRowSimple(squad.map { it.name }.filter { it.isNotBlank() }.take(12)) { name ->
                val on = name == selected
                Box(
                    Modifier
                        .clip(RoundedCornerShape(9.dp))
                        .background(if (on) accent.copy(alpha = 0.14f) else Color(0xFFF6F8FB))
                        .border(1.dp, if (on) accent.copy(alpha = 0.45f) else Color(0xFFE6EBF2), RoundedCornerShape(9.dp))
                        .pressable { onSelect(name) }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                ) {
                    Text(
                        name,
                        fontSize = 12.sp,
                        fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                        color = if (on) accent else muted,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }

        Spacer(Modifier.height(14.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            buttons.forEach { (label, detail, _) ->
                Box(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(14.dp))
                        .background(if (enabled) accent else accent.copy(alpha = 0.4f))
                        .pressable(enabled = enabled) { onPoint(detail) }
                        .padding(vertical = 16.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        label,
                        fontSize = if (buttons.size > 2) 13.sp else 16.sp,
                        fontWeight = FontWeight.ExtraBold,
                        color = Color.White,
                        maxLines = 1,
                    )
                }
            }
        }
    }
}

/** A two-per-row chip wrap without pulling in an experimental FlowRow. */
@Composable
private fun FlowRowSimple(items: List<String>, item: @Composable (String) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        items.chunked(3).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                row.forEach { item(it) }
            }
        }
    }
}

/** What each side shows on the scorer: rally points mid-set, or the running total. */
private fun liveFigure(board: SportBoard, home: Boolean): String = when {
    board.points != null -> if (home) board.points.first else board.points.second
    board.current != null -> "${if (home) board.current.first else board.current.second}"
    else -> "${board.periods.sumOf { if (home) it.first else it.second }}"
}
