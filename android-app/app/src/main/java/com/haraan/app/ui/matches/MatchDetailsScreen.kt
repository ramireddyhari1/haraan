package com.haraan.app.ui.matches

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.ui.components.AutoRefresh
import com.haraan.app.ui.matches.tabs.InfoTab
import com.haraan.app.ui.matches.tabs.CommentaryTab
import com.haraan.app.ui.matches.tabs.LiveTab
import com.haraan.app.ui.matches.tabs.MvpTab
import com.haraan.app.ui.matches.tabs.ScorecardTab
import kotlinx.coroutines.launch

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun MatchDetailsScreen(
    matchId: String,
    joinCode: String = "",
    onOpenScorer: () -> Unit = {},
    onBack: () -> Unit = {},
    viewModel: MatchDetailsViewModel = viewModel(),
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsState()
    val liveAds by viewModel.liveAds.collectAsState()
    val loadContext = androidx.compose.ui.platform.LocalContext.current
    // Football scorer opened from the detail's "Score" button (creator only). Rendered
    // as a full-screen overlay so the match creator can score an existing match without
    // going back through the create wizard.
    var footballScoring by androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(false) }
    val matchRepo = androidx.compose.runtime.remember { com.haraan.app.data.MatchRepository() }
    val detailScope = rememberCoroutineScope()

    // Fetch real match data: by share code (private) when present, else by id.
    LaunchedEffect(matchId, joinCode) {
        val token = com.haraan.app.data.TokenStore.getToken(loadContext)
        viewModel.load(id = matchId, code = joinCode, token = token)
    }

    // Realtime push: subscribe to this match's channel while the screen is open, and
    // refetch the instant a "match.updated" arrives — the WebSocket path that makes the
    // score tick up the moment the scorer taps, not on the 12s poll below (kept as a
    // fallback for when the socket is down). Public matches only (a channel needs the id).
    if (matchId.isNotBlank()) {
        androidx.compose.runtime.DisposableEffect(matchId) {
            com.haraan.app.data.RealtimeClient.subscribe("match.$matchId")
            onDispose { com.haraan.app.data.RealtimeClient.unsubscribe("match.$matchId") }
        }
        LaunchedEffect(matchId, joinCode) {
            com.haraan.app.data.MatchRealtimeBus.updates.collect { id ->
                if (id == matchId) {
                    val token = com.haraan.app.data.TokenStore.getToken(loadContext)
                    viewModel.refresh(id = matchId, code = joinCode, token = token)
                }
            }
        }
    }

    // Live auto-refresh — ticks the score every 12s while the match is live, AND re-pulls
    // the instant the viewer returns to the screen / foregrounds the app, so they never
    // stare at a frozen "LIVE" board. Lifecycle-aware: polling fully pauses off-screen
    // (no battery/data drain in the background) and resumes on return. A finished match
    // refreshes once on resume for the final score, then stops hitting the network.
    AutoRefresh(12_000L, true, matchId, joinCode) {
        val s = viewModel.uiState.value
        if (s is MatchScreenState.Success && !s.data.isLive) return@AutoRefresh
        val token = com.haraan.app.data.TokenStore.getToken(loadContext)
        viewModel.refresh(id = matchId, code = joinCode, token = token)
    }

    // Compact "premium" content scale: shrink every dp + sp uniformly so the screen
    // doesn't feel oversized on compact / lower-density devices. Dial via the factor.
    val baseDensity = LocalDensity.current
    CompositionLocalProvider(
        LocalDensity provides Density(baseDensity.density * 0.90f, baseDensity.fontScale),
        // Brand typeface for every bare Text in the header + all four tabs (they only set
        // size/weight/colour, so the family is inherited from here).
        androidx.compose.material3.LocalTextStyle provides
            androidx.compose.ui.text.TextStyle(fontFamily = com.haraan.app.theme.Poppins)
    ) {
    when (val state = uiState) {
        is MatchScreenState.Loading -> {
            MatchDetailSkeleton()
        }
        is MatchScreenState.Error -> {
            Box(modifier = Modifier.fillMaxSize().background(Color.White), contentAlignment = Alignment.Center) {
                Text("Error: ${state.message}", color = Color.Red, fontSize = 16.sp)
            }
        }
        is MatchScreenState.Empty -> {
            Box(modifier = Modifier.fillMaxSize().background(Color.White), contentAlignment = Alignment.Center) {
                Text("No match data available", color = Color.Gray, fontSize = 16.sp)
            }
        }
        is MatchScreenState.Success if state.data.sport.equals("football", ignoreCase = true) -> {
            // Football gets its own screen. The five tabs below are cricket's — Info,
            // Commentary, Live, Scorecard, MVP only mean anything for a ball-by-ball
            // sport, and a football match rendered through them showed a scorecard
            // with every number blank.
            FootballMatchScreen(state = state.data, onBack = onBack, onScore = { footballScoring = true }, modifier = modifier)
        }
        is MatchScreenState.Success -> {
            // Open on Commentary by default (index 1 in tabsList).
            val pagerState = rememberPagerState(initialPage = 1, pageCount = { tabsList.size })
            val coroutineScope = rememberCoroutineScope()
            val view = androidx.compose.ui.platform.LocalView.current

            // Tab taps tick immediately (in MatchTabs). Swipes don't, so tick when the pager
            // *settles* on a new page — but skip the settle that a tap itself caused, so a tap
            // isn't felt twice. `tapDriven` is raised by onTabSelected and consumed here.
            var tapDriven by androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(false) }
            var firstSettle by androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(true) }
            LaunchedEffect(pagerState.settledPage) {
                if (firstSettle) { firstSettle = false; return@LaunchedEffect }
                if (tapDriven) tapDriven = false else hapticTick(view)
            }
            val listState = androidx.compose.foundation.lazy.rememberLazyListState()
            val scrollOffset by androidx.compose.runtime.remember {
                androidx.compose.runtime.derivedStateOf {
                    if (listState.firstVisibleItemIndex == 0) listState.firstVisibleItemScrollOffset else 1000
                }
            }

            BoxWithConstraints(modifier = modifier.fillMaxSize().background(CrexColors.Background)) {
                val screenHeight = maxHeight
                
                androidx.compose.foundation.lazy.LazyColumn(state = listState, modifier = Modifier.fillMaxSize()) {
                    item {
                        // Hero with parallax offset
                        MatchHeader(state = state.data, scrollOffset = scrollOffset, onScoreClick = onOpenScorer, onBack = onBack)
                    }

                    stickyHeader {
                        // Floating Tabs
                        MatchTabs(
                            selectedTabIndex = pagerState.currentPage,
                            liveActive = state.data.isLive,
                            onTabSelected = { index ->
                                tapDriven = true
                                coroutineScope.launch {
                                    pagerState.animateScrollToPage(index)
                                }
                            }
                        )
                    }

                    item {
                        // Dynamic Pager Content
                        HorizontalPager(
                            state = pagerState,
                            modifier = Modifier.fillMaxWidth().height(screenHeight - 64.dp) // 64dp approx for tabs
                        ) { page ->
                            when (page) {
                                0 -> InfoTab(state = state.data)
                                1 -> CommentaryTab(state = state.data)
                                2 -> LiveTab(state = state.data, ads = liveAds)
                                3 -> ScorecardTab(state = state.data)
                                4 -> MvpTab(state = state.data)
                            }
                        }
                    }
                }
            }
        }
    }
    }

    // Football scorer overlay — the "Score" button on a football detail opens it here,
    // wired to the same event API as the create-flow scorer. Refreshes the detail on exit.
    val scoringState = uiState
    if (footballScoring && scoringState is MatchScreenState.Success &&
        scoringState.data.sport.equals("football", ignoreCase = true)
    ) {
        val d = scoringState.data
        val fb = d.football ?: FootballState()
        FootballScorerScreen(
            setup = FootballScorerSetup(
                matchId = matchId,
                teamA = d.team1FullName.ifBlank { d.team1 },
                teamB = d.team2FullName.ifBlank { d.team2 },
                formatLabel = d.competition,
                joinCode = joinCode,
                squadA = d.homeSquad,
                squadB = d.awaySquad,
                initialHome = fb.timeline.lastOrNull()?.homeScore ?: 0,
                initialAway = fb.timeline.lastOrNull()?.awayScore ?: 0,
            ),
            onGoal = { side, player, _, minute ->
                val tok = com.haraan.app.data.TokenStore.getSignedInToken(loadContext)
                if (tok == null) null else matchRepo.recordMatchEvent(tok, matchId, "goal", side, minute, player)
            },
            onCard = { side, player, kind, minute ->
                val tok = com.haraan.app.data.TokenStore.getSignedInToken(loadContext)
                if (tok == null) null else matchRepo.recordMatchEvent(tok, matchId, kind, side, minute, player)
            },
            onUndoGoal = { side ->
                val tok = com.haraan.app.data.TokenStore.getSignedInToken(loadContext)
                if (tok == null) null else matchRepo.undoMatchEvent(tok, matchId, side)
            },
            onStat = { kind, side, inc ->
                val tok = com.haraan.app.data.TokenStore.getSignedInToken(loadContext)
                if (tok != null) matchRepo.adjustStat(tok, matchId, kind, side, inc)
            },
            finishMatch = {
                val tok = com.haraan.app.data.TokenStore.getSignedInToken(loadContext)
                if (tok != null) matchRepo.completeMatch(tok, matchId)
            },
            onDone = {
                footballScoring = false
                detailScope.launch {
                    val tok = com.haraan.app.data.TokenStore.getToken(loadContext)
                    viewModel.refresh(id = matchId, code = joinCode, token = tok)
                }
            },
            modifier = Modifier.fillMaxSize(),
        )
    }
}

@Composable
fun PlaceholderTab(name: String) {
    Box(
        modifier = Modifier.fillMaxSize().background(Color(0xFFF8FAFC)),
        contentAlignment = Alignment.Center
    ) {
        Text("Coming Soon: $name", color = Color.Gray, fontSize = 18.sp)
    }
}
