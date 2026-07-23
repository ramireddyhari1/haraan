package com.haraan.app.ui.matches

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.create.emblemDrawableFor
import kotlinx.coroutines.launch

// Palette mirrors the CREX light theme used across the create wizard / match screens.
private val Bg = Color(0xFFEBEBF0)
private val Surface = Color(0xFFFFFFFF)
private val Blue = Color(0xFF2563EB)
private val Green = Color(0xFF16A34A)
private val Text1 = Color(0xFF111827)
private val Text2 = Color(0xFF5A5A6A)
private val Text3 = Color(0xFF9A9AA8)
private val Stroke = Color(0xFFE2E8F0)

/** Everything the football scorer needs to open, bundled at create time (mirrors [TossSetup]). */
data class FootballScorerSetup(
    val matchId: String,
    val teamA: String,
    val teamB: String,
    val teamAEmblem: String = "",
    val teamBEmblem: String = "",
    val formatLabel: String = "",
    val isPrivate: Boolean = false,
    val joinCode: String = "",
    // Seed the tallies — 0/0 for a fresh match, or the current score when resuming.
    val initialHome: Int = 0,
    val initialAway: Int = 0,
)

/**
 * A deliberately-simple goals scorer for football matches. Each side has a −/+
 * tally; every change is pushed to the backend (idempotent absolute score) so the
 * live feed tracks it. "Full time" completes the match and hands back the result.
 *
 * This is the football counterpart to the cricket toss→keypad flow. It's a first
 * cut: goals only, no cards/subs/timeline yet.
 */
@Composable
fun FootballScorerScreen(
    setup: FootballScorerSetup,
    pushScore: suspend (side: String, score: Int) -> Unit,
    finishMatch: suspend () -> Unit,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var home by remember { mutableIntStateOf(setup.initialHome) }
    var away by remember { mutableIntStateOf(setup.initialAway) }
    var finishing by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    // Push a side's new total to the backend. Fire-and-forget: the on-screen tally
    // is the source of truth, and the endpoint is idempotent so a dropped call
    // self-heals on the next tap.
    fun push(side: String, value: Int) {
        scope.launch { runCatching { pushScore(side, value) } }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        // Top bar
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Surface)
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(Color(0xFFF1F5F9))
                    .clickable(enabled = !finishing, onClick = onDone),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Match scorer", color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Text(
                    setup.formatLabel.ifBlank { "Football" },
                    color = Text3, fontSize = 12.sp,
                )
            }
            LivePill()
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            ScoreCard(
                team = setup.teamA,
                emblemKey = setup.teamAEmblem,
                score = home,
                onMinus = { if (home > 0) { home--; push("home", home) } },
                onPlus = { home++; push("home", home) },
            )
            ScoreCard(
                team = setup.teamB,
                emblemKey = setup.teamBEmblem,
                score = away,
                onMinus = { if (away > 0) { away--; push("away", away) } },
                onPlus = { away++; push("away", away) },
            )
        }

        // Finish
        Box(
            Modifier
                .fillMaxWidth()
                .background(Surface)
                .navigationBarsPadding()
                .padding(16.dp)
        ) {
            Button(
                onClick = {
                    if (!finishing) {
                        finishing = true
                        scope.launch {
                            // Make sure the final scoreline is persisted before completing.
                            runCatching { pushScore("home", home) }
                            runCatching { pushScore("away", away) }
                            runCatching { finishMatch() }
                            finishing = false
                            onDone()
                        }
                    }
                },
                enabled = !finishing,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Green,
                    contentColor = Color.White,
                    disabledContainerColor = Green.copy(alpha = 0.4f),
                    disabledContentColor = Color.White.copy(alpha = 0.8f),
                ),
            ) {
                if (finishing) {
                    CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp, color = Color.White)
                } else {
                    Text(resultLabel(setup, home, away), fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

private fun resultLabel(setup: FootballScorerSetup, home: Int, away: Int): String = when {
    home > away -> "Full time · ${setup.teamA} win $home–$away"
    away > home -> "Full time · ${setup.teamB} win $away–$home"
    else -> "Full time · $home–$away draw"
}

@Composable
private fun ScoreCard(
    team: String,
    emblemKey: String,
    score: Int,
    onMinus: () -> Unit,
    onPlus: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Crest(emblemKey)
        Spacer(Modifier.width(12.dp))
        Text(
            team,
            color = Text1,
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            modifier = Modifier.weight(1f),
        )
        Spacer(Modifier.width(8.dp))
        TallyButton("−", enabled = score > 0, onClick = onMinus)
        Box(
            modifier = Modifier.width(56.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text("$score", color = Text1, fontSize = 30.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
        }
        TallyButton("+", enabled = true, onClick = onPlus)
    }
}

@Composable
private fun TallyButton(symbol: String, enabled: Boolean, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .size(48.dp)
            .clip(CircleShape)
            .background(if (enabled) Blue.copy(alpha = if (symbol == "+") 1f else 0.12f) else Bg)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            symbol,
            color = when {
                symbol == "+" && enabled -> Color.White
                enabled -> Blue
                else -> Text3.copy(alpha = 0.5f)
            },
            fontSize = 26.sp,
            fontWeight = FontWeight.Bold,
        )
    }
}

@Composable
private fun Crest(emblemKey: String) {
    val resId = emblemDrawableFor(emblemKey)
    Box(
        modifier = Modifier.size(44.dp).clip(CircleShape).background(Bg),
        contentAlignment = Alignment.Center,
    ) {
        if (resId != null) {
            Image(
                painter = painterResource(resId),
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        }
    }
}

/**
 * Read-only match view for the simple-scored sports (football goals, badminton
 * games) — where any tap lands when the viewer can't score it (someone else's
 * match, or one that's finished). Deliberately minimal: teams, the scoreline, and
 * the match state. No cricket tabs.
 */
@Composable
fun SimpleMatchView(
    state: com.haraan.app.ui.matches.MatchUiState,
    onBack: () -> Unit,
    onConfirm: suspend () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val isBadminton = state.sport == "badminton"
    val sportLabel = if (isBadminton) "Badminton" else "Football"
    val home = state.score.toIntOrNull() ?: 0
    val away = state.opponentScore.toIntOrNull() ?: 0
    val completed = !state.isLive && state.status.trim().lowercase().let {
        it == "completed" || it.startsWith("full")
    }
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Surface)
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFF1F5F9))
                    .clickable(onClick = onBack),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(sportLabel, color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Text(state.competition.ifBlank { "Match" }, color = Text3, fontSize = 12.sp)
            }
            if (state.isLive) LivePill()
        }

        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Column(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(18.dp))
                    .background(Surface)
                    .border(1.dp, Stroke, RoundedCornerShape(18.dp))
                    .padding(18.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                FootballTeamRow(state.team1FullName.ifBlank { state.team1 }, home, leading = home > away && completed)
                Box(Modifier.fillMaxWidth().height(1.dp).background(Stroke))
                FootballTeamRow(state.team2FullName.ifBlank { state.team2 }, away, leading = away > home && completed)
            }
            val endLabel = if (isBadminton) "Result" else "Full time"
            val statusText = when {
                completed && home > away -> "$endLabel · ${state.team1FullName.ifBlank { state.team1 }} won"
                completed && away > home -> "$endLabel · ${state.team2FullName.ifBlank { state.team2 }} won"
                completed -> "$endLabel · draw"
                state.isLive -> "Live now"
                else -> state.status.ifBlank { "Scheduled" }
            }
            Text(statusText, color = Text2, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
            if (state.venue.isNotBlank()) {
                Text(state.venue, color = Text3, fontSize = 13.sp)
            }
            ResultVerificationBar(state = state, onConfirm = onConfirm)
        }
    }
}

@Composable
private fun FootballTeamRow(name: String, score: Int, leading: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(40.dp).clip(CircleShape).background(Bg),
            contentAlignment = Alignment.Center,
        ) {
            Text(teamShortCode(name), color = Text2, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.width(12.dp))
        Text(
            name,
            color = Text1,
            fontSize = 16.sp,
            fontWeight = if (leading) FontWeight.Bold else FontWeight.Medium,
            maxLines = 1,
            modifier = Modifier.weight(1f),
        )
        Spacer(Modifier.width(8.dp))
        Text("$score", color = Text1, fontSize = 26.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun LivePill() {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(Color(0xFFFDECEC))
            .padding(horizontal = 10.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(7.dp).clip(CircleShape).background(Color(0xFFDC2626)))
        Spacer(Modifier.width(6.dp))
        Text("LIVE", color = Color(0xFFDC2626), fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}
