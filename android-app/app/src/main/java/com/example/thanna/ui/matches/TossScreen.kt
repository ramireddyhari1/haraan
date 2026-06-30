package com.example.thanna.ui.matches

import android.widget.Toast
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
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
import androidx.compose.runtime.LaunchedEffect
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.MatchRepository
import com.example.thanna.data.SquadMember
import com.example.thanna.data.TokenStore
import kotlinx.coroutines.delay
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
)

private enum class TossPhase { FLIP, DECIDE, LINEUP, STARTING }

/** Colour per side so the two teams read apart at a glance (A blue, B amber). */
private fun sideColor(team: Int) = if (team == 2) TossAmber else TossBlue

private fun playerRef(member: SquadMember?): String? {
    if (member == null) return null
    val id = member.id.takeIf { it.isNotBlank() && !it.equals("null", true) }
    return (id ?: member.name).takeIf { it.isNotBlank() && !it.equals("null", true) }
}

/**
 * Post-create toss ritual: a quick coin flip (skippable), the won-toss bat/bowl choice,
 * and the opening lineup — striker, non-striker, bowler. Sends a complete `start` score
 * action so the match goes Live with the right batting side and players (no auto-guessing).
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
) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { MatchRepository() }

    var phase by remember { mutableStateOf(TossPhase.FLIP) }
    var winner by remember { mutableStateOf(0) }            // 1 = Team A, 2 = Team B
    var battingTeam by remember { mutableStateOf(1) }
    var decisionWord by remember { mutableStateOf("Bat") }

    val teamAName = teamA.ifBlank { "Team A" }
    val teamBName = teamB.ifBlank { "Team B" }
    fun nameOf(team: Int) = if (team == 2) teamBName else teamAName

    // Auto-resolve the flip after a short spin.
    LaunchedEffect(Unit) {
        delay(1700)
        if (winner == 0) {
            winner = if (Random.nextBoolean()) 1 else 2
            phase = TossPhase.DECIDE
        }
    }

    fun skipFlip() {
        if (winner == 0) winner = if (Random.nextBoolean()) 1 else 2
        phase = TossPhase.DECIDE
    }

    fun choose(bat: Boolean) {
        decisionWord = if (bat) "Bat" else "Bowl"
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
        // Header — Skip on the flip, a way out everywhere else.
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Toss", color = TossText1, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            val action = if (phase == TossPhase.FLIP) "Skip" else "Later"
            Text(
                action,
                color = TossBlue,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .clickable { if (phase == TossPhase.FLIP) skipFlip() else onCancel() }
                    .padding(horizontal = 10.dp, vertical = 6.dp)
            )
        }

        when (phase) {
            TossPhase.FLIP -> FlipStage(teamAName, teamBName)
            TossPhase.DECIDE -> DecideStage(winnerName = nameOf(winner), winner = winner, onBat = { choose(true) }, onBowl = { choose(false) })
            TossPhase.LINEUP -> LineupStage(
                battingTeam = battingTeam,
                battingName = nameOf(battingTeam),
                bowlingName = nameOf(if (battingTeam == 1) 2 else 1),
                battingSquad = if (battingTeam == 2) squadB else squadA,
                bowlingSquad = if (battingTeam == 2) squadA else squadB,
                tossLine = "${nameOf(winner)} won the toss & chose to $decisionWord.",
                onStart = { s, ns, b -> start(s, ns, b) },
            )
            TossPhase.STARTING -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = TossBlue)
            }
        }
    }
}

@Composable
private fun ColumnScope.FlipStage(teamA: String, teamB: String) {
    Column(
        modifier = Modifier.fillMaxWidth().weight(1f).padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        val spin = rememberInfiniteTransition(label = "coin")
        val rot by spin.animateFloat(
            initialValue = 0f, targetValue = 360f,
            animationSpec = infiniteRepeatable(tween(650, easing = LinearEasing), RepeatMode.Restart),
            label = "rot"
        )
        Box(
            modifier = Modifier
                .size(148.dp)
                .graphicsLayer { rotationY = rot }
                .clip(CircleShape)
                .background(Brush.verticalGradient(listOf(Color(0xFFFCD34D), Color(0xFFF59E0B))))
                .border(BorderStroke(4.dp, Color(0xFFFDE68A)), CircleShape),
            contentAlignment = Alignment.Center
        ) {
            Text("🏏", fontSize = 44.sp)
        }
        Spacer(Modifier.height(24.dp))
        Text("Flipping the coin…", color = TossText1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(6.dp))
        Text("$teamA   vs   $teamB", color = TossText3, fontSize = 14.sp)
    }
}

@Composable
private fun ColumnScope.DecideStage(winnerName: String, winner: Int, onBat: () -> Unit, onBowl: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxWidth().weight(1f).padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier.size(72.dp).clip(CircleShape).background(sideColor(winner)),
            contentAlignment = Alignment.Center
        ) {
            Text(winnerName.take(1).uppercase(), color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.Black)
        }
        Spacer(Modifier.height(16.dp))
        Text("🏆  $winnerName won the toss", color = TossText1, fontSize = 19.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
        Spacer(Modifier.height(6.dp))
        Text("What will they do?", color = TossText2, fontSize = 14.sp)
        Spacer(Modifier.height(28.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            TossChoiceButton("Bat", TossBlue, Modifier.weight(1f), onBat)
            TossChoiceButton("Bowl", TossGreen, Modifier.weight(1f), onBowl)
        }
    }
}

@Composable
private fun TossChoiceButton(label: String, color: Color, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(14.dp))
            .background(color)
            .clickable(onClick = onClick)
            .padding(vertical = 18.dp, horizontal = 28.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(label, color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
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

        PlayerPickRow("Striker", battingName, battingSquad, selected = striker, exclude = nonStriker) { striker = it }
        Spacer(Modifier.height(18.dp))
        PlayerPickRow("Non-striker", battingName, battingSquad, selected = nonStriker, exclude = striker) { nonStriker = it }
        Spacer(Modifier.height(18.dp))
        PlayerPickRow("Opening bowler", bowlingName, bowlingSquad, selected = bowler, exclude = null) { bowler = it }

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
            Text("Start Match", color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
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
                "No players added — a default name will be used.",
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
