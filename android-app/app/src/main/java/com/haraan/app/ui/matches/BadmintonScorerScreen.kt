package com.haraan.app.ui.matches

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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

// Palette mirrors the CREX light theme used across the create wizard / scorers.
private val Bg = Color(0xFFEBEBF0)
private val Surface = Color(0xFFFFFFFF)
private val Blue = Color(0xFF2563EB)
private val Green = Color(0xFF16A34A)
private val Text1 = Color(0xFF111827)
private val Text2 = Color(0xFF5A5A6A)
private val Text3 = Color(0xFF9A9AA8)
private val Stroke = Color(0xFFE2E8F0)

/** What the badminton scorer needs to open (mirrors [FootballScorerSetup]). */
data class BadmintonScorerSetup(
    val matchId: String,
    val teamA: String,
    val teamB: String,
    val bestOf: Int = 3,
    val formatLabel: String = "",
    val isPrivate: Boolean = false,
    val joinCode: String = "",
    // Seed games won — 0/0 fresh, or the current tally when resuming (in-game points reset).
    val initialGamesHome: Int = 0,
    val initialGamesAway: Int = 0,
)

/**
 * A simple badminton scorer. Points are tracked per game on-screen (first to 21,
 * win by 2, hard cap 30); when a game is won it's added to the games tally and the
 * points reset. Only **games won** are persisted (pushed as the two side scores),
 * so the feed reads "2–1" like a real badminton result. The match completes when a
 * side wins the majority of [BadmintonScorerSetup.bestOf] games.
 *
 * First cut: no per-rally serve/side tracking, and live in-game points are local
 * (not persisted across restarts) — the games tally is the durable state.
 */
@Composable
fun BadmintonScorerScreen(
    setup: BadmintonScorerSetup,
    pushGames: suspend (side: String, games: Int) -> Unit,
    finishMatch: suspend () -> Unit,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var gamesHome by remember { mutableIntStateOf(setup.initialGamesHome) }
    var gamesAway by remember { mutableIntStateOf(setup.initialGamesAway) }
    var pointsHome by remember { mutableIntStateOf(0) }
    var pointsAway by remember { mutableIntStateOf(0) }
    var finishing by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    val gamesToWin = setup.bestOf / 2 + 1
    val matchOver = gamesHome >= gamesToWin || gamesAway >= gamesToWin

    fun pushSide(side: String, value: Int) {
        scope.launch { runCatching { pushGames(side, value) } }
    }

    // Award a point; if it closes the game (21+, win by 2, or 30 cap), roll it into
    // the games tally, persist, and reset the points for the next game.
    fun addPoint(home: Boolean) {
        if (matchOver) return
        val h = if (home) pointsHome + 1 else pointsHome
        val a = if (home) pointsAway else pointsAway + 1
        val gameWon = (h >= 21 || a >= 21) && (kotlin.math.abs(h - a) >= 2) || h == 30 || a == 30
        if (gameWon) {
            if (h > a) {
                gamesHome += 1; pushSide("home", gamesHome)
            } else {
                gamesAway += 1; pushSide("away", gamesAway)
            }
            pointsHome = 0
            pointsAway = 0
        } else {
            pointsHome = h
            pointsAway = a
        }
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
                    .clickable(enabled = !finishing, onClick = onDone),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Match scorer", color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Text(setup.formatLabel.ifBlank { "Badminton" }, color = Text3, fontSize = 12.sp)
            }
        }

        Column(
            modifier = Modifier.weight(1f).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            GameCard(
                team = setup.teamA,
                games = gamesHome,
                points = pointsHome,
                enabled = !matchOver,
                onPoint = { addPoint(home = true) },
            )
            GameCard(
                team = setup.teamB,
                games = gamesAway,
                points = pointsAway,
                enabled = !matchOver,
                onPoint = { addPoint(home = false) },
            )
            Text(
                if (matchOver) "Match over — tap finish to confirm."
                else "First to 21 (win by 2, cap 30) takes the game · best of ${setup.bestOf}.",
                color = Text3, fontSize = 12.sp,
            )
        }

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
                            runCatching { pushGames("home", gamesHome) }
                            runCatching { pushGames("away", gamesAway) }
                            runCatching { finishMatch() }
                            finishing = false
                            onDone()
                        }
                    }
                },
                enabled = !finishing,
                modifier = Modifier.fillMaxWidth().height(52.dp),
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
                    Text(resultLabel(setup, gamesHome, gamesAway), fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

private fun resultLabel(setup: BadmintonScorerSetup, gamesHome: Int, gamesAway: Int): String = when {
    gamesHome > gamesAway -> "Finish · ${setup.teamA} lead $gamesHome–$gamesAway"
    gamesAway > gamesHome -> "Finish · ${setup.teamB} lead $gamesAway–$gamesHome"
    else -> "Finish · $gamesHome–$gamesAway"
}

@Composable
private fun GameCard(
    team: String,
    games: Int,
    points: Int,
    enabled: Boolean,
    onPoint: () -> Unit,
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
        Box(
            Modifier.size(40.dp).clip(CircleShape).background(Bg),
            contentAlignment = Alignment.Center,
        ) {
            Text(teamShortCode(team), color = Text2, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(team, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1)
            Text("$games games won", color = Text3, fontSize = 12.sp)
        }
        Spacer(Modifier.width(8.dp))
        Box(Modifier.width(52.dp), contentAlignment = Alignment.Center) {
            Text("$points", color = Text1, fontSize = 30.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
        }
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(CircleShape)
                .background(if (enabled) Blue else Bg)
                .clickable(enabled = enabled, onClick = onPoint),
            contentAlignment = Alignment.Center,
        ) {
            Text("+", color = if (enabled) Color.White else Text3.copy(alpha = 0.5f), fontSize = 26.sp, fontWeight = FontWeight.Bold)
        }
    }
}
