package com.example.thanna.ui.matches

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
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.ui.matches.tabs.InfoTab
import com.example.thanna.ui.matches.tabs.CommentaryTab
import com.example.thanna.ui.matches.tabs.LiveTab
import com.example.thanna.ui.matches.tabs.ScorecardTab
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
    val loadContext = androidx.compose.ui.platform.LocalContext.current

    // Fetch real match data: by share code (private) when present, else by id.
    LaunchedEffect(matchId, joinCode) {
        val token = com.example.thanna.data.TokenStore.getToken(loadContext)
        viewModel.load(id = matchId, code = joinCode, token = token)
    }

    // Live auto-refresh — poll every 12s while the match is live so the score ticks on
    // its own (no more frozen "LIVE" screen). Stops once the match is no longer live.
    LaunchedEffect(matchId, joinCode) {
        while (true) {
            kotlinx.coroutines.delay(12_000)
            val s = viewModel.uiState.value
            if (s is MatchScreenState.Success && !s.data.isLive) continue
            val token = com.example.thanna.data.TokenStore.getToken(loadContext)
            viewModel.refresh(id = matchId, code = joinCode, token = token)
        }
    }

    // Compact "premium" content scale: shrink every dp + sp uniformly so the screen
    // doesn't feel oversized on compact / lower-density devices. Dial via the factor.
    val baseDensity = LocalDensity.current
    CompositionLocalProvider(
        LocalDensity provides Density(baseDensity.density * 0.90f, baseDensity.fontScale),
        // Brand typeface for every bare Text in the header + all four tabs (they only set
        // size/weight/colour, so the family is inherited from here).
        androidx.compose.material3.LocalTextStyle provides
            androidx.compose.ui.text.TextStyle(fontFamily = com.example.thanna.theme.Poppins)
    ) {
    when (val state = uiState) {
        is MatchScreenState.Loading -> {
            Box(modifier = Modifier.fillMaxSize().background(Color(0xFF07162B)), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = CrexColors.AccentRed)
            }
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
        is MatchScreenState.Success -> {
            // Open on Commentary by default (index 1 in tabsList).
            val pagerState = rememberPagerState(initialPage = 1, pageCount = { tabsList.size })
            val coroutineScope = rememberCoroutineScope()
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
                            onTabSelected = { index ->
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
                                2 -> LiveTab(state = state.data)
                                3 -> ScorecardTab(state = state.data)
                            }
                        }
                    }
                }
            }
        }
    }
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
