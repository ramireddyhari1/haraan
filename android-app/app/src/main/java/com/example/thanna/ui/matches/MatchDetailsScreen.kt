package com.example.thanna.ui.matches

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.ui.matches.tabs.InfoTab
import com.example.thanna.ui.matches.tabs.FantasyTab
import com.example.thanna.ui.matches.tabs.CommentaryTab
import com.example.thanna.ui.matches.tabs.LiveTab
import com.example.thanna.ui.matches.tabs.ScorecardTab
import com.example.thanna.ui.matches.tabs.StatsTab
import kotlinx.coroutines.launch

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun MatchDetailsScreen(
    matchId: String,
    viewModel: MatchDetailsViewModel = viewModel(),
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsState()

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
            val pagerState = rememberPagerState(pageCount = { tabsList.size })
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
                        MatchHeader(state = state.data, scrollOffset = scrollOffset)
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
                                0 -> InfoTab()
                                1 -> FantasyTab()
                                2 -> CommentaryTab()
                                3 -> LiveTab()
                                4 -> ScorecardTab()
                                5 -> StatsTab()
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
