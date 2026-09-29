package com.haraan.app.ui.matches.tabs

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.MatchInsights
import com.haraan.app.data.MatchRepository
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchUiState

/**
 * Insights — what the ball log says about how this match went.
 *
 * The screen is deliberately in two halves, and the split is the point:
 *
 *  · The FIGURES are computed on the server by replaying every delivery. They are the same
 *    arithmetic as the scorecard, they are correct whether or not any model is reachable,
 *    and they are what the tab is really for.
 *  · The WRITTEN READ is a model's take on those figures. It is clearly labelled as such,
 *    it sits BELOW the numbers rather than above them, and its absence costs nothing — the
 *    tab is complete without it.
 *
 * That ordering is not decoration. Put generated prose on top and it becomes the thing the
 * reader trusts; put it under the numbers it describes and it stays what it is — a summary
 * of facts already on screen, checkable against them.
 */
@Composable
fun InsightsTab(matchId: String, state: MatchUiState, modifier: Modifier = Modifier) {
    var insights by remember(matchId) { mutableStateOf<MatchInsights?>(null) }
    var ground by remember(matchId) { mutableStateOf<com.haraan.app.data.GroundInsights?>(null) }
    var loading by remember(matchId) { mutableStateOf(true) }

    // Fetched once, not with the score: a ground's record does not change ball to ball,
    // and re-pulling a satellite tile every over would be a waste of the match's data.
    val ctx = androidx.compose.ui.platform.LocalContext.current
    LaunchedEffect(matchId) {
        ground = MatchRepository().fetchGround(matchId, com.haraan.app.data.TokenStore.getSignedInToken(ctx))
    }

    // Re-fetched when the score moves, so a live match's insights follow the match rather
    // than freezing at whatever the state was when the tab first opened.
    var lock by remember(matchId) { mutableStateOf<com.haraan.app.data.membership.InsightsLock?>(null) }
    LaunchedEffect(matchId, state.score, state.overs) {
        loading = insights == null
        when (val result = MatchRepository().fetchInsights(matchId, com.haraan.app.data.TokenStore.getSignedInToken(ctx))) {
            is com.haraan.app.data.CricketInsightsResult.Ready -> { insights = result.data; lock = null }
            is com.haraan.app.data.CricketInsightsResult.Locked -> { insights = null; lock = result.lock }
            // A dropped refetch keeps what's on screen.
            com.haraan.app.data.CricketInsightsResult.Unavailable -> Unit
        }
        loading = false
    }

    // Not on the member's plan for cricket: the server's reason and the one way forward.
    lock?.let { l ->
        LazyColumn(modifier = modifier.fillMaxSize().background(CrexColors.Background)) {
            item(key = "locked") { com.haraan.app.ui.membership.InsightsLockedPanel(l.message, l.code) }
            ground?.let { known -> item(key = "ground") { Box(Modifier.padding(horizontal = 16.dp)) { GroundInsightsCard(known) } } }
        }
        return
    }

    val data = insights
    if (data == null || data.innings.isEmpty()) {
        // The ground still has something to say about a match nobody has scored yet —
        // where it is, and what has happened there before.
        val known = ground
        if (known != null) {
            LazyColumn(
                modifier = modifier
                    .fillMaxSize()
                    .background(CrexColors.Background)
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
                contentPadding = PaddingValues(top = 14.dp, bottom = 28.dp),
            ) {
                item(key = "ground") { GroundInsightsCard(known) }
            }
            return
        }
        InsightsPlaceholder(loading = loading, modifier = modifier)
        return
    }

    // THE BOARD.
    //
    // Seven sections, one grammar: a title, a side switch where the section is per side,
    // one visual on a white card. The order is the order a player asks in — how did the
    // match go, who batted with whom, where did the runs go, how did we get out, what
    // were the runs made of, how has each player been going, and what is this ground like.
    //
    // What is NOT here is deliberate: no written headline, no generated read, no second
    // copy of the score the header already shows, no caption explaining a chart. Shot
    // types and spin-versus-pace are absent because the scorer records neither.
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(BoardPage)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(22.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 40.dp),
    ) {
        item(key = "progress") {
            Column(Modifier.arrive("progress-$matchId")) { MatchProgressSection(matchId, data.innings, state) }
        }
        if (data.innings.any { it.partnerships.isNotEmpty() }) {
            item(key = "stands") {
                Column(Modifier.arrive("stands-$matchId")) { PartnershipsSection(matchId, data.innings, state) }
            }
        }
        item(key = "wagon") {
            Column(Modifier.arrive("wagon-$matchId")) { WagonSection(matchId, data.innings, state) }
        }
        // Shots: shown when a stroke has been named, or when this match's scorer is asked
        // for them — then an empty card says what will fill it.
        if (data.innings.any { it.shotTypes.isNotEmpty() } || state.shotTypes) {
            item(key = "shots") {
                Column(Modifier.arrive("shots-$matchId")) { ShotsSection(matchId, data.innings, state) }
            }
        }
        if (state.inningsCards.any { c -> c.batters.any { it.out } }) {
            item(key = "wickets") {
                Column(Modifier.arrive("wickets-$matchId")) { WicketsSection(matchId, state.inningsCards, state) }
            }
        }
        item(key = "scoring") {
            Column(Modifier.arrive("scoring-$matchId")) { ScoringSection(matchId, data.innings, state) }
        }
        if (state.homeSquad.isNotEmpty() || state.awaySquad.isNotEmpty()) {
            item(key = "form") {
                AnalyseSection(
                    team1Name = state.team1FullName.ifBlank { state.team1 },
                    team2Name = state.team2FullName.ifBlank { state.team2 },
                    team1Squad = state.homeSquad,
                    team2Squad = state.awaySquad,
                )
            }
        }
        ground?.let { known ->
            item(key = "ground") {
                Column {
                    SectionHead("Ground insights")
                    GroundInsightsCard(known, thisInnings = data.innings.firstOrNull()?.runs ?: 0)
                }
            }
        }
    }
}

@Composable
private fun InsightsPlaceholder(loading: Boolean, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(24.dp),
        contentAlignment = Alignment.Center,
    ) {
        if (loading) {
            CircularProgressIndicator(
                modifier = Modifier.size(22.dp),
                strokeWidth = 2.dp,
                color = CrexColors.AccentBlue,
            )
        } else {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    "Nothing to read yet",
                    color = CrexColors.TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    "Insights appear once the innings has enough deliveries to say something about.",
                    color = CrexColors.TextSecondary,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}
